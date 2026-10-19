#requires -version 5.1
<#
    check-live.ps1 - a quick "is the site actually working" from outside.

    Everything here is requested anonymously, exactly as a stranger on the
    internet would: no login, no cookies. That makes it a real test of what
    the public can see, and it is the check to run right after an upload.

    WHAT THIS CANNOT CHECK
    ----------------------
    The application is almost entirely behind the sign-in, so this only covers
    the outside. After a change, also open these in a browser: the dashboard,
    Start a sale, the stock list, and whatever the change touched.

    It also cannot tell you the site is slow. The host does that on its own:
    responses are normally 0.1-0.3s and occasionally 15s+ with 502s while the
    shared platform struggles. If pages are suddenly slow, run this twice a
    few minutes apart before assuming a code problem - `time_starttransfer` is
    printed for exactly that reason.

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

# Name, path, the status codes that mean healthy, and a minimum size where a
# small-but-valid response would hide a problem.
#
# Note what is NOT here: /login. Signing in is a POST to /login, so a GET is
# 405 by design; the sign-in *page* is /. Checking the wrong one produced a
# false alarm the first time this script ran.
$targets = @(
    @{ Name = 'Sign-in page';         Path = '/';                    Want = @(200); MinBytes = 3000 },
    @{ Name = 'Health check';         Path = '/up';                  Want = @(200); MinBytes = 200 },
    @{ Name = 'App assets manifest';  Path = '/build/manifest.json'; Want = @(200); MinBytes = 50 },
    @{ Name = 'Web app manifest';     Path = '/manifest.webmanifest'; Want = @(200); MinBytes = 50 },
    @{ Name = 'robots.txt';           Path = '/robots.txt';          Want = @(200); MinBytes = 20 },
    # This was committed as a zero-byte placeholder once. A 200 with nothing in
    # it is not a working favicon, hence the minimum size.
    @{ Name = 'Favicon';              Path = '/favicon.ico';         Want = @(200); MinBytes = 1000 },
    @{ Name = 'Setup tool (gone)';    Path = '/_setup.php';          Want = @(404) },
    @{ Name = 'Maintenance (gone)';   Path = '/_maintenance.php';    Want = @(404) },
    @{ Name = 'Old cache tool (gone)'; Path = '/_cache.php';         Want = @(404) }
)

Write-Host ''
Write-Host " ===== $Site ====="
Write-Host ''

$problems = @()
$slowest = 0

foreach ($target in $targets) {
    $url = $Site + $target.Path
    $result = & $curl -sS -o (Join-Path $scratch 'body') -w '%{http_code}|%{size_download}|%{time_starttransfer}' `
        --max-time 60 $url 2>&1

    $parts = "$result" -split '\|'
    $status = 0
    [void] [int]::TryParse($parts[0], [ref] $status)
    $size = [double] $parts[1]
    $first = [double] $parts[2]
    if ($first -gt $slowest) { $slowest = $first }

    $ok = ($target.Want -contains $status)
    $note = ''

    if ($ok -and $target.ContainsKey('MinBytes') -and $size -lt $target.MinBytes) {
        $ok = $false
        $note = " (only $([int] $size) bytes)"
    }

    if (-not $ok) {
        $problems += "$($target.Name) returned $status$note"
    }

    $mark = if ($ok) { 'ok  ' } else { 'FAIL' }
    Write-Host ("  {0} {1,-22} {2,4}   {3,9:N0} bytes  {4,7:N2}s" -f `
        $mark, $target.Name, $status, $size, $first)
}

Write-Host ''
Write-Host (" Slowest response: {0:N2}s" -f $slowest)

# The built CSS and JS are content-hashed, so their names cannot be written
# here. Read them out of the manifest instead: this proves the manifest is
# real and that the file it points at actually arrived.
$manifestPath = Join-Path $scratch 'manifest.json'
& $curl -sS -o $manifestPath --max-time 60 "$Site/build/manifest.json" *> $null

if (Test-Path $manifestPath) {
    try {
        $manifest = Get-Content $manifestPath -Raw | ConvertFrom-Json
        foreach ($entry in @('resources/css/app.css', 'resources/js/app.js')) {
            $file = $manifest.$entry.file

            if (-not $file) {
                $problems += "the assets manifest has no entry for $entry"
                Write-Host "  FAIL Assets manifest has no entry for $entry" -ForegroundColor Red
                continue
            }

            $result = & $curl -sS -o (Join-Path $scratch 'asset') -w '%{http_code}|%{size_download}' `
                --max-time 60 "$Site/build/$file" 2>&1
            $parts = "$result" -split '\|'

            if ($parts[0] -eq '200' -and [double] $parts[1] -gt 1000) {
                Write-Host ("  ok   {0,-34} {1,8:N0} bytes" -f $file, [double] $parts[1])
            } else {
                $problems += "$file returned $($parts[0]) with $($parts[1]) bytes"
                Write-Host ("  FAIL {0,-34} {1} / {2} bytes" -f $file, $parts[0], $parts[1]) -ForegroundColor Red
            }
        }
    } catch {
        $problems += 'the assets manifest is not valid JSON'
        Write-Host '  FAIL Assets manifest is not valid JSON' -ForegroundColor Red
    }
}

# The health endpoint says more than a status code. Pull out just the verdict
# rather than dumping the whole page.
$health = & $curl -sS --max-time 60 "$Site/up" 2>&1
if ($health -match 'Application up') {
    Write-Host ' Health check: Application up'
} elseif ($health -match 'Application down|error') {
    Write-Host ' Health check: reports a problem' -ForegroundColor Red
    $problems += 'the health check reports the application is down'
} else {
    Write-Host ' Health check: unrecognised response' -ForegroundColor Yellow
}
Write-Host ''

# Read the sign-in page: a PHP error is often rendered into a 200 response, so
# the status code alone would not catch it.
$page = Join-Path $scratch 'home.html'
& $curl -sS -o $page --max-time 60 "$Site/" *> $null

if (Test-Path $page) {
    $html = Get-Content $page -Raw

    if ($html -match 'Whoops|Attempt to read property|Undefined variable|Stack trace|Fatal error') {
        Write-Host '  FAIL Sign-in page contains a PHP error' -ForegroundColor Red
        $problems += 'a PHP error is rendered into the sign-in page'
    } else {
        Write-Host '  ok   No PHP error on the sign-in page'
    }

    if ($html -match 'TRUSTMED') {
        Write-Host '  ok   Sign-in page shows the pharmacy name'
    } else {
        Write-Host '  FAIL Sign-in page does not show the pharmacy name' -ForegroundColor Red
        $problems += 'no branding on the sign-in page'
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
