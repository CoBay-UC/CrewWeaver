<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;
use RuntimeException;

class ProjectRepository
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
    protected function _syncMembers(int $projectId, array $memberIds, int $actorId): void
    {
        $memberIds=array_values(array_unique(array_filter(array_map('intval',$memberIds))));$eligible=$this->_pdo->prepare('SELECT COUNT(*) FROM users WHERE id=:id AND status="active"');foreach($memberIds as $userId){$eligible->execute(['id'=>$userId]);if((int)$eligible->fetchColumn()!==1){throw new RuntimeException('Every project member must be an active approved member.');}}
        $this->_pdo->prepare('DELETE FROM project_members WHERE project_id=:id')->execute(['id' => $projectId]);
        $statement = $this->_pdo->prepare('INSERT IGNORE INTO project_members (project_id,user_id,assigned_by) VALUES (:project_id,:user_id,:actor_id)');
        foreach ($memberIds as $userId) {
            if ($userId > 0) {
                $statement->execute(['project_id' => $projectId, 'user_id' => $userId, 'actor_id' => $actorId]);
            }
        }
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function listActive(array $filters = []): array
    {
        $conditions = ['p.is_archived=0'];
        $parameters = [];
        foreach (['team_id', 'sub_team_id', 'status'] as $field) {
            if (!empty($filters[$field])) {
                $conditions[] = 'p.' . $field . '=:' . $field;
                $parameters[$field] = $filters[$field];
            }
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(p.title LIKE :search_title OR p.description LIKE :search_description)';
            $search = '%' . $filters['search'] . '%';
            $parameters += ['search_title' => $search, 'search_description' => $search];
        }
        $statement = $this->_pdo->prepare(
            'SELECT p.*,t.name AS team_name,st.name AS sub_team_name,u.display_name AS lead_name, ' .
            '(SELECT COUNT(*) FROM tasks x WHERE x.project_id=p.id) AS task_count, ' .
            '(SELECT COUNT(*) FROM tasks x WHERE x.project_id=p.id AND x.status="Done") AS done_count ' .
            'FROM projects p JOIN teams t ON t.id=p.team_id LEFT JOIN sub_teams st ON st.id=p.sub_team_id ' .
            'LEFT JOIN users u ON u.id=p.lead_user_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY p.priority DESC,p.title'
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->_pdo->prepare(
            'SELECT p.*,t.name AS team_name,st.name AS sub_team_name,u.display_name AS lead_name FROM projects p ' .
            'JOIN teams t ON t.id=p.team_id LEFT JOIN sub_teams st ON st.id=p.sub_team_id LEFT JOIN users u ON u.id=p.lead_user_id WHERE p.id=:id'
        );
        $statement->execute(['id' => $id]);
        $project = $statement->fetch();
        if ($project === false) {
            return null;
        }
        $members = $this->_pdo->prepare('SELECT u.id,u.display_name,u.avatar_url,u.email FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=:id ORDER BY u.display_name');
        $members->execute(['id' => $id]);
        $project['members'] = $members->fetchAll();
        return $project;
    }

    public function create(array $data, int $actorId): array
    {
        $this->_pdo->beginTransaction();
        try {
            $statement = $this->_pdo->prepare(
                'INSERT INTO projects (team_id,sub_team_id,title,description,lead_user_id,priority,colour,status,scheduling_mode,planned_start,target_completion,created_by) ' .
                'VALUES (:team_id,:sub_team_id,:title,:description,:lead_user_id,:priority,:colour,:status,:scheduling_mode,:planned_start,:target_completion,:created_by)'
            );
            $statement->execute([
                'team_id' => (int) $data['team_id'], 'sub_team_id' => ($data['sub_team_id'] ?? null) ?: null,
                'title' => trim($data['title']), 'description' => $data['description'] ?? null,
                'lead_user_id' => ($data['lead_user_id'] ?? null) ?: null, 'priority' => $data['priority'] ?? 'normal',
                'colour' => $data['colour'] ?? '#4f46e5', 'status' => $data['status'] ?? 'Planned',
                'scheduling_mode' => $data['scheduling_mode'] ?? 'calendar', 'planned_start' => ($data['planned_start'] ?? null) ?: null,
                'target_completion' => ($data['target_completion'] ?? null) ?: null, 'created_by' => $actorId,
            ]);
            $id = (int) $this->_pdo->lastInsertId();
            $this->_syncMembers($id, $data['member_ids'] ?? [], $actorId);
            $this->_pdo->commit();
            return $this->find($id) ?? [];
        } catch (\Throwable $exception) {
            if ($this->_pdo->inTransaction()) {
                $this->_pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function update(int $id, array $data, int $actorId): ?array
    {
        $current = $this->find($id);
        if ($current === null) {
            return null;
        }
        $version = (int) ($data['version'] ?? $current['version']);
        $this->_pdo->beginTransaction();
        try {
        $statement = $this->_pdo->prepare(
            'UPDATE projects SET team_id=:team_id,sub_team_id=:sub_team_id,title=:title,description=:description,lead_user_id=:lead_user_id,' .
            'priority=:priority,colour=:colour,status=:status,scheduling_mode=:scheduling_mode,planned_start=:planned_start,target_completion=:target_completion,version=version+1 ' .
            'WHERE id=:id AND version=:version AND is_archived=0'
        );
        $statement->execute([
            'team_id' => (int) ($data['team_id'] ?? $current['team_id']), 'sub_team_id' => array_key_exists('sub_team_id', $data) ? $data['sub_team_id'] : $current['sub_team_id'],
            'title' => trim($data['title'] ?? $current['title']), 'description' => $data['description'] ?? $current['description'],
            'lead_user_id' => $data['lead_user_id'] ?? $current['lead_user_id'], 'priority' => $data['priority'] ?? $current['priority'],
            'colour' => $data['colour'] ?? $current['colour'], 'status' => $data['status'] ?? $current['status'],
            'scheduling_mode' => $data['scheduling_mode'] ?? $current['scheduling_mode'], 'planned_start' => $data['planned_start'] ?? $current['planned_start'],
            'target_completion' => $data['target_completion'] ?? $current['target_completion'], 'id' => $id, 'version' => $version,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The project changed while you were editing.');
        }
        if (array_key_exists('member_ids', $data)) {
            $this->_syncMembers($id, (array) $data['member_ids'], $actorId);
        }
        $this->_pdo->commit();
        return $this->find($id);
        } catch (\Throwable $exception) {
            if ($this->_pdo->inTransaction()) { $this->_pdo->rollBack(); }
            throw $exception;
        }
    }

    public function archive(int $id, int $actorId): bool
    {
        $statement = $this->_pdo->prepare('UPDATE projects SET is_archived=1,archived_at=UTC_TIMESTAMP(),archived_by=:actor,version=version+1 WHERE id=:id AND is_archived=0');
        $statement->execute(['actor' => $actorId, 'id' => $id]);
        return $statement->rowCount() === 1;
    }

    public function restore(int $id): bool
    {
        $statement = $this->_pdo->prepare('UPDATE projects SET is_archived=0,archived_at=NULL,archived_by=NULL,version=version+1 WHERE id=:id AND is_archived=1');
        $statement->execute(['id' => $id]);
        return $statement->rowCount() === 1;
    }

    public function archiveList(array $filters = []): array
    {
        $conditions = ['p.is_archived=1'];
        $parameters = [];
        if (!empty($filters['search'])) {
            $conditions[] = 'p.title LIKE :search';
            $parameters['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['team_id'])) {
            $conditions[] = 'p.team_id=:team_id';
            $parameters['team_id'] = $filters['team_id'];
        }
        if (!empty($filters['sub_team_id'])) {
            $conditions[] = 'p.sub_team_id=:sub_team_id';
            $parameters['sub_team_id'] = $filters['sub_team_id'];
        }
        $statement = $this->_pdo->prepare(
            'SELECT p.*,t.name AS team_name,st.name AS sub_team_name,u.display_name AS archived_by_name FROM projects p JOIN teams t ON t.id=p.team_id ' .
            'LEFT JOIN sub_teams st ON st.id=p.sub_team_id LEFT JOIN users u ON u.id=p.archived_by WHERE ' . implode(' AND ', $conditions) . ' ORDER BY p.archived_at DESC'
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function archivedCount(?int $teamId = null, ?int $subTeamId = null): int
    {
        $conditions = ['is_archived=1'];
        $parameters = [];
        if ($teamId !== null) { $conditions[] = 'team_id=:team_id'; $parameters['team_id'] = $teamId; }
        if ($subTeamId !== null) { $conditions[] = 'sub_team_id=:sub_team_id'; $parameters['sub_team_id'] = $subTeamId; }
        $statement = $this->_pdo->prepare('SELECT COUNT(*) FROM projects WHERE ' . implode(' AND ', $conditions));
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }
}
