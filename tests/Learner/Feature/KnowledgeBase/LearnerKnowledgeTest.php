<?php

namespace Tests\Learner\Feature\KnowledgeBase;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Database\Admin\Seeders\DictionaryGrammarSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Tests\Learner\TestCase;
use VistiqCore\Models\LearningItem;
use VistiqLearner\Models\User;

class LearnerKnowledgeTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->seed(DictionaryGrammarSeeder::class);
    }

    public function test_learner_can_view_grammar_index(): void
    {
        $response = $this->get(route('grammar.index'));

        $response->assertOk()
            ->assertSee('Thư viện ngữ pháp');
    }

    public function test_learner_sees_only_published_grammar_items(): void
    {
        $grammarTypeId = DB::table('learning_item_types')->where('code', 'grammar')->value('id');

        // Create a draft item
        $draftItem = LearningItem::create([
            'learning_item_type_id' => $grammarTypeId,
            'canonical_text' => 'Draft Grammar Topic Secret',
            'slug' => 'draft-grammar-secret',
            'status' => 'draft',
        ]);

        $response = $this->get(route('grammar.index'));

        $response->assertOk()
            ->assertDontSee('Draft Grammar Topic Secret');
    }

    public function test_learner_can_search_grammar_by_keyword(): void
    {
        $response = $this->get(route('grammar.index', ['q' => 'present-perfect']));

        $response->assertOk();
    }

    public function test_learner_can_filter_grammar_by_level(): void
    {
        $response = $this->get(route('grammar.index', ['level' => 'B1']));

        $response->assertOk();
    }

    public function test_learner_can_view_published_grammar_detail(): void
    {
        $response = $this->get(route('grammar.show', 'present-perfect'));

        $response->assertOk()
            ->assertSee('Present Perfect');
    }

    public function test_draft_grammar_detail_returns_404(): void
    {
        $grammarTypeId = DB::table('learning_item_types')->where('code', 'grammar')->value('id');

        LearningItem::create([
            'learning_item_type_id' => $grammarTypeId,
            'canonical_text' => 'Unpublished Hidden Topic',
            'slug' => 'unpublished-hidden-topic',
            'status' => 'draft',
        ]);

        $response = $this->get(route('grammar.show', 'unpublished-hidden-topic'));

        $response->assertNotFound();
    }

    public function test_learner_can_view_knowledge_index(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('knowledge.index'));

        $response->assertOk()
            ->assertSee('Kho kiến thức');
    }
}
