<?php
/**
 * admin/_acceso.php — el portero único del backoffice (2026-09-13).
 *
 * Cada página del admin lo carga justo después de `session_start()` y las cabeceras. Acá se
 * decide TODO lo que tiene que ver con quién entró:
 *
 *  1. sin sesión, o con la sesión vencida, manda a `login.php` (con `volver`);
 *  2. carga el usuario desde la base EN CADA PETICIÓN: deshabilitarlo lo saca en el acto;
 *  3. con contraseña temporal, solo deja ir a `perfil.php` hasta que la cambie;
 *  4. aplica los permisos por página, por fiesta (`?party=` o `?p=`) y por módulo, y responde
 *     403 con "No tienes acceso" en vez de la página.
 *
 * El bloque de login por contraseña que cada página trae debajo queda como código muerto:
 * el portero redirige antes. Se dejó para no tocar 13 archivos el día de la fiesta; la entrada
 * por clave maestra vive en `maestro.php`.
 */
require_once __DIR__ . '/../lib.admin-usuarios.php';

function admin_acceso_h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function admin_pagina_actual(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
}

/** Al login, recordando a dónde iba si era un GET. */
function admin_ir_al_login(string $motivo = ''): void
{
    // La API de carteles la llama JavaScript: una redirección a una página de login le sirve
    // de nada. Contesta 401 como contestaba antes.
    if (admin_pagina_actual() === 'carteles-api.php') {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'no_autenticado']);
        exit;
    }
    $q = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $volver = admin_pagina_actual();
        $consulta = (string) ($_SERVER['QUERY_STRING'] ?? '');
        if ($consulta !== '') {
            $volver .= '?' . $consulta;
        }
        if ($volver !== 'index.php') {
            $q['volver'] = $volver;
        }
    }
    if ($motivo !== '') {
        $q['motivo'] = $motivo;
    }
    header('Location: login.php' . ($q ? '?' . http_build_query($q) : ''));
    exit;
}

function admin_cerrar_sesion(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** El usuario de la sesión, o null si ya no puede entrar. */
function admin_cargar_usuario_de_sesion(): ?array
{
    $id = (int) ($_SESSION['admin_usuario_id'] ?? 0);
    if ($id === 0) {
        return cb_admin_usuario_maestro();
    }
    if (!cb_admin_usuarios_listo()) {
        return null;
    }
    $usuario = cb_admin_usuario_por_id($id);
    return $usuario !== null && $usuario['activo'] ? $usuario : null;
}

// ── 1. Sesión ────────────────────────────────────────────────────────────────
if (empty($_SESSION['admin_logged'])) {
    admin_ir_al_login();
}
$ccAccesoIdle = (int) cb_config('session_idle_seconds');
$ccAccesoAbsoluto = (int) cb_config('session_absolute_seconds');
if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > $ccAccesoIdle
    || time() - (int) ($_SESSION['admin_started'] ?? 0) > $ccAccesoAbsoluto) {
    admin_cerrar_sesion();
    admin_ir_al_login('expiro');
}

// ── 2. Usuario ───────────────────────────────────────────────────────────────
$ccAccesoUsuario = admin_cargar_usuario_de_sesion();
if ($ccAccesoUsuario === null) {
    admin_cerrar_sesion();
    admin_ir_al_login('deshabilitado');
}
$GLOBALS['ccAdminUsuario'] = $ccAccesoUsuario;

function admin_usuario_actual(): array
{
    return $GLOBALS['ccAdminUsuario'];
}

function admin_es_super(): bool
{
    return admin_usuario_actual()['rol'] === 'super';
}

function admin_fiesta_permitida(string $slug): bool
{
    return cb_admin_usuario_ve_fiesta(admin_usuario_actual(), $slug);
}

function admin_puede(string $modulo, ?string $slug = null): bool
{
    return cb_admin_usuario_puede(admin_usuario_actual(), $modulo, $slug);
}

/** Solo las fiestas que este usuario puede ver, con las mismas claves (slug => fiesta). */
function admin_fiestas_visibles(array $parties): array
{
    if (admin_es_super()) {
        return $parties;
    }
    $permitidas = admin_usuario_actual()['fiestas'];
    return array_filter($parties, static fn($p, $slug) => in_array((string) $slug, $permitidas, true), ARRAY_FILTER_USE_BOTH);
}

/** 403 con una página corta; JSON para las llamadas que lo esperan. */
function admin_denegar(string $detalle = ''): void
{
    http_response_code(403);
    if (admin_pagina_actual() === 'carteles-api.php') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'No tienes acceso a esa fiesta.']);
        exit;
    }
    $u = admin_usuario_actual();
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleClick Admin · Sin acceso</title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>
</style>
</head>
<body class="login-body">
  <main class="login-card">
    <div class="login-logo">
      <img src="../brand/cumpleclick-mark.svg" alt="" width="88" height="88">
      CumpleClick <span>Admin</span>
    </div>
    <p class="alert alert-error">No tienes acceso a esta parte del panel<?= $detalle !== '' ? ': ' . admin_acceso_h($detalle) : '' ?>.</p>
    <p class="muted small" style="text-align:center">Entraste como <strong><?= admin_acceso_h($u['nombre']) ?></strong>. Si necesitas esta opción, pídesela a quien te dio el acceso.</p>
    <p style="text-align:center"><a class="btn btn-ghost" href="index.php">Volver a mis fiestas</a></p>
  </main>
</body>
</html>
<?php
    exit;
}

