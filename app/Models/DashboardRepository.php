<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class DashboardRepository
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
    public function summary(int $userId): array
    {
        $tasks = $this->_pdo->prepare(
            'SELECT DISTINCT x.*,p.title AS project_title,p.colour AS project_colour FROM tasks x JOIN projects p ON p.id=x.project_id LEFT JOIN task_members tm ON tm.task_id=x.id ' .
            'WHERE p.is_archived=0 AND (tm.user_id=:member OR x.owner_user_id=:owner) AND x.status<>"Done" ORDER BY COALESCE(x.manual_due_date,x.calculated_finish),x.priority DESC LIMIT 30'
        );
        $tasks->execute(['member' => $userId, 'owner' => $userId]);
        $projects = $this->_pdo->prepare('SELECT DISTINCT p.* FROM projects p LEFT JOIN project_members pm ON pm.project_id=p.id WHERE p.is_archived=0 AND (pm.user_id=:member OR p.lead_user_id=:lead) ORDER BY p.title LIMIT 20');
        $projects->execute(['member' => $userId, 'lead' => $userId]);
        $dates = $this->_pdo->query('SELECT * FROM important_dates WHERE is_active=1 AND show_countdown=1 AND event_date>=UTC_DATE() AND (project_id IS NULL OR project_id IN (SELECT id FROM projects WHERE is_archived=0)) ORDER BY event_date LIMIT 12');
        return ['tasks' => $tasks->fetchAll(), 'projects' => $projects->fetchAll(), 'dates' => $dates->fetchAll()];
    }
}
