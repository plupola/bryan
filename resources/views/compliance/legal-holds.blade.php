{{-- resources/views/compliance/legal-holds.blade.php --}}
@extends('layouts.admin')

@section('title', 'Compliance · Legal Holds')

@section('content')
  <header class="page-header"><h1>Legal Holds</h1></header>
  @include('partials.shared.empty-state', ['title' => 'No legal holds'])
@endsection
