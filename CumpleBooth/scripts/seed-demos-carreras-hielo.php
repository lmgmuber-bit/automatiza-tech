<?php
/**
 * seed-demos-carreras-hielo.php — crea las dos fiestas demo comerciales
 * (Carreras/Vicente e Hielo/Isidora) con "Conoce al cumpleanero" completo y una
 * invitacion publicada cada una.
 *
 * SEGURO PARA PROD, a diferencia de storage/event-profile-demo/seed.php:
 * ese pasa solo las fiestas demo a cb_save_parties(), que BORRA toda fiesta
 * ausente del array (lib.php: DELETE FROM cc_parties WHERE public_slug=?).
 * Este carga primero las existentes y hace merge.
 *
 *   php scripts/seed-demos-carreras-hielo.php            # dry-run
 *   php scripts/seed-demos-carreras-hielo.php --apply    # escribe
 *
 * El token publico se imprime UNA sola vez: la BD guarda solo su hash.
 * Guardalo al correrlo o tendras que recrear la invitacion.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(2);
}

$root = dirname(__DIR__);
require $root . '/public/lib.php';

$apply = in_array('--apply', $argv, true);
$mode  = $apply ? 'APPLY' : 'DRY-RUN';

$demos = [
    'demo-carreras-vicente' => [
        'theme'      => 'carreras',
        'admin_label' => 'DEMO comercial - Carreras',
        'name'       => 'Vicente',
        'gender'     => 'm',
        'title'      => 'Conoce a Vicente',
        'cta'        => 'Conoce al cumpleañero',
        'style'      => 'epico',
        'phrase'     => 'La carrera está por comenzar',
        'intro_text' => 'Le encantan los autos, la velocidad y celebrar con toda su gente.',
        'fields'     => [
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
        'theme'      => 'hielo',
        'admin_label' => 'DEMO comercial - Reino de Hielo',
        'name'       => 'Isidora',
        'gender'     => 'f',
        'title'      => 'Conoce a Isidora',
        'cta'        => 'Conoce a la cumpleañera',
        'style'      => 'magico',
        'phrase'     => 'La magia está por comenzar',
        'intro_text' => 'Le encanta la nieve, cantar y compartir con sus personas favoritas.',
        'fields'     => [
            ['favorites', 'color',    'Colores favoritos', 'Celeste y plateado',                 'text'],
            ['favorites', 'music',    'Música favorita',   'Canciones para cantar',              'text'],
            ['favorites', 'hobby',    'Le encanta',        'Patinar y crear historias',          'text'],
            ['sizes',     'shirt',    'Talla de polera',   '8',                                  'size'],
            ['sizes',     'pants',    'Talla de pantalón', '8',                                  'size'],
            ['sizes',     'shoe',     'Calzado',           '30',                                 'size'],
            ['gifts',     'wishlist', 'Ideas de regalo',   'Cuentos ilustrados y manualidades',  'text'],
            ['custom',    'snack',    'Snack favorito',    'Helado y frutillas',                 'text'],
        ],
    ],
];

$eventDate = gmdate('Y-m-d', strtotime('+30 days'));

echo "== $mode ==\n";
echo 'storage_mode: ' . cb_storage_mode() . "\n";
if (!cb_event_profile_feature_enabled()) {
    echo "\n[!] AVISO: event_profile_enabled es FALSE en la configuracion.\n";
    echo "    Las fiestas y las invitaciones se crean igual, pero el boton\n";
    echo "    \"Conoce a ...\" NO se mostrara hasta que lo pongas en true.\n";
}

// --- merge, no reemplazo ----------------------------------------------------
$existingData = cb_load_parties();
$parties = is_array($existingData['parties'] ?? null) ? $existingData['parties'] : [];
echo "\nFiestas existentes que se PRESERVAN: " . count($parties) . "\n";

$nuevas = [];
foreach ($demos as $slug => $d) {
    if (isset($parties[$slug])) {
        echo "  - $slug ya existe, no se toca\n";
        continue;
    }
    $nuevas[] = $slug;
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
echo 'Fiestas demo a crear: ' . (count($nuevas) ? implode(', ', $nuevas) : '(ninguna)') . "\n";

if (!$apply) {
    echo "\nDry-run: no se escribio nada. Repite con --apply.\n";
    exit(0);
}
if (!$nuevas) {
    echo "\nNada que crear.\n";
    exit(0);
}

if (!cb_save_parties(['parties' => $parties])) {
    fwrite(STDERR, "ERROR: cb_save_parties() fallo. Nada garantizado.\n");
    exit(1);
}

$salida = [];
foreach ($demos as $slug => $d) {
    if (!in_array($slug, $nuevas, true)) {
        continue;
    }
    $partyId = cb_party_db_id($slug);
    if ($partyId === null) {
        fwrite(STDERR, "ERROR: no se resolvio la fiesta $slug.\n");
        exit(1);
    }

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
        if ($row[4] === 'size') {
            $f['value_type'] = 'size';
        }
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
        fwrite(STDERR, 'ERROR invitacion ' . $slug . ': ' . (string) ($inv['error'] ?? '?') . "\n");
        exit(1);
    }

    // Imagen aprobada: se reutiliza el fondo del tema, ya presente en PROD.
    $src = $root . '/public/themes/' . $d['theme'] . '/fondo-banner.jpg';
    if (!is_file($src)) {
        fwrite(STDERR, "ERROR: falta $src\n");
        exit(1);
    }
    $key = cb_invitation_storage_key($slug, 'demo-invitacion', 1, 'jpg');
    $dst = cb_invitation_file_path($key);
    if ($dst === null) {
        fwrite(STDERR, "ERROR: invitation_dir invalido.\n");
        exit(1);
    }
    if (!is_dir(dirname($dst)) && !mkdir(dirname($dst), 0770, true) && !is_dir(dirname($dst))) {
        fwrite(STDERR, "ERROR: no se creo el storage privado de invitaciones.\n");
        exit(1);
    }
    if (!copy($src, $dst)) {
        fwrite(STDERR, "ERROR: no se copio el fondo del tema.\n");
        exit(1);
    }
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
    if (empty($out['ok'])) {
        fwrite(STDERR, "ERROR: no se registro la imagen aprobada.\n");
        exit(1);
    }
    if (empty(cb_publish_invitation((int) $inv['id'], 'seed-demo-comercial')['ok'])) {
        fwrite(STDERR, "ERROR: no se publico la invitacion.\n");
        exit(1);
    }

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

echo "\n=== ENLACES (el token no se puede recuperar despues) ===\n";
foreach ($salida as $s) {
    echo "\n[" . strtoupper($s['tema']) . "]\n";
    echo '  Admin del perfil : ' . $s['admin']  . "\n";
    echo '  Plan Basico      : ' . $s['basico'] . "\n";
    echo '  Plan Full        : ' . $s['full']   . "\n";
}
echo "\nListo.\n";
