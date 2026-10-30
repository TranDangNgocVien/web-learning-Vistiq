<?php

namespace Tests\Admin\Feature\QuestionBank;

use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Tests\Admin\TestCase;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Models\Role;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionBankCatalog;

class ReadingQuestionBankTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin('content-admin'));
    }

    private function reading(string $title = 'Office relocation', string $partCode = 'part-7'): LearningStimulus
    {
        $this->post('/admin/readings', ['stimulus_type' => 'reading', 'title' => $title, 'body' => '<p>Our office is moving next month.</p>', 'exam_part_id' => DB::table('exam_parts')->where('code', $partCode)->value('id')])->assertSessionHasNoErrors();

        return LearningStimulus::latest('id')->firstOrFail();
    }

    private function fields(LearningStimulus $reading, array $extra = []): array
    {
        $p = app(ContentRevisionService::class)->payload('stimulus', $reading);

        return array_replace(['exam_part_id' => $p['metadata']['exam_part_id'], 'question_type_id' => DB::table('question_types')->where('code', 'multiple_choice')->value('id'), 'question_difficulty_id' => DB::table('question_difficulties')->where('code', 'medium')->value('id'), 'prompt' => 'When will the office move?', 'options_values' => ['A' => 'Next month', 'B' => 'Tomorrow'], 'correct_options' => ['A'], 'editor_stimulus_id' => $reading->id, 'learning_stimulus_id' => $reading->id], $extra);
    }

    public function test_reading_creation_builds_a_group_and_embedded_editor(): void
    {
        $reading = $this->reading();
        $p = app(ContentRevisionService::class)->payload('stimulus', $reading);
        $this->assertSame('passage', $p['metadata']['content_kind']);
        $this->assertDatabaseHas('question_groups', ['id' => $p['metadata']['question_group_id'], 'kind' => 'reading_set']);
        $this->get('/admin/readings?stimulus='.$reading->id)->assertOk()->assertSee('Office relocation')->assertSee('Thêm câu hỏi');
        $this->get('/admin/readings/'.$reading->id.'/question-editor')->assertOk()->assertSee('data-embedded="1"', false)->assertSee('Lưu và thêm câu tiếp');
        $this->get('/admin/readings?create=1')->assertOk()->assertSee('Lưu và thêm câu hỏi');
    }

    public function test_reading_drafts_show_a_send_for_review_action_in_the_list(): void
    {
        $reading = $this->reading();
        $reviewUrl = route('admin.content.transition', ['kind' => 'stimulus', 'id' => $reading->id, 'action' => 'review']);

        $this->get('/admin/readings')
            ->assertOk()
            ->assertSee($reviewUrl, false)
            ->assertSee('class="button button-primary" type="submit">Gửi duyệt</button>', false);

        $this->post($reviewUrl)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('content_revisions', ['id' => $reading->draft_revision_id, 'status' => 'review']);
    }

    public function test_reading_list_can_send_multiple_draft_passages_for_review_atomically(): void
    {
        $first = $this->reading('First passage');
        $second = $this->reading('Second passage');
        $this->get('/admin/readings')
            ->assertOk()
            ->assertSee('data-reading-review-select-all', false)
            ->assertSee('data-reading-review-checkbox', false)
            ->assertSee('Gửi duyệt bài đã chọn');

        $this->post('/admin/readings/review-bulk', [
            'ids' => [$first->id, $second->id],
            'revision_ids' => [$first->id => $first->draft_revision_id, $second->id => $second->draft_revision_id],
        ])->assertRedirect()->assertSessionHas('status', 'Đã gửi duyệt 2 bài Reading.');

        $this->assertDatabaseHas('content_revisions', ['id' => $first->draft_revision_id, 'status' => 'review']);
        $this->assertDatabaseHas('content_revisions', ['id' => $second->draft_revision_id, 'status' => 'review']);
    }

    public function test_reading_bulk_review_rejects_stale_selection_without_partial_transition(): void
    {
        $first = $this->reading('First passage');
        $second = $this->reading('Second passage');
        $firstRevisionId = $first->draft_revision_id;
        $secondRevisionId = $second->draft_revision_id;
        ContentRevision::findOrFail($secondRevisionId)->update(['status' => 'review']);

        $this->post('/admin/readings/review-bulk', [
            'ids' => [$first->id, $second->id],
            'revision_ids' => [$first->id => $firstRevisionId, $second->id => $secondRevisionId],
        ])->assertConflict();

        $this->assertDatabaseHas('content_revisions', ['id' => $firstRevisionId, 'status' => 'draft']);
        $this->assertDatabaseHas('content_revisions', ['id' => $secondRevisionId, 'status' => 'review']);
    }

    public function test_five_linked_questions_produce_one_bank_row_and_full_preview(): void
    {
        $reading = $this->reading();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/admin/questions', $this->fields($reading, ['prompt' => 'Question '.$i]))->assertOk();
        }
        $this->getJson('/admin/questions')->assertOk()->assertViewHas('entries', fn ($e) => $e->total() === 1 && $e->first()['questions']->count() === 5);
        $this->get('/admin/questions/bank-preview/stimulus/'.$reading->id)->assertOk()->assertSee('Câu hỏi liên quan (5)')->assertSee('Question 4')->assertSee('Đáp án đúng');
        $rows = QuestionBankItem::orderBy('group_order')->get();
        $this->assertSame([0, 1, 2, 3, 4], $rows->pluck('group_order')->all());
        $this->assertCount(1, $rows->pluck('question_group_id')->unique());
    }

    public function test_reading_detail_shows_single_and_bulk_approval_for_questions_waiting_review(): void
    {
        $reading = $this->reading();
        $questionId = $this->postJson('/admin/questions', $this->fields($reading))->assertOk()->json('id');
        $question = QuestionBankItem::findOrFail($questionId);
        $question->draftRevision->update(['status' => 'review']);

        $this->actingAs($this->admin('reviewer'));
        $this->get('/admin/readings?stimulus='.$reading->id.'&preview_question='.$questionId)
            ->assertOk()
            ->assertSee('Duyệt 1 câu đang chờ')
            ->assertSee('aria-label="Duyệt câu hỏi Q-'.$questionId.'"', false);
    }

    public function test_embedded_save_preserves_group_order_and_rejects_stale_revision(): void
    {
        $reading = $this->reading();
        $created = $this->postJson('/admin/questions', $this->fields($reading))->assertOk();
        $id = $created->json('id');
        $group = QuestionBankItem::findOrFail($id)->question_group_id;
        $this->putJson('/admin/questions/'.$id, $this->fields($reading, ['prompt' => 'Updated question', 'revision_counter' => 1, 'group_order' => 99]))->assertOk()->assertJsonPath('revision_counter', 2)->assertJsonFragment(['id' => $id]);
        $this->assertDatabaseHas('question_bank_items', ['id' => $id, 'question_group_id' => $group, 'group_order' => 0]);
        $this->putJson('/admin/questions/'.$id, $this->fields($reading, ['revision_counter' => 1]))->assertConflict();
        $this->get('/admin/readings/'.$reading->id.'/question-editor?question='.$id)->assertOk()->assertSee('Updated question');
    }

    public function test_embedded_validation_keeps_other_articles_and_questions_untouched(): void
    {
        $first = $this->reading('First');
        $other = $this->reading('Other');
        $id = $this->postJson('/admin/questions', $this->fields($first))->assertOk()->json('id');
        $this->putJson('/admin/questions/'.$id, $this->fields($other, ['revision_counter' => 1]))->assertUnprocessable();
        $this->get('/admin/readings/'.$other->id.'/question-editor?question='.$id)->assertNotFound();
        $this->postJson('/admin/questions', $this->fields($first, ['question_type_id' => 999999]))->assertUnprocessable();
        $this->assertSame(1, QuestionBankItem::count());
    }

    public function test_paragraph_has_part6_group_and_one_row(): void
    {
        $reading = $this->reading('Company update', 'part-6');
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/admin/questions', $this->fields($reading))->assertOk();
        }
        $p = app(ContentRevisionService::class)->payload('stimulus', $reading);
        $this->assertSame('paragraph', $p['metadata']['content_kind']);
        $this->assertDatabaseHas('question_groups', ['id' => $p['metadata']['question_group_id'], 'kind' => 'part6']);
        $this->getJson('/admin/questions')->assertOk()->assertViewHas('entries', fn ($e) => $e->total() === 1 && str_starts_with($e->first()['code'], 'T-'));
    }

    public function test_current_draft_moves_question_out_of_old_passage(): void
    {
        $old = $this->reading('Old');
        $new = $this->reading('New');
        $q = QuestionBankItemFactory::new()->create(['learning_stimulus_id' => $old->id]);
        $published = ContentRevision::create(['kind' => 'question', 'entity_id' => $q->id, 'number' => 1, 'status' => 'published', 'payload' => ['prompt' => 'Old association', 'stimulus_ids' => [$old->id]]]);
        $draft = ContentRevision::create(['kind' => 'question', 'entity_id' => $q->id, 'number' => 2, 'status' => 'draft', 'payload' => ['prompt' => 'New association', 'stimulus_ids' => [(string) $new->id, $new->id]]]);
        $q->forceFill(['published_revision_id' => $published->id, 'draft_revision_id' => $draft->id])->save();
        $data = app(QuestionBankCatalog::class)->catalog();
        $this->assertCount(0, $data['entries']['stimulus:'.$old->id]['questions']);
        $this->assertCount(1, $data['entries']['stimulus:'.$new->id]['questions']);
        $this->getJson('/admin/questions?q=Old')->assertViewHas('entries', fn ($e) => $e->total() === 0);
    }

    public function test_multiple_passages_keep_codes_and_shared_question_selection_is_unique(): void
    {
        $first = $this->reading('First passage');
        $second = $this->reading('Second passage');
        $q = QuestionBankItemFactory::new()->create();
        $r = ContentRevision::create(['kind' => 'question', 'entity_id' => $q->id, 'number' => 1, 'status' => 'draft', 'payload' => ['prompt' => 'A shared question', 'stimulus_ids' => [$first->id, (string) $second->id]]]);
        $q->forceFill(['draft_revision_id' => $r->id])->save();
        $this->getJson('/admin/questions')->assertOk()->assertViewHas('entries', fn ($e) => $e->total() === 2)->assertViewHas('matchingQuestionIds', [$q->id]);
        $this->assertSame(1, substr_count($this->getJson('/admin/questions')->getContent(), 'name="ids[]"'));
    }

    public function test_filtering_groups_rows_but_preview_keeps_all_questions(): void
    {
        $reading = $this->reading();
        $easy = DB::table('question_difficulties')->where('code', 'easy')->value('id');
        $this->postJson('/admin/questions', $this->fields($reading, ['prompt' => 'Easy question', 'question_difficulty_id' => $easy]))->assertOk();
        $this->postJson('/admin/questions', $this->fields($reading, ['prompt' => 'Medium question']))->assertOk();
        $this->getJson('/admin/questions?difficulty=easy')->assertViewHas('entries', fn ($e) => $e->first()['questions']->count() === 2 && count($e->first()['matching_ids']) === 1 && $e->first()['difficulty'] === 'mixed');
        $this->get('/admin/questions/bank-preview/stimulus/'.$reading->id.'?difficulty=easy')->assertOk()->assertSee('Easy question')->assertSee('Medium question');
    }

    public function test_audio_through_group_or_direct_media_is_grouped_and_unassigned_group_is_visible(): void
    {
        $asset = MediaAsset::create(['title' => 'Conversation.mp3', 'disk' => 'public', 'path' => 'media/test.mp3', 'mime_type' => 'audio/mpeg', 'size' => 100, 'created_by' => auth()->id()]);
        $audio = LearningStimulus::create(['title' => 'Conversation', 'stimulus_type' => 'audio', 'body' => '', 'created_by' => auth()->id(), 'status' => 'draft']);
        $audio->forceFill(['metadata' => ['asset_id' => $asset->id]])->save();
        $part = DB::table('exam_parts')->where('code', 'part-3')->value('id');
        $group = DB::table('question_groups')->insertGetId(['title' => 'Conversation group', 'kind' => 'conversation', 'exam_part_id' => $part, 'stimulus_ids' => json_encode([(string) $audio->id]), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        QuestionBankItemFactory::new()->count(2)->create(['question_group_id' => $group, 'exam_part_id' => $part]);
        $q = QuestionBankItemFactory::new()->create(['exam_part_id' => $part]);
        $q->forceFill(['metadata' => ['audio_id' => $asset->id]])->save();
        $missingGroup = DB::table('question_groups')->insertGetId(['title' => 'Missing resource', 'kind' => 'conversation', 'exam_part_id' => $part, 'stimulus_ids' => '[]', 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        QuestionBankItemFactory::new()->create(['question_group_id' => $missingGroup, 'exam_part_id' => $part]);
        $this->getJson('/admin/questions')->assertOk()->assertViewHas('entries', fn ($e) => $e->total() === 2)->assertSee('Chưa gắn tài nguyên');
        $this->getJson('/admin/questions?audio=yes')->assertViewHas('entries', fn ($e) => $e->total() === 1 && $e->first()['questions']->count() === 3);
    }

    public function test_view_only_user_can_preview_but_cannot_create(): void
    {
        $reading = $this->reading();
        $user = $this->admin('content-admin');
        $user->roles()->detach();
        $role = Role::create(['code' => 'read-only-admin', 'name' => 'Read only']);
        $role->permissions()->attach(DB::table('permissions')->whereIn('code', ['content.view', 'admin.access'])->pluck('id'));
        $user->roles()->attach($role->id);
        $this->actingAs($user);
        $this->get('/admin/readings?stimulus='.$reading->id)->assertOk()->assertDontSee('+ Thêm câu hỏi')->assertDontSee('+ Tạo bài đọc');
        $this->get('/admin/questions/bank-preview/stimulus/'.$reading->id)->assertOk();
        $this->postJson('/admin/questions', $this->fields($reading))->assertForbidden();
    }

    public function test_bank_paginates_whole_articles_and_finds_unpadded_codes(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $reading = LearningStimulus::create(['title' => 'Passage '.$i, 'body' => 'Shared text', 'stimulus_type' => 'reading', 'status' => 'draft', 'created_by' => auth()->id()]);
            QuestionBankItemFactory::new()->count(2)->create(['learning_stimulus_id' => $reading->id]);
        }
        $this->getJson('/admin/questions?page=2')->assertOk()->assertViewHas('entries', fn ($e) => $e->total() === 11 && $e->count() === 1 && $e->first()['questions']->count() === 2);
        $first = LearningStimulus::firstOrFail();
        $this->getJson('/admin/questions?q=P-'.$first->id)->assertOk()->assertViewHas('entries', fn ($e) => $e->total() === 1 && $e->first()['id'] === $first->id);
        $question = QuestionBankItem::firstOrFail();
        $this->getJson('/admin/questions?q=Q-'.$question->id)->assertOk()->assertViewHas('matchingQuestionIds', [$question->id]);
    }

    public function test_embedded_edit_preserves_all_passages_of_a_shared_question(): void
    {
        $first = $this->reading('First');
        $second = $this->reading('Second');
        $id = $this->postJson('/admin/questions', $this->fields($first))->assertOk()->json('id');
        $q = QuestionBankItem::findOrFail($id);
        $payload = app(ContentRevisionService::class)->payload('question', $q);
        $payload['stimulus_ids'] = [$first->id, (string) $second->id];
        app(ContentRevisionService::class)->save('question', $q, $payload, 1);
        $this->putJson('/admin/questions/'.$id, $this->fields($second, ['revision_counter' => 2]))->assertOk();
        $updated = app(ContentRevisionService::class)->payload('question', $q->fresh());
        $this->assertSame([$first->id, $second->id], $updated['stimulus_ids']);
        $this->assertSame($payload['group_id'], $updated['group_id']);
        $this->assertSame($payload['group_order'], $updated['group_order']);
    }

    public function test_group_media_without_stimulus_and_missing_files_keep_their_questions(): void
    {
        $part = DB::table('exam_parts')->where('code', 'part-3')->value('id');
        $asset = MediaAsset::create(['title' => 'Shared audio.wav', 'disk' => 'local', 'path' => 'media/shared.wav', 'mime_type' => 'audio/wav', 'size' => 100, 'created_by' => auth()->id()]);
        $group = DB::table('question_groups')->insertGetId(['title' => 'Media group', 'kind' => 'conversation', 'exam_part_id' => $part, 'stimulus_ids' => '[]', 'metadata' => json_encode(['audio_id' => $asset->id]), 'created_at' => now(), 'updated_at' => now()]);
        QuestionBankItemFactory::new()->count(3)->create(['exam_part_id' => $part, 'question_group_id' => $group]);
        $this->getJson('/admin/questions')->assertOk()->assertViewHas('entries', fn ($e) => $e->total() === 1 && $e->first()['kind'] === 'media' && $e->first()['questions']->count() === 3);
        $asset->delete();
        $this->getJson('/admin/questions?missing=1')->assertOk()->assertViewHas('entries', fn ($e) => $e->total() === 1 && $e->first()['questions']->count() === 3);
        $this->get('/admin/questions/bank-preview/media/'.$asset->id)->assertOk()->assertSee('file không còn tồn tại');
    }

    public function test_paragraph_group_without_passage_is_reported_as_missing_reading(): void
    {
        $part = DB::table('exam_parts')->where('code', 'part-6')->value('id');
        $group = DB::table('question_groups')->insertGetId(['title' => 'Unassigned paragraph', 'kind' => 'part6', 'exam_part_id' => $part, 'stimulus_ids' => '[]', 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        QuestionBankItemFactory::new()->count(4)->create(['exam_part_id' => $part, 'question_group_id' => $group]);
        $this->getJson('/admin/questions?missing=1&reading=no')->assertOk()->assertViewHas('entries', fn ($e) => $e->total() === 1 && $e->first()['kind'] === 'group');
        $this->getJson('/admin/questions?reading=yes')->assertOk()->assertViewHas('entries', fn ($e) => $e->total() === 0);
    }

    public function test_reading_update_preserves_binding_and_rolls_back_groups_on_conflict(): void
    {
        $reading = $this->reading();
        $payload = app(ContentRevisionService::class)->payload('stimulus', $reading);
        $fields = ['stimulus_type' => 'reading', 'title' => 'Updated title', 'body' => '<p>Updated content.</p>', 'exam_part_id' => $payload['metadata']['exam_part_id'], 'revision_counter' => 1];
        $this->putJson('/admin/stimuli/'.$reading->id, $fields)->assertOk()->assertJsonPath('revision_counter', 2);
        $updated = app(ContentRevisionService::class)->payload('stimulus', $reading->fresh());
        $this->assertSame($payload['metadata']['question_group_id'], $updated['metadata']['question_group_id']);
        $otherPart = DB::table('exam_parts')->where('code', 'part-6')->value('id');
        $this->putJson('/admin/stimuli/'.$reading->id, array_replace($fields, ['exam_part_id' => $otherPart]))->assertConflict();
        $this->assertSame(1, DB::table('question_groups')->count());
        $this->postJson('/admin/questions', $this->fields($reading->fresh()))->assertOk();
        $this->putJson('/admin/stimuli/'.$reading->id, array_replace($fields, ['exam_part_id' => $otherPart, 'revision_counter' => 2]))->assertUnprocessable();
        $this->get('/admin/readings?stimulus='.$reading->id)->assertOk()->assertSee('Updated title')->assertSee('Updated content.');
    }
}
