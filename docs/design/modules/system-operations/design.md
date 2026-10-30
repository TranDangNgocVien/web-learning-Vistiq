# Design: Log và cài đặt hệ thống

> Proposal. Căn cứ chung: docs/design/modules/README.md và AGENTS.md.

## Mục tiêu

Admin có thể thay đổi cấu hình cho phép, tra cứu audit và thao tác backup/restore mà hiểu rõ kết quả và rủi ro.

## Bố cục

- Điều hướng tabs rõ: Cài đặt, Audit log, Backup/restore.
- Cài đặt: danh sách nhóm bên trái hoặc filter; form nhóm ở vùng chính; mỗi field có mô tả, kiểu, giá trị hiện tại và Save.
- Audit: bộ lọc actor/action/entity/date và bảng thời gian, người thực hiện, hành động, đối tượng, kết quả; xem diff đã che dữ liệu nhạy cảm.
- Backup: danh sách/tạo backup; Restore tách thành flow xác minh/preflight/xác nhận.

## Hành vi và trạng thái

- Audit là read-only, không có edit/delete.
- Lưu settings xác nhận nhóm nào đổi; lỗi giữ giá trị nhập.
- Restore có tên nguồn, thời điểm backup, kiểm tra hợp lệ, ảnh hưởng và bước xác nhận tách biệt; trạng thái job có thể theo dõi.
- Secret chỉ hiện trạng thái đã cấu hình, không render giá trị.

## Responsive và accessibility

Settings form một cột trên mobile; bảng audit có ưu tiên cột. Confirm destructive action có focus trap và nút Cancel nổi bật; aria-live cho tiến độ job.

## Tiêu chí review

- Không có giao diện sửa/xóa log.
- Restore không thể kích hoạt nhầm từ form thông thường.
- Secret không xuất hiện trong label, diff hoặc thông báo lỗi.
