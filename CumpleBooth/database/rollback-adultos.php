<?php
/**
 * Rollback de AT-CUMPLECLICK-026 sin perder los cumpleaños de adulto (revisión B2 de Codex, CX-04).
 *
 * El código anterior (188ba45) no conoce `adult_birthday` y su `cb_save_parties()` reescribe
 * TODAS las fiestas cada vez que el admin guarda cualquiera: lee el adulto como infantil
 * (`lib.php:422`), lo vuelve a escribir como `child_birthday` con edad nula porque el tope era 17
 * (`lib.php:577-584`) y arrastra a su invitación (`lib.php:614`). Además, `active=0` no apaga
 * la invitación publicada: la página pública solo mira `status='published'`, y con el código
 * anterior la mostraría con el vocabulario de una fiesta infantil.
 *
 * Por eso el rollback va en tres tiempos, con este script en `database/` (fuera de la web):
 *
 *   php rollback-adultos.php exportar  <archivo.json>   ANTES de bajar el código, con el nuevo arriba.
 *       Guarda modalidad, edad, estado y temática de cada fiesta adulta y el estado de sus
 *       invitaciones; después las suspende (fiesta `active=0`, invitación `archived`), para que
 *       el código anterior no sirva nada de ellas.
 *   php rollback-adultos.php comprobar <archivo.json>   En cualquier momento; solo lee.
 *       Sale 0 si la base coincide con el respaldo y 1 si no (durante el rollback, 1 es lo esperado).
 *   php rollback-adultos.php restaurar <archivo.json>   DESPUÉS de volver a subir el código nuevo.
 *       Devuelve exactamente lo guardado, todo o nada, y comprueba al final.
 *
 * Lo que el admin cambie a mano durante el rollback en otros campos (nombre, fecha, invitados)
 * se conserva: solo se restauran los campos que el código anterior corrompe o que este script
 * suspende. No borra filas. El archivo de respaldo no puede quedar dentro de la carpeta servida,
 * de ningún webroot ni de ningún repositorio Git (ver cb_rba_validar_ruta); se crea nuevo, nunca
 * se pisa.
 *
 * Formato /2 (revisión B4, CX-28): el respaldo lleva la huella del entorno donde se exportó y
 * comprobar y restaurar se niegan en otro entorno; restaurar exige además que cada fiesta conserve
 * su slug y cada invitación su fiesta. Un respaldo /1 ya no se acepta: no trae huella.
 * La suma sha256 del archivo es de integridad (detecta un archivo dañado o editado sin cuidado),
 * no una firma: quien puede escribir el archivo puede recalcularla.
 */
if (PHP_SAPI !== 'cli') {
    exit(2);
}

const CB_RBA_FORMATO = 'cumpleclick-rollback-adultos/2';
const CB_RBA_FORMATO_ANTERIOR = 'cumpleclick-rollback-adultos/1';
const CB_RBA_CAMPOS_FIESTA = ['event_type', 'birthday_age', 'active', 'theme_slug'];
const CB_RBA_CAMPOS_INVITACION = ['party_id', 'event_type', 'status', 'published_at', 'published_by', 'revoked_at', 'revoked_by'];

function cb_rba_salir(string $mensaje, int $codigo = 1): void
{
    fwrite(STDERR, $mensaje . "\n");
    exit($codigo);
}

$modo = (string) ($argv[1] ?? '');
$archivo = (string) ($argv[2] ?? '');
if (!in_array($modo, ['exportar', 'comprobar', 'restaurar'], true) || $archivo === '') {
    cb_rba_salir("Uso: php rollback-adultos.php exportar|comprobar|restaurar <archivo.json>", 2);
}
// Con open_basedir, PHP no deja mirar fuera de las carpetas permitidas y la búsqueda de `.git`
// en los padres daría «no hay» sin serlo: la guarda fallaría abierta (revisión B4, CX-27). En
// la consola no debería estar activo; si lo está, exportar se niega antes de leer o escribir.
if ($modo === 'exportar' && (string) ini_get('open_basedir') !== '') {
    cb_rba_salir('open_basedir está activo en este PHP y no deja revisar las carpetas padre del respaldo. '
        . 'No se leyó ni se escribió nada. Correr: php -d open_basedir= rollback-adultos.php exportar <archivo.json>');
}

