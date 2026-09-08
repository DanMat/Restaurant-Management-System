<?php

declare(strict_types=1);

/**
 * RAS demo seed — turns a freshly-installed Nimbus site into an explorable
 * restaurant demo: capability roles, one login per role (public passwords, on
 * purpose), the menu, and enough live floor/order/reservation data that every
 * terminal has something to show.
 *
 * Assumes an empty, migrated database with an admin already created
 * (`nimbus migrate` + `nimbus install`), which is exactly the state the hourly
 * reset leaves — so this script does not need to be idempotent, it needs to be
 * run against a fresh DB. Run inside the site container with the DB env set:
 *
 *   php deploy/seed-demo.php
 */

// The Nimbus app root differs by image (/app on the platform image); find autoload.
$__autoload = null;
foreach (['/app/vendor/autoload.php', __DIR__ . '/../vendor/autoload.php', '/var/www/html/vendor/autoload.php'] as $__p) {
    if (is_file($__p)) {
        $__autoload = $__p;
        break;
    }
}
require $__autoload ?? throw new RuntimeException('Could not locate vendor/autoload.php');

use DanMat\Restaurant\Menu;
use DanMat\Restaurant\Orders;
use DanMat\Restaurant\Reservations;
use DanMat\Restaurant\Tables;
use Nimbus\Auth\Password;
use Nimbus\Auth\RoleRepository;
use Nimbus\Content\CollectionRepository;
use Nimbus\Content\CollectionService;
use Nimbus\Content\ContentReader;
use Nimbus\Content\EntryInput;
use Nimbus\Content\EntryRepository;
use Nimbus\Content\EntryService;
use Nimbus\Content\FieldTypeRegistry;
use Nimbus\Content\RelationRepository;
use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
use Nimbus\Settings\SettingsRepository;
use Nimbus\Support\EventDispatcher;
use NimbusCMS\Crm\Contacts;

$db = new Connection([
    'host' => getenv('DB_HOST') ?: 'db',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'name' => getenv('DB_NAME') ?: 'nimbus',
    'user' => getenv('DB_USER') ?: 'nimbus',
    'pass' => (string) (getenv('DB_PASS') ?: ''),
]);
$pdo = $db->pdo();
$now = date('Y-m-d H:i:s');

// The one public demo password. These accounts exist to be logged into by anyone
// exploring the demo, and the site resets hourly — so this is intentionally not a
// secret.
$demoPassword = 'restaurant-demo';

echo "RAS demo seed…\n";

// --- 1) Capability roles ---------------------------------------------------
// Floor staff run the room; the cook runs the kitchen; the manager runs
// everything AND holds nimbuscms.crm:read so opening a reservation's guest
// record works for them — and visibly does not for floor staff (the PII gate).
$roles = new RoleRepository($db);
$roleId = [
    'Waiter'  => $roles->create('Waiter', ['danmat.restaurant:floor'], false),
    'Host'    => $roles->create('Host', ['danmat.restaurant:floor'], false),
    'Busboy'  => $roles->create('Busboy', ['danmat.restaurant:floor'], false),
    'Cook'    => $roles->create('Cook', ['danmat.restaurant:kitchen'], false),
    'Manager' => $roles->create('Manager', [
        'danmat.restaurant:floor',
        'danmat.restaurant:kitchen',
        'danmat.restaurant:manage',
        'nimbuscms.crm:read',
        'nimbuscms.crm:write',
        // The manager owns the menu — grant content write on the menu collections
        // (write implies read) so they can edit items/categories from the CMS.
        // Scoped to these collections only: no schema:write, no other content.
        'menu_items:write',
        'categories:write',
    ], false),
];
echo "  roles: " . implode(', ', array_keys($roleId)) . "\n";

// --- 2) One demo user per role --------------------------------------------
$makeUser = static function (string $name, string $email) use ($pdo, $now, $demoPassword): int {
    $pdo->prepare('INSERT INTO nb_users (name, email, password, role, created_at, updated_at) VALUES (:n,:e,:p,:r,:c,:u)')
        ->execute(['n' => $name, 'e' => $email, 'p' => Password::hash($demoPassword), 'r' => 'editor', 'c' => $now, 'u' => $now]);
    return (int) $pdo->lastInsertId();
};
$staff = [
    ['Wendy Waiter', 'waiter@ras.demo', 'Waiter'],
    ['Hank Host', 'host@ras.demo', 'Host'],
    ['Bianca Busboy', 'busboy@ras.demo', 'Busboy'],
    ['Cody Cook', 'cook@ras.demo', 'Cook'],
    ['Morgan Manager', 'manager@ras.demo', 'Manager'],
];
foreach ($staff as [$name, $email, $role]) {
    $roles->assignToUser($makeUser($name, $email), $roleId[$role]);
    echo "  user: {$email}  ({$role})\n";
}

