{{-- resources/views/compliance/retention-policies.blade.php --}}
@extends('layouts.admin')

@section('title', 'Compliance · Retention Policies')

@section('content')
  <header class="page-header"><h1>Retention Policies</h1></header>
  @include('partials.shared.empty-state', ['title' => 'No policies yet'])
@endsection
