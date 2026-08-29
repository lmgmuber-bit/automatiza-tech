<?php
/**
 * WOFF -> TTF, para poder dibujar con la fuente real desde PHP.
 *
 * El kiosco compone la foto en canvas y ahí "Baloo 2" llega por CSS. Un guion
 * de PHP no tiene CSS: GD dibuja con FreeType, y FreeType solo lee sfnt
 * (TTF/OTF). `@fontsource/baloo-2` distribuye .woff y .woff2, así que sin esta
 * conversión el compositor de demo cae a la fuente de mapa de bits de GD, que
 * además no tiene tildes.
 *
 * Se convierte .woff y no .woff2 a propósito: WOFF es el mismo sfnt con cada
 * tabla comprimida en zlib, así que reconstruirlo es desempaquetar y volver a
 * armar el directorio. WOFF2 usa Brotli y ADEMÁS transforma las tablas `glyf`
 * y `loca` a un formato propio; deshacer eso es un decodificador entero, no
 * una descompresión. El paquete trae las dos, así que se usa la fácil.
 *
 * No se guarda un TTF en el repo: se deriva del .woff que ya es dependencia
 * declarada en package.json. Así la fuente del demo no puede quedar
 * desincronizada de la del kiosco, y no se versiona un binario de fuente.
 */

/**
 * Reconstruye un sfnt (TTF/OTF) a partir de un WOFF 1.0.
 *
 * @throws RuntimeException si el archivo no es un WOFF legible.
 */
function cc_woff_a_sfnt(string $woffPath): string
{
    $woff = @file_get_contents($woffPath);
    if ($woff === false || strlen($woff) < 44) {
        throw new RuntimeException("No se pudo leer el WOFF: $woffPath");
    }
    if (substr($woff, 0, 4) !== 'wOFF') {
        throw new RuntimeException("No es un WOFF 1.0 (firma "
            . bin2hex(substr($woff, 0, 4)) . "): $woffPath");
    }

    $cab = unpack('Nflavor/Nlength/nnumTables/nreserved/NtotalSfntSize', substr($woff, 4, 16));
    $numTables = $cab['numTables'];
    if ($numTables < 1 || 44 + $numTables * 20 > strlen($woff)) {
        throw new RuntimeException("Directorio WOFF inconsistente en $woffPath");
    }

    // Directorio: 20 bytes por tabla.
    $tablas = [];
    for ($i = 0; $i < $numTables; $i++) {
        $e = substr($woff, 44 + $i * 20, 20);
        $tag = substr($e, 0, 4);
        $d = unpack('Noffset/NcompLength/NorigLength/Nchecksum', substr($e, 4, 16));

        $bruto = substr($woff, $d['offset'], $d['compLength']);
        if (strlen($bruto) !== $d['compLength']) {
            throw new RuntimeException("Tabla '$tag' truncada en $woffPath");
        }
        // compLength == origLength significa almacenada sin comprimir.
        $datos = $d['compLength'] === $d['origLength'] ? $bruto : @zlib_decode($bruto);
        if ($datos === false || strlen($datos) !== $d['origLength']) {
            throw new RuntimeException("Tabla '$tag' no descomprime al largo declarado en $woffPath");
        }
        $tablas[] = ['tag' => $tag, 'checksum' => $d['checksum'], 'datos' => $datos];
    }

    // El directorio sfnt va ordenado por tag ascendente.
    usort($tablas, static fn ($a, $b) => strcmp($a['tag'], $b['tag']));

    // Cabecera de offsets: los tres campos de búsqueda binaria se derivan de
    // numTables. FreeType los tolera mal calculados, pero se escriben bien.
    $exp = (int) floor(log($numTables, 2));
    $searchRange = (1 << $exp) * 16;
    $sfnt = pack('Nnnnn', $cab['flavor'], $numTables, $searchRange, $exp, $numTables * 16 - $searchRange);

    // Cada tabla arranca alineada a 4 bytes.
    $offset = 12 + $numTables * 16;
    $cuerpo = '';
    foreach ($tablas as $t) {
        $largo = strlen($t['datos']);
        $sfnt .= $t['tag'] . pack('NNN', $t['checksum'], $offset, $largo);
        $relleno = (4 - ($largo % 4)) % 4;
        $cuerpo .= $t['datos'] . str_repeat("\0", $relleno);
        $offset += $largo + $relleno;
    }

    return $sfnt . $cuerpo;
}

/**
 * Devuelve la ruta a un TTF de Baloo 2 listo para `imagettftext()`,
 * derivándolo del .woff de node_modules la primera vez y cacheándolo.
 *
 * El caché vive en storage/ (ignorado por git) y se rehace solo si el .woff
 * de origen es más nuevo, para que un `npm update` de la fuente no deje al
 * demo dibujando con una versión vieja.
 *
 * @param string $peso '400'..'800' tal como los nombra @fontsource.
 * @return string|null ruta al TTF, o null si la fuente no está instalada.
 */
function cc_baloo_ttf(string $peso = '800'): ?string
{
    $raiz = dirname(__DIR__);
    $woff = $raiz . "/node_modules/@fontsource/baloo-2/files/baloo-2-latin-$peso-normal.woff";
    if (!is_file($woff)) {
        return null;
    }
    $cache = $raiz . '/storage/fuentes';
    $ttf = "$cache/baloo-2-latin-$peso-normal.ttf";
    if (is_file($ttf) && filemtime($ttf) >= filemtime($woff)) {
        return $ttf;
    }
    if (!is_dir($cache) && !@mkdir($cache, 0770, true) && !is_dir($cache)) {
        throw new RuntimeException("No se pudo crear $cache");
    }
    file_put_contents($ttf, cc_woff_a_sfnt($woff));
    return $ttf;
}

/**
 * Métricas verticales del sfnt, para poder replicar el `textBaseline` de canvas.
 *
 * `imagettftext()` posiciona por la línea base alfabética; el kiosco dibuja con
 * `textBaseline = 'middle'`, que el navegador sitúa (ascender + descender) / 2
 * por encima de esa línea base. Sin esta corrección el texto del demo queda
 * ~28% del cuerpo más arriba que en el kiosco.
 *
 * @return array{upem:int, ascender:int, descender:int, medioEm:float}
 */
function cc_sfnt_metricas(string $ttfPath): array
{
    $d = file_get_contents($ttfPath);
    $num = unpack('n', substr($d, 4, 2))[1];
    $tablas = [];
    for ($i = 0; $i < $num; $i++) {
        $e = substr($d, 12 + $i * 16, 16);
        $tablas[substr($e, 0, 4)] = unpack('Noff/Nlen', substr($e, 8, 8));
    }
    foreach (['head', 'hhea'] as $req) {
        if (!isset($tablas[$req])) {
            throw new RuntimeException("Al TTF le falta la tabla '$req': $ttfPath");
        }
    }
    $s16 = static fn (string $b): int => unpack('n', $b)[1] > 32767 ? unpack('n', $b)[1] - 65536 : unpack('n', $b)[1];

    $upem = unpack('n', substr($d, $tablas['head']['off'] + 18, 2))[1];
    $asc = $s16(substr($d, $tablas['hhea']['off'] + 4, 2));
    $desc = $s16(substr($d, $tablas['hhea']['off'] + 6, 2));

    return [
        'upem' => $upem,
        'ascender' => $asc,
        'descender' => $desc,
        'medioEm' => ($asc + $desc) / 2 / $upem,
    ];
}
