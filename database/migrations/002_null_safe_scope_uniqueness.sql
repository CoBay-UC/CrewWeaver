ALTER TABLE memberships
    DROP INDEX uq_memberships_scope,
    ADD COLUMN unique_sub_team_id BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(sub_team_id, 0)) PERSISTENT,
    ADD UNIQUE KEY uq_memberships_scope (user_id, team_id, unique_sub_team_id);

ALTER TABLE scoped_roles
    DROP INDEX uq_scoped_roles,
    ADD COLUMN unique_team_id BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(team_id, 0)) PERSISTENT,
    ADD COLUMN unique_sub_team_id BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(sub_team_id, 0)) PERSISTENT,
    ADD UNIQUE KEY uq_scoped_roles (user_id, role, unique_team_id, unique_sub_team_id);

ALTER TABLE scheduling_configurations
    DROP INDEX uq_schedule_scope,
    ADD COLUMN unique_scope_id BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(scope_id, 0)) PERSISTENT,
    ADD UNIQUE KEY uq_schedule_scope (scope_type, unique_scope_id);
