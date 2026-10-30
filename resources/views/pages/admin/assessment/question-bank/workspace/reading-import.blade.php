<details class="csv-import" @if(old('return_to') === 'readings' && $errors->any())open @endif>
    <summary class="button button-quiet">Import bài đọc CSV</summary>
    <form class="panel csv-import-form" method="post" action="{{ route('admin.questions.import') }}" enctype="multipart/form-data" data-question-import>
        @csrf
        <input type="hidden" name="import_key" value=""><input type="hidden" name="offset" value="0">
        <input type="hidden" name="return_to" value="readings"><input type="hidden" name="default_status" value="draft">
        <input type="hidden" name="default_question_type" value="{{ $importQuestionTypeId }}"><input type="hidden" name="default_difficulty" value="{{ $importDifficultyId }}">
        <p class="form-help">Mỗi dòng là một câu hỏi. Dùng cùng <code>reading_key</code> để gom vào một bài; điền <code>reading_title</code>, <code>reading_body</code> và <code>exam_part</code>. TOEIC <code>part-6</code> tự tạo Paragraph.</p>
        <label class="admin-label" for="reading-csv-file">File CSV<input class="admin-input" id="reading-csv-file" type="file" name="file" accept=".csv,.txt,text/csv" required></label>
        <div class="csv-import-actions"><a class="text-link" href="{{ route('admin.questions.import-template',['template'=>'guide']) }}">Hướng dẫn CSV</a><button class="button button-primary" type="submit">Import bài và câu hỏi</button></div>
        <p class="form-help">Bài và câu hỏi được lưu thành bản nháp. Bỏ trống loại câu hoặc độ khó sẽ dùng trắc nghiệm / trung bình. Tối đa 2.000 câu, 10 MB. Mỗi lần import tạo bài mới; kiểm tra kết quả trước khi nhập lại.</p>
        <div class="import-progress" data-import-progress hidden aria-live="polite"><div class="import-progress-heading"><b data-import-progress-label>Đang tải file…</b><span data-import-progress-count>0%</span></div><progress max="100" value="0" data-import-progress-bar aria-label="Tiến độ import bài đọc"></progress></div>
        <div class="admin-alert admin-alert-error" data-import-feedback hidden role="alert"></div>
    </form>
</details>
