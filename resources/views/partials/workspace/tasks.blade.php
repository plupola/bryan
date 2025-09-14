{{-- resources/views/workspace/tasks.blade.php --}}
@extends('layouts.workspace')

@section('title', ($workspace['name'] ?? 'Workspace').' · Tasks')

@section('workspace-content')
  @include('partials.tasks.summary-card', ['stats' => $stats ?? []])
  @include('partials.tasks.controls')
  @include('partials.tasks.list', ['items' => $tasks ?? []])
  @include('partials.tasks.create-modal')
@endsection
