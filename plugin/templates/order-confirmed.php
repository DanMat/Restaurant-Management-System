<?php

/**
 * Online-order confirmation (ADR 0023 page section), rendered inside the theme.
 * Reached only with a valid confirmation token, so it shows the guest their own
 * order and no other. The "payment" was a demo — say so.
 *
 * @var array{id:int,customer_name:?string,status:string,items:list<array{name:string,unit_price:string,qty:int,line_total:string}>,total:string} $order
 * @var callable $e escape a value for output
 */
$name = trim((string) ($order['customer_name'] ?? ''));
?>
<section class="order-page">
  <div class="wrap measure">
    <header class="menu-head">
      <p class="eyebrow">RAS · Order confirmed</p>
      <h1>Thanks<?= $name !== '' ? ', ' . $e($name) : '' ?>!</h1>
      <p class="lede">Order <strong>#<?= (int) $order['id'] ?></strong> is in — the kitchen is on it.</p>
      <p class="demo-note">This was a <strong>demo checkout</strong>: no payment was taken, and the order clears at the top of the hour.</p>
    </header>

    <ul class="order-receipt">
      <?php foreach ($order['items'] as $line): ?>
        <li class="order-receipt-row">
          <span class="order-receipt-qty"><?= (int) $line['qty'] ?>&times;</span>
          <span class="order-receipt-name"><?= $e((string) $line['name']) ?></span>
          <span class="order-receipt-price">&pound;<?= $e(number_format((float) $line['line_total'], 2)) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="order-receipt-total"><span>Total (paid — demo)</span><span>&pound;<?= $e(number_format((float) $order['total'], 2)) ?></span></p>

    <p class="section-more"><a href="/menu_items">Back to the menu</a> · <a href="/order">Place another order</a></p>
  </div>
</section>
