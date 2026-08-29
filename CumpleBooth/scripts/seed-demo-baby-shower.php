<?php
/**
 * Dos baby showers TERMINADOS, de punta a punta: uno de niña y uno de niño.
 *
 * No es una invitación suelta: es el evento completo tal como queda después de
 * la fiesta — invitación publicada, lista de regalos con cosas ya tomadas,
 * apuestas hechas en la cabina, fotos de cabina compuestas dentro del marco, y
 * el álbum de recuerdo publicado con todo eso adentro.
 *
 * Sirve para mostrarle el producto a un cliente y para probar la cadena entera
 * sin tener que hacer la fiesta. Solo corre contra una base local: si
 * `public_base_url` no es localhost, aborta.
 *
 * Las caras de los invitados son personas generadas, no gente real. Se componen
 * con la MISMA geometría que usa el kiosco (`frameBox`, foto cuadrada dentro
 * del marco decorativo), así que lo que se ve en el álbum es lo que saldría de
 * la cabina de verdad.
 */
require __DIR__ . '/_cli.php';

$base = (string) cb_config('public_base_url');
if (stripos($base, 'localhost') === false && stripos($base, '127.0.0.1') === false) {
    fwrite(STDERR, "ABORTA: public_base_url no es local ($base).\n");
    exit(1);
}

/* Las caras de los invitados viven FUERA del repositorio a propósito: son
   personas generadas, no gente real, pero igual son fotos de caras y no tienen
   por qué viajar en el historial de git. La ruta se pasa por variable de
   entorno; sin retratos el script sigue corriendo y solo omite las fotos. */
$RETRATOS = getenv('CC_DEMO_RETRATOS') ?: '';
$pdo = cb_pdo();
$now = gmdate('Y-m-d H:i:s');

/** Los dos eventos. */
$EVENTOS = [
    [
        'slug' => 'demo-bs-nina',
        'tema' => 'baby-rosas',
        'bebe' => 'Amanda',
        'sexo' => 'f',
        'etiqueta' => 'DEMO Baby shower — niña (Rosas)',
        'fecha' => date('Y-m-d', strtotime('-9 days')),   // ya pasó: el álbum tiene sentido
        'invitados' => ['Camila Rojas', 'Rosa Fuentes', 'Javiera y Paz', 'Rodrigo Salas'],
        'retratos' => ['g1.png', 'g2.png', 'g3.png', 'g4.png'],
        'regalos' => [
            ['Coche liviano', 'Que se pliegue con una mano.', 'Camila Rojas'],
            ['Pañales talla recién nacido', 'Un paquete grande alcanza las primeras semanas.', 'Rosa Fuentes'],
            ['Mantita de algodón', '', null],
            ['Set de mudador', 'Con bolsillos, si se puede.', 'Javiera Soto'],
            ['Cuentos de tela', '', null],
            ['Cojín de lactancia', '', null],
        ],
        'apuestas' => [
            ['Camila', 'mama', 'entre', 'antes'],
            ['Rosa', 'papa', 'mas35', 'justo'],
            ['Javiera', 'ambos', 'entre', 'despues'],
            ['Paz', 'mama', 'menos3', 'justo'],
            ['Rodrigo', 'mama', 'entre', 'justo'],
        ],
    ],
    [
        'slug' => 'demo-bs-nino',
        'tema' => 'baby-nube',
        'bebe' => 'Tomás',
        'sexo' => 'm',
        'etiqueta' => 'DEMO Baby shower — niño (Nube)',
        'fecha' => date('Y-m-d', strtotime('-16 days')),
        'invitados' => ['Antonia Vera', 'Los Contreras', 'Don Hernán', 'Emilia Nuñez'],
        'retratos' => ['b1.png', 'b2.png', 'b3.png', 'b4.png'],
        'regalos' => [
            ['Cuna colecho', 'La que se engancha a la cama.', 'Antonia Vera'],
            ['Body manga larga, 0 a 3 meses', '', 'Familia Contreras'],
            ['Toallas de bambú', '', null],
            ['Móvil para la cuna', 'Sin luces que titilen, por favor.', 'Hernán Pizarro'],
            ['Termo para mamaderas', '', null],
            ['Silla para el auto', 'Es la que más nos falta.', null],
        ],
        'apuestas' => [
            ['Antonia', 'papa', 'mas35', 'despues'],
            ['Marcelo', 'ambos', 'entre', 'justo'],
            ['Hernán', 'papa', 'mas35', 'antes'],
            ['Emilia', 'mama', 'entre', 'justo'],
        ],
    ],
];

