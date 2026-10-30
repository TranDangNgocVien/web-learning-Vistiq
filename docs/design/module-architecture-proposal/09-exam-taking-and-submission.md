# Module: Làm bài và nộp bài

> Trạng thái hiện tại: Đã có AttemptRunnerController, AttemptService và route start/save/submit/result; cần kiểm chứng các trạng thái và bảo vệ dữ liệu.

## Mục tiêu và ranh giới

Learner dùng một runner và Attempt aggregate chung cho Practice/Exam. Nguồn câu hỏi/cấu hình mode khác nhau; attempts, attempt_questions và attempt_answers là cấu trúc lưu chung. Domain Assessment áp policy theo mode; Admin Attempts giám sát/chấm cả hai mode.

## Chức năng cần có

- Chọn đề thi hoặc bài tập kỹ năng; bắt đầu/resume attempt với mode Practice hoặc Exam.
- Render đúng snapshot đề, media, thời gian và tiến độ.
- Lưu answer/event/section/pause/resume; trạng thái lưu được xác nhận.
- Nộp bài thủ công hoặc đóng khi hết giờ; xử lý retry an toàn.
- Hiện kết quả, câu đúng/sai và lời giải theo policy; câu chấm tay có trạng thái pending.
- Cho người học xem lịch sử các lượt làm của chính mình: bài/đề, mode, ngày làm, trạng thái, điểm, thời gian làm và kết quả/đáp án nếu policy cho phép; hỗ trợ mở lại chi tiết từng lượt đã hoàn tất.
- Hiển thị bảng xếp hạng theo đề thi với các cột **Tên**, **Điểm**, **Thời gian làm**. Mỗi người học có một dòng đại diện cho lượt hợp lệ có điểm cao nhất; nếu bằng điểm thì thời gian làm ngắn hơn xếp trên, nếu vẫn bằng thì đồng hạng.
- Bảng xếp hạng chỉ gồm lượt đã nộp và đủ điều kiện công bố theo cấu hình đề; không đưa email hoặc thông tin định danh riêng tư vào danh sách.

## Yêu cầu giao diện

Learner ưu tiên độ rõ và độ tin cậy khi làm bài: câu hỏi, nội dung/audio, tiến độ, bộ đếm thời gian và hành động lưu/nộp. Cảnh báo rời trang/submit rõ nhưng không cản trở keyboard/screen reader. Listening phải có player điều khiển được; Reading giữ đoạn văn và câu hỏi dễ đối chiếu. Trang lịch sử có danh sách lượt làm, trạng thái rỗng và cách mở chi tiết; trang xếp hạng dùng bảng gọn với Tên, Điểm, Thời gian làm, thứ hạng và phân trang. Đánh dấu lượt của người đang đăng nhập mà không dựa riêng vào màu sắc. Responsive và không làm mất trạng thái do đổi viewport.

## Validation

- Người dùng đăng nhập, active, sở hữu attempt; đề đã publish và còn mở.
- Lịch sử chỉ truy vấn attempt của người đang đăng nhập; endpoint chi tiết vẫn kiểm tra quyền sở hữu, không dựa vào ID ẩn trên giao diện.
- Leaderboard chỉ nhận attempt đã hoàn tất, có điểm cuối và được phép công bố; mỗi user chỉ lấy lượt hợp lệ tốt nhất của đúng đề. Tên hiển thị được lọc theo privacy policy; không trả email hoặc dữ liệu tài khoản khác.
- Answer khớp question snapshot/type; giới hạn payload, sanitize text và xác thực asset recording/audio.
- Server là nguồn chuẩn cho mode, thời gian, scoring và trạng thái; không tin timer/client score.
- Điểm xếp hạng lấy từ kết quả đã chốt phía server. Thời gian làm được tính từ event/timestamp server và trừ thời gian pause nếu mode cho phép tạm dừng.
- Save/submit idempotent, chống request trùng; không nhận thay đổi sau submitted/expired.
- Chỉ media của attempt hợp lệ mới tải được; không lộ asset ID của attempt khác.
- CSRF, throttle và policy áp dụng cho mọi mutation.

## CRUD và vòng đời

Learner không CRUD attempt tùy ý. Các command chung: start, answer, pause/resume khi policy cho phép, section, recording/audio, submit. Mode Exam có thời hạn và chính sách feedback theo đề; mode Practice có feedback/retry theo cấu hình. Cả hai lưu attempt_questions snapshot và attempt_answers trong cùng cấu trúc; attempt không hard delete. Lịch sử là truy vấn chỉ đọc trên attempt của chủ sở hữu. Bảng xếp hạng cũng chỉ đọc; module này cung cấp màn hình learner và gọi query/service công khai của Analytics, không tự tạo bản sao scoring hoặc leaderboard query.

## Quyền và audit

Yêu cầu auth/active learner và owner policy cho lịch sử/kết quả. Leaderboard tuân theo visibility policy của đề và chỉ công bố các trường Tên, Điểm, Thời gian làm. Ghi event quan trọng: start, submit, expiry, pause/resume, answer save lỗi hoặc thao tác bất thường theo retention policy.

## Kế hoạch test

- Feature: start, load, save answer, resume, submit, result và pending grading.
- History: người học chỉ xem được lượt của mình; lượt đang làm, đã nộp, hết giờ và pending grading hiển thị đúng trạng thái/chính sách.
- Leaderboard: chỉ lấy lượt đã nộp và được công bố; một dòng tốt nhất cho mỗi người; điểm cao hơn/thời gian ngắn hơn và đồng hạng được tính đúng; không lộ email/PII.
- Authorization: user khác không đọc/sửa attempt; inactive user bị chặn.
- Validation: đề unpublished, answer sai type, attempt hết hạn/đã submit, asset ngoài attempt.
- Reliability: submit/save gửi hai lần; timeout trong lúc lưu; thứ tự answer đảo; retry không tạo bản ghi trùng.
- Domain unit: transitions, timing và scoring theo snapshot.
- Security: client sửa timer/score/user ID không có tác dụng; media endpoint có scope.
- Browser: refresh/reconnect, keyboard, audio, reduced motion và viewport mobile.

## Tiêu chí nghiệm thu và phụ thuộc

Không mất answer đã xác nhận khi refresh/retry; submit chỉ tạo một kết quả cuối; kết quả cũ không đổi theo câu nguồn. Người học xem được lịch sử cá nhân và leaderboard đúng policy, với Tên + Điểm + Thời gian làm; dữ liệu xếp hạng do Analytics cung cấp, không nhân đôi logic. Phụ thuộc Exams, SkillExerciseManagement, QuestionBank snapshots, Auth, Media, shared Scoring và Analytics leaderboard query.
