#!/usr/bin/env bash
set -euo pipefail

# Sync selected wp-content paths from the repo checkout into the production
# WordPress Docker named volume (wp_content), while excluding local CLI tests.
#
# Run on the server from the repo root:
#   chmod +x ./scripts/sync-wp-content-volume-prod.sh
#   ./scripts/sync-wp-content-volume-prod.sh
#
# Notes:
# - The production compose uses a named volume for /var/www/html/wp-content, so git pull
#   does not update live code by itself.
# - Synced paths:
#   - plugins/custom-plugins/movies-wp-media-automation (skips includes/tests)
#   - themes/streamit-child (skips inc/tests)
#   - mu-plugins/0-stc-front-bootstrap-trim.php
# - Never sync the temporary profiler MU (0-stc-plugin-load-profiler.php).

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

TRIM_MU="wp-content/mu-plugins/0-stc-front-bootstrap-trim.php"
if [[ ! -f "$TRIM_MU" ]]; then
  echo "Missing required MU: $TRIM_MU" >&2
  exit 1
fi

echo "Syncing wp-content (excluding tests) into Docker volume..."

(
  cd wp-content
  tar -cf - \
    --exclude='plugins/custom-plugins/movies-wp-media-automation/includes/tests' \
    --exclude='themes/streamit-child/inc/tests' \
    plugins/custom-plugins/movies-wp-media-automation \
    themes/streamit-child \
    mu-plugins/0-stc-front-bootstrap-trim.php
) | docker compose -f "$COMPOSE_FILE" exec -T "$SERVICE" tar -xf - -C /var/www/html/wp-content

echo "Ensuring temporary profiler MU is not present on the volume..."
docker compose -f "$COMPOSE_FILE" exec -T "$SERVICE" rm -f \
  /var/www/html/wp-content/mu-plugins/0-stc-plugin-load-profiler.php

echo "Fixing ownership to www-data:www-data..."
docker compose -f "$COMPOSE_FILE" exec -T "$SERVICE" sh -lc \
  "chown -R www-data:www-data \
    /var/www/html/wp-content/plugins/custom-plugins/movies-wp-media-automation \
    /var/www/html/wp-content/themes/streamit-child \
    /var/www/html/wp-content/mu-plugins/0-stc-front-bootstrap-trim.php"

echo "Done."
