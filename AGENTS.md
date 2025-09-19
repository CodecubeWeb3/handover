# Repository Guidelines

## Project Structure & Module Organization
Core application logic sits in `app/`, with `App\Domain` holding booking, payment, and verification services, and `App\Http` covering controllers, form requests, and middleware. Jobs live in `app\Jobs`, while scheduled routines register in `app\Console\Kernel.php`. Database assets are split across `database/migrations`, `database/factories`, and `database/seeders`, including SRID 4326 spatial columns. Frontend code is organised under `resources/js` and Blade views in `resources/views`, with reusable components in `resources/views/components`. Tests mirror runtime folders: `tests/Unit`, `tests/Feature`, `tests/Integration`, and `tests/Browser` for Dusk.

## Build, Test, and Development Commands
Run `composer install`, `npm install`, and the guided `php artisan app:install --force` to configure `.env`, validate extensions, execute migrations/seeds, and create the storage symlink.
After installation, `php artisan migrate --seed` keeps schemas current; use `npm run dev` while iterating or `npm run build` ahead of releases.
`php artisan test` provides quick feedback; append `--group=integration` for Stripe/Ably paths, and run `php artisan dusk` before changing pass, chat, or realtime journeys.
Keep the scheduler active (`php artisan schedule:work`) or add the cron snippet emitted by the installer; persistent workers should use `php artisan queue:work --tries=1`.

## Coding Style & Naming Conventions
Adopt PSR-12 via `./vendor/bin/pint`; no mixed tabs. Domain services prefer typed properties, enums, and expressive method names (e.g., `finalizeLegHandOver`). Jobs and listeners follow `<Verb><Subject>Job` and `<Event><Listener>`. Blade components stay in StudlyCase, Vue/React (when present) in kebab-case. Use `npm run lint` to apply ESLint and Prettier defaults.

## Testing Guidelines
Target >85% line coverage inside `App\Domain`. Every state-machine change needs unit coverage for time, geo, token, and device guards, plus a feature test under `tests/Feature/Scans`. Integration tests stub Stripe and Ably fixtures and assert idempotency headers. Dusk scenarios reside in `tests/Browser/Handovers` and should be tagged `@group dusk` for CI selectivity. Keep fixtures in `tests/Fixtures` with descriptive, timestamped names.

## Commit & Pull Request Guidelines
Git history follows Conventional Commits (`feat:`, `fix:`, `refactor:`). Keep scopes aligned with top-level directories (`feat(app): prevent double booking`). PRs must summarise customer impact, list affected journeys, link the Linear/Jira ticket, and attach evidence (screenshots, console logs, or test output). Flag configuration or feature-flag changes explicitly and request Stripe reviewers when touching payouts.

## Security & Configuration Tips
Never commit secrets or raw webhook payloads; store them in `.env` and update `config/services.php` docs instead. Verify `storage/` and `bootstrap/cache` remain writable after deployment and use `/api/health` (or `php artisan app:install` diagnostics) before handover. Stripe webhooks and Ably keys must be active in staging before production merges, and new feature flags belong in `config/feature-flags.php` with documented defaults.
## Pass Infrastructure
Pass tokens live under `App\Domain\Bookings\Services\PassTokenManager`. Each booking leg owns a `handover_tokens` row storing an encrypted TOTP secret and offline PIN. Parents and operatives fetch payloads via `GET /api/bookings/{booking}/pass/{leg}` (Sanctum) which returns the otpauth URI, QR payload, deep link, and offline PIN; append `?refresh=1` to rotate immediately. Agents scan passes through `POST /api/bookings/{booking}/scan/{leg}` providing `event_uuid`, `token.code`, `location`, and `device.attested`. The booking guards hydrate context automatically and persist outcomes on the event log. Rotation runs through `RotateStalePassTokensJob`, scheduled every five minutes, which refreshes stale secrets and queues notifications so new payloads reach both parents and the operative.
## Payments Progress
SharedPaymentManager (App\Domain\Payments\Services) now provisions Payment and PaymentIntent records when bookings move into the A window and captures them once the B leg scans. ChargeCalculator centralises slot pricing, platform share, and travel stipend splits, while transfers are raised in pending state until Stripe wiring is completed. See config/payments.php for currency and base slot price controls.
Late fees derive from booking event timestamps (scan_A_ok/scan_B_ok) versus country grace + wait caps, so ensure booking event seeding reflects those states when testing payments.

Timer grace expiries automatically call SharedPaymentManager no-show helpers so API/queue jobs triggering those events will record captures/cancellations accordingly.

Refund scaffolding: use PaymentRefundService to zero captured legs and mark payments refunded until Stripe API wiring is added.

Messaging: use POST /api/messages/{thread}/send with Sanctum auth; MessageService auto-creates booking threads and emits MessageSent events for future realtime adapters.

Stripe integration: set PAYMENT_GATEWAY=stripe and provide STRIPE_SECRET/STRIPE_WEBHOOK_SECRET before enabling live intents/transfers; simulation mode issues deterministic ids for local testing. Messaging API now supports typing/read signals and moderation flags via POST /api/messages/{thread}/typing|read and /api/messages/flag/{message}.
Admin overview available at `/admin` (admins/moderators) showing conversation metrics; moderation queue at `/moderation/flags` consumes new `/api/messages/flags` endpoints.
