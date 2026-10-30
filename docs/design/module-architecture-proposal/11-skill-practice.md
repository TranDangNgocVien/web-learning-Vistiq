# Module: Luyện theo kỹ năng

> Trạng thái hiện tại: Đã có controller/view catalog và runner; đề xuất dùng Attempt chung ở mode Practice thay vì aggregate/session riêng.

## Mục tiêu và ranh giới

Learner chọn bộ bài theo kỹ năng, luyện theo mode Practice, nhận feedback và xem tiến bộ. Bài tập được cấu hình bởi SkillExerciseManagement; câu hỏi vẫn thuộc QuestionBank. Khi bắt đầu, hệ thống tạo Attempt mode Practice cùng cấu trúc attempts/attempt_questions/attempt_answers với mode Exam.

## Chức năng cần có

- Chọn kỹ năng, mục tiêu, độ khó/chủ đề và bộ bài khả dụng.
- Bắt đầu attempt Practice, trả lời câu, nghe/đọc nội dung và xem feedback theo policy.
- Lưu tiến độ, làm lại câu sai, đánh dấu nội dung cần ôn.
- Kết quả theo kỹ năng/loại câu và gợi ý bước luyện tiếp theo.
- Tiếp tục phiên dở nếu chính sách cho phép.

## Yêu cầu giao diện

Learner thấy rõ skill, tiến độ và hành động tiếp theo; tách chế độ luyện khỏi giao diện làm đề tính giờ. Listening cần playback dễ dùng; Reading giữ passage kề câu hỏi; lời giải xuất hiện đúng thời điểm theo cấu hình. Trạng thái lưu/đồng bộ hiển thị; trên mobile control chạm đủ lớn, keyboard và screen reader sử dụng được.

## Validation

- Exercise đã publish và phù hợp với skill/filter; câu snapshot thuộc bộ bài tại thời điểm bắt đầu.
- Chỉ chủ phiên hoặc người có quyền truy cập mới đọc/cập nhật.
- Answer khớp question type; không chấp nhận answer sau khi session đóng nếu không hỗ trợ.
- Retry không nhân đôi câu trả lời/điểm; scoring theo rule của practice, không theo exam.
- Giới hạn số câu/session và payload.

## CRUD và vòng đời

Learner không CRUD cấu hình bài tập; chỉ tạo/update/complete attempt mode Practice, answer và bookmark nếu có. Admin CRUD bộ bài nằm ở module SkillExerciseManagement. Practice không có bảng attempt riêng; lịch sử vẫn giữ được khi exercise archive.

## Quyền và audit

Dùng auth/active learner và owner policy. Ghi sự kiện hoàn thành/điểm để Analytics; không ghi nội dung câu trả lời nhạy cảm vào log ứng dụng.

## Kế hoạch test

- Feature: catalog filter, start, answer, feedback, complete và resume.
- Authorization: không đọc session người khác; exercise draft/archived không mở được.
- Validation: question ngoài exercise, skill lệch, payload sai, session closed.
- Domain unit: policy mode Practice, tiến độ, scoring và câu sai cần ôn.
- Reliability: refresh/retry và submit answer lặp không nhân điểm.
- Regression: practice attempt dùng chung attempt_questions/attempt_answers nhưng không bị tính vào thống kê/leaderboard mode Exam.
- Browser: keyboard, audio, responsive, lưu trạng thái khi mạng chập chờn.

## Tiêu chí nghiệm thu và phụ thuộc

Attempt Practice và Exam dùng chung cấu trúc dữ liệu và runner; khác biệt do mode policy quyết định (thời gian, feedback, retry, điều hướng và scoring). Không tạo practice_sessions riêng cho tiến độ làm bài. Phụ thuộc SkillExerciseManagement, QuestionBank, Auth, shared Attempts và Analytics.
