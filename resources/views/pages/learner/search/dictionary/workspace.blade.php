<div class="dictionary-workspace">
    <aside class="dictionary-rail" aria-labelledby="dictionary-results-title">
        <h2 id="dictionary-results-title">Kết quả tìm kiếm</h2>
        <p class="dictionary-result-count">{{ $term === '' && $type === 'grammar' ? $results->total().' bài ngữ pháp' : $results->total().' kết quả' }}</p>
        <nav aria-label="Danh sách kết quả">
            @foreach ($results as $result)
                <a class="dictionary-rail-item {{ ($entry['id'] ?? null) === $result->id ? 'is-current' : '' }}" href="{{ route('lookup.entry', ['slug' => $result->slug, 'q' => $term, 'type' => $type, 'tab' => $activeTab]) }}" @if (($entry['id'] ?? null) === $result->id) aria-current="page" @endif>
                    <span><b>{{ $result->canonical_text }}</b><small>{{ $result->type_label }}</small></span><span class="dictionary-chevron" aria-hidden="true">@include('pages.dictionary.icon', ['name' => 'arrow-right'])</span>
                </a>
            @endforeach
        </nav>
        @if ($results->hasPages())
            <nav class="dictionary-rail-pagination" aria-label="Trang kết quả" data-ajax-pagination>
                @if ($results->previousPageUrl())<a href="{{ $results->previousPageUrl() }}" aria-label="Trang trước">@include('pages.dictionary.icon', ['name' => 'arrow-left'])</a>@endif
                <span>{{ $results->currentPage() }} / {{ $results->lastPage() }}</span>
                @if ($results->nextPageUrl())<a href="{{ $results->nextPageUrl() }}" aria-label="Trang tiếp theo">@include('pages.dictionary.icon', ['name' => 'arrow-right'])</a>@endif
            </nav>
        @endif
    </aside>
    @if ($entry)
        @include('pages.learner.search.dictionary.content')
    @else
        <section class="dictionary-empty">
            <span class="dictionary-empty-icon">@include('pages.dictionary.icon', ['name' => 'search'])</span>
            <h1>Chưa tìm thấy nội dung phù hợp</h1>
            <p>Hãy kiểm tra chính tả, dùng từ gốc hoặc chọn “Tất cả” để mở rộng tìm kiếm.</p>
            <a href="{{ route('lookup.index') }}">Quay lại trang tra cứu @include('pages.dictionary.icon', ['name' => 'arrow-right'])</a>
        </section>
    @endif
</div>
