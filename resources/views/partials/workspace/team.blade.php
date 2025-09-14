{{-- resources/views/workspace/team.blade.php --}}
@extends('layouts.workspace')

@section('title', ($workspace['name'] ?? 'Workspace').' · People & Groups')

@section('workspace-content')
  @include('partials.team.manager-badge', ['manager' => $manager ?? null])

  <div class="team-actions">
    @include('partials.team.actions')
  </div>

  @include('partials.team.table', ['items' => $team ?? []])
@endsection
