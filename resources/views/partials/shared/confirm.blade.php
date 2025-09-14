{{-- resources/views/partials/shared/confirm.blade.php --}}
<div class="confirm" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title">
  <div class="confirm-dialog">
    <h3 id="confirm-title">{{ $title ?? 'Are you sure?' }}</h3>
    <p>{{ $message ?? 'This action cannot be undone.' }}</p>
    <div class="actions">
      <button type="button" data-confirm="no">Cancel</button>
      <button type="button" data-confirm="yes">Confirm</button>
    </div>
  </div>
</div>
