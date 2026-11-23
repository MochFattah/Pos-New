<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use App\Core\Authorization\AccessDeniedRecorder;
use App\Core\Authorization\AuthUser;
use App\Core\Authorization\PolicyService;
use App\Core\Database\Connection;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Mail\MailException;
use App\Core\Security\PasswordHasher;
use App\Core\Security\RateLimiter;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Auth\Services\InvitationEmailSender;
use App\Modules\Branches\Repositories\BranchRepository;
use App\Modules\Tenants\Exceptions\DuplicateEmailException;
use App\Modules\Tenants\Repositories\RoleRepository;
use App\Modules\Tenants\Repositories\UserRepository;
use App\Modules\Users\Validators\UserValidator;
use Closure;

/**
 * Manajemen pengguna tenant. tenant_id selalu dari $actor (sesi server). ID pengguna/role/cabang milik
 * tenant lain diperlakukan sama dengan ID yang tidak ada (NotFoundException + audit security.entity_not_found).
 *
 * Aturan keras: Owner tidak bisa dinonaktifkan, dicabut flag/role Owner-nya, atau diubah oleh non-Owner;
 * role Owner tidak bisa diberikan ke non-Owner (jadi tenant selalu punya >= 1 Owner aktif); aktor tidak bisa
 * memberi atau mencabut role yang memuat izin yang tidak dimilikinya, dan tidak bisa memberi cabang yang
 * tidak dapat diakses (yang sudah ada dan di luar jangkauan aktor dipertahankan).
 *
 * Undangan: pengguna + token + email dikirim dalam satu transaksi, email terakhir (seperti registrasi di T7);
 * kegagalan kirim membatalkan seluruhnya.
 */
final class UserService
{
    public const VIEW = 'users.view';
    public const MANAGE = 'users.manage';

    private const DUPLICATE_EMAIL = 'Email sudah terdaftar.';
    private const MAIL_FAILED = 'Gagal mengirim email undangan. Tidak ada yang disimpan; coba lagi beberapa saat lagi.';
    private const INVITE_LIMITED = 'Terlalu banyak undangan dikirim dalam waktu singkat. Coba lagi nanti.';
    private const INVITE_PENDING_FULL = 'Terlalu banyak undangan yang belum diterima. Batalkan atau tunggu undangan sebelumnya diterima sebelum mengundang lagi.';
    private const RESEND_LIMITED = 'Terlalu banyak kirim ulang undangan untuk pengguna ini. Coba lagi nanti.';

    private Closure $clock;

