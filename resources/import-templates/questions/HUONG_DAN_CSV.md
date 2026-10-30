# Hướng dẫn Import CSV ngân hàng câu hỏi Vistiq

## 1. Bộ mẫu

- vistiq-question-bank-template.csv: 41 dòng minh họa, đủ 20 dạng câu hỏi đang hỗ trợ.
- vistiq-toeic-template.csv: 7 dòng, TOEIC Listening Part 1–4 và Reading Part 5–7.
- vistiq-ielts-template.csv: 34 dòng, IELTS Listening, Reading, Writing và Speaking.
- vistiq-readings-with-questions-template.csv: 3 bài, 9 câu; TOEIC Part 7 (3 câu), Paragraph Part 6 (4 câu) và IELTS Reading (2 câu).
- by-question-type/: CSV riêng theo từng loại.
- part-skill-guide.csv, question-type-guide.csv, difficulty-guide.csv: bảng tra cứu; không nhập các file hướng dẫn này vào form câu hỏi.

Các dòng là mẫu để sửa thành nội dung của bạn. Hãy xóa dòng không dùng. Cột sample_note chỉ hướng dẫn và không được lưu vào câu hỏi.

## 2. Cách dùng

1. Chọn file mẫu theo kỳ thi hoặc dạng câu hỏi. Dùng Excel Data / From Text/CSV, chọn UTF-8 và dấu phẩy nếu Excel không tự chia cột.
2. Sửa prompt, lựa chọn, đáp án, explanation và độ khó. Giữ nguyên tên cột. Lưu CSV UTF-8; mỗi câu hỏi là một dòng dữ liệu.
3. Điền exam_type, skill, exam_part, question_type theo bảng mã. Giá trị mặc định trên form chỉ dùng khi question_type hoặc difficulty bỏ trống. Mã gõ sai sẽ báo lỗi từng dòng.
4. Dòng có diagram_label_completion hoặc plan_map_labelling BẮT BUỘC thay metadata.image_id=null bằng ID ảnh có sẵn trong kho Media. Phần này chưa điền sẽ báo lỗi, không tự tạo ảnh.
5. Với Listening, dùng cùng mã `audio_group` cho các câu hỏi của một bài nghe. CSV chỉ tạo nhóm câu hỏi. Sau khi import, tải audio riêng trong mục Audio rồi gắn audio vào nhóm trong mục Nhóm câu hỏi. TOEIC Part 1 cần ảnh phù hợp. Không chép transcript vào prompt cho học viên.
6. Tải CSV lên form Import. Tất cả câu hỏi được lưu thành draft, sau đó rà soát, gửi duyệt và xuất bản theo quyền. Mỗi lần tối đa 2.000 dòng và 10 MB.

Import xử lý từng dòng: dòng hợp lệ được lưu, dòng lỗi được báo số dòng và lý do. File có thể đã nhập một phần. Sửa lỗi rồi chỉ nhập lại các dòng thất bại để tránh câu hỏi trùng.

## 3. Các cột

