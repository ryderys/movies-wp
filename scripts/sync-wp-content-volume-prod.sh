#!/usr/bin/env bash
set -euo pipefail

# Sync selected wp-content directories from the repo checkout into the production
# WordPress Docker named volume (wp_content), while excluding local CLI tests.
#
# Run on the server from the repo root:
#   chmod +x ./scripts/sync-wp-content-volume-prod.sh
#   ./scripts/sync-wp-content-volume-prod.sh
#
# Notes:
# - The production compose uses a named volume for /var/www/html/wp-content, so git pull
#   does not update live code by itself.
# - We intentionally exclude CLI tests:
#   - plugins/custom-plugins/movies-wp-media-automation/includes/tests
#   - themes/streamit-child/inc/tests

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
SERVICE="${SERVICE:-wordpress}"

if [[ ! -f "$COMPOSE_FILE" ]]; then
  echo "Missing compose file: $COMPOSE_FILE" >&2
  exit 1
fi

if [[ ! -d "wp-content" ]]; then
  echo "Missing wp-content directory in repo checkout." >&2
  exit 1
fi

echo "Syncing wp-content (excluding tests) into Docker volume..."

(
  cd wp-content
  tar -cf - \
    --exclude='plugins/custom-plugins/movies-wp-media-automation/includes/tests' \
    --exclude='themes/streamit-child/inc/tests' \
    plugins/custom-plugins/movies-wp-media-automation \
    themes/streamit-child
) | docker compose -f "$COMPOSE_FILE" exec -T "$SERVICE" tar -xf - -C /var/www/html/wp-content

echo "Fixing ownership to www-data:www-data..."
docker compose -f "$COMPOSE_FILE" exec -T "$SERVICE" sh -lc \
  "chown -R www-data:www-data \
    /var/www/html/wp-content/plugins/custom-plugins/movies-wp-media-automation \
    /var/www/html/wp-content/themes/streamit-child"

echo "Done."
