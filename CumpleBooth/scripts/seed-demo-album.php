<?php
declare(strict_types=1);

/**
 * seed-demo-album.php — puebla un Álbum Recuerdo de demo con contenido 100%
 * sintético (GD + ffmpeg locales, 0 créditos de IA), para QA visual real de
 * la revista: álbum de 100+ fotos, mezcla de layouts (mosaico, dúo, nota,
 * video) y portada/cierre.
 *
 * Las "fotos" son escenas de cumpleaños dibujadas por GD (globos, confeti,
   banderines, número de foto): ninguna cara, ningún menor, ningún personaje.
 * Pasa por la MISMA validación/thumbnails/registro que una subida real
 * (cb_album_validate_upload/store_file/make_thumbnail/record_media); lo único
 * que se salta es is_uploaded_file, porque acá no hay HTTP.
 *
 * Uso:
 *   php scripts/seed-demo-album.php                      → dry-run
 *   php scripts/seed-demo-album.php --apply              → siembra en demo-bluey-vip
 *   php scripts/seed-demo-album.php --apply --party=<slug> --photos=104
 *
 * Idempotente: si ya hay N piezas "demo-album-*" solo republica y emite un
 * token de lectura nuevo (revoca el anterior, como hace el admin).
 */

require __DIR__ . '/_cli.php';

$partySlug = (string) cc_cli_option('party', 'demo-bluey-vip');
$targetPhotos = max(4, (int) cc_cli_option('photos', '104'));

if (cb_storage_mode() !== 'db') {
    fwrite(STDERR, "El Álbum Recuerdo requiere storage_mode=db.\n");
    exit(1);
}
if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD no está disponible en este PHP CLI.\n");
    exit(1);
}

$partyId = cb_party_db_id($partySlug);
if ($partyId === null) {
    fwrite(STDERR, "No existe la fiesta '{$partySlug}'.\n");
    exit(1);
}
$party = cb_load_party_raw($partySlug);
$eventName = trim((string) ($party['birthday_person_name'] ?? $party['nombre'] ?? ''));
if ($eventName === '' || stripos($eventName, 'demo') !== false) {
    $eventName = 'Antonia'; // nombre de fantasía para la demo; nunca uno real
}

if (!cc_cli_require_apply()) {
    fwrite(STDOUT, "Sembraría {$targetPhotos} fotos + 1 video en el álbum de '{$partySlug}' y lo publicaría.\n");
    exit(0);
}

$album = cb_album_ensure($partyId);
$albumId = (int) $album['id'];
$pdo = cb_pdo();

// ── Contenido ya sembrado (idempotencia) ────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM cc_event_media WHERE album_id=? AND original_name LIKE 'demo-album-%' AND moderation_status <> 'removed'"
);
$stmt->execute([$albumId]);
$existing = (int) $stmt->fetchColumn();

$guestNames = ['Tía Rosa', 'Abuelo Juan', 'Mamá Vale', 'Papá Diego', 'Prima Josefa', 'Tío Andrés', 'Madrina Sol', 'Amigo Pipe'];
$messages = [
    '¡Feliz cumpleaños! Lo pasamos increíble, gracias por invitarnos.',
    'Que se repita mil veces. ¡Te queremos mucho!',
    'La mejor fiesta del año, sin discusión.',
    'Gracias por tanto cariño, salió todo precioso.',
    '¡A seguir cumpliendo sueños! Besos grandes.',
    'Qué torta más rica y qué linda decoración. ¡Felicitaciones!',
    'Nos encantó cada detalle. ¡Gracias por la invitación!',
    'Un día para no olvidar. ¡Feliz cumple!',
];

// Paletas de fiesta (ninguna ligada a una temática concreta): globos/confeti
// cambian de color foto a foto para que la revista no se vea repetida.
$palettes = [
    ['bg1' => [124, 58, 237], 'bg2' => [246, 239, 255], 'accents' => [[251, 191, 36], [214, 48, 127], [139, 92, 246], [255, 248, 236]]],
    ['bg1' => [16, 122, 87],  'bg2' => [224, 248, 238], 'accents' => [[255, 209, 102], [6, 95, 70], [52, 211, 153], [255, 255, 255]]],
    ['bg1' => [29, 78, 216],  'bg2' => [219, 234, 254], 'accents' => [[147, 197, 253], [250, 204, 21], [239, 246, 255], [30, 64, 175]]],
    ['bg1' => [190, 24, 93],  'bg2' => [252, 231, 243], 'accents' => [[249, 168, 212], [251, 207, 232], [255, 255, 255], [136, 19, 55]]],
    ['bg1' => [194, 65, 12],  'bg2' => [255, 237, 213], 'accents' => [[253, 186, 116], [250, 204, 21], [255, 247, 237], [154, 52, 18]]],
    ['bg1' => [59, 29, 94],   'bg2' => [237, 228, 251], 'accents' => [[232, 163, 23], [214, 48, 127], [255, 248, 236], [139, 92, 246]]],
];

