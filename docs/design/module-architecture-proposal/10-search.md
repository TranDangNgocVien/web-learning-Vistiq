# Module: Tra cứu

> Trạng thái hiện tại: Đã có Dictionary Lookup/API và tìm kiếm Admin; cần thống nhất đây là module query, không phải nơi sở hữu từ/ngữ pháp.

## Mục tiêu và ranh giới

Cung cấp tìm kiếm từ vựng/ngữ pháp đã publish cho learner và search nhanh cho Admin. Dữ liệu nguồn thuộc KnowledgeBase hoặc các module khác; Search chỉ truy vấn/index và điều hướng tới bản ghi.

## Chức năng cần có

- Tìm theo từ, nghĩa, ví dụ, loại kiến thức và ngôn ngữ.
- Gợi ý khi gõ, phân trang, xem chi tiết và liên kết mục liên quan.
- Chuẩn hóa Unicode/diacritics theo ngôn ngữ; hỗ trợ query tiếng Việt/tiếng Anh.
- Admin search trả kết quả có phân loại và chỉ hiện module người đó được phép xem.
- Lưu recent search hoặc mục đã lưu chỉ khi sản phẩm chốt chính sách riêng tư và retention.

## Yêu cầu giao diện

Learner có ô tra cứu dễ nhận ra, kết quả có nghĩa/loại từ/ví dụ và trạng thái không có kết quả kèm cách sửa truy vấn. Hỗ trợ bàn phím, autocomplete có ARIA và không che control trên mobile. Admin search hiển thị nhóm kết quả và trạng thái permission. Dùng UI hiện có; không áp palette login cho màn tra cứu.

## Validation

- Query được trim, giới hạn độ dài và kiểm soát ký tự/rate limit.
- Type/filter thuộc allowlist; page size có trần; sort chỉ qua field cho phép.
- Chỉ trả nội dung published cho learner.
- Escape/sanitize highlight và HTML; không đưa query trực tiếp vào SQL wildcard nếu không escape.
- Admin kết quả tuân theo quyền từng module; không dùng một endpoint để lộ record cấm.

## CRUD và vòng đời

Read-only đối với nội dung. Recent search/bookmark nếu bật là CRUD riêng của quan hệ user; xóa bookmark không xóa knowledge item. Index là dữ liệu dẫn xuất, có thể rebuild từ nguồn chuẩn.

## Quyền và audit

Learner chỉ cần quyền đọc nội dung công khai. Admin search áp permission của từng kết quả; audit chỉ khi tra cứu nhạy cảm cần theo dõi.

## Kế hoạch test

- Feature: tìm theo term/meaning/type, detail, pagination và no-result.
- Locale: Unicode, hoa/thường, dấu tiếng Việt và dấu câu.
- Authorization: draft không xuất hiện; Admin chỉ thấy record được phép.
- Security: query quá dài, wildcard, XSS trong highlight và throttle.
- Unit: normalize, ranking và filter query.
- Regression: API resource, dictionary page và Grammar link không lệch nhau.
- Performance: index/query kế hoạch, không tải toàn bộ danh mục khi autocomplete.

## Tiêu chí nghiệm thu và phụ thuộc

Lookup không trả dữ liệu draft và cùng một term có kết quả nhất quán giữa page/API. Phụ thuộc KnowledgeBase, Search index và UserSavedItem nếu bật.
