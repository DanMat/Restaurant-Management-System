<?php

declare(strict_types=1);

/**
 * Matches the NimbusCMS core config: conservative, PSR-12-oriented, no
 * house-style rewrites. Kept identical so the plugin formats like the official
 * packages it sits beside.
 */
$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12'                       => true,
        'array_syntax'                 => ['syntax' => 'short'],
        'no_unused_imports'            => true,
        'ordered_imports'              => ['sort_algorithm' => 'alpha'],
        'single_quote'                 => true,
        'trailing_comma_in_multiline'  => ['elements' => ['arrays', 'arguments', 'parameters']],
        'no_trailing_whitespace'       => true,
        'no_whitespace_in_blank_line'  => true,
        'blank_line_after_opening_tag' => true,
    ])
    ->setFinder($finder);
