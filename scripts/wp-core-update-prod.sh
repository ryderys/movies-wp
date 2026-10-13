#!/usr/bin/env bash
set -euo pipefail

# Update WordPress core inside the running Docker container (prod compose).
#
# Why: In this deployment, WP core lives in the image/container filesystem.
# Updating via wp-admin can revert on container recreate, and localized ZIPs
# (fa.wordpress.org) may 404. We force en_US core ZIP + keep Persian via lang packs.
#
# Usage:
#   ./scripts/wp-core-update-prod.sh 7.1.3
#   WP_COMPOSE_FILE=docker-compose.prod.yml ./scripts/wp-core-update-prod.sh 7.1.3
#   WP_SERVICE=wordpress ./scripts/wp-core-update-prod.sh 7.1.3
#
# Notes:
# - Runs WP-CLI with --skip-plugins/--skip-themes to avoid plugin/theme fatals.
# - Creates a writable WP-CLI cache dir inside the container.

WP_COMPOSE_FILE="${WP_COMPOSE_FILE:-docker-compose.prod.yml}"
WP_SERVICE="${WP_SERVICE:-wordpress}"
WP_VERSION="${1:-}"

if [[ -z "$WP_VERSION" ]]; then
  echo "Usage: $0 <wp_version>  (example: $0 7.1.3)" >&2
  exit 2
fi

dc() {
  docker compose -f "$WP_COMPOSE_FILE" "$@"
}

echo "==> Ensuring WP-CLI cache dir is writable in container..."
dc exec "$WP_SERVICE" sh -lc 'mkdir -p /var/www/.wp-cli/cache && chown -R www-data:www-data /var/www/.wp-cli'

echo "==> Updating WordPress core to ${WP_VERSION} (force en_US package)..."
dc exec -u www-data -e WP_CLI_CACHE_DIR=/var/www/.wp-cli/cache "$WP_SERVICE" \
  php wp-cli.phar core update --version="$WP_VERSION" --locale=en_US --force --skip-plugins --skip-themes

echo "==> Updating WordPress database (if needed)..."
dc exec -u www-data -e WP_CLI_CACHE_DIR=/var/www/.wp-cli/cache "$WP_SERVICE" \
  php wp-cli.phar core update-db --skip-plugins --skip-themes

echo "==> Updating core translations (Persian stays via language packs)..."
dc exec -u www-data -e WP_CLI_CACHE_DIR=/var/www/.wp-cli/cache "$WP_SERVICE" \
  php wp-cli.phar language core update --skip-plugins --skip-themes || true

echo "==> Verifying final running core version..."
dc exec "$WP_SERVICE" php -r 'include "wp-includes/version.php"; echo $wp_version, PHP_EOL;'