$fontBold = 'C:\\Windows\\Fonts\\arialbd.ttf';
$hasTtf = is_file($fontBold) && function_exists('imagettftext');

/** Escena sintética de cumpleaños: degradado + banderines + globos + confeti. */
function cc_seed_draw_scene(int $w, int $h, array $palette, int $n, int $total, bool $hasTtf, string $fontBold)
{
    $img = imagecreatetruecolor($w, $h);

    // Degradado vertical entre los dos colores de fondo.
    [$r1, $g1, $b1] = $palette['bg1'];
    [$r2, $g2, $b2] = $palette['bg2'];
    for ($y = 0; $y < $h; $y++) {
        $t = $y / max(1, $h - 1);
        $c = imagecolorallocate($img,
            (int) round($r1 + ($r2 - $r1) * $t),
            (int) round($g1 + ($g2 - $g1) * $t),
            (int) round($b1 + ($b2 - $b1) * $t));
        imageline($img, 0, $y, $w, $y, $c);
    }

    // Banderines: dos guirnaldas de triángulos colgando de arriba.
    $flagIdx = 0;
    foreach ([0.06, 0.16] as $row) {
        $y0 = (int) ($h * $row);
        $step = (int) ($w / 12);
        for ($x = -$step; $x < $w + $step; $x += $step) {
            $accent = $palette['accents'][$flagIdx % count($palette['accents'])];
            $flagIdx++;
            $col = imagecolorallocate($img, $accent[0], $accent[1], $accent[2]);
            $dip = (int) ($step * 0.45);
            $points = [$x, $y0, $x + $step, $y0, $x + (int) ($step / 2), $y0 + $dip];
            // PHP 8.1+ deprecó el tercer argumento; PHP 8.0 (piso del proyecto) lo exige.
            if (PHP_VERSION_ID >= 80100) {
                imagefilledpolygon($img, $points, $col);
            } else {
                imagefilledpolygon($img, $points, 3, $col);
            }
        }
    }

    // Globos: elipses con brillo y cordel.
    $balloons = 3 + ($n % 3);
    for ($i = 0; $i < $balloons; $i++) {
        $accent = $palette['accents'][($n + $i) % count($palette['accents'])];
        $bx = (int) ($w * (0.12 + 0.76 * fmod($i * 0.37 + $n * 0.13, 1.0)));
        $by = (int) ($h * (0.34 + 0.38 * fmod($i * 0.53 + $n * 0.07, 1.0)));
        $bw = (int) ($w * 0.16);
        $bh = (int) ($bw * 1.25);
        $col = imagecolorallocate($img, $accent[0], $accent[1], $accent[2]);
        imagefilledellipse($img, $bx, $by, $bw, $bh, $col);
        $light = imagecolorallocatealpha($img, 255, 255, 255, 90);
        imagefilledellipse($img, $bx - (int) ($bw * 0.22), $by - (int) ($bh * 0.24), (int) ($bw * 0.3), (int) ($bh * 0.22), $light);
        imagesetthickness($img, max(2, (int) ($w / 500)));
        imageline($img, $bx, $by + (int) ($bh / 2), $bx - (int) ($bw * 0.1), min($h - 1, $by + (int) ($bh * 1.7)), $col);
    }

    // Confeti: puntos y tiras cortas al azar (semilla estable por foto).
    mt_srand(1337 + $n);
    for ($i = 0; $i < 90; $i++) {
        $accent = $palette['accents'][mt_rand(0, count($palette['accents']) - 1)];
        $col = imagecolorallocatealpha($img, $accent[0], $accent[1], $accent[2], mt_rand(20, 60));
        $cx = mt_rand(0, $w - 1);
        $cy = mt_rand((int) ($h * 0.2), $h - 1);
        if (mt_rand(0, 1)) {
            imagefilledellipse($img, $cx, $cy, (int) ($w * 0.012), (int) ($w * 0.012), $col);
        } else {
            imageline($img, $cx, $cy, $cx + mt_rand(-14, 14), $cy + mt_rand(-14, 14), $col);
        }
    }
    mt_srand();

    // Número de foto, grande y translúcido, para reconocerla en el QA.
    $label = '#' . $n;
    $caption = 'FOTO DEMO ' . $n . '/' . $total;
    if ($hasTtf) {
        $size = (int) ($w * 0.22);
        $shadow = imagecolorallocatealpha($img, 0, 0, 0, 70);
        $white = imagecolorallocatealpha($img, 255, 255, 255, 18);
        $box = imagettfbbox($size, 0, $fontBold, $label);
        $tw = $box[2] - $box[0];
        imagettftext($img, $size, 0, (int) (($w - $tw) / 2) + 4, (int) ($h * 0.78) + 4, $shadow, $fontBold, $label);
        imagettftext($img, $size, 0, (int) (($w - $tw) / 2), (int) ($h * 0.78), $white, $fontBold, $label);

        $size2 = max(12, (int) ($w * 0.032));
        $ink = imagecolorallocatealpha($img, 30, 20, 40, 30);
        $box2 = imagettfbbox($size2, 0, $fontBold, $caption);
        $tw2 = $box2[2] - $box2[0];
        imagettftext($img, $size2, 0, (int) (($w - $tw2) / 2), (int) ($h * 0.94), $ink, $fontBold, $caption);
    } else {
        imagestring($img, 5, 10, $h - 22, $caption, imagecolorallocate($img, 255, 255, 255));
    }

    return $img;
}

