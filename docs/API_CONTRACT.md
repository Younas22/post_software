# Post Software — API Contract (v1)

This is the authoritative reference for every `/api/v1/*` endpoint currently
implemented. It is meant to let a Next.js (web) or Electron (desktop POS)
client be built against this backend without guessing behavior.

**Companion, auto-generated reference:** an interactive OpenAPI/Swagger
document is served live at `/docs/api` (JSON at `/docs/api.json`), generated
by [Scramble](https://scramble.dedoc.co) directly from the actual routes,
Form Request validation rules, and controller return types — it is always
in sync with the code, and is the better source for exact request/response
*shapes* (every field, every type). It's restricted to the local
environment by default; in production only an authenticated Super Admin can
open it (see `AppServiceProvider`'s `viewApiDocs` gate).

**This document** covers what the generated schema *can't* express on its
own: which permission and subscription feature each endpoint requires, the
tenant-isolation rules, and the idempotency contract — the business rules a
frontend developer needs, not just the field list.

---

## 1. Conventions

### 1.1 Base URL & versioning

All endpoints are prefixed `/api/v1`. There is no unversioned API; a future
breaking change gets `/api/v2` alongside it, not an in-place change.

### 1.2 Authentication

Two independent auth mechanisms exist in this codebase; only one — Sanctum
tokens — applies to this API:

- **Sanctum bearer tokens** (`Authorization: Bearer <token>`) — used by every
  route below except `POST /auth/login` and `GET /health`. Obtained from
  `POST /auth/login`. A token has no expiry by default and is revoked via
  `POST /auth/logout`.
- Session-based auth (the `/admin` and `/client` Blade panels) is a
  **separate, unrelated surface** — not part of this API and not usable to
  call it.
- **Device identity** — a registered POS terminal's own credential, distinct
  from the logged-in cashier's token — is a *second*, independent Sanctum
  token type, issued via `POST /devices/register` (§5.9). The two coexist:
  registering/using a device credential never affects a cashier's own
  session or token.

A missing/invalid token on a protected route returns `401`.

### 1.3 Response envelope

Every response — success or failure — is one of exactly two shapes,
produced by `App\Support\ApiResponse` and, for uncaught exceptions,
`App\Support\ApiExceptionRenderer`. **Never** expect an HTML page, a raw
Laravel validation array, or a stack trace from any `/api/*` route.

Success (single resource):

```json
{ "success": true, "message": "Product retrieved successfully.", "data": { "...": "..." } }
```

Success (paginated collection) — `meta` is only present here, built from
Laravel's paginator:

```json
{
  "success": true,
  "message": "Products retrieved successfully.",
  "data": [ { "...": "..." } ],
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3, "from": 1, "to": 15 }
}
```

Error:

```json
{ "success": false, "message": "The given data was invalid.", "errors": { "email": ["..."] } }
```

`errors` is `{}` (an object) for validation failures and `null` for every
other error type (permission, not-found, conflict, server error).

### 1.4 HTTP status codes used

| Code | Meaning here |
|---|---|
| 200 | Success (read or update) |
| 201 | Resource created |
| 401 | Missing/invalid token |
| 403 | Authenticated, but not permitted — wrong permission, wrong tenant/branch, inactive subscription, disabled feature |
| 404 | Route doesn't exist, or the model id doesn't resolve (these are indistinguishable by design — no path/existence hints leak) |
| 405 | Wrong HTTP verb for an existing route |
| 409 | Conflict with current state: insufficient stock, idempotency key reused with different data, invalid status transition (e.g. receiving an already-received purchase), excessive return |
| 422 | Request body/query failed validation |
| 429 | Rate limited (`/auth/login` is throttled 10/min) |
| 500 | Unhandled server error — message is always the generic `"Server error. Please try again later."`, regardless of the real cause; the real error is logged server-side only |
| 501 | Feature not implemented (`/reports/expenses` only — see §5.10) |

### 1.5 Tenant isolation — the rule every endpoint follows

**No endpoint ever trusts a `client_id`, `business_id`, `branch_id`, or
`user_id` supplied in a request body or query string as authorization.**
Every one of those is resolved from (a) the authenticated user's own
`client_id`/`business_id`/`branch_id` columns when set, or (b) validated
against the actor's own client when the actor is allowed to name one
explicitly (a Client Owner managing several businesses, or a Super Admin
acting on behalf of a client). A request field that merely *looks* like a
tenant id (e.g. `business_id` in a create-product body) is either ignored
outright or independently re-verified — never taken as truth.

Concretely:

- An actor with a fixed `business_id` (Business Admin, Manager, Cashier) can
  only ever act on that one business, regardless of what a request body says.
- An actor with a fixed `branch_id` is further restricted to that one
  branch for branch-scoped resources (Inventory, Sales, Purchases, Cash
  Sessions, Returns).
- A Client Owner (typically `business_id = null`) may act on any business
  belonging to their own `client_id`, which a request may specify — but that
  business is always re-checked against the actor's `client_id` server-side.
- A Super Admin bypasses tenant checks entirely (platform operator).

### 1.6 Permissions

Every endpoint requires one or more permission slugs (`resource.action`),
checked via `User::hasPermission()` against the actor's assigned role(s), or
bypassed entirely for `is_super_admin`. The full role→permission map is
database-driven (`roles`, `permissions`, `role_permission` tables) and only
*seeded* with the defaults below — it is editable without a code change.

### 1.7 Subscription & feature gating

Sales and Reports additionally require:

1. `SubscriptionEnforcementService::hasPlatformAccess()` — the client's
   account is active AND their subscription is `active` or `trial`.
2. `FeatureAccessService::isEnabled()` — the specific feature slug (`pos`,
   `reports`, etc.) is enabled for the business, via its package or an
   explicit per-business override.

Other modules (Products, Inventory, Customers, Suppliers, Purchases) do not
yet enforce feature gating — only permissions and tenant scope. This is a
known gap, not an oversight; extending feature-gating to them is
straightforward (`FeatureAccessService` already exists) but wasn't in scope
for the tasks that built them.

### 1.8 Pagination

Every list endpoint accepts `?per_page=` (default 15) and returns a
standard Laravel paginator shape under `meta` (see §1.3). `?page=` selects
the page.

### 1.9 Idempotency

Documented in full in §6. In short: any endpoint that creates a financial
transaction and could plausibly be retried by an offline client (currently:
**Sales** only) requires a `client_transaction_id` in the request body.
Submitting the same id with the same business twice returns the *original*
result without creating a duplicate row; submitting it with a *different*
payload is rejected with `409`.

---

## 2. Auth

| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| POST | `/auth/login` | none (rate-limited 10/min) | — |
| POST | `/auth/logout` | token | — |
| GET | `/user` | token | — |

### `POST /auth/login`

Request body:

```json
{ "email": "owner@example.com", "password": "secret", "device_name": "iPhone 15" }
```

`device_name` is a free-text label for the issued token (shown in a future
token-management UI) — it is **not** the device-identity feature (§7).

Response `200`:

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "token": "1|abcdef...",
    "user": { "id": 5, "name": "...", "email": "...", "is_super_admin": false, "status": "active", "client_id": 2, "business_id": 3, "branch_id": null }
  }
}
```

Errors: `422` (validation, or wrong credentials — both surface as a
generic `email` error to avoid leaking which part was wrong), `422` also
for a deactivated account (`status = inactive`), `429` if throttled.

### `POST /auth/logout`

Revokes the bearer token used for the request (`currentAccessToken()`).
Response `200`, `data: null`.

### `GET /user`

Returns the same `user` object shown above, wrapped as
`{ "user": {...} }`.

---

## 3. Config

| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| GET | `/config` | token | — |

Bootstrap payload for a client app once it has a token: the actor's
business (or `null` for a Super Admin / businessless user), resolved
settings, and which feature slugs are actually enabled for that business
(package + any per-business override, via `FeatureAccessService`).

```json
{
  "business": { "id": 3, "name": "Acme Mart", "currency": "USD", "timezone": "UTC" },
  "settings": { "tax_rate": "10", "...": "..." },
  "features": ["pos", "customers", "reports"]
}
```

---

## 4. Employees (staff management)

Base: `/employees`. A Client Owner/Business Admin manages their own team
here; a Super Admin may act on behalf of any client by naming one
explicitly.

| Method | Endpoint | Permission | Notes |
|---|---|---|---|
| GET | `/employees` | `users.view` | `?search=`, `?client_id=` (Super Admin only), pagination |
| GET | `/employees/{employee}` | `users.view` | |
| POST | `/employees` | `users.create` | subject to the client's `max_users` package limit |
| PUT/PATCH | `/employees/{employee}` | `users.update` | |
| PATCH | `/employees/{employee}/activate` | `users.update` | re-checks the user limit |
| PATCH | `/employees/{employee}/deactivate` | `users.update` | |

`POST /employees` body:

```json
{
  "name": "New Cashier", "email": "cashier@example.com", "password": "Password-123!",
  "role": "cashier", "business_id": 3, "branch_id": 7
}
```

`role` must be an existing role slug **other than** `super-admin` or
`client-owner` — those can't be granted through this endpoint. `client_id`
is accepted but only meaningful for a Super Admin caller; for anyone else
it's silently ignored in favor of their own `client_id`.

Response `201` — an explicit whitelist, never a raw model dump (never the
password hash, regardless of `$hidden`):

```json
{
  "id": 12, "name": "...", "email": "...", "status": "active",
  "client_id": 2, "business_id": 3, "branch_id": 7,
  "business": { "id": 3, "name": "..." }, "branch": { "id": 7, "name": "..." },
  "roles": ["cashier"], "created_at": "..."
}
```

Errors: `403` if the client is at its user-seat limit (message states the
limit); `422` for a forbidden role, duplicate email, or weak password.

---

## 5. Core POS resources

### 5.1 Categories / Brands

Base: `/categories`, `/brands`. Identical shape for both. Business-scoped
(not client-wide — two businesses under one client each have their own
list). Governed by the shared `products.*` permission set (categories and
brands are catalog sub-resources, not separately permissioned).

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/categories`, `/brands` | `products.view` — `?search=`, `?business_id=`, `?status=` |
| GET | `/categories/{id}`, `/brands/{id}` | `products.view` |
| POST | `/categories`, `/brands` | `products.create` |
| PUT/PATCH | `.../{id}` | `products.update` |
| DELETE | `.../{id}` | `products.delete` — soft delete, not a hard delete |

