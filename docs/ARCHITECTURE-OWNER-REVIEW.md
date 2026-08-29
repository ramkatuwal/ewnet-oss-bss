# Architecture / Owner Review — Technical Baseline

> **Document Purpose:** This is the formal technical baseline for EWNET OSS/BSS Architecture/Owner Review.  
> **Status:** CURRENT / VERIFIED on `develop` branch  
> **Last Verified:** 2026-08-29  
> **Baseline Commit:** `02d572add31ce73c83d8c88c3b402b562320a5b5`

This document describes the **actual current system** as verified. It is not a marketing README. Nothing is invented. All commands, versions, ports, and capabilities reflect the real repository and environment.

---

## 1. Document Scope

| Category | Status |
|----------|--------|
| Current implementation baseline | ✅ VERIFIED |
| Environment & technology stack | ✅ VERIFIED |
| Docker architecture | ✅ VERIFIED |
| Backend architecture | ✅ VERIFIED |
| Frontend architecture | ✅ VERIFIED |
| Database architecture | ✅ VERIFIED |
| Authentication/security | ✅ VERIFIED |
| Build process | ✅ VERIFIED |
| Testing process | ✅ VERIFIED |
| Deployment process | ✅ VERIFIED |
| Known limitations | ✅ DOCUMENTED |
| Architecture review checklist | ✅ INCLUDED |
| Future work | ✅ SEPARATED |

### Legend

- **CURRENT / VERIFIED** — Implemented and confirmed working
- **EXISTING BUT NEEDS REVIEW** — Present but has known gaps or recommendations
- **FUTURE / RECOMMENDED** — Not yet implemented; requires architectural decision before work begins

---

## 2. Git Baseline

| Field | Value |
|-------|-------|
| Repository | `git@github.com:ramkatuwal/ewnet-oss-bss.git` |
| Branch | `develop` |
| Commit SHA | `02d572add31ce73c83d8c88c3b402b562320a5b5` |
| Commit Message | `fix(TASK-030): correct dockerfile build context and asset copying` |
| Working Tree | Clean |
| Deployment Branch | `develop` |

Verification commands:

