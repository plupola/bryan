{{-- resources/views/branding/preview.blade.php --}}
@extends('layouts.admin')

@section('title', 'Branding · Preview')

@section('content')
  <header class="page-header"><h1>Branding Preview</h1></header>
  @include('partials.shared.empty-state', ['title' => 'No preview yet'])
@endsection
