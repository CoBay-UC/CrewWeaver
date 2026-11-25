<?php

declare(strict_types=1);

namespace CrewWeaver\Core;

use CrewWeaver\Configuration\Configuration;

class Session
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected Configuration $_configuration;

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
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name($this->_configuration->get('SESSION_NAME', 'crewweaver_session'));
        session_set_cookie_params([
            'lifetime' => $this->_configuration->int('SESSION_LIFETIME', 7200),
            'path' => '/',
            'secure' => $this->_configuration->bool('SESSION_SECURE', true),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            setcookie(session_name(),'',time()-42000,'/','',$this->_configuration->bool('SESSION_SECURE',true),true);
            session_destroy();
        }
    }
}
