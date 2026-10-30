<div class="dictionary-page {{ $results !== null ? 'dictionary-workspace-page' : 'dictionary-landing' }}">
    @if ($results === null)
        <section class="dictionary-hero">
            <img class="dictionary-hero-art" src="{{ asset('images/dictionary-hero.svg') }}" alt="" width="1400" height="460">
            <div class="dictionary-hero-content">
                <h1>Tra cứu từ vựng, ngữ pháp nhanh chóng</h1>
                <p>Tra nghĩa, ví dụ, phát âm, collocation, phrasal verb, ngữ pháp và nhiều hơn nữa.</p>
                @include('pages.learner.search.dictionary.search-form')
            </div>
        </section>
        <div id="dictionary-search-results" data-ajax-results>
        <section class="dictionary-discover">
            <div class="dictionary-categories">
                @foreach ([['book', 'Từ vựng', 'Nghĩa, phát âm, ví dụ, từ loại, mức độ', 'vocabulary', 'blue'], ['collocation', 'Collocation', 'Kết hợp từ thường gặp', 'collocation', 'blue'], ['link', 'Phrasal Verb', 'Cụm động từ thông dụng', 'phrasal_verb', 'green'], ['grammar', 'Ngữ pháp', 'Giải thích chi tiết, ví dụ', 'grammar', 'violet'], ['idiom', 'Thành ngữ', 'Idioms, sayings', 'idiom', 'rose']] as [$icon, $name, $text, $category, $color])
                    <a class="dictionary-category" data-ajax-filter-reset="#dictionary-search-results" href="{{ route('lookup.index', ['type' => $category]) }}">
                        <span class="dictionary-category-icon {{ $color }}">@include('pages.dictionary.icon', ['name' => $icon === '!' ? 'alert' : $icon])</span>
                        <h3>{{ $name }}</h3><p>{{ $text }}</p>
                    </a>
                @endforeach
            </div>
        </section>
        </div>
    @else
        <div class="dictionary-toolbar">@include('pages.learner.search.dictionary.search-form')</div>
        <div id="dictionary-search-results" data-ajax-results>@include('pages.learner.search.dictionary.workspace')</div>
    @endif
</div>
