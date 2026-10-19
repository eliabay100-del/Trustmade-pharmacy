#requires -version 5.1
<#
    check-live.ps1 - a quick "is the site actually working" from outside.

    Everything here is requested anonymously, exactly as a stranger on the
    internet would: no login, no cookies, no assumptions. That makes it a real
    test of what the public can see, and it is the check to run right after an
    upload and again a few minutes later.

    URLS THAT REQUIRE SIGNING IN
    ---------------------------
    Most of this application is behind the login, so this cannot check it. The
    signed-in screens are checked by opening them in a browser - see
    docs/UPDATES.md for the short list worth clicking through after a change.

    USAGE
    -----
        .\check-live.ps1
        .\check-live.ps1 -Site https://staging.example.com
#>
[CmdletBinding()]
param(
    [string] $Site = 'https://trustmed.pharmacy.et'
)

$ErrorActionPreference = 'Continue'

$curl = Join-Path $env:SystemRoot 'System32\curl.exe'
if (-not (Test-Path $curl)) { $curl = 'curl.exe' }

$Site = $Site.TrimEnd('/')
$scratch = Join-Path $env:TEMP 'trustmed-live-check'
New-Item -ItemType Directory -Path $scratch -Force | Out-Null

# name, path, and what a healthy answer looks like.
# 302 on "/" is correct: a signed-out visitor is sent to the sign-in page.
$targets = @(
    @{ Name = 'Home page';            Path = '/';                    Want = @(200, 302) },
    @{ Name = 'Sign-in page';         Path = '/login';               Want = @(200) },
    @{ Name = 'Health check';         Path = '/up';                  Want = @(200) },
    @{ Name = 'App stylesheet';       Path = '/build/manifest.json'; Want = @(200) },
    @{ Name = 'Favicon';              Path = '/favicon.ico';         Want = @(200) },
    @{ Name = 'Setup tool (gone)';    Path = '/_setup.php';          Want = @(404) },
    @{ Name = 'Maintenance (gone)';   Path = '/_maintenance.php';    Want = @(404) },
    @{ Name = 'Old cache tool (gone)'; Path = '/_cache.php';         Want = @(404) }
)

Write-Host ''
Write-Host " ===== $Site ====="
Write-Host ''

$problems = @()

foreach ($target in $targets) {
    $url = $Site + $target.Path
    $result = & $curl -sS -o (Join-Path $scratch 'body') -w '%{http_code}|%{size_download}|%{time_total}' `
        --max-time 60 --location --max-redirs 0 $url 2>&1

    $parts = "$result" -split '\|'
    $status = [int] $parts[0]

    $ok = $target.Want -contains $status
    $mark = if ($ok) { 'ok  ' } else { 'FAIL' }
    if (-not $ok) { $problems += "$($target.Name) returned $status" }

    Write-Host ("  {0} {1,-22} {2,4}   {3,9:N0} bytes  {4}s" -f `
        $mark, $target.Name, $status, [double] $parts[1], $parts[2])
}

Write-Host ''

# The health endpoint says more than a status code: it reports which
# dependencies are reachable.
$health = & $curl -sS --max-time 60 "$Site/up" 2>&1
if ($health) {
    Write-Host ' Health check says:'
    Write-Host "   $health"
    Write-Host ''
}

# A trace of the sign-in page, to catch a broken asset reference or a PHP
# error rendered into the page rather than returned as an error code.
$login = Join-Path $scratch 'login.html'
& $curl -sS -o $login --max-time 60 "$Site/login" *> $null

if (Test-Path $login) {
    $html = Get-Content $login -Raw

    $tells = @(
        @{ Name = 'Laravel error page'; Match = 'Whoops|Attempt to read property|Undefined variable|Stack trace' },
        @{ Name = 'Branding missing';   Match = 'TRUSTMED' , Negate = $true }
    )

    foreach ($tell in $tells) {
        $found = $html -match $tell.Match
        if ($tell.Negate) {
            if ($found) { Write-Host "  ok   Sign-in page shows the pharmacy name" }
            else { Write-Host "  FAIL Sign-in page does not show the pharmacy name" -ForegroundColor Red; $problems += 'no branding on sign-in page' }
        } elseif ($found) {
            Write-Host "  FAIL Sign-in page contains a $($tell.Name)" -ForegroundColor Red
            $problems += $tell.Name
        } else {
            Write-Host "  ok   No $($tell.Name)"
        }
    }
    Write-Host ''
}

if ($problems.Count) {
    Write-Host " $($problems.Count) problem(s):" -ForegroundColor Red
    foreach ($problem in $problems) { Write-Host "   - $problem" -ForegroundColor Red }
    Write-Host ''
    exit 1
}

Write-Host ' Everything public is answering correctly.' -ForegroundColor Green
Write-Host ''
exit 0
