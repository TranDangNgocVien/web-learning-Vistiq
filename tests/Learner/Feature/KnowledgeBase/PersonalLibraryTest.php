<?php

namespace Tests\Learner\Feature\KnowledgeBase;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Tests\Learner\TestCase;
use VistiqCore\Models\LearningItem;
use VistiqLearner\Models\User;

class PersonalLibraryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lookup_saving_library_filters_collections_and_owner_boundaries(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $type = DB::table('learning_item_types')->where('code', 'vocabulary')->value('id');
        $item = LearningItem::create(['learning_item_type_id' => $type, 'canonical_text' => 'commitment', 'slug' => 'commitment', 'status' => 'published']);
        DB::table('learning_item_meanings')->insert(['learning_item_id' => $item->id, 'sense_number' => 1, 'translation' => 'sự cam kết']);
        $this->get('/knowledge')->assertRedirect('/login');
        $this->actingAs($owner)->get('/lookup/commitment')->assertOk()->assertSee('Lưu vào kho kiến thức');
        $this->get('/knowledge')->assertOk()->assertSee('Kho kiến thức của bạn đang trống')->assertDontSee('sự cam kết');
        $this->post('/knowledge/commitment/save')->assertRedirect();
        $this->post('/knowledge/collections', ['name' => 'TOEIC Part 5'])->assertRedirect();
        $collection = DB::table('user_knowledge_collections')->where('user_id', $owner->id)->value('id');
        $this->post('/knowledge/commitment/save', ['collection_id' => $collection, 'personal_note' => 'Owner private note'])->assertRedirect();
        $this->get('/knowledge?type=vocabulary&collection='.$collection)->assertOk()->assertSee('sự cam kết')->assertSee('TOEIC Part 5')->assertSee('Owner private note');
        $this->get('/knowledge?type=grammar')->assertOk()->assertSee('Chưa có mục phù hợp');
        $this->actingAs($other)->get('/knowledge')->assertOk()->assertDontSee('Owner private note')->assertDontSee('TOEIC Part 5');
        $this->get('/knowledge?item=commitment')->assertNotFound();
        $this->postJson('/knowledge/commitment/save', ['collection_id' => $collection])->assertUnprocessable()->assertJsonValidationErrors('collection_id');
        $this->assertDatabaseMissing('user_saved_items', ['user_id' => $other->id, 'learning_item_id' => $item->id]);
        $this->actingAs($owner)->postJson('/knowledge/commitment/save', ['personal_note' => ''])->assertOk()->assertJsonPath('saved', true);
        $this->assertDatabaseHas('user_saved_items', ['user_id' => $owner->id, 'learning_item_id' => $item->id, 'personal_note' => null]);
        $this->delete('/knowledge/commitment/save')->assertRedirect();
        $this->get('/knowledge')->assertOk()->assertSee('Kho kiến thức của bạn đang trống');
    }
}
