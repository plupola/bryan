{{-- resources/views/partials/workspace/masthead.blade.php --}}
<header class="workspace-masthead" role="region" aria-label="Workspace">
  <div class="workspace-title">
    <h1>{{ $workspace['name'] ?? 'Workspace' }}</h1>
    {{-- Optional metadata: manager, description, quick actions --}}
  </div>
  <div class="workspace-actions">
    @includeIf('partials.workspace.search-scoped')
  </div>
</header>
