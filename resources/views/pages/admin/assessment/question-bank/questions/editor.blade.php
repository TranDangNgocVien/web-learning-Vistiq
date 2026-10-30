@php
    $embedded = isset($embeddedStimulus);
    $isEditing = $question !== null;
    $canEdit = auth()->user()->hasPermission('content.edit');
    $selectedPartId = old('exam_part_id', $payload['exam_part_id'] ?? $question?->exam_part_id);
    $selectedPart = $parts->firstWhere('id', (int) $selectedPartId);
    $isThptEnglish = $selectedPart?->examType?->code === 'thpt' || request()->query('from') === 'thpt';
    $sourceBank = $isThptEnglish ? 'admin.questions.thpt' : match ($selectedPart?->skill?->code ?? $editorSkill ?? null) {
        'reading' => 'admin.readings.index', 'writing' => 'admin.questions.writing', 'speaking' => 'admin.questions.speaking', default => 'admin.questions.listening',
    };
    $sourceBankLabel = match ($sourceBank) {
        'admin.questions.thpt' => 'Câu hỏi Tiếng Anh THPT', 'admin.readings.index' => 'Câu hỏi Reading', 'admin.questions.writing' => 'Câu hỏi Writing', 'admin.questions.speaking' => 'Câu hỏi Speaking', default => 'Câu hỏi Listening',
    };
    $subjectiveSkill = in_array($editorSkill ?? null, ['writing', 'speaking'], true) ? $editorSkill : null;
    $selectedType = old('question_type_id', $questionTypes->firstWhere('code', $payload['type'] ?? 'multiple_choice')?->id);
    $metadata = old('metadata', $payload['metadata'] ?? []);
    $options = old('options', $payload['options'] ?? ['A' => '', 'B' => '', 'C' => '', 'D' => '']);
    if ($options === []) $options = ['A' => '', 'B' => '', 'C' => '', 'D' => ''];
    $correctOptions = old('correct_options', $payload['correct_options'] ?? []);
    $currentTags = old('tags', $tags);
    $status = $question?->contentStatus() === 'archived' ? 'archived' : ($revision?->status ?? $question?->contentStatus() ?? 'draft');
    $statusLabels = ['draft' => 'Bản nháp', 'review' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'published' => 'Đã xuất bản', 'archived' => 'Đã lưu trữ'];
    $difficultyLabels = ['easy' => 'Dễ', 'medium' => 'Trung bình', 'hard' => 'Khó'];
    $selectedKnowledge = \VistiqCore\Models\LearningItem::whereIn('id', $metadata['knowledge_ids'] ?? [])->get();
    $catalog = $parts->map(fn ($part) => ['id' => $part->id, 'exam' => $part->exam_type_id, 'name' => $part->name, 'types' => app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class)->allowedTypes($part->examType->code, $part->skill?->code ?? '')]);
