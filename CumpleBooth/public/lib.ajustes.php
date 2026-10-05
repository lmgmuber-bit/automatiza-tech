<?php
/**
 * Ajustes generales del administrador.
 *
 * Van acá y no en el archivo de configuración del servidor porque son de Luis y los cambia
 * cuando quiere, sin tocar nada del hosting: el correo al que le llega copia de todo lo que
 * sale, el correo al que se manda el enlace para recuperar la contraseña, las cifras del manual
 * y los textos fijos de la Portada de Revista CLICK.
 *
 * Mismo tipo de dato que `marca.json` y `planes.json`: general, editable desde el admin, en
 * `data/`, que no es accesible por web.
 */

function cb_ajustes_ruta(): string
{
    // La variable de entorno existe para que las pruebas escriban en su propio directorio y
    // no en el archivo de verdad del proyecto.
    $propia = trim((string) getenv('CC_AJUSTES_PATH'));
    return $propia !== '' ? $propia : __DIR__ . '/data/ajustes.json';
}

/** Ajustes con sus valores por defecto: vacíos, que significa "apagado". */
/** Claves numéricas del manual: minutos de anticipación y días de plazo para la lista. */
function cb_ajustes_numericos(): array
{
    return ['manual_anticipacion_min' => 240, 'manual_dias_lista' => 60];   // clave => máximo
}

/**
 * Textos fijos de la Portada de Revista CLICK (04-10-2026). Luis los dejó como estaban, pero quiere poder cambiarlos sin
 * tocar código: se editan en Ajustes y el kiosco los recibe con la temática (`theme.revista.textos`). Vacío = el de siempre.
 * clave => [nombre en el admin, máximo de letras, texto de siempre]. Los máximos son los que caben en la portada. Los textos
 * de siempre son los mismos de TEXTOS_DE_SIEMPRE en src/feria/revista.js (lo verifica tests/frontend/revista.test.mjs).
 */
function cb_revista_textos_campos(): array
{
    return [
        'etiqueta' => ['Etiqueta', 14, 'EXCLUSIVA'],
        'bajada' => ['Bajada', 50, 'Así se vivió {evento}'],
        'llamado1' => ['Primer llamado', 36, 'Los looks que todos comentan'],
        'llamado2' => ['Segundo llamado', 36, 'Sus mejores poses'],
        'antetitulo' => ['Sobre el nombre', 24, 'LA ESTRELLA DE HOY'],
        'sinNombre' => ['Si no hay nombre', 14, 'ERES TÚ'],
        'edicion' => ['Edición', 20, 'EDICIÓN ESPECIAL'],
        'numero' => ['Número', 10, 'N.º 1'],
    ];
}

/** Una sola línea y sin caracteres de control: el texto va dibujado en la portada. */
function cb_revista_texto_limpio($valor): string
{
    return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $valor));
}

/** Los textos que usa la portada: el de Ajustes si Luis escribió uno, si no el de siempre. */
function cb_revista_textos(): array
{
    $propios = cb_ajustes()['revista_textos'];
    $textos = [];
    foreach (cb_revista_textos_campos() as $clave => [, , $siempre]) {
        $textos[$clave] = ($propios[$clave] ?? '') !== '' ? $propios[$clave] : $siempre;
    }
    return $textos;
}

function cb_ajustes(): array
{
    $vacio = ['bcc_email' => '', 'recovery_email' => ''];
    foreach (array_keys(cb_ajustes_numericos()) as $clave) { $vacio[$clave] = 0; }
    $vacio['revista_textos'] = array_fill_keys(array_keys(cb_revista_textos_campos()), '');
    $ruta = cb_ajustes_ruta();
    $crudo = is_file($ruta) ? json_decode((string) @file_get_contents($ruta), true) : null;
    if (!is_array($crudo)) {
        return $vacio;
    }
    foreach (['bcc_email', 'recovery_email'] as $clave) {
        $valor = trim((string) ($crudo[$clave] ?? ''));
        // Un correo mal escrito se ignora en vez de romper el envío: si el de copia no vale,
        // el mensaje igual tiene que llegarle al cliente.
        if ($valor !== '' && filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $vacio[$clave] = $valor;
        }
    }
    foreach (cb_ajustes_numericos() as $clave => $max) {
        // Cero significa "no escrito": el manual omite la cifra en vez de inventar una.
        $vacio[$clave] = max(0, min($max, (int) ($crudo[$clave] ?? 0)));
    }
    // Un texto de la portada que no cabe (el archivo se editó a mano) se ignora: queda el de siempre.
    foreach (cb_revista_textos_campos() as $clave => [, $max]) {
        $valor = cb_revista_texto_limpio($crudo['revista_textos'][$clave] ?? '');
        if (mb_strlen($valor) <= $max) {
            $vacio['revista_textos'][$clave] = $valor;
        }
    }
    return $vacio;
}

