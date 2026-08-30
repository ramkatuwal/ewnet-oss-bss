# LibreNMS API Integration — Technical Documentation

> Created as part of TASK-032. First provider implementation using the TASK-031 Integration Foundation.

## Overview

This integration connects EWNET OSS/BSS to a LibreNMS installation via its REST API (`/api/v0`). It synchronizes devices, ports, alerts, and pollers into EWNET's integration data store for future correlation with the FIM network inventory model (TASK-036).

**Important:** This integration does NOT write directly into FIM models. Data is stored in the `librenms_objects` table with preserved external identity for future correlation.

## Setup

### 1. Create Integration Entry

Navigate to System → Integrations → Add Integration:
- **Name:** e.g., "Production LibreNMS"
- **Provider:** `librenms`
- **Type:** `monitoring`
- **Endpoint:** Your LibreNMS base URL (e.g., `https://nms.example.com`)

### 2. Add API Token Credential

In the integration detail page → Credentials tab → Add Credential:
- **Type:** `api_token`
- **Value:** Your LibreNMS API token (created at `/api-access/` in LibreNMS)

The token is encrypted at rest using Laravel's `Crypt::encryptString()`. Only a masked hint is ever displayed.

### 3. Test Connection

Click "Test Connection" to verify:
- Endpoint reachable
- TLS valid
- Authentication valid
- LibreNMS API responding

### 4. Synchronize

Use sync controls to trigger:
- **Sync Devices** — Fetches all devices from `/api/v0/devices`
- **Sync Ports** — Iterates synced devices, fetches ports via `/api/v0/devices/:id/ports`
- **Sync Alerts** — Fetches alerts from `/api/v0/alerts`
- **Sync Pollers** — Fetches pollers from `/api/v0/pollers`
- **Full Sync** — Executes all four in order via Horizon queue

## Architecture


EWNET OSS/BSS
│
Integration Layer (TASK-031)
│
LibreNMS Provider (TASK-032)
│
LibreNMS REST API (/api/v0)
│
┌─────┼──────┬──────────┐
│ │ │ │
Devices Ports Alerts Pollers
│ │ │ │
└─────┴──────┴──────────┘
│
librenms_objects table
(external identity preserved)
│
Future: TASK-036 FIM Correlation


## API Endpoints Used

| LibreNMS Endpoint | Purpose | Auth |
|-------------------|---------|------|
| `GET /api/v0/ping` | Connection test | X-Auth-Token |
| `GET /api/v0/system` | Health check / version | X-Auth-Token |
| `GET /api/v0/devices` | Device discovery | X-Auth-Token |
| `GET /api/v0/devices/:id/ports` | Port discovery per device | X-Auth-Token |
| `GET /api/v0/alerts` | Alert retrieval | X-Auth-Token |
| `GET /api/v0/pollers` | Poller retrieval | X-Auth-Token |

## Data Storage

All synchronized objects are stored in `librenms_objects`:

| Column | Purpose |
|--------|---------|
| integration_id | Links to the Integration entry |
| object_type | device, port, alert, poller |
| external_id | LibreNMS device_id, port_id, alert_id, poller_id |
| external_parent_id | Parent device_id for ports |
| data | Full normalized JSON from LibreNMS API |
| display_name | hostname, ifName, alert name, poller name |
| status | up/down, ifOperStatus, severity |
| last_synced_at | Timestamp of last successful sync |

Unique constraint on `(integration_id, object_type, external_id)` ensures idempotency.

## Idempotency

Repeated synchronization does not create duplicates:
- Sync #1: Device 87 → created
- Sync #2: Device 87 → updated (if changed) or unchanged
- Sync #3: Device 87 → unchanged

## Error Handling

Handles: DNS failure, connection refused, timeout, TLS failure, 401, 403, 404, 429, 500, 502, 503, 504, malformed response.

Errors are logged without exposing credentials. Failed syncs are recorded in `integration_syncs` with sanitized error summaries.

## Security

- API tokens encrypted at rest via `Crypt::encryptString()`
- Tokens never returned in API responses (only masked hints)
- Tokens never logged or included in audit records
- Credential management requires `integrations.credentials.manage` permission
- All sync operations require `integrations.sync` permission
- Read-only integration — no write operations to LibreNMS

## Known Limitations

- No pagination support (LibreNMS API returns all results; large datasets handled via chunked device iteration for ports)
- No scheduled/cron sync (manual + API-triggered only)
- Port sync iterates devices sequentially (not parallel) to avoid rate limiting
- Poller endpoint may not be available on all LibreNMS versions
- No webhook receiver for real-time updates
- No direct LibreNMS database access (API only)
