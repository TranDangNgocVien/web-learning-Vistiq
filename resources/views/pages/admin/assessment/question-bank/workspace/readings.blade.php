@php
    $statusLabels = ['draft'=>'Bản nháp','review'=>'Chờ duyệt','approved'=>'Đã duyệt','published'=>'Đã xuất bản','archived'=>'Đã lưu trữ','mixed'=>'Hỗn hợp'];
    $payload = $entry['payload'] ?? [];
    $selectedStimulus = $entry['stimulus'] ?? null;
    $canEdit = auth()->user()->hasPermission('content.edit');
    $editing = $creating || request()->boolean('edit');
    $autoAdd = session('status') === 'Đã lưu tài nguyên bản nháp.';
    $editorUrl = $selectedStimulus && $canEdit && ($editing || $autoAdd) ? route('admin.readings.question-editor', $selectedStimulus) : '';
    $entryStatus = $entry['status'] ?? 'draft';
    $entryStatusClass = in_array($entryStatus, ['approved', 'published'], true) ? 'badge-mint' : ($entryStatus === 'review' ? 'badge-amber' : 'badge-soft');
    $questionReturnParams = request()->except('create', 'edit', 'question', 'preview_question', 'page');
    $reviewQuestions = $entry ? $entry['questions']->filter(fn ($question) => ($bankData['records'][$question->id]['status'] ?? null) === 'review')->values() : collect();
    $reviewQuestionBatch = $reviewQuestions->take(100);
