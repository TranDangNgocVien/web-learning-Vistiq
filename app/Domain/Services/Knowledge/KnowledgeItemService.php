<?php

namespace VistiqCore\Services\Knowledge;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\LearningItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\System\AuditService;

class KnowledgeItemService
{
    public function __construct(
        private readonly ContentRevisionService $revisions,
        private readonly KnowledgeTaxonomyService $taxonomy,
    ) {}

    /**
     * Build the query for knowledge items based on request filters.
     */
    public function query(Request $request): Builder
    {
        $types = $this->taxonomy->types();
        $selectedType = $request->string('type')->toString();
        $typeIds = $types->pluck('id', 'code');
        if (! $typeIds->has($selectedType)) {
            $selectedType = '';
        }

        $selectedStatus = $request->string('status')->toString();
        $validStatuses = ['draft', 'review', 'approved', 'published', 'archived'];
        if (! in_array($selectedStatus, $validStatuses, true)) {
            $selectedStatus = '';
        }

        $search = $request->string('q')->toString();

        return LearningItem::query()
            ->whereIn('learning_item_type_id', $types->pluck('id'))
            ->when($search !== '', fn ($items) => $items->where('canonical_text', 'like', '%'.$search.'%'))
            ->when($selectedType !== '', fn ($items) => $items->where('learning_item_type_id', $typeIds[$selectedType]))
            ->when($selectedStatus !== '', fn ($items) => $items->where('status', $selectedStatus));
    }

    /**
     * Build the filtered, paginated listing for the admin index.
     *
     * @return array{items: LengthAwarePaginator, types: Collection, typeCounts: Collection, typeIds: Collection, levelsById: Collection, selectedType: string, search: string, selectedStatus: string}
     */
    public function listing(Request $request): array
    {
        $types = $this->taxonomy->types();
        $selectedType = $request->string('type')->toString();
        $typeIds = $types->pluck('id', 'code');
        if (! $typeIds->has($selectedType)) {
            $selectedType = '';
        }

        $selectedStatus = $request->string('status')->toString();
        $validStatuses = ['draft', 'review', 'approved', 'published', 'archived'];
        if (! in_array($selectedStatus, $validStatuses, true)) {
            $selectedStatus = '';
        }

        $search = $request->string('q')->toString();
        $query = $this->query($request);

        $typeCounts = LearningItem::query()
            ->selectRaw('learning_item_type_id, COUNT(*) as item_count')
            ->whereIn('learning_item_type_id', $types->pluck('id'))
            ->groupBy('learning_item_type_id')
            ->pluck('item_count', 'learning_item_type_id');

        return [
            'items' => $query->latest()->paginate(20)->withQueryString(),
            'types' => $types,
            'typeCounts' => $typeCounts,
            'typeIds' => $typeIds,
            'levelsById' => $this->taxonomy->levels()->pluck('label', 'id'),
            'selectedType' => $selectedType,
            'search' => $search,
            'selectedStatus' => $selectedStatus,
        ];
    }

