{{-- resources/views/compliance/data-residency.blade.php --}}
@extends('layouts.admin')

@section('title', 'Compliance · Data Residency')

@section('content')
  <header class="page-header"><h1>Data Residency</h1></header>
  @include('partials.shared.empty-state', ['title' => 'No data yet'])
@endsection
