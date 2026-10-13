# Plan de trabajo — Etapa 2 (el cliente recibe) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Desde la ficha del cliente, Luis envía el plan de trabajo «listo» al cliente por correo (PDF adjunto y enlace) o por su WhatsApp; el cliente lo ve en `automatizatech.cl/ver-plan.php?id=<codigo>` y agenda ahí mismo la llamada de seguimiento, que crea una reunión de seguimiento con su evento en Google Calendar.

**Architecture:** Cuatro piezas nuevas dentro del módulo `inc/plan-trabajo/` (cargado por `cargar.php`; `functions.php` no se toca): funciones puras en `puras.php` (enlaces, textos, correo HTML, validación de la hora, token de la agenda), `envio.php` (correo y WhatsApp, cambia el plan a «enviado»), `agenda.php` (ruta REST pública `POST automatiza-tech/v1/plan-seguimiento` que crea la reunión por el carril de seguimiento) y `vista.php` (HTML de la página pública). `ver-plan.php` en la raíz solo carga WordPress y llama a `vista.php`. El renderer no cambia: ya dibuja «En el sitio web» cuando `agenda.web_url` viene lleno.

**Tech Stack:** WordPress (PHP 8.3, `$wpdb`, REST API, `wp_mail`), JavaScript sin dependencias, `at-agenda.js` existente.

**Spec:** `Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md` (sección 7 «Envío y vista del cliente (Etapa 2)», decisiones 2, 5, 8 y 9, «Errores y seguridad», «Pruebas»).

**Rutas que usa este plan:**
- `<W>` = `C:/wamp64/www/automatiza-tech/.worktrees/plan-trabajo` (worktree, rama `claude/plan-de-trabajo`).
- `<SCR>` = `C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad`.
- `<PHP>` = `/c/wamp64/bin/php/php8.3.28/php.exe` (en el PATH del shell no hay `php`).
- Prueba sin WordPress: `cd <W> && <PHP> tests/plan/<x>-test.php`.
- Prueba con WordPress local: `cd <W> && AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> tests/plan/<x>-wp-test.php`.
- Todas terminan con `TODO OK` (código 0) o `N FALLAS` (código 1).

## Global Constraints

- **Decisión 5:** nada le llega al cliente sin el clic de Luis. El correo y el WhatsApp salen solo desde los botones del panel, con confirmación.
- **Decisión 2:** lo que se agenda desde el plan es siempre una **reunión de seguimiento** (`wp_automatiza_followup_meetings`). Nunca se toca `wp_automatiza_leads` ni el flujo `saveLead`. El asunto es «Seguimiento del plan de trabajo — {proyecto}» (`at_pt_datos_agenda()`).
- **Hostinger rechaza con 554 los correos que enlazan a `*.easypanel.host`.** El correo al cliente nunca lleva un enlace al renderer: enlaza a `ver-plan.php` en el sitio y adjunta el PDF.
- PDF adjunto de hasta **15 MB**. Si no se puede bajar o pesa más, el correo sale igual, con el enlace y los botones, y el panel dice por qué faltó el PDF.
- Solo se baja un PDF desde `https://<host del renderer>/p/<código>/presentation.pdf` (host `AT_PA_RENDERER_HOST`, o `n8n-propuesta-renderer.kchiba.easypanel.host` si no está definido).
- Se envía solo un plan en estado `listo` o ya `enviado` (reenvío), y que tenga `view_url`. `listo → enviado` es la única transición nueva; `enviado` no se edita (como en la Etapa 1).
- La ruta pública de agenda exige un **token del plan** (HMAC del código con `wp_salt('nonce')`, válido hoy y ayer), que el plan esté en `enviado`, un campo trampa vacío, un **límite de 5 intentos por IP y por hora** y **una sola llamada futura agendada por plan**.
- Horarios: la misma regla que la agenda de la portada (`automatiza_tech_check_availability()`): horas en punto entre el inicio y el fin del día, días habilitados, sin feriados ni horas ocupadas, desde la hora siguiente a la actual de hoy y hasta 90 días hacia adelante. Hora de Chile con `current_time()`.
- El repositorio es **público**: sin nombres, correos ni teléfonos reales en código, pruebas ni commits. Datos de prueba marcados `[PRUEBA]`, con correos `@example.com`.
- Las pruebas nunca envían correos de verdad ni llaman a n8n (`pre_wp_mail` y `pre_http_request`).
- Estilo del módulo: tabuladores, comentarios y textos en español de Chile, funciones `at_pt_*`, `declare` de tipos como en `puras.php`, escapar todo lo que se dibuja (`esc_html`, `esc_attr`, `esc_url`).
- `functions.php` nunca se toca. `inc/admin-followup-meetings.php` y el renderer no se modifican en esta etapa.

## Review Focus

1. **El cliente toca «Agendar» dos veces o recarga y vuelve a agendar:** debe quedar una sola reunión y la segunda vez el sitio le dice cuándo es la que ya tiene. → Task 4, caso «ya_agendada».
2. **La hora elegida dejó de servir entre que abrió el diálogo y que tocó «Agendar»** (otra reunión la tomó, ya pasó o el día es feriado): no se crea nada y el mensaje dice qué pasó. → Task 4, casos «hora_ocupada», «pasada», «dia_no_disponible».
3. **El cliente no tiene correo en su ficha ni en el contrato:** «Enviar al cliente» no sale y el panel lo dice; la agenda web contesta que escriba por WhatsApp. → Task 2 y Task 4, caso «sin_correo».
4. **El renderer está caído o el PDF pesa más de 15 MB:** el correo sale igual con el enlace y los botones y el panel avisa que faltó el PDF. → Task 2, casos «pdf_descarga» y «pdf_grande».
5. **Un enlace `ver-plan.php` con un código inventado, de otro tamaño o de un plan que todavía no se aprueba:** la página dice «no está disponible» sin mostrar nada del plan, y la ruta de agenda contesta 404. → Task 4 y Task 5.

---

### Task 1: Funciones puras de la Etapa 2

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php` (agregar al final; cambiar `at_pt_armar_render()` en la clave `web_url` y su comentario)
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php` (`at_pt_datos_render()`: agregar `'sitio' => home_url()`)
- Test: `tests/plan/envio-test.php` (nuevo, sin WordPress)

**Interfaces:**
- Consumes: `at_pt_ymd()`, `at_pt_fecha_larga()`, `at_pt_texto_whatsapp_agenda()` (puras.php, Etapa 1).
- Produces:
  - `at_pt_url_ver_plan(string $base, string $codigo, bool $agendar = false): string`
  - `at_pt_url_whatsapp_agenda(string $telefono_at, string $codigo): string`
  - `at_pt_telefono_wa(string $telefono): string`
  - `at_pt_texto_whatsapp_envio(string $nombre, string $proyecto, string $url_ver): string`
  - `at_pt_entrega_estimada(array $plan): string`
  - `at_pt_correo_plan_html(array $v): string`
  - `at_pt_motivo_hora_agenda(string $fecha, string $hora, string $ahora, array $disp): string`
  - `at_pt_mensajes_agenda(): array`
  - `at_pt_token_agenda(string $codigo, int $dia, string $sal): string`
  - `at_pt_token_agenda_valido(string $token, string $codigo, int $hoy, string $sal): bool`
  - `at_pt_armar_render()` llena `agenda.web_url` con `at_pt_url_ver_plan($datos['sitio'], $codigo, true)` cuando `$datos['sitio']` viene; sin `sitio` queda `''` (las pruebas de la Etapa 1 no cambian).

- [ ] **Step 1: Write the failing test**

Crear `tests/plan/envio-test.php`:

```php
<?php
// Correr: php tests/plan/envio-test.php   (sin WordPress)
// Etapa 2: enlaces, textos, correo, validación de la hora y token de la agenda web.
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

// Enlaces
ok(at_pt_url_ver_plan('https://automatizatech.cl/', 'Ab3dE5fG7hJ9') === 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9', 'enlace a ver-plan.php sin barra doble');
ok(at_pt_url_ver_plan('https://automatizatech.cl', 'Ab3dE5fG7hJ9', true) === 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9&agendar=1', 'con agendar=1 abre el selector de horarios');
ok(at_pt_url_ver_plan('', 'Ab3dE5fG7hJ9') === '' && at_pt_url_ver_plan('https://x.cl', 'malo/../x') === '' && at_pt_url_ver_plan('https://x.cl', '') === '', 'sin sitio o con código raro: sin enlace');
ok(at_pt_url_whatsapp_agenda('56927002984', 'Ab3dE5fG7hJ9') === 'https://wa.me/56927002984?text=Hola%20Tech%2C%20quiero%20agendar%20la%20llamada%20de%20seguimiento%20de%20mi%20plan%20de%20trabajo%20%28c%C3%B3digo%20Ab3dE5fG7hJ9%29', 'WhatsApp con Tech con el código');
ok(at_pt_url_whatsapp_agenda('', 'Ab3dE5fG7hJ9') === '', 'sin número de AT: sin enlace');

// Teléfonos para wa.me
ok(at_pt_telefono_wa('+56 9 1111 1111') === '56911111111' && at_pt_telefono_wa('9 1111 1111') === '56911111111', 'celular chileno con o sin +56');
ok(at_pt_telefono_wa('+51 987 654 321') === '51987654321', 'número extranjero con código de país');
ok(at_pt_telefono_wa('') === '' && at_pt_telefono_wa('1234') === '' && at_pt_telefono_wa('sin número') === '', 'vacío o muy corto: sin teléfono');

// Texto del WhatsApp de Luis al cliente
$t = at_pt_texto_whatsapp_envio('Cliente Prueba', '[PRUEBA] Sitio', 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9');
ok(strpos($t, 'Hola Cliente Prueba, te escribe Luis de AutomatizaTech.') === 0 && strpos($t, 'plan de trabajo de [PRUEBA] Sitio') !== false && strpos($t, 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9') !== false && strpos($t, 'llamada de seguimiento') !== false, 'WhatsApp: saludo, proyecto, enlace e invitación a agendar');
ok(strpos(at_pt_texto_whatsapp_envio('', '', 'https://x.cl/ver-plan.php?id=Ab3dE5fG7hJ9'), 'Hola, te escribe Luis') === 0, 'sin nombre: «Hola,»');

// Entrega estimada
$plan = ['cronograma' => ['fin' => '2027-04-06', 'hitos' => [['nombre' => 'Prototipo', 'fecha' => '2026-11-05'], ['nombre' => 'Entrega estimada', 'fecha' => '2027-02-15']]]];
ok(at_pt_entrega_estimada($plan) === '2027-02-15', 'entrega estimada desde el hito');
ok(at_pt_entrega_estimada(['cronograma' => ['fin' => '2027-04-06', 'hitos' => []]]) === '2027-04-06' && at_pt_entrega_estimada([]) === '', 'sin hito: fin del cronograma; sin cronograma: vacío');

// Correo
$v = [
	'nombre' => 'Cliente <Prueba>', 'proyecto' => '[PRUEBA] Sitio & tienda', 'logo' => 'https://automatizatech.cl/logo.png',
	'url_ver' => 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9', 'url_agendar' => 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9&agendar=1',
	'url_whatsapp' => 'https://wa.me/56927002984?text=Hola', 'inicio' => '2026-10-05', 'entrega' => '2027-02-15', 'con_pdf' => true,
];
$h = at_pt_correo_plan_html($v);
ok(strpos($h, 'Cliente &lt;Prueba&gt;') !== false && strpos($h, '[PRUEBA] Sitio &amp; tienda') !== false, 'correo: nombre y proyecto escapados');
ok(strpos($h, 'href="https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9"') !== false && strpos($h, 'agendar=1') !== false && strpos($h, 'https://wa.me/56927002984') !== false, 'correo: ver el plan, agendar en la web y por WhatsApp');
ok(strpos($h, '5 de octubre de 2026') !== false && strpos($h, '15 de febrero de 2027') !== false && strpos($h, 'estimad') !== false, 'correo: fechas largas y que son estimadas');
ok(strpos($h, 'adjunto') !== false && strpos(at_pt_correo_plan_html(['con_pdf' => false] + $v), 'adjunto') === false, 'correo: menciona el PDF adjunto solo si va adjunto');
ok(stripos($h, 'easypanel') === false, 'correo: ningún enlace al renderer (Hostinger lo rechaza)');
ok(stripos(at_pt_correo_plan_html(['url_ver' => 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/x/index.html'] + $v), 'easypanel') === false, 'correo: un enlace del renderer que se cuele no se dibuja');

// Hora de la agenda: martes 6-oct-2026, 09:00-18:00, ocupada a las 11:00. «Ahora» = lunes 5-oct 10:20.
$disp = ['isFullDay' => false, 'busySlots' => ['11:00'], 'workingHours' => ['start' => '09:00', 'end' => '18:00']];
$ahora = '2026-10-05 10:20';
ok(at_pt_motivo_hora_agenda('2026-10-06', '10:00', $ahora, $disp) === '', 'hora libre dentro del horario: sirve');
ok(at_pt_motivo_hora_agenda('2026-10-06', '10:00:00', $ahora, $disp) === '', 'acepta HH:00:00');
ok(at_pt_motivo_hora_agenda('2026-10-06', '11:00', $ahora, $disp) === 'hora_ocupada', 'Review Focus 2: hora ocupada');
ok(at_pt_motivo_hora_agenda('2026-10-06', '08:00', $ahora, $disp) === 'fuera_de_horario' && at_pt_motivo_hora_agenda('2026-10-06', '18:00', $ahora, $disp) === 'fuera_de_horario', 'antes del inicio o desde el fin: fuera de horario');
ok(at_pt_motivo_hora_agenda('2026-10-05', '10:00', $ahora, $disp) === 'pasada' && at_pt_motivo_hora_agenda('2026-10-04', '15:00', $ahora, $disp) === 'pasada', 'Review Focus 2: hoy a una hora ya empezada o un día pasado');
ok(at_pt_motivo_hora_agenda('2026-10-05', '11:00', $ahora, ['busySlots' => []] + $disp) === '', 'hoy, la hora siguiente sí sirve');
ok(at_pt_motivo_hora_agenda('2027-01-04', '10:00', $ahora, $disp) === 'muy_lejos' && at_pt_motivo_hora_agenda('2027-01-03', '10:00', $ahora, $disp) !== 'muy_lejos', 'hasta 90 días hacia adelante');
ok(at_pt_motivo_hora_agenda('2026-10-06', '10:00', $ahora, ['isFullDay' => true]) === 'dia_no_disponible' && at_pt_motivo_hora_agenda('2026-10-06', '10:00', $ahora, []) === 'dia_no_disponible', 'Review Focus 2: día completo, feriado o sin disponibilidad');
ok(at_pt_motivo_hora_agenda('2026-02-30', '10:00', $ahora, $disp) === 'fecha_invalida' && at_pt_motivo_hora_agenda('mañana', '10:00', $ahora, $disp) === 'fecha_invalida', 'fecha que no existe');
ok(at_pt_motivo_hora_agenda('2026-10-06', '10:30', $ahora, $disp) === 'hora_invalida' && at_pt_motivo_hora_agenda('2026-10-06', '25:00', $ahora, $disp) === 'hora_invalida' && at_pt_motivo_hora_agenda('2026-10-06', '', $ahora, $disp) === 'hora_invalida', 'solo horas en punto válidas');
$mensajes = at_pt_mensajes_agenda();
foreach (['fecha_invalida', 'hora_invalida', 'pasada', 'muy_lejos', 'dia_no_disponible', 'fuera_de_horario', 'hora_ocupada', 'plan_no_disponible', 'sesion_vencida', 'muchos_intentos', 'sin_correo', 'ya_agendada', 'no_guardo'] as $k) {
	ok(isset($mensajes[$k]) && is_string($mensajes[$k]) && $mensajes[$k] !== '', "mensaje para «{$k}»");
}

// Token de la agenda
$sal = 'sal-de-prueba';
$tok = at_pt_token_agenda('Ab3dE5fG7hJ9', 20000, $sal);
ok(preg_match('/^[a-f0-9]{24}$/', $tok) === 1, 'token de 24 caracteres hexadecimales');
ok(at_pt_token_agenda_valido($tok, 'Ab3dE5fG7hJ9', 20000, $sal) && at_pt_token_agenda_valido($tok, 'Ab3dE5fG7hJ9', 20001, $sal), 'vale el día que se creó y el siguiente');
ok(!at_pt_token_agenda_valido($tok, 'Ab3dE5fG7hJ9', 20002, $sal), 'a los dos días vence');
ok(!at_pt_token_agenda_valido($tok, 'Zz3dE5fG7hJ9', 20000, $sal) && !at_pt_token_agenda_valido($tok, 'Ab3dE5fG7hJ9', 20000, 'otra-sal') && !at_pt_token_agenda_valido('', 'Ab3dE5fG7hJ9', 20000, $sal), 'otro plan, otra sal o vacío: no vale');

// El render llena la agenda web cuando viene el sitio (sin sitio queda vacío, como en la Etapa 1)
$p = at_pt_validar_plan(['proyecto' => '[PRUEBA] P', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'B', 'actividades' => [['nombre' => 'A', 'responsable' => 'at', 'dias_habiles' => 2]]]]]]]);
$r = at_pt_armar_render($p['plan'], ['codigo' => 'Ab3dE5fG7hJ9', 'company_name' => 'E', 'whatsapp' => '56927002984', 'sitio' => 'https://automatizatech.cl'], true);
ok($r['agenda']['web_url'] === 'https://automatizatech.cl/ver-plan.php?id=Ab3dE5fG7hJ9&agendar=1', 'render: agenda.web_url apunta a ver-plan.php con agendar=1');
ok(at_pt_armar_render($p['plan'], ['codigo' => 'Ab3dE5fG7hJ9', 'company_name' => 'E'], true)['agenda']['web_url'] === '', 'render sin sitio: web_url vacío');

fin();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd <W> && <PHP> tests/plan/envio-test.php`
Expected: error fatal `Call to undefined function at_pt_url_ver_plan()`.

