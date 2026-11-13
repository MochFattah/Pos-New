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

    /** @var array<string, bool> cache per instance: kode => ada di tabel permissions */
    private array $known = [];

    public function __construct(private readonly Connection $db, private readonly ?AccessDeniedRecorder $denied = null)
    {
    }

    /** Kode harus ada di katalog permissions; typo pada rute menolak semua orang, termasuk Owner. */
    private function isKnown(string $permission): bool
    {
        return $this->known[$permission] ??= $this->db->fetchOne('SELECT 1 AS ok FROM permissions WHERE code = ?', [$permission]) !== null;
    }

    public function can(AuthUser $user, string $permission, ?int $branchId = null): bool
    {
        if (preg_match(self::PERMISSION_PATTERN, $permission) !== 1) {
            return false;
        }
        if ($user->isOwner ? !$this->isKnown($permission) : !in_array($permission, $user->permissions, true)) {
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
            $this->denied?->permissionDenied($user, $permission, $branchId);
            throw new ForbiddenException();
        }
    }
}
