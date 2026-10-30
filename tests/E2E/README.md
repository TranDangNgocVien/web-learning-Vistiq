# Kiểm thử tích hợp bằng browser và MySQL

Chạy từ thư mục gốc Vistiq sau `composer install`, `npm ci`, `npm run build`. PHP cần SQLite, PDO MySQL và Zip. Python 3.10+ cần các package trong `requirements.txt`.

```powershell
python -m venv storage/framework/e2e-venv
storage/framework/e2e-venv/Scripts/pip install -r tests/E2E/requirements.txt
storage/framework/e2e-venv/Scripts/python tests/E2E/run.py --browser "C:/Program Files/Google/Chrome/Application/chrome.exe"
```

Browser chạy headless trên cổng localhost ngẫu nhiên, dùng APP_ENV=e2e để CSRF thực sự hoạt động. Mỗi lần chạy tạo SQLite và storage riêng trong `storage/browser-e2e/<uuid>/`; kiểm tra marker qua HTTP trước mọi mutation. Router chỉ phục vụ fixture được chỉ định và truyền đúng storage cho PHP trên Windows. Không dùng database, maintenance hoặc backup của ứng dụng đang chạy. Script giữ log, kết quả JSON và ảnh 375/768/1024/1440 px để review; đây là dữ liệu kiểm thử cục bộ, không commit.

Luồng: đăng nhập và URL thiếu quyền; CSRF; gửi duyệt/duyệt đúng revision/xuất bản câu hỏi; CRUD/publication Knowledge và saved items; import báo lỗi từng dòng; mất mạng/autosave/nộp/chấm Writing; lịch sử/leaderboard; CSV/XLSX và pending; profile/ảnh/giữ form khi lỗi; settings/audit/backup/preflight/restore và thu hồi phiên. Kiểm tra focus mặc định an toàn, vòng Tab, Escape và reduced motion. Fixture runner rút gọn phục vụ contract, không thay kiểm thử validation builder trong suite PHP.

```powershell
# Mặc định server MySQL 127.0.0.1:3306, root, mật khẩu rỗng.
# Có thể đặt PHAT_MYSQL_PORT / PHAT_MYSQL_USER / PHAT_MYSQL_PASSWORD cho server test.
storage/framework/e2e-venv/Scripts/python tests/E2E/mysql.py
```

MySQL script tự tạo tên ngẫu nhiên với prefix bắt buộc `vistiq_phat_test_`, chạy migration, hai reviewer đồng thời, hai request nộp đồng thời, JSON_TABLE/chấm/history, nâng cấp permission, summary/rank Profile và backup/restore/recovery. Database được drop trong finally; artifact chỉ giữ trong `storage/browser-mysql/<uuid>/`. Không chấp nhận tên database ứng dụng; không chạy migrate:fresh. Script bootstrap SQLite gom fixture trong transaction/WAL để giảm I/O, còn restore kiểm tra SQL thực tế.

Không gửi email thật, không gọi AI/Cloudinary/R2 thật. Unit/feature test giả lập các dịch vụ đó, E2E dùng dữ liệu nội bộ.

## Responsive

```powershell
uv run --with playwright python tests/E2E/responsive.py
# Chạy lại một nhóm trên dữ liệu của một lượt kiểm tra trước:
uv run --with playwright python tests/E2E/responsive.py --fixture storage/browser-e2e/responsive-<id> --only writing,speaking --widths 320,390,844,1440
```

Script ưu tiên Writing/Speaking, sau đó kiểm tra các trang admin, học viên và đăng nhập. Các viewport mặc định từ 320 đến 1440 px, gồm điện thoại nằm ngang. Kiểm tra tràn trang, điều khiển ngoài viewport, vùng bấm nhỏ và lỗi JavaScript; bảng có vùng cuộn ngang riêng được giữ nguyên. Luồng tương tác kiểm tra chọn câu, lọc Ajax, xác nhận/hủy xóa, mẫu XLSX và xem trước import; tạo và nộp bài chỉ dùng fixture riêng. Kết quả và ảnh nằm trong thư mục lượt kiểm tra. Đây là mô phỏng Chrome, không thay cho kiểm tra trên thiết bị thật.
