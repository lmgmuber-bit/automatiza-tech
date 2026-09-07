<?php
/**
 * admin/comprobante.php — el comprobante de pago del servicio, en PDF.
 *
 * Cuando alguien contrata, pide algo que respalde lo que pagó. Esta pantalla arma ese
 * documento con lo que ya está cargado en la ficha de la fiesta (precio, descuento, abono y
 * los contactos de quien contrató), lo deja ver, lo manda por correo con el PDF adjunto y
 * entrega el texto para WhatsApp con el enlace.
 *
 * El PDF NO se guarda en disco: se arma en el momento cada vez, así que siempre refleja lo
 * que dice la ficha hoy. Y NO es una boleta del SII —el propio documento lo aclara—; esa
 * integración es una etapa aparte.
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
function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function admin_csrf_check(): bool
{
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && $t !== '' && hash_equals($_SESSION['csrf'] ?? '', $t);
}
function admin_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(admin_csrf_token()) . '">';
}

/** Los mismos iconos de línea del resto del admin (index.php, leads.php). */
function admin_icon(string $name): string
{
    $paths = [
        'party' => '<path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01M22 8h.01M15 2h.01M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12v0c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11v0c-.11.7-.72 1.22-1.43 1.22H16"/><path d="M11 2 9.89 4.11a2.9 2.9 0 0 0 .5 3.4l.5.5a2.9 2.9 0 0 1 .5 3.4L9 14"/>',
        'palette' => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
        'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
    ];
    $d = $paths[$name] ?? '';
    if ($d === '') { return ''; }
    return '<svg class="icon" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

/** Glifo oficial de WhatsApp (relleno). Va aparte de admin_icon() porque ese dibuja
 *  iconos de línea con `stroke`, y el logo de una marca no se dibuja a mano alzada. */
function icono_whatsapp(int $tam = 18): string
{
    return '<svg viewBox="0 0 24 24" width="' . $tam . '" height="' . $tam . '" fill="currentColor" aria-hidden="true" focusable="false">'
        . '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>'
        . '</svg>';
}

// ================== SESIÓN (mismo contrato que index.php y leads.php) ==================
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    if (admin_csrf_check()) {
        $_SESSION = [];
        session_destroy();
        header('Location: comprobante.php');
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!admin_csrf_check()) {
        $loginError = 'Sesión expirada, intenta de nuevo.';
    } else {
        $pass = (string) ($_POST['password'] ?? '');
        $loginLimit = cb_rate_limit('admin-login', cb_request_identity(), 5, 900, 900);
        if (!$loginLimit['allowed']) {
            $loginError = 'Demasiados intentos. Intenta nuevamente más tarde.';
        } elseif (!defined('ADMIN_PASSWORD_HASH') || ADMIN_PASSWORD_HASH === '') {
            $loginError = 'El administrador aún no está configurado. Ejecuta scripts/bootstrap.php.';
        } elseif (password_verify($pass, ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_started'] = time();
            $_SESSION['admin_seen'] = time();
            header('Location: comprobante.php');
            exit;
        } else {
            $loginError = 'Contraseña incorrecta.';
        }
    }
}

$loggedIn = !empty($_SESSION['admin_logged']);
if ($loggedIn) {
    $idle = (int) cb_config('session_idle_seconds');
    $absolute = (int) cb_config('session_absolute_seconds');
    if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > $idle || time() - (int) ($_SESSION['admin_started'] ?? 0) > $absolute) {
        $_SESSION = [];
        session_destroy();
        $loggedIn = false;
        $loginError = 'La sesión expiró. Ingresa nuevamente.';
    } else {
        $_SESSION['admin_seen'] = time();
    }
}

if (!$loggedIn) {
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleBooth Admin · Ingresar</title>
<style><?php require __DIR__ . '/_style.css.php'; ?></style>
</head>
<body class="login-body">
  <form class="login-card" method="post" action="comprobante.php">
    <h1>CumpleBooth</h1>
    <p class="muted">Comprobante de pago</p>
    <?php if ($loginError !== ''): ?><p class="alert alert-error"><?= h($loginError) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>">
    <input type="hidden" name="action" value="login">
    <label for="password">Contraseña</label>
    <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
    <button class="btn btn-primary" type="submit">Ingresar</button>
  </form>
</body>
</html><?php
    exit;
}


