@extends('layouts.auth')

@section('title', 'Create account')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="auth-logo">SH</div>
        <div>
            <h1 class="h3 mb-1">Create your Safe Handover account</h1>
            <p class="mb-0 text-muted-soft">Trusted custody exchanges begin with verified identities.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('register') }}" novalidate class="needs-validation" autocomplete="off">
        @csrf

        <div class="mb-3">
            <label for="name" class="form-label">Full name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="191"
                class="form-control @error('name') is-invalid @enderror" autofocus>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="191"
                autocomplete="email"
                class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Mobile number</label>
            <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required maxlength="32"
                placeholder="+44 7123 456789"
                class="form-control @error('phone') is-invalid @enderror">
            <div class="form-text">Include your country code. We'll send verification codes via SMS.</div>
            @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="country" class="form-label">Country</label>
                <input id="country" type="text" name="country" value="{{ old('country', 'GB') }}" required maxlength="2"
                    class="form-control text-uppercase @error('country') is-invalid @enderror">
                <div class="form-text">Two-letter ISO code (e.g. GB, IE, US).</div>
                @error('country')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="dob" class="form-label">Date of birth</label>
                <input id="dob" type="date" name="dob" value="{{ old('dob') }}" required
                    max="{{ now()->subYears(18)->toDateString() }}"
                    class="form-control @error('dob') is-invalid @enderror">
                @error('dob')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        @php($role = old('role', \App\Enums\UserRole::Parent->value))
        <fieldset class="mt-4">
            <legend class="fs-6 text-uppercase text-muted-soft mb-2">Role</legend>
            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <div @class(['form-check p-3 border rounded-3 h-100', 'border-primary' => $role === \App\Enums\UserRole::Parent->value, 'border-secondary-subtle' => $role !== \App\Enums\UserRole::Parent->value])>
                        <input class="form-check-input position-static me-2" type="radio" name="role" id="role-parent"
                            value="parent" {{ $role === 'parent' ? 'checked' : '' }}>
                        <label class="form-check-label" for="role-parent">
                            <strong>Parent</strong>
                            <span class="d-block small text-muted-soft">Manage bookings, passes, and shared payments.</span>
                        </label>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div @class(['form-check p-3 border rounded-3 h-100', 'border-primary' => $role === \App\Enums\UserRole::Operative->value, 'border-secondary-subtle' => $role !== \App\Enums\UserRole::Operative->value])>
                        <input class="form-check-input position-static me-2" type="radio" name="role" id="role-operative"
                            value="operative" {{ $role === 'operative' ? 'checked' : '' }}>
                        <label class="form-check-label" for="role-operative">
                            <strong>Operative</strong>
                            <span class="d-block small text-muted-soft">Complete identity checks and accept nearby requests.</span>
                        </label>
                    </div>
                </div>
            </div>
            @error('role')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </fieldset>

        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label for="password" class="form-label">Password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password"
                    class="form-control @error('password') is-invalid @enderror">
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Minimum 12 characters with upper, lower, number, and symbol.</div>
            </div>
            <div class="col-md-6">
                <label for="password_confirmation" class="form-label">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                    class="form-control">
            </div>
        </div>

        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" value="1" id="terms" name="terms"
                {{ old('terms') ? 'checked' : '' }} required>
            <label class="form-check-label" for="terms">
                I agree to the <a href="#" class="link-muted">Terms of Service</a> and
                <a href="#" class="link-muted">Privacy Policy</a>.
            </label>
            @error('terms')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 mt-4">Create account</button>
    </form>

    <p class="text-center text-muted-soft mt-4 mb-0">
        Already onboarded? <a class="link-muted" href="{{ route('login') }}">Sign in</a>.
    </p>
@endsection