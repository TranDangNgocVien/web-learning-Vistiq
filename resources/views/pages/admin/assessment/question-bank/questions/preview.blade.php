@php
    $registry = app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class);
    $options = $payload['options'] ?? [];
    $correctOptions = $payload['correct_options'] ?? [];
    $metadata = $payload['metadata'] ?? [];
    $imageAsset = $previewAssets->get($metadata['image_id'] ?? null);
    $audioAsset = $previewAssets->get($metadata['audio_id'] ?? ($question->stimulus?->stimulus_type === 'audio' ? ($question->stimulus?->metadata['asset_id'] ?? null) : null));
    $tags = $questionTags->get($question->learning_item_id, collect());
@endphp
<div class="qb-preview-body">
    <div class="qb-preview-meta"><div><span class="badge {{ $question->examPart?->examType?->code === 'ielts' ? 'badge-pink' : 'badge-blue' }}">{{ $question->examPart?->examType?->name ?? '—' }}</span><span class="badge badge-soft">{{ $partLabel($question->examPart) }}</span><span class="badge badge-soft">{{ $question->questionType?->name ?? '—' }}</span></div><small>ID: Q-{{ str_pad((string)$question->id, 5, '0', STR_PAD_LEFT) }}</small></div>
    @if($imageAsset)<img class="qb-preview-image" src="{{ route('admin.media.show', $imageAsset) }}" alt="Ảnh minh họa câu hỏi {{ $question->id }}" loading="lazy">@endif
    @if($audioAsset && empty($withinEntry))<audio controls preload="none" src="{{ route($audioAsset->disk === 'local' ? 'admin.media.waveform' : 'admin.media.show', $audioAsset) }}" aria-label="Audio câu hỏi {{ $question->id }}"></audio>@endif
    @if(empty($withinEntry) && $question->stimulus?->stimulus_type === 'reading')<details class="qb-preview-reading"><summary>@include('pages.dictionary.icon', ['name' => 'book-open']) {{ $question->stimulus->title }}</summary><div class="qb-rich-text">{!! $registry->sanitize($question->stimulus->body ?? '') !!}</div></details>@endif
    <section class="qb-preview-section"><h3>Câu hỏi</h3><div class="qb-rich-text">{!! $registry->sanitize($payload['prompt'] ?? '') !!}</div></section>
    @if($options !== [])
        <ol class="qb-options" aria-label="Các phương án">
            @foreach($options as $key => $content)<li class="{{ in_array((string)$key, array_map('strval', $correctOptions), true) ? 'is-correct' : '' }}"><span class="qb-option-key">{{ $key }}</span><div class="qb-rich-text">{!! $registry->sanitize((string)$content) !!}</div></li>@endforeach
        </ol>
    @endif
    <section class="qb-preview-section"><h3>Đáp án đúng</h3>
        @foreach($correctOptions as $key)<div class="qb-answer"><span class="qb-option-key">{{ $key }}</span><div class="qb-rich-text">{!! $registry->sanitize((string)($options[$key] ?? $key)) !!}</div></div>@endforeach
        @foreach($payload['accepted_answers'] ?? [] as $answer)<div class="qb-answer">@include('pages.dictionary.icon', ['name' => 'check-circle']) {{ $answer }}</div>@endforeach
        @foreach($metadata['answer_map'] ?? [] as $label => $answer)<div class="qb-answer"><b>{{ $label }}</b><span>{{ $answer }}</span></div>@endforeach
        @foreach($metadata['blanks'] ?? [] as $key => $blank)<div class="qb-answer"><b>{{ $blank['label'] ?? $key }}</b><span>{{ implode(' / ', $blank['accepted_answers'] ?? []) }}</span></div>@endforeach
        @if(!empty($metadata['model_answer']))<div class="qb-rich-text qb-model-answer">{!! $registry->sanitize($metadata['model_answer']) !!}</div>
        @elseif($correctOptions === [] && empty($payload['accepted_answers']) && empty($metadata['answer_map']) && empty($metadata['blanks']))<p class="qb-preview-note">{{ in_array($question->questionType?->code, ['essay', 'speaking']) ? 'Đánh giá theo tiêu chí chấm điểm.' : 'Chưa có đáp án.' }}</p>@endif
    </section>
    @if(empty($withinListening))<section class="qb-preview-section"><h3>Giải thích</h3>@if(filled($payload['explanation'] ?? ''))<div class="qb-rich-text">{!! $registry->sanitize($payload['explanation']) !!}</div>@else<p class="qb-preview-note">Chưa có giải thích.</p>@endif</section>@endif
    @if(!empty($metadata['rubric']))<section class="qb-related"><span>@include('pages.dictionary.icon', ['name' => 'idea'])</span><div><h3>Tiêu chí chấm điểm</h3><ul>@foreach($metadata['rubric'] as $criterion => $guidance)<li><b>{{ $criterion }}</b>: {{ is_string($guidance) ? $guidance : json_encode($guidance, JSON_UNESCAPED_UNICODE) }}</li>@endforeach</ul></div></section>@endif
    @if(empty($withinListening))<section class="qb-preview-section"><h3>Thẻ / Tags</h3><div class="qb-tags"><span class="badge badge-blue">{{ $question->examPart?->examType?->name ?? '—' }}</span><span class="badge badge-soft">{{ $partLabel($question->examPart) }}</span>@foreach($tags as $tag)<span class="badge badge-soft">{{ $tag->name }}</span>@endforeach</div></section>@endif
    @if(empty($withinListening))
        @can('content.edit')
            <a class="button button-quiet qb-preview-edit" href="{{ $embeddedEditUrl ?? route('admin.questions.edit', $question) }}">@include('pages.dictionary.icon', ['name' => 'edit']) Chỉnh sửa câu hỏi</a>
        @endcan
    @endif
</div>
