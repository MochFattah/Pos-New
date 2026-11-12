<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Security\PasswordHasher;
use App\Modules\Auth\Services\AuthResult;
use App\Modules\Auth\Services\AuthService;
use Tests\Support\AuthHarness;

final class LoginTest extends AuthHarness
{
    private static function flatten(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    protected function makeHasher(): PasswordHasher
    {
        return new class extends PasswordHasher {
            public int $verifyCalls = 0;
            /** @var list<string> */
            public array $verified = [];

            public function verify(string $password, string $hash): bool
            {
                $this->verifyCalls++;
                $this->verified[] = $password;
                return parent::verify($password, $hash);
            }
        };
    }

    public function testWrongPasswordAndUnknownEmailGiveIdenticalResult(): void
    {
        $this->activeOwner();
        $wrong = $this->login('budi@example.com', 'salah-total');
        $unknown = $this->login('tidak-ada@example.com', 'salah-total');
        $this->assertSame($wrong->status(), $unknown->status());
        $this->assertSame(422, $wrong->status());
        $strip = static fn (string $html): string => (string) preg_replace('/(name="_csrf" value=")[^"]*|(name="email" [^>]*value=")[^"]*/', '$1$2', $html);
        $this->assertSame($strip($wrong->body()), $strip($unknown->body()));
        $this->assertContains('Email atau kata sandi salah.', $wrong->body());
        $this->assertTrue($this->auth->attempt('budi@example.com', 'x', '9.9.9.9', $this->session) instanceof AuthResult);
        $this->assertSame(AuthResult::FAILED, $this->auth->attempt('budi@example.com', 'x', '9.9.9.9', $this->session)->status);
        $this->assertSame(AuthResult::FAILED, $this->auth->attempt('nope@example.com', 'x', '9.9.9.9', $this->session)->status);
    }

    public function testSuccessfulLoginRedirectsAndStoresSessionIdentity(): void
    {
        $r = $this->activeOwner();
        $response = $this->login(' Budi@Example.com ');
        $this->assertSame(302, $response->status());
        $this->assertSame('/beranda', $response->header('Location'));
        $this->assertSame($r['userId'], $this->session->get(AuthService::SESSION_USER));
        $this->assertSame($r['tenantId'], $this->session->get(AuthService::SESSION_TENANT));
        $home = $this->send('GET', '/beranda');
        $this->assertSame(200, $home->status());
        $this->assertContains('Budi Santoso', $home->body());
        $this->assertTrue($this->db->fetchOne('SELECT last_login_at FROM users WHERE id = ?', [$r['userId']])['last_login_at'] !== null);
    }

    public function testSessionIdRegeneratedOnLogin(): void
    {
        $this->activeOwner();
        $this->send('GET', '/masuk');
        $before = $this->storage->id();
        $csrfBefore = $this->session->get('_csrf_token');
        $this->assertTrue($before !== '');
        $this->login();
        $this->assertTrue($this->storage->id() !== $before, 'ID sesi harus berubah setelah login');
        $this->assertTrue($this->session->get('_csrf_token') !== $csrfBefore, 'CSRF harus dirotasi');
    }

    public function testFailedLoginDoesNotRegenerateOrStoreIdentity(): void
    {
        $this->activeOwner();
        $this->login('budi@example.com', 'salah');
        $this->assertSame(null, $this->session->get(AuthService::SESSION_USER));
    }

    public function testSixthFailureWithinWindowIsLocked(): void
    {
        $this->activeOwner();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(422, $this->login('budi@example.com', 'salah-' . $i)->status());
        }
        $locked = $this->login('budi@example.com', 'salah-6');
        $this->assertSame(429, $locked->status());
        // Password benar pun ditolak selama terkunci.
        $this->assertSame(429, $this->login()->status());
        $this->assertSame(null, $this->session->get(AuthService::SESSION_USER));
        // Setelah jendela 15 menit lewat, bisa masuk lagi.
        $this->now += 901;
        $this->assertSame(302, $this->login()->status());
    }

    public function testSuccessfulLoginClearsFailureCounter(): void
    {
        $this->activeOwner();
        for ($i = 0; $i < 4; $i++) {
            $this->login('budi@example.com', 'salah');
        }
        $this->assertSame(302, $this->login()->status());
        $this->send('POST', '/keluar');
        for ($i = 0; $i < 4; $i++) {
            $this->assertSame(422, $this->login('budi@example.com', 'salah')->status());
        }
    }

    public function testPerIpAggregateCapBlocksPasswordSpraying(): void
    {
        $this->ipMax = 4;
        $this->wire();
        $this->activeOwner();
        // Email berbeda-beda dari IP yang sama: limiter per (email, IP) tidak pernah tercapai.
        foreach (['a', 'b', 'c', 'd'] as $n) {
            $this->assertSame(AuthResult::FAILED, $this->auth->attempt("$n@example.com", 'tebakan', '10.0.0.1', $this->session)->status);
        }
        $this->assertSame(AuthResult::LOCKED, $this->auth->attempt('e@example.com', 'tebakan', '10.0.0.1', $this->session)->status);
        $this->assertSame(AuthResult::LOCKED, $this->auth->attempt('budi@example.com', self::PASSWORD, '10.0.0.1', $this->session)->status);
        // IP lain tidak terpengaruh.
        $this->assertTrue($this->auth->attempt('budi@example.com', self::PASSWORD, '10.0.0.2', $this->session)->isOk());
    }

