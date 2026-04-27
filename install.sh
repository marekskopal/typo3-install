#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m'

print_header() {
    echo ""
    echo -e "${BLUE}${BOLD}=== $1 ===${NC}"
    echo ""
}

print_step() {
    echo -e "${GREEN}[+]${NC} $1"
}

print_warn() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_error() {
    echo -e "${RED}[x]${NC} $1"
}

# ─────────────────────────────────────────────
# Phase 1: Interactive Prompts
# ─────────────────────────────────────────────

print_header "TYPO3 v14.3 Project Installer"

# 1. Project name
read -p "Project name (e.g. 'My Website'): " PROJECT_NAME
if [ -z "$PROJECT_NAME" ]; then
    print_error "Project name is required"
    exit 1
fi

# 2. Machine name
DEFAULT_MACHINE_NAME=$(echo "$PROJECT_NAME" | tr '[:upper:]' '[:lower:]' | tr ' ' '-' | tr -cd 'a-z0-9-')
read -p "Machine name [$DEFAULT_MACHINE_NAME]: " MACHINE_NAME
MACHINE_NAME=${MACHINE_NAME:-$DEFAULT_MACHINE_NAME}

# 3. Hostname
read -p "Production hostname (e.g. mywebsite.cz): " HOSTNAME
if [ -z "$HOSTNAME" ]; then
    print_error "Hostname is required"
    exit 1
fi

# 4. Ports
read -p "Development HTTP port [4100]: " DEV_HTTP_PORT
DEV_HTTP_PORT=${DEV_HTTP_PORT:-4100}
read -p "Development HTTPS port [4200]: " DEV_SSL_PORT
DEV_SSL_PORT=${DEV_SSL_PORT:-4200}

# 5. Target directory
read -p "Target directory [$(pwd)/$MACHINE_NAME]: " TARGET_DIR
TARGET_DIR=${TARGET_DIR:-$(pwd)/$MACHINE_NAME}

# ─────────────────────────────────────────────
# Extension selection
# ─────────────────────────────────────────────

print_header "Extension Selection"

# Extension list: name|composer_name|version|selected_by_default
EXTENSIONS=(
    "typo3-core|marekskopal/typo3-core|^14.3|1|REQUIRED"
    "typo3-google-font|marekskopal/typo3-google-font|^1.0|0|Google Fonts integration"
    "typo3-fontawesome|marekskopal/typo3-fontawesome|^1.0|0|Font Awesome integration"
    "typo3-faq|marekskopal/typo3-faq|^1.0|0|FAQ plugin"
    "typo3-features|marekskopal/typo3-features|^1.0|0|Features plugin"
    "typo3-instafeed|marekskopal/typo3-instafeed|^1.0|0|Instagram feed plugin"
    "typo3-mailchimp|marekskopal/typo3-mailchimp|^1.0|0|Mailchimp newsletter"
    "typo3-pricing|marekskopal/typo3-pricing|^1.0|0|Pricing tables"
    "typo3-recipe|marekskopal/typo3-recipe|^1.0|0|Recipe plugin"
    "typo3-reference|marekskopal/typo3-reference|^1.0|0|Reference/portfolio"
    "typo3-timeline|marekskopal/typo3-timeline|^1.0|0|Timeline plugin"
    "typo3-mcp-server|marekskopal/typo3-mcp-server|^0.3.0|0|MCP server integration"
)

# Parse into arrays
declare -a EXT_NAMES EXT_COMPOSERS EXT_VERSIONS EXT_SELECTED EXT_DESCRIPTIONS
for i in "${!EXTENSIONS[@]}"; do
    IFS='|' read -r name composer version selected desc <<< "${EXTENSIONS[$i]}"
    EXT_NAMES+=("$name")
    EXT_COMPOSERS+=("$composer")
    EXT_VERSIONS+=("$version")
    EXT_SELECTED+=("$selected")
    EXT_DESCRIPTIONS+=("$desc")
done

# Display and toggle loop
while true; do
    echo ""
    for i in "${!EXT_NAMES[@]}"; do
        if [ "${EXT_SELECTED[$i]}" = "1" ]; then
            marker="${GREEN}[x]${NC}"
        else
            marker="[ ]"
        fi
        if [ "$i" = "0" ]; then
            echo -e "  $marker ${BOLD}${EXT_NAMES[$i]}${NC} (${EXT_DESCRIPTIONS[$i]})"
        else
            printf "  $marker %d) %-25s %s\n" "$i" "${EXT_NAMES[$i]}" "(${EXT_DESCRIPTIONS[$i]})"
        fi
    done
    echo ""
    read -p "Toggle extension number (or Enter to confirm): " TOGGLE
    if [ -z "$TOGGLE" ]; then
        break
    fi
    if [[ "$TOGGLE" =~ ^[0-9]+$ ]] && [ "$TOGGLE" -ge 1 ] && [ "$TOGGLE" -lt "${#EXT_NAMES[@]}" ]; then
        if [ "${EXT_SELECTED[$TOGGLE]}" = "1" ]; then
            EXT_SELECTED[$TOGGLE]="0"
        else
            EXT_SELECTED[$TOGGLE]="1"
        fi
    elif [ "$TOGGLE" = "0" ]; then
        print_warn "typo3-core is required and cannot be deselected"
    else
        print_warn "Invalid number"
    fi
