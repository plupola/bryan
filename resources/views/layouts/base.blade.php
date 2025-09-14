{{-- resources/views/layouts/base.blade.php --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>@yield('title', config('app.name'))</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <link
  rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
  referrerpolicy="no-referrer"
/>

  @stack('head')
</head>
<body class="min-h-screen">
  @yield('body')
  @stack('scripts')
</body>
</html>
