<?php

declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Config;
use PDO;
use Throwable;

final class Connection
{
    private int $depth = 0;

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** Koneksi MySQL dari config/database.php; sesi diset ke UTC. */
    public static function fromConfig(): self
    {
        /** @var array<string, mixed> $cfg */
        $cfg = (array) Config::get('database', []);
        $pdo = new PDO(
            self::mysqlDsn($cfg),
            (string) ($cfg['user'] ?? ''),
            (string) ($cfg['password'] ?? ''),
            self::mysqlOptions()
        );
        return new self($pdo);
    }

    /**
     * Opsi sesi MySQL: rowCount = baris cocok (sama dengan SQLite), UTC, mode ketat
     * (hosting bersama bisa non-strict/MariaDB), dan kolasi koneksi = kolasi tabel.
     *
     * @return array<int, mixed>
     */
    public static function mysqlOptions(): array
    {
        return [
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone='+00:00', "
                . "sql_mode='ONLY_FULL_GROUP_BY,STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION', "
                . 'NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ];
    }

    /** @param array<string, mixed> $cfg */
    public static function mysqlDsn(array $cfg): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($cfg['host'] ?? '127.0.0.1'),
            (int) ($cfg['port'] ?? 3306),
            (string) ($cfg['name'] ?? ''),
            (string) ($cfg['charset'] ?? 'utf8mb4')
        );
    }

    /** Khusus tes. */
    public static function sqliteMemory(): self
    {
        return new self(new PDO('sqlite::memory:'));
    }

    public function driver(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return int jumlah baris terpengaruh
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Transaksi bersarang memakai SAVEPOINT; exception apa pun me-rollback
     * lingkup yang bersangkutan dan dilempar ulang apa adanya.
     */
    public function transaction(callable $fn): mixed
    {
        $outer = $this->depth === 0;
        $savepoint = 'sp_' . $this->depth;

        if ($outer) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT ' . $savepoint);
        }
        $this->depth++;

        try {
            $result = $fn();
        } catch (Throwable $e) {
            $this->depth--;
            if ($outer) {
                try {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                } catch (Throwable) {
                    // Transaksi sudah dibatalkan server; jangan menutupi exception asli.
                }
            } else {
                try {
                    $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                } catch (Throwable) {
                    // Server sudah membatalkan transaksi (deadlock/lock wait): savepoint hilang; jangan menutupi exception asli.
                }
            }
            throw $e;
        }

        $this->depth--;
        if ($outer) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }
        return $result;
    }
}
