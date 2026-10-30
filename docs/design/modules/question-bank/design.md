# Design: Ngân hàng câu hỏi

> Proposal. Căn cứ chung: docs/design/modules/README.md và AGENTS.md.

## Mục tiêu

Giúp biên tập viên tìm, kiểm tra, tạo và nhập câu hỏi nhanh; nội dung cần đọc/nghe được nhìn thấy cùng câu hỏi trong lúc biên tập.

## Bố cục

- Heading ngắn “Ngân hàng câu hỏi”, ngay sau là tabs Listening / Reading / THPT QG / Tất cả.
- Thanh filter một hàng khi đủ rộng: mã/từ khóa, kỳ thi, part, trạng thái, loại câu, độ khó; nút tìm và xóa lọc.
- Bảng là vùng chính: mã, nội dung rút gọn, skill/part, nhóm/stimulus, loại câu, trạng thái, cập nhật, thao tác.
- Chọn một hàng mở preview bên phải trên desktop; mobile chuyển thành trang chi tiết hoặc drawer toàn màn hình.
- Editor chia phần: phân loại; nội dung/stimulus; lựa chọn/đáp án; giải thích; trạng thái và preview.
- Import mở thành panel riêng có các bước: file → nhận diện sheet/cột → xem câu và đoạn văn/audio → sửa/gắn nội dung thiếu → xác nhận import.

## Hành vi và trạng thái

- Listening hiển thị audio player/nhóm audio và các câu liên kết.
- Reading luôn cho thấy passage; nếu file chỉ có câu hỏi, cho phép thêm đoạn văn ngay trong bước preview/import, không để câu hỏi xuất hiện như bài đọc rỗng.
- THPT cho xem câu, lựa chọn và loại bài theo cấu trúc đề.
- Draft/review/published/archive có nhãn chữ và trạng thái trợ năng.
- Editor có thể gửi revision đi duyệt; quyết định approve/trả chỉnh sửa nằm ở hàng đợi ContentApproval. QuestionBank chỉ publish revision đã duyệt theo quyền.
- Import báo số dòng hợp lệ/lỗi, lỗi cạnh dòng, nút xác nhận chỉ bật khi preview hợp lệ.
- Có empty state hướng dẫn tạo/import; lỗi tải có nút thử lại; lưu thành công thông báo rõ.

## Responsive và accessibility

Bảng có thể cuộn ngang có chỉ báo; thao tác không chỉ dựa vào hover. Form có label thật, error summary, focus order và keyboard operation cho picker. Audio controls có tên accessible.

## Tiêu chí review

- Nội dung làm việc nằm trên fold, không có banner giới thiệu.
- Preview thể hiện đúng passage/audio gắn với câu.
- Reviewer được mở đúng revision trong ContentApproval; nút approve không xuất hiện trong editor QuestionBank.
- Không thể xác nhận import khi còn lỗi blocker; thông báo cho biết cách sửa.
