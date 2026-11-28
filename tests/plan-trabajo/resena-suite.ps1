param([switch]$ConMotores)
$ErrorActionPreference = 'Stop'
$php = 'C:/wamp64/bin/php/php8.5.0/php.exe'
$fallos = 0
foreach ($test in @('tests/plan-trabajo/resena-puras-test.php','tests/plan-trabajo/resena-resumen-puras-test.php','tests/plan-trabajo/resena-estado-test.php','tests/plan-trabajo/resena-wamid-test.php','tests/cierre/puras-test.php','tests/entregables/puras-test.php','tests/plan/avance-puras-test.php','tests/plan/cierre-puras-test.php')) {
    # Windows PowerShell 5.1 convierte stderr nativo en ErrorRecord al redirigirlo.
    # Mostrar el diagnóstico y comprobar el exit code; restaurar Stop para el resto.
    try {
        $ErrorActionPreference = 'Continue'
        & $php -d xdebug.mode=off $test 2>&1 | Where-Object { $_ -match 'FAIL|fallas|TODO OK|Failed loading|Fatal|Parse|Warning|Notice' } | ForEach-Object { $_.ToString() }
        if ($LASTEXITCODE -ne 0) { $fallos++ }
    } finally { $ErrorActionPreference = 'Stop' }
}
python -B tests/plan-trabajo/resena-n8n-test.py
if ($LASTEXITCODE -ne 0) { $fallos++ }
python -B N8N/resena-google/aplicar.py --dry-run | node tests/plan-trabajo/resena-n8n-expresiones-test.js
if ($LASTEXITCODE -ne 0) { $fallos++ }
python -B tests/plan-trabajo/resena-resumen-n8n-test.py
if ($LASTEXITCODE -ne 0) { $fallos++ }
node tests/plan-trabajo/resena-resumen-correo-test.js
if ($LASTEXITCODE -ne 0) { $fallos++ }
python -B N8N/resena-google/resumen.py --dry-run | node tests/plan-trabajo/resena-n8n-expresiones-test.js
if ($LASTEXITCODE -ne 0) { $fallos++ }
node --check wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js
if ($LASTEXITCODE -ne 0) { $fallos++ }
$files = @('wp-content/mu-plugins/crm-ai-completo.php','wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php','wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp-recordatorios.php','wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php') + (Get-ChildItem wp-content/themes/automatiza-tech/inc/plan-trabajo/resena*.php).FullName + (Get-ChildItem tests/plan-trabajo/resena*.php).FullName
foreach ($file in $files) { $null = & $php -n -l $file; if ($LASTEXITCODE -ne 0) { $fallos++ } }
if ($ConMotores) {
    foreach ($motor in @('mysql','mariadb')) {
        & tests/plan-trabajo/resena-motor-test.ps1 -Motor $motor
        if ($LASTEXITCODE -ne 0) { $fallos++ }
    }
}
Write-Output "Suite offline completa: $fallos fallos; lint $($files.Count) PHP"
exit ([int]($fallos -gt 0))
