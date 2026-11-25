<?php

declare(strict_types=1);

namespace CrewWeaver\Controllers;

use CrewWeaver\Authentication\AuthenticationService;
use CrewWeaver\Authorization\AuthorizationService;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Models\ActivityRepository;
use CrewWeaver\Models\ReplacementRepository;
use CrewWeaver\Models\UserRepository;
use Throwable;

class AdminApiController
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected UserRepository $_users;
    protected ReplacementRepository $_replacement;
    protected ActivityRepository $_activity;
    protected AuthenticationService $_authentication;
    protected AuthorizationService $_authorization;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(UserRepository $users, ReplacementRepository $replacement, ActivityRepository $activity, AuthenticationService $authentication, AuthorizationService $authorization)
    {
        $this->_users=$users;$this->_replacement=$replacement;$this->_activity=$activity;$this->_authentication=$authentication;$this->_authorization=$authorization;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _global(): bool
    {
        return $this->_authorization->can('manage_users',$this->_authentication->currentUser());
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function users(Request $request): Response
    {
        if(!$this->_global()){return Response::error('Forbidden.',403);}return Response::success('Users retrieved.',$this->_users->paginate((string)$request->query('search'),(string)$request->query('status'),max(1,(int)$request->query('page',1)),min(100,max(1,(int)$request->query('per_page',50)))));
    }

    public function userStatus(Request $request): Response
    {
        if(!$this->_global()){return Response::error('Forbidden.',403);}$status=(string)$request->input('status');if(!in_array($status,['pending','active','inactive'],true)){return Response::error('Validation failed.',422,['status'=>['Status is invalid.']]);}$id=(int)$request->parameter('id');if($id===$this->_authentication->currentUser()?->id()&&$status!=='active'){return Response::error('You cannot deactivate your own active session.',409);}return $this->_users->updateStatus($id,$status)?Response::success('User status updated.',$this->_users->findById($id)):Response::error('User not found.',404);
    }

    public function replacementPreview(Request $request): Response
    {
        if(!$this->_global()){return Response::error('Forbidden.',403);}try{return Response::success('Replacement preview calculated.',$this->_replacement->preview((int)$request->input('source_user_id'),(int)$request->input('replacement_user_id'),(array)$request->input('project_ids',[]),(bool)$request->input('include_archived',false),(array)$request->input('options',[])));}catch(Throwable $exception){return Response::error($exception->getMessage(),422);}
    }

    public function replacementCommit(Request $request): Response
    {
        if(!$this->_global()){return Response::error('Forbidden.',403);}try{$result=$this->_replacement->commit((int)$request->input('source_user_id'),(int)$request->input('replacement_user_id'),(array)$request->input('project_ids',[]),(bool)$request->input('include_archived',false),(array)$request->input('options',[]),$this->_authentication->currentUser()?->id()??0);return Response::success('User replacement completed atomically.',$result);}catch(Throwable $exception){return Response::error($exception->getMessage(),422);}
    }

    public function audit(Request $request): Response
    {
        if(!$this->_authorization->can('view_audit',$this->_authentication->currentUser())){return Response::error('Forbidden.',403);}return Response::success('Audit records retrieved.',$this->_activity->recent(min(200,max(1,(int)$request->query('limit',50))),['actor_id'=>$request->query('actor_id'),'resource_type'=>$request->query('resource_type'),'team_id'=>$request->query('team_id'),'project_id'=>$request->query('project_id')]));
    }
}
