# AGENTS.md

# EWNET OSS/BSS — MASTER ENGINEERING CONTRACT v2.1

**Project:** EWNET OSS/BSS
**Company:** Everest Wireless Network Pvt. Ltd. (EWNET)
**Repository:** `/opt/misp`
**Primary Branch:** `develop`
**System:** Telecom Operations Support System / Business Support System
**Governance Level:** CEO / CTO / Principal Architect
**Document:** AI + Human Engineering Constitution
**Version:** 2.1

---

# 1. PURPOSE

This document is the mandatory engineering contract for all developers, coding agents, AI agents, automation systems, and technical operators working inside the EWNET OSS/BSS repository.

It defines:

* engineering governance
* architecture
* development standards
* security requirements
* database rules
* API rules
* frontend rules
* integration rules
* debugging methodology
* testing requirements
* Git discipline
* production safety
* AI-agent behavior
* OpenCode provider/model policy
* completion and reporting requirements

This document must be treated as an operational engineering policy, not merely as project documentation.

The objective is to ensure that every change is:

* correct
* secure
* reviewable
* testable
* auditable
* maintainable
* production-safe
* architecturally consistent

---

# 2. PROJECT MISSION

EWNET OSS/BSS is a production-grade telecom platform intended to provide centralized operational and business management capabilities.

The platform is designed to evolve into a comprehensive ISP OSS/BSS supporting:

* organizations
* companies
* regions
* branches
* users
* roles
* permissions
* management scopes
* sites
* network assets
* inventory
* network integrations
* LibreNMS
* UISP
* monitoring
* imports
* synchronization
* dashboards
* audit logging
* operational workflows
* future telecom OSS/BSS domains

The system must be engineered as a coherent long-lived platform.

It must NOT become a collection of disconnected CRUD features.

---

# 3. AI GOVERNANCE MODEL

## 3.1 Master Architect

The Master Architect is the final technical and architectural decision authority for implementation work.

The Master Architect may be:

* ChatGPT
* Coding AI
* explicitly designated human architect
* EWNET technical leadership

The Master Architect defines:

* requirements
* architecture
* implementation strategy
* scope
* priorities
* security posture
* database design
* API contracts
* integration contracts
* acceptance criteria
* release criteria

---

## 3.2 OpenCode

OpenCode is the **Execution Agent**.

OpenCode is responsible for:

* repository inspection
* implementation
* file modification
* command execution
* debugging
* test execution
* verification
* evidence collection
* engineering reporting

OpenCode must NOT silently redefine architecture.

OpenCode must NOT invent requirements.

OpenCode must NOT override the Master Architect's decisions.

OpenCode must NOT make destructive changes simply because they appear convenient.

---

## 3.3 Developer / Operator

The human developer/operator is responsible for:

* providing approved requirements
* approving production-impacting operations
* providing credentials when required
* reviewing significant changes
* approving deployment/release actions

---

## 3.4 Authority Hierarchy

The effective engineering hierarchy is:

```text
EWNET Management / Product Authority
                ↓
Master Architect
(ChatGPT / Coding AI / Human Architect)
                ↓
Approved Engineering Plan
                ↓
OpenCode Execution Agent
                ↓
Repository
                ↓
Tests / Evidence / Git
```

OpenCode must preserve this hierarchy.

---

# 4. CURRENT TECHNOLOGY BASELINE

The current project baseline is:

```text
Operating System:
Ubuntu 24.04 LTS

Backend:
Laravel 13
PHP 8.4

Frontend:
React 18
TypeScript
Vite

Database:
PostgreSQL 17
PostGIS

Cache / Queue:
Redis
Laravel Horizon

Web Server:
Nginx

Containerization:
Docker
Docker Compose

Authentication:
Laravel Sanctum

Authorization:
Spatie Laravel Permission
Management Scope authorization

Testing:
PHPUnit

Frontend Validation:
TypeScript strict mode
Vite production build
```

Do not upgrade major framework versions as part of an unrelated task.

Any major platform upgrade requires explicit architectural approval.

---

# 5. REPOSITORY SAFETY

## 5.1 Mandatory Initial Inspection

Before making non-trivial changes:

```bash
pwd
git branch --show-current
git status --short
git diff --stat
git log --oneline -10
```

Then inspect the relevant implementation.

---

## 5.2 Existing Changes Are Sacred

If the working tree contains existing changes:

* preserve them
* inspect them
* understand them
* do not overwrite them
* do not reset them
* do not stash them automatically
* do not clean them automatically
* do not revert them automatically

Existing changes may belong to another task or an unfinished approved implementation.

---

## 5.3 Forbidden Without Explicit Approval

