# Phạm vi module — Luyện theo kỹ năng

Tài liệu thiết kế sản phẩm nằm tại `design.md` cùng thư mục. Phạm vi dưới đây là ranh giới file được phép sửa khi làm độc lập.

## Đường dẫn sở hữu

- `app/Learner/Http/Controllers/Practice/SkillPractice/`
- `app/Learner/Http/Requests/Practice/SkillPractice/`
- `resources/views/pages/learner/practice/skill-practice/`
- `routes/web/learner/practice/skill-practice.php`
- `tests/Learner/Feature/SkillPractice/`

Chỉ tạo file feature mới trong các đường dẫn trên. Nếu cần thay đổi hợp đồng hoặc file dùng chung, mở yêu cầu tích hợp thay vì sửa trực tiếp.

## Quyền truy cập

Entry/catalog phải chỉ mở bài tập đã công bố. Khi bắt đầu luyện, tạo attempt mode Practice qua hợp đồng attempt dùng chung và kiểm tra trạng thái learner.

Mỗi trang và mutation Admin phải được bảo vệ phía server bằng middleware/Gate/Policy. Khi cần permission mới, gửi mã quyền, nhãn, mô tả, actions được bảo vệ và role mặc định cho integration owner để cập nhật catalog, seeder và màn hình gán quyền.

## Không sửa song song

- Các module khác trong `app/`, `routes/`, `resources/views/`, `tests/` và `docs/design/modules/`.
- Shared models, migrations, providers, middleware registration, Composer/autoload, route loaders, layout/component dùng chung, asset dùng chung, `config/admin_permissions.php`, permission seeder và `AGENTS.md`.
- Nếu thay đổi cần chạm nhiều module, mô tả contract cần thiết và thống nhất với integration owner trước khi sửa.
