# Development and task evidence

This repository implements the backend defined in `../test-manager-library-3145555-docs/spec-react-laravel/`.
The numbered tasks and HU/CA identifiers in that specification remain authoritative.

Changes are committed directly to `main` using `T-NN: description`, as requested by the project owner.
Before starting a task, run `git pull --ff-only origin main` with a clean working tree.
Commit only the task's files and executable evidence, then use `git push origin main`.
A commit does not by itself mean that a task is complete; evidence status is recorded separately.

## T-01 — Laravel skeleton and health

Runtime: PHP 8.5, Laravel 13, Composer 2 and PHPUnit 12. Dependency versions are locked in `composer.lock`.
Laravel handles both `/health` (the task's operational path) and `/api/v1/health` (the contract's base path).
Both are public and return exactly `{"status":"ok"}`. No database is needed for health.

Start the local evaluation container with one command from this directory:

```sh
docker compose up --build --detach --wait
```

Run the HTTP-kernel tests and inspect the live response:

```sh
docker compose exec -T api composer test:health
curl --fail http://localhost:8000/health
curl --fail http://localhost:8000/api/v1/health
```

The PHP development server is for local evaluation. Production TLS and the full six-repository composition
remain part of the subsequent deployment work. This command does not create or seed a database.

The feature test dispatches actual requests through Laravel's kernel; changing/removing the health route
must make it fail. Container health also checks the live body, not just whether a process is running.

Execution results are recorded per task in [evidence](evidence/). T-01 through T-16 now have
executed evidence; T-17 and later tasks remain pending in this snapshot. Health alone does not
certify the other stories or the complete application.

## T-02 — Exact schema and transactional Laravel migrations

Set `DATABASE_URL` for the target PostgreSQL 16 database, then run `php artisan library:migrate`
inside the API container. The command takes an advisory transaction lock and runs the Laravel
migrator plus its history in a single transaction. No migration runs during health startup.

The migration repository uses a namespaced JSON comment on the existing `library` schema.
There is no `migrations` table, session table or additional business entity. The schema contains
exactly six tables and thirty columns with no defaults. Only the five documented categories
are seeded. Initial-admin provisioning belongs to T-17. The checks `ck_book_total_positive`
and `ck_user_role` are intentionally left for the original T-18.

Use `library:migrate`, rather than standalone generic migration/reset commands, so DDL and
history commit together. An interrupted operation rolls back; repeated execution is a no-op.
An unknown schema comment or existing tables without migration history require inspection,
and are not overwritten. Destructive reset/rollback of the initial business schema is disabled.

After `docker compose build`, run the integration suite from PowerShell 7:

```powershell
pwsh -File scripts/test-integration.ps1
```

The script generates an ephemeral password without printing it, starts PostgreSQL 16 with
temporary memory storage and no published database port, executes tests against that real
engine, and removes only the named `manager-library-tests` containers on exit. Tests refuse
to mutate a database unless `DB_DATABASE` is exactly `manager_library_test`. Ordinary tests
roll back their transactions; concurrency/live-HTTP tests commit isolated fixtures and remove
their test schema afterward. This isolated composition is not a claim that
the complete API/frontend/infrastructure task T-20 is finished.
