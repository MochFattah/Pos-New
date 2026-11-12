<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Pemindai statis: setiap string SQL (SELECT/INSERT/UPDATE/DELETE) di kode PHP yang menyebut
 * tabel bertanda tenant (punya kolom tenant_id) wajib memuat "tenant_id". String yang disambung
 * dengan "." (termasuk variabel di tengahnya) dianggap satu pernyataan.
 */
final class TenantScopeScanner
{
    /** @return list<string> nama tabel yang memiliki kolom tenant_id menurut skema SQLite uji */
    public static function tenantTables(string $schemaSql): array
    {
        $tables = [];
        foreach (SqlSchemaParser::parse($schemaSql) as $name => $def) {
            if (array_key_exists('tenant_id', $def['columns'])) {
                $tables[] = (string) $name;
            }
        }
        sort($tables);
        return $tables;
    }

    /**
     * @param list<string> $tenantTables
     * @return list<string> SQL (dinormalkan spasinya) yang menyentuh tabel tenant tanpa tenant_id
     */
    public static function violations(string $php, array $tenantTables): array
    {
        $out = [];
        foreach (self::statements($php) as $sql) {
            if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $sql) !== 1) {
                continue;
            }
            $touches = false;
            foreach ($tenantTables as $table) {
                if (preg_match('/\b' . preg_quote($table, '/') . '\b/i', $sql) === 1) {
                    $touches = true;
                    break;
                }
            }
            if ($touches && stripos($sql, 'tenant_id') === false) {
                $out[] = $sql;
            }
        }
        return $out;
    }

    /** @return list<string> */
    private static function statements(string $php): array
    {
        $statements = [];
        $buffer = '';
        $flush = static function () use (&$buffer, &$statements): void {
            if (trim($buffer) !== '') {
                $statements[] = trim((string) preg_replace('/\s+/', ' ', $buffer));
            }
            $buffer = '';
        };
        $pass = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_VARIABLE, T_OBJECT_OPERATOR, T_STRING, T_LNUMBER, T_NS_SEPARATOR];
        foreach (token_get_all($php) as $token) {
            if (is_string($token)) {
                if ($token === '.' || $token === '"') {
                    continue;
                }
                $flush();
                continue;
            }
            [$id, $text] = $token;
            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                $buffer .= ' ' . substr($text, 1, -1);
            } elseif ($id === T_ENCAPSED_AND_WHITESPACE) {
                $buffer .= ' ' . $text;
            } elseif (!in_array($id, $pass, true)) {
                $flush();
            }
        }
        $flush();
        return $statements;
    }
}