require __DIR__ . '/../public/lib.php';

if (cb_storage_mode() !== 'db') {
    cb_rba_salir('Este script solo trabaja con la base de datos (CC_STORAGE_MODE=db).');
}
// Exportar y restaurar solo tienen sentido con el código nuevo arriba: con el anterior, el
// admin volvería a pisar al adulto apenas alguien guarde una fiesta.
$codigoNuevo = function_exists('cb_party_event_type') && cb_party_event_type('adult_birthday') === 'adult_birthday';
if ($modo !== 'comprobar' && !$codigoNuevo) {
    cb_rba_salir("El lib.php cargado es el anterior (no conoce adult_birthday). '$modo' se corre con el código nuevo arriba.");
}

/**
 * El respaldo trae slugs y estados de fiestas reales (revisión B3 de Codex, CX-25). Se niega,
 * antes de escribir nada y antes de suspender filas, si la carpeta destino:
 *   1. está dentro de la carpeta servida: el padre real de `../public` (en PROD `public_html`,
 *      porque `public` es el enlace a `public_html/app`; en local, `CumpleBooth/`);
 *   2. está dentro de cualquier webroot: algún tramo de su ruta real se llama `public_html`, `www`
 *      o `htdocs`, sin distinguir mayúsculas (revisión B4, CX-26). La cuenta de Hostinger sirve
 *      también `domains/automatizatech.cl/public_html/` (preproducción), que el punto 1 no conoce.
 *      Los destinos legítimos de PROD, `~/respaldos` y `domains/cumpleclick.com/database/`, no
 *      tienen ninguno de esos tramos;
 *   3. está dentro de cualquier repositorio Git: ella o alguno de sus padres tiene `.git`, sea
 *      carpeta (checkout normal) o archivo (worktree o submódulo). Así queda fuera la raíz Git
 *      entera, `Docs/` incluida, y no solo el subproyecto. Un .gitignore no basta: no impide
 *      versionar el archivo ni le da privacidad.
 * Enlaces: la carpeta se compara por su ruta real (realpath resuelve los enlaces de todos sus
 * tramos) y se escribe en esa ruta real; el nombre final no puede ser un enlace, ni siquiera roto,
 * porque la escritura lo seguiría hacia otra carpeta. Tampoco puede existir ya.
 * Si al subir por los padres no aparece `.git` (PROD no tiene), la búsqueda termina en la raíz
 * del sistema sin error. Pero cada carpeta del camino tiene que dejarse revisar (revisión B4,
 * CX-27): si PHP no puede entrar a una, «no hay .git» no se puede afirmar y se rechaza. Se exige
 * poder entrar (lo que pide buscar `.git` dentro), no listar: en un hosting `/home` suele no
 * dejarse listar y aun así no esconde nada. Devuelve la ruta real del archivo a crear.
 */
