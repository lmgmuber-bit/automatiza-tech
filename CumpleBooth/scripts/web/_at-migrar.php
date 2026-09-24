<?php
/**
 * _at-migrar.php — corre las migraciones pendientes DESDE EL NAVEGADOR.
 * Para hostings sin SSH. Equivalente web de scripts/migrate.php.
 *
 * USO
 *   1. Abre este archivo en un editor y cambia $TOKEN por algo largo tuyo.
 *   2. Sube el archivo a /public_html/cumpleclick/_at-migrar.php
 *   3. Abre https://automatizatech.cl/cumpleclick/_at-migrar.php
 *      Pega el token, mira la lista (no escribe nada todavía) y luego aplica.
 *   4. BÓRRALO. Hay un botón que lo borra solo; si falla, bórralo por FTP.
 *
 * El token va por POST, no por la URL: así no queda en los logs del servidor.
 * Mientras el token siga siendo el de fábrica, el script se niega a funcionar.
 */

// ---------------------------------------------------------------- CONFIGURA
$TOKEN = 'CAMBIA-ESTO-ANTES-DE-SUBIR';

// Déjalo vacío para autodetectar. Si no encuentra las migraciones, pon aquí la
// ruta absoluta de la carpeta database/migrations de tu directorio privado.
$MIGRACIONES_DIR = '';

// Migraciones que NO se aplican todavía.
//
// La 007 (Álbum Recuerdo) estuvo acá mientras el módulo no estaba terminado.
// Se sacó el 2026-08-25, cuando el álbum quedó subido a PROD y verificado
// archivo por archivo. Mientras estuvo en la lista, PROD servía album.html y
// sus assets pero album-api.php devolvía 503 `unavailable` para cualquier
// token: los archivos estaban, las tablas no.
$OMITIR = [];
// ---------------------------------------------------------------------------

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Content-Type: text/html; charset=utf-8');

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

$tokenOk = false;
$enviado = (string) ($_POST['t'] ?? '');
if (strlen($TOKEN) >= 20 && $TOKEN !== 'CAMBIA-ESTO-ANTES-DE-SUBIR' && $enviado !== '') {
    $tokenOk = hash_equals($TOKEN, $enviado);
}
$accion = (string) ($_POST['accion'] ?? '');

