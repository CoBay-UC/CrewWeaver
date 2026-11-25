<?php

declare(strict_types=1);

namespace CrewWeaver\Validation;

class Validator
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected array $_data;
    protected array $_errors = [];

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(array $data)
    {
        $this->_data = $data;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _add(string $field, string $message): void
    {
        $this->_errors[$field][] = $message;
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function required(string $field, string $label): self
    {
        $value = $this->_data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->_add($field, $label . ' is required.');
        }
        return $this;
    }

    public function oneOf(string $field, array $allowed, string $label): self
    {
        if (isset($this->_data[$field]) && !in_array($this->_data[$field], $allowed, true)) {
            $this->_add($field, $label . ' is invalid.');
        }
        return $this;
    }

    public function integer(string $field, int $minimum = PHP_INT_MIN, int $maximum = PHP_INT_MAX): self
    {
        if (!isset($this->_data[$field])) {
            return $this;
        }
        $value = filter_var($this->_data[$field], FILTER_VALIDATE_INT);
        if ($value === false || $value < $minimum || $value > $maximum) {
            $this->_add($field, 'Must be an integer between ' . $minimum . ' and ' . $maximum . '.');
        }
        return $this;
    }

    public function date(string $field): self
    {
        if (!isset($this->_data[$field]) || $this->_data[$field] === '') {
            return $this;
        }
        $value = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->_data[$field]);
        if ($value === false || $value->format('Y-m-d') !== $this->_data[$field]) {
            $this->_add($field, 'Must be a date in YYYY-MM-DD format.');
        }
        return $this;
    }

    public function errors(): array
    {
        return $this->_errors;
    }

    public function valid(): bool
    {
        return $this->_errors === [];
    }
}
