<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\QuestionBankItem;

class AudioQuestionService
{
    /** @return Collection<int, QuestionBankItem> */
    public function questionsForStimulus(LearningStimulus $stimulus): Collection
    {
        $assetIds = $this->assetIdsForStimulus($stimulus);
        $groupIds = DB::table('question_groups')
            ->where(function ($query) use ($assetIds, $stimulus): void {
                if ($assetIds !== []) {
                    $query->whereIn('metadata->audio_id', $assetIds);
                }

                $query->orWhereJsonContains('stimulus_ids', $stimulus->id)
                    ->orWhereJsonContains('stimulus_ids', (string) $stimulus->id);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return QuestionBankItem::query()
            ->with(['questionType', 'examPart.examType', 'examPart.skill', 'draftRevision', 'publishedRevision', 'stimulus'])
            ->where(function (Builder $query) use ($stimulus, $assetIds, $groupIds): void {
                $query->where('learning_stimulus_id', $stimulus->id);

                if ($assetIds !== []) {
                    $matchesAudio = function (Builder $revision) use ($assetIds): void {
                        $revision->whereIn('payload->metadata->audio_id', $assetIds);
                    };

                    $query->orWhereHas('draftRevision', $matchesAudio)
                        ->orWhereHas('publishedRevision', $matchesAudio);
                }

                $matchesStimulus = fn (Builder $revision): Builder => $revision->whereJsonContains('payload->stimulus_ids', $stimulus->id)
                    ->orWhereJsonContains('payload->stimulus_ids', (string) $stimulus->id);
                $query->orWhereHas('draftRevision', $matchesStimulus)
                    ->orWhereHas('publishedRevision', $matchesStimulus);

                if ($groupIds !== []) {
                    $query->orWhereIn('question_group_id', $groupIds);
                }
            })
            ->get()
            ->unique('id')
            ->values();
    }

    /** @return array<int, int> */
    private function assetIdsForStimulus(LearningStimulus $stimulus): array
    {
        $ids = [(int) ($stimulus->metadata['asset_id'] ?? 0)];

        foreach (ContentRevision::query()->where(['kind' => 'stimulus', 'entity_id' => $stimulus->id])->get(['payload']) as $revision) {
            $ids[] = (int) ($revision->payload['metadata']['asset_id'] ?? 0);
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
