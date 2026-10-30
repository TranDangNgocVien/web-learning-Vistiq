@php
    $statusLabels = ['draft'=>'Bản nháp','review'=>'Chờ duyệt','approved'=>'Đã duyệt','published'=>'Đã xuất bản','archived'=>'Đã lưu trữ','mixed'=>'Hỗn hợp'];
    $formatDuration = static function (?int $seconds): string {
        if (! $seconds) {
            return '—';
        }

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    };
@endphp
<div class="question-bank listening-library">
    @if(session('import_summary'))
        <div class="admin-toast {{ session('import_summary.failed') ? 'admin-toast-warning' : '' }}" role="{{ session('import_summary.failed') ? 'alert' : 'status' }}" aria-live="polite" data-admin-toast data-admin-toast-timeout="{{ session('import_summary.failed') ? 0 : 6000 }}">
            <span>Đã import {{ number_format(session('import_summary.imported')) }}/{{ number_format(session('import_summary.total')) }} câu.{{ session('import_summary.failed') ? ' Có lỗi theo sheet.' : '' }}</span>
            @if(session('import_errors'))
                <details class="import-errors"><summary>Xem lỗi</summary><ul>@foreach(session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul></details>
            @endif
            <button type="button" data-dismiss-admin-toast aria-label="Đóng thông báo">×</button>
        </div>
    @endif

    <header class="qb-heading listening-heading">
        <div>
            <h1>Câu hỏi Listening</h1>
            <p>Quản lý bài nghe, nhóm audio và câu hỏi liên kết.</p>
        </div>
        <div class="qb-heading-actions">
            @can('content.edit')<a class="button button-quiet" href="{{ route('admin.questions.deleted') }}">Câu hỏi đã xóa</a>@endcan
            <a class="button button-quiet" href="{{ route('admin.questions.import-template', ['template'=>'listening-xlsx']) }}">@include('pages.dictionary.icon', ['name' => 'download']) Tải mẫu .xlsx</a>
            @can('content.edit')
                @include('pages.admin.assessment.question-bank.workspace.question-workbook-import', ['workbookKind'=>'listening'])
                @include('pages.admin.assessment.question-bank.workspace.send-all-review')
                <a class="button button-primary" href="{{ route('admin.questions.create', ['exam'=>'toeic']) }}">@include('pages.dictionary.icon', ['name' => 'plus']) Tạo câu hỏi</a>
            @endcan
        </div>
    </header>

    <section class="listening-stats" aria-label="Tổng quan Listening">
        <article class="listening-stat"><span class="listening-stat-icon is-blue">@include('pages.dictionary.icon', ['name' => 'listening'])</span><div><span>Tổng bài Listening</span><strong>{{ number_format($listeningStats['lessons']) }}</strong></div></article>
        <article class="listening-stat"><span class="listening-stat-icon is-violet">@include('pages.dictionary.icon', ['name' => 'collection'])</span><div><span>Nhóm audio</span><strong>{{ number_format($listeningStats['groups']) }}</strong></div></article>
        <article class="listening-stat"><span class="listening-stat-icon is-mint">@include('pages.dictionary.icon', ['name' => 'sound'])</span><div><span>Tổng câu hỏi</span><strong>{{ number_format($listeningStats['questions']) }}</strong></div></article>
        <article class="listening-stat"><span class="listening-stat-icon is-green">@include('pages.dictionary.icon', ['name' => 'check-circle'])</span><div><span>Đã duyệt</span><strong>{{ number_format($listeningStats['approved']) }}</strong></div></article>
    </section>

    <div class="listening-workspace {{ $entry ? 'has-detail' : '' }}">
        <section class="qb-directory" aria-label="Danh sách bài Listening">
            <form id="listening-filters" method="get" action="{{ route('admin.questions.listening') }}" class="listening-filters" data-ajax-filter-form data-ajax-target="#listening-list-results" data-ajax-on-change data-ajax-debounce="350">
                <input type="hidden" name="group" value="{{ $entry['id'] ?? '' }}">
                <input type="hidden" name="question" value="{{ request('question', '') }}">
                <label class="listening-search">Tìm bài<input type="search" name="q" value="{{ $search }}" placeholder="Mã bài hoặc tiêu đề" autocomplete="off"></label>
                <label>Kỳ thi<select name="exam"><option value="">Tất cả</option>@foreach($examOptions as $option)<option value="{{ $option['code'] }}" @selected($examFilter === $option['code'])>{{ $option['name'] }}</option>@endforeach</select></label>
                <label>Part / Section<select name="part"><option value="">Tất cả</option>@foreach($partOptions as $option)<option value="{{ $option['id'] }}" @selected($partFilter === $option['id'])>{{ $option['exam'] }} · {{ $option['name'] }}</option>@endforeach</select></label>
                <label>Trạng thái<select name="status"><option value="">Tất cả</option>@foreach($statusLabels as $code=>$label)<option value="{{ $code }}" @selected($statusFilter === $code)>{{ $label }}</option>@endforeach</select></label>
                <label>Có audio<select name="audio"><option value="">Tất cả</option><option value="yes" @selected($audioFilter === 'yes')>Có audio</option><option value="no" @selected($audioFilter === 'no')>Chưa có audio</option></select></label>
                <div class="listening-filter-actions"><button class="button button-primary" type="submit">@include('pages.dictionary.icon', ['name' => 'search']) Tìm kiếm</button><a class="text-link" data-ajax-filter-reset="#listening-list-results" href="{{ route('admin.questions.listening', array_filter(['group' => $entry['id'] ?? null, 'question' => request('question')])) }}">Xóa bộ lọc</a></div>
            </form>

            <div id="listening-list-results" data-ajax-results>
            <div class="qb-list listening-list">
                <div class="qb-list-heading"><span>{{ $lessons->firstItem() ?? 0 }}–{{ $lessons->lastItem() ?? 0 }} / {{ number_format($lessons->total()) }} bài Listening</span><span>Sắp xếp: <b>Mới cập nhật</b></span></div>
                <div class="qb-table-scroll"><table class="qb-table listening-table"><thead><tr><th>Mã bài</th><th>Bài nghe</th><th>Kỳ thi</th><th>Phần thi</th><th>Nhóm audio</th><th>Số câu</th><th>Thời lượng</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr></thead><tbody>
                    @forelse($lessons as $lesson)
                        @php
                            $lessonUrl = route('admin.questions.listening', array_merge(request()->except('group', 'question', 'page'), ['group'=>$lesson['id']]));
                            $lessonStatus = $lesson['question_status'];
                            $statusClass = in_array($lessonStatus, ['published', 'approved'], true) ? 'badge-mint' : ($lessonStatus === 'review' ? 'badge-amber' : 'badge-soft');
                            $firstQuestion = $lesson['questions']->first();
                            $audioCode = $lesson['audio_group_code'] ?: ($lesson['audio_id'] ? 'AUDIO_'.str_pad((string) $lesson['audio_id'], 3, '0', STR_PAD_LEFT) : '—');
                        @endphp
                        <tr class="{{ $entry && $entry['id'] === $lesson['id'] ? 'is-active' : '' }}">
                            <td><a class="reading-code listening-lesson-code" href="{{ $lessonUrl }}" title="{{ $lesson['code'] }}">{{ $lesson['code'] }}</a></td>
                            <td><a class="listening-lesson-title" href="{{ $lessonUrl }}" title="{{ $lesson['title'] }}"><b>{{ $lesson['title'] }}</b></a><small>{{ $lesson['has_audio'] ? 'Có audio' : 'Chưa có audio' }}</small></td>
                            <td>@foreach($lesson['parts']->pluck('examType')->filter()->unique('id') as $examType)<span class="listening-exam-tag {{ $examType->code === 'ielts' ? 'is-ielts' : '' }}">{{ strtoupper($examType->code) }}</span>@endforeach</td>
                            <td>@foreach($lesson['parts'] as $part)<span class="badge badge-soft listening-part-tag">{{ $part->name }}</span>@endforeach</td>
                            <td><span class="listening-audio-code">{{ $audioCode }}</span></td>
                            <td><a href="{{ $lessonUrl }}">{{ $lesson['questions']->count() }}</a></td>
                            <td class="listening-duration">{{ $formatDuration($lesson['duration_seconds']) }}</td>
                            <td><span class="badge {{ $statusClass }}">{{ $statusLabels[$lessonStatus] ?? $lessonStatus }}</span></td>
                            <td class="qb-updated">{{ $lesson['updated_at']?->locale('vi')->diffForHumans() }}</td>
                            <td><div class="qb-row-actions"><a href="{{ $lessonUrl }}" aria-label="Xem {{ $lesson['code'] }}">@include('pages.dictionary.icon', ['name'=>'eye'])</a>@can('content.edit')@if($firstQuestion)<a href="{{ route('admin.questions.edit', $firstQuestion) }}" aria-label="Sửa câu hỏi trong {{ $lesson['code'] }}">@include('pages.dictionary.icon', ['name'=>'edit'])</a>@endif @endcan</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="qb-empty"><span class="listening-empty-icon">@include('pages.dictionary.icon', ['name' => 'listening'])</span><h3>Chưa có bài Listening phù hợp</h3><p>Thử đổi bộ lọc hoặc import workbook để thêm bài nghe.</p></div></td></tr>
                    @endforelse
                </tbody></table></div>
                @include('pages.admin.assessment.question-bank.workspace.pagination', ['paginator' => $lessons, 'formId' => 'listening-filters', 'label' => 'Phân trang bài Listening'])
            </div>
            </div>
        </section>

        @if($entry)
            @php
                $audioAsset = $entry['audio_asset'];
                $transcript = $entry['payload']['transcript'] ?? $audioAsset?->metadata['transcript'] ?? '';
                $questionIds = $entry['questions']->keys()->values();
                $previousQuestionId = $questionIds->get($selectedQuestionIndex - 2);
                $nextQuestionId = $questionIds->get($selectedQuestionIndex);
                $questionReturnParams = request()->except('question', 'page');
            @endphp
            <aside class="listening-detail" aria-label="Chi tiết bài Listening">
                <header class="listening-detail-heading"><h2>Chi tiết bài Listening</h2><a class="listening-icon-button" href="{{ route('admin.questions.listening', request()->except('group', 'question')) }}" aria-label="Đóng chi tiết">@include('pages.dictionary.icon', ['name'=>'close'])</a></header>
                <div class="listening-detail-body">
                    @include('pages.admin.assessment.question-bank.workspace.send-review')
                    <div class="listening-detail-meta"><div><small>Mã bài:</small><b>{{ $entry['code'] }}</b></div><span class="badge {{ in_array($entry['question_status'], ['approved', 'published'], true) ? 'badge-mint' : ($entry['question_status'] === 'review' ? 'badge-amber' : 'badge-soft') }}">{{ $statusLabels[$entry['question_status']] ?? $entry['question_status'] }}</span></div>
                    <div class="listening-detail-tags">@foreach($entry['parts']->pluck('examType')->filter()->unique('id') as $examType)<span class="listening-exam-tag {{ $examType->code === 'ielts' ? 'is-ielts' : '' }}">{{ strtoupper($examType->code) }}</span>@endforeach @foreach($entry['parts'] as $part)<span class="badge badge-soft">{{ $part->name }}</span>@endforeach</div>

                    <section class="listening-audio-section">
                        <span class="listening-field-label">Tên bài nghe</span>
                        <h3>{{ $entry['title'] }}</h3>
                        <div class="listening-audio-label"><span>Nhóm audio</span><b>{{ $entry['audio_group_code'] ?: ($entry['audio_id'] ? 'AUDIO_'.str_pad((string) $entry['audio_id'], 3, '0', STR_PAD_LEFT) : 'Chưa liên kết') }}</b></div>
                        @if($entry['audio_url'])
                            <div class="listening-player" data-listening-player>
                                <audio src="{{ $entry['audio_url'] }}" preload="none" data-listening-audio></audio>
                                <button class="listening-play-button" type="button" data-listening-toggle aria-label="Phát audio">@include('pages.dictionary.icon', ['name'=>'play'])</button>
                                <div class="listening-player-track">
                                    <div class="listening-waveform" aria-hidden="true">@foreach([13,20,10,25,17,31,22,13,28,18,35,24,14,29,19,37,25,16,30,22,40,27,17,33,22,36,19,12,28,20,39,25,16,32,21,35,18,13,28,20,37,24,15,31,19,34,22,14,29,18,38,26,15,32,20,36,24,13,29,19,33,22,12,27,18,35,24,15] as $height)<i style="--wave-height:{{ $height }}%"></i>@endforeach</div>
                                    <input class="listening-seek" type="range" min="0" max="100" value="0" step="0.1" data-listening-seek aria-label="Tua audio">
                                    <div class="listening-player-time"><span data-listening-current>00:00</span><span data-listening-duration>{{ $formatDuration($entry['duration_seconds']) }}</span></div>
                                </div>
                                <select class="listening-speed" data-listening-speed aria-label="Tốc độ phát"><option value="1">1.0x</option><option value="1.25">1.25x</option><option value="1.5">1.5x</option><option value="2">2.0x</option></select>
                            </div>
                        @else
                            <p class="listening-audio-missing">Bài nghe chưa có file audio.</p>
                        @endif
                    </section>

                    <section class="listening-linked-section">
                        <div class="listening-section-title"><h3>{{ $entry['questions']->count() }} câu hỏi liên kết</h3><a href="{{ route('admin.questions.index', ['q'=>$entry['code']]) }}">Mở ngân hàng</a></div>
                        <div class="listening-linked-list">
                            @foreach($entry['questions'] as $question)
                                @php
                                    $questionUrl = route('admin.questions.listening', array_merge($questionReturnParams, ['group'=>$entry['id'], 'question'=>$question->id]));
                                @endphp
                                <a class="listening-linked-row {{ $selectedQuestion?->id === $question->id ? 'is-active' : '' }}" href="{{ $questionUrl }}" aria-current="{{ $selectedQuestion?->id === $question->id ? 'true' : 'false' }}"><span>{{ $loop->iteration }}</span><b>Q{{ str_pad((string) $question->id, 5, '0', STR_PAD_LEFT) }}</b><span>{{ \Illuminate\Support\Str::limit(strip_tags($bankData['records'][$question->id]['payload']['prompt'] ?? ''), 75) }}</span></a>
                            @endforeach
                        </div>
                    </section>

                    @if($selectedQuestion && $selectedQuestionPayload)
                        @php
                            $previewAssets = $bankData['assets'];
                            $questionTags = \Illuminate\Support\Facades\DB::table('learning_item_tags')->join('tags', 'tags.id', '=', 'learning_item_tags.tag_id')->where('learning_item_id', $selectedQuestion->learning_item_id)->select('learning_item_id', 'tags.name')->get()->groupBy('learning_item_id');
                            $partLabel = fn ($part) => $part?->examType?->code === 'toeic' ? ucfirst(str_replace('-', ' ', $part->code)) : ($part?->name ?? '—');
                        @endphp
                        <section class="listening-question-section" aria-label="Chi tiết câu hỏi">
                            <div class="listening-section-title"><div><h3>Chi tiết câu hỏi</h3><small>Q{{ str_pad((string) $selectedQuestion->id, 5, '0', STR_PAD_LEFT) }}</small></div><nav class="listening-question-pager" aria-label="Chuyển câu hỏi">
                                @if($previousQuestionId)<a href="{{ route('admin.questions.listening', array_merge($questionReturnParams, ['group'=>$entry['id'], 'question'=>$previousQuestionId])) }}" aria-label="Câu trước">‹</a>@else<span aria-disabled="true">‹</span>@endif
                                <span>{{ $selectedQuestionIndex }} / {{ $entry['questions']->count() }}</span>
                                @if($nextQuestionId)<a href="{{ route('admin.questions.listening', array_merge($questionReturnParams, ['group'=>$entry['id'], 'question'=>$nextQuestionId])) }}" aria-label="Câu tiếp theo">›</a>@else<span aria-disabled="true">›</span>@endif
                            </nav></div>
                            <div class="listening-question-preview">@include('pages.admin.assessment.question-bank.questions.preview', ['question'=>$selectedQuestion, 'payload'=>$selectedQuestionPayload, 'withinEntry'=>true, 'withinListening'=>true])</div>
                        </section>
                    @endif

                    <div class="listening-detail-accordions">
                        <details><summary>Giải thích đáp án @include('pages.dictionary.icon', ['name'=>'chevron-down'])</summary>@if($selectedQuestion && filled($selectedQuestionPayload['explanation'] ?? ''))<div class="qb-rich-text">{!! app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class)->sanitize($selectedQuestionPayload['explanation']) !!}</div>@else<p>Chưa có giải thích cho câu hỏi này.</p>@endif</details>
                        <details><summary>Transcript @include('pages.dictionary.icon', ['name'=>'chevron-down'])</summary>@if(filled($transcript))<div class="qb-rich-text">{!! app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class)->sanitize($transcript) !!}</div>@else<p>Chưa có transcript cho bài nghe này.</p>@endif</details>
                    </div>
                </div>
                @if($selectedQuestion)<footer class="listening-detail-actions">@can('content.edit')<a class="button button-quiet" href="{{ route('admin.questions.edit', $selectedQuestion) }}">@include('pages.dictionary.icon', ['name'=>'edit']) Chỉnh sửa</a>@include('pages.admin.assessment.question-bank.workspace.question-delete-action', ['question'=>$selectedQuestion])@endcan<a class="button button-primary" href="{{ route('admin.questions.edit', $selectedQuestion) }}">@include('pages.dictionary.icon', ['name'=>'eye']) Xem chi tiết</a></footer>@endif
            </aside>
        @endif
    </div>
</div>

@include('pages.admin.assessment.question-bank.workspace.standalone-questions')
