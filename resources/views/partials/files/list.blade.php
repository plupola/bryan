{{-- resources/views/partials/files/list.blade.php --}}
<table class="file-list" role="table" aria-label="Files list">
  <thead>
    <tr>
      <th scope="col">Name</th>
      <th scope="col">Status</th>
      <th scope="col">Version</th>
      <th scope="col">Last Modified</th>
      <th scope="col">Size</th>
      <th scope="col" class="actions">Actions</th>
    </tr>
  </thead>
  <tbody>
    @forelse(($items ?? []) as $item)
      <tr>
        <td class="name"><a href="{{ $item['url'] ?? '#' }}">{{ $item['name'] ?? 'Item' }}</a></td>
        <td class="status">{{ $item['status'] ?? '' }}</td>
        <td class="version">{{ $item['version'] ?? '' }}</td>
        <td class="modified">{{ $item['modified_by'] ?? '' }} · {{ $item['modified_at_human'] ?? '' }}</td>
        <td class="size">{{ $item['size'] ?? '' }}</td>
        <td class="actions">@include('partials.files.row-actions', ['item' => $item])</td>
      </tr>
    @empty
      <tr><td colspan="6">@include('partials.shared.empty-state', ['title' => 'No files here'])</td></tr>
    @endforelse
  </tbody>
</table>
