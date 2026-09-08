<?php

/**
 * A single Journal post (/blog/{slug}). The Blog plugin contributes the SEO <head>
 * (canonical, Open Graph article, Twitter, JSON-LD); this template renders the body.
 * The body is a small, safe Markdown subset rendered in-theme (the restaurant image
 * ships no Markdown plugin), escaped-first so post content can never inject HTML.
 *
 * @var array<string,mixed> $entry
 * @var callable            $e
 */
$fields = is_array($entry['fields'] ?? null) ? $entry['fields'] : [];
$body   = (string) ($fields['body'] ?? '');
$date   = (string) ($entry['published_at'] ?? '');

$inline = static function (string $s) use ($e): string {
    $s = $e($s);
    $s = (string) preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" rel="noopener">$1</a>', $s);
    $s = (string) preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s);
    $s = (string) preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $s);
    return (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
};
$render = static function (string $src) use ($inline): string {
    $html = '';
    foreach (preg_split('/\n{2,}/', trim($src)) ?: [] as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        if (preg_match('/^###\s+(.+)/s', $block, $m) === 1) {
            $html .= '<h3>' . $inline(trim($m[1])) . '</h3>';
            continue;
        }
        if (preg_match('/^##\s+(.+)/s', $block, $m) === 1) {
            $html .= '<h2>' . $inline(trim($m[1])) . '</h2>';
            continue;
        }
        $lines = preg_split('/\n/', $block) ?: [];
        $bullets = array_filter($lines, static fn (string $l): bool => preg_match('/^\s*-\s+/', $l) === 1);
        if ($lines !== [] && count($bullets) === count($lines)) {
            $html .= '<ul>';
            foreach ($lines as $l) {
                $html .= '<li>' . $inline(trim((string) preg_replace('/^\s*-\s+/', '', $l))) . '</li>';
            }
            $html .= '</ul>';
            continue;
        }
        $html .= '<p>' . $inline(implode(' ', array_map('trim', $lines))) . '</p>';
    }
    return $html;
};
?>
<article class="page journal-article">
  <div class="wrap measure">
    <p class="eyebrow"><a href="/blog">The Copper Table &middot; Journal</a></p>
    <h1 class="journal-article-title"><?= $e((string) ($entry['title'] ?? '')) ?></h1>
    <?php if ($date !== ''): ?><p class="journal-date"><?= $e(date('F j, Y', (int) strtotime($date))) ?></p><?php endif; ?>
    <div class="prose"><?= $render($body) ?></div>
    <p class="journal-back"><a href="/blog">&larr; All entries</a></p>
  </div>
</article>
