# Estado del proyecto — Etapa 3 (correo de cierre, WhatsApp e historial) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que Luis, desde la fila de un cierre publicado en «📈 Avance del proyecto», pueda mandarse una prueba del correo de cierre de hito, enviarlo al cliente (con el PDF de «Estado del proyecto» si cabe), abrir su WhatsApp con el texto listo, descargar el video del cierre, y que cada paso quede en el historial del CRM con nombre propio en la línea de tiempo.

**Architecture:** Funciones puras nuevas en `inc/plan-trabajo/cierre-puras.php` (resumen del cierre, HTML del correo, texto de WhatsApp, bloqueo D8) y el envío en `inc/plan-trabajo/cierre-correo.php`, sobre lo que ya existe del plan: `at_pt_datos_agenda()`, `at_pt_descargar_pdf()`, `at_cc_correo_avisos()`, `at_cc_cabecera_copia()`, `at_pt_historial()`. El estado de los envíos vive dentro del propio cierre (`envios`) y el envío al cliente se reclama con una escritura condicional sobre la columna `avance` (compare-and-swap) para no mandarlo dos veces. Sin n8n ni renderer: solo WordPress y el mu-plugin de la línea de tiempo.

**Tech Stack:** PHP 8.4 (WordPress, `wp_mail` por SMTP de Hostinger), pruebas PHP propias de `tests/plan/` (sin WordPress y con WordPress local vía `AT_WP_LOAD`) y `tests/cierre/timeline-origen-test.php`.

**Spec:** `Docs/superpowers/specs/2026-10-13-estado-del-proyecto-design.md`, sección 8 (correo, WhatsApp, CRM) y fila 3 de la sección 11. Decisiones D5, D8 y D12.

## Global Constraints

- D5: publicar no envía nada; enviar al cliente es otro botón y siempre un clic de Luis. Nada sale solo.
- D8: todo enlace que llega al cliente va por `automatizatech.cl`; el correo **no se envía** si alguna URL del correo o de las evidencias del cierre contiene `easypanel`, y el panel dice cuál.
- D12: español de Chile, claro, sin jerga, en plural de equipo («terminamos», «te enviamos»); nunca «Luis» como actor ante el cliente.
- Asunto exacto: `Terminamos: {título} — {proyecto}` (prueba: `[Prueba] ` delante).
- PDF adjunto solo si pesa ≤ 15 MB (`AT_PT_PDF_MAX_BYTES`); si no, solo el enlace y el panel lo avisa.
- Reply-To = `at_cc_correo_avisos()`; copia oculta = `at_cc_cabecera_copia($destinatario)`.
- «Enviar al cliente» exige: cierre `publicado`, render público `listo` y generado **después** de publicar ese cierre (`render.at >= cierre.publicado_at`), correo del cliente. Reclamo atómico; reenviar pide confirmación.
- WhatsApp: `Hola {nombre}, terminamos {título} 🎉 {una línea del después}. Te dejamos el detalle{ y el video} aquí: {ver-estado}`.
- Historial (`crm_historial` vía `at_pt_historial`): `hito_cierre_publicado`, `hito_cierre_correo`, `hito_cierre_whatsapp`. Línea de tiempo: «🏁 Cierre de hito publicado / enviado por correo / enviado por WhatsApp», origen 👥 Equipo AutomatizaTech.
- Repo público: datos inventados en pruebas y docs (código `AbCdEf123456`, `example.com`). `functions.php` no se toca.
- Archivos PHP del tema en CRLF; mantener el fin de línea de cada archivo.

## Review Focus

1. **Doble clic en «Enviar al cliente»** (dos pedidos casi juntos): sale un solo correo; el segundo vuelve con «ya se está enviando / ya se envió».
2. **Cierre publicado pero la presentación todavía «Generándose»** o rehecha antes de publicarlo: «Enviar al cliente» se niega con un mensaje claro (el enlace mostraría un estado viejo); la prueba sí sale, sin PDF.
3. **Cliente sin correo o sin teléfono**: el correo dice «la ficha no tiene correo»; el botón de WhatsApp no aparece y se explica por qué; nada se marca como enviado.
4. **Una evidencia de tipo enlace o un video que apunta al servidor interno** (cierre importado antes de la validación, o URL vieja): el correo no sale y el detalle nombra la evidencia.
5. **Volver a borrador y reimportar un cierre ya enviado**: no se pierde que ya se envió (reenviar sigue pidiendo confirmación).

---

## File Structure

| Archivo | Qué hace |
|---|---|
| Create `wp-content/themes/automatiza-tech/inc/plan-trabajo/cierre-puras.php` | Puras: `at_av_cierre_de_avance`, `at_av_resumen_cierre`, `at_av_frase_plan_real`, `at_av_urls_internas`, `at_av_correo_cierre_html`, `at_av_texto_whatsapp_cierre`, `at_av_videos_del_cierre`, `at_av_envios_vacio`. |
| Create `wp-content/themes/automatiza-tech/inc/plan-trabajo/cierre-correo.php` | Con WordPress: `at_av_reclamar_envio`, `at_av_terminar_envio`, `at_av_enviar_cierre`, `at_av_url_whatsapp_cierre`, `at_av_registrar_whatsapp_cierre`. |
| Modify `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-datos.php` | `at_av_guardar_si_igual()` (escritura condicional). |
| Modify `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php` | `at_av_aplicar_import()` conserva `envios` del cierre que reemplaza. |
| Modify `wp-content/themes/automatiza-tech/inc/plan-trabajo/envio.php` | `at_pt_descargar_pdf()` acepta también `/p/<código>-estado/presentation.pdf` y nombre de archivo. |
| Modify `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` | Acciones `at_av_correo` y `at_av_whatsapp`, historial al publicar, botones en la fila de un cierre publicado. |
| Modify `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` | Cargar `cierre-puras.php` (tras `avance-puras.php`) y `cierre-correo.php` (tras `envio.php`). |
| Modify `wp-content/mu-plugins/at-timeline-origen.php` | Tipos `hito_cierre_*` con etiqueta e ícono; origen equipo. |
| Tests | `tests/plan/cierre-puras-test.php` (nuevo, sin WP), `tests/plan/cierre-correo-wp-test.php` (nuevo, WP), `tests/plan/avance-panel-wp-test.php` (ampliar), `tests/cierre/timeline-origen-test.php` (ampliar). |
| Docs | `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md` (sección «Enviar un cierre»), punteros en `CLAUDE.md` y `AGENTS.md` (Etapa 2 mergeada, Etapa 3). |

Datos nuevos dentro de cada cierre (en la columna `avance`, sin migración):

```php
'envios' => ['correo_at' => '', 'correo_enviando' => '', 'correo_a' => '', 'prueba_at' => '', 'whatsapp_at' => '']
```

---

