<?php

declare(strict_types=1);

namespace CrewWeaver\Tests\Unit;

use CrewWeaver\Authentication\AuthenticatedUser;
use CrewWeaver\Authorization\AuthorizationService;
use CrewWeaver\Authorization\VisibilityPolicy;
use CrewWeaver\Models\PermissionRepository;
use PHPUnit\Framework\TestCase;

class AuthorizationServiceTest extends TestCase
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
    protected function _service(): AuthorizationService
    {
        $repository=new class extends PermissionRepository { public function __construct() {} };
        return new AuthorizationService($repository,new VisibilityPolicy());
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function testCrossTeamReadDoesNotGrantCrossTeamWrite(): void
    {
        $member=new AuthenticatedUser(['id'=>7,'status'=>'active'],[],[['team_id'=>1]]);$service=$this->_service();self::assertTrue($service->can('read_organization',$member));self::assertFalse($service->can('edit_task',$member,['team_id'=>2,'sub_team_id'=>null,'owner_user_id'=>null,'is_task_member'=>false]));
    }

    public function testExplicitAssignmentGrantsTaskParticipationWithoutScopeAdministration(): void
    {
        $member=new AuthenticatedUser(['id'=>7,'status'=>'active'],[],[['team_id'=>1]]);$service=$this->_service();$scope=['team_id'=>2,'sub_team_id'=>4,'owner_user_id'=>null,'is_task_member'=>true];self::assertTrue($service->can('edit_task',$member,$scope));self::assertTrue($service->can('contribute_task',$member,$scope));self::assertFalse($service->can('manage_project',$member,$scope));
    }

    public function testTeamAdministratorInheritsDownwardButNotSideways(): void
    {
        $admin=new AuthenticatedUser(['id'=>8,'status'=>'active'],[['role'=>'team_admin','team_id'=>2,'sub_team_id'=>null]],[]);$service=$this->_service();self::assertTrue($service->can('manage_project',$admin,['team_id'=>2,'sub_team_id'=>9]));self::assertFalse($service->can('manage_project',$admin,['team_id'=>3,'sub_team_id'=>9]));
    }

    public function testSubTeamAdminCanManageOnlyTheirValidScope(): void
    {
        $admin = new AuthenticatedUser(['id' => 9, 'status' => 'active'], [
            ['role' => 'sub_team_admin', 'team_id' => 2, 'sub_team_id' => 9],
        ], []);
        $service = $this->_service();
        self::assertTrue($service->can('manage_scopes', $admin));
        self::assertTrue($service->can('manage_scope', $admin, ['team_id' => 2, 'sub_team_id' => 9]));
        self::assertFalse($service->can('manage_scope', $admin, ['team_id' => 2]));
        self::assertFalse($service->can('manage_scope', $admin, ['team_id' => 2, 'sub_team_id' => 8]));
        self::assertFalse($service->can('manage_scope', $admin, ['team_id' => 3, 'sub_team_id' => 9]));
        self::assertFalse($service->can('manage_users', $admin));
        self::assertFalse($service->can('manage_scopes', new AuthenticatedUser(['id' => 10, 'status' => 'active'], [], [])));
    }

    public function testImportantDateVisibilityDoesNotGrantEditingRights(): void
    {
        $service = $this->_service();
        $teamAdmin = new AuthenticatedUser(['id' => 8, 'status' => 'active'], [
            ['role' => 'team_admin', 'team_id' => 2, 'sub_team_id' => null],
        ], []);
        $globalAdmin = new AuthenticatedUser(['id' => 9, 'status' => 'active'], [['role' => 'global_admin']], []);

        self::assertTrue($service->can('read_organization', $teamAdmin));
        self::assertTrue($service->can('manage_dates', $teamAdmin, ['team_id' => 2, 'sub_team_id' => 9]));
        self::assertFalse($service->can('manage_dates', $teamAdmin, ['team_id' => 3]));
        self::assertFalse($service->can('manage_dates', $teamAdmin, ['scope_type' => 'global']));
        self::assertTrue($service->can('manage_dates', $globalAdmin, ['scope_type' => 'global']));
    }
}
