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
 *    un respaldo existente;
 *  - revisión B4 (Vigía-Claude): ningún webroot de la cuenta (`public_html`, `www`, `htdocs`, en
 *    cualquier dominio) aunque no tenga `.git`, mientras `~/respaldos` y
 *    `domains/cumpleclick.com/database/` siguen aceptados (CX-26); con open_basedir, exportar
 *    se niega sin leer ni escribir y una carpeta padre que no se deja revisar es rechazo (CX-27);
 *    un respaldo de otro entorno, uno cuya invitación hoy pertenece a otra fiesta y uno del
 *    formato /1 no restauran nada (CX-28); y la carpeta temporal no queda (CX-29).
 *
 * El ensayo con el código de 188ba45 de verdad (admin viejo por HTTP) vive fuera del repositorio;
 * esta prueba simula su efecto en la base con las mismas sentencias que ese código ejecuta.
 * Datos ficticios. La SQLite vive en una carpeta temporal.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);

// ── Carpeta temporal (revisión B4, CX-29) ───────────────────────────────────
// cb_pdo() guarda la conexión en una variable estática de lib.php que la prueba no puede cerrar,
// y en Windows un SQLite abierto no se puede borrar: cada corrida dejaba su carpeta en %TEMP%.
// Por eso este proceso no abre la base: crea la carpeta, corre la prueba en un proceso hijo y,
// cuando el hijo terminó (y con él su conexión), la borra, también si la prueba falló. Solo
// borra la carpeta que creó en esta corrida; las uniones y enlaces se sueltan sin entrar en ellos.
$tmpHijo = getenv('CB_RBA_PRUEBA_TMP');
if ($tmpHijo === false || $tmpHijo === '') {
    $tmp = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'cumpleclick-rollback-adultos-' . bin2hex(random_bytes(4));
    if (!mkdir($tmp, 0700)) {
        fwrite(STDERR, "FAIL: no se pudo crear la carpeta temporal\n");
        exit(1);
    }
    $borrar = static function (string $ruta) use (&$borrar): void {
        $real = realpath($ruta);
        $padre = realpath(dirname($ruta));
        if (is_link($ruta) || ($real !== false && $padre !== false && $real !== rtrim($padre, '/\\') . DIRECTORY_SEPARATOR . basename($ruta))) {
            if (!@rmdir($ruta)) { @unlink($ruta); }   // unión (en Windows is_dir() la da por falsa) o enlace
            return;
        }
        if (is_dir($ruta)) {
            foreach (scandir($ruta) ?: [] as $nombre) {
                if ($nombre !== '.' && $nombre !== '..') {
                    $borrar($ruta . DIRECTORY_SEPARATOR . $nombre);
                }
            }
            @rmdir($ruta);
        } elseif (!@unlink($ruta)) {
            @rmdir($ruta);   // una unión cuyo destino ya no existe
        }
    };
    register_shutdown_function(static function () use ($tmp, $borrar): void {
        if (is_dir($tmp)) { $borrar($tmp); }
    });
    $hijo = proc_open([PHP_BINARY, __FILE__], [1 => STDOUT, 2 => STDERR], $pipes, null, array_merge(getenv(), ['CB_RBA_PRUEBA_TMP' => $tmp]));
    $codigo = $hijo === false ? 1 : proc_close($hijo);
    $borrar($tmp);
    clearstatcache();
    if (file_exists($tmp)) {
        fwrite(STDERR, "FAIL: la carpeta temporal $tmp quedó después de la corrida\n");
        exit(1);
    }
    echo 'rollback-adultos: carpeta temporal borrada al salir (la prueba ' . ($codigo === 0 ? 'pasó' : "falló con salida $codigo") . ")\n";
    exit($codigo);
}
$tmp = $tmpHijo;
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
mkdir($tmp . '/invitations', 0770, true);
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

/**
 * Corre el script como lo correría Luis por SSH; $script permite correr la copia de una réplica,
 * $ini agrega opciones `-d` de PHP y $otroEnv cambia variables de entorno (otro entorno).
 */
