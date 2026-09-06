<?php

/**
 * The public header — the RAS wordmark and the menu link.
 *
 * @var string   $appName the site name
 * @var callable $e       escape a value for output
 */
?>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="/">
      <span class="brand-mark">RAS</span>
      <span class="brand-name"><?= $e($appName) ?></span>
    </a>
    <nav class="site-nav" aria-label="Primary">
      <a href="/menu_items">Menu</a>
    </nav>
  </div>
</header>
