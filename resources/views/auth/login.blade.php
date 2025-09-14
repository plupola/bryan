@extends('layouts.app')

@section('page-content')
<div 
    x-data="{ 
        requires2FA: {{ session('requires_2fa') ? 'true' : 'false' }}, 
        twoFactorCode: '' 
    }" 
    class="auth-shell"
>
    <header class="auth-header">
        <a class="brand" href="/">{{ config('app.name', 'YourApp') }}</a>
    </header>
    
    <main class="auth-main">
        <section class="login-card">
            <h1 class="card-title">Sign in</h1>
            <p class="card-subtitle">Welcome back. Please enter your credentials.</p>

            {{-- Session Status --}}
            @if (session('status'))
                <div class="alert" role="alert" style="background: color-mix(in srgb, var(--success) 8%, white); border-color: color-mix(in srgb, var(--success) 40%, var(--border)); color: color-mix(in srgb, var(--success) 80%, #6b7280);">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert show" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" 
                  action="{{ route('login') }}" 
                  class="login-form"
                  x-bind:action="requires2FA ? '{{ route('login') }}?2fa=1' : '{{ route('login') }}'">
                @csrf

                {{-- Email Address --}}
                <div class="form-field">
                    <label class="label" for="email">Email</label>
                    <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" 
                           autocomplete="email" required autofocus placeholder="Enter your email">
                    <p class="field-hint">your email.</p>
                </div>

                {{-- Password --}}
                <div class="form-field">
                    <label class="label" for="password">Password</label>
                    <input class="input" id="password" type="password" name="password" 
                           autocomplete="current-password" required placeholder="Enter your password">
                </div>

                {{-- Remember Me --}}
                <div class="form-row">
                    <label class="checkbox">
                        <input id="remember_me" type="checkbox" name="remember"> 
                        <span>Remember me</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a class="link" href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                </div>

                {{-- 2FA Code (hidden by default) --}}
                <div x-show="requires2FA" x-transition class="form-field">
                    <label class="label" for="two_factor_code">Two-Factor Code</label>
                    <input class="input" id="two_factor_code" type="text" name="two_factor_code" 
                           x-model="twoFactorCode" placeholder="Enter your 2FA code">
                    <input type="hidden" name="requires_2fa" x-bind:value="requires2FA ? '1' : '0'">
                </div>

                <button class="btn btn-primary" type="submit">
                    <span x-text="requires2FA ? 'Verify & Sign In' : 'Sign in'"></span>
                </button>
            </form>

            <p class="card-footer">New here? <a class="link" href="{{ route('register') }}">Create an account</a></p>
        </section>
    </main>
    
    <footer class="auth-footer">© {{ date('Y') }} {{ config('app.name', 'YourApp') }}</footer>
</div>
@endsection
