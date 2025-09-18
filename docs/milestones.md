# Safe Handover Delivery Roadmap

This roadmap breaks the production build into sequential milestones. Each milestone ends in a deployable, fully-tested slice that can ship independently while enabling the next stage.

## Milestone 0 – Project Foundation
- Lock Laravel 12 / PHP 8.4 toolchain, Composer dependencies, and coding standards.
- Configure environment scaffolding: `.env.example`, config stubs for queue (database driver), cache, mail, SMS, S3 storage, Pusher/Ably, Stripe, and Stripe Identity.
- Set up base Docker/CI scripts for linting, static analysis, and PHPUnit; ensure database queue + cron worker instructions.
- Establish shared utilities: feature flag helper, response macros, domain enums, and error handling baseline.

## Milestone 1 – Core Domain Modeling
- Define ERD-aligned migrations for all primary entities (users, profiles, children, bookings, booking_slots, applications, operative_holds, payouts, audits, etc.), including spatial columns (SRID 4326) and unique constraints.
- Seed lookup/reference data (countries, VAT/GST, retention policies, permissions).
- Implement Eloquent models with casts, relationships, and domain factories.
- Add repository/service layer skeletons for future modules, plus base validation rules.

## Milestone 2 – Authentication & Verification Shell
- Implement email/password registration with Laravel Fortify, passwordless magic links for parents, and enforced email/phone verification.
- Integrate phone verification via SMS OTP (configurable provider) and enforce MFA for admins/moderators.
- Stub Stripe Identity onboarding endpoints (without business logic yet) and store verification tokens.
- Create base middleware for role gating and country profile scoping.

## Milestone 3 – Parent Experience MVP
- Build parent dashboards: service area setup (map + polygons/postcodes), child profiles, and request creation wizard with 10-min slot grid and meet point capture.
- Persist booking requests with location validation (MySQL spatial) and attach children.
- Implement calendars/heatmaps, waitlist visibility, and status badges from state machine.
- Provide parent notifications (email/SMS) for key transitions.

## Milestone 4 – Operative Experience MVP
- Create operative onboarding flow: ID/KYC trigger, service areas, availability toggles, ledger overview.
- Build search/apply/accept interfaces for nearby requests with overlap guards and waitlists.
- Implement operative holds with TTL and automatic release.
- Surface compliance tasks (scan requirements, device security reminders).

## Milestone 5 – Booking State Machine & Pass Flow
- Encode booking state machine with events, transitions, and guards; ensure transactional integrity.
- Deliver pass experience: QR + TOTP codes, device binding, offline fallback PINs.
- Implement scanning APIs for Parent A/B and Agent, including geo/time/device validation.
- Trigger buffer windows, handle cancellations/no-shows, and audit logs for every transition.

## Milestone 6 – Payments & Financial Ledger
- Integrate Stripe Connect Express onboarding for operatives and platform account linkage.
- Create PaymentIntent lifecycle: pre-authorization on booking, manual capture on completion, refunds/adjustments, and late-fee accrual.
- Build internal ledger, payout schedules, invoices/receipts, VAT/GST handling, and shared-payment support.
- Wire Stripe webhooks with signature verification, idempotency, and failure replays.

## Milestone 7 – Messaging, Notifications & Realtime
- Implement secure booking chat with attachments, event typing indicators, and moderation tools.
- Integrate Pusher/Ably channels scoped to booking participants; add read receipts.
- Expand notification system: email, SMS, optional web push, and digest summaries.
- Add abuse reporting and escalation workflows.

## Milestone 8 – Admin & Moderation Suite
- Install and configure Filament admin with role-based dashboards.
- Provide user management, booking oversight, dispute resolution, compliance reviews, and content moderation queues.
- Surface analytics: bookings per region, SLA adherence, payouts, and incident metrics.
- Include audit trails, immutable event store, and data export tooling (GDPR/subject access).

## Milestone 9 – Security, Privacy & Observability Hardening
- Enforce strict policies: rate limiting, IP allowlists for admin, device fingerprinting, and anomaly detection.
- Implement encryption-at-rest policies, secrets rotation hooks, data retention purge jobs, and consent logging.
- Add monitoring: application logs (structured), metrics, uptime pings, error reporting, and health checks.
- Complete DPA/compliance documentation and threat modeling artifacts.

## Milestone 10 – Performance, QA & Launch Readiness
- Optimize queries with indexes, caching, and pagination budgets; run load tests on booking hotspots.
- Achieve accessibility AA compliance, responsive audits, and cross-browser/device testing.
- Finalize automated test suite coverage (feature, integration, Dusk, API contract tests).
- Prepare deployment scripts for shared hosting, cron setup, queue runners, blue/green strategy, and rollback procedures.
- Compile launch playbook, runbooks, and support handover documentation.

Each milestone will result in production-grade code, tests, and documentation merged into `main` via reviewable PRs.
