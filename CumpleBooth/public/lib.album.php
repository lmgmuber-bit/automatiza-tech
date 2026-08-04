<?php
/**
 * lib.album.php — Álbum Recuerdo: álbum por evento, tokens de aporte y
 * material multimedia curado.
 *
 * Requiere storage_mode=db (igual que invitaciones). El material aportado vive
 * fuera del webroot, dentro de photo_dir, y solo se sirve por token opaco.
 *
 * Vocabulario genérico a propósito (event/contributor, nunca "cumpleañero"):
 * la misma estructura debe servir después para bodas, baby shower o eventos
 * corporativos sin renombrar nada.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Límites
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Todos los límites del módulo en un solo lugar. Son provisionales: el tamaño
 * real por álbum depende del espacio del hosting, que todavía está por
 * confirmar, así que se dejaron conservadores y centralizados para que
 * ajustarlos sea una línea y no una cacería por el código.
 */
function cb_album_limits(): array
{
    return [
        'files_per_submit' => 10,       // archivos por envío de un invitado
        'videos_per_submit' => 2,
        'image_max_bytes' => 12 * 1024 * 1024,
        'video_max_bytes' => 40 * 1024 * 1024,
        'video_max_seconds' => 30.0,
        'video_max_dimension' => 1920,
        'image_max_dimension' => 8000,  // una foto de celular moderna no pasa de aquí
        'album_max_files' => 400,
        'album_max_bytes' => 3 * 1024 * 1024 * 1024,
        'thumb_max_side' => 640,
        // El límite cuenta ARCHIVOS, no envíos: la página sube uno por
        // petición para poder mostrar progreso real y reintentar el que falle
        // sin perder los ya subidos. 30/10 min es el mismo techo que usa
        // upload.php para las fotos de cabina.
        'intake_rate_limit' => 30,
        'intake_rate_window' => 600,    // en 10 minutos
        'intake_rate_block' => 900,     // bloqueo de 15 minutos
        'default_open_days' => 7,       // días de recepción tras la fiesta
        'retention_days' => 90,         // el álbum vive más que los 30 de la foto suelta
    ];
}

/** Versión del texto de consentimiento aceptado por el invitado. */
function cb_album_consent_version(): string
{
    return 'album-intake-v1';
}

function cb_album_statuses(): array
{
    return ['draft', 'collecting', 'closed', 'published'];
}

function cb_album_moderation_states(): array
{
    return ['pending', 'approved', 'hidden', 'removed'];
}

function cb_album_sources(): array
{
    return ['booth', 'guest', 'organizer'];
}

/** Formatos aceptados: extensión canónica => mime real esperado. */
function cb_album_image_formats(): array
{
    return ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
}

// ─────────────────────────────────────────────────────────────────────────────
// Almacenamiento
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Las rutas del álbum viven bajo photo_dir/album/, separadas de las fotos de
 * cabina para que la retención pueda tratarlas distinto sin ambigüedad.
 * El nombre nunca contiene nada escrito por el invitado.
 */