    public function testUnverifiedUserCannotLogin(): void
    {
        $this->pendingOwner();
        $response = $this->login('pending@example.com', self::PASSWORD);
        $this->assertSame(403, $response->status());
        $this->assertContains('/verifikasi-email', $response->body());
        $this->assertSame(null, $this->session->get(AuthService::SESSION_USER));
        $this->assertSame(AuthResult::UNVERIFIED, $this->auth->attempt('pending@example.com', self::PASSWORD, '1.1.1.1', $this->session)->status);
    }

    public function testUnverifiedStatusIsNotLeakedWithWrongPassword(): void
    {
        $this->pendingOwner();
        $wrong = $this->login('pending@example.com', 'salah-total');
        $this->assertSame(422, $wrong->status());
        $this->assertSame(AuthResult::FAILED, $this->auth->attempt('pending@example.com', 'salah-total', '1.1.1.1', $this->session)->status);
    }

    public function testUnknownEmailRunsDummyVerify(): void
    {
        $this->activeOwner();
        $h = $this->hasher;
        $before = $h->verifyCalls;
        $this->auth->attempt('hantu@example.com', 'apa-saja', '2.2.2.2', $this->session);
        $this->assertSame($before + 1, $h->verifyCalls, 'email tak dikenal harus tetap menjalankan password_verify');
    }

    public function testPasswordOver72BytesNeverLogsIn(): void
    {
        $long = str_repeat('a', 72);
        $this->activeOwner('panjang@example.com', $long);
        // Bcrypt memotong di 72 byte: tanpa penjagaan, password lebih panjang berawalan sama akan lolos.
        $this->assertSame(AuthResult::FAILED, $this->auth->attempt('panjang@example.com', $long . 'tambahan', '3.3.3.3', $this->session)->status);
        $this->assertSame(null, $this->session->get(AuthService::SESSION_USER));
        $this->assertTrue($this->auth->attempt('panjang@example.com', $long, '3.3.3.3', $this->session)->isOk());
    }

    public function testIdleTimeoutOfEightHoursEndsSession(): void
    {
        $this->activeOwner();
        $this->login();
        $this->now += 28800 - 1;
        $this->assertSame(200, $this->send('GET', '/beranda')->status());
        $this->now += 28800 + 1;
        $response = $this->send('GET', '/beranda');
        $this->assertSame(302, $response->status());
        $this->assertSame('/masuk', $response->header('Location'));
        $this->assertSame(null, $this->session->get(AuthService::SESSION_USER));
    }

