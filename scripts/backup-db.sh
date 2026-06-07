#!/usr/bin/env bash
# Dump the WordPress MySQL database from production.
#
# Run from project root on the server:
#   bash scripts/backup-db.sh
#   bash scripts/backup-db.sh /var/backups/movies
#
# Works with:
#   - External DB (DB_HOST=moviestart) via host mysql client
#   - Docker db container (DB_HOST=db)

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

COMPOSE_FILE="docker-compose.prod.yml"
COMPOSE_OVERRIDE="docker-compose.prod.external-db.yml"
ENV_FILE=".env"
BACKUP_DIR="${1:-${ROOT_DIR}/backups}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"

log() { echo "[backup] $*"; }
die() { echo "[backup] ERROR: $*" >&2; exit 1; }

[[ -f "${COMPOSE_FILE}" ]] || die "${COMPOSE_FILE} not found."
[[ -f "${ENV_FILE}" ]]       || die "${ENV_FILE} missing."

set -a
# shellcheck disable=SC1090
source "${ENV_FILE}"
set +a

[[ -n "${DB_NAME:-}" ]]     || die "DB_NAME not set in .env"
[[ -n "${DB_USER:-}" ]]     || die "DB_USER not set in .env"
[[ -n "${DB_PASSWORD:-}" ]] || die "DB_PASSWORD not set in .env"

DB_HOST="${DB_HOST:-db}"
mkdir -p "${BACKUP_DIR}"
OUTPUT="${BACKUP_DIR}/db-${DB_NAME}-${TIMESTAMP}.sql.gz"

compose_args=( -f "${COMPOSE_FILE}" )
if [[ "${DB_HOST}" != "db" ]]; then
	compose_args+=( -f "${COMPOSE_OVERRIDE}" )
fi

if [[ "${DB_HOST}" == "db" ]]; then
	log "Dumping ${DB_NAME} via Docker db container..."
	docker compose "${compose_args[@]}" --profile local-db exec -T db \
		mysqldump \
			--single-transaction \
			--quick \
			-u "${DB_USER}" \
			-p"${DB_PASSWORD}" \
			"${DB_NAME}" \
		| gzip >"${OUTPUT}"
else
	log "Dumping ${DB_NAME}@${DB_HOST} via mysql client..."
	mysqldump \
		--host="${DB_HOST}" \
		--user="${DB_USER}" \
		--password="${DB_PASSWORD}" \
		--single-transaction \
		--quick \
		"${DB_NAME}" \
		| gzip >"${OUTPUT}"
fi

log "Backup complete: ${OUTPUT} ($(du -h "${OUTPUT}" | cut -f1))"

find "${BACKUP_DIR}" -name "db-${DB_NAME}-*.sql.gz" -mtime +14 -delete 2>/dev/null || true
