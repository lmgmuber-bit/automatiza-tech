<?php
/**
 * admin/mensajes.php — los textos para mandarle a quien contrató la fiesta.
 *
 * El correo de bienvenida se manda por correo, pero en la práctica casi todo se
 * conversa por WhatsApp: el enlace de firma, el recordatorio si no firman, y las
 * fotos cuando termina la fiesta. Escribir eso a mano cada vez termina en textos
 * distintos, enlaces mal pegados y PIN olvidados.
 *
 * Esta pantalla arma los cuatro mensajes con los datos reales de la fiesta y deja
 * dos botones: copiar, o abrir WhatsApp con el texto ya escrito. No envía nada por
 * su cuenta: abre WhatsApp Web o la app y ahí decide la persona.
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

// ================== SESIÓN (mismo contrato que index.php y leads.php) ==================
$loginError = '';
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
    <?php if ($loginError !== ''): ?><p class="alert alert-err"><?= h($loginError) ?></p><?php endif; ?>
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
// Solo temáticas con mundo 3D ofrecen el juego; mandar ese enlace en una fiesta
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
        'etiqueta' => (string) ($p['admin_label'] ?? ''),
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
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleBooth Admin · Mensajes</title>
<style><?php require __DIR__ . '/_style.css.php'; ?></style>
<style>
  .msg-campos { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:14px 18px; margin:0 0 22px; }
  .msg-tarjeta { border:1px solid #e3e0ea; border-radius:14px; padding:16px 18px; margin:0 0 18px; background:#fff; }
  .msg-tarjeta h3 { margin:0 0 4px; font-size:1.05rem; }
  .msg-tarjeta .muted { margin:0 0 12px; }
  .msg-texto { width:100%; min-height:210px; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.86rem;
               line-height:1.55; padding:12px 14px; border:1px solid #ded9e6; border-radius:10px; resize:vertical; background:#fbfafd; }
  .msg-acciones { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; align-items:center; }
  .btn-wa { background:#25D366; color:#0b3d20; border:0; }
  .btn-wa:hover { filter:brightness(.95); }
  .msg-ok { color:#1a7f4b; font-weight:700; font-size:.9rem; }
  .msg-aviso { background:#FFF8EC; border:1px solid #f0e2c8; border-radius:10px; padding:10px 12px; font-size:.9rem; margin:0 0 18px; }
</style>
</head>
<body>
<div class="wrap">
  <header class="head">
    <div>
      <h1>Mensajes para el cliente</h1>
      <p class="muted">Textos listos para copiar o mandar por WhatsApp a quien contrató la fiesta.</p>
    </div>
    <div class="head-actions"><a class="btn" href="index.php">Volver a Fiestas</a></div>
  </header>

  <main>
    <?php if (!$fiestas): ?>
      <p class="alert alert-err">No hay fiestas cargadas todavía.</p>
    <?php else: ?>

    <div class="msg-aviso">
      El PIN de la galería no se puede leer desde la base (se guarda cifrado), y los enlaces de
      firma e invitación se emiten en sus propias pantallas. Complétalos aquí y los textos se
      arman solos.
    </div>

    <section class="card">
      <div class="msg-campos">
        <div>
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
        <div>
          <label for="f-cliente">Nombre de quien contrató</label>
          <input type="text" id="f-cliente" placeholder="Ej: Carolina" autocomplete="off">
        </div>
        <div>
          <label for="f-telefono">WhatsApp del cliente</label>
          <input type="text" id="f-telefono" placeholder="+56 9 1234 5678" autocomplete="off">
          <small class="ayuda">Opcional. Sin número, WhatsApp te deja elegir el contacto.</small>
        </div>
        <div>
          <label for="f-pin">PIN de la galería</label>
          <input type="text" id="f-pin" value="1234" maxlength="4" inputmode="numeric" autocomplete="off">
        </div>
        <div>
          <label for="f-firma">Enlace de firma</label>
          <input type="text" id="f-firma" placeholder="<?= h($base) ?>/aceptar-plan.php?t=…" autocomplete="off">
          <small class="ayuda">Se genera en la ficha de la fiesta, en Aceptación.</small>
        </div>
        <div>
          <label for="f-invitacion">Enlace de la invitación</label>
          <input type="text" id="f-invitacion" placeholder="<?= h($base) ?>/nombre-token" autocomplete="off">
        </div>
      </div>
    </section>

    <div id="msg-lista"></div>

    <?php endif; ?>
  </main>
</div>

<script>
(function () {
  var sel = document.getElementById('f-fiesta');
  if (!sel) { return; }
  var lista = document.getElementById('msg-lista');
  var campos = ['f-cliente', 'f-telefono', 'f-pin', 'f-firma', 'f-invitacion'].map(function (id) {
    return document.getElementById(id);
  });

  // Las plantillas son las mismas que se usan a mano hoy. `{...}` se reemplaza con
  // los datos de la fiesta; una línea cuyo dato falte se cae entera (ver `bloque`).
  var PLANTILLAS = [
    {
      id: 'bienvenida',
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
      id: 'recordatorio',
      titulo: 'Recordatorio de firma',
      nota: 'Si pasaron unos días y todavía no firman.',
      texto: function (d) {
        return '¡Hola ' + d.cliente + '! 👋 Te recordamos que falta firmar los Términos y Condiciones del cumpleaños de *' + d.nino + '*. Son 2 minutos y con eso dejamos la fiesta confirmada 🎉\n' +
          bloque(d.firma, '{v}', '(pega aquí el enlace de firma)') + '\n' +
          'Cualquier duda nos escribes por aquí.';
      }
    },
    {
      id: 'vispera',
      titulo: 'Un día antes',
      nota: 'Confirma hora y lo que necesitamos en el lugar.',
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
      id: 'despues',
      titulo: 'Después de la fiesta',
      nota: 'Con la galería lista para descargar.',
      texto: function (d) {
        return '¡Hola ' + d.cliente + '! 🎉 Gracias por dejarnos ser parte del cumpleaños de *' + d.nino + '*.\n\n' +
          'Ya están todas las fotos en tu galería 📸 Entras con tu PIN *' + d.pin + '* y las descargas todas:\n' +
          d.urlGaleria + '\n\n' +
          'Si te gustó, nos ayuda mucho que nos etiquetes en Instagram 👉 @Cumple_Click';
      }
    }
  ];

  /** Devuelve la línea solo si hay dato; si no, el texto de reemplazo (o nada). */
  function bloque(valor, plantilla, vacio) {
    if (!valor) { return vacio || ''; }
    return plantilla.replace('{v}', valor);
  }

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
      var texto = p.texto(d);
      var card = document.createElement('section');
      card.className = 'msg-tarjeta';

      var h3 = document.createElement('h3');
      h3.textContent = p.titulo;
      var nota = document.createElement('p');
      nota.className = 'muted';
      nota.textContent = p.nota;

      var ta = document.createElement('textarea');
      ta.className = 'msg-texto';
      ta.value = texto;
      ta.setAttribute('aria-label', 'Mensaje: ' + p.titulo);

      var acciones = document.createElement('div');
      acciones.className = 'msg-acciones';

      var copiar = document.createElement('button');
      copiar.type = 'button';
      copiar.className = 'btn';
      copiar.textContent = '📋 Copiar';

      var wa = document.createElement('a');
      wa.className = 'btn btn-wa';
      wa.target = '_blank';
      wa.rel = 'noopener';
      wa.textContent = '💬 Enviar por WhatsApp';

      var aviso = document.createElement('span');
      aviso.className = 'msg-ok';
      aviso.hidden = true;
      aviso.textContent = '¡Copiado!';

      function refrescarWa() {
        var n = telefonoWa(document.getElementById('f-telefono').value);
        wa.href = 'https://wa.me/' + n + '?text=' + encodeURIComponent(ta.value);
      }
      refrescarWa();

      copiar.addEventListener('click', function () {
        var listo = function () {
          aviso.hidden = false;
          setTimeout(function () { aviso.hidden = true; }, 1800);
        };
        // El portapapeles moderno exige contexto seguro; en http:// del kiosco no
        // existe, así que se cae a seleccionar + execCommand, que sí funciona ahí.
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(ta.value).then(listo, function () { ta.select(); document.execCommand('copy'); listo(); });
        } else {
          ta.select();
          document.execCommand('copy');
          listo();
        }
      });
      // Si el operador edita el texto a mano, el botón de WhatsApp manda lo editado.
      ta.addEventListener('input', refrescarWa);

      acciones.appendChild(copiar);
      acciones.appendChild(wa);
      acciones.appendChild(aviso);
      card.appendChild(h3);
      card.appendChild(nota);
      card.appendChild(ta);
      card.appendChild(acciones);
      lista.appendChild(card);
    });
  }

  sel.addEventListener('change', function () {
    var url = new URL(window.location.href);
    url.searchParams.set('p', sel.value);
    window.history.replaceState({}, '', url.toString());
    pintar();
  });
  campos.forEach(function (el) { if (el) { el.addEventListener('input', pintar); } });
  pintar();
})();
</script>
</body>
</html>
