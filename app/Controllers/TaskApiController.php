<?php

declare(strict_types=1);

namespace CrewWeaver\Controllers;

use CrewWeaver\Authentication\AuthenticationService;
use CrewWeaver\Authorization\AuthorizationService;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Models\ActivityRepository;
use CrewWeaver\Models\AttachmentRepository;
use CrewWeaver\Models\NotificationRepository;
use CrewWeaver\Models\TaskRepository;
use CrewWeaver\Scheduling\SchedulingService;
use CrewWeaver\Validation\Validator;
use Throwable;

class TaskApiController
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected TaskRepository $_tasks;
    protected SchedulingService $_scheduling;
    protected AuthenticationService $_authentication;
    protected AuthorizationService $_authorization;
    protected ActivityRepository $_activity;
    protected NotificationRepository $_notifications;
    protected AttachmentRepository $_attachments;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(TaskRepository $tasks, SchedulingService $scheduling, AuthenticationService $authentication, AuthorizationService $authorization, ActivityRepository $activity, NotificationRepository $notifications, AttachmentRepository $attachments)
    {
        $this->_tasks=$tasks;$this->_scheduling=$scheduling;$this->_authentication=$authentication;$this->_authorization=$authorization;$this->_activity=$activity;$this->_notifications=$notifications;$this->_attachments=$attachments;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _scope(int $taskId): ?array
    {
        $user=$this->_authentication->currentUser();return $user===null?null:$this->_authorization->taskScope($taskId,$user);
    }

    protected function _canEdit(int $taskId): ?Response
    {
        $scope=$this->_scope($taskId);if($scope===null){return Response::error('Task not found.',404);}if(!$this->_authorization->can('edit_task',$this->_authentication->currentUser(),$scope)){return Response::error('Forbidden.',403);}return null;
    }

    protected function _recalculate(int $projectId): array
    {
        try{return $this->_scheduling->apply($projectId);}catch(Throwable $exception){return ['warnings'=>[['type'=>'schedule_error','message'=>$exception->getMessage()]]];}
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function show(Request $request): Response
    {
        $task=$this->_tasks->find((int)$request->parameter('id'));if($task===null){return Response::error('Task not found.',404);}
        $task['comments']=$this->_tasks->comments((int)$task['id']);$task['research']=$this->_tasks->research((int)$task['id']);$task['attachments']=$this->_attachments->taskFiles((int)$task['id']);
        $task['activity'] = $this->_activity->taskHistory((int) $task['id']);
        $scope=$this->_scope((int)$task['id']);$task['permissions']=['edit'=>$scope!==null&&$this->_authorization->can('edit_task',$this->_authentication->currentUser(),$scope),'contribute'=>$scope!==null&&$this->_authorization->can('contribute_task',$this->_authentication->currentUser(),$scope),'claim'=>$scope!==null&&$this->_authorization->can('claim_task',$this->_authentication->currentUser(),$scope)];
        return Response::success('Task retrieved.',$task);
    }

    public function create(Request $request): Response
    {
        $validator=(new Validator($request->all()))->required('project_id','Project')->required('title','Title')->oneOf('status',['Not Started','Researching','In-Progress','Needs Outside Input','Needs Review','Done'],'Status')->integer('estimated_duration_days',0,3650);
        if(!$validator->valid()){return Response::error('Validation failed.',422,$validator->errors());}
        $projectScope=$this->_authorization->projectScope((int)$request->input('project_id'));$user=$this->_authentication->currentUser();if($projectScope===null){return Response::error('Project not found.',404);}if(!$this->_authorization->can('manage_project',$user,$projectScope)){return Response::error('Forbidden.',403);}
        $task=$this->_tasks->create($request->all(),$user?->id()??0);$schedule=$this->_recalculate((int)$task['project_id']);
        $task = $this->_tasks->find((int) $task['id']) ?? $task;
        $this->_activity->log($user?->id(),'task.created','task',(int)$task['id'],$projectScope+['project_id'=>$task['project_id']],null,$task,$request->ip());
        return Response::success('Task created successfully.',['task'=>$task,'schedule'=>$schedule],201);
    }

    public function update(Request $request): Response
    {
        $id=(int)$request->parameter('id');if($denied=$this->_canEdit($id)){return $denied;}$before=$this->_tasks->find($id);$user=$this->_authentication->currentUser();
        try{$task=$this->_tasks->update($id,$request->all(),$user?->id()??0);if($task===null){return Response::error('Task not found.',404);}$schedule=$this->_recalculate((int)$task['project_id']);$task=$this->_tasks->find($id)??$task;$this->_activity->log($user?->id(),'task.updated','task',$id,$task,$before,$task,$request->ip());return Response::success('Task updated successfully.',['task'=>$task,'schedule'=>$schedule]);}
        catch(Throwable $exception){return Response::error($exception->getMessage(),409);}
    }

    public function delete(Request $request): Response
    {
        $id=(int)$request->parameter('id');$scope=$this->_scope($id);if($scope===null){return Response::error('Task not found.',404);}if(!$this->_authorization->can('manage_project',$this->_authentication->currentUser(),$scope)){return Response::error('Forbidden.',403);}$this->_tasks->delete($id);$this->_recalculate((int)$scope['project_id']);return Response::success('Task deleted successfully.');
    }

    public function boardPosition(Request $request): Response
    {
        $id=(int)$request->parameter('id');if($denied=$this->_canEdit($id)){return $denied;}$validator=(new Validator($request->all()))->required('status','Status')->required('order','Order')->required('version','Version')->oneOf('status',['Not Started','Researching','In-Progress','Needs Outside Input','Needs Review','Done'],'Status');if(!$validator->valid()){return Response::error('Validation failed.',422,$validator->errors());}
        $before = $this->_tasks->find($id);
        if ($before === null) return Response::error('Task not found.', 404);
        try {
            $task = $this->_tasks->updateBoardPosition($id, (string) $request->input('status'), (float) $request->input('order'), (int) $request->input('version'));
        } catch (\RuntimeException $exception) {
            if ($exception instanceof \PDOException) throw $exception;
            return Response::error($exception->getMessage(), 409);
        }
        if ($task === []) return Response::error('Task not found.', 404);
        $position = array_flip(['title', 'project_title', 'status', 'kanban_order']);
        $this->_activity->log($this->_authentication->currentUser()?->id(), 'task.position_updated', 'task', $id, $task,
            array_intersect_key($before, $position), array_intersect_key($task, $position), $request->ip());
        return Response::success('Task position updated.', $task);
    }

    public function owner(Request $request): Response
    {
        $id=(int)$request->parameter('id');$scope=$this->_scope($id);$user=$this->_authentication->currentUser();if($scope===null){return Response::error('Task not found.',404);}$owner=$request->input('owner_user_id');$ownerId=$owner===null||$owner===''?null:(int)$owner;
        $claim=$ownerId===$user?->id()&&!$scope['owner_user_id'];$relinquish=$ownerId===null&&(int)$scope['owner_user_id']===$user?->id();$administrative=$this->_authorization->can('manage_project',$user,$scope);if(!($claim?$this->_authorization->can('claim_task',$user,$scope):($relinquish||$administrative))){return Response::error('Forbidden.',403);}
        $task=$this->_tasks->transferOwner($id,$ownerId,$user?->id()??0,(string)$request->input('reason',''));if($ownerId!==null&&$ownerId!==$user?->id()){$this->_notifications->create($ownerId,'ownership','Task ownership transferred','You now own “'.$task['title'].'”.','task',$id,'/projects/'.$task['project_id'].'?task='.$id,'task-owner-'.$id);}
        return Response::success('Task ownership updated.',$task);
    }

    public function milestones(Request $request): Response
    {
        $id=(int)$request->parameter('id');if($denied=$this->_canEdit($id)){return $denied;}try{$items=$this->_tasks->saveMilestones($id,(array)$request->input('milestones',[]));$task=$this->_tasks->find($id);$schedule=$this->_recalculate((int)$task['project_id']);return Response::success('Milestones saved.',['milestones'=>$items,'schedule'=>$schedule]);}catch(Throwable $exception){return Response::error($exception->getMessage(),422);}
    }

    public function dependencies(Request $request): Response
    {
        $id=(int)$request->parameter('id');if($denied=$this->_canEdit($id)){return $denied;}$task=$this->_tasks->find($id);$previous=array_values(array_map(static fn(array $item):array=>['task_id'=>(int)$item['predecessor_task_id'],'lag_days'=>(int)$item['lag_days']],array_filter($task['dependencies'],static fn(array $item):bool=>(int)$item['successor_task_id']===$id)));
        try{$items=$this->_tasks->saveDependencies($id,(array)$request->input('dependencies',[]));$this->_scheduling->validateDependencies((int)$task['project_id']);$schedule=$this->_recalculate((int)$task['project_id']);return Response::success('Dependencies saved.',['dependencies'=>$items,'schedule'=>$schedule]);}
        catch(Throwable $exception){$this->_tasks->saveDependencies($id,$previous);return Response::error($exception->getMessage(),422);}
    }

    public function addComment(Request $request): Response
    {
        $id=(int)$request->parameter('id');$scope=$this->_scope($id);$user=$this->_authentication->currentUser();if($scope===null){return Response::error('Task not found.',404);}if(!$this->_authorization->can('contribute_task',$user,$scope)){return Response::error('Forbidden.',403);}$validator=(new Validator($request->all()))->required('body','Comment');if(!$validator->valid()){return Response::error('Validation failed.',422,$validator->errors());}$mentions=(array)$request->input('mention_user_ids',[]);try{$comment=$this->_tasks->addComment($id,$user?->id()??0,(string)$request->input('body'),$mentions);$task=$this->_tasks->find($id);foreach(array_unique(array_merge($this->_tasks->participantIds($id),array_map('intval',$mentions))) as $recipient){if($recipient===$user?->id()){continue;}$isMention=in_array($recipient,array_map('intval',$mentions),true);$this->_notifications->create($recipient,$isMention?'mention':'comment',$isMention?'You were mentioned':'New task comment',$user?->record()['display_name'].' commented on “'.$task['title'].'”.','task',$id,'/projects/'.$task['project_id'].'?task='.$id,($isMention?'mention':'comment').'-'.$comment['id'].'-'.$recipient);}return Response::success('Comment added.',$comment,201);}catch(Throwable $exception){return Response::error($exception->getMessage(),422);}
    }

    public function addResearch(Request $request): Response
    {
        $id=(int)$request->parameter('id');$scope=$this->_scope($id);$user=$this->_authentication->currentUser();if($scope===null){return Response::error('Task not found.',404);}if(!$this->_authorization->can('contribute_task',$user,$scope)){return Response::error('Forbidden.',403);}$validator=(new Validator($request->all()))->required('title','Title')->required('type','Type')->oneOf('type',['link','note'],'Type');if(!$validator->valid()){return Response::error('Validation failed.',422,$validator->errors());}if($request->input('type')==='link'){ $url=(string)$request->input('url');$scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));if(!filter_var($url,FILTER_VALIDATE_URL)||!in_array($scheme,['http','https'],true)){return Response::error('Validation failed.',422,['url'=>['Research links must use a valid HTTP or HTTPS URL.']]);}}return Response::success('Research item added.',$this->_tasks->addResearch($id,$user?->id()??0,$request->all()),201);
    }

    public function editComment(Request $request): Response
    {
        $comment=$this->_tasks->findComment((int)$request->parameter('id'));$user=$this->_authentication->currentUser();if($comment===null||$user===null){return Response::error('Comment not found.',404);}$scope=$this->_scope((int)$comment['task_id']);$moderate=$scope!==null&&$this->_authorization->can('manage_project',$user,$scope);if($scope===null||!$this->_authorization->can('contribute_task',$user,$scope)||((int)$comment['author_id']!==$user->id()&&!$moderate)){return Response::error('Forbidden.',403);}return $this->_tasks->editComment((int)$comment['id'],$user->id(),(string)$request->input('body'),$moderate)?Response::success('Comment updated.'):Response::error('Comment was not changed.',409);
    }

    public function deleteComment(Request $request): Response
    {
        $comment=$this->_tasks->findComment((int)$request->parameter('id'));$user=$this->_authentication->currentUser();if($comment===null||$user===null){return Response::error('Comment not found.',404);}$scope=$this->_scope((int)$comment['task_id']);$moderate=$scope!==null&&$this->_authorization->can('manage_project',$user,$scope);if($scope===null||((int)$comment['author_id']!==$user->id()&&!$moderate)){return Response::error('Forbidden.',403);}return $this->_tasks->deleteComment((int)$comment['id'],$user->id(),$moderate)?Response::success('Comment deleted.'):Response::error('Comment not found.',404);
    }

    public function editResearch(Request $request): Response
    {
        $item=$this->_tasks->findResearch((int)$request->parameter('id'));$user=$this->_authentication->currentUser();if($item===null||$user===null){return Response::error('Research item not found.',404);}$scope=$this->_scope((int)$item['task_id']);$moderate=$scope!==null&&$this->_authorization->can('manage_project',$user,$scope);if($scope===null||!$this->_authorization->can('contribute_task',$user,$scope)||((int)$item['author_id']!==$user->id()&&!$moderate)){return Response::error('Forbidden.',403);}return $this->_tasks->editResearch((int)$item['id'],$user->id(),$request->all(),$moderate)?Response::success('Research item updated.'):Response::error('Research item was not changed.',409);
    }

    public function deleteResearch(Request $request): Response
    {
        $item=$this->_tasks->findResearch((int)$request->parameter('id'));$user=$this->_authentication->currentUser();if($item===null||$user===null){return Response::error('Research item not found.',404);}$scope=$this->_scope((int)$item['task_id']);$moderate=$scope!==null&&$this->_authorization->can('manage_project',$user,$scope);if($scope===null||((int)$item['author_id']!==$user->id()&&!$moderate)){return Response::error('Forbidden.',403);}return $this->_tasks->deleteResearch((int)$item['id'],$user->id(),$moderate)?Response::success('Research item deleted.'):Response::error('Research item not found.',404);
    }
}
