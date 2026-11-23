@if ($paginator && $paginator->hasPages())
    <nav class="pagination" aria-label="Account results pages">
        @if (!$paginator->onFirstPage())
            <a href="{{ $paginator->previousPageUrl() }}">← Previous</a>
        @endif
        <span>
            Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}">Next →</a>
        @endif
    </nav>
@endif
