@php
    $statusLabels = ['published' => 'Đã xuất bản', 'draft' => 'Bản nháp', 'review' => 'Chờ duyệt', 'approved'=>'Đã duyệt', 'archived'=>'Đã lưu trữ'];
    $difficultyLabels = ['easy' => 'Dễ', 'medium' => 'Trung bình', 'hard' => 'Khó'];
    $isThptEnglish = request()->routeIs('admin.questions.thpt');
    $thptFamilyLabel = fn ($part) => match ($part?->code) {
        'tieng-anh-part-1', 'tieng-anh-part-2' => 'Điền từ ngắn',
        'tieng-anh-part-3' => 'Sắp xếp câu',
        'tieng-anh-part-4' => 'Điền cụm / câu',
        'tieng-anh-part-5', 'tieng-anh-part-6' => 'Đọc hiểu',
        default => $part?->name ?? '—',
    };
    $thptPartFamily = fn ($code) => match ($code) {
        'tieng-anh-part-1', 'tieng-anh-part-2' => 'short_cloze',
        'tieng-anh-part-3' => 'arrangement',
        'tieng-anh-part-4' => 'extended_completion',
        'tieng-anh-part-5', 'tieng-anh-part-6' => 'reading',
        default => 'short_cloze',
    };
    $partLabel = fn ($part) => $part?->examType?->code === 'toeic' ? ucfirst(str_replace('-', ' ', $part->code)) : ($isThptEnglish ? match ($part?->code) {
        'tieng-anh-part-1' => 'Phần 1 · Điền từ ngắn',
        'tieng-anh-part-2' => 'Phần 2 · Điền từ ngắn',
        'tieng-anh-part-3' => 'Phần 3 · Sắp xếp câu',
        'tieng-anh-part-4' => 'Phần 4 · Điền cụm / câu',
        'tieng-anh-part-5' => 'Phần 5 · Đọc hiểu',
        'tieng-anh-part-6' => 'Phần 6 · Đọc hiểu',
        default => $part?->name ?? '—',
    } : ($part?->name ?? '—'));
    $bankRouteName = request()->routeIs('admin.questions.thpt') ? 'admin.questions.thpt' : 'admin.questions.index';
    $defaultThptPart = $parts->firstWhere('code', 'tieng-anh-part-1') ?? $parts->first();
    $thptFamilyDefaults = [
        'short_cloze' => $parts->firstWhere('code', 'tieng-anh-part-1') ?? $defaultThptPart,
        'arrangement' => $parts->firstWhere('code', 'tieng-anh-part-3') ?? $defaultThptPart,
        'extended_completion' => $parts->firstWhere('code', 'tieng-anh-part-4') ?? $defaultThptPart,
        'reading' => $parts->firstWhere('code', 'tieng-anh-part-5') ?? $defaultThptPart,
    ];
    $defaultQuestionTypeId = $questionTypes->firstWhere('code', 'multiple_choice')?->id ?? $questionTypes->first()?->id;
    $defaultDifficultyId = $difficulties->firstWhere('code', 'medium')?->id ?? $difficulties->first()?->id;
    $listPaginator = $isThptEnglish ? $questions : $entries;
@endphp

