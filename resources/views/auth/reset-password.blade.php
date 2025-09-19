@extends('layouts.auth')

@section('title', 'Choose a new password')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="auth-logo">SH</div>
        <div>
            <h1 class="h4 mb-1">Set a new password</h1>
            <p class="mb-0 text-muted-soft">Passwords must remain confidential and unique.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('password.store') }}" novalidate>
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required readonly
                class="form-control-plaintext text-light">
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">New password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                class="form-control @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                class="form-control">
        </div>

        <button type="submit" class="btn btn-primary w-100">Update password</button>
    </form>
@endsection