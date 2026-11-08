<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Security\NativeSessionStorage;
use RuntimeException;
use Tests\Support\TestCase;

final class FakeNativeStorage extends NativeSessionStorage
{
    public bool $regenResult = true;
    public int $expired = 0;

    protected function nativeRegenerate(): bool
    {
        return $this->regenResult;
    }

    protected function expireCookie(): void
    {
        $this->expired++;
    }
}

final class NativeSessionStorageTest extends TestCase
{
    public function testOptionsSetGcMaxLifetimeAtLeastIdleSeconds(): void
    {
        $s = new NativeSessionStorage('/tmp/x', false, 28800, false);
        $o = $s->options();
        $this->assertTrue($o['gc_maxlifetime'] >= 28800);
        $this->assertSame(1, $o['use_strict_mode']);
        $this->assertSame('Lax', $o['cookie_samesite']);
        $this->assertSame(1, $o['cookie_httponly']);
    }

    public function testSecureFlagTrueForcesSecureOnPlainHttp(): void
    {
        $o = (new NativeSessionStorage('/tmp/x', true, 100, false))->options();
        $this->assertSame(1, $o['cookie_secure']);
    }

    public function testSecureAutoFollowsRequestHttps(): void
    {
        $this->assertSame(0, (new NativeSessionStorage('/tmp/x', false, 100, false))->options()['cookie_secure']);
        $this->assertSame(1, (new NativeSessionStorage('/tmp/x', false, 100, true))->options()['cookie_secure']);
    }

    public function testRegenerateThrowsWhenNativeFails(): void
    {
        $s = new FakeNativeStorage('/tmp/x', false, 100, false);
        $s->regenResult = false;
        $this->assertThrows(RuntimeException::class, fn () => $s->regenerateId());
        $s->regenResult = true;
        $s->regenerateId();
    }

    public function testDestroyExpiresCookie(): void
    {
        $s = new FakeNativeStorage('/tmp/x', false, 100, false);
        $s->destroy();
        $this->assertSame(1, $s->expired);
    }
}
