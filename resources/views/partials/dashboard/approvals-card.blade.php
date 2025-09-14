{{-- resources/views/partials/dashboard/approvals-card.blade.php --}}
<article class="card approvals-card" aria-labelledby="approvals-title">
  <header><h2 id="approvals-title">Files Awaiting Approval</h2></header>
  <div class="card-body">
    <ul class="approvals-list">
      @forelse(($items ?? []) as $f)
        <li>
          <a href="{{ $f['url'] ?? '#' }}">{{ $f['file'] ?? 'File' }}</a>
          <span class="version">{{ $f['version'] ?? '' }}</span>
          <span class="who">{{ $f['who'] ?? '' }}</span>
          @if(!empty($f['due'])) <time datetime="{{ $f['due'] }}">{{ $f['due_human'] ?? '' }}</time>@endif
        </li>
      @empty
        @include('partials.shared.empty-state', ['title' => 'No approvals required'])
      @endforelse
    </ul>
  </div>
</article>
