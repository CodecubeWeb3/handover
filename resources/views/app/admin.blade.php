@extends('layouts.auth')

@section('title', 'Admin Overview')

@push('styles')
    <style>
        .admin-grid {
            display: grid;
            gap: 1.25rem;
        }

        @media (min-width: 768px) {
            .admin-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1200px) {
            .admin-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .metric-card {
            border-radius: 1rem;
            background: rgba(15, 23, 42, 0.75);
            border: 1px solid rgba(148, 163, 184, 0.12);
            padding: 1.5rem;
            display: grid;
            gap: 0.35rem;
        }

        .metric-card h2 {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
    </style>
@endpush

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">Moderation overview</h1>
            <p class="text-muted-soft mb-0">Monitor conversations, flags, and booking engagement.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('messages.page') }}" class="btn btn-outline-light btn-sm">Open messenger</a>
            <a href="{{ route('moderation.flags') }}" class="btn btn-primary btn-sm">Moderation queue</a>
        </div>
    </div>

    <section class="admin-grid mb-4">
        <article class="metric-card">
            <span class="text-uppercase text-muted-soft small">Open flags</span>
            <h2 class="text-warning">{{ number_format($metrics['flagged']) }}</h2>
            <p class="text-muted-soft mb-0">Pending message reports awaiting moderator action.</p>
        </article>
        <article class="metric-card">
            <span class="text-uppercase text-muted-soft small">Active threads</span>
            <h2 class="text-info">{{ number_format($metrics['threads']) }}</h2>
            <p class="text-muted-soft mb-0">Conversations currently open between parents and operatives.</p>
        </article>
        <article class="metric-card">
            <span class="text-uppercase text-muted-soft small">Messages logged</span>
            <h2 class="text-success">{{ number_format($metrics['messages']) }}</h2>
            <p class="text-muted-soft mb-0">Total secure messages exchanged on the platform.</p>
        </article>
        <article class="metric-card">
            <span class="text-uppercase text-muted-soft small">Bookings with chat</span>
            <h2 class="text-primary">{{ number_format($metrics['bookings']) }}</h2>
            <p class="text-muted-soft mb-0">Bookings where messaging is active across both legs.</p>
        </article>
    </section>

    <div class="card border-0 shadow-sm" style="background: rgba(15, 23, 42, 0.75); border-radius: 1rem;">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Tips for moderators</h2>
            <ol class="text-muted-soft mb-0">
                <li>Review the moderation queue daily to ensure rapid resolution.</li>
                <li>Escalate any safety concerns to the on-call manager immediately.</li>
                <li>Use the conversation view to gather context before contacting participants.</li>
            </ol>
        </div>
    </div>
@endsection

