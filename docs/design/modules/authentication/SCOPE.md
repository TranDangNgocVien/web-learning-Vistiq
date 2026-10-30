# Phạm vi module — Authentication

Tài liệu thiết kế sản phẩm nằm tại `design.md` cùng thư mục. Phạm vi dưới đây là ranh giới file được phép sửa khi làm độc lập.

## Đường dẫn sở hữu

- `app/Admin/Http/Controllers/Admin/Identity/Authentication/`
- `app/Admin/Http/Requests/Admin/Identity/Authentication/`
- `app/Learner/Http/Controllers/Authentication/`
- `app/Learner/Http/Requests/Authentication/`
- `resources/views/pages/admin/identity/authentication/`
- `resources/views/pages/learner/authentication/`
- `routes/web/admin/identity/authentication.php`
- `routes/web/learner/authentication.php`
- `tests/Admin/Feature/Authentication/`
- `tests/Learner/Feature/Authentication/`

Chỉ tạo file feature mới trong các đường dẫn trên. Nếu cần thay đổi hợp đồng hoặc file dùng chung, mở yêu cầu tích hợp thay vì sửa trực tiếp.

## Quyền truy cập

Sở hữu luồng guest/authenticated, guard và OTP theo actor; giữ throttle, active-account check và xác minh OTP của Admin. Không sửa đăng ký hoặc session của actor còn lại.

Mỗi trang và mutation Admin phải được bảo vệ phía server bằng middleware/Gate/Policy. Khi cần permission mới, gửi mã quyền, nhãn, mô tả, actions được bảo vệ và role mặc định cho integration owner để cập nhật catalog, seeder và màn hình gán quyền.

## Không sửa song song

- Các module khác trong `app/`, `routes/`, `resources/views/`, `tests/` và `docs/design/modules/`.
- Shared models, migrations, providers, middleware registration, Composer/autoload, route loaders, layout/component dùng chung, asset dùng chung, `config/admin_permissions.php`, permission seeder và `AGENTS.md`.
- Nếu thay đổi cần chạm nhiều module, mô tả contract cần thiết và thống nhất với integration owner trước khi sửa.
