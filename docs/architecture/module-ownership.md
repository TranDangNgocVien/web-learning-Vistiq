# Phân công module cho 5 thành viên

Tài liệu này chốt gói công việc, ranh giới sở hữu và hợp đồng phối hợp để mỗi thành viên có thể làm độc lập, dễ review và merge. Các đường dẫn chi tiết được quy định trong `docs/design/modules/<module>/SCOPE.md`; giao diện từng module theo `design.md` và quy tắc chung trong `AGENTS.md`.

## Phân công

| Thành viên | Module phụ trách | Phạm vi chính |
| --- | --- | --- |
| **Linh** | `search`, `question-bank`, `exam-management` | Tra cứu; ngân hàng câu hỏi Listening/Reading/THPT, import và tài nguyên audio/ảnh; tạo, sửa, sinh, preview và phát hành đề theo workflow đã duyệt. |
| **Phát** | `content-approval`, `exam-attempts`, `exam-taking-and-submission` | Duyệt câu hỏi/đề thi; quản trị lượt thi và chấm bài; giao diện learner làm đề, nộp bài, xem lịch sử và bảng xếp hạng. |
| **Danh** | `users-and-permissions`, `authentication` | Người dùng, role và gán permission; đăng nhập, đổi mật khẩu, chặn truy cập Admin trái phép; kiểm thử E2E toàn web và sửa lỗi trong giai đoạn ổn định cuối. |
| **Viên** | `knowledge-base`, `analytics` | Kho kiến thức cho Admin và learner (Grammar, Vocabulary, …); phân tích, thống kê và xuất báo cáo. |
| **Khoa** | `profile`, `system-operations`; tài liệu liên module | Profile; log hệ thống và cài đặt hệ thống; use case nghiệp vụ, use case hệ thống và đặc tả tổng thể/module. |

“Tra cứu” được giao một lần cho Linh. Mỗi module chỉ có một chủ sở hữu triển khai trong giai đoạn làm tính năng.

## Ai cần đọc tài liệu nào trước khi bắt đầu

### Tài liệu chung — tất cả thành viên

- `AGENTS.md` ở thư mục gốc dự án.
- Tài liệu này: `docs/architecture/module-ownership.md`.
- `docs/design/modules/README.md` để nắm quy tắc design dùng chung.
- `SCOPE.md` và `design.md` của từng module mình phụ trách. `SCOPE.md` là ranh giới code được phép sửa; các thư mục trong scope của module khác chỉ được đọc.
- Proposal tương ứng trong `docs/design/module-architecture-proposal/` để hiểu validation, quyền, luồng dữ liệu và tiêu chí nghiệm thu.

### Linh

- Đọc `docs/design/modules/question-bank/SCOPE.md`, `docs/design/modules/question-bank/design.md` và `docs/design/module-architecture-proposal/01-question-bank.md`.
- Đọc `docs/design/modules/exam-management/SCOPE.md`, `docs/design/modules/exam-management/design.md` và `docs/design/module-architecture-proposal/02-exam-management.md`.
- Đọc `docs/design/modules/search/SCOPE.md`, `docs/design/modules/search/design.md` và `docs/design/module-architecture-proposal/10-search.md`.
- Đọc `docs/design/modules/content-approval/SCOPE.md`, `docs/design/modules/content-approval/design.md` cùng `docs/design/module-architecture-proposal/14-content-approval.md` ở chế độ tham khảo để thống nhất handoff gửi duyệt/publish.
- Chỉ sửa code trong các đường dẫn ghi ở ba `SCOPE.md` được giao; Content Approval là tài liệu/code tham khảo, không thuộc scope sửa của Linh.

### Phát

- Đọc `docs/design/modules/content-approval/SCOPE.md`, `docs/design/modules/content-approval/design.md` và `docs/design/module-architecture-proposal/14-content-approval.md`.
- Đọc `docs/design/modules/exam-attempts/SCOPE.md`, `docs/design/modules/exam-attempts/design.md` và `docs/design/module-architecture-proposal/04-exam-attempts.md`.
- Đọc `docs/design/modules/exam-taking-and-submission/SCOPE.md`, `docs/design/modules/exam-taking-and-submission/design.md` và `docs/design/module-architecture-proposal/09-exam-taking-and-submission.md`.
- Đọc thêm `docs/design/modules/exam-management/SCOPE.md`, `docs/design/modules/exam-management/design.md`, `docs/design/module-architecture-proposal/02-exam-management.md` và các file cấu hình builder do Linh sở hữu — chỉ đọc: `app/Admin/Http/Controllers/Admin/Assessment/Exams/ExamBuilderController.php`, `app/Domain/Services/Assessment/Exams/ExamPublishingService.php`, `resources/views/pages/admin/assessment/exams/workspace/editor.blade.php`.
- Đọc `docs/design/modules/analytics/SCOPE.md`, `docs/design/modules/analytics/design.md` và `docs/design/module-architecture-proposal/07-analytics.md` ở chế độ tham khảo cho contract leaderboard.
- Chỉ sửa code trong ba scope được giao. Không sửa builder, schema hoặc service riêng của Exam Management; thay đổi contract cần thống nhất với Linh.