// ── 1. Fotos ────────────────────────────────────────────────────────────────
$saved = 0;
$errors = [];
$sizes = [
    [1080, 1920], // vertical de celular, como las de cabina
    [1600, 1200], // horizontal de cámara
    [1200, 1200], // cuadrada
    [1080, 1350], // vertical 4:5
];

for ($i = $existing + 1; $i <= $targetPhotos; $i++) {
    if ($i <= $targetPhotos - 8) {
        $withNote = false;
    } else {
        $withNote = true; // las últimas 8 llevan mensaje: páginas "nota"
    }
    [$w, $h] = $sizes[$i % count($sizes)];
    $palette = $palettes[$i % count($palettes)];

    $img = cc_seed_draw_scene($w, $h, $palette, $i, $targetPhotos, $hasTtf, $fontBold);
    $baseTmp = tempnam(sys_get_temp_dir(), 'cbseed');
    $tmpPath = $baseTmp . '.jpg';
    @unlink($baseTmp); // tempnam ya dejó un archivo sin extensión; se reemplaza
    if (!imagejpeg($img, $tmpPath, 82)) {
        imagedestroy($img);
        $errors[] = "foto {$i}: no se pudo escribir el JPEG temporal";
        continue;
    }
    imagedestroy($img);

    $byteSize = (int) filesize($tmpPath);
    $validation = cb_album_validate_upload($tmpPath, $byteSize, true);
    if (!$validation['ok']) {
        $errors[] = "foto {$i}: validación rechazó ({$validation['error']})";
        @unlink($tmpPath);
        continue;
    }
    $media = $validation['media'];
    $sha256 = hash_file('sha256', $tmpPath);

    $storageKey = cb_album_storage_key($partySlug, $media['ext']);
    $storedPath = cb_album_store_file($tmpPath, $storageKey, false);
    if ($storedPath === null) {
        $errors[] = "foto {$i}: no se pudo guardar en storage";
        @unlink($tmpPath);
        continue;
    }
    $thumbKey = cb_album_make_thumbnail($storedPath, $partySlug, $media['ext']);

    $guest = $withNote ? $guestNames[$i % count($guestNames)] : null;
    $message = $withNote ? $messages[$i % count($messages)] : null;

    $result = cb_album_record_media($albumId, $partyId, [
        'source' => 'guest',
        'media_kind' => 'image',
        'access_token' => cb_opaque_token(16),
        'storage_key' => $storageKey,
        'thumb_storage_key' => $thumbKey,
        'original_name' => sprintf('demo-album-foto-%03d.jpg', $i),
        'mime' => $media['mime'],
        'byte_size' => $byteSize,
        'width' => (int) $media['width'],
        'height' => (int) $media['height'],
        'sha256' => $sha256,
        'contributor_name' => $guest,
        'contributor_message' => $message,
        'moderation_status' => 'approved',
        'consent_version' => cb_album_consent_version(),
        'uploader_hmac' => hash('sha256', 'seed-demo-album'),
    ]);
    if ($result !== 'ok') {
        $errors[] = "foto {$i}: registro devolvió '{$result}'";
        continue;
    }
    $saved++;
}

// ── 2. Un video corto (ffmpeg local, ken burns sobre una escena) ────────────
$videoNote = null;
$videoCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM cc_event_media WHERE album_id={$albumId} AND original_name LIKE 'demo-album-video%' AND moderation_status <> 'removed'"
)->fetchColumn();

