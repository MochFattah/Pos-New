<?php

declare(strict_types=1);

namespace Tests\Feature\Branches;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use Tests\Support\BranchHarness;

final class BranchTest extends BranchHarness
{
    /** @return array{tenantId: int, userId: int} */
    private function ownerLoggedIn(string $email = 'budi@example.com'): array
    {
        $r = $this->activeOwner($email);
        $this->assertSame(302, $this->login($email)->status());
        return $r;
    }

    /** Menjalankan stack penuh, mengubah HttpException menjadi status seperti Application. */
    private function status(string $method, string $uri, array $data = []): int
    {
        try {
            return $this->send($method, $uri, $data)->status();
        } catch (\App\Core\Exceptions\HttpException $e) {
            return $e->status();
        }
    }

    public function testBranchCodeUniquePerTenantButReusableAcrossTenants(): void
    {
        $a = $this->activeOwner('a@example.com');
        $b = $this->activeOwner('b@example.com');
        $ownerA = $this->authUserFor($a['tenantId'], $a['userId']);
        $ownerB = $this->authUserFor($b['tenantId'], $b['userId']);
        $this->branchService->create($ownerA, ['code' => 'CAB1', 'name' => 'Satu']);
        foreach (['CAB1', 'cab1', ' Cab1 '] as $dup) {
            try {
                $this->branchService->create($ownerA, ['code' => $dup, 'name' => 'Dua']);
                $this->fail('Kode duplikat harus ditolak: ' . $dup);
            } catch (ValidationException $e) {
                $this->assertSame(['code' => 'Kode cabang sudah dipakai cabang lain.'], $e->errors());
            }
        }
        $idB = $this->branchService->create($ownerB, ['code' => 'CAB1', 'name' => 'Satu B']);
        $this->assertSame($b['tenantId'], (int) $this->branchRepo->find($b['tenantId'], $idB)['tenant_id']);
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM branches WHERE tenant_id = ? AND code = ?', [$a['tenantId'], 'CAB1']));
        // UTAMA sudah dipakai cabang utama registrasi di tiap tenant.
        $this->assertThrows(ValidationException::class, fn () => $this->branchService->create($ownerA, ['code' => 'UTAMA', 'name' => 'x']));
    }

