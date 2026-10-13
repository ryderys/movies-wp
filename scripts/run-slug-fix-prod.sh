#!/usr/bin/env bash
set -euo pipefail

# Runs the slug-fix script inside the production WordPress container.
#
# Dry run (default):
#   ./scripts/run-slug-fix-prod.sh
#
# Apply changes:
#   APPLY=1 ./scripts/run-slug-fix-prod.sh
#
# Optional:
#   SAMPLE=50 ./scripts/run-slug-fix-prod.sh

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
SERVICE="${SERVICE:-wordpress}"
SAMPLE="${SAMPLE:-30}"
APPLY="${APPLY:-0}"

if [[ ! -f "$COMPOSE_FILE" ]]; then
  echo "Missing compose file: $COMPOSE_FILE" >&2
  exit 1
fi

PHP_SCRIPT="scripts/fix-slugs-to-tmdb-title-prod.php"
if [[ ! -f "$PHP_SCRIPT" ]]; then
  echo "Missing script: $PHP_SCRIPT" >&2
  exit 1
fi

TARGET_DIR="/tmp/movies-wp-scripts"
TARGET_SCRIPT="${TARGET_DIR}/fix-slugs-to-tmdb-title-prod.php"

echo "Copying PHP script into container..."
# Stream just the PHP file contents into the target directory (avoid extracting repo paths).
(cd scripts && tar -cf - fix-slugs-to-tmdb-title-prod.php) | docker compose -f "$COMPOSE_FILE" exec -T "$SERVICE" sh -lc \
  "mkdir -p \"$TARGET_DIR\" && tar -xf - -C \"$TARGET_DIR\" && chmod 0644 \"$TARGET_SCRIPT\""

MODE_ARGS=(--dry-run "--sample=${SAMPLE}")
if [[ "$APPLY" == "1" ]]; then
  MODE_ARGS=(--apply "--sample=${SAMPLE}")
fi

echo "Running slug-fix inside container (APPLY=$APPLY SAMPLE=$SAMPLE)..."
docker compose -f "$COMPOSE_FILE" exec -T "$SERVICE" php "$TARGET_SCRIPT" "${MODE_ARGS[@]}"

echo "Cleaning up copied script..."
docker compose -f "$COMPOSE_FILE" exec -T "$SERVICE" sh -lc "rm -rf \"$TARGET_DIR\""

echo "Done."

