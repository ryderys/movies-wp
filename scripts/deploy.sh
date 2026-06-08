#!/usr/bin/env bash
# Deploy or update the Movies WordPress site on the production server.
#
# Run from the project root as the deploy user:
#   bash scripts/deploy.sh
#
# First-time setup:
#   1. cp .env.example .env   (or copy your production .env from a secure location)
#   2. cp deploy/Caddyfile.example deploy/Caddyfile
#   3. Edit .env and deploy/Caddyfile with your domain
#   4. bash scripts/deploy.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

COMPOSE_FILE="docker-compose.prod.yml"
COMPOSE_OVERRIDE="docker-compose.prod.external-db.yml"
ENV_FILE=".env"

log() { echo "[deploy] $*"; }
die() { echo "[deploy] ERROR: $*" >&2; exit 1; }

[[ -f "${COMPOSE_FILE}" ]] || die "${COMPOSE_FILE} not found. Run from project root."
[[ -f "${ENV_FILE}" ]]       || die "${ENV_FILE} missing. Copy .env.example to .env and fill in values."

if grep -qE '^WP_ENV=production' "${ENV_FILE}" && grep -qE '^WP_DEBUG=1' "${ENV_FILE}"; then
	die "WP_DEBUG is enabled in production .env. Set WP_DEBUG=0 before deploying."
fi

set -a
# shellcheck disable=SC1090
source "${ENV_FILE}"
set +a

DB_HOST="${DB_HOST:-db}"
compose_args=( -f "${COMPOSE_FILE}" )
profile_args=()

if [[ "${DB_HOST}" != "db" ]]; then
	log "External database detected (DB_HOST=${DB_HOST}) — using host network."
	compose_args+=( -f "${COMPOSE_OVERRIDE}" )
else
	log "Local Docker database (DB_HOST=db)."
	profile_args+=( --profile local-db )
fi

if [[ ! -f "deploy/Caddyfile" ]]; then
	log "No deploy/Caddyfile found — creating from example (HTTP on port 80)."
	cp deploy/Caddyfile.example deploy/Caddyfile
fi

if command -v caddy &>/dev/null && [[ -d /etc/caddy ]]; then
	CADDY_LINK="/etc/caddy/Caddyfile"
	if [[ "$(readlink -f deploy/Caddyfile 2>/dev/null || realpath deploy/Caddyfile)" != "$(readlink -f "${CADDY_LINK}" 2>/dev/null || echo '')" ]]; then
		log "Linking deploy/Caddyfile -> ${CADDY_LINK} (requires sudo)..."
		sudo ln -sf "${ROOT_DIR}/deploy/Caddyfile" "${CADDY_LINK}"
		sudo systemctl reload caddy 2>/dev/null || sudo systemctl restart caddy 2>/dev/null || true
	fi
fi

chmod 600 "${ENV_FILE}" 2>/dev/null || true
[[ -f "wp-content/.docker-salts.php" ]] && chmod 640 wp-content/.docker-salts.php 2>/dev/null || true

mkdir -p wp-content/uploads
chmod 755 wp-content/uploads

if [[ ! -d "wp-content/plugins" ]] || [[ -z "$(ls -A wp-content/plugins 2>/dev/null)" ]]; then
	log "WARNING: wp-content/plugins is empty."
	log "  Sync from your local machine: bash scripts/sync-to-server.sh USER@SERVER"
fi

if [[ ! -d "wp-content/uploads" ]] || [[ -z "$(ls -A wp-content/uploads 2>/dev/null)" ]]; then
	log "NOTE: wp-content/uploads is empty. Sync media before go-live:"
	log "  bash scripts/sync-to-server.sh USER@SERVER"
fi

log "Building and starting containers..."
docker compose "${compose_args[@]}" "${profile_args[@]}" up -d --build --remove-orphans

log "Waiting for WordPress to start..."
sleep 5

if docker compose "${compose_args[@]}" ps --status running | grep -q wordpress; then
	log "WordPress container is running."
else
	die "WordPress container failed to start. Check: docker compose ${compose_args[*]} logs"
fi

log "Testing HTTP on port 8080 (WordPress internal)..."
if curl -sf --max-time 10 http://127.0.0.1:8080/ >/dev/null 2>&1; then
	log "WordPress responds on http://127.0.0.1:8080"
else
	log "WordPress not yet responding on 8080 — check logs:"
	log "  docker compose ${compose_args[*]} logs wordpress --tail 50"
fi

echo ""
echo "=============================================="
echo " Deploy finished."
echo "=============================================="
echo ""
echo "  Site (via Caddy):  http://YOUR_SERVER_IP  or your domain"
echo "  WordPress direct:  http://127.0.0.1:8080  (localhost only)"
echo "  Logs:              docker compose ${compose_args[*]} logs -f"
echo ""
echo "Before go-live:"
echo "  [ ] Push from PC:     bash scripts/push-to-server.sh USER@SERVER --with-env --with-db"
echo "  [ ] Import database:  bash scripts/import-db.sh backups/local.sql OLD_URL NEW_URL"
echo "  [ ] Point DNS to this server"
echo "  [ ] Edit deploy/Caddyfile with your domain for HTTPS"
echo "  [ ] Set WP_HOME and WP_SITEURL in .env"
echo "  [ ] Set FORCE_SSL_ADMIN=1 after HTTPS works"
echo "  [ ] Schedule backups: bash scripts/backup-db.sh /var/backups/movies"
echo ""
