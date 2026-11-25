<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;
use RuntimeException;

class ProjectCopyRepository
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
    protected function _copyMembers(int $sourceProjectId, int $targetProjectId, int $actorId): void
    {
        $statement = $this->_pdo->prepare('INSERT INTO project_members (project_id,user_id,assigned_by) SELECT :target,user_id,:actor FROM project_members WHERE project_id=:source');
        $statement->execute(['target' => $targetProjectId, 'actor' => $actorId, 'source' => $sourceProjectId]);
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function copy(int $sourceProjectId, array $options, int $actorId): int
    {
        $sourceStatement = $this->_pdo->prepare('SELECT * FROM projects WHERE id=:id');
        $sourceStatement->execute(['id' => $sourceProjectId]);
        $source = $sourceStatement->fetch();
        if ($source === false) { throw new RuntimeException('Source project not found.'); }
        $this->_pdo->beginTransaction();
        try {
            $project = $this->_pdo->prepare(
                'INSERT INTO projects (team_id,sub_team_id,title,description,lead_user_id,priority,colour,status,scheduling_mode,planned_start,target_completion,copied_from_id,created_by) ' .
                'VALUES (:team_id,:sub_team_id,:title,:description,:lead,:priority,:colour,:status,:mode,:start,:target,:source,:actor)'
            );
            $removeAssignments = (bool) ($options['remove_assignments'] ?? false);
            $project->execute([
                'team_id' => (int) $options['team_id'], 'sub_team_id' => ($options['sub_team_id'] ?? null) ?: null, 'title' => trim($options['title']),
                'description' => ($options['copy_description'] ?? true) ? $source['description'] : null,
                'lead' => (!$removeAssignments && ($options['copy_ownership'] ?? false)) ? $source['lead_user_id'] : null,
                'priority' => $source['priority'], 'colour' => $source['colour'], 'status' => 'Planned', 'mode' => $source['scheduling_mode'],
                'start' => ($options['planned_start'] ?? null) ?: null, 'target' => null, 'source' => $sourceProjectId, 'actor' => $actorId,
            ]);
            $targetProjectId = (int) $this->_pdo->lastInsertId();
            if (!$removeAssignments && ($options['copy_members'] ?? false)) { $this->_copyMembers($sourceProjectId, $targetProjectId, $actorId); }
            $taskMap = [];
            if ($options['copy_tasks'] ?? true) {
                $tasks = $this->_pdo->prepare('SELECT * FROM tasks WHERE project_id=:id ORDER BY id');
                $tasks->execute(['id' => $sourceProjectId]);
                $insertTask = $this->_pdo->prepare('INSERT INTO tasks (project_id,title,description,status,priority,owner_user_id,scheduling_mode,dates_locked,estimated_duration_days,progress,kanban_order,created_by) VALUES (:project_id,:title,:description,"Not Started",:priority,:owner,"automatic",0,:duration,0,:ordering,:actor)');
                foreach ($tasks->fetchAll() as $task) {
                    $insertTask->execute(['project_id' => $targetProjectId, 'title' => $task['title'], 'description' => $task['description'], 'priority' => $task['priority'], 'owner' => (!$removeAssignments && ($options['copy_ownership'] ?? false)) ? $task['owner_user_id'] : null, 'duration' => $task['estimated_duration_days'], 'ordering' => $task['kanban_order'], 'actor' => $actorId]);
                    $newTaskId = (int) $this->_pdo->lastInsertId();
                    $taskMap[(int) $task['id']] = $newTaskId;
                    if (!$removeAssignments && ($options['copy_members'] ?? false)) {
                        $members = $this->_pdo->prepare('INSERT INTO task_members (task_id,user_id,assigned_by) SELECT :target,user_id,:actor FROM task_members WHERE task_id=:source');
                        $members->execute(['target' => $newTaskId, 'actor' => $actorId, 'source' => $task['id']]);
                    }
                    if ($options['copy_milestones'] ?? true) {
                        $milestones = $this->_pdo->prepare('INSERT INTO milestones (task_id,title,description,duration_days,cumulative_offset_days,is_completed,actual_completion,notes,display_order) SELECT :target,title,description,duration_days,cumulative_offset_days,0,NULL,notes,display_order FROM milestones WHERE task_id=:source');
                        $milestones->execute(['target' => $newTaskId, 'source' => $task['id']]);
                    }
                    if ($options['copy_research'] ?? false) {
                        $research = $this->_pdo->prepare('INSERT INTO research_items (task_id,author_id,type,title,url,content,description,display_order,source_author_id) SELECT :target,NULL,type,title,url,content,description,display_order,author_id FROM research_items WHERE task_id=:source AND type IN ("link","note")');
                        $research->execute(['target' => $newTaskId, 'source' => $task['id']]);
                    }
                }
                if ($options['copy_dependencies'] ?? true) {
                    $dependencies = $this->_pdo->prepare('SELECT d.* FROM task_dependencies d JOIN tasks t ON t.id=d.successor_task_id WHERE t.project_id=:id');
                    $dependencies->execute(['id' => $sourceProjectId]);
                    $insertDependency = $this->_pdo->prepare('INSERT INTO task_dependencies (predecessor_task_id,successor_task_id,lag_days) VALUES (:predecessor,:successor,:lag)');
                    foreach ($dependencies->fetchAll() as $dependency) {
                        if (isset($taskMap[(int) $dependency['predecessor_task_id']], $taskMap[(int) $dependency['successor_task_id']])) {
                            $insertDependency->execute(['predecessor' => $taskMap[(int) $dependency['predecessor_task_id']], 'successor' => $taskMap[(int) $dependency['successor_task_id']], 'lag' => $dependency['lag_days']]);
                        }
                    }
                }
            }
            if ($options['copy_important_dates'] ?? false) {
                $dates = $this->_pdo->prepare('INSERT INTO important_dates (title,description,event_date,event_time,end_date,is_all_day,colour,icon,priority,scope_type,project_id,show_countdown,is_active,created_by) SELECT title,description,event_date,event_time,end_date,is_all_day,colour,icon,priority,"project",:target,show_countdown,1,:actor FROM important_dates WHERE project_id=:source');
                $dates->execute(['target' => $targetProjectId, 'actor' => $actorId, 'source' => $sourceProjectId]);
            }
            $this->_pdo->commit();
            return $targetProjectId;
        } catch (\Throwable $exception) {
            if ($this->_pdo->inTransaction()) { $this->_pdo->rollBack(); }
            throw $exception;
        }
    }
}
