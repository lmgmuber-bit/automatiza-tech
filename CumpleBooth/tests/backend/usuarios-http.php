<?php
/**
 * Recorrido de punta a punta del admin con usuarios (2026-09-13), sobre `php -S` y SQLite:
 *
 *  - sin sesión, cualquier página manda a login.php (con `volver`);
 *  - la clave maestra entra por maestro.php y ve todo;
 *  - el superadministrador crea una operadora; sin SMTP, la temporal aparece en pantalla;
 *  - la operadora entra, la obligan a cambiar la contraseña, y después ve SOLO su fiesta:
 *    sin Nueva fiesta, sin pestañas de super, 403 en finanzas/usuarios/temáticas/álbum ajeno;
 *  - puede apagar los juegos y agregar un invitado en su fiesta, y no en la otra;
 *  - la galería la deja pasar sin PIN en su fiesta y no en la otra;
 *  - deshabilitarla la saca en la petición siguiente.
 *
 * Nunca toca una base real: la SQLite vive en una carpeta temporal que se borra al final.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-usuarios-http-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
$puerto = 18490 + random_int(0, 200);
$base = 'http://127.0.0.1:' . $puerto;
$claveMaestra = 'clave-maestra-1234';

$env = array_merge(getenv(), [
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $tmp . '/test.sqlite',
    'CC_APP_HMAC_KEY' => str_repeat('c', 64), 'CC_PUBLIC_BASE_URL' => $base,
    'CC_PHOTO_DIR' => $tmp . '/photos', 'CC_STATE_DIR' => $tmp . '/state',
    'CUMPLECLICK_CONFIG_FILE' => $tmp . '/no-config.php', 'CC_AJUSTES_PATH' => $tmp . '/ajustes.json',
    'CC_ADMIN_PASSWORD_HASH' => password_hash($claveMaestra, PASSWORD_DEFAULT),
]);
foreach ($env as $k => $v) { putenv($k . '=' . $v); }
require $raiz . '/public/lib.php';
require_once __DIR__ . '/_migraciones.php';
$pdo = cb_pdo();
cb_test_migrar_todo($pdo);
foreach ([['samantha-hielo', 'Samantha', 'hielo'], ['luciano-spidey', 'Luciano', 'spidey']] as [$slug, $nombre, $tema]) {
    $pdo->prepare('INSERT INTO cc_parties(public_slug,admin_label,birthday_person_name,theme_slug,active,created_at,updated_at) VALUES(?,?,?,?,1,?,?)')
        ->execute([$slug, strtoupper($slug), $nombre, $tema, gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')]);
}

$servidor = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $puerto, '-t', $raiz . '/public'],
    [0 => ['pipe', 'r'], 1 => ['file', $tmp . '/servidor.log', 'a'], 2 => ['file', $tmp . '/servidor.log', 'a']], $pipes, $raiz . '/public', $env);
register_shutdown_function(static function () use ($servidor, $tmp): void {
    if (is_resource($servidor)) { proc_terminate($servidor); }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});
for ($i = 0; $i < 50; $i++) {
    $s = @fsockopen('127.0.0.1', $puerto, $errno, $errstr, 0.2);
    if ($s) { fclose($s); break; }
    usleep(100000);
}

$tests = 0;
function h_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); } }

/** Un "navegador": guarda cookies y hace peticiones sin seguir redirecciones. */
final class Navegador
{
    public array $cookies = [];
    public function __construct(private string $base) {}
    public function pedir(string $metodo, string $ruta, ?array $datos = null): array
    {
        $cabeceras = [];
        if ($this->cookies) {
            $pares = [];
            foreach ($this->cookies as $k => $v) { $pares[] = $k . '=' . $v; }
            $cabeceras[] = 'Cookie: ' . implode('; ', $pares);
        }
        $cuerpo = '';
        if ($datos !== null) {
            $cuerpo = http_build_query($datos);
            $cabeceras[] = 'Content-Type: application/x-www-form-urlencoded';
            $cabeceras[] = 'Content-Length: ' . strlen($cuerpo);
        }
        $ctx = stream_context_create(['http' => ['method' => $metodo, 'header' => implode("\r\n", $cabeceras),
            'content' => $cuerpo, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 15]]);
        $html = (string) @file_get_contents($this->base . $ruta, false, $ctx);
        $estado = 0;
        $ubicacion = '';
        foreach ($http_response_header ?? [] as $linea) {
            if (preg_match('/^HTTP\/\S+ (\d{3})/', $linea, $m)) { $estado = (int) $m[1]; }
            if (stripos($linea, 'Location:') === 0) { $ubicacion = trim(substr($linea, 9)); }
            if (stripos($linea, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $linea, $m)) {
                if ($m[2] === 'deleted' || $m[2] === '') { unset($this->cookies[$m[1]]); } else { $this->cookies[$m[1]] = $m[2]; }
            }
        }
        return ['estado' => $estado, 'ubicacion' => $ubicacion, 'html' => $html];
    }
    public function csrf(string $html): string
    {
        return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
    }
}

