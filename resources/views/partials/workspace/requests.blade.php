{{-- resources/views/workspace/requests.blade.php --}}
@extends('layouts.workspace')

@section('title', ($workspace['name'] ?? 'Workspace').' · File Requests')

@section('workspace-content')
  <div class="requests-header">
    @include('partials.requests.stats-card', ['stats' => $stats ?? []])
    @include('partials.requests.filters', ['filters' => $filters ?? []])
    @include('partials.requests.cta-create')
  </div>

  <section class="requests-listing" aria-label="File requests">
    @include('partials.requests.list', ['items' => $requests ?? []])
  </section>

  @include('partials.requests.create-modal')
@endsection
