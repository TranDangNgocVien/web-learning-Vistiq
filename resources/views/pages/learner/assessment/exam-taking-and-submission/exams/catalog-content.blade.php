<main class="assessment-page exam-catalog-page" data-exam-catalog>
    <section class="exam-catalog-hero">
        <div class="exam-catalog-copy">
            <h1>{{ $examTypes->isNotEmpty() ? 'Luyện đề '.$examTypes->pluck('name')->join(' / ') : 'Kho đề thi' }}</h1>
            <p>Chọn bài thi phù hợp, luyện tập theo thời gian thực và theo dõi tiến độ của bạn.</p>
            <nav class="assessment-actions" aria-label="Lịch sử làm bài">
                <a class="button button-quiet" href="{{ route('profile.history') }}">
                    @include('pages.dictionary.icon', ['name' => 'clock'])
                    <span>Lượt làm của tôi</span>
                    @include('pages.dictionary.icon', ['name' => 'arrow-right'])
                </a>
            </nav>
        </div>
        <div class="exam-catalog-art" aria-hidden="true">
            <span class="exam-art-orbit orbit-one"></span>
            <span class="exam-art-orbit orbit-two"></span>
            <span class="exam-art-target">@include('pages.dictionary.icon', ['name' => 'target'])</span>
            @foreach ($examTypes->take(2) as $examType)
                <span class="exam-art-chip {{ $loop->first ? 'chip-top' : 'chip-bottom' }}">{{ $examType->name }}</span>
            @endforeach
        </div>
    </section>

    <form class="exam-catalog-controls" method="get" action="{{ route('exams.index') }}" aria-label="Tìm và lọc đề thi" data-exam-filter-form data-ajax-filter-form data-ajax-target="#exam-catalog-results" data-ajax-debounce="350">
        <input type="hidden" name="exam_type" value="{{ request('exam_type') }}" data-exam-type-filter>
        <div class="exam-catalog-toolbar">
            <div class="exam-tabs" role="group" aria-label="Loại bài thi">
                <button type="button" class="{{ request()->filled('exam_type') ? '' : 'is-active' }}" data-exam-tab="" aria-pressed="{{ request()->filled('exam_type') ? 'false' : 'true' }}">Tất cả</button>
                @foreach ($examTypes as $examType)
                    <button type="button" class="{{ request('exam_type') === $examType->code ? 'is-active' : '' }}" data-exam-tab="{{ $examType->code }}" aria-pressed="{{ request('exam_type') === $examType->code ? 'true' : 'false' }}">{{ $examType->name }}</button>
                @endforeach
            </div>
            <label class="exam-search" for="exam-search-input">
                <span>@include('pages.dictionary.icon', ['name' => 'search'])</span>
                <input id="exam-search-input" type="search" name="q" value="{{ request('q') }}" placeholder="Tìm đề thi, từ khóa..." aria-label="Tìm đề thi">
            </label>
        </div>

        <div class="exam-filter-row">
            <label><span>Nguồn đề</span><select name="provider" data-submit-on-change><option value="">Tất cả</option>@foreach ($providers as $provider)<option value="{{ $provider }}" @selected(request('provider') === $provider)>{{ $provider }}</option>@endforeach @if ($hasInternalExams)<option value="__internal__" @selected(request('provider') === '__internal__')>Không ghi nhà cung cấp</option>@endif</select></label>
            <label><span>Hình thức</span><select name="practice_mode" data-submit-on-change><option value="">Tất cả</option>@foreach ($practiceModes as $practiceMode)<option value="{{ $practiceMode->id }}" @selected((string) request('practice_mode') === (string) $practiceMode->id)>{{ $practiceMode->name }}</option>@endforeach</select></label>
            <label><span>Trạng thái</span><select name="availability" data-submit-on-change><option value="">Tất cả</option><option value="open" @selected(request('availability') === 'open')>Đang mở</option><option value="upcoming" @selected(request('availability') === 'upcoming')>Sắp mở</option><option value="closed" @selected(request('availability') === 'closed')>Đã đóng</option></select></label>
            <label class="exam-sort"><span>Sắp xếp</span><select name="sort" data-submit-on-change><option value="newest" @selected(request('sort', 'newest') === 'newest')>Mới nhất</option><option value="title" @selected(request('sort') === 'title')>Tên đề A–Z</option></select></label>
        </div>
    </form>

    <div id="exam-catalog-results" data-ajax-results>
    <div class="exam-catalog-heading">
        <div><h2>Đề thi dành cho bạn</h2><p><strong data-exam-count>{{ $exams->total() }}</strong> đề thi đã duyệt</p></div>
        <span class="exam-catalog-updated">Cập nhật liên tục</span>
    </div>

    <section class="exam-catalog-list" data-exam-list aria-live="polite">
        @forelse ($exams as $exam)
            @php
                $examTypeName = $exam->examType?->name ?? config('app.name');
                $practiceModeName = $practiceModes->firstWhere('id', $exam->practice_mode_id)?->name ?? $exam->mode;
                $providerName = $exam->provider ?: ($exam->created_by ? 'Người tạo đề' : config('app.name'));
                $isUpcoming = $exam->opens_at?->isFuture() ?? false;
                $isClosed = $exam->closes_at?->isPast() ?? false;
                $approvedPayload = $exam->publishedRevision?->payload ?? $exam->draft_payload ?? [];
                $questionCount = count($approvedPayload['items'] ?? []);
                if ($questionCount === 0) {
                    $questionCount = collect($approvedPayload['sections'] ?? [])->sum(fn ($section) => count($section['question_ids'] ?? []));
                }
                $durationMinutes = (int) ($approvedPayload['policies']['duration_minutes'] ?? 0);
                $durationLabel = $durationMinutes >= 60
                    ? floor($durationMinutes / 60).' giờ'.($durationMinutes % 60 ? ' '.($durationMinutes % 60).' phút' : '')
                    : $durationMinutes.' phút';
            @endphp
            <article class="exam-catalog-card">
                <div class="exam-catalog-cover" aria-hidden="true">
                    <span class="exam-cover-mark">{{ $providerName }}</span>
                    <strong>{{ $examTypeName }}</strong>
                    <span class="exam-cover-lines"></span>
                </div>
                <div class="exam-catalog-info">
                    <div class="exam-catalog-title-row">
                        <h3>{{ $exam->title }}</h3>
                        @if ($isClosed)
                            <span class="exam-status-pill is-closed">Đã đóng</span>
                        @elseif ($isUpcoming)
                            <span class="exam-status-pill is-upcoming">Sắp mở</span>
                        @else
                            <span class="exam-status-pill">Đang mở</span>
                        @endif
                    </div>
                    <div class="exam-catalog-tags">
                        <span class="exam-tag is-exam-type">{{ $examTypeName }}</span>
                        <span class="exam-tag">{{ $providerName }}</span>
                        @if ($practiceModeName)
                            <span class="exam-tag">{{ $practiceModeName }}</span>
                        @endif
                    </div>
                    @if (filled($exam->description))
                        <p class="exam-catalog-description">{{ $exam->description }}</p>
                    @endif
                    <div class="exam-catalog-meta">
                        @if ($questionCount)
                            <span>@include('pages.dictionary.icon', ['name' => 'exam']) {{ number_format($questionCount) }} câu hỏi</span>
                        @endif
                        @if ($durationMinutes)
                            <span>@include('pages.dictionary.icon', ['name' => 'clock']) {{ $durationLabel }}</span>
                        @endif
                        <span>@include('pages.dictionary.icon', ['name' => 'calendar-check']) {{ $isUpcoming ? 'Mở '.$exam->opens_at?->format('d/m/Y H:i') : ($exam->opens_at && $exam->closes_at ? $exam->opens_at->format('d/m').' – '.$exam->closes_at->format('d/m/Y') : 'Làm bài bất cứ lúc nào') }}</span>
                    </div>
                </div>
                <form class="exam-catalog-action" method="post" action="{{ route('exams.start', $exam) }}" data-exam-start data-requires-fullscreen="{{ ($approvedPayload['policies']['require_fullscreen'] ?? false) ? 'true' : 'false' }}" data-allow-mobile="{{ ($approvedPayload['policies']['allow_mobile'] ?? true) ? 'true' : 'false' }}">
                    @csrf
                    <button class="button button-primary" type="submit" @disabled($isUpcoming || $isClosed)>{{ $isUpcoming ? 'Chưa mở' : ($isClosed ? 'Đã đóng' : 'Làm bài') }} @include('pages.dictionary.icon', ['name' => 'arrow-right'])</button>
                    @if ($approvedPayload['policies']['leaderboard'] ?? false)
                        <a class="button button-quiet" href="{{ route('exams.leaderboard', $exam) }}">Bảng xếp hạng</a>
                    @endif
                </form>
                <p class="exam-start-feedback" data-start-feedback role="status" aria-live="polite" hidden></p>
            </article>
        @empty
            <div class="exam-catalog-empty">
                <span>@include('pages.dictionary.icon', ['name' => 'exam'])</span>
                @if (request()->hasAny(['q', 'exam_type', 'provider', 'practice_mode', 'availability']))
                    <h3>Không tìm thấy đề thi phù hợp</h3>
                    <p>Thử đổi từ khóa hoặc bộ lọc để tìm đề khác.</p>
                @else
                    <h3>Chưa có đề thi được duyệt</h3>
                    <p>Các bài thi mới sẽ xuất hiện ở đây khi sẵn sàng.</p>
                @endif
            </div>
        @endforelse
    </section>

    <div class="exam-catalog-pagination" data-ajax-pagination>{{ $exams->links() }}</div>
    </div>
</main>

<style>
    .exam-catalog-page .exam-search { flex-direction: row; margin: 0; }
    .exam-catalog-page .exam-search input { width: 100%; min-width: 0; height: 100%; padding: 0; border: 0; background: transparent; box-shadow: none; }
    .exam-catalog-page .exam-search input:focus { border: 0 !important; box-shadow: none !important; }
    .exam-catalog-page .exam-search:focus-within { outline: none; border-color: #2f6bff; box-shadow: none; }
</style>
