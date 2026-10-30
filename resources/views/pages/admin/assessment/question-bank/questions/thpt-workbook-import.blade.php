<section class="workbook-import thpt-workbook-import" data-workbook-import data-kind="thpt_english" data-preview-url="{{ route('admin.questions.import.preview') }}" data-confirm-url="{{ route('admin.questions.import.confirm') }}">
    <div class="workbook-import-panel">
        <div class="thpt-workbook-actions">
            <p class="form-help">Một file Excel gồm 6 sheet và 40 câu. Sau khi xem trước, chọn ảnh dùng chung cho văn bản Phần 1 và Phần 2.</p>
            <a class="button button-quiet" href="{{ route('admin.questions.import-template', ['template' => 'thpt-english-xlsx']) }}">Tải mẫu Excel 40 câu</a>
        </div>
        <form enctype="multipart/form-data" data-workbook-upload>
            @csrf
            <label class="admin-label">File đề thi .xlsx
                <input type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            </label>
            <button class="button button-primary" type="submit">Đọc và xem trước 6 phần</button>
        </form>
        <section data-workbook-preview hidden aria-live="polite">
            <div class="workbook-preview-summary" data-workbook-summary></div>
            <div class="workbook-sheet-list" data-workbook-sheets></div>
            <div class="workbook-audio-fields" data-workbook-audio></div>
            <div class="workbook-image-fields" data-workbook-images></div>
            <p class="admin-alert admin-alert-error" data-workbook-errors role="alert" hidden></p>
            <button class="button button-primary" type="button" data-workbook-confirm hidden>Xác nhận import</button>
            <button class="button button-quiet" type="button" data-workbook-reset>Tải workbook khác</button>
        </section>
    </div>
</section>
