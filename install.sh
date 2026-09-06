#!/bin/bash
# =====================================================================
# EWNET OSS/BSS — Automated single-command installation
#
# Provisions a fresh deployment from this repository to a running,
# fully-configured HTTPS stack. The ONLY configuration file you edit is
# `.env`; everything (domain, TLS, database, Redis) is derived from it.
#
#   Usage:  ./install.sh                 # interactive / production defaults
#           ./install.sh --help
#           ./install.sh --no-letsencrypt   # force self-signed TLS
#           ./install.sh --dry-run          # validate only; make no changes
#
# On failure it prints the relevant container logs and halts.
# =====================================================================

set -o errexit -o pipefail

# -------------------------------------------------------------------------
# Terminal helpers
# -------------------------------------------------------------------------
C_GREEN=$'\033[0;32m'; C_YELLOW=$'\033[0;33m'; C_RED=$'\033[0;31m'; C_CYAN=$'\033[0;36m'
C_BOLD=$'\033[1m'; C_RESET=$'\033[0m'

info()  { printf '%s[INFO ]%s %s\n'  "$C_CYAN"    "$C_RESET" "$*"; }
ok()    { printf '%s[  OK ]%s %s\n'  "$C_GREEN"   "$C_RESET" "$*"; }
warn()  { printf '%s[WARN ]%s %s\n'  "$C_YELLOW"  "$C_RESET" "$*"; }
fatal() { printf '%s[FAIL ]%s %s\n'  "$C_RED"    "$C_RESET" "$*"; exit 1; }

# -------------------------------------------------------------------------
# Usage / CLI flags
# -------------------------------------------------------------------------
DRY_RUN=0
FORCE_NO_LE=0
FRESH=0
DO_SEED=0

usage() {
    cat <<'EOF'
Usage: ./install.sh [OPTIONS]

EWNET OSS/BSS automated installer. Creates .env (if absent), provisions TLS,
builds and starts the Docker stack, and runs Laravel migrations+seeds.

Options:
  --dry-run              Validate prerequisites and .env only; make no changes.
  --fresh                Force migrate:fresh --seed (DESTRUCTIVE: drops all
                         tables before migrating/rebuilding data).
  --seed                 Seed roles/permissions/demo data after a standard
                         migrate (seeders are idempotent).
  --no-letsencrypt       Force self-signed TLS even for public domains.
  --help                 Show this help and exit.

Default database behavior:
  - empty/new database  ->  migrate:fresh --seed
  - populated database  ->  standard migrate (data is preserved)
EOF
}

for arg in "$@"; do
    case "$arg" in
        --help) usage; exit 0 ;;
        --dry-run)       DRY_RUN=1 ;;
        --fresh)         FRESH=1 ;;
        --seed)          DO_SEED=1 ;;
        --no-letsencrypt) FORCE_NO_LE=1 ;;
        *) fatal "Unknown option: $arg (run ./install.sh --help)" ;;
    esac
done

# -------------------------------------------------------------------------
# Small helpers
# -------------------------------------------------------------------------
_random_hex() { od -An -N"$1" -tx1 /dev/urandom 2>/dev/null | tr -d ' \n'; }

_gen_key() {
    if command -v openssl >/dev/null 2>&1; then openssl rand -base64 32
    else _random_hex 32; fi
}

_gen_pass() {
    if command -v openssl >/dev/null 2>&1; then openssl rand -hex 16
    else _random_hex 16; fi
}

_set_env() {
    local key=$1 val=$2 file=${3:-.env}
    if grep -q "^#\?${key}=" "$file" 2>/dev/null; then
        sed -i "s|^#\?${key}=.*|${key}=${val}|" "$file"
    else
        printf '%s=%s\n' "$key" "$val" >> "$file"
    fi
}

# Set only when really running; in --dry-run report what WOULD be written.
_maybe_set() {
    if [ "$DRY_RUN" -eq 1 ]; then
        warn "(dry-run) would write ${1}=${2}"
    else
        _set_env "$1" "$2"
    fi
}

