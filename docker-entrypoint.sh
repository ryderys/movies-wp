#!/bin/bash
set -e

WP_ROOT="/var/www/html"
WP_CONFIG="${WP_ROOT}/wp-config.php"
WP_CONFIG_DOCKER="${WP_ROOT}/wp-config-docker.php"
SALTS_FILE="${WP_ROOT}/wp-content/.docker-salts.php"

if [ ! -f "$WP_CONFIG_DOCKER" ]; then
	echo "ERROR: ${WP_CONFIG_DOCKER} not found. Cannot start WordPress." >&2
	exit 1
fi

# Always sync: stale wp-config.php (e.g. from wp-config-sample.php) can duplicate salts.
echo "Syncing ${WP_CONFIG} from wp-config-docker.php"
cp "$WP_CONFIG_DOCKER" "$WP_CONFIG"
chown www-data:www-data "$WP_CONFIG"

if [ ! -f "$SALTS_FILE" ] && grep -qF '.docker-salts.php' "$WP_CONFIG" 2>/dev/null; then
	has_salt_env=false
	for key in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
		if [ -n "${!key:-}" ]; then
			has_salt_env=true
			break
		fi
	done

	if [ "$has_salt_env" = false ]; then
		echo "Generating WordPress salts at ${SALTS_FILE}"
		mkdir -p "$(dirname "$SALTS_FILE")"
		{
			echo '<?php'
			for key in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
				val="$(openssl rand -base64 64 | tr -d '\n')"
				printf "if ( ! defined( '%s' ) ) {\n" "$key"
				printf "\tdefine( '%s', '%s' );\n" "$key" "$val"
				printf "}\n"
			done
		} >"$SALTS_FILE"
		chown www-data:www-data "$SALTS_FILE"
		chmod 640 "$SALTS_FILE"
	fi
fi

exec docker-php-entrypoint "$@"
