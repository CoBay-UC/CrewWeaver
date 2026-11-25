CREATE TABLE pending_attachment_deletions (
    storage_name CHAR(64) PRIMARY KEY,
    queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
