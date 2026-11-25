<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class WorkspaceRevisionRepository
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
    public function current(): string
    {
        return (string) $this->_pdo->query('SELECT revision FROM workspace_revision WHERE id=1')->fetchColumn();
    }

    public function advance(): void
    {
        $this->_pdo->exec('UPDATE workspace_revision SET revision=revision+1 WHERE id=1');
    }
}
