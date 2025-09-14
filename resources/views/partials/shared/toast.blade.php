{{-- resources/views/partials/shared/toast.blade.php --}}
<div class="toast" role="status" aria-live="polite">
  <div class="toast-content">
    <strong>{{ $title ?? 'Notice' }}</strong>
    <p>{{ $message ?? '' }}</p>
  </div>
  <button class="toast-close" aria-label="Dismiss">&times;</button>
</div>