// ─────────────────────────────────────────────────────────────────────────────
// Compositor: la MISMA geometría del kiosco.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Compone la foto de cabina: fondo de la sala + la foto del invitado recortada
 * en cuadrado dentro del marco decorativo + el texto de agradecimiento.
 *
 * El `frameBox` sale de themes.json, igual que en el kiosco. Si se copiara a
 * mano acá, una recalibración del tema dejaría las fotos desalineadas sin que
 * nada avisara.
 */
function demo_componer(string $temaSlug, string $retrato, string $invitado, string $bebe, string $destino): array
{
    $temas = json_decode(file_get_contents(__DIR__ . '/../public/data/themes.json'), true)['themes'];
    $tema = $temas[$temaSlug] ?? null;
    if (!$tema) {
        throw new RuntimeException("Tema desconocido: $temaSlug");
    }
    $fb = $tema['frameBox'];

    $fondoPath = __DIR__ . '/../public/themes/' . $temaSlug . '/fondo-sala.jpg';
    $fondo = imagecreatefromjpeg($fondoPath);
    $lienzo = imagecreatetruecolor(1080, 1920);
    imagecopyresampled($lienzo, $fondo, 0, 0, 0, 0, 1080, 1920, imagesx($fondo), imagesy($fondo));
    imagedestroy($fondo);

    // La foto del invitado, recortada cuadrada al centro.
    $info = @getimagesize($retrato);
    $foto = match ($info[2] ?? 0) {
        IMAGETYPE_PNG => imagecreatefrompng($retrato),
        IMAGETYPE_JPEG => imagecreatefromjpeg($retrato),
        IMAGETYPE_WEBP => imagecreatefromwebp($retrato),
        default => throw new RuntimeException("Retrato ilegible: $retrato"),
    };
    $fw = imagesx($foto);
    $fh = imagesy($foto);
    $lado = min($fw, $fh);
    $cuad = imagecreatetruecolor($lado, $lado);
    imagecopy($cuad, $foto, 0, 0, (int) (($fw - $lado) / 2), (int) (($fh - $lado) / 2), $lado, $lado);
    imagedestroy($foto);

    $px = (int) round($fb['x'] * 1080);
    $py = (int) round($fb['y'] * 1920);
    $pw = (int) round($fb['w'] * 1080);
    $ph = (int) round($fb['h'] * 1920);
    imagecopyresampled($lienzo, $cuad, $px, $py, 0, 0, $pw, $ph, $lado, $lado);
    imagedestroy($cuad);

    // El agradecimiento, con velo para que se lea sobre cualquier fondo.
    for ($y = 1560; $y < 1920; $y++) {
        $a = (int) round(96 - (1920 - $y) / 360 * 96);
        imagefilledrectangle($lienzo, 0, $y, 1080, $y, imagecolorallocatealpha($lienzo, 0, 0, 0, 127 - $a));
    }
    $blanco = imagecolorallocate($lienzo, 255, 255, 255);
    // Sin tildes: la fuente de mapa de bits de GD no las dibuja. En el kiosco
    // real esto lo hace canvas con Baloo 2 y sale con acentos.
    $lineas = [
        'Gracias ' . demo_sin_tildes($invitado),
        'por venir al baby shower de ' . demo_sin_tildes($bebe),
    ];
    $y = 1690;
    foreach ($lineas as $i => $linea) {
        $fuente = $i === 0 ? 5 : 4;
        $ancho = imagefontwidth($fuente) * strlen($linea);
        imagestring($lienzo, $fuente, (int) ((1080 - $ancho) / 2), $y, $linea, $blanco);
        $y += 46;
    }

    // PNG y no JPG: `cb_photo_absolute_path()` valida la storage_key contra un
    // patrón que termina en `.png` y devuelve null para cualquier otra cosa. El
    // kiosco sube PNG, así que esto sigue el contrato del producto en vez de
    // inventar uno nuevo — con .jpg la foto se encuentra en la base pero la ruta
    // se rechaza, y el álbum sale con las imágenes rotas sin explicar por qué.
    imagepng($lienzo, $destino, 6);
    imagedestroy($lienzo);
    return [
        'bytes' => (int) filesize($destino),
        'sha256' => hash_file('sha256', $destino),
        'width' => 1080,
        'height' => 1920,
    ];
}

