<?php
/**
 * admin/index.php — Backoffice de CumpleBooth (single-file, sin CDNs).
 * Login por contraseña, CRUD de fiestas y vista de estado de temáticas.
 * Sin dependencias externas. Compatible PHP 8.0+ (baseline 8.2).
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

/** Ruta absoluta a themes/ (junto a public/, un nivel arriba de admin/). */
function admin_themes_base_dir(): string
{
    return __DIR__ . '/../themes';
}

/**
 * En desarrollo el admin se usa desde dist/. Conserva una copia en public/
 * para que el próximo build no borre el asset recién aprobado. En producción
 * (sin árbol fuente) devuelve null y el archivo queda solo en el runtime.
 */
function admin_theme_source_base_dir(): ?string
{
    $runtimeRoot = realpath(__DIR__ . '/..');
    if ($runtimeRoot === false || basename($runtimeRoot) !== 'dist') {
        return null;
    }
    $source = dirname($runtimeRoot) . '/public/themes';
    return is_dir(dirname($source)) ? $source : null;
}

function admin_sync_theme_assets_to_source(string $themeSlug, array $saved): void
{
    $sourceBase = admin_theme_source_base_dir();
    if ($sourceBase === null || !cb_valid_slug($themeSlug, 1, 40)) {
        return;
    }
    foreach ($saved as $relative) {
        $relative = (string) $relative;
        if (!cb_theme_relative_asset_safe($relative)) {
            continue;
        }
        $runtimeFile = admin_themes_base_dir() . '/' . $themeSlug . '/' . $relative;
        $sourceFile = $sourceBase . '/' . $themeSlug . '/' . $relative;
        $sourceParent = dirname($sourceFile);
        if (!is_dir($sourceParent)) {
            @mkdir($sourceParent, 0775, true);
        }
        if (is_file($runtimeFile) && is_dir($sourceParent)) {
            @copy($runtimeFile, $sourceFile);
            @chmod($sourceFile, 0664);
        }
    }
}

/** Escapa para HTML. */
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

/** URL pública base (raíz del booth, un nivel arriba de /admin). */
function admin_base_url(): string
{
    return cb_public_base_url() . '/';
}

/** Parsea el textarea de invitados: una línea "Nombre,f" o "Nombre,m", tolerante. */
function admin_parse_invitados(string $raw): array
{
    $out = [];
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = explode(',', $line);
        $name = trim($parts[0] ?? '');
        if ($name === '') {
            continue;
        }
        $gRaw = strtolower(trim($parts[1] ?? ''));
        $g = 'f';
        if (strpos($gRaw, 'm') === 0) {
            $g = 'm';
        } elseif (strpos($gRaw, 'f') === 0) {
            $g = 'f';
        }
        $out[] = ['name' => $name, 'g' => $g];
    }
    return $out;
}

function admin_invitados_to_text(array $invitados): string
{
    $lines = [];
    foreach ($invitados as $inv) {
        $lines[] = ($inv['name'] ?? '') . ',' . ($inv['g'] ?? 'f');
    }
    return implode("\n", $lines);
}

function admin_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
}

