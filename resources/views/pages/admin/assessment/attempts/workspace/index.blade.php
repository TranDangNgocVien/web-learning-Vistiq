@php
    $labels = [
        'in_progress' => 'Đang làm',
        'paused' => 'Tạm dừng',
        'expired' => 'Hết giờ, chờ nộp',
        'submitted' => 'Đã nộp',
        'pending_grading' => 'Chờ chấm',
        'completed' => 'Hoàn tất',
    ];
    $hasActiveFilters = collect($filters)->contains(static fn ($value): bool => filled($value));
@endphp

<section class="attempts-workspace">
    <header class="page-heading attempts-heading">
        <div>
            <h1>Lượt làm bài</h1>
            <p>Theo dõi trạng thái, điểm số và báo cáo của học viên.</p>
        </div>
    </header>

    <section id="admin-attempt-summary" class="attempt-stats" aria-label="Tổng quan lượt làm bài" data-ajax-results>
        <article class="metric-card">
            <span class="category-icon blue" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'exam'])</span>
            <div class="attempt-metric-copy">
                <span class="attempt-metric-label">Tổng lượt</span>
                <strong>{{ number_format($summary['total']) }}</strong>
                <small>Theo bộ lọc hiện tại</small>
            </div>
        </article>
        <article class="metric-card">
            <span class="category-icon mint" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'check-circle'])</span>
            <div class="attempt-metric-copy">
                <span class="attempt-metric-label">Hoàn tất</span>
                <strong>{{ number_format($summary['completed']) }}</strong>
                <small>{{ $summary['completion_rate'] === null ? 'Chưa có lượt làm' : number_format($summary['completion_rate'], 1).'%' }} tổng lượt</small>
            </div>
        </article>
        <article class="metric-card">
            <span class="category-icon violet" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'dashboard'])</span>
            <div class="attempt-metric-copy">
                <span class="attempt-metric-label">Điểm trung bình</span>
                <strong>{{ $summary['average_score'] === null ? '—' : number_format($summary['average_score'], 2) }}</strong>
                <small>{{ $summary['average_context'] }}</small>
            </div>
        </article>
        <article class="metric-card">
            <span class="category-icon amber" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'alert'])</span>
            <div class="attempt-metric-copy">
                <span class="attempt-metric-label">Báo cáo cần chú ý</span>
                <strong>{{ number_format($summary['pending_reports']) }}</strong>
                <small>Lượt có báo cáo chờ xử lý</small>
            </div>
        </article>
    </section>

    <details class="panel attempt-filter" open>
        <summary class="attempt-filter-summary">
            <span class="category-icon blue" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'filter'])</span>
            <span class="attempt-filter-title">
                <strong>Bộ lọc nâng cao</strong>
                <small>Tìm kiếm và lọc danh sách lượt làm theo nhiều tiêu chí</small>
            </span>
            <span class="attempt-filter-caret" aria-hidden="true"></span>
        </summary>

        <form method="get" action="{{ route('admin.attempts.index') }}" class="admin-form-grid attempt-filter-grid" aria-label="Lọc lượt làm bài" data-ajax-filter-form data-ajax-target="#admin-attempt-summary, #admin-attempt-results" data-ajax-on-change data-ajax-debounce="350">
            <label class="attempt-field" for="attempt-filter-user">
                <span>ID người học</span>
                <span class="attempt-control">
                    <span class="attempt-control-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'profile'])</span>
                    <input id="attempt-filter-user" name="user" type="number" min="1" step="1" inputmode="numeric" value="{{ $filters['user'] ?? '' }}" placeholder="Nhập ID người học">
                </span>
            </label>
            <label class="attempt-field" for="attempt-filter-exam">
                <span>ID đề</span>
                <span class="attempt-control">
                    <span class="attempt-control-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'exam'])</span>
                    <input id="attempt-filter-exam" name="exam" type="number" min="1" step="1" inputmode="numeric" value="{{ $filters['exam'] ?? '' }}" placeholder="Nhập ID đề thi">
                </span>
            </label>
            <label class="attempt-field" for="attempt-filter-status">
                <span>Trạng thái</span>
                <span class="attempt-control">
                    <span class="attempt-control-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'activity'])</span>
                    <select id="attempt-filter-status" name="status">
                        <option value="">Tất cả trạng thái</option>
                        @foreach ($labels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </span>
            </label>
            <label class="attempt-field" for="attempt-filter-from">
                <span>Từ ngày</span>
                <span class="attempt-control">
                    <span class="attempt-control-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'clock'])</span>
                    <input id="attempt-filter-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
                </span>
            </label>
            <label class="attempt-field" for="attempt-filter-to">
                <span>Đến ngày</span>
                <span class="attempt-control">
                    <span class="attempt-control-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'clock'])</span>
                    <input id="attempt-filter-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
                </span>
            </label>
            <label class="attempt-field" for="attempt-filter-min-score">
                <span>Điểm tối thiểu</span>
                <span class="attempt-control">
                    <span class="attempt-control-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'dashboard'])</span>
                    <input id="attempt-filter-min-score" name="min_score" type="number" min="0" step="any" value="{{ $filters['min_score'] ?? '' }}" placeholder="Nhập điểm tối thiểu">
                </span>
            </label>
            <label class="attempt-field" for="attempt-filter-max-score">
                <span>Điểm tối đa</span>
                <span class="attempt-control">
                    <span class="attempt-control-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'dashboard'])</span>
                    <input id="attempt-filter-max-score" name="max_score" type="number" min="0" step="any" value="{{ $filters['max_score'] ?? '' }}" placeholder="Nhập điểm tối đa">
                </span>
            </label>
            <label class="attempt-field" for="attempt-filter-reports">
                <span>Báo cáo</span>
                <span class="attempt-control">
                    <span class="attempt-control-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'flag'])</span>
                    <select id="attempt-filter-reports" name="reports">
                        <option value="">Tất cả báo cáo</option>
                        @foreach (['pending' => 'Chờ xử lý', 'any' => 'Có báo cáo', 'none' => 'Không có báo cáo'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['reports'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </span>
            </label>
            <div class="attempt-filter-actions">
                <button class="button button-primary" type="submit">@include('pages.dictionary.icon', ['name' => 'search']) Lọc lượt thi</button>
                <a class="button button-quiet" data-ajax-filter-reset="#admin-attempt-summary, #admin-attempt-results" href="{{ route('admin.attempts.index') }}">@include('pages.dictionary.icon', ['name' => 'repeat']) Xóa bộ lọc</a>
            </div>
        </form>
    </details>

    <section id="admin-attempt-results" class="panel attempt-results" aria-labelledby="attempt-results-title" data-ajax-results>
        <header class="attempt-results-head">
            <div class="attempt-results-title">
                <span class="category-icon blue" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'exam'])</span>
                <span>
                    <h2 id="attempt-results-title">Kết quả lượt làm</h2>
                    <small>{{ number_format($attempts->total()) }} lượt · Trang {{ $attempts->currentPage() }} / {{ max(1, $attempts->lastPage()) }}</small>
                </span>
            </div>
        </header>

        <div class="table-responsive attempt-table-wrap">
            <table class="admin-table attempt-table">
                <caption class="attempt-table-caption">Danh sách lượt làm bài theo bộ lọc hiện tại</caption>
                <thead>
                    <tr>
                        <th scope="col">Lượt</th>
                        <th scope="col">Học viên</th>
                        <th scope="col">Nguồn đề</th>
                        <th scope="col">Trạng thái &amp; báo cáo</th>
                        <th scope="col">Điểm</th>
                        <th scope="col">Bắt đầu / Nộp bài</th>
                        <th scope="col">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attempts as $attempt)
                        @php
                            $reportCount = (int) ($attempt->report_count ?? 0);
                            $pendingReportCount = (int) ($attempt->pending_report_count ?? 0);
                            $statusClass = match ($attempt->status) {
                                'completed', 'submitted' => 'is-complete',
                                'in_progress' => 'is-active',
                                'pending_grading', 'expired' => 'is-attention',
                                default => 'is-paused',
                            };
                        @endphp
                        <tr>
                            <td><a class="attempt-row-id" href="{{ route('admin.attempts.show', $attempt) }}">#{{ $attempt->id }}</a></td>
                            <td>
                                <span class="attempt-primary-text">{{ $attempt->user?->name ?? 'Người học' }}</span>
                                <small class="attempt-secondary-text">ID {{ $attempt->user_id }}</small>
                            </td>
                            <td><span class="attempt-primary-text">{{ $attempt->snapshot['title'] ?? $attempt->exam?->title ?? 'Đề cũ' }}</span></td>
                            <td>
                                <div class="attempt-status-stack">
                                    <span class="attempt-status {{ $statusClass }}">{{ $labels[$attempt->status] ?? $attempt->status }}</span>
                                    @if ($pendingReportCount > 0)
                                        <span class="attempt-report-flag is-review"><span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'alert'])</span>{{ $pendingReportCount }} báo cáo chờ xử lý</span>
                                    @elseif ($reportCount > 0)
                                        <span class="attempt-report-flag is-reported"><span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'flag'])</span>{{ $reportCount }} báo cáo đã gửi</span>
                                    @else
                                        <span class="attempt-report-flag is-clear"><span aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'check'])</span>Không có báo cáo</span>
                                    @endif
                                </div>
                            </td>
                            <td><strong class="attempt-score">{{ $attempt->scaled_score === null ? '—' : number_format((float) $attempt->scaled_score, 2) }}</strong></td>
                            <td>
                                <span class="attempt-primary-text">{{ $attempt->started_at?->format('d/m/Y H:i') ?? '—' }}</span>
                                <small class="attempt-secondary-text">{{ $attempt->submitted_at?->format('d/m/Y H:i') ?? 'Chưa nộp' }}</small>
                            </td>
                            <td><a class="button button-quiet attempt-detail-link" href="{{ route('admin.attempts.show', $attempt) }}">@include('pages.dictionary.icon', ['name' => 'eye']) Chi tiết</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="admin-empty attempt-empty">
                                    <span class="category-icon blue" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'search'])</span>
                                    <strong>{{ $hasActiveFilters ? 'Không có lượt thi phù hợp' : 'Chưa có lượt thi' }}</strong>
                                    <span>{{ $hasActiveFilters ? 'Hãy điều chỉnh hoặc xóa bộ lọc để xem thêm lượt làm.' : 'Lượt làm mới sẽ xuất hiện ở đây khi học viên bắt đầu bài thi.' }}</span>
                                    @if ($hasActiveFilters)
                                        <a class="button button-quiet" href="{{ route('admin.attempts.index') }}">Xóa bộ lọc</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($attempts->hasPages())
            <div class="attempt-pagination" data-ajax-pagination>{{ $attempts->links() }}</div>
        @endif
    </section>
