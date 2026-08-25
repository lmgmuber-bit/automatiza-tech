<?php
/**
 * _at-seed-cita-completa.php — arma UNA fiesta de punta a punta, desde el
 * navegador, para recorrer el producto entero como lo vive un cliente:
 *
 *   invitación → ficha del cumpleañero → QR del álbum → carga → curaduría →
 *   revista publicada
 *
 * Las fotos NO se descargan de ningún lado ni se generan con IA: se componen
 * acá con GD igual que las arma la cabina — el fondo de sala de la temática más
 * el recorte del personaje. Salen de la propia guía visual del tema, así que se
 * parecen a lo que produce el kiosco de verdad y no cuestan un solo crédito.
 *
 * USO
 *   1. Edita $TOKEN y pon una clave larga tuya.
 *   2. Sube a /public_html/cumpleclick/_at-seed-cita-completa.php y ábrelo.
 *   3. Revisa el resumen (no escribe nada) y confirma.
 *   4. COPIA LOS ENLACES. Los tokens se muestran una sola vez.
 *   5. BÓRRALO con el botón rojo.
 *
 * REQUIERE la migración 007 aplicada. Sin sus tablas el álbum no existe y el
 * script se niega a empezar en vez de reventar a mitad.
 */

// ---------------------------------------------------------------- CONFIGURA
$TOKEN = 'CAMBIA-ESTO-ANTES-DE-SUBIR';

// Tema y protagonista de la fiesta de prueba.
$TEMA   = 'hielo';
$NOMBRE = 'Isidora';
$GENERO = 'f';               // 'f' o 'm' — elige la narración de cierre
$SLUG   = 'cita-completa-isidora';
// ---------------------------------------------------------------------------

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Content-Type: text/html; charset=utf-8');

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

$tokenOk = false;
$enviado = (string) ($_POST['t'] ?? '');
if (strlen($TOKEN) >= 20 && $TOKEN !== 'CAMBIA-ESTO-ANTES-DE-SUBIR' && $enviado !== '') {
    $tokenOk = hash_equals($TOKEN, $enviado);
}
$accion = (string) ($_POST['accion'] ?? '');

