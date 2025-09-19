@extends("layouts.auth")

@section("title", "Moderation")

@push("styles")
    <style>
        .moderation-card {
            border-radius: 1rem;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.15);
        }
    </style>
@endpush

@section("content")
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">Moderation queue</h1>
            <p class="text-muted-soft mb-0">Review flagged messages and resolve incidents.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-light btn-sm">Back to dashboard</a>
    </div>

    <div class="moderation-card card shadow-sm">
        <div class="card-body p-4">
            <form class="row g-3 align-items-end mb-4" data-filter-form>
                <div class="col-md-3 col-lg-2">
                    <label for="filter-reason" class="form-label form-label-sm text-muted-soft">Reason</label>
                    <input type="text" class="form-control form-control-sm" name="reason" id="filter-reason" placeholder="Reason contains">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="filter-reporter" class="form-label form-label-sm text-muted-soft">Reporter</label>
                    <input type="text" class="form-control form-control-sm" name="reporter" id="filter-reporter" placeholder="Reporter name">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="filter-booking" class="form-label form-label-sm text-muted-soft">Booking ID</label>
                    <input type="number" class="form-control form-control-sm" name="booking_id" id="filter-booking" min="1" placeholder="e.g. 4821">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="filter-thread" class="form-label form-label-sm text-muted-soft">Thread ID</label>
                    <input type="number" class="form-control form-control-sm" name="thread_id" id="filter-thread" min="1" placeholder="e.g. 912">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="filter-message" class="form-label form-label-sm text-muted-soft">Message ID</label>
                    <input type="number" class="form-control form-control-sm" name="message_id" id="filter-message" min="1" placeholder="Message id">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="filter-from" class="form-label form-label-sm text-muted-soft">From</label>
                    <input type="date" class="form-control form-control-sm" name="date_from" id="filter-from">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="filter-to" class="form-label form-label-sm text-muted-soft">To</label>
                    <input type="date" class="form-control form-control-sm" name="date_to" id="filter-to">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="filter-per-page" class="form-label form-label-sm text-muted-soft">Per page</label>
                    <select class="form-select form-select-sm" id="filter-per-page" data-per-page>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <div class="col-md-12 col-lg-4 text-md-end">
                    <button type="submit" class="btn btn-primary btn-sm me-2">Apply filters</button>
                    <button type="button" class="btn btn-outline-light btn-sm" data-filter-reset>Reset</button>
                </div>
            </form>

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <span class="small text-muted" data-flag-status>Loading reports...</span>
                <div class="d-flex align-items-center gap-2" data-pagination></div>
            </div>

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
                            <td colspan="5" class="text-center text-muted-soft py-4">Loading flagged messages.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push("scripts")
    @vite("resources/js/moderation-page.js")
@endpush

