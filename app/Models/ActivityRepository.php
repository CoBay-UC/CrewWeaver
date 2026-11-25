<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class ActivityRepository
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
    protected function _collaborationEntry(array $entry): array
    {
        $before = json_decode($entry['before_values'] ?? 'null', true) ?? [];
        $after = json_decode($entry['after_values'] ?? 'null', true) ?? [];
        $title = $after['title'] ?? $before['title'] ?? $entry['current_title'];
        $type = ucfirst(str_replace('_', ' ', $entry['resource_type']));
        $action = ucfirst(str_replace(['.', '_'], ' ', $entry['action']));
        $detail = null;
        $isProject = $entry['resource_type'] === 'project';
        $projectTitle = $isProject ? $title : ($after['project_title'] ?? $before['project_title'] ?? $entry['project_title']);
        $subTeam = $after['sub_team_name'] ?? $before['sub_team_name'] ?? $entry['sub_team_name'];
        $projectContext = null;
        if ($projectTitle !== null && $projectTitle !== '') {
            $projectContext = '[' . $projectTitle . ']';
            if ($subTeam !== null && $subTeam !== '') {
                $projectContext = '[' . $subTeam . ']::' . $projectContext;
            }
        }

        if ($entry['resource_type'] === 'task' && isset($before['status'], $after['status'])
            && $before['status'] !== $after['status']) {
            $action = 'Task status changed';
            $detail = $before['status'] . ' → ' . $after['status'];
        } elseif ($entry['action'] === 'task.position_updated') {
            $action = 'Task reordered';
        } elseif (isset($before['title'], $after['title']) && $before['title'] !== $after['title']) {
            $action = $type . ' renamed';
            $detail = 'Previously ' . $before['title'];
        }

        $resourceName = $isProject ? $projectContext : $title;
        return [
            'created_at' => $entry['created_at'],
            'actor_name' => $entry['actor_name'],
            'action' => $action,
            'detail' => $detail,
            'resource_name' => $resourceName !== null && $resourceName !== '' ? $resourceName
                : $type . ($entry['resource_id'] === null ? '' : ' #' . $entry['resource_id']),
            'project_context' => $isProject ? null : $projectContext,
        ];
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function log(?int $actorId, string $action, string $resourceType, ?int $resourceId, array $scope, ?array $before, ?array $after, ?string $ip): void
    {
        $statement = $this->_pdo->prepare('INSERT INTO activity_logs (actor_id,action,resource_type,resource_id,team_id,sub_team_id,project_id,before_values,after_values,ip_address) VALUES (:actor,:action,:resource_type,:resource_id,:team_id,:sub_team_id,:project_id,:before_values,:after_values,:ip)');
        $statement->execute([
            'actor' => $actorId, 'action' => $action, 'resource_type' => $resourceType, 'resource_id' => $resourceId,
            'team_id' => $scope['team_id'] ?? null, 'sub_team_id' => $scope['sub_team_id'] ?? null, 'project_id' => $scope['project_id'] ?? null,
            'before_values' => $before === null ? null : json_encode($before, JSON_UNESCAPED_SLASHES),
            'after_values' => $after === null ? null : json_encode($after, JSON_UNESCAPED_SLASHES), 'ip' => $ip,
        ]);
    }

    public function recent(int $limit = 50, array $filters = []): array
    {
        $conditions = ['1=1'];
        $parameters = [];
        if(!empty($filters['collaboration_only'])){$conditions[]='a.resource_type IN ("project","task","milestone","comment","research","important_date")';}
        foreach (['team_id','sub_team_id','project_id','actor_id','resource_type'] as $field) {
            if (!empty($filters[$field])) { $conditions[] = 'a.' . $field . '=:' . $field; $parameters[$field] = $filters[$field]; }
        }
        $statement = $this->_pdo->prepare('SELECT a.*,u.display_name AS actor_name FROM activity_logs a LEFT JOIN users u ON u.id=a.actor_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY a.created_at DESC LIMIT :limit');
        foreach ($parameters as $key => $value) { $statement->bindValue(':' . $key, $value); }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function recentCollaboration(int $limit): array
    {
        $statement = $this->_pdo->prepare(<<<SQL
            SELECT a.*, u.display_name AS actor_name, p.title AS project_title, st.name AS sub_team_name,
                CASE a.resource_type
                    WHEN 'project' THEN p.title
                    WHEN 'milestone' THEN m.title
                    WHEN 'research' THEN r.title
                    WHEN 'important_date' THEN d.title
                    ELSE t.title
                END AS current_title
            FROM (
                SELECT id, actor_id, action, resource_type, resource_id, project_id, sub_team_id,
                    before_values, after_values, created_at
                FROM activity_logs
                WHERE resource_type IN ('project', 'task', 'milestone', 'comment', 'research', 'important_date')
                ORDER BY created_at DESC, id DESC
                LIMIT :limit
            ) a
            LEFT JOIN users u ON u.id=a.actor_id
            LEFT JOIN milestones m ON a.resource_type='milestone' AND m.id=a.resource_id
            LEFT JOIN comments c ON a.resource_type='comment' AND c.id=a.resource_id
            LEFT JOIN research_items r ON a.resource_type='research' AND r.id=a.resource_id
            LEFT JOIN important_dates d ON a.resource_type='important_date' AND d.id=a.resource_id
            LEFT JOIN tasks t ON t.id=CASE a.resource_type
                WHEN 'task' THEN a.resource_id
                WHEN 'milestone' THEN m.task_id
                WHEN 'comment' THEN c.task_id
                WHEN 'research' THEN r.task_id
            END
            LEFT JOIN projects p ON p.id=CASE a.resource_type
                WHEN 'project' THEN a.resource_id
                ELSE COALESCE(a.project_id, t.project_id, d.project_id)
            END
            LEFT JOIN sub_teams st ON st.id=COALESCE(a.sub_team_id, p.sub_team_id)
            ORDER BY a.created_at DESC, a.id DESC
            SQL);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return array_map($this->_collaborationEntry(...), $statement->fetchAll());
    }

    public function taskHistory(int $taskId): array
    {
        $statement = $this->_pdo->prepare(
            "SELECT a.action, a.created_at, u.display_name AS actor_name "
            . "FROM activity_logs a LEFT JOIN users u ON u.id=a.actor_id "
            . "WHERE a.resource_type='task' AND a.resource_id=:id ORDER BY a.created_at DESC, a.id DESC LIMIT 50"
        );
        $statement->execute(['id' => $taskId]);
        return $statement->fetchAll();
    }
}
