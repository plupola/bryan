{{-- resources/views/partials/workspace/nav.blade.php --}}
<nav class="workspace-nav" aria-label="Workspace sections">
  <ul>
    <li class="{{ request()->routeIs('workspaces.overview') ? 'active' : '' }}">
      <a href="{{ isset($workspace['id']) ? route('workspaces.overview', $workspace['id']) : '#' }}">Overview</a>
    </li>
    <li class="{{ request()->routeIs('workspaces.files') ? 'active' : '' }}">
      <a href="{{ isset($workspace['id']) ? route('workspaces.files', $workspace['id']) : '#' }}">Files</a>
    </li>
    <li class="{{ request()->routeIs('workspaces.requests') ? 'active' : '' }}">
      <a href="{{ isset($workspace['id']) ? route('workspaces.requests', $workspace['id']) : '#' }}">File Requests</a>
    </li>
    <li class="{{ request()->routeIs('workspaces.tasks') ? 'active' : '' }}">
      <a href="{{ isset($workspace['id']) ? route('workspaces.tasks', $workspace['id']) : '#' }}">Tasks</a>
    </li>
    <li class="{{ request()->routeIs('workspaces.team') ? 'active' : '' }}">
      <a href="{{ isset($workspace['id']) ? route('workspaces.team', $workspace['id']) : '#' }}">People & Groups</a>
    </li>
    <li class="{{ request()->routeIs('workspaces.settings') ? 'active' : '' }}">
      <a href="{{ isset($workspace['id']) ? route('workspaces.settings', $workspace['id']) : '#' }}">Settings</a>
    </li>
  </ul>
</nav>