Never execute these operations without explicit authorization:

```bash
git reset --hard
git clean -fd
git checkout -- .
git restore .
git stash
git rebase
git push --force
git branch -D
```

Do not use destructive commands to "fix" an inconvenient working tree.

---

## 5.4 Never Hide Work

Do not:

* silently stash changes
* silently revert files
* delete unknown files
* overwrite user modifications
* regenerate configuration over existing work
* remove migrations because they appear duplicated
* delete tests because they fail

---

# 6. GIT GOVERNANCE

## 6.1 Before Work

Always inspect:

```bash
git status --short
git branch --show-current
git log --oneline -10
```

---

## 6.2 After Work

Run:

```bash
git diff --check
git status
git diff --stat
```

Review the actual diff.

---

## 6.3 Commit Policy

Never create a Git commit unless explicitly instructed.

Before a requested commit:

```bash
git diff --check
git status
git diff
```

Run the relevant tests first.

Commit messages should identify the task.

Preferred format:

```text
EWNET-TASK-XXX: Short description
```

Avoid meaningless messages such as:

```text
fix
update
changes
test
work
```

---

## 6.4 Push Policy

Never force-push without explicit authorization.

Never overwrite remote history to resolve a local problem.

---

# 7. REQUIREMENT DISCIPLINE

Every significant task must establish:

```text
TASK
OBJECTIVE
CURRENT STATE
ROOT CAUSE
SCOPE
NON-SCOPE
DEPENDENCIES
RISKS
IMPLEMENTATION PLAN
TEST PLAN
ACCEPTANCE CRITERIA
```

Do not expand scope silently.

If additional issues are discovered, report them separately.

---

# 8. NO-ASSUMPTION POLICY

Never assume:

* a route exists
* a permission exists
* a model exists
* a database column exists
* a migration has executed
* a relationship exists
* an API field exists
* an external API behaves a certain way
* a queue is running
* Redis is healthy
* a Docker container is healthy
* a frontend route exists
* a provider is authenticated
* a model is available
* a user has authorization

Verify first.

Unknown information must remain explicitly unknown until verified.

---

# 9. PROJECT STRUCTURE

Current major structure:

```text
app/
  Http/Controllers/Api/V1/
    — API controllers

  Http/Requests/Api/V1/
    — API form requests

  Http/Resources/V1/
    — API resource transformers

  Models/
    — Eloquent models

  Services/
    — business/application services

  Integrations/Providers/
    — LibreNMS + UISP integration clients

  Jobs/
    — queued imports and synchronization jobs

  Policies/
    — authorization policies

resources/js/
  main.tsx
    — React entrypoint

  app/App.tsx
    — root application

  routes/index.tsx
    — frontend routes

  api/client.ts
    — Axios API client

  stores/
    — Zustand stores

  features/
    — feature modules

  components/
    — shared components

  layouts/
    — application layouts

  types/
    — TypeScript types

routes/
  api.php
    — API routes under /v1

  web.php
    — SPA catch-all

database/
  migrations/
  seeders/

tests/
  Feature/
  Unit/
```

---

# 10. QUICK COMMANDS

## 10.1 PHP / Backend

Run the full test suite:

```bash
composer test
```

or:

```bash
./vendor/bin/phpunit
```

Host execution:

```bash
DB_HOST=127.0.0.1 ./vendor/bin/phpunit
```

Run a specific test class:

```bash
./vendor/bin/phpunit tests/Feature/Integrations/ImportPipelineTest.php
```

Run a specific test method:

```bash
./vendor/bin/phpunit tests/Feature/Integrations/ImportPipelineTest.php \
    --filter test_uisp_preview_returns_sites_and_devices
```

Laravel Pint:

```bash
./vendor/bin/pint
```

Artisan inside the application container:

```bash
docker compose exec app php artisan <command>
```

---

# 11. CRITICAL PHP TEST SAFETY

Tests must never accidentally target the production database.

The project uses `phpunit.xml` to pin:

```text
DB_DATABASE=ewnet_test
CACHE_STORE=array
```

This is intentional.

Do not remove or bypass this protection.

Important:

```text
php artisan test
```

may inherit environment variables from the parent process.

Therefore prefer:

```bash
./vendor/bin/phpunit
```

or:

```bash
DB_HOST=127.0.0.1 ./vendor/bin/phpunit
```

Do not modify test configuration merely to make a test pass.

---

# 12. FRONTEND COMMANDS

Development:

```bash
npm run dev
```

Production build:

```bash
npm run build
```

Type checking:

```bash
npm run typecheck
```

TypeScript is strict.

The project enforces:

```text
noUnusedLocals
noUnusedParameters
```

