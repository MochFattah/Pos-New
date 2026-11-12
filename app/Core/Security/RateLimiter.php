<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Database\Connection;
use Closure;

final class RateLimiter
{
    private Closure $clock;
    private readonly int $retentionSeconds;

    /** @param (callable(): int)|null $clock */
    public function __construct(
        private readonly Connection $db,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
        ?callable $clock = null,
        ?int $retentionSeconds = null,
    ) {
        // Tabel login_attempts dipakai bersama beberapa limiter dengan jendela berbeda. Penghapusan
        // memakai retensi terbesar yang dikonfigurasi (bukan jendela milik limiter ini), agar satu
        // kegagalan login tidak menghapus baris reset/registrasi yang jendelanya lebih panjang.
        $this->retentionSeconds = max($windowSeconds, $retentionSeconds ?? $windowSeconds);
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    public function tooManyAttempts(string $email, string $ip): bool
    {
        $since = gmdate('Y-m-d H:i:s', ($this->clock)() - $this->windowSeconds);
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM login_attempts
             WHERE identifier_hash = ? AND ip = ? AND succeeded = 0 AND attempted_at >= ?',
            [self::identifier($email), $ip, $since]
        );
        return (int) ($row['n'] ?? 0) >= $this->maxAttempts;
    }

    public function record(string $email, string $ip, bool $succeeded): void
    {
        $now = ($this->clock)();
        $this->db->execute(
            'DELETE FROM login_attempts WHERE attempted_at < ?',
            [gmdate('Y-m-d H:i:s', $now - $this->retentionSeconds)]
        );
        $this->db->execute(
            'INSERT INTO login_attempts (identifier_hash, ip, succeeded, attempted_at) VALUES (?, ?, ?, ?)',
            [self::identifier($email), $ip, $succeeded ? 1 : 0, gmdate('Y-m-d H:i:s', $now)]
        );
    }

    public function clear(string $email, string $ip): void
    {
        $this->db->execute(
            'DELETE FROM login_attempts WHERE identifier_hash = ? AND ip = ?',
            [self::identifier($email), $ip]
        );
    }

    private static function identifier(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }
}
