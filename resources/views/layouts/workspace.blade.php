{{-- resources/views/layouts/workspace.blade.php --}}
@extends('layouts.app')

@section('content')
  {{-- Workspace masthead & scoped nav --}}
  @include('partials.workspace.masthead', ['workspace' => $workspace ?? null])
  @include('partials.workspace.nav', ['workspace' => $workspace ?? null])

  <section class="workspace-content">
    @yield('workspace-content')
  </section>
@endsection
