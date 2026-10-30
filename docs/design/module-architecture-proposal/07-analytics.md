# Module: Phân tích và thống kê

> Trạng thái hiện tại: Đã có AnalyticsService, dashboard và leaderboard; cần xác định bộ chỉ số và quyền dữ liệu.

## Mục tiêu và ranh giới

Cung cấp truy vấn tổng hợp cho Admin về lượt thi, điểm, tiến độ học, kỹ năng và nội dung. Không sở hữu dữ liệu nguồn và không chỉnh sửa attempt. Leaderboard là một màn hình/query thuộc module này.

## Chức năng cần có

- KPI có định nghĩa rõ: lượt bắt đầu/hoàn tất, tỷ lệ hoàn tất, điểm trung bình, kết quả theo part/skill và xu hướng thời gian.
- Lọc theo kỳ, loại đề, kỹ năng, trạng thái và khoảng ngày.
- Drill-down từ số tổng hợp sang danh sách được phép xem.
- Leaderboard theo đề/chu kỳ với tie-breaker và thời điểm cập nhật rõ.
- Xuất báo cáo CSV và XLSX theo bộ lọc hiện tại; có giới hạn phạm vi và audit.
- Phân tích Practice/Exam từ cùng bảng attempts, so sánh theo mode và giữ định nghĩa mẫu số nhất quán.

## Yêu cầu giao diện

Admin theo AGENTS.md; KPI phải phục vụ quyết định, không biến thành banner trang trí. Bộ lọc nằm gần bảng/biểu đồ. Có chú giải, đơn vị, thời gian cập nhật, trạng thái không có dữ liệu và bảng thay thế cho biểu đồ. Nút xuất CSV/XLSX gần bộ lọc và xuất đúng phạm vi đang xem. Responsive và không dùng màu đơn lẻ để truyền đạt xu hướng.

## Validation

- Date range hợp lệ, có giới hạn độ dài truy vấn và timezone thống nhất.
- Bộ lọc lấy từ catalog; không nhận SQL/order field tùy ý.
- Aggregation bỏ dữ liệu test/cancelled theo định nghĩa chỉ số đã chốt.
- Drill-down áp dụng cùng permission và row-level access như danh sách nguồn.
- Export giới hạn số dòng, lọc PII, kiểm tra permission và chống CSV formula injection.
- Mỗi báo cáo có tiêu đề, thời điểm tạo, timezone, bộ lọc và chú giải các chỉ số.

## CRUD và vòng đời

Read-only đối với dữ liệu nguồn. Có thể tạo export job/cache; các job này không thay đổi dữ liệu nghiệp vụ. Leaderboard reset chỉ là command đặc quyền, cần xác nhận và audit; không xóa attempt.

## Quyền và audit

results.view là cơ sở hiện có. Đề xuất permission riêng cho export và reset nếu cần tách quyền. Audit export, reset hoặc truy vấn dữ liệu nhạy cảm.

## Kế hoạch test

- Unit: công thức, mẫu số, quy tắc thời gian và tie-breaker.
- Feature: filter, dữ liệu rỗng, khoảng ngày biên và quyền xem.
- Regression: thống kê khớp tập dữ liệu fixture đã biết; cancelled/test attempts theo đúng chính sách.
- Privacy: không lộ email/tên nếu view chỉ cần aggregate.
- Export: quyền, giới hạn dòng, công thức CSV injection và tên cột.
- Performance: số lượng lớn dùng aggregate/query phân trang; không N+1.
- Browser: biểu đồ có text/table alternative và filter giữ nguyên khi drill-down.

## Tiêu chí nghiệm thu và phụ thuộc

Mỗi chỉ số có công thức/nguồn dữ liệu được ghi trong tài liệu; CSV/XLSX xuất đúng bộ lọc; drill-down không vượt quyền. Phụ thuộc Attempts, mode policies, Exams, SkillExercises, Users và leaderboard rules.