---

# 13. DOCKER COMMANDS

Build:

```bash
docker compose build
```

Start:

```bash
docker compose up -d
```

Check services:

```bash
docker compose ps
```

Artisan:

```bash
docker compose exec app php artisan <command>
```

Do not rebuild the entire stack unnecessarily.

---

# 14. BACKEND ARCHITECTURE

Controllers must remain thin.

Preferred flow:

```text
HTTP Request
     ↓
Authentication
     ↓
Authorization
     ↓
Form Request
     ↓
Controller
     ↓
Service
     ↓
Model / Repository / Integration
     ↓
Resource / Response
```

Controllers should primarily:

1. authorize
2. validate
3. call application/domain services
4. return responses

Large business workflows should not be embedded directly inside controllers.

---

# 15. FORM REQUESTS

Use Form Requests for request validation.

Validation must be:

* explicit
* deterministic
* reusable where appropriate
* consistent

Do not duplicate conflicting validation rules across controllers.

---

# 16. SERVICE LAYER

Services should contain business/application workflows such as:

* imports
* synchronization
* mapping
* complex state changes
* integration orchestration

Services should be:

* testable
* explicit
* deterministic where possible
* idempotent where appropriate
* failure-aware

---

# 17. MODEL GOVERNANCE

Models should represent:

* persistence
* relationships
* casts
* appropriate scopes
* domain-relevant behavior

Do not turn models into giant service classes.

---

# 18. MODEL ADDITION PROTOCOL

Before creating a new model, OpenCode MUST inspect:

```text
1. Existing models
2. Existing database tables
3. Existing migrations
4. Existing relationships
5. Existing API usage
6. Existing frontend usage
7. Existing tests
8. Existing external identifiers
9. Authorization requirements
10. Audit requirements
```

Determine:

```text
Model:
Purpose:
Table:
Owner:
Relationships:
Unique constraints:
Indexes:
Authorization:
Audit:
External IDs:
Lifecycle:
Soft delete:
```

Only then implement.

Do not create duplicate domain concepts.

---

# 19. DATABASE GOVERNANCE

PostgreSQL is the authoritative system of record.

Before adding a column:

1. inspect the table
2. inspect the model
3. inspect migrations
4. inspect constraints
5. inspect indexes
6. inspect consumers
7. inspect tests

Before adding a table:

1. confirm domain ownership
2. confirm lifecycle
3. confirm relationships
4. confirm authorization
5. confirm auditing
6. confirm uniqueness
7. confirm indexes
8. confirm external IDs where applicable

---

# 20. MIGRATION RULES

Never casually edit an old migration that may already have executed.

Create a new migration instead.

Before creating a migration:

```bash
ls database/migrations
```

Search for related migrations.

Known repository condition:

```text
Duplicate create_permission_tables migrations exist.
Duplicate add_foreign_key_manager_id migrations exist.
```

These are legacy artifacts.

Do NOT delete or rewrite them merely because they look duplicated.

Investigate migration history first.

---

# 21. POSTGRESQL / POSTGIS

PostGIS is enabled and is part of the system architecture.

Before implementing geospatial functionality:

* verify coordinate types
* verify SRID
* verify latitude/longitude conventions
* validate coordinate ranges
* determine nullability
* determine indexes

Valid coordinate ranges:

```text
Latitude:
-90 to +90

Longitude:
-180 to +180
```

Never silently swap latitude and longitude.

---

# 22. API GOVERNANCE

Current API convention:

```text
/api/v1/...
```

Only explicitly public endpoints may bypass authentication.

Current public exception:

```text
/v1/branding
```

Before adding an endpoint:

1. inspect `routes/api.php`
2. search for an existing route
3. inspect controller
4. inspect policy
5. inspect Form Request
6. inspect Resource
7. inspect frontend callers
8. inspect tests
9. verify middleware

Never create a duplicate endpoint.

Always verify HTTP method.

---

# 23. API ENDPOINT ADDITION PROTOCOL

Before implementation:

```text
Route
 ↓
HTTP Method
 ↓
Middleware
 ↓
Authentication
 ↓
Authorization
 ↓
Validation
 ↓
Controller
 ↓
Service
 ↓
Resource
 ↓
Tests
```

Verify:

* request schema
* response schema
* error responses
* pagination
* authorization
* management scope
* backward compatibility

---

# 24. AUTHENTICATION

The application uses Laravel Sanctum.

Current SPA flow:

```text
GET /sanctum/csrf-cookie
        ↓
Authentication request
        ↓
Sanctum session/cookie
        ↓
Authenticated API calls
```

Frontend 401 responses automatically redirect to login.

