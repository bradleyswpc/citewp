#Requires -Version 5.0
<#
.SYNOPSIS
    Builds a distributable .zip of the AI Search Optimizer plugin, honoring .distignore.

.DESCRIPTION
    Replicates the behavior of wp-cli/dist-archive-command on Windows without requiring
    a `zip` binary on PATH. Reads .distignore, copies the plugin folder to a temp staging
    directory minus excluded paths, zips the staged folder, cleans up.

    The resulting .zip contains a single top-level folder named "ai-search-optimizer/"
    matching WordPress's expected plugin install structure.

.PARAMETER OutputPath
    Optional. Where to write the resulting .zip. Defaults to the plugins parent directory
    (one level up from this script's location).

.EXAMPLE
    .\package.ps1
    Builds ai-search-optimizer.zip in the plugins parent directory.

.EXAMPLE
    .\package.ps1 -OutputPath "C:\Users\KingpinBWP\Desktop\"
    Builds the zip to a specified location.

.NOTES
    Run from anywhere. The script resolves its own location and operates relative to that.
#>

param(
    [string]$OutputPath = $null
)

$ErrorActionPreference = "Stop"

# ============================================================================
# Resolve paths
# ============================================================================
$PluginRoot = $PSScriptRoot
$PluginSlug = Split-Path -Leaf $PluginRoot
$DistIgnorePath = Join-Path $PluginRoot ".distignore"

if (-not (Test-Path $DistIgnorePath)) {
    Write-Error "No .distignore found at $DistIgnorePath. Aborting."
    exit 1
}

# Read plugin version from the main plugin file header for output naming
$MainFile = Join-Path $PluginRoot "$PluginSlug.php"
$PluginVersion = ""
if (Test-Path $MainFile) {
    $versionMatch = Select-String -Path $MainFile -Pattern '^\s*\*\s*Version:\s*(.+)$' | Select-Object -First 1
    if ($versionMatch) {
        $PluginVersion = $versionMatch.Matches[0].Groups[1].Value.Trim()
    }
}

# Default output location: one level up from the plugin folder
if (-not $OutputPath) {
    $OutputPath = Split-Path -Parent $PluginRoot
}

$ZipFilename = if ($PluginVersion) { "$PluginSlug.$PluginVersion.zip" } else { "$PluginSlug.zip" }
$ZipFullPath = Join-Path $OutputPath $ZipFilename

Write-Host ""
Write-Host "=== AI Search Optimizer Packaging Script ===" -ForegroundColor Cyan
Write-Host "Plugin root:    $PluginRoot"
Write-Host "Plugin slug:    $PluginSlug"
Write-Host "Plugin version: $(if ($PluginVersion) { $PluginVersion } else { '(not detected)' })"
Write-Host "Output:         $ZipFullPath"
Write-Host ""

# ============================================================================
# Parse .distignore
# ============================================================================
$ExcludePatterns = Get-Content $DistIgnorePath | ForEach-Object { $_.Trim() } | Where-Object {
    $_ -and -not $_.StartsWith("#")
}

Write-Host "Exclusion patterns from .distignore:" -ForegroundColor Yellow
$ExcludePatterns | ForEach-Object { Write-Host "  $_" }
Write-Host ""

# ============================================================================
# Stage to temp directory using robocopy
# ============================================================================
$TempRoot = Join-Path $env:TEMP "citewp-package-$([guid]::NewGuid().ToString('N'))"
$StagingPath = Join-Path $TempRoot $PluginSlug

Write-Host "Staging to temp directory..." -ForegroundColor Yellow
New-Item -ItemType Directory -Path $StagingPath -Force | Out-Null

# Build robocopy exclusion lists
$ExcludeDirs = @()
$ExcludeFiles = @()

foreach ($pattern in $ExcludePatterns) {
    if ($pattern.EndsWith("/")) {
        $dirName = $pattern.TrimEnd("/")
        $ExcludeDirs += (Join-Path $PluginRoot $dirName)
    } elseif ($pattern.Contains("*") -or $pattern.Contains("?")) {
        # Wildcard: pass filename pattern only; robocopy /XF matches by name, not path
        $ExcludeFiles += $pattern
    } else {
        $ExcludeFiles += (Join-Path $PluginRoot $pattern)
    }
}

$robocopyArgs = @(
    $PluginRoot
    $StagingPath
    "/E"
    "/NFL"
    "/NDL"
    "/NP"
    "/NJH"
    "/NJS"
)

if ($ExcludeDirs.Count -gt 0) {
    $robocopyArgs += "/XD"
    $robocopyArgs += $ExcludeDirs
}

if ($ExcludeFiles.Count -gt 0) {
    $robocopyArgs += "/XF"
    $robocopyArgs += $ExcludeFiles
}

& robocopy @robocopyArgs | Out-Null
$rcExit = $LASTEXITCODE
if ($rcExit -ge 8) {
    Write-Error "Robocopy failed with exit code $rcExit"
    Remove-Item -Recurse -Force $TempRoot -ErrorAction SilentlyContinue
    exit 1
}

# ============================================================================
# Verify staging contents
# ============================================================================
Write-Host ""
Write-Host "Staging contents (top level):" -ForegroundColor Yellow
Get-ChildItem $StagingPath | Select-Object Name, @{Name="Type";Expression={if ($_.PSIsContainer) { "DIR" } else { "FILE" }}} | Format-Table -AutoSize | Out-String | Write-Host

$stagedSize = (Get-ChildItem $StagingPath -Recurse -File | Measure-Object -Property Length -Sum).Sum
$stagedSizeMB = [math]::Round($stagedSize / 1MB, 2)
$stagedCount = (Get-ChildItem $StagingPath -Recurse -File).Count
Write-Host "Total: $stagedCount files, $stagedSizeMB MB" -ForegroundColor Green
Write-Host ""

# ============================================================================
# Compress to zip
# ============================================================================
Write-Host "Compressing to zip..." -ForegroundColor Yellow

if (Test-Path $ZipFullPath) {
    Remove-Item $ZipFullPath -Force
}

# Compress the staging plugin folder so the zip root contains "ai-search-optimizer/"
#
# We manually create the zip and add each entry with an explicit forward-slash
# relative path. This bypasses a Windows PowerShell 5.1 / .NET Framework 4.x bug
# where ZipFile.CreateFromDirectory and Compress-Archive can write entry names
# using the OS path separator (backslash on Windows) instead of the forward
# slash required by the zip spec. Servers that follow the spec strictly will
# not extract those entries as a folder hierarchy, breaking plugin install.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zipStream = [System.IO.File]::Create($ZipFullPath)
$archive = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    # Enumerate files using Push-Location so $_.FullName returns paths
    # relative to the temp root. This sidesteps all the Windows path-
    # comparison weirdness (8.3 short names, case sensitivity, trailing
    # separators) that broke earlier substring-based approaches.
    Push-Location $TempRoot
    try {
        $files = Get-ChildItem -Path . -Recurse -File
        foreach ($file in $files) {
            # $file.FullName is the absolute path. We compute the relative
            # path by resolving it against our pushed location.
            $relativePath = Resolve-Path -LiteralPath $file.FullName -Relative
            # Resolve-Path -Relative returns ".\foo\bar.php" — strip the leading ".\"
            $relativePath = $relativePath -replace '^\.[\\/]', ''
            # Normalize to forward slashes for the zip entry name
            $entryName = $relativePath -replace '\\', '/'

            $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)

            $entryStream = $entry.Open()
            $fileStream = [System.IO.File]::OpenRead($file.FullName)
            try {
                $fileStream.CopyTo($entryStream)
            } finally {
                $fileStream.Dispose()
                $entryStream.Dispose()
            }
        }
    } finally {
        Pop-Location
    }
} finally {
    $archive.Dispose()
    $zipStream.Dispose()
}

# ============================================================================
# Cleanup and report
# ============================================================================
Remove-Item -Recurse -Force $TempRoot

if (-not (Test-Path $ZipFullPath)) {
    Write-Error "Zip file was not created at $ZipFullPath"
    exit 1
}

$zipSize = (Get-Item $ZipFullPath).Length
$zipSizeMB = [math]::Round($zipSize / 1MB, 2)

Write-Host ""
Write-Host "=== Done ===" -ForegroundColor Cyan
Write-Host "Created:  $ZipFullPath"
Write-Host "Size:     $zipSizeMB MB"
Write-Host ""
Write-Host "To inspect the zip contents:" -ForegroundColor Gray
Write-Host "  Add-Type -AssemblyName System.IO.Compression.FileSystem" -ForegroundColor Gray
Write-Host "  `$z = [System.IO.Compression.ZipFile]::OpenRead('$ZipFullPath')" -ForegroundColor Gray
Write-Host "  `$z.Entries | Select-Object FullName, Length | Format-Table -AutoSize" -ForegroundColor Gray
Write-Host "  `$z.Dispose()" -ForegroundColor Gray
Write-Host ""
