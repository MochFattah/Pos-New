<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Core\Database\Connection;

final class PasswordResetRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function create(int $userId, string $tokenHash, string $expiresAt, ?string $ip, string $now): void
    {
        $this->db->execute(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, ip, created_at) VALUES (?, ?, ?, ?, ?)',
            [$userId, $tokenHash, $expiresAt, $ip, $now]
        );
    }

    /** @return array<string, mixed>|null */
    public function findByHash(string $tokenHash): ?array
    {
        return $this->db->fetchOne('SELECT * FROM password_resets WHERE token_hash = ?', [$tokenHash]);
    }

    /** Penjaga satu kali pakai: hanya satu pemanggil yang mendapat true. */
    public function markUsed(int $id, string $now): bool
    {
        return $this->db->execute(
            'UPDATE password_resets SET used_at = ? WHERE id = ? AND used_at IS NULL',
            [$now, $id]
        ) === 1;
    }

    /** Membatalkan semua token pengguna yang belum dipakai (token baru menggantikan yang lama). */
    public function invalidateForUser(int $userId, string $now): void
    {
        $this->db->execute(
            'UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL',
            [$now, $userId]
        );
    }
}
