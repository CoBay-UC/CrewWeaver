<?php

declare(strict_types=1);

namespace CrewWeaver\Core;

class Route
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected string $_method;
    protected string $_path;
    protected mixed $_handler;
    protected array $_options;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(string $method, string $path, callable $handler, array $options)
    {
        $this->_method = strtoupper($method);
        $this->_path = $path;
        $this->_handler = $handler;
        $this->_options = $options;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function match(Request $request): ?array
    {
        if ($request->method() !== $this->_method) {
            return null;
        }
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $this->_path);
        if (!preg_match('#^' . $pattern . '$#', $request->path(), $matches)) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    public function handler(): callable
    {
        return $this->_handler;
    }

    public function options(): array
    {
        return $this->_options;
    }
}
