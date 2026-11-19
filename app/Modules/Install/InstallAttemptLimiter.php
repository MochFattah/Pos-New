<?php

declare(strict_types=1);

namespace App\Modules\Install;

use Closure;

/**
 * Pembatas percobaan token penginstal berbasis berkas: tabel login_attempts belum ada sebelum
 * migrasi berjalan. Dihitung per IP dan secara agregat (menahan penebakan dari banyak IP).
 * Gagal tulis berarti gagal tertutup (dianggap terkunci).
 */
final class InstallAttemptLimiter
{
    private Closure $clock;

    /** @param (callable(): int)|null $clock */
    public function __construct(
        private readonly string $file,
        private readonly int $maxPerIp = 5,
        private readonly int $maxTotal = 30,
        private readonly int $windowSeconds = 900,
        ?callable $clock = null,
    ) {
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    public function tooManyAttempts(string $ip): bool
    {
        $entries = $this->withEntries(static fn (array $e): array => $e, false);
        if ($entries === null) {
            return true;
        }
        $hash = self::hash($ip);
        $mine = 0;
        foreach ($entries as $entry) {
            if ($entry[1] === $hash) {
                $mine++;
            }
        }
        return $mine >= $this->maxPerIp || count($entries) >= $this->maxTotal;
    }

    public function recordFailure(string $ip): void
    {
        $hash = self::hash($ip);
        $now = ($this->clock)();
        $this->withEntries(static function (array $e) use ($hash, $now): array {
            $e[] = [$now, $hash];
            return $e;
        }, true);
    }

    /**
     * @param callable(list<array{0: int, 1: string}>): list<array{0: int, 1: string}> $mutate
     * @return list<array{0: int, 1: string}>|null null bila berkas tidak dapat dipakai
     */
    private function withEntries(callable $mutate, bool $write): ?array
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return null;
        }
        $fh = @fopen($this->file, 'c+');
        if ($fh === false) {
            return null;
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                return null;
            }
            $decoded = json_decode((string) stream_get_contents($fh), true);
            $since = ($this->clock)() - $this->windowSeconds;
            $entries = [];
            foreach (is_array($decoded) ? $decoded : [] as $row) {
                if (is_array($row) && is_int($row[0] ?? null) && is_string($row[1] ?? null) && $row[0] >= $since) {
                    $entries[] = [$row[0], $row[1]];
                }
            }
            $entries = $mutate($entries);
            if ($write) {
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, json_encode($entries, JSON_THROW_ON_ERROR));
                fflush($fh);
                @chmod($this->file, 0600);
            }
            return $entries;
        } finally {
            fclose($fh);
        }
    }

    private static function hash(string $ip): string
    {
        return hash('sha256', $ip);
    }
}