    /**
     * Prepare the editor payload for create / edit views.
     *
     * @return array<string, mixed>
     */
    public function editorPayload(LearningItem $item, string $selectedType = 'vocabulary'): array
    {
        $types = $this->taxonomy->types();
        abort_if($item->exists && ! $types->contains('id', $item->learning_item_type_id), 404);

        if (! $types->contains('code', $selectedType)) {
            $selectedType = 'vocabulary';
        }

        $payload = $item->exists ? $this->revisions->payload('knowledge', $item) : ['type' => $selectedType];
        $selectedType = $payload['type'] ?? $selectedType;

        $dictionaryEntries = DB::table('dictionary_entries')
            ->join('learning_items', 'learning_items.id', '=', 'dictionary_entries.learning_item_id')
            ->leftJoin('learning_item_meanings', function ($join): void {
                $join->on('learning_item_meanings.learning_item_id', '=', 'learning_items.id')
                    ->where('learning_item_meanings.sense_number', 1);
            })
            ->orderBy('learning_items.canonical_text')
            ->get(['dictionary_entries.id', 'learning_items.canonical_text', 'learning_item_meanings.translation']);

        $relatedItems = LearningItem::query()
            ->join(DB::source('learning_item_types'), 'learning_item_types.id', '=', 'learning_items.learning_item_type_id')
            ->where('learning_item_types.code', '!=', 'question')
            ->when($item->exists, fn ($items) => $items->where('learning_items.id', '!=', $item->id))
            ->orderBy('learning_items.canonical_text')
            ->limit(500)
            ->get(['learning_items.id', 'learning_items.canonical_text']);

        return [
            'item' => $item,
            'workflowRevision' => $item->exists ? ContentRevision::find($item->draft_revision_id) : null,
            'payload' => $payload,
            'types' => $types,
            'selectedType' => $selectedType,
            'levels' => $this->taxonomy->levels(),
            'partsOfSpeech' => $this->taxonomy->partsOfSpeech(),
            'categories' => $this->taxonomy->grammarCategories(),
            'dictionaryEntries' => $dictionaryEntries,
            'relatedItems' => $relatedItems,
        ];
    }

    /**
     * Create a new knowledge item (draft).
     */
    public function store(array $data): LearningItem
    {
        return DB::transaction(function () use ($data) {
            $typeId = DB::table('learning_item_types')->where('code', $data['type'])->value('id');
            if (LearningItem::where('learning_item_type_id', $typeId)->whereRaw('LOWER(canonical_text) = ?', [mb_strtolower(trim($data['english'] ?? ''))])->exists()) {
                throw ValidationException::withMessages(['english' => 'Mục kiến thức này đã tồn tại trong cùng loại.']);
            }

            $item = LearningItem::create([
                'learning_item_type_id' => $typeId,
                'canonical_text' => $data['english'] ?? 'Bản nháp',
                'slug' => Str::slug($data['english'] ?? 'draft').'-'.Str::random(12),
                'created_by' => auth()->id(),
                'status' => 'draft',
            ]);

            $this->revisions->save('knowledge', $item, $data, 0);

            return $item;
        });
    }

    /**
     * Update an existing knowledge item via revision.
     */
    public function update(LearningItem $item, array $data): void
    {
        abort_unless($this->taxonomy->types()->contains('id', $item->learning_item_type_id), 404);
        $this->revisions->save('knowledge', $item, $data, $data['revision_counter'] ?? 0);
    }

    /**
     * Delete a draft knowledge item that has no external references.
     */
    public function destroy(LearningItem $item): void
    {
        abort_unless($this->taxonomy->types()->contains('id', $item->learning_item_type_id), 404);
        abort_if($item->status !== 'draft', 403, 'Chỉ có thể xóa kiến thức ở trạng thái bản nháp.');

        $hasQuestions = DB::table('question_bank_items')->where('learning_item_id', $item->id)->exists();
        $hasDictionary = DB::table('dictionary_entries')->where('learning_item_id', $item->id)->exists();
        $hasSavedItems = DB::table('user_saved_items')->where('learning_item_id', $item->id)->exists();
        $hasRelations = DB::table('learning_item_relations')
            ->where('source_learning_item_id', $item->id)
            ->orWhere('target_learning_item_id', $item->id)
            ->exists();

        abort_if(
            $hasQuestions || $hasDictionary || $hasRelations || $hasSavedItems,
            422,
            'Không thể xóa mục kiến thức đã được người dùng lưu hoặc có liên kết. Hãy lưu trữ mục này.'
        );

        DB::transaction(function () use ($item): void {
            app(AuditService::class)->record('knowledge.delete', 'knowledge', $item->id, ['title' => $item->canonical_text], ['deleted' => true]);
            ContentRevision::where('kind', 'knowledge')
                ->where('entity_id', $item->id)
                ->where('status', '!=', 'published')
                ->delete();

            $item->delete();
        });
    }
}
