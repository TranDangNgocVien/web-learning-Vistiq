@can('content.edit')
    @php
        $draftReviewIds = collect($bankData['records'])
            ->filter(fn (array $record): bool => ($record['status'] ?? null) === 'draft')
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values();
        $draftReviewCount = $draftReviewIds->count();
        $draftReviewSignature = hash('sha256', $draftReviewIds->implode(','));
    @endphp
    @if($draftReviewCount > 0)
        <form method="post" action="{{ route('admin.questions.bulk') }}"
            data-confirm="Gửi duyệt {{ number_format($draftReviewCount) }} câu hỏi nháp trên toàn bộ ngân hàng Listening, Reading, Writing, Speaking và Tiếng Anh THPT? Cả lượt chỉ hoàn tất khi mọi câu đều hợp lệ; nếu có câu lỗi, hệ thống dừng toàn bộ và nêu mã câu cần sửa."
            data-confirm-title="Gửi duyệt toàn bộ câu nháp"
            data-confirm-label="Gửi duyệt {{ number_format($draftReviewCount) }} câu"
            data-confirm-tone="safe"
            data-pending-label="Đang gửi duyệt…">
            @csrf
            <input type="hidden" name="action" value="review_all">
            <input type="hidden" name="draft_signature" value="{{ $draftReviewSignature }}">
            <button class="button button-quiet" type="submit">Gửi duyệt tất cả ({{ number_format($draftReviewCount) }})</button>
        </form>
    @endif
@endcan
