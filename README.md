# TYPO3 v14.3 Project Installer

Interactive scaffolding tool for TYPO3 v14.3 projects with Docker, SCSS, and multi-language support.

## Install

```sh
composer install
```

## Usage

```sh
./install.sh
```

The interactive installer will prompt for:
- **Project name** and **hostname**
- **Development ports** (HTTP/HTTPS)
- **Extensions** to include (multi-select from available `marekskopal/*` extensions)
- **Languages** (cs, en, de) and default language
- **Database setup** (optional, requires running MySQL)

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
└── composer.json              # TYPO3 v14.3 dependencies
```

## Development

```sh
composer install
vendor/bin/phpstan analyse
vendor/bin/phpcs
vendor/bin/phpunit
```
