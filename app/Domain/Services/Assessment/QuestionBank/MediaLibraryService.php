<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\QuestionBankItem;

class MediaLibraryService
{
    /** @return array<int, Collection<int, QuestionBankItem>> */
    public function questionsForAssets(Collection $assets): array
    {
        if ($assets->isEmpty()) {
            return [];
        }

        $assetIds = $assets->modelKeys();
        $questionIds = $assets->flatMap(function (MediaAsset $asset): array {
            $questionId = filter_var($asset->metadata['question_id'] ?? null, FILTER_VALIDATE_INT);
            $path = $asset->metadata['storage_folder'] ?? $asset->path;

            if (preg_match('/(?:^|\/)Q-(\d{1,10})(?:\/|$)/i', (string) $path, $matches) === 1) {
                $questionId = (int) $matches[1];
            }

            return $questionId ? [(int) $questionId] : [];
        })->unique()->values()->all();
        $stimulusRevisions = ContentRevision::query()
            ->where('kind', 'stimulus')
            ->whereIn('payload->metadata->asset_id', $assetIds)
            ->get(['entity_id', 'payload']);
        $assetIdsByStimulusId = $stimulusRevisions->groupBy('entity_id')->map(
            fn (Collection $revisions): array => $revisions->pluck('payload.metadata.asset_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        );
        foreach (LearningStimulus::query()->whereIn('metadata->asset_id', $assetIds)->get(['id', 'metadata']) as $stimulus) {
            $assetIdsByStimulusId[$stimulus->id] = array_values(array_unique([
                ...($assetIdsByStimulusId[$stimulus->id] ?? []), (int) $stimulus->metadata['asset_id'],
            ]));
        }
        $stimulusIds = $assetIdsByStimulusId->keys()->map(fn ($id): int => (int) $id)->all();
        $stimulusRevisionIds = [];
        if ($stimulusIds !== []) {
            foreach (DB::table('content_revisions')->where('kind', 'question')->whereNotNull('payload->stimulus_ids')->get(['id', 'payload->stimulus_ids as stimulus_ids']) as $revision) {
                $ids = json_decode($revision->stimulus_ids, true) ?: [];
                if (array_intersect($stimulusIds, array_map('intval', $ids)) !== []) {
                    $stimulusRevisionIds[] = $revision->id;
                }
            }
        }
        $groups = DB::table('question_groups')->where(function ($query) use ($assetIds, $stimulusIds): void {
            $query->whereIn('metadata->audio_id', $assetIds)->orWhereIn('metadata->image_id', $assetIds);
            foreach ($stimulusIds as $stimulusId) {
                // Form submissions and older imports can store IDs as JSON strings.
                $query->orWhereJsonContains('stimulus_ids', $stimulusId)
                    ->orWhereJsonContains('stimulus_ids', (string) $stimulusId);
            }
        })->get(['id', 'metadata', 'stimulus_ids']);
        $assetIdsByGroupId = $groups->mapWithKeys(function ($group) use ($assetIdsByStimulusId): array {
            $metadata = json_decode($group->metadata ?? '{}', true) ?: [];
            $ids = [$metadata['audio_id'] ?? null, $metadata['image_id'] ?? null];
            foreach (json_decode($group->stimulus_ids ?? '[]', true) ?: [] as $stimulusId) {
                $ids = [...$ids, ...($assetIdsByStimulusId[(string) $stimulusId] ?? [])];
            }

            return [$group->id => array_values(array_unique(array_map('intval', array_filter($ids))))];
        });

        $questions = QuestionBankItem::query()
            ->with(['questionType', 'examPart.examType', 'examPart.skill', 'draftRevision', 'publishedRevision', 'stimulus'])
            ->where(function (Builder $query) use ($assetIds, $questionIds, $stimulusIds, $stimulusRevisionIds, $groups): void {
                if ($questionIds !== []) {
                    $query->whereIn('id', $questionIds);
                }

                $matchesRevision = function (Builder $revision) use ($assetIds): void {
                    $revision->whereIn('payload->metadata->audio_id', $assetIds)
                        ->orWhereIn('payload->metadata->image_id', $assetIds);
                };

                $query->orWhereHas('draftRevision', $matchesRevision)
                    ->orWhereHas('publishedRevision', $matchesRevision)
                    ->orWhereHas('stimulus', fn (Builder $stimulus): Builder => $stimulus->whereIn('metadata->asset_id', $assetIds));

                if ($stimulusIds !== []) {
                    $query->orWhereIn('learning_stimulus_id', $stimulusIds);
                }
                if ($stimulusRevisionIds !== []) {
                    $query->orWhereIn('draft_revision_id', $stimulusRevisionIds)
                        ->orWhereIn('published_revision_id', $stimulusRevisionIds);
                }
                if ($groups->isNotEmpty()) {
                    $query->orWhereIn('question_group_id', $groups->pluck('id'));
                }
            })
            ->get();

        $questionsByAssetId = [];

        foreach ($questions as $question) {
            $questionAssetIds = [];

            foreach ([$question->draftRevision, $question->publishedRevision] as $revision) {
                $metadata = $revision?->payload['metadata'] ?? [];
                $questionAssetIds[] = $metadata['audio_id'] ?? null;
                $questionAssetIds[] = $metadata['image_id'] ?? null;
                foreach ($revision?->payload['stimulus_ids'] ?? [] as $stimulusId) {
                    $questionAssetIds = [...$questionAssetIds, ...($assetIdsByStimulusId[(int) $stimulusId] ?? [])];
                }
            }

            $questionAssetIds[] = $question->stimulus?->metadata['asset_id'] ?? null;
            $questionAssetIds = [...$questionAssetIds, ...($assetIdsByStimulusId[(string) $question->learning_stimulus_id] ?? [])];
            $questionAssetIds = [...$questionAssetIds, ...($assetIdsByGroupId[$question->question_group_id] ?? [])];

            foreach (array_intersect($assetIds, array_map('intval', array_filter($questionAssetIds))) as $assetId) {
                $questionsByAssetId[$assetId] ??= collect();
                $questionsByAssetId[$assetId]->push($question);
            }
        }

        foreach ($assets as $asset) {
            $questionId = filter_var($asset->metadata['question_id'] ?? null, FILTER_VALIDATE_INT);
            $path = $asset->metadata['storage_folder'] ?? $asset->path;

            if (preg_match('/(?:^|\/)Q-(\d{1,10})(?:\/|$)/i', (string) $path, $matches) === 1) {
                $questionId = (int) $matches[1];
            }

            $question = $questions->firstWhere('id', (int) $questionId);

            if ($question && ! ($questionsByAssetId[$asset->id] ?? collect())->contains('id', $question->id)) {
                $questionsByAssetId[$asset->id] ??= collect();
                $questionsByAssetId[$asset->id]->push($question);
            }
        }

        return $questionsByAssetId;
    }
}
