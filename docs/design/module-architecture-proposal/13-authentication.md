# Module: Authentication

> Trạng thái hiện tại: Đã có AuthController cho learner/admin, login/register, password reset và OTP Admin; đặc tả login/register hiện hành nằm trong docs/design/design.md.

## Mục tiêu và ranh giới

Xác thực learner và admin với guard/session riêng; đăng ký learner, đăng nhập, đăng xuất, quên/đặt lại mật khẩu và xác minh OTP Admin. Authorization/role assignment thuộc Users and Permissions.

## Chức năng cần có

- Đăng ký, đăng nhập, đăng xuất và bảo vệ session.
- Quên mật khẩu, gửi link và đặt lại mật khẩu.
- OTP/xác minh bổ sung cho Admin; gửi lại theo throttle.
- Kiểm tra account active/disabled trước khi cấp quyền truy cập.
- Giữ CSRF, remember-me, autocomplete và password manager hoạt động.

## Yêu cầu giao diện

### Login/register

Tuân thủ nguyên văn phạm vi docs/design/design.md: khung hai cột desktop khoảng 44/56, nền/tranh chuyển ngày-đêm, nút spotlight duy nhất cho password, Be Vietnam Pro, responsive một cột từ 768px trở xuống, hỗ trợ keyboard/touch và reduced motion. Giữ input password thật là type=password khi spotlight bật; fallback không JavaScript vẫn nhập/gửi được. Không thay routes, field name, validation hay autocomplete. Tiêu chí viewport gồm 375, 768, 1024 và 1440px.

### Admin auth và password recovery

design.md hiện nói giao diện spotlight chỉ dành cho login/register và giữ password reset/admin auth hiện có. Vì vậy proposal không áp dụng spotlight cho Admin, forgot-password hoặc reset-password. Các màn đó chỉ được thay đổi nếu phạm vi design được duyệt riêng.

## Validation

- Email chuẩn hóa; password theo policy thống nhất, xác nhận khớp nơi có form xác nhận.
- Rate limit login/register/reset/OTP; phản hồi không làm lộ email có tài khoản hay không.
- Reset token/OTP hợp lệ, chưa hết hạn, dùng một lần; OTP theo đúng độ dài/định dạng cấu hình.
- Regenerate session sau login; invalidate session khi logout/password reset hoặc account bị khóa theo policy.
- Guard admin và learner không dùng lẫn; account inactive bị chặn.
- Tôn trọng CSRF, password hash, remember-me và password manager.

## CRUD và vòng đời

Không phải module CRUD chung. Create account qua registration theo chính sách; login/logout/reset/verify là command. Việc xóa/anonymize account được chốt ở Users/Profile/privacy policy.

## Quyền và audit

Guard authentication theo actor; sau xác thực, role permission do module Users and Permissions kiểm tra. Ghi sự kiện đăng nhập thất bại/thành công, reset, verify và lockout theo chính sách; không ghi password, OTP, reset token hoặc full secret.

## Kế hoạch test

- Feature: learner/admin login, register, logout, forgot/reset, admin OTP và throttle.
- Isolation: learner credential không vào admin guard; admin login không bị route learner coi là learner.
- Security: session fixation, CSRF, reset token hết hạn/dùng lại, account inactive và thông báo không enumerate email.
- Validation: email/password/OTP sai và rate limit.
- UI/browser theo design.md: ngày/đêm, một nút password, spotlight geometry, touch/keyboard, Escape, blur, tab hidden, submit cleanup, autofill/paste/scroll, reduced motion, không tràn ở 4 viewport.
- Progressive enhancement: JavaScript tắt vẫn gửi được password input; lớp chữ rõ không tồn tại khi spotlight chưa khởi tạo.
- Regression: route, field name, CSRF, autocomplete, remember-me và reset/admin UI hiện hành.

## Tiêu chí nghiệm thu và phụ thuộc

Auth hoạt động độc lập giữa hai actor, không làm lộ thông tin tài khoản và giữ nguyên backend contract. Mọi chi tiết thị giác/spotlight login-register đối chiếu trực tiếp docs/design/design.md; các biến thể ngoài phạm vi cần đặc tả riêng. Phụ thuộc Users, mail delivery, session/cache và middleware active account.
