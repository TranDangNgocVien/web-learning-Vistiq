# Vistiq — thiết kế đăng nhập và đăng ký

## Hướng thiết kế

Trang `/login` và `/register` lấy bố cục gọn từ reel Facebook mà người dùng gửi: một khung đăng nhập hai cột, phong cảnh ở cột trái và form ở cột phải. Điểm tương tác chính là nút duy nhất ở cuối ô mật khẩu: mắt nhắm tương ứng trời sáng; khi bật đèn, toàn bộ giao diện chuyển sang đêm và vùng sáng di chuyển theo con trỏ để soi mật khẩu.

## Màu sắc và chữ

| Vai trò | Ban ngày — mặc định | Ban đêm — bật đèn |
| --- | --- | --- |
| Nền ngoài | `#E7E8EB` | `#07121C` |
| Bề mặt form | `#F7F7F8` | `#081722` |
| Trường nhập | `#FFFFFF` | `#07141F` |
| Chữ chính | `#18181B` | `#F5F8FC` |
| Chữ phụ | `#71717A` | `#9CAEBB` |
| Viền và focus | Xám trung tính | Xám sáng trung tính |
| CTA | Nền đen, chữ trắng | Nền trắng, chữ đen |
| Ánh sáng mặt trời/đèn | Vàng ấm | Vàng dịu trên nền tối |

Font Be Vietnam Pro hiện có được giữ lại. Tiêu đề form 28px trên desktop, 25px trên điện thoại; nội dung mô tả 12px; input 16px; nhãn được giữ trong cây trợ năng và placeholder hỗ trợ nhận diện trường. Mật khẩu dùng chữ đơn cách để lớp soi trùng khít với input thật.

## Bố cục và minh họa

- Desktop dùng khung rộng tối đa 980px, cao tối thiểu 540px, chia cột khoảng 44/56, bo góc 22px và viền xám mảnh.
- Cột trái là thẻ phong cảnh bo góc: trời sáng có mây, mặt trời vàng, núi xám, cây lá và hoa; trời đêm chuyển sang sao, trăng bạc và núi tối. Mỗi lần đổi trạng thái, mặt trời đi chéo xuống sau núi và trăng đi lên theo quỹ đạo ngược trong khoảng 2,4 giây; tắt đèn chạy chu trình đảo chiều. Logo Vistiq nằm phía trên; nội dung học tiếng Anh nằm ở cuối tranh.
- Cột phải căn giữa form trong bề rộng tối đa 380px. Tiêu đề và mô tả căn giữa; trường nhập có icon và viền dịu. Giao diện form dùng đen, trắng và xám ở cả hai trạng thái; màu xanh lá không xuất hiện ở CTA hay thành phần form.
- Nút đóng khung ở góc trên bên phải có vùng bấm tối thiểu 42px. Link quên mật khẩu và đăng ký giữ vị trí gần form; chức năng ghi nhớ đăng nhập vẫn dùng được.
- Nền ngoài khung đổi đồng bộ giữa nền sáng và nền đêm có sao, hòa cùng cảnh bên trong. Bề mặt form, chữ, input và nút CTA cũng chuyển tông cùng phong cảnh.
- Từ 768px trở xuống, bố cục chuyển thành một cột. Tranh rút xuống khoảng 226px; form vẫn đủ rộng cho thao tác chạm và trang không tràn ngang.

Logo SVG chữ V hình học trắng trong ô tối, có viền sáng mảnh. Giữ tên Vistiq, dùng logo chung hiện tại cho header, favicon và trang auth. Trang chủ giữ nhận diện và màu riêng như trước.

## Form và đèn pin

- Mỗi trường mật khẩu có đúng một nút. Khi tắt, nút hiển thị mắt nhắm; khi bật, biểu tượng đổi thành đèn pin. Không có nút ẩn/hiện thứ hai.
- Input gốc tiếp tục là `type="password"` khi bật đèn. Trên máy tính, tia mở 30 độ hướng theo con trỏ trong khung auth. Phần chữ ngoài vùng chiếu vẫn là dấu che; phần trong vùng chiếu được làm rõ bằng lớp phủ `clip-path` dùng cùng hình học với tia sáng.
- Lớp chữ phụ có `aria-hidden`, không nhận thao tác và chỉ được cập nhật khi đèn bật. Giá trị thật, validation, CSRF, routes, tên trường, autocomplete, password manager và ghi nhớ đăng nhập giữ nguyên.
- Chuyển động hướng đèn dùng `requestAnimationFrame`, tối đa một lần mỗi khung hình. Đồng bộ khi gõ, dán, autofill, cuộn ngang, resize và đổi con trỏ.
- Mỗi thời điểm chỉ bật một trường. Chọn đèn khác sẽ chuyển nguồn sáng. Escape, rời khung, mất focus cửa sổ, ẩn tab hoặc gửi form sẽ tắt đèn, xóa bản chữ rõ và đưa giao diện về ban ngày.
- Trên cảm ứng hoặc bàn phím, cùng nút chuyển giữa che và hiện toàn bộ mật khẩu; không yêu cầu rê chuột. `prefers-reduced-motion` giảm chuyển động phụ và hướng đèn được cập nhật trực tiếp; quỹ đạo mặt trời/trăng vẫn chuyển động khi người dùng chủ động đổi trạng thái đèn.
- Nếu JavaScript không chạy, input password thật vẫn nhập và gửi form bình thường; nút tăng cường chỉ xuất hiện khi đã khởi tạo được hành vi.

## Phạm vi

CSS và JavaScript mới chỉ áp dụng trong biến thể `data-auth-spotlight` của login/register. Không đổi routes, backend, schema, dependency hay màu trang chủ. Quên mật khẩu, đặt lại mật khẩu và quản trị giữ giao diện hiện có; favicon dùng logo chung.

## Tiêu chí nghiệm thu

- Bố cục gọn và không tràn ngang ở 375, 768, 1024 và 1440px.
- Mắt nhắm hiển thị trọn giao diện ban ngày, mặt trời và cây hoa; bật đèn chuyển toàn trang sang nền đêm có sao, mặt trời lặn và trăng đi lên.
- Mắt đóng đổi thành đèn pin khi bật, vùng chiếu đi cùng con trỏ và ký tự ngoài vùng chiếu luôn được che.
- Đăng ký hỗ trợ soi riêng từng trường mật khẩu; bật nhanh, chuyển nguồn, Escape, rời khung, đổi tab và submit đều dọn lớp chữ rõ.
- Nhập, dán, autofill, mật khẩu dài, cuộn ngang, bàn phím, cảm ứng, validation và reduced motion vẫn hoạt động.
