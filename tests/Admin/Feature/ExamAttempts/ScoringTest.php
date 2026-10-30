<?php

namespace Tests\Admin\Feature\ExamAttempts;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Services\Assessment\Attempts\AttemptService;
use VistiqCore\Services\Assessment\Attempts\GradingService;
use VistiqCore\Services\Assessment\Attempts\ScoringService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;

class ScoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function responses(): array
    {
        return [
            'option key' => ['multiple_choice', [], ['options' => ['B']], 1, 1],
            'wrong key' => ['multiple_choice', [], ['options' => ['A']], 0, 1],
            'multiple partial' => ['multiple_response', [], ['options' => ['B']], 1, 2],
            'mapping' => ['matching', ['answer_map' => ['1' => 'A', '2' => 'B']], ['matching' => ['1' => 'A', '2' => 'C']], 1, 2],
            'case and spaces' => ['short_answer', ['max_words' => 2, 'max_numbers' => 1], ['text' => '  New   York  '], 1, 1],
            'word limit' => ['short_answer', ['max_words' => 1], ['text' => 'New York'], 0, 1],
            'no spell correction' => ['short_answer', [], ['text' => 'New Yrok'], 0, 1],
            'number limit' => ['short_answer', ['max_words' => 2, 'max_numbers' => 0], ['text' => '12'], 0, 1],
            'multiple blanks' => ['form_completion', ['blanks' => ['1' => ['accepted_answers' => ['New York']], '2' => ['accepted_answers' => ['12']]]], ['blanks' => ['1' => 'new york']], 1, 2],
        ];
    }

    #[DataProvider('responses')]
    public function test_objective_units_and_limits(string $type, array $metadata, array $response, int $points, int $units): void
    {
        $item = $this->item(['type' => $type, 'metadata' => $metadata, 'accepted_answers' => ['New York', '12'], 'correct_options' => $type === 'multiple_response' ? ['A', 'B'] : ['B']]);
        $grade = app(ScoringService::class)->gradeObjective($item, $response);
        $this->assertSame($points, $grade['points']);
        $this->assertSame($units, $grade['units']);
    }

    public function test_writing_task_two_has_double_weight_and_regrade_is_audited(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $items = [$this->item(['key' => 'q1', 'type' => 'essay', 'skill' => 'writing', 'part' => 'writing-task-1', 'weight' => 1]), $this->item(['key' => 'q2', 'type' => 'essay', 'skill' => 'writing', 'part' => 'writing-task-2', 'weight' => 2])];
        $attempt = app(AttemptService::class)->start($this->publishedExam($items), User::factory()->create());
        app(AttemptService::class)->submit($attempt);
        $this->assertSame('pending_grading', $attempt->fresh()->status);
        $this->actingAs($this->admin('reviewer'));
        $answers = $attempt->answers()->orderBy('id')->get();
        $grading = app(GradingService::class);
        $grading->grade($answers[0], ['criteria' => array_fill_keys(QuestionRegistry::WRITING_CRITERIA, 6), 'feedback' => 'Task 1']);
        $this->assertSame('pending_grading', $attempt->fresh()->status);
        $grading->grade($answers[1], ['criteria' => array_fill_keys(QuestionRegistry::WRITING_CRITERIA, 9), 'feedback' => 'Task 2']);
        $details = json_decode(DB::table('attempt_results')->value('details'), true);
        $this->assertEquals(8, $details['bands']['writing']);
        $this->assertSame('completed', $attempt->fresh()->status);
        $grading->grade($answers[0], ['criteria' => array_fill_keys(QuestionRegistry::WRITING_CRITERIA, 7.5), 'feedback' => 'Revised', 'reason' => 'Correct rubric assessment', 'grade_token' => hash('sha256', json_encode($answers[0]->fresh()->grade, JSON_THROW_ON_ERROR))]);
        $this->assertSame(3, DB::table('audit_logs')->where('action', 'answer.grade')->count());
    }
}