echo "Usuarios del admin por HTTP en $base\n";
$anon = new Navegador($base);
$r = $anon->pedir('GET', '/admin/index.php');
h_check($r['estado'] === 302 && $r['ubicacion'] === 'login.php', 'sin sesión: index manda a login (' . $r['estado'] . ' ' . $r['ubicacion'] . ')');
$r = $anon->pedir('GET', '/admin/finanzas.php?x=1');
h_check($r['estado'] === 302 && $r['ubicacion'] === 'login.php?volver=finanzas.php%3Fx%3D1', 'sin sesión: recuerda a dónde iba (' . $r['ubicacion'] . ')');
$r = $anon->pedir('GET', '/admin/carteles-api.php?p=samantha-hielo');
h_check($r['estado'] === 401 && str_contains($r['html'], 'no_autenticado'), 'la API de carteles contesta 401 JSON, no una redirección');
$r = $anon->pedir('GET', '/admin/login.php');
h_check($r['estado'] === 200 && str_contains($r['html'], 'name="correo"') && str_contains($r['html'], 'maestro.php'), 'login.php pide correo y enlaza la clave maestra');
$r = $anon->pedir('POST', '/admin/login.php', ['csrf' => $anon->csrf($r['html']), 'correo' => 'nadie@example.test', 'password' => 'x']);
h_check($r['estado'] === 200 && str_contains($r['html'], 'Correo o contraseña incorrectos'), 'clave mala: mensaje genérico');

// ── Clave maestra ───────────────────────────────────────────────────────────
$luis = new Navegador($base);
$r = $luis->pedir('GET', '/admin/maestro.php');
h_check($r['estado'] === 200 && str_contains($r['html'], 'name="password"'), 'maestro.php muestra la clave');
$r = $luis->pedir('POST', '/admin/maestro.php', ['csrf' => $luis->csrf($r['html']), 'password' => 'incorrecta']);
h_check($r['estado'] === 200 && str_contains($r['html'], 'Contraseña incorrecta'), 'maestra equivocada');
$r = $luis->pedir('POST', '/admin/maestro.php', ['csrf' => $luis->csrf($r['html']), 'password' => $claveMaestra]);
h_check($r['estado'] === 302 && $r['ubicacion'] === 'index.php', 'maestra correcta entra');
$r = $luis->pedir('GET', '/admin/index.php');
h_check($r['estado'] === 200 && str_contains($r['html'], 'usuarios.php') && str_contains($r['html'], 'Nueva fiesta')
    && str_contains($r['html'], 'finanzas.php') && str_contains($r['html'], 'luciano-spidey') && str_contains($r['html'], 'Clave maestra'), 'la maestra ve todo');
$csrfLuis = $luis->csrf($r['html']);
$r = $luis->pedir('GET', '/admin/finanzas.php');
h_check($r['estado'] === 200, 'la maestra abre Finanzas');

