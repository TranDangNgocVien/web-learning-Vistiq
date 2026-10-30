@php
    $typeLabels = [
        'vocabulary' => 'Từ vựng',
        'grammar' => 'Ngữ pháp',
        'collocation' => 'Collocation',
        'phrasal_verb' => 'Cụm động từ',
        'idiom' => 'Thành ngữ',
    ];
    $statusLabels = [
        'draft' => 'Bản nháp',
        'review' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'published' => 'Đã xuất bản',
        'archived' => 'Đã lưu trữ',
    ];
    $typesById = $types->keyBy('id');
@endphp

<section class="knowledge-library">

    <nav id="admin-knowledge-tabs" class="knowledge-tabs" aria-label="Nhóm kiến thức">
        <a data-ajax-filter-reset="#admin-knowledge-tabs, #admin-knowledge-results, #admin-knowledge-export" href="{{ route('admin.knowledge.index', array_filter(['q' => $search, 'status' => $selectedStatus])) }}" @class(['is-active' => $selectedType === ''])>
            Tất cả <span>{{ $typeCounts->sum() }}</span>
        </a>
        @foreach($types as $type)
            <a data-ajax-filter-reset="#admin-knowledge-tabs, #admin-knowledge-results, #admin-knowledge-export" href="{{ route('admin.knowledge.index', array_filter(['type' => $type->code, 'q' => $search, 'status' => $selectedStatus])) }}" @class(['is-active' => $selectedType === $type->code])>
                {{ $typeLabels[$type->code] ?? $type->label }}
                <span>{{ $typeCounts[$type->id] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <div class="knowledge-toolbar">
        <form method="get" action="{{ route('admin.knowledge.index') }}" class="knowledge-search" data-ajax-filter-form data-ajax-target="#admin-knowledge-tabs, #admin-knowledge-results, #admin-knowledge-export" data-ajax-on-change data-ajax-debounce="350">
            @if($selectedType !== '')
                <input type="hidden" name="type" value="{{ $selectedType }}">
            @endif

            <label>
                <span class="sr-only">Tìm theo từ hoặc chủ điểm</span>
                <input type="search" name="q" value="{{ $search }}" placeholder="Tìm từ, cụm từ hoặc chủ điểm">
            </label>

            <select name="status" style="height: 42px; padding: 0 12px; border: 1px solid var(--line); border-radius: 9px; background: #fff; font: inherit;">
                <option value="">Tất cả trạng thái</option>
                @foreach($statusLabels as $sCode => $sLabel)
                    <option value="{{ $sCode }}" @selected($selectedStatus === $sCode)>{{ $sLabel }}</option>
                @endforeach
            </select>

            <button class="button button-quiet" type="submit">Tìm kiếm</button>

            @if($search !== '' || $selectedStatus !== '')
                <a data-ajax-filter-reset="#admin-knowledge-tabs, #admin-knowledge-results, #admin-knowledge-export" href="{{ route('admin.knowledge.index', array_filter(['type' => $selectedType])) }}">Xóa bộ lọc</a>
            @endif
        </form>

        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <span id="admin-knowledge-export" data-ajax-results>@can('content.view')<a class="button button-quiet" href="{{ route('admin.knowledge.export', array_filter(['type' => $selectedType, 'status' => $selectedStatus, 'q' => $search])) }}">Xuất CSV</a>@endcan</span>

            @can('content.edit')
                <a class="button button-quiet" href="{{ route('admin.knowledge.import', array_filter(['type' => $selectedType])) }}">Nhập CSV</a>
                <a class="button button-primary" href="{{ route('admin.knowledge.create', array_filter(['type' => $selectedType])) }}">+ Thêm kiến thức</a>
            @endcan
        </div>
    </div>

    <section id="admin-knowledge-results" class="panel knowledge-table-panel" data-ajax-results>
        <div class="knowledge-table-scroll">
            <table class="knowledge-table">
                <thead>
                    <tr>
                        <th>Kiến thức</th>
                        <th>Nhóm</th>
                        <th>Cấp độ</th>
                        <th>Trạng thái</th>
                        <th>Cập nhật</th>
                        <th><span class="sr-only">Thao tác</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        @php
                            $metadata = $item->metadata ?? [];
                            $typeCode = $typesById[$item->learning_item_type_id]->code ?? null;
                            $levelLabel = $levelsById[$item->proficiency_level_id] ?? null;
                        @endphp
                        <tr>
                            <td>
                                <a class="knowledge-item-link" href="{{ route('admin.knowledge.preview', $item) }}">
                                    <strong>{{ $item->canonical_text }}</strong>
                                    @if(filled($metadata['meaning_vi'] ?? null))
                                        <small>{{ $metadata['meaning_vi'] }}</small>
                                    @elseif(filled($metadata['summary'] ?? null))
                                        <small>{{ $metadata['summary'] }}</small>
                                    @endif
                                </a>
                            </td>
                            <td><span class="knowledge-type-pill">{{ $typeLabels[$typeCode] ?? 'Khác' }}</span></td>
                            <td>{{ $levelLabel ?: '—' }}</td>
                            <td><span class="knowledge-status knowledge-status-{{ $item->status }}">{{ $statusLabels[$item->status] ?? $item->status }}</span></td>
                            <td>{{ $item->updated_at?->format('d/m/Y') ?? '—' }}</td>
                            <td style="white-space: nowrap;">
                                <a class="knowledge-edit-link" href="{{ route('admin.knowledge.preview', $item) }}">Xem / duyệt</a>
                                @can('content.edit')<a class="knowledge-edit-link" href="{{ route('admin.knowledge.edit', $item) }}">Chỉnh sửa</a>@endcan
                                @if($item->status === 'draft')
                                    @can('content.edit')
                                        <form method="post" action="{{ route('admin.knowledge.destroy', $item) }}" style="display: inline-block; margin-left: 10px;" data-confirm="Xóa bản nháp {{ $item->canonical_text }}? Không thể hoàn tác." data-confirm-label="Xóa bản nháp" data-pending-label="Đang xóa…">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="knowledge-edit-link" style="background: none; border: none; padding: 0; color: #c93434; cursor: pointer;">Xóa</button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="knowledge-empty">
                                @if($search !== '' || $selectedStatus !== '')
                                    Không tìm thấy kiến thức phù hợp với bộ lọc hiện tại. <a href="{{ route('admin.knowledge.index', array_filter(['type' => $selectedType])) }}">Xóa bộ lọc</a>
                                @else
                                    Chưa có kiến thức trong nhóm này.
                                    @can('content.edit')
                                        <a href="{{ route('admin.knowledge.create', array_filter(['type' => $selectedType])) }}">Thêm kiến thức mới</a> hoặc <a href="{{ route('admin.knowledge.import', array_filter(['type' => $selectedType])) }}">Nhập từ CSV</a>.
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div data-ajax-pagination>{{ $items->links() }}</div>
    </section>
</section>