- [ ] **Step 3: Write minimal implementation**

En `puras.php`, dentro de `at_pt_armar_render()`, reemplazar la línea `'web_url'      => '',` por:

```php
			'web_url'      => at_pt_url_ver_plan((string) ($datos['sitio'] ?? ''), $codigo, true),
```

y en su comentario cambiar «agenda.web_url queda '' en la Etapa 1.» por «agenda.web_url apunta a ver-plan.php?id=…&agendar=1 cuando $datos['sitio'] viene (Etapa 2); sin sitio queda ''.». Agregar `'sitio'` a la lista de claves de `$datos` del mismo comentario.

Agregar al final de `puras.php`:

```php
/* ---------- Etapa 2: envío al cliente, vista pública y agenda web ---------- */

/** Enlace público del plan en el sitio de AT; con $agendar abre el selector de horarios. '' sin sitio o con un código que
 *  no es alfanumérico de 6 a 32 caracteres. */
function at_pt_url_ver_plan(string $base, string $codigo, bool $agendar = false): string {
	$base = rtrim(trim($base), '/');
	if ($base === '' || !preg_match('/^[A-Za-z0-9]{6,32}$/', $codigo)) {
		return '';
	}
	return $base . '/ver-plan.php?id=' . $codigo . ($agendar ? '&agendar=1' : '');
}

/** wa.me de Tech con el mensaje para agendar y el código del plan; '' sin número de AT. */
function at_pt_url_whatsapp_agenda(string $telefono_at, string $codigo): string {
	$n = at_pt_telefono_wa($telefono_at);
	return $n === '' ? '' : 'https://wa.me/' . $n . '?text=' . rawurlencode(at_pt_texto_whatsapp_agenda($codigo));
}

/** Teléfono para wa.me: solo dígitos; un celular chileno de 9 dígitos que parte en 9 lleva 56 delante. '' con menos de
 *  9 dígitos o más de 15. */
function at_pt_telefono_wa(string $telefono): string {
	$n = (string) preg_replace('/\D/', '', $telefono);
	if (strlen($n) === 9 && $n[0] === '9') {
		$n = '56' . $n;
	}
	return (strlen($n) < 10 || strlen($n) > 15) ? '' : $n;
}

/** Mensaje que Luis manda desde su WhatsApp con el enlace del plan. */
function at_pt_texto_whatsapp_envio(string $nombre, string $proyecto, string $url_ver): string {
	$saludo = trim($nombre) !== '' ? 'Hola ' . trim($nombre) : 'Hola';
	$de = trim($proyecto) !== '' ? ' de ' . trim($proyecto) : '';
	return $saludo . ', te escribe Luis de AutomatizaTech. Te comparto el plan de trabajo' . $de
		. ', con las fases, las fechas estimadas y lo que necesitamos de ti: ' . $url_ver
		. "\nAhí mismo puedes agendar la llamada de seguimiento para revisarlo juntos.";
}

/** «Entrega estimada» del cronograma (D4); sin ese hito, el fin del cronograma; '' sin cronograma. */
function at_pt_entrega_estimada(array $plan): string {
	$crono = is_array($plan['cronograma'] ?? null) ? $plan['cronograma'] : [];
	foreach ((array) ($crono['hitos'] ?? []) as $h) {
		if (is_array($h) && ($h['nombre'] ?? '') === 'Entrega estimada') {
			return at_pt_ymd((string) ($h['fecha'] ?? ''));
		}
	}
	return at_pt_ymd((string) ($crono['fin'] ?? ''));
}

/**
 * Correo «Tu plan de trabajo». $v: nombre, proyecto, logo, url_ver, url_agendar, url_whatsapp, inicio y entrega (Y-m-d),
 * con_pdf (bool). Hostinger rechaza (554) los correos que enlazan a *.easypanel.host: un enlace del renderer no se dibuja.
 */
function at_pt_correo_plan_html(array $v): string {
	$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
	$url = function ($u): string {
		$u = trim((string) $u);
		return (preg_match('#^https?://\S+$#i', $u) && stripos($u, 'easypanel') === false) ? $u : '';
	};
	$nombre = trim((string) ($v['nombre'] ?? ''));
	$proyecto = trim((string) ($v['proyecto'] ?? ''));
	$logo = $url($v['logo'] ?? '');
	$ver = $url($v['url_ver'] ?? '');
	$agendar = $url($v['url_agendar'] ?? '');
	$wa = $url($v['url_whatsapp'] ?? '');
	$inicio = at_pt_fecha_larga((string) ($v['inicio'] ?? ''));
	$entrega = at_pt_fecha_larga((string) ($v['entrega'] ?? ''));
	$boton = function (string $href, string $texto, string $fondo) use ($h): string {
		return '<a href="' . $h($href) . '" style="display:inline-block;background:' . $fondo . ';color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 26px;border-radius:24px;margin:6px">' . $h($texto) . '</a>';
	};
	$fechas = ($inicio !== '' && $entrega !== '')
		? '<p>Partimos el <strong>' . $h($inicio) . '</strong> y la entrega estimada es el <strong>' . $h($entrega) . '</strong>. Son fechas estimadas: corren desde que recibimos el anticipo y tus insumos.</p>'
		: '';
	return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f4f6fb;margin:0;padding:0;color:#222">'
		. '<div style="max-width:600px;margin:24px auto;background:#ffffff;border-radius:10px;overflow:hidden">'
		. '<div style="background:#1e40af;color:#ffffff;text-align:center;padding:26px 20px">'
		. ($logo !== '' ? '<img src="' . $h($logo) . '" alt="AutomatizaTech" style="max-height:64px"><br>' : '')
		. '<h1 style="margin:12px 0 0;font-size:22px">Tu plan de trabajo</h1></div>'
		. '<div style="padding:26px;line-height:1.6">'
		. '<p>Hola <strong>' . $h($nombre !== '' ? $nombre : 'cliente') . '</strong>,</p>'
		. '<p>Te enviamos el plan de trabajo' . ($proyecto !== '' ? ' de <strong>' . $h($proyecto) . '</strong>' : '')
		. ': qué hacemos, en qué orden, cuánto demora cada parte, qué necesitamos de ti y cuándo revisas y apruebas.</p>'
		. $fechas
		. (!empty($v['con_pdf']) ? '<p>Va adjunto en PDF; también puedes verlo en línea.</p>' : '')
		. ($ver !== '' ? '<p style="text-align:center;margin:22px 0">' . $boton($ver, 'Ver mi plan de trabajo', '#1e40af') . '</p>' : '')
		. '<p>Queremos revisarlo contigo y aclarar tus dudas en una llamada de seguimiento. Agéndala cuando te acomode:</p>'
		. '<p style="text-align:center;margin:18px 0">'
		. ($agendar !== '' ? $boton($agendar, 'Agendar mi llamada de seguimiento', '#059669') : '')
		. ($wa !== '' ? $boton($wa, 'Agendar por WhatsApp con Tech', '#17b7b1') : '')
		. '</p>'
		. '<p>Cualquier duda, responde este correo.</p><p>Un abrazo,<br><strong>El equipo de AutomatizaTech</strong></p></div>'
		. '<div style="background:#f1f1f1;color:#777;text-align:center;font-size:12px;padding:14px">© ' . date('Y') . ' AutomatizaTech · automatizatech.cl</div>'
		. '</div></body></html>';
}

/**
 * '' si la hora sirve para la llamada; si no, la clave del motivo (at_pt_mensajes_agenda()). $fecha 'Y-m-d', $hora
 * 'HH:00' o 'HH:00:00', $ahora 'Y-m-d H:i' en hora de Chile (current_time). $disp es lo que devuelve
 * automatiza_tech_check_availability(): ['isFullDay' => bool, 'busySlots' => ['HH:MM', …], 'workingHours' =>
 * ['start' => 'HH:MM', 'end' => 'HH:MM']]; vacío o sin horario = día no disponible. Misma regla que la portada:
 * horas en punto desde el inicio hasta antes del fin, desde la hora siguiente a la actual y hasta 90 días.
 */
function at_pt_motivo_hora_agenda(string $fecha, string $hora, string $ahora, array $disp): string {
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || at_pt_ymd($fecha) === '') {
		return 'fecha_invalida';
	}
	if (!preg_match('/^([01]\d|2[0-3]):00(:00)?$/', $hora, $m)) {
		return 'hora_invalida';
	}
	$h = (int) $m[1];
	$hoy = substr($ahora, 0, 10);
	if ($fecha < $hoy || ($fecha === $hoy && $h <= (int) substr($ahora, 11, 2))) {
		return 'pasada';
	}
	$tope = (new DateTimeImmutable($hoy . ' 00:00:00', new DateTimeZone('UTC')))->modify('+90 days')->format('Y-m-d');
	if ($fecha > $tope) {
		return 'muy_lejos';
	}
	if (!empty($disp['isFullDay']) || !is_array($disp['workingHours'] ?? null)) {
		return 'dia_no_disponible';
	}
	$inicio = (int) explode(':', (string) ($disp['workingHours']['start'] ?? '00:00'))[0];
	$fin = (int) explode(':', (string) ($disp['workingHours']['end'] ?? '00:00'))[0];
	if ($h < $inicio || $h >= $fin) {
		return 'fuera_de_horario';
	}
	$texto = sprintf('%02d:00', $h);
	foreach ((array) ($disp['busySlots'] ?? []) as $ocupada) {
		if (substr((string) $ocupada, 0, 5) === $texto) {
			return 'hora_ocupada';
		}
	}
	return '';
}

/** Lo que ve el cliente en la agenda web por cada motivo. */
function at_pt_mensajes_agenda(): array {
	return [
		'fecha_invalida'     => 'Elige una fecha válida.',
		'hora_invalida'      => 'Elige una de las horas disponibles.',
		'pasada'             => 'Esa hora ya pasó. Elige otra.',
		'muy_lejos'          => 'Solo agendamos hasta 90 días hacia adelante.',
		'dia_no_disponible'  => 'Ese día no atendemos. Elige otra fecha.',
		'fuera_de_horario'   => 'Esa hora está fuera de nuestro horario. Elige otra.',
		'hora_ocupada'       => 'Esa hora se acaba de ocupar. Elige otra.',
		'plan_no_disponible' => 'Este plan de trabajo no está disponible.',
		'sesion_vencida'     => 'La página quedó abierta mucho rato. Recárgala e inténtalo de nuevo.',
		'muchos_intentos'    => 'Hiciste muchos intentos seguidos. Espera un rato o escríbenos por WhatsApp.',
		'sin_correo'         => 'No tenemos tu correo para enviarte la invitación. Escríbenos por WhatsApp y la agendamos.',
		'ya_agendada'        => 'Ya tienes una llamada de seguimiento agendada.',
		'no_guardo'          => 'No pudimos agendar la llamada. Inténtalo de nuevo o escríbenos por WhatsApp.',
	];
}

/** Token de la agenda web de un plan para el día $dia (días desde 1970, intdiv(time(), 86400)): reemplaza al nonce de
 *  WordPress, que depende de la sesión (el cliente no tiene; Luis sí, y su nonce no valdría en la ruta pública). */
function at_pt_token_agenda(string $codigo, int $dia, string $sal): string {
	return substr(hash_hmac('sha256', 'at-pt-agenda|' . $codigo . '|' . $dia, $sal), 0, 24);
}

/** El token vale el día en que se creó y el siguiente. */
function at_pt_token_agenda_valido(string $token, string $codigo, int $hoy, string $sal): bool {
	if (!preg_match('/^[a-f0-9]{24}$/', $token)) {
		return false;
	}
	return hash_equals(at_pt_token_agenda($codigo, $hoy, $sal), $token) || hash_equals(at_pt_token_agenda($codigo, $hoy - 1, $sal), $token);
}
```

