# Design: Duyệt câu hỏi và đề thi

> Proposal. Căn cứ chung: docs/design/modules/README.md và AGENTS.md.

## Mục tiêu

Giúp reviewer xử lý câu hỏi và đề thi theo đúng revision, thấy rõ nội dung và quyết định mà không nhầm approve với publish.

## Bố cục

- Heading ngắn, ngay dưới là tabs Tất cả / Câu hỏi / Đề thi, bộ lọc và bảng queue.
- Bảng gồm loại, mã/tên, kỳ thi/part, người gửi, revision, thời gian chờ, blocker và thao tác Mở duyệt. Badge số chỉ gắn với queue tương ứng.
- Trang chi tiết mở nội dung preview cạnh metadata/decision panel trên desktop; mobile xếp preview trước, decision panel sau.
- Question preview hiển thị đầy đủ prompt, đáp án, giải thích và reading/listening stimulus. Exam preview hiển thị part, câu hỏi, thời lượng, điểm và lỗi validation.
- Nếu có bản cũ, cho phép so sánh revision cũ/mới và đánh dấu phần khác biệt bằng nhãn, không chỉ bằng màu.
- Khu quyết định có hai action: Duyệt và Trả chỉnh sửa. Trả chỉnh sửa yêu cầu lý do; Duyệt yêu cầu xác nhận gọn có tên revision.
- Approved hiển thị trạng thái Chờ xuất bản và link mở module nguồn nếu người dùng có content.publish. Không có nút Publish trên màn hàng đợi.

## Hành vi và trạng thái

- Sau quyết định thành công, thông báo trạng thái mới và cập nhật queue; giữ khả năng quay lại bản ghi.
- Revision đã bị tác giả thay đổi hoặc reviewer khác xử lý hiện thông báo conflict, tải nội dung mới và không phát lại quyết định.
- Không có mục chờ thì hiển thị empty state giải thích cách gửi câu hỏi/đề thi đi duyệt theo quyền.
- Lỗi validation gắn với câu hỏi/part cụ thể; lỗi quyền hoặc lỗi tải có hướng khắc phục rõ.
- Hiển thị trạng thái bằng chữ: Chờ duyệt, Đã duyệt, Trả chỉnh sửa, Chờ xuất bản.

## Responsive và accessibility

Bảng có cột ưu tiên hoặc chuyển thành danh sách trên màn hẹp. Tabs, filters, preview, diff và action dùng được bằng keyboard; headings và labels mô tả loại nội dung/revision cho screen reader. Dialog giữ focus và trả focus về action sau khi đóng. Không dựa riêng vào màu hoặc chuyển động để biểu đạt khác biệt.

## Tiêu chí review

- Reviewer thấy đúng nội dung đầy đủ của revision trước khi quyết định.
- Trả chỉnh sửa yêu cầu lý do; approve không tự xuất bản nội dung.
- Question và Exam dùng một queue nhưng preview thể hiện cấu trúc riêng theo loại.
- Không dùng hero, banner hay khối thống kê lớn phía trên bảng.