/** El correo que recibe copia oculta de todo lo que sale. Vacío = no se manda copia. */
function cb_ajuste_bcc(): string
{
    return cb_ajustes()['bcc_email'];
}

/** A dónde se manda el enlace para recuperar la contraseña del admin. */
function cb_ajuste_recuperacion(): string
{
    return cb_ajustes()['recovery_email'];
}

/**
 * Guarda los ajustes. Devuelve `['ok' => bool, 'errors' => string[]]`.
 *
 * Escribe con archivo temporal y `rename`, como el catálogo de planes: si el proceso muere a
 * mitad, el archivo queda como estaba y no truncado.
 */
function cb_guardar_ajustes(array $datos): array
{
    $errores = [];
    $limpio = [];
    $etiquetas = [
        'bcc_email' => 'El correo de copia oculta',
        'recovery_email' => 'El correo de recuperación',
    ];
    foreach ($etiquetas as $clave => $etiqueta) {
        $valor = trim((string) ($datos[$clave] ?? ''));
        if ($valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $errores[] = $etiqueta . ' no es una dirección válida.';
            continue;
        }
        $limpio[$clave] = $valor;
    }
    // Las cifras del manual. Vacío es válido y significa "no escribir el número"; lo que se
    // rechaza es un valor negativo o absurdo, que en el manual del papá quedaría en ridículo.
    $nombres = ['manual_anticipacion_min' => 'Los minutos de anticipación', 'manual_dias_lista' => 'Los días de plazo para la lista'];
    foreach (cb_ajustes_numericos() as $clave => $max) {
        $valor = trim((string) ($datos[$clave] ?? ''));
        if ($valor === '') { $limpio[$clave] = 0; continue; }
        if (!ctype_digit($valor) || (int) $valor > $max) {
            $errores[] = $nombres[$clave] . ' tiene que ser un número entre 0 y ' . $max . '.';
            continue;
        }
        $limpio[$clave] = (int) $valor;
    }
    // Los textos de la Portada de Revista. Uno que no viene se deja como estaba: así un guardado que no los trae (otra
    // pantalla, una prueba) no los borra. Vacío vuelve al texto de siempre.
    $actuales = cb_ajustes()['revista_textos'];
    $recibidos = is_array($datos['revista_textos'] ?? null) ? $datos['revista_textos'] : [];
    $limpio['revista_textos'] = [];
    foreach (cb_revista_textos_campos() as $clave => [$nombre, $max]) {
        if (!array_key_exists($clave, $recibidos)) {
            $limpio['revista_textos'][$clave] = $actuales[$clave];
            continue;
        }
        $valor = cb_revista_texto_limpio($recibidos[$clave]);
        if (mb_strlen($valor) > $max) {
            $errores[] = 'Portada de Revista: «' . $nombre . '» tiene ' . mb_strlen($valor) . ' letras y en la portada caben ' . $max . '.';
            continue;
        }
        $limpio['revista_textos'][$clave] = $valor;
    }
    if ($errores) {
        return ['ok' => false, 'errors' => $errores];
    }

    $contenido = [
        '_LEEME' => 'Ajustes generales del admin de CumpleClick. Se editan desde el admin, en la '
            . 'pantalla Ajustes. bcc_email recibe copia oculta de TODO correo que sale; '
            . 'recovery_email es a donde se manda el enlace para recuperar la contrasena; '
            . 'revista_textos son los textos fijos de la Portada de Revista (vacio = el de siempre).',
        'bcc_email' => $limpio['bcc_email'] ?? '',
        'recovery_email' => $limpio['recovery_email'] ?? '',
        'manual_anticipacion_min' => (int) ($limpio['manual_anticipacion_min'] ?? 0),
        'manual_dias_lista' => (int) ($limpio['manual_dias_lista'] ?? 0),
        'revista_textos' => $limpio['revista_textos'],
    ];
    $json = json_encode($contenido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return ['ok' => false, 'errors' => ['No se pudo preparar el archivo de ajustes.']];
    }
    $temporal = cb_ajustes_ruta() . '.tmp';
    if (@file_put_contents($temporal, $json . "\n", LOCK_EX) === false || !@rename($temporal, cb_ajustes_ruta())) {
        @unlink($temporal);
        return ['ok' => false, 'errors' => ['No se pudo escribir data/ajustes.json. Revisa los permisos de la carpeta.']];
    }
    return ['ok' => true, 'errors' => []];
}
