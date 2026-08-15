# Assembles the Windows bundle that the installer packages.
#
# Run this from a checkout on a build machine, not on a user's machine. It does
# all the work the end user must never do: Composer, npm, and laying out the
# runtime with its php.ini.
#
# Usage:
#   .\standalone\windows\build-bundle.ps1 -FrankenPhpZip C:\downloads\frankenphp-windows-x86_64.zip
#
# Output: dist\bundle\, ready for Inno Setup to package.

[CmdletBinding()]
param(
    # The extracted FrankenPHP release directory, or the release zip.
    [Parameter(Mandatory = $true)]
    [string]$FrankenPhpZip,

    [string]$OutputDir = 'dist\bundle',

    # Skip composer/npm when the working copy already has vendor\ and
    # public\build\ built the way you want them.
    [switch]$SkipDependencies
)

$ErrorActionPreference = 'Stop'

$repo = Resolve-Path (Join-Path $PSScriptRoot '..\..')
Set-Location $repo

$bundle  = Join-Path $repo $OutputDir
$runtime = Join-Path $bundle 'runtime'
$app     = Join-Path $bundle 'app'

Write-Host "Building bundle in $bundle"

if (Test-Path $bundle) { Remove-Item $bundle -Recurse -Force }
New-Item -ItemType Directory -Path $runtime -Force | Out-Null
New-Item -ItemType Directory -Path $app -Force | Out-Null

# --- runtime -------------------------------------------------------------

if ($FrankenPhpZip.ToLower().EndsWith('.zip')) {
    Write-Host 'Extracting FrankenPHP...'
    Expand-Archive -Path $FrankenPhpZip -DestinationPath $runtime -Force
} else {
    Write-Host 'Copying FrankenPHP...'
    Copy-Item (Join-Path $FrankenPhpZip '*') $runtime -Recurse -Force
}

if (-not (Test-Path (Join-Path $runtime 'frankenphp.exe'))) {
    throw "frankenphp.exe not found in $runtime. Check -FrankenPhpZip points at the Windows release."
}

# The release ships no php.ini, so PHP would boot without mbstring, pdo_sqlite
# or openssl and Laravel would die immediately. See standalone/WINDOWS_SPIKE.md.
Copy-Item (Join-Path $repo 'standalone\php.ini-windows') (Join-Path $runtime 'php.ini') -Force
Write-Host 'Installed php.ini into the runtime.'

# --- dependencies --------------------------------------------------------

if (-not $SkipDependencies) {
    Write-Host 'composer install...'
    composer install --no-dev --optimize-autoloader --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'composer install failed.' }

    Write-Host 'npm ci and build...'
    npm ci
    if ($LASTEXITCODE -ne 0) { throw 'npm ci failed.' }
    npm run build
    if ($LASTEXITCODE -ne 0) { throw 'npm run build failed.' }
}

foreach ($required in @('vendor', 'public\build\manifest.json')) {
    if (-not (Test-Path (Join-Path $repo $required))) {
        throw "$required is missing. Run without -SkipDependencies."
    }
}

# --- app -----------------------------------------------------------------

# Everything the app needs at runtime, and nothing else. Development files are
# excluded deliberately: they are dead weight in the download and .env.ddev
# would leak a working config into a user install.
$include = @(
    'app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources',
    'routes', 'vendor', 'storage', 'artisan', 'composer.json'
)

foreach ($item in $include) {
    $source = Join-Path $repo $item
    if (-not (Test-Path $source)) { continue }

    if (Test-Path $source -PathType Container) {
        robocopy $source (Join-Path $app $item) /E /NFL /NDL /NJH /NJS /NP /MT:16 | Out-Null
        if ($LASTEXITCODE -ge 8) { throw "Failed copying $item" }
        $global:LASTEXITCODE = 0
    } else {
        Copy-Item $source (Join-Path $app $item) -Force
    }
}

# The template agent:install seeds .env.standalone from on first run.
Copy-Item (Join-Path $repo '.env.standalone.example') (Join-Path $app '.env.standalone.example') -Force
Copy-Item (Join-Path $repo 'standalone\Caddyfile') (Join-Path $app 'Caddyfile') -Force

# Ship an empty storage skeleton, not the build machine's caches and logs.
Get-ChildItem (Join-Path $app 'storage') -Recurse -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -ne '.gitignore' } | Remove-Item -Force -ErrorAction SilentlyContinue

# --- launcher ------------------------------------------------------------

foreach ($file in @('launcher.ps1', 'stop.ps1', 'Start CPR Agent.cmd', 'Stop CPR Agent.cmd')) {
    Copy-Item (Join-Path $PSScriptRoot $file) (Join-Path $bundle $file) -Force
}

$size = '{0:N0} MB' -f ((Get-ChildItem $bundle -Recurse -File | Measure-Object Length -Sum).Sum / 1MB)
Write-Host ''
Write-Host "Bundle ready: $bundle  ($size)"
Write-Host 'Next: compile standalone\windows\cpr-agent.iss with Inno Setup.'
