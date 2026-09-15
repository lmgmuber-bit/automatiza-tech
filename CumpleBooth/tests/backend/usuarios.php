<?php
/**
 * Usuarios del backoffice (2026-09-13): crear, entrar, permisos por fiesta y módulo, contraseña
 * temporal, deshabilitar y el correo de bienvenida. Sobre SQLite, sin tocar ninguna base real.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$tmp = sys_get_temp_dir() . '/cumpleclick-usuarios-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});
putenv('CC_STORAGE_MODE=db'); putenv('CC_PDO_DSN=sqlite:' . $tmp . '/test.sqlite');
putenv('CC_APP_HMAC_KEY=' . str_repeat('b', 64)); putenv('CC_PUBLIC_BASE_URL=https://example.test/app');
putenv('CC_PHOTO_DIR=' . $tmp . '/photos'); putenv('CC_STATE_DIR=' . $tmp . '/state');
putenv('CUMPLECLICK_CONFIG_FILE=' . $tmp . '/no-config.php'); putenv('CC_AJUSTES_PATH=' . $tmp . '/ajustes.json');
require dirname(__DIR__, 2) . '/public/lib.php';
require dirname(__DIR__, 2) . '/public/lib.mail.php';
require dirname(__DIR__, 2) . '/public/lib.mail-templates.php';
require dirname(__DIR__, 2) . '/public/lib.admin-usuarios.php';
require_once __DIR__ . '/_migraciones.php';
cb_test_migrar_todo(cb_pdo());

$tests = 0;
function u_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

$pdo = cb_pdo();
foreach ([['samantha-hielo', 'Samantha', 'hielo'], ['luciano-spidey', 'Luciano', 'spidey']] as [$slug, $nombre, $tema]) {
    $pdo->prepare('INSERT INTO cc_parties(public_slug,birthday_person_name,theme_slug,active,created_at,updated_at) VALUES(?,?,?,1,?,?)')
        ->execute([$slug, $nombre, $tema, gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')]);
}

// ── Tabla lista y limpieza de entradas ──────────────────────────────────────
u_check(cb_admin_usuarios_listo(), 'la migración 023 deja la tabla lista');
u_check(cb_admin_modulos_limpiar(['juegos', 'fotos', 'nada', 'fotos']) === ['fotos', 'juegos'], 'módulos: solo conocidos, sin repetir, en orden');
u_check(cb_admin_rol_limpiar('super') === 'super' && cb_admin_rol_limpiar('jefe') === 'operador', 'rol desconocido cae a operador');
u_check(preg_match('/^[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$/', cb_admin_contrasena_temporal()) === 1, 'temporal de tres grupos');
u_check(strpbrk(cb_admin_contrasena_temporal() . cb_admin_contrasena_temporal(), '0O1lI') === false, 'temporal sin caracteres ambiguos');

// ── Crear ───────────────────────────────────────────────────────────────────
$r = cb_admin_usuario_crear(['nombre' => ' Ana Pérez ', 'correo' => ' Ana@Correo.CL ', 'rol' => 'operador',
    'modulos' => ['juegos', 'fotos', 'inventado'], 'fiestas' => ['samantha-hielo', 'no-existe']]);
u_check($r['ok'] === true, 'crear operador');
$temporal = $r['temporal'];
$ana = cb_admin_usuario_por_id((int) $r['id']);
u_check($ana['email'] === 'ana@correo.cl' && $ana['nombre'] === 'Ana Pérez', 'correo en minúsculas y nombre sin espacios');
u_check($ana['modulos'] === ['fotos', 'juegos'], 'módulos guardados limpios');
u_check($ana['fiestas'] === ['samantha-hielo'], 'solo las fiestas que existen');
u_check($ana['debe_cambiar'] === true && $ana['activo'] === true, 'nace habilitada y con contraseña temporal');
u_check(cb_admin_usuario_por_correo('ANA@correo.cl')['id'] === $ana['id'], 'se encuentra por correo sin importar mayúsculas');

$dup = cb_admin_usuario_crear(['nombre' => 'Otra', 'correo' => 'ana@correo.cl']);
u_check(!$dup['ok'] && in_array('Ya hay un usuario con ese correo.', $dup['errors'], true), 'correo repetido se rechaza');
$malo = cb_admin_usuario_crear(['nombre' => '', 'correo' => 'sin-arroba']);
u_check(!$malo['ok'] && count($malo['errors']) === 2, 'nombre vacío y correo inválido: dos errores');

// ── Entrar ──────────────────────────────────────────────────────────────────
u_check(cb_admin_login_verificar('ana@correo.cl', 'otra-cosa')['error'] === 'credenciales', 'clave equivocada');
u_check(cb_admin_login_verificar('nadie@correo.cl', $temporal)['error'] === 'credenciales', 'correo inexistente: mismo mensaje');
$login = cb_admin_login_verificar('Ana@Correo.cl', $temporal);
u_check($login['ok'] === true && $login['usuario']['debe_cambiar'] === true, 'entra con la temporal y debe cambiarla');
u_check(cb_admin_usuario_por_id($ana['id'])['ultimo_acceso'] !== '', 'queda el último acceso');

// ── Permisos ────────────────────────────────────────────────────────────────
$ana = cb_admin_usuario_por_id($ana['id']);
u_check(cb_admin_usuario_ve_fiesta($ana, 'samantha-hielo') && !cb_admin_usuario_ve_fiesta($ana, 'luciano-spidey'), 've solo su fiesta');
u_check(cb_admin_usuario_puede($ana, 'fotos', 'samantha-hielo'), 'fotos en su fiesta');
u_check(!cb_admin_usuario_puede($ana, 'fotos', 'luciano-spidey'), 'fotos en otra fiesta, no');
u_check(!cb_admin_usuario_puede($ana, 'album', 'samantha-hielo'), 'módulo no marcado, no');
u_check(cb_admin_usuario_puede($ana, 'juegos') && !cb_admin_usuario_puede($ana, 'carteles'), 'sin fiesta, mira solo el módulo');
u_check(cb_admin_usuario_puede_fiesta($ana['id'], 'samantha-hielo') && !cb_admin_usuario_puede_fiesta($ana['id'], 'luciano-spidey'), 'por id, para la galería');
u_check(cb_admin_usuario_puede_fiesta(0, 'luciano-spidey'), 'la clave maestra ve todo');
$maestro = cb_admin_usuario_maestro();
u_check($maestro['rol'] === 'super' && cb_admin_usuario_puede($maestro, 'album', 'luciano-spidey'), 'maestro puede todo');

// ── Cambiar la contraseña ───────────────────────────────────────────────────
$c = cb_admin_usuario_cambiar_contrasena($ana['id'], 'no-es', 'fiesta-de-samantha', 'fiesta-de-samantha');
u_check(!$c['ok'] && $c['errors'] === ['La contraseña actual no es correcta.'], 'exige la actual');
$c = cb_admin_usuario_cambiar_contrasena($ana['id'], $temporal, 'corta', 'corta');
u_check(!$c['ok'] && str_contains($c['errors'][0], 'al menos 10'), 'mínimo de 10');
$c = cb_admin_usuario_cambiar_contrasena($ana['id'], $temporal, 'fiesta-de-samantha', 'fiesta-de-samanthA');
u_check(!$c['ok'] && $c['errors'] === ['Las dos contraseñas nuevas no coinciden.'], 'deben coincidir');
$c = cb_admin_usuario_cambiar_contrasena($ana['id'], $temporal, 'fiesta-de-samantha', 'fiesta-de-samantha');
u_check($c['ok'] === true, 'cambio correcto');
u_check(cb_admin_usuario_por_id($ana['id'])['debe_cambiar'] === false, 'ya no debe cambiarla');
u_check(cb_admin_login_verificar('ana@correo.cl', 'fiesta-de-samantha')['ok'] === true, 'entra con la nueva');
u_check(cb_admin_login_verificar('ana@correo.cl', $temporal)['error'] === 'credenciales', 'la temporal ya no sirve');

// ── Deshabilitar y volver a habilitar ───────────────────────────────────────
u_check(cb_admin_usuario_activar($ana['id'], false), 'deshabilitar');
u_check(cb_admin_login_verificar('ana@correo.cl', 'fiesta-de-samantha')['error'] === 'deshabilitado', 'deshabilitada: clave correcta pero no entra');
u_check(cb_admin_usuario_por_id($ana['id'])['activo'] === false, 'queda inactiva');
u_check(!cb_admin_usuario_activar(999, true), 'id inexistente');
u_check(cb_admin_usuario_activar($ana['id'], true) && cb_admin_login_verificar('ana@correo.cl', 'fiesta-de-samantha')['ok'], 'vuelve a entrar');

// ── Contraseña temporal nueva ───────────────────────────────────────────────
$re = cb_admin_usuario_reiniciar_contrasena($ana['id']);
u_check($re['ok'] && $re['temporal'] !== $temporal, 'temporal nueva');
u_check(cb_admin_login_verificar('ana@correo.cl', 'fiesta-de-samantha')['error'] === 'credenciales', 'la anterior dejó de servir');
u_check(cb_admin_login_verificar('ana@correo.cl', $re['temporal'])['usuario']['debe_cambiar'] === true, 'y con la nueva debe cambiarla otra vez');

// ── Actualizar accesos ──────────────────────────────────────────────────────
$a = cb_admin_usuario_actualizar($ana['id'], ['nombre' => 'Ana P.', 'correo' => 'ana@correo.cl', 'rol' => 'operador',
    'modulos' => ['album'], 'fiestas' => ['luciano-spidey', 'samantha-hielo']]);
u_check($a['ok'] === true, 'actualizar');
$ana = cb_admin_usuario_por_id($ana['id']);
u_check($ana['nombre'] === 'Ana P.' && $ana['modulos'] === ['album'] && count($ana['fiestas']) === 2, 'accesos nuevos');
$a = cb_admin_usuario_actualizar($ana['id'], ['nombre' => 'Ana', 'correo' => 'ana@correo.cl', 'rol' => 'super']);
u_check($a['ok'] && cb_admin_usuario_puede(cb_admin_usuario_por_id($ana['id']), 'juegos', 'luciano-spidey'), 'como super puede todo');
u_check(!cb_admin_usuario_actualizar(999, ['nombre' => 'x', 'correo' => 'x@x.cl'])['ok'], 'actualizar inexistente');

// ── Correo de bienvenida ────────────────────────────────────────────────────
$usuario = cb_admin_usuario_por_id($ana['id']);
$usuario['rol'] = 'operador';
$m = cb_admin_correo_bienvenida($usuario, 'ABCD-EFGH-JKLM', 'https://example.test/app/admin/login.php', ['Samantha'], 'Luis');
u_check(str_contains($m['text'], 'ABCD-EFGH-JKLM') && str_contains($m['html'], 'ABCD-EFGH-JKLM'), 'la temporal va en texto y html');
u_check(str_contains($m['text'], 'https://example.test/app/admin/login.php') && str_contains($m['html'], 'href="https://example.test/app/admin/login.php"'), 'enlace de entrada');
u_check(str_contains($m['text'], 'ana@correo.cl') && str_contains($m['text'], 'Luis te dio acceso') && str_contains($m['text'], 'la fiesta de Samantha'), 'quién y para qué');
u_check(str_contains($m['html'], 'CumpleClick') && str_contains($m['html'], 'automatizatech.cl'), 'plantilla corporativa');
u_check($m['subject'] === 'CumpleClick: tu acceso al panel de la fiesta', 'asunto');
$m2 = cb_admin_correo_bienvenida($usuario, 'X', 'https://x', [], 'Luis', true);
u_check(str_contains($m2['text'], 'contraseña temporal nueva') && $m2['subject'] === 'CumpleClick: tu contraseña temporal nueva', 'variante de reinicio');
$correo = cc_mail_build(['to' => 'ana@correo.cl', 'subject' => $m['subject'], 'text' => $m['text'], 'html' => $m['html']],
    ['from' => 'no-reply@example.test', 'from_name' => 'CumpleClick', 'reply_to' => '']);
u_check(str_contains($correo, 'ABCD-EFGH-JKLM'), 'el mensaje armado lleva la temporal');

// ── Sin base de datos no hay usuarios, y la clave maestra sigue ─────────────
$pdo->exec('DROP TABLE cc_admin_user_parties');
$pdo->exec('DROP TABLE cc_admin_users');
// La función cachea el resultado: se comprueba con un proceso nuevo (en un archivo, porque
// `php -r` con comillas dentro no sobrevive al escapado de Windows).
$sonda = $tmp . '/sonda.php';
file_put_contents($sonda, '<?php require ' . var_export(dirname(__DIR__, 2) . '/public/lib.admin-usuarios.php', true) . ';'
    . 'echo cb_admin_usuarios_listo() ? "si" : "no", "|", cb_admin_login_verificar("ana@correo.cl", "x")["error"], "|", cb_admin_usuario_puede_fiesta(0, "z") ? "maestro" : "nadie";');
$salida = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($sonda));
u_check(trim((string) $salida) === 'no|credenciales|maestro', 'sin tabla: no listo, nadie entra por correo, la maestra sigue (' . trim((string) $salida) . ')');

echo "OK usuarios: $tests comprobaciones.\n";
