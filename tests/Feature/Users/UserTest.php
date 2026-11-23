<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Modules\Tenants\Repositories\UserRepository;
use Tests\Support\UserHarness;

final class UserTest extends UserHarness
{
    private const INVITEE_PASSWORD = 'sandi-undangan-1';

    /** @return array{tenantId: int, owner: \App\Core\Authorization\AuthUser, ownerId: int} */
    private function tenant(string $email = 'budi@example.com'): array
    {
        $r = $this->activeOwner($email);
        return ['tenantId' => $r['tenantId'], 'ownerId' => $r['userId'], 'owner' => $this->authUserFor($r['tenantId'], $r['userId'])];
    }

    private function invite(array $t, string $email = 'staf@example.com', string $name = 'Siti Staf'): int
    {
        return $this->userService->invite($t['owner'], ['name' => $name, 'email' => $email, 'phone' => '']);
    }

    // --- Undangan ---

    public function testInviteCreatesPendingNonOwnerUserAndSendsEmail(): void
    {
        $t = $this->tenant();
        $id = $this->invite($t, '  Staf@Example.COM ');
        $row = $this->users->findById($t['tenantId'], $id);
        $this->assertSame('pending_verification', $row['status']);
        $this->assertSame(0, (int) $row['is_owner']);
        $this->assertSame('staf@example.com', $row['email']);
        $this->assertSame($t['tenantId'], (int) $row['tenant_id']);
        $this->assertTrue($row['password_hash'] !== '' && !$this->hasher->verify('', (string) $row['password_hash']));

        $mail = $this->mailer->sent[count($this->mailer->sent) - 1];
        $this->assertSame('staf@example.com', $mail['to']);
        $token = $this->mailer->lastInvitationToken();
        $this->assertContains(self::APP_URL . '/undangan/' . $token, $mail['body']);
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM email_verifications WHERE user_id = ? AND token_hash = ?', [$id, hash('sha256', $token)]));
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM email_verifications WHERE token_hash = ?', [$token]));

        $this->assertSame(1, $this->auditCount('user.invited'));
        $raw = (string) $this->db->fetchOne("SELECT metadata FROM audit_logs WHERE action = 'user.invited'")['metadata'];
        $this->assertFalse(str_contains($raw, $token), 'token tidak boleh masuk audit');
        $this->assertFalse(str_contains($raw, (string) $row['password_hash']), 'hash tidak boleh masuk audit');
    }

    public function testInviteEmailUniqueGloballyCaseInsensitive(): void
    {
        $a = $this->tenant('a@example.com');
        $b = $this->tenant('b@example.com');
        $this->invite($a, 'staf@example.com');
        $mails = count($this->mailer->sent);
        foreach ([['a', 'STAF@example.com'], ['a', ' staf@example.com '], ['b', 'staf@example.com'], ['a', 'A@example.com'], ['b', 'a@EXAMPLE.com']] as [$who, $email]) {
            try {
                $this->invite($who === 'a' ? $a : $b, $email);
                $this->fail('Email duplikat harus ditolak: ' . $email);
            } catch (ValidationException $e) {
                $this->assertSame(['email' => 'Email sudah terdaftar.'], $e->errors());
            }
        }
        $this->assertSame($mails, count($this->mailer->sent));
        $this->assertSame(1, $this->count("SELECT COUNT(*) AS n FROM users WHERE email = 'staf@example.com'"));
    }

    public function testInviteRaceOnUniqueConstraintIsValidationErrorNotServerError(): void
    {
        $t = $this->tenant();
        $this->invite($t, 'staf@example.com');
        $blind = new class ($this->db) extends UserRepository {
            public function findByEmail(string $email): ?array
            {
                return null;
            }
        };
        $service = new \App\Modules\Users\Services\UserService(
            $this->db, $blind, $this->roleRepo, $this->branchRepo, $this->policy, $this->makeAudit(),
            new \App\Core\Authorization\AccessDeniedRecorder($this->makeAudit()),
            new \App\Modules\Auth\Services\InvitationEmailSender(new \App\Modules\Auth\Repositories\EmailVerificationRepository($this->db), new \App\Core\Security\TokenService(), $this->mailer, self::APP_URL, 86400),
            $this->hasher,
            new \App\Core\Security\RateLimiter($this->db, 3, 3600),
            new \App\Core\Security\RateLimiter($this->db, 20, 3600)
        );
        $mails = count($this->mailer->sent);
        $this->assertThrows(ValidationException::class, fn () => $service->invite($t['owner'], ['name' => 'X', 'email' => 'staf@example.com']));
        $this->assertSame($mails, count($this->mailer->sent));
        $this->assertSame(1, $this->auditCount('user.invited'));
    }

    // --- F-02: kuota undangan dan pembebasan email basi ---

    private function assertInviteRejected(array $t, string $email, string $key, string $message): void
    {
        try {
            $this->invite($t, $email);
            $this->fail('Undangan harus ditolak');
        } catch (ValidationException $e) {
            $this->assertSame([$key => $message], $e->errors());
        }
    }

