#requires -version 5.1
<#
    make-icons.ps1 - regenerate the browser icon from the branding logo.

    WHY THIS EXISTS
    ---------------
    public/favicon.ico was committed as a zero-byte placeholder, so browsers
    asked for the pharmacy's icon and got nothing: an empty tab, and a blank
    square when the site is installed to a phone's home screen. The manifest
    already points at the logo instead, but the tab icon comes from this file.

    Rather than hand-drawing an .ico that nobody can edit later, this derives
    it from the logo in Business Settings. Change the logo, re-run this, and
    the tab icon follows.

    USAGE
    -----
        # Rebuild public/favicon.ico from the current logo
        .\make-icons.ps1

        # Also shrink the stored logo itself (9 0 2 x 912 at 917 KB is far
        # more detail than any screen in the app asks for)
        .\make-icons.ps1 -OptimiseLogo
#>
[CmdletBinding()]
param(
    # Source image. Defaults to the branding logo the application ships with.
    [string] $Logo,

    # Longest side of the stored logo after optimisation.
    [int] $LogoMax = 512,

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
    Draws the source image at a given size.

    The high-quality settings matter at icon sizes: without them a 32x32
    thumbnail of a logo comes out as fringed mush.
#>
function New-ScaledBitmap {
    param(
        [System.Drawing.Image] $Source,
        [int] $Width,
        [int] $Height
    )

    $bitmap = New-Object System.Drawing.Bitmap(
        $Width, $Height, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb
    )

    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    try {
        $graphics.CompositingMode    = [System.Drawing.Drawing2D.CompositingMode]::SourceCopy
        $graphics.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
        $graphics.InterpolationMode  = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $graphics.SmoothingMode      = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
        $graphics.PixelOffsetMode    = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
        $graphics.Clear([System.Drawing.Color]::Transparent)
        $graphics.DrawImage($Source, 0, 0, $Width, $Height)
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

$sourceBytes = [System.IO.File]::ReadAllBytes($Logo)
$sourceStream = New-Object System.IO.MemoryStream
$sourceStream.Write($sourceBytes, 0, $sourceBytes.Length)
$sourceStream.Position = 0

# Loaded from memory rather than FromFile: FromFile keeps a lock on the file,
# and with -OptimiseLogo the destination is that same file.
$source = [System.Drawing.Image]::FromStream($sourceStream)

try {
    Write-Host ''
    Write-Host "Source: $Logo  ($($source.Width) x $($source.Height), $([math]::Round((Get-Item $Logo).Length / 1KB)) KB)"
    Write-Host ''

    # ------------------------------------------------------------------
    # The stored logo
    # ------------------------------------------------------------------
    if ($OptimiseLogo) {
        $target = Join-Path $repo 'storage\app\public\branding\trustmed-logo.png'
        $before = (Get-Item $target).Length

        $scale = [Math]::Min(1.0, $LogoMax / [Math]::Max($source.Width, $source.Height))
        $width = [int][Math]::Max(1, [Math]::Round($source.Width * $scale))
        $height = [int][Math]::Max(1, [Math]::Round($source.Height * $scale))

        $bitmap = New-ScaledBitmap -Source $source -Width $width -Height $height
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
    # The favicon
    # ------------------------------------------------------------------
    # Three sizes, because browsers pick: 16 for the tab, 32 for the taskbar
    # and bookmarks, 48 for the desktop shortcut and some Android surfaces.
    $sizes = @(16, 32, 48)
    $entries = @()

    foreach ($size in $sizes) {
        # The logo is not exactly square, so it is drawn to a square frame
        # rather than letterboxed - a 1% squash that is invisible at this size,
        # against thin transparent bars that would not be.
        $bitmap = New-ScaledBitmap -Source $source -Width $size -Height $size
        try {
            $entries += , @{ Size = $size; Data = (Get-PngBytes -Bitmap $bitmap) }
        } finally {
            $bitmap.Dispose()
        }
    }

    $icoPath = Join-Path $repo 'public\favicon.ico'

    # ICO container: a 6-byte header, one 16-byte directory entry per image,
    # then the image data. Each image here is a PNG, which Windows has
    # accepted since Vista and every other browser has always accepted.
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

        # bytes in this image, little-endian
        $out.Add([byte] ($length -band 0xFF))
        $out.Add([byte] (($length -shr 8) -band 0xFF))
        $out.Add([byte] (($length -shr 16) -band 0xFF))
        $out.Add([byte] (($length -shr 24) -band 0xFF))

        # where it starts, little-endian
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

    Write-Host '=== favicon ==='
    Write-Host ("  {0}" -f $icoPath)
    foreach ($entry in $entries) {
        Write-Host ("    {0,3} x {0,-3}  {1,7:N0} bytes" -f $entry.Size, ([byte[]] $entry.Data).Length)
    }
    Write-Host ("  {0:N0} bytes in total" -f (Get-Item $icoPath).Length)
    Write-Host ''

    # Read it back the way a browser does: the directory entries have to point
    # at image data that is actually inside the file. This catches the classic
    # ICO mistake of a correct-looking header with wrong offsets, which happens
    # to open in one browser and not another.
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

        # Every entry here is a PNG, so it must carry the PNG signature.
        $signature = ($bytes[$start..($start + 7)] -join ',')
        if ($signature -ne '137,80,78,71,13,10,26,10') { throw "Image $i is not a PNG." }
    }

    Write-Host "  Re-read as $declared images, each pointing at real PNG data inside the file."
    Write-Host ''
} finally {
    $source.Dispose()
    $sourceStream.Dispose()
}
