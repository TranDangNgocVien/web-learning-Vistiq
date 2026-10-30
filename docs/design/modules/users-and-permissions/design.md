# Design: Người dùng và phân quyền

> Proposal. Căn cứ chung: docs/design/modules/README.md và AGENTS.md.

## Mục tiêu

Giúp quản trị viên hiểu ai đang được cấp quyền gì và hậu quả trước khi thay đổi role.

## Bố cục

- Trang Users: heading, search/filter trạng thái/role, bảng tên/email/trạng thái/role/last active và thao tác xem/khóa.
- Trang Role: danh sách role ở cột trái; chi tiết mô tả, member count và permission assignment ở vùng chính.
- Permission hiển thị theo nhóm với checkbox gán/bỏ; code và tên permission là catalog chỉ đọc, không có nút thêm/sửa/xóa permission.
- User detail chia thông tin tài khoản, role, attempt gần đây và hoạt động; section dữ liệu nhạy cảm chỉ hiện khi có quyền.

## Xác nhận xóa role

Khi role có N người dùng, dialog phải nêu tên role, đúng số N, ảnh hưởng gỡ role khỏi toàn bộ thành viên và danh sách/nhóm quyền bị thu hồi. Nội dung nói rõ quyền vẫn còn nếu một role khác tiếp tục cấp quyền đó. Có hai lựa chọn rõ: Giữ role / Xóa role và gỡ N thành viên. Không dùng nút “OK” chung chung.

## Hành vi và trạng thái

- Permission save có trạng thái đang lưu/đã lưu/lỗi và không mất các lựa chọn chưa lưu.
- Role hệ thống/role bảo vệ Super Admin được đánh dấu và khóa action không hợp lệ.
- Xóa bị hủy không thay đổi assignment; lỗi server giữ dữ liệu form và giải thích.
- Bảng rỗng có hướng dẫn tạo user/role phù hợp với quyền hiện tại.

## Responsive và accessibility

Danh sách role thành select trên mobile; permission groups có headings và checkbox labels. Dialog có focus trap, Escape khi an toàn, focus trả về nút gọi; cảnh báo không chỉ biểu thị bằng màu.

## Tiêu chí review

- Không có giao diện CRUD permission.
- Dialog nêu số người bị ảnh hưởng trước khi xóa.
- Giao diện không hứa người dùng mất toàn bộ quyền nếu họ còn role khác.
