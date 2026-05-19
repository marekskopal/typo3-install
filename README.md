# TYPO3 v14.3 Project Installer

Scaffolding tool for TYPO3 v14.3 projects with Docker, SCSS, and multi-language support. Runs interactively or fully unattended via CLI flags.

## Set up the scaffolder

The scaffolder itself is a PHP tool, so its own dependencies need to be installed once before it can run:

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
- **Extensions** to include (multi-select from available `marekskopal/*` extensions)
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

### Generated project structure

```
project/
├── packages/ms_web/          # Local TYPO3 theme extension (SCSS, Fluid, TypoScript)
├── config/
│   ├── sites/{name}/         # Site configuration with languages
│   └── system/               # System settings (DB, mail, caching)
├── public/                   # Web root
├── docker-compose.yml        # Docker setup (nginx proxy + PHP/Apache)
├── Dockerfile                # Multi-stage build (PHP, Composer, Node/SCSS)
├── package.json              # Gulp + SCSS build pipeline
└── composer.json             # TYPO3 v14.3 dependencies
```

## Development

```sh
composer install
vendor/bin/phpstan analyse
vendor/bin/phpcs
vendor/bin/phpunit
```
