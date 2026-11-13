<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\Authorization\AuthUser;
use App\Core\Database\Connection;
use App\Core\Security\PasswordHasher;
use App\Core\Security\RateLimiter;
use App\Core\Security\Session;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Tenants\Repositories\UserRepository;
use Closure;

final class AuthService
{
    public const SESSION_USER = 'auth_user_id';
    public const SESSION_TENANT = 'auth_tenant_id';
    public const SESSION_FINGERPRINT = 'auth_pwv';

    /** Identifier limiter agregat per-IP (semua email) untuk menahan password spraying. */
    public const IP_IDENTIFIER = '*ip*';

    private const MAX_PASSWORD_BYTES = 72;

    /**
     * Hash bcrypt prakomputasi (cost 10 = PASSWORD_DEFAULT, sama dengan PasswordHasher) untuk email tak dikenal,
     * sehingga jalur itu hanya satu verify() seperti jalur email dikenal dan tidak ada hash() malas per proses.
     * Jika cost default PHP/PasswordHasher naik, buat ulang konstanta ini (ada tesnya).
     */
    public const DUMMY_HASH = '$2y$10$hh6X1YdBuzIZSpvtUZwaQ.pm3zfvuWngSjqpYuxKl0f1AXzXmlS2.';

    private Closure $clock;

