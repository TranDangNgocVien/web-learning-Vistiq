# Kiến trúc thư mục theo module

> Trạng thái: Kiến trúc đã chốt và code đã chuyển theo module. Đợt này giữ nguyên schema và các URL/route name hiện có.

## 1. Hướng kiến trúc

Giữ Vistiq ở dạng Laravel modular monolith. Chia ba mặt rõ ràng:

- Admin: màn hình và thao tác quản trị.
- Learner: luồng học, tra cứu, luyện tập và làm bài.
- Domain: quy tắc nghiệp vụ dùng chung giữa hai mặt.

Module sở hữu use case và hợp đồng nghiệp vụ của mình. Controller chỉ nhận request, gọi application/domain service và trả response. Form Request chịu validation HTTP. Module khác dùng service/action công khai thay vì gọi controller hoặc truy vấn nội bộ của nhau.

Repo hiện có PSR-4: VistiqAdmin ánh xạ tới app/Admin, VistiqLearner tới app/Learner, VistiqCore tới app/Domain. Cây module tận dụng các namespace này; Composer autoload không đổi.

## 2. Cây thư mục module đã chuyển

~~~text
app/
├── Admin/
│   ├── Http/Controllers/Admin/
│   │   ├── Assessment/{QuestionBank,Exams,ContentApproval,Attempts}/
│   │   ├── Learning/{Knowledge,SkillExercises}/
│   │   ├── Identity/{Authentication,UsersAndPermissions}/
│   │   ├── Analytics/
│   │   ├── Search/
│   │   └── System/
│   ├── Http/Requests/Admin/
│   │   ├── Assessment/{QuestionBank,Exams,ContentApproval,Attempts}/
│   │   ├── Learning/{Knowledge,SkillExercises}/
│   │   ├── Identity/{Authentication,UsersAndPermissions}/
│   │   ├── Analytics/
│   │   ├── System/
│   │   └── Shared/                    # integration-owned shared requests
│   └── Services/Admin/System/
├── Learner/
│   ├── Http/Controllers/{Authentication,Assessment/ExamTakingAndSubmission,Practice/SkillPractice,Knowledge,Search,Profile}/
│   ├── Http/Requests/{Authentication,Assessment/ExamTakingAndSubmission,Practice/SkillPractice,Knowledge,Search,Profile}/
│   └── Http/Resources/Search/
└── Domain/
    ├── Models/                         # dùng chung, giữ nguyên trong đợt này
    └── Services/
        ├── Assessment/{QuestionBank,Exams,ContentApproval,Attempts}/
        ├── Analytics/
        ├── Identity/UsersAndPermissions/
        ├── Knowledge/
        ├── System/
        └── Shared/                    # integration-owned cross-module services

routes/web/
├── admin.php                           # loader, middleware và prefix chung
├── admin/
│   ├── assessment/{question-bank,exams,content-approval,attempts}.php
│   ├── identity/{authentication,users-and-permissions}.php
│   ├── learning/{knowledge,skill-exercises}.php
│   ├── analytics.php
│   ├── search.php
│   ├── system.php
│   └── demo.php
├── learner.php                         # loader chung
└── learner/
    ├── assessment/exam-taking-and-submission.php
    ├── authentication.php
    ├── knowledge.php
    ├── practice/skill-practice.php
    ├── profile.php
    ├── search.php
    └── site.php

resources/views/pages/
├── admin/{assessment,identity,learning,analytics,search,system}/<module>/
└── learner/{assessment,authentication,knowledge,practice,profile,search}/<module>/

tests/
├── Admin/Feature/{Analytics,Authentication,ContentApproval,ExamAttempts,ExamManagement,KnowledgeBase,QuestionBank,Search,SystemOperations,UsersAndPermissions}/
├── Learner/Feature/{Authentication,ExamTakingAndSubmission,KnowledgeBase,Profile,Search,SkillPractice}/
└── {Admin,Learner}/Feature/Integration/  # kiểm thử contract dùng chung

docs/design/modules/<module>/
├── design.md                            # UX/UI và hành vi nghiệp vụ
└── SCOPE.md                             # đường dẫn được sửa, quyền và ranh giới phối hợp
~~~

Các lớp dùng chung như Models, migrations, providers, route loaders, layout/component, assets và permission catalog không thuộc một contributor module; integration owner quản lý để tránh xung đột merge. Danh sách scope đầy đủ nằm tại docs/design/modules/<module>/SCOPE.md; cách phân 4 gói nằm tại docs/architecture/module-ownership.md.
## 3. Ranh giới nghiệp vụ

