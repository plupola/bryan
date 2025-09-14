{{-- resources/views/partials/shared/pagination.blade.php --}}
<nav class="pagination" role="navigation" aria-label="Pagination">
  {{-- Hook to Laravel paginator if passed --}}
  {{ $paginator ?? '' }}
</nav>
