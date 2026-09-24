# 007resort-booking-web

Public property website and online booking / customer portal for the
**007 Resort & Spa Integrated Facility Operations Platform**.

> Status: **MVP + public site v2.** A full, CMS-driven public website (home, sports, pool, spa, dining, events, blog,
> gallery, about, contact, FAQ, legal) with the booking flows inside it: sports/spa/salon booking, pool tickets,
> memberships, customer accounts, booking history, QR tickets, Paystack checkout, newsletter, and a Mock API / fixture mode. Contract gaps that
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

Every word and image on the public pages comes from the **CMS module of `007resort-api`** (managed in the admin);
bookings, prices, availability and payments come from the booking API. Section headings and button labels are
template microcopy in `resources/views`.

| Area | Routes |
| --- | --- |
| Home | `/` (hero slideshow, floating booking bar, highlights, stats, events, testimonials, gallery strip, membership block, journal, FAQ, subscribe) |
| Sports / spa / dining | `/sports`, `/spa`, `/dining` (CMS page + live courts / treatments from the API), `/facilities/{slug}` |
| Booking (sports, spa, salon) | `/book/{slug}` (pick court) -> `/book/{slug}/{resource}?date=&players=` (court tabs, date strip, slot grid, sticky summary) -> hold -> `/checkout/{booking}` (hold countdown) -> Paystack -> `/payment/return` -> `/tickets/{id}` (QR) |
| Quick booking bar target | `/book-now?what=&date=&players=` (works without JS, redirects into the flows above) |
| Pool tickets | `/pool` (date + quantity steppers) -> Paystack -> `/orders/{order}/tickets` (one QR per person) |
| Memberships | `/memberships` (plans from the API) -> Paystack -> account |
| Events | `/events` (month + type chips), `/events/{slug}?start=` (add-to-calendar), `/events/{slug}.ics` |
| Journal | `/blog` (featured, category chips, search, pagination), `/blog/{slug}` (reading progress, related, share, subscribe) |
| Gallery | `/gallery?album=` (masonry + lightbox, deep link `#photo=<id>`) |
| Info | `/about`, `/contact` (form -> CMS, map, hours, WhatsApp), `/faq`, `/terms`, `/privacy`, `/cookies`, `/pages/{cms-slug}` |
| Newsletter | `POST /newsletter`, `/newsletter/confirm?token=`, `/newsletter/unsubscribe?token=` (double opt-in, GET only previews) |
| SEO | `/sitemap.xml` (CMS sitemap mapped to site URLs), `/robots.txt` |
| Aliases | `/book` -> `/sports`, `/tickets` -> `/pool`, `/membership` -> `/memberships` (links editors use in the CMS) |
| Customer | `/register`, `/verify`, `/login`, `/account`, `/account/bookings[/{id}]` with cancel/reschedule, ticket pages with SVG download and print |

Everything shown or decided (availability, holds, prices, cancel/reschedule eligibility, payment outcome)
comes from the API. This app has no booking, availability or payment logic and no database.

## CMS content

`App\Services\Cms\CmsClient` maps 1:1 to `GET/POST /api/v1/public/cms/*` (contract: `docs/CMS_API.md` in
`007resort-api`). Three implementations:

| Driver | When | Notes |
| --- | --- | --- |
| `fixtures` | `CMS_FIXTURES=true` (or `R007_MOCK=true`), tests | Sample content in `resources/cms-fixtures/*.php` in the exact API shapes; images in `public/stock/` (dev only, credits in `public/stock/CREDITS.md`) |
| `http` (default) | production | `HttpCmsClient` behind `CachedCmsClient` |
| cache | always with `http` | Fresh for `CMS_CACHE_TTL` (60 s), copy kept `CMS_STALE_TTL` (24 h) and served if the API is slow/down (stale-if-error), 15 s circuit breaker so pages do not wait on a dead API |

If the CMS was never reachable and nothing is cached, content pages answer a friendly `503` (with `Retry-After`) that
still links to booking; the home page falls back to a plain hero; booking/checkout/account keep working because they
do not depend on CMS copy. Only booking actions show the "booking unavailable" banners.

What the CMS drives: site settings (brand, contact, hours -> the "open now" pill computed in Africa/Lagos, social,
SEO defaults/OG image, announcement bar, CTA labels, footer), home sections (`HERO_SLIDE`, `HIGHLIGHT`, `STAT`,
`TESTIMONIAL`, `FAQ`, `PARTNER`, `CTA_BAND`), pages (`home`, `sports`, `pool`, `spa`, `dining`, `membership`, `events`,
`blog`, `gallery`, `about`, `contact`, `faq`, `terms`, `privacy`, `cookies` and any other slug at `/pages/{slug}`),
posts, events (recurrence expanded by the API), gallery albums (album slug `sports`/`pool`/`spa`/`dining`/`events`/`grounds`
feeds the themed photo strips), subscribers and the contact inbox. Headlines can mark the accent word with
`*asterisks*`; otherwise the last word is set in the italic accent.

