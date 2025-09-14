{{-- resources/views/partials/dropdowns/search-global.blade.php --}}
<form class="search-form" action="{{ route('search.global', []) ?? '#' }}" method="get" role="search" aria-label="Global search">
  <label for="q" class="sr-only">Search</label>
  <input id="q" name="q" type="search" placeholder="Search workspaces, files, tasks…" autocomplete="off" />
  <button type="submit" aria-label="Search">Search</button>
</form>
