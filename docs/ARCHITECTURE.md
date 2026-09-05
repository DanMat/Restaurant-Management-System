# Restaurant Management System on NimbusCMS — rebuild architecture

Design-first record for rebuilding the Restaurant Management System as an
**application on [NimbusCMS](https://github.com/NimbusCMS/nimbus)**. No code is
written from this document until it has passed both Nimbus review skills
(`nimbus-review-loop` and `nimbus-security-review`); this is the thing they
review.

Companion documents:
- [`PLATFORM-VALIDATION.md`](PLATFORM-VALIDATION.md) — the running ledger of what
  Nimbus needed (findings F1–F3).
- [`adr/0001-restaurant-as-a-colocated-nimbus-plugin.md`](adr/0001-restaurant-as-a-colocated-nimbus-plugin.md)
  — the decision this document elaborates.

---

## 1. Thesis

Two goals, in priority order:

1. **Rebuild the app** — a working restaurant system: menu, floor, orders,
   kitchen, payment, reservations, reports, and staff roles.
2. **Validate the platform** — prove a full, real application can be built on
   stock Nimbus **without any restaurant-specific logic landing in Nimbus core.**
   Every capability Nimbus gains because this app demanded it is the smallest,
   broadly-reusable one, lands with its own ADR in the Nimbus repo, and is
   recorded in the validation ledger.

The legacy system (procedural PHP, `oose.sql`, MD5 passwords) is preserved on
`master`. The rebuild lives on `nimbus-rebuild`.

---

## 2. The architecture decision

**The Restaurant is a Nimbus *plugin*, co-located in this repository, composed
with the official CRM plugin and a theme, and deployed onto a Nimbus site.**

- **Restaurant-specific behaviour** (tables, orders, kitchen flow, reservations,
  reports) is a single plugin — `restaurant` — that lives **inside this repo**
  under [`plugin/`](../plugin). It mirrors the mature official-plugin pattern
  (Inventory / Commerce / CRM): its own `rest_*` tables (ADR 0005), a
  wildcard-immune capability (ADR 0015), capability-gated admin pages (ADR 0020),
  plugin routes for the staff/kitchen terminals, an MCP toolset (ADR 0016), and a
  guide (ADR 0013). **Zero Nimbus core change** — this is the whole point.
- **Guests** are not reinvented: a guest is a **CRM contact**
  ([`nimbuscms/crm`](https://github.com/NimbusCMS/plugin-crm)). Reservations and
  (optionally) orders reference a CRM contact id. This is why the CRM was built
  first.
- **The menu** stays ordinary Nimbus **collections** (`categories`,
  `menu_items`) — content, already proven, served over the read API to the public
  site.
- **The public menu + staff UI theme** is a Nimbus theme (the existing
  [`nimbus-theme-cafe`](https://github.com/DanMat/nimbus-theme-cafe) is the
  starting point).

### Why a plugin, not more collections

Menu is *content*. Tables, orders and the kitchen are **operational state and
behaviour** — a table's status changes as guests are seated and cleared, an order
moves through a workflow, a cook marks a ticket ready, a manager reads today's
revenue, staff actions are gated by role. That is precisely the plugin hinge
surface (own tables + workflow + capability-gated admin pages + routes + MCP), not
the collections/content surface. Forcing live operational state into generic
content collections would be awkward and would tempt restaurant logic into core.

### How the app consumes Nimbus (resolves finding F2)

Nimbus discovers plugins from Composer's `installed.json` by
`"type": "nimbuscms-plugin"`. So the co-located plugin needs no special support:

- `plugin/composer.json` declares `"type": "nimbuscms-plugin"`,
  `extra.nimbus.id = "danmat.restaurant"`, and the PSR-4 plugin class.
- A deployed **Nimbus site** (its own thin repo / compose project, as with
  Foodmart) adds a Composer **path repository** pointing at this repo's `plugin/`
  directory and `composer require`s it; the CRM and theme come from their GitHub
  packages exactly as the demo image already wires official plugins.

No Packagist publication is required for the app's own plugin, and Nimbus stays
the root project. This is the smallest thing that works today and keeps the app
self-contained in one repo — the outcome the F2 finding asked us to choose
deliberately.

---

## 3. Legacy → Nimbus domain map

Every legacy table (`oose.sql`) has a home. Nothing restaurant-specific goes to
core.

| Legacy table | Rebuild home | Shape |
|---|---|---|
| `category` | collection `categories` ✅ | text `name` |
| `item` | collection `menu_items` ✅ | `number` price, `relation` → categories |
| `floorplan` | plugin table `rest_table` | label/number, seats, `status` enum (`open`/`occupied`/`dirty`/`reserved`), assigned staff (user ref) |
| `orders` + `orderlist` (serialised string) | plugin tables `rest_order` + `rest_order_item` | order: table ref, `status` workflow, `paid`, totals, opened-by staff, optional CRM guest; items: proper rows (menu item ref/snapshot, qty, line state) |
| `employee` + `role` | Nimbus **users** + plugin **capabilities** | waiter / cook / host / busboy / manager / admin → capability grants (see §5) |
| (payment: `paid`) | fields on `rest_order` | amount, method, `paid_at` |
| (reports: manager revenue) | aggregation over `rest_order` | revenue-by-day dashboard page + MCP tool |
| (guests) | **CRM contact** | reused; reservations/orders link a contact id |
| (reservations — new) | plugin table `rest_reservation` | table ref, datetime, party size, CRM guest ref, status |

The serialised `orderlist` string (`"Chicken Marsala-1-8.21;…"`) — the legacy
system's worst modelling wart — becomes first-class `rest_order_item` rows, each
snapshotting name + unit price at order time so a later menu edit never rewrites
history.

---

## 4. Plugin shape (`plugin/`)

Mirrors the CRM plugin's structure exactly:

```
plugin/
  composer.json          type: nimbuscms-plugin, id danmat.restaurant
  src/
    RestaurantPlugin.php  register(): migrations, capabilities, admin, routes, mcp, guide
    Schema.php            rest_table / rest_order / rest_order_item / rest_reservation
    Tables.php            floor service (status transitions, assignment)
    Orders.php            order + line-item service (workflow, totals, payment)
    Reservations.php      reservation service (+ CRM guest link)
    Reports.php           revenue aggregation
    *Admin.php            floor board, order/ticket screens, kitchen display, reports
    RestaurantToolset.php MCP: an agent can run the floor and kitchen
    Guide.php             agent guide
  tests/                  one DB-backed test suite per service, mirroring CRM
```

Discipline carried over from the CRM build (non-negotiable):
- **bound SQL everywhere**; enums/statuses are **write-time allow-lists**, never
  interpolated;
- **store raw, escape on render**; every admin value escaped, styles in a
  nonce'd `<style>` block (admin CSP is nonce-only);
- **capability-gated** on every surface (admin + MCP), wildcard-immune;
- **mobile first-class** — staff use phones/tablets on the floor; every screen
  verified at 375px;
- **MCP first-class** — every operator action has a gated, non-enumerating tool;
- **total, transactional deletes**; cross-entity cleanup (deleting a table
  releases its orders sanely; closing an order never orphans line items).

---

## 5. Capabilities & staff roles

The plugin declares its own wildcard-immune capabilities (app-local, not core):

- `danmat.restaurant.floor` — seat/clear tables, open orders, add items, take
  payment (waiter, host, busboy).
- `danmat.restaurant.kitchen` — see and advance kitchen tickets (cook).
- `danmat.restaurant.manage` — menu, staff, reports, settings (manager, admin).

Legacy roles map to bundles of these grants on Nimbus **users**:

| Legacy role | Capabilities |
|---|---|
| waiter | floor |
| host | floor |
| busboy | floor (clear/clean only — enforced in-app) |
| cook | kitchen |
| manager | manage (+ floor, kitchen to observe) |
| admin | manage + Nimbus admin |

This deliberately exercises Nimbus's capability model (a ledger "likely need").
If a first-class *roles* concept (named capability bundles) proves broadly
reusable while doing this, that becomes a Nimbus core PR + ADR — not app code.
Passwords are Nimbus's problem now; the legacy MD5 `pwd` column dies with
`master`.

---

## 6. The terminals (routes + admin pages)

- **Public menu** — the theme renders the `menu_items` collection. Read-only,
  public.
- **Floor terminal** (waiter/host/busboy) — a plugin admin page / route: the
  floor as a grid of tables by status; tap a table to open/append an order, send
  to kitchen, take payment, mark cleared. Mobile-first.
- **Kitchen display** (cook) — a plugin route: open tickets grouped by state
  (`sent` → `preparing` → `ready`), tap to advance. Auto-refresh (poll) in v1;
  real-time transport is a later, non-core concern.
- **Manager dashboard** — reports: today's covers and revenue, and the menu.

Order workflow (replaces the legacy `done`/`paid` tinyints):
`open → sent → preparing → ready → served → closed`, with `paid` orthogonal, all
allow-listed transitions.

---

## 7. Findings resolution (from the validation ledger)

- **F2 — consumption model → DECIDED** (this document + ADR-0001): co-located
  `nimbuscms-plugin` package, consumed by a Nimbus site via a Composer path repo.
- **F1 — relation expansion in the read API** — still open, and Orders/the public
  menu is the vertical that will force it (rendering "item — category — price"
  without N calls). When it blocks, it becomes the **first Nimbus core PR** of
  this rebuild: read-API reference expansion, smallest reusable form, its own ADR.
  In-app (admin/MCP) surfaces avoid it by joining in their own SQL.
- **F3 — money formatting** — stays an app/theme concern; the plugin stores
  `DECIMAL` and formats on render. Only becomes core if a shared money field type
  proves reusable across apps.

---

## 8. Build sequence (each a design→review→build→CI-green slice)

Tables first: it is the smallest vertical that forces operational state, so it
validates this whole architecture before Orders/Kitchen are built on top.

1. **Plugin scaffold + Tables** — package, `danmat.restaurant.floor` capability,
   `rest_table`, floor board admin page + status transitions, MCP tools, tests.
2. **Orders** — `rest_order` + `rest_order_item`, open/append on a table, add
   menu items (cross-collection read), running totals, workflow, MCP.
3. **Kitchen display** — tickets route, cook advances state.
4. **Payment & turn** — mark paid (amount/method), close → dirty → cleared →
   open.
5. **Staff & roles** — capabilities, staff users, role bundles, login → terminal.
6. **Reservations** — `rest_reservation` + CRM guest link.
7. **Reports** — revenue/covers dashboard + MCP.
8. **Theme + public menu + deploy** — onto a Nimbus site (the Foodmart runbook
   pattern), with CRM + theme + this plugin.

Each slice: run both review skills on the slice design, build behind the plugin
hinges, CI green (cs-fixer + PHPStan L6 + phpunit on 8.2/8.3), verify at 375px.

---

## 9. Legacy issues

The open issues on `DanMat/Restaurant-Management-System` (#1 Index error, #2
black screen, #3 username/password mismatch, #4 error) are all faults of the
2014–2018 procedural code (including the MD5 auth of #3). They are **superseded,
not patched**: the rebuild replaces the code that produces them. When the rebuild
merges, each is closed with a note pointing at the vertical that subsumes it
(auth/#3 → Staff & roles; the rest → the rebuilt terminals).

---

## 10. Definition of done

The Restaurant system runs end-to-end on a deployed Nimbus site — menu, floor,
orders, kitchen, payment, reservations (with CRM guests), reports, and role-gated
staff logins — with **no restaurant-specific code in Nimbus core**, every core
capability it did need landed as a reusable, ADR-backed PR, and the validation
ledger closed out. Then `nimbus-rebuild` merges to `master`, the legacy issues
close, and Nimbus has its first proven end-to-end application.
