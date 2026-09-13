<?php
/**
 * admin/marca.php — los datos de contacto de CumpleClick que aparecen al
 * cierre del Álbum Recuerdo.
 *
 * Viven en data/marca.json y no compilados en el bundle del frontend porque el
 * hosting no tiene build: si estuvieran en el JSX habría que recompilar y
 * resubir assets cada vez que cambie un teléfono. Esta página edita ese JSON.
 *
 * No duplica el formulario de ingreso: la sesión `cc_admin` es la misma para
 * todo el admin, así que si no hay sesión manda a index.php y desde ahí se
 * vuelve.
 */
require __DIR__ . '/../lib.php';
require __DIR__ . '/config.php';

$adminSecureCookie = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_name('cc_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $adminSecureCookie, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function marca_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

// ── Sesión ───────────────────────────────────────────────────────────────────
$loggedIn = !empty($_SESSION['admin_logged']);
if ($loggedIn) {
    $idle = (int) cb_config('session_idle_seconds');
    $absolute = (int) cb_config('session_absolute_seconds');
    if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > $idle
        || time() - (int) ($_SESSION['admin_started'] ?? 0) > $absolute) {
        $_SESSION = [];
        session_destroy();
        $loggedIn = false;
    } else {
        $_SESSION['admin_seen'] = time();
    }
}
if (!$loggedIn) {
    header('Location: index.php');
    exit;
}

// ── Los campos ───────────────────────────────────────────────────────────────
// `url` marca los que terminan como href en la revista: esos se validan aparte.
$CAMPOS = [
    'nombre'        => ['etiqueta' => 'Nombre de la marca', 'max' => 40,  'ayuda' => 'Sale en grande al cierre.'],
    'lema'          => ['etiqueta' => 'Lema',               'max' => 80,  'ayuda' => 'Una línea, debajo del nombre.'],
    'invitacion'    => ['etiqueta' => 'Invitación',         'max' => 80,  'ayuda' => 'La frase que llama a contratar. Ej: “¿Lo quieres en tu próxima fiesta?”'],
    'web'           => ['etiqueta' => 'Sitio web',          'max' => 60,  'ayuda' => 'Como se lee. Ej: cumpleclick.cl'],
    'web_url'       => ['etiqueta' => 'Enlace del sitio',   'max' => 200, 'url' => true, 'ayuda' => 'Opcional. Con https://. Si lo dejas vacío el dato se muestra sin ser enlace.'],
    'instagram'     => ['etiqueta' => 'Instagram',          'max' => 40,  'ayuda' => 'Como se lee. Ej: @cumpleclick'],
    'instagram_url' => ['etiqueta' => 'Enlace de Instagram','max' => 200, 'url' => true, 'ayuda' => 'Opcional. Con https://.'],
    'whatsapp'      => ['etiqueta' => 'WhatsApp',           'max' => 40,  'ayuda' => 'Como se lee. Ej: +56 9 1234 5678'],
    'whatsapp_url'  => ['etiqueta' => 'Enlace de WhatsApp', 'max' => 200, 'url' => true, 'ayuda' => 'Opcional. Ej: https://wa.me/56912345678'],
    'correo'        => ['etiqueta' => 'Correo',             'max' => 60,  'ayuda' => 'Como se lee. Ej: contacto@cumpleclick.com'],
    'correo_url'    => ['etiqueta' => 'Enlace del correo',  'max' => 200, 'url' => true, 'ayuda' => 'Opcional. Ej: mailto:contacto@cumpleclick.com'],
];

$ruta = dirname(__DIR__) . '/data/marca.json';

function marca_leer(string $ruta, array $campos): array
{
    $valores = array_fill_keys(array_keys($campos), '');
    if (!is_file($ruta)) {
        return $valores;
    }
    $crudo = json_decode((string) @file_get_contents($ruta), true);
    if (!is_array($crudo)) {
        return $valores;
    }
    foreach ($campos as $clave => $_) {
        if (isset($crudo[$clave])) {
            $valores[$clave] = trim((string) $crudo[$clave]);
        }
    }
    return $valores;
}

$valores = marca_leer($ruta, $CAMPOS);
$errores = [];
$okMensaje = '';

