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
            $this->skip('node tidak tersedia, kesegaran CSS tidak diperiksa');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'css');
        exec(escapeshellarg($node) . ' ' . escapeshellarg($this->root . '/scripts/build-css.mjs') . ' --out=' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        $this->assertSame(0, $code, 'build ke berkas sementara gagal: ' . implode("\n", array_slice($out, -5)));
        $same = file_get_contents($tmp) === file_get_contents($this->root . '/public/assets/css/app.css');
        @unlink($tmp);
        $this->assertTrue($same, 'public/assets/css/app.css usang; jalankan node scripts/build-css.mjs dan komit hasilnya');
    }

    public function testContrastCheckPasses(): void
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

    public function testNoKnownContrastFailuresRemain(): void
    {
        $node = $this->node();
        $this->assertTrue($node !== '', 'node diperlukan untuk skrip kontras');
        exec(escapeshellarg($node) . ' ' . escapeshellarg($this->root . '/scripts/check-contrast.mjs') . ' --json 2>&1', $out, $code);
        $data = json_decode(implode("\n", $out), true);
        $this->assertSame(0, $code);
        $this->assertSame([], array_values(array_filter($data['rows'], static fn (array $r): bool => $r['status'] !== 'PASS')), 'semua pasangan kontras harus lulus');
        $known = json_decode((string) file_get_contents($this->root . '/scripts/contrast-known-failures.json'), true)['failures'];
        $this->assertSame([], $known, 'allowlist kegagalan kontras harus kosong');
        $ids = array_map(static fn (array $r): string => $r['theme'] . ' ' . $r['id'], $data['rows']);
        foreach (['light', 'dark'] as $theme) {
            foreach (['background', 'surface', 'surface-muted'] as $bg) {
                $this->assertContains($theme . ' border-strong on ' . $bg, $ids);
            }
        }
    }

    public function testControlBordersUseBorderStrong(): void
    {
        $form = (string) file_get_contents($this->root . '/resources/css/components/form.css');
        $button = (string) file_get_contents($this->root . '/resources/css/components/button.css');
        $this->assertSame(1, preg_match('/\.field\s*\{[^}]*border:\s*1px solid var\(--color-border-strong\)/', $form));
        $this->assertSame(1, preg_match('/\.btn-secondary\s*\{[^}]*border-color:\s*var\(--color-border-strong\)/', $button));
        $this->assertTrue(str_contains($this->css(), '--color-border-strong:'));
    }

    public function testContrastCheckFailsWhenKnownFailureWorsens(): void
    {
        $node = $this->node();
        $this->assertTrue($node !== '', 'node diperlukan untuk skrip kontras');
        $known = json_decode((string) file_get_contents($this->root . '/scripts/contrast-known-failures.json'), true);
        // Salinan terisolasi dengan text-muted lama (4.11:1) dan entri allowlist yang mencatat 4.5.
        $this->assertSame([], $known['failures']);
        $tokens = (string) file_get_contents($this->root . '/resources/css/tokens.css');
        $broken = preg_replace('/--color-text-muted:\s*#[0-9A-Fa-f]{6};/', '--color-text-muted: #68778A;', $tokens, 1);
        $dir = sys_get_temp_dir() . '/contrast' . bin2hex(random_bytes(4));
        mkdir($dir . '/resources/css', 0777, true);
        mkdir($dir . '/scripts', 0777, true);
        file_put_contents($dir . '/resources/css/tokens.css', $broken);
        copy($this->root . '/scripts/check-contrast.mjs', $dir . '/scripts/check-contrast.mjs');
        $known['failures'][] = ['theme' => 'light', 'id' => 'text-muted on surface-muted', 'reason' => 'x', 'ratioAtListing' => 4.5];
        $tmp = tempnam(sys_get_temp_dir(), 'known');
        file_put_contents($tmp, json_encode($known));
        exec(escapeshellarg($node) . ' ' . escapeshellarg($dir . '/scripts/check-contrast.mjs') . ' --known=' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        @unlink($tmp);
        exec('rm -rf ' . escapeshellarg($dir));
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
