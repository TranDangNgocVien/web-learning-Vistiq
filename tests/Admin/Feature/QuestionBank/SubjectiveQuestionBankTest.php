<?php

namespace Tests\Admin\Feature\QuestionBank;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Models\QuestionDifficulty;
use VistiqCore\Models\QuestionType;
use VistiqCore\Services\Assessment\QuestionBank\SubjectiveQuestionWorkbookService;

class SubjectiveQuestionBankTest extends TestCase
{
    use LazilyRefreshDatabase;

    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin('content-admin'));
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) @unlink($file);
        parent::tearDown();
    }

    private function attributes(string $skill, array $overrides = []): array
    {
        return array_replace([
            'exam_part_id' => ExamPart::where('code', $skill === 'writing' ? 'writing-task-2' : 'speaking-part-2')->value('id'),
            'question_type_id' => QuestionType::where('code', $skill === 'writing' ? 'essay' : 'speaking')->value('id'),
            'question_difficulty_id' => QuestionDifficulty::where('code', 'medium')->value('id'),
            'prompt' => 'A unique '.$skill.' question about education.',
            'metadata' => ['rubric' => SubjectiveQuestionWorkbookService::defaultRubric($skill)],
        ], $overrides);
    }

    private function question(string $skill, array $overrides = []): QuestionBankItem
    {
        $id = $this->postJson(route('admin.questions.store'), $this->attributes($skill, $overrides))->assertSuccessful()->json('id');

        return QuestionBankItem::findOrFail($id);
    }

    private function workbookFile(Spreadsheet $workbook): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'vistiq-subjective-');
        $this->temporaryFiles[] = $path;
        (new Xlsx($workbook))->save($path);
        $workbook->disconnectWorksheets();

        return new UploadedFile($path, 'questions.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_both_banks_have_visible_navigation_and_contextual_editors(): void
    {
        foreach (['writing', 'speaking'] as $skill) {
            $this->get(route('admin.questions.'.$skill))->assertOk()->assertSee('Câu hỏi '.ucfirst($skill))->assertSee(route('admin.questions.create', ['skill' => $skill]), false);
            $this->get(route('admin.questions.create', ['skill' => $skill]))->assertOk()
                ->assertSee('Tạo câu hỏi '.ucfirst($skill))
                ->assertDontSee("@can('content.edit')", false)
                ->assertDontSee('@endcan', false)
                ->assertSee('name="metadata[rubric][grammar]"', false)
                ->assertDontSee('name="metadata[rubric_text]"', false)
                ->assertViewHas('parts', fn ($parts) => $parts->isNotEmpty() && $parts->every(fn ($part) => $part->skill->code === $skill));
            $question = $this->question($skill);
            $this->get(route('admin.questions.edit', $question))->assertOk()->assertViewHas('editorSkill', $skill)->assertSee(route('admin.questions.'.$skill), false);
        }
    }

    public function test_filters_and_detail_never_leak_questions_from_the_other_skill(): void
    {
        $writing = $this->question('writing');
        $speaking = $this->question('speaking');
        $this->get(route('admin.questions.writing'))->assertOk()->assertSee($writing->prompt)->assertDontSee($speaking->prompt);
        $this->get(route('admin.questions.writing', ['q'=>'no-match']))->assertOk()->assertViewHas('questions', fn ($questions) => $questions->total() === 0);
        $this->get(route('admin.questions.writing', ['part'=>'writing-task-1']))->assertOk()->assertViewHas('questions', fn ($questions) => $questions->total() === 0);
        $this->get(route('admin.questions.writing', ['difficulty'=>'hard']))->assertOk()->assertViewHas('questions', fn ($questions) => $questions->total() === 0);
        $this->get(route('admin.questions.writing', ['question'=>$speaking->id]))->assertNotFound();
        $this->get(route('admin.questions.writing', ['question'=>$writing->id]))->assertOk()->assertSee('Đáp ứng yêu cầu đề bài');
    }

    public function test_templates_round_trip_into_drafts_and_confirm_is_idempotent(): void
    {
        foreach (['writing'=>2, 'speaking'=>3] as $skill=>$count) {
            $this->get(route('admin.questions.import-template', ['template'=>$skill.'-xlsx']))->assertOk()->assertDownload('vistiq-'.$skill.'-question-template.xlsx');
            $file = $this->workbookFile(app(SubjectiveQuestionWorkbookService::class)->template($skill));
            $preview = $this->postJson(route('admin.questions.import.preview'), ['kind'=>$skill, 'file'=>$file])->assertOk()->assertJsonPath('can_import', true)->assertJsonPath('total_questions', $count)->json();
            $before = QuestionBankItem::count();
            $this->postJson(route('admin.questions.import.confirm'), ['token'=>$preview['token']])->assertOk()->assertJsonPath('summary.imported', $count)->assertJsonPath('redirect', route('admin.questions.'.$skill));
            $this->postJson(route('admin.questions.import.confirm'), ['token'=>$preview['token']])->assertOk()->assertJsonPath('summary.imported', $count);
            $this->assertSame($before + $count, QuestionBankItem::count());
            $question = QuestionBankItem::latest('id')->first();
            $this->assertSame('draft', $question->draftRevision->status);
            $this->assertSame(SubjectiveQuestionWorkbookService::criteria($skill), array_keys($question->draftRevision->payload['metadata']['rubric']));
        }
    }

    public function test_import_rejects_the_whole_workbook_when_any_row_is_invalid(): void
    {
        $workbook = app(SubjectiveQuestionWorkbookService::class)->template('writing');
        $workbook->getSheet(2)->setCellValue('E2', '');
        $preview = $this->postJson(route('admin.questions.import.preview'), ['kind'=>'writing', 'file'=>$this->workbookFile($workbook)])->assertOk()->assertJsonPath('can_import', false)->assertJsonPath('sheets.1.errors.0.row', 2)->json();
        $this->postJson(route('admin.questions.import.confirm'), ['token'=>$preview['token']])->assertUnprocessable();
        $this->assertSame(0, QuestionBankItem::count());
    }

    public function test_import_rejects_cross_skill_classification_and_missing_rubric(): void
    {
        $workbook = app(SubjectiveQuestionWorkbookService::class)->template('speaking');
        $workbook->getSheet(1)->setCellValue('B2', 'writing');
        $workbook->getSheet(2)->setCellValue('O2', '');
        $this->postJson(route('admin.questions.import.preview'), ['kind'=>'speaking', 'file'=>$this->workbookFile($workbook)])->assertOk()->assertJsonPath('can_import', false)
            ->assertJsonPath('sheets.0.errors.0.message', 'skill phải là speaking.')
            ->assertJsonPath('sheets.1.errors.0.message', 'Cần nhập đủ hướng dẫn cho bốn tiêu chí chấm.');
    }

    public function test_review_checks_scope_and_versions_and_submits_only_the_chosen_bank(): void
    {
        $writing = $this->question('writing');
        $speaking = $this->question('speaking');
        $data = ['ids'=>[$writing->id], 'revision_counters'=>[$writing->id=>$writing->revision_counter]];
        $this->post(route('admin.questions.speaking.review'), $data)->assertUnprocessable();
        $stale = array_replace($data, ['revision_counters'=>[$writing->id=>0]]);
        $this->post(route('admin.questions.writing.review'), $stale)->assertStatus(409);
        $this->post(route('admin.questions.writing.review'), $data)->assertRedirect();
        $this->assertSame('review', $writing->fresh()->draftRevision->status);
        $this->assertSame('draft', $speaking->fresh()->draftRevision->status);
        $this->get(route('admin.questions.writing', ['status'=>'review']))->assertOk()->assertViewHas('questions', fn ($questions) => $questions->total() === 1);
    }

    public function test_review_all_rolls_back_when_a_draft_is_incomplete_and_detects_stale_confirmation(): void
    {
        $complete = $this->question('writing');
        $incomplete = $this->question('writing', ['prompt'=>'']);
        $signature = $this->get(route('admin.questions.writing'))->assertOk()->viewData('draftSignature');
        $this->from(route('admin.questions.writing'))->post(route('admin.questions.writing.review'), ['all_drafts'=>1, 'draft_signature'=>$signature])->assertSessionHasErrors('review');
        $this->assertSame('draft', $complete->fresh()->draftRevision->status);
        $this->question('writing', ['prompt'=>'A newly added question.']);
        $this->from(route('admin.questions.writing'))->post(route('admin.questions.writing.review'), ['all_drafts'=>1, 'draft_signature'=>$signature])->assertSessionHasErrors('draft_signature');
    }

    public function test_permissions_and_preview_tokens_are_enforced_on_the_server(): void
    {
        $file = $this->workbookFile(app(SubjectiveQuestionWorkbookService::class)->template('speaking'));
        $token = $this->postJson(route('admin.questions.import.preview'), ['kind'=>'speaking', 'file'=>$file])->assertOk()->json('token');
        $this->actingAs($this->admin('content-admin'));
        $this->postJson(route('admin.questions.import.confirm'), ['token'=>$token])->assertNotFound();
        $this->actingAs(User::factory()->create(), 'admin');
        foreach (['writing', 'speaking'] as $skill) {
            $this->get(route('admin.questions.'.$skill))->assertForbidden();
            $this->post(route('admin.questions.'.$skill.'.review'), ['all_drafts'=>1])->assertForbidden();
        }
        $this->get(route('admin.questions.import-template', ['template'=>'writing-xlsx']))->assertForbidden();
        $this->postJson(route('admin.questions.import.confirm'), ['token'=>$token])->assertForbidden();
    }
}
