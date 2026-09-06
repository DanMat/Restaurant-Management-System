<?php

/**
 * The guest menu (/menu_items) — live menu items grouped by their `category`
 * relation, priced. The relation is expanded by EntryView, so each item's
 * `fields.category` is a list of `{id,slug,title}`.
 *
 * @var array{handle:string,name:string} $collection
 * @var list<array<string,mixed>>        $entries
 * @var callable                         $e
 */
$groups = [];
foreach ($entries as $item) {
    $fields = is_array($item['fields'] ?? null) ? $item['fields'] : [];
    $rel    = $fields['category'] ?? null;
    $cat    = is_array($rel) && isset($rel[0]['title']) && (string) $rel[0]['title'] !== ''
        ? (string) $rel[0]['title']
        : 'More';
    $groups[$cat][] = $item;
}
?>
<section class="menu-page">
  <div class="wrap">
    <header class="menu-head">
      <p class="eyebrow">RAS · Menu</p>
      <h1>What&rsquo;s on</h1>
      <p class="lede">Fresh every service. Prices include tax.</p>
    </header>

    <?php if ($entries === []): ?>
      <p class="empty">The menu is being set. Please check back shortly.</p>
    <?php endif; ?>

    <?php foreach ($groups as $cat => $items): ?>
      <section class="menu-group">
        <h2><?= $e($cat) ?></h2>
        <ul class="menu-list">
          <?php foreach ($items as $item): ?>
            <?php
              $fields = is_array($item['fields'] ?? null) ? $item['fields'] : [];
              $price  = $fields['price'] ?? null;
              $desc   = trim((string) ($fields['body'] ?? ''));
            ?>
            <li class="menu-item">
              <div class="menu-row">
                <span class="menu-name"><?= $e((string) $item['title']) ?></span>
                <span class="menu-leader" aria-hidden="true"></span>
                <span class="menu-price"><?= $price !== null && $price !== '' ? '&pound;' . $e(number_format((float) $price, 2)) : '' ?></span>
              </div>
              <?php if ($desc !== ''): ?><p class="menu-desc"><?= $e($desc) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </div>
</section>
