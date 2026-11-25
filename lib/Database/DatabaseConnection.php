<?php

declare(strict_types=1);

namespace CrewWeaver\Database;

use CrewWeaver\Configuration\Configuration;
use PDO;

class DatabaseConnection
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected Configuration $_configuration;
    protected ?PDO $_pdo = null;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(Configuration $configuration)
    {
        $this->_configuration = $configuration;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function pdo(): PDO
    {
        if ($this->_pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->_configuration->get('DB_HOST', 'db'),
                $this->_configuration->int('DB_PORT', 3306),
                $this->_configuration->get('DB_DATABASE', 'crewweaver')
            );
            $this->_pdo = new PDO(
                $dsn,
                $this->_configuration->get('DB_USERNAME', 'crewweaver'),
                $this->_configuration->get('DB_PASSWORD'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
                ]
            );
        }

        return $this->_pdo;
    }
}
