<?php

namespace Tests\Admin\Feature\Integration;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Services\Assessment\Attempts\AttemptService;
use VistiqCore\Services\System\SettingsService;

class ThrottleContractTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_autosave_does_not_consume_exam_start_budget(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $learner = User::factory()->create();
        $exam = $this->publishedExam();
        $attempt = app(AttemptService::class)->start($exam, $learner);
        $this->actingAs($learner);
        foreach (range(1, 12) as $i) {
            $this->postJson('/attempts/'.$attempt->id.'/answers', ['answers' => []])->assertOk();
        }
        $next = $this->publishedExam();
        $this->post('/exams/'.$next->id.'/start')->assertRedirect();
    }

    public function test_backup_limits_remain_enforced_and_do_not_block_settings_or_restore(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin());
        foreach (range(1, 3) as $i) {
            $this->postJson('/admin/settings/database/backup', ['password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/admin/settings/database/backup', ['password' => 'wrong'])->assertTooManyRequests();
        $this->put('/admin/settings/exam', ['value' => app(SettingsService::class)->get('exam')])->assertRedirect();
        $this->postJson('/admin/settings/database/restore', ['password' => 'wrong'])->assertUnprocessable();
    }
}
