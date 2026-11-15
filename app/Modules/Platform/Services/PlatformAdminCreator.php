<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Core\Security\PasswordHasher;
use App\Core\Validation\Validator;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Platform\Exceptions\DuplicatePlatformAdminException;
use App\Modules\Platform\Exceptions\PlatformAdminException;
use App\Modules\Platform\Repositories\PlatformUserRepository;
use Closure;
use Throwable;

/** Satu-satunya jalur membuat admin platform (dipanggil skrip CLI; tidak ada pendaftaran lewat web). */
final class PlatformAdminCreator
{
    public const MIN_PASSWORD = 8;
    public const MAX_PASSWORD_BYTES = 72;

    private Closure $clock;

    /** @param (callable(): int)|null $clock */
    public function __construct(
        private readonly PlatformUserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly ?AuditLogger $audit = null,
        ?callable $clock = null,
    ) {
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    /** @throws PlatformAdminException|DuplicatePlatformAdminException */
    public function create(string $name, string $email, string $password): int
    {
        $name = trim($name);
        $email = Validator::normalizeEmail($email);
        if ($name === '' || mb_strlen($name) > 150 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            throw new PlatformAdminException('Nama wajib diisi, maksimal 150 karakter, tanpa karakter kontrol.');
        }
        if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new PlatformAdminException('Email tidak valid (maksimal 190 karakter).');
        }
        if (str_contains($password, "\0")) {
            throw new PlatformAdminException('Kata sandi tidak boleh mengandung karakter NUL.');
        }
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            throw new PlatformAdminException('Kata sandi minimal ' . self::MIN_PASSWORD . ' karakter.');
        }
        if (strlen($password) > self::MAX_PASSWORD_BYTES) {
            throw new PlatformAdminException('Kata sandi maksimal ' . self::MAX_PASSWORD_BYTES . ' byte.');
        }
        if ($this->users->findByEmail($email) !== null) {
            throw new DuplicatePlatformAdminException('Email sudah terdaftar sebagai admin platform.');
        }

        try {
            $id = $this->users->create($name, $email, $this->hasher->hash($password), gmdate('Y-m-d H:i:s', ($this->clock)()));
        } catch (Throwable $e) {
            // Balapan pada kunci unik: periksa ulang agar pesan tetap jelas.
            if ($this->users->findByEmail($email) !== null) {
                throw new DuplicatePlatformAdminException('Email sudah terdaftar sebagai admin platform.');
            }
            throw $e;
        }
        $this->audit?->record('platform.admin_created', null, AuditLogger::ACTOR_SYSTEM, null, 'platform_user', $id);
        return $id;
    }
}
