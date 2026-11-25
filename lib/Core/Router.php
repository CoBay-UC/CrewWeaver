<?php

declare(strict_types=1);

namespace CrewWeaver\Core;

use Throwable;

class Router
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected array $_routes = [];
    protected array $_middleware;
    protected bool $_debug;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(array $middleware, bool $debug)
    {
        $this->_middleware = $middleware;
        $this->_debug = $debug;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _notFound(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::error('Resource not found.', 404);
        }
        return new Response('<h1>404</h1><p>The requested page was not found.</p>', 404, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    protected function _failure(Request $request, Throwable $exception): Response
    {
        error_log($exception->__toString());
        $message = $this->_debug ? $exception->getMessage() : 'An unexpected error occurred.';
        if ($request->isJson()) {
            return Response::error($message, 500);
        }
        return new Response('<h1>Application error</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>', 500);
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function add(string $method, string $path, callable $handler, array $options = []): void
    {
        $this->_routes[] = new Route($method, $path, $handler, $options);
    }

    public function dispatch(Request $request): Response
    {
        try {
            foreach ($this->_routes as $route) {
                $parameters = $route->match($request);
                if ($parameters === null) {
                    continue;
                }
                $request->setRouteParameters($parameters);
                $handler = $route->handler();
                $pipeline = array_reduce(
                    array_reverse($this->_middleware),
                    static fn (callable $next, object $middleware): callable =>
                        static fn (Request $pipelineRequest, array $options): Response => $middleware->handle($pipelineRequest, $options, $next),
                    static fn (Request $pipelineRequest, array $options): Response => $handler($pipelineRequest)
                );
                return $pipeline($request, $route->options());
            }
            return $this->_notFound($request);
        } catch (Throwable $exception) {
            return $this->_failure($request, $exception);
        }
    }
}
