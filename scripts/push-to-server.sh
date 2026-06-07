#!/usr/bin/env bash
# Push the Movies project from your PC to the production server.
#
# Run from project root (Git Bash / WSL / Linux):
#   bash scripts/push-to-server.sh root@YOUR_SERVER_IP --with-env --with-uploads --with-db
#   bash scripts/push-to-server.sh deploy@YOUR_SERVER_IP   # after deploy user exists
#
# First-time: use root@ until scripts/harden-ssh.sh creates the deploy user.
#
# Options:
#   --with-env      Copy production .env
#   --with-uploads  Include wp-content/uploads
#   --with-db       Include backups/*.sql

set -euo pipefail

log() { echo "[push] $*"; }
die() { echo "[push] ERROR: $*" >&2; exit 1; }

if [[ $# -lt 1 ]]; then
	echo "Usage: bash scripts/push-to-server.sh USER@SERVER [remote_path] [options]"
	echo ""
	echo "First-time server (no deploy user yet):"
	echo "  bash scripts/push-to-server.sh root@YOUR_IP --with-env --with-uploads --with-db"
	echo ""
	echo "Options: --with-env  --with-uploads  --with-db"
	exit 1
fi

SERVER="$1"
shift

REMOTE_PATH="/var/www/movies"
WITH_ENV=0
WITH_UPLOADS=0
WITH_DB=0

while [[ $# -gt 0 ]]; do
	case "$1" in
		--with-env) WITH_ENV=1 ;;
		--with-uploads) WITH_UPLOADS=1 ;;
		--with-db) WITH_DB=1 ;;
		-*) die "Unknown option: $1" ;;
		*) REMOTE_PATH="$1" ;;
	esac
	shift
done

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

build_tar_excludes() {
	TAR_EXCLUDES=(
		--exclude='.git'
		--exclude='.env.local'
		--exclude='node_modules'
		--exclude='wp-content/cache'
		--exclude='.vscode'
		--exclude='.idea'
		--exclude='*.log'
		--exclude='wp-admin'
		--exclude='wp-includes'
		--exclude='wp-config.php'
	)
	if [[ "${WITH_ENV}" -eq 0 ]]; then
		TAR_EXCLUDES+=( --exclude='.env' )
	fi
	if [[ "${WITH_UPLOADS}" -eq 0 ]]; then
		TAR_EXCLUDES+=( --exclude='wp-content/uploads' )
	fi
	if [[ "${WITH_DB}" -eq 0 ]]; then
		TAR_EXCLUDES+=( --exclude='backups' )
	fi
}

push_via_tar() {
	build_tar_excludes
	log "Uploading project via tar+ssh (Git Bash compatible)..."
	ssh "${SERVER}" "mkdir -p '${REMOTE_PATH}'"
	tar czf - "${TAR_EXCLUDES[@]}" -C "${ROOT_DIR}" . \
		| ssh "${SERVER}" "tar xzf - -C '${REMOTE_PATH}'"
}

push_via_rsync() {
	local rsync_excludes=(
		--exclude '.git/'
		--exclude 'node_modules/'
		--exclude 'wp-content/cache/'
		--exclude 'wp-admin/'
		--exclude 'wp-includes/'
		--exclude 'wp-config.php'
	)
	[[ "${WITH_ENV}" -eq 0 ]] && rsync_excludes+=( --exclude '.env' )
	[[ "${WITH_UPLOADS}" -eq 0 ]] && rsync_excludes+=( --exclude 'wp-content/uploads/' )
	[[ "${WITH_DB}" -eq 0 ]] && rsync_excludes+=( --exclude 'backups/' )

	log "Syncing project files via rsync..."
	rsync -avz --progress \
		"${rsync_excludes[@]}" \
		"${ROOT_DIR}/" \
		"${SERVER}:${REMOTE_PATH}/"
}

log "Target: ${SERVER}:${REMOTE_PATH}"
ssh "${SERVER}" "mkdir -p '${REMOTE_PATH}'"

if command -v rsync &>/dev/null; then
	push_via_rsync
else
	log "rsync not found — using tar over SSH (uses your Git Bash SSH keys)."
	push_via_tar
fi

if [[ "${WITH_ENV}" -eq 1 ]]; then
	[[ -f "${ROOT_DIR}/.env" ]] || die ".env not found."
	if grep -qE '^WP_DEBUG=1' "${ROOT_DIR}/.env" && grep -qE '^WP_ENV=production' "${ROOT_DIR}/.env"; then
		die ".env has WP_DEBUG=1 with WP_ENV=production."
	fi
	log "Copying .env..."
	scp "${ROOT_DIR}/.env" "${SERVER}:${REMOTE_PATH}/.env"
	ssh "${SERVER}" "chmod 600 '${REMOTE_PATH}/.env'"
fi

echo ""
echo "=============================================="
echo " Push complete."
echo "=============================================="
echo ""
echo "On the server (ssh ${SERVER}):"
echo "  cd ${REMOTE_PATH}"
if [[ "${SERVER}" == root@* ]]; then
	echo "  bash scripts/setup-server.sh          # first time only"
	echo "  bash scripts/harden-ssh.sh deploy deploy/ssh-public-key"
fi
echo "  cp deploy/Caddyfile.example deploy/Caddyfile"
echo "  bash scripts/deploy.sh"
if [[ "${WITH_DB}" -eq 1 ]]; then
	echo "  bash scripts/import-db.sh backups/local.sql http://localhost https://yourdomain.com"
fi
if [[ "${SERVER}" == root@* ]]; then
	echo ""
	echo "After deploy user works: ssh deploy@YOUR_IP"
	echo "Then: sudo HARDEN_SSHD=1 bash scripts/harden-ssh.sh deploy"
fi
echo ""
