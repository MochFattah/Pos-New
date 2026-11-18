<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use Throwable;

abstract class TestCase
{
    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    public function runSetUp(): void
    {
        $this->setUp();
    }

    public function runTearDown(): void
    {
        $this->tearDown();
    }

    /** Melewati tes (dihitung lulus, dicetak SKIP beserta alasannya). */
    protected function skip(string $reason): never
    {
        throw new SkippedException($reason);
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $this->fail($message !== '' ? $message : sprintf(
                'Diharapkan %s, didapat %s',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    protected function assertTrue(mixed $actual, string $message = ''): void
    {
        $this->assertSame(true, $actual, $message);
    }

    protected function assertFalse(mixed $actual, string $message = ''): void
    {
        $this->assertSame(false, $actual, $message);
    }

    /**
     * @param class-string<Throwable> $class
     */
    protected function assertThrows(string $class, callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            if ($e instanceof $class) {
                return;
            }
            $this->fail(sprintf('Diharapkan %s, dilempar %s', $class, $e::class));
        }
        $this->fail(sprintf('Diharapkan %s, tidak ada exception dilempar', $class));
    }

    protected function assertContains(mixed $needle, array|string $haystack, string $message = ''): void
    {
        $found = is_string($haystack)
            ? str_contains($haystack, (string) $needle)
            : in_array($needle, $haystack, true);
        if (!$found) {
            $this->fail($message !== '' ? $message : sprintf('%s tidak ditemukan', var_export($needle, true)));
        }
    }

    protected function fail(string $message): never
    {
        throw new RuntimeException($message);
    }
}
