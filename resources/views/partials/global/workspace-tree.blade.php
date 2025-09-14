{{-- resources/views/partials/global/workspace-tree.blade.php --}}
<ul class="tree">
  @forelse(($nodes ?? []) as $node)
    <li class="{{ request()->is('workspaces/'.$node['id'].'*') ? 'active' : '' }}">
      <a href="{{ route('workspaces.overview', $node['id']) }}">{{ $node['name'] }}</a>
      @if(!empty($node['children']))
        @include('partials.global.workspace-tree', ['nodes' => $node['children']])
      @endif
    </li>
  @empty
    <li class="muted">No workspaces yet.</li>
  @endforelse
</ul>
