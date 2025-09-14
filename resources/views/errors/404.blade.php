{{-- resources/views/errors/404.blade.php --}}
@extends('layouts.app')

@section('content')
  <div class="mx-auto max-w-xl py-16 text-center">
    <h1 class="text-3xl font-semibold mb-4">404 — Page Not Found</h1>
    <p class="text-gray-600 mb-8">Sorry, the page you are looking for could not be found.</p>
    <a href="{{ route('home') }}" class="btn btn-primary">Go Home</a>
  </div>
@endsection
