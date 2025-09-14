{{-- resources/views/partials/dashboard/activity-card.blade.php --}}
<article class="card activity-card" aria-labelledby="activity-title">
  <header><h2 id="activity-title">Recent Activity</h2></header>
  <div class="card-body">
    <ul class="activity-list">
      @forelse(($items ?? []) as $row)
        <li>
          <span class="icon">{{ $row['icon'] ?? '' }}</span>
          <a href="{{ $row['url'] ?? '#' }}">{{ $row['text'] ?? 'Activity' }}</a>
          <time datetime="{{ $row['timestamp'] ?? '' }}">{{ $row['time_human'] ?? '' }}</time>
        </li>
      @empty
        @include('partials.shared.empty-state', ['title' => 'No activity'])
      @endforelse
    </ul>
  </div>
</article>