| Cột | Yêu cầu | Cách điền |
| --- | --- | --- |
| `exam_type` | Khuyến nghị | toeic hoặc ielts. Nếu có giá trị, phải trùng kỳ thi của exam_part. |
| `skill` | Khuyến nghị | listening, reading, writing hoặc speaking. Nếu có giá trị, phải trùng kỹ năng của exam_part. |
| `exam_part` | Bắt buộc | Dùng đúng mã trong bảng Part/Kỹ năng. Có thể dùng ID hiện có, nhưng mã dễ chuyển giữa các môi trường hơn. |
| `question_type` | Khuyến nghị | Một trong 20 mã loại câu hỏi. Bỏ trống mới dùng giá trị mặc định trên form Import. Mã gõ sai sẽ báo lỗi. |
| `difficulty` | Khuyến nghị | easy, medium hoặc hard. Bỏ trống mới dùng giá trị mặc định; không tự suy ra theo số Part. |
| `prompt` | Bắt buộc | Nội dung câu hỏi. Có thể chứa HTML an toàn. Passage có thể đặt trong prompt hoặc liên kết qua stimulus. |
| `option_a / option_b / option_c / option_d` | Theo dạng | Dùng cho trắc nghiệm A-D đơn giản; Part 2 TOEIC để option_d trống. Dạng nâng cao dùng payload_json.options. |
| `correct_option` | Theo dạng | Một khóa A/B/C/D tương ứng đáp án đúng. Dạng nâng cao dùng payload_json.correct_options. |
| `accepted_answers` | Theo dạng | Một ô điền/trả lời ngắn: phân tách biến thể bằng \|, ví dụ bicycle\|bike. Không nhập công thức Excel. |
| `explanation` | Nên có | Giải thích đáp án đúng và lý do loại các phương án nhiễu. |
| `tags` | Tùy chọn | Nhiều tag phân tách bằng dấu phẩy; CSV tự bao dấu nháy kép cho ô có dấu phẩy. |
| `stimulus` | Khi có passage Reading | ID hoặc tiêu đề chính xác của passage đã có trong kho. Với Listening, audio không liên kết qua CSV. |
| `audio_group` | Tùy chọn cho Listening | Mã gom các câu hỏi cùng một bài nghe. Lặp cùng mã trên mọi dòng trong nhóm; importer tạo nhóm chưa gắn audio. |
| `reading_key` | Khi tạo bài đọc kèm câu hỏi | Mã gom bài trong file, tối đa 100 ký tự. Các dòng cùng mã (không phân biệt hoa thường) tạo một bài và một nhóm câu hỏi theo phần. Đây không phải mã bài trong hệ thống. |
| `reading_title` | Dòng đầu tiên hợp lệ của bài | Tiêu đề bài mới, tối đa 255 ký tự. Lặp lại cùng tiêu đề hoặc để trống trên những dòng sau của cùng reading_key. |
| `reading_body` | Dòng đầu tiên hợp lệ của bài | Nội dung bài mới, tối đa 50.000 ký tự; hỗ trợ HTML an toàn và xuống dòng trong ô CSV. Lặp cùng nội dung hoặc để trống ở những dòng sau của cùng reading_key. |
| `status` | Bản nháp | Dùng draft. Mọi câu import đều vào bản nháp để rà soát, duyệt và xuất bản. |
| `payload_json` | Dạng nâng cao | JSON object chứa options, correct_options, accepted_answers, metadata. Những trường này ghi đè cột đơn giản nếu được nhập trong JSON. |
| `sample_note` | Chỉ hướng dẫn | Ghi chú cách dùng dòng mẫu, kịch bản audio và media cần bổ sung. Không nhập vào nội dung câu hỏi. Có thể xóa cột này. |

CSV dùng dấu phẩy, dấu chấm phẩy hoặc tab được hỗ trợ khi import. File mẫu dùng dấu phẩy, UTF-8 BOM và quy tắc CSV chuẩn: ô chứa dấu phẩy, dấu nháy kép hoặc xuống dòng được bao bằng dấu nháy kép; nháy kép bên trong được nhân đôi. Không gộp ô hay chèn hàng hướng dẫn phía trên header.

### Bài đọc kèm câu hỏi

Tải **Mẫu CSV kèm câu hỏi** trong **Bài đọc**, sửa file rồi dùng **Import bài đọc CSV** ngay tại trang đó. Có thể tải và nhập cùng mẫu từ ngân hàng câu hỏi.

- Mỗi dòng chứa một câu hỏi, các lựa chọn/đáp án và giải thích. Dùng cùng `reading_key` cho các câu thuộc cùng bài, kể cả khi các dòng không nằm liền nhau.
- Dòng đầu tiên hợp lệ của mỗi bài cần `reading_title` và `reading_body`. Những dòng sau có thể lặp lại hoặc để trống hai ô này. Các giá trị được điền phải thống nhất; mọi câu trong bài dùng cùng `exam_part`.
- `part-7` tạo passage TOEIC; `part-6` tự tạo Paragraph; `reading-part-1/2/3` tạo passage IELTS. Đặt đoạn văn ở `reading_body`, chỉ đặt yêu cầu riêng của từng câu ở `prompt`.
- Import tự tạo bài nháp, nhóm theo phần và thứ tự câu tăng dần. Bài có mã do hệ thống cấp; `reading_key` chỉ gom bài trong lần import hiện tại.
- Khi tạo bài bằng `reading_key`, không đồng thời điền `stimulus`, `audio_group` hoặc `payload_json.group_id/stimulus_ids`. Để liên kết bài đã có, dùng `stimulus` trong luồng import câu hỏi hiện có và bỏ các cột `reading_*`.
- Mỗi lần import mới tạo bài mới. Khi có dòng lỗi, kiểm tra những bài đã tạo trước khi nhập lại; có thể thêm các câu bị lỗi ngay trong bài đã lưu. Không tải lại toàn bộ file sau khi một phần đã thành công.

