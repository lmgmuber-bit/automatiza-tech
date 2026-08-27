<?php
/**
 * La lista de regalos, del lado de los papás.
 *
 * Una sola pantalla, sin clave, entrando por su token. Hace exactamente tres
 * cosas —agregar un regalo, escribir su condición y ordenar la lista— más
 * ocultar lo que no quieren y ver quién tomó qué. Nada más: si esta pantalla
 * crece, deja de usarse.
 *
 * Es el único lugar de todo el producto donde se muestran los nombres de quien
 * reservó. El invitado nunca los ve (ver la cabecera de lib.gifts.php).
 *
 * Todo se resuelve con formularios normales y POST-redirect-GET: sin JS
 * funciona igual, y recargar después de guardar no repite la acción.
 */
require __DIR__ . '/lib.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$token = (string) ($_GET['t'] ?? ($_POST['t'] ?? ''));
$acceso = null;
$invalido = false;

try {
    $acceso = cb_invitation_resolve_role_token($token, 'parents');
    if ($acceso === null) {
        $invalido = true;
    }
} catch (Throwable $e) {
    error_log('CumpleClick regalos-papas: ' . $e->getMessage());
    $invalido = true;
}

// ── Acciones ───────────────────────────────────────────────────────────────
// Se responde con una redirección para que F5 no vuelva a agregar el regalo.
if (!$invalido && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $invitationId = (int) $acceso['invitation_id'];
    $accion = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $aviso = '';

    try {
        if ($accion === 'agregar') {
            $r = cb_gift_add($invitationId, [
                'title' => $_POST['titulo'] ?? '',
                'notes' => $_POST['nota'] ?? '',
            ], 'parents');
            $aviso = !empty($r['ok']) ? 'agregado' : 'falta_titulo';
        } elseif ($accion === 'nota') {
            $r = cb_gift_update_notes($invitationId, $id, (string) ($_POST['nota'] ?? ''));
            $aviso = !empty($r['ok']) ? 'guardado' : 'error';
        } elseif ($accion === 'arriba' || $accion === 'abajo') {
            $r = cb_gift_move($invitationId, $id, $accion);
            $aviso = !empty($r['ok']) ? 'ordenado' : 'extremo';
        } elseif ($accion === 'modo') {
            $r = cb_gift_set_mode($invitationId, (string) ($_POST['modo'] ?? 'list'));
            $aviso = !empty($r['ok']) ? 'modo' : 'error';
        } elseif ($accion === 'ocultar' || $accion === 'mostrar') {
            $r = cb_gift_set_hidden($invitationId, $id, $accion === 'ocultar');
            // Un regalo ya tomado no se puede ocultar: alguien se comprometió
            // con él y hacerlo desaparecer lo deja sin saber qué pasó.
            $aviso = !empty($r['ok']) ? 'guardado' : 'tomado_no_se_oculta';
        }
    } catch (Throwable $e) {
        error_log('CumpleClick regalos-papas acción: ' . $e->getMessage());
        $aviso = 'error';
    }

    header('Location: regalos-papas.php?t=' . rawurlencode($token) . '&aviso=' . rawurlencode($aviso));
    exit;
}

$nombreBebe = trim((string) ($acceso['birthday_person_name'] ?? ''));
$themeVars = !$invalido ? cb_theme_css_vars((string) ($acceso['theme_slug'] ?? '')) : '';
$modo = 'list';
if (!$invalido) {
    $inv = cb_load_invitation_by_id((int) $acceso['invitation_id']);
    $modo = cb_gift_mode($inv ?: []);
}
$esAbierto = $modo === 'open';
$regalos = $invalido ? [] : cb_gift_list_for_parents((int) $acceso['invitation_id']);
$disponibles = 0;
foreach ($regalos as $g) {
    if ((string) $g['status'] !== 'taken' && (string) $g['moderation_status'] === 'approved') {
        $disponibles++;
    }
}

