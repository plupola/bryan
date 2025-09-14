{{-- resources/views/api/metrics.blade.php --}}
@extends('layouts.admin')

@section('title', 'Admin · API Metrics')

@section('content')
  <header class="page-header"><h1>API Metrics</h1></header>
  @include('partials.shared.empty-state', ['title' => 'No metrics yet'])
@endsection
