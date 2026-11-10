<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Repositories;

use App\Core\Database\Connection;

final class RoleRepository
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
}
