#!/usr/bin/env bash
# Sync wp-content (plugins, uploads) to the server.
# For a full project deploy, use push-to-server.sh instead.
#
#   bash scripts/sync-to-server.sh deploy@YOUR_SERVER_IP
#   bash scripts/push-to-server.sh deploy@YOUR_SERVER_IP --with-env --with-uploads --with-db

set -euo pipefail

if [[ $# -lt 1 ]]; then
	echo "Usage: bash scripts/sync-to-server.sh USER@SERVER [remote_path]"
	echo ""
	echo "For first-time server setup, prefer:"
	echo "  bash scripts/push-to-server.sh USER@SERVER --with-env --with-uploads --with-db"
	exit 1
fi

SERVER="$1"
REMOTE_PATH="${2:-/var/www/movies}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

to_wsl_path() {
	local p="$1"
	if [[ "${p}" =~ ^/([a-zA-Z])/(.*)$ ]]; then
		echo "/mnt/$(echo "${BASH_REMATCH[1]}" | tr 'A-Z' 'a-z')/${BASH_REMATCH[2]}"
	elif command -v cygpath &>/dev/null; then
		wsl wslpath -a "$(cygpath -w "${p}")"
	else
		wsl wslpath -a "${p}"
	fi
}

run_rsync() {
	if command -v rsync &>/dev/null; then
		rsync "$@"
	elif command -v wsl &>/dev/null && wsl command -v rsync &>/dev/null; then
		local wsl_root
		wsl_root="$(to_wsl_path "${ROOT_DIR}")"
		local args=()
		local arg
		for arg in "$@"; do
			case "${arg}" in
				"${ROOT_DIR}"/*) args+=("${arg/${ROOT_DIR}/${wsl_root}}") ;;
				"${ROOT_DIR}/") args+=("${wsl_root}/") ;;
				*) args+=("${arg}") ;;
			esac
		done
		wsl rsync "${args[@]}"
	else
		echo "ERROR: rsync not found. Install WSL or: choco install rsync" >&2
		exit 1
	fi
}

echo "==> Syncing plugins to ${SERVER}:${REMOTE_PATH}/wp-content/plugins/"
run_rsync -avz --progress \
	--exclude '.git' \
	"${ROOT_DIR}/wp-content/plugins/" \
	"${SERVER}:${REMOTE_PATH}/wp-content/plugins/"

echo "==> Syncing uploads to ${SERVER}:${REMOTE_PATH}/wp-content/uploads/"
run_rsync -avz --progress \
	"${ROOT_DIR}/wp-content/uploads/" \
	"${SERVER}:${REMOTE_PATH}/wp-content/uploads/"

echo "==> Done. On server: bash scripts/deploy.sh"
