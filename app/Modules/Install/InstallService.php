<?php

declare(strict_types=1);

namespace App\Modules\Install;

use App\Core\Database\Connection;
use App\Core\Database\Migrator;
use App\Core\Database\Seeders\ReferenceDataSeeder;
use Closure;
use Throwable;

/**
 * Pemasangan satu kali: migrasi, data referensi, lalu berkas kunci storage/installed.lock.
 * Tidak membuat .env dan tidak membuat Super Admin (CLI, lihat scripts/create-platform-admin.php).
 */
final class InstallService
{
    public const OK = 'ok';
    public const ALREADY = 'already';
    public const BUSY = 'busy';
    public const PREFLIGHT = 'preflight';
    public const FAILED = 'failed';

    /** Token lebih pendek dari ini dianggap tidak ada (penginstal mati): mudah ditebak. */
    public const MIN_TOKEN_LENGTH = 16;

    private Closure $dbFactory;

    /**
     * @param callable(): Connection $dbFactory koneksi dibuat malas, hanya saat pemasangan
     * @param list<string> $secrets nilai rahasia (kata sandi/user DB) yang disamarkan dari log
     */
    public function __construct(
        private readonly string $lockPath,
        private readonly string $token,
        callable $dbFactory,
        private readonly string $migrationsDir,
        private readonly PreflightChecker $preflight,
        private readonly string $logPath,
        private readonly array $secrets = [],
    ) {
        $this->dbFactory = Closure::fromCallable($dbFactory);
    }

    public function isInstalled(): bool
    {
        clearstatcache(true, $this->lockPath);
        return is_file($this->lockPath);
    }

    /** Aktif hanya bila belum terpasang dan INSTALL_TOKEN sedikitnya MIN_TOKEN_LENGTH karakter. */
    public function isActive(): bool
    {
        return !$this->isInstalled() && $this->tokenUsable();
    }

    private function tokenUsable(): bool
    {
        return strlen($this->token) >= self::MIN_TOKEN_LENGTH;
    }

    public function tokenMatches(string $given): bool
    {
        if (!$this->tokenUsable()) {
            return false;
        }
        return hash_equals(hash('sha256', $this->token), hash('sha256', trim($given)));
    }

    /** @return list<array{label: string, ok: bool, message: string}> */
    public function preflight(): array
    {
        return $this->preflight->run();
    }

    /**
     * @return array{status: string, applied: list<string>}
     */
    public function install(): array
    {
        $result = static fn (string $status, array $applied = []): array => ['status' => $status, 'applied' => $applied];

        if ($this->isInstalled()) {
            return $result(self::ALREADY);
        }
        if (!PreflightChecker::allPassed($this->preflight->run())) {
            return $result(self::PREFLIGHT);
        }

        // flock dilepas otomatis oleh OS bila proses mati, jadi tidak ada kunci basi.
        $guard = @fopen(dirname($this->lockPath) . '/installing.lock', 'c');
        if ($guard === false) {
            $this->log('tidak dapat membuka storage/installing.lock');
            return $result(self::FAILED);
        }
        try {
            if (!flock($guard, LOCK_EX | LOCK_NB)) {
                return $result(self::BUSY);
            }
            if ($this->isInstalled()) {
                return $result(self::ALREADY);
            }

            try {
                $db = ($this->dbFactory)();
                $applied = (new Migrator($db, $this->migrationsDir))->migrate();
                ReferenceDataSeeder::run($db);
            } catch (Throwable $e) {
                $this->log(sprintf('migrasi/seeder gagal: %s (kode %s): %s', $e::class, (string) $e->getCode(), $this->redact($e->getMessage())));
                return $result(self::FAILED);
            }

            // Pembuatan eksklusif: hanya satu proses yang berhasil menulis kunci.
            $lock = @fopen($this->lockPath, 'x');
            if ($lock === false) {
                return $result($this->isInstalled() ? self::ALREADY : self::FAILED);
            }
            fwrite($lock, gmdate('c') . "\n");
            fclose($lock);
            @chmod($this->lockPath, 0640);

            return $result(self::OK, $applied);
        } finally {
            flock($guard, LOCK_UN);
            fclose($guard);
        }
    }

    private function redact(string $message): string
    {
        foreach ($this->secrets as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '[disamarkan]', $message);
            }
        }
        $message = (string) preg_replace('/[\r\n\t]+/', ' ', $message);
        return mb_strlen($message) > 300 ? mb_substr($message, 0, 300) . '...' : $message;
    }

    private function log(string $line): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents($this->logPath, '[' . date('c') . '] install: ' . $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