File mẫu chứa đầy đủ nội dung bài, đáp án và giải thích; không cần tạo passage trước. Các ô `payload_json` minh họa giới hạn từ của câu trả lời ngắn và đáp án True / False / Not Given của IELTS.

### Nhóm Listening dùng chung audio

Mỗi dòng vẫn là một câu hỏi. Với các câu cùng một cuộc hội thoại, bài nói hoặc IELTS Listening section, dùng cùng một mã `audio_group`. Không điền tên, ID, đường dẫn hoặc transcript audio vào CSV. Sau khi import, tải một audio cho mỗi nhóm trong mục **Audio**, rồi vào **Nhóm câu hỏi** để gắn audio đó vào nhóm tương ứng.

```csv
exam_part,audio_group,prompt
part-3,TOEIC-F1-P3-G01,Why does the woman call the man?
part-3,TOEIC-F1-P3-G01,What will the man ask the driver to do?
part-3,TOEIC-F1-P3-G01,What is indicated about the reception desk?
```

Để dùng lại file cũ, nếu `audio_group` bỏ trống, importer nhận diện mã `Audio script ID: CODE` trong `sample_note` để gom câu hỏi. Mã này chỉ tạo tên nhóm, không tìm hoặc gắn audio. `sample_note` vẫn không được nhập thành nội dung câu hỏi.

## 4. Kỳ thi, Part và kỹ năng

TOEIC trong Vistiq là TOEIC Listening & Reading. Chỉ dùng multiple_choice. Part 2 có 3 lựa chọn; các Part khác có 4. IELTS Reading và Listening có nhiều dạng, Writing dùng essay, Speaking dùng speaking. Phân biệt metadata.variant=academic/general khi có nội dung Academic hoặc General Training.

