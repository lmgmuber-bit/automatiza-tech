<?php
/**
 * _at-verificar.php — comprueba que la subida a PROD quedó completa.
 * Solo LEE: no escribe en la base ni toca archivos (salvo borrarse a sí mismo).
 *
 * Revisa los 3 puntos donde suele fallar un deploy por FTP:
 *   1. Archivos que faltan, o que llegaron cortados (compara el tamaño exacto).
 *   2. Migraciones 008/009 aplicadas y columna birthday_person_gender presente.
 *   3. event_profile_enabled en true (si no, el botón "Conoce a…" no aparece).
 *
 * USO
 *   1. Edita $TOKEN y pon una clave larga tuya.
 *   2. Sube a /public_html/cumpleclick/_at-verificar.php y ábrelo.
 *   3. Bórralo con el botón rojo.
 *
 * Los tamaños esperados son los del build local del 2026-08-19. Si vuelves a
 * generar los archivos, cambiarán y este script los reportará como distintos.
 */

// ---------------------------------------------------------------- CONFIGURA
$TOKEN = 'CAMBIA-ESTO-ANTES-DE-SUBIR';
// ---------------------------------------------------------------------------

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Content-Type: text/html; charset=utf-8');

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function peso(int $b): string
{
    if ($b >= 1048576) { return round($b / 1048576, 1) . ' MB'; }
    if ($b >= 1024)    { return round($b / 1024) . ' KB'; }
    return $b . ' B';
}

$tokenOk = false;
$enviado = (string) ($_POST['t'] ?? '');
if (strlen($TOKEN) >= 20 && $TOKEN !== 'CAMBIA-ESTO-ANTES-DE-SUBIR' && $enviado !== '') {
    $tokenOk = hash_equals($TOKEN, $enviado);
}
$accion = (string) ($_POST['accion'] ?? '');

