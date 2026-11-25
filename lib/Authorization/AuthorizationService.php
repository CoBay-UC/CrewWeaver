<?php

declare(strict_types=1);

namespace CrewWeaver\Authorization;

use CrewWeaver\Authentication\AuthenticatedUser;
use CrewWeaver\Models\PermissionRepository;

class AuthorizationService
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected PermissionRepository $_permissions;
    protected VisibilityPolicy $_visibility;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(PermissionRepository $permissions, VisibilityPolicy $visibility)
    {
        $this->_permissions = $permissions;
        $this->_visibility = $visibility;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _administersScope(AuthenticatedUser $user, int $teamId, ?int $subTeamId): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }
        foreach ($user->roles() as $role) {
            if ($role['role'] === 'team_admin' && (int) $role['team_id'] === $teamId) {
                return true;
            }
            if ($role['role'] === 'sub_team_admin' && $subTeamId !== null
                && (int) $role['team_id'] === $teamId && (int) $role['sub_team_id'] === $subTeamId) {
                return true;
            }
        }
        return false;
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function can(string $capability, ?AuthenticatedUser $user, array $scope = []): bool
    {
        if ($capability === 'read_organization' || $capability === 'read_archive') {
            return $this->_visibility->canReadOrganization($user);
        }
        if ($user === null || !$user->isApproved()) {
            return false;
        }
        if (in_array($capability, ['manage_global', 'view_audit', 'manage_users'], true)) {
            return $user->isGlobalAdmin();
        }
        if ($capability === 'manage_dates' && ($scope['scope_type'] ?? '') === 'global') {
            return $user->isGlobalAdmin();
        }
        if ($capability === 'manage_scopes') {
            foreach ($user->roles() as $role) {
                if (in_array($role['role'], ['global_admin', 'team_admin', 'sub_team_admin'], true)) {
                    return true;
                }
            }
            return false;
        }
        $teamId = isset($scope['team_id']) ? (int) $scope['team_id'] : 0;
        $subTeamId = isset($scope['sub_team_id']) && $scope['sub_team_id'] !== null ? (int) $scope['sub_team_id'] : null;
        if (in_array($capability, ['manage_scope', 'manage_project', 'manage_dates', 'archive_project', 'copy_project', 'replace_user'], true)) {
            return $teamId > 0 && $this->_administersScope($user, $teamId, $subTeamId);
        }
        if (in_array($capability, ['edit_task', 'contribute_task'], true)) {
            return empty($scope['is_archived']) && ($this->_administersScope($user, $teamId, $subTeamId)
                || (int) ($scope['owner_user_id'] ?? 0) === $user->id()
                || (bool) ($scope['is_task_member'] ?? false));
        }
        if ($capability === 'claim_task') {
            return empty($scope['is_archived']) && empty($scope['owner_user_id'])
                && ($this->_administersScope($user, $teamId, $subTeamId) || (bool) ($scope['is_project_member'] ?? false));
        }
        return false;
    }

    public function taskScope(int $taskId, AuthenticatedUser $user): ?array
    {
        return $this->_permissions->taskScopeForUser($taskId, $user->id());
    }

    public function projectScope(int $projectId): ?array
    {
        return $this->_permissions->projectScope($projectId);
    }

    public function subTeamScope(int $subTeamId): ?array
    {
        return $this->_permissions->subTeamScope($subTeamId);
    }
}