function cb_rba_validar_ruta(string $archivo): string
{
    $dir = realpath(dirname($archivo));
    if ($dir === false || !is_dir($dir)) {
        cb_rba_salir('No existe la carpeta del archivo de respaldo.');
    }
    $nombre = basename($archivo);
    if ($nombre === '' || $nombre === '.' || $nombre === '..') {
        cb_rba_salir('Falta el nombre del archivo de respaldo.');
    }
    $comparable = static fn(string $ruta): string => rtrim(str_replace('\\', '/', strtolower($ruta)), '/') . '/';
    $publico = realpath(__DIR__ . '/../public');
    $servida = $publico !== false ? dirname($publico) : false;   // public_html en PROD; la carpeta del proyecto en local
    if ($servida !== false && str_starts_with($comparable($dir), $comparable($servida))) {
        cb_rba_salir('El respaldo no puede quedar dentro de la carpeta servida ' . $servida . ': usar una carpeta privada (p. ej. ~/respaldos).');
    }
    foreach (preg_split('#[/\\\\]+#', $dir, -1, PREG_SPLIT_NO_EMPTY) as $tramo) {
        if (in_array(strtolower($tramo), ['public_html', 'www', 'htdocs'], true)) {
            cb_rba_salir('El respaldo no puede quedar dentro de un webroot (' . $tramo . ' en ' . $dir . '): usar una carpeta privada (p. ej. ~/respaldos).');
        }
    }
    $dentro = static fn(string $carpeta, string $nombre): string => rtrim($carpeta, '/\\') . DIRECTORY_SEPARATOR . $nombre;
    for ($carpeta = $dir; ; $carpeta = $padre) {
        if (!@is_dir($dentro($carpeta, '.'))) {
            cb_rba_salir('No se pudo revisar la carpeta ' . $carpeta . ' (¿permisos u open_basedir?): no se puede asegurar que el respaldo quede fuera de un repositorio Git. Usar una carpeta privada (p. ej. ~/respaldos).');
        }
        $git = $dentro($carpeta, '.git');
        if (file_exists($git) || is_link($git)) {
            cb_rba_salir('El respaldo no puede quedar dentro del repositorio Git de ' . $carpeta
                . ': usar una carpeta privada fuera de todo repositorio (p. ej. ~/respaldos).');
        }
        $padre = dirname($carpeta);
        if ($padre === $carpeta) {
            break;   // raíz del sistema: no hay repositorio arriba
        }
    }
    $destino = $dir . DIRECTORY_SEPARATOR . $nombre;
    if (is_link($destino)) {
        cb_rba_salir('El archivo de respaldo es un enlace: la escritura lo seguiría a otra carpeta. Elegir otro nombre.');
    }
    if (file_exists($destino)) {
        cb_rba_salir('El archivo ya existe: no se sobrescribe un respaldo. Elegir otro nombre.');
    }
    return $destino;
}

function cb_rba_normalizar_fiesta(array $f): array
{
    return [
        'id' => (int) $f['id'],
        'public_slug' => (string) $f['public_slug'],
        'event_type' => (string) $f['event_type'],
        'birthday_age' => $f['birthday_age'] === null ? null : (int) $f['birthday_age'],
        'active' => (int) $f['active'],
        'theme_slug' => $f['theme_slug'] === null ? null : (string) $f['theme_slug'],
    ];
}

function cb_rba_normalizar_invitacion(array $i): array
{
    $texto = static fn($v) => $v === null ? null : (string) $v;
    return [
        'id' => (int) $i['id'],
        'party_id' => $i['party_id'] === null ? null : (int) $i['party_id'],
        'event_type' => (string) $i['event_type'],
        'status' => (string) $i['status'],
        'published_at' => $texto($i['published_at']),
        'published_by' => $texto($i['published_by']),
        'revoked_at' => $texto($i['revoked_at']),
        'revoked_by' => $texto($i['revoked_by']),
    ];
}

