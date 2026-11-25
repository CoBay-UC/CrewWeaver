<?php

declare(strict_types=1);

namespace CrewWeaver\Controllers;

use CrewWeaver\Authentication\AuthenticationService;
use CrewWeaver\Authorization\AuthorizationService;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Models\ActivityRepository;
use CrewWeaver\Models\ProjectCopyRepository;
use CrewWeaver\Models\ProjectRepository;
use CrewWeaver\Models\ScopeDeletionRepository;
use CrewWeaver\Scheduling\SchedulingService;
use CrewWeaver\Storage\ScopeFileCleanup;
use CrewWeaver\Validation\Validator;
use Throwable;

class ProjectApiController
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected ProjectRepository $_projects;
    protected ProjectCopyRepository $_copies;
    protected SchedulingService $_scheduling;
    protected AuthenticationService $_authentication;
    protected AuthorizationService $_authorization;
    protected ActivityRepository $_activity;
    protected ScopeDeletionRepository $_deletions;
    protected ScopeFileCleanup $_fileCleanup;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(ProjectRepository $projects, ProjectCopyRepository $copies, SchedulingService $scheduling, AuthenticationService $authentication, AuthorizationService $authorization, ActivityRepository $activity, ScopeDeletionRepository $deletions, ScopeFileCleanup $fileCleanup)
    {
        $this->_projects=$projects; $this->_copies=$copies; $this->_scheduling=$scheduling; $this->_authentication=$authentication; $this->_authorization=$authorization; $this->_activity=$activity;
        $this->_deletions = $deletions;
        $this->_fileCleanup = $fileCleanup;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _scopeFromInput(Request $request): array
    {
        return ['team_id' => (int) $request->input('team_id'), 'sub_team_id' => $request->input('sub_team_id') ? (int) $request->input('sub_team_id') : null];
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function index(Request $request): Response
    {
        return Response::success('Projects retrieved.', $this->_projects->listActive(['team_id'=>$request->query('team_id'),'sub_team_id'=>$request->query('sub_team_id'),'status'=>$request->query('status'),'search'=>$request->query('search')]));
    }

    public function show(Request $request): Response
    {
        $project=$this->_projects->find((int)$request->parameter('id'));
        return $project===null ? Response::error('Project not found.',404) : Response::success('Project retrieved.',$project);
    }

    public function create(Request $request): Response
    {
        $validator=(new Validator($request->all()))->required('team_id','Team')->required('title','Title')->oneOf('status',['Planned','Active','On Hold','Completed','Cancelled'],'Status')->date('planned_start')->date('target_completion');
        if(!$validator->valid()){return Response::error('Validation failed.',422,$validator->errors());}
        $scope=$this->_scopeFromInput($request); $user=$this->_authentication->currentUser();
        if ($scope['sub_team_id'] !== null) {
            $subTeam = $this->_authorization->subTeamScope($scope['sub_team_id']);
            if ($subTeam === null || (int) $subTeam['team_id'] !== $scope['team_id']) {
                return Response::error('Select a sub-team that belongs to this team.', 422);
            }
        }
        if(!$this->_authorization->can('manage_project',$user,$scope)){return Response::error('Forbidden.',403);}
        $project=$this->_projects->create($request->all(),$user?->id()??0);
        $this->_activity->log($user?->id(),'project.created','project',(int)$project['id'],$scope+['project_id'=>$project['id']],null,$project,$request->ip());
        return Response::success('Project created successfully.',$project,201);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->parameter('id');
        $scope = $this->_authorization->projectScope($id);
        $user = $this->_authentication->currentUser();
        if ($scope === null) return Response::error('Project not found.', 404);
        if (!$this->_authorization->can('manage_project', $user, $scope)) return Response::error('Forbidden.', 403);
        $before = $this->_projects->find($id);
        if ($before === null) return Response::error('Project not found.', 404);
        if ($before['is_archived']) return Response::error('Restore this project before editing.', 409);

        $data = $request->all();
        $validator = (new Validator($data))->required('version', 'Version')->integer('version', 1)
            ->oneOf('status', ['Planned', 'Active', 'On Hold', 'Completed', 'Cancelled'], 'Status')
            ->date('planned_start')->date('target_completion');
        $errors = $validator->errors();
        if (array_key_exists('title', $data)
            && (!is_string($data['title']) || trim($data['title']) === '' || strlen($data['title']) > 960
                || ($length = preg_match_all('/./us', $data['title'])) === false || $length > 240)) {
            $errors['title'] = ['Enter a project title of at most 240 characters.'];
        }
        if (isset($data['description']) && !is_string($data['description'])) {
            $errors['description'] = ['Enter a text description.'];
        }
        if ($errors !== []) return Response::error('Validation failed.', 422, $errors);
        if (array_key_exists('team_id', $data) || array_key_exists('sub_team_id', $data)) {
            $destination = [
                'team_id' => (int) ($data['team_id'] ?? $scope['team_id']),
                'sub_team_id' => array_key_exists('sub_team_id', $data) ? ((int) $data['sub_team_id'] ?: null) : $scope['sub_team_id'],
            ];
            if ($destination['sub_team_id'] !== null) {
                $subTeam = $this->_authorization->subTeamScope((int) $destination['sub_team_id']);
                if ($subTeam === null || (int) $subTeam['team_id'] !== $destination['team_id']) {
                    return Response::error('Select a sub-team that belongs to this team.', 422);
                }
            }
            if (!$this->_authorization->can('manage_project', $user, $destination)) return Response::error('Forbidden.', 403);
            $data = $destination + $data;
        }
        try {
            $project = $this->_projects->update($id, $data, $user?->id() ?? 0);
        } catch (\RuntimeException $exception) {
            if ($exception instanceof \PDOException) throw $exception;
            return Response::error($exception->getMessage(), 409);
        }
        if ($project === null) return Response::error('Project not found.', 404);
        $this->_activity->log($user?->id(), 'project.updated', 'project', $id, $scope, $before, $project, $request->ip());
        return Response::success('Project updated successfully.', $project);
    }

    public function delete(Request $request): Response
    {
        $id = (int) $request->parameter('id');
        $scope = $this->_authorization->projectScope($id);
        $user = $this->_authentication->currentUser();
        if ($scope === null) return Response::error('Project not found.', 404);
        if (!$this->_authorization->can('manage_project', $user, $scope)) return Response::error('Forbidden.', 403);
        if ($request->input('confirmation') !== ScopeDeletionRepository::CONFIRMATION) {
            return Response::error('Enter the exact confirmation phrase to permanently delete the project.', 422);
        }
        try {
            $result = $this->_deletions->deleteProject($id, $scope, $user?->id() ?? 0, $request->ip());
        } catch (\RuntimeException $exception) {
            if ($exception instanceof \PDOException) throw $exception;
            return Response::error($exception->getMessage(), 409);
        }
        return Response::success('Project deleted.', [
            'deleted' => $result['deleted'],
            'pendingFiles' => $this->_fileCleanup->purge($result['files']),
        ]);
    }

    public function archive(Request $request): Response
    {
        $id=(int)$request->parameter('id');$scope=$this->_authorization->projectScope($id);$user=$this->_authentication->currentUser();
        if($scope===null){return Response::error('Project not found.',404);}if(!$this->_authorization->can('archive_project',$user,$scope)){return Response::error('Forbidden.',403);}
        if (!$this->_projects->archive($id, $user?->id() ?? 0)) return Response::error('Project is already archived.', 409);
        $project = $this->_projects->find($id);
        $this->_activity->log($user?->id(), 'project.archived', 'project', $id, $scope, null, ['title' => $project['title'] ?? null], $request->ip());
        return Response::success('Project archived successfully.');
    }

    public function restore(Request $request): Response
    {
        $id=(int)$request->parameter('id');$scope=$this->_authorization->projectScope($id);$user=$this->_authentication->currentUser();
        if($scope===null){return Response::error('Project not found.',404);}if(!$this->_authorization->can('archive_project',$user,$scope)){return Response::error('Forbidden.',403);}
        if (!$this->_projects->restore($id)) return Response::error('Project is not archived.', 409);
        try { $this->_scheduling->apply($id); } catch (Throwable $ignored) {}
        $project = $this->_projects->find($id);
        $this->_activity->log($user?->id(), 'project.restored', 'project', $id, $scope, null, ['title' => $project['title'] ?? null], $request->ip());
        return Response::success('Project restored successfully.', $project);
    }

    public function copy(Request $request): Response
    {
        $sourceId=(int)$request->parameter('id');$sourceScope=$this->_authorization->projectScope($sourceId);$destination=$this->_scopeFromInput($request);$user=$this->_authentication->currentUser();
        if($sourceScope===null){return Response::error('Project not found.',404);}if(!$this->_authorization->can('copy_project',$user,$sourceScope)||!$this->_authorization->can('manage_project',$user,$destination)){return Response::error('Forbidden.',403);}
        $validator=(new Validator($request->all()))->required('title','New project name')->required('team_id','Destination team')->date('planned_start');if(!$validator->valid()){return Response::error('Validation failed.',422,$validator->errors());}
        try{$id=$this->_copies->copy($sourceId,$request->all(),$user?->id()??0);$schedule=$this->_scheduling->apply($id);return Response::success('Project copied successfully.',['project'=>$this->_projects->find($id),'schedule'=>$schedule],201);}catch(Throwable $exception){return Response::error($exception->getMessage(),422);}
    }

    public function schedulePreview(Request $request): Response
    {
        $id=(int)$request->parameter('id');$scope=$this->_authorization->projectScope($id);if($scope===null){return Response::error('Project not found.',404);}if(!$this->_authorization->can('manage_project',$this->_authentication->currentUser(),$scope)){return Response::error('Forbidden.',403);}
        try{return Response::success('Schedule preview calculated.',$this->_scheduling->preview($id));}catch(Throwable $exception){return Response::error($exception->getMessage(),422);}
    }

    public function scheduleApply(Request $request): Response
    {
        $id=(int)$request->parameter('id');$scope=$this->_authorization->projectScope($id);if($scope===null){return Response::error('Project not found.',404);}if(!$this->_authorization->can('manage_project',$this->_authentication->currentUser(),$scope)){return Response::error('Forbidden.',403);}
        try{return Response::success('Schedule recalculated successfully.',$this->_scheduling->apply($id));}catch(Throwable $exception){return Response::error($exception->getMessage(),422);}
    }
}
