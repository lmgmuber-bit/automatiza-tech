# Script PowerShell para limpiar archivos PHP de BOM y contenido extra

Write-Host "=== LIMPIEZA DE ARCHIVOS PHP ===" -ForegroundColor Green

$files = @(
    "wp-content\themes\automatiza-tech\functions.php",
    "wp-content\themes\automatiza-tech\inc\services-manager.php", 
    "wp-content\themes\automatiza-tech\inc\services-frontend.php"
)

foreach ($file in $files) {
    if (Test-Path $file) {
        Write-Host "🔧 Limpiando: $file" -ForegroundColor Yellow
        
        # Crear backup
        $backupFile = "$file.backup-clean"
        Copy-Item $file $backupFile
        Write-Host "  ✅ Backup creado: $backupFile" -ForegroundColor Green
        
        # Leer archivo como bytes
        $bytes = [System.IO.File]::ReadAllBytes($file)
        
        # Verificar y remover BOM UTF-8 (EF BB BF)
        if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
            Write-Host "  ⚠️ BOM UTF-8 detectado, removiendo..." -ForegroundColor Cyan
            $bytes = $bytes[3..($bytes.Length-1)]
        }
        
        # Convertir a string para procesar contenido
        $content = [System.Text.Encoding]::UTF8.GetString($bytes)
        
        # Buscar último ?> y remover todo después
        $lastClosePos = $content.LastIndexOf('?>')
        if ($lastClosePos -ne -1) {
            $afterClose = $content.Substring($lastClosePos + 2)
            if ($afterClose.Trim() -ne "") {
                Write-Host "  ⚠️ Contenido después de ?> detectado, removiendo..." -ForegroundColor Cyan
                $content = $content.Substring(0, $lastClosePos)
            } else {
                Write-Host "  ⚠️ Removiendo tag de cierre ?> (buena práctica)" -ForegroundColor Cyan
                $content = $content.Substring(0, $lastClosePos)
            }
        }
        
        # Limpiar espacios finales
        $content = $content.TrimEnd()
        
        # Escribir archivo limpio (sin BOM)
        $utf8NoBom = New-Object System.Text.UTF8Encoding $false
        [System.IO.File]::WriteAllText($file, $content, $utf8NoBom)
        
        Write-Host "  ✅ Archivo limpiado exitosamente" -ForegroundColor Green
        Write-Host ""
    } else {
        Write-Host "❌ Archivo no encontrado: $file" -ForegroundColor Red
    }
}

Write-Host "🎉 LIMPIEZA COMPLETADA" -ForegroundColor Green
Write-Host "Ahora prueba la funcionalidad de editar servicios en WordPress admin." -ForegroundColor Yellow