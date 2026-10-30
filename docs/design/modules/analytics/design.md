# Design: Phân tích và thống kê

> Proposal. Căn cứ chung: docs/design/modules/README.md và AGENTS.md.

## Mục tiêu

Giúp Admin trả lời câu hỏi về kết quả và tiến độ qua chỉ số có định nghĩa, bộ lọc rõ và báo cáo tải được.

## Bố cục

- Heading ngắn; bộ lọc khoảng ngày, kỳ thi, mode Practice/Exam, skill và trạng thái đặt ngay đầu vùng chức năng.
- Dải KPI nhỏ chỉ gồm chỉ số phục vụ câu hỏi đang xem; bên dưới là biểu đồ xu hướng và bảng breakdown.
- Bảng breakdown có drill-down tới attempt khi được quyền.
- Nút Xuất CSV và Xuất XLSX cạnh bộ lọc/bảng; menu thể hiện export theo filter hiện tại.
- Phần chú giải nêu công thức, timezone, thời điểm cập nhật và trạng thái đã loại trừ.

## Hành vi và trạng thái

- Đổi mode Practice/Exam cập nhật KPI, chart, bảng và báo cáo cùng phạm vi.
- Loading không nhảy layout; không có dữ liệu hiển thị vì sao và gợi ý đổi filter.
- Export hiển thị đang tạo/đã tải/lỗi, tên file và filter áp dụng.
- Leaderboard reset là action tách biệt, có xác nhận, không đặt cạnh Export.

## Responsive và accessibility

Charts có bảng thay thế và text summary; trục có nhãn/đơn vị; không dùng màu duy nhất để phân biệt mode. Trên mobile KPI xếp một cột và bảng có cột ưu tiên.

## Tiêu chí review

- CSV/XLSX đúng filter, timezone và cột trên bảng.
- Practice không bị cộng nhầm vào chỉ số/leaderboard Exam.
- Nội dung không có hero hoặc biểu đồ chỉ để trang trí.
