# Module: Ngân hàng câu hỏi

> Trạng thái hiện tại: Đã có một phần lớn chức năng Admin, import và editor; cần chia lại trách nhiệm và hoàn thiện theo tiêu chí thống nhất.

## Mục tiêu và ranh giới

Quản lý câu hỏi nguồn có thể tái sử dụng. Module sở hữu câu hỏi, đáp án, lựa chọn, nhóm câu hỏi và liên kết với audio/đoạn đọc. Đề thi và bài tập chỉ tham chiếu câu hỏi; không tạo bản sao câu hỏi riêng.

Phân nhánh nghiệp vụ: Listening, Reading, THPT QG và phần chung. Reading passage và audio asset có dữ liệu stimulus riêng nhưng phải được chọn/liên kết ngay trong luồng tạo câu hỏi.

## Hiện trạng trong repo

QuestionController, QuestionEditorController và StimulusController đang phụ trách các phần ngân hàng/import/Listening/Reading. Domain có catalog, registry, workbook importer và editor service; tests Admin đã có cho question bank, reading import, workbook và editor.

## Chức năng cần có

- Tìm, lọc, xem trước, tạo và chỉnh sửa câu hỏi.
- Quản lý câu hỏi theo Listening, Reading và THPT QG; bộ lọc theo kỳ thi, part, loại câu, mức độ và trạng thái.
- Quản lý đáp án, giải thích, tag, nguồn, độ khó và metadata.
- Gắn passage/audio hoặc nhóm câu hỏi; kiểm tra liên kết còn hợp lệ.
- Import workbook/CSV: nhận diện sheet, xem trước lỗi theo dòng, xác nhận ghi dữ liệu, xem tiến độ/kết quả; export nếu có quyền.
- Gửi revision đi duyệt; theo dõi trạng thái/ghi chú reviewer. Hàng đợi và quyết định approve/trả chỉnh sửa thuộc module ContentApproval; publish sau approve vẫn do QuestionBank thực hiện.
- Archive, xem lịch sử revision và nơi câu hỏi được sử dụng.
- Bulk update phải báo rõ số bản ghi thành công/thất bại và lý do.

## Yêu cầu giao diện

Admin theo AGENTS.md và 00-design-alignment.md: heading ngắn, bộ lọc/bảng là nội dung chính; không đưa banner hoặc thống kê trang trí lên trước. Dùng các view riêng cho danh sách, editor và import; import phải hiển thị đoạn đọc/audio đã nhận diện và nơi thêm/sửa stimulus nếu thiếu. Có preview learner, trạng thái empty/loading/error và xác nhận trước thao tác hàng loạt. Hỗ trợ keyboard, focus rõ, bảng co giãn hoặc chuyển sang thẻ ở viewport hẹp.

## Validation

- Kiểm tra exam type, part, skill, question type và difficulty thuộc catalog hợp lệ, có quan hệ tương thích.
- MCQ phải có lựa chọn hợp lệ và đúng một đáp án đúng; dạng nhập đáp án phải có ít nhất một đáp án chuẩn sau chuẩn hóa.
- Reading cần nội dung passage không rỗng; Listening cần audio/group hợp lệ khi dạng câu hỏi yêu cầu.
- Làm sạch HTML đầu vào; giới hạn loại file, dung lượng và số dòng theo cấu hình import.
- Preview import không ghi dữ liệu; confirm dùng preview hợp lệ, báo lỗi từng hàng và không import trùng khi gửi lại cùng yêu cầu.
- Không cho đổi liên kết câu hỏi đã nằm trong đề/attempt theo cách làm sai snapshot đã phát sinh.

## CRUD và vòng đời

Create/read/update/archive nội dung câu hỏi và nhóm. QuestionBank tạo/sửa draft và thực hiện publish sau khi revision được duyệt; không sở hữu quyết định approve/trả chỉnh sửa. Hard delete chỉ cho bản nháp chưa có tham chiếu; nếu đã dùng thì archive. Stimulus dùng CRUD theo chính sách media/content; xóa asset đang được tham chiếu phải bị chặn hoặc yêu cầu thay liên kết.

## Quyền và audit

Áp dụng content.view, content.edit, content.publish; dùng ContentApproval/content.review cho quyết định. Ghi audit cho import confirm, sửa đáp án, submit, publish, bulk action và xóa/archive.

## Kế hoạch test

- Feature: danh sách và filter từng nhánh Listening/Reading/THPT.
- Feature: create/update đúng schema cho MCQ và câu hỏi có passage/audio.
- Validation: part/skill sai, thiếu đáp án, nhiều đáp án đúng, passage/audio thiếu.
- Authorization: viewer không tạo/sửa/import; editor không vượt quyền publish; quyết định review được kiểm tra ở ContentApproval.
- Import: workbook hợp lệ, sheet lỗi, row lỗi, duplicate, preview không ghi, confirm ghi đúng một lần.
- Regression: câu hỏi đã gắn với exam/attempt giữ snapshot và liên kết.
- Unit: chuẩn hóa đáp án, sanitize HTML, ánh xạ cột workbook và rule tương thích catalog.
- Browser: editor/import thao tác bằng keyboard, thông báo lỗi rõ, layout responsive.

## Tiêu chí nghiệm thu và phụ thuộc

Một câu hỏi có thể đi từ draft qua ContentApproval đến published, được tìm và tham chiếu từ module khác mà không nhân bản dữ liệu. Import hiển thị lỗi có thể sửa trước khi ghi. Phụ thuộc Knowledge catalog cho tag/terms nếu được bật, Content/Media cho asset, ContentApproval và ExamManagement cho workflow/liên kết sử dụng.
