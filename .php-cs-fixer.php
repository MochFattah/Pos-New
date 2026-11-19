<?php

declare(strict_types=1);

// BELUM PERNAH dijalankan (PHP-CS-Fixer tidak terpasang di lingkungan pengembangan).
// Jalankan pertama kali dengan --dry-run --diff dan tinjau hasilnya sebelum menerapkan.
$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/app', __DIR__ . '/bootstrap', __DIR__ . '/config', __DIR__ . '/routes', __DIR__ . '/scripts', __DIR__ . '/tests'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
    ])
    ->setFinder($finder);