En `datos.php`, dentro de `at_pt_datos_render()`, agregar la clave `'sitio' => home_url(),` después de `'garantia_meses'`, y sumar «sitio (home_url, para el enlace de la agenda web)» a su comentario.

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd <W> && <PHP> tests/plan/envio-test.php && <PHP> tests/plan/render-test.php && <PHP> tests/plan/validacion-test.php && <PHP> tests/plan/cronograma-test.php && <PHP> tests/plan/fechas-test.php`
Expected: las cinco terminan en `TODO OK`.

Run: `cd <W> && AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> tests/plan/rest-render-wp-test.php`
Expected: `TODO OK`. Si alguna aserción comparaba `web_url === ''` en un render pedido por REST, el valor esperado pasa a ser `home_url('/ver-plan.php?id=' . $codigo . '&agendar=1')`: cambiar solo esa aserción.

- [ ] **Step 5: Commit**

```bash
cd <W>
git add tests/plan/envio-test.php wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php tests/plan/rest-render-wp-test.php
git commit -m "feat(plan): funciones de la Etapa 2 (enlaces, correo, hora y token de la agenda) y agenda web en el render"
```

---

### Task 2: Envío por correo y por el WhatsApp de Luis

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/envio.php`
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` (agregar `require_once __DIR__ . '/envio.php';` después de `rest.php`, y cambiar «Etapa 1: solo Luis lo ve.» por «Etapas 1 y 2.»)
- Test: `tests/plan/envio-wp-test.php`

**Interfaces:**
- Consumes: Task 1 (`at_pt_url_ver_plan`, `at_pt_url_whatsapp_agenda`, `at_pt_telefono_wa`, `at_pt_texto_whatsapp_envio`, `at_pt_entrega_estimada`, `at_pt_correo_plan_html`); Etapa 1 (`at_pt_plan`, `at_pt_payload`, `at_pt_guardar`, `at_pt_cambiar_estado`, `at_pt_datos_agenda`, `at_pt_crm_de_plan`); cierre (`at_cc_correo_avisos`, `at_cc_cabecera_copia`, `at_cc_whatsapp_at`, `at_cc_historial_crm`, `AT_CC_LOGO`; todas con `function_exists`/`defined`).
- Produces:
  - `const AT_PT_PDF_MAX_BYTES = 15728640;`
  - `at_pt_host_renderer(): string`
  - `at_pt_descargar_pdf(string $url, string $codigo): string|WP_Error` (ruta de un archivo temporal propio)
  - `at_pt_enviar_plan(int $id): array` → `['ok' => bool, 'motivo' => string, 'sin_pdf' => string]`; `motivo` ∈ `sin_plan`, `no_listo`, `sin_correo`, `correo_fallo`, `transicion`; `sin_pdf` = por qué no fue adjunto ('' si fue).
  - `at_pt_url_whatsapp_cliente(object $fila): string`
  - `at_pt_marcar_enviado_whatsapp(int $id): array` → `['ok' => bool, 'motivo' => string, 'url' => string]`; `motivo` ∈ `sin_plan`, `no_listo`, `sin_telefono`, `transicion`.

- [ ] **Step 1: Write the failing test**

Crear `tests/plan/envio-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/envio-wp-test.php
// Etapa 2, Task 2: envío del plan por correo (PDF adjunto o solo enlace) y por el WhatsApp de Luis.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_enviar_plan', 'at_pt_descargar_pdf', 'at_pt_marcar_enviado_whatsapp', 'at_pt_url_whatsapp_cliente');
global $wpdb;
$m = ptc_marca();

// Correos: se anotan y no salen (fixtures-panel.php corta todo con __return_true en PHP_INT_MAX).
$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return $nulo; }, 10, 2);
// Renderer: 'ok' devuelve un PDF chico, 'caido' un error, 'grande' 16 MB, 'html' una página que no es PDF.
$renderer = 'ok';
$pedidas = [];
add_filter('pre_http_request', function ($pre, $args, $url) use (&$renderer, &$pedidas) {
	$pedidas[] = $url;
	if (strpos($url, '/presentation.pdf') === false) {
		return $pre;
	}
	if ($renderer === 'caido') {
		return new WP_Error('http_request_failed', 'caído');
	}
	$cuerpo = ['ok' => "%PDF-1.4\n% prueba\n", 'grande' => '%PDF' . str_repeat('x', AT_PT_PDF_MAX_BYTES + 10), 'html' => '<html>no</html>'][$renderer];
	return ['headers' => [], 'body' => $cuerpo, 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}, PHP_INT_MAX, 3); // después del corte de fixtures-panel.php (misma prioridad, registrado después): gana este

/** Plan listo con su versión final en el renderer. */
function plan_listo(string $marca, string $correo = 'prueba-plan-envio@example.com'): object {
	$c = ptc_cliente($marca . bin2hex(random_bytes(2)), $correo);
	$plan = ptc_plan(ptc_contrato($c['tech'], $marca, ptc_propuesta($marca), 'servicios', 'signed', $correo));
	ptc_sembrar((int) $plan->id);
	$codigo = (string) $plan->codigo;
	at_pt_guardar((int) $plan->id, ['estado' => 'listo', 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $codigo . '/index.html', 'pdf_url' => 'https://' . at_pt_host_renderer() . '/p/' . $codigo . '/presentation.pdf']);
	return at_pt_plan((int) $plan->id);
}

// Descarga del PDF
$ok_pdf = at_pt_descargar_pdf('https://' . at_pt_host_renderer() . '/p/Ab3dE5fG7hJ9/presentation.pdf', 'Ab3dE5fG7hJ9');
ok(is_string($ok_pdf) && is_file($ok_pdf) && strncmp((string) file_get_contents($ok_pdf), '%PDF', 4) === 0, 'baja el PDF del renderer a un archivo temporal');
if (is_string($ok_pdf)) { @unlink($ok_pdf); @rmdir(dirname($ok_pdf)); }
$antes = count($pedidas);
ok(is_wp_error(at_pt_descargar_pdf('https://otro-sitio.example.com/p/Ab3dE5fG7hJ9/presentation.pdf', 'Ab3dE5fG7hJ9')) && is_wp_error(at_pt_descargar_pdf('http://' . at_pt_host_renderer() . '/p/Ab3dE5fG7hJ9/presentation.pdf', 'Ab3dE5fG7hJ9')) && count($pedidas) === $antes, 'nunca pide un PDF fuera del renderer ni por http');
$renderer = 'grande';
$g = at_pt_descargar_pdf('https://' . at_pt_host_renderer() . '/p/Ab3dE5fG7hJ9/presentation.pdf', 'Ab3dE5fG7hJ9');
ok(is_wp_error($g) && $g->get_error_message() === 'el PDF pesa más de 15 MB', 'Review Focus 4: más de 15 MB no se adjunta');
$renderer = 'html';
ok(is_wp_error(at_pt_descargar_pdf('https://' . at_pt_host_renderer() . '/p/Ab3dE5fG7hJ9/presentation.pdf', 'Ab3dE5fG7hJ9')), 'una respuesta que no es PDF no se adjunta');
$renderer = 'ok';

// Envío por correo de un plan listo
$p = plan_listo($m);
$correos = [];
$r = at_pt_enviar_plan((int) $p->id);
$fila = at_pt_plan((int) $p->id);
ok($r === ['ok' => true, 'motivo' => '', 'sin_pdf' => ''], 'envía el plan listo');
ok($fila->estado === 'enviado' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $fila->enviado_at) === 1, 'queda «enviado» con la fecha de envío');
ok(count($correos) === 1 && $correos[0]['to'] === 'prueba-plan-envio@example.com', 'un correo al cliente');
$c0 = $correos[0] ?? ['subject' => '', 'message' => '', 'headers' => [], 'attachments' => []];
ok(strpos($c0['subject'], 'Tu plan de trabajo — ') === 0, 'asunto «Tu plan de trabajo — {proyecto}»');
ok(strpos($c0['message'], home_url('/ver-plan.php?id=' . $p->codigo)) !== false && strpos($c0['message'], 'agendar=1') !== false && stripos($c0['message'], 'easypanel') === false, 'el correo enlaza a ver-plan.php y nunca al renderer');
ok(count((array) $c0['attachments']) === 1 && substr((string) $c0['attachments'][0], -4) === '.pdf' && !file_exists((string) $c0['attachments'][0]), 'lleva el PDF adjunto y el temporal se borra después');
$cab = implode("\n", (array) $c0['headers']);
ok(strpos($cab, 'Content-Type: text/html; charset=UTF-8') !== false && strpos($cab, 'Reply-To: ') !== false, 'HTML y Reply-To al correo del cierre');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d AND titulo = %s", at_pt_crm_de_plan($fila), 'Plan de trabajo enviado')) === 1, 'queda en el historial del CRM');

// Reenvío de un plan ya enviado: sale otra vez y sigue enviado
$correos = [];
$r2 = at_pt_enviar_plan((int) $p->id);
ok($r2['ok'] === true && count($correos) === 1 && at_pt_plan((int) $p->id)->estado === 'enviado', 'un plan enviado se puede reenviar');

// Review Focus 4: renderer caído -> el correo sale igual, sin adjunto, y dice por qué
$p2 = plan_listo($m . 'b');
$renderer = 'caido';
$correos = [];
$r3 = at_pt_enviar_plan((int) $p2->id);
ok($r3['ok'] === true && $r3['sin_pdf'] === 'no se pudo descargar el PDF' && count($correos) === 1 && (array) $correos[0]['attachments'] === [], 'renderer caído: correo sin adjunto y con el motivo');
ok(strpos($correos[0]['message'] ?? '', 'adjunto') === false && strpos($correos[0]['message'] ?? '', 'ver-plan.php') !== false, 'sin PDF el correo no dice «adjunto» y lleva el enlace');
$renderer = 'ok';

// No se envía: borrador, sin vista, sin correo, correo que falla
$p3 = plan_listo($m . 'c');
ptc_estado((int) $p3->id, 'borrador');
$correos = [];
ok(at_pt_enviar_plan((int) $p3->id) === ['ok' => false, 'motivo' => 'no_listo', 'sin_pdf' => ''] && $correos === [], 'un borrador no se envía');
ptc_estado((int) $p3->id, 'listo');
at_pt_guardar((int) $p3->id, ['view_url' => '']);
ok(at_pt_enviar_plan((int) $p3->id)['motivo'] === 'no_listo', 'sin versión final no se envía');
$p4 = plan_listo($m . 'd', '');
$correos = [];
ok(at_pt_enviar_plan((int) $p4->id) === ['ok' => false, 'motivo' => 'sin_correo', 'sin_pdf' => ''] && $correos === [] && at_pt_plan((int) $p4->id)->estado === 'listo', 'Review Focus 3: sin correo no sale y sigue listo');
ok(at_pt_enviar_plan(999999999)['motivo'] === 'sin_plan', 'plan que no existe');
$p5 = plan_listo($m . 'e');
$falla = function () { return false; };
add_filter('pre_wp_mail', $falla, PHP_INT_MAX - 1);
remove_all_filters('pre_wp_mail', PHP_INT_MAX);
$r5 = at_pt_enviar_plan((int) $p5->id);
ok($r5['ok'] === false && $r5['motivo'] === 'correo_fallo' && at_pt_plan((int) $p5->id)->estado === 'listo', 'si el correo falla, el plan sigue listo');
remove_filter('pre_wp_mail', $falla, PHP_INT_MAX - 1);
add_filter('pre_wp_mail', '__return_true', PHP_INT_MAX);

// WhatsApp de Luis
$p6 = plan_listo($m . 'f');
$u = at_pt_url_whatsapp_cliente($p6);
ok(strpos($u, 'https://wa.me/56911111111?text=') === 0 && strpos(rawurldecode($u), home_url('/ver-plan.php?id=' . $p6->codigo)) !== false, 'wa.me al teléfono del cliente con el enlace del plan');
$w = at_pt_marcar_enviado_whatsapp((int) $p6->id);
ok($w['ok'] === true && $w['url'] === $u && at_pt_plan((int) $p6->id)->estado === 'enviado', 'abrir el WhatsApp deja el plan enviado');
ok(at_pt_marcar_enviado_whatsapp((int) $p6->id)['ok'] === true, 'se puede volver a abrir con el plan enviado');
$p7 = plan_listo($m . 'g');
$wpdb->update($wpdb->prefix . 'crm_clientes', ['telefono' => ''], ['id' => at_pt_crm_de_plan($p7)]);
$ph = json_decode((string) $wpdb->get_var($wpdb->prepare("SELECT placeholders FROM {$wpdb->prefix}automatiza_contracts WHERE id = %d", (int) $p7->contrato_id)), true);
$ph['telefono_cliente'] = '';
$wpdb->update($wpdb->prefix . 'automatiza_contracts', ['placeholders' => wp_json_encode($ph)], ['id' => (int) $p7->contrato_id]);
ok(at_pt_marcar_enviado_whatsapp((int) $p7->id) === ['ok' => false, 'motivo' => 'sin_telefono', 'url' => ''] && at_pt_plan((int) $p7->id)->estado === 'listo', 'sin teléfono no se abre y sigue listo');
ptc_estado((int) $p7->id, 'borrador');
ok(at_pt_marcar_enviado_whatsapp((int) $p7->id)['motivo'] === 'no_listo', 'un borrador no se manda por WhatsApp');

// El render pedido para un plan del sitio trae la agenda web
ok(at_pt_datos_render($p6)['sitio'] === home_url(), 'at_pt_datos_render() trae el sitio para la agenda web');

fin();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd <W> && AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> tests/plan/envio-wp-test.php`
Expected: `FALLA falta la función at_pt_enviar_plan()` y `1 FALLAS` (la ayuda `exigir()` de `wp-bootstrap.php`).

- [ ] **Step 3: Write minimal implementation**

Crear `wp-content/themes/automatiza-tech/inc/plan-trabajo/envio.php`:

```php
<?php
/**
 * Plan de trabajo, Etapa 2: Luis envía el plan «listo» al cliente por correo (PDF adjunto si se puede, enlace a
 * ver-plan.php siempre) o por su WhatsApp (wa.me con el enlace). Al salir, el plan queda «enviado» (decisión 5: solo con
 * su clic). Spec: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md, sección 7.
 */
if (!defined('ABSPATH')) {
	exit;
}

