<?php

namespace Tests\Learner\Feature\KnowledgeBase;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Tests\Learner\TestCase;
use VistiqCore\Models\LearningItem;
use VistiqLearner\Models\User;

class PublishedKnowledgeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_catalog_and_detail_render_published_records_instead_of_demo_content(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $item = LearningItem::create(['learning_item_type_id' => DB::table('learning_item_types')->where('code', 'idiom')->value('id'), 'canonical_text' => 'Visible real knowledge', 'slug' => 'visible-real-knowledge', 'status' => 'published']);
        DB::table('learning_item_meanings')->insert(['learning_item_id' => $item->id, 'sense_number' => 1, 'translation' => 'Nội dung thật', 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/knowledge?q=Visible')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/knowledge?q=Visible')->assertOk()->assertDontSee('Visible real knowledge')->assertSee('Kho kiến thức của bạn đang trống');
        $this->get('/knowledge/visible-real-knowledge')->assertOk()->assertSee('Nội dung thật')->assertDontSee('take into account');
        $this->post('/knowledge/visible-real-knowledge/save')->assertRedirect();
        $this->get('/knowledge/saved')->assertOk()->assertSee('Visible real knowledge');
    }

    public function test_saved_items_notes_and_mutations_are_scoped_to_the_authenticated_learner(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $item = LearningItem::create(['learning_item_type_id' => DB::table('learning_item_types')->where('code', 'idiom')->value('id'), 'canonical_text' => 'Shared published item', 'slug' => 'shared-published-item', 'status' => 'published']);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->post('/knowledge/shared-published-item/save', ['personal_note' => 'Private owner note', 'user_id' => $other->id])->assertRedirect();
        $this->assertDatabaseHas('user_saved_items', ['user_id' => $owner->id, 'learning_item_id' => $item->id]);
        $this->actingAs($other)->get('/knowledge/saved')->assertOk()->assertDontSee('Private owner note');
        $this->delete('/knowledge/shared-published-item/save')->assertRedirect();
        $this->assertDatabaseHas('user_saved_items', ['user_id' => $owner->id, 'learning_item_id' => $item->id]);
    }
}
