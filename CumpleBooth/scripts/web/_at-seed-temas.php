<?php
/**
 * _at-seed-temas.php — crea fiestas e invitaciones de prueba para kpop,
 * tropical y familia-canina, DESDE EL NAVEGADOR. Para hostings sin SSH.
 *
 * Hermano de _at-seed-demos.php, que hace lo mismo para Carreras e Hielo.
 * Misma lógica segura:
 *   - Carga primero las fiestas existentes y hace merge. No borra nada.
 *   - Mira paso por paso qué le falta a cada demo (fiesta, ficha, invitación),
 *     así una corrida interrumpida se completa al reintentar en vez de
 *     saltarse la demo por tener la fiesta ya creada.
 *   - No crea una segunda invitación si la fiesta ya tiene una.
 *
 * USO
 *   1. Edita $TOKEN y pon una clave larga tuya.
 *   2. Sube a /public_html/cumpleclick/_at-seed-temas.php y ábrelo.
 *   3. Revisa el resumen (no escribe nada) y confirma.
 *   4. COPIA LOS ENLACES. El token público se muestra una sola vez.
 *   5. BÓRRALO con el botón rojo.
 *
 * Los capítulos van a salir MUDOS: la narración de Alice de estos tres temas
 * es la tarea que quedó con Codex. El resto ya debería verse completo.
 */

// ---------------------------------------------------------------- CONFIGURA
$TOKEN = 'CAMBIA-ESTO-ANTES-DE-SUBIR';
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
<title>CumpleClick · temas de prueba</title>
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
 .enlace{margin:0 0 6px} .enlace b{display:inline-block;min-width:118px;color:#8fa3b8;font-weight:600}
 ul{margin:6px 0 0;padding-left:20px}
</style></head><body><div class="wrap">
<h1>CumpleClick · fiestas de prueba para kpop, tropical y familia-canina</h1>
<p class="sub">Bórrame del servidor apenas termines.</p>
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
          . h(basename(__FILE__)) . '</code></p>') . '</div></div></body></html>';
    exit;
}

// ------------------------------------------------------------------ contexto
$libCandidatos = [__DIR__ . '/lib.php', dirname(__DIR__, 2) . '/public/lib.php'];
$lib = null;
foreach ($libCandidatos as $c) { if (is_file($c)) { $lib = $c; break; } }
if ($lib === null) {
    echo '<div class="card"><p class="bad">No encuentro <code>lib.php</code>. Este archivo '
       . 'tiene que quedar en la raíz de <code>/cumpleclick/</code>.</p></div>'
       . '</div></body></html>';
    exit;
}
require $lib;

$themesDir = is_dir(__DIR__ . '/themes') ? __DIR__ . '/themes' : dirname(__DIR__, 2) . '/public/themes';

