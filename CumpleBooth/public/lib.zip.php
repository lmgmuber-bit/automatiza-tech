<?php
/**
 * lib.zip.php — un ZIP que se escribe mientras se descarga (2026-10-21, «descargar todas las fotos» del admin).
 *
 * Por qué no ZipArchive: arma el archivo entero en el disco del servidor antes de mandar un byte. Con 300 fotos
 * de invitados son 1 GB de temporal en un hosting compartido y un minuto sin respuesta, que el borde corta con
 * un 504. Acá cada foto se lee y se manda de una, sin comprimir (un JPEG no se achica) y sin temporales.
 *
 * Por qué no «data descriptors»: con ellos se podría mandar sin leer cada archivo dos veces, pero un ZIP con
 * entradas sin comprimir y tamaños en cero en la cabecera local lo rechazan algunos lectores. Se calcula el CRC
 * de cada foto justo antes de mandarla: dos lecturas del disco, ninguna sorpresa al abrirlo.
 *
 * Tope: el formato clásico cuenta con 32 bits (4 GiB en total y 65.535 archivos). Para pasarse hay que ZIP64;
 * una fiesta tiene tope de 5 GB y las fotos son una parte, así que se valida y se avisa en vez de partir un ZIP
 * que saldría roto.
 */

const CB_ZIP_TOPE_BYTES = 0xFFFFFFFF;
const CB_ZIP_TOPE_ARCHIVOS = 0xFFFF;

/**
 * Valida las entradas y calcula cuánto va a pesar el ZIP (para el Content-Length).
 *
 * @param list<array{nombre: string, ruta: string, fecha?: int}> $entradas
 * @return array{entradas: list<array{nombre: string, ruta: string, fecha: int, tamano: int}>, total: int}
 */
function cb_zip_preparar(array $entradas): array
{
    if (count($entradas) > CB_ZIP_TOPE_ARCHIVOS) {
        throw new RuntimeException('Son demasiados archivos para un solo ZIP.');
    }
    $listas = [];
    $usados = [];
    $total = 22; // fin del directorio central
    foreach ($entradas as $e) {
        $nombre = cb_zip_nombre_unico(cb_zip_nombre_seguro((string) ($e['nombre'] ?? '')), $usados);
        $ruta = (string) ($e['ruta'] ?? '');
        $tamano = is_file($ruta) ? filesize($ruta) : false;
        if ($tamano === false) {
            continue; // una foto que ya no está en disco no puede romper la descarga de las demás
        }
        $listas[] = ['nombre' => $nombre, 'ruta' => $ruta, 'fecha' => (int) ($e['fecha'] ?? time()), 'tamano' => (int) $tamano];
        $total += 30 + strlen($nombre) + (int) $tamano + 46 + strlen($nombre);
    }
    if ($total > CB_ZIP_TOPE_BYTES) {
        throw new RuntimeException('Las fotos pesan más de 4 GB: no caben en un ZIP. Descárgalas por partes.');
    }
    return ['entradas' => $listas, 'total' => $total];
}

/** Escribe el ZIP en `$salida` (un recurso de stream: php://output en el admin, un archivo en las pruebas). */
function cb_zip_emitir(array $preparado, $salida): void
{
    $central = '';
    $offset = 0;
    foreach ($preparado['entradas'] as $e) {
        $crc = cb_zip_crc32($e['ruta']);
        [$hora, $dia] = cb_zip_fecha_dos($e['fecha']);
        $nombre = $e['nombre'];
        // bit 11: nombre en UTF-8; método 0: sin comprimir.
        $comun = pack('vvvvvVVVv', 20, 0x0800, 0, $hora, $dia, $crc, $e['tamano'], $e['tamano'], strlen($nombre));
        fwrite($salida, pack('V', 0x04034b50) . $comun . pack('v', 0) . $nombre);
        $f = fopen($e['ruta'], 'rb');
        if ($f === false) {
            throw new RuntimeException('No se pudo leer ' . $nombre);
        }
        $copiado = stream_copy_to_stream($f, $salida);
        fclose($f);
        if ($copiado !== $e['tamano']) {
            throw new RuntimeException('El archivo cambió mientras se descargaba: ' . $nombre);
        }
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $hora, $dia, $crc, $e['tamano'], $e['tamano'],
            strlen($nombre), 0, 0, 0, 0, 0, $offset) . $nombre;
        $offset += 30 + strlen($nombre) + $e['tamano'];
    }
    $n = count($preparado['entradas']);
    fwrite($salida, $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($central), $offset, 0));
}

function cb_zip_crc32(string $ruta): int
{
    $hex = hash_file('crc32b', $ruta);
    if ($hex === false) {
        throw new RuntimeException('No se pudo leer ' . basename($ruta));
    }
    return (int) hexdec($hex);
}

/** Hora y fecha en el formato de MS-DOS que usa el ZIP (resolución de 2 s, desde 1980). */
function cb_zip_fecha_dos(int $ts): array
{
    $d = getdate(max($ts, 315532800));
    return [($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2),
        (($d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday']];
}

/** Sin rutas que suban carpetas, sin barras invertidas ni caracteres de control. */
function cb_zip_nombre_seguro(string $nombre): string
{
    $partes = [];
    foreach (explode('/', str_replace('\\', '/', $nombre)) as $p) {
        $p = trim((string) preg_replace('/[\x00-\x1F\x7F:*?"<>|]/u', '', $p));
        if ($p !== '' && $p !== '.' && $p !== '..') {
            $partes[] = $p;
        }
    }
    return $partes ? implode('/', $partes) : 'archivo';
}

function cb_zip_nombre_unico(string $nombre, array &$usados): string
{
    $candidato = $nombre;
    $punto = strrpos($nombre, '.');
    for ($i = 2; isset($usados[strtolower($candidato)]); $i++) {
        $candidato = $punto === false ? "$nombre-$i" : substr($nombre, 0, $punto) . "-$i" . substr($nombre, $punto);
    }
    $usados[strtolower($candidato)] = true;
    return $candidato;
}

/** Un trozo de nombre de archivo legible: «María José» → «maria-jose». */
function cb_zip_slug(string $texto, string $siVacio = 'fiesta'): string
{
    $t = strtolower(strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n']));
    $t = trim((string) preg_replace('/[^a-z0-9]+/', '-', $t), '-');
    return $t !== '' ? substr($t, 0, 40) : $siVacio;
}
