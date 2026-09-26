<?php
/**
 * admin/ferias.php — el modo feria en el panel (AT-CUMPLECLICK-020, 2026-09-26).
 *
 * Tres vistas en una página:
 *  - la lista de ferias, con fotos e impresiones de cada una;
 *  - la ficha (crear, editar, duplicar para la próxima): datos que van de recuerdo en cada foto y los mundos que se
 *    ofrecen en Niños y en Adultos;
 *  - la galería de una feria: buscar por número, nombre, mundo, modo y hora; ver; reimprimir en la Selphy 10×15 con
 *    el contador de impresiones; y mostrar el QR de la foto para que el visitante se la lleve al celular.
 *
 * Crear, editar y duplicar es solo del superadministrador. Un operador con el módulo `ferias` y la fiesta de la feria
 * asignada ve la galería de esa feria y reimprime; nada más. La galería pública y el Álbum no existen para una feria.
 */
require __DIR__ . '/../lib.ferias.php';
require __DIR__ . '/config.php';

$adminSecureCookie = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_name('cc_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $adminSecureCookie, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
require __DIR__ . '/_acceso.php';
admin_exigir('ferias');

function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function admin_csrf_token(): string { if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); } return $_SESSION['csrf']; }
function admin_csrf_check(): bool { $t = $_POST['csrf'] ?? ''; return is_string($t) && $t !== '' && hash_equals($_SESSION['csrf'] ?? '', $t); }
function admin_csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(admin_csrf_token()) . '">'; }
function feria_q(string $k, string $def = ''): string { return isset($_GET[$k]) && is_string($_GET[$k]) ? trim($_GET[$k]) : $def; }
function feria_fecha(string $ymd): string { return cb_feria_fecha_valida($ymd) ? substr($ymd, 8, 2) . '/' . substr($ymd, 5, 2) . '/' . substr($ymd, 0, 4) : '—'; }
function feria_url_foto(string $token, bool $inline = true): string { return '../ver.php?t=' . rawurlencode($token) . ($inline ? '&download=inline' : ''); }

/** La feria pedida, solo si este usuario la puede ver. */
function feria_cargar(int $id): array
{
    $feria = cb_feria_por_id($id);
    if ($feria === null) {
        http_response_code(404);
        exit('Esa feria no existe.');
    }
    if (!admin_fiesta_permitida($feria['slug'])) {
        admin_denegar('esa feria no está asignada a tu usuario');
    }
    return $feria;
}

$u = admin_usuario_actual();
$por = $u['id'] === 0 ? 'clave maestra' : (string) $u['email'];
$temas = cb_load_themes()['themes'] ?? [];
$listo = cb_ferias_listo();
$error = '';
$errores = [];
$form = null;      // ficha en edición: [datos, id|null]

// ── Acciones ────────────────────────────────────────────────────────────────
if ($listo && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $accion = (string) ($_POST['action'] ?? '');
    if (!admin_csrf_check()) {
        if ($accion === 'imprimir') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'sesion_vencida']);
            exit;
        }
        http_response_code(403);
        exit('La sesión del formulario venció. Vuelve a abrir la página.');
    }
    if ($accion === 'imprimir') {
        // La llama el botón Imprimir de la galería antes de abrir el diálogo de impresión.
        header('Content-Type: application/json; charset=utf-8');
        $feria = feria_cargar((int) ($_POST['feria'] ?? 0));
        $total = cb_feria_sumar_impresion($feria, (int) ($_POST['foto'] ?? 0), (int) ($_POST['copias'] ?? 1));
        echo json_encode($total === null ? ['ok' => false, 'error' => 'foto_no_encontrada'] : ['ok' => true, 'impresiones' => $total]);
        exit;
    }
    admin_exigir_super();
    try {
        if ($accion === 'guardar') {
            $id = (int) ($_POST['id'] ?? 0) ?: null;
            [$datos, $errores] = cb_feria_validar($_POST);
            if ($errores) {
                $form = [$datos, $id];
            } else {
                $feria = cb_feria_guardar($datos, $id, $por);
                header('Location: ferias.php?ok=' . ($id === null ? 'creada' : 'guardada') . '#feria-' . $feria['id'], true, 303);
                exit;
            }
        } elseif ($accion === 'duplicar') {
            $copia = cb_feria_duplicar((int) ($_POST['id'] ?? 0), $por);
            header('Location: ferias.php?editar=' . $copia['id'] . '&ok=duplicada', true, 303);
            exit;
        } else {
            $error = 'Acción desconocida.';
        }
    } catch (DomainException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('CumpleClick admin ferias: ' . $e->getMessage());
        $error = 'No se pudo guardar. Revisa que la migración 026 esté aplicada y vuelve a intentar.';
    }
}

