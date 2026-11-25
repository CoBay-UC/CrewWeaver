<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class TeamRepository
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
    protected function _slug(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        return $slug !== '' ? $slug : 'scope-' . bin2hex(random_bytes(3));
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function all(bool $includeInactive = false): array
    {
        $where = $includeInactive ? '1=1' : 't.is_active = 1';
        $statement = $this->_pdo->query(
            'SELECT t.*, (SELECT COUNT(*) FROM sub_teams st WHERE st.team_id=t.id) AS sub_team_count, ' .
            '(SELECT COUNT(*) FROM projects p WHERE p.team_id=t.id AND p.is_archived=0) AS project_count ' .
            'FROM teams t WHERE ' . $where . ' ORDER BY t.display_order, t.name'
        );
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->_pdo->prepare('SELECT * FROM teams WHERE id = :id');
        $statement->execute(['id' => $id]);
        $team = $statement->fetch();
        if ($team === false) {
            return null;
        }
        $team['sub_teams'] = $this->subTeams($id, true);
        return $team;
    }

    public function hierarchy(bool $includeInactive = false): array
    {
        $rows = $this->_pdo->query(
            'SELECT * FROM sub_teams' . ($includeInactive ? '' : ' WHERE is_active=1') . ' ORDER BY display_order,name'
        )->fetchAll();
        $children = [];
        foreach ($rows as $row) {
            $children[$row['team_id']][] = $row;
        }
        $teams = $this->all($includeInactive);
        foreach ($teams as &$team) {
            $team['sub_teams'] = $children[$team['id']] ?? [];
        }
        return $teams;
    }

    public function subTeams(int $teamId, bool $includeInactive = false): array
    {
        $statement = $this->_pdo->prepare(
            'SELECT st.*, (SELECT COUNT(*) FROM projects p WHERE p.sub_team_id=st.id AND p.is_archived=0) AS project_count ' .
            'FROM sub_teams st WHERE st.team_id=:team_id' . ($includeInactive ? '' : ' AND st.is_active=1') . ' ORDER BY st.display_order, st.name'
        );
        $statement->execute(['team_id' => $teamId]);
        return $statement->fetchAll();
    }

    public function create(array $data): array
    {
        $statement = $this->_pdo->prepare(
            'INSERT INTO teams (name,slug,description,colour,icon,is_active,display_order) VALUES (:name,:slug,:description,:colour,:icon,:is_active,:display_order)'
        );
        $statement->execute([
            'name' => trim($data['name']),
            'slug' => $this->_slug($data['slug'] ?? $data['name']),
            'description' => $data['description'] ?? null,
            'colour' => $data['colour'] ?? '#4f46e5',
            'icon' => $data['icon'] ?? 'people',
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
            'display_order' => (int) ($data['display_order'] ?? 0),
        ]);
        return $this->find((int) $this->_pdo->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $current = $this->find($id);
        if ($current === null) {
            return null;
        }
        $statement = $this->_pdo->prepare(
            'UPDATE teams SET name=:name,slug=:slug,description=:description,colour=:colour,icon=:icon,is_active=:is_active,display_order=:display_order WHERE id=:id'
        );
        $statement->execute([
            'name' => trim($data['name'] ?? $current['name']),
            'slug' => $this->_slug($data['slug'] ?? $current['slug']),
            'description' => $data['description'] ?? $current['description'],
            'colour' => $data['colour'] ?? $current['colour'],
            'icon' => $data['icon'] ?? $current['icon'],
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : (int) $current['is_active'],
            'display_order' => (int) ($data['display_order'] ?? $current['display_order']),
            'id' => $id,
        ]);
        return $this->find($id);
    }

    public function createSubTeam(array $data): array
    {
        $statement = $this->_pdo->prepare(
            'INSERT INTO sub_teams (team_id,name,slug,description,colour,icon,is_active,display_order) VALUES (:team_id,:name,:slug,:description,:colour,:icon,:is_active,:display_order)'
        );
        $statement->execute([
            'team_id' => (int) $data['team_id'],
            'name' => trim($data['name']),
            'slug' => $this->_slug($data['slug'] ?? $data['name']),
            'description' => $data['description'] ?? null,
            'colour' => $data['colour'] ?? '#0ea5e9',
            'icon' => $data['icon'] ?? 'diagram-3',
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
            'display_order' => (int) ($data['display_order'] ?? 0),
        ]);
        return $this->findSubTeam((int) $this->_pdo->lastInsertId()) ?? [];
    }

    public function findSubTeam(int $id): ?array
    {
        $statement = $this->_pdo->prepare('SELECT * FROM sub_teams WHERE id=:id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function updateSubTeam(int $id, array $data): ?array
    {
        $current = $this->findSubTeam($id);
        if ($current === null) {
            return null;
        }
        $statement = $this->_pdo->prepare(
            'UPDATE sub_teams SET name=:name,slug=:slug,description=:description,colour=:colour,icon=:icon,is_active=:is_active,display_order=:display_order WHERE id=:id'
        );
        $statement->execute([
            'name' => trim($data['name'] ?? $current['name']),
            'slug' => $this->_slug($data['slug'] ?? $current['slug']),
            'description' => $data['description'] ?? $current['description'],
            'colour' => $data['colour'] ?? $current['colour'],
            'icon' => $data['icon'] ?? $current['icon'],
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : (int) $current['is_active'],
            'display_order' => (int) ($data['display_order'] ?? $current['display_order']),
            'id' => $id,
        ]);
        return $this->findSubTeam($id);
    }

}
