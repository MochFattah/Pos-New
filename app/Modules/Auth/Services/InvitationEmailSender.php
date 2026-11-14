<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\Mail\MailerInterface;
use App\Core\Security\TokenService;
use App\Modules\Auth\Repositories\EmailVerificationRepository;
use Closure;

/**
 * Email undangan pengguna: memakai tabel email_verifications (hanya hash token disimpan) dan tautan
 * /undangan/{token} (halaman penetapan kata sandi). Token lama yang belum dipakai dibatalkan lebih dulu,
 * sehingga kirim ulang menggantikan undangan sebelumnya. MailException dibiarkan naik.
 */
final class InvitationEmailSender
{
    private Closure $clock;

    /** @param (callable(): int)|null $clock */
    public function __construct(
        private readonly EmailVerificationRepository $repository,
        private readonly TokenService $tokens,
        private readonly MailerInterface $mailer,
        private readonly string $appUrl,
        private readonly int $ttlSeconds,
        ?callable $clock = null,
        private readonly bool $requirePublicUrl = false,
    ) {
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    public function send(int $userId, string $email, string $name): void
    {
        $this->assertPublicUrl();
        $name = trim((string) preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}\s]+/u', ' ', $name));
        $now = ($this->clock)();
        $nowText = gmdate('Y-m-d H:i:s', $now);
        $this->repository->invalidateForUser($userId, $nowText);
        $token = $this->tokens->generate();
        $this->repository->create($userId, $token['hash'], gmdate('Y-m-d H:i:s', $now + $this->ttlSeconds), $nowText);
        $link = rtrim($this->appUrl, '/') . '/undangan/' . $token['plain'];
        $hours = max(1, intdiv($this->ttlSeconds, 3600));
        $body = "Halo {$name},\n\n"
            . "Anda diundang untuk bergabung di POS Retail. Buka tautan berikut untuk menetapkan kata sandi dan mengaktifkan akun Anda:\n\n"
            . $link . "\n\n"
            . "Tautan berlaku selama {$hours} jam dan hanya dapat dipakai sekali.\n"
            . "Jika Anda tidak merasa diundang, abaikan email ini.\n";
        $this->mailer->send($email, 'Undangan bergabung di POS Retail', $body);
    }

    private function assertPublicUrl(): void
    {
        if (!$this->requirePublicUrl) {
            return;
        }
        $host = strtolower((string) parse_url($this->appUrl, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]' || str_ends_with($host, '.localhost')) {
            throw new \RuntimeException('APP_URL belum diatur ke alamat publik; tautan undangan tidak dapat dibuat di production.');
        }
    }
}