function demo_sin_tildes(string $s): string
{
    return strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','ü'=>'u']);
}

// ─────────────────────────────────────────────────────────────────────────────

$salida = [];

foreach ($EVENTOS as $ev) {
    echo "\n### {$ev['etiqueta']}\n";

    // ── Fiesta ────────────────────────────────────────────────────────────
    $q = $pdo->prepare('SELECT * FROM cc_parties WHERE public_slug = ?');
    $q->execute([$ev['slug']]);
    $fiesta = $q->fetch();
    if (!$fiesta) {
        $pdo->prepare(
            'INSERT INTO cc_parties (public_slug,birthday_person_name,event_type,admin_label,service_plan,
             gallery_enabled,theme_slug,event_date,active,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([$ev['slug'], $ev['bebe'], 'baby_shower', $ev['etiqueta'], 'full', 1, $ev['tema'], $ev['fecha'], 1, $now, $now]);
        $partyId = (int) $pdo->lastInsertId();
    } else {
        $partyId = (int) $fiesta['id'];
        $pdo->prepare('UPDATE cc_parties SET birthday_person_name=?,event_type="baby_shower",admin_label=?,theme_slug=?,event_date=?,active=1,gallery_enabled=1,service_plan="full",updated_at=? WHERE id=?')
            ->execute([$ev['bebe'], $ev['etiqueta'], $ev['tema'], $ev['fecha'], $now, $partyId]);
    }
    echo "  fiesta #$partyId\n";

    // ── Invitación publicada ──────────────────────────────────────────────
    $q = $pdo->prepare('SELECT id FROM cc_invitations WHERE party_id = ? ORDER BY id LIMIT 1');
    $q->execute([$partyId]);
    $invId = (int) ($q->fetchColumn() ?: 0);
    $token = cb_opaque_token(16);
    $campos = [cb_hash_token($token), $ev['tema'], $ev['etiqueta'], $ev['bebe'], $ev['sexo'], 'baby_shower',
               $ev['fecha'], '17:00', 'Av. Los Leones 455, Providencia, Santiago',
               'Gracias por acompanarnos. Fue una tarde preciosa.', $now];
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

    // Lámina de la invitación
    $dirInv = (string) cb_config('invitation_dir');
    $storageKey = cb_invitation_storage_key($ev['slug'], 'lamina', 1, 'jpg');
    $destinoInv = $dirInv . '/' . $storageKey;
    if (!is_dir(dirname($destinoInv))) {
        mkdir(dirname($destinoInv), 0770, true);
    }
    $banner = __DIR__ . '/../public/themes/' . $ev['tema'] . '/fondo-banner.jpg';
    copy($banner, $destinoInv);
    $pdo->prepare('DELETE FROM cc_invitation_outputs WHERE invitation_id=?')->execute([$invId]);
    cb_save_invitation_output($invId, [
        'output_type' => 'personalized_image', 'asset_key' => 'lamina',
        'file_storage_key' => $storageKey, 'status' => 'approved', 'file_mime' => 'image/jpeg',
        'file_byte_size' => (int) filesize($destinoInv), 'file_sha256' => hash_file('sha256', $destinoInv),
    ]);
    echo "  invitacion #$invId publicada\n";

    // ── Lista de regalos, con cosas ya tomadas ────────────────────────────
    $pdo->prepare('DELETE FROM cc_gift_items WHERE invitation_id=?')->execute([$invId]);
    foreach ($ev['regalos'] as [$titulo, $nota, $quien]) {
        $r = cb_gift_add($invId, ['title' => $titulo, 'notes' => $nota], 'parents');
        if (empty($r['ok'])) {
            throw new RuntimeException("regalo '$titulo': " . ($r['error'] ?? '?'));
        }
        if ($quien !== null) {
            $c = cb_gift_claim($invId, (int) $r['id'], $quien, bin2hex(random_bytes(16)));
            if (empty($c['ok'])) {
                throw new RuntimeException("reserva '$titulo': " . ($c['error'] ?? '?'));
            }
        }
    }
    $lista = cb_gift_list_public($invId);
    echo "  regalos: {$lista['total']} en la lista, {$lista['tomados']} ya tienen quien los lleve\n";

    // ── Apuestas de la cabina ─────────────────────────────────────────────
    $pdo->prepare('DELETE FROM cc_predictions WHERE party_id=?')->execute([$partyId]);
    foreach ($ev['apuestas'] as [$nombre, $parecido, $peso, $cuando]) {
        cb_prediction_create_for_party($partyId, [
            'guest_name' => $nombre, 'parecido' => $parecido, 'peso' => $peso, 'fecha' => $cuando,
        ]);
    }
    echo "  apuestas: " . count($ev['apuestas']) . "\n";

    // ── Fotos de cabina ───────────────────────────────────────────────────
    $pdo->prepare('DELETE FROM cc_photos WHERE party_id=?')->execute([$partyId]);
    $dirFotos = (string) cb_config('photo_dir');
    $hechas = 0;
    foreach ($ev['invitados'] as $i => $invitado) {
        $retrato = $RETRATOS . '/' . $ev['retratos'][$i];
        if (!is_file($retrato)) {
            echo "  (falta el retrato {$ev['retratos'][$i]}, se omite)\n";
            continue;
        }
        $tokFoto = bin2hex(random_bytes(16));
        $keyFoto = $ev['slug'] . '/' . gmdate('Y/m') . '/' . $tokFoto . '.png';
        $destinoFoto = $dirFotos . '/' . $keyFoto;
        if (!is_dir(dirname($destinoFoto))) {
            mkdir(dirname($destinoFoto), 0770, true);
        }
        $meta = demo_componer($ev['tema'], $retrato, $invitado, $ev['bebe'], $destinoFoto);
        $res = cb_record_photo_with_quota($ev['slug'], [
            'token' => $tokFoto, 'storage_key' => $keyFoto,
            'original_name' => 'cabina-' . ($i + 1) . '.png',
            'byte_size' => $meta['bytes'], 'width' => $meta['width'], 'height' => $meta['height'],
            'sha256' => $meta['sha256'], 'created_at' => $now,
        ]);
        if ($res !== 'ok') {
            throw new RuntimeException("foto de $invitado: $res");
        }
        $hechas++;
    }
    echo "  fotos de cabina: $hechas\n";

    // ── Álbum de recuerdo ─────────────────────────────────────────────────
    $album = cb_album_ensure($partyId);
    $albumId = (int) $album['id'];
    $sumadas = cb_album_sync_booth_photos($albumId, $partyId);
    // Todo lo que entró queda aprobado: este álbum representa uno ya revisado.
    foreach (cb_album_list_media($albumId) as $m) {
        cb_album_set_moderation($albumId, (int) $m['id'], 'approved', 'seed-demo');
    }
    cb_album_update($albumId, [
        'status' => 'published',
        'title' => 'El baby shower de ' . $ev['bebe'],
        'subtitle' => 'Gracias por acompañarnos',
        'require_pin' => 0,
        'published_at' => $now,
    ]);
    $tokenAlbum = cb_album_issue_token($albumId, 'view', null, 'seed-demo');
    $stats = cb_album_stats($albumId);
    echo "  album #$albumId: $sumadas fotos incorporadas, publicado\n";

    $tokenPapas = cb_invitation_issue_role_token($invId, 'parents', null, 'seed-demo');
    $salida[] = [
        'etiqueta' => $ev['etiqueta'],
        'invitacion' => cb_invitation_public_url($token),
        'album' => cb_album_view_url($tokenAlbum),
        'regalos' => cb_gift_board_url($tokenPapas),
        'predicciones' => cb_prediction_board_url($tokenPapas),
        'kiosco' => $base . '/?p=' . $ev['slug'],
    ];
}

echo "\n" . str_repeat('=', 74) . "\n";
foreach ($salida as $s) {
    echo $s['etiqueta'], "\n";
    foreach (['invitacion' => 'Invitación', 'album' => 'Álbum', 'regalos' => 'Regalos (papás)',
              'predicciones' => 'Predicciones', 'kiosco' => 'Kiosco'] as $k => $rotulo) {
        printf("  %-16s %s\n", $rotulo, $s[$k]);
    }
    echo str_repeat('-', 74), "\n";
}
