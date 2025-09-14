{{-- resources/views/workspace/settings.blade.php --}}
@extends('layouts.workspace')

@section('title', ($workspace['name'] ?? 'Workspace').' · Settings')

@section('workspace-content')
  <header class="page-header"><h1>Workspace Settings</h1></header>
  @include('partials.shared.empty-state', ['title' => 'No settings yet'])
@endsection
