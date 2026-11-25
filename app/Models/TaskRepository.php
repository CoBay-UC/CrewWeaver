<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;
use RuntimeException;

class TaskRepository
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
    protected function _syncMembers(int $taskId, array $memberIds, int $actorId): void
    {
        $memberIds=array_values(array_unique(array_filter(array_map('intval',$memberIds))));$eligible=$this->_pdo->prepare('SELECT COUNT(*) FROM users WHERE id=:id AND status="active"');foreach($memberIds as $userId){$eligible->execute(['id'=>$userId]);if((int)$eligible->fetchColumn()!==1){throw new RuntimeException('Every task assignee must be an active approved member.');}}
        $this->_pdo->prepare('DELETE FROM task_members WHERE task_id=:id')->execute(['id' => $taskId]);
        $statement = $this->_pdo->prepare('INSERT IGNORE INTO task_members (task_id,user_id,assigned_by) VALUES (:task_id,:user_id,:actor_id)');
        foreach ($memberIds as $userId) {
            if ($userId > 0) {
                $statement->execute(['task_id' => $taskId, 'user_id' => $userId, 'actor_id' => $actorId]);
            }
        }
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function find(int $id): ?array
    {
        $statement = $this->_pdo->prepare(
            'SELECT x.*,p.title AS project_title,p.team_id,p.sub_team_id,p.is_archived,u.display_name AS owner_name ' .
            'FROM tasks x JOIN projects p ON p.id=x.project_id LEFT JOIN users u ON u.id=x.owner_user_id WHERE x.id=:id'
        );
        $statement->execute(['id' => $id]);
        $task = $statement->fetch();
        if ($task === false) {
            return null;
        }
        $members = $this->_pdo->prepare('SELECT u.id,u.display_name,u.avatar_url FROM task_members tm JOIN users u ON u.id=tm.user_id WHERE tm.task_id=:id ORDER BY u.display_name');
        $members->execute(['id' => $id]);
        $task['members'] = $members->fetchAll();
        $task['milestones'] = $this->milestones($id);
        $task['dependencies'] = $this->dependencies((int) $task['project_id'], $id);
        return $task;
    }

    public function projectTasks(int $projectId): array
    {
        $statement = $this->_pdo->prepare(
            'SELECT x.*,p.title AS project_title,p.team_id,p.sub_team_id,u.display_name AS owner_name, ' .
            '(SELECT COUNT(*) FROM comments c WHERE c.task_id=x.id) AS comment_count, ' .
            '(SELECT COUNT(*) FROM research_items r WHERE r.task_id=x.id) AS research_count, ' .
            '(SELECT COUNT(*) FROM milestones m WHERE m.task_id=x.id) AS milestone_count, ' .
            '(SELECT COUNT(*) FROM milestones m WHERE m.task_id=x.id AND m.is_completed=1) AS completed_milestone_count ' .
            'FROM tasks x JOIN projects p ON p.id=x.project_id LEFT JOIN users u ON u.id=x.owner_user_id WHERE x.project_id=:project_id ORDER BY x.status,x.kanban_order,x.id'
        );
        $statement->execute(['project_id' => $projectId]);
        $tasks = $statement->fetchAll();
        $memberStatement = $this->_pdo->prepare('SELECT u.id,u.display_name,u.avatar_url FROM task_members tm JOIN users u ON u.id=tm.user_id WHERE tm.task_id=:id ORDER BY u.display_name');
        foreach ($tasks as &$task) {
            $memberStatement->execute(['id' => $task['id']]);
            $task['members'] = $memberStatement->fetchAll();
        }
        return $tasks;
    }

    public function create(array $data, int $actorId): array
    {
        $this->_pdo->beginTransaction();
        try {
            $statement = $this->_pdo->prepare(
                'INSERT INTO tasks (project_id,title,description,status,priority,owner_user_id,scheduling_mode,dates_locked,planned_start,calculated_finish,manual_due_date,estimated_duration_days,progress,kanban_order,created_by) ' .
                'VALUES (:project_id,:title,:description,:status,:priority,:owner_user_id,:scheduling_mode,:dates_locked,:planned_start,:calculated_finish,:manual_due_date,:duration,:progress,:kanban_order,:created_by)'
            );
            $statement->execute([
                'project_id' => (int) $data['project_id'], 'title' => trim($data['title']), 'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'Not Started', 'priority' => $data['priority'] ?? 'normal', 'owner_user_id' => ($data['owner_user_id'] ?? null) ?: null,
                'scheduling_mode' => $data['scheduling_mode'] ?? 'automatic', 'dates_locked' => (int) ($data['dates_locked'] ?? 0),
                'planned_start' => ($data['planned_start'] ?? null) ?: null, 'calculated_finish' => ($data['calculated_finish'] ?? null) ?: null,
                'manual_due_date' => ($data['manual_due_date'] ?? null) ?: null, 'duration' => max(0, (int) ($data['estimated_duration_days'] ?? 1)),
                'progress' => max(0, min(100, (int) ($data['progress'] ?? 0))), 'kanban_order' => (float) ($data['kanban_order'] ?? 1000), 'created_by' => $actorId,
            ]);
            $id = (int) $this->_pdo->lastInsertId();
            $members = (array) ($data['member_ids'] ?? []);
            if (!empty($data['owner_user_id'])) {
                $members[] = (int) $data['owner_user_id'];
            }
            $this->_syncMembers($id, $members, $actorId);
            $this->_pdo->commit();
            return $this->find($id) ?? [];
        } catch (\Throwable $exception) {
            if ($this->_pdo->inTransaction()) { $this->_pdo->rollBack(); }
            throw $exception;
        }
    }

    public function update(int $id, array $data, int $actorId): ?array
    {
        $current = $this->find($id);
        if ($current === null) { return null; }
        $version = (int) ($data['version'] ?? $current['version']);
        $this->_pdo->beginTransaction();
        try {
        $statement = $this->_pdo->prepare(
            'UPDATE tasks SET title=:title,description=:description,status=:status,priority=:priority,scheduling_mode=:scheduling_mode,dates_locked=:dates_locked,' .
            'planned_start=:planned_start,calculated_finish=:calculated_finish,manual_due_date=:manual_due_date,estimated_duration_days=:duration,progress=:progress,version=version+1 ' .
            'WHERE id=:id AND version=:version'
        );
        $statement->execute([
            'title' => trim($data['title'] ?? $current['title']), 'description' => $data['description'] ?? $current['description'],
            'status' => $data['status'] ?? $current['status'], 'priority' => $data['priority'] ?? $current['priority'],
            'scheduling_mode' => $data['scheduling_mode'] ?? $current['scheduling_mode'], 'dates_locked' => (int) ($data['dates_locked'] ?? $current['dates_locked']),
            'planned_start' => $data['planned_start'] ?? $current['planned_start'], 'calculated_finish' => $data['calculated_finish'] ?? $current['calculated_finish'],
            'manual_due_date' => $data['manual_due_date'] ?? $current['manual_due_date'], 'duration' => (int) ($data['estimated_duration_days'] ?? $current['estimated_duration_days']),
            'progress' => (int) ($data['progress'] ?? $current['progress']), 'id' => $id, 'version' => $version,
        ]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('The task changed while you were editing.'); }
        if (array_key_exists('member_ids', $data)) { $this->_syncMembers($id, (array) $data['member_ids'], $actorId); }
        $this->_pdo->commit();
        return $this->find($id);
        } catch (\Throwable $exception) {
            if ($this->_pdo->inTransaction()) { $this->_pdo->rollBack(); }
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->_pdo->prepare('DELETE FROM tasks WHERE id=:id');
        $statement->execute(['id' => $id]);
    }

    public function updateBoardPosition(int $id, string $status, float $order, int $version): array
    {
        $statement = $this->_pdo->prepare('UPDATE tasks SET status=:status,kanban_order=:ordering,version=version+1 WHERE id=:id AND version=:version');
        $statement->execute(['status' => $status, 'ordering' => $order, 'id' => $id, 'version' => $version]);
        if ($statement->rowCount() !== 1) { throw new RuntimeException('The task moved or changed while you were editing.'); }
        return $this->find($id) ?? [];
    }

    public function transferOwner(int $id, ?int $ownerId, int $actorId, ?string $reason = null): array
    {
        $current = $this->find($id);
        if ($current === null) { throw new RuntimeException('Task not found.'); }
        if($ownerId!==null){$eligible=$this->_pdo->prepare('SELECT COUNT(*) FROM users WHERE id=:id AND status="active"');$eligible->execute(['id'=>$ownerId]);if((int)$eligible->fetchColumn()!==1){throw new RuntimeException('The selected owner is not an active approved member.');}}
        $this->_pdo->beginTransaction();
        try {
            $update = $this->_pdo->prepare('UPDATE tasks SET owner_user_id=:owner_id,version=version+1 WHERE id=:id');
            $update->execute(['owner_id' => $ownerId, 'id' => $id]);
            if ($ownerId !== null) {
                $member = $this->_pdo->prepare('INSERT IGNORE INTO task_members (task_id,user_id,assigned_by) VALUES (:task_id,:user_id,:actor_id)');
                $member->execute(['task_id' => $id, 'user_id' => $ownerId, 'actor_id' => $actorId]);
            }
            $history = $this->_pdo->prepare('INSERT INTO ownership_history (task_id,previous_owner_id,new_owner_id,changed_by,reason) VALUES (:task_id,:previous,:new,:actor,:reason)');
            $history->execute(['task_id' => $id, 'previous' => $current['owner_user_id'], 'new' => $ownerId, 'actor' => $actorId, 'reason' => $reason]);
            $this->_pdo->commit();
            return $this->find($id) ?? [];
        } catch (\Throwable $exception) {
            if ($this->_pdo->inTransaction()) { $this->_pdo->rollBack(); }
            throw $exception;
        }
    }

    public function milestones(int $taskId): array
    {
        $statement = $this->_pdo->prepare('SELECT * FROM milestones WHERE task_id=:task_id ORDER BY display_order,id');
        $statement->execute(['task_id' => $taskId]);
        return $statement->fetchAll();
    }

    public function saveMilestones(int $taskId, array $milestones): array
    {
        $this->_pdo->beginTransaction();
        try {
            $existing = $this->_pdo->prepare('SELECT id FROM milestones WHERE task_id=:task_id');
            $existing->execute(['task_id' => $taskId]);
            $existingIds = array_map('intval', $existing->fetchAll(PDO::FETCH_COLUMN));
            $kept = [];
            $offset = 0;
            foreach (array_values($milestones) as $position => $milestone) {
                $duration = max(0, (int) ($milestone['duration_days'] ?? 0));
                $offset += $duration;
                $id = (int) ($milestone['id'] ?? 0);
                if ($id > 0 && in_array($id, $existingIds, true)) {
                    $statement = $this->_pdo->prepare('UPDATE milestones SET title=:title,description=:description,duration_days=:duration,cumulative_offset_days=:offset,is_completed=:completed,actual_completion=:actual,notes=:notes,display_order=:ordering WHERE id=:id AND task_id=:task_id');
                    $statement->execute(['title' => trim($milestone['title']), 'description' => $milestone['description'] ?? null, 'duration' => $duration, 'offset' => $offset, 'completed' => (int) ($milestone['is_completed'] ?? 0), 'actual' => $milestone['actual_completion'] ?? null, 'notes' => $milestone['notes'] ?? null, 'ordering' => ($position + 1) * 10, 'id' => $id, 'task_id' => $taskId]);
                } else {
                    $statement = $this->_pdo->prepare('INSERT INTO milestones (task_id,title,description,duration_days,cumulative_offset_days,is_completed,actual_completion,notes,display_order) VALUES (:task_id,:title,:description,:duration,:offset,:completed,:actual,:notes,:ordering)');
                    $statement->execute(['task_id' => $taskId, 'title' => trim($milestone['title']), 'description' => $milestone['description'] ?? null, 'duration' => $duration, 'offset' => $offset, 'completed' => (int) ($milestone['is_completed'] ?? 0), 'actual' => $milestone['actual_completion'] ?? null, 'notes' => $milestone['notes'] ?? null, 'ordering' => ($position + 1) * 10]);
                    $id = (int) $this->_pdo->lastInsertId();
                }
                if(array_key_exists('member_ids',$milestone)){$this->_pdo->prepare('DELETE FROM milestone_members WHERE milestone_id=:id')->execute(['id'=>$id]);$memberInsert=$this->_pdo->prepare('INSERT IGNORE INTO milestone_members (milestone_id,user_id) SELECT :milestone_id,id FROM users WHERE id=:user_id AND status="active"');foreach(array_unique(array_map('intval',(array)$milestone['member_ids'])) as $memberId){$memberInsert->execute(['milestone_id'=>$id,'user_id'=>$memberId]);if($memberInsert->rowCount()!==1){throw new RuntimeException('Every milestone assignee must be an active approved member.');}}}
                $kept[] = $id;
            }
            $deleteIds = array_diff($existingIds, $kept);
            foreach ($deleteIds as $deleteId) { $this->_pdo->prepare('DELETE FROM milestones WHERE id=:id')->execute(['id' => $deleteId]); }
            $this->_pdo->prepare('UPDATE tasks SET estimated_duration_days=:duration,version=version+1 WHERE id=:id')->execute(['duration' => $offset, 'id' => $taskId]);
            $this->_pdo->commit();
            return $this->milestones($taskId);
        } catch (\Throwable $exception) {
            if ($this->_pdo->inTransaction()) { $this->_pdo->rollBack(); }
            throw $exception;
        }
    }

    public function dependencies(int $projectId, ?int $taskId = null): array
    {
        $sql = 'SELECT d.* FROM task_dependencies d JOIN tasks s ON s.id=d.successor_task_id JOIN tasks p ON p.id=d.predecessor_task_id WHERE s.project_id=:project_id AND p.project_id=:same_project';
        $parameters = ['project_id' => $projectId, 'same_project' => $projectId];
        if ($taskId !== null) {
            $sql .= ' AND (d.successor_task_id=:successor_id OR d.predecessor_task_id=:predecessor_id)';
            $parameters += ['successor_id' => $taskId, 'predecessor_id' => $taskId];
        }
        $statement = $this->_pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function saveDependencies(int $taskId, array $predecessors): array
    {
        $task = $this->find($taskId);
        if ($task === null) { throw new RuntimeException('Task not found.'); }
        $this->_pdo->beginTransaction();
        try {
            $this->_pdo->prepare('DELETE FROM task_dependencies WHERE successor_task_id=:id')->execute(['id' => $taskId]);
            $statement = $this->_pdo->prepare('INSERT INTO task_dependencies (predecessor_task_id,successor_task_id,lag_days) SELECT :predecessor,:successor,:lag FROM tasks p WHERE p.id=:valid_predecessor AND p.project_id=:project_id');
            foreach ($predecessors as $dependency) {
                $predecessor = (int) $dependency['task_id'];
                if ($predecessor === $taskId) { throw new RuntimeException('A task cannot depend on itself.'); }
                $statement->execute(['predecessor' => $predecessor, 'successor' => $taskId, 'lag' => (int) ($dependency['lag_days'] ?? 0), 'valid_predecessor' => $predecessor, 'project_id' => $task['project_id']]);
                if ($statement->rowCount() !== 1) { throw new RuntimeException('Dependency must reference a task in the same project.'); }
            }
            $this->_pdo->commit();
            return $this->dependencies((int) $task['project_id'], $taskId);
        } catch (\Throwable $exception) {
            if ($this->_pdo->inTransaction()) { $this->_pdo->rollBack(); }
            throw $exception;
        }
    }

    public function comments(int $taskId): array
    {
        $statement = $this->_pdo->prepare('SELECT c.*,u.display_name,u.avatar_url FROM comments c LEFT JOIN users u ON u.id=c.author_id WHERE c.task_id=:id ORDER BY c.created_at');
        $statement->execute(['id' => $taskId]);
        return $statement->fetchAll();
    }

    public function addComment(int $taskId, int $authorId, string $body, array $mentionUserIds=[]): array
    {
        $this->_pdo->beginTransaction();try{$statement = $this->_pdo->prepare('INSERT INTO comments (task_id,author_id,body) VALUES (:task_id,:author_id,:body)');$statement->execute(['task_id' => $taskId, 'author_id' => $authorId, 'body' => trim($body)]);$id = (int) $this->_pdo->lastInsertId();$mention=$this->_pdo->prepare('INSERT IGNORE INTO comment_mentions (comment_id,user_id) SELECT :comment_id,id FROM users WHERE id=:user_id AND status="active"');foreach(array_unique(array_map('intval',$mentionUserIds)) as $userId){$mention->execute(['comment_id'=>$id,'user_id'=>$userId]);if($mention->rowCount()!==1){throw new RuntimeException('Mentioned users must be active approved members.');}}$this->_pdo->commit();return array_values(array_filter($this->comments($taskId), static fn (array $comment): bool => (int) $comment['id'] === $id))[0] ?? [];}catch(\Throwable $exception){if($this->_pdo->inTransaction()){$this->_pdo->rollBack();}throw $exception;}
    }

    public function participantIds(int $taskId): array
    {
        $statement=$this->_pdo->prepare('SELECT user_id FROM task_members WHERE task_id=:id UNION SELECT owner_user_id FROM tasks WHERE id=:owner_task AND owner_user_id IS NOT NULL');$statement->execute(['id'=>$taskId,'owner_task'=>$taskId]);return array_map('intval',$statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function findComment(int $commentId): ?array
    {
        $statement=$this->_pdo->prepare('SELECT c.*,t.project_id FROM comments c JOIN tasks t ON t.id=c.task_id WHERE c.id=:id');$statement->execute(['id'=>$commentId]);$row=$statement->fetch();return $row===false?null:$row;
    }

    public function editComment(int $commentId, int $actorId, string $body, bool $moderate): bool
    {
        $statement = $this->_pdo->prepare('UPDATE comments SET body=:body,edited_at=UTC_TIMESTAMP() WHERE id=:id AND (author_id=:actor OR :moderate=1)');
        $statement->execute(['body' => trim($body), 'id' => $commentId, 'actor' => $actorId, 'moderate' => (int) $moderate]);
        return $statement->rowCount() === 1;
    }

    public function deleteComment(int $commentId, int $actorId, bool $moderate): bool
    {
        $statement = $this->_pdo->prepare('DELETE FROM comments WHERE id=:id AND (author_id=:actor OR :moderate=1)');
        $statement->execute(['id' => $commentId, 'actor' => $actorId, 'moderate' => (int) $moderate]);
        return $statement->rowCount() === 1;
    }

    public function research(int $taskId): array
    {
        $statement = $this->_pdo->prepare('SELECT r.*,u.display_name AS author_name FROM research_items r LEFT JOIN users u ON u.id=r.author_id WHERE r.task_id=:id ORDER BY r.display_order,r.id');
        $statement->execute(['id' => $taskId]);
        return $statement->fetchAll();
    }

    public function addResearch(int $taskId, int $authorId, array $data): array
    {
        $statement = $this->_pdo->prepare('INSERT INTO research_items (task_id,author_id,type,title,url,content,description,display_order) VALUES (:task_id,:author_id,:type,:title,:url,:content,:description,:ordering)');
        $statement->execute(['task_id' => $taskId, 'author_id' => $authorId, 'type' => $data['type'], 'title' => trim($data['title']), 'url' => $data['url'] ?? null, 'content' => $data['content'] ?? null, 'description' => $data['description'] ?? null, 'ordering' => (int) ($data['display_order'] ?? 0)]);
        $id = (int) $this->_pdo->lastInsertId();
        return array_values(array_filter($this->research($taskId), static fn (array $item): bool => (int) $item['id'] === $id))[0] ?? [];
    }

    public function findResearch(int $researchId): ?array
    {
        $statement=$this->_pdo->prepare('SELECT r.*,t.project_id FROM research_items r JOIN tasks t ON t.id=r.task_id WHERE r.id=:id');$statement->execute(['id'=>$researchId]);$row=$statement->fetch();return $row===false?null:$row;
    }

    public function editResearch(int $researchId,int $actorId,array $data,bool $moderate): bool
    {
        $statement=$this->_pdo->prepare('UPDATE research_items SET title=:title,url=:url,content=:content,description=:description,display_order=:ordering WHERE id=:id AND (author_id=:actor OR :moderate=1)');$statement->execute(['title'=>trim($data['title']),'url'=>$data['url']??null,'content'=>$data['content']??null,'description'=>$data['description']??null,'ordering'=>(int)($data['display_order']??0),'id'=>$researchId,'actor'=>$actorId,'moderate'=>(int)$moderate]);return $statement->rowCount()===1;
    }

    public function deleteResearch(int $researchId, int $actorId, bool $moderate): bool
    {
        $statement = $this->_pdo->prepare('DELETE FROM research_items WHERE id=:id AND (author_id=:actor OR :moderate=1)');
        $statement->execute(['id' => $researchId, 'actor' => $actorId, 'moderate' => (int) $moderate]);
        return $statement->rowCount() === 1;
    }
}
