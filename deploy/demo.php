<?php

declare(strict_types=1);

/**
 * Public demo logins for the RAS sign-in "Explore as …" picker (NimbusCMS demo
 * mode, Config::demoAccounts()). The passwords are intentionally PUBLIC — the site
 * is a shared sandbox that resets hourly. Deployed to the site's `config/demo.php`
 * and mounted read-only (see DEPLOY.md). The first entry backs the one-click
 * pre-fill, so the Manager (who can see everything) is listed first.
 */
return [
    'accounts' => [
        ['label' => 'Manager — everything + menu + guests', 'email' => 'manager@ras.demo', 'password' => 'restaurant-demo'],
        ['label' => 'Waiter — the floor', 'email' => 'waiter@ras.demo', 'password' => 'restaurant-demo'],
        ['label' => 'Host — the floor', 'email' => 'host@ras.demo', 'password' => 'restaurant-demo'],
        ['label' => 'Busboy — the floor', 'email' => 'busboy@ras.demo', 'password' => 'restaurant-demo'],
        ['label' => 'Cook — the kitchen', 'email' => 'cook@ras.demo', 'password' => 'restaurant-demo'],
        ['label' => 'Admin — the whole CMS', 'email' => 'admin@ras.demo', 'password' => 'restaurant-demo'],
    ],
];