// ── Guardar ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'guardar') {
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || $enviado === '' || !hash_equals($_SESSION['csrf'] ?? '', $enviado)) {
        $errores[] = 'Sesión expirada, vuelve a intentarlo.';
    } else {
        $nuevos = [];
        foreach ($CAMPOS as $clave => $def) {
            // Los saltos de línea y los caracteres de control no tienen nada que
            // hacer acá y ensucian el JSON: fuera antes de validar el largo.
            $valor = (string) ($_POST[$clave] ?? '');
            $valor = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $valor) ?? '');
            $valor = trim(preg_replace('/\s{2,}/u', ' ', $valor) ?? '');

            if ($valor !== '' && mb_strlen($valor) > $def['max']) {
                $errores[] = $def['etiqueta'] . ': máximo ' . $def['max'] . ' caracteres.';
                continue;
            }
            // Los _url terminan como href en una página pública. Sin esta
            // validación, pegar un `javascript:` acá lo convertiría en un enlace
            // ejecutable para cualquier invitado que abra la revista.
            if ($valor !== '' && !empty($def['url'])) {
                $esquema = strtolower((string) parse_url($valor, PHP_URL_SCHEME));
                if ($esquema !== 'http' && $esquema !== 'https') {
                    $errores[] = $def['etiqueta'] . ': tiene que empezar con https:// (o http://).';
                    continue;
                }
            }
            $nuevos[$clave] = $valor;
        }

        if ($errores === []) {
            $salida = [
                '_LEEME' => 'Editado desde el admin (admin/marca.php). Se puede tocar a mano, pero es mas facil desde ahi. Si un campo va vacio, esa linea no aparece en la revista.',
            ] + $nuevos;

            $json = json_encode($salida, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Escritura atómica: se escribe a un temporal en la misma carpeta y
            // recién ahí se renombra. Si el disco se llena o el proceso muere a
            // mitad, marca.json queda como estaba en vez de truncado, y un
            // JSON truncado deja la última página del álbum sin datos.
            $tmp = $ruta . '.tmp';
            $escrito = @file_put_contents($tmp, $json . "\n") !== false && @rename($tmp, $ruta);
            if ($escrito) {
                $okMensaje = 'Guardado. Recarga cualquier álbum publicado y vas a ver el cambio.';
                $valores = marca_leer($ruta, $CAMPOS);
            } else {
                @unlink($tmp);
                $errores[] = 'No se pudo escribir ' . basename($ruta) . '. Dale permiso de escritura a la carpeta '
                           . '<code>data/</code> por FTP (chmod 755, y 644 al archivo).';
            }
        }
    }
}

$escribible = is_file($ruta) ? is_writable($ruta) : is_writable(dirname($ruta));
$logo = '../brand/cumpleclick-mark.svg';

// ── Los carteles de la marca ────────────────────────────────────────────────
// Archivos estáticos en `brand/carteles/`: se diseñan una vez y se suben, no se generan en
// el servidor. El PDF es A4 a 300 ppp y es el que se manda a imprimir; el PNG pesa la cuarta
// parte y sirve para mandarlo por WhatsApp.
$CARTELES_DIR = __DIR__ . '/../brand/carteles/';
$CARTELES_URL = '../brand/carteles/';
$CARTELES = [
    ['base' => 'cartel-servicios-A4', 'titulo' => 'Qué hacemos y cuánto vale',
     'texto' => 'Las cuatro etapas del servicio, los tres planes con su precio de lanzamiento y los tres códigos QR.',
     'imprimible' => true],
    ['base' => 'cartel-tematicas-A4', 'titulo' => 'Elige tu mundo',
     'texto' => 'Las temáticas con su imagen real, separadas entre cumpleaños y baby shower.',
     'imprimible' => true],
    ['base' => 'aviso-qr-whatsapp-A4', 'titulo' => 'Aviso · WhatsApp',
     'texto' => 'Una hoja con un solo código grande que abre el chat. Sin el número escrito, a propósito.',
     'imprimible' => true],
    ['base' => 'aviso-qr-instagram-A4', 'titulo' => 'Aviso · Instagram',
     'texto' => 'Una hoja con un solo código grande que lleva al perfil.',
     'imprimible' => true],
    ['base' => 'instagram-avatar-1080x1080', 'titulo' => 'Instagram · foto de perfil',
     'texto' => 'Solo el globo, sin el nombre: a tamaño de avatar el nombre no se lee. Instagram la recorta en círculo.',
     'imprimible' => false],
    ['base' => 'instagram-1-marca-1080x1080', 'titulo' => 'Instagram · la marca',
     'texto' => 'Cuadrada, 1080 × 1080.', 'imprimible' => false],
    ['base' => 'instagram-2-como-funciona-1080x1350', 'titulo' => 'Instagram · cómo funciona',
     'texto' => 'Vertical, 1080 × 1350.', 'imprimible' => false],
    ['base' => 'instagram-3-planes-1080x1350', 'titulo' => 'Instagram · los planes',
     'texto' => 'Vertical, 1080 × 1350.', 'imprimible' => false],
    // El carrusel va en este orden: Instagram sube las imágenes en el orden en que se eligen.
    ['base' => 'instagram-carrusel-1-portada', 'titulo' => 'Carrusel 1 · portada',
     'texto' => 'La que se ve en el feed. Tiene que dar ganas de deslizar.', 'imprimible' => false],
    ['base' => 'instagram-carrusel-2-invitacion', 'titulo' => 'Carrusel 2 · la invitación',
     'texto' => 'Paso 1 de 4 del servicio.', 'imprimible' => false],
    ['base' => 'instagram-carrusel-3-cabina', 'titulo' => 'Carrusel 3 · la cabina',
     'texto' => 'Paso 2 de 4.', 'imprimible' => false],
    ['base' => 'instagram-carrusel-4-juegos', 'titulo' => 'Carrusel 4 · los juegos',
     'texto' => 'Paso 3 de 4.', 'imprimible' => false],
    ['base' => 'instagram-carrusel-5-album', 'titulo' => 'Carrusel 5 · el álbum',
     'texto' => 'Paso 4 de 4.', 'imprimible' => false],
    ['base' => 'instagram-carrusel-6-tematicas', 'titulo' => 'Carrusel 6 · las temáticas',
     'texto' => 'Con la imagen real de cada mundo.', 'imprimible' => false],
    ['base' => 'instagram-carrusel-7-cierre', 'titulo' => 'Carrusel 7 · precios y cierre',
     'texto' => 'Los tres planes y el llamado a WhatsApp.', 'imprimible' => false],
];

