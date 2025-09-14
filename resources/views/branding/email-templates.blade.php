{{-- resources/views/branding/email-templates.blade.php --}}
@extends('layouts.admin')

@section('title', 'Branding · Email Templates')

@section('content')
  <header class="page-header"><h1>Email Templates</h1></header>
  @include('partials.shared.empty-state', ['title' => 'No templates yet'])
@endsection
