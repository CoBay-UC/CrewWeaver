<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;
use RuntimeException;

class MembershipRepository
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected PDO $_pdo;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(PDO $pdo)
    {
        $this->_pdo=$pdo;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _validateSubTeam(?int $subTeamId, int $teamId): void
    {
        if($subTeamId===null){return;}$statement=$this->_pdo->prepare('SELECT COUNT(*) FROM sub_teams WHERE id=:id AND team_id=:team_id');$statement->execute(['id'=>$subTeamId,'team_id'=>$teamId]);if((int)$statement->fetchColumn()!==1){throw new RuntimeException('Sub-team does not belong to the selected team.');}
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function save(int $userId, int $teamId, ?int $subTeamId): bool
    {
        $this->_validateSubTeam($subTeamId, $teamId);
        $statement = $this->_pdo->prepare(
            'INSERT INTO memberships (user_id,team_id,sub_team_id) VALUES (:user_id,:team_id,:sub_team_id) '
            . 'ON DUPLICATE KEY UPDATE id=id'
        );
        $statement->execute(['user_id' => $userId, 'team_id' => $teamId, 'sub_team_id' => $subTeamId]);
        return $statement->rowCount() === 1;
    }

    public function remove(int $userId,int $teamId,?int $subTeamId): bool
    {
        $statement=$this->_pdo->prepare('DELETE FROM memberships WHERE user_id=:user_id AND team_id=:team_id AND ((sub_team_id IS NULL AND :sub_team_null IS NULL) OR sub_team_id=:sub_team_id)');$statement->execute(['user_id'=>$userId,'team_id'=>$teamId,'sub_team_null'=>$subTeamId,'sub_team_id'=>$subTeamId]);return $statement->rowCount()===1;
    }

    public function grantRole(int $userId,string $role,?int $teamId,?int $subTeamId,int $actorId): array
    {
        if($role==='global_admin'&&($teamId!==null||$subTeamId!==null)){throw new RuntimeException('Global administrator roles cannot have a scope.');}if($role==='team_admin'&&($teamId===null||$subTeamId!==null)){throw new RuntimeException('Team administrator requires exactly one team.');}if($role==='sub_team_admin'&&($teamId===null||$subTeamId===null)){throw new RuntimeException('Sub-team administrator requires a team and sub-team.');}$this->_validateSubTeam($subTeamId,$teamId??0);
        $statement=$this->_pdo->prepare('INSERT IGNORE INTO scoped_roles (user_id,role,team_id,sub_team_id,granted_by) VALUES (:user_id,:role,:team_id,:sub_team_id,:actor)');$statement->execute(['user_id'=>$userId,'role'=>$role,'team_id'=>$teamId,'sub_team_id'=>$subTeamId,'actor'=>$actorId]);return $this->rolesForUser($userId);
    }

    public function revokeRole(int $roleId): bool
    {
        $statement=$this->_pdo->prepare('DELETE FROM scoped_roles WHERE id=:id');$statement->execute(['id'=>$roleId]);return $statement->rowCount()===1;
    }

    public function role(int $roleId): ?array
    {
        $statement=$this->_pdo->prepare('SELECT * FROM scoped_roles WHERE id=:id');$statement->execute(['id'=>$roleId]);$row=$statement->fetch();return $row===false?null:$row;
    }

    public function forUser(int $userId): array
    {
        $statement=$this->_pdo->prepare('SELECT m.*,t.name AS team_name,st.name AS sub_team_name FROM memberships m JOIN teams t ON t.id=m.team_id LEFT JOIN sub_teams st ON st.id=m.sub_team_id WHERE m.user_id=:id ORDER BY t.name,st.name');$statement->execute(['id'=>$userId]);return $statement->fetchAll();
    }

    public function rolesForUser(int $userId): array
    {
        $statement=$this->_pdo->prepare('SELECT r.*,t.name AS team_name,st.name AS sub_team_name FROM scoped_roles r LEFT JOIN teams t ON t.id=r.team_id LEFT JOIN sub_teams st ON st.id=r.sub_team_id WHERE r.user_id=:id ORDER BY r.role,t.name,st.name');$statement->execute(['id'=>$userId]);return $statement->fetchAll();
    }
}
