{{-- resources/views/partials/requests/create-modal.blade.php --}}
@include('partials.shared.modal', [
  'id' => 'request-create-modal',
  'title' => 'Create file request',
  'slot' => '
    <form class="request-create-form" method="post" action="#">
      '.csrf_field().'
      <label>Title <input name="title" type="text"></label>
      <label>Due date <input name="due" type="date"></label>
      <label>Assign to <input name="assignee" type="text"></label>
      <button type="submit">Create</button>
    </form>
  '
])
