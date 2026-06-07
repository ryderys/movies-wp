#!/usr/bin/env bash
# Export the WordPress database from the local Docker dev stack.
#
# Usage:
#   docker compose up -d
#   bash scripts/export-db-local.sh
#   bash scripts/export-db-local.sh backups/local-before-prod.sql
#
# Uses credentials from docker-compose.yml defaults unless you pass DB_* env vars
# or set USE_ENV=1 to load .env (use the LOCAL DEV block in .env, not production).

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

OUTPUT="${1:-${ROOT_DIR}/backups/local-$(date +%Y%m%d-%H%M%S).sql}"
mkdir -p "$(dirname "${OUTPUT}")"

log() { echo "[export] $*"; }
die() { echo "[export] ERROR: $*" >&2; exit 1; }

# Do NOT source .env by default — production .env points at the remote DB.
if [[ "${USE_ENV:-}" == "1" && -f .env ]]; then
	set -a
	# shellcheck disable=SC1091
	source .env
	set +a
fi

# Defaults match docker-compose.yml
DB_NAME="${DB_NAME:-local_wp}"
DB_USER="${DB_USER:-wp_user}"
DB_PASSWORD="${DB_PASSWORD:-wp_pass}"

docker_db_running() {
	docker compose ps --status running 2>/dev/null | grep -qE '\bdb\b'
}

export_db_docker() {
	log "Exporting from Docker db container (${DB_NAME})..."
	docker compose exec -T db \
		mysqldump \
			--single-transaction \
			--quick \
			-u "${DB_USER}" \
			-p"${DB_PASSWORD}" \
			"${DB_NAME}" >"${OUTPUT}"
}

export_db_xampp() {
	local mysqldump=""
	for candidate in \
		"${XAMPP_MYSQLDUMP:-}" \
		"/e/xampp/mysql/bin/mysqldump.exe" \
		"/c/xampp/mysql/bin/mysqldump.exe"; do
		if [[ -n "${candidate}" && -x "${candidate}" ]]; then
			mysqldump="${candidate}"
			break
		fi
	done
	[[ -n "${mysqldump}" ]] || die "XAMPP mysqldump not found."

	log "Exporting via XAMPP (${mysqldump})..."
	"${mysqldump}" \
		--host=127.0.0.1 \
		--user="${DB_USER}" \
		--password="${DB_PASSWORD}" \
		"${DB_NAME}" >"${OUTPUT}"
}

if docker_db_running; then
	export_db_docker
elif [[ "${USE_XAMPP:-}" == "1" ]]; then
	export_db_xampp
else
	die "Docker db container is not running. Start it with: docker compose up -d"
fi

log "Export complete: ${OUTPUT} ($(du -h "${OUTPUT}" | cut -f1))"
