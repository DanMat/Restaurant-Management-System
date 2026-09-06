<?php

/**
 * A single info page (an `entry`), rendered simply inside the RAS shell.
 *
 * @var array<string,mixed> $entry
 * @var callable            $e
 */
$fields = is_array($entry['fields'] ?? null) ? $entry['fields'] : [];
$body   = trim((string) ($fields['body'] ?? ''));
?>
<article class="page">
  <div class="wrap measure">
    <h1><?= $e((string) ($entry['title'] ?? '')) ?></h1>
    <?php if ($body !== ''): ?><div class="prose"><?= nl2br($e($body)) ?></div><?php endif; ?>
  </div>
</article>
