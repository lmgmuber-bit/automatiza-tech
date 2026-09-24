<?php
/**
 * Recuperación de la contraseña del admin.
 *
 * La contraseña vive como hash en la configuración del servidor (`admin_password_hash`), que
 * es un archivo con secretos que no se toca desde la web. Para poder cambiarla sin entrar por
 * SSH, el hash nuevo se guarda en un archivo aparte del **directorio de estado**, que está
 * fuera de la carpeta pública, y `admin/config.php` lo prefiere cuando existe. Así:
 *
 *  - nunca se reescribe el archivo de configuración con las credenciales de la base,
 *  - el hash nuevo no queda en el repositorio ni accesible por web,
 *  - y volver atrás es borrar un archivo.
 *
 * El enlace de recuperación se manda SIEMPRE al correo configurado en Ajustes, nunca a uno
 * escrito en el formulario: si el atacante pudiera elegir el destino, el enlace dejaría de
 * probar nada.
 */
require_once __DIR__ . '/lib.ajustes.php';

/** Cuánto vive el enlace. Corto a propósito: es una llave para entrar al backoffice. */
const CB_RESET_VIGENCIA_SEGUNDOS = 1800;   // 30 minutos

/** El directorio de estado puede no existir todavia: se crea antes de escribir en el. */
function cb_admin_estado_listo(string $ruta): void
{
    $dir = dirname($ruta);
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
}

function cb_admin_password_archivo(): string
{
    $ruta = cb_state_path('admin-password.json');
    cb_admin_estado_listo($ruta);
    return $ruta;
}

function cb_admin_reset_archivo(): string
{
    $ruta = cb_state_path('admin-reset.json');
    cb_admin_estado_listo($ruta);
    return $ruta;
}

/** Hash vigente puesto desde la recuperación, o '' si nunca se cambió por acá. */
function cb_admin_password_override(): string
{
    $ruta = cb_admin_password_archivo();
    if (!is_file($ruta)) {
        return '';
    }
    $datos = json_decode((string) @file_get_contents($ruta), true);
    $hash = is_array($datos) ? trim((string) ($datos['hash'] ?? '')) : '';
    // Solo se acepta algo que de verdad parezca un hash de password_hash().
    return preg_match('/^\$2[aby]\$|^\$argon2/', $hash) === 1 ? $hash : '';
}

/**
 * Emite un enlace de recuperación y devuelve el token en claro (se muestra una sola vez, en
 * el correo). En disco solo queda su huella, como con los enlaces del Álbum.
 */
function cb_admin_reset_emitir(): string
{
    $token = cb_opaque_token(24);
    $datos = [
        'hash' => cb_hash_token($token),
        'expira' => time() + CB_RESET_VIGENCIA_SEGUNDOS,
        'emitido' => gmdate('Y-m-d H:i:s'),
    ];
    @file_put_contents(cb_admin_reset_archivo(), json_encode($datos), LOCK_EX);
    return $token;
}

/** ¿Este token sirve ahora mismo? */
function cb_admin_reset_valido(string $token): bool
{
    if ($token === '') {
        return false;
    }
    $ruta = cb_admin_reset_archivo();
    if (!is_file($ruta)) {
        return false;
    }
    $datos = json_decode((string) @file_get_contents($ruta), true);
    if (!is_array($datos) || (int) ($datos['expira'] ?? 0) < time()) {
        return false;
    }
    // hash_equals y no ==: comparar con == filtra informacion por el tiempo de respuesta.
    return hash_equals((string) ($datos['hash'] ?? ''), cb_hash_token($token));
}

/** Un enlace usado no se puede volver a usar. */
function cb_admin_reset_borrar(): void
{
    @unlink(cb_admin_reset_archivo());
}

/**
 * Deja la contraseña nueva. Devuelve `['ok' => bool, 'errors' => string[]]`.
 *
 * El mínimo de 10 caracteres no es capricho: esta clave abre el backoffice completo, con los
 * datos de los clientes y las fotos de los niños.
 */
function cb_admin_password_cambiar(string $nueva, string $repetida): array
{
    $errores = [];
    if (mb_strlen($nueva) < 10) {
        $errores[] = 'La contraseña tiene que tener al menos 10 caracteres.';
    }
    if ($nueva !== $repetida) {
        $errores[] = 'Las dos contraseñas no coinciden.';
    }
    if ($errores) {
        return ['ok' => false, 'errors' => $errores];
    }

    $hash = password_hash($nueva, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        return ['ok' => false, 'errors' => ['No se pudo preparar la contraseña.']];
    }
    $ruta = cb_admin_password_archivo();
    $temporal = $ruta . '.tmp';
    $contenido = json_encode(['hash' => $hash, 'cambiada' => gmdate('Y-m-d H:i:s')]);
    if (@file_put_contents($temporal, $contenido, LOCK_EX) === false || !@rename($temporal, $ruta)) {
        @unlink($temporal);
        return ['ok' => false, 'errors' => ['No se pudo guardar la contraseña nueva.']];
    }
    @chmod($ruta, 0600);
    cb_admin_reset_borrar();
    return ['ok' => true, 'errors' => []];
}