@if ($errors->any())
    <div class="admin-alert admin-alert-error">
        <b>{{ $isThptEnglish ? 'Không thể lưu câu hỏi.' : 'Không thể import CSV.' }}</b>
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@if (session('import_summary'))
    @php
        $importSummary = session('import_summary');
        $importHasErrors = $importSummary['failed'] > 0;
    @endphp
    <div class="admin-toast {{ $importHasErrors ? 'admin-toast-warning' : '' }}" role="{{ $importHasErrors ? 'alert' : 'status' }}" aria-live="polite" data-admin-toast data-admin-toast-timeout="{{ $importHasErrors ? 0 : 6000 }}">
        <span>Đã xử lý {{ number_format($importSummary['total']) }} dòng: <b>{{ number_format($importSummary['imported']) }} câu hỏi thành công</b>, {{ number_format($importSummary['failed']) }} dòng lỗi.</span>
        @if (session('import_errors'))
            <details class="import-errors"><summary>Xem chi tiết dòng lỗi</summary><ul>@foreach (session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul></details>
        @endif
        <button type="button" data-dismiss-admin-toast aria-label="Đóng thông báo">×</button>
    </div>
@endif

@can('content.edit')<a class="button button-quiet" href="{{ route('admin.questions.import-template', ['template'=>'readings']) }}">Mẫu bài đọc kèm câu hỏi</a>@endcan
<div class="question-bank {{ $isThptEnglish ? 'is-thpt qb-preview-closed' : '' }}" data-question-bank>
    @if($isThptEnglish)
        <header class="qb-heading qb-heading-thpt">
            <div><h1>Ngân hàng câu hỏi · Tiếng Anh THPT Quốc gia</h1><p>Quản lý câu hỏi, nhóm văn bản và trạng thái duyệt.</p></div>
            <div class="qb-heading-actions">
                @can('content.edit')<a class="button button-quiet" href="{{ route('admin.questions.deleted') }}">Câu hỏi đã xóa</a>@endcan
                <a class="button button-quiet" href="{{ route('admin.groups.index') }}">@include('pages.dictionary.icon', ['name' => 'collection']) Tạo nhóm đề</a>
                <a class="button button-quiet" href="#qb-thpt-import">@include('pages.dictionary.icon', ['name' => 'collection']) Import câu hỏi</a>
                @include('pages.admin.assessment.question-bank.workspace.send-all-review')
                <a class="button button-primary" href="#qb-thpt-create">@include('pages.dictionary.icon', ['name' => 'plus']) Thêm câu hỏi</a>
                <a class="button button-quiet qb-export-link" href="{{ route('admin.questions.export') }}">Export CSV</a>
            </div>
        </header>
        <div class="qb-thpt-context" aria-label="Cấu trúc đề Tiếng Anh THPT Quốc gia">
            <span><b>40</b> câu</span><span><b>50</b> phút</span><span><b>A–D</b> · 1 đáp án đúng</span><span><b>0,25</b> điểm/câu</span><span><b>{{ number_format($thptStats['groups'] ?? 0) }}</b> nhóm văn bản</span><span>Đã duyệt <b>{{ number_format($thptStats['approved'] ?? 0) }}</b></span><span>Chưa duyệt <b>{{ number_format($thptStats['pending'] ?? 0) }}</b></span>
        </div>
        <section class="qb-stats qb-thpt-stats" aria-label="Thống kê ngân hàng câu hỏi THPT">
            @foreach([
                ['label' => 'Tổng câu hỏi', 'value' => $thptStats['total'] ?? 0, 'icon' => 'book-open', 'tone' => 'blue'],
                ['label' => 'Điền từ ngắn', 'value' => $thptStats['short_cloze'] ?? 0, 'icon' => 'text-cursor-input', 'tone' => 'green'],
                ['label' => 'Sắp xếp câu', 'value' => $thptStats['arrangement'] ?? 0, 'icon' => 'list-ordered', 'tone' => 'violet'],
                ['label' => 'Điền cụm / câu', 'value' => $thptStats['extended_completion'] ?? 0, 'icon' => 'text-cursor-input', 'tone' => 'orange'],
                ['label' => 'Đọc hiểu', 'value' => $thptStats['reading'] ?? 0, 'icon' => 'book-open-text', 'tone' => 'pink'],
            ] as $stat)
                <article class="qb-stat"><span class="qb-stat-icon is-{{ $stat['tone'] }}">@include('pages.dictionary.icon', ['name' => $stat['icon']])</span><div><span>{{ $stat['label'] }}</span><strong>{{ number_format($stat['value']) }}</strong></div></article>
            @endforeach
        </section>
    @else
        <header class="qb-heading">
            <h1>Ngân hàng câu hỏi</h1>
            <div class="qb-heading-actions">
                @can('content.edit')<a class="button button-quiet" href="{{ route('admin.questions.deleted') }}">Câu hỏi đã xóa</a>@endcan
                <a class="button button-quiet" href="{{ route('admin.questions.export') }}">@include('pages.dictionary.icon', ['name' => 'external-link']) Export CSV</a>
                @can('content.edit')<a class="button button-primary" href="{{ route('admin.questions.create') }}">@include('pages.dictionary.icon', ['name' => 'plus']) Tạo câu hỏi</a>@endcan
            </div>
        </header>
        @include('pages.admin.assessment.question-bank.questions.import-panel')
    @endif
    <div class="qb-workspace">
        <section class="qb-directory" aria-label="Danh sách câu hỏi">
            <div class="qb-filters">
                <form id="qb-filter-form" class="qb-filter-form" method="get" action="{{ route($bankRouteName) }}" data-question-filters data-question-search data-ajax-filter-form data-ajax-target="#qb-results" data-ajax-debounce="350" @if($isThptEnglish)data-thpt-filters @endif>
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    <input type="hidden" name="per_page" value="{{ $filters['perPage'] }}">
                    @unless($isThptEnglish)<input type="hidden" name="family" value="{{ $filters['family'] }}">@endunless
                    @if($isThptEnglish)<input type="hidden" name="exam" value="{{ $filters['exam'] }}">@endif
                    <div class="qb-filter-grid">
                        <label class="qb-filter-search">Tìm bài<div class="search-bar"><span>@include('pages.dictionary.icon', ['name' => 'search'])</span><input type="search" name="q" value="{{ $filters['search'] }}" placeholder="Mã bài hoặc tiêu đề" aria-label="Tìm bài hoặc câu hỏi" autocomplete="off"></div></label>
                        @unless($isThptEnglish)<label>Kỳ thi<select name="exam"><option value="">Tất cả kỳ thi</option>@foreach($examTypes as $examType)<option value="{{ $examType->code }}" @selected($filters['exam'] === $examType->code)>{{ $examType->name }}</option>@endforeach</select></label>@endunless
                        <label>Part / Section<select name="part"><option value="">{{ $isThptEnglish ? 'Tất cả phần' : 'Tất cả Part' }}</option>@foreach($parts as $partOption)<option value="{{ $partOption->code }}" @selected($filters['part'] === $partOption->code)>{{ $isThptEnglish ? $partLabel($partOption) : $partOption->examType->name.' · '.$partLabel($partOption) }}</option>@endforeach</select></label>
                        <label>Trạng thái<select name="status"><option value="">Tất cả</option>@foreach($statusLabels as $code => $label)<option value="{{ $code }}" @selected($filters['status'] === $code)>{{ $label }}</option>@endforeach</select></label>
                        @if(!$isThptEnglish)
                            <label>Có audio<select name="audio"><option value="">Tất cả</option><option value="yes" @selected($filters['audio'] === 'yes')>Có audio</option><option value="no" @selected($filters['audio'] === 'no')>Không có audio</option></select></label>
                        @else
                            <label>Dạng nội dung<select name="family"><option value="">Tất cả</option>@foreach($thptFamilies as $familyCode => $familyName)<option value="{{ $familyCode }}" @selected($filters['family'] === $familyCode)>{{ $familyName }}</option>@endforeach</select></label>
                        @endif
                    </div>
                    <details class="qb-advanced-filters">
                        <summary>Bộ lọc khác</summary>
                        <div class="qb-filter-grid">
                            <label>Mức độ<select name="difficulty"><option value="">Tất cả</option>@foreach($difficulties as $difficulty)<option value="{{ $difficulty->code }}" @selected($filters['difficulty'] === $difficulty->code)>{{ $difficultyLabels[$difficulty->code] ?? $difficulty->name }}</option>@endforeach</select></label>
                            <label>{{ $isThptEnglish ? 'Có văn bản' : 'Có bài đọc' }}<select name="reading"><option value="">Tất cả</option><option value="yes" @selected($filters['reading'] === 'yes')>{{ $isThptEnglish ? 'Có văn bản' : 'Có bài đọc' }}</option><option value="no" @selected($filters['reading'] === 'no')>{{ $isThptEnglish ? 'Không có văn bản' : 'Không có bài đọc' }}</option></select></label>
                            <label>Dạng câu hỏi<select name="type"><option value="">Tất cả</option>@foreach($filterQuestionTypes as $questionType)<option value="{{ $questionType->code }}" @selected($filters['type'] === $questionType->code)>{{ $questionType->name }}</option>@endforeach</select></label>
                        </div>
                    </details>
                    <div class="qb-filter-footer">
                        <label class="qb-missing-filter"><input type="checkbox" name="missing" value="1" @checked($filters['missing'])> Thiếu tài nguyên</label>
                        <div class="qb-filter-actions"><button class="button button-primary" type="submit">Tìm kiếm</button><a class="qb-reset" data-ajax-filter-reset="#qb-results" href="{{ route($bankRouteName) }}">@include('pages.dictionary.icon', ['name' => 'filter']) Xóa bộ lọc</a></div>
                    </div>
                    <script type="application/json" data-question-filter-catalog>{!! json_encode($filterCatalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
                </form>
            </div>
            <section class="qb-list">
                <div class="qb-list-heading">
                    @if($isThptEnglish)<span data-qb-visible-count>Hiển thị {{ number_format($questions->firstItem() ?? 0) }}–{{ number_format($questions->lastItem() ?? 0) }} trong {{ number_format($questions->total()) }} câu hỏi</span>
                    @else<span data-qb-visible-count>Hiển thị {{ number_format($entries->firstItem() ?? 0) }}–{{ number_format($entries->lastItem() ?? 0) }} trong {{ number_format($entries->total()) }} mục · {{ count($matchingQuestionIds) }} câu phù hợp</span>@endif
                    <label>Sắp xếp: <select data-qb-sort aria-label="Sắp xếp câu hỏi"><option value="newest" @selected($filters['sort'] === 'newest')>Cập nhật mới nhất</option><option value="oldest" @selected($filters['sort'] === 'oldest')>Cập nhật cũ nhất</option><option value="id" @selected($filters['sort'] === 'id')>Mã giảm dần</option></select></label>
                </div>
                <form id="question-bulk" class="qb-bulk" method="post" action="{{ route('admin.questions.bulk') }}">@csrf
                    @if($isThptEnglish)<input type="hidden" name="return_to" value="thpt">@endif
                    <script type="application/json" data-question-matching-ids>{!! json_encode($matchingQuestionIds) !!}</script>
                    <span data-question-selection-summary aria-live="polite"></span><input type="hidden" name="action" value="">
                    @can('content.edit')<button class="button button-danger" type="button" data-delete-selected-questions>Xóa / ngừng dùng câu đã chọn</button>@endcan
                    @can('content.review')<button class="button button-primary" type="button" data-approve-selected-questions disabled>Duyệt câu đang chờ đã chọn</button>@endcan
                    @if($isThptEnglish)@can('content.edit')<button class="button button-primary" type="button" data-submit-selected-thpt-review disabled>Gửi duyệt câu nháp đã chọn</button>@endcan @endif
                    <script type="application/json" data-question-review-revisions>{!! json_encode(collect($bankData['records'])->filter(fn (array $record): bool => ($record['status'] ?? null) === 'review')->mapWithKeys(fn (array $record, $id): array => [(string) $id => (int) $record['question']->draft_revision_id])->all()) !!}</script>
                    @if($isThptEnglish)<script type="application/json" data-question-draft-ids>{!! json_encode(collect($bankData['records'])->filter(fn (array $record): bool => ($record['status'] ?? null) === 'draft')->keys()->map(fn ($id): string => (string) $id)->values()) !!}</script>@endif
                    @if($isThptEnglish)
                        @foreach($questions->getCollection() as $question)<input type="checkbox" form="question-bulk" name="ids[]" value="{{ $question->id }}" hidden aria-label="Chọn câu {{ $question->id }}">@endforeach
                    @else
                        @foreach($entries->getCollection()->flatMap(fn ($entry) => $entry['matching_ids'])->unique() as $id)<input type="checkbox" form="question-bulk" name="ids[]" value="{{ $id }}" hidden aria-label="Chọn câu {{ $id }}">@endforeach
                    @endif
                </form>
                @if($isThptEnglish)
                    @can('content.edit')<form id="thpt-review-bulk" method="post" action="{{ route('admin.questions.bulk') }}" data-thpt-review-bulk data-pending-label="Đang gửi duyệt…" hidden>@csrf<input type="hidden" name="action" value="review"><input type="hidden" name="return_to" value="thpt"></form>@endcan
                @endif
                @can('content.review')<form id="question-approval-bulk" method="post" action="{{ route('admin.questions.approve') }}" hidden>@csrf</form>@endcan
                <div class="import-progress bulk-delete-progress" data-delete-progress hidden aria-live="polite"><div class="import-progress-heading"><b data-delete-progress-label>Đang xóa câu hỏi…</b><span data-delete-progress-count>0%</span></div><progress max="100" value="0" data-delete-progress-bar aria-label="Tiến độ xóa câu hỏi"></progress></div>
                <div id="qb-results" data-ajax-results>
                    <script type="application/json" data-ajax-qb-matching-ids>{!! json_encode($matchingQuestionIds) !!}</script>
                    <script type="application/json" data-ajax-qb-review-revisions>{!! json_encode(collect($bankData['records'])->filter(fn (array $record): bool => ($record['status'] ?? null) === 'review')->mapWithKeys(fn (array $record, $id): array => [(string) $id => (int) $record['question']->draft_revision_id])->all()) !!}</script>
                    <script type="application/json" data-ajax-qb-draft-ids>{!! json_encode(collect($bankData['records'])->filter(fn (array $record): bool => ($record['status'] ?? null) === 'draft')->keys()->map(fn ($id): string => (string) $id)->values()) !!}</script>
                    <span class="sr-only" data-ajax-qb-result-count>
                        @if($isThptEnglish)
                            Hiển thị {{ number_format($questions->firstItem() ?? 0) }}–{{ number_format($questions->lastItem() ?? 0) }} trong {{ number_format($questions->total()) }} câu hỏi
                        @else
                            Hiển thị {{ number_format($entries->firstItem() ?? 0) }}–{{ number_format($entries->lastItem() ?? 0) }} trong {{ number_format($entries->total()) }} mục · {{ count($matchingQuestionIds) }} câu phù hợp
                        @endif
                    </span>
                <div class="qb-table-scroll">
                    @if($isThptEnglish)
                    <table class="qb-table qb-thpt-table">
                        <thead><tr><th class="qb-checkbox"><input type="checkbox" data-select-all aria-label="Chọn toàn bộ câu hỏi phù hợp bộ lọc"></th><th>Mã câu hỏi</th><th class="qb-content-cell">Nội dung rút gọn</th><th>Kỹ năng / Dạng</th><th>Mã đề / Nhóm</th><th>Tệp</th><th>Mức độ</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr></thead>
                        <tbody>
                        @forelse($questions as $question)
                            @php
                                $record = $bankData['records'][$question->id] ?? [];
                                $payload = $previewPayloads[$question->id] ?? [];
                                $groupId = (int) ($payload['group_id'] ?? $question->question_group_id ?? 0);
                                $group = $thptGroupsById->get($groupId);
                                $previewKey = $record['keys'][0] ?? 'question:'.$question->id;
                                $promptText = trim(strip_tags((string) ($payload['prompt'] ?? $question->prompt ?? '')));
                                $hasText = (bool) ($record['reading'] ?? false) || ! empty($payload['stimulus_ids']);
                                $hasImage = ! empty($payload['metadata']['image_id']);
                                $questionStatus = $record['status'] ?? 'draft';
                            @endphp
                            <tr data-qb-row="{{ $previewKey }}">
                                <td class="qb-checkbox"><input type="checkbox" data-qb-select-entry data-question-ids="[{{ $question->id }}]" aria-label="Chọn câu Q-{{ str_pad((string)$question->id, 5, '0', STR_PAD_LEFT) }}"></td>
                                <td class="qb-id"><button class="reading-code" type="button" data-qb-preview="{{ $previewKey }}" aria-controls="qb-preview">Q-{{ str_pad((string)$question->id, 5, '0', STR_PAD_LEFT) }}</button></td>
                                <td class="qb-content-cell"><button class="qb-prompt" type="button" data-qb-preview="{{ $previewKey }}" aria-controls="qb-preview"><span>{{ \Illuminate\Support\Str::limit($promptText ?: 'Chưa nhập nội dung câu hỏi', 105) }}</span></button></td>
                                <td><span class="qb-family-label">{{ $thptFamilyLabel($question->examPart) }}</span></td>
                                <td>@if($group)<button class="reading-code" type="button" data-qb-preview="{{ $previewKey }}">{{ $group->title }}</button><small class="qb-part-name">Nhóm văn bản</small>@else<span class="qb-standalone">Câu độc lập</span>@endif</td>
                                <td class="qb-resource-cell">@if($hasText)<span class="qb-resource-tag">Văn bản</span>@endif @if($hasImage)<span class="qb-resource-tag">Ảnh</span>@endif @if(!$hasText && !$hasImage)<span class="qb-empty-value">—</span>@endif</td>
                                <td><span class="badge badge-soft">{{ $difficultyLabels[$question->difficulty?->code ?? ''] ?? '—' }}</span></td>
                                <td><span class="badge {{ in_array($questionStatus, ['published', 'approved'], true) ? 'badge-mint' : ($questionStatus === 'review' ? 'badge-amber' : 'badge-soft') }}">{{ $statusLabels[$questionStatus] ?? 'Bản nháp' }}</span></td>
                                <td class="qb-updated">{{ ($question->draftRevision?->created_at ?? $question->publishedRevision?->created_at ?? $question->updated_at)?->locale('vi')->diffForHumans() }}</td>
                                <td><div class="qb-row-actions"><button type="button" data-qb-preview="{{ $previewKey }}" aria-controls="qb-preview" aria-label="Xem câu hỏi Q-{{ $question->id }}">@include('pages.dictionary.icon',['name'=>'eye'])</button>@include('pages.admin.assessment.question-bank.workspace.approve-question', ['questionId' => $question->id, 'reviewRevisionId' => $question->draft_revision_id, 'questionStatus' => $questionStatus, 'questionLabel' => 'Q-'.$question->id])@can('content.edit')@if($questionStatus === 'draft')<form method="post" action="{{ route('admin.questions.bulk') }}" class="question-approval-action" data-thpt-review-single data-pending-label="Đang gửi duyệt…">@csrf<input type="hidden" name="action" value="review"><input type="hidden" name="ids[]" value="{{ $question->id }}"><button class="button button-primary" type="submit" data-thpt-review-single-submit>Gửi duyệt</button></form>@endif<a href="{{ route('admin.questions.edit', ['question' => $question->id, 'from' => 'thpt']) }}" aria-label="Sửa câu hỏi Q-{{ $question->id }}">@include('pages.dictionary.icon',['name'=>'edit'])</a>@include('pages.admin.assessment.question-bank.workspace.question-delete-action', ['question'=>$question, 'compact'=>true])@endcan</div></td>
                            </tr>
                        @empty<tr><td colspan="11"><div class="qb-empty"><h3>Chưa có câu hỏi phù hợp</h3><p>Đổi bộ lọc, thêm câu hỏi hoặc import dữ liệu THPT.</p></div></td></tr>@endforelse
                        </tbody>
                    </table>
                    @else
                    <table class="qb-table">
                        <thead><tr><th class="qb-checkbox"><input type="checkbox" data-select-all aria-label="Chọn toàn bộ câu phù hợp bộ lọc"></th><th>{{ $isThptEnglish ? 'Mã nhóm / câu' : 'Mã bài / Câu' }}</th><th>Kỳ thi</th><th>{{ $isThptEnglish ? 'Họ dạng' : 'Phần / Kỹ năng' }}</th><th>{{ $isThptEnglish ? 'Nội dung' : 'Loại' }}</th><th class="qb-content-cell">{{ $isThptEnglish ? 'Văn bản / câu hỏi' : 'Tiêu đề / Nội dung' }}</th><th>{{ $isThptEnglish ? 'Câu trong nhóm' : 'Số câu' }}</th><th>Độ khó</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr></thead>
                        <tbody>
                        @forelse($entries as $entry)
                            @php
                                $entryStatus = $entry['question_status'];
                                $examNames = $entry['parts']->map(fn ($part) => $part->examType?->name)->filter()->unique();
                                $entryKinds = $isThptEnglish
                                    ? ['reading'=>'Bài đọc', 'paragraph'=>'Văn bản', 'audio'=>'Tài nguyên', 'group'=>'Nhóm câu', 'question'=>'Câu hỏi']
                                    : ['reading'=>'Passage','paragraph'=>'Paragraph','audio'=>'Audio','group'=>'Nhóm','question'=>'Câu độc lập'];
                            @endphp
                            <tr data-qb-row="{{ $entry['key'] }}" class="{{ $loop->first ? 'is-active' : '' }}">
                                <td class="qb-checkbox"><input type="checkbox" data-qb-select-entry data-question-ids="{{ json_encode($entry['matching_ids']) }}" aria-label="Chọn {{ count($entry['matching_ids']) }} câu phù hợp của {{ $entry['code'] }}"></td>
                                <td class="qb-id"><button class="reading-code" type="button" data-qb-preview="{{ $entry['key'] }}" aria-controls="qb-preview">{{ $entry['code'] }}</button></td>
                                <td>@foreach($examNames as $name)<span class="badge {{ $name === 'IELTS' ? 'badge-pink' : 'badge-blue' }}">{{ $name }}</span>@endforeach</td>
                                <td>@foreach($entry['parts'] as $part)<span class="qb-part-name">{{ $isThptEnglish ? $thptFamilyLabel($part) : $partLabel($part) }}</span>@endforeach</td>
                                <td>{{ $entryKinds[$entry['content_kind']] }}</td>
                                <td class="qb-content-cell"><button class="qb-prompt" type="button" data-qb-preview="{{ $entry['key'] }}" aria-controls="qb-preview"><span>{{ \Illuminate\Support\Str::limit($entry['title'],115) }}</span></button>@if($entry['kind'] === 'group')<small class="qb-incomplete">Chưa gắn tài nguyên</small>@endif</td>
                                <td><button class="reading-code" type="button" data-qb-preview="{{ $entry['key'] }}">{{ $entry['questions']->count() }} câu</button>@if(count($entry['matching_ids']) !== $entry['questions']->count())<small class="reading-excerpt">{{ count($entry['matching_ids']) }} phù hợp</small>@endif</td>
                                <td><span class="badge badge-soft">{{ $difficultyLabels[$entry['difficulty']] ?? ($entry['difficulty'] === 'mixed' ? 'Hỗn hợp' : '—') }}</span></td>
                                <td><span class="badge {{ in_array($entryStatus,['published','approved']) ? 'badge-mint' : 'badge-soft' }}">{{ $statusLabels[$entryStatus] ?? 'Hỗn hợp' }}</span></td>
                                <td class="qb-updated">{{ $entry['updated_at']?->locale('vi')->diffForHumans() }}</td>
                                <td><div class="qb-row-actions"><button type="button" data-qb-preview="{{ $entry['key'] }}" aria-controls="qb-preview" aria-label="Xem {{ $entry['code'] }}">@include('pages.dictionary.icon',['name'=>'eye'])</button>@if($entry['kind'] === 'question')@can('content.edit')<a href="{{ route('admin.questions.edit', $isThptEnglish ? ['question' => $entry['id'], 'from' => 'thpt'] : $entry['id']) }}" aria-label="Sửa {{ $entry['code'] }}">@include('pages.dictionary.icon',['name'=>'edit'])</a>@include('pages.admin.assessment.question-bank.workspace.question-delete-action', ['question'=>$entry['questions']->first(), 'compact'=>true])@endcan @elseif($entry['stimulus'])<a href="{{ route($entry['content_kind'] === 'audio' ? 'admin.audio.index' : 'admin.readings.index',['stimulus'=>$entry['id']]) }}" aria-label="Mở {{ $entry['code'] }}">@include('pages.dictionary.icon',['name'=>'edit'])</a>@endif</div></td>
                            </tr>
                        @empty<tr><td colspan="11"><div class="qb-empty"><h3>Chưa có bài hoặc câu hỏi phù hợp</h3><p>Thay đổi bộ lọc hoặc tạo câu hỏi mới.</p></div></td></tr>@endforelse
                        </tbody>
                    </table>
                    @endif
                </div>
                <footer class="qb-pagination">
                    <nav aria-label="Phân trang bài và câu hỏi" class="qb-page-links" data-ajax-pagination>
                        @if($listPaginator->onFirstPage())<span class="is-disabled" aria-disabled="true">‹</span>@else<a href="{{ $listPaginator->previousPageUrl() }}" aria-label="Trang trước">‹</a>@endif
                        @php
                            $lastShownPage = 0;
                        @endphp
                        @for($pageNumber = 1; $pageNumber <= $listPaginator->lastPage(); $pageNumber++)
                            @if($pageNumber === 1 || $pageNumber === $listPaginator->lastPage() || abs($pageNumber - $listPaginator->currentPage()) <= 1)
                                @if($lastShownPage && $pageNumber > $lastShownPage + 1)<span class="qb-page-gap">…</span>@endif
                                <a href="{{ $listPaginator->url($pageNumber) }}" class="{{ $pageNumber === $listPaginator->currentPage() ? 'is-active' : '' }}" @if($pageNumber === $listPaginator->currentPage())aria-current="page" @endif>{{ $pageNumber }}</a>
                                @php
                                    $lastShownPage = $pageNumber;
                                @endphp
                            @endif
                        @endfor
                        @if($listPaginator->hasMorePages())<a href="{{ $listPaginator->nextPageUrl() }}" aria-label="Trang sau">›</a>@else<span class="is-disabled" aria-disabled="true">›</span>@endif
                    </nav>
                    <label>Hiển thị <select data-qb-per-page aria-label="Số mục mỗi trang">@foreach([10, 20, 50] as $pageSize)<option value="{{ $pageSize }}" @selected($filters['perPage'] === $pageSize)>{{ $pageSize }} / trang</option>@endforeach</select></label>
                </footer>
                </div>
            </section>
        </section>
        <aside class="qb-preview" id="qb-preview" aria-labelledby="qb-preview-title" tabindex="-1" data-qb-preview-panel @if($isThptEnglish)hidden @endif>
            <header class="qb-preview-heading"><h2 id="qb-preview-title">Xem trước bài / câu hỏi</h2><button type="button" data-qb-close-preview aria-label="Đóng xem trước">@include('pages.dictionary.icon', ['name' => 'close'])</button></header>
            <div data-qb-preview-content>@if($entries->isNotEmpty())@include('pages.admin.assessment.question-bank.questions.bank-entry-preview', ['entry'=>$entries->first()])@else<div class="qb-preview-empty">Chọn mã bài hoặc câu hỏi để xem nội dung.</div>@endif</div>
        </aside>
    </div>
    @if($isThptEnglish)
        <section class="qb-thpt-workbench" aria-label="Tạo hoặc import câu hỏi">
            <section id="qb-thpt-create" class="qb-thpt-create-card" aria-labelledby="qb-thpt-create-title">
                <header class="qb-workbench-heading"><div><h2 id="qb-thpt-create-title">Thêm câu hỏi Tiếng Anh THPT Quốc gia</h2><p>Tạo nhanh câu trắc nghiệm A–D; câu mới được lưu ở trạng thái bản nháp.</p></div><a class="text-link" href="{{ route('admin.questions.create', ['exam_part_id' => $defaultThptPart?->id, 'from' => 'thpt']) }}">Mở trình soạn đầy đủ</a></header>
                @can('content.edit')
                <form class="qb-quick-form" method="post" action="{{ route('admin.questions.store') }}">
                    @csrf
                    <input type="hidden" name="return_to" value="thpt">
                    <input type="hidden" name="return_to_thpt_bank" value="1">
                    <input type="hidden" name="question_type_id" value="{{ $defaultQuestionTypeId }}">
                    <input type="hidden" name="exam_part_id" value="{{ $thptFamilyDefaults[old('thpt_family', 'short_cloze')]?->id ?? $defaultThptPart?->id }}" data-thpt-create-part>
                    <div class="qb-quick-fields">
                        <label>Dạng bài <select name="thpt_family" data-thpt-create-family required>@foreach($thptFamilies as $familyCode => $familyName)<option value="{{ $familyCode }}" data-part="{{ $thptFamilyDefaults[$familyCode]?->id ?? $defaultThptPart?->id }}" @selected(old('thpt_family', 'short_cloze') === $familyCode)>{{ $familyName }}</option>@endforeach</select></label>
                        <label>Nhóm văn bản <select name="question_group_id" data-thpt-create-group><option value="">Câu độc lập</option>@foreach($thptGroups as $group)<option value="{{ $group->id }}" data-part="{{ $group->exam_part_id }}" data-family="{{ $thptPartFamily($group->part_code) }}" @selected((string)old('question_group_id') === (string)$group->id)>{{ $group->title }} · {{ $thptFamilyLabel($parts->firstWhere('id', $group->exam_part_id)) }}</option>@endforeach</select></label>
                        <label>Mức độ <select name="question_difficulty_id" required>@foreach($difficulties as $difficulty)<option value="{{ $difficulty->id }}" @selected((string)old('question_difficulty_id', $defaultDifficultyId) === (string)$difficulty->id)>{{ $difficultyLabels[$difficulty->code] ?? $difficulty->name }}</option>@endforeach</select></label>
                    </div>
                    <label class="qb-quick-prompt">Nội dung câu hỏi <textarea name="prompt" rows="3" maxlength="10000" placeholder="Nhập nội dung câu hỏi bằng tiếng Anh…" required>{{ old('prompt') }}</textarea></label>
                    <fieldset class="qb-quick-options"><legend>Đáp án lựa chọn <span>Chọn một đáp án đúng</span></legend>
                        @foreach(['A', 'B', 'C', 'D'] as $key)
                            <label class="qb-quick-option"><input type="radio" name="correct_option" value="{{ $key }}" @checked(old('correct_option', 'A') === $key) required><span class="qb-option-key">{{ $key }}</span><input type="text" name="options_values[{{ $key }}]" value="{{ old('options_values.'.$key) }}" maxlength="2000" placeholder="Phương án {{ $key }}" aria-label="Phương án {{ $key }}" required></label>
                        @endforeach
                    </fieldset>
                    <label class="qb-quick-explanation">Giải thích đáp án <textarea name="explanation" rows="2" maxlength="10000" placeholder="Có thể bổ sung sau trong trình soạn đầy đủ">{{ old('explanation') }}</textarea></label>
                    <footer class="qb-quick-actions"><span class="form-help">Đề chính thức dùng 4 phương án và 1 đáp án đúng.</span><button class="button button-primary" type="submit">Lưu câu hỏi</button></footer>
                </form>
                @else
                    <p class="qb-quick-readonly">Bạn chưa có quyền tạo câu hỏi. <a href="{{ route('admin.questions.create', ['exam_part_id' => $defaultThptPart?->id, 'from' => 'thpt']) }}">Mở ngân hàng câu hỏi</a></p>
                @endcan
            </section>
            <section class="qb-thpt-import-card" aria-label="Import câu hỏi THPT">
                <header class="qb-workbench-heading"><div><h2>Import đề thi 40 câu</h2><p>Một file Excel cho đủ 6 phần; đính kèm ảnh văn bản ở Phần 1–2 sau khi xem trước.</p></div><span class="qb-import-icon">@include('pages.dictionary.icon', ['name' => 'collection'])</span></header>
                @include('pages.admin.assessment.question-bank.questions.thpt-workbook-import')
            </section>
        </section>
    @endif
    @if($isThptEnglish)
        @foreach($questions as $question)
            @php
                $questionRecord = $bankData['records'][$question->id] ?? [];
                $questionPreviewKey = $questionRecord['keys'][0] ?? 'question:'.$question->id;
                [$questionPreviewKind, $questionPreviewId] = array_pad(explode(':', $questionPreviewKey, 2), 2, $question->id);
            @endphp
            <span hidden data-qb-preview-url="{{ $questionPreviewKey }}" data-url="{{ route('admin.questions.bank-preview', array_merge(['kind' => $questionPreviewKind, 'id' => (int)$questionPreviewId], ['thpt_english' => 1])) }}"></span>
        @endforeach
    @endif
    @foreach($entries as $entry)<span hidden data-qb-preview-url="{{ $entry['key'] }}" data-url="{{ route('admin.questions.bank-preview', array_merge(['kind'=>$entry['kind'],'id'=>$entry['id']], $isThptEnglish ? ['thpt_english' => 1] : [])) }}"></span>@endforeach
    @if($entries->isNotEmpty())<template data-qb-preview-template="{{ $entries->first()['key'] }}">@include('pages.admin.assessment.question-bank.questions.bank-entry-preview',['entry'=>$entries->first()])</template>@endif
</div>
