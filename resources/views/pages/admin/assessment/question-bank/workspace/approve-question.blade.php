@can('content.review')
    @if(($reviewRevisionId ?? null) && ($questionStatus ?? null) === 'review')
        <form method="post" action="{{ route('admin.questions.approve') }}" class="question-approval-action" data-pending-label="Đang duyệt…">
            @csrf
            <input type="hidden" name="ids[]" value="{{ $questionId }}">
            <input type="hidden" name="revision_ids[{{ $questionId }}]" value="{{ $reviewRevisionId }}">
            <button class="button button-quiet" type="submit" aria-label="Duyệt câu hỏi {{ $questionLabel ?? 'Q-'.$questionId }}">Duyệt</button>
        </form>
    @endif
@endcan
