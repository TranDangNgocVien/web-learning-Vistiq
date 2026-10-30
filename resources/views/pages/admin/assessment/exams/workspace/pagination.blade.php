@if($paginator->hasPages())
<nav class="ex-page-links" aria-label="Phân trang đề thi">
    @if($paginator->onFirstPage())<span aria-disabled="true">‹</span>@else<a href="{{ $paginator->previousPageUrl() }}" aria-label="Trang trước">‹</a>@endif
    @foreach($elements as $element)
        @if(is_string($element))<span>{{ $element }}</span>@endif
        @if(is_array($element))@foreach($element as $page=>$url)@if($page === $paginator->currentPage())<span class="is-active" aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif @endforeach @endif
    @endforeach
    @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" aria-label="Trang sau">›</a>@else<span aria-disabled="true">›</span>@endif
</nav>
@endif
