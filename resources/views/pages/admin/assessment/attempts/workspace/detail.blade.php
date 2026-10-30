@php
    $statusLabels = [
        'in_progress' => 'Đang làm',
        'paused' => 'Tạm dừng',
        'expired' => 'Hết giờ, chờ nộp',
        'submitted' => 'Đã nộp',
        'pending_grading' => 'Chờ chấm',
        'completed' => 'Hoàn tất',
    ];
    $statusLabel = $statusLabels[$attempt->status] ?? ucfirst(str_replace('_', ' ', $attempt->status));
    $statusTone = in_array($attempt->status, ['completed', 'submitted'], true)
        ? 'is-success'
        : (in_array($attempt->status, ['in_progress', 'paused'], true) ? 'is-active' : 'is-attention');
    $leaderboardLabels = ['approved' => 'Được công bố', 'private' => 'Riêng tư', 'rejected' => 'Đã bác'];
    $leaderboardSummaries = ['approved' => 'Có hiển thị', 'private' => 'Ẩn khỏi bảng xếp hạng', 'rejected' => 'Đã bác'];
    $leaderboardLabel = $leaderboardLabels[$attempt->leaderboard_status] ?? ucfirst((string) $attempt->leaderboard_status);
    $leaderboardSummary = $leaderboardSummaries[$attempt->leaderboard_status] ?? $leaderboardLabel;
    $snapshot = $attempt->snapshot ?? [];
    $items = collect(data_get($snapshot, 'items', []))->values();
    $answers = $attempt->answers->keyBy('item_key');
    $pendingManualCount = $items->filter(static fn ($item): bool => in_array($item['type'] ?? '', ['essay', 'speaking'], true)
        && ($answer = $answers->get($item['key'] ?? '')) !== null && ! $answer->grade)->count();
    $hasResponseValue = static fn ($value): bool => is_scalar($value) && (! is_string($value) || trim($value) !== '');
    $hasResponse = static fn ($response): bool => collect($response ?? [])->flatten()->contains($hasResponseValue);
    $answeredCount = $items->filter(static function ($item) use ($answers, $hasResponse): bool {
        $answer = $answers->get($item['key'] ?? '');

        return $answer !== null && $hasResponse($answer->response);
    })->count();
    $questionCount = $items->count();
    $eventCount = $events->count() + (int) (bool) $attempt->started_at + (int) (bool) $attempt->submitted_at;
    $pendingReportCount = $reports->where('status', 'pending')->count();
    $wasModerated = collect($history->items())->contains(static fn ($event): bool => $event->action === 'attempt.moderate');
    $reviewLabel = $pendingReportCount > 0 ? 'Có báo cáo chờ xử lý' : ($wasModerated ? 'Đã điều chỉnh' : 'Chưa điều chỉnh');
    $reviewTone = $pendingReportCount > 0 ? 'is-attention' : ($wasModerated ? 'is-success' : 'is-neutral');
    $examTitle = data_get($snapshot, 'title') ?: $attempt->exam?->title ?: 'Đề cũ';
    $score = $result?->scaled_score !== null ? number_format((float) $result->scaled_score, 2) : '—';
    $gradingLabel = match ($result?->grading_status) {
        null => 'Chưa có điểm',
        'pending' => 'Chờ chấm',
        default => 'Ước tính',
    };
    $durationSeconds = $attempt->started_at
        ? max(0, (int) $attempt->started_at->diffInSeconds($attempt->submitted_at ?? now()))
        : null;
    $duration = $durationSeconds === null
        ? '—'
        : ($durationSeconds >= 3600
            ? intdiv($durationSeconds, 3600).' giờ '.intdiv($durationSeconds % 3600, 60).' phút'
            : ($durationSeconds >= 60
                ? intdiv($durationSeconds, 60).' phút '.($durationSeconds % 60).' giây'
                : $durationSeconds.' giây'));
    $questionTypes = [
        'multiple_choice' => 'Trắc nghiệm',
        'multiple_response' => 'Chọn nhiều đáp án',
        'fill_blank' => 'Điền từ',
        'ordering' => 'Sắp xếp',
        'extended_completion' => 'Hoàn thành câu',
        'essay' => 'Tự luận',
        'writing' => 'Writing',
        'speaking' => 'Speaking',
    ];
    $questionRegistry = app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class);
    $gradingCriteriaLabels = [
        'task_response' => 'Đáp ứng yêu cầu đề bài',
        'coherence' => 'Mạch lạc và liên kết',
        'fluency' => 'Độ lưu loát và mạch lạc',
        'lexical_resource' => 'Vốn từ vựng',
        'grammar' => 'Ngữ pháp và độ chính xác',
        'pronunciation' => 'Phát âm',
    ];
    $humanizeLabel = static fn ($value): string => \Illuminate\Support\Str::headline((string) $value);
    $formatJson = static fn ($value): string => json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
@endphp

