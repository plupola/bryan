{{-- resources/views/partials/requests/list.blade.php --}}
<table class="requests-table" role="table" aria-label="Requests">
  <thead>
    <tr>
      <th scope="col">Request</th>
      <th scope="col">Status</th>
      <th scope="col">Due</th>
      <th scope="col">Assigned To</th>
      <th scope="col">Files Uploaded</th>
    </tr>
  </thead>
  <tbody>
    @forelse(($items ?? []) as $r)
      <tr>
        <td><a href="{{ $r['url'] ?? '#' }}">{{ $r['title'] ?? 'Request' }}</a></td>
        <td>{{ $r['status'] ?? '' }}</td>
        <td><time datetime="{{ $r['due'] ?? '' }}">{{ $r['due_human'] ?? '' }}</time></td>
        <td>{{ $r['assignee'] ?? '' }}</td>
        <td>{{ $r['files_uploaded'] ?? 0 }}</td>
      </tr>
    @empty
      <tr><td colspan="5">@include('partials.shared.empty-state', ['title' => 'No requests'])</td></tr>
    @endforelse
  </tbody>
</table>
