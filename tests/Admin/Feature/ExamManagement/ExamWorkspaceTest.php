<?php

namespace Tests\Admin\Feature\ExamManagement;

use Database\Admin\Factories\ExamTemplateFactory;
use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Admin\TestCase;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;

class ExamWorkspaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin('content-admin'));
    }

    public function test_list_filters_type_status_search_and_paginate_without_losing_filters(): void
    {
        $type = DB::table('exam_types')->where('code', 'toeic')->value('id');
        ExamTemplateFactory::new()->count(10)->create(['title'=>'TOEIC practice', 'status'=>'published', 'draft_status'=>'approved']);
        ExamTemplateFactory::new()->create(['title'=>'Draft only']);
        ExamTemplateFactory::new()->create(['title'=>'TOEIC archived', 'status'=>'archived']);

        $response = $this->get('/admin/exams?type='.$type.'&status=approved&q=TOEIC&per_page=8');
        $response->assertOk()->assertSee('Danh sách đề thi')->assertSee('Chi tiết đề thi');
        $this->assertSame(10, $response->viewData('exams')->total());
        $this->assertCount(8, $response->viewData('exams'));
        $this->assertStringContainsString('status=approved', $response->viewData('exams')->nextPageUrl());
        $this->get('/admin/exams')->assertOk()->assertViewHas('exams', fn ($exams) => $exams->total() === 11);
        $this->get('/admin/exams?status=archived')->assertOk()->assertViewHas('exams', fn ($exams) => $exams->total() === 11);
        $this->get('/admin/exams?q=no-match')->assertOk()->assertSee('Chưa có đề thi phù hợp');
    }

    public function test_group_passage_is_included_in_filter_and_detail_summary(): void
    {
        $stimulus = LearningStimulus::create(['title'=>'Office notice', 'stimulus_type'=>'reading', 'body'=>'Office relocation', 'status'=>'published']);
        $question = QuestionBankItemFactory::new()->create();
        $group = DB::table('question_groups')->insertGetId(['title'=>'Office group', 'kind'=>'passage', 'exam_part_id'=>$question->exam_part_id, 'stimulus_ids'=>json_encode([$stimulus->id]), 'metadata'=>'{}', 'created_at'=>now(), 'updated_at'=>now()]);
        $revision = ContentRevision::create(['kind'=>'question', 'entity_id'=>$question->id, 'number'=>1, 'status'=>'published', 'payload'=>array_replace($this->item(), ['exam_part_id'=>$question->exam_part_id, 'group_id'=>$group, 'stimulus_ids'=>[]])]);
        $question->forceFill(['published_revision_id'=>$revision->id])->save();
        $exam = ExamTemplateFactory::new()->create(['draft_payload'=>['sections'=>[['exam_part_id'=>$question->exam_part_id,'question_ids'=>[$question->id]]],'policies'=>ExamPublishingService::POLICIES]]);
        ExamTemplateFactory::new()->create();

        $response = $this->get('/admin/exams?resource=reading');
        $response->assertOk();
        $this->assertSame(1, $response->viewData('exams')->total());
        $this->assertSame(1, $response->viewData('summaries')[$exam->id]['passages']);
        $this->get('/admin/exams/'.$exam->id.'/edit')->assertOk()->assertSee('Office notice')->assertSee('data-exam-builder', false)->assertSee('data-preview-bars', false);
        $this->post('/admin/exams/preview-draft', [
            'title'=>'Unsaved preview', 'exam_type_id'=>(string) $exam->exam_type_id, 'mode'=>'mini',
            'sections_json'=>json_encode($exam->draft_payload['sections']),
            'policies_json'=>json_encode(ExamPublishingService::POLICIES), 'generation_json'=>'',
        ])->assertOk()->assertSee('Unsaved preview');
    }

    public function test_creation_and_empty_list_render_with_no_questions(): void
    {
        $this->get('/admin/exams')->assertOk()->assertSee('Chưa có đề thi phù hợp');
        $this->get('/admin/exams/create')->assertOk()->assertSee('Thiết lập đề thi')->assertSee('Xem trước đề thi')->assertSee('data-exam-builder', false);
    }
}
