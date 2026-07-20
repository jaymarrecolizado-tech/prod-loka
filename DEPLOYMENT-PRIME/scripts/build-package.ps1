# Build DEPLOYMENT-PRIME/htdocs from public_html (+ vendor/dist when available)
$ErrorActionPreference = "Stop"

$OutRoot = Split-Path $PSScriptRoot -Parent
$RepoRoot = Split-Path $OutRoot -Parent
$Src = Join-Path $RepoRoot "public_html"
$Dest = Join-Path $OutRoot "htdocs"
$Live = "C:\xampp\htdocs\Projects\prod-loka\public_html"

if (-not (Test-Path (Join-Path $Src "index.php"))) {
    throw "public_html not found at $Src"
}

Write-Host "Source: $Src"
Write-Host "Dest:   $Dest"

if (Test-Path $Dest) {
    Remove-Item -Recurse -Force $Dest
}
New-Item -ItemType Directory -Path $Dest -Force | Out-Null

$xd = @(
    "node_modules", ".git", "cache", "graphify-out", "tests",
    "playwright-report", "test-results", "coverage", ".history", ".vscode", ".idea"
)
$xf = @(
    ".env", ".env.local", ".env.production", ".env.development",
    "composer.phar", "phpunit.xml", "phpunit.xml.dist",
    "playwright.config.js", "package-lock.json", "complexity-report.json"
)

$rcArgs = @($Src, $Dest, "/E", "/NFL", "/NDL", "/NJH", "/NJS", "/NC", "/NS", "/NP", "/XD") + $xd + @("/XF") + $xf
& robocopy @rcArgs | Out-Null
if ($LASTEXITCODE -ge 8) {
    throw "robocopy failed with code $LASTEXITCODE"
}

function Copy-TreeIfMissing([string]$Name) {
    $to = Join-Path $Dest $Name
    if (Test-Path $to) { return }
    $fromPush = Join-Path $Src $Name
    $fromLive = Join-Path $Live $Name
    if (Test-Path $fromPush) {
        Write-Host "Copy $Name from push..."
        & robocopy $fromPush $to /E /NFL /NDL /NJH /NJS /NC /NS /NP | Out-Null
    } elseif (Test-Path $fromLive) {
        Write-Host "Copy $Name from prod-loka live..."
        & robocopy $fromLive $to /E /NFL /NDL /NJH /NJS /NC /NS /NP | Out-Null
    } else {
        Write-Host "WARN: missing $Name - run composer/npm on server"
    }
}

Copy-TreeIfMissing "vendor"
Copy-TreeIfMissing "assets\dist"

foreach ($d in @("cache", "logs", "uploads", "uploads\observations", "uploads\gas_vouchers")) {
    $p = Join-Path $Dest $d
    New-Item -ItemType Directory -Path $p -Force | Out-Null
    Set-Content -Path (Join-Path $p ".gitkeep") -Value ""
}

Remove-Item -Force (Join-Path $Dest ".env") -ErrorAction SilentlyContinue

$stamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
@(
    "LOKA DEPLOYMENT-PRIME package"
    "Built: $stamp"
    "Upload contents of this htdocs folder to:"
    "  /home/lokaloka/htdocs/lokastage.dictr2.cloud/"
    "See ../README.md for permissions and cron."
) | Set-Content -Path (Join-Path $Dest "DEPLOYMENT-PRIME.txt")

Write-Host ""
Write-Host "OK package ready at $Dest"
Write-Host "Next zip htdocs contents, upload, set .env, run set-permissions.sh, install crontab.example"
