# Kiểm tra Viên — Knowledge Base / Analytics

- Branch: `feature/KnowledgeBase-Analytics`; mốc đầu: `c04dba9`.
- Căn cứ: module-ownership, hai SCOPE/design/proposal và AGENTS.md.

## Các phần chưa đáp ứng tại mốc đầu

| Vấn đề | Bằng chứng / ảnh hưởng | Xử lý |
| --- | --- | --- |
| Import tin dữ liệu hidden field | Có thể POST rows_json tùy ý mà không preview; confirm không dùng bản đã kiểm tra ở server. | Preview token theo actor/session, hết hạn 20 phút, khóa gửi lặp; bỏ qua rows_json từ client. |
| Import thiếu validation và báo thành công dù bỏ qua lỗi | Preview chỉ kiểm tra english, import catch từng dòng rồi báo thành công; thiếu meaning/field theo type và CSV nhiều dòng bị tách sai. | Dùng chung schema với form, đọc fgetcsv, giới hạn 5 MB/1.000 dòng, hiển thị lỗi từng dòng; chặn confirm khi còn lỗi và transaction toàn đợt. Giữ tệp/preview để thử lại khi lỗi có thể khắc phục. |
| Màn learner Knowledge còn dữ liệu mẫu | Controller truyền dữ liệu thật nhưng view index/detail vẫn hiển thị số liệu/từ/ghi chú tĩnh. | Render nội dung published, bỏ fallback bịa nội dung, thêm lưu/ghi chú theo owner và contract đọc dùng lại cho Profile. |
| Có thể hard delete draft đã được user lưu | Xóa draft làm cascade mất user_saved_items. | Chặn xóa khi có saved item; giữ tham chiếu và audit xóa draft hợp lệ. |
| Ranh giới route Knowledge chưa đủ chặt | GET create/edit chỉ yêu cầu content.view; ID LearningItem không được giới hạn loại. | GET editor cần content.edit; chặn đưa item Question vào CRUD Knowledge. |
| Analytics mới xuất CSV, thiếu quyền export riêng | Hai route export chỉ dùng results.view; export leaderboard dùng paginator. | Hoàn thiện CSV/XLSX, quyền results.export và tập dữ liệu/rank chung sau merge với Phát. |
| Bộ lọc thống kê chưa đồng nhất | Query difficulty thiếu bộ lọc mode/cancelled; mode variant không được áp dụng giống nhau ở trung bình điểm. | Chuẩn hóa query và fixture số liệu ở giai đoạn tích hợp. |

Commit sửa độc lập: `6d06ab7`. Các thay đổi AGENTS.md, migration cũ và Graphify trong commit gốc được integration owner review, không mặc định coi mọi thay đổi graph là lỗi chức năng.

## Kiểm thử

- Trước sửa: **35/35 đạt, 103 assertions**.
- Năm test hồi quy đầu tiên đều tái hiện vấn đề.
- Sau sửa: **42/42 đạt, 131 assertions**; bổ sung confirm không tin client/chỉ dùng một lần và ownership ghi chú/lưu/bỏ lưu.
- Pint, `git diff --check`, `graphify update .` hoàn tất.
- Browser, XLSX, MySQL và kiểm thử contract Analytics/Attempts được ghi tiếp trong báo cáo tích hợp.

## Kết quả trên develop sau tích hợp

- `4d7e6a0`: CSV/XLSX thực, permission cố định results.export đồng thời results.view, Admin/Super Admin giữ toàn quyền. Export leaderboard dùng toàn bộ tập lọc, cùng best attempt/rank/reset/time/ẩn danh; 31 bản ghi qua hai trang đều có trong export. Query thống kê thống nhất mode/legacy variant/cancelled và các filter. MySQL kiểm chứng cả migration mới và nâng cấp quyền khi role đã tồn tại.
- Sau khi siết GET editor sang content.edit, browser phát hiện link của reviewer vẫn trỏ vào editor và trả 403. Sửa tích hợp `4883a1c` thêm trang xem/duyệt chỉ cần content.view, giữ kiểm tra quyền mutation; không cấp quyền edit cho reviewer. Thêm regression test và E2E create → review → publish → learner save → Profile.
- Chuẩn hóa preview Knowledge, confirm xóa, pending và notification chung. Shared layout tránh biến điều hướng ghi đè dữ liệu item; các sửa này thuộc tích hợp sau merge như kế hoạch cho phép.
- Không còn phát hiện chức năng đang mở trong các mục đã kiểm tra trên develop. Toàn suite **442/442, 2.433 assertions**, JS **4/4**, build/Pint/diff, MySQL và E2E responsive đạt. Chi tiết và giới hạn môi trường: `Integration.md`.
