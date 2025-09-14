{{-- resources/views/partials/files/view-toggles.blade.php --}}
<div class="view-toggles" role="group" aria-label="View options">
  <button data-view="list" aria-pressed="{{ ($view ?? 'list') === 'list' ? 'true' : 'false' }}">List</button>
  <button data-view="grid" aria-pressed="{{ ($view ?? 'list') === 'grid' ? 'true' : 'false' }}">Grid</button>
</div>