// ── Crear la operadora ──────────────────────────────────────────────────────
$r = $luis->pedir('GET', '/admin/usuarios.php');
h_check($r['estado'] === 200 && str_contains($r['html'], 'Nuevo usuario') && str_contains($r['html'], 'samantha-hielo'), 'Usuarios lista las fiestas');
$r = $luis->pedir('POST', '/admin/usuarios.php', ['csrf' => $csrfLuis, 'action' => 'crear', 'nombre' => 'Ana', 'correo' => 'ana@example.test',
    'rol' => 'operador', 'fiestas' => ['samantha-hielo'], 'modulos' => ['fotos', 'juegos', 'carteles', 'invitados']]);
h_check($r['estado'] === 302, 'usuario creado');
$r = $luis->pedir('GET', '/admin/usuarios.php');
h_check(str_contains($r['html'], 'El correo no salió') && preg_match('/<span class="temporal">([A-Za-z0-9-]+)<\/span>/', $r['html'], $m) === 1, 'sin SMTP, la temporal se muestra una vez');
$temporal = $m[1];
$r = $luis->pedir('GET', '/admin/usuarios.php');
h_check(!str_contains($r['html'], $temporal), 'y no vuelve a aparecer');
h_check(str_contains($r['html'], 'ana@example.test') && str_contains($r['html'], 'Fotos del kiosco, Juegos 3D, Carteles QR, Agregar invitados'), 'la tabla muestra sus módulos');
$anaId = (int) $pdo->query("SELECT id FROM cc_admin_users WHERE email = 'ana@example.test'")->fetchColumn();
h_check($anaId > 0, 'quedó en la base');

// ── La operadora entra y cambia la contraseña ───────────────────────────────
$ana = new Navegador($base);
$r = $ana->pedir('GET', '/admin/login.php?volver=album.php%3Fparty%3Dsamantha-hielo');
$r = $ana->pedir('POST', '/admin/login.php', ['csrf' => $ana->csrf($r['html']), 'correo' => 'ANA@example.test', 'password' => $temporal, 'volver' => 'album.php?party=samantha-hielo']);
h_check($r['estado'] === 302 && $r['ubicacion'] === 'perfil.php?cambiar=1', 'con temporal la manda a cambiarla (' . $r['ubicacion'] . ')');
$r = $ana->pedir('GET', '/admin/index.php');
h_check($r['estado'] === 302 && $r['ubicacion'] === 'perfil.php?cambiar=1', 'no puede ir a otra página hasta cambiarla');
$r = $ana->pedir('GET', '/admin/perfil.php?cambiar=1');
h_check($r['estado'] === 200 && str_contains($r['html'], 'Tu contraseña es temporal') && !str_contains($r['html'], 'class="tabs"'), 'perfil pide la nueva, sin pestañas');
$r = $ana->pedir('POST', '/admin/perfil.php', ['csrf' => $ana->csrf($r['html']), 'action' => 'clave', 'actual' => $temporal, 'nueva' => 'corta', 'repetida' => 'corta']);
h_check($r['estado'] === 200 && str_contains($r['html'], 'al menos 10'), 'contraseña corta rechazada');
$r = $ana->pedir('POST', '/admin/perfil.php', ['csrf' => $ana->csrf($r['html']), 'action' => 'clave', 'actual' => $temporal, 'nueva' => 'fiesta-de-samantha', 'repetida' => 'fiesta-de-samantha']);
h_check($r['estado'] === 302 && $r['ubicacion'] === 'index.php', 'contraseña cambiada');

