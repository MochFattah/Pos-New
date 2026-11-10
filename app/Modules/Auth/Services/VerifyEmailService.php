<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\Database\Connection;
use App\Core\Security\PasswordHasher;
use App\Core\Security\TokenService;
use App\Core\Validation\Validator;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Auth\Repositories\EmailVerificationRepository;
use App\Modules\Tenants\DemoService;
use App\Modules\Tenants\Repositories\TenantRepository;
use App\Modules\Tenants\Repositories\UserRepository;
use Closure;

final class VerifyEmailService
{
    private const MIN_PASSWORD_LENGTH = 8;

    private Closure $clock;
    private TokenService $tokens;
    private PasswordHasher $hasher;

    /** @param (callable(): int)|null $clock */
    public function __construct(
        private readonly Connection $db,
        private readonly EmailVerificationRepository $verifications,
        private readonly UserRepository $users,
        private readonly TenantRepository $tenants,
        private readonly DemoService $demo,
        private readonly VerificationEmailSender $sender,
        private readonly AuditLogger $audit,
        ?callable $clock = null,
        ?PasswordHasher $hasher = null,
        ?TokenService $tokens = null,
    ) {
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
        $this->hasher = $hasher ?? new PasswordHasher();
        $this->tokens = $tokens ?? new TokenService();
    }

    /** Verifikasi Owner: mengaktifkan pengguna dan tenant, lalu memulai demo sekali. */
    public function verify(string $plainToken): bool
    {
        return $this->consume($plainToken, true, null);
    }

    /** Penerimaan undangan pengguna non-Owner: aktifkan akun sekaligus menetapkan kata sandi. */
    public function verifyAndSetPassword(string $plainToken, string $newPassword): bool
    {
        if (mb_strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            return false;
        }
        return $this->consume($plainToken, false, $newPassword);
    }

    /** Respons netral: tidak ada efek (dan tidak ada sinyal) untuk email tak dikenal atau sudah terverifikasi. */
    public function resend(string $email, string $ip): void
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || $user['status'] !== 'pending_verification' || (int) $user['is_owner'] !== 1) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s', ($this->clock)());
        $this->db->transaction(function () use ($user, $now, $ip): void {
            $this->verifications->invalidateForUser((int) $user['id'], $now);
            $this->audit->record(
                'auth.verification_resent',
                (int) $user['tenant_id'],
                AuditLogger::ACTOR_TENANT_USER,
                (int) $user['id'],
                'user',
                (int) $user['id'],
                ['requested_from_ip' => $ip]
            );
            $this->sender->send((int) $user['id'], (string) $user['email'], (string) $user['name']);
        });
    }

    private function consume(string $plainToken, bool $owner, ?string $newPassword): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $plainToken) !== 1) {
            return false;
        }
        $nowTs = ($this->clock)();
        $now = gmdate('Y-m-d H:i:s', $nowTs);

        return (bool) $this->db->transaction(function () use ($plainToken, $owner, $newPassword, $now): bool {
            $row = $this->verifications->findByHash($this->tokens->hash($plainToken));
            if ($row === null || $row['used_at'] !== null || (string) $row['expires_at'] <= $now) {
                return false;
            }
            $user = $this->users->findByIdAnyTenant((int) $row['user_id']);
            if ($user === null || $user['status'] !== 'pending_verification' || ((int) $user['is_owner'] === 1) !== $owner) {
                return false;
            }
            if (!$this->verifications->markUsed((int) $row['id'], $now)) {
                return false;
            }
            $tenantId = (int) $user['tenant_id'];
            $hash = $newPassword === null ? null : $this->hasher->hash($newPassword);
            if (!$this->users->activate((int) $user['id'], $now, $hash)) {
                return false;
            }
            if ($owner) {
                $this->tenants->activateIfPending($tenantId, $now);
                $this->demo->startFor($tenantId, 'registration');
            }
            $this->audit->record(
                'auth.email_verified',
                $tenantId,
                AuditLogger::ACTOR_TENANT_USER,
                (int) $user['id'],
                'user',
                (int) $user['id'],
                ['owner' => $owner]
            );
            return true;
        });
    }
}
