{{-- resources/views/partials/team/table.blade.php --}}
<table class="team-table" role="table" aria-label="Team">
  <thead>
    <tr>
      <th scope="col">Name</th>
      <th scope="col">Manager</th>
      <th scope="col">Role</th>
      <th scope="col">Company</th>
      <th scope="col">Email</th>
      <th scope="col">Phone</th>
    </tr>
  </thead>
  <tbody>
    @forelse(($items ?? []) as $m)
      <tr>
        <td>{{ $m['name'] ?? '' }}</td>
        <td>{{ !empty($m['manager']) ? '✓' : '—' }}</td>
        <td>{{ $m['role'] ?? '' }}</td>
        <td>{{ $m['company'] ?? '' }}</td>
        <td>{{ $m['email'] ?? '' }}</td>
        <td>{{ $m['phone'] ?? '' }}</td>
      </tr>
    @empty
      <tr><td colspan="6">@include('partials.shared.empty-state', ['title' => 'No team members'])</td></tr>
    @endforelse
  </tbody>
</table>
