<?php

namespace Tests\Learner\Feature\ExamTakingAndSubmission;

use Database\Admin\Factories\ExamTemplateFactory;
use Database\Learner\Seeders\AssessmentCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Tests\Learner\TestCase;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Models\ExamRevision;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;
use VistiqLearner\Models\User;

class AttemptWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AssessmentCatalogSeeder::class);
        $this->freezeTime();
    }

    private function exam(array $policies = [], string $mode = 'mini'): ExamTemplate
    {
        $exam = ExamTemplateFactory::new()->create(['status' => 'published']);
        $payload = ['schema_version' => 1, 'exam_id' => $exam->id, 'title' => $exam->title, 'exam_type' => 'toeic', 'mode' => $mode, 'variant' => null, 'opens_at' => null, 'closes_at' => null, 'items' => [['key' => 'q1', 'type' => 'multiple_choice', 'section' => 0, 'skill' => 'reading', 'part' => 'part-5', 'prompt' => 'Answer this', 'options' => ['A' => 'one', 'B' => 'two', 'C' => 'three', 'D' => 'four'], 'correct_options' => ['B'], 'accepted_answers' => [], 'explanation' => 'Hidden answer', 'metadata' => ['model_answer' => 'Hidden model'], 'stimuli' => []]], 'sections' => [['index' => 0, 'title' => 'Part 5', 'item_keys' => ['q1'], 'duration_minutes' => 0, 'timing_group' => 'reading']], 'policies' => array_replace(ExamPublishingService::POLICIES, $policies), 'scoring' => null];
        $revision = ExamRevision::create(['exam_template_id' => $exam->id, 'number' => 1, 'payload' => $payload]);
        $exam->update(['published_revision_id' => $revision->id]);

        return $exam;
    }

    private function start(array $policies = [], string $mode = 'mini'): ExamAttempt
    {
        $this->actingAs(User::factory()->create());
        $exam = $this->exam($policies, $mode);
        $response = $this->postJson('/exams/'.$exam->id.'/start')->assertOk();

        return ExamAttempt::findOrFail($response->json('id'));
    }

    public function test_catalog_runner_and_result_are_available_only_to_learner_owner(): void
    {
        $attempt = $this->start();
        $this->get('/exams')->assertOk();
        $this->get('/attempts/'.$attempt->id)->assertOk();
        $state = $this->getJson('/attempts/'.$attempt->id.'/state')->assertOk();
        $state->assertDontSee('correct_options')->assertDontSee('Hidden answer')->assertDontSee('Hidden model');
        $this->postJson('/attempts/'.$attempt->id.'/answers', ['answers' => [['item_key' => 'q1', 'version' => 0, 'response' => ['options' => ['B']]]]])->assertOk()->assertJsonPath('answers.0.version', 1);
        $this->postJson('/attempts/'.$attempt->id.'/submit')->assertOk()->assertJsonPath('result.scaled_score', 100);
        $this->postJson('/attempts/'.$attempt->id.'/submit')->assertOk();
        $this->assertDatabaseCount('attempt_results', 1);
        $this->get('/attempts/'.$attempt->id.'/result')->assertOk();
        $this->actingAs(User::factory()->create())->getJson('/attempts/'.$attempt->id.'/state')->assertNotFound();
        $this->postJson('/attempts/'.$attempt->id.'/submit')->assertNotFound();
    }

    public function test_approved_exam_is_visible_and_startable_without_a_separate_publish_state(): void
    {
        $this->actingAs(User::factory()->create());
        $exam = $this->exam();
        $exam->update(['status' => 'approved', 'draft_status' => 'approved', 'published_at' => null]);

        $this->get('/exams')->assertOk()->assertSee($exam->title)->assertSee('đề thi đã duyệt');
        $response = $this->postJson('/exams/'.$exam->id.'/start')->assertOk();
        $attempt = ExamAttempt::findOrFail($response->json('id'));
        $this->assertSame((int) $exam->published_revision_id, (int) $attempt->exam_revision_id);
        $this->getJson('/exams/'.$exam->id.'/leaderboard')->assertOk();
    }

    public function test_unknown_key_invalid_option_stale_version_and_post_deadline_answers_are_rejected(): void
    {
        $attempt = $this->start(['duration_minutes' => 1]);
        $url = '/attempts/'.$attempt->id.'/answers';
        $this->postJson($url, ['answers' => [['item_key' => 'q999', 'response' => []]]])->assertUnprocessable();
        $this->postJson($url, ['answers' => [['item_key' => 'q1', 'response' => ['options' => ['Z']]]]])->assertUnprocessable();
        $this->postJson($url, ['answers' => [['item_key' => 'q1', 'version' => 0, 'response' => ['options' => ['B']]]]])->assertOk();
        $this->postJson($url, ['answers' => [['item_key' => 'q1', 'version' => 0, 'response' => ['options' => ['A']]]]])->assertConflict();
        $this->travel(61)->seconds();
        $this->postJson($url, ['answers' => [['item_key' => 'q1', 'version' => 1, 'response' => ['options' => ['A']]]]])->assertConflict();
        $this->assertSame('completed', $attempt->fresh()->status);
        $this->assertEquals(1, DB::table('attempt_results')->value('raw_score'));
    }

    public function test_review_policy_never_hides_keys_even_after_submit(): void
    {
        $attempt = $this->start(['answer_review' => 'never', 'explanation_review' => 'never']);
        $this->postJson('/attempts/'.$attempt->id.'/submit')->assertOk()->assertJsonPath('result.review', []);
        $this->getJson('/attempts/'.$attempt->id.'/result')->assertOk()->assertDontSee('correct_options')->assertDontSee('Hidden model');
    }

    public function test_full_simulation_overrides_unsafe_policies_and_submits_on_fullscreen_exit(): void
    {
        $attempt = $this->start([
            'pause_allowed' => true,
            'auto_submit' => false,
            'answer_review' => 'after_each',
            'explanation_review' => 'after_each',
            'require_fullscreen' => true,
        ], 'full');

        $policies = $attempt->snapshot['policies'];
        $this->assertFalse($policies['pause_allowed']);
        $this->assertTrue($policies['auto_submit']);
        $this->assertSame('after_submit', $policies['answer_review']);
        $this->assertSame('after_submit', $policies['explanation_review']);
        $this->postJson('/attempts/'.$attempt->id.'/pause')->assertForbidden();

        $answer = $this->postJson('/attempts/'.$attempt->id.'/answers', [
            'answers' => [['item_key' => 'q1', 'version' => 0, 'response' => ['options' => ['B']]]],
        ])->assertOk()->json('answers.0');
        $this->assertArrayNotHasKey('review', $answer);
        $this->assertArrayNotHasKey('grade', $answer);

        $this->postJson('/attempts/'.$attempt->id.'/events', ['type' => 'FULLSCREEN_EXIT', 'event_key' => 'fullscreen-exit-test'])
            ->assertOk()
            ->assertJsonPath('submitted', true)
            ->assertJsonPath('message', 'Đã thoát toàn màn hình. Bài thi được nộp tự động.');
        $this->assertNotNull($attempt->fresh()->submitted_at);
    }

    public function test_full_simulation_deadline_submits_even_for_a_legacy_snapshot_with_auto_submit_disabled(): void
    {
        $attempt = $this->start(['duration_minutes' => 1], 'full');
        $snapshot = $attempt->snapshot;
        $snapshot['policies']['auto_submit'] = false;
        $attempt->update(['snapshot' => $snapshot]);

        $this->travel(61)->seconds();
        $this->getJson('/attempts/'.$attempt->id.'/state')->assertOk();

        $this->assertNotNull($attempt->fresh()->submitted_at);
    }

    public function test_admin_authoring_and_admin_demo_are_not_exposed_in_learner_app(): void
    {
        $this->get('/admin/exams/create')->assertRedirect(route('admin.login'));
        $this->get('/demo/admin-exam-builder')->assertNotFound();
    }

    public function test_revoked_and_locked_sessions_cannot_start_or_continue_attempts(): void
    {
        $attempt = $this->start();
        $user = auth()->user();
        $user->forceFill(['session_version' => 1])->save();
        $this->actingAs($user->fresh())->withSession(['user_session_version' => 0])->getJson('/attempts/'.$attempt->id.'/state')->assertUnauthorized();
        $user->forceFill(['status' => 'locked'])->save();
        $this->actingAs($user)->getJson('/attempts/'.$attempt->id.'/state')->assertUnauthorized();
    }
}
