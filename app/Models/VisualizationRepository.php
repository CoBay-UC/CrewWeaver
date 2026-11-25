<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class VisualizationRepository
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
    protected function _scopeCondition(string $scopeType): string
    {
        return match ($scopeType) {
            'project' => 'p.id=:scope_id',
            'sub-team' => 'p.sub_team_id=:scope_id',
            'team' => 'p.team_id=:scope_id',
            default => '0=1',
        };
    }

    protected function _members(array &$tasks): void
    {
        $statement = $this->_pdo->prepare('SELECT u.id,u.display_name,u.avatar_url FROM task_members tm JOIN users u ON u.id=tm.user_id WHERE tm.task_id=:id ORDER BY u.display_name');
        foreach ($tasks as &$task) {
            $statement->execute(['id' => $task['id']]);
            $task['members'] = $statement->fetchAll();
        }
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function board(string $scopeType, int $scopeId, array $filters = []): array
    {
        $conditions = ['p.is_archived=0', $this->_scopeCondition($scopeType)];
        $parameters = ['scope_id' => $scopeId];
        foreach (['status' => 'x.status', 'priority' => 'x.priority', 'project_id' => 'p.id', 'owner_user_id' => 'x.owner_user_id'] as $filter => $column) {
            if (!empty($filters[$filter])) { $conditions[] = $column . '=:' . $filter; $parameters[$filter] = $filters[$filter]; }
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(x.title LIKE :task_title OR x.description LIKE :task_description OR p.title LIKE :project_title)';
            $search = '%' . $filters['search'] . '%';
            $parameters += ['task_title' => $search, 'task_description' => $search, 'project_title' => $search];
        }
        if (!empty($filters['member_id'])) { $conditions[] = 'EXISTS(SELECT 1 FROM task_members fm WHERE fm.task_id=x.id AND fm.user_id=:member_id)'; $parameters['member_id'] = $filters['member_id']; }
        if (!empty($filters['overdue'])) { $conditions[] = 'COALESCE(x.manual_due_date,x.calculated_finish)<UTC_DATE() AND x.status<>"Done"'; }
        $statement = $this->_pdo->prepare(
            'SELECT x.*,p.title AS project_title,p.colour AS project_colour,p.team_id,p.sub_team_id,st.name AS sub_team_name,u.display_name AS owner_name,' .
            '(SELECT COUNT(*) FROM comments c WHERE c.task_id=x.id) AS comment_count,(SELECT COUNT(*) FROM research_items r WHERE r.task_id=x.id) AS research_count,' .
            '(SELECT COUNT(*) FROM milestones m WHERE m.task_id=x.id) AS milestone_count,(SELECT COUNT(*) FROM milestones m WHERE m.task_id=x.id AND m.is_completed=1) AS completed_milestone_count,' .
            'EXISTS(SELECT 1 FROM task_dependencies d JOIN tasks predecessor ON predecessor.id=d.predecessor_task_id WHERE d.successor_task_id=x.id AND predecessor.status<>"Done") AS is_blocked ' .
            'FROM tasks x JOIN projects p ON p.id=x.project_id LEFT JOIN sub_teams st ON st.id=p.sub_team_id LEFT JOIN users u ON u.id=x.owner_user_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY COALESCE(st.display_order,-1),x.status,x.kanban_order,x.id'
        );
        $statement->execute($parameters);
        $tasks = $statement->fetchAll();
        $this->_members($tasks);
        return $tasks;
    }

    public function gantt(string $scopeType, int $scopeId): array
    {
        $condition = $this->_scopeCondition($scopeType);
        $projects = $this->_pdo->prepare('SELECT p.id,p.title,p.team_id,p.sub_team_id,p.planned_start,p.calculated_completion,p.target_completion,p.progress,p.colour,st.name AS sub_team_name FROM projects p LEFT JOIN sub_teams st ON st.id=p.sub_team_id WHERE p.is_archived=0 AND ' . $condition . ' ORDER BY st.display_order,p.title');
        $projects->execute(['scope_id' => $scopeId]);
        $rows = $projects->fetchAll();
        $taskStatement = $this->_pdo->prepare('SELECT id,title,planned_start,calculated_finish,actual_start,actual_completion,progress,status,priority FROM tasks WHERE project_id=:id ORDER BY planned_start,id');
        $milestoneStatement = $this->_pdo->prepare('SELECT id,task_id,title,calculated_date,is_completed FROM milestones WHERE task_id=:task_id ORDER BY display_order,id');
        $dependencyStatement = $this->_pdo->prepare('SELECT d.* FROM task_dependencies d JOIN tasks t ON t.id=d.successor_task_id WHERE t.project_id=:id');
        foreach ($rows as &$project) {
            $taskStatement->execute(['id' => $project['id']]);
            $project['tasks'] = $taskStatement->fetchAll();
            foreach ($project['tasks'] as &$task) { $milestoneStatement->execute(['task_id' => $task['id']]); $task['milestones'] = $milestoneStatement->fetchAll(); }
            $dependencyStatement->execute(['id' => $project['id']]);
            $project['dependencies'] = $dependencyStatement->fetchAll();
        }
        return $rows;
    }

    public function calendar(string $scopeType, int $scopeId, ?string $from = null, ?string $to = null): array
    {
        $condition = $this->_scopeCondition($scopeType);
        $range = $from !== null && $to !== null ? 'AND event_date >= :from_date AND event_date < :to_date' : '';
        $parameters = ['scope_id' => $scopeId];
        if ($range !== '') {
            $parameters += ['from_date' => $from, 'to_date' => $to];
        }
        $statement = $this->_pdo->prepare(<<<SQL
            WITH scoped_projects AS (
                SELECT p.id, p.title, p.colour, p.planned_start, p.target_completion, p.calculated_completion, st.name AS sub_team_name
                FROM projects p LEFT JOIN sub_teams st ON st.id=p.sub_team_id
                WHERE p.is_archived=0 AND {$condition}
            ), scoped_tasks AS (
                SELECT t.id, t.project_id, t.title, t.planned_start, t.calculated_finish, t.manual_due_date, p.colour
                FROM tasks t JOIN scoped_projects p ON p.id=t.project_id
            ), events AS (
                SELECT 'project-start' AS kind, id, title, planned_start AS event_date, colour, id AS project_id, NULL AS task_id FROM scoped_projects
                UNION ALL SELECT 'project-target', id, title, target_completion, '#b45309', id, NULL FROM scoped_projects
                UNION ALL SELECT 'project-finish', id, title, calculated_completion, '#15803d', id, NULL FROM scoped_projects
                UNION ALL SELECT 'task-start', id, title, planned_start, colour, project_id, id FROM scoped_tasks
                UNION ALL SELECT 'task-due', id, title, COALESCE(manual_due_date, calculated_finish), '#b91c1c', project_id, id FROM scoped_tasks
                UNION ALL SELECT 'milestone', m.id, m.title, m.calculated_date, '#7e22ce', t.project_id, t.id
                    FROM milestones m JOIN scoped_tasks t ON t.id=m.task_id
            )
            SELECT e.*, p.title AS project_title, p.sub_team_name
            FROM events e JOIN scoped_projects p ON p.id=e.project_id
            WHERE event_date IS NOT NULL {$range} ORDER BY event_date, kind, e.id
            SQL);
        $statement->execute($parameters);
        $labels = [
            'project-start' => 'Project start',
            'project-target' => 'Project target',
            'project-finish' => 'Project finish',
            'task-start' => 'Task start',
            'task-due' => 'Task due',
            'milestone' => 'Milestone',
        ];
        $events = [];
        foreach ($statement->fetchAll() as $row) {
            $event = [
                'id' => $row['kind'] . '-' . $row['id'],
                'title' => $labels[$row['kind']] . ': ' . $row['title'],
                'start' => $row['event_date'],
                'allDay' => true,
                'color' => $row['colour'],
                'extendedProps' => ['type' => $row['kind'], 'taskId' => $row['task_id']],
            ];
            if ($scopeType !== 'project') {
                $context = $row['project_title'];
                if ($scopeType === 'team' && $row['sub_team_name'] !== null) {
                    $context = $row['sub_team_name'] . '::' . $context;
                }
                $event['extendedProps']['listTitle'] = '[' . $context . '] '
                    . ($row['task_id'] === null ? $labels[$row['kind']] : $event['title']);
            }
            if ($row['task_id'] === null) {
                $event['url'] = '/projects/' . (int) $row['project_id'];
            }
            $events[] = $event;
        }
        return $events;
    }
}
