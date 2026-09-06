<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

/**
 * The menu as the app's surfaces need it. A seam over {@see Menu} (which reads the
 * menu collection through the core content-read capability), so the admin, MCP and
 * public surfaces depend on this, not on core content, and a test can supply a
 * canned menu without a collection.
 */
interface MenuSource
{
    /**
     * The menu items available to order — a pickable list.
     *
     * @return list<array{id:int,name:string,price:string,category:?string}>
     */
    public function items(): array;

    /**
     * A handful of items to feature on the public homepage — items with a
     * description first, then others, capped at $limit. Deterministic and
     * visitor-independent (safe for the page cache; see ADR 0027).
     *
     * @return list<array{name:string,price:string,category:?string,blurb:string}>
     */
    public function featured(int $limit = 3): array;
}