// En base64 el adjunto crece ~33 %: 15 MB quedan en ~20 MB, bajo los 25 MB por adjunto de Hostinger y Gmail (igual
// que AT_PA_PDF_MAX_BYTES de las propuestas).
const AT_PT_PDF_MAX_BYTES = 15728640;

function at_pt_host_renderer(): string {
	return defined('AT_PA_RENDERER_HOST') ? (string) AT_PA_RENDERER_HOST : 'n8n-propuesta-renderer.kchiba.easypanel.host';
}

/** Baja el PDF de la versión final a un archivo temporal propio. Solo desde https://<renderer>/p/<código>/presentation.pdf.
 *  WP_Error con el motivo legible si no se pudo. Quien lo use borra el archivo y su carpeta. */
function at_pt_descargar_pdf(string $url, string $codigo) {
	if (!preg_match('#^https://' . preg_quote(at_pt_host_renderer(), '#') . '/p/[A-Za-z0-9]{6,32}/presentation\.pdf$#', trim($url))) {
		return new WP_Error('pdf_url', 'el PDF no es del renderer');
	}
	$r = wp_remote_get(trim($url), ['timeout' => 60, 'limit_response_size' => AT_PT_PDF_MAX_BYTES + 1, 'redirection' => 0]);
	if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) !== 200) {
		return new WP_Error('pdf_descarga', 'no se pudo descargar el PDF');
	}
	$cuerpo = (string) wp_remote_retrieve_body($r);
	if (strlen($cuerpo) > AT_PT_PDF_MAX_BYTES) {
		return new WP_Error('pdf_grande', 'el PDF pesa más de 15 MB');
	}
	if (strncmp($cuerpo, '%PDF', 4) !== 0) {
		return new WP_Error('pdf_descarga', 'no se pudo descargar el PDF');
	}
	// Carpeta única por envío: dos envíos a la vez no se pisan el archivo.
	$dir = trailingslashit(get_temp_dir()) . 'at-plan-' . preg_replace('/[^A-Za-z0-9]/', '', $codigo) . '-' . wp_generate_password(8, false, false) . '/';
	wp_mkdir_p($dir);
	$archivo = $dir . 'Plan-de-trabajo.pdf';
	if (file_put_contents($archivo, $cuerpo) === false) {
		@rmdir($dir);
		return new WP_Error('pdf_descarga', 'no se pudo guardar el PDF');
	}
	return $archivo;
}

/** El plan se puede mandar: versión final lista (o ya enviada, para reenviar) y con su vista. */
function at_pt_se_puede_enviar(object $fila): bool {
	return in_array((string) $fila->estado, ['listo', 'enviado'], true) && trim((string) $fila->view_url) !== '';
}

/** Deja el plan «enviado» (si estaba listo) con la fecha de envío. false si otro proceso lo movió entretanto. */
function at_pt_marcar_enviado(object $fila): bool {
	$id = (int) $fila->id;
	if ((string) $fila->estado === 'listo' && !at_pt_cambiar_estado($id, 'enviado', '')) {
		return false;
	}
	return at_pt_guardar($id, ['enviado_at' => current_time('mysql')]);
}

/** Anota en el historial del CRM del cliente (si el módulo del cierre está cargado y hay cliente). */
function at_pt_historial(object $fila, string $tipo, string $titulo, string $detalle): void {
	$crm = at_pt_crm_de_plan($fila);
	if ($crm > 0 && function_exists('at_cc_historial_crm')) {
		at_cc_historial_crm($crm, $tipo, $titulo, $detalle);
	}
}

/**
 * Envía el plan al correo del cliente (at_pt_datos_agenda(): ficha del CRM o, si falta, el contrato). Reply-To al
 * correo principal del cierre y copia oculta de «Ajustes del cierre». El PDF va adjunto si se pudo bajar; si no, el
 * correo sale igual con el enlace y los botones (Review Focus 4). Devuelve ['ok', 'motivo', 'sin_pdf'].
 */
function at_pt_enviar_plan(int $id): array {
	$fila = at_pt_plan($id);
	if (!$fila) {
		return ['ok' => false, 'motivo' => 'sin_plan', 'sin_pdf' => ''];
	}
	if (!at_pt_se_puede_enviar($fila)) {
		return ['ok' => false, 'motivo' => 'no_listo', 'sin_pdf' => ''];
	}
	$d = at_pt_datos_agenda($id);
	$correo = (string) ($d['client_email'] ?? '');
	if ($correo === '') {
		return ['ok' => false, 'motivo' => 'sin_correo', 'sin_pdf' => ''];
	}
	$pl = at_pt_payload($fila);
	$codigo = (string) $fila->codigo;
	$proyecto = trim((string) ($pl['proyecto'] ?? '')) !== '' ? trim((string) $pl['proyecto']) : (string) ($d['company_name'] ?? '');
	$pdf = at_pt_descargar_pdf((string) $fila->pdf_url, $codigo);
	$adjuntos = is_wp_error($pdf) ? [] : [$pdf];
	$html = at_pt_correo_plan_html([
		'nombre'       => (string) ($d['client_name'] ?? ''),
		'proyecto'     => $proyecto,
		'logo'         => defined('AT_CC_LOGO') ? AT_CC_LOGO : home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png'),
		'url_ver'      => at_pt_url_ver_plan(home_url(), $codigo),
		'url_agendar'  => at_pt_url_ver_plan(home_url(), $codigo, true),
		'url_whatsapp' => at_pt_url_whatsapp_agenda(function_exists('at_cc_whatsapp_at') ? at_cc_whatsapp_at() : '56927002984', $codigo),
		'inicio'       => (string) ($pl['cronograma']['inicio'] ?? ($fila->fecha_inicio ?? '')),
		'entrega'      => at_pt_entrega_estimada($pl),
		'con_pdf'      => $adjuntos !== [],
	]);
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	$cabeceras = array_merge(
		['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $from . '>'],
		function_exists('at_cc_correo_avisos') ? ['Reply-To: ' . at_cc_correo_avisos()] : [],
		function_exists('at_cc_cabecera_copia') ? at_cc_cabecera_copia($correo) : []
	);
	$enviado = (bool) wp_mail($correo, 'Tu plan de trabajo — ' . ($proyecto !== '' ? $proyecto : 'AutomatizaTech'), $html, $cabeceras, $adjuntos);
	foreach ($adjuntos as $a) {
		@unlink($a);
		@rmdir(dirname($a));
	}
	$sin_pdf = is_wp_error($pdf) ? $pdf->get_error_message() : '';
	if (!$enviado) {
		return ['ok' => false, 'motivo' => 'correo_fallo', 'sin_pdf' => $sin_pdf];
	}
	if (!at_pt_marcar_enviado($fila)) {
		return ['ok' => false, 'motivo' => 'transicion', 'sin_pdf' => $sin_pdf];
	}
	at_pt_historial($fila, 'email', 'Plan de trabajo enviado', 'Se envió por correo el plan de trabajo ' . $codigo
		. ($sin_pdf === '' ? ', con el PDF adjunto.' : ', sin el PDF (' . $sin_pdf . '): el correo lleva el enlace.'));
	return ['ok' => true, 'motivo' => '', 'sin_pdf' => $sin_pdf];
}

/** wa.me al teléfono del cliente (ficha del CRM o contrato) con el mensaje y el enlace del plan; '' sin teléfono. */
function at_pt_url_whatsapp_cliente(object $fila): string {
	$d = at_pt_datos_agenda((int) $fila->id);
	$tel = at_pt_telefono_wa((string) ($d['phone'] ?? ''));
	if ($tel === '') {
		return '';
	}
	$pl = at_pt_payload($fila);
	$proyecto = trim((string) ($pl['proyecto'] ?? '')) !== '' ? trim((string) $pl['proyecto']) : (string) ($d['company_name'] ?? '');
	return 'https://wa.me/' . $tel . '?text=' . rawurlencode(at_pt_texto_whatsapp_envio((string) ($d['client_name'] ?? ''), $proyecto, at_pt_url_ver_plan(home_url(), (string) $fila->codigo)));
}

/** «Enviar por mi WhatsApp»: deja el plan enviado y devuelve el wa.me para abrirlo. Devuelve ['ok', 'motivo', 'url']. */
function at_pt_marcar_enviado_whatsapp(int $id): array {
	$fila = at_pt_plan($id);
	if (!$fila) {
		return ['ok' => false, 'motivo' => 'sin_plan', 'url' => ''];
	}
	if (!at_pt_se_puede_enviar($fila)) {
		return ['ok' => false, 'motivo' => 'no_listo', 'url' => ''];
	}
	$url = at_pt_url_whatsapp_cliente($fila);
	if ($url === '') {
		return ['ok' => false, 'motivo' => 'sin_telefono', 'url' => ''];
	}
	if (!at_pt_marcar_enviado($fila)) {
		return ['ok' => false, 'motivo' => 'transicion', 'url' => ''];
	}
	at_pt_historial($fila, 'whatsapp', 'Plan de trabajo enviado por WhatsApp', 'Se abrió el WhatsApp de Luis con el enlace del plan de trabajo ' . $fila->codigo . '.');
	return ['ok' => true, 'motivo' => '', 'url' => $url];
}
```

En `cargar.php`, después de `require_once __DIR__ . '/rest.php';`, agregar `require_once __DIR__ . '/envio.php';`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd <W> && AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> tests/plan/envio-wp-test.php`
Expected: `TODO OK`.

Si el tipo de historial `'whatsapp'` no existe en `crm_historial` (columna `tipo` con lista cerrada), usar `'pedido_respuesta'` como hace el cierre y ajustar la prueba.

- [ ] **Step 5: Commit**

```bash
cd <W>
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/envio.php wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php tests/plan/envio-wp-test.php
git commit -m "feat(plan): envío del plan por correo (PDF adjunto o enlace) y por el WhatsApp de Luis"
```

---

### Task 3: Botones «Enviar al cliente» y «Enviar por mi WhatsApp» en el panel

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php` (bloque `if ($estado === 'listo')` de `at_pt_render_plan()`, `at_pt_mensajes_panel()`, registro y funciones de las acciones)
- Test: `tests/plan/panel-envio-wp-test.php`

**Interfaces:**
- Consumes: Task 2 (`at_pt_enviar_plan`, `at_pt_marcar_enviado_whatsapp`, `at_pt_url_whatsapp_cliente`, `at_pt_se_puede_enviar`); Etapa 1 (`at_pt_accion_plan`, `at_pt_volver`, `at_pt_guardar_detalles`, `at_pt_datos_agenda`).
- Produces: acciones `admin_post_at_pt_enviar` → `at_pt_accion_enviar()` y `admin_post_at_pt_enviar_whatsapp` → `at_pt_accion_enviar_whatsapp()`; claves nuevas de `at_pt_mensajes_panel()`: `enviado`, `enviado_sin_pdf`, `no_listo`, `sin_correo`, `correo_fallo`, `sin_telefono`.

- [ ] **Step 1: Write the failing test**

Crear `tests/plan/panel-envio-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/panel-envio-wp-test.php
// Etapa 2, Task 3: botones de envío en la pestaña del plan y sus acciones admin-post (en un proceso aparte).
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_accion_enviar', 'at_pt_accion_enviar_whatsapp');
global $wpdb;
$admin = pt_admin_id();
wp_set_current_user($admin);
$m = ptc_marca();

/** Plan listo (con versión final) de un cliente nuevo. */
function plan_listo_panel(string $marca, string $correo = 'prueba-plan-panel@example.com'): array {
	$c = ptc_cliente($marca, $correo);
	$plan = ptc_plan(ptc_contrato($c['tech'], $marca, ptc_propuesta($marca), 'servicios', 'signed', $correo));
	ptc_sembrar((int) $plan->id);
	at_pt_guardar((int) $plan->id, ['estado' => 'listo', 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $plan->codigo . '/index.html', 'pdf_url' => 'https://' . at_pt_host_renderer() . '/p/' . $plan->codigo . '/presentation.pdf']);
	return ['plan' => at_pt_plan((int) $plan->id), 'crm' => $c['crm']];
}
function pestana(object $fila, int $crm): string {
	ob_start();
	at_pt_render_plan($fila, $crm);
	return (string) ob_get_clean();
}

// Render
$a = plan_listo_panel($m . 'a');
$h = pestana($a['plan'], $a['crm']);
ok(strpos($h, 'value="at_pt_enviar"') !== false && strpos($h, '📧 Enviar al cliente') !== false && strpos($h, 'confirm(') !== false, 'listo: botón «Enviar al cliente» con confirmación');
ok(strpos($h, 'value="at_pt_enviar_whatsapp"') !== false && strpos($h, 'target="_blank"') !== false && strpos($h, '💬 Enviar por mi WhatsApp') !== false, 'listo: «Enviar por mi WhatsApp» abre otra pestaña');
ok(strpos($h, 'prueba-plan-panel@example.com') !== false, 'dice a qué correo se envía');
ok(strpos($h, 'llega en la etapa 2') === false, 'ya no dice que el envío llega en la etapa 2');
ptc_estado((int) $a['plan']->id, 'borrador');
$hb = pestana(at_pt_plan((int) $a['plan']->id), $a['crm']);
ok(strpos($hb, 'value="at_pt_enviar"') === false && strpos($hb, 'value="at_pt_enviar_whatsapp"') === false, 'borrador: sin botones de envío');
ptc_estado((int) $a['plan']->id, 'enviado');
at_pt_guardar((int) $a['plan']->id, ['enviado_at' => '2026-10-01 10:00:00']);
$he = pestana(at_pt_plan((int) $a['plan']->id), $a['crm']);
ok(strpos($he, 'value="at_pt_enviar"') !== false && strpos($he, 'Reenviar') !== false && strpos($he, '2026-10-01 10:00:00') !== false, 'enviado: dice cuándo y permite reenviar');
$sin = plan_listo_panel($m . 'b', '');
$hs = pestana($sin['plan'], $sin['crm']);
ok(strpos($hs, 'no tiene correo') !== false && preg_match('/<button[^>]*disabled[^>]*>📧 Enviar al cliente/u', $hs) === 1, 'Review Focus 3: sin correo el botón queda deshabilitado y lo explica');

// Acciones (proceso aparte; el hijo corta los correos y las llamadas HTTP)
$b = plan_listo_panel($m . 'c');
$id = (int) $b['plan']->id;
$r = pt_correr_accion('at_pt_enviar', $admin, 'at_pt_plan_' . $id, ['plan_id' => $id, 'crm_id' => $b['crm']]);
$q = pt_query($r['redirect']);
ok(in_array($q['pt_msg'] ?? '', ['enviado', 'enviado_sin_pdf'], true) && at_pt_plan($id)->estado === 'enviado', 'la acción envía y vuelve con el aviso');
$w = pt_correr_accion('at_pt_enviar_whatsapp', $admin, 'at_pt_plan_' . $id, ['plan_id' => $id, 'crm_id' => $b['crm']]);
ok(strpos($w['redirect'], 'https://wa.me/56911111111?text=') === 0, 'WhatsApp: redirige al wa.me del cliente');
ptc_estado($id, 'borrador');
$r2 = pt_correr_accion('at_pt_enviar', $admin, 'at_pt_plan_' . $id, ['plan_id' => $id, 'crm_id' => $b['crm']]);
ok((pt_query($r2['redirect'])['pt_msg'] ?? '') === 'no_listo' && at_pt_plan($id)->estado === 'borrador', 'un borrador no se envía');
$r3 = pt_correr_accion('at_pt_enviar', $admin, 'at_pt_plan_0', ['plan_id' => $id, 'crm_id' => $b['crm']]);
ok($r3['redirect'] === '' && at_pt_plan($id)->estado === 'borrador', 'con un nonce que no es del plan no hace nada');
foreach (['enviado', 'enviado_sin_pdf', 'no_listo', 'sin_correo', 'correo_fallo', 'sin_telefono'] as $k) {
	ok(isset(at_pt_mensajes_panel()[$k]), "aviso del panel para «{$k}»");
}

fin();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd <W> && AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> tests/plan/panel-envio-wp-test.php`
Expected: `FALLA falta la función at_pt_accion_enviar()` y `1 FALLAS`.

- [ ] **Step 3: Write minimal implementation**

En `at_pt_mensajes_panel()` agregar antes de `'transicion'`:

```php
		'enviado'            => ['ok', 'Plan enviado por correo al cliente, con el PDF adjunto.'],
		'enviado_sin_pdf'    => ['aviso', 'Plan enviado por correo, pero sin el PDF adjunto: el correo lleva el enlace para verlo.'],
		'no_listo'           => ['error', 'Solo se envía un plan con la versión final lista.'],
		'sin_correo'         => ['error', 'El cliente no tiene correo en su ficha ni en el contrato: agrégalo en la ficha y vuelve a enviar.'],
		'correo_fallo'       => ['error', 'El correo no salió. El plan sigue «listo»: inténtalo de nuevo en un rato.'],
		'sin_telefono'       => ['error', 'El cliente no tiene teléfono en su ficha ni en el contrato.'],
```

En `at_pt_render_plan()`, reemplazar todo el bloque

```php
		<?php if ($estado === 'listo'): ?>
			<div class="notice notice-success inline"><p>Versión final lista. Si cambias algo y guardas, el plan vuelve a borrador y hay que aprobarlo de nuevo. El envío al cliente llega en la etapa 2.</p></div>
		<?php endif; ?>
```

por

```php
		<?php if (in_array($estado, ['listo', 'enviado'], true)):
			$env = at_pt_datos_agenda($id);
			$correo_cliente = (string) ($env['client_email'] ?? '');
			$wa_cliente = at_pt_url_whatsapp_cliente($fila);
			$listo_para_enviar = at_pt_se_puede_enviar($fila);
		?>
			<div class="notice notice-success inline at-pt-envio">
				<?php if ($estado === 'listo'): ?>
					<p><strong>Versión final lista.</strong> Revísala y envíasela al cliente. Si cambias algo y guardas, el plan vuelve a borrador y hay que aprobarlo de nuevo.</p>
				<?php else: ?>
					<p><strong>Enviado al cliente</strong> el <?php echo esc_html((string) ($fila->enviado_at ?? '')); ?>. Puedes reenviarlo; el cliente ya puede agendar su llamada de seguimiento desde el plan.</p>
				<?php endif; ?>
				<p><?php echo $correo_cliente !== '' ? 'Correo del cliente: <strong>' . esc_html($correo_cliente) . '</strong>.' : 'El cliente no tiene correo en su ficha ni en el contrato: agrégalo en la pestaña General para poder enviarlo.'; ?></p>
				<form method="post" action="<?php echo esc_url($accion); ?>" style="display:inline" onsubmit="return confirm(<?php echo esc_attr(wp_json_encode('¿Enviar el plan de trabajo a ' . ($correo_cliente !== '' ? $correo_cliente : 'el cliente') . '? Le llega el correo con el PDF y el enlace para agendar la llamada de seguimiento.', JSON_UNESCAPED_UNICODE)); ?>);">
					<?php echo $ocultos('at_pt_enviar'); ?>
					<button type="submit" class="button button-primary"<?php echo ($correo_cliente === '' || !$listo_para_enviar) ? ' disabled' : ''; ?>>📧 <?php echo esc_html($estado === 'enviado' ? 'Reenviar al cliente' : 'Enviar al cliente'); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url($accion); ?>" target="_blank" style="display:inline" onsubmit="return confirm(<?php echo esc_attr(wp_json_encode('Se abrirá tu WhatsApp con el mensaje y el enlace del plan, y el plan quedará como enviado. ¿Seguir?', JSON_UNESCAPED_UNICODE)); ?>);">
					<?php echo $ocultos('at_pt_enviar_whatsapp'); ?>
					<button type="submit" class="button"<?php echo ($wa_cliente === '' || !$listo_para_enviar) ? ' disabled' : ''; ?>>💬 Enviar por mi WhatsApp</button>
				</form>
				<?php if ($wa_cliente === ''): ?><p class="description">El cliente no tiene teléfono: el WhatsApp no se puede abrir.</p><?php endif; ?>
			</div>
		<?php endif; ?>
```

Nota: el texto del botón con «📧 Enviar al cliente» deja el emoji pegado al `>` del botón, como espera la prueba (`>📧 Enviar al cliente`).

Agregar el registro junto a los demás `add_action('admin_post_at_pt_…')`:

```php
add_action('admin_post_at_pt_enviar', 'at_pt_accion_enviar');
add_action('admin_post_at_pt_enviar_whatsapp', 'at_pt_accion_enviar_whatsapp');
```

y las funciones, después de `at_pt_accion_reintentar()`:

```php
/** «Enviar al cliente» (decisión 5: solo con el clic de Luis): correo con el PDF y el enlace; el plan queda enviado. */
function at_pt_accion_enviar(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	$r = at_pt_enviar_plan($id);
	if (!$r['ok']) {
		at_pt_volver($crm, $id, $r['motivo']);
	}
	if ($r['sin_pdf'] !== '') {
		at_pt_guardar_detalles($id, ['Motivo: ' . $r['sin_pdf'] . '.']);
		at_pt_volver($crm, $id, 'enviado_sin_pdf');
	}
	at_pt_volver($crm, $id, 'enviado');
}

/** «Enviar por mi WhatsApp»: deja el plan enviado y abre el wa.me del cliente (el formulario va en otra pestaña). */
function at_pt_accion_enviar_whatsapp(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	$r = at_pt_marcar_enviado_whatsapp($id);
	if (!$r['ok']) {
		at_pt_volver($crm, $id, $r['motivo']);
	}
	wp_redirect($r['url']); // wa.me no es del sitio: wp_safe_redirect lo cambiaría por el escritorio
	exit;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd <W> && for t in panel-envio panel-render panel-acciones agenda; do AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> tests/plan/$t-wp-test.php | tail -1; done`
Expected: cuatro líneas `TODO OK`. Si `panel-render-wp-test.php` buscaba el texto viejo «El envío al cliente llega en la etapa 2», cambiar esa aserción por la del texto nuevo «Versión final lista.».

- [ ] **Step 5: Commit**

```bash
cd <W>
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php tests/plan/panel-envio-wp-test.php tests/plan/panel-render-wp-test.php
git commit -m "feat(plan): botones Enviar al cliente y Enviar por mi WhatsApp en la ficha"
```

---

### Task 4: Ruta pública de la agenda web (`POST automatiza-tech/v1/plan-seguimiento`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/agenda.php`
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` (agregar `require_once __DIR__ . '/agenda.php';` después de `envio.php`)
- Test: `tests/plan/agenda-web-wp-test.php`

**Interfaces:**
- Consumes: Task 1 (`at_pt_motivo_hora_agenda`, `at_pt_mensajes_agenda`, `at_pt_token_agenda`, `at_pt_token_agenda_valido`, `at_pt_fecha_larga`); Task 2 (`at_pt_historial`); Etapa 1 (`at_pt_plan_por_codigo`, `at_pt_datos_agenda`); reuniones (`automatiza_tech_check_availability`, `automatiza_tech_check_slot_availability`, `automatiza_tech_create_followup_calendar_event`, `automatiza_tech_send_followup_email`); cierre (`at_cc_correo_avisos`).
- Produces:
  - `const AT_PT_AGENDA_INTENTOS_HORA = 5;`
  - `at_pt_token_agenda_hoy(string $codigo): string` (para la página)
  - `at_pt_agendar_seguimiento(array $entrada, string $ip): array` → `['ok' => bool, 'clave' => string, 'mensaje' => string, 'reunion_id' => int, 'estado_http' => int]`
  - ruta `POST /wp-json/automatiza-tech/v1/plan-seguimiento` con cuerpo JSON `{codigo, token, fecha, hora, sitio_web}` → `{ok, clave, mensaje}` y el estado HTTP de `estado_http`.

- [ ] **Step 1: Write the failing test**

Crear `tests/plan/agenda-web-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/agenda-web-wp-test.php
// Etapa 2, Task 4: la agenda web crea una reunión de SEGUIMIENTO (nunca una demo ni un lead), con token, límite por IP
// y una sola llamada futura por plan.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_agendar_seguimiento', 'at_pt_token_agenda_hoy');
global $wpdb;
$m = ptc_marca();
$reuniones = $wpdb->prefix . 'automatiza_followup_meetings';
$leads = $wpdb->prefix . 'automatiza_leads';

// Horario conocido (se restaura al terminar): lunes a viernes 09-18, sin feriados.
$horario_antes = get_option('automatiza_chat_schedule', null);
register_shutdown_function(function () use ($horario_antes) {
	$horario_antes === null ? delete_option('automatiza_chat_schedule') : update_option('automatiza_chat_schedule', $horario_antes);
});
$dias = [];
foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $d) { $dias[$d] = ['enabled' => true, 'start' => '09:00', 'end' => '18:00']; }
foreach (['saturday', 'sunday'] as $d) { $dias[$d] = ['enabled' => false, 'start' => '09:00', 'end' => '18:00']; }
update_option('automatiza_chat_schedule', $dias + ['holidays' => '']);
// Un día hábil de la semana que viene (siempre en el futuro y dentro de los 90 días).
$dia = new DateTimeImmutable(current_time('Y-m-d') . ' 00:00:00');
$dia = $dia->modify('+7 days');
while (in_array($dia->format('N'), ['6', '7'], true)) { $dia = $dia->modify('+1 day'); }
$fecha = $dia->format('Y-m-d');

$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return $nulo; }, 10, 2);