Body: `{ "name": "Beverages", "status": "active" }` — `slug` auto-derives
from `name` if omitted. `business_id` required only for an actor with no
fixed business of their own (a multi-business Client Owner); otherwise
ignored in favor of the actor's own.

### 5.2 Products

Base: `/products`. Deliberately generic — **no pharmacy/electronics/variant
columns exist on this table.** IMEI, batch, expiry, warranty, etc. are
meant to attach later via the existing `custom_fields` mechanism
(`Product` already uses `HasCustomFields`), not as dedicated columns.

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/products` | `products.view` |
| GET | `/products/{product}` | `products.view` |
| POST | `/products` | `products.create` |
| PUT/PATCH | `/products/{product}` | `products.update` |
| DELETE | `/products/{product}` | `products.delete` (soft delete) |
| GET | `/products/{product}/movements` | `inventory.view` — see §5.3 |

List filters: `?search=` (name/SKU/barcode), `?category_id=`, `?brand_id=`,
`?status=`, `?sku=`, `?barcode=`, `?sort=` (`name`\|`sku`\|`sale_price`\|
`purchase_price`\|`created_at`), `?direction=` (`asc`\|`desc`).

`POST /products` body:

```json
{
  "category_id": 4, "brand_id": 2, "name": "Cola 500ml", "sku": "SKU-100",
  "barcode": "8901234", "purchase_price": 10, "sale_price": 15,
  "tax_rate": 10, "unit": "pcs", "minimum_stock": 5, "status": "active",
  "description": "...", "image": "(multipart file, optional)"
}
```

`sku` and `barcode` are unique **per business**, not globally — the same
SKU is allowed across two different businesses. `category_id`/`brand_id`
must belong to the same business as the product. `tax_rate` is a nullable
percentage; `null` means "no product-specific rate" (see §5.6 for how it's
actually applied at sale time).

Errors: `422` for a duplicate SKU/barcode within the business, or a
category/brand belonging to a different business.

### 5.3 Inventory

Base: `/inventory`. Deliberately has **no single `/inventory/{id}` show
route** — a stock balance is keyed by (branch, product), not one id; read
it via the indexed list filters instead.

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/inventory` | `inventory.view` — `?branch_id=`, `?product_id=` |
| GET | `/inventory/low-stock` | `inventory.view` — items where `quantity <= minimum_stock` |
| GET | `/products/{product}/movements` | `inventory.view` — the full audit ledger for one product; a branch-scoped actor only sees their own branch's movements |
| POST | `/inventory/adjust` | `inventory.adjust` |

