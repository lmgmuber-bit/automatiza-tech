@echo off
echo Organizando documentacion y limpiando tests...

REM Crear carpetas
if not exist "Documentacion" mkdir Documentacion
if not exist "backup-docs" mkdir backup-docs
if not exist "backup-tests" mkdir backup-tests

REM Respaldar y eliminar archivos test (PHP y otros) PRIMERO
echo Limpiando archivos test...
for %%f in (test*.php test*.* *-test.php) do (
    if exist "%%f" (
        echo - Respaldando y eliminando: %%f
        copy "%%f" "backup-tests\" >nul 2>&1
        del "%%f"
    )
)

REM Respaldar y mover archivos de documentacion (MD y HTML)
echo Respaldando documentacion...
for %%f in (*.md *.html) do (
    if exist "%%f" (
        echo - Moviendo: %%f
        copy "%%f" "backup-docs\" >nul 2>&1
        move "%%f" "Documentacion\" >nul 2>&1
    )
)

REM Mover archivos .bat de utilidades (excepto este mismo)
echo Moviendo archivos .bat...
for %%f in (*.bat) do (
    if /i not "%%f"=="organizar-docs-tests.bat" (
        if exist "%%f" (
            echo - Moviendo: %%f
            copy "%%f" "backup-docs\" >nul 2>&1
            move "%%f" "Documentacion\" >nul 2>&1
        )
    )
)

REM Mover archivo .ps1
if exist "organizar-documentacion-tests.ps1" (
    echo - Moviendo: organizar-documentacion-tests.ps1
    copy "organizar-documentacion-tests.ps1" "backup-docs\" >nul 2>&1
    move "organizar-documentacion-tests.ps1" "Documentacion\" >nul 2>&1
)

REM Copiar archivos de carpeta Docs si existe
if exist "Docs" (
    echo Copiando carpeta Docs...
    xcopy "Docs\*.*" "backup-docs\Docs\" /E /I /Y >nul 2>&1
    xcopy "Docs\*.*" "Documentacion\Docs\" /E /I /Y >nul 2>&1
)

echo.
echo Proceso completado!
echo - Documentacion en: Documentacion\
echo - Backup docs en: backup-docs\
echo - Backup tests en: backup-tests\
pause
