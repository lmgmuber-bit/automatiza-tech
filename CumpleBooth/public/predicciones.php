<?php
/**
 * Tablero privado de predicciones para los papás.
 *
 * No es un panel de control: es un recuerdo que los papás abren, miran juntos y
 * muchas veces imprimen. Por eso las apuestas se ven como boletas de papel
 * inclinadas sobre una mesa y no como filas de una tabla, y por eso el diseño
 * habla el mismo idioma que la revista del Álbum Recuerdo —papel, inclinación
 * alternada, cinta— en vez de inventar un lenguaje visual nuevo.
 *
 * Todos los colores salen de la temática del evento vía cb_theme_css_vars(). No
 * hay un solo color de temática escrito acá.
 */
require __DIR__ . '/lib.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$token = isset($_GET['t']) ? (string) $_GET['t'] : '';
$access = null;
$predictions = [];
$invalid = false;

try {
    $access = cb_invitation_resolve_role_token($token, 'parents');
    if ($access === null) {
        $invalid = true;
    } else {
        $predictions = cb_prediction_list_for_party((int) $access['party_id']);
    }
} catch (Throwable $e) {
    error_log('CumpleClick predictions board: ' . $e->getMessage());
    $invalid = true;
}

// El nombre del bebé, y NUNCA admin_label: esa es la etiqueta interna del panel
// —cosas como "DEMO Baby shower — Safari (aun no saben)"— y se estaba colando
// en el título que ve el invitado.
//
// Cuando no hay nombre no se puede rellenar el hueco: en un baby shower "aún no
// saben" no hay nombre que poner, y "Las predicciones de " seguido de nada es
// justamente lo que se quiere evitar. Cambia la frase entera.
$eventName = trim((string) ($access['birthday_person_name'] ?? ''));
if ($eventName !== '') {
    $tituloPagina = 'Las predicciones de ' . $eventName;
    $tituloPestana = 'Predicciones · ' . $eventName;
} else {
    $tituloPagina = 'Las predicciones del baby shower';
    $tituloPestana = 'Predicciones del baby shower';
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
$topCount = max(1, max($counts));

/**
 * created_at se guarda con gmdate(), o sea en UTC. Mostrarlo con date() lo deja
 * a merced de la zona del servidor: en Hostinger, que corre en UTC, los papás
 * verían cada apuesta tres o cuatro horas corrida. Se convierte explícito.
 */
$horaLocal = static function ($value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }
    try {
        $fecha = new DateTimeImmutable($raw, new DateTimeZone('UTC'));
        return $fecha->setTimezone(new DateTimeZone('America/Santiago'))->format('d/m/Y · H:i');
    } catch (Throwable $e) {
        return '';
    }
};
$isoUtc = static function ($value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($raw, new DateTimeZone('UTC')))->format(DATE_ATOM);
    } catch (Throwable $e) {
        return '';
    }
};
?>
<!doctype html>
<html lang="es-CL">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title><?= $invalid ? 'Enlace no disponible' : $escape($tituloPestana) ?> · CumpleClick</title>
  <link rel="icon" href="brand/cumpleclick-mark.svg" type="image/svg+xml">
  <style>
    /* Baloo 2 self-hosted, nunca CDN. Sin esto la hoja declara la fuente y el
       navegador cae a la del sistema: la tipografía de la marca cambiaría de
       forma en cada aparato. */
    @font-face {
      font-family: "Baloo 2";
      src: url("admin/fonts/baloo2-600.woff2") format("woff2");
      font-weight: 600; font-style: normal; font-display: swap;
    }
    @font-face {
      font-family: "Baloo 2";
      src: url("admin/fonts/baloo2-800.woff2") format("woff2");
      font-weight: 800; font-style: normal; font-display: swap;
    }

    /* Los nombres de variable son los que publica cb_theme_css_vars(): --pink,
       --ink, --dark1... Cualquier otro nombre no llega nunca y la página se
       queda con el color de respaldo sin avisar. Los respaldos de abajo son la
       paleta de marca de CumpleClick, para cuando el evento no tiene temática. */
    :root {
      --pink: #8B5CF6;
      --pink-soft: #EDE4FB;
      --yellow: #FBBF24;
      --ink: #2b1a12;
      --bg-light1: #FFF8EC;
      --bg-light2: #F3E9FB;
      --dark1: #4C2882;
      --dark2: #30204f;
      --dark3: #A78BFA;
      <?= $themeVars ?>

      --papel: color-mix(in srgb, var(--bg-light1) 55%, #fff);
      --tenue: color-mix(in srgb, var(--ink) 58%, transparent);
      --fuente: "Baloo 2", "Segoe UI Rounded", "Segoe UI", system-ui, sans-serif;
    }

    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh;
      color: var(--ink);
      font-family: var(--fuente);
      background:
        radial-gradient(circle at 12% 8%, color-mix(in srgb, var(--dark3) 26%, transparent), transparent 42%),
        linear-gradient(168deg, var(--bg-light1), var(--bg-light2));
    }
    /* Textura de papel: puntitos muy tenues. Da superficie sin llamar la
       atención, que es lo que una hoja necesita para no parecer una pantalla. */
    body::before {
      content: ""; position: fixed; inset: 0; pointer-events: none; opacity: .5;
      background-image: radial-gradient(circle, color-mix(in srgb, var(--ink) 12%, transparent) 1px, transparent 1.4px);
      background-size: 22px 22px;
    }

    main { position: relative; width: min(1060px, calc(100% - 28px)); margin: 0 auto; padding: 26px 0 72px; }

    /* ── Marca ─────────────────────────────────────────────────────────────
       Isotipo oficial más el nombre como texto real. El SVG del lockup dibuja
       la palabra con un <text> en Baloo 2 y, dentro de un <img>, se renderiza
       aislado sin ver las @font-face de arriba: el nombre saldría en otra
       fuente según el aparato. */
    .marca { display: inline-flex; align-items: center; gap: 9px; font-weight: 800; font-size: 1.06rem; }
    .marca img { width: 30px; height: 30px; display: block; }
    .marca span { color: var(--dark1); }

    /* ── Cabecera ──────────────────────────────────────────────────────── */
    .hero {
      margin: 20px 0 22px; padding: clamp(26px, 6vw, 54px);
      border-radius: 30px; position: relative; overflow: hidden;
      background: linear-gradient(140deg, var(--dark1), var(--dark2));
      color: #fff;
      box-shadow: 0 26px 60px color-mix(in srgb, var(--dark1) 30%, transparent);
    }
    .hero::after {
      content: ""; position: absolute; right: -70px; top: -90px;
      width: 250px; height: 250px; border-radius: 50%;
      border: 44px solid color-mix(in srgb, var(--dark3) 26%, transparent);
    }
    .hero > * { position: relative; }
    .eyebrow {
      margin: 0; font-size: .78rem; font-weight: 800;
      letter-spacing: .16em; text-transform: uppercase; color: var(--yellow);
    }
    .hero h1 { margin: .16em 0 .3em; font-size: clamp(2.1rem, 7vw, 4.2rem); line-height: .96; max-width: 15ch; }
    .hero p { margin: 0; max-width: 54ch; font-size: clamp(1rem, 2.4vw, 1.18rem); line-height: 1.45; opacity: .93; }

    /* ── Barra de acciones ─────────────────────────────────────────────── */
    /* El puente entre las dos pantallas de los papás. El mismo token abre
       las dos, así que basta llegar a una para llegar a la otra. */
    .otra-pantalla { display:flex; justify-content:center; gap:8px; margin:0 0 20px; flex-wrap:wrap; }
    .otra-pantalla a, .otra-pantalla span {
      font: 600 .86rem/1 var(--fuente); padding:9px 15px; border-radius:999px;
      text-decoration:none; border:1px solid color-mix(in srgb, var(--ink) 14%, transparent);
    }
    .otra-pantalla span { background:var(--pink-soft); color:var(--dark1); border-color:transparent; }
    .otra-pantalla a { color:var(--tenue); background:transparent; }
    .otra-pantalla a:hover { color:var(--dark1); border-color:var(--dark3); }
    .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin: 0 0 18px; }
    .cuenta { font-weight: 800; font-size: 1.06rem; }
    .cuenta b { color: var(--pink); font-size: 1.35rem; }
    .imprimir {
      border: 0; border-radius: 999px; padding: 13px 22px;
      background: var(--dark1); color: #fff;
      font: inherit; font-weight: 800; cursor: pointer;
      box-shadow: 0 8px 20px color-mix(in srgb, var(--dark1) 32%, transparent);
    }
    .imprimir:hover { background: var(--dark2); }

    /* ── Quién gana ────────────────────────────────────────────────────────
       Barras proporcionales en vez de tres números sueltos: los papás quieren
       saber quién va ganando de un vistazo, y tres cifras iguales de tamaño no
       lo dicen. */
    .marcador { margin: 0 0 22px; padding: 20px 22px; border-radius: 24px; background: var(--papel);
      border: 1px solid color-mix(in srgb, var(--ink) 10%, transparent); }
    .marcador h2 { margin: 0 0 14px; font-size: 1.05rem; letter-spacing: .04em; text-transform: uppercase; color: var(--tenue); }
    .barra { display: grid; grid-template-columns: 8.5ch 1fr auto; align-items: center; gap: 12px; margin-bottom: 9px; }
    .barra:last-child { margin-bottom: 0; }
    .barra span { font-weight: 800; }
    .barra .pista { height: 15px; border-radius: 999px; background: color-mix(in srgb, var(--ink) 8%, transparent); overflow: hidden; }
    .barra .relleno { height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--pink), var(--dark3)); min-width: 4px; }
    .barra b { font-size: 1.2rem; color: var(--pink); min-width: 2ch; text-align: right; }

    /* ── Las boletas ───────────────────────────────────────────────────────
       Inclinación alternada por posición, igual que las fotos de la revista:
       es lo que hace que veinte apuestas se lean como un muro de papelitos y
       no como una planilla. */
    .muro { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .boleta {
      position: relative; padding: 24px 22px 20px; border-radius: 18px;
      background: var(--papel);
      border: 1px solid color-mix(in srgb, var(--ink) 12%, transparent);
      box-shadow: 0 14px 30px color-mix(in srgb, var(--ink) 12%, transparent);
      break-inside: avoid;
    }
    .boleta:nth-child(odd)  { transform: rotate(-.5deg); }
    .boleta:nth-child(even) { transform: rotate(.6deg); }
    /* Cinta adhesiva. Va montada sobre el borde —mitad afuera, mitad encima del
       papel— porque una cinta que no pisa la hoja se lee como una barra suelta
       en vez de como algo que sujeta. Es el mismo recurso que usan las
       dedicatorias de la revista, y ahi esta la continuidad del producto. */
    .boleta__cinta {
      position: absolute; top: -8px; left: 50%; width: 86px; height: 22px;
      transform: translateX(-50%) rotate(-1.6deg);
      background: color-mix(in srgb, var(--yellow) 72%, transparent);
      box-shadow: 0 2px 6px color-mix(in srgb, var(--ink) 16%, transparent);
      border-left: 2px dashed color-mix(in srgb, #fff 60%, transparent);
      border-right: 2px dashed color-mix(in srgb, #fff 60%, transparent);
    }
    .boleta__alto { display: flex; align-items: start; justify-content: space-between; gap: 12px; }
    .boleta h3 { margin: 0; font-size: 1.4rem; line-height: 1.1; }
    .puntaje {
      white-space: nowrap; padding: 6px 12px; border-radius: 999px; font-weight: 800;
      background: var(--pink-soft); color: var(--dark1);
    }
    .apuesta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; margin: 18px 0 0; }
    .dato {
      padding: 12px 11px; border-radius: 13px;
      background: color-mix(in srgb, var(--bg-light2) 42%, #fff);
      border: 1px solid color-mix(in srgb, var(--ink) 8%, transparent);
    }
    .dato small { display: block; font-size: .72rem; letter-spacing: .05em; text-transform: uppercase; color: var(--tenue); }
    .dato strong { display: block; margin-top: 3px; line-height: 1.18; }
    .cuando { display: block; margin-top: 15px; font-size: .78rem; color: var(--tenue); }

    /* ── Estados ───────────────────────────────────────────────────────── */
    .vacio, .invalido {
      padding: clamp(30px, 8vw, 68px); border-radius: 28px; text-align: center;
      background: var(--papel); border: 1px solid color-mix(in srgb, var(--ink) 10%, transparent);
    }
    .vacio h2, .invalido h1 { margin: .2em 0; font-size: clamp(1.7rem, 5.5vw, 2.7rem); }
    .vacio p, .invalido p { max-width: 52ch; margin: 0 auto; color: var(--tenue); line-height: 1.5; }
    .invalido { margin-top: 12vh; }
    .invalido .marca { justify-content: center; margin-bottom: 26px; }

    .pie { margin-top: 34px; text-align: center; color: var(--tenue); font-size: .84rem; }
    .pie .marca { font-size: .95rem; }

    @media (max-width: 720px) {
      main { width: calc(100% - 20px); padding-top: 16px; }
      .hero { border-radius: 22px; }
      .muro { grid-template-columns: 1fr; }
      .boleta:nth-child(odd), .boleta:nth-child(even) { transform: none; }
      /* En celular los tres datos apilados dejaban boletas de 400 px: con
         siete apuestas la pagina ya media 3.300 px y con treinta seria
         inmanejable. Rotulo a la izquierda y valor a la derecha en la misma
         linea corta la altura a la mitad y se lee igual de bien. */
      .apuesta { grid-template-columns: 1fr; gap: 0; }
      .dato {
        display: flex; align-items: baseline; justify-content: space-between; gap: 10px;
        padding: 9px 12px; border-radius: 0; border: 0;
        border-bottom: 1px solid color-mix(in srgb, var(--ink) 9%, transparent);
        background: none;
      }
      .apuesta .dato:first-child { border-top: 1px solid color-mix(in srgb, var(--ink) 9%, transparent); }
      .dato strong { margin-top: 0; text-align: right; }
      .barra { grid-template-columns: 7.5ch 1fr auto; gap: 9px; }
    }

    /* En papel: sin fondos pesados, sin inclinaciones y sin el botón. */
    @media print {
      body { background: #fff; }
      body::before, .imprimir, .otra-pantalla { display: none; }
      main { width: 100%; padding: 0; }
      .hero { box-shadow: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .boleta { box-shadow: none; transform: none !important; }
      .boleta__cinta { display: none; }
      .muro { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>
<main>
<?php if ($invalid): ?>
  <section class="invalido" role="alert">
    <div class="marca"><img src="brand/cumpleclick-mark.svg" alt="" width="30" height="30"><span>CumpleClick</span></div>
    <p class="eyebrow" style="color:var(--pink)">Acceso privado</p>
    <h1>Este enlace ya no está disponible</h1>
    <p>Puede estar vencido o haber sido reemplazado. Pide a quien organiza el evento que te comparta el enlace vigente.</p>
  </section>
<?php else: ?>
  <div class="marca"><img src="brand/cumpleclick-mark.svg" alt="" width="30" height="30"><span>CumpleClick</span></div>

  <header class="hero">
    <p class="eyebrow">Tablero de los papás</p>
    <h1><?= $escape($tituloPagina) ?></h1>
    <p>Cada boleta guarda una apuesta hecha en la cabina. Imprímanlas o revísenlas juntos cuando llegue el gran día.</p>
  </header>

  <?php // El mismo token abre la lista de regalos. Sin este enlace los papás
        // no tenían cómo enterarse de que esa pantalla existía. ?>
  <nav class="otra-pantalla" aria-label="Pantallas de los papás">
    <span aria-current="page">Las predicciones</span>
    <a href="<?= $escape(cb_gift_board_url($token)) ?>">Ver la lista de regalos</a>
  </nav>

  <div class="toolbar">
    <span class="cuenta"><b><?= count($predictions) ?></b> <?= count($predictions) === 1 ? 'apuesta' : 'apuestas' ?></span>
    <button class="imprimir" type="button" onclick="window.print()">Imprimir tablero</button>
  </div>

  <?php if (!$predictions): ?>
    <section class="vacio">
      <p class="eyebrow" style="color:var(--pink)">Todavía está por comenzar</p>
      <h2>La primera apuesta aparecerá aquí</h2>
      <p>Cuando un invitado complete el recorrido de la cabina, su predicción se sumará automáticamente a este tablero.</p>
    </section>
  <?php else: ?>
    <section class="marcador" aria-label="A quién creen que se parecerá">
      <h2>¿A quién se va a parecer?</h2>
      <?php foreach (['mama' => 'A mamá', 'papa' => 'A papá', 'ambos' => 'A ambos'] as $clave => $rotulo): ?>
        <div class="barra">
          <span><?= $escape($rotulo) ?></span>
          <div class="pista"><div class="relleno" style="width:<?= (int) round($counts[$clave] / $topCount * 100) ?>%"></div></div>
          <b><?= (int) $counts[$clave] ?></b>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="muro">
      <?php foreach ($predictions as $prediction): $labels = cb_prediction_labels($prediction); ?>
        <article class="boleta">
          <span class="boleta__cinta" aria-hidden="true"></span>
          <div class="boleta__alto">
            <h3><?= $escape($prediction['guest_name']) ?></h3>
            <?php if ($prediction['puntaje_juego'] !== null): ?><span class="puntaje"><?= (int) $prediction['puntaje_juego'] ?> pts</span><?php endif; ?>
          </div>
          <div class="apuesta">
            <div class="dato"><small>Se parecerá</small><strong><?= $escape($labels['parecido']) ?></strong></div>
            <div class="dato"><small>Pesará</small><strong><?= $escape($labels['peso']) ?></strong></div>
            <div class="dato"><small>Llegará</small><strong><?= $escape($labels['fecha']) ?></strong></div>
          </div>
          <time class="cuando" datetime="<?= $escape($isoUtc($prediction['created_at'])) ?>"><?= $escape($horaLocal($prediction['created_at'])) ?></time>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <footer class="pie">
    <div class="marca"><img src="brand/cumpleclick-mark.svg" alt="" width="26" height="26"><span>CumpleClick</span></div>
  </footer>
<?php endif; ?>
</main>
</body>
</html>
