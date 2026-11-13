<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Authorization\AccessDeniedRecorder;
use App\Core\Authorization\AuthUser;
use App\Core\Authorization\PolicyService;
use App\Core\Middleware\PermissionMiddleware;
use App\Modules\Branches\Controllers\BranchController;
use App\Modules\Branches\Repositories\BranchRepository;
use App\Modules\Branches\Services\BranchService;

/** AuthHarness + layanan cabang/otorisasi dan pembuat pengguna non-Owner dengan permission dan akses cabang tertentu. */
abstract class BranchHarness extends AuthHarness
{
    protected PolicyService $policy;
    protected BranchRepository $branchRepo;
    protected BranchService $branchService;

    protected function extraServices(): array
    {
        $denied = new AccessDeniedRecorder($this->makeAudit());
        $this->policy = new PolicyService($this->db, $denied);
        $this->branchRepo = new BranchRepository($this->db);
        $this->branchService = new BranchService($this->db, $this->branchRepo, $this->policy, $this->makeAudit(), $denied, fn (): int => $this->now);
        PermissionMiddleware::provide(fn (): PolicyService => $this->policy);
        return [BranchController::class => new BranchController($this->branchService, $this->policy)];
    }

    protected function tearDown(): void
    {
        PermissionMiddleware::provide(null);
        parent::tearDown();
    }

    /** AuthUser Owner dari sesi yang sedang masuk (lewat AuthService, seperti AuthMiddleware). */
    protected function currentUser(): AuthUser
    {
        $user = $this->auth->userFromSession($this->session);
        $this->assertTrue($user instanceof AuthUser, 'Harus sudah masuk');
        return $user;
    }

    /** Memuat AuthUser untuk pengguna tertentu tanpa login (membangun dari DB yang sama dengan AuthService). */
    protected function authUserFor(int $tenantId, int $userId): AuthUser
    {
        $row = $this->db->fetchOne('SELECT * FROM users WHERE tenant_id = ? AND id = ?', [$tenantId, $userId]);
        $roles = array_map(static fn (array $r): int => (int) $r['role_id'], $this->db->query('SELECT role_id FROM user_roles WHERE tenant_id = ? AND user_id = ?', [$tenantId, $userId]));
        $perms = array_map('strval', array_column($this->db->query(
            'SELECT DISTINCT p.code FROM user_roles ur JOIN role_permissions rp ON rp.tenant_id = ur.tenant_id AND rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id WHERE ur.tenant_id = ? AND ur.user_id = ?',
            [$tenantId, $userId]
        ), 'code'));
        return new AuthUser($userId, $tenantId, null, (string) $row['name'], (string) $row['email'], (int) $row['is_owner'] === 1, $roles, $perms);
    }

    /**
     * Pengguna aktif non-Owner dengan satu role kustom berisi $permissions dan akses ke $branchIds.
     *
     * @param list<string> $permissions
     * @param list<int> $branchIds
     */
    protected function makeUser(int $tenantId, string $email, array $permissions, array $branchIds = []): int
    {
        $now = '2027-01-01 00:00:00';
        $this->db->execute(
            "INSERT INTO users (tenant_id, name, email, password_hash, status, email_verified_at, is_owner, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'active', ?, 0, ?, ?)",
            [$tenantId, 'Staf ' . $email, $email, $this->hasher->hash(self::PASSWORD), $now, $now, $now]
        );
        $userId = $this->db->lastInsertId();
        $this->db->execute('INSERT INTO roles (tenant_id, slug, name, is_system) VALUES (?, ?, ?, 0)', [$tenantId, 'r-' . $userId, 'Role ' . $userId]);
        $roleId = $this->db->lastInsertId();
        foreach ($permissions as $code) {
            $perm = $this->db->fetchOne('SELECT id FROM permissions WHERE code = ?', [$code]);
            $this->assertTrue($perm !== null, "Permission $code tidak ada di seeder");
            $this->db->execute('INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)', [$roleId, $perm['id'], $tenantId]);
        }
        $this->db->execute('INSERT INTO user_roles (user_id, role_id, tenant_id) VALUES (?, ?, ?)', [$userId, $roleId, $tenantId]);
        foreach ($branchIds as $branchId) {
            $this->db->execute('INSERT INTO user_branch_access (user_id, branch_id, tenant_id) VALUES (?, ?, ?)', [$userId, $branchId, $tenantId]);
        }
        return $userId;
    }

    protected function mainBranchId(int $tenantId): int
    {
        return (int) $this->db->fetchOne('SELECT id FROM branches WHERE tenant_id = ? AND is_main = 1', [$tenantId])['id'];
    }

    /** Cabang non-utama langsung ke DB (melewati service) untuk menyiapkan data. */
    protected function seedBranch(int $tenantId, string $code, string $name = 'Cabang'): int
    {
        $now = '2027-01-01 00:00:00';
        $this->db->execute(
            "INSERT INTO branches (tenant_id, code, name, is_main, status, created_at, updated_at) VALUES (?, ?, ?, 0, 'active', ?, ?)",
            [$tenantId, $code, $name, $now, $now]
        );
        return $this->db->lastInsertId();
    }
}
