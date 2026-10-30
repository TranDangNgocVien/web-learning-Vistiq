<?php

namespace Tests\Admin\Feature\ExamAttempts;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Services\Assessment\Attempts\AttemptService;

class AttemptWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_snapshot_hides_keys_and_remains_unchanged_after_edit_and_submit_is_idempotent(): void
    {
        $user = User::factory()->create();
        $exam = $this->publishedExam();
        $service = app(AttemptService::class);
        $attempt = $service->start($exam, $user);
        $public = json_encode($service->state($attempt));
        $this->assertStringNotContainsString('correct_options', $public);
        $this->assertStringNotContainsString('Secret explanation', $public);
        $service->saveAnswers($attempt, [['item_key' => 'q1', 'version' => 0, 'spent_seconds' => 17, 'response' => ['options' => ['B']]]]);
        $this->assertSame(17, $service->state($attempt)['answers'][0]['spent_seconds']);
        $exam->update(['title' => 'New title', 'draft_payload' => ['items' => []]]);
        $service->submit($attempt);
        $service->submit($attempt);
        $this->assertSame(1, DB::table('attempt_results')->count());
        $this->assertEquals(1, DB::table('attempt_results')->value('raw_score'));
        $this->assertSame('Select the answer.', $attempt->fresh()->snapshot['items'][0]['prompt']);
        $this->assertSame(1, DB::table('user_activities')->where('action', 'exam.submit')->count());
    }

    public function test_deadline_submits_even_when_browser_is_closed(): void
    {
        $service = app(AttemptService::class);
        $attempt = $service->start($this->publishedExam([], ['duration_minutes' => 1]), User::factory()->create());
        $this->travel(61)->seconds();
        $this->artisan('attempts:expire')->assertSuccessful();
        $this->assertSame('completed', $attempt->fresh()->status);
        $this->assertEquals(1, json_decode(DB::table('attempt_results')->value('details'), true)['unanswered']);
    }

    public function test_pause_extends_duration_and_close_time_still_wins(): void
    {
        $service = app(AttemptService::class);
        $exam = $this->publishedExam([], ['pause_allowed' => true]);
        $attempt = $service->start($exam, User::factory()->create());
        $deadline = $attempt->expires_at->timestamp;
        $service->pause($attempt);
        $this->travel(120)->seconds();
        $service->pause($attempt, true);
        $this->assertSame($deadline + 120, $attempt->fresh()->expires_at->timestamp);
    }

    public function test_timeout_without_auto_submit_locks_answers_until_manual_submission(): void
    {
        $service = app(AttemptService::class);
        $user = User::factory()->create();
        $exam = $this->publishedExam([], ['duration_minutes' => 1, 'auto_submit' => false]);
        $attempt = $service->start($exam, $user);
        $service->saveAnswers($attempt, [['item_key' => 'q1', 'version' => 0, 'response' => ['options' => ['B']]]]);
        $this->travel(61)->seconds();
        $this->artisan('attempts:expire')->assertSuccessful();
        $this->assertSame('expired', $attempt->fresh()->status);
        $this->assertNull($attempt->fresh()->submitted_at);
        $this->assertSame($attempt->id, $service->start($exam, $user)->id);
        try {
            $service->saveAnswers($attempt, [['item_key' => 'q1', 'version' => 1, 'response' => ['options' => ['A']]]]);
            $this->fail('Expired answers must be locked.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $service->submit($attempt);
        $this->assertSame('completed', $attempt->fresh()->status);
        $this->assertEquals(1, DB::table('attempt_results')->value('raw_score'));
        $this->assertSame($attempt->expires_at->timestamp, $attempt->fresh()->submitted_at->timestamp);
    }

    public function test_duplicate_events_are_counted_once_and_threshold_submits_without_cheating_label(): void
    {
        $service = app(AttemptService::class);
        $attempt = $service->start($this->publishedExam([], ['auto_submit_threshold' => 2]), User::factory()->create());
        $event = ['type' => 'TAB_HIDDEN', 'event_key' => 'event-1'];
        $service->event($attempt, $event);
        $this->assertSame(1, $service->event($attempt, $event)['warnings']);
        $this->assertTrue($service->event($attempt, ['type' => 'WINDOW_BLUR', 'event_key' => 'event-2'])['submitted']);
        $this->assertSame('approved', $attempt->fresh()->leaderboard_status);
    }

    public function test_timed_group_does_not_restart_on_next_part_and_future_group_is_locked(): void
    {
        $service = app(AttemptService::class);
        $exam = $this->publishedExam();
        $revision = $exam->publishedRevision;
        $payload = $revision->payload;
        $payload['sections'][0]['duration_minutes'] = 10;
        $payload['sections'][0]['timing_group'] = 'reading';
        $payload['sections'][1] = array_replace($payload['sections'][0], ['index' => 1]);
        DB::table('exam_revisions')->where('id', $revision->id)->update(['payload' => json_encode($payload)]);
        $attempt = $service->start($exam, User::factory()->create());
        $deadline = $service->deadline($attempt)->timestamp;
        $this->travel(120)->seconds();
        $service->nextSection($attempt);
        $this->assertSame($deadline, $service->deadline($attempt->fresh())->timestamp);
    }

    public function test_learner_routes_coexist_in_the_unified_application(): void
    {
        $this->get('/exams')->assertRedirect(route('login'));
        $this->post('/exams/1/start')->assertRedirect(route('login'));
    }
}
