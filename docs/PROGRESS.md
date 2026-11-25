# Development progress

Last updated: 2026-11-25

The live-deployment follow-up covers task loading, the three schedule views, scoped team administration, membership management, and organization navigation.

## Phase status

| Phase | Status | Delivered / outstanding |
|---|---|---|
| 1. Foundation | Complete; integration pending | MVC, explicit routes/DI, environment configuration, migrations, Docker, local assets, OIDC PKCE/JWK verification, secure sessions, CSRF, rate limiting, authorization, and responsive layout. Live provider and container checks require deployment infrastructure. |
| 2. Administration | Complete | User approval/deactivation, teams, sub-teams, memberships, scoped roles, global-admin restrictions, and mandatory organization-wide approved-member reads. |
| 3. Domain | Complete | Project/task persistence, assignments, ownership history, milestones/members, dependencies, comments/mentions, research, and protected attachments. |
| 4. Scheduling | Complete | Day-zero boundaries, milestone-derived durations, working calendars/holidays, dependencies/lag/cycle rejection, concurrent branches, locks, previews, application, warnings, and focused tests. |
| 5. Visualization | Complete | Project/sub-team/team Kanban with swimlanes and rollback, hierarchical Gantt with Today/zoom, FullCalendar views, inherited dates, and countdown cards. |
| 6. Advanced management | Complete with limits below | Archive/restore, structural project copy/remapping, atomic user replacement, notifications, audit, dashboard, and activity views. |
| 7. Refinement | Source-complete; deployment verification pending | Mobile layouts, keyboard alternatives, focus/contrast/reduced motion, security headers, pagination/indexes, documentation, lint, unit tests, and dependency audits. MariaDB/Docker/browser smoke tests remain unavailable on this host. |

## Verification log

