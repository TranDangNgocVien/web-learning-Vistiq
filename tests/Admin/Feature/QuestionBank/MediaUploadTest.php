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
use VistiqCore\Services\Assessment\QuestionBank\QuestionAudioService;

class MediaUploadTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin());
        Storage::fake('r2');
    }

    public function test_audio_upload_assigns_the_new_asset_to_every_question_in_the_selected_group(): void
    {
        $partId = (int) DB::table('exam_parts')->where('code', 'part-5')->value('id');
        $groupId = DB::table('question_groups')->insertGetId([
            'title' => 'LISTENING-001',
            'kind' => 'question_group',
            'exam_part_id' => $partId,
            'stimulus_ids' => json_encode([]),
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $first = QuestionBankItemFactory::new()->create(['exam_part_id' => $partId]);
        $second = QuestionBankItemFactory::new()->create(['exam_part_id' => $partId]);
        $unrelated = QuestionBankItemFactory::new()->create(['exam_part_id' => $partId]);
        $first->forceFill(['question_group_id' => $groupId, 'group_order' => 0])->save();
        $second->forceFill(['question_group_id' => $groupId, 'group_order' => 1])->save();

        $response = $this->withHeader('Accept', 'application/json')->post(route('admin.media.store'), [
            'title' => 'listening-001.mp3',
            'file' => UploadedFile::fake()->create('listening-001.mp3', 10, 'audio/mpeg'),
            'question_id' => $first->id,
            'question_group_id' => $groupId,
        ]);

        $response->assertOk()->assertJson([
            'assigned_questions' => 2,
            'updated_questions' => 2,
            'current_question_id' => $first->id,
            'revision_counter' => 1,
        ]);

        $asset = MediaAsset::query()->latest('id')->firstOrFail();
        $this->assertSame($groupId, $asset->metadata['question_group_id']);
        $this->assertArrayNotHasKey('question_id', $asset->metadata);

        foreach ([$first, $second] as $question) {
            $question->refresh();
            $revision = ContentRevision::findOrFail($question->draft_revision_id);
            $this->assertSame($asset->id, $revision->payload['metadata']['audio_id']);
            $this->assertSame($groupId, $revision->payload['group_id']);
        }

        $this->assertSame(0, $unrelated->fresh()->revision_counter);
        $this->assertSame($asset->id, json_decode((string) DB::table('question_groups')->where('id', $groupId)->value('metadata'), true)['audio_id']);
    }

    public function test_audio_library_batch_upload_creates_drafts_and_media_assets(): void
    {
        $response = $this->post(route('admin.audio.batch.store'), [
            'files' => [
                UploadedFile::fake()->create('toeic-part-3-01.mp3', 10, 'audio/mpeg'),
                UploadedFile::fake()->create('toeic-part-3-02.ogg', 12, 'audio/ogg'),
            ],
        ]);

        $response->assertRedirect(route('admin.audio.index'))
            ->assertSessionHas('status', 'Đã tải lên 2 file audio.');
        $this->assertDatabaseHas('learning_stimuli', ['title' => 'toeic-part-3-01', 'stimulus_type' => 'audio', 'status' => 'draft']);
        $this->assertDatabaseHas('learning_stimuli', ['title' => 'toeic-part-3-02', 'stimulus_type' => 'audio', 'status' => 'draft']);
        $this->assertDatabaseCount('learning_stimuli', 2);
        $this->assertDatabaseCount('media_assets', 2);

        foreach (ContentRevision::where('kind', 'stimulus')->get() as $revision) {
            $this->assertSame('audio', $revision->payload['stimulus_type']);
            $this->assertArrayHasKey('asset_id', $revision->payload['metadata']);
            $this->assertNotEmpty($revision->payload['metadata']['asset_id']);
        }
    }

    public function test_audio_library_batch_upload_returns_json_for_chunked_requests(): void
    {
        $this->withHeader('Accept', 'application/json')
            ->post(route('admin.audio.batch.store'), [
                'files' => [
                    UploadedFile::fake()->create('chunk-01.mp3', 10, 'audio/mpeg'),
                ],
            ])
            ->assertOk()
            ->assertJson([
                'uploaded' => 1,
                'message' => 'Đã tải lên 1 file audio.',
            ]);
    }

    public function test_audio_library_shows_questions_linked_through_the_audio_group(): void
    {
        $this->post(route('admin.audio.batch.store'), [
            'files' => [UploadedFile::fake()->create('listening-g02.mp3', 10, 'audio/mpeg')],
        ]);

        $stimulus = LearningStimulus::query()->firstOrFail();
        $asset = MediaAsset::query()->firstOrFail();
        $partId = (int) DB::table('exam_parts')->where('code', 'part-5')->value('id');
        $groupId = DB::table('question_groups')->insertGetId([
            'title' => 'LISTENING-G02',
            'kind' => 'question_group',
            'exam_part_id' => $partId,
            'stimulus_ids' => json_encode([]),
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $question = QuestionBankItemFactory::new()->create(['exam_part_id' => $partId]);

        app(QuestionAudioService::class)->assignToGroup($groupId, $asset->id, $question->id);

        $this->get(route('admin.audio.index', ['stimulus' => $stimulus->id]))
            ->assertOk()
            ->assertSee('1 câu hỏi', false)
            ->assertSee('Câu hỏi đang dùng track này', false)
            ->assertSee('Q-'.str_pad((string) $question->id, 5, '0', STR_PAD_LEFT), false);
    }

    public function test_audio_library_does_not_infer_a_group_from_the_same_audio_title(): void
    {
        $this->post(route('admin.audio.batch.store'), [
            'files' => [UploadedFile::fake()->create('TOEIC-F3-P3-G02.mp3', 10, 'audio/mpeg')],
        ]);

        $stimulus = LearningStimulus::query()->firstOrFail();
        $partId = (int) DB::table('exam_parts')->where('code', 'part-3')->value('id');
        $groupId = DB::table('question_groups')->insertGetId([
            'title' => $stimulus->title,
            'kind' => 'conversation',
            'exam_part_id' => $partId,
            'stimulus_ids' => json_encode([]),
            'metadata' => json_encode(['source' => 'csv_import']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $question = QuestionBankItemFactory::new()->create(['exam_part_id' => $partId, 'question_group_id' => $groupId]);

        $this->get(route('admin.audio.index', ['stimulus' => $stimulus->id]))
            ->assertOk()
            ->assertSee('0 câu hỏi', false)
            ->assertDontSee('Q-'.str_pad((string) $question->id, 5, '0', STR_PAD_LEFT), false);
    }

    public function test_audio_library_can_delete_one_or_many_unused_audio_tracks_and_their_files(): void
    {
        $this->post(route('admin.audio.batch.store'), [
            'files' => [
                UploadedFile::fake()->create('delete-me-01.mp3', 10, 'audio/mpeg'),
                UploadedFile::fake()->create('delete-me-02.mp3', 10, 'audio/mpeg'),
            ],
        ]);

        $stimuli = LearningStimulus::query()->orderBy('id')->get();
        $assets = MediaAsset::query()->orderBy('id')->get();
        $paths = $assets->pluck('path')->all();

        $this->post(route('admin.audio.bulk-delete'), ['select_all' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Đã xóa 2 audio; bỏ qua 0 audio đang được dùng hoặc không còn là bản nháp.');

        $this->assertDatabaseCount('learning_stimuli', 0);
        $this->assertDatabaseCount('media_assets', 0);
        foreach ($paths as $path) {
            Storage::disk('r2')->assertMissing($path);
        }
    }

    public function test_audio_bulk_delete_skips_published_or_linked_tracks(): void
    {
        $this->post(route('admin.audio.batch.store'), [
            'files' => [
                UploadedFile::fake()->create('linked.mp3', 10, 'audio/mpeg'),
                UploadedFile::fake()->create('published.mp3', 10, 'audio/mpeg'),
            ],
        ]);

        $stimuli = LearningStimulus::query()->orderBy('id')->get();
        QuestionBankItemFactory::new()->create(['learning_stimulus_id' => $stimuli[0]->id]);
        $stimuli[1]->update(['status' => 'published']);

        $this->post(route('admin.audio.bulk-delete'), ['ids' => $stimuli->modelKeys()])
            ->assertRedirect()
            ->assertSessionHas('status', 'Đã xóa 0 audio; bỏ qua 2 audio đang được dùng hoặc không còn là bản nháp.');

        $this->assertDatabaseCount('learning_stimuli', 2);
        $this->assertDatabaseCount('media_assets', 2);
    }

    public function test_audio_library_rejects_non_audio_batch_files(): void
    {
        $this->post(route('admin.audio.batch.store'), [
            'files' => [UploadedFile::fake()->create('cover.png', 10, 'image/png')],
        ])->assertSessionHasErrors('files.0');

        $this->assertDatabaseCount('learning_stimuli', 0);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_audio_page_renders_the_unified_resource_library(): void
    {
        $this->get(route('admin.audio.index'))
            ->assertOk()
            ->assertSee('Tài nguyên', false)
            ->assertDontSee('Đưa âm thanh vào đúng ngữ cảnh.', false)
            ->assertSee('data-resource-upload', false)
            ->assertSee('data-resource-delete-form', false)
            ->assertSee('data-resource-filters', false)
            ->assertSee('data-resource-detail', false)
            ->assertSee('Chọn tất cả tài nguyên trên trang', false)
            ->assertSee('Xóa đã chọn', false)
            ->assertSee('name="type" value="audio"', false);
    }

    public function test_audio_library_keeps_pagination_compact_inside_the_library_panel(): void
    {
        foreach (range(1, 136) as $index) {
            MediaAsset::create([
                'created_by' => $this->admin()->id,
                'title' => 'Pagination audio '.$index,
                'disk' => 'public',
                'path' => 'media/pagination-'.$index.'.mp3',
                'mime_type' => 'audio/mpeg',
                'size' => 1024,
                'metadata' => [],
            ]);
        }

        $this->get(route('admin.audio.index'))
            ->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('page=3', false)
            ->assertDontSee('page=7', false)
            ->assertSee('1 - 10 / 136', false);
    }
}
