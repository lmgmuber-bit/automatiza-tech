<?php
/**
 * _at-seed-demos.php — crea las dos fiestas demo comerciales DESDE EL NAVEGADOR.
 * Para hostings sin SSH. Equivalente web de scripts/seed-demos-carreras-hielo.php.
 *
 * Crea Carreras/Vicente e Hielo/Isidora con "Conoce al cumpleañero" completo y
 * una invitación publicada cada una, y muestra los 6 enlaces.
 *
 * SEGURO PARA PROD: carga primero las fiestas existentes y hace merge.
 * (storage/event-profile-demo/seed.php NO lo es: pasa solo las demo a
 * cb_save_parties(), que BORRA toda fiesta ausente del array.)
 *
 * USO
 *   1. Edita $TOKEN aquí abajo y pon una clave larga tuya.
 *   2. Sube el archivo a /public_html/cumpleclick/_at-seed-demos.php
 *   3. Ábrelo, revisa el resumen (no escribe nada) y luego crea.
 *   4. COPIA LOS ENLACES. El token público de cada invitación se muestra una
 *      sola vez: la base guarda solo su hash.
 *   5. BÓRRALO con el botón rojo, o por FTP si falla.
 */

// ---------------------------------------------------------------- CONFIGURA
$TOKEN = 'deploy-cumpleclick-AT-19ago-7734gzvka49h7d2yq01xurmfojbip835subida-perfil-cumpleanero-2026';
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
<title>CumpleClick · demos</title>
<style>
 body{font:15px/1.55 system-ui,Segoe UI,sans-serif;background:#0f1720;color:#e6edf3;margin:0;padding:28px}
 .wrap{max-width:880px;margin:0 auto}
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
<h1>CumpleClick · fiestas demo Carreras e Hielo</h1>
<p class="sub">Bórrame del servidor apenas termines.</p>
<?php

if (strlen($TOKEN) < 20 || $TOKEN === 'CAMBIA-ESTO-ANTES-DE-SUBIR') {
    echo '<div class="card"><p class="bad">Este script está desactivado.</p>'
       . '<p class="mut">Edita <code>$TOKEN</code> arriba del archivo y pon una clave '
       . 'tuya de 20 caracteres o más. Después vuelve a subirlo.</p></div>'
       . '</div></body></html>';
    exit;
}

if (!$tokenOk) {
    if ($enviado !== '') { echo '<div class="card"><p class="bad">Token incorrecto.</p></div>'; }
    echo '<div class="card"><form method="post">'
       . '<label class="mut">Token</label><br>'
       . '<input type="password" name="t" autocomplete="off" autofocus><br>'
       . '<button type="submit">Entrar</button></form></div>'
       . '</div></body></html>';
    exit;
}

if ($accion === 'autodestruir') {
    $borrado = @unlink(__FILE__);
    echo '<div class="card">';
    echo $borrado
        ? '<p class="ok">Archivo borrado del servidor. Ya no existe esta URL.</p>'
        : '<p class="bad">No se pudo borrar solo (permisos). Bórralo por FTP: '
          . '<code>' . h(basename(__FILE__)) . '</code></p>';
    echo '</div></div></body></html>';
    exit;
}

// ------------------------------------------------------------------ contexto
$libCandidatos = [__DIR__ . '/lib.php', dirname(__DIR__, 2) . '/public/lib.php'];
$lib = null;
foreach ($libCandidatos as $c) { if (is_file($c)) { $lib = $c; break; } }
if ($lib === null) {
    echo '<div class="card"><p class="bad">No encuentro <code>lib.php</code>.</p>'
       . '<p class="mut">Este archivo tiene que quedar en la misma carpeta que '
       . '<code>lib.php</code> (la raíz de <code>/cumpleclick/</code>).</p></div>'
       . '</div></body></html>';
    exit;
}
require $lib;

$themesDir = is_dir(__DIR__ . '/themes')
    ? __DIR__ . '/themes'
    : dirname(__DIR__, 2) . '/public/themes';

$demos = [
    'demo-carreras-vicente' => [
        'theme'       => 'carreras',
        'admin_label' => 'DEMO comercial - Carreras',
        'name'        => 'Vicente',
        'gender'      => 'm',
        'title'       => 'Conoce a Vicente',
        'cta'         => 'Conoce al cumpleañero',
        'style'       => 'epico',
        'phrase'      => 'La carrera está por comenzar',
        'intro_text'  => 'Le encantan los autos, la velocidad y celebrar con toda su gente.',
        'fields'      => [
            ['favorites', 'color',    'Colores favoritos', 'Rojo y dorado',                   'text'],
            ['favorites', 'music',    'Música favorita',   'Canciones para bailar',           'text'],
            ['favorites', 'hobby',    'Le encanta',        'Armar pistas y dibujar',          'text'],
            ['sizes',     'shirt',    'Talla de polera',   '8',                               'size'],
            ['sizes',     'pants',    'Talla de pantalón', '8',                               'size'],
            ['sizes',     'shoe',     'Calzado',           '31',                              'size'],
            ['gifts',     'wishlist', 'Ideas de regalo',   'Autos a escala y kits creativos', 'text'],
            ['custom',    'snack',    'Snack favorito',    'Fruta y galletas',                'text'],
        ],
    ],
    'demo-hielo-isidora' => [
        'theme'       => 'hielo',
        'admin_label' => 'DEMO comercial - Reino de Hielo',
        'name'        => 'Isidora',
        'gender'      => 'f',
        'title'       => 'Conoce a Isidora',
        'cta'         => 'Conoce a la cumpleañera',
        'style'       => 'magico',
        'phrase'      => 'La magia está por comenzar',
        'intro_text'  => 'Le encanta la nieve, cantar y compartir con sus personas favoritas.',
        'fields'      => [
            ['favorites', 'color',    'Colores favoritos', 'Celeste y plateado',                'text'],
            ['favorites', 'music',    'Música favorita',   'Canciones para cantar',             'text'],
            ['favorites', 'hobby',    'Le encanta',        'Patinar y crear historias',         'text'],
            ['sizes',     'shirt',    'Talla de polera',   '8',                                 'size'],
            ['sizes',     'pants',    'Talla de pantalón', '8',                                 'size'],
            ['sizes',     'shoe',     'Calzado',           '30',                                'size'],
            ['gifts',     'wishlist', 'Ideas de regalo',   'Cuentos ilustrados y manualidades', 'text'],
            ['custom',    'snack',    'Snack favorito',    'Helado y frutillas',                'text'],
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
$perfilOn = false;
try { $perfilOn = cb_event_profile_feature_enabled(); } catch (Throwable $e) {}

// La migración 008 tiene que estar aplicada o esto revienta a medio camino,
// dejando la fiesta creada pero sin ficha ni invitación.
try {
    cb_pdo()->query('SELECT 1 FROM cc_event_profiles LIMIT 1');
} catch (Throwable $e) {
    $problemas[] = 'Falta la tabla cc_event_profiles: corre primero _at-migrar.php '
                 . '(migraciones 008 y 009).';
}

// Qué le falta a cada demo. Se mira paso por paso, no "existe la fiesta o no":
// una corrida interrumpida deja la fiesta hecha y el resto a medias.
$pendiente = [];
$completas = [];
foreach ($demos as $slug => $d) {
    $falta = [];
    $partyId = null;
    if (!isset($parties[$slug])) {
        $falta = ['fiesta', 'ficha', 'invitación'];
    } else {
        try {
            $partyId = cb_party_db_id($slug);
        } catch (Throwable $e) {
            $partyId = null;
        }
        if ($partyId === null) {
            $falta[] = 'fiesta';
        } elseif (!$problemas) {
            try {
                if (cb_event_profile_find_row($partyId) === null) { $falta[] = 'ficha'; }
                if (!cb_list_invitations($partyId)) { $falta[] = 'invitación'; }
            } catch (Throwable $e) {
                $falta[] = 'no se pudo consultar: ' . $e->getMessage();
            }
        }
    }
    if ($falta) { $pendiente[$slug] = $falta; } else { $completas[] = $slug; }
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
       . 'Las fiestas y las invitaciones se crean igual, pero el botón "Conoce a…" '
       . 'no aparecerá hasta que lo pongas en <code>true</code> en la configuración privada.</p>';
}
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
           . 'para cada demo: la fiesta, la ficha "Conoce al cumpleañero" con 8 datos, '
           . 'y una invitación publicada.</p>'
           . '<p class="warn">Los enlaces se muestran una sola vez. Cópialos.</p>'
           . '<form method="post"><input type="hidden" name="t" value="' . h($enviado) . '">'
           . '<input type="hidden" name="accion" value="crear">'
           . '<button type="submit">Crear las demos</button></form></div>';
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

        // La ficha se reescribe siempre: es data de demo nuestra, y así una
        // corrida interrumpida se completa sola al reintentar.
        cb_event_profile_save($partyId, [
            'enabled'       => true,
            'privacy_ack'   => true,
            'event_type'    => 'child_birthday',
            'public_title'  => $d['title'],
            'cta_label'     => $d['cta'],
            'intro_style'   => $d['style'],
            'intro_phrase'  => $d['phrase'],
            'section_order' => ['favorites', 'sizes', 'gifts', 'custom'],
        ], 'seed-demo-comercial');

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
        ]], 'seed-demo-comercial');
        $log[] = $slug . ': ficha del cumpleañero creada (8 datos).';

        // La invitación NO se rehace: crearía un token nuevo y dejaría dos.
        if (!in_array('invitación', $pendiente[$slug], true)) {
            $log[] = $slug . ': ya tenía invitación, no se crea otra. El enlace no se '
                   . 'puede volver a mostrar; si lo perdiste, regenera el token desde '
                   . 'el admin de invitaciones.';
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
            'created_by'             => 'seed-demo-comercial',
        ]);
        if (empty($inv['ok'])) {
            throw new RuntimeException('Invitación ' . $slug . ': ' . (string) ($inv['error'] ?? '?'));
        }

        // Imagen aprobada: se reutiliza el fondo del tema, ya presente en PROD.
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
        if (empty(cb_publish_invitation((int) $inv['id'], 'seed-demo-comercial')['ok'])) {
            throw new RuntimeException('No se publicó la invitación.');
        }
        $log[] = $slug . ': invitación publicada.';

        $base = cb_invitation_public_url((string) $inv['token']);
        $sep  = (strpos($base, '?') === false) ? '?' : '&';
        $salida[] = [
            'tema'   => $d['theme'],
            'fiesta' => $slug,
            'admin'  => cb_public_base_url() . '/admin/event-profile.php?party=' . rawurlencode($slug),
            'basico' => $base . $sep . 'hero=scroll&capitulos=1',
            'full'   => $base . $sep . 'hero=auto&capitulos=auto',
        ];
    }
} catch (Throwable $e) {
    $fatal = $e->getMessage();
}

