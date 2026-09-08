<?php

/**
 * The Journal (/blog) — the restaurant's blog, served by the official Blog plugin
 * over a plain `blog` collection. Published posts, newest first.
 *
 * @var array{handle:string,name:string} $collection
 * @var list<array<string,mixed>>        $entries
 * @var callable                         $e
 */
?>
<section class="menu-page">
  <div class="wrap">
    <header class="menu-head">
      <p class="eyebrow">The Copper Table &middot; Journal</p>
      <h1>From the pass</h1>
      <p class="lede">Notes from the kitchen &mdash; what&rsquo;s on, where it comes from, and what&rsquo;s new.</p>
    </header>

    <?php if ($entries === []): ?>
      <p class="empty">No entries yet. Check back soon.</p>
    <?php endif; ?>

    <ul class="journal-list">
      <?php foreach ($entries as $post): ?>
        <?php
          $fields  = is_array($post['fields'] ?? null) ? $post['fields'] : [];
          $slug    = (string) ($post['slug'] ?? '');
          $summary = trim((string) ($fields['summary'] ?? ''));
          $date    = (string) ($post['published_at'] ?? '');
        ?>
        <li class="journal-item">
          <a class="journal-link" href="/blog/<?= $e($slug) ?>">
            <h2 class="journal-title"><?= $e((string) ($post['title'] ?? '')) ?></h2>
          </a>
          <?php if ($date !== ''): ?><p class="journal-date"><?= $e(date('F j, Y', (int) strtotime($date))) ?></p><?php endif; ?>
          <?php if ($summary !== ''): ?><p class="journal-summary"><?= $e($summary) ?></p><?php endif; ?>
          <a class="journal-more" href="/blog/<?= $e($slug) ?>">Read the entry &rarr;</a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
