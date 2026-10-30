@php
    $statusLabels = ['draft'=>'Bản nháp', 'review'=>'Chờ duyệt', 'approved'=>'Đã duyệt', 'published'=>'Đã xuất bản', 'archived'=>'Đã lưu trữ'];
    $difficultyLabels = ['easy'=>'Dễ', 'medium'=>'Trung bình', 'hard'=>'Khó'];
    $canEdit = auth()->user()->hasPermission('content.edit');
@endphp
<div class="question-bank listening-library" data-subjective-bank>
    <header class="qb-heading listening-heading">
        <h1>Câu hỏi {{ $skillLabel }}</h1>
        <div class="qb-heading-actions">
            @can('content.edit')
                <a class="button button-quiet" href="{{ route('admin.questions.deleted') }}">Câu hỏi đã xóa</a>
                <a class="button button-quiet" href="{{ route('admin.questions.import-template', ['template'=>$skill.'-xlsx']) }}">@include('pages.dictionary.icon', ['name'=>'download']) Tải mẫu .xlsx</a>
                @include('pages.admin.assessment.question-bank.workspace.question-workbook-import', ['workbookKind'=>$skill])
                @if($draftIds->isNotEmpty())
                    <form method="post" action="{{ route($bankRoute.'.review') }}" data-confirm="Gửi duyệt toàn bộ {{ $draftIds->count() }} câu hỏi {{ $skillLabel }} nháp? Cả lượt chỉ hoàn tất khi mọi câu hợp lệ." data-confirm-title="Gửi duyệt câu hỏi {{ $skillLabel }}" data-confirm-label="Gửi duyệt {{ $draftIds->count() }} câu" data-pending-label="Đang gửi duyệt…">
                        @csrf
                        <input type="hidden" name="all_drafts" value="1">
                        <input type="hidden" name="draft_signature" value="{{ $draftSignature }}">
                        <button class="button button-quiet" type="submit">Gửi duyệt tất cả ({{ number_format($draftIds->count()) }})</button>
                    </form>
                @endif
                <a class="button button-primary" href="{{ route('admin.questions.create', ['skill'=>$skill]) }}">@include('pages.dictionary.icon', ['name'=>'plus']) Tạo câu hỏi {{ $skillLabel }}</a>
            @endcan
        </div>
    </header>
    <div class="listening-workspace {{ $selected ? 'has-detail' : '' }}">
        <section class="qb-directory" aria-label="Danh sách câu hỏi {{ $skillLabel }}">
            <form id="subjective-filters" method="get" action="{{ route($bankRoute) }}" class="listening-filters" data-ajax-filter-form data-ajax-target="#subjective-list-results" data-ajax-on-change data-ajax-debounce="350">
                @if($selected)<input type="hidden" name="question" value="{{ $selected['question']->id }}">@endif
                <label class="listening-search">Tìm câu hỏi<input type="search" name="q" value="{{ $filters['search'] }}" placeholder="Mã câu hoặc nội dung" autocomplete="off"></label>
                <label>Trạng thái<select name="status"><option value="">Tất cả</option>@foreach($statusLabels as $code=>$label)<option value="{{ $code }}" @selected($filters['status'] === $code)>{{ $label }}</option>@endforeach</select></label>
                <details class="subjective-filter-options" open data-subjective-filters>
                    <summary>Bộ lọc khác</summary>
                    <div>
                        <label>Kỳ thi<select name="exam"><option value="">Tất cả</option>@foreach($parts->pluck('examType')->filter()->unique('id') as $examType)<option value="{{ $examType->code }}" @selected($filters['exam'] === $examType->code)>{{ $examType->name }}</option>@endforeach</select></label>
                        <label>{{ $skill === 'writing' ? 'Task' : 'Part' }}<select name="part"><option value="">Tất cả</option>@foreach($parts as $part)<option value="{{ $part->code }}" @selected($filters['part'] === $part->code)>{{ $part->name }}</option>@endforeach</select></label>
                        <label>Mức độ<select name="difficulty"><option value="">Tất cả</option>@foreach($difficultyLabels as $code=>$label)<option value="{{ $code }}" @selected($filters['difficulty'] === $code)>{{ $label }}</option>@endforeach</select></label>
                        <label>Sắp xếp<select name="sort">@foreach(['newest'=>'Mới cập nhật', 'oldest'=>'Cũ nhất', 'id'=>'Mã câu tăng dần'] as $code=>$label)<option value="{{ $code }}" @selected($sort === $code)>{{ $label }}</option>@endforeach</select></label>
                    </div>
                </details>
                <a class="text-link" data-ajax-filter-reset="#subjective-list-results" href="{{ route($bankRoute, $selected ? ['question'=>$selected['question']->id] : []) }}">Xóa bộ lọc</a>
            </form>
            <div id="subjective-list-results" data-ajax-results>
                <div class="qb-list listening-list">
                    <div class="qb-list-heading"><span>{{ $questions->firstItem() ?? 0 }}–{{ $questions->lastItem() ?? 0 }} / {{ number_format($questions->total()) }} câu hỏi {{ $skillLabel }}</span></div>
                    @can('content.edit')
                        <form id="subjective-review-form" method="post" action="{{ route($bankRoute.'.review') }}" class="assessment-actions" data-pending-label="Đang gửi duyệt…">
                            @csrf
                            <label class="subjective-select-all"><input type="checkbox" data-subjective-select-all> Chọn tất cả trên trang</label>
                            <span data-subjective-selection role="status">Chọn câu hỏi nháp để gửi duyệt.</span>
                            <button class="button button-quiet" type="submit" data-subjective-review-submit disabled>Gửi duyệt mục đã chọn</button>
                        </form>
                    @endcan
                    <div class="qb-table-scroll"><table class="qb-table subjective-table" role="table">
                        <thead><tr><th class="qb-checkbox"><span class="sr-only">Chọn câu hỏi</span></th><th>Mã câu</th><th>Nội dung</th><th>Kỳ thi</th><th>{{ $skill === 'writing' ? 'Task' : 'Part' }}</th><th>Mức độ</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr></thead>
                        <tbody>
                            @forelse($questions as $question)
                                @php
                                    $record = $records[$question->id];
                                    $code = 'Q-'.str_pad((string)$question->id, 5, '0', STR_PAD_LEFT);
                                    $detailUrl = route($bankRoute, array_merge(request()->except('question'), ['question'=>$question->id]));
                                @endphp
                                <tr class="{{ $selected && $selected['question']->id === $question->id ? 'is-active' : '' }}">
                                    <td class="qb-checkbox">@if($canEdit && $record['status'] === 'draft')<input type="checkbox" name="ids[]" value="{{ $question->id }}" form="subjective-review-form" data-subjective-select aria-label="Chọn {{ $code }}"><input type="hidden" name="revision_counters[{{ $question->id }}]" value="{{ $question->revision_counter }}" form="subjective-review-form">@endif</td>
                                    <td data-label="Mã câu"><a class="reading-code" href="{{ $detailUrl }}">{{ $code }}</a></td>
                                    <td class="qb-content-cell" data-label="Nội dung"><a href="{{ $detailUrl }}"><b>{{ \Illuminate\Support\Str::limit(strip_tags($record['payload']['prompt'] ?? ''), 125) }}</b></a>@if($skill === 'writing' && !empty($record['payload']['metadata']['min_words']))<div class="qe-help">Tối thiểu {{ $record['payload']['metadata']['min_words'] }} từ</div>@endif</td>
                                    <td data-label="Kỳ thi"><span class="listening-exam-tag is-ielts">{{ strtoupper($question->examPart?->examType?->code ?? '') }}</span></td>
                                    <td data-label="{{ $skill === 'writing' ? 'Task' : 'Part' }}">{{ $question->examPart?->name }}</td>
                                    <td data-label="Mức độ">{{ $difficultyLabels[$question->difficulty?->code] ?? '—' }}</td>
                                    <td data-label="Trạng thái"><span class="badge {{ in_array($record['status'], ['approved','published'], true) ? 'badge-mint' : ($record['status'] === 'review' ? 'badge-amber' : 'badge-soft') }}">{{ $statusLabels[$record['status']] ?? $record['status'] }}</span></td>
                                    <td class="qb-updated" data-label="Cập nhật">{{ ($question->draftRevision?->created_at ?? $question->updated_at)?->locale('vi')->diffForHumans() }}</td>
                                    <td data-label="Thao tác"><div class="qb-row-actions">
                                        <a href="{{ $detailUrl }}" title="Xem {{ $code }}" aria-label="Xem {{ $code }}">@include('pages.dictionary.icon', ['name'=>'eye'])</a>
                                        @can('content.edit')
                                            <a href="{{ route('admin.questions.edit', $question) }}" title="Sửa {{ $code }}" aria-label="Sửa {{ $code }}">@include('pages.dictionary.icon', ['name'=>'edit'])</a>
                                            @if($record['status'] === 'draft')
                                                <form class="question-approval-action" method="post" action="{{ route($bankRoute.'.review') }}" data-pending-label="Đang gửi…">@csrf<input type="hidden" name="ids[]" value="{{ $question->id }}"><input type="hidden" name="revision_counters[{{ $question->id }}]" value="{{ $question->revision_counter }}"><button class="button button-quiet" type="submit">Gửi duyệt</button></form>
                                            @endif
                                            <form class="question-delete-action" method="post" action="{{ route('admin.questions.destroy', $question) }}" data-confirm="Xóa câu hỏi {{ $code }}? Câu hỏi đã dùng trong đề được giữ trong lịch sử và ngừng sử dụng." data-confirm-label="Xóa {{ $code }}" data-pending-label="Đang xóa…">@csrf @method('DELETE')<button type="submit" class="text-link" title="Xóa {{ $code }}" aria-label="Xóa {{ $code }}">@include('pages.dictionary.icon', ['name'=>'close'])</button></form>
                                        @endcan
                                    </div></td>
                                </tr>
                            @empty
                                <tr><td colspan="9"><div class="qb-empty"><h3>Chưa có câu hỏi {{ $skillLabel }} phù hợp</h3><p>Thử đổi bộ lọc, tạo câu hỏi hoặc import từ file mẫu .xlsx.</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table></div>
                    @include('pages.admin.assessment.question-bank.workspace.pagination', ['paginator'=>$questions, 'formId'=>'subjective-filters', 'label'=>'Phân trang câu hỏi '.$skillLabel])
                </div>
            </div>
        </section>
        @if($selected)
            <aside class="listening-detail" data-subjective-detail tabindex="-1" aria-label="Chi tiết câu hỏi {{ $skillLabel }}">
                <header class="listening-detail-heading"><h2>Q-{{ str_pad((string)$selected['question']->id, 5, '0', STR_PAD_LEFT) }}</h2><a class="listening-icon-button" href="{{ route($bankRoute, request()->except('question')) }}" aria-label="Đóng chi tiết">@include('pages.dictionary.icon', ['name'=>'close'])</a></header>
                <div class="listening-detail-body">
                    @include('pages.admin.assessment.question-bank.questions.editor-preview', ['previewPayload'=>$selected['payload'], 'previewPart'=>$selected['question']->examPart, 'previewDifficulty'=>$selected['question']->difficulty, 'previewImage'=>\VistiqCore\Models\MediaAsset::find($selected['payload']['metadata']['image_id'] ?? null), 'previewAudio'=>null, 'previewStimulus'=>null, 'previewKnowledge'=>collect()])
                    @can('content.edit')<a class="button button-primary" href="{{ route('admin.questions.edit', $selected['question']) }}">Chỉnh sửa câu hỏi</a>@endcan
                </div>
            </aside>
        @endif
    </div>
</div>
