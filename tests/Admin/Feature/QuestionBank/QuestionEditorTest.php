<?php

namespace Tests\Admin\Feature\QuestionBank;

use Database\Admin\Factories\ExamTemplateFactory;
use Database\Admin\Factories\LearningItemFactory;
use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Tests\Admin\TestCase;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;

class QuestionEditorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin('content-admin'));
    }

    private function fields(array $extra = []): array
    {
        return array_replace([
            'exam_part_id' => DB::table('exam_parts')->where('code', 'part-5')->value('id'),
            'question_type_id' => DB::table('question_types')->where('code', 'multiple_choice')->value('id'),
            'question_difficulty_id' => DB::table('question_difficulties')->where('code', 'medium')->value('id'),
            'prompt' => '<p>The company plans to _____.</p>',
            'options_values' => ['A' => 'expand', 'B' => 'expands', 'C' => 'expanding', 'D' => 'to expand'],
            'correct_options' => ['A'], 'explanation' => 'Use the infinitive.', 'tags' => 'Grammar',
        ], $extra);
    }

    public function test_preview_uses_unsaved_input_and_does_not_create_revisions(): void
    {
        $response = $this->postJson('/admin/questions/preview', $this->fields(['prompt' => '<p>New preview</p><script>alert(1)</script>']));
        $response->assertOk()->assertJsonPath('warnings', []);
        $this->assertStringContainsString('New preview', $response->json('html'));
        $this->assertStringNotContainsString('<script>', $response->json('html'));
        $this->assertStringContainsString('is-correct', $response->json('html'));
        $this->assertDatabaseCount('content_revisions', 0);
        $this->assertDatabaseCount('question_bank_items', 0);
    }

    public function test_save_persists_knowledge_resources_answers_and_real_history(): void
    {
        $question = QuestionBankItemFactory::new()->create();
        $knowledge = LearningItemFactory::new()->ofType('grammar')->create();
        $asset = MediaAsset::create(['title' => 'Audio test', 'disk' => 'local', 'path' => 'test.mp3', 'mime_type' => 'audio/mpeg', 'size' => 100]);
        $fields = $this->fields(['metadata' => ['knowledge_ids' => [$knowledge->id], 'audio_id' => $asset->id], 'revision_counter' => 0]);
        $this->putJson('/admin/questions/'.$question->id, $fields)->assertOk()->assertJsonPath('revision_counter', 1)->assertJsonPath('history.items.0.actor', auth()->user()->name);
        $revision = ContentRevision::firstOrFail();
        $this->assertSame([$knowledge->id], $revision->payload['metadata']['knowledge_ids']);
        $this->assertSame($asset->id, $revision->payload['metadata']['audio_id']);
        $this->assertSame(['A'], $revision->payload['correct_options']);
        $this->assertSame('Grammar', $revision->payload['tags']);
        $this->putJson('/admin/questions/'.$question->id, array_replace($fields, ['revision_counter' => 1, 'explanation' => 'New explanation']))->assertOk()->assertJsonPath('revision_counter', 2);
        $this->getJson('/admin/questions/'.$question->id.'/history')->assertOk()->assertJsonPath('total', 2)->assertJsonPath('items.0.summary', 'Cập nhật giải thích');
        $this->get('/admin/questions/'.$question->id.'/edit')->assertOk()->assertSee($knowledge->canonical_text)->assertSee('Audio test')->assertSee('data-qe-root', false);
        $this->putJson('/admin/questions/'.$question->id, $fields)->assertStatus(409);
    }

    public function test_catalog_search_returns_real_records_and_validates_resource_types(): void
    {
        $knowledge = LearningItemFactory::new()->ofType('grammar')->create(['canonical_text' => 'Infinitive grammar']);
        LearningItemFactory::new()->ofType('grammar')->create(['canonical_text' => 'Archived infinitive', 'status' => 'archived']);
        $this->getJson('/admin/questions/editor-catalog?kind=knowledge&q=Infinitive')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $knowledge->id);
        $asset = MediaAsset::create(['title' => 'Diagram', 'disk' => 'local', 'path' => 'diagram.png', 'mime_type' => 'image/png', 'size' => 100]);
        $this->getJson('/admin/questions/editor-catalog?kind=image&q=Diagram')->assertOk()->assertJsonPath('items.0.id', $asset->id);
        $this->postJson('/admin/questions/preview', $this->fields(['metadata' => ['audio_id' => $asset->id]]))->assertUnprocessable()->assertJsonValidationErrors('metadata.audio_id');
        $this->postJson('/admin/questions/preview', $this->fields(['question_type_id' => DB::table('question_types')->where('code', 'essay')->value('id')]))->assertUnprocessable()->assertJsonValidationErrors('question_type_id');
    }

    public function test_exam_links_are_idempotent_versioned_and_preserve_published_snapshots(): void
    {
        $question = QuestionBankItemFactory::new()->create();
        $exam = ExamTemplateFactory::new()->create(['mode' => 'mini']);
        $url = '/admin/questions/'.$question->id.'/exam-links/'.$exam->id;
        $this->postJson($url, ['revision_counter' => 0])->assertOk()->assertJsonPath('items.0.draft', true);
        $this->assertSame([$question->id], $exam->fresh()->draft_payload['sections'][0]['question_ids']);
        $this->postJson($url, ['revision_counter' => 1])->assertOk();
        $this->assertSame(1, $exam->fresh()->revision_counter);
        $this->deleteJson($url, ['revision_counter' => 0])->assertStatus(409);
        $this->deleteJson($url, ['revision_counter' => 1])->assertOk()->assertJsonCount(0, 'items');
        $this->assertSame([], $exam->fresh()->draft_payload['sections'][0]['question_ids']);

        $published = $this->publishedExam([$this->item(['id' => $question->id])]);
        $original = $published->publishedRevision->payload;
        $published->update(['draft_payload' => ['sections' => [['exam_part_id' => $question->exam_part_id, 'question_ids' => [$question->id]]]]]);
        $this->deleteJson('/admin/questions/'.$question->id.'/exam-links/'.$published->id, ['revision_counter' => 0])->assertOk()->assertJsonPath('items.0.published', true)->assertJsonPath('items.0.draft', false);
        $this->assertSame($original, $published->fresh()->publishedRevision->payload);
    }

    public function test_ielts_matching_keys_and_multiple_answers_are_not_replaced_by_fixed_options(): void
    {
        $part = DB::table('exam_parts')->where('code', 'reading-part-1')->value('id');
        $type = DB::table('question_types')->where('code', 'matching_headings')->value('id');
        $this->postJson('/admin/questions/preview', $this->fields(['exam_part_id' => $part, 'question_type_id' => $type, 'options_values' => ['i' => 'Heading one', 'ii' => 'Heading two'], 'correct_options' => [], 'metadata' => ['answer_map' => ['1' => 'ii']]]))->assertOk()->assertJsonPath('warnings', []);
        $question = QuestionBankItemFactory::new()->create();
        $payload = $this->item(['exam_part_id' => $part, 'type' => 'matching_headings', 'options' => ['i' => 'Heading one', 'ii' => 'Heading two'], 'correct_options' => [], 'metadata' => ['answer_map' => ['1' => 'ii']]]);
        app(ContentRevisionService::class)->save('question', $question, $payload, 0);
        $this->get('/admin/questions/'.$question->id.'/edit')->assertOk()->assertSee('options_values[i]', false)->assertSee('Heading two')->assertSee('1 | ii');
    }

    public function test_reviewer_can_inspect_but_cannot_mutate_editor_data(): void
    {
        $question = QuestionBankItemFactory::new()->create();
        $exam = ExamTemplateFactory::new()->create();
        $this->actingAs($this->admin('reviewer'));
        $this->get('/admin/questions/'.$question->id.'/edit')->assertOk()->assertSee('data-can-edit="0"', false);
        $this->getJson('/admin/questions/'.$question->id.'/history')->assertOk();
        $this->getJson('/admin/questions/'.$question->id.'/exam-links')->assertOk();
        $this->getJson('/admin/questions/editor-catalog?kind=tag')->assertOk();
        $this->postJson('/admin/questions/preview', $this->fields())->assertForbidden();
        $this->putJson('/admin/questions/'.$question->id, $this->fields())->assertForbidden();
        $this->postJson('/admin/questions/'.$question->id.'/exam-links/'.$exam->id, ['revision_counter' => 0])->assertForbidden();
        $this->deleteJson('/admin/questions/'.$question->id.'/exam-links/'.$exam->id, ['revision_counter' => 0])->assertForbidden();
        $this->assertSame(0, $exam->fresh()->revision_counter);
    }

    public function test_empty_knowledge_selection_and_incomplete_answers_can_be_previewed(): void
    {
        $this->postJson('/admin/questions/preview', $this->fields(['metadata' => ['knowledge_ids' => null], 'correct_options' => []]))->assertOk()->assertJsonStructure(['html', 'warnings']);
    }

    public function test_preview_uses_audio_attached_to_selected_stimulus(): void
    {
        $asset = MediaAsset::create(['title' => 'Shared audio', 'disk' => 'local', 'path' => 'shared.mp3', 'mime_type' => 'audio/mpeg', 'size' => 100]);
        $stimulus = \VistiqCore\Models\LearningStimulus::create(['title' => 'Conversation', 'stimulus_type' => 'audio', 'status' => 'draft']);
        $stimulus->forceFill(['metadata' => ['asset_id' => $asset->id]])->save();
        $response = $this->postJson('/admin/questions/preview', $this->fields(['learning_stimulus_id' => $stimulus->id]));
        $response->assertOk();
        $this->assertStringContainsString(route('admin.media.waveform', $asset), $response->json('html'));
        $this->assertStringContainsString('<audio controls', $response->json('html'));
    }
}
