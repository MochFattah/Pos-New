<?php

declare(strict_types=1);

namespace Tests\Feature\Roles;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use Tests\Support\UserHarness;

final class RoleTest extends UserHarness
{
    /** @return array{tenantId: int, owner: \App\Core\Authorization\AuthUser, ownerId: int} */
    private function tenant(string $email = 'budi@example.com'): array
    {
        $r = $this->activeOwner($email);
        return ['tenantId' => $r['tenantId'], 'ownerId' => $r['userId'], 'owner' => $this->authUserFor($r['tenantId'], $r['userId'])];
    }

    /** @return list<string> */
    private function codes(int $tenantId, int $roleId): array
    {
        $codes = array_map('strval', array_column($this->db->query(
            'SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.tenant_id = ? AND rp.role_id = ? ORDER BY p.code',
            [$tenantId, $roleId]
        ), 'code'));
        return $codes;
    }

    public function testOwnerRolePermissionsCannotBeEdited(): void
    {
        $t = $this->tenant();
        $owner = $this->roleId($t['tenantId'], 'owner');
        $this->assertThrows(ValidationException::class, fn () => $this->roleService->syncPermissions($t['owner'], $owner, ['branches.view']));
        $this->assertThrows(ValidationException::class, fn () => $this->roleService->syncPermissions($t['owner'], $owner, []));
        $this->assertSame([], $this->codes($t['tenantId'], $owner));
        $this->assertSame(0, $this->auditCount('role.permissions_synced'));
        $this->assertSame(302, $this->login('budi@example.com')->status());
        $this->assertSame(302, $this->status('POST', "/role/$owner/izin", ['codes' => ['branches.view']]));
        $this->assertSame([], $this->codes($t['tenantId'], $owner));
        // Tetap berlaku untuk aktor non-Owner yang berwenang penuh.
        $mgr = $this->makeUser($t['tenantId'], 'mgr@example.com', ['roles.manage', 'roles.view', 'branches.view']);
        $this->assertThrows(ValidationException::class, fn () => $this->roleService->syncPermissions($this->authUserFor($t['tenantId'], $mgr), $owner, ['branches.view']));
    }

    public function testActorCannotGrantPermissionTheyLack(): void
    {
        $t = $this->tenant();
        $mgr = $this->makeUser($t['tenantId'], 'mgr@example.com', ['roles.view', 'roles.manage', 'branches.view']);
        $actor = $this->authUserFor($t['tenantId'], $mgr);
        $kasir = $this->roleId($t['tenantId'], 'kasir');

        $this->roleService->syncPermissions($actor, $kasir, ['branches.view']);
        $this->assertSame(['branches.view'], $this->codes($t['tenantId'], $kasir));
        $this->assertThrows(ForbiddenException::class, fn () => $this->roleService->syncPermissions($actor, $kasir, ['branches.view', 'users.manage']));
        $this->assertThrows(ForbiddenException::class, fn () => $this->roleService->createCustom($actor, 'Terlalu kuat', ['audit_logs.view']));
        $this->assertSame(['branches.view'], $this->codes($t['tenantId'], $kasir));
        $this->assertSame(0, $this->count("SELECT COUNT(*) AS n FROM roles WHERE tenant_id = ? AND slug = 'terlalu-kuat'", [$t['tenantId']]));

        // Izin yang tidak dimiliki aktor tapi sudah ada di role tetap dipertahankan (aktor tidak bisa mencabutnya).
        $this->roleService->syncPermissions($t['owner'], $kasir, ['branches.view', 'users.manage']);
        $this->roleService->syncPermissions($actor, $kasir, []);
        $this->assertSame(['users.manage'], $this->codes($t['tenantId'], $kasir));
        $meta = $this->lastAudit('role.permissions_synced');
        $this->assertSame(['branches.view'], $meta['removed']);

        $ok = $this->roleService->createCustom($actor, 'Peninjau cabang', ['branches.view']);
        $this->assertSame(['branches.view'], $this->codes($t['tenantId'], $ok));
    }

