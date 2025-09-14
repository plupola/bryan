{{-- resources/views/partials/files/search-local.blade.php --}}
<form class="local-search" method="get" role="search" aria-label="Search in folder">
  <label for="folder-q" class="sr-only">Search</label>
  <input id="folder-q" name="q" type="search" placeholder="Search in this folder…" value="{{ request('q') }}">
  <button type="submit">Search</button>
</form>
