@can('content.edit')
    <form method="post" action="{{ route('admin.questions.destroy', $question) }}" class="question-delete-action {{ ($compact ?? false) ? 'is-compact' : '' }}" data-confirm="Xóa hoặc ngừng sử dụng câu Q-{{ $question->id }}? Nếu câu chỉ nằm trong đề nháp, câu sẽ được gỡ khỏi đề rồi xóa mềm. Nếu câu nằm trong đề đã phát hành, câu sẽ được ngừng sử dụng nhưng vẫn giữ trong đề. Lịch sử làm bài và dữ liệu xem lại được giữ nguyên." data-confirm-title="Xóa câu hỏi" data-confirm-label="Tiếp tục" data-confirm-tone="danger" data-pending-label="Đang xử lý…">
        @csrf
        @method('DELETE')
        @if(($compact ?? false))
            <button type="submit" title="Xóa hoặc ngừng sử dụng câu Q-{{ $question->id }}" aria-label="Xóa hoặc ngừng sử dụng câu Q-{{ $question->id }}"><span aria-hidden="true">×</span></button>
        @else
            <button class="button button-danger" type="submit">@include('pages.dictionary.icon', ['name'=>'close']) Xóa câu hỏi</button>
        @endif
    </form>
@endcan
