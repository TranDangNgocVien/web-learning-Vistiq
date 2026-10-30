<?php

namespace Tests\Admin\Feature\QuestionBank;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Str;
use Tests\Admin\TestCase;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;

class ReadingCsvImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin('content-admin'));
    }

    private function fields(string $csv, array $extra = []): array
    {
        return $extra + [
            'file' => UploadedFile::fake()->createWithContent('readings.csv', $csv),
            'default_question_type' => DB::table('question_types')->where('code', 'multiple_choice')->value('id'),
            'default_difficulty' => DB::table('question_difficulties')->where('code', 'medium')->value('id'),
            'default_status' => 'draft', 'offset' => 0, 'return_to' => 'readings',
        ];
    }

    private function csv(array $overrides): string
    {
        $base = ['reading_key' => 'ARTICLE-1', 'reading_title' => 'Training notice', 'reading_body' => '<p>Training starts on Monday.</p>',
            'exam_part' => 'part-7', 'prompt' => 'When does training start?', 'option_a' => 'Monday', 'option_b' => 'Tuesday', 'option_c' => 'Wednesday', 'option_d' => 'Thursday', 'correct_option' => 'A',
            'stimulus' => '', 'audio_group' => '', 'payload_json' => ''];
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, array_keys($base), escape: '');
        foreach ($overrides as $row) {
            fputcsv($stream, array_values(array_replace($base, $row)), escape: '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    public function test_csv_template_remains_available_by_url_but_csv_controls_are_hidden_from_reading_library(): void
    {
        $this->get(route('admin.questions.import-template', ['template' => 'readings']))
            ->assertDownload('vistiq-readings-with-questions-template.csv');
        $this->get('/admin/readings')->assertOk()
            ->assertDontSee('Mẫu CSV kèm câu hỏi')
            ->assertDontSee('Import bài đọc CSV')
            ->assertSee('Import Reading từ workbook .xlsx')
            ->assertSee('Tải mẫu .xlsx');
        $this->get('/admin/questions')->assertRedirect(route('admin.questions.listening'));
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(resource_path('import-templates/questions/vistiq-question-bank-templates.zip')));
        $normalize = fn (string $csv): string => str_replace(["\r\n", "\r"], "\n", $csv);
        $this->assertSame($normalize(file_get_contents(resource_path('import-templates/questions/vistiq-readings-with-questions-template.csv'))), $normalize($zip->getFromName('vistiq-readings-with-questions-template.csv')));
        $zip->close();
    }

    public function test_sample_import_creates_three_articles_and_nine_linked_questions(): void
    {
        $csv = file_get_contents(resource_path('import-templates/questions/vistiq-readings-with-questions-template.csv'));
        $this->post('/admin/questions/import', $this->fields($csv))->assertRedirect(route('admin.readings.index'))
            ->assertSessionHas('import_summary', ['total' => 9, 'imported' => 9, 'failed' => 0]);
        $this->get('/admin/readings')->assertSee('Đã nhập 9 câu hỏi theo bài đọc');
        $this->assertDatabaseCount('learning_stimuli', 3);
        $this->assertDatabaseCount('question_groups', 3);
        $this->assertDatabaseCount('question_bank_items', 9);
        $this->get('/admin/readings')->assertOk()->assertViewHas('readings', fn ($entries) => $entries->total() === 3);

        foreach (LearningStimulus::all() as $reading) {
            $payload = app(ContentRevisionService::class)->payload('stimulus', $reading);
            $questions = QuestionBankItem::where('learning_stimulus_id', $reading->id)->orderBy('group_order')->get();
            $this->assertSame('draft', $reading->status);
            $this->assertSame(1, $reading->revision_counter);
            $this->assertSame(range(0, $questions->count() - 1), $questions->pluck('group_order')->all());
            $this->assertSame([$payload['metadata']['question_group_id']], $questions->pluck('question_group_id')->unique()->all());
            $part = DB::table('exam_parts')->find($payload['metadata']['exam_part_id']);
            $this->assertSame($part->code === 'part-6' ? 'paragraph' : 'passage', $payload['metadata']['content_kind']);
            $this->assertCount(match ($part->code) {
                'part-6' => 4, 'part-7' => 3, default => 2
            }, $questions);
            foreach ($questions as $question) {
                $q = app(ContentRevisionService::class)->payload('question', $question);
                $this->assertSame([$reading->id], $q['stimulus_ids']);
                $this->assertSame($payload['metadata']['question_group_id'], $q['group_id']);
                $this->assertNotEmpty($q['explanation']);
            }
            $this->get('/admin/questions/bank-preview/stimulus/'.$reading->id)->assertOk()->assertSee('Câu hỏi liên quan ('.$questions->count().')');
        }
        $short = QuestionBankItem::whereHas('questionType', fn ($q) => $q->where('code', 'short_answer'))->firstOrFail();
        $this->assertSame(['car park'], app(ContentRevisionService::class)->payload('question', $short)['accepted_answers']);
        $trueFalse = QuestionBankItem::whereHas('questionType', fn ($q) => $q->where('code', 'true_false_not_given'))->firstOrFail();
        $this->assertSame(['FALSE'], app(ContentRevisionService::class)->payload('question', $trueFalse)['correct_options']);
    }

    public function test_article_and_order_are_preserved_across_csv_batches(): void
    {
        $rows = [[]];
        for ($i = 1; $i < 51; $i++) {
            $rows[] = ['reading_key' => 'article-1', 'reading_title' => '', 'reading_body' => '', 'prompt' => 'Question '.$i];
        }
        $csv = $this->csv($rows);
        $token = (string) Str::uuid();
        $this->postJson('/admin/questions/import', $this->fields($csv, ['import_key' => $token]))
            ->assertOk()->assertJsonPath('next_offset', 50);
        $this->postJson('/admin/questions/import', $this->fields($csv, ['import_key' => $token, 'offset' => 50]))
            ->assertOk()->assertJsonPath('summary.imported', 51)->assertJsonPath('summary.failed', 0)
            ->assertJsonPath('redirect', route('admin.readings.index'));
        $this->assertDatabaseCount('learning_stimuli', 1);
        $this->assertDatabaseCount('question_groups', 1);
        $this->assertSame(range(0, 50), QuestionBankItem::orderBy('group_order')->pluck('group_order')->all());
        $this->assertCount(1, QuestionBankItem::pluck('learning_stimulus_id')->unique());
    }

    public function test_invalid_rows_do_not_create_articles_or_consume_question_order(): void
    {
        $csv = $this->csv([
            ['correct_option' => 'Z'], [], ['reading_title' => 'Conflicting title'], ['exam_part' => 'part-6'],
            ['reading_title' => '', 'reading_body' => '', 'prompt' => 'Another valid question'], ['reading_key' => ''],
            ['reading_key' => 'LISTENING', 'exam_part' => 'part-3'], ['reading_key' => 'NO-BODY', 'reading_body' => ''],
        ]);
        $this->post('/admin/questions/import', $this->fields($csv))
            ->assertSessionHas('import_summary', ['total' => 8, 'imported' => 2, 'failed' => 6])
            ->assertSessionHas('import_errors', fn ($errors) => str_contains(implode(' ', $errors), 'reading_key'));
        $this->assertDatabaseCount('learning_stimuli', 1);
        $this->assertDatabaseCount('question_groups', 1);
        $this->assertSame([0, 1], QuestionBankItem::orderBy('group_order')->pluck('group_order')->all());
    }

    public function test_reading_import_requires_article_columns_and_sanitizes_html(): void
    {
        $this->post('/admin/questions/import', $this->fields('exam_part,prompt'))
            ->assertRedirect(route('admin.readings.index'))->assertSessionHasErrors('file');
        $this->postJson('/admin/questions/import', $this->fields('exam_part,prompt'))
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->post('/admin/questions/import', $this->fields($this->csv([['reading_body' => '<p>Safe content.</p><script>alert(1)</script>']])))
            ->assertSessionHas('import_summary', ['total' => 1, 'imported' => 1, 'failed' => 0]);
        $reading = LearningStimulus::firstOrFail();
        $this->assertStringNotContainsString('<script', $reading->body);
        $this->assertStringNotContainsString('<script', app(ContentRevisionService::class)->payload('stimulus', $reading)['body']);
    }

    public function test_viewer_cannot_import_or_download_editing_templates(): void
    {
        $this->actingAs($this->admin('teacher'), 'admin');
        $this->post('/admin/questions/import', $this->fields($this->csv([[]])))->assertForbidden();
        $this->get(route('admin.questions.import-template', ['template' => 'readings']))->assertForbidden();
        $this->assertDatabaseCount('learning_stimuli', 0);
    }

    public function test_bank_import_supports_new_articles_and_existing_stimulus_links(): void
    {
        $this->post('/admin/questions/import', $this->fields($this->csv([[]]), ['return_to' => 'questions']))
            ->assertRedirect(route('admin.readings.index'))->assertSessionHas('import_summary.imported', 1);
        $reading = LearningStimulus::firstOrFail();
        $conflict = $this->csv([['reading_key' => 'NEW', 'stimulus' => (string) $reading->id]]);
        $this->post('/admin/questions/import', $this->fields($conflict))->assertSessionHas('import_summary.failed', 1);
        $csv = $this->csv([['reading_key' => '', 'reading_title' => '', 'reading_body' => '', 'stimulus' => (string) $reading->id]]);
        $this->post('/admin/questions/import', $this->fields($csv, ['return_to' => 'questions']))->assertSessionHas('import_summary.imported', 1);
        $this->assertDatabaseCount('learning_stimuli', 1);
        $this->assertCount(2, QuestionBankItem::where('learning_stimulus_id', $reading->id)->get());
    }
}
