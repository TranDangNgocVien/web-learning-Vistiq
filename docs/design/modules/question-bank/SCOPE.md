# Phạm vi module — Ngân hàng câu hỏi

Tài liệu thiết kế sản phẩm nằm tại `design.md` cùng thư mục. Phạm vi dưới đây là ranh giới file được phép sửa khi làm độc lập.

## Đường dẫn sở hữu

- `app/Admin/Http/Controllers/Admin/Assessment/QuestionBank/`
- `app/Admin/Http/Requests/Admin/Assessment/QuestionBank/`
- `app/Domain/Services/Assessment/QuestionBank/`
- `resources/views/pages/admin/assessment/question-bank/`
- `routes/web/admin/assessment/question-bank.php`
- `tests/Admin/Feature/QuestionBank/`

Chỉ tạo file feature mới trong các đường dẫn trên. Nếu cần thay đổi hợp đồng hoặc file dùng chung, mở yêu cầu tích hợp thay vì sửa trực tiếp.

## Quyền truy cập

`content.view` cho đọc/export; `content.edit` cho CRUD/import/media/stimulus/group/bulk. File import phải authorize cả preview lẫn commit. Clone/xóa revision dùng chung và gửi duyệt/publish/review transitions thuộc ContentApproval.

Mỗi trang và mutation Admin phải được bảo vệ phía server bằng middleware/Gate/Policy. Khi cần permission mới, gửi mã quyền, nhãn, mô tả, actions được bảo vệ và role mặc định cho integration owner để cập nhật catalog, seeder và màn hình gán quyền.

## Không sửa song song

- Các module khác trong `app/`, `routes/`, `resources/views/`, `tests/` và `docs/design/modules/`.
- Shared models, migrations, providers, middleware registration, Composer/autoload, route loaders, layout/component dùng chung, asset dùng chung, `config/admin_permissions.php`, permission seeder và `AGENTS.md`.
- Nếu thay đổi cần chạm nhiều module, mô tả contract cần thiết và thống nhất với integration owner trước khi sửa.
