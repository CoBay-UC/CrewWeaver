<?php

declare(strict_types=1);

namespace CrewWeaver\Controllers;

use CrewWeaver\Authentication\AuthenticationService;
use CrewWeaver\Authorization\AuthorizationService;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Models\ActivityRepository;
use CrewWeaver\Models\ImportantDateRepository;
use CrewWeaver\Models\TeamRepository;
use CrewWeaver\Validation\Validator;

class ImportantDateApiController
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected ImportantDateRepository $_dates;
    protected AuthenticationService $_authentication;
    protected AuthorizationService $_authorization;
    protected TeamRepository $_teams;
    protected ActivityRepository $_activity;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(ImportantDateRepository $dates, AuthenticationService $authentication, AuthorizationService $authorization, TeamRepository $teams, ActivityRepository $activity)
    {
        $this->_dates = $dates;
        $this->_authentication = $authentication;
        $this->_authorization = $authorization;
        $this->_teams = $teams;
        $this->_activity = $activity;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _scope(array $data): ?array
    {
        $type = $data['scope_type'] ?? '';
        if ($type === 'global') {
            return ['scope_type' => 'global'];
        }
        if (!in_array($type, ['team', 'sub_team', 'project'], true)) {
            return null;
        }
        $id = filter_var($data[$type . '_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            return null;
        }
        return match ($type) {
            'project' => $this->_authorization->projectScope($id),
            'sub_team' => $this->_authorization->subTeamScope($id),
            'team' => $this->_teams->find($id) === null ? null : ['team_id' => $id],
        };
    }

    protected function _allowed(array $scope): bool
    {
        return empty($scope['is_archived'])
            && $this->_authorization->can('manage_dates', $this->_authentication->currentUser(), $scope);
    }

    protected function _validate(array $data): array
    {
        $errors = [];
        foreach (['title' => 240, 'event_date' => 10, 'end_date' => 10, 'event_time' => 8, 'description' => 16000] as $field => $maximum) {
            if (isset($data[$field])) {
                $length = is_string($data[$field]) ? preg_match_all('/./us', $data[$field]) : false;
                if ($length === false || $length > $maximum) {
                    $errors[$field] = ['Enter a valid value for this field.'];
                }
            }
        }
        if ($errors !== []) {
            return $errors;
        }
        $errors = (new Validator($data))->required('title', 'Title')->required('event_date', 'Date')
            ->date('event_date')->date('end_date')->oneOf('priority', ['low', 'normal', 'high', 'critical'], 'Priority')->errors();
        if (!empty($data['end_date']) && $data['end_date'] < ($data['event_date'] ?? '')) {
            $errors['end_date'] = ['The end date must be on or after the start date.'];
        }
        if (!empty($data['event_time']) && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $data['event_time'])) {
            $errors['event_time'] = ['Enter a valid time.'];
        }
        if (isset($data['colour']) && (!is_string($data['colour']) || !preg_match('/^#[0-9a-f]{6}$/iD', $data['colour']))) {
            $errors['colour'] = ['Enter a valid colour.'];
        }
        if (isset($data['icon']) && (!is_string($data['icon']) || !preg_match('/^[a-z0-9-]{1,80}$/D', $data['icon']))) {
            $errors['icon'] = ['Enter a valid Bootstrap icon name.'];
        }
        return $errors;
    }

    protected function _save(Request $request, ?array $before = null): Response
    {
        $data = array_replace($before ?? [], $request->all());
        $scope = $this->_scope($data);
        if ($scope === null) {
            return Response::error('Select an existing scope.', 422);
        }
        if (!$this->_allowed($scope)) {
            return Response::error('Forbidden.', 403);
        }
        $errors = $this->_validate($data);
        if ($errors !== []) {
            return Response::error('Validation failed.', 422, $errors);
        }
        foreach (['team_id', 'sub_team_id', 'project_id'] as $field) {
            $data[$field] = $field === $data['scope_type'] . '_id' ? $scope[$field] : null;
        }
        if (!empty($data['is_all_day'])) {
            $data['event_time'] = null;
        }
        $actor = $this->_authentication->currentUser();
        $date = $this->_dates->save($data, $actor->id(), $before === null ? null : (int) $before['id']);
        $this->_activity->log($actor->id(), $before === null ? 'important_date.created' : 'important_date.updated',
            'important_date', (int) $date['id'], $scope, $before, $date, $request->ip());
        return Response::success('Important date saved.', $date, $before === null ? 201 : 200);
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function index(Request $request): Response
    {
        return Response::success('Important dates retrieved.', $this->_dates->list([
            'team_id' => $request->query('team_id'), 'sub_team_id' => $request->query('sub_team_id'),
            'project_id' => $request->query('project_id'), 'from' => $request->query('from'), 'to' => $request->query('to'),
        ]));
    }

    public function create(Request $request): Response
    {
        return $this->_save($request);
    }

    public function update(Request $request): Response
    {
        $current = $this->_dates->find((int) $request->parameter('id'));
        if ($current === null) {
            return Response::error('Important date not found.', 404);
        }
        if (!$this->_allowed($this->_scope($current) ?? [])) {
            return Response::error('Forbidden.', 403);
        }
        return $this->_save($request, $current);
    }

    public function delete(Request $request): Response
    {
        $id = (int) $request->parameter('id');
        $current = $this->_dates->find($id);
        if ($current === null) {
            return Response::error('Important date not found.', 404);
        }
        $scope = $this->_scope($current) ?? [];
        if (!$this->_allowed($scope)) {
            return Response::error('Forbidden.', 403);
        }
        $this->_dates->delete($id);
        $this->_activity->log($this->_authentication->currentUser()?->id(), 'important_date.deleted',
            'important_date', $id, $scope, $current, null, $request->ip());
        return Response::success('Important date deleted.');
    }
}