    public function testInviteRateLimitedPerTenantWithIndonesianError(): void
    {
        $this->inviteMax = 3;
        $this->rebuild();
        $t = $this->tenant();
        $other = $this->tenant('lain@example.com');
        foreach (['a', 'b', 'c'] as $n) {
            $this->invite($t, "$n@example.com");
        }
        $mails = count($this->mailer->sent);
        $this->assertInviteRejected($t, 'd@example.com', 'form', 'Terlalu banyak undangan dikirim dalam waktu singkat. Coba lagi nanti.');
        $this->assertSame($mails, count($this->mailer->sent));
        $this->assertSame(0, $this->count("SELECT COUNT(*) AS n FROM users WHERE email = 'd@example.com'"));
        $this->invite($other, 'e@example.com'); // tenant lain tidak terdampak
        $this->now += 3601; // jendela lewat
        $this->invite($t, 'd@example.com');
    }

    public function testInviteAttemptsRejectedAsDuplicateStillCountTowardsQuota(): void
    {
        $this->inviteMax = 2;
        $this->rebuild();
        $t = $this->tenant();
        $this->assertInviteRejected($t, 'budi@example.com', 'email', 'Email sudah terdaftar.');
        $this->assertInviteRejected($t, 'budi@example.com', 'email', 'Email sudah terdaftar.');
        $this->assertInviteRejected($t, 'x@example.com', 'form', 'Terlalu banyak undangan dikirim dalam waktu singkat. Coba lagi nanti.');
    }

    public function testInviteRefusedWhenTenantHasTooManyPendingInvites(): void
    {
        $this->pendingMax = 2;
        $this->rebuild();
        $t = $this->tenant();
        $other = $this->tenant('lain@example.com');
        $this->invite($t, 'a@example.com');
        $this->invite($t, 'b@example.com');
        $this->assertInviteRejected($t, 'c@example.com', 'form', 'Terlalu banyak undangan yang belum diterima. Batalkan atau tunggu undangan sebelumnya diterima sebelum mengundang lagi.');
        $this->invite($other, 'c@example.com'); // kuota per tenant
    }

    private function ageHours(int $hours): void
    {
        $this->now += $hours * 3600;
    }

    public function testStalePendingInviteIsReclaimedByAnotherTenant(): void
    {
        $a = $this->tenant('a@example.com');
        $b = $this->tenant('b@example.com');
        $oldId = $this->invite($a, 'korban@example.com');
        $role = $this->roleId($a['tenantId'], 'kasir');
        $this->userService->assignRoles($a['owner'], $oldId, [$role]);
        $branch = (int) $this->db->fetchOne('SELECT id FROM branches WHERE tenant_id = ?', [$a['tenantId']])['id'];
        $this->users->addBranch($a['tenantId'], $oldId, $branch);
        $keep = $this->invite($a, 'lain-a@example.com');

        $this->ageHours(25);
        $newId = $this->invite($b, 'Korban@Example.com');

        $this->assertTrue($newId !== $oldId);
        $this->assertSame(null, $this->users->findById($a['tenantId'], $oldId));
        $this->assertSame($b['tenantId'], (int) $this->users->findById($b['tenantId'], $newId)['tenant_id']);
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM email_verifications WHERE user_id = ?', [$oldId]));
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM user_roles WHERE user_id = ?', [$oldId]));
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM user_branch_access WHERE user_id = ?', [$oldId]));
        // Isolasi: baris tenant A lain tidak tersentuh, dan audit tercatat di tenant aktor (B).
        $this->assertTrue($this->users->findById($a['tenantId'], $keep) !== null);
        $row = $this->db->fetchOne("SELECT tenant_id, entity_id FROM audit_logs WHERE action = 'user.stale_invite_reclaimed'");
        $this->assertSame($b['tenantId'], (int) $row['tenant_id']);
        $this->assertSame($oldId, (int) $row['entity_id']);
        $this->assertSame($a['tenantId'], (int) $this->lastAudit('user.stale_invite_reclaimed')['previous_tenant_id']);
        $this->assertSame(1, $this->auditCount('user.invited') - 2);
    }

    public function testStalePendingInviteIsReclaimedBySameTenant(): void
    {
        $a = $this->tenant();
        $old = $this->invite($a, 'staf@example.com');
        $this->ageHours(25);
        $new = $this->invite($a, 'staf@example.com');
        $this->assertTrue($new !== $old);
        $this->assertSame(1, $this->count("SELECT COUNT(*) AS n FROM users WHERE email = 'staf@example.com'"));
    }

    public function testFreshPendingInviteIsNotReclaimed(): void
    {
        $a = $this->tenant('a@example.com');
        $b = $this->tenant('b@example.com');
        $this->invite($a, 'korban@example.com');
        $this->ageHours(23);
        $this->assertInviteRejected($b, 'korban@example.com', 'email', 'Email sudah terdaftar.');
    }

    public function testOldPendingInviteWithValidTokenIsNotReclaimed(): void
    {
        $a = $this->tenant('a@example.com');
        $b = $this->tenant('b@example.com');
        $id = $this->invite($a, 'korban@example.com');
        $this->ageHours(25);
        $this->userService->resendInvite($a['owner'], $id); // token baru, masih berlaku
        $this->assertInviteRejected($b, 'korban@example.com', 'email', 'Email sudah terdaftar.');
        $this->assertTrue($this->users->findById($a['tenantId'], $id) !== null);
    }

