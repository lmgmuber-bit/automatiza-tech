<?php
declare(strict_types=1);

/**
 * Une el recorrido completo en UNA sola fiesta para las dos demos infantiles.
 *
 * El problema que arregla: las invitaciones de las temáticas infantiles se
 * sembraron con `seed-revision-local.php` sobre fiestas `rev-*`, que no tienen
 * ni invitados ni fotos; y las fiestas `demo-*` con invitados y fotos nunca
 * tuvieron invitación. Cada pieza funcionaba, pero el recorrido de punta a
 * punta —invitación → kiosco → álbum— no existía en ninguna fiesta sola. Se
 * revisaba la invitación de una y el kiosco de otra sin notarlo.
 *
 * Acá se publica la invitación SOBRE la fiesta que ya tiene los invitados y las
 * fotos, y se publica su álbum. Después queda igual que Amanda y Tomás.
 *
 * Lo que NO se toca, porque es de baby shower y sólo de baby shower:
 * apuestas (`cc_predictions`) y lista de regalos. `lib.predictions.php` rechaza
 * cualquier apuesta cuyo evento no sea `baby_shower`, e `invitacion.php`
 * condiciona la sección de regalos a `$esBabyShower`. Un cumpleaños con
 * apuestas seria un dato que el producto nunca va a mostrar.
 *
 * Idempotente: reejecutarlo actualiza lo mismo y reemite los tokens.
 *
 * Uso:  php scripts/unificar-demos-infantiles.php
 */

require __DIR__ . '/_cli.php';

$base = (string) cb_config('public_base_url');
if (stripos($base, 'localhost') === false && stripos($base, '127.0.0.1') === false) {
    fwrite(STDERR, "ABORTA: esto se corre en LOCAL. public_base_url = $base\n");
    exit(1);
}

$pdo = cb_pdo();
$now = gmdate('Y-m-d H:i:s');

/** Las dos fiestas que ya tienen invitados y fotos de cabina. */
$FIESTAS = [
    [
        'slug'      => 'demo-frozen-vip',
        'tema'      => 'hielo',
        'nombre'    => 'Isidora',
        'sexo'      => 'f',
        'etiqueta'  => 'Demo Frozen',
        'fecha'     => '2026-08-16',
        'hora'      => '16:30',
        'direccion' => 'Los Militares 5001, Las Condes, Santiago',
        'mensaje'   => 'Gracias por venir a celebrar con nosotros.',
        'titulo'    => 'La fiesta de Isidora',
    ],
    [
        'slug'      => 'demo-carreras',
        'tema'      => 'carreras',
        'nombre'    => 'Mateo',
        'sexo'      => 'm',
        'etiqueta'  => 'Demo Carreras',
        'fecha'     => '2026-08-23',
        'hora'      => '17:00',
        'direccion' => 'Av. Vitacura 4380, Vitacura, Santiago',
        'mensaje'   => 'Gracias por venir a celebrar con nosotros.',
        'titulo'    => 'La fiesta de Mateo',
    ],
];

/* Firmas para el álbum. La sincronización enlaza las fotos por id y NO copia
   autor ni mensaje —en el producto real los escribe el invitado desde su
   teléfono—, así que acá se rellenan para que el álbum se vea usado. Y no es
   decorativo: una foto CON mensaje se lleva su propia página y sin mensaje se
   agrupan de a cuatro, así que esto decide cuántas páginas tiene el álbum. */
$FIRMAS = [
    ['Martina Rojas',   'Se rió toda la tarde. Gracias por invitarnos.'],
    ['Tía Carolina',    'No quería irse. Eso lo dice todo.'],
    ['Los Fuentes',     'La mejor fiesta a la que hemos ido este año.'],
    ['Benjamín Soto',   'Quiero una igual para mi cumpleaños.'],
    ['Abuela Rosa',     'Guardo esta foto para siempre.'],
    ['Familia Herrera', 'Gracias por hacernos parte de este día.'],
    ['Emilia Castro',   'Volvió contando todo, sin parar.'],
    ['Don Patricio',    'Que se repita el próximo año.'],
    ['Los Miranda',     'Se llevó la foto pegada al pecho.'],
];

$salida = [];

