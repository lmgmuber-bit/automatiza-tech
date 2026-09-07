<?php
/**
 * admin/mensajes.php — los textos para mandarle a quien contrató la fiesta.
 *
 * El correo de bienvenida se manda por correo, pero en la práctica casi todo se
 * conversa por WhatsApp: el enlace de firma, el recordatorio si no firman, y las
 * fotos cuando termina la fiesta. Escribir eso a mano cada vez termina en textos
 * distintos, enlaces mal pegados y PIN olvidados.
 *
 * Esta pantalla arma los mensajes con los datos reales de la fiesta y deja dos
 * botones: copiar, o abrir WhatsApp con el texto ya escrito. No envía nada por su
 * cuenta: abre WhatsApp Web o la app y ahí decide la persona.
 *
 * Lo que la fiesta no puede decir sola va en campos editables arriba: el PIN de la
 * galería (en la base solo está su hash, no se puede leer), el enlace de firma (lo
 * emite la pantalla de Aceptación) y el de la invitación.
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
        header('Location: mensajes.php');
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
            header('Location: mensajes.php');
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
  <form class="login-card" method="post" action="mensajes.php">
    <h1>CumpleBooth</h1>
    <p class="muted">Mensajes para el cliente</p>
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
// Solo las temáticas con mundo 3D ofrecen el juego; mandar ese enlace en una fiesta
// sin juego sería mandar a una página que no corresponde a su temática.
$TEMAS_JUEGO_3D = ['hielo' => 'Reino de Hielo en 3D', 'heroes' => 'Misión 3D', 'spidey' => 'Aventura Arácnida en 3D'];

$data = cb_load_parties();
$parties = is_array($data['parties'] ?? null) ? $data['parties'] : [];
$base = rtrim((string) cb_public_base_url(), '/');

$fiestas = [];
foreach ($parties as $slug => $p) {
    if (!is_array($p)) { continue; }
    $tema = (string) ($p['tema'] ?? $p['theme_slug'] ?? '');
    $fiestas[] = [
        'slug' => (string) $slug,
        'nombre' => (string) ($p['nombre'] ?? $p['birthday_person_name'] ?? $slug),
        'tema' => $tema,
        'temaNombre' => cb_theme_public_name($tema),
        'fecha' => (string) ($p['fecha'] ?? ''),
        'activa' => !empty($p['activa']),
        'juego' => $TEMAS_JUEGO_3D[$tema] ?? '',
        'urlJuego' => $base . '/juego/?p=' . rawurlencode((string) $slug),
        'urlGaleria' => $base . '/galeria.php?p=' . rawurlencode((string) $slug),
    ];
}
usort($fiestas, static function ($a, $b) {
    if ($a['activa'] !== $b['activa']) { return $a['activa'] ? -1 : 1; }
    return strcmp($b['fecha'], $a['fecha']);
});
$seleccion = isset($_GET['p']) && is_string($_GET['p']) ? $_GET['p'] : ($fiestas[0]['slug'] ?? '');
$fiestaActual = null;
foreach ($fiestas as $f) {
    if ($f['slug'] === $seleccion) { $fiestaActual = $f; break; }
}
if ($fiestaActual === null) { $fiestaActual = $fiestas[0] ?? null; $seleccion = $fiestaActual['slug'] ?? ''; }

/* El enlace de la invitación no se pega a mano: se deriva del id de la invitación de
   esta fiesta, igual que en admin/invitations.php. El token público está hasheado en
   la base, pero `cb_invitation_share_token()` reconstruye uno compartible a partir del
   id. Si la fiesta todavía no tiene invitación, el campo queda vacío y editable. */
