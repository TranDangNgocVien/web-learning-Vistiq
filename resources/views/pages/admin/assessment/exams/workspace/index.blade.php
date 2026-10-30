<div class="ex-workspace ex-list-layout" data-exam-list>
    <section class="ex-panel ex-list-main">
        <header class="ex-panel-heading"><h1>Danh sách đề thi</h1>@can('content.edit')<div class="ex-heading-actions"><a class="ex-button" href="{{ route('admin.exams.deleted') }}">Đề đã xóa</a><a class="ex-button" href="{{ route('admin.exams.create', ['template' => 'thpt-english-2025']) }}"><i data-ex-icon="file-text"></i>Đề Tiếng Anh THPT</a><a class="ex-button is-primary" href="{{ route('admin.exams.create') }}"><i data-ex-icon="plus"></i>Tạo đề thi</a></div>@endcan</header>
        <form method="get" class="ex-filters" data-exam-filters data-ajax-filter-form data-ajax-target="#admin-exam-results, #admin-exam-meta, #admin-exam-detail-results" data-ajax-debounce="350">
            <div class="ex-filter-top"><nav class="ex-tabs" aria-label="Loại đề thi">
                <button type="button" data-exam-filter="type" data-filter-value="" class="{{ !request('type') ? 'is-active' : '' }}">Tất cả ({{ number_format($typeCounts->sum()) }})</button>
                @foreach($types as $type)<button type="button" data-exam-filter="type" data-filter-value="{{ $type->id }}" class="{{ (int) request('type') === $type->id ? 'is-active' : '' }}">{{ $type->name }} ({{ number_format($typeCounts->get($type->id, 0)) }})</button>@endforeach
            </nav><label class="ex-search"><i data-ex-icon="search"></i><input name="q" value="{{ request('q') }}" placeholder="Tìm tên đề thi" aria-label="Tìm tên đề thi"></label></div>
            <div class="ex-filter-chips">
                @foreach(['approved' => 'Đã duyệt', 'draft' => 'Bản nháp', 'review' => 'Chờ duyệt'] as $key => $label)<button type="button" class="ex-chip is-{{ $key }}" data-exam-filter="status" data-filter-value="{{ $key }}" aria-pressed="{{ request('status') === $key ? 'true' : 'false' }}"><span class="ex-dot"></span>{{ $label }}</button>@endforeach
                @foreach(['reading'=>['file-text','Có passage'], 'audio'=>['volume-2','Có audio']] as $key=>$item)<button type="button" class="ex-chip" data-exam-filter="resource" data-filter-value="{{ $key }}" aria-pressed="{{ request('resource') === $key ? 'true' : 'false' }}"><i data-ex-icon="{{ $item[0] }}"></i>{{ $item[1] }}</button>@endforeach
                <button type="button" class="ex-chip" data-exam-filter="mode" data-filter-value="full" aria-pressed="{{ request('mode') === 'full' ? 'true' : 'false' }}"><i data-ex-icon="files"></i>Mock Test</button>
            </div>
            <div class="ex-filter-grid">
                <label>Trạng thái<select name="status" data-filter-auto><option value="">Tất cả</option>@foreach(['draft'=>'Bản nháp','review'=>'Chờ duyệt','approved'=>'Đã duyệt'] as $key=>$label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label>Loại đề<select name="type" data-filter-auto><option value="">Tất cả</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected((int) request('type') === $type->id)>{{ $type->name }}</option>@endforeach</select></label>
                <label>Thời gian<select name="days" data-filter-auto><option value="">Tất cả</option>@foreach([7,30,90] as $days)<option value="{{ $days }}" @selected((int) request('days') === $days)>{{ $days }} ngày gần đây</option>@endforeach</select></label>
                <label>Người tạo<select name="creator" data-filter-auto><option value="">Tất cả</option>@foreach($creators as $id=>$name)<option value="{{ $id }}" @selected((int) request('creator') === $id)>{{ $name }}</option>@endforeach</select></label>
                <a class="ex-button" data-ajax-filter-reset="#admin-exam-results, #admin-exam-meta, #admin-exam-detail-results" href="{{ route('admin.exams.index') }}"><i data-ex-icon="rotate-ccw"></i>Xóa bộ lọc</a>
            </div>
            <input type="hidden" name="resource" value="{{ request('resource') }}"><input type="hidden" name="mode" value="{{ request('mode') }}">
            <div class="ex-list-meta" id="admin-exam-meta"><span>Hiển thị <b>{{ $exams->firstItem() ?? 0 }} - {{ $exams->lastItem() ?? 0 }}</b> trong <b>{{ number_format($exams->total()) }}</b> đề thi <span data-exam-selection-count></span></span><label>Sắp xếp theo<select name="sort" data-filter-auto>@foreach(['updated'=>'Cập nhật mới nhất','created'=>'Tạo mới nhất','title'=>'Tên đề A–Z'] as $key=>$label)<option value="{{ $key }}" @selected(request('sort', 'updated') === $key)>{{ $label }}</option>@endforeach</select></label></div>
            <input type="hidden" name="per_page" value="{{ $exams->perPage() }}">
        </form>
        <div id="admin-exam-results" data-ajax-results>
        <div class="ex-table-scroll"><table class="ex-table ex-exam-table"><thead><tr><th><input type="checkbox" data-exam-select-all aria-label="Chọn tất cả đề trên trang"></th><th>Mã đề</th><th>Tên đề thi</th><th>Loại</th><th>Cấu trúc</th><th>Số câu</th><th>Thời gian</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr></thead><tbody>
            @forelse($exams as $exam)
                @php($summary = $summaries[$exam->id])
                <tr data-exam-row="{{ $exam->id }}" class="{{ $loop->first ? 'is-active' : '' }}">
                    <td><input type="checkbox" data-exam-select aria-label="Chọn {{ $exam->title }}"></td>
                    <td><button type="button" class="ex-text-button" data-exam-detail="{{ $exam->id }}">{{ $summary['code'] }}</button></td>
                    <td class="ex-title-cell"><button type="button" class="ex-text-button" data-exam-detail="{{ $exam->id }}">{{ $exam->title }}</button></td>
                    <td><span class="ex-tag is-{{ $exam->examType?->code }}">{{ $exam->examType?->name }}</span></td>
                    <td>{{ count($summary['sections']) }} phần</td><td>{{ $summary['count'] }} câu</td><td>{{ $summary['duration'] }} phút</td>
                    <td><span class="ex-status is-{{ $summary['status'] }}"><span class="ex-dot"></span>{{ $summary['status_label'] }}</span></td>
                    <td>{{ $exam->updated_at?->locale('vi')->diffForHumans() }}</td>
                    <td><div class="ex-row-actions"><button type="button" class="ex-icon-button" data-exam-detail="{{ $exam->id }}" aria-label="Chi tiết {{ $exam->title }}"><i data-ex-icon="eye"></i></button>@can('content.edit')<a class="ex-icon-button" href="{{ route('admin.exams.edit', $exam) }}" aria-label="Chỉnh sửa {{ $exam->title }}"><i data-ex-icon="square-pen"></i></a>@endcan<a class="ex-icon-button" href="{{ route('admin.exams.preview', $exam) }}" target="_blank" rel="noopener" aria-label="Xem trước {{ $exam->title }}"><i data-ex-icon="external-link"></i></a></div></td>
                </tr>
            @empty<tr><td colspan="10"><div class="ex-empty"><i data-ex-icon="files"></i><strong>Chưa có đề thi phù hợp</strong><span>Thử đổi bộ lọc hoặc tạo đề thi mới.</span></div></td></tr>@endforelse
        </tbody></table></div>
        <footer class="ex-pagination"><label>Hiển thị<select data-exam-page-size aria-label="Số đề trên trang">@foreach([8,15,30] as $size)<option @selected($exams->perPage() === $size)>{{ $size }}</option>@endforeach</select>trên mỗi trang</label><div data-ajax-pagination>{{ $exams->onEachSide(1)->links('pages.admin.assessment.exams.workspace.pagination') }}</div></footer>
        </div>
    </section>
    <aside class="ex-panel ex-detail-panel" id="admin-exam-detail-results" data-ajax-results data-exam-detail-panel>
        <header class="ex-panel-heading"><h2>Chi tiết đề thi</h2><button type="button" class="ex-icon-button" data-exam-close-detail aria-label="Đóng chi tiết đề thi"><i data-ex-icon="x"></i></button></header>
        @foreach($exams as $exam)
            @php($summary = $summaries[$exam->id])
            <div class="ex-detail-body" data-exam-detail-body="{{ $exam->id }}" @if(!$loop->first) hidden @endif>
                <div class="ex-detail-tags"><span class="ex-tag is-primary">{{ $exam->examType?->name }}</span><span class="ex-tag">{{ ['full'=>'Mock Test','mini'=>'Đề ngắn','skill'=>'Luyện kỹ năng'][$exam->mode] ?? $exam->mode }}</span><span class="ex-status is-{{ $summary['status'] }}">{{ $summary['status_label'] }}</span><small>Mã đề: {{ $summary['code'] }}</small></div>
                <h3 class="ex-detail-title">{{ $exam->title }}</h3>
                <div class="ex-metrics"><div><i data-ex-icon="file-text"></i><b>{{ $summary['count'] }}</b><span>câu hỏi</span></div><div><i data-ex-icon="clock-3"></i><b>{{ $summary['duration'] }}</b><span>phút</span></div><div><i data-ex-icon="list"></i><b>{{ count($summary['sections']) }}</b><span>phần</span></div><div><i data-ex-icon="star"></i><b>{{ $exam->score_scale_id ? (strtoupper($exam->examType?->code ?? '') === 'TOEIC' ? '990' : (strtoupper($exam->examType?->code ?? '') === 'IELTS' ? '9.0' : 'Theo bảng')) : '%' }}</b><span>thang điểm</span></div></div>
                <h4>Thông tin</h4><dl class="ex-facts"><dt><i data-ex-icon="user-round"></i>Người tạo</dt><dd>{{ $summary['creator'] }}</dd><dt><i data-ex-icon="calendar-days"></i>Tạo lúc</dt><dd>{{ $exam->created_at?->format('d/m/Y H:i') }}</dd><dt><i data-ex-icon="refresh-cw"></i>Cập nhật</dt><dd>{{ $exam->updated_at?->locale('vi')->diffForHumans() }}</dd><dt><i data-ex-icon="settings-2"></i>Chế độ tạo</dt><dd>{{ $summary['generation'] ? 'Tự động chọn câu hỏi' : 'Chọn thủ công' }}</dd><dt><i data-ex-icon="database"></i>Nguồn</dt><dd>Ngân hàng câu hỏi{{ $summary['passages'] ? ' + Passage' : '' }}{{ $summary['audio'] ? ' + Audio' : '' }}</dd></dl>
                <h4>Cấu trúc đề</h4><div class="ex-part-bars">@foreach($summary['sections'] as $part)<div class="ex-part-bar" style="--part-color: {{ ['#3980ff','#5c8bf5','#2bb0b4','#5885fb','#a475f7','#38a6c2','#ffbf43'][$loop->index % 7] }}"><span class="ex-part-number">{{ $loop->iteration }}</span><b title="{{ $part['name'] }}">{{ str_starts_with($part['code'], 'part-') ? ucfirst(str_replace('-', ' ', $part['code'])) : $part['name'] }}</b><span class="ex-bar-track"><i style="width:{{ $summary['count'] ? round($part['count'] / $summary['count'] * 100) : 0 }}%"></i></span><span>{{ $part['count'] }} câu</span><small>{{ $summary['count'] ? round($part['count'] / $summary['count'] * 100) : 0 }}%</small></div>@endforeach</div>
                @if($exam->description)<h4>Mô tả</h4><p class="ex-description">{{ $exam->description }}</p>@endif
                <h4>Tài nguyên liên kết</h4><div class="ex-detail-tags"><span class="ex-tag"><i data-ex-icon="file-text"></i>{{ $summary['passages'] }} passage</span><span class="ex-tag"><i data-ex-icon="volume-2"></i>{{ $summary['audio'] }} câu có audio</span></div>
                <div class="ex-note"><i data-ex-icon="info"></i><div><b>Trạng thái đề thi</b><span>{{ $summary['status'] === 'approved' ? 'Đề đã duyệt và xuất hiện trong kho đề học viên. Chỉnh sửa sẽ tạo bản nháp mới và cần duyệt lại.' : 'Lưu bản nháp, gửi duyệt và đề sẽ xuất hiện trong kho học viên ngay khi được duyệt.' }}</span></div></div>
                <div class="ex-detail-actions"><a class="ex-button" href="{{ route('admin.exams.preview', $exam) }}" target="_blank" rel="noopener"><i data-ex-icon="eye"></i>Xem đề thi</a>@can('content.edit')<a class="ex-button" href="{{ route('admin.exams.edit', $exam) }}"><i data-ex-icon="square-pen"></i>Chỉnh sửa</a>@endcan</div>
                @can('content.edit')@if($exam->status !== 'archived' && $exam->draft_status === 'draft')<form method="post" action="{{ route('admin.exams.transition', [$exam, 'review']) }}">@csrf<button class="ex-button is-primary ex-full-width"><i data-ex-icon="send"></i>Gửi duyệt</button></form>@endif @endcan
                @if($summary['status'] === 'approved')@can('content.publish')<form method="post" action="{{ route('admin.exams.transition', [$exam, 'archive']) }}" data-confirm="Lưu trữ đề thi này? Các câu hỏi và tài nguyên liên kết được giữ lại.">@csrf<button class="ex-button is-danger ex-full-width"><i data-ex-icon="archive"></i>Lưu trữ đề thi</button></form>@endcan @endif
                @can('content.edit')<form method="post" action="{{ route('admin.exams.destroy', $exam) }}" data-confirm="Xóa mềm đề ‘{{ $exam->title }}’? Đề sẽ ẩn khỏi kho học viên; các lượt làm và kết quả đã có vẫn được giữ để xem lại." data-confirm-title="Xóa đề thi" data-confirm-label="Xóa đề thi" data-confirm-tone="danger" data-pending-label="Đang xóa…">@csrf @method('DELETE')<button class="ex-button is-danger ex-full-width" type="submit"><i data-ex-icon="trash-2"></i>Xóa đề thi</button></form>@endcan
            </div>
        @endforeach
        @if($exams->isEmpty())<div class="ex-empty"><span>Chọn một đề thi để xem chi tiết.</span></div>@endif
    </aside>
</div>
