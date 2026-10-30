<?php

namespace Tests\Learner\Feature\ExamTakingAndSubmission;

use Database\Admin\Factories\ExamTemplateFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Tests\Learner\TestCase;
use VistiqCore\Models\ExamRevision;
use VistiqCore\Services\Assessment\Attempts\AttemptService;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;
use VistiqLearner\Models\User;

class NavigationAndLeaderboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    private function exam(array $policies = []): mixed
    {
        $exam = ExamTemplateFactory::new()->create(['status' => 'published']);
        $items = [];
        foreach (range(1, 3) as $number) {
            $items[] = ['key' => 'q'.$number, 'type' => 'multiple_choice', 'section' => 0, 'skill' => 'reading', 'part' => 'part-5', 'prompt' => 'Question '.$number, 'options' => ['A' => 'One', 'B' => 'Two', 'C' => 'Three', 'D' => 'Four'], 'correct_options' => ['B'], 'accepted_answers' => [], 'metadata' => [], 'stimuli' => [], 'explanation' => 'Two'];
        }
        $payload = ['schema_version' => 1, 'exam_id' => $exam->id, 'title' => $exam->title, 'exam_type' => 'toeic', 'mode' => 'mini', 'opens_at' => null, 'closes_at' => null, 'items' => $items, 'sections' => [['index' => 0, 'title' => 'Reading', 'item_keys' => ['q1', 'q2', 'q3'], 'duration_minutes' => 0, 'timing_group' => 'reading']], 'policies' => array_replace(ExamPublishingService::POLICIES, $policies), 'scoring' => null];
        $revision = ExamRevision::create(['exam_template_id' => $exam->id, 'number' => 1, 'payload' => $payload]);
        $exam->update(['published_revision_id' => $revision->id]);

        return $exam;
    }

    public function test_navigation_is_owned_sequential_persistent_and_locks_previous_answers(): void
    {
        $this->actingAs($user = User::factory()->create());
        $attempt = app(AttemptService::class)->start($this->exam(['lock_previous' => true, 'navigation' => 'linear']), $user);
        $base = '/attempts/'.$attempt->id;
        $data = ['answers' => [['item_key' => 'q1', 'version' => 0, 'response' => ['options' => ['B']]]]];
        $this->postJson($base.'/answers', $data)->assertOk();
        $this->postJson($base.'/navigate', ['item_key' => 'q3'])->assertConflict();
        $this->postJson($base.'/navigate', ['item_key' => 'q2'])->assertOk()->assertJsonPath('current_item_index', 1);
        $this->postJson($base.'/navigate', ['item_key' => 'q2'])->assertOk();
        $this->getJson($base.'/state')->assertJsonPath('current_item_index', 1);
        $this->postJson($base.'/answers', $data)->assertConflict();
        $this->postJson($base.'/navigate', ['item_key' => 'q1'])->assertConflict();
        $this->actingAs(User::factory()->create())->postJson($base.'/navigate', ['item_key' => 'q3'])->assertNotFound();
        $this->actingAs($user)->postJson($base.'/submit')->assertOk();
        $this->postJson($base.'/navigate', ['item_key' => 'q3'])->assertConflict();
    }

    public function test_randomized_option_order_is_an_array_pinned_to_the_attempt(): void
    {
        $this->actingAs($user = User::factory()->create());
        $exam = $this->exam(['randomize_options' => true]);
        $attempt = app(AttemptService::class)->start($exam, $user);
        $order = $attempt->snapshot['items'][0]['option_order'];
        $this->assertIsArray($order);
        $this->assertEqualsCanonicalizing(['A', 'B', 'C', 'D'], $order);
        $this->assertSame($order, $attempt->fresh()->snapshot['items'][0]['option_order']);
        $this->getJson('/attempts/'.$attempt->id.'/state')->assertJsonPath('snapshot.items.0.option_order', $order)->assertDontSee('correct_options');
    }

    public function test_linear_navigation_cannot_skip_to_the_next_section_and_cursor_advances_with_section(): void
    {
        $exam = $this->exam(['navigation' => 'linear', 'lock_previous' => true]);
        $payload = $exam->publishedRevision->payload;
        $payload['items'][2]['section'] = 1;
        $payload['sections'][0]['item_keys'] = ['q1', 'q2'];
        $payload['sections'][] = ['index' => 1, 'title' => 'Next', 'item_keys' => ['q3'], 'duration_minutes' => 0, 'timing_group' => 'reading'];
        $revision = ExamRevision::create(['exam_template_id' => $exam->id, 'number' => 2, 'payload' => $payload]);
        $exam->update(['published_revision_id' => $revision->id]);
        $this->actingAs($user = User::factory()->create());
        $attempt = app(AttemptService::class)->start($exam, $user);
        $url = '/attempts/'.$attempt->id;
        $this->postJson($url.'/section')->assertConflict();
        $this->postJson($url.'/navigate', ['item_key' => 'q2'])->assertOk();
        $this->postJson($url.'/section')->assertOk()->assertJsonPath('current_item_index', 2)->assertJsonPath('active_section', 1);
        $this->postJson($url.'/navigate', ['item_key' => 'q1'])->assertConflict();
    }

    private function completed(mixed $exam, mixed $user, float $score, int $seconds, array $policies = []): mixed
    {
        $service = app(AttemptService::class);
        $attempt = $service->start($exam, $user);
        if ($policies) {
            $snapshot = $attempt->snapshot;
            $snapshot['policies'] = array_replace($snapshot['policies'], $policies);
            $attempt->update(['snapshot' => $snapshot]);
        }
        $service->submit($attempt);
        DB::table('attempt_results')->where('exam_attempt_id', $attempt->id)->update(['scaled_score' => $score, 'details' => json_encode(['duration_seconds' => $seconds])]);

        return $attempt;
    }

    public function test_leaderboard_selects_best_per_user_ties_and_excludes_private_pending_and_disabled_results(): void
    {
        $exam = $this->exam(['anonymous' => false]);
        $this->actingAs($user = User::factory()->create(['name' => 'Visible learner']));
        $this->completed($exam, $user, 80, 30);
        $this->completed($exam, $user, 90, 70);
        $this->completed($exam, $user, 90, 60);
        $other = User::factory()->create(['name' => 'Hidden name']);
        $this->completed($exam, $other, 90, 60, ['anonymous' => true]);
        $third = User::factory()->create();
        $this->completed($exam, $third, 80, 10);
        $private = $this->completed($exam, User::factory()->create(), 100, 1);
        $private->update(['leaderboard_status' => 'private']);
        $this->completed($exam, User::factory()->create(), 100, 1, ['leaderboard' => false]);
        $pending = $this->completed($exam, User::factory()->create(), 100, 1);
        DB::table('attempt_results')->where('exam_attempt_id', $pending->id)->update(['grading_status' => 'pending']);
        $response = $this->getJson('/exams/'.$exam->id.'/leaderboard')->assertOk()->assertJsonPath('total', 3);
        $this->assertSame([1, 1, 3], array_column($response->json('data'), 'rank'));
        $this->assertSame(1, count(array_filter($response->json('data'), fn ($row) => $row['is_current_user'])));
        $response->assertDontSee($user->email)->assertDontSee('Hidden name')->assertDontSee('snapshot')->assertDontSee('user_id');
        $this->get('/exams/'.$exam->id.'/leaderboard')->assertOk()->assertSee('Visible learner')->assertSee('· Bạn');
        $this->get('/exams/'.$this->exam(['leaderboard' => false])->id.'/leaderboard')->assertNotFound();
        $exam->update(['leaderboard_reset_at' => now()->addMinute()]);
        $this->getJson('/exams/'.$exam->id.'/leaderboard')->assertJsonPath('total', 0);
    }

    public function test_leaderboard_pagination_preserves_ties_across_pages(): void
    {
        $exam = $this->exam();
        $this->actingAs($viewer = User::factory()->create());
        foreach (range(1, 31) as $index) {
            $this->completed($exam, $index === 1 ? $viewer : User::factory()->create(), 90, 60);
        }
        $this->getJson('/exams/'.$exam->id.'/leaderboard?page=2')->assertOk()->assertJsonPath('total', 31)->assertJsonCount(1, 'data')->assertJsonPath('data.0.rank', 1);
    }
}
