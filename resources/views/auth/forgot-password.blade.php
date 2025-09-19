@extends('layouts.auth')

@section('title', 'Reset password')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="auth-logo">SH</div>
        <div>
            <h1 class="h4 mb-1">Forgot your password?</h1>
            <p class="mb-0 text-muted-soft">We will email a secure link to reset access.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">Email me a reset link</button>
    </form>

    <p class="text-center text-muted-soft mt-4 mb-0">
        Remembered your password? <a class="link-muted" href="{{ route('login') }}">Sign in</a>.
    </p>
@endsection