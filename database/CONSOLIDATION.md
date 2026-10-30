# Tinh gọn CSDL Vistiq

Mục tiêu: **43 bảng vật lý, tính cả `migrations`**. Bản `localhost.sql` được cung cấp có 78 bảng; 76 bảng là số sau khi bỏ `subscription_plans` và `user_subscriptions`. Migration mới chuyển 33 bảng nghiệp vụ/hệ thống và lưu trữ dữ liệu cũ của hai bảng mua gói, rồi mới bỏ các bảng nguồn.

## 43 bảng giữ lại

| Nhóm | Bảng |
| --- | --- |
| Tài khoản, quản trị, hệ thống (12) | `users`, `roles`, `permissions`, `role_user`, `permission_role`, `admin_otp_challenges`, `audit_logs`, `system_settings`, `password_reset_tokens`, `sessions`, `user_notifications`, `migrations` |
| Kiến thức (9) | `learning_items`, `learning_item_meanings`, `grammar_topics`, `grammar_rules`, `tags`, `learning_item_tags`, `learning_item_relations`, `user_saved_items`, `user_knowledge_collections` |
| Ngân hàng câu hỏi (7) | `question_bank_items`, `question_options`, `question_accepted_answers`, `question_groups`, `question_types`, `learning_stimuli`, `media_assets` |
| Đề thi (7) | `exam_types`, `exam_parts`, `exam_templates`, `exam_sections`, `exam_section_questions`, `exam_revisions`, `score_scales` |
| Làm bài (4) | `exam_attempts`, `attempt_answers`, `attempt_events`, `attempt_reports` |
| Hỗ trợ (4) | `content_revisions`, `ai_tasks`, `jobs`, `failed_jobs` |

## Dữ liệu được chuyển đi đâu

| Nguồn | Đích |
| --- | --- |
| `user_profiles`, `user_preferences` | Các cột hồ sơ/tùy chọn trên `users`; giữ ID và thời gian của bản ghi cũ để có thể hoàn tác |
| `dictionary_entries` | Phiên âm, audio, ID mục từ cũ và thời gian trên `learning_items` |
| `collocations`, `phrasal_verbs`, `idioms` | JSON thuộc loại tương ứng trên `learning_items`, giữ toàn bộ thuộc tính và ID |
| `collocation_dictionary_entries` | Quan hệ có khóa ngoại trong `learning_item_relations`, loại `collocation_member` |
| `learning_item_meaning_examples`, `grammar_examples` | Mảng `examples` trên nghĩa/quy tắc; giữ tất cả ví dụ, thứ tự, bản dịch, nguồn và ID |
| `attempt_results` | Điểm, trạng thái chấm, người chấm, thời gian và `details` trực tiếp trên `exam_attempts` |
| `attempt_answer_options` | `attempt_answers.response.legacy_selected_options` |
| `answer_grades` | `attempt_answers.grade.legacy_criteria` |
| `exam_policies` | Các cột chính sách trên `exam_templates` |
| `score_scale_ranges` | `score_scales.configuration.ranges`, giữ cấu hình hiện có |
| `question_rubrics` | `question_bank_items.rubrics` |
| `user_activities` | Các bản ghi riêng trong `audit_logs`, có `source_activity_id`; không xóa/ghi đè log kiểm toán cũ |
| `user_achievements`, `saved_item_reviews` | Dữ liệu lịch sử trong bản ghi người dùng/mục đã lưu |
| Danh mục nhỏ, định nghĩa huy hiệu/rubric/tiêu chí | Các nhóm `compact.*` trong `system_settings`, giữ ID, nhãn và thuộc tính tùy chỉnh |
| `cache`, `cache_locks`, `job_batches` | Lưu trữ nội dung cũ trong `system_settings`; cache chạy bằng file. Migration từ chối nếu còn batch đang chạy |
| Hai bảng mua gói | Định nghĩa cũ trong `system_settings`, lịch sử trên `users`; giao diện và thao tác gán gói đã được gỡ |

Các mã loại, mức độ, kỹ năng, chế độ, loại sự kiện được bổ sung trên bản ghi chính. ID danh mục cũ được giữ để biểu mẫu, revision, snapshot và payload đã lưu không bị đổi ý nghĩa. `question_bank_items.learning_item_id` trở thành nullable, không còn UNIQUE và dùng `ON DELETE SET NULL`.

`CompactDatabase` là hợp đồng đọc/ghi tương thích cho các tên dữ liệu cũ. Nó đọc cột chính hoặc chiếu JSON bằng `JSON_TABLE` trên MySQL và `json_each` trên SQLite; **không tạo bảng phụ hay view**. Các thao tác ghi JSON khóa bản ghi sở hữu trước khi cập nhật. Truy vấn JOIN và bộ kiểm tra `exists` đã chuyển sang cùng hợp đồng. Không dùng trực tiếp `Illuminate\Support\Facades\DB::table()` với tên bảng đã bỏ; dùng hợp đồng này khi cần đọc payload cũ.

## Chạy chuyển đổi