_host_from_url() {
    printf '%s' "$1" | sed -E 's#^[a-z][a-z0-9+.-]*://##; s#[:/].*$##'
}

# -------------------------------------------------------------------------
# Prerequisite checks
# -------------------------------------------------------------------------
check_prereqs() {
    local dir
    dir=$(cd "$(dirname "$0")" && pwd)
    [ -f "$dir/docker-compose.yml" ] || fatal "docker-compose.yml not found. Run from the repository root."
    [ -f "$dir/Dockerfile" ]         || fatal "Dockerfile not found."

    command -v docker >/dev/null 2>&1 || fatal "Docker is not installed (https://docs.docker.com/engine/install/)."
    docker compose version >/dev/null 2>&1 || fatal "docker compose plugin not available."
    docker info >/dev/null 2>&1 || fatal "Docker daemon is not running (or the current user lacks permission)."
    command -v git >/dev/null 2>&1 || fatal "Git is not installed."
    ok "Docker and Git are installed."
}

# -------------------------------------------------------------------------
# .env provisioning (safe copy + fill secure defaults)
# -------------------------------------------------------------------------
ensure_env() {
    local dir; dir=$(cd "$(dirname "$0")" && pwd); cd "$dir"

    if [ ! -f .env ]; then
        if [ "$DRY_RUN" -eq 1 ]; then
            warn "(dry-run) .env missing — a real run would create it from .env.example with secure defaults."
            return 0
        fi
        info "No .env found — creating one from .env.example with secure defaults."
        cp .env.example .env
        chmod 600 .env
    else
        warn ".env already exists — keeping existing values."
    fi

    # Make current .env available to this shell (for compose + template rendering)
    set -a; . ./.env; set +a

    # --- APP_DOMAIN ---
    # Effective domain: explicit value, or derived from APP_URL (legacy .env).
    # Used for APP_URL/SESSION_DOMAIN/SANCTUM defaults and SSL_MODE choice.
    local domain
    if [ -z "${APP_DOMAIN:-}" ]; then
        if [ -n "${APP_URL:-}" ]; then domain=$(_host_from_url "$APP_URL"); else domain=localhost; fi
        if [ "$DRY_RUN" -eq 1 ]; then
            warn "(dry-run) would write APP_DOMAIN=${domain}"
        else
            _set_env APP_DOMAIN "$domain"
            set -a; . ./.env; set +a
        fi
    else
        domain=$APP_DOMAIN
    fi

    # --- APP_KEY ------------------------------------------------------
    if [ -z "${APP_KEY:-}" ]; then
        info "No APP_KEY set — generating a random application key."
        _maybe_set APP_KEY "base64:$(_gen_key)"
        set -a; . ./.env; set +a
        warn "APP_KEY written. Containers get this value via compose."
    fi

    # --- APP_URL / session / sanctum ----------------------------------
    [ "$domain" = "localhost" ] && warn "APP_DOMAIN is localhost — serving at https://localhost (browsers will see a cert warning)."
    [ -z "${APP_URL:-}" ] && _maybe_set APP_URL "https://${domain}"
    [ -z "${SESSION_DOMAIN:-}" ] && [ "$domain" != "localhost" ] && _maybe_set SESSION_DOMAIN ".${domain}"
    [ -z "${SANCTUM_STATEFUL_DOMAINS:-}" ] && _maybe_set SANCTUM_STATEFUL_DOMAINS "${domain},localhost,127.0.0.1"
    set -a; . ./.env; set +a

    # --- DB_PASSWORD ---------------------------------------------------
    if [ -z "${DB_PASSWORD:-}" ] || [ "${DB_PASSWORD:-}" = "ewnet123" ]; then
        _maybe_set DB_PASSWORD "$(_gen_pass)"
        set -a; . ./.env; set +a
        info "Generated a fresh DB_PASSWORD."
    fi

    # --- REDIS_PASSWORD -----------------------------------------------
    if [ -z "${REDIS_PASSWORD:-}" ]; then
        _maybe_set REDIS_PASSWORD "$(_gen_pass)"
        set -a; . ./.env; set +a
        info "Generated a fresh REDIS_PASSWORD."
    fi

    # --- SSL_MODE default ---------------------------------------------
    if [ -z "${SSL_MODE:-}" ]; then
        if [ "${FORCE_NO_LE:-0}" -eq 1 ] || [ "$domain" = "localhost" ]; then
            _maybe_set SSL_MODE self-signed
        else
            _maybe_set SSL_MODE letsencrypt
        fi
        set -a; . ./.env; set +a
    fi
}

