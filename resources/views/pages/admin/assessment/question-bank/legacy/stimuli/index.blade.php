@php
    $isReading = $stimulusType === 'reading';
    $statusLabels = ['published' => 'Đã xuất bản', 'draft' => 'Bản nháp', 'review' => 'Cần rà soát'];
    $resourceLabel = $isReading ? 'bài đọc' : 'audio';
    $storeRoute = $isReading ? route('admin.readings.store') : route('admin.audio.store');
    $formatDuration = static function (?int $seconds): string {
        if (! $seconds) {
            return '—';
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    };
@endphp

@if (isset($errors) && $errors->any())
    <div class="admin-alert admin-alert-error"><b>Chưa thể thêm {{ $resourceLabel }}.</b><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="resource-toolbar"><div class="admin-tabs"><a class="{{ $isReading ? 'is-active' : '' }}" href="{{ route('admin.readings.index') }}">Bài đọc IELTS</a><a class="{{ ! $isReading ? 'is-active' : '' }}" href="{{ route('admin.audio.index') }}">Audio IELTS</a></div><details class="resource-create"><summary class="button button-primary">@include('pages.dictionary.icon', ['name' => 'plus']) Thêm {{ $resourceLabel }} mới</summary><form class="panel resource-create-form" method="post" action="{{ $storeRoute }}" enctype="multipart/form-data">@csrf<input type="hidden" name="stimulus_type" value="{{ $stimulusType }}"><div class="section-head compact"><div><span class="eyebrow">TẠO {{ $isReading ? 'BÀI ĐỌC' : 'AUDIO' }}</span><h3>Thông tin cơ bản</h3></div></div><div class="admin-form-grid"><label>Tiêu đề *<input name="title" value="{{ old('title') }}" required></label>@if (! $isReading)<label>Accent / giọng<select name="accent"><option value="">Chọn accent</option>@foreach (['British', 'American', 'Australian'] as $accent)<option value="{{ $accent }}" @selected(old('accent') === $accent)>{{ $accent }}</option>@endforeach</select></label>@endif<label class="admin-form-wide">Media URL<input type="url" name="asset_path" value="{{ old('asset_path') }}" placeholder="https://..."></label>@if (! $isReading)<label>Thời lượng (giây)<input type="number" name="duration_seconds" min="1" value="{{ old('duration_seconds') }}"></label>@endif<label>Trạng thái<select name="status"><option value="draft" @selected(old('status', 'draft') === 'draft')>Bản nháp</option><option value="review" @selected(old('status') === 'review')>Cần rà soát</option><option value="published" @selected(old('status') === 'published')>Đã xuất bản</option></select></label><label class="admin-form-wide">Tải file<input type="file" name="media" accept=".mp3,.wav,.m4a,.ogg,.jpg,.jpeg,.png,.webp"><small class="form-help">Tối đa 50 MB. File được lưu trong storage/public.</small></label></div>@if ($isReading)<label class="admin-label">Nội dung bài đọc<textarea class="editor-textarea" name="body" rows="7">{{ old('body') }}</textarea></label>@else<label class="admin-label">Transcript<textarea class="editor-textarea" name="transcript" rows="7">{{ old('transcript') }}</textarea></label>@endif<div class="resource-form-actions"><button class="button button-quiet" type="reset">Xóa form</button><button class="button button-primary" type="submit">Lưu {{ $resourceLabel }}</button></div></form></details></div>

<div class="resource-layout">
    <section class="panel resource-list-panel">
        <div class="section-head compact"><div><span class="eyebrow">DANH SÁCH {{ $isReading ? 'BÀI ĐỌC' : 'AUDIO' }}</span><h2>{{ number_format($stimuli->total()) }} {{ $resourceLabel }}</h2></div></div>
        <form class="search-bar resource-search" method="get"><span>@include('pages.dictionary.icon', ['name' => 'search'])</span><input name="q" value="{{ $search }}" placeholder="Tìm theo tiêu đề..." aria-label="Tìm {{ $resourceLabel }}"></form>
        <div class="resource-items">
            @forelse ($stimuli as $stimulus)
                <a class="resource-row {{ $selectedStimulus?->id === $stimulus->id ? 'is-active' : '' }}" href="{{ $isReading ? route('admin.readings.index', ['stimulus' => $stimulus->id, 'q' => $search]) : route('admin.audio.index', ['stimulus' => $stimulus->id, 'q' => $search]) }}"><span class="category-icon {{ $isReading ? 'violet' : 'coral' }}">@include('pages.dictionary.icon', ['name' => $isReading ? 'book-open' : 'listening'])</span><span><b>{{ $stimulus->title }}</b><small>{{ $stimulus->questions_count }} câu hỏi liên kết · {{ $isReading ? 'Reading' : ($stimulus->accent ?: 'Chưa chọn accent') }}</small></span><span class="badge {{ $stimulus->status === 'published' ? 'badge-mint' : ($stimulus->status === 'review' ? 'badge-amber' : 'badge-soft') }}">{{ $statusLabels[$stimulus->status] ?? $stimulus->status }}</span></a>
            @empty
                <div class="admin-empty compact-empty"><span class="category-icon {{ $isReading ? 'violet' : 'coral' }}">@include('pages.dictionary.icon', ['name' => $isReading ? 'book-open' : 'listening'])</span><p>Chưa có {{ $resourceLabel }} nào.</p></div>
            @endforelse
        </div>
        @if ($stimuli->hasPages())<div class="pagination resource-pagination">{{ $stimuli->onEachSide(1)->links() }}</div>@endif
    </section>

    <section class="panel resource-detail">
        @if ($selectedStimulus)
            <div class="resource-detail-head"><div><span class="eyebrow">CHI TIẾT {{ $isReading ? 'BÀI ĐỌC' : 'AUDIO' }}</span><h2>{{ $selectedStimulus->title }}</h2><div class="tag-row"><span class="badge badge-blue">IELTS</span><span class="badge badge-soft">{{ $selectedStimulus->questions->count() }} câu hỏi liên kết</span><span class="badge {{ $selectedStimulus->status === 'published' ? 'badge-mint' : 'badge-amber' }}">{{ $statusLabels[$selectedStimulus->status] ?? $selectedStimulus->status }}</span></div></div><a class="button button-quiet" href="{{ route('admin.questions.create', ['stimulus' => $selectedStimulus->id]) }}">@include('pages.dictionary.icon', ['name' => 'plus']) Thêm câu hỏi</a></div>
            @if ($isReading)
                <div class="resource-meta-grid"><span><small>Loại bài đọc</small><b>IELTS Academic Reading</b></span><span><small>Số từ</small><b>{{ $selectedStimulus->body ? str_word_count(strip_tags($selectedStimulus->body)) : 0 }}</b></span><span><small>Cập nhật</small><b>{{ optional($selectedStimulus->updated_at)->format('d/m/Y') }}</b></span></div>
                <article class="resource-content"><h3>Nội dung bài đọc</h3><p>{{ $selectedStimulus->body ?: 'Chưa nhập nội dung bài đọc.' }}</p></article>
            @else
                <div class="resource-meta-grid"><span><small>Accent / giọng</small><b>{{ $selectedStimulus->accent ?: 'Chưa chọn' }}</b></span><span><small>Thời lượng</small><b>{{ $formatDuration($selectedStimulus->duration_seconds) }}</b></span><span><small>Trạng thái</small><b>{{ $statusLabels[$selectedStimulus->status] ?? $selectedStimulus->status }}</b></span></div>
                @if ($assetUrl)<div class="audio-player"><audio controls preload="none" src="{{ $assetUrl }}">Trình duyệt không hỗ trợ phát audio.</audio><span>@include('pages.dictionary.icon', ['name' => 'listening'])</span><b>{{ $selectedStimulus->title }}</b></div>@else<div class="media-placeholder">Chưa có file audio để phát.</div>@endif
                <article class="resource-content"><h3>Transcript (bản ghi)</h3><p>{{ $selectedStimulus->transcript ?: 'Chưa nhập transcript.' }}</p></article>
            @endif
            <div class="linked-questions"><div class="section-head compact"><div><span class="eyebrow">CÂU HỎI LIÊN KẾT</span><h3>{{ $selectedStimulus->questions->count() }} câu hỏi</h3></div><a class="text-link" href="{{ route('admin.questions.index', ['stimulus' => $selectedStimulus->id]) }}">Xem trong kho câu hỏi @include('pages.dictionary.icon', ['name' => 'arrow-right'])</a></div>@forelse ($selectedStimulus->questions as $question)<a class="linked-question-row" href="{{ route('admin.questions.edit', $question) }}"><span class="question-number">{{ $loop->iteration }}</span><span><b>{{ $question->questionType?->name }}</b><small>{{ \Illuminate\Support\Str::limit($question->prompt, 90) }}</small></span><span class="badge badge-blue">{{ $question->difficulty?->name }}</span></a>@empty<div class="admin-empty compact-empty"><p>Chưa có câu hỏi liên kết. Hãy tạo câu hỏi và chọn {{ $isReading ? 'bài đọc' : 'audio' }} này ở phần thông tin câu hỏi.</p></div>@endforelse</div>
        @else
            <div class="admin-empty"><span class="category-icon blue">@include('pages.dictionary.icon', ['name' => $isReading ? 'book-open' : 'listening'])</span><h3>Chọn một {{ $resourceLabel }}</h3><p>Danh sách bên trái sẽ hiển thị chi tiết tại đây.</p></div>
        @endif
    </section>
</div>
