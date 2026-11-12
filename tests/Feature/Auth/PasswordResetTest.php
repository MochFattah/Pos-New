<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Mail\MailException;
use App\Modules\Auth\Services\AuthService;
use Tests\Support\AuthHarness;

final class PasswordResetTest extends AuthHarness
{
    private const NEW_PASSWORD = 'kata-sandi-baru-1';

    private function requestReset(string $email = 'budi@example.com'): void
    {
        $this->assertSame(200, $this->send('POST', '/lupa-password', ['email' => $email])->status());
    }

    public function testResetRequestForUnknownEmailLooksSuccessful(): void
    {
        $this->activeOwner();
        $known = $this->send('POST', '/lupa-password', ['email' => 'budi@example.com']);
        $mailsAfterKnown = count($this->mailer->sent);
        $unknown = $this->send('POST', '/lupa-password', ['email' => 'hantu@example.com']);
        $this->assertSame($known->status(), $unknown->status());
        $strip = static fn (string $h): string => (string) preg_replace('/name="_csrf" value="[^"]*"/', '', $h);
        $this->assertSame($strip($known->body()), $strip($unknown->body()));
        $this->assertSame(1, $mailsAfterKnown - 1, 'email terdaftar menerima tepat satu email reset');
        $this->assertSame($mailsAfterKnown, count($this->mailer->sent), 'email tak dikenal tidak mengirim apa pun');
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM password_resets'));
    }

    public function testResetTokenIsSingleUseAndExpires60Minutes(): void
    {
        $this->activeOwner();
        $this->requestReset();
        $token = $this->mailer->lastResetToken();
        $row = $this->db->fetchOne('SELECT * FROM password_resets');
        $this->assertSame(hash('sha256', $token), $row['token_hash']);
        $this->assertSame(gmdate('Y-m-d H:i:s', $this->now + 3600), $row['expires_at']);
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM password_resets WHERE token_hash = ?', [$token]), 'token polos tidak disimpan');

        $this->assertTrue($this->resets->reset($token, self::NEW_PASSWORD));
        $this->assertFalse($this->resets->reset($token, 'kata-sandi-lain-2'));
        $this->assertSame(302, $this->login('budi@example.com', self::NEW_PASSWORD)->status());

        $this->send('POST', '/keluar');
        $this->requestReset();
        $expired = $this->mailer->lastResetToken();
        $this->now += 3600;
        $this->assertFalse($this->resets->reset($expired, 'kata-sandi-lain-3'));
        $this->assertSame(404, $this->send('GET', '/reset-password/' . $expired)->status());
    }

    public function testTokenAtFiftyNineMinutesStillWorks(): void
    {
        $this->activeOwner();
        $this->requestReset();
        $token = $this->mailer->lastResetToken();
        $this->now += 3599;
        $this->assertTrue($this->resets->reset($token, self::NEW_PASSWORD));
    }

    public function testResetRevokesExistingSessions(): void
    {
        $this->activeOwner();
        $this->login();
        $this->assertSame(200, $this->send('GET', '/beranda')->status());
        $this->requestReset();
        $this->assertTrue($this->resets->reset($this->mailer->lastResetToken(), self::NEW_PASSWORD));
        $response = $this->send('GET', '/beranda');
        $this->assertSame(302, $response->status());
        $this->assertSame('/masuk', $response->header('Location'));
        $this->assertSame(null, $this->session->get(AuthService::SESSION_USER));
    }

    public function testOldPasswordStopsWorkingAfterReset(): void
    {
        $this->activeOwner();
        $this->requestReset();
        $this->resets->reset($this->mailer->lastResetToken(), self::NEW_PASSWORD);
        $this->assertSame(422, $this->login('budi@example.com', self::PASSWORD)->status());
    }

