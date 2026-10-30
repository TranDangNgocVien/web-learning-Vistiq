# Module: Quản lý lượt thi

> Trạng thái hiện tại: Đã có danh sách/chi tiết attempt, chấm điểm, moderation và policy; cần thống nhất với luồng làm bài Learner.

## Mục tiêu và ranh giới

Mặt Admin dùng để giám sát mọi lượt làm bài đã phát sinh ở cả mode Practice và Exam: tiến độ, câu trả lời, chấm tự luận, sự cố/báo cáo và điều chỉnh có kiểm toán. Cả hai mode là cùng một Attempt aggregate; trang quản lý có thể lọc theo mode.

## Hiện trạng trong repo

AttemptController, GradingController, AttemptService, ScoringService, GradingService và LeaderboardService đang bao phủ các phần chính. Tests đã có cho attempt workflow, scoring và thao tác Admin.

## Chức năng cần có

- Tìm/lọc lượt theo người dùng, nguồn bài/bộ đề, mode Practice/Exam, trạng thái, khoảng ngày, điểm và sự cố.
- Xem snapshot đề, timeline, câu trả lời, điểm và lịch sử thay đổi.
- Chấm câu cần giám khảo, xem gợi ý AI nhưng không tự coi AI là kết quả cuối.
- Duyệt/bác báo cáo, moderation và xử lý attempt theo policy.
- Theo dõi timeout, submit pending, bất thường lưu đáp án và lỗi media.

## Yêu cầu giao diện

Admin theo AGENTS.md; bảng lượt làm bài và màn chi tiết là trung tâm. Hiển thị rõ mode Practice/Exam, nguồn bài, trạng thái, mốc thời gian, điểm và phần cần xử lý. Câu trả lời thủ công có vùng rubric, nhập điểm/lời nhận xét và preview lịch sử. Action nhạy cảm có xác nhận, lý do và liên kết audit; không dùng màu là dấu hiệu duy nhất của trạng thái.

## Validation

- Attempt/answer thuộc đúng user, exam snapshot và trạng thái cho phép.
- Điểm nằm trong thang điểm của câu; điểm tối đa và rubric được xác định phía server.
- Chỉ chấm câu cần chấm tay; khóa sửa sau khi khóa sổ hoặc theo permission override.
- Moderation/state transition theo danh sách trạng thái hợp lệ; không đổi attempt submitted thành active.
- Lý do xử lý bắt buộc cho action điều chỉnh/hủy.

## CRUD và vòng đời

Không CRUD attempt theo nghĩa tạo/xóa thủ công. Cung cấp query và command có kiểm soát: grade, moderate, cancel/flag nếu được policy cho phép. Không xóa lịch sử attempt; corrections tạo revision/audit trail.

## Quyền và audit

Dùng results.view, results.grade, reports.review và quyền publish/reset liên quan nếu có. Audit grading, AI assistance accept/reject, moderation và chỉnh điểm.

## Kế hoạch test

- Feature: lọc/danh sách/chi tiết chỉ hiển thị attempt thuộc quyền.
- Grading: điểm biên 0/tối đa, ngoài phạm vi, câu không cần chấm tay, sửa lại có audit.
- Moderation: trạng thái hợp lệ, trạng thái đã kết thúc, thiếu lý do và request lặp.
- Authorization: viewer không grade; user không thể truy cập attempt của người khác.
- Domain unit: tính điểm, trạng thái và rule khóa sửa.
- Regression: thao tác Admin không sửa snapshot/câu trả lời gốc ngoài phần được phép.
- Resilience: grading lỗi giữa transaction không để điểm nửa chừng.

## Tiêu chí nghiệm thu và phụ thuộc

Attempt xem được đầy đủ lịch sử và được điều chỉnh có dấu vết. Phụ thuộc shared Attempts, mode policies, Scoring, Grading, Audit và Analytics.
