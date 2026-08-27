<?php
/**
 * Siembra local de revisión: una invitación publicada por temática.
 *
 * No es parte del producto. Sirve para abrir las ocho temáticas en el
 * navegador y mirarlas, sin crear catorce fiestas a mano. Sólo corre contra
 * una base local: si `public_base_url` no es localhost, aborta, porque este
 * script publica invitaciones y siembra datos falsos, y eso en producción
 * sería un desastre silencioso.
 *
 * La lámina de cada invitación se compone acá con GD sobre el
 * `fondo-banner.jpg` real del tema. No pretende ser la lámina definitiva —esa
 * la produce el flujo del admin— pero es 1080x1920, es del tema correcto, y
 * permite ver la página entera, la descarga y la tarjeta de WhatsApp.
 *
 * Es idempotente: al repetirlo reusa la fiesta y la invitación de cada tema en
 * vez de duplicarlas. Los tokens sí se emiten de nuevo —son de un solo uso por
 * diseño— y quedan impresos al final.
 */
require __DIR__ . '/_cli.php';

$base = (string) cb_config('public_base_url');
if (stripos($base, 'localhost') === false && stripos($base, '127.0.0.1') === false) {
    fwrite(STDERR, "ABORTA: public_base_url no es local ($base). Este script sólo siembra en local.\n");
    exit(1);
}

$pdo = cb_pdo();
$now = gmdate('Y-m-d H:i:s');
$fecha = date('Y-m-d', strtotime('+39 days'));

/** Las ocho temáticas, con quién celebra y de qué tipo es el evento. */
$temas = [
    // slug           nombre       sexo  tipo              etiqueta
    ['baby-nube',     'Valentina', 'f',  'baby_shower',    'Baby shower — Nube'],
    ['baby-safari',   '',          '',   'baby_shower',    'Baby shower — Safari (sin nombre ni sexo)'],
    ['hielo',         'Isidora',   'f',  'child_birthday', 'Cumpleanos — Hielo'],
    ['carreras',      'Mateo',     'm',  'child_birthday', 'Cumpleanos — Carreras'],
    ['kpop',          'Antonia',   'f',  'child_birthday', 'Cumpleanos — K-Pop'],
    ['heroes',        'Vicente',   'm',  'child_birthday', 'Cumpleanos — Heroes'],
    ['familia-canina', 'Emilia',   'f',  'child_birthday', 'Cumpleanos — Familia Canina'],
    ['tropical',      'Joaquin',   'm',  'child_birthday', 'Cumpleanos — Tropical'],
];

