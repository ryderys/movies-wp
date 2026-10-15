#!/usr/bin/env bash
set -euo pipefail

# Fast-forward the server checkout, then copy the plugin and child theme into
# the production wp_content Docker volume.
#
# Run on the server, from anywhere:
#   ./scripts/deploy-prod.sh
#   DEPLOY_BRANCH=prod ./scripts/deploy-prod.sh
#
# The branch defaults to whatever this checkout is already on. Refuses to run
# when tracked files are modified. Untracked files (for example .env) do not
# block the deploy. git pull --ff-only aborts if the branch is not a
# fast-forward.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
  echo "Tracked files are modified; refusing to pull or sync." >&2
  git status --short --untracked-files=no >&2
  exit 1
fi

BRANCH="${DEPLOY_BRANCH:-$(git rev-parse --abbrev-ref HEAD)}"
if [[ "$BRANCH" == "HEAD" ]]; then
  echo "Detached HEAD. Set DEPLOY_BRANCH to the branch to deploy." >&2
  exit 1
fi

echo "Fast-forwarding ${BRANCH}..."
git fetch origin "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "HEAD is $(git rev-parse --short HEAD)"
exec ./scripts/sync-wp-content-volume-prod.sh