function admin_exigir(string $modulo, ?string $slug = null): void
{
    if (!admin_puede($modulo, $slug)) {
        admin_denegar($slug !== null && !admin_fiesta_permitida($slug) ? 'esa fiesta no es tuya' : CB_ADMIN_MODULOS[$modulo]['nombre'] ?? $modulo);
    }
}

function admin_exigir_super(): void
{
    if (!admin_es_super()) {
        admin_denegar('es solo para el superadministrador');
    }
}

// ── 3. Contraseña temporal pendiente ─────────────────────────────────────────
if ($ccAccesoUsuario['debe_cambiar'] && admin_pagina_actual() !== 'perfil.php') {
    header('Location: perfil.php?cambiar=1');
    exit;
}

// ── 4. Qué exige cada página ─────────────────────────────────────────────────
// index.php, perfil.php y usuarios.php se cuidan solos (usuarios.php llama a admin_exigir_super).
$ccAccesoSoloSuper = ['invitations.php', 'comprobante.php', 'aceptaciones.php', 'leads.php', 'planes.php',
    'finanzas.php', 'ajustes.php', 'marca.php'];
// página => [módulos que la abren (basta uno), nombre del parámetro con la fiesta]
$ccAccesoPorFiesta = [
    'album.php' => [['fotos', 'album'], 'party'],
    'mensajes.php' => [['mensajes'], 'p'],
    'event-profile.php' => [['perfil'], 'party'],
    'carteles-api.php' => [['carteles'], 'p'],
];
if (!admin_es_super()) {
    $ccAccesoPagina = admin_pagina_actual();
    if (in_array($ccAccesoPagina, $ccAccesoSoloSuper, true)) {
        admin_denegar('es solo para el superadministrador');
    }
    if (isset($ccAccesoPorFiesta[$ccAccesoPagina])) {
        [$ccAccesoModulos, $ccAccesoParam] = $ccAccesoPorFiesta[$ccAccesoPagina];
        $ccAccesoSlug = (string) ($_GET[$ccAccesoParam] ?? $_POST[$ccAccesoParam] ?? '');
        $ccAccesoOk = $ccAccesoSlug !== '' && admin_fiesta_permitida($ccAccesoSlug);
        if ($ccAccesoOk) {
            $ccAccesoOk = false;
            foreach ($ccAccesoModulos as $ccAccesoModulo) {
                if (admin_puede($ccAccesoModulo, $ccAccesoSlug)) {
                    $ccAccesoOk = true;
                    break;
                }
            }
        }
        if (!$ccAccesoOk) {
            admin_denegar($ccAccesoSlug === '' ? 'falta la fiesta' : 'esa fiesta no es tuya o no tienes esa opción');
        }
    }
}

// ── Piezas comunes para dibujar ──────────────────────────────────────────────
/** La barra de pestañas: un operador ve solo Fiestas (y Mensajes si lo tiene). */
function admin_nav(string $activa = 'fiestas'): string
{
    $icono = static fn(string $n): string => function_exists('admin_icon') ? admin_icon($n) : '';
    $u = admin_usuario_actual();
    $super = $u['rol'] === 'super';
    $tabs = [['fiestas', 'index.php', $icono('party') . ' Fiestas']];
    if ($super) {
        $tabs[] = ['temas', 'index.php?view=temas', $icono('palette') . ' Temáticas'];
        $nuevos = 0;
        if (cb_storage_mode() === 'db') {
            try {
                $nuevos = (int) cb_pdo()->query("SELECT COUNT(*) FROM cc_leads WHERE status = 'new'")->fetchColumn();
            } catch (Throwable $e) {
                $nuevos = 0;
            }
        }
        $tabs[] = ['leads', 'leads.php', $icono('party') . ' Solicitudes' . ($nuevos > 0 ? ' <b class="tab-badge">' . $nuevos . '</b>' : '')];
        $tabs[] = ['mensajes', 'mensajes.php', $icono('party') . ' Mensajes'];
        $tabs[] = ['comprobante', 'comprobante.php', $icono('copy') . ' Comprobante'];
        $tabs[] = ['planes', 'planes.php', $icono('copy') . ' Planes'];
        $tabs[] = ['finanzas', 'finanzas.php', $icono('chart') . ' Finanzas'];
        $tabs[] = ['marketing', 'contenido.php', 'Contenido'];
        $tabs[] = ['ajustes', 'ajustes.php', $icono('copy') . ' Ajustes'];
        $tabs[] = ['usuarios', 'usuarios.php', $icono('party') . ' Usuarios'];
    } elseif (in_array('mensajes', $u['modulos'], true) && $u['fiestas']) {
        $tabs[] = ['mensajes', 'mensajes.php?p=' . rawurlencode($u['fiestas'][0]), $icono('party') . ' Mensajes'];
    }
    $html = '<nav class="tabs">';
    foreach ($tabs as [$clave, $href, $texto]) {
        $html .= '<a class="tab' . ($clave === $activa ? ' active' : '') . '" href="' . admin_acceso_h($href) . '">' . $texto . '</a>';
    }
    return $html . '</nav>';
}

/** Quién entró y el enlace a su perfil, para la cabecera. La clave maestra no tiene perfil. */
function admin_usuario_chip(): string
{
    $u = admin_usuario_actual();
    if ($u['id'] === 0) {
        return '<span class="muted small">Clave maestra</span>';
    }
    return '<a class="btn btn-ghost" href="perfil.php">' . admin_acceso_h($u['nombre']) . ' · Mi perfil</a>';
}