| Kỳ thi | Kỹ năng | Mã exam_part | Phần | Hướng dẫn |
| --- | --- | --- | --- | --- |
| toeic | listening | `part-1` | Part 1 · Photographs | Nghe mô tả ảnh, chọn 1 trong 4 đáp án. Cần ảnh và audio tương ứng; không đưa transcript vào prompt. |
| toeic | listening | `part-2` | Part 2 · Question-Response | Nghe câu hỏi/câu nói và chọn phản hồi. Chỉ 3 lựa chọn A/B/C, để option_d trống. Tải và gắn audio riêng sau khi import. |
| toeic | listening | `part-3` | Part 3 · Conversations | Nghe hội thoại và trả lời câu hỏi. Mỗi câu 4 lựa chọn; các câu cùng hội thoại dùng chung `audio_group` và một audio. |
| toeic | listening | `part-4` | Part 4 · Talks | Nghe bài nói/thông báo và trả lời câu hỏi. Mỗi câu 4 lựa chọn; các câu cùng bài nói dùng chung `audio_group` và một audio. |
| toeic | reading | `part-5` | Part 5 · Incomplete Sentences | Hoàn thành một câu bằng ngữ pháp hoặc từ vựng phù hợp. Đặt câu có chỗ trống trong prompt; 4 lựa chọn. |
| toeic | reading | `part-6` | Part 6 · Text Completion | Hoàn thành đoạn văn. Đưa đủ ngữ cảnh đoạn vào prompt hoặc passage; mỗi câu 4 lựa chọn. |
| toeic | reading | `part-7` | Part 7 · Reading Comprehension | Đọc một hoặc nhiều tài liệu rồi trả lời. Đưa đủ tài liệu vào prompt hoặc passage; mỗi câu 4 lựa chọn. |
| ielts | listening | `listening-part-1` | Listening Part 1 | Dùng một `audio_group` cho toàn bộ câu hỏi trong cùng cuộc hội thoại/form. |
| ielts | listening | `listening-part-2` | Listening Part 2 | Dùng một `audio_group` cho toàn bộ câu hỏi trong cùng bài nói. |
| ielts | listening | `listening-part-3` | Listening Part 3 | Dùng một `audio_group` cho toàn bộ câu hỏi trong cùng cuộc thảo luận. |
| ielts | listening | `listening-part-4` | Listening Part 4 | Dùng một `audio_group` cho toàn bộ câu hỏi trong cùng bài giảng. |
| ielts | reading | `reading-part-1` | Reading Part 1 | Part 1/2/3 là thứ tự passage/section trong Vistiq; loại câu hỏi không cố định riêng cho một Part. |
| ielts | reading | `reading-part-2` | Reading Part 2 | Part 1/2/3 là thứ tự passage/section trong Vistiq; loại câu hỏi không cố định riêng cho một Part. |
| ielts | reading | `reading-part-3` | Reading Part 3 | Part 1/2/3 là thứ tự passage/section trong Vistiq; loại câu hỏi không cố định riêng cho một Part. |
| ielts | speaking | `speaking-part-1` | Speaking Part 1 | Hỏi về chủ đề quen thuộc. |
| ielts | speaking | `speaking-part-2` | Speaking Part 2 | Cue card, 1 phút chuẩn bị, tối đa 2 phút nói. |
| ielts | speaking | `speaking-part-3` | Speaking Part 3 | Thảo luận sâu hơn, có phân tích và lập luận. |
| ielts | writing | `writing-task-1` | Writing Task 1 | Academic: mô tả dữ liệu/biểu đồ; General: thư. Tối thiểu 150 từ. |
| ielts | writing | `writing-task-2` | Writing Task 2 | Bài luận, tối thiểu 250 từ. |
| ielts | listening | `listening` | listening · mã tổng hợp | Mã tổng hợp đã có trong danh mục; ưu tiên mã có Part khi biên soạn mới. |
| ielts | reading | `reading` | reading · mã tổng hợp | Mã tổng hợp đã có trong danh mục; ưu tiên mã có Part khi biên soạn mới. |
| ielts | speaking | `speaking` | speaking · mã tổng hợp | Mã tổng hợp đã có trong danh mục; ưu tiên mã có Part khi biên soạn mới. |

Reading Part 1/2/3 chỉ thứ tự passage/section trong hệ thống, không ấn định một loại câu hỏi riêng cho từng Part. Chọn loại phù hợp kỹ năng và nội dung. Bảng loại câu hỏi bên dưới và part-skill-guide.csv ghi các mã được phép.

## 5. Các dạng câu hỏi

