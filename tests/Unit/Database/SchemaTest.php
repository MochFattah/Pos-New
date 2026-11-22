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
            $has = array_key_exists('tenant_id', $def['columns']);
            if (in_array($table, self::NON_TENANT, true)) {
                $this->assertFalse($has, "$table seharusnya tanpa tenant_id");
                continue;
            }
            $this->assertTrue($has, "$table tanpa tenant_id");
            $this->assertSame($table !== 'audit_logs', $def['columns']['tenant_id'], "NOT NULL tenant_id pada $table");
        }
    }

    public function testEveryForeignKeyColumnIsIndexed(): void
    {
        $checked = 0;
        foreach ($this->mysql() as $table => $def) {
            foreach ($def['fks'] as $fk) {
                $checked++;
                $n = count($fk['cols']);
                $ok = false;
                foreach ($def['indexes'] as $idx) {
                    if (array_slice($idx, 0, $n) === $fk['cols']) {
                        $ok = true;
                    }
                }
                $this->assertTrue($ok, "$table(" . implode(',', $fk['cols']) . ') tanpa indeks berawalan kolom FK');
            }
        }
        $this->assertTrue($checked >= 15, "terlalu sedikit FK: $checked");
    }

    public function testForeignKeysReferenceUniqueKeys(): void
    {
        $all = $this->mysql();
        foreach ($all as $table => $def) {
            foreach ($def['fks'] as $fk) {
                $ref = $all[$fk['ref_table']];
                $ok = $ref['primary'] === $fk['ref_cols'] || in_array($fk['ref_cols'], $ref['uniques'], true);
                $this->assertTrue($ok, "FK $table(" . implode(',', $fk['cols']) . ") merujuk kolom non-unik di {$fk['ref_table']}");
            }
        }
    }

    public function testJunctionTablesUseCompositeTenantForeignKeys(): void
    {
        $t = $this->mysql();
        $has = static function (string $table, array $cols, string $ref, array $refCols) use ($t): bool {
            foreach ($t[$table]['fks'] as $fk) {
                if ($fk['cols'] === $cols && $fk['ref_table'] === $ref && $fk['ref_cols'] === $refCols) {
                    return true;
                }
            }
            return false;
        };
        $this->assertTrue($has('user_roles', ['tenant_id', 'user_id'], 'users', ['tenant_id', 'id']));
        $this->assertTrue($has('user_roles', ['tenant_id', 'role_id'], 'roles', ['tenant_id', 'id']));
        $this->assertTrue($has('user_branch_access', ['tenant_id', 'user_id'], 'users', ['tenant_id', 'id']));
        $this->assertTrue($has('user_branch_access', ['tenant_id', 'branch_id'], 'branches', ['tenant_id', 'id']));
        $this->assertTrue($has('role_permissions', ['tenant_id', 'role_id'], 'roles', ['tenant_id', 'id']));
        $this->assertTrue($has('role_permissions', ['permission_id'], 'permissions', ['id']));
    }

    private function sqliteSql(): string
    {
        return (string) file_get_contents($this->root() . '/tests/Support/schema.sqlite.sql');
    }

    public function testSqliteSchemaMatchesMysqlMigration(): void
    {
        $drift = SqlSchemaParser::drift($this->mysql(), SqlSchemaParser::parse($this->sqliteSql()));
        $this->assertSame([], $drift);
    }

    /** @return list<array{string, string, string}> [deskripsi, cari, ganti] */
    private static function mutations(): array
    {
        return [
            ['unique', "  CONSTRAINT uq_users_email UNIQUE (email),\n", ''],
            ['foreign key', 'REFERENCES platform_users (id)', 'REFERENCES users (id)'],
            ['foreign key (kolom komposit)', 'FOREIGN KEY (tenant_id, role_id) REFERENCES roles (tenant_id, id)', 'FOREIGN KEY (role_id) REFERENCES roles (id)'],
            ['on delete', 'REFERENCES users (tenant_id, id) ON DELETE CASCADE', 'REFERENCES users (tenant_id, id)'],
            ['NOT NULL', 'reason TEXT NULL', 'reason TEXT NOT NULL'],
            ['enum', "CHECK (source IN ('registration','grant','extension'))", "CHECK (source IN ('registration','grant'))"],
        ];
    }

    public function testDriftGuardDetectsMutatedSqliteSchema(): void
    {
        $sql = $this->sqliteSql();
        $mysql = $this->mysql();
        foreach (self::mutations() as [$label, $find, $replace]) {
            $this->assertTrue(substr_count($sql, $find) >= 1, "mutasi '$label' tidak menemukan teks");
            $mutated = substr_replace($sql, $replace, (int) strpos($sql, $find), strlen($find));
            $drift = SqlSchemaParser::drift($mysql, SqlSchemaParser::parse($mutated));
            $this->assertTrue(count($drift) >= 1, "drift '$label' tidak terdeteksi");
        }
    }

    public function testMysqlMigrationSplitsIntoExpectedStatementCount(): void
    {
        $statements = Migrator::split($this->mysqlSql(), true);
        $this->assertSame(17, count($statements));
        foreach ($statements as $s) {
            $this->assertTrue($s !== '');
            $this->assertTrue(preg_match('/^CREATE TABLE IF NOT EXISTS `?\w+`?\s*\(/', $s) === 1, 'bukan CREATE TABLE: ' . substr($s, 0, 40));
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

    /** @return array<int, array{t: int, u: int, r: int, b: int}> */
    private function seedTenantGraph(Connection $db): array
    {
        $ts = "'2026-01-01 00:00:00'";
        $ids = [];
        foreach ([1, 2] as $n) {
            $t = $this->tenant($db, "g$n");
            $db->execute("INSERT INTO users (tenant_id, name, email, password_hash, status, is_owner, created_at, updated_at) VALUES (?, 'U', 'u$n@x.id', 'h', 'active', 0, $ts, $ts)", [$t]);
            $u = $db->lastInsertId();
            $db->execute("INSERT INTO roles (tenant_id, slug, name, is_system) VALUES (?, 'r', 'R', 0)", [$t]);
            $r = $db->lastInsertId();
            $db->execute("INSERT INTO branches (tenant_id, code, name, is_main, status, created_at, updated_at) VALUES (?, 'B', 'B', 0, 'active', $ts, $ts)", [$t]);
            $b = $db->lastInsertId();
            $ids[$n] = ['t' => $t, 'u' => $u, 'r' => $r, 'b' => $b];
        }
        return $ids;
    }

    public function testUserRolesRejectCrossTenantLinks(): void
    {
        $db = Db::fresh();
        $g = $this->seedTenantGraph($db);
        $sql = 'INSERT INTO user_roles (user_id, role_id, tenant_id) VALUES (?, ?, ?)';
        foreach ([$g[1]['t'], $g[2]['t'], 999] as $tid) {
            $this->assertThrows(PDOException::class, fn () => $db->execute($sql, [$g[1]['u'], $g[2]['r'], $tid]));
        }
        $this->assertSame(1, $db->execute($sql, [$g[1]['u'], $g[1]['r'], $g[1]['t']]));
    }

    public function testUserBranchAccessRejectsCrossTenantLinks(): void
    {
        $db = Db::fresh();
        $g = $this->seedTenantGraph($db);
        $sql = 'INSERT INTO user_branch_access (user_id, branch_id, tenant_id) VALUES (?, ?, ?)';
        foreach ([$g[1]['t'], $g[2]['t'], 999] as $tid) {
            $this->assertThrows(PDOException::class, fn () => $db->execute($sql, [$g[1]['u'], $g[2]['b'], $tid]));
        }
        $this->assertSame(1, $db->execute($sql, [$g[2]['u'], $g[2]['b'], $g[2]['t']]));
    }

    public function testRolePermissionsRejectWrongTenant(): void
    {
        $db = Db::fresh();
        $g = $this->seedTenantGraph($db);
        $perm = (int) $db->fetchOne("SELECT id FROM permissions WHERE code = 'users.view'")['id'];
        $sql = 'INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)';
        $this->assertThrows(PDOException::class, fn () => $db->execute($sql, [$g[1]['r'], $perm, $g[2]['t']]));
        $this->assertThrows(PDOException::class, fn () => $db->execute($sql, [$g[1]['r'], $perm, 999]));
        $this->assertSame(1, $db->execute($sql, [$g[1]['r'], $perm, $g[1]['t']]));
    }
}
