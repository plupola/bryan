{{-- resources/views/partials/workspace/search-scoped.blade.php --}}
<form class="workspace-search" action="{{ route('workspaces.files', $workspace['id'] ?? 0) }}" method="get" role="search" aria-label="Workspace search">
  <label for="wq" class="sr-only">Search within workspace</label>
  <input id="wq" name="q" type="search" placeholder="Search in this workspace…" />
  <button type="submit">Search</button>
</form>