    public function testLogoutDestroysSessionAndIsAudited(): void
    {
        $r = $this->activeOwner();
        $this->login();
        $response = $this->send('POST', '/keluar');
        $this->assertSame('/masuk', $response->header('Location'));
        $this->assertSame(null, $this->session->get(AuthService::SESSION_USER));
        $this->assertSame('/masuk', $this->send('GET', '/beranda')->header('Location'));
        $this->assertSame(1, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'auth.logout' AND actor_id = ?", [$r['userId']]));
    }

    public function testLogoutRequiresCsrfToken(): void
    {
        $this->activeOwner();
        $this->login();
        $this->assertSame(419, $this->send('POST', '/keluar', [], false)->status());
        $this->assertTrue($this->session->get(AuthService::SESSION_USER) !== null);
    }

    public function testLoginRequiresCsrfToken(): void
    {
        $this->activeOwner();
        $this->assertSame(419, $this->send('POST', '/masuk', ['email' => 'budi@example.com', 'password' => self::PASSWORD], false)->status());
    }

    public function testAuditLogRecordsLoginWithoutPassword(): void
    {
        $r = $this->activeOwner();
        $this->login('budi@example.com', 'salah-total');
        $this->login();
        $ok = $this->db->fetchOne("SELECT * FROM audit_logs WHERE action = 'auth.login'");
        $this->assertSame($r['userId'], (int) $ok['actor_id']);
        $this->assertSame($r['tenantId'], (int) $ok['tenant_id']);
        $failed = $this->db->fetchOne("SELECT * FROM audit_logs WHERE action = 'auth.login_failed'");
        $this->assertSame($r['userId'], (int) $failed['actor_id']);
        $dump = json_encode($this->db->query('SELECT * FROM audit_logs'), JSON_THROW_ON_ERROR);
        $this->assertFalse(str_contains($dump, self::PASSWORD));
        $this->assertFalse(str_contains($dump, 'salah-total'));
    }

    public function testFormEmptyFieldsGetAccessibleErrors(): void
    {
        $response = $this->send('POST', '/masuk', ['email' => '', 'password' => '']);
        $this->assertSame(422, $response->status());
        $this->assertContains('aria-describedby="email-error"', $response->body());
        $this->assertContains('aria-describedby="password-error"', $response->body());
        $this->assertContains('<label for="password">', $response->body());
    }

    public function testPasswordIsNeverEchoedBack(): void
    {
        $this->activeOwner();
        $response = $this->login('budi@example.com', 'rahasia-salah-xyz');
        $this->assertFalse(str_contains($response->body(), 'rahasia-salah-xyz'));
    }

    public function testPasswordWithEdgeSpacesIsNotTrimmed(): void
    {
        $this->activeOwner('spasi@example.com', ' spasi di tepi ');
        $this->assertSame(422, $this->login('spasi@example.com', 'spasi di tepi')->status());
        $this->assertSame(302, $this->login('spasi@example.com', ' spasi di tepi ')->status());
    }

    public function testLoginPageWhenAlreadySignedInRedirectsToHome(): void
    {
        $this->activeOwner();
        $this->login();
        $this->assertSame('/beranda', $this->send('GET', '/masuk')->header('Location'));
    }

    public function testInactiveUserCannotLoginEvenWithCorrectPassword(): void
    {
        $r = $this->activeOwner();
        $this->db->execute("UPDATE users SET status = 'inactive' WHERE id = ?", [$r['userId']]);
        $this->assertSame(422, $this->login()->status());
    }

    public function testDeactivatedUserLosesExistingSession(): void
    {
        $r = $this->activeOwner();
        $this->login();
        $this->db->execute("UPDATE users SET status = 'inactive' WHERE id = ?", [$r['userId']]);
        $this->assertSame('/masuk', $this->send('GET', '/beranda')->header('Location'));
    }

    public function testAuthUserCarriesRolesAndPermissions(): void
    {
        $r = $this->activeOwner();
        $roleId = (int) $this->db->fetchOne("SELECT id FROM roles WHERE tenant_id = ? AND slug = 'kasir'", [$r['tenantId']])['id'];
        $permId = (int) $this->db->fetchOne("SELECT id FROM permissions WHERE code = 'branches.view'")['id'];
        $this->db->execute('INSERT INTO user_roles (user_id, role_id, tenant_id) VALUES (?, ?, ?)', [$r['userId'], $roleId, $r['tenantId']]);
        $this->db->execute('INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)', [$roleId, $permId, $r['tenantId']]);
        $result = $this->auth->attempt('budi@example.com', self::PASSWORD, '4.4.4.4', $this->session);
        $user = $result->user;
        $this->assertTrue($user !== null && $user->isOwner);
        $this->assertSame('Budi Santoso', $user->name);
        $this->assertSame(2, count($user->roleIds));
        $this->assertSame(['branches.view'], $user->permissions);
    }

    public function testDummyHashIsPrecomputedBcryptWithCurrentCost(): void
    {
        $dummy = AuthService::DUMMY_HASH;
        $info = password_get_info($dummy);
        $this->assertSame('2y', $info['algo']);
        $fresh = password_get_info($this->hasher->hash('apa-saja'));
        $this->assertSame($fresh['options']['cost'], $info['options']['cost'], 'biaya sama dengan PasswordHasher');
        $this->assertFalse($this->hasher->needsRehash($dummy));
        $this->assertFalse(password_verify('', $dummy));
    }

    public function testUnknownEmailLoginNeverHashesOnlyVerifiesOnce(): void
    {
        $this->activeOwner();
        $hashing = new class extends PasswordHasher {
            public int $hashCalls = 0;
            public int $verifyCalls = 0;
            public function hash(string $password): string
            {
                $this->hashCalls++;
                return parent::hash($password);
            }
            public function verify(string $password, string $hash): bool
            {
                $this->verifyCalls++;
                return parent::verify($password, $hash);
            }
        };
        $auth = new AuthService($this->db, $this->users, new \App\Core\Security\RateLimiter($this->db, 5, 900), new \App\Core\Security\RateLimiter($this->db, 50, 900), $hashing, $this->makeAudit());
        $auth->attempt('hantu@example.com', 'x', '8.8.8.8', $this->session);
        $this->assertSame(0, $hashing->hashCalls);
        $this->assertSame(1, $hashing->verifyCalls);
    }

    public function testAuthPagesAreNotCachedAndSendNoReferrer(): void
    {
        $form = $this->send('GET', '/masuk');
        $this->assertSame('no-store', $form->header('Cache-Control'));
        $this->assertSame('no-referrer', $form->header('Referrer-Policy'));
        $this->activeOwner();
        $bad = $this->login('budi@example.com', 'salah');
        $this->assertSame('no-store', $bad->header('Cache-Control'));
        $this->assertSame('no-referrer', $bad->header('Referrer-Policy'));
    }
}
