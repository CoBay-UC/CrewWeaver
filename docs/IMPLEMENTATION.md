# CrewWeaver implementation record

## Architecture

CrewWeaver is a server-rendered MVC application with REST-style JSON operations used by modular browser code. `public/index.php` is the single HTTP entry point. Routes explicitly bind controllers; controllers validate and authorize; repositories/models own persistence; focused services own scheduling, copying, replacement, files, notifications, and authentication; views only render already-prepared data.

The application is intentionally framework-light. This keeps permission and scheduling behavior visible and independently testable while Composer supplies standards-oriented security packages. The dependency rationale is recorded below.

## Decisions

| Date | Decision | Rationale |
|---|---|---|
| 2026-11-25 | Namespace is `CrewWeaver\\`, overriding the example `ProjectManager\\` namespace. | The requested application namespace is explicit. |
| 2026-11-25 | MariaDB 11.4 and PHP 8.3 Apache are the container defaults. | Supported long-lived deployment targets that satisfy the specification. |
| 2026-11-25 | All timestamps are stored as UTC `DATETIME`; calendar-only values are stored as `DATE`. | Prevents timezone drift while retaining date-only intent. |
| 2026-11-25 | Day-zero schedule boundaries use half-open intervals `[start, finish)`. | A two-day task starting Jan 1 finishes at the Jan 3 boundary; inclusive UI displays Jan 1–2. |
| 2026-11-25 | A seeded development identity may be selected only when `APP_ENV=development` and `DEV_AUTH_ENABLED=true`. | Enables isolated development without weakening production OIDC-only authentication. |
| 2026-11-25 | Optimistic concurrency uses integer `version` columns on mutable aggregate roots. | Prevents silent lost updates with simple, portable SQL. |
| 2026-11-25 | MariaDB DDL migrations execute without a surrounding PDO transaction and are recorded only after success. | MariaDB implicitly commits DDL; pretending the migration is transactional would cause invalid commits. Backups remain mandatory before upgrades. |

The Gantt display numbers calendar dates from day 1 at each project's planned start. The scheduling engine continues to calculate half-open boundaries from day 0. Team and sub-team timelines use separate day-count rows for projects with different start dates.

The organization-wide sidebar preference defaults to showing active sub-teams beneath their parent team. Global administrators can change it in Teams & sub-teams. Scoped administrators manage assignments within their existing scope.

Team and sub-team deletion resolves contained active and archived projects through explicit typed consent. The database transaction removes scoped work, writes an audit entry, and queues uploaded files for permanent removal. Files are purged after commit and the queue records any retries.

Calendar Important Dates have an explicit display rule. Team dates appear on all calendars. Sub-team dates appear only on the owning sub-team calendar. Global dates appear on all calendars, and project dates appear on the project calendar and its enclosing calendars. The directory remains available across scopes to approved users.

Calendar List All requests all events for the current scope when selected and groups them by start month and date. It uses the same event visibility rules as the date-range views.

Team calendar agendas prefix sub-team projects with `[Sub-team::Project]`. Direct team projects and sub-team agendas use `[Project]`. Project boundary rows use the project name once. Important Dates keep their independent titles.

Successful workspace API writes advance the revision in migration 005. The active browser checks it every two seconds and also checks when a same-browser tab announces a write or the page regains visibility. Boards, overviews, Gantt, calendars, and archive lists update in place. Task and project draft saves compare changed fields against the latest record. Disjoint edits retry automatically, while an overlapping edit keeps the draft for review. Schedule writes only increment the task or project version when the stored date, duration, or progress actually changes. Direct database writes bypass the API revision.

Authorized project administrators can edit the title and description, archive, restore, or permanently delete a project. Permanent deletion reuses the scope deletion transaction and confirmation phrase, removes dependent work and project dates, and queues file cleanup. Archived projects can be restored or deleted by their administrators. Task editing and contribution require an active project.

The team and sub-team editors search the Bootstrap Icons catalog already bundled into the Docker image. The chosen icon name stays in the existing database field. Dark mode has its own surface, link, muted-text, and calendar colors.

Recent organization activity displays collaboration titles from the audit entry when available and otherwise resolves them from the current record. It shows previous and new task statuses and the prior title on renames. The dashboard receives only display fields, while the administrative audit endpoint continues to provide the full records.

Activity tied to a project displays `[Sub-team]::[Project]` or `[Project]` for direct team projects. Project events use that label as the item name. Tasks and other records show the label below their own title when the project context is available.

Normal installation applies migrations to an empty database. Fixtures remain opt-in for disposable development databases. GitHub Actions publish the existing Docker build to GHCR using `latest-dev` and commit tags from `dev`, and versioned images and a GitHub Release from a merged `dev` to `main` pull request.

Archive counts are shown on team and sub-team overview pages. Their archive links open the corresponding filtered list.

## Dependency rationale and licenses

OIDC token validation allows 60 seconds of clock skew by default after a local Pocket ID sign-in exposed a 40-second offset. The configurable allowance is bounded at 120 seconds and applies to future issue and not-before times. CrewWeaver still rejects tokens at their stated expiration.

- `firebase/php-jwt` 7.x (BSD-3-Clause): verified asymmetric JWT/JWK handling for OIDC ID tokens. Composer's advisory policy rejected the initially considered 6.11 line, so CrewWeaver pins the remediated major line.
- `phpunit/phpunit` (BSD-3-Clause, development): unit and integration test runner.
- Bootstrap 5.3 (MIT), Bootstrap Icons (MIT), SortableJS (MIT), and FullCalendar core (MIT) are installed as local public assets by the image build. Runtime pages do not depend on a CDN.
- The Gantt view is an application-owned accessible CSS/JavaScript timeline, avoiding an additional library and keeping permission-aware editing explicit.

## Deviations and environment limits

- The host did not expose PHP/Composer, so an official portable PHP 8.3.37 and Composer were used only from ignored `storage/cache` for lint, lock generation, audits, and unit tests. Docker/MariaDB remain unavailable; deployment smoke checks are still required.
- No production OIDC provider is available during implementation. Profile/bootstrap mapping and authorization policy are unit-tested; discovery, token exchange, logout, and deactivation must receive an integration smoke test against the deployment provider.
- Binary attachment duplication during project copy is not exposed. Reusing a storage identifier violates uniqueness, while duplicating files safely requires coordinated database/filesystem rollback; this remains gated on MariaDB integration coverage. Link/note research provenance is preserved without false authorship.
- Replacement commit is atomic and covers project/task/milestone assignments and ownership history, but the initial endpoint is limited to global administrators. Enabling team/sub-team administrators requires filtering every preview/commit query to the caller's authorized scope.
- Internal assignment/ownership/comment/mention notifications are persistent. Deadline/overdue generation has no bundled scheduler because the deployment scheduler policy was not specified; a periodic command/delivery adapter remains future work.

