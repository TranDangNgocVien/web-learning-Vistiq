# Design: Quản lý bài tập theo kỹ năng

> Proposal. Căn cứ chung: docs/design/modules/README.md và AGENTS.md.

## Mục tiêu

Admin tạo một bộ bài theo kỹ năng bằng cách chọn các câu đã có; không nhập lại hoặc nhân bản nội dung câu hỏi.

## Bố cục

- Danh sách bộ bài lọc theo Listening/Reading/skill, topic, level, trạng thái và số câu.
- Editor có metadata, mode settings, list câu theo thứ tự và picker QuestionBank.
- Picker hiển thị preview ngắn, skill/part, stimulus, trạng thái publish và nơi dùng; filter không làm mất selection hiện tại.
- Summary chỉ số câu, thời gian dự kiến, loại câu và completeness nằm sát list, không phải banner.
- Learner preview dùng cùng attempt runner mode Practice.

## Hành vi và trạng thái

- Thêm/bỏ/sắp xếp câu cập nhật thứ tự và tổng số.
- Câu bị archive hoặc mất stimulus đánh dấu ngay trong list; publish bị chặn đến khi sửa.
- Save draft và Publish là hai action riêng; publish confirm nêu bộ câu sẽ mở cho learner.
- Empty picker và bộ bài rỗng có hướng dẫn phù hợp.

## Responsive và accessibility

Picker thành drawer trên mobile; reorder có nút lên/xuống ngoài kéo-thả. Selection được thông báo cho screen reader.

## Tiêu chí review

- Câu trong bộ bài vẫn thuộc QuestionBank, không có editor câu hỏi nhân bản.
- Preview mở đúng mode Practice và cấu hình bộ bài.
- Thay đổi không làm biến đổi attempt đang chạy.
