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

    private function node(): string
    {
        return trim((string) shell_exec('command -v node 2>/dev/null'));
    }

    public function testBuiltCssIsFresh(): void
    {
        $node = $this->node();
        if ($node === '') {
            echo "  (dilewati: node tidak tersedia, kesegaran CSS tidak diperiksa)\n";
            $this->assertTrue(true);
            return;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'css');
        exec(escapeshellarg($node) . ' ' . escapeshellarg($this->root . '/scripts/build-css.mjs') . ' --out=' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        $this->assertSame(0, $code, 'build ke berkas sementara gagal: ' . implode("\n", array_slice($out, -5)));
        $same = file_get_contents($tmp) === file_get_contents($this->root . '/public/assets/css/app.css');
        @unlink($tmp);
        $this->assertTrue($same, 'public/assets/css/app.css usang; jalankan node scripts/build-css.mjs dan komit hasilnya');
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
        $known = json_decode((string) file_get_contents($this->root . '/scripts/contrast-known-failures.json'), true)['failures'];
        foreach ($known as $k) {
            $this->assertTrue(isset($k['ratioAtListing']) && is_numeric($k['ratioAtListing']), 'entri allowlist wajib punya ratioAtListing: ' . $k['id']);
        }
    }

    public function testContrastCheckFailsWhenKnownFailureWorsens(): void
    {
        $node = $this->node();
        $this->assertTrue($node !== '', 'node diperlukan untuk skrip kontras');
        $known = json_decode((string) file_get_contents($this->root . '/scripts/contrast-known-failures.json'), true);
        $known['failures'][0]['ratioAtListing'] += 0.5; // rasio sekarang lebih rendah dari yang tercatat
        $tmp = tempnam(sys_get_temp_dir(), 'known');
        file_put_contents($tmp, json_encode($known));
        exec(escapeshellarg($node) . ' ' . escapeshellarg($this->root . '/scripts/check-contrast.mjs') . ' --known=' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        @unlink($tmp);
        $this->assertSame(1, $code, 'kegagalan yang memburuk harus exit 1');
        $this->assertTrue(str_contains(implode("\n", $out), 'MEMBURUK'));
    }

    public function testContrastCheckWarnsOnStaleEntry(): void
    {
        $node = $this->node();
        $this->assertTrue($node !== '', 'node diperlukan untuk skrip kontras');
        $known = json_decode((string) file_get_contents($this->root . '/scripts/contrast-known-failures.json'), true);
        $known['failures'][] = ['theme' => 'light', 'id' => 'text-primary on surface', 'reason' => 'x', 'ratioAtListing' => 1.0];
        $tmp = tempnam(sys_get_temp_dir(), 'known');
        file_put_contents($tmp, json_encode($known));
        exec(escapeshellarg($node) . ' ' . escapeshellarg($this->root . '/scripts/check-contrast.mjs') . ' --known=' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        @unlink($tmp);
        $this->assertTrue(str_contains(implode("\n", $out), 'PERINGATAN: entri allowlist USANG'));
    }

    public function testFontsReadmeExists(): void
    {
        $this->assertTrue(is_file($this->root . '/public/assets/fonts/README.md'));
    }
}
