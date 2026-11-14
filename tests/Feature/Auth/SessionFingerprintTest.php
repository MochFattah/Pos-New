<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Tests\Support\AuthHarness;

final class SessionFingerprintTest extends AuthHarness
{
    public function testRefreshKeepsCurrentSessionAfterInSessionPasswordChange(): void
    {
        $r = $this->activeOwner();
        $this->assertSame(302, $this->login()->status());
        $this->assertTrue($this->auth->userFromSession($this->session) !== null);

        $this->users->updatePassword($r['tenantId'], $r['userId'], $this->hasher->hash('sandi-baru-12345'), '2027-01-01 00:00:00');
        $this->assertTrue($this->auth->userFromSession($this->session) === null, 'tanpa refresh, sesi lama tercabut');
    }

    public function testRefreshSessionFingerprintReKeysOnlyTheGivenSession(): void
    {
        $r = $this->activeOwner();
        $this->assertSame(302, $this->login()->status());
        $other = new \App\Core\Security\Session(new \App\Core\Security\ArraySessionStorage(), fn (): int => $this->now);
        foreach ([AuthServiceKeys::USER => $r['userId'], AuthServiceKeys::TENANT => $r['tenantId'], AuthServiceKeys::FP => $this->session->get(AuthServiceKeys::FP)] as $k => $v) {
            $other->set($k, $v);
        }
        $this->assertTrue($this->auth->userFromSession($other) !== null);

        $this->users->updatePassword($r['tenantId'], $r['userId'], $this->hasher->hash('sandi-baru-12345'), '2027-01-01 00:00:00');
        $this->auth->refreshSessionFingerprint($this->session);

        $this->assertTrue($this->auth->userFromSession($this->session) !== null, 'sesi yang mengganti password tetap masuk');
        $this->assertTrue($this->auth->userFromSession($other) === null, 'sesi lain tercabut');
    }

    public function testRefreshWithoutLoginDoesNothing(): void
    {
        $this->activeOwner();
        $this->auth->refreshSessionFingerprint($this->session);
        $this->assertTrue($this->auth->userFromSession($this->session) === null);
        $this->assertSame(null, $this->session->get(AuthServiceKeys::FP));
    }
}

final class AuthServiceKeys
{
    public const USER = \App\Modules\Auth\Services\AuthService::SESSION_USER;
    public const TENANT = \App\Modules\Auth\Services\AuthService::SESSION_TENANT;
    public const FP = \App\Modules\Auth\Services\AuthService::SESSION_FINGERPRINT;
}