done

# Build selected extensions JSON array
SELECTED_EXT_JSON="["
FIRST=true
for i in "${!EXT_NAMES[@]}"; do
    if [ "${EXT_SELECTED[$i]}" = "1" ]; then
        if [ "$FIRST" = true ]; then
            FIRST=false
        else
            SELECTED_EXT_JSON+=","
        fi
        SELECTED_EXT_JSON+="{\"name\":\"${EXT_COMPOSERS[$i]}\",\"version\":\"${EXT_VERSIONS[$i]}\"}"
    fi
done
SELECTED_EXT_JSON+="]"

# ─────────────────────────────────────────────
# Language selection
# ─────────────────────────────────────────────

print_header "Language Selection"

LANG_CODES=("cs" "en" "de")
LANG_NAMES=("Czech" "English" "German")
LANG_SELECTED=("0" "0" "0")

while true; do
    echo ""
    for i in "${!LANG_CODES[@]}"; do
        if [ "${LANG_SELECTED[$i]}" = "1" ]; then
            marker="${GREEN}[x]${NC}"
        else
            marker="[ ]"
        fi
        echo -e "  $marker $((i+1))) ${LANG_NAMES[$i]} (${LANG_CODES[$i]})"
    done
    echo ""
    read -p "Toggle language number (or Enter to confirm): " TOGGLE
    if [ -z "$TOGGLE" ]; then
        # Check at least one selected
        HAS_LANG=false
        for sel in "${LANG_SELECTED[@]}"; do
            [ "$sel" = "1" ] && HAS_LANG=true
        done
        if [ "$HAS_LANG" = true ]; then
            break
        else
            print_warn "Select at least one language"
        fi
    elif [[ "$TOGGLE" =~ ^[1-3]$ ]]; then
        IDX=$((TOGGLE - 1))
        if [ "${LANG_SELECTED[$IDX]}" = "1" ]; then
            LANG_SELECTED[$IDX]="0"
        else
            LANG_SELECTED[$IDX]="1"
        fi
    else
        print_warn "Invalid number (1-3)"
    fi
done

# Build selected languages array
SELECTED_LANGS=()
for i in "${!LANG_CODES[@]}"; do
    if [ "${LANG_SELECTED[$i]}" = "1" ]; then
        SELECTED_LANGS+=("${LANG_CODES[$i]}")
    fi
done

# Default language selection
if [ "${#SELECTED_LANGS[@]}" -eq 1 ]; then
    DEFAULT_LANG="${SELECTED_LANGS[0]}"
    echo ""
    print_step "Default language: $DEFAULT_LANG (only one selected)"
else
    echo ""
    echo "Select the default language:"
    for i in "${!SELECTED_LANGS[@]}"; do
        echo "  $((i+1))) ${SELECTED_LANGS[$i]}"
    done
    read -p "Default language number: " DEF_LANG_NUM
    DEF_LANG_IDX=$((DEF_LANG_NUM - 1))
    if [ "$DEF_LANG_IDX" -ge 0 ] && [ "$DEF_LANG_IDX" -lt "${#SELECTED_LANGS[@]}" ]; then
        DEFAULT_LANG="${SELECTED_LANGS[$DEF_LANG_IDX]}"
    else
        print_error "Invalid selection"
        exit 1
    fi
fi

# Build languages JSON
LANGS_JSON="["
FIRST=true
for lang in "${SELECTED_LANGS[@]}"; do
    if [ "$FIRST" = true ]; then
        FIRST=false
    else
        LANGS_JSON+=","
    fi
    LANGS_JSON+="\"$lang\""
done
LANGS_JSON+="]"

# ─────────────────────────────────────────────
# Phase 2: Validation
# ─────────────────────────────────────────────

print_header "Validation"

command -v php >/dev/null 2>&1 || { print_error "PHP is required but not found"; exit 1; }
print_step "PHP found: $(php -v | head -1)"

