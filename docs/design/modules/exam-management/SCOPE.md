# Phạm vi module — Quản lý đề thi

Tài liệu thiết kế sản phẩm nằm tại `design.md` cùng thư mục. Phạm vi dưới đây là ranh giới file được phép sửa khi làm độc lập.

## Đường dẫn sở hữu

- `app/Admin/Http/Controllers/Admin/Assessment/Exams/`
- `app/Admin/Http/Requests/Admin/Assessment/Exams/`
- `app/Domain/Services/Assessment/Exams/`
- `resources/views/pages/admin/assessment/exams/`
- `routes/web/admin/assessment/exams.php`
- `tests/Admin/Feature/ExamManagement/`

Chỉ tạo file feature mới trong các đường dẫn trên. Nếu cần thay đổi hợp đồng hoặc file dùng chung, mở yêu cầu tích hợp thay vì sửa trực tiếp.

## Quyền truy cập

`content.view` cho xem đề; `exams.edit` và `content.edit` cho cấu hình/biên tập. Gửi duyệt, approve/reject và archive đi qua ContentApproval; publish được kiểm tra bởi ExamPublishingService. Không xem việc ẩn nút là authorization.

Mỗi trang và mutation Admin phải được bảo vệ phía server bằng middleware/Gate/Policy. Khi cần permission mới, gửi mã quyền, nhãn, mô tả, actions được bảo vệ và role mặc định cho integration owner để cập nhật catalog, seeder và màn hình gán quyền.

## Không sửa song song

- Các module khác trong `app/`, `routes/`, `resources/views/`, `tests/` và `docs/design/modules/`.
- Shared models, migrations, providers, middleware registration, Composer/autoload, route loaders, layout/component dùng chung, asset dùng chung, `config/admin_permissions.php`, permission seeder và `AGENTS.md`.
- Nếu thay đổi cần chạm nhiều module, mô tả contract cần thiết và thống nhất với integration owner trước khi sửa.
