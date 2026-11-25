<?php

declare(strict_types=1);

namespace CrewWeaver\Middleware;

use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Models\RateLimitRepository;

class RateLimitMiddleware
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected RateLimitRepository $_limits;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(RateLimitRepository $limits)
    {
        $this->_limits=$limits;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function handle(Request $request,array $options,callable $next): Response
    {
        if(($options['rate_limit']??true)===false){return $next($request,$options);}$maximum=(int)($options['rate_limit_max']??180);$window=(int)($options['rate_limit_window']??60);$attempts=$this->_limits->consume($request->ip().'|'.$request->path(),$window);if($attempts>$maximum){return $request->isJson()?Response::error('Too many requests. Try again shortly.',429):new Response('Too many requests. Try again shortly.',429,['Retry-After'=>(string)$window]);}return $next($request,$options);
    }
}
