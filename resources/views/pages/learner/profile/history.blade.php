@php
    $selectedExam = old('exam', $filters['exam'] ?? '');
    $selectedStatus = old('status', $filters['status'] ?? '');
    $selectedFrom = old('from', $filters['from'] ?? '');
    $selectedTo = old('to', $filters['to'] ?? '');
    $hasHistoryFilters = filled($selectedExam) || filled($selectedStatus) || filled($selectedFrom) || filled($selectedTo);
    $attemptStatuses = [
        'in_progress' => ['Đang làm', 'badge-blue'],
        'paused' => ['Tạm dừng', 'badge-amber'],
        'submitted' => ['Đã nộp', 'badge-blue'],
        'expired' => ['Hết giờ, chờ nộp', 'badge-pink'],
        'pending_grading' => ['Chờ chấm', 'badge-amber'],
        'completed' => ['Hoàn tất', 'badge-mint'],
        'cancelled' => ['Đã hủy', 'badge-soft'],
    ];
@endphp

<section class="exam-history-page">
    <header class="page-heading">
        <div>
            <h1>Lịch sử làm đề</h1>
            <p class="text-sm text-[var(--ink-soft)]">Theo dõi tiến độ và kết quả các bài thi đã thực hiện.</p>
        </div>
    </header>

    <section class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3" aria-label="Tổng quan lịch sử làm đề">
        <article class="panel flex min-h-24 items-center gap-4 p-4">
            <span class="category-icon blue" aria-hidden="true">
                <svg class="app-icon h-6 w-6" viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"></rect><path d="M8 8h8M8 12h8M8 16h5"></path></svg>
            </span>
            <div>
                <p class="mb-1 text-sm font-semibold text-[var(--ink-soft)]">Tổng lượt làm</p>
                <strong class="block text-2xl text-[var(--ink)]">{{ number_format((int) ($historySummary->total_attempts ?? 0)) }}</strong>
            </div>
        </article>

        <article class="panel flex min-h-24 items-center gap-4 p-4">
            <span class="category-icon violet" aria-hidden="true">
                <svg class="app-icon h-6 w-6" viewBox="0 0 24 24"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3z"></path></svg>
            </span>
            <div>
                <p class="mb-1 text-sm font-semibold text-[var(--ink-soft)]">Điểm trung bình</p>
                <strong class="block text-2xl text-[var(--ink)]">{{ $historySummary?->average_score === null ? '—' : number_format((float) $historySummary->average_score, 1) }}</strong>
                @if ($historySummary?->average_score === null)
                    <span class="text-xs text-[var(--ink-soft)]">Chưa có kết quả được chấm</span>
                @endif
            </div>
        </article>

        <article class="panel flex min-h-24 items-center gap-4 p-4">
            <span class="category-icon mint" aria-hidden="true">
                <svg class="app-icon h-6 w-6" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="m8 12 2.5 2.5L16 9"></path></svg>
            </span>
            <div>
                <p class="mb-1 text-sm font-semibold text-[var(--ink-soft)]">Hoàn tất</p>
                <strong class="block text-2xl text-[var(--ink)]">{{ number_format((int) ($historySummary->completed_attempts ?? 0)) }} bài</strong>
            </div>
        </article>
    </section>

    <section class="panel mb-4 p-4 sm:p-5" aria-labelledby="history-filter-heading">
        <div class="mb-4 flex items-center gap-2">
            <span class="inline-grid h-8 w-8 place-items-center rounded-lg bg-[var(--blue-soft)] text-[var(--blue)]" aria-hidden="true">
                <svg class="app-icon h-4 w-4" viewBox="0 0 24 24"><path d="M4 5h16l-6 7v5l-4 2v-7L4 5z"></path></svg>
            </span>
            <h2 id="history-filter-heading" class="m-0 text-base font-semibold text-[var(--ink)]">Bộ lọc lịch sử làm đề</h2>
        </div>

        <form method="get" action="{{ route('profile.history') }}" class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 xl:grid-cols-6" data-ajax-filter-form data-ajax-target="#learner-history-results" data-ajax-on-change data-ajax-debounce="350">
            <label class="flex min-w-0 flex-col gap-2 text-sm font-semibold text-[var(--ink-soft)]" for="history-filter-exam">
                <span>Đề thi</span>
                <select id="history-filter-exam" name="exam" class="h-11 w-full rounded-lg border border-[var(--line)] bg-white px-3 text-sm text-[var(--ink-soft)] shadow-sm focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--blue)]" @if ($errors->has('exam')) aria-invalid="true" aria-describedby="history-filter-exam-error" @endif>
                    <option value="">Tất cả đề</option>
                    @foreach ($exams as $exam)
                        <option value="{{ $exam->id }}" @selected((string) $selectedExam === (string) $exam->id)>{{ $exam->title }}</option>
                    @endforeach
                </select>
                @error('exam')
                    <span id="history-filter-exam-error" class="text-xs font-normal text-red-800">{{ $message }}</span>
                @enderror
            </label>

            <label class="flex min-w-0 flex-col gap-2 text-sm font-semibold text-[var(--ink-soft)]" for="history-filter-status">
                <span>Trạng thái</span>
                <select id="history-filter-status" name="status" class="h-11 w-full rounded-lg border border-[var(--line)] bg-white px-3 text-sm text-[var(--ink-soft)] shadow-sm focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--blue)]" @if ($errors->has('status')) aria-invalid="true" aria-describedby="history-filter-status-error" @endif>
                    <option value="">Tất cả trạng thái</option>
                    @foreach (['in_progress' => 'Đang làm', 'paused' => 'Tạm dừng', 'submitted' => 'Đã nộp', 'expired' => 'Hết giờ, chờ nộp', 'pending_grading' => 'Chờ chấm', 'completed' => 'Hoàn tất'] as $code => $label)
                        <option value="{{ $code }}" @selected($selectedStatus === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')
                    <span id="history-filter-status-error" class="text-xs font-normal text-red-800">{{ $message }}</span>
                @enderror
            </label>

            <label class="flex min-w-0 flex-col gap-2 text-sm font-semibold text-[var(--ink-soft)]" for="history-filter-from">
                <span>Từ ngày</span>
                <input id="history-filter-from" name="from" type="date" value="{{ $selectedFrom }}" @if (filled($selectedTo)) max="{{ $selectedTo }}" @endif class="h-11 w-full min-w-0 rounded-lg border border-[var(--line)] bg-white px-3 text-sm text-[var(--ink-soft)] shadow-sm focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--blue)]" @if ($errors->has('from')) aria-invalid="true" aria-describedby="history-filter-from-error" @endif>
                @error('from')
                    <span id="history-filter-from-error" class="text-xs font-normal text-red-800">{{ $message }}</span>
                @enderror
            </label>

            <label class="flex min-w-0 flex-col gap-2 text-sm font-semibold text-[var(--ink-soft)]" for="history-filter-to">
                <span>Đến ngày</span>
                <input id="history-filter-to" name="to" type="date" value="{{ $selectedTo }}" @if (filled($selectedFrom)) min="{{ $selectedFrom }}" @endif class="h-11 w-full min-w-0 rounded-lg border border-[var(--line)] bg-white px-3 text-sm text-[var(--ink-soft)] shadow-sm focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--blue)]" @if ($errors->has('to')) aria-invalid="true" aria-describedby="history-filter-to-error" @endif>
                @error('to')
                    <span id="history-filter-to-error" class="text-xs font-normal text-red-800">{{ $message }}</span>
                @enderror
            </label>

            <button class="button button-primary h-11 w-full" type="submit">
                <svg class="app-icon h-4 w-4" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m16 16 4 4"></path></svg>
                Lọc lịch sử
            </button>
            <a class="button button-quiet h-11 w-full" data-ajax-filter-reset="#learner-history-results" href="{{ route('profile.history') }}">
                <svg class="app-icon h-4 w-4" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"></path><path d="M3 3v5h5"></path></svg>
                Xóa bộ lọc
            </a>
        </form>
    </section>

    <section id="learner-history-results" class="panel overflow-hidden" aria-labelledby="history-results-heading" data-ajax-results>
        <header class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
            <div class="flex min-w-0 items-center gap-3">
                <span class="category-icon blue" aria-hidden="true">
                    <svg class="app-icon h-5 w-5" viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11"></path><circle cx="4" cy="6" r=".7"></circle><circle cx="4" cy="12" r=".7"></circle><circle cx="4" cy="18" r=".7"></circle></svg>
                </span>
                <div>
                    <h2 id="history-results-heading" class="m-0 text-base font-semibold text-[var(--ink)]">Kết quả lịch sử làm đề</h2>
                    <p class="mb-0 mt-1 text-xs text-[var(--ink-soft)]">{{ number_format($attempts->total()) }} lượt làm đề · Múi giờ {{ $timeZone }}</p>
                </div>
            </div>
        </header>

        <div class="table-responsive">
            <table class="admin-table w-full min-w-[760px]">
                <caption class="sr-only">Các lượt làm đề của bạn</caption>
                <thead class="bg-[var(--canvas)] text-[var(--ink-soft)]">
                    <tr>
                        <th scope="col">Đề thi</th>
                        <th scope="col">Bắt đầu</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col" class="text-center">Điểm</th>
                        <th scope="col">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attempts as $attempt)
                        @php
                            $attemptTitle = $attempt->exam?->title ?? $attempt->snapshot['title'] ?? 'Đề đã lưu';
                            [$statusLabel, $statusClass] = $attemptStatuses[$attempt->status] ?? [ucfirst(str_replace('_', ' ', $attempt->status)), 'badge-soft'];
                        @endphp
                        <tr class="transition-colors hover:bg-[var(--canvas)]">
                            <td class="font-semibold text-[var(--ink)]">{{ $attemptTitle }}</td>
                            <td>
                                @if ($attempt->started_at)
                                    <time class="inline-flex items-center gap-2 whitespace-nowrap" datetime="{{ $attempt->started_at->toIso8601String() }}">
                                        <svg class="app-icon h-4 w-4 text-[var(--muted)]" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18"></path></svg>
                                        {{ $attempt->started_at->timezone($timeZone)->format('d/m/Y H:i') }}
                                    </time>
                                @else
                                    <span class="text-[var(--ink-soft)]">Chưa bắt đầu</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $statusClass }} !text-[var(--ink)]">{{ $statusLabel }}</span></td>
                            <td class="text-center">
                                @if ($attempt->scaled_score !== null)
                                    <span class="badge badge-blue rounded-full px-3 py-1">{{ number_format((float) $attempt->scaled_score, 2) }}</span>
                                @else
                                    <span class="text-sm text-[var(--ink-soft)]">Chưa có điểm</span>
                                @endif
                            </td>
                            <td>
                                @if ($attempt->snapshot && !$attempt->submitted_at)
                                    <a class="text-link inline-flex items-center gap-2" href="{{ route('attempts.show', $attempt) }}" aria-label="Tiếp tục làm đề: {{ $attemptTitle }}">
                                        <svg class="app-icon h-4 w-4" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12a7 7 0 0 1 12-5l2 2"></path><path d="M19 4v5h-5M19 12a7 7 0 0 1-12 5l-2-2"></path><path d="M5 20v-5h5"></path></svg>
                                        Tiếp tục làm đề
                                    </a>
                                @elseif ($attempt->snapshot && $attempt->submitted_at)
                                    <a class="text-link inline-flex items-center gap-2" href="{{ route('attempts.result', $attempt) }}" aria-label="Xem kết quả: {{ $attemptTitle }}">
                                        <svg class="app-icon h-4 w-4" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        Xem kết quả
                                    </a>
                                @else
                                    <span class="text-[var(--ink-soft)]">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center">
                                <div class="mx-auto flex max-w-lg flex-col items-center gap-2 px-4">
                                    <span class="category-icon blue" aria-hidden="true">
                                        <svg class="app-icon h-5 w-5" viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"></rect><path d="M8 8h8M8 12h8M8 16h5"></path></svg>
                                    </span>
                                    <h3 class="mb-0 mt-2 text-base font-semibold text-[var(--ink)]">Chưa có lượt làm đề phù hợp</h3>
                                    <p class="mb-2 text-sm text-[var(--ink-soft)]">
                                        @if ($hasHistoryFilters)
                                            Thử điều chỉnh bộ lọc hoặc xóa bộ lọc để xem các lượt làm đề khác.
                                        @else
                                            Các lượt làm đề sẽ xuất hiện tại đây sau khi bạn bắt đầu làm bài.
                                        @endif
                                    </p>
                                    <div class="flex flex-wrap justify-center gap-2">
                                        @if ($hasHistoryFilters)
                                            <a class="button button-quiet" href="{{ route('profile.history') }}">Xóa bộ lọc</a>
                                        @endif
                                        <a class="button button-primary" href="{{ route('exams.index') }}">Chọn đề thi</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($attempts->hasPages())
            <div class="border-t border-[var(--line)] p-4 sm:p-5" data-ajax-pagination>
                {{ $attempts->links() }}
            </div>
        @endif
    </section>
</section>
