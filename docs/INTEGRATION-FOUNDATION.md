# Integration Foundation — Technical Documentation

> Created as part of TASK-031. Provider-agnostic foundation for external system integrations.

## Architecture Overview

EWNET Core
│
└── Integrations Domain
├── Contracts (IntegrationProviderInterface)
├── Models (Integration, IntegrationCredential, IntegrationSync)
├── Services (IntegrationManager)
├── Jobs (RunIntegrationSync)
├── Controllers (IntegrationController, IntegrationCredentialController)
├── Requests (IntegrationRequest, IntegrationCredentialRequest)
├── Resources (IntegrationResource, IntegrationCredentialResource, IntegrationSyncResource)
└── Policies (IntegrationPolicy)


## How to Implement a Future Provider

1. Create provider class implementing `App\Contracts\IntegrationProviderInterface`
2. Register in a service provider: `IntegrationManager::register('librenms', LibreNmsProvider::class)`
3. Implement all contract methods: `identity()`, `displayName()`, `capabilities()`, `validateConfiguration()`, `testConnection()`, `healthCheck()`, `synchronize()`
4. Never store credentials directly — use `IntegrationCredential::setSecretValue()` which encrypts via Laravel Crypt
5. Never log or return plaintext credentials

## Credential Security

- All secret values encrypted at rest using `Crypt::encryptString()`
- Only masked hints (e.g., `************7F3A`) stored alongside encrypted values
- `encrypted_value` is in `$hidden` array — never serialized in API responses
- AuditService automatically strips `password`, `token`, `secret`, `api_key`, `credentials` from metadata
- Credential management requires `integrations.credentials.manage` permission (more restrictive than view)

## Health Check States

| State | Meaning |
|-------|---------|
| unknown | Not yet checked |
| pending | Awaiting first check |
| connected | Healthy |
| degraded | Partially functional |
| failed | Unreachable or erroring |
| disabled | Administratively disabled |

## Sync Framework

Syncs are queued via Horizon (`RunIntegrationSync` job). Bounded retries: 3 attempts, 60s backoff, 300s timeout. Results tracked in `integration_syncs` table with counts for processed/created/updated/unchanged/failed records.

## RBAC Permissions

| Permission | Description |
|------------|-------------|
| integrations.view | List/view integrations |
| integrations.create | Create new integrations |
| integrations.update | Update integration config |
| integrations.delete | Delete integrations |
| integrations.test | Test connection / health check |
| integrations.sync | Trigger synchronization |
| integrations.credentials.manage | Add/remove credentials |
| integrations.logs.view | View sync history |

## API Endpoints

| Method | Endpoint | Permission |
|--------|----------|------------|
| GET | /api/v1/integrations | integrations.view |
| POST | /api/v1/integrations | integrations.create |
| GET | /api/v1/integrations/{id} | integrations.view |
| PUT | /api/v1/integrations/{id} | integrations.update |
| DELETE | /api/v1/integrations/{id} | integrations.delete |
| POST | /api/v1/integrations/{id}/test | integrations.test |
| POST | /api/v1/integrations/{id}/health-check | integrations.test |
| POST | /api/v1/integrations/{id}/sync | integrations.sync |
| GET | /api/v1/integrations/{id}/syncs | integrations.logs.view |
| GET | /api/v1/integrations/{id}/credentials | integrations.credentials.manage |
| POST | /api/v1/integrations/{id}/credentials | integrations.credentials.manage |
| DELETE | /api/v1/integrations/{id}/credentials/{cid} | integrations.credentials.manage |
