{{-- resources/views/workspace/pinned-items-card.blade.php --}}
<article class="card pinned-card" aria-labelledby="pinned-title">
  <header><h2 id="pinned-title">Pinned</h2></header>
  <div class="card-body">
    <ul class="pinned-items">
      @forelse(($items ?? []) as $i)
        <li><a href="{{ $i['url'] ?? '#' }}">{{ $i['title'] ?? 'Item' }}</a></li>
      @empty
        @include('partials.shared.empty-state', ['title' => 'No pinned items'])
      @endforelse
    </ul>
  </div>
</article>
