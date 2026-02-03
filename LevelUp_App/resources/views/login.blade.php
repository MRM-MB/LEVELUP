@extends('layouts.app')

@section('title', 'Log In')

@section('content')
<div class="auth-page">
    <div class="auth-content">
        <div class="login-container">
            <div class="login-card">
                <div class="text">
                    Log In
                </div>

                {{-- Validation errors --}}
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('login.perform') }}" method="POST" class="form" novalidate>
                    @csrf
                    <div class="login-data">
                        <label for="username">Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="{{ old('username') }}"
                            required
                            autocomplete="username"
                        >
                    </div>

                    <div class="login-data">
                        <label for="password">Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="loginpage-btn">
                        <button type="submit">Log In</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

{{-- Clear timer state on login page load to ensure fresh start after logout --}}
<script>
    // Clear timer state when arriving at login page (e.g., after logout)
    localStorage.removeItem('levelup_timer_state');
</script>
@endsection