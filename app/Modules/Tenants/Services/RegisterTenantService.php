<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Services;

use App\Core\Database\Connection;
use App\Core\Security\PasswordHasher;
use App\Core\Validation\Validator;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Auth\Services\VerificationEmailSender;
use App\Modules\Tenants\Exceptions\DuplicateEmailException;
use App\Modules\Tenants\Repositories\RoleRepository;
use App\Modules\Tenants\Repositories\TenantRepository;
use App\Modules\Tenants\Repositories\UserRepository;
use Closure;

final class RegisterTenantService
{
    private const STALE_SECONDS = 86400;

    private Closure $clock;

    /** @param (callable(): int)|null $clock */
    public function __construct(
        private readonly Connection $db,
        private readonly TenantRepository $tenants,
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly VerificationEmailSender $sender,
        private readonly PasswordHasher $hasher,
        private readonly AuditLogger $audit,
        ?callable $clock = null,
    ) {
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    /**
     * Seluruh pendaftaran dalam satu transaksi; email dikirim terakhir sehingga kegagalan
     * pengiriman (MailException) membatalkan semuanya. Input diasumsikan sudah divalidasi.
     *
     * @param array<string, string> $input
     * @return array{tenantId: int, userId: int}
     * @throws DuplicateEmailException
     */
    public function register(array $input): array
    {
        if (str_contains($input['password'], "\0")) {
            throw new \InvalidArgumentException('Kata sandi tidak boleh mengandung byte NUL.');
        }
        $email = Validator::normalizeEmail($input['email']);
        $phone = ($input['phone'] ?? '') === '' ? null : (string) preg_replace('/[\s\-()]/', '', $input['phone']);
        $address = ($input['address'] ?? '') === '' ? null : $input['address'];
        $type = ($input['business_type'] ?? '') === '' ? null : $input['business_type'];
        $now = gmdate('Y-m-d H:i:s', ($this->clock)());

        /** @var array{tenantId: int, userId: int} */
        return $this->db->transaction(function () use ($input, $email, $phone, $address, $type, $now): array {
            $reclaimed = null;
            if ($this->users->findByEmail($email) !== null) {
                // Hanya undangan non-Owner yang basi (tanpa token berlaku, > 24 jam) yang bisa dibebaskan;
                // pengguna aktif, Owner, dan undangan segar tetap memberi 'Email sudah terdaftar' yang netral.
                $reclaimed = $this->users->reclaimStalePending($email, $now, gmdate('Y-m-d H:i:s', ($this->clock)() - self::STALE_SECONDS));
                if ($reclaimed === null) {
                    throw new DuplicateEmailException();
                }
            }
            $tenantId = $this->tenants->create($input['business_name'], 'pending_verification', $now);
            $this->tenants->createProfile($tenantId, $type, $address, $phone);
            $this->tenants->createDefaultSettings($tenantId);
            $this->tenants->createMainBranch($tenantId, $address, $phone, $now);
            $roleIds = $this->roles->createSystemRoles($tenantId);
            $userId = $this->users->create(
                $tenantId,
                $input['owner_name'],
                $email,
                $phone,
                $this->hasher->hash($input['password']),
                true,
                'pending_verification',
                $now
            );
            $this->roles->assign($tenantId, $userId, $roleIds['owner']);
            $this->audit->record(
                'tenant.registered',
                $tenantId,
                AuditLogger::ACTOR_TENANT_USER,
                $userId,
                'tenant',
                $tenantId,
                ['business_name' => $input['business_name']]
            );
            if ($reclaimed !== null) {
                $this->audit->record('user.stale_invite_reclaimed', $tenantId, AuditLogger::ACTOR_TENANT_USER, $userId, 'user', $reclaimed['id'], ['previous_tenant_id' => $reclaimed['tenant_id']]);
            }
            $this->sender->send($userId, $email, $input['owner_name']);
            return ['tenantId' => $tenantId, 'userId' => $userId];
        });
    }
}
