{{-- resources/views/partials/dropdowns/user-menu.blade.php --}}
<ul class="menu" role="menu">
  <li><a href="{{ route('profile.show') ?? '#' }}">Your Profile</a></li>
  <li><a href="{{ route('about') ?? '#' }}">About Huddle</a></li>
  <li><a href="{{ route('help.onboarding') ?? '#' }}">Show me how to use my App</a></li>
  <li><a href="{{ route('feedback') ?? '#' }}">Give Feedback</a></li>
  <li class="separator" role="separator"></li>
  <li>
    <form action="{{ route('logout') ?? '#' }}" method="post">
      @csrf
      <button type="submit">Sign out</button>
    </form>
  </li>
</ul>
