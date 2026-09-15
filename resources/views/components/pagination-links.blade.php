@if ($paginator->hasPages())
    <nav aria-label="Phân trang" class="flex w-full items-center justify-between gap-1.5 sm:w-auto">
        @if ($paginator->onFirstPage())
            <span class="pagination-link" aria-disabled="true"><x-icon name="chevron-left" class="size-3" /><span class="sm:sr-only">Trước</span></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Trang trước" class="pagination-link"><x-icon name="chevron-left" class="size-3" /><span class="sm:sr-only">Trước</span></a>
        @endif

        <span class="px-2 text-sm text-gray-600 sm:hidden">Trang {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        <div class="hidden items-center gap-1.5 sm:flex">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1 text-gray-400" aria-hidden="true">…</span>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span aria-current="page" aria-label="Trang {{ $page }}" class="pagination-link">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Trang {{ $page }}" class="pagination-link">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Trang sau" class="pagination-link"><span class="sm:sr-only">Sau</span><x-icon name="chevron-right" class="size-3" /></a>
        @else
            <span class="pagination-link" aria-disabled="true"><span class="sm:sr-only">Sau</span><x-icon name="chevron-right" class="size-3" /></span>
        @endif
    </nav>
@endif
