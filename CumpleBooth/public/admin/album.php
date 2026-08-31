<?php
/**
 * admin/album.php — Álbum Recuerdo de un evento: recepción de aportes, QR y
 * publicación.
 *
 * Requiere storage_mode=db. La curaduría del material llega en la fase C; esta
 * página ya muestra el inventario para que se vea qué hay, pero todavía no
 * aprueba ni reordena.
 */
require __DIR__ . '/../lib.php';
require __DIR__ . '/config.php';

$adminSecureCookie = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_name('cc_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $adminSecureCookie, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function admin_csrf_check(): bool
{
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && $t !== '' && hash_equals($_SESSION['csrf'] ?? '', $t);
}

function admin_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(admin_csrf_token()) . '">';
}

function admin_format_bytes(int $bytes): string
{
    if ($bytes >= 1073741824) { return number_format($bytes / 1073741824, 2, ',', '.') . ' GB'; }
    if ($bytes >= 1048576) { return number_format($bytes / 1048576, 1, ',', '.') . ' MB'; }
    if ($bytes >= 1024) { return number_format($bytes / 1024, 0, ',', '.') . ' KB'; }
    return $bytes . ' B';
}

/**
 * Mismo set de íconos que index.php, más `refresh` y `back` que esta vista
 * necesita. Se duplica igual que admin_csrf_* porque las páginas del admin son
 * autónomas por diseño; unificarlas es un refactor aparte.
 */
function admin_icon(string $name): string
{
    $icons = [
        'trash' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>',
        'copy' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
        'external' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        'warn' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><circle cx="12" cy="12" r="10"/><path d="M12 17h.01"/></svg>',
        'lock' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'party' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12v0c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 9"/><path d="m6 16 6.5-6.5"/></svg>',
        'gallery' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>',
        'refresh' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>',
        'back' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>',
    ];
    return $icons[$name] ?? '';
}

// ================== LOGIN ==================
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!admin_csrf_check()) {
        $loginError = 'Sesión expirada, intenta de nuevo.';
    } else {
        $pass = (string) ($_POST['password'] ?? '');
        $loginLimit = cb_rate_limit('admin-login', cb_request_identity(), 5, 900, 900);
        if (!$loginLimit['allowed']) {
            $loginError = 'Demasiados intentos. Intenta nuevamente más tarde.';
        } elseif (ADMIN_PASSWORD_HASH === '') {
            $loginError = 'El administrador aún no está configurado. Ejecuta scripts/bootstrap.php.';
        } elseif (password_verify($pass, ADMIN_PASSWORD_HASH)) {
            $_SESSION['admin_logged'] = true;
            session_regenerate_id(true);
            $_SESSION['admin_started'] = time();
            $_SESSION['admin_seen'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            $returnTo = is_string($_POST['return_to'] ?? null) && strpos((string) $_POST['return_to'], 'album.php') === 0
                ? (string) $_POST['return_to']
                : 'index.php';
            header('Location: ' . $returnTo);
            exit;
        } else {
            $loginError = 'Contraseña incorrecta.';
        }
    }
}

$loggedIn = !empty($_SESSION['admin_logged']);
if ($loggedIn) {
    $idle = (int) cb_config('session_idle_seconds');
    $absolute = (int) cb_config('session_absolute_seconds');
    if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > $idle || time() - (int) ($_SESSION['admin_started'] ?? 0) > $absolute) {
        $_SESSION = [];
        session_destroy();
        $loggedIn = false;
        $loginError = 'La sesión expiró. Ingresa nuevamente.';
    } else {
        $_SESSION['admin_seen'] = time();
    }
}

if (!$loggedIn) {
    $returnTo = is_string($_GET['party'] ?? null) ? 'album.php?party=' . rawurlencode((string) $_GET['party']) : 'index.php';
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleBooth Admin · Ingresar</title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>
</style>
</head>
<body class="login-body">
  <main class="login-card">
    <div class="login-logo">
      <img src="../brand/cumpleclick-mark.svg" alt="CumpleClick" width="36" height="36" style="display:block">
      CumpleClick <span>Admin</span>
    </div>
    <?php if ($loginError !== ''): ?>
      <p class="alert alert-error"><?= admin_icon('warn') ?> <?= h($loginError) ?></p>
    <?php endif; ?>
    <form method="post" action="album.php" class="login-form">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
      <label for="password">Contraseña</label>
      <div class="input-icon">
        <?= admin_icon('lock') ?>
        <input type="password" id="password" name="password" required autofocus placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-cta btn-block">Ingresar</button>
    </form>
  </main>
</body>
</html>
    <?php
    exit;
}

// ================== DATOS DEL EVENTO ==================
$publicSlugRaw = is_string($_GET['party'] ?? null) ? (string) $_GET['party'] : '';
$publicSlug = cb_valid_public_slug($publicSlugRaw) ? $publicSlugRaw : '';
$party = $publicSlug !== '' ? cb_load_party_raw($publicSlug) : null;

$errors = [];
$okMessage = null;
/** Tokens en claro recién emitidos: se muestran una sola vez y no se persisten. */
$freshToken = null;
$freshViewToken = null;

if ($party === null) {
    $errors[] = 'El evento no existe o el identificador público no es válido.';
} elseif (cb_storage_mode() !== 'db') {
    $errors[] = 'El Álbum Recuerdo requiere storage_mode=db.';
}

$partyId = $party !== null ? cb_party_db_id($publicSlug) : null;
if ($party !== null && $partyId === null) {
    $errors[] = 'El evento no está disponible en la base de datos.';
}

