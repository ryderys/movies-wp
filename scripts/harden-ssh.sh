#!/usr/bin/env bash
# Install SSH public keys and harden sshd for production.
# Run ON THE SERVER as root (once, before disabling password login).
#
# Option A — key from environment:
#   SSH_PUBLIC_KEY='ssh-ed25519 AAAA... comment' bash scripts/harden-ssh.sh
#
# Option B — key from gitignored local file (after push from your PC):
#   bash scripts/harden-ssh.sh deploy
#
# Option C — key file path:
#   bash scripts/harden-ssh.sh deploy /path/to/public-key.pub

set -euo pipefail

DEPLOY_USER="${1:-deploy}"
KEY_FILE="${2:-}"

if [[ "${EUID}" -ne 0 ]]; then
	echo "Run as root: sudo bash $0 [deploy_user] [key_file]" >&2
	exit 1
fi

if ! id "${DEPLOY_USER}" &>/dev/null; then
	useradd -m -s /bin/bash "${DEPLOY_USER}"
	usermod -aG sudo "${DEPLOY_USER}"
fi

install_key() {
	local key="$1"
	[[ -n "${key}" ]] || return 1
	# Reject private keys accidentally pasted.
	if [[ "${key}" == *"BEGIN OPENSSH PRIVATE KEY"* ]] || [[ "${key}" == *"BEGIN RSA PRIVATE KEY"* ]]; then
		echo "ERROR: This looks like a PRIVATE key. Paste only the public key (.pub)." >&2
		exit 1
	fi
	if [[ ! "${key}" =~ ^(ssh-ed25519|ssh-rsa|ecdsa-sha2-nistp256|ssh-ed25519-sk) ]]; then
		echo "ERROR: Unrecognized key type. Expected ssh-ed25519 or ssh-rsa public key." >&2
		exit 1
	fi

	local ssh_dir="/home/${DEPLOY_USER}/.ssh"
	local auth_keys="${ssh_dir}/authorized_keys"
	mkdir -p "${ssh_dir}"
	chmod 700 "${ssh_dir}"
	touch "${auth_keys}"
	chmod 600 "${auth_keys}"

	if grep -qF "${key%% *}" "${auth_keys}" 2>/dev/null; then
		echo "Key already installed for ${DEPLOY_USER}."
	else
		echo "${key}" >>"${auth_keys}"
		echo "Installed SSH key for ${DEPLOY_USER}."
	fi
	chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "${ssh_dir}"
}

resolve_key() {
	if [[ -n "${SSH_PUBLIC_KEY:-}" ]]; then
		install_key "${SSH_PUBLIC_KEY}"
		return
	fi
	if [[ -n "${KEY_FILE}" && -f "${KEY_FILE}" ]]; then
		install_key "$(grep -v '^#' "${KEY_FILE}" | head -1)"
		return
	fi
	local default_file
	default_file="$(cd "$(dirname "$0")/.." && pwd)/deploy/ssh-public-key"
	if [[ -f "${default_file}" ]]; then
		install_key "$(grep -v '^#' "${default_file}" | head -1)"
		return
	fi
	echo "No SSH key provided. Set SSH_PUBLIC_KEY, pass a key file, or create deploy/ssh-public-key" >&2
	exit 1
}

configure_sudoers() {
	local sudoers="/etc/sudoers.d/${DEPLOY_USER}-deploy"
	cat >"${sudoers}" <<'SUDOERS'
# Limited sudo for deploy user — not full NOPASSWD root.
Cmnd_Alias MOVIES_DOCKER = /usr/bin/docker
Cmnd_Alias MOVIES_CADDY  = /usr/bin/systemctl reload caddy, /usr/bin/systemctl restart caddy, /usr/bin/systemctl status caddy
deploy ALL=(ALL) NOPASSWD: MOVIES_DOCKER
deploy ALL=(ALL) NOPASSWD: MOVIES_CADDY
SUDOERS
	# Replace deploy username if different.
	if [[ "${DEPLOY_USER}" != "deploy" ]]; then
		sed -i "s/^deploy /${DEPLOY_USER} /" "${sudoers}"
	fi
	chmod 440 "${sudoers}"
	# Remove overly permissive sudoers if present.
	if [[ -f "/etc/sudoers.d/${DEPLOY_USER}" ]]; then
		rm -f "/etc/sudoers.d/${DEPLOY_USER}"
	fi
	echo "Configured limited sudo for ${DEPLOY_USER}."
}

harden_sshd() {
	local sshd_config="/etc/ssh/sshd_config"
	local dropin="/etc/ssh/sshd_config.d/99-movies-hardening.conf"
	mkdir -p /etc/ssh/sshd_config.d
	cat >"${dropin}" <<EOF
# Movies production hardening
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
ChallengeResponseAuthentication no
PubkeyAuthentication yes
AuthenticationMethods publickey
MaxAuthTries 3
LoginGraceTime 30
AllowUsers ${DEPLOY_USER}
ClientAliveInterval 300
ClientAliveCountMax 2
X11Forwarding no
AllowTcpForwarding no
EOF
	echo "SSHD hardening written to ${dropin}."
	echo "WARNING: Ensure your SSH key works before closing this session!"
	echo "         Test in a NEW terminal: ssh ${DEPLOY_USER}@this-server"
	if sshd -t 2>/dev/null; then
		systemctl reload sshd 2>/dev/null || systemctl reload ssh 2>/dev/null || true
		echo "SSHD reloaded."
	else
		echo "ERROR: sshd -t failed. Fix config before reloading." >&2
		exit 1
	fi
}

configure_fail2ban_ssh() {
	local jail="/etc/fail2ban/jail.d/movies-ssh.local"
	mkdir -p /etc/fail2ban/jail.d
	cat >"${jail}" <<'EOF'
[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600
findtime = 600
EOF
	systemctl enable --now fail2ban 2>/dev/null || true
	systemctl restart fail2ban 2>/dev/null || true
	echo "Fail2ban SSH jail enabled."
}

resolve_key
configure_sudoers
configure_fail2ban_ssh

# Only harden sshd when explicitly requested (avoid lockout during first setup).
if [[ "${HARDEN_SSHD:-}" == "1" ]]; then
	harden_sshd
else
	echo ""
	echo "SSHD hardening skipped (set HARDEN_SSHD=1 to disable password login)."
	echo "After confirming key login works, run:"
	echo "  HARDEN_SSHD=1 bash scripts/harden-ssh.sh ${DEPLOY_USER}"
fi

echo ""
echo "SSH key setup complete for ${DEPLOY_USER}."
