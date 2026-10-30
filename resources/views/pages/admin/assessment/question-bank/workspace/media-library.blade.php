@php
    $indexRoute = route('admin.resources.index');
    $formatFileSize = static function (int $size): string {
        foreach (['GB' => 1073741824, 'MB' => 1048576, 'KB' => 1024] as $unit => $divisor) {
            if ($size >= $divisor) return number_format($size / $divisor, $unit === 'KB' ? 0 : 1).' '.$unit;
        }
        return $size.' B';
    };
    $type = $filters['type'] ?? '';
    $categories = ['' => 'Tất cả', 'toeic' => 'TOEIC', 'ielts' => 'IELTS', 'part-1' => 'Part 1', 'part-2' => 'Part 2', 'part-3' => 'Part 3', 'part-4' => 'Part 4', 'listening' => 'Listening', 'reading' => 'Reading'];
@endphp
<section class="resource-library" data-resource-library>
    <header class="resource-heading"><h1>Tài nguyên <span>{{ number_format($resourceStats['total']) }}</span></h1>@can('content.edit')<button class="resource-primary" type="button" data-resource-upload><i data-resource-icon="upload"></i>Tải lên</button>@endcan</header>
    <div class="resource-notice" data-resource-notice role="status" aria-live="polite" hidden><span data-resource-notice-text></span><button type="button" class="resource-secondary" data-resource-retry hidden><i data-resource-icon="refresh-cw"></i>Thử lại</button></div>
    <div class="resource-stats">
        @foreach([
            ['Tổng tài nguyên', number_format($resourceStats['total']), 'package', 'blue'],
            ['Audio', number_format($resourceStats['audio']), 'audio-lines', 'green'],
            ['Hình ảnh', number_format($resourceStats['image']), 'image', 'orange'],
            ['Dung lượng đã dùng', $formatFileSize($resourceStats['size']), 'database', 'violet'],
            ['Chưa gắn câu hỏi', number_format($resourceStats['unassigned']), 'link', 'blue'],
        ] as [$label, $value, $icon, $color])
            <div class="resource-stat"><span class="resource-stat-icon is-{{ $color }}"><i data-resource-icon="{{ $icon }}"></i></span><div><span>{{ $label }}</span><strong>{{ $value }}</strong></div></div>
        @endforeach
    </div>
    <div class="resource-workspace">
        <div class="resource-main">
            <form action="{{ $indexRoute }}" method="get" class="resource-filters" data-resource-filters>
                <input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="category" value="{{ $filters['category'] ?? '' }}">
                <div class="resource-toolbar">
                    <nav class="resource-tabs" aria-label="Loại tài nguyên">@foreach(['' => 'Tất cả', 'audio' => 'Audio', 'image' => 'Hình ảnh'] as $key => $label)<button type="button" data-resource-type="{{ $key }}" class="{{ $type === $key ? 'is-active' : '' }}" aria-pressed="{{ $type === $key ? 'true' : 'false' }}">@if($key)<i data-resource-icon="{{ $key === 'audio' ? 'audio-lines' : 'image' }}"></i>@endif{{ $label }}</button>@endforeach</nav>
                    <label class="resource-search"><i data-resource-icon="search"></i><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm tài nguyên..." aria-label="Tìm theo tên, tệp hoặc nguồn"><button type="submit" class="resource-icon-button" title="Tìm kiếm" aria-label="Tìm kiếm"><i data-resource-icon="arrow-right"></i></button></label>
                </div>
                <div class="resource-categories" aria-label="Danh mục">@foreach($categories as $key => $label)<button type="button" data-resource-category="{{ $key }}" class="{{ ($filters['category'] ?? '') === $key ? 'is-active' : '' }}" aria-pressed="{{ ($filters['category'] ?? '') === $key ? 'true' : 'false' }}">{{ $label }}</button>@endforeach</div>
                <div class="resource-filter-grid">
                    <label>Loại file<select name="format" data-resource-auto-submit><option value="">Tất cả</option>@foreach(['mp3', 'wav', 'm4a', 'ogg', 'jpg', 'jpeg', 'png', 'webp'] as $format)<option value="{{ $format }}" @selected(($filters['format'] ?? '') === $format)>{{ strtoupper($format) }}</option>@endforeach</select></label>
                    <label>Trạng thái<select name="status" data-resource-auto-submit><option value="">Tất cả</option><option value="used" @selected(($filters['status'] ?? '') === 'used')>Đang dùng</option><option value="unassigned" @selected(($filters['status'] ?? '') === 'unassigned')>Chưa gắn</option></select></label>
                    <label>Dung lượng<select name="size" data-resource-auto-submit><option value="">Tất cả</option><option value="small" @selected(($filters['size'] ?? '') === 'small')>Dưới 1 MB</option><option value="medium" @selected(($filters['size'] ?? '') === 'medium')>1 - 10 MB</option><option value="large" @selected(($filters['size'] ?? '') === 'large')>Trên 10 MB</option></select></label>
                    <label>Người tải lên<select name="creator" data-resource-auto-submit><option value="">Tất cả</option>@foreach($creators as $id => $name)<option value="{{ $id }}" @selected((int) ($filters['creator'] ?? 0) === $id)>{{ $name }}</option>@endforeach</select></label>
                    <a class="resource-reset" href="{{ $indexRoute }}"><i data-resource-icon="filter-x"></i>Xóa bộ lọc</a>
                </div>
                <div class="resource-list-toolbar"><span><strong>{{ number_format($assets->total()) }}</strong> tài nguyên <span data-resource-selected-count></span></span><label>Sắp xếp: <select name="sort" data-resource-auto-submit><option value="updated" @selected(($filters['sort'] ?? '') === 'updated')>Cập nhật mới nhất</option><option value="name" @selected(($filters['sort'] ?? '') === 'name')>Tên A - Z</option><option value="size" @selected(($filters['sort'] ?? '') === 'size')>Dung lượng lớn nhất</option></select></label></div>
                <input type="hidden" name="per_page" value="{{ $assets->perPage() }}">
            </form>
            <form method="post" action="{{ route('admin.media.destroy') }}" data-resource-delete-form>
                @csrf @method('delete')
                @can('content.edit')<div class="resource-bulk" data-resource-bulk hidden><span data-resource-bulk-label></span><button type="submit" class="resource-delete-button"><i data-resource-icon="trash-2"></i>Xóa đã chọn</button><button type="button" class="resource-icon-button" data-resource-clear-selection title="Bỏ chọn" aria-label="Bỏ chọn"><i data-resource-icon="x"></i></button></div>@endcan
                <div class="resource-table-scroll"><table class="resource-table">
                    <thead><tr><th class="resource-check">@can('content.edit')<input type="checkbox" data-resource-select-all aria-label="Chọn tất cả tài nguyên trên trang">@endcan</th><th>ID</th><th>Tên tài nguyên</th><th>Loại</th><th>Danh mục</th><th>Liên kết câu hỏi</th><th>Dung lượng</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr></thead>
                    <tbody>@forelse($assets as $asset)
                        <tr data-resource-row="{{ $asset->id }}">
                            <td class="resource-check">@can('content.edit')<input type="checkbox" name="ids[]" value="{{ $asset->id }}" aria-label="Chọn {{ $asset->title }}" data-resource-select>@endcan</td>
                            <td class="resource-id">{{ $asset->resource_kind === 'audio' ? 'A' : 'I' }}{{ str_pad((string) $asset->id, 5, '0', STR_PAD_LEFT) }}</td>
                            <td><button type="button" class="resource-name" data-resource-open="{{ $asset->id }}" title="{{ $asset->title }}"><i class="is-{{ $asset->resource_kind }}" data-resource-icon="{{ $asset->resource_kind === 'audio' ? 'volume-2' : 'image' }}"></i><span>{{ $asset->title }}</span></button></td>
                            <td><span class="resource-badge is-{{ $asset->resource_kind }}">{{ $asset->resource_kind === 'audio' ? 'Audio' : ($asset->resource_kind === 'image' ? 'Hình ảnh' : 'Tệp') }}</span></td>
                            <td class="resource-category-cell" title="{{ $asset->resource_category }}">{{ $asset->resource_category }}</td>
                            <td><button type="button" class="resource-link" data-resource-open="{{ $asset->id }}" data-resource-show-questions>{{ $asset->linkedQuestions->count() }} câu hỏi</button></td>
                            <td>{{ $formatFileSize((int) $asset->size) }}</td>
                            <td><span class="resource-badge {{ $asset->resource_status === 'used' ? 'is-used' : 'is-unassigned' }}">{{ $asset->resource_status === 'used' ? 'Đang dùng' : 'Chưa gắn' }}</span></td>
                            <td title="{{ $asset->updated_at?->format('d/m/Y H:i') }}">{{ $asset->updated_at?->locale('vi')->diffForHumans() }}</td>
                            <td><div class="resource-row-actions"><button type="button" class="resource-icon-button" data-resource-open="{{ $asset->id }}" title="Xem trước" aria-label="Xem trước {{ $asset->title }}"><i data-resource-icon="{{ $asset->resource_kind === 'audio' ? 'circle-play' : 'eye' }}"></i></button>@can('content.edit')<a class="resource-icon-button" href="{{ route('admin.resources.edit', $asset) }}" title="Chỉnh sửa tài nguyên" aria-label="Sửa {{ $asset->title }}"><i data-resource-icon="square-pen"></i></a><button type="button" class="resource-icon-button is-danger" data-resource-delete="{{ $asset->id }}" title="Xóa tài nguyên" aria-label="Xóa {{ $asset->title }}"><i data-resource-icon="trash-2"></i></button>@endcan<a class="resource-icon-button" href="{{ route('admin.media.show', $asset) }}" target="_blank" rel="noopener" title="Mở tệp gốc" aria-label="Mở tệp gốc {{ $asset->title }}"><i data-resource-icon="external-link"></i></a></div></td>
                        </tr>
                    @empty<tr><td colspan="10"><div class="resource-empty"><i data-resource-icon="folder-search"></i><h2>{{ $resourceStats['total'] ? 'Không tìm thấy tài nguyên' : 'Chưa có tài nguyên' }}</h2><p>{{ $resourceStats['total'] ? 'Thử đổi từ khóa hoặc xóa bộ lọc.' : 'Tải lên audio hoặc hình ảnh để bắt đầu.' }}</p></div></td></tr>@endforelse</tbody>
                </table></div>
            </form>
            <footer class="resource-pagination">
                <nav aria-label="Phân trang tài nguyên"><a class="resource-page-button {{ $assets->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $assets->previousPageUrl() ?? '#' }}" aria-label="Trang trước" @if($assets->onFirstPage()) aria-disabled="true" tabindex="-1" @endif><i data-resource-icon="chevron-left"></i></a>@for($number = max(1, $assets->currentPage() - 2); $number <= min($assets->lastPage(), $assets->currentPage() + 2); $number++)<a class="resource-page-button {{ $number === $assets->currentPage() ? 'is-active' : '' }}" href="{{ $assets->url($number) }}" @if($number === $assets->currentPage()) aria-current="page" @endif>{{ $number }}</a>@endfor<a class="resource-page-button {{ $assets->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $assets->nextPageUrl() ?? '#' }}" aria-label="Trang sau" @unless($assets->hasMorePages()) aria-disabled="true" tabindex="-1" @endunless><i data-resource-icon="chevron-right"></i></a><span>{{ $assets->firstItem() ?? 0 }} - {{ $assets->lastItem() ?? 0 }} / {{ $assets->total() }}</span></nav>
                <label>Hiển thị <select data-resource-page-size aria-label="Số tài nguyên mỗi trang">@foreach([10, 20, 50] as $count)<option value="{{ $count }}" @selected($assets->perPage() === $count)>{{ $count }} / trang</option>@endforeach</select></label>
            </footer>
        </div>
        <aside class="resource-detail" aria-label="Chi tiết tài nguyên" data-resource-detail><header><h2>Chi tiết tài nguyên</h2><button type="button" class="resource-icon-button" data-resource-close title="Đóng chi tiết" aria-label="Đóng chi tiết"><i data-resource-icon="x"></i></button></header><div data-resource-detail-body><div class="resource-detail-empty"><i data-resource-icon="mouse-pointer-2"></i><p>Chọn tài nguyên để xem chi tiết.</p></div></div></aside>
    </div>
    @foreach($assets as $asset)<template data-resource-template="{{ $asset->id }}">@include('pages.admin.assessment.question-bank.workspace.resource-detail', ['asset' => $asset])</template>@endforeach
    @can('content.edit')
        <dialog class="resource-upload-dialog" data-resource-upload-dialog>
            <form method="post" enctype="multipart/form-data" action="{{ route('admin.media.store') }}" data-resource-upload-form>
                @csrf
                <input type="hidden" name="create_stimulus" value="1">
                <header><h2>Tải lên tài nguyên</h2><button type="button" class="resource-icon-button" data-resource-upload-close title="Đóng" aria-label="Đóng"><i data-resource-icon="x"></i></button></header>
                <label class="resource-dropzone"><i data-resource-icon="cloud-upload"></i><strong>Chọn audio hoặc hình ảnh</strong><span>MP3, WAV, M4A, OGG, JPG, PNG, WEBP</span><input type="file" name="file" accept=".mp3,.wav,.m4a,.ogg,.jpg,.jpeg,.png,.webp" multiple required data-resource-upload-file></label>
                <p class="resource-upload-progress" data-resource-upload-progress role="status" aria-live="polite"></p>
                <label>Tên tài nguyên<input name="title" maxlength="255" required data-resource-upload-title></label>
                <label>Nguồn<input name="source" maxlength="1000" placeholder="Nguồn tài nguyên"></label>
                <label data-resource-upload-transcript hidden>Bản chép lời<textarea name="transcript" rows="4" maxlength="50000"></textarea></label>
                <p class="resource-form-error" data-resource-upload-error role="alert" hidden></p>
                <footer><button type="button" class="resource-secondary" data-resource-upload-close>Hủy</button><button type="submit" class="resource-primary"><i data-resource-icon="upload"></i>Tải lên</button></footer>
            </form>
        </dialog>
    @endcan
</section>