| Module | Chủ sở hữu |
| --- | --- |
| Ngân hàng câu hỏi | Câu hỏi nguồn, đáp án, nhóm câu hỏi, import/export, CRUD nháp và liên kết audio/đoạn đọc |
| Quản lý đề thi | Cấu trúc đề, part, quy tắc chọn câu, phiên bản và trạng thái phát hành |
| Duyệt câu hỏi và đề thi | Decision endpoint cho nội dung câu hỏi và đề thi, approve/reject/archive và audit; không sở hữu CRUD nguồn. Gọi service phát hành của module nguồn khi publish |
| Quản lý lượt thi | Một danh sách/aggregate attempt dùng chung; theo dõi, chấm, xử lý báo cáo và cờ vi phạm |
| Làm bài và nộp bài | Runner dùng chung để giao câu hỏi, lưu câu trả lời và điều khiển attempt |
| Quản lý bài tập theo kỹ năng | Bộ bài tập tham chiếu câu hỏi từ ngân hàng; không sao chép câu hỏi |
| Luyện theo kỹ năng | Entry/catalog cho mode Practice; dùng cùng attempt và runner với mode Exam |
| Kho kiến thức | Bản ghi từ vựng, ngữ pháp và các nội dung kiến thức khác |
| Tra cứu | Giao diện và truy vấn tìm nội dung đã công bố; không sở hữu bản ghi kiến thức |
| Người dùng và phân quyền | Hồ sơ tài khoản quản trị, vai trò và permission |
| Authentication | Xác minh danh tính, phiên đăng nhập, đăng ký và khôi phục mật khẩu |
| Phân tích và thống kê | Truy vấn tổng hợp từ lượt thi, luyện tập và nội dung |
| Log và cài đặt hệ thống | Nhật ký kiểm toán, cài đặt vận hành và backup/restore |

Practice và Exam dùng chung một mô hình Attempt. Khác biệt được biểu diễn bằng mode và policy thực thi (thời gian, feedback, khả năng retry/điều hướng, thời điểm chấm), không tạo hai session aggregate hoặc hai bộ bảng câu trả lời. Domain Assessment sở hữu vòng đời; runner Learner ghi nhận bài; Admin Attempts quản lý mọi attempt và lọc theo mode.

QuestionBank và ExamManagement sở hữu nội dung, validation chuyên biệt và bản publish. ContentApproval cung cấp hàng đợi dùng chung, điều phối gửi duyệt, approve hoặc trả chỉnh sửa; không cho sửa nội dung ngay trong quyết định và không publish thay module nguồn. Cùng một revision phải được duyệt trước khi module nguồn publish.

### Mô hình dữ liệu logic đề xuất

Sơ đồ người dùng cung cấp đặt attempts làm gốc, chia mode Practice/Exam, rồi dùng chung attempt_questions và attempt_answers. Đề xuất giữ đúng ranh giới đó:

- attempts: user, mode (practice/exam), trạng thái, thời điểm, cấu hình mode và nguồn tham chiếu tới đề hoặc bộ bài.
- attempt_questions: thứ tự câu được giao, nguồn question bank và snapshot câu/stimulus tại thời điểm bắt đầu.
- attempt_answers: câu trả lời của learner, phiên bản lưu, thời gian trả lời, điểm/feedback theo policy.
- attempt_events và attempt_results: nhật ký state transition và kết quả của cùng attempt.

Mode Exam tham chiếu exam template/revision; mode Practice tham chiếu skill exercise/part/question selection. Cả hai sinh cùng dạng attempt_questions. Practice và Exam policy khác nhau nhưng không sở hữu bảng attempt riêng.

Schema hiện tại đang dùng exam_attempts và attempt_answers, đồng thời lưu snapshot trên exam_attempts; chưa có bảng attempt_questions riêng. Mô hình logic đích đã ghi nhận theo yêu cầu. Trước khi triển khai cần lập kế hoạch migration tương thích, bảo toàn FK, lịch sử, snapshot, routes và dữ liệu hiện có; không đổi schema trong đợt chỉ sắp xếp thư mục.

## 4. Quy ước route, view và data

- Giai đoạn đầu giữ nguyên URL, route name, middleware và response. Tách route file chỉ là tổ chức mã nguồn.
- File route tổng giữ nhóm prefix/guard; route con được nạp bên trong đúng middleware group.
- View chuyển theo module khi controller tương ứng được tách. Không đổi view path hàng loạt trong cùng một bước.
- app/Domain/Models đang được tham chiếu bởi Admin, Learner, factory, seeder và test; giữ namespace model hiện tại ở đợt 1. Chỉ chuyển model khi có kế hoạch cập nhật toàn bộ consumer và autoload.
- Migration tiếp tục theo thứ tự thời gian của Laravel. Không chia thư mục migration theo module trừ khi thống nhất cơ chế discovery trước.
- Static asset có thể giữ nguyên trong resources/js và resources/css ở đợt đầu; chỉ tách theo module khi asset đã có owner rõ ràng.

## 5. Ánh xạ từ cấu trúc cũ sang module mới

