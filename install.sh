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

usage() {
    cat <<EOF
Usage: install.sh [options]

Scaffolds a new TYPO3 v14.3 project. Any value not provided via flags is
asked interactively, unless --yes is given.

Options:
  -n, --name <name>              Project name (e.g. "My Website")
  -m, --machine-name <slug>      Machine name (default: derived from --name)
  -H, --hostname <host>          Production hostname (e.g. mywebsite.cz)
      --http-port <port>         Development HTTP port (default: 4100)
      --ssl-port <port>          Development HTTPS port (default: 4200)
  -t, --target-dir <path>        Target directory (default: \$PWD/<machine-name>)
  -e, --extensions <list>        Comma-separated extension names to enable
                                 (e.g. "fontawesome,faq"). The typo3- prefix
                                 is optional. typo3-core is always included.
                                 Use --list-extensions to see all names.
  -l, --languages <list>         Comma-separated language codes: cs,en,de
                                 (default in --yes mode: en)
  -d, --default-language <code>  Default language (default: first of --languages)
      --setup-db                 Run database setup after scaffolding
      --no-setup-db              Skip database setup
      --init-git                 Initialize git repo
      --no-init-git              Skip git initialization
  -y, --yes                      Non-interactive mode. Use defaults for any
                                 value not given. Requires --name and --hostname.
      --list-extensions          Print available extension names and exit
  -h, --help                     Show this help and exit

Examples:
  ./install.sh
  ./install.sh --name "My Site" --hostname mysite.cz --target-dir /srv/mysite
  ./install.sh -y -n "My Site" -H mysite.cz -e fontawesome,faq -l cs,en -d cs
EOF
}

# ─────────────────────────────────────────────
# CLI parsing
# ─────────────────────────────────────────────

PROJECT_NAME=""
MACHINE_NAME=""
HOSTNAME=""
DEV_HTTP_PORT=""
DEV_SSL_PORT=""
TARGET_DIR=""
CLI_EXTENSIONS=""
CLI_LANGUAGES=""
CLI_DEFAULT_LANG=""
SETUP_DB_FLAG=""
INIT_GIT_FLAG=""
ASSUME_YES=false
LIST_EXTENSIONS_ONLY=false

require_value() {
    if [ -z "$2" ] || [[ "$2" == -* ]]; then
        print_error "Option $1 requires a value"
        exit 1
    fi
}

while [ $# -gt 0 ]; do
    case "$1" in
        -n|--name) require_value "$1" "$2"; PROJECT_NAME="$2"; shift 2;;
        -m|--machine-name) require_value "$1" "$2"; MACHINE_NAME="$2"; shift 2;;
        -H|--hostname) require_value "$1" "$2"; HOSTNAME="$2"; shift 2;;
        --http-port) require_value "$1" "$2"; DEV_HTTP_PORT="$2"; shift 2;;
        --ssl-port) require_value "$1" "$2"; DEV_SSL_PORT="$2"; shift 2;;
        -t|--target-dir) require_value "$1" "$2"; TARGET_DIR="$2"; shift 2;;
        -e|--extensions) require_value "$1" "$2"; CLI_EXTENSIONS="$2"; shift 2;;
        -l|--languages) require_value "$1" "$2"; CLI_LANGUAGES="$2"; shift 2;;
        -d|--default-language) require_value "$1" "$2"; CLI_DEFAULT_LANG="$2"; shift 2;;
        --setup-db) SETUP_DB_FLAG="y"; shift;;
        --no-setup-db) SETUP_DB_FLAG="n"; shift;;
        --init-git) INIT_GIT_FLAG="y"; shift;;
        --no-init-git) INIT_GIT_FLAG="n"; shift;;
        -y|--yes) ASSUME_YES=true; shift;;
        --list-extensions) LIST_EXTENSIONS_ONLY=true; shift;;
        -h|--help) usage; exit 0;;
        --) shift; break;;
        *) print_error "Unknown option: $1"; echo ""; usage; exit 1;;
    esac
done

# ─────────────────────────────────────────────
# Extension catalog (name|composer|version|default_selected|description)
# ─────────────────────────────────────────────

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

declare -a EXT_NAMES EXT_COMPOSERS EXT_VERSIONS EXT_SELECTED EXT_DESCRIPTIONS
for i in "${!EXTENSIONS[@]}"; do
    IFS='|' read -r name composer version selected desc <<< "${EXTENSIONS[$i]}"
    EXT_NAMES+=("$name")
    EXT_COMPOSERS+=("$composer")
    EXT_VERSIONS+=("$version")
    EXT_SELECTED+=("$selected")
    EXT_DESCRIPTIONS+=("$desc")