Do not weaken authentication to solve application errors.

---

# 25. AUTHORIZATION

Authentication is NOT authorization.

Protected operations must consider:

```text
Authentication
       +
Permission
       +
Management Scope
       +
Resource Access / Ownership
```

Backend authorization is authoritative.

Never rely solely on frontend permission checks.

---

# 26. RBAC

Authorization uses Spatie Laravel Permission.

Roles and permissions are seeded through:

```text
database/seeders/RolesAndPermissionsSeeder.php
```

Before adding a permission:

1. search existing permissions
2. inspect the seeder
3. inspect roles
4. inspect policies
5. inspect frontend usage
6. add tests

Use consistent permission names.

Example:

```text
system.debug.view
```

Do not invent inconsistent naming conventions.

---

# 27. MANAGEMENT SCOPES

Management scope is a first-class security boundary.

Users may be scoped to organizational entities such as:

* companies
* regions
* branches

Relevant architecture includes:

```text
UserManagementScope
ChecksManagementScope
ManagementScopeService
```

Never solve a scope problem by blindly granting a broader permission.

Never trust client-supplied company/branch IDs as authorization evidence.

Authorization must be evaluated server-side.

---

# 28. FRONTEND ARCHITECTURE

The frontend uses:

```text
React 18
TypeScript
Vite
React Router
Axios
TanStack Query
Zustand
```

Feature organization:

```text
resources/js/features/<domain>/
```

Shared code:

```text
resources/js/components/
resources/js/hooks/
```

Do not duplicate API logic across components.

---

# 29. API CLIENT

The centralized Axios client is:

```text
resources/js/api/client.ts
```

It handles:

* CSRF
* authentication
* response processing
* pagination flattening
* 401 behavior

Do not create competing Axios clients without architectural justification.

The response interceptor currently flattens Laravel `meta` pagination fields into top-level fields for:

```text
PaginatedResponse<T>
```

Do not break this contract unintentionally.

---

# 30. TYPESCRIPT

Strict mode must remain enabled.

Do not use:

```text
any
@ts-ignore
@ts-nocheck
```

to hide implementation errors unless explicitly justified and documented.

Before completing frontend work:

```bash
npm run typecheck
npm run build
```

A failed production build means the task is not complete.

---

# 31. UI / UX COMPLETION

A feature is not complete merely because it renders.

Every appropriate page must consider:

* loading
* empty state
* error state
* validation state
* permission denied state
* pagination
* cache invalidation
* navigation
* responsive behavior
* accessibility

---

# 32. QUEUES AND HORIZON

Long-running operations should use queued jobs.

Current queued workloads include:

```text
ProcessAssetImport
ProcessSiteImport
RunIntegrationSync
```

Before debugging queues:

```bash
docker compose ps
```

Then inspect:

* Horizon
* queue status
* failed jobs
* job payload
* exception
* retry count
* timeout
* backoff
* queue name

Do not repeatedly retry failures without understanding the cause.

---

# 33. REDIS

Redis is used for:

* caching
* queues
* transient state

Redis is NOT the authoritative business data store.

Do not store durable business state only in Redis.

Cache keys must be:

* explicit
* collision-safe
* appropriately scoped
* given appropriate TTLs

The project previously encountered a TanStack Query cache-key collision.

Therefore query/cache keys must be domain-specific.

---

# 34. INTEGRATION ARCHITECTURE

The integration layer must remain provider-agnostic.

Current important providers:

```text
LibreNMS
UISP
```

Preferred architecture:

```text
Integration
     ↓
Provider Client
     ↓
DTO / Normalizer
     ↓
Mapper
     ↓
Domain Service
     ↓
Canonical Database
```

Do not spread provider-specific payload structures throughout the application.

---

# 35. EXTERNAL API VERIFICATION

Before implementing an external integration:

1. verify actual endpoint
2. verify HTTP method
3. verify authentication
4. verify parameters
5. inspect actual response
6. identify pagination
7. identify rate limits
8. identify timeout behavior
9. identify retries
10. identify duplicate behavior
11. identify provider identifiers
12. identify mapping requirements

Never infer an external API contract from UI appearance.

---

# 36. LIBRENMS

LibreNMS imports must distinguish:

```text
device ID
hostname
display
asset tag
site
IP
metadata
```

Do not assume hostname equals display name.

Provider fields must be mapped explicitly.

Do not overwrite meaningful canonical values with empty provider values.

Import operations must be idempotent.

---

# 37. UISP

UISP integration must map provider data into canonical EWNET entities.

Preferred matching order:

```text
Provider external ID
        ↓
Existing canonical mapping
        ↓
Deterministic fallback
```

