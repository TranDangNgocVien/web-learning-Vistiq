<?php

namespace Tests\Learner\Feature\Search;

use Database\Learner\Seeders\DictionaryGrammarSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Learner\TestCase;
use VistiqLearner\Models\LearningItem;
use VistiqLearner\Models\User;

class DictionaryLookupTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_can_open_the_search_page_without_logging_in(): void
    {
        $this->get('/lookup')->assertOk()->assertSee('không cần đăng nhập')->assertSee('name="q"', false);
    }

    public function test_search_normalizes_case_and_spacing_and_ranks_exact_matches_first(): void
    {
        LearningItem::factory()->create(['canonical_text' => 'achiever', 'slug' => 'achiever']);
        LearningItem::factory()->create(['canonical_text' => 'achieve', 'slug' => 'achieve']);

        $this->getJson('/api/v1/dictionary?q=%20%20ACHIEVE%20%20')
            ->assertOk()->assertJsonPath('data.0.term', 'achieve')->assertJsonPath('data.1.term', 'achiever')->assertJsonPath('meta.total', 2);
    }

    public function test_search_matches_terms_inside_compound_words(): void
    {
        LearningItem::factory()->create(['canonical_text' => 'achieve', 'slug' => 'achieve']);
        LearningItem::factory()->create(['canonical_text' => 'self-achievement', 'slug' => 'self-achievement']);

        $this->getJson('/api/v1/dictionary?q=achieve')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.term', 'achieve')
            ->assertJsonPath('data.1.term', 'self-achievement');
    }

    public function test_legacy_lookup_routes_preserve_search_filters_and_select_the_matching_tab(): void
    {
        $this->get('/demo/lookup-collocations?q=take%20into%20account&type=collocation')
            ->assertRedirect(route('lookup.index', ['q' => 'take into account', 'type' => 'collocation', 'tab' => 'collocations']));
    }

    public function test_search_matches_vietnamese_meanings(): void
    {
        $item = LearningItem::factory()->create(['canonical_text' => 'achieve']);
        $this->meaning($item, 'đạt được mục tiêu');

        $this->getJson('/api/v1/dictionary?q='.urlencode('đạt được'))->assertJsonPath('data.0.term', 'achieve');
    }

    #[DataProvider('lookupTypes')]
    public function test_search_filters_each_supported_content_type(string $type): void
    {
        LearningItem::factory()->ofType($type)->create(['canonical_text' => 'target example', 'slug' => 'target-'.$type]);
        LearningItem::factory()->ofType('question')->create(['canonical_text' => 'target question']);

        $this->getJson('/api/v1/dictionary?q=target&type='.$type)->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', $type);
    }

    public static function lookupTypes(): array
    {
        return array_map(fn (string $type): array => [$type], ['vocabulary', 'collocation', 'phrasal_verb', 'idiom', 'grammar']);
    }

    public function test_drafts_future_publications_and_questions_are_hidden_from_public_search(): void
    {
        $this->freezeTime();
        LearningItem::factory()->draft()->create(['canonical_text' => 'hidden draft']);
        LearningItem::factory()->create(['canonical_text' => 'hidden future', 'published_at' => now()->addDay()]);
        LearningItem::factory()->ofType('question')->create(['canonical_text' => 'hidden question']);

        $this->getJson('/api/v1/dictionary?q=hidden')->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/dictionary/suggestions?q=hidden')->assertJsonCount(0, 'data');
    }

    public function test_guests_can_read_definitions_examples_and_related_grammar(): void
    {
        $item = LearningItem::factory()->create(['canonical_text' => 'achieve', 'slug' => 'achieve']);
        DB::table('dictionary_entries')->insert(['learning_item_id' => $item->id, 'phonetic' => '/əˈtʃiːv/', 'audio_url' => 'https://example.test/achieve.mp3']);
        $meaningId = $this->meaning($item, 'đạt được', 'verb');
        DB::table('learning_item_meaning_examples')->insert(['learning_item_meaning_id' => $meaningId, 'english' => 'She achieved her goal.', 'vietnamese' => 'Cô ấy đạt được mục tiêu.']);
        $this->seed(DictionaryGrammarSeeder::class);

        $response = $this->getJson('/api/v1/dictionary/entries/achieve');
        $response->assertOk()->assertJsonPath('data.term', 'achieve')
            ->assertJsonPath('data.phonetic', '/əˈtʃiːv/')
            ->assertJsonPath('data.audio_url', 'https://example.test/achieve.mp3')
            ->assertJsonPath('data.meanings.0.translation', 'đạt được')
            ->assertJsonPath('data.meanings.0.part_of_speech', 'verb')
            ->assertJsonPath('data.meanings.0.examples.0.english', 'She achieved her goal.')
            ->assertJsonCount(3, 'data.related_grammar');
        $this->get('/lookup/achieve')->assertSee('đạt được')->assertSee('She achieved her goal.')->assertSee('Ngữ pháp liên quan');
    }

    public function test_guests_can_open_grammar_with_formula_and_translated_examples(): void
    {
        $this->seed(DictionaryGrammarSeeder::class);

        $this->getJson('/api/v1/dictionary/entries/present-perfect')
            ->assertJsonPath('data.grammar.rules.0.formula', 'S + have / has + V3 (past participle)')
            ->assertJsonPath('data.grammar.rules.0.examples.0.english', 'She has achieved her goal.');
        $this->get('/lookup/present-perfect')->assertSee('S + have / has + V3')->assertSee('Cô ấy đã đạt được mục tiêu');
    }

    public function test_guests_can_browse_all_grammar_without_a_search_term(): void
    {
        $this->seed(DictionaryGrammarSeeder::class);

        $this->get('/lookup?type=grammar')->assertOk()->assertSee('10 bài ngữ pháp')
            ->assertSee('Present Perfect')->assertSee('Look forward to')->assertSee('Depend on');
    }

    public function test_grammar_suggestions_by_part_of_speech_do_not_claim_a_direct_link(): void
    {
        $item = LearningItem::factory()->create(['canonical_text' => 'improve', 'slug' => 'improve']);
        $this->meaning($item, 'cải thiện', 'verb');
        $this->seed(DictionaryGrammarSeeder::class);

        $this->getJson('/api/v1/dictionary/entries/improve')
            ->assertJsonCount(2, 'data.related_grammar')->assertJsonPath('data.related_grammar.0.relation_reason', 'part_of_speech');
    }

    public function test_related_items_include_both_directions_collocations_and_phrasal_verbs_but_hide_drafts(): void
    {
        $item = LearningItem::factory()->create(['canonical_text' => 'take', 'slug' => 'take']);
        $entryId = DB::table('dictionary_entries')->insertGetId(['learning_item_id' => $item->id]);
        $collocation = LearningItem::factory()->ofType('collocation')->create();
        $collocationId = DB::table('collocations')->insertGetId(['learning_item_id' => $collocation->id]);
        DB::table('collocation_dictionary_entries')->insert(['collocation_id' => $collocationId, 'dictionary_entry_id' => $entryId]);
        $phrasal = LearningItem::factory()->ofType('phrasal_verb')->create();
        DB::table('phrasal_verbs')->insert(['learning_item_id' => $phrasal->id, 'base_dictionary_entry_id' => $entryId, 'particle' => 'off']);
        $incoming = LearningItem::factory()->create();
        $draft = LearningItem::factory()->draft()->create();
        DB::table('learning_item_relations')->insert([
            ['source_learning_item_id' => $incoming->id, 'target_learning_item_id' => $item->id, 'relation_type' => 'synonym'],
            ['source_learning_item_id' => $item->id, 'target_learning_item_id' => $draft->id, 'relation_type' => 'synonym'],
        ]);

        $response = $this->getJson('/api/v1/dictionary/entries/take');
        $response->assertJsonCount(3, 'data.related_items');
        $expectedIds = [$incoming->id, $collocation->id, $phrasal->id];
        $actualIds = array_column($response->json('data.related_items'), 'id');
        sort($expectedIds);
        sort($actualIds);
        $this->assertSame($expectedIds, $actualIds);
    }

    public function test_draft_related_grammar_is_hidden(): void
    {
        $item = LearningItem::factory()->create(['slug' => 'word']);
        $grammar = LearningItem::factory()->ofType('grammar')->draft()->create();
        DB::table('learning_item_relations')->insert(['source_learning_item_id' => $item->id, 'target_learning_item_id' => $grammar->id, 'relation_type' => 'related_grammar']);

        $this->getJson('/api/v1/dictionary/entries/word')->assertJsonCount(0, 'data.related_grammar');
    }

    public function test_missing_and_unpublished_entries_return_404_for_api_and_html(): void
    {
        $this->freezeTime();
        LearningItem::factory()->draft()->create(['slug' => 'draft']);
        LearningItem::factory()->create(['slug' => 'future', 'published_at' => now()->addDay()]);
        LearningItem::factory()->ofType('question')->create(['slug' => 'question']);

        foreach (['missing', 'draft', 'future', 'question'] as $slug) {
            $this->getJson('/api/v1/dictionary/entries/'.$slug)->assertNotFound();
            $this->get('/lookup/'.$slug)->assertNotFound();
        }
    }

    public function test_suggestions_are_limited_to_eight_published_prefix_matches(): void
    {
        LearningItem::factory()->count(10)->sequence(fn ($sequence): array => [
            'canonical_text' => 'achieve '.$sequence->index,
            'slug' => 'achieve-'.$sequence->index,
        ])->create();
        LearningItem::factory()->draft()->create(['canonical_text' => 'achieve hidden']);
        LearningItem::factory()->create(['canonical_text' => 'to achieve']);

        $response = $this->getJson('/api/v1/dictionary/suggestions?q=achieve');
        $response->assertOk()->assertJsonCount(8, 'data');
        $this->assertSame('achieve 0', $response->json('data.0.term'));
    }

    public function test_search_has_stable_pagination_and_preserves_filters(): void
    {
        LearningItem::factory()->count(3)->sequence(fn ($sequence): array => ['canonical_text' => 'word '.$sequence->index])->create();

        $this->getJson('/api/v1/dictionary?q=word&type=vocabulary&per_page=2&page=2')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.term', 'word 2')
            ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.per_page', 2);
    }

    public function test_wildcard_and_sql_input_are_treated_as_literal_search_text(): void
    {
        LearningItem::factory()->create(['canonical_text' => 'ordinary']);
        LearningItem::factory()->create(['canonical_text' => '100%']);

        $this->getJson('/api/v1/dictionary?q=%25')->assertJsonCount(1, 'data')->assertJsonPath('data.0.term', '100%');
        $this->getJson('/api/v1/dictionary?q=_')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/dictionary?q='.urlencode("' OR 1=1 --"))->assertJsonCount(0, 'data');
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_search_input_returns_422_with_a_vietnamese_message(array $query, string $field, string $message): void
    {
        $this->getJson('/api/v1/dictionary?'.http_build_query($query))
            ->assertUnprocessable()->assertJsonValidationErrors($field)->assertJsonPath('errors.'.$field.'.0', $message);
    }

    public static function invalidQueries(): array
    {
        return [
            'missing query' => [[], 'q', 'Vui lòng nhập từ hoặc cụm từ cần tra cứu.'],
            'blank query' => [['q' => '   '], 'q', 'Vui lòng nhập từ hoặc cụm từ cần tra cứu.'],
            'array query' => [['q' => ['word']], 'q', 'Từ cần tra cứu phải là chuỗi văn bản.'],
            'long query' => [['q' => str_repeat('a', 101)], 'q', 'Từ cần tra cứu không được vượt quá 100 ký tự.'],
            'invalid filter' => [['q' => 'word', 'type' => 'question'], 'type', 'Loại nội dung tra cứu không hợp lệ.'],
            'array filter' => [['q' => 'word', 'type' => ['vocabulary']], 'type', 'Loại nội dung tra cứu phải là chuỗi văn bản.'],
            'fractional page' => [['q' => 'word', 'page' => '1.5'], 'page', 'Trang phải là số nguyên.'],
            'negative page' => [['q' => 'word', 'page' => 0], 'page', 'Trang phải từ 1 trở lên.'],
            'deep page' => [['q' => 'word', 'page' => 1001], 'page', 'Trang không được vượt quá 1000.'],
            'fractional page size' => [['q' => 'word', 'per_page' => '1.5'], 'per_page', 'Số kết quả mỗi trang phải là số nguyên.'],
            'zero page size' => [['q' => 'word', 'per_page' => 0], 'per_page', 'Số kết quả mỗi trang phải từ 1 trở lên.'],
            'large page size' => [['q' => 'word', 'per_page' => 51], 'per_page', 'Mỗi trang chỉ được hiển thị tối đa 50 kết quả.'],
        ];
    }

    public function test_search_renders_real_results_and_a_useful_empty_state(): void
    {
        LearningItem::factory()->create(['canonical_text' => 'beautiful', 'slug' => 'beautiful']);

        $this->get('/lookup?q=beautiful')->assertSee('1 kết quả')->assertSee('/lookup/beautiful');
        $this->get('/lookup?q=missingword')->assertSee('Chưa tìm thấy nội dung phù hợp');
    }

    public function test_search_opens_the_first_result_and_tabs_preserve_the_search_context(): void
    {
        $item = LearningItem::factory()->create(['canonical_text' => 'achieve', 'slug' => 'achieve']);
        LearningItem::factory()->create(['canonical_text' => 'achievement', 'slug' => 'achievement']);
        $meaningId = $this->meaning($item, 'đạt được mục tiêu', 'verb');
        DB::table('learning_item_meaning_examples')->insert(['learning_item_meaning_id' => $meaningId, 'english' => 'They achieved their goal.', 'vietnamese' => 'Họ đạt được mục tiêu.']);

        $this->get('/lookup?q=achieve&type=vocabulary')->assertOk()
            ->assertSee('đạt được mục tiêu')->assertSee('dictionary-workspace', false)
            ->assertSee('q=achieve&amp;type=vocabulary&amp;tab=examples', false);
        $this->get('/lookup/achieve?q=achieve&type=vocabulary&tab=examples')->assertOk()
            ->assertSee('Ví dụ câu')->assertSee('They achieved their goal.')->assertSee('Họ đạt được mục tiêu.');
        $this->get('/lookup/achievement?q=achieve&type=vocabulary&tab=collocations')->assertOk()
            ->assertSee('Collocation thường gặp')->assertSee('Chưa có collocation');
    }

    public function test_synonym_and_antonym_tabs_use_the_correct_relations_in_both_directions(): void
    {
        $item = LearningItem::factory()->create(['canonical_text' => 'achieve', 'slug' => 'achieve']);
        $synonym = LearningItem::factory()->create(['canonical_text' => 'accomplish', 'slug' => 'accomplish']);
        $antonym = LearningItem::factory()->create(['canonical_text' => 'fail', 'slug' => 'fail']);
        DB::table('learning_item_relations')->insert([
            ['source_learning_item_id' => $item->id, 'target_learning_item_id' => $synonym->id, 'relation_type' => 'synonym'],
            ['source_learning_item_id' => $antonym->id, 'target_learning_item_id' => $item->id, 'relation_type' => 'antonym'],
        ]);

        $this->get('/lookup/achieve?tab=synonyms')->assertOk()->assertSee('<b>accomplish<small>', false)->assertDontSee('<b>fail<small>', false);
        $this->get('/lookup/achieve?tab=antonyms')->assertOk()->assertSee('<b>fail<small>', false)->assertDontSee('<b>accomplish<small>', false);
        $this->get('/lookup/achieve?tab=unknown')->assertRedirect()->assertSessionHasErrors('tab');
    }

    public function test_direct_entry_pagination_keeps_the_term_and_returns_to_search(): void
    {
        LearningItem::factory()->create(['canonical_text' => 'word', 'slug' => 'word']);
        LearningItem::factory()->count(13)->sequence(fn ($sequence): array => ['canonical_text' => 'word '.$sequence->index])->create();

        $this->get('/lookup/word')->assertOk()->assertViewHas('results', function ($results): bool {
            $url = parse_url($results->nextPageUrl());
            parse_str($url['query'], $query);

            return $url['path'] === '/lookup' && $query === ['q' => 'word', 'type' => 'all', 'page' => '2'];
        });
    }

    public function test_html_validation_errors_render_safely_after_an_array_query(): void
    {
        $this->from('/lookup')->followingRedirects()->get('/lookup?q[0]=word')
            ->assertOk()->assertSee('Từ cần tra cứu phải là chuỗi văn bản.');
    }

    public function test_search_and_entry_escape_imported_content(): void
    {
        $item = LearningItem::factory()->create(['canonical_text' => '<script>alert(1)</script>', 'slug' => 'unsafe-word']);
        $this->meaning($item, '<img src=x onerror=alert(1)>');

        $this->get('/lookup?q=script')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/lookup/unsafe-word')->assertSee('&lt;img', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_unsafe_audio_urls_are_not_exposed(): void
    {
        $item = LearningItem::factory()->create(['slug' => 'unsafe-audio']);
        DB::table('dictionary_entries')->insert(['learning_item_id' => $item->id, 'audio_url' => 'javascript:alert(1)']);

        $this->getJson('/api/v1/dictionary/entries/unsafe-audio')->assertJsonPath('data.audio_url', null);
    }

    public function test_authenticated_users_can_also_search(): void
    {
        $user = User::factory()->create();
        LearningItem::factory()->create(['canonical_text' => 'word']);

        $this->actingAs($user)->getJson('/api/v1/dictionary?q=word')->assertJsonCount(1, 'data');
    }

    public function test_legacy_lookup_links_redirect_to_real_search_and_keep_the_query(): void
    {
        $this->get('/demo/lookup?q=beautiful')->assertRedirect('/lookup?q=beautiful');
        $this->get('/demo/lookup-overview')->assertRedirect('/lookup?q=achieve');
    }

    public function test_public_api_returns_429_after_the_rate_limit(): void
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->getJson('/api/v1/dictionary?q=word')->assertOk();
        }

        $this->getJson('/api/v1/dictionary?q=word')->assertTooManyRequests()->assertHeader('Retry-After');
    }

    private function meaning(LearningItem $item, string $translation, ?string $partOfSpeech = null): int
    {
        $posId = null;
        if ($partOfSpeech !== null) {
            DB::table('parts_of_speech')->insertOrIgnore(['code' => $partOfSpeech, 'label' => $partOfSpeech]);
            $posId = DB::table('parts_of_speech')->where('code', $partOfSpeech)->value('id');
        }

        return DB::table('learning_item_meanings')->insertGetId([
            'learning_item_id' => $item->id, 'sense_number' => 1, 'translation' => $translation, 'part_of_speech_id' => $posId,
        ]);
    }
}