// --- 3) The menu (collections + entries) -----------------------------------
$collections = new CollectionService($db, new CollectionRepository($db));
$collections->create('categories', 'Categories', '#', '', ['kind' => 'collection', 'permissions' => []], [
    ['handle' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => false, 'options' => []],
]);
$collections->create('menu_items', 'Menu Items', '#', '', ['kind' => 'collection', 'permissions' => []], [
    ['handle' => 'price', 'label' => 'Price', 'type' => 'number', 'required' => false, 'options' => []],
    ['handle' => 'category', 'label' => 'Category', 'type' => 'relation', 'required' => false, 'options' => ['target' => 'categories']],
    ['handle' => 'body', 'label' => 'Description', 'type' => 'textarea', 'required' => false, 'options' => []],
]);
$repo    = new CollectionRepository($db);
$entries = new EntryService($db, new EntryRepository($db), new RelationRepository($db), new FieldTypeRegistry(), new EventDispatcher());
$catCol  = $repo->findByHandle('categories');
$menuCol = $repo->findByHandle('menu_items');

$slug   = static fn (string $s): string => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)) ?? '', '-');
$catIds = [];
foreach (['Soup', 'Appetizer', 'Main Course', 'Dessert'] as $cat) {
    $r = $entries->save($catCol, new EntryInput($cat, $slug($cat), 'published', ['name' => $cat], '2024-01-01 00:00:00'), null, null);
    $catIds[$cat] = (int) $r->entryId;
}
$menu = [
    ['Miso Soup', 3.50, 'Soup', 'Dashi, silken tofu, spring onion.'],
    ['Chicken Soup', 4.99, 'Soup', ''],
    ['Goulash Soup', 4.00, 'Soup', ''],
    ['Guacamole', 5.50, 'Appetizer', 'Hand-mashed, lime, warm corn chips.'],
    ['Pepperoni Bread', 6.25, 'Appetizer', ''],
    ['Artichoke Spinach Dip', 4.00, 'Appetizer', ''],
    ['Grilled Salmon', 9.99, 'Main Course', 'Seasonal greens, brown butter.'],
    ['Chicken Marsala', 8.21, 'Main Course', ''],
    ['Salsa Chicken', 10.99, 'Main Course', ''],
    ['Fudge', 4.99, 'Dessert', ''],
    ['Apple Crisp', 4.25, 'Dessert', 'Oat crumble, vanilla cream.'],
    ['Parfait', 3.99, 'Dessert', ''],
];
foreach ($menu as [$name, $price, $cat, $desc]) {
    $entries->save($menuCol, new EntryInput($name, $slug($name), 'published', [
        'price' => $price, 'category' => [$catIds[$cat]], 'body' => $desc,
    ], '2024-01-01 00:00:00'), null, null);
}
echo "  menu: " . count($catIds) . " categories, " . count($menu) . " items\n";

// --- 3b) The homepage (a `single`-kind collection: one editable entry) ------
// The public root ("/") renders this as a restaurant front page (theme
// `entry-home.php`); featured dishes come live from the menu via the plugin's
// view-data contributor. Modeled as content so the copy is editable in the CMS.
$collections->create('home', 'Home', '#', '', ['kind' => 'single', 'permissions' => []], [
    ['handle' => 'hero_kicker', 'label' => 'Hero kicker', 'type' => 'text', 'required' => false, 'options' => []],
    ['handle' => 'hero_title', 'label' => 'Hero title', 'type' => 'text', 'required' => false, 'options' => []],
    ['handle' => 'hero_tagline', 'label' => 'Hero tagline', 'type' => 'textarea', 'required' => false, 'options' => []],
    ['handle' => 'about_title', 'label' => 'About title', 'type' => 'text', 'required' => false, 'options' => []],
    ['handle' => 'about_body', 'label' => 'About body', 'type' => 'textarea', 'required' => false, 'options' => []],
    ['handle' => 'hours', 'label' => 'Hours', 'type' => 'textarea', 'required' => false, 'options' => []],
    ['handle' => 'address', 'label' => 'Address', 'type' => 'textarea', 'required' => false, 'options' => []],
    ['handle' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => false, 'options' => []],
]);
$homeCol = $repo->findByHandle('home');
$entries->save($homeCol, new EntryInput('The Copper Table', 'home', 'published', [
    'hero_kicker'  => 'Est. 2014 · Modern American',
    'hero_title'   => 'The Copper Table',
    'hero_tagline' => 'A neighbourhood kitchen for lunch and dinner — seasonal plates, an easy room, and a short list done well.',
    'about_title'  => 'About the table',
    'about_body'   => "We opened on a corner in 2014 with a wood-topped bar and a small menu that changes with the season. Everything is cooked to order; nothing leaves the pass we wouldn't eat ourselves.\n\nToday the room runs on the Restaurant Automation System — a live NimbusCMS demo.",
    'hours'        => "Mon–Thu · 11:00–22:00\nFri–Sat · 11:00–23:00\nSunday · 10:00–21:00",
    'address'      => "18 Copper Lane\nOld Town\nEC1 4RS",
    'phone'        => '020 7946 0142',
], '2024-01-01 00:00:00'), null, null);
echo "  homepage: 1 single collection + entry\n";