$urlInvitacion = '';
// Las tres funciones viven en lib.invitations.php; se comprueban antes de llamarlas para
// que la pantalla siga sirviendo aunque ese módulo no esté presente.
if ($fiestaActual !== null && cb_storage_mode() === 'db'
    && function_exists('cb_list_invitations')
    && function_exists('cb_invitation_share_token')
    && function_exists('cb_invitation_pretty_url')) {
    try {
        $partyId = cb_party_db_id($fiestaActual['slug']);
        if ($partyId !== null) {
            $invs = cb_list_invitations($partyId);
            if ($invs) {
                $inv = $invs[0];
                $urlInvitacion = cb_invitation_pretty_url(
                    cb_invitation_share_token((int) $inv['id']),
                    (string) ($inv['birthday_person_name'] ?? $fiestaActual['nombre'])
                );
            }
        }
    } catch (Throwable $e) {
        $urlInvitacion = '';
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleBooth Admin · Mensajes</title>
<style><?php require __DIR__ . '/_style.css.php'; ?></style>
<style>
  /* Solo lo que no existe en _style.css.php: la grilla de campos, la caja de texto
     del mensaje y el botón de WhatsApp con su color de marca. Todo lo demás usa las
     clases del admin (card, field, btn, tabs, alert). */
  .msg-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px 20px; }
  .msg-lista { display: grid; gap: 18px; margin-top: 22px; }
  .msg-card-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
  .msg-texto {
    width: 100%; min-height: 190px; margin-top: 14px;
    border: 1.5px solid var(--border); border-radius: var(--radius-sm);
    padding: 12px 14px; background: #fff; color: var(--text);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: .87rem; line-height: 1.6; resize: vertical;
  }
  .msg-texto:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(139,92,246,.12); outline: none; }
  .msg-acciones { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 12px; }
  .btn-wa { background: #25D366; color: #08331c; border: 0; }
  .btn-wa:hover { background: #1eb757; color: #08331c; }
  .btn-wa svg { flex: 0 0 auto; }
  .msg-copiado { display: inline-flex; align-items: center; gap: 6px; color: var(--ok, #1a7f4b); font-weight: 700; font-size: .9rem; }
  /* `display` explícito le gana al display:none implícito de [hidden]: sin esta regla
     el "¡Copiado!" queda visible desde que carga la página. */
  .msg-copiado[hidden] { display: none; }
</style>
</head>
<body>
<div class="wrap">
  <header class="head">
    <div>
      <h1>Mensajes<?= $fiestaActual ? ' · ' . h($fiestaActual['nombre']) : '' ?></h1>
      <p class="muted">
        <?php if ($fiestaActual): ?>
          Temática <?= h($fiestaActual['temaNombre'] !== '' ? $fiestaActual['temaNombre'] : $fiestaActual['tema']) ?>.
          Textos listos para copiar o mandar por WhatsApp a quien contrató esta fiesta.
        <?php else: ?>
          Textos listos para copiar o mandar por WhatsApp a quien contrató la fiesta.
        <?php endif; ?>
      </p>
    </div>
    <div class="inline-form logout-btn">
      <?php if ($fiestaActual): ?>
        <a class="btn btn-ghost" href="index.php?action=editar&amp;slug=<?= rawurlencode($fiestaActual['slug']) ?>">Ver la fiesta</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="marca.php">Datos de la marca</a>
      <form method="post" action="mensajes.php" class="inline-form">
        <?= admin_csrf_field() ?><input type="hidden" name="action" value="logout">
        <button class="btn btn-ghost" type="submit"><?= admin_icon('logout') ?> Salir</button>
      </form>
    </div>
  </header>

  <nav class="tabs">
    <a class="tab" href="index.php"><?= admin_icon('party') ?> Fiestas</a>
    <a class="tab" href="index.php?view=temas"><?= admin_icon('palette') ?> Temáticas</a>
    <a class="tab" href="leads.php"><?= admin_icon('party') ?> Solicitudes</a>
    <a class="tab active" href="mensajes.php"><?= admin_icon('chat') ?> Mensajes</a>
  </nav>

  <main>
    <?php if (!$fiestas): ?>
      <p class="alert alert-error">No hay fiestas cargadas todavía.</p>
    <?php else: ?>

    <section class="card">
      <h2>La fiesta y sus datos</h2>
      <p class="muted">
        El PIN de la galería no se puede leer desde la base (se guarda cifrado), y los enlaces de
        firma e invitación se emiten en sus propias pantallas. Complétalos aquí y los textos se
        arman solos.
      </p>
      <div class="msg-grid" style="margin-top:18px">
        <div class="field">
          <label for="f-fiesta">Fiesta</label>
          <select id="f-fiesta">
            <?php foreach ($fiestas as $f): ?>
              <option value="<?= h($f['slug']) ?>" <?= $f['slug'] === $seleccion ? 'selected' : '' ?>
                data-nombre="<?= h($f['nombre']) ?>" data-tema="<?= h($f['temaNombre']) ?>"
                data-juego="<?= h($f['juego']) ?>" data-urljuego="<?= h($f['urlJuego']) ?>"
                data-urlgaleria="<?= h($f['urlGaleria']) ?>">
                <?= h($f['nombre']) ?> — <?= h($f['temaNombre'] !== '' ? $f['temaNombre'] : $f['tema']) ?><?= $f['activa'] ? '' : ' (inactiva)' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="f-cliente">Nombre de quien contrató</label>
          <input type="text" id="f-cliente" placeholder="Ej: Carolina" autocomplete="off">
        </div>
        <div class="field">
          <label for="f-telefono">WhatsApp del cliente</label>
          <input type="text" id="f-telefono" placeholder="+56 9 1234 5678" autocomplete="off">
          <small class="muted">Opcional. Sin número, WhatsApp te deja elegir el contacto.</small>
        </div>
        <div class="field">
          <label for="f-pin">PIN de la galería</label>
          <input type="text" id="f-pin" value="1234" maxlength="4" inputmode="numeric" autocomplete="off">
        </div>
        <div class="field">
          <label for="f-firma">Enlace de firma</label>
          <input type="text" id="f-firma" placeholder="<?= h($base) ?>/aceptar-plan.php?t=…" autocomplete="off">
          <small class="muted">Se genera en la ficha de la fiesta, en Aceptación.</small>
        </div>
        <div class="field">
          <label for="f-invitacion">Enlace de la invitación</label>
          <input type="text" id="f-invitacion" value="<?= h($urlInvitacion) ?>" placeholder="<?= h($base) ?>/nombre-token" autocomplete="off">
          <small class="muted"><?= $urlInvitacion !== '' ? 'Tomado de la invitación de esta fiesta.' : 'Esta fiesta todavía no tiene invitación creada.' ?></small>
        </div>
      </div>
    </section>

    <div class="msg-lista" id="msg-lista"></div>

    <?php endif; ?>
  </main>
</div>

<template id="tpl-wa"><?= icono_whatsapp() ?></template>
<template id="tpl-copy"><?= admin_icon('copy') ?></template>
<template id="tpl-check"><?= admin_icon('check') ?></template>

<script>
(function () {
  var sel = document.getElementById('f-fiesta');
  if (!sel) { return; }
  var lista = document.getElementById('msg-lista');
  var campos = ['f-cliente', 'f-telefono', 'f-pin', 'f-firma', 'f-invitacion'].map(function (id) {
    return document.getElementById(id);
  });
  function icono(id) { return document.getElementById(id).innerHTML; }

  /** Devuelve la línea solo si hay dato; si no, el texto de reemplazo (o nada). */
  function bloque(valor, plantilla, vacio) {
    if (!valor) { return vacio || ''; }
    return plantilla.replace('{v}', valor);
  }

  var PLANTILLAS = [
    {
      titulo: 'Bienvenida y firma',
      nota: 'Apenas se cierra el trato. Lleva el enlace de firma y todos los enlaces de la fiesta.',
      texto: function (d) {
        return '¡Hola ' + d.cliente + '! 👋 Somos CumpleClick.\n\n' +
          'Gracias por elegirnos para el cumpleaños de *' + d.nino + '* 🎉 Ya tenemos todo listo con la temática *' + d.tema + '*, con cabina de fotos' + (d.juego ? ', recuerdos y el juego 3D de la temática' : ' y recuerdos') + '.\n\n' +
          '*Antes de la fiesta, un solo paso:* firmar los Términos y Condiciones. Son 2 minutos, se revisa el resumen del plan y se firma con el dedo en la pantalla 👇\n' +
          bloque(d.firma, '{v}', '(pega aquí el enlace de firma)') + '\n\n' +
          '*Tus enlaces:*\n' +
          bloque(d.invitacion, '💌 Invitación para tus invitados: {v}\n', '') +
          bloque(d.juego && d.urlJuego, '🎮 ' + d.juego + ': ' + d.urlJuego + '\n', '') +
          '🖼️ Galería de fotos (PIN ' + d.pin + '): ' + d.urlGaleria + '\n\n' +
          '📘 Te mandamos también por correo el manual de la fiesta en PDF, con todo lo que necesitamos el día del evento.\n\n' +
          'Síguenos en Instagram 👉 @Cumple_Click\n' +
          'Nuestro sitio 👉 https://cumpleclick.com\n' +
          'Correo 👉 contacto@cumpleclick.com\n\n' +
          'Cualquier duda, respóndenos por aquí 😊';
      }
    },
    {
      titulo: 'Recordatorio de firma',
      nota: 'Si pasaron unos días y todavía no firman.',
      texto: function (d) {
        return '¡Hola ' + d.cliente + '! 👋 Te recordamos que falta firmar los Términos y Condiciones del cumpleaños de *' + d.nino + '*. Son 2 minutos y con eso dejamos la fiesta confirmada 🎉\n' +
          bloque(d.firma, '{v}', '(pega aquí el enlace de firma)') + '\n' +
          'Cualquier duda nos escribes por aquí.';
      }
    },
    {
      titulo: 'Un día antes',
      nota: 'Confirma la hora y lo que necesitamos en el lugar.',
      texto: function (d) {
        return '¡Hola ' + d.cliente + '! 👋 Mañana es el cumpleaños de *' + d.nino + '* 🎉\n\n' +
          'Para dejar todo listo necesitamos:\n' +
          '• Un espacio de 2 x 2 metros para la cabina, contra una pared\n' +
          '• Un enchufe cerca\n' +
          '• Luz pareja, sin sol directo a la pantalla\n\n' +
          'Llegamos antes de la hora de inicio para instalar y probar todo. ¿Nos confirmas la dirección y la hora?';
      }
    },
    {
      titulo: 'Galería de fotos',
      nota: 'Solo el enlace y el PIN. Es el que más se reenvía: sirve para el cliente y para los papás invitados.',
      texto: function (d) {
        return '📸 *Las fotos del cumpleaños de ' + d.nino + ' ya están listas*\n\n' +
          'Entra aquí y descárgalas todas:\n' +
          d.urlGaleria + '\n\n' +
          'PIN de acceso: *' + d.pin + '*\n\n' +
          'Puedes verlas, descargar las que quieras e imprimirlas donde prefieras 💜\n' +
          'CumpleClick · @Cumple_Click · contacto@cumpleclick.com';
      }
    },
    {
      titulo: 'Agradecimiento después de la fiesta',
      nota: 'Cierra la experiencia y pide la etiqueta en Instagram.',
      texto: function (d) {
        return '¡Hola ' + d.cliente + '! 🎉 Gracias por dejarnos ser parte del cumpleaños de *' + d.nino + '*.\n\n' +
          'Ya están todas las fotos en tu galería 📸 Entras con tu PIN *' + d.pin + '* y las descargas todas:\n' +
          d.urlGaleria + '\n\n' +
          'Si te gustó, nos ayuda mucho que nos etiquetes en Instagram 👉 @Cumple_Click';
      }
    }
  ];

  function datos() {
    var op = sel.options[sel.selectedIndex];
    var cliente = document.getElementById('f-cliente').value.trim();
    return {
      nino: op.getAttribute('data-nombre') || '',
      tema: op.getAttribute('data-tema') || '',
      juego: op.getAttribute('data-juego') || '',
      urlJuego: op.getAttribute('data-urljuego') || '',
      urlGaleria: op.getAttribute('data-urlgaleria') || '',
      cliente: cliente !== '' ? cliente : 'papás de ' + (op.getAttribute('data-nombre') || ''),
      telefono: document.getElementById('f-telefono').value.trim(),
      pin: document.getElementById('f-pin').value.trim() || '••••',
      firma: document.getElementById('f-firma').value.trim(),
      invitacion: document.getElementById('f-invitacion').value.trim()
    };
  }

  /** wa.me quiere solo dígitos con código de país. Un número chileno escrito como
   *  "9 1234 5678" no lleva país: se le antepone 56 para que el enlace funcione. */
  function telefonoWa(valor) {
    var n = (valor || '').replace(/[^0-9]/g, '');
    if (n === '') { return ''; }
    if (n.length === 9 && n.charAt(0) === '9') { n = '56' + n; }
    if (n.length === 8) { n = '569' + n; }
    return n;
  }

  function pintar() {
    var d = datos();
    lista.innerHTML = '';
    PLANTILLAS.forEach(function (p) {
      var card = document.createElement('section');
      card.className = 'card';

      var head = document.createElement('div');
      head.className = 'msg-card-head';
      var h2 = document.createElement('h2');
      h2.textContent = p.titulo;
      head.appendChild(h2);

      var nota = document.createElement('p');
      nota.className = 'muted';
      nota.textContent = p.nota;

      var ta = document.createElement('textarea');
      ta.className = 'msg-texto';
      ta.value = p.texto(d);
      ta.setAttribute('aria-label', 'Mensaje: ' + p.titulo);

      var acciones = document.createElement('div');
      acciones.className = 'msg-acciones';

      var copiar = document.createElement('button');
      copiar.type = 'button';
      copiar.className = 'btn btn-ghost';
      copiar.innerHTML = icono('tpl-copy') + ' Copiar';

      var wa = document.createElement('a');
      wa.className = 'btn btn-wa';
      wa.target = '_blank';
      wa.rel = 'noopener';
      wa.innerHTML = icono('tpl-wa') + ' Compartir por WhatsApp';

      var aviso = document.createElement('span');
      aviso.className = 'msg-copiado';
      aviso.hidden = true;
      aviso.innerHTML = icono('tpl-check') + ' ¡Copiado!';

      function refrescarWa() {
        var n = telefonoWa(document.getElementById('f-telefono').value);
        wa.href = 'https://wa.me/' + n + '?text=' + encodeURIComponent(ta.value);
      }
      refrescarWa();

      copiar.addEventListener('click', function () {
        var avisar = function (ok) {
          aviso.innerHTML = ok ? icono('tpl-check') + ' ¡Copiado!' : 'Selecciónalo y copia con Ctrl+C';
          aviso.style.color = ok ? '' : '#b45309';
          aviso.hidden = false;
          setTimeout(function () { aviso.hidden = true; }, 2200);
        };
        // Copiar puede fallar sin aviso: el portapapeles moderno exige contexto seguro
        // (el kiosco corre en http://) y execCommand lanza si el documento no tiene el
        // foco. Cuando falla se selecciona el texto y se dice qué hacer, en vez de
        // dejar al operador creyendo que copió.
        var respaldo = function () {
          try {
            ta.focus();
            ta.select();
            avisar(document.execCommand('copy'));
          } catch (e) {
            avisar(false);
          }
        };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(ta.value).then(function () { avisar(true); }, respaldo);
        } else {
          respaldo();
        }
      });
      // Si el operador edita el texto a mano, el botón de WhatsApp manda lo editado.
      ta.addEventListener('input', refrescarWa);

      acciones.appendChild(copiar);
      acciones.appendChild(wa);
      acciones.appendChild(aviso);
      card.appendChild(head);
      card.appendChild(nota);
      card.appendChild(ta);
      card.appendChild(acciones);
      lista.appendChild(card);
    });
  }

  // Cambiar de fiesta recarga la página en vez de solo repintar: el enlace de la
  // invitación se resuelve en el servidor y no está en el DOM de las otras fiestas.
  sel.addEventListener('change', function () {
    window.location.href = 'mensajes.php?p=' + encodeURIComponent(sel.value);
  });
  campos.forEach(function (el) { if (el) { el.addEventListener('input', pintar); } });
  pintar();
})();
</script>
</body>
</html>
