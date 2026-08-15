# CPR Agent launcher.
#
# Everything the user ever does is double click a shortcut that runs this. It
# points the app at the data directory, installs on first run, starts the
# server, and opens a browser once the port is actually answering.
#
# Layout it expects, all under one root:
#
#   CPRAgent\
#     runtime\   frankenphp.exe, php8ts.dll, ext\, php.ini
#     app\       the Laravel app, vendor\, public\build\, Caddyfile
#     data\      database.sqlite, storage\, logs\   (created here, never shipped)
#
# The split matters for updates: the installer replaces runtime\ and app\ and
# never touches data\, so a new version keeps the user's characters.

$ErrorActionPreference = 'Stop'

$root    = Split-Path -Parent $MyInvocation.MyCommand.Path
$runtime = Join-Path $root 'runtime'
$app     = Join-Path $root 'app'
$data    = Join-Path $root 'data'
$exe     = Join-Path $runtime 'frankenphp.exe'
$port    = 8000

# The storage skeleton has to exist before artisan is invoked at all, not just
# before agent:install does its work. Laravel builds the Blade compiler while
# booting, and config/view.php resolves its cache path with realpath(), which
# returns false for a directory that does not exist yet. The result is
# "Please provide a valid cache path" before the command ever runs.
$dirs = @(
    $data
    Join-Path $data 'logs'
    Join-Path $data 'storage'
    Join-Path $data 'storage\app'
    Join-Path $data 'storage\logs'
    Join-Path $data 'storage\framework'
    Join-Path $data 'storage\framework\cache'
    Join-Path $data 'storage\framework\cache\data'
    Join-Path $data 'storage\framework\sessions'
    Join-Path $data 'storage\framework\views'
)

foreach ($dir in $dirs) {
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
}

$log = Join-Path $data 'logs\launcher.log'
function Write-Log($message) {
    "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $message" | Add-Content -Path $log -Encoding utf8
    Write-Host $message
}

if (-not (Test-Path $exe)) {
    Write-Log "FATAL: frankenphp.exe not found at $exe. The install looks incomplete."
    Read-Host 'Press Enter to close'
    exit 1
}

# Real environment variables rather than an env file, because these are paths
# only the launcher knows. Real vars also win over anything in the env file,
# so the data directory cannot be overridden by a stale value.
$env:APP_ENV              = 'standalone'
$env:AGENT_PHP_ARGS       = 'php-cli'
$env:DB_DATABASE          = Join-Path $data 'database.sqlite'
$env:LARAVEL_STORAGE_PATH = Join-Path $data 'storage'
$env:AGENT_WEB_COMMAND    = '"{0}" run --config "{1}"' -f $exe, (Join-Path $app 'Caddyfile')

Set-Location $app

# First run only. agent:install is idempotent, but skipping it on later runs
# keeps startup quick. agent:serve migrates on every start anyway, so an update
# that adds migrations still applies them.
if (-not (Test-Path (Join-Path $app '.env.standalone'))) {
    Write-Log 'First run: preparing the application.'
    & $exe php-cli artisan agent:install 2>&1 | Tee-Object -FilePath $log -Append

    if ($LASTEXITCODE -ne 0) {
        Write-Log "FATAL: setup failed with exit code $LASTEXITCODE."
        Read-Host 'Press Enter to close'
        exit 1
    }
}

Write-Log 'Starting CPR Agent.'

# agent:serve supervises Reverb, the queue worker and the web server, restarts
# any that die, and clears strays left by a previous run that was killed.
$serve = Start-Process -FilePath $exe `
    -ArgumentList 'php-cli', 'artisan', 'agent:serve', '--with-web' `
    -WorkingDirectory $app `
    -PassThru `
    -RedirectStandardOutput (Join-Path $data 'logs\serve.out.log') `
    -RedirectStandardError  (Join-Path $data 'logs\serve.err.log')

# Wait for the port to answer before opening a browser, or the user gets an
# error page and assumes the app is broken.
$deadline = (Get-Date).AddSeconds(60)
$ready = $false

while ((Get-Date) -lt $deadline) {
    if ($serve.HasExited) {
        Write-Log "FATAL: the server exited during startup (code $($serve.ExitCode)). See logs\serve.err.log."
        Read-Host 'Press Enter to close'
        exit 1
    }

    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $client.Connect('127.0.0.1', $port)
        $client.Close()
        $ready = $true
        break
    } catch {
        Start-Sleep -Milliseconds 500
    }
}

if (-not $ready) {
    Write-Log "FATAL: nothing was listening on port $port after 60 seconds. See logs\serve.err.log."
    Read-Host 'Press Enter to close'
    exit 1
}

Write-Log "Ready on http://localhost:$port"
Start-Process "http://localhost:$port"

Write-Host ''
Write-Host '  CPR Agent is running.'
Write-Host "  Open http://localhost:$port in a browser to play."
Write-Host '  Closing this window stops the app.'
Write-Host ''

# Hold the window open for as long as the server runs. Closing it kills this
# process; the children are then cleaned up by reapStrays on the next launch.
$serve.WaitForExit()
Write-Log 'CPR Agent stopped.'
