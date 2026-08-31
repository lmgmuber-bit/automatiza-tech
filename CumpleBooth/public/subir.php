<?php
/**
 * subir.php — página pública de carga del Álbum Recuerdo.
 *
 * Deliberadamente sin React: un invitado en una fiesta, con la wifi saturada y
 * el celular en la mano, no debe descargar un bundle para mandar tres fotos.
 * PHP + JavaScript sin dependencias, mobile-first, y con la paleta real de la
 * temática del evento vía cb_theme_css_vars().
 *
 * El token solo habilita subir. Esta página nunca lista lo que ya se subió, ni
 * muestra invitados, ni da acceso a nada del admin.
 */
require __DIR__ . '/lib.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

$limits = cb_album_limits();

/** Página de estado (enlace inválido, recepción cerrada) con la misma piel. */
function cb_intake_page_message(string $title, string $body, int $status = 200, string $themeSlug = ''): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $vars = $themeSlug !== '' ? cb_theme_css_vars($themeSlug) : '';
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>Álbum Recuerdo · CumpleClick</title>
<style><?php require __DIR__ . '/_album-intake.css.php'; ?></style>
<?php if ($vars !== ''): ?><style>:root{<?= $vars ?>}</style><?php endif; ?>
</head>
<body>
  <main class="sheet sheet--center">
    <h1 class="headline"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="lede"><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></p>
    <p class="brandline">
      <img src="brand/cumpleclick-mark.svg" alt="" width="22" height="22">CumpleClick
    </p>
  </main>
</body>
</html><?php
    exit;
}

