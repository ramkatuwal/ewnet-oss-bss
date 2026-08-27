# ARCHITECTURE GATE 001 — BASELINE FREEZE

## 1. Production Source Location
- **Path:** `/opt/misp`
- **Backup:** `/opt/backups/ewnet-baseline-[DATE]`
- **Strategy:** Full tarball exclusion of `.git`, `node_modules`, and `vendor`.

## 2. Docker Configuration
- **File:** `docker-compose.yml` (Resolved version saved in backup)
- **Services:** App, Horizon, Postgres (PostGIS), Redis, Nginx.

## 3. Database State
- **Schema:** Dumped to `schema-only.sql` in backup.
- **Migrations:** Fully synchronized (0 Pending).

## 4. Environment Contract
- **Template:** `env-contract.template` (Secrets redacted).
- **Required Variables:** APP_KEY, DB_CONNECTION, REDIS_HOST, SANCTUM_STATEFUL_DOMAINS.

## 5. API Route Inventory
- **Total Routes:** Recorded in `api-route-inventory.txt`.
- **Protection:** All routes protected by `auth:sanctum`.

## 6. Permission/Role Matrix
- **Roles:** Super Admin.
- **Permissions:** 29 Active Permissions (Recorded in `permission-matrix.json`).

## 7. Authentication Behavior
- **Mechanism:** Laravel Sanctum.
- **Session:** Stateful for SPA; Token-based for API.
- **CSRF:** Protected via `EnsureFrontendRequestsAreStateful`.

## 8. Git Strategy
- **Current Repo:** Documentation Only (`ramkatuwal/ewnet-oss-bss.git`).
- **Application Source:** Managed locally at `/opt/misp` with regular backups.
- **Future Direction:** A separate private repository or branch strategy will be used for active application source code to maintain this clean architectural baseline.

## 9. Next Phase Directive
- **Sequence:** ORGANIZATION/RBAC HARDENING → AUDIT/OBSERVABILITY → API CONTRACT → CORE DOMAIN → FIM.

## 10. Final Verification
- **Permission Matrix:** Generated and saved in backup.
- **Backup Location:** `/opt/backups/ewnet-baseline-20260827`
- **Status:** All governance artifacts secured.
