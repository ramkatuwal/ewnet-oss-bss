# EWNET OSS/BSS — ENVIRONMENT BASELINE

## Baseline Metadata
- **Audit Date:** August 27, 2026
- **Git Commit:** Root Baseline (Documentation Only)
- **Git Branch:** master

## Operating System
- **Distribution:** Ubuntu 24.04.4 LTS
- **Kernel:** Linux 6.8.0-138-generic
- **Architecture:** x86-64

## Hardware
- **CPU:** Intel(R) Xeon(R) Bronze 3104 CPU @ 1.70GHz (6 Cores, 1 Socket)
- **Memory:** 15Gi Total (1.5Gi Used, 14Gi Available)
- **Storage:** 48G Total (29G Used, 17G Free)

## Runtime
- **PHP:** 8.4.24
- **Laravel:** 13.26.1
- **Node.js:** v22.23.2
- **NPM:** 10.9.8
- **Vite:** 8.2.2

## Infrastructure
- **Docker:** Healthy (App, Horizon, Postgres, Redis, Web)
- **PostgreSQL:** 17 with PostGIS 3.5
- **Redis:** 7.4-alpine
- **Nginx:** 1.26-alpine

## Database Environment
- **Total Tables:** 23 Active Application Tables
- **Spatial Columns:** 0 (All spatial modules purged)
- **Migration Status:** Synchronized (0 Pending)

## Security Notes
- **Auth:** Sanctum (SPA + API)
- **CORS:** Configured for oss.ewnet.com.np
- **CSRF:** Protected via Sanctum Stateful Middleware