`POST /inventory/adjust` body: `{ "branch_id": 7, "product_id": 42, "quantity": 15, "notes": "Opening stock" }`.
`quantity` is **signed** — positive increases stock, negative decreases it,
and can never be `0`. A decrease that would take stock below zero is
rejected with `409` — this endpoint (and every other stock-affecting
operation platform-wide) goes through the single `StockService`, which
takes a row lock before checking/writing, so two simultaneous adjustments
for the same product+branch are serialized by the database rather than
racing in PHP.

Every stock change of any kind — manual adjustment, purchase, sale,
return — is recorded as an immutable row in the movements ledger
(`type`, signed `quantity`, `balance_after`, and a polymorphic `reference`
back to whatever caused it).

### 5.4 Customers / Suppliers

Base: `/customers`, `/suppliers`. Identical shape. Separately permissioned
(`customers.*` / `suppliers.*`) since, unlike categories/brands, a cashier
legitimately needs `customers.create` (registering a walk-in) without any
supplier access at all.

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/customers`, `/suppliers` | `customers.view` / `suppliers.view` — `?search=`, `?status=` |
| GET | `.../{id}` | `customers.view` / `suppliers.view` |
| POST | `/customers`, `/suppliers` | `customers.create` / `suppliers.create` |
| PUT/PATCH | `.../{id}` | `customers.update` / `suppliers.update` |
| DELETE | `.../{id}` | `customers.delete` / `suppliers.delete` (soft delete) |

Body: `{ "name": "...", "phone": "...", "email": "...", "address": "...", "notes": "...", "status": "active" }`.
No transaction-history fields live here — a customer's purchase history is
derived from Sales/Returns referencing them; a supplier's from Purchases.

### 5.5 Purchases

Base: `/purchases`. **There is no `DELETE` route — a purchase is voided via
`cancel`, never deleted** (it's financial history).

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/purchases` | `purchases.view` — `?branch_id=`, `?supplier_id=`, `?status=`, `?date_from=`, `?date_to=` |
| GET | `/purchases/{purchase}` | `purchases.view` |
| POST | `/purchases` | `purchases.create` |
| PUT/PATCH | `/purchases/{purchase}` | `purchases.update` — **only while `status = pending`**; only `invoice_reference`/`purchase_date`/`notes` are editable (quantities/prices are fixed at creation — correcting those means cancelling and re-entering) |
| PATCH | `/purchases/{purchase}/receive` | `purchases.update` — transitions `pending → received`, applying stock at that point |
| PATCH | `/purchases/{purchase}/cancel` | `purchases.cancel` — from either state; if it was `received`, reverses the stock it had added |

