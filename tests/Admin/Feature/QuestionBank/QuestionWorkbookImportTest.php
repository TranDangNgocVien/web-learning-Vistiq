<?php

namespace Tests\Admin\Feature\QuestionBank;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Admin\TestCase;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionBankCatalog;
use VistiqCore\Services\Assessment\QuestionBank\QuestionWorkbookImportService;

class QuestionWorkbookImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin('content-admin'));
        Storage::fake('r2');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_xlsx_templates_are_downloadable_and_use_one_sheet_per_lesson(): void
    {
        foreach (['listening', 'reading'] as $kind) {
            $this->get(route('admin.questions.import-template', ['template' => $kind.'-xlsx']))
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->assertDownload('vistiq-'.$kind.'-question-template.xlsx');

            $workbook = app(QuestionWorkbookImportService::class)->template($kind);
            $this->assertSame(['HUONG_DAN', strtoupper($kind).'_001', strtoupper($kind).'_002'], array_map(fn ($sheet) => $sheet->getTitle(), $workbook->getAllSheets()));
            $guide = $workbook->getSheetByName('HUONG_DAN');
            $this->assertSame('HƯỚNG DẪN IMPORT CÂU HỎI '.strtoupper($kind), $guide->getCell('A1')->getValue());
            $this->assertStringContainsString('Mỗi sheet dữ liệu', $guide->getCell('B3')->getValue());
            $instructions = collect($guide->rangeToArray('B2:B'.$guide->getHighestDataRow()))->flatten()->implode(' ');
            $this->assertStringContainsString($kind === 'listening' ? 'file audio' : 'reading_title', $instructions);
            $questionSheet = $workbook->getSheet(1);
            $this->assertSame(['exam_type', 'skill', 'exam_part', 'question_type'], array_slice($questionSheet->rangeToArray('A1:D1')[0], 0, 4));
            $this->assertSame('"toeic,ielts"', $questionSheet->getDataValidation('A2')->getFormula1());
            $this->assertSame('"multiple_choice"', $questionSheet->getDataValidation('D2')->getFormula1());
            $this->assertSame('"easy,medium,hard"', $questionSheet->getDataValidation('K2')->getFormula1());
            $workbook->disconnectWorksheets();
        }
    }

    public function test_import_skips_the_instruction_sheet_and_previews_only_lesson_sheets(): void
    {
        $workbook = $this->uploadWorkbook([
            'HUONG_DAN' => [
                ['Mục', 'Hướng dẫn chi tiết'],
                ['Sheet dữ liệu', 'Mỗi sheet dữ liệu là một bài Listening.'],
            ],
            'LISTENING_010' => [
                ['exam_type', 'skill', 'exam_part', 'question_type', 'prompt', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'difficulty', 'explanation'],
                ['toeic', 'listening', 'part-1', 'multiple_choice', 'Which door is open?', 'A', 'B', 'C', 'D', 'B', 'easy', 'Door B is open.'],
            ],
        ]);

        $response = $this->postJson(route('admin.questions.import.preview'), ['kind' => 'listening', 'file' => $workbook])
            ->assertOk()
            ->assertJsonPath('total_questions', 1)
            ->assertJsonCount(1, 'sheets');

        $code = $response->json('sheets.0.code');
        $this->assertMatchesRegularExpression('/^LISTENING_\d{8}_\d{6}_01$/', $code);
        $this->assertNotSame('LISTENING_010', $code);
    }

    public function test_preview_rejects_exam_skill_and_question_type_mismatches(): void
    {
        $workbook = $this->uploadWorkbook([
            'LISTENING_011' => [
                ['exam_type', 'skill', 'exam_part', 'question_type', 'prompt', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'difficulty', 'explanation'],
                ['toeic', 'listening', 'listening', 'multiple_choice', 'Which door is open?', 'A', 'B', 'C', 'D', 'B', 'easy', 'Door B is open.'],
                ['toeic', 'reading', 'part-1', 'sentence_completion', 'Which room is open?', 'A', 'B', 'C', 'D', 'A', 'medium', 'Room A is open.'],
            ],
        ]);

        $this->postJson(route('admin.questions.import.preview'), ['kind' => 'listening', 'file' => $workbook])
            ->assertOk()
            ->assertJsonPath('total_questions', 0)
            ->assertJsonPath('sheets.0.errors.0.row', 2)
            ->assertJsonPath('sheets.0.errors.0.message', 'exam_type không khớp với exam_part.')
            ->assertJsonPath('sheets.0.errors.1.row', 3);
    }

    public function test_listening_preview_reports_sheet_rows_and_confirm_assigns_each_audio_to_its_group(): void
    {
        config(['services.cloudinary' => ['cloud_name' => 'test-cloud', 'api_key' => 'test-key', 'api_secret' => 'test-secret']]);
        Http::fake(['api.cloudinary.com/*' => fn () => Http::response(['public_id' => 'question-'.Str::uuid(), 'secure_url' => 'https://cdn.example.test/question.png', 'asset_id' => (string) Str::uuid()])]);
        $workbook = $this->uploadWorkbook([
            'LISTENING_001' => [
                ['exam_type', 'skill', 'exam_part', 'question_type', 'prompt', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'difficulty', 'explanation'],
                ['toeic', 'listening', 'part-1', 'multiple_choice', 'Which room is open?', 'Room A', 'Room B', 'Room C', 'Room D', 'B', 'easy', 'The sign says B.'],
                ['toeic', 'listening', 'part-1', 'multiple_choice', 'Which desk is open?', 'Desk A', 'Desk B', 'Desk C', '', 'C', 'medium', 'This row has a missing option.'],
            ],
            'LISTENING_002' => [
                ['exam_type', 'skill', 'exam_part', 'question_type', 'prompt', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'difficulty', 'explanation'],
                ['toeic', 'listening', 'part-1', 'multiple_choice', 'Where is the speaker?', 'At home', 'At work', 'At school', 'At a store', 'D', 'medium', 'The speaker says store.'],
            ],
        ]);

        $preview = $this->postJson(route('admin.questions.import.preview'), ['kind' => 'listening', 'file' => $workbook])
            ->assertOk()
            ->assertJsonPath('total_questions', 2)
            ->assertJsonPath('sheets.0.question_count', 1)
            ->assertJsonPath('sheets.0.errors.0.row', 3)
            ->assertJsonPath('sheets.1.question_count', 1)
            ->json();
        $firstCode = $preview['sheets'][0]['code'];
        $secondCode = $preview['sheets'][1]['code'];
        $this->assertMatchesRegularExpression('/^LISTENING_\d{8}_\d{6}_01$/', $firstCode);
        $this->assertMatchesRegularExpression('/^LISTENING_\d{8}_\d{6}_02$/', $secondCode);
        $this->assertStringContainsString('Sheet LISTENING_001 → '.$firstCode, $preview['sheets'][0]['title']);

        $this->postJson(route('admin.questions.import.confirm'), ['token' => $preview['token']])
            ->assertUnprocessable()
            ->assertJsonStructure(['sheets' => [$firstCode, $secondCode]]);

        $response = $this->postJson(route('admin.questions.import.confirm'), [
            'token' => $preview['token'],
            'audio' => [
                $firstCode => UploadedFile::fake()->create('lesson-001.mp3', 20, 'audio/mpeg'),
                $secondCode => UploadedFile::fake()->create('lesson-002.ogg', 20, 'audio/ogg'),
            ],
            // TOEIC Part 1 requires an image for every valid question row.
            'images' => [
                $firstCode => [2 => UploadedFile::fake()->createWithContent('question-001.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6oFEAAAAASUVORK5CYII='))],
                $secondCode => [2 => UploadedFile::fake()->createWithContent('question-002.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC0lEQVR42mP8/x8AAusB9Wl6oFEAAAAASUVORK5CYII='))],
            ],
        ])->assertOk();
        $this->assertSame(2, $response->json('summary.imported'), json_encode($response->json(), JSON_UNESCAPED_UNICODE));
        $response->assertJsonPath('summary.failed', 1);

        $this->assertDatabaseCount('question_bank_items', 2);
        foreach ([$firstCode => 'B', $secondCode => 'D'] as $code => $correctOption) {
            $group = DB::table('question_groups')->get()->first(fn ($row) => (json_decode($row->metadata, true)['lesson_code'] ?? null) === $code);
            $this->assertNotNull($group);
            $assetId = json_decode($group->metadata, true)['audio_id'] ?? null;
            $this->assertNotEmpty($assetId);
            $this->assertTrue(MediaAsset::whereKey($assetId)->exists());
            $question = QuestionBankItem::where('question_group_id', $group->id)->firstOrFail();
            $payload = app(ContentRevisionService::class)->payload('question', $question);
            $this->assertSame($assetId, $payload['metadata']['audio_id']);
            $this->assertSame([$correctOption], $payload['correct_options']);
        }
        $this->assertTrue(app(QuestionBankCatalog::class)->catalog()['entries']->contains(fn (array $entry) => $entry['code'] === $firstCode));
        $this->assertSame($response->json('redirect'), route('admin.questions.listening'));
    }

    public function test_reading_workbook_creates_one_coded_passage_and_links_all_valid_questions(): void
    {
        $workbook = $this->uploadWorkbook([
            'READING_001' => [
                ['exam_type', 'skill', 'exam_part', 'question_type', 'prompt', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'difficulty', 'explanation', 'reading_title', 'reading_body'],
                ['toeic', 'reading', 'part-7', 'multiple_choice', 'When does training start?', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'A', 'easy', 'It begins Monday.', 'Training notice', '<p>Training starts Monday.</p>'],
                ['toeic', 'reading', 'part-7', 'multiple_choice', 'Where is the room?', 'Room A', 'Room B', 'Room C', 'Room D', 'B', 'medium', 'The notice names room B.', '', ''],
            ],
        ]);

        $preview = $this->postJson(route('admin.questions.import.preview'), ['kind' => 'reading', 'file' => $workbook])
            ->assertOk()->assertJsonPath('total_questions', 2)->json();
        $this->postJson(route('admin.questions.import.confirm'), ['token' => $preview['token']])
            ->assertOk()->assertJsonPath('summary.imported', 2)->assertJsonPath('summary.failed', 0);

        $stimulus = LearningStimulus::firstOrFail();
        $payload = app(ContentRevisionService::class)->payload('stimulus', $stimulus);
        $this->assertSame('READING_001', $payload['metadata']['lesson_code']);
        $this->assertSame('Training notice', $payload['title']);
        $this->assertSame(2, QuestionBankItem::where('learning_stimulus_id', $stimulus->id)->count());
        $this->assertTrue(app(QuestionBankCatalog::class)->catalog()['entries']->contains(fn (array $entry) => $entry['code'] === 'READING_001'));
    }

    /** @param array<string,list<list<string>>> $worksheets */
    private function uploadWorkbook(array $worksheets): UploadedFile
    {
        $workbook = new Spreadsheet;
        $first = true;
        foreach ($worksheets as $name => $rows) {
            $sheet = $first ? $workbook->getActiveSheet() : $workbook->createSheet();
            $first = false;
            $sheet->setTitle($name);
            $sheet->fromArray($rows, null, 'A1');
        }
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vistiq-'.Str::uuid().'.xlsx';
        (new Xlsx($workbook))->save($path);
        $workbook->disconnectWorksheets();
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, basename($path), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', UPLOAD_ERR_OK, true);
    }
}
