<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class UserRepository
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
    public function findById(int $id): ?array
    {
        $statement = $this->_pdo->prepare('SELECT * FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();
        return $user === false ? null : $user;
    }

    public function findByIdentity(string $issuer, string $subject): ?array
    {
        $statement = $this->_pdo->prepare('SELECT * FROM users WHERE oidc_issuer = :issuer AND oidc_subject = :subject');
        $statement->execute(['issuer' => $issuer, 'subject' => $subject]);
        $user = $statement->fetch();
        return $user === false ? null : $user;
    }

    public function provision(array $profile, bool $approve, bool $globalAdmin): array
    {
        $this->_pdo->beginTransaction();
        try {
            $existing = $this->findByIdentity($profile['issuer'], $profile['subject']);
            $created=$existing===null;
            if ($existing === null) {
                $statement = $this->_pdo->prepare(
                    'INSERT INTO users (oidc_issuer, oidc_subject, email, first_name, last_name, display_name, avatar_url, status, approved_at, last_login_at) ' .
                    'VALUES (:issuer, :subject, :email, :first_name, :last_name, :display_name, :avatar_url, :status, :approved_at, UTC_TIMESTAMP())'
                );
                $statement->execute([
                    'issuer' => $profile['issuer'],
                    'subject' => $profile['subject'],
                    'email' => $profile['email'],
                    'first_name' => $profile['first_name'],
                    'last_name' => $profile['last_name'],
                    'display_name' => $profile['display_name'],
                    'avatar_url' => $profile['avatar_url'],
                    'status' => $approve ? 'active' : 'pending',
                    'approved_at' => $approve ? gmdate('Y-m-d H:i:s') : null,
                ]);
                $userId = (int) $this->_pdo->lastInsertId();
                $preferences = $this->_pdo->prepare('INSERT INTO user_preferences (user_id) VALUES (:user_id)');
                $preferences->execute(['user_id' => $userId]);
            } else {
                $userId = (int) $existing['id'];
                $statement = $this->_pdo->prepare(
                    'UPDATE users SET email = :email, first_name = :first_name, last_name = :last_name, ' .
                    'display_name = :display_name, avatar_url = :avatar_url, last_login_at = UTC_TIMESTAMP() WHERE id = :id'
                );
                $statement->execute([
                    'email' => $profile['email'],
                    'first_name' => $profile['first_name'],
                    'last_name' => $profile['last_name'],
                    'display_name' => $profile['display_name'],
                    'avatar_url' => $profile['avatar_url'],
                    'id' => $userId,
                ]);
                if($approve&&$existing['status']==='pending'){$activation=$this->_pdo->prepare("UPDATE users SET status='active',approved_at=COALESCE(approved_at,UTC_TIMESTAMP()) WHERE id=:id AND status='pending'");$activation->execute(['id'=>$userId]);}
            }
            if ($globalAdmin&&$created) {
                $role = $this->_pdo->prepare(
                    "INSERT IGNORE INTO scoped_roles (user_id, role, team_id, sub_team_id, granted_by) VALUES (:user_id, 'global_admin', NULL, NULL, NULL)"
                );
                $role->execute(['user_id' => $userId]);
                $activation = $this->_pdo->prepare("UPDATE users SET status = 'active', approved_at = COALESCE(approved_at, UTC_TIMESTAMP()) WHERE id = :id AND status <> 'inactive'");
                $activation->execute(['id' => $userId]);
            }
            $this->_pdo->commit();
            return $this->findById($userId) ?? [];
        } catch (\Throwable $exception) {
            if ($this->_pdo->inTransaction()) {
                $this->_pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function roles(int $userId): array
    {
        $statement = $this->_pdo->prepare('SELECT role, team_id, sub_team_id FROM scoped_roles WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function memberships(int $userId): array
    {
        $statement = $this->_pdo->prepare(
            'SELECT m.*, t.name AS team_name, st.name AS sub_team_name FROM memberships m ' .
            'JOIN teams t ON t.id = m.team_id LEFT JOIN sub_teams st ON st.id = m.sub_team_id WHERE m.user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function paginate(string $search, string $status, int $page, int $perPage): array
    {
        $conditions = ['1=1'];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(display_name LIKE :search_name OR email LIKE :search_email)';
            $parameters += ['search_name' => '%' . $search . '%', 'search_email' => '%' . $search . '%'];
        }
        if (in_array($status, ['pending', 'active', 'inactive'], true)) {
            $conditions[] = 'status = :status';
            $parameters['status'] = $status;
        }
        $where = implode(' AND ', $conditions);
        $count = $this->_pdo->prepare('SELECT COUNT(*) FROM users WHERE ' . $where);
        $count->execute($parameters);
        $statement = $this->_pdo->prepare(
            'SELECT id, display_name, first_name, last_name, email, avatar_url, status, last_login_at, created_at ' .
            'FROM users WHERE ' . $where . ' ORDER BY display_name LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        return ['items' => $statement->fetchAll(), 'total' => (int) $count->fetchColumn(), 'page' => $page, 'per_page' => $perPage];
    }

    public function updateStatus(int $userId, string $status): bool
    {
        $statement = $this->_pdo->prepare(
            "UPDATE users SET status = :status, approved_at = CASE WHEN :approved_status = 'active' THEN COALESCE(approved_at, UTC_TIMESTAMP()) ELSE approved_at END WHERE id = :id"
        );
        $statement->execute(['status' => $status, 'approved_status' => $status, 'id' => $userId]);
        return $statement->rowCount() === 1;
    }
}
