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

Execution results are recorded in [evidence/T-01.md](evidence/T-01.md). T-02 through T-23 remain pending;
health success does not certify authentication, persistence, other stories or the complete application.
