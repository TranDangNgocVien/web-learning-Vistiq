@can('content.edit')
@php($draftQuestions = $entry['questions']->filter(fn ($q) => ($q->draftRevision?->status ?? $q->contentStatus()) === 'draft'))
@if($draftQuestions->isNotEmpty())
<form method="post" action="{{ route('admin.questions.bulk') }}" class="assessment-actions" data-pending-label="Đang gửi duyệt…">
    @csrf<input type="hidden" name="action" value="review">
    @foreach($draftQuestions->take(100) as $question)<input type="hidden" name="ids[]" value="{{ $question->id }}">@endforeach
    <button type="submit" class="button button-primary">Gửi duyệt {{ min(100, $draftQuestions->count()) }} câu hỏi</button>
    <small>Câu hỏi nháp cần gửi duyệt trước khi xuất bản.</small>
</form>
@endif
@endcan
