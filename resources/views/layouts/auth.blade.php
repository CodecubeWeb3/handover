<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ trim($__env->yieldContent('title').' · ') }}{{ config('app.name', 'Safe Handover') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body data-user-id="{{ auth()->id() }}">
    <main class="auth-card card border-0 shadow-lg mx-auto">
        <div class="card-body p-4 p-md-5">
            @yield('content')
        </div>
    </main>
    @stack('scripts')
</body>
</html>
