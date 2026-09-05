<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

/**
 * The menu as the order surfaces need it — a pickable list. A seam over {@see Menu}
 * (which reads the menu collection through the core content-read capability), so the
 * admin and MCP surfaces depend on this, not on core content, and a test can supply
 * a canned menu without a collection.
 */
interface MenuSource
{
    /**
     * The menu items available to order.
     *
     * @return list<array{id:int,name:string,price:string,category:?string}>
     */
    public function items(): array;
}
