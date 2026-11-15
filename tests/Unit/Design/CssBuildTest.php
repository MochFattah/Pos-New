<?php

declare(strict_types=1);

namespace Tests\Unit\Design;

use Tests\Support\TestCase;

final class CssBuildTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    private function css(): string
    {
        $path = $this->root . '/public/assets/css/app.css';
        $this->assertTrue(is_file($path), 'public/assets/css/app.css harus ada (jalankan node scripts/build-css.mjs)');
        return (string) file_get_contents($path);
    }

    public function testBuiltCssContainsComponentsAndTokens(): void
    {
        $css = $this->css();
        foreach (['.btn-primary', '.btn-secondary', '.btn-tertiary', '.btn-danger', '.field-error', '.badge-success',
                  '.table-data', '.dialog-panel', '.toast', '.skeleton', '.empty-state', '.nav-item', '.skip-link',
                  '.app-shell', '.sidebar', '.topbar', '.card', '.page-header', '.stack',
                  '--color-primary:', '--color-surface:', '--color-text-primary:', '[data-theme="dark"]', ':root:not([data-theme="light"])', 'prefers-color-scheme: dark',
                  'prefers-reduced-motion'] as $needle) {
            $this->assertTrue(str_contains($css, $needle), 'CSS hasil build tidak memuat ' . $needle);
        }
    }

    public function testBuiltCssHasNoExternalUrls(): void
    {
        $this->assertSame(0, preg_match('~https?://~i', $this->css()), 'CSS tidak boleh memuat URL eksternal');
    }

    public function testBuiltCssIsFresh(): void
    {
        $built = filemtime($this->root . '/public/assets/css/app.css');
        $sources = array_merge([$this->root . '/resources/css/app.css', $this->root . '/resources/css/tokens.css'],
            glob($this->root . '/resources/css/components/*.css') ?: []);
        foreach ($sources as $src) {
            // Toleransi 2 detik agar checkout git (mtime seragam) tidak gagal.
            $this->assertTrue(filemtime($src) <= $built + 2, basename($src) . ' lebih baru dari CSS hasil build; jalankan node scripts/build-css.mjs');
        }
    }

    public function testContrastCheckPassesWithAllowlist(): void
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        $this->assertTrue($node !== '', 'node diperlukan untuk skrip kontras');
        exec(escapeshellarg($node) . ' ' . escapeshellarg($this->root . '/scripts/check-contrast.mjs') . ' --json 2>&1', $out, $code);
        $this->assertSame(0, $code, 'check-contrast gagal: ' . implode("\n", array_slice($out, -5)));
        $data = json_decode(implode("\n", $out), true);
        $this->assertTrue(is_array($data) && isset($data['rows']), 'keluaran --json tidak valid');
        $this->assertSame([], $data['stale'], 'ada entri allowlist usang');
    }

    public function testFontsReadmeExists(): void
    {
        $this->assertTrue(is_file($this->root . '/public/assets/fonts/README.md'));
    }
}
