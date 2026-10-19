#requires -version 5.1
<#
    build-update.ps1 - build the ZIP that goes up to the live site.

    WHY THIS EXISTS
    ---------------
    Updates are uploaded by hand through the Plesk File Manager: no SSH, no git
    on the server. The fragile part of that was remembering which files had
    changed - a missed file meant the live site kept running old code while
    looking perfectly fine.

    So the list is not typed in by hand any more. Git already knows what
    changed; this script asks it, refuses anything that must not go to a web
    server, syntax-checks every PHP file, and zips the result with forward-slash
    entry names.

    Forward slashes matter. .NET's own zip helper writes "app\Services\X.php",
    which Linux treats as a single file with a backslash in its name - the
    upload "succeeds" and the application never changes. This script builds the
    archive entry by entry to keep "/" as the separator.

    USAGE
    -----
        # Everything changed since the last deployed tag (the normal case)
        .\build-update.ps1

        # Since a specific commit
        .\build-update.ps1 -Since 5ecb02c

        # A hand-picked set, when git cannot know (rare)
        .\build-update.ps1 -Files routes/web.php,app/Models/User.php

        # Add one file the rules would otherwise leave behind - for example a
        # newly optimised branding logo, which lives under storage/
        .\build-update.ps1 -Include storage/app/public/branding/trustmed-logo.png

    Then: upload the ZIP in Plesk File Manager, extract it, and - if the script
    says a maintenance URL is needed - visit that URL once in a browser.

    After the upload is verified on the live site, tag the commit that is now
    running, so the next run of this script starts from the right place:

        git tag -f deployed && git push --force --tags
#>
[CmdletBinding()]
param(
    # Git ref to compare against. Defaults to the "deployed" tag, which is
    # moved forward after each successful upload.
    [string] $Since,

    # Explicit file list (repo-relative, forward slashes). Replaces the git
    # diff, and bypasses the "never upload" filter, because naming a file is
    # an instruction.
    [string[]] $Files,

    # Extra files to add on top of the git diff, bypassing the filter. For the
    # rare deliberate exception, such as shipping an optimised branding logo
    # that happens to live under storage/.
    [string[]] $Include,

    # Where to put the finished ZIP. Defaults to the user's Downloads folder,
    # which is where a browser expects to find the file to upload.
    [string] $Out,

    # Where the ZIP is assembled. Cleared each run.
    [string] $Stage,

    # Force a maintenance script into the ZIP even if nothing seems to need one.
    [switch] $Maintenance
)

$ErrorActionPreference = 'Stop'

$repo = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
if (-not (Test-Path (Join-Path $repo 'composer.json'))) {
    throw "Could not find the application root above $PSScriptRoot"
}
if (-not (Test-Path (Join-Path $repo '.git'))) {
    throw "$repo is not a git working copy."
}

if (-not $Out) {
    $stamp = Get-Date -Format 'yyyyMMdd-HHmm'
    $Out = Join-Path ([Environment]::GetFolderPath('UserProfile')) "Downloads\trustmed-update-$stamp.zip"
}
if (-not $Stage) {
    $Stage = Join-Path $env:TEMP 'trustmed-deploy\build'
}

function Invoke-Git {
    param([string[]] $Arguments)
    $result = & git -C $repo @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "git $($Arguments -join ' ') failed:`n$result"
    }
    return @($result | Where-Object { $_ -ne '' })
}

# ----------------------------------------------------------------------------
# 1. What changed
# ----------------------------------------------------------------------------
Write-Host ''
Write-Host '=== 1. working out what changed ==='

$deleted = @()

if ($Files) {
    Write-Host "  using the file list given on the command line ($($Files.Count) file(s))"
    # Nothing is taken from git; the list is the whole update.
    $changed = @()
} else {
    if (-not $Since) {
        $tags = @(Invoke-Git @('tag', '--list', 'deployed'))
        $Since = if ($tags.Count) { 'deployed' } else { 'HEAD' }
    }

    Write-Host "  comparing the working tree against '$Since'"

    # Working tree vs $Since: this deliberately includes work that has not been
    # committed yet, because that is usually exactly what is being deployed.
    $changed = @(Invoke-Git @('diff', '--name-only', '--no-renames', '--diff-filter=d', $Since))
    $untracked = @(Invoke-Git @('ls-files', '--others', '--exclude-standard'))
    $deleted = @(Invoke-Git @('diff', '--name-only', '--no-renames', '--diff-filter=D', $Since))

    if ($untracked.Count) {
        Write-Host "  including $($untracked.Count) new file(s) git is not tracking yet"
    }

    $changed = @($changed + $untracked | Sort-Object -Unique)
}

if (-not $changed.Count -and -not $Files) {
    Write-Host ''
    Write-Host '  Nothing has changed since then, so there is nothing to upload.' -ForegroundColor Yellow
    Write-Host "  If the last upload is not actually live, pass -Since <commit> explicitly."
    Write-Host ''
    exit 0
}

