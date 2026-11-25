<?php

declare(strict_types=1);

namespace CrewWeaver\Database;

use PDO;
use RuntimeException;

class Migrator
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected PDO $_pdo;
    protected string $_migrationPath;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(PDO $pdo, string $migrationPath)
    {
        $this->_pdo = $pdo;
        $this->_migrationPath = $migrationPath;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _ensureTable(): void
    {
        $this->_pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (' .
            'migration VARCHAR(255) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    protected function _statements(string $sql): array
    {
        return array_values(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[])));
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function migrate(): array
    {
        $this->_ensureTable();
        $applied = $this->_pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob($this->_migrationPath . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files, SORT_STRING);
        $completed = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Cannot read migration ' . $name);
            }
            try {
                foreach($this->_statements($sql) as $migrationStatement){$this->_pdo->exec($migrationStatement);}
                $statement = $this->_pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
                $statement->execute(['migration' => $name]);
                $completed[] = $name;
            } catch (\Throwable $exception) {
                throw $exception;
            }
        }

        return $completed;
    }
}
