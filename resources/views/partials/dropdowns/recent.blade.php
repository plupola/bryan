{{-- resources/views/partials/dropdowns/recent.blade.php --}}
<div class="menu" role="menu" aria-label="Recent">
  <div class="menu-header">Recent</div>
  <ul class="menu-list">
    @forelse(($items ?? []) as $item)
      <li><a href="{{ $item['url'] ?? '#' }}">{{ $item['title'] ?? 'Untitled' }}</a></li>
    @empty
      <li class="muted">No recent items</li>
    @endforelse
  </ul>
</div>
