<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class PermissionRepository
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
        $this->_pdo = $pdo;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function taskScope(int $taskId): ?array
    {
        $statement = $this->_pdo->prepare(
            'SELECT t.id AS task_id, t.owner_user_id, p.id AS project_id, p.team_id, p.sub_team_id, p.is_archived, ' .
            'EXISTS(SELECT 1 FROM task_members tm WHERE tm.task_id = t.id AND tm.user_id = :user_id) AS is_task_member, ' .
            'EXISTS(SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = :user_id) AS is_project_member ' .
            'FROM tasks t JOIN projects p ON p.id = t.project_id WHERE t.id = :task_id'
        );
        $statement->execute(['user_id' => 0, 'task_id' => $taskId]);
        $scope = $statement->fetch();
        return $scope === false ? null : $scope;
    }

    public function taskScopeForUser(int $taskId, int $userId): ?array
    {
        $statement = $this->_pdo->prepare(
            'SELECT t.id AS task_id, t.owner_user_id, p.id AS project_id, p.team_id, p.sub_team_id, p.is_archived, ' .
            'EXISTS(SELECT 1 FROM task_members tm WHERE tm.task_id = t.id AND tm.user_id = :member_user) AS is_task_member, ' .
            'EXISTS(SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = :project_user) AS is_project_member ' .
            'FROM tasks t JOIN projects p ON p.id = t.project_id WHERE t.id = :task_id'
        );
        $statement->execute(['member_user' => $userId, 'project_user' => $userId, 'task_id' => $taskId]);
        $scope = $statement->fetch();
        return $scope === false ? null : $scope;
    }

    public function projectScope(int $projectId): ?array
    {
        $statement = $this->_pdo->prepare('SELECT id AS project_id, team_id, sub_team_id, is_archived FROM projects WHERE id = :id');
        $statement->execute(['id' => $projectId]);
        $scope = $statement->fetch();
        return $scope === false ? null : $scope;
    }

    public function subTeamScope(int $subTeamId): ?array
    {
        $statement = $this->_pdo->prepare('SELECT id AS sub_team_id, team_id FROM sub_teams WHERE id = :id');
        $statement->execute(['id' => $subTeamId]);
        $scope = $statement->fetch();
        return $scope === false ? null : $scope;
    }
}
