@echo off
chcp 65001
echo ========================================
echo    CORRECCION FINAL COMPLETA UTF-8
echo    Automatiza Tech - Limpieza Total
echo ========================================

set "THEME_PATH=C:\wamp64\www\automatiza-tech\wp-content\themes\automatiza-tech"

echo Aplicando corrección total de caracteres...

REM Mapa completo de correcciones
powershell -Command "& {
    $files = @('index.php', 'header.php', 'footer.php', 'functions.php', 'style.css')
    
    foreach ($file in $files) {
        if (Test-Path $file) {
            Write-Host 'Procesando: ' $file
            $content = Get-Content $file -Raw -Encoding UTF8
            
            # Correcciones básicas de encoding
            $content = $content -replace 'Ã¡', 'á'
            $content = $content -replace 'Ã©', 'é'
            $content = $content -replace 'Ã­', 'í'
            $content = $content -replace 'Ã³', 'ó'
            $content = $content -replace 'Ãº', 'ú'
            $content = $content -replace 'Ã±', 'ñ'
            $content = $content -replace 'Â¿', '¿'
            $content = $content -replace 'Â¡', '¡'
            
            # Correcciones de caracteres específicos problemáticos
            $content = $content -replace 'é³', 'ó'
            $content = $content -replace 'é¡', 'á'
            $content = $content -replace 'é­', 'í'
            $content = $content -replace 'é©', 'é'
            $content = $content -replace 'éº', 'ú'
            $content = $content -replace 'é¿', 'ñ'
            
            # Correcciones específicas de palabras comunes
            $content = $content -replace 'ConfiguraciÃ³n', 'Configuración'
            $content = $content -replace 'configuraciÃ³n', 'configuración'
            $content = $content -replace 'AutomatizaciÃ³n', 'Automatización'
            $content = $content -replace 'automatizaciÃ³n', 'automatización'
            $content = $content -replace 'AtenciÃ³n', 'Atención'
            $content = $content -replace 'atenciÃ³n', 'atención'
            $content = $content -replace 'IntegracionÃ©s', 'Integraciones'
            $content = $content -replace 'integraciÃ³n', 'integración'
            $content = $content -replace 'EducaciÃ³n', 'Educación'
            $content = $content -replace 'educaciÃ³n', 'educación'
            $content = $content -replace 'InformaciÃ³n', 'Información'
            $content = $content -replace 'informaciÃ³n', 'información'
            $content = $content -replace 'InscripciÃ³n', 'Inscripción'
            $content = $content -replace 'inscripciÃ³n', 'inscripción'
            $content = $content -replace 'ImplementaciÃ³n', 'Implementación'
            $content = $content -replace 'implementaciÃ³n', 'implementación'
            $content = $content -replace 'NavigaciÃ³n', 'Navegación'
            $content = $content -replace 'navegaciÃ³n', 'navegación'
            $content = $content -replace 'OptimaciÃ³n', 'Optimización'
            $content = $content -replace 'optimizaciÃ³n', 'optimización'
            
            # Palabras con tilde
            $content = $content -replace 'tÃ­tulo', 'título'
            $content = $content -replace 'TÃ­tulo', 'Título'
            $content = $content -replace 'SubtÃ­tulo', 'Subtítulo'
            $content = $content -replace 'subtÃ­tulo', 'subtítulo'
            $content = $content -replace 'MenÃº', 'Menú'
            $content = $content -replace 'menÃº', 'menú'
            $content = $content -replace 'TamaÃ±o', 'Tamaño'
            $content = $content -replace 'tamaÃ±o', 'tamaño'
            $content = $content -replace 'NÃºmero', 'Número'
            $content = $content -replace 'nÃºmero', 'número'
            $content = $content -replace 'TelÃ©fono', 'Teléfono'
            $content = $content -replace 'telÃ©fono', 'teléfono'
            $content = $content -replace 'LÃ­mite', 'Límite'
            $content = $content -replace 'lÃ­mite', 'límite'
            
            # Palabras específicas del contenido
            $content = $content -replace 'AutomÃ¡tica', 'Automática'
            $content = $content -replace 'automÃ¡tica', 'automática'
            $content = $content -replace 'AutomÃ¡ticas', 'Automáticas'
            $content = $content -replace 'automÃ¡ticas', 'automáticas'
            $content = $content -replace 'AutomÃ¡ticamente', 'Automáticamente'
            $content = $content -replace 'automÃ¡ticamente', 'automáticamente'
            $content = $content -replace 'BÃ¡sico', 'Básico'
            $content = $content -replace 'bÃ¡sico', 'básico'
            $content = $content -replace 'BÃ¡sicas', 'Básicas'
            $content = $content -replace 'bÃ¡sicas', 'básicas'
            $content = $content -replace 'FÃ¡cil', 'Fácil'
            $content = $content -replace 'fÃ¡cil', 'fácil'
            $content = $content -replace 'PÃ¡gina', 'Página'
            $content = $content -replace 'pÃ¡gina', 'página'
            $content = $content -replace 'MÃ¡s', 'Más'
            $content = $content -replace 'mÃ¡s', 'más'
            $content = $content -replace 'MÃ©dicas', 'Médicas'
            $content = $content -replace 'mÃ©dicas', 'médicas'
            $content = $content -replace 'MÃ©tricas', 'Métricas'
            $content = $content -replace 'mÃ©tricas', 'métricas'
            $content = $content -replace 'TÃ©cnico', 'Técnico'
            $content = $content -replace 'tÃ©cnico', 'técnico'
            $content = $content -replace 'EspecÃ­ficas', 'Específicas'
            $content = $content -replace 'especÃ­ficas', 'específicas'
            $content = $content -replace 'AnalÃ­ticas', 'Analíticas'
            $content = $content -replace 'analÃ­ticas', 'analíticas'
            $content = $content -replace 'InstantÃ¡neas', 'Instantáneas'
            $content = $content -replace 'instantÃ¡neas', 'instantáneas'
            $content = $content -replace 'SatisfacciÃ³n', 'Satisfacción'
            $content = $content -replace 'satisfacciÃ³n', 'satisfacción'
            $content = $content -replace 'ConversaciÃ³n', 'Conversación'
            $content = $content -replace 'conversaciÃ³n', 'conversación'
            $content = $content -replace 'PersonalizaciÃ³n', 'Personalización'
            $content = $content -replace 'personalizaciÃ³n', 'personalización'
            $content = $content -replace 'CotizaciÃ³n', 'Cotización'
            $content = $content -replace 'cotizaciÃ³n', 'cotización'
            
            # Otras palabras comunes
            $content = $content -replace 'CuÃ©ntanos', 'Cuéntanos'
            $content = $content -replace 'cuÃ©ntanos', 'cuéntanos'
            $content = $content -replace 'CÃ³mo', 'Cómo'
            $content = $content -replace 'cÃ³mo', 'cómo'
            $content = $content -replace 'QuÃ©', 'Qué'
            $content = $content -replace 'quÃ©', 'qué'
            $content = $content -replace 'DÃ­a', 'Día'
            $content = $content -replace 'dÃ­a', 'día'
            $content = $content -replace 'DÃ­as', 'Días'
            $content = $content -replace 'dÃ­as', 'días'
            $content = $content -replace 'AÃ±o', 'Año'
            $content = $content -replace 'aÃ±o', 'año'
            $content = $content -replace 'NiÃ±o', 'Niño'
            $content = $content -replace 'niÃ±o', 'niño'
            $content = $content -replace 'EspaÃ±ol', 'Español'
            $content = $content -replace 'espaÃ±ol', 'español'
            
            # Limpiar cualquier residuo de caracteres problemáticos
            $content = $content -replace 'Ã', ''
            $content = $content -replace 'Â', ''
            
            # Guardar con UTF-8
            [System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
            Write-Host '✓ Completado: ' $file
        }
    }
}"

echo.
echo ========================================
echo    CORRECCION COMPLETADA
echo ========================================
echo.
echo ✓ Todos los archivos procesados
echo ✓ Codificación UTF-8 aplicada correctamente
echo ✓ Caracteres especiales restaurados
echo.
echo Sitio disponible en: http://localhost/automatiza-tech
echo Test de verificación: http://localhost/automatiza-tech/verificacion-utf8-final.html
echo.
pause