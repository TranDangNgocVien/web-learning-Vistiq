# Báo cáo tích hợp Vistiq

Phản hồi sau tích hợp ngày 18/10/2026 và kết quả sửa bổ sung: [Feedback-2026-10-18.md](Feedback-2026-10-18.md).

## Mốc và thứ tự merge

- Gốc `main`: `76793fc`; baseline 285 test, 274 đạt, 9 failure, 2 error.
- Danh: đầu `5ea757c`, sửa `c916a98`, báo cáo `0d2ee37`.
- Phát: đầu `9eaba07`, sửa `deaf752`, báo cáo `afe27b4`; merge `bce7116`.
- Viên: đầu `c04dba9`, sửa `6d06ab7`, báo cáo `6953402`; merge `9d034a2`.
- Khoa: code `4d162c6`, merge `d8ca989`; bản cuối feature Danh `d9d0e76`, Phát `7ed596f`, Viên `e270ded`, Khoa `76998ec`. Báo cáo cuối được cập nhật trên từng feature branch và merge trở lại; tree sau merge giống bản đã kiểm thử.
- Thứ tự merge: Danh → Phát → Viên. Không squash, không force push hoặc xóa branch nguồn.
- Conflict chức năng nằm ở view leaderboard: giữ filter/export/reset của Viên, rank_position của Phát và dialog chung. Graph được hợp nhất node/relationship hai phía rồi dựng lại từ code.

## Các sửa tích hợp

- CSV/XLSX dùng cùng nguồn lọc; export cần cả results.view và results.export. Catalog/seeder giữ Admin/Super Admin toàn quyền; migration bổ sung key cho database đã triển khai, không cấp mặc định cho reviewer.
- Export leaderboard lấy toàn bộ trang, giới hạn 10.000 dòng và báo lỗi khi vượt giới hạn; giữ best attempt, đồng hạng, thời gian, privacy và mốc reset. XLSX được tạo bằng PhpSpreadsheet và đọc lại bằng test.
- Thống kê trung bình/độ khó dùng chung tập attempt, gồm lọc variant practice cũ, cancelled/is_test và các filter loại đề/kỹ năng/trạng thái. Không triển khai module Skill Practice.
- Layout Admin dùng biến điều hướng riêng, tránh ghi đè `$item` khiến editor Knowledge lỗi 500. Thêm preview bản nháp, phản hồi chung, pending export và bảo vệ submit lặp.
- Khôi phục GET nhóm câu hỏi và control import/template CSV; giữ cả workbook và CSV. Sửa workbook lookup part theo exam type + skill thay vì ghi đè part có code trùng. Chặn reading_key cùng đợt bị tách thành hai bài khác part.
- Chỉ sửa assertion THPT để xác nhận alias thpt-english trỏ blueprint 2025 hiện hành. Fixture TOEIC Part 1 bổ sung ảnh bắt buộc và fake Cloudinary; không bỏ validation ảnh để làm test đạt.
- Migration cũ trên branch Viên chỉ có thay đổi format, không có thay đổi schema cần backfill. Migration current_item_index của Phát được giữ.

## Kiểm chứng trước phần Khoa

- Sau merge, trước sửa tích hợp: 404/413 đạt; 9 failure giống baseline, hai error AiService đã được sửa ở branch Phát.
- Sau sửa: **416/416 đạt, 2.206 assertions**, SQLite RAM.
- `npm run build` đạt. Có cảnh báo fontaine tùy chọn và kích thước bundle Admin; không coi warning là build failure.
- Test và log chi tiết cục bộ ở `storage/logs/integration/` (không commit dữ liệu kiểm thử).

## Kiểm chứng cuối

