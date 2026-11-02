---
name: typo3-web-install
description: Install a TYPO3 14 website with typo3-install (standalone or inside an existing Docker stack), connect the typo3-mcp-server and build its content over MCP. Use when asked to set up, scaffold or install a TYPO3 site or web, add a TYPO3 website to a project, or fill a TYPO3 site's pages through the MCP server.
---

# Installing a TYPO3 website with typo3-install

This is what was learned building izbik.com (a TYPO3 site next to an app in one Docker stack, its content written
only over the MCP server). Follow it in order; each step says how to check it worked.

## 0. Decide

- **Standalone** (the site is the whole project): the installer writes `docker-compose.yml` with an nginx proxy (TLS).
- **Inside an existing stack** (an app already has its compose file, proxy and database): use `--skip-docker`, put the
  project in a subdirectory (e.g. `web/`), and wire it in by hand (step 4).
- **Languages**: `-l en,cs -d en` gives English at `/`, Czech at `/cs/`. Only `cs`, `en`, `de` are known.
- **MCP**: with `typo3-mcp-server`, pick the endpoint's path. If another app on the same host serves `/mcp`, use
  `--mcp-base-path /typo3-web/mcp`.
- Ask the user only what the code cannot tell: the hostname, the languages, which database (and whether a new one may
  be created on an existing server), and the admin's e-mail.

## 1. Where the installer runs

The database setup connects from where `install.sh` runs, with PHP (`pdo_mysql`, `mysqli`) and Composer.

- Database reachable from the host (local MySQL, a published port): run `./install.sh` on the host with
  `--db-host 127.0.0.1 --db-port <port>`.
- Database in a container whose port is not published, or the host's own MySQL already holds the port: run the
  installer **in a container on the database's network** and pass the service name (`--db-host db`). Build the image
  from `installer.Dockerfile` next to this file:

```sh
docker build -t typo3-installer -f .claude/skills/typo3-web-install/installer.Dockerfile .
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp --network <stack>_backend \
    -v /path/to/typo3-install:/installer -v "$OUT":/out -w /out typo3-installer \
    bash /installer/install.sh -y --name "Izbik" --hostname www.izbik.com --target-dir /out/web-install \
    -e core,google-font,features,faq,mcp-server --mcp-base-path /typo3-web/mcp -l en,cs -d en \
    --setup-db --db-host db --db-name izbik_web --db-user root --db-password "$ROOT_PW" \
    --admin-username admin --admin-email admin@example.com --admin-password "$ADMIN_PW" \
    --no-init-git [--skip-docker]
```

Generate the admin password (`openssl rand -base64 18`) and keep it out of the repository and the chat log; tell the
user where it is.

**Check:** exit 0, "Database setup complete!", pages 1–3 plus one translation of each per extra language listed.

## 2. After the installer

- Without pnpm in the installer's environment there is no `pnpm-lock.yaml`: run `pnpm install && pnpm build` in the
  project once (the image builds without it, unlocked).
- `.env` has the database as the site's container sees it (`host.docker.internal` for the host's own database, else
  the service name), `TYPO3_BASE_URL`, `TYPO3_ENCRYPTION_KEY` and `TYPO3_INSTALL_TOOL_PASSWORD`. Never commit `.env`;
  `config/system/settings.php` holds no secrets and is committed.
- The installer's database credentials (often root) end up as the site's `MYSQL_USER`. For production give the site
  its own user with rights on its database only.
- Standalone: put a certificate at `PROXY_SSL_CERT` / `PROXY_SSL_KEY` (a self-signed one: `openssl req -x509 -newkey
  rsa:2048 -nodes -days 825 -keyout ssl/server.key -out ssl/server.crt -subj "/CN=localhost"`), then
  `docker compose up -d --build`.

## 3. Standalone check

```sh
for u in / /cs/ /sitemap.xml /robots.txt /typo3/ /nope /cs/nope; do curl -sk -o /dev/null -w "$u %{http_code}\n" https://localhost:4200$u; done
```

Expect 200 everywhere except 404 for `/nope` and `/cs/nope` (not 500). `curl -sk https://localhost:4200/ | grep canonical`
shows an absolute URL.

## 4. Inside an existing stack (`--skip-docker`)

Move the output into the stack (e.g. `rsync` without `vendor/ var/ .env`), then:

