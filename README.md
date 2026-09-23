# 007resort-booking-web

Public property website and online booking / customer portal for the
**007 Resort & Spa Integrated Facility Operations Platform**.

> Status: **MVP.** Public site, sports/spa/salon booking, pool tickets, memberships, customer
> accounts, booking history, QR tickets, Paystack checkout, and a Mock API mode. Contract gaps that
> depend on API modules not yet landed are listed in [docs/API_DEPENDENCIES.md](docs/API_DEPENDENCIES.md).

## Purpose

`007resort-booking-web` is the customer-facing site:

- Facility pages and services
- Sports court/pitch booking, spa appointments, pool day tickets
- Online payment and QR confirmation
- Customer portal: booking history, cancel / reschedule (subject to the API's rules)

It is a Laravel **UI / backend-for-frontend (BFF)** over the **007 Resort & Spa API** (Laravel, cloud node),
which is the single "brain" of the platform and the **only** owner of the MySQL schema.

## Architecture rules

- **PHP never mutates business data directly - it calls the API.** Bookings, tickets,
  payments, refunds and memberships are created/changed only via `/api/v1/...` using
  `App\Services\R007Api\R007ApiClient`.
- **One booking engine.** Availability and bookings come from the **same API booking engine
  used by Reception**. This app contains no availability or booking logic of its own, so the
  same slot can never be sold twice (online vs. front desk).
- **Customer accounts live in the API.** Customers register/sign in through API customer
  endpoints; the access token is stored **server-side in the session** and never exposed to
  the browser.
- **Payment provider callbacks/webhooks go to the API, never to this app.** This app only
  redirects the customer to the provider and shows the result the API reports.
- **No application database.** No business migrations or Eloquent models (enforced by
  `tests/Unit/NoBusinessTablesTest.php`).
- **Idempotency.** Every `POST/PUT/PATCH/DELETE` sends an `Idempotency-Key` header -
  essential for "Pay now" / "Book" double-clicks and retries.
- **Errors** use RFC 7807 problem details, mapped to `R007ApiException`.
- **Money** is received as decimal strings - never float arithmetic.
- **Time.** The API stores/returns UTC; convert to local time for display only.

### Reporting hierarchy (context)

Online sales are attributed by the API like any other channel:

```
Property -> Facility -> Operating Point -> Terminal -> Staff -> Transaction
```

(online bookings use a dedicated "online" operating point / terminal in the API).

## What is in the site

| Area | Routes |
| --- | --- |
| Public | `/`, `/facilities/{slug}` (restaurant, indoor-club, beauty-spa, pool, sports-arena, bush-bar, salon, cafe, supermarket), `/contact`, `/sitemap.xml`, `/robots.txt` |
| Sports / spa / salon booking | `/book/{slug}` (pick court/treatment) -> `/book/{slug}/{resource}?date=` (slot grid) -> hold -> `/checkout/{booking}` (hold countdown) -> Paystack -> `/payment/return` -> `/tickets/{id}` (QR) |
| Pool tickets | `/pool` (adult/child counts) -> Paystack -> `/orders/{order}/tickets` (one QR per person) |
| Memberships | `/memberships` -> Paystack -> account |
| Customer | `/register`, `/verify`, `/login`, `/account`, `/account/bookings[/{id}]` with cancel/reschedule, ticket pages with SVG download and print |

Everything shown or decided (availability, holds, prices, cancel/reschedule eligibility, payment outcome)
comes from the API. This app has no booking, availability or payment logic and no database.

### Graceful degradation (site offline / stale)

- The API can say a facility or resource is unavailable online (`onlineBookable: false`, problem code
  `capability_disabled` / `offline_not_allowed`, or HTTP 503). Only that item shows a calm notice; the rest of
  the site keeps working.
- If the API is unreachable, static facility content still renders with a "live availability unavailable" banner.
- Payment return never claims success or failure it cannot verify ("we could not check your payment yet").

### Safety

- **Payment return** (`/payment/return`): the `reference` query parameter is only a lookup key. The outcome is
  always obtained from `GET /payments/paystack/verify/{reference}` on the API; other query parameters
  (`status=success`, ...) are ignored. Paystack webhooks terminate at the API; this app has no webhook route
  (asserted by a test).
- **Double-submit safety**: every mutating form carries a per-render `_submission` UUID that is forwarded as the
  API `Idempotency-Key` and guarded by a short cache lock (`App\Support\IdempotentSubmit`); JS also locks buttons.
- **CSRF** (Laravel web middleware), **rate limits** (`login`, `register`, `verify`, `booking`, `payment`, `public`
  in `AppServiceProvider`), **spam guard** (honeypot + minimum fill time on registration), security headers + CSP
  (`SecurityHeaders`), API token kept server-side in an encrypted session.
- **Privacy-conscious logging**: only request id, HTTP status and stable problem code are logged; never names,
  emails, phones, tokens or payloads.

## Requirements

- PHP 8.4+ with `mbstring`, `intl`, `bcmath`, `curl`, `dom`, `fileinfo`, `openssl`, `xml`, `xmlwriter`, `zip`
- Composer 2
- Node.js 24 + npm
- A reachable 007 Resort & Spa API (cloud node) **or** Mock API mode

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci && npm run build   # or `npm run dev`
```

## Run

```bash
# Against the real API
#   R007_API_BASE_URL=https://api.example.com  R007_API_SERVICE_TOKEN=<secret from the API>
php artisan serve --port=8107   # http://localhost:8107
```

### Mock API mode (no backend needed)

```bash
R007_MOCK=true php artisan serve --port=8107
```

`R007_MOCK=true` swaps the API client for an in-process fixture (`App\Services\R007Api\MockR007ApiClient`)
with courts, spa/salon services, pool tickets and membership plans, customer registration (verification code
`123456`), holds that really conflict (`409 slot_unavailable`) and expire after 10 minutes, cancellation windows,
and a fake Paystack page (`/mock/paystack/{reference}`, only served in mock mode) with success/fail buttons.
State is kept in the cache store and is wiped by `php artisan cache:clear`. Set
`R007_MOCK_OFFLINE_FACILITIES=salon` to see the degraded-availability UX for one facility.

## Test

```bash
vendor/bin/pint --test
php artisan test
npm run build
```

Feature tests use `Http::fake` against the contract shapes (see `tests/ApiTestCase.php`): every flow, the
`409 slot_unavailable` UX, payment-return handling (verify via API, query params ignored), degraded-availability
notices, auth, CSRF, rate limits and double-submit. `MockModeTest` runs the whole journey against the mock.

CI (`.github/workflows/ci.yml`) runs Pint, the test suite, a front-end build and a gitleaks
secret scan on every push/PR.

## Configuration

All configuration comes from the environment (see `.env.example` - placeholders only).

| Variable | Default | Description |
| --- | --- | --- |
| `R007_API_BASE_URL` | `http://127.0.0.1:5080` | Base URL of the Cloud API (`https://api.<domain>`) |
| `R007_API_PREFIX` | `/api/v1` | Versioned API path prefix |
| `R007_API_TIMEOUT` | `10` | Request timeout (seconds) |
| `R007_API_CONNECT_TIMEOUT` | `3` | Connect timeout (seconds) |
| `R007_API_CLIENT_ID` | `007resort-booking-web` | Public client identifier registered in the API (not a secret) |
| `R007_API_SERVICE_TOKEN` | empty | **Secret.** Credential of the "online" channel for public reads when nobody is signed in |
| `R007_MOCK` | `false` | Mock API mode (dev/demo only; never in production) |
| `R007_MOCK_OFFLINE_FACILITIES` | empty | Mock only: facility keys shown as paused |
| `R007_BOOKING_HORIZON_DAYS` | `30` | How far ahead the date pickers go |
| `R007_DISPLAY_TIMEZONE` | `Africa/Lagos` | Timezone used when rendering dates |
| `SITE_PHONE`, `SITE_EMAIL`, `SITE_ADDRESS`, `SITE_MAP_URL` | placeholders | Fallback contact details (API `/public/site` wins) |
| `TRUSTED_PROXIES` | `127.0.0.1` | Reverse proxy addresses whose `X-Forwarded-*` are trusted |
| `SESSION_DRIVER` | `file` | Session store (holds the customer's API token server-side; `SESSION_ENCRYPT=true`) |
| `CACHE_STORE` | `file` | Cache store (also used for idempotency locks; must support locks: file/redis) |

See `config/r007.php` and `config/site.php` (static facility copy and the API-kind matching).

## Deployment

- **Runs on the Cloud node VPS** (ADR-0014) behind nginx/caddy with TLS. Build with
  `composer install --no-dev --optimize-autoloader && npm ci && npm run build`, then
  `php artisan config:cache route:cache view:cache`, serve `public/` with PHP-FPM. Set `APP_ENV=production`,
  `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `R007_MOCK=false`, `R007_API_BASE_URL` (the Cloud API), and
  `R007_API_SERVICE_TOKEN` from the secret store. Environment template: `BOOKING_*` in
  `007resort-infrastructure/env/cloud.env.example`.
- **Paystack**: point the Paystack dashboard webhook at the **API** (`/api/v1/payments/webhooks/paystack`),
  and the transaction callback is set per-request to this site's `/payment/return`.
- **Cloud only.** Deployed publicly in the cloud, talking to the **cloud-mode 007 Resort & Spa API**.
  The on-site server is never exposed to the internet; bookings made online reach the site
  through the API's outbound sync from the site.
- During a site internet outage, online booking and payment continue in the cloud but new
  online bookings reach the site only when sync resumes (see the internet-outage runbook).

Environment templates and runbooks live in
[prinzderick/007resort-infrastructure](https://github.com/prinzderick/007resort-infrastructure).

## Notes

Sessions/idempotency locks use the file cache by default; use Redis (`CACHE_STORE=redis`, `SESSION_DRIVER=redis`)
if you ever run more than one web instance.

`R007ApiClient` is duplicated from `007resort-admin-web` during Phase 0. It should be
extracted into a shared private Composer package once the API contract stabilises.

## Related

- Architecture, ADRs and domain docs: [prinzderick/007resort-docs](https://github.com/prinzderick/007resort-docs)
- API: [prinzderick/007resort-api](https://github.com/prinzderick/007resort-api)
- Contributing: [CONTRIBUTING.md](CONTRIBUTING.md)
