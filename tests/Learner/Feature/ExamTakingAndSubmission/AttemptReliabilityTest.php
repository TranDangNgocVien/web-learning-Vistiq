<?php

namespace Tests\Learner\Feature\ExamTakingAndSubmission;

use Database\Admin\Factories\ExamTemplateFactory;
use Database\Learner\Seeders\AssessmentCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Storage;
use Tests\Learner\TestCase;
use VistiqCore\Models\ExamRevision;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Services\Assessment\Attempts\AttemptService;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;
use VistiqLearner\Models\User;

class AttemptReliabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AssessmentCatalogSeeder::class);
        $this->freezeTime();
    }

    private function attempt(array $policies = [], string $type = 'multiple_choice'): mixed
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $exam = ExamTemplateFactory::new()->create(['status' => 'published']);
        $payload = ['schema_version' => 1, 'exam_id' => $exam->id, 'title' => $exam->title, 'exam_type' => 'toeic', 'mode' => 'mini', 'variant' => null, 'opens_at' => null, 'closes_at' => now()->addHours(2)->toIso8601String(), 'items' => [['key' => 'q1', 'type' => 'multiple_choice', 'section' => 0, 'skill' => 'reading', 'part' => 'part-5', 'prompt' => 'Pinned question', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_options' => ['B'], 'accepted_answers' => [], 'explanation' => 'Protected explanation', 'metadata' => [], 'stimuli' => []]], 'sections' => [['index' => 0, 'title' => 'Part 5', 'item_keys' => ['q1'], 'duration_minutes' => 0, 'timing_group' => 'reading']], 'policies' => array_replace(ExamPublishingService::POLICIES, $policies), 'scoring' => null];
        $payload['items'][0]['type'] = $type;
        if ($type === 'speaking') {
            $payload['items'][0]['skill'] = 'speaking';
        }
        $revision = ExamRevision::create(['exam_template_id' => $exam->id, 'number' => 1, 'payload' => $payload]);
        $exam->update(['published_revision_id' => $revision->id]);

        return app(AttemptService::class)->start($exam, $user);
    }

    public function test_retry_returns_same_version_and_changed_stale_answer_conflicts(): void
    {
        $attempt = $this->attempt();
        $url = '/attempts/'.$attempt->id.'/answers';
        $data = ['answers' => [['item_key' => 'q1', 'version' => 0, 'response' => ['options' => ['B']], 'spent_seconds' => 7, 'flagged' => true]]];
        $this->postJson($url, $data)->assertOk()->assertJsonPath('answers.0.version', 1);
        $this->postJson($url, $data)->assertOk()->assertJsonPath('answers.0.version', 1);
        $this->assertDatabaseCount('attempt_answers', 1);
        $data['answers'][0]['response']['options'] = ['A'];
        $this->postJson($url, $data)->assertConflict();
        $this->getJson('/attempts/'.$attempt->id.'/state')->assertOk()->assertJsonPath('answers.0.response.options.0', 'B');
        $this->get('/attempts/'.$attempt->id)->assertOk()->assertSee('Mode Exam')->assertSee('data-exam-runner', false);
    }

    public function test_history_ignores_injected_user_id_and_result_enforces_ownership(): void
    {
        $first = $this->attempt();
        $owner = auth()->user();
        app(AttemptService::class)->submit($first);
        $second = $this->attempt();
        $this->actingAs($owner);
        $response = $this->get('/attempts/history?user='.$second->user_id)->assertOk()->assertSee($first->snapshot['title'])->assertDontSee($second->snapshot['title']);
        $this->assertSame(1, $response->viewData('attempts')->total());
        $this->get('/attempts/history?to=2099-01-01')->assertOk();
        $this->get('/attempts/'.$second->id)->assertNotFound();
        $this->getJson('/attempts/'.$second->id.'/result')->assertNotFound();
        $this->postJson('/attempts/'.$second->id.'/submit')->assertNotFound();
    }

    public function test_submit_retry_uses_server_duration_and_after_close_review_policy(): void
    {
        $attempt = $this->attempt(['answer_review' => 'after_close', 'explanation_review' => 'after_close']);
        $this->travel(10)->seconds();
        $url = '/attempts/'.$attempt->id.'/submit';
        $this->postJson($url, ['score' => 9999, 'duration_seconds' => 0, 'user_id' => 9999])->assertOk()->assertJsonPath('result.details.duration_seconds', 10)->assertJsonPath('result.review', []);
        $this->postJson($url)->assertOk()->assertJsonPath('result.details.duration_seconds', 10);
        $this->assertDatabaseCount('attempt_results', 1);
        $this->assertSame(1, DB::table('user_activities')->where('action', 'exam.submit')->count());
        $this->get('/attempts/'.$attempt->id.'/result')->assertOk()->assertDontSee('Protected explanation');
        $this->travel(2)->hours();
        $this->getJson('/attempts/'.$attempt->id.'/result')->assertOk()->assertJsonPath('review.q1.explanation', 'Protected explanation');
    }

    public function test_resume_does_not_return_unnoticed_timed_out_attempt(): void
    {
        $attempt = $this->attempt(['duration_minutes' => 1]);
        $this->travel(61)->seconds();
        $response = $this->postJson('/exams/'.$attempt->exam_template_id.'/start')->assertOk();
        $this->assertNotSame($attempt->id, $response->json('id'));
        $this->assertSame('completed', $attempt->fresh()->status);
        $this->assertDatabaseCount('attempt_results', 1);
    }

    public function test_pause_locks_save_and_foreign_media_is_not_readable(): void
    {
        $attempt = $this->attempt(['pause_allowed' => true]);
        $this->postJson('/attempts/'.$attempt->id.'/pause')->assertOk();
        $this->postJson('/attempts/'.$attempt->id.'/answers', ['answers' => [['item_key' => 'q1', 'version' => 0, 'response' => ['options' => ['B']]]]])->assertConflict();
        $asset = MediaAsset::create(['title' => 'Unrelated asset', 'disk' => 'public', 'path' => 'outside.png', 'mime_type' => 'image/png', 'size' => 1, 'created_by' => auth()->id()]);
        $this->get('/attempts/'.$attempt->id.'/media/'.$asset->id)->assertNotFound();
        $this->postJson('/attempts/'.$attempt->id.'/resume')->assertOk();
        $this->getJson('/attempts/history?from=not-a-date')->assertUnprocessable();
    }

    public function test_recording_upload_binds_asset_to_attempt_and_item(): void
    {
        Storage::fake('r2');
        $attempt = $this->attempt([], 'speaking');
        $owner = auth()->user();
        $upload = $this->post('/attempts/'.$attempt->id.'/recording', ['item_key' => 'q1', 'file' => UploadedFile::fake()->create('response.wav', 1, 'audio/wav')])->assertCreated();
        $assetId = $upload->json('asset_id');
        $this->assertSame($attempt->id, MediaAsset::findOrFail($assetId)->metadata['recording_attempt_id']);
        $this->postJson('/attempts/'.$attempt->id.'/answers', ['answers' => [['item_key' => 'q1', 'version' => 0, 'response' => ['asset_id' => $assetId]]]])->assertOk();
        $this->get('/attempts/'.$attempt->id.'/media/'.$assetId)->assertOk();
        app(AttemptService::class)->submit($attempt);
        $next = app(AttemptService::class)->start($attempt->exam, $owner);
        $this->postJson('/attempts/'.$next->id.'/answers', ['answers' => [['item_key' => 'q1', 'version' => 0, 'response' => ['asset_id' => $assetId]]]])->assertUnprocessable();
        $this->get('/attempts/'.$next->id.'/media/'.$assetId)->assertNotFound();
    }

    public function test_retry_ignores_json_object_key_order_without_advancing_version(): void
    {
        $attempt = $this->attempt();
        $snapshot = $attempt->snapshot;
        $snapshot['items'][0]['type'] = 'matching';
        $snapshot['items'][0]['metadata'] = ['answer_map' => ['long_key' => 'A', 'x' => 'B']];
        $attempt->update(['snapshot' => $snapshot]);
        $url = '/attempts/'.$attempt->id.'/answers';
        $data = ['answers' => [['item_key' => 'q1', 'version' => 0, 'response' => ['matching' => ['long_key' => 'A', 'x' => 'B']]]]];
        $this->postJson($url, $data)->assertOk()->assertJsonPath('answers.0.version', 1);
        $data['answers'][0]['response']['matching'] = ['x' => 'B', 'long_key' => 'A'];
        $this->postJson($url, $data)->assertOk()->assertJsonPath('answers.0.version', 1);
        $data['answers'][0]['response']['matching']['x'] = 'A';
        $this->postJson($url, $data)->assertConflict();
    }

    public function test_closing_while_paused_excludes_pause_from_automatic_submission_duration(): void
    {
        $attempt = $this->attempt(['pause_allowed' => true]);
        $this->travel(10)->seconds();
        $this->postJson('/attempts/'.$attempt->id.'/pause')->assertOk();
        $this->travel(2)->hours();
        $this->getJson('/attempts/'.$attempt->id.'/result')->assertOk()->assertJsonPath('details.duration_seconds', 10);
        $this->assertTrue($attempt->fresh()->submitted_at->equalTo($attempt->snapshot['closes_at']));
        $this->postJson('/attempts/'.$attempt->id.'/submit')->assertOk()->assertJsonPath('result.details.duration_seconds', 10);
        $this->assertDatabaseCount('attempt_results', 1);
    }

    public function test_closing_while_paused_keeps_duration_when_manual_submission_is_required(): void
    {
        $attempt = $this->attempt(['pause_allowed' => true, 'auto_submit' => false]);
        $snapshot = $attempt->snapshot;
        $snapshot['sections'][0]['duration_minutes'] = 1;
        $attempt->update(['snapshot' => $snapshot]);
        $this->travel(10)->seconds();
        $this->postJson('/attempts/'.$attempt->id.'/pause')->assertOk();
        $this->travel(2)->hours();
        $this->getJson('/attempts/'.$attempt->id.'/state')->assertOk()->assertJsonPath('status', 'expired');
        $this->postJson('/attempts/'.$attempt->id.'/submit')->assertOk()->assertJsonPath('result.details.duration_seconds', 10);
        $this->assertTrue($attempt->fresh()->submitted_at->equalTo($attempt->snapshot['closes_at']));
    }

    public function test_manual_submission_during_pause_counts_only_active_work_time(): void
    {
        $attempt = $this->attempt(['pause_allowed' => true]);
        $this->travel(10)->seconds();
        $this->postJson('/attempts/'.$attempt->id.'/pause')->assertOk();
        $this->travel(60)->seconds();
        $this->postJson('/attempts/'.$attempt->id.'/submit')->assertOk()->assertJsonPath('result.details.duration_seconds', 10);
        $this->postJson('/attempts/'.$attempt->id.'/submit')->assertOk()->assertJsonPath('result.details.duration_seconds', 10);
        $this->assertSame(60, $attempt->fresh()->paused_seconds);
    }

    public function test_disabled_review_later_is_enforced_by_the_answer_endpoint(): void
    {
        $attempt = $this->attempt(['review_later' => false]);
        $this->postJson('/attempts/'.$attempt->id.'/answers', ['answers' => [
            ['item_key' => 'q1', 'version' => 0, 'response' => ['options' => ['B']], 'flagged' => true],
        ]])->assertUnprocessable()->assertJsonValidationErrors('answers');
        $this->assertDatabaseCount('attempt_answers', 0);
    }
}