// ruta => bytes esperados.
// Los de texto llevan DOS valores: [tamaño en Windows (CRLF), tamaño con LF].
// Un FTP en modo ASCII convierte los saltos de línea y cambia el tamaño sin
// que el contenido esté mal; aceptando ambos se evita el falso positivo.
$OBLIGATORIOS = [
    'lib.php'                                => [96207, 93956],
    'lib.album.php'                          => [46807, 45636],
    'lib.event-profiles.php'                 => [49876, 48912],
    'lib.invitations.php'                    => [35567, 34741],
    'invitacion.php'                         => [86073, 84607],
    'descargar-invitacion.php'               => [5346, 5224],
    'event-profile-media.php'                => [5166, 5008],
    'admin/_style.css.php'                   => [40531, 39775],
    'admin/event-profile.php'                => [51905, 51238],
    'admin/invitations.php'                  => [67540, 66385],
    'admin/index.php'                        => [77224, 75804],
    'assets/invitation.css'                  => 41689,
    'assets/invitation.js'                   => 44626,
    'assets/event-profile.css'               => 18503,
    'assets/event-profile.js'                => [7588, 7389],
    'data/event-profile-presets.json'        => 12143,
    'assets/audio/narracion-final.mp3'       => 96174,
    'assets/audio/narracion-final-nino.mp3'  => 79874,
    'assets/audio/narracion-final-nina.mp3'  => 84889,
    'assets/audio/narracion-playlist-final.mp3' => 40586,

    'themes/carreras/fondo-banner.jpg'                        => 1295080,
    'themes/carreras/revelacion-carreras.mp4'                 => 742877,
    'themes/carreras/invitation/intro-invitacion-wow-v1.mp4'  => 4282419,
    'themes/carreras/invitation/invitation-scroll-v1.mp4'     => 3480176,
    'themes/carreras/invitation/invitation-motion-v1.mp4'     => 1684180,
    'themes/carreras/narracion-video/saludo-rayo-mcqueen.mp3' => 29301,
    'themes/carreras/narracion-video/saludo-mate.mp3'         => 30973,
    'themes/carreras/narracion-video/saludo-cruz.mp3'         => 27629,
    'themes/carreras/narracion-video/saludo-sally.mp3'        => 26375,
    'themes/carreras/narracion-video/saludo-luigi.mp3'        => 26375,
    'themes/carreras/narracion-video/saludo-el-rey.mp3'       => 29301,
    'themes/carreras/narracion-video/rayo-mcqueen-estrella.mp3' => 33898,

    'themes/hielo/fondo-banner.jpg'                           => 261483,
    'themes/hielo/entrada-palacio-hielo.mp4'                  => 6344296,
    'themes/hielo/invitation/intro-invitacion-wow-v1.mp4'     => 4585021,
    'themes/hielo/invitation/invitation-motion-v1.mp4'        => 651012,
    'themes/hielo/invitation/candidates/saludo-elsa-v2.mp4'     => 1504006,
    'themes/hielo/invitation/candidates/saludo-anna-v3.mp4'     => 1398791,
    'themes/hielo/invitation/candidates/saludo-olaf-v2.mp4'     => 1271512,
    'themes/hielo/invitation/candidates/saludo-kristoff-v2.mp4' => 1655406,
    'themes/hielo/invitation/candidates/saludo-sven-v3.mp4'     => 1064063,
    'themes/hielo/invitation/candidates/saludo-bruni-v3.mp4'    => 1160656,
    'themes/hielo/narracion-video/saludo-anna.mp3'     => 54378,
    'themes/hielo/narracion-video/saludo-elsa.mp3'     => 63573,
    'themes/hielo/narracion-video/saludo-olaf.mp3'     => 54378,
    'themes/hielo/narracion-video/saludo-kristoff.mp3' => 62737,
    'themes/hielo/narracion-video/saludo-sven.mp3'     => 47273,
    'themes/hielo/narracion-video/saludo-bruni.mp3'    => 85725,

    // Temas kpop, tropical y familia-canina (2026-08-23). Sus saludos y su
    // hero motion ya estaban en PROD desde el kiosco; lo nuevo es el hero
    // scroll (derivado del motion) y el intro con el logo corregido.
    'themes/kpop/invitation/invitation-scroll-v1.mp4'            => 3084576,
    'themes/kpop/invitation/intro-invitacion-wow-v1.mp4'         => 4339642,
    // Los tres posters aceptan dos tamaños: el local y el que quedó en PROD.
    // Son la misma imagen — SSIM 0,998 contra un control de 0,372 entre dos
    // posters distintos — recodificada con otra calidad y con un bloque EXIF.
    // Se verificó descargándolos, no suponiendo.
    'themes/kpop/invitation/intro-invitacion-wow-v1-poster.jpg'  => [22612, 26200],
    'themes/tropical/invitation/invitation-scroll-v1.mp4'           => 4133716,
    'themes/tropical/invitation/intro-invitacion-wow-v1.mp4'        => 5131839,
    'themes/tropical/invitation/intro-invitacion-wow-v1-poster.jpg' => [70516, 83152],
    'themes/familia-canina/invitation/invitation-scroll-v1.mp4'           => 3100319,
    'themes/familia-canina/invitation/intro-invitacion-wow-v1.mp4'        => 3472693,
    'themes/familia-canina/invitation/intro-invitacion-wow-v1-poster.jpg' => [30461, 35108],
];

$OPCIONALES = [
    'themes/carreras/invitation/candidate-wan27-scroll.mp4' => 3518010,
    'themes/carreras/invitation/candidate-wan27-auto.mp4'   => 3473845,
    'themes/hielo/invitation/candidate-hielo-scroll.mp4'    => 2819858,
    'themes/hielo/invitation/candidate-hielo-auto.mp4'      => 1023686,
];

// Si estos aparecen, el módulo de Álbum se subió por error (aún no está listo).
$NO_DEBERIAN = ['album.html', 'subir.php', 'album-api.php', 'ver-media.php', 'admin/album.php'];

