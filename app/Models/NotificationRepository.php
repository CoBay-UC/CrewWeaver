<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class NotificationRepository
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
    public function forUser(int $userId, int $limit = 50): array
    {
        $statement = $this->_pdo->prepare('SELECT * FROM notifications WHERE user_id=:user_id ORDER BY created_at DESC LIMIT :limit');
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function unreadCount(int $userId): int
    {
        $statement = $this->_pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:user_id AND read_at IS NULL');
        $statement->execute(['user_id' => $userId]);
        return (int) $statement->fetchColumn();
    }

    public function create(int $userId, string $type, string $title, string $body, ?string $resourceType, ?int $resourceId, ?string $url, ?string $deduplicationKey): void
    {
        $statement = $this->_pdo->prepare('INSERT INTO notifications (user_id,type,title,body,resource_type,resource_id,resource_url,deduplication_key) VALUES (:user_id,:type,:title,:body,:resource_type,:resource_id,:url,:dedupe) ON DUPLICATE KEY UPDATE title=VALUES(title),body=VALUES(body),created_at=UTC_TIMESTAMP(),read_at=NULL');
        $statement->execute(['user_id' => $userId, 'type' => $type, 'title' => $title, 'body' => $body, 'resource_type' => $resourceType, 'resource_id' => $resourceId, 'url' => $url, 'dedupe' => $deduplicationKey]);
    }

    public function markRead(int $notificationId, int $userId): bool
    {
        $statement = $this->_pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,UTC_TIMESTAMP()) WHERE id=:id AND user_id=:user_id');
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);
        return $statement->rowCount() === 1;
    }

    public function markAllRead(int $userId): int
    {
        $statement = $this->_pdo->prepare('UPDATE notifications SET read_at=UTC_TIMESTAMP() WHERE user_id=:user_id AND read_at IS NULL');
        $statement->execute(['user_id' => $userId]);
        return $statement->rowCount();
    }
}
