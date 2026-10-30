# Module: Profile

> Trạng thái hiện tại: Đã có trang hồ sơ và lịch sử; luồng chỉnh sửa dữ liệu cá nhân cần xác nhận.

## Mục tiêu và ranh giới

Learner xem và cập nhật thông tin cá nhân được phép, mục tiêu học và một số tùy chọn cá nhân. Credentials và khôi phục tài khoản thuộc Authentication; phân quyền người dùng thuộc Admin Identity.

## Chức năng cần có

- Xem/cập nhật tên hiển thị, ảnh đại diện và trường profile được duyệt.
- Xem lịch sử làm bài/luyện tập, tiến độ và nội dung đã lưu.
- Quản lý mục tiêu học/tùy chọn thông báo nếu có.
- Có đường dẫn tới đổi mật khẩu/đăng xuất do Authentication xử lý.
- Cho phép yêu cầu xóa/ẩn danh tài khoản nếu policy sản phẩm được duyệt.

## Yêu cầu giao diện

Learner dùng layout profile hiện có, chia rõ thông tin tài khoản và tiến độ. Form chỉ có field người dùng có thể sửa; hiển thị lỗi gần field, xác nhận lưu và không đổi trạng thái khi server báo lỗi. Lịch sử có filter/phân trang, empty state hữu ích, responsive và accessible.

## Validation

- User phải đăng nhập và chỉ sửa profile của chính mình.
- Name/avatar/goal theo độ dài và định dạng cho phép; upload kiểm tra MIME, kích thước, nội dung thật.
- Email/credential không cập nhật qua endpoint profile.
- Không nhận role, status, permission, subscription plan hoặc user ID từ payload để ghi thẳng.
- Lịch sử chỉ trả bản ghi thuộc user, phân trang có giới hạn.

## CRUD và vòng đời

Read/update profile; create/update preferences và saved items nếu được bật. Không cho learner hard delete attempt hoặc knowledge item. Đổi email/password đi qua Authentication với xác minh riêng.

## Quyền và audit

Owner policy cho mọi dữ liệu cá nhân. Audit thay đổi profile nhạy cảm và yêu cầu xóa; không log giá trị bí mật.

## Kế hoạch test

- Feature: xem/cập nhật profile, history và saved item.
- Authorization: user A không đọc/sửa dữ liệu user B.
- Validation: field ngoài allowlist, avatar sai MIME/kích thước và string vượt giới hạn.
- Security: mass assignment không đổi role/status/email/password.
- Regression: lỗi lưu giữ nguyên giá trị cũ và hiển thị field error.
- Privacy: chỉ trả dữ liệu của owner; archive/anonymize không phá foreign keys.
- Browser: keyboard, file upload, focus/error summary và responsive.

## Tiêu chí nghiệm thu và phụ thuộc

Profile chỉ chứa thông tin do learner kiểm soát; không có đường vòng thay credentials hoặc quyền. Phụ thuộc Auth, Attempts, SkillPractice, saved items và policy retention.
