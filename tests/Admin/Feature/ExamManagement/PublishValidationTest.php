<?php

namespace Tests\Admin\Feature\ExamManagement;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Admin\TestCase;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;

class PublishValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_full_toeic_blocks_wrong_counts_missing_duration_and_scale(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $snapshot = $this->publishedExam()->publishedRevision->payload;
        $snapshot['mode'] = 'full';
        $snapshot['policies']['duration_minutes'] = 0;
        $errors = implode(' ', app(ExamPublishingService::class)->problems($snapshot));
        $this->assertStringContainsString('Part 1', $errors);
        $this->assertStringContainsString('120', $errors);
        $this->assertStringContainsString('bảng quy đổi', $errors);
    }

    public function test_full_simulation_forces_non_pausable_auto_submit_and_delayed_answer_review(): void
    {
        $policies = ExamPublishingService::normalizePolicies('full', [
            'pause_allowed' => true,
            'auto_submit' => false,
            'answer_review' => 'after_each',
            'explanation_review' => 'after_each',
        ]);

        $this->assertFalse($policies['pause_allowed']);
        $this->assertTrue($policies['auto_submit']);
        $this->assertSame('after_submit', $policies['answer_review']);
        $this->assertSame('after_submit', $policies['explanation_review']);
    }

    public function test_full_ielts_requires_variant_and_all_four_skills(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $snapshot = $this->publishedExam()->publishedRevision->payload;
        $snapshot['mode'] = 'full';
        $snapshot['exam_type'] = 'ielts';
        $snapshot['variant'] = null;
        $errors = implode(' ', app(ExamPublishingService::class)->problems($snapshot));
        $this->assertStringContainsString('variant', $errors);
        $this->assertStringContainsString('40', $errors);
        $this->assertStringContainsString('Writing', $errors);
        $this->assertStringContainsString('Speaking', $errors);
    }

    public function test_dynamic_types_require_answers_and_four_rubric_criteria(): void
    {
        $this->expectException(ValidationException::class);
        app(QuestionRegistry::class)->validate($this->item(['type' => 'essay', 'exam_type' => 'ielts', 'skill' => 'writing', 'metadata' => ['rubric' => ['grammar' => 'Only one']]]));
    }

    public function test_conversion_tables_must_cover_every_raw_score(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $snapshot = $this->publishedExam()->publishedRevision->payload;
        $snapshot['scoring'] = ['configuration' => ['tables' => ['reading' => [0 => 5]]]];
        $this->assertStringContainsString('raw 1', implode(' ', app(ExamPublishingService::class)->problems($snapshot)));
    }
}