/** Plan enviado de un cliente nuevo (con o sin correo). */
function plan_enviado(string $marca, string $correo = 'prueba-plan-agenda@example.com'): object {
	$c = ptc_cliente($marca, $correo);
	$plan = ptc_plan(ptc_contrato($c['tech'], $marca, null, 'servicios', 'signed', $correo));
	ptc_sembrar((int) $plan->id);
	at_pt_guardar((int) $plan->id, ['estado' => 'enviado', 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $plan->codigo . '/index.html']);
	return at_pt_plan((int) $plan->id);
}
function pedir(object $plan, string $fecha, string $hora, string $ip, array $cambia = []): array {
	$r = at_pt_agendar_seguimiento($cambia + ['codigo' => (string) $plan->codigo, 'token' => at_pt_token_agenda_hoy((string) $plan->codigo), 'fecha' => $fecha, 'hora' => $hora, 'sitio_web' => ''], $ip);
	if (!empty($r['reunion_id'])) { $GLOBALS['ptc_creado']['reuniones'][] = (int) $r['reunion_id']; }
	return $r;
}
$ip = function (string $s) { return '203.0.113.' . (abs(crc32($s . $GLOBALS['m'])) % 250 + 1); };

$p = plan_enviado($m . 'a');
$leads_antes = (int) $wpdb->get_var("SELECT COUNT(*) FROM $leads");
$correos = [];
$r = pedir($p, $fecha, '10:00', $ip('a'));
$fila = $r['reunion_id'] ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $reuniones WHERE id = %d", $r['reunion_id'])) : null;
ok($r['ok'] === true && $r['clave'] === 'agendada' && $r['estado_http'] === 200 && $fila !== null, 'agenda la llamada');
ok($fila && $fila->meeting_date === $fecha && substr((string) $fila->meeting_time, 0, 5) === '10:00' && $fila->status === 'scheduled', 'reunión con la fecha y la hora elegidas, programada');
ok($fila && strpos((string) $fila->meeting_subject, 'Seguimiento del plan de trabajo') === 0 && strpos((string) $fila->notes, 'Plan de trabajo ' . $p->codigo) === 0 && $fila->client_email === 'prueba-plan-agenda@example.com', 'decisión 2: tipo fijo de seguimiento, datos del cliente desde el plan');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM $leads") === $leads_antes, 'decisión 2: ninguna demo ni lead');
ok(strpos($r['mensaje'], at_pt_fecha_larga($fecha)) !== false && strpos($r['mensaje'], '10:00') !== false, 'el mensaje dice el día y la hora');
$a_luis = array_values(array_filter($correos, function ($c) { return strpos((string) $c['subject'], 'agendó la llamada de seguimiento') !== false; }));
ok(count($a_luis) === 1, 'aviso a Luis por correo');

// Review Focus 1: una sola llamada futura por plan
$r2 = pedir($p, $fecha, '15:00', $ip('a2'));
ok($r2['ok'] === false && $r2['clave'] === 'ya_agendada' && $r2['estado_http'] === 409 && strpos($r2['mensaje'], at_pt_fecha_larga($fecha)) !== false, 'Review Focus 1: la segunda vez dice cuándo es la que ya tiene');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $reuniones WHERE notes LIKE %s", 'Plan de trabajo ' . $wpdb->esc_like((string) $p->codigo) . '%')) === 1, 'queda una sola reunión');

// Review Focus 2: la hora dejó de servir
$q = plan_enviado($m . 'b');
$rq = pedir($q, $fecha, '10:00', $ip('b'));
ok($rq['ok'] === false && $rq['clave'] === 'hora_ocupada' && $rq['estado_http'] === 409, 'Review Focus 2: hora tomada por otra reunión');
ok(pedir($q, $fecha, '08:00', $ip('b2'))['clave'] === 'fuera_de_horario', 'fuera de horario');
ok(pedir($q, '2020-01-06', '10:00', $ip('b3'))['clave'] === 'pasada', 'día pasado');
$sabado = $dia; while ($sabado->format('N') !== '6') { $sabado = $sabado->modify('+1 day'); }
ok(pedir($q, $sabado->format('Y-m-d'), '10:00', $ip('b4'))['clave'] === 'dia_no_disponible', 'día que no se atiende');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $reuniones WHERE notes LIKE %s", 'Plan de trabajo ' . $wpdb->esc_like((string) $q->codigo) . '%')) === 0, 'ninguno de esos intentos creó una reunión');

