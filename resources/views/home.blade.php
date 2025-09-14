{{-- resources/views/home.blade.php --}}
@extends('layouts.app')

@section('title', 'Home')

@section('content')
  <header class="page-header">
    <h1>Home</h1>
  </header>
  <div>
    @include('partials.global.header', ['items' => $header ?? []])
  </div>
  <div>
    @include('partials.global.sidebar', ['items' => $sidebar ?? []])
  </div>
  <section class="cards-grid">
    @include('partials.dashboard.activity-card', ['items' => $activity ?? []])
    @include('partials.dashboard.tasks-card', ['items' => $tasks ?? []])
    @include('partials.dashboard.approvals-card', ['items' => $approvals ?? []])
    @include('partials.dashboard.locked-files-card', ['items' => $locked ?? []])
  </section>
@endsection
