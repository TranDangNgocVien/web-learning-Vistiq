<?php

namespace Tests\Learner\Feature\KnowledgeBase;

use Database\Learner\Seeders\DictionaryGrammarSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Learner\TestCase;
use VistiqLearner\Models\LearningItem;

class DictionaryGrammarSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_grammar_seeding_can_repeat_without_duplicates_or_changing_vocabulary(): void
    {
        $word = LearningItem::factory()->create(['canonical_text' => 'achieve', 'slug' => 'achieve']);

        $this->seed(DictionaryGrammarSeeder::class);
        $this->seed(DictionaryGrammarSeeder::class);

        $this->assertDatabaseCount('grammar_topics', 10);
        $this->assertDatabaseCount('grammar_rules', 10);
        $this->assertDatabaseCount('grammar_examples', 10);
        $this->assertDatabaseCount('learning_item_relations', 3);
        $this->assertDatabaseHas('learning_items', ['id' => $word->id, 'canonical_text' => 'achieve', 'slug' => 'achieve']);
        $this->getJson('/api/v1/dictionary?q=hiện+tại+hoàn+thành&type=grammar')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'present-perfect');
    }
}
