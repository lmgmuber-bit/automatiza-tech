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
require __DIR__ . '/lib-woff.php';

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
        // [nombre, retrato, mensaje]. El mensaje no es adorno: una foto CON
        // mensaje se lleva su propia página; sin mensaje se agrupan de a cuatro
        // en un mosaico y el álbum entero queda en cuatro páginas.
        'invitados' => [
            ['Camila Rojas', 'g1.png', 'Amanda, tu mamá te esperó con una paciencia que da envidia. Ya te queremos.'],
            ['Rosa Fuentes', 'g2.png', 'Que tengas la salud de tu bisabuela y el genio de tu papá, mi niña.'],
            ['Javiera y Paz', 'g3.png', 'Prometemos malcriarte apenas nos dejen. Firmado: tus tías.'],
            ['Rodrigo Salas', 'g4.png', 'Gracias por dejarnos ser parte de esta espera. Nos vemos afuera, Amanda.'],
            ['Ana María Soto', 'g5.png', 'Cuarenta años esperando ser abuela y valió cada uno.'],
            ['Matías Herrera', 'g6.png', 'Bienvenida al club de los que llegan tarde a todo. Es hereditario.'],
            ['Fernanda y Emilia', 'g7.png', 'Tu prima ya pregunta cuándo vas a poder jugar. Apúrate.'],
            ['Las chicas de la oficina', 'g8.png', 'Le dijimos a tu mamá que descansara. Se rió de nosotras.'],
            // La novena no sobra: la portada se lleva una foto y no entra a las
            // páginas de adentro, así que con ocho el álbum quedaba en nueve.
            ['Trinidad Miranda', 'g9.png', 'Te vamos a contar mil veces cómo fue este día. Prepárate.'],
        ],
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
        'invitados' => [
            ['Antonia Vera', 'b1.png', 'Tomás, tu mamá cantaba cuando creía que nadie la escuchaba. Ya te conocía.'],
            ['Los Contreras', 'b2.png', 'De parte de toda la familia: te esperamos hace rato, campeón.'],
            ['Don Hernán', 'b3.png', 'Cuando puedas caminar te enseño a pescar. Es un trato.'],
            ['Emilia Nuñez', 'b4.png', 'Gracias por hacernos tíos. No sabíamos que queríamos serlo.'],
            ['Abuela Marta', 'b5.png', 'Tienes las manos de tu abuelo. Todavía no naces y ya lo sé.'],
            ['Marcelo y Cristián', 'b6.png', 'Brindamos por ti antes de conocerte. Nos pareció justo.'],
            ['Valentina Rojas', 'b7.png', 'Que la vida te trate como te esperamos nosotros, Tomás.'],
            ['Los abuelos Pizarro', 'b8.png', 'Ya tenemos tu pieza lista. No hay apuro, pero ahí está.'],
            ['Tío Sergio', 'b9.png', 'Tu papá era igual de inquieto. Suerte, mamá.'],
        ],
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

    // La MISMA geometría que `getSquarePhotoGeometry()` en src/frameGeometry.js:
    // `frameBox` es el marco decorativo pintado en el fondo; adentro va un
    // cuadrado centrado, y ese cuadrado se mete FRAME_PHOTO_INSET_RATIO (8,5%)
    // por lado para no taparle el borde. Calcularlo distinto acá haría que el
    // demo mostrara la cara en un lugar donde el kiosco no la pone.
    $ox = $fb['x'] * 1080;
    $oy = $fb['y'] * 1920;
    $ow = $fb['w'] * 1080;
    $oh = $fb['h'] * 1920;
    $ladoMarco = min($ow, $oh);
    $inset = $ladoMarco * 0.085;
    $ladoFoto = max(1.0, $ladoMarco - $inset * 2);
    $px = (int) round($ox + ($ow - $ladoMarco) / 2 + $inset);
    $py = (int) round($oy + ($oh - $ladoMarco) / 2 + $inset);
    $pw = $ph = (int) round($ladoFoto);
    imagecopyresampled($lienzo, $cuad, $px, $py, 0, 0, $pw, $ph, $lado, $lado);
    imagedestroy($cuad);

    // El agradecimiento, con la tipografía, los colores y la posición del
    // kiosco (`composePhoto()` en src/App.jsx). Es el texto que el invitado se
    // lleva impreso, así que un demo que lo dibuja distinto no está mostrando
    // el producto.
    demo_texto_kiosco($lienzo, 1080, 1920, $fb, $tema['colors'], $invitado, $bebe);

    demo_marca_de_agua($lienzo, 1080, 1920);

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

/**
 * La marca de agua de CumpleClick, con la MISMA geometría del kiosco.
 *
 * `drawBrandWatermark()` en src/App.jsx la dibuja así: ancho = W*0.085,
 * abajo a la izquierda (x = W*0.03, y = H - alto - H*0.025), alfa 0.42.
 * Las cuatro medidas se copian de ahí; si el kiosco las cambia, esto queda
 * desalineado y hay que venir a actualizarlo.
 *
 * El isotipo original es SVG y GD no lee SVG, así que se usa un PNG
 * rasterizado del MISMO archivo. NO se recrea el logo: el kiosco dice
 * explícitamente "sin tarjeta ni recreaciones", y una versión parecida sería
 * peor que no ponerlo. Si el PNG no está, la foto sale sin marca y el guion
 * lo avisa, en vez de dibujar algo inventado.
 */
