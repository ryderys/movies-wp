#!/usr/bin/env bash
# Import a SQL dump into the production database and optionally replace site URLs.
#
# Run on the SERVER from project root:
#   bash scripts/import-db.sh backups/local.sql
#   bash scripts/import-db.sh backups/local.sql http://localhost/movies https://yourdomain.com
#
# Requires .env with DB_HOST, DB_NAME, DB_USER, DB_PASSWORD.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

COMPOSE_FILE="docker-compose.prod.yml"
COMPOSE_OVERRIDE="docker-compose.prod.external-db.yml"
ENV_FILE=".env"

log() { echo "[import] $*"; }
die() { echo "[import] ERROR: $*" >&2; exit 1; }

[[ $# -ge 1 ]] || die "Usage: bash scripts/import-db.sh DUMP.sql [OLD_URL NEW_URL]"
[[ -f "${ENV_FILE}" ]] || die "${ENV_FILE} missing."
[[ -f "$1" ]]       || die "Dump file not found: $1"

DUMP="$1"
OLD_URL="${2:-}"
NEW_URL="${3:-}"

set -a
# shellcheck disable=SC1090
source "${ENV_FILE}"
set +a

[[ -n "${DB_NAME:-}" ]]     || die "DB_NAME not set in .env"
[[ -n "${DB_USER:-}" ]]     || die "DB_USER not set in .env"
[[ -n "${DB_PASSWORD:-}" ]] || die "DB_PASSWORD not set in .env"

DB_HOST="${DB_HOST:-db}"

compose_args=( -f "${COMPOSE_FILE}" )
if [[ "${DB_HOST}" != "db" ]]; then
	compose_args+=( -f "${COMPOSE_OVERRIDE}" )
fi

import_via_mysql() {
	log "Importing into ${DB_NAME}@${DB_HOST}..."
	if [[ "${DUMP}" == *.gz ]]; then
		gunzip -c "${DUMP}" | mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}"
	else
		mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" <"${DUMP}"
	fi
}

import_via_docker() {
	log "Importing via Docker db container..."
	if [[ "${DUMP}" == *.gz ]]; then
		gunzip -c "${DUMP}" | docker compose "${compose_args[@]}" exec -T db \
			mysql -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}"
	else
		docker compose "${compose_args[@]}" exec -T db \
			mysql -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" <"${DUMP}"
	fi
}

if [[ "${DB_HOST}" == "db" ]]; then
	import_via_docker
else
	import_via_mysql
fi

log "Import finished."

if [[ -n "${OLD_URL}" && -n "${NEW_URL}" ]]; then
	log "Replacing URLs: ${OLD_URL} -> ${NEW_URL}"
	docker compose "${compose_args[@]}" exec -T wordpress \
		wp search-replace "${OLD_URL}" "${NEW_URL}" --all-tables --skip-columns=guid 2>/dev/null \
		|| log "WP-CLI not available in container — run search-replace manually after deploy."
fi

if [[ -n "${NEW_URL}" ]]; then
	log "Tip: set in .env:"
	log "  WP_HOME=${NEW_URL}"
	log "  WP_SITEURL=${NEW_URL}"
fi