if ($videoCount === 0) {
    $ffmpeg = trim((string) @shell_exec('where ffmpeg 2>NUL'));
    if ($ffmpeg === '') {
        $errors[] = 'video: ffmpeg no está en PATH, se omite (la página de video no se ejercita)';
    } else {
        $posterImg = cc_seed_draw_scene(1080, 1920, $palettes[5], 999, $targetPhotos, $hasTtf, $fontBold);
        $posterBase = tempnam(sys_get_temp_dir(), 'cbseed');
        $posterTmp = $posterBase . '.jpg';
        @unlink($posterBase);
        imagejpeg($posterImg, $posterTmp, 85);
        imagedestroy($posterImg);

        $videoBase = tempnam(sys_get_temp_dir(), 'cbseed');
        $videoTmp = $videoBase . '.mp4';
        @unlink($videoBase);
        $cmd = 'ffmpeg -y -v error -loop 1 -i ' . escapeshellarg($posterTmp)
            . ' -f lavfi -i anullsrc=r=48000:cl=stereo'
            . ' -filter_complex "[0:v]scale=1440:2560,zoompan=z=\'1+0.0009*in\':x=\'iw/2-(iw/zoom/2)\':y=\'ih/2-(ih/zoom/2)\':d=200:s=720x1280:fps=25,format=yuv420p[v]"'
            . ' -map "[v]" -map 1:a -t 8 -c:v libx264 -preset veryfast -c:a aac -b:a 128k -shortest'
            . ' -movflags +faststart ' . escapeshellarg($videoTmp) . ' 2>&1';
        exec($cmd, $ffmpegOut, $ffmpegCode);
        @unlink($posterTmp);

        if ($ffmpegCode !== 0 || !is_file($videoTmp) || filesize($videoTmp) < 1000) {
            $errors[] = 'video: ffmpeg falló (' . implode(' | ', array_slice((array) $ffmpegOut, 0, 2)) . ')';
        } else {
            $byteSize = (int) filesize($videoTmp);
            $validation = cb_album_validate_upload($videoTmp, $byteSize, true);
            if (!$validation['ok']) {
                $errors[] = "video: validación rechazó ({$validation['error']})";
                @unlink($videoTmp);
            } else {
                $media = $validation['media'];
                $storageKey = cb_album_storage_key($partySlug, 'mp4');
                $storedPath = cb_album_store_file($videoTmp, $storageKey, false);
                if ($storedPath === null) {
                    $errors[] = 'video: no se pudo guardar en storage';
                    @unlink($videoTmp);
                } else {
                    $result = cb_album_record_media($albumId, $partyId, [
                        'source' => 'guest',
                        'media_kind' => 'video',
                        'access_token' => cb_opaque_token(16),
                        'storage_key' => $storageKey,
                        'original_name' => 'demo-album-video-001.mp4',
                        'mime' => 'video/mp4',
                        'byte_size' => $byteSize,
                        'width' => (int) ($media['width'] ?? 720),
                        'height' => (int) ($media['height'] ?? 1280),
                        'duration_seconds' => round((float) ($media['duration'] ?? 8), 2),
                        'sha256' => hash_file('sha256', $storedPath),
                        'contributor_name' => 'Familia completa',
                        'contributor_message' => '¡Que cumplas muchos más! Te grabamos este saludo con cariño.',
                        'moderation_status' => 'approved',
                        'consent_version' => cb_album_consent_version(),
                        'uploader_hmac' => hash('sha256', 'seed-demo-album'),
                    ]);
                    if ($result === 'ok') {
                        $videoNote = 'video sembrado (8s, 720x1280)';
                    } else {
                        $errors[] = "video: registro devolvió '{$result}'";
                    }
                }
            }
        }
    }
}

// ── 3. Publicar: portada = primera foto sembrada, título, sin PIN ──────────
$coverId = $pdo->query(
    "SELECT id FROM cc_event_media WHERE album_id={$albumId} AND original_name LIKE 'demo-album-%' AND media_kind='image' AND moderation_status='approved' ORDER BY id LIMIT 1"
)->fetchColumn();

cb_album_update($albumId, [
    'status' => 'published',
    'title' => 'Los recuerdos de ' . $eventName,
    'subtitle' => 'Su cumpleaños Nº 7',
    'cover_media_id' => $coverId !== false ? (int) $coverId : null,
    'require_pin' => 0,
    'published_at' => gmdate('Y-m-d H:i:s'),
]);

$token = cb_album_issue_token($albumId, 'view', null, 'seed-demo-album');

$totalMedia = (int) $pdo->query("SELECT COUNT(*) FROM cc_event_media WHERE album_id={$albumId} AND moderation_status <> 'removed'")->fetchColumn();

fwrite(STDOUT, "Álbum #{$albumId} de '{$partySlug}': +{$saved} fotos (total {$totalMedia} piezas aprobadas).\n");
if ($videoNote !== null) {
    fwrite(STDOUT, $videoNote . ".\n");
}
foreach ($errors as $err) {
    fwrite(STDOUT, "  ! {$err}\n");
}
fwrite(STDOUT, 'URL local: http://localhost/automatiza-tech/CumpleBooth/dist/album.html?t=' . $token . PHP_EOL);