| Date | Check | Result |
|---|---|---|
| 2026-11-25 | Repository inventory | Only `PROJECT_SPECIFICATION.md` existed; no unrelated code to preserve. |
| 2026-11-25 | Host tool inventory | Node 24.19.0 and npm 11.17.0 available. PHP, Composer, Docker, and Git repository metadata unavailable. |
| 2026-11-25 | Git repository | Initialized `main` repository in the supplied workspace. |
| 2026-11-25 | Frontend manifests | `npm ci --dry-run` succeeded; `npm audit --omit=dev` found 0 vulnerabilities. |
| 2026-11-25 | JavaScript syntax | All 7 application modules passed `node --check`. |
| 2026-11-25 | PHP runtime | Official portable PHP 8.3.37 used from ignored `storage/cache`; 78 PHP files passed native `php -l`. |
| 2026-11-25 | Composer | Strict manifest validation passed. Lock generated. Security audit found no advisories. The advisory-blocked JWT 6.11 line was replaced with remediated 7.2.2. |
| 2026-11-25 | PHPUnit | 14 tests / 33 assertions passed on PHP 8.3.37, covering cross-team visibility/write denial, scoped inheritance, OIDC profile/bootstrap mapping, milestones, working days/holiday, concurrency, cycles, locks, lag graph behavior, and downstream shifts. |
| 2026-11-25 | Static route audit | All 65 routed controller handlers resolve. Canonical six task statuses are present in the schema. |
| 2026-11-25 | Pocket ID callback diagnosis | Live Docker logs showed ID tokens issued 40 seconds ahead of the application clock. Added bounded clock tolerance and signed-token regression tests. PHPUnit passed 20 tests and 57 assertions. |
| 2026-11-25 | Local Docker smoke test | Rebuilt and recreated the web service. The running MariaDB database reported both migrations recorded. Health returned HTTP 200 and login redirected to Pocket ID with the registered loopback callback, S256 PKCE, and the groups scope. A fresh authenticated callback requires the user's Pocket ID session. |
| 2026-11-25 | Views and administration | Fixed native MariaDB parameter binding in task detail and search. Restored FullCalendar. Added shared scope navigation, aligned Kanban columns, scrollable dated Gantt rows, and scoped administration forms. |
| 2026-11-25 | Seeded browser and database smoke tests | Passed a fresh three-migration install, task modal and research, Kanban header alignment and search, frozen Gantt labels, Calendar events and task navigation, desktop and mobile sidebar behavior, team and sub-team CRUD, membership persistence and removal, and the sidebar setting. |
| 2026-11-25 | Local upgrade verification | Applied migration 003 to the existing database, rebuilt the web container, and verified health, live task lookup, a live calendar range, the navigation preference, and the unauthenticated task API's HTTP 401 response. PHPUnit passed 21 tests with 64 assertions, and PHP and JavaScript lint passed. |
| 2026-11-25 | Dialogs and permanent scope deletion | The desktop and mobile browser suite passed project creation, task creation, copying, archive and restore, membership confirmation, rejected deletion without the exact phrase, and deletion of active and archived disposable projects with file cleanup. PHPUnit passed 21 tests with 64 assertions, and PHP and JavaScript lint passed. |
| 2026-11-25 | Sub-team projects and Important Dates | Browser checks passed for sub-team project creation, archive visibility, date creation/editing/deletion, and calendar rules across six calendar feeds. Verified that the same team date appears once per calendar and that a sub-team date appears only on its own calendar. PHP and JavaScript lint passed. |
| 2026-11-25 | Calendar List All and progress slider | Desktop and mobile browser tests passed for month grouping, full date labels, calendar view switching, opening events, live slider percentages, and saved progress. PHP and JavaScript lint passed, along with 22 PHPUnit tests and 69 assertions. |
| 2026-11-25 | Panel shape and theme controls | Checked halved panel radii and the 10 px chamfer in light and dark mode. Desktop and mobile browser checks passed for instant toggling, preference persistence, System mode, and standard button shape. PHP and JavaScript lint and all 22 PHPUnit tests passed. |
| 2026-11-25 | Agenda labels and collaboration refresh | The isolated browser suite passed Team and sub-team calendar labels, in-place board changes across two browser sessions, disjoint draft edits, and review of overlapping edits. |
| 2026-11-25 | Project and scope refinement | Browser checks passed for renaming, archive and restore, permanent deletion of active and archived disposable projects, read-only archived tasks, searchable team and sub-team icons, and dark card text contrast. Checked the dark calendar and board visually. PHP lint, JavaScript syntax checks, and 22 PHPUnit tests with 69 assertions passed. |
| 2026-11-25 | Clean installation and GitHub Packages | Verified zero users, teams, projects, tasks, and Important Dates after a fresh five-migration install. The development command seeded only the separate browser-test database. Both GitHub workflows passed YAML, trigger, permission, and Bash syntax checks locally. Registry publication requires a GitHub workflow run. |
| 2026-11-25 | Local upgrade | Applied migration 005 and recreated the web service on port 20123. Health returned HTTP 200, the unauthenticated revision endpoint returned HTTP 401, and login redirected to OIDC with S256 PKCE. The seeded database showed a recalculated project finish and progress while untouched task versions stayed at their original values. Removed the disposable Compose test stack after verification. |
| 2026-11-25 | Dashboard activity titles | The seeded browser suite verified the affected task name and status transition, and verified that archived or deleted project and Important Date entries kept their titles. PHP and JavaScript syntax checks passed, along with 22 PHPUnit tests and 69 assertions. |
| 2026-11-25 | Dashboard project context | The browser suite verified `[Sub-team]::[Project]` on project and task activity and `[Project]` for a direct team project. PHP and JavaScript syntax checks passed, alongside 22 PHPUnit tests and 69 assertions. |

## Known issues

- The local Docker deployment passed the health and login redirect checks above. Broader persistence and rollback coverage remains pending.
- This UI smoke suite uses an isolated development identity. The previous live deployment logs also show authenticated requests to the workspace after the Pocket ID clock fix.
- Project copy preserves task/milestone/dependency/member/research-note structure, but binary attachment copying is deliberately not exposed until storage-level copy/rollback receives a MariaDB integration test.
- User replacement is fully available to global administrators; scoped-admin selected-project replacement needs a scope-filtered UI/API pass before enabling it safely.
- Notification persistence and comment/mention/ownership dispatch are active. Periodic deadline/overdue generation and future email/webhook delivery are not yet scheduled.
- Drag/drop concurrency against MariaDB and the complete real OIDC and file-download direct-URL flows remain broader acceptance checks.

