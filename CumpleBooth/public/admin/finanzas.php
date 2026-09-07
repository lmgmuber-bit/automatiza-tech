<?php
/**
 * admin/finanzas.php — cuánto se ha invertido y cuánto ha entrado.
 *
 * Los ingresos de las fiestas **no se escriben acá**: se leen del cobro que ya tiene cada
 * ficha, con la misma función que arma el comprobante. Así la pantalla de finanzas y la
 * boleta del cliente no pueden decir números distintos. Lo que se carga a mano es lo que la
 * aplicación no puede saber sola: la impresora, el papel, los imanes, la publicidad.
 *
 * Lo regalado se muestra aparte y no como ingreso cero: una fiesta de marcha blanca al 100%
 * es plata que se decidió no cobrar, y verla sumada es lo que avisa cuando la marcha blanca
 * se está estirando de más.
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

/** Los mismos iconos de línea del resto del admin. */
function admin_icon(string $name): string
{
    $paths = [
        'party' => '<path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01M22 8h.01M15 2h.01M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12v0c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11v0c-.11.7-.72 1.22-1.43 1.22H16"/><path d="M11 2 9.89 4.11a2.9 2.9 0 0 0 .5 3.4l.5.5a2.9 2.9 0 0 1 .5 3.4L9 14"/>',
        'palette' => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
        'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'chart' => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 16v-4M12 16V8M17 16v-6"/>',
        'trash' => '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
    ];
    $d = $paths[$name] ?? '';
    if ($d === '') { return ''; }
    return '<svg class="icon" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

// ================== SESIÓN (mismo contrato que index.php y planes.php) ==================
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    if (admin_csrf_check()) {
        $_SESSION = [];
        session_destroy();
        header('Location: finanzas.php');
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
            header('Location: finanzas.php');
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
  <form class="login-card" method="post" action="finanzas.php">
    <h1>CumpleBooth</h1>
    <p class="muted">Finanzas</p>
    <?php if ($loginError !== ''): ?><p class="alert alert-error"><?= h($loginError) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>">
    <input type="hidden" name="action" value="login">
    <label for="password">Contraseña</label>
    <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
    <button class="btn btn-primary" type="submit">Ingresar</button>
    <p class="muted small" style="margin-top:12px;text-align:center"><a href="recuperar.php">Olvidé la contraseña</a></p>
  </form>
</body>
</html><?php
    exit;
}

// ================== DATOS ==================
require_once __DIR__ . '/../lib.finanzas.php';

$aviso = '';
$errores = [];
$disponible = cb_finanzas_disponible();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'guardar') {
    if (!admin_csrf_check()) {
        $errores[] = 'Sesión expirada, vuelve a intentarlo.';
    } else {
        $r = cb_finanzas_guardar([
            'id' => $_POST['id'] ?? null,
            'fecha' => $_POST['fecha'] ?? '',
            'tipo' => $_POST['tipo'] ?? '',
            'categoria' => $_POST['categoria'] ?? 'otro',
            'descripcion' => $_POST['descripcion'] ?? '',
            'monto' => $_POST['monto'] ?? '',
            'unidades' => $_POST['unidades'] ?? '',
            'nota' => $_POST['nota'] ?? '',
        ]);
        if (!empty($r['ok'])) {
            // Redirección después de guardar: recargar la página no vuelve a cargar el gasto.
            header('Location: finanzas.php?ok=1');
            exit;
        }
        $errores = $r['errors'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'borrar') {
    if (!admin_csrf_check()) {
        $errores[] = 'Sesión expirada, vuelve a intentarlo.';
    } else {
        cb_finanzas_borrar((int) ($_POST['id'] ?? 0));
        header('Location: finanzas.php?borrado=1');
        exit;
    }
}

if (isset($_GET['ok'])) { $aviso = 'Movimiento guardado.'; }
if (isset($_GET['borrado'])) { $aviso = 'Movimiento borrado.'; }

$r = $disponible ? cb_finanzas_resumen() : [
    'invertido' => 0, 'ingresado' => 0, 'ingreso_fiestas' => 0, 'ingreso_manual' => 0,
    'regalado' => 0, 'resultado' => 0, 'fiestas_cobradas' => 0, 'fiestas_regaladas' => 0,
    'categorias' => [], 'meses' => [], 'movimientos' => [], 'fiestas' => [],
    'equilibrio' => ['plan' => '', 'precio' => 0, 'costo_recuerdos' => 0, 'margen' => 0, 'faltan' => 0],
];

/** "2026-09" -> "sep 2026", para que el eje del gráfico se lea de un vistazo. */
function fin_mes_corto(string $ym): string
{
    $nombres = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr', '05' => 'may',
                '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'sep', '10' => 'oct',
                '11' => 'nov', '12' => 'dic'];
    if (strlen($ym) < 7) { return $ym; }
    return ($nombres[substr($ym, 5, 2)] ?? '') . ' ' . substr($ym, 2, 2);
}

// Solo los últimos doce meses: más que eso no cabe legible en la pantalla de un teléfono.
$meses = array_slice($r['meses'], -12);
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Finanzas · CumpleBooth</title>
<style><?php require __DIR__ . '/_style.css.php'; ?></style>
<style>
  /* Solo lo que no existe en la hoja compartida. */
  .fin-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; }
  .fin-kpi {
    background: var(--card-bg); border-radius: var(--radius-sm); box-shadow: var(--shadow);
    padding: 16px 18px; min-width: 0;
  }
  .fin-kpi strong {
    display: block; font-family: var(--font-display); font-size: 1.6rem; line-height: 1.1;
    color: var(--text); font-variant-numeric: tabular-nums; overflow-wrap: anywhere;
  }
  .fin-kpi span { display: block; font-size: .78rem; font-weight: 700; color: var(--text-muted); margin-top: 4px; }
  .fin-kpi small { display: block; font-size: .74rem; color: var(--text-muted); margin-top: 6px; line-height: 1.35; }
  .fin-kpi--bien strong { color: var(--success); }
  .fin-kpi--mal strong { color: var(--danger); }
  .fin-kpi--regalo strong { color: var(--warn); }

  .fin-graficos { display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; align-items: start; }
  @media (max-width: 860px) { .fin-graficos { grid-template-columns: 1fr; } }
  .fin-svg { width: 100%; height: auto; display: block; overflow: visible; }
  .fin-leyenda { display: flex; gap: 16px; flex-wrap: wrap; font-size: .8rem; font-weight: 700;
    color: var(--text-muted); margin: 0 0 10px; }
  .fin-leyenda i { display: inline-block; width: 11px; height: 11px; border-radius: 3px;
    margin-right: 6px; vertical-align: -1px; }

  .fin-barras { display: flex; flex-direction: column; gap: 12px; margin: 4px 0 0; }
  .fin-barra-fila { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4px 12px; align-items: baseline; }
  .fin-barra-fila b { font-size: .86rem; font-weight: 700; min-width: 0; overflow-wrap: anywhere; }
  .fin-barra-fila span { font-size: .86rem; font-variant-numeric: tabular-nums; color: var(--text-muted); white-space: nowrap; }
  .fin-barra-pista { grid-column: 1 / -1; height: 9px; border-radius: 99px; background: var(--primary-soft); overflow: hidden; }
  .fin-barra-pista i { display: block; height: 100%; border-radius: 99px; background: var(--primary); }

  .fin-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px 18px; align-items: end; }
  .fin-form .fin-ancho { grid-column: 1 / -1; }
  .fin-form select, .fin-form input, .fin-form textarea { min-width: 0; }

  .fin-tabla-marco { overflow-x: auto; margin: 4px -4px 0; padding: 0 4px; }
  .fin-tabla { width: 100%; border-collapse: collapse; font-size: .9rem; min-width: 560px; }
  .fin-tabla th, .fin-tabla td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: top; }
  .fin-tabla th { font-size: .74rem; text-transform: uppercase; letter-spacing: .06em;
    color: var(--text-muted); font-weight: 700; }
  .fin-tabla tbody tr:last-child td { border-bottom: 0; }
  .fin-tabla .fin-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .fin-egreso { color: var(--danger); font-weight: 700; }
  .fin-ingreso { color: var(--success); font-weight: 700; }
  .fin-vacio { padding: 22px 4px; color: var(--text-muted); font-size: .92rem; }
  .fin-borrar { background: none; border: 0; padding: 4px; cursor: pointer; color: var(--text-muted);
    border-radius: 8px; line-height: 0; }
  .fin-borrar:hover { color: var(--danger); background: var(--danger-soft); }
