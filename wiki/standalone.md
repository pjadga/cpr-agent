# Standalone Self-Hosting — FrankenPHP Tutorial & Build Catalog

CPR Agent can run as a self-hosted app with **zero external services** — no
Docker knowledge, no PHP install, no npm, no Redis, no MariaDB. This document
is the full tutorial plus a catalog of the build process: every decision,
every failure, and every fix from the 2026-07-05 prototype session, so the
next person (or future us) doesn't rediscover any of it.

The terse operational runbook lives in [`standalone/README.md`](standalone/README.md).
This file is the long-form companion.

---

## 1. Architecture

```
Players' phones/browsers                 GM's machine
────────────────────────                 ─────────────────────────────────────
https://host  ──────────────▶  Caddy (FrankenPHP) :8000
                                 ├── /app/*  ──▶  Reverb :8085 (localhost only)
                                 └── everything else ──▶ PHP (the Laravel app)
                                                            │
Foundry VTT ◀── Agent Bridge ──── /api/foundry/callback ────┘
```

Two design decisions make this work:

**One origin, one port.** Reverb never gets its own public port. Caddy
reverse-proxies the Pusher-protocol WebSocket path (`/app/*`) to the internal
Reverb process. Players connect to the same host and port they loaded the
page from, so there are no mixed-content problems, no second TLS cert, and
no extra firewall rule. This is the answer to "doesn't the Reverb port throw
a spanner in self-hosting?" — the port simply doesn't exist externally.

**Runtime WebSocket config, not build-time.** Historically the frontend read
`VITE_REVERB_*` from `import.meta.env`, baked into the JS bundle when Vite
built it — meaning every self-hoster would have needed Node + a full asset
rebuild to point the app at their own domain. Now
`resources/views/partials/reverb-config.blade.php` injects
`window.reverbConfig` (key/host/port/scheme from Laravel config) into every
page, and `resources/js/bootstrap.js` resolves each value through a fallback
chain:

```
window.reverbConfig  →  import.meta.env.VITE_REVERB_*  →  window.location
```

The final fallback derives host, port, and scheme from the page's own origin
— which is exactly correct whenever Reverb is same-origin proxied. Net
effect: **one prebuilt asset bundle works on any host.** ddev and existing
`.env` files keep working unchanged because the same `VITE_REVERB_*` names
feed the middle of the chain.

### Zero-external-services profile

| Concern   | Driver              | Why it's enough                           |
|-----------|---------------------|-------------------------------------------|
| Database  | SQLite              | One GM + a table of players is tiny load   |
| Cache     | `file`              | App only uses the `Cache` facade           |
| Sessions  | `file`              | Same                                       |
| Queue     | `database`          | `SyncCharacterDataToDb` etc. still async   |
| WebSocket | Reverb (child proc) | Proxied same-origin                        |

Redis and MariaDB remain available for bigger installs — they're config, not
code.

---

## 2. Two distribution modes

### Mode A — FrankenPHP binary + repo checkout

For people comfortable with `git clone`. One downloaded binary provides PHP
and the web server; the app runs from the checkout.

```bash
# 1. Get FrankenPHP (static binary: PHP + Caddy in one file)
curl -L -o standalone/bin/frankenphp \
  https://github.com/php/frankenphp/releases/latest/download/frankenphp-linux-x86_64
chmod +x standalone/bin/frankenphp

# 2. Configure .env.standalone — set APP_KEY, REVERB_APP_KEY/SECRET and an
#    absolute DB_DATABASE path. It loads automatically because APP_ENV=standalone.

# 3. Build assets once (any machine with node): npm run build
#    (prebuilt public/build/ can also be shipped in a release tarball)

# 4. Migrate and run
touch standalone/database.sqlite
APP_ENV=standalone ./standalone/bin/frankenphp php-cli artisan migrate --force
APP_ENV=standalone ./standalone/bin/frankenphp php-cli artisan reverb:start &
APP_ENV=standalone ./standalone/bin/frankenphp php-cli artisan queue:work &
APP_ENV=standalone ./standalone/bin/frankenphp run --config standalone/Caddyfile
```

App on `http://localhost:8000`; WebSockets ride `/app/*` on the same origin.

### Mode B — one sealed binary (the app *inside* the executable)

For people who should never see a git checkout. The entire application —
PHP runtime, Caddy, app code, vendor, prebuilt assets, a default `.env` —
is compiled into a single ~136MB executable.

**Build it** (needs Docker + ~10GB free disk; produces `cpr-agent`):

