<?php

declare(strict_types=1);

namespace App\Core\Authorization;

use App\Core\Database\Connection;
use App\Core\Exceptions\ForbiddenException;

/**
 * Pemeriksaan otorisasi di server: Owner memiliki seluruh permission tenant-nya; pengguna lain
 * harus memegang permission lewat role. Bila cabang disebut, cabang itu harus milik tenant pengguna
 * dan (untuk non-Owner) tercantum di user_branch_access. Daftar permission di AuthUser dimuat ulang
 * dari DB pada setiap request oleh AuthService, jadi tidak ada cache lintas request.
 */
class PolicyService
{
    private const PERMISSION_PATTERN = '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*\z/';

    public function __construct(private readonly Connection $db)
    {
    }

    public function can(AuthUser $user, string $permission, ?int $branchId = null): bool
    {
        if (preg_match(self::PERMISSION_PATTERN, $permission) !== 1) {
            return false;
        }
        if (!$user->isOwner && !in_array($permission, $user->permissions, true)) {
            return false;
        }
        if ($branchId === null) {
            return true;
        }
        if ($user->isOwner) {
            return $this->db->fetchOne(
                'SELECT 1 AS ok FROM branches WHERE tenant_id = ? AND id = ?',
                [$user->tenantId, $branchId]
            ) !== null;
        }
        return $this->db->fetchOne(
            'SELECT 1 AS ok FROM user_branch_access WHERE tenant_id = ? AND user_id = ? AND branch_id = ?',
            [$user->tenantId, $user->id, $branchId]
        ) !== null;
    }

    /** @throws ForbiddenException */
    public function authorize(AuthUser $user, string $permission, ?int $branchId = null): void
    {
        if (!$this->can($user, $permission, $branchId)) {
            throw new ForbiddenException();
        }
    }
}
