<?php

declare(strict_types=1);

namespace CrewWeaver\Controllers;

use CrewWeaver\Authentication\AuthenticationService;
use CrewWeaver\Authorization\AuthorizationService;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Models\ActivityRepository;
use CrewWeaver\Models\MembershipRepository;
use CrewWeaver\Models\UserRepository;
use PDOException;
use RuntimeException;
use Throwable;

class MembershipApiController
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected MembershipRepository $_memberships;
    protected AuthenticationService $_authentication;
    protected AuthorizationService $_authorization;
    protected UserRepository $_users;
    protected ActivityRepository $_activity;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(MembershipRepository $memberships, AuthenticationService $authentication, AuthorizationService $authorization, UserRepository $users, ActivityRepository $activity)
    {
        $this->_memberships=$memberships;$this->_authentication=$authentication;$this->_authorization=$authorization;
        $this->_users = $users;
        $this->_activity = $activity;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _scope(Request $request): array
    {
        return ['team_id'=>(int)$request->input('team_id'),'sub_team_id'=>$request->input('sub_team_id')?(int)$request->input('sub_team_id'):null];
    }

    protected function _visibleAssignments(int $userId): array
    {
        $actor = $this->_authentication->currentUser();
        return array_values(array_filter($this->_memberships->forUser($userId),
            fn (array $record): bool => $this->_authorization->can('manage_scope', $actor, $record)));
    }

    protected function _userAvailable(int $userId): bool
    {
        $record = $this->_users->findById($userId);
        return $record !== null && ($record['status'] === 'active'
            || $this->_authorization->can('manage_users', $this->_authentication->currentUser()));
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function index(Request $request): Response
    {
        $actor = $this->_authentication->currentUser();
        if (!$this->_authorization->can('manage_scopes', $actor)) {
            return Response::error('Forbidden.', 403);
        }
        $userId = (int) $request->parameter('id');
        return $this->_userAvailable($userId)
            ? Response::success('Memberships retrieved.', $this->_visibleAssignments($userId))
            : Response::error('User not found.', 404);
    }

    public function create(Request $request): Response
    {
        $scope = $this->_scope($request);
        $actor = $this->_authentication->currentUser();
        if (!$this->_authorization->can('manage_scope', $actor, $scope)) {
            return Response::error('Forbidden.', 403);
        }
        $userId = (int) $request->input('user_id');
        if (!$this->_userAvailable($userId)) {
            return Response::error('User not found.', 404);
        }
        try {
            $created = $this->_memberships->save($userId, $scope['team_id'], $scope['sub_team_id']);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1452) {
                return Response::error('The selected user or scope no longer exists.', 422);
            }
            throw $exception;
        } catch (RuntimeException $exception) {
            return Response::error($exception->getMessage(), 422);
        }
        if ($created) {
            $this->_activity->log($actor?->id(), 'membership.created', 'membership', $userId, $scope,
                null, $scope + ['user_id' => $userId], $request->ip());
        }
        return Response::success('Membership saved.', $this->_visibleAssignments($userId), $created ? 201 : 200);
    }

    public function delete(Request $request): Response
    {
        $scope = $this->_scope($request);
        $actor = $this->_authentication->currentUser();
        if (!$this->_authorization->can('manage_scope', $actor, $scope)) {
            return Response::error('Forbidden.', 403);
        }
        $userId = (int) $request->input('user_id');
        if (!$this->_memberships->remove($userId, $scope['team_id'], $scope['sub_team_id'])) {
            return Response::error('Membership not found.', 404);
        }
        $this->_activity->log($actor?->id(), 'membership.deleted', 'membership', $userId, $scope,
            $scope + ['user_id' => $userId], null, $request->ip());
        return Response::success('Membership removed.', $this->_visibleAssignments($userId));
    }

    public function grantRole(Request $request): Response
    {
        $role=(string)$request->input('role');$scope=$this->_scope($request);$user=$this->_authentication->currentUser();if($role==='global_admin'){if(!$this->_authorization->can('manage_global',$user)){return Response::error('Forbidden.',403);}}elseif(!$this->_authorization->can('manage_scope',$user,$scope)){return Response::error('Forbidden.',403);}try{return Response::success('Scoped role granted.',$this->_memberships->grantRole((int)$request->input('user_id'),$role,$scope['team_id']?:null,$scope['sub_team_id'],$user?->id()??0),201);}catch(Throwable $exception){return Response::error($exception->getMessage(),422);}
    }

    public function revokeRole(Request $request): Response
    {
        $role=$this->_memberships->role((int)$request->parameter('id'));if($role===null){return Response::error('Role not found.',404);}$scope=['team_id'=>(int)$role['team_id'],'sub_team_id'=>$role['sub_team_id']?(int)$role['sub_team_id']:null];$user=$this->_authentication->currentUser();if($role['role']==='global_admin'?!$this->_authorization->can('manage_global',$user):!$this->_authorization->can('manage_scope',$user,$scope)){return Response::error('Forbidden.',403);}return $this->_memberships->revokeRole((int)$role['id'])?Response::success('Role revoked.'):Response::error('Role not found.',404);
    }
}
