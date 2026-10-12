<?php
/**
 * database/rollback-adultos.php (revisión B2 de Codex, CX-04), con el código actual:
 *
 *  - exportar guarda modalidad, edad, estado y temática de la fiesta adulta y el estado de su
 *    invitación, y las suspende (fiesta inactiva, invitación archivada); la infantil y el baby
 *    shower no se tocan;
 *  - con la base como la deja el código anterior (adulto reescrito como infantil, sin edad,
 *    invitación arrastrada), comprobar avisa y restaurar devuelve exactamente lo guardado,
 *    conservando lo que se editó a mano en otros campos;
 *  - restaurar es idempotente y todo o nada: un respaldo alterado o una fila que falta no
 *    escriben nada;
 *  - el respaldo no puede quedar dentro de la carpeta servida ni de ningún repositorio Git (revisión
 *    B3, CX-25): ni en CumpleBooth, ni en una hermana como Docs/, ni en la raíz Git, ni con `.git`
 *    como archivo (worktree), ni a través de un enlace; cada rechazo ocurre sin crear el archivo y
 *    sin suspender filas, y un destino privado fuera de todo repositorio se acepta. Tampoco pisa
 *    un respaldo existente.
 *
 * El ensayo con el código de 188ba45 de verdad (admin viejo por HTTP) vive fuera del repositorio;
 * esta prueba simula su efecto en la base con las mismas sentencias que ese código ejecuta.
 * Datos ficticios. La SQLite vive en una carpeta temporal.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-rollback-adultos-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
mkdir($tmp . '/invitations', 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});
$env = array_merge(getenv(), [
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $tmp . '/test.sqlite',
    'CC_APP_HMAC_KEY' => str_repeat('f', 64), 'CC_PUBLIC_BASE_URL' => 'https://example.test/cumpleclick',
    'CC_PHOTO_DIR' => $tmp . '/photos', 'CC_STATE_DIR' => $tmp . '/state', 'CC_INVITATION_DIR' => $tmp . '/invitations',
    'CUMPLECLICK_CONFIG_FILE' => $tmp . '/no-config.php', 'CC_AJUSTES_PATH' => $tmp . '/ajustes.json',
]);
foreach ($env as $k => $v) { putenv($k . '=' . $v); }
require $raiz . '/public/lib.php';
require_once __DIR__ . '/_migraciones.php';
cb_test_migrar_todo(cb_pdo());

$tests = 0;
function r_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

/** Corre el script como lo correría Luis por SSH; $script permite correr la copia de una réplica. */
function r_script(string $modo, string $archivo, ?string $script = null): array
{
    global $raiz, $env;
    $script ??= $raiz . '/database/rollback-adultos.php';
    $proc = proc_open([PHP_BINARY, $script, $modo, $archivo],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname($script, 2), $env);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $codigo = proc_close($proc);
    return ['codigo' => $codigo, 'json' => json_decode((string) $out, true), 'err' => (string) $err];
}

