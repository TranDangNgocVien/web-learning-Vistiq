<?php

namespace Tests\Admin\Feature\Integration;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Models\User as CoreUser;
use VistiqCore\Services\Analytics\AnalyticsService;
use VistiqCore\Services\Analytics\LeaderboardService;
use VistiqCore\Services\Assessment\Attempts\AttemptService;

class AdminOperationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public static function screens(): array
    {
        return array_map(fn ($path) => [$path], ['/admin', '/admin/analytics', '/admin/questions/listening', '/admin/questions/create', '/admin/readings', '/admin/audio', '/admin/groups', '/admin/media', '/admin/exams', '/admin/exams/create', '/admin/attempts', '/admin/grading', '/admin/knowledge', '/admin/knowledge/create', '/admin/leaderboard', '/admin/users', '/admin/roles', '/admin/settings', '/admin/settings/database', '/admin/audit', '/admin/search?q=test']);
    }

    #[DataProvider('screens')]
    public function test_admin_screens_render_with_real_data(string $path): void
    {
        $this->actingAs($this->admin())->get($path)->assertOk();
    }

    public function test_last_super_admin_is_protected_and_sessions_are_revoked(): void
    {
        $super = $this->admin();
        $this->actingAs($super)->postJson('/admin/users/'.$super->id.'/lock')->assertConflict();
        $learner = User::factory()->create();
        $this->post('/admin/users/'.$learner->id.'/revoke-sessions')->assertRedirect();
        $this->assertSame(1, $learner->fresh()->session_version);
        $this->actingAs($learner->fresh(), 'admin')->withSession(['admin_session_version' => 0])->getJson('/admin')->assertUnauthorized();
        $this->actingAs($super)->post('/admin/users/'.$learner->id.'/lock')->assertRedirect();
        $this->assertSame('locked', $learner->fresh()->status);
    }

    public function test_content_editor_cannot_manage_users_and_reviewer_cannot_edit(): void
    {
        $editor = $this->admin('content-admin');
        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
        $this->actingAs($this->admin('reviewer'))->postJson('/admin/exams', ['title' => 'Denied'])->assertForbidden();
        $this->get('/admin/demo/admin-settings')->assertRedirect('/admin/settings');
        $this->get('/admin/settings')->assertForbidden();
    }

    public function test_profile_shows_related_reports_and_unchecked_roles_can_be_removed(): void
    {
        $super = $this->admin();
        $learner = $this->admin('content-admin');
        $attempt = app(AttemptService::class)->start($this->publishedExam(), $learner);
        DB::table('attempt_reports')->insert(['exam_attempt_id' => $attempt->id, 'reported_by' => $learner->id, 'attempt_report_reason_id' => DB::table('attempt_report_reasons')->value('id'), 'details' => 'Check this answer', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($super)->get('/admin/users/'.$learner->id)->assertOk()->assertSee('Check this answer');
        $this->post('/admin/users/'.$learner->id.'/roles')->assertRedirect();
        $this->assertSame(0, $learner->fresh()->roles()->count());
        $this->postJson('/admin/users/'.$super->id.'/roles')->assertConflict();
    }

    public function test_media_rejects_executable_and_persists_disk_and_metadata(): void
    {
        config(['services.cloudinary' => ['cloud_name' => 'test-cloud', 'api_key' => 'test-key', 'api_secret' => 'test-secret']]);
        Http::fake(['api.cloudinary.com/*' => Http::response(['public_id' => 'test-diagram', 'secure_url' => 'https://cdn.example.test/diagram.png', 'asset_id' => 'test-asset'])]);
        $this->actingAs($this->admin('content-admin'));
        $this->postJson('/admin/media', ['file' => UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;')])->assertUnprocessable();
        $this->post('/admin/media', ['file' => UploadedFile::fake()->createWithContent('diagram.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6oFEAAAAASUVORK5CYII=')), 'title' => 'Map'])->assertRedirect();
        $asset = DB::table('media_assets')->first();
        $this->assertSame('cloudinary', $asset->disk);
        $this->assertSame('test-diagram', $asset->path);
        $this->assertSame('https://cdn.example.test/diagram.png', json_decode($asset->metadata, true)['secure_url']);
        Http::assertSentCount(1);
    }

    public function test_password_reset_uses_broker_and_backup_requires_current_password(): void
    {
        Notification::fake();
        $super = $this->admin();
        $this->post('/admin/forgot-password', ['email' => $super->email])->assertRedirect();
        Notification::assertSentTo(CoreUser::findOrFail($super->id), ResetPassword::class);
        $this->actingAs($super)->postJson('/admin/settings/database/backup', ['password' => 'wrong', 'include_schema' => 1, 'include_data' => 1])->assertUnprocessable();
    }

    public function test_leaderboard_and_analytics_use_real_completed_attempts_and_reset_keeps_history(): void
    {
        $exam = $this->publishedExam();
        $service = app(AttemptService::class);
        $attempt = $service->start($exam, User::factory()->create());
        $service->saveAnswers($attempt, [['item_key' => 'q1', 'response' => ['options' => ['B']]]]);
        $service->submit($attempt);
        $this->assertSame(1, app(LeaderboardService::class)->rankings($exam)->total());
        $metrics = app(AnalyticsService::class)->dashboard();
        $this->assertSame(1, $metrics['completed']);
        $this->actingAs($this->admin())->post('/admin/leaderboard/'.$exam->id.'/reset')->assertRedirect();
        $this->assertDatabaseCount('exam_attempts', 1);
        $this->assertSame(0, app(LeaderboardService::class)->rankings($exam->fresh())->total());
    }

    public function test_complete_authoring_to_publish_to_exam_to_result_flow(): void
    {
        $editor = $this->admin('content-admin');
        $reviewer = $this->admin('reviewer');
        $super = $this->admin();
        $part = DB::table('exam_parts')->where('code', 'part-5')->value('id');
        $this->actingAs($editor)->post('/admin/questions', ['exam_part_id' => $part, 'question_type_id' => DB::table('question_types')->where('code', 'multiple_choice')->value('id'), 'question_difficulty_id' => DB::table('question_difficulties')->value('id'), 'prompt' => 'A real question', 'options' => ['A' => 'one', 'B' => 'two', 'C' => 'three', 'D' => 'four'], 'correct_option' => 'B'])->assertRedirect();
        $question = QuestionBankItem::first();
        $this->post('/admin/content/question/'.$question->id.'/review')->assertRedirect();
        $this->actingAs($reviewer)->post('/admin/content/question/'.$question->id.'/approve', $this->reviewData('question', $question->id))->assertRedirect();
        $this->actingAs($super)->post('/admin/content/question/'.$question->id.'/publish')->assertRedirect();
        $this->actingAs($editor)->post('/admin/exams', ['title' => 'E2E mini', 'exam_type_id' => DB::table('exam_types')->where('code', 'toeic')->value('id'), 'mode' => 'mini', 'sections' => [['exam_part_id' => $part, 'question_ids' => [$question->id]]], 'policies' => ['duration_minutes' => 30]])->assertRedirect();
        $exam = ExamTemplate::first();
        $this->post('/admin/exams/'.$exam->id.'/review')->assertRedirect();
        $this->actingAs($reviewer)->post('/admin/exams/'.$exam->id.'/approve', $this->reviewData('exam', $exam->id))->assertRedirect();
        $this->actingAs($super)->get('/admin/exams/'.$exam->id.'/preview')->assertOk();
        $this->post('/admin/exams/'.$exam->id.'/publish')->assertRedirect();
        $service = app(AttemptService::class);
        $attempt = $service->start($exam->fresh(), User::factory()->create());
        $service->saveAnswers($attempt, [['item_key' => 'q'.$question->id, 'response' => ['options' => ['B']]]]);
        $service->submit($attempt);
        $this->assertEquals(100, $service->result($attempt)['scaled_score']);
    }
}
