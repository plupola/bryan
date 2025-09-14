{{-- resources/views/partials/files/breadcrumbs.blade.php --}}
<nav class="breadcrumbs" aria-label="Breadcrumb">
  <ol>
    @foreach(($crumbs ?? []) as $c)
      <li><a href="{{ $c['url'] ?? '#' }}">{{ $c['label'] ?? '' }}</a></li>
    @endforeach
  </ol>
</nav>
