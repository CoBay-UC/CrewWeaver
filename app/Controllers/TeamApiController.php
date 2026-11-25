<?php

declare(strict_types=1);

namespace CrewWeaver\Controllers;

use CrewWeaver\Authentication\AuthenticationService;
use CrewWeaver\Authorization\AuthorizationService;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Models\ActivityRepository;
use CrewWeaver\Models\OrganizationSettingsRepository;
use CrewWeaver\Models\ScopeDeletionRepository;
use CrewWeaver\Models\TeamRepository;
use CrewWeaver\Storage\ScopeFileCleanup;
use PDOException;
use RuntimeException;

class TeamApiController
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected TeamRepository $_teams;
    protected AuthenticationService $_authentication;
    protected AuthorizationService $_authorization;
    protected ActivityRepository $_activity;
    protected OrganizationSettingsRepository $_settings;
    protected ScopeDeletionRepository $_deletions;
    protected ScopeFileCleanup $_fileCleanup;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(TeamRepository $teams, AuthenticationService $authentication, AuthorizationService $authorization, ActivityRepository $activity, OrganizationSettingsRepository $settings, ScopeDeletionRepository $deletions, ScopeFileCleanup $fileCleanup)
    {
        $this->_teams = $teams;
        $this->_authentication = $authentication;
        $this->_authorization = $authorization;
        $this->_activity = $activity;
        $this->_settings = $settings;
        $this->_deletions = $deletions;
        $this->_fileCleanup = $fileCleanup;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _canManage(array $scope): bool
    {
        return $this->_authorization->can('manage_scope', $this->_authentication->currentUser(), $scope);
    }

    protected function _isGlobalAdmin(): bool
    {
        return $this->_authorization->can('manage_global', $this->_authentication->currentUser());
    }

    protected function _save(Request $request, bool $subTeam, ?array $before, array $scope, array $data): Response
    {
        $errors = [];
        foreach (['name' => 160, 'slug' => 180, 'icon' => 80, 'description' => 16000] as $field => $maximum) {
            if (!array_key_exists($field, $data) || ($data[$field] === null && in_array($field, ['slug', 'description'], true))) {
                continue;
            }
            $length = is_string($data[$field]) && strlen($data[$field]) <= $maximum * 4
                ? preg_match_all('/./us', $data[$field]) : false;
            if ($length === false || $length > $maximum) {
                $errors[$field] = ["Enter valid text of at most {$maximum} characters."];
            }
        }
        if ($errors === []) {
            if (trim($data['name'] ?? $before['name'] ?? '') === '') {
                $errors['name'] = ['Name is required.'];
            }
            if (isset($data['slug']) && $data['slug'] !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $data['slug'])) {
                $errors['slug'] = ['Use lowercase letters, digits, and hyphens.'];
            }
            if (isset($data['icon']) && !preg_match('/^[a-z0-9-]+$/D', $data['icon'])) {
                $errors['icon'] = ['Enter a Bootstrap icon name.'];
            }
        }
        if (isset($data['colour']) && (!is_string($data['colour']) || !preg_match('/^#[0-9a-f]{6}$/iD', $data['colour']))) {
            $errors['colour'] = ['Enter a six-digit hexadecimal colour.'];
        }
        if (isset($data['display_order']) && filter_var($data['display_order'], FILTER_VALIDATE_INT,
            ['options' => ['min_range' => -2147483648, 'max_range' => 2147483647]]) === false) {
            $errors['display_order'] = ['Enter a 32-bit whole number.'];
        }
        if ($errors !== []) {
            return Response::error('Validation failed.', 422, $errors);
        }
        if (($data['slug'] ?? null) === '') {
            $data['slug'] = null;
        }
        try {
            $record = match (true) {
                $subTeam && $before === null => $this->_teams->createSubTeam($data),
                $subTeam => $this->_teams->updateSubTeam((int) $before['id'], $data),
                $before === null => $this->_teams->create($data),
                default => $this->_teams->update((int) $before['id'], $data),
            };
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return Response::error('This slug is already in use in the selected scope.', 409);
            }
            throw $exception;
        }
        if (!$record) {
            return Response::error('Scope not found.', 404);
        }

        $type = $subTeam ? 'sub_team' : 'team';
        $recordId = (int) $record['id'];
        $auditScope = $subTeam ? $scope + ['sub_team_id' => $recordId] : ['team_id' => $recordId];
        $this->_activity->log($this->_authentication->currentUser()?->id(), $type . ($before === null ? '.created' : '.updated'),
            $type, $recordId, $auditScope, $before, $record, $request->ip());
        return Response::success($subTeam ? 'Sub-team saved.' : 'Team saved.', $record, $before === null ? 201 : 200);
    }

    protected function _delete(Request $request, bool $subTeam, array $before): Response
    {
        if ($request->input('confirmation') !== ScopeDeletionRepository::CONFIRMATION) {
            return Response::error('Type the exact confirmation phrase to permanently delete this scope.', 422);
        }
        $id = (int) $before['id'];
        $actor = $this->_authentication->currentUser();
        try {
            $result = $this->_deletions->delete($id, $subTeam, $actor->id(), $request->ip());
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                return Response::error('A record outside this scope still references it. No data was deleted.', 409);
            }
            throw $exception;
        } catch (RuntimeException $exception) {
            return Response::error($exception->getMessage(), 409);
        }
        $pendingFiles = $this->_fileCleanup->purge($result['files']);
        return Response::success($subTeam ? 'Sub-team permanently deleted.' : 'Team permanently deleted.', [
            'deleted' => $result['deleted'],
            'pendingFiles' => $pendingFiles,
        ]);
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function index(Request $request): Response
    {
        return Response::success('Teams retrieved.', $this->_teams->all((bool) $request->query('include_inactive')));
    }

    public function create(Request $request): Response
    {
        return $this->_isGlobalAdmin()
            ? $this->_save($request, false, null, [], $request->all())
            : Response::error('Forbidden.', 403);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->parameter('id');
        if (!$this->_canManage(['team_id' => $id])) {
            return Response::error('Forbidden.', 403);
        }
        $before = $this->_teams->find($id);
        return $before === null ? Response::error('Team not found.', 404) : $this->_save($request, false, $before, [], $request->all());
    }

    public function delete(Request $request): Response
    {
        if (!$this->_isGlobalAdmin()) {
            return Response::error('Forbidden.', 403);
        }
        $before = $this->_teams->find((int) $request->parameter('id'));
        return $before === null ? Response::error('Team not found.', 404) : $this->_delete($request, false, $before);
    }

    public function createSubTeam(Request $request): Response
    {
        $teamId = (int) $request->input('team_id');
        $scope = ['team_id' => $teamId];
        if (!$this->_canManage($scope)) {
            return Response::error('Forbidden.', 403);
        }
        if ($this->_teams->find($teamId) === null) {
            return Response::error('Parent team not found.', 404);
        }
        return $this->_save($request, true, null, $scope, $scope + $request->all());
    }

    public function updateSubTeam(Request $request): Response
    {
        $id = (int) $request->parameter('id');
        $before = $this->_teams->findSubTeam($id);
        if ($before === null) {
            return Response::error('Sub-team not found.', 404);
        }
        $scope = ['team_id' => (int) $before['team_id'], 'sub_team_id' => $id];
        return $this->_canManage($scope)
            ? $this->_save($request, true, $before, $scope, $request->all())
            : Response::error('Forbidden.', 403);
    }

    public function deleteSubTeam(Request $request): Response
    {
        $id = (int) $request->parameter('id');
        $before = $this->_teams->findSubTeam($id);
        if ($before === null) {
            return Response::error('Sub-team not found.', 404);
        }
        return $this->_canManage(['team_id' => (int) $before['team_id'], 'sub_team_id' => $id])
            ? $this->_delete($request, true, $before)
            : Response::error('Forbidden.', 403);
    }

    public function updateSidebar(Request $request): Response
    {
        if (!$this->_isGlobalAdmin()) {
            return Response::error('Forbidden.', 403);
        }
        $show = $request->input('show_sub_teams');
        if (!is_bool($show)) {
            return Response::error('Select whether to show sub-teams.', 422);
        }
        $before = $this->_settings->showsSidebarSubTeams();
        $this->_settings->setSidebarSubTeams($show);
        if ($before !== $show) {
            $this->_activity->log($this->_authentication->currentUser()?->id(), 'organization.sidebar_updated',
                'organization_settings', null, [], ['show_sub_teams' => $before], ['show_sub_teams' => $show], $request->ip());
        }
        return Response::success('Navigation setting saved.', ['show_sub_teams' => $show]);
    }
}
