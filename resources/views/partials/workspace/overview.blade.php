{{-- resources/views/workspace/overview.blade.php --}}
@extends('layouts.workspace')

@section('title', ($workspace['name'] ?? 'Workspace').' · Overview')

@section('workspace-content')
  <section class="cards-grid">
    @include('workspace.pinned-items-card', ['items' => $pinned ?? []])
    @include('workspace.activity-card', ['items' => $activity ?? []])
    @includeIf('partials.workspace.summary-tabs')
  </section>
@endsection
