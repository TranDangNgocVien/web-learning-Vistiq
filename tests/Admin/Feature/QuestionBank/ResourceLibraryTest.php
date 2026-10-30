<?php

namespace Tests\Admin\Feature\QuestionBank;

use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Admin\TestCase;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Services\Assessment\QuestionBank\AudioQuestionService;

class ResourceLibraryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin());
    }

    private function asset(array $attributes = []): MediaAsset
    {
        return MediaAsset::create(array_replace(['title' => 'conversation.mp3', 'disk' => 'public', 'path' => 'media/conversation.mp3', 'mime_type' => 'audio/mpeg', 'size' => 2400000, 'created_by' => auth()->id(), 'metadata' => ['source' => 'TOEIC practice', 'tags' => ['TOEIC', 'Part 3']]], $attributes));
    }

    public function test_combined_library_filters_real_resources_and_paginates(): void
    {
        $audio = $this->asset();
        $image = $this->asset(['title' => 'diagram.png', 'path' => 'media/diagram.png', 'mime_type' => 'image/png', 'size' => 800, 'metadata' => ['tags' => ['IELTS', 'Reading']]]);

        $this->get('/admin/resources')->assertOk()->assertViewHas('resourceStats', fn ($stats) => $stats['total'] === 2 && $stats['audio'] === 1 && $stats['image'] === 1 && $stats['size'] === 2400800);
        $this->get('/admin/resources?type=image&category=ielts&size=small&format=png')->assertOk()->assertViewHas('assets', fn ($assets) => $assets->total() === 1 && $assets->first()->id === $image->id);
        $this->get('/admin/resources?type=audio&q=practice&category=part-3')->assertOk()->assertViewHas('assets', fn ($assets) => $assets->total() === 1 && $assets->first()->id === $audio->id);
        $this->get('/admin/media?type=audio')->assertOk()->assertViewHas('assets', fn ($assets) => $assets->total() === 1);
        $this->get('/admin/resources?type=image&q=missing')->assertOk()->assertViewHas('assets', fn ($assets) => $assets->isEmpty());
    }

    public function test_linked_question_drives_category_and_used_status(): void
    {
        $asset = $this->asset(['metadata' => []]);
        $question = QuestionBankItemFactory::new()->create(['exam_part_id' => DB::table('exam_parts')->where('code', 'part-3')->value('id')]);
        $revision = ContentRevision::create(['kind' => 'question', 'entity_id' => $question->id, 'number' => 1, 'status' => 'draft', 'payload' => ['metadata' => ['audio_id' => $asset->id]]]);
        $question->forceFill(['draft_revision_id' => $revision->id])->save();

        $this->get('/admin/resources?category=part-3&status=used')->assertOk()->assertViewHas('assets', fn ($assets) => $assets->total() === 1 && $assets->first()->linkedQuestions->contains('id', $question->id));
        $this->get('/admin/resources?status=unassigned')->assertOk()->assertViewHas('assets', fn ($assets) => $assets->isEmpty());
    }

    public function test_ajax_list_returns_only_the_resource_view_with_filters_and_pagination(): void
    {
        $this->asset(['title' => 'image.png', 'mime_type' => 'image/png']);
        for ($index = 1; $index <= 11; $index++) {
            $this->asset(['title' => 'track-'.$index.'.mp3']);
        }

        $response = $this->getJson('/admin/resources?type=audio&q=track&page=2');
        $response->assertOk()->assertJsonStructure(['html', 'url']);
        $html = $response->json('html');
        $this->assertStringContainsString('data-resource-library', $html);
        $this->assertStringContainsString('track-1.mp3', $html);
        $this->assertStringNotContainsString('image.png', $html);
        $this->assertStringNotContainsString('<html', $html);
        $this->assertSame(route('admin.resources.index').'?type=audio&q=track&page=2', $response->json('url'));
        $this->getJson('/admin/resources?type=invalid')->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_ajax_list_clamps_deleted_or_out_of_range_pages(): void
    {
        $this->asset();
        $response = $this->getJson('/admin/resources?page=99');
        $response->assertOk();
        $this->assertStringContainsString('conversation.mp3', $response->json('html'));
        $this->assertSame(route('admin.resources.index').'?page=1', $response->json('url'));
        $this->getJson('/admin/resources?q=missing&page=99')->assertOk()->assertJsonPath('url', route('admin.resources.index').'?q=missing&page=1');
    }

    public function test_ajax_edit_and_delete_return_json_and_preserve_validation(): void
    {
        Storage::fake('public');
        $asset = $this->asset();
        $linked = $this->asset(['metadata' => ['question_id' => 99]]);
        ContentRevision::create(['kind' => 'question', 'entity_id' => 99, 'number' => 1, 'status' => 'draft', 'payload' => ['metadata' => ['audio_id' => $linked->id]]]);
        $this->putJson('/admin/media/'.$asset->id, ['title' => ''])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->assertSame('conversation.mp3', $asset->fresh()->title);
        $this->putJson('/admin/media/'.$asset->id, ['title' => 'Ajax name'])->assertOk()->assertJsonPath('id', $asset->id)->assertJsonPath('message', 'Đã cập nhật tài nguyên.');
        $this->assertSame('Ajax name', $asset->fresh()->title);
        $this->deleteJson('/admin/media', ['ids' => [$asset->id, $linked->id]])->assertOk()->assertJsonPath('deleted', 1)->assertJsonPath('skipped', 1)->assertJsonPath('message', 'Đã xóa 1 tài nguyên; bỏ qua 1 tài nguyên đang được sử dụng.');
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $linked->id]);
        $this->deleteJson('/admin/media', ['ids' => []])->assertUnprocessable()->assertJsonValidationErrors('ids');
    }

    public function test_edit_preserves_storage_and_question_assignment_metadata(): void
    {
        $asset = $this->asset(['metadata' => ['question_id' => 12, 'storage_folder' => 'questions/Q-00012']]);
        $this->put('/admin/media/'.$asset->id, ['title' => 'Renamed track', 'source' => 'Updated source', 'transcript' => 'Hello.', 'tags' => 'Listening, Business'])->assertRedirect();
        $asset->refresh();
        $this->assertSame('Renamed track', $asset->title);
        $this->assertSame(12, $asset->metadata['question_id']);
        $this->assertSame('questions/Q-00012', $asset->metadata['storage_folder']);
        $this->assertSame(['Listening', 'Business'], $asset->metadata['tags']);
        $this->assertSame('media/conversation.mp3', $asset->path);
    }

    public function test_library_counts_questions_using_audio_through_a_group_stimulus(): void
    {
        Storage::fake('r2');
        $this->postJson('/admin/media', ['title' => 'group-audio.mp3', 'file' => UploadedFile::fake()->create('group-audio.mp3', 10, 'audio/mpeg'), 'create_stimulus' => true])->assertOk();
        $stimulus = LearningStimulus::query()->firstOrFail();
        $partId = DB::table('exam_parts')->where('code', 'part-3')->value('id');
        $groupId = DB::table('question_groups')->insertGetId(['title' => 'Audio group', 'kind' => 'conversation', 'exam_part_id' => $partId, 'stimulus_ids' => json_encode([$stimulus->id]), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $question = QuestionBankItemFactory::new()->create(['exam_part_id' => $partId, 'question_group_id' => $groupId]);
        $this->get('/admin/resources?status=used&category=part-3')->assertOk()->assertViewHas('assets', fn ($assets) => $assets->total() === 1 && $assets->first()->linkedQuestions->contains('id', $question->id));
    }

    public function test_group_audio_with_string_stimulus_ids_counts_real_questions_and_cannot_be_deleted(): void
    {
        Storage::fake('r2');
        $this->postJson('/admin/media', ['title' => 'imported-group.mp3', 'file' => UploadedFile::fake()->create('imported-group.mp3', 10, 'audio/mpeg'), 'create_stimulus' => true])->assertOk();
        $asset = MediaAsset::query()->firstOrFail();
        $stimulus = LearningStimulus::query()->firstOrFail();
        $partId = DB::table('exam_parts')->where('code', 'part-3')->value('id');
        $groupId = DB::table('question_groups')->insertGetId(['title' => 'Imported group', 'kind' => 'conversation', 'exam_part_id' => $partId, 'stimulus_ids' => json_encode([(string) $stimulus->id]), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $questions = QuestionBankItemFactory::new()->count(2)->create(['exam_part_id' => $partId, 'question_group_id' => $groupId]);

        $this->get('/admin/resources?status=used&category=part-3')->assertOk()
            ->assertViewHas('assets', fn ($assets) => $assets->total() === 1 && $assets->first()->linkedQuestions->pluck('id')->sort()->values()->all() === $questions->modelKeys())
            ->assertViewHas('resourceStats', fn ($stats) => $stats['unassigned'] === 0);
        $response = $this->getJson('/admin/resources?q=imported-group&status=used&category=part-3')->assertOk();
        $this->assertStringContainsString('2 câu hỏi', $response->json('html'));
        $this->assertStringContainsString('Đang dùng', $response->json('html'));
        $this->assertStringContainsString('TOEIC Part 3', $response->json('html'));
        $this->assertEquals($questions->modelKeys(), app(AudioQuestionService::class)->questionsForStimulus($stimulus)->pluck('id')->all());
        $this->deleteJson('/admin/media', ['ids' => [$asset->id]])->assertOk()->assertJsonPath('deleted', 0)->assertJsonPath('skipped', 1);
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
        Storage::disk('r2')->assertExists($asset->path);
    }

    public function test_draft_and_published_revision_stimulus_links_support_both_id_types_without_duplicates(): void
    {
        $asset = $this->asset(['metadata' => []]);
        $stimulus = LearningStimulus::create(['created_by' => auth()->id(), 'title' => 'Root audio', 'stimulus_type' => 'audio', 'body' => '', 'status' => 'draft']);
        $stimulus->forceFill(['metadata' => ['asset_id' => $asset->id]])->save();
        $partId = DB::table('exam_parts')->where('code', 'part-3')->value('id');
        $questions = QuestionBankItemFactory::new()->count(2)->create(['exam_part_id' => $partId]);
        foreach ($questions as $index => $question) {
            $status = $index === 0 ? 'draft' : 'published';
            $revision = ContentRevision::create(['kind' => 'question', 'entity_id' => $question->id, 'number' => 1, 'status' => $status, 'payload' => ['stimulus_ids' => [$stimulus->id, (string) $stimulus->id]]]);
            $question->forceFill([$status.'_revision_id' => $revision->id])->save();
        }

        $this->get('/admin/resources?status=used&category=part-3')->assertOk()->assertViewHas('assets', fn ($assets) => $assets->total() === 1 && $assets->first()->linkedQuestions->pluck('id')->sort()->values()->all() === $questions->modelKeys());
        $this->assertEquals($questions->modelKeys(), app(AudioQuestionService::class)->questionsForStimulus($stimulus)->pluck('id')->all());
    }

    public function test_delete_protects_string_stimulus_references_even_without_live_questions(): void
    {
        Storage::fake('r2');
        foreach (['group-reference', 'historical-reference'] as $title) {
            $this->postJson('/admin/media', ['title' => $title.'.mp3', 'file' => UploadedFile::fake()->create($title.'.mp3', 10, 'audio/mpeg'), 'create_stimulus' => true])->assertOk();
        }
        $stimuli = LearningStimulus::query()->orderBy('id')->get();
        DB::table('question_groups')->insert(['title' => 'Empty linked group', 'kind' => 'conversation', 'exam_part_id' => DB::table('exam_parts')->where('code', 'part-3')->value('id'), 'stimulus_ids' => json_encode([(string) $stimuli[0]->id]), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        ContentRevision::create(['kind' => 'question', 'entity_id' => 999, 'number' => 1, 'status' => 'draft', 'payload' => ['stimulus_ids' => [(string) $stimuli[1]->id]]]);

        $this->deleteJson('/admin/media', ['ids' => MediaAsset::query()->pluck('id')->all()])->assertOk()->assertJsonPath('deleted', 0)->assertJsonPath('skipped', 2);
        $this->assertDatabaseCount('media_assets', 2);
        $this->assertDatabaseCount('learning_stimuli', 2);
        foreach (MediaAsset::all() as $asset) {
            Storage::disk('r2')->assertExists($asset->path);
        }
    }

    public function test_delete_removes_unused_files_and_preserves_revision_and_group_references(): void
    {
        Storage::fake('public');
        $unused = $this->asset(['path' => 'media/unused.mp3']);
        $linked = $this->asset(['path' => 'media/linked.mp3']);
        $groupAsset = $this->asset(['path' => 'media/group.mp3']);
        Storage::disk('public')->put($unused->path, 'audio');
        Storage::disk('public')->put($linked->path, 'audio');
        ContentRevision::create(['kind' => 'question', 'entity_id' => 99, 'number' => 1, 'status' => 'draft', 'payload' => ['metadata' => ['audio_id' => $linked->id]]]);
        DB::table('question_groups')->insert(['title' => 'Group', 'kind' => 'conversation', 'exam_part_id' => DB::table('exam_parts')->where('code', 'part-3')->value('id'), 'stimulus_ids' => '[]', 'metadata' => json_encode(['audio_id' => $groupAsset->id]), 'created_at' => now(), 'updated_at' => now()]);

        $this->delete('/admin/media', ['ids' => [$unused->id, $linked->id, $groupAsset->id]])->assertRedirect()->assertSessionHas('status', 'Đã xóa 1 tài nguyên; bỏ qua 2 tài nguyên đang được sử dụng.');
        $this->assertDatabaseMissing('media_assets', ['id' => $unused->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $linked->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $groupAsset->id]);
        Storage::disk('public')->assertMissing($unused->path);
        Storage::disk('public')->assertExists($linked->path);
    }

    public function test_reviewer_can_view_but_cannot_edit_or_delete(): void
    {
        $asset = $this->asset();
        $this->actingAs($this->admin('reviewer'));
        $this->get('/admin/resources')->assertOk()->assertDontSee('data-resource-upload-dialog', false)->assertDontSee('data-resource-edit-form', false);
        $response = $this->getJson('/admin/resources')->assertOk();
        $this->assertStringNotContainsString('data-resource-upload-dialog', $response->json('html'));
        $this->assertStringNotContainsString('data-resource-edit-form', $response->json('html'));
        $this->putJson('/admin/media/'.$asset->id, ['title' => 'Denied'])->assertForbidden();
        $this->deleteJson('/admin/media', ['ids' => [$asset->id]])->assertForbidden();
    }

    public function test_waveform_source_streams_audio_with_admin_access(): void
    {
        Storage::fake('public');
        $asset = $this->asset(['path' => 'media/wave.wav', 'mime_type' => 'audio/wav']);
        Storage::disk('public')->put($asset->path, 'waveform-audio-bytes');
        $response = $this->get('/admin/media/'.$asset->id.'/waveform');
        $response->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->assertSame('waveform-audio-bytes', $response->streamedContent());
        $this->get('/admin/media/'.$this->asset(['mime_type' => 'image/png'])->id.'/waveform')->assertNotFound();
        auth()->logout();
        $this->get('/admin/media/'.$asset->id.'/waveform')->assertRedirect('/admin/login');
    }

    public function test_library_audio_upload_remains_selectable_in_question_groups_and_can_be_deleted_when_unused(): void
    {
        Storage::fake('r2');
        $this->postJson('/admin/media', ['title' => 'new-track.mp3', 'file' => UploadedFile::fake()->create('new-track.mp3', 10, 'audio/mpeg'), 'create_stimulus' => true, 'transcript' => 'A conversation.'])->assertOk();
        $asset = MediaAsset::query()->firstOrFail();
        $stimulus = LearningStimulus::query()->firstOrFail();
        $revision = ContentRevision::findOrFail($stimulus->draft_revision_id);
        $this->assertSame($asset->id, $revision->payload['metadata']['asset_id']);
        $this->assertSame('A conversation.', $revision->payload['transcript']);
        $this->get('/admin/groups')->assertOk()->assertSee('Audio · new-track');
        $this->delete('/admin/media', ['ids' => [$asset->id]])->assertRedirect();
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        $this->assertDatabaseMissing('learning_stimuli', ['id' => $stimulus->id]);
        Storage::disk('r2')->assertMissing($asset->path);
    }

    public function test_delete_preserves_audio_tracks_used_by_a_group_or_published(): void
    {
        Storage::fake('r2');
        foreach (['group-track', 'published-track'] as $title) {
            $this->postJson('/admin/media', ['title' => $title.'.mp3', 'file' => UploadedFile::fake()->create($title.'.mp3', 10, 'audio/mpeg'), 'create_stimulus' => true])->assertOk();
        }
        $stimuli = LearningStimulus::query()->orderBy('id')->get();
        DB::table('question_groups')->insert(['title' => 'Assigned group', 'kind' => 'conversation', 'exam_part_id' => DB::table('exam_parts')->where('code', 'part-3')->value('id'), 'stimulus_ids' => json_encode([$stimuli[0]->id]), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $stimuli[1]->update(['status' => 'published']);
        $this->delete('/admin/media', ['ids' => MediaAsset::query()->pluck('id')->all()])->assertRedirect()->assertSessionHas('status', 'Đã xóa 0 tài nguyên; bỏ qua 2 tài nguyên đang được sử dụng.');
        $this->assertDatabaseCount('media_assets', 2);
        $this->assertDatabaseCount('learning_stimuli', 2);
    }

    public function test_resource_preview_can_render_with_representative_data(): void
    {
        for ($index = 1; $index <= 12; $index++) {
            $audio = $index % 3 !== 0;
            $this->asset(['title' => $audio ? 'toeic_part3_conversation_'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'.wav' : 'ielts_reading_diagram_'.$index.'.png', 'path' => $audio ? 'media/acceptance-audio.wav' : 'images/vistiq-logo.png', 'mime_type' => $audio ? 'audio/wav' : 'image/png', 'metadata' => ['tags' => $audio ? ['TOEIC', 'Part 3', 'Listening', 'Business'] : ['IELTS', 'Reading'], 'source' => 'Practice library']]);
        }
        $response = $this->get('/admin/resources');
        $response->assertOk();
    }
}
