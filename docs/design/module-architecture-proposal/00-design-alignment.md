# Đề xuất căn cứ giao diện cho tài liệu module

> Trạng thái: Proposal. Tài liệu này không thay thế hoặc sửa docs/design/design.md.

## Tài liệu đã đối chiếu

- docs/design/design.md có đặc tả chi tiết riêng cho /login và /register: bố cục hai cột trên desktop, tranh sáng/đêm, màu, font Be Vietnam Pro, hành vi nút đèn soi mật khẩu, hỗ trợ keyboard/touch, reduced motion, giữ nguyên backend/routes và các tiêu chí kích thước 375/768/1024/1440px.
- AGENTS.md quy định riêng cho Admin: đưa nội dung và control làm việc lên trước; không thêm hero/banner/stat trang trí lớn; heading ngắn; form, table, list, search hoặc editor xuất hiện ngay sau heading.
- Các đặc tả UI riêng từng module được đặt tại docs/design/modules/<module>/design.md; index là docs/design/modules/README.md.

## Cách áp dụng trong bộ tài liệu

1. Module Authentication phải giữ nguyên phạm vi và tiêu chí của docs/design/design.md. Spotlight password chỉ áp dụng biến thể login/register được nêu trong tài liệu; không tự mở rộng sang forgot/reset hoặc màn Admin.
2. Admin modules theo AGENTS.md và quy tắc chức năng trong tài liệu này.
3. Các module Learner khác tiếp tục dùng layout, tokens và pattern đang có. design.md hiện không phải design system toàn sản phẩm, nên không suy diễn màu auth thành màu của các màn này.
4. Mỗi module mô tả bố cục chức năng, trạng thái rỗng/loading/error, responsive và thao tác keyboard cần có. Token màu/font cụ thể chỉ ghi khi đã có nguồn trong tài liệu hoặc UI hiện hữu.
5. Thay đổi giao diện login/register phải giữ nguyên input thật, validation, CSRF, route, tên trường, autocomplete, password manager và remember-me theo design.md.

## Quy tắc Admin đang có

- Ưu tiên nội dung và control làm việc.
- Heading ngắn, mô tả đúng chức năng.
- Không đặt banner marketing hoặc khối trang trí làm nội dung chính bị đẩy xuống.

## Ranh giới tài liệu

docs/design/design.md hiện là thiết kế riêng cho Authentication. Không ghi đè file đó hoặc áp màu auth toàn sản phẩm. Bộ docs/design/modules/<module>/design.md là đề xuất UI cho các module còn lại, giữ tokens và shell hiện hữu cho đến khi có design system chung được duyệt.