| Hiện tại | Đích đề xuất |
| --- | --- |
| Cấu trúc cũ | Vị trí module mới |
| --- | --- |
| Admin QuestionController, QuestionEditorController, StimulusController, MediaController | `Admin/Assessment/QuestionBank/` |
| ExamBuilderController, ExamRuleController | `Admin/Assessment/Exams/` |
| ContentApprovalController; QuestionBankWorkflowController cho export/bulk/group/nháp | `Admin/Assessment/ContentApproval/` và `Admin/Assessment/QuestionBank/` theo trách nhiệm |
| AttemptController, GradingController | `Admin/Assessment/Attempts/` |
| KnowledgeAdminController, GrammarController | `Admin/Learning/Knowledge/` |
| UserManagementController | `Admin/Identity/UsersAndPermissions/` |
| DashboardController, LeaderboardController | `Admin/Analytics/` |
| AdminSearchController | `Admin/Search/` |
| SettingsController, DatabaseBackupController | `Admin/System/` |
| Learner AttemptRunnerController, ExamController, ResultController | `Learner/Assessment/ExamTakingAndSubmission/` |
| Learner SkillPracticeController | `Learner/Practice/SkillPractice/` |
| Learner Dictionary controllers | `Learner/Search/` |
| Learner GrammarController, KnowledgeController | `Learner/Knowledge/` |
| Learner ProfileController | `Learner/Profile/` |
| Learner/AuthController, Admin AuthController/AdminOtpController | Authentication module theo actor |

## 6. Quy ước dùng chung cho module

- Mỗi module có tài liệu riêng theo MODULE_TEMPLATE.md.
- Permission được kiểm tra phía server ở mọi route/action; ẩn nút trên giao diện không thay thế authorization.
- Tác vụ thay đổi dữ liệu quan trọng ghi audit event có actor, đối tượng, thời điểm và diff đã lọc dữ liệu nhạy cảm.
- Mọi danh sách lớn có phân trang, lọc có giới hạn và trạng thái rỗng/loading/error dễ hiểu.
- Xóa dữ liệu tham chiếu bởi đề, lượt thi hoặc audit ưu tiên archive/soft delete. Hard delete cần quy tắc retention riêng.
- Dữ liệu câu hỏi/đề đã đưa vào attempt cần snapshot để kết quả cũ không thay đổi khi nội dung nguồn được sửa.

## 7. Kết quả của đợt chuyển cấu trúc

- Controllers, Form Requests, Domain Services, Admin/Learner views, route includes và module-owned feature tests đã được chuyển theo cây module.
- Admin Question Bank workflow đã tách khỏi Content Approval; quyết định duyệt câu hỏi/đề đi qua ContentApproval, còn thao tác nhóm/bulk/export/nháp ở QuestionBank.
- Root route files chỉ nạp include theo module; URL, route name, guard và schema được giữ lại.
- Module chưa có chức năng riêng đã nhận skeleton có thể commit và scope tài liệu. Shared Models, migrations, providers và assets vẫn thuộc integration owner.
- Test suite không được chạy trong đợt này. Trước khi merge/deploy, integration owner cần chạy route/auth regression và test suite theo `AGENTS.md`/CI.

## 8. Các quyết định đã chốt và phạm vi đợt chuyển đổi

### Đã đưa vào proposal

- Analytics có xuất báo cáo CSV và XLSX.
- Practice và Exam dùng chung Attempt, attempt_questions và attempt_answers; khác biệt do mode policy quyết định.
- Duyệt câu hỏi và đề thi dùng module/hàng đợi riêng; quyết định approve/trả chỉnh sửa tách khỏi CRUD và publish.
- Role được CRUD và gán permission có sẵn; permission catalog không CRUD.
- Xóa role có thành viên phải xác nhận rõ số người bị gỡ role và quyền có thể bị thu hồi.
- Mỗi module có design.md riêng dưới docs/design/modules; docs/design/design.md vẫn là spec canonical của login/register.

### Phạm vi chuyển đổi

- Đợt này chỉ di chuyển code và tách route includes; giữ nguyên URL, route name, middleware, database schema và response contract.
- Attempt domain hiện vẫn dùng schema vật lý hiện có. Bổ sung `attempt_questions`/backfill/cutover/rollback là một thay đổi dữ liệu riêng, không nằm trong đợt cấu trúc thư mục.
- Các khác biệt nghiệp vụ publish/review hiện có được giữ nguyên để tránh trộn migration kiến trúc với thay đổi vòng đời nội dung. Module owner phải ghi rõ policy hiện hữu và đề xuất thay đổi qua design doc trước khi triển khai.
- Danh sách đường dẫn mỗi thành viên được sửa được ghi tại `docs/design/modules/<module>/SCOPE.md`; cách gom thành 4 gói được ghi tại `docs/architecture/module-ownership.md`.
