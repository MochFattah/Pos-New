<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\TenantScopeScanner;
use Tests\Support\TestCase;

final class TenantIsolationTest extends TestCase
{
    /**
     * Pengecualian disengaja: file => [potongan SQL (dinormalkan) => alasan]. Tiap entri harus
     * benar-benar cocok dengan satu pernyataan (entri basi menggagalkan tes), agar daftar ini
     * tidak membusuk.
     */
    private const ALLOWED = [
        'UserRepository.php' => [
            'SELECT * FROM users WHERE email = ?' => 'Email unik global (spec 5); dipakai login sebelum tenant diketahui. Tenant diturunkan dari baris hasil.',
            'SELECT * FROM users WHERE id = ?' => 'findByIdAnyTenant: alur token (verifikasi/reset) hanya memegang user_id dari baris token tepercaya; tenant diturunkan dari baris pengguna.',
            "UPDATE users SET status = 'active', email_verified_at = ?, updated_at = ? WHERE id = ? AND status = 'pending_verification'" => 'Aktivasi dari alur token; user_id berasal dari baris token tepercaya, bukan dari request.',
            "UPDATE users SET status = 'active', email_verified_at = ?, password_hash = ?, updated_at = ? WHERE id = ? AND status = 'pending_verification'" => 'Aktivasi + set password dari alur token undangan; user_id dari baris token.',
            "UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ? AND status = 'active'" => 'Reset password dari alur token; user_id dari baris token.',
            'UPDATE users SET last_login_at = ? WHERE id = ?' => 'Dipanggil setelah autentikasi sukses; user_id dari baris pengguna yang baru diverifikasi.',
        ],
    ];

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<string> */
    private function tenantTables(): array
    {
        return TenantScopeScanner::tenantTables((string) file_get_contents($this->root() . '/tests/Support/schema.sqlite.sql'));
    }

    public function testTenantTableListIsParsedFromSchema(): void
    {
        $tables = $this->tenantTables();
        foreach (['branches', 'users', 'roles', 'role_permissions', 'user_roles', 'user_branch_access', 'audit_logs', 'tenant_demos'] as $t) {
            $this->assertTrue(in_array($t, $tables, true), $t);
        }
        foreach (['tenants', 'permissions', 'password_resets', 'email_verifications', 'login_attempts', 'platform_users'] as $t) {
            $this->assertFalse(in_array($t, $tables, true), $t);
        }
    }

    public function testScannerFlagsQueriesThatOmitTenantId(): void
    {
        $tables = $this->tenantTables();
        $php = <<<'PHP'
<?php
$a = $db->query('SELECT * FROM branches WHERE id = ?', [$id]);
$b = $db->execute("UPDATE users SET name = ? " . "WHERE id = ?", []);
$c = $db->query('SELECT * FROM audit_logs ' . $where . ' LIMIT 5');
$d = $db->query('SELECT * FROM branches WHERE tenant_id = ? AND id = ?', []);
$e = $db->query('SELECT * FROM tenants WHERE id = ?', []);
PHP;
        $found = TenantScopeScanner::violations($php, $tables);
        $this->assertSame(3, count($found), implode(' | ', $found));
        $this->assertContains('FROM branches WHERE id = ?', $found[0]);
        $this->assertContains('UPDATE users SET name = ? WHERE id = ?', $found[1]);
        $this->assertContains('FROM audit_logs', $found[2]);
    }

    public function testNoRepositoryQueryOmitsTenantId(): void
    {
        $tables = $this->tenantTables();
        $files = glob($this->root() . '/app/Modules/*/Repositories/*.php') ?: [];
        $this->assertTrue(count($files) >= 7, 'pemindai harus menemukan repository T7-T10');
        $used = [];
        $problems = [];
        foreach ($files as $file) {
            $base = basename($file);
            foreach (TenantScopeScanner::violations((string) file_get_contents($file), $tables) as $sql) {
                if (isset(self::ALLOWED[$base][$sql])) {
                    $used[$base][$sql] = true;
                    continue;
                }
                $problems[] = "$base: $sql";
            }
        }
        $this->assertSame([], $problems, "Query tabel tenant tanpa tenant_id:\n" . implode("\n", $problems));
        foreach (self::ALLOWED as $base => $entries) {
            foreach (array_keys($entries) as $sql) {
                $this->assertTrue(isset($used[$base][$sql]), "Entri allowlist basi: $base: $sql");
            }
        }
    }
}
