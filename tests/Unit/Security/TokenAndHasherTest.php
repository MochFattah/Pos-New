<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Security\PasswordHasher;
use App\Core\Security\TokenService;
use Tests\Support\TestCase;

final class TokenAndHasherTest extends TestCase
{
    public function testTokenHashDiffersFromPlainAndIsDeterministic(): void
    {
        $t = (new TokenService())->generate();
        $this->assertSame(1, preg_match('/^[0-9a-f]{64}$/', $t['plain']));
        $this->assertTrue($t['hash'] !== $t['plain']);
        $this->assertSame(hash('sha256', $t['plain']), $t['hash']);
        $this->assertSame($t['hash'], (new TokenService())->hash($t['plain']));
        $this->assertTrue((new TokenService())->generate()['plain'] !== $t['plain']);
    }

    public function testPasswordVerifyRejectsWrongPassword(): void
    {
        $h = new PasswordHasher();
        $hash = $h->hash('rahasia-123');
        $this->assertTrue($hash !== 'rahasia-123');
        $this->assertTrue($h->verify('rahasia-123', $hash));
        $this->assertFalse($h->verify('salah', $hash));
        $this->assertFalse($h->needsRehash($hash));
    }
}
