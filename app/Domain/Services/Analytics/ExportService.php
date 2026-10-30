<?php

namespace VistiqCore\Services\Analytics;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use VistiqCore\Models\ExamTemplate;

class ExportService
{
    public const MAX_ROWS = 10000;

    /**
     * Export dashboard metrics to CSV.
     *
     * @param  array<string, mixed>  $metrics  From AnalyticsService::dashboard()
     * @return string CSV content
     */
    public function dashboardCsv(array $metrics): string
    {
        $output = fopen('php://temp', 'r+');
        // BOM for Excel UTF-8 support
        fwrite($output, "\xEF\xBB\xBF");

        // Report metadata
        fputcsv($output, ['Báo cáo thống kê Vistiq']);
        fputcsv($output, ['Thời điểm tạo', $metrics['updated_at'] ?? now()->format('d/m/Y H:i')]);
        fputcsv($output, ['Timezone', $metrics['timezone'] ?? config('app.timezone', 'UTC')]);

        $filters = $metrics['filters'] ?? [];
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            fputcsv($output, ['Khoảng ngày', $filters['date_from'] ?? '—', 'đến', $filters['date_to'] ?? '—']);
        }
        if (! empty($filters['exam_id'])) {
            $examTitle = $metrics['exams']?->firstWhere('id', $filters['exam_id'])?->title ?? $filters['exam_id'];
            fputcsv($output, ['Kỳ thi', self::sanitize((string) $examTitle)]);
        }
        if (! empty($filters['mode']) && $filters['mode'] !== 'all') {
            fputcsv($output, ['Chế độ', $filters['mode'] === 'practice' ? 'Luyện tập (Practice)' : 'Thi chính thức (Exam)']);
        }

        foreach (['exam_type' => 'Loại đề', 'skill' => 'Kỹ năng', 'status' => 'Trạng thái'] as $key => $label) {
            if (! empty($filters[$key])) {
                fputcsv($output, [$label, self::sanitize((string) $filters[$key])]);
            }
        }

        fputcsv($output, []); // blank line

        // KPI summary
        fputcsv($output, ['Chỉ số', 'Giá trị', 'Chú giải']);
        fputcsv($output, ['Tổng lượt thi', $metrics['attempts'], 'Tổng số attempt đã bắt đầu (có snapshot)']);
        fputcsv($output, ['Đã hoàn tất', $metrics['completed'], 'Attempt đã nộp bài (submitted_at NOT NULL)']);
        fputcsv($output, ['Tỷ lệ hoàn tất (%)', $metrics['completion_rate'], 'completed / attempts × 100']);
        fputcsv($output, ['Điểm trung bình', $metrics['avg_score'] ?? 'N/A', 'AVG(scaled_score) của attempt đã chấm hoàn tất']);
        fputcsv($output, ['Chưa nộp (%)', $metrics['drop_off'], '(attempts − completed) / attempts × 100']);
        fputcsv($output, ['DAU', $metrics['dau'], 'Distinct users hoạt động trong ngày']);
        fputcsv($output, ['WAU', $metrics['wau'], 'Distinct users hoạt động trong 7 ngày']);
        fputcsv($output, ['MAU', $metrics['mau'], 'Distinct users hoạt động trong 30 ngày']);
        fputcsv($output, ['Retention 7 ngày (%)', $metrics['retention'], 'Cohort 7–14 ngày trước quay lại 7 ngày gần nhất']);

        fputcsv($output, []); // blank line

        // Chart data
        fputcsv($output, ['Ngày', 'Lượt thi', 'Người hoạt động']);
        foreach ($metrics['chart'] ?? [] as $point) {
            fputcsv($output, [$point['date'], $point['attempts'], $point['active']]);
        }

        fputcsv($output, []); // blank line

        // Difficulty table
        fputcsv($output, ['Câu hỏi', 'Số đáp án', 'Accuracy (%)', 'Giây trung bình']);
        foreach ($metrics['difficulty'] ?? [] as $row) {
            fputcsv($output, [
                self::sanitize((string) $row->item_key),
                $row->responses,
                round($row->accuracy * 100, 1),
                round($row->seconds, 1),
            ]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Export leaderboard rankings to CSV.
     *
     * @param  LengthAwarePaginator|iterable  $rankings
     * @return string CSV content
     */
    public function leaderboardCsv($rankings, ?ExamTemplate $exam = null): string
    {
        $output = fopen('php://temp', 'r+');
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, ['Bảng xếp hạng Vistiq', $exam ? self::sanitize($exam->title) : 'Tất cả kỳ thi']);
        fputcsv($output, ['Thời điểm xuất', now()->format('d/m/Y H:i')]);
        fputcsv($output, ['Timezone', config('app.timezone', 'UTC')]);
        fputcsv($output, []);

        fputcsv($output, ['Hạng', 'Thí sinh', 'Kỳ thi', 'Điểm', 'Thời gian làm bài (giây)', 'Thời điểm nộp bài']);

        $rank = 1;
        foreach ($rankings as $row) {
            fputcsv($output, [
                $row->rank_position ?? $rank++,
                self::sanitize((string) $row->name),
                self::sanitize((string) $row->title),
                $row->scaled_score,
                $row->seconds ?? 0,
                $row->submitted_at ?? '—',
            ]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Sanitize a cell value to prevent CSV formula injection.
     */
    public function download(string $csv, string $prefix, string $format): mixed
    {
        $content = $format === 'xlsx' ? $this->xlsx($csv) : $csv;
        $mime = $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8';

        return response($content, 200, ['Content-Type' => $mime, 'Content-Disposition' => 'attachment; filename="'.$prefix.'-'.now()->format('YmdHis').'.'.$format.'"', 'Cache-Control' => 'private, no-store']);
    }

    public function xlsx(string $csv): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Báo cáo Vistiq');
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($stream);
        $row = 1;
        try {
            while (($cells = fgetcsv($stream, null, ',', '"', '')) !== false) {
                foreach ($cells as $column => $cell) {
                    $numeric = is_string($cell) && preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/D', $cell);
                    $sheet->setCellValueExplicit([$column + 1, $row], $numeric ? (float) $cell : (string) $cell, $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
                $row++;
            }
            $sheet->getStyle('A1:F1')->getFont()->setBold(true);
            foreach (range('A', 'F') as $column) {
                $sheet->getColumnDimension($column)->setWidth($column === 'A' ? 32 : 26);
            }
            $sheet->freezePane('A2');
            ob_start();
            try {
                (new Xlsx($book))->save('php://output');

                return ob_get_contents();
            } finally {
                ob_end_clean();
            }
        } finally {
            fclose($stream);
            $book->disconnectWorksheets();
        }
    }

    public static function sanitize(string $value): string
    {
        if (preg_match('/^[\s\x{FEFF}]*[=+\-@]/u', $value) || preg_match('/^[\t\r\n]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
