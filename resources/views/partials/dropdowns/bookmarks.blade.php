{{-- resources/views/partials/dropdowns/bookmarks.blade.php --}}
<div class="menu" role="menu" aria-label="Bookmarks">
  <div class="menu-header">Bookmarks</div>
  <ul class="menu-list">
    @forelse(($items ?? []) as $item)
      <li><a href="{{ $item['url'] ?? '#' }}">{{ $item['title'] ?? 'Untitled' }}</a></li>
    @empty
      <li class="muted">No bookmarks yet</li>
    @endforelse
  </ul>
</div>
