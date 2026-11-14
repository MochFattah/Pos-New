<?php

declare(strict_types=1);

namespace App\Modules\Roles\Repositories;

use App\Core\Database\Connection;

/** Katalog permission (tabel global) dan penulisan role_permissions (selalu dengan tenant_id). */
class PermissionRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** @return list<array{code: string, module: string, description: string}> */
    public function catalog(): array
    {
        $rows = $this->db->query('SELECT code, module, description FROM permissions ORDER BY module, code');
        return array_map(static fn (array $r): array => [
            'code' => (string) $r['code'],
            'module' => (string) $r['module'],
            'description' => (string) $r['description'],
        ], $rows);
    }

    /** @return list<string> */
    public function allCodes(): array
    {
        return array_map(static fn (array $r): string => (string) $r['code'], $this->db->query('SELECT code FROM permissions ORDER BY code'));
    }

    public function grant(int $tenantId, int $roleId, string $code): void
    {
        $this->db->execute(
            'INSERT INTO role_permissions (role_id, permission_id, tenant_id)
             SELECT ?, id, ? FROM permissions WHERE code = ?',
            [$roleId, $tenantId, $code]
        );
    }

    public function revoke(int $tenantId, int $roleId, string $code): void
    {
        $this->db->execute(
            'DELETE FROM role_permissions WHERE tenant_id = ? AND role_id = ? AND permission_id IN (SELECT id FROM permissions WHERE code = ?)',
            [$tenantId, $roleId, $code]
        );
    }
}
