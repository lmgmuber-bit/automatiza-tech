<?php
/**
 * La lista de confirmados, del lado de la familia.
 *
 * Una sola pantalla, sin clave, entrando por el token de rol `parents` — el
 * mismo que en baby shower abre predicciones y regalos, así revocar uno
 * cierra todo a la vez. Solo lectura: los invitados confirman desde la
 * invitación; acá la familia ve quién viene y con qué niños, y con eso arma
 * la lista del kiosco (la ruleta) a mano, como siempre.
 *
 * A diferencia de las otras dos pantallas de papás, esta vale para TODAS las
 * modalidades (cumpleaños y baby shower).
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/lib.rsvp.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$token = (string) ($_GET['t'] ?? '');
$acceso = null;
$invalido = false;

try {
    $acceso = cb_rsvp_resolve_parents_token($token);
    if ($acceso === null) {
        $invalido = true;
    }
} catch (Throwable $e) {
    error_log('CumpleClick asistencia-papas: ' . $e->getMessage());
    $invalido = true;
}

$lista = [];
$esBabyShower = false;
$titulo = 'Confirmaciones';
if (!$invalido) {
    $lista = cb_rsvp_list((int) $acceso['party_id']);
    $esBabyShower = (string) ($acceso['event_type'] ?? '') === 'baby_shower';
    $nombre = trim((string) ($acceso['birthday_person_name'] ?? ''));
    $titulo = $esBabyShower
        ? ($nombre !== '' ? "Confirmados al baby shower de $nombre" : 'Confirmados al baby shower')
        : ($nombre !== '' ? "Confirmados a la fiesta de $nombre" : 'Confirmados a la fiesta');
}
$totalFamilias = count($lista);
$totalNinos = 0;
foreach ($lista as $fila) {
    $g = trim((string) ($fila['guest_names'] ?? ''));
    if ($g !== '') {
        // Los niños llegan como texto libre ("Emma y Lucas", "Sofía, Tomás"):
        // se cuentan los separadores más comunes para dar una cifra honesta.
        $totalNinos += count(preg_split('/\s*(?:,| y | e )\s*/u', $g, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}