echo '<div class="card"><h2>Resultado</h2><pre>' . h(implode("\n", $log)) . '</pre>';
if ($fatal !== null) {
    echo '<p class="bad">Se detuvo: ' . h($fatal) . '</p>'
       . '<p class="mut">Lo que aparece arriba sí quedó hecho. Corrige la causa y '
       . 'vuelve a abrir este script: las demos ya creadas no se duplican.</p>';
} else {
    echo '<p class="ok">Listo.</p>';
}
echo '</div>';

if ($salida) {
    echo '<div class="card"><h2>Enlaces</h2>'
       . '<p class="warn">Cópialos ahora. El token no se puede recuperar después: '
       . 'la base guarda solo su hash.</p>';
    foreach ($salida as $s) {
        echo '<p style="margin:16px 0 6px"><strong>' . h(strtoupper($s['tema'])) . '</strong></p>';
        echo '<p class="enlace"><b>Admin ficha</b> <code>' . h($s['admin'])  . '</code></p>';
        echo '<p class="enlace"><b>Plan Básico</b> <code>' . h($s['basico']) . '</code></p>';
        echo '<p class="enlace"><b>Plan Full</b> <code>'   . h($s['full'])   . '</code></p>';
    }
    echo '</div>';
}

echo '<div class="card"><p class="mut">¿Ya copiaste los enlaces? Entonces borra este archivo.</p>'
   . '<form method="post"><input type="hidden" name="t" value="' . h($enviado) . '">'
   . '<input type="hidden" name="accion" value="autodestruir">'
   . '<button class="danger" type="submit">Borrar este archivo del servidor</button></form></div>';

?>
</div></body></html>
