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

    /** Jumlah pengguna non-Owner yang masih menunggu undangan diterima di tenant ini. */
    public function countPendingNonOwner(int $tenantId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM users WHERE tenant_id = ? AND status = 'pending_verification' AND is_owner = 0",
            [$tenantId]
        );
        return (int) ($row['n'] ?? 0);
    }

    /**
     * Membebaskan email dari baris undangan basi: status pending_verification, bukan Owner, dibuat sebelum
     * $createdBefore, dan tanpa token verifikasi yang masih berlaku (belum dipakai dan belum kedaluwarsa).
     * Semua syarat dicek atomik di SQL; dipanggil di dalam transaksi pemanggil. Token, role, dan akses cabang
     * baris itu ikut dihapus dengan tenant_id baris itu sendiri. Mengembalikan [id, tenant_id] baris yang
     * dihapus, atau null bila email tidak ada / tidak memenuhi syarat.
     *
     * @return array{id: int, tenant_id: int}|null
     */
    public function reclaimStalePending(string $email, string $now, string $createdBefore): ?array
    {
        $row = $this->findByEmail($email);
        if ($row === null) {
            return null;
        }
        $id = (int) $row['id'];
        $tenantId = (int) $row['tenant_id'];
        $params = [$id, $tenantId, $createdBefore, $now];
        $eligible = $this->db->fetchOne(
            "SELECT id FROM users WHERE id = ? AND tenant_id = ? AND status = 'pending_verification' AND is_owner = 0 AND created_at < ?
             AND NOT EXISTS (SELECT 1 FROM email_verifications v WHERE v.user_id = users.id AND v.used_at IS NULL AND v.expires_at > ?)",
            $params
        );
        if ($eligible === null) {
            return null;
        }
        $this->db->execute('DELETE FROM user_branch_access WHERE tenant_id = ? AND user_id = ?', [$tenantId, $id]);
        $this->db->execute('DELETE FROM user_roles WHERE tenant_id = ? AND user_id = ?', [$tenantId, $id]);
        // Hanya token terpakai/kedaluwarsa; token valid (mis. hasil kirim ulang bersamaan) membuat guard akhir gagal -> rollback.
        $this->db->execute('DELETE FROM email_verifications WHERE user_id = ? AND (used_at IS NOT NULL OR expires_at <= ?)', [$id, $now]);
        $this->db->execute('DELETE FROM password_resets WHERE user_id = ?', [$id]);
        $deleted = $this->db->execute(
            "DELETE FROM users WHERE id = ? AND tenant_id = ? AND status = 'pending_verification' AND is_owner = 0 AND created_at < ?
             AND NOT EXISTS (SELECT 1 FROM email_verifications v WHERE v.user_id = users.id AND v.used_at IS NULL AND v.expires_at > ?)",
            $params
        );
        return $deleted === 1 ? ['id' => $id, 'tenant_id' => $tenantId] : null;
    }

    public function activate(int $tenantId, int $userId, string $now, ?string $passwordHash = null): bool
    {
        if ($passwordHash === null) {
            return $this->db->execute(
                "UPDATE users SET status = 'active', email_verified_at = ?, updated_at = ? WHERE tenant_id = ? AND id = ? AND status = 'pending_verification'",
                [$now, $now, $tenantId, $userId]
            ) === 1;
        }
        return $this->db->execute(
            "UPDATE users SET status = 'active', email_verified_at = ?, password_hash = ?, updated_at = ? WHERE tenant_id = ? AND id = ? AND status = 'pending_verification'",
            [$now, $passwordHash, $now, $tenantId, $userId]
        ) === 1;
    }

    /** Mengganti password (hanya pengguna aktif). Hash baru sekaligus mencabut semua sesi lama (sidik jari di sesi). */
    public function updatePassword(int $tenantId, int $userId, string $passwordHash, string $now): bool
    {
        return $this->db->execute(
            "UPDATE users SET password_hash = ?, updated_at = ? WHERE tenant_id = ? AND id = ? AND status = 'active'",
            [$passwordHash, $now, $tenantId, $userId]
        ) === 1;
    }

    /**
     * Menyimpan ulang hash (biaya baru) hanya bila hash di DB masih sama dengan yang diverifikasi saat login,
     * sehingga reset password yang berjalan bersamaan tidak tertimpa. False bila hash sudah berubah.
     */
    public function rehashPassword(int $tenantId, int $userId, string $oldHash, string $newHash, string $now): bool
    {
        return $this->db->execute(
            "UPDATE users SET password_hash = ?, updated_at = ? WHERE tenant_id = ? AND id = ? AND status = 'active' AND password_hash = ?",
            [$newHash, $now, $tenantId, $userId, $oldHash]
        ) === 1;
    }

    public function touchLastLogin(int $tenantId, int $userId, string $now): void
    {
        $this->db->execute('UPDATE users SET last_login_at = ? WHERE tenant_id = ? AND id = ?', [$now, $tenantId, $userId]);
    }

    /** @return list<array<string, mixed>> */
    public function listByTenant(int $tenantId): array
    {
        return $this->db->query(
            'SELECT id, name, email, phone, status, is_owner, last_login_at FROM users WHERE tenant_id = ? ORDER BY is_owner DESC, name ASC, id ASC',
            [$tenantId]
        );
    }

    /** Hanya nama dan telepon; email, status, is_owner, dan tenant tidak pernah diubah dari sini. */
    public function updateProfile(int $tenantId, int $userId, string $name, ?string $phone, string $now): bool
    {
        return $this->db->execute(
            'UPDATE users SET name = ?, phone = ?, updated_at = ? WHERE tenant_id = ? AND id = ?',
            [$name, $phone, $now, $tenantId, $userId]
        ) === 1;
    }

    /**
     * Pindah antara active dan inactive (inactive juga dari pending_verification = mencabut undangan).
     * Penjaga ada di SQL: Owner tidak pernah bisa dinonaktifkan dan akun yang belum pernah
     * terverifikasi tidak bisa diaktifkan. Mengembalikan true hanya bila baris berubah.
     */
    public function setStatus(int $tenantId, int $userId, string $status, string $now): bool
    {
        if ($status === 'inactive') {
            return $this->db->execute(
                "UPDATE users SET status = 'inactive', updated_at = ? WHERE tenant_id = ? AND id = ? AND is_owner = 0 AND status <> 'inactive'",
                [$now, $tenantId, $userId]
            ) === 1;
        }
        if ($status === 'active') {
            return $this->db->execute(
                "UPDATE users SET status = 'active', updated_at = ? WHERE tenant_id = ? AND id = ? AND status = 'inactive' AND email_verified_at IS NOT NULL",
                [$now, $tenantId, $userId]
            ) === 1;
        }
        throw new \InvalidArgumentException('Status tidak didukung: ' . $status);
    }

    /** @return list<int> */
    public function branchIds(int $tenantId, int $userId): array
    {
        $rows = $this->db->query(
            'SELECT branch_id FROM user_branch_access WHERE tenant_id = ? AND user_id = ? ORDER BY branch_id',
            [$tenantId, $userId]
        );
        return array_map(static fn (array $r): int => (int) $r['branch_id'], $rows);
    }

    public function addBranch(int $tenantId, int $userId, int $branchId): void
    {
        $this->db->execute(
            'INSERT INTO user_branch_access (user_id, branch_id, tenant_id) VALUES (?, ?, ?)',
            [$userId, $branchId, $tenantId]
        );
    }

    public function removeBranch(int $tenantId, int $userId, int $branchId): void
    {
        $this->db->execute(
            'DELETE FROM user_branch_access WHERE tenant_id = ? AND user_id = ? AND branch_id = ?',
            [$tenantId, $userId, $branchId]
        );
    }

    private static function isDuplicateEmail(PDOException $e): bool
    {
        $state = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $isUnique = $state === '23000' && in_array($driverCode, [1062, 19, 2067], true);
        return $isUnique && stripos($e->getMessage(), 'email') !== false;
    }
}
