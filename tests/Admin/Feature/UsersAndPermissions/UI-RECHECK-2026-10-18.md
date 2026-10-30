# Rà lại UI và chức năng Danh — 18/10/2026

## Thay đổi

- Bỏ các mã quyền và mã vai trò hiển thị phụ khỏi trang vai trò và gán vai trò trong hồ sơ. Giữ nguyên ID/code dùng cho backend; mã tạo vai trò vẫn là trường nhập cần thiết.
- Thẻ quyền dùng nền trắng, viền và dấu nhấn xanh khi được chọn. Mô tả dùng màu chữ rõ hơn; giữ nhãn và checkbox để không chỉ dựa vào màu.
- Phân trang Người dùng dùng nút chung của Admin: trang hiện tại chữ trắng trên nền xanh, trang khác chữ đậm trên nền trắng. Mobile hiển thị số trang hiện tại cùng Trước/Sau.
- Sắp xếp người dùng theo created_at rồi ID giảm dần để phân trang ổn định khi nhiều tài khoản có cùng thời điểm tạo.
- Chỉ chỉnh scope Users & Permissions của Danh. Ảnh phân trang chứa content.save/question thuộc trang Audit của System Operations (Khoa); không sửa trang đó hoặc shared CSS/layout.

## Kết quả test backend

**45/45 PASS — 247 assertions.** SQLite RAM, dữ liệu giả, không thay database ứng dụng. Dùng mailer array và loại các bài kiểm tra email/reset theo yêu cầu.

```powershell
$env:MAIL_MAILER = 'array'
php artisan test tests/Admin/Feature/UsersAndPermissions tests/Admin/Feature/Authentication tests/Learner/Feature/Authentication --filter='^(?!.*(?:EmailRecoveryTest|PasswordResetStabilityAuditTest|forgot_password|reset_password|password_recovery|password_reset)).*$'
```

Đã kiểm tra:

- Danh sách/chi tiết người dùng, keyword/status/role và biên ngày tạo; phân trang giữ filter, trang 1 và 2 không lặp người dùng.
- Khóa/mở khóa, thu hồi phiên, thay vai trò, audit và bảo vệ Super Admin cuối cùng.
- CRUD role; lưu/gỡ quyền, bao gồm bỏ chọn toàn bộ; validation dữ liệu sai kiểu/catalog và bảo vệ role hệ thống.
- Truy cập URL trực tiếp khi thiếu quyền: content-admin không xem/thay tài khoản; người chỉ có users.manage không tạo/sửa/xóa role hoặc gán vai trò. Dữ liệu và session_version được bảo toàn khi request bị chặn.
- Login/register/logout, validation, email chuẩn hóa khi đăng nhập, remember-me, throttle đăng nhập, tài khoản bị khóa và tách cổng learner/Admin.
- OTP bằng challenge giả trong database: bật/tắt bảo vệ, đúng/sai/hết hạn, giới hạn số lần thử và chỉ dùng một lần. Không gửi hoặc test email OTP thực.

## Kiểm tra trên browser

- Users, Roles, User detail với preview dữ liệu giả; Login, Register và Admin Login trên localhost.
- Cả 6 màn không tràn nội dung ngang ở 375, 768, 1024, 1440px. Bảng người dùng được cuộn ngang trong vùng bảng ở màn hình hẹp.
- Không còn phần tử code hiển thị trên 3 màn quản trị.
- Chọn tất cả/Bỏ chọn cập nhật checkbox và bộ đếm; khi giảm quyền có cảnh báo đúng quyền và số thành viên, hủy không gửi thay đổi.
- Phân trang: trang hiện tại rgb(11,102,239), chữ trắng; trang khác nền trắng, chữ rgb(73,97,129); focus bàn phím nhìn rõ.
- Spotlight login: bật/tắt và Escape hoạt động, input thật vẫn type=password. Register chuyển nguồn sáng giữa 2 ô mật khẩu và Escape tắt được.
- Browser kiểm tra giao diện và thao tác không commit; các mutation được xác minh qua feature test cô lập. Không tạo/sửa/xóa tài khoản thật trong đợt này.

Pint và git diff --check đều pass. Preview tạm đã được dọn. Không chạy lại email theo yêu cầu; chưa thực hiện kiểm tra thiết bị cảm ứng thật hay screen reader trong lượt này.
