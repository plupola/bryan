{{-- resources/views/partials/requests/filters.blade.php --}}
<form class="requests-filters" method="get" role="search" aria-label="Filter file requests">
  <select name="show">
    <option>All requests</option>
    <option>Not started</option>
    <option>In progress</option>
    <option>Complete</option>
  </select>

  <select name="created_by">
    <option>Everyone</option>
    <option>You</option>
  </select>

  <select name="assigned_to">
    <option>Everyone</option>
    <option>You</option>
  </select>

  <select name="order">
    <option>Last updated</option>
    <option>Title</option>
    <option>Due date</option>
    <option>Last file uploaded</option>
  </select>

  <button type="submit">Apply</button>
</form>