# ----------------------------------------------------------------------------
# 2. Anything that must never be uploaded
# ----------------------------------------------------------------------------
# A public git repository holds history, tests and tooling. None of that
# belongs on a web server, and some of it (tests, docs) would be harmless while
# other parts (vite config, package.json) would quietly imply assets were
# rebuilt when they were not.
$neverDeploy = @(
    '^tests/',
    '^docs/',
    '^tools/',
    '^\.github/',
    '^node_modules/',
    '^vendor/',
    '^\.git',
    '^storage/',
    '^bootstrap/cache/',
    '^public/build/',
    '^public/hot$',
    '^public/storage$',
    '^database/database\.sqlite$',
    '^\.',                      # dotfiles at the root: .env, .gitignore, .editorconfig
    '\.md$',
    '^phpunit\.xml',
    '^package(-lock)?\.json$',
    '^vite\.config\.js$',
    '^postcss\.config\.js$',
    '^tailwind\.config\.js$'
)

# Things that change behaviour on the server but cannot be applied by copying
# files alone. Loud enough to stop and think.
$needsCare = @(
    '^composer\.(json|lock)$',
    '^config/',
    '^database/migrations/',
    '^database/seeders/'
)

$deploy    = New-Object System.Collections.Generic.List[string]
$skipped   = New-Object System.Collections.Generic.List[string]
$careful   = New-Object System.Collections.Generic.List[string]

