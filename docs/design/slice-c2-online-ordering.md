# Slice C2 — Online ordering + simulated checkout

**Status:** design (pre-build) · **Branch:** `nimbus-rebuild` · **Scope chosen by Dan:**
*simulated checkout* — a public menu → cart → checkout that places a real order into
the kitchen queue, with a clearly-labelled **fake** payment (no real money, no
processor, no card capture).

This is the first **public write surface** in the app, so it carries a full security
review (public `/ext` routes have no admin auth and no automatic CSRF — ADR 0017).

## Why / what

The 2014 original was staff-only. Modern guests order takeaway online. Add a public
ordering page where a guest picks dishes, leaves a name + phone, and "pays" (demo);
the order drops straight into the **existing kitchen display** as a new ticket.

**In scope:** pickup/takeaway only; a server-rendered cart (no JS required); a
simulated "Pay" that marks the order paid and sends it to the kitchen; a
confirmation page with an order number.

**Out of scope:** real payments/processor (**forbidden** — see Security), delivery,
accounts/logins for guests, order tracking beyond the confirmation page, editing a
placed order.

## Order model (fold into the unreleased `002_orders` migration)

`rest_order` is table-centric (`table_id NOT NULL`, no channel/customer). Following
this repo's established pattern (the payment columns were folded into `002_orders`
while unreleased), extend `002_orders` — every deploy re-migrates from an empty DB,
so `CREATE TABLE IF NOT EXISTS` stays idempotent and no fragile `ALTER` is needed:

- `table_id BIGINT UNSIGNED NULL` (was NOT NULL) — an online order has no table.
- `channel ENUM('dine_in','online') NOT NULL DEFAULT 'dine_in'`.
- `customer_name VARCHAR(120) NULL`, `customer_phone VARCHAR(40) NULL` — online only.

Dine-in orders are unaffected (channel defaults to `dine_in`, table_id still set).

## Orders service

Add one method, mirroring the discipline of `open()`/`addItem()`/`pay()`:

```
placeOnline(array $cart, string $name, string $phone, string $now): array
```

- `$cart` = `[ [menu_item_id, qty], … ]` from the request. For each line the service
  **snapshots name + unit price from the menu** via `Menu::snapshot($id)` (ADR 0029)
  — **client prices are never trusted**; an unknown/​unpublished id is dropped.
- Caps: qty per line 1..`MAX_QTY` (999, existing); at most `MAX_ONLINE_LINES` (=40)
  distinct lines; empty cart → rejected.
- Creates a **table-less** `rest_order` (`channel='online'`, `table_id=NULL`,
  `status='sent'`, `paid=1`, `amount_paid`=computed total, `payment_method='online-demo'`,
  customer name/phone), inserts the snapshotted lines, all in one transaction. It does
  **not** touch table state (there is no table). Returns the created order.
- Total is always computed server-side from the snapshotted lines (never posted).

`status='sent'` means the order appears immediately in the kitchen **New** column
next to dine-in tickets. `payment_method='online-demo'` makes the simulation obvious
in the data and in Reports.

## Public route (ADR 0017) — `/ext/restaurant`

- `GET /ext/restaurant/order` — the ordering page: the live menu grouped by category,
  each item with a **qty number input** (0..99), a **name** + **phone** field, and a
  **"Place order · Pay (demo)"** button. A visible banner: *"Demo checkout — no real
  payment is taken."* **No card fields exist.** Rendered by the theme
  (`order.php`), fully server-side — **no JavaScript required**, so no inline script
  and no public-page CSP concern.
- `POST /ext/restaurant/order` — reads the qty inputs, builds the cart, calls
  `placeOnline()`, redirects to the confirmation page with the new order id.
- `GET /ext/restaurant/order/{id}/confirmed` — a confirmation: order number, the
  itemised lines, total, and "we're preparing it" — reads only that order, shows no
  other order's data, and never lists orders (non-enumerating beyond the id given).

Header/nav gains an **Order** link.

## Kitchen / Reports integration

- `KitchenAdmin` tickets show `table_label`; for an online order (null table) show
  **"Online · {customer_name}"** instead — escape-on-render. `ticketsByStatus()` and
  `get()` already return the row; add the channel/customer fields and a null-safe
  label. Cooks advance online tickets exactly like dine-in (New → Preparing → Ready).
- Reports already sum `amount_paid` over paid orders, so online sales count in
  revenue automatically (correct). No Reports change required beyond it continuing to
  work with a null table.

## Security review (Attacker / Defender / QA) — the crux

Public, unauthenticated **write**. Reviewed hard.

### 🔴 Attacker → ⚪ Defender (control · severity)