    public function testResetDoesNotActivatePendingUser(): void
    {
        $r = $this->pendingOwner();
        $sentBefore = count($this->mailer->sent);
        $this->requestReset('pending@example.com');
        $this->assertSame($sentBefore, count($this->mailer->sent), 'pengguna pending tidak dikirimi tautan reset');
        // Token reset yang (hipotetis) sudah ada untuk pengguna pending tidak boleh mengaktifkannya.
        $hash = hash('sha256', str_repeat('a', 64));
        $this->db->execute(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
            [$r['userId'], $hash, gmdate('Y-m-d H:i:s', $this->now + 600), gmdate('Y-m-d H:i:s', $this->now)]
        );
        $this->assertFalse($this->resets->reset(str_repeat('a', 64), self::NEW_PASSWORD));
        $user = $this->db->fetchOne('SELECT status, email_verified_at FROM users WHERE id = ?', [$r['userId']]);
        $this->assertSame('pending_verification', $user['status']);
        $this->assertSame(null, $user['email_verified_at']);
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM password_resets WHERE used_at IS NOT NULL'));
        $this->assertSame(0, $this->count("SELECT COUNT(*) AS n FROM tenants WHERE status = 'active'"));
    }

    public function testNewRequestInvalidatesPreviousToken(): void
    {
        $this->activeOwner();
        $this->requestReset();
        $first = $this->mailer->lastResetToken();
        $this->now += 5;
        $this->requestReset();
        $second = $this->mailer->lastResetToken();
        $this->assertTrue($first !== $second);
        $this->assertFalse($this->resets->reset($first, self::NEW_PASSWORD));
        $this->assertTrue($this->resets->reset($second, self::NEW_PASSWORD));
    }

