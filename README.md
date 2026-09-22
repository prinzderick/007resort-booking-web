# otueke-booking-web

Public property website and online booking / customer portal for the
**Otueke Integrated Facility Operations Platform**.

> Status: **Phase 0 - scaffolding only.** No business features yet.

## Purpose

`otueke-booking-web` is the customer-facing site:

- Facility pages and services
- Sports court/pitch booking, spa appointments, pool day tickets
- Online payment and QR confirmation
- Customer portal: booking history, cancel / reschedule (subject to the API's rules)

It is a Laravel **UI / backend-for-frontend (BFF)** over the **Otueke API** (ASP.NET Core),
which is the single "brain" of the platform and the **only** owner of the MySQL schema.

## Architecture rules

- **PHP never mutates business data directly - it calls the API.** Bookings, tickets,
  payments, refunds and memberships are created/changed only via `/api/v1/...` using
  `App\Services\OtuekeApi\OtuekeApiClient`.
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
- **Errors** use RFC 7807 problem details, mapped to `OtuekeApiException`.
- **Money** is received as decimal strings - never float arithmetic.
- **Time.** The API stores/returns UTC; convert to local time for display only.

### Reporting hierarchy (context)

Online sales are attributed by the API like any other channel:

```
Property -> Facility -> Operating Point -> Terminal -> Staff -> Transaction
```

(online bookings use a dedicated "online" operating point / terminal in the API).

## Requirements

- PHP 8.4+ with `mbstring`, `intl`, `bcmath`, `curl`, `dom`, `fileinfo`, `openssl`, `xml`, `zip`
- Composer 2
- Node.js 24 + npm
- A reachable Otueke API instance (cloud-mode API in production)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci && npm run build   # or `npm run dev`
```

Set `OTUEKE_API_BASE_URL` in `.env`.

## Run

```bash
php artisan serve --port=8001   # http://localhost:8001
```

- `GET /` - placeholder home page (static facility list)
- `GET /health` - `{"status":"ok","service":"otueke-booking-web"}`

## Test

```bash
vendor/bin/pint --test
php artisan test
```

CI (`.github/workflows/ci.yml`) runs Pint, the test suite, a front-end build and a gitleaks
secret scan on every push/PR.

## Configuration

All configuration comes from the environment (see `.env.example` - placeholders only).

| Variable | Default | Description |
| --- | --- | --- |
| `OTUEKE_API_BASE_URL` | `http://127.0.0.1:5080` | Base URL of the Otueke API |
| `OTUEKE_API_PREFIX` | `/api/v1` | Versioned API path prefix |
| `OTUEKE_API_TIMEOUT` | `10` | Request timeout (seconds) |
| `OTUEKE_API_CONNECT_TIMEOUT` | `3` | Connect timeout (seconds) |
| `OTUEKE_API_CLIENT_ID` | `otueke-booking-web` | Public client identifier registered in the API (not a secret) |
| `OTUEKE_DISPLAY_TIMEZONE` | `Africa/Lagos` | Timezone used when rendering dates |
| `SESSION_DRIVER` | `file` | Session store (holds the customer's API token server-side) |
| `CACHE_STORE` | `file` | Cache store |
| `QUEUE_CONNECTION` | `sync` | Queue driver |

See `config/otueke.php`.

## Deployment

- **Cloud only.** Deployed publicly in the cloud, talking to the **cloud-mode Otueke API**.
  The on-site server is never exposed to the internet; bookings made online reach the site
  through the API's outbound sync from the site.
- During a site internet outage, online booking and payment continue in the cloud but new
  online bookings reach the site only when sync resumes (see the internet-outage runbook).

Environment templates and runbooks live in
[prinzderick/otueke-infrastructure](https://github.com/prinzderick/otueke-infrastructure).

## Notes

`OtuekeApiClient` is duplicated from `otueke-admin-web` during Phase 0. It should be
extracted into a shared private Composer package once the API contract stabilises.

## Related

- Architecture, ADRs and domain docs: [prinzderick/otueke-docs](https://github.com/prinzderick/otueke-docs)
- API: [prinzderick/otueke-api](https://github.com/prinzderick/otueke-api)
- Contributing: [CONTRIBUTING.md](CONTRIBUTING.md)
