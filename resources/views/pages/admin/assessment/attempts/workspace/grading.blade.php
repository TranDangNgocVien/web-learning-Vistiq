@php
    $answer = $answers->first();
    $item = $answer ? collect($answer->attempt->snapshot['items'] ?? [])->firstWhere('key', $answer->item_key) : null;
    $criteria = $item ? ($gradingCriteria[$item['type']] ?? []) : [];
    $labels = [
        'task_response' => 'Task Response', 'coherence' => 'Coherence & Cohesion',
        'fluency' => 'Fluency & Coherence', 'lexical_resource' => 'Lexical Resource',
        'grammar' => 'Grammatical Range & Accuracy', 'pronunciation' => 'Pronunciation',
    ];
    $keepInput = $answer && (string) old('grading_answer_id') === (string) $answer->id;
    $scores = collect($criteria)->mapWithKeys(fn ($criterion) => [$criterion => $keepInput ? old('criteria.'.$criterion) : data_get($answer?->grade, 'criteria.'.$criterion)]);
    $filledScores = $scores->filter(fn ($score) => $score !== null && $score !== '');
    $band = $filledScores->count() === 4 ? round($filledScores->sum() / 4 * 2) / 2 : null;
    $responseText = (string) data_get($answer?->response, 'text', '');
    $wordCount = $responseText === '' ? 0 : preg_match_all('/\S+/u', $responseText);
    $durationSeconds = $answer?->attempt?->started_at && $answer?->attempt?->submitted_at
        ? max(0, (int) $answer->attempt->started_at->diffInSeconds($answer->attempt->submitted_at)) : null;
    $questionType = ($item['type'] ?? null) === 'speaking' ? 'Speaking' : 'Writing';
    $examType = strtoupper((string) data_get($answer?->attempt?->snapshot, 'exam_type', ''));
@endphp