Yêu cầu MySQL 8.0+ (bản SQL được cung cấp dùng 8.4.3), dừng queue worker trước khi chạy và khởi động lại sau khi thành công. Không chạy `migrate:fresh`, `db:wipe` hoặc import đè vào CSDL đang dùng.

```powershell
# .env: CACHE_STORE=file
php artisan config:clear
php -d memory_limit=512M artisan vistiq:compact-database
```

Lệnh bật maintenance, tạo backup toàn bộ schema và dữ liệu ở `storage/app/private/database-consolidation/`, lưu SHA-256, chép dữ liệu rồi so sánh số dòng và SHA-256 của **từng dòng, từng cột** sau khi chuẩn hóa kiểu số/JSON. Chỉ khi tất cả nguồn khớp mới bỏ bảng. Dữ liệu không có bản ghi sở hữu, JSON không hợp lệ hoặc lỗi kiểm chứng sẽ làm migration dừng. Nếu chuyển đổi lỗi, maintenance được giữ để xử lý trước khi mở truy cập lại. Chạy lại lệnh tiếp tục dùng manifest đã lưu; không bỏ qua kiểm chứng.

Trong lúc sao chép và kiểm chứng, phpMyAdmin vẫn hiển thị 78 bảng (hoặc 76 nếu hai bảng mua gói đã được bỏ). Dữ liệu từ điển lớn có thể mất nhiều phút. Số bảng chỉ giảm xuống 43 ở cuối quá trình; tải lại trang Structure sau khi lệnh báo thành công.

Trường `backup` và checksum nằm trong nhóm `compact.migration` của `system_settings`. Giữ file backup ở nơi riêng tư, vì có dữ liệu tài khoản. Bản SQL gốc trong Downloads được giữ nguyên.

## Hoàn tác

Chạy trong maintenance và dừng worker. Nếu migration này là migration cuối:

```powershell
php artisan down
php -d memory_limit=512M artisan migrate:rollback --step=1 --force
php artisan up
```

`down()` dựng lại các bảng nguồn từ schema đã ghi nhận, đọc dữ liệu hiện tại theo lô và phục hồi khóa ngoại. Các bản ghi tạo/sửa sau chuyển đổi cũng được phục hồi; các cột bổ sung trên bảng chính được giữ để không làm mất dữ liệu mới. Không phục hồi đè file backup cũ nếu muốn giữ các thay đổi phát sinh sau migration.

## Seed và file SQL trong repository

Seeder và factory PHP sử dụng hợp đồng lưu trữ mới, chạy được sau khi tinh gọn. `database/schema.sql` mô tả cấu trúc hiện tại. `database/data.sql` là **bản seed từ điển của schema cũ**, giữ lại như nguồn dữ liệu có bản quyền/attribution; không import trực tiếp file này vào schema 43 bảng. Với dữ liệu đã nhập, migration tự chuyển toàn bộ dữ liệu hiện có; không cần nạp lại từ điển.

Kiểm thử chuyển đổi/hoàn tác và dữ liệu mồ côi nằm ở `tests/Feature/DatabaseConsolidationTest.php`. Bộ kiểm thử các module giữ nguyên kỳ vọng nghiệp vụ và dùng assertion chung cho hợp đồng lưu trữ mới.

Bốn trường hợp chuyển đổi được kiểm tra: nhiều nghĩa/nhiều ví dụ và liên kết collocation; chặn dữ liệu mồ côi trước khi bỏ bảng; bảo toàn điểm/đáp án cũ khi chuyển và hoàn tác; câu hỏi độc lập và liên kết kiến thức không còn UNIQUE. Hai kiểm thử giao diện Authentication đã lỗi từ trước thay đổi này. Kiểm thử xếp hạng trang thứ hai có thể dao động khi các lượt thi mẫu đi qua ranh giới một giây; chạy riêng bộ ExportContractTest đạt, cần cố định đồng hồ của fixture khi xử lý bài kiểm thử đó.

## Kết quả kiểm chứng cục bộ ngày 19/10/2026

- Bản sao từ `localhost.sql` trên MySQL đã qua vòng 78 → 43 → 78 → 43 bảng; checksum của cả 35 bảng nguồn khớp sau hoàn tác.
- CSDL `vistiq` đang dùng đã chuyển từ 78 xuống 43 bảng; đối chiếu lại toàn bộ 35 nguồn và kiểm tra các khóa ngoại còn lại đều đạt.
- Giữ đủ 104.829 mục từ và 176.335 ví dụ; tra cứu `achieve` hoạt động sau khi phục hồi các chỉ mục FULLTEXT.
- Bốn kiểm thử migration đạt. Trang chủ và API `/api/v1/dictionary/entries/achieve` trả HTTP 200 sau khi mở lại website.
- Báo cáo và log nằm tại `storage/app/private/database-consolidation/verification/`; `live-result.json` ghi số dòng trước/sau cùng đường dẫn và SHA-256 của backup. `database/schema.sql` đã xuất lại đủ 43 bảng; bản seed SQL cũ được giữ nguyên.
