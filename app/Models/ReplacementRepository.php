<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;
use RuntimeException;

class ReplacementRepository
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
    protected function _projectFilter(array $projectIds, bool $includeArchived): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $projectIds))));
        $conditions = [$includeArchived ? '1=1' : 'p.is_archived=0'];
        if ($ids !== []) { $conditions[] = 'p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; }
        return ['sql' => implode(' AND ', $conditions), 'ids' => $ids];
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function preview(int $sourceId, int $replacementId, array $projectIds, bool $includeArchived, array $options=[]): array
    {
        if ($sourceId === $replacementId) { throw new RuntimeException('Source and replacement users must differ.'); }
        $filter = $this->_projectFilter($projectIds, $includeArchived);
        $queries = [
            'project_members' => 'SELECT COUNT(*) FROM project_members pm JOIN projects p ON p.id=pm.project_id WHERE pm.user_id=? AND ' . $filter['sql'],
            'project_leads' => 'SELECT COUNT(*) FROM projects p WHERE p.lead_user_id=? AND ' . $filter['sql'],
            'task_members' => 'SELECT COUNT(*) FROM task_members tm JOIN tasks t ON t.id=tm.task_id JOIN projects p ON p.id=t.project_id WHERE tm.user_id=? AND ' . $filter['sql'],
            'task_owners' => 'SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id=t.project_id WHERE t.owner_user_id=? AND ' . $filter['sql'],
            'milestone_members' => 'SELECT COUNT(*) FROM milestone_members mm JOIN milestones m ON m.id=mm.milestone_id JOIN tasks t ON t.id=m.task_id JOIN projects p ON p.id=t.project_id WHERE mm.user_id=? AND ' . $filter['sql'],
        ];
        $result = [];
        foreach ($queries as $name => $sql) { $statement = $this->_pdo->prepare($sql); $statement->execute(array_merge([$sourceId], $filter['ids'])); $result[$name] = (int) $statement->fetchColumn(); }
        if(array_key_exists('project_assignments',$options)&&!$options['project_assignments']){$result['project_members']=0;}
        if(array_key_exists('task_assignments',$options)&&!$options['task_assignments']){$result['task_members']=0;}
        if(array_key_exists('milestone_assignments',$options)&&!$options['milestone_assignments']){$result['milestone_members']=0;}
        if(array_key_exists('ownership',$options)&&!$options['ownership']){$result['project_leads']=0;$result['task_owners']=0;}
        $result['total'] = array_sum($result);
        return $result;
    }

    public function commit(int $sourceId, int $replacementId, array $projectIds, bool $includeArchived, array $options, int $actorId): array
    {
        $preview = $this->preview($sourceId, $replacementId, $projectIds, $includeArchived,$options);
        $filter = $this->_projectFilter($projectIds, $includeArchived);
        $this->_pdo->beginTransaction();
        try {
            if ($options['project_assignments'] ?? true) {
                $insert = $this->_pdo->prepare('INSERT IGNORE INTO project_members (project_id,user_id,assigned_by) SELECT pm.project_id,?,? FROM project_members pm JOIN projects p ON p.id=pm.project_id WHERE pm.user_id=? AND ' . $filter['sql']);
                $insert->execute(array_merge([$replacementId,$actorId,$sourceId],$filter['ids']));
                $delete = $this->_pdo->prepare('DELETE pm FROM project_members pm JOIN projects p ON p.id=pm.project_id WHERE pm.user_id=? AND ' . $filter['sql']);
                $delete->execute(array_merge([$sourceId],$filter['ids']));
            }
            if ($options['ownership'] ?? true) {
                $history=$this->_pdo->prepare('INSERT INTO ownership_history (task_id,previous_owner_id,new_owner_id,changed_by,reason) SELECT t.id,?,?,?,"User replacement" FROM tasks t JOIN projects p ON p.id=t.project_id WHERE t.owner_user_id=? AND '.$filter['sql']);
                $history->execute(array_merge([$sourceId,$replacementId,$actorId,$sourceId],$filter['ids']));
                $ownerMember=$this->_pdo->prepare('INSERT IGNORE INTO task_members (task_id,user_id,assigned_by) SELECT t.id,?,? FROM tasks t JOIN projects p ON p.id=t.project_id WHERE t.owner_user_id=? AND '.$filter['sql']);
                $ownerMember->execute(array_merge([$replacementId,$actorId,$sourceId],$filter['ids']));
                $update = $this->_pdo->prepare('UPDATE tasks t JOIN projects p ON p.id=t.project_id SET t.owner_user_id=?,t.version=t.version+1 WHERE t.owner_user_id=? AND ' . $filter['sql']);
                $update->execute(array_merge([$replacementId,$sourceId],$filter['ids']));
                $lead = $this->_pdo->prepare('UPDATE projects p SET p.lead_user_id=?,p.version=p.version+1 WHERE p.lead_user_id=? AND ' . $filter['sql']);
                $lead->execute(array_merge([$replacementId,$sourceId],$filter['ids']));
            }
            if ($options['task_assignments'] ?? true) {
                $insert = $this->_pdo->prepare('INSERT IGNORE INTO task_members (task_id,user_id,assigned_by) SELECT tm.task_id,?,? FROM task_members tm JOIN tasks t ON t.id=tm.task_id JOIN projects p ON p.id=t.project_id WHERE tm.user_id=? AND ' . $filter['sql']);
                $insert->execute(array_merge([$replacementId,$actorId,$sourceId],$filter['ids']));
                $delete = $this->_pdo->prepare('DELETE tm FROM task_members tm JOIN tasks t ON t.id=tm.task_id JOIN projects p ON p.id=t.project_id WHERE tm.user_id=? AND ' . $filter['sql']);
                $delete->execute(array_merge([$sourceId],$filter['ids']));
            }
            if($options['milestone_assignments']??true){
                $insert=$this->_pdo->prepare('INSERT IGNORE INTO milestone_members (milestone_id,user_id) SELECT mm.milestone_id,? FROM milestone_members mm JOIN milestones m ON m.id=mm.milestone_id JOIN tasks t ON t.id=m.task_id JOIN projects p ON p.id=t.project_id WHERE mm.user_id=? AND '.$filter['sql']);$insert->execute(array_merge([$replacementId,$sourceId],$filter['ids']));
                $delete=$this->_pdo->prepare('DELETE mm FROM milestone_members mm JOIN milestones m ON m.id=mm.milestone_id JOIN tasks t ON t.id=m.task_id JOIN projects p ON p.id=t.project_id WHERE mm.user_id=? AND '.$filter['sql']);$delete->execute(array_merge([$sourceId],$filter['ids']));
            }
            $run = $this->_pdo->prepare('INSERT INTO replacement_runs (source_user_id,replacement_user_id,initiated_by,scope_description,affected_count) VALUES (?,?,?,?,?)');
            $run->execute([$sourceId,$replacementId,$actorId,json_encode(['project_ids'=>$projectIds,'include_archived'=>$includeArchived,'options'=>$options],JSON_UNESCAPED_SLASHES),$preview['total']]);
            $this->_pdo->commit();
            return $preview + ['run_id' => (int) $this->_pdo->lastInsertId()];
        } catch (\Throwable $exception) {
            if ($this->_pdo->inTransaction()) { $this->_pdo->rollBack(); }
            throw $exception;
        }
    }
}
