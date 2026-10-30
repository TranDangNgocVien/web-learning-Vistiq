# Module: Quản lý kho kiến thức

> Trạng thái hiện tại: Đã có quản trị LearningItem, Grammar và tra cứu một phần; đề xuất chuẩn hóa grammar/vocabulary và mở rộng loại nội dung.

## Mục tiêu và ranh giới

Quản lý nội dung kiến thức có thể tra cứu hoặc gắn vào bài học: từ vựng, ngữ pháp và loại kiến thức mở rộng. Module sở hữu bản ghi, taxonomy, nội dung, revision và trạng thái xuất bản. Search chỉ truy vấn nội dung đã công bố.

## Hiện trạng trong repo

KnowledgeAdminController và các controller Grammar/Knowledge Learner cùng LearningItem model, ContentRevisionService và dictionary views đang cung cấp phần nền. Cần tách quản lý nguồn khỏi màn hình đọc/tra cứu.

## Chức năng cần có

- CRUD mục kiến thức, taxonomy/tag, ví dụ, phát âm và liên kết nội dung.
- Quản lý các loại grammar, vocabulary và loại bổ sung qua schema theo type.
- Tìm/lọc, import/export có preview, xem revision và workflow draft-review-publish.
- Preview cách mục kiến thức xuất hiện trong tra cứu/học tập.
- Quản lý nội dung trùng và liên kết mục liên quan.

## Yêu cầu giao diện

Admin theo AGENTS.md: danh sách và editor xuất hiện ngay sau heading. Editor phân nhóm trường theo loại nội dung, nêu rõ trường bắt buộc và preview. Hiển thị validation ngay cạnh trường; empty state hướng dẫn tạo mục đầu tiên. Learner consumption dùng card/detail hiện hữu, không áp layout auth sang trang kiến thức.

## Validation

- Type nằm trong registry; trường bắt buộc theo type.
- Slug/term chuẩn hóa và duy nhất trong phạm vi ngôn ngữ/type đã chốt.
- Nghĩa, từ loại, ví dụ và giải thích có giới hạn độ dài; HTML được sanitize.
- Link media và related item phải tồn tại, không tạo liên kết vòng nếu policy cấm.
- Import có encoding, loại file, duplicate policy và báo lỗi theo hàng.
- Chỉ publish nội dung đủ trường và được duyệt; sửa nội dung public tạo revision.

## CRUD và vòng đời

Create/read/update/review/publish/archive. Hard delete chỉ dành cho draft không tham chiếu; nội dung public hoặc được lưu/đánh dấu bởi user phải archive hoặc tạo tombstone để lịch sử không hỏng.

## Quyền và audit

Theo quyền quản trị nội dung hiện có; đề xuất tách permission knowledge nếu cần phân quyền chi tiết. Audit sửa định nghĩa, nội dung, import và đổi trạng thái.

## Kế hoạch test

- Feature: CRUD mỗi type, taxonomy và revision.
- Validation: schema thiếu trường, term/slug trùng, markup nguy hiểm, liên kết không tồn tại.
- Import: encoding, duplicate, preview không ghi, confirm transaction và báo row error.
- Workflow: draft không xuất hiện trong lookup; publish xuất hiện; archive rời kết quả.
- Authorization và audit cho editor/reviewer/publisher.
- Unit: normalize/search key, sanitizer và type schema.
- Regression: dictionary lookup/Grammar Learner vẫn đọc cùng canonical records.

## Tiêu chí nghiệm thu và phụ thuộc

Một nội dung có một nguồn canonical, được tra cứu sau publish và có lịch sử. Phụ thuộc Search, Media, Audit và ContentRevision.
