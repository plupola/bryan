{{-- resources/views/api/keys.blade.php --}}
@extends('layouts.admin')

@section('title', 'Admin · API Keys')

@section('content')
  <header class="page-header"><h1>API Keys</h1></header>
  @include('partials.shared.empty-state', ['title' => 'No keys yet'])
@endsection
