# Module: Duyệt câu hỏi và đề thi

> Trạng thái hiện tại: Đã có một phần workflow và quyền reviewer, nhưng xử lý câu hỏi và đề thi còn phân tán; đề xuất gom hàng đợi và quyết định review thành module riêng.

## Mục tiêu và ranh giới

Admin xem và xử lý tập trung các revision câu hỏi/đề thi đang chờ duyệt. Module sở hữu hàng đợi, quyết định approve hoặc trả chỉnh sửa, cùng lịch sử quyết định. Ngân hàng câu hỏi và Quản lý đề thi vẫn sở hữu nội dung, validation chuyên biệt và thao tác publish sau khi được duyệt. Module này không CRUD câu hỏi/đề thi và không thay đổi trực tiếp payload trong lúc review.

## Hiện trạng trong repo

Question revision đang đi qua ContentWorkflowController/ContentRevisionService; exam revision có transition review/approve/reject ở ExamBuilderController và publish validation riêng. Repo đã có permission content.review, role reviewer, audit và feature tests cho lifecycle question/exam. Hiện QuestionBank có super-admin bypass publish từ draft/review, trong khi ExamManagement yêu cầu approved; proposal đề nghị thống nhất policy. Đề xuất mới là ranh giới hàng đợi và thao tác review thống nhất, không phải xây lại workflow từ đầu.

## Chức năng cần có

- Hàng đợi hợp nhất, phân loại Câu hỏi/Đề thi; lọc theo mã/tên, người gửi, loại kỳ thi/part, ngày gửi và revision.
- Preview đúng revision được gửi: câu hỏi kèm lựa chọn/đáp án/stimulus; đề thi kèm cấu trúc part, số câu, thời lượng và lỗi validation.
- Xem khác biệt với revision trước hoặc bản đã publish, kèm lịch sử sửa và các quyết định review trước.
- Approve revision hợp lệ hoặc trả chỉnh sửa/từ chối kèm lý do bắt buộc.
- Sau approve, hiển thị trạng thái chờ publish; việc publish vẫn thực hiện trong module QuestionBank hoặc ExamManagement bởi người có content.publish.
- Hiển thị empty/loading/error/success, tổng hợp số việc đang chờ nếu hữu ích; không dùng KPI/banner trang trí.
- Xem audit của quyết định và mở lại đúng bản ghi nguồn theo quyền.

## Yêu cầu giao diện

Tham chiếu [design.md](../modules/content-approval/design.md), căn cứ chung tại [module design index](../modules/README.md) và AGENTS.md. Trang bắt đầu bằng heading ngắn, tabs/bộ lọc và bảng hàng đợi. Mở một mục để xem preview và quyết định; không cần rời trang để đối chiếu nội dung. Hiển thị rõ loại nội dung, revision, người gửi, thời gian chờ và lỗi blocker. Approve và trả chỉnh sửa là hai hành động riêng; form lý do chỉ xuất hiện khi trả chỉnh sửa. Màu trạng thái luôn đi cùng nhãn.

## Validation

- Chỉ revision đang ở trạng thái review mới nhận quyết định approve/trả chỉnh sửa; revision published/archive không được đưa lại vào hàng đợi.
- Quyết định gắn với đúng revision/version đã xem; nếu tác giả cập nhật hoặc reviewer khác xử lý trước thì trả conflict và tải trạng thái mới.
- Reviewer cần content.review; người gửi không tự approve revision của mình theo mặc định.
- Approve chỉ chuyển trạng thái sang approved, không publish hoặc làm revision learner-visible.
- Luồng publish chuẩn yêu cầu approved; nếu giữ emergency override cho super-admin thì phải tách action, xác nhận rõ và audit riêng, áp dụng thống nhất cho question/exam.
- Trả chỉnh sửa bắt buộc có lý do đã trim, giới hạn độ dài và lưu vào lịch sử/audit; revision quay về draft để tác giả cập nhật.
- Nội dung phải qua validator tương ứng trước khi approve: question registry/stimulus validation hoặc ExamPublishingService validation.
- Khóa/cập nhật có điều kiện hoặc transaction để hai reviewer không xử lý cùng một revision thành hai kết quả.

## CRUD và vòng đời

Không CRUD câu hỏi, đề thi hoặc permission. Hàng đợi là read/query; reviewer chỉ tạo quyết định state transition. Luồng chuẩn: draft → review → approved → published. Trả chỉnh sửa đưa revision về draft kèm lý do; sau cập nhật cần gửi lại và tạo quyết định mới. Publish/Archive thuộc module nguồn. Giữ nguyên revision đã publish để attempt cũ không đổi.

## Quyền và audit

content.view cho đọc preview, content.review cho xem hàng đợi và approve/trả chỉnh sửa; content.edit gửi revision sang review; content.publish riêng cho bước phát hành sau duyệt. Kiểm tra quyền ở server cả endpoint lẫn query scope. Audit cần actor, loại và ID nội dung, revision, trạng thái trước/sau, lý do, thời điểm; không ghi nội dung bí mật ngoài phần cần thiết để truy vết.

## Kế hoạch test

- Feature: hàng đợi gộp question/exam, lọc đúng loại, người gửi, trạng thái và thời gian; preview trỏ đúng revision.
- Feature: approve đưa đúng revision sang approved nhưng learner chưa thể truy cập trước publish.
- Feature: trả chỉnh sửa thiếu lý do bị từ chối; có lý do thì quay về draft và giữ nguyên lịch sử.
- Authorization: viewer chỉ đọc; editor gửi duyệt nhưng không quyết định; reviewer không publish; publisher publish riêng sau trạng thái approved.
- Authorization: tác giả không tự approve; request gọi trực tiếp không vượt content.review.
- Validation: nội dung không hợp lệ không được approve; lỗi hiển thị theo question/part cụ thể.
- Concurrency: hai quyết định đồng thời hoặc revision stale chỉ có một thành công; bên còn lại nhận conflict, không ghi đè audit.
- Audit: ghi đầy đủ actor/revision/action/reason cho approve và trả chỉnh sửa.
- Regression: ContentRevisionTest, ContentToolsTest và luồng authoring → review → approve → publish vẫn hoạt động khi chuyển controller.
- Browser: preview question/exam dùng keyboard, focus sau quyết định hợp lý, viewport hẹp vẫn thao tác được.

## Tiêu chí nghiệm thu và phụ thuộc

Reviewer xử lý câu hỏi và đề thi trong một hàng đợi, luôn thấy đúng revision và lý do trả sửa. Approve không đồng nghĩa publish; quyền publish vẫn tách biệt. Phụ thuộc QuestionBank, ExamManagement, ContentRevision, ExamPublishingService, Users/Permissions và AuditLog.
