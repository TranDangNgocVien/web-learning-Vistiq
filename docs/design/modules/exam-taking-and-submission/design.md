# Design: Làm bài và nộp bài

> Proposal. Căn cứ chung: docs/design/modules/README.md; auth-only design nằm trong docs/design/design.md.

## Mục tiêu

Cho learner làm bài ổn định, thấy tiến độ/lưu bài, nghe hoặc đọc stimulus và hoàn tất attempt đúng mode.

## Runner dùng chung

- Một runner cho mọi Attempt; mode Practice/Exam chọn policy, không tạo hai giao diện/cấu trúc trả lời khác nhau.
- Header gọn có tên nguồn bài, nhãn mode, trạng thái lưu và tiến độ.
- Vùng chính hiển thị stimulus và câu hỏi; điều hướng câu/part ở panel bên cạnh trên desktop, drawer hoặc navigator gọn trên mobile.
- Footer cố định vùng thao tác câu trước/sau và nộp bài; trạng thái focus không bị che.
- Dialog submit nêu số câu đã trả lời/chưa trả lời và cho phép quay lại.

## Khác biệt do mode

- Practice: theo cấu hình có thể không giới hạn giờ, hiện feedback/giải thích sau câu hoặc cho retry.
- Exam: timer server-backed, giữ feedback đến khi submit, tuân thủ điều hướng và thời lượng đề.
- Mode badge, timer/feedback controls và quy tắc submit là khác biệt; layout, autosave, question navigation và answer components dùng chung.

## Hành vi và trạng thái

- Trạng thái “Đã lưu”/“Đang lưu”/“Chưa lưu” nhìn thấy và có text.
- Kết nối gián đoạn có cảnh báo và retry, không giả báo lưu thành công.
- Listening audio player có play/pause/volume/progress và accessible labels; Reading giữ passage đọc được khi đổi câu.
- Hết giờ nộp theo server và giải thích trạng thái; pending grading có nhãn rõ.

## Responsive và accessibility

Keyboard có thể di chuyển qua câu và đáp án; focus indicator rõ; vùng chạm đủ lớn. Bảo đảm screen reader đọc câu hiện tại và trạng thái save. Respect reduced-motion và không dùng màu làm tín hiệu duy nhất.

## Tiêu chí review

- Answer đã xác nhận vẫn tồn tại sau refresh/retry.
- Hai mode có cùng runner/data shape, khác policy như định nghĩa.
- Không thể sửa answer sau trạng thái đóng nếu policy không cho phép.