function r_script(string $modo, string $archivo, ?string $script = null, array $ini = [], array $otroEnv = []): array
{
    global $raiz, $env;
    $script ??= $raiz . '/database/rollback-adultos.php';
    $cmd = [PHP_BINARY];
    foreach ($ini as $clave => $valor) {
        $cmd[] = '-d';
        $cmd[] = $clave . '=' . $valor;
    }
    array_push($cmd, $script, $modo, $archivo);
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname($script, 2), array_merge($env, $otroEnv));
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
function r_rechazado(string $destino, string $caso, string $motivo, ?string $script = null, array $ini = []): void
{
    global $idAdulto, $invAdulto;
    $r = r_script('exportar', $destino, $script, $ini);
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

// Webroots de la misma cuenta sin `.git` (revisión B4, CX-26). Réplica de la cuenta de Hostinger:
// `~` = $tmp/home, con el webroot de preproducción y los dos destinos legítimos de PROD.
$home = $tmp . '/home';
foreach (['/respaldos', '/domains/cumpleclick.com/database', '/domains/cumpleclick.com/public_html/app',
    '/domains/automatizatech.cl/public_html/cumpleclick', '/domains/otro.test/WWW', '/domains/otro.test/HtDocs/sub'] as $sub) {
    mkdir($home . $sub, 0770, true);
}
r_rechazado($home . '/domains/automatizatech.cl/public_html/respaldo-ficticio.json', 'public_html de otro dominio de la cuenta (preproducción)', 'webroot');
r_rechazado($home . '/domains/automatizatech.cl/public_html/cumpleclick/respaldo-ficticio.json', 'subcarpeta del public_html de otro dominio', 'webroot');
r_rechazado($home . '/domains/cumpleclick.com/public_html/app/respaldo-ficticio.json', 'public_html de cumpleclick.com en la réplica', 'webroot');
r_rechazado($home . '/domains/otro.test/WWW/respaldo-ficticio.json', 'carpeta WWW en mayúsculas', 'webroot');
r_rechazado($home . '/domains/otro.test/HtDocs/sub/respaldo-ficticio.json', 'debajo de HtDocs', 'webroot');

// open_basedir (revisión B4, CX-27): con él activo, exportar se niega antes de leer o escribir.
// El destino es el válido de la réplica y el open_basedir deja leer la base y el código, así que
// sin la guarda el exportar pasaría: la búsqueda de `.git` en los padres fallaría abierta.
// Entre comillas y con `/`: `-d` se lee como INI, donde `;` abre un comentario y la lista
// quedaría solo con su primera carpeta.
$baseDir = '"' . str_replace('\\', '/', $tmp . PATH_SEPARATOR . $raiz) . '"';
r_rechazado($home . '/respaldos/respaldo-con-open-basedir.json', 'exportar con open_basedir activo', 'No se leyó ni se escribió nada', null, ['open_basedir' => $baseDir]);
// Y si aun así una carpeta padre no se deja revisar, es rechazo y no «no hay .git». Se prueba la
// función de la guarda sola, con su texto exacto, porque el script entero ya se negó arriba.
$fuente = (string) file_get_contents($raiz . '/database/rollback-adultos.php');
r_check(preg_match('/^function cb_rba_validar_ruta\(.*?^}\R/ms', $fuente, $m) === 1, 'se extrae la función de la guarda del script');
mkdir($tmp . '/guarda', 0770);
file_put_contents($tmp . '/guarda/guarda.php', "<?php\nfunction cb_rba_salir(string \$mensaje, int \$codigo = 1): void { fwrite(STDERR, \$mensaje . \"\\n\"); exit(\$codigo); }\n"
    . $m[0] . "echo cb_rba_validar_ruta(\$argv[1]);\n");
$guarda = static function (array $ini) use ($tmp, $home): array {
    $cmd = [PHP_BINARY];
    foreach ($ini as $clave => $valor) { array_push($cmd, '-d', $clave . '=' . $valor); }
    array_push($cmd, $tmp . '/guarda/guarda.php', $home . '/respaldos/solo-guarda.json');
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $tmp);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return ['codigo' => proc_close($p), 'out' => (string) $out, 'err' => (string) $err];
};
$g = $guarda([]);
r_check($g['codigo'] === 0 && str_ends_with(str_replace('\\', '/', $g['out']), '/home/respaldos/solo-guarda.json'), 'control: sin open_basedir la guarda sola acepta ~/respaldos. ' . $g['err']);
$g = $guarda(['open_basedir' => $tmp]);
r_check($g['codigo'] !== 0 && str_contains($g['err'], 'No se pudo revisar la carpeta'), 'una carpeta padre que no se deja revisar es rechazo. Salida ' . $g['codigo'] . ': ' . $g['err']);
r_check(!file_exists($home . '/respaldos/solo-guarda.json'), 'la guarda sola no escribe nada');

