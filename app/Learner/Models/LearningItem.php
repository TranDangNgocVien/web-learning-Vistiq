<?php

namespace VistiqLearner\Models;

use Database\Learner\Factories\LearningItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Str;

#[Fillable(['learning_item_type_id', 'proficiency_level_id', 'created_by', 'canonical_text', 'slug', 'status', 'published_at'])]
class LearningItem extends Model
{
    use \VistiqCore\Models\Concerns\StoresClassifications;

    /** @use HasFactory<LearningItemFactory> */
    use HasFactory;

    public const LOOKUP_TYPES = ['vocabulary', 'collocation', 'phrasal_verb', 'idiom', 'grammar'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    /** @return Builder<static> */
    public static function publicLookup(): Builder
    {
        return static::query()
            ->join(DB::source('learning_item_types as item_type'), 'item_type.id', '=', 'learning_items.learning_item_type_id')
            ->leftJoin(DB::source('proficiency_levels as level'), 'level.id', '=', 'learning_items.proficiency_level_id')
            ->where('learning_items.status', 'published')
            ->where(fn (Builder $query): Builder => $query->whereNull('learning_items.published_at')->orWhere('learning_items.published_at', '<=', now()))
            ->whereIn('item_type.code', self::LOOKUP_TYPES)
            ->select('learning_items.id', 'learning_items.canonical_text', 'learning_items.slug', 'item_type.code as type', 'item_type.label as type_label', 'level.code as level')
            ->selectSub(DB::table('learning_item_meanings')->select('translation')->whereColumn('learning_item_id', 'learning_items.id')->orderBy('sense_number')->limit(1), 'preview');
    }

    /** @return Builder<static> */
    public static function search(string $term, string $type = 'all', bool $suggestions = false): Builder
    {
        $query = static::publicLookup();

        if ($type !== 'all') {
            $query->where('item_type.code', $type);
        }

        if ($term === '') {
            return $query->orderBy('learning_items.canonical_text')->orderBy('learning_items.id');
        }

        $normalized = mb_strtolower($term);
        $slug = Str::slug($normalized);
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $normalized);
        $hasWildcard = str_contains($normalized, '%') || str_contains($normalized, '_');

        if ($suggestions) {
            if ($slug === '' || $hasWildcard) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('learning_items.slug', 'like', $slug.'%')
                ->orderByRaw('CASE WHEN learning_items.slug = ? THEN 0 ELSE 1 END', [$slug])
                ->orderBy('learning_items.slug')->orderBy('learning_items.id');
        }

        $fullTextTerm = static::fullTextPrefixTerm($normalized);

        if (DB::connection()->getDriverName() === 'mysql' && $slug !== '' && ! $hasWildcard && $fullTextTerm !== null) {
            $matchingIds = DB::table('learning_items')
                ->whereFullText('canonical_text', $fullTextTerm, ['mode' => 'boolean'])
                ->pluck('id')
                ->merge(DB::table('learning_item_meanings')
                    ->whereFullText('translation', $fullTextTerm, ['mode' => 'boolean'])
                    ->pluck('learning_item_id'))
                ->merge(DB::table('learning_items')->where('slug', 'like', $slug.'%')->pluck('id'))
                ->unique()->values()->all();

            $query->whereIn('learning_items.id', $matchingIds);

            return $query->orderByRaw('CASE WHEN learning_items.slug = ? THEN 0 WHEN learning_items.slug LIKE ? THEN 1 ELSE 2 END', [$slug, $slug.'%'])
                ->orderBy('learning_items.canonical_text')->orderBy('learning_items.id');
        }

        $query->where(function (Builder $query) use ($escaped, $suggestions): void {
            $query->whereRaw("LOWER(learning_items.canonical_text) LIKE ? ESCAPE '!'", [$escaped.'%']);

            if (! $suggestions) {
                $query->orWhereRaw("LOWER(learning_items.canonical_text) LIKE ? ESCAPE '!'", ['%'.$escaped.'%'])
                    ->orWhereExists(function (QueryBuilder $meanings) use ($escaped): void {
                        $meanings->selectRaw('1')->from('learning_item_meanings')
                            ->whereColumn('learning_item_id', 'learning_items.id')
                            ->whereRaw("LOWER(translation) LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
                    });
            }
        });

        return $query->orderByRaw("CASE WHEN LOWER(learning_items.canonical_text) = ? THEN 0 WHEN LOWER(learning_items.canonical_text) LIKE ? ESCAPE '!' THEN 1 ELSE 2 END", [$normalized, $escaped.'%'])
            ->orderBy('learning_items.canonical_text')->orderBy('learning_items.id');
    }

    private static function fullTextPrefixTerm(string $term): ?string
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $term, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($tokens)) {
            return null;
        }

        $tokens = array_values(array_filter($tokens, fn (string $token): bool => mb_strlen($token) >= 3));

        if ($tokens === []) {
            return null;
        }

