<?php

declare(strict_types=1);

namespace Tests\Feature\Views;

use Tests\Support\TestCase;

/** Menjalankan scripts/test-js.mjs (Playwright + Chromium, offline). Dilewati bila node/chromium tak tersedia. */
final class JsBehaviorTest extends TestCase
{
    public function testJsModulesBehaveInChromium(): void
    {
        $root = dirname(__DIR__, 3);
        $out = [];
        $code = 0;
        exec('cd ' . escapeshellarg($root) . ' && node scripts/test-js.mjs 2>&1', $out, $code);
        $text = implode("\n", $out);
        // Kode 2 = penanda eksplisit dari test-js.mjs bahwa playwright/Chromium tidak tersedia; kegagalan lain tetap gagal.
        if ($code === 2) {
            $this->skip('tes JS dilewati: ' . (preg_match('/DILEWATI[^\n]*/', $text, $m) === 1 ? $m[0] : 'node/playwright tidak tersedia'));
        }
        if ($code === 127) {
            $this->skip('tes JS dilewati: node tidak ditemukan');
        }
        $this->assertSame(0, $code, "scripts/test-js.mjs gagal:\n" . $text);
    }
}
