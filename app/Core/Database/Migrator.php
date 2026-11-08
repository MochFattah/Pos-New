<?php

declare(strict_types=1);

namespace App\Core\Database;

use RuntimeException;
use Throwable;

final class Migrator
{
    public function __construct(private readonly Connection $db, private readonly string $dir)
    {
    }

    /** @return list<string> nama berkas yang baru dijalankan */
    public function migrate(): array
    {
        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'name VARCHAR(255) NOT NULL PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)'
        );
        $done = array_column($this->db->query('SELECT name FROM schema_migrations'), 'name');

        $files = glob(rtrim($this->dir, '/') . '/*.sql') ?: [];
        $names = array_map('basename', $files);
        sort($names, SORT_STRING);

        $applied = [];
        foreach ($names as $name) {
            if (in_array($name, $done, true)) {
                continue;
            }
            $this->apply($name);
            $applied[] = $name;
        }
        return $applied;
    }

    private function apply(string $name): void
    {
        $sql = (string) file_get_contents(rtrim($this->dir, '/') . '/' . $name);
        $statements = self::split($sql, $this->db->driver() === 'mysql');

        $run = function () use ($statements, $name): void {
            foreach ($statements as $statement) {
                $this->db->execute($statement);
            }
            $this->db->execute(
                'INSERT INTO schema_migrations (name, applied_at) VALUES (?, ?)',
                [$name, gmdate('Y-m-d H:i:s')]
            );
        };

        try {
            if ($this->db->driver() === 'sqlite') {
                $this->db->transaction($run);
            } else {
                // MySQL meng-commit DDL secara implisit: tanpa transaksi; nama dicatat setelah semua sukses.
                $run();
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Migrasi $name gagal: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Memecah SQL pada ';' di luar string, identifier, dan komentar (-- # /* *​/).
     * Komentar dibuang.
     *
     * @return list<string>
     */
    public static function split(string $sql, bool $backslashEscapes = false): array
    {
        $out = [];
        $buf = '';
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($c === "'" || $c === '"' || $c === '`') {
                $buf .= $c;
                for ($i++; $i < $len; $i++) {
                    $d = $sql[$i];
                    $buf .= $d;
                    if ($backslashEscapes && $d === '\\' && $c !== '`' && $i + 1 < $len) {
                        $buf .= $sql[++$i];
                    } elseif ($d === $c) {
                        if (($sql[$i + 1] ?? '') === $c) { // kutip ganda = literal
                            $buf .= $sql[++$i];
                        } else {
                            break;
                        }
                    }
                }
            } elseif (($c === '-' && $next === '-') || $c === '#') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                $buf .= "\n";
            } elseif ($c === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 1;
                $buf .= ' ';
            } elseif ($c === ';') {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';
            } else {
                $buf .= $c;
            }
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }
}
