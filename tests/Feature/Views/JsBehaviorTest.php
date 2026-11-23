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
        exec('cd ' . escapeshellarg($root) . ' && timeout 180 node scripts/test-js.mjs 2>&1', $out, $code);
        $text = implode("\n", $out);
        // Kode 2 = penanda eksplisit dari test-js.mjs bahwa playwright/Chromium tidak tersedia; kegagalan lain tetap gagal.
        $required = getenv('POS_REQUIRE_JS') === '1'; // CI: lewati = gagal
        if ($code === 124) {
            $this->fail("scripts/test-js.mjs melebihi batas 180 detik (Chromium menggantung?):\n" . $text);
        }
        if ($code === 2 && $required) {
            $this->fail('POS_REQUIRE_JS=1 tetapi tes JS dilewati (playwright/Chromium tidak tersedia): ' . $text);
        }
        if ($code === 127 && $required) {
            $this->fail('POS_REQUIRE_JS=1 tetapi node/timeout tidak ditemukan');
        }
        if ($code === 2) {
            $this->skip('tes JS dilewati: ' . (preg_match('/DILEWATI[^\n]*/', $text, $m) === 1 ? $m[0] : 'node/playwright tidak tersedia'));
        }
        if ($code === 127) {
            $this->skip('tes JS dilewati: node tidak ditemukan');
        }
        $this->assertSame(0, $code, "scripts/test-js.mjs gagal:\n" . $text);
    }
}
