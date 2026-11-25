<?php

declare(strict_types=1);

namespace CrewWeaver\Authorization;

use CrewWeaver\Authentication\AuthenticatedUser;

class VisibilityPolicy
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
    public function canReadOrganization(?AuthenticatedUser $user): bool
    {
        return $user !== null && $user->isApproved();
    }

    public function canReadArchive(?AuthenticatedUser $user): bool
    {
        return $this->canReadOrganization($user);
    }

    public function canReadAdministrativeAccounts(?AuthenticatedUser $user): bool
    {
        return $user !== null && $user->isApproved() && $user->isGlobalAdmin();
    }
}
