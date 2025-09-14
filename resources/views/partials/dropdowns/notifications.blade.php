{{-- resources/views/partials/dropdowns/notifications.blade.php --}}
<div class="menu" role="menu" aria-label="Notifications">
  <div class="menu-header">Notifications</div>
  <ul class="menu-list">
    @forelse(($items ?? []) as $n)
      <li>
        <a href="{{ $n['url'] ?? '#' }}">
          <span class="n-type">{{ $n['type'] ?? '' }}</span>
          <span class="n-text">{{ $n['text'] ?? 'Notification' }}</span>
          <time datetime="{{ $n['timestamp'] ?? '' }}">{{ $n['time_human'] ?? '' }}</time>
        </a>
      </li>
    @empty
      <li class="muted">You're all caught up</li>
    @endforelse
  </ul>
</div>