// --- 3c) Site settings: brand + render the homepage at the root ------------
// These are DB settings (nb_settings), which shadow config/site.php at runtime —
// so the guest-facing root ("/") shows the branded homepage, not the bare Nimbus
// placeholder. Seeded here so a from-scratch rebuild matches the golden restore.
(new SettingsRepository($db))->setMany([
    'site.title'       => 'The Copper Table',
    'site.description' => 'A restaurant running on the Restaurant Automation System — a live NimbusCMS demo.',
    'site.home'        => 'home',
]);
echo "  settings: home -> home, brand -> The Copper Table\n";

// --- 3d) The Journal (a `blog` collection, served by the official Blog plugin) ---
// A plain content collection named `blog`; the Blog plugin turns it into a real
// blog (per-post SEO head, an RSS feed at /ext/blog/feed.xml, /tag/{tag} archives),
// and the theme renders the list + detail (collection-blog / entry-blog).
$collections->create('blog', 'Journal', '#', '', ['kind' => 'collection', 'permissions' => []], [
    ['handle' => 'summary', 'label' => 'Summary', 'type' => 'textarea', 'required' => false, 'options' => []],
    ['handle' => 'body', 'label' => 'Body', 'type' => 'textarea', 'required' => false, 'options' => []],
    ['handle' => 'cover', 'label' => 'Cover', 'type' => 'text', 'required' => false, 'options' => []],
    ['handle' => 'tags', 'label' => 'Tags', 'type' => 'text', 'required' => false, 'options' => []],
    ['handle' => 'canonical_url', 'label' => 'Canonical URL', 'type' => 'text', 'required' => false, 'options' => []],
]);
$blogCol = $repo->findByHandle('blog');
$posts = [
    [
        'title'   => 'The spring menu is here',
        'date'    => '2026-03-18 09:00:00',
        'tags'    => 'menu, seasonal',
        'summary' => 'Peas, asparagus and the first herbs of the year are on. A few winter plates step down; the salmon stays.',
        'body'    => "The season turned, and so did the pass. From this week the board leans green: sweet peas, grilled asparagus, and the first soft herbs from the garden up the road.\n\n## What is new\n\n- **Guacamole**, hand-mashed with lime and warm corn chips, back on the appetizers.\n- **Grilled Salmon** with seasonal greens and brown butter, which is not going anywhere.\n- A lighter **Apple Crisp** to finish, oat crumble and vanilla cream.\n\nThe short list is the point. We cook what is good this week and let it change when the market does. Come hungry.",
    ],
    [
        'title'   => 'Where the fish comes from',
        'date'    => '2026-02-27 09:00:00',
        'tags'    => 'sourcing, kitchen',
        'summary' => 'Our salmon is a day-boat catch from the same supplier we have used since 2014. Here is why that matters on the plate.',
        'body'    => "People ask about the salmon, so here is the honest answer.\n\nIt comes in from a day-boat supplier we have worked with since we opened in 2014. Landed one day, on your plate the next. No middle week in a freezer.\n\n## Why we bother\n\nFresh fish needs almost nothing done to it. A hot pan, a little brown butter, the greens that are good that morning. When the raw material is right, the kitchen's job is to stay out of the way.\n\nIf a delivery is not up to it, it does not go on the board that night. That is the whole rule.",
    ],
    [
        'title'   => 'You can now order online',
        'date'    => '2026-01-15 09:00:00',
        'tags'    => 'news, ordering',
        'summary' => 'Pickup orders straight from the menu, paid on the site. The same system the floor runs on, opened up to the street.',
        'body'    => "Something we could not do ten years ago: you can now order from the menu and pay right here on the site, for pickup.\n\nIt runs on the very same system the floor uses for dine-in orders, so the kitchen sees your ticket the moment it lands, in the same queue as the room. No second screen, no lost slips.\n\n## How it works\n\n- Browse the [menu](/menu_items) and add what you want.\n- Leave a name and phone, pay, and pick a time.\n- We fire it when it is due, not the second it is placed, so it is hot when you arrive.\n\nDine-in is still the heart of the place. This is just the door held open a little wider.",
    ],
];
foreach ($posts as $p) {
    $entries->save($blogCol, new EntryInput($p['title'], $slug($p['title']), 'published', [
        'summary' => $p['summary'], 'body' => $p['body'], 'cover' => '', 'tags' => $p['tags'], 'canonical_url' => '',
    ], $p['date']), null, null);
}
echo "  journal: 1 blog collection, " . count($posts) . " posts\n";