@endphp
<div class="question-bank reading-library listening-library reading-admin-library" data-reading-library data-auto-add="{{ $autoAdd ? '1' : '0' }}" data-editor-url="{{ $editorUrl }}" data-initial-question="{{ request()->integer('question') ?: '' }}">
    @if(session('import_summary'))
        <div class="admin-toast {{ session('import_summary.failed') ? 'admin-toast-warning' : '' }}" role="status">
            <span>Đã nhập {{ number_format(session('import_summary.imported')) }} câu hỏi theo bài đọc; tổng {{ number_format(session('import_summary.total')) }} dòng.{{ session('import_summary.failed') ? ' Có lỗi theo sheet.' : '' }}</span>
            @if(session('import_errors'))
                <details class="import-errors"><summary>Xem lỗi</summary><ul>@foreach(session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul></details>
            @endif
        </div>
    @endif

    <header class="qb-heading listening-heading">
        <div>
            <h1>Câu hỏi Reading</h1>
            <p>Quản lý bài đọc, nhóm câu hỏi và nội dung liên kết.</p>
        </div>
        <div class="qb-heading-actions">
            @can('content.edit')<a class="button button-quiet" href="{{ route('admin.questions.deleted') }}">Câu hỏi đã xóa</a>@endcan
            @can('content.edit')
                @include('pages.admin.assessment.question-bank.workspace.question-workbook-import', ['workbookKind'=>'reading'])
                <a class="button button-quiet" href="{{ route('admin.questions.import-template', ['template'=>'reading-xlsx']) }}">@include('pages.dictionary.icon', ['name'=>'download']) Tải mẫu .xlsx</a>
                @include('pages.admin.assessment.question-bank.workspace.send-all-review')
                <a class="button button-primary" href="{{ route('admin.readings.index', ['create'=>1]) }}">@include('pages.dictionary.icon', ['name'=>'plus']) Tạo bài đọc</a>
            @endcan
        </div>
    </header>

    <section class="listening-stats" aria-label="Tổng quan Reading">
        <article class="listening-stat"><span class="listening-stat-icon is-blue">@include('pages.dictionary.icon', ['name'=>'book-open'])</span><div><span>Tổng bài Reading</span><strong>{{ number_format($readingStats['readings']) }}</strong></div></article>
        <article class="listening-stat"><span class="listening-stat-icon is-violet">@include('pages.dictionary.icon', ['name'=>'collection'])</span><div><span>Nhóm câu hỏi</span><strong>{{ number_format($readingStats['groups']) }}</strong></div></article>
        <article class="listening-stat"><span class="listening-stat-icon is-mint">@include('pages.dictionary.icon', ['name'=>'sound'])</span><div><span>Tổng câu hỏi</span><strong>{{ number_format($readingStats['questions']) }}</strong></div></article>
        <article class="listening-stat"><span class="listening-stat-icon is-green">@include('pages.dictionary.icon', ['name'=>'check-circle'])</span><div><span>Đã duyệt</span><strong>{{ number_format($readingStats['approved']) }}</strong></div></article>
    </section>

    <div class="listening-workspace reading-workspace {{ $entry || $creating ? 'has-detail' : '' }}">
        <section class="qb-directory" aria-label="Danh sách bài Reading">
            <form id="reading-filters" method="get" action="{{ route('admin.readings.index') }}" class="listening-filters" data-ajax-filter-form data-ajax-target="#reading-list-results" data-ajax-on-change data-ajax-debounce="350">
                <input type="hidden" name="stimulus" value="{{ $entry['id'] ?? '' }}">
                <label class="listening-search">Tìm bài<input type="search" name="q" value="{{ $search }}" placeholder="Mã bài, tiêu đề hoặc nội dung" autocomplete="off"></label>
                <label>Kỳ thi<select name="exam"><option value="">Tất cả</option>@foreach($examOptions as $option)<option value="{{ $option->code }}" @selected($examFilter === $option->code)>{{ $option->name }}</option>@endforeach</select></label>
                <label>Part / Section<select name="part"><option value="">Tất cả</option>@foreach($parts as $part)<option value="{{ $part->id }}" @selected($partId === $part->id)>{{ $part->examType?->name }} · {{ $part->name }}</option>@endforeach</select></label>
                <label>Trạng thái<select name="status"><option value="">Tất cả</option>@foreach($statusLabels as $code=>$label)<option value="{{ $code }}" @selected($statusFilter === $code)>{{ $label }}</option>@endforeach</select></label>
                <div class="listening-filter-actions"><button class="button button-primary" type="submit">@include('pages.dictionary.icon', ['name'=>'search']) Tìm kiếm</button><a class="text-link" data-ajax-filter-reset="#reading-list-results" href="{{ route('admin.readings.index', array_filter(['stimulus' => $entry['id'] ?? null])) }}">Xóa bộ lọc</a></div>
            </form>

            <div id="reading-list-results" data-ajax-results>
            <div class="qb-list listening-list">
                <div class="qb-list-heading"><span>{{ $readings->firstItem() ?? 0 }}–{{ $readings->lastItem() ?? 0 }} / {{ number_format($readings->total()) }} bài Reading</span><span>Sắp xếp: <b>Mới cập nhật</b></span></div>
                @can('content.edit')
                    <form id="reading-review-bulk" method="post" action="{{ route('admin.readings.review-bulk') }}" class="qb-bulk reading-review-bulk" data-pending-label="Đang gửi duyệt…">
                        @csrf
                        <span data-reading-review-summary aria-live="polite">Chọn bài đọc nháp để gửi duyệt.</span>
                        <button class="button button-primary" type="submit" data-reading-review-submit disabled>Gửi duyệt bài đã chọn <span data-reading-review-count></span></button>
                    </form>
                @endcan
                <div class="qb-table-scroll">
                    <table class="qb-table listening-table reading-admin-table">
                        <thead><tr><th class="qb-checkbox"><span class="sr-only">Chọn</span>@can('content.edit')<input type="checkbox" data-reading-review-select-all aria-label="Chọn tất cả bài Reading nháp trên trang này">@endcan</th><th>Mã bài</th><th>Bài đọc</th><th>Kỳ thi</th><th>Phần thi</th><th>Số câu</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr></thead>
                        <tbody>
                            @forelse($readings as $reading)
                                @php
                                    $readingUrl = route('admin.readings.index', array_merge(request()->except('create', 'edit', 'question', 'preview_question', 'page'), ['stimulus'=>$reading['id']]));
                                    $readingStatus = $reading['status'];
                                    $readingStatusClass = in_array($readingStatus, ['approved', 'published'], true) ? 'badge-mint' : ($readingStatus === 'review' ? 'badge-amber' : 'badge-soft');
                                    $readingExamTypes = $reading['parts']->pluck('examType')->filter()->unique('id');
                                @endphp
                                <tr class="{{ $entry && $entry['id'] === $reading['id'] ? 'is-active' : '' }}">
                                    <td class="qb-checkbox">@can('content.edit')@if($readingStatus === 'draft' && $reading['stimulus']?->draft_revision_id)<input type="checkbox" value="{{ $reading['id'] }}" data-revision-id="{{ $reading['stimulus']->draft_revision_id }}" data-reading-review-checkbox aria-label="Chọn bài đọc {{ $reading['code'] }} để gửi duyệt">@endif @endcan</td>
                                    <td><a class="reading-code" href="{{ $readingUrl }}">{{ $reading['code'] }}</a></td>
                                    <td><a href="{{ $readingUrl }}"><b>{{ $reading['title'] }}</b></a><small class="reading-excerpt">{{ \Illuminate\Support\Str::limit(strip_tags($reading['payload']['body'] ?? ''), 90) }}</small></td>
                                    <td>@foreach($readingExamTypes as $examType)<span class="listening-exam-tag {{ $examType->code === 'ielts' ? 'is-ielts' : '' }}">{{ strtoupper($examType->code) }}</span>@endforeach</td>
                                    <td>@foreach($reading['parts'] as $part)<span class="badge badge-soft listening-part-tag">{{ $part->name }}</span>@endforeach</td>
                                    <td><a href="{{ $readingUrl }}" @if($entry && $entry['id'] === $reading['id'])data-selected-reading-count @endif>{{ $reading['questions']->count() }}</a></td>
                                    <td><span class="badge {{ $readingStatusClass }}">{{ $statusLabels[$readingStatus] ?? $readingStatus }}</span></td>
                                    <td class="qb-updated">{{ $reading['updated_at']?->locale('vi')->diffForHumans() }}</td>
                                    <td>
                                        <div class="qb-row-actions">
                                            <a href="{{ $readingUrl }}" aria-label="Xem {{ $reading['code'] }}">@include('pages.dictionary.icon', ['name'=>'eye'])</a>
                                            @can('content.edit')
                                                @if($readingStatus === 'draft' && $reading['stimulus']?->draft_revision_id)
                                                    <form method="post" action="{{ route('admin.content.transition', ['kind'=>'stimulus', 'id'=>$reading['id'], 'action'=>'review']) }}" class="question-approval-action" data-pending-label="Đang gửi duyệt…">
                                                        @csrf
                                                        <button class="button button-primary" type="submit">Gửi duyệt</button>
                                                    </form>
                                                @endif
                                                <a href="{{ $readingUrl.'&edit=1' }}" aria-label="Sửa {{ $reading['code'] }}">@include('pages.dictionary.icon', ['name'=>'edit'])</a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9"><div class="qb-empty"><span class="listening-empty-icon">@include('pages.dictionary.icon', ['name'=>'book-open'])</span><h3>Chưa có bài Reading phù hợp</h3><p>Thử đổi bộ lọc hoặc import workbook để thêm bài đọc.</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('pages.admin.assessment.question-bank.workspace.pagination', ['paginator' => $readings, 'formId' => 'reading-filters', 'label' => 'Phân trang bài Reading'])
            </div>
            </div>
        </section>

        @if($entry || $creating)
            <aside class="listening-detail reading-admin-detail" aria-label="Chi tiết bài Reading">
                <header class="listening-detail-heading"><h2>{{ $creating ? 'Tạo bài Reading' : 'Chi tiết bài Reading' }}</h2><a class="listening-icon-button" href="{{ route('admin.readings.index', request()->except('stimulus', 'create', 'edit', 'question', 'preview_question')) }}" aria-label="Đóng chi tiết">@include('pages.dictionary.icon', ['name'=>'close'])</a></header>
                <div class="listening-detail-body">
                    @if($entry)
                        <div class="listening-detail-meta"><div><small>Mã bài:</small><b>{{ $entry['code'] }}</b></div><span class="badge {{ $entryStatusClass }}">{{ $statusLabels[$entryStatus] ?? $entryStatus }}</span></div>
                        <div class="listening-detail-tags">@foreach($entry['parts']->pluck('examType')->filter()->unique('id') as $examType)<span class="listening-exam-tag {{ $examType->code === 'ielts' ? 'is-ielts' : '' }}">{{ strtoupper($examType->code) }}</span>@endforeach @foreach($entry['parts'] as $part)<span class="badge badge-soft">{{ $part->name }}</span>@endforeach</div>
                        @include('shared.workflow-actions', ['kind'=>'stimulus', 'entityId'=>$selectedStimulus->id, 'revision'=>$workflowRevision])
                        @include('pages.admin.assessment.question-bank.workspace.send-review')
                    @endif

                    @if($canEdit && $editing)
                        <form class="reading-content-form" method="post" action="{{ $selectedStimulus ? route('admin.stimuli.update', $selectedStimulus) : route('admin.readings.store') }}" data-unsaved-form>
                            @csrf
                            @if($selectedStimulus)@method('PUT')@endif
                            <input type="hidden" name="stimulus_type" value="reading">
                            <input type="hidden" name="revision_counter" value="{{ $selectedStimulus?->revision_counter ?? 0 }}">
                            <label>Tiêu đề<input name="title" value="{{ old('title', $payload['title'] ?? '') }}" maxlength="255" required></label>
                            <label>Kỳ thi / Phần<select name="exam_part_id" required><option value="">Chọn phần</option>@foreach($parts as $part)<option value="{{ $part->id }}" @selected((int)old('exam_part_id', $payload['metadata']['exam_part_id'] ?? (isset($entry['parts']) ? $entry['parts']->first()?->id : 0)) === $part->id)>{{ $part->examType?->name }} · {{ $part->name }}</option>@endforeach</select><small>TOEIC Part 6 được lưu dưới dạng Paragraph.</small></label>
                            <label>Nội dung bài<textarea name="body" data-rich-editor rows="12">{{ old('body', $payload['body'] ?? '') }}</textarea></label>
                            <button class="button button-primary" type="submit">{{ $selectedStimulus ? 'Lưu bài đọc' : 'Lưu và thêm câu hỏi' }}</button>
                        </form>
                        @if($entry)
                            <div data-reading-questions>@include('pages.admin.assessment.question-bank.workspace.reading-questions')</div>
                            <div class="reading-editor-heading" data-reading-editor-heading hidden><h3 data-reading-editor-title>Câu hỏi</h3><button class="button button-quiet" type="button" data-reading-close-editor>Đóng câu hỏi</button></div>
                            <div data-reading-editor aria-live="polite"></div>
                            <p class="qe-feedback" data-reading-feedback role="alert" hidden></p>
                        @endif
                    @elseif($entry)
                        <section class="listening-audio-section reading-admin-passage">
                            <span class="listening-field-label">Bài đọc</span>
                            <h3>{{ $entry['title'] }}</h3>
                            <div class="qb-rich-text reading-body">{!! app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class)->sanitize($payload['body'] ?? '') !!}</div>
                        </section>

                        <section class="listening-linked-section">
                            <div class="listening-section-title"><h3>{{ $entry['questions']->count() }} câu hỏi liên kết</h3><a href="{{ route('admin.questions.index', ['q'=>$entry['code']]) }}">Mở ngân hàng</a></div>
                            @can('content.review')
                                @if($reviewQuestions->isNotEmpty())
                                    <form method="post" action="{{ route('admin.questions.approve') }}" class="assessment-actions question-approval-bulk" data-confirm="Duyệt {{ $reviewQuestions->count() }} câu hỏi đang chờ trong bài đọc này?" data-confirm-title="Xác nhận duyệt câu hỏi" data-confirm-label="Duyệt {{ $reviewQuestions->count() }} câu" data-confirm-tone="safe" data-pending-label="Đang duyệt…">
                                        @csrf
                                        @foreach($reviewQuestionBatch as $reviewQuestion)
                                            <input type="hidden" name="ids[]" value="{{ $reviewQuestion->id }}">
                                            <input type="hidden" name="revision_ids[{{ $reviewQuestion->id }}]" value="{{ $reviewQuestion->draft_revision_id }}">
                                        @endforeach
                                        <button type="submit" class="button button-primary">Duyệt {{ $reviewQuestionBatch->count() }} câu đang chờ{{ $reviewQuestions->count() > 100 ? ' (còn '.($reviewQuestions->count() - 100).' câu)' : '' }}</button>
                                    </form>
                                @endif
                            @endcan
                            <div class="listening-linked-list">
                                @forelse($entry['questions'] as $question)
                                    @php
                                        $questionUrl = route('admin.readings.index', array_merge($questionReturnParams, ['stimulus'=>$entry['id'], 'preview_question'=>$question->id]));
                                        $questionIsActive = $selectedQuestion?->id === $question->id;
                                    @endphp
                                    <a class="listening-linked-row {{ $questionIsActive ? 'is-active' : '' }}" href="{{ $questionUrl }}" aria-current="{{ $questionIsActive ? 'true' : 'false' }}"><span>{{ $loop->iteration }}</span><b>Q{{ str_pad((string)$question->id, 5, '0', STR_PAD_LEFT) }}</b><span>{{ \Illuminate\Support\Str::limit(strip_tags($bankData['records'][$question->id]['payload']['prompt'] ?? ''), 75) }}</span></a>
                                @empty
                                    <p class="qb-preview-note">Chưa có câu hỏi liên kết với bài đọc này.</p>
                                @endforelse
                            </div>
                        </section>

                        @if($selectedQuestion && $selectedQuestionPayload)
                            @php
                                $previewAssets = $bankData['assets'];
                                $questionTags = collect();
                                $partLabel = fn ($part) => $part?->examType?->code === 'toeic' ? ucfirst(str_replace('-', ' ', $part->code)) : ($part?->name ?? '—');
                                $questionIds = $entry['questions']->keys()->values();
                                $previousQuestionId = $questionIds->get($selectedQuestionIndex - 2);
                                $nextQuestionId = $questionIds->get($selectedQuestionIndex);
                            @endphp
                            <section class="listening-question-section" aria-label="Chi tiết câu hỏi">
                                <div class="listening-section-title">
                                    <div><h3>Chi tiết câu hỏi</h3><small>Q{{ str_pad((string)$selectedQuestion->id, 5, '0', STR_PAD_LEFT) }}</small></div>
                                    <nav class="listening-question-pager" aria-label="Chuyển câu hỏi">
                                        @if($previousQuestionId)<a href="{{ route('admin.readings.index', array_merge($questionReturnParams, ['stimulus'=>$entry['id'], 'preview_question'=>$previousQuestionId])) }}" aria-label="Câu trước">‹</a>@else<span aria-disabled="true">‹</span>@endif
                                        <span>{{ $selectedQuestionIndex }} / {{ $entry['questions']->count() }}</span>
                                        @if($nextQuestionId)<a href="{{ route('admin.readings.index', array_merge($questionReturnParams, ['stimulus'=>$entry['id'], 'preview_question'=>$nextQuestionId])) }}" aria-label="Câu tiếp theo">›</a>@else<span aria-disabled="true">›</span>@endif
                                    </nav>
                                </div>
                                <div class="listening-question-preview">@include('pages.admin.assessment.question-bank.questions.preview', ['question'=>$selectedQuestion, 'payload'=>$selectedQuestionPayload, 'withinEntry'=>true, 'withinListening'=>true])</div>
                                @include('pages.admin.assessment.question-bank.workspace.approve-question', ['questionId' => $selectedQuestion->id, 'reviewRevisionId' => $selectedQuestion->draft_revision_id, 'questionStatus' => $bankData['records'][$selectedQuestion->id]['status'] ?? null, 'questionLabel' => 'Q-'.$selectedQuestion->id])
                            </section>
                            <div class="listening-detail-accordions">
                                <details><summary>Giải thích đáp án @include('pages.dictionary.icon', ['name'=>'chevron-down'])</summary>@if(filled($selectedQuestionPayload['explanation'] ?? ''))<div class="qb-rich-text">{!! app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class)->sanitize($selectedQuestionPayload['explanation']) !!}</div>@else<p>Chưa có giải thích cho câu hỏi này.</p>@endif</details>
                            </div>
                        @endif
                        @if($canEdit && $autoAdd)
                            <div data-reading-questions>@include('pages.admin.assessment.question-bank.workspace.reading-questions')</div>
                            <div class="reading-editor-heading" data-reading-editor-heading hidden><h3 data-reading-editor-title>Câu hỏi</h3><button class="button button-quiet" type="button" data-reading-close-editor>Đóng câu hỏi</button></div>
                            <div data-reading-editor aria-live="polite"></div>
                            <p class="qe-feedback" data-reading-feedback role="alert" hidden></p>
                        @endif
                    @else
                        <div class="reading-create-note"><span class="listening-empty-icon">@include('pages.dictionary.icon', ['name'=>'book-open'])</span><p>Nhập thông tin bài đọc để bắt đầu liên kết câu hỏi.</p></div>
                    @endif
                </div>
                @if($entry && ! $editing && $canEdit)
                    <footer class="listening-detail-actions">
                        <a class="button button-quiet" href="{{ route('admin.readings.index', array_merge($questionReturnParams, ['stimulus'=>$entry['id'], 'edit'=>1])) }}">@include('pages.dictionary.icon', ['name'=>'edit']) Sửa bài đọc</a>
                        @if($selectedQuestion)<a class="button button-primary" href="{{ route('admin.readings.index', array_merge($questionReturnParams, ['stimulus'=>$entry['id'], 'edit'=>1, 'question'=>$selectedQuestion->id])) }}">@include('pages.dictionary.icon', ['name'=>'edit']) Sửa câu hỏi</a>@include('pages.admin.assessment.question-bank.workspace.question-delete-action', ['question'=>$selectedQuestion])@endif
                    </footer>
                @endif
            </aside>
        @endif
    </div>
</div>

@include('pages.admin.assessment.question-bank.workspace.standalone-questions')