// Una fiesta por tema. El plan es full, pero desde el admin puedes verlas en
// Básico con el botón "Ver Básico (scroll)" sin tocar la base.
$demos = [
    'demo-kpop-martina' => [
        'theme'       => 'kpop',
        'admin_label' => 'PRUEBA - Guerreras K-Pop',
        'name'        => 'Martina',
        'gender'      => 'f',
        'title'       => 'Conoce a Martina',
        'cta'         => 'Conoce a la cumpleañera',
        'style'       => 'epico',
        'phrase'      => 'El escenario está por encenderse',
        'intro_text'  => 'Le encanta cantar, bailar y armar coreografías con sus amigas.',
        'fields'      => [
            ['favorites', 'color',    'Colores favoritos', 'Fucsia y morado',              'text'],
            ['favorites', 'music',    'Música favorita',   'K-Pop, obvio',                 'text'],
            ['favorites', 'hobby',    'Le encanta',        'Aprender coreografías',        'text'],
            ['sizes',     'shirt',    'Talla de polera',   '10',                           'size'],
            ['sizes',     'pants',    'Talla de pantalón', '10',                           'size'],
            ['sizes',     'shoe',     'Calzado',           '33',                           'size'],
            ['gifts',     'wishlist', 'Ideas de regalo',   'Micrófono de juguete y stickers', 'text'],
            ['custom',    'snack',    'Snack favorito',    'Palomitas y jugo',             'text'],
        ],
    ],
    'demo-tropical-benjamin' => [
        'theme'       => 'tropical',
        'admin_label' => 'PRUEBA - Tropical',
        'name'        => 'Benjamín',
        'gender'      => 'm',
        'title'       => 'Conoce a Benjamín',
        'cta'         => 'Conoce al cumpleañero',
        'style'       => 'magico',
        'phrase'      => 'La isla está por despertar',
        'intro_text'  => 'Le encanta el mar, la arena y los animales de la playa.',
        'fields'      => [
            ['favorites', 'color',    'Colores favoritos', 'Turquesa y naranjo',           'text'],
            ['favorites', 'music',    'Música favorita',   'Canciones de verano',          'text'],
            ['favorites', 'hobby',    'Le encanta',        'Buscar conchitas y nadar',     'text'],
            ['sizes',     'shirt',    'Talla de polera',   '8',                            'size'],
            ['sizes',     'pants',    'Talla de pantalón', '8',                            'size'],
            ['sizes',     'shoe',     'Calzado',           '31',                           'size'],
            ['gifts',     'wishlist', 'Ideas de regalo',   'Juegos de playa y libros del mar', 'text'],
            ['custom',    'snack',    'Snack favorito',    'Fruta y helado',               'text'],
        ],
    ],
    'demo-familia-canina-emilia' => [
        'theme'       => 'familia-canina',
        'admin_label' => 'PRUEBA - Familia Canina',
        'name'        => 'Emilia',
        'gender'      => 'f',
        'title'       => 'Conoce a Emilia',
        'cta'         => 'Conoce a la cumpleañera',
        'style'       => 'magico',
        'phrase'      => 'La casa está lista para celebrar',
        'intro_text'  => 'Le encantan los perritos, las historias y jugar con toda la familia.',
        'fields'      => [
            ['favorites', 'color',    'Colores favoritos', 'Celeste y amarillo',           'text'],
            ['favorites', 'music',    'Música favorita',   'Canciones para bailar',        'text'],
            ['favorites', 'hobby',    'Le encanta',        'Dibujar y cuidar a su mascota', 'text'],
            ['sizes',     'shirt',    'Talla de polera',   '6',                            'size'],
            ['sizes',     'pants',    'Talla de pantalón', '6',                            'size'],
            ['sizes',     'shoe',     'Calzado',           '29',                           'size'],
            ['gifts',     'wishlist', 'Ideas de regalo',   'Peluches y cuentos ilustrados', 'text'],
            ['custom',    'snack',    'Snack favorito',    'Galletas y leche',             'text'],
        ],
    ],
];

$eventDate = gmdate('Y-m-d', strtotime('+30 days'));

// ------------------------------------------------------------------ chequeos
$problemas = [];
try {
    $modo = cb_storage_mode();
} catch (Throwable $e) {
    $modo = '?';
    $problemas[] = 'Configuración no legible: ' . $e->getMessage();
}
try {
    $existente = cb_load_parties();
    $parties = is_array($existente['parties'] ?? null) ? $existente['parties'] : [];
} catch (Throwable $e) {
    $parties = [];
    $problemas[] = 'No se pudieron leer las fiestas actuales: ' . $e->getMessage();
}
foreach ($demos as $d) {
    $fondo = $themesDir . '/' . $d['theme'] . '/fondo-banner.jpg';
    if (!is_file($fondo)) { $problemas[] = 'Falta la imagen del tema: ' . $fondo; }
}
try {
    cb_pdo()->query('SELECT 1 FROM cc_event_profiles LIMIT 1');
} catch (Throwable $e) {
    $problemas[] = 'Falta la tabla cc_event_profiles: corre primero _at-migrar.php '
                 . '(migraciones 008 y 009).';
}
$perfilOn = false;
try { $perfilOn = cb_event_profile_feature_enabled(); } catch (Throwable $e) {}

// Aviso, no bloqueo: sin el hero scroll la invitación abre igual, pero el Plan
// Básico se reproduce solo y no se puede evaluar la diferencia entre planes.
$sinHero = [];
foreach ($demos as $d) {
    if (!is_file($themesDir . '/' . $d['theme'] . '/invitation/invitation-scroll-v1.mp4')) {
        $sinHero[] = $d['theme'];
    }
}

