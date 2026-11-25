# CrewWeaver Engineering Rules

`PROJECT_SPECIFICATION.md` is the authoritative product and implementation specification. Read it in full before changing this repository. If this file and the specification conflict, the specification wins.

## Required architecture

- Target PHP 8.3+, MariaDB, PDO prepared statements, Composer PSR-4 autoloading, Bootstrap 5.3, Bootstrap Icons, and modular vanilla ES6.
- Keep controllers, models, views, middleware, routes, and focused infrastructure under their prescribed directories. SQL belongs only in model/repository or database classes; views contain presentation only.
- Use explicit dependency injection. Do not add service locators, global helper functions, catch-all utility classes, global mutable JavaScript state, or framework code outside `vendor/`.
- Every application-owned PHP file uses `declare(strict_types=1);`, the `CrewWeaver` namespace, typed declarations, and the YYCGuy section order from the specification. Do not use private members; protected members begin with `_`.
- Enforce permissions on the server for every page, API call, mutation, and download. Organization-wide read access for active approved members is mandatory and must never be narrowed by team membership.
- Use transactions for multi-record mutations, prepared statements for all values, normalized relationships, UTC timestamps, optimistic versions, CSRF protection, output escaping, and immutable audit history.
- Keep production data out of source control. Development seeds run only through an explicit command.

## Development workflow

1. Update `docs/PROGRESS.md` when starting or completing a phase.
2. Add a versioned migration for every schema change; never rewrite a migration that may have run elsewhere.
3. Add or update tests with behavior changes, especially authorization and scheduling tests.
4. Run the available lint/test/smoke checks and record real results. Never claim unavailable checks passed.
5. Record material decisions and deviations in `docs/IMPLEMENTATION.md`.
6. Do not leave placeholder controls, mock production data, TODO implementations, or nonfunctional actions.

## Useful references

- [Product specification](PROJECT_SPECIFICATION.md)
- [Implementation and decisions](docs/IMPLEMENTATION.md)
- [Development progress](docs/PROGRESS.md)
- [Authorization matrix](docs/AUTHORIZATION.md)
- [Scheduling conventions](docs/SCHEDULING.md)
- [Schema](docs/SCHEMA.md)

