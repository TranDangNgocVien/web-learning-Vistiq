<?php

namespace VistiqCore\Services\Knowledge;

use Illuminate\Database\Query\Builder;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use VistiqCore\Models\LearningItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;

class KnowledgeReaderService
{
    public function publishedQuery(array $filters = []): Builder
    {
        return DB::table('learning_items as item')
            ->join(DB::source('learning_item_types as type'), 'type.id', '=', 'item.learning_item_type_id')
            ->leftJoin(DB::source('proficiency_levels as level'), 'level.id', '=', 'item.proficiency_level_id')
            ->where('item.status', 'published')->where('type.code', '!=', 'question')
            ->where(fn ($q) => $q->whereNull('item.published_at')->orWhere('item.published_at', '<=', now()))
            ->when(! empty($filters['q']), fn ($q) => $q->where('item.canonical_text', 'like', '%'.$filters['q'].'%'))
            ->when(! empty($filters['type']) && $filters['type'] !== 'all', fn ($q) => $q->where('type.code', $filters['type']))
            ->when(! empty($filters['level']), fn ($q) => $q->where('level.code', $filters['level']))
            ->select('item.id', 'item.canonical_text', 'item.slug', 'type.code as type_code', 'type.label as type_label', 'level.code as level_code', 'level.label as level_label')
            ->selectSub(DB::table('dictionary_entries')->select('audio_url')->whereColumn('learning_item_id', 'item.id')->limit(1), 'audio_url');
    }

    /** Public read contract for Knowledge and Profile; owner is supplied by the authenticated caller. */
    public function savedItems(int $owner, array $filters = []): mixed
    {
        return $this->publishedQuery($filters)->join('user_saved_items as saved', 'saved.learning_item_id', '=', 'item.id')
            ->where('saved.user_id', $owner)->addSelect('saved.personal_note', 'saved.is_starred', 'saved.collection_id', 'saved.created_at as saved_at')
            ->when(! empty($filters['collection']), fn ($q) => $q->where('saved.collection_id', $filters['collection']))
            ->selectSub(DB::table('learning_item_meanings')->select('translation')->whereColumn('learning_item_id', 'item.id')->orderBy('sense_number')->limit(1), 'meaning')
            ->selectSub(DB::table('dictionary_entries')->select('phonetic')->whereColumn('learning_item_id', 'item.id')->limit(1), 'ipa')
            ->selectSub(DB::table('learning_item_meaning_examples as example')->join('learning_item_meanings as meaning', 'meaning.id', '=', 'example.learning_item_meaning_id')->whereColumn('meaning.learning_item_id', 'item.id')->select('example.english')->orderBy('meaning.sense_number')->orderBy('example.sort_order')->limit(1), 'example')
            ->when(($filters['sort'] ?? '') === 'alphabetical', fn ($q) => $q->orderBy('item.canonical_text'))
            ->when(($filters['sort'] ?? '') === 'oldest', fn ($q) => $q->orderBy('saved.created_at'), fn ($q) => $q->orderByDesc('saved.created_at'))
            ->orderByDesc('saved.id')->paginate(12)->withQueryString();
    }

    public function library(int $owner, array $filters = []): array
    {
        $items = $this->savedItems($owner, $filters);
        $counts = $this->publishedQuery()->join('user_saved_items as saved', 'saved.learning_item_id', '=', 'item.id')->where('saved.user_id', $owner)
            ->reorder()->select('type.code')->selectRaw('COUNT(*) as total')->groupBy('type.code')->pluck('total', 'code');
        $collections = DB::table('user_knowledge_collections as c')->leftJoin('user_saved_items as s', fn ($join) => $join->on('s.collection_id', '=', 'c.id')->on('s.user_id', '=', 'c.user_id'))
            ->where('c.user_id', $owner)->select('c.id', 'c.name')->selectRaw('COUNT(s.id) as total')->groupBy('c.id', 'c.name')->orderBy('c.name')->get();
        $tags = DB::table('learning_item_tags as pivot')->join('tags', 'tags.id', '=', 'pivot.tag_id')->whereIn('pivot.learning_item_id', $items->pluck('id'))->select('pivot.learning_item_id', 'tags.name')->get()->groupBy('learning_item_id');
        $selected = null;
        $slug = ($filters['detail'] ?? '') === 'hidden' ? null : ($filters['item'] ?? $items->first()?->slug);
        if ($slug) {
            // Only the session owner's saved, currently published items may enter the detail pane.
            $saved = $this->publishedQuery()->join('user_saved_items as saved', 'saved.learning_item_id', '=', 'item.id')->where('saved.user_id', $owner)->where('item.slug', $slug)->addSelect('saved.personal_note', 'saved.collection_id')->first();
            abort_if(isset($filters['item']) && ! $saved, 404);
            if ($saved) {
                $selected = $this->detail($slug) + ['saved' => $saved];
            }
        }

        return compact('items', 'counts', 'collections', 'tags', 'selected', 'filters');
    }

    public function detail(string $slug): array
    {
        $summary = $this->publishedQuery()->where('item.slug', $slug)->first();
        abort_unless($summary, 404, 'Mục kiến thức không tồn tại hoặc chưa được xuất bản.');
        $item = LearningItem::findOrFail($summary->id);
        $payload = app(ContentRevisionService::class)->payload('knowledge', $item, false);
        $payload['content'] = app(KnowledgeTaxonomyService::class)->sanitizeHtml($payload['content'] ?? '');
        foreach ($payload['grammar_rules'] ?? [] as $key => $rule) {
            $payload['grammar_rules'][$key]['explanation'] = app(KnowledgeTaxonomyService::class)->sanitizeHtml($rule['explanation'] ?? '');
        }

        return compact('summary', 'payload');
    }
}