</style>
</head>
<body>
<div class="wrap">
  <header class="topbar">
    <div>
      <h1>Finanzas</h1>
      <p class="muted">
        Lo que se invierte se carga acá. Lo que entra por las fiestas se lee solo del cobro de
        cada ficha, así que no hay que anotarlo dos veces.
      </p>
    </div>
    <form method="post" class="inline-form">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="logout">
      <button class="btn btn-ghost" type="submit"><?= admin_icon('logout') ?> Salir</button>
    </form>
  </header>

  <nav class="tabs">
    <a class="tab" href="index.php"><?= admin_icon('party') ?> Fiestas</a>
    <a class="tab" href="index.php?view=temas"><?= admin_icon('palette') ?> Temáticas</a>
    <a class="tab" href="leads.php"><?= admin_icon('party') ?> Solicitudes</a>
    <a class="tab" href="mensajes.php"><?= admin_icon('chat') ?> Mensajes</a>
    <a class="tab" href="comprobante.php"><?= admin_icon('copy') ?> Comprobante</a>
    <a class="tab" href="planes.php"><?= admin_icon('copy') ?> Planes</a>
    <a class="tab active" href="finanzas.php"><?= admin_icon('chart') ?> Finanzas</a>
  </nav>

  <?php if ($aviso !== ''): ?><p class="alert alert-ok"><?= h($aviso) ?></p><?php endif; ?>
  <?php foreach ($errores as $e): ?><p class="alert alert-error"><?= h($e) ?></p><?php endforeach; ?>
  <?php if (!$disponible): ?>
    <p class="alert alert-error">
      Falta la migración 017 o la aplicación no está usando base de datos. Sin eso no se pueden
      guardar movimientos.
    </p>
  <?php endif; ?>

  <main>

    <!-- ============ LOS CUATRO NÚMEROS ============ -->
    <section class="fin-kpis">
      <article class="fin-kpi">
        <strong><?= h(cb_format_clp($r['invertido'])) ?></strong>
        <span>Invertido</span>
        <small>Equipo, insumos y todo lo cargado a mano.</small>
      </article>
      <article class="fin-kpi">
        <strong><?= h(cb_format_clp($r['ingresado'])) ?></strong>
        <span>Ingresado</span>
        <small>
          <?= (int) $r['fiestas_cobradas'] ?> fiesta<?= $r['fiestas_cobradas'] === 1 ? '' : 's' ?> cobrada<?= $r['fiestas_cobradas'] === 1 ? '' : 's' ?><?php
          if ($r['ingreso_manual'] > 0): ?> + <?= h(cb_format_clp($r['ingreso_manual'])) ?> de otros ingresos<?php endif; ?>.
        </small>
      </article>
      <article class="fin-kpi <?= $r['resultado'] >= 0 ? 'fin-kpi--bien' : 'fin-kpi--mal' ?>">
        <strong><?= h(cb_format_clp($r['resultado'])) ?></strong>
        <span>Resultado</span>
        <small>
          <?php if ($r['resultado'] >= 0): ?>
            Ya estás a favor.
          <?php elseif ($r['equilibrio']['faltan'] > 0): ?>
            Faltan <?= (int) $r['equilibrio']['faltan'] ?> fiesta<?= $r['equilibrio']['faltan'] === 1 ? '' : 's' ?>
            <?= h($r['equilibrio']['plan']) ?> para quedar en cero.
          <?php else: ?>
            Todavía en rojo.
          <?php endif; ?>
        </small>
      </article>
      <article class="fin-kpi fin-kpi--regalo">
        <strong><?= h(cb_format_clp($r['regalado'])) ?></strong>
        <span>Regalado</span>
        <small>
          <?= (int) $r['fiestas_regaladas'] ?> fiesta<?= $r['fiestas_regaladas'] === 1 ? '' : 's' ?>
          sin costo. No es pérdida: es lo que decidiste no cobrar.
        </small>
      </article>
    </section>

    <!-- ============ GRÁFICOS ============ -->
    <section class="fin-graficos">

      <article class="card">
        <h2>Mes a mes</h2>
        <p class="muted small">Lo que entró contra lo que salió, mes por mes.</p>
        <?php if (!$meses): ?>
          <p class="fin-vacio">Todavía no hay movimientos. Carga la primera compra abajo y el gráfico aparece.</p>
        <?php else:
          // El alto se reparte contra el mes más grande, sea de ingreso o de egreso, para que
          // las dos series se comparen sobre la misma escala y no cada una con la suya.
          $tope = 0;
          foreach ($meses as $m) { $tope = max($tope, $m['ingreso'], $m['egreso']); }
          $tope = max($tope, 1);
          $anchoMes = 68; $altoBarras = 150; $margenIzq = 8; $altoTotal = $altoBarras + 34;
          $ancho = max(320, count($meses) * $anchoMes + $margenIzq * 2);
        ?>
        <p class="fin-leyenda">
          <span><i style="background:var(--success)"></i>Ingresos</span>
          <span><i style="background:var(--danger)"></i>Inversión</span>
        </p>
        <div class="fin-tabla-marco">
        <svg class="fin-svg" viewBox="0 0 <?= $ancho ?> <?= $altoTotal ?>"
             style="min-width:<?= (int) ($ancho * 0.7) ?>px"
             role="img" aria-label="Ingresos y egresos por mes">
          <line x1="0" y1="<?= $altoBarras ?>" x2="<?= $ancho ?>" y2="<?= $altoBarras ?>"
                stroke="var(--border)" stroke-width="1.5"/>
          <?php foreach ($meses as $i => $m):
            $x = $margenIzq + $i * $anchoMes;
            $hIn = (int) round($m['ingreso'] / $tope * ($altoBarras - 12));
            $hEg = (int) round($m['egreso'] / $tope * ($altoBarras - 12));
          ?>
            <?php if ($hIn > 0): ?>
            <rect x="<?= $x + 6 ?>" y="<?= $altoBarras - $hIn ?>" width="24" height="<?= $hIn ?>"
                  rx="4" fill="var(--success)"><title><?= h(fin_mes_corto($m['mes'])) ?>: <?= h(cb_format_clp($m['ingreso'])) ?> de ingresos</title></rect>
            <?php endif; ?>
            <?php if ($hEg > 0): ?>
            <rect x="<?= $x + 34 ?>" y="<?= $altoBarras - $hEg ?>" width="24" height="<?= $hEg ?>"
                  rx="4" fill="var(--danger)"><title><?= h(fin_mes_corto($m['mes'])) ?>: <?= h(cb_format_clp($m['egreso'])) ?> de inversión</title></rect>
            <?php endif; ?>
            <text x="<?= $x + 32 ?>" y="<?= $altoBarras + 20 ?>" text-anchor="middle"
                  font-size="12" font-weight="700" fill="var(--text-muted)"><?= h(fin_mes_corto($m['mes'])) ?></text>
          <?php endforeach; ?>
          <text x="<?= $margenIzq ?>" y="<?= $altoBarras + 33 ?>" font-size="11" fill="var(--text-muted)">
            Barra más alta: <?= h(cb_format_clp($tope)) ?>
          </text>
        </svg>
        </div>
        <?php endif; ?>
      </article>

      <article class="card">
        <h2>En qué se fue</h2>
        <p class="muted small">La inversión, repartida por categoría.</p>
        <?php if (!$r['categorias']): ?>
          <p class="fin-vacio">Sin inversiones cargadas todavía.</p>
        <?php else: ?>
          <div class="fin-barras">
            <?php foreach ($r['categorias'] as $c): ?>
              <div class="fin-barra-fila">
                <b><?= h($c['nombre']) ?></b>
                <span><?= h(cb_format_clp($c['monto'])) ?> · <?= number_format($c['parte'] * 100, 0) ?>%</span>
                <span class="fin-barra-pista">
                  <i style="width:<?= number_format(max(2, $c['parte'] * 100), 1, '.', '') ?>%"></i>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>

    </section>

    <!-- ============ CARGAR UN MOVIMIENTO ============ -->
    <section class="card">
      <h2>Anotar una compra o un ingreso</h2>
      <p class="muted small">
        El monto se puede escribir como se lee: <code>169.990</code> o <code>$169.990</code>.
      </p>
      <form method="post" class="fin-form">
        <?= admin_csrf_field() ?>
        <input type="hidden" name="action" value="guardar">

        <div class="field">
          <label for="fecha">Fecha</label>
          <input type="date" id="fecha" name="fecha" value="<?= h(date('Y-m-d')) ?>" required>
        </div>

        <div class="field">
          <label for="tipo">Qué es</label>
          <select id="tipo" name="tipo" required>
            <?php foreach (cb_finanzas_tipos() as $k => $v): ?>
              <option value="<?= h($k) ?>"><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="categoria">Categoría</label>
          <select id="categoria" name="categoria">
            <?php foreach (cb_finanzas_categorias() as $k => $v): ?>
              <option value="<?= h($k) ?>"<?= $k === 'insumos' ? ' selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="monto">Monto</label>
          <input type="text" id="monto" name="monto" inputmode="numeric" placeholder="169.990" required>
        </div>

        <div class="field">
          <label for="unidades">Unidades <span class="muted small">(opcional)</span></label>
          <input type="number" id="unidades" name="unidades" min="0" placeholder="36">
          <small class="muted">Hojas, imanes, acrílicos. Sirve para saber el costo por foto.</small>
        </div>

        <div class="field fin-ancho">
          <label for="descripcion">Qué compraste</label>
          <input type="text" id="descripcion" name="descripcion" maxlength="160"
                 placeholder="Canon SELPHY CP1500 con papel KP-36" required>
        </div>

        <div class="field fin-ancho">
          <label for="nota">Nota <span class="muted small">(opcional)</span></label>
          <input type="text" id="nota" name="nota" maxlength="500" placeholder="Comprado en Líder, boleta en el correo">
        </div>

        <div class="fin-ancho">
          <button class="btn btn-primary" type="submit" <?= $disponible ? '' : 'disabled' ?>>Guardar</button>
        </div>
      </form>
    </section>

    <!-- ============ LO CARGADO A MANO ============ -->
    <section class="card">
      <h2>Movimientos</h2>
      <p class="muted small">Lo que cargaste tú. Los ingresos de fiestas van en la tabla de abajo.</p>
      <?php if (!$r['movimientos']): ?>
        <p class="fin-vacio">Todavía no hay nada anotado.</p>
      <?php else: ?>
      <div class="fin-tabla-marco">
        <table class="fin-tabla">
          <thead>
            <tr>
              <th>Fecha</th><th>Qué</th><th>Categoría</th>
              <th class="fin-num">Monto</th><th class="fin-num">C/u</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($r['movimientos'] as $m): ?>
            <tr>
              <td><?= h(date('d/m/Y', strtotime($m['fecha']))) ?></td>
              <td>
                <?= h($m['descripcion']) ?>
                <?php if ($m['nota'] !== ''): ?><br><span class="muted small"><?= h($m['nota']) ?></span><?php endif; ?>
              </td>
              <td class="muted small"><?= h(cb_finanzas_categorias()[$m['categoria']] ?? $m['categoria']) ?></td>
              <td class="fin-num <?= $m['tipo'] === 'egreso' ? 'fin-egreso' : 'fin-ingreso' ?>">
                <?= $m['tipo'] === 'egreso' ? '−' : '+' ?><?= h(cb_format_clp($m['monto'])) ?>
              </td>
              <td class="fin-num muted small">
                <?= $m['unidades'] ? h(cb_format_clp((int) round($m['monto'] / max(1, $m['unidades'])))) : '—' ?>
              </td>
              <td>
                <form method="post" onsubmit="return confirm('¿Borrar este movimiento?')">
                  <?= admin_csrf_field() ?>
                  <input type="hidden" name="action" value="borrar">
                  <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                  <button class="fin-borrar" type="submit" title="Borrar" aria-label="Borrar"><?= admin_icon('trash') ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

    <!-- ============ LO QUE ENTRA POR LAS FIESTAS ============ -->
    <section class="card">
      <h2>Ingresos por fiesta</h2>
      <p class="muted small">
        Se lee del cobro de cada ficha. Para cambiar un monto, se edita la fiesta, no esta tabla.
      </p>
      <?php if (!$r['fiestas']): ?>
        <p class="fin-vacio">Ninguna fiesta tiene precio cargado todavía.</p>
      <?php else: ?>
      <div class="fin-tabla-marco">
        <table class="fin-tabla">
          <thead>
            <tr>
              <th>Fiesta</th><th>Fecha</th>
              <th class="fin-num">Precio</th><th class="fin-num">Descuento</th><th class="fin-num">Cobrado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($r['fiestas'] as $f): ?>
            <tr>
              <td>
                <a href="index.php?action=editar&amp;slug=<?= h(urlencode($f['slug'])) ?>"><?= h($f['nombre']) ?></a>
                <?php if (!$f['activa']): ?><br><span class="badge badge-off">Desactivada</span><?php endif; ?>
              </td>
              <td class="muted small"><?= $f['fecha'] !== '' ? h(date('d/m/Y', strtotime($f['fecha']))) : '—' ?></td>
              <td class="fin-num"><?= h(cb_format_clp($f['precio'])) ?></td>
              <td class="fin-num">
                <?php if ($f['regalado'] > 0): ?>
                  <span class="muted">−<?= h(cb_format_clp($f['regalado'])) ?></span>
                  <?php if ($f['motivo'] !== ''): ?><br><span class="muted small"><?= h($f['motivo']) ?></span><?php endif; ?>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="fin-num <?= $f['cobrado'] > 0 ? 'fin-ingreso' : 'muted' ?>"><?= h(cb_format_clp($f['cobrado'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

  </main>
</div>
</body>
</html>