    /** @param (callable(): int)|null $clock */
    public function __construct(
        private readonly Connection $db,
        private readonly UserRepository $users,
        private readonly RateLimiter $limiter,
        private readonly RateLimiter $ipLimiter,
        private readonly PasswordHasher $hasher,
        private readonly AuditLogger $audit,
        ?callable $clock = null,
    ) {
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    /**
     * Mencoba masuk. Hasil OK sudah memutar ID sesi (dan CSRF) dan mengisi sesi.
     * Email tak dikenal dan password salah menghasilkan hasil serupa, dengan satu kali
     * password_verify pada kedua jalur agar waktu respons tidak membedakannya.
     */
    public function attempt(string $email, string $password, string $ip, Session $session): AuthResult
    {
        if ($this->limiter->tooManyAttempts($email, $ip) || $this->ipLimiter->tooManyAttempts(self::IP_IDENTIFIER, $ip)) {
            return AuthResult::locked();
        }

        $user = $this->users->findByEmail($email);
        $tooLong = strlen($password) > self::MAX_PASSWORD_BYTES;
        // Batas bcrypt 72 byte: lebih panjang dari itu tidak pernah dicocokkan (sisa byte akan diabaikan diam-diam).
        $hash = $user !== null && !$tooLong ? (string) $user['password_hash'] : self::DUMMY_HASH;
        $passwordOk = $this->hasher->verify($tooLong ? '' : $password, $hash) && $user !== null && !$tooLong;

        $tenantActive = $user !== null && $this->tenantStatus((int) $user['tenant_id']) === 'active';
        if (!$passwordOk || $user === null) {
            $this->fail($email, $ip, $user, 'invalid_credentials');
            return AuthResult::failed();
        }
        if ($user['status'] === 'pending_verification') {
            // Hanya setelah password benar, agar status akun tidak bocor ke penebak.
            return AuthResult::unverified();
        }
        if ($user['status'] !== 'active' || !$tenantActive) {
            $this->fail($email, $ip, $user, 'account_not_active');
            return AuthResult::failed();
        }

        $authUser = $this->loadUser((int) $user['tenant_id'], (int) $user['id']);
        if ($authUser === null) {
            $this->fail($email, $ip, $user, 'account_not_active');
            return AuthResult::failed();
        }

        $this->limiter->clear($email, $ip);
        $session->regenerate();
        $session->set(self::SESSION_USER, $authUser->id);
        $session->set(self::SESSION_TENANT, $authUser->tenantId);
        $session->set(self::SESSION_FINGERPRINT, self::fingerprint((string) $user['password_hash']));
        $this->users->touchLastLogin($authUser->tenantId, $authUser->id, gmdate('Y-m-d H:i:s', ($this->clock)()));
        $this->audit->record('auth.login', $authUser->tenantId, AuditLogger::ACTOR_TENANT_USER, $authUser->id, 'user', $authUser->id);
        return AuthResult::ok($authUser);
    }

    public function logout(Session $session): void
    {
        $userId = $session->get(self::SESSION_USER);
        $tenantId = $session->get(self::SESSION_TENANT);
        if (is_int($userId) && is_int($tenantId)) {
            $this->audit->record('auth.logout', $tenantId, AuditLogger::ACTOR_TENANT_USER, $userId, 'user', $userId);
        }
        $session->destroy();
    }

    /**
     * Pengguna dari sesi, atau null bila sesi kosong/tak valid. Dicek ulang ke DB di setiap request:
     * pengguna harus masih aktif, tenant aktif, dan sidik jari password di sesi harus sama dengan
     * hash saat ini, sehingga ganti/reset password mencabut seluruh sesi lama tanpa tabel sesi sisi server.
     */
    public function userFromSession(Session $session): ?AuthUser
    {
        $userId = $session->get(self::SESSION_USER);
        $tenantId = $session->get(self::SESSION_TENANT);
        $fingerprint = $session->get(self::SESSION_FINGERPRINT);
        if (!is_int($userId) || !is_int($tenantId) || !is_string($fingerprint)) {
            return null;
        }
        $row = $this->users->findById($tenantId, $userId);
        if ($row === null || $row['status'] !== 'active'
            || !hash_equals(self::fingerprint((string) $row['password_hash']), $fingerprint)
            || $this->tenantStatus($tenantId) !== 'active') {
            return null;
        }
        return $this->loadUser($tenantId, $userId);
    }

    private function loadUser(int $tenantId, int $userId): ?AuthUser
    {
        $row = $this->users->findById($tenantId, $userId);
        if ($row === null) {
            return null;
        }
        $roleIds = array_map(
            static fn (array $r): int => (int) $r['role_id'],
            $this->db->query('SELECT role_id FROM user_roles WHERE tenant_id = ? AND user_id = ? ORDER BY role_id', [$tenantId, $userId])
        );
        $permissions = array_column($this->db->query(
            'SELECT DISTINCT p.code FROM user_roles ur
             JOIN role_permissions rp ON rp.tenant_id = ur.tenant_id AND rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.tenant_id = ? AND ur.user_id = ? ORDER BY p.code',
            [$tenantId, $userId]
        ), 'code');
        $branch = $this->db->fetchOne(
            'SELECT branch_id FROM user_branch_access WHERE tenant_id = ? AND user_id = ? ORDER BY branch_id LIMIT 1',
            [$tenantId, $userId]
        );
        return new AuthUser(
            $userId,
            $tenantId,
            $branch === null ? null : (int) $branch['branch_id'],
            (string) $row['name'],
            (string) $row['email'],
            (int) $row['is_owner'] === 1,
            $roleIds,
            array_map('strval', $permissions),
        );
    }

    /** @param array<string, mixed>|null $user */
    private function fail(string $email, string $ip, ?array $user, string $reason): void
    {
        $this->limiter->record($email, $ip, false);
        $this->ipLimiter->record(self::IP_IDENTIFIER, $ip, false);
        $this->audit->record(
            'auth.login_failed',
            $user === null ? null : (int) $user['tenant_id'],
            $user === null ? AuditLogger::ACTOR_SYSTEM : AuditLogger::ACTOR_TENANT_USER,
            $user === null ? null : (int) $user['id'],
            null,
            null,
            ['reason' => $reason]
        );
    }

    private function tenantStatus(int $tenantId): ?string
    {
        $row = $this->db->fetchOne('SELECT status FROM tenants WHERE id = ?', [$tenantId]);
        return $row === null ? null : (string) $row['status'];
    }

    private static function fingerprint(string $passwordHash): string
    {
        return hash('sha256', 'pwv|' . $passwordHash);
    }
}
