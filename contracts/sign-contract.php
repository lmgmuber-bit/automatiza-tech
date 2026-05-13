<?php
/**
 * Página PÚBLICA donde el cliente firma el contrato.
 * URL: /contracts/sign-contract.php?token=SIGN_TOKEN
 * No requiere login. La autenticación es por token de 64 chars hex (sign_token).
 */

require_once dirname(__DIR__) . '/wp-load.php';
require_once __DIR__ . '/contract-service.php';
require_once dirname(__DIR__) . '/at-rate-limit.php';

// Rate limit: 10 intentos/hora por IP (anti brute-force sobre tokens de 64hex)
if ( ! at_rate_limit_check( 'sign_contract_token', 10, 3600 ) ) {
    status_header( 429 );
    echo 'Demasiados intentos. Por favor espera antes de volver a intentarlo.';
    exit;
}

$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
$c = ContractService::get_by_token($token);
if (!$c) { status_header(404); echo 'Enlace de firma no válido o expirado.'; exit; }

if (!in_array($c->status, array('sent','viewed','at_signed','signed'))) {
    status_header(403); echo 'Este contrato no está disponible para firma.'; exit;
}
ContractService::register_view($token);
$c = ContractService::get_by_token($token);

$flash = '';
$flash_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sign') {
    $data = array(
        'signer_name'  => sanitize_text_field($_POST['signer_name']  ?? ''),
        'signer_rut'   => sanitize_text_field($_POST['signer_rut']   ?? ''),
        'signer_email' => sanitize_email   ($_POST['signer_email']   ?? ''),
        'method'       => sanitize_text_field($_POST['method']       ?? 'canvas'),
    );
    if (!empty($_POST['signature_dataurl'])) $data['signature_dataurl'] = wp_unslash($_POST['signature_dataurl']);
    if (!empty($_FILES['signature_file']['tmp_name'])) {
        $data['signature_file'] = $_FILES['signature_file'];
        $data['method'] = 'image_upload';
    }
    if (empty($_POST['accept_terms'])) {
        $flash = 'Debes aceptar los términos del contrato para firmarlo.';
        $flash_type = 'error';
    } else {
        $r = ContractService::sign_as_client($token, $data);
        if (is_wp_error($r)) { $flash = $r->get_error_message(); $flash_type = 'error'; }
        else {
            $c = $r;
            // E4: token rotated on signing — use the new token from the returned contract for download links
            $token = $c->sign_token;
            $flash = '¡Contrato firmado correctamente! Recibirás una copia en tu correo.';
            $flash_type = 'ok';
        }
    }
}

