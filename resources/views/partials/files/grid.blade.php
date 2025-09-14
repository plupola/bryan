{{-- resources/views/partials/files/grid.blade.php --}}
<ul class="file-grid" role="list" aria-label="Files grid">
  @forelse(($items ?? []) as $item)
    <li class="file-tile">
      <a href="{{ $item['url'] ?? '#' }}">
        <div class="thumb" aria-hidden="true"></div>
        <div class="meta">
          <div class="name">{{ $item['name'] ?? 'Item' }}</div>
          <div class="sub">{{ $item['modified_by'] ?? '' }} · {{ $item['modified_at_human'] ?? '' }}</div>
        </div>
      </a>
      @include('partials.files.row-actions', ['item' => $item])
    </li>
  @empty
    @include('partials.shared.empty-state', ['title' => 'No files here'])
  @endforelse
</ul>