`POST /purchases` body:

```json
{
  "branch_id": 7, "supplier_id": 3, "invoice_reference": "INV-9981",
  "purchase_date": "2026-08-20", "status": "received", "notes": "...",
  "items": [
    { "product_id": 42, "quantity": 10, "purchase_price": 5, "discount": 2, "tax": 3 }
  ]
}
```

`status` defaults to `received` (stock applied immediately); pass
`"pending"` to record the purchase without touching stock until a later
`/receive` call. **`subtotal`/`discount`/`tax`/`total` are never accepted
in the request at all — they're computed server-side from the `items`
array** (`subtotal = Σ quantity×purchase_price`, `total = subtotal −
discount + tax`).

Errors: `422` for a branch/supplier/product that doesn't belong to the
actor's tenant/business; `409` for receiving an already-received purchase,
or cancelling an already-cancelled one.

### 5.6 Sales (POS)

Base: `/sales`. This is the platform's core transaction endpoint — see
the full flow and idempotency contract in §6 before integrating it.

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/sales` | `sales.view` — `?branch_id=`, `?cashier_id=`, `?customer_id=`, `?payment_method_id=`, `?status=`, `?date_from=`, `?date_to=` |
| GET | `/sales/{sale}` | `sales.view` |
| GET | `/sales/{sale}/receipt` | `sales.view` — see §5.9 |
| POST | `/sales` | `sales.create` + active subscription + `pos` feature enabled |

`POST /sales` body:

```json
{
  "branch_id": 7, "customer_id": null,
  "client_transaction_id": "b1e6c1f0-...-uuid-from-the-device",
  "items": [ { "product_id": 42, "quantity": 2, "discount": 0 } ],
  "payments": [ { "payment_method_id": 1, "amount": 44, "reference": null } ]
}
```

**Nothing about price, tax, or the total is ever read from this body.**
There is no `unit_price`/`price`/`tax` field on a line item at all — the
unit price is always `Product.sale_price` at the moment of sale, and tax is
always derived from `Product.tax_rate`, both fetched server-side. A line's
optional `discount` is the one money figure a cashier can legitimately
apply. `payment_method_id` comes from `GET /config` → not shown there
directly, but is one of the platform's seeded methods (`cash`, `card`,
`bank`, `other` by default — database-driven, a business can add its own).

Response `201` — a full receipt payload (see §5.9's shape) rather than a
bare model, so the POS can render/print immediately without a second call.

Errors: `409` insufficient stock (message states available vs. requested)
or a reused `client_transaction_id` with different data; `422` for an
invalid branch/customer/product/payment method, or a missing
`client_transaction_id` (it's required, not optional, for this endpoint);
`403` for missing permission, inactive subscription, or the `pos` feature
being disabled for the business.

### 5.7 Returns

Base: created only against a specific sale — `POST /sales/{sale}/returns`
— never as a bare top-level resource, and `GET /returns` / `GET
/returns/{return}` for reading. **The original sale is never edited or
deleted to represent a return** — it's always a separate record pointing
back at it.

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/returns` | `returns.view` — `?sale_id=`, `?branch_id=` |
| GET | `/returns/{return}` | `returns.view` |
| POST | `/sales/{sale}/returns` | `returns.process` |

