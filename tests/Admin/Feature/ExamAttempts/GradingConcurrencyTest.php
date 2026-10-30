<?php

namespace Tests\Admin\Feature\ExamAttempts;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Services\Assessment\Attempts\AttemptService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;

class GradingConcurrencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_omitting_the_grade_token_cannot_bypass_regrade_conflict_protection(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $service = app(AttemptService::class);
        $attempt = $service->start($this->publishedExam([$this->item(['type' => 'essay', 'skill' => 'writing'])]), User::factory()->create());
        $service->submit($attempt);
        $answer = $attempt->answers()->firstOrFail();
        $url = '/admin/grading/'.$answer->id;
        $data = ['criteria' => array_fill_keys(QuestionRegistry::WRITING_CRITERIA, 6), 'feedback' => 'Reviewed'];
        $this->actingAs($this->admin('reviewer'))->postJson($url, $data)->assertRedirect();
        $before = $answer->fresh()->grade;
        $this->postJson($url, $data + ['reason' => 'Regrade without version'])->assertUnprocessable()->assertJsonValidationErrors('grade_token');
        $this->assertSame($before, $answer->fresh()->grade);
    }
}
