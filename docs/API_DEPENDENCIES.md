# API dependencies of 007resort-booking-web

> **Status: BUILT.** Every PROPOSED endpoint below now exists in `007resort-api` (PR prinzderick/007resort-api#13, module
> `app/Domain/Customer`, see its `docs/CUSTOMER_PUBLIC_API.md` and `docs/openapi/v1.yaml`), and this site was driven end to end
> against it with `R007_MOCK=false` (register -> verify -> hold a tennis court -> Paystack (dev sandbox) -> QR; pool tickets; membership).
> Differences the site adapted to: `GET /public/site` facilities carry `parentId` (Sports Arena > Lawn Tennis/Football/Basketball) and
> a page can span several API facilities (Male + Female salon) - `SiteService` now exposes `ids` and `BookingService::resources()`
> merges them; `facilityId` for pool tickets is the facility the ticket grants access to (the API resolves the selling facility);
> register answers a uniform 201 (no account enumeration); tokens: customer `r7c_`, service `r7s_` (`R007_API_SERVICE_TOKEN`).

The site consumes the contract at `007resort-docs`/`api/openapi/v1.yaml` (Cloud node). The contract did
**not yet contain** the customer-facing surface the website needs, so the endpoints below are
**PROPOSED** and isolated behind `app/Services/Online/*` (one file to change per endpoint). The Mock API mode
(`MockR007ApiClient`) and all `Http::fake` tests use exactly these shapes. Until the API team lands (or
renames) them, the site works fully in mock mode and degrades calmly against a real API that lacks them.

## Existing contract endpoints used

`GET /bookings/resources`, `GET /bookings/resources/{id}/availability`, `POST /bookings/hold`,
`GET /bookings/{id}`, `POST /bookings/{id}/confirm|cancel|reschedule` (with `If-Match: "<rowVersion>"`),
`POST /payments/paystack/initialize`, `GET /payments/paystack/verify/{reference}`, `GET /entitlements/{id}`,
`GET /catalog/products?facilityId&filter[kind]=TICKET`, `GET /memberships/plans`, `POST /memberships`,
`GET /memberships/{id}`. All mutating calls send `Idempotency-Key`.

## PROPOSED / not yet in the contract

| # | Endpoint | Purpose | Shape assumed |
| - | --- | --- | --- |
| 1 | `GET /public/site` | Contact, opening hours and per-facility online state for the public pages. Unauthenticated or service-token. | `{contact:{phone,email,address,mapUrl}, openingHours, facilities:[{id,code,kind,name,description,openingHours,phone,onlineBookable:bool,onlineNotice}]}`. Facilities are linked to pages by `kind`/`code` prefix (`config/site.php`). |
| 2 | `POST /customer/auth/register` `login` `verify` `verify/resend` `logout`, `GET /customer/me` | Customer accounts (separate identity space, architecture/17). | login/verify return `{accessToken, customer:{id,name,email,phone,emailVerified}}`; an unverified login is `403` with "verify" in `detail`. |
| 3 | `GET /customer/bookings` | Booking history of the signed-in customer (cursor page of `Booking`). | contract `Booking` + optional `policy:{canCancel,canReschedule,cancelBy,refundAmount,note}` (also wanted on `GET /bookings/{id}`). |
| 4 | `GET /customer/entitlements?bookingId\|orderId` | Individual QR tickets of a booking / pool order. | page of `Entitlement`. |
| 5 | `POST /public/ticket-orders` (+ `GET /customer/orders/{id}`) | Create an **unpaid** pool ticket order priced by the server; paid via `paystack/initialize {orderIds}`; entitlements issued on capture, one per person. | body `{facilityId, visitDate, lines:[{productId,quantity}], customer}` -> `{id,total,...}`. |
| 6 | `GET /customer/memberships` | Memberships of the signed-in customer. | page of `Membership`. |
| 7 | Additive fields | `BookableResource.onlineAvailable/onlineNotice`; ticket `Product.ticketCategory: ADULT\|CHILD`. | optional. |

## Behavioural assumptions to confirm

- The website uses the customer's bearer token for holds, payments and reads; the API must enforce ownership
  (403/404 for other customers' bookings/tickets/payments). The site maps both to a 404 page.
- A service credential (`R007_API_SERVICE_TOKEN`) with read-only public scope exists for resources,
  availability, products and plans when nobody is signed in.
- `PaymentCaptured{subjectType:BOOKING}` normally confirms the booking on the API; the site calls
  `POST /bookings/{id}/confirm {paystackReference}` only as a fallback and tolerates `409`/`412`.
- Strategy C (site stale) is signalled as `409 capability_disabled` on availability/hold; `503` and
  `offline_not_allowed` are treated the same way (notice for that item only).
- `If-Match` uses the quoted `rowVersion` (`"3"`).
- `POST /memberships` without tenders creates a `PENDING_PAYMENT` membership that Paystack capture activates.
- Paystack `callbackUrl` in `initialize` is honoured per request.
- Not built (needs API/native work): Apple/Google Wallet passes (the ticket page is an installable-friendly,
  printable/downloadable QR page instead), email/SMS delivery of tickets (API-side), Turnstile/reCAPTCHA
  (honeypot + fill-time guard only).

## CMS module (public site v2)

The public website reads all of its content from `GET /api/v1/public/cms/*` and writes newsletter subscriptions and
contact messages through `POST /api/v1/public/cms/{subscribers,subscribers/confirm/{token},subscribers/unsubscribe/{token},contact}`
(contract: `docs/CMS_API.md` in `007resort-api`, PR prinzderick/007resort-api#19). Endpoints used: `site`, `home`, `pages`, `pages/{slug}`,
`posts`, `posts/{slug}`, `post-categories`, `events`, `events/{slug}`, `gallery/albums`, `gallery/albums/{slug}`, `sitemap`.
The service token needs scope `public.read` (and may POST to the four public write endpoints). The visitor's IP is
forwarded as `X-Client-IP` so per-visitor rate limits work. Site email links point at `CMS_WEB_URL` + `/newsletter/confirm?token=`
and `/newsletter/unsubscribe?token=` (set `CMS_WEB_URL` on the API to this site's origin).
