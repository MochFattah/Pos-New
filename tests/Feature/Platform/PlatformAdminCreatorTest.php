<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Exceptions\DuplicatePlatformAdminException;
use App\Modules\Platform\Exceptions\PlatformAdminException;
use Tests\Support\PlatformHarness;

final class PlatformAdminCreatorTest extends PlatformHarness
{
    public function testCreatesAdminWithNormalizedEmailAndHashedPassword(): void
    {
        $id = $this->creator->create('  Admin Satu ', '  Root@Example.COM ', 'sandi-aman-123');
        $row = $this->db->fetchOne('SELECT * FROM platform_users WHERE id = ?', [$id]);
        $this->assertSame('root@example.com', $row['email']);
        $this->assertSame('Admin Satu', $row['name']);
        $this->assertSame('active', $row['status']);
        $this->assertTrue($row['password_hash'] !== 'sandi-aman-123' && password_verify('sandi-aman-123', $row['password_hash']));
        $audit = $this->db->fetchOne("SELECT * FROM audit_logs WHERE action = 'platform.admin_created'");
        $this->assertSame(null, $audit['tenant_id']);
        $this->assertSame('system', $audit['actor_type']);
        $this->assertSame($id, (int) $audit['entity_id']);
        $this->assertFalse(str_contains(json_encode($audit, JSON_THROW_ON_ERROR), 'sandi-aman-123'));
    }

    public function testRejectsDuplicateEmailCaseInsensitivelyWithoutChangingExisting(): void
    {
        $this->creator->create('Satu', 'root@example.com', 'sandi-aman-123');
        $this->assertThrows(DuplicatePlatformAdminException::class, fn () => $this->creator->create('Dua', 'ROOT@example.com', 'sandi-lain-9999'));
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM platform_users'));
        $row = $this->db->fetchOne('SELECT * FROM platform_users');
        $this->assertSame('Satu', $row['name']);
        $this->assertTrue(password_verify('sandi-aman-123', $row['password_hash']));
    }

    public function testRejectsWeakOrInvalidInput(): void
    {
        $bad = [
            ['Nama', 'a@example.com', 'pendek1'],
            ['Nama', 'a@example.com', str_repeat('x', 73)],
            ['Nama', 'a@example.com', "sandi\0panjang-ok"],
            ['Nama', 'a@example.com', ''],
            ['Nama', 'bukan-email', 'sandi-aman-123'],
            ['Nama', str_repeat('a', 190) . '@example.com', 'sandi-aman-123'],
            ['', 'a@example.com', 'sandi-aman-123'],
            [str_repeat('n', 151), 'a@example.com', 'sandi-aman-123'],
            ["Na\nma", 'a@example.com', 'sandi-aman-123'],
        ];
        foreach ($bad as [$n, $e, $p]) {
            $this->assertThrows(PlatformAdminException::class, fn () => $this->creator->create($n, $e, $p));
        }
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM platform_users'));
        $this->assertTrue($this->creator->create('Nama', 'ok@example.com', str_repeat('x', 72)) > 0, 'tepat 72 byte diterima');
    }
}
