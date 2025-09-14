{{-- resources/views/partials/shared/empty-state.blade.php --}}
<section class="empty-state" role="region" aria-label="{{ $title ?? 'Empty state' }}">
  <h3>{{ $title ?? 'Nothing to show yet' }}</h3>
  <p>{{ $message ?? 'When there is content, it will appear here.' }}</p>
  {{ $cta ?? '' }}
</section>
