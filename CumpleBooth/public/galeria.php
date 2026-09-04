<?php
/**
 * galeria.php — galería privada de la fiesta con impresión.
 *
 * Dos pestañas, porque el kiosco produce dos cosas distintas por invitado y
 * mezclarlas en una sola grilla obligaba a mirar una por una para saber cuál
 * era cuál: "Recuerdos" (el diploma / recuerdito que se lleva el invitado) y
 * "Fotos con personaje" (la foto compuesta con el marco de la temática). Las
 * dos se suben por el mismo endpoint y se distinguen por el prefijo del nombre
 * (`diploma-` / `recuerdito-`), igual que ya hace ver.php.
 *
 * La selección múltiple alimenta dos acciones: imprimir (una foto por hoja,
 * ajustada a la página, N copias, pensado para la Selphy/AirPrint desde la
 * tablet o el notebook) y descargar un ZIP con las elegidas.
 *
 * Acceso: PIN de 4 dígitos con hash, sesión corta y rate limit persistente,
 * como siempre. Además, una sesión de admin válida entra sin PIN: el
 * organizador que imprime en la fiesta ya está logueado en el backoffice y no
 * tiene por qué pedirle el PIN a los papás. Se lee la sesión de admin sin
 * crearla (misma técnica que ver-media.php) y recién después se abre la de
 * galería.
 */
require __DIR__ . '/lib.php';

$secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

