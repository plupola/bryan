{{-- resources/views/compliance/reports.blade.php --}}
@extends('layouts.admin')

@section('title', 'Compliance · Reports')

@section('content')
  <header class="page-header"><h1>Compliance Reports</h1></header>
  @include('partials.shared.empty-state', ['title' => 'No reports yet'])
@endsection
