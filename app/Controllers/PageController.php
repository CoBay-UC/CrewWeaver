<?php

declare(strict_types=1);

namespace CrewWeaver\Controllers;

use CrewWeaver\Authentication\AuthenticationService;
use CrewWeaver\Authentication\CsrfToken;
use CrewWeaver\Authorization\AuthorizationService;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Core\View;
use CrewWeaver\Models\ActivityRepository;
use CrewWeaver\Models\DashboardRepository;
use CrewWeaver\Models\ImportantDateRepository;
use CrewWeaver\Models\NotificationRepository;
use CrewWeaver\Models\OrganizationSettingsRepository;
use CrewWeaver\Models\ProjectRepository;
use CrewWeaver\Models\ScopeDeletionRepository;
use CrewWeaver\Models\TaskRepository;
use CrewWeaver\Models\TeamRepository;
use CrewWeaver\Models\UserRepository;
use CrewWeaver\Models\VisualizationRepository;

class PageController
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected View $_view;
    protected AuthenticationService $_authentication;
    protected AuthorizationService $_authorization;
    protected CsrfToken $_csrf;
    protected TeamRepository $_teams;
    protected ProjectRepository $_projects;
    protected TaskRepository $_tasks;
    protected DashboardRepository $_dashboard;
    protected VisualizationRepository $_visualization;
    protected ImportantDateRepository $_dates;
    protected NotificationRepository $_notifications;
    protected ActivityRepository $_activity;
    protected UserRepository $_users;
    protected OrganizationSettingsRepository $_organizationSettings;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(View $view, AuthenticationService $authentication, AuthorizationService $authorization, CsrfToken $csrf, TeamRepository $teams, ProjectRepository $projects, TaskRepository $tasks, DashboardRepository $dashboard, VisualizationRepository $visualization, ImportantDateRepository $dates, NotificationRepository $notifications, ActivityRepository $activity, UserRepository $users, OrganizationSettingsRepository $organizationSettings)
    {
        $this->_view = $view;
        $this->_authentication = $authentication;
        $this->_authorization = $authorization;
        $this->_csrf = $csrf;
        $this->_teams = $teams;
        $this->_projects = $projects;
        $this->_tasks = $tasks;
        $this->_dashboard = $dashboard;
        $this->_visualization = $visualization;
        $this->_dates = $dates;
        $this->_notifications = $notifications;
        $this->_activity = $activity;
        $this->_users = $users;
        $this->_organizationSettings = $organizationSettings;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _base(string $title): array
    {
        $user = $this->_authentication->currentUser();
        $showSubTeams = $this->_organizationSettings->showsSidebarSubTeams();
        return [
            'title' => $title,
            'currentUser' => $user,
            'csrfToken' => $this->_csrf->value(),
            'navigationTeams' => $showSubTeams ? $this->_teams->hierarchy() : $this->_teams->all(),
            'showSidebarSubTeams' => $showSubTeams,
            'canManageScopes' => $this->_authorization->can('manage_scopes', $user),
            'unreadNotifications' => $user === null ? 0 : $this->_notifications->unreadCount($user->id()),
            'isGlobalAdmin' => $user?->isGlobalAdmin() ?? false,
        ];
    }

    protected function _scope(string $type, int $id): ?array
    {
        return match ($type) {
            'project' => $this->_projects->find($id),
            'sub-team' => $this->_teams->findSubTeam($id),
            'team' => $this->_teams->find($id),
            default => null,
        };
    }

    protected function _managedHierarchy(): array
    {
        $user = $this->_authentication->currentUser();
        $managed = [];
        foreach ($this->_teams->hierarchy(true) as $team) {
            $teamId = (int) $team['id'];
            $team['can_manage'] = $this->_authorization->can('manage_scope', $user, ['team_id' => $teamId]);
            $team['sub_teams'] = array_values(array_filter($team['sub_teams'], fn (array $child): bool =>
                $this->_authorization->can('manage_scope', $user, ['team_id' => $teamId, 'sub_team_id' => $child['id']])));
            if ($team['can_manage'] || $team['sub_teams'] !== []) {
                $managed[] = $team;
            }
        }
        return $managed;
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function dashboard(Request $request): Response
    {
        $user = $this->_authentication->currentUser();
        return $this->_view->render('Dashboard/index', $this->_base('Dashboard') + ['summary' => $this->_dashboard->summary($user?->id() ?? 0), 'activity' => $this->_activity->recentCollaboration(12)]);
    }

    public function teams(Request $request): Response
    {
        return $this->_view->render('Teams/index', $this->_base('Teams') + ['teams' => $this->_teams->all(true)]);
    }

    public function team(Request $request): Response
    {
        $team = $this->_teams->find((int) $request->parameter('id'));
        if ($team === null) { return new Response('Team not found.', 404); }
        return $this->_view->render('Teams/show', $this->_base($team['name']) + ['team' => $team, 'projects' => $this->_projects->listActive(['team_id' => $team['id']]), 'dates' => $this->_dates->list(['team_id' => $team['id']]), 'archivedCount' => $this->_projects->archivedCount((int) $team['id']), 'canManageScope' => $this->_authorization->can('manage_scope', $this->_authentication->currentUser(), ['team_id' => (int) $team['id'], 'sub_team_id' => null])]);
    }

    public function subTeam(Request $request): Response
    {
        $id = (int) $request->parameter('id');
        $scope = $this->_teams->findSubTeam($id);
        if ($scope === null) {
            return new Response('Sub-team not found.', 404);
        }
        return $this->_view->render('SubTeams/show', $this->_base($scope['name']) + [
            'scope' => $scope,
            'scopeType' => 'sub-team',
            'scopeId' => $id,
            'projects' => $this->_projects->listActive(['sub_team_id' => $id]),
            'dates' => $this->_dates->list(['sub_team_id' => $id]),
            'archivedCount' => $this->_projects->archivedCount((int) $scope['team_id'], $id),
            'canManageScope' => $this->_authorization->can('manage_project', $this->_authentication->currentUser(), [
                'team_id' => $scope['team_id'], 'sub_team_id' => $id,
            ]),
        ]);
    }

    public function project(Request $request): Response
    {
        $project = $this->_projects->find((int) $request->parameter('id'));
        if ($project === null) { return new Response('Project not found.', 404); }
        return $this->_view->render('Projects/show', $this->_base($project['title']) + ['project' => $project, 'tasks' => $this->_tasks->projectTasks((int) $project['id']), 'dates' => $this->_dates->list(['project_id' => $project['id']]), 'activity' => $this->_activity->recent(25, ['project_id' => $project['id'],'collaboration_only'=>true]), 'canManageScope' => $this->_authorization->can('manage_project', $this->_authentication->currentUser(), $project)]);
    }

    public function board(Request $request): Response
    {
        $type = $request->parameter('scope'); $id = (int) $request->parameter('id'); $scope = $this->_scope($type, $id);
        if ($scope === null) { return new Response('Scope not found.', 404); }
        $lanes=$type==='team'?array_merge(['Direct team projects'],array_column($scope['sub_teams'],'name')):[(string)($scope['title']??$scope['name'])];
        $user = $this->_authentication->currentUser();
        $tasks = $this->_visualization->board($type, $id);
        foreach ($tasks as &$task) {
            $task['can_edit'] = $this->_authorization->can('edit_task', $user, $task + [
                'is_task_member' => in_array($user?->id(), array_map('intval', array_column($task['members'], 'id')), true),
            ]);
        }
        unset($task);
        return $this->_view->render('Tasks/board', $this->_base('Kanban') + [
            'scopeType' => $type, 'scopeId' => $id, 'scope' => $scope,
            'tasks' => $tasks,
            'dates' => $this->_dates->list([$type === 'sub-team' ? 'sub_team_id' : $type . '_id' => $id]),
            'lanes' => $lanes,
        ]);
    }

    public function gantt(Request $request): Response
    {
        $type = $request->parameter('scope'); $id = (int) $request->parameter('id'); $scope = $this->_scope($type, $id);
        if ($scope === null) { return new Response('Scope not found.', 404); }
        return $this->_view->render('Gantt/index', $this->_base('Gantt') + ['scopeType' => $type, 'scopeId' => $id, 'scope' => $scope, 'rows' => $this->_visualization->gantt($type, $id)]);
    }

    public function calendar(Request $request): Response
    {
        $type = $request->parameter('scope'); $id = (int) $request->parameter('id'); $scope = $this->_scope($type, $id);
        if ($scope === null) { return new Response('Scope not found.', 404); }
        return $this->_view->render('Calendar/index', $this->_base('Calendar') + ['scopeType' => $type, 'scopeId' => $id, 'scope' => $scope]);
    }

    public function archive(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->query('search'),
            'team_id' => (int) $request->query('team_id'),
            'sub_team_id' => (int) $request->query('sub_team_id'),
        ];
        $projects = $this->_projects->archiveList($filters);
        foreach ($projects as &$project) {
            $project['can_restore'] = $this->_authorization->can('archive_project', $this->_authentication->currentUser(), $project);
        }
        return $this->_view->render('Archive/index', $this->_base('Archived projects') + [
            'projects' => $projects,
            'filters' => $filters,
        ]);
    }

    public function users(Request $request): Response
    {
        $user = $this->_authentication->currentUser();
        if (!$this->_authorization->can('manage_scopes', $user)) {
            return new Response('Forbidden.', 403);
        }
        $search = (string) $request->query('search');
        $status = $this->_authorization->can('manage_users', $user) ? (string) $request->query('status') : 'active';
        return $this->_view->render('Users/index', $this->_base('User administration') + [
            'users' => $this->_users->paginate($search, $status, max(1, (int) $request->query('page', 1)), 50),
            'search' => $search,
            'status' => $status,
            'managedTeams' => $this->_managedHierarchy(),
        ]);
    }

    public function manageTeams(Request $request): Response
    {
        if (!$this->_authorization->can('manage_scopes', $this->_authentication->currentUser())) {
            return new Response('Forbidden.', 403);
        }
        return $this->_view->render('Teams/manage', $this->_base('Team administration') + [
            'managedTeams' => $this->_managedHierarchy(),
            'deleteConfirmation' => ScopeDeletionRepository::CONFIRMATION,
        ]);
    }

    public function importantDates(Request $request): Response
    {
        $user = $this->_authentication->currentUser();
        $options = ['global' => [
            'name' => 'Organization',
            'can_edit' => $this->_authorization->can('manage_dates', $user, ['scope_type' => 'global']),
        ]];
        foreach ($this->_teams->hierarchy(true) as $team) {
            $options['team:' . $team['id']] = [
                'name' => $team['name'] . ' (team)',
                'can_edit' => $this->_authorization->can('manage_dates', $user, ['team_id' => $team['id']]),
            ];
            foreach ($team['sub_teams'] as $subTeam) {
                $options['sub_team:' . $subTeam['id']] = [
                    'name' => $team['name'] . ' / ' . $subTeam['name'] . ' (sub-team)',
                    'can_edit' => $this->_authorization->can('manage_dates', $user, [
                        'team_id' => $team['id'], 'sub_team_id' => $subTeam['id'],
                    ]),
                ];
            }
        }
        foreach ($this->_projects->listActive() as $project) {
            $options['project:' . $project['id']] = [
                'name' => $project['team_name'] . ' / ' . $project['title'] . ' (project)',
                'can_edit' => $this->_authorization->can('manage_dates', $user, $project),
            ];
        }
        $selected = (string) $request->query('scope', '');
        $period = (string) $request->query('period', '');
        $search = (string) $request->query('search', '');
        $sort = (string) $request->query('sort', 'soonest');
        $filters = [
            'id' => (int) $request->query('date', 0),
            'search' => $search,
            'sort' => $sort,
            'include_inactive' => $this->_authorization->can('manage_scopes', $user),
        ];
        if (isset($options[$selected])) {
            [$scopeType, $scopeId] = array_pad(explode(':', $selected, 2), 2, null);
            $filters += ['exact_scope' => $scopeType, 'exact_id' => (int) $scopeId];
        }
        if ($period === 'upcoming') {
            $filters['from'] = date('Y-m-d');
        } elseif ($period === 'past') {
            $filters['to'] = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        }

        $items = [];
        foreach ($this->_dates->list($filters) as $date) {
            $scopeType = $date['scope_type'];
            $key = $scopeType === 'global' ? 'global' : $scopeType . ':' . $date[$scopeType . '_id'];
            $date['scope_key'] = $key;
            $date['can_edit'] = $options[$key]['can_edit'] ?? false;
            if ($date['is_active'] || $date['can_edit']) {
                $items[] = $date;
            }
        }
        return $this->_view->render('Calendar/important-dates', $this->_base('Important Dates') + [
            'dateItems' => $items,
            'options' => $options,
            'selected' => $selected,
            'period' => $period,
            'search' => $search,
            'sort' => $sort,
            'focusedId' => $filters['id'],
        ]);
    }
}
