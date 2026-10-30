<?php

namespace Tests\Admin\Feature\ContentApproval;

use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Tests\Admin\TestCase;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;

class ContentRevisionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_editor_reviewer_and_publisher_complete_lifecycle_and_published_revision_is_immutable(): void
    {
        $editor = $this->admin('content-admin');
        $reviewer = $this->admin('reviewer');
        $publisher = $this->admin();
        $question = QuestionBankItemFactory::new()->create();
        $this->actingAs($editor);
        $service = app(ContentRevisionService::class);
        $service->save('question', $question, $this->item(['exam_part_id' => $question->exam_part_id]), 0);
        $this->postJson('/admin/content/question/'.$question->id.'/review')->assertOk();
        $this->postJson('/admin/content/question/'.$question->id.'/publish')->assertForbidden();
        $this->actingAs($reviewer)->postJson('/admin/content/question/'.$question->id.'/approve', $this->reviewData('question', $question->id))->assertOk();
        $this->actingAs($publisher)->postJson('/admin/content/question/'.$question->id.'/publish')->assertOk();
        $published = $question->fresh()->published_revision_id;
        $this->actingAs($editor);
        $service->save('question', $question->fresh(), $this->item(['prompt' => 'Edited prompt', 'exam_part_id' => $question->exam_part_id]), 1);
        $this->assertSame('Select the answer.', ContentRevision::find($published)->payload['prompt']);
        $this->assertSame('draft', ContentRevision::find($question->fresh()->draft_revision_id)->status);
        $this->postJson('/admin/content/question/'.$question->id.'/publish')->assertForbidden();
    }

    public function test_reject_requires_reason_and_stale_save_returns_conflict(): void
    {
        $question = QuestionBankItemFactory::new()->create();
        $this->actingAs($this->admin());
        $service = app(ContentRevisionService::class);
        $service->save('question', $question, $this->item(['exam_part_id' => $question->exam_part_id]), 0);
        $this->postJson('/admin/content/question/'.$question->id.'/review')->assertOk();
        $this->postJson('/admin/content/question/'.$question->id.'/reject', $this->reviewData('question', $question->id))->assertUnprocessable();
        $this->postJson('/admin/content/question/'.$question->id.'/reject', $this->reviewData('question', $question->id) + ['note' => 'Fix wording'])->assertOk();
        $this->putJson('/admin/questions/'.$question->id, ['prompt' => 'Other', 'exam_part_id' => $question->exam_part_id, 'question_type_id' => $question->question_type_id, 'question_difficulty_id' => $question->question_difficulty_id, 'revision_counter' => 0])->assertConflict();
    }

    public function test_editing_legacy_published_question_pins_old_payload_before_saving_new_draft(): void
    {
        $question = QuestionBankItemFactory::new()->create(['prompt' => 'Legacy prompt']);
        $question->learningItem->forceFill(['status' => 'published'])->save();
        $question->options()->create(['option_key' => 'A', 'content' => 'Old', 'is_correct' => true]);
        $this->actingAs($this->admin('content-admin'));
        $service = app(ContentRevisionService::class);
        $service->save('question', $question, $this->item(['prompt' => 'Draft prompt', 'exam_part_id' => $question->exam_part_id]), 0);
        $question->refresh();
        $this->assertSame('Legacy prompt', $service->published('question', $question)->payload['prompt']);
        $this->assertSame('Draft prompt', $service->payload('question', $question)['prompt']);
        $this->assertSame(2, $question->revision_counter);
    }

    public function test_html_sanitizer_removes_active_content_and_retains_tables(): void
    {
        $html = app(QuestionRegistry::class)->sanitize('<h2>Heading</h2><script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">link</a><table><tr><td>Cell</td></tr></table>');
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('<table>', $html);
    }

    public function test_preview_creates_no_attempt_or_activity_or_result(): void
    {
        $this->actingAs($this->admin());
        $exam = $this->publishedExam();
        $this->get('/admin/exams/'.$exam->id.'/preview')->assertOk();
        $this->assertDatabaseCount('exam_attempts', 0);
        $this->assertDatabaseCount('attempt_results', 0);
        $this->assertSame(0, DB::table('user_activities')->where('action', 'like', 'exam.%')->count());
    }
}
