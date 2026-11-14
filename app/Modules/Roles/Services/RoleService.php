<?php

declare(strict_types=1);

namespace App\Modules\Roles\Services;

use App\Core\Authorization\AccessDeniedRecorder;
use App\Core\Authorization\AuthUser;
use App\Core\Authorization\PolicyService;
use App\Core\Database\Connection;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Roles\Repositories\PermissionRepository;
use App\Modules\Tenants\Exceptions\DuplicateRoleSlugException;
use App\Modules\Tenants\Repositories\RoleRepository;

/**
 * Manajemen role dan izin per tenant. Izin Owner tidak disimpan (Owner dievaluasi lewat is_owner) dan role
 * Owner tidak bisa diubah. Aktor hanya boleh menambah izin yang dimilikinya; izin yang sudah ada di role
 * tetapi tidak dimiliki aktor dipertahankan (tidak bisa dicabut). Role sistem tidak bisa dihapus/diganti nama.
 * Slug role kustom dibuat dari nama (a-z, 0-9, strip), unik per tenant, dan stabil setelah ganti nama.
 */
final class RoleService
{
    public const VIEW = 'roles.view';
    public const MANAGE = 'roles.manage';

    private const SLUG_MAX = 60;
    private const NAME_MAX = 100;

    public function __construct(
        private readonly Connection $db,
        private readonly RoleRepository $roles,
        private readonly PermissionRepository $permissions,
        private readonly PolicyService $policy,
        private readonly AuditLogger $audit,
        private readonly AccessDeniedRecorder $denied,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(AuthUser $actor): array
    {
        $this->policy->authorize($actor, self::VIEW);
        return $this->roles->listByTenant($actor->tenantId);
    }

    /** @return array<string, mixed> role beserta 'permissions' (kode) */
    public function get(AuthUser $actor, int $id): array
    {
        $this->policy->authorize($actor, self::VIEW);
        $role = $this->findOrFail($actor, $id);
        $role['permissions'] = $this->roles->permissionCodes($actor->tenantId, $id);
        $role['user_count'] = $this->roles->userCount($actor->tenantId, $id);
        return $role;
    }

    /** @return list<array{code: string, module: string, description: string, grantable: bool}> */
    public function catalog(AuthUser $actor): array
    {
        $this->policy->authorize($actor, self::VIEW);
        $out = [];
        foreach ($this->permissions->catalog() as $item) {
            $item['grantable'] = $this->policy->can($actor, $item['code']);
            $out[] = $item;
        }
        return $out;
    }

    /**
     * @param list<mixed> $permissionCodes
     * @throws ValidationException|ForbiddenException
     */
    public function createCustom(AuthUser $actor, string $name, array $permissionCodes = []): int
    {
        $this->policy->authorize($actor, self::MANAGE);
        [$name, $slug] = $this->validName($name);
        if ($this->roles->slugExists($actor->tenantId, $slug)) {
            throw new ValidationException(['name' => 'Nama role sudah dipakai.']);
        }
        $codes = $this->validCodes($permissionCodes);
        $this->requireGrantable($actor, $codes);
        try {
            return $this->db->transaction(function () use ($actor, $name, $slug, $codes): int {
                $id = $this->roles->create($actor->tenantId, $slug, $name);
                foreach ($codes as $code) {
                    $this->permissions->grant($actor->tenantId, $id, $code);
                }
                $this->log('role.created', $actor, $id, ['name' => $name, 'slug' => $slug, 'permissions' => $codes]);
                return $id;
            });
        } catch (DuplicateRoleSlugException) {
            throw new ValidationException(['name' => 'Nama role sudah dipakai.']);
        }
    }

    /**
     * @param list<mixed> $permissionCodes
     * @throws ValidationException|ForbiddenException|NotFoundException
     */
    public function syncPermissions(AuthUser $actor, int $roleId, array $permissionCodes): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $role = $this->findOrFail($actor, $roleId);
        if (RoleRepository::isOwnerRole($role)) {
            throw new ValidationException(['permissions' => 'Izin role Owner tidak dapat diubah.']);
        }
        $wanted = $this->validCodes($permissionCodes);
        $current = $this->roles->permissionCodes($actor->tenantId, $roleId);
        $added = array_values(array_diff($wanted, $current));
        $this->requireGrantable($actor, $added);
        $removed = array_values(array_filter(
            array_diff($current, $wanted),
            fn (string $code): bool => $this->policy->can($actor, $code)
        ));
        if ($added === [] && $removed === []) {
            return;
        }
        sort($added);
        sort($removed);
        $this->db->transaction(function () use ($actor, $roleId, $role, $added, $removed): void {
            foreach ($removed as $code) {
                $this->permissions->revoke($actor->tenantId, $roleId, $code);
            }
            foreach ($added as $code) {
                $this->permissions->grant($actor->tenantId, $roleId, $code);
            }
            $this->log('role.permissions_synced', $actor, $roleId, ['role' => (string) $role['slug'], 'added' => $added, 'removed' => $removed]);
        });
    }

