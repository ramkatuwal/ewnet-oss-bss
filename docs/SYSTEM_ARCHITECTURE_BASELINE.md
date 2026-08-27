# EWNET OSS/BSS — SYSTEM ARCHITECTURE BASELINE

## Baseline Metadata
- **Commit:** Root Baseline (Documentation Only)
- **Date:** August 27, 2026

## Technology Stack
- **Backend:** Laravel 13, PHP 8.4, PostgreSQL 17
- **Frontend:** React 18, TypeScript, Vite, MUI, TanStack Query, Zustand

## Model Inventory
- **Core:** User, Company, Region, Branch, Department

## Authorization Architecture
- **System:** Spatie Laravel Permission
- **Roles:** 1 (Super Admin)
- **Permissions:** 29 Active Permissions (Core Org, Security, Users)
- **Access Control:** All API routes protected by `auth:sanctum` middleware.
- **Policies:** Explicitly registered for Role, Permission, and User models.

## API Architecture
- **Version:** v1
- **Auth:** auth:sanctum middleware on all protected groups.
- **Routes:** Active Endpoints for Core Organization and Security.

## Frontend Architecture
- **State:** TanStack Query (Server), Zustand (Client)
- **Build Size:** ~549 kB
- **TypeScript:** 0 Errors

## Git Repository Structure
- **Type:** Documentation Only
- **Tracked Files:** `.gitignore`, `docs/ENVIRONMENT_BASELINE.md`, `docs/SYSTEM_ARCHITECTURE_BASELINE.md`
- **Remote:** git@github.com:ramkatuwal/ewnet-oss-bss.git

## Future Integration Rules
1. All new models must define organizational ownership (Company/Branch).
2. All API endpoints must be protected by Sanctum authentication.
3. No legacy Geography or FIM modules should be reintroduced without explicit authorization.