### Danh

- Đọc `docs/design/modules/users-and-permissions/SCOPE.md`, `docs/design/modules/users-and-permissions/design.md` và `docs/design/module-architecture-proposal/03-users-and-permissions.md`.
- Đọc `docs/design/modules/authentication/SCOPE.md`, `docs/design/modules/authentication/design.md`, `docs/design/module-architecture-proposal/13-authentication.md` và `docs/design/design.md` (đặc tả login/register canonical).
- Trước E2E, đọc `composer.json`, `package.json`, `phpunit.xml`, cùng `SCOPE.md`/`design.md` của các module đang triển khai để biết test commands, luồng và quyền mong đợi. Không đưa `skill-practice` hoặc `skill-exercise-management` vào E2E.
- Code module chỉ sửa trong hai scope được giao. Test E2E liên module đặt trong `tests/E2E/` sau khi thống nhất với integration owner; không tự sửa `composer.json`, `package.json`, cấu hình test hoặc file module khác.

### Viên

- Đọc `docs/design/modules/knowledge-base/SCOPE.md`, `docs/design/modules/knowledge-base/design.md` và `docs/design/module-architecture-proposal/05-knowledge-base.md`.
- Đọc `docs/design/modules/analytics/SCOPE.md`, `docs/design/modules/analytics/design.md` và `docs/design/module-architecture-proposal/07-analytics.md`.
- Đọc `docs/design/modules/exam-attempts/SCOPE.md`, `docs/design/modules/exam-attempts/design.md`, `docs/design/module-architecture-proposal/04-exam-attempts.md`, `docs/design/modules/exam-taking-and-submission/SCOPE.md`, `docs/design/modules/exam-taking-and-submission/design.md` và `docs/design/module-architecture-proposal/09-exam-taking-and-submission.md` ở chế độ tham khảo cho nguồn điểm/thời gian và leaderboard.
- Chỉ sửa code trong scope KnowledgeBase và Analytics. Export báo cáo tuân theo `docs/design/modules/analytics/design.md`: CSV/XLSX phải dùng cùng bộ lọc hiện tại; permission/config dùng chung cần gửi integration owner.

### Khoa

- Đọc `docs/design/modules/profile/SCOPE.md`, `docs/design/modules/profile/design.md` và `docs/design/module-architecture-proposal/12-profile.md`.
- Đọc `docs/design/modules/system-operations/SCOPE.md`, `docs/design/modules/system-operations/design.md` và `docs/design/module-architecture-proposal/08-system-operations.md`.
- Để vẽ use case và viết đặc tả liên module, đọc `docs/design/modules/README.md`, `docs/design/module-architecture-proposal/00-architecture-proposal.md`, `docs/design/module-architecture-proposal/00-design-alignment.md`, `docs/design/module-architecture-proposal/MODULE_TEMPLATE.md`, cùng `SCOPE.md` và `design.md` của 12 module đang được giao trong bảng trên: `question-bank`, `exam-management`, `search`, `content-approval`, `exam-attempts`, `exam-taking-and-submission`, `users-and-permissions`, `authentication`, `knowledge-base`, `analytics`, `profile`, `system-operations`. Proposal từng module là nguồn nghiệp vụ; không đưa hai module đã loại vào đặc tả đang triển khai.
- Code chỉ sửa trong scope Profile và System Operations. Khoa độc quyền soạn các proposal/đặc tả tại `docs/design/module-architecture-proposal/` và use case tại `docs/architecture/use-cases/` (tạo thư mục nếu chưa có); chủ module review nghiệp vụ trước khi chốt. Các `design.md` UI và `SCOPE.md` trong `docs/design/modules/<module>/` vẫn do chủ module đó quản lý; Khoa đọc và góp ý, không sửa song song.

## Phạm vi đã loại khỏi kế hoạch

- Loại bỏ `skill-practice` (Luyện theo kỹ năng) khỏi phạm vi sản phẩm và danh sách task hiện tại: không triển khai luồng practice, màn hình, route, permission hoặc test mới cho module này.
- Loại `skill-exercise-management` khỏi kế hoạch cùng với `skill-practice`, vì module quản lý bộ bài chỉ phục vụ luồng luyện theo kỹ năng đã bỏ.
- Luồng làm bài trong phạm vi hiện tại là **làm đề thi** (`exam-taking-and-submission`, mode Exam). Không giao module practice hoặc bài tập kỹ năng cho thành viên nào.

## Hợp đồng giữa các module

### Linh ↔ Phát: cấu hình đề thi và màn hình làm bài

Linh sở hữu schema, lưu trữ, validation và ý nghĩa của cấu hình trong Exam Management. Phát đọc cấu hình đã lưu/đã publish và render đúng hành vi trong runner; không tự đặt mặc định khác hoặc suy diễn cấu hình ở phía client.

Runner cần xử lý đầy đủ các cấu hình hiện có trong builder, gồm:

