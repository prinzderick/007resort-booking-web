## Summary

<!-- What does this PR change and why? Link the issue / ADR. -->

## Type of change

- [ ] feat
- [ ] fix
- [ ] docs
- [ ] chore / refactor / ci

## Checklist

- [ ] Title follows Conventional Commits (e.g. `feat(booking): add pool ticket checkout`)
- [ ] No business rules or business-data writes in PHP - all business operations call the 007 Resort & Spa API (`/api/v1/...`)
- [ ] No new migrations / Eloquent models for business data (API owns the schema)
- [ ] Money is handled as decimal strings from the API - no float arithmetic
- [ ] Timestamps from the API treated as UTC; converted to local time for display only
- [ ] Mutating API calls send an `Idempotency-Key` (via `R007ApiClient`)
- [ ] No secrets, tokens, real customer data or `.env` files committed
- [ ] `vendor/bin/pint --test` and `php artisan test` pass locally
- [ ] Docs / README updated where relevant

## Screenshots / notes for reviewers

<!-- Optional -->
