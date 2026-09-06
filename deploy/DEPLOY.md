# Deploying the RAS demo

The Restaurant Automation System runs as a public **demo**: anyone can log in as
any role and explore the admin, and the data resets every hour. It is deployed the
same way as Foodmart — a co-located site on the existing platform box (one box,
one bill), behind Cloudflare.

Target hostname (example): **`ras.danmat.dev`** — substitute your chosen subdomain
throughout.

## What ships

- **NimbusCMS core** (`nimbuscms/nimbus`, dev-main).
- **The restaurant plugin** — this repo's `plugin/`, consumed via a Composer
  **path repository** (ADR-0001); no Packagist.
- **The CRM plugin** (`nimbuscms/crm`, dev-main) — guests.
- **The RAS theme** — this repo's `theme/`, copied to the site's `themes/restaurant/`.
- **The menu collections + demo data** — via `deploy/seed-demo.php`.

## Site image

Mirror the Foodmart demo image. A `Dockerfile` for the site (built from a checkout
of this repo alongside a Nimbus checkout) should:

1. Start from the Nimbus base (PHP 8.2/8.3 + extensions), app at `/var/www/html`.
2. Add a path repository to this repo's `plugin/` and `composer require danmat/restaurant:@dev`.
3. `composer require nimbuscms/crm:dev-main`.
4. Copy this repo's `theme/` to `/var/www/html/themes/restaurant/`.
5. Set config:
   - `config/theme.php` → `return 'restaurant';`
   - `config/plugins.php` → enable `nimbuscms.crm` and `danmat.restaurant`
     (plugins are enabled by default; no action usually needed).
   - Optionally render the menu at `/` (site config `homeCollection` → `menu_items`),
     else the public menu is at `/menu_items` and the header links to it.
6. Copy `deploy/seed-demo.php` and `deploy/reset-demo.sh` into the image.

## Compose + edge

Add a site to the `nimbus-platform` project (as with Foodmart):

- `docker-compose.ras.yml` — the `ras` app service (image above) + its own MySQL
  (`db-ras`), on the `nimbus-platform_web` network, with `DB_*` env.
- Caddy: a per-host block for `ras.danmat.dev` → the `ras` service, `import
  cloudflare_only`, `tls /certs/ras.danmat.dev.pem`.
- Cloudflare: a proxied DNS record for `ras.danmat.dev` → the box, and an origin
  cert in `/certs/` (same as the other sites).

## First deploy

From the box, in the platform project:

```sh
docker compose -f docker-compose.yml -f docker-compose.ras.yml up -d --build ras db-ras
docker compose -f docker-compose.ras.yml exec ras sh deploy/reset-demo.sh
```

`reset-demo.sh` migrates (core + plugin + CRM), creates the admin, and seeds the
demo. Visit `https://ras.danmat.dev/menu_items` (public menu) and
`https://ras.danmat.dev/admin` (staff).

## Hourly reset

A cron on the box re-runs the reset each hour:

```cron
0 * * * * cd /opt/nimbus-platform && docker compose -f docker-compose.ras.yml exec -T ras sh deploy/reset-demo.sh >> /var/log/ras-reset.log 2>&1
```

## The demo logins (public, on purpose)

Every staff account uses the password **`restaurant-demo`**. Each is a Nimbus user in a
capability role, so each sees only what their role allows:

| Login | Role | Sees | Can it take payment? Open a guest's CRM record? |
|-------|------|------|--------------------------------------------------|
| `waiter@ras.demo` | Waiter (`:floor`) | Floor, Orders, Reservations | Takes payment · **cannot** open CRM guest |
| `host@ras.demo` | Host (`:floor`) | Floor, Orders, Reservations | same as waiter |
| `busboy@ras.demo` | Busboy (`:floor`) | Floor, Orders, Reservations | same as waiter |
| `cook@ras.demo` | Cook (`:kitchen`) | Kitchen only | **cannot** take payment |
| `manager@ras.demo` | Manager (`:floor` + `:kitchen` + `:manage` + `crm:read/write` + `menu_items/categories:write`) | Everything incl. Reports **and the menu** (edit items/categories) | Takes payment · **can** open CRM guest |
| `admin@ras.demo` | Admin | The whole CMS | — |

Logging in as a waiter vs a manager shows the capability model live: the cook has
no payment button, and a floor login opening a reservation's "Guest in CRM" link
is refused while the manager's is not — the cross-plugin PII gate, visible.
