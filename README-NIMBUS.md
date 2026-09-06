# Restaurant Management System — rebuild on NimbusCMS

This branch (`nimbus-rebuild`) is rebuilding the Restaurant Management System as
an **application on [NimbusCMS](https://github.com/NimbusCMS/nimbus)**, instead
of the original hand-rolled PHP. The legacy system is preserved on `master` and
in git history.

The rebuild has a second purpose beyond the app itself: it is the **first real
validation of Nimbus as a platform.** If a full restaurant system can be built
on Nimbus without pushing restaurant-specific logic into the CMS core, the
platform is proven. What Nimbus can and cannot yet do is tracked, feature by
feature, in [`docs/PLATFORM-VALIDATION.md`](docs/PLATFORM-VALIDATION.md).

The architecture is settled in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
(and [`docs/adr/0001`](docs/adr/0001-restaurant-as-a-colocated-nimbus-plugin.md)):
restaurant-specific behaviour is a **Nimbus plugin co-located in this repo**
(`plugin/`), composed with the official CRM (guests) and a theme; the menu stays
collections. Zero Nimbus core change.

## Status

- ✅ **Menu** — categories and priced menu items, as Nimbus collections. Proven.
- ✅ **Tables (the floor)** — the `restaurant` plugin: `rest_table` with live
  status, a mobile floor board, capability-gated admin + MCP.
- ✅ **Orders** — `rest_order` + `rest_order_item`: open on a table, pick from the
  menu (snapshotting name + price), quantities, workflow, server-computed totals,
  admin + MCP. Forced and consumes the new core content-read capability (ADR 0029),
  closing findings F1/A2.
- ✅ **Kitchen display** — a cook's screen (New → Preparing → Ready) advancing the
  order workflow; capability-gated admin page, auto-refresh, mobile.
- ✅ **Payment & turn** — settle a bill (server-computed amount, method), close the
  order, and turn the table (→ dirty → clean → open). The service loop is closed.
- ✅ **Staff & roles** — fine-grained capabilities (floor / kitchen / manage) gate
  the terminals, so a cook can't take payment and a waiter can't be handed the
  books. Forced the core capability behind ADR 0030 (closing F4).
- ✅ **Reservations** — a booking book that links guests to their **CRM** records by
  id, without the restaurant ever reading CRM data (the PII boundary held).
- ✅ **Reports** — a manager dashboard (revenue today / 7 days, active orders, top
  items), read-only and gated on `:manage`.
- ✅ **RAS design uplift** — the staff terminals wear the original "Restaurant
  Automation System" identity, uplifted: the signature **circular table tokens** are
  back on the Floor (modernized status colours), with a shared RAS header across
  every terminal.
- ✅ **Public menu theme** — a dark, elegant guest menu in the RAS identity
  (`theme/`), rendering the `menu_items` collection grouped by category with prices.
- ⬜ Deploy live + create staff logins, then merge to `master` — the last step.

**Every operational vertical of the legacy system is now rebuilt on Nimbus**, and
the rebuild drove two reusable core capabilities (ADR 0029 content-read, ADR 0030
fine-grained capabilities) — with no restaurant-specific logic in Nimbus core.

## Layout

```
plugin/                       the restaurant plugin (type: nimbuscms-plugin)
  src/                        RestaurantPlugin, Schema, Tables, admin, MCP toolset, guide
  tests/                      DB-backed test suites (run in CI)
app/collections.php           the menu content model, declared as data
bin/provision-menu.sh         installs the menu onto a running Nimbus via its public API
docs/ARCHITECTURE.md          the rebuild design + decision record
docs/PLATFORM-VALIDATION.md   the running ledger of what Nimbus needed (F1–F4)
```

A deployed Nimbus site consumes `plugin/` via a Composer path repository (Nimbus
discovers it by `type: nimbuscms-plugin`); the CRM and theme come from their own
packages, as the demo image already wires official plugins.

## Running the Menu vertical

Point it at a running Nimbus instance (see the Nimbus repo for how to start one):

```bash
NIMBUS_URL=http://localhost:8080 \
ADMIN_EMAIL=admin@nimbus.test ADMIN_PASSWORD=password \
  bin/provision-menu.sh
```

Then mint a token in Nimbus (`php bin/nimbus token:create --name="Menu"`) and
read the menu as any frontend would:

```bash
curl -H "Authorization: Bearer <token>" \
  http://localhost:8080/api/v1/collections/menu_items/entries
```

> How this app should ultimately *consume* Nimbus — a Composer dependency, a
> published image, or a Nimbus project it extends — is an open platform decision,
> recorded as finding **F2** in the validation ledger.
