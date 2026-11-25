<?php

declare(strict_types=1);

namespace CrewWeaver\Middleware;

use CrewWeaver\Authentication\CsrfToken;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;

class CsrfMiddleware
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected CsrfToken $_csrf;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(CsrfToken $csrf)
    {
        $this->_csrf = $csrf;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function handle(Request $request, array $options, callable $next): Response
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) || !($options['csrf'] ?? true)) {
            return $next($request, $options);
        }
        $candidate = $request->header('x-csrf-token') ?? $request->input('_csrf');
        if (!$this->_csrf->verify(is_string($candidate) ? $candidate : null)) {
            return $request->isJson() ? Response::error('CSRF token validation failed.', 419) : new Response('CSRF token validation failed.', 419);
        }
        return $next($request, $options);
    }
}
