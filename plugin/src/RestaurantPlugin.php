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
 * Slice 1: the floor (tables). Orders, kitchen, payment, reservations and reports
 * follow, each as its own slice.
 */
final class RestaurantPlugin implements Plugin
{
    /** Matches extra.nimbus.id in composer.json. */
    public const ID = 'danmat.restaurant';

    public function register(PluginContext $context): void
    {
        $context->migrations()->register('001_tables', Schema::tables());

        // One coarse, wildcard-immune capability for v1 (danmat.restaurant:read/write).
        // Fine-grained staff roles are a recorded platform finding (F4), not app hacks.
        $context->capabilities()->declare('Restaurant', ['read', 'write']);

        // Storage is taken lazily, so register() runs no query and loads without a database.
        $storage = static fn (): PluginStorage => $context->storage();
        $tables  = new Tables($storage);

        // The agent surface — every tool gates on danmat.restaurant:read|write (ADR 0016).
        $context->mcp()->register(new RestaurantToolset($tables));

        // The floor board. A staff terminal is a capability-gated ADMIN PAGE, never a
        // public plugin route (routes carry no auth/CSRF). Gated on :write; the handler
        // gets the CSP nonce (2nd arg) and a CSRF token (3rd arg).
        $context->adminPages()->register(
            'restaurant',
            'Floor',
            '🍽️',
            static fn (Request $r, string $nonce = '', string $csrf = ''): string => (new TablesAdmin($tables))->render($csrf, $r->query('ok') ?? $r->query('err'), $r->query('edit'), $r->query('status'), $nonce),
            self::ID . ':write',
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

        // Teach an MCP agent how to drive the restaurant (ADR 0013).
        $context->skills()->register('Restaurant', Guide::text());
    }
}
