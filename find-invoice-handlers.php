<?php
// Buscar archivos que contengan download_invoice o validate_invoice
$dir = "C:\\wamp64\\www\\automatiza-tech\\wp-content\\themes\\automatiza-tech";

function searchInFiles($dir, $patterns) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            
            foreach ($patterns as $pattern) {
                if (stripos($content, $pattern) !== false) {
                    echo "Encontrado '{$pattern}' en: {$file->getPathname()}\n";
                    
                    // Mostrar las líneas que contienen el patrón
                    $lines = explode("\n", $content);
                    foreach ($lines as $num => $line) {
                        if (stripos($line, $pattern) !== false) {
                            echo "  Línea " . ($num + 1) . ": " . trim($line) . "\n";
                        }
                    }
                    echo "\n";
                }
            }
        }
    }
}

$patterns = array('download_invoice', 'validate_invoice', 'wp_ajax');

searchInFiles($dir, $patterns);
