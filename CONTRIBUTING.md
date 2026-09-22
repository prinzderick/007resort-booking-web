# Contributing

## Branches

- `main` is **protected**: all changes land via pull request with passing CI.
- Branch names:
  - `feature/<short-description>` - new functionality
  - `fix/<short-description>` - bug fixes
  - `docs/<short-description>` - documentation only
  - `chore/<short-description>` - tooling, dependencies, CI, refactors

## Commits

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(booking): add spa appointment picker
fix(api-client): map 409 problem responses
docs: document booking flow
chore(deps): bump laravel/framework
```

## Pull requests

- Keep PRs small and focused; fill in the PR template checklist.
- CI must be green (Pint, tests, asset build, gitleaks).
- At least one review before merging to `main`.

## Engineering rules

1. **No business rules in PHP.** Validation that matters, pricing, stock, payments,
   refunds, tickets, bookings, availability, memberships and order state live in the Otueke API. PHP
   renders and forwards; it calls `/api/v1/...` through `OtuekeApiClient`.
2. **No business tables.** Do not add migrations or Eloquent models for business data.
   A read-only reporting connection requires an approved ADR.
3. **Money** is received from the API as **decimal strings**. Never use float arithmetic
   (no `(float)`, `+`, `*` on amounts). Display as received or use `bcmath` / a money
   library if a computation is truly presentation-only.
4. **Time.** Timestamps from the API are UTC. Convert to local time (`config('otueke.display_timezone')`)
   only when displaying; send UTC back to the API.
5. **Idempotency.** Mutating calls always carry an `Idempotency-Key`; when retrying the
   same user action, reuse the same key.
6. **Bookings and availability** always come from the API booking engine (shared with
   Reception). Never compute availability or cancellation/reschedule eligibility locally.
7. **Payment callbacks/webhooks** are handled by the API, never by this app.
8. **Customer data** is fetched from the API per request; do not persist it locally.
9. **No secrets.** Never commit `.env`, keys, tokens, certificates or real customer data.
   Configuration comes from the environment / secret store.

## Local checks

```bash
vendor/bin/pint          # fix style
vendor/bin/pint --test   # verify style
php artisan test
```
