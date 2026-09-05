# ADR-0001 — The Restaurant is a co-located Nimbus plugin, composed with the CRM

- **Status:** Accepted
- **Date:** 2026-09-05
- **Context:** rebuilding the Restaurant Management System on NimbusCMS
  (`nimbus-rebuild`); the Menu vertical is already proven as collections, and the
  official CRM plugin is feature-complete.

## Context

The Menu vertical modelled categories and priced items as Nimbus collections and
served them over the read API with no core change. The next verticals — Tables,
Orders, Kitchen, Reservations, Reports — are **operational state and behaviour**,
not content, and the platform-validation rule forbids restaurant-specific logic
in Nimbus core (see [`PLATFORM-VALIDATION.md`](../PLATFORM-VALIDATION.md)). So the
question this ADR settles is: *what, architecturally, is the Restaurant on
Nimbus, and how does the app hold its own logic?* (finding **F2**).

## Decision

The Restaurant is a **Nimbus plugin (`danmat.restaurant`) co-located in this
repository** under `plugin/`, mirroring the official Inventory/Commerce/CRM
plugin pattern (own `rest_*` tables, a wildcard-immune capability, capability-
gated admin pages, plugin routes for the terminals, an MCP toolset, a guide).
It is **composed** with:

- the official **CRM** plugin — guests are CRM contacts; reservations/orders
  reference a contact id (the reason the CRM was built first);
- the **menu collections** — content, unchanged;
- a **theme** for the public menu and staff terminals.

A deployed **Nimbus site** consumes the co-located plugin through a Composer
**path repository** (Nimbus discovers plugins from `installed.json` by
`type: nimbuscms-plugin`), with the CRM and theme wired from their GitHub packages
as the demo image already does. Nimbus stays the root project; no Packagist
publication is needed for the app's own plugin.

## Alternatives considered

- **Compose only existing generic plugins** (Commerce for orders/payment,
  Inventory for stock) with a thin restaurant plugin for the rest. Best reuse
  story, but it stretches Commerce beyond a restaurant's fit (covers/tickets/
  floor turn are not e-commerce checkout) and multiplies integration surface. We
  keep the *option* to lean on generic capabilities where they genuinely fit, but
  the app's spine is its own plugin.
- **Collections-only, driven by an external app repo over the API.** Rejected:
  live table status and kitchen flow in generic content collections is awkward,
  and an external process re-implements what plugin hinges already provide.

## Consequences

- **Good:** zero core change for the app's own logic; the entire mature plugin
  hinge system (capability, admin, routes, MCP, migrations) is reused; the app is
  self-contained in one repo; guests come free from the CRM.
- **Cost:** a deployed site must wire a Composer path repo to this repo's
  `plugin/`. Documented in the deploy slice.
- **Forces later:** relation expansion in the read API (finding **F1**) when the
  public menu/orders frontend needs it — that becomes the first, ADR-backed,
  reusable Nimbus core PR of this rebuild.

## Follow-ups

- Update **F2** in the validation ledger to "decided" and point at this ADR.
- Every build slice still runs both Nimbus review skills before code.
