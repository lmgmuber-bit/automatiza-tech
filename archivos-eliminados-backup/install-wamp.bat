@echo off
REM Script de instalación local para Automatiza Tech WordPress
REM Compatible con WAMPServer

echo ========================================
echo  AUTOMATIZA TECH - INSTALACION WAMP
echo ========================================
echo.

REM Verificar si estamos en el directorio correcto
if not exist "wp-config-local.php" (
    echo ERROR: No se encuentra wp-config-local.php
    echo Asegurate de ejecutar este script desde el directorio raiz del proyecto.
    pause
    exit /b 1
)

REM Buscar instalación de WAMPServer
set WAMP_PATH=C:\wamp64
if not exist "%WAMP_PATH%" (
    echo Verificando otras ubicaciones de WAMPServer...
    set WAMP_PATH=C:\wamp
    if not exist "!WAMP_PATH!" (
        set WAMP_PATH=D:\wamp64
        if not exist "!WAMP_PATH!" (
            set WAMP_PATH=D:\wamp
            if not exist "!WAMP_PATH!" (
                echo ERROR: No se encuentra WAMPServer instalado.
                echo Ubicaciones verificadas:
                echo - C:\wamp64
                echo - C:\wamp
                echo - D:\wamp64
                echo - D:\wamp
                echo.
                echo Por favor, especifica la ruta de WAMPServer:
                set /p WAMP_PATH=Ruta completa: 
                if not exist "!WAMP_PATH!" (
                    echo ERROR: Ruta no válida
                    pause
                    exit /b 1
                )
            )
        )
    )
)

echo ✓ WAMPServer encontrado en: %WAMP_PATH%
echo.

REM Detectar versión de PHP activa
if exist "%WAMP_PATH%\bin\php" (
    for /d %%i in ("%WAMP_PATH%\bin\php\php*") do set PHP_PATH=%%i\php.exe
) else (
    echo ⚠ No se pudo detectar PHP automáticamente
    echo Verificando versiones disponibles...
    dir "%WAMP_PATH%\bin\php" /ad /b
    echo.
    echo Por favor, especifica la versión de PHP a usar (ej: php8.1.10):
    set /p PHP_VERSION=Versión PHP: 
    set PHP_PATH=%WAMP_PATH%\bin\php\!PHP_VERSION!\php.exe
)

if not exist "%PHP_PATH%" (
    echo ERROR: No se encuentra PHP en: %PHP_PATH%
    pause
    exit /b 1
)

echo ✓ PHP encontrado: %PHP_PATH%
echo.

echo Paso 1: Configurando archivos...
echo --------------------------------

REM Copiar configuración local específica para WAMP
if exist "wp-config.php" (
    echo ⚠ wp-config.php ya existe. Creando respaldo...
    copy wp-config.php wp-config-backup.php >nul
)

echo Configurando wp-config.php para WAMPServer...
copy wp-config-local.php wp-config.php >nul

REM Actualizar configuración específica para WAMP
powershell -Command "(Get-Content wp-config.php) -replace 'localhost/automatiza-tech', 'localhost/automatiza-tech' | Set-Content wp-config.php"
echo ✓ Configuración local aplicada

REM Crear archivo .htaccess para WAMP
echo # Apache configuration for Automatiza Tech > .htaccess
echo RewriteEngine On >> .htaccess
echo RewriteBase /automatiza-tech/ >> .htaccess
echo. >> .htaccess
echo # WordPress standard redirects >> .htaccess
echo RewriteRule ^index\.php$ - [L] >> .htaccess
echo RewriteCond %%{REQUEST_FILENAME} !-f >> .htaccess
echo RewriteCond %%{REQUEST_FILENAME} !-d >> .htaccess
echo RewriteRule . /automatiza-tech/index.php [L] >> .htaccess
echo. >> .htaccess
echo # Security headers for development >> .htaccess
echo ^<IfModule mod_headers.c^> >> .htaccess
echo Header set X-Content-Type-Options nosniff >> .htaccess
echo ^</IfModule^> >> .htaccess

echo ✓ Archivo .htaccess para WAMP creado

echo.
echo Paso 2: Verificando servicios WAMP...
echo ------------------------------------