?><!doctype html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>CumpleClick · migraciones</title>
<style>
 body{font:15px/1.55 system-ui,Segoe UI,sans-serif;background:#0f1720;color:#e6edf3;margin:0;padding:28px}
 .wrap{max-width:820px;margin:0 auto}
 h1{font-size:20px;margin:0 0 4px} .sub{color:#8fa3b8;margin:0 0 22px}
 .card{background:#16212c;border:1px solid #24323f;border-radius:10px;padding:18px;margin:0 0 16px}
 table{width:100%;border-collapse:collapse;font-size:14px}
 th,td{text-align:left;padding:7px 10px;border-bottom:1px solid #24323f}
 th{color:#8fa3b8;font-weight:600}
 code{background:#0f1720;padding:1px 5px;border-radius:4px;font-size:13px}
 input[type=password],input[type=text]{background:#0f1720;border:1px solid #2c3d4d;color:#e6edf3;
  border-radius:6px;padding:9px 11px;width:320px;max-width:100%;font-size:14px}
 button{background:#2f81f7;border:0;color:#fff;border-radius:6px;padding:10px 18px;
  font-size:14px;font-weight:600;cursor:pointer;margin-top:10px}
 button.danger{background:#b3202b} button.ghost{background:#33445a}
 .ok{color:#4ec97b} .warn{color:#e9b949} .bad{color:#ff6b6b} .mut{color:#8fa3b8}
 pre{background:#0f1720;border:1px solid #24323f;border-radius:8px;padding:12px;
  overflow:auto;font-size:13px;white-space:pre-wrap}
</style></head><body><div class="wrap">
<h1>CumpleClick · migraciones de base de datos</h1>
<p class="sub">Bórrame del servidor apenas termines.</p>
<?php

if (strlen($TOKEN) < 20 || $TOKEN === 'CAMBIA-ESTO-ANTES-DE-SUBIR') {
    echo '<div class="card"><p class="bad">Este script está desactivado.</p>'
       . '<p class="mut">Edita <code>$TOKEN</code> arriba del archivo y pon una '
       . 'clave tuya de 20 caracteres o más. Después vuelve a subirlo.</p></div>';
    echo '</div></body></html>';
    exit;
}

if (!$tokenOk) {
    if ($enviado !== '') { echo '<div class="card"><p class="bad">Token incorrecto.</p></div>'; }
    echo '<div class="card"><form method="post">'
       . '<label class="mut">Token</label><br>'
       . '<input type="password" name="t" autocomplete="off" autofocus><br>'
       . '<button type="submit">Entrar</button>'
       . '</form></div>';
    echo '</div></body></html>';
    exit;
}

// ------------------------------------------------------------ autodestrucción
if ($accion === 'autodestruir') {
    $borrado = @unlink(__FILE__);
    echo '<div class="card">';
    echo $borrado
        ? '<p class="ok">Archivo borrado del servidor. Ya no existe esta URL.</p>'
        : '<p class="bad">No se pudo borrar solo (permisos). Bórralo por FTP: '
          . '<code>' . h(basename(__FILE__)) . '</code></p>';
    echo '</div></div></body></html>';
    exit;
}

// ------------------------------------------------------------------ contexto
$libCandidatos = [__DIR__ . '/lib.php', dirname(__DIR__, 2) . '/public/lib.php'];
$lib = null;
foreach ($libCandidatos as $c) { if (is_file($c)) { $lib = $c; break; } }
if ($lib === null) {
    echo '<div class="card"><p class="bad">No encuentro <code>lib.php</code>.</p>'
       . '<p class="mut">Este archivo tiene que quedar en la misma carpeta que '
       . '<code>lib.php</code> (la raíz de <code>/cumpleclick/</code>).</p></div>'
       . '</div></body></html>';
    exit;
}
require $lib;

// Localizar database/migrations
$candidatos = [];
if ($MIGRACIONES_DIR !== '') { $candidatos[] = $MIGRACIONES_DIR; }
try {
    $stateDir = cb_private_dir((string) cb_config('state_dir'), 'state_dir');
    $privado  = dirname($stateDir);
    $candidatos[] = $privado . '/database/migrations';
    $candidatos[] = dirname($privado) . '/database/migrations';
} catch (Throwable $e) { /* sin config de storage privado; seguimos con el resto */ }
$candidatos[] = dirname(__DIR__) . '/database/migrations';
$candidatos[] = dirname(__DIR__, 2) . '/database/migrations';
$candidatos[] = __DIR__ . '/_migraciones';

$dir = null;
foreach ($candidatos as $c) {
    $c = rtrim(str_replace('\\', '/', $c), '/');
    if (is_dir($c) && glob($c . '/[0-9][0-9][0-9]_*.php')) { $dir = $c; break; }
}
if ($dir === null) {
    echo '<div class="card"><p class="bad">No encuentro la carpeta <code>database/migrations</code>.</p>'
       . '<p class="mut">Busqué en:</p><pre>' . h(implode("\n", $candidatos)) . '</pre>'
       . '<p class="mut">Pon la ruta correcta en <code>$MIGRACIONES_DIR</code>, o sube las '
       . 'migraciones a una carpeta <code>_migraciones</code> junto a este archivo.</p></div>'
       . '</div></body></html>';
    exit;
}

try {
    $pdo = cb_pdo();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
} catch (Throwable $e) {
    echo '<div class="card"><p class="bad">No hay conexión a la base de datos.</p>'
       . '<pre>' . h($e->getMessage()) . '</pre></div></div></body></html>';
    exit;
}

$timeType = $driver === 'mysql' ? 'DATETIME' : 'TEXT';
$tableOpts = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
$pdo->exec("CREATE TABLE IF NOT EXISTS cc_schema_migrations (version VARCHAR(120) NOT NULL PRIMARY KEY, applied_at $timeType NOT NULL)$tableOpts");
$aplicadas = array_flip($pdo->query('SELECT version FROM cc_schema_migrations')->fetchAll(PDO::FETCH_COLUMN));

$files = glob($dir . '/[0-9][0-9][0-9]_*.php') ?: [];
sort($files, SORT_STRING);

$filas = [];
$pendientes = [];
foreach ($files as $file) {
    if (substr($file, -9) === '.down.php') { continue; }
    $version = basename($file, '.php');
    if (isset($aplicadas[$version]))      { $estado = 'aplicada'; }
    elseif (in_array($version, $OMITIR, true)) { $estado = 'omitida'; }
    else { $estado = 'pendiente'; $pendientes[$version] = $file; }
    $filas[] = [$version, $estado];
}

echo '<div class="card"><table><tr><th>Migración</th><th>Estado</th></tr>';
foreach ($filas as [$v, $e]) {
    $cls = $e === 'aplicada' ? 'ok' : ($e === 'omitida' ? 'mut' : 'warn');
    echo '<tr><td><code>' . h($v) . '</code></td><td class="' . $cls . '">' . h($e) . '</td></tr>';
}
echo '</table>';
echo '<p class="mut">Motor: <code>' . h($driver) . '</code> · storage_mode: <code>'
   . h(cb_storage_mode()) . '</code><br>Carpeta: <code>' . h($dir) . '</code></p></div>';

// -------------------------------------------------------------------- aplicar
if ($accion !== 'aplicar') {
    if (!$pendientes) {
        echo '<div class="card"><p class="ok">No hay nada pendiente. La base ya está al día.</p></div>';
    } else {
        echo '<div class="card"><p>Se van a aplicar <strong>' . count($pendientes)
           . '</strong>: <code>' . implode('</code>, <code>', array_map('h', array_keys($pendientes))) . '</code></p>'
           . '<p class="mut">Todavía no se ha escrito nada.</p>'
           . '<form method="post"><input type="hidden" name="t" value="' . h($enviado) . '">'
           . '<input type="hidden" name="accion" value="aplicar">'
           . '<button type="submit">Aplicar ahora</button></form></div>';
    }
} else {
    echo '<div class="card"><h2 style="font-size:16px;margin:0 0 10px">Resultado</h2><pre>';
    $errores = false;
    foreach ($pendientes as $version => $file) {
        $migration = require $file;
        if (!is_callable($migration)) {
            echo 'ERROR  ' . h($version) . " — migración inválida\n"; $errores = true; break;
        }
        // MySQL hace commit implícito en DDL; en SQLite sí se puede envolver.
        $transaccional = $driver !== 'mysql';
        if ($transaccional) { $pdo->beginTransaction(); }
        try {
            $migration($pdo);
            $pdo->prepare('INSERT INTO cc_schema_migrations (version,applied_at) VALUES (?,?)')
                ->execute([$version, gmdate('Y-m-d H:i:s')]);
            if ($transaccional) { $pdo->commit(); }
            echo 'OK     ' . h($version) . "\n";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            echo 'ERROR  ' . h($version) . ' — ' . h($e->getMessage()) . "\n";
            $errores = true;
            break;
        }
    }
    echo '</pre>';
    echo $errores
        ? '<p class="bad">Se detuvo en el primer error. Las anteriores sí quedaron aplicadas.</p>'
        : '<p class="ok">Listo. Todas las migraciones pendientes quedaron aplicadas.</p>';
    echo '</div>';
}

echo '<div class="card"><p class="mut">Cuando termines, borra este archivo del servidor.</p>'
   . '<form method="post"><input type="hidden" name="t" value="' . h($enviado) . '">'
   . '<input type="hidden" name="accion" value="autodestruir">'
   . '<button class="danger" type="submit">Borrar este archivo del servidor</button></form></div>';

?>
</div></body></html>
