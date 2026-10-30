# Design: Quản lý lượt làm bài

> Proposal. Căn cứ chung: docs/design/modules/README.md và AGENTS.md.

## Mục tiêu

Admin giám sát mọi attempt Practice/Exam trong một nơi, nhận ra lượt cần chấm hoặc xử lý và xem lịch sử đầy đủ.

## Bố cục

- Heading “Lượt làm bài”, ngay dưới là tabs/filter mode: Tất cả, Practice, Exam; thêm trạng thái, đề/bộ bài, user, ngày và điểm.
- Bảng chính hiển thị mode, người làm, nguồn bài, started/submitted, trạng thái, điểm, cờ review và thao tác.
- Chọn một hàng mở chi tiết bên phải hoặc trang riêng: attempt summary, timeline, snapshot câu hỏi và câu trả lời.
- Khu vực grading hiển thị câu cần chấm, rubric, điểm tối đa, feedback, lịch sử người chấm và nút lưu.
- Moderation/report nằm thành khu vực riêng, có lý do, trạng thái và action được phép.

## Hành vi và trạng thái

- Mode Practice/Exam hiển thị bằng text badge; filter lưu trong query string.
- Câu chờ chấm, đã chấm, attempt hết giờ, nộp dở và có báo cáo có cách phân biệt rõ.
- Chỉnh điểm/moderate yêu cầu lý do và confirm; sau lưu thể hiện audit/revision.
- Không có thao tác xóa attempt. Reset leaderboard được đặt ngoài action thường ngày.

## Responsive và accessibility

Bảng hỗ trợ cột ưu tiên ở mobile và filter drawer. Grading dùng keyboard, label điểm tối đa, error summary và giữ input khi lỗi. Timeline có thứ tự thời gian đọc được bằng screen reader.

## Tiêu chí review

- Practice và Exam xuất hiện cùng danh sách, cùng schema detail; chỉ mode/policy khác.
- Snapshot câu và đáp án learner nhìn thấy khớp thời điểm bắt đầu.
- Không có trạng thái hoặc điểm chỉ mã hóa bằng màu.
