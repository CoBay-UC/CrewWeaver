CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    oidc_issuer VARCHAR(512) NOT NULL,
    oidc_subject VARCHAR(255) NOT NULL,
    email VARCHAR(320) NULL,
    first_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    display_name VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(1024) NULL,
    status ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending',
    approved_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_identity (oidc_issuer(255), oidc_subject),
    KEY idx_users_status_name (status, display_name),
    KEY idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_preferences (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    theme ENUM('system','light','dark') NOT NULL DEFAULT 'system',
    timezone VARCHAR(80) NOT NULL DEFAULT 'UTC',
    gantt_zoom ENUM('day','week','month','quarter') NOT NULL DEFAULT 'week',
    notification_assignments TINYINT(1) NOT NULL DEFAULT 1,
    notification_mentions TINYINT(1) NOT NULL DEFAULT 1,
    notification_comments TINYINT(1) NOT NULL DEFAULT 1,
    notification_deadlines TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    description TEXT NULL,
    colour CHAR(7) NOT NULL DEFAULT '#4f46e5',
    icon VARCHAR(80) NOT NULL DEFAULT 'people',
    logo_path VARCHAR(512) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teams_slug (slug),
    KEY idx_teams_active_order (is_active, display_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sub_teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    description TEXT NULL,
    colour CHAR(7) NOT NULL DEFAULT '#0ea5e9',
    icon VARCHAR(80) NOT NULL DEFAULT 'diagram-3',
    logo_path VARCHAR(512) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sub_teams_team_slug (team_id, slug),
    KEY idx_sub_teams_active_order (team_id, is_active, display_order, name),
    CONSTRAINT fk_sub_teams_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE memberships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    team_id BIGINT UNSIGNED NOT NULL,
    sub_team_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_memberships_scope (user_id, team_id, sub_team_id),
    KEY idx_memberships_team (team_id, sub_team_id, user_id),
    CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_memberships_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE RESTRICT,
    CONSTRAINT fk_memberships_sub_team FOREIGN KEY (sub_team_id) REFERENCES sub_teams(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scoped_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('global_admin','team_admin','sub_team_admin') NOT NULL,
    team_id BIGINT UNSIGNED NULL,
    sub_team_id BIGINT UNSIGNED NULL,
    granted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scoped_roles (user_id, role, team_id, sub_team_id),
    KEY idx_roles_scope (role, team_id, sub_team_id),
    CONSTRAINT fk_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_roles_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE RESTRICT,
    CONSTRAINT fk_roles_sub_team FOREIGN KEY (sub_team_id) REFERENCES sub_teams(id) ON DELETE RESTRICT,
    CONSTRAINT fk_roles_granter FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id BIGINT UNSIGNED NOT NULL,
    sub_team_id BIGINT UNSIGNED NULL,
    title VARCHAR(240) NOT NULL,
    description MEDIUMTEXT NULL,
    lead_user_id BIGINT UNSIGNED NULL,
    priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    colour CHAR(7) NOT NULL DEFAULT '#4f46e5',
    status ENUM('Planned','Active','On Hold','Completed','Cancelled') NOT NULL DEFAULT 'Planned',
    scheduling_mode ENUM('calendar','working') NOT NULL DEFAULT 'calendar',
    planned_start DATE NULL,
    calculated_completion DATE NULL,
    target_completion DATE NULL,
    actual_start DATE NULL,
    actual_completion DATE NULL,
    progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    copied_from_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_projects_active_scope (is_archived, team_id, sub_team_id, status),
    KEY idx_projects_dates (planned_start, calculated_completion),
    CONSTRAINT fk_projects_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE RESTRICT,
    CONSTRAINT fk_projects_sub_team FOREIGN KEY (sub_team_id) REFERENCES sub_teams(id) ON DELETE RESTRICT,
    CONSTRAINT fk_projects_lead FOREIGN KEY (lead_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_archiver FOREIGN KEY (archived_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_origin FOREIGN KEY (copied_from_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_members (
    project_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, user_id),
    KEY idx_project_members_user (user_id, project_id),
    CONSTRAINT fk_project_members_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_project_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_project_members_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    colour CHAR(7) NOT NULL DEFAULT '#64748b',
    UNIQUE KEY uq_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_tags (
    project_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (project_id, tag_id),
    CONSTRAINT fk_project_tags_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_project_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(240) NOT NULL,
    description MEDIUMTEXT NULL,
    status ENUM('Not Started','Researching','In-Progress','Needs Outside Input','Needs Review','Done') NOT NULL DEFAULT 'Not Started',
    priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    owner_user_id BIGINT UNSIGNED NULL,
    scheduling_mode ENUM('automatic','manual') NOT NULL DEFAULT 'automatic',
    dates_locked TINYINT(1) NOT NULL DEFAULT 0,
    planned_start DATE NULL,
    calculated_finish DATE NULL,
    manual_due_date DATE NULL,
    actual_start DATE NULL,
    actual_completion DATE NULL,
    estimated_duration_days INT UNSIGNED NOT NULL DEFAULT 1,
    progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
    kanban_order DECIMAL(18,6) NOT NULL DEFAULT 1000,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tasks_board (project_id, status, kanban_order),
    KEY idx_tasks_owner_status (owner_user_id, status),
    KEY idx_tasks_dates (planned_start, calculated_finish, manual_due_date),
    CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tasks_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_members (
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id),
    KEY idx_task_members_user (user_id, task_id),
    CONSTRAINT fk_task_members_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_task_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_task_members_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_tags (
    task_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (task_id, tag_id),
    CONSTRAINT fk_task_tags_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_task_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ownership_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NOT NULL,
    previous_owner_id BIGINT UNSIGNED NULL,
    new_owner_id BIGINT UNSIGNED NULL,
    changed_by BIGINT UNSIGNED NULL,
    reason VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ownership_task_created (task_id, created_at),
    CONSTRAINT fk_ownership_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_ownership_previous FOREIGN KEY (previous_owner_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ownership_new FOREIGN KEY (new_owner_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ownership_actor FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE milestones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(240) NOT NULL,
    description TEXT NULL,
    duration_days INT UNSIGNED NOT NULL DEFAULT 0,
    cumulative_offset_days INT UNSIGNED NOT NULL DEFAULT 0,
    calculated_date DATE NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    actual_completion DATE NULL,
    notes TEXT NULL,
    display_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_milestones_task_order (task_id, display_order),
    CONSTRAINT fk_milestones_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE milestone_members (
    milestone_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (milestone_id, user_id),
    CONSTRAINT fk_milestone_members_milestone FOREIGN KEY (milestone_id) REFERENCES milestones(id) ON DELETE CASCADE,
    CONSTRAINT fk_milestone_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_dependencies (
    predecessor_task_id BIGINT UNSIGNED NOT NULL,
    successor_task_id BIGINT UNSIGNED NOT NULL,
    lag_days INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (predecessor_task_id, successor_task_id),
    KEY idx_dependencies_successor (successor_task_id),
    CONSTRAINT fk_dependencies_predecessor FOREIGN KEY (predecessor_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_dependencies_successor FOREIGN KEY (successor_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT chk_dependencies_distinct CHECK (predecessor_task_id <> successor_task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NULL,
    body MEDIUMTEXT NOT NULL,
    edited_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_comments_task_created (task_id, created_at),
    CONSTRAINT fk_comments_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comment_mentions (
    comment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (comment_id, user_id),
    CONSTRAINT fk_comment_mentions_comment FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_mentions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NULL,
    type ENUM('link','note','image','file') NOT NULL,
    title VARCHAR(240) NOT NULL,
    url VARCHAR(2048) NULL,
    content MEDIUMTEXT NULL,
    description TEXT NULL,
    display_order INT NOT NULL DEFAULT 0,
    source_author_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_research_task_order (task_id, display_order),
    CONSTRAINT fk_research_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_research_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_research_source_author FOREIGN KEY (source_author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NULL,
    comment_id BIGINT UNSIGNED NULL,
    research_item_id BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    storage_name CHAR(64) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(160) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attachments_storage (storage_name),
    KEY idx_attachments_task (task_id),
    CONSTRAINT fk_attachments_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_attachments_comment FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_attachments_research FOREIGN KEY (research_item_id) REFERENCES research_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_attachments_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE important_dates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(240) NOT NULL,
    description TEXT NULL,
    event_date DATE NOT NULL,
    event_time TIME NULL,
    end_date DATE NULL,
    is_all_day TINYINT(1) NOT NULL DEFAULT 1,
    colour CHAR(7) NOT NULL DEFAULT '#f59e0b',
    icon VARCHAR(80) NOT NULL DEFAULT 'calendar-event',
    priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    scope_type ENUM('global','team','sub_team','project') NOT NULL,
    team_id BIGINT UNSIGNED NULL,
    sub_team_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    show_countdown TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_important_dates_scope_date (scope_type, team_id, sub_team_id, project_id, event_date),
    CONSTRAINT fk_dates_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_dates_sub_team FOREIGN KEY (sub_team_id) REFERENCES sub_teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_dates_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_dates_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scheduling_configurations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope_type ENUM('global','team','sub_team','project') NOT NULL,
    scope_id BIGINT UNSIGNED NULL,
    timezone VARCHAR(80) NOT NULL DEFAULT 'UTC',
    monday TINYINT(1) NOT NULL DEFAULT 1,
    tuesday TINYINT(1) NOT NULL DEFAULT 1,
    wednesday TINYINT(1) NOT NULL DEFAULT 1,
    thursday TINYINT(1) NOT NULL DEFAULT 1,
    friday TINYINT(1) NOT NULL DEFAULT 1,
    saturday TINYINT(1) NOT NULL DEFAULT 0,
    sunday TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_schedule_scope (scope_type, scope_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE holidays (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scheduling_configuration_id BIGINT UNSIGNED NOT NULL,
    holiday_date DATE NOT NULL,
    name VARCHAR(160) NOT NULL,
    UNIQUE KEY uq_holidays_config_date (scheduling_configuration_id, holiday_date),
    CONSTRAINT fk_holidays_config FOREIGN KEY (scheduling_configuration_id) REFERENCES scheduling_configurations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(80) NOT NULL,
    title VARCHAR(240) NOT NULL,
    body TEXT NOT NULL,
    resource_type VARCHAR(80) NULL,
    resource_id BIGINT UNSIGNED NULL,
    resource_url VARCHAR(1024) NULL,
    deduplication_key VARCHAR(255) NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notifications_dedup (user_id, deduplication_key),
    KEY idx_notifications_unread (user_id, read_at, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(80) NOT NULL,
    resource_id BIGINT UNSIGNED NULL,
    team_id BIGINT UNSIGNED NULL,
    sub_team_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    before_values JSON NULL,
    after_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_resource (resource_type, resource_id, created_at),
    KEY idx_activity_scope (team_id, sub_team_id, project_id, created_at),
    KEY idx_activity_actor (actor_id, created_at),
    CONSTRAINT fk_activity_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_activity_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_activity_sub_team FOREIGN KEY (sub_team_id) REFERENCES sub_teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_activity_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE replacement_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_user_id BIGINT UNSIGNED NOT NULL,
    replacement_user_id BIGINT UNSIGNED NOT NULL,
    initiated_by BIGINT UNSIGNED NOT NULL,
    scope_description TEXT NOT NULL,
    affected_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_replacement_source FOREIGN KEY (source_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_replacement_target FOREIGN KEY (replacement_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_replacement_actor FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    bucket_key CHAR(64) PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    KEY idx_rate_limits_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