    public function testActiveOrOwnerUsersAreNeverReclaimed(): void
    {
        $a = $this->tenant('a@example.com');
        $b = $this->tenant('b@example.com');
        $id = $this->invite($a, 'aktif@example.com');
        $this->users->activate($a['tenantId'], $id, $this->nowStr());
        $this->ageHours(48);
        $this->assertInviteRejected($b, 'aktif@example.com', 'email', 'Email sudah terdaftar.');
        $this->assertInviteRejected($b, 'a@example.com', 'email', 'Email sudah terdaftar.');
        // Owner pending lama (tanpa token berlaku) juga tidak dibebaskan.
        $this->db->execute("UPDATE users SET status = 'pending_verification', email_verified_at = NULL WHERE id = ?", [$a['ownerId']]);
        $this->db->execute('DELETE FROM email_verifications WHERE user_id = ?', [$a['ownerId']]);
        $this->assertInviteRejected($b, 'a@example.com', 'email', 'Email sudah terdaftar.');
        $this->assertTrue($this->users->findById($a['tenantId'], $a['ownerId']) !== null);
    }

    public function testStalePendingInviteDoesNotBlockRegistration(): void
    {
        $a = $this->tenant('a@example.com');
        $this->invite($a, 'korban@example.com');
        $this->ageHours(25);
        $r = $this->register->register(['business_name' => 'Toko Korban', 'owner_name' => 'Korban', 'email' => 'korban@example.com', 'password' => 'sandi-korban-123']);
        $this->assertSame(1, (int) $this->users->findById($r['tenantId'], $r['userId'])['is_owner']);
        $this->assertSame($r['tenantId'], (int) $this->db->fetchOne("SELECT tenant_id FROM audit_logs WHERE action = 'user.stale_invite_reclaimed'")['tenant_id']);
    }

    public function testRegistrationStillRejectsFreshInviteWithNeutralError(): void
    {
        $a = $this->tenant('a@example.com');
        $this->invite($a, 'korban@example.com');
        $this->assertThrows(\App\Modules\Tenants\Exceptions\DuplicateEmailException::class, fn () => $this->register->register(['business_name' => 'T', 'owner_name' => 'K', 'email' => 'korban@example.com', 'password' => 'sandi-korban-123']));
    }

    public function testInviteValidatesInput(): void
    {
        $t = $this->tenant();
        foreach ([['name' => '', 'email' => 'a@b.co'], ['name' => 'X', 'email' => 'bukan-email'], ['name' => 'X', 'email' => 'a@b.co', 'phone' => 'abc'], ['name' => "X\0", 'email' => 'a@b.co']] as $input) {
            $this->assertThrows(ValidationException::class, fn () => $this->userService->invite($t['owner'], $input));
        }
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM users'));
    }

    public function testMailFailureRollsBackInvitation(): void
    {
        $t = $this->tenant();
        $this->mailer->fail = true;
        try {
            $this->invite($t);
            $this->fail('Kegagalan email harus dilaporkan');
        } catch (ValidationException $e) {
            $this->assertTrue(isset($e->errors()['form']));
        }
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM users'));
        $this->assertSame(0, $this->auditCount('user.invited'));
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM email_verifications'), 'hanya token verifikasi Owner');
    }

    public function testInvitationAcceptFlowActivatesUserAndTokenIsSingleUse(): void
    {
        $t = $this->tenant();
        $id = $this->invite($t);
        $token = $this->mailer->lastInvitationToken();

        $this->assertSame(200, $this->send('GET', '/undangan/' . $token)->status());
        $this->login('staf@example.com', self::INVITEE_PASSWORD);
        $this->assertSame('/masuk', $this->send('GET', '/beranda')->header('Location'), 'belum bisa masuk sebelum menerima undangan');

        $weak = $this->send('POST', '/undangan/' . $token, ['password' => 'pendek', 'password_confirmation' => 'pendek']);
        $this->assertSame(422, $weak->status());
        $mismatch = $this->send('POST', '/undangan/' . $token, ['password' => self::INVITEE_PASSWORD, 'password_confirmation' => 'beda-sandi-123']);
        $this->assertSame(422, $mismatch->status());
        $this->assertSame('pending_verification', $this->users->findById($t['tenantId'], $id)['status'], 'gagal validasi tidak memakai token');

        $ok = $this->send('POST', '/undangan/' . $token, ['password' => self::INVITEE_PASSWORD, 'password_confirmation' => self::INVITEE_PASSWORD]);
        $this->assertSame(302, $ok->status());
        $this->assertSame('/masuk', $ok->header('Location'));
        $row = $this->users->findById($t['tenantId'], $id);
        $this->assertSame('active', $row['status']);
        $this->assertTrue($row['email_verified_at'] !== null);
        $this->assertSame(0, (int) $row['is_owner']);
        $this->assertSame(302, $this->login('staf@example.com', self::INVITEE_PASSWORD)->status());

        $this->assertSame(404, $this->send('GET', '/undangan/' . $token)->status());
        $again = $this->send('POST', '/undangan/' . $token, ['password' => 'sandi-lain-12345', 'password_confirmation' => 'sandi-lain-12345']);
        $this->assertSame(404, $again->status());
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM audit_logs WHERE action = ? AND entity_id = ?', ['auth.email_verified', $id]));
    }