    public function testRoleFromOtherTenantReturns404(): void
    {
        $a = $this->tenant('a@example.com');
        $b = $this->tenant('b@example.com');
        $foreign = $this->roleId($b['tenantId'], 'kasir');
        $custom = $this->roleService->createCustom($b['owner'], 'Rahasia B', ['branches.view']);
        foreach ([$foreign, $custom] as $id) {
            $this->assertThrows(NotFoundException::class, fn () => $this->roleService->get($a['owner'], $id));
            $this->assertThrows(NotFoundException::class, fn () => $this->roleService->syncPermissions($a['owner'], $id, ['branches.view']));
            $this->assertThrows(NotFoundException::class, fn () => $this->roleService->rename($a['owner'], $id, 'Diretas'));
            $this->assertThrows(NotFoundException::class, fn () => $this->roleService->delete($a['owner'], $id));
        }
        $this->assertSame(['branches.view'], $this->codes($b['tenantId'], $custom));
        $this->assertSame('Rahasia B', $this->db->fetchOne('SELECT name FROM roles WHERE id = ?', [$custom])['name']);
        $this->assertSame(8, $this->count('SELECT COUNT(*) AS n FROM audit_logs WHERE action = ? AND tenant_id = ? AND entity_type = ?', ['security.entity_not_found', $a['tenantId'], 'role']));
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM audit_logs WHERE action = ? AND tenant_id = ?', ['security.entity_not_found', $b['tenantId']]));

        $this->assertSame(302, $this->login('a@example.com')->status());
        $this->assertSame(404, $this->status('GET', "/role/$custom"));
        $this->assertSame(404, $this->status('GET', '/role/987654'));
        $this->assertSame(404, $this->status('POST', "/role/$custom/izin", ['codes' => ['branches.view']]));
        $this->assertSame(404, $this->status('POST', "/role/$custom/hapus"));
        $this->assertSame(404, $this->status('POST', "/role/$custom/nama", ['name' => 'x']));
        $names = array_column($this->roleService->list($a['owner']), 'name');
        $this->assertFalse(in_array('Rahasia B', $names, true));
    }

    public function testPermissionChangesAreAudited(): void
    {
        $t = $this->tenant();
        $id = $this->roleService->createCustom($t['owner'], 'Admin toko', ['branches.view', 'users.view']);
        $created = $this->lastAudit('role.created');
        $this->assertSame('Admin toko', $created['name']);
        $this->assertSame('admin-toko', $created['slug']);
        $this->assertSame(['branches.view', 'users.view'], $created['permissions']);

        $this->roleService->syncPermissions($t['owner'], $id, ['users.view', 'roles.view', 'branches.manage']);
        $this->assertSame(1, $this->auditCount('role.permissions_synced'));
        $meta = $this->lastAudit('role.permissions_synced');
        $this->assertSame(['branches.manage', 'roles.view'], $meta['added']);
        $this->assertSame(['branches.view'], $meta['removed']);
        $row = $this->db->fetchOne("SELECT actor_id, entity_type, entity_id, tenant_id FROM audit_logs WHERE action = 'role.permissions_synced'");
        $this->assertSame([$t['ownerId'], 'role', $id, $t['tenantId']], [(int) $row['actor_id'], $row['entity_type'], (int) $row['entity_id'], (int) $row['tenant_id']]);

        $this->roleService->syncPermissions($t['owner'], $id, ['roles.view', 'users.view', 'branches.manage']);
        $this->assertSame(1, $this->auditCount('role.permissions_synced'), 'tanpa perubahan tanpa audit');
        $this->assertSame(['branches.manage', 'roles.view', 'users.view'], $this->codes($t['tenantId'], $id));
    }

    public function testCreateCustomRoleSlugIsUniquePerTenantAndNotSystem(): void
    {
        $a = $this->tenant('a@example.com');
        $b = $this->tenant('b@example.com');
        $id = $this->roleService->createCustom($a['owner'], '  Staf Toko 2 ');
        $row = $this->db->fetchOne('SELECT * FROM roles WHERE id = ?', [$id]);
        $this->assertSame(['staf-toko-2', 'Staf Toko 2', 0, $a['tenantId']], [$row['slug'], $row['name'], (int) $row['is_system'], (int) $row['tenant_id']]);
        foreach (['Staf Toko 2', 'staf   toko-2', 'Kasir', 'OWNER', 'Owner Tenant!'] as $dup) {
            if ($dup === 'Owner Tenant!') {
                $this->roleService->createCustom($a['owner'], $dup);
                continue;
            }
            try {
                $this->roleService->createCustom($a['owner'], $dup);
                $this->fail("Nama/slug duplikat harus ditolak: $dup");
            } catch (ValidationException $e) {
                $this->assertTrue(isset($e->errors()['name']), $dup);
            }
        }
        $this->roleService->createCustom($b['owner'], 'Staf Toko 2');
        foreach (['', '   ', '!!!', str_repeat('a', 101), "a\0b"] as $bad) {
            $this->assertThrows(ValidationException::class, fn () => $this->roleService->createCustom($a['owner'], $bad));
        }
        $this->assertThrows(ValidationException::class, fn () => $this->roleService->createCustom($a['owner'], 'Kode ngawur', ['bukan.izin']));
    }

