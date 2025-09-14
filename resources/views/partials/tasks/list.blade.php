{{-- resources/views/partials/tasks/list.blade.php --}}
<table class="tasks-table" role="table" aria-label="Tasks">
  <thead>
    <tr>
      <th scope="col">Task</th>
      <th scope="col">Due</th>
      <th scope="col">Priority</th>
      <th scope="col">Assignee</th>
      <th scope="col">Status</th>
      <th scope="col">File</th>
    </tr>
  </thead>
  <tbody>
    @forelse(($items ?? []) as $t)
      <tr>
        <td><a href="{{ $t['url'] ?? '#' }}">{{ $t['title'] ?? 'Task' }}</a></td>
        <td><time datetime="{{ $t['due'] ?? '' }}">{{ $t['due_human'] ?? '' }}</time></td>
        <td>{{ $t['priority'] ?? '' }}</td>
        <td>{{ $t['assignee'] ?? '' }}</td>
        <td>{{ $t['status'] ?? '' }}</td>
        <td>{{ $t['file'] ?? '' }}</td>
      </tr>
    @empty
      <tr><td colspan="6">@include('partials.shared.empty-state', ['title' => 'No tasks'])</td></tr>
    @endforelse
  </tbody>
</table>