// ── Lo que ve y lo que no ───────────────────────────────────────────────────
$r = $ana->pedir('GET', '/admin/index.php');
$html = $r['html'];
h_check($r['estado'] === 200 && str_contains($html, 'Mis fiestas') && str_contains($html, 'SAMANTHA-HIELO'), 've su fiesta');
h_check(!str_contains($html, 'luciano-spidey') && !str_contains($html, 'LUCIANO-SPIDEY'), 'no ve la otra');
h_check(!str_contains($html, 'Nueva fiesta') && !str_contains($html, 'action=editar') && !str_contains($html, 'value="eliminar"'), 'sin crear, editar ni eliminar');
h_check(!str_contains($html, 'finanzas.php') && !str_contains($html, 'usuarios.php') && !str_contains($html, 'view=temas') && !str_contains($html, 'comprobante.php') && !str_contains($html, 'marca.php'), 'sin pestañas ni botones de super');
h_check(str_contains($html, 'juegos 3D') && str_contains($html, 'invitados_agregar') && str_contains($html, 'carteles.html') && str_contains($html, 'Ana · Mi perfil'), 'con sus módulos y su perfil');
h_check(!str_contains($html, 'mensajes.php') && !str_contains($html, 'album.php?party=samantha-hielo">'), 'sin los módulos no marcados');
$csrfAna = $ana->csrf($html);
foreach (['/admin/finanzas.php', '/admin/usuarios.php', '/admin/index.php?view=temas', '/admin/index.php?action=nueva', '/admin/album.php?party=luciano-spidey',
          '/admin/mensajes.php?p=samantha-hielo', '/admin/leads.php', '/admin/ajustes.php', '/admin/invitations.php?party=samantha-hielo'] as $ruta) {
    $r = $ana->pedir('GET', $ruta);
    h_check($r['estado'] === 403 && str_contains($r['html'], 'No tienes acceso'), "403 en $ruta (" . $r['estado'] . ')');
}
$r = $ana->pedir('GET', '/admin/album.php?party=samantha-hielo');
h_check($r['estado'] === 200 && str_contains($r['html'], 'Fotos del kiosco') && str_contains($r['html'], 'section.card:not(#fotos-kiosco) { display: none'), 'álbum de su fiesta: solo las fotos del kiosco');
$r = $ana->pedir('GET', '/admin/carteles-api.php?p=luciano-spidey');
h_check($r['estado'] === 403 && str_contains($r['html'], '"ok":false'), 'API de carteles de otra fiesta: 403 JSON');
$r = $ana->pedir('GET', '/admin/carteles-api.php?p=samantha-hielo');
h_check($r['estado'] !== 403 && $r['estado'] !== 401 && $r['estado'] !== 302, 'API de carteles de su fiesta pasa el portero (' . $r['estado'] . ')');

// ── Acciones ────────────────────────────────────────────────────────────────
$r = $ana->pedir('POST', '/admin/index.php', ['csrf' => $csrfAna, 'action' => 'juegos3d', 'slug' => 'samantha-hielo', 'prender' => '0']);
h_check($r['estado'] === 302 && $r['ubicacion'] === 'index.php?ok=envio', 'apaga los juegos de su fiesta');
h_check((int) $pdo->query("SELECT games3d_enabled FROM cc_parties WHERE public_slug = 'samantha-hielo'")->fetchColumn() === 0, 'quedaron apagados en la base');
$r = $ana->pedir('POST', '/admin/index.php', ['csrf' => $csrfAna, 'action' => 'juegos3d', 'slug' => 'luciano-spidey', 'prender' => '0']);
h_check($r['estado'] === 200 && str_contains($r['html'], 'No tienes permiso para los juegos'), 'no toca los juegos de la otra');
h_check((int) $pdo->query("SELECT games3d_enabled FROM cc_parties WHERE public_slug = 'luciano-spidey'")->fetchColumn() === 1, 'la otra sigue prendida');
$r = $ana->pedir('POST', '/admin/index.php', ['csrf' => $csrfAna, 'action' => 'invitados_agregar', 'slug' => 'samantha-hielo', 'nombre' => 'Tomás', 'genero' => 'm']);
h_check($r['estado'] === 302, 'agrega un invitado');
$invitado = $pdo->query("SELECT g.name, g.gender FROM cc_guests g JOIN cc_parties p ON p.id = g.party_id WHERE p.public_slug = 'samantha-hielo'")->fetch();
h_check($invitado && $invitado['name'] === 'Tomás' && $invitado['gender'] === 'm', 'el invitado quedó en la lista');
$r = $ana->pedir('POST', '/admin/index.php', ['csrf' => $csrfAna, 'action' => 'invitados_agregar', 'slug' => 'luciano-spidey', 'nombre' => 'Intruso', 'genero' => 'm']);
h_check($r['estado'] === 200 && str_contains($r['html'], 'No tienes permiso para agregar invitados'), 'no agrega en la otra');
$r = $ana->pedir('POST', '/admin/index.php', ['csrf' => $csrfAna, 'action' => 'eliminar', 'slug' => 'samantha-hielo']);
h_check($r['estado'] === 200 && str_contains($r['html'], 'No tienes permiso para esa acción'), 'no elimina fiestas');
h_check((int) $pdo->query('SELECT COUNT(*) FROM cc_parties')->fetchColumn() === 2, 'las dos fiestas siguen');

