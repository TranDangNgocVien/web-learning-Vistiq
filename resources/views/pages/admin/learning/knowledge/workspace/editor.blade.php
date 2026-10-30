@php
    $typeLabels = ['vocabulary' => 'Từ vựng', 'grammar' => 'Ngữ pháp', 'collocation' => 'Collocation', 'phrasal_verb' => 'Cụm động từ', 'idiom' => 'Thành ngữ'];
    $examples = old('examples', $payload['examples'] ?? []);
    $relations = old('relations', $payload['relations'] ?? []);
    $grammarRules = old('grammar_rules', $payload['grammar_rules'] ?? []);
    $selectedDictionaryEntries = array_map('strval', old('dictionary_entry_ids', $payload['dictionary_entry_ids'] ?? []));
    $typeTitleLabels = ['vocabulary' => 'Từ tiếng Anh', 'grammar' => 'Chủ điểm / cấu trúc', 'collocation' => 'Cụm từ', 'phrasal_verb' => 'Phrasal verb', 'idiom' => 'Thành ngữ'];
@endphp

@if($item->exists)
    @include('shared.workflow-actions', ['kind' => 'knowledge', 'entityId' => $item->id, 'revision' => $workflowRevision])
@endif

<section class="knowledge-editor-page">
    <header class="knowledge-editor-header">
        <a class="knowledge-back-link" href="{{ route('admin.knowledge.index', ['type' => $selectedType]) }}">← Quay lại {{ $typeLabels[$selectedType] ?? 'Kho kiến thức' }}</a>
        <div class="knowledge-editor-title">
            <div>
                <span class="knowledge-type-pill">{{ $typeLabels[$selectedType] ?? 'Kiến thức' }}</span>
                <h1>{{ $item->exists ? 'Chỉnh sửa kiến thức' : 'Thêm kiến thức' }}</h1>
            </div>
            @if($item->exists)<span class="knowledge-status knowledge-status-{{ $item->status }}">{{ ['draft' => 'Bản nháp', 'review' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'published' => 'Đã xuất bản', 'archived' => 'Đã lưu trữ'][$item->status] ?? 'Đang cập nhật' }}</span>@endif
        </div>
    </header>


    <a class="button button-quiet" href="#knowledge-preview">Xem trước bản nháp</a>
    <div class="workspace-with-preview">
    <form class="knowledge-editor" method="post" action="{{ $item->exists ? route('admin.knowledge.update', $item) : route('admin.knowledge.store') }}" data-unsaved-form data-pending-label="Đang lưu…">
        @csrf
        @if($item->exists)@method('PUT')@endif
        <input type="hidden" name="type" value="{{ $selectedType }}">
        <input type="hidden" name="revision_counter" value="{{ old('revision_counter', $item->revision_counter ?? 0) }}">
        <input type="hidden" name="examples_present" value="1">
        <input type="hidden" name="relations_present" value="1">
        @if($selectedType === 'collocation')<input type="hidden" name="dictionary_entry_ids_present" value="1">@endif

        <section class="knowledge-editor-section">
            <div class="knowledge-section-heading"><div><h2>Thông tin chính</h2><p>Nội dung hiển thị trong kho kiến thức.</p></div></div>
            <div class="knowledge-fields">
                <label class="knowledge-field">
                    <span>{{ $typeTitleLabels[$selectedType] ?? 'Nội dung tiếng Anh' }} <b>*</b></span>
                    <input name="english" required maxlength="255" value="{{ old('english', $payload['english'] ?? '') }}" placeholder="Nhập nội dung tiếng Anh">
                    @error('english')<small class="knowledge-field-error">{{ $message }}</small>@enderror
                </label>

                @if($selectedType !== 'grammar')
                    <label class="knowledge-field"><span>Nghĩa tiếng Việt <b>*</b></span><input name="meaning_vi" required value="{{ old('meaning_vi', $payload['meaning_vi'] ?? '') }}" placeholder="Nhập nghĩa tiếng Việt">@error('meaning_vi')<small class="knowledge-field-error">{{ $message }}</small>@enderror</label>
                    <label class="knowledge-field knowledge-field-wide"><span>Định nghĩa / giải thích tiếng Anh</span><textarea name="definition_en" rows="3" placeholder="Giải thích ngắn gọn">{{ old('definition_en', $payload['definition_en'] ?? '') }}</textarea></label>
                @else
                    <label class="knowledge-field"><span>Nghĩa tiếng Việt</span><textarea name="meaning_vi" rows="3" placeholder="Tên gọi hoặc diễn giải tiếng Việt">{{ old('meaning_vi', $payload['meaning_vi'] ?? '') }}</textarea></label>
                    <label class="knowledge-field"><span>Tóm tắt tiếng Việt</span><textarea name="summary" rows="3" placeholder="Mục tiêu hoặc nội dung chính của chủ điểm">{{ old('summary', $payload['summary'] ?? '') }}</textarea></label>
                    <label class="knowledge-field"><span>Nhóm ngữ pháp <b>*</b></span><select name="grammar_category_id" required><option value="">Chọn nhóm</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('grammar_category_id', $payload['grammar_category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select>@error('grammar_category_id')<small class="knowledge-field-error">{{ $message }}</small>@enderror</label>
                    <label class="knowledge-field knowledge-field-wide"><span>Nội dung bài học</span><textarea name="content" rows="10" data-rich-editor placeholder="Soạn nội dung ngữ pháp">{{ old('content', $payload['content'] ?? '') }}</textarea></label>
                @endif

                @if(in_array($selectedType, ['vocabulary', 'collocation'], true))
                    <label class="knowledge-field"><span>Từ loại</span><select name="part_of_speech_id"><option value="">Chưa chọn</option>@foreach($partsOfSpeech as $partOfSpeech)<option value="{{ $partOfSpeech->id }}" @selected((string) old('part_of_speech_id', $payload['part_of_speech_id'] ?? '') === (string) $partOfSpeech->id)>{{ $partOfSpeech->label }}</option>@endforeach</select></label>
                @endif

                <label class="knowledge-field"><span>Trình độ</span><select name="proficiency_level_id"><option value="">Chưa chọn</option>@foreach($levels as $level)<option value="{{ $level->id }}" @selected((string) old('proficiency_level_id', $payload['proficiency_level_id'] ?? '') === (string) $level->id)>{{ $level->label }}</option>@endforeach</select></label>
                <label class="knowledge-field"><span>Nhãn</span><input name="tags" value="{{ old('tags', $payload['tags'] ?? '') }}" placeholder="Phân cách bằng dấu phẩy"></label>
            </div>
        </section>

        @if($selectedType === 'vocabulary')
            <section class="knowledge-editor-section">
                <div class="knowledge-section-heading"><div><h2>Phát âm</h2><p>Thông tin giúp người học nhận biết và luyện cách đọc.</p></div></div>
                <div class="knowledge-fields"><label class="knowledge-field"><span>Phiên âm IPA</span><input name="ipa" value="{{ old('ipa', $payload['ipa'] ?? '') }}" placeholder="/ˈwɜːd/"></label></div>
            </section>
        @elseif($selectedType === 'collocation')
            <section class="knowledge-editor-section">
                <div class="knowledge-section-heading"><div><h2>Cách kết hợp từ</h2><p>Chọn các từ liên quan để người học xem nghĩa ngay trong ngữ cảnh.</p></div></div>
                <div class="knowledge-fields">
                    <label class="knowledge-field"><span>Mẫu kết hợp</span><input name="grammatical_pattern" value="{{ old('grammatical_pattern', $payload['grammatical_pattern'] ?? '') }}" placeholder="verb + noun"></label>
                    <label class="knowledge-field"><span>Các từ trong collocation</span><select name="dictionary_entry_ids[]" multiple size="5">@foreach($dictionaryEntries as $entry)<option value="{{ $entry->id }}" @selected(in_array((string) $entry->id, $selectedDictionaryEntries, true))>{{ $entry->canonical_text }}@if($entry->translation) · {{ $entry->translation }}@endif</option>@endforeach</select><small>Giữ Ctrl hoặc Command để chọn nhiều từ.</small></label>
                </div>
            </section>
        @elseif($selectedType === 'phrasal_verb')
            <section class="knowledge-editor-section">
                <div class="knowledge-section-heading"><div><h2>Cấu tạo cụm động từ</h2><p>Chọn động từ gốc và nhập tiểu từ.</p></div></div>
                <div class="knowledge-fields">
                    <label class="knowledge-field"><span>Động từ gốc <b>*</b></span><select name="base_dictionary_entry_id" required><option value="">Chọn từ</option>@foreach($dictionaryEntries as $entry)<option value="{{ $entry->id }}" @selected((string) old('base_dictionary_entry_id', $payload['base_dictionary_entry_id'] ?? '') === (string) $entry->id)>{{ $entry->canonical_text }}@if($entry->translation) · {{ $entry->translation }}@endif</option>@endforeach</select></label>
                    <label class="knowledge-field"><span>Tiểu từ <b>*</b></span><input name="particle" required value="{{ old('particle', $payload['particle'] ?? '') }}" placeholder="up, out, on..."></label>
                </div>
            </section>
        @elseif($selectedType === 'idiom')
            <section class="knowledge-editor-section">
                <div class="knowledge-section-heading"><div><h2>Cách dùng thành ngữ</h2><p>Ghi chú sắc thái và ngữ cảnh sử dụng.</p></div></div>
                <div class="knowledge-fields">
                    <label class="knowledge-field"><span>Sắc thái</span><select name="register"><option value="">Chưa chọn</option><option value="formal" @selected(old('register', $payload['register'] ?? '') === 'formal')>Trang trọng</option><option value="informal" @selected(old('register', $payload['register'] ?? '') === 'informal')>Thân mật</option><option value="neutral" @selected(old('register', $payload['register'] ?? '') === 'neutral')>Trung tính</option></select></label>
                    <label class="knowledge-field"><span>Ghi chú sử dụng</span><textarea name="usage_note" rows="3" placeholder="Khi nào nên dùng thành ngữ này?">{{ old('usage_note', $payload['usage_note'] ?? '') }}</textarea></label>
                </div>
            </section>
        @elseif($selectedType === 'grammar')
            <section class="knowledge-editor-section">
                <div class="knowledge-section-heading"><div><h2>Quy tắc ngữ pháp</h2><p>Thêm cấu trúc và giải thích cho từng quy tắc.</p></div><button class="button button-quiet" type="button" data-knowledge-add="grammar_rules">+ Thêm quy tắc</button></div>
                <div class="knowledge-repeat-list" data-knowledge-list="grammar_rules" data-next-index="{{ count($grammarRules) }}">
                    @foreach($grammarRules as $index => $rule)
                        <div class="knowledge-repeat-row" data-knowledge-row>
                            <label class="knowledge-field"><span>Tên quy tắc</span><input name="grammar_rules[{{ $index }}][title]" value="{{ $rule['title'] ?? '' }}" placeholder="Ví dụ: Câu khẳng định"></label>
                            <label class="knowledge-field"><span>Cấu trúc</span><input name="grammar_rules[{{ $index }}][formula]" value="{{ $rule['formula'] ?? '' }}" placeholder="S + have/has + V3"></label>
                            <label class="knowledge-field knowledge-field-wide"><span>Giải thích</span><textarea name="grammar_rules[{{ $index }}][explanation]" rows="3" placeholder="Giải thích cách dùng">{{ $rule['explanation'] ?? '' }}</textarea></label>
                            <button class="knowledge-remove-row" type="button" data-knowledge-remove aria-label="Xóa quy tắc">Xóa quy tắc</button>
                        </div>
                    @endforeach
                </div>
                <template data-knowledge-template="grammar_rules"><div class="knowledge-repeat-row" data-knowledge-row><label class="knowledge-field"><span>Tên quy tắc</span><input name="grammar_rules[__INDEX__][title]" placeholder="Ví dụ: Câu khẳng định"></label><label class="knowledge-field"><span>Cấu trúc</span><input name="grammar_rules[__INDEX__][formula]" placeholder="S + have/has + V3"></label><label class="knowledge-field knowledge-field-wide"><span>Giải thích</span><textarea name="grammar_rules[__INDEX__][explanation]" rows="3" placeholder="Giải thích cách dùng"></textarea></label><button class="knowledge-remove-row" type="button" data-knowledge-remove aria-label="Xóa quy tắc">Xóa quy tắc</button></div></template>
            </section>
        @endif

        <section class="knowledge-editor-section">
            <div class="knowledge-section-heading"><div><h2>Ví dụ</h2><p>Thêm câu ví dụ tiếng Anh và bản dịch nếu có.</p></div><button class="button button-quiet" type="button" data-knowledge-add="examples">+ Thêm ví dụ</button></div>
            <div class="knowledge-repeat-list" data-knowledge-list="examples" data-next-index="{{ count($examples) }}">
                @foreach($examples as $index => $example)
                    <div class="knowledge-repeat-row" data-knowledge-row>
                        <label class="knowledge-field"><span>Câu tiếng Anh</span><input name="examples[{{ $index }}][english]" value="{{ $example['english'] ?? '' }}" placeholder="Write an example sentence"></label>
                        <label class="knowledge-field"><span>Dịch tiếng Việt</span><input name="examples[{{ $index }}][vietnamese]" value="{{ $example['vietnamese'] ?? '' }}" placeholder="Nhập bản dịch"></label>
                        <button class="knowledge-remove-row" type="button" data-knowledge-remove aria-label="Xóa ví dụ">Xóa ví dụ</button>
                    </div>
                @endforeach
            </div>
            <template data-knowledge-template="examples"><div class="knowledge-repeat-row" data-knowledge-row><label class="knowledge-field"><span>Câu tiếng Anh</span><input name="examples[__INDEX__][english]" placeholder="Write an example sentence"></label><label class="knowledge-field"><span>Dịch tiếng Việt</span><input name="examples[__INDEX__][vietnamese]" placeholder="Nhập bản dịch"></label><button class="knowledge-remove-row" type="button" data-knowledge-remove aria-label="Xóa ví dụ">Xóa ví dụ</button></div></template>
        </section>

        <section class="knowledge-editor-section">
            <div class="knowledge-section-heading"><div><h2>Liên kết kiến thức</h2><p>Kết nối mục này với từ hoặc chủ điểm liên quan.</p></div><button class="button button-quiet" type="button" data-knowledge-add="relations">+ Thêm liên kết</button></div>
            <div class="knowledge-repeat-list" data-knowledge-list="relations" data-next-index="{{ count($relations) }}">
                @foreach($relations as $index => $relation)
                    <div class="knowledge-repeat-row knowledge-relation-row" data-knowledge-row>
                        <label class="knowledge-field"><span>Kiến thức liên quan</span><select name="relations[{{ $index }}][id]"><option value="">Chọn nội dung</option>@foreach($relatedItems as $relatedItem)<option value="{{ $relatedItem->id }}" @selected((string) ($relation['id'] ?? '') === (string) $relatedItem->id)>{{ $relatedItem->canonical_text }}</option>@endforeach</select></label>
                        <label class="knowledge-field"><span>Mối quan hệ</span><select name="relations[{{ $index }}][type]"><option value="related" @selected(($relation['type'] ?? 'related') === 'related')>Liên quan</option><option value="synonym" @selected(($relation['type'] ?? '') === 'synonym')>Đồng nghĩa</option><option value="antonym" @selected(($relation['type'] ?? '') === 'antonym')>Trái nghĩa</option><option value="derived" @selected(($relation['type'] ?? '') === 'derived')>Từ phái sinh</option></select></label>
                        <button class="knowledge-remove-row" type="button" data-knowledge-remove aria-label="Xóa liên kết">Xóa liên kết</button>
                    </div>
                @endforeach
            </div>
            <template data-knowledge-template="relations"><div class="knowledge-repeat-row knowledge-relation-row" data-knowledge-row><label class="knowledge-field"><span>Kiến thức liên quan</span><select name="relations[__INDEX__][id]"><option value="">Chọn nội dung</option>@foreach($relatedItems as $relatedItem)<option value="{{ $relatedItem->id }}">{{ $relatedItem->canonical_text }}</option>@endforeach</select></label><label class="knowledge-field"><span>Mối quan hệ</span><select name="relations[__INDEX__][type]"><option value="related">Liên quan</option><option value="synonym">Đồng nghĩa</option><option value="antonym">Trái nghĩa</option><option value="derived">Từ phái sinh</option></select></label><button class="knowledge-remove-row" type="button" data-knowledge-remove aria-label="Xóa liên kết">Xóa liên kết</button></div></template>
        </section>

        <footer class="knowledge-editor-actions"><a class="button button-quiet" href="{{ route('admin.knowledge.index', ['type' => $selectedType]) }}">Hủy</a>@can('content.edit')<button class="button button-primary" type="submit">{{ $item->exists ? 'Lưu thay đổi' : 'Tạo bản nháp' }}</button>@endcan</footer>
    </form>
    @include('pages.admin.learning.knowledge.workspace.preview')
    </div>
</section>