$token = (string) ($_GET['t'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    cb_intake_page_message(
        'Enlace no válido',
        'Revisa el código QR o pídele al organizador de la fiesta un enlace nuevo.',
        400
    );
}

try {
    $resolved = cb_album_resolve_token($token, 'intake');
} catch (Throwable $e) {
    error_log('CumpleClick subir.php: ' . $e->getMessage());
    cb_intake_page_message('No disponible', 'Vuelve a intentarlo en unos minutos.', 503);
}

// Un token inexistente, revocado o vencido dan exactamente el mismo mensaje:
// distinguirlos le confirmaría a quien prueba códigos que acertó uno real.
if ($resolved === null) {
    cb_intake_page_message(
        'Este enlace ya no está disponible',
        'Puede que la recepción de recuerdos haya terminado. Consúltale al organizador de la fiesta.',
        410
    );
}

$album = $resolved['album'];
$partySlug = $resolved['party_slug'];
$party = cb_load_party_raw($partySlug);
if ($party === null) {
    cb_intake_page_message('Este enlace ya no está disponible', 'Consúltale al organizador de la fiesta.', 410);
}

$themes = cb_load_themes()['themes'] ?? [];
$themeSlug = (string) ($party['tema'] ?? '');
$themeData = is_array($themes[$themeSlug] ?? null) ? $themes[$themeSlug] : [];
$themeVars = cb_theme_css_vars($themeSlug);
$eventName = (string) ($party['nombre'] ?? '');

if (!cb_album_intake_open($album, $party)) {
    cb_intake_page_message(
        'La recepción de recuerdos está cerrada',
        $eventName !== ''
            ? 'El álbum de la fiesta de ' . $eventName . ' ya no está recibiendo fotos ni videos. ¡Gracias igual!'
            : 'Este álbum ya no está recibiendo fotos ni videos. ¡Gracias igual!',
        403,
        $themeSlug
    );
}

$videosAllowed = !empty($album['intake_videos']);
$intakeMessage = trim((string) ($album['intake_message'] ?? ''));
if ($intakeMessage === '') {
    $intakeMessage = '¡Comparte tus mejores fotos' . ($videosAllowed ? ' y videos' : '') . ' de esta celebración!';
}

// Fondo temático: se usa el banner del tema si existe en disco. Si falta, el
// degradado de tokens ya deja la página con los colores correctos.
$bannerRel = 'themes/' . $themeSlug . '/fondo-banner.jpg';
$hasBanner = $themeSlug !== '' && is_file(__DIR__ . '/' . $bannerRel);

$accept = $videosAllowed ? 'image/jpeg,image/png,image/webp,video/mp4' : 'image/jpeg,image/png,image/webp';
$config = [
    'endpoint' => 'album-intake.php',
    'token' => $token,
    'videos' => $videosAllowed,
    'maxFiles' => (int) $limits['files_per_submit'],
    'maxVideos' => (int) $limits['videos_per_submit'],
    'imageMaxBytes' => (int) $limits['image_max_bytes'],
    'videoMaxBytes' => (int) $limits['video_max_bytes'],
    'videoMaxSeconds' => (float) $limits['video_max_seconds'],
];
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>Comparte tus recuerdos<?= $eventName !== '' ? ' · ' . htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8') : '' ?></title>
<style><?php require __DIR__ . '/_album-intake.css.php'; ?></style>
<?php if ($themeVars !== ''): ?><style>:root{<?= $themeVars ?>}</style><?php endif; ?>
<?php if ($hasBanner): ?>
<style>.sheet::before{background-image:url("<?= htmlspecialchars($bannerRel, ENT_QUOTES, 'UTF-8') ?>")}</style>
<?php endif; ?>
</head>
<body>
<main class="sheet">
  <header class="intro">
    <p class="eyebrow">Álbum Recuerdo</p>
    <h1 class="headline">
      <?php if ($eventName !== ''): ?>
        Fiesta de <?= htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8') ?>
      <?php else: ?>
        Comparte tus recuerdos
      <?php endif; ?>
    </h1>
    <p class="lede"><?= htmlspecialchars($intakeMessage, ENT_QUOTES, 'UTF-8') ?></p>
  </header>

  <section class="panel" id="panel-form">
    <ul class="rules">
      <li>Hasta <strong><?= (int) $limits['files_per_submit'] ?> archivos</strong> por envío.</li>
      <li>Fotos JPG, PNG o WEBP de hasta <strong><?= (int) round($limits['image_max_bytes'] / 1048576) ?> MB</strong>.</li>
      <?php if ($videosAllowed): ?>
        <li>Videos MP4 de hasta <strong><?= (int) $limits['video_max_seconds'] ?> segundos</strong> y <?= (int) round($limits['video_max_bytes'] / 1048576) ?> MB.</li>
      <?php else: ?>
        <li>En esta fiesta se reciben <strong>solo fotos</strong>.</li>
      <?php endif; ?>
      <li>El organizador revisa todo antes de publicarlo.</li>
    </ul>

    <label class="picker" for="files">
      <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m21 16-5-5L6 19"/>
      </svg>
      <span class="picker-title">Elegir <?= $videosAllowed ? 'fotos o videos' : 'fotos' ?></span>
      <span class="picker-hint">Puedes seleccionar varios de una vez</span>
      <input type="file" id="files" name="files" multiple accept="<?= htmlspecialchars($accept, ENT_QUOTES, 'UTF-8') ?>">
    </label>

    <ul class="queue" id="queue" aria-live="polite"></ul>

    <div class="field">
      <label for="name">Tu nombre <span class="optional">(opcional)</span></label>
      <input type="text" id="name" name="name" maxlength="80" autocomplete="name" placeholder="Ej: Tía Rosa">
    </div>
    <div class="field">
      <label for="message">Un mensaje <span class="optional">(opcional)</span></label>
      <textarea id="message" name="message" maxlength="280" rows="2" placeholder="¡Qué linda fiesta!"></textarea>
    </div>

    <label class="consent">
      <input type="checkbox" id="consent">
      <span>Confirmo que tengo derecho a compartir estas fotos<?= $videosAllowed ? ' y videos' : '' ?> y que se agreguen al álbum de recuerdos de esta fiesta.</span>
    </label>

    <p class="error" id="form-error" role="alert" hidden></p>

    <button type="button" class="cta" id="send" disabled>Enviar mis recuerdos</button>
    <p class="fineprint">
      No pedimos tu teléfono ni tu correo. Solo se guarda lo que envías y, si quieres, tu nombre.
    </p>
  </section>

  <section class="panel panel--done" id="panel-done" hidden>
    <div class="done-mark" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 6 9 17l-5-5"/>
      </svg>
    </div>
    <h2 class="headline" id="done-title">¡Gracias!</h2>
    <p class="lede" id="done-text"></p>
    <button type="button" class="cta cta--ghost" id="again">Enviar más recuerdos</button>
  </section>

  <p class="brandline">
    <img src="brand/cumpleclick-mark.svg" alt="" width="22" height="22">CumpleClick
  </p>
</main>

<script>
(function () {
  'use strict';
  var CONFIG = <?= json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  var input = document.getElementById('files');
  var queue = document.getElementById('queue');
  var sendBtn = document.getElementById('send');
  var consent = document.getElementById('consent');
  var formError = document.getElementById('form-error');
  var panelForm = document.getElementById('panel-form');
  var panelDone = document.getElementById('panel-done');

  // Mensajes por clave de error del backend. Cualquier clave desconocida cae en
  // un texto genérico: al invitado nunca le llega un detalle interno.
  var MESSAGES = {
    empty: 'El archivo llegó vacío.',
    format: 'Ese formato no se puede subir.',
    image_too_big: 'La foto pesa demasiado.',
    image_dimensions: 'La foto es demasiado grande.',
    videos_disabled: 'En esta fiesta solo se reciben fotos.',
    video_too_big: 'El video pesa demasiado.',
    video_too_long: 'El video dura más de ' + CONFIG.videoMaxSeconds + ' segundos.',
    video_dimensions: 'El video tiene una resolución demasiado alta.',
    album_full: 'El álbum de esta fiesta ya está lleno.',
    closed: 'La recepción de recuerdos se cerró.',
    bad_link: 'Este enlace ya no sirve.',
    consent_required: 'Falta confirmar el permiso para compartir.',
    rate_limited: 'Enviaste muchos archivos seguidos. Espera un momento y sigue.',
    no_file: 'No se pudo leer el archivo.',
    upload_failed: 'No se pudo guardar. Inténtalo de nuevo.',
    unavailable: 'El servicio no está disponible ahora.',
    network: 'Se cortó la conexión. Inténtalo de nuevo.'
  };

  var items = [];

  function humanSize(bytes) {
    if (bytes >= 1048576) { return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB'; }
    return Math.max(1, Math.round(bytes / 1024)) + ' KB';
  }

  function showError(text) {
    formError.textContent = text;
    formError.hidden = !text;
  }

  function isVideo(file) { return file.type.indexOf('video/') === 0; }

  function refresh() {
    var pendientes = items.filter(function (it) { return it.state !== 'done'; }).length;
    sendBtn.disabled = !(items.length && consent.checked && pendientes > 0);
    sendBtn.textContent = items.length > 1
      ? 'Enviar ' + items.length + ' recuerdos'
      : 'Enviar mi recuerdo';
  }

  function render() {
    queue.innerHTML = '';
    items.forEach(function (item, index) {
      var li = document.createElement('li');
      li.className = 'queue-item is-' + item.state;

      var thumb = document.createElement('div');
      thumb.className = 'queue-thumb';
      if (item.previewUrl) {
        var img = document.createElement('img');
        img.src = item.previewUrl;
        img.alt = '';
        thumb.appendChild(img);
      } else {
        thumb.textContent = isVideo(item.file) ? '▶' : '🖼';
      }

      var body = document.createElement('div');
      body.className = 'queue-body';
      var name = document.createElement('span');
      name.className = 'queue-name';
      name.textContent = item.file.name;
      var meta = document.createElement('span');
      meta.className = 'queue-meta';
      meta.textContent = item.state === 'error'
        ? item.error
        : (item.state === 'done' ? 'Enviado' : humanSize(item.file.size));
      body.appendChild(name);
      body.appendChild(meta);

      var bar = document.createElement('div');
      bar.className = 'queue-bar';
      var fill = document.createElement('span');
      fill.style.width = (item.progress || 0) + '%';
      bar.appendChild(fill);
      body.appendChild(bar);

      li.appendChild(thumb);
      li.appendChild(body);

      if (item.state !== 'sending' && item.state !== 'done') {
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'queue-remove';
        remove.setAttribute('aria-label', 'Quitar ' + item.file.name);
        remove.textContent = '×';
        remove.addEventListener('click', function () {
          if (item.previewUrl) { URL.revokeObjectURL(item.previewUrl); }
          items.splice(index, 1);
          render();
          refresh();
        });
        li.appendChild(remove);
      }

      queue.appendChild(li);
    });
  }

  /**
   * Captura el primer fotograma del video en el propio celular. El servidor no
   * tiene ffmpeg, así que sin esto la revista no tendría póster que mostrar
   * antes de que el video cargue.
   */
  function grabPoster(file) {
    return new Promise(function (resolve) {
      var url = URL.createObjectURL(file);
      var video = document.createElement('video');
      var settled = false;
      function finish(value) {
        if (settled) { return; }
        settled = true;
        URL.revokeObjectURL(url);
        resolve(value);
      }
      // Si el códec no se puede decodificar en este navegador, no se bloquea el
      // envío: el video igual se sube, solo se queda sin póster.
      var timer = setTimeout(function () { finish(null); }, 4000);
      video.muted = true;
      video.playsInline = true;
      video.preload = 'metadata';
      video.addEventListener('error', function () { clearTimeout(timer); finish(null); });
      video.addEventListener('loadeddata', function () {
        try {
          var canvas = document.createElement('canvas');
          var scale = Math.min(1, 640 / Math.max(video.videoWidth || 1, video.videoHeight || 1));
          canvas.width = Math.max(1, Math.round((video.videoWidth || 1) * scale));
          canvas.height = Math.max(1, Math.round((video.videoHeight || 1) * scale));
          canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
          canvas.toBlob(function (blob) { clearTimeout(timer); finish(blob); }, 'image/jpeg', 0.8);
        } catch (e) {
          clearTimeout(timer);
          finish(null);
        }
      });
      video.src = url;
    });
  }

  function uploadOne(item) {
    return new Promise(function (resolve) {
      var send = function (poster) {
        var data = new FormData();
        data.append('t', CONFIG.token);
        data.append('consent', '1');
        data.append('name', document.getElementById('name').value);
        data.append('message', document.getElementById('message').value);
        data.append('file', item.file, item.file.name);
        if (poster) { data.append('poster', poster, 'poster.jpg'); }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', CONFIG.endpoint, true);
        xhr.upload.addEventListener('progress', function (event) {
          if (event.lengthComputable) {
            item.progress = Math.round((event.loaded / event.total) * 100);
            render();
          }
        });
        xhr.addEventListener('load', function () {
          var body = null;
          try { body = JSON.parse(xhr.responseText); } catch (e) { body = null; }
          if (xhr.status >= 200 && xhr.status < 300 && body && body.ok) {
            item.state = 'done';
            item.progress = 100;
            item.duplicate = !!body.duplicate;
          } else {
            item.state = 'error';
            item.progress = 0;
            var key = body && body.error ? body.error : 'upload_failed';
            item.error = MESSAGES[key] || MESSAGES.upload_failed;
            item.fatal = (key === 'closed' || key === 'bad_link' || key === 'album_full');
          }
          render();
          resolve();
        });
        xhr.addEventListener('error', function () {
          item.state = 'error';
          item.progress = 0;
          item.error = MESSAGES.network;
          render();
          resolve();
        });
        xhr.send(data);
      };

      item.state = 'sending';
      item.progress = 0;
      render();
      if (isVideo(item.file)) {
        grabPoster(item.file).then(send);
      } else {
        send(null);
      }
    });
  }

  input.addEventListener('change', function () {
    showError('');
    var incoming = Array.prototype.slice.call(input.files || []);
    var rejected = [];

    incoming.forEach(function (file) {
      if (items.length >= CONFIG.maxFiles) {
        rejected.push('Solo se pueden enviar ' + CONFIG.maxFiles + ' archivos por vez.');
        return;
      }
      if (isVideo(file)) {
        if (!CONFIG.videos) {
          rejected.push('En esta fiesta solo se reciben fotos.');
          return;
        }
        var videos = items.filter(function (it) { return isVideo(it.file); }).length;
        if (videos >= CONFIG.maxVideos) {
          rejected.push('Solo se pueden enviar ' + CONFIG.maxVideos + ' videos por vez.');
          return;
        }
        if (file.size > CONFIG.videoMaxBytes) {
          rejected.push('"' + file.name + '" pesa más de ' + Math.round(CONFIG.videoMaxBytes / 1048576) + ' MB.');
          return;
        }
      } else if (file.size > CONFIG.imageMaxBytes) {
        rejected.push('"' + file.name + '" pesa más de ' + Math.round(CONFIG.imageMaxBytes / 1048576) + ' MB.');
        return;
      }

      // Duplicado obvio dentro de la misma selección; el servidor igual valida
      // por hash, esto solo evita subirlo dos veces al pedo.
      var repeated = items.some(function (it) {
        return it.file.name === file.name && it.file.size === file.size;
      });
      if (repeated) { return; }

      items.push({
        file: file,
        state: 'ready',
        progress: 0,
        previewUrl: isVideo(file) ? null : URL.createObjectURL(file)
      });
    });

    if (rejected.length) { showError(rejected[0]); }
    input.value = '';
    render();
    refresh();
  });

  consent.addEventListener('change', function () {
    showError('');
    refresh();
  });

  sendBtn.addEventListener('click', function () {
    if (!consent.checked) {
      showError('Falta confirmar el permiso para compartir.');
      return;
    }
    showError('');
    sendBtn.disabled = true;

    var pending = items.filter(function (it) { return it.state !== 'done'; });
    // Secuencial a propósito: en la wifi de una fiesta, tres subidas en
    // paralelo se pisan y ninguna termina. De a uno el progreso es real.
    var chain = Promise.resolve();
    pending.forEach(function (item) {
      chain = chain.then(function () {
        var fatal = items.some(function (it) { return it.fatal; });
        return fatal ? null : uploadOne(item);
      });
    });

    chain.then(function () {
      var done = items.filter(function (it) { return it.state === 'done'; });
      var failed = items.filter(function (it) { return it.state === 'error'; });

      if (done.length && !failed.length) {
        var nuevos = done.filter(function (it) { return !it.duplicate; }).length;
        document.getElementById('done-text').textContent = nuevos === 0
          ? 'Estos recuerdos ya estaban en el álbum, así que no se repitieron.'
          : (nuevos === 1
              ? 'Tu recuerdo se agregó al álbum. El organizador lo revisará antes de publicarlo.'
              : 'Tus ' + nuevos + ' recuerdos se agregaron al álbum. El organizador los revisará antes de publicarlos.');
        panelForm.hidden = true;
        panelDone.hidden = false;
        items.forEach(function (it) { if (it.previewUrl) { URL.revokeObjectURL(it.previewUrl); } });
        items = [];
        return;
      }

      if (failed.length) {
        showError(failed[0].error + (done.length ? ' Los demás sí se enviaron.' : ''));
      }
      refresh();
    });
  });

  document.getElementById('again').addEventListener('click', function () {
    panelDone.hidden = true;
    panelForm.hidden = false;
    document.getElementById('message').value = '';
    consent.checked = false;
    render();
    refresh();
  });

  refresh();
})();
</script>
</body>
</html>
