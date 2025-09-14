{{-- resources/views/system/health.blade.php --}}
@extends('layouts.admin')

@section('title', 'System · Health')

@section('content')
  <header class="page-header"><h1>System Health</h1></header>
  @include('partials.shared.empty-state', ['title' => 'All good?'])
@endsection
