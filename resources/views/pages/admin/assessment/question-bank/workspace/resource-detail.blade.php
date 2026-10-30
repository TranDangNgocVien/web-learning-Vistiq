@php
    $metadata = $asset->metadata ?? [];
    $isAudio = $asset->resource_kind === 'audio';
    $extension = strtoupper($asset->resource_format ?: ($isAudio ? 'AUDIO' : 'IMAGE'));
@endphp
<div class="resource-detail-content">
    <div class="resource-detail-labels"><span class="resource-badge is-{{ $asset->resource_kind }}">{{ $isAudio ? 'Audio' : 'Hình ảnh' }}</span><span class="resource-badge">{{ $asset->resource_category }}</span><span class="resource-badge">{{ $extension }}</span><small>ID: {{ $isAudio ? 'A' : 'I' }}{{ str_pad((string) $asset->id, 5, '0', STR_PAD_LEFT) }}</small></div>
    <h3 class="resource-detail-title">{{ $asset->title }}</h3>
    @can('content.edit')<a class="resource-track-link" href="{{ route('admin.resources.edit', $asset) }}"><i data-resource-icon="square-pen"></i>Chỉnh sửa tài nguyên</a>@endcan
    @if($isAudio && $asset->resourceStimuli->isNotEmpty())<a class="resource-track-link" href="{{ route('admin.audio.index', ['stimulus' => $asset->resourceStimuli->first()->id]) }}"><i data-resource-icon="square-pen"></i>Chi tiết track audio</a>@endif
    @if($isAudio)
        <div class="resource-player" data-resource-player data-resource-wave-url="{{ route('admin.media.waveform', $asset) }}">
            <audio controls preload="none" src="{{ route('admin.media.show', $asset) }}" aria-label="Phát {{ $asset->title }}"></audio>
            <div class="resource-custom-player" hidden><div class="resource-wave-row"><button type="button" class="resource-play" data-resource-play aria-label="Phát audio" title="Phát audio"><i data-resource-icon="play"></i></button><canvas width="480" height="90" data-resource-wave aria-label="Dạng sóng audio"></canvas></div><div class="resource-seek-row"><span data-resource-time>00:00 / --:--</span><input type="range" min="0" max="100" value="0" step="0.1" data-resource-seek aria-label="Vị trí phát audio" disabled><button type="button" class="resource-icon-button" data-resource-mute aria-label="Tắt âm" title="Tắt âm"><i data-resource-icon="volume-2"></i></button></div></div>
            <p class="resource-player-error" data-resource-player-error hidden role="status">Không thể tải audio. <a href="{{ route('admin.media.show', $asset) }}" target="_blank" rel="noopener">Mở tệp gốc</a></p>
        </div>
    @elseif($asset->resource_kind === 'image')<a class="resource-image-preview" href="{{ route('admin.media.show', $asset) }}" target="_blank" rel="noopener"><img src="{{ route('admin.media.show', $asset) }}" alt="{{ $asset->title }}"><span data-resource-image-error hidden>Không thể tải hình ảnh. Mở tệp gốc.</span></a>
    @else<a class="resource-image-preview" href="{{ route('admin.media.show', $asset) }}" target="_blank" rel="noopener"><i data-resource-icon="file"></i>Mở tệp gốc</a>@endif
    <section class="resource-detail-section"><h3>Thông tin</h3><dl class="resource-info-grid">
        @if($isAudio)<div><i data-resource-icon="clock-3"></i><dt>Thời lượng</dt><dd data-resource-duration>{{ isset($metadata['duration_seconds']) ? gmdate('i:s', (int) $metadata['duration_seconds']) : 'Chưa xác định' }}</dd></div>@endif
        <div><i data-resource-icon="file"></i><dt>Dung lượng</dt><dd>{{ $formatFileSize((int) $asset->size) }}</dd></div><div><i data-resource-icon="hard-drive"></i><dt>Lưu trữ</dt><dd>{{ match($asset->disk) { 'cloudinary' => 'Cloudinary', 'r2' => 'R2', 's3' => 'S3', default => 'Kho nội bộ' } }}</dd></div><div><i data-resource-icon="user-round"></i><dt>Người tải lên</dt><dd>{{ $asset->resource_creator }}</dd></div><div><i data-resource-icon="refresh-cw"></i><dt>Cập nhật</dt><dd>{{ $asset->updated_at?->locale('vi')->diffForHumans() }}</dd></div>
    </dl></section>
    <section class="resource-detail-section" data-resource-questions><h3>Sử dụng trong <span>({{ $asset->linkedQuestions->count() }} câu hỏi)</span></h3><div class="resource-question-list">@forelse($asset->linkedQuestions as $question)<a href="{{ route('admin.questions.edit', $question) }}"><i data-resource-icon="file-text"></i><span>Q-{{ str_pad((string) $question->id, 5, '0', STR_PAD_LEFT) }}<small>{{ $question->examPart?->examType?->name }} {{ $question->examPart?->name }}</small></span><i data-resource-icon="chevron-right"></i></a>@empty<p>Chưa gắn với câu hỏi nào.</p>@endforelse</div></section>
    <section class="resource-detail-section"><h3>Thẻ / Tags</h3><div class="resource-tags">@forelse($asset->resource_categories as $tag)<span>{{ $tag }}</span>@empty<span>Chưa có thẻ</span>@endforelse</div></section>
    @if(filled($metadata['source'] ?? null))<section class="resource-detail-section"><h3>Nguồn</h3><p class="resource-detail-text">{{ $metadata['source'] }}</p></section>@endif
    @if(filled($metadata['speaker_labels'] ?? null))<section class="resource-detail-section"><h3>Người nói</h3><p class="resource-detail-text">{{ is_array($metadata['speaker_labels']) ? implode(', ', $metadata['speaker_labels']) : $metadata['speaker_labels'] }}</p></section>@endif
    @if(filled($metadata['transcript'] ?? null))<details class="resource-transcript"><summary>Bản chép lời</summary><p>{{ $metadata['transcript'] }}</p></details>@endif
</div>