done

if [ "$LIST_EXTENSIONS_ONLY" = true ]; then
    echo "Available extensions:"
    for i in "${!EXT_NAMES[@]}"; do
        if [ "$i" = "0" ]; then
            printf "  %-22s (required)  %s\n" "${EXT_NAMES[$i]}" "${EXT_DESCRIPTIONS[$i]}"
        else
            printf "  %-22s             %s\n" "${EXT_NAMES[$i]}" "${EXT_DESCRIPTIONS[$i]}"
        fi
    done
    echo ""
    echo "Pass with --extensions, e.g. --extensions fontawesome,faq"
    echo "(the 'typo3-' prefix is optional)"
    exit 0
fi

# Apply --extensions if provided
EXTENSIONS_FROM_CLI=false
if [ -n "$CLI_EXTENSIONS" ]; then
    EXTENSIONS_FROM_CLI=true
    for i in "${!EXT_NAMES[@]}"; do
        if [ "$i" != "0" ]; then EXT_SELECTED[$i]="0"; fi
    done
    IFS=',' read -ra REQ_EXT <<< "$CLI_EXTENSIONS"
    for raw in "${REQ_EXT[@]}"; do
        ext="$(echo "$raw" | tr -d ' ')"
        [ -z "$ext" ] && continue
        FOUND=false
        for i in "${!EXT_NAMES[@]}"; do
            short="${EXT_NAMES[$i]#typo3-}"
            if [ "${EXT_NAMES[$i]}" = "$ext" ] || [ "$short" = "$ext" ]; then
                EXT_SELECTED[$i]="1"
                FOUND=true
                break
            fi
        done
        if [ "$FOUND" = false ]; then
            print_error "Unknown extension: $ext"
            print_warn "Run ./install.sh --list-extensions to see available names"
            exit 1
        fi
    done
fi

# Apply --languages if provided
LANG_CODES=("cs" "en" "de")
LANG_NAMES=("Czech" "English" "German")
LANG_SELECTED=("0" "0" "0")

LANGUAGES_FROM_CLI=false
if [ -n "$CLI_LANGUAGES" ]; then
    LANGUAGES_FROM_CLI=true
    IFS=',' read -ra REQ_LANG <<< "$CLI_LANGUAGES"
    for raw in "${REQ_LANG[@]}"; do
        lang="$(echo "$raw" | tr -d ' ')"
        [ -z "$lang" ] && continue
        FOUND=false
        for i in "${!LANG_CODES[@]}"; do
            if [ "${LANG_CODES[$i]}" = "$lang" ]; then
                LANG_SELECTED[$i]="1"
                FOUND=true
                break
            fi
        done
        if [ "$FOUND" = false ]; then
            print_error "Unknown language code: $lang (allowed: cs, en, de)"
            exit 1
        fi
    done
fi

# ─────────────────────────────────────────────
# Phase 1: Prompts (skipped for values supplied via flags or in --yes mode)
# ─────────────────────────────────────────────

print_header "TYPO3 v14.3 Project Installer"

if [ -z "$PROJECT_NAME" ]; then
    if [ "$ASSUME_YES" = true ]; then
        print_error "--name is required with --yes"
        exit 1
    fi
    read -p "Project name (e.g. 'My Website'): " PROJECT_NAME
    if [ -z "$PROJECT_NAME" ]; then
        print_error "Project name is required"
        exit 1
    fi
fi

DEFAULT_MACHINE_NAME=$(echo "$PROJECT_NAME" | tr '[:upper:]' '[:lower:]' | tr ' ' '-' | tr -cd 'a-z0-9-')
if [ -z "$MACHINE_NAME" ]; then
    if [ "$ASSUME_YES" = true ]; then
        MACHINE_NAME="$DEFAULT_MACHINE_NAME"
    else
        read -p "Machine name [$DEFAULT_MACHINE_NAME]: " MACHINE_NAME
        MACHINE_NAME=${MACHINE_NAME:-$DEFAULT_MACHINE_NAME}
    fi
fi

if [ -z "$HOSTNAME" ]; then
    if [ "$ASSUME_YES" = true ]; then
        print_error "--hostname is required with --yes"
        exit 1
    fi
    read -p "Production hostname (e.g. mywebsite.cz): " HOSTNAME
    if [ -z "$HOSTNAME" ]; then
        print_error "Hostname is required"
        exit 1
    fi