REM Verificar si Apache está ejecutándose
tasklist /fi "imagename eq httpd.exe" 2>nul | find /i "httpd.exe" >nul
if errorlevel 1 (
    echo ⚠ Apache no está ejecutándose.
    echo Verificando WAMPManager...
    tasklist /fi "imagename eq wampmanager.exe" 2>nul | find /i "wampmanager.exe" >nul
    if errorlevel 1 (
        echo Iniciando WAMPServer...
        start "" "%WAMP_PATH%\wampmanager.exe"
        timeout /t 5 /nobreak >nul
    )
    
    echo.
    echo Por favor:
    echo 1. Asegúrate de que WAMPServer esté iniciado (icono verde)
    echo 2. Verifica que Apache y MySQL estén activos
    echo 3. Presiona cualquier tecla cuando estén iniciados
    pause
)

REM Verificar MySQL
tasklist /fi "imagename eq mysqld.exe" 2>nul | find /i "mysqld.exe" >nul
if errorlevel 1 (
    echo ⚠ MySQL no está ejecutándose. Por favor, inicia MySQL desde WAMPServer.
    pause
)

echo ✓ Servicios WAMP verificados

echo.
echo Paso 3: Configurando base de datos...
echo -------------------------------------

REM Buscar MySQL de WAMP
if exist "%WAMP_PATH%\bin\mysql" (
    for /d %%i in ("%WAMP_PATH%\bin\mysql\mysql*") do set MYSQL_PATH=%%i\bin\mysql.exe
) else (
    echo ⚠ No se pudo detectar MySQL automáticamente
    set MYSQL_PATH=%WAMP_PATH%\bin\mysql\mysql8.0.31\bin\mysql.exe
)

echo Verificando conexión a MySQL...
"%MYSQL_PATH%" -u root -e "SELECT 1;" 2>nul
if errorlevel 1 (
    echo Intentando con contraseña vacía...
    "%MYSQL_PATH%" -u root -p"" -e "SELECT 1;" 2>nul
    if errorlevel 1 (
        echo ERROR: No se puede conectar a MySQL
        echo.
        echo Posibles soluciones:
        echo 1. Verifica que MySQL esté iniciado en WAMPServer
        echo 2. Usa phpMyAdmin para verificar la conexión
        echo 3. Verifica la contraseña de root de MySQL
        echo.
        echo ¿Deseas continuar manualmente? (s/n)
        set /p manual_db=
        if /i not "!manual_db!"=="s" (
            pause
            exit /b 1
        )
    ) else (
        set MYSQL_CMD="%MYSQL_PATH%" -u root -p""
    )
) else (
    set MYSQL_CMD="%MYSQL_PATH%" -u root
)

if defined MYSQL_CMD (
    echo ✓ Conexión a MySQL establecida
    
    echo Creando base de datos automatiza_tech_local...
    %MYSQL_CMD% -e "CREATE DATABASE IF NOT EXISTS automatiza_tech_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    if errorlevel 1 (
        echo ERROR: No se pudo crear la base de datos
        echo Intenta crearla manualmente desde phpMyAdmin
    ) else (
        echo ✓ Base de datos creada
    )
    
    REM Ejecutar script SQL de configuración
    if exist "sql\database-setup-local.sql" (
        echo Ejecutando configuración inicial de base de datos...
        %MYSQL_CMD% automatiza_tech_local < sql\database-setup-local.sql 2>nul
        if errorlevel 1 (
            echo ⚠ Algunos comandos SQL no se ejecutaron (esto puede ser normal)
        )
        echo ✓ Configuración de base de datos completada
    )
)

echo.
echo Paso 4: Copiando archivos a www...
echo ----------------------------------

set WWW_PATH=%WAMP_PATH%\www\automatiza-tech

REM Crear directorio en www si no existe
if not exist "%WWW_PATH%" (
    mkdir "%WWW_PATH%"
    echo ✓ Directorio creado en www
)

REM Copiar archivos (excluyendo archivos de desarrollo)
echo Copiando archivos del proyecto...

REM Crear lista de exclusiones
echo .git\ > wamp-exclude.txt
echo .gitignore >> wamp-exclude.txt
echo *.bat >> wamp-exclude.txt
echo *.md >> wamp-exclude.txt
echo wp-config-local.php >> wamp-exclude.txt
echo wp-config-backup.php >> wamp-exclude.txt
echo install-local.bat >> wamp-exclude.txt
echo install-wamp.bat >> wamp-exclude.txt