/** Compone una lámina 1080x1920 sobre el banner real del tema. */
function seed_lamina(string $tema, string $quien, string $fecha, string $destino): array
{
    $fondo = __DIR__ . '/../public/themes/' . $tema . '/fondo-banner.jpg';
    if (!is_file($fondo)) {
        throw new RuntimeException("Falta el fondo de $tema");
    }
    // Por contenido, no por extensión: `carreras/fondo-banner.jpg` es un PNG
    // con nombre .jpg. El navegador lo olfatea y no se nota, GD no.
    $info = @getimagesize($fondo);
    $src = match ($info[2] ?? 0) {
        IMAGETYPE_PNG => imagecreatefrompng($fondo),
        IMAGETYPE_WEBP => imagecreatefromwebp($fondo),
        IMAGETYPE_JPEG => imagecreatefromjpeg($fondo),
        default => throw new RuntimeException("Formato no reconocido en $tema"),
    };
    $lienzo = imagecreatetruecolor(1080, 1920);
    // El banner no siempre es 9:16: se recorta al centro en vez de deformarlo.
    $sw = imagesx($src);
    $sh = imagesy($src);
    $escala = max(1080 / $sw, 1920 / $sh);
    $dw = (int) round($sw * $escala);
    $dh = (int) round($sh * $escala);
    imagecopyresampled($lienzo, $src, (int) ((1080 - $dw) / 2), (int) ((1920 - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);

    // Un velo abajo, para que el texto se lea sobre cualquier fondo.
    for ($y = 1180; $y < 1920; $y++) {
        $a = (int) round(110 - (1920 - $y) / 740 * 110);
        imagefilledrectangle($lienzo, 0, $y, 1080, $y, imagecolorallocatealpha($lienzo, 0, 0, 0, 127 - $a));
    }

    $blanco = imagecolorallocate($lienzo, 255, 255, 255);
    $lineas = [
        $quien !== '' ? $quien : 'Nuestro bebe',
        'Te esperamos',
        date('d/m/Y', strtotime($fecha)) . ' - 16:30',
        'LAMINA DE REVISION LOCAL',
    ];
    $y = 1420;
    foreach ($lineas as $i => $linea) {
        $fuente = $i === 0 ? 5 : 4;
        $ancho = imagefontwidth($fuente) * strlen($linea);
        imagestring($lienzo, $fuente, (int) ((1080 - $ancho) / 2), $y, $linea, $blanco);
        $y += 60;
    }

    imagejpeg($lienzo, $destino, 88);
    imagedestroy($lienzo);
    return [
        'bytes' => (int) filesize($destino),
        'sha256' => hash_file('sha256', $destino),
    ];
}

$resultado = [];

foreach ($temas as [$tema, $quien, $sexo, $tipo, $etiqueta]) {
    $slug = 'rev-' . $tema;

    // Fiesta
    $stmt = $pdo->prepare('SELECT * FROM cc_parties WHERE public_slug = ?');
    $stmt->execute([$slug]);
    $fiesta = $stmt->fetch();
    if (!$fiesta) {
        $ins = $pdo->prepare(
            'INSERT INTO cc_parties (public_slug,birthday_person_name,event_type,admin_label,service_plan,
             gallery_enabled,theme_slug,event_date,active,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $ins->execute([$slug, $quien, $tipo, $etiqueta, 'full', 1, $tema, $fecha, 1, $now, $now]);
        $partyId = (int) $pdo->lastInsertId();
    } else {
        $partyId = (int) $fiesta['id'];
        $pdo->prepare(
            'UPDATE cc_parties SET birthday_person_name=?,event_type=?,admin_label=?,theme_slug=?,
             event_date=?,active=1,service_plan="full",updated_at=? WHERE id=?'
        )->execute([$quien, $tipo, $etiqueta, $tema, $fecha, $now, $partyId]);
    }

    // Invitación
    $stmt = $pdo->prepare('SELECT * FROM cc_invitations WHERE party_id = ? ORDER BY id LIMIT 1');
    $stmt->execute([$partyId]);
    $inv = $stmt->fetch();
    $token = cb_opaque_token(16);
    $hash = cb_hash_token($token);
    $mensaje = $tipo === 'baby_shower'
        ? 'Ven a esperar con nosotros. Nos vemos en la tarde, sin apuro.'
        : 'Trae ropa comoda: hay cabina de fotos y muchas ganas de celebrar.';
    $direccion = 'Av. Providencia 1234, Providencia, Santiago';

    if (!$inv) {
        $ins = $pdo->prepare(
            'INSERT INTO cc_invitations (public_token_hash,party_id,theme_slug,admin_label,birthday_person_name,
             birthday_person_gender,event_type,event_date,event_time,address,message,language,channel,status,
             visual_version,created_at,updated_at,created_by,published_at,published_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $ins->execute([
            $hash, $partyId, $tema, $etiqueta, $quien, $sexo, $tipo, $fecha, '16:30',
            $direccion, $mensaje, 'es', 'link', 'published',
            1, $now, $now, 'seed-revision', $now, 'seed-revision',
        ]);
        $invId = (int) $pdo->lastInsertId();
    } else {
        $invId = (int) $inv['id'];
        $pdo->prepare(
            'UPDATE cc_invitations SET public_token_hash=?,theme_slug=?,admin_label=?,birthday_person_name=?,
             birthday_person_gender=?,event_type=?,event_date=?,event_time=?,address=?,message=?,
             status="published",updated_at=?,published_at=? WHERE id=?'
        )->execute([
            $hash, $tema, $etiqueta, $quien, $sexo, $tipo, $fecha, '16:30',
            $direccion, $mensaje, $now, $now, $invId,
        ]);
    }

    // Lámina aprobada
    $dir = (string) cb_config('invitation_dir');
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    $storageKey = cb_invitation_storage_key($slug, 'lamina', 1, 'jpg');
    $destino = $dir . '/' . $storageKey;
    if (!is_dir(dirname($destino))) {
        mkdir(dirname($destino), 0770, true);
    }
    $meta = seed_lamina($tema, $quien, $fecha, $destino);

    $pdo->prepare('DELETE FROM cc_invitation_outputs WHERE invitation_id = ?')->execute([$invId]);
    cb_save_invitation_output($invId, [
        'output_type' => 'personalized_image',
        'asset_key' => 'lamina',
        'file_storage_key' => $storageKey,
        'status' => 'approved',
        'file_mime' => 'image/jpeg',
        'file_byte_size' => $meta['bytes'],
        'file_sha256' => $meta['sha256'],
    ]);

    $fila = [
        'tema' => $tema,
        'etiqueta' => $etiqueta,
        'invitacion' => cb_invitation_public_url($token),
    ];

    // Sólo baby shower: regalos, predicciones y el acceso de los papás.
    if ($tipo === 'baby_shower') {
        $pdo->prepare('DELETE FROM cc_gift_items WHERE invitation_id = ?')->execute([$invId]);
        $regalos = [
            ['Coche liviano', 'Que se pliegue con una mano.'],
            ['Panales talla RN', 'Un paquete grande alcanza para las primeras semanas.'],
            ['Mantita de algodon', ''],
            ['Set de mudador', 'Con bolsillos, si se puede.'],
            ['Cuentos de tela', ''],
        ];
        foreach ($regalos as [$titulo, $nota]) {
            // Claves en inglés a propósito: la traducción desde `titulo`/`nota`
            // vive en el borde HTTP (gift-api.php y regalos-papas.php), no acá.
            $r = cb_gift_add($invId, ['title' => $titulo, 'notes' => $nota], 'parents');
            if (empty($r['ok'])) {
                throw new RuntimeException("No se pudo agregar '$titulo': " . ($r['error'] ?? '?'));
            }
        }
        // Uno tomado, para ver el badge y comprobar que el invitado no ve el nombre.
        $primero = $pdo->prepare('SELECT id FROM cc_gift_items WHERE invitation_id = ? ORDER BY position LIMIT 1');
        $primero->execute([$invId]);
        $giftId = (int) $primero->fetchColumn();
        if ($giftId > 0) {
            $c = cb_gift_claim($invId, $giftId, 'Camila Rojas', bin2hex(random_bytes(16)));
            if (empty($c['ok'])) {
                throw new RuntimeException('No se pudo reservar el primer regalo: ' . ($c['error'] ?? '?'));
            }
        }

        $pdo->prepare('DELETE FROM cc_predictions WHERE party_id = ?')->execute([$partyId]);
        $apuestas = [
            ['Camila', 'mama', 'entre', 'antes'],
            ['Diego', 'papa', 'mas35', 'justo'],
            ['Fran', 'ambos', 'menos3', 'despues'],
            ['Pia', 'mama', 'entre', 'justo'],
        ];
        foreach ($apuestas as [$nombre, $parecido, $peso, $cuando]) {
            cb_prediction_create_for_party($partyId, [
                'guest_name' => $nombre,
                'parecido' => $parecido,
                'peso' => $peso,
                'fecha' => $cuando,
            ]);
        }

        $tokenPapas = cb_invitation_issue_role_token($invId, 'parents', null, 'seed-revision');
        $fila['predicciones'] = cb_prediction_board_url($tokenPapas);
        $fila['regalos'] = cb_gift_board_url($tokenPapas);
    }

    $resultado[] = $fila;
}

echo "\n";
foreach ($resultado as $r) {
    echo str_repeat('-', 74), "\n", $r['etiqueta'], "\n";
    echo "  Invitacion   ", $r['invitacion'], "\n";
    if (isset($r['predicciones'])) {
        echo "  Predicciones ", $r['predicciones'], "\n";
        echo "  Regalos      ", $r['regalos'], "\n";
    }
}
echo str_repeat('-', 74), "\n";
