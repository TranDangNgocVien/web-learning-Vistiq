<?php

namespace Tests\Learner\Feature\KnowledgeBase;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Database\Admin\Seeders\DictionaryGrammarSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Tests\Learner\TestCase;
use VistiqCore\Models\LearningItem;

class LearnerKnowledgeWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->seed(DictionaryGrammarSeeder::class);
    }

    public function test_archived_grammar_items_are_hidden_from_learner_index(): void
    {
        $grammarTypeId = DB::table('learning_item_types')->where('code', 'grammar')->value('id');

        LearningItem::create([
            'learning_item_type_id' => $grammarTypeId,
            'canonical_text' => 'Archived Secret Grammar',
            'slug' => 'archived-secret-grammar',
            'status' => 'archived',
        ]);

        $response = $this->get(route('grammar.index'));

        $response->assertOk()
            ->assertDontSee('Archived Secret Grammar');
    }

    public function test_archived_grammar_detail_returns_404(): void
    {
        $grammarTypeId = DB::table('learning_item_types')->where('code', 'grammar')->value('id');

        LearningItem::create([
            'learning_item_type_id' => $grammarTypeId,
            'canonical_text' => 'Archived Direct Access',
            'slug' => 'archived-direct-access',
            'status' => 'archived',
        ]);

        $response = $this->get(route('grammar.show', 'archived-direct-access'));

        $response->assertNotFound();
    }

    public function test_learner_grammar_search_filters_correctly(): void
    {
        $response = $this->get(route('grammar.index', ['q' => 'achieve']));

        $response->assertOk();
    }
}
