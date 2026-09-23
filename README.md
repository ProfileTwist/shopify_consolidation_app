# Shopify Consolidation App

Consolidates orders, payouts, and products from many Shopify stores into one
database, exposed through a REST API and a React dashboard. Separate from the
Storka monolith.

## Layout

```
shopify_consolidation_app/
├── api/                    # Laravel 13 REST API + sync jobs (PHP 8.4)
├── web/                    # React + Vite + shadcn dashboard
├── docker-compose.yml      # mysql, api, api-web, queue, scheduler, web
├── .env                    # APP_KEY + Shopify OAuth creds (git-ignored)
└── inbound-webhook-api/    # generic webhook receiver → AWS SNS/SQS (LocalStack)
```

## Prerequisites

A Docker daemon. On this Mac we use **Colima** (no Docker Desktop needed):

```bash
colima start
docker version
```

If Colima isn't installed: `brew install colima docker docker-compose`.

## Run the whole setup

**Main app** (dashboard + API + DB + workers) — from this folder:

```bash
docker compose up -d --build
```

- Dashboard → **http://localhost:3000** (or http://liquorpilot.local:3000)
- API → http://localhost:8080 · MySQL → localhost:3307

**Webhook service** (only for real-time webhooks / SNS-SQS demo):

```bash
cd inbound-webhook-api && docker compose up -d --build
```

- Service → http://localhost:8090 · LocalStack (SNS+SQS) → localhost:4566

Stop either with `docker compose down` (add `-v` to also wipe its data volume).

## User authentication & roles

The app has its **own user system** (separate from the Shopify store owners).

**How it works**
- Auth is **token-based** via Laravel **Sanctum**. `POST /api/login` with email +
  password returns a bearer token; the SPA stores it and sends it as
  `Authorization: Bearer <token>` on every request. `POST /api/logout` revokes it.
- **Roles & permissions** use `spatie/laravel-permission`. Every protected route is
  guarded by a permission (e.g. `permission:view orders`), so access is enforced
  server-side, not just hidden in the UI.

**Roles**

| Role | Permissions | Can |
|------|-------------|-----|
| `admin` | view orders, manage connections, manage users | everything |
| `manager` | view orders, manage connections | view data + connect/sync/delete stores |
| `viewer` | view orders | view orders/payouts/products only (no connect/sync) |

**Permissions** (assigned to roles): `view orders`, `manage connections`, `manage users`.
The UI also reads the signed-in user's permissions (`GET /api/me`) to show/hide
actions like **Connect store**, **Sync now**, **Pause**, **Delete**.

**Demo logins** (seeded)

| Email | Password | Role |
|-------|----------|------|
| admin@demo.test | password | admin |
| viewer@demo.test | password | viewer |

**Add or change users**

```bash
docker compose exec api php artisan tinker
>>> $u = App\Models\User::create(['name'=>'Jane','email'=>'jane@liquorpilot.com','password'=>bcrypt('secret')]);
>>> $u->assignRole('manager');   // admin | manager | viewer
```

Roles/permissions themselves are seeded in `database/seeders/RolePermissionSeeder.php`.

## Connect a real Shopify store

Dashboard → **Connections → Connect a Shopify store**:

- **OAuth (recommended):** enter the `*.myshopify.com` domain → Connect → approve on
  Shopify. Requires `SHOPIFY_APP_CLIENT_ID` / `SHOPIFY_APP_CLIENT_SECRET` in `.env`,
  the app's scopes, and the redirect URL
  `http://liquorpilot.local:8080/api/shopify/callback` whitelisted on the app.
- **Custom-app token:** paste a store's Admin API token (`shpat_…`) into the token
  field — no OAuth app needed, good for a single store.

Optional host `liquorpilot.local` (matches the OAuth redirect):
`echo "127.0.0.1 liquorpilot.local" | sudo tee -a /etc/hosts`

## Seed demo data (dev stores start empty)

```bash
docker compose exec api php artisan shopify:seed-store --store=<id> --count=15 --products=8
docker compose exec api php artisan shopify:sync --store=<id>
```

Payouts can't be created in Shopify (settlement is real-money, read-only), so payout
data on a dev store is synthesized locally for the demo.

## How the sync works

- Each store is a row in `stores` (Admin API token encrypted at rest).
- `PullShopifyDataJob` runs **per store** — isolated, idempotent (upsert on
  `store_id` + `shopify_id`), pulling **orders + payouts + products**.
- `sync:due` (scheduler, every 10s) dispatches each store on its own
  `sync_interval_seconds`; `shopify:sync` triggers on demand.
- Driver: `SHOPIFY_DRIVER=http` hits real Shopify; `mock` generates sample data.

## Key env (`.env`, read by docker-compose)

| Var | Purpose |
|-----|---------|
| `APP_KEY` | **Shared across all containers** so encrypted tokens decrypt everywhere |
| `SHOPIFY_APP_CLIENT_ID` / `SHOPIFY_APP_CLIENT_SECRET` | OAuth app credentials |
