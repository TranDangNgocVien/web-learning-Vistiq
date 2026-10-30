@php
    $typeLabels = [
        'vocabulary' => 'Từ vựng',
        'grammar' => 'Ngữ pháp',
        'collocation' => 'Collocation',
        'phrasal_verb' => 'Cụm động từ',
        'idiom' => 'Thành ngữ',
    ];
@endphp

<section class="knowledge-editor-page">
    <header class="knowledge-editor-header">
        <a class="knowledge-back-link" href="{{ route('admin.knowledge.index', array_filter(['type' => $type])) }}">← Quay lại {{ $typeLabels[$type] ?? 'Kho kiến thức' }}</a>
        <div class="knowledge-editor-title">
            <h1>Import kiến thức</h1>
        </div>
    </header>


    <form class="knowledge-editor" method="post" action="{{ route('admin.knowledge.import.preview') }}" enctype="multipart/form-data" data-pending-label="Đang kiểm tra…">
        @csrf

        <section class="knowledge-editor-section">
            <div class="knowledge-section-heading">
                <div>
                    <h2>Tải lên tệp CSV</h2>
                    <p>Tệp phải có cột <code>english</code> (bắt buộc). Các cột tùy chọn: <code>meaning_vi</code>, <code>definition_en</code>, <code>ipa</code>, <code>tags</code>, <code>summary</code>.</p>
                </div>
            </div>
            <div class="knowledge-fields">
                <label class="knowledge-field">
                    <span>Loại kiến thức <b>*</b></span>
                    <select name="type" required>
                        @foreach($typeLabels as $code => $label)
                            <option value="{{ $code }}" @selected(old('type', $type) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<small class="knowledge-field-error">{{ $message }}</small>@enderror
                </label>

                <label class="knowledge-field">
                    <span>Tệp CSV <b>*</b></span>
                    <input type="file" name="file" accept=".csv,.tsv,.txt" required>
                    <small>CSV/TSV UTF-8 · Tối đa 5 MB và 1.000 dòng. meaning_vi bắt buộc trừ Grammar; Grammar cần grammar_category_id; Phrasal Verb cần base_dictionary_entry_id và particle.</small>
                    @error('file')<small class="knowledge-field-error">{{ $message }}</small>@enderror
                </label>

                <label class="knowledge-field">
                    <span>Xử lý trùng lặp</span>
                    <select name="duplicate_policy">
                        <option value="skip" @selected(old('duplicate_policy', 'skip') === 'skip')>Bỏ qua mục đã tồn tại</option>
                        <option value="overwrite" @selected(old('duplicate_policy') === 'overwrite')>Ghi đè mục đã tồn tại</option>
                    </select>
                </label>
            </div>
        </section>

        <footer class="knowledge-editor-actions">
            <a class="button button-quiet" href="{{ route('admin.knowledge.index', array_filter(['type' => $type])) }}">Hủy</a>
            <button class="button button-primary" type="submit">Xem trước dữ liệu</button>
        </footer>
    </form>
</section>
