<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;
use RuntimeException;
use Throwable;

class ScopeDeletionRepository
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected PDO $_pdo;
    protected ActivityRepository $_activity;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */
    public const CONFIRMATION = 'I understand this permanently deletes the data and cannot be undone.';

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(PDO $pdo, ActivityRepository $activity)
    {
        $this->_pdo = $pdo;
        $this->_activity = $activity;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _execute(string $sql, int $id): \PDOStatement
    {
        $statement = $this->_pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement;
    }

    protected function _queueAttachments(string $projectScope, int $id): array
    {
        $files = $this->_execute(
            'SELECT DISTINCT a.storage_name FROM attachments a '
            . 'LEFT JOIN comments c ON c.id=a.comment_id '
            . 'LEFT JOIN research_items r ON r.id=a.research_item_id '
            . 'JOIN tasks t ON t.id IN (a.task_id,c.task_id,r.task_id) '
            . "JOIN projects p ON p.id=t.project_id WHERE p.{$projectScope}=:id",
            $id
        )->fetchAll(PDO::FETCH_COLUMN);
        $queue = $this->_pdo->prepare(
            'INSERT INTO pending_attachment_deletions (storage_name) VALUES (:storage_name) '
            . 'ON DUPLICATE KEY UPDATE storage_name=VALUES(storage_name)'
        );
        foreach ($files as $file) {
            $queue->execute(['storage_name' => $file]);
        }
        return $files;
    }

    protected function _deleteProjects(string $column, int $id, int $projectCount): array
    {
        $taskScope = "JOIN projects p ON p.id=t.project_id WHERE p.{$column}=:id";
        $this->_execute("SELECT t.id FROM tasks t {$taskScope} FOR UPDATE", $id)->fetchAll();
        foreach (['comments', 'research_items'] as $table) {
            $this->_execute("SELECT c.id FROM {$table} c JOIN tasks t ON t.id=c.task_id {$taskScope} FOR UPDATE", $id)->fetchAll();
        }
        $files = $this->_queueAttachments($column, $id);
        $this->_execute("DELETE n FROM notifications n JOIN tasks t ON t.id=n.resource_id AND n.resource_type='task' {$taskScope}", $id);
        $this->_execute("DELETE n FROM notifications n JOIN projects p ON p.id=n.resource_id AND n.resource_type='project' WHERE p.{$column}=:id", $id);
        $taskCount = $this->_execute("DELETE t FROM tasks t {$taskScope}", $id)->rowCount();
        $this->_execute("DELETE s FROM scheduling_configurations s JOIN projects p ON p.id=s.scope_id AND s.scope_type='project' WHERE p.{$column}=:id", $id);
        $this->_execute("DELETE FROM projects WHERE {$column}=:id", $id);
        return ['deleted' => ['projects' => $projectCount, 'tasks' => $taskCount, 'attachments' => count($files)], 'files' => $files];
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function delete(int $id, bool $subTeam, int $actorId, string $ip): array
    {
        $table = $subTeam ? 'sub_teams' : 'teams';
        $type = $subTeam ? 'sub_team' : 'team';
        $projectScope = $subTeam ? 'sub_team_id' : 'team_id';
        $this->_pdo->beginTransaction();
        try {
            $scope = $this->_execute("SELECT id,name,slug FROM {$table} WHERE id=:id FOR UPDATE", $id)->fetch();
            if ($scope === false) {
                throw new RuntimeException('The scope has already been deleted.');
            }
            if (!$subTeam) {
                $this->_execute('SELECT id FROM sub_teams WHERE team_id=:id FOR UPDATE', $id)->fetchAll();
            }
            $projects = $this->_execute("SELECT id FROM projects WHERE {$projectScope}=:id FOR UPDATE", $id)->fetchAll();
            $mismatch = $this->_execute(
                "SELECT p.id FROM projects p JOIN sub_teams st ON st.id=p.sub_team_id WHERE p.{$projectScope}=:id AND p.team_id<>st.team_id LIMIT 1 FOR UPDATE",
                $id
            )->fetchColumn();
            if ($mismatch !== false) {
                throw new RuntimeException('A project has mismatched team and sub-team assignments. No data was deleted.');
            }
            $result = $this->_deleteProjects($projectScope, $id, count($projects));
            $this->_execute("DELETE FROM memberships WHERE {$projectScope}=:id", $id);
            $this->_execute("DELETE FROM scoped_roles WHERE {$projectScope}=:id", $id);
            $this->_execute("DELETE FROM scheduling_configurations WHERE scope_type='{$type}' AND scope_id=:id", $id);
            if (!$subTeam) {
                $this->_execute(
                    "DELETE s FROM scheduling_configurations s JOIN sub_teams st ON st.id=s.scope_id AND s.scope_type='sub_team' WHERE st.team_id=:id", $id
                );
                $this->_execute('DELETE FROM sub_teams WHERE team_id=:id', $id);
            }
            $this->_execute("DELETE FROM {$table} WHERE id=:id", $id);
            $this->_activity->log($actorId, $type . '.deleted', $type, $id, [], $scope + ['deleted' => $result['deleted']], null, $ip);
            $this->_pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->_pdo->inTransaction()) {
                $this->_pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteProject(int $id, array $scope, int $actorId, string $ip): array
    {
        $this->_pdo->beginTransaction();
        try {
            $project = $this->_execute('SELECT id,title,team_id,sub_team_id FROM projects WHERE id=:id FOR UPDATE', $id)->fetch();
            if (!$project || (int) $project['team_id'] !== (int) $scope['team_id']
                || (int) $project['sub_team_id'] !== (int) $scope['sub_team_id']) {
                throw new RuntimeException('The project has moved or was deleted. The view will update automatically.');
            }
            $result = $this->_deleteProjects('id', $id, 1);
            unset($scope['project_id']);
            $this->_activity->log($actorId, 'project.deleted', 'project', $id, $scope, $project + ['deleted' => $result['deleted']], null, $ip);
            $this->_pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->_pdo->inTransaction()) {
                $this->_pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function queuedFiles(): array
    {
        return $this->_pdo->query('SELECT storage_name FROM pending_attachment_deletions ORDER BY queued_at')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function markFileDeleted(string $storageName): void
    {
        $statement = $this->_pdo->prepare('DELETE FROM pending_attachment_deletions WHERE storage_name=:storage_name');
        $statement->execute(['storage_name' => $storageName]);
    }
}