$pendiente = [];
$completas = [];
$aMedias = [];
foreach ($demos as $slug => $d) {
    $falta = [];
    if (!isset($parties[$slug])) {
        $falta = ['fiesta', 'ficha', 'invitación'];
    } else {
        try { $partyId = cb_party_db_id($slug); } catch (Throwable $e) { $partyId = null; }
        if ($partyId === null) {
            $falta[] = 'fiesta';
        } elseif (!$problemas) {
            try {
                if (cb_event_profile_find_row($partyId) === null) { $falta[] = 'ficha'; }
                // No basta con que exista la fila de invitación: crearla y
                // publicarla son dos pasos, y entre medio se copia la imagen
                // aprobada. Si eso falla, queda una invitación en borrador que
                // no sirve para nada. Preguntar solo "¿hay invitación?" daba la
                // demo por lista con un borrador roto adentro.
                $invs = cb_list_invitations($partyId);
                $publicadas = 0;
                foreach ($invs as $i) {
                    if ((string) ($i['status'] ?? '') === 'published') { $publicadas++; }
                }
                if ($publicadas === 0) {
                    $falta[] = 'invitación';
                    // Con un borrador dando vueltas no se crea otra encima: se
                    // avisa, para no dejar dos invitaciones de la misma fiesta.
                    if ($invs) { $aMedias[$slug] = count($invs); }
                }
            } catch (Throwable $e) {
                $falta[] = 'no se pudo consultar: ' . $e->getMessage();
            }
        }
    }
    if ($falta) { $pendiente[$slug] = $falta; } else { $completas[] = $slug; }
}
foreach ($aMedias as $slug => $n) {
    $problemas[] = $slug . ' tiene ' . $n . ' invitación(es) sin publicar, de un intento '
                 . 'anterior que no terminó. Bórrala desde el admin de invitaciones y '
                 . 'vuelve a correr esto, o quedarían dos invitaciones para la misma fiesta.';
}

echo '<div class="card"><h2>Estado</h2>';
echo '<p>storage_mode: <code>' . h($modo) . '</code><br>';
echo 'Fiestas existentes que se preservan: <strong>' . count($parties) . '</strong><br>';
echo 'Temas leídos desde: <code>' . h($themesDir) . '</code></p>';
if ($completas) {
    echo '<p class="ok">Ya completas, no se tocan: <code>'
       . implode('</code>, <code>', array_map('h', $completas)) . '</code></p>';
}
if ($pendiente) {
    echo '<p>Falta por crear:</p><ul>';
    foreach ($pendiente as $slug => $falta) {
        echo '<li><code>' . h($slug) . '</code> → ' . h(implode(', ', $falta)) . '</li>';
    }
    echo '</ul>';
} else {
    echo '<p class="mut">No falta nada.</p>';
}
if (!$perfilOn) {
    echo '<p class="warn">Aviso: <code>event_profile_enabled</code> está en <code>false</code>. '
       . 'Las fiestas y las invitaciones se crean igual, pero el botón "Conoce a…" no '
       . 'aparecerá hasta que lo pongas en <code>true</code>.</p>';
}
if ($sinHero) {
    echo '<p class="warn">Aviso: estos temas no tienen <code>invitation-scroll-v1.mp4</code>: <code>'
       . implode('</code>, <code>', array_map('h', $sinHero)) . '</code>. '
       . 'La invitación abre igual, pero el Plan Básico se reproducirá solo en vez de '
       . 'avanzar con el dedo, así que no vas a poder comparar los dos planes.</p>';
}
echo '<p class="mut">Recuerda: los capítulos de estos tres temas salen <strong>mudos</strong>. '
   . 'La narración de Alice es la tarea que quedó pendiente con Codex.</p>';
if ($problemas) {
    echo '<p class="bad">Problemas que hay que resolver antes:</p><ul>';
    foreach ($problemas as $p) { echo '<li class="bad">' . h($p) . '</li>'; }
    echo '</ul>';
}
echo '</div>';

if ($problemas) {
    echo '<div class="card"><p class="mut">No se creó nada.</p></div></div></body></html>';
    exit;
}