$album = null;
$limits = cb_album_limits();
if (!$errors && $partyId !== null) {
    try {
        $album = cb_album_ensure($partyId);
        // Enlazar lo que la cabina haya sacado desde la última visita. Es
        // idempotente y no copia bytes, así que hacerlo al abrir la página
        // evita un botón más que el organizador tendría que recordar apretar.
        cb_album_sync_booth_photos((int) $album['id'], $partyId);
    } catch (Throwable $e) {
        error_log('CumpleClick admin/album.php: ' . $e->getMessage());
        $errors[] = 'No se pudo abrir el álbum de este evento.';
    }
}

// ================== ACCIONES ==================
if ($album !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'login') {
    $albumId = (int) $album['id'];
    if (!admin_csrf_check()) {
        $errors[] = 'Sesión expirada, vuelve a intentarlo.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'guardar-recepcion') {
                $intakeEnabled = !empty($_POST['intake_enabled']) ? 1 : 0;
                $intakeVideos = !empty($_POST['intake_videos']) ? 1 : 0;
                $requirePin = !empty($_POST['require_pin']) ? 1 : 0;

                $closesRaw = trim((string) ($_POST['intake_closes_at'] ?? ''));
                $closesAt = null;
                if ($closesRaw !== '') {
                    // <input type="date"> entrega YYYY-MM-DD; se cierra al final del día.
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $closesRaw)) {
                        $errors[] = 'La fecha de cierre no tiene un formato válido.';
                    } else {
                        $closesAt = $closesRaw . ' 23:59:59';
                    }
                }

                $message = trim((string) ($_POST['intake_message'] ?? ''));
                if (mb_strlen($message) > 400) {
                    $errors[] = 'El mensaje para invitados no puede pasar de 400 caracteres.';
                }

                // Activar la recepción implica pasar el álbum a "recolectando";
                // apagarla no lo cierra, solo deja de recibir (el organizador
                // puede estar curando con la recepción en pausa).
                $status = (string) $album['status'];
                if ($intakeEnabled && in_array($status, ['draft', 'closed'], true)) {
                    $status = 'collecting';
                }

                if (!$errors) {
                    cb_album_update($albumId, [
                        'intake_enabled' => $intakeEnabled,
                        'intake_videos' => $intakeVideos,
                        'require_pin' => $requirePin,
                        'intake_closes_at' => $closesAt,
                        'intake_message' => $message !== '' ? $message : null,
                        'status' => $status,
                    ]);
                    $okMessage = 'Configuración de recepción guardada.';
                }
            } elseif ($action === 'guardar-publicacion') {
                $title = trim((string) ($_POST['title'] ?? ''));
                $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
                if (mb_strlen($title) > 160) { $errors[] = 'El título no puede pasar de 160 caracteres.'; }
                if (mb_strlen($subtitle) > 240) { $errors[] = 'El subtítulo no puede pasar de 240 caracteres.'; }
                if (!$errors) {
                    cb_album_update($albumId, [
                        'title' => $title !== '' ? $title : null,
                        'subtitle' => $subtitle !== '' ? $subtitle : null,
                    ]);
                    $okMessage = 'Título y subtítulo guardados.';
                }
            } elseif ($action === 'generar-token') {
                // El vencimiento por defecto se calcula desde la fecha del
                // evento, no desde hoy: un cartel impreso una semana antes debe
                // seguir sirviendo el día de la fiesta.
                $eventDate = (string) ($party['fecha'] ?? '');
                $base = $eventDate !== '' ? strtotime($eventDate) : false;
                if ($base === false) { $base = time(); }
                $expiresAt = gmdate('Y-m-d H:i:s', $base + $limits['default_open_days'] * 86400);
                $freshToken = cb_album_issue_token($albumId, 'intake', $expiresAt, 'admin');
                if ((string) $album['status'] === 'draft') {
                    cb_album_update($albumId, ['status' => 'collecting']);
                }
                $okMessage = 'Enlace nuevo generado. El anterior quedó revocado.';
            } elseif ($action === 'revocar-token') {
                cb_album_revoke_tokens($albumId, 'intake');
                cb_album_update($albumId, ['intake_enabled' => 0]);
                $okMessage = 'Enlace revocado. Los carteles impresos con ese QR ya no funcionan.';
            } elseif ($action === 'cerrar-recepcion') {
                cb_album_update($albumId, ['status' => 'closed', 'intake_enabled' => 0]);
                cb_album_revoke_tokens($albumId, 'intake');
                $okMessage = 'Recepción cerrada.';
            } elseif ($action === 'reabrir-recepcion') {
                cb_album_update($albumId, ['status' => 'collecting']);
                $okMessage = 'Recepción reabierta. Genera un enlace nuevo para volver a recibir aportes.';
            } elseif ($action === 'moderar') {
                $mediaId = (int) ($_POST['media'] ?? 0);
                $state = (string) ($_POST['estado'] ?? '');
                if (!cb_album_set_moderation($albumId, $mediaId, $state, 'admin')) {
                    $errors[] = 'No se pudo cambiar el estado de ese recuerdo.';
                } else {
                    $okMessage = [
                        'approved' => 'Recuerdo aprobado.',
                        'hidden' => 'Recuerdo oculto: no aparecerá en la revista.',
                        'removed' => 'Recuerdo eliminado. Puedes restaurarlo desde el filtro "Eliminados".',
                        'pending' => 'Recuerdo devuelto a revisión.',
                    ][$state] ?? 'Estado actualizado.';
                    // La portada no puede quedar apuntando a algo que ya no se
                    // muestra: se suelta sola en vez de dejar la revista rota.
                    if (in_array($state, ['hidden', 'removed'], true) && (int) $album['cover_media_id'] === $mediaId) {
                        cb_album_update($albumId, ['cover_media_id' => null]);
                        $okMessage .= ' Se quitó como portada.';
                    }
                }
            } elseif ($action === 'aprobar-pendientes') {
                $aprobados = 0;
                foreach (cb_album_list_media($albumId, ['pending']) as $row) {
                    if (cb_album_set_moderation($albumId, (int) $row['id'], 'approved', 'admin')) {
                        $aprobados++;
                    }
                }
                $okMessage = $aprobados === 0
                    ? 'No había recuerdos pendientes.'
                    : ($aprobados === 1 ? 'Se aprobó 1 recuerdo.' : "Se aprobaron $aprobados recuerdos.");
            } elseif ($action === 'portada') {
                $mediaId = (int) ($_POST['media'] ?? 0);
                $target = cb_album_find_media($albumId, $mediaId);
                if ($target === null) {
                    $errors[] = 'Ese recuerdo no pertenece a este álbum.';
                } elseif ($target['media_kind'] !== 'image') {
                    $errors[] = 'La portada tiene que ser una foto.';
                } elseif ($target['moderation_status'] !== 'approved') {
                    $errors[] = 'Aprueba el recuerdo antes de ponerlo de portada.';
                } else {
                    cb_album_update($albumId, ['cover_media_id' => $mediaId]);
                    $okMessage = 'Portada elegida.';
                }
            } elseif ($action === 'mover') {
                // El orden se recalcula sobre la lista visible completa, no
                // sobre la página filtrada: mover con un filtro puesto no debe
                // reordenar en silencio lo que no se está viendo.
                $mediaId = (int) ($_POST['media'] ?? 0);
                $dir = (string) ($_POST['dir'] ?? '');
                $orden = array_map(static fn(array $r): int => (int) $r['id'], cb_album_list_media($albumId));
                $pos = array_search($mediaId, $orden, true);
                if ($pos === false) {
                    $errors[] = 'Ese recuerdo no pertenece a este álbum.';
                } else {
                    $destino = $dir === 'arriba' ? $pos - 1 : ($dir === 'abajo' ? $pos + 1 : $pos);
                    if ($destino >= 0 && $destino < count($orden)) {
                        [$orden[$pos], $orden[$destino]] = [$orden[$destino], $orden[$pos]];
                        cb_album_reorder($albumId, $orden);
                    }
                }
            } elseif ($action === 'reordenar') {
                $raw = (string) ($_POST['orden'] ?? '');
                $ids = array_values(array_filter(array_map('intval', explode(',', $raw))));
                if (!$ids) {
                    $errors[] = 'No se recibió un orden válido.';
                } else {
                    cb_album_reorder($albumId, $ids);
                    $okMessage = 'Orden guardado.';
                }
            } elseif ($action === 'publicar') {
                $aprobados = count(cb_album_list_media($albumId, ['approved']));
                if ($aprobados === 0) {
                    $errors[] = 'Aprueba al menos un recuerdo antes de publicar el álbum.';
                } else {
                    cb_album_update($albumId, [
                        'status' => 'published',
                        'published_at' => gmdate('Y-m-d H:i:s'),
                        'intake_enabled' => 0,
                    ]);
                    cb_album_revoke_tokens($albumId, 'intake');
                    // El enlace de lectura se emite en el momento de publicar y
                    // se muestra una sola vez, igual que el de aportes.
                    $freshViewToken = cb_album_issue_token($albumId, 'view', null, 'admin');
                    $okMessage = 'Álbum publicado. Guarda el enlace: no se vuelve a mostrar.';
                }
            } elseif ($action === 'despublicar') {
                cb_album_update($albumId, ['status' => 'closed']);
                cb_album_revoke_tokens($albumId, 'view');
                $okMessage = 'Álbum despublicado. El enlace que habías compartido dejó de funcionar.';
            } elseif ($action === 'nuevo-enlace-revista') {
                $freshViewToken = cb_album_issue_token($albumId, 'view', null, 'admin');
                $okMessage = 'Enlace de la revista regenerado. El anterior quedó revocado.';
            } elseif ($action === 'subir-organizador') {
                $result = cb_album_store_admin_uploads($albumId, $partyId, $publicSlug, $_FILES['archivos'] ?? null);
                if ($result['saved'] > 0) {
                    $okMessage = $result['saved'] === 1
                        ? 'Se agregó 1 archivo al álbum.'
                        : 'Se agregaron ' . $result['saved'] . ' archivos al álbum.';
                }
                foreach ($result['errors'] as $problem) {
                    $errors[] = $problem;
                }
                if ($result['saved'] === 0 && !$result['errors']) {
                    $errors[] = 'No se recibió ningún archivo.';
                }
            } else {
                $errors[] = 'Acción no reconocida.';
            }
        } catch (Throwable $e) {
            error_log('CumpleClick admin/album.php: ' . $e->getMessage());
            $errors[] = 'No se pudo completar la acción.';
        }
        if ($album !== null) {
            $album = cb_album_find_by_id($albumId);
        }
    }
}

