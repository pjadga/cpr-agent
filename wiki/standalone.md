---
title: "Self-Hosting: Standalone (FrankenPHP)"
order: 4
---

# Standalone Self-Hosting: FrankenPHP Tutorial & Build Log

> Preface: This is currently VERY buggy - one of the few places I've enlisted Claude to
> help more than I've put in. As it stands, there are some Repository steps that are 
> required for this to work properly, and currently I do not have the pipeline sorted to
> generate the necessary files for download. If your technically minded, have at it. Otherwise,
> in future updates this will be part of the release. X

The idea here is that the Agent can run as a self-hosted app with **zero external
services**. No Docker knowledge, no PHP install, no npm, no Redis, no MariaDB.
This page is the full tutorial, plus a log of how the build came together: every
decision, every failure and every fix from the 2026-07-05 prototype session, so
nobody (me included) has to work any of it out again.

The short, just-the-commands version lives in [`standalone/README.md`](standalone/README.md).
This is the long version.

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

Two decisions make this whole thing work:

**One origin, one port.** Reverb never gets its own public port. Caddy
reverse-proxies the Pusher-protocol WebSocket path (`/app/*`) to the Reverb
process running inside. Players connect to the same host and port they loaded
the page from, so there are no mixed-content problems, no second TLS cert and
no extra firewall rule. So if you've ever asked "doesn't the Reverb port throw a
spanner in self-hosting?", this is the answer: from the outside, the port just
doesn't exist.

**WebSocket config at runtime, not build time.** The frontend used to read
`VITE_REVERB_*` from `import.meta.env`, which got baked into the JS bundle when
Vite built it. That meant every self-hoster would have needed Node and a full
asset rebuild just to point the app at their own domain. Not great. Now
`resources/views/partials/reverb-config.blade.php` injects
`window.reverbConfig` (key/host/port/scheme from Laravel config) into every
page, and `resources/js/bootstrap.js` works through each value in this order:

```
window.reverbConfig  →  import.meta.env.VITE_REVERB_*  →  window.location
```

The last fallback takes host, port and scheme from the page's own address,
which is exactly right whenever Reverb is proxied on the same origin. End
result: **one prebuilt asset bundle works on any host.** ddev and existing
`.env` files carry on working as they were, because the same `VITE_REVERB_*`
names feed the middle of the chain.

### Zero-external-services profile

| Concern   | Driver              | Why it's enough                           |
|-----------|---------------------|-------------------------------------------|
| Database  | SQLite              | One GM + a table of players is tiny load   |
| Cache     | `file`              | App only uses the `Cache` facade           |
| Sessions  | `file`              | Same                                       |
| Queue     | `database`          | `SyncCharacterDataToDb` etc. still async   |
| WebSocket | Reverb (child proc) | Proxied same-origin                        |

Redis and MariaDB are still there if you're running something bigger. They're
config, not code.

---

## 2. Two ways to run it

### Mode A: FrankenPHP binary + repo checkout

For anyone happy with `git clone`. One downloaded binary gives you PHP and the
web server, and the app runs from your checkout.

```bash
# 1. Get FrankenPHP (static binary: PHP + Caddy in one file)
curl -L -o standalone/bin/frankenphp \
  https://github.com/php/frankenphp/releases/latest/download/frankenphp-linux-x86_64
chmod +x standalone/bin/frankenphp

# 2. Configure .env.standalone: set APP_KEY, REVERB_APP_KEY/SECRET and an
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

App on `http://localhost:8000`, with WebSockets riding `/app/*` on the same origin.

### Mode B: one sealed binary (the app *inside* the executable)

For anyone who should never have to see a git checkout. The whole application
(PHP runtime, Caddy, app code, vendor, prebuilt assets and a default `.env`)
gets compiled into a single ~136MB executable.

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

**Run it** from a directory that is *not* a Laravel checkout (see gotcha G8
below):

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

The binary carries `standalone/.env.embed` as its default config, but **real
environment variables always win**. That's how one binary serves every install
without a rebuild.

### Sharing with players

- **LAN**: `http://<gm-ip>:8000`. A plain-http page means plain `ws://`, which
  browsers are fine with (mixed-content rules only bite on `https://` pages).
- **Tailscale Funnel / Cloudflare Tunnel**: point the tunnel at port 8000.
  Both proxy WebSockets, so players get a real `https://` URL and the GM
  doesn't forward any ports.
- **Public domain**: change the Caddyfile site address from `:8000` to your
  domain. Caddy sorts out Let's Encrypt TLS for you, and thanks to the
  `window.location` fallback the frontend needs no reconfiguring at all.

---

## 3. How I got here

Some context. I was weighing up self-hosting options. Docker Compose is the
main route for the people who already run Foundry in Docker. I looked at
NativePHP and ruled it out: the Agent is a hub with lots of clients (GM,
players and the Bridge), and NativePHP desktop apps are deliberately built as
single-user localhost apps. FrankenPHP won as the "easy mode" because its
built-in Caddy gives you auto-TLS and WebSocket proxying in the same file as
the PHP runtime.

### The code changes that unblocked everything

| Change | File(s) | Why |
|--------|---------|-----|
| Runtime Echo config injection | `resources/views/partials/reverb-config.blade.php`, `config/broadcasting.php` (`frontend` block), all three layouts | Kills the Vite build-time coupling; prebuilt assets work anywhere |
| Fallback chain + same-origin port inheritance | `resources/js/bootstrap.js` | `window.location.port` matters when proxying on a non-standard port (`localhost:8000`) |
| `PDO::MYSQL_ATTR_SSL_CA` → `Pdo\Mysql::ATTR_SSL_CA` | `config/database.php` | PHP 8.5 (FrankenPHP ships 8.5) deprecated the old constant and printed warnings above every page; the class exists since 8.4 so ddev is unaffected |

