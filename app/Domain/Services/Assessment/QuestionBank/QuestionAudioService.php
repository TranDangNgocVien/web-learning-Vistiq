<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use Illuminate\Support\Facades\DB;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\System\AuditService;

class QuestionAudioService
{
    public function __construct(
        private ContentRevisionService $revisions,
        private AuditService $audit,
    ) {}

    /**
     * @return array{group_id: int, asset_id: int, assigned_questions: int, updated_questions: int, current_question_id: int|null, revision_counter: int|null}
     */
    public function assignToGroup(int $groupId, int $assetId, ?int $currentQuestionId = null): array
    {
        $asset = MediaAsset::findOrFail($assetId);
        abort_unless(str_starts_with((string) $asset->mime_type, 'audio/'), 422, 'Chỉ audio mới có thể gắn cho nhóm câu hỏi.');

        return DB::transaction(function () use ($groupId, $assetId, $currentQuestionId): array {
            $group = DB::table('question_groups')->where('id', $groupId)->lockForUpdate()->first();
            abort_unless($group, 404);

            $questionIds = QuestionBankItem::query()
                ->where('question_group_id', $groupId)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);

            if ($currentQuestionId !== null && ! $questionIds->contains($currentQuestionId)) {
                $questionIds->push($currentQuestionId);
            }

            abort_unless($questionIds->isNotEmpty(), 422, 'Nhóm câu hỏi chưa có câu hỏi để gắn audio.');

            $questions = QuestionBankItem::query()
                ->whereIn('id', $questionIds->all())
                ->orderBy('group_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $nextGroupOrder = ((int) QuestionBankItem::query()->where('question_group_id', $groupId)->max('group_order')) + 1;
            $assignedQuestions = 0;
            $updatedQuestions = 0;
            $currentRevisionCounter = null;

            foreach ($questions as $question) {
                $payload = $this->revisions->payload('question', $question);
                $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
                $hasAudio = (int) ($metadata['audio_id'] ?? 0) === $assetId;
                $hasGroup = (int) ($payload['group_id'] ?? $question->question_group_id ?? 0) === $groupId;

                $payload['metadata'] = array_replace($metadata, ['audio_id' => $assetId]);
                $payload['group_id'] = $groupId;

                if (! $hasAudio || ! $hasGroup) {
                    $revision = $this->revisions->save('question', $question, $payload, $question->revision_counter);
                    $updatedQuestions++;
                    $revisionCounter = $revision->number;
                } else {
                    $revisionCounter = $question->revision_counter;
                }

                if ((int) $question->question_group_id !== $groupId && ! $question->published_revision_id) {
                    $question->forceFill([
                        'question_group_id' => $groupId,
                        'group_order' => $nextGroupOrder++,
                    ])->save();
                }

                if ($currentQuestionId !== null && $question->id === $currentQuestionId) {
                    $currentRevisionCounter = $revisionCounter;
                }

                $assignedQuestions++;
            }

            $groupMetadata = json_decode((string) ($group->metadata ?? ''), true);
            $groupMetadata = is_array($groupMetadata) ? $groupMetadata : [];
            $groupMetadata['audio_id'] = $assetId;
            DB::table('question_groups')->where('id', $groupId)->update([
                'metadata' => json_encode($groupMetadata, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            $this->audit->record('question-group.audio.assign', 'question_group', $groupId, [], [
                'asset_id' => $assetId,
                'assigned_questions' => $assignedQuestions,
                'updated_questions' => $updatedQuestions,
            ]);

            return [
                'group_id' => $groupId,
                'asset_id' => $assetId,
                'assigned_questions' => $assignedQuestions,
                'updated_questions' => $updatedQuestions,
                'current_question_id' => $currentQuestionId,
                'revision_counter' => $currentRevisionCounter,
            ];
        });
    }

    /**
     * @return array{group_id: int, asset_id: int, updated_questions: int}
     */
    public function removeFromGroup(int $groupId, int $assetId): array
    {
        $asset = MediaAsset::findOrFail($assetId);
        abort_unless(str_starts_with((string) $asset->mime_type, 'audio/'), 422, 'Chỉ audio mới có thể gỡ khỏi nhóm câu hỏi.');

        return DB::transaction(function () use ($groupId, $assetId): array {
            $group = DB::table('question_groups')->where('id', $groupId)->lockForUpdate()->first();
            abort_unless($group, 404);

            $metadata = json_decode((string) ($group->metadata ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];
            abort_unless((int) ($metadata['audio_id'] ?? 0) === $assetId, 409, 'Nhóm này không còn gắn với tài nguyên audio đã chọn.');

            $questions = QuestionBankItem::query()
                ->where('question_group_id', $groupId)
                ->orderBy('group_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $updatedQuestions = 0;

            foreach ($questions as $question) {
                $payload = $this->revisions->payload('question', $question);
                $questionMetadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
                if ((int) ($questionMetadata['audio_id'] ?? 0) !== $assetId) {
                    continue;
                }

                unset($questionMetadata['audio_id']);
                $payload['metadata'] = $questionMetadata;
                $this->revisions->save('question', $question, $payload, $question->revision_counter);
                $updatedQuestions++;
            }

            unset($metadata['audio_id']);
            DB::table('question_groups')->where('id', $groupId)->update([
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            $this->audit->record('question-group.audio.remove', 'question_group', $groupId, ['audio_id' => $assetId], ['updated_questions' => $updatedQuestions]);

            return ['group_id' => $groupId, 'asset_id' => $assetId, 'updated_questions' => $updatedQuestions];
        });
    }
}
