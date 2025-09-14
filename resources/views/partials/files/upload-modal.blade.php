{{-- resources/views/partials/files/upload-modal.blade.php --}}
@include('partials.shared.modal', [
  'id' => 'upload-modal',
  'title' => 'Upload files',
  'slot' => view('partials.files.upload-form')
])
