@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="auth-logo">SH</div>
        <div>
            <h1 class="h3 mb-1">Welcome back</h1>
            <p class="mb-0 text-muted-soft">Authenticate to manage bookings or accept new assignments.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate autocomplete="off">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                autocomplete="email" class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                class="form-control @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">Stay signed in</label>
            </div>
            <a class="link-muted small" href="{{ route('password.request') }}">Forgotten password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>

    <p class="text-center text-muted-soft mt-4 mb-0">
        Need an account? <a class="link-muted" href="{{ route('register') }}">Start verification</a>.
    </p>
@endsection