{{-- resources/views/partials/tasks/create-modal.blade.php --}}
@include('partials.shared.modal', [
  'id' => 'task-create-modal',
  'title' => 'Create task',
  'slot' => '
    <form class="task-create-form" method="post" action="#">
      '.csrf_field().'
      <label>Title <input name="title" type="text"></label>
      <label>Due <input name="due" type="date"></label>
      <label>Priority
        <select name="priority"><option>Low</option><option>Medium</option><option>High</option></select>
      </label>
      <label>Assign to <input name="assignee" type="text"></label>
      <button type="submit">Create</button>
    </form>
  '
])
