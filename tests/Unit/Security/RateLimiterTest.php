<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Security\RateLimiter;
use Tests\Support\Db;
use Tests\Support\TestCase;

final class RateLimiterTest extends TestCase
{
    private int $now = 1_800_000_000;

    private function limiter(\App\Core\Database\Connection $db): RateLimiter
    {
        return new RateLimiter($db, 5, 900, fn (): int => $this->now);
    }

    public function testRateLimiterBlocksAfterFiveFailuresWithinWindow(): void
    {
        $db = Db::fresh();
        $rl = $this->limiter($db);
        for ($i = 0; $i < 4; $i++) {
            $rl->record('A@Toko.id', '1.1.1.1', false);
        }
        $this->assertFalse($rl->tooManyAttempts('a@toko.id', '1.1.1.1'));
        $rl->record(' a@toko.id ', '1.1.1.1', false);
        $this->assertTrue($rl->tooManyAttempts('a@toko.id', '1.1.1.1'));
        $row = $db->fetchOne('SELECT identifier_hash, attempted_at FROM login_attempts LIMIT 1');
        $this->assertSame(hash('sha256', 'a@toko.id'), $row['identifier_hash']);
        $this->assertSame(gmdate('Y-m-d H:i:s', $this->now), $row['attempted_at']);
    }

    public function testRateLimiterIgnoresOldAttemptsAndOtherIp(): void
    {
        $db = Db::fresh();
        $rl = $this->limiter($db);
        for ($i = 0; $i < 5; $i++) {
            $rl->record('a@toko.id', '2.2.2.2', false);
        }
        $this->assertFalse($rl->tooManyAttempts('a@toko.id', '1.1.1.1'));
        $this->assertTrue($rl->tooManyAttempts('a@toko.id', '2.2.2.2'));
        $this->now += 901;
        $this->assertFalse($rl->tooManyAttempts('a@toko.id', '2.2.2.2'));
    }

    public function testSuccessfulLoginDoesNotCountTowardLimit(): void
    {
        $db = Db::fresh();
        $rl = $this->limiter($db);
        for ($i = 0; $i < 4; $i++) {
            $rl->record('a@toko.id', '1.1.1.1', false);
        }
        for ($i = 0; $i < 3; $i++) {
            $rl->record('a@toko.id', '1.1.1.1', true);
        }
        $this->assertFalse($rl->tooManyAttempts('a@toko.id', '1.1.1.1'));
    }

    public function testClearRemovesAttemptsForEmailAndIp(): void
    {
        $db = Db::fresh();
        $rl = $this->limiter($db);
        for ($i = 0; $i < 5; $i++) {
            $rl->record('a@toko.id', '1.1.1.1', false);
        }
        $rl->record('a@toko.id', '9.9.9.9', false);
        $rl->clear('A@toko.id', '1.1.1.1');
        $this->assertFalse($rl->tooManyAttempts('a@toko.id', '1.1.1.1'));
        $this->assertSame(1, count($db->query('SELECT id FROM login_attempts')));
    }

    public function testRecordPurgesRowsOlderThanWindowOnly(): void
    {
        $db = Db::fresh();
        $rl = $this->limiter($db);
        $rl->record('old@toko.id', '1.1.1.1', false);
        $this->now += 500;
        $rl->record('recent@toko.id', '1.1.1.1', false);
        $this->now += 500; // old = 1000 s, recent = 500 s
        $rl->record('new@toko.id', '1.1.1.1', true);
        $this->assertSame(2, count($db->query('SELECT id FROM login_attempts')));
        $this->assertSame(0, count($db->query('SELECT id FROM login_attempts WHERE identifier_hash = ?', [hash('sha256', 'old@toko.id')])));
        $this->assertSame(1, count($db->query('SELECT id FROM login_attempts WHERE identifier_hash = ?', [hash('sha256', 'recent@toko.id')])));
    }
}
