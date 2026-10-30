# Đề xuất kiến trúc module Vistiq

> Trạng thái: Bản nháp để người dùng xem xét. Chưa phải kiến trúc được chốt và chưa thay đổi mã nguồn.

Thư mục này mô tả cách chia module cho các chức năng quản trị và học tập. Mục tiêu là thống nhất quyền sở hữu nghiệp vụ, cấu trúc controller/request/service, tài liệu giao diện, validation, CRUD và kế hoạch kiểm thử.

## Tài liệu

- [Đề xuất kiến trúc tổng thể](00-architecture-proposal.md)
- [Quy ước giao diện dùng khi viết proposal](00-design-alignment.md)
- [Khung tài liệu module](MODULE_TEMPLATE.md)
- [Ngân hàng câu hỏi](01-question-bank.md)
- [Quản lý đề thi](02-exam-management.md)
- [Duyệt câu hỏi và đề thi](14-content-approval.md)
- [Quản lý người dùng và phân quyền](03-users-and-permissions.md)
- [Quản lý lượt thi](04-exam-attempts.md)
- [Kho kiến thức](05-knowledge-base.md)
- [Quản lý bài tập theo kỹ năng](06-skill-exercise-management.md)
- [Phân tích và thống kê](07-analytics.md)
- [Log và cài đặt hệ thống](08-system-operations.md)
- [Làm bài và nộp bài](09-exam-taking-and-submission.md)
- [Tra cứu](10-search.md)
- [Luyện theo kỹ năng](11-skill-practice.md)
- [Profile](12-profile.md)
- [Authentication](13-authentication.md)

Mỗi module có UI specification riêng trong [docs/design/modules](../modules/README.md), với tên file design.md. Đặc tả login/register ở docs/design/design.md vẫn là nguồn canonical cho auth.

## Cách đọc trạng thái hiện tại

“Đã có” nghĩa là repo đang có controller, route, service, view hoặc test liên quan; không có nghĩa chức năng đã đầy đủ hay đạt mọi tiêu chí bên dưới. “Một phần” và “đề xuất mới” là đánh giá sơ bộ từ cấu trúc hiện tại.

## Quy tắc xem xét

1. Duyệt ranh giới module và chủ sở hữu dữ liệu trước.
2. Chốt các mục cần quyết định trong tài liệu kiến trúc.
3. Sau khi thống nhất, mới chuyển từng nhóm code; giữ URL, tên route và hành vi hiện có trong mỗi đợt nếu không có yêu cầu đổi.