<div class="grading-page">
    <nav class="grading-breadcrumbs" aria-label="Đường dẫn">
        <span>Admin Studio</span><span aria-hidden="true">/</span>
        <a href="{{ route('admin.attempts.index') }}">Lượt thi &amp; báo cáo</a><span aria-hidden="true">/</span>
        <span aria-current="page">Chấm điểm bài làm</span>
    </nav>
    <header class="grading-heading">
        <div><h1>Chấm điểm bài làm</h1><p>Xem câu trả lời và chấm theo rubric của đề.</p></div>
        @if($answer)
            <div class="grading-toolbar" aria-label="Điều hướng bài chấm">
                @if($answers->previousPageUrl())
                    <a class="button button-quiet grading-page-button" href="{{ $answers->previousPageUrl() }}" aria-label="Bài trước" title="Bài trước">‹</a>
                @else
                    <span class="button button-quiet grading-page-button is-disabled" aria-disabled="true">‹</span>
                @endif
                <strong>Bài {{ $answers->currentPage() }} / {{ $answers->total() }}</strong>
                @if($answers->nextPageUrl())
                    <a class="button button-quiet grading-page-button" href="{{ $answers->nextPageUrl() }}" aria-label="Bài tiếp theo" title="Bài tiếp theo">›</a>
                @else
                    <span class="button button-quiet grading-page-button is-disabled" aria-disabled="true">›</span>
                @endif
                <button class="button button-primary grading-save-top" type="submit" form="grading-form-{{ $answer->id }}">{{ $answer->grade ? 'Lưu điểm điều chỉnh' : 'Lưu kết quả' }}</button>
            </div>
        @endif
    </header>

    @if($answer && $item)
        @if($errors->any() && $keepInput)<div class="grading-error-summary" role="alert">Chưa lưu được điểm. Kiểm tra các trường được đánh dấu bên dưới.</div>@endif
        <div class="grading-columns" id="grading-answer-{{ $answer->id }}">
            <section class="panel grading-submission" aria-labelledby="grading-submission-title">
                <div class="grading-person">
                    <span class="grading-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($answer->attempt->user?->name ?? 'H', 0, 1)) }}</span>
                    <div class="grading-person-main">
                        <h2 id="grading-submission-title">{{ $answer->attempt->user?->name ?? 'Học viên' }} <span>· {{ $answer->item_key }}</span></h2>
                        <p>{{ $questionType }}{{ $examType ? ' · '.$examType : '' }} · Revision đề #{{ $answer->attempt->exam_revision_id ?? '—' }}</p>
                        <a class="grading-attempt-link" href="{{ route('admin.attempts.show', $answer->attempt) }}">Xem lượt thi #{{ $answer->exam_attempt_id }}</a>
                    </div>
                    <dl class="grading-person-meta">
                        <div><dt>Nộp bài</dt><dd>{{ $answer->attempt->submitted_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                        <div><dt>Thời gian làm</dt><dd>{{ $durationSeconds === null ? '—' : intdiv($durationSeconds, 60).' phút' }}</dd></div>
                    </dl>
                </div>
                <section class="grading-prompt" aria-labelledby="grading-prompt-title">
                    <div class="grading-section-head"><h3 id="grading-prompt-title">Đề bài</h3><span class="badge badge-blue">{{ $item['part'] ?? $questionType }}</span></div>
                    <div class="grading-prompt-content">{!! app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class)->sanitize($item['prompt'] ?? '') !!}</div>
                </section>
                <section class="grading-answer" aria-labelledby="grading-answer-title">
                    <div class="grading-section-head"><h3 id="grading-answer-title">Bài làm của thí sinh</h3>@if($responseText !== '')<span class="grading-word-count">{{ $wordCount }} từ</span>@endif</div>
                    @if(trim($responseText) !== '')
                        <div class="grading-answer-content">@foreach(preg_split('/\R{2,}/u', trim($responseText)) as $paragraph)<p>{!! nl2br(e($paragraph)) !!}</p>@endforeach</div>
                    @else
                        <div class="grading-answer-empty">Học viên không nộp câu trả lời dạng văn bản.</div>
                    @endif
                    @if(data_get($answer->response, 'asset_id'))
                        <div class="grading-recording"><strong>Bản ghi âm của thí sinh</strong><audio controls preload="metadata" aria-label="Bản ghi âm câu {{ $answer->item_key }}" src="{{ route('admin.attempts.recording', [$answer->attempt, $answer->response['asset_id']]) }}"></audio></div>
                    @endif
                </section>
            </section>

            <section class="panel grading-results" aria-labelledby="grading-results-title">
                <div class="grading-results-heading">
                    <div><h2 id="grading-results-title">Kết quả chấm điểm</h2><p>{{ $questionType }} · {{ $answer->grade ? 'Đã chấm' : 'Chờ chấm' }}</p></div>
                    <span class="grading-state {{ $answer->grade ? 'is-graded' : '' }}" data-grading-state>{{ $answer->grade ? 'Đã công bố' : 'Chưa có điểm' }}</span>
                </div>
                <p class="grading-required-note">Để lưu: chọn đủ 4 điểm và nhập nhận xét chung{{ $answer->grade ? ', kèm lý do điều chỉnh' : '' }}.</p>
                <form method="post" action="{{ route('admin.grading.store', $answer) }}" id="grading-form-{{ $answer->id }}" class="grading-form" data-grading-form data-pending-label="Đang lưu điểm…" @if($answer->grade) data-confirm="Chấm lại câu {{ $answer->item_key }} của lượt #{{ $answer->exam_attempt_id }}? Kết quả sẽ được cập nhật và ghi audit." data-confirm-label="Lưu điểm điều chỉnh" data-confirm-tone="safe" @endif>
                    @csrf
                    <input type="hidden" name="grade_token" value="{{ hash('sha256', json_encode($answer->grade ?? [], JSON_THROW_ON_ERROR)) }}">
                    <input type="hidden" name="grading_answer_id" value="{{ $answer->id }}">
                    <div class="grading-score-grid" aria-label="Điểm theo bốn tiêu chí">
                        <div class="grading-band-card"><span>Band của câu này</span><strong data-grading-band>{{ $band === null ? '—' : number_format($band, 1) }}</strong><small>{{ $band === null ? 'Chọn đủ 4 tiêu chí' : 'Tính từ 4 tiêu chí' }}</small></div>
                        @foreach($criteria as $index => $criterion)
                            <label class="grading-score-card grading-tone-{{ $index + 1 }}" for="grading-score-{{ $criterion }}">
                                <span>{{ $labels[$criterion] ?? \Illuminate\Support\Str::headline($criterion) }} <b aria-hidden="true">*</b></span>
                                <select id="grading-score-{{ $criterion }}" name="criteria[{{ $criterion }}]" required aria-invalid="{{ $errors->has('criteria.'.$criterion) ? 'true' : 'false' }}" @if($errors->has('criteria.'.$criterion)) aria-describedby="grading-score-error-{{ $criterion }}" @endif>
                                    <option value="">Chọn điểm</option>
                                    @for($half = 0; $half <= 18; $half++)
                                        @php($score = number_format($half / 2, 1))
                                        <option value="{{ $score }}" @selected((string) $scores[$criterion] !== '' && (float) $scores[$criterion] === $half / 2)>{{ $score }}</option>
                                    @endfor
                                </select>
                                <span class="grading-score-track" aria-hidden="true"><i style="width: {{ $scores[$criterion] === null || $scores[$criterion] === '' ? 0 : (float) $scores[$criterion] / 9 * 100 }}%"></i></span>
                                @error('criteria.'.$criterion)<span class="grading-field-error" id="grading-score-error-{{ $criterion }}" role="alert">{{ $message }}</span>@enderror
                            </label>
                        @endforeach
                    </div>
                    @error('criteria')<p class="grading-field-error" role="alert">{{ $message }}</p>@enderror
                    <div class="grading-client-error" data-grading-client-error role="alert" hidden></div>
                    <div class="grading-overall-feedback"><label for="grading-feedback">Nhận xét chung <span aria-hidden="true">*</span></label><textarea id="grading-feedback" name="feedback" maxlength="20000" rows="3" required aria-invalid="{{ $errors->has('feedback') ? 'true' : 'false' }}" placeholder="Tóm tắt đánh giá để công bố kết quả...">{{ $keepInput ? old('feedback') : data_get($answer->grade, 'feedback') }}</textarea>@error('feedback')<span class="grading-field-error" role="alert">{{ $message }}</span>@enderror</div>
                    @if($answer->grade)<div class="grading-reason"><label for="grading-reason">Lý do điều chỉnh <span aria-hidden="true">*</span></label><textarea id="grading-reason" name="reason" maxlength="2000" rows="2" required aria-invalid="{{ $errors->has('reason') ? 'true' : 'false' }}" placeholder="Vì sao cần chấm lại bài này?">{{ $keepInput ? old('reason') : '' }}</textarea>@error('reason')<span class="grading-field-error" role="alert">{{ $message }}</span>@enderror</div>@endif
                    <div class="grading-section-head grading-feedback-head"><h3>Nhận xét chi tiết</h3><span>Không bắt buộc · tối đa 2.000 ký tự mỗi mục</span></div>
                    <div class="grading-criterion-list">
                        @foreach($criteria as $index => $criterion)
                            <div class="grading-criterion grading-tone-{{ $index + 1 }}">
                                <div class="grading-criterion-label"><strong data-grading-criterion-score="{{ $criterion }}">{{ $scores[$criterion] === null || $scores[$criterion] === '' ? '—' : number_format((float) $scores[$criterion], 1) }}</strong><span>{{ $labels[$criterion] ?? \Illuminate\Support\Str::headline($criterion) }}</span></div>
                                <div class="grading-criterion-body">
                                    @if(data_get($item, 'metadata.rubric.'.$criterion))<p class="grading-rubric">{{ data_get($item, 'metadata.rubric.'.$criterion) }}</p>@endif
                                    <label for="grading-comment-{{ $criterion }}">Nhận xét tiêu chí này</label>
                                    <textarea id="grading-comment-{{ $criterion }}" name="criterion_feedback[{{ $criterion }}]" maxlength="2000" rows="2" aria-invalid="{{ $errors->has('criterion_feedback.'.$criterion) ? 'true' : 'false' }}" placeholder="Ghi nhận xét cụ thể cho học viên...">{{ $keepInput ? old('criterion_feedback.'.$criterion) : data_get($answer->grade, 'criterion_feedback.'.$criterion) }}</textarea>
                                    @error('criterion_feedback.'.$criterion)<span class="grading-field-error" role="alert">{{ $message }}</span>@enderror
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('criterion_feedback')<p class="grading-field-error" role="alert">{{ $message }}</p>@enderror
                    <div class="grading-improvements"><label for="grading-improvements">Gợi ý cải thiện</label><textarea id="grading-improvements" name="improvements" maxlength="10000" rows="3" placeholder="Các bước cụ thể để học viên cải thiện bài làm...">{{ $keepInput ? old('improvements') : data_get($answer->grade, 'improvements') }}</textarea>@error('improvements')<span class="grading-field-error" role="alert">{{ $message }}</span>@enderror</div>
                    <div class="grading-form-footer"><span>{{ $answer->grade ? 'Người chấm gần nhất: #'.($answer->grade['reviewer_id'] ?? '—').' · '.($answer->grade['graded_at'] ?? '—') : 'Điểm sẽ được công bố khi lưu.' }}</span><button class="button button-primary" type="submit">{{ $answer->grade ? 'Lưu điểm điều chỉnh' : 'Lưu kết quả' }}</button></div>
                </form>
                <div class="grading-ai-panel">
                    <form method="post" action="{{ route('admin.grading.ai', $answer) }}" data-grading-ai-form data-pending-label="Đang yêu cầu…">@csrf<button class="button button-quiet" type="submit">Yêu cầu gợi ý AI</button></form>
                    <span>Người chấm kiểm tra gợi ý trước khi công bố.</span>
                    @foreach($suggestions->get($answer->id, collect()) as $suggestion)<details><summary>Gợi ý AI #{{ $suggestion->id }} · {{ $suggestion->status }}</summary><pre>{{ $suggestion->result ?? $suggestion->error ?? 'Đang xử lý' }}</pre></details>@endforeach
                </div>
            </section>
        </div>
    @else
        <div class="panel grading-empty">Chưa có bài Writing hoặc Speaking đã nộp để chấm. <a href="{{ route('admin.attempts.index') }}">Xem lượt thi</a></div>
    @endif
</div>

<style>
    .grading-page{display:grid;gap:14px;min-width:0;color:var(--ink)}
    .grading-breadcrumbs{display:flex;flex-wrap:wrap;align-items:center;gap:8px;color:var(--muted);font-size:11px}.grading-breadcrumbs a{color:#49668f}.grading-breadcrumbs a:hover{text-decoration:underline;color:var(--blue)}.grading-breadcrumbs [aria-current="page"]{color:var(--ink);font-weight:700}
    .grading-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap}.grading-heading h1{margin:0 0 3px;font-size:25px}.grading-heading p{margin:0;font-size:12px}
    .grading-toolbar{display:flex;align-items:center;gap:9px}.grading-toolbar strong{min-width:75px;text-align:center;font-size:12px}.grading-page-button{width:38px;padding:0;font-size:22px;line-height:1}.grading-page-button.is-disabled{opacity:.45;pointer-events:none}.grading-save-top{margin-left:9px}
    .grading-columns{display:grid;grid-template-columns:minmax(0,.94fr) minmax(0,1.06fr);align-items:start;gap:16px}.admin-page .panel.grading-submission,.admin-page .panel.grading-results{min-width:0;margin:0;padding:18px;border-radius:13px;box-shadow:0 7px 22px rgba(35,71,126,.045)}
    .grading-person{display:flex;align-items:center;gap:11px;margin-bottom:17px}.grading-avatar{display:grid;place-items:center;width:42px;height:42px;flex:none;border-radius:50%;background:#dbe7ff;color:#255bd0;font-weight:800}.grading-person-main{min-width:0;flex:1}.grading-person-main h2{margin:0;font-size:15px}.grading-person-main h2 span{color:#48628d}.grading-person-main p{margin:2px 0 0;font-size:11px}.grading-attempt-link{display:inline-block;margin-top:3px;color:var(--blue);font-size:10px;font-weight:700}.grading-attempt-link:hover{text-decoration:underline}.grading-person-meta{display:grid;gap:3px;margin:0;color:#597093;font-size:10px}.grading-person-meta div{display:flex;gap:6px;justify-content:space-between}.grading-person-meta dt,.grading-person-meta dd{margin:0}.grading-person-meta dd{color:#3f5680;font-weight:600;white-space:nowrap}
    .grading-prompt{padding:14px 15px;border-radius:10px;background:#f0f5ff}.grading-section-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:9px}.grading-section-head h3{margin:0;font-size:15px}.grading-prompt-content{color:#334b74;line-height:1.75}.grading-prompt-content p:last-child{margin-bottom:0}
    .grading-answer{margin-top:18px}.grading-word-count{padding:4px 10px;border-radius:7px;background:#f0f3f9;color:#6c7e9e;font-size:10px}.grading-answer-content{padding:13px 14px;border:1px solid var(--line);border-radius:9px;color:#354b72;line-height:1.75}.grading-answer-content p{margin:0 0 12px;color:inherit}.grading-answer-content p:last-child{margin:0}.grading-answer-empty{padding:16px;border:1px dashed var(--line);border-radius:9px;color:var(--muted)}.grading-recording{display:grid;gap:8px;margin-top:14px}.grading-recording audio{width:100%;max-width:430px}.grading-recording strong{font-size:12px}
    .grading-results-heading{display:flex;align-items:start;justify-content:space-between;gap:8px;margin-bottom:8px}.grading-results-heading h2{margin:0;font-size:17px}.grading-results-heading p{margin:2px 0 0;font-size:11px}.grading-state{padding:4px 9px;border-radius:99px;background:#fff3dc;color:#925b05;font-size:10px;font-weight:700;white-space:nowrap}.grading-state.is-graded{background:#e4f8ef;color:#087553}.grading-state.is-unsaved{background:#fff3dc;color:#925b05}.grading-required-note{margin:0 0 13px;color:#52688d;font-size:11px}
    .grading-score-grid{display:grid;grid-template-columns:1.12fr repeat(4,minmax(0,1fr));gap:7px}.grading-band-card,.grading-score-card{min-width:0;min-height:120px;padding:12px 10px;border:1px solid var(--line);border-radius:9px}.grading-band-card{display:grid;align-content:space-between;text-align:center;background:#f0f5ff}.grading-band-card span{font-size:10px;font-weight:700}.grading-band-card strong{font-size:34px;line-height:1;color:#123c91}.grading-band-card small{font-size:9px;color:#4d719f}.grading-score-card{display:flex;flex-direction:column;gap:7px;justify-content:space-between}.grading-score-card>span:first-child{min-height:36px;color:#405375;font-size:10px;font-weight:600;line-height:1.35}.grading-score-card b{color:#b42318}.grading-score-card select{width:100%;min-height:34px;padding:5px;border:1px solid #d9e3f1;border-radius:6px;background:#fff;color:#143879;font-weight:800}.grading-score-track{display:block;height:5px;border-radius:99px;background:#e5eaf2;overflow:hidden}.grading-score-track i{display:block;height:100%;background:#2569f0}.grading-tone-2 .grading-score-track i{background:#10a16f}.grading-tone-3 .grading-score-track i{background:#efa700}.grading-tone-4 .grading-score-track i{background:#7443e3}
    .grading-feedback-head{margin:20px 0 10px}.grading-feedback-head span{color:var(--muted);font-size:10px}.grading-criterion-list{display:grid;gap:8px}.grading-criterion{display:grid;grid-template-columns:140px minmax(0,1fr);border:1px solid var(--line);border-radius:9px;overflow:hidden}.grading-criterion-label{display:flex;align-items:center;gap:9px;padding:10px;background:#eaf4ff;color:#153b87;font-size:10px;font-weight:700}.grading-criterion-label strong{display:grid;place-items:center;min-width:40px;height:36px;border-radius:6px;background:#c9e7ff;font-size:17px}.grading-tone-2 .grading-criterion-label{background:#e9fbf3;color:#096a52}.grading-tone-2 .grading-criterion-label strong{background:#bdf4dc}.grading-tone-3 .grading-criterion-label{background:#fff8e9;color:#8c5b00}.grading-tone-3 .grading-criterion-label strong{background:#ffecba}.grading-tone-4 .grading-criterion-label{background:#f4efff;color:#6335b8}.grading-tone-4 .grading-criterion-label strong{background:#e5d7ff}.grading-criterion-body{display:grid;gap:5px;padding:10px}.grading-criterion-body label{font-size:10px;font-weight:700;color:#4c6285}.grading-rubric{margin:0 0 3px;color:#52688d;font-size:11px;line-height:1.55}
    .grading-form textarea{width:100%;min-height:60px;padding:9px 10px;border:1px solid #d9e3f1;border-radius:7px;background:#fff;resize:vertical;color:var(--ink);font-size:11px;line-height:1.55}.grading-form textarea::placeholder{color:#9aabc3}.grading-improvements,.grading-overall-feedback,.grading-reason{display:grid;gap:6px;margin-top:13px}.grading-improvements label,.grading-overall-feedback label,.grading-reason label{font-size:12px;font-weight:700}.grading-overall-feedback label span,.grading-reason label span{color:#b42318}.grading-improvements{padding:12px;border:1px solid #f3dfae;border-radius:9px;background:#fffaed}.grading-form-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:15px}.grading-form-footer span{color:var(--muted);font-size:10px}
    .grading-ai-panel{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:15px;padding-top:14px;border-top:1px solid var(--line)}.grading-ai-panel>span{color:var(--muted);font-size:10px}.grading-ai-panel details{width:100%;padding:9px;border:1px solid var(--line);border-radius:7px}.grading-ai-panel pre{white-space:pre-wrap;overflow-wrap:anywhere}.grading-field-error,.grading-error-summary,.grading-client-error{color:#b42318;font-size:11px;font-weight:600}.grading-error-summary,.grading-client-error{padding:10px 12px;border:1px solid #f4c6c3;border-radius:8px;background:#fff2f1}.grading-client-error{margin-top:10px}.grading-client-error[hidden]{display:none}.grading-empty{padding:22px!important}.grading-empty a{color:var(--blue);font-weight:700}.grading-page :focus-visible{outline:3px solid rgba(11,102,239,.38);outline-offset:2px}
    @media(max-width:1200px){.grading-columns{grid-template-columns:1fr}.grading-score-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.grading-band-card{grid-column:1/-1;min-height:85px;display:flex;align-items:center;justify-content:space-between;padding:14px}.grading-band-card strong{font-size:29px}}
    @media(max-width:720px){.grading-heading h1{font-size:21px}.grading-toolbar{width:100%;flex-wrap:wrap}.grading-save-top{margin-left:auto}.grading-score-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.grading-band-card{grid-column:1/-1}.grading-criterion{grid-template-columns:1fr}.grading-person{flex-wrap:wrap}.grading-person-meta{width:100%;padding-left:53px}.grading-form-footer{align-items:stretch;flex-direction:column}.grading-form-footer .button{width:100%}}
    @media(prefers-reduced-motion:reduce){.grading-page .button{transition:none}}
</style>

<script>
    (() => {
        const root = document.querySelector('.grading-page');
        const form = root?.querySelector('[data-grading-form]');
        if (!form) return;
        const selects = [...form.querySelectorAll('select[name^="criteria["]')];
        const band = form.querySelector('[data-grading-band]');
        const state = root.querySelector('[data-grading-state]');
        const clientError = form.querySelector('[data-grading-client-error]');
        const markUnsaved = () => {
            state.textContent = 'Có thay đổi chưa lưu';
            state.classList.remove('is-graded');
            state.classList.add('is-unsaved');
            clientError.hidden = true;
        };
        form.addEventListener('input', markUnsaved);
        form.addEventListener('change', markUnsaved);
        form.addEventListener('invalid', event => {
            if (!clientError.hidden) return;
            const name = event.target.name ?? '';
            clientError.textContent = name.startsWith('criteria[')
                ? 'Chưa lưu: chọn điểm cho đủ 4 tiêu chí.'
                : name === 'feedback' ? 'Chưa lưu: nhập nhận xét chung.'
                : name === 'reason' ? 'Chưa lưu: nhập lý do điều chỉnh điểm.'
                : 'Chưa lưu: kiểm tra trường bắt buộc.';
            clientError.hidden = false;
        }, true);
        const update = () => {
            const values = selects.map(select => select.value === '' ? null : Number(select.value));
            band.textContent = values.every(value => value !== null) ? (Math.round(values.reduce((sum, value) => sum + value, 0) / 4 * 2) / 2).toFixed(1) : '—';
            selects.forEach((select, index) => {
                const criterion = select.name.slice(9, -1);
                const detail = form.querySelector('[data-grading-criterion-score="' + criterion + '"]');
                const track = select.closest('.grading-score-card')?.querySelector('.grading-score-track i');
                if (detail) detail.textContent = values[index] === null ? '—' : values[index].toFixed(1);
                if (track) track.style.width = values[index] === null ? '0%' : (values[index] / 9 * 100) + '%';
            });
        };
        selects.forEach(select => select.addEventListener('change', update));
        const disableOnSubmit = target => target.addEventListener('submit', () => {
            window.setTimeout(() => {
                target.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; button.setAttribute('aria-busy', 'true'); });
                if (target === form) {
                    const topButton = root.querySelector('.grading-save-top');
                    if (topButton) { topButton.disabled = true; topButton.textContent = 'Đang lưu điểm…'; topButton.setAttribute('aria-busy', 'true'); }
                }
            }, 0);
        });
        disableOnSubmit(form);
        root.querySelectorAll('[data-grading-ai-form]').forEach(disableOnSubmit);
    })();
</script>
