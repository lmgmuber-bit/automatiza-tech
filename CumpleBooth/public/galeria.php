<?php
/**
 * galeria.php — galería privada de la fiesta, por invitado, con impresión.
 *
 * El kiosco produce dos cosas por invitado: el "recuerdo" (diploma /
 * recuerdito que se lleva) y la "foto con personaje" (compuesta con el marco
 * de la temática). Las dos se suben por el mismo endpoint y se distinguen por
 * el prefijo del nombre de archivo (`diploma-` / `recuerdito-`), igual que ya
 * hace ver.php. El nombre del invitado también viaja en ese nombre de archivo
 * (`Tio-Pepe.png`), así que las fotos se agrupan contra la lista de invitados
 * de la fiesta normalizando los dos lados (minúsculas, sin tildes, sin signos).
 *
 * Vista principal: la LISTA DE INVITADOS; cada uno despliega su recuerdo y su
 * foto con personaje. Una segunda vista ("Todas") muestra las dos pestañas
 * planas. La selección es una sola y se conserva entre vistas y pestañas.
 * Con lo seleccionado: imprimir (una foto por hoja, N copias, pensado para la
 * Selphy/AirPrint desde la tablet) o descargar un ZIP.
 *
 * Acceso: PIN de 4 dígitos con hash, sesión corta y rate limit persistente.
 * Además una sesión de admin válida entra sin PIN: el organizador que imprime
 * en la fiesta ya está logueado en el backoffice. Se lee la sesión de admin sin
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
    return $base === '' || $base === 'foto' || $base === 'invitado' ? '' : $base;
}

/**
 * Clave de comparación de nombres: minúsculas, sin tildes ni signos. El kiosco
 * ya reemplazó todo lo que no es letra o número por "-", así que "Tío Pepe" en
 * la lista y "Tio-Pepe.png" en el archivo tienen que caer en la misma clave.
 */