xcopy /E /I /Y /Q . "%WWW_PATH%" /EXCLUDE:wamp-exclude.txt 2>nul
echo ✓ Archivos copiados a www

echo.
echo Paso 5: Configurando permisos y directorios...
echo ----------------------------------------------

REM Crear directorios necesarios
if not exist "%WWW_PATH%\wp-content\uploads" (
    mkdir "%WWW_PATH%\wp-content\uploads"
    echo ✓ Directorio uploads creado
)

if not exist "%WWW_PATH%\wp-content\cache" (
    mkdir "%WWW_PATH%\wp-content\cache"
    echo ✓ Directorio cache creado
)

REM Crear archivos de log para desarrollo
if not exist "%WWW_PATH%\wp-content\debug.log" (
    echo. > "%WWW_PATH%\wp-content\debug.log"
    echo ✓ Archivo debug.log creado
)

echo ✓ Permisos y directorios configurados

echo.
echo Paso 6: Verificando WordPress...
echo --------------------------------

if not exist "%WWW_PATH%\wp-includes" (
    echo WordPress no detectado. ¿Deseas descargarlo automáticamente? (s/n)
    set /p download_wp=
    if /i "!download_wp!"=="s" (
        echo Descargando WordPress en español...
        powershell -Command "& {Add-Type -AssemblyName System.IO.Compression.FileSystem; try { Invoke-WebRequest -Uri 'https://es.wordpress.org/latest-es_ES.zip' -OutFile 'wordpress.zip' -UseBasicParsing; Expand-Archive -Path 'wordpress.zip' -DestinationPath 'temp' -Force; Copy-Item -Path 'temp\wordpress\*' -Destination '%WWW_PATH%' -Recurse -Force; Remove-Item -Path 'wordpress.zip', 'temp' -Recurse -Force; Write-Host 'WordPress descargado correctamente' } catch { Write-Host 'Error al descargar WordPress. Descárgalo manualmente desde wordpress.org' } }"
        echo ✓ WordPress descargado e instalado
    )
)

echo.
echo ========================================
echo      INSTALACION WAMP COMPLETADA
echo ========================================
echo.
echo ✓ Configuración para WAMPServer aplicada
echo ✓ Base de datos configurada
echo ✓ Archivos copiados a www
echo ✓ Permisos configurados
echo.
echo Información de la instalación:
echo - WAMPServer: %WAMP_PATH%
echo - Proyecto: %WWW_PATH%
echo - Base de datos: automatiza_tech_local
echo - Usuario MySQL: root
echo - Contraseña MySQL: (la configurada en WAMP)
echo.
echo Próximos pasos:
echo.
echo 1. Verifica que WAMPServer esté en verde (todos los servicios activos)
echo.
echo 2. Abre tu navegador y ve a:
echo    http://localhost/automatiza-tech
echo.
echo 3. Si es la primera instalación de WordPress:
echo    - Selecciona idioma: Español
echo    - Configura la base de datos:
echo      * Nombre: automatiza_tech_local
echo      * Usuario: root
echo      * Contraseña: (la de tu WAMP, puede estar vacía)
echo      * Servidor: localhost
echo.
echo 4. Después de instalar WordPress, ejecuta la configuración del tema:
echo    http://localhost/automatiza-tech/install-automatiza-tech.php
echo.
echo 5. Acceso a herramientas:
echo    - phpMyAdmin: http://localhost/phpmyadmin
echo    - WAMPServer: Icono en bandeja del sistema
echo.
echo ¿Deseas abrir el sitio en el navegador ahora? (s/n)
set /p open_browser=
if /i "%open_browser%"=="s" (
    start http://localhost/automatiza-tech
    start http://localhost/phpmyadmin
)

echo.
echo ¡Desarrollo local con WAMP listo! 🚀
echo.
echo Tips para WAMPServer:
echo - Clic izquierdo en icono WAMP: Menú rápido
echo - Clic derecho en icono WAMP: Menú completo
echo - Verde = Todo OK, Naranja = Parcial, Rojo = Error
echo.
pause