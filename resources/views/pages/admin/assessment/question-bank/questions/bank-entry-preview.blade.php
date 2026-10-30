@php
    $statusLabels = ['draft'=>'Bản nháp', 'review'=>'Chờ duyệt', 'approved'=>'Đã duyệt', 'published'=>'Đã xuất bản', 'archived'=>'Đã lưu trữ', 'mixed'=>'Hỗn hợp'];
    $difficultyLabels = ['easy'=>'Dễ', 'medium'=>'Trung bình', 'hard'=>'Khó', 'mixed'=>'Hỗn hợp'];
    $isThptEnglish = request()->routeIs('admin.questions.thpt') || request()->boolean('thpt_english');
    $kindLabels = $isThptEnglish
        ? ['reading'=>'Bài đọc dùng chung', 'paragraph'=>'Văn bản', 'audio'=>'Tài nguyên', 'group'=>'Nhóm câu hỏi', 'question'=>'Câu hỏi']
        : ['reading'=>'Passage', 'paragraph'=>'Paragraph · Part 6', 'audio'=>'Audio', 'group'=>'Nhóm câu hỏi', 'question'=>'Câu hỏi'];
    $partLabel = fn ($part) => $part?->examType?->code === 'toeic' ? ucfirst(str_replace('-', ' ', $part->code)) : ($isThptEnglish ? match ($part?->code) {
        'tieng-anh-part-1' => 'Phần 1 · Điền từ ngắn',
        'tieng-anh-part-2' => 'Phần 2 · Điền từ ngắn',
        'tieng-anh-part-3' => 'Phần 3 · Sắp xếp câu',
        'tieng-anh-part-4' => 'Phần 4 · Điền cụm / câu',
        'tieng-anh-part-5' => 'Phần 5 · Đọc hiểu',
        'tieng-anh-part-6' => 'Phần 6 · Đọc hiểu',
        default => $part?->name ?? '—',
    } : ($part?->name ?? '—'));
    $previewAssets = $bankData['assets'];
    $questionTags = $questionTags ?? \Illuminate\Support\Facades\DB::table('learning_item_tags')->join('tags','tags.id','=','learning_item_tags.tag_id')->whereIn('learning_item_id', $entry['questions']->pluck('learning_item_id'))->select('learning_item_id','tags.name')->get()->groupBy('learning_item_id');
    $asset = $previewAssets->get($entry['payload']['metadata']['asset_id'] ?? 0);
    $registry = app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class);
@endphp
@if($entry['kind'] === 'question')
    @php($question = $entry['questions']->first())
    @include('pages.admin.assessment.question-bank.questions.preview', ['payload'=>$bankData['records'][$question->id]['payload']])
@else
    <div class="qb-preview-body qb-entry-detail">
        <div class="qb-preview-meta"><span class="badge badge-blue">{{ $kindLabels[$entry['content_kind']] }}</span><small>{{ $entry['code'] }}</small></div>
        <h3 class="qb-entry-title">{{ $entry['title'] }}</h3>
        @foreach($entry['parts'] as $part)<span class="badge badge-soft">{{ $part->examType?->name }} · {{ $partLabel($part) }}</span>@endforeach
        @if($entry['content_kind'] === 'audio')
            @if($asset || filled($entry['payload']['asset_path'] ?? ''))<audio controls preload="none" src="{{ $asset ? route($asset->disk === 'local' ? 'admin.media.waveform' : 'admin.media.show', $asset) : $entry['payload']['asset_path'] }}" aria-label="Phát {{ $entry['title'] }}"></audio>
            @else<p class="qb-incomplete">Chưa có file audio hoặc file không còn tồn tại.</p>@endif
            @if(filled($entry['payload']['transcript'] ?? ''))<details class="qb-preview-reading"><summary>Transcript</summary><div class="qb-rich-text">{!! $registry->sanitize($entry['payload']['transcript']) !!}</div></details>@endif
        @elseif(filled($entry['payload']['body'] ?? ''))
            <div class="qb-rich-text qb-entry-reading">{!! $registry->sanitize($entry['payload']['body']) !!}</div>
        @endif
        @if($entry['kind'] === 'group')<p class="qb-incomplete">Chưa gắn tài nguyên</p>@endif
        <div class="qb-entry-facts"><span><b>{{ $entry['questions']->count() }}</b> câu hỏi</span><span>{{ $difficultyLabels[$entry['difficulty']] ?? '—' }}</span><span>{{ $statusLabels[$entry['question_status']] }}</span></div>
        <section class="qb-preview-section"><h3>{{ $isThptEnglish ? 'Câu hỏi trong nhóm' : 'Câu hỏi liên quan' }} ({{ $entry['questions']->count() }})</h3>
            @forelse($entry['questions'] as $question)
                @php($payload = $bankData['records'][$question->id]['payload'])
                <details class="qb-linked-question" data-linked-question="{{ $question->id }}">
                    <summary><b>Q-{{ str_pad((string)$question->id,5,'0',STR_PAD_LEFT) }}</b><span>{{ \Illuminate\Support\Str::limit(strip_tags($payload['prompt'] ?? ''),100) }}</span>@if(isset($entry['matching_ids']) && in_array($question->id,$entry['matching_ids']))<small>Phù hợp bộ lọc</small>@endif</summary>
                    @include('pages.admin.assessment.question-bank.questions.preview', ['withinEntry'=>true, 'embeddedEditUrl'=>$entry['stimulus']?->stimulus_type === 'reading' ? route('admin.readings.index',['stimulus'=>$entry['id'],'question'=>$question->id]) : null])
                </details>
            @empty<p class="qb-preview-note">Chưa có câu hỏi liên kết</p>@endforelse
        </section>
        @if($entry['stimulus'])<a class="button button-quiet qb-preview-edit" href="{{ route($entry['stimulus']->stimulus_type === 'reading' ? 'admin.readings.index' : 'admin.audio.index', ['stimulus'=>$entry['id']]) }}">@can('content.edit') Chỉnh sửa bài / thêm câu hỏi @else Xem bài @endcan</a>@endif
    </div>
@endif
