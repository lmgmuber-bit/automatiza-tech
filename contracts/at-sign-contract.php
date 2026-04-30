<?php
/**
 * Página INTERNA de revisión y firma del contrato por el representante AutomatizaTech.
 * Requiere usuario WP autenticado con capability 'edit_posts'.
 *
 * URL: /contracts/at-sign-contract.php?token=AT_REVIEW_TOKEN
 *
 * Flujo:
 *   1. Muestra el contrato (preview)
 *   2. Permite firmar (canvas o subir imagen)
 *   3. Al firmar, status = at_signed y se ofrece botón "Enviar al cliente"
 */

require_once dirname(__DIR__) . '/wp-load.php';
require_once __DIR__ . '/contract-service.php';

if (!is_user_logged_in() || !current_user_can('edit_posts')) {
    auth_redirect();
    exit;
}

$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
$c     = ContractService::get_by_at_token($token);
if (!$c) { status_header(404); echo 'Contrato no encontrado'; exit; }

$flash = '';
$flash_type = '';

// POST: firmar como AT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['_at_nonce']) || !wp_verify_nonce($_POST['_at_nonce'], 'at_sign_' . $c->id)) {
        wp_die('Nonce inválido');
    }
    if ($_POST['action'] === 'sign') {
        $data = array(
            'signer_name'  => sanitize_text_field($_POST['signer_name']  ?? ''),
            'signer_rut'   => sanitize_text_field($_POST['signer_rut']   ?? ''),
            'signer_email' => sanitize_email   ($_POST['signer_email']   ?? ''),
            'method'       => sanitize_text_field($_POST['method']       ?? 'canvas'),
        );
        if (!empty($_POST['signature_dataurl'])) {
            $data['signature_dataurl'] = wp_unslash($_POST['signature_dataurl']);
        }
        if (!empty($_FILES['signature_file']['tmp_name'])) {
            $data['signature_file'] = $_FILES['signature_file'];
            $data['method'] = 'image_upload';
        }
        $r = ContractService::sign_as_at($c->id, $data);
        if (is_wp_error($r)) { $flash = $r->get_error_message(); $flash_type = 'error'; }
        else { $flash = 'Firmaste el contrato. Ya puedes enviarlo al cliente.'; $flash_type = 'ok'; $c = $r; }
    } elseif ($_POST['action'] === 'send') {
        $email = sanitize_email($_POST['client_email'] ?? '');
        $name  = sanitize_text_field($_POST['client_name'] ?? '');
        $r = ContractService::send_for_client_signature($c->id, $email ?: null, $name ?: null);
        if (is_wp_error($r)) { $flash = $r->get_error_message(); $flash_type = 'error'; }
        else { $flash = 'Contrato enviado al cliente correctamente.'; $flash_type = 'ok'; $c = ContractService::get_by_id($c->id); }
    }
}

