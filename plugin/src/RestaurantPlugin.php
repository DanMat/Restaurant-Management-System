<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

use Nimbus\Http\Request;
use Nimbus\Http\Response;
use Nimbus\Plugin\Plugin;
use Nimbus\Plugin\PluginContext;
use Nimbus\Plugin\PluginStorage;

/**
 * The Restaurant Management System, as a NimbusCMS plugin — the application's own
 * logic, co-located in its repository and composed onto a Nimbus site alongside
 * the official CRM (guests) and a theme (public menu). It touches **no core
 * data** and adds **no restaurant-specific logic to Nimbus core**: everything is
 * on its own `rest_*` tables (ADR 0005), behind its own wildcard-immune capability
 * (ADR 0015), on capability-gated admin pages (ADR 0020) and MCP tools (ADR 0016).
 *
 * Slice 1: the floor (tables). Slice 2: orders + line items (menu read via the core
 * content-read capability, ADR 0029). Slice 3: the kitchen display. Slice 4:
 * payment & turn. Slice 5: staff roles — the terminals are gated on fine-grained
 * actions (ADR 0030): floor staff reach tables/orders/payment, cooks the kitchen,
 * managers everything. Slice 6: reservations, which link to CRM guests without the
 * restaurant ever reading CRM data. Slice 7: the manager reports dashboard.
 */
final class RestaurantPlugin implements Plugin
{
    /** Matches extra.nimbus.id in composer.json. */
    public const ID = 'danmat.restaurant';

