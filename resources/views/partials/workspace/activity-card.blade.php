{{-- resources/views/workspace/activity-card.blade.php --}}
<article class="card workspace-activity-card" aria-labelledby="wa-title">
  <header><h2 id="wa-title">Workspace Activity</h2></header>
  <div class="card-body">
    <ul class="activity-list">
      @forelse(($items ?? []) as $row)
        <li>
          <a href="{{ $row['url'] ?? '#' }}">{{ $row['text'] ?? 'Activity' }}</a>
          <time datetime="{{ $row['timestamp'] ?? '' }}">{{ $row['time_human'] ?? '' }}</time>
        </li>
      @empty
        @include('partials.shared.empty-state', ['title' => 'No workspace activity'])
      @endforelse
    </ul>
  </div>
</article>
