<?php

/**
 * The public footer.
 *
 * @var string   $appName the site name
 * @var callable $e       escape a value for output
 */
$year = date('Y');
?>
<footer class="site-footer">
  <div class="wrap footer-inner">
    <p class="footer-name"><?= $e($appName) ?></p>
    <p class="footer-meta">Open daily · Lunch &amp; dinner</p>
    <p class="footer-fine">Powered by the Restaurant Automation System on NimbusCMS · <?= $e((string) $year) ?></p>
  </div>
</footer>
