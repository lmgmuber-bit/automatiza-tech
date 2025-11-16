@echo off
echo ============================================
echo    ARCHIVOS QUE SE VAN A LIMPIAR
echo ============================================
echo.

cd /d c:\wamp64\www\automatiza-tech

echo [ARCHIVOS DE TEST]:
echo ------------------------------------
dir /b test-*.php test-*.html test_*.php 2>nul
echo.

echo [ARCHIVOS DE DEBUG]:
echo ------------------------------------
dir /b debug*.php debug*.log debug*.js 2>nul
echo.

echo [ARCHIVOS DE VERIFICACION]:
echo ------------------------------------
dir /b check-*.php verify-*.php 2>nul
echo.

echo [DOCUMENTACION MD]:
echo ------------------------------------
dir /b *.md 2>nul
echo.

echo [ARCHIVOS DE INSTALACION]:
echo ------------------------------------
dir /b install-*.php install-*.bat add-*.php setup-*.php 2>nul
echo.

echo [ARCHIVOS DE LIMPIEZA/FIX]:
echo ------------------------------------
dir /b clean-*.php clean-*.bat clear-*.php fix-*.php fix-*.ps1 2>nul
echo.

echo [ARCHIVOS DE GENERACION/PREVIEW]:
echo ------------------------------------
dir /b generate-*.php preview-*.html email-preview.html 2>nul
echo.

echo [ARCHIVOS DE CORRECCION]:
echo ------------------------------------
dir /b correccion-*.bat correccion-*.html limpieza-*.bat 2>nul
echo.

echo [ARCHIVOS HTML DE PRUEBA]:
echo ------------------------------------
dir /b boton-*.html formulario-*.html icono-*.html verificacion-*.html demo-*.html 2>nul
echo.

echo [ARCHIVOS SQL DE MIGRACION]:
echo ------------------------------------
dir /b create-*.sql add-*.sql fix-*.sql 2>nul
echo.

echo [ARCHIVOS TXT]:
echo ------------------------------------
dir /b *.txt 2>nul
echo.

echo [BACKUPS]:
echo ------------------------------------
dir /b *.backup *.backup-clean *.bak *.old 2>nul
echo.

echo [ARCHIVOS ESPECIFICOS]:
echo ------------------------------------
if exist "buscar-url-qr.php" echo buscar-url-qr.php
if exist "cookie-test.php" echo cookie-test.php
if exist "full-diagnosis.php" echo full-diagnosis.php
if exist "diagnose-invoices-prod.php" echo diagnose-invoices-prod.php
if exist "find-invoice-handlers.php" echo find-invoice-handlers.php
if exist "reorder_plans.php" echo reorder_plans.php
if exist "servicios-admin-simple.php" echo servicios-admin-simple.php
if exist "show-client-structure.php" echo show-client-structure.php
if exist "smtp-diagnostico.php" echo smtp-diagnostico.php
if exist "validar-factura.php" echo validar-factura.php
if exist "create-validation-page.php" echo create-validation-page.php
if exist "smtp-config.env.example" echo smtp-config.env.example
if exist "backup-files.bat" echo backup-files.bat
echo.

echo ============================================
echo Total de categorias a limpiar: 13
echo ============================================
echo.
echo Si quieres proceder con la limpieza:
echo Ejecuta: cleanup-production.bat
echo.
pause