// Seguridad: token, trampa, estado del plan, código
ok(pedir($q, $fecha, '11:00', $ip('c'), ['token' => str_repeat('a', 24)])['clave'] === 'sesion_vencida', 'token que no es del plan: sesión vencida (403)');
ok(pedir($q, $fecha, '11:00', $ip('c2'), ['sitio_web' => 'spam'])['ok'] === false, 'campo trampa lleno: no agenda');
$borrador = plan_enviado($m . 'd');
ptc_estado((int) $borrador->id, 'listo');
$rb = pedir($borrador, $fecha, '11:00', $ip('d'));
ok($rb['clave'] === 'plan_no_disponible' && $rb['estado_http'] === 404, 'Review Focus 5: un plan que no está enviado no agenda (404)');
$ri = at_pt_agendar_seguimiento(['codigo' => 'NoExiste1234', 'token' => '', 'fecha' => $fecha, 'hora' => '11:00'], $ip('d2'));
ok($ri['clave'] === 'plan_no_disponible' && $ri['estado_http'] === 404, 'Review Focus 5: código inventado (404)');
ok(at_pt_agendar_seguimiento(['codigo' => ['x'], 'fecha' => $fecha], $ip('d3'))['estado_http'] === 404, 'entrada con forma rara: 404 sin errores');

// Review Focus 3: sin correo
$s = plan_enviado($m . 'e', '');
$rs = pedir($s, $fecha, '12:00', $ip('e'));
ok($rs['clave'] === 'sin_correo' && $rs['estado_http'] === 409, 'Review Focus 3: sin correo pide escribir por WhatsApp');

// Límite por IP
$l = plan_enviado($m . 'f');
$misma = $ip('limite');
$claves = [];
for ($i = 0; $i < AT_PT_AGENDA_INTENTOS_HORA + 1; $i++) { $claves[] = pedir($l, $fecha, '08:00', $misma)['clave']; }
ok(end($claves) === 'muchos_intentos' && count(array_filter($claves, function ($c) { return $c === 'fuera_de_horario'; })) === AT_PT_AGENDA_INTENTOS_HORA, 'el sexto intento de la misma IP en una hora se rechaza (429)');
ok(pedir($l, $fecha, '13:00', $ip('otra'))['ok'] === true, 'otra IP sí puede');

// La ruta REST existe, es pública y devuelve el estado HTTP
$req = new WP_REST_Request('POST', '/automatiza-tech/v1/plan-seguimiento');
$req->set_header('Content-Type', 'application/json');
$req->set_body(wp_json_encode(['codigo' => 'NoExiste1234', 'token' => '', 'fecha' => $fecha, 'hora' => '10:00']));
$res = rest_do_request($req);
ok($res->get_status() === 404 && ($res->get_data()['ok'] ?? null) === false && ($res->get_data()['mensaje'] ?? '') !== '', 'POST /plan-seguimiento: 404 con mensaje para un código inventado');

fin();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd <W> && AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> tests/plan/agenda-web-wp-test.php`
Expected: `FALLA falta la función at_pt_agendar_seguimiento()` y `1 FALLAS`.

- [ ] **Step 3: Write minimal implementation**

Crear `wp-content/themes/automatiza-tech/inc/plan-trabajo/agenda.php`:

```php
<?php
/**
 * Plan de trabajo, Etapa 2: agenda web de la llamada de seguimiento desde ver-plan.php.
 *   POST /wp-json/automatiza-tech/v1/plan-seguimiento  {codigo, token, fecha, hora, sitio_web} → {ok, clave, mensaje}
 * Crea una reunión de SEGUIMIENTO (wp_automatiza_followup_meetings) con los datos del cliente que salen del plan y su
 * evento en Google Calendar con Meet (automatiza_tech_create_followup_calendar_event). Nunca toca wp_automatiza_leads
 * (decisión 2). Pública: token del plan (at_pt_token_agenda), plan «enviado», campo trampa vacío, 5 intentos por IP y
 * por hora, y una sola llamada futura por plan.
 */
if (!defined('ABSPATH')) {
	exit;
}

const AT_PT_AGENDA_INTENTOS_HORA = 5;

add_action('rest_api_init', function () {
	register_rest_route('automatiza-tech/v1', '/plan-seguimiento', [
		'methods'             => 'POST',
		'callback'            => 'at_pt_rest_agendar',
		'permission_callback' => '__return_true', // pública: la protegen el token, el estado del plan y el límite por IP
	]);
});

/** Token del plan para hoy (lo escribe ver-plan.php en la página). */
function at_pt_token_agenda_hoy(string $codigo): string {
	return at_pt_token_agenda($codigo, intdiv(time(), 86400), wp_salt('nonce'));
}

