<?php
/**
 * La fuente real del compositor de demo.
 *
 * El agradecimiento que sale impreso en la foto de cabina se dibujaba con la
 * fuente de mapa de bits de GD: tipografía de terminal y sin tildes
 * ("Emilia Nunez"). Ahora se dibuja con Baloo 2, la misma del kiosco, sacada
 * del .woff de `@fontsource/baloo-2` y convertida a TTF porque FreeType no lee
 * WOFF.
 *
 * Este test existe por una degradación silenciosa concreta: una conversión mal
 * hecha puede producir un archivo que FreeType ACEPTA y que igual dibuja
 * cuadraditos .notdef en vez de letras. `imagettftext()` no devuelve error en
 * ese caso. Así que no basta con validar la firma del archivo: hay que
 * rasterizar y comparar píxeles.
 *
 * Corre suelto:  php tests/backend/fuente-baloo.php
 */
if (PHP_SAPI !== 'cli') { exit(2); }
require dirname(__DIR__, 2) . '/scripts/lib-woff.php';

$checks = 0;
$check = static function (bool $ok, string $msg) use (&$checks): void {
    $checks++;
    if (!$ok) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); }
};

if (!function_exists('imagettftext')) {
    echo "OMITIDO: este PHP no trae FreeType.\n";
    exit(0);
}
$ttf = cc_baloo_ttf('800');
if ($ttf === null) {
    echo "OMITIDO: falta node_modules/@fontsource/baloo-2 (corre `npm install`).\n";
    exit(0);
}

// ── El archivo es un sfnt completo ───────────────────────────────────────────
$bytes = file_get_contents($ttf);
$check(substr($bytes, 0, 4) === "\x00\x01\x00\x00", 'el TTF no empieza con la firma TrueType');

$num = unpack('n', substr($bytes, 4, 2))[1];
$tags = [];
for ($i = 0; $i < $num; $i++) { $tags[] = substr($bytes, 12 + $i * 16, 4); }
foreach (['cmap', 'glyf', 'head', 'hhea', 'hmtx', 'loca', 'maxp'] as $t) {
    $check(in_array($t, $tags, true), "al TTF le falta la tabla '$t'");
}

$m = cc_sfnt_metricas($ttf);
$check($m['upem'] > 0 && $m['ascender'] > 0 && $m['descender'] < 0, 'métricas verticales absurdas');

// ── Y de verdad dibuja letras, no cuadraditos ───────────────────────────────
/** Rasteriza un texto y devuelve la huella de sus píxeles con tinta. */
$huella = static function (string $texto) use ($ttf): string {
    $im = imagecreatetruecolor(260, 120);
    imagefilledrectangle($im, 0, 0, 259, 119, imagecolorallocate($im, 255, 255, 255));
    imagettftext($im, 64, 0, 20, 90, imagecolorallocate($im, 0, 0, 0), $ttf, $texto);
    $bits = '';
    for ($y = 0; $y < 120; $y += 2) {
        for ($x = 0; $x < 260; $x += 2) {
            $bits .= (imagecolorat($im, $x, $y) & 0xFF) < 128 ? '1' : '0';
        }
    }
    imagedestroy($im);
    return $bits;
};
$tinta = static fn (string $h): int => substr_count($h, '1');

// U+E000 es de uso privado: ninguna fuente de texto lo trae, así que su dibujo
// ES el .notdef. Cualquier letra que se le parezca no se está dibujando.
$notdef = $huella("\u{E000}");
$check($tinta($notdef) > 0, 'el glifo .notdef sale vacío: la comparación no probaría nada');

// Control positivo: el .woff que usamos es el subset `latin`, así que estos
// caracteres NO están y tienen que salir como .notdef. Si algún día dejaran de
// coincidir, la comparación de abajo se habría vuelto incapaz de detectar un
// glifo faltante y estaría aprobando cualquier cosa.
foreach (["\u{0915}", "\u{6F22}"] as $fuera) {
    $check($huella($fuera) === $notdef, 'un caracter fuera del subset latin no se dibuja como .notdef: la comparacion ya no sirve');
}

foreach (['ñ', 'á', 'é', 'í', 'ó', 'ú', 'ü', 'Á', 'Ñ', '¡', '¿'] as $letra) {
    $h = $huella($letra);
    $check($tinta($h) > 0, "'$letra' no dibuja nada");
    $check($h !== $notdef, "'$letra' se dibuja como .notdef: la fuente no trae ese glifo");
}

// Y las acentuadas tienen que diferenciarse de su versión sin tilde, o estaría
// dibujando la letra base y comiéndose el acento.
foreach ([['á', 'a'], ['ñ', 'n'], ['é', 'e'], ['ü', 'u']] as [$con, $sin]) {
    $check($huella($con) !== $huella($sin), "'$con' se dibuja igual que '$sin'");
}

// El ancho tiene que crecer con el texto: si midiera siempre igual, el
// encogido-hasta-caber del compositor no encogería nunca.
$corto = imagettfbbox(48, 0, $ttf, 'Ana');
$largo = imagettfbbox(48, 0, $ttf, 'Ana Victoria del Carmen');
$check(($largo[2] - $largo[0]) > ($corto[2] - $corto[0]) * 2, 'imagettfbbox no mide el ancho real');

printf("OK fuente Baloo 2 del compositor: %d checks.\n", $checks);
