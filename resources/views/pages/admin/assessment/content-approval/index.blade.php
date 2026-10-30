<h1>Duyệt nội dung</h1>
@php($kindLabels=['question'=>'Câu hỏi','stimulus'=>'Bài Reading','exam'=>'Đề thi'])
@if($draftCount)<div class="review-draft-notice" role="status"><p>{{ number_format($draftCount) }} câu hỏi đang ở bản nháp, chưa gửi duyệt. Mở đúng ngân hàng để gửi duyệt:</p><nav class="assessment-actions" aria-label="Ngân hàng câu hỏi nháp"><a class="text-link" href="{{ route('admin.questions.listening', ['status'=>'draft']) }}">Listening →</a><a class="text-link" href="{{ route('admin.readings.index', ['status'=>'draft']) }}">Reading →</a><a class="text-link" href="{{ route('admin.questions.thpt', ['status'=>'draft']) }}">Tiếng Anh THPT →</a></nav></div>@endif
@if(session('review_bulk_skipped'))
    <section class="admin-alert admin-alert-error review-bulk-skipped" role="alert">
        <strong>{{ count(session('review_bulk_skipped')) }} revision chưa được duyệt</strong>
        <details><summary>Xem từng mục và lý do</summary><ul>@foreach(session('review_bulk_skipped') as $skipped)<li><strong>{{ $skipped['label'] }}:</strong> {{ $skipped['reason'] }}</li>@endforeach</ul></details>
    </section>
