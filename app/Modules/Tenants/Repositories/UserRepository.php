<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Repositories;

use App\Core\Database\Connection;
use App\Core\Validation\Validator;
use App\Modules\Tenants\Exceptions\DuplicateEmailException;
use PDOException;

class UserRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** Email dinormalisasi (trim + huruf kecil) sebelum dicari. @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE email = ?', [Validator::normalizeEmail($email)]);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $tenantId, int $userId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE tenant_id = ? AND id = ?', [$tenantId, $userId]);
    }

    /**
     * Pencarian tanpa tenant untuk alur token (tenant diturunkan dari baris pengguna itu sendiri).
     *
     * @return array<string, mixed>|null
     */
    public function findByIdAnyTenant(int $userId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
    }

    /**
     * @throws DuplicateEmailException bila batas unik email di DB menolak (balapan dua pendaftaran)
     */
    public function create(int $tenantId, string $name, string $email, ?string $phone, string $passwordHash, bool $isOwner, string $status, string $now): int
    {
        try {
            $this->db->execute(
                'INSERT INTO users (tenant_id, name, email, phone, password_hash, status, is_owner, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$tenantId, $name, Validator::normalizeEmail($email), $phone, $passwordHash, $status, $isOwner ? 1 : 0, $now, $now]
            );
        } catch (PDOException $e) {
            if (self::isDuplicateEmail($e)) {
                throw new DuplicateEmailException();
            }
            throw $e;
        }
        return $this->db->lastInsertId();
    }

    public function activate(int $userId, string $now, ?string $passwordHash = null): bool
    {
        if ($passwordHash === null) {
            return $this->db->execute(
                "UPDATE users SET status = 'active', email_verified_at = ?, updated_at = ? WHERE id = ? AND status = 'pending_verification'",
                [$now, $now, $userId]
            ) === 1;
        }
        return $this->db->execute(
            "UPDATE users SET status = 'active', email_verified_at = ?, password_hash = ?, updated_at = ? WHERE id = ? AND status = 'pending_verification'",
            [$now, $passwordHash, $now, $userId]
        ) === 1;
    }

    /** Mengganti password (hanya pengguna aktif). Hash baru sekaligus mencabut semua sesi lama (sidik jari di sesi). */
    public function updatePassword(int $userId, string $passwordHash, string $now): bool
    {
        return $this->db->execute(
            "UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ? AND status = 'active'",
            [$passwordHash, $now, $userId]
        ) === 1;
    }

    public function touchLastLogin(int $userId, string $now): void
    {
        $this->db->execute('UPDATE users SET last_login_at = ? WHERE id = ?', [$now, $userId]);
    }

    private static function isDuplicateEmail(PDOException $e): bool
    {
        $state = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $isUnique = $state === '23000' && in_array($driverCode, [1062, 19, 2067], true);
        return $isUnique && stripos($e->getMessage(), 'email') !== false;
    }
}
