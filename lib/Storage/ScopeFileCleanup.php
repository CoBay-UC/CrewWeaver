<?php

declare(strict_types=1);

namespace CrewWeaver\Storage;

use CrewWeaver\Models\ScopeDeletionRepository;
use Throwable;

class ScopeFileCleanup
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected ScopeDeletionRepository $_deletions;
    protected UploadStorage $_storage;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(ScopeDeletionRepository $deletions, UploadStorage $storage)
    {
        $this->_deletions = $deletions;
        $this->_storage = $storage;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function purge(array $names): int
    {
        $remaining = 0;
        foreach ($names as $name) {
            try {
                $this->_storage->remove($name);
                $this->_deletions->markFileDeleted($name);
            } catch (Throwable $exception) {
                ++$remaining;
                error_log('A queued scope attachment could not be purged: ' . $exception->getMessage());
            }
        }
        return $remaining;
    }
}