| Mã question_type | Tên | Kỹ năng IELTS | Cách điền |
| --- | --- | --- | --- |
| `multiple_choice` | Trắc nghiệm một đáp án | reading, listening | Điền option_a đến option_d và correct_option=A/B/C/D. TOEIC Part 2 chỉ dùng A/B/C; các Part khác dùng A/B/C/D. |
| `multiple_response` | Trắc nghiệm nhiều đáp án | reading, listening | payload_json.options chứa các lựa chọn; correct_options là mảng, ví dụ ["A","C"]. Ghi số đáp án cần chọn trong prompt. |
| `true_false_not_given` | True / False / Not Given | reading | payload_json.options dùng đúng thứ tự khóa TRUE, FALSE, NOT_GIVEN. correct_options chứa đúng một khóa. |
| `yes_no_not_given` | Yes / No / Not Given | reading | payload_json.options dùng đúng thứ tự khóa YES, NO, NOT_GIVEN. correct_options chứa đúng một khóa. |
| `matching` | Nối đáp án | reading, listening | payload_json.options chứa đáp án A/B/C...; metadata.answer_map nối mã hàng với khóa đáp án; metadata.labels mô tả từng hàng. |
| `matching_information` | Nối thông tin với đoạn văn | reading | Đánh nhãn đoạn A/B/C... trong prompt. metadata.answer_map dùng khóa đáp án tồn tại trong options. |
| `matching_headings` | Nối tiêu đề đoạn | reading | options là danh sách tiêu đề. metadata.answer_map nối mỗi đoạn với tiêu đề; labels là nhãn đoạn. |
| `matching_features` | Nối đặc điểm | reading | options là người/đối tượng. metadata.answer_map nối đặc điểm với đối tượng; labels là mô tả đặc điểm. |
| `matching_sentence_endings` | Nối phần cuối câu | reading | options là phần cuối câu. metadata.labels chứa phần đầu câu; answer_map nối đầu câu với đáp án. |
| `sentence_completion` | Hoàn thành câu | reading, listening | Một chỗ trống dùng accepted_answers, ngăn cách biến thể bằng \|; metadata.max_words/max_numbers đặt giới hạn. |
| `summary_completion` | Hoàn thành tóm tắt | reading, listening | Một chỗ trống dùng accepted_answers. Nhiều chỗ trống dùng metadata.blanks, mỗi ô có label và accepted_answers. |
| `note_completion` | Hoàn thành ghi chú | reading, listening | Ví dụ mẫu có hai ô trong metadata.blanks. Mỗi ô có danh sách đáp án và giới hạn từ/số riêng. |
| `table_completion` | Hoàn thành bảng | reading, listening | Đưa bảng vào prompt; một ô dùng accepted_answers, nhiều ô dùng metadata.blanks. |
| `flow_chart_completion` | Hoàn thành lưu đồ | reading, listening | Mô tả các bước trong prompt; mỗi bước trống dùng một phần tử metadata.blanks hoặc accepted_answers. |
| `form_completion` | Hoàn thành biểu mẫu | listening | Dùng cho IELTS Listening. Giữ chính xác tên, địa chỉ, số điện thoại trong accepted_answers hoặc metadata.blanks. |
| `diagram_label_completion` | Điền nhãn sơ đồ | reading, listening | BẮT BUỘC thay metadata.image_id=null bằng ID ảnh trong kho Media. Đáp án ghi vào accepted_answers hoặc metadata.blanks. |
| `plan_map_labelling` | Điền nhãn bản đồ | listening | Dùng cho IELTS Listening. BẮT BUỘC thay metadata.image_id=null bằng ID ảnh bản đồ và liên kết audio trước khi xuất bản. |
| `short_answer` | Trả lời ngắn | reading, listening | accepted_answers chứa các biến thể đúng. metadata.max_words/max_numbers là giới hạn trả lời. |
| `essay` | Writing | writing | metadata.rubric cần đủ task_response, coherence, lexical_resource, grammar. Ghi variant=academic/general và min_words=150/250 tùy task. |
| `speaking` | Speaking | speaking | metadata.rubric cần đủ fluency, lexical_resource, grammar, pronunciation. Part 2 có cue_card; câu hỏi phụ nằm trong follow_ups. |

## 6. Gán độ khó

Đây là quy ước biên soạn của Vistiq, xét theo nhóm học viên mục tiêu. Không quy đổi trực tiếp easy/medium/hard thành band IELTS hay điểm TOEIC. Số Part, độ dài câu và tên dạng bài không tự quyết định độ khó.

| Mã difficulty | Tên | Tiêu chí |
| --- | --- | --- |
| `easy` | Dễ | Thông tin trực tiếp; từ vựng quen thuộc; cấu trúc đơn giản; một bước xử lý; phương án nhiễu dễ phân biệt. |
| `medium` | Trung bình | Cần paraphrase, kết hợp hai chi tiết hoặc xử lý ngữ cảnh; có phương án nhiễu hợp lý. |
| `hard` | Khó | Cần suy luận hoặc tổng hợp nhiều nguồn; ngôn ngữ phức tạp; phương án gần đúng hoặc thông tin gây nhiễu. |

- Listening: đánh giá tốc độ/độ rõ của audio, số người nói, cách diễn đạt lại, ý ngầm và mức nhiễu.
- Reading: đánh giá từ vựng/cấu trúc, vị trí bằng chứng, số thông tin cần kết nối và mức suy luận.
- Writing/Speaking: đánh giá chủ đề và yêu cầu phát triển ý; độ khó của đề khác với chất lượng bài làm.
- Sau khi có kết quả làm thử, rà lại nhãn theo tỷ lệ đúng của đúng nhóm học viên mục tiêu và phản hồi giáo viên. Không gán band hay điểm chỉ từ nhãn độ khó.

