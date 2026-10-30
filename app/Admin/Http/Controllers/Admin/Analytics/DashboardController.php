<?php

namespace VistiqAdmin\Http\Controllers\Admin\Analytics;

use Illuminate\Http\Request;
use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use VistiqAdmin\Http\Requests\Admin\Analytics\AnalyticsFilterRequest;
use VistiqCore\Services\Analytics\AnalyticsService;
use VistiqCore\Services\Analytics\ExportService;
use VistiqCore\Services\Analytics\OverviewService;
use VistiqCore\Services\System\AuditService;

class DashboardController extends AdminPageController
{
    /**
     * Admin overview dashboard.
     */
    public function index(Request $request, OverviewService $overview): mixed
    {
        $data = $request->validate(['days' => 'nullable|integer|in:7,14,30']);

        return $this->page('Tổng quan', 'pages.admin.analytics.dashboard.overview', [
            'overview' => $overview->read((int) ($data['days'] ?? 14)),
        ]);
    }

    /**
     * Detailed analytics screen with filtering support.
     */
    public function analytics(AnalyticsFilterRequest $request, AnalyticsService $analytics): mixed
    {
        return $this->page('Phân tích và thống kê', 'pages.admin.analytics.dashboard.workspace', [
            'metrics' => $analytics->dashboard($request),
        ]);
    }

    /**
     * Export dashboard metrics to CSV matching active filters.
     */
    public function export(AnalyticsFilterRequest $request, AnalyticsService $analytics, ExportService $export): mixed
    {
        $metrics = $analytics->dashboard($request);
        app(AuditService::class)->record('analytics.export', 'analytics', null, [], ['filters' => $metrics['filters'], 'format' => $request->input('format', 'csv')]);

        return $export->download($export->dashboardCsv($metrics), 'analytics-report', $request->input('format', 'csv'));
    }
}
