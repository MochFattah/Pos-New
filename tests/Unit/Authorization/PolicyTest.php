<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Core\Exceptions\ForbiddenException;
use Tests\Support\BranchHarness;

final class PolicyTest extends BranchHarness
{
    public function testOwnerCanEverythingInOwnTenant(): void
    {
        $a = $this->activeOwner('a@example.com');
        $b = $this->activeOwner('b@example.com');
        $owner = $this->authUserFor($a['tenantId'], $a['userId']);
        foreach (['branches.manage', 'users.manage', 'audit_logs.view', 'modul_baru.apa_saja'] as $permission) {
            $this->assertTrue($this->policy->can($owner, $permission), $permission);
        }
        $this->assertTrue($this->policy->can($owner, 'branches.manage', $this->mainBranchId($a['tenantId'])));
        // Cabang tenant lain tidak pernah dapat dijangkau, bahkan oleh Owner.
        $this->assertFalse($this->policy->can($owner, 'branches.manage', $this->mainBranchId($b['tenantId'])));
        $this->assertFalse($this->policy->can($owner, 'branches.manage', 999999));
    }

    public function testMalformedPermissionCodeIsDeniedEvenForOwner(): void
    {
        $a = $this->activeOwner();
        $owner = $this->authUserFor($a['tenantId'], $a['userId']);
        foreach (['', 'branches', 'Branches.Manage', "branches.manage\n", '*', 'a.b; DROP'] as $code) {
            $this->assertFalse($this->policy->can($owner, $code), var_export($code, true));
        }
    }

    public function testRoleWithoutPermissionIsDenied(): void
    {
        $a = $this->activeOwner();
        $uid = $this->makeUser($a['tenantId'], 'kasir@example.com', ['branches.view']);
        $user = $this->authUserFor($a['tenantId'], $uid);
        $this->assertTrue($this->policy->can($user, 'branches.view'));
        $this->assertFalse($this->policy->can($user, 'branches.manage'));
        $this->assertFalse($this->policy->can($user, 'users.view'));
        $none = $this->authUserFor($a['tenantId'], $this->makeUser($a['tenantId'], 'kosong@example.com', []));
        $this->assertFalse($this->policy->can($none, 'branches.view'));
    }

    public function testAuthorizeThrowsForbiddenWhenDenied(): void
    {
        $a = $this->activeOwner();
        $user = $this->authUserFor($a['tenantId'], $this->makeUser($a['tenantId'], 'kasir@example.com', ['branches.view']));
        $this->policy->authorize($user, 'branches.view');
        $this->assertThrows(ForbiddenException::class, fn () => $this->policy->authorize($user, 'branches.manage'));
    }

    public function testBranchRestrictedUserDeniedOtherBranch(): void
    {
        $a = $this->activeOwner();
        $t = $a['tenantId'];
        $main = $this->mainBranchId($t);
        $other = $this->seedBranch($t, 'CAB2');
        $user = $this->authUserFor($t, $this->makeUser($t, 'gudang@example.com', ['branches.view'], [$main]));
        $this->assertTrue($this->policy->can($user, 'branches.view', $main));
        $this->assertFalse($this->policy->can($user, 'branches.view', $other));
        $this->assertTrue($this->policy->can($user, 'branches.view'), 'tanpa cabang = cek permission saja');
    }

    public function testUserWithoutAnyBranchAccessIsDeniedForAnyBranch(): void
    {
        $a = $this->activeOwner();
        $user = $this->authUserFor($a['tenantId'], $this->makeUser($a['tenantId'], 'x@example.com', ['branches.view']));
        $this->assertFalse($this->policy->can($user, 'branches.view', $this->mainBranchId($a['tenantId'])));
    }

    public function testPermissionDoesNotLeakAcrossBranchOfOtherTenant(): void
    {
        $a = $this->activeOwner('a@example.com');
        $b = $this->activeOwner('b@example.com');
        $user = $this->authUserFor($a['tenantId'], $this->makeUser($a['tenantId'], 'x@example.com', ['branches.view'], [$this->mainBranchId($a['tenantId'])]));
        $this->assertFalse($this->policy->can($user, 'branches.view', $this->mainBranchId($b['tenantId'])));
    }
}
