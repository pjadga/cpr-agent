# Stops CPR Agent.
#
# Closing the launcher window is the normal way to quit. This exists for when
# the app has been left running in the background, or something has gone wrong
# and the window is gone but the server is not.
#
# Only processes running from THIS installation's runtime are touched, matched
# on the full executable path. A separate checkout, a DDEV container, or a
# second copy of the app elsewhere on the machine is left alone.

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$exe  = Join-Path $root 'runtime\frankenphp.exe'

$running = Get-CimInstance Win32_Process -Filter "Name='frankenphp.exe'" |
    Where-Object { $_.ExecutablePath -eq $exe }

if (-not $running) {
    Write-Host 'CPR Agent is not running.'
    Start-Sleep -Seconds 2
    exit 0
}

Write-Host 'Stopping CPR Agent...'

foreach ($process in $running) {
    Write-Host ('  stopping pid {0}' -f $process.ProcessId)
    Stop-Process -Id $process.ProcessId -Force -ErrorAction SilentlyContinue
}

Start-Sleep -Seconds 2

$left = Get-CimInstance Win32_Process -Filter "Name='frankenphp.exe'" |
    Where-Object { $_.ExecutablePath -eq $exe }

if ($left) {
    Write-Host 'Some processes did not stop. Try again, or restart Windows.'
} else {
    Write-Host 'Stopped.'
}

Start-Sleep -Seconds 2
