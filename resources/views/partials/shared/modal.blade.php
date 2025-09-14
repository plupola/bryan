{{-- resources/views/partials/shared/modal.blade.php --}}
<div class="modal" role="dialog" aria-modal="true" aria-labelledby="{{ $id ?? 'modal-title' }}">
  <div class="modal-backdrop" data-dismiss="modal"></div>
  <div class="modal-dialog">
    <header class="modal-header">
      <h2 id="{{ $id ?? 'modal-title' }}">{{ $title ?? 'Modal Title' }}</h2>
      <button class="close" aria-label="Close" data-dismiss="modal">&times;</button>
    </header>
    <section class="modal-body">
      {{ $slot ?? 'Content goes here' }}
    </section>
    <footer class="modal-footer">
      {{ $footer ?? '' }}
    </footer>
  </div>
</div>
