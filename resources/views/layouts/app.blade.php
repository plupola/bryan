{{-- resources/views/layouts/app.blade.php --}}
@extends('layouts.base')
{{-- Somewhere in layouts/app.blade.php, before </body> --}}
<div id="modal-root" x-data @open-modal.window="if ($event.detail.id) $refs[$event.detail.id].showModal()">
  {{-- Workspace Create Modal --}}
  <dialog x-ref="workspace-create-modal" class="modal">
    <form method="dialog" class="modal__overlay" @click.self="close()"></form>
    <div class="modal__content">
      <header class="modal__header">
        <h3 class="modal__title">Create Workspace</h3>
        <button class="modal__close" @click="$refs['workspace-create-modal'].close()">×</button>
      </header>
      <div id="modal-body" class="modal__body">
        {{-- Filled by hx-get from Add Workspace button --}}
      </div>
    </div>
  </dialog>
</div>

@section('body')
  <header role="banner">
    @include('partials.global.header')
  </header>

  <div class="app-shell" data-layout="app">
    <aside class="app-sidebar" role="complementary" aria-label="Global navigation">
      @include('partials.global.sidebar')
    </aside>

    <main class="app-main" role="main" id="main-content">
      @yield('content')
    </main>
  </div>
@endsection
