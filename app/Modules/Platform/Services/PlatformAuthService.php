<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Core\Security\PasswordHasher;
use App\Core\Security\RateLimiter;
use App\Core\Security\Session;
use App\Core\Database\Connection;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Platform\Repositories\PlatformUserRepository;
use Closure;

/**
 * Login Super Admin. Satu cookie sesi dengan tenant tetapi kunci terpisah; masuk ke satu sisi
 * menghapus kunci sisi lain (tidak ada pencampuran hak akses). Tanpa tenant_id, tanpa AccessGate.
 */
final class PlatformAuthService
{
    public const SESSION_USER = 'platform_user';
    public const SESSION_FINGERPRINT = 'platform_pwv';

    public const OK = 'ok';
    public const FAILED = 'failed';
    public const LOCKED = 'locked';

    /** Namespace limiter terpisah dari login tenant (login_attempts dipakai bersama). */
    public const IDENTIFIER_PREFIX = 'platform:';
    public const IP_IDENTIFIER = '*platform-ip*';

    private const MAX_PASSWORD_BYTES = 72;

    private Closure $clock;

    /** @param (callable(): int)|null $clock */
    public function __construct(
        private readonly Connection $db,
        private readonly PlatformUserRepository $users,
        private readonly RateLimiter $limiter,
        private readonly RateLimiter $ipLimiter,
        private readonly PasswordHasher $hasher,
        private readonly AuditLogger $audit,
        ?callable $clock = null,
    ) {
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    /**
     * Email tak dikenal, password salah, dan akun nonaktif menghasilkan FAILED yang sama,
     * dengan satu password_verify pada semua jalur. Sukses memutar ID sesi dan menghapus kunci sesi tenant.
     *
     * @return self::OK|self::FAILED|self::LOCKED
     */
    public function attempt(string $email, string $password, string $ip, Session $session): string
    {
        $key = self::IDENTIFIER_PREFIX . strtolower(trim($email));
        if ($this->limiter->tooManyAttempts($key, $ip) || $this->ipLimiter->tooManyAttempts(self::IP_IDENTIFIER, $ip)) {
            return self::LOCKED;
        }

        $user = $this->users->findByEmail($email);
        $tooLong = strlen($password) > self::MAX_PASSWORD_BYTES;
        $hash = $user !== null && !$tooLong ? (string) $user['password_hash'] : AuthService::DUMMY_HASH;
        $passwordOk = $this->hasher->verify($tooLong ? '' : $password, $hash) && $user !== null && !$tooLong;

        if (!$passwordOk || $user === null || $user['status'] !== 'active') {
            $this->limiter->record($key, $ip, false);
            $this->ipLimiter->record(self::IP_IDENTIFIER, $ip, false);
            $this->audit->record(
                'platform.login_failed',
                null,
                $user === null ? AuditLogger::ACTOR_SYSTEM : AuditLogger::ACTOR_PLATFORM_USER,
                $user === null ? null : (int) $user['id'],
                null,
                null,
                ['reason' => $user !== null && $passwordOk ? 'account_not_active' : 'invalid_credentials']
            );
            return self::FAILED;
        }

        $id = (int) $user['id'];
        $this->limiter->clear($key, $ip);
        $this->clearTenantKeys($session);
        $session->regenerate();
        $session->set(self::SESSION_USER, $id);
        $session->set(self::SESSION_FINGERPRINT, self::fingerprint((string) $user['password_hash']));
        $this->users->touchLastLogin($id, gmdate('Y-m-d H:i:s', ($this->clock)()));
        $this->audit->record('platform.login', null, AuditLogger::ACTOR_PLATFORM_USER, $id, 'platform_user', $id);
        return self::OK;
    }

    public function logout(Session $session): void
    {
        $id = $session->get(self::SESSION_USER);
        if (is_int($id)) {
            $this->audit->record('platform.logout', null, AuditLogger::ACTOR_PLATFORM_USER, $id, 'platform_user', $id);
        }
        $session->destroy();
    }

    /**
     * Pengguna platform dari sesi, dicek ulang ke DB tiap request (aktif + sidik jari password).
     *
     * @return array{id: int, name: string, email: string}|null
     */
    public function userFromSession(Session $session): ?array
    {
        $id = $session->get(self::SESSION_USER);
        $fingerprint = $session->get(self::SESSION_FINGERPRINT);
        if (!is_int($id) || !is_string($fingerprint)) {
            return null;
        }
        $row = $this->users->findById($id);
        if ($row === null || $row['status'] !== 'active'
            || !hash_equals(self::fingerprint((string) $row['password_hash']), $fingerprint)) {
            return null;
        }
        return ['id' => $id, 'name' => (string) $row['name'], 'email' => (string) $row['email']];
    }

    public static function forgetKeys(Session $session): void
    {
        $session->forget(self::SESSION_USER);
        $session->forget(self::SESSION_FINGERPRINT);
    }

    private function clearTenantKeys(Session $session): void
    {
        $session->forget(AuthService::SESSION_USER);
        $session->forget(AuthService::SESSION_TENANT);
        $session->forget(AuthService::SESSION_FINGERPRINT);
    }

    private static function fingerprint(string $passwordHash): string
    {
        return hash('sha256', 'platform-pwv|' . $passwordHash);
    }
}