<div class="attempt-detail">
    <nav class="attempt-detail-breadcrumbs" aria-label="Đường dẫn">
        <span>Admin Studio</span>
        <span aria-hidden="true">/</span>
        <a href="{{ route('admin.attempts.index') }}">Lượt thi &amp; báo cáo</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Lượt thi #{{ $attempt->id }}</span>
    </nav>

    <header class="attempt-detail-heading">
        <h1>Lượt thi #{{ $attempt->id }}</h1>
        <p>Xem lịch sử làm bài, trạng thái chấm và câu trả lời của học viên.</p>
    </header>

    <section class="panel attempt-overview" aria-label="Tổng quan lượt thi">
        <div class="attempt-overview-main">
            <div class="attempt-learner">
                <span class="attempt-learner-avatar" aria-hidden="true">
                    @include('pages.dictionary.icon', ['name' => 'profile'])
                </span>
                <div class="attempt-learner-copy">
                    <h2>{{ $attempt->user?->name ?? 'Người học' }}</h2>
                    <p>{{ $examTitle }}</p>
                    <div class="attempt-badges" aria-label="Trạng thái lượt thi">
                        <span class="attempt-badge is-mode">Exam</span>
                        <span class="attempt-badge {{ $statusTone }}">{{ $statusLabel }}</span>
                        <span class="attempt-badge {{ $attempt->leaderboard_status === 'approved' ? 'is-success' : 'is-neutral' }}">{{ $leaderboardLabel }}</span>
                    </div>
                    @can('results.grade')
                        @if ($attempt->submitted_at && $pendingManualCount > 0)
                            <a class="button button-primary attempt-grade-link" href="{{ route('admin.grading.index', ['attempt' => $attempt->id]) }}">Chấm {{ $pendingManualCount }} câu</a>
                        @endif
                    @endcan
                </div>
            </div>

            <dl class="attempt-overview-facts">
                <div>
                    <dt>@include('pages.dictionary.icon', ['name' => 'check-circle']) Xếp hạng</dt>
                    <dd>{{ $leaderboardSummary }}</dd>
                </div>
                <div>
                    <dt>@include('pages.dictionary.icon', ['name' => 'exam']) Revision đề</dt>
                    <dd>{{ $attempt->exam_revision_id ?? 'Legacy' }}</dd>
                </div>
                <div>
                    <dt>@include('pages.dictionary.icon', ['name' => 'dashboard']) Điểm</dt>
                    <dd>{{ $score }} <span>· {{ $gradingLabel }}</span></dd>
                </div>
                <div>
                    <dt>@include('pages.dictionary.icon', ['name' => 'clock']) Bắt đầu</dt>
                    <dd>{{ $attempt->started_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
                </div>
                <div class="attempt-fact-wide">
                    <dt>@include('pages.dictionary.icon', ['name' => 'check-circle']) Nộp bài</dt>
                    <dd>{{ $attempt->submitted_at?->format('Y-m-d H:i:s') ?? 'Chưa nộp' }}</dd>
                </div>
            </dl>
        </div>

        <div class="attempt-overview-stats" aria-label="Số liệu lượt thi">
            <article class="attempt-stat is-blue">
                <span class="attempt-stat-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'clock'])</span>
                <div><span>Thời gian làm</span><strong>{{ $duration }}</strong></div>
            </article>
            <article class="attempt-stat is-green">
                <span class="attempt-stat-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'check-circle'])</span>
                <div><span>Câu đã trả lời</span><strong>{{ $answeredCount }} / {{ $questionCount }}</strong></div>
            </article>
            <article class="attempt-stat is-violet">
                <span class="attempt-stat-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'activity'])</span>
                <div><span>Sự kiện ghi nhận</span><strong>{{ number_format($eventCount) }}</strong></div>
            </article>
            <article class="attempt-stat is-amber">
                <span class="attempt-stat-icon" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'alert'])</span>
                <div><span>Trạng thái kiểm duyệt</span><strong>{{ $reviewLabel }}</strong></div>
            </article>
        </div>
    </section>

    <div class="attempt-detail-columns">
        <section class="panel attempt-detail-card" aria-labelledby="attempt-moderation-title">
            <header class="attempt-card-heading">
                <span class="attempt-card-icon is-blue" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'activity'])</span>
                <h2 id="attempt-moderation-title">Kiểm duyệt &amp; công bố xếp hạng</h2>
            </header>

            @can('reports.review')
                @if ($attempt->submitted_at)
                    <form method="post" action="{{ route('admin.attempts.moderate', $attempt) }}" class="admin-form-grid attempt-moderation-form" data-confirm="Thay đổi quyết định xếp hạng lượt #{{ $attempt->id }} và xử lý báo cáo đang chờ?" data-confirm-label="Lưu kiểm duyệt">
                        @csrf
                        <label for="attempt-leaderboard-status">
                            <span>Công bố xếp hạng <span aria-hidden="true">*</span></span>
                            <select id="attempt-leaderboard-status" name="leaderboard_status" required aria-invalid="{{ $errors->has('leaderboard_status') ? 'true' : 'false' }}" @if ($errors->has('leaderboard_status')) aria-describedby="attempt-leaderboard-error" @endif>
                                @foreach (['approved' => 'Cho hiển thị', 'private' => 'Ẩn khỏi bảng xếp hạng', 'rejected' => 'Bác'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('leaderboard_status', $attempt->leaderboard_status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('leaderboard_status')
                                <span id="attempt-leaderboard-error" class="attempt-field-error" role="alert">{{ $message }}</span>
                            @enderror
                        </label>
                        <label class="admin-label" for="attempt-moderation-note">
                            <span>Lý do hoặc ghi chú <span aria-hidden="true">*</span></span>
                            <textarea id="attempt-moderation-note" name="note" maxlength="2000" required placeholder="Nhập lý do hoặc ghi chú kiểm duyệt..." aria-describedby="attempt-note-help{{ $errors->has('note') ? ' attempt-note-error' : '' }}">{{ old('note') }}</textarea>
                            @error('note')
                                <span id="attempt-note-error" class="attempt-field-error" role="alert">{{ $message }}</span>
                            @enderror
                            <small id="attempt-note-help">Bắt buộc · tối đa 2.000 ký tự</small>
                        </label>
                        <div class="attempt-moderation-actions">
                            <button class="button button-primary" type="submit">@include('pages.dictionary.icon', ['name' => 'check-circle']) Lưu kiểm duyệt</button>
                            <button class="button" type="reset">@include('pages.dictionary.icon', ['name' => 'activity']) Đặt lại</button>
                        </div>
                        <p class="attempt-moderation-note">
                            @include('pages.dictionary.icon', ['name' => 'alert'])
                            Thay đổi được lưu vào lịch sử kiểm duyệt của lượt thi này.
                        </p>
                    </form>
                @else
                    <p class="attempt-inline-note">Chỉ có thể kiểm duyệt sau khi học viên nộp bài.</p>
                @endif
            @else
                <p class="attempt-inline-note">Trạng thái xếp hạng: <strong>{{ $leaderboardLabel }}</strong></p>
            @endcan
        </section>

        <section class="panel attempt-detail-card" aria-labelledby="attempt-timeline-title">
            <header class="attempt-card-heading">
                <span class="attempt-card-icon is-blue" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'clock'])</span>
                <h2 id="attempt-timeline-title">Dòng thời gian</h2>
            </header>
            <ol class="attempt-timeline">
                @if ($attempt->started_at)
                    <li class="is-start">
                        <span class="attempt-timeline-marker" aria-hidden="true">▶</span>
                        <div><time datetime="{{ $attempt->started_at->toIso8601String() }}">{{ $attempt->started_at->format('Y-m-d H:i:s') }}</time><strong>Bắt đầu</strong></div>
                    </li>
                @endif
                @foreach ($events as $event)
                    @php
                        $occurredAt = \Illuminate\Support\Carbon::parse($event->occurred_at);
                    @endphp
                    <li class="is-event">
                        <span class="attempt-timeline-marker" aria-hidden="true">↻</span>
                        <div>
                            <time datetime="{{ $occurredAt->toIso8601String() }}">{{ $occurredAt->format('Y-m-d H:i:s') }}</time>
                            <strong>{{ strtoupper($event->code) }}@if ($event->item_key) · {{ $event->item_key }}@endif</strong>
                        </div>
                    </li>
                @endforeach
                @if ($attempt->submitted_at)
                    <li class="is-submit">
                        <span class="attempt-timeline-marker" aria-hidden="true">⚑</span>
                        <div><time datetime="{{ $attempt->submitted_at->toIso8601String() }}">{{ $attempt->submitted_at->format('Y-m-d H:i:s') }}</time><strong>Nộp bài</strong></div>
                    </li>
                @endif
                @if (! $attempt->started_at && $events->isEmpty() && ! $attempt->submitted_at)
                    <li class="attempt-timeline-empty">Chưa có sự kiện cho lượt thi này.</li>
                @endif
            </ol>
        </section>
    </div>

    <section class="panel attempt-detail-card attempt-questions-card" aria-labelledby="attempt-questions-title">
        <header class="attempt-questions-heading">
            <div class="attempt-card-heading">
                <span class="attempt-card-icon is-blue" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'exam'])</span>
                <h2 id="attempt-questions-title">Câu hỏi và câu trả lời đã ghim</h2>
            </div>
            <div class="attempt-question-totals" aria-label="Tổng hợp câu trả lời">
                <span class="is-total">Tổng câu: {{ $questionCount }}</span>
                <span class="is-answered">Đã trả lời: {{ $answeredCount }}</span>
                <span class="is-unanswered">Chưa trả lời: {{ max(0, $questionCount - $answeredCount) }}</span>
            </div>
        </header>

        @if ($items->isNotEmpty())
            <div class="attempt-question-scroll" tabindex="0" aria-label="Danh sách câu hỏi và câu trả lời">
                <div class="attempt-question-list-head">
                    <span>#</span><span>Câu hỏi</span><span>Loại</span><span>Trạng thái</span><span>Tóm tắt câu trả lời</span><span>Thao tác</span>
                </div>
                <div class="attempt-question-list" role="list">
                    @foreach ($items as $index => $item)
                        @php
                            $itemKey = $item['key'] ?? 'Câu '.($index + 1);
                            $answer = $answers->get($itemKey);
                            $itemType = $item['type'] ?? '';
                            $itemTypeLabel = $questionTypes[$itemType] ?? ucfirst(str_replace('_', ' ', $itemType));
                            $response = is_array($answer?->response) ? $answer->response : [];
                            $recordingId = $itemType === 'speaking' ? (int) ($response['asset_id'] ?? 0) : 0;
                            $hasAnswer = $answer && $hasResponse($response);
                            $optionMap = is_array($item['options'] ?? null) ? $item['options'] : [];
                            $selectedOptionValues = $response['options'] ?? [];
                            $selectedOptionValues = is_array($selectedOptionValues)
                                ? $selectedOptionValues
                                : (filled($selectedOptionValues) ? [$selectedOptionValues] : []);
                            $selectedOptionKeys = collect($selectedOptionValues)
                                ->filter($hasResponseValue)
                                ->map(static fn ($value): string => (string) $value)
                                ->values();
                            $matchingResponses = is_array($response['matching'] ?? null) ? $response['matching'] : [];
                            $blankResponses = is_array($response['blanks'] ?? null) ? $response['blanks'] : [];
                            $blankDefinitions = is_array(data_get($item, 'metadata.blanks', [])) ? data_get($item, 'metadata.blanks', []) : [];
                            $fallbackResponseValues = collect($response)
                                ->except(['options', 'matching', 'blanks', 'text', 'asset_id'])
                                ->flatten()
                                ->filter($hasResponseValue)
                                ->map(static fn ($value): string => is_bool($value) ? ($value ? 'Có' : 'Không') : (string) $value);
                            $responseValues = collect($response)
                                ->flatten()
                                ->filter($hasResponseValue)
                                ->map(static fn ($value): string => is_bool($value) ? ($value ? 'Có' : 'Không') : (string) $value);
                            $selectedOptionSummary = $selectedOptionKeys->map(static function ($key) use ($optionMap): string {
                                $label = $optionMap[$key] ?? null;

                                return $key.(is_scalar($label) && trim((string) $label) !== '' ? ' · '.$label : '');
                            });
                            $responseSummaryValues = $selectedOptionSummary->isNotEmpty() ? $selectedOptionSummary : $responseValues;
                            $responseSummary = $recordingId > 0 ? 'Bản ghi âm' : ($responseSummaryValues->isNotEmpty()
                                ? \Illuminate\Support\Str::limit($responseSummaryValues->implode(' · '), 68)
                                : ($hasAnswer ? 'Đã lưu phản hồi' : 'Chưa có câu trả lời'));
                            $rawGrade = $answer?->grade;
                            $rawGrade = is_string($rawGrade) ? json_decode($rawGrade, true) : $rawGrade;
                            $grade = is_array($rawGrade) ? $rawGrade : [];
                            $gradeCriteria = is_array($grade['criteria'] ?? null) ? $grade['criteria'] : [];
                            $gradeAverage = $gradeCriteria !== []
                                ? array_sum(array_map(static fn ($value): float => (float) $value, $gradeCriteria)) / count($gradeCriteria)
                                : null;
                            $rubric = is_array(data_get($item, 'metadata.rubric', [])) ? data_get($item, 'metadata.rubric', []) : [];
                            $promptText = trim(strip_tags((string) ($item['prompt'] ?? '')));
                            $questionTitle = $promptText !== ''
                                ? \Illuminate\Support\Str::limit($promptText, 84)
                                : 'Nội dung câu hỏi đã ghim';
                        @endphp
                        <details class="attempt-question-row" role="listitem">
                            <summary class="attempt-question-summary">
                                <span class="attempt-question-key">{{ $itemKey }}</span>
                                <span class="attempt-question-title">{{ $questionTitle }}</span>
                                <span class="attempt-question-type">{{ $itemTypeLabel }}</span>
                                <span><span class="attempt-answer-state {{ $hasAnswer ? 'is-answered' : 'is-unanswered' }}">{{ $hasAnswer ? 'Có câu trả lời' : 'Chưa trả lời' }}</span></span>
                                <span class="attempt-answer-summary" title="{{ $responseSummary }}">{{ $responseSummary }}</span>
                                <span class="attempt-question-action"><span class="attempt-question-chevron" aria-hidden="true"></span><span>Xem chi tiết</span></span>
                            </summary>
                            <div class="attempt-question-expanded">
                                <div class="attempt-pinned-content">
                                    <h3>Nội dung đã ghim</h3>
                                    @if (! empty($item['prompt']))
                                        <div class="attempt-rich-content">{!! $questionRegistry->sanitize($item['prompt']) !!}</div>
                                    @else
                                        <p class="attempt-inline-note">Snapshot không có nội dung câu hỏi.</p>
                                    @endif
                                    @foreach ($item['stimuli'] ?? [] as $stimulus)
                                        <article class="attempt-stimulus">
                                            <h4>{{ $stimulus['title'] ?? $stimulus['stimulus_type'] ?? 'Tư liệu' }}</h4>
                                            <div class="attempt-rich-content">{!! $questionRegistry->sanitize($stimulus['body'] ?? '') !!}</div>
                                        </article>
                                    @endforeach
                                </div>
                                <div class="attempt-answer-data">
                                    @if ($optionMap !== [])
                                        <section class="attempt-options-card">
                                            <h3>Các phương án</h3>
                                            <div class="attempt-option-list">
                                                @foreach ($optionMap as $optionKey => $optionText)
                                                    @php
                                                        $optionIsSelected = $selectedOptionKeys->contains(static fn ($key): bool => $key === (string) $optionKey);
                                                    @endphp
                                                    <div class="attempt-option-row {{ $optionIsSelected ? 'is-selected' : '' }}">
                                                        <span class="attempt-option-key">{{ $optionKey }}</span>
                                                        <span class="attempt-option-label">{{ is_scalar($optionText) ? $optionText : 'Nội dung phương án không khả dụng.' }}</span>
                                                        @if ($optionIsSelected)
                                                            <span class="attempt-option-state">Đã chọn</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </section>
                                    @endif
                                    <section class="attempt-answer-card">
                                        <h3>Học viên trả lời</h3>
                                        @if (! $hasAnswer)
                                            <p class="attempt-answer-empty">Học viên chưa trả lời câu này.</p>
                                        @elseif ($selectedOptionKeys->isNotEmpty())
                                            <div class="attempt-answer-list">
                                                @foreach ($selectedOptionKeys as $optionKey)
                                                    @php
                                                        $selectedLabel = $optionMap[$optionKey] ?? null;
                                                    @endphp
                                                    <div class="attempt-answer-entry">
                                                        <span class="attempt-answer-key">{{ $optionKey }}</span>
                                                        <span>{{ is_scalar($selectedLabel) ? $selectedLabel : 'Phương án '.$optionKey }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @elseif ($matchingResponses !== [])
                                            <dl class="attempt-answer-list">
                                                @foreach ($matchingResponses as $rowKey => $selectedValue)
                                                    @php
                                                        $metadataLabels = data_get($item, 'metadata.labels', []);
                                                        $rowLabel = is_array($metadataLabels) ? ($metadataLabels[$rowKey] ?? null) : null;
                                                        $rowLabel = is_scalar($rowLabel) && trim((string) $rowLabel) !== ''
                                                            ? $rowLabel
                                                            : (is_numeric($rowKey) ? 'Mục '.$loop->iteration : $humanizeLabel($rowKey));
                                                        $selectedValue = is_scalar($selectedValue) ? (string) $selectedValue : '';
                                                        $matchingLabel = $optionMap[$selectedValue] ?? null;
                                                    @endphp
                                                    <div class="attempt-answer-entry">
                                                        <dt>{{ $rowLabel }}</dt>
                                                        <dd>{{ is_scalar($matchingLabel) ? $matchingLabel : ($selectedValue !== '' ? $selectedValue : 'Chưa chọn') }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @elseif ($blankResponses !== [])
                                            <dl class="attempt-answer-list">
                                                @foreach ($blankResponses as $blankKey => $blankValue)
                                                    @php
                                                        $blankDefinition = $blankDefinitions[$blankKey] ?? [];
                                                        $blankLabel = is_array($blankDefinition) ? ($blankDefinition['label'] ?? null) : null;
                                                        $blankLabel = is_scalar($blankLabel) && trim((string) $blankLabel) !== ''
                                                            ? $blankLabel
                                                            : 'Ô trống '.$loop->iteration;
                                                    @endphp
                                                    <div class="attempt-answer-entry">
                                                        <dt>{{ $blankLabel }}</dt>
                                                        <dd>{{ is_scalar($blankValue) ? $blankValue : '—' }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @elseif ($recordingId > 0)
                                            <audio class="attempt-recording" controls preload="metadata" aria-label="Bản ghi âm câu {{ $itemKey }}" src="{{ route('admin.attempts.recording', [$attempt, $recordingId]) }}">Trình duyệt không hỗ trợ phát bản ghi âm.</audio>
                                            <a href="{{ route('admin.attempts.recording', [$attempt, $recordingId]) }}" download="{{ $itemKey }}.webm">Tải bản ghi âm</a>
                                            @if (filled($response['text'] ?? null))<p class="attempt-answer-text">{{ $response['text'] }}</p>@endif
                                        @elseif (filled($response['text'] ?? null))
                                            <p class="attempt-answer-text">{{ $response['text'] }}</p>
                                        @elseif ($fallbackResponseValues->isNotEmpty())
                                            <ul class="attempt-answer-list attempt-answer-fallback">
                                                @foreach ($fallbackResponseValues as $value)
                                                    <li>{{ $value }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="attempt-answer-empty">Phản hồi đã được lưu.</p>
                                        @endif
                                    </section>
                                    <section class="attempt-answer-card">
                                        <h3>Kết quả chấm</h3>
                                        @if ($gradeCriteria !== [])
                                            <div class="attempt-grade-overview">
                                                <strong>{{ number_format($gradeAverage, 1) }}<span> / 9</span></strong>
                                                <span>Điểm trung bình</span>
                                            </div>
                                            <dl class="attempt-grade-criteria">
                                                @foreach ($gradeCriteria as $criterion => $criterionScore)
                                                    <div>
                                                        <dt>{{ $gradingCriteriaLabels[$criterion] ?? $humanizeLabel($criterion) }}</dt>
                                                        <dd>{{ number_format((float) $criterionScore, 1) }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                            @if (filled($grade['feedback'] ?? null))
                                                <div class="attempt-answer-feedback">
                                                    <strong>Nhận xét</strong>
                                                    <p>{{ $grade['feedback'] }}</p>
                                                </div>
                                            @endif
                                            @foreach ($grade['criterion_feedback'] ?? [] as $criterion => $comment)
                                                @if (filled($comment))
                                                    <div class="attempt-answer-feedback">
                                                        <strong>{{ $gradingCriteriaLabels[$criterion] ?? $humanizeLabel($criterion) }}</strong>
                                                        <p>{{ $comment }}</p>
                                                    </div>
                                                @endif
                                            @endforeach
                                            @if (filled($grade['improvements'] ?? null))
                                                <div class="attempt-answer-feedback">
                                                    <strong>Gợi ý cải thiện</strong>
                                                    <p>{{ $grade['improvements'] }}</p>
                                                </div>
                                            @endif
                                            @if (filled($grade['reason'] ?? null))
                                                <div class="attempt-answer-feedback">
                                                    <strong>Lý do điều chỉnh</strong>
                                                    <p>{{ $grade['reason'] }}</p>
                                                </div>
                                            @endif
                                        @elseif ($answer && ($answer->is_correct !== null || $answer->awarded_points !== null))
                                            <div class="attempt-grade-objective">
                                                @if ($answer->is_correct !== null)
                                                    <span class="attempt-grade-state {{ $answer->is_correct ? 'is-correct' : 'is-incorrect' }}">{{ $answer->is_correct ? 'Đúng' : 'Chưa đúng' }}</span>
                                                @endif
                                                @if ($answer->awarded_points !== null)
                                                    <span>Điểm đạt: <strong>{{ number_format((float) $answer->awarded_points, 2) }}</strong></span>
                                                @endif
                                            </div>
                                        @elseif (in_array($itemType, ['essay', 'writing', 'speaking'], true))
                                            <p class="attempt-answer-empty">Câu trả lời đang chờ chấm.</p>
                                        @else
                                            <p class="attempt-answer-empty">Chưa có kết quả chấm cho câu này.</p>
                                        @endif
                                        @can('results.grade')
                                            @if ($attempt->submitted_at && $answer && in_array($itemType, ['essay', 'speaking'], true))
                                                <a class="button button-quiet" href="{{ route('admin.grading.index', ['attempt' => $attempt->id, 'answer' => $answer->id]) }}">{{ $grade ? 'Điều chỉnh điểm' : 'Chấm câu này' }}</a>
                                            @endif
                                        @endcan
                                    </section>
                                    @if ($rubric !== [])
                                        <section class="attempt-rubric-card">
                                            <h3>Tiêu chí đánh giá</h3>
                                            <dl class="attempt-rubric-list">
                                                @foreach ($rubric as $criterion => $description)
                                                    <div>
                                                        <dt>{{ $gradingCriteriaLabels[$criterion] ?? $humanizeLabel($criterion) }}</dt>
                                                        <dd>{{ is_scalar($description) ? $description : '' }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </section>
                                    @endif
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        @else
            <div class="attempt-detail-empty">
                <strong>Chưa có snapshot câu hỏi</strong>
                <span>Lượt thi cũ này không lưu nội dung câu hỏi tại thời điểm bắt đầu.</span>
            </div>
        @endif
    </section>

    <section class="panel attempt-detail-card" aria-labelledby="attempt-reports-title">
        <header class="attempt-card-heading attempt-card-heading-between">
            <div class="attempt-card-heading">
                <span class="attempt-card-icon is-amber" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'alert'])</span>
                <h2 id="attempt-reports-title">Báo cáo</h2>
            </div>
            <span class="attempt-section-count">{{ number_format($reports->count()) }} báo cáo</span>
        </header>
        @forelse ($reports as $report)
            @php
                $reportTime = $report->created_at ? \Illuminate\Support\Carbon::parse($report->created_at) : null;
            @endphp
            <article class="attempt-report-row">
                <div class="attempt-report-heading">
                    <strong>Báo cáo #{{ $report->id }}</strong>
                    <span class="attempt-report-status {{ $report->status === 'pending' ? 'is-attention' : 'is-neutral' }}">{{ $report->status === 'pending' ? 'Chờ xử lý' : ucfirst(str_replace('_', ' ', $report->status)) }}</span>
                    @if ($reportTime)
                        <time datetime="{{ $reportTime->toIso8601String() }}">{{ $reportTime->format('Y-m-d H:i:s') }}</time>
                    @endif
                </div>
                <p>{{ $report->details ?: 'Không có nội dung bổ sung.' }}</p>
            </article>
        @empty
            <p class="attempt-inline-note">Chưa có báo cáo cho lượt thi này.</p>
        @endforelse
    </section>

    <section class="panel attempt-detail-card" aria-labelledby="attempt-history-title">
        <header class="attempt-card-heading attempt-card-heading-between">
            <div class="attempt-card-heading">
                <span class="attempt-card-icon is-violet" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'activity'])</span>
                <h2 id="attempt-history-title">Lịch sử chấm và kiểm duyệt</h2>
            </div>
            <span class="attempt-section-count">{{ number_format($history->total()) }} thay đổi</span>
        </header>
        @forelse ($history as $event)
            @php
                $historyTime = \Illuminate\Support\Carbon::parse($event->created_at);
                $beforeValue = is_string($event->before) ? $event->before : $formatJson($event->before);
                $afterValue = is_string($event->after) ? $event->after : $formatJson($event->after);
            @endphp
            <details class="attempt-history-row">
                <summary>
                    <time datetime="{{ $historyTime->toIso8601String() }}">{{ $historyTime->format('Y-m-d H:i:s') }}</time>
                    <strong>{{ $event->action }}</strong>
                    <span>Người thực hiện #{{ $event->actor_id ?? '—' }}</span>
                    <span class="attempt-history-action">Xem thay đổi</span>
                </summary>
                <div class="attempt-history-values">
                    <section><h3>Trước khi thay đổi</h3><pre>{{ $beforeValue ?: 'Không có dữ liệu.' }}</pre></section>
                    <section><h3>Sau khi thay đổi</h3><pre>{{ $afterValue ?: 'Không có dữ liệu.' }}</pre></section>
                </div>
            </details>
        @empty
            <p class="attempt-inline-note">Chưa có điều chỉnh điểm hoặc kiểm duyệt.</p>
        @endforelse
        @if ($history->hasPages())
            <div class="attempt-history-pagination">{{ $history->links() }}</div>
        @endif
    </section>
</div>

<style>
    .attempt-detail { display: grid; gap: 13px; min-width: 0; color: var(--ink, #142c55); }
    .attempt-detail-breadcrumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; color: var(--muted, #7184a1); font-size: 11px; }
    .attempt-detail-breadcrumbs a { color: #49668f; text-decoration: none; }
    .attempt-detail-breadcrumbs a:hover { color: var(--blue, #0b66ef); text-decoration: underline; }
    .attempt-detail-breadcrumbs [aria-current="page"] { color: var(--ink, #142c55); font-weight: 700; }
    .attempt-detail-heading { display: grid; gap: 3px; }
    .attempt-detail-heading h1 { margin: 0; color: var(--ink, #142c55); font-size: 25px; line-height: 1.2; }
    .attempt-detail-heading p { margin: 0; color: var(--muted, #7184a1); font-size: 12px; }
    .attempt-detail .panel.attempt-overview,
    .attempt-detail .panel.attempt-detail-card { min-width: 0; margin: 0; padding: 16px; overflow: hidden; border: 1px solid var(--line, #dfe8f5); border-radius: 13px; background: #fff; box-shadow: 0 7px 22px rgba(35, 71, 126, .045); }
    .attempt-overview { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(320px, .95fr); gap: 20px; align-items: stretch; }
    .attempt-overview-main { min-width: 0; padding-right: 18px; border-right: 1px solid #e8eef7; }
    .attempt-learner { display: flex; align-items: center; gap: 13px; min-width: 0; }
    .attempt-learner-avatar { width: 54px; height: 54px; display: grid; flex: 0 0 auto; place-items: center; border-radius: 50%; color: #2878ef; background: #eaf2ff; }
    .attempt-learner-avatar .app-icon { width: 26px; height: 26px; }
    .attempt-learner-copy { min-width: 0; }
    .attempt-learner-copy h2 { margin: 0; overflow: hidden; color: var(--ink, #142c55); font-size: 18px; line-height: 1.25; text-overflow: ellipsis; }
    .attempt-learner-copy > p { margin: 3px 0 8px; overflow: hidden; color: #6d809f; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
    .attempt-badges { display: flex; flex-wrap: wrap; gap: 6px; }
    .attempt-grade-link { margin-top: 10px; }
    .attempt-badge { min-height: 22px; display: inline-flex; align-items: center; padding: 4px 9px; border-radius: 999px; color: #526984; background: #edf2f8; font-size: 10px; font-weight: 700; line-height: 1.2; }
    .attempt-badge.is-mode { color: #435c7f; background: #edf2f9; }
    .attempt-badge.is-success { color: #12805c; background: #e4f7ed; }
    .attempt-badge.is-active { color: #1765c3; background: #e9f2ff; }
    .attempt-badge.is-attention { color: #a56610; background: #fff3df; }
    .attempt-badge.is-neutral { color: #5e6d83; background: #edf1f6; }
    .attempt-overview-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 18px; margin: 15px 0 0; }
    .attempt-overview-facts > div { min-width: 0; display: grid; grid-template-columns: minmax(88px, auto) minmax(0, 1fr); align-items: center; gap: 7px; }
    .attempt-overview-facts dt { display: flex; align-items: center; gap: 7px; color: #7183a0; font-size: 10px; white-space: nowrap; }
    .attempt-overview-facts dt .app-icon { width: 15px; height: 15px; color: #547199; }
    .attempt-overview-facts dd { min-width: 0; margin: 0; overflow: hidden; color: #233b62; font-size: 10px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .attempt-overview-facts dd span { color: #7183a0; font-weight: 500; }
    .attempt-fact-wide { grid-column: 1 / -1; }
    .attempt-overview-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; align-content: center; }
    .attempt-stat { min-width: 0; min-height: 66px; display: flex; align-items: center; gap: 11px; padding: 10px 12px; border: 1px solid transparent; border-radius: 11px; }
    .attempt-stat.is-blue { color: #0b61d8; border-color: #e7efff; background: #f2f6ff; }
    .attempt-stat.is-green { color: #118461; border-color: #e2f4eb; background: #effaf4; }
    .attempt-stat.is-violet { color: #7042c4; border-color: #eee6fb; background: #f7f3ff; }
    .attempt-stat.is-amber { color: #a75e13; border-color: #f7eadc; background: #fff7ef; }
    .attempt-stat-icon { width: 35px; height: 35px; display: grid; flex: 0 0 auto; place-items: center; border-radius: 10px; background: rgba(255, 255, 255, .72); }
    .attempt-stat-icon .app-icon { width: 19px; height: 19px; }
    .attempt-stat > div { min-width: 0; display: grid; gap: 3px; }
    .attempt-stat > div > span { overflow: hidden; color: #455b7a; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
    .attempt-stat strong { overflow: hidden; color: var(--ink, #142c55); font-size: 14px; line-height: 1.2; text-overflow: ellipsis; white-space: nowrap; }
    .attempt-detail-columns { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(290px, .75fr); gap: 13px; align-items: stretch; }
    .attempt-detail-card { min-width: 0; }
    .attempt-card-heading { min-width: 0; display: flex; align-items: center; gap: 10px; margin-bottom: 13px; }
    .attempt-card-heading h2 { min-width: 0; margin: 0; color: var(--ink, #142c55); font-size: 16px; line-height: 1.35; }
    .attempt-card-icon { width: 29px; height: 29px; display: inline-grid; flex: 0 0 auto; place-items: center; border-radius: 9px; background: #eef5ff; color: #1769e7; }
    .attempt-card-icon.is-amber { color: #c27519; background: #fff4e7; }
    .attempt-card-icon.is-violet { color: #7042c4; background: #f3edff; }
    .attempt-card-icon .app-icon { width: 17px; height: 17px; }
    .attempt-card-heading-between { justify-content: space-between; }
    .attempt-card-heading-between > .attempt-card-heading { margin: 0; }
    .attempt-section-count { color: #7183a0; font-size: 10px; white-space: nowrap; }
    .attempt-moderation-form { display: grid; grid-template-columns: minmax(0, .88fr) minmax(0, 1.12fr); gap: 12px 18px; align-items: start; }
    .attempt-moderation-form > label { min-width: 0; display: grid; gap: 6px; color: var(--ink, #142c55); font-size: 11px; font-weight: 700; }
    .attempt-moderation-form > label select { width: 100%; min-height: 39px; }
    .attempt-moderation-form > label textarea { width: 100%; min-height: 88px; resize: vertical; }
    .attempt-moderation-form > label small { color: var(--muted, #7184a1); font-size: 10px; font-weight: 500; }
    .attempt-field-error { color: #b42318; font-size: 10px; font-weight: 600; }
    .attempt-moderation-actions { display: flex; flex-wrap: wrap; gap: 9px; align-items: center; }
    .attempt-moderation-actions .button { min-height: 39px; display: inline-flex; align-items: center; gap: 7px; }
    .attempt-moderation-actions .app-icon { width: 15px; height: 15px; }
    .attempt-moderation-note { min-width: 0; display: flex; align-items: flex-start; gap: 8px; margin: 0; color: var(--muted, #7184a1); font-size: 10px; line-height: 1.5; }
    .attempt-moderation-note .app-icon { width: 16px; height: 16px; flex: 0 0 auto; color: var(--blue, #0b66ef); }
    .attempt-inline-note { margin: 0; color: var(--muted, #7184a1); font-size: 11px; line-height: 1.55; }
    .attempt-timeline { position: relative; display: grid; gap: 0; margin: 0; padding: 0; list-style: none; }
    .attempt-timeline > li { position: relative; min-height: 50px; display: grid; grid-template-columns: 38px minmax(0, 1fr); gap: 11px; align-items: start; }
    .attempt-timeline > li:not(:last-child)::after { position: absolute; top: 35px; bottom: -2px; left: 17px; width: 2px; background: #dce7f8; content: ''; }
    .attempt-timeline-marker { z-index: 1; width: 35px; height: 35px; display: grid; place-items: center; border-radius: 50%; color: #1677ec; background: #eaf2ff; font-size: 13px; }
    .attempt-timeline .is-start .attempt-timeline-marker { color: #17865e; background: #e4f7ed; }
    .attempt-timeline .is-submit .attempt-timeline-marker { color: #1769e7; }
    .attempt-timeline li > div { min-width: 0; display: grid; gap: 3px; padding: 3px 0 12px; }
    .attempt-timeline time { color: #687d9d; font-size: 10px; font-variant-numeric: tabular-nums; }
    .attempt-timeline strong { overflow-wrap: anywhere; color: #20395f; font-size: 11px; line-height: 1.35; }
    .attempt-timeline-empty { display: block !important; min-height: auto !important; padding: 0 0 0 46px; color: var(--muted, #7184a1); font-size: 11px; }
    .attempt-questions-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px; margin-bottom: 12px; }
    .attempt-questions-heading .attempt-card-heading { margin: 0; }
    .attempt-question-totals { display: flex; flex-wrap: wrap; gap: 7px; }
    .attempt-question-totals span { min-height: 24px; display: inline-flex; align-items: center; padding: 4px 9px; border-radius: 999px; font-size: 10px; font-weight: 700; white-space: nowrap; }
    .attempt-question-totals .is-total { color: #405b83; background: #edf3fc; }
    .attempt-question-totals .is-answered { color: #12805c; background: #e8f8ef; }
    .attempt-question-totals .is-unanswered { color: #a65e12; background: #fff3e5; }
    .attempt-question-scroll { overflow-x: auto; border: 1px solid #e7edf6; border-radius: 9px; }
    .attempt-question-scroll:focus-visible { outline: 3px solid rgba(11, 102, 239, .35); outline-offset: 2px; }
    .attempt-question-list-head,
    .attempt-question-summary { display: grid; grid-template-columns: 86px minmax(220px, 1.45fr) minmax(120px, .75fr) minmax(130px, .78fr) minmax(190px, 1fr) minmax(120px, .6fr); align-items: center; gap: 12px; min-width: 930px; }
    .attempt-question-list-head { padding: 10px 12px; color: #536b8e; background: #f3f7fd; font-size: 10px; font-weight: 700; }
    .attempt-question-list { min-width: 930px; }
    .attempt-question-row { border-top: 1px solid #eaf0f8; }
    .attempt-question-row:first-child { border-top: 0; }
    .attempt-question-summary { min-height: 39px; padding: 5px 12px; cursor: pointer; list-style: none; }
    .attempt-question-summary::-webkit-details-marker { display: none; }
    .attempt-question-row[open] .attempt-question-summary { background: #f9fbff; }
    .attempt-question-key { color: #15396e; font-size: 11px; font-weight: 800; }
    .attempt-question-title { overflow: hidden; color: #40587b; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
    .attempt-question-type { color: #536b8e; font-size: 10px; overflow-wrap: anywhere; }
    .attempt-answer-state,
    .attempt-report-status { min-height: 20px; display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 999px; font-size: 9px; font-weight: 700; line-height: 1.2; white-space: nowrap; }
    .attempt-answer-state.is-answered { color: #13805d; background: #e6f8ee; }
    .attempt-answer-state.is-unanswered { color: #a96312; background: #fff3e5; }
    .attempt-answer-summary { display: block; overflow: hidden; color: #7183a0; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
    .attempt-question-action { display: inline-flex; align-items: center; gap: 6px; color: var(--blue, #0b66ef); font-size: 10px; font-weight: 700; white-space: nowrap; }
    .attempt-question-chevron { width: 7px; height: 7px; border-top: 1.7px solid currentColor; border-right: 1.7px solid currentColor; transform: rotate(45deg); }
    .attempt-question-row[open] .attempt-question-chevron { transform: rotate(135deg); }
    .attempt-question-expanded { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(260px, .85fr); gap: 13px; padding: 14px; border-top: 1px solid #e8eef7; background: #fbfcff; }
    .attempt-pinned-content,
    .attempt-answer-data { min-width: 0; }
    .attempt-question-expanded h3 { margin: 0 0 8px; color: #20395f; font-size: 12px; }
    .attempt-rich-content { color: #40587b; font-size: 11px; line-height: 1.7; overflow-wrap: anywhere; }
    .attempt-rich-content > :first-child { margin-top: 0; }
    .attempt-rich-content > :last-child { margin-bottom: 0; }
    .attempt-stimulus { margin-top: 12px; padding: 10px; border: 1px solid #e6edf7; border-radius: 8px; background: #fff; }
    .attempt-stimulus h4 { margin: 0 0 6px; color: #536b8e; font-size: 10px; }
    .attempt-answer-data { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; align-content: start; }
    .attempt-answer-data section { min-width: 0; padding: 10px; border: 1px solid #e6edf7; border-radius: 8px; background: #fff; }
    .attempt-recording { display: block; width: 100%; max-width: 100%; margin-bottom: 8px; }
    .attempt-answer-data .attempt-options-card { grid-column: 1 / -1; }
    .attempt-option-list,
    .attempt-answer-list { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; }
    .attempt-option-row { display: grid; grid-template-columns: 25px minmax(0, 1fr) auto; gap: 8px; align-items: start; padding: 7px 8px; border: 1px solid #e8eef7; border-radius: 7px; color: #536b8e; font-size: 10px; line-height: 1.45; }
    .attempt-option-row.is-selected { border-color: #b8d0ff; background: #f2f7ff; color: #274d86; }
    .attempt-option-key,
    .attempt-answer-key { display: inline-flex; min-width: 23px; min-height: 22px; justify-content: center; align-items: center; padding: 2px 5px; border-radius: 6px; color: #36537b; background: #eef3fa; font-size: 9px; font-weight: 800; }
    .attempt-option-label { min-width: 0; overflow-wrap: anywhere; }
    .attempt-option-state { color: #0d7e58; font-size: 9px; font-weight: 700; white-space: nowrap; }
    .attempt-answer-entry { display: flex; gap: 8px; align-items: flex-start; min-width: 0; color: #40587b; font-size: 10px; line-height: 1.5; overflow-wrap: anywhere; }
    .attempt-answer-entry dt { min-width: 82px; color: #7183a0; font-weight: 600; }
    .attempt-answer-entry dd { min-width: 0; margin: 0; color: #243f67; }
    .attempt-answer-text { margin: 0; color: #40587b; font-size: 10px; line-height: 1.65; white-space: pre-wrap; overflow-wrap: anywhere; }
    .attempt-answer-empty { margin: 0; color: #7183a0; font-size: 10px; line-height: 1.5; }
    .attempt-answer-fallback { color: #40587b; font-size: 10px; line-height: 1.5; overflow-wrap: anywhere; }
    .attempt-grade-overview { display: flex; gap: 9px; align-items: baseline; margin-bottom: 8px; color: #7183a0; font-size: 9px; }
    .attempt-grade-overview strong { color: #173f78; font-size: 17px; font-variant-numeric: tabular-nums; }
    .attempt-grade-overview strong span { color: #7183a0; font-size: 10px; font-weight: 500; }
    .attempt-grade-criteria,
    .attempt-rubric-list { display: grid; gap: 6px; margin: 0; }
    .attempt-grade-criteria > div,
    .attempt-rubric-list > div { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: start; color: #536b8e; font-size: 9px; line-height: 1.45; }
    .attempt-grade-criteria dt,
    .attempt-rubric-list dt { color: #536b8e; }
    .attempt-grade-criteria dd { margin: 0; color: #173f78; font-weight: 700; font-variant-numeric: tabular-nums; }
    .attempt-rubric-list > div { grid-template-columns: minmax(100px, .8fr) minmax(0, 1.2fr); }
    .attempt-rubric-list dd { margin: 0; color: #7183a0; overflow-wrap: anywhere; }
    .attempt-answer-feedback { display: grid; gap: 3px; margin-top: 9px; padding-top: 8px; border-top: 1px solid #edf1f7; }
    .attempt-answer-feedback strong { color: #536b8e; font-size: 9px; }
    .attempt-answer-feedback p { margin: 0; color: #40587b; font-size: 10px; line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere; }
    .attempt-grade-objective { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; color: #536b8e; font-size: 10px; }
    .attempt-grade-objective strong { color: #173f78; font-variant-numeric: tabular-nums; }
    .attempt-grade-state { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 999px; font-size: 9px; font-weight: 700; }
    .attempt-grade-state.is-correct { color: #13805d; background: #e6f8ee; }
    .attempt-grade-state.is-incorrect { color: #a96312; background: #fff3e5; }
    .attempt-history-values pre { max-height: 260px; margin: 0; overflow: auto; color: #425a7b; font: 10px/1.55 ui-monospace, SFMono-Regular, Consolas, monospace; white-space: pre-wrap; overflow-wrap: anywhere; }
    .attempt-detail-empty { display: grid; gap: 5px; padding: 22px 14px; color: var(--muted, #7184a1); text-align: center; }
    .attempt-detail-empty strong { color: var(--ink, #142c55); font-size: 12px; }
    .attempt-detail-empty span { font-size: 11px; }
    .attempt-report-row { display: grid; gap: 7px; padding: 12px 0; border-top: 1px solid #eaf0f8; }
    .attempt-report-row:first-of-type { padding-top: 0; border-top: 0; }
    .attempt-report-heading { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .attempt-report-heading strong { color: #20395f; font-size: 11px; }
    .attempt-report-heading time { margin-left: auto; color: #7183a0; font-size: 10px; font-variant-numeric: tabular-nums; }
    .attempt-report-status.is-attention { color: #a96312; background: #fff3e5; }
    .attempt-report-status.is-neutral { color: #5e6d83; background: #edf1f6; }
    .attempt-report-row p { margin: 0; color: #536b8e; font-size: 11px; line-height: 1.55; overflow-wrap: anywhere; }
    .attempt-history-row { border-top: 1px solid #eaf0f8; }
    .attempt-history-row:first-of-type { border-top: 0; }
    .attempt-history-row > summary { display: grid; grid-template-columns: minmax(150px, .7fr) minmax(170px, 1fr) minmax(170px, 1fr) auto; gap: 10px; align-items: center; padding: 11px 2px; cursor: pointer; list-style: none; }
    .attempt-history-row > summary::-webkit-details-marker { display: none; }
    .attempt-history-row time,
    .attempt-history-row > summary > span { color: #7183a0; font-size: 10px; }
    .attempt-history-row > summary strong { color: #304b70; font-size: 10px; overflow-wrap: anywhere; }
    .attempt-history-action { color: var(--blue, #0b66ef) !important; font-weight: 700; white-space: nowrap; }
    .attempt-history-values { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; padding: 0 0 12px; }
    .attempt-history-values section { min-width: 0; padding: 10px; border: 1px solid #e6edf7; border-radius: 8px; background: #fbfcff; }
    .attempt-history-values h3 { margin: 0 0 7px; color: #536b8e; font-size: 10px; }
    .attempt-history-pagination { padding-top: 11px; border-top: 1px solid #eaf0f8; }
    .attempt-detail :focus-visible { outline: 3px solid rgba(11, 102, 239, .38); outline-offset: 2px; }

    @media (max-width: 1100px) {
        .attempt-overview { grid-template-columns: minmax(0, 1fr); }
        .attempt-overview-main { padding-right: 0; border-right: 0; }
        .attempt-detail-columns { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 760px) {
        .attempt-detail-heading h1 { font-size: 21px; }
        .attempt-overview-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .attempt-question-expanded { grid-template-columns: minmax(0, 1fr); }
        .attempt-answer-data { grid-template-columns: minmax(0, 1fr); }
        .attempt-answer-data .attempt-options-card { grid-column: auto; }
    }
    @media (max-width: 560px) {
        .attempt-detail { gap: 10px; }
        .attempt-detail .panel.attempt-overview,
        .attempt-detail .panel.attempt-detail-card { padding: 12px; border-radius: 10px; }
        .attempt-learner-avatar { width: 45px; height: 45px; }
        .attempt-learner-copy h2 { font-size: 15px; }
        .attempt-overview-facts { grid-template-columns: minmax(0, 1fr); gap: 8px; }
        .attempt-overview-facts > div { grid-template-columns: 112px minmax(0, 1fr); }
        .attempt-overview-stats { gap: 7px; }
        .attempt-stat { min-height: 60px; gap: 7px; padding: 8px; }
        .attempt-stat-icon { width: 30px; height: 30px; }
        .attempt-stat > div > span { font-size: 9px; }
        .attempt-stat strong { font-size: 12px; }
        .attempt-moderation-form { grid-template-columns: minmax(0, 1fr); gap: 10px; }
        .attempt-moderation-actions { grid-row: auto; }
        .attempt-questions-heading { align-items: flex-start; }
        .attempt-questions-heading .attempt-card-heading h2 { font-size: 14px; }
        .attempt-question-totals { gap: 5px; }
        .attempt-question-totals span { font-size: 9px; }
        .attempt-history-row > summary { grid-template-columns: minmax(0, 1fr) auto; }
        .attempt-history-row > summary strong { grid-column: 1; }
        .attempt-history-row > summary > span:not(.attempt-history-action) { grid-column: 1; }
        .attempt-history-action { grid-column: 2; grid-row: 1 / span 3; }
        .attempt-history-values { grid-template-columns: minmax(0, 1fr); }
    }
</style>