// La pantalla viste los colores y el fondo de la temática de la fiesta
// (pedido de Luis: "muy sencilla"). Si el tema no está o no tiene fondo,
// cae a la paleta neutra de siempre — nada se rompe por un slug raro.
$colores = [];
$fondoTemaUrl = '';
if (!$invalido) {
    $slugTema = (string) ($acceso['theme_slug'] ?? '');
    if (preg_match('/^[a-z0-9-]+$/', $slugTema)) {
        $temas = cb_load_themes()['themes'] ?? [];
        $colores = is_array($temas[$slugTema]['colors'] ?? null) ? $temas[$slugTema]['colors'] : [];
        $rutaFondo = __DIR__ . '/themes/' . $slugTema . '/fondo-banner.jpg';
        if (is_file($rutaFondo)) {
            $fondoTemaUrl = 'themes/' . rawurlencode($slugTema) . '/fondo-banner.jpg?v=' . rawurlencode((string) filemtime($rutaFondo));
        }
    }
}
$hex = static fn ($v, $porDefecto) => preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $v) === 1 ? (string) $v : $porDefecto;
$cAccent = $hex($colores['accent'] ?? '', '#7b6cff');
$cYellow = $hex($colores['yellow'] ?? '', '#ffd75e');
$cDark1 = $hex($colores['dark1'] ?? '', '#0d1533');
$cDark2 = $hex($colores['dark2'] ?? '', '#0a1029');
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $esc($titulo) ?> · CumpleClick</title>
<style>
  :root {
    color-scheme: dark;
    --accent: <?= $esc($cAccent) ?>;
    --amarillo: <?= $esc($cYellow) ?>;
    --fondo1: <?= $esc($cDark1) ?>;
    --fondo2: <?= $esc($cDark2) ?>;
  }
  body {
    margin: 0; padding: 24px 16px 48px;
    background: linear-gradient(170deg, var(--fondo1), var(--fondo2)) fixed;
    color: #fff;
    font: 16px/1.5 "Baloo 2", "Segoe UI", system-ui, sans-serif;
    min-height: 100dvh;
  }
  /* El arte real de la fiesta, difuminado detrás: la familia reconoce SU
     temática al abrir, igual que en la invitación. */
  body::before {
    content: "";
    position: fixed; inset: 0; z-index: -1;
    <?php if ($fondoTemaUrl !== ''): ?>
    background: url("<?= $esc($fondoTemaUrl) ?>") center top / cover no-repeat;
    filter: blur(14px) saturate(1.05) brightness(0.5);
    transform: scale(1.08);
    <?php endif; ?>
  }
  main { max-width: 640px; margin: 0 auto; }
  .cabecera {
    background: color-mix(in srgb, var(--fondo1) 74%, transparent);
    border: 1px solid color-mix(in srgb, var(--accent) 55%, transparent);
    border-top: 4px solid var(--accent);
    border-radius: 20px;
    padding: 20px 22px 16px;
    margin-bottom: 18px;
    backdrop-filter: blur(10px);
    box-shadow: 0 18px 44px rgba(0, 0, 0, 0.35);
  }
  h1 { font-size: 1.45rem; line-height: 1.25; margin: 0 0 8px; }
  .resumen { margin: 0; display: flex; gap: 8px; flex-wrap: wrap; }
  .cifra {
    display: inline-block;
    background: color-mix(in srgb, var(--amarillo) 22%, transparent);
    border: 1px solid color-mix(in srgb, var(--amarillo) 55%, transparent);
    color: var(--amarillo);
    font-weight: 700; font-size: 0.88rem;
    padding: 3px 12px; border-radius: 999px;
  }
  .tarjeta {
    background: color-mix(in srgb, var(--fondo1) 68%, transparent);
    border: 1px solid rgba(255,255,255,0.14);
    border-left: 4px solid var(--accent);
    border-radius: 16px; padding: 14px 18px; margin-bottom: 12px;
    backdrop-filter: blur(8px);
  }
  .familia { font-weight: 700; font-size: 1.05rem; margin: 0; }
  .ninos { color: #e6ecff; margin: 2px 0 0; }
  .fecha { color: rgba(255,255,255,0.55); font-size: 0.82rem; margin: 4px 0 0; }
  .vacio {
    text-align: center; padding: 48px 20px; color: rgba(255,255,255,0.8);
    background: color-mix(in srgb, var(--fondo1) 60%, transparent);
    border: 1px dashed color-mix(in srgb, var(--accent) 60%, transparent);
    border-radius: 16px;
    backdrop-filter: blur(8px);
  }
  .enlaces { margin-top: 28px; display: flex; gap: 10px; flex-wrap: wrap; }
  .enlaces a {
    color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 600;
    background: color-mix(in srgb, var(--accent) 40%, transparent);
    border: 1px solid color-mix(in srgb, var(--accent) 70%, transparent);
    padding: 8px 14px; border-radius: 999px;
  }
  .pie { margin-top: 30px; text-align: center; color: rgba(255,255,255,0.45); font-size: 0.8rem; }
  .error {
    text-align: center; padding: 64px 20px;
    background: color-mix(in srgb, var(--fondo1) 70%, transparent);
    border-radius: 20px;
  }
</style>
</head>
<body>
<main>
<?php if ($invalido): ?>
  <div class="error">
    <h1>Enlace no válido</h1>
    <p class="resumen">Este enlace venció o fue reemplazado. Pídele uno nuevo a CumpleClick.</p>
  </div>
<?php else: ?>
  <header class="cabecera">
    <h1><?= $esc($titulo) ?></h1>
    <p class="resumen">
      <span class="cifra"><?= $totalFamilias === 1 ? '1 confirmación' : $esc($totalFamilias . ' confirmaciones') ?></span>
      <?php if (!$esBabyShower && $totalNinos > 0): ?>
      <span class="cifra">👧🧒 <?= $esc($totalNinos) ?> <?= $totalNinos === 1 ? 'niño' : 'niños' ?></span>
      <?php endif; ?>
    </p>
  </header>

  <?php if (!$lista): ?>
  <div class="vacio">Todavía nadie confirma. Cuando alguien confirme desde la invitación, aparece aquí al instante.</div>
  <?php else: ?>
  <?php foreach ($lista as $fila): ?>
  <article class="tarjeta">
    <p class="familia"><?= $esc($fila['family_name']) ?></p>
    <?php if (trim((string) ($fila['guest_names'] ?? '')) !== ''): ?>
    <p class="ninos">👧🧒 <?= $esc($fila['guest_names']) ?></p>
    <?php endif; ?>
    <p class="fecha">Confirmó el <?= $esc(date('d-m-Y H:i', strtotime((string) $fila['created_at']) ?: time())) ?></p>
  </article>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($esBabyShower): ?>
  <div class="enlaces">
    <a href="predicciones.php?t=<?= $esc($token) ?>">🔮 Predicciones</a>
    <a href="regalos-papas.php?t=<?= $esc($token) ?>">🎁 Lista de regalos</a>
  </div>
  <?php endif; ?>
  <p class="pie">CumpleClick · esta lista se actualiza sola con cada confirmación</p>
<?php endif; ?>
</main>
</body>
</html>
