# Standalone / FrankenPHP prototype

> Terse runbook. The full tutorial, architecture rationale, and the complete
> build-failure catalog live in [`../standalone.md`](../standalone.md).

Run CPR Agent as a self-hosted app with **zero external services**: SQLite,
file cache/sessions, database queue, and [FrankenPHP](https://frankenphp.dev)
(embedded Caddy + PHP) serving the app with Reverb proxied on the same origin.

Players only ever see **one host and one port** — Caddy reverse-proxies the
`/app/*` WebSocket path to the internal Reverb process, so no second port is
exposed and no mixed-content/TLS gymnastics are needed. The frontend derives
the WebSocket endpoint from the page origin at runtime (see
`resources/views/partials/reverb-config.blade.php`), so prebuilt assets work
on any host without rebuilding.

## Mode A — FrankenPHP binary + repo checkout (works today)

```bash
# 1. Get FrankenPHP (static binary, includes PHP + Caddy)
curl -L -o standalone/bin/frankenphp \
  https://github.com/php/frankenphp/releases/latest/download/frankenphp-linux-x86_64
chmod +x standalone/bin/frankenphp

# 2. Configure: edit .env.standalone (APP_KEY, REVERB_APP_KEY/SECRET,
#    absolute DB_DATABASE path). Loaded because APP_ENV=standalone.

# 3. Migrate
touch standalone/database.sqlite
APP_ENV=standalone ./standalone/bin/frankenphp php-cli artisan migrate --force

# 4. Run (two processes; queue worker optional but needed for char-data persistence)
APP_ENV=standalone ./standalone/bin/frankenphp php-cli artisan reverb:start &
APP_ENV=standalone ./standalone/bin/frankenphp php-cli artisan queue:work &
APP_ENV=standalone ./standalone/bin/frankenphp run --config standalone/Caddyfile
```

App: http://localhost:8000 — WebSockets ride the same origin via `/app/*`.

Assets must exist in `public/build/` (`ddev npm run build`) before serving.

## Mode B — single self-contained binary (embedded app)

`static-build.Dockerfile` compiles the entire app INTO the FrankenPHP binary
(via the official `static-builder-gnu` image — long build, compiles PHP from
source):

```bash
docker build -t cpr-agent-static -f standalone/static-build.Dockerfile .
docker cp $(docker create --name cpr-tmp cpr-agent-static):/go/src/app/dist/frankenphp-linux-x86_64 cpr-agent
docker rm cpr-tmp
```

Run the result anywhere (glibc linux x86_64). **Run from a directory that is
NOT a Laravel checkout** — `php-cli artisan` resolves `artisan` from the
current directory first, so inside a checkout you'd boot the checkout instead
of the embedded app:

```bash
mkdir -p ~/.cpr-agent/storage/framework/{cache/data,sessions,views} ~/.cpr-agent/storage/{app,logs}
cd ~/.cpr-agent
export APP_KEY="base64:$(openssl rand -base64 32)"
export REVERB_APP_KEY=$(openssl rand -hex 16)
export REVERB_APP_SECRET=$(openssl rand -hex 16)
export DB_DATABASE=$HOME/.cpr-agent/database.sqlite
export LARAVEL_STORAGE_PATH=$HOME/.cpr-agent/storage   # embedded app extracts to a temp dir per version

touch $DB_DATABASE
./cpr-agent php-cli artisan migrate --force
./cpr-agent php-cli artisan reverb:start &
./cpr-agent php-cli artisan queue:work &
./cpr-agent run --config Caddyfile.embedded   # copy from standalone/Caddyfile.embedded
```

Defaults embedded in the binary come from `standalone/.env.embed`; real
environment variables always win.

Verified end-to-end 2026-07-05: page render, Pusher-protocol WebSocket
handshake through the `/app/*` proxy, and a Laravel→Reverb→client broadcast
round-trip, all against the sealed binary on fresh state.

## Sharing with players

Any of:
- **LAN**: players browse to `http://<your-ip>:8000` (http origin → `ws://`, fine on LAN).
- **Tailscale Funnel / Cloudflare Tunnel**: point the tunnel at port 8000 —
  both proxy WebSockets; players get a proper `https://` URL.
- **Public domain**: change the Caddyfile site address from `:8000` to your
  domain and Caddy provisions TLS automatically.

## Build gotchas (hard-won, 2026-07-05)

- The official docs' single-stage recipe fails: `static-builder-gnu` ships no
  php/composer. Hence the `composer:2` deps stage.
- **ext-curl is deliberately excluded.** The builder's prebuilt `libcurl.a`
  references ldap/libssh2/zstd/ngtcp2 symbols that spc's dependency resolution
  fails to assemble — every added lib shifted the missing-symbol set. Guzzle
  falls back to the PHP stream handler (TLS via openssl); nothing in this app
  calls `curl_*` directly.
- `mbregex` must be listed as its own extension or `mb_split()` is missing
  and Laravel dies on boot. `phar` is included so tinker works.
- Repeated builds hit GitHub's anonymous rate limit — pass a token:
  `GITHUB_TOKEN=$(gh auth token) docker build --secret id=github-token,env=GITHUB_TOKEN ...`
- The build needs ~10GB free disk; the linker fails cryptically without it.

## Known rough edges (prototype)

- Port 8085 chosen for Reverb because ddev occupies 8080 on the dev box.
- The three processes (server, reverb, queue) want a supervisor for real use —
  a tiny `agent:serve` artisan command that spawns reverb + queue as child
  processes would make this a true one-command launch.
- Mode B binary is Linux x86_64; macOS/Windows need their own builds.
- PHP tests exit 1 despite 167/167 passing — pre-existing, unrelated (likely
  deprecation-related exit code).