### Add a section / block

1. New **home section type** from the API: render it in `resources/views/home.blade.php` from `$by['MY_TYPE']`
   (see `PageController::home()` for how data is prepared), keep the partial next to the others in
   `resources/views/partials/`.
2. New **page**: create it in the admin with a slug; `/pages/{slug}` renders it (hero, subtitle, Markdown body).
   For a route of its own add it to `routes/web.php` and to `config/site.php` `page_defaults` (title/subtitle fallback,
   FAQ topic, event category, highlight category, album slug for the strips).
3. Reveal animation: add class `reveal` (and `style="--i:N"` for stagger); rails: wrap items in `<x-rail>`;
   count-up: `data-count`.

## Front-end

Design system in `resources/css/*.css` (tokens, base, layout, components, sections, pages, booking, motion), bundled
by Vite into **one hand-written stylesheet** (no Tailwind/framework). Fraunces + Inter are self-hosted (`@fontsource`).
JS is vanilla ES modules in `resources/js/modules` (~6 KB gz): header/drawer, scroll reveal + count-up, parallax, scroll-snap
rails, hero slideshow, gallery + lightbox, forms, booking widgets (slot summary, steppers, hold countdown), subscribe
(+ optional slide-in, dismissal remembered 30 days), misc (magnetic buttons, reading bar, mobile CTA). Everything is
transform/opacity only, respects `prefers-reduced-motion`, and content is visible without JS (an inline, CSP-nonced
snippet arms reveals only when JS runs, with a 4 s failsafe). Images use the media `variants` for `srcset`, lazy loading,
the hero image is preloaded. CSP: `script-src 'self' 'nonce-...'`; images are allowed from this site and the API origin
(`CMS_MEDIA_HOSTS` for a CDN). Cross-document view transitions can be switched off with `SITE_VIEW_TRANSITIONS=false`.

```bash
npm ci && npm run build        # production assets
npm run dev                    # vite dev server
python3 scripts/stock-variants.py <dir with manifest.json>   # regenerate dev stock variants + fixtures (needs cwebp)
```

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

### Fixture CMS + real booking API

```bash
CMS_FIXTURES=true R007_API_BASE_URL=http://127.0.0.1:8080 R007_API_SERVICE_TOKEN=... php artisan serve --port=8117
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
| `CMS_FIXTURES` | `false` | Serve bundled sample CMS content instead of calling the API (`CMS_DRIVER=fixtures|http` overrides) |
| `CMS_API_PATH` | `public/cms` | Path of the CMS endpoints under `/api/v1` |
| `CMS_CACHE_TTL` / `CMS_STALE_TTL` / `CMS_TIMEOUT` | `60` / `86400` / `4` | Seconds: fresh window, stale-if-error window, HTTP timeout |
| `CMS_PAGE_MAX_AGE` | `60` | Browser `Cache-Control` max-age of public GET pages (signed-out only) |
| `CMS_MEDIA_HOSTS` | empty | Extra image origins for the CSP (CDN) |
| `CMS_SUBSCRIBE_POPUP` | `true` | Dismissible newsletter slide-in after engagement |
| `SITE_VIEW_TRANSITIONS` | `true` | Cross-document view transitions (page fade) |
| `R007_MOCK` | `false` | Mock API mode (dev/demo only; never in production) |
| `R007_MOCK_OFFLINE_FACILITIES` | empty | Mock only: facility keys shown as paused |
| `R007_BOOKING_HORIZON_DAYS` | `30` | How far ahead the date pickers go |
| `R007_DISPLAY_TIMEZONE` | `Africa/Lagos` | Timezone used when rendering dates |
| `SITE_PHONE`, `SITE_EMAIL`, `SITE_ADDRESS`, `SITE_MAP_URL` | placeholders | Fallback contact details when the CMS has none |
| `TRUSTED_PROXIES` | `127.0.0.1` | Reverse proxy addresses whose `X-Forwarded-*` are trusted |
| `SESSION_DRIVER` | `file` | Session store (holds the customer's API token server-side; `SESSION_ENCRYPT=true`) |
| `CACHE_STORE` | `file` | Cache store (also used for idempotency locks; must support locks: file/redis) |

See `config/r007.php`, `config/cms.php` and `config/site.php` (nav chrome, facility fallbacks and API-kind matching, page defaults).

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