    public function testCreateCustomRaceOnUniqueSlugIsValidationError(): void
    {
        $t = $this->tenant();
        $blind = new class ($this->db) extends \App\Modules\Tenants\Repositories\RoleRepository {
            public function slugExists(int $tenantId, string $slug): bool
            {
                return false;
            }
        };
        $service = new \App\Modules\Roles\Services\RoleService($this->db, $blind, new \App\Modules\Roles\Repositories\PermissionRepository($this->db), $this->policy, $this->makeAudit(), new \App\Core\Authorization\AccessDeniedRecorder($this->makeAudit()));
        $this->assertThrows(ValidationException::class, fn () => $service->createCustom($t['owner'], 'Kasir'));
        $this->assertSame(0, $this->auditCount('role.created'));
    }

    public function testSystemRolesCannotBeDeletedOrRenamed(): void
    {
        $t = $this->tenant();
        foreach (['owner', 'kasir', 'akuntan'] as $slug) {
            $id = $this->roleId($t['tenantId'], $slug);
            $this->assertThrows(ValidationException::class, fn () => $this->roleService->rename($t['owner'], $id, 'Nama Lain'));
            $this->assertThrows(ValidationException::class, fn () => $this->roleService->delete($t['owner'], $id));
            $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM roles WHERE id = ? AND slug = ? AND is_system = 1', [$id, $slug]));
        }
        $this->assertSame('Kasir', $this->db->fetchOne('SELECT name FROM roles WHERE tenant_id = ? AND slug = ?', [$t['tenantId'], 'kasir'])['name']);
        $this->assertSame(0, $this->auditCount('role.renamed') + $this->auditCount('role.deleted'));
        // SQL juga menolak walau service dilewati.
        $this->assertFalse($this->roleRepo->delete($t['tenantId'], $this->roleId($t['tenantId'], 'kasir')));
        $this->assertFalse($this->roleRepo->rename($t['tenantId'], $this->roleId($t['tenantId'], 'kasir'), 'Lain'));
    }

    public function testCustomRoleRenameAndDelete(): void
    {
        $t = $this->tenant();
        $id = $this->roleService->createCustom($t['owner'], 'Sementara', ['branches.view']);
        $this->roleService->rename($t['owner'], $id, 'Sementara Baru');
        $this->assertSame('Sementara Baru', $this->db->fetchOne('SELECT name FROM roles WHERE id = ?', [$id])['name']);
        $this->assertSame('sementara', $this->db->fetchOne('SELECT slug FROM roles WHERE id = ?', [$id])['slug'], 'slug stabil');
        $this->assertThrows(ValidationException::class, fn () => $this->roleService->rename($t['owner'], $id, 'Kasir'), 'nama menabrak role sistem');
        $this->assertThrows(ValidationException::class, fn () => $this->roleService->rename($t['owner'], $id, ''));
        $this->assertSame(1, $this->auditCount('role.renamed'));

        $staff = $this->makeUser($t['tenantId'], 'staf@example.com', []);
        $this->db->execute('INSERT INTO user_roles (user_id, role_id, tenant_id) VALUES (?, ?, ?)', [$staff, $id, $t['tenantId']]);
        $this->assertThrows(ValidationException::class, fn () => $this->roleService->delete($t['owner'], $id));
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM roles WHERE id = ?', [$id]));
        $this->db->execute('DELETE FROM user_roles WHERE user_id = ? AND role_id = ?', [$staff, $id]);
        $this->roleService->delete($t['owner'], $id);
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM roles WHERE id = ?', [$id]));
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM role_permissions WHERE role_id = ?', [$id]));
        $this->assertSame(1, $this->auditCount('role.deleted'));
    }

