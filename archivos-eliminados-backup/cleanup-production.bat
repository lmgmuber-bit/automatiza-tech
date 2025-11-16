@echo off
echo ============================================
echo    LIMPIEZA DE ARCHIVOS DE PRUEBA
echo ============================================
echo.
echo Este script eliminará:
echo - Archivos de test (test-*.php, test-*.html)
echo - Archivos de debug (debug*.php, debug*.log)
echo - Archivos de verificación (check-*.php, verify-*.php)
echo - Documentación .md
echo - Scripts de instalación/migración
echo - Archivos de limpieza antiguos
echo.
echo ARCHIVOS QUE SE MANTENDRAN:
echo - Todos los archivos de WordPress core
echo - Tema y plugins
echo - Archivos funcionales del sistema
echo.
pause
echo.
echo Iniciando limpieza...
echo.

cd /d c:\wamp64\www\automatiza-tech

REM Crear carpeta de backup
if not exist "archivos-eliminados-backup" mkdir archivos-eliminados-backup

echo [MOVIENDO ARCHIVOS A BACKUP...]
echo.

REM Mover archivos test
for %%f in (test-*.php test-*.html test_*.php) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

REM Mover archivos debug
for %%f in (debug*.php debug*.log debug*.js) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

REM Mover archivos check
for %%f in (check-*.php check_*.php) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

REM Mover archivos verify
for %%f in (verify*.php) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

REM Mover documentación MD
for %%f in (*.md) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

REM Mover archivos de instalación
for %%f in (install-*.php install-*.bat add-*.php setup-*.php) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

REM Mover archivos de limpieza
for %%f in (clean-*.php clean-*.bat clear-*.php fix-*.php fix-*.ps1) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

REM Mover archivos de generación/preview
for %%f in (generate-*.php preview-*.html email-preview.html) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

REM Mover archivos de corrección
for %%f in (correccion-*.bat correccion-*.html limpieza-*.bat) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

REM Mover archivos específicos
if exist "buscar-url-qr.php" move buscar-url-qr.php archivos-eliminados-backup\
if exist "cookie-test.php" move cookie-test.php archivos-eliminados-backup\
if exist "full-diagnosis.php" move full-diagnosis.php archivos-eliminados-backup\
if exist "diagnose-invoices-prod.php" move diagnose-invoices-prod.php archivos-eliminados-backup\
if exist "find-invoice-handlers.php" move find-invoice-handlers.php archivos-eliminados-backup\
if exist "reorder_plans.php" move reorder_plans.php archivos-eliminados-backup\
if exist "servicios-admin-simple.php" move servicios-admin-simple.php archivos-eliminados-backup\
if exist "show-client-structure.php" move show-client-structure.php archivos-eliminados-backup\
if exist "smtp-diagnostico.php" move smtp-diagnostico.php archivos-eliminados-backup\
if exist "validar-factura.php" move validar-factura.php archivos-eliminados-backup\
if exist "create-validation-page.php" move create-validation-page.php archivos-eliminados-backup\
if exist "smtp-config.env.example" move smtp-config.env.example archivos-eliminados-backup\

REM Mover archivos HTML de prueba/corrección
for %%f in (boton-*.html formulario-*.html icono-*.html verificacion-*.html demo-*.html) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

REM Mover archivos SQL de creación/migración
for %%f in (create-*.sql add-*.sql fix-*.sql) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

REM Mover archivos TXT de documentación
for %%f in (*.txt) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

REM Mover archivos backup
for %%f in (*.backup *.backup-clean *.bak *.old) do (
    if exist "%%f" (
        echo Moviendo %%f
        move "%%f" archivos-eliminados-backup\
    )
)

echo.
echo ============================================
echo    LIMPIEZA COMPLETADA
echo ============================================
echo.
echo Archivos movidos a: archivos-eliminados-backup\
echo.
echo Si todo funciona bien, puedes eliminar la carpeta.
echo Si algo falla, restaura desde el backup.
echo.
pause