/** Tamaño legible de un archivo, o null si no está subido. */
function cartel_peso(string $ruta): ?string
{
    if (!is_file($ruta)) {
        return null;
    }
    $b = (int) filesize($ruta);
    return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB'
                         : number_format(max(1, (int) round($b / 1024)), 0, ',', '.') . ' kB';
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>CumpleClick Admin · Datos de la marca</title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>
/* Solo lo que el sistema del admin no cubre. Los campos usan la clase .field
   de _style.css.php, que es la que estiliza label, input y ayuda en todo el
   panel: la primera version invento su propia clase y los inputs quedaron con
   el borde gris del navegador, distintos al resto del admin. */
.marca-grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
.marca-grid .field small { color: var(--text-muted); }
.marca-previa { display: flex; flex-direction: column; align-items: center; gap: 10px;
  padding: 26px 18px; border-radius: 14px; background: #17324d; color: #fff; text-align: center; }
.marca-previa .invita { font-weight: 700; color: #ffd34e; margin: 0; }
.marca-previa .nombre { font-weight: 800; font-size: 1.3rem; letter-spacing: .04em; }
.marca-previa .lema { margin: 0; opacity: .85; font-size: .9rem; }
.marca-previa .datos { display: flex; flex-wrap: wrap; gap: 6px 18px; justify-content: center;
  font-weight: 700; font-size: .86rem; }
/* Los carteles. La miniatura manda: lo que se baja es una imagen, así que la tarjeta
   muestra la imagen y no un nombre de archivo. */
.carteles { display: grid; gap: 18px; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
.cartel { display: flex; flex-direction: column; gap: 10px; padding: 14px;
  border: 1px solid var(--border, #d7e2ee); border-radius: 14px; background: #fff; }
.cartel img { width: 100%; height: auto; display: block; border-radius: 8px;
  border: 1px solid #e6edf5; background: #f6f9fc; }
.cartel h3 { margin: 0; font-size: 1rem; }
.cartel p { margin: 0; font-size: .84rem; color: var(--text-muted); flex: 1; }
.cartel .acciones { display: flex; flex-wrap: wrap; gap: 8px; }
.cartel .acciones .btn { font-size: .82rem; padding: 7px 12px; }
.cartel .falta { font-size: .82rem; font-weight: 700; color: #b3261e; }
</style>
</head>
<body>
<main class="wrap">

  <p><a class="btn btn-ghost" href="index.php">Volver al panel</a></p>

  <h1>Datos de la marca</h1>
  <p class="muted">
    Es lo que aparece en la <b>última página</b> de cada Álbum Recuerdo. Se guarda
    en <code>data/marca.json</code> y el cambio se ve al recargar cualquier álbum
    publicado, sin subir nada más.
  </p>

  <?php if ($okMensaje !== ''): ?>
    <p class="alert alert-ok"><?= h($okMensaje) ?></p>
  <?php endif; ?>
  <?php foreach ($errores as $error): ?>
    <p class="alert alert-error"><?= $error /* ya viene con etiquetas propias controladas */ ?></p>
  <?php endforeach; ?>
  <?php if (!$escribible): ?>
    <p class="alert alert-error">
      El archivo <code>data/marca.json</code> no tiene permiso de escritura, así que
      el botón de guardar va a fallar. Arréglalo por FTP: la carpeta <code>data/</code>
      en 755 y el archivo en 644.
    </p>
  <?php endif; ?>

  <section class="card">
    <h2>Cómo se va a ver</h2>
    <div class="marca-previa">
      <?php if ($valores['invitacion'] !== ''): ?>
        <p class="invita"><?= h($valores['invitacion']) ?></p>
      <?php endif; ?>
      <span class="nombre"><?= h($valores['nombre'] !== '' ? $valores['nombre'] : 'CumpleClick') ?></span>
      <img src="<?= h($logo) ?>" alt="" width="48" height="48" style="display:block">
      <?php if ($valores['lema'] !== ''): ?>
        <p class="lema"><?= h($valores['lema']) ?></p>
      <?php endif; ?>
      <div class="datos">
        <?php foreach (['web', 'instagram', 'whatsapp', 'correo'] as $clave): ?>
          <?php if ($valores[$clave] !== ''): ?><span><?= h($valores[$clave]) ?></span><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <form method="post" action="marca.php">
    <input type="hidden" name="csrf" value="<?= h(marca_csrf_token()) ?>">
    <input type="hidden" name="action" value="guardar">

    <section class="card">
      <h2>Editar</h2>
      <div class="marca-grid">
        <?php foreach ($CAMPOS as $clave => $def): ?>
          <div class="field">
            <label for="c-<?= h($clave) ?>"><?= h($def['etiqueta']) ?></label>
            <?php /* type="text" incluso en los enlaces: `type="url"` no entra en
                     el selector de _style.css.php y ademas bloquea el envio del
                     lado del navegador con un mensaje generico. La validacion
                     que importa es la del servidor, que exige http o https. */ ?>
            <input
              type="text"
              id="c-<?= h($clave) ?>"
              name="<?= h($clave) ?>"
              value="<?= h($valores[$clave]) ?>"
              maxlength="<?= (int) $def['max'] ?>"
              autocomplete="off"
              <?= !empty($def['url']) ? 'inputmode="url" placeholder="https://"' : '' ?>>
            <small><?= h($def['ayuda']) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
      <p style="margin-top:18px">
        <button type="submit" class="btn btn-cta">Guardar</button>
      </p>
      <p class="muted" style="margin:6px 0 0">
        Deja vacío lo que todavía no tengas: esa línea simplemente no se muestra
        en la revista.
      </p>
    </section>
  </form>

  <section class="card">
    <h2>Carteles para imprimir y publicar</h2>
    <p class="muted">
      Llevan el logo y los colores de la marca, y los códigos QR van al WhatsApp, al
      Instagram y al sitio. El <b>PDF es A4 a 300 ppp</b>: es el que se manda a imprimir.
      El PNG pesa la cuarta parte y sirve para mandarlo por WhatsApp o subirlo a Instagram.
      Los precios son los de <a href="planes.php">Planes</a>; si cambian ahí, estos carteles
      hay que volver a generarlos.
    </p>
    <div class="carteles">
      <?php foreach ($CARTELES as $c): ?>
        <?php
          $previa = $CARTELES_DIR . $c['base'] . '-previa.jpg';
          $pdf    = $CARTELES_DIR . $c['base'] . '.pdf';
          $png    = $CARTELES_DIR . $c['base'] . ($c['imprimible'] ? '-web.png' : '.png');
          $pesoPdf = $c['imprimible'] ? cartel_peso($pdf) : null;
          $pesoPng = cartel_peso($png);
        ?>
        <article class="cartel">
          <?php if (is_file($previa)): ?>
            <img src="<?= h($CARTELES_URL . $c['base']) ?>-previa.jpg" alt="<?= h($c['titulo']) ?>" loading="lazy">
          <?php endif; ?>
          <h3><?= h($c['titulo']) ?></h3>
          <p><?= h($c['texto']) ?></p>
          <div class="acciones">
            <?php if ($c['imprimible'] && $pesoPdf !== null): ?>
              <a class="btn btn-cta" download href="<?= h($CARTELES_URL . $c['base']) ?>.pdf">PDF · <?= h($pesoPdf) ?></a>
            <?php endif; ?>
            <?php if ($pesoPng !== null): ?>
              <a class="btn btn-ghost" download href="<?= h($CARTELES_URL . $c['base']) ?><?= $c['imprimible'] ? '-web' : '' ?>.png">PNG · <?= h($pesoPng) ?></a>
            <?php endif; ?>
            <?php if ($pesoPng === null && ($pesoPdf === null)): ?>
              <span class="falta">Falta subir este archivo a <code>brand/carteles/</code>.</span>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

</main>
</body>
</html>
