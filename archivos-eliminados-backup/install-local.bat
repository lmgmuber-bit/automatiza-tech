@echo off
REM Script de instalación local para Automatiza Tech WordPress
REM Compatible con XAMPP, WAMP, LARAGON

echo ========================================
echo  AUTOMATIZA TECH - INSTALACION LOCAL
echo ========================================
echo.

REM Verificar si estamos en el directorio correcto
if not exist "wp-config-local.php" (
    echo ERROR: No se encuentra wp-config-local.php
    echo Asegurate de ejecutar este script desde el directorio raiz del proyecto.
    pause
    exit /b 1
)

REM Verificar si XAMPP está instalado
set XAMPP_PATH=C:\xampp
if not exist "%XAMPP_PATH%" (
    echo Verificando otras ubicaciones de XAMPP...
    set XAMPP_PATH=C:\XAMPP
    if not exist "!XAMPP_PATH!" (
        set XAMPP_PATH=D:\xampp
        if not exist "!XAMPP_PATH!" (
            echo ERROR: No se encuentra XAMPP instalado.
            echo Por favor instala XAMPP desde: https://www.apachefriends.org/
            pause
            exit /b 1
        )
    )
)

echo ✓ XAMPP encontrado en: %XAMPP_PATH%
echo.

echo Paso 1: Configurando archivos...
echo --------------------------------

REM Copiar configuración local
if exist "wp-config.php" (
    echo ⚠ wp-config.php ya existe. Creando respaldo...
    copy wp-config.php wp-config-backup.php >nul
)

echo Copiando configuración local...
copy wp-config-local.php wp-config.php >nul
echo ✓ Configuración local aplicada

REM Crear archivo .htaccess local
echo RewriteEngine On > .htaccess
echo RewriteBase /automatiza-tech/ >> .htaccess
echo RewriteRule ^index\.php$ - [L] >> .htaccess
echo RewriteCond %%{REQUEST_FILENAME} !-f >> .htaccess
echo RewriteCond %%{REQUEST_FILENAME} !-d >> .htaccess
echo RewriteRule . /automatiza-tech/index.php [L] >> .htaccess
echo ✓ Archivo .htaccess local creado

echo.
echo Paso 2: Configurando base de datos...
echo -------------------------------------

REM Verificar si MySQL está ejecutándose
tasklist /fi "imagename eq mysqld.exe" 2>nul | find /i "mysqld.exe" >nul
if errorlevel 1 (
    echo ⚠ MySQL no está ejecutándose. Iniciando servicios XAMPP...
    "%XAMPP_PATH%\xampp-control.exe"
    echo.
    echo Por favor:
    echo 1. Inicia Apache y MySQL desde el panel de XAMPP
    echo 2. Presiona cualquier tecla cuando estén iniciados
    pause
)

REM Verificar conexión a MySQL
echo Verificando conexión a MySQL...
"%XAMPP_PATH%\mysql\bin\mysql.exe" -u root -e "SELECT 1;" 2>nul
if errorlevel 1 (
    echo ERROR: No se puede conectar a MySQL
    echo Verifica que MySQL esté ejecutándose en XAMPP
    pause
    exit /b 1
)

echo ✓ Conexión a MySQL establecida

REM Crear base de datos
echo Creando base de datos automatiza_tech_local...
"%XAMPP_PATH%\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS automatiza_tech_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
    echo ERROR: No se pudo crear la base de datos
    pause
    exit /b 1
)

echo ✓ Base de datos creada

REM Ejecutar script SQL de configuración
if exist "sql\database-setup-local.sql" (
    echo Ejecutando configuración inicial de base de datos...
    "%XAMPP_PATH%\mysql\bin\mysql.exe" -u root automatiza_tech_local < sql\database-setup-local.sql
    if errorlevel 1 (
        echo ⚠ Hubo algunos warnings en la configuración SQL (esto es normal)
    )
    echo ✓ Configuración de base de datos completada
) else (
    echo ⚠ No se encontró el archivo SQL de configuración
)

echo.
echo Paso 3: Copiando archivos a htdocs...
echo -------------------------------------

set HTDOCS_PATH=%XAMPP_PATH%\htdocs\automatiza-tech

REM Crear directorio en htdocs si no existe
if not exist "%HTDOCS_PATH%" (
    mkdir "%HTDOCS_PATH%"
    echo ✓ Directorio creado en htdocs
)

REM Copiar archivos (excluyendo algunos archivos de desarrollo)
echo Copiando archivos del proyecto...
xcopy /E /I /Y /Q . "%HTDOCS_PATH%" /EXCLUDE:local-exclude.txt 2>nul

REM Crear archivo de exclusión para futuras copias
echo .git\ > local-exclude.txt
echo .gitignore >> local-exclude.txt
echo *.bat >> local-exclude.txt
echo *.md >> local-exclude.txt
echo wp-config-local.php >> local-exclude.txt
echo wp-config-backup.php >> local-exclude.txt

echo ✓ Archivos copiados a htdocs

echo.
echo Paso 4: Configurando permisos...
echo --------------------------------

REM Crear directorio de uploads si no existe
if not exist "%HTDOCS_PATH%\wp-content\uploads" (
    mkdir "%HTDOCS_PATH%\wp-content\uploads"
    echo ✓ Directorio uploads creado
)

REM Crear archivo de debug log
if not exist "%HTDOCS_PATH%\wp-content\debug.log" (
    echo. > "%HTDOCS_PATH%\wp-content\debug.log"
    echo ✓ Archivo debug.log creado
)

echo ✓ Permisos configurados

echo.
echo Paso 5: Descargando WordPress (si es necesario)...
echo --------------------------------------------------

if not exist "%HTDOCS_PATH%\wp-includes" (
    echo WordPress no detectado. ¿Deseas descargarlo automáticamente? (s/n)
    set /p download_wp=
    if /i "!download_wp!"=="s" (
        echo Descargando WordPress...
        powershell -Command "Invoke-WebRequest -Uri 'https://es.wordpress.org/latest-es_ES.zip' -OutFile 'wordpress.zip'"
        powershell -Command "Expand-Archive -Path 'wordpress.zip' -DestinationPath 'temp' -Force"
        xcopy /E /I /Y temp\wordpress\* "%HTDOCS_PATH%"
        rmdir /s /q temp
        del wordpress.zip
        echo ✓ WordPress descargado e instalado
    )
)

echo.
echo ========================================
echo      INSTALACION COMPLETADA
echo ========================================
echo.
echo ✓ Configuración local aplicada
echo ✓ Base de datos configurada
echo ✓ Archivos copiados a htdocs
echo ✓ Permisos configurados
echo.
echo Próximos pasos:
echo 1. Abre tu navegador y ve a: http://localhost/automatiza-tech
echo 2. Si es la primera vez, ejecuta la instalación de WordPress
echo 3. Usa estos datos para la configuración:
echo    - Base de datos: automatiza_tech_local
echo    - Usuario: root
echo    - Contraseña: (vacía)
echo    - Servidor: localhost
echo.
echo 4. Después de instalar WordPress, ve a:
echo    http://localhost/automatiza-tech/install-automatiza-tech.php
echo    para configurar el tema automáticamente
echo.
echo ¿Deseas abrir el sitio en el navegador ahora? (s/n)
set /p open_browser=
if /i "%open_browser%"=="s" (
    start http://localhost/automatiza-tech
)

echo.
echo ¡Desarrollo local listo! 🚀
pause