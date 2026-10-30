<?php

namespace Tests\Admin\Feature\QuestionBank;

use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Admin\TestCase;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;

class DraftReviewHandoffTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_legacy_draft_and_current_revision_can_be_sent_to_review_without_publication(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin('content-admin'));
        $legacy = QuestionBankItemFactory::new()->create();
        $legacy->learningItem->update(['status' => 'draft']);
        foreach (['A' => 'One', 'B' => 'Two', 'C' => 'Three', 'D' => 'Four'] as $key => $content) {
            $legacy->options()->create(['option_key' => $key, 'content' => $content, 'is_correct' => $key === 'B', 'sort_order' => ord($key) - 65]);
        }
        $current = QuestionBankItemFactory::new()->create();
        app(ContentRevisionService::class)->save('question', $current, $this->item(['exam_part_id' => $current->exam_part_id]), 0);
        $this->post('/admin/questions/bulk', ['ids' => [$legacy->id, $current->id], 'action' => 'review'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('review', $legacy->fresh()->draftRevision->status);
        $this->assertSame('review', $current->fresh()->draftRevision->status);
        $this->assertNull($legacy->fresh()->published_revision_id);
        $this->get('/admin/readings?status=review')->assertOk()->assertSee('Choose the correct answer.');
        $this->get('/admin/questions')->assertRedirect('/admin/questions/listening');
        $this->actingAs($this->admin('reviewer'))->get('/admin/content-approval')->assertOk()->assertSee('Select the answer.');
        $this->post('/admin/questions/bulk', ['ids' => [$legacy->id], 'action' => 'review'])->assertForbidden();
    }

    public function test_reviewer_can_approve_single_or_multiple_questions_with_revision_guards(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin('content-admin'));
        $revisions = app(ContentRevisionService::class);
        $questions = collect([1, 2, 3])->map(function () use ($revisions): QuestionBankItem {
            $question = QuestionBankItemFactory::new()->create();
            $revision = $revisions->save('question', $question, $this->item(['exam_part_id' => $question->exam_part_id]), 0);
            $revision->update(['status' => 'review']);

            return $question->fresh();
        });

        $this->actingAs($this->admin('reviewer'));
        $first = $questions->first();
        $this->post('/admin/questions/approve', [
            'ids' => [$first->id],
            'revision_ids' => [$first->id => $first->draft_revision_id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $first->fresh()->draftRevision->status);

        $batch = $questions->slice(1)->values();
        $this->post('/admin/questions/approve', [
            'ids' => $batch->pluck('id')->all(),
            'revision_ids' => $batch->mapWithKeys(fn (QuestionBankItem $question): array => [$question->id => $question->draft_revision_id])->all(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $batch->each(fn (QuestionBankItem $question) => $this->assertSame('approved', $question->fresh()->draftRevision->status));

        $this->actingAs($this->admin('content-admin'));
        $staleQuestion = QuestionBankItemFactory::new()->create();
        $staleRevision = $revisions->save('question', $staleQuestion, $this->item(['exam_part_id' => $staleQuestion->exam_part_id]), 0);
        $staleRevision->update(['status' => 'review']);
        $newRevision = $revisions->save('question', $staleQuestion->fresh(), $this->item(['exam_part_id' => $staleQuestion->exam_part_id, 'prompt' => 'Newer revision.']), 1);
        $this->actingAs($this->admin('reviewer'));
        $this->post('/admin/questions/approve', [
            'ids' => [$staleQuestion->id],
            'revision_ids' => [$staleQuestion->id => $staleRevision->id],
        ])->assertConflict();
        $this->assertSame('draft', $newRevision->fresh()->status);
    }

    public function test_thpt_bank_shows_single_and_bulk_approval_actions_for_questions_waiting_review(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin('content-admin'));
        $part = ExamPart::with('skill')->where('code', 'tieng-anh-part-1')->firstOrFail();
        $question = QuestionBankItemFactory::new()->create(['exam_part_id' => $part->id]);
        $revision = app(ContentRevisionService::class)->save('question', $question, $this->item([
            'id' => $question->id,
            'exam_part_id' => $part->id,
            'exam_type' => 'thpt',
            'part' => $part->code,
            'skill' => $part->skill->code,
        ]), 0);
        $revision->update(['status' => 'review']);

        $this->actingAs($this->admin('reviewer'));
        $this->get('/admin/questions/thpt')
            ->assertOk()
            ->assertSee('Duyệt câu đang chờ đã chọn')
            ->assertSee('aria-label="Duyệt câu hỏi Q-'.$question->id.'"', false);
    }

    public function test_thpt_bank_can_submit_one_or_multiple_draft_questions_for_review(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin('content-admin'));
        $part = ExamPart::with('skill')->where('code', 'tieng-anh-part-1')->firstOrFail();
        $revisions = app(ContentRevisionService::class);
        $questions = collect(range(1, 3))->map(function () use ($part, $revisions): QuestionBankItem {
            $question = QuestionBankItemFactory::new()->create(['exam_part_id' => $part->id]);
            $revisions->save('question', $question, $this->item([
                'id' => $question->id,
                'exam_part_id' => $part->id,
                'exam_type' => 'thpt',
                'part' => $part->code,
                'skill' => $part->skill->code,
            ]), 0);

            return $question->fresh();
        });

        $this->get('/admin/questions/thpt')
            ->assertOk()
            ->assertSee('data-select-all', false)
            ->assertSee('data-submit-selected-thpt-review', false)
            ->assertDontSee('data-thpt-review-select', false)
            ->assertSee('data-thpt-review-single', false)
            ->assertSee('Gửi duyệt câu nháp đã chọn');

        $this->post('/admin/questions/bulk', ['ids' => [$questions[0]->id], 'action' => 'review'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->post('/admin/questions/bulk', ['ids' => $questions->slice(1)->pluck('id')->all(), 'action' => 'review'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $questions->each(function (QuestionBankItem $question): void {
            $this->assertSame('review', $question->fresh()->draftRevision->status);
            $this->assertNull($question->fresh()->published_revision_id);
        });
    }
}