    public function testUnknownPermissionCodeIsRejectedWithoutPartialChange(): void
    {
        $t = $this->tenant();
        $id = $this->roleService->createCustom($t['owner'], 'Uji', ['branches.view']);
        foreach ([['branches.view', 'tidak.ada'], ['BRANCHES.VIEW'], [''], ["branches.view\0"]] as $codes) {
            $this->assertThrows(ValidationException::class, fn () => $this->roleService->syncPermissions($t['owner'], $id, $codes));
        }
        $this->assertSame(['branches.view'], $this->codes($t['tenantId'], $id));
        $this->roleService->syncPermissions($t['owner'], $id, ['branches.view', 'branches.view']);
        $this->assertSame(['branches.view'], $this->codes($t['tenantId'], $id));
    }

    public function testRolePermissionChangeTakesEffectForAssignedUsers(): void
    {
        $t = $this->tenant();
        $staff = $this->makeUser($t['tenantId'], 'staf@example.com', []);
        $role = $this->roleService->createCustom($t['owner'], 'Pelihat', []);
        $this->userService->assignRoles($t['owner'], $staff, [$role]);
        $this->send('POST', '/keluar');
        $this->assertSame(302, $this->login('staf@example.com')->status());
        $this->assertSame(403, $this->status('GET', '/cabang'));
        $this->roleService->syncPermissions($t['owner'], $role, ['branches.view']);
        $this->assertSame(200, $this->status('GET', '/cabang'));
    }

    public function testMissingPermissionReturns403FullStack(): void
    {
        $t = $this->tenant();
        $this->makeUser($t['tenantId'], 'lihat@example.com', ['roles.view']);
        $this->makeUser($t['tenantId'], 'kosong@example.com', []);
        $kasir = $this->roleId($t['tenantId'], 'kasir');
        $this->send('POST', '/keluar');
        $this->assertSame(302, $this->login('lihat@example.com')->status());
        $this->assertSame(200, $this->status('GET', '/role'));
        $this->assertSame(200, $this->status('GET', "/role/$kasir"));
        foreach ([['GET', '/role/baru'], ['POST', '/role'], ['POST', "/role/$kasir/izin"], ['POST', "/role/$kasir/nama"], ['POST', "/role/$kasir/hapus"]] as [$m, $u]) {
            $this->assertSame(403, $this->status($m, $u, ['name' => 'x', 'codes' => ['branches.view']]), "$m $u");
        }
        $this->assertSame([], $this->codes($t['tenantId'], $kasir));
        $this->send('POST', '/keluar');
        $this->assertSame(302, $this->login('kosong@example.com')->status());
        $this->assertSame(403, $this->status('GET', '/role'));
        $this->assertSame(403, $this->status('GET', "/role/$kasir"));
    }

    public function testManageRolesThroughHttp(): void
    {
        $t = $this->tenant();
        $this->assertSame(302, $this->login('budi@example.com')->status());
        $this->assertSame(200, $this->send('GET', '/role')->status());
        $this->assertSame(200, $this->send('GET', '/role/baru')->status());
        $bad = $this->send('POST', '/role', ['name' => '']);
        $this->assertSame(422, $bad->status());
        $this->assertContains('role="alert"', $bad->body());
        $made = $this->send('POST', '/role', ['name' => '<i>Gudang</i>', 'codes' => ['branches.view']]);
        $this->assertSame(302, $made->status());
        $id = (int) $this->db->fetchOne("SELECT id FROM roles WHERE tenant_id = ? AND is_system = 0", [$t['tenantId']])['id'];
        $this->assertSame('/role/' . $id, $made->header('Location'));
        $show = $this->send('GET', "/role/$id");
        $this->assertSame(200, $show->status());
        $this->assertFalse(str_contains($show->body(), '<i>Gudang</i>'));
        $this->assertContains('&lt;i&gt;Gudang&lt;/i&gt;', $show->body());
        $this->assertContains('branches.view', $show->body());

        $this->assertSame(302, $this->send('POST', "/role/$id/izin", ['codes' => ['users.view'], 'tenant_id' => '9'])->status());
        $this->assertSame(['users.view'], $this->codes($t['tenantId'], $id));
        $this->assertSame(302, $this->send('POST', "/role/$id/nama", ['name' => 'Gudang Utama'])->status());
        $this->assertSame(302, $this->send('POST', "/role/$id/hapus")->status());
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM roles WHERE id = ?', [$id]));
        $sys = $this->roleId($t['tenantId'], 'kasir');
        $this->assertSame(302, $this->send('POST', "/role/$sys/hapus")->status(), 'ditolak dengan pesan, bukan error');
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM roles WHERE id = ?', [$sys]));
    }
}
