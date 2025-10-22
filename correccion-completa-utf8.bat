@echo off
chcp 65001
echo ========================================
echo    CORRECCION COMPLETA DE CARACTERES
echo    Automatiza Tech - UTF-8 Fix
echo ========================================
echo.

set "THEME_PATH=C:\wamp64\www\automatiza-tech\wp-content\themes\automatiza-tech"

echo Aplicando correcciones de caracteres especiales...
echo.

REM Correcciones para index.php
powershell -Command "& {$content = Get-Content '%THEME_PATH%\index.php' -Raw -Encoding UTF8; $content = $content -replace 'é³', 'ó'; $content = $content -replace 'é¡', 'á'; $content = $content -replace 'é­', 'í'; $content = $content -replace 'é©', 'é'; $content = $content -replace 'éº', 'ú'; $content = $content -replace 'é¿', 'ñ'; $content = $content -replace 'qué©', 'qué'; $content = $content -replace 'Conté¡ctanos', 'Contáctanos'; $content = $content -replace 'mé¡s', 'más'; $content = $content -replace 'atencié³n', 'atención'; $content = $content -replace 'déa', 'día'; $content = $content -replace 'Fé¡cil', 'Fácil'; $content = $content -replace 'Integracié³n', 'Integración'; $content = $content -replace 'instanté¡neas', 'instantáneas'; $content = $content -replace 'satisfaccié³n', 'satisfacción'; $content = $content -replace 'Analé­ticas', 'Analíticas'; $content = $content -replace 'Mé©tricas', 'Métricas'; $content = $content -replace 'automé¡ticamente', 'automáticamente'; $content = $content -replace 'pé¡gina', 'página'; $content = $content -replace 'especé­ficas', 'específicas'; $content = $content -replace 'mé©dicas', 'médicas'; $content = $content -replace 'bé¡sicas', 'básicas'; $content = $content -replace 'Educacié³n', 'Educación'; $content = $content -replace 'informacié³n', 'información'; $content = $content -replace 'inscripcié³n', 'inscripción'; $content = $content -replace 'automé¡ticamente', 'automáticamente'; $content = $content -replace 'menéºs', 'menús'; $content = $content -replace 'propiedades', 'propiedades'; $content = $content -replace 'té©cnico', 'técnico'; $content = $content -replace 'Bé¡sico', 'Básico'; $content = $content -replace 'automé¡ticas', 'automáticas'; $content = $content -replace 'bé¡sicas', 'básicas'; $content = $content -replace 'Analé­ticas', 'Analíticas'; $content = $content -replace 'Mé¡s Popular', 'Más Popular'; $content = $content -replace 'avanzadas', 'avanzadas'; $content = $content -replace 'Implementacié³n', 'Implementación'; $content = $content -replace 'contactaré¡', 'contactará'; [System.IO.File]::WriteAllText('%THEME_PATH%\index.php', $content, [System.Text.Encoding]::UTF8)}"

echo ✓ index.php corregido

REM Correcciones para header.php
powershell -Command "& {$content = Get-Content '%THEME_PATH%\header.php' -Raw -Encoding UTF8; $content = $content -replace 'é³', 'ó'; $content = $content -replace 'é¡', 'á'; $content = $content -replace 'é­', 'í'; $content = $content -replace 'é©', 'é'; $content = $content -replace 'éº', 'ú'; $content = $content -replace 'é¿', 'ñ'; $content = $content -replace 'automé¡tica', 'automática'; $content = $content -replace 'inteligente', 'inteligente'; $content = $content -replace 'mé¡s', 'más'; $content = $content -replace 'optimizacié³n', 'optimización'; $content = $content -replace 'mé³vil', 'móvil'; $content = $content -replace 'configuracié³n', 'configuración'; $content = $content -replace 'ré¡pida', 'rápida'; [System.IO.File]::WriteAllText('%THEME_PATH%\header.php', $content, [System.Text.Encoding]::UTF8)}"

echo ✓ header.php corregido

REM Correcciones para footer.php si existe
if exist "%THEME_PATH%\footer.php" (
    powershell -Command "& {$content = Get-Content '%THEME_PATH%\footer.php' -Raw -Encoding UTF8; $content = $content -replace 'é³', 'ó'; $content = $content -replace 'é¡', 'á'; $content = $content -replace 'é­', 'í'; $content = $content -replace 'é©', 'é'; $content = $content -replace 'éº', 'ú'; $content = $content -replace 'é¿', 'ñ'; [System.IO.File]::WriteAllText('%THEME_PATH%\footer.php', $content, [System.Text.Encoding]::UTF8)}"
    echo ✓ footer.php corregido
)

REM Correcciones para functions.php
if exist "%THEME_PATH%\functions.php" (
    powershell -Command "& {$content = Get-Content '%THEME_PATH%\functions.php' -Raw -Encoding UTF8; $content = $content -replace 'é³', 'ó'; $content = $content -replace 'é¡', 'á'; $content = $content -replace 'é­', 'í'; $content = $content -replace 'é©', 'é'; $content = $content -replace 'éº', 'ú'; $content = $content -replace 'é¿', 'ñ'; [System.IO.File]::WriteAllText('%THEME_PATH%\functions.php', $content, [System.Text.Encoding]::UTF8)}"
    echo ✓ functions.php corregido
)

REM Correcciones para style.css
if exist "%THEME_PATH%\style.css" (
    powershell -Command "& {$content = Get-Content '%THEME_PATH%\style.css' -Raw -Encoding UTF8; $content = $content -replace 'é³', 'ó'; $content = $content -replace 'é¡', 'á'; $content = $content -replace 'é­', 'í'; $content = $content -replace 'é©', 'é'; $content = $content -replace 'éº', 'ú'; $content = $content -replace 'é¿', 'ñ'; [System.IO.File]::WriteAllText('%THEME_PATH%\style.css', $content, [System.Text.Encoding]::UTF8)}"
    echo ✓ style.css corregido
)

echo.
echo ========================================
echo    CORRECCION COMPLETADA
echo ========================================
echo.
echo Caracteres corregidos:
echo é³ → ó
echo é¡ → á  
echo é­ → í
echo é© → é
echo éº → ú
echo é¿ → ñ
echo qué© → qué
echo Y muchos otros términos específicos...
echo.
echo ✓ Todos los archivos han sido procesados
echo ✓ Codificación UTF-8 aplicada
echo.
echo Para verificar: http://localhost/automatiza-tech
echo.
pause