$ph = json_decode($c->placeholders, true) ?: array();
// A2.3: serve PDF via protected AJAX handler using sign_token (no direct uploads URL)
$pdf_url    = ContractService::secure_pdf_url( $c, false, $token );
$signed_url = ContractService::secure_pdf_url( $c, true,  $token );
$is_signed  = $c->status === 'signed';
$logo = home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png');

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Firma de contrato · AutomatizaTech</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--brand:#004AAD;--accent:#0096C7;--ok:#1c7c40;--err:#b71c1c;--bg:#eef2f7;--card:#fff;--text:#21303d;--gray:#7a8694;}
*{box-sizing:border-box;font-family:Arial,sans-serif}
body{margin:0;background:var(--bg);color:var(--text)}
.topbar{background:linear-gradient(135deg,var(--brand),var(--accent));padding:18px 0;text-align:center}
.topbar img{height:38px}
.wrap{max-width:1200px;margin:24px auto;padding:0 16px}
.card{background:var(--card);border-radius:12px;box-shadow:0 4px 18px rgba(0,0,0,.06);padding:24px;margin-bottom:20px}
h1{margin:0 0 4px 0;color:var(--brand);font-size:22px}
.muted{color:var(--gray);font-size:13px}
.grid{display:grid;grid-template-columns:1.5fr 1fr;gap:20px}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
iframe{width:100%;height:760px;border:1px solid #e5e9f0;border-radius:8px}
.flash{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px}
.flash.ok{background:#dcf8e6;color:var(--ok)} .flash.error{background:#fde0e0;color:var(--err)}
label{display:block;font-size:12px;font-weight:600;color:var(--gray);margin:10px 0 4px;text-transform:uppercase;letter-spacing:.5px}
input[type=text],input[type=email]{width:100%;padding:10px 12px;border:1px solid #d6dde6;border-radius:6px;font-size:14px}
button{background:var(--brand);color:#fff;border:0;padding:14px 24px;border-radius:8px;font-weight:600;cursor:pointer;font-size:15px;width:100%}
.tab-buttons{display:flex;gap:8px;margin:8px 0 12px}
.tab-buttons button{background:#eef2f7;color:var(--text);padding:8px 14px;font-size:12px;border-radius:6px;width:auto}
.tab-buttons button.active{background:var(--brand);color:#fff}
canvas{border:2px dashed #c7d3e0;border-radius:8px;background:#fafbfd;width:100%;height:180px;display:block}
.row{display:flex;gap:8px;align-items:center;justify-content:space-between;margin-top:8px}
.checkbox-row{display:flex;align-items:flex-start;gap:8px;margin:14px 0;font-size:13px}
.success-box{text-align:center;padding:30px}
.success-box .check{font-size:60px;color:var(--ok);margin-bottom:10px}
hr{border:0;border-top:1px solid #e5e9f0;margin:14px 0}
.legal{font-size:11px;color:var(--gray);line-height:1.5;margin-top:14px}
</style>
</head>
<body>
<div class="topbar"><img src="<?= esc_url($logo) ?>" alt="AutomatizaTech"></div>
<div class="wrap">

  <div class="card">
    <h1>Contrato listo para tu firma</h1>
    <div class="muted">
      <?= esc_html($c->contract_number) ?> · <?= esc_html($ph['razon_social_cliente'] ?? '') ?> · <?= esc_html($ph['nombre_proyecto'] ?? '') ?>
    </div>
    <?php if ($flash): ?><div class="flash <?= esc_attr($flash_type) ?>" style="margin-top:14px"><?= esc_html($flash) ?></div><?php endif; ?>
  </div>

  <?php if ($is_signed): ?>
    <div class="card success-box">
      <div class="check">✓</div>
      <h2 style="color:var(--ok);margin:0 0 6px 0">¡Contrato firmado correctamente!</h2>
      <p>Hemos enviado una copia del contrato firmado a <strong><?= esc_html($c->signer_email) ?></strong>.</p>
      <p class="muted">También quedará disponible en tu ficha del Portal AutomatizaTech.</p>
      <p style="margin-top:24px"><a href="<?= esc_url($signed_url) ?>" target="_blank"><button>⬇️ Descargar contrato firmado (PDF)</button></a></p>
    </div>
  <?php else: ?>
    <div class="grid">
      <div class="card">
        <h3 style="margin:0 0 10px 0">📄 Lee el contrato</h3>
        <iframe src="<?= esc_url($pdf_url) ?>?v=<?= time() ?>"></iframe>
        <p class="muted" style="margin-top:8px">Tómate el tiempo necesario para revisarlo. Puedes descargarlo desde el visor.</p>
      </div>
      <div class="card">
        <h3 style="margin:0 0 6px 0">🖋️ Firma el contrato</h3>
        <p class="muted" style="margin:0 0 12px 0">Completa tus datos y firma dibujando o subiendo una imagen.</p>

        <form method="post" enctype="multipart/form-data" id="signForm">
          <input type="hidden" name="action" value="sign">
          <input type="hidden" name="signature_dataurl" id="sig_dataurl">
          <input type="hidden" name="method" id="sig_method" value="canvas">

          <label>Nombre completo del firmante</label>
          <input type="text" name="signer_name" required value="<?= esc_attr($ph['representante_cliente_nombre'] ?? '') ?>">

          <label>RUT</label>
          <input type="text" name="signer_rut" required placeholder="12.345.678-9" value="<?= esc_attr($ph['representante_cliente_rut'] ?? '') ?>">

          <label>Email</label>
          <input type="email" name="signer_email" required value="<?= esc_attr($ph['email_cliente'] ?? '') ?>">

          <label>Firma</label>
          <div class="tab-buttons">
            <button type="button" class="active" data-tab="draw">✍️ Dibujar</button>
            <button type="button" data-tab="upload">📎 Subir imagen</button>
          </div>

          <div id="tab-draw">
            <canvas id="sigCanvas"></canvas>
            <div class="row">
              <span class="muted">Dibuja tu firma con el mouse o el dedo</span>
              <button type="button" id="clearSig" style="background:var(--accent);padding:6px 12px;font-size:12px;width:auto">Limpiar</button>
            </div>
          </div>
          <div id="tab-upload" style="display:none">
            <input type="file" name="signature_file" accept="image/png,image/jpeg">
            <p class="muted" style="margin-top:6px">PNG o JPG, máx 2MB. Idealmente fondo blanco/transparente.</p>
          </div>

          <div class="checkbox-row">
            <input type="checkbox" name="accept_terms" id="accept" value="1" required>
            <label for="accept" style="text-transform:none;letter-spacing:0;color:var(--text);font-weight:400;margin:0">
              He leído y acepto los términos y condiciones del contrato. Mi firma electrónica tiene la misma validez que una firma manuscrita conforme a la Ley 19.799.
            </label>
          </div>

          <hr>
          <button type="submit">✅ Firmar contrato</button>

          <p class="legal">
            Tu firma quedará registrada con tu nombre, RUT, email, dirección IP, fecha/hora, navegador y un código hash SHA-256 que garantiza la integridad del documento.
            Recibirás una copia firmada por correo y quedará disponible en el Portal AutomatizaTech.
          </p>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <p class="muted" style="text-align:center;margin-top:30px">
    ¿Dudas? Escríbenos a <a href="mailto:contacto@automatizatech.cl">contacto@automatizatech.cl</a><br>
    © <?= date('Y') ?> AutomatizaTech SpA
  </p>
</div>

<script>
(function(){
  const canvas = document.getElementById('sigCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  function resize(){ const r=canvas.getBoundingClientRect(); canvas.width=r.width; canvas.height=r.height; ctx.lineWidth=2; ctx.lineCap='round'; ctx.strokeStyle='#0a1f3d'; }
  resize(); window.addEventListener('resize', resize);
  let drawing=false, hasDrawn=false;
  function pos(e){ const r=canvas.getBoundingClientRect(); const t=e.touches?e.touches[0]:e; return {x:t.clientX-r.left, y:t.clientY-r.top}; }
  function start(e){drawing=true; const p=pos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); e.preventDefault();}
  function move(e){ if(!drawing) return; const p=pos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); hasDrawn=true; e.preventDefault();}
  function end(){drawing=false;}
  ['mousedown','touchstart'].forEach(ev=>canvas.addEventListener(ev,start));
  ['mousemove','touchmove'].forEach(ev=>canvas.addEventListener(ev,move));
  ['mouseup','mouseleave','touchend'].forEach(ev=>canvas.addEventListener(ev,end));
  document.getElementById('clearSig').onclick=()=>{ctx.clearRect(0,0,canvas.width,canvas.height); hasDrawn=false;};

  const tabs=document.querySelectorAll('.tab-buttons button');
  tabs.forEach(b=>b.onclick=()=>{
    tabs.forEach(x=>x.classList.remove('active')); b.classList.add('active');
    const t=b.dataset.tab;
    document.getElementById('tab-draw').style.display=t==='draw'?'':'none';
    document.getElementById('tab-upload').style.display=t==='upload'?'':'none';
    document.getElementById('sig_method').value=t==='draw'?'canvas':'image_upload';
  });

  document.getElementById('signForm').addEventListener('submit', function(e){
    if (document.getElementById('sig_method').value === 'canvas') {
      if (!hasDrawn) { e.preventDefault(); alert('Por favor dibuja tu firma o cambia a "Subir imagen".'); return; }
      document.getElementById('sig_dataurl').value = canvas.toDataURL('image/png');
    }
  });
})();
</script>
</body>
</html>
