# TYPO3 v14.3 Project Installer

Scaffolding tool for TYPO3 v14.3 projects with Docker, SCSS, and multi-language support. Runs interactively or fully unattended via CLI flags.

Requirements: `git`, `composer`, `php` (and optionally `pnpm` for the frontend build).

## Quick start (no clone needed)

Run the installer directly from GitHub — the bootstrap clones the scaffolder into a temp dir, installs its dependencies, runs the installer, and removes the temp dir on exit.

**Interactive** (process substitution keeps stdin attached to your terminal):

```sh
bash <(curl -fsSL https://raw.githubusercontent.com/marekskopal/typo3-install/main/bootstrap.sh)
```

**Non-interactive** — flags after `--` go to `install.sh`:

```sh
curl -fsSL https://raw.githubusercontent.com/marekskopal/typo3-install/main/bootstrap.sh \
    | bash -s -- -y --name "My Site" --hostname mysite.cz --extensions fontawesome,faq
```

The project is created under the directory you ran the command from (or `--target-dir` if given).

## Set up the scaffolder (manual)

If you'd rather clone first, install the scaffolder's own dependencies once:

```sh
composer install
```

This only installs the scaffolder — it does not yet generate a TYPO3 project.

## Generate a new project

### Interactive

```sh
./install.sh
```

The installer will prompt for:
- **Project name** and **hostname**
- **Target directory**
- **Development ports** (HTTP/HTTPS)
- **Development SSL certificate and key** for the nginx proxy
- **Extensions** to include (multi-select from the [available extensions](#available-extensions))
- **Languages** (cs, en, de) and default language
- **Database setup** (optional, requires running MySQL)
- **Git init**

### Non-interactive

Any prompt can be answered with a CLI flag. Combine the flags with `-y` / `--yes` to skip every remaining prompt and run unattended:

```sh
./install.sh -y \
    --name "My Site" \
    --hostname mysite.cz \
    --target-dir /srv/mysite \
    --extensions fontawesome,faq,timeline \
    --languages cs,en \
    --default-language cs
```

Common flags:

| Flag | Description |
|------|-------------|
| `-n, --name <name>` | Project name (e.g. `"My Website"`) |
| `-m, --machine-name <slug>` | Machine name (defaults to a slug of `--name`) |
| `-H, --hostname <host>` | Production hostname |
| `-t, --target-dir <path>` | Where the project is created (defaults to `$PWD/<machine-name>`) |
| `--http-port <port>` / `--ssl-port <port>` | Dev ports (defaults: 4100 / 4200) |
| `--ssl-cert <path>` / `--ssl-key <path>` | Dev SSL certificate and key for the proxy (defaults: `./ssl/server.crt` / `./ssl/server.key`) |
| `-e, --extensions <list>` | Comma-separated extension names; the `typo3-` prefix is optional |
| `-l, --languages <list>` | Comma-separated language codes from `cs,en,de` |
| `-d, --default-language <code>` | Default language (must be one of `--languages`) |
| `--setup-db` / `--no-setup-db` | Run / skip database setup |
| `--init-git` / `--no-init-git` | Initialize / skip git repo |
| `-y, --yes` | Use defaults for any value not provided; requires `--name` and `--hostname` |
| `--list-extensions` | Show all available extension names |
| `-h, --help` | Show all options |

Run `./install.sh --help` for the full reference.

Flags and prompts can be mixed: any value provided via a flag skips its prompt, and the rest are asked interactively as usual.

### Available extensions

`typo3-core` is always installed; the rest are optional. Each one is required with a `^major.minor` constraint at its latest release, so Composer installs the newest compatible version.

| Extension | Description |
|-----------|-------------|
| `typo3-core` | Core plugin (required; pulls in news and ke_search) |
| `typo3-google-font` | Google Fonts integration |
| `typo3-fontawesome` | Font Awesome integration |
| `typo3-faq` | FAQ plugin |
| `typo3-features` | Features plugin |
| `typo3-instafeed` | Instagram feed plugin |
| `typo3-mailchimp` | Mailchimp newsletter |
| `typo3-pricing` | Pricing tables |
| `typo3-recipe` | Recipe plugin |
| `typo3-reference` | Reference/portfolio |
| `typo3-team` | Team members list |
| `typo3-timeline` | Timeline plugin |
| `typo3-darts` | Darts league plugin |
| `typo3-mcp-server` | MCP server integration |
| `typo3-mcp-docs` | MCP documentation plugin |

All extensions are published under `marekskopal/` on Packagist. `./install.sh --list-extensions` prints the current list.

### Development SSL certificate

The nginx proxy serves the site over HTTPS on the dev SSL port and needs a certificate and private key. Point `--ssl-cert` / `--ssl-key` at an existing pair, or place one at the defaults `./ssl/server.crt` and `./ssl/server.key` inside the generated project. The paths are written to `.env` (`PROXY_SSL_CERT` / `PROXY_SSL_KEY`), and `/ssl` is git-ignored so the key is never committed.

A self-signed pair for local development can be created with:

```sh
mkdir -p ssl && openssl req -x509 -newkey rsa:2048 -nodes -days 825 \
    -keyout ssl/server.key -out ssl/server.crt -subj "/CN=localhost"
```

### Generated project structure

```
project/
├── packages/ms_web/          # Local TYPO3 theme extension (SCSS, Fluid, TypoScript)
├── config/
│   ├── sites/{name}/         # Site configuration with languages
│   └── system/               # System settings (DB, mail, caching)
├── public/                   # Web root
│   ├── .htaccess             # TYPO3 v14.3 Apache rules
│   └── fileadmin/.htaccess   # CSP hardening for user uploads (default file storage)
├── docker/                   # Apache vhost and PHP ini for the web image
├── proxy/                    # nginx reverse proxy with TLS
├── docker-compose.yml        # Docker setup (nginx proxy + PHP/Apache)
├── Dockerfile                # Multi-stage build (PHP, Composer, Node/SCSS)
├── .env / .env.example       # Ports, SSL cert paths, database and SMTP settings
├── package.json              # Gulp + SCSS build pipeline
└── composer.json             # TYPO3 v14.3 dependencies
```

### Docker stack

| Component | Image |
|-----------|-------|
| Web | `php:8.5.12-apache` with `mlocati/php-extension-installer:2.13.2` |
| Composer | `composer:2.11.1` |
| Frontend build | `node:24.22.2` (pnpm, Gulp, Sass) |
| Proxy | `nginx:1.31.8-alpine` |

The proxy terminates TLS 1.3 and sends HSTS, `Referrer-Policy`, `X-Frame-Options` and `X-Content-Type-Options` headers on every response.

## Development

Install scaffolder dependencies:

```sh
composer install
```

Static analysis:

```sh
vendor/bin/phpstan analyse
```

Code style:

```sh
vendor/bin/phpcs
```

Tests:

```sh
vendor/bin/phpunit
```

### Templates

Generated files live in `templates/`. Static files are copied as-is; files with `{{name}}` placeholders (for example `Dockerfile`, `docker-compose.yml`, `env`, `config/system/settings.php`) are filled in by `AbstractGenerator::renderTemplate()`. Reusable snippets that are inserted into other templates live in `templates/fragments/`. Edit the template files rather than the generator classes when changing generated content.
