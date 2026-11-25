<?php

declare(strict_types=1);

namespace CrewWeaver\Authentication;

class AuthenticatedUser
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected array $_record;
    protected array $_roles;
    protected array $_memberships;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(array $record, array $roles, array $memberships)
    {
        $this->_record = $record;
        $this->_roles = $roles;
        $this->_memberships = $memberships;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function id(): int
    {
        return (int) $this->_record['id'];
    }

    public function record(): array
    {
        return $this->_record;
    }

    public function roles(): array
    {
        return $this->_roles;
    }

    public function memberships(): array
    {
        return $this->_memberships;
    }

    public function isApproved(): bool
    {
        return $this->_record['status'] === 'active';
    }

    public function isGlobalAdmin(): bool
    {
        return count(array_filter($this->_roles, static fn (array $role): bool => $role['role'] === 'global_admin')) > 0;
    }
}
