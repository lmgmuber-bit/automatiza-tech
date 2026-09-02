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
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $esc($titulo) ?> · CumpleClick</title>
<style>
  :root { color-scheme: dark; }
  body {
    margin: 0; padding: 24px 16px 48px;
    background: #0d1533; color: #fff;
    font: 16px/1.5 "Baloo 2", system-ui, sans-serif;
  }
  main { max-width: 640px; margin: 0 auto; }
  h1 { font-size: 1.45rem; line-height: 1.25; margin: 0 0 4px; }
  .resumen { color: #9fb2e8; margin: 0 0 20px; }
  .tarjeta {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 16px; padding: 14px 18px; margin-bottom: 12px;
  }
  .familia { font-weight: 700; font-size: 1.05rem; }
  .ninos { color: #cdd9ff; margin: 2px 0 0; }
  .fecha { color: #7d90c9; font-size: 0.82rem; margin: 4px 0 0; }
  .vacio {
    text-align: center; padding: 48px 20px; color: #9fb2e8;
    border: 1px dashed rgba(255,255,255,0.25); border-radius: 16px;
  }
  .enlaces { margin-top: 28px; display: flex; gap: 10px; flex-wrap: wrap; }
  .enlaces a {
    color: #fff; text-decoration: none; font-size: 0.9rem;
    background: rgba(255,255,255,0.1); padding: 8px 14px; border-radius: 999px;
  }
  .error { text-align: center; padding: 64px 20px; }
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
  <h1><?= $esc($titulo) ?></h1>
  <p class="resumen">
    <?= $totalFamilias === 1 ? '1 confirmación' : $esc($totalFamilias . ' confirmaciones') ?>
    <?php if (!$esBabyShower && $totalNinos > 0): ?> · <?= $esc($totalNinos) ?> <?= $totalNinos === 1 ? 'niño' : 'niños' ?><?php endif; ?>
  </p>

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
<?php endif; ?>
</main>
</body>
</html>
