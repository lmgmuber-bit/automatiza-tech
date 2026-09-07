<?php
/**
 * aceptar-plan.php — página pública donde el cliente lee los Términos, la Política
 * de Privacidad y el Consentimiento de imagen de menores, marca las casillas y
 * firma en pantalla. Autenticación por token opaco (GET ?t=<32 hex>), sin sesión.
 * No expone IDs internos ni rutas. Sin dependencias externas ni CDNs.
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/lib.acceptance.php';

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function cb_accept_page_error(int $code, string $title, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow"><title>CumpleClick</title></head>'
        . '<body style="font-family:system-ui,sans-serif;text-align:center;padding:3rem 1.5rem;background:#FFF8EC;color:#4C2882">'
        . '<h1 style="font-size:1.5rem">' . h($title) . '</h1><p>' . h($message) . '</p>'
        . '<p style="color:#7c6a9c;font-size:.9rem">Si crees que es un error, escríbenos a CumpleClick.</p></body></html>';
    exit;
}

$token = (string) ($_GET['t'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    cb_accept_page_error(400, 'Enlace inválido', 'El enlace de aceptación no es válido. Revisa que lo hayas copiado completo.');
}

try {
    $pageLimit = cb_rate_limit('accept-page', cb_request_identity(), 60, 600, 900);
    if (!$pageLimit['allowed']) {
        header('Retry-After: ' . max(1, (int) $pageLimit['retry_after']));
        cb_accept_page_error(429, 'Demasiados intentos', 'Espera unos minutos y vuelve a abrir el enlace.');
    }
    $acceptance = cb_load_acceptance_by_token_hash(cb_hash_token($token));
} catch (Throwable $e) {
    error_log('CumpleClick aceptar-plan.php: ' . $e->getMessage());
    cb_accept_page_error(503, 'Servicio no disponible', 'No pudimos cargar el enlace en este momento. Intenta más tarde.');
}

if (!$acceptance) {
    cb_accept_page_error(404, 'Enlace no encontrado', 'Este enlace no existe o ya fue utilizado.');
}
if ((string) $acceptance['status'] !== 'pending') {
    cb_accept_page_error(410, 'Enlace ya utilizado', 'Este enlace ya no está disponible. Si ya aceptaste, revisa el comprobante que te enviamos por correo; si necesitas uno nuevo, pídelo a CumpleClick.');
}
if (cb_acceptance_is_expired($acceptance)) {
    cb_accept_page_error(410, 'Enlace vencido', 'Este enlace de aceptación venció. Pide a CumpleClick que te envíe uno nuevo.');
}

try {
    $bundle = cb_legal_bundle();
} catch (Throwable $e) {
    error_log('CumpleClick aceptar-plan.php legal: ' . $e->getMessage());
    cb_accept_page_error(503, 'Documentos no disponibles', 'No pudimos cargar los documentos legales. Intenta más tarde.');
}

$errors = [];
$values = ['signer_name' => '', 'signer_rut' => '', 'signer_email' => (string) $acceptance['client_email'], 'signer_relationship' => '', 'accepted_marketing' => false];
$done = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
    $postLimit = cb_rate_limit('accept-post', cb_request_identity(), 10, 600, 900);
    if (!$postLimit['allowed']) {
        $errors['status'] = 'Recibimos varios intentos seguidos. Espera unos minutos y vuelve a intentarlo.';
    } elseif ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'same-site', 'none'], true)) {
        $errors['status'] = 'Origen no permitido.';
    } elseif (!cb_acceptance_form_nonce_valid((string) $acceptance['public_token_hash'], (string) ($_POST['nonce'] ?? ''))) {
        $errors['status'] = 'El formulario expiró. Recarga la página y vuelve a firmar.';
    } else {
        foreach (['signer_name', 'signer_rut', 'signer_email', 'signer_relationship'] as $field) {
            $values[$field] = is_string($_POST[$field] ?? null) ? trim((string) $_POST[$field]) : '';
        }
        $values['accepted_marketing'] = ($_POST['accepted_marketing'] ?? '') === '1';
        // Metadatos del navegador declarados por el cliente (evidencia complementaria, no confiable por sí sola).
        $meta = [];
        foreach (['meta_tz' => 'zona_horaria', 'meta_screen' => 'pantalla', 'meta_lang' => 'idioma_navegador', 'meta_touch' => 'pantalla_tactil'] as $key => $label) {
            $value = cb_acceptance_text($_POST[$key] ?? '', 80);
            if ($value !== '') {
                $meta[$label] = $value;
            }
        }
        $forwarded = cb_acceptance_text($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '', 200);
        if ($forwarded !== '') {
            $meta['x_forwarded_for (no verificado)'] = $forwarded;
        }
        $acceptLanguage = cb_acceptance_text($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 120);
        if ($acceptLanguage !== '') {
            $meta['accept_language'] = $acceptLanguage;
        }
        try {
            $result = cb_accept_plan($acceptance, $_POST, [
                'ip' => cb_request_identity(),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'meta' => $meta,
            ]);
        } catch (Throwable $e) {
            error_log('CumpleClick aceptar-plan.php accept: ' . $e->getMessage());
            $result = ['ok' => false, 'errors' => ['status' => 'No pudimos registrar la aceptación. Intenta nuevamente en unos minutos.']];
        }
        if ($result['ok']) {
            $fresh = cb_load_acceptance_by_id((int) $result['id']) ?? $acceptance;
            try {
                $mails = cb_acceptance_send_notifications($fresh, $result['receipt_token']);
            } catch (Throwable $e) {
                error_log('CumpleClick aceptar-plan.php mail: ' . $e->getMessage());
                $mails = ['client' => false, 'internal' => false];
            }
            $done = ['row' => $fresh, 'receipt_url' => cb_acceptance_receipt_url($result['receipt_token']), 'mails' => $mails];
        } else {
            $errors = $result['errors'];
        }
    }
}

$summary = is_array($acceptance['plan_summary'] ?? null) ? $acceptance['plan_summary'] : [];
$summaryLabels = cb_acceptance_summary_fields();
$nonce = cb_acceptance_form_nonce((string) $acceptance['public_token_hash'], time());
$relationships = ['madre' => 'Madre', 'padre' => 'Padre', 'tutor' => 'Tutor/a legal', 'autorizado' => 'Adulto autorizado por los padres'];
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>CumpleClick · Aceptación de Términos y firma</title>
<style>
@font-face { font-family: 'Baloo 2'; font-weight: 700; font-display: swap; src: url('admin/fonts/baloo2-700.woff2') format('woff2'); }
:root { --primary:#8B5CF6; --primary-dark:#6d3fd4; --primary-soft:#EDE4FB; --cta:#D6307F; --cta-dark:#B02566; --accent:#FBBF24; --bg:#FFF8EC; --text:#4C2882; --muted:#7c6a9c; --card:#fff; --border:#E9D8FD; --danger:#DC2626; --danger-soft:#FEE2E2; --success:#16A34A; --success-soft:#DCFCE7; }
* { box-sizing: border-box; }
body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); line-height:1.55; }
.wrap { max-width: 860px; margin: 0 auto; padding: 1.25rem 1rem 4rem; }
header.top { display:flex; align-items:center; gap:.75rem; padding:.5rem 0 1rem; }
header.top img { width:44px; height:44px; }
header.top h1 { font-family:'Baloo 2', system-ui, sans-serif; font-size:1.35rem; margin:0; }
header.top h1 span { color: var(--muted); font-weight:500; font-size:.95rem; display:block; }
.card { background: var(--card); border:1px solid var(--border); border-radius:16px; padding:1.25rem; margin-bottom:1rem; box-shadow: 0 1px 2px rgba(76,40,130,.06), 0 6px 20px rgba(139,92,246,.10); }
.card h2 { font-family:'Baloo 2', system-ui, sans-serif; font-size:1.15rem; margin:0 0 .5rem; }
.steps { display:grid; grid-template-columns: repeat(3, 1fr); gap:.5rem; margin:.5rem 0 0; padding:0; list-style:none; }
.steps li { background: var(--primary-soft); border-radius:12px; padding:.6rem .75rem; font-size:.9rem; }
.steps li strong { display:block; color: var(--primary-dark); }
table.summary { width:100%; border-collapse:collapse; font-size:.95rem; }
table.summary th, table.summary td { text-align:left; padding:.45rem .5rem; border-bottom:1px solid var(--border); vertical-align:top; }
table.summary th { width:38%; color: var(--muted); font-weight:600; }
details.doc { border:1px solid var(--border); border-radius:12px; margin:.75rem 0; background:#fff; }
details.doc summary { cursor:pointer; padding:.8rem 1rem; font-weight:700; list-style:none; display:flex; justify-content:space-between; align-items:center; }
details.doc summary::-webkit-details-marker { display:none; }
details.doc summary::after { content:'Leer'; font-size:.8rem; color: var(--primary-dark); background: var(--primary-soft); padding:.2rem .6rem; border-radius:999px; }
details.doc[open] summary::after { content:'Cerrar'; }
.doc-body { max-height: 22rem; overflow:auto; padding:0 1rem 1rem; font-size:.92rem; border-top:1px solid var(--border); }
.doc-body h2 { font-size:1.05rem; margin-top:1rem; }
.doc-body h3 { font-size:.98rem; }
.doc-body blockquote { border-left:4px solid var(--accent); background:#FFF8E1; margin:.75rem 0; padding:.5rem .75rem; font-size:.88rem; }
.doc-body table { border-collapse:collapse; width:100%; font-size:.85rem; }
.doc-body th, .doc-body td { border:1px solid var(--border); padding:.3rem .4rem; text-align:left; }
.doc-body .legal-version { color: var(--muted); font-size:.8rem; }
.check { display:flex; gap:.6rem; align-items:flex-start; padding:.6rem .75rem; border-radius:10px; background:#faf7ff; margin:.4rem 0; font-size:.93rem; }
.check input { margin-top:.25rem; width:1.15rem; height:1.15rem; flex:none; accent-color: var(--cta); }
.check.optional { background:#fffdf5; border:1px dashed var(--accent); }
.grid { display:grid; grid-template-columns: 1fr 1fr; gap:.75rem; }
.field label { display:block; font-size:.85rem; font-weight:600; margin-bottom:.25rem; }
.field input, .field select { width:100%; padding:.6rem .7rem; border:1px solid var(--border); border-radius:10px; font:inherit; color: var(--text); background:#fff; }
.field input:focus, .field select:focus { outline:2px solid var(--primary); border-color: var(--primary); }
.sig-wrap { border:2px dashed var(--primary); border-radius:12px; background:#fff; position:relative; touch-action:none; }
canvas#firma { width:100%; height:200px; display:block; border-radius:10px; cursor:crosshair; }
.sig-hint { position:absolute; left:0; right:0; bottom:.5rem; text-align:center; color: var(--muted); font-size:.85rem; pointer-events:none; }
.sig-tools { display:flex; justify-content:space-between; align-items:center; margin-top:.5rem; font-size:.85rem; color: var(--muted); }
.btn { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; padding:.7rem 1.1rem; border-radius:999px; border:0; font:inherit; font-weight:700; cursor:pointer; text-decoration:none; }
.btn-cta { background: var(--cta); color:#fff; font-size:1.05rem; width:100%; }
.btn-cta:hover { background: var(--cta-dark); }
.btn-cta:disabled { opacity:.5; cursor:not-allowed; }
.btn-ghost { background: var(--primary-soft); color: var(--primary-dark); padding:.45rem .8rem; font-size:.85rem; }
.alert { border-radius:12px; padding:.75rem 1rem; margin:.75rem 0; font-size:.93rem; }
.alert-error { background: var(--danger-soft); color:#7f1d1d; }
.alert-ok { background: var(--success-soft); color:#14532d; }
.alert ul { margin:.25rem 0 0 1rem; padding:0; }
.err { color: var(--danger); font-size:.82rem; margin-top:.2rem; }
.muted { color: var(--muted); font-size:.85rem; }
.receipt { word-break: break-all; font-family: Consolas, monospace; font-size:.85rem; background:#faf7ff; padding:.6rem; border-radius:8px; }
.honey { position:absolute; left:-9999px; }
@media (max-width: 640px) { .steps, .grid { grid-template-columns: 1fr; } .wrap { padding: .75rem .75rem 3rem; } }
@media print { .no-print { display:none !important; } .doc-body { max-height:none; overflow:visible; } details.doc { display:block; } details.doc:not([open]) .doc-body { display:block; } .card { box-shadow:none; } }
</style>
</head>
<body>
<div class="wrap">
  <header class="top">
    <img src="brand/cumpleclick-mark.svg" alt="CumpleClick">
    <h1>CumpleClick <span>Aceptación de Términos y firma electrónica</span></h1>
  </header>

  <?php if ($done): ?>
    <section class="card">
      <h2>¡Listo! Tu aceptación quedó registrada</h2>
      <p class="alert alert-ok">Gracias, <?= h($done['row']['signer_name']) ?>. Registramos tu aceptación el <?= h(cb_chile_datetime((string) $done['row']['accepted_at'])) ?>.</p>
      <p>Guarda tu copia del comprobante firmado (contiene el texto íntegro, tu firma y la huella digital del documento):</p>
      <p><a class="btn btn-ghost" href="<?= h($done['receipt_url']) ?>" target="_blank" rel="noopener">Ver o descargar mi comprobante</a></p>
      <p class="receipt"><?= h($done['receipt_url']) ?></p>
      <p class="muted">Huella SHA-256 del comprobante: <code><?= h($done['row']['evidence_sha256']) ?></code></p>
      <?php if ($done['mails']['client']): ?>
        <p class="muted">Te enviamos una copia a <strong><?= h($done['row']['signer_email']) ?></strong>. Si no llega, revisa la carpeta de spam.</p>
      <?php else: ?>
        <p class="muted">No pudimos enviarte el correo automático; guarda el enlace de arriba. CumpleClick también conserva tu comprobante.</p>
      <?php endif; ?>
      <p>Recuerda: la fecha queda reservada al recibir el anticipo indicado en el Resumen del Plan. Nos vemos en la fiesta.</p>
    </section>
  <?php else: ?>

    <section class="card">
      <h2>Hola, <?= h($acceptance['client_name']) ?></h2>
      <p>Antes de confirmar tu reserva del <strong><?= h($summary['plan_name'] ?? 'plan') ?></strong><?= !empty($summary['event_date']) ? ' para el <strong>' . h($summary['event_date']) . '</strong>' : '' ?>, necesitamos que revises y aceptes los documentos del servicio. Toma unos 5 minutos.</p>
      <ol class="steps">
        <li><strong>1. Revisa el plan</strong>Confirma que lo acordado está correcto.</li>
        <li><strong>2. Lee los documentos</strong>Términos, privacidad y consentimiento de imagen.</li>
        <li><strong>3. Acepta y firma</strong>Marca las casillas y firma con el dedo o el mouse.</li>
      </ol>
      <p class="muted no-print">Puedes <button type="button" class="btn btn-ghost" onclick="window.print()">imprimir o guardar en PDF</button> esta página antes de aceptar. Este enlace vence el <?= h(cb_chile_datetime((string) $acceptance['expires_at'])) ?>.</p>
    </section>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error" role="alert">
        <strong>Revisa lo siguiente:</strong>
        <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <section class="card">
      <h2>1. Resumen del Plan</h2>
      <table class="summary">
        <?php foreach ($summaryLabels as $key => $label): ?>
          <?php if (!empty($summary[$key])): ?>
            <tr><th><?= h($label) ?></th><td><?= nl2br(h($summary[$key])) ?></td></tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </table>
      <p class="muted">Si algo no coincide con lo conversado, no firmes y escríbenos antes.</p>
    </section>

    <form method="post" action="aceptar-plan.php?t=<?= h($token) ?>" id="form-aceptacion" novalidate>
      <input type="hidden" name="nonce" value="<?= h($nonce) ?>">
      <input type="hidden" name="signature_dataurl" id="signature_dataurl" value="">
      <input type="hidden" name="meta_tz" id="meta_tz" value="">
      <input type="hidden" name="meta_screen" id="meta_screen" value="">
      <input type="hidden" name="meta_lang" id="meta_lang" value="">
      <input type="hidden" name="meta_touch" id="meta_touch" value="">
      <div class="honey" aria-hidden="true"><label>Sitio web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

      <section class="card">
        <h2>2. Documentos del servicio (versión <?= h($bundle['version']) ?>)</h2>
        <p class="muted">Abre cada documento para leerlo completo. Debes aceptar los tres para continuar; la autorización de marketing es opcional.</p>
        <?php $first = true; foreach ($bundle['documents'] as $key => $doc): ?>
          <details class="doc" <?= $first ? 'open' : '' ?>>
            <summary><?= h($doc['title']) ?></summary>
            <div class="doc-body"><?= $doc['html'] ?></div>
          </details>
          <label class="check">
            <input type="checkbox" name="<?= h($doc['checkbox']) ?>" value="1" required <?= ($_POST[$doc['checkbox']] ?? '') === '1' ? 'checked' : '' ?>>
            <span><?= h($doc['label']) ?><?php if (isset($errors[$doc['checkbox']])): ?><span class="err"><?= h($errors[$doc['checkbox']]) ?></span><?php endif; ?></span>
          </label>
          <?php $first = false; endforeach; ?>
        <label class="check optional">
          <input type="checkbox" name="accepted_marketing" value="1" <?= $values['accepted_marketing'] ? 'checked' : '' ?>>
          <span><?= h(cb_legal_marketing_label()) ?></span>
        </label>
      </section>

      <section class="card">
        <h2>3. Tus datos y firma</h2>
        <div class="grid">
          <div class="field">
            <label for="signer_name">Nombre completo (como en tu cédula)</label>
            <input type="text" id="signer_name" name="signer_name" value="<?= h($values['signer_name']) ?>" autocomplete="name" required maxlength="160">
            <?php if (isset($errors['signer_name'])): ?><div class="err"><?= h($errors['signer_name']) ?></div><?php endif; ?>
          </div>
          <div class="field">
            <label for="signer_rut">RUT</label>
            <input type="text" id="signer_rut" name="signer_rut" value="<?= h($values['signer_rut']) ?>" placeholder="12.345.678-5" inputmode="text" required maxlength="20">
            <?php if (isset($errors['signer_rut'])): ?><div class="err"><?= h($errors['signer_rut']) ?></div><?php endif; ?>
          </div>
          <div class="field">
            <label for="signer_email">Correo para enviarte el comprobante</label>
            <input type="email" id="signer_email" name="signer_email" value="<?= h($values['signer_email']) ?>" autocomplete="email" required maxlength="254">
            <?php if (isset($errors['signer_email'])): ?><div class="err"><?= h($errors['signer_email']) ?></div><?php endif; ?>
          </div>
          <div class="field">
            <label for="signer_relationship">Relación con el niño o niña homenajeado</label>
            <select id="signer_relationship" name="signer_relationship" required>
              <option value="">Selecciona…</option>
              <?php foreach ($relationships as $value => $label): ?>
                <option value="<?= h($value) ?>" <?= $values['signer_relationship'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['signer_relationship'])): ?><div class="err"><?= h($errors['signer_relationship']) ?></div><?php endif; ?>
          </div>
        </div>

        <p style="margin:1rem 0 .4rem"><strong>Firma aquí</strong> <span class="muted">(con el dedo en el celular o con el mouse)</span></p>
        <div class="sig-wrap">
          <canvas id="firma" aria-label="Recuadro para dibujar tu firma"></canvas>
          <div class="sig-hint" id="sig-hint">Dibuja tu firma dentro del recuadro</div>
        </div>
        <div class="sig-tools">
          <span>Firma electrónica simple (Ley 19.799). Se guardará junto con la fecha, hora e IP.</span>
          <button type="button" class="btn btn-ghost" id="sig-clear">Borrar</button>
        </div>
        <?php if (isset($errors['signature'])): ?><div class="err"><?= h($errors['signature']) ?></div><?php endif; ?>

        <p style="margin-top:1.25rem">
          <button type="submit" class="btn btn-cta" id="btn-enviar">Acepto y firmo</button>
        </p>
        <p class="muted">Al presionar "Acepto y firmo" quedará registrada tu aceptación de los documentos marcados, con fecha y hora, dirección IP, navegador y la huella digital del texto aceptado. Recibirás una copia por correo.</p>
      </section>
    </form>
  <?php endif; ?>

  <footer class="muted" style="text-align:center;margin-top:2rem">CumpleClick · AutomatizaTech · Este enlace es personal, no lo compartas.</footer>
</div>

<?php if (!$done): ?>
<script>
(function () {
  var canvas = document.getElementById('firma');
  var hint = document.getElementById('sig-hint');
  var hidden = document.getElementById('signature_dataurl');
  var form = document.getElementById('form-aceptacion');
  var clearBtn = document.getElementById('sig-clear');
  if (!canvas || !canvas.getContext) { return; }
  var ctx = canvas.getContext('2d');
  var drawing = false, strokes = 0, last = null;

  function resize() {
    var ratio = Math.max(1, window.devicePixelRatio || 1);
    var rect = canvas.getBoundingClientRect();
    var snapshot = strokes > 0 ? canvas.toDataURL('image/png') : null;
    canvas.width = Math.round(rect.width * ratio);
    canvas.height = Math.round(rect.height * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineWidth = 2.6; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#1f1235';
    if (snapshot) { var img = new Image(); img.onload = function () { ctx.drawImage(img, 0, 0, rect.width, rect.height); }; img.src = snapshot; }
  }
  function pos(ev) {
    var rect = canvas.getBoundingClientRect();
    return { x: ev.clientX - rect.left, y: ev.clientY - rect.top };
  }
  function start(ev) { if (ev.button !== undefined && ev.button !== 0) { return; } drawing = true; last = pos(ev); canvas.setPointerCapture && canvas.setPointerCapture(ev.pointerId); ev.preventDefault(); }
  function move(ev) {
    if (!drawing) { return; }
    var p = pos(ev);
    ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
    last = p; strokes++;
    if (hint) { hint.style.display = 'none'; }
    ev.preventDefault();
  }
  function end(ev) { if (!drawing) { return; } drawing = false; last = null; ev.preventDefault(); }
  canvas.addEventListener('pointerdown', start);
  canvas.addEventListener('pointermove', move);
  canvas.addEventListener('pointerup', end);
  canvas.addEventListener('pointercancel', end);
  canvas.addEventListener('pointerleave', end);
  window.addEventListener('resize', resize);
  resize();

  clearBtn.addEventListener('click', function () {
    ctx.save(); ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.clearRect(0, 0, canvas.width, canvas.height); ctx.restore();
    strokes = 0; hidden.value = ''; if (hint) { hint.style.display = ''; }
  });

  document.getElementById('meta_tz').value = (Intl && Intl.DateTimeFormat) ? (Intl.DateTimeFormat().resolvedOptions().timeZone || '') : '';
  document.getElementById('meta_screen').value = (window.screen ? screen.width + 'x' + screen.height : '') + ' @' + (window.devicePixelRatio || 1);
  document.getElementById('meta_lang').value = navigator.language || '';
  document.getElementById('meta_touch').value = ('ontouchstart' in window || navigator.maxTouchPoints > 0) ? 'si' : 'no';

  form.addEventListener('submit', function (ev) {
    var missing = [];
    Array.prototype.forEach.call(form.querySelectorAll('input[type="checkbox"][required]'), function (c) { if (!c.checked) { missing.push(c); } });
    if (missing.length) { ev.preventDefault(); alert('Debes aceptar los tres documentos obligatorios para continuar.'); missing[0].focus(); return; }
    if (strokes < 8) { ev.preventDefault(); alert('Dibuja tu firma en el recuadro antes de enviar.'); canvas.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
    // Exporta la firma a resolución fija para que el archivo sea estable entre dispositivos.
    var out = document.createElement('canvas'); out.width = 900; out.height = 300;
    var octx = out.getContext('2d'); octx.drawImage(canvas, 0, 0, canvas.width, canvas.height, 0, 0, out.width, out.height);
    hidden.value = out.toDataURL('image/png');
    document.getElementById('btn-enviar').disabled = true;
  });
})();
</script>
<?php endif; ?>
</body>
</html>