Body:

```json
{ "reason": "Customer changed mind", "items": [ { "sale_item_id": 91, "quantity": 2 } ] }
```

Return items reference the specific **sale item** (not just the product),
so the returnable-quantity check is exact even if a sale rang the same
product twice across two lines. `refund_amount` is a proportional share of
that line's original `line_total` (already net of its discount/tax) — it
is **not** recomputed from current product prices, so a return always
refunds exactly what the customer paid, even if the price has since
changed. Restoring inventory happens via the same `StockService` as
everything else (`type: return`).

Errors: `422` if the requested quantity would exceed what remains
returnable for that line (a second return can't exceed what the first one
left); `403` for wrong branch/tenant, same rules as Sales.

### 5.8 Cash Sessions

Base: `/cash-sessions`. All math (expected cash, difference) is computed
server-side from the session's own recorded movements and the actual
`cash`-method sales rung up during it — never from a client-supplied total.

| Method | Endpoint | Permission | Notes |
|---|---|---|---|
| GET | `/cash-sessions` | `cash_sessions.view` | full history across a scope; `?branch_id=`, `?user_id=`, `?status=` |
| GET | `/cash-sessions/current?branch_id=` | none beyond auth* | the actor's own open session for that branch, or `null` |
| POST | `/cash-sessions/open` | none beyond auth* | |
| GET | `/cash-sessions/{id}` | none beyond auth* | the session's own cashier, or `cash_sessions.view` |
| POST | `/cash-sessions/{id}/cash-in` | none beyond auth* | |
| POST | `/cash-sessions/{id}/cash-out` | none beyond auth* | |
| POST | `/cash-sessions/{id}/close` | none beyond auth* | |

\* These routes carry no `permission:*` route middleware because a single
permission can't express "the session's own cashier, OR a manager/admin
with `cash_sessions.manage`" — that OR is enforced entirely inside
`CashSessionPolicy`, checked per-request in the controller. Effectively: a
cashier needs `cash_sessions.operate` to open/operate their *own* session;
a manager/admin needs `cash_sessions.manage` to act on *anyone's*.

`POST /cash-sessions/open`: `{ "branch_id": 7, "opening_cash": 100, "notes": "..." }`.
**A cashier can have at most one open session per branch — enforced by a
real database unique constraint** (not just an application check), so two
simultaneous "open" requests can't both succeed even under a genuine race.
A duplicate attempt returns `409`.

`POST .../cash-in` / `.../cash-out`: `{ "amount": 20, "reason": "..." }`.

`POST .../close`: `{ "actual_cash": 150 }`. Response includes
`expected_cash` (`opening + cash sales during the session + cash_in −
cash_out`, all server-computed) and `difference` (`actual − expected`,
negative for a shortfall).

### 5.9 Devices (Electron/POS terminal authentication)

