# Design: Kho kiến thức

> Proposal. Căn cứ chung: docs/design/modules/README.md và AGENTS.md.

## Mục tiêu

Biên tập viên duy trì vocabulary, grammar và loại kiến thức mở rộng bằng một thư viện có thể lọc, preview và duyệt.

## Bố cục

- Heading “Kho kiến thức”, bộ lọc loại nội dung, ngôn ngữ, topic, trạng thái và ô tìm.
- Bảng/list hiển thị term/tiêu đề, loại, topic, revision, trạng thái và cập nhật.
- Editor chia metadata, nội dung chính, ví dụ/liên kết media, related items và publish state.
- Preview learner đặt cạnh editor trên desktop, thành tab trên mobile.
- Import/export nằm trong toolbar phụ, không chiếm khu vực trên đầu trang.

## Hành vi và trạng thái

- Form trường thay đổi theo loại grammar/vocabulary; trường bắt buộc được ghi rõ.
- Revision compare hiển thị khác biệt và reviewer note.
- Draft không hiện trong learner preview công khai; archived được cảnh báo khi có liên kết.
- Empty state đề xuất tạo item hoặc import; lỗi import chỉ ra từng dòng.

## Responsive và accessibility

Editor theo một cột trên màn hẹp; tab có semantics; label/error thật cho từng trường. Rich text có keyboard toolbar và preview sanitized.

## Tiêu chí review

- Admin có thể phân biệt draft với nội dung đã công bố.
- Preview phản ánh đúng nội dung learner sẽ tra cứu.
- Tài liệu dùng chung shell hiện có, không áp hiệu ứng auth.
