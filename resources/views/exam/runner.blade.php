@extends($preview ? 'layouts.admin-app' : 'layouts.app')

@section('content')
<main class="assessment-page exam-session-page" data-exam-runner>
    <header class="exam-session-toolbar">
        <div class="exam-session-heading">
            <nav class="exam-breadcrumb" aria-label="Đường dẫn">
                <a href="{{ route($preview ? 'admin.exams.index' : 'exams.index') }}" aria-label="Kho đề thi">@include('pages.dictionary.icon', ['name' => 'home'])</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route($preview ? 'admin.exams.index' : 'exams.index') }}">Làm đề thi</a>
                <span aria-hidden="true">/</span>
                <span>{{ $snapshot['title'] }}</span>
            </nav>
            <div class="exam-session-title-row">
                <h1>{{ $snapshot['title'] }}</h1>
                <span class="exam-session-subtitle">{{ $preview ? 'Xem trước bài thi' : 'Bài thi theo cấu trúc đề thật' }}</span>
            </div>
        </div>
        <div class="exam-session-clock-wrap">
            <div class="exam-session-clock" aria-label="Thời gian còn lại">
                @include('pages.dictionary.icon', ['name' => 'clock'])
                <div><span>Thời gian còn lại</span><strong data-timer>--:--</strong></div>
            </div>
            <button class="button button-primary exam-submit-button" type="button" data-submit>@include('pages.dictionary.icon', ['name' => 'arrow-right']) Nộp bài</button>
        </div>
    </header>

    @if ($preview)
        <div class="assessment-actions exam-preview-actions">
            <label>Thiết bị <select data-viewport><option value="desktop">Desktop</option><option value="tablet">Tablet</option>@if ($snapshot['policies']['allow_mobile'] ?? true)<option value="mobile">Mobile</option>@endif</select></label>
        </div>
    @endif

    <section class="exam-session-progress" aria-label="Tiến độ bài làm">
        <div class="exam-progress-main">
            <div class="exam-progress-label"><span>Tiến độ làm bài</span><strong><span data-answered-count>0</span> / <span data-question-total>{{ count($snapshot['items']) }}</span> câu</strong></div>
            <div class="exam-progress-track"><i data-progress-bar></i></div>
        </div>
        <div class="exam-progress-stat is-answered"><span class="exam-stat-dot"></span><span>Đã trả lời</span><strong data-answered-summary>0</strong></div>
        <div class="exam-progress-stat is-unanswered"><span class="exam-stat-dot"></span><span>Chưa trả lời</span><strong data-unanswered-count>{{ count($snapshot['items']) }}</strong></div>
        <div class="exam-progress-stat is-flagged"><span class="exam-stat-dot"></span><span>Đánh dấu</span><strong data-flagged-count>0</strong></div>
    </section>

    @if ($warnings)
        <div class="admin-alert admin-alert-error"><b>Cần hoàn thiện trước khi xuất bản</b><ul>@foreach ($warnings as $warning)<li>{{ $warning }}</li>@endforeach</ul></div>
    @endif
    <p class="notice-box exam-session-warning" data-warning role="alert" hidden></p>

    <div class="assessment-actions exam-session-actions">
        <span class="exam-session-save-status" data-save-status role="status">Sẵn sàng</span>
        <div>
            <button class="button button-quiet" type="button" data-fullscreen>Toàn màn hình</button>
            <button class="button button-quiet" type="button" data-highlight>@include('pages.dictionary.icon', ['name' => 'saved']) Ghi chú</button>
            @if ($snapshot['policies']['pause_allowed'] ?? false)
                <button class="button button-quiet" type="button" data-pause>Tạm dừng</button>
            @endif
            <button class="button button-quiet" type="button" data-next-section>Phần tiếp theo</button>
        </div>
    </div>

    <div class="assessment-layout exam-session-layout">
        <aside class="panel assessment-navigation exam-question-sidebar" data-navigation aria-label="Danh sách câu hỏi"></aside>
        <section class="exam-session-items" data-items aria-live="polite"></section>
    </div>

    <script type="application/json" data-exam-config>{!! json_encode(['preview' => $preview, 'snapshot' => $snapshot, 'state' => $state ?? null, 'endpoints' => $endpoints, 'csrf' => csrf_token(), 'watermark' => auth()->user()?->email], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) !!}</script>
</main>
@endsection