Name-based fallback matching must be deterministic.

Ambiguous matches must not be automatically created.

Potential UISP data includes:

```text
sites
devices
device types
firmware
last seen
alerts
interfaces
IP addresses
```

Synchronization must be concurrency-safe.

Recommended lock concept:

```text
uisp-sync:{integration_id}
```

Long-running synchronization must have an appropriate lock TTL.

---

# 38. IMPORT / SYNCHRONIZATION PIPELINE

Preferred pipeline:

```text
INPUT
  ↓
VALIDATION
  ↓
NORMALIZATION
  ↓
IDENTIFICATION
  ↓
MATCHING
  ↓
CREATE / UPDATE
  ↓
RELATIONSHIPS
  ↓
AUDIT
  ↓
RESULT
```

Each stage must have explicit failure behavior.

Never silently discard records.

---

# 39. IMPORT DATA INTEGRITY

Protect against:

* duplicate records
* duplicate serial numbers
* missing identifiers
* invalid coordinates
* invalid relationships
* ambiguous provider fields
* partial imports
* transaction failures

Do not use fake unique placeholders such as:

```text
N/A
UNKNOWN
```

where a uniqueness constraint exists.

Use actual `NULL` when missing data is legitimately unknown and the schema permits it.

---

# 40. IDEMPOTENCY

Imports and synchronization must be safe to run more than once.

The same provider record should not produce duplicate canonical records.

Preferred identity mechanisms include:

```text
external provider ID
provider + external ID
stable canonical mapping
```

Do not use names as the primary identity when stable IDs are available.

---

# 41. CONCURRENCY

Synchronization jobs must consider duplicate concurrent execution.

Use deterministic distributed locks where appropriate.

Never assume Horizon will prevent duplicate dispatch.

---

# 42. ERROR HANDLING

Errors must be:

* deterministic
* meaningful
* safe
* logged appropriately
* returned using established API conventions

Never expose to users:

* stack traces
* SQL queries
* secrets
* internal paths
* access tokens

---

# 43. LOGGING

Logs must contain enough safe information to diagnose failures.

Do NOT log:

* API keys
* passwords
* access tokens
* session secrets
* private keys

Avoid excessive production logging.

Debug logging must not leak sensitive data.

Previous security work specifically removed unsafe production logging such as GPS/debug data.

Preserve that standard.

---

# 44. AUDIT LOGGING

Security-sensitive and business-significant operations should be auditable.

Audit information may include:

```text
actor
action
resource
resource ID
timestamp
safe context
before/after state where appropriate
```

Never store secrets in audit logs.

Audit logging must not unnecessarily break the underlying business operation.

---

# 45. SECURITY

Security is a release blocker.

Check for:

```text
IDOR
authorization bypass
privilege escalation
management-scope bypass
mass assignment
SQL injection
command injection
XSS
CSRF
SSRF
path traversal
unsafe file uploads
secret leakage
sensitive logging
insecure redirects
unsafe imports
```

Never weaken security merely to make a test pass.

---

# 46. SECURITY INCIDENT PROTOCOL

If unexpected records or infrastructure changes appear, stop normal development and investigate.

Examples:

* unexpected users
* unexpected companies
* unexpected sites
* unexpected assets
* unexpected permissions
* unexpected firewall changes
* unexpected API requests

Investigate:

```text
authentication
audit logs
application logs
Nginx logs
database records
request origins
tokens/sessions
Git history
deployment changes
```

Do not immediately conclude:

```text
hacked
```

or:

```text
safe
```

Evidence determines the conclusion.

---

# 47. PERFORMANCE

Before optimizing:

1. measure
2. reproduce
3. identify bottleneck
4. inspect query behavior
5. inspect indexes
6. inspect N+1 behavior
7. inspect caching
8. inspect queue behavior

Avoid premature optimization.

---

# 48. DATABASE PERFORMANCE

Avoid:

* unbounded queries
* N+1 relationships
* unnecessary `SELECT *`
* missing indexes
* loading huge datasets into memory

For large processing workloads consider:

* chunking
* cursors
* queues
* bulk operations

---

# 49. EXTERNAL API TIMEOUTS

External API calls must define appropriate:

```text
connect timeout
request timeout
retry policy
backoff
rate limiting
failure behavior
```

External systems must never be allowed to hang workers indefinitely.

---

# 50. OPENCode MODEL / PROVIDER GOVERNANCE

OpenCode is the execution environment.

The preferred provider for EWNET coding execution is:

```text
Google Gemini API
```

Preferred provider namespace:

```text
google/
```

Examples:

```text
google/gemini-3.5-flash
google/gemini-3.6-flash
google/gemini-3.1-pro-preview
```