?><!doctype html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>CumpleClick · verificación</title>
<style>
 body{font:15px/1.55 system-ui,Segoe UI,sans-serif;background:#0f1720;color:#e6edf3;margin:0;padding:28px}
 .wrap{max-width:900px;margin:0 auto}
 h1{font-size:20px;margin:0 0 4px} h2{font-size:16px;margin:0 0 12px}
 .sub{color:#8fa3b8;margin:0 0 22px}
 .card{background:#16212c;border:1px solid #24323f;border-radius:10px;padding:18px;margin:0 0 16px}
 table{width:100%;border-collapse:collapse;font-size:13.5px}
 th,td{text-align:left;padding:6px 10px;border-bottom:1px solid #24323f;vertical-align:top}
 th{color:#8fa3b8;font-weight:600}
 code{background:#0f1720;padding:1px 5px;border-radius:4px;font-size:12.5px;word-break:break-all}
 input[type=password]{background:#0f1720;border:1px solid #2c3d4d;color:#e6edf3;
  border-radius:6px;padding:9px 11px;width:320px;max-width:100%;font-size:14px}
 button{background:#2f81f7;border:0;color:#fff;border-radius:6px;padding:10px 18px;
  font-size:14px;font-weight:600;cursor:pointer;margin-top:10px}
 button.danger{background:#b3202b}
 .ok{color:#4ec97b} .warn{color:#e9b949} .bad{color:#ff6b6b} .mut{color:#8fa3b8}
 .big{font-size:17px;font-weight:600}
</style></head><body><div class="wrap">
<h1>CumpleClick · verificación del deploy</h1>
<p class="sub">Solo lee. Bórrame cuando termines.</p>
<?php

if (strlen($TOKEN) < 20 || $TOKEN === 'CAMBIA-ESTO-ANTES-DE-SUBIR') {
    echo '<div class="card"><p class="bad">Este script está desactivado.</p>'
       . '<p class="mut">Edita <code>$TOKEN</code> arriba del archivo y pon una clave '
       . 'tuya de 20 caracteres o más.</p></div></div></body></html>';
    exit;
}
if (!$tokenOk) {
    if ($enviado !== '') { echo '<div class="card"><p class="bad">Token incorrecto.</p></div>'; }
    echo '<div class="card"><form method="post"><label class="mut">Token</label><br>'
       . '<input type="password" name="t" autocomplete="off" autofocus><br>'
       . '<button type="submit">Entrar</button></form></div></div></body></html>';
    exit;
}
if ($accion === 'autodestruir') {
    $borrado = @unlink(__FILE__);
    echo '<div class="card">' . ($borrado
        ? '<p class="ok">Archivo borrado del servidor.</p>'
        : '<p class="bad">No se pudo borrar solo (permisos). Bórralo por FTP: <code>'
          . h(basename(__FILE__)) . '</code></p>')
       . '</div></div></body></html>';
    exit;
}

// ------------------------------------------------------------------ archivos
function revisar(array $lista, string $base): array
{
    $filas = []; $malos = 0;
    foreach ($lista as $rel => $esperado) {
        $ruta = $base . '/' . $rel;
        if (!is_file($ruta)) {
            $filas[] = [$rel, 'falta', '—', 'bad']; $malos++;
            continue;
        }
        $real = (int) filesize($ruta);
        $aceptados = is_array($esperado) ? $esperado : [$esperado];
        if (!in_array($real, $aceptados, true)) {
            $filas[] = [$rel, 'tamaño distinto',
                        peso($real) . ' vs ' . peso($aceptados[0]) . ' esperados', 'warn'];
            $malos++;
            continue;
        }
        // El segundo valor es el mismo archivo con saltos de línea convertidos
        // por un FTP en modo ASCII: el contenido es idéntico.
        $nota = (count($aceptados) > 1 && $real === $aceptados[1]) ? ' (subido en modo ASCII)' : '';
        $filas[] = [$rel, 'ok', peso($real) . $nota, 'ok'];
    }
    return [$filas, $malos];
}

$base = __DIR__;
[$filasObl, $malosObl] = revisar($OBLIGATORIOS, $base);
[$filasOpc, $malosOpc] = revisar($OPCIONALES, $base);

echo '<div class="card"><h2>1. Archivos obligatorios</h2>';
echo $malosObl === 0
    ? '<p class="ok big">Los ' . count($OBLIGATORIOS) . ' están y con el tamaño correcto.</p>'
    : '<p class="bad big">' . $malosObl . ' con problema, de ' . count($OBLIGATORIOS) . '.</p>';
echo '<table><tr><th>Archivo</th><th>Estado</th><th>Tamaño</th></tr>';
foreach ($filasObl as [$rel, $estado, $tam, $cls]) {
    if ($cls === 'ok' && $malosObl > 0) { continue; } // con problemas, muestra solo lo que falla
    echo '<tr><td><code>' . h($rel) . '</code></td><td class="' . $cls . '">' . h($estado)
       . '</td><td class="mut">' . h($tam) . '</td></tr>';
}
echo '</table>';
if ($malosObl > 0) { echo '<p class="mut">Se listan solo los que fallan.</p>'; }
echo '</div>';

echo '<div class="card"><h2>2. Archivos opcionales (Básico vs Full)</h2>';
echo '<table><tr><th>Archivo</th><th>Estado</th><th>Tamaño</th></tr>';
foreach ($filasOpc as [$rel, $estado, $tam, $cls]) {
    if ($estado === 'falta') { $cls = 'mut'; $estado = 'no subido (no rompe nada)'; }
    echo '<tr><td><code>' . h($rel) . '</code></td><td class="' . $cls . '">' . h($estado)
       . '</td><td class="mut">' . h($tam) . '</td></tr>';
}
echo '</table></div>';

$intrusos = [];
foreach ($NO_DEBERIAN as $rel) { if (file_exists($base . '/' . $rel)) { $intrusos[] = $rel; } }
if ($intrusos) {
    echo '<div class="card"><h2>Módulo de Álbum presente</h2><p class="mut">Estos archivos '
       . 'del Álbum están en PROD y su módulo todavía no está terminado. Desde el '
       . '2026-08-23 ya no molestan: el botón "Álbum Recuerdo" del backoffice solo '
       . 'aparece si la tabla <code>cc_event_albums</code> existe, y la migración 007 no '
       . 'está aplicada. Borrarlos es opcional; reaparecerán solos cuando el módulo '
       . 'esté listo y se corra su migración.</p><ul>';
    foreach ($intrusos as $i) { echo '<li><code>' . h($i) . '</code></li>'; }
    echo '</ul></div>';
}

// ------------------------------------------------------------ base de datos
echo '<div class="card"><h2>3. Base de datos y configuración</h2>';
$lib = is_file($base . '/lib.php') ? $base . '/lib.php'
     : (is_file(dirname(__DIR__, 2) . '/public/lib.php') ? dirname(__DIR__, 2) . '/public/lib.php' : null);
if ($lib === null) {
    echo '<p class="bad">No encuentro <code>lib.php</code>: este archivo tiene que quedar '
       . 'en la raíz de <code>/cumpleclick/</code>.</p></div></div></body></html>';
    exit;
}
require $lib;

$chequeos = [];

// Dónde está el archivo de configuración, resuelto igual que lo hace lib.php:
// primero la variable de entorno, si no, <padre de la carpeta de lib.php>/config/.
$cfgEnv = getenv('CUMPLECLICK_CONFIG_FILE');
if ($cfgEnv !== false && $cfgEnv !== '') {
    $cfgPath = (string) $cfgEnv;
    $cfgOrigen = 'variable de entorno CUMPLECLICK_CONFIG_FILE';
} else {
    $cfgPath = dirname(dirname($lib)) . '/config/cumpleclick.local.php';
    $cfgOrigen = 'ruta por defecto';
}
$chequeos[] = ['archivo de configuración',
    $cfgPath . '  (' . $cfgOrigen . ')' . (is_file($cfgPath) ? ' — existe' : ' — NO existe'),
    is_file($cfgPath) ? 'ok' : 'bad'];

try {
    $chequeos[] = ['storage_mode', cb_storage_mode(), cb_storage_mode() === 'db' ? 'ok' : 'warn'];
} catch (Throwable $e) {
    $chequeos[] = ['storage_mode', 'no legible: ' . $e->getMessage(), 'bad'];
}
try {
    $on = cb_event_profile_feature_enabled();
    $chequeos[] = ['event_profile_enabled', $on ? 'true' : 'false — el botón "Conoce a…" NO aparecerá',
                   $on ? 'ok' : 'bad'];
} catch (Throwable $e) {
    $chequeos[] = ['event_profile_enabled', 'no legible', 'bad'];
}

// Carpetas privadas de storage. Se valida sin crearlas: este script no escribe.
// Trampa conocida: si el config de PROD no define invitation_dir, el valor por
// defecto cae DENTRO del webroot y cb_private_dir lo rechaza — crear una
// invitación fallaría. Aquí se ve antes de que pase.
foreach ([['photo_dir', ''], ['invitation_dir', 'invitations'],
          ['event_profile_dir', 'event-profiles']] as [$clave, $sub]) {
    $valor = trim((string) cb_config($clave));
    $nota = '';
    if ($valor === '' && $sub !== '') {
        $valor = dirname((string) cb_config('photo_dir')) . '/' . $sub;
        $nota = ' (derivado de photo_dir)';
    }
    try {
        $ruta = cb_private_dir($valor, $clave);
        $chequeos[] = [$clave, $ruta . $nota . (is_dir($ruta) ? ' — existe' : ' — se creará sola'), 'ok'];
    } catch (Throwable $e) {
        $chequeos[] = [$clave, 'INVÁLIDO (' . $e->getMessage() . '). Valor actual: ' . $valor, 'bad'];
    }
}

try {
    $pdo = cb_pdo();
    $chequeos[] = ['conexión a la base', 'ok (' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . ')', 'ok'];

    foreach (['cc_parties', 'cc_invitations', 'cc_event_profiles', 'cc_featured_people',
              'cc_event_profile_fields', 'cc_event_profile_sections'] as $t) {
        try {
            $n = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
            $chequeos[] = ['tabla ' . $t, 'existe (' . (int) $n . ' filas)', 'ok'];
        } catch (Throwable $e) {
            $chequeos[] = ['tabla ' . $t, 'NO existe — falta correr las migraciones', 'bad'];
        }
    }
    try {
        $pdo->query('SELECT birthday_person_gender FROM cc_invitations LIMIT 1');
        $chequeos[] = ['columna birthday_person_gender', 'existe', 'ok'];
    } catch (Throwable $e) {
        $chequeos[] = ['columna birthday_person_gender', 'NO existe — falta la migración 009', 'bad'];
    }
    try {
        $v = $pdo->query('SELECT version FROM cc_schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        foreach (['008_event_profiles', '009_invitation_gender'] as $m) {
            $chequeos[] = ['migración ' . $m, in_array($m, $v, true) ? 'aplicada' : 'PENDIENTE',
                           in_array($m, $v, true) ? 'ok' : 'bad'];
        }
    } catch (Throwable $e) {
        $chequeos[] = ['cc_schema_migrations', 'no legible', 'bad'];
    }
    foreach (['demo-carreras-vicente', 'demo-hielo-isidora'] as $slug) {
        try {
            $existe = cb_party_db_id($slug) !== null;
            $chequeos[] = ['fiesta demo ' . $slug, $existe ? 'creada' : 'no creada todavía',
                           $existe ? 'ok' : 'warn'];
        } catch (Throwable $e) {
            $chequeos[] = ['fiesta demo ' . $slug, 'no se pudo consultar', 'warn'];
        }
    }
} catch (Throwable $e) {
    $chequeos[] = ['conexión a la base', 'FALLA: ' . $e->getMessage(), 'bad'];
}

echo '<table><tr><th>Chequeo</th><th>Resultado</th></tr>';
foreach ($chequeos as [$k, $v, $cls]) {
    echo '<tr><td>' . h($k) . '</td><td class="' . $cls . '">' . h($v) . '</td></tr>';
}
echo '</table></div>';

echo '<div class="card"><p class="mut">Lo que este script NO puede comprobar: que los '
   . 'videos se vean bien y que la narración suene. Eso ábrelo tú en el teléfono con '
   . 'los enlaces de las demos.</p>'
   . '<form method="post"><input type="hidden" name="t" value="' . h($enviado) . '">'
   . '<input type="hidden" name="accion" value="autodestruir">'
   . '<button class="danger" type="submit">Borrar este archivo del servidor</button></form></div>';

?>
</div></body></html>
