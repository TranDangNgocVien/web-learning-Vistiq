# Module: Quản lý đề thi

> Trạng thái hiện tại: Đã có builder, sinh đề, quy tắc và publish validation; cần đưa về một ranh giới module.

## Mục tiêu và ranh giới

Quản lý template đề thi, cấu trúc part, quy tắc chọn câu, revision và phát hành. Không sở hữu nội dung câu hỏi; đề chỉ tham chiếu QuestionBank và lưu snapshot cần thiết khi bắt đầu lượt thi.

## Hiện trạng trong repo

ExamBuilderController, ExamRuleController, ExamGenerationService và ExamPublishingService đang cung cấp phần chính. Đã có route quản lý đề và tests cho workspace, generation, publish validation.

## Chức năng cần có

- Danh sách/tìm kiếm đề theo mã, loại kỳ thi, trạng thái và người tạo.
- Tạo, sửa, nhân bản draft, cấu hình part/thời lượng/điểm và số câu.
- Thêm câu hỏi hoặc nhóm câu từ ngân hàng; sinh đề theo rule và xem trước kết quả.
- Validate cấu trúc và gửi revision đi duyệt; hàng đợi/approve/trả chỉnh sửa do ContentApproval quản lý. ExamManagement publish sau khi được duyệt, xem revision và archive.
- Xem revision, danh sách câu hỏi đang thiếu/không tương thích và preview như learner.

## Yêu cầu giao diện

Admin theo AGENTS.md. Builder đặt cấu trúc đề và câu hỏi ở vùng làm việc chính; toolbar chỉ chứa lưu, validate, preview và publish. Lỗi validation gắn cạnh part/câu hỏi liên quan, có summary điều hướng. Preview learner dùng chính dữ liệu thực tế và không làm thay đổi draft. Trên màn hình hẹp, chia builder thành các bước/accordion có thể truy cập bằng bàn phím.

## Validation

- Mã đề duy nhất; exam type, part và skill phải tồn tại.
- Part có giới hạn thời gian/số câu/điểm hợp lệ; tổng điểm và thời lượng không âm, nằm trong giới hạn cấu hình.
- Câu tham chiếu phải published và tương thích loại kỳ thi/part/skill.
- Không lặp câu trái rule; audio/passages và số câu trong group phải đủ.
- Không publish đề rỗng, thiếu part bắt buộc hoặc có lỗi blocker.
- State transition hợp lệ; cập nhật đề đang được dùng không sửa kết quả/snapshot của attempt cũ.

## CRUD và vòng đời

CRUD template và part khi còn draft. Sau publish, thay đổi tạo revision mới hoặc chuyển lại draft theo permission; không ghi đè snapshot của lần thi đã bắt đầu. Revision cần đi qua ContentApproval trước khi publish. Archive thay cho hard delete khi đã có attempt.

## Quyền và audit

content.view, content.edit, content.publish là điểm xuất phát theo route hiện có; content.review thuộc ContentApproval. Audit tạo/sửa/sinh/submit duyệt/publish/archive và thay đổi cấu trúc đề.

## Kế hoạch test

- Feature: CRUD draft, gắn câu hỏi, sinh đề, preview, gửi duyệt, approve/trả chỉnh sửa qua ContentApproval và publish.
- Validation: exam type/part sai, part rỗng, câu không tương thích, thiếu stimulus, tổng thời lượng/điểm sai.
- Authorization: editor không publish nếu thiếu quyền; request trực tiếp bị từ chối dù nút bị ẩn.
- Domain unit: rule chọn câu, chống lặp, kiểm tra đủ số lượng và chuyển trạng thái.
- Regression: đề/revision mới không làm thay đổi kết quả attempt cũ.
- Concurrency: hai admin sửa cùng revision không âm thầm ghi đè; báo xung đột.
- Browser: lỗi blocker dẫn đến đúng vị trí, preview không phát sinh dữ liệu.

## Tiêu chí nghiệm thu và phụ thuộc

Đề chỉ publish khi hợp lệ và đã được duyệt, learner chỉ thấy đề đã công bố, và dữ liệu cũ của attempt bất biến. Phụ thuộc QuestionBank, ContentApproval, Assessment catalog và workflow/audit.
