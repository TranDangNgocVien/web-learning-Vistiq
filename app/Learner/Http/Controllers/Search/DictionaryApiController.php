<?php

namespace VistiqLearner\Http\Controllers\Search;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use VistiqLearner\Http\Controllers\Controller;
use VistiqLearner\Http\Requests\Search\DictionaryLookupRequest;
use VistiqLearner\Http\Resources\Search\DictionaryEntryResource;
use VistiqLearner\Models\LearningItem;

class DictionaryApiController extends Controller
{
    public function index(DictionaryLookupRequest $request): AnonymousResourceCollection
    {
        $items = LearningItem::search($request->validated('q'), $request->validated('type') ?? 'all')
            ->paginate($request->integer('per_page', 12))->withQueryString();
        $items->through(fn (LearningItem $item): array => $item->lookupSummary());

        return DictionaryEntryResource::collection($items);
    }

    public function suggestions(DictionaryLookupRequest $request): AnonymousResourceCollection
    {
        $items = LearningItem::search($request->validated('q'), $request->validated('type') ?? 'all', true)->limit(8)->get();

        return DictionaryEntryResource::collection($items->map(fn (LearningItem $item): array => $item->lookupSummary()));
    }

    public function show(string $slug): DictionaryEntryResource
    {
        $item = LearningItem::publicLookup()->where('learning_items.slug', $slug)->firstOrFail();

        return new DictionaryEntryResource($item->lookupDetails());
    }
}