- **Service** in the stack's compose file, built from the project's `Dockerfile`, on the proxy's network and the
  database's, with `MYSQL_HOST/PORT/DATABASE/USER/PASSWORD`, `TYPO3_CONTEXT`, `TYPO3_BASE_URL`, `TYPO3_ENCRYPTION_KEY`,
  `REVERSE_PROXY_IP` (the stack's subnets), volumes for `/var/www/html/public/fileadmin` and `/var/www/html/var` only,
  `depends_on` the database being healthy. Health check over TCP
  (`php -r "exit(@fsockopen('127.0.0.1', 80) ? 0 : 1);"`): an HTTP check fails when the site's base is not the
  container's own host.
- **Database**: a database of its own on the same server; a grant for the stack's user, and an init script
  (`/docker-entrypoint-initdb.d`) so fresh volumes get both. Running `GRANT` on an existing server is a manual,
  infrastructure step.
- **Proxy** (nginx): the site at `/`. With the MCP server under its own path, pass that path and its OAuth discovery
  documents to the site, as `^~` prefix locations so they win over an app's `location ~ ^/\.well-known/oauth-` regex
  (regex locations match in file order, prefix `^~` ones before any regex):

```nginx
location /typo3-web/mcp {
    proxy_buffering off; proxy_read_timeout 300s; client_max_body_size 50m;
    proxy_pass http://http_web; proxy_set_header Host $http_host;
    proxy_set_header X-Forwarded-Proto $scheme; proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}
location ^~ /.well-known/oauth-authorization-server/typo3-web/mcp { proxy_pass http://http_web; proxy_set_header Host $http_host; proxy_set_header X-Forwarded-Proto $scheme; }
location ^~ /.well-known/oauth-protected-resource/typo3-web/mcp { proxy_pass http://http_web; proxy_set_header Host $http_host; proxy_set_header X-Forwarded-Proto $scheme; }
```

- `.env.example` of the stack gets the website's variables; generate `TYPO3_ENCRYPTION_KEY` where the stack generates
  its other secrets.

**Check:** `/.well-known/oauth-protected-resource/<path>` returns `resource` and `authorization_servers` on the
public scheme and host; `POST <path>` answers 401 with `WWW-Authenticate: Bearer resource_metadata=…`; the stack's
other routes still answer as before.

## 5. Connect the MCP server

`claude mcp add --transport http <name> https://<host><mcp-base-path>`, then `/mcp` in Claude Code signs in through
the TYPO3 backend login. Extension records (FAQ, features, …) get their own tools only after an admin enables their
tables in the backend (System > MCP Server > Manage Extension Tables) or in `EXTCONF`; reconnect the client afterwards.

## 6. Build the content over MCP

Templates and SCSS are files; everything in the database goes through the MCP tools. What to know (typo3-mcp-server
1.4.1):

- **Order:** `pages_create` / `content_create` / `<prefix>_create` put the new record **first** on its page. Create
  the first one with the page's uid as `pid`, every next one with `pid: -<uid of the previous>` (after it). Fix order
  with `content_move` / `pages_move` (`afterUid`). `pages_list` sorts by uid, not by the tree's order.
- **New pages are hidden** (TYPO3's default); unhide them in one `record_update_batch` on `pages`.
- **Field names are TYPO3 14's**: an external-link page's target is `link`, not `url`. Watch `ignoredFields` in every
  result; `table_schema` lists the fields.
- **Images:** `file_upload_from_url` (e.g. raw files of a public repository), then `file_reference_add`; alt text,
  title and caption need `record_update_batch` on `sys_file_reference` with the uids it returned.
- **Plugins:** settings are XML in `pi_flexform`
  (`<T3FlexForms><data><sheet index="sDEF"><language index="lDEF"><field index="settings.x"><value index="vDEF">…`),
  the storage folder in `pages`.
- **Translations:**
  - Content without files: one call, `content_create` with `sysLanguageUid: 1` and `l18n_parent` (records:
    `l10n_parent`), the translated text in the same call.
  - Records with files or inline children (FAQ answers): `record_translate` (copies them along), then update the
    `[Translate to …:]` texts, alt texts included.
  - Pages: `record_translate`, then set title, slug and `hidden: 0`. The slug change reports a DataHandler error
    (the row *is* saved; issue #35) and EXT:redirects leaves redirects from the `translate-to-…` slugs: list them with
    `redirect_list` (`sourcePath: translate-to`) and delete them with `record_delete_batch` on `sys_redirect`.
- `cache_clear` (scope `pages`, or `all` after structural changes) before looking at the result.
- Content that must be editable by agents belongs in content elements and records, not in Fluid templates or
  language files.

Known MCP issues: https://github.com/marekskopal/typo3-mcp-server/issues (#35–#41 from this experience).

## 7. Final checks

- Every page and its translation: 200; the language switch links the page's own translation; hreflang and canonical
  are absolute (else `TYPO3_BASE_URL` is missing or relative).
- Phone width 375 px: no horizontal scroll (check in a real browser such as Playwright; headless Chrome screenshots
  narrower than ~500 px are not reliable).
- Lighthouse mobile (`npx lighthouse <url> --chrome-flags=--headless=new`): images need `srcset` for a good LCP;
  console errors would show CSP violations.
- The content lives in the database and `fileadmin`: deploying means moving both; nothing seeds it from code.
