<?php

namespace Tests\Admin\Feature\ExamAttempts;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Models\AttemptAnswer;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Services\Assessment\Attempts\AttemptService;
use VistiqCore\Services\Assessment\Attempts\GradingService;
use VistiqCore\Services\Assessment\Attempts\ScoringService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;

class AttemptAdministrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    private function attempt(bool $manual = false): mixed
    {
        $item = $manual ? $this->item(['type' => 'essay', 'skill' => 'writing']) : $this->item();
        $attempt = app(AttemptService::class)->start($this->publishedExam([$item]), User::factory()->create());
        app(AttemptService::class)->submit($attempt);

        return $attempt->fresh();
    }

    private function grade(float $score = 6): array
    {
        return ['criteria' => array_fill_keys(QuestionRegistry::WRITING_CRITERIA, $score), 'feedback' => 'Reviewed against pinned rubric'];
    }

    public function test_filters_detail_and_permissions(): void
    {
        $first = $this->attempt();
        $second = $this->attempt(true);
        $this->actingAs($this->admin('reviewer'));
        $this->get('/admin/attempts?status=completed&user='.$first->user_id)->assertOk()->assertSee($first->snapshot['title'])->assertDontSee($second->snapshot['title']);
        $this->get('/admin/attempts/'.$first->id)->assertOk()->assertSee('Select the answer.')->assertSee('Dòng thời gian');
        $this->getJson('/admin/attempts?from=2026-10-14&to=2026-10-01')->assertUnprocessable();
        $this->get('/admin/attempts?max_score=100')->assertOk();
        $this->get('/admin/attempts?to=2099-01-01')->assertOk();
        $this->actingAs(User::factory()->create(), 'admin')->get('/admin/attempts')->assertForbidden();
    }

    public function test_grading_paginates_only_manual_questions(): void
    {
        $manual = $this->attempt(true);
        $objective = $this->attempt();
        for ($i = 0; $i < 25; $i++) {
            AttemptAnswer::create(['exam_attempt_id' => $objective->id, 'item_key' => 'objective-'.$i, 'response' => []]);
        }
        $this->actingAs($this->admin('reviewer'));
        $response = $this->get('/admin/grading')->assertOk()->assertSee($manual->user->name);
        $answers = $response->viewData('answers');
        $this->assertSame(1, $answers->total());
        $this->assertSame($manual->id, $answers->first()->exam_attempt_id);
    }

    public function test_grading_opens_selected_answer_and_navigates_one_at_a_time(): void
    {
        $first = $this->attempt(true);
        $second = $this->attempt(true);
        $this->actingAs($this->admin('reviewer'));

        $selected = $this->get(route('admin.grading.index', ['answer' => $first->answers()->first()->id]))
            ->assertOk()->assertSee($first->user->name)->assertDontSee($second->user->name);
        $answers = $selected->viewData('answers');
        $this->assertSame(2, $answers->total());
        $this->assertSame(1, $answers->count());
        $this->assertSame($first->id, $answers->first()->exam_attempt_id);
        $this->get(route('admin.grading.index', ['attempt' => $first->id, 'answer' => $second->answers()->first()->id]))->assertNotFound();
    }

    public function test_submitted_speaking_recording_can_be_played_and_graded_from_its_attempt(): void
    {
        Storage::fake('public');
        $exam = $this->publishedExam([$this->item(['type' => 'speaking', 'skill' => 'speaking'])]);
        $attempt = app(AttemptService::class)->start($exam, User::factory()->create());
        Storage::disk('public')->put('recordings/answer.webm', 'recorded audio');
        $asset = MediaAsset::create([
            'title' => 'answer.webm', 'disk' => 'public', 'path' => 'recordings/answer.webm',
            'mime_type' => 'audio/webm', 'size' => 14,
            'metadata' => ['recording_attempt_id' => $attempt->id, 'recording_item_key' => 'q1'],
        ]);
        $answer = AttemptAnswer::create(['exam_attempt_id' => $attempt->id, 'item_key' => 'q1', 'response' => ['asset_id' => $asset->id]]);
        app(AttemptService::class)->submit($attempt);

        $this->actingAs($this->admin('reviewer'));
        $this->get(route('admin.attempts.show', $attempt))->assertOk()
            ->assertSee('Bản ghi âm')->assertSee('Chấm 1 câu')
            ->assertSee(route('admin.attempts.recording', [$attempt, $asset]), false);
        $recording = $this->get(route('admin.attempts.recording', [$attempt, $asset]))->assertOk()->assertHeader('Content-Type', 'audio/webm');
        $this->assertSame('recorded audio', $recording->streamedContent());
        $this->get(route('admin.grading.index', ['attempt' => $attempt->id]))->assertOk()
            ->assertSee('Chấm điểm bài làm')->assertSee('grading-answer-'.$answer->id, false)
            ->assertSee(route('admin.attempts.recording', [$attempt, $asset]), false);

        $other = $this->attempt(true);
        $this->get(route('admin.attempts.recording', [$other, $asset]))->assertNotFound();
        $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.attempts.recording', [$attempt, $asset]))->assertForbidden();
    }

    public function test_grade_boundaries_regrade_reason_and_conflict(): void
    {
        $attempt = $this->attempt(true);
        $answer = $attempt->answers()->first();
        $this->actingAs($this->admin('reviewer'));
        $url = '/admin/grading/'.$answer->id;
        $this->postJson($url, $this->grade(9.5))->assertUnprocessable();
        $this->postJson($url, $this->grade(6.25))->assertUnprocessable();
        $gradingUrl = route('admin.grading.index', ['attempt' => $attempt->id, 'answer' => $answer->id]);
        $this->post($url, $this->grade(0) + [
            'criterion_feedback' => ['task_response' => 'Clear position'],
            'improvements' => 'Develop the second argument.',
        ])->assertRedirect($gradingUrl);
        $this->assertSame('Clear position', $answer->fresh()->grade['criterion_feedback']['task_response']);
        $this->assertSame('Develop the second argument.', $answer->fresh()->grade['improvements']);
        $this->get(route('admin.attempts.show', $attempt))->assertOk()
            ->assertSee('Clear position')->assertSee('Develop the second argument.');
        $oldToken = hash('sha256', json_encode([], JSON_THROW_ON_ERROR));
        $this->postJson($url, $this->grade(9))->assertUnprocessable();
        $this->postJson($url, $this->grade(9) + ['reason' => 'Correct score', 'grade_token' => $oldToken])->assertConflict();
        $this->post($url, $this->grade(9) + ['reason' => 'Correct score', 'grade_token' => hash('sha256', json_encode($answer->fresh()->grade, JSON_THROW_ON_ERROR))])->assertRedirect($gradingUrl);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'answer.grade')->count());
        $this->assertSame('completed', $attempt->fresh()->status);
    }

    public function test_mini_ielts_writing_and_speaking_displays_overall_band_after_both_are_graded(): void
    {
        $exam = $this->publishedExam([
            $this->item(['key' => 'writing-1', 'id' => 1, 'type' => 'essay', 'skill' => 'writing']),
            $this->item(['key' => 'speaking-1', 'id' => 2, 'type' => 'speaking', 'skill' => 'speaking']),
        ], [], ['exam_type_id' => DB::table('exam_types')->where('code', 'ielts')->value('id')]);
        $attempt = app(AttemptService::class)->start($exam, User::factory()->create());
        app(AttemptService::class)->submit($attempt);
        $answers = $attempt->answers()->get()->keyBy('item_key');
        $this->actingAs($this->admin('reviewer'));

        $this->post(route('admin.grading.store', $answers['writing-1']), $this->grade(7))->assertRedirect();
        $this->assertNull(DB::table('exam_attempts')->where('id', $attempt->id)->value('scaled_score'));
        $this->post(route('admin.grading.store', $answers['speaking-1']), [
            'criteria' => array_fill_keys(QuestionRegistry::SPEAKING_CRITERIA, 8),
            'feedback' => 'Speaking rubric reviewed.',
        ])->assertRedirect();

        $this->assertSame('completed', $attempt->fresh()->status);
        $this->assertEquals(7.5, DB::table('exam_attempts')->where('id', $attempt->id)->value('scaled_score'));
        $this->get(route('admin.attempts.show', $attempt))->assertOk()->assertSee('7.50');
    }

    public function test_moderation_retries_do_not_duplicate_audit_and_snapshot_stays_fixed(): void
    {
        $attempt = $this->attempt();
        $snapshot = $attempt->snapshot;
        $this->actingAs($this->admin());
        $url = '/admin/attempts/'.$attempt->id.'/moderate';
        $this->postJson($url, ['leaderboard_status' => 'private', 'note' => '   '])->assertUnprocessable();
        $this->postJson($url, ['leaderboard_status' => 'active', 'note' => 'Invalid'])->assertUnprocessable();
        $data = ['leaderboard_status' => 'private', 'note' => '  Privacy request  '];
        $this->post($url, $data)->assertRedirect();
        $this->post($url, $data)->assertRedirect();
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'attempt.moderate')->count());
        $this->assertSame($snapshot, $attempt->fresh()->snapshot);
        $this->assertSame('completed', $attempt->fresh()->status);
    }

    public function test_scoring_failure_rolls_back_grade_and_audit(): void
    {
        $attempt = $this->attempt(true);
        $answer = $attempt->answers()->first();
        $this->actingAs($this->admin('reviewer'));
        $this->mock(ScoringService::class)->shouldReceive('calculate')->andThrow(new \RuntimeException('scoring unavailable'));
        try {
            app(GradingService::class)->grade($answer, $this->grade());
            $this->fail('Must roll back.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('scoring unavailable', $exception->getMessage());
        }
        $this->assertNull($answer->fresh()->grade);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'answer.grade')->count());
        $this->assertSame('pending_grading', $attempt->fresh()->status);
    }
}
