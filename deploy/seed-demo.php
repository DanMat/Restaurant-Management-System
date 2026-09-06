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

use DanMat\Restaurant\Orders;
use DanMat\Restaurant\Reservations;
use DanMat\Restaurant\Tables;
use Nimbus\Auth\Password;
use Nimbus\Auth\RoleRepository;
use Nimbus\Content\CollectionRepository;
use Nimbus\Content\CollectionService;
use Nimbus\Content\EntryInput;
use Nimbus\Content\EntryRepository;
use Nimbus\Content\EntryService;
use Nimbus\Content\FieldTypeRegistry;
use Nimbus\Content\RelationRepository;
use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
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

// --- 4) Live floor / orders / reservations ---------------------------------
$storage      = static fn (): PluginStorage => new PluginStorage($db);
$tables       = new Tables($storage);
$reservations = new Reservations($storage, $tables);
$orders       = new Orders($storage, $tables, static fn (int $id): ?array => null);

$t = [];
foreach ([['1', 2], ['2', 4], ['3', 4], ['4', 2], ['5', 6], ['6', 2], ['Patio 1', 4], ['Patio 2', 4]] as [$label, $seats]) {
    $t[$label] = $tables->save(null, ['label' => $label, 'seats' => (string) $seats], $now);
}
$tables->setStatus($t['4'], 'dirty', $now);
$tables->setStatus($t['5'], 'reserved', $now);

// An open order on table 2, mid-service and sent to the kitchen.
$o1 = $orders->open($t['2'], $now);
$orders->addItem($o1, null, 'Grilled Salmon', '9.99', 2, $now);
$orders->addItem($o1, null, 'Guacamole', '5.50', 1, $now);
$orders->setStatus($o1, 'sent', $now);
// A second order on table 3, ready for the pass.
$o2 = $orders->open($t['3'], $now);
$orders->addItem($o2, null, 'Chicken Marsala', '8.21', 1, $now);
$orders->setStatus($o2, 'ready', $now);

// A couple of settled orders today, so Reports has revenue.
foreach ([['Fudge', '4.99', 2], ['Miso Soup', '3.50', 3]] as $i => [$name, $price, $qty]) {
    $tid = $tables->save(null, ['label' => 'H' . $i, 'seats' => '2'], $now);
    $paid = $orders->open($tid, $now);
    $orders->addItem($paid, null, $name, $price, $qty, $now);
    $orders->pay($paid, 'card', $now);
}

// A guest in the CRM, and a reservation linked to them — floor staff see the
// booking; only the manager (crm:read) can open the guest record.
$crm       = new Contacts($storage);
$contactId = $crm->save(null, ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test', 'phone' => '555-0101'], $now);
$reservations->save(null, ['party_name' => 'Lovelace', 'party_size' => '4', 'reserved_at' => date('Y-m-d 19:30:00'), 'table_id' => (string) $t['5'], 'contact_id' => (string) $contactId, 'notes' => 'Window seat.'], $now);
$reservations->save(null, ['party_name' => 'Turing', 'party_size' => '2', 'reserved_at' => date('Y-m-d 20:00:00')], $now);

echo "  floor: 8 tables, 2 open orders, 2 paid, 2 reservations, 1 CRM guest\n";
echo "Done. Demo password for every staff login: {$demoPassword}\n";
