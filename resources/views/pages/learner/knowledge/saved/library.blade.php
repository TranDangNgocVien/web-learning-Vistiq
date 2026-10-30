@php
    $labels = ['all'=>'Tất cả', 'vocabulary'=>'Từ vựng', 'grammar'=>'Ngữ pháp', 'collocation'=>'Collocation', 'phrasal_verb'=>'Phrasal Verb', 'idiom'=>'Thành ngữ'];
    $total = $counts->sum();
    $activeType = $filters['type'] ?? 'all';
    $selectedSlug = $selected['summary']->slug ?? null;
@endphp
<div class="personal-library" data-knowledge-library>
    <div class="personal-library-main">
        <header class="library-heading"><h1>Kho kiến thức</h1><p>Nơi lưu trữ các mục yêu thích về từ vựng, ngữ pháp, collocation và phrasal verb.</p></header>
        <section id="personal-library-metrics" class="library-metrics" aria-label="Thống kê mục đã lưu" data-ajax-results>
            @foreach([['Tổng mục đã lưu',$total,'saved','blue'],['Từ vựng',$counts['vocabulary'] ?? 0,'book','green'],['Ngữ pháp',$counts['grammar'] ?? 0,'grammar','violet'],['Collocation / Phrasal Verb',($counts['collocation'] ?? 0)+($counts['phrasal_verb'] ?? 0),'link','orange']] as [$label,$value,$icon,$color])
            <article class="library-metric"><span class="library-metric-icon is-{{ $color }}">@include('pages.dictionary.icon',['name'=>$icon])</span><div><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong></div></article>
            @endforeach
        </section>
        <section class="library-filters">
            <form method="get" action="{{ route('knowledge.index') }}" class="library-search" data-ajax-filter-form data-ajax-target="#personal-library-metrics, #personal-library-tabs, #personal-library-reset, #personal-library-items, #personal-library-aside" data-ajax-on-change data-ajax-debounce="350">
                <label class="library-search-input">@include('pages.dictionary.icon',['name'=>'search'])<span class="visually-hidden">Tìm trong các mục đã lưu</span><input type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm kiếm trong các mục đã lưu…"></label>
                <input type="hidden" name="type" value="{{ $activeType }}">
                @if($filters['collection'] ?? null)<input type="hidden" name="collection" value="{{ $filters['collection'] }}">@endif
                <label class="library-sort">Sắp xếp theo<select name="sort"><option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Mới lưu nhất</option><option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Cũ nhất</option><option value="alphabetical" @selected(($filters['sort'] ?? '') === 'alphabetical')>Tên A–Z</option></select></label>
                <button class="button button-quiet" type="submit">Tìm kiếm</button>
            </form>
            <nav id="personal-library-tabs" class="library-tabs" aria-label="Loại kiến thức đã lưu">@foreach($labels as $code=>$label)<a data-ajax-filter-reset="#personal-library-metrics, #personal-library-tabs, #personal-library-reset, #personal-library-items, #personal-library-aside" href="{{ route('knowledge.index', [...request()->except('page','item'), 'type'=>$code]) }}" @if($activeType===$code) aria-current="page" @endif>{{ $label }} ({{ number_format($code==='all' ? $total : ($counts[$code] ?? 0)) }})</a>@endforeach</nav>
            <div id="personal-library-reset" data-ajax-results>@if(($filters['q'] ?? '') || ($filters['collection'] ?? null))<a class="text-link" data-ajax-filter-reset="#personal-library-metrics, #personal-library-tabs, #personal-library-reset, #personal-library-items, #personal-library-aside" href="{{ route('knowledge.index') }}">Xóa bộ lọc</a>@endif</div>
        </section>
        <div id="personal-library-items" data-ajax-results>
        <section class="library-cards" aria-label="Các mục đã lưu">
            @forelse($items as $item)
            <article class="library-card {{ $selectedSlug===$item->slug ? 'is-selected' : '' }}">
                <div class="library-card-top"><span class="library-type is-{{ $item->type_code }}">{{ $labels[$item->type_code] ?? $item->type_label }}</span>
                    <form method="post" action="{{ route('knowledge.unsave',$item->slug) }}" data-confirm="Bỏ lưu {{ $item->canonical_text }}? Ghi chú cá nhân của mục này cũng sẽ bị xóa." data-confirm-label="Bỏ lưu kiến thức" data-pending-label="Đang bỏ lưu…">@csrf @method('DELETE')<button class="library-heart" type="submit" aria-label="Bỏ lưu {{ $item->canonical_text }}" title="Bỏ lưu kiến thức">@include('pages.dictionary.icon',['name'=>'heart'])</button></form>
                </div>
                <h2><a data-library-select href="{{ route('knowledge.index',[...request()->except('item','detail'), 'item'=>$item->slug]) }}#knowledge-detail">{{ $item->canonical_text }}</a></h2>
                @if($item->ipa)<p class="library-ipa">{{ $item->ipa }}</p>@endif
                @if(is_string($item->audio_url) && preg_match('#^https://[^\s]+$#i',$item->audio_url))<button class="library-pronounce" type="button" data-library-audio="{{ $item->audio_url }}" aria-label="Nghe phát âm {{ $item->canonical_text }}" title="Nghe phát âm">@include('pages.dictionary.icon',['name'=>'sound'])</button>@endif
                @if($item->meaning)<p class="library-meaning">{{ $item->meaning }}</p>@endif
                @if($item->example)<blockquote lang="en">{{ $item->example }}</blockquote>@endif
                @if($item->personal_note)<p class="library-note">{{ $item->personal_note }}</p>@endif
                <div class="library-tags">@foreach(($tags[$item->id] ?? collect())->take(3) as $tag)<span>{{ $tag->name }}</span>@endforeach @if($item->level_code)<span>{{ $item->level_code }}</span>@endif</div>
                @unless($item->meaning || $item->example)<a class="text-link" data-library-select href="{{ route('knowledge.index',[...request()->except('item','detail'), 'item'=>$item->slug]) }}#knowledge-detail">Xem nội dung →</a>@endunless
            </article>
            @empty
            <div class="library-empty"><span class="library-metric-icon is-blue">@include('pages.dictionary.icon',['name'=>'saved'])</span><h2>{{ $total ? 'Chưa có mục phù hợp' : 'Kho kiến thức của bạn đang trống' }}</h2><p>{{ $total ? 'Thử từ khóa khác hoặc xóa bộ lọc.' : 'Tra cứu từ vựng, ngữ pháp hoặc cụm từ, rồi chọn “Lưu vào kho kiến thức”.' }}</p><a class="button button-primary" href="{{ route('lookup.index') }}">Tra cứu kiến thức</a></div>
            @endforelse
        </section>
        <div class="library-pagination" data-ajax-pagination>{{ $items->links() }}</div>
        </div>
    </div>
    <aside id="personal-library-aside" class="library-sidebar" data-ajax-results>
        @if($selected)
        @php($detail = $selected['payload'])
        <section class="library-detail" id="knowledge-detail" tabindex="-1" aria-label="Chi tiết mục đã lưu">
            <header><h2>Chi tiết mục</h2><a href="{{ route('knowledge.index',[...request()->except('item'), 'detail'=>'hidden']) }}" aria-label="Đóng chi tiết mục" title="Đóng chi tiết">×</a></header>
            <span class="library-type is-{{ $selected['summary']->type_code }}">{{ $labels[$selected['summary']->type_code] ?? $selected['summary']->type_label }}</span>
            <h3>{{ $selected['summary']->canonical_text }}</h3>
            @if($detail['ipa'] ?? null)<p class="library-ipa">{{ $detail['ipa'] }}</p>@endif
            @if(is_string($selected['summary']->audio_url) && preg_match('#^https://[^\s]+$#i',$selected['summary']->audio_url))<audio controls preload="none" src="{{ $selected['summary']->audio_url }}" aria-label="Nghe phát âm {{ $selected['summary']->canonical_text }}"></audio>@endif
            <a class="library-lookup-link" href="{{ route('lookup.entry', $selected['summary']->slug) }}">Mở trong Tra cứu →</a>
            @if($detail['meaning_vi'] ?? null)<h4>Nghĩa</h4><p>{{ $detail['meaning_vi'] }}</p>@endif
            @if(($detail['definition_en'] ?? null) || ($detail['summary'] ?? null))<h4>Giải thích</h4><p>{{ $detail['definition_en'] ?? $detail['summary'] }}</p>@endif
            @if($detail['content'] ?? null)<div class="library-prose">{!! $detail['content'] !!}</div>@endif
            @foreach($detail['grammar_rules'] ?? [] as $rule)<h4>{{ $rule['title'] }}</h4><p>{{ $rule['formula'] ?? '' }}</p><div class="library-prose">{!! $rule['explanation'] ?? '' !!}</div>@endforeach
            @if($detail['examples'] ?? [])<h4>Ví dụ</h4><ol class="library-examples">@foreach($detail['examples'] as $example)<li><p lang="en">{{ $example['english'] }}</p><p class="library-translation">{{ $example['vietnamese'] ?? '' }}</p></li>@endforeach</ol>@endif
            @if($detail['tags'] ?? null)<h4>Thẻ</h4><div class="library-tags">@foreach(explode(',',$detail['tags']) as $tag)<span>{{ trim($tag) }}</span>@endforeach</div>@endif
            <form method="post" action="{{ route('knowledge.save', $selected['summary']->slug) }}" data-pending-label="Đang lưu…" class="library-note-form">@csrf
                <label for="library-collection">Lưu trong bộ sưu tập<select id="library-collection" name="collection_id"><option value="">Chưa phân loại</option>@foreach($collections as $collection)<option value="{{ $collection->id }}" @selected(old('collection_id',$selected['saved']->collection_id)==$collection->id)>{{ $collection->name }}</option>@endforeach</select></label>
                @error('collection_id')<small role="alert">{{ $message }}</small>@enderror
                <label for="library-note">Ghi chú riêng <small>(tối đa 5.000 ký tự)</small><textarea id="library-note" name="personal_note" maxlength="5000" rows="2">{{ old('personal_note',$selected['saved']->personal_note) }}</textarea></label>
                @error('personal_note')<small role="alert">{{ $message }}</small>@enderror
                <button type="submit" class="button button-primary">@include('pages.dictionary.icon',['name'=>'save']) Lưu thay đổi</button>
            </form>
        </section>
        @endif
        <section class="library-collections"><header><h2>Bộ sưu tập của tôi</h2><a class="text-link" href="{{ route('knowledge.index') }}">Tất cả →</a></header>
            <nav aria-label="Bộ sưu tập">@forelse($collections as $collection)<a data-ajax-filter-reset="#personal-library-metrics, #personal-library-tabs, #personal-library-reset, #personal-library-items, #personal-library-aside" href="{{ route('knowledge.index',['collection'=>$collection->id]) }}" @if(($filters['collection'] ?? null)==$collection->id) aria-current="page" @endif>@include('pages.dictionary.icon',['name'=>'collection'])<span>{{ $collection->name }}</span><small>{{ $collection->total }} mục</small></a>@empty<p>Chưa có bộ sưu tập.</p>@endforelse</nav>
            <details @if($errors->has('name')) open @endif><summary>Tạo bộ sưu tập</summary><form method="post" action="{{ route('knowledge.collections.store') }}" data-pending-label="Đang tạo…">@csrf<label for="collection-name">Tên bộ sưu tập * (tối đa 80 ký tự)<input id="collection-name" name="name" value="{{ old('name') }}" maxlength="80" required placeholder="Ví dụ: Từ vựng công việc"></label>@error('name')<small role="alert">{{ $message }}</small>@enderror<button class="button button-quiet" type="submit">Tạo bộ sưu tập</button></form></details>
        </section>
    </aside>
</div>
