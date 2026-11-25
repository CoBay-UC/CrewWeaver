CREATE TABLE workspace_revision (
    id TINYINT UNSIGNED PRIMARY KEY,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO workspace_revision (id) VALUES (1);
