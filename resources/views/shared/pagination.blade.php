@php
    $currentPage = max(1, (int) ($pagination['pagina'] ?? 1));
    $totalPages = max(1, (int) ($pagination['totalPaginas'] ?? 1));
@endphp

@if ($totalPages > 1)
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-stone-500">Pagina {{ $currentPage }} de {{ $totalPages }}</p>
        <div class="flex gap-3">
            @if ($currentPage > 1)
                <a href="{{ request()->fullUrlWithQuery(['pagina' => $currentPage - 1]) }}" class="pagination-link">Anterior</a>
            @endif
            @if ($currentPage < $totalPages)
                <a href="{{ request()->fullUrlWithQuery(['pagina' => $currentPage + 1]) }}" class="pagination-link">Siguiente</a>
            @endif
        </div>
    </div>
@endif
