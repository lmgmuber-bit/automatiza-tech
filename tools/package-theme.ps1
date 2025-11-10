param(
    [string]$ThemePath = "c:\wamp64\www\automatiza-tech\wp-content\themes\automatiza-tech",
    [string]$OutDir = "c:\wamp64\www\automatiza-tech\dist"
)

if (!(Test-Path $ThemePath)) {
    Write-Error "Theme path not found: $ThemePath"
    exit 1
}

if (!(Test-Path $OutDir)) { New-Item -ItemType Directory -Path $OutDir | Out-Null }

$ts = Get-Date -Format "yyyyMMdd-HHmmss"
$zipName = "automatiza-tech-theme-$ts.zip"
$outZip = Join-Path $OutDir $zipName

# Ensure we zip with folder root 'automatiza-tech/' inside the archive
$parent = Split-Path $ThemePath -Parent
$folderName = Split-Path $ThemePath -Leaf

Push-Location $parent
try {
    if (Test-Path $outZip) { Remove-Item $outZip -Force }
    Compress-Archive -Path $folderName -DestinationPath $outZip -Force
    Write-Host "Created theme package:" $outZip
}
finally {
    Pop-Location
}