    public function testInvitationPasswordIsNotTrimmed(): void
    {
        $t = $this->tenant();
        $this->invite($t);
        $token = $this->mailer->lastInvitationToken();
        $padded = '  sandi dengan spasi  ';
        $this->assertSame(302, $this->send('POST', '/undangan/' . $token, ['password' => $padded, 'password_confirmation' => $padded])->status());
        $this->assertSame(302, $this->login('staf@example.com', $padded)->status());
    }

    public function testInvitationTokenExpiresAfter24HoursAndRandomTokensAreNeutral404(): void
    {
        $t = $this->tenant();
        $this->invite($t);
        $token = $this->mailer->lastInvitationToken();
        $this->now += 86400;
        $this->assertSame(404, $this->send('GET', '/undangan/' . $token)->status());
        $this->assertSame(404, $this->send('POST', '/undangan/' . $token, ['password' => self::INVITEE_PASSWORD, 'password_confirmation' => self::INVITEE_PASSWORD])->status());
        $expiredBody = $this->send('GET', '/undangan/' . $token)->body();
        $randomBody = $this->send('GET', '/undangan/' . bin2hex(random_bytes(32)))->body();
        $this->assertSame($expiredBody, $randomBody);
        $this->assertSame(404, $this->send('GET', '/undangan/xyz')->status());
        $this->assertSame('pending_verification', $this->db->fetchOne("SELECT status FROM users WHERE email = 'staf@example.com'")['status']);
    }

    public function testOwnerVerificationTokenCannotBeUsedOnInvitationPage(): void
    {
        $this->pendingOwner('baru@example.com');
        $token = $this->mailer->lastToken();
        $this->assertSame(404, $this->send('GET', '/undangan/' . $token)->status());
        $this->assertSame(404, $this->send('POST', '/undangan/' . $token, ['password' => self::INVITEE_PASSWORD, 'password_confirmation' => self::INVITEE_PASSWORD])->status());
        $this->assertSame('pending_verification', $this->db->fetchOne("SELECT status FROM users WHERE email = 'baru@example.com'")['status']);
    }

    public function testInviteeTokenOnlyWorksWhileUserStillPending(): void
    {
        $t = $this->tenant();
        $id = $this->invite($t);
        $token = $this->mailer->lastInvitationToken();
        $this->userService->setStatus($t['owner'], $id, 'inactive');
        $this->assertSame(404, $this->send('GET', '/undangan/' . $token)->status());
        $this->assertSame('inactive', $this->users->findById($t['tenantId'], $id)['status']);
    }

    public function testResendInvitationInvalidatesOldTokenAndIsRateLimited(): void
    {
        $t = $this->tenant();
        $id = $this->invite($t);
        $first = $this->mailer->lastInvitationToken();
        $this->now += 10;
        $this->userService->resendInvite($t['owner'], $id);
        $second = $this->mailer->lastInvitationToken();
        $this->assertTrue($first !== $second);
        $this->assertSame(404, $this->send('GET', '/undangan/' . $first)->status());
        $this->assertSame(200, $this->send('GET', '/undangan/' . $second)->status());
        $this->userService->resendInvite($t['owner'], $id);
        $this->userService->resendInvite($t['owner'], $id);
        $mails = count($this->mailer->sent);
        $this->assertThrows(ValidationException::class, fn () => $this->userService->resendInvite($t['owner'], $id));
        $this->assertSame($mails, count($this->mailer->sent));
        $this->now += 3601;
        $this->userService->resendInvite($t['owner'], $id);
        $this->assertSame($mails + 1, count($this->mailer->sent));
    }

    public function testResendOnlyForPendingNonOwnerUsers(): void
    {
        $t = $this->tenant();
        $id = $this->invite($t);
        $token = $this->mailer->lastInvitationToken();
        $this->send('POST', '/undangan/' . $token, ['password' => self::INVITEE_PASSWORD, 'password_confirmation' => self::INVITEE_PASSWORD]);
        $this->assertThrows(ValidationException::class, fn () => $this->userService->resendInvite($t['owner'], $id));
        $this->assertThrows(ValidationException::class, fn () => $this->userService->resendInvite($t['owner'], $t['ownerId']));
    }

    // --- Perlindungan Owner ---

    public function testOwnerFlagCannotBeRemovedOrDeactivated(): void
    {
        $t = $this->tenant();
        $this->userService->update($t['owner'], $t['ownerId'], ['name' => 'Budi Baru', 'phone' => '', 'is_owner' => '0', 'tenant_id' => '999', 'email' => 'x@y.co', 'status' => 'inactive']);
        $row = $this->users->findById($t['tenantId'], $t['ownerId']);
        $this->assertSame(1, (int) $row['is_owner']);
        $this->assertSame('Budi Baru', $row['name']);
        $this->assertSame('budi@example.com', $row['email']);
        $this->assertSame('active', $row['status']);
        $this->assertSame($t['tenantId'], (int) $row['tenant_id']);

        $this->assertThrows(ValidationException::class, fn () => $this->userService->setStatus($t['owner'], $t['ownerId'], 'inactive'));
        $this->assertSame('active', $this->users->findById($t['tenantId'], $t['ownerId'])['status']);
        $this->assertSame(0, $this->auditCount('user.status_changed'));
        // Lapis SQL juga menolak walau service dilewati.
        $this->assertFalse($this->users->setStatus($t['tenantId'], $t['ownerId'], 'inactive', '2027-01-01 00:00:00'));
        $this->assertSame('active', $this->users->findById($t['tenantId'], $t['ownerId'])['status']);
    }

