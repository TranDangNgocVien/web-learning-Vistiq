@php($subjectiveWorkbook = in_array($workbookKind, ['writing','speaking'], true))
<details class="workbook-import" data-workbook-import data-kind="{{ $workbookKind }}" data-preview-url="{{ route('admin.questions.import.preview') }}" data-audio-url="{{ route('admin.questions.import.audio') }}" data-discard-audio-url="{{ route('admin.questions.import.discard-audio') }}" data-confirm-url="{{ route('admin.questions.import.confirm') }}">
    <summary class="button button-quiet">Import {{ ucfirst($workbookKind) }} từ workbook .xlsx</summary>
    <div class="panel workbook-import-panel">
        @if($subjectiveWorkbook)
            <p class="form-help">Nhận file .xlsx tối đa 20 MB, 50 sheet và 2.000 câu hỏi. Mỗi dòng là một câu hỏi; sheet HUONG_DAN trong file mẫu mô tả từng cột. Nhập đủ nội dung và bốn tiêu chí chấm. Câu hỏi được lưu bản nháp sau khi toàn bộ workbook hợp lệ.</p>
        @else
        <p class="form-help">Workbook có sheet <code>HUONG_DAN</code> mô tả cách điền cột; sheet này tự được bỏ qua. Mỗi câu khai báo <code>exam_type</code>, <code>skill</code>, <code>exam_part</code>, <code>question_type</code> và <code>difficulty</code>; các cột có danh sách chọn trong Excel. Mỗi sheet dữ liệu là một bài.@if($workbookKind === 'listening') Tên sheet chỉ để đối chiếu; mã bài được tự tạo theo giờ Việt Nam, ví dụ <code>LISTENING_YYYYMMDD_HHMMSS_01</code>. Với IELTS, có thể dùng <code>listening</code> hoặc <code>part-1</code> đến <code>part-4</code>.@else Tên sheet là mã bài.@endif</p>
        @endif
        @if($workbookKind === 'reading')
            <p class="form-help">Xem và bổ sung tiêu đề, đoạn văn cho từng bài ngay ở bước xem trước workbook.</p>
        @endif
        <a class="button button-quiet" href="{{ route('admin.questions.import-template', ['template' => $workbookKind.'-xlsx']) }}">Tải mẫu .xlsx</a>
        <form enctype="multipart/form-data" data-workbook-upload>
            @csrf
            <label class="admin-label">Workbook .xlsx
                <input type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            </label>
            <button class="button button-primary" type="submit">Xem trước workbook</button>
        </form>
        <section data-workbook-preview hidden aria-live="polite">
            <div class="workbook-preview-summary" data-workbook-summary></div>
            <div class="workbook-progress" data-workbook-progress hidden role="status" aria-live="polite">
                <div class="workbook-progress-heading"><b data-workbook-progress-label>Đang tải lên…</b><span data-workbook-progress-value>0%</span></div>
                <progress data-workbook-progress-bar max="100" value="0" aria-label="Tiến trình tải file import"></progress>
                <small data-workbook-progress-message></small>
            </div>
            <div class="workbook-audio-fields" data-workbook-audio></div>
            <div class="workbook-sheet-list" data-workbook-sheets></div>
            <div class="workbook-image-fields" data-workbook-images></div>
            <p class="admin-alert admin-alert-error" data-workbook-errors role="alert" hidden></p>
            <button class="button button-primary" type="button" data-workbook-confirm hidden>Xác nhận import</button>
            <button class="button button-quiet" type="button" data-workbook-reset>Tải workbook khác</button>
        </section>
    </div>
</details>