// ------------------------------------------------------------------- resumen
if ($accion !== 'crear') {
    if (!$pendiente) {
        echo '<div class="card"><p class="ok">No hay nada que crear.</p></div>';
    } else {
        echo '<div class="card"><p>Todavía no se ha escrito nada. Al confirmar se crea, '
           . 'para cada tema: la fiesta, la ficha "Conoce al cumpleañero" con 8 datos, '
           . 'y una invitación publicada.</p>'
           . '<p class="warn">Los enlaces se muestran una sola vez. Cópialos.</p>'
           . '<form method="post"><input type="hidden" name="t" value="' . h($enviado) . '">'
           . '<input type="hidden" name="accion" value="crear">'
           . '<button type="submit">Crear las 3 fiestas de prueba</button></form></div>';
    }
    echo '<div class="card"><form method="post">'
       . '<input type="hidden" name="t" value="' . h($enviado) . '">'
       . '<input type="hidden" name="accion" value="autodestruir">'
       . '<button class="danger" type="submit">Borrar este archivo del servidor</button>'
       . '</form></div></div></body></html>';
    exit;
}

// -------------------------------------------------------------------- crear
$log = [];
$salida = [];
$fatal = null;

try {
    foreach ($demos as $slug => $d) {
        if (isset($parties[$slug])) { continue; }
        $parties[$slug] = [
            'public_slug'          => $slug,
            'admin_label'          => $d['admin_label'],
            'birthday_person_name' => $d['name'],
            'theme_slug'           => $d['theme'],
            'fecha'                => $eventDate,
            'activa'               => true,
            'invitados'            => [],
            'service_plan'         => 'full',
            'gallery_enabled'      => false,
            'creada'               => gmdate('Y-m-d H:i:s'),
        ];
    }
    if (!cb_save_parties(['parties' => $parties])) {
        throw new RuntimeException('cb_save_parties() falló. No se garantiza nada.');
    }
    $log[] = 'Fiestas guardadas (merge, sin borrar las existentes).';

    foreach ($demos as $slug => $d) {
        if (!isset($pendiente[$slug])) { continue; }

        $partyId = cb_party_db_id($slug);
        if ($partyId === null) { throw new RuntimeException("No se resolvió la fiesta $slug."); }

        cb_event_profile_save($partyId, [
            'enabled'       => true,
            'privacy_ack'   => true,
            'event_type'    => 'child_birthday',
            'public_title'  => $d['title'],
            'cta_label'     => $d['cta'],
            'intro_style'   => $d['style'],
            'intro_phrase'  => $d['phrase'],
            'section_order' => ['favorites', 'sizes', 'gifts', 'custom'],
        ], 'seed-temas-prueba');

        $fields = [];
        foreach ($d['fields'] as $row) {
            $f = [
                'section_key' => $row[0],
                'field_key'   => $row[1],
                'label'       => $row[2],
                'value'       => $row[3],
                'is_public'   => true,
            ];
            if ($row[4] === 'size') { $f['value_type'] = 'size'; }
            $fields[] = $f;
        }
        cb_event_profile_replace_people($partyId, [[
            'display_name'         => $d['name'],
            'nickname'             => '',
            'intro_text'           => $d['intro_text'],
            'is_public'            => true,
            'photo_public_consent' => false,
            'photo_ai_consent'     => false,
            'fields'               => $fields,
        ]], 'seed-temas-prueba');
        $log[] = $slug . ': ficha del cumpleañero creada (8 datos).';

        if (!in_array('invitación', $pendiente[$slug], true)) {
            $log[] = $slug . ': ya tenía invitación, no se crea otra. El enlace se puede '
                   . 'recuperar desde el admin de invitaciones.';
            continue;
        }

        $inv = cb_create_invitation([
            'party_id'               => $partyId,
            'theme_slug'             => $d['theme'],
            'admin_label'            => $d['admin_label'],
            'birthday_person_name'   => $d['name'],
            'birthday_person_gender' => $d['gender'],
            'event_date'             => $eventDate,
            'event_time'             => '16:00',
            'address'                => 'Salón de celebraciones CumpleClick',
            'message'                => 'Te esperamos para compartir una tarde inolvidable.',
            'created_by'             => 'seed-temas-prueba',
        ]);
        if (empty($inv['ok'])) {
            throw new RuntimeException('Invitación ' . $slug . ': ' . (string) ($inv['error'] ?? '?'));
        }

        $src = $themesDir . '/' . $d['theme'] . '/fondo-banner.jpg';
        $key = cb_invitation_storage_key($slug, 'demo-invitacion', 1, 'jpg');
        $dst = cb_invitation_file_path($key);
        if ($dst === null) { throw new RuntimeException('invitation_dir inválido.'); }
        if (!is_dir(dirname($dst)) && !mkdir(dirname($dst), 0770, true) && !is_dir(dirname($dst))) {
            throw new RuntimeException('No se creó el storage privado de invitaciones.');
        }
        if (!copy($src, $dst)) { throw new RuntimeException('No se copió el fondo del tema.'); }

        $info = getimagesize($dst);
        $out = cb_save_invitation_output((int) $inv['id'], [
            'output_type'        => 'personalized_image',
            'asset_key'          => 'demo-invitacion',
            'file_storage_key'   => $key,
            'status'             => 'approved',
            'visual_source_json' => ['source' => 'existing-theme-background', 'theme_slug' => $d['theme']],
            'file_mime'          => 'image/jpeg',
            'file_byte_size'     => filesize($dst),
            'file_sha256'        => hash_file('sha256', $dst),
            'width'              => is_array($info) ? (int) $info[0] : 0,
            'height'             => is_array($info) ? (int) $info[1] : 0,
        ]);
        if (empty($out['ok'])) { throw new RuntimeException('No se registró la imagen aprobada.'); }
        if (empty(cb_publish_invitation((int) $inv['id'], 'seed-temas-prueba')['ok'])) {
            throw new RuntimeException('No se publicó la invitación.');
        }
        $log[] = $slug . ': invitación publicada.';

        $bonita = function_exists('cb_invitation_pretty_url')
            ? cb_invitation_pretty_url((string) $inv['token'], $d['name'])
            : '';
        $base = cb_invitation_public_url((string) $inv['token']);
        $sep  = (strpos($base, '?') === false) ? '?' : '&';
        $salida[] = [
            'tema'    => $d['theme'],
            'fiesta'  => $slug,
            'admin'   => cb_public_base_url() . '/admin/invitations.php?party=' . rawurlencode($slug),
            'bonita'  => $bonita,
            'larga'   => $base,
            'basico'  => $base . $sep . 'hero=scroll&capitulos=1',
            'full'    => $base . $sep . 'hero=auto&capitulos=auto',
        ];
    }
} catch (Throwable $e) {
    $fatal = $e->getMessage();
}

