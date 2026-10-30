# Phạm vi module — Quản lý bài tập theo kỹ năng

Tài liệu thiết kế sản phẩm nằm tại `design.md` cùng thư mục. Phạm vi dưới đây là ranh giới file được phép sửa khi làm độc lập.

## Đường dẫn sở hữu

- `app/Admin/Http/Controllers/Admin/Learning/SkillExercises/`
- `app/Admin/Http/Requests/Admin/Learning/SkillExercises/`
- `app/Domain/Services/Learning/SkillExercises/`
- `resources/views/pages/admin/learning/skill-exercises/`
- `routes/web/admin/learning/skill-exercises.php`
- `tests/Admin/Feature/SkillExerciseManagement/`

Chỉ tạo file feature mới trong các đường dẫn trên. Nếu cần thay đổi hợp đồng hoặc file dùng chung, mở yêu cầu tích hợp thay vì sửa trực tiếp.

## Quyền truy cập

`content.view` cho đọc; `content.edit` cho tạo/sửa bộ bài và liên kết câu hỏi; `content.publish` cho phát hành. Bộ bài tham chiếu câu hỏi từ Question Bank, không sao chép bản ghi câu hỏi.

Mỗi trang và mutation Admin phải được bảo vệ phía server bằng middleware/Gate/Policy. Khi cần permission mới, gửi mã quyền, nhãn, mô tả, actions được bảo vệ và role mặc định cho integration owner để cập nhật catalog, seeder và màn hình gán quyền.

## Không sửa song song

- Các module khác trong `app/`, `routes/`, `resources/views/`, `tests/` và `docs/design/modules/`.
- Shared models, migrations, providers, middleware registration, Composer/autoload, route loaders, layout/component dùng chung, asset dùng chung, `config/admin_permissions.php`, permission seeder và `AGENTS.md`.
- Nếu thay đổi cần chạm nhiều module, mô tả contract cần thiết và thống nhất với integration owner trước khi sửa.
