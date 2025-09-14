{{-- resources/views/partials/global/header.blade.php --}}
@php
  $user = auth()->user();
  $initial = strtoupper(mb_substr($user->name ?? 'U', 0, 1));
  $unreadCount = $user ? ($user->unreadNotifications->count() ?? 0) : 0; // safe on guests
@endphp

<header class="global-header" role="banner">
  <div class="gh__inner">
    {{-- Left: logo + title --}}
    <div class="gh__left">
      <a class="brand" href="{{ route('home') }}" aria-label="Go to Home">
        {{-- Replace with your SVG/IMG as needed --}}
        <span class="brand__logo" aria-hidden="true">
          <!-- simple logo square -->
          <svg viewBox="0 0 24 24" class="logo-svg"><rect x="3" y="3" width="18" height="18" rx="4"/></svg>
        </span>
        <span class="brand__name">{{ config('app.name') }}</span>
      </a>
    </div>

    {{-- Center: search --}}
    <div class="gh__center" role="search">
      @includeIf('partials.dropdowns.search-global')
      {{-- OPTIONAL fallback (remove if your partial renders its own input) --}}
      @if(!View::exists('partials.dropdowns.search-global'))
        <form action="{{ url('/search') }}" method="GET" class="searchbar" aria-label="Global search">
          <label for="global-search" class="sr-only">Search</label>
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
          <input id="global-search" name="q" type="search" placeholder="Search…" autocomplete="off" />
        </form>
      @endif
    </div>

    {{-- Right: toggles + icons + user --}}
    <div class="gh__right">
      {{-- Dark/Light theme toggle (persisted in localStorage) --}}
      <button id="themeToggle" class="toggle" data-theme-toggle aria-pressed="false" title="Toggle theme">
        <span class="toggle__thumb" aria-hidden="true"></span>
        <span class="sr-only">Toggle theme</span>
      </button>

      {{-- Quick icons --}}
      <nav class="icon-nav" aria-label="Quick access">
        {{-- RECENT --}}
        <div class="dd">
          <button class="icon-btn" type="button"
                  aria-haspopup="menu"
                  aria-expanded="false"
                  aria-controls="recent-menu"
                  data-dropdown-target="recent-menu"
                  title="Recent">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            <span class="sr-only">Recent</span>
          </button>
          <div id="recent-menu" class="dd__menu" role="menu" hidden>
            @includeIf('partials.dropdowns.recent')
          </div>
        </div>

        {{-- BOOKMARKS --}}
        <div class="dd">
          <button class="icon-btn" type="button"
                  aria-haspopup="menu"
                  aria-expanded="false"
                  aria-controls="bookmarks-menu"
                  data-dropdown-target="bookmarks-menu"
                  title="Bookmarks">
            <i class="fa-solid fa-bookmark" aria-hidden="true"></i>
            <span class="sr-only">Bookmarks</span>
          </button>
          <div id="bookmarks-menu" class="dd__menu" role="menu" hidden>
            @includeIf('partials.dropdowns.bookmarks')
          </div>
        </div>

        {{-- NOTIFICATIONS --}}
        <div class="dd">
          <button class="icon-btn" type="button"
                  aria-haspopup="menu"
                  aria-expanded="false"
                  aria-controls="notifications-menu"
                  data-dropdown-target="notifications-menu"
                  title="Notifications">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            @if($unreadCount > 0)
              <span class="dot" aria-label="{{ $unreadCount }} unread"></span>
            @endif
            <span class="sr-only">Notifications</span>
          </button>
          <div id="notifications-menu" class="dd__menu" role="menu" hidden>
            @includeIf('partials.dropdowns.notifications')
          </div>
        </div>
      </nav>

      {{-- User avatar / menu (safe for guests) --}}
      <div class="dd dd--user">
        <button class="avatar-btn" type="button"
                aria-haspopup="menu"
                aria-expanded="false"
                aria-controls="user-menu"
                data-dropdown-target="user-menu"
                title="Account">
          @if($user && method_exists($user, 'profile_photo_url') && $user->profile_photo_url)
            <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="avatar-img">
          @else
            <span class="avatar-fallback" aria-hidden="true">{{ $initial }}</span>
            <span class="sr-only">{{ $user->name ?? 'Guest' }}</span>
          @endif
        </button>
        <div id="user-menu" class="dd__menu dd__menu--user" role="menu" hidden>
          @includeIf('partials.dropdowns.user-menu')
          @unless (View::exists('partials.dropdowns.user-menu'))
            <a class="dd__item" href="{{ url('/profile') }}">Your Profile</a>
            <a class="dd__item" href="{{ url('/settings') }}">Settings</a>
            @if($user)
              <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="dd__item dd__item--danger" type="submit">Sign out</button>
              </form>
            @else
              <a class="dd__item" href="{{ route('login') }}">Sign in</a>
            @endif
          @endunless
        </div>
      </div>
    </div>
  </div>
</header>