---

# 51. COPILOT PROVIDER RESTRICTION

The following are NOT equivalent:

```text
google/gemini-3.5-flash
```

and:

```text
github-copilot/gemini-3.5-flash
```

The first uses the Google provider.

The second uses GitHub Copilot.

Unless explicitly authorized by the Master Architect:

```text
github-copilot/*
```

must NOT be selected.

---

# 52. COPILOT ERROR HANDLING

If OpenCode reports:

```text
Forbidden: unauthorized: not licensed to use Copilot
```

OpenCode must:

1. stop
2. inspect the selected model
3. identify the provider
4. report the provider/model
5. switch only to an authorized provider/model
6. continue only after provider authentication is valid

Do NOT attempt to bypass provider licensing.

Do NOT silently switch providers.

---

# 53. GEMINI API SECURITY

Gemini API credentials are secrets.

Never place API keys in:

```text
AGENTS.md
source code
Git
committed shell scripts
frontend code
public files
logs
documentation
```

Use secure environment/configuration mechanisms.

API keys must never be exposed to browser-side JavaScript.

---

# 54. OPENCode MODEL REPORTING

Before significant implementation work, OpenCode should report:

```text
Provider:
Model:
Repository:
Branch:
Working Tree:
```

Example:

```text
Provider: Google
Model: google/gemini-3.5-flash
Repository: /opt/misp
Branch: develop
Working Tree: modified — preserving existing changes
```

---

# 55. AI EXECUTION MODES

## Plan Mode

Use Plan Mode for:

* architecture analysis
* root-cause analysis
* large changes
* migrations
* security work
* integration design
* refactoring
* uncertain requirements

Plan Mode must not modify application code.

---

## Build Mode

Build Mode may:

* modify files
* execute commands
* run tests

Build Mode remains subject to all repository safety rules.

---

# 56. MANDATORY DEBUGGING PROTOCOL

Never patch symptoms blindly.

Required sequence:

```text
1. Reproduce
2. Capture exact error
3. Identify affected layer
4. Trace request/data flow
5. Inspect logs
6. Inspect database state
7. Inspect relevant code
8. Identify root cause
9. Implement minimal correct fix
10. Add regression test
11. Run focused test
12. Run broader regression suite
13. Verify final behavior
14. Report evidence
```

---

# 57. ROOT CAUSE REPORTING

Every significant bug fix must state:

```text
ROOT CAUSE:
...

WHY IT HAPPENED:
...

FIX:
...

WHY THE FIX IS CORRECT:
...

REGRESSION TEST:
...
```

Never report only:

```text
Fixed.
```

---

# 58. TESTING GOVERNANCE

A feature is not complete because the code compiles.

Appropriate testing levels include:

```text
Unit
Feature
Integration
API
Database
Frontend typecheck
Frontend build
```

At minimum:

1. focused test
2. relevant regression tests
3. broader suite where appropriate

---

# 59. TEST FAILURE POLICY

When tests fail:

Do not immediately modify production code.

First determine:

```text
Is the test wrong?
Is the implementation wrong?
Is the environment wrong?
Is the fixture wrong?
Is the database state wrong?
Is there a pre-existing failure?
```

Never delete or weaken a test simply because it fails.

---

# 60. FRONTEND BUILD COMPLETION

Frontend work is not complete until, where applicable:

```bash
npm run typecheck
npm run build
```

both pass.

If either fails:

```text
STATUS: BLOCKED
```

or:

```text
STATUS: PARTIAL
```

Do not report complete.

---

# 61. DOCKER VERIFICATION

After container-affecting changes:

```bash
docker compose ps
```

Verify relevant containers.

Where required:

```bash
docker compose build
docker compose up -d
```

Then verify application health.

---

# 62. PRODUCTION SAFETY

Before production-impacting changes verify:

```text
environment
database
migrations
queues
Redis
Nginx
permissions
filesystem
logs
backup availability
service health
```

Never assume development and production are equivalent.

---

# 63. BACKUP SAFETY

Before destructive database operations:

1. confirm environment
2. confirm database
3. verify backup availability
4. verify recovery path
5. obtain explicit authorization

Never execute destructive production operations without authorization.

---

# 64. KNOWN REPOSITORY GOTCHAS

The following known conditions must be preserved unless explicitly remediated as a dedicated task.

## 64.1 Test Configuration

`composer test` intentionally clears configuration before running tests.

Do not remove this behavior.

---

## 64.2 Testing Database

Tests use:

```text
ewnet_test
```

The production database is:

```text
ewnet
```

Never confuse the two.

---

## 64.3 Docker Volume Mounts

