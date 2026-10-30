# Khung mô tả module

Sao chép tài liệu này cho module mới. Mỗi module cần ghi trạng thái hiện tại riêng với phạm vi đề xuất; “đã có code” không đồng nghĩa “đã hoàn tất”.

## Mục tiêu và ranh giới

- Module sở hữu nghiệp vụ/dữ liệu nào?
- Module không sở hữu nội dung gì, dù có hiển thị hoặc liên kết tới nó?
- Actor chính: admin, learner hay hệ thống.

## Hiện trạng trong repo

Ghi route, controller, service, model, view và test hiện có. Phân loại: đã có, một phần, hoặc đề xuất mới.

## Chức năng cần có

Liệt kê use case theo actor, bao gồm luồng lỗi, trạng thái rỗng và audit nếu có.

## Yêu cầu giao diện

Tham chiếu 00-design-alignment.md và docs/design/modules/<module>/design.md. Mô tả bố cục, thao tác, trạng thái loading/empty/error/success, responsive và accessibility.

## Validation

Ghi validation ở request boundary, quy tắc nghiệp vụ, quyền truy cập, dữ liệu liên kết và tính nhất quán khi lưu. Không để validation chỉ ở client.

## CRUD và vòng đời dữ liệu

Phân loại thao tác create/read/update/delete; nếu không CRUD thì mô tả command/query và state transition. Nêu quy tắc archive/retention.

## Quyền và audit

Ghi permission hiện có hoặc permission cần đề xuất; nêu action phải ghi audit và dữ liệu cần che.

## Kế hoạch test

Nêu feature, unit, authorization, validation, failure, regression và browser/accessibility cases cần có. Test phải kiểm tra kết quả quan sát được, không gắn cứng vào cấu trúc HTML không ổn định.

## Tiêu chí nghiệm thu và phụ thuộc

Định nghĩa kết quả kiểm chứng được; nêu module phụ thuộc, rủi ro, dữ liệu/migration hoặc quyết định còn bỏ ngỏ.