/** Iconos SVG inline (nunca emojis para botones de UI). */
function admin_icon(string $name): string
{
    $icons = [
        'edit' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>',
        'copy' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
        'external' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>',
        'duplicate' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M4 16V4a2 2 0 0 1 2-2h8"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        'warn' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><circle cx="12" cy="12" r="10"/><path d="M12 17h.01"/></svg>',
        'lock' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'party' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12v0c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 9"/><path d="m6 16 6.5-6.5"/></svg>',
        'palette' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2Z"/></svg>',
        'gallery' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>',
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
            header('Location: index.php');
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

// ================== ACCIONES (CRUD) ==================
$formErrors = [];
$formValues = null;

if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'login') {
    if (!admin_csrf_check()) {
        $formErrors[] = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $partiesData = cb_load_parties();
        $parties = $partiesData['parties'];
        $themesData = cb_load_themes();
        $themes = $themesData['themes'] ?? [];

        if ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            header('Location: index.php');
            exit;
        } elseif ($action === 'guardar') {
            $isEdit = ($_POST['modo'] ?? '') === 'editar';
            $adminLabel = trim((string) ($_POST['admin_label'] ?? ''));
            $birthdayPersonName = trim((string) ($_POST['birthday_person_name'] ?? ''));
            $tema = (string) ($_POST['tema'] ?? '');
            $fecha = trim((string) ($_POST['fecha'] ?? ''));
            $activa = isset($_POST['activa']);
            $eventType = (string) ($_POST['event_type'] ?? '') === 'baby_shower' ? 'baby_shower' : 'child_birthday';
            $servicePlan = in_array((string) ($_POST['service_plan'] ?? ''), ['booth', 'full'], true) ? (string) $_POST['service_plan'] : 'booth';
            $galleryEnabled = isset($_POST['gallery_enabled']);
            // Juegos habilitados para esta fiesta. `juegos_definidos` distingue
            // "no marcó ninguno" (esta fiesta no juega) de "nunca abrió esta
            // opción" (juega la cadena completa, como siempre).
            $juegos = isset($_POST['juegos_definidos'])
                ? cb_sanitize_party_games((array) ($_POST['juegos'] ?? []))
                : null;
            $invitadosRaw = (string) ($_POST['invitados'] ?? '');
            $invitados = admin_parse_invitados($invitadosRaw);
            $galeriaPin = trim((string) ($_POST['galeriaPin'] ?? ''));
            $clearPin = isset($_POST['clear_pin']);
            $frameReset = isset($_POST['frame_reset']);
            $frameCandidate = [
                'x' => $_POST['frame_x'] ?? null, 'y' => $_POST['frame_y'] ?? null,
                'w' => $_POST['frame_w'] ?? null, 'h' => $_POST['frame_h'] ?? null,
            ];
            $frameBox = $frameReset ? null : cb_normalize_frame_box($frameCandidate);

            $errs = [];
            if ($adminLabel === '') {
                $errs[] = 'La etiqueta interna (admin) es obligatoria.';
            }
            if ($birthdayPersonName === '') {
                $errs[] = 'El nombre del cumpleañero/a es obligatorio.';
            }
            if (!isset($themes[$tema])) {
                $errs[] = 'Selecciona una temática válida.';
            }
            if ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                $errs[] = 'La fecha debe tener formato AAAA-MM-DD.';
            }
            if ($galleryEnabled && $servicePlan !== 'full') {
                $errs[] = 'La galería solo puede habilitarse con el plan Full.';
            }
            if ($galleryEnabled && $galeriaPin === '' && (!$isEdit || empty($parties[$origPublicSlug]['galeriaHabilitada']))) {
                $errs[] = 'Para habilitar la galería debes configurar un PIN de 4 dígitos.';
            }
            if ($galeriaPin !== '' && !cb_valid_galeria_pin($galeriaPin)) {
                $errs[] = 'El PIN de galería debe ser vacío o exactamente 4 dígitos.';
            }
            if (!$frameReset && $frameBox === null) {
                $errs[] = 'La calibración del marco debe usar valores 0..1 y quedar dentro del lienzo.';
            }

            $publicSlug = '';
            $origPublicSlug = '';
            if ($isEdit) {
                $origPublicSlug = cb_valid_public_slug((string) ($_POST['slug_original'] ?? '')) ? (string) $_POST['slug_original'] : '';
                if (!isset($parties[$origPublicSlug])) {
                    $errs[] = 'La fiesta original ya no existe.';
                }
                $publicSlug = $origPublicSlug;
            } else {
                try {
                    $pdo = cb_pdo();
                    $publicSlug = cb_generate_public_slug($pdo, $birthdayPersonName, cb_theme_public_name($tema));
                } catch (Throwable $e) {
                    error_log('CumpleClick public_slug generation: ' . $e->getMessage());
                    $errs[] = 'No se pudo generar el identificador público único. Reintenta.';
                }
            }

            if (empty($errs)) {
                $registro = [
                    'admin_label'          => $adminLabel,
                    'birthday_person_name' => $birthdayPersonName,
                    'nombre'               => $birthdayPersonName,
                    'event_type'           => $eventType,
                    'theme_slug'           => $tema,
                    'tema'                 => $tema,
                    'public_slug'          => $publicSlug,
                    'fecha'                => $fecha,
                    'activa'               => $activa,
                    'service_plan'         => $servicePlan,
                    'gallery_enabled'      => $galleryEnabled,
                    'juegos'               => $juegos,
                    'invitados'            => $invitados,
                    'frameBox'             => $frameBox,
                    'creada'               => $isEdit ? ($parties[$origPublicSlug]['creada'] ?? gmdate('Y-m-d H:i:s')) : gmdate('Y-m-d H:i:s'),
                ];
                if ($galeriaPin !== '') {
                    $registro['galeriaPin'] = $galeriaPin;
                } elseif ($isEdit && !$clearPin) {
                    $registro['galeriaPinHash'] = (string) ($parties[$origPublicSlug]['galeriaPinHash'] ?? '');
                    $registro['galeriaPinHmac'] = (string) ($parties[$origPublicSlug]['galeriaPinHmac'] ?? '');
                }
                $parties[$publicSlug] = $registro;
                if (cb_save_parties(['parties' => $parties])) {
                    header('Location: index.php?ok=' . ($isEdit ? 'editada' : 'creada'));
                    exit;
                }
                $errs[] = 'No se pudo guardar (revisa permisos/errores de BD). Consula los logs del servidor.';
            }

            $formErrors = $errs;
            $formValues = [
                'modo' => $isEdit ? 'editar' : 'nueva',
                'public_slug' => $publicSlug,
                'admin_label' => $adminLabel,
                'birthday_person_name' => $birthdayPersonName,
                'event_type' => $eventType,
                'tema' => $tema,
                'fecha' => $fecha,
                'activa' => $activa,
                'service_plan' => $servicePlan,
                'gallery_enabled' => $galleryEnabled,
                'juegos' => $juegos,
                'invitados_text' => $invitadosRaw,
                'galeriaPin' => $galeriaPin,
                'pin_configured' => $isEdit && !empty($parties[$origPublicSlug]['galeriaHabilitada']),
                'frameBox' => $frameBox,
            ];
        } elseif ($action === 'eliminar') {
            $publicSlug = cb_valid_public_slug((string) ($_POST['slug'] ?? '')) ? (string) $_POST['slug'] : '';
            if (isset($parties[$publicSlug])) {
                unset($parties[$publicSlug]);
                cb_save_parties(['parties' => $parties]);
            }
            header('Location: index.php?ok=eliminada');
            exit;
        } elseif ($action === 'duplicar') {
            $publicSlug = cb_valid_public_slug((string) ($_POST['slug'] ?? '')) ? (string) $_POST['slug'] : '';
            if (isset($parties[$publicSlug])) {
                $source = $parties[$publicSlug];
                $newName = ($source['birthday_person_name'] ?? 'Fiesta') . ' (copia)';
                try {
                    $pdo = cb_pdo();
                    $newPublicSlug = cb_generate_public_slug($pdo, $newName, cb_theme_public_name((string) ($source['tema'] ?? '')));
                } catch (Throwable $e) {
                    error_log('CumpleClick duplicate public_slug: ' . $e->getMessage());
                    $newPublicSlug = '';
                }
                if ($newPublicSlug !== '') {
                    $parties[$newPublicSlug] = [
                        'public_slug'          => $newPublicSlug,
                        'admin_label'          => ($source['admin_label'] ?? '') . ' (copia)',
                        'birthday_person_name' => $newName,
                        'nombre'               => $newName,
                        'theme_slug'           => (string) ($source['tema'] ?? ''),
                        'tema'                 => (string) ($source['tema'] ?? ''),
                        'fecha'                => '',
                        'activa'               => false,
                        'service_plan'         => 'booth',
                        'gallery_enabled'      => false,
                        'invitados'            => [],
                        'frameBox'             => cb_normalize_frame_box($source['frameBox'] ?? null),
                        'creada'               => gmdate('Y-m-d H:i:s'),
                    ];
                    cb_save_parties(['parties' => $parties]);
                }
            }
            header('Location: index.php?ok=duplicada');
            exit;
        } elseif ($action === 'subir_tema') {
            // Subida de archivos (fotos/audio/video) por temática, sin depender de FTP.
            $tslug = cb_sanitize_slug((string) ($_POST['tema_slug'] ?? ''));
            if (!isset($themes[$tslug]) || !is_array($themes[$tslug])) {
                $_SESSION['upload_flash'] = ['tema' => $tslug, 'saved' => [], 'rejected' => [['name' => '(temática)', 'reason' => 'temática inválida']]];
            } else {
                $rate = cb_rate_limit('admin-theme-upload:' . $tslug, cb_request_identity(), 30, 600, 600);
                if (!$rate['allowed']) {
                    $res = ['saved' => [], 'rejected' => [['name' => '(subida)', 'reason' => 'demasiadas subidas; reintenta en ' . (int) $rate['retry_after'] . ' segundos']]];
                } else {
                    $assetKey = is_string($_POST['asset_key'] ?? null) ? (string) $_POST['asset_key'] : '';
                    $uploadFiles = isset($_FILES['archivo']) ? $_FILES['archivo'] : ($_FILES['archivos'] ?? []);
                    $res = cb_process_theme_uploads(
                        $tslug,
                        $themes[$tslug],
                        admin_themes_base_dir(),
                        $uploadFiles,
                        $assetKey
                    );
                }
                admin_sync_theme_assets_to_source($tslug, $res['saved']);
                $_SESSION['upload_flash'] = ['tema' => $tslug, 'saved' => $res['saved'], 'rejected' => $res['rejected']];
            }
            $returnView = ($_POST['return_view'] ?? '') === 'tema' ? 'tema' : 'temas';
            $location = $returnView === 'tema' && isset($themes[$tslug])
                ? 'index.php?view=tema&slug=' . rawurlencode($tslug)
                : 'index.php?view=temas';
            header('Location: ' . $location);
            exit;
        } elseif ($action === 'guardar_prompt') {
            $tslugRaw = is_string($_POST['tema_slug'] ?? null) ? (string) $_POST['tema_slug'] : '';
            $assetKey = is_string($_POST['asset_key'] ?? null) ? (string) $_POST['asset_key'] : '';
            $promptText = is_string($_POST['prompt_text'] ?? null) ? (string) $_POST['prompt_text'] : '';
            $tslug = cb_valid_slug($tslugRaw, 1, 40) ? $tslugRaw : '';
            $flash = ['ok' => false, 'message' => 'No se pudo guardar el prompt.'];
            if ($tslug === '' || !isset($themes[$tslug]) || !is_array($themes[$tslug])) {
                $flash['message'] = 'La temática no es válida.';
            } else {
                try {
                    $result = cb_save_theme_prompt($tslug, $themes[$tslug], $assetKey, $promptText);
                    $flash = $result['ok']
                        ? ['ok' => true, 'message' => !empty($result['deleted']) ? 'Prompt eliminado.' : 'Prompt guardado en la BD.']
                        : ['ok' => false, 'message' => (string) $result['error']];
                } catch (Throwable $e) {
                    error_log('CumpleClick admin prompt: ' . $e->getMessage());
                    $flash['message'] = 'No se pudo acceder al almacenamiento de prompts. Ejecuta las migraciones y reintenta.';
                }
            }
            $_SESSION['prompt_flash'] = $flash;
            header('Location: index.php?view=tema&slug=' . rawurlencode($tslug));
            exit;
        }
    }
}