/**
 * Botón de acción sobre un recuerdo. Cada uno es su propio POST con CSRF: no
 * hay enlaces GET que muten estado, así que un prefetch del navegador o una
 * imagen remota no pueden aprobar ni borrar nada.
 */
function admin_media_action(
    string $selfUrl,
    int $mediaId,
    string $action,
    string $label,
    string $variant = 'btn-ghost',
    array $extra = [],
    string $confirm = '',
    bool $disabled = false
): string {
    $html = '<form method="post" action="' . h($selfUrl) . '" class="inline-form"'
        . ($confirm !== '' ? ' data-confirm="' . h($confirm) . '"' : '') . '>'
        . admin_csrf_field()
        . '<input type="hidden" name="action" value="' . h($action) . '">'
        . '<input type="hidden" name="media" value="' . $mediaId . '">';
    foreach ($extra as $name => $value) {
        $html .= '<input type="hidden" name="' . h($name) . '" value="' . h($value) . '">';
    }
    $html .= '<button type="submit" class="btn ' . h($variant) . ' btn-sm"'
        . ($disabled ? ' disabled' : '') . '>' . h($label) . '</button></form>';
    return $html;
}

// ================== VISTA ==================
$stats = $album !== null ? cb_album_stats((int) $album['id']) : null;
$usage = $album !== null ? cb_album_usage((int) $album['id']) : ['count' => 0, 'bytes' => 0];
$tokenInfo = $album !== null ? cb_album_active_token_info((int) $album['id'], 'intake') : null;
$themes = cb_load_themes()['themes'] ?? [];
$themeSlug = (string) ($party['tema'] ?? '');
$themeName = (string) ($themes[$themeSlug]['nombre'] ?? $themeSlug);
$eventLabel = (string) ($party['nombre'] ?? $publicSlug);
$hasPin = $party !== null && !empty($party['galeriaPinHash']);
$defaultMessage = '¡Comparte tus mejores fotos y videos de esta celebración!';
$closesValue = '';
if ($album !== null && !empty($album['intake_closes_at'])) {
    $closesValue = substr((string) $album['intake_closes_at'], 0, 10);
}

