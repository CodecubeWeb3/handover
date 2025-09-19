# Current Progress Snapshot

## Milestone Alignment
- **Milestone 5 – Booking State Machine & Pass Flow:** complete (guards, pass tokens/rotation, scan APIs, context logging).
- **Milestone 6 – Payments & Ledger:** core capture logic, late-fee/no-show policies, refund scaffolding, and payout settlement flags implemented; remaining work covers live Stripe API calls, webhook reconciliation, and ledger reporting.
- **Milestone 7 – Messaging & Realtime:** initial pass in place with booking threads, message sending, and broadcast auth placeholders; realtime delivery, typing/read receipts, and abuse workflows still pending.
- **Milestones 8–10:** admin tooling, security hardening, and launch-readiness not yet started.

## Feature Coverage
- **Pass & Guard Flow:** `/api/bookings/{booking}/pass/{leg}` + `/scan/{leg}` endpoints, guard evaluation + event logging, scheduled token rotation, documentation updates (`app/Domain/Bookings/*`, `routes/api.php`).
- **Payments:** `SharedPaymentManager` handles preauth/capture/no-show paths, `ChargeCalculator` applies late fees, `PaymentRefundService` supports partial refunds and payout settlement metadata, transfer records linked on bookings.
- **Messaging:** `MessageService` manages threads/messages, `POST /api/messages/{thread}/send` endpoint with auth + attachments, stubbed broadcast auth controller ready for real-time wiring.

## Environment Notes
- Use SQLite fallback locally (`DB_CONNECTION=sqlite`, `DB_DATABASE=database/database.sqlite`); migrations up to `2025_09_18_030000_update_payments_for_refunds` executed.
- Queue worker validated via `php artisan queue:work --tries=1 --stop-when-empty`.
- PHPUnit binary absent in this environment; run tests locally once installed.

## Next Suggested Steps
1. Integrate Stripe PaymentIntent/Transfer APIs plus webhook handlers for capture/refund/payout reconciliation.
2. Build messaging realtime layer (broadcast events, typing/read receipts) and moderation flows per Milestone 7.
3. Expand notifications (email/SMS) and link to pass/message events.
4. Begin Milestone 8 admin surfaces + moderation tooling.
5. Harden security/observability (Milestone 9) before performance + launch tasks (Milestone 10).- Stripe gateway scaffolding, webhook processing, and realtime messaging signals are in place; pending tasks: live API credentials, persistent read receipts UI, and admin tooling build-out.

- Admin dashboard (/admin) exposes messaging metrics; moderation queue now has API/UI with flag resolution, typing/read persistence, and queued notifications.
