# Module: Quản lý bài tập theo kỹ năng

> Trạng thái hiện tại: Đề xuất mới/đang phân tán. Repo có màn hình và controller SkillPractice phía Learner nhưng chưa thấy module Admin riêng quản lý bộ bài theo kỹ năng.

## Mục tiêu và ranh giới

Admin tạo bộ bài hoặc lộ trình theo Listening, Reading và kỹ năng khác bằng cách tham chiếu câu hỏi đã có trong QuestionBank. Không sao chép câu hỏi/đáp án sang bản ghi bài tập. Module lưu thứ tự, mục tiêu, cấu hình và điều kiện mở.

## Chức năng cần có

- CRUD bộ bài, nhóm theo kỹ năng/cấp độ/chủ đề và trạng thái.
- Chọn câu hỏi đã publish từ bank; sắp xếp, kiểm tra số lượng và tương thích skill.
- Cấu hình thời lượng, số lần luyện, giải thích hiển thị và thứ tự câu.
- Preview learner; phát hành/ẩn; xem learner nào hoàn thành nếu policy cho phép.
- Tái sử dụng một câu hỏi trong nhiều bộ bài và biết nơi đang tham chiếu.

## Yêu cầu giao diện

Admin theo AGENTS.md. Màn hình editor chia vùng cấu hình bộ bài và picker câu hỏi; picker có filter theo skill/part/trạng thái, thao tác thêm/bỏ rõ ràng và không làm mất cấu hình khi filter. Preview learner ở chế độ đọc. Có cảnh báo khi câu bị archive hoặc bộ bài thiếu số lượng.

## Validation

- Kỹ năng và catalog tồn tại; mỗi câu đã publish và tương thích skill.
- Không nhận question ID do client gửi nếu ngoài tập kết quả user được phép chọn.
- Số câu, thứ tự và cấu hình thời gian nằm trong giới hạn; không có thứ tự trùng.
- Không publish bộ bài rỗng, chứa câu bị archive hoặc passage/audio hỏng.
- Thay đổi nguồn câu hỏi không âm thầm phá bộ bài đã publish; yêu cầu cập nhật hoặc đánh dấu cần sửa.

## CRUD và vòng đời

CRUD bộ bài/cấu hình; link câu hỏi là quan hệ nhiều-nhiều có thứ tự. Archive bộ bài giữ lại lịch sử session. Xóa link không xóa câu hỏi nguồn.

## Quyền và audit

Dùng permission content edit/publish hiện có hoặc permission learning riêng nếu cần tách người biên soạn. Audit thay danh sách câu, thứ tự và trạng thái publish.

## Kế hoạch test

- Feature: tạo bộ bài, gắn/sắp xếp/bỏ câu và preview.
- Validation: câu khác skill, draft/archived, bộ bài rỗng, ID không thuộc phạm vi.
- Domain unit: thứ tự ổn định và kiểm tra cấu hình/đủ câu.
- Authorization: chỉ editor thay đổi; learner không gọi endpoint admin.
- Regression: sửa một câu trong bank không tạo bản sao hoặc sửa session đã bắt đầu.
- Data integrity: archive câu đang gắn tạo cảnh báo; archive bộ bài giữ session cũ.
- Browser: picker filter, keyboard reorder và state khi chưa có kết quả.

## Tiêu chí nghiệm thu và phụ thuộc

Learner nhận đúng tập câu theo bộ bài đã publish và mọi câu vẫn trỏ tới QuestionBank. Phụ thuộc QuestionBank, Assessment catalog, SkillPractice và Analytics.