    public function testTenantAlwaysKeepsOneActiveOwner(): void
    {
        $t = $this->tenant();
        $activeOwners = fn (): int => $this->count("SELECT COUNT(*) AS n FROM users WHERE tenant_id = ? AND is_owner = 1 AND status = 'active'", [$t['tenantId']]);
        $ownerRole = $this->roleId($t['tenantId'], 'owner');
        $kasir = $this->roleId($t['tenantId'], 'kasir');

        $this->assertThrows(ValidationException::class, fn () => $this->userService->setStatus($t['owner'], $t['ownerId'], 'inactive'));
        $this->assertThrows(ValidationException::class, fn () => $this->userService->assignRoles($t['owner'], $t['ownerId'], []));
        $this->assertThrows(ValidationException::class, fn () => $this->userService->assignRoles($t['owner'], $t['ownerId'], [$kasir]));
        $this->assertSame(1, $activeOwners());
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM user_roles WHERE user_id = ? AND role_id = ?', [$t['ownerId'], $ownerRole]));

        // Role Owner tidak bisa diberikan ke non-Owner, sehingga tidak ada jalur membuat/menghapus Owner dari UI.
        $id = $this->invite($t);
        $this->assertThrows(ValidationException::class, fn () => $this->userService->assignRoles($t['owner'], $id, [$ownerRole]));
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM user_roles WHERE user_id = ?', [$id]));
        // Menambah role lain ke Owner boleh asalkan role Owner tetap ada.
        $this->userService->assignRoles($t['owner'], $t['ownerId'], [$ownerRole, $kasir]);
        $this->assertSame(2, $this->count('SELECT COUNT(*) AS n FROM user_roles WHERE user_id = ?', [$t['ownerId']]));
        $this->assertSame(1, $activeOwners());
    }

    public function testSelfDeactivateDenied(): void
    {
        $t = $this->tenant();
        $mgr = $this->makeUser($t['tenantId'], 'mgr@example.com', ['users.view', 'users.manage']);
        $actor = $this->authUserFor($t['tenantId'], $mgr);
        $this->assertThrows(ValidationException::class, fn () => $this->userService->setStatus($actor, $mgr, 'inactive'));
        $this->assertSame('active', $this->users->findById($t['tenantId'], $mgr)['status']);
    }

    public function testNonOwnerCannotModifyOwnerUser(): void
    {
        $t = $this->tenant();
        $mgr = $this->makeUser($t['tenantId'], 'mgr@example.com', ['users.view', 'users.manage'], [$this->mainBranchId($t['tenantId'])]);
        $actor = $this->authUserFor($t['tenantId'], $mgr);
        $id = $t['ownerId'];
        $before = $this->users->findById($t['tenantId'], $id);
        $this->assertThrows(ForbiddenException::class, fn () => $this->userService->update($actor, $id, ['name' => 'Diretas', 'phone' => '']));
        $this->assertThrows(ForbiddenException::class, fn () => $this->userService->setStatus($actor, $id, 'inactive'));
        $this->assertThrows(ForbiddenException::class, fn () => $this->userService->assignRoles($actor, $id, []));
        $this->assertThrows(ForbiddenException::class, fn () => $this->userService->assignBranches($actor, $id, []));
        $this->assertThrows(ForbiddenException::class, fn () => $this->userService->forEdit($actor, $id));
        $this->assertSame($before['name'], $this->users->findById($t['tenantId'], $id)['name']);
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM user_roles WHERE user_id = ?', [$id]));
    }

    // --- Batas kewenangan aktor ---

    public function testActorCannotGrantPermissionTheyLack(): void
    {
        $t = $this->tenant();
        $mgr = $this->makeUser($t['tenantId'], 'mgr@example.com', ['users.view', 'users.manage', 'branches.view']);
        $actor = $this->authUserFor($t['tenantId'], $mgr);
        $target = $this->invite($t);
        $small = $this->roleService->createCustom($t['owner'], 'Lihat cabang', ['branches.view']);
        $big = $this->roleService->createCustom($t['owner'], 'Kelola cabang', ['branches.view', 'branches.manage']);

        $this->userService->assignRoles($actor, $target, [$small]);
        $this->assertSame([$small], $this->userRoleIds($t['tenantId'], $target));
        $this->assertThrows(ForbiddenException::class, fn () => $this->userService->assignRoles($actor, $target, [$small, $big]));
        $this->assertSame([$small], $this->userRoleIds($t['tenantId'], $target));

        // Pengguna yang memegang role melebihi aktor tidak boleh diubah rolenya oleh aktor itu.
        $this->userService->assignRoles($t['owner'], $target, [$big]);
        $this->assertThrows(ForbiddenException::class, fn () => $this->userService->assignRoles($actor, $target, [$small]));
        $this->assertSame([$big], $this->userRoleIds($t['tenantId'], $target));
    }

    /** @return list<int> */
    private function userRoleIds(int $tenantId, int $userId): array
    {
        $ids = array_map(static fn (array $r): int => (int) $r['role_id'], $this->db->query('SELECT role_id FROM user_roles WHERE tenant_id = ? AND user_id = ? ORDER BY role_id', [$tenantId, $userId]));
        return $ids;
    }

