# CrewWeaver

CrewWeaver is a self-hosted, multi-team project management application for collaborative engineering work. It provides organization-wide discovery with scope-safe mutation permissions, task ownership, milestone and dependency scheduling, Kanban, Gantt, calendar, Important Dates, archive/copy, user replacement, notifications, protected files, and immutable audit history.

## Quick start with Docker

Requirements: Docker with Compose and an external standards-compliant OIDC provider (Pocket ID is supported).

```sh
cp .env.example .env
# Edit every password, APP_KEY, URL, and OIDC value.
docker compose build
docker compose up -d db
docker compose run --rm web php bin/migrate.php
docker compose up -d web
```

Open `http://127.0.0.1:20123` with the default Compose port and configure `APP_URL` and the registered OIDC redirect for that origin. Installation runs schema migrations and starts with an empty workspace. Sign in through the configured bootstrap OIDC administrator to create your teams and projects. The health endpoint is `GET /health` and checks database connectivity.

### Optional developer fixtures

Use a separate disposable development database for browser tests and sample data. Set `APP_ENV=development`, configure the explicit development authentication flags, run migrations, then explicitly run the seed command against that database.

```sh
docker compose run --rm web php bin/seed.php
```

The seed command refuses to run outside development.

### GitHub Packages

- Pushes to `dev`, including merged pull requests, publish `ghcr.io/<owner>/crewweaver:latest-dev` and retain a `dev-<commit>` tag for each build.
- Merging a pull request from the repository’s `dev` branch into `main` publishes a versioned image using `v1.0.<workflow run number>` and creates a GitHub Release. The release at the tip of `main` also updates `latest`. Use a pull request for releases.

To install from GitHub Packages, set `CREWWEAVER_IMAGE=ghcr.io/<owner>/crewweaver:<tag>` in `.env`, use `docker compose pull web` in place of the build step, and finish with `docker compose up -d --no-build web`. Use the same migration command for packaged and local builds. For a private package, sign in to GHCR before pulling.

## OIDC configuration

Register `OIDC_REDIRECT_URI` as an exact Authorization Code callback. CrewWeaver uses Authorization Code with PKCE and verifies the signature, discovery issuer, audience, state, nonce, expiry, and subject. Users are keyed by `(issuer, subject)`, never email. Configure the initial administrator with the exact verified `OIDC_BOOTSTRAP_ISSUER` and `OIDC_BOOTSTRAP_SUBJECT`; there is no first-login-wins behavior.

When mapping Pocket ID groups, set `OIDC_SCOPES=openid profile email groups` and use the exact group names returned by the client's OIDC Data Preview. Set `OIDC_LOGOUT_URI` to the page CrewWeaver should open after clearing its local session.

If sign-in returns a generic error, inspect the web service logs with `docker compose logs --tail 100 web`. The `OIDC_CLOCK_SKEW_SECONDS` setting allows for a small difference between the provider and application clocks during ID token validation. It defaults to 60 seconds and accepts 0 through 120 seconds. Expired ID tokens are rejected at their stated expiration time.

Optional comma-separated trusted provider groups can approve identities or grant global administration. Keep mappings empty unless provider group governance is trusted. Profile claims update on login, while locally managed memberships and roles do not.

Key environment settings are documented in `.env.example`:

- application URL/key/timezone and secure session behavior;
- database connection and MariaDB bootstrap passwords;
- OIDC issuer/client/redirect/logout/scopes and claim names;
- optional trusted approval/admin groups and exact bootstrap identity;
- upload size and development-only authentication switches.

Use HTTPS in production and leave `SESSION_SECURE=true`.

## Architecture

The single entry point is `public/index.php`. Explicit route tables are in `app/Routes`. Controllers coordinate validation and authorization, repositories in `app/Models` own SQL, views only present prepared data, middleware protects every route, and focused classes under `lib/` implement OIDC, authorization, storage, validation, and scheduling. JavaScript is split into API, administration, Kanban, task dialogue, Gantt, calendar, and theme modules.

The full conventions are in [AGENTS.md](AGENTS.md), with implementation decisions in [docs/IMPLEMENTATION.md](docs/IMPLEMENTATION.md), schema in [docs/SCHEMA.md](docs/SCHEMA.md), permissions in [docs/AUTHORIZATION.md](docs/AUTHORIZATION.md), scheduling semantics in [docs/SCHEDULING.md](docs/SCHEDULING.md), and operations in [docs/OPERATIONS.md](docs/OPERATIONS.md).

## Database and upgrades

Run versioned migrations explicitly:

```sh
docker compose run --rm web php bin/migrate.php
```

Migrations are forward-only and recorded in `schema_migrations`. Back up the database and `storage/uploads` together before upgrade. Detailed backup/restore steps are in [docs/OPERATIONS.md](docs/OPERATIONS.md).

## Authorization model

All active approved members can read active teams, projects, tasks, schedules, collaboration content, people, and inherited Important Dates across the organization. They can read archived history. Team membership never filters these reads. Pending and inactive accounts cannot read organization data.

Writes remain restricted: participants edit/contribute only where assigned or owning; sub-team admins manage their sub-team; team admins manage their team and descendants; global admins manage everything. Explicit cross-team assignments grant participation, not implicit membership. File downloads pass the same approved-member visibility gate.

## Schedule model

Dates are half-open boundaries: day zero is the project start and a two-day task finishing at the day-2 boundary occupies two calendar dates. Milestone durations sum to task duration. Finish-to-start dependencies support lag, reject cycles, and schedule concurrent branches independently. Manual locks warn and remain unchanged. Actual dates are never rewritten. See [docs/SCHEDULING.md](docs/SCHEDULING.md).

## Development and tests

Inside the application container:

```sh
composer lint
composer test
```

For an integration run, migrate a disposable MariaDB database, explicitly seed development data, exercise an approved member and a cross-team direct URL, then verify that the same member receives `403` for an unauthorized cross-team mutation. Verify mobile widths for dashboard, board, task modal, calendar, administration, archive, and replacement workflows.

The implementation environment’s verified and unavailable checks are recorded in [docs/PROGRESS.md](docs/PROGRESS.md). Do not interpret source presence as a passing deployment test.

## Local dependencies and licenses

The Docker build installs Bootstrap, Bootstrap Icons, SortableJS, and FullCalendar into `public/assets/vendor`; pages make no CDN requests. Composer installs `firebase/php-jwt`. Dependency choices and licenses are listed in [docs/IMPLEMENTATION.md](docs/IMPLEMENTATION.md).

## Production checklist

- Use HTTPS, unique long secrets, a least-privilege database user, and durable volumes.
- Register exact redirect/logout URLs and verify the bootstrap issuer+subject before first login.
- Run migrations, but never development seeds.
- Back up and restore-test database plus uploads.
- Put the web service behind a TLS reverse proxy; expose only `public/`.
- Monitor `/health`, application logs, OIDC failures, audit events, disk use, and database backups.
