<?php

declare(strict_types=1);

namespace CrewWeaver\Authentication;

use CrewWeaver\Configuration\Configuration;
use CrewWeaver\Core\Session;
use CrewWeaver\Models\UserRepository;

class AuthenticationService
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected Configuration $_configuration;
    protected Session $_session;
    protected UserRepository $_users;
    protected ?AuthenticatedUser $_current = null;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(Configuration $configuration, Session $session, UserRepository $users)
    {
        $this->_configuration = $configuration;
        $this->_session = $session;
        $this->_users = $users;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _sessionUserId(): ?int
    {
        $userId = $this->_session->get('user_id');
        $lastActivity=(int)$this->_session->get('last_activity',0);
        if($userId!==null&&$lastActivity>0&&time()-$lastActivity>$this->_configuration->int('SESSION_LIFETIME',7200)){$this->_session->destroy();return null;}
        if($userId!==null){$this->_session->set('last_activity',time());}
        if ($userId === null && $this->_configuration->get('APP_ENV', 'production') === 'development' && $this->_configuration->bool('DEV_AUTH_ENABLED')) {
            return $this->_configuration->int('DEV_AUTH_USER_ID', 1);
        }
        return $userId === null ? null : (int) $userId;
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function currentUser(): ?AuthenticatedUser
    {
        if ($this->_current !== null) {
            return $this->_current;
        }
        $userId = $this->_sessionUserId();
        if ($userId === null) {
            return null;
        }
        $record = $this->_users->findById($userId);
        if ($record === null) {
            $this->_session->remove('user_id');
            return null;
        }
        $this->_current = new AuthenticatedUser($record, $this->_users->roles($userId), $this->_users->memberships($userId));
        return $this->_current;
    }

    public function login(array $user): void
    {
        $this->_session->regenerate();
        $this->_session->set('user_id', (int) $user['id']);
        $this->_session->set('last_activity',time());
        $this->_current = null;
    }

    public function logout(): void
    {
        $this->_current = null;
        $this->_session->destroy();
    }
}
