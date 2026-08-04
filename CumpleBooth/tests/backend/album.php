<?php
/**
 * Pruebas aisladas del Álbum Recuerdo. Como leads.php, monta su propia base
 * SQLite y no depende de assets temáticos, así que corre sin el catálogo
 * completo de imágenes.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$tmp = sys_get_temp_dir() . '/cumpleclick-album-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});
putenv('CC_STORAGE_MODE=db');
putenv('CC_PDO_DSN=sqlite:' . $tmp . '/album.sqlite');
putenv('CC_APP_HMAC_KEY=' . str_repeat('c', 64));
putenv('CC_PUBLIC_BASE_URL=https://example.test/cumpleclick');
putenv('CC_PHOTO_DIR=' . $tmp . '/photos');
putenv('CC_STATE_DIR=' . $tmp . '/state');
putenv('CC_INVITATION_DIR=' . $tmp . '/invitations');
require dirname(__DIR__, 2) . '/public/lib.php';

$tests = 0;
function album_check(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
}

$root = dirname(__DIR__, 2);
foreach ([
    '001_initial', '002_theme_prompts', '003_invitations_and_plan',
    '004_gate_a_corrections', '005_theme_prompt_history', '006_public_leads',
    '007_event_album',
] as $version) {
    $migration = require $root . '/database/migrations/' . $version . '.php';
    $migration(cb_pdo());
}

// ── Migración reversible ────────────────────────────────────────────────────
$tables = static function (): array {
    return cb_pdo()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
};
album_check(in_array('cc_event_albums', $tables(), true), 'la migración crea cc_event_albums');
album_check(in_array('cc_event_album_tokens', $tables(), true), 'la migración crea cc_event_album_tokens');
album_check(in_array('cc_event_media', $tables(), true), 'la migración crea cc_event_media');
$down = require $root . '/database/migrations/007_event_album.down.php';
$down(cb_pdo());
$afterDown = $tables();
album_check(
    !in_array('cc_event_albums', $afterDown, true)
    && !in_array('cc_event_album_tokens', $afterDown, true)
    && !in_array('cc_event_media', $afterDown, true),
    'la reversa borra solo las tablas nuevas'
);
album_check(
    in_array('cc_parties', $afterDown, true)
    && in_array('cc_photos', $afterDown, true)
    && in_array('cc_leads', $afterDown, true),
    'la reversa no toca ninguna tabla anterior'
);
$up = require $root . '/database/migrations/007_event_album.php';
$up(cb_pdo());
album_check(in_array('cc_event_albums', $tables(), true), 'la migración se puede volver a aplicar tras revertirla');

// ── Fiesta de prueba ────────────────────────────────────────────────────────
album_check(cb_save_parties(['parties' => ['demo' => [
    'nombre' => 'Demo', 'tema' => 'carreras', 'fecha' => '2026-08-15', 'activa' => true,
    'invitados' => [['name' => 'Ana', 'g' => 'f']],
    'creada' => gmdate('Y-m-d H:i:s'),
]]]), 'fiesta de prueba creada');
$partyId = cb_party_db_id('demo');
album_check(is_int($partyId) && $partyId > 0, 'la fiesta demo tiene id');

// ── Ciclo de vida del álbum ─────────────────────────────────────────────────
$album = cb_album_ensure($partyId);
album_check((int) $album['party_id'] === $partyId && $album['status'] === 'draft', 'ensure crea el álbum en borrador');
album_check((int) cb_album_ensure($partyId)['id'] === (int) $album['id'], 'ensure es idempotente');
album_check((int) $album['retention_days'] === 90, 'retención propia de 90 días por defecto');
album_check((int) $album['require_pin'] === 1, 'el PIN viene exigido por defecto');
album_check($album['template_key'] === 'kids-theme', 'la plantilla inicial es kids-theme');
$albumId = (int) $album['id'];

cb_album_update($albumId, ['title' => 'Los recuerdos', 'status' => 'collecting', 'intake_enabled' => 1]);
$album = cb_album_find_by_id($albumId);
album_check($album['title'] === 'Los recuerdos' && $album['status'] === 'collecting', 'update guarda columnas conocidas');
cb_album_update($albumId, ['party_id' => 99999, 'id' => 42]);
album_check((int) cb_album_find_by_id($albumId)['party_id'] === $partyId, 'update ignora columnas no permitidas');

// ── Tokens ──────────────────────────────────────────────────────────────────
$intakeToken = cb_album_issue_token($albumId, 'intake', null, 'test');
album_check(preg_match('/^[a-f0-9]{32}$/', $intakeToken) === 1, 'el token de aporte es opaco de 128 bits');
$stored = cb_pdo()->query('SELECT token_hash FROM cc_event_album_tokens')->fetchAll(PDO::FETCH_COLUMN);
album_check(!in_array($intakeToken, $stored, true), 'el token en claro nunca se guarda en base');
album_check(in_array(cb_hash_token($intakeToken), $stored, true), 'en base queda solo el hash del token');

$resolved = cb_album_resolve_token($intakeToken, 'intake');
album_check($resolved !== null && (int) $resolved['album']['id'] === $albumId, 'el token resuelve a su álbum');
album_check($resolved['party_slug'] === 'demo', 'el token resuelve a su fiesta');
album_check(cb_album_resolve_token($intakeToken, 'view') === null, 'un token de aporte no sirve para leer la revista');
album_check(cb_album_resolve_token(str_repeat('f', 32), 'intake') === null, 'un token inexistente se rechaza');
album_check(cb_album_resolve_token('no-es-hexadecimal', 'intake') === null, 'un token mal formado se rechaza sin consultar');

$second = cb_album_issue_token($albumId, 'intake', null, 'test');
album_check(cb_album_resolve_token($intakeToken, 'intake') === null, 'regenerar revoca el token anterior');
album_check(cb_album_resolve_token($second, 'intake') !== null, 'el token nuevo queda activo');

$expired = cb_album_issue_token($albumId, 'intake', gmdate('Y-m-d H:i:s', time() - 60), 'test');
album_check(cb_album_resolve_token($expired, 'intake') === null, 'un token vencido se rechaza');
$live = cb_album_issue_token($albumId, 'intake', gmdate('Y-m-d H:i:s', time() + 3600), 'test');
album_check(cb_album_resolve_token($live, 'intake') !== null, 'un token vigente se acepta');
album_check(cb_album_active_token_info($albumId, 'intake') !== null, 'el admin puede ver la vigencia sin el token en claro');
cb_album_revoke_tokens($albumId, 'intake');
album_check(cb_album_resolve_token($live, 'intake') === null, 'revocar cierra el acceso de inmediato');
album_check(
    (int) cb_pdo()->query("SELECT COUNT(*) FROM cc_event_album_tokens WHERE album_id=$albumId")->fetchColumn() === 4,
    'revocar conserva el histórico de tokens emitidos'
);

// El token de lectura es independiente del de aporte.
$viewToken = cb_album_issue_token($albumId, 'view', null, 'test');
album_check(cb_album_resolve_token($viewToken, 'view') !== null, 'el token de lectura funciona por su cuenta');
album_check(cb_album_resolve_token($viewToken, 'intake') === null, 'el token de lectura no permite subir material');

// ── Puerta de recepción ─────────────────────────────────────────────────────
$party = cb_load_parties()['parties']['demo'];
$open = cb_album_find_by_id($albumId);
album_check(cb_album_intake_open($open, $party), 'recibe con fiesta activa y álbum recolectando');
album_check(!cb_album_intake_open(array_merge($open, ['intake_enabled' => 0]), $party), 'el interruptor apagado cierra la recepción');
album_check(!cb_album_intake_open(array_merge($open, ['status' => 'closed']), $party), 'un álbum cerrado no recibe');
album_check(!cb_album_intake_open(array_merge($open, ['status' => 'published']), $party), 'un álbum publicado no sigue recibiendo');
album_check(
    !cb_album_intake_open(array_merge($open, ['intake_closes_at' => gmdate('Y-m-d H:i:s', time() - 60)]), $party),
    'la fecha de cierre pasada corta la recepción'
);
album_check(
    cb_album_intake_open(array_merge($open, ['intake_closes_at' => gmdate('Y-m-d H:i:s', time() + 60)]), $party),
    'una fecha de cierre futura mantiene la recepción abierta'
);
album_check(!cb_album_intake_open($open, array_merge($party, ['activa' => false])), 'una fiesta inactiva no recibe');

// ── Storage: rutas opacas y a prueba de traversal ───────────────────────────
$guestKey = cb_album_storage_key('demo', 'jpg');
album_check(preg_match('#^album/demo/\d{4}/\d{2}/[a-f0-9]{32}\.jpg$#', $guestKey) === 1, 'la storage key es opaca y acotada');
album_check(cb_album_media_path($guestKey) !== null, 'una storage key válida resuelve a una ruta');
album_check(cb_album_media_path('album/demo/2026/08/../../../evil.php') === null, 'path traversal en la key se rechaza');
album_check(cb_album_media_path('demo/2026/08/' . str_repeat('a', 32) . '.jpg') === null, 'una key fuera de album/ se rechaza');
album_check(cb_album_media_path('album/demo/2026/08/' . str_repeat('a', 32) . '.php') === null, 'una extensión ejecutable se rechaza');
$rejectedExt = false;
try { cb_album_storage_key('demo', 'php'); } catch (InvalidArgumentException $e) { $rejectedExt = true; }
album_check($rejectedExt, 'no se puede pedir una storage key con extensión ejecutable');
$rejectedSlug = false;
try { cb_album_storage_key('../evil', 'jpg'); } catch (InvalidArgumentException $e) { $rejectedSlug = true; }
album_check($rejectedSlug, 'un slug inválido no genera storage key');

// ── Fotos de cabina: se enlazan, no se copian ───────────────────────────────
$boothToken = bin2hex(random_bytes(16));
album_check(cb_record_photo('demo', [
    'token' => $boothToken, 'storage_key' => 'demo/2026/08/' . $boothToken . '.png',
    'original_name' => 'foto.png', 'byte_size' => 2048, 'width' => 1080, 'height' => 1920,
    'sha256' => hash('sha256', 'foto-cabina'), 'created_at' => gmdate('Y-m-d H:i:s'),
]), 'se registra una foto de cabina');
album_check(cb_album_sync_booth_photos($albumId, $partyId) === 1, 'sync incorpora la foto de cabina');
album_check(cb_album_sync_booth_photos($albumId, $partyId) === 0, 'sync es idempotente');
$booth = cb_album_list_media($albumId, null, 'booth');
album_check(count($booth) === 1, 'la foto de cabina aparece una sola vez');
album_check($booth[0]['storage_key'] === null && (int) $booth[0]['photo_id'] > 0, 'se enlaza por photo_id, sin duplicar el archivo');
album_check($booth[0]['moderation_status'] === 'approved', 'la foto de cabina nace aprobada');
album_check($booth[0]['photo_token'] === $boothToken, 'el listado trae el token para servirla por ver.php');
album_check(cb_album_usage($albumId)['bytes'] === 0, 'la foto de cabina no consume cuota del álbum');
$boothId = (int) $booth[0]['id'];

// ── Aportes de invitado ─────────────────────────────────────────────────────
$guest = [
    'source' => 'guest', 'media_kind' => 'image', 'storage_key' => $guestKey,
    'original_name' => 'imagen.jpg', 'mime' => 'image/jpeg', 'byte_size' => 500000,
    'width' => 1600, 'height' => 1200, 'sha256' => hash('sha256', 'foto-invitado'),
    'contributor_name' => 'Tía Rosa', 'contributor_message' => '¡Qué linda fiesta!',
    'consent_version' => cb_album_consent_version(),
    'uploader_hmac' => cb_hmac('203.0.113.9', 'album-intake'),
];
album_check(cb_album_record_media($albumId, $partyId, $guest) === 'ok', 'el aporte de invitado se registra');
album_check(
    cb_album_record_media($albumId, $partyId, array_merge($guest, ['storage_key' => cb_album_storage_key('demo', 'jpg')])) === 'duplicate',
    'el mismo archivo no entra dos veces aunque cambie la ruta'
);
$usage = cb_album_usage($albumId);
album_check($usage['count'] === 1 && $usage['bytes'] === 500000, 'la cuota cuenta solo lo que ocupa disco');

$guestRow = cb_album_list_media($albumId, null, 'guest')[0];
album_check($guestRow['moderation_status'] === 'pending', 'el aporte de invitado nace pendiente, no visible');
album_check($guestRow['consent_version'] === cb_album_consent_version(), 'se guarda la versión del consentimiento');
album_check(strpos(json_encode($guestRow), '203.0.113.9') === false, 'la IP del invitado nunca queda en claro');
$guestId = (int) $guestRow['id'];

// ── Moderación ──────────────────────────────────────────────────────────────
$approved = cb_album_list_media($albumId, ['approved']);
album_check(count($approved) === 1 && $approved[0]['source'] === 'booth', 'lo pendiente no aparece entre lo aprobado');
album_check(cb_album_set_moderation($albumId, $guestId, 'approved', 'test'), 'aprobar funciona');
album_check(count(cb_album_list_media($albumId, ['approved'])) === 2, 'lo aprobado ya aparece');
album_check(!cb_album_set_moderation($albumId, $guestId, 'inventado'), 'un estado desconocido se rechaza');
album_check(cb_album_set_moderation($albumId, $guestId, 'hidden', 'test'), 'ocultar funciona');
album_check(count(cb_album_list_media($albumId, ['approved'])) === 1, 'lo oculto sale de la revista');
album_check(cb_album_usage($albumId)['count'] === 1, 'ocultar no libera cuota: el archivo sigue en disco');

// Eliminar es reversible: cambia el estado pero no suelta el archivo.
album_check(cb_album_set_moderation($albumId, $guestId, 'removed', 'test'), 'eliminar marca el estado');
$removed = cb_album_find_media($albumId, $guestId);
album_check($removed['moderation_status'] === 'removed' && !empty($removed['removed_at']), 'eliminar deja fecha para auditar');
album_check($removed['storage_key'] === $guestKey, 'eliminar conserva la referencia al archivo: es restaurable');
album_check(count(cb_album_list_media($albumId)) === 1, 'lo eliminado sale del listado por defecto');
album_check(cb_album_usage($albumId)['count'] === 0, 'lo eliminado sí libera cuota, porque la retención lo purgará');
album_check(cb_album_set_moderation($albumId, $guestId, 'approved', 'test'), 'restaurar funciona');
album_check(count(cb_album_list_media($albumId, ['approved'])) === 2, 'lo restaurado vuelve a aparecer');

// Un id de otro álbum no se puede moderar desde este.
album_check(!cb_album_set_moderation($albumId + 999, $guestId, 'hidden'), 'no se puede moderar material de otro álbum');
album_check(cb_album_find_media($albumId + 999, $guestId) === null, 'no se puede leer material de otro álbum');

// ── Orden y portada ─────────────────────────────────────────────────────────
cb_album_reorder($albumId, [$guestId, $boothId]);
album_check((int) cb_album_list_media($albumId)[0]['id'] === $guestId, 'reordenar aplica el orden recibido');
cb_album_reorder($albumId, [$boothId, $guestId]);
album_check((int) cb_album_list_media($albumId)[0]['id'] === $boothId, 'reordenar se puede deshacer');
$before = cb_album_list_media($albumId);
cb_album_reorder($albumId, [999999, -1, 0]);
album_check(cb_album_list_media($albumId) === $before, 'reordenar con ids ajenos no altera nada');

cb_album_update($albumId, ['cover_media_id' => $boothId]);
album_check((int) cb_album_find_by_id($albumId)['cover_media_id'] === $boothId, 'la portada se guarda');

// ── Estadísticas ────────────────────────────────────────────────────────────
$stats = cb_album_stats($albumId);
album_check($stats['total'] === 2, 'las estadísticas cuentan lo visible');
album_check($stats['by_source']['booth'] === 1 && $stats['by_source']['guest'] === 1, 'las estadísticas separan por origen');
album_check($stats['by_kind']['image'] === 2 && $stats['by_kind']['video'] === 0, 'las estadísticas separan por tipo');

// ── Lector de MP4 sin ffprobe ───────────────────────────────────────────────
album_check(cb_album_probe_mp4(__FILE__) === null, 'un archivo que no es MP4 devuelve null');
album_check(cb_album_probe_mp4($tmp . '/no-existe.mp4') === null, 'un archivo inexistente devuelve null');
$fake = $tmp . '/fake.mp4';
file_put_contents($fake, "\x00\x00\x00\x18ftypisom" . str_repeat("\x00", 200));
album_check(cb_album_probe_mp4($fake) === null, 'un MP4 sin mvhd legible devuelve null');
// Átomo con tamaño mentiroso: no debe colgar ni leer fuera del archivo.
file_put_contents($fake, "\x7f\xff\xff\xffmoov" . str_repeat("\x00", 64));
album_check(cb_album_probe_mp4($fake) === null, 'un átomo con tamaño mayor al archivo se abandona');

// MP4 mínimo construido a mano: ftyp + moov(mvhd + trak(tkhd)).
$mvhdBody = "\x00\x00\x00\x00"                 // version 0 + flags
    . str_repeat("\x00", 8)                     // creation + modification
    . pack('N', 1000)                           // timescale
    . pack('N', 12000)                          // duration -> 12 s
    . str_repeat("\x00", 80);
$mvhd = pack('N', 8 + strlen($mvhdBody)) . 'mvhd' . $mvhdBody;
$tkhdBody = "\x00\x00\x00\x00"                 // version 0 + flags
    . str_repeat("\x00", 72)                    // hasta la matriz, inclusive
    . pack('N', 1280 << 16)                     // width  16.16
    . pack('N', 720 << 16);                     // height 16.16
$tkhd = pack('N', 8 + strlen($tkhdBody)) . 'tkhd' . $tkhdBody;
$trak = pack('N', 8 + strlen($tkhd)) . 'trak' . $tkhd;
$moovPayload = $mvhd . $trak;
$moov = pack('N', 8 + strlen($moovPayload)) . 'moov' . $moovPayload;
// 16 bytes exactos: tamaño(4) + 'ftyp'(4) + major_brand(4) + minor_version(4).
$ftyp = pack('N', 16) . 'ftyp' . 'isom' . pack('N', 512);
file_put_contents($fake, $ftyp . $moov);
$probe = cb_album_probe_mp4($fake);
album_check($probe !== null, 'un MP4 mínimo bien formado sí se lee');
album_check(abs($probe['duration'] - 12.0) < 0.01, 'la duración sale de mvhd (timescale/duration)');
album_check($probe['width'] === 1280 && $probe['height'] === 720, 'las dimensiones salen de tkhd en punto fijo 16.16');
$inspected = cb_album_inspect_video($fake);
album_check($inspected !== null && $inspected['source'] === 'mp4-atoms', 'sin ffprobe se usa el lector de átomos');
album_check(cb_album_inspect_video(__FILE__) === null, 'la inspección falla cerrada si ningún método puede leer');

// ── Tokens visuales compartidos con el kiosco ───────────────────────────────
album_check(strpos(cb_theme_css_vars('hielo'), '--dark1:#0d3a5c') !== false, 'los tokens CSS salen de themes.json');
album_check(strpos(cb_theme_css_vars('carreras'), '--pink:#e8000d') !== false, 'cada tema trae su propia paleta');
album_check(cb_theme_css_vars('tema-inexistente') === '', 'un tema desconocido no emite CSS');
album_check(strpos(cb_theme_css_vars('hielo'), 'javascript') === false, 'solo se emiten valores hexadecimales');

// ── URLs públicas ───────────────────────────────────────────────────────────
$sample = str_repeat('a', 32);
album_check(cb_album_intake_url($sample) === 'https://example.test/cumpleclick/subir.php?t=' . $sample, 'URL de carga bien formada');
album_check(cb_album_view_url($sample) === 'https://example.test/cumpleclick/album.html?t=' . $sample, 'URL de revista bien formada');
album_check(cb_album_sign_url($sample) === 'https://example.test/cumpleclick/cartel-qr.html?t=' . $sample, 'URL del cartel bien formada');

fwrite(STDOUT, "OK $tests checks album\n");