- Quy tắc chuyển câu: khóa câu trước sau khi chuyển; cho phép đánh dấu câu để xem lại.
- Trộn thứ tự đáp án.
- Yêu cầu audio liên kết khi làm bài.
- Theo dõi việc rời khỏi bài thi; yêu cầu toàn màn hình.
- Cho phép tạm dừng; cho phép làm trên điện thoại.
- Chặn sao chép và chặn dán nội dung.
- Điều hướng tự do hoặc theo thứ tự, cùng các chính sách nâng cao.

Trước khi đổi tên, kiểu dữ liệu hoặc ý nghĩa của một cấu hình, hai chủ module thống nhất contract và cách tương thích với đề đã lưu. Bản runner phải dùng đúng cấu hình của đề/revision mà learner bắt đầu; thay đổi builder không được làm đổi hành vi của attempt đã bắt đầu.

### Content Approval ↔ Exam Management: nguồn câu hỏi của đề

Exam Management dùng revision câu hỏi hiện tại nếu revision đó đã được duyệt; nếu chưa có revision được duyệt thì dùng revision đã xuất bản. Việc đưa một câu hỏi đã duyệt vào đề không tự xuất bản câu hỏi độc lập. Tài nguyên được câu hỏi tham chiếu được chụp vào revision đề và kiểm tra khi gửi/duyệt đề; trạng thái publish của tài nguyên riêng không bắt buộc, còn revision đề vẫn phải qua review và publish để learner truy cập.

### Phát ↔ Viên: lịch sử, xếp hạng và số liệu

Phát sở hữu màn hình learner xem lịch sử lượt làm và bảng xếp hạng trong luồng làm đề. Viên sở hữu truy vấn/tổng hợp và xuất báo cáo thuộc Analytics. Hai bên thống nhất service/response contract để không nhân đôi logic điểm, thời gian làm hoặc điều kiện công bố. Bảng xếp hạng chỉ hiển thị thông tin được phép công bố, tối thiểu gồm tên, điểm và thời gian làm.

### Khoa ↔ các chủ module: đặc tả

Khoa soạn use case nghiệp vụ/hệ thống và duy trì bộ đặc tả liên module. Chủ module xác nhận luồng, validation, quyền và tiêu chí nghiệm thu thuộc phần mình trước khi đặc tả được xem là chốt. Tài liệu UI riêng của module phải khớp `design.md` và `SCOPE.md` tương ứng.

## Phạm vi quyền và kiểm thử

- Danh sở hữu CRUD role và thao tác gán permission. Permission là catalog cố định do mã nguồn/seeder quản lý: không tạo chức năng CRUD Permission. Khi xóa role đang có thành viên, phải xác nhận rõ số người bị ảnh hưởng và việc họ mất toàn bộ quyền đến từ role đó.
- Danh đảm bảo đổi mật khẩu có xác thực/validation phù hợp và mọi route/action Admin đều kiểm tra quyền phía server. Người không được cấp quyền không thể vào Admin bằng cách gõ URL trực tiếp; ẩn menu/nút không thay thế kiểm tra này.
- Mỗi chủ module chịu trách nhiệm test unit/feature và kiểm tra quyền cho module mình. Danh sở hữu bộ E2E web xuyên suốt sau khi các module hoàn tất và được tích hợp.
- Trong giai đoạn phát triển, lỗi nằm trong module nào thì chủ module đó sửa. Sau khi feature freeze, Danh điều phối vòng sửa lỗi cuối theo kết quả E2E; trước khi sửa file module khác, phải xác nhận chủ module đã dừng chỉnh sửa file đó để tránh merge conflict. Không sửa đồng thời cùng một module.

## Quy tắc làm độc lập và merge

- Thành viên chỉ sửa code trong các đường dẫn thuộc `SCOPE.md` của module được giao. Module khác chỉ đọc; nhu cầu thay đổi contract cần được thống nhất với chủ module cung cấp và chủ module gọi.
- Không gọi controller của module khác hoặc chỉnh private implementation của module đó. Dùng public service/action contract; ghi rõ request/response, lỗi và quyền nếu cần mở rộng contract.
- File tích hợp dùng chung do integration owner quản lý: route loaders `routes/web/admin.php` và `routes/web/learner.php`, permission catalog/seeder, provider, middleware registration, Composer/config, shared models/migrations, shared requests/services, layout/component/CSS/JS dùng chung và fixture/contract test dùng chung. Integration owner là người điều phối dự án hoặc người được chỉ định riêng; không mặc định gán các file này cho một module.
- Chủ module tạo PR/nhánh với thay đổi giới hạn trong scope, nêu contract phụ thuộc, migration/config cần tích hợp và lệnh kiểm tra đã chạy. Integration owner xử lý nạp route, file chung và merge cuối.
- Thay đổi liên module được chia theo thứ tự: thống nhất contract → chủ sở hữu contract cập nhật/duyệt → module tiêu thụ triển khai → chạy kiểm tra tích hợp. Không để hai người cùng sửa một file hoặc cùng một module song song.