/** Suma de integridad (no es una firma: no lleva clave). Cubre también la huella del entorno. */
function cb_rba_suma(array $fiestas, array $invitaciones, string $entorno): string
{
    return hash('sha256', json_encode(['entorno' => $entorno, 'fiestas' => $fiestas, 'invitaciones' => $invitaciones], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Huella del entorno (revisión B4, CX-28): sha256 del motor, del nombre de la base que informa el
 * propio servidor (en SQLite, la ruta del archivo) y de la URL pública configurada. La URL separa
 * dos entornos cuya base se llame igual (local y PROD). Nunca entra el DSN, el usuario ni la
 * contraseña, y en el respaldo solo queda el hash. Solo usa cb_pdo() y cb_config(), que también
 * existen en el código anterior: comprobar tiene que seguir funcionando con él arriba.
 */
function cb_rba_entorno(PDO $pdo): string
{
    $motor = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $base = '';
    if ($motor === 'mysql') {
        $base = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    } elseif ($motor === 'pgsql') {
        $base = (string) $pdo->query('SELECT current_database()')->fetchColumn();
    } elseif ($motor === 'sqlite') {
        foreach ($pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            if (($fila['name'] ?? '') === 'main') {
                $ruta = (string) ($fila['file'] ?? '');
                $real = $ruta !== '' ? realpath($ruta) : false;
                $base = str_replace('\\', '/', $real !== false ? $real : $ruta);
                $base = PHP_OS_FAMILY === 'Windows' ? strtolower($base) : $base;
            }
        }
    }
    $url = rtrim((string) cb_config('public_base_url'), '/');
    return hash('sha256', json_encode(['motor' => $motor, 'base' => $base, 'url' => $url], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function cb_rba_fiesta_actual(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT id, public_slug, event_type, birthday_age, active, theme_slug FROM cc_parties WHERE id = ?');
    $st->execute([$id]);
    $fila = $st->fetch(PDO::FETCH_ASSOC);
    return $fila ? cb_rba_normalizar_fiesta($fila) : null;
}

function cb_rba_invitacion_actual(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT id, party_id, event_type, status, published_at, published_by, revoked_at, revoked_by FROM cc_invitations WHERE id = ?');
    $st->execute([$id]);
    $fila = $st->fetch(PDO::FETCH_ASSOC);
    return $fila ? cb_rba_normalizar_invitacion($fila) : null;
}

/** Lee y valida el respaldo antes de mirar la base: formato, suma de integridad y entorno. */
function cb_rba_leer(string $archivo, PDO $pdo): array
{
    $datos = is_file($archivo) ? json_decode((string) file_get_contents($archivo), true) : null;
    if (is_array($datos) && ($datos['formato'] ?? '') === CB_RBA_FORMATO_ANTERIOR) {
        cb_rba_salir('El respaldo es del formato anterior (' . CB_RBA_FORMATO_ANTERIOR . '), sin huella del entorno, y ya no se acepta. '
            . 'No se toca la base. Exportar de nuevo con esta versión del script (o recurrir al volcado de la base).');
    }
    if (!is_array($datos) || ($datos['formato'] ?? '') !== CB_RBA_FORMATO || !is_string($datos['entorno'] ?? null)
        || !is_array($datos['fiestas'] ?? null) || !is_array($datos['invitaciones'] ?? null)) {
        cb_rba_salir('El archivo no es un respaldo de rollback-adultos válido.');
    }
    if (!hash_equals(cb_rba_suma($datos['fiestas'], $datos['invitaciones'], $datos['entorno']), (string) ($datos['suma'] ?? ''))) {
        cb_rba_salir('La suma de integridad del respaldo no coincide: el archivo se dañó o fue modificado. No se toca la base.');
    }
    if (!hash_equals(cb_rba_entorno($pdo), $datos['entorno'])) {
        cb_rba_salir('El respaldo es de otro entorno (otra base u otra URL pública): no corresponde a esta base. No se toca la base.');
    }
    return $datos;
}

/** Compara la base con el respaldo. Devuelve la lista de diferencias (vacía = igual). */
function cb_rba_diferencias(PDO $pdo, array $datos): array
{
    $dif = [];
    foreach ($datos['fiestas'] as $f) {
        $actual = cb_rba_fiesta_actual($pdo, (int) $f['id']);
        if ($actual === null || $actual['public_slug'] !== $f['public_slug']) {
            $dif[] = ['fiesta' => $f['public_slug'], 'campo' => 'existe', 'respaldo' => true, 'ahora' => false];
            continue;
        }
        foreach (CB_RBA_CAMPOS_FIESTA as $c) {
            if ($actual[$c] !== $f[$c]) {
                $dif[] = ['fiesta' => $f['public_slug'], 'campo' => $c, 'respaldo' => $f[$c], 'ahora' => $actual[$c]];
            }
        }
    }
    foreach ($datos['invitaciones'] as $i) {
        $actual = cb_rba_invitacion_actual($pdo, (int) $i['id']);
        if ($actual === null) {
            $dif[] = ['invitacion' => $i['id'], 'campo' => 'existe', 'respaldo' => true, 'ahora' => false];
            continue;
        }
        foreach (CB_RBA_CAMPOS_INVITACION as $c) {
            if ($actual[$c] !== $i[$c]) {
                $dif[] = ['invitacion' => $i['id'], 'campo' => $c, 'respaldo' => $i[$c], 'ahora' => $actual[$c]];
            }
        }
    }
    return $dif;
}

$pdo = cb_pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ahora = gmdate('Y-m-d H:i:s');

if ($modo === 'exportar') {
    // La ruta se valida antes de leer, escribir o suspender cualquier cosa.
    $archivo = cb_rba_validar_ruta($archivo);
    $fiestas = array_map('cb_rba_normalizar_fiesta', $pdo->query(
        "SELECT id, public_slug, event_type, birthday_age, active, theme_slug FROM cc_parties WHERE event_type = 'adult_birthday' ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC));
    $ids = array_column($fiestas, 'id');
    $sql = "SELECT id, party_id, event_type, status, published_at, published_by, revoked_at, revoked_by FROM cc_invitations WHERE event_type = 'adult_birthday'";
    if ($ids) {
        $sql .= ' OR party_id IN (' . implode(',', array_map('intval', $ids)) . ')';
    }
    $invitaciones = array_map('cb_rba_normalizar_invitacion', $pdo->query($sql . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
    $entorno = cb_rba_entorno($pdo);
    $datos = [
        'formato' => CB_RBA_FORMATO,
        'creado' => $ahora,
        'entorno' => $entorno,
        'fiestas' => $fiestas,
        'invitaciones' => $invitaciones,
        'suma' => cb_rba_suma($fiestas, $invitaciones, $entorno),
    ];
    // Primero el archivo (con 0600 y releído), después la suspensión: si el respaldo no quedó,
    // la base no se toca. `x` lo crea solo si no existe y no sigue un enlace puesto entre la
    // validación y la escritura; el 0600 va antes del contenido. El umask hace que nazca ya sin
    // permisos para grupo y otros, aunque el chmod fallara (nota de la revisión B4).
    $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    umask(0077);
    $fh = @fopen($archivo, 'xb');
    if ($fh === false) {
        cb_rba_salir('No se pudo crear el respaldo (¿apareció otro archivo con ese nombre?). La base no se tocó.');
    }
    @chmod($archivo, 0600);
    // En POSIX se comprueba sobre el archivo abierto (no por su nombre) que nadie más lo pueda
    // leer. En Windows los bits de grupo y otros no existen y se omite.
    $modoArchivo = fstat($fh)['mode'] ?? 0777;
    if (PHP_OS_FAMILY !== 'Windows' && ($modoArchivo & 0077) !== 0) {
        fclose($fh);
        @unlink($archivo);   // vacío y recién creado por este script
        cb_rba_salir(sprintf('El respaldo quedaría legible por grupo u otros (permisos %o). No se escribió y la base no se tocó.', $modoArchivo & 0777));
    }
    $escritos = fwrite($fh, $json);
    $cerrado = fclose($fh);
    if ($escritos !== strlen($json) || !$cerrado) {
        cb_rba_salir('No se pudo escribir el respaldo. La base no se tocó.');
    }
    cb_rba_leer($archivo, $pdo);
    try {
        $pdo->beginTransaction();
        $apagarFiesta = $pdo->prepare('UPDATE cc_parties SET active = 0, updated_at = ? WHERE id = ?');
        foreach ($fiestas as $f) {
            $apagarFiesta->execute([$ahora, $f['id']]);
        }
        // `archived` y no `revoked`: no toca revoked_at/by, y en el admin anterior se lee como
        // «Archivada», que nadie confunde con una invitación para volver a publicar.
        $archivar = $pdo->prepare("UPDATE cc_invitations SET status = 'archived', updated_at = ? WHERE id = ?");
        foreach ($invitaciones as $i) {
            $archivar->execute([$ahora, $i['id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        cb_rba_salir('No se pudo suspender (la base quedó como estaba; el respaldo sí se escribió): ' . $e->getMessage());
    }
    // Si alguien creó otra fiesta adulta mientras tanto, el respaldo no la cubre.
    $nuevas = (int) $pdo->query("SELECT COUNT(*) FROM cc_parties WHERE event_type = 'adult_birthday' AND active = 1")->fetchColumn();
    echo json_encode([
        'modo' => 'exportar', 'archivo' => $archivo,
        'fiestas' => array_column($fiestas, 'public_slug'),
        'invitaciones' => count($invitaciones),
        'suspendidas' => true,
        'adultas_activas_fuera_del_respaldo' => $nuevas,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit($nuevas === 0 ? 0 : 1);
}

$datos = cb_rba_leer($archivo, $pdo);

if ($modo === 'restaurar') {
    try {
        $pdo->beginTransaction();
        // Antes de escribir nada: todas las filas del respaldo tienen que seguir ahí y ser las
        // mismas. Una fiesta se reconoce por id y slug; una invitación, por id y por la fiesta a
        // la que pertenece (revisión B4, CX-28: con solo el id, un respaldo de otra base publicó
        // la invitación de otra fiesta). El código anterior no cambia party_id, así que en un
        // rollback normal siempre calza. Si algo falta o no calza, no se escribe nada.
        foreach ($datos['fiestas'] as $f) {
            $actual = cb_rba_fiesta_actual($pdo, (int) $f['id']);
            if ($actual === null || $actual['public_slug'] !== $f['public_slug']) {
                throw new RuntimeException('Falta la fiesta ' . $f['public_slug'] . ' (id ' . $f['id'] . ').');
            }
        }
        foreach ($datos['invitaciones'] as $i) {
            $actual = cb_rba_invitacion_actual($pdo, (int) $i['id']);
            if ($actual === null) {
                throw new RuntimeException('Falta la invitación ' . $i['id'] . '.');
            }
            $partyRespaldo = $i['party_id'] === null ? null : (int) $i['party_id'];
            if ($actual['party_id'] !== $partyRespaldo) {
                throw new RuntimeException('La invitación ' . $i['id'] . ' pertenece en esta base a otra fiesta (party_id '
                    . var_export($actual['party_id'], true) . ' y no ' . var_export($partyRespaldo, true) . '): el respaldo no es de esta base.');
            }
        }
        $fiesta = $pdo->prepare('UPDATE cc_parties SET event_type = ?, birthday_age = ?, active = ?, theme_slug = ?, updated_at = ? WHERE id = ? AND public_slug = ?');
        foreach ($datos['fiestas'] as $f) {
            $fiesta->execute([$f['event_type'], $f['birthday_age'], $f['active'], $f['theme_slug'], $ahora, $f['id'], $f['public_slug']]);
        }
        $invitacion = $pdo->prepare('UPDATE cc_invitations SET party_id = ?, event_type = ?, status = ?, published_at = ?, published_by = ?, revoked_at = ?, revoked_by = ?, updated_at = ? WHERE id = ?');
        foreach ($datos['invitaciones'] as $i) {
            $invitacion->execute([$i['party_id'], $i['event_type'], $i['status'], $i['published_at'], $i['published_by'], $i['revoked_at'], $i['revoked_by'], $ahora, $i['id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        cb_rba_salir('No se restauró nada: ' . $e->getMessage());
    }
}

$dif = cb_rba_diferencias($pdo, $datos);
echo json_encode([
    'modo' => $modo,
    'fiestas' => count($datos['fiestas']),
    'invitaciones' => count($datos['invitaciones']),
    'igual_al_respaldo' => $dif === [],
    'diferencias' => $dif,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
exit($dif === [] ? 0 : 1);