    public function testResetRejectsPasswordOver72BytesAndTooShort(): void
    {
        $this->activeOwner();
        $this->requestReset();
        $token = $this->mailer->lastResetToken();
        $this->assertFalse($this->resets->reset($token, str_repeat('a', 73)));
        $this->assertFalse($this->resets->reset($token, 'pendek'));
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM password_resets WHERE used_at IS NOT NULL'));
        $page = $this->send('POST', '/reset-password/' . $token, ['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)]);
        $this->assertSame(422, $page->status());
        $this->assertContains('aria-describedby="password-hint password-error"', $page->body());
        $this->assertTrue($this->resets->reset($token, str_repeat('a', 72)), 'token tidak terbakar oleh percobaan yang ditolak');
    }

    public function testRandomOrMalformedTokensAreNeutral(): void
    {
        $this->activeOwner();
        $this->assertSame(404, $this->send('GET', '/reset-password/' . str_repeat('0', 64))->status());
        $this->assertSame(404, $this->send('GET', '/reset-password/bukan-token')->status());
        $post = $this->send('POST', '/reset-password/' . str_repeat('0', 64), ['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD]);
        $this->assertSame(404, $post->status());
    }

    public function testResetFormFlowEndToEnd(): void
    {
        $this->activeOwner();
        $this->requestReset();
        $token = $this->mailer->lastResetToken();
        $form = $this->send('GET', '/reset-password/' . $token);
        $this->assertSame(200, $form->status());
        $this->assertContains('<label for="password_confirmation">', $form->body());
        $mismatch = $this->send('POST', '/reset-password/' . $token, ['password' => self::NEW_PASSWORD, 'password_confirmation' => 'beda-sekali-1']);
        $this->assertSame(422, $mismatch->status());
        $ok = $this->send('POST', '/reset-password/' . $token, ['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD]);
        $this->assertSame('/masuk', $ok->header('Location'));
        $this->assertContains('Kata sandi berhasil diubah', $this->send('GET', '/masuk')->body());
    }

    public function testResetRequestIsRateLimitedPerEmailAndIp(): void
    {
        $this->activeOwner();
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(200, $this->send('POST', '/lupa-password', ['email' => 'budi@example.com'])->status());
        }
        $this->assertSame(429, $this->send('POST', '/lupa-password', ['email' => 'budi@example.com'])->status());
    }

    public function testMailFailureStaysNeutral(): void
    {
        $this->activeOwner();
        $this->mailer->fail = true;
        $this->assertSame(200, $this->send('POST', '/lupa-password', ['email' => 'budi@example.com'])->status());
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM password_resets'), 'token sudah commit sebelum email dikirim');
        $this->resets->request('budi@example.com'); // kegagalan kirim ditelan, tidak melempar
    }

    public function testRequestAndResetAreAudited(): void
    {
        $r = $this->activeOwner();
        $this->requestReset();
        $token = $this->mailer->lastResetToken();
        $this->resets->reset($token, self::NEW_PASSWORD);
        $this->assertSame(1, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'auth.password_reset_requested' AND actor_id = ?", [$r['userId']]));
        $this->assertSame(1, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'auth.password_reset' AND actor_id = ?", [$r['userId']]));
        $dump = json_encode($this->db->query('SELECT * FROM audit_logs'), JSON_THROW_ON_ERROR);
        $this->assertFalse(str_contains($dump, $token));
        $this->assertFalse(str_contains($dump, self::NEW_PASSWORD));
    }

    public function testProductionGuardRejectsLocalhostAppUrlForEveryEmail(): void
    {
        $this->activeOwner();
        $service = new \App\Modules\Auth\Services\PasswordResetService(
            $this->db,
            new \App\Modules\Auth\Repositories\PasswordResetRepository($this->db),
            $this->users,
            new \App\Core\Security\TokenService(),
            $this->hasher,
            $this->mailer,
            new \App\Modules\AuditLogs\Services\AuditLogger(new \App\Modules\AuditLogs\Repositories\AuditLogRepository($this->db), static fn (): array => []),
            'http://localhost:8000',
            3600,
            null,
            true
        );
        $this->assertThrows(\RuntimeException::class, fn () => $service->request('budi@example.com'));
        $this->assertThrows(\RuntimeException::class, fn () => $service->request('hantu@example.com'));
    }

    public function testLoginFailureDoesNotReduceResetCap(): void
    {
        $this->activeOwner();
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(200, $this->send('POST', '/lupa-password', ['email' => 'budi@example.com'])->status());
        }
        $this->now += 1000; // jendela login 900 s lewat, jendela reset 3600 s belum
        $this->login('budi@example.com', 'salah-total');
        $this->assertSame(429, $this->send('POST', '/lupa-password', ['email' => 'budi@example.com'])->status());
    }

    public function testMailIsSentAfterCommitAndFailureKeepsTokenAndStaysNeutral(): void
    {
        $this->activeOwner();
        $probe = new class ($this->db) implements \App\Core\Mail\MailerInterface {
            public ?bool $inTx = null;
            public function __construct(private \App\Core\Database\Connection $conn)
            {
            }
            public function send(string $to, string $subject, string $textBody): void
            {
                $this->inTx = $this->conn->inTransaction();
                throw new MailException('smtp down');
            }
        };
        $mailer = $probe;
        $svc = new \App\Modules\Auth\Services\PasswordResetService(
            $this->db, new \App\Modules\Auth\Repositories\PasswordResetRepository($this->db), $this->users,
            new \App\Core\Security\TokenService(), $this->hasher, $mailer,
            $this->makeAudit(),
            self::APP_URL, 3600, fn (): int => $this->now
        );
        $svc->request('budi@example.com'); // tidak melempar
        $this->assertSame(false, $probe->inTx, 'email dikirim setelah commit');
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM password_resets'));
    }

    public function testResetRejectsNulByteInNewPasswordWithoutBurningToken(): void
    {
        $this->activeOwner();
        $this->requestReset();
        $token = $this->mailer->lastResetToken();
        $this->assertFalse($this->resets->reset($token, "abcd\0efghij"));
        $page = $this->send('POST', '/reset-password/' . $token, ['password' => "abcd\0efghij", 'password_confirmation' => "abcd\0efghij"]);
        $this->assertSame(422, $page->status());
        $this->assertTrue($this->resets->reset($token, self::NEW_PASSWORD));
    }

    public function testResetPagesAreNotCachedAndSendNoReferrer(): void
    {
        $this->activeOwner();
        $this->requestReset();
        $token = $this->mailer->lastResetToken();
        foreach ([
            $this->send('GET', '/lupa-password'),
            $this->send('GET', '/reset-password/' . $token),
            $this->send('GET', '/reset-password/' . str_repeat('0', 64)),
            $this->send('POST', '/reset-password/' . $token, ['password' => 'x', 'password_confirmation' => 'x']),
        ] as $res) {
            $this->assertSame('no-store', $res->header('Cache-Control'));
            $this->assertSame('no-referrer', $res->header('Referrer-Policy'));
        }
    }

    public function testResetFormOn422RevalidatesRouteToken(): void
    {
        $this->activeOwner();
        $res = $this->send('POST', '/reset-password/' . str_repeat('0', 64), ['password' => 'x', 'password_confirmation' => 'x']);
        $this->assertSame(404, $res->status());
        $this->assertFalse(str_contains($res->body(), str_repeat('0', 64)));
    }
}
