<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Security\ArraySessionStorage;
use App\Core\Security\Csrf;
use App\Core\Security\Session;
use Tests\Support\TestCase;

final class SessionTest extends TestCase
{
    private int $now = 1000;

    private function session(?ArraySessionStorage $st = null): Session
    {
        $s = new Session($st ?? new ArraySessionStorage(), fn (): int => $this->now);
        $s->start();
        return $s;
    }

    public function testGetSetForgetAndFlash(): void
    {
        $s = $this->session();
        $s->set('a', 1);
        $this->assertSame(1, $s->get('a'));
        $this->assertSame('x', $s->get('zz', 'x'));
        $s->forget('a');
        $this->assertSame(null, $s->get('a'));
        $s->flash('ok', 'Tersimpan');
        $this->assertSame('Tersimpan', $s->pullFlash('ok'));
        $this->assertSame(null, $s->pullFlash('ok'));
    }

    public function testRegenerateChangesIdAndKeepsData(): void
    {
        $st = new ArraySessionStorage();
        $s = $this->session($st);
        $s->set('uid', 7);
        $before = $st->id();
        $s->regenerate();
        $this->assertTrue($st->id() !== $before);
        $this->assertSame(7, $s->get('uid'));
    }

    public function testDestroyClearsData(): void
    {
        $s = $this->session();
        $s->set('uid', 7);
        $s->destroy();
        $s->start();
        $this->assertSame(null, $s->get('uid'));
    }

    public function testIdleExpiry(): void
    {
        $s = $this->session();
        $this->assertFalse($s->isIdleExpired(28800));
        $s->set('_last_activity', $this->now);
        $this->now += 28800;
        $this->assertFalse($s->isIdleExpired(28800));
        $this->now += 1;
        $this->assertTrue($s->isIdleExpired(28800));
    }

    public function testCsrfRejectsMissingAndWrongToken(): void
    {
        $s = $this->session();
        $token = Csrf::token($s);
        $this->assertSame($token, Csrf::token($s));
        $this->assertTrue(Csrf::isValid($s, $token));
        $this->assertFalse(Csrf::isValid($s, null));
        $this->assertFalse(Csrf::isValid($s, ''));
        $this->assertFalse(Csrf::isValid($s, 'salah'));
        $this->assertFalse(Csrf::isValid($this->session(), $token));
    }

    public function testRegenerateRotatesCsrfToken(): void
    {
        $s = $this->session();
        $before = Csrf::token($s);
        $s->regenerate();
        $after = Csrf::token($s);
        $this->assertTrue($before !== $after);
        $this->assertFalse(Csrf::isValid($s, $before));
    }
}
