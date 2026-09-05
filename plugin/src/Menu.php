<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

use Nimbus\Content\ContentReader;

/**
 * The menu, read from the `menu_items` **collection** (content, not this plugin's
 * tables) through the core plugin content-read capability (ADR 0029). A thin
 * adapter: it maps a collection entry — `{id, title, fields:{price, category}}` —
 * to the two shapes the app needs, the **picker list** and a **price snapshot**.
 *
 * The ContentReader is resolved lazily (it needs a database), so constructing this
 * runs no query.
 */
final class Menu implements MenuSource
{
    /** Collection handles the Menu vertical provisioned onto Nimbus. */
    public const COLLECTION = 'menu_items';

    /** @param \Closure():ContentReader $reader */
    public function __construct(private \Closure $reader)
    {
    }

    /**
     * The menu, for a picker: id (the collection entry id, used as `menu_item_id`),
     * name, price and category label.
     *
     * @return list<array{id:int,name:string,price:string,category:?string}>
     */
    public function items(): array
    {
        $out = [];
        foreach (($this->reader)()->entries(self::COLLECTION, 500) as $entry) {
            $out[] = [
                'id'       => (int) ($entry['id'] ?? 0),
                'name'     => (string) ($entry['title'] ?? ''),
                'price'    => $this->price($entry),
                'category' => $this->category($entry),
            ];
        }
        return $out;
    }

    /**
     * The name + unit price to snapshot onto an order line, for one menu item id,
     * or null if there is no such published item.
     *
     * @return array{name:string,price:string}|null
     */
    public function snapshot(int $menuItemId): ?array
    {
        $entry = ($this->reader)()->entry(self::COLLECTION, $menuItemId);
        if ($entry === null) {
            return null;
        }
        return ['name' => (string) ($entry['title'] ?? ''), 'price' => $this->price($entry)];
    }

    /** @param array<string,mixed> $entry */
    private function price(array $entry): string
    {
        $fields = is_array($entry['fields'] ?? null) ? $entry['fields'] : [];
        $raw    = $fields['price'] ?? 0;
        return number_format(is_numeric($raw) ? (float) $raw : 0.0, 2, '.', '');
    }

    /** @param array<string,mixed> $entry */
    private function category(array $entry): ?string
    {
        $fields = is_array($entry['fields'] ?? null) ? $entry['fields'] : [];
        $rel    = $fields['category'] ?? null;
        if (is_array($rel) && isset($rel[0]) && is_array($rel[0])) {
            $title = (string) ($rel[0]['title'] ?? '');
            return $title === '' ? null : $title;
        }
        return null;
    }
}
