#!/bin/bash
set -euo pipefail

# Usage: ./release.sh [major|minor|patch]
# Example: ./release.sh patch

if [ $# -lt 1 ]; then
  echo "Usage: $0 [major|minor|patch]"
  exit 1
fi

BUMP="$1"
PROJECT="$(basename "$PWD")"

# ---------- helpers ----------
read_file_version() {
  if [ -f "style.css" ]; then
    # read Version: from theme header (tolerate spaces / asterisk)
    grep -Ei '^[[:space:]]*\**[[:space:]]*Version:' style.css | head -n1 | sed -E 's/.*Version:[[:space:]]*//'
  elif [ -f "${PROJECT}.php" ]; then
    # read Version: from plugin header (tolerate spaces / asterisk)
    grep -Ei '^[[:space:]]*\**[[:space:]]*Version:' "${PROJECT}.php" | head -n1 | sed -E 's/.*Version:[[:space:]]*//'
  else
    echo "0.0.0"
  fi
}

ver_max() { printf "%s\n%s\n" "$1" "$2" | sort -V | tail -n1; }
tag_exists() { git rev-parse -q --verify "refs/tags/$1" >/dev/null 2>&1; }

# ---------- determine base version ----------
LATEST_TAG="$(git describe --tags --abbrev=0 2>/dev/null || echo "v0.0.0")"
TAGVER="${LATEST_TAG#v}"
FILEVER="$(read_file_version)"
BASEVER="$(ver_max "$FILEVER" "$TAGVER")"

IFS='.' read -r MAJOR MINOR PATCH <<<"$BASEVER"
case "$BUMP" in
  major) MAJOR=$((MAJOR+1)); MINOR=0; PATCH=0 ;;
  minor) MINOR=$((MINOR+1)); PATCH=0 ;;
  patch) PATCH=$((PATCH+1)) ;;
  *) echo "❌ Invalid bump type. Use: major, minor, patch"; exit 1 ;;
esac

NEWVER="${MAJOR}.${MINOR}.${PATCH}"
while tag_exists "v${NEWVER}"; do PATCH=$((PATCH+1)); NEWVER="${MAJOR}.${MINOR}.${PATCH}"; done

ZIPNAME="${PROJECT}-v${NEWVER}.zip"
echo "🚀 Bumping $PROJECT $BASEVER → $NEWVER"

# ---------- update versions (SSHFS-safe) ----------
if [ -f "style.css" ]; then
  # Accept leading spaces and optional '*' used in CSS header comments.
  sed -E 's/^([[:space:]]*\**[[:space:]]*Version:).*/\1 '"${NEWVER}"'/' style.css > style.css.tmp && mv style.css.tmp style.css
  echo "✔ Updated style.css"
fi

if [ -f "${PROJECT}.php" ]; then
  # Accept leading spaces and '*' in plugin main header.
  sed -E 's/^([[:space:]]*\**[[:space:]]*Version:).*/\1 '"${NEWVER}"'/' "${PROJECT}.php" > tmpfile && mv tmpfile "${PROJECT}.php"
  echo "✔ Updated ${PROJECT}.php"
fi

# ---------- build release notes (CHANGELOG + GitHub notes) ----------
PREV_TAG="$LATEST_TAG"
if git rev-parse -q --verify "$PREV_TAG" >/dev/null 2>&1 && [ "$PREV_TAG" != "v0.0.0" ]; then
  LOG_RANGE="${PREV_TAG}..HEAD"
else
  LOG_RANGE=""
fi

if [ -n "$LOG_RANGE" ]; then
  COMMITS_RAW="$(git log $LOG_RANGE --no-merges --pretty=format:'%s' | grep -Ev '^(chore\(release\):|chore: release|update changelog|bump version|release v)' || true)"
  FILES_CHANGED="$(git diff --name-status $LOG_RANGE || true)"
else
  COMMITS_RAW="$(git log --no-merges --pretty=format:'%s' | grep -Ev '^(chore\(release\):|chore: release|update changelog|bump version|release v)' || true)"
  FILES_CHANGED="$(git diff --name-status $(git hash-object -t tree /dev/null) HEAD || true)"
fi

bucket(){ echo "$COMMITS_RAW" | grep -Ei "^$1(\(.+\))?: " | sed -E "s/^$1(\(.+\))?: /- /" || true; }
FEAT="$(bucket feat)"; FIX="$(bucket fix)"; PERF="$(bucket perf)"; REFACTOR="$(bucket refactor)"
DOCS="$(bucket docs)"; STYLE="$(bucket style)"; TESTS="$(bucket test)"; BUILD="$(bucket build)"; CI="$(bucket ci)"
MISC="$(echo "$COMMITS_RAW" | grep -Ev '^(feat|fix|perf|refactor|docs|style|test|build|ci)(\(.+\))?: ' | sed 's/^/- /' || true)"
NOTES=""; [ -f RELEASE_NOTES.md ] && NOTES="$(cat RELEASE_NOTES.md)"

