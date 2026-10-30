<?php

namespace Tests\Admin\Feature\ContentApproval;

use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Admin\TestCase;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;

class ReviewSafetyTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_legacy_endpoint_requires_the_reviewed_payload_token_and_rejects_stale_content(): void
    {
        $this->actingAs($author = $this->admin('content-admin'));
        $question = QuestionBankItemFactory::new()->create();
        $service = app(ContentRevisionService::class);
        $revision = $service->save('question', $question, $this->item(['exam_part_id' => $question->exam_part_id]), 0);
        $revision->update(['status' => 'review']);
        $this->actingAs($this->admin('reviewer'));
        $url = '/admin/content/question/'.$question->id.'/approve';
        $this->postJson($url)->assertConflict();
        $data = $this->reviewData('question', $question->id);
        $revision->update(['payload' => array_replace($revision->payload, ['prompt' => 'Changed after preview'])]);
        $this->postJson($url, $data)->assertConflict();
        $this->postJson($url, $this->reviewData('question', $question->id))->assertOk()->assertJsonPath('status', 'approved');
        $this->postJson($url, $data)->assertConflict();
        $this->assertNull($question->fresh()->published_revision_id);
    }

    public function test_standard_publish_cannot_bypass_approval_even_for_super_admin(): void
    {
        $this->actingAs($this->admin());
        $question = QuestionBankItemFactory::new()->create();
        app(ContentRevisionService::class)->save('question', $question, $this->item(['exam_part_id' => $question->exam_part_id]), 0);
        $this->postJson('/admin/content/question/'.$question->id.'/publish')->assertConflict();
        $this->assertNull($question->fresh()->published_revision_id);
    }

    public function test_audio_preview_supports_public_paths_and_https_but_not_unsafe_url_schemes(): void
    {
        foreach (['https://example.test/audio.wav', 'audio/example.wav'] as $path) {
            $html = view('pages.admin.assessment.content-approval.payload', ['content' => ['stimulus_type' => 'audio', 'asset_path' => $path, 'metadata' => []]])->render();
            $this->assertStringContainsString('<audio controls', $html);
            $this->assertStringContainsString($path, $html);
        }
        $html = view('pages.admin.assessment.content-approval.payload', ['content' => ['stimulus_type' => 'audio', 'asset_path' => 'javascript://alert(1)', 'metadata' => []]])->render();
        $this->assertStringNotContainsString('<audio', $html);
    }

    public function test_queue_filters_exam_type_and_part_and_displays_blockers(): void
    {
        $this->actingAs($this->admin('content-admin'));
        $part = \Illuminate\Support\Facades\DB::table('exam_parts')->where('code', 'part-5')->first();
        $question = QuestionBankItemFactory::new()->create(['exam_part_id' => $part->id]);
        $revision = app(ContentRevisionService::class)->save('question', $question, $this->item(['exam_part_id' => $part->id]), 0);
        $revision->update(['status' => 'review']);
        $exam = \Database\Admin\Factories\ExamTemplateFactory::new()->create(['title' => 'Queue exam filter', 'draft_status' => 'review', 'draft_payload' => ['sections' => [['exam_part_id' => $part->id, 'question_ids' => [$question->id]]], 'policies' => \VistiqCore\Services\Assessment\Exams\ExamPublishingService::POLICIES]]);
        $this->actingAs($this->admin('reviewer'));
        $response = $this->get('/admin/content-approval?part='.$part->id.'&exam_type='.$part->exam_type_id)->assertOk()->assertSee('Queue exam filter')->assertSee('Kiểm tra nội dung');
        $this->assertSame(2, $response->viewData('entries')->total());
        $this->get('/admin/content-approval?kind=exam&part='.$part->id)->assertOk()->assertSee('lỗi cần sửa');
        $otherPart = \Illuminate\Support\Facades\DB::table('exam_parts')->where('id', '!=', $part->id)->value('id');
        $this->get('/admin/content-approval?part='.$otherPart)->assertOk()->assertDontSee('Queue exam filter');
    }
}