$ph = json_decode($c->placeholders, true) ?: array();
$pdf_url = $c->signed_pdf_url ?: $c->pdf_url;

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Revisión interna · <?= esc_html($c->contract_number) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--brand:#004AAD;--accent:#0096C7;--ok:#1c7c40;--err:#b71c1c;--bg:#eef2f7;--card:#fff;--text:#21303d;--gray:#7a8694;}
*{box-sizing:border-box;font-family:Arial,sans-serif}
body{margin:0;background:var(--bg);color:var(--text)}
.wrap{max-width:1200px;margin:24px auto;padding:0 16px}
.card{background:var(--card);border-radius:12px;box-shadow:0 4px 18px rgba(0,0,0,.06);padding:24px;margin-bottom:20px}
h1{margin:0 0 4px 0;color:var(--brand);font-size:22px}
.muted{color:var(--gray);font-size:13px}
.grid{display:grid;grid-template-columns:1.5fr 1fr;gap:20px}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
iframe{width:100%;height:780px;border:1px solid #e5e9f0;border-radius:8px}
.flash{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px}
.flash.ok{background:#dcf8e6;color:var(--ok)}
.flash.error{background:#fde0e0;color:var(--err)}
.badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;text-transform:uppercase}
.b-draft,.b-at_pending{background:#fff4dc;color:#b46400}
.b-at_signed{background:#dde8f9;color:var(--brand)}
.b-sent,.b-viewed{background:#e0f0fa;color:var(--accent)}
.b-signed{background:#dcf8e6;color:var(--ok)}
label{display:block;font-size:12px;font-weight:600;color:var(--gray);margin:10px 0 4px;text-transform:uppercase;letter-spacing:.5px}
input[type=text],input[type=email]{width:100%;padding:10px 12px;border:1px solid #d6dde6;border-radius:6px;font-size:14px}
button{background:var(--brand);color:#fff;border:0;padding:12px 22px;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px}
button.secondary{background:var(--accent)}
button:disabled{opacity:.5;cursor:not-allowed}
.tab-buttons{display:flex;gap:8px;margin:8px 0 12px}
.tab-buttons button{background:#eef2f7;color:var(--text);padding:8px 14px;font-size:12px;border-radius:6px}
.tab-buttons button.active{background:var(--brand);color:#fff}
canvas{border:2px dashed #c7d3e0;border-radius:8px;background:#fafbfd;width:100%;height:180px;display:block}
.row{display:flex;gap:8px;align-items:center;justify-content:space-between;margin-top:8px}
.dl{font-size:13px}.dl b{color:var(--gray);font-weight:600;display:inline-block;min-width:120px}
hr{border:0;border-top:1px solid #e5e9f0;margin:16px 0}
</style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <div>
        <h1>Revisión interna · <?= esc_html($c->contract_number) ?></h1>
        <div class="muted"><?= esc_html($ph['razon_social_cliente'] ?? '') ?> · <?= esc_html($ph['nombre_proyecto'] ?? '') ?></div>
      </div>
      <span class="badge b-<?= esc_attr($c->status) ?>"><?= esc_html($c->status) ?></span>
    </div>

    <?php if ($flash): ?><div class="flash <?= esc_attr($flash_type) ?>" style="margin-top:14px"><?= esc_html($flash) ?></div><?php endif; ?>
  </div>

  <div class="grid">
    <div class="card">
      <h3 style="margin:0 0 10px 0">📄 Vista previa del contrato</h3>
      <iframe src="<?= esc_url($pdf_url) ?>?v=<?= time() ?>"></iframe>
      <p class="muted" style="margin-top:8px">Si modificas placeholders o vuelves a firmar, el PDF se regenera automáticamente.</p>
    </div>

    <div class="card">

      <?php if (in_array($c->status, array('draft','at_pending'))): ?>
        <h3 style="margin:0 0 6px 0">🖋️ Firmar como representante AT</h3>
        <p class="muted" style="margin:0 0 12px 0">Tu firma se aplicará al contrato y luego podrás enviarlo al cliente.</p>

        <form method="post" enctype="multipart/form-data" id="signForm">
          <?php wp_nonce_field('at_sign_' . $c->id, '_at_nonce'); ?>
          <input type="hidden" name="action" value="sign">
          <input type="hidden" name="signature_dataurl" id="sig_dataurl">
          <input type="hidden" name="method" id="sig_method" value="canvas">

          <label>Nombre del representante</label>
          <input type="text" name="signer_name" required value="<?= esc_attr($ph['representante_at_nombre'] ?? wp_get_current_user()->display_name) ?>">

          <label>RUT</label>
          <input type="text" name="signer_rut" required value="<?= esc_attr($ph['representante_at_rut'] ?? '') ?>">

          <label>Email</label>
          <input type="email" name="signer_email" required value="<?= esc_attr(wp_get_current_user()->user_email) ?>">

          <label>Firma</label>
          <div class="tab-buttons">
            <button type="button" class="active" data-tab="draw">✍️ Dibujar</button>
            <button type="button" data-tab="upload">📎 Subir imagen</button>
          </div>

          <div id="tab-draw">
            <canvas id="sigCanvas"></canvas>
            <div class="row">
              <span class="muted">Dibuja con el mouse o pantalla táctil</span>
              <button type="button" id="clearSig" class="secondary" style="padding:6px 12px;font-size:12px">Limpiar</button>
            </div>
          </div>
          <div id="tab-upload" style="display:none">
            <input type="file" name="signature_file" accept="image/png,image/jpeg">
            <p class="muted" style="margin-top:6px">PNG o JPG, máx 2MB. Idealmente fondo transparente.</p>
          </div>

          <hr>
          <button type="submit">✅ Firmar contrato como AT</button>
        </form>

      <?php elseif ($c->status === 'at_signed'): ?>
        <h3 style="margin:0 0 6px 0">📤 Enviar al cliente</h3>
        <p class="muted" style="margin:0 0 12px 0">Tu firma ya quedó registrada. Confirma los datos del cliente y envía el contrato.</p>

        <div class="dl" style="margin-bottom:12px">
          <p><b>Firmaste:</b> <?= esc_html($c->at_signer_name) ?></p>
          <p><b>Fecha:</b> <?= esc_html($c->at_signed_at) ?></p>
          <p><b>IP:</b> <?= esc_html($c->at_signer_ip) ?></p>
          <p><b>Método:</b> <?= esc_html($c->at_signature_method) ?></p>
        </div>

        <form method="post">
          <?php wp_nonce_field('at_sign_' . $c->id, '_at_nonce'); ?>
          <input type="hidden" name="action" value="send">

          <label>Email del cliente</label>
          <input type="email" name="client_email" required value="<?= esc_attr($ph['email_cliente'] ?? '') ?>">

          <label>Nombre del cliente / representante</label>
          <input type="text" name="client_name" required value="<?= esc_attr($ph['representante_cliente_nombre'] ?? ($ph['razon_social_cliente'] ?? '')) ?>">

          <hr>
          <button type="submit">📨 Enviar al cliente para firma</button>
        </form>

      <?php else: ?>
        <h3 style="margin:0 0 6px 0">Estado: <?= esc_html($c->status) ?></h3>
        <div class="dl" style="margin-top:10px">
          <?php if ($c->sent_at): ?><p><b>Enviado:</b> <?= esc_html($c->sent_at) ?></p><?php endif; ?>
          <?php if ($c->viewed_at): ?><p><b>Visto por cliente:</b> <?= esc_html($c->viewed_at) ?></p><?php endif; ?>
          <?php if ($c->signed_at): ?>
            <p><b>Firmado por cliente:</b> <?= esc_html($c->signed_at) ?></p>
            <p><b>Firmante:</b> <?= esc_html($c->signer_name) ?> · RUT <?= esc_html($c->signer_rut) ?></p>
            <p><b>Email:</b> <?= esc_html($c->signer_email) ?></p>
            <p><b>IP:</b> <?= esc_html($c->signer_ip) ?></p>
            <p><b>Hash final:</b> <code style="font-size:10px"><?= esc_html(substr($c->signed_document_hash, 0, 32)) ?>…</code></p>
          <?php endif; ?>
        </div>
        <hr>
        <a href="<?= esc_url($pdf_url) ?>" target="_blank"><button type="button">⬇️ Descargar PDF</button></a>
      <?php endif; ?>

    </div>
  </div>
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
      if (!hasDrawn) { e.preventDefault(); alert('Por favor dibuja tu firma'); return; }
      document.getElementById('sig_dataurl').value = canvas.toDataURL('image/png');
    }
  });
})();
</script>
</body>
</html>
