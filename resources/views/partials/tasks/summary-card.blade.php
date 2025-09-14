{{-- resources/views/partials/tasks/summary-card.blade.php --}}
<article class="card task-summary" aria-labelledby="task-summary-title">
  <header><h2 id="task-summary-title">Task Summary</h2></header>
  <div class="card-body">
    <ul class="stats">
      <li>Total: {{ $stats['total'] ?? 0 }}</li>
      <li>Not started: {{ $stats['not_started'] ?? 0 }}</li>
      <li>In progress: {{ $stats['in_progress'] ?? 0 }}</li>
      <li>Complete: {{ $stats['complete'] ?? 0 }}</li>
    </ul>
  </div>
</article>
