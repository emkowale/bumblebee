#!/usr/bin/env bash
set -euo pipefail

# Universal release script for Bumblebee (WordPress plugin)
# - Reads version from bumblebee.php (Version: X.Y.Z)
# - Builds zip: artifacts/bumblebee-vX.Y.Z.zip (folder name inside zip is "bumblebee/")
# - Commits changes, creates git tag vX.Y.Z, pushes commits + tag
# - Creates GitHub Release with attached zip (requires gh CLI or uses curl fallback)

REPO_SLUG="emkowale/bumblebee"
PLUGIN_DIR="bumblebee"
MAIN_FILE="bumblebee.php"
ARTIFACT_DIR="artifacts"

color() { printf "\033[%sm%s\033[0m\n" "$1" "$2"; }
ok()    { color "32" "✔ $1"; }
info()  { color "36" "ℹ $1"; }
warn()  { color "33" "⚠ $1"; }
err()   { color "31" "✖ $1"; exit 1; }

require_cmd() { command -v "$1" >/dev/null 2>&1 || err "Missing required command: $1"; }

# Ensure tools are present
require_cmd git
require_cmd rsync
require_cmd zip

# Sanity checks
[ -f "$MAIN_FILE" ] || err "Run this from the plugin root (where $MAIN_FILE exists)."
[ "$(basename "$PWD")" = "$PLUGIN_DIR" ] || warn "Current directory name is '$(basename "$PWD")'. Expected '$PLUGIN_DIR' for proper zip structure."

# Parse version from main plugin file
VER=$(grep -E "^[[:space:]]*Version:[[:space:]]*[0-9]+\.[0-9]+\.[0-9]+" -m1 "$MAIN_FILE" | sed -E 's/.*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*/\1/')
[ -n "${VER:-}" ] || err "Could not parse Version: from $MAIN_FILE"
TAG="v$VER"
ZIP="bumblebee-v$VER.zip"

info "Preparing release $TAG"

# Git state
if ! git rev-parse --git-dir >/dev/null 2>&1; then
  warn "Not a git repo. Initializing..."
  git init
fi
git add .
git commit -m "Release $VER" || info "Nothing to commit (working tree clean)"
git tag -a "$TAG" -m "Bumblebee $VER" || info "Tag $TAG already exists"

# Build zip
rm -rf "$ARTIFACT_DIR" package
mkdir -p package/bumblebee "$ARTIFACT_DIR"

# Ensure correct folder name inside zip: "bumblebee/"
rsync -a --delete \
  --exclude '.git' \
  --exclude '.github' \
  --exclude 'node_modules' \
  --exclude 'vendor' \
  --exclude '*.zip' \
  --exclude 'tests' \
  ./ package/bumblebee/

( cd package && zip -r "../$ARTIFACT_DIR/$ZIP" bumblebee >/dev/null )
ok "Built $ARTIFACT_DIR/$ZIP"

# Push commits and tags
git branch -M main || true
git push -u origin main || warn "Could not push main (check remote)"
git push origin --tags || warn "Could not push tags (check remote)"

# Release via gh or curl
if command -v gh >/dev/null 2>&1; then
  info "Creating GitHub release via gh CLI"
  gh release create "$TAG" "$ARTIFACT_DIR/$ZIP" -R "$REPO_SLUG" -t "Bumblebee $TAG" -n "Automated release $TAG" || warn "gh release failed"
else
  info "gh CLI not found. Attempting curl API (requires GITHUB_TOKEN env var)"
  # Need jq and curl
  if ! command -v jq >/dev/null 2>&1; then err "jq is required for API JSON handling (brew install jq / apt install jq)"; fi
  [ -n "${GITHUB_TOKEN:-}" ] || err "GITHUB_TOKEN not set; cannot create release via API"
  API_JSON=$(jq -n --arg tag "$TAG" --arg name "Bumblebee $TAG" --arg body "Automated release $TAG" '{tag_name:$tag, name:$name, body:$body, draft:false, prerelease:false}')
  RESP=$(curl -sS -X POST -H "Authorization: token $GITHUB_TOKEN" -H "Content-Type: application/json" -d "$API_JSON" "https://api.github.com/repos/$REPO_SLUG/releases")
  UPLOAD_URL=$(echo "$RESP" | jq -r '.upload_url' | sed -E 's/\{.*\}//')
  [ -n "$UPLOAD_URL" ] || err "Failed to create release via API: $RESP"
  curl -sS -X POST -H "Authorization: token $GITHUB_TOKEN" -H "Content-Type: application/zip" \
    --data-binary @"$ARTIFACT_DIR/$ZIP" "$UPLOAD_URL?name=$ZIP" >/dev/null || warn "Asset upload failed"
fi

ok "Release complete: $TAG"
