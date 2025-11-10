[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [switch]$DryRun
)

# Archive dev/test and backup files into archive/<timestamp>/ preserving relative paths.
# Safe by default: excludes wp-admin, wp-includes, wp-content, tools, archive, and wp-* core files.

$ErrorActionPreference = 'Stop'

$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$archiveRoot = Join-Path $root 'archive'
$archiveDest = Join-Path $archiveRoot $timestamp

# Ensure archive destination exists
New-Item -ItemType Directory -Path $archiveDest -Force | Out-Null

# Patterns of dev/test/backup files to archive (future-proof)
$patterns = @(
    'test-*.php','test-*.html','test_*.php','*_test.php','*test*.html',
    'debug-*.php','debug_*.php','debug*.js',
    'check-*.php','check_*.php',
    'install-*.bat','*.bat','*.cmd',
    'fix-all-files.php','full-diagnosis.php','verify_current_data.php','reorder_plans.php',
    'servicios-admin-simple.php','activate-plans.php','init-contact-system.php','add_color_fields.php',
    'setup-final.php','set-highlight-profesional.php',
    'readme.html','WAMP-GUIA.md','INICIO-RAPIDO.md',
    'sql/*.sql','*.sql'
)

# Exclude core/theme/plugin paths and critical files
$excludeRegex = [regex]'(\\wp-admin\\|\\wp-includes\\|\\wp-content\\|\\tools\\|\\archive\\|\\\.git\\|\\\.github\\|\\node_modules\\|\\vendor\\|\\logs\\|\\backups\\|\\uploads\\)'
$coreRegex = [regex]'(\\|/)wp-.*\.php$'
$configRegex = [regex]'(\\|/)wp-config\.php$'

$moveCount = 0
$skipCount = 0

foreach ($pattern in $patterns) {
    $items = Get-ChildItem -Path $root -Filter $pattern -Recurse -File -ErrorAction SilentlyContinue
    foreach ($item in $items) {
        $full = $item.FullName
        if ($excludeRegex.IsMatch($full) -or $coreRegex.IsMatch($full) -or $configRegex.IsMatch($full)) {
            $skipCount++
            continue
        }
        # Prevent moving this script or anything in tools
        if ($full -like (Join-Path $PSScriptRoot '*')) { $skipCount++; continue }

        $relative = $full.Substring($root.Length).TrimStart('\\','/')
        $destPath = Join-Path $archiveDest $relative
        $destDir = Split-Path $destPath -Parent
        if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }

        if ($DryRun) {
            Write-Host "DRY-RUN: Would move $relative -> $($destPath.Substring($root.Length).TrimStart('\\','/'))"
        } else {
            if ($PSCmdlet.ShouldProcess($relative, 'Archive')) {
                Move-Item -LiteralPath $full -Destination $destPath -Force
                Write-Host "Moved: $relative"
                $moveCount++
            }
        }
    }
}

Write-Host "Done. Moved: $moveCount file(s). Skipped: $skipCount file(s). Archive: $archiveDest"