{
  echo "## v${NEWVER} - $(date +%Y-%m-%d)"
  [ -n "$NOTES" ] && { echo; echo "$NOTES"; }
  [ -n "$FEAT" ] && { echo; echo "### Features"; echo "$FEAT"; }
  [ -n "$FIX" ] && { echo; echo "### Fixes"; echo "$FIX"; }
  [ -n "$PERF" ] && { echo; echo "### Performance"; echo "$PERF"; }
  [ -n "$REFACTOR" ] && { echo; echo "### Refactoring"; echo "$REFACTOR"; }
  [ -n "$DOCS" ] && { echo; echo "### Docs"; echo "$DOCS"; }
  [ -n "$STYLE" ] && { echo; echo "### Style"; echo "$STYLE"; }
  [ -n "$TESTS" ] && { echo; echo "### Tests"; echo "$TESTS"; }
  [ -n "$BUILD" ] && { echo; echo "### Build"; echo "$BUILD"; }
  [ -n "$CI" ] && { echo; echo "### CI"; echo "$CI"; }
  [ -n "$MISC" ] && { echo; echo "### Other"; echo "$MISC"; }
  if [ -n "$FILES_CHANGED" ]; then
    echo; echo "### Changed files"; echo "$FILES_CHANGED" | sed 's/^/- /'
  fi
  if [ -z "$FEAT$FIX$PERF$REFACTOR$DOCS$STYLE$TESTS$BUILD$CI$MISC" ]; then
    echo; echo "_No user-facing changes in this release._"
  fi
} > .release_notes.tmp

{ cat .release_notes.tmp; echo; echo; [ -f CHANGELOG.md ] && cat CHANGELOG.md; } > .changelog.tmp
mv .changelog.tmp CHANGELOG.md
echo "✔ Updated CHANGELOG.md"

# ---------- commit, tag ----------
git add -A
git commit -m "chore(release): v${NEWVER}" || echo "ℹ Nothing to commit"
git push
git tag -a "v${NEWVER}" -m "${PROJECT} v${NEWVER}"
git push origin "v${NEWVER}"

# ---------- build clean ZIP with correct WP structure ----------
cd ..
WORKDIR="${PROJECT}-build"
rm -rf "$WORKDIR"; mkdir "$WORKDIR"
rsync -a "$PROJECT/" "$WORKDIR/$PROJECT/" \
  --exclude ".git" --exclude ".github" --exclude "node_modules" \
  --exclude "vendor" --exclude "*.map" --exclude "*.DS_Store" \
  --exclude "release.sh" --exclude ".idea" --exclude ".vscode"

cd "$WORKDIR"
zip -rq "../$ZIPNAME" "$PROJECT"
cd ..
rm -rf "$WORKDIR"
cd "$PROJECT"

# ---------- verify ZIP contents ----------
echo "🔎 Verifying built ZIP..."
if unzip -p "../$ZIPNAME" "$PROJECT/style.css" >/dev/null 2>&1; then
  # theme: verify Version in style.css
  if unzip -p "../$ZIPNAME" "$PROJECT/style.css" | grep -Ei '^[[:space:]]*\**[[:space:]]*Version:[[:space:]]*'"$NEWVER" >/dev/null; then
    echo "✔ ZIP has style.css Version: $NEWVER"
  else
    echo "❌ ZIP does not contain style.css with Version: $NEWVER"; exit 1
  fi
elif [ -f "${PROJECT}.php" ]; then
  # plugin: verify Version in main file
  if unzip -p "../$ZIPNAME" "$PROJECT/${PROJECT}.php" | grep -Ei '^[[:space:]]*\**[[:space:]]*Version:[[:space:]]*'"$NEWVER" >/dev/null; then
    echo "✔ ZIP has ${PROJECT}.php Version: $NEWVER"
  else
    echo "❌ ZIP does not contain ${PROJECT}.php with Version: $NEWVER"; exit 1
  fi
fi

# root folder check
if ! unzip -l "../$ZIPNAME" | awk '{print $4}' | grep -E "^$PROJECT/$" | head -n1 >/dev/null; then
  echo "❌ ZIP root folder is not '$PROJECT/'"; exit 1
fi

# ---------- GitHub release ----------
if command -v gh >/dev/null 2>&1; then
  gh release create "v${NEWVER}" "../$ZIPNAME" --title "${PROJECT} v${NEWVER}" --notes-file .release_notes.tmp
  echo "✔ Published GitHub release v${NEWVER}"
  rm -f .release_notes.tmp
else
  echo "⚠ 'gh' not found; created tag and zip, but did not publish a GitHub release."
  echo "   Run: gh release create v${NEWVER} ../${ZIPNAME} --title \"${PROJECT} v${NEWVER}\" --notes-file .release_notes.tmp"
fi

echo "🎉 Release $PROJECT v$NEWVER complete"