foreach ($file in $changed) {
    $path = $file -replace '\\', '/'
    $path = $path.TrimStart('./')

    if ($neverDeploy | Where-Object { $path -match $_ }) {
        $skipped.Add($path)
        continue
    }

    if (-not (Test-Path (Join-Path $repo ($path -replace '/', '\')))) {
        # Deleted in the working tree; reported below instead.
        $skipped.Add($path)
        continue
    }

    $deploy.Add($path)
    if ($needsCare | Where-Object { $path -match $_ }) { $careful.Add($path) }
}

# Files named on the command line are taken at their word - the filter above
# exists to stop things being included by accident, not on purpose.
$explicit = @()
if ($Files)   { $explicit += $Files }
if ($Include) { $explicit += $Include }

if ($explicit.Count) {
    Write-Host ''
    Write-Host '  Named on the command line, so taken as given:'
    foreach ($file in $explicit) {
        $path = $file -replace '\\', '/'
        $path = $path.TrimStart('./')

        if (-not (Test-Path (Join-Path $repo ($path -replace '/', '\')))) {
            throw "Asked to include $path but there is no such file in the project."
        }

        if (-not $deploy.Contains($path)) { $deploy.Add($path) }

        # It may have been filtered out a moment ago; it is not being left
        # behind, so it should not be reported as left behind.
        while ($skipped.Contains($path)) { [void] $skipped.Remove($path) }

        Write-Host "    + $path" -ForegroundColor Cyan
    }
}

Write-Host "  $($deploy.Count) file(s) will go up, $($skipped.Count) left behind"

if ($skipped.Count) {
    Write-Host ''
    Write-Host '  Left behind deliberately (they live in git, not on the server):'
    foreach ($file in ($skipped | Sort-Object)) { Write-Host "    - $file" }
}

if ($deleted.Count) {
    Write-Host ''
    Write-Host '  These files no longer exist in the project but are still on the' -ForegroundColor Yellow
    Write-Host '  server. Delete them by hand in the Plesk File Manager:' -ForegroundColor Yellow
    foreach ($file in $deleted) { Write-Host "    - $file" -ForegroundColor Yellow }
}

if ($careful.Count) {
    Write-Host ''
    Write-Host '  Look at these before uploading:' -ForegroundColor Yellow
    foreach ($file in $careful) {
        switch -Regex ($file) {
            '^composer\.'         { Write-Host "    - $file  (the server cannot run composer install from the panel)" -ForegroundColor Yellow }
            '^config/'            { Write-Host "    - $file  (config is read from .env on the server)" -ForegroundColor Yellow }
            '^database/migrations' { Write-Host "    - $file  (a maintenance URL will be added to run it)" -ForegroundColor Yellow }
            default               { Write-Host "    - $file" -ForegroundColor Yellow }
        }
    }
}

if (-not $deploy.Count) {
    Write-Host ''
    Write-Host '  Nothing deployable in that set. Stopping.' -ForegroundColor Yellow
    exit 0
}

# ----------------------------------------------------------------------------
# 3. Stage the files
# ----------------------------------------------------------------------------
Write-Host ''
Write-Host '=== 2. collecting the files ==='

if (Test-Path $Stage) { Remove-Item $Stage -Recurse -Force }
New-Item -ItemType Directory -Path $Stage -Force | Out-Null

$totalBytes = 0
foreach ($file in ($deploy | Sort-Object)) {
    $source = Join-Path $repo ($file -replace '/', '\')
    $target = Join-Path $Stage ($file -replace '/', '\')

    New-Item -ItemType Directory -Path (Split-Path $target) -Force | Out-Null
    Copy-Item $source $target -Force

    $size = (Get-Item $source).Length
    $totalBytes += $size
    Write-Host ("  {0,-56} {1,9:N0} bytes" -f $file, $size)
}

# ----------------------------------------------------------------------------
# 4. The one-time maintenance script
# ----------------------------------------------------------------------------
# The application is deliberately left uncached on the server, so ordinary code
# and template changes take effect the moment the files land: no cache to
# clear, nothing to run. A new database migration is the one exception, and it
# needs a token-protected one-shot page because the panel offers no way to run
# a command.
$maintenanceFiles = @()

if ($Maintenance -or ($deploy | Where-Object { $_ -match '^database/migrations/' })) {
    Write-Host ''
    Write-Host '=== 3. adding the one-time maintenance page ==='

    $token = ([guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N'))
    $template = Join-Path $PSScriptRoot 'templates\maintenance.php'
    $body = [System.IO.File]::ReadAllText($template)

    $body = $body.Replace('__TOKEN__', $token)
    $body = $body.Replace('__RUN_MIGRATIONS__', 'true')

    $stagedMaintenance = Join-Path $Stage 'public\_maintenance.php'
    New-Item -ItemType Directory -Path (Split-Path $stagedMaintenance) -Force | Out-Null

    # Not Set-Content: PowerShell 5.1 writes a UTF-8 BOM, and a BOM in front of
    # "<?php" is emitted to the browser as output, which breaks the header()
    # calls that follow.
    [System.IO.File]::WriteAllText(
        $stagedMaintenance,
        $body,
        (New-Object System.Text.UTF8Encoding($false))
    )

    $maintenanceFiles += 'public/_maintenance.php'
    Write-Host '  public/_maintenance.php added'
    Write-Host ''
    Write-Host '  After extracting the ZIP, open this address once in a browser:'
    Write-Host ''
    Write-Host "    https://trustmed.pharmacy.et/_maintenance.php?token=$token" -ForegroundColor Cyan
    Write-Host ''
    Write-Host '  It runs the new migrations, clears the caches, checks the'
    Write-Host '  installation, and then deletes itself.'
} else {
    Write-Host ''
    Write-Host '=== 3. no maintenance page needed ==='
    Write-Host '  No new database migration in this update, and the site is left'
    Write-Host '  uncached on purpose, so the new files take effect on their own.'
}

# ----------------------------------------------------------------------------
# 5. Syntax check everything going out
# ----------------------------------------------------------------------------
Write-Host ''
Write-Host '=== 4. checking the PHP is valid ==='

$checked = 0
foreach ($file in (($deploy + $maintenanceFiles) | Sort-Object)) {
    if ($file -notmatch '\.php$') { continue }

    $path = Join-Path $Stage ($file -replace '/', '\')
    $output = & php -l $path 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Syntax error in $file`n$output"
    }
    $checked++
}
Write-Host "  $checked PHP file(s) parsed cleanly"

# ----------------------------------------------------------------------------
# 6. Zip it, with forward slashes
# ----------------------------------------------------------------------------
Write-Host ''
Write-Host '=== 5. writing the ZIP ==='

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$outDir = Split-Path $Out
if ($outDir -and -not (Test-Path $outDir)) {
    New-Item -ItemType Directory -Path $outDir -Force | Out-Null
}
if (Test-Path $Out) { Remove-Item $Out -Force }

$archive = [System.IO.Compression.ZipFile]::Open($Out, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $rootLength = $Stage.TrimEnd('\').Length + 1
    foreach ($file in (Get-ChildItem $Stage -Recurse -File)) {
        # The whole reason this is not Compress-Archive: entry names use "/"
        # no matter which operating system built the archive.
        $entryName = $file.FullName.Substring($rootLength).Replace('\', '/')
        [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive,
            $file.FullName,
            $entryName,
            [System.IO.Compression.CompressionLevel]::Optimal
        )
    }
} finally {
    $archive.Dispose()
}

Write-Host ("  {0}" -f $Out)
Write-Host ("  {0:N0} bytes ({1:N0} KB of source)" -f (Get-Item $Out).Length, ($totalBytes / 1024))

# ----------------------------------------------------------------------------
# 7. What is inside, and what to do next
# ----------------------------------------------------------------------------
Write-Host ''
Write-Host '=== 6. what is inside ==='

$archive = [System.IO.Compression.ZipFile]::OpenRead($Out)
try {
    foreach ($entry in ($archive.Entries | Sort-Object FullName)) {
        Write-Host ("  {0,-56} {1,9:N0}" -f $entry.FullName, $entry.Length)
    }
} finally {
    $archive.Dispose()
}

Write-Host ''
Write-Host '=== next ==='
$step = 0
Write-Host ("  {0}. Plesk > File Manager > trustmed.pharmacy.et > Upload, then extract." -f (++$step))
Write-Host ("  {0}. Wait for the ""extracted"" message before closing the window." -f (++$step))
if ($maintenanceFiles.Count) {
    Write-Host ("  {0}. Open the maintenance address printed above, once." -f (++$step)) -ForegroundColor Cyan
}
Write-Host ("  {0}. Check the live site (tools\deploy\check-live.ps1)." -f (++$step))
Write-Host ("  {0}. Then record what is running:" -f (++$step)) -ForegroundColor Cyan
Write-Host '       git tag -f deployed && git push --force --tags'
Write-Host ''