# -------------------------------------------------------------------------
# TLS / SSL provisioning
# -------------------------------------------------------------------------
provision_tls() {
    if [ "$SSL_MODE" = "self-signed" ] || [ "$SSL_MODE" = "off" ]; then
        info "SSL_MODE=$SSL_MODE — the web container auto-generates a self-signed certificate on start."
        return
    fi

    if [ "$SSL_MODE" = "manual" ]; then
        warn "SSL_MODE=manual — place fullchain.pem/privkey.pem into NGINX_CERTS_SOURCE before starting the stack."
        return
    fi

    if [ "$SSL_MODE" = "letsencrypt" ]; then
        _provision_letsencrypt
        return
    fi

    fatal "Unknown SSL_MODE '$SSL_MODE' (expected self-signed | letsencrypt | manual)."
}

_provision_letsencrypt() {
    local domain
    domain=${APP_DOMAIN:-localhost}
    [ "$domain" = "localhost" ] && fatal "Cannot request a Let's Encrypt certificate for 'localhost'. Set APP_DOMAIN to a public domain."

    if [ -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ]; then
        info "Existing Let's Encrypt certificate for ${domain} — reusing it."
    else
        if ! command -v certbot >/dev/null 2>&1; then
            info "certbot not found — installing it now (requires root)."
            [ "$(id -u)" -eq 0 ] || fatal "Cannot install certbot as non-root. Install certbot manually, or set SSL_MODE=self-signed."
            if command -v apt-get >/dev/null 2>&1; then
                apt-get update -qq && apt-get install -y -qq certbot >/dev/null
            else
                fatal "Could not install certbot (no apt-get). Set SSL_MODE=self-signed, or install certbot manually."
            fi
        fi

        info "Requesting a Let's Encrypt certificate for ${domain} (standalone, port 80 must be free)."
        mkdir -p ./public/.well-known/acme-challenge

        certbot certonly --standalone \
            -d "$domain" \
            --non-interactive --agree-tos --register-unsafely-without-email \
            --keep-until-expiring || fatal "Let's Encrypt issuance failed. Check DNS / port 80, or set SSL_MODE=self-signed."

        info "Certificate obtained."
    fi

    _set_env NGINX_SSL_CERT_PATH "/etc/letsencrypt/live/${domain}/fullchain.pem"
    _set_env NGINX_SSL_KEY_PATH  "/etc/letsencrypt/live/${domain}/privkey.pem"
    _set_env NGINX_CERTS_SOURCE  "/etc/letsencrypt"
    set -a; . ./.env; set +a
    info "Nginx configured to use certificates from /etc/letsencrypt/live/${domain}/."
}

# -------------------------------------------------------------------------
# Docker stack
# -------------------------------------------------------------------------
up_stack() {
    info "Building and starting the container stack (this can take several minutes)..."
    docker compose up -d --build
    ok "Container stack launched."
}

_wait_for_condition() {
    local name=$1
    shift
    local max_attempts=${1:-60}
    local i=0
    until "$@" >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -ge "$max_attempts" ]; then
            fatal_and_logs "Timed out waiting for ${name} to become ready."
        fi
        sleep 5
    done
    ok "${name} is ready."
}

fatal_and_logs() {
    echo
    warn "Printing container diagnostics before aborting..."
    echo "--- docker compose ps ---"
    docker compose ps 2>&1 || true
    for svc in app postgres redis web horizon; do
        echo "--- logs: ${svc} (last 60 lines) ---"
        docker compose logs --tail=60 "$svc" 2>&1 || true
    done
    echo
    fatal "$1"
}

