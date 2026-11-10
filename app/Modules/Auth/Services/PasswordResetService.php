<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\Database\Connection;
use App\Core\Mail\MailerInterface;
use App\Core\Security\PasswordHasher;
use App\Core\Security\TokenService;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Auth\Repositories\PasswordResetRepository;
use App\Modules\Tenants\Repositories\UserRepository;
use Closure;

final class PasswordResetService
{
    public const MIN_PASSWORD_LENGTH = 8;
    public const MAX_PASSWORD_BYTES = 72;

    private Closure $clock;

    /** @param (callable(): int)|null $clock */
    public function __construct(
        private readonly Connection $db,
        private readonly PasswordResetRepository $resets,
        private readonly UserRepository $users,
        private readonly TokenService $tokens,
        private readonly PasswordHasher $hasher,
        private readonly MailerInterface $mailer,
        private readonly AuditLogger $audit,
        private readonly string $appUrl,
        private readonly int $ttlSeconds = 3600,
        ?callable $clock = null,
        private readonly bool $requirePublicUrl = false,
    ) {
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    /**
     * Selalu diam-diam sukses untuk email tak dikenal/tak aktif. Hanya pengguna aktif yang menerima
     * tautan: pengguna pending_verification tidak boleh melewati verifikasi lewat reset.
     * MailException dari pengiriman dibiarkan naik (pemanggil menelannya agar respons tetap netral).
     */
    public function request(string $email, string $ip = ''): void
    {
        // Sebelum mencari pengguna: salah konfigurasi harus gagal sama untuk semua email (tanpa bocor keberadaan akun).
        $this->assertPublicUrl();
        $user = $this->users->findByEmail($email);
        if ($user === null || $user['status'] !== 'active') {
            return;
        }
        $nowTs = ($this->clock)();
        $now = gmdate('Y-m-d H:i:s', $nowTs);
        $this->db->transaction(function () use ($user, $nowTs, $now, $ip): void {
            $this->resets->invalidateForUser((int) $user['id'], $now);
            $token = $this->tokens->generate();
            $this->resets->create((int) $user['id'], $token['hash'], gmdate('Y-m-d H:i:s', $nowTs + $this->ttlSeconds), $ip !== '' ? $ip : null, $now);
            $this->audit->record(
                'auth.password_reset_requested',
                (int) $user['tenant_id'],
                AuditLogger::ACTOR_TENANT_USER,
                (int) $user['id'],
                'user',
                (int) $user['id'],
                ['requested_from_ip' => $ip]
            );
            $name = trim((string) preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}\s]+/u', ' ', (string) $user['name']));
            $minutes = max(1, intdiv($this->ttlSeconds, 60));
            $body = "Halo {$name},\n\n"
                . "Kami menerima permintaan untuk mengatur ulang kata sandi akun POS Retail Anda. Buka tautan berikut:\n\n"
                . rtrim($this->appUrl, '/') . '/reset-password/' . $token['plain'] . "\n\n"
                . "Tautan berlaku selama {$minutes} menit dan hanya dapat dipakai sekali.\n"
                . "Jika Anda tidak memintanya, abaikan email ini; kata sandi Anda tidak berubah.\n";
            $this->mailer->send((string) $user['email'], 'Atur ulang kata sandi POS Retail', $body);
        });
    }

    /** Token masih bisa dipakai (untuk menampilkan formulir); tidak mengubah apa pun. */
    public function isValidToken(string $plainToken): bool
    {
        return $this->findUsable($plainToken) !== null;
    }

    /**
     * Mengganti password, sekali pakai. Password hash baru mencabut semua sesi pengguna itu.
     * Tidak mengaktifkan pengguna pending_verification.
     */
    public function reset(string $plainToken, string $newPassword): bool
    {
        if (mb_strlen($newPassword) < self::MIN_PASSWORD_LENGTH || strlen($newPassword) > self::MAX_PASSWORD_BYTES) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s', ($this->clock)());

        return (bool) $this->db->transaction(function () use ($plainToken, $newPassword, $now): bool {
            $found = $this->findUsable($plainToken);
            if ($found === null) {
                return false;
            }
            [$row, $user] = $found;
            if (!$this->resets->markUsed((int) $row['id'], $now)) {
                return false;
            }
            if (!$this->users->updatePassword((int) $user['id'], $this->hasher->hash($newPassword), $now)) {
                return false;
            }
            $this->resets->invalidateForUser((int) $user['id'], $now);
            $this->audit->record(
                'auth.password_reset',
                (int) $user['tenant_id'],
                AuditLogger::ACTOR_TENANT_USER,
                (int) $user['id'],
                'user',
                (int) $user['id']
            );
            return true;
        });
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>}|null */
    private function findUsable(string $plainToken): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $plainToken) !== 1) {
            return null;
        }
        $now = gmdate('Y-m-d H:i:s', ($this->clock)());
        $row = $this->resets->findByHash($this->tokens->hash($plainToken));
        if ($row === null || $row['used_at'] !== null || (string) $row['expires_at'] <= $now) {
            return null;
        }
        $user = $this->users->findByIdAnyTenant((int) $row['user_id']);
        if ($user === null || $user['status'] !== 'active') {
            return null;
        }
        return [$row, $user];
    }

    private function assertPublicUrl(): void
    {
        if (!$this->requirePublicUrl) {
            return;
        }
        $host = strtolower((string) parse_url($this->appUrl, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]' || str_ends_with($host, '.localhost')) {
            throw new \RuntimeException('APP_URL belum diatur ke alamat publik; tautan reset tidak dapat dibuat di production.');
        }
    }
}
