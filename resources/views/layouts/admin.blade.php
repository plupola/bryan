{{-- resources/views/layouts/admin.blade.php --}}
@extends('layouts.base')

@section('body')
  <header role="banner">
    @include('partials.global.header')
  </header>

  <div class="admin-shell" data-layout="admin">
    <aside class="admin-sidebar" role="complementary" aria-label="Admin navigation">
      @include('partials.global.sidebar') {{-- or an admin-specific sidebar if you prefer --}}
    </aside>

    <main class="admin-main" role="main" id="admin-main-content">
      @yield('content')
    </main>
  </div>
@endsection
