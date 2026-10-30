<?php

namespace Tests\Admin\Feature\Analytics;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Services\Analytics\AnalyticsService;
use VistiqCore\Services\Assessment\Attempts\AttemptService;

class ExportContractTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_export_requires_a_separate_permission_and_xlsx_is_a_real_workbook(): void
    {
        $this->actingAs($this->admin('reviewer'))->get('/admin/analytics/export')->assertForbidden();
        $this->get('/admin/leaderboard/export')->assertForbidden();
        $this->actingAs($this->admin('admin'));
        $response = $this->get('/admin/analytics/export?format=xlsx&mode=exam')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'vistiq-export-');
        try {
            file_put_contents($path, $response->getContent());
            $book = IOFactory::load($path);
            $rows = $book->getActiveSheet()->toArray();
            $this->assertSame('Báo cáo thống kê Vistiq', $rows[0][0]);
            $this->assertStringContainsString('Thi chính thức', json_encode($rows, JSON_UNESCAPED_UNICODE));
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'analytics.export']);
    }

    public function test_leaderboard_export_includes_every_filtered_page_and_uses_computed_ranks(): void
    {
        $exam = $this->publishedExam([], ['leaderboard' => true, 'anonymous' => false]);
        $service = app(AttemptService::class);
        foreach (range(1, 31) as $number) {
            $attempt = $service->start($exam, User::factory()->create(['name' => 'Rank learner '.$number]));
            $service->submit($attempt);
        }
        $this->actingAs($this->admin());
        $response = $this->get('/admin/leaderboard/export?exam_id='.$exam->id.'&page=2')->assertOk();
        foreach (range(1, 31) as $number) {
            $this->assertStringContainsString('Rank learner '.$number, $response->getContent());
        }
        $this->assertStringNotContainsString('snapshot', $response->getContent());
        $this->get('/admin/leaderboard?exam_id='.$exam->id.'&page=2')->assertOk()->assertSee('#1');
        $this->getJson('/admin/leaderboard/export?exam_id=999999')->assertUnprocessable();
        $this->getJson('/admin/leaderboard/export?format=pdf')->assertUnprocessable();
    }

    public function test_average_and_difficulty_use_the_same_mode_and_cancelled_exclusions(): void
    {
        $exam = $this->publishedExam();
        $service = app(AttemptService::class);
        foreach (['exam', 'legacy-practice', 'cancelled'] as $kind) {
            $attempt = $service->start($exam, User::factory()->create());
            $service->saveAnswers($attempt, [['item_key' => 'q1', 'version' => 0, 'response' => ['options' => [$kind === 'exam' ? 'B' : 'A']]]]);
            $service->submit($attempt);
            $snapshot = $attempt->fresh()->snapshot;
            if ($kind === 'legacy-practice') {
                $snapshot['variant'] = 'practice';
                $attempt->update(['snapshot' => $snapshot]);
            }
            if ($kind === 'cancelled') {
                $attempt->update(['status' => 'cancelled']);
            }
        }
        $metrics = app(AnalyticsService::class)->dashboard(Request::create('/admin/analytics', 'GET', ['mode' => 'exam']));
        $this->assertSame(1, $metrics['attempts']);
        $this->assertEquals(100, $metrics['avg_score']);
        $this->assertSame(1, $metrics['difficulty']->first()->responses);
        $this->assertEquals(1, $metrics['difficulty']->first()->accuracy);
    }
}
