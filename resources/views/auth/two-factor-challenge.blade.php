@extends('layouts.auth')

@section('title', 'Two-factor challenge')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="auth-logo">SH</div>
        <div>
            <h1 class="h4 mb-1">Two-factor authentication</h1>
            <p class="mb-0 text-muted-soft">Confirm it''s really you using your authenticator app or recovery code.</p>
        </div>
    </div>

    <form method="POST" action="{{ url('/two-factor-challenge') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="code" class="form-label">Authenticator code</label>
            <input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9]*"
                class="form-control @error('code') is-invalid @enderror" autofocus>
            @error('code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <div class="text-center text-muted-soft">or</div>
        </div>

        <div class="mb-3">
            <label for="recovery_code" class="form-label">Recovery code</label>
            <input id="recovery_code" type="text" name="recovery_code"
                class="form-control @error('recovery_code') is-invalid @enderror">
            @error('recovery_code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">Continue</button>
    </form>
@endsection