# Slice B — Public homepage + a denser guest site

**Status:** design (pre-build) · **Branch:** `nimbus-rebuild` · **Depends on:** the RAS
theme (Slice 8b), the menu collections, the ADR-0027 view-data hinge (core, already
shipped).

## Why

The 2014 original had **no public website** — it was a staff dashboard (RAS). The
guest-facing site is therefore new work, and today it is a single page: the menu at
`/`. Dan's ask: *"make the public-facing site more dense with a homepage direction
and all the normal stuff"*, keeping the uplifted RAS identity (dark `#1a1c20` +
gold `#d4a017`, serif display, printed-menu feel).

So: a real restaurant homepage — hero, a short about, a few **featured dishes
pulled live from the menu**, hours & location, and a reservations call-to-action —
with the full menu remaining its own page.

## Non-goals (this slice)

- Online **ordering / payment** — that is Slice C2 (its own design + security pass).
- Online **reservations** — booking stays staff-side (the Reservations terminal).
  The homepage CTA is *"call to reserve"* (a real `tel:` link), not a public form.
  (A public booking form is a candidate future slice; it is a public write surface
  and would need the same security treatment as ordering.)
- No new **core** capability. Everything here rides existing hinges.

## Content model — the homepage is editable CMS content

Model the homepage as a **`single`-kind collection `home`** (one entry, no index),
so the copy is editable in the admin and the slice also demonstrates Nimbus as a
CMS rather than hard-coding strings in a template.

`home` fields (all optional; the template degrades gracefully when blank):

| handle | type | purpose |
|--------|------|---------|
| `hero_kicker` | text | eyebrow, e.g. "Est. 2014 · Modern American" |
| `hero_title` | text | large display line, e.g. "The Copper Table" |
| `hero_tagline` | textarea | one or two sentences under the title |
| `about_title` | text | section heading |
| `about_body` | textarea | a short paragraph |
| `hours` | textarea | one "Day · time" per line |
| `address` | textarea | postal address, one line per row |
| `phone` | text | used for the `tel:` reservations CTA |

Wire it with the existing home mechanism: set the **`site.home` setting** to
`home` (DB setting — see the deploy gotcha; seeded via `SettingsRepository`). The
router then renders the single entry through `entry-home.php`
(`specialize('entry','home')`), falling back to `entry.php` if the theme lacked it.

> The menu stays at `/menu_items`. `site.home` moves from `menu_items` → `home`.

## Live "featured dishes" — the plugin feeds the theme (ADR 0027)

A restaurant homepage should show a few real dishes, and they must stay live as the
menu changes. The platform-honest way (no core change, no restaurant logic leaking
into core) is the **view-data hinge**:

- The restaurant plugin registers a `ViewDataContributor` via
  `PluginContext::viewData()`.
- Its `data(PageContext $page)` returns `[]` unless `$page->kind === 'home'`; on the
  home page it returns `['featured' => [ {title, price, category, blurb}, … ]]` — a
  **handful** of published menu items read through the plugin's existing
  `ContentReader` (`$context->content()`), which is **published-only** and
  **visitor-independent** (safe to bake into the by-path page cache).
- The result reaches the theme namespaced as
  `contrib['danmat.restaurant']['featured']`. The theme **escapes every value on
  render** (it is data, not HTML).

Selection rule for "featured": the first *N* (=3) published menu items that have a
non-empty description (`body`), falling back to the first *N* items — deterministic,
cache-stable, no per-visitor state.

Why not just query the menu in the template? Themes get only the current page's
view-model; cross-collection reads are a plugin concern. The hinge is exactly this
seam, and using it keeps the theme dumb and the data live.

## Theme changes

- **`templates/entry-home.php`** (new) — the homepage, sections in order:
  1. **Hero** — kicker, title, tagline; full-width dark band, gold rule, a "View
     the menu" button (→ `/menu_items`) and a "Call to reserve" button (→ `tel:`).
  2. **About** — `about_title` + `about_body`, measure-width column.
  3. **Featured** — 2–3 cards from `contrib['danmat.restaurant']['featured']`
     (name · price · one-line blurb), a "See the full menu →" link.
  4. **Visit** — a two-column block: **Hours** (from `hours`) and **Find us**
     (`address` + a `tel:` phone), on one column at ≤ 640px.
  5. **Reserve** — a closing CTA band (call to reserve).
- **`templates/header.php`** — nav becomes **Home · Menu** (and later Order). The
  brand already links to `/`.
- **`assets/app.css`** — sections styled in the existing token system (no new
  colours beyond the current palette); hero, cards grid, the two-column Visit block,
  responsive at `40rem`. Reuse `.wrap`, `.eyebrow`, serif headings, dotted leaders.
- **`theme.json`** — document the new template + `nav`.

## Seed (`deploy/seed-demo.php`)

- Create the `home` single collection + its fields.
- Save the one home entry with real copy for "The Copper Table".
- Set `site.home` → `home` (was `menu_items`); keep title/description.
- Golden re-dump on the box so the hourly reset serves it.

## Three-hat review (proportionate — no core change)

**🧑‍💼 Product** — Real problem (the demo needs a credible restaurant front page),
for the demo's guests; keeps the menu live. Not over-built (no booking/ordering
here). ✅

**🏗️ Architect** — Classification: **theme + plugin + seed** in the app repo; zero
core change. The one design choice — homepage as a `single` collection + featured
via the view-data hinge — reuses shipped seams (ADR 0027, single-kind home) exactly
as intended, and is the smallest thing that keeps featured dishes live. No new
capability, no API surface frozen. A reusable *pattern* (a plugin lighting up a
theme homepage) but implemented entirely with existing hooks. ✅

**👷 Principal engineer** — Correctness: template degrades when any field/contrib is
empty; featured selection is deterministic (cache-stable). Perf: featured is a
handful, read once per cached page; no N+1. Testability: the contributor is a pure
`PageContext → array` unit (test: returns `[]` off-home, returns ≤3 published items
on-home, never a draft). Mobile: verify at 375px (hero, cards, two-column Visit
collapse). ✅

**🔒 Security lens (light — public read surface only):** all output is
escape-on-render (data, not HTML, per the hinge contract); the contributor is
**visitor-independent** (no `$_COOKIE`/`$_SESSION`/user) so nothing per-visitor is
baked into the shared page cache; ContentReader is published-only (no draft leak);
`tel:` uses an admin-entered phone, escaped. No write surface, no new route. Nothing
to block. (The real security work lands in Slice C2, which *does* add a public
write.)

## Definition of done

- `entry-home.php` renders all five sections; blanks degrade gracefully.
- Featured dishes come **live** from the menu via the contributor; editing a menu
  item is reflected on the homepage after the page-cache TTL.
- Header nav = Home · Menu; menu still at `/menu_items`.
- Verified at desktop **and 375px** (no horizontal scroll; Visit collapses).
- Plugin CI green (contributor unit test); theme CI green (`php -l`).
- Seed creates `home` + sets `site.home`; box golden re-dumped.
