{{-- resources/views/partials/tasks/controls.blade.php --}}
<form class="task-controls" method="get" role="search" aria-label="Task controls">
  <input name="q" type="search" placeholder="Search by title" value="{{ request('q') }}">
  <select name="status">
    <option>Not Complete</option>
    <option>All</option>
    <option>Complete</option>
  </select>
  <select name="sort">
    <option>Due earliest</option>
    <option>Due latest</option>
    <option>Last updated</option>
    <option>Alphabetically</option>
  </select>
  <button type="button">Filters</button>
  <button type="button" data-open="task-create-modal">Create a new task</button>
</form>