### Task 1: Funciones puras del correo y del WhatsApp

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cierre-puras.php`
- Test: `tests/plan/cierre-puras-test.php`

**Interfaces:**
- Consumes: `at_av_estado_bloques(array $plan, array $avance): array` (filas con `nombre`, `estado`, `plan_desde`, `plan_hasta`, `inicio_real`, `fin_real`), `at_pt_fecha_larga(string $ymd): string` («5 de noviembre de 2026»), `at_av_normalizar()`.
- Produces:
  - `at_av_envios_vacio(): array`
  - `at_av_cierre_de_avance(array $avance, string $id): ?array`
  - `at_av_frase_plan_real(string $plan_hasta, string $fecha_real): string`
  - `at_av_resumen_cierre(array $plan, array $avance, string $id): array` → `['titulo','fecha_real','frase','antes'=>[],'durante'=>[],'despues'=>[],'cifras'=>[['titulo','valor','unidad','fuente','fecha']],'linea_despues','sigue'=>[['nombre','cuando']],'necesitamos'=>[],'videos'=>[['titulo','url']],'urls'=>[]]`
  - `at_av_urls_internas(array $urls): array` (las que contienen `easypanel`, sin repetir)
  - `at_av_correo_cierre_html(array $v): string` con `$v`: `nombre, proyecto, logo, resumen (de at_av_resumen_cierre), mensaje, url_estado, con_pdf (bool)`
  - `at_av_texto_whatsapp_cierre(string $nombre, array $resumen, string $url_estado): string`

- [ ] **Step 1: Escribir la prueba que falla** — `tests/plan/cierre-puras-test.php`:

```php
<?php
// Correr: php tests/plan/cierre-puras-test.php   (sin WordPress)
// Estado del proyecto, Etapa 3 — Task 1: resumen del cierre, correo, WhatsApp y bloqueo D8. Datos inventados.
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/cierre-puras.php';
$fallas = 0;
function ok($c, $m) { global $fallas; if ($c) { echo "ok   $m\n"; } else { $fallas++; echo "FALLA $m\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

$act = fn(string $n, string $d, string $h) => ['nombre' => $n, 'responsable' => 'at', 'dias_habiles' => 2, 'desde' => $d, 'hasta' => $h];
$plan = ['proyecto' => '[PRUEBA] Sitio de la panadería', 'fases' => [
	['clave' => 'diseno_desarrollo', 'bloques' => [
		['nombre' => 'Diseño', 'entregable' => 'Maqueta', 'actividades' => [$act('Maqueta', '2026-10-05', '2026-10-09')]],
		['nombre' => 'Desarrollo', 'entregable' => 'Sitio', 'actividades' => [$act('Construcción', '2026-10-12', '2026-10-21')]],
	]],
	['clave' => 'implementacion', 'bloques' => [['nombre' => 'Puesta en marcha', 'entregable' => 'Publicado', 'actividades' => [$act('Publicación', '2026-10-22', '2026-11-05')]]]],
], 'cronograma' => ['inicio' => '2026-10-05', 'fin' => '2026-11-05'], 'soporte' => ['mensuales' => []]];
$av = at_av_vacio();
$av['cierres'] = [[
	'id' => 'c111111', 'titulo' => 'Sitio publicado', 'bloques' => ['Desarrollo'], 'fecha_real' => '2026-10-10', 'estado' => 'publicado', 'publicado_at' => '2026-10-13 10:00:00', 'entregable_id' => 0,
	'antes' => ['texto' => 'Lento.', 'puntos' => ['Tardaba 17 s', 'Sucursales cerradas', 'Fotos viejas', 'Cuarto punto'], 'evidencias' => ['c111111-lh']],
	'durante' => ['texto' => 'Rehicimos el sitio.', 'actividades' => [['bloque' => 'Desarrollo', 'nombre' => 'Construcción', 'plan_desde' => '2026-10-12', 'plan_hasta' => '2026-10-21', 'real' => '2026-10-10', 'nota' => '']]],
	'despues' => ['texto' => 'Rápido y claro.', 'puntos' => ['Carga en 2,1 s'], 'evidencias' => ['c111111-lh2', 'c111111-video', 'c111111-sitio']],
]];
$av['evidencias'] = [
	'c111111-lh' => ['tipo' => 'cifra', 'titulo' => 'Velocidad', 'valor' => '56', 'unidad' => 'de 100', 'fuente' => 'Lighthouse', 'fecha' => '2026-10-03', 'url' => '', 'poster' => ''],
	'c111111-lh2' => ['tipo' => 'cifra', 'titulo' => 'Velocidad', 'valor' => '98', 'unidad' => 'de 100', 'fuente' => 'Lighthouse', 'fecha' => '2026-10-14', 'url' => '', 'poster' => ''],
	'c111111-video' => ['tipo' => 'video', 'titulo' => 'Recorrido', 'url' => 'https://automatizatech.cl/wp-content/uploads/at-estado/7/aaaaaaaaaaaaaaaaaaaa.mp4', 'poster' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => ''],
	'c111111-sitio' => ['tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'https://example.com/', 'poster' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => ''],
];
$av['necesitamos'] = [['texto' => 'Logo en alta', 'estado' => 'abierto', 'fecha' => '2026-10-01'], ['texto' => 'Dominio', 'estado' => 'resuelto', 'fecha' => '2026-10-01']];

ok(at_av_cierre_de_avance($av, 'c111111')['titulo'] === 'Sitio publicado' && at_av_cierre_de_avance($av, 'cnoexis') === null, 'busca un cierre por id');
ok(at_av_frase_plan_real('2026-10-21', '2026-10-10') === 'Lo teníamos planificado para el 21 de octubre de 2026 y quedó listo el 10 de octubre de 2026 (11 días antes).', 'frase: antes de lo planificado');
ok(at_av_frase_plan_real('2026-10-21', '2026-10-21') === 'Quedó listo el 21 de octubre de 2026, el día planificado.', 'frase: el día planificado');
ok(at_av_frase_plan_real('2026-10-21', '2026-10-23') === 'Lo teníamos planificado para el 21 de octubre de 2026 y quedó listo el 23 de octubre de 2026 (2 días después).', 'frase: después');
ok(at_av_frase_plan_real('', '2026-10-10') === 'Quedó listo el 10 de octubre de 2026.', 'frase: sin fecha planificada');

$r = at_av_resumen_cierre($plan, $av, 'c111111');
ok($r['antes'] === ['Tardaba 17 s', 'Sucursales cerradas', 'Fotos viejas'], 'antes: hasta 3 puntos');
ok($r['durante'] === ['Construcción'] && $r['despues'] === ['Carga en 2,1 s'], 'durante: las actividades; después: sus puntos');
ok(count($r['cifras']) === 2 && $r['cifras'][1]['valor'] === '98' && $r['cifras'][1]['fuente'] === 'Lighthouse', 'cifras del antes y del después con fuente y fecha');
ok($r['linea_despues'] === 'Carga en 2,1 s', 'una línea del después: su primer punto');
ok(str_contains($r['frase'], '21 de octubre') && str_contains($r['frase'], '11 días antes'), 'la frase usa el fin planificado del bloque del cierre');
ok(array_column($r['sigue'], 'nombre') === ['Puesta en marcha'] && $r['necesitamos'] === ['Logo en alta'], 'lo que sigue: bloques no listos; necesitamos: solo lo abierto');
ok($r['videos'] === [['titulo' => 'Recorrido', 'url' => 'https://automatizatech.cl/wp-content/uploads/at-estado/7/aaaaaaaaaaaaaaaaaaaa.mp4']], 'videos del cierre');
ok(in_array('https://example.com/', $r['urls'], true) && in_array($r['videos'][0]['url'], $r['urls'], true), 'urls: enlaces y archivos del cierre');
$sin = $av; $sin['cierres'][0]['despues']['puntos'] = [];
ok(at_av_resumen_cierre($plan, $sin, 'c111111')['linea_despues'] === 'Rápido y claro.', 'sin puntos: la primera frase del texto del después');

ok(at_av_urls_internas(['https://automatizatech.cl/x', 'https://n8n-x.kchiba.easypanel.host/p/a', 'https://N8N-X.KCHIBA.EASYPANEL.HOST/p/a']) === ['https://n8n-x.kchiba.easypanel.host/p/a', 'https://N8N-X.KCHIBA.EASYPANEL.HOST/p/a'], 'D8: detecta el servidor interno sin importar mayúsculas');

$html = at_av_correo_cierre_html(['nombre' => 'Ana', 'proyecto' => '[PRUEBA] Panadería', 'logo' => 'https://automatizatech.cl/logo.png', 'resumen' => $r,
	'mensaje' => "Gracias por tu paciencia.\n<b>hola</b>", 'url_estado' => 'https://automatizatech.cl/ver-estado.php?id=AbCdEf123456', 'con_pdf' => true]);
ok(str_contains($html, 'Terminamos: Sitio publicado') && str_contains($html, 'Hola <strong>Ana</strong>'), 'correo: título y saludo');
ok(str_contains($html, 'Ver el estado del proyecto') && str_contains($html, 'ver-estado.php?id=AbCdEf123456'), 'correo: botón al estado');
ok(str_contains($html, '98 de 100') && str_contains($html, 'Lighthouse') && str_contains($html, '14 de octubre de 2026'), 'correo: cifra con fuente y fecha');
ok(str_contains($html, 'Gracias por tu paciencia.<br>') && str_contains($html, '&lt;b&gt;hola&lt;/b&gt;') && !str_contains($html, '<b>hola</b>'), 'correo: el mensaje de Luis va escapado y con saltos de línea');
ok(str_contains($html, 'Lo que sigue') && str_contains($html, 'Puesta en marcha') && str_contains($html, 'Lo que necesitamos de ti') && str_contains($html, 'Logo en alta'), 'correo: lo que sigue y lo que necesitamos');
ok(str_contains($html, 'adjunto en PDF'), 'correo: avisa el PDF adjunto');
ok(!str_contains($html, 'easypanel') && !str_contains(at_av_correo_cierre_html(['resumen' => $r, 'url_estado' => 'https://x.easypanel.host/p']), 'easypanel.host/p'), 'correo: nunca dibuja un enlace del servidor interno');
ok(!str_contains(at_av_correo_cierre_html(['resumen' => $r, 'url_estado' => 'https://automatizatech.cl/e', 'mensaje' => '']), 'Un mensaje de'), 'correo: sin mensaje no hay recuadro vacío');

$wa = at_av_texto_whatsapp_cierre('Ana', $r, 'https://automatizatech.cl/ver-estado.php?id=AbCdEf123456');
ok($wa === "Hola Ana, terminamos Sitio publicado 🎉 Carga en 2,1 s. Te dejamos el detalle y el video aquí: https://automatizatech.cl/ver-estado.php?id=AbCdEf123456", 'WhatsApp: con video');
$rv = $r; $rv['videos'] = [];
ok(at_av_texto_whatsapp_cierre('', $rv, 'https://automatizatech.cl/e') === 'Hola, terminamos Sitio publicado 🎉 Carga en 2,1 s. Te dejamos el detalle aquí: https://automatizatech.cl/e', 'WhatsApp: sin video y sin nombre');
ok(at_av_envios_vacio() === ['correo_at' => '', 'correo_enviando' => '', 'correo_a' => '', 'prueba_at' => '', 'whatsapp_at' => ''], 'registro de envíos vacío');
fin();
```

- [ ] **Step 2: Correr la prueba y ver que falla**

Run: `/c/wamp64/bin/php/php8.4.15/php.exe tests/plan/cierre-puras-test.php`
Expected: error fatal «Failed opening required … cierre-puras.php».

- [ ] **Step 3: Implementar `cierre-puras.php`** (CRLF):

```php
<?php
/**
 * Estado del proyecto, Etapa 3: lo que dice el correo de cierre de hito y el texto de WhatsApp (spec §8). Funciones
 * puras: no tocan la base ni envían. El envío vive en cierre-correo.php.
 */
if (!defined('ABSPATH') && !defined('AT_PT_PURAS_SIN_WP')) {
	// Se carga también desde las pruebas sin WordPress.
}

/** Registro de envíos de un cierre (vive dentro del cierre, en la columna avance). */
function at_av_envios_vacio(): array {
	return ['correo_at' => '', 'correo_enviando' => '', 'correo_a' => '', 'prueba_at' => '', 'whatsapp_at' => ''];
}

function at_av_cierre_de_avance(array $avance, string $id): ?array {
	foreach ((array) ($avance['cierres'] ?? []) as $c) {
		if (is_array($c) && ($c['id'] ?? '') === $id) {
			return $c;
		}
	}
	return null;
}

/** «Lo teníamos planificado para el X y quedó listo el Y (N días antes).» Sin fecha planificada, solo cuándo quedó. */
function at_av_frase_plan_real(string $plan_hasta, string $fecha_real): string {
	$real = at_pt_fecha_larga($fecha_real);
	$plan = at_pt_fecha_larga($plan_hasta);
	if ($real === '') {
		return '';
	}
	if ($plan === '') {
		return "Quedó listo el {$real}.";
	}
	$dias = (int) round((strtotime($plan_hasta . ' 12:00:00 UTC') - strtotime($fecha_real . ' 12:00:00 UTC')) / 86400);
	if ($dias === 0) {
		return "Quedó listo el {$real}, el día planificado.";
	}
	$n = abs($dias);
	$cuando = $n . ($n === 1 ? ' día ' : ' días ') . ($dias > 0 ? 'antes' : 'después');
	return "Lo teníamos planificado para el {$plan} y quedó listo el {$real} ({$cuando}).";
}

/** Primera frase de un texto (hasta el primer punto, signo o salto de línea), con su punto. */
function at_av_primera_frase(string $t): string {
	$t = trim($t);
	if ($t === '') {
		return '';
	}
	if (preg_match('/^(.+?[.!?])(\s|$)/u', $t, $m)) {
		return trim($m[1]);
	}
	return trim((string) strtok($t, "\n"));
}

/** Todo lo que el correo y el WhatsApp necesitan de un cierre. [] si no existe. */
function at_av_resumen_cierre(array $plan, array $avance, string $id): array {
	$c = at_av_cierre_de_avance($avance, $id);
	if ($c === null) {
		return [];
	}
	$tres = fn($l) => array_slice(array_values(array_filter(array_map('strval', (array) $l), 'strlen')), 0, 3);
	$plan_hasta = '';
	$sigue = [];
	foreach (at_av_estado_bloques($plan, $avance) as $b) {
		if (in_array($b['nombre'], (array) ($c['bloques'] ?? []), true) && (string) $b['plan_hasta'] > $plan_hasta) {
			$plan_hasta = (string) $b['plan_hasta'];
		}
		if ($b['estado'] !== 'listo' && count($sigue) < 3) {
			$sigue[] = ['nombre' => (string) $b['nombre'], 'cuando' => $b['estado'] === 'en_curso' ? 'En curso' : 'Planificado del ' . at_pt_fecha_larga((string) $b['plan_desde']) . ' al ' . at_pt_fecha_larga((string) $b['plan_hasta'])];
		}
	}
	$cifras = [];
	$videos = [];
	$urls = [];
	foreach (array_unique(array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? []))) as $eid) {
		$e = $avance['evidencias'][$eid] ?? null;
		if (!is_array($e)) {
			continue;
		}
		foreach (['url', 'poster'] as $k) {
			if ((string) ($e[$k] ?? '') !== '') {
				$urls[] = (string) $e[$k];
			}
		}
		if (($e['tipo'] ?? '') === 'cifra') {
			$cifras[] = ['titulo' => (string) $e['titulo'], 'valor' => (string) $e['valor'], 'unidad' => (string) $e['unidad'], 'fuente' => (string) $e['fuente'], 'fecha' => (string) $e['fecha']];
		} elseif (($e['tipo'] ?? '') === 'video' && (string) ($e['url'] ?? '') !== '') {
			$videos[] = ['titulo' => (string) $e['titulo'], 'url' => (string) $e['url']];
		}
	}
	$despues = $tres($c['despues']['puntos'] ?? []);
	return [
		'titulo' => (string) $c['titulo'], 'fecha_real' => (string) $c['fecha_real'],
		'frase' => at_av_frase_plan_real($plan_hasta, (string) $c['fecha_real']),
		'antes' => $tres($c['antes']['puntos'] ?? []),
		'durante' => $tres(array_column((array) ($c['durante']['actividades'] ?? []), 'nombre')),
		'despues' => $despues,
		'cifras' => $cifras,
		'linea_despues' => $despues[0] ?? at_av_primera_frase((string) ($c['despues']['texto'] ?? '')),
		'sigue' => $sigue,
		'necesitamos' => array_values(array_map(fn($x) => (string) $x['texto'], array_filter((array) ($avance['necesitamos'] ?? []), fn($x) => is_array($x) && ($x['estado'] ?? '') !== 'resuelto'))),
		'videos' => $videos,
		'urls' => array_values(array_unique($urls)),
	];
}

/** D8: las URL que apuntan al servidor interno (Easypanel); el correo no sale con ninguna. */
function at_av_urls_internas(array $urls): array {
	return array_values(array_unique(array_filter(array_map('strval', $urls), fn($u) => stripos($u, 'easypanel') !== false)));
}

/**
 * Correo «Terminamos: …» (spec §8). $v: nombre, proyecto, logo, resumen (at_av_resumen_cierre), mensaje (texto de Luis,
 * opcional), url_estado (ver-estado.php), con_pdf. Escapa todo; un enlace del servidor interno no se dibuja (D8).
 */
function at_av_correo_cierre_html(array $v): string {
	$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	$url = function ($u): string {
		$u = trim((string) $u);
		return (preg_match('#^https://\S+$#i', $u) && stripos($u, 'easypanel') === false) ? $u : '';
	};
	$r = is_array($v['resumen'] ?? null) ? $v['resumen'] : [];
	$nombre = trim((string) ($v['nombre'] ?? ''));
	$logo = $url($v['logo'] ?? '');
	$estado = $url($v['url_estado'] ?? '');
	$lista = function (string $titulo, array $items) use ($h): string {
		if (!$items) {
			return '';
		}
		return '<h3 style="margin:18px 0 6px;font-size:15px;color:#1e40af">' . $h($titulo) . '</h3><ul style="margin:0;padding-left:20px">'
			. implode('', array_map(fn($x) => '<li>' . $h($x) . '</li>', $items)) . '</ul>';
	};
	$cifras = '';
	foreach ((array) ($r['cifras'] ?? []) as $x) {
		$cifras .= '<li><strong>' . $h($x['titulo']) . ':</strong> ' . $h(trim($x['valor'] . ' ' . $x['unidad']))
			. ' <span style="color:#64748b">(' . $h($x['fuente']) . ', ' . $h(at_pt_fecha_larga((string) $x['fecha'])) . ')</span></li>';
	}
	$mensaje = trim((string) ($v['mensaje'] ?? ''));
	$sigue = array_map(fn($s) => $s['nombre'] . ' — ' . $s['cuando'], (array) ($r['sigue'] ?? []));
	return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f4f6fb;margin:0;padding:0;color:#222">'
		. '<div style="max-width:600px;margin:24px auto;background:#ffffff;border-radius:10px;overflow:hidden">'
		. '<div style="background:#1e40af;color:#ffffff;text-align:center;padding:26px 20px">'
		. ($logo !== '' ? '<img src="' . $h($logo) . '" alt="AutomatizaTech" style="max-height:64px"><br>' : '')
		. '<h1 style="margin:12px 0 0;font-size:22px">Terminamos: ' . $h($r['titulo'] ?? '') . '</h1></div>'
		. '<div style="padding:26px;line-height:1.6">'
		. '<p>Hola <strong>' . $h($nombre !== '' ? $nombre : 'cliente') . '</strong>,</p>'
		. '<p>Terminamos <strong>' . $h($r['titulo'] ?? '') . '</strong>' . (trim((string) ($v['proyecto'] ?? '')) !== '' ? ' de ' . $h($v['proyecto']) : '') . '. ' . $h($r['frase'] ?? '') . '</p>'
		. ($mensaje !== '' ? '<div style="background:#f0f6fc;border-left:3px solid #1e40af;padding:10px 14px;margin:14px 0">' . nl2br($h($mensaje), false) . '</div>' : '')
		. $lista('Cómo estaba', (array) ($r['antes'] ?? []))
		. $lista('Qué hicimos', (array) ($r['durante'] ?? []))
		. $lista('Cómo quedó', (array) ($r['despues'] ?? []))
		. ($cifras !== '' ? '<h3 style="margin:18px 0 6px;font-size:15px;color:#1e40af">En números</h3><ul style="margin:0;padding-left:20px">' . $cifras . '</ul>' : '')
		. (!empty($v['con_pdf']) ? '<p>Va adjunto en PDF el estado completo del proyecto; también puedes verlo en línea.</p>' : '')
		. ($estado !== '' ? '<p style="text-align:center;margin:22px 0"><a href="' . $h($estado) . '" style="display:inline-block;background:#1e40af;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 26px;border-radius:24px">Ver el estado del proyecto</a></p>' : '')
		. $lista('Lo que sigue', $sigue)
		. $lista('Lo que necesitamos de ti', (array) ($r['necesitamos'] ?? []))
		. '<p style="margin-top:22px">Cualquier duda, responde este correo.</p><p>Un abrazo,<br><strong>El equipo de AutomatizaTech</strong></p></div>'
		. '<div style="background:#f1f1f1;color:#777;text-align:center;font-size:12px;padding:14px">© ' . date('Y') . ' AutomatizaTech · automatizatech.cl</div>'
		. '</div></body></html>';
}

/** Texto para el WhatsApp de Luis (spec §8). */
function at_av_texto_whatsapp_cierre(string $nombre, array $resumen, string $url_estado): string {
	$saludo = trim($nombre) !== '' ? 'Hola ' . trim($nombre) : 'Hola';
	$linea = rtrim(trim((string) ($resumen['linea_despues'] ?? '')), '.');
	$video = !empty($resumen['videos']) ? ' y el video' : '';
	return $saludo . ', terminamos ' . trim((string) ($resumen['titulo'] ?? '')) . ' 🎉' . ($linea !== '' ? ' ' . $linea . '.' : '')
		. ' Te dejamos el detalle' . $video . ' aquí: ' . $url_estado;
}
```

- [ ] **Step 4: Correr la prueba** — Run: igual que el paso 2. Expected: `TODO OK`. Si la frase del caso «sin fecha planificada» falla porque `at_av_estado_bloques` da `plan_hasta` para el bloque, la prueba usa la función directa: es correcto.
- [ ] **Step 5: Commit** — `git add wp-content/themes/automatiza-tech/inc/plan-trabajo/cierre-puras.php tests/plan/cierre-puras-test.php && git commit -m "feat(estado): correo de cierre y texto de WhatsApp (funciones puras)"`

---

### Task 2: Escritura condicional del avance y envíos que sobreviven a reimportar

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-datos.php` (después de `at_av_guardar`)
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php` (`at_av_aplicar_import`, donde arma el cierre: línea con `'publicado_at' => '', 'entregable_id' => $c['entregable_id']`)
- Test: `tests/plan/avance-datos-wp-test.php` (ampliar) y `tests/plan/avance-puras-test.php` (ampliar)

**Interfaces:**
- Produces: `at_av_guardar_si_igual(int $plan_id, string $json_antes, array $avance): bool` — guarda solo si la columna sigue valiendo exactamente `$json_antes` (texto leído antes). `at_av_aplicar_import()` copia `envios` del cierre reemplazado.

- [ ] **Step 1: Pruebas que fallan.** En `tests/plan/avance-puras-test.php`, antes de `fin();`:

```php
// Etapa 3: reimportar un borrador que ya se envió no olvida sus envíos.
$base_env = at_av_aplicar_import(at_av_vacio(), at_av_validar_import(archivo(), COD, $plan, at_av_vacio()), 'cenv001', '2026-10-14 10:00:00')['avance'];
$base_env['cierres'][0]['envios'] = ['correo_at' => '2026-10-14 11:00:00', 'correo_enviando' => '', 'correo_a' => 'cliente@example.com', 'prueba_at' => '', 'whatsapp_at' => ''];
$re = at_av_aplicar_import($base_env, at_av_validar_import(archivo(), COD, $plan, $base_env), 'cotro01', '2026-10-15 10:00:00')['avance'];
ok(($re['cierres'][0]['envios']['correo_at'] ?? '') === '2026-10-14 11:00:00', 'reimportar el mismo cierre conserva cuándo se envió');
```

En `tests/plan/avance-datos-wp-test.php`, antes de `fin();` (usa el plan de prueba `$id` que ya crea esa prueba):

```php
$f = at_pt_plan($id);
$antes = (string) $f->avance;
$a = at_av_leer($f);
$a['necesitamos'] = [['texto' => 'Uno', 'estado' => 'abierto', 'fecha' => '2026-10-15']];
ok(at_av_guardar_si_igual($id, $antes, $a) === true, 'guarda si nadie cambió el avance');
ok(at_av_guardar_si_igual($id, $antes, at_av_vacio()) === false && count(at_av_leer(at_pt_plan($id))['necesitamos']) === 1, 'no pisa si el avance cambió entretanto');
```

- [ ] **Step 2: Correr y ver que fallan** — `php tests/plan/avance-puras-test.php` (FALLA en el caso nuevo) y `AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/avance-datos-wp-test.php` (función no definida).
- [ ] **Step 3: Implementar.** En `avance-datos.php`:

```php
/** Guarda el avance solo si la columna sigue valiendo $json_antes (lo leído antes): para reclamar un envío sin que dos
 *  pedidos casi juntos manden dos correos. false si cambió, si no hay migración o si el JSON es muy grande. */
function at_av_guardar_si_igual(int $plan_id, string $json_antes, array $avance): bool {
	if (!at_av_columna_lista()) {
		return false;
	}
	$json = wp_json_encode(at_av_normalizar($avance), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if (!is_string($json) || strlen($json) > 1572864) {
		return false;
	}
	global $wpdb;
	$t = at_pt_tabla();
	$n = $json_antes === ''
		? $wpdb->query($wpdb->prepare("UPDATE {$t} SET avance = %s WHERE id = %d AND (avance IS NULL OR avance = '')", $json, $plan_id))
		: $wpdb->query($wpdb->prepare("UPDATE {$t} SET avance = %s WHERE id = %d AND avance = %s", $json, $plan_id, $json_antes));
	return (int) $n === 1;
}
```

En `at_av_aplicar_import()`, donde se arma el cierre nuevo, agregar la clave `'envios'` tomada del cierre reemplazado (la variable que esa función ya usa para el cierre viejo del mismo título; si no hay, `at_av_envios_vacio()` no existe sin `cierre-puras.php`, así que usar el arreglo literal):

```php
'envios' => is_array($viejo['envios'] ?? null) ? $viejo['envios'] : ['correo_at' => '', 'correo_enviando' => '', 'correo_a' => '', 'prueba_at' => '', 'whatsapp_at' => ''],
```

(Leer la función completa antes de editar: el nombre real de la variable del cierre reemplazado puede ser `$reemplaza` o similar; usar ese.)

- [ ] **Step 4: Correr las dos pruebas** — Expected: `TODO OK` en ambas (y el resto de `tests/plan/avance-*` igual que antes).
- [ ] **Step 5: Commit** — `git commit -m "feat(estado): guardado condicional del avance y envíos que sobreviven a reimportar"`

---

### Task 3: Envío del correo de cierre y WhatsApp (con WordPress)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cierre-correo.php`
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/envio.php:21-44` (`at_pt_descargar_pdf`)
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` (require de `cierre-puras.php` tras `avance-puras.php`; `cierre-correo.php` tras `envio.php`)
- Test: `tests/plan/cierre-correo-wp-test.php`

**Interfaces:**
- Consumes: Task 1 (`at_av_resumen_cierre`, `at_av_correo_cierre_html`, `at_av_texto_whatsapp_cierre`, `at_av_urls_internas`, `at_av_envios_vacio`), Task 2 (`at_av_guardar_si_igual`), `at_pt_datos_agenda()`, `at_pt_telefono_wa()`, `at_pt_url_ver_estado(home_url(), $codigo)`, `at_pt_historial($fila, $tipo, $titulo, $detalle)`, `at_cc_correo_avisos()`, `at_cc_cabecera_copia()`.
- Produces:
  - `at_pt_descargar_pdf(string $url, string $codigo, string $nombre = 'Plan-de-trabajo.pdf')` — acepta `/p/<código>/presentation.pdf` y `/p/<código>-estado/presentation.pdf`.
  - `at_av_enviar_cierre(int $plan_id, string $cierre_id, string $destino, string $mensaje, bool $reenviar): array` → `['ok' => bool, 'motivo' => string, 'detalle' => string, 'sin_pdf' => string]`. `$destino` `'prueba'|'cliente'`. Motivos: `sin_plan`, `sin_cierre`, `no_publicado`, `render_no_listo`, `sin_correo`, `interno`, `ya_enviado`, `enviando`, `correo_fallo`.
  - `at_av_url_whatsapp_cierre(object $fila, string $cierre_id): string` ('' sin teléfono o sin cierre publicado).
  - `at_av_registrar_whatsapp_cierre(int $plan_id, string $cierre_id): array` → `['ok','motivo','url']`.

- [ ] **Step 1: Prueba que falla** — `tests/plan/cierre-correo-wp-test.php` (mismo arranque que `avance-panel-wp-test.php`: `WP_ADMIN`, `AT_AV_PRUEBAS`, `wp-bootstrap.php`, `fixtures-panel.php`, plan de prueba enviado con `ptc_*`; correos capturados con `pre_wp_mail` y descarga del PDF con `pre_http_request`):

```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/cierre-correo-wp-test.php
// Estado del proyecto, Etapa 3 — Task 3: correo de cierre (prueba y cliente), reclamo atómico, D8 y WhatsApp.
if (!defined('WP_ADMIN')) { define('WP_ADMIN', true); }
define('AT_AV_PRUEBAS', true);
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_av_enviar_cierre', 'at_av_registrar_whatsapp_cierre', 'at_av_url_whatsapp_cierre');
at_av_migrar_esquema();
wp_set_current_user(pt_admin_id());
$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-cierre-correo@example.com');
$id = (int) ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-cierre-correo@example.com'))->id;
ptc_sembrar($id);
ptc_estado($id, 'enviado');
$cod = (string) at_pt_plan($id)->codigo;
$correos = [];
add_filter('pre_wp_mail', function ($r, $a) use (&$correos) { $correos[] = $a; return true; }, 10, 2);
$pdf_resp = '%PDF-1.4 prueba';
add_filter('pre_http_request', function ($pre, $args, $url) use (&$pdf_resp) {
	if (str_contains($url, 'presentation.pdf')) {
		return ['headers' => [], 'body' => $pdf_resp, 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
	}
	return $pre;
}, 10, 3);
$archivo = ['formato' => 'at-cierre-v1', 'plan' => $cod, 'cierre' => ['titulo' => 'Diseño terminado', 'bloques' => ['Diseño'], 'fecha_real' => '2026-10-10',
	'antes' => ['texto' => 'Sin maqueta.', 'puntos' => ['Sin maqueta'], 'evidencias' => ['visitas']], 'despues' => ['texto' => 'Maqueta aprobada.', 'puntos' => ['Maqueta aprobada'], 'evidencias' => ['sitio']]],
	'evidencias' => [['ref' => 'visitas', 'tipo' => 'cifra', 'titulo' => 'Visitas', 'valor' => '10', 'fuente' => 'GA4', 'fecha' => '2026-10-09'], ['ref' => 'sitio', 'tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'https://example.com/']]];
$f = at_pt_plan($id);
$v = at_av_validar_import($archivo, $cod, at_pt_payload($f), at_av_leer($f));
$av = at_av_aplicar_import(at_av_leer($f), $v, 'cprueba', '2026-10-14 10:00:00')['avance'];
at_av_guardar($id, $av);

$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', false);
ok(!$r['ok'] && $r['motivo'] === 'no_publicado' && $correos === [], '1) un borrador no se envía');
$av = at_av_cambiar_estado_cierre(at_av_leer(at_pt_plan($id)), 'cprueba', 'publicado', '2026-10-14 10:05:00');
at_av_guardar($id, $av);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', false);
ok(!$r['ok'] && $r['motivo'] === 'render_no_listo', '2) publicado sin presentación lista: al cliente no (Review Focus 2)');
$r = at_av_enviar_cierre($id, 'cprueba', 'prueba', 'Hola equipo', false);
$ultimo = end($correos);
ok($r['ok'] && count($correos) === 1 && str_starts_with($ultimo['subject'], '[Prueba] Terminamos: Diseño terminado') && $ultimo['to'] === at_cc_correo_avisos() && $ultimo['attachments'] === [], '3) la prueba va a Luis, sin PDF si no hay presentación');
ok(str_contains($ultimo['message'], 'Hola equipo') && str_contains($ultimo['message'], 'ver-estado.php?id=' . $cod), '3) la prueba lleva el mensaje y el enlace al estado');

$av = at_av_leer(at_pt_plan($id));
$av['render'] = array_merge(at_av_registro_vacio(), ['estado' => 'listo', 'at' => '2026-10-14 10:06:00', 'view_url' => 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/' . $cod . '-estado/index.html', 'pdf_url' => 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/' . $cod . '-estado/presentation.pdf']);
at_av_guardar($id, $av);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', false);
$ultimo = end($correos);
ok($r['ok'] && $ultimo['to'] === 'prueba-cierre-correo@example.com' && $ultimo['subject'] === 'Terminamos: Diseño terminado — ' . (at_pt_payload(at_pt_plan($id))['proyecto'] ?? '') && count($ultimo['attachments']) === 1, '4) al cliente: asunto exacto y PDF adjunto');
ok(!str_contains($ultimo['message'], 'easypanel') && in_array('Reply-To: ' . at_cc_correo_avisos(), (array) $ultimo['headers'], true), '4) sin enlaces internos y con Reply-To');
$env = at_av_cierre_de_avance(at_av_leer(at_pt_plan($id)), 'cprueba')['envios'];
ok($env['correo_at'] !== '' && $env['correo_enviando'] === '' && $env['correo_a'] === 'prueba-cierre-correo@example.com', '4) queda anotado cuándo y a quién');
$n = count($correos);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', false);
ok(!$r['ok'] && $r['motivo'] === 'ya_enviado' && count($correos) === $n, '5) un segundo clic sin confirmar no reenvía (Review Focus 1)');
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
ok($r['ok'] && count($correos) === $n + 1, '5) reenviar con confirmación sí');

// Reclamo atómico: otro proceso dejó el envío «en curso» hace un momento.
$av = at_av_leer(at_pt_plan($id));
foreach ($av['cierres'] as $k => $x) { if ($x['id'] === 'cprueba') { $av['cierres'][$k]['envios']['correo_enviando'] = current_time('mysql'); } }
at_av_guardar($id, $av);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
ok(!$r['ok'] && $r['motivo'] === 'enviando' && count($correos) === $n + 1, '6) mientras otro envío está en curso no sale otro (Review Focus 1)');
foreach ($av['cierres'] as $k => $x) { if ($x['id'] === 'cprueba') { $av['cierres'][$k]['envios']['correo_enviando'] = ''; } }
at_av_guardar($id, $av);

// D8: una evidencia apunta al servidor interno.
$av = at_av_leer(at_pt_plan($id));
$av['evidencias']['cprueba-sitio']['url'] = 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/x/';
at_av_guardar($id, $av);
$r = at_av_enviar_cierre($id, 'cprueba', 'prueba', '', false);
ok(!$r['ok'] && $r['motivo'] === 'interno' && str_contains($r['detalle'], 'easypanel') && count($correos) === $n + 1, '7) D8: con un enlace interno no sale nada y dice cuál (Review Focus 4)');
$av['evidencias']['cprueba-sitio']['url'] = 'https://example.com/';
at_av_guardar($id, $av);

// PDF muy grande: sale sin adjunto y lo dice.
$pdf_resp = '%PDF' . str_repeat('x', AT_PT_PDF_MAX_BYTES + 10);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
ok($r['ok'] && $r['sin_pdf'] !== '' && end($correos)['attachments'] === [], '8) PDF de más de 15 MB: va solo el enlace y se avisa');

// WhatsApp
$url = at_av_url_whatsapp_cierre(at_pt_plan($id), 'cprueba');
$d = at_pt_datos_agenda($id);
ok(($d['phone'] ?? '') === '' ? $url === '' : (str_starts_with($url, 'https://wa.me/') && str_contains(rawurldecode($url), 'terminamos Diseño terminado')), '9) WhatsApp: wa.me con el texto, o vacío si la ficha no tiene teléfono (Review Focus 3)');
fin();
```

(Si `fixtures-panel.php` crea el cliente sin teléfono, agregar `ptc_telefono($c, '+56 9 1234 5678')` si existe o actualizar `crm_clientes.telefono` del cliente de prueba con `$wpdb->update` antes del caso 9, y cambiar el caso a la rama con teléfono.)

- [ ] **Step 2: Correr y ver que falla** — `AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/cierre-correo-wp-test.php` → «Falta la función at_av_enviar_cierre».
- [ ] **Step 3: Generalizar `at_pt_descargar_pdf`** en `envio.php`: firma `at_pt_descargar_pdf(string $url, string $codigo, string $nombre = 'Plan-de-trabajo.pdf')`, regex `'#^https://' . preg_quote(at_pt_host_renderer(), '#') . '/p/[A-Za-z0-9]{6,32}(-estado)?/presentation\.pdf$#'` y `$archivo = $dir . $nombre;` (validar `$nombre` con `/^[A-Za-z0-9._-]{1,80}\.pdf$/`, si no, el de siempre).
- [ ] **Step 4: Implementar `cierre-correo.php`** (CRLF):

```php
<?php
/**
 * Estado del proyecto, Etapa 3 (spec §8): correo de cierre de hito (prueba a Luis o al cliente) y WhatsApp de Luis.
 * Nada sale sin su clic (D5). Al cliente solo con el cierre publicado y la presentación rehecha después de publicarlo;
 * el envío se reclama con una escritura condicional (at_av_guardar_si_igual) para no mandarlo dos veces.
 */
if (!defined('ABSPATH')) {
	exit;
}

const AT_AV_ENVIO_VENCE = 600; // segundos: un reclamo más viejo se considera caído y se puede volver a enviar

/** Índice del cierre en el avance, o -1. */
function at_av_indice_cierre(array $avance, string $id): int {
	foreach ($avance['cierres'] as $k => $c) {
		if (($c['id'] ?? '') === $id) {
			return (int) $k;
		}
	}
	return -1;
}

/** Cambia el registro de envíos de un cierre sin pisar lo que otro proceso haya escrito: reintenta 3 veces. */
function at_av_anotar_envio(int $plan_id, string $cierre_id, array $campos): bool {
	for ($i = 0; $i < 3; $i++) {
		$f = at_pt_plan($plan_id);
		if (!$f) {
			return false;
		}
		$antes = (string) $f->avance;
		$av = at_av_leer($f);
		$k = at_av_indice_cierre($av, $cierre_id);
		if ($k < 0) {
			return false;
		}
		$av['cierres'][$k]['envios'] = array_merge(at_av_envios_vacio(), (array) ($av['cierres'][$k]['envios'] ?? []), $campos);
		if (at_av_guardar_si_igual($plan_id, $antes, $av)) {
			return true;
		}
	}
	return false;
}

/** Datos comunes del correo y del WhatsApp: [fila, avance, cierre, resumen, datos del cliente, url del estado] o el motivo. */
function at_av_preparar_envio(int $plan_id, string $cierre_id): array|string {
	$f = at_pt_plan($plan_id);
	if (!$f) {
		return 'sin_plan';
	}
	$av = at_av_leer($f);
	$c = at_av_cierre_de_avance($av, $cierre_id);
	if ($c === null) {
		return 'sin_cierre';
	}
	if (($c['estado'] ?? '') !== 'publicado') {
		return 'no_publicado';
	}
	return [$f, $av, $c, at_av_resumen_cierre(at_pt_payload($f), $av, $cierre_id), at_pt_datos_agenda($plan_id), at_pt_url_ver_estado(home_url(), (string) $f->codigo)];
}

function at_av_enviar_cierre(int $plan_id, string $cierre_id, string $destino, string $mensaje, bool $reenviar): array {
	$no = fn(string $m, string $d = '') => ['ok' => false, 'motivo' => $m, 'detalle' => $d, 'sin_pdf' => ''];
	$p = at_av_preparar_envio($plan_id, $cierre_id);
	if (is_string($p)) {
		return $no($p);
	}
	[$f, $av, $c, $r, $d, $url_estado] = $p;
	$prueba = $destino !== 'cliente';
	$internas = at_av_urls_internas(array_merge($r['urls'], [$url_estado]));
	if ($internas) {
		return $no('interno', implode(', ', $internas));
	}
	$para = $prueba ? (function_exists('at_cc_correo_avisos') ? at_cc_correo_avisos() : '') : (string) ($d['client_email'] ?? '');
	if ($para === '' || !is_email($para)) {
		return $no('sin_correo');
	}
	$render = $av['render'];
	$render_listo = $render['estado'] === 'listo' && $render['at'] !== '' && $render['at'] >= (string) ($c['publicado_at'] ?? '');
	if (!$prueba && !$render_listo) {
		return $no('render_no_listo');
	}
	if (!$prueba) {
		// Reclamo atómico (Review Focus 1).
		$f2 = at_pt_plan($plan_id);
		$antes = (string) $f2->avance;
		$av2 = at_av_leer($f2);
		$k = at_av_indice_cierre($av2, $cierre_id);
		$env = array_merge(at_av_envios_vacio(), (array) ($av2['cierres'][$k]['envios'] ?? []));
		if ($env['correo_enviando'] !== '' && strtotime(current_time('mysql')) - strtotime($env['correo_enviando']) < AT_AV_ENVIO_VENCE) {
			return $no('enviando');
		}
		if ($env['correo_at'] !== '' && !$reenviar) {
			return $no('ya_enviado');
		}
		$av2['cierres'][$k]['envios'] = array_merge($env, ['correo_enviando' => current_time('mysql')]);
		if (!at_av_guardar_si_igual($plan_id, $antes, $av2)) {
			return $no('enviando');
		}
	}
	$pdf = $render_listo && $render['pdf_url'] !== '' ? at_pt_descargar_pdf($render['pdf_url'], (string) $f->codigo, 'Estado-del-proyecto.pdf') : new WP_Error('sin_pdf', 'la presentación todavía no está lista');
	$adjuntos = is_wp_error($pdf) ? [] : [$pdf];
	$pl = at_pt_payload($f);
	$proyecto = trim((string) ($pl['proyecto'] ?? '')) !== '' ? trim((string) $pl['proyecto']) : (string) ($d['company_name'] ?? '');
	$html = at_av_correo_cierre_html([
		'nombre' => (string) ($d['client_name'] ?? ''), 'proyecto' => $proyecto,
		'logo' => defined('AT_CC_LOGO') ? AT_CC_LOGO : home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png'),
		'resumen' => $r, 'mensaje' => $mensaje, 'url_estado' => $url_estado, 'con_pdf' => $adjuntos !== [],
	]);
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	$cabeceras = array_merge(
		['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $from . '>'],
		function_exists('at_cc_correo_avisos') ? ['Reply-To: ' . at_cc_correo_avisos()] : [],
		(!$prueba && function_exists('at_cc_cabecera_copia')) ? at_cc_cabecera_copia($para) : []
	);
	$asunto = ($prueba ? '[Prueba] ' : '') . 'Terminamos: ' . $r['titulo'] . ' — ' . ($proyecto !== '' ? $proyecto : 'AutomatizaTech');
	$enviado = (bool) wp_mail($para, $asunto, $html, $cabeceras, $adjuntos);
	foreach ($adjuntos as $a) {
		@unlink($a);
		@rmdir(dirname($a));
	}
	$sin_pdf = is_wp_error($pdf) ? $pdf->get_error_message() : '';
	if ($prueba) {
		if ($enviado) {
			at_av_anotar_envio($plan_id, $cierre_id, ['prueba_at' => current_time('mysql')]);
		}
		return ['ok' => $enviado, 'motivo' => $enviado ? '' : 'correo_fallo', 'detalle' => '', 'sin_pdf' => $sin_pdf];
	}
	if (!$enviado) {
		at_av_anotar_envio($plan_id, $cierre_id, ['correo_enviando' => '']);
		return ['ok' => false, 'motivo' => 'correo_fallo', 'detalle' => '', 'sin_pdf' => $sin_pdf];
	}
	at_av_anotar_envio($plan_id, $cierre_id, ['correo_enviando' => '', 'correo_at' => current_time('mysql'), 'correo_a' => $para]);
	at_pt_historial($f, 'hito_cierre_correo', 'Cierre de hito enviado por correo', 'Enviamos por correo el cierre «' . $r['titulo'] . '»'
		. ($sin_pdf === '' ? ', con el estado del proyecto en PDF.' : ', sin el PDF (' . $sin_pdf . '): el correo lleva el enlace.'));
	return ['ok' => true, 'motivo' => '', 'detalle' => '', 'sin_pdf' => $sin_pdf];
}

function at_av_url_whatsapp_cierre(object $fila, string $cierre_id): string {
	$p = at_av_preparar_envio((int) $fila->id, $cierre_id);
	if (is_string($p)) {
		return '';
	}
	[, , , $r, $d, $url_estado] = $p;
	$tel = at_pt_telefono_wa((string) ($d['phone'] ?? ''));
	return $tel === '' ? '' : 'https://wa.me/' . $tel . '?text=' . rawurlencode(at_av_texto_whatsapp_cierre((string) ($d['client_name'] ?? ''), $r, $url_estado));
}

/** «Enviar por mi WhatsApp»: anota el envío y el historial y devuelve el wa.me. ['ok', 'motivo', 'url']. */
function at_av_registrar_whatsapp_cierre(int $plan_id, string $cierre_id): array {
	$f = at_pt_plan($plan_id);
	if (!$f) {
		return ['ok' => false, 'motivo' => 'sin_plan', 'url' => ''];
	}
	$c = at_av_cierre_de_avance(at_av_leer($f), $cierre_id);
	if ($c === null || ($c['estado'] ?? '') !== 'publicado') {
		return ['ok' => false, 'motivo' => $c === null ? 'sin_cierre' : 'no_publicado', 'url' => ''];
	}
	$url = at_av_url_whatsapp_cierre($f, $cierre_id);
	if ($url === '') {
		return ['ok' => false, 'motivo' => 'sin_telefono', 'url' => ''];
	}
	at_av_anotar_envio($plan_id, $cierre_id, ['whatsapp_at' => current_time('mysql')]);
	at_pt_historial($f, 'hito_cierre_whatsapp', 'Cierre de hito enviado por WhatsApp', 'Abrimos el WhatsApp del equipo con el cierre «' . $c['titulo'] . '» y el enlace al estado del proyecto.');
	return ['ok' => true, 'motivo' => '', 'url' => $url];
}
```

Y en `cargar.php`: `require_once __DIR__ . '/cierre-puras.php';` justo después de `avance-puras.php`, y `require_once __DIR__ . '/cierre-correo.php';` justo después de `envio.php`.

- [ ] **Step 5: Correr** — la prueba nueva y, para no romper el plan, `panel-envio-wp-test.php` y `envio-wp-test.php` (usan `at_pt_descargar_pdf`). Expected: `TODO OK` en las tres.
- [ ] **Step 6: Commit** — `git commit -m "feat(estado): correo de cierre de hito y WhatsApp, con reclamo atómico y bloqueo D8"`

---

### Task 4: Panel: botones del cierre publicado e historial al publicar

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` (mensajes, `add_action`, `at_av_accion_publicar`, fila del cierre publicado en `at_av_render_seccion`)
- Modify: `wp-content/themes/automatiza-tech/assets/css/plan-trabajo.css` (`.at-av-envio`)
- Test: `tests/plan/avance-panel-wp-test.php` (ampliar)

**Interfaces:**
- Consumes: Task 3 (`at_av_enviar_cierre`, `at_av_registrar_whatsapp_cierre`, `at_av_url_whatsapp_cierre`), Task 1 (`at_av_resumen_cierre`, `at_av_texto_whatsapp_cierre`, `at_av_cierre_de_avance`).
- Produces: acciones `admin_post_at_av_correo` (POST `cierre_id`, `destino` prueba|cliente, `mensaje`, `reenviar` 0|1) y `admin_post_at_av_whatsapp` (POST `cierre_id`, abre wa.me en otra pestaña).

- [ ] **Step 1: Pruebas que fallan** — en `avance-panel-wp-test.php`, antes del bloque `// --- Subir sobre un cierre publicado`, con el cierre `$cid` publicado (caso 6 lo publica; ubicar tras el caso 9 cuando `$cid` sigue publicado):

```php
$r = $correr('at_av_correo', ['cierre_id' => $cid, 'destino' => 'prueba', 'mensaje' => 'Hola']);
ok($msg($r) === 'av_correo_prueba', '20) «Enviarme una prueba» desde el panel');
$r = $correr('at_av_correo', ['cierre_id' => $cid, 'destino' => 'cliente']);
ok(in_array($msg($r), ['av_correo_cliente', 'av_correo_render_no_listo', 'av_correo_sin_correo'], true), '20) «Enviar al cliente» responde con un aviso conocido');
$hist = $GLOBALS['wpdb']->get_col($GLOBALS['wpdb']->prepare("SELECT tipo_evento FROM {$GLOBALS['wpdb']->prefix}crm_historial WHERE cliente_id = %d", $crm));
ok(in_array('hito_cierre_publicado', $hist, true), '21) publicar deja «hito_cierre_publicado» en el historial del CRM');
```

y en la parte que dibuja la sección (bloque del caso 14), con el cierre publicado:

```php
ok(str_contains($h, 'value="at_av_correo"') && str_contains($h, 'Enviarme una prueba') && str_contains($h, 'Enviar al cliente'), '22) el cierre publicado muestra el correo de cierre');
ok(substr_count($h, 'value="at_av_correo"') === 1, '22) solo el publicado tiene correo (el borrador no)');
```

- [ ] **Step 2: Correr y ver que fallan** — `AT_WP_LOAD=… php tests/plan/avance-panel-wp-test.php`.
- [ ] **Step 3: Implementar.**
  - Mensajes nuevos en `at_av_mensajes()`:

```php
'av_correo_prueba'            => ['ok', 'Te enviamos la prueba del correo de cierre a tu correo. Al cliente no le llegó nada.'],
'av_correo_cliente'           => ['ok', 'Correo de cierre enviado al cliente. Quedó en el historial del CRM.'],
'av_correo_cliente_sin_pdf'   => ['aviso', 'Correo de cierre enviado al cliente, pero sin el PDF (detalle abajo): lleva el enlace al estado.'],
'av_correo_no_publicado'      => ['error', 'Primero publica el cierre: solo se envía un cierre publicado.'],
'av_correo_render_no_listo'   => ['error', 'Todavía no está lista la presentación con este cierre. Espera el «Lista» (o usa «Rehacer la presentación») y vuelve a enviar.'],
'av_correo_sin_correo'        => ['error', 'No hay a qué correo enviarlo: la ficha del cliente no tiene correo (o falta el correo principal en «Ajustes del cierre», para la prueba).'],
'av_correo_interno'           => ['error', 'No se envió: hay un enlace al servidor interno (detalle abajo). El correo de Hostinger lo rechazaría.'],
'av_correo_ya_enviado'        => ['aviso', 'Este cierre ya se envió al cliente. Para mandarlo otra vez usa «Reenviar» y confírmalo.'],
'av_correo_enviando'          => ['aviso', 'Ese correo ya se está enviando. Recarga en un minuto.'],
'av_correo_correo_fallo'      => ['error', 'El servidor de correo no aceptó el envío. Inténtalo de nuevo en un rato.'],
'av_whatsapp_sin_telefono'    => ['error', 'La ficha del cliente no tiene un teléfono para WhatsApp.'],
```

  - `add_action('admin_post_at_av_correo', 'at_av_accion_correo'); add_action('admin_post_at_av_whatsapp', 'at_av_accion_whatsapp');`
  - Acciones:

```php
function at_av_accion_correo(): void {
	[$fila, $crm] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$destino = ($_POST['destino'] ?? '') === 'cliente' ? 'cliente' : 'prueba';
	$mensaje = mb_substr(sanitize_textarea_field(wp_unslash($_POST['mensaje'] ?? '')), 0, 1500);
	$r = at_av_enviar_cierre($id, at_av_cierre_post(), $destino, $mensaje, ($_POST['reenviar'] ?? '') === '1');
	if (!$r['ok']) {
		if ($r['detalle'] !== '') {
			at_pt_guardar_detalles($id, ['Enlace interno: ' . $r['detalle']]);
		}
		at_pt_volver($crm, $id, 'av_correo_' . $r['motivo']);
	}
	if ($r['sin_pdf'] !== '' && $destino === 'cliente') {
		at_pt_guardar_detalles($id, ['Motivo: ' . $r['sin_pdf'] . '.']);
		at_pt_volver($crm, $id, 'av_correo_cliente_sin_pdf');
	}
	at_pt_volver($crm, $id, $destino === 'cliente' ? 'av_correo_cliente' : 'av_correo_prueba');
}

function at_av_accion_whatsapp(): void {
	[$fila, $crm] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$r = at_av_registrar_whatsapp_cierre($id, at_av_cierre_post());
	if (!$r['ok']) {
		at_pt_volver($crm, $id, $r['motivo'] === 'sin_telefono' ? 'av_whatsapp_sin_telefono' : 'av_correo_' . $r['motivo']);
	}
	wp_redirect($r['url']); // wa.me no es del sitio: wp_safe_redirect lo cambiaría por el escritorio
	exit;
}
```

  (Motivos sin mensaje propio — `sin_plan`, `sin_cierre` — caen en el aviso genérico de `at_pt_volver`; agregar `'av_correo_sin_cierre' => ['error', 'Ese cierre no existe.']` y `'av_correo_sin_plan' => ['error', 'Ese plan no existe.']`.)
  - En `at_av_accion_publicar()`, después de `at_av_guardar_o_volver($id, $crm, $r);`:

```php
$pub = at_av_cierre_de_avance($r, at_av_cierre_post());
at_pt_historial($fila, 'hito_cierre_publicado', 'Cierre de hito publicado', 'Publicamos el cierre «' . ($pub['titulo'] ?? '') . '» en el estado del proyecto. No se le envió nada al cliente.');
```

  - En `at_av_render_seccion`, dentro del `<details>` de la fila, cuando `$pub`: un bloque «📣 Avisar al cliente» con:
    - Estado de envíos: «Correo al cliente: enviado el {correo_at} a {correo_a}» / «aún no»; «Prueba: {prueba_at}»; «WhatsApp: abierto el {whatsapp_at}».
    - Un formulario `at_av_correo` (`at_av_ocultos('at_av_correo', $id, $crm_id, ['cierre_id' => $cid])`) con `<textarea name="mensaje" rows="3" maxlength="1500" placeholder="Mensaje opcional para el cliente (va arriba del resumen)">`, botón `name="destino" value="prueba"` «📧 Enviarme una prueba» y botón `name="destino" value="cliente"` «📨 Enviar al cliente» (si ya se envió: texto «📨 Reenviar al cliente», `<input type="hidden" name="reenviar" value="1">` y `onclick="return confirm('Este cierre ya se envió el …. ¿Enviarlo otra vez al cliente?')"`; si no: `confirm('Se enviará el correo de cierre a {correo del cliente}. ¿Enviar?')`).
    - WhatsApp: si `at_av_url_whatsapp_cierre($fila, $cid) !== ''`, formulario `at_av_whatsapp` con `target="_blank"` y botón «💬 Enviar por mi WhatsApp», más el texto en un `<textarea readonly rows="3">` (para copiar); si no, «La ficha no tiene teléfono para WhatsApp.»
    - Videos: por cada `at_av_resumen_cierre(...)['videos']`, `<a class="button" href="{url}" download>⬇️ Descargar el video «{título}»</a>`.
  - CSS en `plan-trabajo.css`: `.at-av-envio { margin: 10px 0; padding: 10px 14px; background: #f0f6fc; border-left: 3px solid #2271b1; } .at-av-envio textarea { width: 100%; max-width: 640px; }`.

- [ ] **Step 4: Correr** `avance-panel-wp-test.php`, `avance-formulario-wp-test.php` y `cierre-correo-wp-test.php`. Expected: `TODO OK`.
- [ ] **Step 5: Commit** — `git commit -m "feat(estado): avisar al cliente desde el cierre publicado (correo, WhatsApp y video)"`

---

### Task 5: Nombres en la línea de tiempo del CRM

**Files:**
- Modify: `wp-content/mu-plugins/at-timeline-origen.php`
- Test: `tests/cierre/timeline-origen-test.php` (ampliar)

**Interfaces:**
- Produces: claves `hito_cierre_publicado`, `hito_cierre_correo`, `hito_cierre_whatsapp` en `at_tl_mapa_historial()` (mapean a sí mismas) y en `at_tl_tipos_extra()`; los tres en `$humanos` de `at_tl_origen` (origen equipo aunque no haya sesión).

- [ ] **Step 1: Prueba que falla** — en `timeline-origen-test.php`, antes del final:

```php
foreach (['hito_cierre_publicado' => '🏁 Cierre de hito publicado', 'hito_cierre_correo' => '🏁 Cierre de hito enviado por correo', 'hito_cierre_whatsapp' => '🏁 Cierre de hito enviado por WhatsApp'] as $t => $etiqueta) {
	$x = at_tl_tipos_extra()[at_tl_mapa_historial()[$t] ?? ''] ?? null;
	ok(is_array($x) && $x['icon'] . ' ' . $x['label'] === $etiqueta, "$t: «$etiqueta»");
	ok(at_tl_origen(hist($t, 'x', 1)) === 'equipo' && at_tl_origen(hist($t, 'x', 0)) === 'equipo', "$t: lo hizo el Equipo AutomatizaTech");
}
```

- [ ] **Step 2: Correr y ver que falla** — `php tests/cierre/timeline-origen-test.php`.
- [ ] **Step 3: Implementar** — en `at_tl_mapa_historial()` agregar `'hito_cierre_publicado' => 'hito_cierre_publicado', 'hito_cierre_correo' => 'hito_cierre_correo', 'hito_cierre_whatsapp' => 'hito_cierre_whatsapp',`; en `at_tl_tipos_extra()`:

```php
'hito_cierre_publicado' => ['label' => 'Cierre de hito publicado', 'icon' => '🏁', 'color' => '#0f766e', 'bg' => '#ccfbf1'],
'hito_cierre_correo'    => ['label' => 'Cierre de hito enviado por correo', 'icon' => '🏁', 'color' => '#0f766e', 'bg' => '#ccfbf1'],
'hito_cierre_whatsapp'  => ['label' => 'Cierre de hito enviado por WhatsApp', 'icon' => '🏁', 'color' => '#0f766e', 'bg' => '#ccfbf1'],
```

y sumar los tres a `$humanos`.
- [ ] **Step 4: Correr** — Expected `TODO OK`.
- [ ] **Step 5: Commit** — `git commit -m "feat(crm): cierres de hito con nombre propio en la línea de tiempo"`

---

### Task 6: Guía, punteros y lista de despliegue

**Files:**
- Modify: `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md` (encabezado: Etapa 2 mergeada en el PR #89; nueva sección «Avisar al cliente de un cierre» en §2; §6 lista de despliegue de la Etapa 3; §7 lo no probado)
- Modify: `CLAUDE.md` y `AGENTS.md` (puntero del plan de trabajo: PR #89 mergeado 15-oct; Etapa 3 en `claude/estado-proyecto-e3`)

- [ ] **Step 1:** Escribir en la guía, §2, la sección con: qué hace cada botón (prueba a Luis sin PDF si no hay presentación; cliente solo publicado + «Lista» posterior; reenviar con confirmación; WhatsApp abre el de Luis y anota; descargar el video), que todo queda en el historial con 🏁 y que nada sale solo.
- [ ] **Step 2:** §6, despliegue (sin migración, sin n8n ni renderer), en este orden:

| Orden | Local | Destino | Clase |
|---|---|---|---|
| 1 | `inc/plan-trabajo/cierre-puras.php`, `cierre-correo.php` (nuevos) | mismas rutas | OBLIGATORIO, antes que `cargar.php` |
| 2 | `inc/plan-trabajo/avance-datos.php`, `avance-puras.php`, `envio.php`, `avance-panel.php`, `assets/css/plan-trabajo.css` | mismas rutas | OBLIGATORIO (respaldar y cotejar md5 contra `main`) |
| 3 | `inc/plan-trabajo/cargar.php` | misma ruta | OBLIGATORIO, al final del tema |
| 4 | `wp-content/mu-plugins/at-timeline-origen.php` | misma ruta | OBLIGATORIO |
| — | `tests/`, `Docs/` | — | NO subir |

- [ ] **Step 3:** §7: no probado — un correo real por el SMTP de Hostinger con PDF adjunto, el wa.me en el celular de Luis, la descarga del video en iPhone.
- [ ] **Step 4:** `graphify update .` y commit: `git commit -m "docs(estado): Etapa 3 en la guía y punteros"`.

---

## Self-Review

1. **Cobertura del spec §8:** asunto (T3 caso 4) · plan vs real (T1) · antes/durante/después ≤ 3 (T1) · cifras con fuente y fecha (T1) · mensaje opcional (T1, T4) · botón al estado (T1) · lo que sigue y necesitamos (T1) · PDF ≤ 15 MB o solo enlace con aviso (T3 caso 8, T4 mensaje) · Reply-To y copia oculta (T3) · prueba a Luis (T3 caso 3) · cliente exige publicado + render listo (T3 casos 1–2) · reclamo atómico (T2, T3 casos 5–6) · reenviar con confirmación (T3 caso 5, T4) · D8 con el nombre de la URL (T3 caso 7, T4) · WhatsApp texto y botón (T1, T3 caso 9, T4) · descargar el video (T4) · historial de los tres eventos (T3, T4 caso 21) · línea de tiempo con nombres y origen equipo (T5).
2. **Sin marcadores pendientes:** cada paso trae el código o el texto exacto; los dos puntos que dependen de leer el archivo (nombre de la variable del cierre reemplazado en `at_av_aplicar_import`, y si `fixtures-panel.php` tiene helper de teléfono) dicen qué leer y qué hacer en cada caso.
3. **Tipos:** `at_av_enviar_cierre` devuelve siempre `ok, motivo, detalle, sin_pdf`; los mensajes del panel son `av_correo_<motivo>` para todos los motivos de T3.
4. **Review Focus:** 1 → T3 casos 5–6; 2 → T3 caso 2; 3 → T3 caso 9 + mensaje `av_correo_sin_correo` / `av_whatsapp_sin_telefono`; 4 → T3 caso 7; 5 → T2 prueba de reimportar.
