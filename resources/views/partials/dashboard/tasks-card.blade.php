{{-- resources/views/partials/dashboard/tasks-card.blade.php --}}
<article class="card tasks-card" aria-labelledby="tasks-title">
  <header><h2 id="tasks-title">My Tasks</h2></header>
  <div class="card-body">
    <ul class="tasks-list">
      @forelse(($items ?? []) as $t)
        <li>
          <a href="{{ $t['url'] ?? '#' }}">{{ $t['title'] ?? 'Task' }}</a>
          @if(!empty($t['due'])) <time datetime="{{ $t['due'] }}">{{ $t['due_human'] ?? '' }}</time>@endif
          <span class="priority {{ $t['priority'] ?? '' }}"></span>
        </li>
      @empty
        @include('partials.shared.empty-state', ['title' => 'No tasks'])
      @endforelse
    </ul>
  </div>
</article>
