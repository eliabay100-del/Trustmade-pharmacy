#requires -version 5.1
<#
    make-icons.ps1 - generate the browser / install icon set from the logo.

    WHY THIS EXISTS
    ---------------
    There are four different icons in play and browsers do not share them:

      favicon.ico          the browser tab
      icons/icon-*.png     the installed app (Chrome/Edge/Android read the
                           web app manifest)
      icon-maskable-*.png  the same, for launchers that crop icons to a shape
      apple-touch-icon.png the iPhone/iPad home screen

    Each has its own rules, and getting them wrong fails silently:

    * The manifest must declare each raster icon at its real pixel size.
      "sizes": "any" is for SVG; a PNG declared that way is not something
      Chrome will use for an installed app - which is why installing the site
      produced a generic icon.
    * A "maskable" icon gets cropped to a circle or squircle, so the artwork
      has to sit inside the middle ~80%. Declaring an edge-to-edge image as
      both "any maskable" means the mask eats the corners.
    * iOS ignores the manifest and, worse, paints transparency black. An
      apple-touch-icon has to be opaque and 180x180.

    USAGE
    -----
        # Rebuild every icon from the current logo
        .\make-icons.ps1

        # Also shrink the stored logo itself
        .\make-icons.ps1 -OptimiseLogo
#>
[CmdletBinding()]
param(
    # Source image. Defaults to the branding logo the application ships with.
    [string] $Logo,

    # Longest side of the stored logo after optimisation.
    [int] $LogoMax = 192,

    # Rewrite the stored logo at that size, in place.
    [switch] $OptimiseLogo
)

$ErrorActionPreference = 'Stop'

$repo = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
if (-not $Logo) {
    $Logo = Join-Path $repo 'storage\app\public\branding\trustmed-logo.png'
}

if (-not (Test-Path $Logo)) { throw "No logo at $Logo" }

Add-Type -AssemblyName System.Drawing

<#
    Draws the logo centred on a square canvas.

    $Inset is the fraction of the canvas left as margin on each side: 0.04
    leaves the artwork nearly full-bleed, 0.17 keeps it inside the safe zone a
    maskable icon is cropped to.

    $Background of Transparent keeps the alpha channel; anything else flattens
    onto that colour, which is what iOS requires.
#>
function New-IconBitmap {
    param(
        [System.Drawing.Image] $Source,
        [int] $Size,
        [double] $Inset = 0.04,
        [System.Drawing.Color] $Background = [System.Drawing.Color]::Transparent
    )

    $bitmap = New-Object System.Drawing.Bitmap(
        $Size, $Size, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb
    )

    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    try {
        $graphics.CompositingMode    = [System.Drawing.Drawing2D.CompositingMode]::SourceCopy
        $graphics.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
        $graphics.InterpolationMode  = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $graphics.SmoothingMode      = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
        $graphics.PixelOffsetMode    = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality

        $graphics.Clear($Background)

        # Keep the logo's aspect ratio: fit it inside the content square.
        $content = [int] [Math]::Round($Size * (1 - (2 * $Inset)))
        $scale = [Math]::Min($content / $Source.Width, $content / $Source.Height)
        $width = [int] [Math]::Max(1, [Math]::Round($Source.Width * $scale))
        $height = [int] [Math]::Max(1, [Math]::Round($Source.Height * $scale))
        $x = [int] [Math]::Floor(($Size - $width) / 2)
        $y = [int] [Math]::Floor(($Size - $height) / 2)

        $graphics.DrawImage($Source, $x, $y, $width, $height)
    } finally {
        $graphics.Dispose()
    }

    return $bitmap
}

function Get-PngBytes {
    param([System.Drawing.Bitmap] $Bitmap)

    $stream = New-Object System.IO.MemoryStream
    try {
        $Bitmap.Save($stream, [System.Drawing.Imaging.ImageFormat]::Png)
        return $stream.ToArray()
    } finally {
        $stream.Dispose()
    }
}

# Loaded from memory rather than FromFile: FromFile keeps a lock on the file,
# and with -OptimiseLogo the destination is that same file.
$sourceBytes = [System.IO.File]::ReadAllBytes($Logo)
$sourceStream = New-Object System.IO.MemoryStream
$sourceStream.Write($sourceBytes, 0, $sourceBytes.Length)
$sourceStream.Position = 0
$source = [System.Drawing.Image]::FromStream($sourceStream)

