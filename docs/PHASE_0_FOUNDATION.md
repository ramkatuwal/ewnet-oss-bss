# Phase 0: Foundation & Technology Architecture

**Date:** August 27, 2026
**Project:** EWNET OSS/BSS (MISP)
**Working Directory:** `/opt/misp`

## Verified Technology Stack
- **OS:** Ubuntu 24.04 LTS
- **Runtime:** PHP 8.4
- **Framework:** Laravel 13
- **Database:** PostgreSQL 17 with PostGIS
- **Cache/Queue:** Redis 7.4
- **Queue Management:** Laravel Horizon
- **Authentication:** Laravel Sanctum
- **Authorization:** Spatie Laravel Permission
- **Frontend:** React 18, TypeScript, Vite, TanStack Query, Zustand
- **Infrastructure:** Docker, Nginx

## Architectural Principles
1. **API-First:** All business logic is exposed via strictly typed, versioned RESTful APIs.
2. **Secure-by-Default:** Deny by default; explicit authorization required for all mutations and data access.
3. **Organizational Ownership:** Every resource is bound to a strict Company → Region → Branch → Department hierarchy.
4. **Policy Authorization:** Laravel Gates/Policies are the single source of truth for access decisions.
5. **Query-Level Authorization:** Data retrieval is scoped at the query builder level to prevent IDOR/BOLA.
6. **Auditability:** All security-relevant mutations and access attempts are immutably logged.
7. **Database Integrity:** Foreign keys, unique constraints, and cascading rules are enforced at the PostgreSQL level.
8. **Observability:** Structured logging, correlation IDs, and health checks are mandatory.
9. **Future Hardware Abstraction:** The system is designed to eventually abstract physical network inventory without disrupting core OSS/BSS logic.
10. **Future Domain Modularity:** Strict boundaries prevent cross-domain leakage.
