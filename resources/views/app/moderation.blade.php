@extends('layouts.auth')

@section('title', 'Moderation')

@push('styles')
    <style>
        .moderation-card {
            border-radius: 1rem;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.15);
        }
    </style>
@endpush

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">Moderation queue</h1>
            <p class="text-muted-soft mb-0">Review flagged messages and resolve incidents.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-light btn-sm">Back to dashboard</a>
    </div>

    <div class="moderation-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive" data-flag-table>
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead class="text-uppercase small text-muted">
                        <tr>
                            <th class="ps-4">Message</th>
                            <th>Reason</th>
                            <th>Reporter</th>
                            <th>Flagged</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody data-flag-rows>
                        <tr>
                            <td colspan="5" class="text-center text-muted-soft py-4">Loading flagged messages…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/moderation-page.js')
@endpush