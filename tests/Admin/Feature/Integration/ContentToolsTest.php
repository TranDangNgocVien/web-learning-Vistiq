<?php

namespace Tests\Admin\Feature\Integration;

use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Services\Assessment\Attempts\AttemptService;

class ContentToolsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin());
    }

    public function test_complex_csv_export_import_preserves_mapping_and_blank_answers(): void
    {
        $part = DB::table('exam_parts')->where('code', 'reading-part-1')->value('id');
        $type = DB::table('question_types')->where('code', 'matching_headings')->value('id');
        $difficulty = DB::table('question_difficulties')->value('id');
        $payload = ['prompt' => 'Match headings', 'options' => ['i' => 'One', 'ii' => 'Two'], 'correct_options' => [], 'accepted_answers' => [], 'metadata' => ['answer_map' => ['1' => 'ii'], 'labels' => ['1' => 'Paragraph A']]];
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['exam_part', 'prompt', 'question_type', 'payload_json'], escape: '');
        fputcsv($stream, [$part, 'Match headings', 'matching_headings', json_encode($payload)], escape: '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        $this->post('/admin/questions/import', ['file' => UploadedFile::fake()->createWithContent('complex.csv', $csv), 'default_question_type' => $type, 'default_difficulty' => $difficulty, 'default_status' => 'draft', 'offset' => 0])->assertSessionHas('import_summary', ['total' => 1, 'imported' => 1, 'failed' => 0]);
        $revision = ContentRevision::first();
        $this->assertSame(['1' => 'ii'], $revision->payload['metadata']['answer_map']);
        $export = $this->get('/admin/questions/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('payload_json', $export);
        $this->assertStringContainsString('Paragraph A', $export);
    }

    public function test_option_ids_used_by_legacy_attempt_are_preserved(): void
    {
        $question = QuestionBankItemFactory::new()->create();
        $option = $question->options()->create(['option_key' => 'A', 'content' => 'Old', 'is_correct' => true]);
        $attempt = app(AttemptService::class)->start($this->publishedExam(), User::factory()->create());
        $answer = $attempt->answers()->create(['item_key' => 'legacy', 'response' => []]);
        DB::table('attempt_answer_options')->insert(['attempt_answer_id' => $answer->id, 'question_option_id' => $option->id]);
        $this->put('/admin/questions/'.$question->id, ['exam_part_id' => $question->exam_part_id, 'question_type_id' => $question->question_type_id, 'question_difficulty_id' => $question->question_difficulty_id, 'prompt' => 'Edited', 'options' => ['A' => 'New', 'B' => 'Other'], 'correct_option' => 'B', 'revision_counter' => 0])->assertRedirect();
        $this->assertDatabaseHas('question_options', ['id' => $option->id, 'content' => 'Old', 'is_correct' => 1]);
        $this->assertDatabaseHas('attempt_answer_options', ['question_option_id' => $option->id]);
        $this->assertSame('New', ContentRevision::first()->payload['options']['A']);
    }

    public function test_groups_can_be_edited_deleted_and_referenced_drafts_cannot_be_deleted(): void
    {
        $part = DB::table('exam_parts')->where('code', 'part-5')->value('id');
        $this->post('/admin/groups', ['title' => 'Set', 'kind' => 'question_group', 'exam_part_id' => $part])->assertRedirect();
        $id = DB::table('question_groups')->value('id');
        $this->put('/admin/groups/'.$id, ['title' => 'Updated', 'kind' => 'question_group', 'exam_part_id' => $part])->assertRedirect();
        $this->assertDatabaseHas('question_groups', ['id' => $id, 'title' => 'Updated']);
        $this->delete('/admin/groups/'.$id)->assertRedirect();
        $this->assertDatabaseMissing('question_groups', ['id' => $id]);
        $question = QuestionBankItemFactory::new()->create();
        $exam = $this->publishedExam();
        $exam->update(['draft_payload' => ['sections' => [['question_ids' => [$question->id]]]]]);
        $this->deleteJson('/admin/content/question/'.$question->id)->assertConflict();
    }

    public function test_groups_page_renders_the_workbench_layout(): void
    {
        $part = DB::table('exam_parts')->where('code', 'part-5')->value('id');
        $this->post('/admin/groups', ['title' => 'Workbench sample', 'kind' => 'conversation', 'exam_part_id' => $part])->assertRedirect();

        $this->get('/admin/groups')
            ->assertOk()
            ->assertSee('Tạo nhóm câu hỏi')
            ->assertSee('Nhóm câu hỏi')
            ->assertDontSee('Nhóm câu hỏi, <em>một nhịp nghe.</em>', false)
            ->assertSee('question-group-card', false)
            ->assertSee('data-resource-picker', false)
            ->assertSee('data-resource-search', false)
            ->assertSee('data-resource-select', false)
            ->assertSee('Chọn một dòng để xem hoặc chỉnh sửa.');
    }

    public function test_admin_shell_uses_an_independent_scroll_frame(): void
    {
        $this->get('/admin/audio')
            ->assertOk()
            ->assertSee('<html lang="vi" class="admin-document">', false)
            ->assertSee('<main class="page-wrap admin-page">', false);
    }

    public static function knowledgeTypes(): array
    {
        return [['vocabulary', 'dictionary_entries'], ['collocation', 'collocations'], ['idiom', 'idioms'], ['grammar', 'grammar_topics']];
    }

    #[DataProvider('knowledgeTypes')]
    public function test_knowledge_publication_materializes_shared_content(string $type, string $table): void
    {
        $this->post('/admin/knowledge', ['type' => $type, 'english' => 'Knowledge sample', 'meaning_vi' => 'Nghĩa mẫu', 'definition_en' => 'Definition', 'ipa' => '/test/', 'grammatical_pattern' => 'verb + noun', 'register' => 'neutral', 'content' => '<p>Grammar content</p>', 'grammar_category_id' => DB::table('grammar_categories')->value('id'), 'examples' => [['english' => 'An example.', 'vietnamese' => 'Ví dụ.']]])->assertRedirect();
        $id = DB::table('learning_items')->where('canonical_text', 'Knowledge sample')->value('id');
        foreach (['review', 'approve', 'publish'] as $action) {
            $this->post('/admin/content/knowledge/'.$id.'/'.$action)->assertRedirect();
        }
        $this->assertDatabaseHas($table, ['learning_item_id' => $id]);
        $this->assertDatabaseHas('learning_item_meanings', ['learning_item_id' => $id, 'translation' => 'Nghĩa mẫu']);
        $this->assertDatabaseHas('learning_item_meaning_examples', ['english' => 'An example.']);
    }

    public function test_publishing_rejects_policies_with_unknown_keys_and_string_booleans(): void
    {
        $this->postJson('/admin/exams', ['title' => 'Bad', 'exam_type_id' => DB::table('exam_types')->value('id'), 'mode' => 'mini', 'sections' => [], 'policies' => ['duration_minutes' => 30, 'pause_allowed' => 'false', 'unknown' => true]])->assertUnprocessable();
    }
}
