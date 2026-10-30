<section class="section-block">
    <div class="section-head compact"><h1>{{ $savedView ? 'Mục đã lưu' : 'Kho kiến thức' }}</h1><a class="button button-quiet" href="{{ route($savedView ? 'knowledge.index' : 'knowledge.saved') }}">{{ $savedView ? 'Khám phá kiến thức' : 'Mục đã lưu' }}</a></div>
    <form method="get" class="filter-panel" data-ajax-filter-form data-ajax-target="#saved-knowledge-results" data-ajax-on-change data-ajax-debounce="350">
        <label>Tìm kiến thức<input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Từ hoặc tiêu đề"></label>
        <label>Loại<select name="type"><option value="all">Tất cả loại</option>@foreach($types as $type)<option value="{{ $type->code }}" @selected(($filters['type'] ?? '') === $type->code)>{{ $type->label }}</option>@endforeach</select></label>
        <label>Cấp độ<select name="level"><option value="">Tất cả cấp độ</option>@foreach($levels as $level)<option value="{{ $level->code }}" @selected(($filters['level'] ?? '') === $level->code)>{{ $level->label }}</option>@endforeach</select></label>
        <button class="button button-primary" type="submit">Lọc kiến thức</button><a class="text-link" data-ajax-filter-reset="#saved-knowledge-results" href="{{ route($savedView ? 'knowledge.saved' : 'knowledge.index') }}">Xóa bộ lọc</a>
    </form>
    <div id="saved-knowledge-results" data-ajax-results>
    <p role="status">{{ $items->total() }} mục {{ $savedView ? 'đã lưu' : 'được công bố' }}</p>
    <div class="knowledge-category-grid">
        @forelse($items as $item)<article class="panel"><span class="badge badge-soft">{{ $item->type_label }}{{ $item->level_label ? ' · '.$item->level_label : '' }}</span><h2><a href="{{ route($item->type_code === 'grammar' ? 'grammar.show' : 'knowledge.show', $item->slug) }}">{{ $item->canonical_text }}</a></h2>@if($savedView && $item->personal_note)<p>{{ $item->personal_note }}</p>@endif<a class="text-link" href="{{ route($item->type_code === 'grammar' ? 'grammar.show' : 'knowledge.show', $item->slug) }}">Xem nội dung</a></article>
        @empty<div class="panel"><h2>{{ $savedView ? 'Chưa có mục đã lưu phù hợp' : 'Chưa có kiến thức phù hợp' }}</h2><p>{{ $savedView ? 'Mở một mục kiến thức và chọn Lưu kiến thức để thêm vào danh sách.' : 'Thử từ khóa hoặc bộ lọc khác.' }}</p></div>@endforelse
    </div>
    <div data-ajax-pagination>{{ $items->links() }}</div>
    </div>
</section>
