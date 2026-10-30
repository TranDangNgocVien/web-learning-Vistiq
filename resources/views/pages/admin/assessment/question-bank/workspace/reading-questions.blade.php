<div class="reading-question-list" data-reading-question-list>
    <div class="reading-section-heading"><h3>Câu hỏi liên kết <span class="badge badge-blue">{{ $entry['questions']->count() }}</span></h3>@can('content.edit')<button type="button" class="button button-primary" data-reading-edit-question="">+ Thêm câu hỏi</button>@endcan</div>
    @forelse($entry['questions'] as $question)
        @php($record = $bankData['records'][$question->id])
        <button type="button" class="reading-question-row" data-reading-edit-question="{{ $question->id }}"><b>Q-{{ str_pad((string)$question->id,5,'0',STR_PAD_LEFT) }}</b><span>{{ \Illuminate\Support\Str::limit(strip_tags($record['payload']['prompt'] ?? ''),150) }}</span><small>{{ $question->questionType?->name }}</small><span aria-hidden="true">›</span></button>
    @empty<p class="qb-preview-note">Chưa có câu hỏi liên kết</p>@endforelse
</div>