```bash
ddev npm run build   # assets must exist in public/build/ before the docker build

GITHUB_TOKEN=$(gh auth token) docker build \
  --secret id=github-token,env=GITHUB_TOKEN \
  -t cpr-agent-static -f standalone/static-build.Dockerfile .

docker create --name cpr-tmp cpr-agent-static
docker cp cpr-tmp:/go/src/app/dist/frankenphp-linux-x86_64 cpr-agent
docker rm cpr-tmp
```

**Run it** — from a directory that is *not* a Laravel checkout (see gotcha
G8 below):

```bash
mkdir -p ~/.cpr-agent/storage/framework/{cache/data,sessions,views} ~/.cpr-agent/storage/{app,logs}
cd ~/.cpr-agent
export APP_KEY="base64:$(openssl rand -base64 32)"
export REVERB_APP_KEY=$(openssl rand -hex 16)
export REVERB_APP_SECRET=$(openssl rand -hex 16)
export DB_DATABASE=$HOME/.cpr-agent/database.sqlite
export LARAVEL_STORAGE_PATH=$HOME/.cpr-agent/storage

touch $DB_DATABASE
./cpr-agent php-cli artisan migrate --force
./cpr-agent php-cli artisan reverb:start &
./cpr-agent php-cli artisan queue:work &
./cpr-agent run --config Caddyfile.embedded    # copy from standalone/Caddyfile.embedded
```

The binary carries `standalone/.env.embed` as its default config; **real
environment variables always override it** — that's how one binary serves
every install without a rebuild.

### Sharing with players

- **LAN**: `http://<gm-ip>:8000` — plain-http origin means plain `ws://`,
  which browsers allow (mixed-content rules only bite on `https://` pages).
- **Tailscale Funnel / Cloudflare Tunnel**: point the tunnel at port 8000.
  Both proxy WebSockets; players get a real `https://` URL and the GM
  forwards no ports.
- **Public domain**: change the Caddyfile site address from `:8000` to the
  domain — Caddy provisions Let's Encrypt TLS automatically, and the
  `window.location` fallback means the frontend needs zero reconfiguration.

---

## 3. The process catalog — how we got here

Context: we were planning self-hosting options. Docker Compose is the
primary path for the Foundry-hosting crowd; NativePHP was considered and
rejected (CPR Agent is a multi-client hub — GM + players + the Bridge — while
NativePHP desktop deliberately builds single-user localhost apps); FrankenPHP
was chosen as the "easy mode" because its embedded Caddy gives auto-TLS and
WebSocket proxying in the same artifact as the PHP runtime.

### Code changes that unblocked everything

| Change | File(s) | Why |
|--------|---------|-----|
| Runtime Echo config injection | `resources/views/partials/reverb-config.blade.php`, `config/broadcasting.php` (`frontend` block), all three layouts | Kills the Vite build-time coupling; prebuilt assets work anywhere |
| Fallback chain + same-origin port inheritance | `resources/js/bootstrap.js` | `window.location.port` matters when proxying on a non-standard port (`localhost:8000`) |
| `PDO::MYSQL_ATTR_SSL_CA` → `Pdo\Mysql::ATTR_SSL_CA` | `config/database.php` | PHP 8.5 (FrankenPHP ships 8.5) deprecated the old constant and printed warnings above every page; the class exists since 8.4 so ddev is unaffected |

All suites stayed green throughout: 167 PHP tests, 64 JS tests, Pint clean.
(Note: `composer test` exits 1 on a clean tree despite all tests passing —
pre-existing quirk, still unexplained.)

### The nine build attempts (Mode B)

Every failure below is now permanently encoded in
`standalone/static-build.Dockerfile` or the runbook.

1. **`composer: command not found` (exit 127).** The official FrankenPHP
   docs recipe runs `composer install` inside `static-builder-gnu` — but that
   image ships *neither composer nor php*. Fix: multi-stage build with a
   `composer:2` deps stage. Bonus lesson: the failure originally reported
   exit 0 because the build output was piped through `tail`, which swallowed
   the exit code — log to a file and append the status instead.
2. **`ld: final link failed: No space left on device.`** Disk was 100% full
   (1.1GB free of 229GB). Docker builder-cache pruning reclaimed almost
   nothing real; actual cleanup was needed. The static build wants ~10GB
   headroom, and the linker error says nothing about disk.
