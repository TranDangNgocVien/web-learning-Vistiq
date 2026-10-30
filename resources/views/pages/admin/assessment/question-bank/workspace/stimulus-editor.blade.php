@php
    $audioOnly = $stimulusType === 'audio';
    $indexRoute = $audioOnly ? route('admin.audio.index') : route('admin.readings.index');
    $selectedMetadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
    $audioPostLimitBytes = $audioOnly
        ? (int) ini_parse_quantity((string) ini_get('post_max_size'))
        : 0;
    $formatFileSize = static function (int $size): string {
        if ($size >= 1048576) {
            return number_format($size / 1048576, 1).' MB';
        }

        return number_format(max(1, $size) / 1024, 0).' KB';
    };
@endphp

@if($audioOnly)
    <div class="audio-workbench">
        @can('content.edit')
            <section class="audio-upload-card" aria-labelledby="audio-upload-title">
                <div class="audio-upload-card__intro">
                    <span class="audio-workbench-section-kicker">01 / ADD SOURCES</span>
                    <h2 id="audio-upload-title">Tải file lên một lần, dùng ở nhiều nơi.</h2>
                    <p>Chọn nhiều file cùng lúc. Tên track được lấy từ tên file; sau khi tải xong, mở từng track để thêm transcript và thông tin người nói.</p>
                    <ul class="audio-upload-specs">
                        <li><span>01</span><div><strong>Định dạng</strong><small>MP3 · WAV · M4A · OGG</small></div></li>
                        <li><span>02</span><div><strong>Dung lượng</strong><small>Tối đa 50 MB cho mỗi file</small></div></li>
                        <li><span>03</span><div><strong>Quy trình</strong><small>Tải lên → nghe thử → gắn vào nhóm</small></div></li>
                    </ul>
                </div>

                <form class="audio-upload-form" method="post" action="{{ route('admin.audio.batch.store') }}" enctype="multipart/form-data" data-audio-batch data-audio-post-limit-bytes="{{ $audioPostLimitBytes }}" data-success-url="{{ route('admin.audio.index') }}">
                    @csrf
                    <label class="audio-dropzone" for="audio-batch-files" data-audio-dropzone>
                        <input id="audio-batch-files" name="files[]" type="file" accept=".mp3,.wav,.m4a,.ogg,audio/mpeg,audio/wav,audio/mp4,audio/ogg" multiple required data-audio-batch-input>
                        <span class="audio-dropzone__icon" aria-hidden="true">↥</span>
                        <strong data-audio-dropzone-title>Thả file audio vào đây</strong>
                        <small>hoặc bấm để chọn nhiều file từ máy tính</small>
                        <span class="audio-dropzone__hint">Tên file sẽ trở thành tên track</span>
                    </label>

                    <div class="audio-upload-selection" data-audio-file-list hidden>
                        <div class="audio-upload-selection__heading">
                            <span><b data-audio-file-count>0</b> file đã chọn</span>
                            <button type="button" data-audio-clear>Chọn lại</button>
                        </div>
                        <ul data-audio-file-items></ul>
                    </div>

                    <div class="audio-upload-form__footer">
                        <p data-audio-upload-status><span aria-hidden="true">●</span> Sẵn sàng nhận file audio.</p>
                        <button class="button button-primary" type="submit" data-audio-submit>
                            <span>Tải lên thư viện</span><b aria-hidden="true">→</b>
                        </button>
                    </div>
                </form>
            </section>
        @endcan

        <section class="audio-library-layout" aria-label="Thư viện và trình biên tập audio">
            <aside class="audio-library-panel" data-audio-library-panel>
                <header class="audio-library-panel__header">
                    <div>
                        <span class="audio-workbench-section-kicker">02 / YOUR LIBRARY</span>
                        <h2>Thư viện audio</h2>
                        <p data-audio-library-summary>{{ $stimuli->total() }} track · {{ $stimuli->firstItem() ?? 0 }}–{{ $stimuli->lastItem() ?? 0 }} đang hiển thị</p>
                    </div>
                    <form method="get" action="{{ $indexRoute }}" class="audio-library-search" data-audio-live-search>
                        <label class="sr-only" for="audio-library-query">Tìm audio</label>
                        <input id="audio-library-query" type="search" name="q" value="{{ $search }}" placeholder="Tìm theo tên…" autocomplete="off" data-audio-live-query>
                        <button class="button button-quiet" type="submit">Tìm</button>
                    </form>
                </header>

                @can('content.edit')
                    <form class="audio-library-bulk-form" method="post" action="{{ route('admin.audio.bulk-delete') }}" data-audio-delete-form data-audio-total="{{ $stimuli->total() }}">
                        @csrf
                        <input type="hidden" name="q" value="{{ $search }}">
                        <input type="hidden" name="select_all" value="1" data-audio-select-all-value disabled>
                        <input type="hidden" name="ids[]" value="" data-audio-single-id disabled>
                        <div class="audio-library-bulk-toolbar">
                            <div class="audio-selection-tools">
                                <label class="audio-selection-toggle"><input type="checkbox" data-audio-select-all aria-label="Chọn tất cả audio"><span data-audio-select-all-label>Chọn tất cả</span></label>
                                <span class="audio-selection-count" data-audio-selection-count>0 đã chọn</span>
                            </div>
                            <button class="button button-danger" type="submit" data-audio-delete-selected disabled>Xóa audio đã chọn</button>
                        </div>
                        <div class="audio-library-list" data-audio-library-list>
                            @forelse($stimuli as $stimulus)
                                <div class="audio-library-row {{ $selectedStimulus?->id === $stimulus->id ? 'is-selected' : '' }}">
                                    <label class="audio-library-row__check"><input type="checkbox" name="ids[]" value="{{ $stimulus->id }}" data-audio-selection aria-label="Chọn {{ $stimulus->title }}"><span></span></label>
                                    <span class="audio-library-row__wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                                    <a class="audio-library-row__main" href="{{ route('admin.audio.index', ['stimulus' => $stimulus->id, 'q' => $search]) }}" @if($selectedStimulus?->id === $stimulus->id) aria-current="page" @endif>
                                        <strong>{{ $stimulus->title }}</strong><small>{{ strtoupper($stimulus->status) }} · {{ $stimulus->questions_count }} câu hỏi</small>
                                    </a>
                                    <button class="audio-library-row__delete" type="button" data-audio-delete-single="{{ $stimulus->id }}" aria-label="Xóa {{ $stimulus->title }}">×</button>
                                    <a class="audio-library-row__arrow" href="{{ route('admin.audio.index', ['stimulus' => $stimulus->id, 'q' => $search]) }}" aria-hidden="true">→</a>
                                </div>
                            @empty
                                <div class="audio-library-empty">
                                    <span class="audio-library-empty__icon" aria-hidden="true">♪</span>
                                    <h3>Kho đang chờ track đầu tiên.</h3>
                                    <p>Chọn file audio ở khu vực phía trên để bắt đầu xây thư viện.</p>
                                </div>
                            @endforelse
                        </div>
                    </form>
                @else
                    <div class="audio-library-list" data-audio-library-list>
                        @forelse($stimuli as $stimulus)
                            <a class="audio-library-row audio-library-row--readonly {{ $selectedStimulus?->id === $stimulus->id ? 'is-selected' : '' }}" href="{{ route('admin.audio.index', ['stimulus' => $stimulus->id, 'q' => $search]) }}" @if($selectedStimulus?->id === $stimulus->id) aria-current="page" @endif>
                                <span class="audio-library-row__wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                                <span class="audio-library-row__main"><strong>{{ $stimulus->title }}</strong><small>{{ strtoupper($stimulus->status) }} · {{ $stimulus->questions_count }} câu hỏi</small></span>
                                <span class="audio-library-row__arrow" aria-hidden="true">→</span>
                            </a>
                        @empty
                            <div class="audio-library-empty">
                                <span class="audio-library-empty__icon" aria-hidden="true">♪</span>
                                <h3>Kho đang chờ track đầu tiên.</h3>
                                <p>Chọn file audio ở khu vực phía trên để bắt đầu xây thư viện.</p>
                            </div>
                        @endforelse
                    </div>
                @endcan

                @if($stimuli->hasPages())
                    <div class="audio-library-pagination" data-audio-library-pagination>{{ $stimuli->onEachSide(0)->links() }}</div>
                @endif
            </aside>

            <section class="audio-editor-panel" aria-labelledby="audio-editor-title">
                @if($selectedStimulus)
                    @include('shared.workflow-actions', ['kind' => 'stimulus', 'entityId' => $selectedStimulus->id, 'revision' => $workflowRevision])
                    <header class="audio-editor-panel__header">
                        <div>
                            <span class="audio-workbench-section-kicker">03 / REFINE THE TRACK</span>
                            <h2 id="audio-editor-title">{{ $selectedStimulus->title }}</h2>
                            <p>Chỉnh sửa bản nháp; gắn file audio với câu hỏi hoặc nhóm từ Tài nguyên.</p>
                        </div>
                        <span class="audio-editor-status">{{ strtoupper($selectedStimulus->status) }}</span>
                    </header>

                    @can('content.edit')
                        <form class="audio-editor-form" method="post" enctype="multipart/form-data" action="{{ route('admin.stimuli.update', $selectedStimulus) }}" data-unsaved-form>
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="revision_counter" value="{{ $selectedStimulus->revision_counter }}">
                            <input type="hidden" name="stimulus_type" value="audio">

                            <div class="audio-editor-preview">
                                <div class="audio-editor-preview__heading"><span>Preview audio</span><small>Nghe để kiểm tra đúng file</small></div>
                                @if($selectedMetadata['asset_id'] ?? null)
                                    <audio controls preload="none" src="{{ route('admin.media.show', $selectedMetadata['asset_id']) }}" aria-label="Phát {{ $selectedStimulus->title }}">Trình duyệt không phát được audio.</audio>
                                @else
                                    <div class="audio-editor-preview__empty">Chưa có file audio</div>
                                @endif
                                <label class="audio-replace-file">
                                    <span>Thay file audio</span>
                                    <input type="file" name="media" accept=".mp3,.wav,.m4a,.ogg,audio/mpeg,audio/wav,audio/mp4,audio/ogg" data-audio-editor-file>
                                    <small data-audio-editor-file-status>Để trống nếu muốn giữ file hiện tại.</small>
                                </label>
                            </div>

                            <div class="audio-editor-fields">
                                <label class="audio-field audio-field--wide">
                                    <span>Tên track <b>*</b></span>
                                    <input name="title" value="{{ old('title', $payload['title'] ?? '') }}" required>
                                </label>
                                <label class="audio-field">
                                    <span>Thời lượng (giây)</span>
                                    <input type="number" name="duration_seconds" min="1" max="86400" value="{{ old('duration_seconds', $payload['duration_seconds'] ?? '') }}" data-audio-duration>
                                </label>
                                <label class="audio-field">
                                    <span>Accent / giọng đọc</span>
                                    <input name="accent" value="{{ old('accent', $payload['accent'] ?? '') }}" placeholder="Ví dụ: American English">
                                </label>
                                <label class="audio-field audio-field--wide">
                                    <span>Transcript</span>
                                    <textarea name="transcript" rows="7" placeholder="Dán bản chép lời của đoạn audio…">{{ old('transcript', $payload['transcript'] ?? '') }}</textarea>
                                </label>
                                <label class="audio-field">
                                    <span>Nguồn</span>
                                    <input name="source" value="{{ old('source', $selectedMetadata['source'] ?? '') }}" placeholder="Nguồn hoặc mã đề">
                                </label>
                                <label class="audio-field">
                                    <span>Nhãn người nói</span>
                                    <input name="speaker_labels" value="{{ old('speaker_labels', is_array($selectedMetadata['speaker_labels'] ?? null) ? implode(', ', $selectedMetadata['speaker_labels']) : ($selectedMetadata['speaker_labels'] ?? '')) }}" placeholder="Speaker A, Speaker B">
                                </label>
                                <details class="audio-metadata-details audio-field--wide">
                                    <summary>Metadata nâng cao</summary>
                                    <textarea name="metadata_json" rows="6" spellcheck="false">{{ old('metadata_json', json_encode($selectedMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
                                </details>
                            </div>

                            <footer class="audio-editor-form__footer">
                                <p><span aria-hidden="true">✓</span> Mỗi audio có thể được dùng chung cho nhiều câu hỏi trong một nhóm.</p>
                                <button class="button button-primary" type="submit"><span>Lưu bản nháp</span><b aria-hidden="true">→</b></button>
                            </footer>
                        </form>
                    @endcan

                    <section class="audio-linked-questions" aria-labelledby="audio-linked-questions-title">
                        <div class="audio-linked-questions__heading">
                            <div><span class="audio-workbench-section-kicker">LINKED CONTENT</span><h3 id="audio-linked-questions-title">Câu hỏi đang dùng track này</h3></div>
                            <span>{{ $selectedStimulus->questions->count() }} câu</span>
                        </div>
                        @forelse($selectedStimulus->questions as $question)
                            <a class="audio-linked-question" href="{{ route('admin.questions.edit', $question) }}">
                                <span>Q-{{ str_pad((string) $question->id, 5, '0', STR_PAD_LEFT) }}</span>
                                <strong>{{ $question->questionType?->name ?? 'Chưa phân loại' }}</strong>
                                <small>{{ $question->examPart?->examType?->name }} · {{ $question->examPart?->name }}</small>
                                <b aria-hidden="true">→</b>
                            </a>
                        @empty
                            <p class="audio-linked-questions__empty">Track chưa được gắn vào câu hỏi nào. Bạn có thể gắn audio theo nhóm ở màn hình chỉnh sửa câu hỏi.</p>
                        @endforelse
                    </section>
                @else
                    <div class="audio-editor-empty">
                        <div class="audio-editor-empty__wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                        <span class="audio-workbench-section-kicker">SELECT A TRACK</span>
                        <h2 id="audio-editor-title">Chọn một track để chỉnh sửa</h2>
                        <p>Audio mới tải lên sẽ xuất hiện ở thư viện bên trái. Chọn một track để nghe thử, thêm transcript và xem các câu hỏi liên kết.</p>
                    </div>
                @endif
            </section>
        </section>
    </div>
@else
    <form method="get" action="{{ $indexRoute }}" class="assessment-actions" data-ajax-filter-form data-ajax-target="#stimulus-search-results" data-ajax-debounce="350">
        <input type="hidden" name="stimulus" value="{{ $selectedStimulus?->id ?? '' }}">
        <label>Tìm tài nguyên<input type="search" name="q" value="{{ $search }}" autocomplete="off"></label>
        <button class="button button-quiet" type="submit">Tìm</button>
        <a data-ajax-filter-reset="#stimulus-search-results" href="{{ route($audioOnly ? 'admin.audio.index' : 'admin.readings.index', array_filter(['stimulus' => $selectedStimulus?->id])) }}">Reset filter</a>
    </form>

    <div class="assessment-grid">
        <div id="stimulus-search-results" data-ajax-results>
        <section class="panel">
            <h2>Kho Passage</h2>
            @forelse($stimuli as $stimulus)
                <p><a href="{{ route('admin.readings.index', ['stimulus' => $stimulus->id]) }}">{{ $stimulus->title }} · {{ $stimulus->status }} · {{ $stimulus->questions_count }} câu</a></p>
            @empty
                <p>Chưa có tài nguyên.</p>
            @endforelse
            <div data-ajax-pagination>{{ $stimuli->onEachSide(0)->links() }}</div>
        </section>
        </div>

        <section class="panel">
            @if($selectedStimulus)
                @include('shared.workflow-actions', ['kind' => 'stimulus', 'entityId' => $selectedStimulus->id, 'revision' => $workflowRevision])
                <h2>{{ $selectedStimulus->title }}</h2>
            @endif
            <form method="post" enctype="multipart/form-data" action="{{ $selectedStimulus ? route('admin.stimuli.update', $selectedStimulus) : route('admin.readings.store') }}" data-unsaved-form>
                @csrf
                @if($selectedStimulus) @method('PUT') @endif
                <input type="hidden" name="revision_counter" value="{{ $selectedStimulus?->revision_counter ?? 0 }}">
                <input type="hidden" name="stimulus_type" value="reading">
                <label>Tiêu đề<input name="title" required value="{{ old('title', $payload['title'] ?? '') }}"></label>
                <label>Passage<textarea name="body" data-rich-editor rows="12">{{ old('body', $payload['body'] ?? '') }}</textarea></label>
                <label>Metadata<textarea name="metadata_json" rows="5">{{ old('metadata_json', json_encode($payload['metadata'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea></label>
                @can('content.edit')<button class="button button-primary" type="submit">Lưu draft</button>@endcan
            </form>
            @if($selectedStimulus)
                <h3>Câu hỏi liên kết</h3>
                @forelse($selectedStimulus->questions as $question)
                    <p><a href="{{ route('admin.questions.edit', $question) }}">#{{ $question->id }} · {{ strip_tags($question->prompt) }}</a></p>
                @empty
                    <p>Chưa có câu hỏi liên kết</p>
                @endforelse
            @endif
        </section>
    </div>
@endif