$selfUrl = 'album.php?party=' . rawurlencode($publicSlug);
$sourceLabels = ['booth' => 'Cabina', 'guest' => 'Invitado', 'organizer' => 'Tuyo'];
$estadoLabels = [
    'draft' => 'borrador',
    'collecting' => 'recibiendo aportes',
    'closed' => 'recepción cerrada, sin publicar',
    'published' => 'publicado',
];
$stateLabels = ['pending' => 'Por revisar', 'approved' => 'Aprobado', 'hidden' => 'Oculto', 'removed' => 'Eliminado'];

// Filtros de curaduría. Solo "todos" permite reordenar: mover con un filtro
// puesto reordenaría en silencio lo que no se está viendo.
$filterTabs = [];
$mediaList = [];
$filterKey = 'todos';
if ($album !== null && $stats !== null) {
    $filterKey = (string) ($_GET['filtro'] ?? 'todos');
    $definitions = [
        'todos' => ['label' => 'Todos', 'states' => ['pending', 'approved', 'hidden'], 'source' => null],
        'pendientes' => ['label' => 'Por revisar', 'states' => ['pending'], 'source' => null],
        'cabina' => ['label' => 'Cabina', 'states' => ['pending', 'approved', 'hidden'], 'source' => 'booth'],
        'invitados' => ['label' => 'Invitados', 'states' => ['pending', 'approved', 'hidden'], 'source' => 'guest'],
        'organizador' => ['label' => 'Tuyos', 'states' => ['pending', 'approved', 'hidden'], 'source' => 'organizer'],
        'ocultos' => ['label' => 'Ocultos', 'states' => ['hidden'], 'source' => null],
        'eliminados' => ['label' => 'Eliminados', 'states' => ['removed'], 'source' => null],
    ];
    if (!isset($definitions[$filterKey])) {
        $filterKey = 'todos';
    }
    foreach ($definitions as $key => $definition) {
        $rows = cb_album_list_media((int) $album['id'], $definition['states'], $definition['source']);
        $filterTabs[$key] = ['label' => $definition['label'], 'count' => count($rows)];
        if ($key === $filterKey) {
            $mediaList = $rows;
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Álbum Recuerdo · <?= h($eventLabel) ?></title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>
</style>
</head>
<body>
<div class="wrap">
  <header class="topbar">
    <div class="logo">
      <img src="../brand/cumpleclick-mark.svg" alt="CumpleClick" width="36" height="36" style="display:block">
      CumpleClick <span>Álbum</span>
    </div>
    <p class="album-context"><?= h($eventLabel) ?><?= $themeName !== '' ? ' · ' . h($themeName) : '' ?></p>
    <a class="btn btn-ghost album-back" href="index.php"><?= admin_icon('back') ?> Volver a fiestas</a>
  </header>

  <main>
    <?php if ($okMessage !== null): ?>
      <p class="alert alert-ok"><?= admin_icon('check') ?> <?= h($okMessage) ?></p>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-error">
        <?= admin_icon('warn') ?>
        <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <?php if ($album === null): ?>
      <section class="card">
        <p>No hay un álbum disponible para este evento.</p>
        <a class="btn btn-ghost" href="index.php">Volver a fiestas</a>
      </section>
    <?php else: ?>

      <?php if ($freshToken !== null): ?>
        <?php
        $intakeUrl = cb_album_intake_url($freshToken);
        $signUrl = cb_album_sign_url($freshToken);
        ?>
        <section class="card album-token-card">
          <h2><?= admin_icon('check') ?> Enlace de aportes listo</h2>
          <p class="muted">
            Este enlace se muestra <strong>una sola vez</strong>: en la base solo queda su huella.
            Si cierras la página sin guardarlo, tendrás que generar uno nuevo (y el cartel impreso
            con el QR anterior dejará de servir).
          </p>
          <div class="party-url">
            <input type="text" readonly value="<?= h($intakeUrl) ?>" id="intake-url">
            <button type="button" class="btn btn-icon" data-copy="<?= h($intakeUrl) ?>" title="Copiar enlace">
              <?= admin_icon('copy') ?>
            </button>
            <a class="btn btn-icon" href="<?= h($intakeUrl) ?>" target="_blank" rel="noopener" title="Abrir página de carga">
              <?= admin_icon('external') ?>
            </a>
          </div>
          <p class="muted">
            Cartel imprimible con el QR:
            <a href="<?= h($signUrl) ?>" target="_blank" rel="noopener"><?= h($signUrl) ?></a>
            <br><em>El cartel y el QR llegan en la siguiente etapa; por ahora este enlace ya es válido
            para la página de carga.</em>
          </p>
        </section>
      <?php endif; ?>

      <section class="card">
        <h2>Recepción de fotos y videos</h2>
        <p class="muted">
          Los invitados escanean un QR y suben sus recuerdos. Nada de lo que suben se publica hasta
          que tú lo apruebes.
        </p>
        <form method="post" action="album.php?party=<?= rawurlencode($publicSlug) ?>" class="stack-form">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="action" value="guardar-recepcion">

          <label class="check-row">
            <input type="checkbox" name="intake_enabled" value="1" <?= !empty($album['intake_enabled']) ? 'checked' : '' ?>>
            <span>Recibir fotos y videos de invitados</span>
          </label>
          <label class="check-row">
            <input type="checkbox" name="intake_videos" value="1" <?= !empty($album['intake_videos']) ? 'checked' : '' ?>>
            <span>Permitir videos (máximo <?= (int) $limits['video_max_seconds'] ?> s y <?= admin_format_bytes((int) $limits['video_max_bytes']) ?> cada uno)</span>
          </label>
          <label class="check-row">
            <input type="checkbox" name="require_pin" value="1" <?= !empty($album['require_pin']) ? 'checked' : '' ?>>
            <span>
              Exigir el PIN de galería para ver la revista terminada
              <?php if (!$hasPin): ?>
                <em class="warn-inline">— este evento todavía no tiene PIN; configúralo al editar la fiesta.</em>
              <?php endif; ?>
            </span>
          </label>

          <label for="intake_closes_at">Cerrar recepción el</label>
          <input type="date" id="intake_closes_at" name="intake_closes_at" value="<?= h($closesValue) ?>">
          <p class="muted">Si lo dejas vacío, la recepción sigue abierta hasta que la cierres a mano.</p>

          <label for="intake_message">Mensaje para los invitados</label>
          <textarea id="intake_message" name="intake_message" rows="2" maxlength="400"
                    placeholder="<?= h($defaultMessage) ?>"><?= h((string) ($album['intake_message'] ?? '')) ?></textarea>

          <div class="party-actions">
            <button type="submit" class="btn btn-primary">Guardar</button>
          </div>
        </form>
      </section>

      <section class="card">
        <h2>Enlace y QR</h2>
        <?php if ($tokenInfo !== null): ?>
          <p>
            Hay un enlace activo, generado el <?= h(substr((string) $tokenInfo['created_at'], 0, 16)) ?> UTC.
            <?php if (!empty($tokenInfo['expires_at'])): ?>
              Vence el <?= h(substr((string) $tokenInfo['expires_at'], 0, 16)) ?> UTC.
            <?php else: ?>
              No tiene fecha de vencimiento.
            <?php endif; ?>
          </p>
          <p class="muted">
            Por seguridad el enlace no se puede volver a mostrar: solo se guardó su huella.
            Si lo perdiste, genera uno nuevo — pero recuerda que eso invalida cualquier cartel ya impreso.
          </p>
        <?php else: ?>
          <p>Todavía no hay un enlace activo para este evento.</p>
        <?php endif; ?>
        <div class="party-actions">
          <form method="post" action="album.php?party=<?= rawurlencode($publicSlug) ?>" class="inline-form"
                data-confirm="<?= $tokenInfo !== null ? 'Generar un enlace nuevo revoca el actual y deja inservibles los carteles ya impresos. ¿Continuar?' : '' ?>">
            <?= admin_csrf_field() ?>
            <input type="hidden" name="action" value="generar-token">
            <button type="submit" class="btn btn-cta"><?= admin_icon('refresh') ?> <?= $tokenInfo !== null ? 'Regenerar enlace' : 'Generar enlace' ?></button>
          </form>
          <?php if ($tokenInfo !== null): ?>
            <form method="post" action="album.php?party=<?= rawurlencode($publicSlug) ?>" class="inline-form"
                  data-confirm="Revocar el enlace corta la recepción de inmediato. ¿Continuar?">
              <?= admin_csrf_field() ?>
              <input type="hidden" name="action" value="revocar-token">
              <button type="submit" class="btn btn-danger"><?= admin_icon('trash') ?> Revocar enlace</button>
            </form>
          <?php endif; ?>
        </div>
      </section>

      <section class="card">
        <h2>Contenido</h2>
        <div class="kpis">
          <div class="kpi-card">
            <span class="kpi-icon"><?= admin_icon('gallery') ?></span>
            <div class="kpi-text"><strong><?= (int) $stats['total'] ?></strong><span>recuerdos en el álbum</span></div>
          </div>
          <div class="kpi-card">
            <span class="kpi-icon"><?= admin_icon('party') ?></span>
            <div class="kpi-text"><strong><?= (int) $stats['by_state']['pending'] ?></strong><span>por revisar</span></div>
          </div>
          <div class="kpi-card">
            <span class="kpi-icon"><?= admin_icon('copy') ?></span>
            <div class="kpi-text">
              <strong><?= admin_format_bytes((int) $usage['bytes']) ?></strong>
              <span>de <?= admin_format_bytes((int) $limits['album_max_bytes']) ?></span>
            </div>
          </div>
        </div>
        <ul class="plain-list">
          <li>Cabina: <strong><?= (int) $stats['by_source']['booth'] ?></strong></li>
          <li>Invitados: <strong><?= (int) $stats['by_source']['guest'] ?></strong></li>
          <li>Organizador: <strong><?= (int) $stats['by_source']['organizer'] ?></strong></li>
          <li>Fotos: <strong><?= (int) $stats['by_kind']['image'] ?></strong> · Videos: <strong><?= (int) $stats['by_kind']['video'] ?></strong></li>
          <li>Ocultos: <strong><?= (int) $stats['by_state']['hidden'] ?></strong> · Eliminados: <strong><?= (int) $stats['by_state']['removed'] ?></strong></li>
        </ul>
        <p class="muted">
          Las fotos de la cabina están enlazadas al álbum y no ocupan espacio extra:
          se referencian, no se copian.
        </p>

        <form method="post" action="<?= h($selfUrl) ?>" class="stack-form organizer-upload" enctype="multipart/form-data">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="action" value="subir-organizador">
          <label for="archivos">Agregar tus propias fotos o videos</label>
          <input type="file" id="archivos" name="archivos[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4">
          <p class="muted">
            Lo que subas acá queda aprobado de entrada. Mismos formatos y límites que
            para los invitados; los videos van hasta <?= (int) $limits['video_max_seconds'] ?> s.
          </p>
          <div class="party-actions">
            <button type="submit" class="btn btn-ghost">Subir al álbum</button>
          </div>
        </form>
      </section>

      <section class="card">
        <h2>Curaduría</h2>

        <nav class="curation-filters">
          <?php foreach ($filterTabs as $key => $tab): ?>
            <a class="chip <?= $filterKey === $key ? 'is-active' : '' ?>"
               href="<?= h($selfUrl . '&filtro=' . rawurlencode($key)) ?>">
              <?= h($tab['label']) ?> <span class="chip-count"><?= (int) $tab['count'] ?></span>
            </a>
          <?php endforeach; ?>
        </nav>

        <?php if ((int) $stats['by_state']['pending'] > 0): ?>
          <form method="post" action="<?= h($selfUrl) ?>" class="inline-form bulk-approve"
                data-confirm="¿Aprobar los <?= (int) $stats['by_state']['pending'] ?> recuerdos pendientes?">
            <?= admin_csrf_field() ?>
            <input type="hidden" name="action" value="aprobar-pendientes">
            <button type="submit" class="btn btn-primary btn-sm">
              <?= admin_icon('check') ?> Aprobar los <?= (int) $stats['by_state']['pending'] ?> pendientes
            </button>
          </form>
        <?php endif; ?>

        <?php if (!$mediaList): ?>
          <p class="muted empty-note">
            <?= $filterKey === 'todos'
              ? 'Todavía no hay recuerdos en este álbum. Aparecerán acá las fotos de la cabina y lo que envíen los invitados.'
              : 'No hay recuerdos en este filtro.' ?>
          </p>
        <?php else: ?>
          <ol class="curation-grid" id="curation-grid" data-reorder="<?= $filterKey === 'todos' ? '1' : '0' ?>">
            <?php foreach ($mediaList as $index => $item): ?>
              <?php
              $mediaId = (int) $item['id'];
              $isCover = (int) ($album['cover_media_id'] ?? 0) === $mediaId;
              $state = (string) $item['moderation_status'];
              $isVideo = (string) $item['media_kind'] === 'video';
              // Cabina y aporte se sirven por endpoints distintos: la foto de
              // cabina no tiene token propio a propósito.
              if ((string) $item['source'] === 'booth') {
                  $thumbUrl = $item['photo_token']
                      ? '../ver.php?t=' . rawurlencode((string) $item['photo_token']) . '&amp;download=inline'
                      : '';
                  $fullUrl = $thumbUrl;
              } else {
                  $token = rawurlencode((string) $item['access_token']);
                  $thumbUrl = '../ver-media.php?t=' . $token . '&amp;v=thumb';
                  $fullUrl = '../ver-media.php?t=' . $token;
              }
              ?>
              <li class="tile is-<?= h($state) ?> <?= $isCover ? 'is-cover' : '' ?>"
                  data-media="<?= $mediaId ?>" <?= $filterKey === 'todos' ? 'draggable="true"' : '' ?>>
                <div class="tile-media">
                  <?php if ($isVideo): ?>
                    <video class="tile-video" preload="none"
                           <?= $item['poster_storage_key'] ? 'poster="../ver-media.php?t=' . rawurlencode((string) $item['access_token']) . '&amp;v=poster"' : '' ?>
                           controls playsinline src="<?= $fullUrl ?>"></video>
                  <?php elseif ($thumbUrl !== ''): ?>
                    <img src="<?= $thumbUrl ?>" alt="" loading="lazy" decoding="async">
                  <?php else: ?>
                    <span class="tile-missing">Sin vista previa</span>
                  <?php endif; ?>
                  <span class="tile-badges">
                    <span class="badge badge--<?= h((string) $item['source']) ?>"><?= h($sourceLabels[(string) $item['source']] ?? $item['source']) ?></span>
                    <?php if ($isVideo): ?>
                      <span class="badge badge--video"><?= $item['duration_seconds'] ? (int) round((float) $item['duration_seconds']) . 's' : 'video' ?></span>
                    <?php endif; ?>
                    <?php if ($isCover): ?><span class="badge badge--cover">Portada</span><?php endif; ?>
                    <?php if ($state !== 'approved'): ?>
                      <span class="badge badge--state"><?= h($stateLabels[$state] ?? $state) ?></span>
                    <?php endif; ?>
                  </span>
                </div>

                <?php if (!empty($item['contributor_name']) || !empty($item['contributor_message'])): ?>
                  <div class="tile-note">
                    <?php if (!empty($item['contributor_name'])): ?>
                      <strong><?= h((string) $item['contributor_name']) ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($item['contributor_message'])): ?>
                      <span><?= h((string) $item['contributor_message']) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="tile-actions">
                  <?php if ($state !== 'approved'): ?>
                    <?= admin_media_action($selfUrl, $mediaId, 'moderar', 'Aprobar', 'btn-primary', ['estado' => 'approved']) ?>
                  <?php endif; ?>
                  <?php if ($state === 'approved'): ?>
                    <?= admin_media_action($selfUrl, $mediaId, 'moderar', 'Ocultar', 'btn-ghost', ['estado' => 'hidden']) ?>
                  <?php endif; ?>
                  <?php if ($state === 'removed'): ?>
                    <?= admin_media_action($selfUrl, $mediaId, 'moderar', 'Restaurar', 'btn-ghost', ['estado' => 'approved']) ?>
                  <?php else: ?>
                    <?= admin_media_action(
                        $selfUrl, $mediaId, 'moderar', 'Eliminar', 'btn-danger', ['estado' => 'removed'],
                        'Se puede restaurar después desde el filtro "Eliminados". ¿Eliminar este recuerdo?'
                    ) ?>
                  <?php endif; ?>
                  <?php if (!$isVideo && $state === 'approved' && !$isCover): ?>
                    <?= admin_media_action($selfUrl, $mediaId, 'portada', 'Portada', 'btn-ghost') ?>
                  <?php endif; ?>
                </div>

                <?php if ($filterKey === 'todos'): ?>
                  <div class="tile-order">
                    <?= admin_media_action($selfUrl, $mediaId, 'mover', '↑', 'btn-icon', ['dir' => 'arriba'], '', $index === 0) ?>
                    <span class="tile-position"><?= $index + 1 ?></span>
                    <?= admin_media_action($selfUrl, $mediaId, 'mover', '↓', 'btn-icon', ['dir' => 'abajo'], '', $index === count($mediaList) - 1) ?>
                  </div>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>

          <?php if ($filterKey === 'todos'): ?>
            <form method="post" action="<?= h($selfUrl) ?>" class="inline-form reorder-form" id="reorder-form" hidden>
              <?= admin_csrf_field() ?>
              <input type="hidden" name="action" value="reordenar">
              <input type="hidden" name="orden" id="reorder-value">
              <button type="submit" class="btn btn-cta btn-sm">Guardar el orden nuevo</button>
            </form>
            <p class="muted">
              En computador puedes arrastrar las tarjetas para reordenarlas. Las flechas
              funcionan igual en celular y con teclado.
            </p>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <section class="card">
        <h2>Publicación</h2>
        <form method="post" action="album.php?party=<?= rawurlencode($publicSlug) ?>" class="stack-form">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="action" value="guardar-publicacion">
          <label for="title">Título del álbum</label>
          <input type="text" id="title" name="title" maxlength="160"
                 value="<?= h((string) ($album['title'] ?? '')) ?>"
                 placeholder="Los recuerdos de <?= h($eventLabel) ?>">
          <label for="subtitle">Subtítulo</label>
          <input type="text" id="subtitle" name="subtitle" maxlength="240"
                 value="<?= h((string) ($album['subtitle'] ?? '')) ?>"
                 placeholder="<?= h($themeName) ?><?= !empty($party['fecha']) ? ' · ' . h((string) $party['fecha']) : '' ?>">
          <div class="party-actions">
            <button type="submit" class="btn btn-primary">Guardar</button>
          </div>
        </form>

        <?php if ($freshViewToken !== null): ?>
          <?php $viewUrl = cb_album_view_url($freshViewToken); ?>
          <div class="album-token-card">
            <h3><?= admin_icon('check') ?> Enlace de la revista</h3>
            <p class="muted">
              Este es el enlace que compartes con las familias. Se muestra <strong>una sola vez</strong>.
              <?php if (!empty($album['require_pin'])): ?>
                Además del enlace necesitarán el PIN de galería de esta fiesta.
              <?php else: ?>
                <strong>Cualquiera con el enlace puede ver el álbum</strong>, sin PIN.
              <?php endif; ?>
            </p>
            <div class="party-url">
              <input type="text" readonly value="<?= h($viewUrl) ?>">
              <button type="button" class="btn btn-icon" data-copy="<?= h($viewUrl) ?>" title="Copiar enlace">
                <?= admin_icon('copy') ?>
              </button>
              <a class="btn btn-icon" href="<?= h($viewUrl) ?>" target="_blank" rel="noopener" title="Abrir la revista">
                <?= admin_icon('external') ?>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <p class="muted">
          Estado actual: <strong><?= h($estadoLabels[(string) $album['status']] ?? $album['status']) ?></strong>.
          El material se conserva <strong><?= (int) $album['retention_days'] ?> días</strong> desde la fecha del evento;
          después la retención lo elimina junto con el resto de la fiesta.
        </p>

        <div class="party-actions">
          <?php if ((string) $album['status'] === 'published'): ?>
            <form method="post" action="<?= h($selfUrl) ?>" class="inline-form"
                  data-confirm="Regenerar el enlace deja inservible el que ya compartiste. ¿Continuar?">
              <?= admin_csrf_field() ?>
              <input type="hidden" name="action" value="nuevo-enlace-revista">
              <button type="submit" class="btn btn-ghost"><?= admin_icon('refresh') ?> Regenerar enlace de la revista</button>
            </form>
            <form method="post" action="<?= h($selfUrl) ?>" class="inline-form"
                  data-confirm="El enlace que compartiste dejará de funcionar de inmediato. ¿Despublicar el álbum?">
              <?= admin_csrf_field() ?>
              <input type="hidden" name="action" value="despublicar">
              <button type="submit" class="btn btn-danger">Despublicar</button>
            </form>
          <?php else: ?>
            <form method="post" action="<?= h($selfUrl) ?>" class="inline-form"
                  data-confirm="Publicar cierra la recepción de aportes y genera el enlace de la revista. ¿Continuar?">
              <?= admin_csrf_field() ?>
              <input type="hidden" name="action" value="publicar">
              <button type="submit" class="btn btn-cta"><?= admin_icon('gallery') ?> Publicar el álbum</button>
            </form>
          <?php endif; ?>
          <?php if ((string) $album['status'] === 'closed'): ?>
            <form method="post" action="album.php?party=<?= rawurlencode($publicSlug) ?>" class="inline-form">
              <?= admin_csrf_field() ?>
              <input type="hidden" name="action" value="reabrir-recepcion">
              <button type="submit" class="btn btn-ghost">Reabrir recepción</button>
            </form>
          <?php else: ?>
            <form method="post" action="album.php?party=<?= rawurlencode($publicSlug) ?>" class="inline-form"
                  data-confirm="Cerrar la recepción revoca el enlace activo. ¿Continuar?">
              <?= admin_csrf_field() ?>
              <input type="hidden" name="action" value="cerrar-recepcion">
              <button type="submit" class="btn btn-ghost">Cerrar recepción</button>
            </form>
          <?php endif; ?>
        </div>
      </section>

    <?php endif; ?>
  </main>
</div>

<script>
document.addEventListener('click', function (event) {
  var copyBtn = event.target.closest('[data-copy]');
  if (copyBtn && navigator.clipboard) {
    navigator.clipboard.writeText(copyBtn.getAttribute('data-copy'));
    copyBtn.classList.add('copied');
    setTimeout(function () { copyBtn.classList.remove('copied'); }, 1200);
  }
});
document.addEventListener('submit', function (event) {
  var message = event.target.getAttribute('data-confirm');
  if (message && !window.confirm(message)) { event.preventDefault(); }
});

// Reordenar arrastrando, solo en escritorio y solo sin filtro. Es una mejora
// sobre las flechas, que siguen siendo el camino que funciona en celular, con
// teclado y con JavaScript apagado.
(function () {
  var grid = document.getElementById('curation-grid');
  var form = document.getElementById('reorder-form');
  if (!grid || !form || grid.getAttribute('data-reorder') !== '1') { return; }

  var dragged = null;

  grid.addEventListener('dragstart', function (event) {
    var tile = event.target.closest('.tile');
    if (!tile) { return; }
    dragged = tile;
    tile.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
    // Firefox no inicia el arrastre sin datos en el portapapeles del evento.
    event.dataTransfer.setData('text/plain', tile.getAttribute('data-media'));
  });

  grid.addEventListener('dragend', function () {
    if (dragged) { dragged.classList.remove('is-dragging'); }
    dragged = null;
  });

  grid.addEventListener('dragover', function (event) {
    if (!dragged) { return; }
    event.preventDefault();
    var over = event.target.closest('.tile');
    if (!over || over === dragged) { return; }
    var box = over.getBoundingClientRect();
    // Se compara contra el centro para que la tarjeta arrastrada se ubique
    // antes o después según de qué lado se soltó, no siempre antes.
    var after = (event.clientY - box.top) > box.height / 2;
    grid.insertBefore(dragged, after ? over.nextSibling : over);
  });

  grid.addEventListener('drop', function (event) {
    event.preventDefault();
    var ids = Array.prototype.map.call(grid.querySelectorAll('.tile'), function (tile) {
      return tile.getAttribute('data-media');
    });
    document.getElementById('reorder-value').value = ids.join(',');
    form.hidden = false;
    // Los números visibles se recalculan al vuelo para que el orden en
    // pantalla no mienta mientras el cambio no está guardado.
    Array.prototype.forEach.call(grid.querySelectorAll('.tile-position'), function (span, index) {
      span.textContent = index + 1;
    });
  });
})();
</script>
</body>
</html>
