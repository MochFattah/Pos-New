<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database\Migrator;

/**
 * Pemindai sederhana untuk CREATE TABLE (MySQL maupun SQLite) dan pembanding drift.
 *
 * Model per tabel:
 *  columns: nama => NOT NULL? (kolom PRIMARY KEY dianggap NOT NULL)
 *  primary: list<string>
 *  uniques: list<list<string>> (UNIQUE tingkat tabel; SQLite wajib memakai bentuk ini)
 *  indexes: list<list<string>> (PK, UNIQUE, dan KEY biasa)
 *  fks:     list<array{cols: list<string>, ref_table: string, ref_cols: list<string>, on_delete: string}>
 *  enums:   kolom => list<string> (ENUM MySQL atau CHECK ... IN (...) SQLite)
 *
 * @phpstan-type Table array{columns: array<string,bool>, primary: list<string>, uniques: list<list<string>>, indexes: list<list<string>>, fks: list<array{cols: list<string>, ref_table: string, ref_cols: list<string>, on_delete: string}>, enums: array<string, list<string>>}
 */
final class SqlSchemaParser
{
    /** @return array<string, array<string, mixed>> */
    public static function parse(string $sql): array
    {
        $tables = [];
        foreach (Migrator::split($sql, true) as $statement) {
            if (!preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\(/i', $statement, $m)) {
                continue;
            }
            $body = substr($statement, strlen($m[0]));
            $body = substr($body, 0, (int) strrpos($body, ')'));
            $t = ['columns' => [], 'primary' => [], 'uniques' => [], 'indexes' => [], 'fks' => [], 'enums' => []];
            foreach (self::splitTopLevel($body) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                if (preg_match('/^(?:CONSTRAINT\s+`?\w+`?\s+)?FOREIGN\s+KEY\s*\(([^)]*)\)\s*REFERENCES\s+`?(\w+)`?\s*\(([^)]*)\)(?:\s+ON\s+DELETE\s+(CASCADE|SET\s+NULL|RESTRICT|NO\s+ACTION))?/i', $part, $f)) {
                    $action = strtoupper(preg_replace('/\s+/', ' ', $f[4] ?? 'RESTRICT'));
                    $t['fks'][] = [
                        'cols' => self::names($f[1]),
                        'ref_table' => $f[2],
                        'ref_cols' => self::names($f[3]),
                        'on_delete' => $action === 'NO ACTION' ? 'RESTRICT' : $action,
                    ];
                } elseif (preg_match('/^PRIMARY\s+KEY\s*\(([^)]*)\)/i', $part, $f)) {
                    $t['primary'] = self::names($f[1]);
                    $t['indexes'][] = $t['primary'];
                } elseif (preg_match('/^(?:CONSTRAINT\s+`?\w+`?\s+)?UNIQUE(?:\s+(?:KEY|INDEX))?(?:\s+`?\w+`?)?\s*\(([^)]*)\)/i', $part, $f)) {
                    $t['uniques'][] = self::names($f[1]);
                    $t['indexes'][] = self::names($f[1]);
                } elseif (preg_match('/^(?:KEY|INDEX)\s+`?\w+`?\s*\(([^)]*)\)/i', $part, $f)) {
                    $t['indexes'][] = self::names($f[1]);
                } elseif (preg_match('/^(?:CONSTRAINT|CHECK)\b/i', $part)) {
                    continue;
                } elseif (preg_match('/^`?(\w+)`?\s/', $part . ' ', $c)) {
                    $col = $c[1];
                    $inlinePk = preg_match('/\bPRIMARY\s+KEY\b/i', $part) === 1;
                    $t['columns'][$col] = $inlinePk || preg_match('/\bNOT\s+NULL\b/i', $part) === 1;
                    if ($inlinePk) {
                        $t['primary'] = [$col];
                        $t['indexes'][] = [$col];
                    }
                    if (preg_match('/\bENUM\s*\(([^)]*)\)/i', $part, $e)
                        || preg_match('/\bCHECK\s*\(\s*`?\w+`?\s+IN\s*\(([^)]*)\)\s*\)/i', $part, $e)) {
                        preg_match_all("/'([^']*)'/", $e[1], $vals);
                        $t['enums'][$col] = $vals[1];
                    }
                }
            }
            foreach ($t['primary'] as $pk) {
                $t['columns'][$pk] = true;
            }
            $tables[$m[1]] = $t;
        }
        return $tables;
    }

    /**
     * Daftar ketidakselarasan antara skema MySQL dan SQLite (kosong = selaras).
     *
     * @param array<string, array<string, mixed>> $mysql
     * @param array<string, array<string, mixed>> $sqlite
     * @return list<string>
     */
    public static function drift(array $mysql, array $sqlite): array
    {
        $out = [];
        $a = array_keys($mysql);
        $b = array_keys($sqlite);
        foreach (array_diff($a, $b) as $x) {
            $out[] = "tabel $x hanya ada di MySQL";
        }
        foreach (array_diff($b, $a) as $x) {
            $out[] = "tabel $x hanya ada di SQLite";
        }
        foreach ($mysql as $name => $m) {
            if (!isset($sqlite[$name])) {
                continue;
            }
            $s = $sqlite[$name];
            $mc = array_keys($m['columns']);
            $sc = array_keys($s['columns']);
            sort($mc);
            sort($sc);
            if ($mc !== $sc) {
                $out[] = "kolom $name berbeda";
            } else {
                foreach ($m['columns'] as $col => $nn) {
                    if ($nn !== $s['columns'][$col]) {
                        $out[] = "NOT NULL $name.$col berbeda";
                    }
                }
            }
            if ($m['primary'] !== $s['primary']) {
                $out[] = "primary key $name berbeda";
            }
            $mu = array_map(static fn (array $c): string => implode(',', $c), $m['uniques']);
            $su = array_map(static fn (array $c): string => implode(',', $c), $s['uniques']);
            sort($mu);
            sort($su);
            if ($mu !== $su) {
                $out[] = "unique $name berbeda";
            }
            $mf = array_map([self::class, 'fkKey'], $m['fks']);
            $sf = array_map([self::class, 'fkKey'], $s['fks']);
            sort($mf);
            sort($sf);
            if ($mf !== $sf) {
                $out[] = "foreign key $name berbeda";
            }
            ksort($m['enums']);
            ksort($s['enums']);
            if ($m['enums'] !== $s['enums']) {
                $out[] = "enum $name berbeda";
            }
        }
        return $out;
    }

    /** @param array{cols: list<string>, ref_table: string, ref_cols: list<string>, on_delete: string} $fk */
    private static function fkKey(array $fk): string
    {
        return implode(',', $fk['cols']) . '->' . $fk['ref_table'] . '(' . implode(',', $fk['ref_cols']) . ') ' . $fk['on_delete'];
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
