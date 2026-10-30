<?php

namespace Tests\Admin\Feature\KnowledgeBase;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Admin\TestCase;
use VistiqCore\Models\LearningItem;

class KnowledgeAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_admin_can_view_knowledge_index(): void
    {
        $admin = $this->admin('super-admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.knowledge.index'));

        $response->assertOk()
            ->assertSee('Kho kiến thức')
            ->assertSee('Nhập CSV')
            ->assertSee('Xuất CSV');
    }

    public function test_admin_can_filter_knowledge_by_status(): void
    {
        $admin = $this->admin('super-admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.knowledge.index', ['status' => 'draft']));

        $response->assertOk()
            ->assertSee('Bản nháp');
    }

    public function test_admin_can_create_draft_knowledge_item(): void
    {
        $admin = $this->admin('super-admin');

        $response = $this->actingAs($admin)
            ->post(route('admin.knowledge.store'), [
                'type' => 'vocabulary',
                'english' => 'serendipity',
                'meaning_vi' => 'sự may mắn tình cờ',
                'definition_en' => 'finding valuable things not sought for',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('learning_items', [
            'canonical_text' => 'serendipity',
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_delete_draft_knowledge_item(): void
    {
        $admin = $this->admin('super-admin');

        $item = LearningItem::create([
            'learning_item_type_id' => 1,
            'canonical_text' => 'test draft item',
            'slug' => 'test-draft-item',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.knowledge.destroy', $item));

        $response->assertRedirect(route('admin.knowledge.index'))
            ->assertSessionHas('status', 'Đã xóa bản nháp kiến thức.');

        $this->assertDatabaseMissing('learning_items', ['id' => $item->id]);
    }

    public function test_admin_cannot_delete_published_knowledge_item(): void
    {
        $admin = $this->admin('super-admin');

        $item = LearningItem::create([
            'learning_item_type_id' => 1,
            'canonical_text' => 'published item',
            'slug' => 'published-item',
            'status' => 'published',
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.knowledge.destroy', $item));

        $response->assertStatus(403);
        $this->assertDatabaseHas('learning_items', ['id' => $item->id]);
    }

    public function test_admin_can_export_knowledge_to_csv(): void
    {
        $admin = $this->admin('super-admin');

        LearningItem::create([
            'learning_item_type_id' => 1,
            'canonical_text' => 'exportable word',
            'slug' => 'exportable-word',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.knowledge.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('exportable word');
    }

    public function test_admin_can_preview_and_confirm_import_csv(): void
    {
        $admin = $this->admin('super-admin');

        $csvContent = "english,meaning_vi,definition_en\nresilient,kiên cường,able to withstand difficulty\n";
        $file = UploadedFile::fake()->createWithContent('words.csv', $csvContent);

        $previewResponse = $this->actingAs($admin)
            ->post(route('admin.knowledge.import.preview'), [
                'type' => 'vocabulary',
                'file' => $file,
                'duplicate_policy' => 'skip',
            ]);

        $previewResponse->assertOk()
            ->assertSee('resilient')
            ->assertSee('kiên cường');

        $confirmResponse = $this->actingAs($admin)
            ->post(route('admin.knowledge.import.confirm'), [
                'type' => 'vocabulary',
                'duplicate_policy' => 'skip',
                'preview_token' => $previewResponse->viewData('previewToken'),
                'rows_json' => json_encode([
                    ['english' => 'resilient', 'meaning_vi' => 'kiên cường'],
                ]),
            ]);

        $confirmResponse->assertRedirect(route('admin.knowledge.index', ['type' => 'vocabulary']));
        $this->assertDatabaseHas('learning_items', [
            'canonical_text' => 'resilient',
            'status' => 'draft',
        ]);
    }
}
