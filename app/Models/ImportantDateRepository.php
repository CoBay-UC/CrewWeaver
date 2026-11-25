<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class ImportantDateRepository
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
    public function list(array $filters = []): array
    {
        $conditions = ['(d.project_id IS NULL OR p.is_archived=0)'];
        if (empty($filters['include_inactive'])) {
            $conditions[] = 'd.is_active=1';
        }
        $parameters = [];
        if (!empty($filters['search'])) {
            $conditions[] = '(d.title LIKE :search_title OR d.description LIKE :search_description)';
            $search = '%' . $filters['search'] . '%';
            $parameters += ['search_title' => $search, 'search_description' => $search];
        }
        if (!empty($filters['id'])) {
            $conditions[] = 'd.id=:id';
            $parameters['id'] = $filters['id'];
        }
        if (isset($filters['exact_scope'])) {
            $conditions[] = 'd.scope_type=:exact_scope';
            $parameters['exact_scope'] = $filters['exact_scope'];
            $column = match ($filters['exact_scope']) {
                'team' => 'team_id',
                'sub_team' => 'sub_team_id',
                'project' => 'project_id',
                default => null,
            };
            if ($column !== null) {
                $conditions[] = "d.{$column}=:exact_id";
                $parameters['exact_id'] = $filters['exact_id'];
            }
        }
        if (!empty($filters['from'])) { $conditions[] = 'd.event_date>=:from_date'; $parameters['from_date'] = $filters['from']; }
        if (!empty($filters['to'])) { $conditions[] = 'd.event_date<=:to_date'; $parameters['to_date'] = $filters['to']; }
        if (isset($filters['overlap_from'], $filters['overlap_until'])) {
            $conditions[] = 'COALESCE(d.end_date,d.event_date)>=:overlap_from AND d.event_date<:overlap_until';
            $parameters += ['overlap_from' => $filters['overlap_from'], 'overlap_until' => $filters['overlap_until']];
        }
        if (!empty($filters['team_id'])) { $conditions[] = '(d.scope_type="global" OR d.team_id=:team_id OR st.team_id=:team_id_inherited OR p.team_id=:team_id_project)'; $parameters += ['team_id' => $filters['team_id'], 'team_id_inherited' => $filters['team_id'], 'team_id_project' => $filters['team_id']]; }
        if (!empty($filters['sub_team_id'])) { $conditions[] = '(d.scope_type="global" OR d.team_id=(SELECT team_id FROM sub_teams WHERE id=:sub_team_parent) OR d.sub_team_id=:sub_team_id OR p.sub_team_id=:sub_team_project)'; $parameters += ['sub_team_parent' => $filters['sub_team_id'], 'sub_team_id' => $filters['sub_team_id'], 'sub_team_project' => $filters['sub_team_id']]; }
        if (!empty($filters['project_id'])) { $conditions[] = '(d.scope_type="global" OR d.team_id=(SELECT team_id FROM projects WHERE id=:project_team) OR d.sub_team_id=(SELECT sub_team_id FROM projects WHERE id=:project_sub_team) OR d.project_id=:project_id)'; $parameters += ['project_team' => $filters['project_id'], 'project_sub_team' => $filters['project_id'], 'project_id' => $filters['project_id']]; }
        if (isset($filters['calendar_scope'], $filters['calendar_id'])) {
            $calendar = match ($filters['calendar_scope']) {
                'sub-team' => " OR (d.scope_type='sub_team' AND d.sub_team_id=:calendar_id)"
                    . " OR (d.scope_type='project' AND p.sub_team_id=:calendar_child)",
                'project' => " OR (d.scope_type='project' AND d.project_id=:calendar_id)",
                'team' => " OR (d.scope_type='project' AND p.team_id=:calendar_id)",
                default => '',
            };
            $conditions[] = "(d.scope_type IN ('global','team'){$calendar})";
            if ($calendar !== '') {
                $parameters['calendar_id'] = $filters['calendar_id'];
            }
            if ($filters['calendar_scope'] === 'sub-team') {
                $parameters['calendar_child'] = $filters['calendar_id'];
            }
        }
        $direction = ($filters['sort'] ?? '') === 'latest' ? 'DESC' : 'ASC';
        $statement = $this->_pdo->prepare(
            "SELECT d.*, COALESCE(p.team_id,st.team_id,d.team_id) AS scope_team_id, "
            . "COALESCE(p.sub_team_id,d.sub_team_id) AS scope_sub_team_id, "
            . "CASE d.scope_type WHEN 'team' THEN tm.name WHEN 'sub_team' THEN st.name "
            . "WHEN 'project' THEN p.title ELSE 'Organization' END AS scope_name "
            . 'FROM important_dates d LEFT JOIN teams tm ON tm.id=d.team_id '
            . 'LEFT JOIN sub_teams st ON st.id=d.sub_team_id LEFT JOIN projects p ON p.id=d.project_id '
            . 'WHERE ' . implode(' AND ', $conditions) . " ORDER BY d.event_date {$direction},d.event_time,d.priority DESC,d.id"
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->_pdo->prepare('SELECT * FROM important_dates WHERE id=:id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function save(array $data, int $actorId, ?int $id = null): array
    {
        $parameters = [
            'title' => trim($data['title']), 'description' => $data['description'] ?? null, 'event_date' => $data['event_date'],
            'event_time' => ($data['event_time'] ?? null) ?: null, 'end_date' => ($data['end_date'] ?? null) ?: null, 'is_all_day' => (int) ($data['is_all_day'] ?? 1),
            'colour' => $data['colour'] ?? '#f59e0b', 'icon' => $data['icon'] ?? 'calendar-event', 'priority' => $data['priority'] ?? 'normal',
            'scope_type' => $data['scope_type'], 'team_id' => ($data['team_id'] ?? null) ?: null, 'sub_team_id' => ($data['sub_team_id'] ?? null) ?: null,
            'project_id' => ($data['project_id'] ?? null) ?: null, 'show_countdown' => (int) ($data['show_countdown'] ?? 1), 'is_active' => (int) ($data['is_active'] ?? 1),
        ];
        if ($id === null) {
            $statement = $this->_pdo->prepare('INSERT INTO important_dates (title,description,event_date,event_time,end_date,is_all_day,colour,icon,priority,scope_type,team_id,sub_team_id,project_id,show_countdown,is_active,created_by) VALUES (:title,:description,:event_date,:event_time,:end_date,:is_all_day,:colour,:icon,:priority,:scope_type,:team_id,:sub_team_id,:project_id,:show_countdown,:is_active,:created_by)');
            $statement->execute($parameters + ['created_by' => $actorId]);
            $id = (int) $this->_pdo->lastInsertId();
        } else {
            $statement = $this->_pdo->prepare('UPDATE important_dates SET title=:title,description=:description,event_date=:event_date,event_time=:event_time,end_date=:end_date,is_all_day=:is_all_day,colour=:colour,icon=:icon,priority=:priority,scope_type=:scope_type,team_id=:team_id,sub_team_id=:sub_team_id,project_id=:project_id,show_countdown=:show_countdown,is_active=:is_active WHERE id=:id');
            $statement->execute($parameters + ['id' => $id]);
        }
        return $this->find($id) ?? [];
    }

    public function delete(int $id): void
    {
        $this->_pdo->prepare('DELETE FROM important_dates WHERE id=:id')->execute(['id' => $id]);
    }
}