</section>

<style>
    .attempts-workspace { color: var(--ink); }
    .attempts-heading { margin: 0 0 13px; }
    .attempts-heading h1 { margin: 0; font-size: 25px; line-height: 1.2; letter-spacing: -.025em; }
    .attempts-heading p { margin: 5px 0 0; color: var(--muted); font-size: 11px; }
    .attempt-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin: 0 0 12px; }
    .attempt-stats .metric-card { min-height: 102px; flex-direction: row; align-items: center; gap: 12px; padding: 13px 14px; box-shadow: var(--shadow); }
    .attempt-stats .metric-card .category-icon { width: 48px; height: 48px; margin: 0; border-radius: 12px; font-size: 20px; }
    .attempt-metric-copy { min-width: 0; display: grid; gap: 2px; }
    .attempt-metric-label { overflow: hidden; color: var(--ink-soft); font-size: 10px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .attempt-metric-copy strong { font-size: 22px; line-height: 1.15; font-variant-numeric: tabular-nums; }
    .attempt-metric-copy small { overflow: hidden; color: var(--muted); font-size: 9px; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
    .admin-page .attempt-filter.panel, .admin-page .attempt-results.panel { margin: 0 0 12px; padding: 0; overflow: hidden; }
    .attempt-filter-summary { display: flex; align-items: center; gap: 12px; padding: 13px 15px; cursor: pointer; list-style: none; }
    .attempt-filter-summary::-webkit-details-marker { display: none; }
    .attempt-filter-title { min-width: 0; display: grid; gap: 3px; }
    .attempt-filter-title strong { font-size: 13px; }
    .attempt-filter-title small { color: var(--muted); font-size: 10px; }
    .attempt-filter-caret { width: 9px; height: 9px; margin-left: auto; border-top: 1.7px solid #597294; border-right: 1.7px solid #597294; transform: rotate(-45deg); transition: transform .16s ease; }
    .attempt-filter:not([open]) .attempt-filter-caret { transform: rotate(135deg); }
    .attempt-filter-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 18px; row-gap: 3px; padding: 3px 15px 14px; border-top: 1px solid #edf1f7; }
    .attempt-field { min-width: 0; display: grid; gap: 6px; margin: 7px 0; color: var(--ink); font-size: 10px; font-weight: 700; }
    .attempt-control { min-width: 0; height: 39px; display: flex; align-items: center; gap: 9px; padding: 0 10px; border: 1px solid var(--line); border-radius: 8px; background: #fff; transition: border-color .16s ease, box-shadow .16s ease; }
    .attempt-control:focus-within { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(11, 102, 239, .09); }
    .attempt-control-icon { width: 16px; display: inline-flex; flex: 0 0 auto; color: #37577f; }
    .attempt-control-icon .app-icon { width: 16px; height: 16px; }
    .attempt-control input, .attempt-control select { width: 100%; min-width: 0; height: 100%; padding: 0; border: 0; outline: 0; color: var(--ink-soft); background: transparent; box-shadow: none; font: inherit; font-size: 11px; font-weight: 500; }
    .attempt-control input::placeholder { color: #8b9ab1; opacity: 1; }
    .attempt-control input:focus, .attempt-control select:focus { border: 0; outline: 0; box-shadow: none; }
    .attempt-filter-actions { grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 2px; }
    .attempt-filter-actions .button { width: 100%; min-height: 39px; font-size: 11px; }
    .attempt-filter-actions .app-icon { width: 16px; height: 16px; }
    .attempt-results-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 12px 15px; }
    .attempt-results-title { min-width: 0; display: flex; align-items: center; gap: 10px; }
    .attempt-results-title .category-icon { width: 38px; height: 38px; margin: 0; font-size: 17px; }
    .attempt-results-title h2 { margin: 0 0 3px; font-size: 14px; }
    .attempt-results-title small { color: var(--muted); font-size: 10px; }
    .attempt-table-wrap { width: 100%; overflow-x: auto; }
    .attempt-table { min-width: 1050px; width: 100%; border-collapse: collapse; table-layout: auto; }
    .attempt-table th { padding: 11px 12px; color: #516686; background: #f5f8fd; font-size: 10px; font-weight: 700; white-space: nowrap; }
    .attempt-table td { padding: 10px 12px; color: #526783; border-bottom: 1px solid #edf1f7; font-size: 10px; vertical-align: middle; }
    .attempt-table tbody tr:last-child td { border-bottom: 0; }
    .attempt-table tbody tr:hover { background: #fbfdff; }
    .attempt-row-id { color: var(--blue); font-size: 11px; font-weight: 800; text-decoration: none; }
    .attempt-primary-text { display: block; color: #354d70; font-weight: 700; }
    .attempt-secondary-text { display: block; margin-top: 4px; color: #8997ab; font-size: 9px; }
    .attempt-status-stack { display: grid; justify-items: start; gap: 5px; }
    .attempt-status, .attempt-report-flag { min-height: 21px; display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 999px; font-size: 9px; font-weight: 700; line-height: 1.15; white-space: nowrap; }
    .attempt-status::before { width: 7px; height: 7px; border-radius: 50%; background: currentColor; content: ''; }
    .attempt-status.is-complete { color: #13835f; background: #e6f8ef; }
    .attempt-status.is-active { color: #1b64c9; background: #eaf3ff; }
    .attempt-status.is-attention { color: #a76812; background: #fff4df; }
    .attempt-status.is-paused { color: #596b84; background: #eef2f7; }
    .attempt-report-flag { min-height: 18px; padding: 2px 1px; border-radius: 0; font-size: 9px; font-weight: 600; }
    .attempt-report-flag .app-icon { width: 13px; height: 13px; }
    .attempt-report-flag.is-review { color: #b45309; }
    .attempt-report-flag.is-reported { color: #536b8b; }
    .attempt-report-flag.is-clear { color: #73829a; }
    .attempt-score { color: var(--ink); font-size: 11px; font-variant-numeric: tabular-nums; }
    .attempt-detail-link { min-height: 31px; padding: 0 10px; font-size: 9px; }
    .attempt-detail-link .app-icon { width: 14px; height: 14px; }
    .attempt-empty { min-height: 185px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 24px; color: var(--muted); text-align: center; }
    .attempt-empty .category-icon { width: 40px; height: 40px; font-size: 17px; }
    .attempt-empty strong { color: var(--ink-soft); font-size: 12px; }
    .attempt-empty > span:not(.category-icon) { font-size: 10px; }
    .attempt-empty .button { margin-top: 3px; }
    .attempt-pagination { padding: 9px 14px; border-top: 1px solid #edf1f7; }
    .attempt-table-caption { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }

    @media (max-width: 1000px) {
        .attempt-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 760px) {
        .attempts-heading h1 { font-size: 21px; }
        .attempt-filter-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 520px) {
        .attempt-stats .metric-card { min-height: 84px; gap: 8px; padding: 9px; }
        .attempt-stats .metric-card .category-icon { width: 36px; height: 36px; border-radius: 9px; font-size: 16px; }
        .attempt-metric-label { font-size: 9px; }
        .attempt-metric-copy strong { font-size: 18px; }
        .attempt-metric-copy small { font-size: 8px; }
        .attempt-filter-summary { padding: 11px; }
        .attempt-filter-grid { padding: 3px 11px 12px; }
        .attempt-filter-actions { grid-template-columns: 1fr; }
        .attempt-results-head { padding: 11px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .attempt-filter-caret, .attempt-control { transition: none; }
    }
</style>
