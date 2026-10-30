# Hoàn thiện Khoa — Profile / System Operations

- Branch: `codex/khoa-profile-system-operations`, tạo từ `develop` tại `4d7e6a0`; commit triển khai `4d162c6`, merge `d8ca989`.
- Căn cứ: SCOPE/design/proposal Profile và System Operations, module-ownership và AGENTS.md; phạm vi sản phẩm theo kế hoạch đã duyệt.

## Phát hiện ban đầu và kết quả

| Yêu cầu chưa đạt | Biểu hiện ban đầu | Trạng thái sau sửa |
| --- | --- | --- |
| Profile và lịch sử thật | Trang dùng dữ liệu mẫu, chưa có endpoint cập nhật theo owner. | Form chỉ sửa tên/quốc gia/múi giờ/bio/avatar; email, quyền, trạng thái và plan bị cấm. History dùng AttemptQueryService với owner từ session, filter/phân trang/link kết quả. Saved dùng KnowledgeReaderService. |
| Avatar có vòng đời an toàn | Chưa có validation, preview và xử lý thay/xóa ảnh. | JPEG/PNG/WebP thật, tối đa 2 MB và 4.096 px, preview/bỏ ảnh, giữ cả tệp đã chọn khi validation lỗi qua form bất đồng bộ; lưu ảnh mới trước, chỉ xóa ảnh cũ sau commit; rollback dọn ảnh mới. |
| Chất lượng giao diện Profile | Bản form đầu tiên quá thô, khoảng cách và bố cục chưa đáp ứng mẫu người dùng. | Dựng lại theo ảnh tham chiếu thứ hai: thẻ tài khoản, bốn metric, form bên trái, hoạt động/thông tin bên phải; responsive một cột. Metrics và hoạt động từ dữ liệu thật. |
| Form settings chỉ là JSON | Người dùng phải tự sửa JSON, chưa có nhãn/ràng buộc tại trường. | Form theo nhóm và kiểu, giữ input khi lỗi, kiểm tra SMTP/OTP, AI/capability, storage và ngưỡng bảo mật. Secret chỉ báo trạng thái; không render hoặc flash secret. Scales/plans lưu từng form, phát hiện form bị cắt bởi giới hạn input. |
| Audit thiếu truy vấn và redaction lịch sử | Chỉ phân trang log, không có kết quả/lọc/diff an toàn. | Migration result có default/rollback index; lọc actor/action/loại/ID/ngày/kết quả. Diff lịch sử được redaction khi đọc; audit chỉ đọc. |
| Backup chưa có artifact và integrity | Stream SQL trực tiếp, chưa có danh sách private hoặc checksum. | Artifact SQL/metadata private, SHA-256/preflight khi tạo và tải, xác thực mật khẩu/quyền, audit và phân trang. |
| Restore chưa phục hồi khi SQL lỗi | Upload thực thi ngay, recovery có tạo nhưng không thực thi; thiếu kết quả bền vững và chống lặp. | Upload/preflight riêng, token UUID gắn actor/checksum/hạn 20 phút, current password + xác nhận, flock chống lặp/đồng thời, recovery trước khi ghi, transaction DML và kiểm tra FK. Lỗi thực thi phục hồi recovery; lỗi kép giữ maintenance. Kết quả lưu private JSON ngoài database và audit; chỉ up khi an toàn. Thành công thu hồi session/remember/reset token. |
| Thiếu màn tác vụ vận hành | Chưa có nơi xem hàng đợi/lỗi import và AI. | Màn chỉ đọc có phân trang, trạng thái, hướng xử lý lỗi an toàn; không lộ payload/exception/credentials. AI chỉ liệt kê khi bật. |

## Contract và thay đổi tích hợp

- Profile gọi public summary/listing của Attempts, savedItems của Knowledge và bestRankForUser của Analytics. Độ chính xác = tổng câu đúng / tổng câu objective của các kết quả đã chấm, không trộn các thang điểm TOEIC/IELTS. Rank dùng cùng truy vấn công bố/best attempt/tie-breaker/reset như leaderboard.
- Đổi mật khẩu nằm trong Authentication: kiểm tra mật khẩu hiện tại, policy, xoay remember/session version và thu hồi phiên khác. Endpoint Profile không nhận credentials.
- `/demo/profile`, `/demo/history` giữ redirect tới trang xác thực. Không thêm mục tiêu học, xóa/ẩn danh hoặc Skill Practice.
- Middleware throttle tách budget theo action, web/API cùng action chung budget; autosave không làm khóa bắt đầu đề hoặc restore. Disk local private không đăng ký route trùng `/storage/{path}` của public avatar. Menu Admin mobile đóng mặc định, hỗ trợ Escape/outside click.
- Trang Knowledge xem/duyệt riêng (`4883a1c`) hoàn thiện đường đi của reviewer có content.review nhưng không có content.edit; đây là sửa tích hợp sau khi shared layout đã được sửa.

## Kiểm chứng

- Profile: 10/10 test đạt, gồm ownership/mass assignment, MIME/size, lưu lỗi giữ ảnh cũ, thay/bỏ ảnh, history/saved và credentials riêng; test kiểm chứng metric 75% và hạng #2 từ fixture thật.
- System Operations: 20/20 test đạt, gồm backup roundtrip/index, preflight/actor/hạn/checksum/replay, lỗi SQL giữa chừng, lỗi kép giữ maintenance, secret flash, allowlist/dependencies và audit read-only.
- Toàn bộ suite: **442/442 test, 2.433 assertions**; JS runner **4/4**. Build frontend đạt.
- E2E Chrome thật và MySQL cô lập: xem kết quả cuối ở `Integration.md`; script tái lập tại `tests/E2E/README.md`.

Không còn thiếu chức năng trong phạm vi Khoa đã chốt theo các ca đã kiểm chứng. Chưa kiểm chứng giao email/provider AI/media ngoài mạng; không triển khai tài liệu use case/đặc tả liên module theo lựa chọn hiện tại.
