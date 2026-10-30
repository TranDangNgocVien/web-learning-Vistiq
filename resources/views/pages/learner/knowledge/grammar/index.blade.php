<form method="get" action="{{ route('grammar.index') }}" class="filter-panel" data-ajax-filter-form data-ajax-target="#grammar-results" data-ajax-on-change data-ajax-debounce="350">
    <div class="search-bar">
        <span>@include('pages.dictionary.icon', ['name' => 'search'])</span>
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm chủ điểm ngữ pháp..." aria-label="Tìm chủ điểm ngữ pháp">
    </div>

    <select name="level" aria-label="Cấp độ">
        <option value="">Tất cả cấp độ</option>
        @if(isset($levels))
            @foreach($levels as $lvl)
                <option value="{{ $lvl->code }}" @selected(($filters['level'] ?? '') === $lvl->code)>{{ $lvl->label ?? $lvl->code }}</option>
            @endforeach
        @else
            <option value="A1">A1</option>
            <option value="A2">A2</option>
            <option value="B1">B1</option>
            <option value="B2">B2</option>
            <option value="C1">C1</option>
        @endif
    </select>

    <select name="category" aria-label="Chủ đề">
        <option value="">Tất cả chủ đề</option>
        @if(isset($categories))
            @foreach($categories as $cat)
                <option value="{{ $cat->slug }}" @selected(($filters['category'] ?? '') === $cat->slug)>{{ $cat->name }}</option>
            @endforeach
        @endif
    </select>

    @if(!empty($filters['q']) || !empty($filters['level']) || !empty($filters['category']))
        <a href="{{ route('grammar.index') }}" data-ajax-filter-reset="#grammar-results" class="button button-quiet" style="font-size: 11px;">Xóa lọc</a>
    @endif
</form>

<div id="grammar-results" data-ajax-results>
<div class="grammar-grid">
    @if(isset($items) && $items->count() > 0)
        @php
            $tones = ['blue', 'violet', 'mint', 'amber', 'coral'];
            $icons = ['clock', 'repeat', 'grammar', 'book', 'grip'];
        @endphp
        @foreach($items as $index => $item)
            @php
                $tone = $tones[$index % count($tones)];
                $icon = $icons[$index % count($icons)];
            @endphp
            <a class="grammar-card" href="{{ route('grammar.show', $item->slug) }}">
                <span class="category-icon {{ $tone }}">
                    @include('pages.dictionary.icon', ['name' => $icon])
                </span>
                <div class="card-meta">
                    <span class="badge badge-blue">{{ $item->level_label ?: ($item->level_code ?: 'B1') }}</span>
                    <small>{{ $item->category_name ?? 'Ngữ pháp' }}</small>
                </div>
                <h3>{{ $item->canonical_text }}</h3>
                <p>{{ Str::limit($item->summary ?? 'Học cấu trúc và ví dụ thực tế.', 95) }}</p>
                <span class="text-link">Học chủ điểm <b>@include('pages.dictionary.icon', ['name' => 'arrow-right'])</b></span>
            </a>
        @endforeach
    @else
        @if(!empty($filters['q']) || !empty($filters['level']) || !empty($filters['category']))
            <div style="grid-column: 1 / -1; padding: 40px; text-align: center; color: #73849c; background: #fff; border-radius: 12px; border: 1px solid var(--line);">
                <p>Không tìm thấy chủ điểm ngữ pháp phù hợp với bộ lọc.</p>
                <a href="{{ route('grammar.index') }}" class="button button-quiet">Xóa bộ lọc</a>
            </div>
        @else
            @foreach ([['clock', 'Present Perfect', 'Thì hiện tại hoàn thành', 'B1', '18 phút', 'blue', 'present-perfect'], ['repeat', 'Conditional Sentences', 'Câu điều kiện loại 0–3', 'B1–B2', '22 phút', 'violet', 'conditional-sentences'], ['grammar', 'Passive Voice', 'Câu bị động trong tiếng Anh', 'B1', '16 phút', 'mint', 'passive-voice'], ['book', 'Articles', 'Mạo từ a, an, the', 'A2', '12 phút', 'amber', 'articles'], ['grip', 'Relative Clauses', 'Mệnh đề quan hệ', 'B2', '20 phút', 'coral', 'relative-clauses'], ['infinity', 'Gerund & Infinitive', 'Danh động từ và động từ nguyên mẫu', 'B1–B2', '19 phút', 'blue', 'gerund-infinitive']] as [$icon, $name, $text, $level, $duration, $tone, $slug])
                <a class="grammar-card" href="{{ route('grammar.show', $slug) }}">
                    <span class="category-icon {{ $tone }}">
                        @include('pages.dictionary.icon', ['name' => $icon === 'infinity' ? 'grip' : $icon])
                    </span>
                    <div class="card-meta">
                        <span class="badge badge-blue">{{ $level }}</span>
                        <small>{{ $duration }} đọc</small>
                    </div>
                    <h3>{{ $name }}</h3>
                    <p>{{ $text }}</p>
                    <span class="text-link">Học chủ điểm <b>@include('pages.dictionary.icon', ['name' => 'arrow-right'])</b></span>
                </a>
            @endforeach
        @endif
    @endif
</div>

@if(isset($items) && method_exists($items, 'links'))
    <div style="margin-top: 20px;" data-ajax-pagination>
        {{ $items->links() }}
    </div>
@endif
</div>
