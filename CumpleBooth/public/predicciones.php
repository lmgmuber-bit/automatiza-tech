<?php
/** Tablero privado de predicciones para los papás. */
require __DIR__ . '/lib.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$token = isset($_GET['t']) ? (string) $_GET['t'] : '';
$access = null;
$predictions = [];
$theme = [];
$invalid = false;

try {
    $access = cb_invitation_resolve_role_token($token, 'parents');
    if ($access === null) {
        $invalid = true;
    } else {
        $predictions = cb_prediction_list_for_party((int) $access['party_id']);
        $themes = cb_load_themes();
        $theme = is_array($themes['themes'][$access['theme_slug']] ?? null)
            ? $themes['themes'][$access['theme_slug']]
            : [];
    }
} catch (Throwable $e) {
    error_log('CumpleClick predictions board: ' . $e->getMessage());
    $invalid = true;
}

$eventName = trim((string) ($access['admin_label'] ?? ''));
if ($eventName === '') {
    $eventName = trim((string) ($access['birthday_person_name'] ?? 'Baby shower'));
}
$themeSlug = (string) ($access['theme_slug'] ?? '');
$themeVars = $themeSlug !== '' ? cb_theme_css_vars($themeSlug) : '';
$counts = ['mama' => 0, 'papa' => 0, 'ambos' => 0];
foreach ($predictions as $prediction) {
    $key = (string) ($prediction['parecido'] ?? '');
    if (isset($counts[$key])) {
        $counts[$key]++;
    }
}
?>
<!doctype html>
<html lang="es-CL">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title><?= $invalid ? 'Enlace no disponible' : 'Predicciones · ' . $escape($eventName) ?> · CumpleClick</title>
  <style>
    :root{<?= $themeVars ?>--accent:var(--primary,#8c5de8);--accent-2:var(--secondary,#f0a9c8);--ink:var(--dark,#252039);--paper:#fffdfb;--muted:#706b7c}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;color:var(--ink);font-family:"Baloo 2","Trebuchet MS",sans-serif;background:linear-gradient(150deg,color-mix(in srgb,var(--accent) 13%,#fff) 0%,#fff8f1 44%,color-mix(in srgb,var(--accent-2) 18%,#fff) 100%)}
    body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.42;background:radial-gradient(circle at 10% 10%,#fff 0 3px,transparent 4px),radial-gradient(circle at 82% 24%,#fff 0 5px,transparent 6px);background-size:72px 72px,118px 118px}
    main{position:relative;width:min(1080px,calc(100% - 28px));margin:0 auto;padding:34px 0 70px}.brand{display:flex;align-items:center;gap:10px;font-weight:800;letter-spacing:.02em}.brand-mark{width:30px;height:30px;border:6px solid var(--accent);border-radius:50%;box-shadow:inset 0 0 0 4px var(--accent-2)}
    .hero{margin:24px 0 18px;padding:clamp(24px,6vw,56px);border-radius:32px;background:linear-gradient(135deg,color-mix(in srgb,var(--accent) 88%,#30244f),color-mix(in srgb,var(--accent-2) 78%,#fff));color:#fff;box-shadow:0 24px 70px color-mix(in srgb,var(--accent) 25%,transparent);overflow:hidden;position:relative}.hero:after{content:"";position:absolute;width:230px;height:230px;border:42px solid rgba(255,255,255,.12);border-radius:50%;right:-60px;top:-80px}.eyebrow{text-transform:uppercase;letter-spacing:.13em;font-size:.76rem;font-weight:800}.hero h1{font-size:clamp(2.2rem,8vw,4.9rem);line-height:.92;margin:.18em 0 .25em;max-width:720px}.hero p{font-size:clamp(1rem,2.5vw,1.25rem);max-width:660px;margin:0;line-height:1.45}
    .toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:20px 0}.count{font-weight:800;font-size:1.08rem}.print{border:0;border-radius:999px;padding:12px 18px;background:var(--ink);color:#fff;font:inherit;font-weight:800;cursor:pointer}.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}.summary div,.card,.empty,.invalid{background:rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.8);box-shadow:0 14px 35px rgba(43,34,71,.09);backdrop-filter:blur(12px)}.summary div{padding:18px;border-radius:22px}.summary strong{display:block;font-size:1.8rem;color:var(--accent)}.summary span{color:var(--muted)}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.card{padding:22px;border-radius:24px;break-inside:avoid}.card-head{display:flex;align-items:start;justify-content:space-between;gap:12px}.card h2{font-size:1.35rem;margin:0}.score{white-space:nowrap;background:color-mix(in srgb,var(--accent) 13%,#fff);color:var(--accent);padding:6px 10px;border-radius:999px;font-weight:800}.facts{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:18px 0 0}.fact{padding:11px;border-radius:16px;background:#fff7fa}.fact small{display:block;color:var(--muted);font-size:.72rem}.fact strong{display:block;margin-top:2px;line-height:1.15}.time{display:block;color:var(--muted);font-size:.78rem;margin-top:14px}.empty,.invalid{padding:clamp(30px,8vw,72px);border-radius:30px;text-align:center}.empty h2,.invalid h1{font-size:clamp(1.7rem,6vw,3rem);margin:.2em 0}.empty p,.invalid p{color:var(--muted);max-width:560px;margin:0 auto}.invalid{margin-top:10vh}.invalid .brand{justify-content:center;margin-bottom:28px}
    @media(max-width:720px){main{width:min(100% - 20px,1080px);padding-top:18px}.hero{border-radius:25px}.summary{grid-template-columns:1fr 1fr 1fr;gap:7px}.summary div{padding:13px 9px}.summary strong{font-size:1.4rem}.summary span{font-size:.75rem}.grid{grid-template-columns:1fr}.facts{grid-template-columns:1fr}.toolbar{align-items:flex-start}.print{padding:10px 14px}}
    @media print{body{background:#fff}body:before,.print{display:none}main{width:100%;padding:0}.hero{box-shadow:none;color:#111;background:#f1eafc}.summary div,.card{box-shadow:none;border:1px solid #ddd}.grid{grid-template-columns:repeat(2,1fr)}}
  </style>
</head>
<body>
<main>
<?php if ($invalid): ?>
  <section class="invalid" role="alert">
    <div class="brand"><span class="brand-mark" aria-hidden="true"></span> CumpleClick</div>
    <p class="eyebrow">Acceso privado</p>
    <h1>Este enlace ya no está disponible</h1>
    <p>Puede estar vencido o haber sido reemplazado. Pide a quien organiza el evento que te comparta el enlace vigente.</p>
  </section>
<?php else: ?>
  <div class="brand"><span class="brand-mark" aria-hidden="true"></span> CumpleClick</div>
  <header class="hero">
    <p class="eyebrow">Tablero de los papás</p>
    <h1>Las predicciones de <?= $escape($eventName) ?></h1>
    <p>Cada tarjeta guarda una apuesta hecha en la cabina. Imprímelas o revísenlas juntos cuando llegue el gran día.</p>
  </header>
  <div class="toolbar">
    <span class="count"><?= count($predictions) ?> <?= count($predictions) === 1 ? 'predicción' : 'predicciones' ?></span>
    <button class="print" type="button" onclick="window.print()">Imprimir tablero</button>
  </div>
  <?php if (!$predictions): ?>
    <section class="empty">
      <p class="eyebrow">Todavía está por comenzar</p>
      <h2>La primera predicción aparecerá aquí</h2>
      <p>Cuando un invitado complete el recorrido de la cabina, su apuesta se sumará automáticamente a este tablero.</p>
    </section>
  <?php else: ?>
    <section class="summary" aria-label="Resumen de parecido">
      <div><strong><?= $counts['mama'] ?></strong><span>A mamá</span></div>
      <div><strong><?= $counts['papa'] ?></strong><span>A papá</span></div>
      <div><strong><?= $counts['ambos'] ?></strong><span>A ambos</span></div>
    </section>
    <section class="grid">
      <?php foreach ($predictions as $prediction): $labels = cb_prediction_labels($prediction); ?>
        <article class="card">
          <div class="card-head">
            <h2><?= $escape($prediction['guest_name']) ?></h2>
            <?php if ($prediction['puntaje_juego'] !== null): ?><span class="score"><?= (int) $prediction['puntaje_juego'] ?> pts</span><?php endif; ?>
          </div>
          <div class="facts">
            <div class="fact"><small>Se parecerá</small><strong><?= $escape($labels['parecido']) ?></strong></div>
            <div class="fact"><small>Pesará</small><strong><?= $escape($labels['peso']) ?></strong></div>
            <div class="fact"><small>Llegará</small><strong><?= $escape($labels['fecha']) ?></strong></div>
          </div>
          <time class="time" datetime="<?= $escape($prediction['created_at']) ?>"><?= $escape(date('d/m/Y · H:i', strtotime((string) $prediction['created_at']))) ?></time>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
