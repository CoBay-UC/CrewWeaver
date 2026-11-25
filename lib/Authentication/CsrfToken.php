<?php

declare(strict_types=1);

namespace CrewWeaver\Authentication;

use CrewWeaver\Core\Session;

class CsrfToken
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected Session $_session;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(Session $session)
    {
        $this->_session = $session;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function value(): string
    {
        $token = $this->_session->get('csrf_token');
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $this->_session->set('csrf_token', $token);
        }
        return $token;
    }

    public function verify(?string $candidate): bool
    {
        return is_string($candidate) && hash_equals($this->value(), $candidate);
    }
}
