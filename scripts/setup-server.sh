#!/usr/bin/env bash
# Bootstrap a fresh Ubuntu/Debian VPS for the Movies WordPress project.
#
# Run ON THE SERVER as root (first-time setup only):
#   sudo bash scripts/setup-server.sh
#
# What this script does:
#   1. Configures Liara APT + Docker Hub mirrors (override via env vars)
#   2. Updates the system
#   3. Creates a deploy user (default: deploy)
#   4. Installs Docker + Docker Compose plugin + Caddy
#   5. Configures UFW firewall (SSH, HTTP, HTTPS)
#   6. Creates /var/www/movies app directory

set -euo pipefail

DEPLOY_USER="${DEPLOY_USER:-deploy}"
APP_DIR="${APP_DIR:-/var/www/movies}"
SSH_PORT="${SSH_PORT:-22}"

UBUNTU_MIRROR="${UBUNTU_MIRROR:-https://linux-mirror.liara.ir/repository/ubuntu}"
UBUNTU_SECURITY_MIRROR="${UBUNTU_SECURITY_MIRROR:-https://linux-mirror.liara.ir/repository/ubuntu-security}"
DEBIAN_MIRROR="${DEBIAN_MIRROR:-https://linux-mirror.liara.ir/repository/debian}"
DEBIAN_SECURITY_MIRROR="${DEBIAN_SECURITY_MIRROR:-https://linux-mirror.liara.ir/repository/debian-security}"
DOCKER_REGISTRY_MIRROR="${DOCKER_REGISTRY_MIRROR:-https://docker-mirror.liara.ir}"

configure_apt_mirrors() {
	echo "==> Configuring Liara APT mirrors..."

	if [[ -f /etc/apt/sources.list.d/ubuntu.sources ]]; then
		cp -a /etc/apt/sources.list.d/ubuntu.sources /etc/apt/sources.list.d/ubuntu.sources.bak
		sed -i \
			-e "s|https\\?://security.ubuntu.com/ubuntu|${UBUNTU_SECURITY_MIRROR}|g" \
			-e "s|https\\?://archive.ubuntu.com/ubuntu|${UBUNTU_MIRROR}|g" \
			/etc/apt/sources.list.d/ubuntu.sources
	elif [[ -f /etc/apt/sources.list.d/debian.sources ]]; then
		cp -a /etc/apt/sources.list.d/debian.sources /etc/apt/sources.list.d/debian.sources.bak
		sed -i \
			-e "s|https\\?://deb.debian.org/debian|${DEBIAN_MIRROR}|g" \
			-e "s|https\\?://deb.debian.org/debian-security|${DEBIAN_SECURITY_MIRROR}|g" \
			/etc/apt/sources.list.d/debian.sources
	elif [[ -f /etc/apt/sources.list ]]; then
		cp -a /etc/apt/sources.list /etc/apt/sources.list.bak
		sed -i \
			-e "s|https\\?://deb.debian.org/debian|${DEBIAN_MIRROR}|g" \
			-e "s|https\\?://deb.debian.org/debian-security|${DEBIAN_SECURITY_MIRROR}|g" \
			/etc/apt/sources.list
	fi
}

configure_docker_registry_mirror() {
	if ! command -v docker &>/dev/null; then
		return
	fi

	echo "==> Configuring Docker Hub registry mirror (${DOCKER_REGISTRY_MIRROR})..."
	mkdir -p /etc/docker
	cat >/etc/docker/daemon.json <<EOF
{
  "registry-mirrors": [
    "${DOCKER_REGISTRY_MIRROR}"
  ]
}
EOF
	systemctl daemon-reload
	systemctl restart docker
}

if [[ "${EUID}" -ne 0 ]]; then
	echo "Run as root: sudo bash $0" >&2
	exit 1
fi

configure_apt_mirrors

echo "==> Updating system packages..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq

echo "==> Installing base tools..."
apt-get install -y -qq \
	ca-certificates \
	curl \
	git \
	ufw \
	fail2ban \
	unattended-upgrades \
	debian-keyring \
	debian-archive-keyring \
	apt-transport-https

echo "==> Installing Caddy..."
if ! command -v caddy &>/dev/null; then
	curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
	curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
	apt-get update -qq
	apt-get install -y -qq caddy
fi

echo "==> Creating deploy user: ${DEPLOY_USER}..."
if ! id "${DEPLOY_USER}" &>/dev/null; then
	useradd -m -s /bin/bash "${DEPLOY_USER}"
	usermod -aG sudo "${DEPLOY_USER}"
fi

# Limited sudo — full hardening applied by scripts/harden-ssh.sh
if [[ -f "$(dirname "$0")/harden-ssh.sh" ]]; then
	bash "$(dirname "$0")/harden-ssh.sh" "${DEPLOY_USER}" 2>/dev/null || true
fi

echo "==> Installing Docker..."
if ! command -v docker &>/dev/null; then
	install -m 0755 -d /etc/apt/keyrings
	curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc 2>/dev/null \
		|| curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
	chmod a+r /etc/apt/keyrings/docker.asc

	. /etc/os-release
	DOCKER_REPO="${ID:-ubuntu}"
	if [[ "${DOCKER_REPO}" != "ubuntu" && "${DOCKER_REPO}" != "debian" ]]; then
		DOCKER_REPO="ubuntu"
	fi

	echo \
		"deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/${DOCKER_REPO} \
		${VERSION_CODENAME} stable" >/etc/apt/sources.list.d/docker.list

	apt-get update -qq
	apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-compose-plugin
fi

configure_docker_registry_mirror

usermod -aG docker "${DEPLOY_USER}"

echo "==> Configuring firewall (UFW)..."
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow "${SSH_PORT}/tcp" comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw --force enable

echo "==> Enabling fail2ban and unattended security upgrades..."
systemctl enable --now fail2ban
dpkg-reconfigure -plow unattended-upgrades || true

echo "==> Creating application directory: ${APP_DIR}..."
mkdir -p "${APP_DIR}"
chown "${DEPLOY_USER}:${DEPLOY_USER}" "${APP_DIR}"

echo ""
echo "=============================================="
echo " Server setup complete."
echo "=============================================="
echo ""
echo "Next steps:"
echo "  1. Log in as ${DEPLOY_USER}:  ssh ${DEPLOY_USER}@YOUR_SERVER_IP"
echo "  2. Add your SSH public key to ~${DEPLOY_USER}/.ssh/authorized_keys"
echo "  3. From your PC, push the project:"
echo "       bash scripts/push-to-server.sh ${DEPLOY_USER}@YOUR_SERVER_IP --with-env --with-uploads --with-db"
echo "  4. On the server:"
echo "       cd ${APP_DIR}"
echo "       cp deploy/Caddyfile.example deploy/Caddyfile   # edit domain"
echo "       bash scripts/deploy.sh"
echo "       bash scripts/import-db.sh backups/local.sql OLD_URL NEW_URL"
echo ""
echo "Security reminders:"
echo "  - Install SSH key: sudo bash scripts/harden-ssh.sh deploy deploy/ssh-public-key"
echo "  - After key login works: HARDEN_SSHD=1 sudo bash scripts/harden-ssh.sh deploy"
echo "  - Keep .env at chmod 600 — never commit it to git"
echo "  - Rotate DB password if it was ever shared in chat or logs"
echo ""