Every suite stayed green the whole way through: 167 PHP tests, 64 JS tests,
Pint clean. (One oddity: `composer test` exits 1 on a clean tree even though
every test passes. It was like that before, and I still don't know why.)

### Nine goes at the build (Mode B)

Every failure below is now baked into `standalone/static-build.Dockerfile` or
the runbook, so it can't bite again.

1. **`composer: command not found` (exit 127).** The official FrankenPHP
   docs recipe runs `composer install` inside `static-builder-gnu`, but that
   image ships *neither composer nor php*. Fix: a multi-stage build with a
   `composer:2` deps stage. Bonus lesson: the failure first reported exit 0,
   because the build output was piped through `tail`, which swallowed the exit
   code. Log to a file and append the status instead.
2. **`ld: final link failed: No space left on device.`** The disk was 100% full
   (1.1GB free of 229GB). Pruning Docker's builder cache got back almost
   nothing, so it needed a proper clear-out. The static build wants ~10GB of
   headroom, and the linker error says nothing at all about disk space.
3. **`undefined reference to ldap_*` / `ber_*`.** The builder's *prebuilt*
   `libcurl.a` was compiled with OpenLDAP support, but spc doesn't put
   openldap in the link set. First workaround: `PHP_EXTENSION_LIBS=openldap`.
4. **`Library [openldap] not exist!`** Wrong name, spc has no lib called that.
   Second go: add `ldap` as a PHP *extension* so its lib comes along with it.
5. **`undefined reference to libssh2_* / ZSTD_*`.** The ldap symbols
   resolved, but libcurl also wants libssh2 and zstd. Third go:
   `PHP_EXTENSION_LIBS=libssh2,zstd`.
6. **GitHub 403 rate limit** downloading the new lib sources. All the repeat
   builds burned through the anonymous API quota. Fix: pass `gh auth token` as
   a BuildKit secret (`--secret id=github-token,env=GITHUB_TOKEN`) so it never
   ends up in the image layers.
7. **`undefined reference to ngtcp2_* / nghttp2_* / nghttp3_*`.** These libs
   linked fine on attempt 3! Adding libs was *shifting* spc's resolution set,
   not adding to it. Which smells like an upstream dependency-resolution bug
   around the prebuilt curl, so I stopped playing whack-a-mole. **Fix: drop
   `ext-curl` entirely.** Guzzle quietly falls back to PHP's stream handler
   (TLS via openssl), nothing in the app calls `curl_*` directly, and
   composer.json doesn't need it. Build succeeded.
8. **`Call to undefined function mb_split()`** on first boot. spc's mbstring
   builds with `--disable-mbregex` unless... (tried `PHP_EXTENSION_LIBS=onig`,
   which built and linked `libonig.a`, but PHP still configured
   `--disable-mbregex`).
9. **`mbregex` is its own spc extension.** Adding it to `PHP_EXTENSIONS`
   (it pulls in onig itself) sorted it: `mb_split()`/`mb_ereg()` present,
   Laravel boots, migrations run. Done. (`phar` went in at the same time so
   `artisan tinker` works in the sealed binary.)

### Runtime gotchas (Mode B)

- **G8: artisan resolution.** `./cpr-agent php-cli artisan …` looks for
  `artisan` in the current directory *first*. Run it inside a Laravel checkout
  and you boot the checkout instead, `.env` and all (I watched it try to
  connect to ddev's `mariadb` on host `db`). Always run it from a clean
  directory, so the embedded app's files come from the binary's extraction
  dir under `/tmp/frankenphp_<hash>/`.
- **Storage has to live outside the binary.** Each binary version extracts to
  a fresh temp dir, so set `LARAVEL_STORAGE_PATH` somewhere permanent and
  create the skeleton first (`framework/{cache/data,sessions,views}`, `app`,
  `logs`).
- **Secrets come from the environment.** The baked-in `.env.embed` leaves
  `APP_KEY`, the Reverb credentials and `DB_DATABASE` empty on purpose. Whatever
  launches it supplies them (env vars beat `.env` in Laravel).
- **Local port clashes.** Reverb uses 8085 in these configs because ddev holds
  8080 on my dev box. Any free localhost port works, it's internal only.

### What "works" actually means here

Every check ran against the sealed binary on completely fresh state
(`~/.cpr-agent-proto`), not just the checkout:

1. **Page render.** A headless-Firefox screenshot of the served login page.
2. **Runtime config.** The served HTML has `window.reverbConfig` with only the
   key in it (host/port/scheme correctly missing, so the client works them out
   from the origin).
3. **Transport.** A Bun WebSocket client did the real Pusher-protocol
   handshake against `ws://localhost:8000/app/<key>` (through the Caddy
   proxy) and got `pusher:connection_established` back. I cross-checked the
   socket_id in Reverb's debug log.
4. **The full pipe.** `Broadcast::on('embed-test')->as('EmbedTest')->sendNow()`
   fired from the embedded Laravel runtime and turned up at the subscribed
   client: `{"msg":"single binary, night city"}`. The queued path (database
   driver + `queue:work`) got the same treatment in Mode A, including a job
   that survived worker restarts.

### Still to do

- An `agent:serve` artisan command that looks after reverb and the queue as
  child processes. Three commands become one, and a menubar/NativePHP
  *launcher* becomes easy later.
- CI to build and publish binaries for each release (Linux first; macOS and
  Windows need their own FrankenPHP builds).
- A Docker Compose stack for the "I already run Foundry in Docker" crowd. It
  shares every code change with this work.
- A proper first run: generate keys automatically on first boot instead of
  asking people to run `openssl`.
