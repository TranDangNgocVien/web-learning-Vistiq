# Phạm vi module — Duyệt câu hỏi và đề thi

Tài liệu thiết kế sản phẩm nằm tại `design.md` cùng thư mục. Phạm vi dưới đây là ranh giới file được phép sửa khi làm độc lập.

## Đường dẫn sở hữu

- `app/Admin/Http/Controllers/Admin/Assessment/ContentApproval/`
- `app/Admin/Http/Requests/Admin/Assessment/ContentApproval/`
- `app/Domain/Services/Assessment/ContentApproval/`
- `resources/views/pages/admin/assessment/content-approval/`
- `routes/web/admin/assessment/content-approval.php`
- `tests/Admin/Feature/ContentApproval/`

Chỉ tạo file feature mới trong các đường dẫn trên. Nếu cần thay đổi hợp đồng hoặc file dùng chung, mở yêu cầu tích hợp thay vì sửa trực tiếp.

## Quyền truy cập

`content.review` cho approve/reject; `content.edit` cho gửi đi duyệt; `content.publish` cho archive/publish transitions. Module này sở hữu quyết định duyệt câu hỏi và đề thi, còn QuestionBank/ExamManagement sở hữu CRUD và service phát hành nội dung. Lưu actor, quyết định, lý do và revision trong audit.

Mỗi trang và mutation Admin phải được bảo vệ phía server bằng middleware/Gate/Policy. Khi cần permission mới, gửi mã quyền, nhãn, mô tả, actions được bảo vệ và role mặc định cho integration owner để cập nhật catalog, seeder và màn hình gán quyền.

## Không sửa song song

- Các module khác trong `app/`, `routes/`, `resources/views/`, `tests/` và `docs/design/modules/`.
- Shared models, migrations, providers, middleware registration, Composer/autoload, route loaders, layout/component dùng chung, asset dùng chung, `config/admin_permissions.php`, permission seeder và `AGENTS.md`.
- Nếu thay đổi cần chạm nhiều module, mô tả contract cần thiết và thống nhất với integration owner trước khi sửa.
