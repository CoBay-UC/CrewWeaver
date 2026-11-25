<?php

declare(strict_types=1);

namespace CrewWeaver\Models;

use PDO;

class OrganizationSettingsRepository
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
    public function showsSidebarSubTeams(): bool
    {
        $statement = $this->_pdo->prepare('SELECT setting_value FROM organization_settings WHERE setting_key = :key');
        $statement->execute(['key' => 'show_sidebar_sub_teams']);
        return $statement->fetchColumn() !== '0';
    }

    public function setSidebarSubTeams(bool $show): void
    {
        $statement = $this->_pdo->prepare(
            'INSERT INTO organization_settings (setting_key, setting_value) VALUES (:key, :value) '
            . 'ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
        );
        $statement->execute(['key' => 'show_sidebar_sub_teams', 'value' => $show ? '1' : '0']);
    }
}