@endphp
<div class="question-edit-page {{ $embedded ? 'qe-embedded' : '' }} {{ $isThptEnglish ? 'is-thpt-question' : '' }}" data-qe-root @if($embedded && !empty($payload['exam_part_id']))data-lock-part="1"@endif>
    <header class="qe-page-heading"><div>@if(!$embedded)<a class="qe-back" href="{{ route($sourceBank) }}">@include('pages.dictionary.icon', ['name' => 'arrow-left']) {{ $sourceBankLabel }}</a>@endif<h1>{{ $isEditing ? 'Chỉnh sửa câu hỏi' : 'Tạo câu hỏi' }}{{ $subjectiveSkill ? ' '.ucfirst($subjectiveSkill) : '' }}</h1></div>@if($isEditing || $embedded)<div class="qe-workflow" data-qe-workflow>@if($isEditing)@include('shared.workflow-actions', ['kind' => 'question', 'entityId' => $question->id, 'revision' => $revision])@endif</div>@endif</header>
    @if($isThptEnglish && !$embedded)<p class="qe-thpt-context">Tiếng Anh THPT · trắc nghiệm một đáp án · bốn lựa chọn A–D · câu điền và đọc hiểu cần liên kết cùng văn bản.</p>@endif
    <div class="qe-layout">
        <form id="question-form" class="qe-form" method="post" action="{{ $isEditing ? route('admin.questions.update', $question) : route('admin.questions.store') }}" data-qe-form data-preview-url="{{ route('admin.questions.preview') }}" data-catalog-url="{{ route('admin.questions.editor-catalog') }}" data-can-edit="{{ $canEdit ? '1' : '0' }}" @if($embedded)data-embedded="1"@endif @if($isEditing)data-history-url="{{ route('admin.questions.history', $question) }}" data-links-url="{{ route('admin.questions.exam-links', $question) }}" @endif>
            @csrf
            @if($embedded)<input type="hidden" name="editor_stimulus_id" value="{{ $embeddedStimulus->id }}">@endif
            @if($isEditing) @method('PUT') @endif
            @if($isThptEnglish)<input type="hidden" name="return_to" value="thpt">@endif
            <input type="hidden" name="revision_counter" value="{{ $question?->revision_counter ?? 0 }}">
            <script type="application/json" data-qe-catalog>{!! json_encode(['parts' => $catalog, 'families' => $editorTypeFamilies], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
            <fieldset @disabled(!$canEdit)>
                <h2>Thông tin chung</h2>
                <div class="qe-general-grid">
                    <label>ID câu hỏi<input data-qe-id value="{{ $isEditing ? 'Q-'.str_pad((string)$question->id, 5, '0', STR_PAD_LEFT) : 'Tạo khi lưu' }}" readonly></label>
                    <label>Kỳ thi <em>*</em><select data-qe-exam>@foreach($parts->pluck('examType')->unique('id') as $examType)<option value="{{ $examType->id }}">{{ $examType->name }}</option>@endforeach</select></label>
                    <label>Phần / Kỹ năng <em>*</em><select name="exam_part_id" data-qe-part required>@foreach($parts as $part)<option value="{{ $part->id }}" data-exam="{{ $part->exam_type_id }}" data-exam-code="{{ $part->examType->code }}" data-part-code="{{ $part->code }}" @selected((string)$selectedPartId === (string)$part->id)>{{ $part->examType->code === 'toeic' ? ucfirst(str_replace('-', ' ', $part->code)) : $part->name }}</option>@endforeach</select></label>
                    <label>Dạng câu hỏi <em>*</em><select name="question_type_id" data-qe-type required>@foreach($questionTypes as $type)<option value="{{ $type->id }}" data-code="{{ $type->code }}" @selected((string)$selectedType === (string)$type->id)>{{ $type->name }}</option>@endforeach</select></label>
                    <label>Mức độ <em>*</em><select name="question_difficulty_id" required>@foreach($difficulties as $difficulty)<option value="{{ $difficulty->id }}" @selected((string)old('question_difficulty_id', $payload['question_difficulty_id'] ?? $question?->question_difficulty_id ?? $difficulties->firstWhere('code', 'medium')?->id) === (string)$difficulty->id)>{{ $difficultyLabels[$difficulty->code] ?? $difficulty->name }}</option>@endforeach</select></label>
                    <label>Trạng thái<select disabled><option data-qe-current-status>{{ $statusLabels[$status] ?? $status }}</option></select></label>
                    <label>Người tạo<input value="{{ $editorCreator ?? 'Không xác định' }}" readonly></label>
                    <label>Cập nhật lần cuối<input data-qe-updated value="{{ ($revision?->created_at ?? $question?->updated_at)?->format('d/m/Y H:i') ?? 'Chưa lưu' }}" readonly></label>
                </div>
                <div class="qe-section-title"><label for="qe-prompt">Nội dung câu hỏi <em>*</em></label><span data-qe-count="prompt">0 / 10.000</span></div>
                <textarea id="qe-prompt" name="prompt" data-qe-rich rows="4" maxlength="10000">{{ old('prompt', $payload['prompt'] ?? '') }}</textarea>
                <section data-type-family="choice matching">
                    <div class="qe-section-title"><h3>Đáp án lựa chọn <em>*</em></h3></div>
                    <p class="qe-thpt-question-note" data-thpt-question-note hidden>Giữ đúng bốn lựa chọn A, B, C, D và chọn một đáp án đúng.</p>
                    <div class="qe-option-list" data-qe-options>
                        @foreach($options as $key => $value)<div class="qe-option-row {{ in_array((string)$key, $correctOptions, true) ? 'is-correct' : '' }}" data-qe-option="{{ $key }}"><span class="qe-option-key">{{ $key }}</span><input name="options_values[{{ $key }}]" value="{{ html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8') }}" data-qe-original-option="{{ $value }}" aria-label="Phương án {{ $key }}" maxlength="2000"><label class="qe-option-answer" data-choice-answer><input type="checkbox" name="correct_options[]" value="{{ $key }}" @checked(in_array((string)$key, $correctOptions, true)) aria-label="Đặt {{ $key }} là đáp án đúng"><span>Đáp án đúng</span></label><div class="qe-option-tools"><button type="button" data-qe-move="up" title="Di chuyển lên" aria-label="Di chuyển phương án {{ $key }} lên">↑</button><button type="button" data-qe-move="down" title="Di chuyển xuống" aria-label="Di chuyển phương án {{ $key }} xuống">↓</button><button type="button" data-qe-remove-option title="Xóa phương án" aria-label="Xóa phương án {{ $key }}">@include('pages.dictionary.icon', ['name' => 'close'])</button></div></div>@endforeach
                    </div>
                    <div class="qe-options-footer"><button class="button button-quiet" type="button" data-qe-add-option>@include('pages.dictionary.icon', ['name' => 'plus']) Thêm phương án</button><label data-qe-single-answer>Đáp án đúng <em>*</em><select data-qe-correct-select aria-label="Chọn đáp án đúng"></select></label></div>
                </section>
                <div class="qe-advanced-fields">@include('pages.admin.assessment.question-bank.questions.editor-advanced')</div>
                <div class="qe-section-title"><label for="qe-explanation">Giải thích đáp án</label><span data-qe-count="explanation">0 / 10.000</span></div>
                <textarea id="qe-explanation" name="explanation" data-qe-rich rows="4" maxlength="10000">{{ old('explanation', $payload['explanation'] ?? '') }}</textarea>
                <div class="qe-bottom-grid">
                    <section><h3>Kiến thức liên quan</h3><div class="qe-chips" data-qe-knowledge>@foreach($selectedKnowledge as $item)<span class="qe-chip" data-id="{{ $item->id }}"><input type="hidden" name="metadata[knowledge_ids][]" value="{{ $item->id }}"><span>{{ $item->canonical_text }}</span><button type="button" data-qe-remove-chip aria-label="Gỡ kiến thức {{ $item->canonical_text }}">×</button></span>@endforeach<button class="qe-chip-add" type="button" data-qe-picker="knowledge">+ Thêm kiến thức</button></div><h3 class="qe-tags-label">Thẻ / Tags</h3><input type="hidden" name="tags" value="{{ $currentTags }}" data-qe-tags-value><div class="qe-chips" data-qe-tags></div><button class="qe-chip-add" type="button" data-qe-picker="tag">+ Thêm thẻ</button></section>
                    <section><h3>Tài nguyên đính kèm</h3><div class="qe-resource-picks">
                        @foreach(['image' => ['image_id', 'Ảnh'], 'audio' => ['audio_id', 'Audio']] as $kind => [$field, $label])
                            @php($asset = $assets->firstWhere('id', (int)($metadata[$field] ?? 0)))
                            <div class="qe-resource" data-qe-resource="{{ $kind }}"><input type="hidden" name="metadata[{{ $field }}]" value="{{ $metadata[$field] ?? '' }}"><button type="button" data-qe-picker="{{ $kind }}">@include('pages.dictionary.icon', ['name' => $kind === 'audio' ? 'sound' : 'book']) <span data-qe-resource-label>{{ $asset?->title ?: 'Chọn '.$label }}</span></button><button type="button" data-qe-clear-resource aria-label="Gỡ {{ $label }}" @if(!$asset)hidden @endif>×</button></div>
                        @endforeach
                        @php($selectedStimulus = $stimuli->firstWhere('id', (int)old('learning_stimulus_id', $payload['stimulus_ids'][0] ?? $selectedStimulusId)))
                        <div class="qe-resource" data-qe-resource="stimulus"><input type="hidden" name="learning_stimulus_id" value="{{ $selectedStimulus?->id }}"><button type="button" data-qe-picker="stimulus">@include('pages.dictionary.icon', ['name' => 'link']) <span data-qe-resource-label>{{ $selectedStimulus?->title ?: 'Chọn bài đọc / bài nghe' }}</span></button><button type="button" data-qe-clear-resource aria-label="Gỡ bài đọc / bài nghe" @if(!$selectedStimulus)hidden @endif>×</button></div>
                    </div><p class="qe-help">Chọn tài nguyên đã có trong thư viện.</p><a class="qe-library-link" href="{{ route('admin.resources.index') }}" target="_blank" rel="noopener">Mở thư viện tài nguyên @include('pages.dictionary.icon', ['name' => 'external-link'])</a></section>
                </div>
                <details class="qe-extra"><summary>Nhóm câu hỏi và giới hạn trả lời</summary><div class="qe-extra-grid">
                    <label>Nhóm câu hỏi<select name="question_group_id"><option value="">Không nhóm</option>@foreach($groups as $group)<option value="{{ $group->id }}" data-part="{{ $group->exam_part_id }}" @selected((string)old('question_group_id', $payload['group_id'] ?? $question?->question_group_id) === (string)$group->id)>{{ $group->title }}</option>@endforeach</select></label>
                    <label>Thứ tự trong nhóm<input name="group_order" type="number" min="0" max="200" value="{{ old('group_order', $payload['group_order'] ?? $question?->group_order ?? 0) }}"></label>
                    <label>Variant IELTS<select name="metadata[variant]"><option value="">Không áp dụng</option><option value="academic" @selected(($metadata['variant'] ?? '') === 'academic')>Academic</option><option value="general" @selected(($metadata['variant'] ?? '') === 'general')>General Training</option></select></label>
                    <label data-type-family="choice text matching">Số từ tối đa<input type="number" min="0" max="100" name="metadata[max_words]" value="{{ $metadata['max_words'] ?? '' }}"></label>
                    <label data-type-family="choice text matching">Số tối đa<input type="number" min="0" max="20" name="metadata[max_numbers]" value="{{ $metadata['max_numbers'] ?? '' }}"></label>
                </div></details>
            </fieldset>
            <div class="qe-feedback" data-qe-feedback role="alert" hidden></div>
            <footer class="qe-save-bar">
                <span data-qe-save-status role="status">{{ $isEditing ? 'Đã tải bản hiện tại' : 'Chưa lưu' }}</span>
                @if(!$embedded)
                    <a class="button button-quiet" href="{{ route($sourceBank) }}">Quay lại</a>
                @endif
                @can('content.edit')
                    <button class="button button-primary" type="submit">{{ $embedded ? 'Lưu câu hỏi' : ($isEditing ? 'Lưu nháp' : 'Tạo câu hỏi') }}</button>
                    @if($embedded)
                        <button class="button button-quiet" type="submit" name="save_next" value="1">Lưu và thêm câu tiếp</button>
                    @endif
                @endcan
            </footer>
        </form>
        <aside class="qe-sidebar">
            <section class="qe-preview-card"><header><h2>Xem trước câu hỏi</h2><div><button type="button" data-qe-refresh @disabled(!$canEdit)>@include('pages.dictionary.icon', ['name' => 'repeat']) Làm mới</button><button type="button" data-qe-expand aria-label="Mở rộng xem trước">@include('pages.dictionary.icon', ['name' => 'external-link'])</button></div></header><div class="qe-preview-content" data-qe-preview>@include('pages.admin.assessment.question-bank.questions.editor-preview', $editorPreview)</div><details class="qe-preview-warnings" data-qe-warnings hidden><summary>Cần hoàn thiện trước khi gửi duyệt</summary><ul></ul></details></section>
            <section class="qe-side-section"><header><h3>Liên kết đề thi</h3>@if($isEditing && $canEdit)<button type="button" data-qe-picker="exam">@include('pages.dictionary.icon', ['name' => 'link']) Quản lý</button>@endif</header><div data-qe-links></div>@if(!$isEditing)<p class="qe-help">Lưu câu hỏi để liên kết với đề thi.</p>@endif</section>
            <section class="qe-side-section"><header><h3>Lịch sử chỉnh sửa</h3><button type="button" data-qe-more-history @if(!$editorHistory['next_page'])hidden @endif>Xem thêm</button></header><div data-qe-history></div></section>
            <script type="application/json" data-qe-initial>{!! json_encode(['history' => $editorHistory, 'links' => $editorExamLinks], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
        </aside>
    </div>
    <dialog class="qe-picker" data-qe-dialog aria-labelledby="qe-picker-title"><header><h2 id="qe-picker-title">Chọn dữ liệu</h2><button type="button" data-qe-dialog-close aria-label="Đóng">×</button></header><input data-qe-picker-search type="search" placeholder="Tìm kiếm…" aria-label="Tìm trong danh sách"><div class="qe-picker-results" data-qe-picker-results></div><p class="qe-help" data-qe-picker-feedback role="status"></p><div data-qe-new-tag hidden><input data-qe-tag-input placeholder="Tên thẻ mới" aria-label="Tên thẻ mới" maxlength="80"><button class="button button-primary" type="button" data-qe-add-tag>Thêm thẻ</button></div></dialog>
</div>