Ví dụ: TOEIC Part 5 kiểm tra by Friday là easy; Part 6 chọn liên từ dựa quan hệ hai câu là medium; Part 7 kết hợp email và thông báo để suy ra ngày/địa điểm là hard. Các nhãn trong mẫu là minh họa cho nội dung cụ thể.

## 7. Dữ liệu cấu trúc payload_json

JSON phải là object hợp lệ, dùng nháy kép. Khi có options/correct_options/accepted_answers/metadata trong payload_json, trường đó ghi đè dữ liệu cột đơn giản. Giữ JSON trong một ô; Excel sẽ xử lý bao nháy CSV khi lưu.

Nhiều đáp án:

```json
{"options":{"A":"Cycle lanes","B":"Swimming pool","C":"Public garden","D":"Car park"},"correct_options":["A","C"]}
```

Nối đáp án:

```json
{"options":{"A":"City","B":"Village"},"metadata":{"answer_map":{"1":"A","2":"B"},"labels":{"1":"Cycle lanes","2":"Bus service"}}}
```

Nhiều ô trống:

```json
{"metadata":{"blanks":{"1":{"label":"Opening time","accepted_answers":["9","nine"],"max_words":1,"max_numbers":1},"2":{"label":"Location","accepted_answers":["River Road"],"max_words":2}}}}
```

Writing:

```json
{"metadata":{"variant":"academic","min_words":250,"rubric":{"task_response":"Hoàn thành yêu cầu","coherence":"Mạch lạc và liên kết","lexical_resource":"Từ vựng","grammar":"Ngữ pháp"}}}
```

Speaking:

```json
{"metadata":{"cue_card":"Describe a place where you like to study.","follow_ups":["Why do you like it?"],"rubric":{"fluency":"Lưu loát và mạch lạc","lexical_resource":"Từ vựng","grammar":"Ngữ pháp","pronunciation":"Phát âm"}}}
```

## 8. Lỗi thường gặp

- exam_type=toeic nhưng exam_part=reading-part-1: Part này thuộc IELTS.
- skill=reading nhưng exam_part=part-1: TOEIC Part 1 thuộc Listening.
- question_type gõ sai: dùng đúng mã, không nhập tên hiển thị tiếng Việt.
- difficulty gõ Trung bình: dùng medium.
- Chọn dạng không phù hợp: TOEIC chỉ multiple_choice; IELTS Reading không dùng form_completion hay plan_map_labelling.
- Matching thiếu answer_map, nhiều đáp án thiếu correct_options, hoặc rubric thiếu tiêu chí: sửa payload_json theo mẫu.
- Diagram/map thiếu ID ảnh: tải ảnh vào kho Media và thay image_id bằng số ID thực tế.
- stimulus Reading có giá trị không tồn tại: dùng đúng ID/tiêu đề passage hoặc bỏ trống để bổ sung sau trong bản nháp.
- Số ô lớn hơn số cột: có dấu phẩy trong nội dung chưa được bao nháy đúng.

## 9. Nguồn định dạng bài thi

Các ví dụ do Vistiq tự biên soạn. Quy ước độ khó là hướng dẫn biên soạn nội bộ. Định dạng và nhóm câu hỏi tham chiếu:

- TOEIC Listening & Reading: https://www.ets.org/content/ets-org/ca/en/toeic/test-takers/about/listening-reading.html
- IELTS Reading: https://ielts.org/take-a-test/test-types/ielts-academic-test/ielts-academic-format-reading
- IELTS Listening: https://ielts.org/take-a-test/test-types/ielts-academic-test/ielts-academic-format-listening
- IELTS Writing: https://ielts.org/take-a-test/test-types/ielts-academic-test/ielts-academic-format-writing
- IELTS Speaking: https://ielts.org/take-a-test/test-types/ielts-academic-test/ielts-academic-format-speaking
