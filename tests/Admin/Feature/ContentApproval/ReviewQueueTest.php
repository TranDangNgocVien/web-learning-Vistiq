<?php

namespace Tests\Admin\Feature\ContentApproval;

use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Admin\TestCase;
use VistiqCore\Services\Assessment\ContentApproval\ContentReviewService;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;
use VistiqCore\Services\System\AuditService;
use VistiqCore\Models\Role;
use VistiqCore\Models\LearningStimulus;

class ReviewQueueTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    private function question(): mixed
    {
        $this->actingAs($this->admin('content-admin'));
        $question = QuestionBankItemFactory::new()->create();
        $revision = app(ContentRevisionService::class)->save('question', $question, $this->item(['exam_part_id' => $question->exam_part_id]), 0);
        $revision->update(['status' => 'review']);

        return $question->fresh();
    }

    private function decision(mixed $question): array
    {
        $review = app(ContentReviewService::class)->preview('question', $question->id);

        return ['revision_counter' => $review['number'], 'review_token' => $review['token']];
    }

    public function test_queue_combines_types_filters_and_preview_is_read_only(): void
    {
        $question = $this->question();
        $exam = $this->publishedExam();
        $exam->update(['draft_status' => 'review', 'revision_counter' => 2]);
        $this->actingAs($this->admin('reviewer'));
        $this->get('/admin/content-approval')->assertOk()->assertSee($exam->title)->assertSee('Select the answer.');
        $this->get('/admin/content-approval?kind=question&revision=1')->assertOk()->assertDontSee($exam->title);
        $this->get('/admin/content-approval?to=2099-01-01')->assertOk();
        $count = DB::table('audit_logs')->count();
        $this->get('/admin/content-approval/question/'.$question->id)->assertOk()->assertSee('Revision 1')->assertSee('Secret explanation.');
        $this->assertSame($count, DB::table('audit_logs')->count());
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_submitted_reading_appears_in_queue_and_can_be_approved_without_publishing(): void
    {
        $author = $this->admin('content-admin');
        $this->actingAs($author, 'admin');
        $part = DB::table('exam_parts')->where('code', 'part-7')->first();
        $reading = LearningStimulus::create([
            'created_by' => $author->id,
            'title' => 'Reading awaiting review',
            'stimulus_type' => 'reading',
            'body' => '<p>The library opens at nine.</p>',
            'status' => 'draft',
        ]);
        $revision = app(ContentRevisionService::class)->save('stimulus', $reading, [
            'title' => 'Reading awaiting review',
            'stimulus_type' => 'reading',
            'body' => '<p>The library opens at nine.</p>',
            'metadata' => ['exam_part_id' => $part->id, 'content_kind' => 'passage'],
        ], 0);
        app(ContentRevisionService::class)->transition('stimulus', $reading, 'review');

        $this->actingAs($this->admin('reviewer'), 'admin');
        $this->get('/admin/content-approval?kind=stimulus&exam_type='.$part->exam_type_id.'&part='.$part->id)
            ->assertOk()
            ->assertSee('Reading awaiting review')
            ->assertSee('Bài Reading');
        $this->get('/admin/content-approval/stimulus/'.$reading->id.'?modal=1')
            ->assertOk()
            ->assertSee('The library opens at nine.')
            ->assertSee('Xác nhận duyệt');

        $review = app(ContentReviewService::class)->preview('stimulus', $reading->id);
        $this->postJson('/admin/content-approval/stimulus/'.$reading->id.'/approve', [
            'revision_counter' => $review['number'],
            'review_token' => $review['token'],
        ])->assertOk()->assertJsonPath('status', 'approved');

        $this->assertDatabaseHas('content_revisions', ['id' => $revision->id, 'status' => 'approved']);
        $this->assertSame('approved', $reading->fresh()->status);
        $this->assertNull($reading->fresh()->published_revision_id);
        $this->get('/admin/content-approval?kind=stimulus')->assertOk()->assertDontSee('Reading awaiting review');
    }

    public function test_selected_readings_can_be_bulk_approved(): void
    {
        $author = $this->admin('content-admin');
        $this->actingAs($author, 'admin');
        $part = DB::table('exam_parts')->where('code', 'part-7')->first();
        $readings = collect(range(1, 2))->map(function ($number) use ($author, $part) {
            $reading = LearningStimulus::create([
                'created_by' => $author->id,
                'title' => 'Bulk Reading '.$number,
                'stimulus_type' => 'reading',
                'body' => '<p>Reading content '.$number.'.</p>',
                'status' => 'draft',
            ]);
            app(ContentRevisionService::class)->save('stimulus', $reading, [
                'title' => $reading->title,
                'stimulus_type' => 'reading',
                'body' => $reading->body,
                'metadata' => ['exam_part_id' => $part->id, 'content_kind' => 'passage'],
            ], 0);
            app(ContentRevisionService::class)->transition('stimulus', $reading, 'review');

            return $reading;
        });

        $this->actingAs($this->admin('reviewer'), 'admin');
        $queue = $this->get('/admin/content-approval?kind=stimulus')->assertOk()->getContent();
        preg_match('/name="selection_snapshot" value="([a-f0-9]{64})"/', $queue, $snapshot);
        $this->assertNotEmpty($snapshot[1] ?? null);
        $start = $this->postJson('/admin/content-approval/approve-all', [
            'selection_mode' => 'selected',
            'expected_count' => 2,
            'selection_snapshot' => $snapshot[1],
            'kind' => 'stimulus',
            'selected' => $readings->map(fn ($reading) => 'stimulus:'.$reading->id)->all(),
        ])->assertOk()->assertJsonPath('total', 2);

        $this->postJson($start->json('next_url'))->assertOk()
            ->assertJsonPath('approved', 2)
            ->assertJsonPath('done', true);
        foreach ($readings as $reading) {
            $this->assertSame('approved', $reading->fresh()->status);
            $this->assertNull($reading->fresh()->published_revision_id);
        }
    }

    public function test_queue_opens_review_details_in_modal_with_approve_and_reject_actions(): void
    {
        $question = $this->question();
        $this->actingAs($this->admin('reviewer'));

        $this->get('/admin/content-approval')
            ->assertOk()
            ->assertSee('data-review-open', false)
            ->assertSee('data-review-select-all', false)
            ->assertSee('data-review-select-row', false)
            ->assertSee('data-review-bulk-approve', false)
            ->assertDontSee('Duyệt nhanh')
            ->assertSee('data-content-review-dialog', false)
            ->assertSee('Duyệt');

        $this->get('/admin/content-approval/question/'.$question->id.'?modal=1')
            ->assertOk()
            ->assertSee('data-review-item', false)
            ->assertSee('Select the answer.')
            ->assertSee('Xác nhận duyệt')
            ->assertSee('data-review-confirm-message', false)
            ->assertSee('Xác nhận từ chối và trả chỉnh sửa')
            ->assertSee('name="revision_counter"', false)
            ->assertSee('name="review_token"', false)
            ->assertSee('name="note"', false);
    }

    public function test_approve_requires_token_approves_once_and_does_not_publish(): void
    {
        $question = $this->question();
        $this->actingAs($this->admin('reviewer'));
        $url = '/admin/content-approval/question/'.$question->id.'/approve';
        $this->postJson($url)->assertUnprocessable();
        $data = $this->decision($question);
        $this->postJson($url, $data)->assertOk()->assertJsonPath('status', 'approved');
        $this->postJson($url, $data)->assertConflict();
        $this->assertNull($question->fresh()->published_revision_id);
        $audit = DB::table('audit_logs')->where('action', 'content.approve')->first();
        $this->assertSame(1, json_decode($audit->after, true)['revision']);
        $this->assertDatabaseCount('exam_attempts', 0);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'content.approve')->count());
    }

    public function test_super_admin_can_self_approve_from_review_and_legacy_routes_and_is_audited(): void
    {
        $this->actingAs($author = $this->admin());
        $question = QuestionBankItemFactory::new()->create();
        $revision = app(ContentRevisionService::class)->save('question', $question, $this->item(['exam_part_id' => $question->exam_part_id]), 0);
        $revision->update(['status' => 'review']);
        $this->postJson('/admin/content-approval/question/'.$question->id.'/approve', $this->decision($question))
            ->assertOk()
            ->assertJsonPath('status', 'approved');
        $audit = DB::table('audit_logs')->where('action', 'content.approve')->where('entity_id', $question->id)->first();
        $this->assertTrue((bool) json_decode($audit->after, true)['self_review']);

        $legacyQuestion = QuestionBankItemFactory::new()->create();
        $legacyRevision = app(ContentRevisionService::class)->save('question', $legacyQuestion, $this->item(['exam_part_id' => $legacyQuestion->exam_part_id]), 0);
        $legacyRevision->update(['status' => 'review']);
        $this->postJson('/admin/content/question/'.$legacyQuestion->id.'/approve', $this->decision($legacyQuestion))
            ->assertOk()
            ->assertJsonPath('status', 'approved');
    }

    public function test_non_super_admin_still_cannot_self_approve(): void
    {
        $author = $this->admin('content-admin');
        $author->roles()->attach(Role::where('code', 'reviewer')->value('id'));
        $this->actingAs($author);
        $question = QuestionBankItemFactory::new()->create();
        $revision = app(ContentRevisionService::class)->save('question', $question, $this->item(['exam_part_id' => $question->exam_part_id]), 0);
        $revision->update(['status' => 'review']);

        $this->postJson('/admin/content-approval/question/'.$question->id.'/approve', $this->decision($question))->assertForbidden();
    }

    public function test_approve_all_processes_matching_revisions_across_pages_and_reports_blockers(): void
    {
        $validQuestions = [];
        for ($index = 0; $index < 21; $index++) {
            $validQuestions[] = $this->question();
        }
        $blockedQuestion = $this->question();
        $blockedQuestion->draftRevision->update(['payload' => array_replace($blockedQuestion->draftRevision->payload, ['correct_options' => []])]);

        $this->actingAs($this->admin('reviewer'));
        $queue = $this->get('/admin/content-approval?kind=question')->assertOk()->getContent();
        preg_match('/name="selection_snapshot" value="([a-f0-9]{64})"/', $queue, $snapshotMatch);
        $this->assertNotEmpty($snapshotMatch[1] ?? null);
        $this->post('/admin/content-approval/approve-all', [
            'selection_mode' => 'all_filtered',
            'expected_count' => 22,
            'selection_snapshot' => $snapshotMatch[1],
            'kind' => 'question',
        ])->assertRedirect(route('admin.content-approval.index', ['kind' => 'question']))
            ->assertSessionHas('review_bulk_batch');
        $nextUrl = route('admin.content-approval.continue', session('review_bulk_batch'));
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'content.approve')->count());
        $processed = 0;
        do {
            $response = $this->postJson($nextUrl)->assertOk();
            $this->assertGreaterThan($processed, $response->json('processed'));
            $this->assertLessThanOrEqual(5, $response->json('processed') - $processed);
            $processed = $response->json('processed');
        } while (! $response->json('done'));
        $response->assertJsonPath('approved', 21)->assertJsonPath('skipped', 1)
            ->assertSessionHas('status', 'Đã duyệt 21/22 revision đã chọn. Bỏ qua 1 mục; xem lý do bên dưới.')
            ->assertSessionHas('review_bulk_skipped', fn ($skipped) => count($skipped) === 1 && str_contains($skipped[0]['label'], '#'.$blockedQuestion->id));
        $this->postJson($nextUrl)->assertOk()->assertJsonPath('approved', 21)->assertJsonPath('done', true);
        $this->assertSame(21, DB::table('audit_logs')->where('action', 'content.approve')->count());

        foreach ($validQuestions as $question) {
            $this->assertSame('approved', $question->draftRevision()->first()->status);
        }
        $this->assertSame('review', $blockedQuestion->draftRevision()->first()->status);
    }

    public function test_approve_all_rejects_a_stale_filtered_queue_before_processing(): void
    {
        $this->question();
        $this->actingAs($this->admin('reviewer'));

        $this->postJson('/admin/content-approval/approve-all', [
            'selection_mode' => 'all_filtered',
            'expected_count' => 1,
            'selection_snapshot' => str_repeat('a', 64),
            'kind' => 'question',
        ])->assertUnprocessable()->assertJsonValidationErrors('selection');

        $this->postJson('/admin/content-approval/approve-all', [
            'selection_mode' => 'all_filtered',
            'expected_count' => 2,
            'selection_snapshot' => str_repeat('a', 64),
            'kind' => 'question',
        ])->assertUnprocessable()->assertJsonValidationErrors('selection');
    }

    public function test_bulk_approval_keeps_the_original_selection_and_detects_revision_changes_between_batches(): void
    {
        $questions = collect(range(1, 7))->map(fn () => $this->question());
        $this->actingAs($this->admin('reviewer'));
        $queue = $this->get('/admin/content-approval?kind=question')->assertOk()->getContent();
        preg_match('/name="selection_snapshot" value="([a-f0-9]{64})"/', $queue, $snapshot);
        $start = $this->postJson('/admin/content-approval/approve-all', [
            'selection_mode' => 'all_filtered', 'expected_count' => 7,
            'selection_snapshot' => $snapshot[1], 'kind' => 'question',
            'excluded' => ['question:'.$questions->last()->id],
        ])->assertOk()->assertJsonPath('processed', 0)->assertJsonPath('total', 6);
        $nextUrl = $start->json('next_url');
        $this->postJson($nextUrl)->assertOk()->assertJsonPath('processed', 5)->assertJsonPath('done', false);
        $changed = $questions[5]->draftRevision;
        $changed->forceFill(['updated_at' => now()->addMinute()])->save();
        $this->postJson($nextUrl)->assertOk()->assertJsonPath('done', true)
            ->assertJsonPath('approved', 5)->assertJsonPath('skipped', 1);
        $this->assertSame('review', $changed->fresh()->status);
        $this->assertSame('review', $questions->last()->draftRevision->fresh()->status);
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_only_the_authorized_batch_owner_can_continue_and_expired_batches_are_reported(): void
    {
        $question = $this->question();
        $this->actingAs($this->admin('reviewer'));
        $queue = $this->get('/admin/content-approval?kind=question')->assertOk()->getContent();
        preg_match('/name="selection_snapshot" value="([a-f0-9]{64})"/', $queue, $snapshot);
        $start = $this->postJson('/admin/content-approval/approve-all', [
            'selection_mode' => 'selected', 'expected_count' => 1,
            'selection_snapshot' => $snapshot[1], 'kind' => 'question', 'selected' => ['question:'.$question->id],
        ])->assertOk();
        $this->get('/admin/content-approval')->assertOk()->assertSee('data-review-resume-url', false);
        $this->actingAs($this->admin('reviewer'));
        $this->postJson($start->json('next_url'))->assertForbidden();
        $this->postJson(route('admin.content-approval.continue', (string) \Illuminate\Support\Str::uuid()))->assertStatus(410);
        $this->actingAs($this->admin('content-admin'));
        $this->postJson($start->json('next_url'))->assertForbidden();
        $this->assertSame('review', $question->draftRevision->fresh()->status);
    }

    public function test_reject_trims_reason_and_revision_or_payload_change_conflicts(): void
    {
        $question = $this->question();
        $this->actingAs($this->admin('reviewer'));
        $url = '/admin/content-approval/question/'.$question->id.'/reject';
        $data = $this->decision($question);
        $this->postJson($url, $data + ['note' => '   '])->assertUnprocessable();
        $this->postJson($url, $data + ['note' => str_repeat('a', 2001)])->assertUnprocessable();
        $revision = $question->draftRevision;
        $revision->update(['payload' => array_replace($revision->payload, ['prompt' => 'Changed after preview'])]);
        $this->postJson($url, $data + ['note' => 'Fix wording'])->assertConflict();
        $this->post($url, $data + ['note' => 'Fix wording'])->assertConflict()->assertSee('Changed after preview')->assertSee('Quyết định vừa gửi chưa được áp dụng.');
        $this->postJson($url, $this->decision($question) + ['note' => '  Fix wording  '])->assertOk()->assertJsonPath('status', 'draft');
        $this->assertSame('Fix wording', $revision->fresh()->review_note);
    }

    public function test_invalid_content_and_missing_permission_are_rejected(): void
    {
        $question = $this->question();
        $revision = $question->draftRevision;
        $revision->update(['payload' => array_replace($revision->payload, ['correct_options' => []])]);
        $this->actingAs($this->admin('reviewer'));
        $this->postJson('/admin/content-approval/question/'.$question->id.'/approve', $this->decision($question))->assertUnprocessable();
        $this->actingAs($this->admin('content-admin'))->get('/admin/content-approval')->assertForbidden();
        $this->postJson('/admin/content-approval/question/'.$question->id.'/approve', $this->decision($question))->assertForbidden();
    }

    public function test_audit_failure_rolls_back_decision(): void
    {
        $question = $this->question();
        $this->actingAs($this->admin('reviewer'));
        $data = $this->decision($question);
        $this->mock(AuditService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        try {
            app(ContentReviewService::class)->decide('question', $question->id, 'approve', $data);
            $this->fail('Audit failure must abort the decision.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }
        $this->assertSame('review', $question->draftRevision->fresh()->status);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'content.approve')->count());
    }

    public function test_super_admin_can_approve_own_exam_without_preview_pinning_content(): void
    {
        $question = $this->question();
        $this->actingAs($author = $this->admin());
        $revisions = app(ContentRevisionService::class);
        $revisions->transition('question', $question, 'approve');
        $revisions->transition('question', $question, 'publish');
        $exam = $this->publishedExam();
        $exam->update(['created_by' => $author->id, 'draft_status' => 'review', 'revision_counter' => 3, 'draft_payload' => ['sections' => [['exam_part_id' => $question->exam_part_id, 'question_ids' => [$question->id], 'duration_minutes' => 0]], 'policies' => ExamPublishingService::POLICIES]]);
        $review = app(ContentReviewService::class)->preview('exam', $exam->id);
        $this->assertSame([], $review['blockers']);
        $revisionCount = DB::table('exam_revisions')->count();
        $this->postJson('/admin/exams/'.$exam->id.'/approve', ['revision_counter' => 3, 'review_token' => $review['token']])
            ->assertOk()
            ->assertJsonPath('status', 'approved');
        $this->assertSame($revisionCount + 1, DB::table('exam_revisions')->count());
        $this->assertSame('approved', $exam->fresh()->draft_status);
        $this->assertSame('approved', $exam->fresh()->status);
        $this->assertNotNull($exam->fresh()->published_revision_id);
        $audit = DB::table('audit_logs')->where('entity_id', $exam->id)->where('action', 'exam.approve')->first();
        $this->assertTrue((bool) json_decode($audit->after, true)['self_review']);
    }

    public function test_valid_exam_uses_published_questions_and_stale_configuration_conflicts(): void
    {
        $question = $this->question();
        $this->actingAs($this->admin());
        $revisions = app(ContentRevisionService::class);
        $revisions->transition('question', $question, 'approve');
        $revisions->transition('question', $question, 'publish');
        $exam = $this->publishedExam();
        $exam->update(['draft_status' => 'review', 'revision_counter' => 3, 'draft_payload' => ['sections' => [['exam_part_id' => $question->exam_part_id, 'question_ids' => [$question->id], 'duration_minutes' => 0]], 'policies' => ExamPublishingService::POLICIES]]);
        $this->actingAs($this->admin('reviewer'));
        $service = app(ContentReviewService::class);
        $review = $service->preview('exam', $exam->id);
        $this->assertSame([], $review['blockers']);
        $data = ['revision_counter' => 3, 'review_token' => $review['token']];
        $draft = $exam->draft_payload;
        $draft['policies']['duration_minutes'] = 25;
        $exam->update(['draft_payload' => $draft]);
        $url = '/admin/content-approval/exam/'.$exam->id.'/approve';
        $this->postJson($url, $data)->assertConflict();
        $review = $service->preview('exam', $exam->id);
        $oldApprovedRevision = $exam->published_revision_id;
        $revisionCount = DB::table('exam_revisions')->count();
        $this->postJson($url, ['revision_counter' => 3, 'review_token' => $review['token']])->assertOk()->assertJsonPath('status', 'approved');
        $this->assertNotSame($oldApprovedRevision, $exam->fresh()->published_revision_id);
        $this->assertSame($revisionCount + 1, DB::table('exam_revisions')->count());
        $this->assertSame('approved', $exam->fresh()->status);
        $this->assertSame(1, DB::table('audit_logs')->where('entity_id', $exam->id)->where('entity_type', 'exam')->where('action', 'exam.approve')->count());
    }

    public function test_non_super_admin_revision_writer_cannot_self_approve_exam(): void
    {
        $exam = $this->publishedExam();
        $exam->update(['draft_status' => 'review', 'revision_counter' => 4]);
        $writer = $this->admin('content-admin');
        $writer->roles()->attach(Role::where('code', 'reviewer')->value('id'));
        $this->actingAs($writer);
        app(AuditService::class)->record('exam.save', 'exam', $exam->id, [], ['revision' => 4]);
        $this->assertSame($writer->id, app(ContentReviewService::class)->preview('exam', $exam->id)['author']);
        $review = app(ContentReviewService::class)->preview('exam', $exam->id);
        $this->postJson('/admin/exams/'.$exam->id.'/approve', ['revision_counter' => 4, 'review_token' => $review['token']])->assertForbidden();
    }

    public function test_exam_queue_author_filter_matches_the_current_revision_writer(): void
    {
        $creator = $this->admin('content-admin');
        $writer = $this->admin('content-admin');
        $exam = $this->publishedExam();
        $exam->update(['created_by' => $creator->id, 'draft_status' => 'review', 'revision_counter' => 4]);
        $this->actingAs($writer);
        app(AuditService::class)->record('exam.save', 'exam', $exam->id, [], ['revision' => 4]);
        $this->actingAs($creator);
        app(AuditService::class)->record('exam.save', 'exam', $exam->id, [], ['revision' => 3]);
        $this->actingAs($this->admin('reviewer'));
        $this->get('/admin/content-approval?kind=exam&author='.$writer->id)->assertOk()->assertSee($exam->title)->assertSee($writer->name);
        $this->get('/admin/content-approval?kind=exam&author='.$creator->id)->assertOk()->assertDontSee($exam->title);
    }

    public function test_archived_questions_are_excluded_and_direct_decision_is_blocked(): void
    {
        $question = $this->question();
        $this->actingAs($this->admin('reviewer'));
        $data = $this->decision($question);
        $question->learningItem->update(['status' => 'archived']);
        $this->get('/admin/content-approval')->assertOk()->assertDontSee('Select the answer.');
        $this->postJson('/admin/content-approval/question/'.$question->id.'/approve', $data)->assertConflict();
        $this->postJson('/admin/content/question/'.$question->id.'/approve')->assertConflict();
    }
}
