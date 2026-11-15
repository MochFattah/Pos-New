<?php

declare(strict_types=1);

namespace App\Modules\Platform\Repositories;

use App\Core\Database\Connection;
use App\Core\Validation\Validator;

/** platform_users bukan tabel tenant (tanpa tenant_id): akun pemilik platform, terpisah dari users. */
class PlatformUserRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchOne('SELECT * FROM platform_users WHERE email = ?', [Validator::normalizeEmail($email)]);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM platform_users WHERE id = ?', [$id]);
    }

    /** Email wajib sudah dinormalisasi pemanggil. */
    public function create(string $name, string $email, string $passwordHash, string $now): int
    {
        $this->db->execute(
            "INSERT INTO platform_users (name, email, password_hash, status, created_at, updated_at) VALUES (?, ?, ?, 'active', ?, ?)",
            [$name, $email, $passwordHash, $now, $now]
        );
        return $this->db->lastInsertId();
    }

    public function touchLastLogin(int $id, string $now): void
    {
        $this->db->execute('UPDATE platform_users SET last_login_at = ? WHERE id = ?', [$now, $id]);
    }
}
