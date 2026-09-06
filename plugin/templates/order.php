<?php

/**
 * Public online-order page (ADR 0023 page section), rendered inside the active
 * theme. Server-rendered, NO JavaScript required: a form of quantity inputs plus
 * name + phone, posting to the /ext action route. The checkout is a labelled DEMO
 * — there is no real payment and no card field.
 *
 * @var list<array{id:int,name:string,price:string,category:?string}> $items
 * @var string   $error   an error code from a rejected submission ('' if none)
 * @var callable $e       escape a value for output
 */
$groups = [];
foreach ($items as $it) {
    $cat            = ($it['category'] ?? null) !== null && $it['category'] !== '' ? (string) $it['category'] : 'More';
    $groups[$cat][] = $it;
}
$messages = [
    'empty'     => 'Your order was empty — add a dish or two, then place it again.',
    'invalid'   => 'Something wasn’t right with that order — please try again.',
    'slow_down' => 'That’s a lot of orders very quickly — give it a moment and try again.',
    'try_again' => 'Please try that again.',
];
$errorMsg = $error !== '' ? ($messages[$error] ?? $messages['try_again']) : '';
?>
<section class="order-page">
  <div class="wrap">
    <header class="menu-head">
      <p class="eyebrow">RAS · Order online</p>
      <h1>Order for pickup</h1>
      <p class="lede">Choose your dishes, leave your details, and we’ll start cooking.</p>
      <p class="demo-note">Demo checkout — <strong>no real payment is taken</strong> and the order clears at the top of the hour.</p>
    </header>

    <?php if ($errorMsg !== ''): ?>
      <p class="order-error" role="alert"><?= $e($errorMsg) ?></p>
    <?php endif; ?>

    <form method="post" action="/ext/restaurant/order" class="order-form">
      <?php /* Honeypot: hidden from people, tempting to bots. */ ?>
      <div class="hp" aria-hidden="true">
        <label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <?php foreach ($groups as $cat => $rows): ?>
        <section class="order-group">
          <h2><?= $e((string) $cat) ?></h2>
          <ul class="order-list">
            <?php foreach ($rows as $it): ?>
              <li class="order-item">
                <div class="order-item-main">
                  <span class="order-name"><?= $e((string) $it['name']) ?></span>
                  <span class="order-price">&pound;<?= $e(number_format((float) $it['price'], 2)) ?></span>
                </div>
                <label class="order-qty">
                  <span class="order-qty-label">Qty</span>
                  <input type="number" name="qty[<?= (int) $it['id'] ?>]" value="0" min="0" max="99" inputmode="numeric">
                </label>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>

      <section class="order-details">
        <h2>Your details</h2>
        <div class="order-field">
          <label for="name">Name</label>
          <input id="name" type="text" name="name" maxlength="120" autocomplete="name" required>
        </div>
        <div class="order-field">
          <label for="phone">Phone</label>
          <input id="phone" type="tel" name="phone" maxlength="40" autocomplete="tel" required>
        </div>
      </section>

      <button type="submit" class="btn btn-gold order-submit">Place order · Pay (demo)</button>
    </form>
  </div>
</section>