// ── Galería: salta el PIN solo en su fiesta ─────────────────────────────────
$r = $ana->pedir('GET', '/galeria.php?p=samantha-hielo');
h_check($r['estado'] === 200, 'galería de su fiesta sin PIN (' . $r['estado'] . ')');
$r = $ana->pedir('GET', '/galeria.php?p=luciano-spidey');
h_check($r['estado'] === 404, 'la otra galería la trata como a un invitado (' . $r['estado'] . ')');
$r = $luis->pedir('GET', '/galeria.php?p=luciano-spidey');
h_check($r['estado'] === 200, 'la maestra sí entra a todas');

// ── Perfil ──────────────────────────────────────────────────────────────────
$r = $ana->pedir('GET', '/admin/perfil.php');
h_check($r['estado'] === 200 && str_contains($r['html'], 'readonly') && str_contains($r['html'], 'SAMANTHA-HIELO') && str_contains($r['html'], 'class="tabs"'), 'perfil con correo de solo lectura y sus fiestas');
$r = $ana->pedir('POST', '/admin/perfil.php', ['csrf' => $ana->csrf($r['html']), 'action' => 'nombre', 'nombre' => 'Ana María']);
h_check($r['estado'] === 302 && $pdo->query("SELECT nombre FROM cc_admin_users WHERE id = $anaId")->fetchColumn() === 'Ana María', 'cambia su nombre');

// ── Deshabilitar la saca en el acto ─────────────────────────────────────────
$r = $luis->pedir('POST', '/admin/usuarios.php', ['csrf' => $csrfLuis, 'action' => 'desactivar', 'id' => $anaId]);
h_check($r['estado'] === 302, 'la maestra la deshabilita');
$r = $ana->pedir('GET', '/admin/index.php');
h_check($r['estado'] === 302 && $r['ubicacion'] === 'login.php?motivo=deshabilitado', 'la sesión viva ya no sirve (' . $r['ubicacion'] . ')');
$r = $ana->pedir('GET', '/admin/login.php?motivo=deshabilitado');
$r = $ana->pedir('POST', '/admin/login.php', ['csrf' => $ana->csrf($r['html']), 'correo' => 'ana@example.test', 'password' => 'fiesta-de-samantha']);
h_check($r['estado'] === 200 && str_contains($r['html'], 'Tu acceso está deshabilitado'), 'y tampoco puede volver a entrar');

// ── Salir ───────────────────────────────────────────────────────────────────
$r = $luis->pedir('POST', '/admin/index.php', ['csrf' => $csrfLuis, 'action' => 'logout']);
h_check($r['estado'] === 302, 'salir');
$r = $luis->pedir('GET', '/admin/index.php');
h_check($r['estado'] === 302 && $r['ubicacion'] === 'login.php', 'después de salir, al login');

$log = (string) @file_get_contents($tmp . '/servidor.log');
$errores = array_values(array_filter(explode("\n", $log), static fn($l) => stripos($l, 'PHP ') !== false && (stripos($l, 'Warning') !== false || stripos($l, 'Fatal') !== false || stripos($l, 'Notice') !== false || stripos($l, 'Deprecated') !== false)));
h_check(!$errores, 'sin avisos ni errores de PHP en el servidor: ' . implode(' | ', array_slice($errores, 0, 3)));
echo "OK usuarios por HTTP: $tests comprobaciones.\n";
