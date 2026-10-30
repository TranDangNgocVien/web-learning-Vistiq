<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\System\AuditService;

class QuestionDeletionService
{
    public function __construct(private QuestionEditorService $editor, private AuditService $audit) {}

    /**
     * Returns deleted for a soft-deleted question, disabled for a question kept
     * in a published exam, and skipped when that action is not authorized.
     */
    public function delete(int $questionId, bool $skipUnauthorizedPublished = false): string
    {
        Gate::authorize('content.edit');

        return DB::transaction(function () use ($questionId, $skipUnauthorizedPublished): string {
            $question = QuestionBankItem::with('learningItem')->lockForUpdate()->findOrFail($questionId);
            $links = collect($this->editor->examLinks($question));
            $usedInPublishedExam = $links->contains(fn (array $link): bool => $link['published']);

            if ($usedInPublishedExam) {
                if (! Gate::allows('content.publish')) {
                    abort_unless($skipUnauthorizedPublished, 403, 'Câu hỏi vẫn nằm trong đề đã phát hành. Cần quyền xuất bản nội dung để ngừng sử dụng câu hỏi này.');

                    return 'skipped';
                }

                if ($question->contentStatus() !== 'archived') {
                    $this->disablePublishedQuestion($question);
                }
                $this->detachFromDraftExams($question, $links);

                return 'disabled';
            }

            $draftExamIds = $this->detachFromDraftExams($question, $links);

            $before = [
                'status' => $question->contentStatus(),
                'draft_exam_ids' => $draftExamIds,
                'attempt_data_preserved' => true,
            ];
            $question->delete();
            $this->audit->record('question.delete', 'question', $question->id, $before, ['soft_deleted' => true]);

            return 'deleted';
        }, 3);
    }

    private function detachFromDraftExams(QuestionBankItem $question, Collection $links): array
    {
        $draftExamIds = [];
        foreach ($links->where('draft', true)->where('published', false) as $link) {
            $exam = ExamTemplate::lockForUpdate()->findOrFail((int) $link['id']);
            $this->editor->changeExamLink($question, $exam, false, (int) $link['revision_counter']);
            $draftExamIds[] = (int) $link['id'];
        }

        return $draftExamIds;
    }

    private function disablePublishedQuestion(QuestionBankItem $question): void
    {
        $revision = $question->published_revision_id
            ? ContentRevision::whereKey($question->published_revision_id)->where('kind', 'question')->where('entity_id', $question->id)->where('status', 'published')->first()
            : null;

        if ($revision) {
            app(ContentRevisionService::class)->transition('question', $question, 'archive');

            return;
        }

        $before = ['status' => $question->contentStatus()];
        $question->forceFill(['status' => 'archived'])->save();
        $question->learningItem?->forceFill(['status' => 'archived'])->save();
        $this->audit->record('content.archive', 'question', $question->id, $before, ['status' => 'archived', 'kept_in_published_exams' => true]);
    }
}
