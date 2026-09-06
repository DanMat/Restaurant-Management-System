<?php

/**
 * The public homepage — the `home` single-collection entry, rendered as a real
 * restaurant front page in the RAS identity. Every field is optional; each section
 * is omitted when its content is blank, so the page degrades gracefully.
 *
 * Featured dishes come LIVE from the menu via the restaurant plugin's view-data
 * contributor (ADR 0027), namespaced under `danmat.restaurant`. It is DATA, not
 * HTML — escape every value here.
 *
 * @var array<string,mixed>              $entry   the home entry view-model
 * @var array<string,array<string,mixed>> $contrib namespaced plugin view-data
 * @var string                           $appName the site name
 * @var callable                         $e       escape a value for output
 */
$fields = is_array($entry['fields'] ?? null) ? $entry['fields'] : [];
$f      = static fn (string $k): string => trim((string) ($fields[$k] ?? ''));

$heroKicker  = $f('hero_kicker');
$heroTitle   = $f('hero_title') !== '' ? $f('hero_title') : $appName;
$heroTagline = $f('hero_tagline');
$aboutTitle  = $f('about_title');
$aboutBody   = $f('about_body');
$hours       = $f('hours');
$address     = $f('address');
$phone       = $f('phone');

$featured = $contrib['danmat.restaurant']['featured'] ?? [];
$featured = is_array($featured) ? $featured : [];

// tel: href — digits and a leading + only, so an admin-entered phone is a safe URL.
$telHref = $phone !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $phone) : '';
?>
<section class="hero">
  <div class="wrap hero-inner">
    <?php if ($heroKicker !== ''): ?><p class="eyebrow"><?= $e($heroKicker) ?></p><?php endif; ?>
    <h1 class="hero-title"><?= $e($heroTitle) ?></h1>
    <?php if ($heroTagline !== ''): ?><p class="hero-tagline"><?= $e($heroTagline) ?></p><?php endif; ?>
    <p class="hero-actions">
      <a class="btn btn-gold" href="/menu_items">View the menu</a>
      <?php if ($telHref !== ''): ?><a class="btn btn-ghost" href="<?= $e($telHref) ?>">Call to reserve</a><?php endif; ?>
    </p>
  </div>
</section>

<?php if ($aboutBody !== '' || $aboutTitle !== ''): ?>
<section class="home-section about">
  <div class="wrap measure">
    <?php if ($aboutTitle !== ''): ?><h2 class="section-title"><?= $e($aboutTitle) ?></h2><?php endif; ?>
    <?php if ($aboutBody !== ''): ?><p class="about-body"><?= nl2br($e($aboutBody)) ?></p><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($featured !== []): ?>
<section class="home-section featured">
  <div class="wrap">
    <header class="section-head">
      <p class="eyebrow">From the kitchen</p>
      <h2 class="section-title">A few of tonight&rsquo;s plates</h2>
    </header>
    <ul class="dish-grid">
      <?php foreach ($featured as $dish): ?>
        <?php
          $name  = (string) ($dish['name'] ?? '');
          $price = (string) ($dish['price'] ?? '');
          $cat   = trim((string) ($dish['category'] ?? ''));
          $blurb = trim((string) ($dish['blurb'] ?? ''));
        ?>
        <li class="dish">
          <?php if ($cat !== ''): ?><p class="dish-cat"><?= $e($cat) ?></p><?php endif; ?>
          <div class="dish-row">
            <span class="dish-name"><?= $e($name) ?></span>
            <span class="dish-price"><?= $price !== '' ? '&pound;' . $e(number_format((float) $price, 2)) : '' ?></span>
          </div>
          <?php if ($blurb !== ''): ?><p class="dish-blurb"><?= $e($blurb) ?></p><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="section-more"><a href="/menu_items">See the full menu &rarr;</a></p>
  </div>
</section>
<?php endif; ?>

<?php if ($hours !== '' || $address !== '' || $phone !== ''): ?>
<section class="home-section visit">
  <div class="wrap visit-grid">
    <?php if ($hours !== ''): ?>
      <div class="visit-col">
        <h2 class="section-title">Hours</h2>
        <p class="visit-body"><?= nl2br($e($hours)) ?></p>
      </div>
    <?php endif; ?>
    <?php if ($address !== '' || $phone !== ''): ?>
      <div class="visit-col">
        <h2 class="section-title">Find us</h2>
        <?php if ($address !== ''): ?><p class="visit-body"><?= nl2br($e($address)) ?></p><?php endif; ?>
        <?php if ($phone !== ''): ?><p class="visit-body"><a href="<?= $e($telHref) ?>"><?= $e($phone) ?></a></p><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="home-section reserve">
  <div class="wrap reserve-inner">
    <h2 class="section-title">Join us</h2>
    <p class="reserve-lede">Walk-ins welcome. For a table<?= $telHref !== '' ? ', give us a call' : '' ?>.</p>
    <p class="hero-actions">
      <?php if ($telHref !== ''): ?><a class="btn btn-gold" href="<?= $e($telHref) ?>">Call to reserve</a><?php endif; ?>
      <a class="btn btn-ghost" href="/menu_items">Browse the menu</a>
    </p>
  </div>
</section>
