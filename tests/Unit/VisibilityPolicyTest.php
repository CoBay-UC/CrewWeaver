<?php

declare(strict_types=1);

namespace CrewWeaver\Tests\Unit;

use CrewWeaver\Authentication\AuthenticatedUser;
use CrewWeaver\Authorization\VisibilityPolicy;
use PHPUnit\Framework\TestCase;

class VisibilityPolicyTest extends TestCase
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function testApprovedMemberReadsOrganizationRegardlessOfMembership(): void
    {
        $user=new AuthenticatedUser(['id'=>1,'status'=>'active'],[],[['team_id'=>999]]);$policy=new VisibilityPolicy();self::assertTrue($policy->canReadOrganization($user));self::assertTrue($policy->canReadArchive($user));
    }

    public function testPendingAndInactiveAccountsCannotReadOrganization(): void
    {
        $policy=new VisibilityPolicy();self::assertFalse($policy->canReadOrganization(new AuthenticatedUser(['id'=>1,'status'=>'pending'],[],[])));self::assertFalse($policy->canReadOrganization(new AuthenticatedUser(['id'=>2,'status'=>'inactive'],[],[])));
    }

    public function testOnlyGlobalAdministratorReadsAdministrativeAccounts(): void
    {
        $policy=new VisibilityPolicy();$member=new AuthenticatedUser(['id'=>1,'status'=>'active'],[],[]);$admin=new AuthenticatedUser(['id'=>2,'status'=>'active'],[['role'=>'global_admin','team_id'=>null,'sub_team_id'=>null]],[]);self::assertFalse($policy->canReadAdministrativeAccounts($member));self::assertTrue($policy->canReadAdministrativeAccounts($admin));
    }
}
