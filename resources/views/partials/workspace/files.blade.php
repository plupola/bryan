{{-- resources/views/workspace/files.blade.php --}}
@extends('layouts.workspace')

@section('title', ($workspace['name'] ?? 'Workspace').' · Files')

@section('workspace-content')
  @include('partials.files.toolbar', ['workspace' => $workspace ?? null])
  @include('partials.files.breadcrumbs', ['crumbs' => $breadcrumbs ?? []])

  <div class="files-controls">
    @include('partials.files.view-toggles')
    @include('partials.files.search-local')
  </div>

  <section class="files-region" aria-label="Files">
    {{-- Toggle your list vs grid based on a view mode variable --}}
    @if(($view ?? 'list') === 'grid')
      @include('partials.files.grid', ['items' => $items ?? []])
    @else
      @include('partials.files.list', ['items' => $items ?? []])
    @endif
  </section>

  @include('partials.files.upload-modal')
@endsection
