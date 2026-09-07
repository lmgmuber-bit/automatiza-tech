<?php
/** Pruebas de los ajustes generales, la copia oculta y la recuperación de la contraseña. */
if (PHP_SAPI !== 'cli') { exit(2); }
$tmp = sys_get_temp_dir() . '/cumpleclick-admin-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});
putenv('CC_STORAGE_MODE=json');
putenv('CC_APP_HMAC_KEY=' . str_repeat('f', 64));
putenv('CC_PUBLIC_BASE_URL=https://example.test/app');
putenv('CC_PHOTO_DIR=' . $tmp . '/photos');
putenv('CC_STATE_DIR=' . $tmp . '/state');
putenv('CC_PARTIES_JSON_PATH=' . $tmp . '/parties.json');
putenv('CUMPLECLICK_CONFIG_FILE=' . $tmp . '/no-config.php');
putenv('CC_AJUSTES_PATH=' . $tmp . '/ajustes.json');
require dirname(__DIR__, 2) . '/public/lib.php';
require dirname(__DIR__, 2) . '/public/lib.mail.php';
require dirname(__DIR__, 2) . '/public/lib.admin-password.php';

$tests = 0;
function ap_check(bool $cond, string $msg): void {
    global $tests;
    $tests++;
    if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); }
}

// ── Copia oculta ────────────────────────────────────────────────────────────
// Una copia oculta que va como cabecera no es oculta: el cliente la ve. Por eso viaja solo
// como destinatario extra del sobre, y esto comprueba que la cabecera NO aparece.
$cfg = ['from' => 'no-reply@example.test', 'from_name' => 'CumpleClick', 'reply_to' => ''];
$correo = cc_mail_build(['to' => 'cliente@example.test', 'subject' => 'Hola', 'text' => 'hola',
                         'html' => '<p>hola</p>', 'bcc' => ['copia@example.test']], $cfg);
ap_check(stripos($correo, "\nBcc:") === false && stripos($correo, "\r\nBcc:") === false,
    'el mensaje no lleva cabecera Bcc');
ap_check(strpos($correo, 'copia@example.test') === false, 'la direccion de copia no viaja en el mensaje');

$ocultos = cc_mail_ocultos(['to' => 'cliente@example.test', 'bcc' => ['copia@example.test']]);
ap_check($ocultos === ['copia@example.test'], 'la copia sale como destinatario del sobre');
ap_check(cc_mail_ocultos(['to' => 'a@b.cl', 'bcc' => ['a@b.cl']]) === [],
    'no se manda copia al mismo destinatario');
ap_check(cc_mail_ocultos(['to' => 'a@b.cl', 'bcc' => ['roto', '']]) === [],
    'las direcciones invalidas se descartan');
ap_check(cc_mail_ocultos(['to' => 'a@b.cl', 'bcc' => ['x@y.cl', 'x@y.cl']]) === ['x@y.cl'],
    'no se repite la misma copia');

// ── Ajustes generales ───────────────────────────────────────────────────────
$r = cb_guardar_ajustes(['bcc_email' => 'copia@example.test', 'recovery_email' => 'rescate@example.test']);
ap_check(!empty($r['ok']), 'los ajustes se guardan');
ap_check(cb_ajuste_bcc() === 'copia@example.test', 'la copia oculta queda configurada');
ap_check(cb_ajuste_recuperacion() === 'rescate@example.test', 'el correo de recuperacion queda configurado');
$r = cb_guardar_ajustes(['bcc_email' => 'esto no es un correo', 'recovery_email' => '']);
ap_check(empty($r['ok']), 'rechaza una direccion invalida');
ap_check(cb_ajuste_bcc() === 'copia@example.test', 'un guardado rechazado no pisa lo anterior');
$r = cb_guardar_ajustes(['bcc_email' => '', 'recovery_email' => '']);
ap_check(!empty($r['ok']) && cb_ajuste_bcc() === '', 'se puede apagar la copia dejandola vacia');

// La copia configurada se aplica sin que quien manda tenga que acordarse.
cb_guardar_ajustes(['bcc_email' => 'general@example.test', 'recovery_email' => 'rescate@example.test']);
ap_check(cc_mail_ocultos(['to' => 'cliente@example.test']) === ['general@example.test'],
    'todo correo lleva la copia general');

// ── Recuperacion de la contrasena ───────────────────────────────────────────
ap_check(cb_admin_password_override() === '', 'sin recuperacion no hay hash propio');
$token = cb_admin_reset_emitir();
ap_check(strlen($token) === 48, 'el token tiene largo de token');
ap_check(cb_admin_reset_valido($token), 'el token recien emitido sirve');
ap_check(!cb_admin_reset_valido('otro-token'), 'otro token no sirve');
ap_check(!cb_admin_reset_valido(''), 'el token vacio no sirve');

$r = cb_admin_password_cambiar('corta', 'corta');
ap_check(empty($r['ok']), 'rechaza una contrasena corta');
$r = cb_admin_password_cambiar('unaClaveLarga1', 'otraDistinta12');
ap_check(empty($r['ok']), 'rechaza si las dos no coinciden');
ap_check(cb_admin_reset_valido($token), 'un intento fallido no quema el enlace');

$r = cb_admin_password_cambiar('unaClaveLarga1', 'unaClaveLarga1');
ap_check(!empty($r['ok']), 'cambia la contrasena');
$hash = cb_admin_password_override();
ap_check($hash !== '' && password_verify('unaClaveLarga1', $hash), 'el hash nuevo valida la contrasena');
ap_check(!password_verify('unaClaveLarga1', 'la vieja'), 'la contrasena vieja no valida el hash nuevo');
ap_check(!cb_admin_reset_valido($token), 'el enlace se quema al usarlo');

// Un token vencido no sirve, aunque el archivo exista.
$token2 = cb_admin_reset_emitir();
$ruta = cb_admin_reset_archivo();
$datos = json_decode((string) file_get_contents($ruta), true);
$datos['expira'] = time() - 1;
file_put_contents($ruta, json_encode($datos));
ap_check(!cb_admin_reset_valido($token2), 'un enlace vencido no sirve');

echo "OK admin: $tests comprobaciones (ajustes, copia oculta y recuperacion)\n";