function demo_marca_de_agua($lienzo, int $W, int $H): bool
{
    static $avisado = false;
    $logoPng = getenv('CC_DEMO_LOGO') ?: '';
    if ($logoPng === '' || !is_file($logoPng)) {
        if (!$avisado) {
            fwrite(STDERR, "  (sin CC_DEMO_LOGO: las fotos salen sin marca de agua)
");
            $avisado = true;
        }
        return false;
    }
    $logo = imagecreatefrompng($logoPng);
    imagealphablending($logo, true);
    $lw = imagesx($logo);
    $lh = imagesy($logo);
    $markW = (int) round($W * 0.085);
    $markH = (int) round($markW * ($lh / $lw));
    $x = (int) round($W * 0.03);
    $y = (int) round($H - $markH - $H * 0.025);
    // GD no tiene globalAlpha: se compone en una capa y se mezcla al 42%.
    $capa = imagecreatetruecolor($markW, $markH);
    imagealphablending($capa, false);
    imagesavealpha($capa, true);
    imagefill($capa, 0, 0, imagecolorallocatealpha($capa, 0, 0, 0, 127));
    imagealphablending($capa, true);
    imagecopyresampled($capa, $logo, 0, 0, 0, 0, $markW, $markH, $lw, $lh);
    imagecopymerge_alpha($lienzo, $capa, $x, $y, $markW, $markH, 42);
    imagedestroy($capa);
    imagedestroy($logo);
    return true;
}

/** imagecopymerge() pierde la transparencia; esta variante la conserva. */
function imagecopymerge_alpha($dst, $src, int $x, int $y, int $w, int $h, int $pct): void
{
    $tmp = imagecreatetruecolor($w, $h);
    imagealphablending($tmp, false);
    imagesavealpha($tmp, true);
    imagecopy($tmp, $dst, 0, 0, $x, $y, $w, $h);
    imagealphablending($tmp, true);
    imagecopy($tmp, $src, 0, 0, 0, 0, $w, $h);
    imagecopymerge($dst, $tmp, $x, $y, 0, 0, $w, $h, $pct);
    imagedestroy($tmp);
}


/**
 * El agradecimiento, dibujado como lo dibuja el kiosco.
 *
 * `composePhoto()` (src/App.jsx) lo compone en canvas: dos líneas centradas
 * debajo del marco, en Baloo 2 800, relleno `--yellow` y contorno `--dark2` de
 * la temática, con sombra suave. Acá se replica lo mismo con GD.
 *
 * Antes esto salía con `imagestring()`, la fuente de mapa de bits que GD trae
 * adentro: tipografía de terminal, un solo tamaño y sin tildes —"Emilia Nunez",
 * "Muchas gracias" en gris de sistema—. Como es el texto que el invitado se
 * lleva impreso, un demo que lo dibuja así no está mostrando el producto.
 *
 * Las medidas se copian de composePhoto(). Si el kiosco las cambia, esto queda
 * desalineado y hay que venir a actualizarlo.
 */
function demo_texto_kiosco($lienzo, int $W, int $H, array $fb, array $colores, string $invitado, string $bebe): void
{
    $ttf = cc_baloo_ttf('800');
    if ($ttf === null) {
        throw new RuntimeException(
            'Falta @fontsource/baloo-2 en node_modules; corre `npm install` antes del seed. '
            . 'Sin la fuente real el texto saldría en la de mapa de bits de GD, sin tildes.'
        );
    }
    // canvas usa textBaseline 'middle'; imagettftext() posiciona por la línea
    // base alfabética. La diferencia es (ascender + descender) / 2 del propio
    // archivo de fuente, no una constante inventada.
    $medioEm = cc_sfnt_metricas($ttf)['medioEm'];

    $lineas = [
        ['texto' => 'Muchas gracias ' . $invitado,        'fs' => (int) round($W * 0.046), 'max' => $W * 0.86, 'min' => 14],
        ['texto' => 'por venir al baby shower de ' . $bebe, 'fs' => (int) round($W * 0.036), 'max' => $W * 0.88, 'min' => 12],
    ];
    // Encoge de a 2 hasta caber, igual que el kiosco: los nombres largos no
    // desbordan, se achican.
    foreach ($lineas as $i => $l) {
        while ($l['fs'] > $l['min'] && demo_ancho_texto($ttf, $l['fs'], $l['texto']) > $l['max']) {
            $l['fs'] -= 2;
        }
        $lineas[$i] = $l;
    }
    // El texto arranca debajo del marco COMPLETO, no debajo de la foto.
    $lineas[0]['y'] = $fb['y'] * $H + $fb['h'] * $H + $H * 0.018;
    $lineas[1]['y'] = $lineas[0]['y'] + $lineas[0]['fs'] * 1.35;

    $relleno = demo_color_hex($lienzo, $colores['yellow']);
    $borde = demo_color_hex($lienzo, $colores['dark2']);

    foreach ($lineas as $i => $l) {
        $grosor = max($i === 0 ? 3.0 : 2.0, $l['fs'] * 0.09);
        $x = demo_x_centrado($ttf, $l['fs'], $l['texto'], $W);
        $y = (int) round($l['y'] + $medioEm * $l['fs']);

        // Sombra (canvas: negro 50%, blur 10, offsetY 3). GD no tiene sombra ni
        // un desenfoque decente sobre alfa, así que se acumulan copias muy
        // transparentes en anillos: a esta escala el resultado es el mismo halo
        // y no hace falta una capa aparte.
        foreach ([3, 6, 9] as $radio) {
            for ($a = 0; $a < 8; $a++) {
                $ang = $a * M_PI / 4;
                imagettftext(
                    $lienzo, $l['fs'], 0,
                    (int) round($x + cos($ang) * $radio),
                    (int) round($y + 3 + sin($ang) * $radio),
                    imagecolorallocatealpha($lienzo, 0, 0, 0, 112), $ttf, $l['texto']
                );
            }
        }

        // Contorno: canvas dibuja lineWidth/2 hacia afuera de la silueta, así
        // que se repite el texto en un anillo de ese radio y el relleno tapa
        // la mitad de adentro.
        $r = $grosor / 2;
        for ($a = 0; $a < 16; $a++) {
            $ang = $a * M_PI / 8;
            imagettftext(
                $lienzo, $l['fs'], 0,
                (int) round($x + cos($ang) * $r),
                (int) round($y + sin($ang) * $r),
                $borde, $ttf, $l['texto']
            );
        }
        imagettftext($lienzo, $l['fs'], 0, $x, $y, $relleno, $ttf, $l['texto']);
    }
}

/** Ancho en px que ocupa el texto, para el encogido y el centrado. */
function demo_ancho_texto(string $ttf, int $fs, string $texto): float
{
    $b = imagettfbbox($fs, 0, $ttf, $texto);
    return (float) ($b[2] - $b[0]);
}

/**
 * X para que el texto quede centrado.
 *
 * `imagettftext()` recibe el origen de la línea base, no el borde del dibujo:
 * el primer glifo puede tener un margen izquierdo propio. `bbox[0]` es ese
 * margen y hay que descontarlo, o el texto queda unos píxeles corrido.
 */
function demo_x_centrado(string $ttf, int $fs, string $texto, int $W): int
{
    $b = imagettfbbox($fs, 0, $ttf, $texto);
    return (int) round(($W - ($b[2] - $b[0])) / 2 - $b[0]);
}

/** '#C4708C' -> color asignado en la imagen. */
function demo_color_hex($img, string $hex): int
{
    $h = ltrim(trim($hex), '#');
    if (strlen($h) === 3) {
        $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $h)) {
        throw new RuntimeException("Color inválido en themes.json: $hex");
    }
    return imagecolorallocate($img, (int) hexdec(substr($h, 0, 2)), (int) hexdec(substr($h, 2, 2)), (int) hexdec(substr($h, 4, 2)));
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
    $creditos = [];  // token de foto => [nombre, mensaje], para firmar el álbum
    foreach ($ev['invitados'] as $i => [$invitado, $archivoRetrato, $mensaje]) {
        $retrato = $RETRATOS . '/' . $archivoRetrato;
        if (!is_file($retrato)) {
            echo "  (falta el retrato $archivoRetrato, se omite)\n";
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
        $creditos[$tokFoto] = [$invitado, $mensaje];
        $hechas++;
    }
    echo "  fotos de cabina: $hechas\n";

    // ── Álbum de recuerdo ─────────────────────────────────────────────────
    $album = cb_album_ensure($partyId);
    $albumId = (int) $album['id'];
    $sumadas = cb_album_sync_booth_photos($albumId, $partyId);

    /* La sincronización enlaza las fotos de cabina por id y no copia autor ni
       mensaje: en el producto real esos los escribe el invitado desde su
       teléfono. Acá se rellenan para que el álbum se vea como uno usado de
       verdad — y además es lo que decide la paginación: una foto CON mensaje se
       lleva su propia página, sin mensaje se agrupan de a cuatro en un mosaico
       y el álbum entero queda en cuatro páginas. */
    $firmar = $pdo->prepare(
        'UPDATE cc_event_media m JOIN cc_photos ph ON ph.id = m.photo_id
         SET m.contributor_name = ?, m.contributor_message = ?
         WHERE m.album_id = ? AND ph.access_token = ?'
    );
    foreach ($creditos as $tok => [$quien, $mensajeInvitado]) {
        $firmar->execute([$quien, $mensajeInvitado, $albumId, $tok]);
    }
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