command -v composer >/dev/null 2>&1 || { print_error "Composer is required but not found"; exit 1; }
print_step "Composer found: $(composer --version 2>/dev/null | head -1)"

if [ -d "$TARGET_DIR" ] && [ "$(ls -A "$TARGET_DIR" 2>/dev/null)" ]; then
    print_warn "Target directory $TARGET_DIR is not empty"
    read -p "Continue anyway? (y/n) [n]: " CONFIRM
    if [ "$CONFIRM" != "y" ]; then
        exit 1
    fi
fi

# ─────────────────────────────────────────────
# Phase 3: Generation
# ─────────────────────────────────────────────

print_header "Generating Project"

mkdir -p "$TARGET_DIR"

# Build config JSON
CONFIG_JSON=$(cat <<JSONEOF
{
    "project_name": "$PROJECT_NAME",
    "machine_name": "$MACHINE_NAME",
    "hostname": "$HOSTNAME",
    "dev_http_port": "$DEV_HTTP_PORT",
    "dev_ssl_port": "$DEV_SSL_PORT",
    "target_dir": "$TARGET_DIR",
    "extensions": $SELECTED_EXT_JSON,
    "languages": $LANGS_JSON,
    "default_language": "$DEFAULT_LANG"
}
JSONEOF
)

GEN="php $SCRIPT_DIR/bin/generate.php"

# Step 1: Generate composer.json
print_step "Generating composer.json..."
$GEN ComposerJson "$CONFIG_JSON" "$TARGET_DIR"

# Step 2: Run composer install
print_step "Running composer install (this may take a while)..."
cd "$TARGET_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-scripts 2>&1 | tail -5

# Step 3: Generate ms_web extension
print_step "Generating ms_web extension..."
$GEN MsWeb "$CONFIG_JSON" "$TARGET_DIR"

# Step 4: Run composer update to pick up local package
print_step "Running composer update..."
COMPOSER_ALLOW_SUPERUSER=1 composer update --no-interaction 2>&1 | tail -5

# Step 5: Generate site configuration
print_step "Generating site configuration..."
$GEN SiteConfig "$CONFIG_JSON" "$TARGET_DIR"

# Step 6: Generate system settings
print_step "Generating system settings..."
$GEN Settings "$CONFIG_JSON" "$TARGET_DIR"

# Step 7: Generate Docker infrastructure
print_step "Generating Docker infrastructure..."
$GEN Docker "$CONFIG_JSON" "$TARGET_DIR"

# Step 8: Generate frontend build files
print_step "Generating frontend build files..."
$GEN FrontendBuild "$CONFIG_JSON" "$TARGET_DIR"

# Step 9: Generate project files (.gitignore, .editorconfig, .htaccess)
print_step "Generating project files..."
$GEN ProjectFiles "$CONFIG_JSON" "$TARGET_DIR"

# Step 10: Build frontend (optional, needs pnpm)
if command -v pnpm >/dev/null 2>&1; then
    print_step "Installing frontend dependencies..."
    cd "$TARGET_DIR"
    pnpm install 2>&1 | tail -3
    print_step "Building SCSS..."
    pnpm build 2>&1 | tail -3
else
    print_warn "pnpm not found - skipping frontend build. Run 'pnpm install && pnpm build' later."
fi

# Step 11: Database setup (optional)
echo ""
read -p "Set up database now? (requires running MySQL) (y/n) [n]: " SETUP_DB
if [ "$SETUP_DB" = "y" ]; then
    print_step "Setting up database..."
    $GEN DatabaseSetup "$CONFIG_JSON" "$TARGET_DIR"
fi

# Step 12: Git init (optional)
echo ""
read -p "Initialize git repository? (y/n) [y]: " INIT_GIT
INIT_GIT=${INIT_GIT:-y}
if [ "$INIT_GIT" = "y" ]; then
    cd "$TARGET_DIR"
    git init
    git add .
    git commit -m "Initial TYPO3 v14.3 project setup"
    print_step "Git repository initialized"
fi

# ─────────────────────────────────────────────
# Done
# ─────────────────────────────────────────────

print_header "Installation Complete"

echo "Project created in: $TARGET_DIR"
echo ""
echo "Next steps:"
echo "  1. cd $TARGET_DIR"
echo "  2. Copy .env.example to .env and adjust values"
echo "  3. docker compose up -d"
echo "  4. Access TYPO3 at https://localhost:$DEV_SSL_PORT/typo3"
echo ""
if [ "$SETUP_DB" != "y" ]; then
    echo "  Database setup was skipped. To set up later:"
    echo "  php $SCRIPT_DIR/bin/generate.php DatabaseSetup '<config_json>' '$TARGET_DIR'"
    echo ""
fi