    /** @param (callable(): int)|null $clock detik unix */
    public function __construct(
        private readonly Connection $db,
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly BranchRepository $branches,
        private readonly PolicyService $policy,
        private readonly AuditLogger $audit,
        private readonly AccessDeniedRecorder $denied,
        private readonly InvitationEmailSender $invitations,
        private readonly PasswordHasher $hasher,
        private readonly RateLimiter $resendLimiter,
        private readonly RateLimiter $inviteLimiter,
        private readonly int $maxPendingInvites = 50,
        ?callable $clock = null,
        private readonly int $staleSeconds = 86400,
    ) {
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    /** @return list<array<string, mixed>> pengguna tenant beserta 'role_names' */
    public function list(AuthUser $actor): array
    {
        $this->policy->authorize($actor, self::VIEW);
        $names = $this->roles->roleNamesByUser($actor->tenantId);
        $rows = $this->users->listByTenant($actor->tenantId);
        foreach ($rows as &$row) {
            $row['role_names'] = $names[(int) $row['id']] ?? [];
        }
        return $rows;
    }

    /** @return array<string, mixed> */
    public function forEdit(AuthUser $actor, int $id): array
    {
        $this->policy->authorize($actor, self::MANAGE);
        $user = $this->findOrFail($actor, $id);
        $this->guardOwnerTarget($actor, $user);
        return $user;
    }

    /**
     * Data formulir ubah: pengguna, role dan cabang yang dipegang, serta pilihan yang tersedia bagi aktor.
     *
     * @return array{user: array<string, mixed>, roles: list<array<string, mixed>>, branches: list<array<string, mixed>>}
     */
    public function editContext(AuthUser $actor, int $id): array
    {
        $user = $this->forEdit($actor, $id);
        $held = $this->roles->roleIdsForUser($actor->tenantId, $id);
        $isOwnerUser = (int) $user['is_owner'] === 1;
        $roles = [];
        foreach ($this->roles->listByTenant($actor->tenantId) as $role) {
            $isOwnerRole = RoleRepository::isOwnerRole($role);
            if ($isOwnerRole && !$isOwnerUser) {
                continue;
            }
            $checked = in_array((int) $role['id'], $held, true);
            $grantable = $this->canGrantAll($actor, $this->roles->permissionCodes($actor->tenantId, (int) $role['id']));
            $role['checked'] = $checked;
            $role['locked'] = $isOwnerRole || !$grantable;
            $roles[] = $role;
        }
        $heldBranches = $this->users->branchIds($actor->tenantId, $id);
        $branches = [];
        foreach ($this->branches->listByTenant($actor->tenantId) as $branch) {
            $branchId = (int) $branch['id'];
            $branch['checked'] = in_array($branchId, $heldBranches, true);
            $branch['locked'] = !$this->policy->can($actor, self::MANAGE, $branchId);
            $branches[] = $branch;
        }
        return ['user' => $user, 'roles' => $roles, 'branches' => $branches];
    }

    /** @param array<string, mixed> $input @throws ValidationException */
    public function invite(AuthUser $actor, array $input): int
    {
        $this->policy->authorize($actor, self::MANAGE);
        $result = UserValidator::validateInvite($input);
        if ($result->fails()) {
            throw new ValidationException($result->errors());
        }
        $data = $result->validated();
        $phone = ($data['phone'] ?? '') === '' ? null : $data['phone'];
        // Kuota per tenant; setiap percobaan yang lolos validasi dihitung (termasuk yang ditolak duplikat),
        // jadi tidak bisa dipakai menebak email tanpa batas.
        $quotaKey = 'invite:tenant:' . $actor->tenantId;
        if ($this->inviteLimiter->tooManyAttempts($quotaKey, 'invite')) {
            throw new ValidationException(['form' => self::INVITE_LIMITED]);
        }
        $this->inviteLimiter->record($quotaKey, 'invite', false);
        if ($this->users->countPendingNonOwner($actor->tenantId) >= $this->maxPendingInvites) {
            throw new ValidationException(['form' => self::INVITE_PENDING_FULL]);
        }
        $existing = $this->users->findByEmail($data['email']);
        if ($existing !== null && !$this->isReclaimable($existing)) {
            throw new ValidationException(['email' => self::DUPLICATE_EMAIL]);
        }
        // Hash acak yang tak pernah diketahui siapa pun: akun tidak bisa dimasuki sebelum undangan diterima.
        $placeholder = $this->hasher->hash(bin2hex(random_bytes(32)));
        $now = $this->now();
        try {
            return $this->db->transaction(function () use ($actor, $data, $phone, $placeholder, $now, $existing): int {
                if ($existing !== null) {
                    $cutoff = gmdate('Y-m-d H:i:s', ($this->clock)() - $this->staleSeconds);
                    $gone = $this->users->reclaimStalePending($data['email'], $now, $cutoff);
                    if ($gone === null) {
                        throw new DuplicateEmailException();
                    }
                    $this->log('user.stale_invite_reclaimed', $actor, $gone['id'], ['previous_tenant_id' => $gone['tenant_id']]);
                    // Tenant asal juga perlu jejak; tanpa email dan tanpa identitas tenant pengundang baru.
                    $this->audit->record('user.stale_invite_reclaimed_lost', $gone['tenant_id'], AuditLogger::ACTOR_SYSTEM, null, 'user', $gone['id'], []);
                }
                $id = $this->users->create($actor->tenantId, $data['name'], $data['email'], $phone, $placeholder, false, 'pending_verification', $now);
                $this->log('user.invited', $actor, $id, ['email' => $data['email']]);
                $this->invitations->send($id, $data['email'], $data['name']);
                return $id;
            });
        } catch (DuplicateEmailException) {
            throw new ValidationException(['email' => self::DUPLICATE_EMAIL]);
        } catch (MailException) {
            throw new ValidationException(['form' => self::MAIL_FAILED]);
        }
    }

    /** Pra-pemeriksaan murah; penentu sebenarnya adalah penjaga atomik di UserRepository::reclaimStalePending. */
    private function isReclaimable(array $user): bool
    {
        return $user['status'] === 'pending_verification'
            && (int) $user['is_owner'] === 0
            && strtotime((string) $user['created_at'] . ' UTC') < ($this->clock)() - $this->staleSeconds;
    }

    /** @throws ValidationException bila pengguna bukan pending non-Owner atau terlalu sering dikirim ulang */
    public function resendInvite(AuthUser $actor, int $id): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $user = $this->findOrFail($actor, $id);
        $this->guardOwnerTarget($actor, $user);
        if ((int) $user['is_owner'] === 1 || $user['status'] !== 'pending_verification') {
            throw new ValidationException(['status' => 'Undangan hanya dapat dikirim ulang untuk pengguna yang belum menerima undangan.']);
        }
        $key = 'invite:' . $actor->tenantId . ':' . $id;
        if ($this->resendLimiter->tooManyAttempts($key, 'resend')) {
            throw new ValidationException(['form' => self::RESEND_LIMITED]);
        }
        $this->resendLimiter->record($key, 'resend', false);
        try {
            $this->db->transaction(function () use ($actor, $user, $id): void {
                $this->log('user.invitation_resent', $actor, $id, []);
                $this->invitations->send($id, (string) $user['email'], (string) $user['name']);
            });
        } catch (MailException) {
            throw new ValidationException(['form' => self::MAIL_FAILED]);
        }
    }

