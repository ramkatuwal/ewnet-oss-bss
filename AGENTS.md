# AGENTS.md

## What is this

EWNET OSS/BSS — a telecom operations support system (OSS/BSS). Laravel 13 + PHP 8.4 backend, React 18 + TypeScript + Vite frontend, PostgreSQL 17 (PostGIS), Redis, Docker Compose deployment.

## Quick commands

### PHP (backend)
```bash
# Run tests (clears config first — this is required).
# SAFETY: phpunit.xml pins DB_DATABASE=ewnet_test and CACHE_STORE=array, so it can
# never hit the production `ewnet` DB. On the host (not in Docker) the `.env.testing`
# DB_HOST=postgres doesn't resolve, so pass DB_HOST=127.0.0.1.
composer test                            # or: ./vendor/bin/phpunit
DB_HOST=127.0.0.1 ./vendor/bin/phpunit   # from the host

# Run a single test class
./vendor/bin/phpunit tests/Feature/Integrations/ImportPipelineTest.php

# Run a single test method
./vendor/bin/phpunit tests/Feature/Integrations/ImportPipelineTest.php --filter test_uisp_preview_returns_sites_and_devices

# WARNING: prefer `./vendor/bin/phpunit` over `php artisan test`. `php artisan test`
# spawns PHPUnit with the parent process env (loaded from `.env`), so without the
# phpunit.xml DB_DATABASE pin it can run RefreshDatabase tests against production.

# Lint (Laravel Pint, defaults — no pint.json)
./vendor/bin/pint

# Artisan (inside container)
docker compose exec app php artisan <command>
```

### JS (frontend)
```bash
npm run dev          # Vite dev server
npm run build        # Production build (public/build/)
npm run typecheck    # tsc --noEmit — strict mode, no unused locals/params
```

### Docker
```bash
docker compose build    # includes frontend asset build
docker compose up -d    # starts app, horizon, postgres, redis, nginx
```

## Project structure

```
app/
  Http/Controllers/Api/V1/   — all API controllers (26)
  Http/Requests/Api/V1/      — form request validation classes
  Http/Resources/V1/         — API resource transformers
  Models/                    — Eloquent models (24)
  Services/                  — business logic layer
  Integrations/Providers/    — LibreNMS + UISP integration clients
  Jobs/                      — queued imports and sync
  Policies/                  — authorization (Spatie permissions)

resources/js/
  main.tsx                   — React entrypoint
  app/App.tsx                — root component
  routes/index.tsx           — all frontend routes (React Router)
  api/client.ts              — Axios instance with CSRF + auth interceptors
  stores/                    — Zustand stores (auth, config, theme)
  features/                  — feature modules (pages + components per domain)
  components/                — shared components
  layouts/                   — AuthLayout, MainLayout
  types/                     — TypeScript type definitions

routes/
  api.php                    — all API routes under /v1, auth:sanctum protected
  web.php                    — SPA catch-all (serves blade app template)
```

## Architecture notes

- **SPA auth flow**: Sanctum cookie-based. Frontend hits `GET /sanctum/csrf-cookie` first, then authenticates. 401 responses auto-redirect to `/login`.
- **API versioning**: All routes prefixed `/v1`. Only `/v1/branding` is public.
- **Pagination flattening**: `api/client.ts` response interceptor flattens Laravel's `meta` into top-level fields for frontend `PaginatedResponse<T>` compatibility.
- **Vite path alias**: `@/` resolves to `resources/js/` (configured in both `vite.config.ts` and `tsconfig.json`).
- **Roles/Permissions**: Spatie laravel-permission. Roles seeded in `database/seeders/RolesAndPermissionsSeeder.php`.
- **Management scopes**: Users can be scoped to specific companies/branches. See `UserManagementScope` model and `ChecksManagementScope` policy trait.
- **Queued work**: Horizon processes `ProcessAssetImport`, `ProcessSiteImport`, `RunIntegrationSync` jobs.
- **Database**: PostgreSQL with PostGIS. Geo queries possible on site/location fields. Test DB is `ewnet_test`.

## Conventions

- **Backend**: PSR-4 under `App\` namespace. All API controllers in `Api/V1/`. Requests/Resources follow the same versioned path.
- **Frontend**: Feature-based module structure under `resources/js/features/<domain>/`. Each feature has `pages/` and optional `components/`. Shared code in `components/` and `hooks/`.
- **Indentation**: 4 spaces (PHP, TS, TSX). 2 spaces for YAML (4 for docker-compose).
- **TypeScript**: Strict mode enabled — `noUnusedLocals`, `noUnusedParameters` enforced.

## Gotchas

- `composer test` runs `config:clear` before tests — this is intentional; don't skip it or test config may be stale.
- Tests require a running Postgres instance (connection to `127.0.0.1:5432`). The `phpunit.xml` sets `APP_ENV=testing`, pins `DB_DATABASE=ewnet_test`, and `CACHE_STORE=array`, so tests are isolated from the production `ewnet` DB and non-Redis.
- Frontend `node_modules` and `vendor` are volume-mounted in Docker (not copied), so host installs are available inside containers.
- Stray `.bak` files exist in `app/Services/` and `app/Integrations/` — ignore them.
- Duplicate migrations exist (two `create_permission_tables`, two `add_foreign_key_manager_id`). These are legacy artifacts.
- `_ide_helper.php` is tracked at root — it's a large generated file, don't edit manually.
- No lint CI or pre-commit hooks configured. Pint and `typecheck` run manually.
- No ESLint or Prettier configured — frontend formatting relies on editor conventions.