wait_for_stack() {
    info "Waiting for container healthchecks to pass..."

    _wait_for_condition "PostgreSQL" 24 sh -c \
        "docker compose exec -T postgres pg_isready -U ${DB_USERNAME:-ewnet} -d ${DB_DATABASE:-ewnet} >/dev/null 2>&1"

    if [ -n "${REDIS_PASSWORD:-}" ]; then
        _wait_for_condition "Redis" 12 sh -c \
            "docker compose exec -T redis redis-cli --no-auth-warning -a \"${REDIS_PASSWORD}\" ping 2>&1 | grep -q PONG"
    else
        _wait_for_condition "Redis" 12 sh -c \
            "docker compose exec -T redis redis-cli ping 2>&1 | grep -q PONG"
    fi

    # Wait for the app container to be fully booted (artisan reachable)
    _wait_for_condition "Laravel app" 12 sh -c \
        "docker compose exec -T app php artisan about --no-interaction >/dev/null 2>&1"

    ok "All services are running."
}

# -------------------------------------------------------------------------
# Application initialization
# -------------------------------------------------------------------------
app_exec() { docker compose exec -T app "$@"; }

install_deps() {
    info "Installing production PHP dependencies..."
    app_exec composer install --no-interaction --optimize-autoloader --no-dev

    info "Checking for frontend assets (built by the Dockerfile)..."
    if app_exec sh -c 'test -d public/build' >/dev/null 2>&1; then
        ok "Frontend assets present."
    else
        warn "public/build is missing — running npm build (ensure Node is available)."
        app_exec sh -c 'npm ci --legacy-peer-deps && npm run build'
    fi
}

migrate_and_seed() {
    # Count users directly via psql so we don't depend on dev-only tooling
    # (tinker/psysh) being present in the production image.
    local user_count
    user_count=$(docker compose exec -T postgres psql \
        -U "${DB_USERNAME:-ewnet}" -d "${DB_DATABASE:-ewnet}" \
        -tAc 'SELECT COUNT(*) FROM users;' 2>/dev/null | tr -d ' \r\n' || true)

    if [ "$FRESH" -eq 1 ] || [ -z "$user_count" ] || [ "$user_count" -le 1 ]; then
        if [ "$FRESH" -eq 1 ] && [ -n "$user_count" ] && [ "$user_count" -gt 1 ]; then
            warn "--fresh requested — dropping a database with ${user_count} existing user rows."
        fi
        info "Fresh/empty database detected — running migrate:fresh --seed (drops all tables)."
        app_exec php artisan migrate:fresh --seed --force
        ok "Database migrated and seeded."
    else
        info "Database already has data (${user_count} users) — running non-destructive migrate."
        app_exec php artisan migrate --force
        if [ "$DO_SEED" -eq 1 ]; then
            info "Seeding roles/permissions/demo data (idempotent)."
            app_exec php artisan db:seed --force
        fi
        ok "Migration complete."
    fi
}

optimize() {
    app_exec php artisan storage:link 2>/dev/null || true
    app_exec php artisan config:cache
    app_exec php artisan route:cache
    app_exec php artisan view:cache
    app_exec php artisan event:cache 2>/dev/null || true
    ok "Application caches warmed."
}

# -------------------------------------------------------------------------
# Main
# -------------------------------------------------------------------------
main() {
    echo
    info "${C_BOLD}EWNET OSS/BSS — Installer${C_RESET}"
    echo

    check_prereqs

    if [ "$DRY_RUN" -eq 1 ]; then
        ensure_env
        ok "Dry-run complete: prerequisites pass, .env is valid."
        exit 0
    fi

    ensure_env
    provision_tls
    up_stack
    wait_for_stack
    install_deps
    migrate_and_seed
    optimize

    echo
    ok "${C_BOLD}Installation complete.${C_RESET}"
    echo
    info "Site:    ${APP_URL:-https://${APP_DOMAIN:-localhost}}"
    info "Horizon: ${APP_URL:-https://${APP_DOMAIN:-localhost}}/${HORIZON_PATH:-horizon}"
    info "Admin credentials are created by the seeder (see INSTALLATION.md)."
    echo
}

main "$@"