    public function register(PluginContext $context): void
    {
        $context->migrations()->register('001_tables', Schema::tables());
        $context->migrations()->register('002_orders', Schema::orders());
        $context->migrations()->register('003_reservations', Schema::reservations());

        // Wildcard-immune capability with fine-grained staff actions (ADR 0030, F4):
        //   floor   — waiters/hosts/busboys: tables, orders, payment
        //   kitchen — cooks: the kitchen display
        //   manage  — managers/admins: reports & settings (reports land later)
        //   read/write — the agent/integration surface over MCP
        // Legacy roles map to grants of these; a manager holds floor+kitchen+manage.
        $context->capabilities()->declare('Restaurant', ['read', 'write', 'floor', 'kitchen', 'manage']);

        // Storage is taken lazily, so register() runs no query and loads without a database.
        $storage = static fn (): PluginStorage => $context->storage();
        $tables  = new Tables($storage);
        // The menu is a Nimbus collection, read in-process via the content-read
        // capability (ADR 0029); Orders snapshots a line's name+price through it.
        $menu         = new Menu(static fn () => $context->content());
        $orders       = new Orders($storage, $tables, static fn (int $menuItemId): ?array => $menu->snapshot($menuItemId));
        $reservations = new Reservations($storage, $tables);
        $reports      = new Reports($storage);

        // The agent surface — every tool gates on danmat.restaurant:read|write (ADR 0016).
        $context->mcp()->register(new RestaurantToolset($tables, $orders, $menu, $reservations, $reports));

        // The floor board. A staff terminal is a capability-gated ADMIN PAGE, never a
        // public plugin route (routes carry no auth/CSRF). Gated on :write; the handler
        // gets the CSP nonce (2nd arg) and a CSRF token (3rd arg).
        $context->adminPages()->register(
            'restaurant',
            'Floor',
            '🍽️',
            static fn (Request $r, string $nonce = '', string $csrf = ''): string => (new TablesAdmin($tables))->render($csrf, $r->query('ok') ?? $r->query('err'), $r->query('edit'), $r->query('status'), $nonce),
            self::ID . ':floor',
        );

        $context->adminPages()->action('restaurant', 'table-save', static function (Request $r) use ($tables): Response {
            $fields = [
                'label'  => (string) ($r->input('label') ?? ''),
                'seats'  => (string) ($r->input('seats') ?? ''),
                'status' => (string) ($r->input('status') ?? ''),
            ];
            $idIn = trim((string) ($r->input('id') ?? ''));
            $id   = ($idIn !== '' && ctype_digit($idIn)) ? (int) $idIn : null;
            try {
                $tables->save($id, $fields, date('Y-m-d H:i:s'));
                return Response::redirect('/admin/restaurant?ok=saved');
            } catch (\InvalidArgumentException $e) {
                $msg  = $e->getMessage();
                $code = str_contains($msg, 'already exists') ? 'dupe' : (str_contains($msg, 'label') ? 'nolabel' : 'invalid');
                return Response::redirect('/admin/restaurant?err=' . $code);
            } catch (\Throwable) {
                return Response::redirect('/admin/restaurant?err=invalid');
            }
        });

        $context->adminPages()->action('restaurant', 'table-status', static function (Request $r) use ($tables): Response {
            $idIn = trim((string) ($r->input('id') ?? ''));
            $status = (string) ($r->input('status') ?? '');
            if ($idIn !== '' && ctype_digit($idIn)) {
                try {
                    $tables->setStatus((int) $idIn, $status, date('Y-m-d H:i:s'));
                } catch (\Throwable) {
                    return Response::redirect('/admin/restaurant?err=invalid');
                }
            }
            return Response::redirect('/admin/restaurant?ok=seated');
        });

        $context->adminPages()->action('restaurant', 'table-delete', static function (Request $r) use ($tables): Response {
            $idIn = trim((string) ($r->input('id') ?? ''));
            if ($idIn !== '' && ctype_digit($idIn)) {
                $tables->delete((int) $idIn);
            }
            return Response::redirect('/admin/restaurant?ok=deleted');
        });

        // Orders terminal — list, open-on-table, and a single-order screen with the
        // menu picker. A capability-gated admin page, same as the floor.
        $context->adminPages()->register(
            'restaurant-orders',
            'Orders',
            '🧾',
            static fn (Request $r, string $nonce = '', string $csrf = ''): string => (new OrdersAdmin($orders, $tables, $menu))->render($csrf, $r->query('ok') ?? $r->query('err'), $r->query('view'), $r->query('status'), $nonce),
            self::ID . ':floor',
        );

        // Where an order action returns to: back to the order screen it was on
        // (?view), else the list.
        $backToOrder = static function (Request $r): string {
            $view = trim((string) ($r->input('view') ?? ''));
            return ($view !== '' && ctype_digit($view)) ? '/admin/restaurant-orders?view=' . $view . '&' : '/admin/restaurant-orders?';
        };

        $context->adminPages()->action('restaurant-orders', 'order-open', static function (Request $r) use ($orders): Response {
            $tableIn = trim((string) ($r->input('table_id') ?? ''));
            if ($tableIn === '' || !ctype_digit($tableIn)) {
                return Response::redirect('/admin/restaurant-orders?err=notable');
            }
            try {
                $id = $orders->open((int) $tableIn, date('Y-m-d H:i:s'));
                return Response::redirect('/admin/restaurant-orders?view=' . $id . '&ok=opened');
            } catch (\Throwable) {
                return Response::redirect('/admin/restaurant-orders?err=invalid');
            }
        });

        $context->adminPages()->action('restaurant-orders', 'order-status', static function (Request $r) use ($orders, $backToOrder): Response {
            $base = $backToOrder($r);
            $idIn = trim((string) ($r->input('id') ?? ''));
            if ($idIn !== '' && ctype_digit($idIn)) {
                try {
                    $orders->setStatus((int) $idIn, (string) ($r->input('status') ?? ''), date('Y-m-d H:i:s'));
                } catch (\Throwable) {
                    return Response::redirect($base . 'err=invalid');
                }
            }
            return Response::redirect($base . 'ok=updated');
        });

        $context->adminPages()->action('restaurant-orders', 'order-add-item', static function (Request $r) use ($orders, $backToOrder): Response {
            $base    = $backToOrder($r);
            $orderIn = trim((string) ($r->input('order_id') ?? ''));
            if ($orderIn === '' || !ctype_digit($orderIn)) {
                return Response::redirect($base . 'err=invalid');
            }
            $menuIn = trim((string) ($r->input('menu_item_id') ?? ''));
            $qtyIn  = trim((string) ($r->input('qty') ?? '1'));
            try {
                $orders->addItem(
                    (int) $orderIn,
                    ($menuIn !== '' && ctype_digit($menuIn)) ? (int) $menuIn : null,
                    ($n = (string) ($r->input('name') ?? '')) !== '' ? $n : null,
                    ($p = (string) ($r->input('price') ?? '')) !== '' ? $p : null,
                    (ctype_digit($qtyIn) && (int) $qtyIn > 0) ? (int) $qtyIn : 1,
                    date('Y-m-d H:i:s'),
                );
                return Response::redirect($base . 'ok=added');
            } catch (\Throwable) {
                return Response::redirect($base . 'err=invalid');
            }
        });

        $context->adminPages()->action('restaurant-orders', 'order-set-qty', static function (Request $r) use ($orders, $backToOrder): Response {
            $base   = $backToOrder($r);
            $itemIn = trim((string) ($r->input('item_id') ?? ''));
            $qtyIn  = trim((string) ($r->input('qty') ?? ''));
            if ($itemIn !== '' && ctype_digit($itemIn) && $qtyIn !== '' && ctype_digit($qtyIn)) {
                try {
                    $orders->setItemQty((int) $itemIn, (int) $qtyIn, date('Y-m-d H:i:s'));
                } catch (\Throwable) {
                    return Response::redirect($base . 'err=invalid');
                }
            }
            return Response::redirect($base . 'ok=updated');
        });

        $context->adminPages()->action('restaurant-orders', 'order-remove-item', static function (Request $r) use ($orders, $backToOrder): Response {
            $itemIn = trim((string) ($r->input('item_id') ?? ''));
            if ($itemIn !== '' && ctype_digit($itemIn)) {
                $orders->removeItem((int) $itemIn);
            }
            return Response::redirect($backToOrder($r) . 'ok=removed');
        });

        $context->adminPages()->action('restaurant-orders', 'order-pay', static function (Request $r) use ($orders, $backToOrder): Response {
            $base = $backToOrder($r);
            $idIn = trim((string) ($r->input('id') ?? ''));
            if ($idIn === '' || !ctype_digit($idIn)) {
                return Response::redirect($base . 'err=invalid');
            }
            try {
                // Only the method comes from the request — the amount is computed.
                $orders->pay((int) $idIn, (string) ($r->input('method') ?? ''), date('Y-m-d H:i:s'));
                return Response::redirect($base . 'ok=paid');
            } catch (\Throwable) {
                return Response::redirect($base . 'err=invalid');
            }
        });

        $context->adminPages()->action('restaurant-orders', 'order-delete', static function (Request $r) use ($orders): Response {
            $idIn = trim((string) ($r->input('id') ?? ''));
            if ($idIn !== '' && ctype_digit($idIn)) {
                $orders->delete((int) $idIn);
            }
            return Response::redirect('/admin/restaurant-orders?ok=deleted');
        });

        // Kitchen display — the cook's screen. A capability-gated admin page (not a
        // public route), read-mostly with an advance action per ticket.
        $context->adminPages()->register(
            'restaurant-kitchen',
            'Kitchen',
            '👨‍🍳',
            static fn (Request $r, string $nonce = '', string $csrf = ''): string => (new KitchenAdmin($orders))->render($csrf, $r->query('ok') ?? $r->query('err'), $nonce),
            self::ID . ':kitchen',
        );

        $context->adminPages()->action('restaurant-kitchen', 'advance', static function (Request $r) use ($orders): Response {
            $idIn = trim((string) ($r->input('id') ?? ''));
            if ($idIn !== '' && ctype_digit($idIn)) {
                try {
                    $orders->setStatus((int) $idIn, (string) ($r->input('status') ?? ''), date('Y-m-d H:i:s'));
                } catch (\Throwable) {
                    return Response::redirect('/admin/restaurant-kitchen?err=invalid');
                }
            }
            return Response::redirect('/admin/restaurant-kitchen?ok=advanced');
        });

        // Reservations — the book. Floor staff manage bookings; a booking may link to
        // a CRM guest, but this page only links out (the CRM page is separately gated).
        $context->adminPages()->register(
            'restaurant-reservations',
            'Reservations',
            '📅',
            static fn (Request $r, string $nonce = '', string $csrf = ''): string => (new ReservationsAdmin($reservations, $tables))->render($csrf, $r->query('ok') ?? $r->query('err'), $r->query('edit'), $r->query('status'), $nonce),
            self::ID . ':floor',
        );

        $context->adminPages()->action('restaurant-reservations', 'reservation-save', static function (Request $r) use ($reservations): Response {
            $fields = [
                'party_name'  => (string) ($r->input('party_name') ?? ''),
                'party_size'  => (string) ($r->input('party_size') ?? ''),
                'reserved_at' => (string) ($r->input('reserved_at') ?? ''),
                'table_id'    => (string) ($r->input('table_id') ?? ''),
                'contact_id'  => (string) ($r->input('contact_id') ?? ''),
                'status'      => (string) ($r->input('status') ?? ''),
                'notes'       => (string) ($r->input('notes') ?? ''),
            ];
            $idIn = trim((string) ($r->input('id') ?? ''));
            $id   = ($idIn !== '' && ctype_digit($idIn)) ? (int) $idIn : null;
            try {
                $reservations->save($id, $fields, date('Y-m-d H:i:s'));
                return Response::redirect('/admin/restaurant-reservations?ok=saved');
            } catch (\InvalidArgumentException $e) {
                $code = str_contains($e->getMessage(), 'party name') ? 'noname' : 'invalid';
                return Response::redirect('/admin/restaurant-reservations?err=' . $code);
            } catch (\Throwable) {
                return Response::redirect('/admin/restaurant-reservations?err=invalid');
            }
        });

        $context->adminPages()->action('restaurant-reservations', 'reservation-delete', static function (Request $r) use ($reservations): Response {
            $idIn = trim((string) ($r->input('id') ?? ''));
            if ($idIn !== '' && ctype_digit($idIn)) {
                $reservations->delete((int) $idIn);
            }
            return Response::redirect('/admin/restaurant-reservations?ok=deleted');
        });

        // Reports — the manager dashboard. Read-only, gated on the manage action.
        $context->adminPages()->register(
            'restaurant-reports',
            'Reports',
            '📈',
            static fn (Request $r, string $nonce = '', string $csrf = ''): string => (new ReportsAdmin($reports))->render($csrf, null, $nonce),
            self::ID . ':manage',
        );

        // Teach an MCP agent how to drive the restaurant (ADR 0013).
        $context->skills()->register('Restaurant', Guide::text());
    }
}