function cb_album_storage_key(string $partySlug, string $ext): string
{
    if (!cb_valid_public_slug($partySlug)) {
        throw new InvalidArgumentException('public_slug inválido para almacenar media de álbum.');
    }
    $ext = strtolower(ltrim($ext, '.'));
    if (!in_array($ext, ['jpg', 'png', 'webp', 'mp4'], true)) {
        throw new InvalidArgumentException('Extensión no permitida en el álbum.');
    }
    return 'album/' . $partySlug . '/' . gmdate('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
}

function cb_album_media_path(string $storageKey): ?string
{
    if (!preg_match('#^album/[a-z0-9-]{1,80}/\d{4}/\d{2}/[a-f0-9]{32}\.(jpg|png|webp|mp4)$#', $storageKey)) {
        return null;
    }
    return cb_photo_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
}

// ─────────────────────────────────────────────────────────────────────────────
// Inspección de video sin ffprobe
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Lee duración y dimensiones de un MP4 recorriendo sus átomos, sin binarios
 * externos.
 *
 * Existe porque cb_inspect_video() depende de ffprobe y `ffprobe_path` no está
 * configurado (ni siquiera aparece en config/cumpleclick.example.php); en un
 * hosting compartido lo normal es que no exista. Sin este respaldo, todo video
 * de invitado se rechazaría por no poder inspeccionarse.
 *
 * Lee solo cabeceras: `mvhd` da timescale y duración del contenedor, y el
 * `tkhd` del track de video da ancho/alto. No decodifica un solo frame.
 *
 * Devuelve null si el archivo no es un MP4 legible — el llamador debe tratar
 * null como rechazo, nunca como aprobación.
 */
function cb_album_probe_mp4(string $path): ?array
{
    $fh = @fopen($path, 'rb');
    if ($fh === false) {
        return null;
    }
    // El tamaño se saca del handle, no de filesize(): PHP cachea filesize() por
    // ruta y el endpoint de carga escribe el video y lo inspecciona en la misma
    // petición, así que filesize() puede devolver el tamaño de un archivo
    // anterior con la misma ruta temporal.
    $stat = fstat($fh);
    $size = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
    if ($size < 16) {
        fclose($fh);
        return null;
    }

    $result = ['duration' => null, 'width' => 0, 'height' => 0];
    // Profundidad acotada: un MP4 legítimo no anida los átomos que buscamos más
    // allá de moov > trak > mdia > minf, y un archivo hostil no debe poder
    // hacernos recursar sin fin.
    $walk = static function (int $start, int $end, int $depth) use (&$walk, $fh, &$result): void {
        if ($depth > 6) {
            return;
        }
        $offset = $start;
        while ($offset + 8 <= $end) {
            if (fseek($fh, $offset) !== 0) { return; }
            $header = fread($fh, 8);
            if ($header === false || strlen($header) < 8) { return; }
            $parts = unpack('Nsize/a4type', $header);
            $boxSize = (int) $parts['size'];
            $type = (string) $parts['type'];
            $headerSize = 8;
            if ($boxSize === 1) {
                // Tamaño de 64 bits en el campo largesize. Si la parte alta no es
                // cero el átomo supera los 4 GiB: muy por encima de cualquier
                // límite del álbum, así que se abandona en vez de intentar leerlo.
                $large = fread($fh, 8);
                if ($large === false || strlen($large) < 8) { return; }
                if (unpack('N', substr($large, 0, 4))[1] !== 0) { return; }
                $boxSize = unpack('N', substr($large, 4, 4))[1];
                $headerSize = 16;
            } elseif ($boxSize === 0) {
                $boxSize = $end - $offset; // se extiende hasta el final
            }
            if ($boxSize < $headerSize || $offset + $boxSize > $end) { return; }

            $payloadStart = $offset + $headerSize;
            $payloadEnd = $offset + $boxSize;

            if ($type === 'moov' || $type === 'trak' || $type === 'mdia') {
                $walk($payloadStart, $payloadEnd, $depth + 1);
            } elseif ($type === 'mvhd' && $result['duration'] === null) {
                fseek($fh, $payloadStart);
                $body = fread($fh, 32);
                if ($body !== false && strlen($body) >= 20) {
                    $version = ord($body[0]);
                    if ($version === 0 && strlen($body) >= 20) {
                        $timescale = unpack('N', substr($body, 12, 4))[1];
                        $duration = unpack('N', substr($body, 16, 4))[1];
                    } elseif ($version === 1 && strlen($body) >= 32) {
                        $timescale = unpack('N', substr($body, 20, 4))[1];
                        $hi = unpack('N', substr($body, 24, 4))[1];
                        $lo = unpack('N', substr($body, 28, 4))[1];
                        $duration = ($hi << 32) | $lo;
                    } else {
                        $timescale = 0;
                        $duration = 0;
                    }
                    if ($timescale > 0 && $duration > 0) {
                        $result['duration'] = $duration / $timescale;
                    }
                }
            } elseif ($type === 'tkhd') {
                fseek($fh, $payloadStart);
                // tkhd mide 84 bytes en version 0 y 96 en version 1; width/height
                // son siempre los últimos 8, en punto fijo 16.16.
                $body = fread($fh, 96);
                if ($body !== false && strlen($body) >= 84) {
                    $version = ord($body[0]);
                    $boxLen = $version === 1 ? 96 : 84;
                    if (strlen($body) >= $boxLen) {
                        $tail = substr($body, $boxLen - 8, 8);
                        $w = unpack('N', substr($tail, 0, 4))[1] >> 16;
                        $h = unpack('N', substr($tail, 4, 4))[1] >> 16;
                        // El track de audio trae 0x0; nos quedamos con el de mayor
                        // área, que es el de video.
                        if ($w > 0 && $h > 0 && $w * $h > $result['width'] * $result['height']) {
                            $result['width'] = (int) $w;
                            $result['height'] = (int) $h;
                        }
                    }
                }
            }

            $offset += $boxSize;
        }
    };

    $walk(0, $size, 0);
    fclose($fh);

    if ($result['duration'] === null || $result['duration'] <= 0) {
        return null;
    }
    return $result;
}

/**
 * Inspección de video con degradación explícita: ffprobe si está configurado,
 * el lector de átomos si no. Devuelve null solo si ninguno pudo leer el
 * archivo, y en ese caso el llamador rechaza (fail closed).
 */
function cb_album_inspect_video(string $path): ?array
{
    $probe = cb_inspect_video($path);
    if ($probe !== null) {
        return $probe + ['source' => 'ffprobe'];
    }
    $fallback = cb_album_probe_mp4($path);
    if ($fallback === null) {
        return null;
    }
    return [
        'duration' => (float) $fallback['duration'],
        'codec' => '',            // el lector de átomos no identifica el códec
        'width' => (int) $fallback['width'],
        'height' => (int) $fallback['height'],
        'source' => 'mp4-atoms',
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Validación y almacenamiento de aportes
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Identifica el tipo real de un archivo mirando sus bytes, no su extensión ni
 * el Content-Type que mandó el navegador.
 *
 * Devuelve ['kind'=>'image'|'video', 'ext'=>..., 'mime'=>..., 'width', 'height']
 * o null si no es un formato aceptado. Todo lo que no reconozca se rechaza:
 * la lista es blanca, nunca negra.
 */
function cb_album_sniff_upload(string $path): ?array
{
    $info = @getimagesize($path);
    if (is_array($info)) {
        $type = $info[2] ?? null;
        $byType = [
            IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
            IMAGETYPE_PNG => ['png', 'image/png'],
            IMAGETYPE_WEBP => ['webp', 'image/webp'],
        ];
        if (!isset($byType[$type])) {
            return null;
        }
        [$ext, $mime] = $byType[$type];
        return [
            'kind' => 'image',
            'ext' => $ext,
            'mime' => $mime,
            'width' => (int) ($info[0] ?? 0),
            'height' => (int) ($info[1] ?? 0),
        ];
    }

    // No es imagen: única otra posibilidad aceptada es MP4.
    if (!cb_sniff_mp4($path)) {
        return null;
    }
    $probe = cb_album_inspect_video($path);
    if ($probe === null) {
        return null;
    }
    return [
        'kind' => 'video',
        'ext' => 'mp4',
        'mime' => 'video/mp4',
        'width' => (int) $probe['width'],
        'height' => (int) $probe['height'],
        'duration' => (float) $probe['duration'],
    ];
}

/**
 * Valida un archivo ya subido a disco temporal contra los límites del álbum.
 * Devuelve ['ok'=>true, 'media'=>[...]] o ['ok'=>false, 'error'=>'clave'].
 *
 * Las claves de error son estables y se traducen a mensajes en la página; no
 * se filtra al invitado ninguna ruta ni detalle interno.
 */
function cb_album_validate_upload(string $tmpPath, int $byteSize, bool $videosAllowed): array
{
    $limits = cb_album_limits();
    if ($byteSize <= 0) {
        return ['ok' => false, 'error' => 'empty'];
    }

    $sniff = cb_album_sniff_upload($tmpPath);
    if ($sniff === null) {
        return ['ok' => false, 'error' => 'format'];
    }

    if ($sniff['kind'] === 'image') {
        if ($byteSize > $limits['image_max_bytes']) {
            return ['ok' => false, 'error' => 'image_too_big'];
        }
        if ($sniff['width'] < 1 || $sniff['height'] < 1) {
            return ['ok' => false, 'error' => 'format'];
        }
        if ($sniff['width'] > $limits['image_max_dimension'] || $sniff['height'] > $limits['image_max_dimension']) {
            return ['ok' => false, 'error' => 'image_dimensions'];
        }
    } else {
        if (!$videosAllowed) {
            return ['ok' => false, 'error' => 'videos_disabled'];
        }
        if ($byteSize > $limits['video_max_bytes']) {
            return ['ok' => false, 'error' => 'video_too_big'];
        }
        if (($sniff['duration'] ?? 0.0) > $limits['video_max_seconds']) {
            return ['ok' => false, 'error' => 'video_too_long'];
        }
        $longest = max($sniff['width'], $sniff['height']);
        // 0x0 significa que no se pudo leer tkhd; se acepta porque la duración
        // sí se validó y el peso ya acota el daño, pero no se inventa un tamaño.
        if ($longest > $limits['video_max_dimension']) {
            return ['ok' => false, 'error' => 'video_dimensions'];
        }
    }

    return ['ok' => true, 'media' => $sniff];
}

/**
 * Genera una miniatura JPEG con GD. Devuelve la storage key de la miniatura o
 * null si GD no puede con este archivo — la ausencia de miniatura degrada la
 * revista, no la rompe, así que nunca aborta la subida.
 */
function cb_album_make_thumbnail(string $sourcePath, string $partySlug, string $ext): ?string
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $loaders = ['jpg' => 'imagecreatefromjpeg', 'png' => 'imagecreatefrompng', 'webp' => 'imagecreatefromwebp'];
    $loader = $loaders[$ext] ?? null;
    if ($loader === null || !function_exists($loader)) {
        return null;
    }
    $src = @$loader($sourcePath);
    if (!$src) {
        return null;
    }
    $srcW = imagesx($src);
    $srcH = imagesy($src);
    if ($srcW < 1 || $srcH < 1) {
        imagedestroy($src);
        return null;
    }
    $max = (int) cb_album_limits()['thumb_max_side'];
    $scale = min(1.0, $max / max($srcW, $srcH));
    $dstW = max(1, (int) round($srcW * $scale));
    $dstH = max(1, (int) round($srcH * $scale));

    $dst = imagecreatetruecolor($dstW, $dstH);
    // La miniatura se guarda en JPEG, que no tiene alfa: se rellena de blanco
    // para que un PNG transparente no salga con el fondo en negro.
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagedestroy($src);

    $key = cb_album_storage_key($partySlug, 'jpg');
    $path = cb_album_media_path($key);
    if ($path === null) {
        imagedestroy($dst);
        return null;
    }
    if (!cb_album_prepare_dir($path)) {
        imagedestroy($dst);
        return null;
    }
    $ok = @imagejpeg($dst, $path, 80);
    imagedestroy($dst);
    if (!$ok) {
        @unlink($path);
        return null;
    }
    @chmod($path, 0660);
    return $key;
}

/** Crea el directorio de un archivo de álbum si falta. */
function cb_album_prepare_dir(string $absolutePath): bool
{
    $dir = dirname($absolutePath);
    if (is_dir($dir)) {
        return true;
    }
    return mkdir($dir, 0770, true) || is_dir($dir);
}

/**
 * Mueve un archivo subido al storage privado del álbum de forma atómica.
 * Devuelve la ruta absoluta o null si algo falló (y en ese caso no deja
 * archivos a medias).
 */
function cb_album_store_file(string $tmpPath, string $storageKey, bool $isUploadedFile): ?string
{
    $path = cb_album_media_path($storageKey);
    if ($path === null || !cb_album_prepare_dir($path)) {
        return null;
    }
    // Se escribe con nombre temporal y se renombra: si el proceso muere a mitad
    // no queda un archivo incompleto ocupando la storage key definitiva.
    $staging = $path . '.tmp.' . bin2hex(random_bytes(4));
    $moved = $isUploadedFile ? @move_uploaded_file($tmpPath, $staging) : @rename($tmpPath, $staging);
    if (!$moved || !@rename($staging, $path)) {
        @unlink($staging);
        return null;
    }
    @chmod($path, 0660);
    return $path;
}

/**
 * Guarda los archivos que sube el organizador desde el admin.
 *
 * Aplica exactamente la misma validación por bytes que el aporte de invitado
 * —el admin no es una puerta de atrás para meter cualquier cosa al storage—,
 * pero el material nace aprobado: lo está subiendo el dueño del evento, no un
 * tercero desconocido.
 *
 * Devuelve ['saved'=>int, 'errors'=>string[]] con mensajes ya legibles.
 */
function cb_album_store_admin_uploads(int $albumId, int $partyId, string $partySlug, $files): array
{
    $saved = 0;
    $errors = [];
    if (!is_array($files) || !isset($files['name'])) {
        return ['saved' => 0, 'errors' => []];
    }

    $names = (array) $files['name'];
    $total = count($names);
    $messages = [
        'empty' => 'llegó vacío',
        'format' => 'no es una foto ni un video en un formato aceptado',
        'image_too_big' => 'la foto pesa demasiado',
        'image_dimensions' => 'la foto es demasiado grande',
        'video_too_big' => 'el video pesa demasiado',
        'video_too_long' => 'el video dura más de lo permitido',
        'video_dimensions' => 'el video tiene una resolución demasiado alta',
    ];

    for ($i = 0; $i < $total; $i++) {
        $original = (string) ($names[$i] ?? '');
        $label = $original !== '' ? '"' . basename($original) . '"' : 'un archivo';
        $error = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                ? "$label supera el tamaño máximo que acepta el servidor."
                : "$label no se pudo subir.";
            continue;
        }

        $tmpPath = (string) ($files['tmp_name'][$i] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            $errors[] = "$label no se pudo leer.";
            continue;
        }
        $byteSize = (int) ($files['size'][$i] ?? 0);

        // El organizador siempre puede subir videos, aunque la recepción de
        // invitados los tenga apagados: ese interruptor es para el QR.
        $validation = cb_album_validate_upload($tmpPath, $byteSize, true);
        if (!$validation['ok']) {
            $errors[] = "$label " . ($messages[$validation['error']] ?? 'no se pudo aceptar') . '.';
            @unlink($tmpPath);
            continue;
        }
        $media = $validation['media'];

        $sha256 = hash_file('sha256', $tmpPath);
        if ($sha256 === false) {
            $errors[] = "$label no se pudo leer.";
            @unlink($tmpPath);
            continue;
        }
        if (cb_album_media_exists($albumId, $sha256)) {
            $errors[] = "$label ya estaba en el álbum.";
            @unlink($tmpPath);
            continue;
        }

        try {
            $storageKey = cb_album_storage_key($partySlug, $media['ext']);
        } catch (Throwable $e) {
            $errors[] = "$label no se pudo guardar.";
            @unlink($tmpPath);
            continue;
        }
        $storedPath = cb_album_store_file($tmpPath, $storageKey, true);
        if ($storedPath === null) {
            $errors[] = "$label no se pudo guardar.";
            @unlink($tmpPath);
            continue;
        }

        $thumbKey = $media['kind'] === 'image'
            ? cb_album_make_thumbnail($storedPath, $partySlug, $media['ext'])
            : null;

        $result = cb_album_record_media($albumId, $partyId, [
            'source' => 'organizer',
            'media_kind' => $media['kind'],
            'access_token' => cb_opaque_token(16),
            'storage_key' => $storageKey,
            'thumb_storage_key' => $thumbKey,
            'original_name' => mb_substr(basename($original), 0, 200),
            'mime' => $media['mime'],
            'byte_size' => $byteSize,
            'width' => (int) $media['width'],
            'height' => (int) $media['height'],
            'duration_seconds' => $media['kind'] === 'video' ? round((float) ($media['duration'] ?? 0), 2) : null,
            'sha256' => $sha256,
            'moderation_status' => 'approved',
        ]);

        if ($result !== 'ok') {
            @unlink($storedPath);
            if ($thumbKey !== null) { @unlink((string) cb_album_media_path($thumbKey)); }
            $errors[] = $result === 'quota'
                ? "$label no entra: el álbum llegó a su límite."
                : "$label no se pudo registrar.";
            continue;
        }
        $saved++;
    }

    return ['saved' => $saved, 'errors' => $errors];
}

/** Resuelve material del álbum por su token opaco. Null ante cualquier duda. */
function cb_album_find_media_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    $stmt = cb_album_require_db()->prepare(
        'SELECT m.*, a.status AS album_status, p.public_slug
         FROM cc_event_media m
         JOIN cc_event_albums a ON a.id = m.album_id
         JOIN cc_parties p ON p.id = m.party_id
         WHERE m.access_token=?'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Normaliza el nombre y el mensaje que escribe el invitado. Ambos son
 * opcionales; lo que llegue se recorta y se limpia de caracteres de control.
 */
function cb_album_clean_contributor_text(?string $raw, int $maxLength): ?string
{
    if ($raw === null) {
        return null;
    }
    $value = trim($raw);
    if ($value === '') {
        return null;
    }
    // Quita controles (incluidos saltos de línea en el nombre) sin tocar
    // acentos ni emojis, que sí son legítimos en un mensaje de cumpleaños.
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
    $value = trim(preg_replace('/\s+/u', ' ', (string) $value));
    if ($value === '') {
        return null;
    }
    return mb_substr($value, 0, $maxLength);
}

// ─────────────────────────────────────────────────────────────────────────────
// Álbum
// ─────────────────────────────────────────────────────────────────────────────

function cb_album_require_db(): PDO
{
    if (cb_storage_mode() !== 'db') {
        throw new RuntimeException('El Álbum Recuerdo requiere storage_mode=db.');
    }
    return cb_pdo();
}

/** Devuelve el álbum de una fiesta, o null si todavía no se creó. */
function cb_album_find_by_party(int $partyId): ?array
{
    $stmt = cb_album_require_db()->prepare('SELECT * FROM cc_event_albums WHERE party_id=?');
    $stmt->execute([$partyId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Crea el álbum de una fiesta si falta y lo devuelve. Idempotente: si dos
 * pestañas del admin entran a la vez, la segunda recupera el que creó la
 * primera en vez de fallar por la restricción única.
 */
function cb_album_ensure(int $partyId): array
{
    $existing = cb_album_find_by_party($partyId);
    if ($existing !== null) {
        return $existing;
    }
    $pdo = cb_album_require_db();
    $now = gmdate('Y-m-d H:i:s');
    $limits = cb_album_limits();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO cc_event_albums (party_id,status,template_key,require_pin,retention_days,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([$partyId, 'draft', 'kids-theme', 1, (int) $limits['retention_days'], $now, $now]);
    } catch (PDOException $e) {
        $again = cb_album_find_by_party($partyId);
        if ($again === null) {
            throw $e;
        }
        return $again;
    }
    $album = cb_album_find_by_party($partyId);
    if ($album === null) {
        throw new RuntimeException('No se pudo crear el álbum del evento.');
    }
    return $album;
}

/** Actualiza solo columnas conocidas; cualquier otra clave se ignora. */
function cb_album_update(int $albumId, array $fields): void
{
    $allowed = [
        'status', 'template_key', 'title', 'subtitle', 'cover_media_id',
        'intake_enabled', 'intake_videos', 'intake_closes_at', 'intake_message',
        'require_pin', 'retention_days', 'published_at',
    ];
    $sets = [];
    $values = [];
    foreach ($fields as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            continue;
        }
        $sets[] = "$key=?";
        $values[] = $value;
    }
    if (!$sets) {
        return;
    }
    $sets[] = 'updated_at=?';
    $values[] = gmdate('Y-m-d H:i:s');
    $values[] = $albumId;
    $stmt = cb_album_require_db()->prepare('UPDATE cc_event_albums SET ' . implode(',', $sets) . ' WHERE id=?');
    $stmt->execute($values);
}

/**
 * ¿Está recibiendo material ahora mismo? Cadena completa de condiciones, para
 * que ningún endpoint tenga que recordar la lista por su cuenta.
 */
function cb_album_intake_open(array $album, array $party): bool
{
    if (empty($party['activa'])) {
        return false;
    }
    if ((string) ($album['status'] ?? '') !== 'collecting') {
        return false;
    }
    if (empty($album['intake_enabled'])) {
        return false;
    }
    $closesAt = (string) ($album['intake_closes_at'] ?? '');
    if ($closesAt !== '' && strtotime($closesAt) < time()) {
        return false;
    }
    return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// Tokens
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Emite un token nuevo y revoca los anteriores del mismo propósito.
 * Devuelve el token EN CLARO una sola vez: en base solo queda su SHA-256, así
 * que si el admin cierra la página sin copiarlo hay que regenerarlo.
 */
function cb_album_issue_token(int $albumId, string $purpose, ?string $expiresAt, ?string $createdBy = null): string
{
    if (!in_array($purpose, ['intake', 'view'], true)) {
        throw new InvalidArgumentException('Propósito de token desconocido.');
    }
    $pdo = cb_album_require_db();
    $now = gmdate('Y-m-d H:i:s');
    $token = cb_opaque_token(16);
    $pdo->beginTransaction();
    try {
        $revoke = $pdo->prepare(
            "UPDATE cc_event_album_tokens SET status='revoked', revoked_at=?
             WHERE album_id=? AND purpose=? AND status='active'"
        );
        $revoke->execute([$now, $albumId, $purpose]);
        $insert = $pdo->prepare(
            'INSERT INTO cc_event_album_tokens (album_id,token_hash,purpose,status,expires_at,created_at,created_by)
             VALUES (?,?,?,?,?,?,?)'
        );
        $insert->execute([$albumId, cb_hash_token($token), $purpose, 'active', $expiresAt, $now, $createdBy]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return $token;
}

/** Revoca todos los tokens activos de un propósito. No borra el histórico. */
function cb_album_revoke_tokens(int $albumId, string $purpose): void
{
    $stmt = cb_album_require_db()->prepare(
        "UPDATE cc_event_album_tokens SET status='revoked', revoked_at=?
         WHERE album_id=? AND purpose=? AND status='active'"
    );
    $stmt->execute([gmdate('Y-m-d H:i:s'), $albumId, $purpose]);
}

/**
 * Resuelve un token en claro al álbum y la fiesta correspondientes.
 * Devuelve null ante cualquier duda: formato inválido, no encontrado, revocado
 * o vencido. Nunca distingue entre esos casos hacia afuera, para no confirmarle
 * a nadie que un token existió.
 */
function cb_album_resolve_token(string $token, string $purpose): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    $stmt = cb_album_require_db()->prepare(
        'SELECT t.album_id, t.status, t.expires_at, p.public_slug
         FROM cc_event_album_tokens t
         JOIN cc_event_albums a ON a.id = t.album_id
         JOIN cc_parties p ON p.id = a.party_id
         WHERE t.token_hash=? AND t.purpose=?'
    );
    $stmt->execute([cb_hash_token($token), $purpose]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if ((string) $row['status'] !== 'active') {
        return null;
    }
    $expiresAt = (string) ($row['expires_at'] ?? '');
    if ($expiresAt !== '' && strtotime($expiresAt) < time()) {
        return null;
    }
    $album = cb_album_find_by_id((int) $row['album_id']);
    if ($album === null) {
        return null;
    }
    return ['album' => $album, 'party_slug' => (string) $row['public_slug']];
}

function cb_album_find_by_id(int $albumId): ?array
{
    $stmt = cb_album_require_db()->prepare('SELECT * FROM cc_event_albums WHERE id=?');
    $stmt->execute([$albumId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Metadata del token activo (sin el token en claro, que no se guarda). */
function cb_album_active_token_info(int $albumId, string $purpose): ?array
{
    $stmt = cb_album_require_db()->prepare(
        "SELECT created_at, expires_at FROM cc_event_album_tokens
         WHERE album_id=? AND purpose=? AND status='active'
         ORDER BY id DESC"
    );
    $stmt->execute([$albumId, $purpose]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Material
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Uso real del álbum. Cuenta todo lo que ocupa disco de verdad, incluido lo
 * oculto: ocultar no libera espacio. Lo eliminado sí se descuenta porque la
 * retención lo purgará.
 */
function cb_album_usage(int $albumId): array
{
    $stmt = cb_album_require_db()->prepare(
        "SELECT COUNT(*) AS total, COALESCE(SUM(byte_size),0) AS bytes
         FROM cc_event_media
         WHERE album_id=? AND moderation_status <> 'removed' AND source <> 'booth'"
    );
    $stmt->execute([$albumId]);
    $row = $stmt->fetch() ?: [];
    return ['count' => (int) ($row['total'] ?? 0), 'bytes' => (int) ($row['bytes'] ?? 0)];
}

/** ¿Este archivo ya está en el álbum? Evita duplicados por reenvío. */
function cb_album_media_exists(int $albumId, string $sha256): bool
{
    $stmt = cb_album_require_db()->prepare(
        "SELECT 1 FROM cc_event_media WHERE album_id=? AND sha256=? AND moderation_status <> 'removed'"
    );
    $stmt->execute([$albumId, $sha256]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Registra material reservando cuota en la misma transacción, para que dos
 * invitados subiendo a la vez no puedan pasarse del límite entre la
 * verificación y el INSERT.
 *
 * Devuelve 'ok' | 'quota' | 'duplicate'.
 */
function cb_album_record_media(int $albumId, int $partyId, array $media): string
{
    $pdo = cb_album_require_db();
    $limits = cb_album_limits();
    $pdo->beginTransaction();
    try {
        $forUpdate = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $lock = $pdo->prepare('SELECT id FROM cc_event_albums WHERE id=?' . $forUpdate);
        $lock->execute([$albumId]);
        if (!$lock->fetchColumn()) {
            $pdo->rollBack();
            return 'quota';
        }

        if (!empty($media['sha256'])) {
            $dup = $pdo->prepare(
                "SELECT 1 FROM cc_event_media WHERE album_id=? AND sha256=? AND moderation_status <> 'removed'"
            );
            $dup->execute([$albumId, $media['sha256']]);
            if ($dup->fetchColumn()) {
                $pdo->rollBack();
                return 'duplicate';
            }
        }

        $usage = $pdo->prepare(
            "SELECT COUNT(*) AS total, COALESCE(SUM(byte_size),0) AS bytes
             FROM cc_event_media
             WHERE album_id=? AND moderation_status <> 'removed' AND source <> 'booth'"
        );
        $usage->execute([$albumId]);
        $row = $usage->fetch() ?: [];
        $count = (int) ($row['total'] ?? 0);
        $bytes = (int) ($row['bytes'] ?? 0);
        if ($count >= $limits['album_max_files'] || $bytes + (int) $media['byte_size'] > $limits['album_max_bytes']) {
            $pdo->rollBack();
            return 'quota';
        }

        $next = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM cc_event_media WHERE album_id=?');
        $next->execute([$albumId]);
        $sortOrder = (int) $next->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO cc_event_media
             (album_id,party_id,source,media_kind,photo_id,access_token,storage_key,thumb_storage_key,poster_storage_key,
              original_name,mime,byte_size,width,height,duration_seconds,sha256,
              contributor_name,contributor_message,moderation_status,sort_order,consent_version,uploader_hmac,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $albumId,
            $partyId,
            $media['source'],
            $media['media_kind'],
            $media['photo_id'] ?? null,
            $media['access_token'] ?? null,
            $media['storage_key'] ?? null,
            $media['thumb_storage_key'] ?? null,
            $media['poster_storage_key'] ?? null,
            $media['original_name'] ?? null,
            $media['mime'] ?? null,
            (int) $media['byte_size'],
            (int) ($media['width'] ?? 0),
            (int) ($media['height'] ?? 0),
            $media['duration_seconds'] ?? null,
            $media['sha256'] ?? null,
            $media['contributor_name'] ?? null,
            $media['contributor_message'] ?? null,
            $media['moderation_status'] ?? 'pending',
            $sortOrder,
            $media['consent_version'] ?? null,
            $media['uploader_hmac'] ?? null,
            gmdate('Y-m-d H:i:s'),
        ]);
        $pdo->commit();
        return 'ok';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Incorpora al álbum las fotos de cabina que todavía no estén referenciadas.
 * No copia archivos ni bytes: solo enlaza cc_photos por id. Idempotente.
 * Devuelve cuántas se agregaron.
 */
function cb_album_sync_booth_photos(int $albumId, int $partyId): int
{
    $pdo = cb_album_require_db();
    $stmt = $pdo->prepare(
        'SELECT ph.id, ph.byte_size, ph.width, ph.height, ph.sha256, ph.original_name, ph.created_at
         FROM cc_photos ph
         WHERE ph.party_id=? AND ph.deleted_at IS NULL
           AND ph.id NOT IN (SELECT photo_id FROM cc_event_media WHERE album_id=? AND photo_id IS NOT NULL)
         ORDER BY ph.created_at'
    );
    $stmt->execute([$partyId, $albumId]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return 0;
    }

    $next = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM cc_event_media WHERE album_id=?');
    $next->execute([$albumId]);
    $sortOrder = (int) $next->fetchColumn();

    // La foto de cabina nace aprobada: ya la vio la familia en el kiosco y no
    // es material de un tercero desconocido. El organizador igual puede
    // ocultarla después.
    $insert = $pdo->prepare(
        'INSERT INTO cc_event_media
         (album_id,party_id,source,media_kind,photo_id,original_name,mime,byte_size,width,height,sha256,
          moderation_status,sort_order,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $added = 0;
    foreach ($rows as $photo) {
        $sortOrder++;
        $insert->execute([
            $albumId,
            $partyId,
            'booth',
            'image',
            (int) $photo['id'],
            (string) ($photo['original_name'] ?? ''),
            'image/png',
            (int) $photo['byte_size'],
            (int) $photo['width'],
            (int) $photo['height'],
            (string) ($photo['sha256'] ?? ''),
            'approved',
            $sortOrder,
            (string) ($photo['created_at'] ?? gmdate('Y-m-d H:i:s')),
        ]);
        $added++;
    }
    return $added;
}

/**
 * Lista el material del álbum. `$states` acota por estado de moderación;
 * por defecto trae todo menos lo eliminado.
 */
function cb_album_list_media(int $albumId, ?array $states = null, ?string $source = null, ?string $kind = null): array
{
    $states = $states ?? ['pending', 'approved', 'hidden'];
    $states = array_values(array_intersect($states, cb_album_moderation_states()));
    if (!$states) {
        return [];
    }
    $sql = 'SELECT m.*, ph.access_token AS photo_token
            FROM cc_event_media m
            LEFT JOIN cc_photos ph ON ph.id = m.photo_id
            WHERE m.album_id=? AND m.moderation_status IN (' . implode(',', array_fill(0, count($states), '?')) . ')';
    $params = array_merge([$albumId], $states);
    if ($source !== null && in_array($source, cb_album_sources(), true)) {
        $sql .= ' AND m.source=?';
        $params[] = $source;
    }
    if ($kind !== null && in_array($kind, ['image', 'video'], true)) {
        $sql .= ' AND m.media_kind=?';
        $params[] = $kind;
    }
    $sql .= ' ORDER BY m.sort_order, m.id';
    $stmt = cb_album_require_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function cb_album_find_media(int $albumId, int $mediaId): ?array
{
    $stmt = cb_album_require_db()->prepare(
        'SELECT m.*, ph.access_token AS photo_token
         FROM cc_event_media m
         LEFT JOIN cc_photos ph ON ph.id = m.photo_id
         WHERE m.album_id=? AND m.id=?'
    );
    $stmt->execute([$albumId, $mediaId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Cambia el estado de moderación. Eliminar es reversible a propósito: marca
 * `removed` y guarda la fecha, pero no toca el archivo en disco. La purga real
 * la hace la retención junto con el resto de la fiesta.
 */
function cb_album_set_moderation(int $albumId, int $mediaId, string $state, ?string $reviewedBy = null): bool
{
    if (!in_array($state, cb_album_moderation_states(), true)) {
        return false;
    }
    $now = gmdate('Y-m-d H:i:s');
    $stmt = cb_album_require_db()->prepare(
        'UPDATE cc_event_media
         SET moderation_status=?, reviewed_at=?, reviewed_by=?, removed_at=?
         WHERE album_id=? AND id=?'
    );
    $stmt->execute([$state, $now, $reviewedBy, $state === 'removed' ? $now : null, $albumId, $mediaId]);
    return $stmt->rowCount() > 0;
}

/** Reordena aplicando la lista recibida; los ids ajenos al álbum se ignoran. */
function cb_album_reorder(int $albumId, array $mediaIds): void
{
    $pdo = cb_album_require_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE cc_event_media SET sort_order=? WHERE album_id=? AND id=?');
        $order = 0;
        foreach ($mediaIds as $mediaId) {
            $mediaId = (int) $mediaId;
            if ($mediaId <= 0) {
                continue;
            }
            $order++;
            $stmt->execute([$order, $albumId, $mediaId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Resumen para el admin: cuántos hay por estado y origen, y cuánto pesan. */
function cb_album_stats(int $albumId): array
{
    $stmt = cb_album_require_db()->prepare(
        'SELECT source, media_kind, moderation_status, COUNT(*) AS total, COALESCE(SUM(byte_size),0) AS bytes
         FROM cc_event_media WHERE album_id=?
         GROUP BY source, media_kind, moderation_status'
    );
    $stmt->execute([$albumId]);
    $stats = [
        'total' => 0, 'bytes' => 0,
        'by_source' => ['booth' => 0, 'guest' => 0, 'organizer' => 0],
        'by_state' => ['pending' => 0, 'approved' => 0, 'hidden' => 0, 'removed' => 0],
        'by_kind' => ['image' => 0, 'video' => 0],
    ];
    foreach ($stmt->fetchAll() as $row) {
        $total = (int) $row['total'];
        $state = (string) $row['moderation_status'];
        $stats['by_state'][$state] = ($stats['by_state'][$state] ?? 0) + $total;
        if ($state === 'removed') {
            continue; // lo eliminado no cuenta para los totales visibles
        }
        $stats['total'] += $total;
        $stats['bytes'] += (int) $row['bytes'];
        $source = (string) $row['source'];
        $kind = (string) $row['media_kind'];
        $stats['by_source'][$source] = ($stats['by_source'][$source] ?? 0) + $total;
        $stats['by_kind'][$kind] = ($stats['by_kind'][$kind] ?? 0) + $total;
    }
    return $stats;
}

// ─────────────────────────────────────────────────────────────────────────────
// URLs públicas
// ─────────────────────────────────────────────────────────────────────────────

function cb_album_intake_url(string $token): string
{
    return cb_public_base_url() . '/subir.php?t=' . rawurlencode($token);
}

function cb_album_view_url(string $token): string
{
    return cb_public_base_url() . '/album.html?t=' . rawurlencode($token);
}

function cb_album_sign_url(string $token): string
{
    return cb_public_base_url() . '/cartel-qr.html?t=' . rawurlencode($token);
}
