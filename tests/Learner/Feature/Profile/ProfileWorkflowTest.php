<?php

namespace Tests\Learner\Feature\Profile;

use Database\Admin\Factories\ExamTemplateFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Learner\TestCase;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Models\ExamRevision;
use VistiqCore\Models\LearningItem;
use VistiqCore\Services\Analytics\LeaderboardService;
use VistiqCore\Services\Assessment\Attempts\AttemptQueryService;
use VistiqCore\Services\System\AuditService;
use VistiqLearner\Models\User;

class ProfileWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('avatar.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP1sAAAAASUVORK5CYII='));
    }

    public function test_profile_requires_an_active_learner_and_demo_links_redirect(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/demo/profile')->assertRedirect('/profile');
        $this->get('/demo/history')->assertRedirect('/profile/history');
        $this->actingAs(User::factory()->create(['status' => 'locked']))->put('/profile', ['name' => 'Invalid'])->assertRedirect('/login');
    }

    public function test_profile_edits_only_session_owner_and_rejects_sensitive_fields(): void
    {
        $user = User::factory()->create(['name' => 'Original']);
        $other = User::factory()->create();
        $this->actingAs($user)->putJson('/profile', ['name' => 'Changed', 'email' => 'attack@example.test', 'user_id' => $other->id, 'roles' => [1], 'status' => 'locked'])->assertUnprocessable()->assertJsonValidationErrors(['email', 'user_id', 'roles', 'status']);
        $this->assertSame('Original', $user->fresh()->name);
        $this->put('/profile', ['name' => 'Updated owner', 'country_code' => 'vn', 'time_zone' => 'Asia/Ho_Chi_Minh', 'bio' => 'Own introduction'])->assertRedirect('/profile');
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'country_code' => 'VN', 'bio' => 'Own introduction']);
        $this->assertSame($other->name, $other->fresh()->name);
        $this->get('/profile')->assertOk()->assertSee('Updated owner')->assertSee('Own introduction')->assertDontSee('Nguyễn Minh Anh');
    }

    public function test_avatar_validation_checks_content_size_and_conflicting_removal(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $this->putJson('/profile', ['name' => 'Valid', 'avatar' => UploadedFile::fake()->createWithContent('image.png', 'not an image')])->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->putJson('/profile', ['name' => 'Valid', 'avatar' => UploadedFile::fake()->create('big.png', 2049, 'image/png')])->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->putJson('/profile', ['name' => 'Valid', 'avatar' => $this->image(), 'remove_avatar' => 1])->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_json_profile_save_returns_navigation_and_preserves_success_feedback(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->putJson('/profile', ['name' => 'Saved asynchronously'])->assertOk()->assertJsonPath('redirect', route('profile.show'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Saved asynchronously']);
        $this->get('/profile')->assertOk()->assertSee('Đã lưu thay đổi hồ sơ.');
    }

    public function test_avatar_replacement_and_removal_happen_after_successful_save(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $old = 'avatars/'.$user->id.'/old.png';
        Storage::disk('public')->put($old, 'old');
        DB::table('user_profiles')->insert(['user_id' => $user->id, 'avatar_path' => $old]);
        $this->actingAs($user)->put('/profile', ['name' => 'Owner', 'avatar' => $this->image()])->assertRedirect();
        $path = DB::table('user_profiles')->where('user_id', $user->id)->value('avatar_path');
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertMissing($old);
        $this->put('/profile', ['name' => 'Owner', 'remove_avatar' => 1])->assertRedirect();
        Storage::disk('public')->assertMissing($path);
        $this->assertNull(DB::table('user_profiles')->where('user_id', $user->id)->value('avatar_path'));
    }

    public function test_failed_save_preserves_old_avatar_and_cleans_new_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $old = 'avatars/'.$user->id.'/old.png';
        Storage::disk('public')->put($old, 'old');
        DB::table('user_profiles')->insert(['user_id' => $user->id, 'avatar_path' => $old]);
        $this->mock(AuditService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Simulated persistence failure'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($user)->put('/profile', ['name' => 'Owner', 'avatar' => $this->image()]);
            $this->fail('Expected failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated persistence failure', $exception->getMessage());
        }
        $this->assertSame([$old], Storage::disk('public')->allFiles());
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'avatar_path' => $old]);
    }

    public function test_history_filters_paginates_and_never_accepts_owner_from_query(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $exam = ExamTemplateFactory::new()->create(['title' => 'Real history exam']);
        foreach (range(1, 21) as $number) {
            ExamAttempt::create(['user_id' => $owner->id, 'exam_template_id' => $exam->id, 'attempt_number' => $number, 'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(), 'snapshot' => ['title' => 'Real history exam']]);
        }
        $private = ExamTemplateFactory::new()->create(['title' => 'Other private exam']);
        ExamAttempt::create(['user_id' => $other->id, 'exam_template_id' => $private->id, 'attempt_number' => 1, 'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(), 'snapshot' => ['title' => 'Other private exam']]);
        $this->actingAs($owner)->get('/profile/history?user='.$other->id)->assertOk()->assertSee('21 lượt làm đề')->assertSee('Real history exam')->assertDontSee('Other private exam')->assertSee('page=2', false);
        $this->get('/profile/history?status=paused')->assertOk()->assertSee('0 lượt làm đề');
        $this->getJson('/profile/history?from=2026-10-20&to=2026-10-01')->assertUnprocessable();
    }

    public function test_saved_profile_uses_knowledge_publication_and_owner_contract(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $item = LearningItem::create(['learning_item_type_id' => DB::table('learning_item_types')->where('code', 'idiom')->value('id'), 'canonical_text' => 'Saved real knowledge', 'slug' => 'saved-real', 'status' => 'published']);
        $this->actingAs($owner)->post('/knowledge/saved-real/save', ['personal_note' => 'Owner private note'])->assertRedirect();
        $this->get('/profile/saved')->assertOk()->assertSee('Saved real knowledge')->assertSee('Owner private note');
        $this->actingAs($other)->get('/profile/saved')->assertOk()->assertDontSee('Owner private note')->assertDontSee('Saved real knowledge');
        $item->update(['status' => 'draft']);
        $this->actingAs($owner)->get('/profile/saved')->assertOk()->assertDontSee('Saved real knowledge');
    }

    public function test_password_change_is_separate_requires_password_and_revokes_other_sessions(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123']);
        $this->actingAs($user)->putJson('/account/password', ['current_password' => 'wrong', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])->assertUnprocessable();
        $this->put('/account/password', ['current_password' => 'OldPassword123', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])->assertRedirect('/profile');
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->assertSame(1, (int) $user->fresh()->session_version);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password.change', 'entity_id' => $user->id]);
        $this->get('/profile')->assertOk();
    }

    public function test_overview_metrics_and_recent_activity_are_real_owner_data(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $exam = ExamTemplateFactory::new()->create(['title' => 'Profile ranked exam', 'status' => 'published']);
        $revision = ExamRevision::create(['exam_template_id' => $exam->id, 'number' => 1, 'payload' => ['title' => 'Profile ranked exam', 'items' => [], 'policies' => ['leaderboard' => true, 'anonymous' => true]]]);
        $exam->update(['published_revision_id' => $revision->id]);
        foreach ([[$owner, 75, 3, 1], [$other, 100, 4, 0]] as [$user,$score,$correct,$wrong]) {
            $attempt = ExamAttempt::create(['user_id' => $user->id, 'exam_template_id' => $exam->id, 'attempt_number' => 1, 'status' => 'completed', 'leaderboard_status' => 'approved', 'started_at' => now()->subMinutes(5), 'submitted_at' => now(), 'snapshot' => $revision->payload]);
            DB::table('attempt_results')->insert(['exam_attempt_id' => $attempt->id, 'raw_score' => $correct, 'calculated_at' => now(), 'scaled_score' => $score, 'grading_status' => 'completed', 'details' => json_encode(['correct' => $correct, 'wrong' => $wrong, 'unanswered' => 0, 'duration_seconds' => 300])]);
        }
        $summary = app(AttemptQueryService::class)->profileSummary($owner->id);
        $this->assertSame(1, $summary['submitted']);
        $this->assertEquals(75, $summary['accuracy']);
        $rank = app(LeaderboardService::class)->bestRankForUser($owner->id);
        $this->assertSame(2, $rank['rank']);
        $this->actingAs($owner)->get('/profile')->assertOk()->assertSee('75%')->assertSee('#2')->assertSee('Hoàn thành Profile ranked exam')->assertDontSee($other->email);
    }
}
