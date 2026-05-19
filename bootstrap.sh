#!/bin/bash
set -e
set -o pipefail

REPO_URL="${TYPO3_INSTALL_REPO:-https://github.com/marekskopal/typo3-install.git}"
REPO_BRANCH="${TYPO3_INSTALL_BRANCH:-main}"

GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

err()  { echo -e "${RED}[x]${NC} $1" >&2; }
info() { echo -e "${GREEN}[+]${NC} $1"; }

for cmd in git composer php; do
    command -v "$cmd" >/dev/null 2>&1 || { err "$cmd is required but not found"; exit 1; }
done

ORIGINAL_PWD="$PWD"
TMP_DIR="$(mktemp -d -t typo3-install-XXXXXX)"

cleanup() {
    if [ -n "$TMP_DIR" ] && [ -d "$TMP_DIR" ]; then
        rm -rf "$TMP_DIR"
    fi
}
trap cleanup EXIT INT TERM

info "Fetching installer from $REPO_URL (branch: $REPO_BRANCH)..."
git clone --depth 1 --branch "$REPO_BRANCH" "$REPO_URL" "$TMP_DIR"

info "Installing scaffolder dependencies..."
(
    cd "$TMP_DIR"
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-progress 2>&1 | tail -5
)

# Run install.sh from the original CWD so default target paths resolve
# relative to where the user invoked the bootstrap, not the temp clone.
cd "$ORIGINAL_PWD"
bash "$TMP_DIR/install.sh" "$@"
