<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Core\Database\Connection;
use App\Core\Database\Migrator;
use PDOException;
use Tests\Support\Db;
use Tests\Support\SqlSchemaParser;
use Tests\Support\TestCase;

final class SchemaTest extends TestCase
{
    private const NON_TENANT = [
        'platform_users', 'tenants', 'permissions', 'demo_settings', 'schema_migrations',
        'email_verifications', 'password_resets', 'login_attempts',
    ];

    private const TABLES = [
        'platform_users', 'tenants', 'tenant_profiles', 'tenant_settings', 'branches', 'users', 'roles',
        'permissions', 'role_permissions', 'user_roles', 'user_branch_access', 'demo_settings',
        'tenant_demos', 'email_verifications', 'password_resets', 'login_attempts', 'audit_logs',
    ];

    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function mysqlSql(): string
    {
        return (string) file_get_contents($this->root() . '/database/migrations/0001_foundation_schema.sql');
    }

    /** @return array<string, array{columns: list<string>, indexes: list<list<string>>, fks: list<list<string>>}> */
    private function mysql(): array
    {
        return SqlSchemaParser::parse($this->mysqlSql());
    }

    public function testMysqlMigrationHasExactlyTheSeventeenTables(): void
    {
        $names = array_keys($this->mysql());
        sort($names);
        $expected = self::TABLES;
        sort($expected);
        $this->assertSame($expected, $names);
    }

    public function testEveryTenantTableHasTenantId(): void
    {
        foreach ($this->mysql() as $table => $def) {
            if (in_array($table, self::NON_TENANT, true)) {
                continue;
            }
            $this->assertTrue(in_array('tenant_id', $def['columns'], true), "$table tanpa tenant_id");
        }
        $this->assertTrue(count($this->mysql()) > 0);
    }

    public function testEveryForeignKeyColumnIsIndexed(): void
    {
        $checked = 0;
        foreach ($this->mysql() as $table => $def) {
            foreach ($def['fks'] as $fkCols) {
                $checked++;
                $ok = false;
                foreach ($def['indexes'] as $idx) {
                    if (array_slice($idx, 0, count($fkCols)) === $fkCols) {
                        $ok = true;
                    }
                }
                $this->assertTrue($ok, "$table(" . implode(',', $fkCols) . ') tanpa indeks');
            }
        }
        $this->assertTrue($checked >= 15, "terlalu sedikit FK: $checked");
    }

    public function testSqliteSchemaColumnsMatchMysqlMigration(): void
    {
        $sqlite = SqlSchemaParser::parse((string) file_get_contents($this->root() . '/tests/Support/schema.sqlite.sql'));
        $mysql = $this->mysql();
        $a = array_keys($mysql);
        $b = array_keys($sqlite);
        sort($a);
        sort($b);
        $this->assertSame($a, $b, 'himpunan tabel berbeda');
        foreach ($mysql as $table => $def) {
            $x = $def['columns'];
            $y = $sqlite[$table]['columns'];
            sort($x);
            sort($y);
            $this->assertSame($x, $y, "kolom $table berbeda");
            $this->assertSame(count($def['fks']), count($sqlite[$table]['fks']), "jumlah FK $table berbeda");
        }
    }

    public function testMysqlMigrationSplitsIntoExpectedStatementCount(): void
    {
        $statements = Migrator::split($this->mysqlSql(), true);
        $this->assertSame(17, count($statements));
        foreach ($statements as $s) {
            $this->assertTrue($s !== '');
            $this->assertTrue(preg_match('/^CREATE TABLE `?\w+`?\s*\(/', $s) === 1, 'bukan CREATE TABLE: ' . substr($s, 0, 40));
            $this->assertContains('ENGINE=InnoDB', $s);
            $this->assertContains('utf8mb4_unicode_ci', $s);
        }
    }

    public function testSeederIsIdempotent(): void
    {
        $db = Db::fresh();
        $seeder = require $this->root() . '/database/seeders/reference_data.php';
        $seeder($db);
        $seeder($db);
        $this->assertSame(9, (int) $db->fetchOne('SELECT COUNT(*) AS c FROM permissions')['c']);
        $demo = $db->query('SELECT is_enabled, duration_hours FROM demo_settings');
        $this->assertSame(1, count($demo));
        $this->assertSame(1, (int) $demo[0]['is_enabled']);
        $this->assertSame(24, (int) $demo[0]['duration_hours']);
        $codes = array_column($db->query('SELECT code FROM permissions ORDER BY code'), 'code');
        $expected = [
            'audit_logs.view', 'branches.manage', 'branches.view', 'roles.manage', 'roles.view',
            'tenant_settings.manage', 'tenant_settings.view', 'users.manage', 'users.view',
        ];
        $this->assertSame($expected, $codes);
        $row = $db->fetchOne("SELECT module, description FROM permissions WHERE code = 'branches.view'");
        $this->assertSame('branches', $row['module']);
        $this->assertTrue($row['description'] !== '');
    }

    private function tenant(Connection $db, string $uuid): int
    {
        $db->execute(
            "INSERT INTO tenants (uuid, name, status, created_at, updated_at) VALUES (?, 'T', 'active', '2026-01-01 00:00:00', '2026-01-01 00:00:00')",
            [$uuid]
        );
        return $db->lastInsertId();
    }

    public function testEmailIsUniqueInSqliteSchema(): void
    {
        $db = Db::fresh();
        $t = $this->tenant($db, 'u1');
        $sql = "INSERT INTO users (tenant_id, name, email, password_hash, status, is_owner, created_at, updated_at) VALUES (?, 'A', 'a@x.id', 'h', 'active', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00')";
        $db->execute($sql, [$t]);
        $t2 = $this->tenant($db, 'u2');
        $this->assertThrows(PDOException::class, fn () => $db->execute($sql, [$t2]));
    }

    public function testTenantScopedUniqueKeys(): void
    {
        $db = Db::fresh();
        $t1 = $this->tenant($db, 'u1');
        $t2 = $this->tenant($db, 'u2');
        $branch = "INSERT INTO branches (tenant_id, code, name, is_main, status, created_at, updated_at) VALUES (?, 'PST', 'Pusat', 1, 'active', '2026-01-01 00:00:00', '2026-01-01 00:00:00')";
        $db->execute($branch, [$t1]);
        $db->execute($branch, [$t2]);
        $this->assertThrows(PDOException::class, fn () => $db->execute($branch, [$t1]));
        $role = "INSERT INTO roles (tenant_id, slug, name, is_system) VALUES (?, 'kasir', 'Kasir', 1)";
        $db->execute($role, [$t1]);
        $db->execute($role, [$t2]);
        $this->assertThrows(PDOException::class, fn () => $db->execute($role, [$t1]));
    }

    public function testForeignKeysAreEnforced(): void
    {
        $db = Db::fresh();
        $this->assertThrows(PDOException::class, fn () => $db->execute(
            "INSERT INTO branches (tenant_id, code, name, is_main, status, created_at, updated_at) VALUES (999, 'X', 'X', 0, 'active', '2026-01-01 00:00:00', '2026-01-01 00:00:00')"
        ));
    }
}
