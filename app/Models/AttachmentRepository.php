<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class AttachmentRepository
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
    public function create(int $taskId, int $userId, array $stored, ?string $description): array
    {
        $statement = $this->_pdo->prepare('INSERT INTO attachments (task_id,uploaded_by,storage_name,original_name,mime_type,size_bytes,description) VALUES (:task_id,:user_id,:storage_name,:original_name,:mime_type,:size_bytes,:description)');
        $statement->execute(['task_id' => $taskId, 'user_id' => $userId, 'storage_name' => $stored['storage_name'], 'original_name' => $stored['original_name'], 'mime_type' => $stored['mime_type'], 'size_bytes' => $stored['size_bytes'], 'description' => $description]);
        return $this->find((int) $this->_pdo->lastInsertId()) ?? [];
    }

    public function find(int $id): ?array
    {
        $statement = $this->_pdo->prepare('SELECT a.*,p.team_id,p.sub_team_id,p.is_archived FROM attachments a JOIN tasks t ON t.id=a.task_id JOIN projects p ON p.id=t.project_id WHERE a.id=:id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function taskFiles(int $taskId): array
    {
        $statement = $this->_pdo->prepare('SELECT a.id,a.original_name,a.mime_type,a.size_bytes,a.description,a.created_at,u.display_name AS author_name FROM attachments a LEFT JOIN users u ON u.id=a.uploaded_by WHERE a.task_id=:id ORDER BY a.created_at DESC');
        $statement->execute(['id' => $taskId]);
        return $statement->fetchAll();
    }

    public function delete(int $id): ?string
    {
        $file = $this->find($id);
        if ($file === null) { return null; }
        $this->_pdo->prepare('DELETE FROM attachments WHERE id=:id')->execute(['id' => $id]);
        return $file['storage_name'];
    }
}
