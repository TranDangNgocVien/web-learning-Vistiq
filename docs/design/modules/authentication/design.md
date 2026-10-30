# Design: Authentication

> Proposal. Đặc tả canonical login/register đã có ở docs/design/design.md; file này xác định phạm vi module và không tạo visual spec thứ hai.

## Phạm vi áp dụng

- Login/register: áp dụng đầy đủ docs/design/design.md, gồm bố cục hai cột desktop 44/56, tranh ngày/đêm, spotlight password, Be Vietnam Pro, responsive từ 768px, keyboard/touch, reduced motion và tiêu chí viewport.
- Admin login/OTP, forgot-password và reset-password: giữ giao diện hiện tại. Không tự áp theme/spotlight của learner auth.
- Các route, input name, validation, CSRF, autocomplete, remember-me, password manager và backend contract giữ nguyên theo canonical design.

## Hành vi và trạng thái

Thể hiện lỗi cạnh trường và summary khi cần; loading không cho gửi lặp; thông báo đăng nhập/reset không tiết lộ account tồn tại. OTP có trạng thái gửi lại theo throttle. JavaScript enhancement không phải điều kiện để form hoạt động.

## Responsive và accessibility

Tuân theo breakpoints, keyboard/touch support, focus, reduced-motion và progressive enhancement đã ghi tại docs/design/design.md. Không dùng motion nếu người dùng tắt motion ngoài ngoại lệ được đặc tả rõ.

## Tiêu chí review

- Không có phiên bản design login/register mâu thuẫn với docs/design/design.md.
- Không thay đổi API/form contract khi làm visual redesign.
- Admin auth/recovery được giữ ngoài spotlight scope.
