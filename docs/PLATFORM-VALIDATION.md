# Platform validation ledger

This repository is being rebuilt as the **first real application on
[NimbusCMS](https://github.com/NimbusCMS/nimbus)**. Its job is not only to be a
restaurant system — it is to *validate the platform* by being demanding.

The rule, for every feature:

1. Build it with Nimbus's existing **public** APIs.
2. If blocked, name the exact missing capability.
3. Add the **smallest** core capability that unblocks it — but only if it is
   broadly reusable.
4. Keep genuinely restaurant-specific logic (tables, kitchen flow, reservations)
   **inside this app**, never in Nimbus core.

Every capability Nimbus gains because this app needed it is recorded below.
Success is finishing the Restaurant system **without any restaurant-specific
logic landing in Nimbus core.**

---

## Vertical status

| Vertical | Status | Needed a new core capability? |
|----------|--------|-------------------------------|
| **Menu** (categories, priced items) | ✅ proven on stock Nimbus | No |
| Tables | ⬜ not started | likely: a user/staff reference field |
| Orders | ⬜ not started | likely: repeatable line items, workflow state |
| Kitchen display | ⬜ not started | likely: plugin routes + admin pages |
| Reservations | ⬜ not started | tbd |
| Reports | ⬜ not started | likely: dashboard widgets / aggregation |
| Staff & roles | ⬜ not started | likely: custom roles / capability model |

---

## Menu — proven

Categories and priced menu items are ordinary Nimbus collections. No core
change was required to **model or serve** a menu.

- `categories` — a collection with a text `name`.
- `menu_items` — a collection with a `number` price and a `relation` to
  `categories`.

Provisioned entirely through Nimbus's public admin API by
[`bin/provision-menu.sh`](../bin/provision-menu.sh); the content model is
declared in [`app/collections.php`](../app/collections.php). Served over the
read API at `GET /api/v1/collections/menu_items/entries`.

```json
{ "data": [ { "slug": "margherita", "title": "Margherita Pizza",
    "fields": { "price": 12.5, "category": [15] } } ], "meta": { "total": 2 } }
```

---

## Findings (candidate Nimbus capabilities)

These are things the Menu vertical surfaced. None *blocked* Menu, so none has
been built yet — they are logged for when a later vertical makes them a
blocker, at which point each becomes a Nimbus core PR with its own ADR.

### F1 — The API returns relations as bare ids

`"category": [15]` means a frontend must make a second call per category to
render "Margherita — *Mains* — $12.50". **Candidate capability:** relation (and
in general, reference) *expansion* in the read API — the same enrichment media
fields already get. Broadly reusable; almost every real frontend wants it.
**Severity:** high — likely the first capability Orders/Menu-frontend forces.

### F2 — No supported way to consume Nimbus from a separate app repo

Nimbus runs only as the **root project** today: `Config::basePath()` resolves
to the package directory, it is not on Packagist, and there is no published
image or "library mode". So an application cannot simply
`composer require nimbuscms/nimbus` and point it at its own config/uploads.
Provisioning is possible only by scripting the HTTP admin API (which is what
this app does).

**Candidate capabilities**, smallest first:
- a documented "build your app as a Nimbus project" layout (works today);
- a declarative provisioning command (`bin/nimbus collections:sync <file>`) so
  apps stop scripting HTTP form posts;
- **library/consumption mode** — Nimbus resolvable as a Composer dependency with
  the *consuming* project's root as the base path;
- Packagist publication + a runnable image.

**Severity:** foundational, but not a Menu blocker. This is the decision that
most shapes how every Nimbus application is packaged, so it is called out
separately for a deliberate choice rather than an accidental one.

### F3 — Numbers drop trailing decimals (`8.00` → `8`)

Minor. Money reads back as `8` and `12.5`. Formatting is arguably the
frontend's job, so this stays an app concern unless a shared "money/decimal"
field type proves reusable across apps.