- Khoa hoàn thiện Profile/System Operations trên `codex/khoa-profile-system-operations`; chi tiết chức năng, contract và test ở `Khoa.md`. Profile được dựng lại theo ảnh tham chiếu thứ hai của người dùng; dữ liệu, metric và hoạt động là dữ liệu thật của owner.
- Sau merge phần Khoa và các báo cáo: **59/59 contract test, 295 assertions** đạt trên develop.
- Toàn bộ PHP suite cuối: **442/442 đạt, 2.433 assertions**, SQLite RAM, 48,4 giây. JS runner **4/4**. Pint và diff check đạt; build frontend đạt với hai warning tùy chọn đã nêu.
- Một vòng test gặp file font manifest mất tạm thời khi build thay asset. Chạy lại tuần tự sau build đạt toàn bộ; không sửa assertion để che lỗi. Các lần chạy E2E đầu giúp phát hiện throttle dùng chung budget, route storage bị shadow và race autosave/submit; tất cả đã có sửa/hồi quy.
- E2E Chrome thật: **11 nhóm kiểm tra đạt**, không có JavaScript exception. Luồng gồm URL thiếu quyền/CSRF, gửi/duyệt revision và publish, Knowledge CRUD/publication/saved/import lỗi dòng, offline/autosave/nộp/chấm, history/leaderboard/CSV/XLSX, Profile/avatar/giữ cả file khi form lỗi, settings/audit/backup/preflight/restore/logout.
- **68 ảnh ở 375/768/1024/1440 px**, thuộc 17 màn đại diện: role, user, Reading bank, exam builder, review, Knowledge editor/preview, catalog Knowledge, runner Reading/Writing, history/saved/Profile, leaderboard, settings/audit/database. Document không tràn ngang; bảng cuộn trong container. Đã xem ảnh desktop/mobile/tablet, kiểm tra focus/Tab/Escape, pending và reduced motion. Menu Admin mobile không tự mở che control.
- MySQL **8.4.3** trên database ngẫu nhiên có prefix `vistiq_phat_test_`: fresh migrations, upgrade results.export khi role đã tồn tại, review đồng thời 200/409 với một audit, submit đồng thời một kết quả/activity, JSON_TABLE grading/owner history, profile aggregate/rank, backup integrity/restore/session revocation và recovery khi SQL lỗi đều đạt. Database test đã được drop; không dùng migrate:fresh hoặc restore database ứng dụng.
- Script tái lập: `tests/E2E/README.md`. Log/ảnh cuối browser: `storage/browser-e2e/82fd64f130f946499069498894035887/`; MySQL: `storage/browser-mysql/fecdaa7fd5714208994563d9261790f4/`; PHP: `storage/logs/integration/final-sequential.txt`. Artifact kiểm thử không commit.

## Sửa phát hiện khi kiểm tra tích hợp

- `4883a1c`: trang xem/duyệt Knowledge cho reviewer không cần quyền edit; sửa link và notification/confirm/pending theo shared pattern.
- `dc6dc93`: submit chờ cùng promise của autosave đang chạy; không báo recording chưa đồng bộ trên bài Writing không có recording. Giữ retry sau lỗi và thêm hai test JavaScript đồng thời.
- Shared middleware throttle tách action budget, web/API của cùng action dùng cùng budget; nhóm request ghi bài vẫn dùng chung ngân sách. Shared local disk private không chiếm route public avatar. Regression contract tests bảo vệ hai thay đổi này.
- Shared model/migration/provider/middleware/session, catalog/seeder, layout/asset, route loaders và test contract đã được tiếp nhận ở vai trò integration owner. Feature routes vẫn nằm trong module include, không đưa vào loaders. Shared layouts/tokens/dialog/notification dùng chung, không tạo hệ style Admin riêng.
- Graphify CLI và cả integration Codex/Antigravity cùng dùng `graphify-out/graph.json`; đã cập nhật AST sau thay đổi code. Nhãn community tự sinh được giữ, không gọi LLM để đổi nhãn.

## Giới hạn và bàn giao

- SMTP/OTP tới hộp thư thật, provider AI/Cloudinary/R2 ngoài mạng chưa được kiểm chứng; mail/HTTP đã giả lập trong feature test. Không bổ sung Skill Practice, mục tiêu học, xóa/ẩn danh hay bộ use case/đặc tả liên module.
- Restore hỗ trợ SQL Vistiq MySQL/SQLite cùng driver, tối đa 100 MB; cần cấu hình PHP upload/post và quyền ghi private storage tương ứng khi triển khai. Backup có schema tạo không phá bảng; file DROP cấu trúc bị từ chối, không bảo đảm phục hồi backup từ schema tùy ý. Vận hành production cần chạy migration mới theo quy trình triển khai; không tự chạy trên database đang sử dụng.
- Các phát hiện chức năng trong phạm vi đã sửa và kiểm chứng theo các ca trên. Giữ nguyên main và stash `pre-integration graphify query cache` chứa thay đổi cache Graphify cục bộ trước khi bắt đầu. Không force push hoặc xóa branch nguồn.
