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

## Status

- ✅ **Menu** — categories and priced menu items, modelled as Nimbus
  collections and served over its API. Proven on stock Nimbus, no core changes.
- ⬜ Tables, Orders, Kitchen display, Reservations, Reports — next.

## Layout

```
app/collections.php     the app's content model, declared as data
bin/provision-menu.sh   installs that model onto a running Nimbus via its public API
docs/PLATFORM-VALIDATION.md   the running ledger of what Nimbus needed
```

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