@endif
<section class="panel">
    <nav id="content-approval-kinds" class="assessment-actions" aria-label="Loại nội dung">
        @foreach([''=>'Tất cả','question'=>'Câu hỏi','stimulus'=>'Bài Reading','exam'=>'Đề thi'] as $kind=>$label)
            <a class="button button-quiet" data-ajax-filter-reset="#content-approval-kinds, #content-approval-results" href="{{ route('admin.content-approval.index', array_merge(request()->except('page'), ['kind'=>$kind])) }}" @if(($filters['kind']??'')===$kind) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form method="get" class="admin-form-grid review-queue-filters" data-ajax-filter-form data-ajax-target="#content-approval-kinds, #content-approval-results" data-ajax-on-change data-ajax-debounce="350">
        <input type="hidden" name="kind" value="{{ $filters['kind']??'' }}">
        <label>Kỳ thi<select name="exam_type"><option value="">Tất cả</option>@foreach($examTypes as $type)<option value="{{ $type->id }}" @selected((string)($filters['exam_type']??'')===(string)$type->id)>{{ $type->name }}</option>@endforeach</select></label>
        <label>Part<select name="part"><option value="">Tất cả</option>@foreach($parts as $part)<option value="{{ $part->id }}" @selected((string)($filters['part']??'')===(string)$part->id)>{{ $part->exam_type_name }} · {{ $part->name }}</option>@endforeach</select></label>
        <label>Từ ngày<input name="from" type="date" value="{{ $filters['from']??'' }}"></label>
        <label>Đến ngày<input name="to" type="date" value="{{ $filters['to']??'' }}"></label>
    </form>
    <div id="content-approval-results" data-review-queue-results data-ajax-results>
        @php($initialProgress = $bulkBatch ?? session('review_bulk_progress'))
        <form method="post" action="{{ route('admin.content-approval.approve-all') }}" data-review-bulk-approve data-review-total="{{ $entries->total() }}" @if($bulkBatch) data-review-resume-url="{{ $bulkBatch['next_url'] }}" @endif @if($initialProgress) data-review-progress-state="{{ json_encode($initialProgress) }}" @endif>
            @csrf
            <input type="hidden" name="selection_mode" value="selected" data-review-selection-mode>
            <input type="hidden" name="expected_count" value="{{ $entries->total() }}">
            <input type="hidden" name="selection_snapshot" value="{{ $selectionSnapshot }}">
            @foreach(['kind', 'q', 'author', 'from', 'to', 'revision', 'exam_type', 'part'] as $filterName)
                @if(!empty($filters[$filterName]))<input type="hidden" name="{{ $filterName }}" value="{{ $filters[$filterName] }}">@endif
            @endforeach
            <div class="review-bulk-toolbar" data-review-bulk-toolbar hidden>
                <span data-review-selection-count role="status" aria-live="polite"></span>
                <button type="submit" class="button button-primary" data-review-bulk-button data-pending-label="Đang duyệt…">Duyệt mục đã chọn</button>
            </div>
            <div class="table-responsive">
                <table class="admin-table" aria-label="Nội dung chờ duyệt">
                    <thead><tr>
                        <th><label class="review-select-all"><input type="checkbox" data-review-select-all aria-label="Chọn tất cả {{ $entries->total() }} kết quả phù hợp qua mọi trang" @disabled($entries->total() === 0)><span>Loại</span></label></th>
                        <th>Nội dung</th><th>Người gửi</th><th>Revision</th><th>Thời điểm cập nhật</th><th>Kiểm tra nội dung</th><th>Thao tác</th>
                    </tr></thead>
                    <tbody>@forelse($entries as $entry)<tr data-review-entry="{{ $entry->kind }}-{{ $entry->id }}">
                        <td><label class="review-row-select"><input type="checkbox" data-review-select-row value="{{ $entry->kind }}:{{ $entry->id }}" aria-label="Chọn {{ $kindLabels[$entry->kind]??'nội dung' }} #{{ $entry->id }}"><span>{{ $kindLabels[$entry->kind]??'Nội dung' }}</span></label></td>
                        <td>#{{ $entry->id }} · {{ \Illuminate\Support\Str::limit(strip_tags($entry->title), 120) }}</td>
                        <td>{{ $entry->author_name??'Không rõ' }}</td><td>{{ $entry->revision }}</td><td>{{ $entry->submitted_at }}</td>
                        <td>@if($entry->blockers)<details><summary>{{ count($entry->blockers) }} lỗi cần sửa</summary><ul>@foreach($entry->blockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul></details>@else Sẵn sàng duyệt @endif</td>
                        <td><button type="button" class="button button-primary review-open-button" data-review-open data-review-url="{{ route('admin.content-approval.show',['kind'=>$entry->kind,'id'=>$entry->id,'modal'=>1]) }}" aria-haspopup="dialog">Duyệt</button></td>
                    </tr>@empty<tr><td colspan="7">Không có revision chờ duyệt phù hợp. Biên tập viên gửi duyệt từ ngân hàng câu hỏi, bài Reading hoặc đề thi.</td></tr>@endforelse</tbody>
                </table>
            </div>
            <div class="review-bulk-progress" data-review-progress>
                <div class="review-bulk-progress-heading"><span id="review-progress-label">Tiến độ duyệt</span><output data-review-progress-count>{{ $initialProgress['processed'] ?? 0 }}/{{ $initialProgress['total'] ?? $entries->total() }}</output></div>
                <progress data-review-progress-bar aria-labelledby="review-progress-label" aria-describedby="review-progress-status" max="{{ max(1, $initialProgress['total'] ?? $entries->total()) }}" value="{{ $initialProgress['processed'] ?? 0 }}"></progress>
                <p id="review-progress-status" data-review-progress-status role="status" aria-live="polite">{{ $entries->total() ? 'Chưa bắt đầu duyệt.' : 'Không có nội dung chờ duyệt.' }}</p>
            </div>
            <div data-ajax-pagination>{{ $entries->links() }}</div>
        </form>
    </div>
</section>

<dialog class="content-review-dialog" data-content-review-dialog aria-labelledby="content-review-dialog-title">
    <header class="content-review-dialog-header">
        <h2 id="content-review-dialog-title">Duyệt nội dung</h2>
        <button type="button" class="icon-button" data-review-close aria-label="Đóng cửa sổ duyệt" title="Đóng">×</button>
    </header>
    <div class="content-review-dialog-body" data-review-dialog-content aria-live="polite">
        <p class="content-review-loading" role="status">Chọn một revision để xem nội dung.</p>
    </div>
</dialog>