function at_pt_rest_agendar(WP_REST_Request $r): WP_REST_Response {
	$cuerpo = $r->get_json_params();
	$res = at_pt_agendar_seguimiento(is_array($cuerpo) ? $cuerpo : [], (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
	return new WP_REST_Response(['ok' => $res['ok'], 'clave' => $res['clave'], 'mensaje' => $res['mensaje']], $res['estado_http']);
}

/** Respuesta de la agenda con el texto de at_pt_mensajes_agenda() (o $mensaje si viene). */
function at_pt_respuesta_agenda(bool $ok, string $clave, int $http, int $reunion = 0, string $mensaje = ''): array {
	return [
		'ok'          => $ok,
		'clave'       => $clave,
		'mensaje'     => $mensaje !== '' ? $mensaje : (at_pt_mensajes_agenda()[$clave] ?? at_pt_mensajes_agenda()['no_guardo']),
		'reunion_id'  => $reunion,
		'estado_http' => $http,
	];
}

/** Reunión de seguimiento futura ya agendada para este plan (la nota parte con «Plan de trabajo <código>»), o null. */
function at_pt_seguimiento_pendiente(string $codigo): ?object {
	global $wpdb;
	$t = $wpdb->prefix . 'automatiza_followup_meetings';
	$f = $wpdb->get_row($wpdb->prepare(
		"SELECT id, meeting_date, meeting_time FROM $t WHERE notes LIKE %s AND status = 'scheduled' AND meeting_date >= %s ORDER BY meeting_date, meeting_time LIMIT 1",
		'Plan de trabajo ' . $wpdb->esc_like($codigo) . '%',
		current_time('Y-m-d')
	));
	return $f ?: null;
}

/**
 * Agenda la llamada de seguimiento de un plan enviado. $entrada = {codigo, token, fecha, hora, sitio_web}; $ip para el
 * límite de intentos. Devuelve ['ok', 'clave', 'mensaje', 'reunion_id', 'estado_http'].
 */
function at_pt_agendar_seguimiento(array $entrada, string $ip): array {
	$texto = function (string $k) use ($entrada): string {
		return is_scalar($entrada[$k] ?? null) ? trim((string) $entrada[$k]) : '';
	};
	$codigo = $texto('codigo');
	$fila = $codigo !== '' ? at_pt_plan_por_codigo($codigo) : null;
	if (!$fila || (string) $fila->estado !== 'enviado') {
		return at_pt_respuesta_agenda(false, 'plan_no_disponible', 404);
	}
	if ($texto('sitio_web') !== '') {
		return at_pt_respuesta_agenda(false, 'no_guardo', 400); // campo trampa: un bot
	}
	if (!at_pt_token_agenda_valido($texto('token'), $codigo, intdiv(time(), 86400), wp_salt('nonce'))) {
		return at_pt_respuesta_agenda(false, 'sesion_vencida', 403);
	}
	// Límite por IP: cuenta cada intento con token válido, sirva o no la hora.
	$clave_ip = 'at_pt_agenda_' . md5($ip);
	$intentos = (int) get_transient($clave_ip);
	if ($intentos >= AT_PT_AGENDA_INTENTOS_HORA) {
		return at_pt_respuesta_agenda(false, 'muchos_intentos', 429);
	}
	set_transient($clave_ip, $intentos + 1, HOUR_IN_SECONDS);

	$d = at_pt_datos_agenda((int) $fila->id);
	if (($d['client_email'] ?? '') === '') {
		return at_pt_respuesta_agenda(false, 'sin_correo', 409);
	}
	$ya = at_pt_seguimiento_pendiente($codigo);
	if ($ya) {
		return at_pt_respuesta_agenda(false, 'ya_agendada', 409, 0, 'Ya tienes una llamada de seguimiento agendada para el '
			. at_pt_fecha_larga((string) $ya->meeting_date) . ' a las ' . substr((string) $ya->meeting_time, 0, 5) . '. Si necesitas cambiarla, escríbenos por WhatsApp.');
	}
	$fecha = $texto('fecha');
	$hora = substr($texto('hora'), 0, 5);
	$disp = [];
	if (function_exists('automatiza_tech_check_availability') && at_pt_ymd($fecha) !== '') {
		$pedido = new WP_REST_Request('POST', '/automatiza-tech/v1/check-availability');
		$pedido->set_param('date', $fecha);
		$res = automatiza_tech_check_availability($pedido);
		$disp = is_array($res) ? $res : [];
	}
	$motivo = at_pt_motivo_hora_agenda($fecha, $texto('hora'), current_time('Y-m-d H:i'), $disp);
	if ($motivo !== '') {
		return at_pt_respuesta_agenda(false, $motivo, in_array($motivo, ['hora_ocupada'], true) ? 409 : 400);
	}
	if (function_exists('automatiza_tech_check_slot_availability') && empty(automatiza_tech_check_slot_availability($fecha, $hora . ':00')['available'])) {
		return at_pt_respuesta_agenda(false, 'hora_ocupada', 409);
	}
	global $wpdb;
	$t = $wpdb->prefix . 'automatiza_followup_meetings';
	$ok = $wpdb->insert($t, [
		'client_name'     => (string) $d['client_name'],
		'client_email'    => (string) $d['client_email'],
		'company_name'    => (string) $d['company_name'],
		'phone'           => (string) $d['phone'],
		'meeting_date'    => $fecha,
		'meeting_time'    => $hora . ':00',
		'meeting_subject' => (string) $d['meeting_subject'],
		'notes'           => (string) $d['notes'] . ' Agendada por el cliente desde el plan.',
		'status'          => 'scheduled',
	]);
	$reunion = $ok ? (int) $wpdb->insert_id : 0;
	if ($reunion <= 0) {
		return at_pt_respuesta_agenda(false, 'no_guardo', 500);
	}
	// Evento en Google Calendar con Meet (n8n). Si n8n ya mandó el correo, no se repite; si no, va el de siempre.
	$cal = function_exists('automatiza_tech_create_followup_calendar_event') ? automatiza_tech_create_followup_calendar_event($reunion) : [];
	$cal = is_array($cal) ? $cal : [];
	if (!empty($cal['meet_link'])) {
		$wpdb->update($t, ['meet_link' => (string) $cal['meet_link']], ['id' => $reunion]);
	}
	if (empty($cal['email_sent']) && function_exists('automatiza_tech_send_followup_email') && automatiza_tech_send_followup_email($reunion)) {
		$wpdb->update($t, ['email_sent' => 1], ['id' => $reunion]);
	}
	$cuando = at_pt_fecha_larga($fecha) . ' a las ' . $hora;
	at_pt_historial($fila, 'reunion', 'Llamada de seguimiento agendada', 'El cliente agendó desde el plan ' . $codigo . ' la llamada del ' . $cuando . '.');
	if (function_exists('at_cc_correo_avisos')) {
		wp_mail(at_cc_correo_avisos(), '📅 ' . ($d['client_name'] !== '' ? $d['client_name'] : 'Un cliente') . ' agendó la llamada de seguimiento del plan',
			'<p>' . esc_html(($d['client_name'] ?: 'El cliente') . ($d['company_name'] !== '' ? ' (' . $d['company_name'] . ')' : '')) . ' agendó desde su plan de trabajo ' . esc_html($codigo)
			. ' la llamada de seguimiento del <strong>' . esc_html($cuando) . '</strong>.</p>'
			. '<p>' . (!empty($cal['success']) ? 'El evento quedó en Google Calendar' . (!empty($cal['meet_link']) ? ' con Meet.' : '.') : '<strong>No se pudo crear el evento en Google Calendar:</strong> créalo a mano desde «Reuniones de seguimiento».') . '</p>',
			['Content-Type: text/html; charset=UTF-8']);
	}
	return at_pt_respuesta_agenda(true, 'agendada', 200, $reunion, 'Listo: agendamos tu llamada de seguimiento para el ' . $cuando . '. Te llegará la invitación por correo.');
}
```

En `cargar.php`, después de `require_once __DIR__ . '/envio.php';`, agregar `require_once __DIR__ . '/agenda.php';`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd <W> && AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> tests/plan/agenda-web-wp-test.php`
Expected: `TODO OK`.

Si `crm_historial.tipo` no acepta `'reunion'`, usar el tipo que use `inc/admin-followup-meetings.php` para las reuniones (buscar `crm_historial` en ese archivo) y ajustar.

- [ ] **Step 5: Commit**

```bash
cd <W>
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/agenda.php wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php tests/plan/agenda-web-wp-test.php
git commit -m "feat(plan): agenda web de la llamada de seguimiento (ruta pública plan-seguimiento)"
```

---

### Task 5: Página pública `ver-plan.php`

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/vista.php`
- Create: `ver-plan.php` (raíz del sitio, junto a `ver-presentacion.php`)
- Create: `wp-content/themes/automatiza-tech/assets/css/plan-ver.css`
- Create: `wp-content/themes/automatiza-tech/assets/js/plan-ver.js`
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` (agregar `require_once __DIR__ . '/vista.php';` después de `agenda.php`)
- Test: `tests/plan/ver-plan-wp-test.php`

**Interfaces:**
- Consumes: Task 1 (`at_pt_url_whatsapp_agenda`), Task 4 (`at_pt_token_agenda_hoy`, `at_pt_seguimiento_pendiente`, `at_pt_fecha_larga`), Etapa 1 (`at_pt_plan_por_codigo`, `at_pt_payload`), cierre (`at_cc_whatsapp_at`, `AT_CC_LOGO`), `assets/home-premium/at-agenda.js` (ids `at-fecha`, `at-slots`, `at-scheduled-time`, `at-franja`; `window.AT_AGENDA.configUrl`).
- Produces:
  - `at_pt_fila_ver_plan(string $codigo): ?object` (solo planes `listo` o `enviado` con `view_url`)
  - `at_pt_html_ver_plan(?object $fila, bool $abrir_agenda): string` (documento HTML completo)

- [ ] **Step 1: Write the failing test**

Crear `tests/plan/ver-plan-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/ver-plan-wp-test.php
// Etapa 2, Task 5: la página pública ver-plan.php (iframe del renderer, barra con los dos botones y diálogo de agenda).
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_fila_ver_plan', 'at_pt_html_ver_plan');
$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-plan-ver@example.com');
$plan = ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-plan-ver@example.com'));
ptc_sembrar((int) $plan->id);
$vista = 'https://' . at_pt_host_renderer() . '/p/' . $plan->codigo . '/index.html';
at_pt_guardar((int) $plan->id, ['estado' => 'enviado', 'view_url' => $vista]);
$codigo = (string) $plan->codigo;

$fila = at_pt_fila_ver_plan($codigo);
$h = at_pt_html_ver_plan($fila, false);
ok($fila !== null && strpos($h, '<iframe src="' . esc_url($vista) . '"') !== false, 'muestra el plan del renderer en un iframe');
ok(strpos($h, 'Agendar mi llamada de seguimiento') !== false && strpos($h, 'Agendar por WhatsApp con Tech') !== false && strpos($h, 'https://wa.me/') !== false, 'barra con los dos botones de agenda');
ok(strpos($h, 'id="at-fecha"') !== false && strpos($h, 'id="at-slots"') !== false && strpos($h, 'id="at-scheduled-time"') !== false && strpos($h, 'at-agenda.js') !== false, 'diálogo con el selector de horarios de la portada');
ok(strpos($h, at_pt_token_agenda_hoy($codigo)) !== false && strpos($h, rest_url('automatiza-tech/v1/plan-seguimiento')) !== false && strpos($h, '"abrir":false') !== false, 'trae el token del plan y la ruta de la agenda; sin agendar=1 el diálogo no se abre solo');
ok(strpos(at_pt_html_ver_plan($fila, true), '"abrir":true') !== false, 'con agendar=1 el diálogo se abre solo');
ok(strpos($h, 'name="sitio_web"') !== false && stripos($h, 'aceptar') === false, 'campo trampa y sin aceptar ni rechazar');
ok(stripos($h, 'noindex') !== false, 'no se indexa');

// Ya agendada: el botón web avisa la fecha en vez de abrir el selector
global $wpdb;
$wpdb->insert($wpdb->prefix . 'automatiza_followup_meetings', ['client_name' => 'Cliente Prueba', 'client_email' => 'prueba-plan-ver@example.com', 'meeting_date' => gmdate('Y-m-d', time() + 5 * 86400), 'meeting_time' => '10:00:00', 'notes' => 'Plan de trabajo ' . $codigo . ' (prueba).', 'status' => 'scheduled']);
$GLOBALS['ptc_creado']['reuniones'][] = (int) $wpdb->insert_id;
$hy = at_pt_html_ver_plan(at_pt_fila_ver_plan($codigo), true);
ok(strpos($hy, 'Ya tienes tu llamada de seguimiento') !== false && strpos($hy, 'id="at-fecha"') === false, 'con una llamada ya agendada lo dice y no muestra el selector');

// Listo (vista de Luis antes de enviar): se ve, pero la agenda no está activa
at_pt_guardar((int) $plan->id, ['estado' => 'listo']);
$hl = at_pt_html_ver_plan(at_pt_fila_ver_plan($codigo), true);
ok(strpos($hl, '<iframe') !== false && strpos($hl, 'id="at-fecha"') === false && strpos($hl, 'cuando te enviemos el plan') !== false, 'listo: se ve el plan y la agenda espera al envío');

// Review Focus 5: borrador, código inventado o con otro largo
at_pt_guardar((int) $plan->id, ['estado' => 'borrador']);
ok(at_pt_fila_ver_plan($codigo) === null && at_pt_fila_ver_plan('NoExiste1234') === null && at_pt_fila_ver_plan('abc') === null && at_pt_fila_ver_plan('') === null, 'Review Focus 5: borrador, código inventado o corto: sin plan');
$hn = at_pt_html_ver_plan(null, false);
ok(strpos($hn, 'no está disponible') !== false && strpos($hn, '<iframe') === false && strpos($hn, $codigo) === false, 'sin plan: «no está disponible» sin mostrar nada');

// ver-plan.php solo carga WordPress y llama a la vista
$archivo = (string) file_get_contents(dirname(__DIR__, 2) . '/ver-plan.php');
ok(strpos($archivo, "require_once __DIR__ . '/wp-load.php'") !== false && strpos($archivo, 'at_pt_html_ver_plan(') !== false && strpos($archivo, 'nocache_headers()') !== false && strpos($archivo, 'X-Robots-Tag') !== false, 'ver-plan.php: carga WordPress, sin caché, noindex, y dibuja la vista');

fin();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd <W> && AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> tests/plan/ver-plan-wp-test.php`
Expected: `FALLA falta la función at_pt_fila_ver_plan()` y `1 FALLAS`.

- [ ] **Step 3: Write minimal implementation**

Crear `wp-content/themes/automatiza-tech/inc/plan-trabajo/vista.php`:

```php
<?php
/**
 * Plan de trabajo, Etapa 2: la página pública ver-plan.php?id=<código>[&agendar=1]. iframe del renderer, barra con los
 * dos botones de agenda (web y WhatsApp con Tech) y el diálogo con el selector de horarios de la portada (at-agenda.js).
 * Sin aceptar ni rechazar. Solo planes «listo» (Luis lo mira antes de enviar) o «enviado»; la agenda web solo en
 * «enviado» (la ruta de agenda.php lo exige igual).
 */
if (!defined('ABSPATH')) {
	exit;
}

/** El plan de ese código si se puede mostrar (listo o enviado, con su vista); null si no. */
function at_pt_fila_ver_plan(string $codigo): ?object {
	$f = at_pt_plan_por_codigo($codigo);
	if (!$f || !in_array((string) $f->estado, ['listo', 'enviado'], true) || trim((string) $f->view_url) === '') {
		return null;
	}
	return $f;
}

/** Documento HTML de ver-plan.php. $abrir_agenda = vino con agendar=1. */
function at_pt_html_ver_plan(?object $fila, bool $abrir_agenda): string {
	$tema = get_template_directory_uri();
	$dir = get_template_directory();
	$ver = function (string $rel) use ($dir): string {
		$f = $dir . $rel;
		return file_exists($f) ? (string) filemtime($f) : '1';
	};
	$cabeza = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
		. '<meta name="robots" content="noindex, nofollow"><title>Plan de trabajo — AutomatizaTech</title>'
		. '<link rel="stylesheet" href="' . esc_url($tema . '/assets/css/plan-ver.css?v=' . $ver('/assets/css/plan-ver.css')) . '"></head>';
	if (!$fila) {
		return $cabeza . '<body class="at-pt-ver at-pt-ver--vacio"><main class="at-pt-vacio"><h1>Este plan de trabajo no está disponible</h1>'
			. '<p>Revisa el enlace que te enviamos o escríbenos a contacto@automatizatech.cl.</p></main></body></html>';
	}
	$codigo = (string) $fila->codigo;
	$enviado = (string) $fila->estado === 'enviado';
	$wa = at_pt_url_whatsapp_agenda(function_exists('at_cc_whatsapp_at') ? at_cc_whatsapp_at() : '56927002984', $codigo);
	$ya = $enviado ? at_pt_seguimiento_pendiente($codigo) : null;
	$logo = defined('AT_CC_LOGO') ? AT_CC_LOGO : home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png');
	$html = $cabeza . '<body class="at-pt-ver">'
		. '<iframe src="' . esc_url((string) $fila->view_url) . '" title="Plan de trabajo" allow="fullscreen"></iframe>'
		. '<nav class="at-pt-barra" aria-label="Agenda tu llamada de seguimiento"><p>¿Revisamos juntos tu plan?</p>';
	if ($enviado && $ya) {
		$html .= '<p class="at-pt-ya">Ya tienes tu llamada de seguimiento el ' . esc_html(at_pt_fecha_larga((string) $ya->meeting_date) . ' a las ' . substr((string) $ya->meeting_time, 0, 5)) . '.</p>';
	} elseif ($enviado) {
		$html .= '<button type="button" class="at-pt-btn at-pt-btn--si" data-at-pt-abrir>📅 Agendar mi llamada de seguimiento</button>';
	} else {
		$html .= '<p class="at-pt-ya">Podrás agendar tu llamada de seguimiento aquí cuando te enviemos el plan.</p>';
	}
	if ($wa !== '') {
		$html .= '<a class="at-pt-btn at-pt-btn--sec" href="' . esc_url($wa) . '" target="_blank" rel="noopener">💬 Agendar por WhatsApp con Tech</a>';
	}
	$html .= '</nav>';
	if ($enviado && !$ya) {
		$html .= '<dialog id="at-pt-agenda" class="at-pt-dlg" aria-labelledby="at-pt-agenda-titulo">'
			. '<form id="at-pt-form-agenda" novalidate>'
			. '<div class="at-pt-dlg-cab"><img src="' . esc_url($logo) . '" alt="" width="53" height="44"><span>AutomatizaTech</span></div>'
			. '<h2 id="at-pt-agenda-titulo">Agenda tu llamada de seguimiento</h2>'
			. '<p>Revisamos juntos tu plan de trabajo y aclaramos tus dudas por videollamada. Te llega la invitación por correo.</p>'
			. '<p id="at-pt-agenda-msg" class="at-pt-msg" role="status" hidden></p>'
			. '<div class="at-pt-campos">'
			. '<label for="at-fecha">Día</label><input type="date" id="at-fecha" required>'
			. '<p class="at-pt-etiqueta">Hora</p><div id="at-slots" class="at-pt-horas"><span>Elige una fecha para ver horarios disponibles.</span></div>'
			. '<input type="hidden" id="at-scheduled-time" value=""><input type="hidden" id="at-franja" value="">'
			. '<div class="at-pt-trampa" aria-hidden="true"><label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>'
			. '</div>'
			. '<div class="at-pt-dlg-botones"><button type="button" class="at-pt-btn at-pt-btn--sec" id="at-pt-cerrar">Cerrar</button>'
			. '<button type="submit" class="at-pt-btn at-pt-btn--si">Agendar</button></div>'
			. '</form></dialog>'
			. '<script>window.AT_AGENDA = ' . wp_json_encode(['configUrl' => esc_url_raw(rest_url('automatiza-tech/v1/appointments-config'))]) . ';'
			. 'window.AT_PT_VER = ' . wp_json_encode(['url' => esc_url_raw(rest_url('automatiza-tech/v1/plan-seguimiento')), 'codigo' => $codigo, 'token' => at_pt_token_agenda_hoy($codigo), 'abrir' => $abrir_agenda], JSON_UNESCAPED_SLASHES) . ';</script>'
			. '<script src="' . esc_url($tema . '/assets/home-premium/at-agenda.js?v=' . $ver('/assets/home-premium/at-agenda.js')) . '"></script>'
			. '<script src="' . esc_url($tema . '/assets/js/plan-ver.js?v=' . $ver('/assets/js/plan-ver.js')) . '"></script>';
	}
	return $html . '</body></html>';
}
```

Crear `ver-plan.php` en la raíz:

```php
<?php
// ver-plan.php?id=<código>[&agendar=1] — plan de trabajo del cliente (Etapa 2). La lógica vive en
// wp-content/themes/automatiza-tech/inc/plan-trabajo/vista.php.
require_once __DIR__ . '/wp-load.php';

$codigo = isset($_GET['id']) ? preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_GET['id'])) : '';
// La página lleva el token del día: nunca se guarda en caché.
if (!defined('DONOTCACHEPAGE')) {
	define('DONOTCACHEPAGE', true);
}
nocache_headers();
header('X-Robots-Tag: noindex, nofollow');
if (!function_exists('at_pt_html_ver_plan')) {
	status_header(503);
	exit('Plan de trabajo no disponible por ahora.');
}
$fila = at_pt_fila_ver_plan((string) $codigo);
if (!$fila) {
	status_header(404);
}
echo at_pt_html_ver_plan($fila, !empty($_GET['agendar']));
```

Crear `wp-content/themes/automatiza-tech/assets/css/plan-ver.css`:

```css
/* ver-plan.php — colores del logo de AutomatizaTech (los mismos de la barra del cierre). */
:root{--at-marino:#063f76;--at-turquesa:#17b7b1;--at-verde:#4abc9b;--at-noche:#0a1628;--text-faint:#64748b}
html,body{margin:0;padding:0;height:100%;background:#000}
body.at-pt-ver{display:flex;flex-direction:column;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
body.at-pt-ver>iframe{flex:1 1 auto;width:100%;min-height:0;border:0}
.at-pt-barra{flex:0 0 auto;background:var(--at-noche);border-top:3px solid var(--at-turquesa);color:#f8fafc;padding:10px 16px;display:flex;gap:10px;align-items:center;justify-content:center;flex-wrap:wrap}
.at-pt-barra p{margin:0;font-size:15px}
.at-pt-ya{color:#cbd5e1}
.at-pt-btn{border:0;border-radius:999px;padding:11px 20px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-block;min-height:44px;box-sizing:border-box}
.at-pt-btn--si{background:var(--at-turquesa);color:var(--at-noche)}
.at-pt-btn--sec{background:transparent;color:#e2e8f0;border:1px solid #2c4f78}
.at-pt-btn:focus-visible{outline:3px solid var(--at-verde);outline-offset:2px}
.at-pt-btn[disabled]{opacity:.6;cursor:wait}
dialog.at-pt-dlg{border:0;border-radius:14px;padding:20px;max-width:440px;width:calc(100% - 32px);max-height:calc(100dvh - 32px);overflow-y:auto;box-sizing:border-box;color:#0f172a}
dialog.at-pt-dlg::backdrop{background:rgba(10,22,40,.75)}
.at-pt-dlg-cab{display:flex;align-items:center;gap:10px;font-weight:700;color:var(--at-marino)}
.at-pt-dlg h2{margin:12px 0 6px;font-size:20px;color:var(--at-marino)}
.at-pt-dlg p{margin:0 0 10px;font-size:15px;line-height:1.5}
.at-pt-dlg label,.at-pt-etiqueta{display:block;font-weight:600;margin:10px 0 4px;font-size:14px}
.at-pt-dlg input[type=date]{width:100%;padding:10px;font-size:16px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
.at-pt-horas{display:flex;flex-wrap:wrap;gap:8px;min-height:44px;align-items:center}
.at-slot-btn{min-width:72px;min-height:44px;border:1px solid #cbd5e1;background:#fff;border-radius:8px;font-size:15px;cursor:pointer}
.at-slot-btn.is-active{background:var(--at-marino);color:#fff;border-color:var(--at-marino)}
.at-pt-dlg-botones{display:flex;gap:10px;justify-content:flex-end;margin-top:16px}
.at-pt-dlg .at-pt-btn--sec{color:var(--at-marino);border-color:#cbd5e1}
.at-pt-msg{padding:8px 12px;border-radius:8px;font-size:14px}
.at-pt-msg--ok{background:#d1fae5;color:#064e3b}.at-pt-msg--aviso{background:#fef3c7;color:#78350f}.at-pt-msg--error{background:#fee2e2;color:#7f1d1d}
.at-pt-trampa{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.at-pt-vacio{color:#f8fafc;max-width:520px;margin:20vh auto 0;padding:0 16px;text-align:center}
@media (prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important}}
```

Crear `wp-content/themes/automatiza-tech/assets/js/plan-ver.js`:

```js
/* ver-plan.php: abre el diálogo de agenda y envía la hora elegida a POST automatiza-tech/v1/plan-seguimiento.
 * El selector de horarios es at-agenda.js (el de la portada): escribe la hora en #at-scheduled-time. */
(function () {
  'use strict';
  var cfg = window.AT_PT_VER || {};
  var dlg = document.getElementById('at-pt-agenda');
  if (!dlg) return;
  function abrir() { if (typeof dlg.showModal === 'function') { if (!dlg.open) dlg.showModal(); } else { dlg.setAttribute('open', ''); } }
  Array.prototype.forEach.call(document.querySelectorAll('[data-at-pt-abrir]'), function (b) { b.addEventListener('click', abrir); });
  var cerrar = document.getElementById('at-pt-cerrar');
  if (cerrar) cerrar.addEventListener('click', function () { dlg.close ? dlg.close() : dlg.removeAttribute('open'); });
  if (cfg.abrir) abrir();

  var form = document.getElementById('at-pt-form-agenda');
  var msg = document.getElementById('at-pt-agenda-msg');
  var boton = form.querySelector('button[type=submit]');
  var enviando = false;
  function aviso(tipo, texto) { msg.className = 'at-pt-msg at-pt-msg--' + tipo; msg.textContent = texto; msg.hidden = false; }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (enviando) return;
    var fecha = document.getElementById('at-fecha').value;
    var hora = (document.getElementById('at-scheduled-time').value || '').slice(0, 5);
    if (!fecha || !hora) { aviso('aviso', 'Elige un día y una hora.'); return; }
    enviando = true;
    boton.disabled = true;
    aviso('aviso', 'Agendando…');
    // Sin cookies: la ruta es pública y valida el token del plan (con la sesión de WordPress, la API exigiría otro nonce).
    fetch(cfg.url, {
      method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: cfg.codigo, token: cfg.token, fecha: fecha, hora: hora, sitio_web: form.elements.sitio_web.value })
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (j) {
        if (j && j.ok) {
          aviso('ok', j.mensaje);
          form.querySelector('.at-pt-campos').hidden = true;
          boton.hidden = true;
          Array.prototype.forEach.call(document.querySelectorAll('[data-at-pt-abrir]'), function (b) { b.hidden = true; });
        } else {
          aviso('error', (j && j.mensaje) || 'No pudimos agendar la llamada. Inténtalo de nuevo o escríbenos por WhatsApp.');
          boton.disabled = false;
        }
      })
      .catch(function () { aviso('error', 'Sin conexión. Inténtalo de nuevo.'); boton.disabled = false; })
      .then(function () { enviando = false; });
  });
})();
```

En `cargar.php`, después de `require_once __DIR__ . '/agenda.php';`, agregar `require_once __DIR__ . '/vista.php';`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd <W> && AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> tests/plan/ver-plan-wp-test.php`
Expected: `TODO OK`.

- [ ] **Step 5: Commit**

```bash
cd <W>
git add ver-plan.php wp-content/themes/automatiza-tech/inc/plan-trabajo/vista.php wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php wp-content/themes/automatiza-tech/assets/css/plan-ver.css wp-content/themes/automatiza-tech/assets/js/plan-ver.js tests/plan/ver-plan-wp-test.php
git commit -m "feat(plan): página pública ver-plan.php con la agenda de la llamada de seguimiento"
```

---

### Task 6: Prueba de punta a punta en el navegador, suite completa y documentación

**Files:**
- Create: `tests/plan/etapa2-e2e.js` (Playwright; se corre con `NODE_PATH=C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer/node_modules`)
- Modify: `Docs/METODO_AT/PLAN-DE-TRABAJO.md` (sección «Etapa 2: el cliente recibe»)
- Modify: `CLAUDE.md` y `AGENTS.md` (puntero de una línea: Etapa 2 hecha en la rama, sin desplegar)

**Interfaces:**
- Consumes: todo lo anterior; el sitio local `wp-local-plan` servido en `http://localhost:8093` (el mismo que usan las pruebas; si no está levantado, levantarlo como en la Etapa 1 con el servidor PHP de `<SCR>/wp-local-plan` y su `router.php`).
- Produces: capturas `<SCR>/plan-etapa2/ver-plan-390.png` y `ver-plan-1280.png`; documentación.

- [ ] **Step 1: Write the end-to-end script**

Crear `tests/plan/etapa2-e2e.js`:

```js
// Uso: NODE_PATH=<renderer>/node_modules node tests/plan/etapa2-e2e.js <url ver-plan.php?id=…&agendar=1> <carpeta capturas>
// Abre la página a 390 px y 1280 px, comprueba que el diálogo se abre solo, elige el primer horario libre de un día
// hábil de la semana siguiente y agenda; espera el mensaje «Listo: agendamos…». Sale con 1 si algo falla.
const { chromium } = require('playwright');
(async () => {
  const [url, carpeta] = process.argv.slice(2);
  if (!url || !carpeta) { console.error('faltan argumentos'); process.exit(2); }
  const nav = await chromium.launch();
  let fallas = 0;
  const ok = (c, m) => { console.log((c ? 'ok   ' : 'FALLA ') + m); if (!c) fallas++; };
  for (const [ancho, alto, agendar] of [[1280, 800, false], [390, 844, true]]) {
    const p = await nav.newPage({ viewport: { width: ancho, height: alto } });
    const errores = [];
    p.on('pageerror', e => errores.push(String(e)));
    await p.goto(url, { waitUntil: 'networkidle' });
    ok(await p.locator('dialog#at-pt-agenda[open]').count() === 1, ancho + ' px: el diálogo se abre solo con agendar=1');
    const dia = new Date(); dia.setDate(dia.getDate() + 7);
    while (dia.getDay() === 0 || dia.getDay() === 6) dia.setDate(dia.getDate() + 1);
    const ymd = dia.toISOString().slice(0, 10);
    await p.fill('#at-fecha', ymd);
    await p.dispatchEvent('#at-fecha', 'change');
    await p.waitForSelector('.at-slot-btn', { timeout: 10000 });
    ok(await p.locator('.at-slot-btn').count() > 0, ancho + ' px: muestra horarios libres');
    const anchoPagina = await p.evaluate(() => document.documentElement.scrollWidth);
    ok(anchoPagina <= ancho, ancho + ' px: sin desplazamiento horizontal');
    await p.screenshot({ path: carpeta + '/ver-plan-' + ancho + '.png' });
    if (agendar) {
      await p.locator('.at-slot-btn').first().click();
      await p.click('#at-pt-form-agenda button[type=submit]');
      await p.waitForSelector('.at-pt-msg--ok, .at-pt-msg--error', { timeout: 20000 });
      const texto = await p.textContent('#at-pt-agenda-msg');
      ok(/^Listo: agendamos tu llamada/.test(texto || ''), 'agenda y confirma: ' + texto);
    }
    ok(errores.length === 0, ancho + ' px: sin errores de JavaScript ' + errores.join(' | '));
    await p.close();
  }
  await nav.close();
  process.exit(fallas ? 1 : 0);
})();
```

- [ ] **Step 2: Prepare a sent plan in the local site and run it**

Crear el script de una sola vez `<SCR>/plan-etapa2/sembrar.php` (no va al repositorio):

```php
<?php
// Uso: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> <SCR>/plan-etapa2/sembrar.php crear|limpiar <código>
// Deja en el sitio local un plan [PRUEBA] «enviado» para mirar ver-plan.php en el navegador, o lo borra.
define('PTC_CONSERVAR', true);
$W = 'C:/wamp64/www/automatiza-tech/.worktrees/plan-trabajo';
require $W . '/tests/plan/wp-bootstrap.php';
require $W . '/tests/plan/fixtures-panel.php';
global $wpdb;
if (($argv[1] ?? '') === 'limpiar') {
	$f = at_pt_plan_por_codigo((string) ($argv[2] ?? ''));
	if (!$f) { exit("no existe
"); }
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_followup_meetings WHERE notes LIKE %s", 'Plan de trabajo ' . $wpdb->esc_like((string) $f->codigo) . '%'));
	$c = $wpdb->get_row($wpdb->prepare("SELECT client_id FROM {$wpdb->prefix}automatiza_contracts WHERE id = %d", (int) $f->contrato_id));
	$crm = (int) $wpdb->get_var($wpdb->prepare("SELECT crm_cliente_id FROM {$wpdb->prefix}automatiza_tech_clients WHERE id = %d", (int) ($c->client_id ?? 0)));
	$wpdb->delete(at_pt_tabla(), ['id' => (int) $f->id]);
	$wpdb->delete($wpdb->prefix . 'automatiza_contracts', ['id' => (int) $f->contrato_id]);
	$wpdb->delete($wpdb->prefix . 'automatiza_tech_clients', ['id' => (int) ($c->client_id ?? 0)]);
	$wpdb->delete($wpdb->prefix . 'crm_historial', ['cliente_id' => $crm]);
	$wpdb->delete($wpdb->prefix . 'crm_clientes', ['id' => $crm]);
	exit("limpio
");
}
$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-plan-e2e@example.com');
$plan = ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-plan-e2e@example.com'));
ptc_sembrar((int) $plan->id);
at_pt_guardar((int) $plan->id, ['estado' => 'enviado', 'view_url' => 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/u9Xkhqjyf78h/index.html']);
echo $plan->codigo, "
";
```

Run: `AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> <SCR>/plan-etapa2/sembrar.php crear` → imprime el código (la vista de fondo es solo el iframe de un plan de PROD que ya existe; no se toca).

Run: `cd <W> && NODE_PATH=C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer/node_modules node tests/plan/etapa2-e2e.js "http://localhost:8093/ver-plan.php?id=<código>&agendar=1" <SCR>/plan-etapa2`
Expected: todas las líneas `ok`, código de salida 0. En el sitio local el evento de Google Calendar falla (sin n8n): el mensaje igual es «Listo: agendamos…» y la reunión queda en «Reuniones de seguimiento». Mirar las dos capturas.

Después: `AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> <SCR>/plan-etapa2/sembrar.php limpiar <código>` → `limpio`.

- [ ] **Step 3: Run the whole plan suite**

Run:

```bash
cd <W>
for t in envio render validacion cronograma fechas; do <PHP> tests/plan/$t-test.php | tail -1; done
for t in tests/plan/*-wp-test.php; do echo "$t: $(AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php <PHP> $t | tail -1)"; done
for t in contrato cierre firma-cliente-visor revision-final; do AT_WP_LOAD=$(pwd)/wp-load.php <PHP> tests/cierre/$t-wp-test.php | tail -1; done
```

Expected: todas `TODO OK`.

- [ ] **Step 4: Document**

En `Docs/METODO_AT/PLAN-DE-TRABAJO.md` agregar la sección:

```markdown
## Etapa 2: el cliente recibe (rama `claude/plan-de-trabajo`)

- **Enviar al cliente** (ficha › «🗓️ Plan de trabajo», solo con el plan «listo» o «enviado»): correo «Tu plan de trabajo —
  {proyecto}» con el PDF adjunto (hasta 15 MB; si no se puede, va solo con el enlace y el panel lo dice), enlace a
  `automatizatech.cl/ver-plan.php?id=<código>` y dos botones para agendar. Nunca enlaza al renderer (Hostinger rechaza
  los correos con `*.easypanel.host`). Reply-To y copia oculta: los de «Ajustes del cierre». El plan queda «enviado».
- **Enviar por mi WhatsApp:** abre el WhatsApp de Luis con el mensaje y el enlace del plan, y lo deja «enviado».
- **`ver-plan.php`:** el plan en un iframe, barra con «Agendar mi llamada de seguimiento» (web) y «Agendar por WhatsApp
  con Tech». `&agendar=1` abre el selector de horarios solo. Un plan «listo» se ve, pero la agenda espera al envío.
- **Agenda web:** `POST /wp-json/automatiza-tech/v1/plan-seguimiento` crea una **reunión de seguimiento** (nunca una
  demo ni un lead) con el evento en Google Calendar con Meet, el correo de siempre al cliente y un aviso a Luis. Token
  del plan (válido hoy y ayer), plan «enviado», 5 intentos por IP y por hora, una sola llamada futura por plan.
- Código: `inc/plan-trabajo/envio.php`, `agenda.php`, `vista.php`, `ver-plan.php`, `assets/css/plan-ver.css`,
  `assets/js/plan-ver.js`. Pruebas: `tests/plan/envio-test.php`, `envio-wp-test.php`, `panel-envio-wp-test.php`,
  `agenda-web-wp-test.php`, `ver-plan-wp-test.php`, `etapa2-e2e.js`.
- **Para PROD** (con autorización de Luis), en este orden: `inc/plan-trabajo/puras.php`, `datos.php`, `envio.php`,
  `agenda.php`, `vista.php`, `panel.php`, `assets/css/plan-ver.css`, `assets/js/plan-ver.js`, `cargar.php` (al final de
  los del módulo: carga los nuevos), y `ver-plan.php` en la raíz. Sin migración. Los planes aprobados antes de subir no
  traen «En el sitio web» en el documento: hay que volver a aprobarlos para que lo traigan.
```

En `CLAUDE.md` y `AGENTS.md`, en el puntero existente del plan de trabajo, agregar al final: «Etapa 2 (envío al cliente, `ver-plan.php` y agenda web) implementada en la rama, sin desplegar: ver `Docs/METODO_AT/PLAN-DE-TRABAJO.md`.»

- [ ] **Step 5: Commit**

```bash
cd <W>
git add tests/plan/etapa2-e2e.js Docs/METODO_AT/PLAN-DE-TRABAJO.md CLAUDE.md AGENTS.md
git commit -m "docs(plan): Etapa 2 (envío, ver-plan.php y agenda web) y prueba de punta a punta"
```
