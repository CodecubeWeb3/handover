@extends('layouts.auth')

@section('title', 'Verify email')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="auth-logo">SH</div>
        <div>
            <h1 class="h4 mb-1">Confirm your email address</h1>
            <p class="mb-0 text-muted-soft">We've sent a secure verification link to your inbox.</p>
        </div>
    </div>

    @if (session('status') === 'verification-link-sent')
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            A fresh verification link has been emailed to you.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <p class="text-muted-soft">
        Didn't receive the email? Check your spam folder or request another verification link below.
        You must complete email verification before creating or accepting bookings.
    </p>

    <form method="POST" action="{{ route('verification.send') }}" class="d-grid gap-3">
        @csrf
        <button type="submit" class="btn btn-primary">Resend verification email</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="btn btn-outline-light btn-sm">Sign out</button>
    </form>
@endsection