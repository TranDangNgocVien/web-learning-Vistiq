<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use Illuminate\Database\Eloquent\Collection as ModelCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\User;

class ResourceLibraryService
{
    public function __construct(private MediaLibraryService $library) {}

    public function catalog(): Collection
    {
        $assets = MediaAsset::query()->latest('updated_at')->latest('id')->get();
        $questions = $this->library->questionsForAssets($assets);
        $creators = User::query()->whereIn('id', $assets->pluck('created_by')->filter()->unique())->pluck('name', 'id');

        return $assets->map(function (MediaAsset $asset) use ($questions, $creators): MediaAsset {
            $linked = $questions[$asset->id] ?? collect();
            $categories = $linked->flatMap(fn ($question): array => array_filter([
                $question->examPart?->examType?->name,
                $question->examPart?->name,
                $question->examPart?->skill?->name,
            ]))->merge(is_array($asset->metadata['tags'] ?? null) ? $asset->metadata['tags'] : [])->unique()->values();
            $asset->setRelation('linkedQuestions', $linked);
            $asset->setAttribute('resource_kind', str_starts_with($asset->mime_type, 'audio/') ? 'audio' : (str_starts_with($asset->mime_type, 'image/') ? 'image' : 'file'));
            $asset->setAttribute('resource_format', match ($asset->mime_type) {
                'audio/mpeg', 'audio/mp3' => 'mp3',
                'audio/wav', 'audio/x-wav', 'audio/vnd.wave' => 'wav',
                'audio/mp4', 'audio/x-m4a' => 'm4a',
                'audio/ogg', 'application/ogg' => 'ogg',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => strtolower(pathinfo($asset->path, PATHINFO_EXTENSION)),
            });
            $asset->setAttribute('resource_categories', $categories->all());
            $asset->setAttribute('resource_category', $linked->first()?->examPart ? trim(($linked->first()->examPart->examType?->name ?? '').' '.$linked->first()->examPart->name) : ($asset->metadata['category'] ?? ($categories->take(2)->implode(' ') ?: 'Khác')));
            $asset->setAttribute('resource_creator', $creators[$asset->created_by] ?? 'Không xác định');
            $asset->setAttribute('resource_status', $linked->isNotEmpty() ? 'used' : 'unassigned');

            return $asset;
        });
    }

    public function stimuliForAssets(Collection $assets): array
    {
        if ($assets->isEmpty()) {
            return [];
        }
        $assetIds = $assets->modelKeys();
        $revisions = ContentRevision::query()->where('kind', 'stimulus')->whereIn('payload->metadata->asset_id', $assetIds)->get(['entity_id', 'payload']);
        $stimuli = LearningStimulus::query()->whereIn('metadata->asset_id', $assetIds)->orWhereIn('id', $revisions->pluck('entity_id'))->get();
        $revisionAssets = $revisions->groupBy('entity_id');
        $result = [];
        foreach ($stimuli as $stimulus) {
            $ids = ($revisionAssets[$stimulus->id] ?? collect())->pluck('payload.metadata.asset_id')->push($stimulus->metadata['asset_id'] ?? null)->filter()->unique();
            foreach (array_intersect($assetIds, $ids->all()) as $assetId) {
                $result[$assetId] ??= new ModelCollection;
                $result[$assetId]->push($stimulus);
            }
        }

        return $result;
    }

    public function stimuliForAsset(MediaAsset $asset): ModelCollection
    {
        return $this->stimuliForAssets(new ModelCollection([$asset]))[$asset->id] ?? new ModelCollection;
    }

    public function isReferenced(MediaAsset $asset, array $ignoredStimulusIds = []): bool
    {
        if (($this->library->questionsForAssets(new ModelCollection([$asset]))[$asset->id] ?? collect())->isNotEmpty()) {
            return true;
        }

        // Historical revisions and group assignments must remain playable too.
        return ContentRevision::query()->where(function ($query) use ($ignoredStimulusIds): void {
            $query->where('kind', '!=', 'stimulus')->orWhereNotIn('entity_id', $ignoredStimulusIds);
        })->where(function ($query) use ($asset): void {
            foreach (['audio_id', 'image_id', 'asset_id'] as $key) {
                $query->orWhere('payload->metadata->'.$key, $asset->id);
            }
        })->exists()
            || LearningStimulus::query()->whereNotIn('id', $ignoredStimulusIds)->where('metadata->asset_id', $asset->id)->exists()
            || DB::table('question_groups')->where('metadata->audio_id', $asset->id)->orWhere('metadata->image_id', $asset->id)->exists();
    }
}
