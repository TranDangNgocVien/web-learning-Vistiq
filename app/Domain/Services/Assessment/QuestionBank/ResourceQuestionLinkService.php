<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use Illuminate\Support\Facades\DB;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\System\AuditService;

class ResourceQuestionLinkService
{
    public function __construct(
        private ContentRevisionService $revisions,
        private AuditService $audit,
    ) {}

    public function apply(MediaAsset $asset, array $questionIds, string $action): int
    {
        abort_unless(in_array($action, ['attach', 'detach'], true), 422);
        abort_unless(str_starts_with($asset->mime_type, 'audio/') || str_starts_with($asset->mime_type, 'image/'), 422, 'Chỉ audio hoặc hình ảnh mới có thể gắn vào câu hỏi.');

        $questionIds = collect($questionIds)->map(fn ($id): int => (int) $id)->unique()->values();
        abort_if($questionIds->isEmpty() || $questionIds->count() > 100, 422, 'Chọn từ 1 đến 100 câu hỏi.');

        return DB::transaction(function () use ($asset, $questionIds, $action): int {
            $asset = MediaAsset::query()->lockForUpdate()->findOrFail($asset->id);
            $questions = QuestionBankItem::query()
                ->with('learningItem')
                ->whereIn('id', $questionIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            abort_unless($questions->count() === $questionIds->count(), 422, 'Một số câu hỏi không còn tồn tại. Tải lại danh sách.');

            $field = str_starts_with($asset->mime_type, 'audio/') ? 'audio_id' : 'image_id';
            $changed = 0;
            foreach ($questions as $question) {
                $payload = $this->revisions->payload('question', $question);
                $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
                $currentId = (int) ($metadata[$field] ?? 0);
                if ($action === 'attach' && $currentId !== $asset->id) {
                    $metadata[$field] = $asset->id;
                } elseif ($action === 'detach' && $currentId === $asset->id) {
                    unset($metadata[$field]);
                } else {
                    continue;
                }

                $payload['metadata'] = $metadata;
                $this->revisions->save('question', $question, $payload, $question->revision_counter);
                $changed++;
            }

            $this->audit->record('resource.questions.'.$action, 'media', $asset->id, [], [
                'question_ids' => $questionIds->all(),
                'updated_questions' => $changed,
            ]);

            return $changed;
        });
    }
}