    public function testNonOwnerCanOnlyAssignBranchesTheyHaveAndKeepsOthers(): void
    {
        $t = $this->tenant();
        $main = $this->mainBranchId($t['tenantId']);
        $b2 = $this->seedBranch($t['tenantId'], 'B2');
        $b3 = $this->seedBranch($t['tenantId'], 'B3');
        $mgr = $this->makeUser($t['tenantId'], 'mgr@example.com', ['users.view', 'users.manage'], [$main, $b2]);
        $actor = $this->authUserFor($t['tenantId'], $mgr);
        $target = $this->invite($t);
        $b4 = $this->seedBranch($t['tenantId'], 'B4');
        $this->userService->assignBranches($t['owner'], $target, [$b3]);

        $this->assertThrows(ForbiddenException::class, fn () => $this->userService->assignBranches($actor, $target, [$b3, $b4]));
        $this->userService->assignBranches($actor, $target, [$main]);
        $ids = array_map(static fn (array $r): int => (int) $r['branch_id'], $this->db->query('SELECT branch_id FROM user_branch_access WHERE user_id = ? ORDER BY branch_id', [$target]));
        $this->assertSame([$main, $b3], $ids, 'cabang yang tidak dimiliki aktor dipertahankan');
    }

    // --- Isolasi tenant ---

