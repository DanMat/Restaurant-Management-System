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
| **Tables** | ✅ done (plugin) | No — coarse capability only (see F4) |
| **Orders** | ✅ done (plugin) | **Yes — the plugin content-read capability (ADR 0029 in core), which closed F1/A2** |
| **Kitchen display** | ✅ done (plugin) | No — an admin page (routes are public; not used) |
| **Payment & turn** | ✅ done (plugin) | No — server-computed amount on the order |
| **Reservations** | ✅ done (plugin) | No — links to CRM guests by id, never reads CRM (PII gate honored) |
| Reports | ⬜ not started | likely: dashboard widgets / aggregation |
| **Staff & roles** | ✅ done (plugin) | **Yes — fine-grained plugin capabilities (ADR 0030 in core), which closed F4** |

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

### F1 / A2 — Plugins had no in-process way to read a collection — ✅ RESOLVED (NimbusCMS ADR 0029)

**Resolved 2026-09-05** by a core capability the Orders vertical forced: a
read-only, published-only `ContentReader` exposed to plugins as
`PluginContext::content()` ([NimbusCMS PR #209](https://github.com/NimbusCMS/nimbus/pull/209),
core ADR 0029). The restaurant's `Menu` reads `menu_items` through it and snapshots
each ordered line's name + price. This is the platform-validation initiative working
as intended: the app drove the *smallest broadly-reusable* core capability, landed
with its own ADR + reviews in core, no restaurant-specific logic in it. The
relation-expansion note below is subsumed — `ContentReader` returns entries with
references expanded (like a theme).

<details><summary>Original F1 analysis (subsumed by ADR 0029)</summary>

#### F1 — The API returns relations as bare ids

`"category": [15]` means a frontend must make a second call per category to
render "Margherita — *Mains* — $12.50". **Candidate capability:** relation (and
in general, reference) *expansion* in the read API — the same enrichment media
fields already get. Broadly reusable; almost every real frontend wants it.
**Severity:** high — likely the first capability Orders/Menu-frontend forces.

</details>

### F2 — How an application consumes Nimbus — ✅ DECIDED (ADR-0001)

**Resolved 2026-09-05** ([`adr/0001`](adr/0001-restaurant-as-a-colocated-nimbus-plugin.md)):
the app's restaurant-specific logic is a **co-located Nimbus plugin** in this
repo's `plugin/` (`type: nimbuscms-plugin`), and a deployed Nimbus site consumes
it via a Composer **path repository** — Nimbus stays the root project, no
Packagist needed. Guests reuse the official CRM plugin; the menu stays
collections. The original analysis is kept below for the record.

<details><summary>Original F2 analysis (superseded by ADR-0001)</summary>

#### F2 — No supported way to consume Nimbus from a separate app repo

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

</details>

### F4 — Fine-grained, plugin-defined capabilities — ✅ RESOLVED (NimbusCMS ADR 0030)

**Resolved 2026-09-05** ([NimbusCMS PR #210](https://github.com/NimbusCMS/nimbus/pull/210)):
a plugin may now declare **any action** on its own capability (not just
read/write) and gate admin pages, actions and MCP tools on it — each an
independent, wildcard-immune grant. Only two read/write caps had to lift; the
authorization model was already general. The **Staff & roles** slice will re-gate
the restaurant's terminals on `floor` / `kitchen` / `manage`. Original finding
kept below.

<details><summary>Original F4 finding (resolved by ADR 0030)</summary>

Surfaced by the **design review**, before code. Stock Nimbus gives a plugin
**one** wildcard-immune capability (its own id) and gates admin pages/actions on
`{pluginId}:read|write` only (verified in `AdminPageRegistrar::isGateableCapability`).
The restaurant has six roles with genuinely different permissions
(waiter/host/busboy/cook/manager/admin) — a cook must not take payment, a busboy
only clears tables. That cannot be expressed at the gate today.

**v1 approach:** one coarse `danmat.restaurant:read|write` capability, with the
finer distinctions enforced **in-app** in the handlers that matter (comp/void =
manager-checked in code).

**Candidate capability (smallest first):**
- admin-page/action gating on an arbitrary *declared action* (not just
  read/write), so a plugin can gate on `danmat.restaurant:kitchen`; **or**
- a plugin declaring additional wildcard-immune capability ids; **or**
- a first-class **roles** concept (named capability bundles) — ADR 0009 already
  anticipates this.

**Severity:** high for the **Staff & roles** vertical (its blocker); until then,
non-blocking. This is the rebuild's most likely *second* core PR after F1.

</details>

### F3 — Numbers drop trailing decimals (`8.00` → `8`)

Minor. Money reads back as `8` and `12.5`. Formatting is arguably the
frontend's job, so this stays an app concern unless a shared "money/decimal"
field type proves reusable across apps.
