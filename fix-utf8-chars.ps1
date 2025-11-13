# Script para corregir caracteres UTF-8 dañados
$file = "C:\wamp64\www\automatiza-tech\wp-content\themes\automatiza-tech\lib\invoice-pdf-fpdf.php"
$content = Get-Content $file -Raw -Encoding UTF8

# Reemplazos de caracteres dañados
$replacements = @{
    'Ã³' = 'ó'
    'Ã­' = 'í'
    'Ã©' = 'é'
    'Ã¡' = 'á'
    'Ãº' = 'ú'
    'Ã±' = 'ñ'
    'Â©' = '©'
    'Âº' = 'º'
    'Â°' = '°'
    'Ã' = 'Ó'
    'Ã' = 'Í'
    'Ã‰' = 'É'
    'Ã' = 'Á'
    'Ãš' = 'Ú'
    'Ã'' = 'Ñ'
}

foreach ($key in $replacements.Keys) {
    $content = $content -replace [regex]::Escape($key), $replacements[$key]
}

Set-Content $file $content -Encoding UTF8 -NoNewline
Write-Host "Caracteres corregidos exitosamente" -ForegroundColor Green