$isAdmin = false;
if (!empty($_COOKIE['cc_admin'])) {
    session_name('cc_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
    $idle = (int) cb_config('session_idle_seconds');
    $absolute = (int) cb_config('session_absolute_seconds');
    $isAdmin = !empty($_SESSION['admin_logged'])
        && time() - (int) ($_SESSION['admin_seen'] ?? 0) <= $idle
        && time() - (int) ($_SESSION['admin_started'] ?? 0) <= $absolute;
    session_write_close();
    session_id('');
}

session_name('cc_gallery');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function gallery_h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function gallery_csrf(): string
{
    if (empty($_SESSION['gallery_csrf'])) { $_SESSION['gallery_csrf'] = bin2hex(random_bytes(16)); }
    return (string) $_SESSION['gallery_csrf'];
}
function gallery_csrf_valid(): bool
{
    $sent = (string) ($_POST['csrf'] ?? '');
    return $sent !== '' && hash_equals((string) ($_SESSION['gallery_csrf'] ?? ''), $sent);
}
function gallery_message(int $status, string $title, string $message): void
{
    http_response_code($status);
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . gallery_h($title) . '</title><body style="font-family:system-ui;text-align:center;padding:3rem;background:#1a1a1a;color:#fff"><h1>' . gallery_h($title) . '</h1><p>' . gallery_h($message) . '</p></body></html>';
    exit;
}

/** Recuerdo (diploma/recuerdito) o foto con personaje, por el nombre que mandó el kiosco. */
function gallery_kind(string $name): string
{
    return (strncmp($name, 'diploma-', 8) === 0 || strncmp($name, 'recuerdito-', 11) === 0) ? 'recuerdo' : 'personaje';
}

/** Nombre del invitado legible a partir del nombre de archivo del kiosco. */
function gallery_label(string $name): string
{
    $base = preg_replace('/\.png$/i', '', $name);
    $base = preg_replace('/^(diploma|recuerdito)-/', '', $base);
    $base = trim(str_replace(['-', '_'], ' ', $base));
    return $base === '' || $base === 'foto' || $base === 'invitado' ? 'Invitado' : $base;
}

$slug = (string) ($_GET['p'] ?? '');
if (!cb_valid_public_slug($slug)) { gallery_message(400, 'Galería no disponible', 'El enlace no es válido.'); }
$party = cb_load_party_raw($slug);
if ($party === null) { gallery_message(404, 'Galería no disponible', 'No encontramos esta fiesta.'); }
$pinEnabled = !empty($party['galeriaPinHash']) || !empty($party['galeriaPin']);
if (!$pinEnabled && !$isAdmin) { gallery_message(404, 'Galería no disponible', 'El organizador aún no habilitó la galería.'); }

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!gallery_csrf_valid()) {
        $error = 'La sesión expiró. Recarga la página.';
    } elseif (($_POST['action'] ?? '') === 'logout') {
        unset($_SESSION['gallery_auth'][$slug]);
        session_regenerate_id(true);
        header('Location: galeria.php?p=' . rawurlencode($slug));
        exit;
    } elseif (($_POST['action'] ?? '') === 'login') {
        $limit = cb_rate_limit('gallery-pin:' . $slug, cb_request_identity(), 5, 60, 60);
        if (!$limit['allowed']) {
            header('Retry-After: ' . max(1, (int) $limit['retry_after']));
            $error = 'Demasiados intentos. Espera un minuto.';
        } elseif (cb_verify_party_pin($party, (string) ($_POST['pin'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['gallery_auth'][$slug] = time() + 1800;
            header('Location: galeria.php?p=' . rawurlencode($slug));
            exit;
        } else {
            $error = 'PIN incorrecto.';
        }
    }
}
$authenticated = $isAdmin || (int) ($_SESSION['gallery_auth'][$slug] ?? 0) >= time();
if (!$authenticated) { unset($_SESSION['gallery_auth'][$slug]); }

$photos = [];
if ($authenticated) {
    foreach (cb_list_party_photos($slug) as $photo) {
        $path = cb_photo_absolute_path((string) ($photo['storage_key'] ?? ''));
        if ($path && is_file($path)) {
            $token = (string) ($photo['access_token'] ?? $photo['token'] ?? '');
            $name = (string) ($photo['original_name'] ?? 'foto.png');
            $view = 'ver.php?t=' . rawurlencode($token);
            $photos[] = [
                'id' => $token,
                'name' => $name,
                'label' => gallery_label($name),
                'kind' => gallery_kind($name),
                'path' => $path,
                'view' => $view,
                'thumb' => $view . '&download=inline&v=thumb',
                'full' => $view . '&download=inline',
                'created' => (string) ($photo['created_at'] ?? ''),
            ];
        }
    }
    // Solo lectura para instalaciones anteriores; no vuelve a exponer /fotos directamente.
    $legacyDir = __DIR__ . '/fotos/' . $slug;
    foreach (is_dir($legacyDir) ? (glob($legacyDir . '/*.png') ?: []) : [] as $path) {
        $name = basename($path);
        $view = 'ver.php?p=' . rawurlencode($slug) . '&f=' . rawurlencode($name);
        $photos[] = [
            'id' => 'legacy:' . $name,
            'name' => $name,
            'label' => gallery_label($name),
            'kind' => gallery_kind($name),
            'path' => $path,
            'view' => $view,
            'thumb' => $view . '&download=inline&v=thumb',
            'full' => $view . '&download=inline',
            'created' => '',
        ];
    }
}

// ZIP: todas, o solo las seleccionadas (`sel[]` trae los ids de la grilla).
if ($authenticated && isset($_GET['zip']) && class_exists('ZipArchive')) {
    $wanted = null;
    if (!empty($_GET['sel']) && is_array($_GET['sel'])) {
        $wanted = [];
        foreach ($_GET['sel'] as $id) { $wanted[(string) $id] = true; }
    }
    $chosen = $wanted === null ? $photos : array_values(array_filter($photos, static fn ($ph) => isset($wanted[$ph['id']])));
    if (!$chosen) { gallery_message(400, 'Nada que descargar', 'Selecciona al menos una foto.'); }
    $tmp = tempnam(sys_get_temp_dir(), 'cczip_');
    $zip = new ZipArchive();
    if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) { gallery_message(500, 'Error', 'No se pudo preparar el archivo.'); }
    $used = [];
    foreach ($chosen as $index => $photo) {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', basename($photo['name']));
        if (isset($used[$name])) { $name = ($index + 1) . '-' . $name; }
        $used[$name] = true;
        $zip->addFile($photo['path'], $name);
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="fotos-' . $slug . ($wanted === null ? '' : '-seleccion') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

$themes = cb_load_themes();
$theme = $themes['themes'][$party['tema'] ?? ''] ?? [];
$colors = $theme['colors'] ?? [];
$accent = (string) ($colors['accent'] ?? '#7C3AED');
$dark1 = (string) ($colors['dark1'] ?? '#1a1a1a');
$dark2 = (string) ($colors['dark2'] ?? '#312e81');
$yellow = (string) ($colors['yellow'] ?? '#FBBF24');
$recuerdos = array_values(array_filter($photos, static fn ($ph) => $ph['kind'] === 'recuerdo'));
$personajes = array_values(array_filter($photos, static fn ($ph) => $ph['kind'] === 'personaje'));
$esBabyShower = (string) ($party['event_type'] ?? 'child_birthday') === 'baby_shower';
$tituloRecuerdos = $esBabyShower ? 'Recuerditos' : 'Recuerdos';
$zipAll = 'galeria.php?p=' . rawurlencode($slug) . '&zip=1';
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="<?= gallery_h($accent) ?>"><title>Galería · <?= gallery_h($party['nombre'] ?? '') ?></title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;padding:20px 14px 120px;font-family:system-ui,sans-serif;background:linear-gradient(135deg,<?= gallery_h($dark1) ?>,<?= gallery_h($dark2) ?>);color:#fff}
.wrap{width:min(1180px,100%);margin:auto;text-align:center}
h1{color:<?= gallery_h($yellow) ?>;margin:.2rem 0 .6rem;font-size:clamp(1.3rem,4vw,2rem)}
.card{width:min(420px,100%);margin:2rem auto;padding:24px;border-radius:20px;background:#ffffff14}
.pin{width:100%;min-height:52px;border:2px solid #fff5;border-radius:12px;text-align:center;font-size:1.5rem;letter-spacing:.5em}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:48px;padding:12px 22px;border:0;border-radius:999px;background:<?= gallery_h($accent) ?>;color:#fff;font-weight:800;font-size:1rem;text-decoration:none;cursor:pointer;font-family:inherit}
.btn:disabled{opacity:.45;cursor:not-allowed}
.btn-ghost{background:#ffffff22;color:#fff}
.btn:focus-visible,.pin:focus-visible,.tab:focus-visible,.foto:focus-visible{outline:3px solid <?= gallery_h($yellow) ?>;outline-offset:3px}
.error{color:#fecaca}
.muted{opacity:.8}
.toolbar{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin:.6rem 0 1rem}
.tabs{display:flex;justify-content:center;gap:8px;margin:0 0 1rem;flex-wrap:wrap}
.tab{min-height:48px;padding:10px 20px;border:2px solid #ffffff33;border-radius:999px;background:transparent;color:#fff;font-weight:800;font-size:1rem;cursor:pointer;font-family:inherit}
.tab[aria-selected="true"]{background:#fff;color:#222;border-color:#fff}
.tab b{margin-left:6px;padding:2px 9px;border-radius:999px;background:<?= gallery_h($accent) ?>;color:#fff;font-size:.85rem}
.tab[aria-selected="true"] b{background:<?= gallery_h($accent) ?>}
.panel[hidden]{display:none}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px}
.foto{position:relative;margin:0;padding:8px;border-radius:16px;background:#fff;color:#222;cursor:pointer;border:3px solid transparent;transition:transform .12s,border-color .12s;user-select:none;-webkit-user-select:none}
.foto:active{transform:scale(.98)}
.foto.sel{border-color:<?= gallery_h($accent) ?>;box-shadow:0 0 0 3px #ffffffaa}
.foto img{display:block;width:100%;aspect-ratio:9/16;object-fit:cover;border-radius:10px;background:#eee}
.foto .nombre{display:block;margin-top:6px;font-weight:800;font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.foto .check{position:absolute;top:14px;left:14px;width:30px;height:30px;border-radius:50%;background:#fffd;border:2px solid #0003;display:grid;place-items:center;font-size:18px;color:#fff}
.foto.sel .check{background:<?= gallery_h($accent) ?>;border-color:#fff}
.foto .ver{position:absolute;top:14px;right:14px;min-height:30px;padding:4px 10px;border-radius:999px;background:#000a;color:#fff;font-size:.8rem;font-weight:800;text-decoration:none}
.barra{position:fixed;left:0;right:0;bottom:0;z-index:20;padding:10px 12px calc(10px + env(safe-area-inset-bottom));background:#111c;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border-top:1px solid #ffffff22}
.barra-in{width:min(1180px,100%);margin:auto;display:flex;gap:10px;align-items:center;justify-content:center;flex-wrap:wrap}
.barra .cuenta{font-weight:800;min-width:9ch}
.opciones{display:inline-flex;align-items:center;gap:8px;font-size:.9rem}
.opciones select,.opciones input[type="checkbox"]{min-height:36px;border-radius:10px;border:1px solid #fff5;background:#fff;color:#222;font:inherit;padding:0 8px}
.opciones input[type="checkbox"]{width:22px;height:22px;min-height:0}
.pie{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:2.5rem;font-weight:800;opacity:.9}
#hoja{display:none}
#aviso{position:fixed;inset:0;z-index:30;display:none;place-items:center;background:#000c;color:#fff;font-weight:800;font-size:1.2rem}
#aviso.on{display:grid}
@media print{
  @page{margin:0}
  body{background:#fff;color:#000;padding:0;min-height:0}
  .wrap,.barra,#aviso{display:none !important}
  #hoja{display:block}
  .pagina{width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;page-break-after:always;break-after:page;overflow:hidden;background:#fff}
  .pagina:last-child{page-break-after:auto;break-after:auto}
  .pagina img{width:100%;height:100%;object-fit:contain}
  #hoja.llenar .pagina img{object-fit:cover}
}
</style></head><body><main class="wrap">
<h1>Galería de la fiesta de <?= gallery_h($party['nombre'] ?? '') ?></h1>
<?php if (!$authenticated): ?>
<section class="card"><p>Ingresa el PIN de 4 dígitos entregado por el organizador.</p><?php if ($error): ?><p class="error"><?= gallery_h($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= gallery_h(gallery_csrf()) ?>"><input type="hidden" name="action" value="login"><input class="pin" type="password" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="one-time-code"><p><button class="btn" type="submit">Ver fotos</button></p></form></section>
<?php else: ?>
<div class="toolbar">
  <?php if ($photos && class_exists('ZipArchive')): ?><a class="btn btn-ghost" href="<?= gallery_h($zipAll) ?>">Descargar todas (<?= count($photos) ?>)</a><?php endif; ?>
  <?php if (!$isAdmin): ?><form method="post"><input type="hidden" name="csrf" value="<?= gallery_h(gallery_csrf()) ?>"><input type="hidden" name="action" value="logout"><button class="btn btn-ghost" type="submit">Cerrar galería</button></form>
  <?php else: ?><a class="btn btn-ghost" href="admin/index.php">Volver al admin</a><?php endif; ?>
</div>
<?php if (!$photos): ?><p class="muted">Todavía no hay fotos. Cuando el kiosco suba la primera, aparece acá.</p><?php else: ?>
<div class="tabs" role="tablist" aria-label="Tipo de foto">
  <button class="tab" role="tab" type="button" id="tab-recuerdo" aria-selected="true" aria-controls="panel-recuerdo" data-kind="recuerdo">🎓 <?= gallery_h($tituloRecuerdos) ?><b><?= count($recuerdos) ?></b></button>
  <button class="tab" role="tab" type="button" id="tab-personaje" aria-selected="false" aria-controls="panel-personaje" data-kind="personaje">🎭 Fotos con personaje<b><?= count($personajes) ?></b></button>
</div>
<p class="muted" style="margin:0 0 .8rem">Toca una foto para seleccionarla. Abajo puedes imprimir o descargar las elegidas.</p>
<?php foreach (['recuerdo' => $recuerdos, 'personaje' => $personajes] as $kind => $lista): ?>
<section class="panel" id="panel-<?= $kind ?>" role="tabpanel" aria-labelledby="tab-<?= $kind ?>" <?= $kind === 'recuerdo' ? '' : 'hidden' ?>>
  <?php if (!$lista): ?><p class="muted">Todavía no hay <?= $kind === 'recuerdo' ? strtolower($tituloRecuerdos) : 'fotos con personaje' ?>.</p><?php else: ?>
  <div class="grid">
    <?php foreach ($lista as $ph): ?>
    <figure class="foto" tabindex="0" role="checkbox" aria-checked="false" data-id="<?= gallery_h($ph['id']) ?>" data-full="<?= gallery_h($ph['full']) ?>" data-kind="<?= $kind ?>">
      <img src="<?= gallery_h($ph['thumb']) ?>" alt="<?= gallery_h($ph['label']) ?>" loading="lazy" decoding="async">
      <span class="check" aria-hidden="true">✓</span>
      <a class="ver" href="<?= gallery_h($ph['view']) ?>" target="_blank" rel="noopener" data-ver>Ver</a>
      <figcaption class="nombre"><?= gallery_h($ph['label']) ?></figcaption>
    </figure>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
<p class="pie"><img src="brand/cumpleclick-mark.svg" alt="" width="24" height="24">CumpleClick</p>
</main>
<?php if ($authenticated && $photos): ?>
<div class="barra" id="barra">
  <div class="barra-in">
    <span class="cuenta" id="cuenta">0 seleccionadas</span>
    <button class="btn btn-ghost" type="button" id="sel-todas">Todas de esta pestaña</button>
    <button class="btn btn-ghost" type="button" id="sel-ninguna">Ninguna</button>
    <label class="opciones">Copias <select id="copias"><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option></select></label>
    <label class="opciones"><input type="checkbox" id="llenar"> Llenar la hoja (recorta un poco)</label>
    <button class="btn" type="button" id="imprimir" disabled>🖨️ Imprimir</button>
    <?php if (class_exists('ZipArchive')): ?><button class="btn btn-ghost" type="button" id="descargar" disabled>⬇️ Descargar ZIP</button><?php endif; ?>
  </div>
</div>
<div id="hoja" aria-hidden="true"></div>
<div id="aviso">Preparando la impresión…</div>
<script>
(function () {
  'use strict';
  var ZIP_BASE = <?= json_encode($zipAll, JSON_UNESCAPED_SLASHES) ?>;
  var sel = {}; // id -> {full, kind}
  var fotos = Array.prototype.slice.call(document.querySelectorAll('.foto'));
  var cuenta = document.getElementById('cuenta');
  var btnImprimir = document.getElementById('imprimir');
  var btnDescargar = document.getElementById('descargar');
  var hoja = document.getElementById('hoja');
  var aviso = document.getElementById('aviso');
  var activa = 'recuerdo';

  function refresh() {
    var n = Object.keys(sel).length;
    cuenta.textContent = n === 1 ? '1 seleccionada' : n + ' seleccionadas';
    btnImprimir.disabled = n === 0;
    if (btnDescargar) { btnDescargar.disabled = n === 0; }
  }
  function setSel(fig, on) {
    var id = fig.getAttribute('data-id');
    if (on) { sel[id] = { full: fig.getAttribute('data-full'), kind: fig.getAttribute('data-kind') }; }
    else { delete sel[id]; }
    fig.classList.toggle('sel', on);
    fig.setAttribute('aria-checked', on ? 'true' : 'false');
  }
  fotos.forEach(function (fig) {
    fig.addEventListener('click', function (e) {
      if (e.target.closest('[data-ver]')) { return; } // "Ver" abre la foto, no selecciona
      setSel(fig, !fig.classList.contains('sel'));
      refresh();
    });
    fig.addEventListener('keydown', function (e) {
      if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); setSel(fig, !fig.classList.contains('sel')); refresh(); }
    });
  });

  // Pestañas: la selección se conserva al cambiar de pestaña, a propósito —
  // el organizador puede elegir dos recuerdos y una foto e imprimir los tres.
  Array.prototype.slice.call(document.querySelectorAll('.tab')).forEach(function (tab) {
    tab.addEventListener('click', function () {
      activa = tab.getAttribute('data-kind');
      document.querySelectorAll('.tab').forEach(function (t) { t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
      document.querySelectorAll('.panel').forEach(function (p) { p.hidden = p.id !== 'panel-' + activa; });
    });
  });

  document.getElementById('sel-todas').addEventListener('click', function () {
    fotos.forEach(function (fig) { if (fig.getAttribute('data-kind') === activa) { setSel(fig, true); } });
    refresh();
  });
  document.getElementById('sel-ninguna').addEventListener('click', function () {
    fotos.forEach(function (fig) { setSel(fig, false); });
    refresh();
  });

  if (btnDescargar) {
    btnDescargar.addEventListener('click', function () {
      var ids = Object.keys(sel);
      if (!ids.length) { return; }
      var url = ZIP_BASE + ids.map(function (id) { return '&sel[]=' + encodeURIComponent(id); }).join('');
      window.location.href = url;
    });
  }

  /**
   * Impresión: se arma una hoja oculta con una página por foto (× copias) a
   * tamaño completo, se espera a que TODAS carguen y recién ahí se llama a
   * window.print(). Sin la espera, la primera hoja salía en blanco en la
   * tablet porque el diálogo se abre antes de que la imagen exista.
   */
  btnImprimir.addEventListener('click', function () {
    var ids = Object.keys(sel);
    if (!ids.length) { return; }
    var copias = parseInt(document.getElementById('copias').value, 10) || 1;
    hoja.classList.toggle('llenar', document.getElementById('llenar').checked);
    hoja.innerHTML = '';
    var esperas = [];
    ids.forEach(function (id) {
      for (var c = 0; c < copias; c++) {
        var pagina = document.createElement('div');
        pagina.className = 'pagina';
        var img = document.createElement('img');
        img.alt = '';
        esperas.push(new Promise(function (resolve) {
          img.onload = resolve;
          img.onerror = resolve;
          setTimeout(resolve, 8000);
        }));
        img.src = sel[id].full;
        pagina.appendChild(img);
        hoja.appendChild(pagina);
      }
    });
    aviso.classList.add('on');
    Promise.all(esperas).then(function () {
      aviso.classList.remove('on');
      window.print();
    });
  });
  window.addEventListener('afterprint', function () { hoja.innerHTML = ''; });

  refresh();
})();
</script>
<?php endif; ?>
</body></html>
