@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="disabled">‹ Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Previous</a>
        @endif

        @if (method_exists($paginator, 'lastPage'))
            <span class="disabled">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} · {{ number_format($paginator->total()) }} records</span>
        @else
            <span class="disabled">Page {{ $paginator->currentPage() }}</span>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a>
        @else
            <span class="disabled">Next ›</span>
        @endif
    </nav>
@endif