        return implode(' ', array_map(fn (string $token): string => '+'.$token.'*', $tokens));
    }

    /** @return array{id: int, term: string, slug: string, type: string, type_label: string, level: ?string, preview: ?string, url: string} */
    public function lookupSummary(): array
    {
        return [
            'id' => $this->id, 'term' => $this->canonical_text, 'slug' => $this->slug,
            'type' => $this->type, 'type_label' => $this->type_label, 'level' => $this->level,
            'preview' => $this->preview,
            'url' => route('lookup.entry', $this->slug),
        ];
    }

    /** @return array<string, mixed> */
    public function lookupDetails(): array
    {
        $entry = DB::table('dictionary_entries')->where('learning_item_id', $this->id)->first();
        $meanings = DB::table('learning_item_meanings as meaning')
            ->leftJoin(DB::source('parts_of_speech as pos'), 'pos.id', '=', 'meaning.part_of_speech_id')
            ->where('meaning.learning_item_id', $this->id)
            ->select('meaning.id', 'meaning.sense_number', 'meaning.translation', 'meaning.definition', 'meaning.usage_note', 'pos.code as part_of_speech', 'pos.label as part_of_speech_label')
            ->orderBy('meaning.sense_number')->get();
        $examples = DB::table('learning_item_meaning_examples')->whereIn('learning_item_meaning_id', $meanings->pluck('id'))
            ->orderBy('sort_order')->orderBy('id')->get()->groupBy('learning_item_meaning_id');

        $topic = DB::table('grammar_topics as topic')->join(DB::source('grammar_categories as category'), 'category.id', '=', 'topic.grammar_category_id')
            ->where('topic.learning_item_id', $this->id)->select('topic.id', 'topic.summary', 'topic.content', 'category.name as category')->first();
        $rules = $topic ? DB::table('grammar_rules')->where('grammar_topic_id', $topic->id)->orderBy('sort_order')->orderBy('id')->get() : collect();
        $grammarExamples = DB::table('grammar_examples')->whereIn('grammar_rule_id', $rules->pluck('id'))->orderBy('sort_order')->orderBy('id')->get()->groupBy('grammar_rule_id');
        $related = $this->relatedItems($entry?->id, $meanings->pluck('part_of_speech')->filter()->all());
        $audioUrl = $entry?->audio_url;

        return [
            ...$this->lookupSummary(),
            'phonetic' => $entry?->phonetic,
            'audio_url' => is_string($audioUrl) && preg_match('#^https://[^\\s]+$#i', $audioUrl) ? $audioUrl : null,
            'meanings' => $meanings->map(fn (object $meaning): array => [
                'sense_number' => $meaning->sense_number, 'translation' => $meaning->translation,
                'definition' => $meaning->definition, 'usage_note' => $meaning->usage_note,
                'part_of_speech' => $meaning->part_of_speech, 'part_of_speech_label' => $meaning->part_of_speech_label,
                'examples' => $examples->get($meaning->id, collect())->map(fn (object $example): array => ['english' => $example->english, 'vietnamese' => $example->vietnamese])->all(),
            ])->all(),
            'grammar' => $topic ? [
                'summary' => $topic->summary, 'content' => $topic->content, 'category' => $topic->category,
                'rules' => $rules->map(fn (object $rule): array => [
                    'title' => $rule->title, 'formula' => $rule->formula, 'explanation' => $rule->explanation,
                    'examples' => $grammarExamples->get($rule->id, collect())->map(fn (object $example): array => ['english' => $example->english, 'vietnamese' => $example->vietnamese])->all(),
                ])->all(),
            ] : null,
            'related_items' => array_values(array_filter($related, fn (array $item): bool => $item['type'] !== 'grammar')),
            'related_grammar' => array_values(array_filter($related, fn (array $item): bool => $item['type'] === 'grammar')),
        ];
    }

    /**
     * Batch IDs from the imported tables, which have no dedicated model layer.
     *
     * @param  array<int, string>  $partsOfSpeech
     * @return array<int, array<string, mixed>>
     */
    private function relatedItems(?int $dictionaryEntryId, array $partsOfSpeech): array
    {
        $outgoingRelations = DB::table('learning_item_relations')->where('source_learning_item_id', $this->id)->get();
        $incomingRelations = DB::table('learning_item_relations')->where('target_learning_item_id', $this->id)->get();
        $outgoing = $outgoingRelations->pluck('target_learning_item_id');
        $incoming = $incomingRelations->pluck('source_learning_item_id');
        $relationTypes = $outgoingRelations->map(fn (object $relation): array => ['item_id' => $relation->target_learning_item_id, 'type' => $relation->relation_type])
            ->merge($incomingRelations->map(fn (object $relation): array => ['item_id' => $relation->source_learning_item_id, 'type' => $relation->relation_type]))
            ->groupBy('item_id')->map(fn (Collection $relations): array => $relations->pluck('type')->unique()->values()->all());
        $linkedIds = $outgoing->merge($incoming);

        if ($dictionaryEntryId) {
            $linkedIds = $linkedIds
                ->merge(DB::table('collocations')->join(DB::source('collocation_dictionary_entries as pivot'), 'pivot.collocation_id', '=', 'collocations.id')->where('pivot.dictionary_entry_id', $dictionaryEntryId)->pluck('learning_item_id'))
                ->merge(DB::table('phrasal_verbs')->where('base_dictionary_entry_id', $dictionaryEntryId)->pluck('learning_item_id'));
        }

        $tagSlugs = array_map(fn (string $code): string => 'pos-'.$code, $partsOfSpeech);
        $tagSlugs[] = 'type-'.$this->type;
        $grammarIds = DB::table('learning_item_tags as pivot')->join('tags', 'tags.id', '=', 'pivot.tag_id')
            ->join('grammar_topics', 'grammar_topics.learning_item_id', '=', 'pivot.learning_item_id')
            ->whereIn('tags.slug', $tagSlugs)->pluck('pivot.learning_item_id');

        return static::publicLookup()->whereIn('learning_items.id', $linkedIds->merge($grammarIds)->unique())
            ->where('learning_items.id', '!=', $this->id)->orderBy('learning_items.canonical_text')->limit(30)->get()
            ->map(fn (LearningItem $item): array => [...$item->lookupSummary(), 'relation_types' => collect($relationTypes->get($item->id, []))->flatten()->unique()->values()->all(), 'relation_reason' => $linkedIds->contains($item->id) ? 'linked' : 'part_of_speech'])->all();
    }
}
