@php
    $examples = collect($entry['meanings'])->flatMap(fn ($meaning) => $meaning['examples'])
        ->merge(collect($entry['grammar']['rules'] ?? [])->flatMap(fn ($rule) => $rule['examples']));
    $relatedItems = collect($entry['related_items']);
    $tabItems = match ($activeTab) {
        'collocations' => $relatedItems->where('type', 'collocation'),
        'phrasal-verbs' => $relatedItems->where('type', 'phrasal_verb'),
        'synonyms' => $relatedItems->filter(fn ($item) => in_array('synonym', $item['relation_types'] ?? [], true)),
        'antonyms' => $relatedItems->filter(fn ($item) => in_array('antonym', $item['relation_types'] ?? [], true)),
        default => collect(),
    };
    $tabTitles = ['overview' => 'Tổng quan', 'examples' => 'Ví dụ', 'collocations' => 'Collocation', 'phrasal-verbs' => 'Phrasal Verb', 'synonyms' => 'Từ đồng nghĩa', 'antonyms' => 'Từ trái nghĩa', 'exercises' => 'Bài tập'];
@endphp
<article class="dictionary-content">
    <header class="dictionary-word-header">
        <div>
            <div class="dictionary-word-line">
                <h1>{{ $entry['term'] }}</h1>
                @if ($entry['phonetic'])<span class="dictionary-phonetic">{{ $entry['phonetic'] }}</span>@endif
                @if (! $entry['audio_url'] && $entry['type'] !== 'grammar')
                    <button class="dictionary-sound" type="button" aria-label="Nghe phát âm {{ $entry['term'] }}" data-pronounce="{{ $entry['term'] }}" hidden>@include('pages.dictionary.icon', ['name' => 'sound'])</button>
                    <span class="dictionary-sr-only" data-pronunciation-status role="status"></span>
                @endif
            </div>
            <div class="dictionary-tags">
                @foreach (collect($entry['meanings'])->pluck('part_of_speech_label')->filter()->unique() as $partOfSpeech)<span>{{ $partOfSpeech }}</span>@endforeach
                @if (! collect($entry['meanings'])->pluck('part_of_speech_label')->filter()->count())<span>{{ $entry['type_label'] }}</span>@endif
                @if ($entry['level'])<span class="dictionary-level">{{ $entry['level'] }}</span>@endif
            </div>
            @if ($entry['audio_url'])<audio controls preload="none" aria-label="Phát âm {{ $entry['term'] }}" src="{{ $entry['audio_url'] }}"></audio>@endif
        </div>
        @guest
            <a class="dictionary-save" href="{{ route('login') }}" title="Đăng nhập để quản lý kho kiến thức">@include('pages.dictionary.icon', ['name' => 'heart']) Lưu vào kho kiến thức</a>
        @else
            @php($entrySaved = \Illuminate\Support\Facades\DB::table('user_saved_items')->where('user_id', auth()->id())->where('learning_item_id', $entry['id'])->exists())
            <form method="post" action="{{ route('knowledge.save', $entry['slug']) }}" data-library-toggle @if($entrySaved) data-confirm="Bỏ lưu {{ $entry['term'] }}? Ghi chú cá nhân của mục này cũng sẽ bị xóa." data-confirm-label="Bỏ lưu kiến thức" @endif>
                @csrf @if($entrySaved)@method('DELETE')@endif
                <button class="dictionary-save" type="submit">@include('pages.dictionary.icon', ['name' => 'heart']) {{ $entrySaved ? 'Bỏ lưu khỏi kho kiến thức' : 'Lưu vào kho kiến thức' }}</button>
            </form>
        @endguest
    </header>
    <nav class="dictionary-tabs" aria-label="Nội dung mục từ">
        @foreach ($tabTitles as $tab => $label)
            <a class="{{ $activeTab === $tab ? 'is-active' : '' }}" href="{{ route('lookup.entry', ['slug' => $entry['slug'], 'q' => $term, 'type' => $type, 'tab' => $tab]) }}" @if ($activeTab === $tab) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @if ($activeTab === 'overview')
        <div class="dictionary-overview">
            <div class="dictionary-overview-main">
                @if ($entry['meanings'])
                    <section aria-labelledby="meanings-title">
                        <h2 id="meanings-title">Nghĩa</h2>
                        @foreach ($entry['meanings'] as $meaning)
                            <section class="dictionary-meaning">
                                <span class="dictionary-sense">{{ $meaning['sense_number'] }}</span>
                                <div>
                                    <h3>{{ $meaning['translation'] }}</h3>
                                    @if ($meaning['definition'])<p class="dictionary-translation" lang="en">{{ $meaning['definition'] }}</p>@endif
                                    @if ($meaning['usage_note'])<p class="dictionary-note">{{ $meaning['usage_note'] }}</p>@endif
                                    @foreach ($meaning['examples'] as $example)
                                        <div class="dictionary-inline-example"><p lang="en">{{ $example['english'] }}</p>@if ($example['vietnamese'])<p class="dictionary-translation">{{ $example['vietnamese'] }}</p>@endif</div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </section>
                @elseif (! $entry['grammar'])
                    <p class="dictionary-note">Mục từ này chưa có giải nghĩa chi tiết.</p>
                @endif
                @if ($entry['grammar'])
                    <section id="grammar-content" class="dictionary-grammar-content" aria-labelledby="grammar-title">
                        <h2 id="grammar-title">{{ $entry['grammar']['category'] }}</h2>
                        <p>{{ $entry['grammar']['summary'] }}</p>
                        @if ($entry['grammar']['content'])<div class="dictionary-prose">{{ $entry['grammar']['content'] }}</div>@endif
                        @foreach ($entry['grammar']['rules'] as $rule)
                            <section class="dictionary-rule"><h3>{{ $rule['title'] }}</h3>
                                @if ($rule['formula'])<pre class="dictionary-formula">{{ $rule['formula'] }}</pre>@endif
                                <p class="dictionary-prose">{{ $rule['explanation'] }}</p>
                                @foreach ($rule['examples'] as $example)
                                    <div class="dictionary-inline-example"><p lang="en">{{ $example['english'] }}</p><p class="dictionary-translation">{{ $example['vietnamese'] }}</p></div>
                                @endforeach
                            </section>
                        @endforeach
                    </section>
                @endif
                <section class="dictionary-word-types"><h2>Từ loại</h2><div>
                    <b>{{ $entry['term'] }}</b>
                    @foreach (collect($entry['meanings'])->pluck('part_of_speech_label')->filter()->unique() as $label)<span>{{ $label }}</span>@endforeach
                    @if ($entry['type'] !== 'vocabulary')<span>{{ $entry['type_label'] }}</span>@endif
                </div></section>
            </div>
            <aside class="dictionary-context">
                <img src="{{ asset('images/figma/achievement-mountain.svg') }}" alt="Đỉnh núi với lá cờ" width="280" height="160">
                <blockquote>Mỗi từ mới là một bước tiến.<br>Hiểu nghĩa, học ví dụ và dùng đúng ngữ cảnh.</blockquote>
                <h2>Nội dung mục từ</h2>
                <dl><div><dt>Nghĩa</dt><dd>{{ count($entry['meanings']) }}</dd></div><div><dt>Ví dụ</dt><dd>{{ $examples->count() }}</dd></div><div><dt>Ngữ pháp liên quan</dt><dd>{{ count($entry['related_grammar']) }}</dd></div></dl>
            </aside>
        </div>
    @elseif ($activeTab === 'examples')
        <section class="dictionary-example-section"><div class="dictionary-section-heading"><h2>Ví dụ câu</h2><span>{{ $examples->count() }} ví dụ</span></div>
            <div class="dictionary-example-filters"><span>Tất cả ({{ $examples->count() }})</span><small>Câu ví dụ và bản dịch</small></div>
            @forelse ($examples as $example)
                <article class="dictionary-example-row">
                    <div class="dictionary-example-audio"><button class="dictionary-sound" type="button" aria-label="Nghe câu {{ $example['english'] }}" data-pronounce="{{ $example['english'] }}" hidden>@include('pages.dictionary.icon', ['name' => 'sound'])</button><span class="dictionary-sr-only" data-pronunciation-status role="status"></span></div>
                    <div><p lang="en">{{ $example['english'] }}</p>@if ($example['vietnamese'])<p class="dictionary-translation">{{ $example['vietnamese'] }}</p>@endif</div>
                    @if ($entry['level'])<span class="dictionary-example-level">{{ $entry['level'] }}</span>@endif
                </article>
            @empty
                <p class="dictionary-tab-empty">Chưa có câu ví dụ cho mục từ này. Bạn có thể xem cách dùng trong các bài ngữ pháp liên quan bên dưới.</p>
            @endforelse
        </section>
    @elseif ($activeTab === 'exercises')
        <section class="dictionary-tab-empty"><h2>Luyện tập cách dùng từ</h2><p>Khám phá bài luyện theo kỹ năng để vận dụng từ vựng và ngữ pháp đã học.</p><a href="{{ route('demo.page', 'skills') }}">Đến trang luyện theo kỹ năng @include('pages.dictionary.icon', ['name' => 'arrow-right'])</a></section>
    @else
        <section><div class="dictionary-section-heading"><h2>{{ $activeTab === 'collocations' ? 'Collocation thường gặp' : $tabTitles[$activeTab] }}</h2><span>{{ $tabItems->count() }} mục liên quan</span></div>
            @if ($tabItems->isNotEmpty())
                <div class="dictionary-relation-table">
                    @foreach ($tabItems as $item)
                        <a href="{{ $item['url'] }}"><b>{{ $item['term'] }}<small>{{ $item['type_label'] }}</small></b><span>{{ $item['preview'] }}</span><span class="dictionary-chevron" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'arrow-right'])</span></a>
                    @endforeach
                </div>
            @else
                <p class="dictionary-tab-empty">Chưa có {{ mb_strtolower($tabTitles[$activeTab]) }} được liên kết với mục từ này.</p>
            @endif
        </section>
    @endif
    <div class="dictionary-related-grid">
        <section id="related-words" class="dictionary-related-card"><h2>Cụm từ liên quan</h2>
            <div class="dictionary-related-chips">
                @forelse ($entry['related_items'] as $related)<a href="{{ $related['url'] }}">{{ $related['term'] }}<span aria-hidden="true">+</span></a>@empty<p class="dictionary-translation">Chưa có từ hoặc cụm từ được liên kết.</p>@endforelse
            </div>
        </section>
        <section id="related-grammar" class="dictionary-related-card"><h2>Ngữ pháp liên quan</h2>
            @forelse ($entry['related_grammar'] as $grammar)
                <a class="dictionary-grammar-link" href="{{ $grammar['url'] }}"><span><b>{{ $grammar['term'] }}</b><small>{{ $grammar['relation_reason'] === 'linked' ? 'Liên kết với mục từ' : 'Gợi ý theo từ loại / loại nội dung' }}</small></span><span aria-hidden="true">+</span></a>
            @empty<p class="dictionary-translation">Chưa có bài ngữ pháp được liên kết với mục từ này.</p>@endforelse
            <a class="dictionary-browse-grammar" href="{{ route('lookup.index', ['type' => 'grammar']) }}">Tra cứu thêm ngữ pháp @include('pages.dictionary.icon', ['name' => 'arrow-right'])</a>
        </section>
    </div>
</article>
