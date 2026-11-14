<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Repositories;

use App\Core\Database\Connection;
use App\Modules\Tenants\Exceptions\DuplicateRoleSlugException;
use PDOException;

class RoleRepository
{
    /** Role sistem bawaan (PRD 4.3): slug => nama. Dibuat tanpa izin; Owner diperlakukan khusus lewat is_owner. */
    public const SYSTEM_ROLES = [
        'owner' => 'Owner',
        'manager' => 'Manager',
        'supervisor' => 'Supervisor',
        'kasir' => 'Kasir',
        'staf-gudang' => 'Staf Gudang',
        'staf-pembelian' => 'Staf Pembelian',
        'staf-keuangan' => 'Staf Keuangan',
        'akuntan' => 'Akuntan',
    ];

    public function __construct(private readonly Connection $db)
    {
    }

    /** @return array<string, int> slug => id */
    public function createSystemRoles(int $tenantId): array
    {
        $ids = [];
        foreach (self::SYSTEM_ROLES as $slug => $name) {
            $this->db->execute(
                'INSERT INTO roles (tenant_id, slug, name, is_system) VALUES (?, ?, ?, 1)',
                [$tenantId, $slug, $name]
            );
            $ids[$slug] = $this->db->lastInsertId();
        }
        return $ids;
    }

    public function assign(int $tenantId, int $userId, int $roleId): void
    {
        $this->db->execute(
            'INSERT INTO user_roles (user_id, role_id, tenant_id) VALUES (?, ?, ?)',
            [$userId, $roleId, $tenantId]
        );
    }

    public const OWNER_SLUG = 'owner';

    /** Role Owner bawaan: tidak punya baris izin (Owner dievaluasi lewat is_owner) dan tidak bisa diubah/dihapus. @param array<string, mixed> $role */
    public static function isOwnerRole(array $role): bool
    {
        return (string) $role['slug'] === self::OWNER_SLUG && (int) $role['is_system'] === 1;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $roleId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM roles WHERE tenant_id = ? AND id = ?', [$tenantId, $roleId]);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(int $tenantId, string $slug): ?array
    {
        return $this->db->fetchOne('SELECT * FROM roles WHERE tenant_id = ? AND slug = ?', [$tenantId, $slug]);
    }

    public function slugExists(int $tenantId, string $slug): bool
    {
        return $this->findBySlug($tenantId, $slug) !== null;
    }

    /** Nama sama (tanpa membedakan huruf besar/kecil) dengan role lain di tenant yang sama. */
    public function nameTaken(int $tenantId, string $name, int $exceptRoleId): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM roles WHERE tenant_id = ? AND LOWER(name) = ? AND id <> ?',
            [$tenantId, mb_strtolower($name), $exceptRoleId]
        ) !== null;
    }

    /** @return list<array<string, mixed>> role beserta jumlah izin dan pengguna */
    public function listByTenant(int $tenantId): array
    {
        return $this->db->query(
            'SELECT r.id, r.slug, r.name, r.is_system,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.tenant_id = r.tenant_id AND rp.role_id = r.id) AS permission_count,
                    (SELECT COUNT(*) FROM user_roles ur WHERE ur.tenant_id = r.tenant_id AND ur.role_id = r.id) AS user_count
             FROM roles r WHERE r.tenant_id = ? ORDER BY r.is_system DESC, r.name ASC, r.id ASC',
            [$tenantId]
        );
    }

    /** @throws DuplicateRoleSlugException */
    public function create(int $tenantId, string $slug, string $name): int
    {
        try {
            $this->db->execute(
                'INSERT INTO roles (tenant_id, slug, name, is_system) VALUES (?, ?, ?, 0)',
                [$tenantId, $slug, $name]
            );
        } catch (PDOException $e) {
            $state = (string) ($e->errorInfo[0] ?? $e->getCode());
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($state === '23000' && in_array($driverCode, [1062, 19, 2067], true) && stripos($e->getMessage(), 'slug') !== false) {
                throw new DuplicateRoleSlugException();
            }
            throw $e;
        }
        return $this->db->lastInsertId();
    }

    /** Hanya role non-sistem. */
    public function rename(int $tenantId, int $roleId, string $name): bool
    {
        return $this->db->execute(
            'UPDATE roles SET name = ? WHERE tenant_id = ? AND id = ? AND is_system = 0',
            [$name, $tenantId, $roleId]
        ) === 1;
    }

    /** Hanya role non-sistem; izinnya ikut dihapus. Pemanggil memastikan tidak ada pengguna yang memegangnya. */
    public function delete(int $tenantId, int $roleId): bool
    {
        return (bool) $this->db->transaction(function () use ($tenantId, $roleId): bool {
            $role = $this->find($tenantId, $roleId);
            if ($role === null || (int) $role['is_system'] === 1) {
                return false;
            }
            $this->db->execute('DELETE FROM role_permissions WHERE tenant_id = ? AND role_id = ?', [$tenantId, $roleId]);
            return $this->db->execute('DELETE FROM roles WHERE tenant_id = ? AND id = ? AND is_system = 0', [$tenantId, $roleId]) === 1;
        });
    }

    public function userCount(int $tenantId, int $roleId): int
    {
        return (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM user_roles WHERE tenant_id = ? AND role_id = ?',
            [$tenantId, $roleId]
        )['n'] ?? 0);
    }

    /** @return list<string> kode izin role, terurut */
    public function permissionCodes(int $tenantId, int $roleId): array
    {
        $rows = $this->db->query(
            'SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.tenant_id = ? AND rp.role_id = ? ORDER BY p.code',
            [$tenantId, $roleId]
        );
        return array_map(static fn (array $r): string => (string) $r['code'], $rows);
    }

    /** @return list<int> */
    public function roleIdsForUser(int $tenantId, int $userId): array
    {
        $rows = $this->db->query(
            'SELECT role_id FROM user_roles WHERE tenant_id = ? AND user_id = ? ORDER BY role_id',
            [$tenantId, $userId]
        );
        return array_map(static fn (array $r): int => (int) $r['role_id'], $rows);
    }

    public function unassign(int $tenantId, int $userId, int $roleId): void
    {
        $this->db->execute(
            'DELETE FROM user_roles WHERE tenant_id = ? AND user_id = ? AND role_id = ?',
            [$tenantId, $userId, $roleId]
        );
    }

    /** @return array<int, list<string>> userId => nama role */
    public function roleNamesByUser(int $tenantId): array
    {
        $out = [];
        $rows = $this->db->query(
            'SELECT ur.user_id, r.name FROM user_roles ur JOIN roles r ON r.tenant_id = ur.tenant_id AND r.id = ur.role_id
             WHERE ur.tenant_id = ? ORDER BY r.name',
            [$tenantId]
        );
        foreach ($rows as $row) {
            $out[(int) $row['user_id']][] = (string) $row['name'];
        }
        return $out;
    }
}
