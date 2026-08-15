# Windows installer

How to build the Windows release. Nothing in here is run by an end user: they
download one setup.exe, double click it, and get a Start Menu shortcut.

For why the Windows build works the way it does, read
[../WINDOWS_SPIKE.md](../WINDOWS_SPIKE.md) first. The short version is that
FrankenPHP on Windows is not a single static binary, so we ship a folder and
wrap it in an installer.

Claude helped a lot with the creation of the standalone code bundling - it's something I'd not done before and has been a huge learning experience.
The theory is based off Caddy and FrankenPHP with Inno6 used as a bundling tool to create an EXE.

Essentially - Inno puts together some powershell scripts in CLI wrappers to control the creation and running
of the Agent internals. This lets me use Caddy as the internal server and FrankenPHP to replace the php binary.

This creates a super efficient local server in-which Laravel (the agent) can run with minimal setup.


## Files

| File | What it is |
| --- | --- |
| `build-bundle.ps1` | Assembles `dist\bundle\` from a checkout plus a FrankenPHP release |
| `cpr-agent.iss` | Inno Setup script that packages the bundle into a setup.exe |
| `launcher.ps1` | What the shortcut runs: installs on first run, starts the app, opens a browser |
| `stop.ps1` | Stops this installation, without touching other copies on the machine |
| `Start CPR Agent.cmd` | Double click wrapper around `launcher.ps1` |
| `Stop CPR Agent.cmd` | Double click wrapper around `stop.ps1` |

## Building a release

You need PHP with Composer, Node, and Inno Setup 6 on the build machine, plus
the FrankenPHP Windows release from
https://github.com/php/frankenphp/releases.

```powershell
# 1. Assemble the bundle (runs composer and npm for you)
.\standalone\windows\build-bundle.ps1 -FrankenPhpZip C:\downloads\frankenphp-windows-x86_64.zip

# 2. Package it
& "C:\Program Files (x86)\Inno Setup 6\ISCC.exe" standalone\windows\cpr-agent.iss
```

The result is `dist\CPRAgent-Setup-<version>.exe`, around 230 MB unpacked.

Pass `-SkipDependencies` to reuse the `vendor\` and `public\build\` already in
your working copy instead of rebuilding them.

### Letting CI do the slow half

CI cannot build the installer, because `frankenphp.exe` is a Windows binary and
Inno Setup only runs on Windows. It can build everything that goes inside it.

The `standalone:windows-inputs` job produces `vendor\` (no dev dependencies)
and `public\build\` as a downloadable artifact. It runs automatically on tags,
and can be triggered manually from any branch.

So a release is:

1. Tag the release, or run the job manually from the pipeline
2. Download the artifact and unpack it over your checkout
3. Build and package:

```powershell
.\standalone\windows\build-bundle.ps1 -FrankenPhpZip C:\downloads\frankenphp-windows-x86_64.zip -SkipDependencies
& "C:\Program Files (x86)\Inno Setup 6\ISCC.exe" standalone\windows\cpr-agent.iss
```

This keeps Composer and npm off the Windows machine entirely, and means the
shipped `vendor\` is the one CI resolved rather than whatever happened to be on
a desktop.

The `standalone:check` job guards the file paths this script copies, so a
rename fails a pipeline in seconds instead of surfacing weeks later when
someone tries to cut a build.

Bump `AppVersion` in `cpr-agent.iss` for each release. Leave `AppId` alone:
Windows uses it to recognise an upgrade rather than a second install.

## Installed layout

```
%LOCALAPPDATA%\CPRAgent\
  runtime\   frankenphp.exe, php8ts.dll, ext\, php.ini
  app\       the Laravel app, vendor\, public\build\, Caddyfile
  data\      database.sqlite, storage\, logs\
```

The split is what makes updates safe. The installer replaces `runtime\` and
`app\`, and never writes to `data\`, so a new version keeps the user's
characters and accounts. Uninstalling asks before deleting `data\`.

The install directory has no space in it deliberately. Several layers pass
paths around as command arguments and a space is a needless source of quoting
bugs. `agent:serve` handles quoted paths correctly either way, but there is no
reason to lean on that.

## Decisions worth knowing

**Per user install, no elevation.** Installing into LocalAppData means no UAC
prompt, and the app can write its own database. The only thing needing admin is
the firewall rule, which is a separate opt in task and prompts on its own.
Declining it leaves the app working on localhost, just not reachable from other
devices.

**The firewall rule is created by the installer**, so the user never sees a
Windows Defender prompt when the app first starts. Without it, Windows prompts
on first launch because Caddy binds `:8000` on all interfaces, which is exactly
the kind of scary dialog this whole approach exists to avoid.

**The launcher waits for the port before opening a browser.** Opening it
immediately shows a connection error while the server is still starting, and a
user reasonably concludes the app is broken.

**The console window stays visible.** Closing it stops the app, which is a
discoverable way to quit, and it means errors are visible when something goes
wrong. A background service with a tray icon is the eventual goal, but it needs
somewhere to show errors first.

## Testing without building an installer

The bundle runs directly, which is much faster than compiling a setup.exe for
every change:

```powershell
.\standalone\windows\build-bundle.ps1 -FrankenPhpZip C:\bin\frankenphp -SkipDependencies
cd dist\bundle
.\launcher.ps1
```

Delete `dist\bundle\data\` to test the first run experience again from scratch.