Frontend:

```text
node_modules
```

and backend:

```text
vendor
```

are volume-mounted in Docker.

Host installations may therefore be visible inside containers.

---

## 64.4 Stray Backup Files

Stray `.bak` files exist under:

```text
app/Services/
app/Integrations/
```

Ignore them unless a dedicated cleanup task is authorized.

---

## 64.5 Duplicate Legacy Migrations

Known duplicate migrations include:

```text
create_permission_tables
add_foreign_key_manager_id
```

Do not delete them casually.

---

## 64.6 Generated IDE Helper

`_ide_helper.php` is tracked at the repository root.

It is generated.

Do not edit it manually.

---

## 64.7 CI / Hooks

There is currently:

```text
No lint CI
No pre-commit hooks
No ESLint
No Prettier
```

Therefore manual verification is required.

---

# 65. CHANGE MINIMIZATION

Prefer changes that are:

```text
small
focused
reviewable
testable
reversible
```

Avoid unrelated refactoring.

If refactoring is required for correctness or security, explain why.

---

# 66. BACKWARD COMPATIBILITY

Before changing:

* API responses
* database fields
* routes
* permissions
* integration mappings
* frontend contracts

search all consumers.

Do not silently break existing behavior.

---

# 67. API COMPATIBILITY

When changing an API:

```text
Existing consumers
        ↓
Existing response contract
        ↓
Existing frontend types
        ↓
Existing tests
```

must be reviewed.

Do not remove fields merely because the current UI does not use them.

---

# 68. COMPLETION CRITERIA

A significant task is complete only when:

```text
Requirement satisfied
        +
Root cause addressed
        +
Implementation complete
        +
Authorization verified
        +
Focused tests passed
        +
Regression tests passed
        +
Frontend verified where applicable
        +
Database verified where applicable
        +
Integration verified where applicable
        +
Git state reviewed
        +
No unexplained critical errors
```

---

# 69. BLOCKED STATE

If safe implementation cannot continue:

```text
STATUS: BLOCKED
```

Report:

```text
BLOCKER:
...

EVIDENCE:
...

WHY IT BLOCKS IMPLEMENTATION:
...

WHAT IS REQUIRED:
...
```

Do not invent workarounds merely to claim completion.

---

# 70. STOP CONDITIONS

OpenCode must stop and request direction when:

* requirements conflict
* production data may be affected
* destructive operations are required
* credentials are required
* architecture is ambiguous
* database ownership is unclear
* authorization behavior is unclear
* management scope is unclear
* an external API contract is uncertain
* existing uncommitted changes may be affected
* tests reveal a serious unrelated regression
* provider authentication is ambiguous
* an unauthorized provider is selected
* a proposed change materially expands scope

---

# 71. NEVER DO THESE

Never:

* fabricate test results
* fabricate API responses
* fabricate database state
* fabricate Git commits
* claim success without evidence
* silently redefine architecture
* bypass authorization
* bypass management scopes
* disable security controls
* weaken tests
* delete tests to achieve green status
* delete migrations casually
* delete user changes
* reset the repository
* expose secrets
* commit credentials
* expose API keys
* use GitHub Copilot without authorization
* treat `github-copilot/gemini-*` as Google Gemini
* claim Gemini works without verifying Google authentication
* mark incomplete work as complete
* silently change providers
* silently change database schema
* silently change API contracts

---

# 72. FINAL ENGINEERING REPORT

Every significant task must end with:

```text
============================================================
EWNET ENGINEERING COMPLETION REPORT
============================================================

TASK:
...

OBJECTIVE:
...

STATUS:
COMPLETE / PARTIAL / BLOCKED

CURRENT STATE:
...

ROOT CAUSE:
...

WHY IT HAPPENED:
...

IMPLEMENTATION:
...

FILES CHANGED:
...

DATABASE CHANGES:
...

API CHANGES:
...

FRONTEND CHANGES:
...

AUTHORIZATION / SECURITY:
...

INTEGRATION CHANGES:
...

TESTS:
...

TYPECHECK:
...

BUILD:
...

DOCKER / RUNTIME VERIFICATION:
...

GIT STATUS:
...

COMMIT:
...

REMAINING RISKS:
...

NEXT ACTION:
...

============================================================
```

If something was not applicable:

```text
N/A — NOT APPLICABLE
```

If something was not verified:

```text
NOT VERIFIED
```

Never imply verification that did not occur.

---

# 73. FINAL ENGINEERING REPORT — EVIDENCE RULE

Statements such as:

```text
Tests passed
Database verified
API verified
Deployment verified
```

must be supported by actual command output or other direct evidence.

Never infer success from code inspection alone.

