<?php

namespace Tests\Admin\Feature\Analytics;

use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Admin\TestCase;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Services\Analytics\OverviewService;

class OverviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_overview_counts_grouped_days_top_exams_topics_and_growth_use_database(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(12, 0));
        $this->actingAs($actor = $this->admin());
        $exam = $this->publishedExam();
        foreach ([['completed', false, 1], ['submitted', false, 1], ['cancelled', false, 1], ['completed', true, 1], ['completed', false, 16]] as [$status,$test,$days]) {
            ExamAttempt::create(['user_id' => $actor->id, 'exam_template_id' => $exam->id, 'attempt_number' => random_int(1, 1000000), 'status' => $status, 'started_at' => now()->subDays($days), 'submitted_at' => $status === 'completed' ? now()->subDays($days) : null, 'snapshot' => ['is_test' => $test]]);
        }
        $question = QuestionBankItemFactory::new()->create();
        $tag = DB::table('tags')->insertGetId(['name' => 'Business vocabulary', 'slug' => 'business-vocabulary']);
        DB::table('learning_item_tags')->insert(['learning_item_id' => $question->learning_item_id, 'tag_id' => $tag]);
        $data = app(OverviewService::class)->read();
        $attempt = collect($data['metrics'])->firstWhere('key', 'attempts');
        $this->assertSame(3, $attempt['total']);
        $this->assertSame(2, $attempt['current']);
        $this->assertEquals(100, $attempt['change']);
        $this->assertSame(14, $data['series']->count());
        $this->assertEquals(2, $data['distribution']->sum('total'));
        $this->assertEquals(2, $data['topExams']->first()->total);
        $this->assertEquals(1, $data['topics']->first()->total);
        $this->get('/admin')->assertOk()->assertSee('Lượt làm bài theo ngày')->assertSee($exam->title)->assertSee('Business vocabulary')->assertSee($actor->email);
        $this->get('/admin?days=31')->assertSessionHasErrors('days');
    }

    public function test_overview_does_not_disclose_users_or_audit_to_content_editor(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $secret = $this->admin();
        $this->actingAs($this->admin('content-admin'))->get('/admin')->assertOk()->assertDontSee($secret->email)->assertDontSee('Người dùng mới đăng ký')->assertDontSee('Hoạt động gần đây');
        $data = app(OverviewService::class)->read();
        $this->assertTrue($data['newUsers']->isEmpty());
        $this->assertTrue($data['activities']->isEmpty());
    }
}
