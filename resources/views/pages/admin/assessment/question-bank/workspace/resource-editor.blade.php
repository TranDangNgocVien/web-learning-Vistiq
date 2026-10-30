@php
    $metadata = $asset->metadata ?? [];
    $isAudio = $asset->resource_kind === 'audio';
    $storageLabels = ['cloudinary' => 'Cloudinary', 'r2' => 'R2', 's3' => 'S3'];
    $assetId = ($isAudio ? 'A' : 'I').str_pad((string) $asset->id, 5, '0', STR_PAD_LEFT);
@endphp
<section class="resource-editor resource-library" data-resource-editor>
    <header class="resource-editor-heading">
        <div><h1>Chỉnh sửa tài nguyên <small>{{ $assetId }}</small></h1><p>Cập nhật thông tin và các câu hỏi đang sử dụng tài nguyên này.</p></div>
        <div class="resource-editor-actions"><a class="resource-secondary" href="{{ route('admin.resources.index') }}">Quay lại</a><a class="resource-secondary" href="{{ route('admin.media.show', $asset) }}" target="_blank" rel="noopener"><i data-resource-icon="external-link"></i>Xem trước</a>@can('content.edit')<button class="resource-primary" type="submit" form="resource-edit-details"><i data-resource-icon="save"></i>Lưu thay đổi</button>@endcan</div>
    </header>

    <div class="resource-editor-layout">
        <main class="resource-editor-main">
            <form id="resource-edit-details" class="resource-editor-card" method="post" action="{{ route('admin.media.update', $asset) }}">
                @csrf @method('put')
                <div class="resource-editor-section-heading"><div><h2>Thông tin tài nguyên</h2><p>Thông tin mô tả và phân loại tệp.</p></div><span class="resource-badge {{ $asset->resource_status === 'used' ? 'is-used' : 'is-unassigned' }}">{{ $asset->resource_status === 'used' ? 'Đang dùng' : 'Chưa gắn' }}</span></div>
                <div class="resource-editor-fields">
                    <label class="is-wide">Tên tài nguyên <em>*</em><input name="title" value="{{ $asset->title }}" maxlength="255" required></label>
                    <label>Loại tài nguyên<input value="{{ $isAudio ? 'Audio · '.strtoupper($asset->resource_format) : 'Hình ảnh · '.strtoupper($asset->resource_format) }}" readonly></label>
                    <label>Danh mục<input name="category" value="{{ $metadata['category'] ?? $asset->resource_category }}" maxlength="120" placeholder="Ví dụ: TOEIC Part 4"></label>
                    <label>Nguồn tải lên<input value="{{ $asset->resource_creator }}" readonly></label>
                    <label>Dung lượng<input value="{{ $formatFileSize((int) $asset->size) }}" readonly></label>
                    <label>Thời lượng<input value="{{ isset($metadata['duration_seconds']) ? gmdate('i:s', (int) $metadata['duration_seconds']) : '—' }}" readonly></label>
                    <label>Lưu trữ<input value="{{ $storageLabels[$asset->disk] ?? 'Kho nội bộ' }}" readonly></label>
                    <label class="is-wide">Nguồn tài nguyên<input name="source" value="{{ $metadata['source'] ?? '' }}" maxlength="1000" placeholder="Nhập nguồn hoặc xuất xứ tài nguyên"></label>
                    <label class="is-wide">Mô tả<textarea name="description" rows="3" maxlength="2000" placeholder="Mô tả ngắn về tài nguyên">{{ $metadata['description'] ?? '' }}</textarea></label>
                    <label class="is-wide">Thẻ / Tags <small>Phân cách bằng dấu phẩy</small><input name="tags" value="{{ implode(', ', is_array($metadata['tags'] ?? null) ? $metadata['tags'] : []) }}" maxlength="500" placeholder="TOEIC, Part 4, Listening"></label>
                    @if($isAudio)<label class="is-wide">Bản chép lời<textarea name="transcript" rows="4" maxlength="50000" placeholder="Nội dung audio nếu có">{{ $metadata['transcript'] ?? '' }}</textarea></label>@endif
                </div>
            </form>

            @if($isAudio)
                <section class="resource-editor-card resource-group-card">
                    <div class="resource-editor-section-heading"><div><h2>Nhóm câu hỏi sử dụng audio</h2><p>Gắn hoặc gỡ audio này khỏi nhóm. Các câu trong nhóm sẽ dùng chung tài nguyên.</p></div><span class="resource-count-pill">{{ $attachedGroups->count() }} nhóm</span></div>
                    @can('content.edit')
                        <form class="resource-group-attach" method="post" action="{{ route('admin.resources.groups.set', $asset) }}">
                            @csrf
                            <label>Chọn nhóm câu hỏi<select name="group_id" required><option value="">Chọn nhóm</option>@foreach($groups as $group)<option value="{{ $group->id }}" @disabled((int) ($questionCounts[$group->id] ?? 0) === 0)>{{ $group->title }} · {{ $questionCounts[$group->id] ?? 0 }} câu</option>@endforeach</select></label>
                            <button class="resource-secondary" type="submit" name="action" value="attach"><i data-resource-icon="link"></i>Gắn audio</button>
                        </form>
                    @endcan
                    <div class="resource-applied-groups">
                        @forelse($attachedGroups as $group)
                            @php $groupMetadata = json_decode((string) ($group->metadata ?? ''), true) ?: []; @endphp
                            <div class="resource-applied-group"><span class="resource-stat-icon is-violet"><i data-resource-icon="database"></i></span><span><strong>{{ $group->title }}</strong><small>{{ $questionCounts[$group->id] ?? 0 }} câu hỏi · {{ $group->kind }}</small></span>@can('content.edit')<form method="post" action="{{ route('admin.resources.groups.set', $asset) }}">@csrf<input type="hidden" name="group_id" value="{{ $group->id }}"><button type="submit" class="resource-icon-button is-danger" name="action" value="detach" aria-label="Gỡ audio khỏi {{ $group->title }}" title="Gỡ audio"><i data-resource-icon="unlink"></i></button></form>@endcan</div>
                        @empty
                            <p class="resource-editor-empty">Chưa gắn audio này vào nhóm câu hỏi.</p>
                        @endforelse
                    </div>
                </section>
            @endif

            <section class="resource-editor-card resource-question-card">
                <div class="resource-editor-section-heading"><div><h2>Gắn câu hỏi {{ $isAudio ? 'vào audio' : 'với hình ảnh' }}</h2><p>Chọn các câu hỏi rồi gắn hoặc gỡ liên kết tài nguyên.</p></div><span class="resource-count-pill">{{ number_format($asset->linkedQuestions->count()) }} câu đang liên kết</span></div>
                <form class="resource-question-filters" method="get" action="{{ route('admin.resources.edit', $asset) }}" data-ajax-filter-form data-ajax-target="#resource-question-results" data-ajax-on-change data-ajax-debounce="350">
                    <label class="is-search">Tìm câu hỏi<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm theo ID hoặc nội dung"></label>
                    <label>Kỳ thi<select name="exam_type"><option value="">Tất cả</option>@foreach($examTypes as $type)<option value="{{ $type->id }}" @selected((string) ($filters['exam_type'] ?? '') === (string) $type->id)>{{ $type->name }}</option>@endforeach</select></label>
                    <label>Part / Kỹ năng<select name="part"><option value="">Tất cả</option>@foreach($parts as $part)<option value="{{ $part->id }}" @selected((string) ($filters['part'] ?? '') === (string) $part->id)>{{ $part->name }}</option>@endforeach</select></label>
                    <label>Nhóm câu hỏi<select name="group"><option value="">Tất cả</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected((string) ($filters['group'] ?? '') === (string) $group->id)>{{ $group->title }}</option>@endforeach</select></label>
                    <button class="resource-secondary" type="submit"><i data-resource-icon="search"></i>Lọc</button><a class="resource-secondary" data-ajax-filter-reset="#resource-question-results" href="{{ route('admin.resources.edit', $asset) }}">Xóa bộ lọc</a>
                </form>
                <div id="resource-question-results" data-ajax-results>
                @can('content.edit')
                    <form method="post" action="{{ route('admin.resources.questions.sync', $asset) }}" data-resource-question-form>
                        @csrf @method('put')
                        <div class="resource-question-toolbar"><label><input type="checkbox" data-resource-select-all-questions> Chọn tất cả trên trang</label><span data-resource-question-selection>Chọn tối đa 100 câu</span><div><button class="resource-secondary" type="submit" name="action" value="detach"><i data-resource-icon="unlink"></i>Gỡ liên kết</button><button class="resource-primary" type="submit" name="action" value="attach"><i data-resource-icon="link"></i>Gắn câu hỏi đã chọn</button></div></div>
                        <div class="resource-table-scroll resource-question-table-scroll"><table class="resource-table resource-question-table"><thead><tr><th><span class="sr-only">Chọn</span></th><th>ID câu hỏi</th><th>Kỳ thi</th><th>Part / Kỹ năng</th><th>Nội dung</th><th>Nhóm câu hỏi</th><th>Trạng thái</th></tr></thead><tbody>
                            @forelse($questions as $question)
                                @php
                                    $part = $question->examPart;
                                    $revision = $question->draftRevision;
                                    $prompt = $revision?->payload['prompt'] ?? $question->prompt;
                                    $status = $question->contentStatus() ?? 'draft';
                                    $group = $groups->firstWhere('id', $question->question_group_id);
                                @endphp
                                <tr><td><input type="checkbox" name="ids[]" value="{{ $question->id }}" data-resource-question-select @checked(in_array($question->id, $directQuestionIds, true)) aria-label="Chọn câu Q{{ $question->id }}"></td><td><a href="{{ route('admin.questions.edit', $question) }}">Q{{ str_pad((string) $question->id, 5, '0', STR_PAD_LEFT) }}</a></td><td>{{ $part?->examType?->name ?? '—' }}</td><td>{{ $part?->name ?? '—' }}</td><td class="resource-question-prompt" title="{{ strip_tags((string) $prompt) }}">{{ strip_tags((string) $prompt) }}</td><td>{{ $group?->title ?? '—' }}</td><td><span class="resource-badge {{ $status === 'published' ? 'is-used' : 'is-unassigned' }}">{{ $status === 'published' ? 'Đã duyệt' : 'Bản nháp' }}</span></td></tr>
                            @empty
                                <tr><td colspan="7"><div class="resource-editor-empty">Không tìm thấy câu hỏi phù hợp với bộ lọc.</div></td></tr>
                            @endforelse
                        </tbody></table></div>
                    </form>
                @else
                    <div class="resource-table-scroll resource-question-table-scroll"><table class="resource-table resource-question-table"><thead><tr><th>ID câu hỏi</th><th>Kỳ thi</th><th>Part / Kỹ năng</th><th>Nội dung</th><th>Nhóm câu hỏi</th><th>Trạng thái</th></tr></thead><tbody>@forelse($questions as $question)<tr><td><a href="{{ route('admin.questions.edit', $question) }}">Q{{ str_pad((string) $question->id, 5, '0', STR_PAD_LEFT) }}</a></td><td>{{ $question->examPart?->examType?->name ?? '—' }}</td><td>{{ $question->examPart?->name ?? '—' }}</td><td class="resource-question-prompt">{{ strip_tags((string) ($question->draftRevision?->payload['prompt'] ?? $question->prompt)) }}</td><td>{{ $groups->firstWhere('id', $question->question_group_id)?->title ?? '—' }}</td><td>{{ $question->contentStatus() ?? 'draft' }}</td></tr>@empty<tr><td colspan="6"><div class="resource-editor-empty">Không tìm thấy câu hỏi phù hợp với bộ lọc.</div></td></tr>@endforelse</tbody></table></div>
                @endcan
                <footer class="resource-question-pagination" data-ajax-pagination><span>{{ $questions->firstItem() ?? 0 }}–{{ $questions->lastItem() ?? 0 }} / {{ $questions->total() }} câu</span>{{ $questions->links() }}</footer>
                </div>
            </section>
        </main>

        <aside class="resource-editor-aside">
            <section class="resource-editor-card resource-preview-card"><div class="resource-editor-section-heading"><h2>Xem trước tài nguyên</h2></div>
                <div class="resource-detail-labels"><span class="resource-badge is-{{ $asset->resource_kind }}">{{ $isAudio ? 'Audio' : 'Hình ảnh' }}</span><span class="resource-badge">{{ $asset->resource_category }}</span><span class="resource-badge">{{ strtoupper($asset->resource_format) }}</span></div>
                <h3 class="resource-editor-preview-title">{{ $asset->title }}</h3>
                @if($isAudio)<audio class="resource-editor-audio" controls preload="none" src="{{ route('admin.media.show', $asset) }}">Trình duyệt không hỗ trợ phát audio.</audio>
                @elseif($asset->resource_kind === 'image')<a class="resource-editor-image" href="{{ route('admin.media.show', $asset) }}" target="_blank" rel="noopener"><img src="{{ route('admin.media.show', $asset) }}" alt="{{ $asset->title }}"></a>
                @else<a class="resource-editor-file" href="{{ route('admin.media.show', $asset) }}" target="_blank" rel="noopener">Mở tệp gốc</a>@endif
                <dl class="resource-editor-facts"><div><dt><i data-resource-icon="user-round"></i>Người tải lên</dt><dd>{{ $asset->resource_creator }}</dd></div><div><dt><i data-resource-icon="clock-3"></i>Cập nhật</dt><dd>{{ $asset->updated_at?->locale('vi')->diffForHumans() }}</dd></div><div><dt><i data-resource-icon="hard-drive"></i>Lưu trữ</dt><dd>{{ $storageLabels[$asset->disk] ?? 'Kho nội bộ' }}</dd></div></dl>
            </section>
            <section class="resource-editor-card"><div class="resource-editor-section-heading"><h2>Thống kê liên kết</h2></div><div class="resource-editor-stats"><div><span class="resource-stat-icon is-blue"><i data-resource-icon="link"></i></span><span><strong>{{ $asset->linkedQuestions->count() }}</strong><small>Câu hỏi liên kết</small></span></div><div><span class="resource-stat-icon is-violet"><i data-resource-icon="database"></i></span><span><strong>{{ $attachedGroups->count() }}</strong><small>Nhóm câu hỏi</small></span></div><div><span class="resource-stat-icon is-green"><i data-resource-icon="circle-check"></i></span><span><strong>{{ $directQuestionIds ? count($directQuestionIds) : 0 }}</strong><small>Gắn trực tiếp</small></span></div></div></section>
            <section class="resource-editor-card"><div class="resource-editor-section-heading"><h2>Nhóm đang dùng</h2></div><div class="resource-aside-group-list">@forelse($attachedGroups as $group)<div><i data-resource-icon="database"></i><span><strong>{{ $group->title }}</strong><small>{{ $questionCounts[$group->id] ?? 0 }} câu hỏi</small></span></div>@empty<p>Chưa gắn vào nhóm nào.</p>@endforelse</div></section>
            <section class="resource-editor-card"><div class="resource-editor-section-heading"><h2>Câu hỏi đang dùng</h2><span>{{ $asset->linkedQuestions->count() }}</span></div><div class="resource-aside-question-list">@forelse($asset->linkedQuestions->take(8) as $question)<a href="{{ route('admin.questions.edit', $question) }}"><span>Q{{ str_pad((string) $question->id, 5, '0', STR_PAD_LEFT) }}</span><small>{{ \Illuminate\Support\Str::limit(strip_tags((string) ($question->draftRevision?->payload['prompt'] ?? $question->prompt)), 58) }}</small></a>@empty<p>Chưa có câu hỏi dùng tài nguyên này.</p>@endforelse</div></section>
        </aside>
    </div>
</section>
