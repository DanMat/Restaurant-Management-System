<?php

declare(strict_types=1);

/**
 * The Restaurant application's content model, declared as data.
 *
 * This is the single source of truth for the collections the app needs from
 * Nimbus. bin/provision-menu.sh reads it and creates them through Nimbus's
 * public admin API — the app "installs itself" onto a stock Nimbus instance,
 * with no changes to Nimbus core.
 *
 * Menu is the first vertical. Orders, tables, kitchen and reports follow, each
 * added here as it is built — and each one that Nimbus cannot yet express
 * becomes a finding in docs/PLATFORM-VALIDATION.md.
 */

return [
    'categories' => [
        'name'   => 'Categories',
        'icon'   => 'C',
        'fields' => [
            ['handle' => 'name', 'label' => 'Name', 'type' => 'text'],
        ],
    ],

    'menu_items' => [
        'name'   => 'Menu Items',
        'icon'   => 'M',
        'fields' => [
            ['handle' => 'price',    'label' => 'Price',    'type' => 'number'],
            ['handle' => 'category', 'label' => 'Category', 'type' => 'relation', 'target' => 'categories'],
        ],
    ],
];