// ================== VISTA: LOGIN ==================
if (!$loggedIn) {
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleClick Admin · Ingresar</title>
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
    <?php if ($loginError !== ''): ?>
      <p class="alert alert-error"><?= admin_icon('warn') ?> <?= h($loginError) ?></p>
    <?php endif; ?>
    <form method="post" action="index.php" class="login-form">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="login">
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

// ================== DATOS PARA RENDER ==================
$partiesData = cb_load_parties();
$parties = $partiesData['parties'];
$themesData = cb_load_themes();
$themes = $themesData['themes'] ?? [];
$themesBaseDir = admin_themes_base_dir();

$themeStatus = [];
foreach ($themes as $tslug => $tdata) {
    $themeStatus[$tslug] = cb_theme_files_status($tslug, $tdata, $themesBaseDir);
}

// Flash de resultado de subida de archivos (guardados/rechazados), one-shot.
$uploadFlash = $_SESSION['upload_flash'] ?? null;
unset($_SESSION['upload_flash']);
$promptFlash = $_SESSION['prompt_flash'] ?? null;
unset($_SESSION['prompt_flash']);

$kpiActivas = 0;
foreach ($parties as $p) {
    if (!empty($p['activa'])) {
        $kpiActivas++;
    }
}
$kpiListas = 0;
foreach ($themeStatus as $st) {
    if ($st['ready']) {
        $kpiListas++;
    }
}

$okMessages = [
    'creada' => 'Fiesta creada correctamente.',
    'editada' => 'Fiesta actualizada correctamente.',
    'eliminada' => 'Fiesta eliminada.',
    'duplicada' => 'Fiesta duplicada (queda inactiva por defecto).',
];
$okFlash = isset($_GET['ok'], $okMessages[$_GET['ok']]) ? $okMessages[$_GET['ok']] : null;

$view = $_GET['view'] ?? 'fiestas';
$action = $_GET['action'] ?? '';
$baseUrl = admin_base_url();
$detailThemeSlugRaw = is_string($_GET['slug'] ?? null) ? (string) $_GET['slug'] : '';
$detailThemeSlug = cb_valid_slug($detailThemeSlugRaw, 1, 40) && isset($themes[$detailThemeSlugRaw])
    ? $detailThemeSlugRaw
    : '';
$detailTheme = $detailThemeSlug !== '' && is_array($themes[$detailThemeSlug] ?? null) ? $themes[$detailThemeSlug] : null;
$detailInventory = [];
$detailPrompts = [];
if ($view === 'tema' && $detailTheme !== null) {
    $detailInventory = cb_theme_asset_inventory($detailThemeSlug, $detailTheme, $themesBaseDir);
    try {
        $detailPrompts = cb_load_theme_prompts($detailThemeSlug, $detailTheme);
    } catch (Throwable $e) {
        error_log('CumpleClick load theme prompts: ' . $e->getMessage());
        $formErrors[] = 'No se pudieron cargar los prompts. Ejecuta las migraciones de BD.';
    }
}

// Determina si hay que mostrar el formulario de fiesta (crear/editar)
$showForm = $formValues !== null || in_array($action, ['nueva', 'editar'], true);
if ($formValues === null && $action === 'editar') {
    $editSlug = cb_valid_public_slug((string) ($_GET['slug'] ?? '')) ? (string) $_GET['slug'] : '';
    if (isset($parties[$editSlug])) {
        $p = $parties[$editSlug];
        $formValues = [
            'modo' => 'editar',
            'public_slug' => $editSlug,
            'admin_label' => $p['admin_label'] ?? '',
            'birthday_person_name' => $p['birthday_person_name'] ?? '',
            'event_type' => (string) ($p['event_type'] ?? '') === 'baby_shower' ? 'baby_shower' : 'child_birthday',
            'tema' => $p['tema'] ?? '',
            'fecha' => $p['fecha'] ?? '',
            'activa' => !empty($p['activa']),
            'service_plan' => in_array((string) ($p['service_plan'] ?? ''), ['booth', 'full'], true) ? (string) $p['service_plan'] : 'booth',
            'gallery_enabled' => !empty($p['gallery_enabled']),
            'juegos' => cb_sanitize_party_games($p['juegos'] ?? null),
            'invitados_text' => admin_invitados_to_text($p['invitados'] ?? []),
            'galeriaPin' => '',
            'pin_configured' => !empty($p['galeriaHabilitada']),
            'frameBox' => cb_normalize_frame_box($p['frameBox'] ?? null),
        ];
    } else {
        $showForm = false;
    }
} elseif ($formValues === null && $action === 'nueva') {
    $formValues = [
        'modo' => 'nueva',
        'public_slug' => '',
        'admin_label' => '',
        'birthday_person_name' => '',
        'event_type' => 'child_birthday',
        'tema' => array_key_first($themes) ?? '',
        'fecha' => '',
        'activa' => true,
        'service_plan' => 'booth',
        'gallery_enabled' => false,
        'juegos' => null,
        'invitados_text' => '',
        'galeriaPin' => '',
        'pin_configured' => false,
        'frameBox' => ['x' => 0.32, 'y' => 0.30, 'w' => 0.36, 'h' => 0.28],
    ];
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleClick Admin</title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>
</style>
</head>
<body>
<div class="wrap">
  <header class="topbar">
    <div class="logo">
      <img src="../brand/cumpleclick-mark.svg" alt="CumpleClick" width="36" height="36" style="display:block">
      CumpleClick <span>Admin</span>
    </div>
    <div class="kpis">
      <div class="kpi-card">
        <span class="kpi-icon"><?= admin_icon('party') ?></span>
        <div class="kpi-text"><strong><?= (int) $kpiActivas ?></strong><span>fiestas activas</span></div>
      </div>
      <div class="kpi-card">
        <span class="kpi-icon"><?= admin_icon('palette') ?></span>
        <div class="kpi-text"><strong><?= (int) $kpiListas ?>/<?= count($themes) ?></strong><span>temáticas listas</span></div>
      </div>
    </div>
    <div class="inline-form logout-btn">
      <a class="btn btn-ghost" href="marca.php">Datos de la marca</a>
      <form method="post" action="index.php" class="inline-form">
        <?= admin_csrf_field() ?><input type="hidden" name="action" value="logout">
        <button class="btn btn-ghost" type="submit"><?= admin_icon('logout') ?> Salir</button>
      </form>
    </div>
  </header>

  <nav class="tabs">
    <a class="tab <?= (!in_array($view, ['temas', 'tema'], true) && !$showForm) ? 'active' : '' ?>" href="index.php"><?= admin_icon('party') ?> Fiestas</a>
    <a class="tab <?= (in_array($view, ['temas', 'tema'], true) && !$showForm) ? 'active' : '' ?>" href="index.php?view=temas"><?= admin_icon('palette') ?> Temáticas</a>
    <?php
      /* Cuántas solicitudes sin atender. Va en la pestaña a propósito: si el
         número no se ve desde acá, hay que acordarse de entrar a mirar, y una
         solicitud que nadie mira es un cliente perdido. Envuelto en try porque
         `cc_leads` puede no existir todavía en una instalación vieja; en ese
         caso la pestaña aparece igual, sólo que sin el número. */
      $leadsNuevos = 0;
      if (cb_storage_mode() === 'db') {
          try {
              $leadsNuevos = (int) cb_pdo()->query("SELECT COUNT(*) FROM cc_leads WHERE status = 'new'")->fetchColumn();
          } catch (Throwable $e) {
              $leadsNuevos = 0;
          }
      }
    ?>
    <a class="tab" href="leads.php"><?= admin_icon('party') ?> Solicitudes<?= $leadsNuevos > 0 ? ' <b class="tab-badge">' . (int) $leadsNuevos . '</b>' : '' ?></a>
  </nav>

  <main>
    <?php if ($okFlash): ?>
      <p class="alert alert-ok"><?= admin_icon('check') ?> <?= h($okFlash) ?></p>
    <?php endif; ?>
    <?php if (!empty($formErrors)): ?>
      <div class="alert alert-error">
        <?= admin_icon('warn') ?>
        <ul><?php foreach ($formErrors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <?php if ($showForm && $formValues): ?>
      <?php
      $isEdit = $formValues['modo'] === 'editar';
      $selectedTheme = $themes[$formValues['tema']] ?? [];
      $formBox = cb_normalize_frame_box($formValues['frameBox'] ?? null)
          ?? cb_normalize_frame_box($selectedTheme['frameBox'] ?? null)
          ?? ['x' => 0.32, 'y' => 0.30, 'w' => 0.36, 'h' => 0.28];
      ?>
      <section class="card form-card">
        <h2><?= $isEdit ? 'Editar fiesta' : 'Nueva fiesta' ?></h2>
        <form method="post" action="index.php" class="party-form">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="action" value="guardar">
          <input type="hidden" name="modo" value="<?= $isEdit ? 'editar' : 'nueva' ?>">
          <?php if ($isEdit): ?>
            <input type="hidden" name="slug_original" value="<?= h($formValues['public_slug']) ?>">
          <?php endif; ?>

          <div class="field">
            <label for="f-admin-label">Etiqueta interna (admin)</label>
            <input type="text" id="f-admin-label" name="admin_label" required maxlength="60" value="<?= h($formValues['admin_label']) ?>" placeholder="Ej. DEMO-BLUEY">
            <small>Nombre corto para identificar la fiesta en el backoffice.</small>
          </div>

          <div class="field">
            <label for="f-birthday-name">Nombre del cumpleañero/a</label>
            <input type="text" id="f-birthday-name" name="birthday_person_name" required maxlength="60" value="<?= h($formValues['birthday_person_name']) ?>" placeholder="Ej. Valentina">
          </div>

          <div class="field">
            <label for="f-event-type">Modalidad del evento</label>
            <select id="f-event-type" name="event_type" required>
              <option value="child_birthday" <?= ($formValues['event_type'] ?? 'child_birthday') === 'child_birthday' ? 'selected' : '' ?>>Cumpleaños infantil</option>
              <option value="baby_shower" <?= ($formValues['event_type'] ?? '') === 'baby_shower' ? 'selected' : '' ?>>Baby shower</option>
            </select>
            <small>La modalidad controla el recorrido y el vocabulario. Los eventos actuales permanecen como cumpleaños infantil.</small>
          </div>

          <?php if ($isEdit): ?>
            <div class="field">
              <label for="f-public-slug">Slug público (URL)</label>
              <input type="text" id="f-public-slug" value="<?= h($formValues['public_slug']) ?>" readonly>
              <small>Se genera en el servidor, es inmutable y no se puede editar.</small>
            </div>
          <?php else: ?>
            <p class="muted small">El identificador público de la fiesta se generará automáticamente al guardar.</p>
          <?php endif; ?>

          <div class="field">
            <label for="f-tema">Temática</label>
            <select id="f-tema" name="tema" required <?= $isEdit ? 'disabled' : '' ?>>
              <?php foreach ($themes as $tslug => $tdata): ?>
                <?php $st = $themeStatus[$tslug]; ?>
                <?php
                $franq = trim((string) ($tdata['franquicia'] ?? ''));
                $optLabel = $franq !== '' ? $franq . ' (' . ($tdata['nombre'] ?? $tslug) . ')' : ($tdata['nombre'] ?? $tslug);
                ?>
                <option value="<?= h($tslug) ?>" <?= $formValues['tema'] === $tslug ? 'selected' : '' ?>>
                  <?= h($optLabel) ?> — <?= $st['ready'] ? 'Lista' : ('Faltan ' . count($st['missing']) . ' archivos') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($isEdit): ?><input type="hidden" name="tema" value="<?= h($formValues['tema']) ?>"><?php endif; ?>
          </div>

          <div class="field">
            <label for="f-fecha">Fecha</label>
            <input type="date" id="f-fecha" name="fecha" value="<?= h($formValues['fecha']) ?>">
          </div>

          <div class="field">
            <label>Plan de servicio</label>
            <label class="checkbox-field">
              <input type="radio" name="service_plan" value="booth" <?= $formValues['service_plan'] === 'booth' ? 'checked' : '' ?>>
              Booth — solo cabina de fotos
            </label>
            <label class="checkbox-field">
              <input type="radio" name="service_plan" value="full" <?= $formValues['service_plan'] === 'full' ? 'checked' : '' ?>>
              Full — cabina + galería de papás + invitaciones
            </label>
            <small>El plan Full suma automáticamente la misión WOW 3D exclusiva de la temática. No se habilita manualmente ni aparece en Booth.</small>
          </div>

          <div class="field">
            <label class="checkbox-field">
              <input type="checkbox" name="gallery_enabled" <?= !empty($formValues['gallery_enabled']) ? 'checked' : '' ?>>
              Habilitar galería de papás (requiere plan Full y PIN)
            </label>
          </div>

          <?php
          // Juegos de la temática elegida. Se listan solo los tipos que esa
          // temática realmente ofrece: mostrar los cinco siempre confundiría
          // (Bluey no tiene "armar muñeco", por ejemplo).
          $temaForm = (string) ($formValues['tema'] ?? '');
          $kindsTema = isset($themes[$temaForm]) ? cb_theme_available_game_kinds($themes[$temaForm]) : [];
          $juegosSel = $formValues['juegos'] ?? null;
          $etiquetaKind = [
            'fichas' => 'Rompecabezas',
            'copos' => 'Atrapar (copos / estrellas / rayos)',
            'armar-muneco' => 'Armar el muñeco (arrastrar piezas)',
            'ritmo' => 'Seguir el ritmo (carriles)',
            'escudo' => 'Atrapar al vuelo (objetivo móvil)',
          ];
          ?>
          <?php if ($kindsTema): ?>
          <div class="field">
            <label>Juegos habilitados en esta fiesta</label>
            <label class="checkbox-field">
              <input type="checkbox" name="juegos_definidos" value="1" <?= $juegosSel !== null ? 'checked' : '' ?>>
              <strong>Elegir yo qué juegos van</strong>
            </label>
            <small>Sin marcar esto, la fiesta juega la cadena normal completa. Si eliges juegos, solo se conservan los marcados; en plan Full la misión WOW 3D se agrega siempre como bonus final.</small>
            <div style="margin-top:8px;padding-left:6px">
              <?php foreach ($kindsTema as $k): ?>
                <label class="checkbox-field">
                  <input type="checkbox" name="juegos[]" value="<?= h($k) ?>"
                    <?= ($juegosSel !== null && in_array($k, $juegosSel, true)) ? 'checked' : '' ?>>
                  <?= h($etiquetaKind[$k] ?? $k) ?>
                </label>
              <?php endforeach; ?>
            </div>
            <small>Solo aparecen los juegos que ofrece <strong><?= h($themes[$temaForm]['nombre'] ?? $temaForm) ?></strong>. Si cambias de temática, guarda y vuelve a entrar para ver los suyos.</small>
          </div>
          <?php endif; ?>

          <div class="field">
            <label for="f-galeriapin">PIN de galería de papás (opcional, 4 dígitos)</label>
            <input type="text" id="f-galeriapin" name="galeriaPin" maxlength="4" pattern="\d{4}" inputmode="numeric"
              value="<?= h($formValues['galeriaPin']) ?>" placeholder="1234">
            <small><?= !empty($formValues['pin_configured']) ? 'Hay un PIN configurado. Déjalo vacío para conservarlo.' : 'Define un PIN para habilitar la galería.' ?> El valor se guarda con password_hash, nunca en claro.</small>
            <?php if (!empty($formValues['pin_configured'])): ?><label class="checkbox-field"><input type="checkbox" name="clear_pin"> Deshabilitar y borrar el PIN actual</label><?php endif; ?>
          </div>

          <div class="field">
            <label for="f-invitados">Invitados (uno por línea: <code>Nombre,f</code> o <code>Nombre,m</code>)</label>
            <textarea id="f-invitados" name="invitados" rows="6" placeholder="Ana,f&#10;Luis,m"><?= h($formValues['invitados_text']) ?></textarea>
          </div>

          <fieldset class="field frame-calibrator">
            <legend>Calibrador del marco de cámara</legend>
            <p class="muted small">Ajusta el marco decorativo sobre el fondo. La zona naranja muestra la foto cuadrada final, centrada y con margen para no tapar el borde dorado.</p>
            <div class="frame-preview" style="background-image:url('../themes/<?= h($formValues['tema']) ?>/fondo-sala.jpg')"><span id="frame-overlay"></span></div>
            <div class="frame-grid">
              <?php foreach (['x' => 'X', 'y' => 'Y', 'w' => 'Ancho', 'h' => 'Alto'] as $key => $label): ?>
                <label><?= h($label) ?><input class="frame-value" data-frame="<?= h($key) ?>" type="number" name="frame_<?= h($key) ?>" min="<?= in_array($key, ['w', 'h'], true) ? '0.05' : '0' ?>" max="1" step="0.001" value="<?= h($formBox[$key]) ?>" required></label>
              <?php endforeach; ?>
            </div>
            <label class="checkbox-field"><input type="checkbox" name="frame_reset"> Usar calibración predeterminada de la temática</label>
          </fieldset>

          <label class="checkbox-field">
            <input type="checkbox" name="activa" <?= !empty($formValues['activa']) ? 'checked' : '' ?>>
            Fiesta activa (accesible por su URL)
          </label>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a class="btn btn-ghost" href="index.php">Cancelar</a>
          </div>
        </form>
      </section>

    <?php elseif ($view === 'tema'): ?>
      <?php if ($detailTheme === null): ?>
        <section class="card empty-state">
          <p><strong>Temática no encontrada.</strong></p>
          <a class="btn btn-ghost" href="index.php?view=temas">Volver a temáticas</a>
        </section>
      <?php else: ?>
        <?php
        $detailStatus = $themeStatus[$detailThemeSlug];
        $detailAccent = (string) ($detailTheme['colors']['accent'] ?? '#7C3AED');
        $detailFranchise = trim((string) ($detailTheme['franquicia'] ?? ''));
        $loadedAssets = count(array_filter($detailInventory, static function (array $asset): bool { return $asset['exists']; }));
        $promptableAssets = count(array_filter($detailInventory, static function (array $asset): bool { return !empty($asset['promptable']); }));
        ?>
        <section class="theme-detail-head" style="--chip-color: <?= h($detailAccent) ?>">
          <div>
            <a class="detail-back" href="index.php?view=temas">← Volver a temáticas</a>
            <h2><?= h($detailFranchise !== '' ? $detailFranchise : ($detailTheme['nombre'] ?? $detailThemeSlug)) ?></h2>
            <?php if ($detailFranchise !== ''): ?><p class="muted"><?= h($detailTheme['nombre'] ?? $detailThemeSlug) ?></p><?php endif; ?>
          </div>
          <span class="theme-slug"><?= h($detailThemeSlug) ?></span>
        </section>

        <?php if ($promptFlash): ?>
          <p class="alert <?= !empty($promptFlash['ok']) ? 'alert-ok' : 'alert-error' ?>">
            <?= admin_icon(!empty($promptFlash['ok']) ? 'check' : 'warn') ?> <?= h($promptFlash['message'] ?? '') ?>
          </p>
        <?php endif; ?>
        <?php if ($uploadFlash && ($uploadFlash['tema'] ?? '') === $detailThemeSlug): ?>
          <div class="alert <?= empty($uploadFlash['rejected']) ? 'alert-ok' : 'alert-error' ?>">
            <?= admin_icon(empty($uploadFlash['rejected']) ? 'check' : 'warn') ?>
            <div><strong>Resultado de la subida:</strong><ul>
              <?php foreach ($uploadFlash['saved'] as $savedName): ?><li>Guardado: <code><?= h($savedName) ?></code></li><?php endforeach; ?>
              <?php foreach ($uploadFlash['rejected'] as $rejected): ?><li>Rechazado <code><?= h($rejected['name']) ?></code>: <?= h($rejected['reason']) ?></li><?php endforeach; ?>
              <?php if (empty($uploadFlash['saved']) && empty($uploadFlash['rejected'])): ?><li>No se seleccionó ningún archivo.</li><?php endif; ?>
            </ul></div>
          </div>
        <?php endif; ?>

        <section class="detail-stats" aria-label="Resumen de la temática">
          <div class="detail-stat"><strong><?= $detailStatus['ready'] ? 'Lista' : 'Incompleta' ?></strong><span><?= $detailStatus['ready'] ? 'assets requeridos completos' : count($detailStatus['missing']) . ' requeridos pendientes' ?></span></div>
          <div class="detail-stat"><strong><?= (int) $loadedAssets ?>/<?= count($detailInventory) ?></strong><span>archivos cargados</span></div>
          <div class="detail-stat"><strong><?= count($detailPrompts) ?>/<?= (int) $promptableAssets ?></strong><span>prompts asociados</span></div>
          <div class="detail-stat"><strong><?= h($detailTheme['publico'] ?? '—') ?></strong><span>público objetivo</span></div>
        </section>

        <section class="card camouflage-note">
          <?= admin_icon('lock') ?>
          <div><strong>Camuflaje obligatorio para IA visual</strong><p>Aplica al copiar en Higgsfield, Gemini u otra herramienta: describe solo rasgos físicos y una creación original. Nunca escribas nombres de franquicia ni de personaje. El backend bloquea esos nombres antes de guardar.</p></div>
        </section>

        <section class="card production-studio" aria-labelledby="studio-title">
          <div>
            <span class="eyebrow">ESTUDIO MANUAL</span>
            <h2 id="studio-title">Del prompt aprobado al asset publicado</h2>
            <p>Los prompts son privados y versionados. Genera fuera de CumpleClick y vuelve aquí para adjuntar el resultado al destino exacto.</p>
          </div>
          <ol class="production-steps">
            <li><strong>1</strong><span>Revisar y copiar<br>el prompt</span></li>
            <li><strong>2</strong><span>Generar manualmente<br>imagen o video</span></li>
            <li><strong>3</strong><span>Adjuntar al slot<br>correspondiente</span></li>
            <li><strong>4</strong><span>Previsualizar y<br>probar la demo</span></li>
          </ol>
        </section>

        <?php
        // El estado de ffprobe decide si los .mp4 se pueden subir. Sin este
        // aviso, en un hosting sin ffprobe cada video se rechaza y no hay forma
        // de saber por qué desde la interfaz.
        $ffprobeRuta = (string) cb_config('ffprobe_path');
        $ffprobeOk = $ffprobeRuta !== '' && is_file($ffprobeRuta);
        $videoSinValidar = !$ffprobeOk && (bool) cb_config('allow_video_upload_without_ffprobe');
        ?>
        <?php if (!$ffprobeOk): ?>
          <div class="alert <?= $videoSinValidar ? 'alert-ok' : 'alert-error' ?>">
            <?= admin_icon('warn') ?>
            <div>
              <?php if ($videoSinValidar): ?>
                <strong>Videos sin validar.</strong> Este servidor no tiene ffprobe, así que los
                <code>.mp4</code> se aceptan sin comprobar codec, duración ni dimensiones.
                Súbelos ya convertidos a H.264 y revísalos en la tablet después.
              <?php else: ?>
                <strong>No se pueden subir videos.</strong> Este servidor no tiene ffprobe
                configurado y la validación de <code>.mp4</code> falla cerrada. Las imágenes y los
                MP3 sí funcionan. Para habilitar videos: configura <code>ffprobe_path</code>, o
                activa <code>allow_video_upload_without_ffprobe</code> asumiendo que se sube sin
                validar.
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
        <section class="card">
          <div class="detail-section-head">
            <div><h2>Producción por assets</h2><p class="muted">Revisa, genera y adjunta imágenes o videos. Los audios se cargan sin prompt generativo.</p></div>
            <form method="post" action="index.php?view=tema&amp;slug=<?= rawurlencode($detailThemeSlug) ?>" enctype="multipart/form-data" class="detail-upload-form">
              <?= admin_csrf_field() ?>
              <input type="hidden" name="action" value="subir_tema">
              <input type="hidden" name="tema_slug" value="<?= h($detailThemeSlug) ?>">
              <input type="hidden" name="return_view" value="tema">
              <input type="file" name="archivos[]" multiple accept=".jpg,.jpeg,.png,.mp3,.mp4" aria-label="Subir archivos para <?= h($detailTheme['nombre'] ?? $detailThemeSlug) ?>">
              <button type="submit" class="btn btn-ghost btn-sm"><?= admin_icon('plus') ?> Subir archivos</button>
            </form>
          </div>

          <div class="asset-filters" aria-label="Filtrar assets">
            <button type="button" class="asset-filter active" data-asset-filter="all">Todos</button>
            <button type="button" class="asset-filter" data-asset-filter="fondos">Fondos</button>
            <button type="button" class="asset-filter" data-asset-filter="personajes">Personajes</button>
            <button type="button" class="asset-filter" data-asset-filter="juegos">Juegos</button>
            <button type="button" class="asset-filter" data-asset-filter="marketing">Marketing y video</button>
            <button type="button" class="asset-filter" data-asset-filter="audio">Audio</button>
          </div>

          <div class="asset-detail-grid">
            <?php foreach ($detailInventory as $asset): ?>
              <?php
              $isVisual = in_array($asset['kind'], ['image', 'png'], true);
              $isPromptable = !empty($asset['promptable']);
              $prompt = $detailPrompts[$asset['name']] ?? null;
              $promptId = 'prompt-' . preg_replace('/[^a-z0-9]+/i', '-', $asset['name']);
              $acceptByKind = $asset['kind'] === 'video'
                  ? '.mp4'
                  : ($asset['kind'] === 'audio' ? '.mp3' : ($asset['kind'] === 'png' ? '.png' : '.jpg,.jpeg,.png'));
              ?>
              <article class="asset-card <?= $asset['exists'] ? 'asset-loaded' : 'asset-missing' ?>" data-asset-group="<?= h($asset['group'] ?? 'otros') ?>">
                <div class="asset-preview">
                  <?php if ($asset['exists'] && $isVisual): ?>
                    <img src="<?= h($asset['preview_url']) ?>" alt="Vista previa de <?= h($asset['name']) ?>" loading="lazy">
                  <?php elseif ($asset['exists'] && $asset['kind'] === 'video'): ?>
                    <video src="<?= h($asset['preview_url']) ?>" controls preload="metadata"></video>
                  <?php elseif ($asset['exists'] && $asset['kind'] === 'audio'): ?>
                    <div class="asset-media-placeholder"><?= admin_icon('gallery') ?><span>Audio cargado</span></div>
                    <audio src="<?= h($asset['preview_url']) ?>" controls preload="none"></audio>
                  <?php else: ?>
                    <div class="asset-media-placeholder"><?= admin_icon('gallery') ?><span>Sin archivo</span></div>
                  <?php endif; ?>
                </div>
                <div class="asset-card-body">
                  <div class="asset-title-row">
                    <div><span class="asset-group-label"><?= h(strtoupper((string) ($asset['group'] ?? 'asset'))) ?></span><code><?= h($asset['name']) ?></code></div>
                    <span class="badge <?= $asset['exists'] ? 'badge-ok' : ($asset['required'] ? 'badge-warn' : 'badge-off') ?>"><?= $asset['exists'] ? 'Cargado' : ($asset['required'] ? 'Falta' : 'Opcional') ?></span>
                  </div>
                  <p class="asset-human-label"><?= h($asset['label'] ?? $asset['name']) ?></p>
                  <dl class="asset-meta-list">
                    <div><dt>Tipo</dt><dd><?= h(strtoupper($asset['kind'])) ?></dd></div>
                    <div><dt>Peso</dt><dd><?= $asset['exists'] ? h(admin_format_bytes((int) $asset['bytes'])) : '—' ?></dd></div>
                    <div><dt>Dimensiones</dt><dd><?= $asset['width'] && $asset['height'] ? ((int) $asset['width'] . '×' . (int) $asset['height']) : '—' ?></dd></div>
                    <div><dt>Actualizado</dt><dd><?= h($asset['modified_at'] ?: '—') ?></dd></div>
                  </dl>

                  <form method="post" action="index.php?view=tema&amp;slug=<?= rawurlencode($detailThemeSlug) ?>" enctype="multipart/form-data" class="asset-slot-upload">
                    <?= admin_csrf_field() ?>
                    <input type="hidden" name="action" value="subir_tema">
                    <input type="hidden" name="tema_slug" value="<?= h($detailThemeSlug) ?>">
                    <input type="hidden" name="return_view" value="tema">
                    <input type="hidden" name="asset_key" value="<?= h($asset['name']) ?>">
                    <label>
                      <span><?= $asset['exists'] ? 'Reemplazar archivo' : 'Adjuntar resultado' ?></span>
                      <input type="file" name="archivo" accept="<?= h($acceptByKind) ?>" required>
                    </label>
                    <button type="submit" class="btn btn-ghost btn-sm"><?= admin_icon('plus') ?> <?= $asset['exists'] ? 'Reemplazar' : 'Adjuntar' ?></button>
                  </form>

                  <?php if ($isPromptable): ?>
                    <form method="post" action="index.php?view=tema&amp;slug=<?= rawurlencode($detailThemeSlug) ?>" class="prompt-form">
                      <?= admin_csrf_field() ?>
                      <input type="hidden" name="action" value="guardar_prompt">
                      <input type="hidden" name="tema_slug" value="<?= h($detailThemeSlug) ?>">
                      <input type="hidden" name="asset_key" value="<?= h($asset['name']) ?>">
                      <label for="<?= h($promptId) ?>">Prompt asociado</label>
                      <textarea id="<?= h($promptId) ?>" name="prompt_text" maxlength="20000" rows="7" placeholder="Describe la escena y los rasgos físicos sin nombres protegidos."><?= h($prompt['prompt'] ?? '') ?></textarea>
                      <div class="prompt-actions">
                        <button type="submit" class="btn btn-primary btn-sm"><?= admin_icon('edit') ?> Guardar prompt</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-copy-prompt="<?= h($promptId) ?>"><?= admin_icon('copy') ?> Copiar</button>
                        <?php if ($prompt): ?><span class="muted small">BD · <?= h($prompt['updated_at']) ?></span><?php endif; ?>
                      </div>
                    </form>
                    <?php
                    $promptHistory = [];
                    try {
                        $promptHistory = cb_load_theme_prompt_history($detailThemeSlug, $asset['name']);
                    } catch (Throwable $e) {
                        error_log('CumpleClick prompt history render: ' . $e->getMessage());
                    }
                    ?>
                    <?php if (count($promptHistory) > 1): ?>
                      <details class="prompt-history">
                        <summary>Historial de versiones (<?= count($promptHistory) ?>)</summary>
                        <ul class="prompt-history-list">
                          <?php foreach ($promptHistory as $i => $rev): ?>
                            <li>
                              <div class="prompt-history-meta">
                                <span class="badge <?= $rev['action'] === 'delete' ? 'badge-warn' : 'badge-off' ?>"><?= $i === 0 ? 'Actual' : ($rev['action'] === 'delete' ? 'Borrado' : 'Anterior') ?></span>
                                <span class="muted small"><?= h($rev['created_at']) ?></span>
                              </div>
                              <p class="prompt-history-text"><?= h(mb_strimwidth((string) $rev['prompt_text'], 0, 220, '…')) ?></p>
                              <?php if ($i > 0 && $rev['action'] !== 'delete'): ?>
                                <button type="button" class="btn btn-ghost btn-sm" data-restore-prompt="<?= h($promptId) ?>" data-restore-text="<?= h($rev['prompt_text']) ?>"><?= admin_icon('duplicate') ?> Usar esta versión</button>
                              <?php endif; ?>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      </details>
                    <?php endif; ?>
                  <?php else: ?>
                    <p class="muted small asset-no-prompt"><?= !empty($asset['derived']) ? 'Se deriva del retrato aprobado; no se genera con IA.' : 'Este tipo de archivo no utiliza prompt generativo.' ?></p>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

    <?php elseif ($view === 'temas'): ?>
      <?php if ($uploadFlash): ?>
        <?php $temaNombreFlash = $themes[$uploadFlash['tema']]['nombre'] ?? $uploadFlash['tema']; ?>
        <div class="alert <?= empty($uploadFlash['rejected']) ? 'alert-ok' : 'alert-error' ?>">
          <?= admin_icon(empty($uploadFlash['rejected']) ? 'check' : 'warn') ?>
          <div>
            <strong>Subida a "<?= h($temaNombreFlash) ?>":</strong>
            <ul>
              <?php foreach ($uploadFlash['saved'] as $sName): ?>
                <li>Guardado: <code><?= h($sName) ?></code></li>
              <?php endforeach; ?>
              <?php foreach ($uploadFlash['rejected'] as $r): ?>
                <li>Rechazado <code><?= h($r['name']) ?></code>: <?= h($r['reason']) ?></li>
              <?php endforeach; ?>
              <?php if (empty($uploadFlash['saved']) && empty($uploadFlash['rejected'])): ?>
                <li>No se seleccionó ningún archivo.</li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <section class="card">
        <h2>Estado de temáticas</h2>
        <p class="muted themes-intro">Sube archivos directamente desde aquí (JPG/MP3/MP4) o por FTP a <code>themes/&lt;slug&gt;/</code>. Solo se aceptan nombres exactos de la lista de cada temática — es intencional, evita archivos mal nombrados.</p>
        <p class="muted small upload-limits"><?= admin_icon('warn') ?> Límites del servidor: máx. por archivo <strong><?= h(ini_get('upload_max_filesize')) ?></strong>, máx. total por solicitud <strong><?= h(ini_get('post_max_size')) ?></strong>. Si tu archivo supera esto, súbelo por FTP.</p>

        <div class="themes-grid">
          <?php foreach ($themes as $tslug => $tdata): ?>
            <?php
            $st = $themeStatus[$tslug];
            $slots = cb_theme_upload_slots($tdata);
            $accent = $tdata['colors']['accent'] ?? '#7C3AED';
            ?>
            <?php $franq = trim((string) ($tdata['franquicia'] ?? '')); ?>
            <article class="theme-card" style="--chip-color: <?= h($accent) ?>">
              <div class="theme-card-head">
                <div class="theme-franchise">
                  <?php if ($franq !== ''): ?>
                    <strong style="color: <?= h($accent) ?>"><?= h($franq) ?></strong>
                    <span class="theme-generic">(<?= h($tdata['nombre'] ?? $tslug) ?>)</span>
                  <?php else: ?>
                    <strong style="color: <?= h($accent) ?>"><?= h($tdata['nombre'] ?? $tslug) ?></strong>
                  <?php endif; ?>
                </div>
                <span class="theme-slug"><?= h($tslug) ?></span>
              </div>

              <div>
                <div class="theme-meta"><?= h($tdata['publico'] ?? '—') ?></div>
                <div class="personajes-row">
                  <?php foreach (($tdata['personajes'] ?? []) as $p): ?>
                    <span class="personaje-pill" title="<?= h($p['name'] ?? '') ?>"><?= h($p['emoji'] ?? '') ?></span>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="theme-status-row">
                <?php if ($st['ready']): ?>
                  <span class="badge badge-ok"><?= admin_icon('check') ?> Lista</span>
                <?php else: ?>
                  <details class="missing-details">
                    <summary>
                      <span class="badge badge-warn"><?= admin_icon('warn') ?> Faltan <?= count($st['missing']) ?></span>
                    </summary>
                    <ul class="missing-list">
                      <?php foreach ($st['missing'] as $m): ?>
                        <li><code><?= h($m) ?></code></li>
                      <?php endforeach; ?>
                    </ul>
                  </details>
                <?php endif; ?>
              </div>

              <a class="btn btn-primary btn-sm theme-detail-link" href="index.php?view=tema&amp;slug=<?= rawurlencode($tslug) ?>"><?= admin_icon('gallery') ?> Ver detalles y prompts</a>

              <form method="post" action="index.php?view=temas" enctype="multipart/form-data" class="theme-upload-form">
                <?= admin_csrf_field() ?>
                <input type="hidden" name="action" value="subir_tema">
                <input type="hidden" name="tema_slug" value="<?= h($tslug) ?>">
                <input type="file" name="archivos[]" multiple accept=".jpg,.jpeg,.png,.mp3,.mp4" aria-label="Archivos para <?= h($tdata['nombre'] ?? $tslug) ?>">
                <button type="submit" class="btn btn-ghost btn-sm"><?= admin_icon('plus') ?> Subir</button>
                <details class="upload-names">
                  <summary>Nombres válidos (<?= count($slots) ?>)</summary>
                  <ul>
                    <?php
                    $optionalLabels = ['video' => 'video opcional', 'png' => 'recorte transparente opcional'];
                    foreach ($slots as $slot):
                    ?>
                      <li><code><?= h($slot['name']) ?></code><?= $slot['required'] ? '' : ' <span class="muted">— ' . h($optionalLabels[$slot['kind']] ?? 'opcional') . '</span>' ?></li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

    <?php else: ?>
      <section class="list-header">
        <h2>Fiestas</h2>
        <a class="btn btn-cta" href="index.php?action=nueva"><?= admin_icon('plus') ?> Nueva fiesta</a>
      </section>

      <?php if (empty($parties)): ?>
        <div class="card empty-state">
          <span class="empty-icon"><?= admin_icon('party') ?></span>
          <p><strong>Aún no hay fiestas creadas.</strong><br>Usa "Nueva fiesta" para empezar.</p>
          <a class="btn btn-cta" href="index.php?action=nueva"><?= admin_icon('plus') ?> Nueva fiesta</a>
        </div>
      <?php endif; ?>

      <div class="party-list">
        <?php foreach ($parties as $publicSlug => $p): ?>
          <?php
          $temaSlug = (string) ($p['tema'] ?? '');
          $temaNombre = $themes[$temaSlug]['nombre'] ?? $temaSlug;
          $temaFranq = trim((string) ($themes[$temaSlug]['franquicia'] ?? ''));
          $temaColor = $themes[$temaSlug]['colors']['accent'] ?? '#7C3AED';
          $activa = !empty($p['activa']);
          $servicePlan = in_array((string) ($p['service_plan'] ?? ''), ['booth', 'full'], true) ? (string) $p['service_plan'] : 'booth';
          $galleryEnabled = !empty($p['gallery_enabled']);
          $url = $baseUrl . '?p=' . rawurlencode($publicSlug);
          $tieneGaleriaPin = !empty($p['galeriaHabilitada']);
          $galeriaUrl = $baseUrl . 'galeria.php?p=' . rawurlencode($publicSlug);
          $invitationsUrl = 'invitations.php?party=' . rawurlencode($publicSlug);
          $photoUsage = cb_photo_usage((string) $publicSlug);
          $quotaRatio = max($photoUsage['count'] / 200, $photoUsage['bytes'] / 1073741824);
          $eventProfile = null;
          $eventProfileAvailable = cb_storage_mode() === 'db' && function_exists('cb_event_profile_get');
          if ($eventProfileAvailable) {
              try {
                  $profilePartyId = cb_party_db_id((string) $publicSlug);
                  $eventProfile = $profilePartyId !== null ? cb_event_profile_get($profilePartyId, true) : null;
              } catch (Throwable $e) { error_log('CumpleClick admin profile status: ' . $e->getMessage()); }
          }
          ?>
          <article class="card party-card" style="--chip-accent: <?= h($temaColor) ?>">
            <div class="party-main">
              <div class="party-title">
                <strong><?= h($p['admin_label'] ?: ($p['nombre'] ?? $publicSlug)) ?></strong>
                <span class="chip" style="--chip-color: <?= h($temaColor) ?>" <?= $temaFranq !== '' ? 'title="' . h($temaFranq) . '"' : '' ?>><?= h($temaNombre) ?></span>
                <span class="badge <?= $activa ? 'badge-ok' : 'badge-off' ?>">
                  <?= $activa ? admin_icon('check') : '' ?> <?= $activa ? 'Activa' : 'Inactiva' ?>
                </span>
                <span class="badge badge-off"><?= h($servicePlan) ?></span>
                <?php if ($galleryEnabled): ?><span class="badge badge-ok">Galería</span><?php endif; ?>
                <?php
                $eventProfileEnabled = !empty($eventProfile['is_enabled']);
                $eventProfilePeople = is_array($eventProfile['featured_people'] ?? null) ? count($eventProfile['featured_people']) : 0;
                ?>
                <?php if ($eventProfileEnabled && $eventProfilePeople > 0): ?><span class="badge badge-ok">Perfil: <?= (int) $eventProfilePeople ?> protagonista<?= $eventProfilePeople === 1 ? '' : 's' ?></span>
                <?php elseif ($eventProfile !== null): ?><span class="badge badge-off">Perfil desactivado</span><?php endif; ?>
              </div>
              <div class="muted small"><?= h($p['birthday_person_name'] ?: '—') ?> · <?= h($p['fecha'] ?: '—') ?> · <?= count($p['invitados'] ?? []) ?> invitados · <?= (int) $photoUsage['count'] ?>/200 fotos · slug: <?= h($publicSlug) ?></div>
              <?php if ($quotaRatio >= 0.8): ?><div class="badge badge-off">Atención: galería al <?= (int) floor($quotaRatio * 100) ?>% de cuota</div><?php endif; ?>
            </div>

            <div class="party-url">
              <input type="text" readonly value="<?= h($url) ?>" onclick="this.select()" aria-label="URL de la fiesta">
              <button type="button" class="btn btn-icon" data-copy="<?= h($url) ?>" title="Copiar URL">
                <?= admin_icon('copy') ?><span class="btn-label sr-only">Copiar</span>
              </button>
              <a class="btn btn-icon" href="<?= h($url) ?>" target="_blank" rel="noopener" title="Abrir en pestaña nueva">
                <?= admin_icon('external') ?>
              </a>
            </div>

            <div class="party-actions">
              <a class="btn btn-ghost" href="index.php?action=editar&amp;slug=<?= rawurlencode($publicSlug) ?>"><?= admin_icon('edit') ?> Editar</a>
              <?php if ($tieneGaleriaPin): ?>
                <a class="btn btn-ghost" href="<?= h($galeriaUrl) ?>" target="_blank" rel="noopener"><?= admin_icon('gallery') ?> Galería</a>
              <?php endif; ?>
              <a class="btn btn-ghost" href="<?= h($invitationsUrl) ?>"><?= admin_icon('duplicate') ?> Invitaciones</a>
              <?php // Solo si el módulo está realmente utilizable. Los archivos del
                    // álbum pueden estar subidos sin la migración 007 aplicada, y en
                    // ese estado este botón lleva a una página que falla al consultar.
                    if (function_exists('cb_album_feature_ready') && cb_album_feature_ready()): ?>
                <a class="btn btn-ghost" href="album.php?party=<?= rawurlencode($publicSlug) ?>"><?= admin_icon('gallery') ?> Álbum Recuerdo</a>
              <?php endif; ?>
              <?php if ($eventProfileAvailable): ?>
                <a class="btn btn-ghost" href="event-profile.php?party=<?= rawurlencode($publicSlug) ?>"><?= admin_icon('party') ?> Perfil del protagonista</a>
              <?php endif; ?>
              <form method="post" action="index.php" class="inline-form">
                <?= admin_csrf_field() ?>
                <input type="hidden" name="action" value="duplicar">
                <input type="hidden" name="slug" value="<?= h($publicSlug) ?>">
                <button type="submit" class="btn btn-ghost"><?= admin_icon('duplicate') ?> Duplicar</button>
              </form>
              <form method="post" action="index.php" class="inline-form" data-confirm="¿Eliminar la fiesta &quot;<?= h(addslashes($p['admin_label'] ?: ($p['nombre'] ?? $publicSlug))) ?>&quot;? Esta acción no se puede deshacer.">
                <?= admin_csrf_field() ?>
                <input type="hidden" name="action" value="eliminar">
                <input type="hidden" name="slug" value="<?= h($publicSlug) ?>">
                <button type="submit" class="btn btn-danger"><?= admin_icon('trash') ?> Eliminar</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<script>
(function () {
  var framePreview = document.querySelector('.frame-preview');
  var frameOverlay = document.getElementById('frame-overlay');
  var frameInputs = document.querySelectorAll('.frame-value');
  function updateFramePreview() {
    if (!frameOverlay) { return; }
    var v = {};
    Array.prototype.forEach.call(frameInputs, function (input) { v[input.getAttribute('data-frame')] = Math.max(0, Math.min(1, parseFloat(input.value) || 0)); });
    // El canvas es 9:16: convierte ambos ejes a píxeles, toma el lado menor
    // y vuelve a porcentajes para previsualizar exactamente el recorte cuadrado.
    var squareWidth = Math.min(v.w, v.h * (16 / 9));
    var squareHeight = Math.min(v.h, v.w * (9 / 16));
    var photoScale = 1 - (0.085 * 2);
    var photoWidth = squareWidth * photoScale;
    var photoHeight = squareHeight * photoScale;
    frameOverlay.style.left = ((v.x + (v.w - photoWidth) / 2) * 100) + '%';
    frameOverlay.style.top = ((v.y + (v.h - photoHeight) / 2) * 100) + '%';
    frameOverlay.style.width = (photoWidth * 100) + '%';
    frameOverlay.style.height = (photoHeight * 100) + '%';
  }
  Array.prototype.forEach.call(frameInputs, function (input) { input.addEventListener('input', updateFramePreview); });
  var themeSelect = document.getElementById('f-tema');
  if (themeSelect && framePreview) { themeSelect.addEventListener('change', function () { framePreview.style.backgroundImage = "url('../themes/" + encodeURIComponent(themeSelect.value) + "/fondo-sala.jpg')"; }); }
  updateFramePreview();

  // --- Copiar URL al portapapeles (con fallback) ---
  function fallbackCopy(text, done) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.focus(); ta.select();
    try { document.execCommand('copy'); done(); } catch (e) { /* noop */ }
    document.body.removeChild(ta);
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-copy]'), function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy');
      var label = btn.querySelector('.btn-label');
      var done = function () {
        btn.classList.add('copied');
        if (label) { label.textContent = '¡Copiado!'; }
        btn.setAttribute('title', '¡Copiado!');
        setTimeout(function () {
          btn.classList.remove('copied');
          if (label) { label.textContent = 'Copiar'; }
          btn.setAttribute('title', 'Copiar URL');
        }, 1600);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); });
      } else {
        fallbackCopy(text, done);
      }
    });
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-copy-prompt]'), function (btn) {
    btn.addEventListener('click', function () {
      var field = document.getElementById(btn.getAttribute('data-copy-prompt'));
      if (!field) { return; }
      var original = btn.innerHTML;
      var done = function () {
        btn.textContent = 'Copiado';
        setTimeout(function () { btn.innerHTML = original; }, 1400);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(field.value).then(done, function () { fallbackCopy(field.value, done); });
      } else {
        fallbackCopy(field.value, done);
      }
    });
  });

  // --- Historial de prompts: solo rellena el textarea, no guarda hasta
  // que el admin toque "Guardar prompt" (así puede revisar/editar antes). ---
  Array.prototype.forEach.call(document.querySelectorAll('[data-restore-prompt]'), function (btn) {
    btn.addEventListener('click', function () {
      var field = document.getElementById(btn.getAttribute('data-restore-prompt'));
      if (!field) { return; }
      field.value = btn.getAttribute('data-restore-text') || '';
      field.focus();
      var original = btn.innerHTML;
      btn.textContent = 'Cargado en el editor';
      setTimeout(function () { btn.innerHTML = original; }, 1600);
    });
  });

  // --- Filtros del estudio de producción (no cambian datos ni URLs). ---
  Array.prototype.forEach.call(document.querySelectorAll('[data-asset-filter]'), function (btn) {
    btn.addEventListener('click', function () {
      var filter = btn.getAttribute('data-asset-filter') || 'all';
      Array.prototype.forEach.call(document.querySelectorAll('[data-asset-filter]'), function (other) {
        other.classList.toggle('active', other === btn);
      });
      Array.prototype.forEach.call(document.querySelectorAll('.asset-card[data-asset-group]'), function (card) {
        card.hidden = filter !== 'all' && card.getAttribute('data-asset-group') !== filter;
      });
    });
  });

  // --- Confirmación en formularios de eliminar/duplicar ---
  Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm]'), function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
  });

  // --- Modal vista previa de imágenes y videos en inventario de assets ---
  (function () {
    var assetGrid = document.querySelector('.asset-detail-grid');
    if (!assetGrid) return;

    // Reunir todos los items navegables (img > video) una sola vez.
    var allItems = [];
    var allPreviews = assetGrid.querySelectorAll('.asset-preview');
    Array.prototype.forEach.call(allPreviews, function (preview) {
      var img = preview.querySelector('img');
      var vid = preview.querySelector('video');
      if (img) {
        allItems.push({ type: 'img', src: img.src, alt: img.alt || '', el: preview });
      } else if (vid) {
        allItems.push({ type: 'video', src: vid.src, el: preview });
      }
    });

    var currentIndex = -1;
    var mediaEl = null;

    var overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.style.display = 'none';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-label', 'Vista previa');

    var content = document.createElement('div');
    content.className = 'modal-content';

    var closeBtn = document.createElement('button');
    closeBtn.className = 'modal-close';
    closeBtn.innerHTML = '&times;';
    closeBtn.setAttribute('aria-label', 'Cerrar');
    content.appendChild(closeBtn);

    var prevBtn = document.createElement('button');
    prevBtn.className = 'modal-nav modal-prev';
    prevBtn.innerHTML = '&#8249;';
    prevBtn.setAttribute('aria-label', 'Anterior');
    content.appendChild(prevBtn);

    var nextBtn = document.createElement('button');
    nextBtn.className = 'modal-nav modal-next';
    nextBtn.innerHTML = '&#8250;';
    nextBtn.setAttribute('aria-label', 'Siguiente');
    content.appendChild(nextBtn);

    function closeModal() {
      overlay.style.display = 'none';
      document.body.style.overflow = '';
      if (mediaEl) {
        if (mediaEl.tagName === 'VIDEO') { mediaEl.pause(); mediaEl.currentTime = 0; }
        content.removeChild(mediaEl);
        mediaEl = null;
      }
      currentIndex = -1;
    }

    function showItem(index) {
      if (index < 0 || index >= allItems.length) return;
      currentIndex = index;
      var item = allItems[index];
      if (mediaEl) { content.removeChild(mediaEl); mediaEl = null; }
      if (item.type === 'img') {
        mediaEl = document.createElement('img');
        mediaEl.src = item.src;
        mediaEl.alt = item.alt;
      } else {
        mediaEl = document.createElement('video');
        mediaEl.src = item.src;
        mediaEl.controls = true;
        mediaEl.autoplay = true;
        mediaEl.setAttribute('playsinline', '');
      }
      content.insertBefore(mediaEl, prevBtn);
      prevBtn.style.display = (allItems.length > 1 && currentIndex > 0) ? '' : 'none';
      nextBtn.style.display = (allItems.length > 1 && currentIndex < allItems.length - 1) ? '' : 'none';
    }

    closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeModal();
    });
    prevBtn.addEventListener('click', function () {
      if (currentIndex > 0) showItem(currentIndex - 1);
    });
    nextBtn.addEventListener('click', function () {
      if (currentIndex < allItems.length - 1) showItem(currentIndex + 1);
    });
    document.addEventListener('keydown', function (e) {
      if (overlay.style.display !== 'flex') return;
      if (e.key === 'Escape') { closeModal(); return; }
      if (e.key === 'ArrowLeft' && currentIndex > 0) { showItem(currentIndex - 1); return; }
      if (e.key === 'ArrowRight' && currentIndex < allItems.length - 1) { showItem(currentIndex + 1); return; }
    });

    document.body.appendChild(overlay);
    overlay.appendChild(content);

    assetGrid.addEventListener('click', function (e) {
      var preview = e.target.closest('.asset-preview');
      if (!preview) return;
      var found = -1;
      for (var i = 0; i < allItems.length; i++) {
        if (allItems[i].el === preview) { found = i; break; }
      }
      if (found === -1) return;
      showItem(found);
      overlay.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    });
  })();
})();
</script>
</body>
</html>