$r = r_script('borrar', $tmp . '/x.json');
r_check($r['codigo'] === 2, 'un modo desconocido no hace nada');

// ── Exportar y suspender: `~/respaldos` de la réplica, fuera de todo repositorio Git ─
$sinGit = true;
for ($c = (string) realpath($home . '/respaldos'); ; $c = $padre) {
    $sinGit = $sinGit && !file_exists($c . '/.git');
    $padre = dirname($c);
    if ($padre === $c) { break; }
}
r_check($sinGit, 'la carpeta ~/respaldos de la réplica no está dentro de ningún repositorio Git (requisito del caso válido)');
$snap = $home . '/respaldos/respaldo-adultos.json';
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

// ── Respaldos que no son de esta base (revisión B4, CX-28) ──────────────────
/** Un restaurar o comprobar rechazado: sale con error, por el motivo esperado, sin escribir en la base. */
$sinEscribir = static function (array $r, string $caso, string $motivo) use ($idAdulto, $invAdulto): void {
    r_check($r['codigo'] !== 0 && str_contains($r['err'], $motivo), "$caso: se rechaza ($motivo). Salida {$r['codigo']}: {$r['err']}");
    $f = r_fila('cc_parties', $idAdulto);
    $i = r_fila('cc_invitations', $invAdulto);
    r_check($f['event_type'] === 'child_birthday' && $f['birthday_age'] === null && (int) $f['active'] === 0 && $i['status'] === 'archived',
        "$caso: no escribe nada (la fiesta y su invitación siguen como las dejó el rollback)");
};
r_check(is_string($datos['entorno'] ?? null) && strlen($datos['entorno']) === 64, 'el respaldo guarda una huella del entorno de 64 hex');
$crudo = (string) file_get_contents($snap);
r_check(!str_contains($crudo, 'sqlite:') && !str_contains($crudo, 'test.sqlite') && !str_contains($crudo, 'example.test'),
    'el respaldo no trae el DSN, la ruta de la base ni la URL en claro');
