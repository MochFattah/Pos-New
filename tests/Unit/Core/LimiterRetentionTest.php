<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Tests\Support\TestCase;

final class LimiterRetentionTest extends TestCase
{
    public function testRetentionCoversEveryConfiguredLimiterWindow(): void
    {
        $root = dirname(__DIR__, 3);
        $cfg = require $root . '/config/security.php';
        $src = (string) file_get_contents($root . '/bootstrap/app.php');
        $this->assertSame(1, preg_match('/\$limiterRetention = static fn \(\): int => max\((.*?)\n\);/s', $src, $m), 'closure retensi ditemukan');
        foreach ($cfg as $name => $section) {
            if (is_array($section) && isset($section['window_seconds'])) {
                $this->assertTrue(str_contains($m[1], "'security.{$name}.window_seconds'"), "retensi memuat jendela {$name}");
            }
        }
    }
}