$AVISOS = [
    'agregado' => 'Listo, ya está en la lista.',
    'modo' => 'Cambiaste cómo funcionan los regalos.',
    'guardado' => 'Guardado.',
    'ordenado' => 'Cambiaste el orden.',
    'extremo' => 'Ese ya está en la punta.',
    'falta_titulo' => 'Escribe qué es antes de agregarlo.',
    'tomado_no_se_oculta' => 'Ese ya lo tomó alguien, así que no se puede ocultar.',
    'error' => 'No pudimos guardar el cambio. Inténtalo de nuevo.',
];
$aviso = (string) ($_GET['aviso'] ?? '');
$avisoTexto = $AVISOS[$aviso] ?? '';
$avisoMalo = in_array($aviso, ['falta_titulo', 'tomado_no_se_oculta', 'error', 'extremo'], true);
?>
<!doctype html>
<html lang="es-CL">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title><?= $invalido ? 'Enlace no disponible' : ($esAbierto ? 'Los regalos' : 'Lista de regalos') ?> · CumpleClick</title>
  <link rel="icon" href="brand/cumpleclick-mark.svg" type="image/svg+xml">
  <style>
    /* Baloo 2 local, nunca CDN: si no, la tipografía de la marca cambia de
       forma en cada aparato. */
    @font-face { font-family:"Baloo 2"; src:url("admin/fonts/baloo2-600.woff2") format("woff2");
                 font-weight:600; font-style:normal; font-display:swap; }
    @font-face { font-family:"Baloo 2"; src:url("admin/fonts/baloo2-800.woff2") format("woff2");
                 font-weight:800; font-style:normal; font-display:swap; }

    /* Los nombres son los que publica cb_theme_css_vars(): --pink, --ink,
       --dark1... Cualquier otro no llega nunca y la página se queda con el
       respaldo sin avisar. */
    :root {
      --pink:#8B5CF6; --pink-soft:#EDE4FB; --yellow:#FBBF24; --ink:#2b1a12;
      --bg-light1:#FFF8EC; --bg-light2:#F3E9FB; --dark1:#4C2882; --dark2:#30204f; --dark3:#A78BFA;
      <?= $themeVars ?>
      --papel: color-mix(in srgb, var(--bg-light1) 55%, #fff);
      --tenue: color-mix(in srgb, var(--ink) 58%, transparent);
      --fuente: "Baloo 2", "Segoe UI Rounded", "Segoe UI", system-ui, sans-serif;
    }
    * { box-sizing:border-box; }
    body {
      margin:0; padding:28px 16px 64px; min-height:100vh;
      background:linear-gradient(170deg, var(--bg-light2), var(--bg-light1) 60%);
      color:var(--ink); font-family:var(--fuente); line-height:1.5;
    }
    .hoja { max-width:44rem; margin:0 auto; }
    .marca { display:flex; align-items:center; justify-content:center; gap:.5em; margin-bottom:22px; }
    .marca img { width:2em; height:2em; }
    .marca b { color:var(--dark1); font-size:1.05rem; font-weight:800; }
    .marca b i { color:var(--pink); font-style:normal; }

    h1 { margin:0; color:var(--dark1); font-size:clamp(1.5rem,6vw,2rem); font-weight:800; line-height:1.15; }
    .lede { margin:8px 0 0; color:var(--tenue); font-size:.98rem; }

    .aviso { margin:18px 0 0; padding:11px 16px; border-radius:14px;
             background:color-mix(in srgb, var(--pink) 14%, #fff); color:var(--dark1);
             font-size:.92rem; font-weight:600; }
    .aviso.malo { background:#fdeaea; color:#8a2020; }

    .caja { margin:22px 0 0; padding:18px; border-radius:20px; background:var(--papel);
            box-shadow:0 10px 30px color-mix(in srgb, var(--dark1) 12%, transparent); }
    .caja h2 { margin:0 0 12px; color:var(--dark1); font-size:1.05rem; font-weight:800; }

    label { display:block; margin:0 0 4px; color:var(--tenue); font-size:.82rem; font-weight:600; }
    input[type=text] {
      width:100%; padding:11px 14px; border:1px solid color-mix(in srgb, var(--dark1) 18%, transparent);
      border-radius:12px; background:#fff; color:var(--ink); font:inherit; font-size:.95rem;
    }
    input[type=text]:focus-visible { outline:2px solid var(--pink); outline-offset:1px; }
    .par { display:grid; gap:12px; }
    @media (min-width:600px) { .par { grid-template-columns:1fr 1.4fr; } }

    button { font:inherit; cursor:pointer; }
    .btn {
      margin-top:12px; padding:11px 20px; border:0; border-radius:999px;
      background:var(--pink); color:#fff; font-size:.92rem; font-weight:800;
    }
    .mini {
      padding:6px 10px; border:1px solid color-mix(in srgb, var(--dark1) 16%, transparent);
      border-radius:9px; background:#fff; color:var(--dark1); font-size:.76rem; font-weight:700;
    }

    ul { margin:0; padding:0; list-style:none; display:grid; gap:12px; }
    .item { padding:14px 16px; border-radius:16px; background:var(--papel);
            box-shadow:0 6px 18px color-mix(in srgb, var(--dark1) 9%, transparent); }
    .item.oculto { opacity:.5; }
    .titulo { margin:0; color:var(--dark1); font-size:1.02rem; font-weight:800; overflow-wrap:anywhere; }
    .quien { margin:4px 0 0; color:var(--pink); font-size:.85rem; font-weight:700; }
    .agrego { margin:4px 0 0; color:var(--tenue); font-size:.76rem; }
    .fila { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-top:10px; }
    .fila form { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin:0; flex:1 1 auto; }
    .fila input[type=text] { flex:1 1 12rem; padding:8px 12px; font-size:.86rem; }

    .vacio { padding:28px 18px; border-radius:18px; background:var(--papel); text-align:center; color:var(--tenue); }
    .pie { margin:28px 0 0; color:var(--tenue); font-size:.78rem; text-align:center; }

    .modos { display:grid; gap:10px; }
    @media (min-width:600px) { .modos { grid-template-columns:1fr 1fr; } }
    .modo { margin:0; padding:12px 14px; border:1px solid color-mix(in srgb, var(--dark1) 14%, transparent);
            border-radius:14px; background:#fff; }
    .modo.activo { border-color:var(--pink); background:color-mix(in srgb, var(--pink) 9%, #fff); }
    .modo b { display:block; color:var(--dark1); font-size:.92rem; }
    .modo span { display:block; margin:3px 0 8px; color:var(--tenue); font-size:.8rem; line-height:1.35; }
  </style>
</head>
<body>
<div class="hoja">
  <div class="marca">
    <img src="brand/cumpleclick-mark.svg" alt="">
    <b>Cumple<i>Click</i></b>
  </div>

<?php if ($invalido): ?>
  <div class="vacio">
    <h1>Este enlace ya no está disponible</h1>
    <p class="lede">Puede haber sido revocado o haber expirado. Escríbenos y te enviamos uno nuevo.</p>
  </div>
<?php else: ?>

  <?php // En modo abierto no hay lista, así que llamarla así confunde. ?>
  <h1><?= $esAbierto ? 'Los regalos' : 'La lista de regalos' ?><?= $nombreBebe !== '' ? ' de ' . $esc($nombreBebe) : '' ?></h1>
  <p class="lede"><?= $esAbierto
    ? 'No hay lista: cada invitado anota lo que va a llevar y todos lo ven, para que nadie repita. Acá miras lo que van anotando.'
    : 'Agrega lo que necesitan. Los invitados van a ir eligiendo de acá, y cada uno queda marcado para que nadie lleve lo mismo.'
  ?></p>

  <?php // El interruptor va arriba de todo porque cambia el sentido de lo que
        // viene abajo: en modo abierto los papás no cargan nada, miran. ?>
  <section class="caja">
    <h2>Cómo funcionan los regalos</h2>
    <div class="modos">
      <?php foreach ([
          ['list', 'Con lista', 'Ustedes anotan lo que necesitan y los invitados eligen de ahí.'],
          ['open', 'Sin lista', 'Cada invitado anota lo que va a llevar. Nadie pide nada.'],
      ] as [$valor, $rotulo, $explica]): ?>
      <form method="post" class="modo<?= $modo === $valor ? ' activo' : '' ?>">
        <input type="hidden" name="t" value="<?= $esc($token) ?>">
        <input type="hidden" name="accion" value="modo">
        <input type="hidden" name="modo" value="<?= $valor ?>">
        <b><?= $rotulo ?><?= $modo === $valor ? ' · activo' : '' ?></b>
        <span><?= $explica ?></span>
        <?php if ($modo !== $valor): ?>
        <button class="mini" type="submit">Usar este</button>
        <?php endif; ?>
      </form>
      <?php endforeach; ?>
    </div>
    <p class="agrego" style="margin-top:10px">Cambiar de modo no borra nada de lo ya anotado.</p>
  </section>

  <?php if ($avisoTexto !== ''): ?>
  <p class="aviso<?= $avisoMalo ? ' malo' : '' ?>" role="status"><?= $esc($avisoTexto) ?></p>
  <?php endif; ?>

  <section class="caja">
    <h2><?= $esAbierto ? 'Agregar algo de todos modos' : 'Agregar un regalo' ?></h2>
    <?php if ($esAbierto): ?>
    <p class="agrego" style="margin:-6px 0 12px">Sin lista los invitados anotan solos, pero si quieren
      dejar una idea puesta, pueden.</p>
    <?php endif; ?>
    <form method="post" class="par">
      <input type="hidden" name="t" value="<?= $esc($token) ?>">
      <input type="hidden" name="accion" value="agregar">
      <div>
        <label for="titulo">Qué es</label>
        <input id="titulo" type="text" name="titulo" maxlength="120" required placeholder="Coche">
      </div>
      <div>
        <?php // Este campo es el que más valor agrega de toda la lista. ?>
        <label for="nota">Cómo lo necesitan (opcional)</label>
        <input id="nota" type="text" name="nota" maxlength="400"
               placeholder="Liviano, que quepa en la maleta del auto">
      </div>
      <div><button class="btn" type="submit">Agregarlo</button></div>
    </form>
  </section>

  <h2 style="margin:30px 0 12px;color:var(--dark1);font-size:1.05rem;font-weight:800">
    <?php if ($esAbierto): ?>
    <?= count($regalos) ?> anotado<?= count($regalos) === 1 ? '' : 's' ?> por los invitados
    <?php else: ?>
    <?= count($regalos) ?> en la lista · <?= $disponibles ?> sin dueño todavía
    <?php endif; ?>
  </h2>

  <?php if (!$regalos): ?>
  <div class="vacio">
    <?= $esAbierto
      ? 'Todavía no anotó nadie. La sección ya aparece en la invitación, invitando a anotar.'
      : 'Todavía no hay nada. Agrega el primero acá arriba — sin al menos un regalo, la sección no aparece en la invitación.'
    ?>
  </div>
  <?php else: ?>
  <ul>
    <?php foreach ($regalos as $g):
        $tomado = (string) $g['status'] === 'taken';
        $oculto = (string) $g['moderation_status'] !== 'approved';
    ?>
    <li class="item<?= $oculto ? ' oculto' : '' ?>">
      <p class="titulo"><?= $esc($g['title']) ?></p>
      <?php if ($tomado): ?>
      <?php // El único lugar del producto donde se ve el nombre. ?>
      <p class="quien">Lo lleva <?= $esc($g['claimed_name']) ?></p>
      <?php endif; ?>
      <?php if ((string) $g['added_by'] === 'guest'): ?>
      <p class="agrego">Lo agregó un invitado</p>
      <?php endif; ?>
      <?php if ($oculto): ?>
      <p class="agrego">Oculto — no se ve en la invitación</p>
      <?php endif; ?>

      <div class="fila">
        <form method="post">
          <input type="hidden" name="t" value="<?= $esc($token) ?>">
          <input type="hidden" name="accion" value="nota">
          <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
          <input type="text" name="nota" maxlength="400" value="<?= $esc($g['notes']) ?>"
                 placeholder="Cómo lo necesitan" aria-label="Condición de <?= $esc($g['title']) ?>">
          <button class="mini" type="submit">Guardar</button>
        </form>
      </div>
      <div class="fila">
        <?php foreach ([['arriba', 'Subir'], ['abajo', 'Bajar']] as [$acc, $rotulo]): ?>
        <form method="post" style="flex:0 0 auto">
          <input type="hidden" name="t" value="<?= $esc($token) ?>">
          <input type="hidden" name="accion" value="<?= $acc ?>">
          <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
          <button class="mini" type="submit"><?= $rotulo ?></button>
        </form>
        <?php endforeach; ?>
        <?php if (!$tomado): ?>
        <form method="post" style="flex:0 0 auto">
          <input type="hidden" name="t" value="<?= $esc($token) ?>">
          <input type="hidden" name="accion" value="<?= $oculto ? 'mostrar' : 'ocultar' ?>">
          <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
          <button class="mini" type="submit"><?= $oculto ? 'Volver a mostrar' : 'Ocultar' ?></button>
        </form>
        <?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <p class="pie">Este enlace es privado. Quien lo tenga puede editar la lista, así que compártelo
    solo entre ustedes.</p>
<?php endif; ?>
</div>
</body>
</html>
