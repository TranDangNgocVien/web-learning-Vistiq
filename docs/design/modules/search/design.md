# Design: Tra cứu

> Proposal. Căn cứ chung: docs/design/modules/README.md; dùng visual shell Learner hiện có.

## Mục tiêu

Giúp learner tìm nhanh nghĩa, cách dùng và nội dung kiến thức đã công bố.

## Bố cục

- Search field là điểm đầu tiên và duy nhất cần nổi bật; filter loại nội dung/ngôn ngữ đặt cạnh hoặc ngay dưới.
- Autocomplete gợi ý tối đa một danh sách ngắn, không che bàn phím trên mobile.
- Kết quả có term/title, loại nội dung, nghĩa/summary, ví dụ và link detail.
- Trang detail có nội dung đầy đủ, related items và hành động lưu nếu được bật.
- Admin global search nhóm kết quả theo module và có nhãn loại record.

## Hành vi và trạng thái

- Debounce gợi ý; Enter mở kết quả; Escape đóng gợi ý; mũi tên duyệt item.
- No result nêu query đã tìm và gợi ý bỏ filter/sửa chính tả.
- Draft/archived không xuất hiện ở Learner.
- Lỗi mạng không xóa query; cho phép thử lại.

## Responsive và accessibility

Search label không chỉ dựa placeholder; autocomplete theo combobox/listbox ARIA. Highlight match được escape/sanitize. Kết quả xếp một cột trên mobile, không có hover-only action.

## Tiêu chí review

- Cùng một bản ghi published cho kết quả nhất quán ở page/API.
- Query và filter giữ nguyên khi mở rồi quay lại kết quả.
- Search module không cung cấp CRUD knowledge.
