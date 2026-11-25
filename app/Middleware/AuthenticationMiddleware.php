<?php

declare(strict_types=1);

namespace CrewWeaver\Middleware;

use CrewWeaver\Authentication\AuthenticationService;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;

class AuthenticationMiddleware
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected AuthenticationService $_authentication;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(AuthenticationService $authentication)
    {
        $this->_authentication = $authentication;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function handle(Request $request, array $options, callable $next): Response
    {
        if (!($options['auth'] ?? true)) {
            return $next($request, $options);
        }
        $user = $this->_authentication->currentUser();
        if ($user === null) {
            return $request->isJson() ? Response::error('Authentication required.', 401) : Response::redirect('/login');
        }
        if (($options['approved'] ?? true) && !$user->isApproved()) {
            return $request->isJson() ? Response::error('Account approval required.', 403) : Response::redirect('/pending');
        }
        return $next($request, $options);
    }
}
