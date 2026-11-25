<?php

declare(strict_types=1);

namespace CrewWeaver\Middleware;

use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Models\WorkspaceRevisionRepository;

class WorkspaceRevisionMiddleware
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected WorkspaceRevisionRepository $_revisions;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(WorkspaceRevisionRepository $revisions)
    {
        $this->_revisions = $revisions;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function handle(Request $request, array $options, callable $next): Response
    {
        $response = $next($request, $options);
        if (!in_array($request->method(), ['POST', 'PATCH', 'PUT', 'DELETE'], true)
            || !str_starts_with($request->path(), '/api/')
            || str_starts_with($request->path(), '/api/notifications')) {
            return $response;
        }
        if ($response->status() >= 200 && $response->status() < 300) {
            try {
                $this->_revisions->advance();
                return $response->withHeader('X-CrewWeaver-Changed', '1');
            } catch (\PDOException $exception) {
                // The mutation has already completed. Preserve its response.
                error_log('Could not publish the workspace revision: ' . $exception->getMessage());
            }
        }
        return $response;
    }
}
