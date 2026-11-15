<?php
// Utilidades comunes de las pruebas de seguridad-errores. Todo es local: no sale a la red.
$GLOBALS['fallos'] = 0;
$GLOBALS['pasadas'] = 0;

function comprobar(bool $ok, string $nombre, string $detalle = ''): void {
    if ($ok) { $GLOBALS['pasadas']++; echo "  ok   $nombre\n"; return; }
    $GLOBALS['fallos']++;
    echo "  FALLA $nombre" . ($detalle !== '' ? " -> $detalle" : '') . "\n";
}

function raiz(): string { return dirname(__DIR__, 2); }

function cierre(): void {
    echo "\n" . $GLOBALS['pasadas'] . " pasadas, " . $GLOBALS['fallos'] . " fallas\n";
    exit($GLOBALS['fallos'] > 0 ? 1 : 0);
}

function carpeta_temporal(): string {
    $d = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'at-seg-' . bin2hex(random_bytes(4));
    mkdir($d, 0700, true);
    return $d;
}

function borrar_carpeta(string $d): void {
    foreach (glob($d . '/*') ?: [] as $f) { is_file($f) ? unlink($f) : null; }
    @rmdir($d);
}

/**
 * Copia un endpoint a una carpeta temporal junto a dobles de sus dependencias y lo ejecuta
 * en un proceso PHP aparte. El cuerpo de la peticion se entrega por archivo (en CLI php://input
 * llega vacio).
 *
 * @param array<string,string> $dobles nombre de archivo => codigo PHP del doble
 * @return array{salida:string,codigo:int}
 */
function ejecutar_endpoint(string $rutaEndpoint, array $dobles, string $cuerpo, array $env = []): array {
    $d = carpeta_temporal();
    $cuerpoArchivo = $d . '/cuerpo.json';
    file_put_contents($cuerpoArchivo, $cuerpo);
    $codigo = str_replace("'php://input'", var_export($cuerpoArchivo, true), file_get_contents(raiz() . '/' . $rutaEndpoint));
    file_put_contents($d . '/endpoint.php', $codigo);
    foreach ($dobles as $nombre => $php) { file_put_contents($d . '/' . $nombre, $php); }

    $entorno = array_merge(getenv() ?: [], $env);
    $p = proc_open([PHP_BINARY, '-d', 'display_errors=0', $d . '/endpoint.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $d, $entorno);
    $salida = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $rc = proc_close($p);
    borrar_carpeta($d);
    return ['salida' => $salida, 'codigo' => $rc];
}
