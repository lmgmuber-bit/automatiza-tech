# Script: organizar-documentacion-tests.ps1
# Crea carpetas, respalda documentación y limpia archivos de test.

$root = Get-Location
$docDir = Join-Path $root 'Documentacion'
$backupDocs = Join-Path $root 'backup-docs'
$backupTests = Join-Path $root 'backup-tests'

$dirs = @($docDir,$backupDocs,$backupTests)
foreach($d in $dirs){ if(-not (Test-Path $d)){ New-Item -ItemType Directory -Path $d | Out-Null } }

# Archivos de documentación (root + carpeta Docs)
$docFiles = @()
$docFiles += Get-ChildItem -Path $root -File -Include *.md,readme*.html -ErrorAction SilentlyContinue
$docsFolder = Join-Path $root 'Docs'
if(Test-Path $docsFolder){ $docFiles += Get-ChildItem -Path $docsFolder -Recurse -File -ErrorAction SilentlyContinue }

foreach($f in $docFiles){
    Copy-Item $f.FullName -Destination $backupDocs -Force
    $destPath = Join-Path $docDir $f.Name
    if(-not (Test-Path $destPath)){ Move-Item $f.FullName -Destination $docDir -Force }
}

# Archivos de test (patrones comunes) en todo el árbol
$testFiles = Get-ChildItem -Path $root -Recurse -File -Include test-*.php,*test*.php -ErrorAction SilentlyContinue
foreach($t in $testFiles){
    Copy-Item $t.FullName -Destination $backupTests -Force
    Remove-Item $t.FullName -Force
}

Write-Host "Documentación movida a $docDir:"; $docFiles | Select-Object -ExpandProperty Name | Sort-Object | ForEach-Object { Write-Host " - $_" }
Write-Host "Tests respaldados y eliminados:"; $testFiles | Select-Object -ExpandProperty FullName | Sort-Object | ForEach-Object { Write-Host " - $_" }
Write-Host "Backup docs en: $backupDocs"
Write-Host "Backup tests en: $backupTests"
