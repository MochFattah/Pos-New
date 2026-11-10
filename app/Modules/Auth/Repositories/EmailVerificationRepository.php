<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Core\Database\Connection;

final class EmailVerificationRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function create(int $userId, string $tokenHash, string $expiresAt, string $now): void
    {
        $this->db->execute(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
            [$userId, $tokenHash, $expiresAt, $now]
        );
    }

    /** @return array<string, mixed>|null */
    public function findByHash(string $tokenHash): ?array
    {
        return $this->db->fetchOne('SELECT * FROM email_verifications WHERE token_hash = ?', [$tokenHash]);
    }

    /** Penjaga satu kali pakai: hanya satu pemanggil yang mendapat true. */
    public function markUsed(int $id, string $now): bool
    {
        return $this->db->execute(
            'UPDATE email_verifications SET used_at = ? WHERE id = ? AND used_at IS NULL',
            [$now, $id]
        ) === 1;
    }

    /** Membatalkan token lama yang belum dipakai dengan mempercepat kedaluwarsanya ke sekarang. */
    public function invalidateForUser(int $userId, string $now): void
    {
        $this->db->execute(
            'UPDATE email_verifications SET expires_at = ? WHERE user_id = ? AND used_at IS NULL AND expires_at > ?',
            [$now, $userId, $now]
        );
    }
}
