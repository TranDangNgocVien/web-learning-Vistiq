@if($paginator->hasPages())
<nav class="identity-page-links" aria-label="Phân trang người dùng">
    <p class="identity-page-summary">Trang {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</p>
    <div class="identity-page-numbers">
        @if($paginator->onFirstPage())
            <span class="button button-quiet" aria-disabled="true">← Trước</span>
        @else
            <a class="button button-quiet" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Trang trước">← Trước</a>
        @endif
        @foreach($elements as $element)
            @if(is_string($element))<span class="identity-page-gap" aria-hidden="true">{{ $element }}</span>@endif
            @if(is_array($element))
                @foreach($element as $page => $url)
                    @if($page === $paginator->currentPage())
                        <span class="button button-primary identity-page-number" aria-current="page" aria-label="Trang {{ $page }}">{{ $page }}</span>
                    @else
                        <a class="button button-quiet identity-page-number" href="{{ $url }}" aria-label="Trang {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if($paginator->hasMorePages())
            <a class="button button-quiet" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Trang sau">Sau →</a>
        @else
            <span class="button button-quiet" aria-disabled="true">Sau →</span>
        @endif
    </div>
</nav>
@endif
