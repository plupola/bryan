{{-- resources/views/admin/dashboard.blade.php --}}
@extends('layouts.admin')

@section('title', 'Admin · Dashboard')

@section('content')
  <header class="page-header"><h1>Admin Dashboard</h1></header>
  <section class="cards-grid">
    {{-- Add admin widgets here --}}
    @include('partials.shared.empty-state', ['title' => 'No admin widgets yet'])
  </section>
@endsection
