<footer class="qb-pagination">
    <nav aria-label="{{ $label ?? 'Phân trang' }}" class="qb-page-links" data-ajax-pagination>
        @if($paginator->onFirstPage())<span class="is-disabled" aria-disabled="true">‹</span>@else<a href="{{ $paginator->previousPageUrl() }}" aria-label="Trang trước">‹</a>@endif
        @php($lastShownPage = 0)
        @for($pageNumber = 1; $pageNumber <= $paginator->lastPage(); $pageNumber++)
            @if($pageNumber === 1 || $pageNumber === $paginator->lastPage() || abs($pageNumber - $paginator->currentPage()) <= 1)
                @if($lastShownPage && $pageNumber > $lastShownPage + 1)<span class="qb-page-gap" aria-hidden="true">…</span>@endif
                <a href="{{ $paginator->url($pageNumber) }}" class="{{ $pageNumber === $paginator->currentPage() ? 'is-active' : '' }}" @if($pageNumber === $paginator->currentPage())aria-current="page" @endif>{{ $pageNumber }}</a>
                @php($lastShownPage = $pageNumber)
            @endif
        @endfor
        @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" aria-label="Trang sau">›</a>@else<span class="is-disabled" aria-disabled="true">›</span>@endif
    </nav>
    <label>Hiển thị <select name="per_page" form="{{ $formId }}" aria-label="Số mục mỗi trang">
        @foreach([10, 20, 50] as $pageSize)<option value="{{ $pageSize }}" @selected($paginator->perPage() === $pageSize)>{{ $pageSize }} / trang</option>@endforeach
    </select></label>
</footer>