try {
    Write-Host ''
    Write-Host ("Source: {0}  ({1} x {2}, {3:N0} KB)" -f $Logo, $source.Width, $source.Height, ((Get-Item $Logo).Length / 1KB))
    Write-Host ''

    # ------------------------------------------------------------------
    # The stored logo
    # ------------------------------------------------------------------
    if ($OptimiseLogo) {
        $target = Join-Path $repo 'storage\app\public\branding\trustmed-logo.png'
        $before = (Get-Item $target).Length

        $scale = [Math]::Min(1.0, $LogoMax / [Math]::Max($source.Width, $source.Height))
        $width = [int] [Math]::Max(1, [Math]::Round($source.Width * $scale))
        $height = [int] [Math]::Max(1, [Math]::Round($source.Height * $scale))

        $bitmap = New-Object System.Drawing.Bitmap(
            $width, $height, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb
        )
        $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
        try {
            $graphics.CompositingMode    = [System.Drawing.Drawing2D.CompositingMode]::SourceCopy
            $graphics.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
            $graphics.InterpolationMode  = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
            $graphics.SmoothingMode      = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
            $graphics.PixelOffsetMode    = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
            $graphics.Clear([System.Drawing.Color]::Transparent)
            $graphics.DrawImage($source, 0, 0, $width, $height)
        } finally {
            $graphics.Dispose()
        }

        try {
            $bitmap.Save($target, [System.Drawing.Imaging.ImageFormat]::Png)
        } finally {
            $bitmap.Dispose()
        }

        $after = (Get-Item $target).Length
        Write-Host '=== logo ==='
        Write-Host ("  {0} x {1}  ->  {2} x {3}" -f $source.Width, $source.Height, $width, $height)
        Write-Host ("  {0:N0} KB  ->  {1:N0} KB  ({2:P0} smaller)" -f ($before / 1KB), ($after / 1KB), (1 - ($after / $before)))
        Write-Host ''
    }

    # ------------------------------------------------------------------
    # The icon set
    # ------------------------------------------------------------------
    $iconsDir = Join-Path $repo 'public\icons'
    New-Item -ItemType Directory -Path $iconsDir -Force | Out-Null

    # file, size, inset, background, purpose
    $set = @(
        @{ File = 'icon-16.png';          Size = 16;  Inset = 0.00; Bg = 'transparent'; Why = 'tab icon, small' },
        @{ File = 'icon-32.png';          Size = 32;  Inset = 0.00; Bg = 'transparent'; Why = 'tab icon' },
        @{ File = 'icon-192.png';         Size = 192; Inset = 0.04; Bg = 'transparent'; Why = 'installed app (manifest, any)' },
        @{ File = 'icon-512.png';         Size = 512; Inset = 0.04; Bg = 'transparent'; Why = 'installed app, high density (manifest, any)' },
        @{ File = 'icon-maskable-192.png'; Size = 192; Inset = 0.17; Bg = 'white';       Why = 'launcher crops to a shape (manifest, maskable)' },
        @{ File = 'icon-maskable-512.png'; Size = 512; Inset = 0.17; Bg = 'white';       Why = 'as above, high density' }
    )

    Write-Host '=== icons ==='

    foreach ($icon in $set) {
        $background = if ($icon.Bg -eq 'white') { [System.Drawing.Color]::White } else { [System.Drawing.Color]::Transparent }

        $bitmap = New-IconBitmap -Source $source -Size $icon.Size -Inset $icon.Inset -Background $background
        try {
            $path = Join-Path $iconsDir $icon.File
            $bitmap.Save($path, [System.Drawing.Imaging.ImageFormat]::Png)
        } finally {
            $bitmap.Dispose()
        }

        Write-Host ("  public/icons/{0,-24} {1,4}x{1,-4} {2,7:N0} bytes   {3}" -f `
            $icon.File, $icon.Size, (Get-Item $path).Length, $icon.Why)
    }

    # iOS: 180x180, and transparency must be flattened or the home screen shows
    # a black square behind the logo.
    $touch = New-IconBitmap -Source $source -Size 180 -Inset 0.10 -Background ([System.Drawing.Color]::White)
    try {
        $touchPath = Join-Path $repo 'public\apple-touch-icon.png'
        $touch.Save($touchPath, [System.Drawing.Imaging.ImageFormat]::Png)
    } finally {
        $touch.Dispose()
    }
    Write-Host ("  public/{0,-30}  180x180 {1,7:N0} bytes   iPhone home screen (opaque)" -f 'apple-touch-icon.png', (Get-Item $touchPath).Length)

    # ------------------------------------------------------------------
    # favicon.ico
    # ------------------------------------------------------------------
    # Three sizes, because browsers pick: 16 for the tab, 32 for the taskbar
    # and bookmarks, 48 for a desktop shortcut.
    $sizes = @(16, 32, 48)
    $entries = @()

    foreach ($size in $sizes) {
        $bitmap = New-IconBitmap -Source $source -Size $size -Inset 0.0
        try {
            $entries += , @{ Size = $size; Data = (Get-PngBytes -Bitmap $bitmap) }
        } finally {
            $bitmap.Dispose()
        }
    }

    $icoPath = Join-Path $repo 'public\favicon.ico'

    # ICO container: a 6-byte header, one 16-byte directory entry per image,
    # then the image data. Each image here is a PNG, which Windows has accepted
    # since Vista and every other browser has always accepted.
    #
    # Assembled into a byte list rather than with BinaryWriter: PowerShell's
    # overload resolution on Write() picks the wrong overload for a byte array
    # often enough that the archive came out with a valid header and no images.
    $out = New-Object 'System.Collections.Generic.List[byte]'
    $count = $entries.Count

    $out.AddRange([byte[]] @(0, 0, 1, 0, ($count -band 0xFF), (($count -shr 8) -band 0xFF)))

    $offset = 6 + (16 * $count)

    foreach ($entry in $entries) {
        $size = [int] $entry.Size
        $length = ([byte[]] $entry.Data).Length

        $out.Add([byte] ($size -band 0xFF))   # width  (0 would mean 256)
        $out.Add([byte] ($size -band 0xFF))   # height
        $out.Add([byte] 0)                    # palette size (0 = truecolour)
        $out.Add([byte] 0)                    # reserved
        $out.Add([byte] 1)                    # colour planes, low byte
        $out.Add([byte] 0)
        $out.Add([byte] 32)                   # bits per pixel, low byte
        $out.Add([byte] 0)

        $out.Add([byte] ($length -band 0xFF))
        $out.Add([byte] (($length -shr 8) -band 0xFF))
        $out.Add([byte] (($length -shr 16) -band 0xFF))
        $out.Add([byte] (($length -shr 24) -band 0xFF))

        $out.Add([byte] ($offset -band 0xFF))
        $out.Add([byte] (($offset -shr 8) -band 0xFF))
        $out.Add([byte] (($offset -shr 16) -band 0xFF))
        $out.Add([byte] (($offset -shr 24) -band 0xFF))

        $offset += $length
    }

    foreach ($entry in $entries) {
        $out.AddRange([byte[]] $entry.Data)
    }

    [System.IO.File]::WriteAllBytes($icoPath, $out.ToArray())

    Write-Host ''
    Write-Host '=== favicon ==='
    foreach ($entry in $entries) {
        Write-Host ("  {0,3} x {0,-3}  {1,7:N0} bytes" -f $entry.Size, ([byte[]] $entry.Data).Length)
    }
    Write-Host ("  public/favicon.ico  ({0:N0} bytes in total)" -f (Get-Item $icoPath).Length)
    Write-Host ''

    # Read it back the way a browser does: the directory entries have to point
    # at image data that is actually inside the file. A correct-looking header
    # with wrong offsets opens in one browser and not another.
    $bytes = [System.IO.File]::ReadAllBytes($icoPath)

    if ($bytes.Length -lt 6) { throw 'The favicon is shorter than its header.' }

    $declared = [int] ([BitConverter]::ToUInt16($bytes, 4))
    if ($declared -ne $count) { throw "Header claims $declared images, expected $count." }

    for ($i = 0; $i -lt $declared; $i++) {
        $base = 6 + (16 * $i)
        $length = [int] [BitConverter]::ToUInt32($bytes, $base + 8)
        $start = [int] [BitConverter]::ToUInt32($bytes, $base + 12)

        if ($length -le 0) { throw "Image $i is empty." }
        if (($start + $length) -gt $bytes.Length) { throw "Image $i runs past the end of the file." }

        $signature = ($bytes[$start..($start + 7)] -join ',')
        if ($signature -ne '137,80,78,71,13,10,26,10') { throw "Image $i is not a PNG." }
    }

    Write-Host "  Re-read as $declared images, each pointing at real PNG data inside the file."
    Write-Host ''
    Write-Host 'Now check the manifest and the <link> tags still match these names.'
    Write-Host ''
} finally {
    $source.Dispose()
    $sourceStream.Dispose()
}
