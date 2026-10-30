<?php

namespace Tests\Admin\Feature\Analytics;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Tests\Admin\TestCase;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\User;
use VistiqCore\Services\Analytics\AnalyticsService;
use VistiqCore\Services\Analytics\ExportService;
use VistiqCore\Services\Analytics\LeaderboardService;

class AnalyticsMetricsAndLeaderboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_kpi_formulas_handle_zero_attempts_without_error(): void
    {
        $service = app(AnalyticsService::class);
        $metrics = $service->dashboard();

        $this->assertSame(0, $metrics['attempts']);
        $this->assertSame(0, $metrics['completed']);
        $this->assertEquals(0, $metrics['completion_rate']);
        $this->assertEquals(0, $metrics['drop_off']);
        $this->assertNull($metrics['avg_score']);
    }

    public function test_export_service_escapes_csv_formula_injection(): void
    {
        $dangerousPayloads = [
            '=SUM(A1:A10)',
            '+12345',
            '-cmd|/c calc!A0',
            '@evil_formula()',
            "\tTAB_INJECTION",
            "\rCR_INJECTION",
        ];

        foreach ($dangerousPayloads as $payload) {
            $escaped = ExportService::sanitize($payload);
            $this->assertStringStartsWith("'", $escaped);
        }

        // Normal strings should remain unchanged
        $this->assertSame('Normal Text', ExportService::sanitize('Normal Text'));
    }

    public function test_export_service_includes_utf8_bom(): void
    {
        $export = app(ExportService::class);
        $service = app(AnalyticsService::class);

        $metrics = $service->dashboard();
        $csv = $export->dashboardCsv($metrics);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function test_mode_filtering_distinguishes_practice_from_exam(): void
    {
        $service = app(AnalyticsService::class);
        $user = User::create(['name' => 'Learner', 'email' => 'learner@test.com', 'password' => 'secret']);

        $exam = ExamTemplate::create([
            'exam_type_id' => 1,
            'practice_mode_id' => 1,
            'title' => 'Exam Test Mode',
            'slug' => 'exam-test-mode-1',
            'status' => 'published',
        ]);

        // Create practice attempt
        ExamAttempt::create([
            'exam_template_id' => $exam->id,
            'user_id' => $user->id,
            'snapshot' => ['mode' => 'practice'],
            'started_at' => now(),
            'status' => 'started',
        ]);

        // Create exam attempt
        ExamAttempt::create([
            'exam_template_id' => $exam->id,
            'user_id' => $user->id,
            'attempt_number' => 2,
            'snapshot' => ['mode' => 'exam'],
            'started_at' => now(),
            'status' => 'started',
        ]);

        $requestPractice = Request::create('/admin/analytics', 'GET', ['mode' => 'practice']);
        $metricsPractice = $service->dashboard($requestPractice);

        $requestExam = Request::create('/admin/analytics', 'GET', ['mode' => 'exam']);
        $metricsExam = $service->dashboard($requestExam);

        $this->assertEquals(1, $metricsPractice['attempts']);
        $this->assertEquals(1, $metricsExam['attempts']);
    }

    public function test_cancelled_attempts_are_excluded_from_metrics(): void
    {
        $service = app(AnalyticsService::class);
        $user = User::create(['name' => 'Learner2', 'email' => 'learner2@test.com', 'password' => 'secret']);

        $exam = ExamTemplate::create([
            'exam_type_id' => 1,
            'practice_mode_id' => 1,
            'title' => 'Exam Test Cancelled',
            'slug' => 'exam-test-cancelled-1',
            'status' => 'published',
        ]);

        // Create cancelled attempt
        ExamAttempt::create([
            'exam_template_id' => $exam->id,
            'user_id' => $user->id,
            'snapshot' => ['mode' => 'exam'],
            'started_at' => now(),
            'status' => 'cancelled',
        ]);

        $metrics = $service->dashboard();
        $this->assertEquals(0, $metrics['attempts']);
    }
}