function r_fila(string $tabla, int $id): array
{
    $st = cb_pdo()->prepare("SELECT * FROM $tabla WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: [];
}

// ── Tres fiestas y dos invitaciones publicadas ──────────────────────────────
$ok = cb_save_parties(['parties' => [
    'adulto-prueba' => ['nombre' => 'Marta Prueba', 'tema' => 'adulto-coqueteria-pop', 'event_type' => 'adult_birthday', 'edad' => 61, 'activa' => true,
        'invitados' => [['name' => 'Invitada Uno', 'g' => 'f'], ['name' => 'Invitado Dos', 'g' => 'm']]],
    'nino-prueba' => ['nombre' => 'Niño Prueba', 'tema' => 'spidey', 'event_type' => 'child_birthday', 'edad' => 6, 'activa' => true, 'invitados' => []],
    'baby-prueba' => ['nombre' => 'Bebé Prueba', 'tema' => 'baby-nube', 'event_type' => 'baby_shower', 'activa' => true, 'invitados' => []],
]]);
r_check($ok, 'se siembran las tres fiestas');
$idAdulto = (int) cb_party_db_id('adulto-prueba');
$idNino = (int) cb_party_db_id('nino-prueba');
$idBaby = (int) cb_party_db_id('baby-prueba');

$invitacion = static function (string $slug, string $tema, string $nombre, string $tipo): int {
    $inv = cb_create_invitation(['party_id' => cb_party_db_id($slug), 'theme_slug' => $tema, 'birthday_person_name' => $nombre,
        'event_type' => $tipo, 'event_date' => '2026-12-04', 'event_time' => '19:00', 'address' => 'Calle Ficticia 123', 'message' => 'Prueba']);
    r_check(!empty($inv['ok']), "se crea la invitación de $slug");
    $key = cb_invitation_storage_key($slug, 'imagen', 1, 'png');
    $ruta = (string) cb_config('invitation_dir') . '/' . $key;
    @mkdir(dirname($ruta), 0770, true);
    file_put_contents($ruta, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6c7EAAAAASUVORK5CYII='));
    cb_save_invitation_output((int) $inv['id'], ['output_type' => 'personalized_image', 'asset_key' => 'imagen', 'file_storage_key' => $key,
        'status' => 'approved', 'file_mime' => 'image/png', 'file_byte_size' => filesize($ruta), 'file_sha256' => hash_file('sha256', $ruta)]);
    r_check(!empty(cb_publish_invitation((int) $inv['id'], 'prueba')['ok']), "se publica la invitación de $slug");
    return (int) $inv['id'];
};
$invAdulto = $invitacion('adulto-prueba', 'adulto-coqueteria-pop', 'Marta Prueba', 'adult_birthday');
$invNino = $invitacion('nino-prueba', 'spidey', 'Niño Prueba', 'child_birthday');

$antesAdulto = r_fila('cc_parties', $idAdulto);
$antesInv = r_fila('cc_invitations', $invAdulto);
$antesNino = r_fila('cc_parties', $idNino);
$antesBaby = r_fila('cc_parties', $idBaby);
$antesInvNino = r_fila('cc_invitations', $invNino);
r_check($antesAdulto['event_type'] === 'adult_birthday' && (int) $antesAdulto['birthday_age'] === 61 && (int) $antesAdulto['active'] === 1, 'el adulto parte como adulto de 61, activo');
r_check($antesInv['status'] === 'published' && $antesInv['event_type'] === 'adult_birthday', 'su invitación parte publicada como adulta');

// ── Rutas que no se aceptan (CX-25): ni la carpeta servida ni ningún repositorio Git ──
/**
 * Réplica de la disposición del repositorio dentro de la carpeta temporal: raíz con `.git`
 * (carpeta, o archivo como en un worktree), `Docs/` hermana y `CumpleBooth/` con el script copiado
 * byte a byte y un `public/lib.php` que solo carga el lib.php real, con la misma base de prueba.
 * Así se prueba la raíz Git completa sin escribir nunca dentro del repositorio de verdad.
 */
function r_replica(string $nombre, bool $gitComoArchivo): string
{
    global $raiz, $tmp;
    $base = $tmp . '/' . $nombre;
    foreach (['/CumpleBooth/database', '/CumpleBooth/public', '/CumpleBooth/docs', '/Docs'] as $sub) {
        mkdir($base . $sub, 0770, true);
    }
    if ($gitComoArchivo) {
        file_put_contents($base . '/.git', 'gitdir: ' . $tmp . "/repo-principal-ficticio/.git/worktrees/ficticio\n");
    } else {
        mkdir($base . '/.git', 0770);
    }
    copy($raiz . '/database/rollback-adultos.php', $base . '/CumpleBooth/database/rollback-adultos.php');
    file_put_contents($base . '/CumpleBooth/public/lib.php', '<?php require ' . var_export($raiz . '/public/lib.php', true) . ";\n");
    r_check(hash_file('sha256', $base . '/CumpleBooth/database/rollback-adultos.php') === hash_file('sha256', $raiz . '/database/rollback-adultos.php'),
        "la réplica $nombre corre el mismo script, byte a byte");
    return $base;
}

/** Un exportar rechazado: sale con error, por la regla esperada, sin crear el archivo ni suspender filas. */
function r_rechazado(string $destino, string $caso, string $motivo, ?string $script = null): void
{
    global $idAdulto, $invAdulto;
    $r = r_script('exportar', $destino, $script);
    $creado = is_file($destino);
    if ($creado) {
        @unlink($destino);   // si la guarda fallara, que el archivo no quede (y menos en un repositorio)
    }
    r_check($r['codigo'] !== 0 && str_contains($r['err'], $motivo), "$caso: se rechaza ($motivo). Salida {$r['codigo']}: {$r['err']}");
    r_check(!$creado, "$caso: no crea el archivo");
    r_check((int) r_fila('cc_parties', $idAdulto)['active'] === 1 && r_fila('cc_invitations', $invAdulto)['status'] === 'published',
        "$caso: no suspende la fiesta ni su invitación");
}

r_rechazado($raiz . '/docs/respaldo-que-no-debe-quedar.json', 'carpeta interna de CumpleBooth', 'una carpeta privada (p. ej.');
$repo = r_replica('repo-ficticio', false);
$scriptRepo = $repo . '/CumpleBooth/database/rollback-adultos.php';
r_rechazado($repo . '/Docs/respaldo-ficticio.json', 'carpeta hermana Docs/ del mismo repositorio', 'repositorio Git', $scriptRepo);
r_rechazado($repo . '/respaldo-ficticio.json', 'raíz Git misma', 'repositorio Git', $scriptRepo);
r_rechazado($repo . '/CumpleBooth/docs/respaldo-ficticio.json', 'carpeta interna del CumpleBooth de la réplica', 'una carpeta privada (p. ej.', $scriptRepo);
mkdir($tmp . '/privado', 0770);
r_rechazado($tmp . '/privado/../repo-ficticio/Docs/respaldo-ficticio.json', 'ruta con .. que vuelve al repositorio', 'repositorio Git', $scriptRepo);
$worktree = r_replica('worktree-ficticio', true);
r_check(is_file($worktree . '/.git'), 'la réplica de worktree tiene `.git` como archivo');
r_rechazado($worktree . '/Docs/respaldo-ficticio.json', '`.git` como archivo (worktree)', 'repositorio Git', $worktree . '/CumpleBooth/database/rollback-adultos.php');

// Enlaces: la carpeta se resuelve por su ruta real y el nombre final no puede ser un enlace. Solo
// si el sistema deja crearlos (en Windows sin modo desarrollador, la carpeta va por unión NTFS).
$enlaces = [];
$carpetaEnlace = $tmp . '/privado-enlazado';
if (@symlink($repo . '/Docs', $carpetaEnlace)
    || (PHP_OS_FAMILY === 'Windows' && (exec('mklink /J ' . escapeshellarg(str_replace('/', '\\', $carpetaEnlace)) . ' ' . escapeshellarg(str_replace('/', '\\', $repo . '/Docs')) . ' 2>NUL', $salidaMklink, $codigoMklink) !== false) && $codigoMklink === 0)) {
    $enlaces[] = 'carpeta';
    r_rechazado($carpetaEnlace . '/respaldo-ficticio.json', 'carpeta privada que es un enlace al repositorio', 'repositorio Git');
    r_check(!is_file($repo . '/Docs/respaldo-ficticio.json'), 'nada llegó al repositorio por el enlace');
}
$nombreEnlace = $tmp . '/privado/enlace-roto.json';
if (@symlink($repo . '/Docs/apuntado.json', $nombreEnlace)) {
    $enlaces[] = 'archivo';
    r_rechazado($nombreEnlace, 'nombre final como enlace roto hacia el repositorio', 'es un enlace');
    r_check(!file_exists($repo . '/Docs/apuntado.json'), 'la escritura no siguió el enlace');
    @unlink($nombreEnlace);
}

$r = r_script('borrar', $tmp . '/x.json');
r_check($r['codigo'] === 2, 'un modo desconocido no hace nada');

// ── Exportar y suspender: destino privado válido, fuera de todo repositorio Git ─
$sinGit = true;
for ($c = (string) realpath($tmp . '/privado'); ; $c = $padre) {
    $sinGit = $sinGit && !file_exists($c . '/.git');
    $padre = dirname($c);
    if ($padre === $c) { break; }
}
r_check($sinGit, 'la carpeta temporal privada no está dentro de ningún repositorio Git (requisito del caso válido)');
$snap = $tmp . '/privado/respaldo-adultos.json';
$r = r_script('exportar', $snap);
r_check($r['codigo'] === 0, 'exportar termina bien: ' . $r['err']);
r_check(($r['json']['fiestas'] ?? null) === ['adulto-prueba'] && ($r['json']['invitaciones'] ?? null) === 1, 'el respaldo cubre solo la fiesta adulta y su invitación');
$datos = json_decode((string) file_get_contents($snap), true);
r_check($datos['fiestas'][0]['birthday_age'] === 61 && $datos['fiestas'][0]['event_type'] === 'adult_birthday' && $datos['fiestas'][0]['active'] === 1, 'el respaldo guarda modalidad, edad y estado');
r_check($datos['invitaciones'][0]['status'] === 'published' && $datos['invitaciones'][0]['published_at'] === $antesInv['published_at'], 'el respaldo guarda el estado de publicación');
r_check((int) r_fila('cc_parties', $idAdulto)['active'] === 0, 'la fiesta adulta queda inactiva');
r_check(r_fila('cc_invitations', $invAdulto)['status'] === 'archived', 'su invitación deja de estar publicada');
r_check(r_fila('cc_parties', $idNino) == $antesNino && r_fila('cc_parties', $idBaby) == $antesBaby, 'la infantil y el baby shower no se tocan');
r_check(r_fila('cc_invitations', $invNino) == $antesInvNino, 'la invitación infantil sigue publicada, igual');
$r = r_script('exportar', $snap);
r_check($r['codigo'] !== 0 && str_contains($r['err'], 'ya existe'), 'no pisa un respaldo existente');
$r = r_script('comprobar', $snap);
r_check($r['codigo'] === 1 && ($r['json']['igual_al_respaldo'] ?? true) === false, 'comprobar avisa que la base difiere mientras dura la suspensión');

// ── Lo que hace el código anterior al guardar cualquier fiesta (188ba45, lib.php:577-584 y 614) ──
$pdo = cb_pdo();
$pdo->prepare("UPDATE cc_parties SET event_type='child_birthday', birthday_age=NULL WHERE id=?")->execute([$idAdulto]);
$pdo->prepare("UPDATE cc_invitations SET event_type='child_birthday' WHERE party_id=?")->execute([$idAdulto]);
// Y cosas legítimas que se hacen en el admin durante el rollback, que restaurar no debe deshacer.
$pdo->prepare("UPDATE cc_parties SET birthday_person_name='Niño Prueba Editado', games3d_enabled=0 WHERE id=?")->execute([$idNino]);
$pdo->prepare("UPDATE cc_parties SET event_date='2026-12-05' WHERE id=?")->execute([$idAdulto]);
$r = r_script('comprobar', $snap);
r_check($r['codigo'] === 1 && count($r['json']['diferencias']) === 5, 'comprobar enumera las cinco diferencias (modalidad, edad y estado de la fiesta; modalidad y publicación de la invitación): ' . json_encode($r['json']['diferencias'] ?? null));

// ── Restaurar ───────────────────────────────────────────────────────────────
$alterado = $tmp . '/alterado.json';
$copia = $datos;
$copia['fiestas'][0]['birthday_age'] = 17;
file_put_contents($alterado, json_encode($copia));
$r = r_script('restaurar', $alterado);
r_check($r['codigo'] !== 0 && str_contains($r['err'], 'suma'), 'un respaldo alterado a mano se rechaza');
r_check(r_fila('cc_parties', $idAdulto)['birthday_age'] === null, 'y no escribe nada');

$r = r_script('restaurar', $snap);
r_check($r['codigo'] === 0 && ($r['json']['igual_al_respaldo'] ?? false) === true, 'restaurar termina igual al respaldo: ' . $r['err']);
$adulto = r_fila('cc_parties', $idAdulto);
r_check($adulto['event_type'] === 'adult_birthday' && (int) $adulto['birthday_age'] === 61 && (int) $adulto['active'] === 1 && $adulto['theme_slug'] === 'adulto-coqueteria-pop',
    'el adulto vuelve exacto: adult_birthday, 61, activo, su temática');
r_check($adulto['event_date'] === '2026-12-05', 'lo editado a mano en otro campo durante el rollback se conserva');
$inv = r_fila('cc_invitations', $invAdulto);
r_check($inv['status'] === 'published' && $inv['event_type'] === 'adult_birthday' && $inv['published_at'] === $antesInv['published_at'] && $inv['published_by'] === $antesInv['published_by'],
    'su invitación vuelve publicada como adulta, con la misma fecha y autor de publicación');
$nino = r_fila('cc_parties', $idNino);
r_check($nino['birthday_person_name'] === 'Niño Prueba Editado' && (int) $nino['games3d_enabled'] === 0 && $nino['event_type'] === 'child_birthday' && (int) $nino['birthday_age'] === 6,
    'la fiesta infantil conserva lo editado durante el rollback');
r_check(r_fila('cc_parties', $idBaby) == $antesBaby, 'el baby shower sigue intacto');
$fiestas = cb_load_parties()['parties'];
r_check(($fiestas['adulto-prueba']['edad'] ?? null) === 61 && count($fiestas['adulto-prueba']['invitados']) === 2, 'el código nuevo lee al adulto con su edad y sus invitados');
r_check((cb_resolve_party('adulto-prueba')['ok'] ?? false) === true, 'el kiosco vuelve a abrir la fiesta adulta');
$r = r_script('restaurar', $snap);
r_check($r['codigo'] === 0, 'restaurar dos veces da lo mismo');

// ── Todo o nada: si falta una fila, no se escribe ninguna ───────────────────
$pdo->prepare("UPDATE cc_parties SET event_type='child_birthday', birthday_age=NULL WHERE id=?")->execute([$idAdulto]);
$sinInv = $tmp . '/sin-invitacion.json';
$copia = $datos;
$copia['invitaciones'][0]['id'] = 999999;
$copia['suma'] = hash('sha256', json_encode(['fiestas' => $copia['fiestas'], 'invitaciones' => $copia['invitaciones']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents($sinInv, json_encode($copia));
$r = r_script('restaurar', $sinInv);
r_check($r['codigo'] !== 0 && str_contains($r['err'], 'No se restauró nada'), 'una invitación que ya no existe detiene la restauración');
r_check(r_fila('cc_parties', $idAdulto)['event_type'] === 'child_birthday', 'y la fiesta no quedó a medias');

// Las uniones y enlaces se sueltan antes de que la limpieza recorra la carpeta temporal.
if (in_array('carpeta', $enlaces, true)) {
    PHP_OS_FAMILY === 'Windows' ? @rmdir($carpetaEnlace) : @unlink($carpetaEnlace);
}
$sinEnlaces = array_diff(['carpeta', 'archivo'], $enlaces);
echo "rollback-adultos: $tests checks OK" . ($sinEnlaces ? ' (NOT_RUN por el sistema: enlace de ' . implode(' y ', $sinEnlaces) . ')' : '') . "\n";
