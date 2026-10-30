# Design specs theo module

> Trạng thái: Bản nháp thiết kế để review; chưa phải yêu cầu giao diện đã chốt.

Mỗi thư mục con có một file design.md riêng. Các file này mô tả bố cục, thao tác, trạng thái và khả năng truy cập của module; chúng không thay thế nghiệp vụ, validation hoặc test plan trong module proposal.

## Căn cứ chung

- Giữ palette, font, shell và components đang có trong ứng dụng; tài liệu module không tự tạo design tokens mới.
- Admin tuân theo AGENTS.md: nội dung/control chính xuất hiện ngay sau heading ngắn; không dùng hero/banner/stat lớn để trang trí.
- Learner giữ shell hiện tại. Hiệu ứng ngày/đêm và spotlight chỉ áp dụng login/register theo docs/design/design.md.
- Bảng, biểu đồ và màu trạng thái phải có nhãn/chú giải; trạng thái loading, empty, error và success phải được mô tả.
- Tương tác chính cần keyboard/focus, screen reader labels, responsive và reduced-motion khi có chuyển động.

## Danh sách

| Module | UI spec | Proposal |
| --- | --- | --- |
| Ngân hàng câu hỏi | [design.md](question-bank/design.md) | [module](../module-architecture-proposal/01-question-bank.md) |
| Quản lý đề thi | [design.md](exam-management/design.md) | [module](../module-architecture-proposal/02-exam-management.md) |
| Duyệt câu hỏi và đề thi | [design.md](content-approval/design.md) | [module](../module-architecture-proposal/14-content-approval.md) |
| Người dùng và phân quyền | [design.md](users-and-permissions/design.md) | [module](../module-architecture-proposal/03-users-and-permissions.md) |
| Quản lý lượt thi | [design.md](exam-attempts/design.md) | [module](../module-architecture-proposal/04-exam-attempts.md) |
| Kho kiến thức | [design.md](knowledge-base/design.md) | [module](../module-architecture-proposal/05-knowledge-base.md) |
| Quản lý bài tập kỹ năng | [design.md](skill-exercise-management/design.md) | [module](../module-architecture-proposal/06-skill-exercise-management.md) |
| Phân tích và thống kê | [design.md](analytics/design.md) | [module](../module-architecture-proposal/07-analytics.md) |
| Log và cài đặt hệ thống | [design.md](system-operations/design.md) | [module](../module-architecture-proposal/08-system-operations.md) |
| Làm bài và nộp bài | [design.md](exam-taking-and-submission/design.md) | [module](../module-architecture-proposal/09-exam-taking-and-submission.md) |
| Tra cứu | [design.md](search/design.md) | [module](../module-architecture-proposal/10-search.md) |
| Luyện theo kỹ năng | [design.md](skill-practice/design.md) | [module](../module-architecture-proposal/11-skill-practice.md) |
| Profile | [design.md](profile/design.md) | [module](../module-architecture-proposal/12-profile.md) |
| Authentication | [design.md](authentication/design.md) | [module](../module-architecture-proposal/13-authentication.md) |
