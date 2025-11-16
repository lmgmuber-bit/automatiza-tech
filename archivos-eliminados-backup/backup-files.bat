@echo off
echo === BACKUP DE ARCHIVOS ANTES DE LIMPIEZA ===

copy "wp-content\themes\automatiza-tech\functions.php" "functions.php.backup-clean"
copy "wp-content\themes\automatiza-tech\inc\services-manager.php" "services-manager.php.backup-clean"  
copy "wp-content\themes\automatiza-tech\inc\services-frontend.php" "services-frontend.php.backup-clean"

echo ✅ Backups creados
echo.
echo Ahora voy a limpiar los archivos manualmente...
echo Por favor, ejecuta el script de verificación después.