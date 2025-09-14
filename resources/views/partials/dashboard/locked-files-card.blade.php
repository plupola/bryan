{{-- resources/views/partials/dashboard/locked-files-card.blade.php --}}
<article class="card locked-files-card" aria-labelledby="locked-title">
  <header><h2 id="locked-title">Locked Files</h2></header>
  <div class="card-body">
    <ul class="locked-list">
      @forelse(($items ?? []) as $f)
        <li>
          <a href="{{ $f['url'] ?? '#' }}">{{ $f['file'] ?? 'File' }}</a>
          <span class="locked-by">{{ $f['locked_by'] ?? '' }}</span>
          <time datetime="{{ $f['locked_at'] ?? '' }}">{{ $f['locked_at_human'] ?? '' }}</time>
          @if(!empty($f['can_force_unlock']))
            <form method="post" action="{{ $f['force_unlock_url'] ?? '#' }}">
              @csrf <button type="submit">Force Unlock</button>
            </form>
          @endif
        </li>
      @empty
        @include('partials.shared.empty-state', ['title' => 'No locked files'])
      @endforelse
    </ul>
  </div>
</article>
