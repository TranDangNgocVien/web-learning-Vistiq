# Design: Luyện theo kỹ năng

> Proposal. Căn cứ chung: docs/design/modules/README.md và runner dùng chung ở exam-taking-and-submission/design.md.

## Mục tiêu

Learner chọn bộ câu theo kỹ năng và bắt đầu một Attempt mode Practice. Không tạo practice runner hoặc answer schema riêng.

## Bố cục

- Catalog có filter skill, part, level, topic và thời lượng dự kiến; item cho biết số câu và feedback có sẵn.
- Detail bộ bài tóm tắt mục tiêu, kỹ năng, số câu và nút Bắt đầu luyện.
- Khi bắt đầu, chuyển sang shared attempt runner với mode Practice.
- Kết quả hiển thị điểm/tiến độ, câu sai, feedback và hành động Luyện lại hoặc quay catalog.

## Hành vi và trạng thái

- Practice mode tuân theo cấu hình bộ bài về timer, feedback, retry và điều hướng.
- Tình trạng lưu answer dùng cùng trạng thái save với Exam.
- Attempt Practice không xuất hiện dưới nhãn Exam và không lọt vào leaderboard Exam.
- Bộ bài không khả dụng/đã archive có trang thông báo và đường quay lại.

## Responsive và accessibility

Catalog card có thứ bậc text rõ, không chỉ tô màu skill. Dùng keyboard tương thích shared runner. Kết quả có heading, summary và bảng/list câu sai.

## Tiêu chí review

- Practice mở cùng runner và lưu vào cùng attempts, attempt_questions, attempt_answers.
- Khác biệt UI chỉ phản ánh mode policy, không nhân đôi control không cần thiết.
- Learner nhìn thấy phản hồi theo đúng cấu hình của bộ bài.
