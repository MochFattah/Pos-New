<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database\Migrator;

/**
 * Pemindai sederhana untuk CREATE TABLE (MySQL maupun SQLite).
 * Hasil: nama tabel => ['columns' => list<string>, 'indexes' => list<list<string>>, 'fks' => list<list<string>>]
 * 'indexes' berisi daftar kolom dari PRIMARY KEY / UNIQUE / KEY tingkat tabel.
 */
final class SqlSchemaParser
{
    /** @return array<string, array{columns: list<string>, indexes: list<list<string>>, fks: list<list<string>>}> */
    public static function parse(string $sql): array
    {
        $tables = [];
        foreach (Migrator::split($sql, true) as $statement) {
            if (!preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\(/i', $statement, $m)) {
                continue;
            }
            $body = substr($statement, strlen($m[0]));
            $body = substr($body, 0, (int) strrpos($body, ')'));
            $table = ['columns' => [], 'indexes' => [], 'fks' => []];
            foreach (self::splitTopLevel($body) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                if (preg_match('/^(?:CONSTRAINT\s+`?\w+`?\s+)?FOREIGN\s+KEY\s*\(([^)]*)\)/i', $part, $f)) {
                    $table['fks'][] = self::names($f[1]);
                } elseif (preg_match('/^PRIMARY\s+KEY\s*\(([^)]*)\)/i', $part, $f)
                    || preg_match('/^(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`?\w+`?\s*\(([^)]*)\)/i', $part, $f)
                    || preg_match('/^(?:CONSTRAINT\s+`?\w+`?\s+)?UNIQUE(?:\s+KEY)?(?:\s+`?\w+`?)?\s*\(([^)]*)\)/i', $part, $f)
                ) {
                    $table['indexes'][] = self::names($f[1]);
                } elseif (preg_match('/^(?:CONSTRAINT|CHECK)\b/i', $part)) {
                    continue;
                } elseif (preg_match('/^`?(\w+)`?\s/', $part . ' ', $c)) {
                    $table['columns'][] = $c[1];
                }
            }
            $tables[$m[1]] = $table;
        }
        return $tables;
    }

    /** @return list<string> */
    private static function names(string $list): array
    {
        return array_map(static fn (string $n): string => trim($n, " `\t\n"), explode(',', $list));
    }

    /** @return list<string> */
    private static function splitTopLevel(string $body): array
    {
        $parts = [];
        $buf = '';
        $depth = 0;
        $quote = '';
        $len = strlen($body);
        for ($i = 0; $i < $len; $i++) {
            $c = $body[$i];
            if ($quote !== '') {
                $buf .= $c;
                if ($c === '\\' && $i + 1 < $len) {
                    $buf .= $body[++$i];
                } elseif ($c === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $parts[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        $parts[] = $buf;
        return $parts;
    }
}
