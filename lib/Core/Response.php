<?php

declare(strict_types=1);

namespace CrewWeaver\Core;

class Response
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected string $_body;
    protected int $_status;
    protected array $_headers;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->_body = $body;
        $this->_status = $status;
        $this->_headers = $headers;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function success(string $message, mixed $data = [], int $status = 200): self
    {
        return self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status, array $errors = []): self
    {
        $payload = ['success' => false, 'message' => $message];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }
        return self::json($payload, $status);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        $response = clone $this;
        $response->_headers[$name] = $value;
        return $response;
    }

    public function status(): int
    {
        return $this->_status;
    }

    public function emit(): void
    {
        http_response_code($this->_status);
        foreach ($this->_headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->_body;
    }
}