// ================== DATOS ==================
require_once __DIR__ . '/../lib.comprobante.php';
require_once __DIR__ . '/../lib.mail.php';
require_once __DIR__ . '/../lib.mail-templates.php';

$data = cb_load_parties();
$fiestas = [];
foreach (($data['parties'] ?? []) as $slug => $p) {
    $fiestas[$slug] = [
        'slug' => $slug,
        'nombre' => (string) ($p['nombre'] ?? $slug),
        'fecha' => (string) ($p['fecha'] ?? ''),
    ];
}
uasort($fiestas, static fn($a, $b) => strcmp($b['fecha'], $a['fecha']) ?: strcmp($a['nombre'], $b['nombre']));

$slugActual = isset($_GET['p']) && is_string($_GET['p']) && isset($fiestas[$_GET['p']])
    ? $_GET['p']
    : (string) (array_key_first($fiestas) ?? '');

$aviso = '';
$avisoError = '';

// ── Enviar el comprobante por correo ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enviar') {
    if (!admin_csrf_check()) {
        $avisoError = 'Sesión expirada, vuelve a intentarlo.';
    } else {
        $slugEnvio = (string) ($_POST['slug'] ?? '');
        $destinos = array_values(array_filter((array) ($_POST['destinos'] ?? []), 'is_string'));
        $datos = isset($fiestas[$slugEnvio]) ? cb_comprobante_datos($slugEnvio) : null;
        if ($datos === null) {
            $avisoError = 'Esta fiesta todavía no tiene precio cargado.';
        } elseif (!$destinos) {
            $avisoError = 'Elige al menos un correo.';
        } elseif (!cc_mail_enabled()) {
            $avisoError = 'El envío de correo no está configurado en el servidor.';
        } else {
            // Los correos válidos son los de la ficha y nada más: el formulario manda solo
            // marcas, pero un POST armado a mano podría traer cualquier dirección.
            $permitidos = cb_party_contact_emails($slugEnvio);
            $pdf = cb_comprobante_pdf($datos);
            $archivo = cb_comprobante_nombre_archivo($datos);
            $url = cb_comprobante_url($slugEnvio);
            $correo = cb_comprobante_correo($datos, $url);
            $ok = [];
            $fallaron = [];
            foreach ($destinos as $destino) {
                if (!in_array($destino, $permitidos, true)) { continue; }
                $envio = cc_mail_send([
                    'to' => $destino,
                    'subject' => $correo['subject'],
                    'text' => $correo['text'],
                    'html' => $correo['html'],
                    'attachments' => [[
                        'filename' => $archivo,
                        'type' => 'application/pdf',
                        'data' => $pdf,
                    ]],
                ]);
                if (!empty($envio['ok'])) { $ok[] = $destino; } else { $fallaron[] = $destino; }
            }
            if ($ok) {
                $aviso = 'Comprobante enviado a ' . implode(', ', $ok) . '.';
            }
            if ($fallaron) {
                $avisoError = 'No se pudo enviar a ' . implode(', ', $fallaron) . '. Revisa el correo del servidor.';
            }
        }
        $slugActual = isset($fiestas[$slugEnvio]) ? $slugEnvio : $slugActual;
    }
}