?><!doctype html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>CumpleClick · cita completa</title>
<style>
 body{font:15px/1.55 system-ui,Segoe UI,sans-serif;background:#0f1720;color:#e6edf3;margin:0;padding:28px}
 .wrap{max-width:900px;margin:0 auto}
 h1{font-size:20px;margin:0 0 4px} h2{font-size:16px;margin:0 0 10px}
 .sub{color:#8fa3b8;margin:0 0 22px}
 .card{background:#16212c;border:1px solid #24323f;border-radius:10px;padding:18px;margin:0 0 16px}
 code{background:#0f1720;padding:1px 5px;border-radius:4px;font-size:13px;word-break:break-all}
 input[type=password]{background:#0f1720;border:1px solid #2c3d4d;color:#e6edf3;
  border-radius:6px;padding:9px 11px;width:320px;max-width:100%;font-size:14px}
 button{background:#2f81f7;border:0;color:#fff;border-radius:6px;padding:10px 18px;
  font-size:14px;font-weight:600;cursor:pointer;margin-top:10px}
 button.danger{background:#b3202b}
 .ok{color:#4ec97b} .warn{color:#e9b949} .bad{color:#ff6b6b} .mut{color:#8fa3b8}
 pre{background:#0f1720;border:1px solid #24323f;border-radius:8px;padding:12px;
  overflow:auto;font-size:13px;white-space:pre-wrap}
 .paso{margin:0 0 10px;padding-left:22px;position:relative}
 .paso b{display:block;color:#8fa3b8;font-size:12px;letter-spacing:.06em;text-transform:uppercase}
 ul{margin:6px 0 0;padding-left:20px}
</style></head><body><div class="wrap">
<h1>CumpleClick · una fiesta completa, de la invitación al álbum</h1>
<p class="sub">Bórrame del servidor apenas termines.</p>
<?php

if (strlen($TOKEN) < 20 || $TOKEN === 'CAMBIA-ESTO-ANTES-DE-SUBIR') {
    echo '<div class="card"><p class="bad">Este script está desactivado.</p>'
       . '<p class="mut">Edita <code>$TOKEN</code> arriba del archivo.</p></div>'
       . '</div></body></html>';
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
        : '<p class="bad">No se pudo borrar solo. Bórralo por FTP: <code>'
          . h(basename(__FILE__)) . '</code></p>') . '</div></div></body></html>';
    exit;
}

$libCandidatos = [__DIR__ . '/lib.php', dirname(__DIR__, 2) . '/public/lib.php'];
$lib = null;
foreach ($libCandidatos as $c) { if (is_file($c)) { $lib = $c; break; } }
if ($lib === null) {
    echo '<div class="card"><p class="bad">No encuentro <code>lib.php</code>. Este archivo va '
       . 'en la raíz de <code>/cumpleclick/</code>.</p></div></div></body></html>';
    exit;
}
require $lib;

$themesDir = is_dir(__DIR__ . '/themes') ? __DIR__ . '/themes' : dirname(__DIR__, 2) . '/public/themes';
$temaDir   = $themesDir . '/' . $TEMA;

/**
 * Los invitados de la fiesta. Cada uno con su mensaje, para que la revista
 * salga con variedad real: páginas de nota, mosaicos y fotos a sangre. Los que
 * no traen mensaje se agrupan en mosaico, que es justo lo que hace buildPages().
 *
 * Los mensajes son neutros en género y toman el nombre de $NOMBRE. Estaban
 * escritos para una niña llamada Isidora ("reina del hielo", "prima"), así que
 * al correr el script con otro tema y otro protagonista la revista salía
 * felicitando a alguien que no era.
 *
 * `cut` es el recorte del personaje que se usa SOLO si hay que componer la foto
 * con GD. Si existe la carpeta de fotos reales, se ignora.
 */
$INVITADOS = [
    ['cut' => 'elsa',     'autor' => 'La tía Carolina', 'mensaje' => 'Que tu cumpleaños sea tan lindo como tú, ' . $NOMBRE . '. Gracias por invitarnos, lo pasamos increíble toda la tarde.'],
    ['cut' => 'anna',     'autor' => 'Matías',          'mensaje' => null],
    ['cut' => 'olaf',     'autor' => 'Fernanda',        'mensaje' => 'Se te caía el gorro de tanto correr y no te importaba nada. Así te queremos.'],
    ['cut' => 'sven',     'autor' => 'Los primos',      'mensaje' => 'Prometimos no comernos toda la torta y no lo cumplimos. Perdón.'],
    ['cut' => 'bruni',    'autor' => 'Josefa',          'mensaje' => null],
    ['cut' => 'kristoff', 'autor' => 'El tío Rodrigo',  'mensaje' => 'Nunca había visto a alguien soplar las velas con tanta energía. ¡Feliz cumpleaños!'],
    ['cut' => 'elsa',     'autor' => 'Abuela Rosa',     'mensaje' => 'Te quiero con todo el corazón, ' . $NOMBRE . '. Que cumplas muchos más.'],
    ['cut' => 'anna',     'autor' => 'Camila',          'mensaje' => null],
    ['cut' => 'olaf',     'autor' => 'Vicente',         'mensaje' => null],
];

/**
 * Fotos reales, si las hay. Deja una carpeta `_fotos-demo` junto a este archivo
 * con jpg o png ordenados por nombre y se usan en vez de las compuestas con GD.
 * Sirve para dos cosas: fotos generadas para una demo comercial, y las fotos de
 * una fiesta de verdad cuando quieras mostrarle a un cliente su propio álbum.
 * Se toman en orden alfabético y se emparejan con la lista de arriba.
 */
$fotosDir = __DIR__ . '/_fotos-demo';
$FOTOS_REALES = [];
if (is_dir($fotosDir)) {
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        foreach (glob($fotosDir . '/*.' . $ext) ?: [] as $f) { $FOTOS_REALES[] = $f; }
    }
    sort($FOTOS_REALES, SORT_NATURAL);
}

/**
 * Consigue el póster del video: el primer cuadro que ve el invitado antes de
 * apretar play. Sin él la página del video es un rectángulo negro.
 *
 * Primero busca un JPEG hermano (`_video-demo.jpg`). Ese es el camino
 * confiable: en hosting compartido casi nunca hay ffmpeg y muchas veces
 * exec() viene deshabilitado, así que depender del binario significaba no
 * tener póster justo en producción, que es donde importa. Recién si no está
 * el archivo intenta extraerlo con ffmpeg, y si tampoco se puede devuelve
 * null sin quejarse: el video igual queda cargado.
 */
function cita_poster_de_video(string $videoPath, string $videoOriginal): ?string
{
    $hermano = preg_replace('/\.mp4$/i', '.jpg', $videoOriginal);
    if (is_string($hermano) && $hermano !== $videoOriginal && is_file($hermano)) {
        $copia = tempnam(sys_get_temp_dir(), 'ccpos') . '.jpg';
        if (copy($hermano, $copia)) { return $copia; }
        @unlink($copia);
    }

    if (!function_exists('exec')) { return null; }
    $destino = tempnam(sys_get_temp_dir(), 'ccpos') . '.jpg';
    $cmd = 'ffmpeg -y -loglevel error -ss 1.2 -i ' . escapeshellarg($videoPath)
         . ' -frames:v 1 -q:v 3 ' . escapeshellarg($destino) . ' 2>&1';
    $salida = []; $codigo = 1;
    @exec($cmd, $salida, $codigo);
    if ($codigo === 0 && is_file($destino) && filesize($destino) > 0) { return $destino; }
    @unlink($destino);
    return null;
}

/**
 * Compone una "foto de la fiesta" como la arma la cabina: el fondo de sala del
 * tema, el recorte del personaje encima, y un leve viñeteado para que no se vea
 * como un collage plano. Devuelve la ruta del JPEG temporal.
 */
function componer_foto(string $fondoPath, string $cutPath, int $ancho, int $alto, int $variante): ?string
{
    if (!function_exists('imagecreatetruecolor')) { return null; }
    $fondo = @imagecreatefromjpeg($fondoPath);
    $cut   = @imagecreatefrompng($cutPath);
    if (!$fondo || !$cut) {
        if ($fondo) { imagedestroy($fondo); }
        if ($cut) { imagedestroy($cut); }
        return null;
    }
    $lienzo = imagecreatetruecolor($ancho, $alto);
    imagealphablending($lienzo, true);

    // Fondo recortado a la caja, sin deformar: se escala por el lado que falta
    // y se centra. Deformar la sala se nota de inmediato.
    $fw = imagesx($fondo); $fh = imagesy($fondo);
    $escala = max($ancho / $fw, $alto / $fh);
    $nw = (int) ceil($fw * $escala); $nh = (int) ceil($fh * $escala);
    imagecopyresampled($lienzo, $fondo, (int) (($ancho - $nw) / 2), (int) (($alto - $nh) / 2), 0, 0, $nw, $nh, $fw, $fh);

    // El personaje ocupa cerca del 70% del alto y se apoya abajo, con un
    // desplazamiento horizontal por variante para que no salgan todas iguales.
    $cw = imagesx($cut); $ch = imagesy($cut);
    $destAlto = (int) ($alto * 0.70);
    $destAncho = (int) ($cw * ($destAlto / $ch));
    $offsets = [0.50, 0.34, 0.66, 0.42, 0.58];
    $cx = (int) ($ancho * $offsets[$variante % count($offsets)]) - (int) ($destAncho / 2);
    $cy = $alto - $destAlto - (int) ($alto * 0.04);
    imagecopyresampled($lienzo, $cut, $cx, $cy, 0, 0, $destAncho, $destAlto, $cw, $ch);

    // Viñeteado suave en las esquinas.
    for ($i = 0; $i < 26; $i++) {
        $alpha = 118 - $i * 4;
        if ($alpha <= 0) { break; }
        $color = imagecolorallocatealpha($lienzo, 0, 0, 0, $alpha);
        imagerectangle($lienzo, $i, $i, $ancho - 1 - $i, $alto - 1 - $i, $color);
    }

    $tmp = tempnam(sys_get_temp_dir(), 'ccfoto') . '.jpg';
    imagejpeg($lienzo, $tmp, 86);
    imagedestroy($lienzo); imagedestroy($fondo); imagedestroy($cut);
    return is_file($tmp) ? $tmp : null;
}

// ------------------------------------------------------------------ chequeos
$problemas = [];
try { $modo = cb_storage_mode(); } catch (Throwable $e) { $modo = '?'; $problemas[] = 'Configuración no legible: ' . $e->getMessage(); }
if ($modo !== 'db') { $problemas[] = 'storage_mode debe ser db para el álbum.'; }

if (!function_exists('cb_album_ensure')) {
    $problemas[] = 'lib.album.php no está cargado. Sube la versión actual de lib.php y lib.album.php.';
} elseif (!cb_album_feature_ready()) {
    $problemas[] = 'Las tablas del álbum no existen: falta aplicar la migración 007. '
                 . 'Quita 007_event_album de la lista $OMITIR de _at-migrar.php y córrelo.';
}
try { cb_pdo()->query('SELECT 1 FROM cc_event_profiles LIMIT 1'); }
catch (Throwable $e) { $problemas[] = 'Falta la tabla cc_event_profiles: corre _at-migrar.php (008 y 009).'; }

// La guía del tema (fondo de sala + recortes) sólo hace falta cuando hay que
// componer las fotos con GD. Con la carpeta `_fotos-demo` completa no se usa
// nada de eso, y exigirla igual dejaba el script inservible en cualquier tema
// que no fuera hielo: pedía los recortes de elsa, anna y olaf para armar una
// fiesta de Carreras con fotos propias.
$fondoSala = $temaDir . '/fondo-sala.jpg';
$hayFotosReales = count($FOTOS_REALES) >= count($INVITADOS);
if (!$hayFotosReales) {
    if (!function_exists('imagecreatetruecolor')) {
        $problemas[] = 'La extensión GD no está disponible: sin ella no se pueden componer las fotos.';
    }
    if (!is_file($fondoSala)) { $problemas[] = 'Falta ' . $fondoSala; }
    $cutsFaltantes = [];
    foreach ($INVITADOS as $inv) {
        if (!is_file($temaDir . '/' . $inv['cut'] . '-cut.png')) { $cutsFaltantes[] = $inv['cut']; }
    }
    if ($cutsFaltantes) {
        $problemas[] = 'Faltan recortes del tema: ' . implode(', ', array_unique($cutsFaltantes))
                     . '. O deja ' . count($INVITADOS) . ' fotos en la carpeta _fotos-demo y no hacen falta.';
    }
}

try {
    $existente = cb_load_parties();
    $parties = is_array($existente['parties'] ?? null) ? $existente['parties'] : [];
} catch (Throwable $e) { $parties = []; $problemas[] = 'No se pudieron leer las fiestas: ' . $e->getMessage(); }

$yaExiste = isset($parties[$SLUG]);

echo '<div class="card"><h2>Lo que se va a armar</h2>';
echo '<div class="paso"><b>1 · Fiesta</b><code>' . h($SLUG) . '</code> — ' . h($NOMBRE)
   . ', tema ' . h($TEMA) . ', plan Full</div>';
echo '<div class="paso"><b>2 · Ficha del cumpleañero</b>8 datos: gustos, tallas e ideas de regalo</div>';
echo '<div class="paso"><b>3 · Invitación</b>publicada, con su enlace bonito</div>';
echo '<div class="paso"><b>4 · Álbum</b>con su QR de carga y su enlace de revista</div>';
$conMensaje = count(array_filter($INVITADOS, static fn ($i) => !empty($i['mensaje'])));
echo '<div class="paso"><b>5 · Fotos</b>' . count($INVITADOS) . ' '
   . ($hayFotosReales
        ? 'tomadas de la carpeta <code>_fotos-demo</code>'
        : 'recreadas con GD desde la guía del tema (fondo de sala + recorte del personaje)')
   . ', ' . $conMensaje . ' con mensaje de invitado</div>';
echo '<div class="paso"><b>5b · Video</b>'
   . (is_file(__DIR__ . '/_video-demo.mp4')
        ? 'se agrega <code>_video-demo.mp4</code> como página de video'
        : 'sin <code>_video-demo.mp4</code>: el álbum queda sin página de video')
   . '</div>';
echo '<div class="paso"><b>6 · Curaduría</b>todas aprobadas, la primera marcada como portada</div>';
echo '<div class="paso"><b>7 · Revista</b>álbum publicado y listo para abrir</div>';
echo '<p class="mut">storage_mode: <code>' . h($modo) . '</code> · fiestas que se preservan: <strong>'
   . count($parties) . '</strong></p>';
if ($yaExiste) {
    echo '<p class="warn">La fiesta <code>' . h($SLUG) . '</code> ya existe. El script completa solo '
       . 'lo que falte; no duplica nada.</p>';
}
if ($problemas) {
    echo '<p class="bad">Antes hay que resolver:</p><ul>';
    foreach ($problemas as $p) { echo '<li class="bad">' . h($p) . '</li>'; }
    echo '</ul>';
}
echo '</div>';

if ($problemas) {
    echo '<div class="card"><p class="mut">No se creó nada.</p>'
       . '<form method="post"><input type="hidden" name="t" value="' . h($enviado) . '">'
       . '<input type="hidden" name="accion" value="autodestruir">'
       . '<button class="danger" type="submit">Borrar este archivo</button></form></div>'
       . '</div></body></html>';
    exit;
}

if ($accion !== 'crear') {
    echo '<div class="card"><p>Todavía no se ha escrito nada.</p>'
       . '<p class="warn">Los enlaces se muestran una sola vez. Cópialos.</p>'
       . '<form method="post"><input type="hidden" name="t" value="' . h($enviado) . '">'
       . '<input type="hidden" name="accion" value="crear">'
       . '<button type="submit">Armar la fiesta completa</button></form></div>'
       . '<div class="card"><form method="post">'
       . '<input type="hidden" name="t" value="' . h($enviado) . '">'
       . '<input type="hidden" name="accion" value="autodestruir">'
       . '<button class="danger" type="submit">Borrar este archivo del servidor</button>'
       . '</form></div></div></body></html>';
    exit;
}

// -------------------------------------------------------------------- crear
$log = [];
$enlaces = [];
$fatal = null;
$eventDate = gmdate('Y-m-d', strtotime('+21 days'));

try {
    // 1 · Fiesta
    if (!isset($parties[$SLUG])) {
        $parties[$SLUG] = [
            'public_slug' => $SLUG, 'admin_label' => 'CITA COMPLETA - ' . ucfirst($TEMA),
            'birthday_person_name' => $NOMBRE, 'theme_slug' => $TEMA, 'fecha' => $eventDate,
            'activa' => true, 'invitados' => [], 'service_plan' => 'full',
            'gallery_enabled' => false, 'creada' => gmdate('Y-m-d H:i:s'),
        ];
        if (!cb_save_parties(['parties' => $parties])) {
            throw new RuntimeException('cb_save_parties() falló.');
        }
        $log[] = '1 · Fiesta creada.';
    } else {
        $log[] = '1 · La fiesta ya existía, se reutiliza.';
    }
    $partyId = cb_party_db_id($SLUG);
    if ($partyId === null) { throw new RuntimeException('No se resolvió la fiesta.'); }

    // 2 · Ficha del cumpleañero
    cb_event_profile_save($partyId, [
        'enabled' => true, 'privacy_ack' => true, 'event_type' => 'child_birthday',
        'public_title' => 'Conoce a ' . $NOMBRE,
        'cta_label' => $GENERO === 'm' ? 'Conoce al cumpleañero' : 'Conoce a la cumpleañera',
        'intro_style' => 'magico', 'intro_phrase' => 'La magia está por comenzar',
        'section_order' => ['favorites', 'sizes', 'gifts', 'custom'],
    ], 'seed-cita-completa');
    cb_event_profile_replace_people($partyId, [[
        'display_name' => $NOMBRE, 'nickname' => '',
        'intro_text' => 'Le encanta la nieve, cantar y compartir con sus personas favoritas.',
        'is_public' => true, 'photo_public_consent' => false, 'photo_ai_consent' => false,
        'fields' => [
            ['section_key' => 'favorites', 'field_key' => 'color',    'label' => 'Colores favoritos', 'value' => 'Celeste y plateado',                'is_public' => true],
            ['section_key' => 'favorites', 'field_key' => 'music',    'label' => 'Música favorita',   'value' => 'Canciones para cantar',             'is_public' => true],
            ['section_key' => 'favorites', 'field_key' => 'hobby',    'label' => 'Le encanta',        'value' => 'Patinar y crear historias',         'is_public' => true],
            ['section_key' => 'sizes',     'field_key' => 'shirt',    'label' => 'Talla de polera',   'value' => '8',  'value_type' => 'size', 'is_public' => true],
            ['section_key' => 'sizes',     'field_key' => 'pants',    'label' => 'Talla de pantalón', 'value' => '8',  'value_type' => 'size', 'is_public' => true],
            ['section_key' => 'sizes',     'field_key' => 'shoe',     'label' => 'Calzado',           'value' => '30', 'value_type' => 'size', 'is_public' => true],
            ['section_key' => 'gifts',     'field_key' => 'wishlist', 'label' => 'Ideas de regalo',   'value' => 'Cuentos ilustrados y manualidades', 'is_public' => true],
            ['section_key' => 'custom',    'field_key' => 'snack',    'label' => 'Snack favorito',    'value' => 'Helado y frutillas',                'is_public' => true],
        ],
    ]], 'seed-cita-completa');
    $log[] = '2 · Ficha del cumpleañero lista (8 datos).';

    // 3 · Invitación
    $invs = cb_list_invitations($partyId);
    $publicada = null;
    foreach ($invs as $i) { if ((string) ($i['status'] ?? '') === 'published') { $publicada = $i; break; } }
    if ($publicada === null && $invs) {
        throw new RuntimeException('Hay una invitación sin publicar de un intento anterior. '
            . 'Bórrala desde el admin y vuelve a correr esto.');
    }
    if ($publicada === null) {
        $inv = cb_create_invitation([
            'party_id' => $partyId, 'theme_slug' => $TEMA, 'admin_label' => 'CITA COMPLETA',
            'birthday_person_name' => $NOMBRE, 'birthday_person_gender' => $GENERO,
            'event_date' => $eventDate, 'event_time' => '16:00',
            'address' => 'Salón de celebraciones CumpleClick',
            'message' => 'Te esperamos para compartir una tarde inolvidable.',
            'created_by' => 'seed-cita-completa',
        ]);
        if (empty($inv['ok'])) { throw new RuntimeException('Invitación: ' . (string) ($inv['error'] ?? '?')); }
        $src = $temaDir . '/fondo-banner.jpg';
        $key = cb_invitation_storage_key($SLUG, 'demo-invitacion', 1, 'jpg');
        $dst = cb_invitation_file_path($key);
        if ($dst === null) { throw new RuntimeException('invitation_dir inválido.'); }
        if (!is_dir(dirname($dst)) && !mkdir(dirname($dst), 0770, true) && !is_dir(dirname($dst))) {
            throw new RuntimeException('No se creó el storage de invitaciones.');
        }
        if (!copy($src, $dst)) { throw new RuntimeException('No se copió el fondo del tema.'); }
        $info = getimagesize($dst);
        $out = cb_save_invitation_output((int) $inv['id'], [
            'output_type' => 'personalized_image', 'asset_key' => 'demo-invitacion',
            'file_storage_key' => $key, 'status' => 'approved',
            'visual_source_json' => ['source' => 'existing-theme-background', 'theme_slug' => $TEMA],
            'file_mime' => 'image/jpeg', 'file_byte_size' => filesize($dst),
            'file_sha256' => hash_file('sha256', $dst),
            'width' => is_array($info) ? (int) $info[0] : 0,
            'height' => is_array($info) ? (int) $info[1] : 0,
        ]);
        if (empty($out['ok'])) { throw new RuntimeException('No se registró la imagen aprobada.'); }
        if (empty(cb_publish_invitation((int) $inv['id'], 'seed-cita-completa')['ok'])) {
            throw new RuntimeException('No se publicó la invitación.');
        }
        $invToken = (string) $inv['token'];
        $log[] = '3 · Invitación publicada.';
    } else {
        $invToken = cb_invitation_share_token((int) $publicada['id']);
        $log[] = '3 · La invitación ya existía, se reutiliza su enlace reconstruible.';
    }

    // 4 · Álbum + tokens
    $album = cb_album_ensure($partyId);
    $albumId = (int) $album['id'];
    // intake_enabled va explícito: cb_album_intake_open() lo exige y sin él
    // subir.php devuelve 403. La primera versión de este script no lo activaba y
    // la página de carga del invitado quedaba inalcanzable en la demo — con el
    // álbum en 'collecting' y el token válido, que es lo que despista.
    cb_album_update($albumId, [
        'title'          => 'El cumpleaños de ' . $NOMBRE,
        'subtitle'       => 'Todo lo que pasó esa tarde',
        'status'         => 'collecting',
        'intake_enabled' => 1,
        'intake_videos'  => 1,
    ]);
    $tokenCarga  = cb_album_issue_token($albumId, 'intake', null, 'seed-cita-completa');
    $tokenRevista = cb_album_issue_token($albumId, 'view', null, 'seed-cita-completa');
    $log[] = '4 · Álbum creado, con QR de carga y enlace de revista.';

    // 5 · Fotos recreadas con la guía del tema
    $creadas = 0; $duplicadas = 0;
    foreach ($INVITADOS as $n => $invitado) {
        // Foto real si la hay; si no, se compone con GD desde la guía del tema.
        // La copia es a propósito: cb_album_store_file mueve el archivo, y sin
        // copiar se llevaría el original de la carpeta _fotos-demo.
        $ext = 'jpg';
        if (isset($FOTOS_REALES[$n])) {
            $origen = $FOTOS_REALES[$n];
            $ext = strtolower(pathinfo($origen, PATHINFO_EXTENSION));
            if ($ext === 'jpeg') { $ext = 'jpg'; }
            $tmp = tempnam(sys_get_temp_dir(), 'ccreal') . '.' . $ext;
            if (!copy($origen, $tmp)) { throw new RuntimeException('No se copió ' . basename($origen)); }
        } else {
            $cutPath = $temaDir . '/' . $invitado['cut'] . '-cut.png';
            $tmp = componer_foto($fondoSala, $cutPath, 1080, 1350, $n);
            if ($tmp === null) { throw new RuntimeException('No se pudo componer la foto ' . ($n + 1)); }
        }

        $sha = hash_file('sha256', $tmp);
        if (cb_album_media_exists($albumId, $sha)) { $duplicadas++; @unlink($tmp); continue; }

        $key = cb_album_storage_key($SLUG, $ext);
        $guardado = cb_album_store_file($tmp, $key, false);
        if ($guardado === null) { throw new RuntimeException('No se guardó la foto ' . ($n + 1)); }
        $thumb = cb_album_make_thumbnail($guardado, $SLUG, $ext);
        $dim = getimagesize($guardado);

        $res = cb_album_record_media($albumId, $partyId, [
            // El access_token NO es opcional aunque la firma lo acepte nulo:
            // album-api.php sirve cada foto por ver-media.php?t=<access_token> y
            // salta con un `continue` silencioso las que no lo traen. Sin esto
            // el admin muestra las 9 aprobadas y el invitado abre una revista
            // vacía, sin un solo error en ninguna parte.
            'access_token' => cb_opaque_token(16),
            'source' => 'guest', 'media_kind' => 'image',
            'storage_key' => $key, 'thumb_storage_key' => $thumb,
            'original_name' => 'recuerdo-' . ($n + 1) . '.' . $ext,
            'mime' => ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? 'image/jpeg',
            'byte_size' => filesize($guardado), 'sha256' => $sha,
            'width' => is_array($dim) ? (int) $dim[0] : 0,
            'height' => is_array($dim) ? (int) $dim[1] : 0,
            'contributor_name' => $invitado['autor'],
            'contributor_message' => $invitado['mensaje'],
            'moderation_status' => 'pending',
            'consent_version' => cb_album_consent_version(),
        ]);
        if ($res !== 'ok') { throw new RuntimeException('La foto ' . ($n + 1) . ' devolvió: ' . $res); }
        $creadas++;
    }
    $log[] = '5 · ' . $creadas . ' fotos compuestas y cargadas'
           . ($duplicadas ? ' (' . $duplicadas . ' ya estaban)' : '') . '.';

    // 5b · El video de la fiesta, si lo hay. El álbum acepta video y la revista
    //      le arma su propia página, pero sin un video en la demo esa página no
    //      se ve nunca. Deja un `_video-demo.mp4` junto a este archivo.
    $videoDemo = null;
    foreach (['_video-demo.mp4', '_fotos-demo/_video-demo.mp4'] as $cand) {
        if (is_file(__DIR__ . '/' . $cand)) { $videoDemo = __DIR__ . '/' . $cand; break; }
    }
    if ($videoDemo === null) {
        $log[] = '5b · Sin video de demo (es opcional): no se agregó página de video.';
    } else {
        $tmpV = tempnam(sys_get_temp_dir(), 'ccvid') . '.mp4';
        if (!copy($videoDemo, $tmpV)) { throw new RuntimeException('No se copió el video de demo.'); }
        $shaV = hash_file('sha256', $tmpV);
        if (cb_album_media_exists($albumId, $shaV)) {
            @unlink($tmpV);
            $log[] = '5b · El video ya estaba cargado.';
        } else {
            $keyV = cb_album_storage_key($SLUG, 'mp4');
            $guardadoV = cb_album_store_file($tmpV, $keyV, false);
            if ($guardadoV === null) { throw new RuntimeException('No se guardó el video.'); }

            // El póster es opcional a propósito: en hosting compartido puede no
            // haber ffmpeg ni exec(). Sin póster el invitado ve el rectángulo del
            // reproductor en vez del primer cuadro, que es feo pero no rompe nada.
            $keyPoster = null; $anchoV = 0; $altoV = 0;
            $poster = cita_poster_de_video($guardadoV, $videoDemo);
            if ($poster !== null) {
                $dimV = getimagesize($poster);
                if (is_array($dimV)) { $anchoV = (int) $dimV[0]; $altoV = (int) $dimV[1]; }
                $keyPoster = cb_album_storage_key($SLUG, 'jpg');
                if (cb_album_store_file($poster, $keyPoster, false) === null) { $keyPoster = null; }
            }

            $resV = cb_album_record_media($albumId, $partyId, [
                'access_token' => cb_opaque_token(16),
                'source' => 'guest', 'media_kind' => 'video',
                'storage_key' => $keyV, 'poster_storage_key' => $keyPoster,
                'original_name' => 'las-velitas.mp4',
                'mime' => 'video/mp4',
                'byte_size' => filesize($guardadoV), 'sha256' => $shaV,
                'width' => $anchoV, 'height' => $altoV,
                'contributor_name' => 'La mamá de ' . $NOMBRE,
                'contributor_message' => 'El momento de las velitas, grabado desde la otra punta de la mesa.',
                'moderation_status' => 'pending',
                'consent_version' => cb_album_consent_version(),
            ]);
            if ($resV !== 'ok') { throw new RuntimeException('El video devolvió: ' . $resV); }
            $log[] = '5b · Video cargado' . ($keyPoster ? ' con póster.' : ' (sin póster: deja un _video-demo.jpg al lado del mp4).');
        }
    }

    // 6 · Curaduría: aprobar todo y marcar portada
    $medios = cb_album_list_media($albumId);
    $aprobadas = 0; $portada = null;
    foreach ($medios as $m) {
        if ((string) $m['moderation_status'] !== 'approved') {
            cb_album_set_moderation($albumId, (int) $m['id'], 'approved', 'seed-cita-completa');
            $aprobadas++;
        }
        if ($portada === null && (string) $m['media_kind'] === 'image') { $portada = (int) $m['id']; }
    }
    if ($portada !== null) { cb_album_update($albumId, ['cover_media_id' => $portada]); }
    $log[] = '6 · ' . $aprobadas . ' aprobadas, portada marcada.';

    // 7 · Publicar la revista
    cb_album_update($albumId, ['status' => 'published']);
    $log[] = '7 · Álbum publicado.';

    $enlaces = [
        ['Invitación (lo que recibe el invitado)', function_exists('cb_invitation_pretty_url')
            ? cb_invitation_pretty_url($invToken, $NOMBRE)
            : cb_invitation_public_url($invToken)],
        ['Invitación · respaldo largo', cb_invitation_public_url($invToken)],
        ['Cargar fotos (lo que abre el QR)', cb_album_intake_url($tokenCarga)],
        ['Cartel del QR para imprimir', cb_album_sign_url($tokenCarga)],
        ['La revista', cb_album_view_url($tokenRevista)],
        ['Admin · curaduría del álbum', cb_public_base_url() . '/admin/album.php?party=' . rawurlencode($SLUG)],
        ['Admin · invitaciones', cb_public_base_url() . '/admin/invitations.php?party=' . rawurlencode($SLUG)],
    ];
} catch (Throwable $e) {
    $fatal = $e->getMessage();
}

echo '<div class="card"><h2>Resultado</h2><pre>' . h(implode("\n", $log)) . '</pre>';
if ($fatal !== null) {
    echo '<p class="bad">Se detuvo: ' . h($fatal) . '</p>'
       . '<p class="mut">Lo de arriba sí quedó hecho. Corrige la causa y vuelve a abrir '
       . 'este script: retoma donde se cortó.</p>';
} else {
    echo '<p class="ok">Fiesta completa lista.</p>';
}
echo '</div>';

if ($enlaces) {
    echo '<div class="card"><h2>El recorrido, en orden</h2>'
       . '<p class="warn">Cópialos ahora. Los tokens no se pueden recuperar después.</p>';
    foreach ($enlaces as [$rotulo, $url]) {
        echo '<p style="margin:0 0 10px"><span class="mut">' . h($rotulo) . '</span><br>'
           . '<code>' . h($url) . '</code></p>';
    }
    echo '<p class="mut">Ábrelos en ese orden y vas a recorrer lo mismo que vive un cliente: '
       . 'recibe la invitación, conoce al cumpleañero, escanea el QR en la fiesta, sube su foto, '
       . 'tú la apruebas, y al final todos abren la revista.</p>'
       . '<p class="warn">Ojo con el enlace de <strong>cargar fotos</strong>: el álbum queda '
       . '<strong>publicado</strong>, y publicar cierra la recepción, así que va a devolver 403. '
       . 'Es el comportamiento correcto — durante la fiesta se recibe, después se publica. '
       . 'Para probar la carga como invitado, entra al admin del álbum y vuelve a abrir la '
       . 'recepción; el mismo QR sigue sirviendo.</p></div>';
}

echo '<div class="card"><p class="mut">¿Ya copiaste los enlaces? Entonces borra este archivo.</p>'
   . '<form method="post"><input type="hidden" name="t" value="' . h($enviado) . '">'
   . '<input type="hidden" name="accion" value="autodestruir">'
   . '<button class="danger" type="submit">Borrar este archivo del servidor</button></form></div>';

?>
</div></body></html>
