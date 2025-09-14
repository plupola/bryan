{{-- resources/views/partials/requests/stats-card.blade.php --}}
<article class="card requests-stats" aria-labelledby="req-stats-title">
  <header><h2 id="req-stats-title">Request Stats</h2></header>
  <div class="card-body">
    <ul class="stats">
      <li>All: {{ $stats['all'] ?? 0 }}</li>
      <li>Not started: {{ $stats['not_started'] ?? 0 }}</li>
      <li>In progress: {{ $stats['in_progress'] ?? 0 }}</li>
      <li>Complete: {{ $stats['complete'] ?? 0 }}</li>
    </ul>
  </div>
</article>
