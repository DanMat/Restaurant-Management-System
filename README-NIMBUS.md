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
  status, a mobile floor board, capability-gated admin + MCP. First operational
  vertical; validated the plugin architecture.
- ⬜ Orders, Kitchen display, Payment, Staff & roles, Reservations, Reports — next.

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