$otraUrl = ['CC_PUBLIC_BASE_URL' => 'https://otro-entorno.example.test/cumpleclick'];
$sinEscribir(r_script('restaurar', $snap, null, [], $otraUrl), 'restaurar con otra URL pública (otro entorno)', 'otro entorno');
$sinEscribir(r_script('comprobar', $snap, null, [], $otraUrl), 'comprobar en otro entorno', 'otro entorno');
// Otra base con las mismas filas (una copia): el respaldo válido de esta no se aplica en aquella.
$otraBase = $tmp . '/otra-base.sqlite';
r_check(copy($tmp . '/test.sqlite', $otraBase), 'se copia la base a otro archivo');
$r = r_script('restaurar', $snap, null, [], ['CC_PDO_DSN' => 'sqlite:' . $otraBase]);
r_check($r['codigo'] !== 0 && str_contains($r['err'], 'otro entorno'), 'restaurar sobre otra base con las mismas filas se rechaza: ' . $r['err']);
$otraPdo = new PDO('sqlite:' . $otraBase);
r_check($otraPdo->query("SELECT birthday_age FROM cc_parties WHERE id = $idAdulto")->fetchColumn() === null, 'y la otra base no se tocó');
$otraPdo = null;
// La misma base, pero la invitación del respaldo hoy es de otra fiesta: no se publica ni se mueve.
cb_pdo()->prepare('UPDATE cc_invitations SET party_id = ? WHERE id = ?')->execute([$idNino, $invAdulto]);
$sinEscribir(r_script('restaurar', $snap), 'la invitación del respaldo pertenece hoy a otra fiesta', 'pertenece en esta base a otra fiesta');
r_check((int) r_fila('cc_invitations', $invAdulto)['party_id'] === $idNino, 'y no la mueve de fiesta');
cb_pdo()->prepare('UPDATE cc_invitations SET party_id = ? WHERE id = ?')->execute([$idAdulto, $invAdulto]);
// Un respaldo del formato /1 (sin huella), con su suma correcta: ya no se acepta, con un mensaje claro.
$v1 = $tmp . '/respaldo-formato-1.json';
$copia = $datos;
unset($copia['entorno']);
$copia['formato'] = 'cumpleclick-rollback-adultos/1';
$copia['suma'] = hash('sha256', json_encode(['fiestas' => $copia['fiestas'], 'invitaciones' => $copia['invitaciones']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents($v1, json_encode($copia));
$sinEscribir(r_script('restaurar', $v1), 'respaldo del formato /1', 'formato anterior');
$sinEscribir(r_script('comprobar', $v1), 'comprobar un respaldo del formato /1', 'formato anterior');

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

// El otro destino legítimo de PROD, junto al script (revisión B4, CX-26): no se rechaza por error.
$snap2 = $home . '/domains/cumpleclick.com/database/respaldo-adultos-2.json';
$r = r_script('exportar', $snap2);
r_check($r['codigo'] === 0 && is_file($snap2), 'exportar a domains/cumpleclick.com/database/ se acepta: ' . $r['err']);
r_check((int) r_fila('cc_parties', $idAdulto)['active'] === 0, 'y suspende como siempre');
$r = r_script('restaurar', $snap2);
r_check($r['codigo'] === 0 && ($r['json']['igual_al_respaldo'] ?? false) === true, 'y ese respaldo restaura: ' . $r['err']);
r_check((int) r_fila('cc_parties', $idAdulto)['active'] === 1 && r_fila('cc_invitations', $invAdulto)['status'] === 'published', 'el adulto vuelve activo y publicado');

// ── Todo o nada: si falta una fila, no se escribe ninguna ───────────────────
$pdo->prepare("UPDATE cc_parties SET event_type='child_birthday', birthday_age=NULL WHERE id=?")->execute([$idAdulto]);
$sinInv = $tmp . '/sin-invitacion.json';
$copia = $datos;
$copia['invitaciones'][0]['id'] = 999999;
$copia['suma'] = hash('sha256', json_encode(['entorno' => $copia['entorno'], 'fiestas' => $copia['fiestas'], 'invitaciones' => $copia['invitaciones']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents($sinInv, json_encode($copia));
$r = r_script('restaurar', $sinInv);
r_check($r['codigo'] !== 0 && str_contains($r['err'], 'No se restauró nada'), 'una invitación que ya no existe detiene la restauración');
r_check(r_fila('cc_parties', $idAdulto)['event_type'] === 'child_birthday', 'y la fiesta no quedó a medias');

// Las uniones y enlaces se sueltan antes de que la limpieza recorra la carpeta temporal.
if (in_array('carpeta', $enlaces, true)) {
    PHP_OS_FAMILY === 'Windows' ? @rmdir($carpetaEnlace) : @unlink($carpetaEnlace);
}
$pdo = null;   // la conexión estática de cb_pdo() se cierra al terminar este proceso hijo (CX-29)
$sinEnlaces = array_diff(['carpeta', 'archivo'], $enlaces);
echo "rollback-adultos: $tests checks OK" . ($sinEnlaces ? ' (NOT_RUN por el sistema: enlace de ' . implode(' y ', $sinEnlaces) . ')' : '') . "\n";