foreach ($FIESTAS as $f) {
    $q = $pdo->prepare('SELECT id, theme_slug, service_plan FROM cc_parties WHERE public_slug = ?');
    $q->execute([$f['slug']]);
    $fiesta = $q->fetch();
    if (!$fiesta) {
        fwrite(STDERR, "No existe la fiesta {$f['slug']} en local.\n");
        exit(1);
    }
    $partyId = (int) $fiesta['id'];
    echo "\n### {$f['etiqueta']}  ({$f['slug']}, tema {$fiesta['theme_slug']}, plan {$fiesta['service_plan']})\n";

    // ── Invitación publicada, sobre ESTA fiesta ───────────────────────────
    $q = $pdo->prepare('SELECT id FROM cc_invitations WHERE party_id = ? ORDER BY id LIMIT 1');
    $q->execute([$partyId]);
    $invId = (int) ($q->fetchColumn() ?: 0);

    $token = cb_opaque_token(16);
    $campos = [cb_hash_token($token), $f['tema'], $f['etiqueta'], $f['nombre'], $f['sexo'],
               'child_birthday', $f['fecha'], $f['hora'], $f['direccion'], $f['mensaje'], $now];
    if ($invId === 0) {
        $pdo->prepare(
            'INSERT INTO cc_invitations (public_token_hash,theme_slug,admin_label,birthday_person_name,
             birthday_person_gender,event_type,event_date,event_time,address,message,updated_at,
             party_id,language,channel,status,visual_version,created_at,created_by,published_at,published_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute(array_merge($campos, [$partyId, 'es', 'link', 'published', 1, $now, 'seed-demo', $now, 'seed-demo']));
        $invId = (int) $pdo->lastInsertId();
    } else {
        $pdo->prepare(
            'UPDATE cc_invitations SET public_token_hash=?,theme_slug=?,admin_label=?,birthday_person_name=?,
             birthday_person_gender=?,event_type=?,event_date=?,event_time=?,address=?,message=?,updated_at=?,
             status="published",published_at=? WHERE id=?'
        )->execute(array_merge($campos, [$now, $invId]));
    }

    // Lámina, compuesta sobre el fondo real de la temática.
    $dirInv = (string) cb_config('invitation_dir');
    $storageKey = cb_invitation_storage_key($f['slug'], 'lamina', 1, 'jpg');
    $destinoInv = $dirInv . '/' . $storageKey;
    if (!is_dir(dirname($destinoInv))) {
        mkdir(dirname($destinoInv), 0770, true);
    }
    /* `carreras/fondo-banner.jpg` ES UN PNG con nombre .jpg: el navegador lo
       olfatea y no se nota, pero copiarlo tal cual deja un archivo cuyo
       contenido no coincide con su extensión. Se despacha por contenido. */
    $banner = __DIR__ . '/../public/themes/' . $f['tema'] . '/fondo-banner.jpg';
    $tipo = (int) (@getimagesize($banner)[2] ?? 0);
    if ($tipo === IMAGETYPE_PNG) {
        $im = imagecreatefrompng($banner);
        imagejpeg($im, $destinoInv, 90);
        imagedestroy($im);
    } else {
        copy($banner, $destinoInv);
    }
    $pdo->prepare('DELETE FROM cc_invitation_outputs WHERE invitation_id=?')->execute([$invId]);
    cb_save_invitation_output($invId, [
        'output_type' => 'personalized_image', 'asset_key' => 'lamina',
        'file_storage_key' => $storageKey, 'status' => 'approved', 'file_mime' => 'image/jpeg',
        'file_byte_size' => (int) filesize($destinoInv), 'file_sha256' => hash_file('sha256', $destinoInv),
    ]);
    echo "  invitacion #$invId publicada\n";

    // ── Álbum de recuerdo, con las fotos que la fiesta ya tiene ───────────
    $album = cb_album_ensure($partyId);
    $albumId = (int) $album['id'];
    $sumadas = cb_album_sync_booth_photos($albumId, $partyId);

    $medios = cb_album_list_media($albumId);
    $firmar = $pdo->prepare('UPDATE cc_event_media SET contributor_name = ?, contributor_message = ? WHERE id = ?');
    foreach ($medios as $i => $m) {
        [$quien, $mensaje] = $FIRMAS[$i % count($FIRMAS)];
        $firmar->execute([$quien, $mensaje, (int) $m['id']]);
        cb_album_set_moderation($albumId, (int) $m['id'], 'approved', 'seed-demo');
    }

    cb_album_update($albumId, [
        'status'       => 'published',
        'title'        => $f['titulo'],
        'subtitle'     => 'Gracias por celebrar con nosotros',
        'require_pin'  => 0,
        'published_at' => $now,
    ]);
    $tokenAlbum = cb_album_issue_token($albumId, 'view', null, 'seed-demo');

    // El número sale de la BASE, no de lo que creo que paso.
    $c = $pdo->prepare('SELECT COUNT(*) FROM cc_event_media WHERE album_id = ?');
    $c->execute([$albumId]);
    echo "  album #$albumId publicado: " . (int) $c->fetchColumn() . " fotos ($sumadas incorporadas ahora)\n";

    $salida[] = [
        'etiqueta'   => $f['etiqueta'],
        'slug'       => $f['slug'],
        'invitacion' => cb_invitation_public_url($token),
        'album'      => cb_album_view_url($tokenAlbum),
    ];
}

echo "\n" . str_repeat('=', 74) . "\n";
foreach ($salida as $s) {
    echo "{$s['etiqueta']}\n";
    echo "  Invitación  {$s['invitacion']}\n";
    echo "  Álbum       {$s['album']}\n";
    echo "  Kiosco      " . rtrim((string) cb_config('public_base_url'), '/') . "/?p={$s['slug']}\n";
}
