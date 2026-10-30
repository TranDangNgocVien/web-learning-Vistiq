<div class="csv-guide">
    <details open>
        <summary>Cách điền và sử dụng mẫu</summary>
        <div class="csv-guide-content">
            <ol>
                <li>Tải mẫu theo kỳ thi hoặc theo dạng trong bảng bên dưới. Giữ nguyên hàng tiêu đề, sửa câu hỏi và đáp án, xóa các dòng không dùng.</li>
                <li>Lưu <b>CSV UTF-8</b>. Nếu Excel mở gộp cột, dùng <b>Data → From Text/CSV</b>, chọn UTF-8 và dấu phẩy. Nội dung có dấu phẩy hoặc JSON phải nằm trong một ô; Excel sẽ bao và nhân đôi dấu nháy khi lưu CSV.</li>
                <li>Điền mã <code>toeic</code>, <code>ielts</code> hoặc <code>thpt</code> và Part/Kỹ năng đúng bảng tra. Với THPT, dùng dạng <code>multiple_choice</code> và đủ bốn phương án A–D. Độ khó dùng <code>easy</code>, <code>medium</code>, <code>hard</code>. Cột <code>sample_note</code> chỉ để hướng dẫn, không lưu vào câu hỏi.</li>
                <li>Với Reading, có thể liên kết passage bằng <code>stimulus</code>. Với Listening, dùng chung mã <code>audio_group</code> cho các câu cùng bài nghe; sau khi import, tải audio riêng ở <a href="{{ route('admin.resources.index', ['type' => 'audio']) }}">Tài nguyên</a>, mở tài nguyên và gắn vào nhóm câu hỏi phù hợp. Không nhập tên, ID hay đường dẫn audio trong CSV.</li>
                <li>Import tạo bản nháp. Rà soát câu hỏi, media và đáp án trước khi gửi duyệt. Khi có lỗi, chỉ sửa và import lại những dòng lỗi để tránh tạo trùng những câu đã nhập thành công.</li>
            </ol>
            <p class="csv-guide-notice"><b>Mẫu sơ đồ / bản đồ cần bổ sung ảnh:</b> thay <code>"image_id": null</code> trong phần <code>metadata</code> của <code>payload_json</code> bằng ID ảnh thực tế trong kho Tài nguyên trước khi import. TOEIC Part 1 cần ảnh; mỗi nhóm Listening cần được gắn audio sau khi tải lên riêng trước khi xuất bản.</p>
            <p class="form-help">Các file bảng tra Part, loại câu hỏi và độ khó chỉ dùng đọc hướng dẫn; không tải chúng lên form import câu hỏi.</p>
        </div>
    </details>

    <details>
        <summary>Phân loại TOEIC / IELTS và mã Part / Kỹ năng</summary>
        <div class="csv-guide-content">
            <p><b>TOEIC trong Vistiq:</b> Listening Part 1–4, Reading Part 5–7, chỉ dùng <code>multiple_choice</code>. Part 2 có 3 lựa chọn A/B/C; các Part khác có 4 lựa chọn A/B/C/D.</p>
            <p><b>IELTS:</b> Reading Part 1–3 là thứ tự passage/section trong Vistiq. Dạng câu hỏi được chọn theo kỹ năng và nội dung bài; Reading Part 1 có thể có nhiều dạng câu hỏi Reading.</p>
            <p><b>Tốt nghiệp THPT:</b> các môn trắc nghiệm có Part riêng theo môn; đề luyện tập THPT chỉ nhận câu hỏi bốn lựa chọn. Ngữ văn thi tự luận nên không có trong template này.</p>
            <a class="text-link" href="{{ route('admin.questions.import-template', ['template' => 'parts']) }}">Tải bảng mã Part / Kỹ năng CSV</a>
            <div class="csv-guide-table-wrap" tabindex="0" role="region" aria-label="Bảng mã Part và kỹ năng">
                <table class="csv-guide-table">
                    <thead><tr><th scope="col">Kỳ thi / Kỹ năng</th><th scope="col">Mã exam_part</th><th scope="col">Part / Task</th><th scope="col">Cách sử dụng</th></tr></thead>
                    <tbody>
                        @foreach ($csvGuide['parts'] as $partGuide)
                            <tr><td><b>{{ strtoupper($partGuide['exam_type']) }}</b><br><code>{{ $partGuide['skill'] }}</code></td><td><code>{{ $partGuide['exam_part'] }}</code></td><td>{{ $partGuide['name'] }}</td><td>{{ $partGuide['note'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="form-help">Bảng loại câu hỏi bên dưới cho biết dạng nào được dùng cho từng kỹ năng IELTS. Các mã <code>listening</code>, <code>reading</code>, <code>speaking</code> là mã tổng hợp có sẵn; ưu tiên mã Part cụ thể khi biên soạn mới.</p>
        </div>
    </details>

    <details>
        <summary>Chọn độ khó: Dễ / Trung bình / Khó</summary>
        <div class="csv-guide-content">
            <p>Đây là <b>quy ước biên soạn của Vistiq</b>, không quy đổi trực tiếp sang điểm TOEIC hoặc band IELTS. Đánh giá theo yêu cầu xử lý, ngôn ngữ, thông tin cần kết hợp và phương án nhiễu; số Part không tự quyết định độ khó.</p>
            <a class="text-link" href="{{ route('admin.questions.import-template', ['template' => 'difficulty']) }}">Tải bảng tiêu chí độ khó CSV</a>
            <div class="csv-guide-table-wrap" tabindex="0" role="region" aria-label="Tiêu chí độ khó câu hỏi">
                <table class="csv-guide-table">
                    <thead><tr><th scope="col">Mã / Mức</th><th scope="col">Tiêu chí chung</th><th scope="col">Listening</th><th scope="col">Reading</th><th scope="col">Writing / Speaking</th></tr></thead>
                    <tbody>
                        @foreach ($csvGuide['difficulties'] as $difficultyGuide)
                            <tr><td><code>{{ $difficultyGuide['code'] }}</code><br><b>{{ $difficultyGuide['name'] }}</b></td><td>{{ $difficultyGuide['rule'] }}</td><td>{{ $difficultyGuide['listening'] }}</td><td>{{ $difficultyGuide['reading'] }}</td><td>{{ $difficultyGuide['productive'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="form-help">Ví dụ: tìm hạn chót trong một câu thường là dễ; nhận ra cách diễn đạt tương đương thường là trung bình; đối chiếu ngày nhận hàng và địa điểm thay đổi từ hai tài liệu thường là khó. Đánh giá lại sau khi có dữ liệu làm bài thực tế.</p>
        </div>
    </details>

    <details>
        <summary>20 loại câu hỏi: hướng dẫn đáp án và tải CSV riêng</summary>
        <div class="csv-guide-content">
            <p>TOEIC chỉ dùng <code>multiple_choice</code>. Cột kỹ năng bên dưới áp dụng cho IELTS. File riêng theo loại có các dòng mẫu cho những kỹ năng tương ứng.</p>
            <a class="text-link" href="{{ route('admin.questions.import-template', ['template' => 'types']) }}">Tải bảng hướng dẫn loại câu hỏi CSV</a>
            <div class="csv-guide-table-wrap" tabindex="0" role="region" aria-label="Loại câu hỏi và mẫu CSV">
                <table class="csv-guide-table">
                    <thead><tr><th scope="col">Loại / Mã question_type</th><th scope="col">Kỹ năng IELTS</th><th scope="col">Cách khai báo đáp án</th><th scope="col">Mẫu</th></tr></thead>
                    <tbody>
                        @foreach ($csvGuide['types'] as $typeGuide)
                            <tr><td><b>{{ $typeGuide['name'] }}</b><br><code>{{ $typeGuide['code'] }}</code></td><td>{{ implode(', ', $typeGuide['skills']) }}</td><td>{{ $typeGuide['note'] }}</td><td><a class="text-link" href="{{ route('admin.questions.import-template', ['template' => 'type-'.$typeGuide['code']]) }}" aria-label="Tải CSV mẫu {{ $typeGuide['name'] }}">Tải CSV</a></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </details>

    <details>
        <summary>Ý nghĩa các cột CSV và ví dụ payload_json</summary>
        <div class="csv-guide-content">
            <div class="csv-guide-table-wrap" tabindex="0" role="region" aria-label="Hướng dẫn từng cột CSV">
                <table class="csv-guide-table">
                    <thead><tr><th scope="col">Tên cột</th><th scope="col">Yêu cầu</th><th scope="col">Cách điền</th></tr></thead>
                    <tbody>
                        @foreach ($csvGuide['columns'] as $columnGuide)
                            <tr><td><code>{{ $columnGuide[0] }}</code></td><td>{{ $columnGuide[1] }}</td><td>{{ $columnGuide[2] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <h4>Ví dụ JSON nhập trong một ô payload_json</h4>
            <p>Nhiều đáp án đúng:</p>
            <pre><code>{"options":{"A":"Cycle lanes","B":"Swimming pool","C":"Public garden"},"correct_options":["A","C"]}</code></pre>
            <p>Matching: nhãn hàng và khóa đáp án phải tương ứng.</p>
            <pre><code>{"options":{"A":"City","B":"Village"},"metadata":{"labels":{"1":"Cycle lanes","2":"Bus service"},"answer_map":{"1":"A","2":"B"}}}</code></pre>
            <p>Nhiều ô trống: mỗi ô có đáp án riêng.</p>
            <pre><code>{"metadata":{"blanks":{"1":{"label":"Opening time","accepted_answers":["9","nine"],"max_words":1,"max_numbers":1},"2":{"label":"Location","accepted_answers":["River Road"],"max_words":2}}}}</code></pre>
            <p class="form-help">JSON dùng dấu nháy kép và không có dấu phẩy thừa. Giá trị trong JSON ghi đè các cột đáp án đơn giản tương ứng. Dùng mẫu Writing/Speaking để giữ đủ 4 tiêu chí rubric.</p>
        </div>
    </details>
</div>
