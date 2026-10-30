@php
    $groupKinds = [
        'conversation' => 'Conversation',
        'talk' => 'Talk',
        'part6' => 'Part 6 · Text completion',
        'reading_set' => 'Reading set',
        'ielts_section' => 'IELTS section',
        'question_group' => 'Question group',
    ];
    $partsById = $parts->keyBy('id');
    $defaultPartId = old('exam_part_id', $parts->first()?->id);
    $selectedCreateStimuli = array_map('strval', (array) old('stimulus_ids', []));
@endphp

<div class="question-groups-page">
    @can('content.edit')
        <section class="question-groups-create" aria-labelledby="question-groups-create-title">
            <header class="question-groups-create__header">
                <div>
                    <h1 id="question-groups-create-title">Tạo nhóm câu hỏi</h1>
                    <p>Nhập thông tin nhóm và chọn tối đa 3 tài nguyên dùng chung.</p>
                </div>
            </header>

            <form class="question-groups-create__form" method="post" action="{{ route('admin.groups.store') }}">
                @csrf
                <div class="question-groups-form-grid">
                    <label class="question-groups-field">
                        <span>Tên nhóm <b>*</b></span>
                        <input name="title" value="{{ old('title') }}" placeholder="Ví dụ: TOEIC-F1-P3-G01" required>
                        <small>Mã này sẽ xuất hiện khi chọn nhóm ở màn hình câu hỏi.</small>
                    </label>

                    <label class="question-groups-field">
                        <span>Loại nội dung <b>*</b></span>
                        <select name="kind" required>
                            @foreach($groupKinds as $kind => $label)
                                <option value="{{ $kind }}" @selected(old('kind', 'conversation') === $kind)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="question-groups-field">
                        <span>Exam part <b>*</b></span>
                        <select name="exam_part_id" required>
                            @foreach($parts as $part)
                                <option value="{{ $part->id }}" @selected((string) $defaultPartId === (string) $part->id)>{{ $part->examType?->name ?? 'Exam' }} · {{ $part->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="question-groups-field question-groups-field--wide">
                        <span>Tài nguyên dùng chung <b class="question-groups-optional">tối đa 3</b></span>
                        <div class="question-groups-resource-picker" data-resource-picker>
                            <label class="sr-only" for="group-resource-search-create">Tìm tài nguyên dùng chung</label>
                            <input id="group-resource-search-create" class="question-groups-resource-search" type="search" placeholder="Tìm theo tên audio hoặc passage..." autocomplete="off" data-resource-search>
                            <select name="stimulus_ids[]" multiple size="4" data-resource-select>
                                @foreach($stimuli as $stimulus)
                                    <option value="{{ $stimulus->id }}" @selected(in_array((string) $stimulus->id, $selectedCreateStimuli, true))>{{ $stimulus->stimulus_type === 'audio' ? 'Audio' : 'Passage' }} · {{ $stimulus->title }}</option>
                                @endforeach
                            </select>
                            <p class="question-groups-resource-empty" data-resource-empty hidden>Không tìm thấy tài nguyên phù hợp.</p>
                        </div>
                        <small>Giữ Ctrl/Cmd để chọn nhiều. Chưa có audio? <a href="{{ route('admin.resources.index', ['type' => 'audio']) }}">Mở Tài nguyên</a> rồi quay lại gắn vào nhóm.</small>
                    </div>
                </div>

                <div class="question-groups-create__footer">
                    <p>Tài nguyên có thể chỉnh sửa lại sau khi tạo nhóm.</p>
                    <button class="button button-primary" type="submit"><span>Tạo nhóm</span><b aria-hidden="true">→</b></button>
                </div>
            </form>
        </section>
    @endcan

    <section class="question-groups-directory" aria-labelledby="question-groups-directory-title">
        <div class="question-groups-directory__heading">
            <div>
                <h2 id="question-groups-directory-title">Nhóm câu hỏi</h2>
                <p>Chọn một dòng để xem hoặc chỉnh sửa.</p>
            </div>
            <span class="question-groups-count"><b>{{ $groups->total() }}</b> nhóm</span>
        </div>

        <div class="question-groups-list">
            @forelse($groups as $group)
                @php
                    $decodedStimulusIds = json_decode((string) ($group->stimulus_ids ?? ''), true);
                    $groupStimulusIds = is_array($decodedStimulusIds) ? array_map('intval', $decodedStimulusIds) : [];
                    $decodedGroupMetadata = json_decode((string) ($group->metadata ?? ''), true);
                    $groupMetadata = is_array($decodedGroupMetadata) ? $decodedGroupMetadata : [];
                    $part = $partsById->get($group->exam_part_id);
                    $partLabel = $part ? (($part->examType?->name ?? 'Exam').' · '.$part->name) : 'Chưa chọn exam part';
                    $questionCount = (int) ($questionCounts->get($group->id, 0) ?? 0);
                    $hasGroupAudio = filled($groupMetadata['audio_id'] ?? null);
                    $groupResources = $stimuli->whereIn('id', $groupStimulusIds);
                @endphp

                <details class="question-group-card">
                    <summary class="question-group-card__summary">
                        <span class="question-group-card__id"><small>GROUP</small><strong>#{{ str_pad((string) $group->id, 3, '0', STR_PAD_LEFT) }}</strong></span>
                        <span class="question-group-card__identity"><strong>{{ $group->title }}</strong><small>{{ $partLabel }} · {{ $questionCount }} câu hỏi</small></span>
                        <span class="question-group-card__kind"><small>LOẠI</small><b>{{ $groupKinds[$group->kind] ?? $group->kind }}</b></span>
                        <span class="question-group-card__resource">
                            @if($hasGroupAudio)
                                <b class="question-groups-chip question-groups-chip--audio"><i aria-hidden="true"></i>Audio nhóm đã gắn</b>
                            @elseif($groupResources->isNotEmpty())
                                <b class="question-groups-chip question-groups-chip--resource"><i aria-hidden="true"></i>{{ $groupResources->count() }} tài nguyên</b>
                            @else
                                <b class="question-groups-chip question-groups-chip--empty"><i aria-hidden="true"></i>Chưa gắn tài nguyên</b>
                            @endif
                        </span>
                        <span class="question-group-card__toggle"><span>Chỉnh sửa</span><b aria-hidden="true">⌄</b></span>
                    </summary>

                    <div class="question-group-card__editor">
                        <div class="question-group-card__editor-heading">
                            <div><h3>Chỉnh sửa nhóm #{{ str_pad((string) $group->id, 3, '0', STR_PAD_LEFT) }}</h3></div>
                            <span class="question-groups-edit-note">Cập nhật áp dụng cho các câu hỏi trong nhóm.</span>
                        </div>

                        @can('content.edit')
                            <form class="question-groups-editor-form" method="post" action="{{ route('admin.groups.update', $group->id) }}">
                                @csrf
                                @method('PUT')

                                <div class="question-groups-form-grid">
                                    <label class="question-groups-field">
                                        <span>Tên nhóm <b>*</b></span>
                                        <input name="title" value="{{ $group->title }}" required>
                                    </label>

                                    <label class="question-groups-field">
                                        <span>Loại nội dung <b>*</b></span>
                                        <select name="kind" required>
                                            @foreach($groupKinds as $kind => $label)
                                                <option value="{{ $kind }}" @selected($group->kind === $kind)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="question-groups-field">
                                        <span>Exam part <b>*</b></span>
                                        <select name="exam_part_id" required>
                                            @foreach($parts as $partOption)
                                                <option value="{{ $partOption->id }}" @selected((int) $partOption->id === (int) $group->exam_part_id)>{{ $partOption->examType?->name ?? 'Exam' }} · {{ $partOption->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <div class="question-groups-field question-groups-field--wide">
                                        <span>Tài nguyên dùng chung <b class="question-groups-optional">tối đa 3</b></span>
                                        <div class="question-groups-resource-picker" data-resource-picker>
                                            <label class="sr-only" for="group-resource-search-{{ $group->id }}">Tìm tài nguyên dùng chung</label>
                                            <input id="group-resource-search-{{ $group->id }}" class="question-groups-resource-search" type="search" placeholder="Tìm theo tên audio hoặc passage..." autocomplete="off" data-resource-search>
                                            <select name="stimulus_ids[]" multiple size="4" data-resource-select>
                                                @foreach($stimuli as $stimulus)
                                                    <option value="{{ $stimulus->id }}" @selected(in_array((int) $stimulus->id, $groupStimulusIds, true))>{{ $stimulus->stimulus_type === 'audio' ? 'Audio' : 'Passage' }} · {{ $stimulus->title }}</option>
                                                @endforeach
                                            </select>
                                            <p class="question-groups-resource-empty" data-resource-empty hidden>Không tìm thấy tài nguyên phù hợp.</p>
                                        </div>
                                        <small>Audio nhóm được gắn từ màn hình câu hỏi. <a href="{{ route('admin.resources.index', ['type' => 'audio']) }}">Mở Tài nguyên</a> để quản lý file.</small>
                                    </div>
                                </div>

                                <div class="question-groups-editor-form__footer">
                                    <p><span aria-hidden="true">✓</span> {{ $questionCount }} câu hỏi đang tham chiếu nhóm này.</p>
                                    <button class="button button-primary" type="submit"><span>Lưu thay đổi</span><b aria-hidden="true">→</b></button>
                                </div>
                            </form>

                            <div class="question-groups-delete-row">
                                <span>Chỉ xóa được nhóm chưa có câu hỏi.</span>
                                <form method="post" action="{{ route('admin.groups.destroy', $group->id) }}" data-confirm="Xóa nhóm chưa sử dụng?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-danger" type="submit">Xóa nhóm</button>
                                </form>
                            </div>
                        @endcan
                    </div>
                </details>
            @empty
                <div class="question-groups-empty">
                    <span class="question-groups-empty__mark" aria-hidden="true">＋</span>
                    <div><h3>Chưa có nhóm câu hỏi.</h3><p>Tạo nhóm mới ở phía trên để bắt đầu.</p></div>
                </div>
            @endforelse
        </div>

        @if($groups->total() > 0)
            <div class="question-groups-pagination">
                <span>Hiển thị {{ $groups->firstItem() }}–{{ $groups->lastItem() }} / {{ $groups->total() }} nhóm</span>
                {{ $groups->links() }}
            </div>
        @endif
    </section>
</div>
