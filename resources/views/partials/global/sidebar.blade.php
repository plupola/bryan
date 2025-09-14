{{-- resources/views/partials/global/sidebar.blade.php --}}
<nav class="global-sidebar" aria-label="Workspaces">
  <button class="btn-add-workspace" type="button" aria-label="Add Workspace">+ Add Workspace</button>

  {{-- Optional: Workspace scoped search can live here too --}}

  {{-- Workspace tree --}}
  <div class="workspace-tree">
    @include('partials.global.workspace-tree', ['nodes' => $workspaces ?? []])
  </div>

  <div class="sidebar-footer">
    <a href="{{ route('workspaces.index') }}?archived=1" class="link-archived">Archived</a>
    @can('access-admin')
      <a href="{{ route('admin.dashboard') }}" class="link-admin">Admin Panel</a>
    @endcan
  </div>
</nav>