1. **Price / total tampering** — post cheaper prices or a fake total.
   → Server **snapshots unit price from the published menu** (`Menu::snapshot`) and
   **computes** the total; the request carries only `menu_item_id` + `qty`. Client
   price/total are ignored entirely. **Mitigated. (would be High if trusted.)**
2. **Unknown / unpublished item injection** — order a draft or arbitrary id.
   → `Menu::snapshot` returns null for anything not live-published; such lines are
   dropped. **Mitigated.**
3. **Over-posting / oversized order (DoS)** — 10⁶ qty, thousands of lines.
   → qty clamped 1..999 per line; ≤40 distinct lines; empty → rejected; name/phone
   length-capped (120/40) and truncated. Body size bounded by the platform. **Medium
   → mitigated.**
4. **SQLi** — via ids/qty/name/phone.
   → All writes are parameter-bound (existing `Orders` discipline); ids/qtys cast to
   int; channel is a write-time enum literal, never interpolated. **Mitigated.**
5. **Stored XSS** — `customer_name`/`phone` shown to staff (kitchen) and on the
   confirmation page.
   → Escape-on-render everywhere (`View::e` in kitchen ticket + theme confirmation);
   stored raw, escaped out. **Mitigated (High if not).**
6. **Spam / abuse (no CSRF on a public route)** — scripted flood of fake orders.
   → This is not classic CSRF (no auth/session to ride). Controls: a **per-IP
   throttle** (N orders/minute via plugin storage), a **honeypot** hidden field
   (bots fill it → silently dropped), the order-size caps above, the hourly reset,
   and Cloudflare in front. Proportionate for a demo. **Medium → mitigated; residual
   accepted (demo, resets hourly).**
7. **PII** — how much guest data, where.
   → Only an optional name + phone, on the order row, escaped on render, wiped every
   hour by the reset. **No card/email/address.** **Low.**
8. **Real-payment misuse** — could this take money?
   → **No.** There is no processor, no card field, no money movement — a labelled
   simulation that only sets `paid=1, payment_method='online-demo'`. Building real
   payment is explicitly **out of scope and prohibited** for me to wire. **n/a.**

### 🟢 QA / permanence (regression tests)

- `placeOnline` **snapshots menu price, ignoring a posted price**; computes the total.
- unknown/unpublished id **dropped**; empty cart **rejected**; qty and line-count
  **clamped**.
- creates a **table-less** order (`channel='online'`, `table_id NULL`, `status='sent'`,
  `paid=1`) that **appears in `ticketsByStatus(['sent'])`** — i.e. reaches the kitchen.
- kitchen ticket + confirmation **escape** a hostile `customer_name`.
- per-IP throttle refuses the (N+1)th order in the window; honeypot-filled request is
  dropped.
- **Merge bar:** no Critical/High left open; all High-potentials (price tamper, XSS)
  have a control + a failing-first test. Security-green to build.

## Three-hat platform review

**🧑‍💼 Product** — A real, common capability (online takeaway) that showcases the
platform end-to-end (public write → domain service → kitchen → reports). Demo-honest
(simulated pay). Doesn't distort the CMS. ✅

**🏗️ Architect** — Classification: **app plugin + theme** (the plugin owns the public
route + service + schema; the theme renders). Reuses ADR 0017 (public routes), ADR
0029 (menu read), the existing Orders/kitchen/reports. **No core change** — the
public-route, content-read, and view seams already exist. The order-model extension
is folded into the unreleased migration (no new hinge). Smallest cut that delivers
the loop. One watch-item: public write surfaces are new for this app — hence the full
security pass and the throttle. ✅

**👷 Principal engineer** — Server-computed totals, bound SQL, enum allow-lists,
escape-on-render, transactional insert — all carried from the dine-in Orders code.
JS-free page keeps the CSP surface nil. Testable service (`placeOnline` is
DB-backed, faked menu). Mobile: the order form at 375px (qty inputs, sticky total).
✅

## Decisions (confirmed by Dan 2026-09-06)

1. **Guest details** — **name AND phone both required** (validated non-empty,
   length-capped 120/40, escaped on render).
2. **Anti-abuse** — **per-IP throttle + honeypot + order-size caps** (no extra
   confirm step); proportionate for a demo that resets hourly behind Cloudflare.

## Definition of done

- `002_orders` extended (nullable table, channel, customer fields); dine-in unchanged.
- `Orders::placeOnline()` with snapshotting, caps, transaction; unit-tested.
- Public `/ext/restaurant/order` (GET form + POST place + confirmation), JS-free,
  throttled + honeypot; theme `order.php` + confirmation, responsive @375px.
- Kitchen shows online tickets ("Online · name"); Reports counts online sales.
- Header nav gains **Order**; seed adds a couple of sample online orders (optional).
- Security regression tests green; PHPStan L6 + cs-fixer + plugin CI green.
