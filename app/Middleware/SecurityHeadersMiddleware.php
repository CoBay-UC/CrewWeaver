<?php

declare(strict_types=1);

namespace CrewWeaver\Middleware;

use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;

class SecurityHeadersMiddleware
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function handle(Request $request, array $options, callable $next): Response
    {
        return $next($request, $options)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Content-Security-Policy', "default-src 'self'; img-src 'self' https: data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https:")
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }
}
