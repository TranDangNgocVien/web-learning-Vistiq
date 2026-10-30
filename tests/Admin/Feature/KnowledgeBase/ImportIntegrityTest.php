<?php

namespace Tests\Admin\Feature\KnowledgeBase;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Validation\ValidationException;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Models\LearningItem;
use VistiqCore\Services\Knowledge\KnowledgeImportService;

class ImportIntegrityTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin());
    }

    public function test_confirmation_requires_a_server_preview_and_never_trusts_client_rows(): void
    {
        $this->postJson(route('admin.knowledge.import.confirm'), ['type' => 'vocabulary', 'duplicate_policy' => 'skip', 'rows_json' => json_encode([['english' => 'Forged', 'meaning_vi' => 'Giả']])])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('learning_items', ['canonical_text' => 'Forged']);
    }

    public function test_preview_handles_multiline_csv_and_validates_every_row(): void
    {
        $service = app(KnowledgeImportService::class);
        $preview = $service->preview(UploadedFile::fake()->createWithContent('multiline.csv', "english,meaning_vi\nword,\"nghĩa dòng một\nnghĩa dòng hai\"\ninvalid,\n"), 'vocabulary');
        $this->assertCount(1, $preview['errors']);
        $this->assertStringContainsString("\n", $preview['rows'][0]['meaning_vi']);
        $this->assertSame(3, $preview['errors'][0]['row']);
    }

    public function test_invalid_row_rolls_back_the_whole_import(): void
    {
        try {
            app(KnowledgeImportService::class)->import([['english' => 'Valid first row', 'meaning_vi' => 'Hợp lệ'], ['english' => 'Missing meaning']], 'vocabulary');
            $this->fail('Invalid rows must reject the batch.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertDatabaseMissing('learning_items', ['canonical_text' => 'Valid first row']);
    }

    public function test_saved_draft_cannot_be_hard_deleted(): void
    {
        $item = LearningItem::create(['learning_item_type_id' => DB::table('learning_item_types')->where('code', 'idiom')->value('id'), 'canonical_text' => 'Saved draft', 'slug' => 'saved-draft', 'status' => 'draft']);
        DB::table('user_saved_items')->insert(['user_id' => User::factory()->create()->id, 'learning_item_id' => $item->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->delete(route('admin.knowledge.destroy', $item))->assertUnprocessable();
        $this->assertDatabaseHas('learning_items', ['id' => $item->id]);
    }

    public function test_confirmation_uses_the_preview_once_and_ignores_forged_rows(): void
    {
        $response = $this->post(route('admin.knowledge.import.preview'), ['type' => 'vocabulary', 'file' => UploadedFile::fake()->createWithContent('words.csv', "english,meaning_vi\nReal preview,Thật\n")])->assertOk();
        $data = ['preview_token' => $response->viewData('previewToken'), 'rows_json' => json_encode([['english' => 'Forged', 'meaning_vi' => 'Giả']])];
        $this->post(route('admin.knowledge.import.confirm'), $data)->assertRedirect();
        $this->postJson(route('admin.knowledge.import.confirm'), $data)->assertUnprocessable();
        $this->assertDatabaseHas('learning_items', ['canonical_text' => 'Real preview']);
        $this->assertDatabaseMissing('learning_items', ['canonical_text' => 'Forged']);
    }
}
