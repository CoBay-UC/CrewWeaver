<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class ScheduleRepository
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
    public function projectGraph(int $projectId): ?array
    {
        $project = $this->_pdo->prepare('SELECT id,team_id,sub_team_id,planned_start,scheduling_mode,target_completion FROM projects WHERE id=:id');
        $project->execute(['id' => $projectId]);
        $record = $project->fetch();
        if ($record === false) { return null; }
        $tasks = $this->_pdo->prepare('SELECT * FROM tasks WHERE project_id=:id ORDER BY id');
        $tasks->execute(['id' => $projectId]);
        $list = $tasks->fetchAll();
        $milestones = $this->_pdo->prepare('SELECT id,title,duration_days FROM milestones WHERE task_id=:task_id ORDER BY display_order,id');
        foreach ($list as &$task) {
            $milestones->execute(['task_id' => $task['id']]);
            $task['milestones'] = $milestones->fetchAll();
        }
        $dependencies = $this->_pdo->prepare('SELECT d.* FROM task_dependencies d JOIN tasks t ON t.id=d.successor_task_id WHERE t.project_id=:id');
        $dependencies->execute(['id' => $projectId]);
        $configuration=$this->_pdo->prepare('SELECT * FROM scheduling_configurations WHERE (scope_type="project" AND scope_id=:project_id) OR (scope_type="sub_team" AND scope_id=:sub_team_id) OR (scope_type="team" AND scope_id=:team_id) OR (scope_type="global" AND scope_id IS NULL) ORDER BY FIELD(scope_type,"project","sub_team","team","global") LIMIT 1');
        $configuration->execute(['project_id'=>$projectId,'sub_team_id'=>$record['sub_team_id']??0,'team_id'=>$record['team_id']]);$calendar=$configuration->fetch();$holidays=[];
        if($calendar!==false){$holidayStatement=$this->_pdo->prepare('SELECT holiday_date FROM holidays WHERE scheduling_configuration_id=:id');$holidayStatement->execute(['id'=>$calendar['id']]);$holidays=$holidayStatement->fetchAll(PDO::FETCH_COLUMN);}
        return ['project' => $record, 'tasks' => $list, 'dependencies' => $dependencies->fetchAll(), 'calendar'=>$calendar===false?null:$calendar, 'holidays'=>$holidays];
    }

    public function apply(int $projectId, array $result): void
    {
        $this->_pdo->beginTransaction();
        try {
            $taskUpdate = $this->_pdo->prepare(
                'UPDATE tasks t JOIN (SELECT CAST(:start AS DATE) AS start_date, CAST(:finish AS DATE) AS finish_date, CAST(:duration AS UNSIGNED) AS duration_days) s '
                . 'SET t.planned_start=s.start_date,t.calculated_finish=s.finish_date,t.estimated_duration_days=s.duration_days,t.version=t.version+1 '
                . 'WHERE t.id=:id AND t.project_id=:project_id AND NOT (t.scheduling_mode="manual" AND t.dates_locked=1) '
                . 'AND NOT (t.planned_start <=> s.start_date AND t.calculated_finish <=> s.finish_date AND t.estimated_duration_days <=> s.duration_days)'
            );
            $milestoneUpdate = $this->_pdo->prepare('UPDATE milestones SET cumulative_offset_days=:offset,calculated_date=:date WHERE id=:id AND task_id=:task_id');
            foreach ($result['tasks'] as $taskId => $task) {
                $taskUpdate->execute(['start' => $task['start'], 'finish' => $task['finish'], 'duration' => $task['duration_days'], 'id' => $taskId, 'project_id' => $projectId]);
                foreach ($task['milestones'] as $milestone) {
                    $milestoneUpdate->execute(['offset' => $milestone['cumulative_offset_days'], 'date' => $milestone['calculated_date'], 'id' => $milestone['id'], 'task_id' => $taskId]);
                }
            }
            $project = $this->_pdo->prepare(
                'UPDATE projects p JOIN (SELECT CAST(:finish AS DATE) AS finish_date, COALESCE(ROUND(AVG(progress)),0) AS progress FROM tasks WHERE project_id=:progress_project) s '
                . 'SET p.calculated_completion=s.finish_date,p.progress=s.progress,p.version=p.version+1 '
                . 'WHERE p.id=:id AND NOT (p.calculated_completion <=> s.finish_date AND p.progress <=> s.progress)'
            );
            $project->execute(['finish' => $result['project_finish'], 'progress_project'=>$projectId, 'id' => $projectId]);
            $this->_pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->_pdo->inTransaction()) { $this->_pdo->rollBack(); }
            throw $exception;
        }
    }
}
