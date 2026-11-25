<?php

declare(strict_types=1);

namespace CrewWeaver\Configuration;

use RuntimeException;

class Configuration
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected array $_values;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(array $values)
    {
        $this->_values = $values;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public static function fromEnvironment(string $rootPath): self
    {
        $values = $_ENV + getenv();
        $environmentFile = $rootPath . DIRECTORY_SEPARATOR . '.env';
        if (is_file($environmentFile)) {
            $lines = file($environmentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                if (!array_key_exists($key, $values)) {
                    $values[$key] = trim(trim($value), "\"'");
                }
            }
        }

        return new self($values);
    }

    public function get(string $key, ?string $default = null): string
    {
        $value = $this->_values[$key] ?? $default;
        if ($value === null) {
            throw new RuntimeException('Missing required configuration: ' . $key);
        }

        return (string) $value;
    }

    public function optional(string $key): ?string
    {
        $value = $this->_values[$key] ?? null;
        return $value === null || $value === '' ? null : (string) $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->_values[$key] ?? $default;
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function int(string $key, int $default): int
    {
        return (int) ($this->_values[$key] ?? $default);
    }

    public function csv(string $key): array
    {
        $value = $this->optional($key);
        if ($value === null) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
