<?php
/**
 * Ajustes generales del administrador.
 *
 * Van acá y no en el archivo de configuración del servidor porque son de Luis y los cambia
 * cuando quiere, sin tocar nada del hosting: el correo al que le llega copia de todo lo que
 * sale, y el correo al que se manda el enlace para recuperar la contraseña.
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

function cb_ajustes(): array
{
    $vacio = ['bcc_email' => '', 'recovery_email' => ''];
    foreach (array_keys(cb_ajustes_numericos()) as $clave) { $vacio[$clave] = 0; }
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
    if ($errores) {
        return ['ok' => false, 'errors' => $errores];
    }

    $contenido = [
        '_LEEME' => 'Ajustes generales del admin de CumpleClick. Se editan desde el admin, en la '
            . 'pantalla Ajustes. bcc_email recibe copia oculta de TODO correo que sale; '
            . 'recovery_email es a donde se manda el enlace para recuperar la contrasena.',
        'bcc_email' => $limpio['bcc_email'] ?? '',
        'recovery_email' => $limpio['recovery_email'] ?? '',
        'manual_anticipacion_min' => (int) ($limpio['manual_anticipacion_min'] ?? 0),
        'manual_dias_lista' => (int) ($limpio['manual_dias_lista'] ?? 0),
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
