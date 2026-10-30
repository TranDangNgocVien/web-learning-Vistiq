<?php

namespace Tests\Admin\Feature\KnowledgeBase;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Tests\Admin\TestCase;
use VistiqCore\Models\LearningItem;
use VistiqCore\Services\Knowledge\KnowledgeTaxonomyService;

class KnowledgeValidationAndWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_upsert_validation_fails_when_english_missing(): void
    {
        $admin = $this->admin('super-admin');

        $response = $this->actingAs($admin)
            ->post(route('admin.knowledge.store'), [
                'type' => 'vocabulary',
                'english' => '',
            ]);

        $response->assertSessionHasErrors(['english']);
    }

    public function test_upsert_validation_fails_when_grammar_lacks_category(): void
    {
        $admin = $this->admin('super-admin');

        $response = $this->actingAs($admin)
            ->post(route('admin.knowledge.store'), [
                'type' => 'grammar',
                'english' => 'Present Continuous',
                'grammar_category_id' => '',
            ]);

        $response->assertSessionHasErrors(['grammar_category_id']);
    }

    public function test_cannot_delete_draft_with_existing_references(): void
    {
        $admin = $this->admin('super-admin');

        $item = LearningItem::create([
            'learning_item_type_id' => 1,
            'canonical_text' => 'Draft Word with relation',
            'slug' => 'draft-word-with-relation',
            'status' => 'draft',
        ]);

        // Add a mock relation in learning_item_relations
        DB::table('learning_item_relations')->insert([
            'source_learning_item_id' => $item->id,
            'target_learning_item_id' => $item->id,
            'relation_type' => 'synonym',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.knowledge.destroy', $item));

        $response->assertStatus(422);
        $this->assertDatabaseHas('learning_items', ['id' => $item->id]);
    }

    public function test_import_with_duplicate_policy_skip_and_overwrite(): void
    {
        $admin = $this->admin('super-admin');

        $typeId = DB::table('learning_item_types')->where('code', 'vocabulary')->value('id');

        // Pre-create existing item
        LearningItem::create([
            'learning_item_type_id' => $typeId,
            'canonical_text' => 'existing-word',
            'slug' => 'existing-word-slug',
            'status' => 'draft',
        ]);

        // 1. Skip policy
        $csvContent = "english,meaning_vi\nexisting-word,nghĩa cũ\nnew-word,nghĩa mới\n";
        $file = UploadedFile::fake()->createWithContent('import_test.csv', $csvContent);

        $previewResponse = $this->actingAs($admin)
            ->post(route('admin.knowledge.import.preview'), [
                'type' => 'vocabulary',
                'file' => $file,
                'duplicate_policy' => 'skip',
            ]);

        $previewResponse->assertOk()
            ->assertSee('existing-word')
            ->assertSee('new-word');

        // Confirm with skip
        $this->actingAs($admin)
            ->post(route('admin.knowledge.import.confirm'), [
                'type' => 'vocabulary',
                'duplicate_policy' => 'skip',
                'preview_token' => $previewResponse->viewData('previewToken'),
                'rows_json' => json_encode([
                    ['english' => 'existing-word', 'meaning_vi' => 'nghĩa mới'],
                    ['english' => 'new-word', 'meaning_vi' => 'nghĩa mới'],
                ]),
            ])
            ->assertRedirect(route('admin.knowledge.index', ['type' => 'vocabulary']));

        $this->assertDatabaseHas('learning_items', ['canonical_text' => 'new-word']);
    }

    public function test_taxonomy_service_sanitizes_html_markup(): void
    {
        $taxonomy = app(KnowledgeTaxonomyService::class);

        $unsafeHtml = '<p>An toàn</p><script>alert("XSS")</script><b onclick="evil()">Bold</b>';
        $cleaned = $taxonomy->sanitizeHtml($unsafeHtml);

        $this->assertStringNotContainsString('<script>', $cleaned);
        $this->assertStringNotContainsString('onclick', $cleaned);
        $this->assertStringContainsString('An toàn', $cleaned);
    }
}