// ── Qué vista toca ──────────────────────────────────────────────────────────
$vista = 'lista';
$feria = null;
if (!$listo) {
    $vista = 'sin-tablas';
} elseif ($form !== null) {
    $vista = 'ficha';
} elseif (feria_q('nueva') === '1') {
    admin_exigir_super();
    $vista = 'ficha';
    $hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d');
    $form = [['nombre' => '', 'organizador' => '', 'organizador_ig' => '', 'lugar' => '', 'fecha' => $hoy, 'hora_inicio' => '',
        'mesa' => '', 'notas' => '', 'mundos_infantil' => [], 'mundos_adulto' => [], 'retencion_dias' => 7, 'max_fotos' => 1000,
        'activa' => false], null];
} elseif (feria_q('editar') !== '') {
    admin_exigir_super();
    $vista = 'ficha';
    $f = feria_cargar((int) feria_q('editar'));
    $form = [array_merge($f, ['mundos_infantil' => $f['mundos']['infantil'], 'mundos_adulto' => $f['mundos']['adulto']]), $f['id']];
} elseif (feria_q('galeria') !== '') {
    $vista = 'galeria';
    $feria = feria_cargar((int) feria_q('galeria'));
}

$ferias = $vista === 'lista' ? array_values(array_filter(cb_ferias_listar(), static fn($f) => admin_fiesta_permitida($f['slug']))) : [];
$okTexto = ['creada' => 'Feria creada.', 'guardada' => 'Feria guardada.', 'duplicada' => 'Feria duplicada: revisa la fecha y los datos, y actívala cuando esté lista.'][feria_q('ok')] ?? '';
$base = rtrim(cb_public_base_url(), '/');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ferias · CumpleClick</title>
<style><?php require __DIR__ . '/_style.css.php'; ?></style>
<style>
.feria-wrap { max-width: 1280px; }
.feria-brand { display: flex; align-items: center; gap: 14px; }
.feria-brand img { width: 56px; height: 56px; }
.feria-brand h1, .feria-brand p { margin: 0; }
.feria-barra { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin: 20px 0; }
.feria-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
.feria-card h2 { font-size: 1.2rem; margin-bottom: 4px; }
.feria-datos { list-style: none; padding: 0; margin: 10px 0; display: grid; gap: 4px; font-size: .92rem; }
.feria-cifras { display: flex; gap: 18px; margin: 10px 0; font-variant-numeric: tabular-nums; }
.feria-cifras strong { display: block; font-size: 1.5rem; line-height: 1.1; }
.feria-acciones { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.feria-acciones form { margin: 0; }
.feria-estado { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: .78rem; font-weight: 700; }
.feria-estado.activa { background: var(--success-soft); color: #14532d; }
.feria-estado.inactiva { background: var(--bg2); color: var(--text-muted); }
.feria-estado.archivada { background: var(--danger-soft); color: #7f1d1d; }
.feria-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.feria-ancho { grid-column: 1 / -1; }
.feria-mundos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.feria-mundos fieldset { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px 14px; margin: 0; min-width: 0; }
.feria-mundos legend { font-weight: 800; padding: 0 6px; }
.feria-mundo { display: flex; align-items: center; gap: 8px; padding: 6px 0; min-height: 44px; }
.feria-mundo input { width: 22px; height: 22px; }
.feria-mundo small { color: var(--text-muted); }
.feria-filtros { display: grid; grid-template-columns: 2fr repeat(4, minmax(0, 1fr)) auto; gap: 10px; align-items: end; }
.feria-filtros .btn { min-height: 44px; }
.feria-grid { list-style: none; padding: 0; margin: 16px 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }
.feria-foto { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; display: flex; flex-direction: column; }
.feria-foto-media { position: relative; aspect-ratio: 9 / 16; background: var(--bg2); }
.feria-foto-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
.feria-num { position: absolute; top: 8px; left: 8px; background: #111; color: #fff; font-weight: 800; padding: 3px 10px; border-radius: 999px; font-variant-numeric: tabular-nums; }
.feria-foto-body { padding: 10px 12px; display: grid; gap: 6px; font-size: .88rem; }
.feria-foto-body strong { font-size: 1rem; }
.feria-foto-acc { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.feria-foto-acc .btn { padding: 8px 4px; min-height: 44px; font-size: .85rem; justify-content: center; }
.feria-recuerdo { font-weight: 700; }
.feria-pag { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.feria-impresion { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin: 10px 0; }
.feria-impresion select { min-height: 40px; }
dialog.feria-qr { border: 0; border-radius: var(--radius); padding: 24px; text-align: center; max-width: 92vw; }
dialog.feria-qr::backdrop { background: rgba(0, 0, 0, .6); }
dialog.feria-qr canvas { display: block; margin: 12px auto; max-width: 100%; height: auto; }
#hoja-imprimir { display: none; }
#aviso-imprimir { position: fixed; inset: 0; z-index: 60; display: none; place-items: center; background: rgba(0, 0, 0, .8); color: #fff; font-weight: 800; font-size: 1.2rem; }
#aviso-imprimir.on { display: grid; }
@media print {
  @page { margin: 0; }
  body { background: #fff; }
  body > *:not(#hoja-imprimir) { display: none !important; }
  #hoja-imprimir { display: block !important; }
  .pagina-foto { width: 100vw; height: 100vh; display: flex; align-items: center; justify-content: center; page-break-after: always; break-after: page; overflow: hidden; background: #fff; }
  .pagina-foto:last-child { page-break-after: auto; break-after: auto; }
  .pagina-foto img { width: 100%; height: 100%; object-fit: contain; }
  #hoja-imprimir.llenar .pagina-foto img { object-fit: cover; }
}
@media (max-width: 760px) {
  .feria-form, .feria-mundos { grid-template-columns: 1fr; }
  .feria-ancho { grid-column: auto; }
  .feria-filtros { grid-template-columns: 1fr 1fr; }
  .feria-filtros .feria-q { grid-column: 1 / -1; }
}
</style>
</head>
<body>
<div class="wrap feria-wrap">
<header class="topbar">
  <div class="feria-brand"><img src="../brand/cumpleclick-mark.svg" alt=""><div><h1>Ferias</h1><p class="muted">Mundos de la cabina, galería y reimpresiones de cada feria.</p></div></div>
  <?= admin_usuario_chip() ?>
</header>
<?= admin_nav('ferias') ?>
<main>
<?php if ($error !== ''): ?><p class="alert alert-error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($okTexto !== ''): ?><p class="alert alert-ok" role="status"><?= h($okTexto) ?></p><?php endif; ?>

<?php if ($vista === 'sin-tablas'): ?>
  <section class="card"><h2>El modo feria todavía no está instalado</h2>
  <p>Falta aplicar la migración 026 en la base. Mientras tanto, las fiestas funcionan igual que siempre.</p></section>

<?php elseif ($vista === 'lista'): ?>
  <div class="feria-barra">
    <p class="muted"><?= count($ferias) === 1 ? '1 feria' : count($ferias) . ' ferias' ?>. Cada una guarda sus fotos <?= h('7') ?> días, o lo que diga su ficha.</p>
    <?php if (admin_es_super()): ?><a class="btn btn-cta" href="?nueva=1">+ Nueva feria</a><?php endif; ?>
  </div>
  <?php if (!$ferias): ?>
    <section class="card"><h2>Todavía no hay ferias</h2><p>Crea la ficha de la feria, marca los mundos de Niños y Adultos y actívala: la tablet se abre con el enlace de la ficha.</p></section>
  <?php endif; ?>
  <div class="feria-lista">
  <?php foreach ($ferias as $f):
      $estado = $f['anonimizada'] ? 'archivada' : ($f['activa'] ? 'activa' : 'inactiva');
      $selector = $base . '/feria.html?f=' . rawurlencode($f['slug']); ?>
    <section class="card feria-card" id="feria-<?= (int) $f['id'] ?>">
      <span class="feria-estado <?= h($estado) ?>"><?= h(['activa' => 'Activa', 'inactiva' => 'Inactiva', 'archivada' => 'Archivada: fotos borradas'][$estado]) ?></span>
      <h2><?= h($f['nombre']) ?></h2>
      <ul class="feria-datos">
        <li><?= h(feria_fecha($f['fecha'])) ?><?= $f['hora_inicio'] !== '' ? ' · desde las ' . h($f['hora_inicio']) : '' ?><?= $f['mesa'] !== '' ? ' · mesa ' . h($f['mesa']) : '' ?></li>
        <?php if ($f['lugar'] !== ''): ?><li><?= h($f['lugar']) ?></li><?php endif; ?>
        <?php if ($f['organizador'] !== ''): ?><li>Organiza <?= h($f['organizador']) ?> <?= h($f['organizador_ig']) ?></li><?php endif; ?>
        <li class="muted">Niños: <?= h(count($f['mundos']['infantil'])) ?> mundos · Adultos: <?= h(count($f['mundos']['adulto'])) ?> mundos · fotos por <?= (int) $f['retencion_dias'] ?> días</li>
      </ul>
      <div class="feria-cifras"><span><strong><?= (int) $f['fotos'] ?></strong>fotos</span><span><strong><?= (int) $f['impresiones'] ?></strong>impresiones</span></div>
      <div class="feria-acciones">
        <a class="btn btn-primary" href="?galeria=<?= (int) $f['id'] ?>">Galería</a>
        <?php if (admin_es_super()): ?>
          <a class="btn btn-ghost" href="?editar=<?= (int) $f['id'] ?>">Editar</a>
          <form method="post"><?= admin_csrf_field() ?><input type="hidden" name="action" value="duplicar"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><button class="btn btn-ghost" type="submit">Duplicar para otra feria</button></form>
        <?php endif; ?>
      </div>
      <?php if ($estado === 'activa'): ?>
        <details style="margin-top:12px"><summary>Abrir en la tablet</summary>
          <p class="small">Enlace del selector de mundos: <a href="<?= h($selector) ?>" target="_blank" rel="noopener"><?= h($selector) ?></a></p>
          <button class="btn btn-ghost" type="button" data-qr="<?= h($selector) ?>" data-qr-titulo="<?= h($f['nombre']) ?>" data-qr-texto="Escanéalo con la cámara de la tablet para abrir la cabina de la feria.">Ver QR del enlace</button>
        </details>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
  </div>

<?php elseif ($vista === 'ficha'):
    [$d, $fid] = $form; ?>
  <section class="card">
    <a href="ferias.php">← Volver a las ferias</a>
    <h2><?= $fid === null ? 'Nueva feria' : 'Editar feria' ?></h2>
    <p class="muted">El nombre, el organizador y la fecha van de recuerdo en cada foto: «<?= h(cb_feria_recuerdo($d) ?: 'Nombre de la feria · Organizador · fecha') ?>».</p>
    <?php if ($errores): ?><div class="alert alert-error" role="alert">Revisa la ficha:<ul><?php foreach ($errores as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="feria-form">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="guardar"><?php if ($fid !== null): ?><input type="hidden" name="id" value="<?= (int) $fid ?>"><?php endif; ?>
      <div class="field feria-ancho"><label for="nombre">Nombre de la feria</label><input type="text" id="nombre" name="nombre" maxlength="160" required value="<?= h($d['nombre']) ?>" placeholder="Por ejemplo: Mini Paseo Dieciochero"></div>
      <div class="field"><label for="organizador">Organiza</label><input type="text" id="organizador" name="organizador" maxlength="160" value="<?= h($d['organizador']) ?>"></div>
      <div class="field"><label for="organizador_ig">Instagram del organizador</label><input type="text" id="organizador_ig" name="organizador_ig" maxlength="31" value="<?= h($d['organizador_ig']) ?>" placeholder="@usuario"></div>
      <div class="field"><label for="fecha">Fecha</label><input type="date" id="fecha" name="fecha" required value="<?= h($d['fecha']) ?>"></div>
      <div class="field"><label for="hora_inicio">Hora de inicio</label><input type="time" id="hora_inicio" name="hora_inicio" value="<?= h($d['hora_inicio']) ?>"></div>
      <div class="field"><label for="lugar">Lugar</label><input type="text" id="lugar" name="lugar" maxlength="255" value="<?= h($d['lugar']) ?>"></div>
      <div class="field"><label for="mesa">Mesa o stand</label><input type="text" id="mesa" name="mesa" maxlength="40" value="<?= h($d['mesa']) ?>"></div>
      <div class="field feria-ancho"><label for="notas">Notas internas</label><textarea id="notas" name="notas" rows="3" maxlength="2000"><?= h($d['notas']) ?></textarea><small>No salen en las fotos ni en la tablet.</small></div>
      <div class="feria-ancho feria-mundos">
        <?php foreach (['infantil' => 'Niños', 'adulto' => 'Adultos'] as $modo => $etiqueta): ?>
          <fieldset><legend><?= h($etiqueta) ?></legend>
          <?php foreach ($temas as $slug => $t):
              if (!is_array($t) || !cb_feria_tema_permitido($t, $modo)) { continue; }
              $marcado = in_array($slug, (array) $d['mundos_' . $modo], true);
              $notas = [];
              if (($t['audiencia'] ?? '') === 'adulto') { $notas[] = 'para adultos'; }
              if (($t['modalidad'] ?? '') === 'baby_shower') { $notas[] = 'baby shower'; }
              if (empty($t['personajes'])) { $notas[] = 'sin ruleta'; } ?>
            <label class="feria-mundo"><input type="checkbox" name="mundos_<?= h($modo) ?>[]" value="<?= h($slug) ?>" <?= $marcado ? 'checked' : '' ?>>
              <span><?= h($t['nombre'] ?? $slug) ?><?= $notas ? ' <small>(' . h(implode(', ', $notas)) . ')</small>' : '' ?></span></label>
          <?php endforeach; ?>
          </fieldset>
        <?php endforeach; ?>
      </div>
      <div class="field"><label for="retencion_dias">Días que se guardan las fotos</label><input type="number" id="retencion_dias" name="retencion_dias" min="1" max="30" value="<?= (int) $d['retencion_dias'] ?>"><small>Después se borran solas.</small></div>
      <div class="field"><label for="max_fotos">Tope de fotos</label><input type="number" id="max_fotos" name="max_fotos" min="10" max="5000" value="<?= (int) $d['max_fotos'] ?>"></div>
      <label class="feria-mundo feria-ancho"><input type="checkbox" name="activa" value="1" <?= !empty($d['activa']) ? 'checked' : '' ?>> Feria activa: la tablet la puede abrir</label>
      <div class="feria-acciones feria-ancho"><button class="btn btn-cta" type="submit">Guardar feria</button><a class="btn btn-ghost" href="ferias.php">Cancelar</a></div>
    </form>
  </section>

<?php elseif ($vista === 'galeria'):
    $filtros = ['q' => feria_q('q'), 'modo' => feria_q('modo'), 'tema' => feria_q('tema'), 'desde' => feria_q('desde'), 'hasta' => feria_q('hasta')];
    $gal = cb_feria_galeria($feria, $filtros, max(1, (int) feria_q('pag', '1')), 24);
    $sinNumero = cb_feria_fotos_sin_numero($feria);
    $mundosFeria = array_values(array_unique(array_merge($feria['mundos']['infantil'], $feria['mundos']['adulto'])));
    $qsBase = array_filter(['galeria' => $feria['id']] + $filtros, static fn($v) => $v !== '' && $v !== null); ?>
  <section class="card">
    <a href="ferias.php">← Volver a las ferias</a>
    <h2><?= h($feria['nombre']) ?></h2>
    <p class="feria-recuerdo"><?= h(cb_feria_recuerdo($feria)) ?><?= $feria['organizador_ig'] !== '' ? ' · ' . h($feria['organizador_ig']) : '' ?></p>
    <p class="muted"><?= h($feria['lugar']) ?><?= $feria['mesa'] !== '' ? ' · mesa ' . h($feria['mesa']) : '' ?> · las fotos se guardan <?= (int) $feria['retencion_dias'] ?> días desde la fecha de la feria.</p>
    <form method="get" class="feria-filtros" role="search">
      <input type="hidden" name="galeria" value="<?= (int) $feria['id'] ?>">
      <div class="field feria-q"><label for="q">Número o nombre</label><input type="search" id="q" name="q" value="<?= h($filtros['q']) ?>" placeholder="F-027 o Sofía"></div>
      <div class="field"><label for="modo">Modo</label><select id="modo" name="modo"><option value="">Todos</option><?php foreach (CB_FERIA_MODOS as $k => $v): ?><option value="<?= h($k) ?>" <?= $filtros['modo'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="tema">Mundo</label><select id="tema" name="tema"><option value="">Todos</option><?php foreach ($mundosFeria as $s): ?><option value="<?= h($s) ?>" <?= $filtros['tema'] === $s ? 'selected' : '' ?>><?= h($temas[$s]['nombre'] ?? $s) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="desde">Desde</label><input type="time" id="desde" name="desde" value="<?= h($filtros['desde']) ?>"></div>
      <div class="field"><label for="hasta">Hasta</label><input type="time" id="hasta" name="hasta" value="<?= h($filtros['hasta']) ?>"></div>
      <button class="btn btn-primary" type="submit">Buscar</button>
    </form>
    <div class="feria-impresion">
      <label>Papel <select id="papel"><option value="100mm 148mm" selected>10×15 cm (Selphy)</option><option value="127mm 178mm">13×18 cm</option><option value="A4 portrait">A4</option><option value="auto">Según impresora</option></select></label>
      <label>Copias <select id="copias"><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label>
      <label><input type="checkbox" id="llenar" checked> Llenar la hoja</label>
    </div>
    <p class="muted"><?= $gal['total'] === 1 ? '1 foto' : (int) $gal['total'] . ' fotos' ?><?= array_filter($filtros) ? ' con esta búsqueda' : '' ?>. La hora es la de Chile.
      <?php if ($sinNumero > 0): ?> Hay <?= $sinNumero ?> foto<?= $sinNumero === 1 ? '' : 's' ?> sin número (la tablet no alcanzó a reservarlo): <a href="album.php?party=<?= h(rawurlencode($feria['slug'])) ?>#fotos-kiosco">verlas en Fotos del kiosco</a>.<?php endif; ?></p>
  </section>

  <?php if (!$gal['fotos']): ?>
    <section class="card"><p><?= array_filter($filtros) ? 'Ninguna foto calza con la búsqueda.' : 'Esta feria todavía no tiene fotos con número.' ?></p></section>
  <?php else: ?>
    <ol class="feria-grid">
    <?php foreach ($gal['fotos'] as $foto): $url = $base . '/ver.php?t=' . rawurlencode($foto['token']); ?>
      <li class="feria-foto" id="foto-<?= (int) $foto['id'] ?>">
        <div class="feria-foto-media"><img loading="lazy" src="<?= h(feria_url_foto($foto['token'])) ?>" alt="Foto <?= h($foto['etiqueta']) ?>"><span class="feria-num"><?= h($foto['etiqueta']) ?></span></div>
        <div class="feria-foto-body">
          <strong><?= h($foto['nombre'] !== '' ? $foto['nombre'] : 'Sin nombre') ?></strong>
          <span><?= h($temas[$foto['tema']]['nombre'] ?? $foto['tema']) ?> · <?= h(CB_FERIA_MODOS[$foto['modo']] ?? $foto['modo']) ?> · <?= h($foto['hora']) ?></span>
          <span class="muted" data-impresiones><?= $foto['impresiones'] === 1 ? '1 impresión' : (int) $foto['impresiones'] . ' impresiones' ?></span>
          <div class="feria-foto-acc">
            <a class="btn btn-ghost" href="<?= h(feria_url_foto($foto['token'], false)) ?>" target="_blank" rel="noopener">Ver</a>
            <button class="btn btn-primary" type="button" data-imprimir="<?= (int) $foto['id'] ?>" data-src="<?= h(feria_url_foto($foto['token'])) ?>">Imprimir</button>
            <button class="btn btn-ghost" type="button" data-qr="<?= h($url) ?>" data-qr-titulo="Foto <?= h($foto['etiqueta']) ?>" data-qr-texto="Escanéalo con la cámara del celular para guardar la foto.">QR</button>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
    </ol>
    <?php if ($gal['paginas'] > 1): ?>
      <nav class="feria-pag" aria-label="Páginas">
      <?php for ($p = 1; $p <= $gal['paginas']; $p++): ?>
        <a class="btn <?= $p === $gal['pagina'] ? 'btn-primary' : 'btn-ghost' ?>" href="?<?= h(http_build_query($qsBase + ['pag' => $p])) ?>" <?= $p === $gal['pagina'] ? 'aria-current="page"' : '' ?>><?= $p ?></a>
      <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
  <div id="hoja-imprimir" aria-hidden="true"></div>
  <div id="aviso-imprimir">Preparando la impresión…</div>
  <form id="form-imprimir" hidden><?= admin_csrf_field() ?><input type="hidden" name="feria" value="<?= (int) $feria['id'] ?>"></form>
<?php endif; ?>

<dialog class="feria-qr" id="dialogo-qr" aria-labelledby="qr-titulo">
  <h2 id="qr-titulo"></h2>
  <canvas id="qr-canvas" width="320" height="320"></canvas>
  <p id="qr-texto" class="muted"></p>
  <form method="dialog"><button class="btn btn-primary">Cerrar</button></form>
</dialog>
</main>
</div>
<script src="qrcode.min.js"></script>
<script>
(function () {
  /* QR: el de la foto (para llevársela al celular) y el del enlace de la tablet. Se dibuja en el navegador con la
     misma librería que usa el kiosco; el enlace no sale de la página. */
  var dialogo = document.getElementById('dialogo-qr');
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-qr]');
    if (!b || !dialogo) return;
    document.getElementById('qr-titulo').textContent = b.getAttribute('data-qr-titulo') || 'QR';
    document.getElementById('qr-texto').textContent = b.getAttribute('data-qr-texto') || '';
    var canvas = document.getElementById('qr-canvas');
    if (window.QRCode) {
      window.QRCode.toCanvas(canvas, b.getAttribute('data-qr'), { width: 320, margin: 2 }, function () {});
    }
    dialogo.showModal();
  });

  /* Reimprimir: primero se anota la impresión en el servidor y después se abre el diálogo de impresión con una
     página por copia. Se espera a que la imagen cargue: sin la espera la primera hoja sale en blanco en la tablet
     (mismo mecanismo de la galería y del Álbum, probado con la Selphy). Si anotar falla, se imprime igual. */
  var hoja = document.getElementById('hoja-imprimir');
  var aviso = document.getElementById('aviso-imprimir');
  var form = document.getElementById('form-imprimir');
  if (!hoja || !form) return;
  document.body.appendChild(hoja);
  document.body.appendChild(aviso);
  var papel = document.getElementById('papel');
  try { var guardado = localStorage.getItem('cc-admin-papel'); if (guardado) papel.value = guardado; } catch (e) { /* sin almacenamiento */ }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-imprimir]');
    if (!b) return;
    var copias = parseInt(document.getElementById('copias').value, 10) || 1;
    var datos = new FormData(form);
    datos.append('action', 'imprimir');
    datos.append('foto', b.getAttribute('data-imprimir'));
    datos.append('copias', String(copias));
    b.disabled = true;
    fetch('ferias.php', { method: 'POST', body: datos, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.ok) {
          var t = b.closest('.feria-foto').querySelector('[data-impresiones]');
          if (t) t.textContent = j.impresiones === 1 ? '1 impresión' : j.impresiones + ' impresiones';
        }
      })
      .catch(function () { /* se imprime igual; el contador se corrige con la próxima */ })
      .then(function () { b.disabled = false; imprimir(b.getAttribute('data-src'), copias); });
  });

  function imprimir(src, copias) {
    hoja.classList.toggle('llenar', document.getElementById('llenar').checked);
    var estilo = document.getElementById('papel-css');
    if (!estilo) { estilo = document.createElement('style'); estilo.id = 'papel-css'; document.head.appendChild(estilo); }
    estilo.textContent = papel.value === 'auto' ? '' : '@media print { @page { size: ' + papel.value + '; margin: 0; } }';
    try { localStorage.setItem('cc-admin-papel', papel.value); } catch (e) { /* sin almacenamiento */ }
    hoja.innerHTML = '';
    var esperas = [];
    for (var i = 0; i < copias; i++) {
      var pagina = document.createElement('div');
      pagina.className = 'pagina-foto';
      var img = document.createElement('img');
      img.alt = '';
      esperas.push(new Promise(function (resolve) { img.onload = resolve; img.onerror = resolve; setTimeout(resolve, 8000); }));
      img.src = src;
      pagina.appendChild(img);
      hoja.appendChild(pagina);
    }
    aviso.classList.add('on');
    Promise.all(esperas).then(function () { aviso.classList.remove('on'); window.print(); });
  }
  window.addEventListener('afterprint', function () { hoja.innerHTML = ''; });
})();
</script>
</body>
</html>
