<?php

declare(strict_types=1);

namespace App\Core\Authorization;

/** Konteks pengguna yang sedang masuk; dibangun server dari sesi, tidak pernah dari input request. */
final class AuthUser
{
    /**
     * @param list<int> $roleIds
     * @param list<string> $permissions kode permission yang diberikan lewat role (Owner dievaluasi oleh PolicyService, bukan daftar ini)
     */
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly ?int $branchId,
        public readonly string $name,
        public readonly string $email,
        public readonly bool $isOwner,
        public readonly array $roleIds = [],
        public readonly array $permissions = [],
    ) {
    }
}