Base: `/devices`. A **device credential is a second, independent Sanctum
token type** — issued to a `Device` model, not a `User` — that coexists
with the logged-in cashier's own token rather than replacing it. Both ride
the same `auth:sanctum` guard (Sanctum's token table is polymorphic), but
resolve to different model types; application code (and the `device.active`
middleware below) distinguishes them explicitly.

| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| POST | `/devices/register` | user token | `devices.manage` |
| GET | `/devices/current` | **device** token | — (gated by the `device.active` middleware instead) |

**Registration is performed by an already-authenticated privileged user**
(the admin setting up a new terminal) — not by Electron acting anonymously.
This is what lets the server validate business/subscription/device-limit
state before issuing anything, and is what "never trust client-supplied
device identity" means in practice: the request supplies only a target
`branch_id` and a human-chosen `name`/`platform`; **`device_identifier` is
always generated server-side** (a UUID) and any client-supplied value for
it is simply not an accepted field.

`POST /devices/register` body: `{ "branch_id": 7, "name": "Front Counter POS", "platform": "windows" }`
(`platform` ∈ `windows`\|`mac`\|`linux`\|`web`\|`android`\|`ios`).

Response `201`:

```json
{
  "device": { "id": 9, "name": "...", "platform": "windows", "device_identifier": "uuid...", "status": "active", "client_id": 2, "business_id": 3, "branch_id": 7, "activated_at": "...", "last_seen_at": null },
  "token": "5|abcdef..."
}
```

**`token` is shown exactly once**, like any Sanctum personal access token —
Electron must store it securely now; it cannot be retrieved again (only
re-registration issues a new one). Registration doubles as activation: a
newly registered device is immediately `active`, not pending approval —
there is no separate "activate" call in this API.

Errors: `403` inactive subscription, or the client's `max_devices` package
limit reached; `422` for a branch not belonging to the actor's own
business/client.

**Activating, deactivating, or blocking an already-registered device is a
session-authenticated Admin Panel action** (`Admin\DeviceController`, not
part of this API) — not duplicated here. What *is* enforced here is the
consequence: `GET /devices/current`, and any future device-scoped
endpoint behind the `device.active` middleware, rejects a token belonging
to a `blocked` or `inactive` device with `403`, even though the token
itself is still technically unrevoked. A non-device token (a cashier's own
user token) hitting a device-only endpoint gets `401` — Sanctum resolved
*something*, just not a device.

### 5.10 Receipts

`GET /sales/{sale}/receipt` — same permission/tenant check as `GET
/sales/{sale}` (never trust the sale id alone; a branch-scoped actor can't
fetch another branch's receipt just by guessing an id). Returns clean,
structured data — business (name/logo/phone/address), branch, invoice
number, sale date, cashier, customer (or `null`), each item
(name/SKU/quantity/unit price/discount/tax/line total), each payment
(method/amount/reference), and the subtotal/discount/tax/total. The Web
app prints this via the browser; Electron would drive thermal printing
from it — neither printing path is implemented here, this endpoint only
shapes the data both need.

### 5.10a Sync (Electron initial + incremental)

Base: `/sync/*`. **Device-authenticated only** — every method reads its
business/branch exclusively from the authenticated device's own record
(§5.9), never from a request parameter, so there is nothing here for a
request to spoof. No `permission:*` gate applies (there is no logged-in
user in this flow); only `device.active` (blocked/inactive devices get
`403`, a non-device token gets `401`).

| Method | Endpoint | Notes |
|---|---|---|
| GET | `/sync/bootstrap` | one-shot, not paginated — device/business/branch info, resolved settings, enabled features, payment methods, the RBAC catalog (roles+permissions) |
| GET | `/sync/categories` | business-scoped |
| GET | `/sync/brands` | business-scoped |
| GET | `/sync/products` | business-scoped |
| GET | `/sync/customers` | business-scoped |
| GET | `/sync/inventory` | **branch**-scoped (the device's own branch only) — no `deleted_ids`, a balance is updated, never deleted |

**Cursor strategy:** every one of the five paginated endpoints shares the
same contract. Omit `?since=` for an **initial sync** (returns everything
currently in scope for that business/branch). Pass the `cursor` value from
a previous response as `?since=` for an **incremental sync** — this is an
opaque, server-generated string encoding a server-clock timestamp; nothing
about it is derived from the client's own clock, and Electron must treat it
as a black box (store and echo back verbatim, never construct one itself).
A malformed/tampered cursor returns `422`, not a silent full resync.

Response shape (identical across all five):

```json
{
  "data": [ { "...": "..." } ],
  "deleted_ids": [14, 22],
  "cursor": "eyJhc19vZiI6IjIwMjYtMDgtMjMg...==",
  "synced_at": "2026-08-23T10:15:00+00:00",
  "meta": { "current_page": 1, "per_page": 500, "total": 42, "last_page": 1, "has_more": false }
}
```

`deleted_ids` is only ever populated on an incremental call (a `since` was
given) for a soft-deletable resource — an initial sync always returns `[]`
there (nothing to reconcile on a fresh device). `?per_page=` is accepted
(default 500, capped at 1000) so a large catalog is chunked rather than
returned in one response.

**Never dropped, never doubled:** the filter is `updated_at > since`
(strict, no upper bound) — a row changed between when the cursor was
captured and when the query actually ran is simply included a cycle early,
which is the deliberate trade-off: this design can occasionally hand back
a change slightly ahead of schedule, but can never silently skip one.

### 5.10b Offline Sale Upload (Electron)

`POST /sync/sales` — device-authenticated, same gate as §5.10a. Uploads
one sale that was rung up **while offline**; distinct from `POST /sales`
(§5.6, user-authenticated, always "now") in exactly two ways: it carries
an `offline_created_at` timestamp that becomes the sale's actual
`sale_date` (never the upload time), and it identifies the cashier who
rang it up via `cashier_id` rather than the request's own authenticated
identity (there is no live cashier session in this flow — the device
itself is the caller). Everything else — authoritative pricing, tenant
isolation, the idempotency contract (§6) — is identical, because this
literally reuses the same `SaleService`.

Body:

```json
{
  "client_transaction_id": "b1e6c1f0-...-uuid-from-the-device",
  "cashier_id": 12, "customer_id": null,
  "offline_created_at": "2026-08-20T14:30:00Z",
  "items": [ { "product_id": 42, "quantity": 2, "discount": 0 } ],
  "payments": [ { "payment_method_id": 1, "amount": 44 } ]
}
```

As with every other write endpoint, `business_id`/`branch_id`/`device_id`
are **not accepted fields at all** — both come exclusively from the
authenticated device's own record. `cashier_id` is re-verified to belong to
that same business (and branch, if the cashier has a fixed one) and to be
an active account — it is not merely trusted because it looks like a valid
id. `offline_created_at` must be `<= now()` (the server's clock, not the
device's) — a claimed future timestamp is rejected outright, not clamped.

Response `201`:

```json
{ "success": true, "message": "Offline sale synced successfully.", "data": {
  "status": "accepted", "client_transaction_id": "...", "server_id": 501,
  "invoice_number": "INV-...", "receipt": { "...": "see §5.9" }
}}
```

**A duplicate upload of the same `client_transaction_id` (identical
payload) returns this exact same body again — including the same
`server_id` — rather than creating a second sale.** This endpoint never
silently modifies an already-accepted transaction and claims success: a
reused id with a *different* payload is rejected (see below), never merged
or overwritten.

**Structured failure classification** — this endpoint's error responses
carry one additional field on top of the standard envelope (§1.3),
`error_type`, so Electron can decide what to do next without inspecting
the message string:

| `error_type` | Meaning | Example |
|---|---|---|
| `retryable` | May succeed later with the exact same payload, unchanged | Insufficient stock right now |
| `non_retryable` | Will never succeed as submitted; don't retry unless the payload itself changes | Invalid cashier/product/branch reference, blocked device, expired subscription, bad request shape |
| `conflict` | This `client_transaction_id` was already used with *different* data | A reused id — Electron must resolve this, not retry |

```json
{ "success": false, "message": "Insufficient stock: 1 available, 5 requested.", "error_type": "retryable", "errors": {} }
```

No other endpoint in this API returns `error_type` — it's specific to this
one, where a disconnected client genuinely needs a machine-readable retry
decision. An unclassified `5xx` (no `error_type` present at all) should be
treated as retryable per ordinary HTTP convention.

### 5.11 Reports

Base: `/reports/*`. Every method independently re-resolves and re-verifies
its own tenant scope (see below) — there's no single `permission:*` route
middleware for this group, because access also depends on subscription and
feature state, which route middleware can't express.

| Method | Endpoint | Filters |
|---|---|---|
| GET | `/reports/sales` | `date_from`, `date_to`, `branch_id`, `cashier_id`, `customer_id`, `payment_method_id` |
| GET | `/reports/purchases` | `date_from`, `date_to`, `branch_id`, `supplier_id` |
| GET | `/reports/inventory` | `branch_id`, `category_id` |
| GET | `/reports/products` | `date_from`, `date_to`, `branch_id`, `category_id` (top-selling, by quantity) |
| GET | `/reports/customers` | `date_from`, `date_to`, `branch_id`, `customer_id` (spend per customer) |
| GET | `/reports/profit` | `date_from`, `date_to`, `branch_id` |
| GET | `/reports/expenses` | — |

Every report requires `reports.view`, an active subscription, and the
`reports` feature enabled for the business. `business_id` may be passed
as a query hint only for an actor with no fixed business of their own (a
multi-business Client Owner or Super Admin) — it is always re-verified
against that actor's own client and can never be used to view another
tenant's data (a fixed-business actor's own `business_id` always wins,
silently ignoring anything passed in the query string).

All totals are computed with SQL aggregation (`SUM`/`COUNT`/`GROUP BY`) —
no report loads a full transaction table into a PHP collection to sum it
there, so response time doesn't grow with history size. Row-level results
(e.g. the sales list backing the sales report, or the ranked product list)
are paginated the same way as any other list endpoint.

Response shape is `{ "summary": {...aggregates}, "<resource>": {paginated} }`
for Sales/Purchases/Inventory, `{ "<resource>": {paginated} }` for
Products/Customers (the ranking *is* the report), and a flat
`{ "revenue", "estimated_cost", "estimated_gross_profit" }` for Profit.

**Profit is an estimate**, documented as such in the response message
itself: `sale_items` doesn't snapshot each unit's historical cost, so
"cost" is `Σ quantity × Product.purchase_price at today's price`, not the
true historical margin.

**`GET /reports/expenses` returns `501 Not Implemented`.** No Expense
tracking table or model exists anywhere in this codebase — rather than
fabricate a report over data that isn't tracked, this endpoint says so
plainly. Building real expense tracking is a future task.

---

## 6. Idempotency (the `client_transaction_id` contract)

**Problem this solves:** Electron may submit the same offline sale more
than once — timeout, network failure, retry, app restart, sync retry — and
the server must still create it exactly once.

**Mechanism:** `App\Services\IdempotencyService`, backed by a dedicated
`idempotency_keys` table, one row per `(business_id, client_transaction_id)`
pair — **that pairing is a real database unique constraint**, not just an
application check, so it holds even under genuine concurrent requests. A
row records: the business, an optional device, the client-supplied
`client_transaction_id`, a hash of the request payload, and — once the
wrapped operation actually finishes — the response that was returned and a
polymorphic link to whatever was created (currently, a `Sale`).

**Behavior by scenario** (all currently exercised for Sales, the only
endpoint that uses this today):

| Scenario | Result |
|---|---|
| First request with a new key | Executes normally, records the response |
| Same key, same business, identical payload, repeated | The *original* response is replayed — no second row is created, and the operation does not run again |
| Same key after a timeout (the first attempt crashed before recording a response) | Treated as not-yet-completed — this retry actually executes the operation (there's nothing to replay yet) |
| Same key, same business, a **different** payload | Rejected with `409` — a key is a promise about one specific transaction, not a slot to overwrite |
| A different key | An entirely separate transaction, executes normally |
| The same key string reused by a **different** business | Not a collision at all — the unique constraint includes `business_id`, so it's simply a different row. One business can never observe, block, or replay another's key |
| Two genuinely concurrent requests with the same key | Only one can win the underlying `INSERT`; the loser detects the unique-constraint violation, re-reads the now-locked row, and waits for the winner's transaction to commit before replaying its result — so two simultaneous identical requests still produce exactly one transaction |

**Client integration guidance:** generate a UUID (or similar) once per
logical transaction, on the device, before the first send attempt — reuse
that *same* string for every retry of that same sale. Never generate a new
id per retry (that defeats the mechanism) and never reuse one across two
genuinely different sales (the second will be rejected as a payload
mismatch under the same id, or — if it happens to match — silently
replayed as the first).

`client_transaction_id` is **required** on `POST /sales` (not optional) —
this is enforced deliberately so the mechanism is actually used by every
POS write, not opt-in.

---

## 7. Not yet implemented (out of scope for this contract)

Documented here so a client team doesn't spend time discovering these by
trial and error:

- **Branch management** — creating/activating/deactivating a branch exists
  only in the session-authenticated Admin panel (`routes/admin.php`), not
  as an API endpoint. A branch id used elsewhere in this API (Sales,
  Purchases, Cash Sessions, Inventory, Reports) must already exist.
- **Batch offline upload** — pulling data down (§5.10a) and uploading one
  offline sale at a time (§5.10b) are both built. What isn't built is a
  single "upload my whole offline queue" endpoint accepting many sales in
  one call — Electron currently uploads each queued sale as its own
  `POST /sync/sales` request (idempotent per §6, so safe to retry each one
  individually on reconnect).
- **Product variants / serials** — the schema doesn't preclude adding a
  `product_variants` table or per-unit serial tracking later; nothing here
  builds it now.

---

## 8. Change log

| Date | Change |
|---|---|
| 2026-08-23 | Initial contract: Auth, Config, Employees, Products/Categories/Brands, Inventory, Customers/Suppliers, Purchases, Sales, Returns, Cash Sessions, Devices (Electron authentication), Sync (initial + incremental + offline sale upload), Reports, Receipts, and the idempotency architecture. |