fi

if [ -z "$DEV_HTTP_PORT" ]; then
    if [ "$ASSUME_YES" = true ]; then
        DEV_HTTP_PORT="4100"
    else
        read -p "Development HTTP port [4100]: " DEV_HTTP_PORT
        DEV_HTTP_PORT=${DEV_HTTP_PORT:-4100}
    fi
fi

if [ -z "$DEV_SSL_PORT" ]; then
    if [ "$ASSUME_YES" = true ]; then
        DEV_SSL_PORT="4200"
    else
        read -p "Development HTTPS port [4200]: " DEV_SSL_PORT
        DEV_SSL_PORT=${DEV_SSL_PORT:-4200}
    fi
fi

if [ -z "$TARGET_DIR" ]; then
    if [ "$ASSUME_YES" = true ]; then
        TARGET_DIR="$(pwd)/$MACHINE_NAME"
    else
        read -p "Target directory [$(pwd)/$MACHINE_NAME]: " TARGET_DIR
        TARGET_DIR=${TARGET_DIR:-$(pwd)/$MACHINE_NAME}
    fi
fi

# ─────────────────────────────────────────────
# Extension selection (interactive only when not preset via CLI or --yes)
# ─────────────────────────────────────────────

if [ "$EXTENSIONS_FROM_CLI" = false ] && [ "$ASSUME_YES" = false ]; then
    print_header "Extension Selection"

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
fi

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

if [ "$LANGUAGES_FROM_CLI" = false ] && [ "$ASSUME_YES" = false ]; then
    print_header "Language Selection"

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
elif [ "$LANGUAGES_FROM_CLI" = false ] && [ "$ASSUME_YES" = true ]; then
    LANG_SELECTED=("0" "1" "0")
fi

SELECTED_LANGS=()
for i in "${!LANG_CODES[@]}"; do
    if [ "${LANG_SELECTED[$i]}" = "1" ]; then
        SELECTED_LANGS+=("${LANG_CODES[$i]}")
    fi
done

if [ "${#SELECTED_LANGS[@]}" -eq 0 ]; then
    print_error "At least one language must be selected"
    exit 1
fi

# Default language
if [ -n "$CLI_DEFAULT_LANG" ]; then
    DEFAULT_LANG="$CLI_DEFAULT_LANG"
    VALID=false
    for lang in "${SELECTED_LANGS[@]}"; do
        [ "$lang" = "$DEFAULT_LANG" ] && VALID=true
    done
    if [ "$VALID" = false ]; then
        print_error "--default-language '$DEFAULT_LANG' is not among the selected languages (${SELECTED_LANGS[*]})"
        exit 1
    fi
elif [ "${#SELECTED_LANGS[@]}" -eq 1 ]; then
    DEFAULT_LANG="${SELECTED_LANGS[0]}"
    echo ""
    print_step "Default language: $DEFAULT_LANG (only one selected)"
elif [ "$ASSUME_YES" = true ]; then
    DEFAULT_LANG="${SELECTED_LANGS[0]}"
    print_step "Default language: $DEFAULT_LANG (first of --languages)"
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
    if [ "$ASSUME_YES" = true ]; then
        print_warn "--yes given; continuing anyway"
    else
        read -p "Continue anyway? (y/n) [n]: " CONFIRM
        if [ "$CONFIRM" != "y" ]; then
            exit 1
        fi
    fi
fi

# ─────────────────────────────────────────────
# Phase 3: Generation
# ─────────────────────────────────────────────

print_header "Generating Project"

mkdir -p "$TARGET_DIR"

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
if [ -z "$SETUP_DB_FLAG" ]; then
    if [ "$ASSUME_YES" = true ]; then
        SETUP_DB="n"
    else
        echo ""
        read -p "Set up database now? (requires running MySQL) (y/n) [n]: " SETUP_DB
    fi
else
    SETUP_DB="$SETUP_DB_FLAG"
fi
if [ "$SETUP_DB" = "y" ]; then
    print_step "Setting up database..."
    $GEN DatabaseSetup "$CONFIG_JSON" "$TARGET_DIR"
fi

# Step 12: Git init (optional)
if [ -z "$INIT_GIT_FLAG" ]; then
    if [ "$ASSUME_YES" = true ]; then
        INIT_GIT="y"
    else
        echo ""
        read -p "Initialize git repository? (y/n) [y]: " INIT_GIT
        INIT_GIT=${INIT_GIT:-y}
    fi
else
    INIT_GIT="$INIT_GIT_FLAG"
fi
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
