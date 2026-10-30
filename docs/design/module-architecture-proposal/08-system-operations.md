# Module: Log và cài đặt hệ thống

> Trạng thái hiện tại: Đã có SettingsController, AuditService, màn audit và database backup; cần chuẩn hóa ranh giới vận hành.

## Mục tiêu và ranh giới

Quản trị cấu hình được cho phép, xem audit log và thực hiện backup/restore có kiểm soát. Audit log append-only; không dùng log ứng dụng để thay thế lịch sử revision nội dung.

## Chức năng cần có

- Xem/tìm audit theo actor, action, đối tượng, thời gian và kết quả.
- Cập nhật setting theo nhóm, xem giá trị hiệu lực và lịch sử thay đổi nếu có.
- Tạo/tải backup; restore theo quy trình xác nhận, preflight và ghi nhận kết quả.
- Theo dõi job thất bại, hàng đợi import và thao tác AI nếu quyền phù hợp.
- Che secret/token/PII trong log và UI; không hiển thị env/config tùy ý.

## Yêu cầu giao diện

Admin theo AGENTS.md; bảng log và form setting là nội dung chính. Setting có nhãn, kiểu dữ liệu, giá trị hiện tại, phạm vi ảnh hưởng và validation. Restore hiển thị cảnh báo hậu quả, tên file/nguồn và bước xác nhận rõ. Log có filter, phân trang, empty/error state; không có nút sửa/xóa từng event.

## Validation

- Chỉ update setting nằm trong allowlist/schema; kiểm tra kiểu, enum, khoảng giá trị và dependency giữa setting.
- Không nhận key cấu hình tùy ý; không trả secret nguyên văn về browser.
- Backup chỉ đọc file nguồn được phép, giới hạn size, kiểm tra integrity và quyền tải.
- Restore xác minh định dạng/nguồn, chặn path traversal, ghi preflight và yêu cầu permission đặc biệt.
- Log sanitize thông tin nhạy cảm; request dài/độc hại được giới hạn.

## CRUD và vòng đời

Settings: read/update, không CRUD key mới trực tiếp từ UI nếu chưa có schema. Audit: read-only/append-only. Backup: tạo và tải artifact, retention theo policy. Restore là command đặc quyền, không phải CRUD.

## Quyền và audit

Hiện có settings.manage và audit.view. Đề xuất tách quyền backup/restore. Audit mọi lần đổi setting, tạo/tải backup, restore bắt đầu/kết thúc và kết quả thất bại; tuyệt đối không ghi secret.

## Kế hoạch test

- Feature: đọc/cập nhật setting theo group và permission.
- Validation: key không cho phép, kiểu sai, giá trị ngoài ngưỡng và field secret.
- Audit: append-only, filter đúng, redaction cả log lẫn diff.
- Backup: quyền, integrity, file lỗi/thiếu và filename an toàn.
- Restore: preflight, request lặp, file giả/path traversal và rollback khi thất bại.
- Security: CSRF/rate limit và không lộ đường dẫn/storage secret.
- Operational: transaction/state report khi job chạy dở; cảnh báo lỗi người vận hành sửa được.

## Tiêu chí nghiệm thu và phụ thuộc

Setting sai không được áp dụng; audit không bị sửa/xóa từ app; backup/restore có kết quả truy vết. Phụ thuộc Settings, Audit, storage/queue và phân quyền.
