<?php

declare(strict_types=1);

namespace App\Modules\Branches\Services;

use App\Core\Authorization\AccessDeniedRecorder;
use App\Core\Authorization\AuthUser;
use App\Core\Authorization\PolicyService;
use App\Core\Database\Connection;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Branches\Exceptions\DuplicateBranchCodeException;
use App\Modules\Branches\Repositories\BranchRepository;
use App\Modules\Branches\Validators\BranchValidator;

/**
 * Manajemen cabang. tenant_id selalu diambil dari $actor (sesi server), tidak pernah dari input.
 * ID cabang milik tenant lain diperlakukan sama dengan ID yang tidak ada (NotFoundException).
 */
final class BranchService
{
    public const VIEW = 'branches.view';
    public const MANAGE = 'branches.manage';

    private const DUPLICATE_CODE = 'Kode cabang sudah dipakai cabang lain.';

    /** @var callable(): int */
    private $clock;

    /** @param (callable(): int)|null $clock detik unix */
    public function __construct(
        private readonly Connection $db,
        private readonly BranchRepository $branches,
        private readonly PolicyService $policy,
        private readonly AuditLogger $audit,
        private readonly AccessDeniedRecorder $denied,
        ?callable $clock = null
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Owner melihat semua cabang tenant; pengguna lain hanya cabang di user_branch_access
     * (tanpa baris akses = daftar kosong, gagal tertutup).
     *
     * @return list<array<string, mixed>>
     */
    public function list(AuthUser $actor): array
    {
        $this->policy->authorize($actor, self::VIEW);
        return $actor->isOwner
            ? $this->branches->listByTenant($actor->tenantId)
            : $this->branches->listForUser($actor->tenantId, $actor->id);
    }

    /** @return array<string, mixed> */
    public function get(AuthUser $actor, int $id): array
    {
        $this->policy->authorize($actor, self::VIEW);
        return $this->findOrFail($actor, $id);
    }

    /** @return array<string, mixed> cabang untuk formulir ubah (butuh izin kelola) */
    public function forEdit(AuthUser $actor, int $id): array
    {
        $this->policy->authorize($actor, self::MANAGE);
        $branch = $this->findOrFail($actor, $id);
        $this->policy->authorize($actor, self::MANAGE, $id);
        return $branch;
    }

    /** @param array<string, mixed> $input @throws ValidationException */
    public function create(AuthUser $actor, array $input): int
    {
        $this->policy->authorize($actor, self::MANAGE);
        $data = $this->validated($input);
        if ($this->branches->codeExists($actor->tenantId, $data['code'])) {
            throw new ValidationException(['code' => self::DUPLICATE_CODE]);
        }
        $now = $this->now();
        try {
            return $this->db->transaction(function () use ($actor, $data, $now): int {
                $id = $this->branches->create($actor->tenantId, $data['code'], $data['name'], $data['address'], $data['phone'], $now);
                $this->log('branch.created', $actor, $id, ['code' => $data['code'], 'name' => $data['name']]);
                if (!$actor->isOwner) {
                    // Pembuat non-Owner otomatis berhak atas cabang barunya; satu transaksi dengan pembuatan cabang.
                    $this->branches->grantAccess($actor->tenantId, $actor->id, $id);
                    $this->audit->record('user.branches_assigned', $actor->tenantId, AuditLogger::ACTOR_TENANT_USER, $actor->id, 'user', $actor->id, ['added' => [$id], 'removed' => [], 'reason' => 'branch_created']);
                }
                return $id;
            });
        } catch (DuplicateBranchCodeException) {
            throw new ValidationException(['code' => self::DUPLICATE_CODE]);
        }
    }

    /** @param array<string, mixed> $input @throws ValidationException */
    public function update(AuthUser $actor, int $id, array $input): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $current = $this->findOrFail($actor, $id);
        $this->policy->authorize($actor, self::MANAGE, $id);
        $data = $this->validated($input);
        if ($this->branches->codeExists($actor->tenantId, $data['code'], $id)) {
            throw new ValidationException(['code' => self::DUPLICATE_CODE]);
        }

        $changes = [];
        foreach (['code', 'name', 'address', 'phone'] as $field) {
            $old = $current[$field] === null ? null : (string) $current[$field];
            if ($old !== $data[$field]) {
                $changes[$field] = ['from' => $old, 'to' => $data[$field]];
            }
        }
        if ($changes === []) {
            return;
        }
        $now = $this->now();
        try {
            $this->db->transaction(function () use ($actor, $id, $data, $now, $changes): void {
                $this->branches->update($actor->tenantId, $id, $data['code'], $data['name'], $data['address'], $data['phone'], $now);
                $this->log('branch.updated', $actor, $id, ['changes' => $changes]);
            });
        } catch (DuplicateBranchCodeException) {
            throw new ValidationException(['code' => self::DUPLICATE_CODE]);
        }
    }

    /** @throws ValidationException bila cabang utama */
    public function deactivate(AuthUser $actor, int $id): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $branch = $this->findOrFail($actor, $id);
        $this->policy->authorize($actor, self::MANAGE, $id);
        if ((int) $branch['is_main'] === 1) {
            throw new ValidationException(['status' => 'Cabang utama tidak dapat dinonaktifkan.']);
        }
        $this->changeStatus($actor, $id, 'inactive', 'branch.deactivated', (string) $branch['code']);
    }

    public function activate(AuthUser $actor, int $id): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $branch = $this->findOrFail($actor, $id);
        $this->policy->authorize($actor, self::MANAGE, $id);
        $this->changeStatus($actor, $id, 'active', 'branch.activated', (string) $branch['code']);
    }

    private function changeStatus(AuthUser $actor, int $id, string $status, string $action, string $code): void
    {
        $now = $this->now();
        $this->db->transaction(function () use ($actor, $id, $status, $action, $code, $now): void {
            if ($this->branches->setStatus($actor->tenantId, $id, $status, $now)) {
                $this->log($action, $actor, $id, ['code' => $code]);
            }
        });
    }

    /** @return array<string, mixed> */
    private function findOrFail(AuthUser $actor, int $id): array
    {
        $branch = $this->branches->find($actor->tenantId, $id);
        if ($branch === null) {
            $this->denied->entityNotFound($actor, 'branch', $id);
            throw new NotFoundException('Cabang tidak ditemukan.');
        }
        return $branch;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{code: string, name: string, address: ?string, phone: ?string}
     */
    private function validated(array $input): array
    {
        $result = BranchValidator::validate($input);
        if ($result->fails()) {
            throw new ValidationException($result->errors());
        }
        $v = $result->validated();
        return [
            'code' => $v['code'],
            'name' => $v['name'],
            'address' => ($v['address'] ?? '') === '' ? null : $v['address'],
            'phone' => ($v['phone'] ?? '') === '' ? null : $v['phone'],
        ];
    }

    /** @param array<string, mixed> $metadata */
    private function log(string $action, AuthUser $actor, int $branchId, array $metadata): void
    {
        $this->audit->record($action, $actor->tenantId, AuditLogger::ACTOR_TENANT_USER, $actor->id, 'branch', $branchId, $metadata);
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s', ($this->clock)());
    }
}