    public function testUserFromOtherTenantReturns404(): void
    {
        $a = $this->tenant('a@example.com');
        $b = $this->tenant('b@example.com');
        $victim = $this->makeUser($b['tenantId'], 'korban@example.com', ['users.view']);
        $before = $this->users->findById($b['tenantId'], $victim);
        $branchB = $this->mainBranchId($b['tenantId']);
        $calls = [
            fn () => $this->userService->forEdit($a['owner'], $victim),
            fn () => $this->userService->update($a['owner'], $victim, ['name' => 'Diretas', 'phone' => '']),
            fn () => $this->userService->setStatus($a['owner'], $victim, 'inactive'),
            fn () => $this->userService->assignRoles($a['owner'], $victim, []),
            fn () => $this->userService->assignBranches($a['owner'], $victim, [$this->mainBranchId($a['tenantId'])]),
            fn () => $this->userService->resendInvite($a['owner'], $victim),
        ];
        foreach ($calls as $call) {
            $this->assertThrows(NotFoundException::class, $call);
        }
        $this->assertSame($before, $this->users->findById($b['tenantId'], $victim));
        $this->assertSame(count($calls), $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'security.entity_not_found' AND tenant_id = ? AND entity_type = 'user' AND entity_id = ?", [$a['tenantId'], $victim]));
        $this->assertSame(0, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'security.entity_not_found' AND tenant_id = ?", [$b['tenantId']]));

        // Stack penuh: respons identik dengan ID yang tidak ada.
        $this->assertSame(302, $this->login('a@example.com')->status());
        $this->assertSame(404, $this->status('GET', '/pengguna/' . $victim . '/ubah'));
        $this->assertSame(404, $this->status('GET', '/pengguna/987654/ubah'));
        $this->assertSame(404, $this->status('POST', '/pengguna/' . $victim . '/nonaktifkan'));
        $this->assertSame(404, $this->status('POST', '/pengguna/' . $victim . '/cabang', ['branch_ids' => [$branchB]]));
        $this->assertSame($before, $this->users->findById($b['tenantId'], $victim));
    }

    public function testCrossTenantBranchAssignmentRejected(): void
    {
        $a = $this->tenant('a@example.com');
        $b = $this->tenant('b@example.com');
        $branchB = $this->mainBranchId($b['tenantId']);
        $staff = $this->invite($a);
        try {
            $this->userService->assignBranches($a['owner'], $staff, [$this->mainBranchId($a['tenantId']), $branchB]);
            $this->fail('Cabang tenant lain harus ditolak');
        } catch (NotFoundException) {
        }
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM user_branch_access WHERE user_id = ?', [$staff]), 'tidak ada penugasan sebagian');
        $this->assertSame(1, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'security.entity_not_found' AND entity_type = 'branch' AND entity_id = ? AND tenant_id = ?", [$branchB, $a['tenantId']]));
        $this->assertSame(0, $this->auditCount('user.branches_assigned'));
        $this->assertThrows(NotFoundException::class, fn () => $this->userService->assignBranches($a['owner'], $staff, [987654]));
    }

    public function testRoleFromOtherTenantCannotBeAssigned(): void
    {
        $a = $this->tenant('a@example.com');
        $b = $this->tenant('b@example.com');
        $staff = $this->invite($a);
        $foreign = $this->roleId($b['tenantId'], 'kasir');
        $this->assertThrows(NotFoundException::class, fn () => $this->userService->assignRoles($a['owner'], $staff, [$foreign]));
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM user_roles WHERE user_id = ?', [$staff]));
        $this->assertSame(1, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'security.entity_not_found' AND entity_type = 'role' AND tenant_id = ?", [$a['tenantId']]));
    }

    // --- Status, peran, cabang, audit ---

    public function testDeactivatedUserLosesSessionAndCanBeReactivated(): void
    {
        $t = $this->tenant();
        $staff = $this->makeUser($t['tenantId'], 'kasir@example.com', []);
        $this->send('POST', '/keluar');
        $this->assertSame(302, $this->login('kasir@example.com')->status());
        $this->assertSame(200, $this->send('GET', '/beranda')->status());

        $this->userService->setStatus($t['owner'], $staff, 'inactive');
        $this->assertSame('/masuk', $this->send('GET', '/beranda')->header('Location'));
        $this->login('kasir@example.com');
        $this->assertSame('/masuk', $this->send('GET', '/beranda')->header('Location'), 'nonaktif tidak bisa masuk');

        $this->userService->setStatus($t['owner'], $staff, 'active');
        $this->assertSame(302, $this->login('kasir@example.com')->status());
        $this->assertSame(200, $this->send('GET', '/beranda')->status());
        $this->assertSame(2, $this->auditCount('user.status_changed'));
        $this->assertSame(['from' => 'active', 'to' => 'inactive'], $this->firstStatusAudit());
        $this->userService->setStatus($t['owner'], $staff, 'active');
        $this->assertSame(2, $this->auditCount('user.status_changed'), 'tanpa perubahan tanpa audit');
    }

    /** @return array<string, mixed> */
    private function firstStatusAudit(): array
    {
        $row = $this->db->fetchOne("SELECT metadata FROM audit_logs WHERE action = 'user.status_changed' ORDER BY id ASC LIMIT 1");
        return (array) json_decode((string) $row['metadata'], true);
    }

    public function testUnverifiedUserCannotBeActivatedButInvitationCanBeRevoked(): void
    {
        $t = $this->tenant();
        $id = $this->invite($t);
        $this->assertThrows(ValidationException::class, fn () => $this->userService->setStatus($t['owner'], $id, 'active'));
        $this->userService->setStatus($t['owner'], $id, 'inactive');
        $this->assertThrows(ValidationException::class, fn () => $this->userService->setStatus($t['owner'], $id, 'active'), 'tidak pernah memverifikasi email');
        $this->assertThrows(ValidationException::class, fn () => $this->userService->setStatus($t['owner'], $id, 'pending_verification'));
        $this->assertThrows(ValidationException::class, fn () => $this->userService->setStatus($t['owner'], $id, 'bebas'));
    }

    public function testPermissionChangesAreAudited(): void
    {
        $t = $this->tenant();
        $main = $this->mainBranchId($t['tenantId']);
        $b2 = $this->seedBranch($t['tenantId'], 'B2');
        $id = $this->invite($t);
        $kasir = $this->roleId($t['tenantId'], 'kasir');
        $gudang = $this->roleId($t['tenantId'], 'staf-gudang');

        $this->userService->assignRoles($t['owner'], $id, [$kasir]);
        $this->userService->assignRoles($t['owner'], $id, [$gudang]);
        $this->userService->assignRoles($t['owner'], $id, [$gudang]);
        $this->assertSame(2, $this->auditCount('user.roles_assigned'), 'tanpa perubahan tanpa audit');
        $meta = $this->lastAudit('user.roles_assigned');
        $this->assertSame(['staf-gudang'], $meta['added']);
        $this->assertSame(['kasir'], $meta['removed']);

        $this->userService->assignBranches($t['owner'], $id, [$main, $b2]);
        $this->userService->assignBranches($t['owner'], $id, [$b2]);
        $this->assertSame(2, $this->auditCount('user.branches_assigned'));
        $meta = $this->lastAudit('user.branches_assigned');
        $this->assertSame([], $meta['added']);
        $this->assertSame([$main], $meta['removed']);

        $this->userService->update($t['owner'], $id, ['name' => 'Nama Baru', 'phone' => '081234567890']);
        $this->assertSame(1, $this->auditCount('user.updated'));
        $this->userService->update($t['owner'], $id, ['name' => 'Nama Baru', 'phone' => '081234567890']);
        $this->assertSame(1, $this->auditCount('user.updated'));
        $row = $this->db->fetchOne("SELECT actor_id, entity_type, entity_id, tenant_id FROM audit_logs WHERE action = 'user.roles_assigned' ORDER BY id DESC LIMIT 1");
        $this->assertSame([$t['ownerId'], 'user', $id, $t['tenantId']], [(int) $row['actor_id'], $row['entity_type'], (int) $row['entity_id'], (int) $row['tenant_id']]);
    }

    public function testBranchAssignmentForOwnerIsRejected(): void
    {
        $t = $this->tenant();
        $this->assertThrows(ValidationException::class, fn () => $this->userService->assignBranches($t['owner'], $t['ownerId'], [$this->mainBranchId($t['tenantId'])]));
    }

    public function testInvalidIdListsAreValidationErrors(): void
    {
        $t = $this->tenant();
        $id = $this->invite($t);
        foreach ([['x'], [-1], [0], [1.5], ['1; DROP']] as $ids) {
            $this->assertThrows(ValidationException::class, fn () => $this->userService->assignRoles($t['owner'], $id, $ids));
            $this->assertThrows(ValidationException::class, fn () => $this->userService->assignBranches($t['owner'], $id, $ids));
        }
    }

    public function testListShowsOnlyOwnTenantUsersWithRoles(): void
    {
        $a = $this->tenant('a@example.com');
        $b = $this->tenant('b@example.com');
        $this->makeUser($b['tenantId'], 'lain@example.com', []);
        $this->invite($a);
        $emails = array_column($this->userService->list($a['owner']), 'email');
        sort($emails);
        $this->assertSame(['a@example.com', 'staf@example.com'], $emails);
    }

    // --- Stack penuh ---

    public function testMissingPermissionReturns403FullStack(): void
    {
        $t = $this->tenant();
        $viewer = $this->makeUser($t['tenantId'], 'lihat@example.com', ['users.view']);
        $nobody = $this->makeUser($t['tenantId'], 'kosong@example.com', []);
        $this->send('POST', '/keluar');
        $this->assertSame(302, $this->login('lihat@example.com')->status());
        $this->assertSame(200, $this->status('GET', '/pengguna'));
        foreach ([['GET', '/pengguna/undang'], ['POST', '/pengguna/undang'], ['GET', "/pengguna/$nobody/ubah"], ['POST', "/pengguna/$nobody"], ['POST', "/pengguna/$nobody/nonaktifkan"], ['POST', "/pengguna/$nobody/role"], ['POST', "/pengguna/$nobody/cabang"], ['POST', "/pengguna/$nobody/kirim-ulang-undangan"]] as [$m, $u]) {
            $this->assertSame(403, $this->status($m, $u, ['name' => 'x', 'email' => 'x@y.co']), "$m $u");
        }
        $this->send('POST', '/keluar');
        $this->assertSame(302, $this->login('kosong@example.com')->status());
        $this->assertSame(403, $this->status('GET', '/pengguna'));
        $this->assertTrue($this->auditCount('security.permission_denied') >= 9);
        $this->send('POST', '/keluar');
        $this->assertSame(302, $this->send('GET', '/pengguna')->status(), 'tanpa login diarahkan ke /masuk');
    }

    public function testInviteAndManageThroughHttp(): void
    {
        $t = $this->ownerLoggedIn('owner@example.com');
        $page = $this->send('GET', '/pengguna/undang');
        $this->assertSame(200, $page->status());
        $bad = $this->send('POST', '/pengguna/undang', ['name' => '', 'email' => 'salah']);
        $this->assertSame(422, $bad->status());
        $this->assertContains('role="alert"', $bad->body());
        $ok = $this->send('POST', '/pengguna/undang', ['name' => '<b>Siti</b>', 'email' => 'siti@example.com', 'phone' => '']);
        $this->assertSame(302, $ok->status());
        $id = (int) $this->db->fetchOne("SELECT id FROM users WHERE email = 'siti@example.com'")['id'];
        $this->assertSame('/pengguna/' . $id . '/ubah', $ok->header('Location'));

        $edit = $this->send('GET', "/pengguna/$id/ubah");
        $this->assertSame(200, $edit->status());
        $this->assertFalse(str_contains($edit->body(), '<b>Siti</b>'), 'nama harus di-escape');
        $this->assertContains('&lt;b&gt;Siti&lt;/b&gt;', $edit->body());
        $list = $this->send('GET', '/pengguna');
        $this->assertContains('siti@example.com', $list->body());

        $kasir = $this->roleId($t['tenantId'], 'kasir');
        $main = $this->mainBranchId($t['tenantId']);
        $this->assertSame(302, $this->send('POST', "/pengguna/$id/role", ['role_ids' => [(string) $kasir]])->status());
        $this->assertSame(302, $this->send('POST', "/pengguna/$id/cabang", ['branch_ids' => [(string) $main]])->status());
        $this->assertSame([$kasir], $this->userRoleIds($t['tenantId'], $id));
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM user_branch_access WHERE user_id = ?', [$id]));
        $this->assertSame(302, $this->send('POST', "/pengguna/$id", ['name' => 'Siti Baru', 'phone' => '', 'is_owner' => '1', 'tenant_id' => '77'])->status());
        $row = $this->users->findById($t['tenantId'], $id);
        $this->assertSame(['Siti Baru', 0, $t['tenantId']], [$row['name'], (int) $row['is_owner'], (int) $row['tenant_id']]);
        $this->assertSame(302, $this->send('POST', "/pengguna/$id/kirim-ulang-undangan")->status());
        $this->assertSame(302, $this->send('POST', "/pengguna/$id/nonaktifkan")->status());
        $this->assertSame('inactive', $this->users->findById($t['tenantId'], $id)['status']);
        $selfDeactivate = $this->send('POST', '/pengguna/' . $t['userId'] . '/nonaktifkan');
        $this->assertSame(302, $selfDeactivate->status());
        $this->assertSame('active', $this->users->findById($t['tenantId'], $t['userId'])['status']);
        $this->assertSame(302, $this->send('POST', "/pengguna/$id/role", [])->status(), 'daftar kosong melepas semua role');
        $this->assertSame([], $this->userRoleIds($t['tenantId'], $id));
    }

    public function testRequestCannotInjectTenantOrGarbageIds(): void
    {
        $this->ownerLoggedIn();
        $this->assertSame(404, $this->status('GET', '/pengguna/abc/ubah'));
        $this->assertSame(404, $this->status('GET', '/pengguna/0/ubah'));
        $this->assertSame(404, $this->status('POST', '/pengguna/99999999999999999999/nonaktifkan'));
    }

    public function testRepositoryThrowsDuplicateEmailForRace(): void
    {
        $t = $this->tenant();
        $this->assertThrows(\App\Modules\Tenants\Exceptions\DuplicateEmailException::class, fn () => $this->users->create($t['tenantId'], 'Dup', 'BUDI@example.com', null, 'x', false, 'pending_verification', '2027-01-01 00:00:00'));
    }
}
