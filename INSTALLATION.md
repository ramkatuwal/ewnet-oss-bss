# EWNET OSS/BSS — Installation Guide

Complete instructions for deploying EWNET OSS/BSS from a clean Ubuntu/Debian server to a fully operational HTTPS stack.

**Single rule:** The **only** file you edit is `.env`. All configuration — domain, TLS, database, Redis, and application keys — is derived from that one file.

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Quick install (automated)](#2-quick-install-automated)
3. [Manual install (step-by-step)](#3-manual-install-step-by-step)
4. [The .env file — variable reference](#4-the-env-file--variable-reference)
5. [TLS certificates](#5-tls-certificates)
6. [Maintenance & operations](#6-maintenance--operations)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. Prerequisites

| Requirement | Minimum version | Notes |
|-------------|----------------|-------|
| **Ubuntu/Debian** | 22.04 LTS | Other Linux distributions work if Docker and Docker Compose are installed. |
| **Docker Engine** | 24.x+ | [Install docs](https://docs.docker.com/engine/install/) |
| **Docker Compose plugin** | 2.x+ | Included with Docker Desktop or install via `apt-get install docker-compose-plugin` |
| **Git** | any | `apt-get install git` |
| **Ports 80 & 443** | free | Must not be occupied by another web server on the target host. |
| **DNS (production)** | A record pointing to the host | Required for Let's Encrypt certificates. A staging/private DNS setup is fine for internal deployments. |

Verify the basics:

```bash
docker --version        # Docker version 24+
docker compose version  # Docker Compose version v2+
git --version
```

---

## 2. Quick install (automated)

For a fresh server, run this single command (from the cloned repository):

```bash
git clone https://github.com/ramkatuwal/ewnet-oss-bss.git /opt/misp
cd /opt/misp
chmod +x install.sh
./install.sh
```

The script will:

1. Copy `.env.example` to `.env` with secure random passwords and app key.
2. Ask (or set defaults for) your domain name.
3. Provision TLS (auto self-signed for localhost; Let's Encrypt for public domains).
4. Build the Docker image (PHP-FPM, Nginx, PostgreSQL+PostGIS, Redis).
5. Wait for PostgreSQL and Redis health checks.
6. Run Laravel migrations + seed roles/permissions and the default admin user.
7. Cache application config for production performance.

### Useful flags

| Flag | Effect |
|------|--------|
| `--help` | Show usage and exit. |
| `--dry-run` | Validate prerequisites and `.env` without starting anything. |
| `--fresh` | Force `migrate:fresh --seed` (**DESTRUCTIVE**: drops all tables). |
| `--seed` | Re-seed roles/permissions/demo data after a standard migrate (idempotent `firstOrCreate`). |
| `--no-letsencrypt` | Force self-signed TLS even on public domains. |

Database behavior by default: an empty/new database runs `migrate:fresh --seed`;
a database that already contains data runs a standard `migrate --force` and is
**not** seeded or dropped unless you pass `--seed` / `--fresh`.

---

## 3. Manual install (step-by-step)

Use this when you prefer full control or need to customize.

### 3.1 Clone and create .env

```bash
git clone https://github.com/ramkatuwal/ewnet-oss-bss.git /opt/misp
cd /opt/misp
cp .env.example .env
chmod 600 .env
```

### 3.2 Edit .env

Open `.env` in your editor and set the values for your environment. The **minimum** changes for a production deployment:

```ini
APP_DOMAIN=your-domain.com
APP_URL=https://your-domain.com
APP_KEY=                              # leave empty — install.sh generates this
APP_ENV=production
APP_DEBUG=false
DB_PASSWORD=your-strong-db-password
REDIS_PASSWORD=your-strong-redis-password
SESSION_DOMAIN=.your-domain.com
SANCTUM_STATEFUL_DOMAINS=your-domain.com,localhost,127.0.0.1
SSL_MODE=self-signed                  # or letsencrypt, or manual
```

> **Tip:** For localhost development, most values can stay as their `.env.example` defaults — only `DB_PASSWORD` and `REDIS_PASSWORD` should be regenerated.

### 3.3 Build and start the stack

```bash
docker compose up -d --build
```

### 3.4 Wait for services to be healthy

```bash
# Watch the logs until you see postgres accepting connections:
docker compose logs -f postgres
# (Ctrl-C to stop watching when you see "database system is ready to accept connections")
```

### 3.5 Generate application key and optimize

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan storage:link
```

### 3.6 Migrate and seed

```bash
# For a brand new database:
docker compose exec app php artisan migrate:fresh --seed --force

# To add seed data without dropping tables (safe for existing databases):
docker compose exec app php artisan db:seed --force
```

### 3.7 Verify

Open `https://your-domain.com` in a browser. You should see the EWNET login screen.

---

## 4. The .env file — variable reference

All variables below are read by the stack. Defaults (from `.env.example`) are shown in parentheses.

### Application

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_NAME` | `EWNET OSS/BSS` | Application name (shown in browser tab). |
| `APP_ENV` | `production` | Laravel environment. `local` for dev. |
| `APP_KEY` | *(empty)* | Cryptographic key. **Must** be generated (`install.sh` does this). |
| `APP_DEBUG` | `false` | Show detailed errors. **Never** `true` in production. |
| `APP_URL` | `https://localhost` | Full public URL (scheme + domain, no trailing `/`). |
| `APP_DOMAIN` | `localhost` | Root domain. Drives Nginx `server_name`, TLS paths, cookie domains. |
| `APP_LOCALE` | `en` | Default locale. |
| `APP_FALLBACK_LOCALE` | `en` | Fallback locale. |
| `APP_FAKER_LOCALE` | `en_US` | Faker locale for development seeds. |

### Database (PostgreSQL + PostGIS)

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_CONNECTION` | `pgsql` | Database driver. Do not change. |
| `DB_HOST` | `postgres` | Docker service name (do not change unless using external DB). |
| `DB_PORT` | `5432` | Container-internal PostgreSQL port. |
| `DB_DATABASE` | `ewnet` | Database name. |
| `DB_USERNAME` | `ewnet` | Database user. |
| `DB_PASSWORD` | `ewnet123` | Database password. `install.sh` generates a random value. |
| `DB_HOST_PORT` | `5432` | Host-exposed port (`127.0.0.1:<port>`). Change if 5432 is in use on the host. |

### Redis / Cache / Queue / Sessions

| Variable | Default | Description |
|----------|---------|-------------|
| `REDIS_HOST` | `redis` | Docker service name. |
| `REDIS_PORT` | `6379` | Redis port. |
| `REDIS_PASSWORD` | *(empty)* | Redis `requirepass`. `install.sh` generates a random value when left empty. |
| `REDIS_DB` | `0` | Redis database for cache/sessions. |
| `REDIS_CACHE_DB` | `1` | Separate Redis database for cache. |
| `REDIS_HOST_PORT` | `6379` | Host-exposed Redis port (`127.0.0.1:<port>`). |
| `CACHE_STORE` | `redis` | Cache driver. |
| `CACHE_PREFIX` | `ewnet` | Cache key prefix. |
| `QUEUE_CONNECTION` | `redis` | Queue driver. |
| `REDIS_QUEUE_RETRY_AFTER` | `90` | Seconds before a stuck job is retried. |
| `SESSION_DRIVER` | `redis` | Session driver. |
| `SESSION_LIFETIME` | `120` | Session lifetime in minutes. |
| `SESSION_DOMAIN` | `.localhost` | Cookie domain. Dot-prefixed = all subdomains. |
| `SESSION_SECURE_COOKIE` | `true` | Only send cookies over HTTPS. |
| `SESSION_SAME_SITE` | `lax` | Cookie SameSite policy. |
| `BROADCAST_CONNECTION` | `log` | Broadcast driver. |

### Authentication / Horizon

| Variable | Default | Description |
|----------|---------|-------------|
| `SANCTUM_STATEFUL_DOMAINS` | `localhost` | Comma-separated hosts allowed stateful SPA auth. |
| `HORIZON_DOMAIN` | *(empty)* | Horizon dashboard domain filter. |
| `HORIZON_PATH` | `horizon` | Horizon dashboard URL path. |

### Logging

| Variable | Default | Description |
|----------|---------|-------------|
| `LOG_CHANNEL` | `stack` | Log transport. |
| `LOG_LEVEL` | `debug` | Minimum severity logged (`debug` → `error`). |

### TLS / SSL

| Variable | Default | Description |
|----------|---------|-------------|
| `SSL_MODE` | `self-signed` | `self-signed` / `letsencrypt` / `manual` / `off`. |
| `NGINX_SSL_CERT_PATH` | `/etc/nginx/certs/fullchain.pem` | Container-internal certificate path. |
| `NGINX_SSL_KEY_PATH` | `/etc/nginx/certs/privkey.pem` | Container-internal key path. |
| `NGINX_CERTS_SOURCE` | `certs_data` | Certificate source: named Docker volume (`certs_data`) or a host directory containing `fullchain.pem` + `privkey.pem`. |

> **Password note:** If you change `DB_PASSWORD` or `REDIS_PASSWORD` on an **existing** running stack, the containers get the new values on next `docker compose up -d` but PostgreSQL and Redis retain the old password in their data volume. You must also run `ALTER ROLE ewnet WITH PASSWORD 'new-password';` inside PostgreSQL (or start from a fresh volume).

---

## 5. TLS certificates

### Option A: Self-signed (default, zero-config)

```ini
SSL_MODE=self-signed
```

The Nginx container generates a 825-day self-signed certificate automatically on first start. Browsers will show a warning — this is expected and correct for development or internal networks. No extra steps required.

### Option B: Let's Encrypt (production with a public domain)

1. Ensure DNS for your domain points to the server and port 80 is reachable from the internet.
2. Set your domain in `.env`:

```ini
APP_DOMAIN=your-domain.com
SSL_MODE=letsencrypt
```

3. Run `./install.sh` (or if already running: `docker compose down && ./install.sh`).

`install.sh` will use `certbot` to obtain a certificate via the ACME webroot challenge and place it at `/etc/letsencrypt/live/your-domain.com/`. Renewals are automatic via certbot's systemd timer (Ubuntu) or cron job.

To renew manually at any time:

```bash
certbot renew
docker compose exec web nginx -s reload
```

### Option C: Manual certificates (bring your own)

```ini
SSL_MODE=manual
NGINX_CERTS_SOURCE=/path/to/your/certs
```

Place your `fullchain.pem` and `privkey.pem` in the directory pointed to by `NGINX_CERTS_SOURCE`. The directory is bind-mounted into the `web` container at `/etc/nginx/certs/`.

---

## 6. Maintenance & operations

### Starting and stopping

```bash
# Start the stack
docker compose up -d

# Stop (preserves data volumes)
docker compose down

# Rebuild images (e.g., after Dockerfile changes)
docker compose build --no-cache
docker compose up -d --build

# Full reset (WARNING: destroys all data)
docker compose down -v
```

### Laravel artisan commands

All artisan commands run inside the app container:

```bash
docker compose exec app php artisan <command>
```

Common commands:

```bash
php artisan migrate               # run pending migrations
php artisan db:seed               # run seeders (safe: uses firstOrCreate)
php artisan route:list            # list registered routes
php artisan horizon:terminate     # gracefully restart Horizon workers
```

### Logs

```bash
# Follow all services
docker compose logs -f

# Follow a specific service
docker compose logs -f app
docker compose logs -f postgres

# Last 100 lines
docker compose logs --tail=100 app
```

Application logs (when `LOG_CHANNEL=stack`) are written to `storage/logs/laravel.log` and also streamed to Docker's JSON log driver.

### Backups

```bash
# Database dump
docker compose exec postgres pg_dump -U ewnet ewnet > backup_$(date +%F).sql

# Restore
cat backup_2026-09-06.sql | docker compose exec -T postgres psql -U ewnet ewnet
```

---

## 7. Troubleshooting

### Container won't start / exits immediately

```bash
docker compose ps                  # check status
docker compose logs --tail=50 web  # most common: Nginx fails if cert missing
```

If `web` exits with an Nginx SSL error, the certificate at `NGINX_SSL_CERT_PATH` does not exist. Fix:

```bash
# For self-signed: ensure SSL_MODE=self-signed and NGINX_CERTS_SOURCE is a writable volume
grep SSL_MODE .env
# Then recreate the web container:
docker compose up -d web
```

### PostgreSQL rejects connections

```bash
docker compose logs postgres | tail -20
```

Common causes:

- **Password mismatch:** If you changed `DB_PASSWORD` in `.env` after the volume was created, the existing postgres data volume still uses the old password. Fix: start from scratch (`docker compose down -v`) or `ALTER ROLE` inside postgres.
- **Health check failure:** The postgres container may be crash-looping. Check `docker compose logs postgres` for startup errors.

### Horizon not processing jobs

```bash
docker compose logs horizon | tail -30
docker compose exec app php artisan horizon:status
```

Horizon reads `.env` via the mounted volume. After changing `.env`, restart workers:

```bash
docker compose restart horizon
```

### Redis authentication errors

```bash
docker compose logs redis | tail -20
docker compose exec redis redis-cli -a "$REDIS_PASSWORD" ping
# Should return: PONG
```

If `PONG` is not returned, `REDIS_PASSWORD` in `.env` does not match what Redis was started with. Fix: `docker compose down -v` and re-`up` (destroys data volume).

### Nginx not serving the application (502 Bad Gateway)

The `fastcgi_pass app:9000` directive expects the `app` container to be running with PHP-FPM. Check:

```bash
docker compose ps
docker compose exec app php -r 'echo "PHP running\n";'
```

If the app container is up but PHP-FPM isn't listening, the container may have exited. Check `docker compose logs app`.

### Environment variable not taking effect

1. After editing `.env`, containers must be restarted for compose to inject new values:

```bash
docker compose up -d --force-recreate app horizon web
```

2. If `php artisan config:cache` has been run, cached config overrides `.env`. Clear the cache:

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan config:cache
```

### Useful diagnostic commands

```bash
# Full service status
docker compose ps -a

# Inspect a running container's environment
docker compose exec app env | sort | grep DB

# Connect to the database
docker compose exec postgres psql -U ewnet ewnet

# Connect to Redis
docker compose exec redis redis-cli -a "$REDIS_PASSWORD"

# Run a one-off artisan command
docker compose exec app php artisan about
```