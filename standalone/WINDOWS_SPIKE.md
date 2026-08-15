# Running the standalone build on native Windows

Findings from the Windows spike, tested on 14 August 2026 against
FrankenPHP 1.12.7 (PHP 8.5.9 ZTS, Caddy 2.11.4) on Windows 11.

The question was whether we can ship the self hosted app to a Windows user who
installs nothing: no PHP, no Composer, no Node, no WSL. They download one
thing, run it, and a browser opens on the app.

The answer is yes. Everything below is what we learned getting there.

## Summary

| Question | Answer |
| --- | --- |
| Does Reverb run natively on Windows, without WSL? | Yes |
| Can we ship a single self contained .exe? | No, but we can still ship a single download |
| Packaging shape | A zip of the app folder, wrapped in an installer |
| Does the app actually work end to end? | Yes, including WebSockets |

Two things surprised us and are worth reading before anything else: the
Windows FrankenPHP build needs a `php.ini` we supply ourselves, and
`agent:serve` was leaving orphaned processes behind on Windows. Both are
covered below and the second is now fixed.

## Before anything else: the binary needs a php.ini

The Windows release of FrankenPHP is not a static binary like the Linux one.
It is the standard PHP for Windows distribution with `frankenphp.exe` dropped
in. Extensions ship as separate DLLs in `.\ext\`, and the archive contains no
`php.ini` at all.

Without one, PHP boots with only its built in extensions. No mbstring, no
pdo_sqlite, no openssl. Laravel dies immediately.

You will see errors about mbstring, typically `Call to undefined function
mb_split()`, and `php_ini_loaded_file()` returns nothing.

The fix is to copy [`php.ini-windows`](php.ini-windows) next to
`frankenphp.exe` and rename it to `php.ini`:

```powershell
copy standalone\php.ini-windows C:\bin\frankenphp\php.ini
```

Two details make this file safe to ship as is. The directory holding the exe
is on PHP's ini search path on Windows, so the file is found no matter which
directory you run from. And a relative `extension_dir = "ext"` resolves
against the exe directory rather than the working directory. Both were
verified from an unrelated working directory, and for both `php-cli` and the
`frankenphp` server, which read the same file.

### A related trap in .env

`.env.standalone` originally had:

```
DB_DATABASE="C:\cpr-agent\standalone\database.sqlite"
```

Dotenv processes escape sequences inside double quotes, reads `\c` as an
invalid one, and refuses to load the file with "The environment file is
invalid!". Use forward slashes and no quotes:

```
DB_DATABASE=C:/cpr-agent/standalone/database.sqlite
```

Both traps are now flagged in `.env.standalone.example` so the next person
does not lose an hour to them.

## What was tested, and what happened

### Reverb runs natively

This was the make or break question. Reverb is built on ReactPHP, and native
Windows has no pcntl, so there was a real chance the signal handling or event
loop would not start at all. If it had failed, the whole no WSL promise would
have needed rethinking, not repackaging.

It runs. `artisan reverb:start` came up clean, stayed up, and `netstat`
confirmed `127.0.0.1:8085 LISTENING`. No missing extension errors, no event
loop complaints. `artisan migrate --force` ran all 21 migrations against
SQLite.

### The agent commands work

Both `agent:install` and `agent:serve` were verified on Windows under
FrankenPHP.

`agent:install --show` generated an APP_KEY and Reverb credentials, wrote them
back into `.env.standalone`, created the SQLite file, built the storage
skeleton, ran the migrations, and exited cleanly.

`agent:serve` migrated and then supervised both children, giving three
`frankenphp.exe` processes with Reverb listening on 8085.

`AGENT_PHP_ARGS=php-cli` does exactly what it was designed for. `PHP_BINARY`
resolves to `C:\bin\frankenphp\frankenphp.exe`, so children are spawned as
`frankenphp.exe php-cli C:\...\artisan reverb:start`. You can confirm this
without starting anything using `agent:serve --dry-run`.

### The app boots and talks

After `npm run build` populated `public/build/`, all three runtime checks
passed.

The site renders. `GET /` returns 200 with no Vite error, and `/login` renders
with a CSRF token and password field.

Authentication works. Registration posts successfully and redirects to
`/dashboard`, the user row lands in SQLite, and `/dashboard` returns 200 with
the session cookie and redirects to `/login` without it. Turnstile is
correctly absent from the form when the keys are blank. The registration form
requires a `role` field of `player` or `gm`, which is easy to miss when
testing by hand.

WebSockets work, which was the last real unknown. A Pusher protocol client
connected through Caddy's `/app/*` proxy, completed the handshake, subscribed
to a public channel, and then received an event broadcast from the Laravel
side with its full payload. The whole Laravel to Reverb to browser round trip
works natively on Windows.

The full PHP test suite passes: 188 tests, 423 assertions.

## The orphaned process bug

Worth reading in full, because it would have shipped as a bug the user hit on
their second launch.

`AgentServe` used to claim in its docblock that child processes are terminated
when the parent exits. That is not true on Windows. Force killing the
`agent:serve` process left both children alive and still holding port 8085.
Symfony Process spawns them through an intermediate shell, so the parent PIDs
they report point at intermediates that have already exited, and nothing ties
a child's lifetime to the supervisor.

A graceful exit was always fine, because the normal shutdown path stops each
child and Symfony shells out to `taskkill /F /T`. The exposure was a hard kill,
and the user closing the launcher window, which Windows signals with
`CTRL_CLOSE_EVENT`. PHP does not expose that event, so it cannot be trapped.

For someone with no IT skills the symptom would have been: close the window,
double click again, and the app fails to start because Reverb cannot bind its
port.

Fixed in `AgentServe.php` with two defences.

1. Ctrl-C and Ctrl-Break are now trapped on Windows via
   `sapi_windows_set_ctrl_handler()`, not just through pcntl on Linux and
   macOS. The ordinary interactive quit now takes the clean shutdown path.
2. `reapStrays()` runs at startup and kills leftovers from a previous run.

The second one is what makes the window close case harmless, since it cleans
up on the next launch rather than trying to prevent the mess.

Reaping matches on command line rather than on a recorded PID, deliberately.
On Windows the PID we hold for a child can belong to a shell that has already
exited, and Windows recycles PIDs, so acting on a stored PID risks killing
something unrelated. Instead a process is killed only when every argument of
one of our child commands appears in its command line. That includes this
install's absolute artisan path, so a second checkout on the same machine, or
a DDEV container, is never touched. Pass `--no-reap` to skip the check.

Verified by reproducing the original fault: started `agent:serve`, force killed
only the supervisor, confirmed both children survived holding 8085, then
relaunched. The output showed both strays identified and killed, then Reverb
rebound the port cleanly. Two tests in `AgentServeTest` cover it.

A Windows Job Object with `JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE` is the only way
to guarantee children die with the parent. It was not used here because it
needs ext-ffi and hand written Win32 struct bindings, where a mistake in the
struct layout is an access violation in a consumer app. Reaping at startup
makes the symptom invisible to the user for far less risk. Revisit if we ever
ship this as a Windows Service.

## Packaging: why it is a zip and not one file

We cannot compile the app into a single `.exe` on Windows, for two independent
reasons.

`frankenphp build-static` does not exist in the Windows build. It returns
`unknown command`. The available subcommands are `php-cli`, `php-server`,
`run`, `reload`, `storage` and `manpage`, plus Caddy's own.

More fundamentally, the Windows build cannot be one file even in principle. It
depends on `php8ts.dll`, the `ext\*.dll` set, the ICU, OpenSSL and SQLite
DLLs, and now our `php.ini`. Embedding the app would not collapse any of that
into a single executable.

This costs the user nothing, because they still download and double click one
thing: an installer that unpacks the folder for them. The single file build
remains available on Linux.

### What goes in the bundle

- The whole extracted FrankenPHP directory: `frankenphp.exe`, `php8ts.dll`,
  `ext\`, and the ICU, OpenSSL and SQLite DLLs
- `php.ini`, copied from `standalone/php.ini-windows`
- A prebuilt `vendor/`, so the user never runs Composer
- A prebuilt `public/build/`, so the user never runs Node
- `Caddyfile`
- The launcher

## First run, and the installer

The user must never type a command. On first run the launcher has to set the
environment variables (`APP_ENV`, `AGENT_PHP_ARGS=php-cli`, `DB_DATABASE`,
`LARAVEL_STORAGE_PATH`) to paths under the user's profile, run `agent:install`
silently the first time only, start `agent:serve`, and open
`http://localhost:8000` in the default browser.

The whole experience should be: double click, browser opens, sign in.

Installing to `%LOCALAPPDATA%` while keeping the database and storage in the
user's profile also makes updates easy. Running a newer installer replaces the
app files and leaves the data alone.

### Firewall

Starting the web server triggers a Windows Defender prompt, because the
Caddyfile binds `:8000` on all interfaces. Reverb does not trigger it, since it
binds `127.0.0.1:8085`. Allowing it creates two inbound Allow rules on the
Private profile only.

A security prompt is exactly what we are trying to avoid for this audience.
**Decision: keep `:8000` and have the installer create the firewall rule at
install time**, where it already has admin rights. The user never sees the
prompt, and players can still reach the app over the LAN.

The alternative, binding `localhost:8000`, removes the prompt but also removes
LAN play, so it was rejected.

This is separate from the testing above. Windows Firewall never filters
loopback traffic, so the prompt did not affect any of the results in this
document.

## Notes and gotchas

`artisan test` does not work under FrankenPHP. Collision re-invokes
`PHP_BINARY vendor/phpunit/phpunit/phpunit`, and `frankenphp.exe` rejects a
bare script path as a subcommand. Run phpunit directly instead:

```powershell
.\frankenphp.exe php-cli vendor/phpunit/phpunit/phpunit
```

This is the same root cause as `AGENT_PHP_ARGS` and has nothing to do with the
app itself.

Reverb uses port 8085 because ddev occupies 8080 on the development machine.
Any free local port works.

## Still to do

- Build the Inno Setup installer and the launcher it calls
- Add the firewall rule to the installer
- CI to build `vendor/` and `public/build/` and publish the Windows artifact
- Consider a background Windows Service and tray icon later, so the app
  survives a reboot and shows no console window