    /** @throws ValidationException|NotFoundException */
    public function rename(AuthUser $actor, int $roleId, string $name): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $role = $this->findOrFail($actor, $roleId);
        if ((int) $role['is_system'] === 1) {
            throw new ValidationException(['name' => 'Role sistem tidak dapat diganti namanya.']);
        }
        [$name, $slug] = $this->validName($name);
        $sameSlug = $this->roles->findBySlug($actor->tenantId, $slug);
        if (($sameSlug !== null && (int) $sameSlug['id'] !== $roleId) || $this->roles->nameTaken($actor->tenantId, $name, $roleId)) {
            throw new ValidationException(['name' => 'Nama role sudah dipakai.']);
        }
        if ((string) $role['name'] === $name) {
            return;
        }
        $from = (string) $role['name'];
        $this->db->transaction(function () use ($actor, $roleId, $name, $from): void {
            if ($this->roles->rename($actor->tenantId, $roleId, $name)) {
                $this->log('role.renamed', $actor, $roleId, ['from' => $from, 'to' => $name]);
            }
        });
    }

    /** @throws ValidationException|NotFoundException */
    public function delete(AuthUser $actor, int $roleId): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $role = $this->findOrFail($actor, $roleId);
        if ((int) $role['is_system'] === 1) {
            throw new ValidationException(['role' => 'Role sistem tidak dapat dihapus.']);
        }
        if ($this->roles->userCount($actor->tenantId, $roleId) > 0) {
            throw new ValidationException(['role' => 'Role masih dipakai pengguna. Lepaskan dari pengguna terlebih dahulu.']);
        }
        $this->db->transaction(function () use ($actor, $roleId, $role): void {
            if ($this->roles->delete($actor->tenantId, $roleId)) {
                $this->log('role.deleted', $actor, $roleId, ['slug' => (string) $role['slug']]);
            }
        });
    }

    /** @return array<string, mixed> */
    private function findOrFail(AuthUser $actor, int $id): array
    {
        $role = $this->roles->find($actor->tenantId, $id);
        if ($role === null) {
            $this->denied->entityNotFound($actor, 'role', $id);
            throw new NotFoundException('Role tidak ditemukan.');
        }
        return $role;
    }

    /** @return array{0: string, 1: string} [nama rapi, slug] */
    private function validName(string $name): array
    {
        if (str_contains($name, "\0")) {
            throw new ValidationException(['name' => 'Nama role mengandung karakter tidak valid.']);
        }
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        if ($name === '') {
            throw new ValidationException(['name' => 'Nama role wajib diisi.']);
        }
        if (mb_strlen($name) > self::NAME_MAX) {
            throw new ValidationException(['name' => 'Nama role maksimal ' . self::NAME_MAX . ' karakter.']);
        }
        $slug = trim(substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-'), 0, self::SLUG_MAX), '-');
        if ($slug === '') {
            throw new ValidationException(['name' => 'Nama role harus memuat huruf (a-z) atau angka.']);
        }
        return [$name, $slug];
    }

    /**
     * @param list<mixed> $raw
     * @return list<string> kode unik yang ada di katalog, terurut
     * @throws ValidationException
     */
    private function validCodes(array $raw): array
    {
        $known = array_flip($this->permissions->allCodes());
        $codes = [];
        foreach ($raw as $code) {
            if (!is_string($code) || !isset($known[$code])) {
                throw new ValidationException(['permissions' => 'Kode izin tidak dikenal.']);
            }
            $codes[$code] = $code;
        }
        $codes = array_values($codes);
        sort($codes);
        return $codes;
    }

    /** @param list<string> $codes @throws ForbiddenException */
    private function requireGrantable(AuthUser $actor, array $codes): void
    {
        foreach ($codes as $code) {
            if (!$this->policy->can($actor, $code)) {
                $this->denied->permissionDenied($actor, $code);
                throw new ForbiddenException('Anda tidak dapat memberikan izin yang tidak Anda miliki.');
            }
        }
    }

    /** @param array<string, mixed> $metadata */
    private function log(string $action, AuthUser $actor, int $roleId, array $metadata): void
    {
        $this->audit->record($action, $actor->tenantId, AuditLogger::ACTOR_TENANT_USER, $actor->id, 'role', $roleId, $metadata);
    }
}
