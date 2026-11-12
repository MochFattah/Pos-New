<?php

declare(strict_types=1);

namespace App\Modules\Branches\Repositories;

use App\Core\Database\Connection;
use App\Modules\Branches\Exceptions\DuplicateBranchCodeException;
use PDOException;

class BranchRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM branches WHERE tenant_id = ? AND id = ?', [$tenantId, $id]);
    }

    /** @return list<array<string, mixed>> */
    public function listByTenant(int $tenantId): array
    {
        return $this->db->query(
            'SELECT * FROM branches WHERE tenant_id = ? ORDER BY is_main DESC, name ASC, id ASC',
            [$tenantId]
        );
    }

    public function codeExists(int $tenantId, string $code, ?int $exceptId = null): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM branches WHERE tenant_id = ? AND code = ? AND id <> ?',
            [$tenantId, $code, $exceptId ?? 0]
        ) !== null;
    }

    /** @throws DuplicateBranchCodeException */
    public function create(int $tenantId, string $code, string $name, ?string $address, ?string $phone, string $now): int
    {
        try {
            $this->db->execute(
                'INSERT INTO branches (tenant_id, code, name, address, phone, is_main, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)',
                [$tenantId, $code, $name, $address, $phone, 'active', $now, $now]
            );
        } catch (PDOException $e) {
            throw self::isUnique($e) ? new DuplicateBranchCodeException() : $e;
        }
        return $this->db->lastInsertId();
    }

    /** @throws DuplicateBranchCodeException */
    public function update(int $tenantId, int $id, string $code, string $name, ?string $address, ?string $phone, string $now): bool
    {
        try {
            return $this->db->execute(
                'UPDATE branches SET code = ?, name = ?, address = ?, phone = ?, updated_at = ? WHERE tenant_id = ? AND id = ?',
                [$code, $name, $address, $phone, $now, $tenantId, $id]
            ) === 1;
        } catch (PDOException $e) {
            throw self::isUnique($e) ? new DuplicateBranchCodeException() : $e;
        }
    }

    /** Cabang utama tidak pernah ikut berubah status di level SQL (lapis kedua selain pemeriksaan service). */
    public function setStatus(int $tenantId, int $id, string $status, string $now): bool
    {
        return $this->db->execute(
            'UPDATE branches SET status = ?, updated_at = ? WHERE tenant_id = ? AND id = ? AND is_main = 0 AND status <> ?',
            [$status, $now, $tenantId, $id, $status]
        ) === 1;
    }

    private static function isUnique(PDOException $e): bool
    {
        $state = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        return $state === '23000' && in_array($driverCode, [1062, 19, 2067], true);
    }
}