```bash
git status              # nothing to commit, working tree clean
git branch --show-current  # develop
git log -1 --oneline    # 02d572a fix(TASK-030): correct dockerfile build context and asset copying
git remote -v           # origin git@github.com:ramkatuwal/ewnet-oss-bss.git
3. Server Environment
Host
Component
Value
OS
Ubuntu 24.04.4 LTS (Noble Numbat)
Kernel
6.8.0-138-generic
Architecture
x86_64
CPU
Intel Xeon Bronze 3104 @ 1.70GHz, 6 cores, 1 socket
RAM
15.57 GiB total, ~14 GiB available
Swap
4.0 GiB
Disk
/dev/sda2 48G total, 34G used (74%), 13G available
Inodes
68% used
Virtualization
VMware
Timezone
UTC
NTP
Active, synchronized
Software Versions
Component
Version
Context
Docker Engine
29.7.2
Runtime
Docker Compose
v5.5.0
Orchestration
PHP
8.4.25
Runtime (inside container)
Laravel
13.26.1
Backend framework
Composer
2.8.12
PHP dependency manager
PostgreSQL
17.5
Database
PostGIS
3.5
Spatial extension
Redis
7.4.11
Cache / Queue / Session
Nginx
1.26.3
Reverse proxy / static assets
Node.js
v22.23.2
BUILD ONLY — not in production runtime
npm
10.9.8
BUILD ONLY — not in production runtime
TypeScript
5.5.4
BUILD ONLY
Vite
8.x
BUILD ONLY
⚠️ Important: Node.js and npm exist only on the host/build environment and inside the Docker builder stage. They are NOT present in the production ewnet-app runtime image. Never run npm commands inside the production container.
4. Application Architecture
Browser
   │
   ▼
Nginx (ewnet-web)
   │
   ├── Static assets (/build/*) → served directly
   │
   └── PHP requests → fastcgi_pass app:9000
                          │
                          ▼
                   Laravel / PHP-FPM (ewnet-app)
                          │
               ┌──────────┼──────────┐
               ▼          ▼          ▼
          PostgreSQL   Redis     Horizon
         (ewnet-postgres) (ewnet-redis) (ewnet-horizon)
Component Roles
Component
Role
Nginx
TLS termination, HTTP→HTTPS redirect, static asset serving, reverse proxy to PHP-FPM
Laravel / PHP-FPM
API server, SPA fallback, business logic, authentication, authorization
PostgreSQL + PostGIS
Primary data store with spatial capabilities
Redis
Cache, session storage, queue backend, Horizon state
Horizon
Queue worker supervisor and monitoring dashboard
API Structure
Prefix: /api/v1/
Public endpoints: /api/v1/branding (no auth required)
Protected endpoints: All others require Sanctum session authentication
Route file: routes/api.php
Middleware Stack
auth:sanctum — Session-based authentication for protected routes
can:* — Policy-based authorization via Spatie Permissions
Standard Laravel middleware (CSRF, session, etc.)
Authorization
Package: spatie/laravel-permission
Policies: Located in app/Policies/
Permissions: Seeded via database/seeders/SystemPermissionSeeder.php and OrganizationPermissionsSeeder.php
Roles: Super Admin, regional/branch scoped roles
Management Scopes: Organization-scoped data isolation via UserManagementScope model
Queues
Driver: Redis
Supervisor: Laravel Horizon
Configuration: config/horizon.php, config/queue.php
Worker config: supervisor-1, redis connection, default queue, auto-balancing, max 10 processes
Configuration
Environment: production
Debug: OFF
Cache driver: Redis
Session driver: Redis
Queue driver: Redis
Config/Routes cache: NOT CACHED (views cached)
5. Docker Architecture
Multi-Stage Build
Stage 1: node:20-alpine (frontend-builder)
    │
    ├── COPY . .
    ├── npm ci --legacy-peer-deps
    ├── npm run build
    └── Output: /app/public/build/
              │
              ▼
Stage 2: php:8.4-fpm-alpine (runtime)
    │
    ├── System deps (postgresql-dev, libzip, gd, redis, supervisor)
    ├── PHP extensions (pdo_pgsql, pgsql, zip, gd, bcmath, opcache, pcntl, redis)
    ├── Composer 2.8
    ├── COPY . /var/www/html
    ├── COPY --from=frontend-builder /app/public/build ./public/build
    ├── composer install --no-interaction --optimize-autoloader --no-dev
    ├── Permission setup (www-data ownership)
    └── CMD ["php-fpm"]
Why Node/npm Are NOT in Production
The multi-stage Dockerfile ensures:
Frontend compilation happens in Stage 1 (node:20-alpine)
Only compiled assets (public/build/) are copied to Stage 2
Stage 2 uses php:8.4-fpm-alpine which has no Node.js
composer install --no-dev excludes development packages
The final ewnet-app image contains only PHP runtime + compiled frontend
Verified: which node and which npm return empty inside the production container.
Containers
Container
Image
Role
Ports
Health Check
ewnet-app
ewnet-app (custom)
Laravel PHP-FPM
9000 (internal)
None
ewnet-horizon
ewnet-app (custom)
Queue workers
None
None
ewnet-postgres
postgis/postgis:17-3.5
Database
127.0.0.1:5432
pg_isready ✅
ewnet-redis
redis:7.4-alpine
Cache/Queue/Session
127.0.0.1:6379
redis-cli ping ✅
ewnet-web
nginx:1.26-alpine
Reverse proxy / TLS
0.0.0.0:80,443
None
Networking
Network: ewnet-network (custom bridge)
PostgreSQL/Redis: Bound to 127.0.0.1 only — not publicly accessible
PHP-FPM: Internal port 9000, not published to host
Nginx: Only public-facing service (ports 80/443)
Volumes
Volume
Mount Point
Purpose
ewnet_postgres_data
/var/lib/postgresql/data
Persistent database storage
ewnet_redis_data
/data
Redis persistence (RDB)
Bind mount ./:/var/www/html
/var/www/html
Application source code (app, horizon, web)
Anonymous volume
/var/www/html/vendor
Vendor directory override (app, horizon)
Anonymous volume
/var/www/html/node_modules
Node modules override (app only)
Read-only bind
/etc/nginx/conf.d/default.conf
Nginx configuration
Read-only bind
/etc/letsencrypt
TLS certificates
⚠️ Known Issue: Anonymous vendor/node_modules volumes can persist stale dependency state across rebuilds. See Section 15 (Known Limitations).
6. Backend Development Guide
Initial Setup
cd /opt/misp
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
Development Flow
Create migration:
   docker compose exec app php artisan make:migration create_<table>_table
Edit file in database/migrations/.
Create/update model:
bash
   docker compose exec app php artisan make:model <ModelName>
Edit file in app/Models/. Define relationships, casts, fillable attributes.
Create request validation
   docker compose exec app php artisan make:request Api/V1/<Name>Request
Edit file in app/Http/Requests/Api/V1/. Define rules() and authorize().
Create service (if complex logic):
Create manually in app/Services/. Inject via constructor. No artisan generator exists for services.
Create controller:
bash
   docker compose exec app php artisan make:controller Api/V1/<Name>Controller

Edit file in app/Http/Controllers/Api/V1/. Use FormRequest for validation. Return API Resources.
Define API route:
Edit routes/api.php. Add route within appropriate middleware group:
Public: outside auth middleware
Protected: inside Route::middleware('auth:sanctum') group
Apply authorization:
Create policy: docker compose exec app php artisan make:policy <Name>Policy
Register in app/Providers/AuthServiceProvider.php
Use $this->authorize() in controllers or can: middleware in routes
Add tests:
Create test in tests/Feature/ or tests/Unit/. Extend TestCase. Use RefreshDatabase trait.
Run targeted tests:
bash

   docker compose exec app composer install --dev   # temporary, for PHPUnit access
   docker compose exec app vendor/bin/phpunit --filter=<TestName>
Run complete test suite:

docker compose exec app vendor/bin/phpunit --no-coverage
Expected: 183 tests, 411 assertions, 0 failures.
Verify API manually:
bash
curl -sk https://domain/api/v1/branding    # public, expect 200
curl -sk https://domain/api/v1/system/info  # protected, expect 401 without auth
Commit changes:

git add -A
git commit -m "feat(scope): description"
git push origin develop
Directory Structure

app/
├── Http/Controllers/Api/V1/   # API controllers
├── Http/Requests/Api/V1/      # Form request validation
├── Http/Resources/V1/         # API resource transformers
├── Models/                    # Eloquent models
├── Policies/                  # Authorization policies
├── Providers/                 # Service providers
├── Services/                  # Business logic services
├── Listeners/                 # Event listeners
routes/
├── api.php                    # API route definitions
├── web.php                    # Web routes (SPA fallback)
database/
├── migrations/                # Database migrations
├── seeders/                   # Data seeders
├── factories/                 # Model factories
config/                        # Laravel configuration files
tests/
├── Feature/                   # Integration/feature tests
├── Unit/                      # Unit tests

7. Frontend Development Guide
Technology Stack
Technology
Version
Purpose
React
18.3.1
UI framework
TypeScript
5.5.4
Type safety
Vite
8.x
Build tool
MUI (Material UI)
6.1.1
Component library
TanStack Query
5.55.4
Server state management
Zustand
4.5.5
Client state management
React Router
6.26.1
Client-side routing
React Hook Form
7.53.0
Form handling
Zod
3.23.8
Schema validation
Axios
1.7.7
HTTP client
Leaflet / React-Leaflet
1.9.4 / 4.2.1
Maps
Tailwind CSS
4.0.0
Utility CSS
Laravel Vite Plugin
3.1
Vite-Laravel integration
Source Structure

resources/js/
├── app/                       # App entry, providers, theme setup
│   ├── App.tsx                # Root component with ThemeProvider
│   └── main.tsx               # Entry point
├── api/                       # API client functions
│   ├── system.ts              # System info/config API
│   ├── audit.ts               # Audit log API
│   └── ...
├── components/                # Shared/reusable components
│   └── navigation/            # Sidebar, nav config
├── features/                  # Feature-based modules
│   ├── auth/pages/            # LoginPage
│   ├── dashboard/             # DashboardPage, widgets
│   ├── organization/pages/    # Companies, Regions, Branches, Departments
│   ├── system/pages/          # SystemInfoPage, SystemConfigurationPage
│   ├── audit/pages/           # SecurityActivityPage
│   └── users/pages/           # UsersPage, UserDetailPage
├── layouts/                   # MainLayout with sidebar
├── routes/                    # Route definitions (index.tsx)
├── stores/                    # Zustand stores
│   ├── authStore.ts           # Authentication state
│   ├── configStore.ts         # System configuration state
│   └── themeStore.ts          # Theme preferences
├── theme/                     # MUI theme configuration
│   └── theme.ts               # createAppTheme()
└── types/                     # Shared TypeScript types

Build vs Runtime Distinction
Environment
Node/npm
Commands
Purpose
Host / Build
✅ Available
npm ci, npm run build, npx tsc --noEmit
Development and CI
Production Container
❌ Absent
None
Serves pre-compiled assets only
Adding a New Page
Create page component: resources/js/features/<module>/pages/<PageName>.tsx
Add route in resources/js/routes/index.tsx (lazy-loaded)
Add navigation item in resources/js/components/navigation/navConfig.tsx
Create API function in resources/js/api/<module>.ts if needed
Run type check: npx tsc --noEmit
Run build: npm run build
Rebuild Docker image: docker compose build app && docker compose up -d app horizon
Development Commands (Host Only)

# Type checking
npx tsc --noEmit

# Production build
npm run build

# Development server (optional, for HMR)
npm run dev

8. How to Build the Project
Validate Configuration
docker compose config

Build Production Image

docker compose build --no-cache app

This executes the multi-stage Dockerfile:
Stage 1: Installs npm dependencies, compiles frontend assets
Stage 2: Installs PHP extensions, copies application code, copies compiled assets from Stage 1, runs composer install --no-dev
Deploy
bash

docker compose up -d

Verify Build Output

# Manifest exists
docker compose exec app ls public/build/manifest.json

# Assets directory populated
docker compose exec app ls public/build/assets/

# Node/npm absent from runtime
docker compose exec app which node   # should return empty
docker compose exec app which npm    # should return empty

9. How to Test
TypeScript Check

npx tsc --noEmit

Expected: 0 errors, no output.
Environment: Host or Node build container. NOT inside ewnet-app.
Frontend Build

npm run build

Expected: Successful build with compiled assets in public/build/.
Environment: Host or Node build container. NOT inside ewnet-app.
PHPUnit

# Install dev dependencies temporarily inside container
docker compose exec app composer install --dev

# Run full suite
docker compose exec app vendor/bin/phpunit --no-coverage

Expected:
o 200
Note: PHPUnit requires dev dependencies. The production image excludes them by design. Dev dependencies must be installed temporarily inside the container for testing.
Expected: All 5 services Up. PostgreSQL and Redis show (healthy).
Application Smoke Tests

10. Authentication / Security Verification
Sanctum Configuration
Setting
Value
Stateful domains
oss.ewnet.com.np, localhost, 127.0.0.1
Session domain
.ewnet.com.np
Secure cookies
true
Session lifetime
120 minutes
Session driver
Redis
Cookie Attributes (Verified)
Secure ✅
HttpOnly ✅ (session cookie)
SameSite=Lax ✅
Domain: .ewnet.com.np ✅
Security Headers (Verified via Nginx)
Header
Value
Strict-Transport-Security
max-age=31536000; includeSubDomains
X-Content-Type-Options
nosniff
X-Frame-Options
SAMEORIGIN
Referrer-Policy
strict-origin-when-cross-origin
Permissions-Policy
geolocation=(), microphone=(), camera=()
TLS
Property
Value
Certificate
Let's Encrypt
CN
oss.ewnet.com.np
Valid Until
Nov 21, 2026
Auto-renewal
Certbot timer active
HTTP→HTTPS
301 redirect verified
Authorization
RBAC via spatie/laravel-permission
Policies enforce per-resource authorization
Management scopes provide organization-level data isolation
System configuration restricted to system.config.manage permission
11. Database / PostGIS
Property
Value
Engine
PostgreSQL 17.5
Spatial Extension
PostGIS 3.5
Container
ewnet-postgres
Image
postgis/postgis:17-3.5
Port
127.0.0.1:5432 (localhost only)
Data Volume
ewnet_postgres_data
Database Name
ewnet
Extensions
postgis, pg_trgm, uuid-ossp
Safe Inspection Commands
bash

# Database size
docker compose exec postgres psql -U ewnet -d ewnet -c "SELECT pg_size_pretty(pg_database_size(current_database()));"

# Connection count
docker compose exec postgres psql -U ewnet -d ewnet -c "SELECT state, count(*) FROM pg_stat_activity GROUP BY state;"

# Table sizes
docker compose exec postgres psql -U ewnet -d ewnet -c "SELECT relname, pg_size_pretty(pg_total_relation_size(relid)) FROM pg_statio_user_tables ORDER BY pg_total_relation_size(relid) DESC LIMIT 10;"

 DANGER: Never run DROP DATABASE, VACUUM FULL, REINDEX, or destructive DDL without explicit authorization and backup verification. These operations are NOT part of normal workflow.


12. Redis / Horizon
Redis
Property
Value
Version
7.4.11
Container
ewnet-redis
Image
redis:7.4-alpine
Port
127.0.0.1:6379 (localhost only)
Data Volume
ewnet_redis_data
Persistence
RDB snapshots (AOF disabled)
Max Memory
Unlimited (⚠️ see Section 15)
Eviction Policy
noeviction (⚠️ see Section 15)
Horizon
Property
Value
Supervisor
supervisor-1
Connection
Redis
Queue
default
Balancing
Auto
Max Processes
10
Tries
3
Timeout
60s
Memory Limit
64 MB
Verification Commands
bash

# Horizon status
docker compose exec app php artisan horizon:status
# Expected: "INFO  Horizon is running."

# Redis connectivity
docker compose exec redis redis-cli ping
# Expected: PONG

# Failed jobs count
docker compose exec postgres psql -U ewnet -d ewnet -c "SELECT COUNT(*) FROM failed_jobs;"

13. Installation / Deployment
Automated Installer

curl -fsSL https://raw.githubusercontent.com/ramkatuwal/ewnet-oss-bss/develop/install.sh | sudo bash

The installer script (install.sh) performs:
Ubuntu version check (requires 24.04+)
Resource checks (CPU, RAM, disk)
Docker and Docker Compose installation
Repository clone/checkout
.env generation from .env.example
APP_KEY generation
Docker image build
Database migration and seeding
Container startup
Health verification
⚠️ TLS is NOT automated. Certificates must be provisioned separately via Certbot or manual installation before HTTPS will function.
Manual Deployment
bash

cd /opt/misp
git pull origin develop
docker compose build --no-cache app
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:clear
docker compose exec app php artisan package:discover

14. Production Verification Checklist
Use this checklist for repeatable production verification:

[ ] Git working tree clean
[ ] On correct branch (develop)
[ ] docker compose config validates
[ ] Docker image builds successfully
[ ] All 5 containers running
[ ] PostgreSQL healthy
[ ] Redis healthy
[ ] Horizon running (php artisan horizon:status)
[ ] Nginx serving frontend (GET / → 200)
[ ] Branding API public (GET /api/v1/branding → 200)
[ ] Protected API returns 401 unauthenticated
[ ] TypeScript: 0 errors (npx tsc --noEmit)
[ ] PHPUnit: 183 tests, 411 assertions, 0 failures
[ ] public/build/manifest.json exists in container
[ ] Node/npm absent from production runtime
[ ] TLS certificate valid
[ ] HTTP→HTTPS redirect working
[ ] Security headers present

15. Known Warnings / Limitations
Non-Blocking Build Warnings
Warning
Severity
Notes
Vite __dirname deprecation
INFO
Use import.meta.dirname in future Vite upgrade
esbuild option deprecated in vite:react-babel
INFO
Migrate to @vitejs/plugin-react-oxc when ready
LightningCSS unknown @theme / @tailwind rules
INFO
Tailwind v4 syntax; cosmetic only
optimizeDeps.rollupOptions deprecated
INFO
Use optimizeDeps.rolldownOptions in future
These are informational. They do not affect build output or runtime behavior.
Infrastructure Recommendations (EXISTING BUT NEEDS REVIEW)
Item
Status
Details
Docker log rotation
⚠️ NOT CONFIGURED
All containers use json-file with no max-size/max-file. Web container log already 23MB. Recommend max-size: 10m, max-file: 3.
Docker build cache
⚠️ 20.88 GB
Should be pruned in maintenance window via docker builder prune
Anonymous vendor volumes
⚠️ ARCHITECTURE RISK
Can persist stale dependencies across rebuilds. Caused Horizon failure in TASK-029-B. Consider removing or redesigning.
Container health checks
⚠️ PARTIAL
Only PostgreSQL and Redis have health checks. App, Horizon, and Web lack them.
Non-root containers
⚠️ NOT IMPLEMENTED
All containers run as root. Requires permission audit before conversion.
Resource limits
⚠️ NOT CONFIGURED
No CPU/memory/PID limits on any container. Low current utilization but should be baselined.
Redis maxmemory
⚠️ UNLIMITED
No memory cap configured. Risk of OOM under load.
Redis AOF
⚠️ DISABLED
Only RDB persistence. Queue jobs may be lost on crash.
SSH hardening
⚠️ ROOT LOGIN ENABLED
Password authentication enabled. Key-only recommended.
Backup strategy
🔴 NONE
No automated database, application, or volume backups exist.
Config/routes cache
⚠️ NOT CACHED
Laravel config and routes not cached in production. Performance impact.
PHP upload limit
⚠️ 2MB
Too low for logo/avatar uploads.
PHP expose_php
⚠️ ON
Information disclosure. Should be Off.
Manual TLS Provisioning
TLS certificates are managed via Let's Encrypt / Certbot. The certbot systemd timer is active. However, initial certificate provisioning is manual and not integrated into the installer.
16. Architecture Review Checklist
Application
Laravel architecture follows standard conventions
API structure is versioned (/api/v1/)
Domain boundaries are clear (Organization, Security, System, Audit)
Validation uses FormRequest classes
Authorization uses policies + Spatie permissions
Queue architecture uses Redis + Horizon
Frontend
TypeScript strict mode enabled
Component structure follows feature-based organization
API integration uses Axios + TanStack Query
Authentication state managed via Zustand + Sanctum cookies
Build architecture uses multi-stage Docker build
Infrastructure
Docker multi-stage build separates build/runtime
Nginx handles TLS, static assets, reverse proxy
PostgreSQL/PostGIS provides primary data store
Redis provides cache, session, queue backend
Horizon manages queue workers
Volumes provide persistent storage
Logging needs rotation configuration
Health checks need expansion
Resource limits need definition
Security
Sanctum session authentication implemented
RBAC authorization via Spatie permissions
Secrets in .env (not committed to Git)
Containers run without privileged mode
Database/Redis bound to localhost only
TLS with HSTS enabled
Security headers configured
Backup strategy needs implementation
Operations
Installer script exists (install.sh)
Upgrade procedure documented (manual)
Rollback via Git + Docker rebuild
Backup/restore needs implementation
Monitoring needs implementation
Log rotation needs configuration
Disaster recovery plan needs creation
17. Future Architecture Direction
⚠️ Nothing below is implemented. These are future areas requiring architectural decisions before any implementation begins.
Integration Foundation
Before building network integrations, the following foundation must be established:
Stable API versioning strategy
External system adapter/interface pattern
Credential/vault management for third-party systems
Audit logging for external system interactions
Error handling and retry patterns for unreliable external APIs
Potential Future Integration Domains (FUTURE)
Domain
Purpose
Status
LibreNMS API
Network monitoring data ingestion
FUTURE
RADIUS/NAS
Authentication/accounting integration
FUTURE
Juniper
Device management/configuration
FUTURE
Huawei
Device management/configuration
FUTURE
MikroTik
Device management/configuration
FUTURE
Other NMS
Generic network management system adapters
FUTURE
These integrations require:
Completion of current Phase 3 stabilization
Architecture review approval
Dedicated design tasks for each integration domain
Adapter pattern implementation before specific vendor work
18. Definition of Done
This document is complete when:
Reflects actual repository at commit 02d572a
All commands verified against live environment
No secrets included
No invented architecture
Backend development process documented
Frontend development process documented
Production runtime vs build environment clearly explained
Testing procedure is reproducible
Architecture review checklist exists
Future work separated from current implementation
End of Architecture / Owner Review Technical Baseline