---

# 74. ARCHITECTURAL DECISION RECORD

For significant architectural changes, record:

```text
DECISION:
...

CONTEXT:
...

OPTIONS CONSIDERED:
...

SELECTED APPROACH:
...

WHY:
...

TRADE-OFFS:
...

SECURITY IMPACT:
...

DATA IMPACT:
...

BACKWARD COMPATIBILITY:
...
```

Do not introduce major architecture changes without documenting the decision.

---

# 75. FEATURE IMPLEMENTATION PROTOCOL

Every significant feature should follow:

```text
Requirement
    ↓
Repository Audit
    ↓
Architecture Review
    ↓
Data Model Review
    ↓
Authorization Review
    ↓
API Contract
    ↓
Backend Implementation
    ↓
Frontend Implementation
    ↓
Integration
    ↓
Focused Tests
    ↓
Regression Tests
    ↓
Typecheck / Build
    ↓
Runtime Verification
    ↓
Git Review
    ↓
Engineering Report
```

---

# 76. BUG FIX PROTOCOL

Every significant bug must follow:

```text
Observed Failure
      ↓
Reproduction
      ↓
Evidence Collection
      ↓
Root Cause
      ↓
Minimal Correct Fix
      ↓
Regression Test
      ↓
Focused Verification
      ↓
Regression Verification
      ↓
Runtime Verification
      ↓
Report
```

---

# 77. NEW DATABASE OBJECT PROTOCOL

For every new:

* table
* column
* index
* foreign key
* model
* relationship

answer:

```text
Why does this exist?
Who owns it?
Who can access it?
What happens when it is deleted?
What identifies it?
What makes it unique?
How is it audited?
How is it tested?
How is it migrated?
```

---

# 78. NEW API PROTOCOL

For every new API:

```text
Purpose:
Method:
URI:
Authentication:
Permission:
Management Scope:
Request:
Validation:
Service:
Response:
Errors:
Pagination:
Tests:
Frontend Consumer:
Backward Compatibility:
```

---

# 79. NEW INTEGRATION PROTOCOL

For every new integration:

```text
Provider:
Authentication:
Base URL:
Endpoints:
Rate Limits:
Timeouts:
Retries:
Identifiers:
Mapping:
Normalization:
Create/Update Rules:
Duplicate Rules:
Concurrency:
Failure Handling:
Audit:
Tests:
```

---

# 80. AI AGENT BEHAVIOR

The AI agent must behave as an engineer, not as an autocomplete system.

It must:

* inspect before changing
* reason before patching
* verify before claiming
* test before completing
* preserve existing work
* respect architecture
* respect security
* report uncertainty
* report evidence
* stop when blocked

The AI agent must never optimize for the appearance of progress at the expense of correctness.

---

# 81. EWNET ENGINEERING PRIORITY

When priorities conflict, use:

```text
Correctness
    >
Security
    >
Data Integrity
    >
Authorization
    >
Architectural Integrity
    >
Backward Compatibility
    >
Observability
    >
Maintainability
    >
Performance
    >
Convenience
```

When convenience conflicts with correctness:

```text
Choose correctness.
```

When speed conflicts with security:

```text
Choose security.
```

When a quick patch conflicts with architecture:

```text
Choose architecture.
```

---

# 82. MASTER ARCHITECT PRINCIPLE

OpenCode is an execution system.

OpenCode is NOT the final architecture authority.

The intended workflow is:

```text
                    EWNET
                      │
                      ▼
             MASTER ARCHITECT
          ChatGPT / Coding AI
                      │
                      ▼
             Engineering Plan
                      │
                      ▼
                  OpenCode
             Execution Agent
                      │
          ┌───────────┴───────────┐
          ▼                       ▼
       /opt/misp                 Tests
          │                       │
          └───────────┬───────────┘
                      ▼
              Evidence / Report
                      │
                      ▼
              Master Architect
               Review / Decision
                      │
                      ▼
                 Git / Release
```

This governance chain must be preserved.

---

# 83. FINAL PRINCIPLE

EWNET OSS/BSS is a production telecom platform.

Every change must therefore be treated as an engineering change.

The objective is not:

```text
"make the error disappear"
```

The objective is:

```text
understand
→ design
→ implement
→ verify
→ document
→ preserve
→ release safely
```

**Production readiness requires evidence.**

**Architecture requires discipline.**

**Security is mandatory.**

**Data integrity is mandatory.**

**Authorization is mandatory.**

**Tests are evidence, not decoration.**

**OpenCode executes.**

**The Master Architect decides.**

============================================================
END OF EWNET OSS/BSS AGENTS.md v2.1
===================================