3. **`undefined reference to ldap_*` / `ber_*`.** The builder's *prebuilt*
   `libcurl.a` was compiled with OpenLDAP support, but spc doesn't put
   openldap in the link set. First workaround attempt: `PHP_EXTENSION_LIBS=openldap`.
4. **`Library [openldap] not exist!`** — wrong name; spc has no lib by that
   name. Second attempt: add `ldap` as a PHP *extension* so its lib rides in.
5. **`undefined reference to libssh2_* / ZSTD_*`.** The ldap symbols
   resolved, but libcurl also wants libssh2 and zstd. Third attempt:
   `PHP_EXTENSION_LIBS=libssh2,zstd`.
6. **GitHub 403 rate limit** downloading the new lib sources — anonymous API
   quota burned by the repeated builds. Fix: pass `gh auth token` as a
   BuildKit secret (`--secret id=github-token,env=GITHUB_TOKEN`) so it never
   lands in image layers.
7. **`undefined reference to ngtcp2_* / nghttp2_* / nghttp3_*`** — libs that
   linked fine in attempt 3! Adding libs was *shifting* spc's resolution set,
   not extending it. Conclusion: upstream dependency-resolution bug around
   the prebuilt curl; stop playing whack-a-mole. **Fix: drop `ext-curl`
   entirely.** Guzzle transparently falls back to PHP's stream handler (TLS
   via openssl); nothing in the app calls `curl_*` directly and composer.json
   doesn't require it. Build succeeded.
8. **`Call to undefined function mb_split()`** at first boot. spc's mbstring
   builds with `--disable-mbregex` unless... (attempt: `PHP_EXTENSION_LIBS=onig`
   — built and linked `libonig.a` but PHP still configured `--disable-mbregex`).
9. **`mbregex` is a separate spc extension.** Adding it to `PHP_EXTENSIONS`
   (it pulls onig itself) fixed it: `mb_split()`/`mb_ereg()` present, Laravel
   boots, migrations run. Done. (`phar` was added at the same time so
   `artisan tinker` works in the sealed binary.)

### Runtime gotchas (Mode B)

- **G8 — artisan resolution.** `./cpr-agent php-cli artisan …` resolves
  `artisan` from the current directory *first*. Run it inside a Laravel
  checkout and you boot the checkout — complete with its `.env` (we watched
  it try to connect to ddev's `mariadb` on host `db`). Always run from a
  clean directory; the embedded app's files then resolve from the binary's
  extraction dir under `/tmp/frankenphp_<hash>/`.
- **Storage must live outside the binary.** Each binary version extracts to
  a fresh temp dir, so set `LARAVEL_STORAGE_PATH` to a persistent location
  and pre-create the skeleton (`framework/{cache/data,sessions,views}`,
  `app`, `logs`).
- **Secrets via environment.** The baked `.env.embed` deliberately leaves
  `APP_KEY`, Reverb credentials, and `DB_DATABASE` empty; the launcher
  environment supplies them (env vars beat `.env` in Laravel).
- **Local port collisions.** Reverb uses 8085 in these configs because ddev
  holds 8080 on the dev box. Any free localhost port works — it's internal.

### Verification (what "works" means here)

All checks ran against the sealed binary on completely fresh state
(`~/.cpr-agent-proto`), not just the checkout:

1. Page render — headless-Firefox screenshot of the served login page.
2. Runtime config — served HTML contains `window.reverbConfig` with only the
   key (host/port/scheme correctly absent → client derives from origin).
3. Transport — a Bun WebSocket client performed the real Pusher-protocol
   handshake against `ws://localhost:8000/app/<key>` (through the Caddy
   proxy) and received `pusher:connection_established`; the socket_id was
   cross-checked in Reverb's debug log.
4. Full pipe — `Broadcast::on('embed-test')->as('EmbedTest')->sendNow()`
   fired from the embedded Laravel runtime arrived at the subscribed client:
   `{"msg":"single binary, night city"}`. The queued path (database driver +
   `queue:work`) was verified the same way in Mode A, including a job that
   persisted across worker restarts.

### Not done yet (productization backlog)

- `agent:serve` artisan command supervising reverb + queue as child
  processes — turns three commands into one, and makes a menubar/NativePHP
  *launcher* shell trivial later.
- CI to build and publish per-release binaries (Linux first; macOS/Windows
  need their own FrankenPHP builds).
- Docker Compose stack as the parallel "I already run Foundry in Docker"
  path — shares every code change with this work.
- First-run experience: auto-generate keys on first boot instead of asking
  users to run `openssl`.
