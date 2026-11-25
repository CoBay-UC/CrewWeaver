<?php

declare(strict_types=1);

namespace CrewWeaver\Core;

class Request
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected string $_method;
    protected string $_path;
    protected array $_query;
    protected array $_body;
    protected array $_headers;
    protected array $_files;
    protected array $_routeParameters = [];

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(string $method, string $path, array $query, array $body, array $headers, array $files)
    {
        $this->_method = strtoupper($method);
        $this->_path = '/' . ltrim($path, '/');
        $this->_query = $query;
        $this->_body = $body;
        $this->_headers = array_change_key_case($headers, CASE_LOWER);
        $this->_files = $files;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public static function fromGlobals(): self
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $body = $_POST;
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $body = is_array($decoded) ? $decoded : [];
        }
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($body['_method'])) {
            $method = (string) $body['_method'];
        }
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

        return new self($method, $path, $_GET, $body, getallheaders() ?: [], $_FILES);
    }

    public function method(): string
    {
        return $this->_method;
    }

    public function path(): string
    {
        return $this->_path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->_query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->_body[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->_body;
    }

    public function header(string $name): ?string
    {
        return $this->_headers[strtolower($name)] ?? null;
    }

    public function file(string $name): ?array
    {
        return $this->_files[$name] ?? null;
    }

    public function setRouteParameters(array $parameters): void
    {
        $this->_routeParameters = $parameters;
    }

    public function parameter(string $name): string
    {
        return (string) ($this->_routeParameters[$name] ?? '');
    }

    public function isJson(): bool
    {
        return str_starts_with($this->_path, '/api/') || str_contains((string) $this->header('accept'), 'application/json');
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