$datos = $slugActual !== '' ? cb_comprobante_datos($slugActual) : null;
$contactos = $slugActual !== '' ? cb_party_contacts($slugActual) : [];
$urlComprobante = $datos !== null ? cb_comprobante_url($slugActual) : '';
$textoWhatsapp = $datos !== null ? cb_comprobante_texto_whatsapp($datos, $urlComprobante) : '';
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Comprobante · CumpleBooth</title>
<style><?php require __DIR__ . '/_style.css.php'; ?></style>
<style>
  /* Solo lo que no existe en _style.css.php. */
  .cmp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
  @media (max-width: 720px) { .cmp-grid { grid-template-columns: 1fr; } }
  .cmp-monto { display: flex; justify-content: space-between; gap: 16px; padding: 7px 0; }
  .cmp-monto + .cmp-monto { border-top: 1px solid var(--linea, #E4DDF0); }
  .cmp-monto strong { font-size: 1.05rem; }
  .cmp-total { font-size: 1.25rem; }
  .cmp-texto {
    width: 100%; min-height: 150px; font: inherit; font-size: .92rem; line-height: 1.5;
    padding: 12px 14px; border: 1.5px solid #DED9E6; border-radius: 12px; background: #fff;
    resize: vertical;
  }
  .cmp-visor { width: 100%; height: 520px; border: 1.5px solid #DED9E6; border-radius: 12px; background: #fff; }
  .cmp-destino { display: flex; align-items: center; gap: 9px; padding: 6px 0; font-weight: 600; }
  .cmp-destino span { font-weight: 400; color: #6B6280; }
  .cmp-acciones { display: flex; flex-wrap: wrap; gap: 10px; margin: 12px 0 0; }
</style>
</head>
<body>
<div class="wrap">
  <header class="topbar">
    <div>
      <h1>Comprobante de pago</h1>
      <p class="muted">
        El respaldo de lo cobrado y lo pagado, en PDF. No es boleta del SII: eso se emite aparte.
      </p>
    </div>
    <form method="post" class="inline-form">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="logout">
      <button class="btn btn-ghost" type="submit"><?= admin_icon('logout') ?> Salir</button>
    </form>
  </header>

  <nav class="tabs">
    <a class="tab" href="index.php"><?= admin_icon('party') ?> Fiestas</a>
    <a class="tab" href="index.php?view=temas"><?= admin_icon('palette') ?> Temáticas</a>
    <a class="tab" href="leads.php"><?= admin_icon('party') ?> Solicitudes</a>
    <a class="tab" href="mensajes.php"><?= admin_icon('chat') ?> Mensajes</a>
    <a class="tab active" href="comprobante.php"><?= admin_icon('copy') ?> Comprobante</a>
  </nav>

  <?php if ($aviso !== ''): ?><p class="alert alert-ok"><?= h($aviso) ?></p><?php endif; ?>
  <?php if ($avisoError !== ''): ?><p class="alert alert-error"><?= h($avisoError) ?></p><?php endif; ?>

  <section class="card">
    <h2>Fiesta</h2>
    <?php if (!$fiestas): ?>
      <p class="muted">Todavía no hay fiestas creadas.</p>
    <?php else: ?>
      <form method="get" class="inline-form">
        <select name="p" onchange="this.form.submit()">
          <?php foreach ($fiestas as $slug => $f): ?>
            <option value="<?= h($slug) ?>" <?= $slug === $slugActual ? 'selected' : '' ?>>
              <?= h($f['nombre']) ?><?= $f['fecha'] !== '' ? ' · ' . h($f['fecha']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn" type="submit">Ver</button></noscript>
      </form>
    <?php endif; ?>
  </section>

  <?php if ($datos === null): ?>
    <section class="card">
      <p>
        Esta fiesta todavía no tiene <strong>precio cargado</strong>, así que no hay nada que comprobar.
        Cárgalo en la ficha de la fiesta, en la sección «Cobro», y vuelve acá.
      </p>
      <a class="btn btn-primary" href="index.php?action=editar&amp;slug=<?= h(rawurlencode($slugActual)) ?>">Ir a la ficha de la fiesta</a>
    </section>
  <?php else: ?>

    <section class="card">
      <h2>Lo que dice el comprobante</h2>
      <div class="cmp-grid">
        <div>
          <div class="cmp-monto"><span>Precio del servicio</span><span><?= h(cb_format_clp($datos['cobro']['price_total'])) ?></span></div>
          <?php if (!empty($datos['cobro']['discount_amount'])): ?>
            <div class="cmp-monto">
              <span>Descuento<?= $datos['cobro']['discount_label'] !== '' ? ' · ' . h($datos['cobro']['discount_label']) : '' ?></span>
              <span>- <?= h(cb_format_clp((int) $datos['cobro']['discount_amount'])) ?></span>
            </div>
          <?php endif; ?>
          <div class="cmp-monto"><strong>Total a pagar</strong><strong class="cmp-total"><?= h(cb_format_clp($datos['cobro']['total'])) ?></strong></div>
          <?php if ($datos['cobro']['deposit_amount'] !== null): ?>
            <div class="cmp-monto"><span>Abono recibido</span><span><?= h(cb_format_clp((int) $datos['cobro']['deposit_amount'])) ?></span></div>
            <div class="cmp-monto"><strong>Saldo pendiente</strong><strong><?= h(cb_format_clp($datos['cobro']['balance'])) ?></strong></div>
          <?php endif; ?>
        </div>
        <div>
          <p class="muted" style="margin-top:0">N° <?= h($datos['folio']) ?></p>
          <?php if ($datos['cliente'] === null): ?>
            <p class="alert alert-error">
              Esta fiesta no tiene contactos cargados: el comprobante sale sin nombre de cliente y no
              hay a quién enviárselo. Agrégalos en la ficha de la fiesta.
            </p>
          <?php else: ?>
            <p style="margin:0">
              <strong><?= h($datos['cliente']['nombre']) ?></strong><br>
              <?= h($datos['cliente']['relacion']) ?><?= $datos['cliente']['relacion'] !== '' ? ' · ' : '' ?><?= h($datos['cliente']['email']) ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="card">
      <h2>Ver y descargar</h2>
      <p class="muted">
        El PDF se arma en el momento: si cambias el precio o el abono en la ficha, el enlace de
        abajo muestra el comprobante actualizado sin tener que generar nada de nuevo.
      </p>
      <p class="party-url">
        <input type="text" readonly value="<?= h($urlComprobante) ?>" id="url-comprobante">
        <button type="button" class="btn btn-icon" data-copiar="<?= h($urlComprobante) ?>" title="Copiar enlace"><?= admin_icon('copy') ?></button>
        <a class="btn btn-icon" href="<?= h($urlComprobante) ?>" target="_blank" rel="noopener" title="Abrir el PDF">↗</a>
      </p>
      <iframe class="cmp-visor" src="<?= h($urlComprobante) ?>" title="Vista previa del comprobante"></iframe>
    </section>

    <section class="card">
      <h2>Enviar por correo</h2>
      <?php if (!$contactos): ?>
        <p class="muted">Esta fiesta no tiene correos cargados. Agrégalos en la ficha de la fiesta.</p>
      <?php elseif (!cc_mail_enabled()): ?>
        <p class="alert alert-error">El envío de correo no está configurado en el servidor.</p>
      <?php else: ?>
        <form method="post">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="action" value="enviar">
          <input type="hidden" name="slug" value="<?= h($slugActual) ?>">
          <p class="muted">El PDF va adjunto al mensaje, y también el enlace por si lo quieren compartir.</p>
          <?php foreach ($contactos as $c): ?>
            <label class="cmp-destino">
              <input type="checkbox" name="destinos[]" value="<?= h($c['email']) ?>" <?= $c['is_primary'] ? 'checked' : '' ?>>
              <?= h($c['name']) ?> <span><?= h($c['email']) ?></span>
            </label>
          <?php endforeach; ?>
          <button class="btn btn-primary" type="submit">Enviar el comprobante</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>Mandarlo por WhatsApp</h2>
      <textarea class="cmp-texto" id="texto-wa" readonly><?= h($textoWhatsapp) ?></textarea>
      <p class="cmp-acciones">
        <button type="button" class="btn" data-copiar-de="texto-wa"><?= admin_icon('copy') ?> Copiar texto</button>
        <a class="btn btn-primary" target="_blank" rel="noopener"
           href="https://wa.me/?text=<?= h(rawurlencode($textoWhatsapp)) ?>"><?= icono_whatsapp() ?> Enviar por WhatsApp</a>
      </p>
    </section>

  <?php endif; ?>
</div>

<script>
// Copiar: mismo gesto que en el resto del admin (confirmación breve en el propio botón).
function avisarCopiado(boton) {
  const antes = boton.innerHTML;
  boton.textContent = 'Copiado';
  setTimeout(() => { boton.innerHTML = antes; }, 1200);
}
document.querySelectorAll('[data-copiar]').forEach((b) => {
  b.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(b.dataset.copiar); avisarCopiado(b); } catch (e) {}
  });
});
document.querySelectorAll('[data-copiar-de]').forEach((b) => {
  b.addEventListener('click', async () => {
    const campo = document.getElementById(b.dataset.copiarDe);
    try { await navigator.clipboard.writeText(campo.value); avisarCopiado(b); } catch (e) { campo.select(); }
  });
});
</script>
</body>
</html>
