# Security Notes

This file records security-relevant decisions made during the Phase 9 audit, so
future changes don't accidentally reverse them.

## Mass assignment

`User::$fillable` deliberately excludes `is_super_admin`, `client_id`,
`business_id`, and `branch_id`. These fields decide platform-wide privilege
and tenant identity — no HTTP form should ever be able to set them, now or in
the future. Trusted server-side code (seeders, future admin services) sets
them via `forceFill()` instead of relying on mass assignment. See
`SuperAdminSeeder` and `DemoClientSeeder` for the pattern.

If you add a "create staff user" admin screen later: compute `client_id`/
`business_id`/`branch_id` from the authenticated actor's own context (or an
explicit, authorized admin action), and set them via `forceFill()` or direct
attribute assignment — never by adding them back to `$fillable`.

## Tenant isolation

Every tenant-scoped model (`Business`, `Branch`, `Device`, `Subscription`,
`ActivityLog`) is checked against `client_id` derived from the
**authenticated user**, never from a request parameter. Policies
(`BusinessPolicy`, `BranchPolicy`, `DevicePolicy`, `SubscriptionPolicy`,
`ActivityLogPolicy`) are the single source of truth for this comparison —
route middleware (`permission:*`) is a first line of defense, not the only
one.

## Rate limiting

- `POST /login` — LoginRequest's own 5-attempts-per-email+IP limiter, plus a
  10-per-minute-per-IP route-level ceiling (stops spraying many different
  emails from one IP).
- `POST /forgot-password` — 5 per minute per IP.
- `POST /reset-password` — 10 per minute per IP.
- `POST /api/v1/auth/login` — 10 per minute per IP.
- All other `/api/*` routes inherit Laravel's default `api` middleware group
  (`throttle:api`).

## Password policy

`Password::defaults()` (set in `AppServiceProvider::boot()`) requires at
least 10 characters, mixed case, numbers, and symbols. `uncompromised()` is
deliberately **not** enabled — it calls the HaveIBeenPwned API over HTTP
during validation, which would make password validation depend on outbound
internet access. Recommended to enable in production once that dependency is
acceptable.

## File uploads

Business logo uploads (`StoreBusinessRequest`/`UpdateBusinessRequest`) are
restricted to `image` (jpg/jpeg/png/gif/bmp/webp — Laravel's `image` rule
excludes SVG by default, avoiding SVG-borne script injection), capped at
2MB, and capped at 4000x4000px to block decompression-bomb-style uploads.

## Subscription enforcement

`SubscriptionEnforcementService` is the single place that answers "is this
client/subscription actually active" — see its docblock. `EnsureSubscriptionActive`
(`subscription.active` middleware alias) applies this to the entire `/client`
route group. Super Admins are always exempt (they manage subscriptions, they
aren't bound by one).

## API authentication

Token-based (Laravel Sanctum personal access tokens), not cookie/session —
appropriate for React/Electron/mobile clients that aren't served from the
same origin as the Laravel app. No CSRF exposure on these routes since
there's no cookie-based session involved.

## Known remaining risks (see the Phase 9 deliverable for the full list)

- `SESSION_SECURE_COOKIE` is unset locally (cookies sent over HTTP) — must be
  set to `true` before any production deploy over HTTPS.
- No `Model::shouldBeStrict()` — a typo'd non-fillable key passed to
  `create()`/`update()` is silently discarded rather than throwing. Worth
  enabling in local/testing environments to catch this class of bug earlier.
- No account lockout after repeated failed logins beyond rate limiting (no
  temporary account lock, no notification to the user of failed attempts).
- Password reset tokens are not themselves rate-limited per-token (brute
  force is impractical given their length/hashing, but no explicit limiter
  exists on `GET /reset-password/{token}`).