    public function testDatabaseUniqueConstraintRaceIsReportedAsValidationError(): void
    {
        $a = $this->activeOwner();
        $owner = $this->authUserFor($a['tenantId'], $a['userId']);
        // Repository yang "lupa" pra-cek: kendala unik DB tetap harus menjadi kesalahan validasi, bukan 500.
        $repo = new class ($this->db) extends \App\Modules\Branches\Repositories\BranchRepository {
            public function codeExists(int $tenantId, string $code, ?int $exceptId = null): bool
            {
                return false;
            }
        };
        $service = new \App\Modules\Branches\Services\BranchService($this->db, $repo, $this->policy, $this->makeAudit(), new \App\Core\Authorization\AccessDeniedRecorder($this->makeAudit()));
        $this->assertThrows(ValidationException::class, fn () => $service->create($owner, ['code' => 'UTAMA', 'name' => 'x']));
        $this->assertSame(0, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'branch.created'"));
    }

    public function testMainBranchCannotBeDeactivated(): void
    {
        $a = $this->activeOwner();
        $owner = $this->authUserFor($a['tenantId'], $a['userId']);
        $main = $this->mainBranchId($a['tenantId']);
        $this->assertThrows(ValidationException::class, fn () => $this->branchService->deactivate($owner, $main));
        $this->assertSame('active', $this->branchRepo->find($a['tenantId'], $main)['status']);
        $this->assertSame(0, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'branch.deactivated'"));
        // Lapis SQL juga menolak walau service dilewati.
        $this->assertFalse($this->branchRepo->setStatus($a['tenantId'], $main, 'inactive', '2027-01-01 00:00:00'));
        $this->assertSame('active', $this->branchRepo->find($a['tenantId'], $main)['status']);
    }

    public function testDeactivateAndActivateToggle(): void
    {
        $a = $this->activeOwner();
        $t = $a['tenantId'];
        $owner = $this->authUserFor($t, $a['userId']);
        $id = $this->branchService->create($owner, ['code' => 'CAB2', 'name' => 'Dua']);
        $this->branchService->deactivate($owner, $id);
        $this->assertSame('inactive', $this->branchRepo->find($t, $id)['status']);
        $this->branchService->deactivate($owner, $id); // idempoten
        $this->branchService->activate($owner, $id);
        $this->assertSame('active', $this->branchRepo->find($t, $id)['status']);
        $actions = array_column($this->db->query("SELECT action FROM audit_logs WHERE entity_type = 'branch' AND entity_id = ? ORDER BY id", [$id]), 'action');
        $this->assertSame(['branch.created', 'branch.deactivated', 'branch.activated'], $actions);
    }

    public function testBranchChangesAreAudited(): void
    {
        $a = $this->activeOwner();
        $t = $a['tenantId'];
        $owner = $this->authUserFor($t, $a['userId']);
        $id = $this->branchService->create($owner, ['code' => 'cab2', 'name' => 'Dua', 'phone' => '0812 3456 7890']);
        $this->branchService->update($owner, $id, ['code' => 'CAB2', 'name' => 'Dua Baru', 'address' => 'Jl. Mawar 1', 'phone' => '081234567890']);
        $this->branchService->update($owner, $id, ['code' => 'CAB2', 'name' => 'Dua Baru', 'address' => 'Jl. Mawar 1', 'phone' => '081234567890']); // tanpa perubahan
        $this->branchService->deactivate($owner, $id);
        $rows = $this->db->query("SELECT * FROM audit_logs WHERE entity_type = 'branch' AND entity_id = ? ORDER BY id", [$id]);
        $this->assertSame(['branch.created', 'branch.updated', 'branch.deactivated'], array_column($rows, 'action'));
        foreach ($rows as $row) {
            $this->assertSame($t, (int) $row['tenant_id']);
            $this->assertSame('tenant_user', $row['actor_type']);
            $this->assertSame($a['userId'], (int) $row['actor_id']);
        }
        $updated = json_decode((string) $rows[1]['metadata'], true);
        $this->assertSame(['from' => 'Dua', 'to' => 'Dua Baru'], $updated['changes']['name']);
        $this->assertFalse(isset($updated['changes']['code']), 'kode tidak berubah (hanya huruf besar)');
    }

    public function testTenantAUserGets404ForTenantBBranchId(): void
    {
        $a = $this->activeOwner('a@example.com');
        $b = $this->activeOwner('b@example.com');
        $ownerA = $this->authUserFor($a['tenantId'], $a['userId']);
        $branchB = $this->mainBranchId($b['tenantId']);
        $this->assertThrows(NotFoundException::class, fn () => $this->branchService->get($ownerA, $branchB));
        $this->assertThrows(NotFoundException::class, fn () => $this->branchService->forEdit($ownerA, $branchB));
        $this->assertSame(null, $this->branchRepo->find($a['tenantId'], $branchB));
    }

    public function testTenantAUserCannotUpdateOrDeactivateTenantBBranch(): void
    {
        $a = $this->activeOwner('a@example.com');
        $b = $this->activeOwner('b@example.com');
        $ownerA = $this->authUserFor($a['tenantId'], $a['userId']);
        $branchB = $this->seedBranch($b['tenantId'], 'CAB9', 'Milik B');
        $before = $this->branchRepo->find($b['tenantId'], $branchB);
        $this->assertThrows(NotFoundException::class, fn () => $this->branchService->update($ownerA, $branchB, ['code' => 'HACK', 'name' => 'Dibajak']));
        $this->assertThrows(NotFoundException::class, fn () => $this->branchService->deactivate($ownerA, $branchB));
        $this->assertThrows(NotFoundException::class, fn () => $this->branchService->activate($ownerA, $branchB));
        $this->assertSame($before, $this->branchRepo->find($b['tenantId'], $branchB));
        $this->assertSame(0, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE entity_type = 'branch' AND entity_id = ? AND action LIKE 'branch.%'", [$branchB]));
    }

    public function testCrossTenantAndNonexistentIdsAreRecordedUnderActorTenantOnly(): void
    {
        $a = $this->activeOwner('a@example.com');
        $b = $this->activeOwner('b@example.com');
        $ownerA = $this->authUserFor($a['tenantId'], $a['userId']);
        $branchB = $this->mainBranchId($b['tenantId']);
        $before = $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE tenant_id = ?", [$b['tenantId']]);
        $this->assertThrows(NotFoundException::class, fn () => $this->branchService->get($ownerA, $branchB));
        $this->assertThrows(NotFoundException::class, fn () => $this->branchService->get($ownerA, 987654));
        $rows = $this->db->query("SELECT * FROM audit_logs WHERE action = 'security.entity_not_found' ORDER BY id");
        $this->assertSame(2, count($rows));
        foreach ($rows as $row) {
            $this->assertSame($a['tenantId'], (int) $row['tenant_id']);
            $this->assertSame($a['userId'], (int) $row['actor_id']);
            $this->assertSame('branch', $row['entity_type']);
        }
        $this->assertSame($branchB, (int) $rows[0]['entity_id']);
        $this->assertSame($before, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE tenant_id = ?", [$b['tenantId']]));
    }

    public function testPermissionDeniedIsRecordedWithActorTenantAndCode(): void
    {
        $a = $this->activeOwner();
        $viewer = $this->authUserFor($a['tenantId'], $this->makeUser($a['tenantId'], 'v@example.com', ['branches.view']));
        $this->assertThrows(ForbiddenException::class, fn () => $this->policy->authorize($viewer, 'branches.manage'));
        $row = $this->db->fetchOne("SELECT * FROM audit_logs WHERE action = 'security.permission_denied'");
        $this->assertSame($a['tenantId'], (int) $row['tenant_id']);
        $this->assertSame($viewer->id, (int) $row['actor_id']);
        $this->assertContains('branches.manage', (string) $row['metadata']);
    }

    public function testServiceChecksPermissionBeforeLookup(): void
    {
        $a = $this->activeOwner();
        $viewer = $this->authUserFor($a['tenantId'], $this->makeUser($a['tenantId'], 'v@example.com', ['branches.view']));
        $main = $this->mainBranchId($a['tenantId']);
        $this->assertSame(1, count($this->branchService->list($viewer)));
        $this->assertThrows(ForbiddenException::class, fn () => $this->branchService->create($viewer, ['code' => 'X1', 'name' => 'x']));
        $this->assertThrows(ForbiddenException::class, fn () => $this->branchService->update($viewer, $main, ['code' => 'X1', 'name' => 'x']));
        $this->assertThrows(ForbiddenException::class, fn () => $this->branchService->deactivate($viewer, 999));
        $nobody = $this->authUserFor($a['tenantId'], $this->makeUser($a['tenantId'], 'n@example.com', []));
        $this->assertThrows(ForbiddenException::class, fn () => $this->branchService->list($nobody));
    }

    public function testBranchRestrictedManagerCannotEditOtherBranch(): void
    {
        $a = $this->activeOwner();
        $t = $a['tenantId'];
        $main = $this->mainBranchId($t);
        $other = $this->seedBranch($t, 'CAB2');
        $mgr = $this->authUserFor($t, $this->makeUser($t, 'm@example.com', ['branches.manage', 'branches.view'], [$main]));
        $this->branchService->update($mgr, $main, ['code' => 'UTAMA', 'name' => 'Pusat']);
        $this->assertThrows(ForbiddenException::class, fn () => $this->branchService->update($mgr, $other, ['code' => 'CAB2', 'name' => 'x']));
    }

    public function testValidationRules(): void
    {
        $a = $this->activeOwner();
        $owner = $this->authUserFor($a['tenantId'], $a['userId']);
        $bad = [
            ['code' => '', 'name' => 'x'],
            ['code' => 'A B', 'name' => 'x'],
            ['code' => '<b>', 'name' => 'x'],
            ['code' => str_repeat('A', 31), 'name' => 'x'],
            ['code' => 'OK1', 'name' => ''],
            ['code' => 'OK1', 'name' => str_repeat('n', 151)],
            ['code' => 'OK1', 'name' => 'x', 'phone' => 'abc'],
            ['code' => 'OK1', 'name' => 'x', 'address' => str_repeat('a', 501)],
            ['code' => ['x'], 'name' => 'x'],
        ];
        foreach ($bad as $input) {
            $this->assertThrows(ValidationException::class, fn () => $this->branchService->create($owner, $input));
        }
        $id = $this->branchService->create($owner, ['code' => 'ok_1-b', 'name' => '  Toko  ', 'address' => '', 'phone' => '']);
        $row = $this->branchRepo->find($a['tenantId'], $id);
        $this->assertSame('OK_1-B', $row['code']);
        $this->assertSame('Toko', $row['name']);
        $this->assertSame(null, $row['address']);
        $this->assertSame(null, $row['phone']);
    }

    public function testOwnerCreatesBranchThroughHttpWithTenantFromSession(): void
    {
        $a = $this->ownerLoggedIn();
        $b = $this->activeOwner('b@example.com');
        $form = $this->send('GET', '/cabang/baru');
        $this->assertSame(200, $form->status());
        $this->assertContains('Kode cabang', $form->body());
        $r = $this->send('POST', '/cabang', ['code' => 'bdg', 'name' => 'Bandung', 'tenant_id' => (string) $b['tenantId'], 'is_main' => '1', 'status' => 'inactive']);
        $this->assertSame(302, $r->status());
        $this->assertSame('/cabang', $r->header('Location'));
        $row = $this->db->fetchOne("SELECT * FROM branches WHERE code = 'BDG'");
        $this->assertSame($a['tenantId'], (int) $row['tenant_id']);
        $this->assertSame(0, (int) $row['is_main']);
        $this->assertSame('active', $row['status']);
        $index = $this->send('GET', '/cabang');
        $this->assertSame(200, $index->status());
        $this->assertContains('Bandung', $index->body());
        $this->assertContains('Cabang berhasil ditambahkan.', $index->body());
        $this->assertFalse(str_contains($this->send('GET', '/cabang')->body(), 'Cabang berhasil ditambahkan.'), 'flash hanya sekali');
    }

    public function testHttpValidationErrorRerendersFormWith422AndEscapes(): void
    {
        $this->ownerLoggedIn();
        $r = $this->send('POST', '/cabang', ['code' => '', 'name' => '<script>alert(1)</script>']);
        $this->assertSame(422, $r->status());
        $this->assertContains('Kode cabang wajib diisi.', $r->body());
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $r->body());
        $this->assertFalse(str_contains($r->body(), '<script>alert'));
        $this->assertContains('aria-invalid="true"', $r->body());
    }

    public function testHttpEditAndUpdate(): void
    {
        $a = $this->ownerLoggedIn();
        $id = $this->seedBranch($a['tenantId'], 'CAB2', 'Dua');
        $form = $this->send('GET', "/cabang/$id/ubah");
        $this->assertSame(200, $form->status());
        $this->assertContains('value="CAB2"', $form->body());
        $r = $this->send('POST', "/cabang/$id", ['code' => 'CAB2', 'name' => 'Dua Baru']);
        $this->assertSame(302, $r->status());
        $this->assertSame('Dua Baru', $this->branchRepo->find($a['tenantId'], $id)['name']);
        $dup = $this->send('POST', "/cabang/$id", ['code' => 'UTAMA', 'name' => 'x']);
        $this->assertSame(422, $dup->status());
        $this->assertContains('Kode cabang sudah dipakai cabang lain.', $dup->body());
    }

    public function testHttpDeactivateActivateAndMainBranchRefusal(): void
    {
        $a = $this->ownerLoggedIn();
        $id = $this->seedBranch($a['tenantId'], 'CAB2', 'Dua');
        $main = $this->mainBranchId($a['tenantId']);
        $this->assertSame(302, $this->send('POST', "/cabang/$id/nonaktifkan")->status());
        $this->assertSame('inactive', $this->branchRepo->find($a['tenantId'], $id)['status']);
        $this->assertContains('Nonaktif', $this->send('GET', '/cabang')->body());
        $this->assertSame(302, $this->send('POST', "/cabang/$id/aktifkan")->status());
        $this->assertSame('active', $this->branchRepo->find($a['tenantId'], $id)['status']);
        $r = $this->send('POST', "/cabang/$main/nonaktifkan");
        $this->assertSame(302, $r->status());
        $this->assertContains('Cabang utama tidak dapat dinonaktifkan.', $this->send('GET', '/cabang')->body());
        $this->assertSame('active', $this->branchRepo->find($a['tenantId'], $main)['status']);
    }

    public function testBranchRoutesThroughFullStackReturn404ForOtherTenantId(): void
    {
        $a = $this->ownerLoggedIn('a@example.com');
        $b = $this->activeOwner('b@example.com');
        $id = $this->seedBranch($b['tenantId'], 'CAB9', 'Milik B');
        $before = $this->branchRepo->find($b['tenantId'], $id);
        $this->assertSame(404, $this->status('GET', "/cabang/$id/ubah"));
        $this->assertSame(404, $this->status('POST', "/cabang/$id", ['code' => 'HACK', 'name' => 'x']));
        $this->assertSame(404, $this->status('POST', "/cabang/$id/nonaktifkan"));
        $this->assertSame(404, $this->status('POST', "/cabang/$id/aktifkan"));
        $this->assertSame(404, $this->status('GET', '/cabang/999999/ubah'), 'ID tak ada dan ID tenant lain tak dapat dibedakan');
        $this->assertSame($before, $this->branchRepo->find($b['tenantId'], $id));
        $this->assertFalse(str_contains($this->send('GET', '/cabang')->body(), 'Milik B'));
        $this->assertSame($a['tenantId'], $this->currentUser()->tenantId);
    }

    public function testNonNumericOrOddIdIs404(): void
    {
        $this->ownerLoggedIn();
        foreach (['abc', '0', '-1', '01', '1e3', '99999999999999999999'] as $id) {
            $this->assertSame(404, $this->status('GET', "/cabang/$id/ubah"), $id);
        }
    }

    public function testUserWithoutPermissionGets403OnBranchRoutes(): void
    {
        $a = $this->activeOwner();
        $this->makeUser($a['tenantId'], 'kosong@example.com', []);
        $this->assertSame(302, $this->login('kosong@example.com')->status());
        $main = $this->mainBranchId($a['tenantId']);
        $this->assertSame(403, $this->status('GET', '/cabang'));
        $this->assertSame(403, $this->status('GET', '/cabang/baru'));
        $this->assertSame(403, $this->status('POST', '/cabang', ['code' => 'X1', 'name' => 'x']));
        $this->assertSame(403, $this->status('GET', "/cabang/$main/ubah"));
        $this->assertSame(403, $this->status('POST', "/cabang/$main", ['code' => 'X1', 'name' => 'x']));
        $this->assertSame(403, $this->status('POST', "/cabang/$main/nonaktifkan"));
        $this->assertSame(403, $this->status('POST', "/cabang/$main/aktifkan"));
        $this->assertSame(0, $this->count("SELECT COUNT(*) AS n FROM branches WHERE code = 'X1'"));
    }

    public function testViewOnlyUserSeesListWithoutManageControlsAndGets403OnManage(): void
    {
        $a = $this->activeOwner();
        $this->makeUser($a['tenantId'], 'lihat@example.com', ['branches.view']);
        $this->assertSame(302, $this->login('lihat@example.com')->status());
        $index = $this->send('GET', '/cabang');
        $this->assertSame(200, $index->status());
        $this->assertContains('Cabang Utama', $index->body());
        $this->assertFalse(str_contains($index->body(), 'Tambah cabang'));
        $this->assertFalse(str_contains($index->body(), 'Nonaktifkan'));
        $this->assertSame(403, $this->status('GET', '/cabang/baru'));
        $this->assertSame(403, $this->status('POST', '/cabang', ['code' => 'X1', 'name' => 'x']));
    }

    public function testManageOnlyUserCanOpenEditForm(): void
    {
        $a = $this->activeOwner();
        $this->makeUser($a['tenantId'], 'kelola@example.com', ['branches.manage'], [$this->mainBranchId($a['tenantId'])]);
        $this->assertSame(302, $this->login('kelola@example.com')->status());
        $this->assertSame(200, $this->status('GET', '/cabang/baru'));
        $this->assertSame(200, $this->status('GET', '/cabang/' . $this->mainBranchId($a['tenantId']) . '/ubah'));
    }

    public function testAnonymousIsRedirectedToLoginAndCsrfIsEnforced(): void
    {
        $r = $this->send('GET', '/cabang');
        $this->assertSame(302, $r->status());
        $this->assertSame('/masuk', $r->header('Location'));
        $this->ownerLoggedIn();
        $noCsrf = $this->send('POST', '/cabang', ['code' => 'X1', 'name' => 'x'], false);
        $this->assertSame(419, $noCsrf->status());
        $this->assertSame(0, $this->count("SELECT COUNT(*) AS n FROM branches WHERE code = 'X1'"));
    }

    public function testIndexListsOnlyOwnTenantBranches(): void
    {
        $this->ownerLoggedIn('a@example.com');
        $b = $this->activeOwner('b@example.com');
        $this->seedBranch($b['tenantId'], 'RAHASIA', 'Cabang Rahasia B');
        $body = $this->send('GET', '/cabang')->body();
        $this->assertContains('Cabang Utama', $body);
        $this->assertFalse(str_contains($body, 'Rahasia'));
    }

    public function testPermissionMiddlewareWithoutProviderFailsClosed(): void
    {
        \App\Core\Middleware\PermissionMiddleware::provide(null);
        $this->ownerLoggedIn();
        $this->assertThrows(\LogicException::class, fn () => $this->send('GET', '/cabang'));
    }
}
