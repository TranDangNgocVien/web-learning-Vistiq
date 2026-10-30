# Module: Quản lý người dùng và phân quyền

> Trạng thái hiện tại: Đã có danh sách/chi tiết người dùng, action và cập nhật permission role; proposal chốt CRUD role nhưng permission catalog không CRUD.

## Mục tiêu và ranh giới

Admin tìm và quản lý tài khoản, vai trò, permission và trạng thái truy cập. Learner tự sửa thông tin cá nhân qua Profile; thông tin xác thực thuộc Authentication.

## Hiện trạng trong repo

UserManagementController xử lý danh sách, chi tiết, action và cập nhật role; models và policies cho User/Role/Permission đã có. Cần xác nhận mức CRUD đầy đủ của role/permission.

## Chức năng cần có

- Tìm/lọc người dùng theo tên, email, trạng thái, role và ngày tạo.
- Xem hồ sơ vận hành, lịch sử attempt, hoạt động và session theo quyền.
- Khóa/mở khóa, kích hoạt/vô hiệu hóa hoặc áp dụng action có kiểm toán.
- CRUD role và gán/bỏ các permission đã được định nghĩa sẵn.
- Xem permission catalog ở chế độ chỉ đọc; không tạo, sửa hay xóa permission từ giao diện.
- Khi xóa role đang gán cho N người dùng, xác nhận sẽ gỡ role đó khỏi toàn bộ N người. Hiển thị rõ các quyền của role sẽ mất; người dùng chỉ còn truy cập nếu role khác vẫn cấp quyền tương ứng.
- Bảo vệ tài khoản Super Admin cuối cùng và cấp quyền theo nguyên tắc tối thiểu.

## Yêu cầu giao diện

Admin theo AGENTS.md: bảng người dùng và form role là trọng tâm. Chi tiết người dùng chia khu vực thông tin, quyền và hoạt động; dữ liệu riêng tư chỉ hiện khi đủ quyền. Action khóa/vô hiệu hóa có confirm và trạng thái kết quả. Role editor thể hiện permission theo nhóm, có mô tả tác động và lựa chọn tất cả/bỏ tất cả có thể truy cập bằng keyboard. Màn xác nhận xóa role nêu tên role, số N người bị ảnh hưởng và các chức năng mất quyền; thao tác phải yêu cầu xác nhận tường minh.

## Validation

- Email chuẩn hóa và duy nhất; role/permission phải tồn tại.
- Chỉ cho phép thay đổi field thuộc module; không nhận trực tiếp role/status qua mass assignment.
- Không tự khóa tài khoản hiện tại hoặc Super Admin cuối cùng nếu làm mất khả năng quản trị.
- Tôn trọng trạng thái account và policy trước khi thu hồi session hoặc cho phép login.
- Permission chỉ được chọn từ catalog hệ thống; không nhận permission code mới từ request.
- Khi xóa role, xóa các liên kết role-user trong transaction, giữ lại các role khác của từng user và ghi audit số user/quyền bị thu hồi.
- Chặn xóa role hệ thống hoặc role cuối cùng bảo đảm còn Super Admin; kiểm tra quyền ở server.
- Không hiển thị password, token, OTP hoặc thông tin bí mật trong audit/detail.

## CRUD và vòng đời

User: read và action trạng thái; profile fields do Profile quản lý. Không hard delete user có attempt/audit; ưu tiên deactivate/anonymize theo retention policy. Role: CRUD. Permission catalog: read-only; chỉ CRUD mối gán permission-role. Xóa role được phép sau xác nhận và gỡ toàn bộ liên kết user-role; permission tương ứng sẽ bị thu hồi nếu không có role khác cấp.

## Quyền và audit

Permission hiện có users.manage, roles.manage. Audit mọi thay đổi trạng thái, role, permission-role assignment và thao tác nhạy cảm; ghi giá trị trước/sau đã loại dữ liệu bí mật.

## Kế hoạch test

- Feature: filter, detail và action account theo quyền.
- Feature: CRUD role và gán/bỏ permission đã định nghĩa.
- Role deletion: confirmation hiển thị đúng N người; hủy không đổi dữ liệu; xác nhận gỡ role khỏi toàn bộ người dùng trong một transaction.
- Authorization: sau xóa role, quyền chỉ mất nếu không còn role khác cấp; Super Admin cuối cùng không thể bị loại khỏi hệ thống.
- Permission catalog: không có endpoint/UI create/update/delete permission; request giả mạo bị từ chối.
- Authorization: không truy cập chéo admin/learner; request thiếu permission bị 403.
- Validation: email trùng, role/permission giả, cố vô hiệu hóa Super Admin cuối cùng.
- Security: không lộ secret/session token; CSRF và mass assignment được kiểm soát.
- Audit: actor, đối tượng, diff và thời gian được ghi sau action thành công; không ghi action thất bại như thành công.
- Regression: account inactive không đăng nhập hoặc bắt đầu attempt.

## Tiêu chí nghiệm thu và phụ thuộc

Phân quyền được áp dụng tại backend cho mọi thao tác. Role có thể CRUD; permission catalog chỉ đọc và gán. Xóa role có người dùng luôn yêu cầu xác nhận tường minh và audit tác động.
