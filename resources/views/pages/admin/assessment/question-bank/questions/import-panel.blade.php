<details id="{{ $isThptEnglish ? 'qb-thpt-import' : 'qb-question-import' }}" class="csv-import {{ $isThptEnglish ? 'qb-thpt-import' : '' }}" @if($isThptEnglish)open @endif>
    <summary class="button button-quiet">@include('pages.dictionary.icon', ['name' => 'collection']) {{ $isThptEnglish ? 'Tải file CSV và mẫu import' : 'Import câu hỏi CSV' }}</summary>
    <form class="panel csv-import-form" method="post" action="{{ route('admin.questions.import') }}" enctype="multipart/form-data" data-question-import data-progress-url="{{ route('admin.questions.import-progress', ['token' => 'IMPORT_TOKEN']) }}">
        @csrf
        @if($isThptEnglish)<input type="hidden" name="return_to" value="thpt">@endif
        <input type="hidden" name="import_key" value="">
        <input type="hidden" name="offset" value="0">
        <div class="section-head"><div><span class="eyebrow">NẠP HÀNG LOẠT</span><h3>{{ $isThptEnglish ? 'Câu hỏi và văn bản dùng chung' : 'Câu hỏi và nhóm audio Listening' }}</h3><p class="form-help">{{ $isThptEnglish ? 'Mỗi câu THPT có bốn phương án A–D và một đáp án đúng. Dùng reading_key để gom câu hỏi cùng văn bản.' : 'Bộ mẫu có câu hỏi minh họa. Sửa nội dung và bỏ dòng không dùng trước khi import.' }}</p></div></div>
        <div class="csv-template-downloads" aria-label="Tải mẫu câu hỏi CSV">
            <a class="button button-quiet" href="{{ route('admin.questions.import-template', ['template' => 'all']) }}">Mẫu tổng hợp CSV</a>
            @unless($isThptEnglish)<a class="button button-quiet" href="{{ route('admin.questions.import-template', ['template' => 'toeic']) }}">Mẫu TOEIC · 7 Part</a>
            <a class="button button-quiet" href="{{ route('admin.questions.import-template', ['template' => 'ielts']) }}">Mẫu IELTS · 4 kỹ năng</a>
            <a class="button button-quiet" href="{{ route('admin.questions.import-template', ['template' => 'listening-groups']) }}">Mẫu nhóm audio Listening</a>@endunless
            <a class="button button-quiet" href="{{ route('admin.questions.import-template', ['template' => 'readings']) }}">Mẫu bài đọc kèm câu hỏi</a>
            <a class="button button-quiet" href="{{ route('admin.questions.import-template', ['template' => 'bundle']) }}">Tải bộ mẫu ZIP</a>
            <a class="text-link" href="{{ route('admin.questions.import-template', ['template' => 'guide']) }}">Tải hướng dẫn</a>
        </div>
        <label class="admin-label" for="question-csv-file">File CSV
            <input class="admin-input" id="question-csv-file" type="file" name="file" accept=".csv,.txt,text/csv" required @if($isThptEnglish)data-thpt-csv-file @endif>
            <span class="form-help">Bắt buộc có cột <code>exam_part</code> và <code>prompt</code>. Có thể dùng mã như <code>tieng-anh-part-5</code>, <code>multiple_choice</code>, <code>medium</code>.</span>
        </label>
        @if($isThptEnglish)
            <section class="qb-csv-image-import" data-thpt-csv-images hidden aria-live="polite">
                <header><b>Ảnh văn bản dùng chung</b><span data-thpt-csv-image-summary></span></header>
                <p>File có câu thuộc Phần 1 hoặc 2. Chọn một ảnh cho mỗi <code>reading_key</code>; ảnh sẽ gắn với cả nhóm câu của văn bản.</p>
                <div data-thpt-csv-image-groups></div>
                <p class="admin-alert admin-alert-error" data-thpt-csv-image-error role="alert" hidden></p>
            </section>
        @endif
        <div class="admin-form-grid">
            <label>Loại câu hỏi mặc định
                <select name="default_question_type" required>
                    @foreach ($questionTypes as $questionType)<option value="{{ $questionType->id }}" @selected(old('default_question_type', $defaultQuestionTypeId) == $questionType->id)>{{ $questionType->name }}</option>@endforeach
                </select>
            </label>
            <label>Độ khó mặc định
                <select name="default_difficulty" required>
                    @foreach ($difficulties as $difficulty)<option value="{{ $difficulty->id }}" @selected(old('default_difficulty', $defaultDifficultyId) == $difficulty->id)>{{ $difficulty->name }}</option>@endforeach
                </select>
            </label>
            <label>Trạng thái sau khi import
                <select name="default_status" required><option value="draft">Bản nháp · rà soát trước khi xuất bản</option></select>
            </label>
        </div>
        @if($isThptEnglish)
            <p class="form-help">Dùng <code>option_a</code> đến <code>option_d</code>, <code>correct_option</code>, <code>exam_part</code> và <code>reading_key</code> cho câu cùng văn bản. Phần 1–2 cần <code>reading_title</code>, <code>reading_body</code> và một ảnh cho mỗi nhóm; khung chọn ảnh hiện ra sau khi đọc file. Đề THPT không cần audio.</p>
        @else
            <p class="form-help">Trắc nghiệm đơn dùng <code>option_a</code> đến <code>option_d</code> và <code>correct_option</code>. Điền đáp án dùng <code>accepted_answers</code>, phân tách biến thể bằng <code>|</code>. Matching, nhiều đáp án, nhiều ô trống và rubric Writing/Speaking dùng <code>payload_json</code>. Chỉ ô loại câu hỏi hoặc độ khó bỏ trống mới dùng giá trị mặc định.</p>
            <p class="form-help">Để gom các câu cùng bài nghe, lặp cùng mã ở <code>audio_group</code> trên các dòng. CSV chỉ tạo nhóm câu hỏi, không chứa audio. Sau khi import, tải audio riêng ở <a href="{{ route('admin.resources.index', ['type' => 'audio']) }}">Tài nguyên</a>, mở tài nguyên đó và gắn vào nhóm câu hỏi phù hợp. File cũ thiếu <code>audio_group</code> vẫn có thể dùng mã <code>Audio script ID: CODE</code> trong <code>sample_note</code> để gom câu.</p>
            @include('pages.admin.assessment.question-bank.questions.csv-guide')
            <p class="form-help">Tạo bài đọc kèm câu hỏi bằng <code>reading_key</code>, <code>reading_title</code> và <code>reading_body</code>. Lặp cùng <code>reading_key</code> cho các câu thuộc một bài; hệ thống tự tạo bài và nhóm theo phần. TOEIC <code>part-6</code> được lưu là Paragraph.</p>
        @endif
        <div class="csv-import-actions"><span class="form-help">Tối đa 2.000 dòng / 10 MB mỗi lần. Những dòng hợp lệ được lưu dù có dòng khác bị lỗi.</span><button class="button button-primary" type="submit">Import câu hỏi</button></div>
        <div class="import-progress" data-import-progress hidden aria-live="polite">
            <div class="import-progress-heading"><b data-import-progress-label>Đang tải file…</b><span data-import-progress-count>0%</span></div>
            <progress max="100" value="0" data-import-progress-bar aria-label="Tiến độ import câu hỏi"></progress>
        </div>
        <div class="admin-alert admin-alert-error" data-import-feedback hidden role="alert"></div>
    </form>
</details>
