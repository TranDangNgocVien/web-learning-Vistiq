# Design: Quản lý đề thi

> Proposal. Căn cứ chung: docs/design/modules/README.md và AGENTS.md.

## Mục tiêu

Biên tập viên dựng, kiểm tra và phát hành đề mà luôn thấy cấu trúc part, câu hỏi đã chọn và lỗi cần xử lý.

## Bố cục

- Trang danh sách có heading ngắn, nút tạo đề, search/filter và bảng đề theo mã, kỳ thi, trạng thái, số part/câu, revision và cập nhật.
- Builder desktop dùng vùng chính cho cấu trúc part/câu; panel phụ cho metadata/rule hoặc kiểm tra. Không dùng hero.
- Header công cụ giữ các thao tác Lưu nháp, Kiểm tra, Xem trước và Gửi duyệt. Phát hành chỉ xuất hiện sau khi revision được duyệt và người dùng có quyền publish.
- Question picker mở dạng panel/drawer, có filter và kết quả từ QuestionBank; mỗi câu hiển thị part, type và stimulus.
- Preview dùng layout learner thật, mở ở tab/panel riêng để không mất trạng thái builder.
- Mobile chuyển sang các bước Metadata → Parts → Preview; draft được lưu trước khi rời trang.

## Hành vi và trạng thái

- Lỗi publish gom thành checklist có liên kết tới part/câu lỗi.
- Part có thể thu gọn; tổng câu và điểm cập nhật khi thêm/bỏ câu.
- Trạng thái draft, chờ duyệt, đã duyệt, published, archived luôn có text.
- Review/approve/trả chỉnh sửa thực hiện tại ContentApproval; builder chỉ gửi duyệt và phát hành revision đã approved.
- Có cảnh báo revision xung đột và lựa chọn tải lại/so sánh trước khi ghi.
- Empty state của picker giải thích filter đang áp dụng; preview draft có nhãn rõ.

## Responsive và accessibility

Không cần kéo-thả để sắp xếp; cung cấp nút lên/xuống và keyboard. Mọi lỗi có focus target. Phần preview dùng headings theo thứ bậc và player controls có label.

## Tiêu chí review

- Lưu, validate, preview và publish luôn tìm thấy được.
- Approve không nằm trong builder và đề chưa được duyệt không có thao tác phát hành thường.
- Không phát hành được khi còn blocker và người dùng biết cách tới lỗi.
- Builder không thay đổi attempt snapshot đã phát sinh.