    /** @param array<string, mixed> $input hanya name dan phone yang dipakai @throws ValidationException */
    public function update(AuthUser $actor, int $id, array $input): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $user = $this->findOrFail($actor, $id);
        $this->guardOwnerTarget($actor, $user);
        $result = UserValidator::validateProfile($input);
        if ($result->fails()) {
            throw new ValidationException($result->errors());
        }
        $data = $result->validated();
        $phone = ($data['phone'] ?? '') === '' ? null : $data['phone'];
        $fields = [];
        if ((string) $user['name'] !== $data['name']) {
            $fields[] = 'name';
        }
        if ((string) ($user['phone'] ?? '') !== (string) $phone) {
            $fields[] = 'phone';
        }
        if ($fields === []) {
            return;
        }
        $now = $this->now();
        $this->db->transaction(function () use ($actor, $id, $data, $phone, $now, $fields): void {
            $this->users->updateProfile($actor->tenantId, $id, $data['name'], $phone, $now);
            $this->log('user.updated', $actor, $id, ['fields' => $fields]);
        });
    }

    /** @throws ValidationException */
    public function setStatus(AuthUser $actor, int $id, string $status): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $user = $this->findOrFail($actor, $id);
        $this->guardOwnerTarget($actor, $user);
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new ValidationException(['status' => 'Status tidak valid.']);
        }
        if ($status === 'inactive') {
            if ((int) $user['is_owner'] === 1) {
                throw new ValidationException(['status' => 'Owner tidak dapat dinonaktifkan.']);
            }
            if ($id === $actor->id) {
                throw new ValidationException(['status' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.']);
            }
        }
        if ($user['status'] === $status) {
            return;
        }
        if ($status === 'active' && $user['email_verified_at'] === null) {
            throw new ValidationException(['status' => 'Pengguna belum menerima undangan sehingga belum dapat diaktifkan.']);
        }
        $from = (string) $user['status'];
        $now = $this->now();
        $this->db->transaction(function () use ($actor, $id, $status, $from, $now): void {
            if ($this->users->setStatus($actor->tenantId, $id, $status, $now)) {
                $this->log('user.status_changed', $actor, $id, ['from' => $from, 'to' => $status]);
            }
        });
    }

    /**
     * Mengganti himpunan role pengguna. Role yang ditambah/dicabut tidak boleh memuat izin yang tidak
     * dimiliki aktor. Role Owner hanya untuk Owner dan wajib tetap ada pada Owner.
     *
     * @param list<mixed> $roleIds
     * @throws ValidationException|ForbiddenException|NotFoundException
     */
    public function assignRoles(AuthUser $actor, int $id, array $roleIds): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $user = $this->findOrFail($actor, $id);
        $this->guardOwnerTarget($actor, $user);
        $wanted = $this->idList($roleIds, 'role_ids', 'Role');

        $byId = [];
        foreach ($wanted as $roleId) {
            $role = $this->roles->find($actor->tenantId, $roleId);
            if ($role === null) {
                $this->denied->entityNotFound($actor, 'role', $roleId);
                throw new NotFoundException('Role tidak ditemukan.');
            }
            $byId[$roleId] = $role;
        }
        $hasOwnerRole = array_filter($byId, static fn (array $r): bool => RoleRepository::isOwnerRole($r)) !== [];
        if ((int) $user['is_owner'] === 1 && !$hasOwnerRole) {
            throw new ValidationException(['role_ids' => 'Owner harus tetap memiliki role Owner.']);
        }
        if ((int) $user['is_owner'] !== 1 && $hasOwnerRole) {
            throw new ValidationException(['role_ids' => 'Role Owner hanya untuk Owner.']);
        }

        $current = $this->roles->roleIdsForUser($actor->tenantId, $id);
        $added = array_values(array_diff($wanted, $current));
        $removed = array_values(array_diff($current, $wanted));
        $slugs = ['added' => [], 'removed' => []];
        foreach (['added' => $added, 'removed' => $removed] as $kind => $list) {
            foreach ($list as $roleId) {
                $role = $byId[$roleId] ?? $this->roles->find($actor->tenantId, $roleId);
                if ($role === null) {
                    continue;
                }
                $this->requireGrantable($actor, $this->roles->permissionCodes($actor->tenantId, $roleId));
                $slugs[$kind][] = (string) $role['slug'];
            }
        }
        if ($added === [] && $removed === []) {
            return;
        }
        sort($slugs['added']);
        sort($slugs['removed']);
        $this->db->transaction(function () use ($actor, $id, $added, $removed, $slugs): void {
            foreach ($removed as $roleId) {
                $this->roles->unassign($actor->tenantId, $id, $roleId);
            }
            foreach ($added as $roleId) {
                $this->roles->assign($actor->tenantId, $id, $roleId);
            }
            $this->log('user.roles_assigned', $actor, $id, $slugs);
        });
    }

    /**
     * Mengganti akses cabang pengguna non-Owner. Aktor hanya boleh menambah/mencabut cabang yang dapat
     * diaksesnya; akses ke cabang di luar jangkauan aktor dipertahankan.
     *
     * @param list<mixed> $branchIds
     * @throws ValidationException|ForbiddenException|NotFoundException
     */
    public function assignBranches(AuthUser $actor, int $id, array $branchIds): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $user = $this->findOrFail($actor, $id);
        $this->guardOwnerTarget($actor, $user);
        if ((int) $user['is_owner'] === 1) {
            throw new ValidationException(['branch_ids' => 'Owner otomatis dapat mengakses semua cabang.']);
        }
        $wanted = $this->idList($branchIds, 'branch_ids', 'Cabang');
        foreach ($wanted as $branchId) {
            if ($this->branches->find($actor->tenantId, $branchId) === null) {
                $this->denied->entityNotFound($actor, 'branch', $branchId);
                throw new NotFoundException('Cabang tidak ditemukan.');
            }
        }
        $current = $this->users->branchIds($actor->tenantId, $id);
        $added = array_values(array_diff($wanted, $current));
        foreach ($added as $branchId) {
            if (!$this->policy->can($actor, self::MANAGE, $branchId)) {
                $this->denied->permissionDenied($actor, self::MANAGE, $branchId);
                throw new ForbiddenException();
            }
        }
        $removed = [];
        foreach (array_diff($current, $wanted) as $branchId) {
            if ($this->policy->can($actor, self::MANAGE, $branchId)) {
                $removed[] = $branchId;
            }
        }
        if ($added === [] && $removed === []) {
            return;
        }
        sort($added);
        sort($removed);
        $this->db->transaction(function () use ($actor, $id, $added, $removed): void {
            foreach ($removed as $branchId) {
                $this->users->removeBranch($actor->tenantId, $id, $branchId);
            }
            foreach ($added as $branchId) {
                $this->users->addBranch($actor->tenantId, $id, $branchId);
            }
            $this->log('user.branches_assigned', $actor, $id, ['added' => $added, 'removed' => $removed]);
        });
    }

    /** @return array<string, mixed> */
    private function findOrFail(AuthUser $actor, int $id): array
    {
        $user = $this->users->findById($actor->tenantId, $id);
        if ($user === null) {
            $this->denied->entityNotFound($actor, 'user', $id);
            throw new NotFoundException('Pengguna tidak ditemukan.');
        }
        return $user;
    }

    /** @param array<string, mixed> $user */
    private function guardOwnerTarget(AuthUser $actor, array $user): void
    {
        if ((int) $user['is_owner'] === 1 && !$actor->isOwner) {
            $this->denied->permissionDenied($actor, 'users.modify_owner');
            throw new ForbiddenException('Hanya Owner yang dapat mengubah akun Owner.');
        }
    }

    /** @param list<string> $codes */
    private function canGrantAll(AuthUser $actor, array $codes): bool
    {
        foreach ($codes as $code) {
            if (!$this->policy->can($actor, $code)) {
                return false;
            }
        }
        return true;
    }

    /** @param list<string> $codes @throws ForbiddenException */
    private function requireGrantable(AuthUser $actor, array $codes): void
    {
        foreach ($codes as $code) {
            if (!$this->policy->can($actor, $code)) {
                $this->denied->permissionDenied($actor, $code);
                throw new ForbiddenException('Anda tidak dapat memberi atau mencabut role yang memuat izin yang tidak Anda miliki.');
            }
        }
    }

    /**
     * @param list<mixed> $raw
     * @return list<int> ID unik positif
     * @throws ValidationException
     */
    private function idList(array $raw, string $field, string $label): array
    {
        $ids = [];
        foreach ($raw as $value) {
            $valid = (is_int($value) && $value > 0)
                || (is_string($value) && preg_match('/^[1-9][0-9]{0,17}\z/', $value) === 1);
            if (!$valid) {
                throw new ValidationException([$field => $label . ' tidak valid.']);
            }
            $ids[(int) $value] = (int) $value;
        }
        return array_values($ids);
    }

    /** @param array<string, mixed> $metadata */
    private function log(string $action, AuthUser $actor, int $userId, array $metadata): void
    {
        $this->audit->record($action, $actor->tenantId, AuditLogger::ACTOR_TENANT_USER, $actor->id, 'user', $userId, $metadata);
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s', ($this->clock)());
    }
}
