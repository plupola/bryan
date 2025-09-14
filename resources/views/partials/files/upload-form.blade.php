{{-- resources/views/partials/files/upload-form.blade.php --}}
<form class="upload-form" method="post" enctype="multipart/form-data" action="{{ route('files.index') }}">
  @csrf
  <input type="file" name="files[]" multiple>
  <button type="submit">Upload</button>
</form>
