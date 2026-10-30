# Design: Profile

> Proposal. Căn cứ chung: docs/design/modules/README.md; dùng visual shell Learner hiện có.

## Mục tiêu

Cho learner quản lý thông tin cá nhân và theo dõi hoạt động học trong cùng một khu vực.

## Bố cục

- Header profile gọn với tên, trạng thái tài khoản và avatar.
- Tabs/section: Thông tin cá nhân, Lịch sử làm bài, Lịch sử luyện tập, Mục đã lưu, Tùy chọn (nếu có).
- Form profile chỉ chứa trường learner được phép sửa; đổi password/email điều hướng sang Authentication.
- Lịch sử có mode Practice/Exam, nguồn bài, ngày, kết quả và link mở review hợp lệ.

## Hành vi và trạng thái

- Lưu thành công có phản hồi; server validation giữ lại dữ liệu chưa hợp lệ.
- Upload avatar có preview, giới hạn file và action bỏ ảnh.
- Empty history/saved state hướng dẫn hoạt động tiếp theo.
- Yêu cầu xóa/ẩn danh nếu triển khai phải là flow riêng, không lẫn với lưu profile.

## Responsive và accessibility

Trên mobile chuyển tabs thành menu chọn có label. Form có label/error summary; avatar có alternative text; lịch sử phân trang và có keyboard focus.

## Tiêu chí review

- Profile không có control đổi role/permission/status.
- Lịch sử chỉ hiển thị attempt của tài khoản hiện tại.
- Profile và auth credentials có ranh giới rõ.
