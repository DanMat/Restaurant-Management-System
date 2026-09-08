<?php

/**
 * The HTML shell for the public RAS site.
 *
 * @var string   $appName   the site name
 * @var string   $__content the rendered page
 * @var string   $title     the page title (optional)
 * @var callable $partial   include another theme template
 * @var callable $e         escape a value for output
 * @var array<string,mixed> $meta
 * @var string   $head      extra <head> HTML contributed by plugins (already-rendered, trusted)
 */
// Append the site name unless the page title already is it (the homepage entry is
// titled after the restaurant, so this avoids "Name · Name").
$pageTitle = isset($title) && $title !== '' && $title !== $appName ? $title . ' · ' . $appName : $appName;
$meta      = $meta ?? [];
$cssVer    = substr((string) @hash_file('crc32b', __DIR__ . '/../assets/app.css'), 0, 8);
// On blog/tag pages the Blog plugin contributes the full, correct <head> (canonical,
// Open Graph article, Twitter, JSON-LD), so the shell defers its own canonical/OG
// there to avoid emitting them twice.
$path           = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
$pluginOwnsHead = str_starts_with($path, '/blog') || str_starts_with($path, '/tag');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($pageTitle) ?></title>
    <?php if (!$pluginOwnsHead): ?>
        <?php if (!empty($meta['description'])): ?>
            <meta name="description" content="<?= $e($meta['description']) ?>">
        <?php endif; ?>
        <?php if (!empty($meta['canonical'])): ?>
            <link rel="canonical" href="<?= $e($meta['canonical']) ?>">
        <?php endif; ?>
        <meta property="og:site_name" content="<?= $e($appName) ?>">
        <meta property="og:title" content="<?= $e($pageTitle) ?>">
        <meta property="og:type" content="<?= $e($meta['og_type'] ?? 'website') ?>">
    <?php endif; ?>
    <?= $head ?? '' ?>
    <link rel="stylesheet" href="/theme/assets/app.css?v=<?= $e($cssVer) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<?= $partial('header') ?>
<main id="main">
    <?= $__content ?>
</main>
<?= $partial('footer') ?>
</body>
</html>