// --- 4) Live floor / orders / reservations ---------------------------------
$storage      = static fn (): PluginStorage => new PluginStorage($db);
$tables       = new Tables($storage);
$reservations = new Reservations($storage, $tables);
// A menu-backed snapshot so online orders (placeOnline) can look items up by id;
// the manual dine-in lines below pass name+price directly and don't use it.
$menu         = new Menu(static fn (): ContentReader => new ContentReader($db, new FieldTypeRegistry()));
$orders       = new Orders($storage, $tables, static fn (int $id): ?array => $menu->snapshot($id));

$t = [];
foreach ([['1', 2], ['2', 4], ['3', 4], ['4', 2], ['5', 6], ['6', 2], ['Patio 1', 4], ['Patio 2', 4]] as [$label, $seats]) {
    $t[$label] = $tables->save(null, ['label' => $label, 'seats' => (string) $seats], $now);
}
$tables->setStatus($t['5'], 'reserved', $now); // held for tonight's booking

// An open order on table 2, mid-service and sent to the kitchen.
$o1 = $orders->open($t['2'], $now);
$orders->addItem($o1, null, 'Grilled Salmon', '9.99', 2, $now);
$orders->addItem($o1, null, 'Guacamole', '5.50', 1, $now);
$orders->setStatus($o1, 'sent', $now);
// A second order on table 3, ready for the pass.
$o2 = $orders->open($t['3'], $now);
$orders->addItem($o2, null, 'Chicken Marsala', '8.21', 1, $now);
$orders->setStatus($o2, 'ready', $now);

// Two just-settled tables — paying turns each to `dirty` (awaiting bussing), which
// also gives Reports some revenue. No throwaway tables: it happens on real ones.
$paid1 = $orders->open($t['6'], $now);
$orders->addItem($paid1, null, 'Fudge', '4.99', 2, $now);
$orders->pay($paid1, 'card', $now);
$paid2 = $orders->open($t['Patio 1'], $now);
$orders->addItem($paid2, null, 'Miso Soup', '3.50', 3, $now);
$orders->pay($paid2, 'cash', $now);

// A takeaway order placed online (simulated checkout) — it lands in the kitchen
// queue as a "New" ticket labelled by the guest, next to the dine-in tickets. Pick
// two real menu items by id from the live menu so the snapshot resolves.
$byName = [];
foreach ($menu->items() as $mi) {
    $byName[$mi['name']] = $mi['id'];
}
$onlineCart = [];
foreach (['Salsa Chicken' => 1, 'Guacamole' => 2] as $dish => $qty) {
    if (isset($byName[$dish])) {
        $onlineCart[] = ['menu_item_id' => $byName[$dish], 'qty' => $qty];
    }
}
if ($onlineCart !== []) {
    $orders->placeOnline($onlineCart, 'Grace Hopper', '555-0148', $now);
}

// A guest in the CRM, and a reservation linked to them — floor staff see the
// booking; only the manager (crm:read) can open the guest record.
$crm       = new Contacts($storage);
$contactId = $crm->save(null, ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test', 'phone' => '555-0101'], $now);
$reservations->save(null, ['party_name' => 'Lovelace', 'party_size' => '4', 'reserved_at' => date('Y-m-d 19:30:00'), 'table_id' => (string) $t['5'], 'contact_id' => (string) $contactId, 'notes' => 'Window seat.'], $now);
$reservations->save(null, ['party_name' => 'Turing', 'party_size' => '2', 'reserved_at' => date('Y-m-d 20:00:00')], $now);

echo "  floor: 8 tables (2 occupied, 2 dirty/just-paid, 1 reserved, 3 open), 2 open orders, 2 paid, 1 online order, 2 reservations, 1 CRM guest\n";
echo "Done. Demo password for every staff login: {$demoPassword}\n";