function gallery_norm(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $map = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o'];
    $s = strtr($s, $map);
    $s = preg_replace('/[^\pL\pN]+/u', '-', $s);
    return trim($s, '-');
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
            $label = gallery_label($name);
            $photos[] = [
                'id' => $token,
                'name' => $name,
                'label' => $label,
                'norm' => gallery_norm($label),
                'kind' => gallery_kind($name),
                'path' => $path,
                'view' => $view,
                'thumb' => $view . '&download=inline&v=thumb',
                'full' => $view . '&download=inline',
            ];
        }
    }
    // Solo lectura para instalaciones anteriores; no vuelve a exponer /fotos directamente.
    $legacyDir = __DIR__ . '/fotos/' . $slug;
    foreach (is_dir($legacyDir) ? (glob($legacyDir . '/*.png') ?: []) : [] as $path) {
        $name = basename($path);
        $view = 'ver.php?p=' . rawurlencode($slug) . '&f=' . rawurlencode($name);
        $label = gallery_label($name);
        $photos[] = [
            'id' => 'legacy:' . $name,
            'name' => $name,
            'label' => $label,
            'norm' => gallery_norm($label),
            'kind' => gallery_kind($name),
            'path' => $path,
            'view' => $view,
            'thumb' => $view . '&download=inline&v=thumb',
            'full' => $view . '&download=inline',
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

// ── Agrupar por invitado ─────────────────────────────────────────────────
$esBabyShower = (string) ($party['event_type'] ?? 'child_birthday') === 'baby_shower';
$tituloRecuerdo = $esBabyShower ? 'Recuerdito' : 'Recuerdo';
$tituloRecuerdos = $esBabyShower ? 'Recuerditos' : 'Recuerdos';
$byNorm = [];
foreach ($photos as $ph) { $byNorm[$ph['norm']][] = $ph; }
$groups = [];
$assigned = [];
foreach ((array) ($party['invitados'] ?? []) as $guest) {
    $gname = trim((string) ($guest['name'] ?? ''));
    if ($gname === '') { continue; }
    $key = gallery_norm($gname);
    $mine = $byNorm[$key] ?? [];
    if ($mine) { $assigned[$key] = true; }
    $groups[] = ['name' => $gname, 'key' => $key, 'photos' => $mine];
}
// Fotos cuyo nombre no coincide con ningún invitado de la lista: se agrupan
// por el nombre que traen, y las que no traen ninguno van al final.
$leftovers = [];
foreach ($byNorm as $key => $list) {
    if (isset($assigned[$key])) { continue; }
    $leftovers[] = ['name' => $key === '' ? 'Sin nombre' : $list[0]['label'], 'key' => $key === '' ? '__sin-nombre' : $key, 'photos' => $list, 'extra' => true];
}
usort($leftovers, static fn ($a, $b) => ($a['key'] === '__sin-nombre') <=> ($b['key'] === '__sin-nombre') ?: strcmp($a['name'], $b['name']));
$groups = array_merge($groups, $leftovers);
$conFotos = count(array_filter($groups, static fn ($g) => count($g['photos']) > 0));
$recuerdos = array_values(array_filter($photos, static fn ($ph) => $ph['kind'] === 'recuerdo'));
$personajes = array_values(array_filter($photos, static fn ($ph) => $ph['kind'] === 'personaje'));

$themes = cb_load_themes();
$theme = $themes['themes'][$party['tema'] ?? ''] ?? [];
$colors = $theme['colors'] ?? [];
$accent = (string) ($colors['accent'] ?? '#7C3AED');
$dark1 = (string) ($colors['dark1'] ?? '#1a1a1a');
$dark2 = (string) ($colors['dark2'] ?? '#312e81');
$yellow = (string) ($colors['yellow'] ?? '#FBBF24');
$zipAll = 'galeria.php?p=' . rawurlencode($slug) . '&zip=1';

/** Tarjeta de foto (se usa igual en la vista por invitado y en la plana). */
function gallery_card(array $ph, string $tituloRecuerdo): void
{
    $label = $ph['label'] !== '' ? $ph['label'] : 'Invitado';
    ?>
    <figure class="foto" tabindex="0" role="checkbox" aria-checked="false" data-id="<?= gallery_h($ph['id']) ?>" data-full="<?= gallery_h($ph['full']) ?>" data-kind="<?= gallery_h($ph['kind']) ?>">
      <img src="<?= gallery_h($ph['thumb']) ?>" alt="<?= gallery_h($label) ?>" loading="lazy" decoding="async">
      <span class="check" aria-hidden="true">✓</span>
      <a class="ver" href="<?= gallery_h($ph['view']) ?>" target="_blank" rel="noopener" data-ver>Ver</a>
      <figcaption class="nombre"><?= $ph['kind'] === 'recuerdo' ? '🎓 ' . gallery_h($tituloRecuerdo) : '🎭 Con personaje' ?></figcaption>
    </figure>
    <?php
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="<?= gallery_h($accent) ?>"><title>Galería · <?= gallery_h($party['nombre'] ?? '') ?></title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;padding:20px 14px 130px;font-family:system-ui,sans-serif;background:linear-gradient(135deg,<?= gallery_h($dark1) ?>,<?= gallery_h($dark2) ?>);color:#fff}
.wrap{width:min(1180px,100%);margin:auto;text-align:center}
h1{color:<?= gallery_h($yellow) ?>;margin:.2rem 0 .6rem;font-size:clamp(1.3rem,4vw,2rem)}
.card{width:min(420px,100%);margin:2rem auto;padding:24px;border-radius:20px;background:#ffffff14}
.pin{width:100%;min-height:52px;border:2px solid #fff5;border-radius:12px;text-align:center;font-size:1.5rem;letter-spacing:.5em}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:48px;padding:12px 22px;border:0;border-radius:999px;background:<?= gallery_h($accent) ?>;color:#fff;font-weight:800;font-size:1rem;text-decoration:none;cursor:pointer;font-family:inherit}
.btn:disabled{opacity:.45;cursor:not-allowed}
.btn-ghost{background:#ffffff22;color:#fff}
.btn-mini{min-height:36px;padding:6px 14px;font-size:.85rem}
.btn:focus-visible,.pin:focus-visible,.tab:focus-visible,.foto:focus-visible,.buscar:focus-visible,summary:focus-visible{outline:3px solid <?= gallery_h($yellow) ?>;outline-offset:3px}
.error{color:#fecaca}
.muted{opacity:.8}
.toolbar{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin:.6rem 0 1rem}
.tabs{display:flex;justify-content:center;gap:8px;margin:0 0 1rem;flex-wrap:wrap}
.tab{min-height:48px;padding:10px 20px;border:2px solid #ffffff33;border-radius:999px;background:transparent;color:#fff;font-weight:800;font-size:1rem;cursor:pointer;font-family:inherit}
.tab[aria-selected="true"]{background:#fff;color:#222;border-color:#fff}
.tab b{margin-left:6px;padding:2px 9px;border-radius:999px;background:<?= gallery_h($accent) ?>;color:#fff;font-size:.85rem}
.panel[hidden]{display:none}
.buscar{width:min(420px,100%);min-height:48px;margin:0 auto 1rem;padding:10px 16px;border:2px solid #fff5;border-radius:999px;background:#ffffff14;color:#fff;font:inherit;font-size:1rem}
.buscar::placeholder{color:#fffa}
.invitados{display:grid;gap:12px;text-align:left}
.inv{border-radius:18px;background:#ffffff14;overflow:hidden}
.inv[hidden]{display:none}
.inv summary{list-style:none;display:flex;align-items:center;gap:12px;min-height:60px;padding:10px 16px;cursor:pointer;font-weight:800;font-size:1.05rem}
.inv summary::-webkit-details-marker{display:none}
.inv summary .flecha{margin-left:auto;transition:transform .15s;opacity:.8}
.inv[open] summary .flecha{transform:rotate(90deg)}
.inv .conteo{display:inline-flex;gap:6px;font-size:.8rem;font-weight:700;opacity:.9}
.inv .conteo span{padding:2px 9px;border-radius:999px;background:#0004}
.inv.vacio summary{opacity:.55;cursor:default}
.inv .cuerpo{padding:0 14px 14px}
.inv .acciones{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 10px}
.inv .vacio-txt{margin:0 0 10px;font-size:.9rem;opacity:.8}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}
.grid-mini{grid-template-columns:repeat(auto-fill,minmax(130px,1fr))}
.foto{position:relative;margin:0;padding:8px;border-radius:16px;background:#fff;color:#222;cursor:pointer;border:3px solid transparent;transition:transform .12s,border-color .12s;user-select:none;-webkit-user-select:none}
.foto:active{transform:scale(.98)}
.foto.sel{border-color:<?= gallery_h($accent) ?>;box-shadow:0 0 0 3px #ffffffaa}
.foto img{display:block;width:100%;aspect-ratio:9/16;object-fit:cover;border-radius:10px;background:#eee}
.foto .nombre{display:block;margin-top:6px;font-weight:800;font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.foto .check{position:absolute;top:14px;left:14px;width:30px;height:30px;border-radius:50%;background:#fffd;border:2px solid #0003;display:grid;place-items:center;font-size:18px;color:#fff}
.foto.sel .check{background:<?= gallery_h($accent) ?>;border-color:#fff}
.foto .ver{position:absolute;top:14px;right:14px;min-height:30px;padding:4px 10px;border-radius:999px;background:#000a;color:#fff;font-size:.8rem;font-weight:800;text-decoration:none}
.barra{position:fixed;left:0;right:0;bottom:0;z-index:20;padding:10px 12px calc(10px + env(safe-area-inset-bottom));background:#111c;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border-top:1px solid #ffffff22}
.barra-in{width:min(1180px,100%);margin:auto;display:flex;gap:10px;align-items:center;justify-content:center;flex-wrap:wrap}
.barra .cuenta{font-weight:800;min-width:9ch}
.opciones{display:inline-flex;align-items:center;gap:8px;font-size:.9rem}
.opciones select{min-height:36px;border-radius:10px;border:1px solid #fff5;background:#fff;color:#222;font:inherit;padding:0 8px}
.opciones input[type="checkbox"]{width:22px;height:22px}
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
<div class="tabs" role="tablist" aria-label="Vista">
  <button class="tab" role="tab" type="button" id="tab-invitados" aria-selected="true" aria-controls="panel-invitados" data-vista="invitados">👥 Por invitado<b><?= (int) $conFotos ?></b></button>
  <button class="tab" role="tab" type="button" id="tab-recuerdo" aria-selected="false" aria-controls="panel-recuerdo" data-vista="recuerdo">🎓 <?= gallery_h($tituloRecuerdos) ?><b><?= count($recuerdos) ?></b></button>
  <button class="tab" role="tab" type="button" id="tab-personaje" aria-selected="false" aria-controls="panel-personaje" data-vista="personaje">🎭 Con personaje<b><?= count($personajes) ?></b></button>
</div>
<p class="muted" style="margin:0 0 .8rem">Toca una foto para seleccionarla. Abajo puedes imprimir o descargar las elegidas.</p>

<section class="panel" id="panel-invitados" role="tabpanel" aria-labelledby="tab-invitados">
  <input class="buscar" id="buscar" type="search" placeholder="Buscar invitado…" autocomplete="off" aria-label="Buscar invitado">
  <div class="invitados" id="invitados">
    <?php foreach ($groups as $i => $g): ?>
      <?php
        $rec = array_values(array_filter($g['photos'], static fn ($p) => $p['kind'] === 'recuerdo'));
        $per = array_values(array_filter($g['photos'], static fn ($p) => $p['kind'] === 'personaje'));
        $tiene = count($g['photos']) > 0;
      ?>
      <details class="inv <?= $tiene ? '' : 'vacio' ?>" data-nombre="<?= gallery_h(mb_strtolower($g['name'], 'UTF-8')) ?>" <?= $tiene && $conFotos <= 8 ? 'open' : '' ?>>
        <summary <?= $tiene ? '' : 'tabindex="-1"' ?>>
          <span><?= gallery_h($g['name']) ?><?= !empty($g['extra']) ? ' <small class="muted">(no está en la lista)</small>' : '' ?></span>
          <span class="conteo">
            <?php if ($tiene): ?><span>🎓 <?= count($rec) ?></span><span>🎭 <?= count($per) ?></span><?php else: ?><span>sin fotos aún</span><?php endif; ?>
          </span>
          <?php if ($tiene): ?><span class="flecha" aria-hidden="true">▶</span><?php endif; ?>
        </summary>
        <?php if ($tiene): ?>
        <div class="cuerpo">
          <div class="acciones">
            <button class="btn btn-ghost btn-mini" type="button" data-elegir-inv>Elegir todo de <?= gallery_h($g['name']) ?></button>
            <?php if ($rec): ?><button class="btn btn-ghost btn-mini" type="button" data-elegir-inv="recuerdo">Solo <?= gallery_h(mb_strtolower($tituloRecuerdo, 'UTF-8')) ?></button><?php endif; ?>
            <?php if ($per): ?><button class="btn btn-ghost btn-mini" type="button" data-elegir-inv="personaje">Solo con personaje</button><?php endif; ?>
          </div>
          <div class="grid grid-mini">
            <?php foreach ($rec as $ph) { gallery_card($ph, $tituloRecuerdo); } ?>
            <?php foreach ($per as $ph) { gallery_card($ph, $tituloRecuerdo); } ?>
          </div>
        </div>
        <?php endif; ?>
      </details>
    <?php endforeach; ?>
  </div>
  <p class="muted" id="sin-resultados" hidden>Ningún invitado coincide con la búsqueda.</p>
</section>

<?php foreach (['recuerdo' => $recuerdos, 'personaje' => $personajes] as $kind => $lista): ?>
<section class="panel" id="panel-<?= $kind ?>" role="tabpanel" aria-labelledby="tab-<?= $kind ?>" hidden>
  <?php if (!$lista): ?><p class="muted">Todavía no hay <?= $kind === 'recuerdo' ? mb_strtolower($tituloRecuerdos, 'UTF-8') : 'fotos con personaje' ?>.</p><?php else: ?>
  <div class="acciones" style="display:flex;gap:8px;justify-content:center;margin:0 0 10px"><button class="btn btn-ghost btn-mini" type="button" data-elegir-vista="<?= $kind ?>">Elegir todas las de esta pestaña</button></div>
  <div class="grid">
    <?php foreach ($lista as $ph): ?>
    <figure class="foto" tabindex="0" role="checkbox" aria-checked="false" data-id="<?= gallery_h($ph['id']) ?>" data-full="<?= gallery_h($ph['full']) ?>" data-kind="<?= $kind ?>">
      <img src="<?= gallery_h($ph['thumb']) ?>" alt="<?= gallery_h($ph['label'] !== '' ? $ph['label'] : 'Invitado') ?>" loading="lazy" decoding="async">
      <span class="check" aria-hidden="true">✓</span>
      <a class="ver" href="<?= gallery_h($ph['view']) ?>" target="_blank" rel="noopener" data-ver>Ver</a>
      <figcaption class="nombre"><?= gallery_h($ph['label'] !== '' ? $ph['label'] : 'Invitado') ?></figcaption>
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
  var sel = {}; // id -> full (la misma foto aparece en dos vistas; la selección es por id)
  var fotos = Array.prototype.slice.call(document.querySelectorAll('.foto'));
  var cuenta = document.getElementById('cuenta');
  var btnImprimir = document.getElementById('imprimir');
  var btnDescargar = document.getElementById('descargar');
  var hoja = document.getElementById('hoja');
  var aviso = document.getElementById('aviso');

  function refresh() {
    var n = Object.keys(sel).length;
    cuenta.textContent = n === 1 ? '1 seleccionada' : n + ' seleccionadas';
    btnImprimir.disabled = n === 0;
    if (btnDescargar) { btnDescargar.disabled = n === 0; }
  }
  function setSel(id, full, on) {
    if (on) { sel[id] = full; } else { delete sel[id]; }
    fotos.forEach(function (fig) {
      if (fig.getAttribute('data-id') !== id) { return; }
      fig.classList.toggle('sel', on);
      fig.setAttribute('aria-checked', on ? 'true' : 'false');
    });
  }
  function toggle(fig) {
    var id = fig.getAttribute('data-id');
    setSel(id, fig.getAttribute('data-full'), !sel[id]);
    refresh();
  }
  fotos.forEach(function (fig) {
    fig.addEventListener('click', function (e) {
      if (e.target.closest('[data-ver]')) { return; } // "Ver" abre la foto, no selecciona
      toggle(fig);
    });
    fig.addEventListener('keydown', function (e) {
      if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); toggle(fig); }
    });
  });

  // Vistas: la selección se conserva al cambiar, a propósito — se puede elegir
  // el recuerdo de un invitado y la foto de otro e imprimir todo junto.
  Array.prototype.slice.call(document.querySelectorAll('.tab')).forEach(function (tab) {
    tab.addEventListener('click', function () {
      var vista = tab.getAttribute('data-vista');
      document.querySelectorAll('.tab').forEach(function (t) { t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
      document.querySelectorAll('.panel').forEach(function (p) { p.hidden = p.id !== 'panel-' + vista; });
    });
  });

  // "Elegir todo de <invitado>" (o solo un tipo).
  Array.prototype.slice.call(document.querySelectorAll('[data-elegir-inv]')).forEach(function (btn) {
    btn.addEventListener('click', function () {
      var kind = btn.getAttribute('data-elegir-inv');
      var cards = btn.closest('.inv').querySelectorAll('.foto');
      Array.prototype.forEach.call(cards, function (fig) {
        if (kind && fig.getAttribute('data-kind') !== kind) { return; }
        setSel(fig.getAttribute('data-id'), fig.getAttribute('data-full'), true);
      });
      refresh();
    });
  });
  Array.prototype.slice.call(document.querySelectorAll('[data-elegir-vista]')).forEach(function (btn) {
    btn.addEventListener('click', function () {
      var kind = btn.getAttribute('data-elegir-vista');
      fotos.forEach(function (fig) {
        if (fig.getAttribute('data-kind') === kind) { setSel(fig.getAttribute('data-id'), fig.getAttribute('data-full'), true); }
      });
      refresh();
    });
  });
  document.getElementById('sel-ninguna').addEventListener('click', function () {
    Object.keys(sel).forEach(function (id) { setSel(id, null, false); });
    refresh();
  });

  // Buscador de invitados (filtra las tarjetas por nombre, sin tildes).
  var buscar = document.getElementById('buscar');
  var sinRes = document.getElementById('sin-resultados');
  function norm(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  if (buscar) {
    buscar.addEventListener('input', function () {
      var q = norm(buscar.value.trim());
      var visibles = 0;
      Array.prototype.forEach.call(document.querySelectorAll('.inv'), function (inv) {
        var ok = q === '' || norm(inv.getAttribute('data-nombre') || '').indexOf(q) !== -1;
        inv.hidden = !ok;
        if (ok) { visibles++; if (q !== '' && !inv.classList.contains('vacio')) { inv.open = true; } }
      });
      sinRes.hidden = visibles > 0;
    });
  }

  if (btnDescargar) {
    btnDescargar.addEventListener('click', function () {
      var ids = Object.keys(sel);
      if (!ids.length) { return; }
      window.location.href = ZIP_BASE + ids.map(function (id) { return '&sel[]=' + encodeURIComponent(id); }).join('');
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
        img.src = sel[id];
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