echo '<div class="card"><h2>Resultado</h2><pre>' . h(implode("\n", $log)) . '</pre>';
if ($fatal !== null) {
    echo '<p class="bad">Se detuvo: ' . h($fatal) . '</p>'
       . '<p class="mut">Lo que aparece arriba sí quedó hecho. Corrige la causa y vuelve '
       . 'a abrir este script: retoma solo lo que falta.</p>';
} else {
    echo '<p class="ok">Listo.</p>';
}
echo '</div>';

if ($salida) {
    echo '<div class="card"><h2>Enlaces</h2>'
       . '<p class="warn">Cópialos ahora. El token no se puede recuperar después.</p>';
    foreach ($salida as $s) {
        echo '<p style="margin:16px 0 6px"><strong>' . h(strtoupper($s['tema'])) . '</strong></p>';
        if ($s['bonita'] !== '') {
            echo '<p class="enlace"><b>Para el cliente</b> <code>' . h($s['bonita']) . '</code></p>';
        }
        echo '<p class="enlace"><b>Respaldo</b> <code>'    . h($s['larga'])  . '</code></p>';
        echo '<p class="enlace"><b>Admin</b> <code>'       . h($s['admin'])  . '</code></p>';
    }
    echo '<p class="mut">Para comparar los dos planes usa los botones '
       . '<strong>Ver Básico</strong> y <strong>Ver Full</strong> del admin de invitaciones: '
       . 'llevan la firma que hace falta. Pegar <code>&amp;hero=</code> a mano ya no sirve, '
       . 'el enlace del cliente entrega siempre lo que dice su plan.</p></div>';
}

echo '<div class="card"><p class="mut">¿Ya copiaste los enlaces? Entonces borra este archivo.</p>'
   . '<form method="post"><input type="hidden" name="t" value="' . h($enviado) . '">'
   . '<input type="hidden" name="accion" value="autodestruir">'
   . '<button class="danger" type="submit">Borrar este archivo del servidor</button></form></div>';

?>
</div></body></html>
