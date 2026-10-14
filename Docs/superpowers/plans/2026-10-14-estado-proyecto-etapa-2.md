# Estado del proyecto — Etapa 2 (avance, importar cierre, evidencias y presentación «Estado del proyecto») Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Luis carga el avance de un plan ya enviado (bloques, cierres de hito importados desde un archivo `at-cierre-v1`, sus evidencias, cambios de plan, «necesitamos de ti» y alcance), publica un cierre y el cliente ve en `ver-estado.php` la presentación viva «Estado del proyecto», con PDF, hecha por el mismo renderer. Nada se le envía al cliente (el correo y el WhatsApp de cierre son la Etapa 3).

**Architecture:** Cuatro piezas, cada una con un archivo propio. WordPress: módulo nuevo `inc/plan-trabajo/avance-*.php` (puras → datos → evidencias → render → panel) sobre una columna nueva `avance` (JSON) de `wp_automatiza_planes_trabajo`; las rutas REST del plan aceptan `modo=estado`. Renderer: `document_type: 'estado'` con `validateEstadoPayload()` y `template-estado.js` (8 tipos de lámina, mismos estilos base del plan). n8n: «Plan de trabajo · 3 Render» gana una rama `modo: 'estado'` que reutiliza las fotos ya guardadas del plan y nunca paga fotos. `ver-estado.php` muestra la presentación cuando hay al menos un cierre publicado y un render listo.

**Tech Stack:** PHP 8.4 + WordPress local de prueba (tema); Node 24 + `node:test` + Playwright 1.62.1 (renderer); Python 3 que genera el JSON del flujo n8n y lo simula con `node`.

**Spec:** `Docs/superpowers/specs/2026-10-13-estado-del-proyecto-design.md` (secciones 2, 3, 4, 5, 7, 9, 10 y 11, etapa 2). Etapa 1 ya en PROD: `Docs/superpowers/plans/2026-10-13-estado-proyecto-etapa-1.md`.

## Global Constraints

- El `payload` del plan firmado **no se modifica nunca** (D3). Todo el avance vive en la columna `avance`.
- Un cierre es `borrador` o `publicado`. El cliente solo ve publicados; publicar **no envía nada** (D5). Nada de correo ni WhatsApp al cliente en esta etapa.
- **Ninguna cifra sin fuente y fecha** (D6): la importación rechaza una evidencia `cifra` sin `valor`, `fuente` o `fecha`.
- Evidencias en `wp-content/uploads/at-estado/<id del plan>/`, nombres aleatorios, carpeta con `.htaccess` que impide ejecutar código y agrega `noindex` y `no-transform`; imágenes reescritas con GD (sin EXIF ni GPS) (D7).
- Todo enlace que llega al cliente va por `https://automatizatech.cl/...`; un `enlace` a `*.easypanel.host` o que no sea `https` se rechaza (D8).
- Sin IA en esta etapa (D9): ni fotos nuevas ni textos sugeridos. El render de estado envía `image_briefs: []` y el renderer los ignora con `document_type: 'estado'`.
- `unique_id` de la presentación pública: `<código>-estado`; de la vista previa con borradores: `<código>-previa-<token de 10>` (D11).
- Topes (spec §3): 30 cierres, 60 evidencias, 30 cambios de plan, 30 ítems de «necesitamos», textos de 2.000 caracteres, puntos de 200, imagen 15 MB, video 200 MB.
- Texto en español de Chile, claro, en plural de equipo («terminamos», «te mostramos») (D12).
- El HTML de las propuestas y el del plan no cambian ni un byte: `renderer/test/template-propuesta-igual.test.js` y las pruebas del plan siguen pasando sin tocar sus valores.
- `functions.php` no se toca. Rama `claude/estado-proyecto-e2`; sin merge ni despliegue sin el OK de Luis.
- Repo público: nada de vulnerabilidades, secretos ni códigos reales de clientes en commits. Código de plan inventado para ejemplos: `AbCdEf123456`.
- Archivos del repo en CRLF: editar con la herramienta Edit, no con `sed` de patrones.

## Review Focus

1. **Corregir el texto de un cierre ya importado** (volver a importar el mismo título en borrador): las evidencias con la misma `ref` y el mismo `archivo` conservan el archivo ya subido; Luis no vuelve a subir un video de 13 MB por cambiar una coma. → prueba en Task 1 (`at_av_aplicar_import`).
2. **Un borrador nunca llega al cliente:** el cuerpo de render sin `previa` no trae borradores, y `ver-estado.php` muestra «Pronto…» aunque haya un render listo si ningún cierre está publicado. → pruebas en Task 5 y Task 8.
3. **Un archivo disfrazado** (código PHP o texto con nombre `.jpg`, un SVG, un «.mp4» que es texto) se rechaza por su contenido, y una foto con EXIF/GPS queda sin ellos. → prueba en Task 3.
4. **Un archivo de cierre con errores** (cifra sin fuente o fecha, enlace http o a easypanel, bloque que no existe, plan equivocado) se rechaza entero con motivos en lenguaje simple y el avance guardado no cambia. → pruebas en Task 1 y Task 7.
5. **n8n o el renderer caídos al publicar:** el cierre queda publicado, el registro del render queda en `error` con el motivo, el panel ofrece «Reintentar» y `ver-estado.php` sigue mostrando la última presentación lista (o «Pronto…»), nunca una página rota. → pruebas en Task 5, Task 7 y Task 8.

## Rulings (decisiones tomadas al planificar; cada una dice qué cuesta si es errada)

- **R1. Póster del video:** la spec dice «primer cuadro, generado al subir», pero en Hostinger `exec`/`system` están desactivados en PHP (no hay ffmpeg). El póster es una imagen opcional que viene con el video (`"poster": "archivo.jpg"` en la evidencia). Sin póster, el PDF muestra una tarjeta «▶ Ver el video» con el enlace. Si es errado: se agrega un póster generado en el renderer (Chromium) más adelante.
- **R2. «Editar» un cierre:** el panel muestra el cierre como archivo `at-cierre-v1` en un cuadro de texto y se guarda con la misma validación de «Importar cierre» (un solo camino de validación). No hay un formulario campo por campo. Si es errado: se agrega un editor de formularios sin cambiar los datos.
- **R3. Vista previa con borradores:** se renderiza en una ruta con token (`<código>-previa-<token>`) que solo se muestra en el panel, nunca en `ver-estado.php`. Si es errado: la previa se quita y Luis revisa con «publicar → mirar → volver a borrador».
- **R4. «Estado del proyecto» se rehace** solo si hay al menos un cierre publicado (sin cierres publicados el cliente ve «Pronto…» y no se gasta un render).
- **R5. Rama de n8n:** el modo `estado` entra al mismo flujo «3 Render» (D1) por un nodo `¿Estado del proyecto?` justo después del Webhook; sus nodos son propios (no tocan el camino del plan) y nunca mandan correo a Luis (el panel muestra el resultado).

---

### Task 0: Preparar el sitio local de prueba y la línea base

**Files:** ninguno del repo.

- [ ] **Step 1: Apuntar las uniones del WordPress local a este worktree**

El sitio de prueba vive en `C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/wp-local-plan` (en adelante `$SCR/wp-local-plan`). Sus carpetas `wp-content/mu-plugins` y `wp-content/themes/automatiza-tech` son uniones (junctions). Rehacerlas hacia `C:\wamp64\www\automatiza-tech\.worktrees\estado-e2`:

```powershell
$S = "C:\Users\luis_\AppData\Local\Temp\claude\C--wamp64-www-automatiza-tech\be929449-8523-4c30-a49a-56a73bb785ab\scratchpad\wp-local-plan"
$W = "C:\wamp64\www\automatiza-tech\.worktrees\estado-e2"
cmd /c rmdir "$S\wp-content\mu-plugins"
cmd /c mklink /J "$S\wp-content\mu-plugins" "$W\wp-content\mu-plugins"
cmd /c rmdir "$S\wp-content\themes\automatiza-tech"
cmd /c mklink /J "$S\wp-content\themes\automatiza-tech" "$W\wp-content\themes\automatiza-tech"
if (-not (Test-Path "$S\wp-includes")) { cmd /c mklink /J "$S\wp-includes" "C:\wamp64\www\automatiza-tech\wp-includes" }
```

(`rmdir` sobre una unión borra solo la unión, nunca su contenido.)

- [ ] **Step 2: Instalar las dependencias del renderer en este worktree**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/estado-e2/renderer && npm install
```

Expected: termina sin errores (`package-lock.json` está ignorado por git).

- [ ] **Step 3: Verificar que las suites de base pasan antes de tocar nada**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/estado-e2
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad
PHP=/c/wamp64/bin/php/php8.4.15/php.exe
(cd renderer && npm test 2>&1 | tail -4)
python N8N/plan-trabajo/probar_plan.py 2>&1 | tail -2
$PHP tests/plan/render-test.php | tail -1
for t in rest-render ver-plan ver-estado panel-acciones panel-envio disparador datos datos-guardar; do echo "== $t"; AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php $PHP tests/plan/$t-wp-test.php | tail -1; done
```

Expected: todo «pass» / «TODO OK». Fallas previas conocidas en `main`, ajenas a esta rama (no las arregles; anótalas): `panel-render-wp-test` (1), `datos-contexto-wp-test` (1), `firma-contrato-wp-test` (sin salida). Si una prueba no imprime nada, mira `/c/wamp64/logs/php_error.log`.

---

### Task 1: Avance — forma, validación de «Importar cierre» y estado de los bloques (funciones puras)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php`
- Create: `tests/plan/avance-puras-test.php`

**Interfaces:**
- Consumes: nada de otras tareas (archivo puro, sin WordPress ni base).
- Produces (los usan las Tasks 2, 3, 5, 7 y 8):
  - Constantes `AT_AV_MAX_CIERRES=30`, `AT_AV_MAX_EVIDENCIAS=60`, `AT_AV_MAX_CAMBIOS=30`, `AT_AV_MAX_NECESITAMOS=30`, `AT_AV_MAX_TEXTO=2000`, `AT_AV_MAX_PUNTO=200`, `AT_AV_MAX_PUNTOS=8`, `AT_AV_MAX_ALCANCE=20`, `AT_AV_MAX_ACTIVIDADES=30`, `AT_AV_ESTADOS_BLOQUE=['listo','en_curso','por_venir']`, `AT_AV_TIPOS=['captura','enlace','cifra','video']`.
  - `at_av_registro_vacio(): array` → `['estado'=>'','view_url'=>'','pdf_url'=>'','nota'=>'','at'=>'']`.
  - `at_av_vacio(): array`; `at_av_normalizar(mixed $guardado): array` (acepta JSON en texto o arreglo).
  - `at_av_limpiar(mixed $v, bool $multilinea=false): string`; `at_av_texto(mixed $v, int $max, string $que, array &$errores, bool $multilinea=false): string`; `at_av_fecha_valida(mixed $f): bool`; `at_av_url_publica(mixed $u): string`; `at_av_clave(string $s): string`.
  - `at_av_bloques_plan(array $plan): array` → lista de `['nombre','fase','plan_desde','plan_hasta','entregable']`.
  - `at_av_bloque_del_plan(array $bloques_plan, mixed $nombre): string`; `at_av_actividades_plan(array $plan, array $nombres): array`.
  - `at_av_validar_import(mixed $d, string $codigo, array $plan, array $avance): array` → `['ok','errores','avisos','cierre','evidencias','bloques','cambios_plan','necesitamos','alcance']` (los tres últimos `null` si el archivo no los trae).
  - `at_av_aplicar_import(array $avance, array $v, string $nuevo_id, string $ahora): array` → `['avance', 'cierre_id', 'archivos']` (`archivos` = nombres de archivo que ya nadie usa).
  - `at_av_faltan_archivos(array $avance, string $cierre_id): array` → `[id evidencia => título]`.
  - `at_av_cambiar_estado_cierre(array $avance, string $id, string $estado, string $ahora): array|string` (avance nuevo o motivo).
  - `at_av_quitar_cierre(array $avance, string $id): array|string` → `['avance','archivos']` o motivo.
  - `at_av_publicados(array $avance): array`; `at_av_estado_bloques(array $plan, array $avance): array`.
  - `at_av_exportar_cierre(array $avance, string $id, string $codigo): array` (el cierre como archivo `at-cierre-v1`, para «Editar»).
  - Panel: `at_av_cambios_de_texto(string $t, array $bloques_plan, array &$errores): array`, `at_av_texto_de_cambios(array $l): string`, `at_av_necesitamos_de_texto(string $t, array $anteriores, string $hoy, array &$errores): array`, `at_av_texto_de_necesitamos(array $l): string`, `at_av_lista_de_texto(string $t, string $que, array &$errores): array`, `at_av_bloques_de_post(mixed $post, array $plan, array &$errores): array`.

- [ ] **Step 1: Escribir la prueba que falla**

Crear `tests/plan/avance-puras-test.php`:

```php
<?php
// Correr: php tests/plan/avance-puras-test.php   (sin WordPress)
// Estado del proyecto, Etapa 2 — Task 1: forma del avance, importar cierre (at-cierre-v1), estado de los bloques y
// textos del panel. Datos inventados: el repositorio es público.
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

const COD = 'AbCdEf123456';

/** Plan firmado mínimo con fechas ya calculadas: dos fases, tres bloques. */
function plan_prueba(): array {
	$act = fn(string $n, string $d, string $h) => ['nombre' => $n, 'responsable' => 'at', 'dias_habiles' => 2, 'desde' => $d, 'hasta' => $h];
	return [
		'proyecto' => '[PRUEBA] Sitio de la panadería',
		'fases' => [
			['clave' => 'diseno_desarrollo', 'bloques' => [
				['nombre' => 'Diseño', 'entregable' => 'Maqueta aprobada', 'actividades' => [$act('Maqueta de la portada', '2026-10-05', '2026-10-06'), $act('Páginas internas', '2026-10-07', '2026-10-09')]],
				['nombre' => 'Desarrollo', 'entregable' => 'Sitio en pruebas', 'actividades' => [$act('Construcción del sitio', '2026-10-12', '2026-10-21')]],
			]],
			['clave' => 'implementacion', 'bloques' => [
				['nombre' => 'Puesta en marcha', 'entregable' => 'Sitio publicado', 'actividades' => [$act('Publicación', '2026-10-22', '2026-10-23')]],
			]],
		],
		'cronograma' => ['inicio' => '2026-10-05', 'fin' => '2026-10-23', 'semanas' => 3, 'barras' => [], 'hitos' => []],
		'soporte' => ['garantia_meses' => 3, 'mensuales' => ['Mantención mensual del sitio']],
	];
}

/** Archivo at-cierre-v1 válido; $cambios reemplaza claves de primer nivel o de «cierre» (con 'cierre.x'). */
function archivo(array $cambios = []): array {
	$d = [
		'formato' => 'at-cierre-v1', 'plan' => COD,
		'cierre' => [
			'titulo' => 'Sitio web', 'bloques' => ['diseño', 'Desarrollo '], 'fecha_real' => '2026-10-10', 'entregable_id' => 0,
			'antes' => ['texto' => 'El sitio usaba una plantilla genérica.', 'puntos' => ['Plantilla genérica', 'Páginas de demostración indexadas'], 'evidencias' => ['lh-antes']],
			'durante' => ['texto' => 'Rediseñamos y publicamos.', 'actividades' => [
				['bloque' => 'Diseño', 'nombre' => 'Maqueta de la portada', 'real' => '2026-10-03', 'nota' => ''],
				['bloque' => 'Desarrollo', 'nombre' => 'Construcción del sitio', 'real' => '2026-10-10', 'nota' => 'Adelantado'],
			]],
			'despues' => ['texto' => 'Sitio nuevo publicado.', 'puntos' => ['Publicado el 10-oct'], 'evidencias' => ['lh-despues', 'portada', 'reel', 'sitio']],
		],
		'evidencias' => [
			['ref' => 'lh-antes', 'tipo' => 'cifra', 'titulo' => 'Lighthouse celular', 'valor' => '56', 'unidad' => 'puntos', 'fuente' => 'PageSpeed Insights', 'fecha' => '2026-10-06'],
			['ref' => 'lh-despues', 'tipo' => 'cifra', 'titulo' => 'Lighthouse celular', 'valor' => '98', 'unidad' => 'puntos', 'fuente' => 'medición local', 'fecha' => '2026-10-13'],
			['ref' => 'portada', 'tipo' => 'captura', 'titulo' => 'Portada nueva', 'archivo' => 'portada-nueva.png'],
			['ref' => 'reel', 'tipo' => 'video', 'titulo' => 'Recorrido del sitio', 'archivo' => 'reel-sitio.mp4', 'poster' => 'reel-poster.jpg'],
			['ref' => 'sitio', 'tipo' => 'enlace', 'titulo' => 'El sitio publicado', 'url' => 'https://example.com/'],
		],
		'bloques' => ['Puesta en marcha' => ['estado' => 'en_curso', 'inicio_real' => '2026-10-13', 'fin_real' => '']],
		'cambios_plan' => [['fecha' => '2026-10-13', 'titulo' => 'Publicidad pasa al servicio mensual', 'detalle' => 'Acordado con el cliente.', 'bloques' => ['Puesta en marcha']]],
		'necesitamos' => [['texto' => 'Fecha de inicio del servicio mensual', 'estado' => 'abierto', 'fecha' => '2026-10-13']],
		'alcance' => ['fase1' => ['Sitio web publicado'], 'mensual' => ['Publicidad y visibilidad']],
	];
	foreach ($cambios as $k => $v) {
		if (str_starts_with($k, 'cierre.')) {
			$d['cierre'][substr($k, 7)] = $v;
		} elseif ($v === null) {
			unset($d[$k]);
		} else {
			$d[$k] = $v;
		}
	}
	return $d;
}

$plan = plan_prueba();
$vacio = at_av_vacio();

// --- forma
ok(at_av_normalizar(null) === $vacio && at_av_normalizar('') === $vacio && at_av_normalizar('{roto') === $vacio, 'avance vacío, sin guardar o ilegible: forma vacía completa');
$n = at_av_normalizar('{"cierres":[{"id":"c1"},"basura"],"alcance":{"fase1":["A",3]},"render":{"estado":"listo","otra":1}}');
ok(count($n['cierres']) === 1 && $n['alcance']['fase1'] === ['A'] && $n['render']['estado'] === 'listo' && !isset($n['render']['otra']) && $n['render_previa'] === at_av_registro_vacio(), 'normalizar descarta lo que no tiene la forma esperada');

// --- ayudas
ok(at_av_url_publica('https://example.com/a?b=1') === 'https://example.com/a?b=1', 'enlace https: sirve');
ok(at_av_url_publica('http://example.com/') === '' && at_av_url_publica('https://n8n-propuesta-renderer.kchiba.easypanel.host/p/x') === '' && at_av_url_publica('https://ex ample.com') === '' && at_av_url_publica('javascript:alert(1)') === '', 'http, easypanel, espacios o javascript: no sirven (D8)');
ok(at_av_limpiar("<b>Hola</b>\n  mundo\x07") === 'Hola mundo' && at_av_limpiar("a\n\n\n\nb", true) === "a\n\nb", 'limpiar quita etiquetas y caracteres de control');
ok(at_av_fecha_valida('2026-02-28') && !at_av_fecha_valida('2026-02-30') && !at_av_fecha_valida('10-10-2026') && !at_av_fecha_valida(null), 'fechas AAAA-MM-DD reales');
$bp = at_av_bloques_plan($plan);
ok(array_column($bp, 'nombre') === ['Diseño', 'Desarrollo', 'Puesta en marcha'] && $bp[0]['plan_desde'] === '2026-10-05' && $bp[0]['plan_hasta'] === '2026-10-09' && $bp[2]['fase'] === 'implementacion', 'bloques del plan con su fase y sus fechas planificadas');
ok(at_av_bloque_del_plan($bp, '  DISEÑO ') === 'Diseño' && at_av_bloque_del_plan($bp, 'Otro') === '', 'los bloques se reconocen sin importar mayúsculas ni espacios');

// --- importar: el archivo bueno
$v = at_av_validar_import(archivo(), COD, $plan, $vacio);
ok($v['ok'] === true && $v['errores'] === [], 'archivo válido: sin errores', implode(' | ', $v['errores']));
ok($v['cierre']['bloques'] === ['Diseño', 'Desarrollo'], 'los bloques del cierre quedan con el nombre del plan');
$acts = $v['cierre']['durante']['actividades'];
ok(count($acts) === 2 && $acts[1]['plan_desde'] === '2026-10-12' && $acts[1]['plan_hasta'] === '2026-10-21' && $acts[1]['real'] === '2026-10-10', 'cada actividad trae sus fechas planificadas del plan');
ok(array_keys($v['evidencias']) === ['lh-antes', 'lh-despues', 'portada', 'reel', 'sitio'] && $v['evidencias']['reel']['poster_archivo'] === 'reel-poster.jpg', 'evidencias por ref, con el póster del video');
ok($v['cambios_plan'][0]['bloques'] === ['Puesta en marcha'] && $v['necesitamos'][0]['estado'] === 'abierto' && $v['alcance']['mensual'] === ['Publicidad y visibilidad'], 'cambios, necesitamos y alcance validados');
$sin_act = archivo(['cierre.durante' => ['texto' => 'x']]);
$v2 = at_av_validar_import($sin_act, COD, $plan, $vacio);
ok($v2['ok'] && count($v2['cierre']['durante']['actividades']) === 3 && $v2['cierre']['durante']['actividades'][0]['nombre'] === 'Maqueta de la portada', 'sin actividades: se precargan las del plan de esos bloques');
$v3 = at_av_validar_import(archivo(['cambios_plan' => null, 'necesitamos' => null, 'alcance' => null, 'bloques' => null]), COD, $plan, $vacio);
ok($v3['ok'] && $v3['cambios_plan'] === null && $v3['necesitamos'] === null && $v3['alcance'] === null && $v3['bloques'] === [], 'listas que no vienen: null (no se tocan al guardar)');

// --- importar: errores (Review Focus 4). Cada uno rechaza el archivo entero con un motivo simple.
$casos = [
	'otro formato' => [['formato' => 'otro'], 'no es un cierre'],
	'plan equivocado' => [['plan' => 'ZZZZZZ999999'], 'es de otro plan'],
	'cifra sin fuente' => [['evidencias' => [['ref' => 'x', 'tipo' => 'cifra', 'titulo' => 'Visitas', 'valor' => '10', 'fecha' => '2026-10-01']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'no tiene fuente'],
	'cifra sin fecha' => [['evidencias' => [['ref' => 'x', 'tipo' => 'cifra', 'titulo' => 'Visitas', 'valor' => '10', 'fuente' => 'GA4']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'no tiene fecha'],
	'enlace http' => [['evidencias' => [['ref' => 'x', 'tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'http://example.com']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'https'],
	'enlace easypanel' => [['evidencias' => [['ref' => 'x', 'tipo' => 'enlace', 'titulo' => 'Vista', 'url' => 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/x/']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'servidor interno'],
	'fecha real inválida' => [['cierre.fecha_real' => '2026-13-01'], 'fecha real del cierre'],
	'bloque inexistente' => [['cierre.bloques' => ['Diseño', 'Inventado']], 'no está en el plan'],
	'sin bloques' => [['cierre.bloques' => []], 'al menos un bloque'],
	'evidencia no listada' => [['cierre.despues' => ['evidencias' => ['no-existe']]], 'no está en la lista'],
	'captura sin archivo' => [['evidencias' => [['ref' => 'x', 'tipo' => 'captura', 'titulo' => 'Portada']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'necesita «archivo»'],
	'video .exe' => [['evidencias' => [['ref' => 'x', 'tipo' => 'video', 'titulo' => 'Reel', 'archivo' => 'reel.exe']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'video (.mp4 o .webm)'],
	'texto muy largo' => [['cierre.antes' => ['texto' => str_repeat('a', 2001)]], 'máximo 2000'],
	'ref repetida' => [['evidencias' => [['ref' => 'x', 'tipo' => 'enlace', 'titulo' => 'A', 'url' => 'https://a.cl'], ['ref' => 'x', 'tipo' => 'enlace', 'titulo' => 'B', 'url' => 'https://b.cl']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'misma «ref»'],
	'muchas evidencias' => [['evidencias' => array_map(fn($i) => ['ref' => "e$i", 'tipo' => 'enlace', 'titulo' => "E$i", 'url' => 'https://a.cl'], range(1, 61)), 'cierre.antes' => [], 'cierre.despues' => []], 'máximo 60'],
	'estado de bloque raro' => [['bloques' => ['Diseño' => ['estado' => 'casi']]], 'estado desconocido'],
	'cambio sin fecha' => [['cambios_plan' => [['titulo' => 'Algo']]], 'fecha válida'],
];
foreach ($casos as $nombre => [$cambios, $esperado]) {
	$r = at_av_validar_import(archivo($cambios), COD, $plan, $vacio);
	$texto = implode(' | ', $r['errores']);
	ok($r['ok'] === false && $r['cierre'] === [] && stripos($texto, $esperado) !== false, "rechaza: {$nombre} («{$esperado}»)" . ($r['ok'] ? '' : " — {$texto}"));
}
ok(at_av_validar_import('no es arreglo', COD, $plan, $vacio)['ok'] === false, 'algo que no es un objeto: rechazado sin lanzar');

// --- aplicar
$a1 = at_av_aplicar_import($vacio, $v, 'cabc123', '2026-10-13 18:00:00');
$av = $a1['avance'];
$c = $av['cierres'][0];
ok($a1['cierre_id'] === 'cabc123' && $c['estado'] === 'borrador' && $c['publicado_at'] === '' && $c['despues']['evidencias'] === ['cabc123-lh-despues', 'cabc123-portada', 'cabc123-reel', 'cabc123-sitio'], 'importar crea un cierre en borrador con ids de evidencia propios');
ok($av['evidencias']['cabc123-sitio']['url'] === 'https://example.com/' && $av['evidencias']['cabc123-portada']['url'] === '' && $av['bloques']['Puesta en marcha']['estado'] === 'en_curso' && count($av['cambios_plan']) === 1, 'enlaces con su url, archivos pendientes, bloques y listas guardados');
ok(at_av_faltan_archivos($av, 'cabc123') === ['cabc123-portada' => 'Portada nueva', 'cabc123-reel' => 'Recorrido del sitio'], 'faltan los archivos de la captura y del video (el póster es opcional)');
ok(at_av_cambiar_estado_cierre($av, 'cabc123', 'publicado', '2026-10-13 18:05:00') === 'Falta subir el archivo de: Portada nueva, Recorrido del sitio.', 'no se publica con archivos pendientes');
// Simular la subida de los archivos (Task 3 lo hace de verdad).
$av['evidencias']['cabc123-portada']['url'] = 'https://automatizatech.cl/wp-content/uploads/at-estado/7/aaaaaaaaaaaaaaaaaaaa.png';
$av['evidencias']['cabc123-reel']['url'] = 'https://automatizatech.cl/wp-content/uploads/at-estado/7/bbbbbbbbbbbbbbbbbbbb.mp4';
// Review Focus 1: reimportar el mismo título (borrador) conserva los archivos de las evidencias con la misma ref y archivo.
$corr = archivo(['cierre.titulo' => 'SITIO WEB', 'cierre.antes' => ['texto' => 'Texto corregido.', 'evidencias' => ['lh-antes']]]);
$corr['evidencias'][3]['archivo'] = 'reel-sitio-v2.mp4'; // el video cambió: hay que volver a subirlo
$vc = at_av_validar_import($corr, COD, $plan, $av);
$a2 = at_av_aplicar_import($av, $vc, 'cotro99', '2026-10-13 19:00:00');
ok($vc['ok'] && $a2['cierre_id'] === 'cabc123' && count($a2['avance']['cierres']) === 1 && $a2['avance']['cierres'][0]['antes']['texto'] === 'Texto corregido.', 'reimportar el mismo título reemplaza el borrador (mismo id)');
ok($a2['avance']['evidencias']['cabc123-portada']['url'] !== '' && $a2['avance']['evidencias']['cabc123-reel']['url'] === '' && $a2['archivos'] === ['bbbbbbbbbbbbbbbbbbbb.mp4'], 'la captura conserva su archivo; el video cambiado queda pendiente y su archivo viejo se puede borrar');
$pub = at_av_cambiar_estado_cierre($av, 'cabc123', 'publicado', '2026-10-13 18:05:00');
ok(is_array($pub) && $pub['cierres'][0]['estado'] === 'publicado' && $pub['cierres'][0]['publicado_at'] === '2026-10-13 18:05:00', 'con los archivos subidos se publica');
$r = at_av_validar_import(archivo(), COD, $plan, $pub);
ok($r['ok'] === false && stripos(implode(' ', $r['errores']), 'ya está publicado') !== false, 'un cierre publicado no se pisa al importar');
ok(is_string(at_av_quitar_cierre($pub, 'cabc123')), 'un cierre publicado no se quita (primero a borrador)');
$bor = at_av_cambiar_estado_cierre($pub, 'cabc123', 'borrador', '2026-10-13 18:10:00');
$q = at_av_quitar_cierre($bor, 'cabc123');
ok(is_array($q) && $q['avance']['cierres'] === [] && $q['avance']['evidencias'] === [] && $q['archivos'] === ['aaaaaaaaaaaaaaaaaaaa.png', 'bbbbbbbbbbbbbbbbbbbb.mp4'], 'quitar un borrador saca sus evidencias y devuelve sus archivos');
ok(at_av_cambiar_estado_cierre($av, 'nada', 'publicado', 'x') === 'Ese cierre no existe.', 'cierre inexistente: motivo');

// --- estado de los bloques
$eb = at_av_estado_bloques($plan, $pub);
ok($eb[0]['estado'] === 'listo' && $eb[0]['fin_real'] === '2026-10-10' && $eb[1]['estado'] === 'listo' && $eb[2]['estado'] === 'en_curso' && $eb[2]['inicio_real'] === '2026-10-13', 'bloques cubiertos por un cierre publicado: listos con la fecha del cierre');
$eb2 = at_av_estado_bloques($plan, $bor);
ok($eb2[0]['estado'] === 'por_venir' && $eb2[0]['fin_real'] === '', 'un cierre en borrador no marca nada como listo (Review Focus 2)');
ok(count(at_av_publicados($pub)) === 1 && at_av_publicados($bor) === [], 'publicados: solo los publicados');

// --- exportar para «Editar» (R2): el archivo vuelve a validar igual
$exp = at_av_exportar_cierre($pub, 'cabc123', COD);
$ve = at_av_validar_import($exp, COD, $plan, $bor);
ok($exp['formato'] === 'at-cierre-v1' && $exp['plan'] === COD && $ve['ok'] && $ve['cierre']['titulo'] === 'Sitio web' && array_keys($ve['evidencias']) === ['lh-antes', 'lh-despues', 'portada', 'reel', 'sitio'], 'exportar un cierre da un archivo at-cierre-v1 que vuelve a validar', implode(' | ', $ve['errores']));

// --- textos del panel
$e = [];
$cam = at_av_cambios_de_texto("2026-10-13 | Publicidad al mensual | Acordado por correo\n\n2026-10-20 | Otro cambio", $bp, $e);
ok($e === [] && count($cam) === 2 && $cam[0] === ['fecha' => '2026-10-13', 'titulo' => 'Publicidad al mensual', 'detalle' => 'Acordado por correo', 'bloques' => []], 'cambios de plan desde el panel: «fecha | título | detalle»');
ok(at_av_texto_de_cambios($cam) === "2026-10-13 | Publicidad al mensual | Acordado por correo\n2026-10-20 | Otro cambio |", 'y de vuelta al cuadro de texto');
$e = [];
at_av_cambios_de_texto('13-10-2026 | Mal', $bp, $e);
ok(count($e) === 1 && str_contains($e[0], 'línea 1'), 'cambio con fecha mal escrita: error que dice la línea');
$e = [];
$nec = at_av_necesitamos_de_texto("Logo en alta\n✓ Acceso al dominio", [['texto' => 'Logo en alta', 'estado' => 'abierto', 'fecha' => '2026-10-01']], '2026-10-14', $e);
ok($e === [] && $nec === [['texto' => 'Logo en alta', 'estado' => 'abierto', 'fecha' => '2026-10-01'], ['texto' => 'Acceso al dominio', 'estado' => 'resuelto', 'fecha' => '2026-10-14']], 'necesitamos: «✓ » marca resuelto y se conserva la fecha de lo que ya estaba');
ok(at_av_texto_de_necesitamos($nec) === "Logo en alta\n✓ Acceso al dominio", 'y de vuelta al cuadro de texto');
$e = [];
ok(at_av_lista_de_texto("Uno\n\nDos ", 'Fase 1', $e) === ['Uno', 'Dos'] && $e === [], 'listas de alcance: una por línea');
$e = [];
$b = at_av_bloques_de_post([['nombre' => 'Diseño', 'estado' => 'listo', 'inicio_real' => '2026-10-03', 'fin_real' => '2026-10-09'], ['nombre' => 'Desarrollo', 'estado' => 'por_venir', 'inicio_real' => '', 'fin_real' => ''], ['nombre' => 'Inventado', 'estado' => 'listo']], $plan, $e);
ok(array_keys($b) === ['Diseño', 'Desarrollo'] && $b['Diseño']['fin_real'] === '2026-10-09' && count($e) === 1, 'bloques del panel: se guardan los del plan; uno desconocido es error');
$e = [];
at_av_bloques_de_post([['nombre' => 'Diseño', 'estado' => 'listo', 'inicio_real' => '2026-10-09', 'fin_real' => '2026-10-03']], $plan, $e);
ok(count($e) === 1 && str_contains($e[0], 'antes'), 'fin real antes del inicio real: error');
fin();
```

- [ ] **Step 2: Correr la prueba y ver que falla**

Run: `/c/wamp64/bin/php/php8.4.15/php.exe tests/plan/avance-puras-test.php`
Expected: error fatal «Failed opening required … avance-puras.php».

- [ ] **Step 3: Escribir la implementación**

Crear `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php`:

```php
<?php
/**
 * Estado del proyecto (Etapa 2): forma del avance de un plan (columna `avance`), validación de «Importar cierre»
 * (formato at-cierre-v1), estado de los bloques y los textos del panel. Funciones puras: sin base ni WordPress
 * (tests/plan/avance-puras-test.php). Spec: Docs/superpowers/specs/2026-10-13-estado-del-proyecto-design.md §3 y §4.
 * El plan firmado (payload) solo se lee: el avance vive aparte (D3).
 */

const AT_AV_MAX_CIERRES = 30;
const AT_AV_MAX_EVIDENCIAS = 60;
const AT_AV_MAX_CAMBIOS = 30;
const AT_AV_MAX_NECESITAMOS = 30;
const AT_AV_MAX_TEXTO = 2000;
const AT_AV_MAX_PUNTO = 200;
const AT_AV_MAX_PUNTOS = 8;
const AT_AV_MAX_ALCANCE = 20;
const AT_AV_MAX_ACTIVIDADES = 30;
const AT_AV_ESTADOS_BLOQUE = ['listo', 'en_curso', 'por_venir'];
const AT_AV_TIPOS = ['captura', 'enlace', 'cifra', 'video'];

/** Registro de un render de «Estado del proyecto» (público o vista previa). */
function at_av_registro_vacio(): array {
	return ['estado' => '', 'view_url' => '', 'pdf_url' => '', 'nota' => '', 'at' => ''];
}

function at_av_vacio(): array {
	return [
		'version' => 1, 'bloques' => [], 'cierres' => [], 'evidencias' => [], 'cambios_plan' => [], 'necesitamos' => [],
		'alcance' => ['fase1' => [], 'mensual' => []], 'render' => at_av_registro_vacio(), 'render_previa' => at_av_registro_vacio(),
	];
}

/** Avance guardado (texto JSON o arreglo) con todas sus claves; lo que no tiene la forma esperada vuelve a vacío. */
function at_av_normalizar(mixed $guardado): array {
	if (is_string($guardado)) {
		$guardado = $guardado === '' ? [] : json_decode($guardado, true);
	}
	$a = at_av_vacio();
	if (!is_array($guardado)) {
		return $a;
	}
	foreach (['bloques', 'evidencias'] as $k) {
		if (is_array($guardado[$k] ?? null)) {
			$a[$k] = array_filter($guardado[$k], 'is_array');
		}
	}
	foreach (['cierres', 'cambios_plan', 'necesitamos'] as $k) {
		if (is_array($guardado[$k] ?? null)) {
			$a[$k] = array_values(array_filter($guardado[$k], 'is_array'));
		}
	}
	if (is_array($guardado['alcance'] ?? null)) {
		foreach (['fase1', 'mensual'] as $k) {
			$a['alcance'][$k] = array_values(array_filter((array) ($guardado['alcance'][$k] ?? []), 'is_string'));
		}
	}
	foreach (['render', 'render_previa'] as $k) {
		if (is_array($guardado[$k] ?? null)) {
			$a[$k] = array_merge(at_av_registro_vacio(), array_intersect_key($guardado[$k], at_av_registro_vacio()));
		}
	}
	return $a;
}

/** Texto sin etiquetas ni caracteres de control; en una sola línea salvo $multilinea. No recorta. */
function at_av_limpiar(mixed $v, bool $multilinea = false): string {
	if (!is_string($v) && !is_int($v) && !is_float($v)) {
		return '';
	}
	$t = strip_tags((string) $v);
	$t = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $t);
	$t = str_replace(["\r\n", "\r"], "\n", $t);
	$t = $multilinea ? (string) preg_replace("/\n{3,}/", "\n\n", $t) : (string) preg_replace('/\s+/u', ' ', $t);
	return trim($t);
}

/** Texto de un campo con tope. Pasarse es un error: nada se recorta en silencio. */
function at_av_texto(mixed $v, int $max, string $que, array &$errores, bool $multilinea = false): string {
	$t = at_av_limpiar($v, $multilinea);
	if (mb_strlen($t) > $max) {
		$errores[] = "{$que} tiene " . mb_strlen($t) . " caracteres (máximo {$max}).";
		return '';
	}
	return $t;
}

function at_av_fecha_valida(mixed $f): bool {
	return is_string($f) && (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/** Enlace que puede ver el cliente: https, sin espacios ni comillas, hasta 500 caracteres y nunca del servidor de
 *  Easypanel (el SMTP de Hostinger rechaza esos correos: D8). '' si no sirve. */
function at_av_url_publica(mixed $u): string {
	$u = is_string($u) ? trim($u) : '';
	if ($u === '' || strlen($u) > 500 || !preg_match('#^https://[^\s"\'<>]+$#i', $u)) {
		return '';
	}
	$host = strtolower((string) parse_url($u, PHP_URL_HOST));
	return ($host === '' || str_contains($host, 'easypanel')) ? '' : $u;
}

/** Clave para comparar nombres de bloques y títulos: minúsculas y espacios simples. */
function at_av_clave(string $s): string {
	return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
}

/** Bloques del plan firmado, en su orden: nombre, fase (clave), fechas planificadas (primera y última actividad) y entregable. */
function at_av_bloques_plan(array $plan): array {
	$out = [];
	foreach ((array) ($plan['fases'] ?? []) as $fase) {
		if (!is_array($fase)) {
			continue;
		}
		foreach ((array) ($fase['bloques'] ?? []) as $b) {
			if (!is_array($b) || trim((string) ($b['nombre'] ?? '')) === '') {
				continue;
			}
			$desde = '';
			$hasta = '';
			foreach ((array) ($b['actividades'] ?? []) as $a) {
				$d = (string) ($a['desde'] ?? '');
				$h = (string) ($a['hasta'] ?? '');
				if (at_av_fecha_valida($d) && ($desde === '' || $d < $desde)) {
					$desde = $d;
				}
				if (at_av_fecha_valida($h) && $h > $hasta) {
					$hasta = $h;
				}
			}
			$out[] = ['nombre' => (string) $b['nombre'], 'fase' => (string) ($fase['clave'] ?? ''), 'plan_desde' => $desde, 'plan_hasta' => $hasta, 'entregable' => (string) ($b['entregable'] ?? '')];
		}
	}
	return $out;
}

/** Nombre del bloque del plan que calza con $nombre (sin importar mayúsculas ni espacios); '' si no existe. */
function at_av_bloque_del_plan(array $bloques_plan, mixed $nombre): string {
	$k = at_av_clave(is_string($nombre) ? $nombre : '');
	foreach ($bloques_plan as $b) {
		if ($k !== '' && at_av_clave((string) $b['nombre']) === $k) {
			return (string) $b['nombre'];
		}
	}
	return '';
}

/** Actividades planificadas de esos bloques (para precargar «Durante»). */
function at_av_actividades_plan(array $plan, array $nombres): array {
	$claves = array_map(fn($n) => at_av_clave((string) $n), $nombres);
	$out = [];
	foreach ((array) ($plan['fases'] ?? []) as $fase) {
		foreach ((array) (is_array($fase) ? ($fase['bloques'] ?? []) : []) as $b) {
			if (!is_array($b) || !in_array(at_av_clave((string) ($b['nombre'] ?? '')), $claves, true)) {
				continue;
			}
			foreach ((array) ($b['actividades'] ?? []) as $a) {
				if (is_array($a)) {
					$out[] = ['bloque' => (string) $b['nombre'], 'nombre' => (string) ($a['nombre'] ?? ''), 'plan_desde' => (string) ($a['desde'] ?? ''),
						'plan_hasta' => (string) ($a['hasta'] ?? ''), 'real' => '', 'nota' => ''];
				}
			}
		}
	}
	return $out;
}

/** Una evidencia del archivo importado, normalizada; null (con su error) si no sirve. */
function at_av_validar_evidencia(mixed $e, int $n, array &$errores): ?array {
	if (!is_array($e)) {
		$errores[] = "La evidencia {$n} no tiene la forma esperada.";
		return null;
	}
	$antes = count($errores);
	$ref = is_string($e['ref'] ?? null) ? strtolower(trim($e['ref'])) : '';
	if (!preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $ref)) {
		$errores[] = "La evidencia {$n} no tiene «ref» (minúsculas, números y guiones; hasta 40).";
		return null;
	}
	$titulo = at_av_texto($e['titulo'] ?? null, 120, "El título de la evidencia «{$ref}»", $errores);
	if ($titulo === '' && count($errores) === $antes) {
		$errores[] = "La evidencia «{$ref}» no tiene título.";
	}
	$nombre = $titulo !== '' ? $titulo : $ref;
	$tipo = is_string($e['tipo'] ?? null) ? strtolower(trim($e['tipo'])) : '';
	if (!in_array($tipo, AT_AV_TIPOS, true)) {
		$errores[] = "La evidencia «{$nombre}» tiene un tipo desconocido (captura, enlace, cifra o video).";
		return null;
	}
	$fecha = $e['fecha'] ?? '';
	if ($fecha !== '' && $fecha !== null && !at_av_fecha_valida($fecha)) {
		$errores[] = "La fecha de la evidencia «{$nombre}» no es una fecha válida (AAAA-MM-DD).";
		$fecha = '';
	}
	$ev = ['ref' => $ref, 'tipo' => $tipo, 'titulo' => $titulo, 'url' => '', 'archivo' => '', 'poster' => '', 'poster_archivo' => '',
		'valor' => '', 'unidad' => '', 'fuente' => at_av_texto($e['fuente'] ?? null, 200, "La fuente de «{$nombre}»", $errores), 'fecha' => (string) ($fecha ?? '')];
	if ($tipo === 'cifra') {
		$ev['valor'] = at_av_texto($e['valor'] ?? null, 40, "El valor de «{$nombre}»", $errores);
		$ev['unidad'] = at_av_texto($e['unidad'] ?? null, 60, "La unidad de «{$nombre}»", $errores);
		// D6: ninguna cifra sin fuente y fecha.
		if ($ev['valor'] === '') {
			$errores[] = "La cifra «{$nombre}» no tiene valor.";
		}
		if ($ev['fuente'] === '') {
			$errores[] = "La cifra «{$nombre}» no tiene fuente.";
		}
		if ($ev['fecha'] === '') {
			$errores[] = "La cifra «{$nombre}» no tiene fecha.";
		}
	} elseif ($tipo === 'enlace') {
		$ev['url'] = at_av_url_publica($e['url'] ?? null);
		if ($ev['url'] === '') {
			$errores[] = "El enlace «{$nombre}» debe ser una dirección https que no sea del servidor interno.";
		}
	} else {
		$ext = $tipo === 'video' ? 'mp4|webm' : 'jpe?g|png|webp';
		$archivo = is_string($e['archivo'] ?? null) ? trim($e['archivo']) : '';
		if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}\.(' . $ext . ')$/i', $archivo)) {
			$errores[] = "La evidencia «{$nombre}» necesita «archivo» con un nombre de " . ($tipo === 'video' ? 'video (.mp4 o .webm).' : 'imagen (.jpg, .png o .webp).');
		}
		$ev['archivo'] = $archivo;
		if ($tipo === 'video' && ($e['poster'] ?? '') !== '' && ($e['poster'] ?? null) !== null) {
			$p = is_string($e['poster']) ? trim($e['poster']) : '';
			if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}\.(jpe?g|png|webp)$/i', $p)) {
				$errores[] = "El póster del video «{$nombre}» debe ser una imagen (.jpg, .png o .webp).";
			} else {
				$ev['poster_archivo'] = $p;
			}
		}
	}
	return count($errores) === $antes ? $ev : null;
}

/** «Antes» o «Después»: texto, hasta AT_AV_MAX_PUNTOS puntos y las refs de sus evidencias (deben existir). */
function at_av_validar_parte(mixed $p, string $que, array $refs, array &$errores): array {
	$p = is_array($p) ? $p : [];
	$out = ['texto' => at_av_texto($p['texto'] ?? null, AT_AV_MAX_TEXTO, "El texto de «{$que}»", $errores, true), 'puntos' => [], 'evidencias' => []];
	$puntos = $p['puntos'] ?? [];
	if (!is_array($puntos)) {
		$errores[] = "Los puntos de «{$que}» deben ser una lista.";
		$puntos = [];
	}
	if (count($puntos) > AT_AV_MAX_PUNTOS) {
		$errores[] = "«{$que}» trae " . count($puntos) . ' puntos (máximo ' . AT_AV_MAX_PUNTOS . ').';
	}
	foreach (array_slice(array_values($puntos), 0, AT_AV_MAX_PUNTOS) as $i => $t) {
		$x = at_av_texto($t, AT_AV_MAX_PUNTO, 'El punto ' . ($i + 1) . " de «{$que}»", $errores);
		if ($x !== '') {
			$out['puntos'][] = $x;
		}
	}
	foreach ((array) ($p['evidencias'] ?? []) as $r) {
		$r = is_string($r) ? strtolower(trim($r)) : '';
		if (!in_array($r, $refs, true)) {
			$errores[] = "«{$que}» nombra una evidencia que no está en la lista: «{$r}».";
			continue;
		}
		if (!in_array($r, $out['evidencias'], true)) {
			$out['evidencias'][] = $r;
		}
	}
	return $out;
}

/** Estado de un bloque: listo, en curso o por venir, con fechas reales válidas (fin no antes del inicio). */
function at_av_validar_estado_bloque(mixed $x, string $nombre, array &$errores): ?array {
	$x = is_array($x) ? $x : [];
	$estado = is_string($x['estado'] ?? null) ? $x['estado'] : '';
	if (!in_array($estado, AT_AV_ESTADOS_BLOQUE, true)) {
		$errores[] = "El bloque «{$nombre}» tiene un estado desconocido (listo, en_curso o por_venir).";
		return null;
	}
	$fechas = [];
	foreach (['inicio_real', 'fin_real'] as $k) {
		$f = $x[$k] ?? '';
		if ($f !== '' && $f !== null && !at_av_fecha_valida($f)) {
			$errores[] = "La fecha «{$k}» del bloque «{$nombre}» no es válida (AAAA-MM-DD).";
			return null;
		}
		$fechas[$k] = (string) ($f ?? '');
	}
	if ($fechas['inicio_real'] !== '' && $fechas['fin_real'] !== '' && $fechas['fin_real'] < $fechas['inicio_real']) {
		$errores[] = "El bloque «{$nombre}» termina antes de empezar.";
		return null;
	}
	return ['estado' => $estado] + $fechas;
}

function at_av_validar_cambios(mixed $l, array $bp, array &$errores): array {
	if (!is_array($l)) {
		$errores[] = '«cambios_plan» debe ser una lista.';
		return [];
	}
	if (count($l) > AT_AV_MAX_CAMBIOS) {
		$errores[] = 'Hay ' . count($l) . ' cambios de plan (máximo ' . AT_AV_MAX_CAMBIOS . ').';
	}
	$out = [];
	foreach (array_slice(array_values($l), 0, AT_AV_MAX_CAMBIOS) as $i => $c) {
		$c = is_array($c) ? $c : [];
		$n = $i + 1;
		if (!at_av_fecha_valida($c['fecha'] ?? null)) {
			$errores[] = "El cambio de plan {$n} no tiene una fecha válida (AAAA-MM-DD).";
			continue;
		}
		$titulo = at_av_texto($c['titulo'] ?? null, 120, "El título del cambio de plan {$n}", $errores);
		if ($titulo === '') {
			$errores[] = "El cambio de plan {$n} no tiene título.";
			continue;
		}
		$bloques = [];
		foreach ((array) ($c['bloques'] ?? []) as $nb) {
			$b = at_av_bloque_del_plan($bp, $nb);
			if ($b === '') {
				$errores[] = "El cambio «{$titulo}» nombra un bloque que no está en el plan.";
			} elseif (!in_array($b, $bloques, true)) {
				$bloques[] = $b;
			}
		}
		$out[] = ['fecha' => (string) $c['fecha'], 'titulo' => $titulo, 'detalle' => at_av_texto($c['detalle'] ?? null, 600, "El detalle de «{$titulo}»", $errores, true), 'bloques' => $bloques];
	}
	return $out;
}

function at_av_validar_necesitamos(mixed $l, array &$errores): array {
	if (!is_array($l)) {
		$errores[] = '«necesitamos» debe ser una lista.';
		return [];
	}
	if (count($l) > AT_AV_MAX_NECESITAMOS) {
		$errores[] = 'Hay ' . count($l) . ' ítems en «necesitamos» (máximo ' . AT_AV_MAX_NECESITAMOS . ').';
	}
	$out = [];
	foreach (array_slice(array_values($l), 0, AT_AV_MAX_NECESITAMOS) as $i => $x) {
		$x = is_array($x) ? $x : ['texto' => $x];
		$texto = at_av_texto($x['texto'] ?? null, 200, 'El ítem ' . ($i + 1) . ' de «necesitamos»', $errores);
		if ($texto === '') {
			continue;
		}
		$estado = ($x['estado'] ?? 'abierto') === 'resuelto' ? 'resuelto' : 'abierto';
		$fecha = at_av_fecha_valida($x['fecha'] ?? null) ? (string) $x['fecha'] : '';
		$out[] = ['texto' => $texto, 'estado' => $estado, 'fecha' => $fecha];
	}
	return $out;
}

function at_av_validar_alcance(mixed $a, array &$errores): array {
	$a = is_array($a) ? $a : [];
	$out = ['fase1' => [], 'mensual' => []];
	foreach (['fase1' => 'Fase 1', 'mensual' => 'Servicio mensual'] as $k => $que) {
		$l = $a[$k] ?? [];
		if (!is_array($l)) {
			$errores[] = "«{$que}» del alcance debe ser una lista.";
			continue;
		}
		if (count($l) > AT_AV_MAX_ALCANCE) {
			$errores[] = "«{$que}» trae " . count($l) . ' ítems (máximo ' . AT_AV_MAX_ALCANCE . ').';
		}
		foreach (array_slice(array_values($l), 0, AT_AV_MAX_ALCANCE) as $i => $t) {
			$x = at_av_texto($t, 200, "El ítem " . ($i + 1) . " de «{$que}»", $errores);
			if ($x !== '') {
				$out[$k][] = $x;
			}
		}
	}
	return $out;
}

/**
 * Valida un archivo «Importar cierre» (at-cierre-v1, spec §4) contra el plan abierto. Todo o nada: con errores no
 * devuelve nada que guardar. Los errores van en lenguaje simple (hasta 25). Un cierre publicado con el mismo título no
 * se pisa (hay que pasarlo a borrador primero). Las listas cambios_plan, necesitamos y alcance que no vienen quedan null
 * (al guardar no se tocan).
 */
function at_av_validar_import(mixed $d, string $codigo, array $plan, array $avance): array {
	$resultado = fn(array $errores, array $avisos = [], array $cierre = [], array $evidencias = [], array $bloques = [], ?array $cambios = null, ?array $necesitamos = null, ?array $alcance = null) =>
		['ok' => !$errores, 'errores' => $errores, 'avisos' => $avisos, 'cierre' => $errores ? [] : $cierre, 'evidencias' => $errores ? [] : $evidencias,
			'bloques' => $errores ? [] : $bloques, 'cambios_plan' => $errores ? null : $cambios, 'necesitamos' => $errores ? null : $necesitamos, 'alcance' => $errores ? null : $alcance];
	if (!is_array($d) || ($d['formato'] ?? '') !== 'at-cierre-v1') {
		return $resultado(['El archivo no es un cierre de AutomatizaTech (falta «formato»: «at-cierre-v1»).']);
	}
	if (!is_string($d['plan'] ?? null) || $d['plan'] !== $codigo) {
		return $resultado(['El archivo es de otro plan («' . at_av_limpiar($d['plan'] ?? '') . '»); este plan es «' . $codigo . '».']);
	}
	$errores = [];
	$avisos = [];
	$bp = at_av_bloques_plan($plan);

	$evs = $d['evidencias'] ?? [];
	if (!is_array($evs)) {
		$errores[] = '«evidencias» debe ser una lista.';
		$evs = [];
	}
	if (count($evs) > AT_AV_MAX_EVIDENCIAS) {
		$errores[] = 'El archivo trae ' . count($evs) . ' evidencias (máximo ' . AT_AV_MAX_EVIDENCIAS . ').';
		$evs = [];
	}
	$evidencias = [];
	foreach (array_values($evs) as $i => $e) {
		$ev = at_av_validar_evidencia($e, $i + 1, $errores);
		if ($ev === null) {
			continue;
		}
		if (isset($evidencias[$ev['ref']])) {
			$errores[] = "Hay dos evidencias con la misma «ref»: «{$ev['ref']}».";
			continue;
		}
		$evidencias[$ev['ref']] = $ev;
	}
	$refs = array_keys($evidencias);

	$c = is_array($d['cierre'] ?? null) ? $d['cierre'] : null;
	if ($c === null) {
		return $resultado(array_merge(['Falta «cierre».'], $errores));
	}
	$titulo = at_av_texto($c['titulo'] ?? null, 120, 'El título del cierre', $errores);
	if ($titulo === '') {
		$errores[] = 'El cierre no tiene título.';
	}
	$bloques = [];
	foreach ((array) ($c['bloques'] ?? []) as $nb) {
		$b = at_av_bloque_del_plan($bp, $nb);
		if ($b === '') {
			$errores[] = 'El bloque «' . at_av_limpiar($nb) . '» no está en el plan.';
		} elseif (!in_array($b, $bloques, true)) {
			$bloques[] = $b;
		}
	}
	if (!$bloques && !array_filter($errores, fn($e) => str_contains($e, 'no está en el plan'))) {
		$errores[] = 'El cierre debe nombrar al menos un bloque del plan.';
	}
	$fecha_real = $c['fecha_real'] ?? '';
	if (!at_av_fecha_valida($fecha_real)) {
		$errores[] = 'La fecha real del cierre no es una fecha válida (AAAA-MM-DD).';
		$fecha_real = '';
	}
	$ent = $c['entregable_id'] ?? 0;
	$entregable = (is_int($ent) || (is_string($ent) && ctype_digit($ent))) ? (int) $ent : -1;
	if ($entregable < 0) {
		$errores[] = '«entregable_id» debe ser un número (0 si no hay).';
		$entregable = 0;
	}
	$antes = at_av_validar_parte($c['antes'] ?? null, 'Antes', $refs, $errores);
	$despues = at_av_validar_parte($c['despues'] ?? null, 'Después', $refs, $errores);
	$dur = is_array($c['durante'] ?? null) ? $c['durante'] : [];
	$durante = ['texto' => at_av_texto($dur['texto'] ?? null, AT_AV_MAX_TEXTO, 'El texto de «Durante»', $errores, true), 'actividades' => []];
	$plan_acts = at_av_actividades_plan($plan, $bloques);
	if (!array_key_exists('actividades', $dur)) {
		$durante['actividades'] = $plan_acts; // spec §3: se precargan desde el plan
	} elseif (!is_array($dur['actividades'])) {
		$errores[] = 'Las actividades de «Durante» deben ser una lista.';
	} else {
		if (count($dur['actividades']) > AT_AV_MAX_ACTIVIDADES) {
			$errores[] = '«Durante» trae ' . count($dur['actividades']) . ' actividades (máximo ' . AT_AV_MAX_ACTIVIDADES . ').';
		}
		foreach (array_slice(array_values($dur['actividades']), 0, AT_AV_MAX_ACTIVIDADES) as $i => $a) {
			if (!is_array($a)) {
				$errores[] = 'La actividad ' . ($i + 1) . ' de «Durante» no tiene la forma esperada.';
				continue;
			}
			$nombre = at_av_texto($a['nombre'] ?? null, 120, 'El nombre de la actividad ' . ($i + 1), $errores);
			if ($nombre === '') {
				$errores[] = 'La actividad ' . ($i + 1) . ' de «Durante» no tiene nombre.';
				continue;
			}
			$bloque = at_av_bloque_del_plan($bp, $a['bloque'] ?? '');
			if ($bloque === '' || !in_array($bloque, $bloques, true)) {
				$errores[] = "La actividad «{$nombre}» es de un bloque que este cierre no cubre.";
				continue;
			}
			$real = $a['real'] ?? '';
			if ($real !== '' && $real !== null && !at_av_fecha_valida($real)) {
				$errores[] = "La fecha real de «{$nombre}» no es válida (AAAA-MM-DD).";
				$real = '';
			}
			$fila = ['bloque' => $bloque, 'nombre' => $nombre, 'plan_desde' => '', 'plan_hasta' => '', 'real' => (string) ($real ?? ''),
				'nota' => at_av_texto($a['nota'] ?? null, 300, "La nota de «{$nombre}»", $errores)];
			$hallada = false;
			foreach ($plan_acts as $pa) {
				if ($pa['bloque'] === $bloque && at_av_clave($pa['nombre']) === at_av_clave($nombre)) {
					$fila['plan_desde'] = $pa['plan_desde'];
					$fila['plan_hasta'] = $pa['plan_hasta'];
					$hallada = true;
					break;
				}
			}
			if (!$hallada) {
				$avisos[] = "La actividad «{$nombre}» no está en el plan: va sin fechas planificadas.";
			}
			$durante['actividades'][] = $fila;
		}
	}
	$usadas = array_unique(array_merge($antes['evidencias'], $despues['evidencias']));
	foreach ($refs as $r) {
		if (!in_array($r, $usadas, true)) {
			$avisos[] = "La evidencia «{$evidencias[$r]['titulo']}» no está en «Antes» ni en «Después»: no se mostrará.";
		}
	}

	$reemplaza = null;
	foreach ($avance['cierres'] ?? [] as $x) {
		if ($titulo !== '' && at_av_clave((string) ($x['titulo'] ?? '')) === at_av_clave($titulo)) {
			$reemplaza = $x;
			if (($x['estado'] ?? '') === 'publicado') {
				$errores[] = "El cierre «{$titulo}» ya está publicado: pásalo a borrador antes de importarlo de nuevo.";
			}
		}
	}
	if ($reemplaza === null && count($avance['cierres'] ?? []) >= AT_AV_MAX_CIERRES) {
		$errores[] = 'El plan ya tiene ' . AT_AV_MAX_CIERRES . ' cierres (el máximo).';
	}
	$viejas = $reemplaza ? count(array_merge((array) ($reemplaza['antes']['evidencias'] ?? []), (array) ($reemplaza['despues']['evidencias'] ?? []))) : 0;
	if (count($avance['evidencias'] ?? []) - $viejas + count($evidencias) > AT_AV_MAX_EVIDENCIAS) {
		$errores[] = 'Con este cierre el plan pasaría las ' . AT_AV_MAX_EVIDENCIAS . ' evidencias.';
	}

	$estado_bloques = [];
	if (array_key_exists('bloques', $d)) {
		if (!is_array($d['bloques'])) {
			$errores[] = '«bloques» debe ser un objeto con el nombre de cada bloque.';
		} else {
			foreach ($d['bloques'] as $nb => $x) {
				$b = at_av_bloque_del_plan($bp, (string) $nb);
				if ($b === '') {
					$errores[] = 'El bloque «' . at_av_limpiar((string) $nb) . '» no está en el plan.';
					continue;
				}
				$eb = at_av_validar_estado_bloque($x, $b, $errores);
				if ($eb !== null) {
					$estado_bloques[$b] = $eb;
				}
			}
		}
	}
	$cambios = array_key_exists('cambios_plan', $d) ? at_av_validar_cambios($d['cambios_plan'], $bp, $errores) : null;
	$necesitamos = array_key_exists('necesitamos', $d) ? at_av_validar_necesitamos($d['necesitamos'], $errores) : null;
	$alcance = array_key_exists('alcance', $d) ? at_av_validar_alcance($d['alcance'], $errores) : null;

	if (count($errores) > 25) {
		$errores = array_merge(array_slice($errores, 0, 25), ['… y ' . (count($errores) - 25) . ' más.']);
	}
	$cierre = ['titulo' => $titulo, 'bloques' => $bloques, 'fecha_real' => (string) $fecha_real, 'entregable_id' => $entregable,
		'antes' => $antes, 'durante' => $durante, 'despues' => $despues];
	return $resultado($errores, $avisos, $cierre, $evidencias, $estado_bloques, $cambios, $necesitamos, $alcance);
}

/** Nombres de archivo (captura, video y póster) que usan esas evidencias. */
function at_av_archivos_de(array $evidencias): array {
	$out = [];
	foreach ($evidencias as $e) {
		foreach (['url', 'poster'] as $k) {
			$u = (string) ($e[$k] ?? '');
			if ($u !== '' && in_array($e['tipo'] ?? '', ['captura', 'video'], true)) {
				$out[] = basename((string) parse_url($u, PHP_URL_PATH));
			}
		}
	}
	return $out;
}

/**
 * Guarda lo validado: crea el cierre en borrador, o reemplaza el borrador del mismo título conservando su id. Las
 * evidencias con la misma ref y el mismo nombre de archivo conservan el archivo ya subido (Review Focus 1); las demás
 * evidencias viejas salen y sus archivos vuelven en 'archivos' para borrarlos. Bloques nombrados se reemplazan;
 * cambios_plan, necesitamos y alcance solo si el archivo los trae. $nuevo_id: 'c' + 6 minúsculas o números.
 */
function at_av_aplicar_import(array $avance, array $v, string $nuevo_id, string $ahora): array {
	$id = $nuevo_id;
	$viejas = [];
	foreach ($avance['cierres'] as $i => $x) {
		if (at_av_clave((string) ($x['titulo'] ?? '')) === at_av_clave($v['cierre']['titulo'])) {
			$id = (string) $x['id'];
			foreach (array_merge((array) ($x['antes']['evidencias'] ?? []), (array) ($x['despues']['evidencias'] ?? [])) as $eid) {
				if (isset($avance['evidencias'][$eid])) {
					$viejas[$eid] = $avance['evidencias'][$eid];
					unset($avance['evidencias'][$eid]);
				}
			}
			unset($avance['cierres'][$i]);
		}
	}
	$mapa = [];
	$conservados = [];
	foreach ($v['evidencias'] as $ref => $ev) {
		$eid = $id . '-' . $ref;
		$mapa[$ref] = $eid;
		unset($ev['ref']);
		$antes = $viejas[$eid] ?? null;
		if ($antes && ($antes['tipo'] ?? '') === $ev['tipo'] && in_array($ev['tipo'], ['captura', 'video'], true)) {
			if (($antes['archivo'] ?? '') === $ev['archivo'] && ($antes['url'] ?? '') !== '') {
				$ev['url'] = $antes['url'];
				$conservados[] = basename((string) parse_url($antes['url'], PHP_URL_PATH));
			}
			if ($ev['poster_archivo'] !== '' && ($antes['poster_archivo'] ?? '') === $ev['poster_archivo'] && ($antes['poster'] ?? '') !== '') {
				$ev['poster'] = $antes['poster'];
				$conservados[] = basename((string) parse_url($antes['poster'], PHP_URL_PATH));
			}
		}
		$avance['evidencias'][$eid] = $ev;
	}
	$archivos = array_values(array_diff(at_av_archivos_de($viejas), $conservados));
	$parte = fn(array $p) => ['texto' => $p['texto'], 'puntos' => $p['puntos'], 'evidencias' => array_map(fn($r) => $mapa[$r], $p['evidencias'])];
	$c = $v['cierre'];
	$avance['cierres'][] = [
		'id' => $id, 'titulo' => $c['titulo'], 'bloques' => $c['bloques'], 'fecha_real' => $c['fecha_real'], 'estado' => 'borrador',
		'publicado_at' => '', 'entregable_id' => $c['entregable_id'], 'antes' => $parte($c['antes']), 'durante' => $c['durante'],
		'despues' => $parte($c['despues']), 'correo' => ['enviado_at' => null, 'prueba_at' => null, 'mensaje' => ''],
		'whatsapp' => ['abierto_at' => null], 'importado_at' => $ahora,
	];
	$avance['cierres'] = array_values($avance['cierres']);
	foreach ($v['bloques'] as $n => $b) {
		$avance['bloques'][$n] = $b;
	}
	foreach (['cambios_plan', 'necesitamos', 'alcance'] as $k) {
		if ($v[$k] !== null) {
			$avance[$k] = $v[$k];
		}
	}
	return ['avance' => $avance, 'cierre_id' => $id, 'archivos' => $archivos];
}

/** Evidencias de captura o video de ese cierre sin su archivo subido: [id => título]. El póster es opcional. */
function at_av_faltan_archivos(array $avance, string $cierre_id): array {
	$out = [];
	foreach ($avance['cierres'] as $c) {
		if (($c['id'] ?? '') !== $cierre_id) {
			continue;
		}
		foreach (array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? [])) as $eid) {
			$e = $avance['evidencias'][$eid] ?? null;
			if (is_array($e) && in_array($e['tipo'] ?? '', ['captura', 'video'], true) && ($e['url'] ?? '') === '') {
				$out[$eid] = (string) $e['titulo'];
			}
		}
	}
	return $out;
}

/** Publica o vuelve a borrador un cierre. Devuelve el avance nuevo o el motivo (texto) si no se puede. */
function at_av_cambiar_estado_cierre(array $avance, string $id, string $estado, string $ahora): array|string {
	foreach ($avance['cierres'] as $i => $c) {
		if (($c['id'] ?? '') !== $id) {
			continue;
		}
		if ($estado === 'publicado') {
			$faltan = at_av_faltan_archivos($avance, $id);
			if ($faltan) {
				return 'Falta subir el archivo de: ' . implode(', ', $faltan) . '.';
			}
			$avance['cierres'][$i]['estado'] = 'publicado';
			$avance['cierres'][$i]['publicado_at'] = $ahora;
		} elseif ($estado === 'borrador') {
			$avance['cierres'][$i]['estado'] = 'borrador';
			$avance['cierres'][$i]['publicado_at'] = '';
		} else {
			return 'Estado de cierre desconocido.';
		}
		return $avance;
	}
	return 'Ese cierre no existe.';
}

/** Quita un cierre en borrador con sus evidencias. ['avance', 'archivos' (para borrar)] o el motivo. */
function at_av_quitar_cierre(array $avance, string $id): array|string {
	foreach ($avance['cierres'] as $i => $c) {
		if (($c['id'] ?? '') !== $id) {
			continue;
		}
		if (($c['estado'] ?? '') !== 'borrador') {
			return 'Solo se quita un cierre en borrador.';
		}
		$quitadas = [];
		foreach (array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? [])) as $eid) {
			if (isset($avance['evidencias'][$eid])) {
				$quitadas[$eid] = $avance['evidencias'][$eid];
				unset($avance['evidencias'][$eid]);
			}
		}
		unset($avance['cierres'][$i]);
		$avance['cierres'] = array_values($avance['cierres']);
		return ['avance' => $avance, 'archivos' => at_av_archivos_de($quitadas)];
	}
	return 'Ese cierre no existe.';
}

/** Cierres publicados, del más antiguo al más nuevo (por fecha real). */
function at_av_publicados(array $avance): array {
	$l = array_values(array_filter($avance['cierres'], fn($c) => ($c['estado'] ?? '') === 'publicado'));
	usort($l, fn($a, $b) => strcmp((string) ($a['fecha_real'] ?? ''), (string) ($b['fecha_real'] ?? '')));
	return $l;
}

/** Bloques del plan con su estado para el cliente: el que dejó Luis o la importación, o «listo» si un cierre PUBLICADO
 *  los cubre (fin real = fecha del cierre si no traía una). Un borrador no marca nada. */
function at_av_estado_bloques(array $plan, array $avance): array {
	$publicados = at_av_publicados($avance);
	$out = [];
	foreach (at_av_bloques_plan($plan) as $b) {
		$x = is_array($avance['bloques'][$b['nombre']] ?? null) ? $avance['bloques'][$b['nombre']] : [];
		$fila = $b + [
			'estado' => in_array($x['estado'] ?? '', AT_AV_ESTADOS_BLOQUE, true) ? (string) $x['estado'] : 'por_venir',
			'inicio_real' => (string) ($x['inicio_real'] ?? ''), 'fin_real' => (string) ($x['fin_real'] ?? ''),
		];
		foreach ($publicados as $c) {
			if (in_array($b['nombre'], (array) ($c['bloques'] ?? []), true)) {
				$fila['estado'] = 'listo';
				if ($fila['fin_real'] === '') {
					$fila['fin_real'] = (string) $c['fecha_real'];
				}
			}
		}
		$out[] = $fila;
	}
	return $out;
}

/** Un cierre como archivo at-cierre-v1 (para «Editar», R2): vuelve a validar igual. [] si no existe. */
function at_av_exportar_cierre(array $avance, string $id, string $codigo): array {
	foreach ($avance['cierres'] as $c) {
		if (($c['id'] ?? '') !== $id) {
			continue;
		}
		$pref = $id . '-';
		$ref = fn(string $eid) => str_starts_with($eid, $pref) ? substr($eid, strlen($pref)) : $eid;
		$evidencias = [];
		foreach (array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? [])) as $eid) {
			$e = $avance['evidencias'][$eid] ?? null;
			if (!is_array($e) || isset($evidencias[$eid])) {
				continue;
			}
			$x = ['ref' => $ref($eid), 'tipo' => $e['tipo'], 'titulo' => $e['titulo']];
			if ($e['tipo'] === 'cifra') {
				$x += ['valor' => $e['valor'], 'unidad' => $e['unidad'], 'fuente' => $e['fuente'], 'fecha' => $e['fecha']];
			} elseif ($e['tipo'] === 'enlace') {
				$x['url'] = $e['url'];
			} else {
				$x['archivo'] = $e['archivo'];
				if (($e['poster_archivo'] ?? '') !== '') {
					$x['poster'] = $e['poster_archivo'];
				}
			}
			if ($e['tipo'] !== 'cifra' && ($e['fuente'] ?? '') !== '') {
				$x['fuente'] = $e['fuente'];
			}
			if ($e['tipo'] !== 'cifra' && ($e['fecha'] ?? '') !== '') {
				$x['fecha'] = $e['fecha'];
			}
			$evidencias[$eid] = $x;
		}
		$parte = fn(array $p) => ['texto' => (string) ($p['texto'] ?? ''), 'puntos' => (array) ($p['puntos'] ?? []), 'evidencias' => array_map($ref, (array) ($p['evidencias'] ?? []))];
		$actividades = array_map(fn($a) => ['bloque' => $a['bloque'], 'nombre' => $a['nombre'], 'real' => $a['real'], 'nota' => $a['nota']], (array) ($c['durante']['actividades'] ?? []));
		return [
			'formato' => 'at-cierre-v1', 'plan' => $codigo,
			'cierre' => ['titulo' => $c['titulo'], 'bloques' => $c['bloques'], 'fecha_real' => $c['fecha_real'], 'entregable_id' => (int) ($c['entregable_id'] ?? 0),
				'antes' => $parte($c['antes'] ?? []), 'durante' => ['texto' => (string) ($c['durante']['texto'] ?? ''), 'actividades' => $actividades], 'despues' => $parte($c['despues'] ?? [])],
			'evidencias' => array_values($evidencias),
		];
	}
	return [];
}

/* ---------- Textos del panel (sección «Avance», Task 7) ---------- */

/** «Cambios de plan»: una línea por cambio, «AAAA-MM-DD | título | detalle». Las líneas vacías se ignoran. */
function at_av_cambios_de_texto(string $t, array $bloques_plan, array &$errores): array {
	$lista = [];
	foreach (preg_split('/\r\n|\n|\r/', $t) as $i => $linea) {
		if (trim($linea) === '') {
			continue;
		}
		$p = array_map('trim', explode('|', $linea, 3));
		if (!at_av_fecha_valida($p[0])) {
			$errores[] = 'Cambios de plan, línea ' . ($i + 1) . ': empieza con la fecha AAAA-MM-DD («2026-10-13 | título | detalle»).';
			continue;
		}
		$lista[] = ['fecha' => $p[0], 'titulo' => $p[1] ?? '', 'detalle' => $p[2] ?? '', 'bloques' => []];
	}
	return at_av_validar_cambios($lista, $bloques_plan, $errores);
}

function at_av_texto_de_cambios(array $l): string {
	return implode("\n", array_map(fn($c) => trim($c['fecha'] . ' | ' . $c['titulo'] . ' | ' . str_replace("\n", ' ', (string) $c['detalle'])), $l));
}

/** «Necesitamos de ti»: una línea por ítem; «✓ » al inicio = resuelto. Un ítem que ya estaba (mismo texto) conserva su fecha. */
function at_av_necesitamos_de_texto(string $t, array $anteriores, string $hoy, array &$errores): array {
	$fechas = [];
	foreach ($anteriores as $a) {
		$fechas[at_av_clave((string) ($a['texto'] ?? ''))] = (string) ($a['fecha'] ?? '');
	}
	$lista = [];
	foreach (preg_split('/\r\n|\n|\r/', $t) as $linea) {
		$linea = trim($linea);
		if ($linea === '') {
			continue;
		}
		$resuelto = str_starts_with($linea, '✓');
		$texto = trim($resuelto ? mb_substr($linea, 1) : $linea);
		$lista[] = ['texto' => $texto, 'estado' => $resuelto ? 'resuelto' : 'abierto', 'fecha' => ($fechas[at_av_clave($texto)] ?? '') ?: $hoy];
	}
	return at_av_validar_necesitamos($lista, $errores);
}

function at_av_texto_de_necesitamos(array $l): string {
	return implode("\n", array_map(fn($x) => (($x['estado'] ?? '') === 'resuelto' ? '✓ ' : '') . $x['texto'], $l));
}

/** Lista del alcance (Fase 1 o mensual): una por línea. */
function at_av_lista_de_texto(string $t, string $que, array &$errores): array {
	$l = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $t)), 'strlen'));
	return at_av_validar_alcance([$que === 'mensual' ? 'mensual' : 'fase1' => $l], $errores)[$que === 'mensual' ? 'mensual' : 'fase1'];
}

/** Tabla de bloques del panel (bloques[i][nombre|estado|inicio_real|fin_real]) → [nombre del plan => estado]. */
function at_av_bloques_de_post(mixed $post, array $plan, array &$errores): array {
	$bp = at_av_bloques_plan($plan);
	$out = [];
	foreach ((array) $post as $fila) {
		$fila = is_array($fila) ? $fila : [];
		$nombre = at_av_bloque_del_plan($bp, $fila['nombre'] ?? '');
		if ($nombre === '') {
			$errores[] = 'El bloque «' . at_av_limpiar($fila['nombre'] ?? '') . '» no está en el plan.';
			continue;
		}
		$eb = at_av_validar_estado_bloque($fila, $nombre, $errores);
		if ($eb !== null) {
			$out[$nombre] = $eb;
		}
	}
	return $out;
}
```

Nota para quien implementa: el mensaje de `at_av_validar_estado_bloque` cuando el fin es anterior al inicio dice «termina antes de empezar»; la prueba busca la palabra «antes», que también aparece ahí.

- [ ] **Step 4: Correr la prueba y ver que pasa**

Run: `/c/wamp64/bin/php/php8.4.15/php.exe tests/plan/avance-puras-test.php`
Expected: todas las líneas «ok» y «TODO OK». Si un caso de error no calza con su texto esperado, corrige el mensaje en `avance-puras.php` (no la prueba), salvo que la prueba contradiga la spec.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php tests/plan/avance-puras-test.php
git commit -m "feat(estado): avance del plan e importar cierre at-cierre-v1 (funciones puras)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---
### Task 2: Columna `avance` — migración, lectura y escritura

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-datos.php`
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php` (función `at_pt_migrar_esquema`, líneas 16-56)
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php`
- Test: `tests/plan/avance-datos-wp-test.php`

**Interfaces:**
- Consumes (Task 1): `at_av_normalizar()`, `at_av_vacio()`, `at_av_registro_vacio()`. Del plan: `at_pt_tabla()`, `at_pt_plan(int): ?object` (hace `SELECT *`, así que la fila trae `->avance` cuando la columna existe).
- Produces: `at_av_columna_lista(): bool`; `at_av_migrar_esquema(): void` (en `admin_init`, prioridad 11); `at_av_leer(object $fila): array`; `at_av_guardar(int $plan_id, array $avance): bool`; `at_av_guardar_render(int $plan_id, string $clave /* 'render'|'render_previa' */, array $campos): bool`.

- [ ] **Step 1: Escribir la prueba que falla**

Crear `tests/plan/avance-datos-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/avance-datos-wp-test.php
// Estado del proyecto, Etapa 2 — Task 2: columna avance (migración idempotente), leer y guardar sin tocar el plan firmado.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_av_migrar_esquema', 'at_av_columna_lista', 'at_av_leer', 'at_av_guardar', 'at_av_guardar_render');
global $wpdb;
$t = at_pt_tabla();
$hay_columna = fn() => $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$t} LIKE %s", 'avance')) === 'avance';

// Migración: desde '1' agrega la columna (o la reconoce) y deja '2'; repetirla no hace nada.
update_option('at_plan_schema', '1');
delete_transient('at_av_migrar_intento');
at_av_migrar_esquema();
ok($hay_columna() && get_option('at_plan_schema') === '2' && at_av_columna_lista(), 'migración: columna avance y at_plan_schema = 2');
at_av_migrar_esquema();
ok(get_option('at_plan_schema') === '2', 'repetir la migración no cambia nada');
at_pt_migrar_esquema();
ok(get_option('at_plan_schema') === '2', 'la migración del plan no vuelve a correr ni baja la versión');

$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-avance-datos@example.com');
$plan = ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-avance-datos@example.com'));
$id = (int) $plan->id;
ptc_sembrar($id);
ok(at_av_leer(at_pt_plan($id)) === at_av_vacio(), 'plan sin avance: forma vacía');
$a = at_av_vacio();
$a['necesitamos'] = [['texto' => 'Logo en alta', 'estado' => 'abierto', 'fecha' => '2026-10-14']];
ok(at_av_guardar($id, $a) && at_av_leer(at_pt_plan($id))['necesitamos'][0]['texto'] === 'Logo en alta', 'guardar y leer el avance');
$payload_antes = (string) at_pt_plan($id)->payload;
$estado_antes = (string) at_pt_plan($id)->estado;
ok(at_av_guardar_render($id, 'render', ['estado' => 'listo', 'view_url' => 'https://x.cl/y', 'otra' => 1]), 'guardar un registro de render');
$l = at_av_leer(at_pt_plan($id));
ok($l['render']['estado'] === 'listo' && !isset($l['render']['otra']) && $l['necesitamos'][0]['texto'] === 'Logo en alta' && $l['render_previa'] === at_av_registro_vacio(), 'guardar un render toca solo su registro');
ok((string) at_pt_plan($id)->payload === $payload_antes && (string) at_pt_plan($id)->estado === $estado_antes, 'el plan firmado y su estado no cambian (D3)');
ok(!at_av_guardar_render($id, 'otra', []) && !at_av_guardar(999999999, $a), 'clave desconocida o plan inexistente: false');
$enorme = at_av_vacio();
$enorme['cambios_plan'] = array_fill(0, 3000, ['fecha' => '2026-10-14', 'titulo' => str_repeat('x', 120), 'detalle' => str_repeat('y', 600), 'bloques' => []]);
ok(!at_av_guardar($id, $enorme) && at_av_leer(at_pt_plan($id))['cambios_plan'] === [], 'un avance de más de 1,5 MB no se guarda y no pisa el anterior');
update_option('at_plan_schema', '1');
ok(!at_av_columna_lista() && !at_av_guardar($id, $a), 'sin la migración marcada no se guarda');
update_option('at_plan_schema', '2');
fin();
```

- [ ] **Step 2: Correr la prueba y ver que falla**

Run:
```bash
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad
AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/avance-datos-wp-test.php
```
Expected: «Faltan funciones: at_av_migrar_esquema…» (lo dice `exigir()`) y salida distinta de 0.

- [ ] **Step 3: Escribir la implementación**

Crear `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-datos.php`:

```php
<?php
/**
 * Estado del proyecto (Etapa 2): columna `avance` (JSON) de wp_automatiza_planes_trabajo. Migración idempotente
 * (at_plan_schema '1' → '2'), lectura y escritura. Sin la columna el módulo sigue funcionando: at_av_columna_lista()
 * da false, el panel avisa y la ruta de render responde 409. El payload del plan firmado nunca se toca (D3).
 */
if (!defined('ABSPATH')) {
	exit;
}

/** La migración del avance está hecha (columna avance creada). */
function at_av_columna_lista(): bool {
	return get_option('at_plan_schema') === '2';
}

/**
 * Agrega `avance LONGTEXT NULL` y deja at_plan_schema en '2'. Necesita la tabla creada (opción '1'). Si la columna ya
 * existe (instalación nueva: la crea dbDelta), solo marca la opción. Si falla, espera 5 minutos antes de reintentar,
 * igual que at_pt_migrar_esquema().
 */
function at_av_migrar_esquema(): void {
	if (get_option('at_plan_schema') !== '1' || get_transient('at_av_migrar_intento')) {
		return;
	}
	global $wpdb;
	$t = at_pt_tabla();
	$existe = fn(): bool => $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$t} LIKE %s", 'avance')) === 'avance';
	if (!$existe()) {
		$wpdb->query("ALTER TABLE {$t} ADD COLUMN avance LONGTEXT NULL");
		if (!$existe()) {
			set_transient('at_av_migrar_intento', 1, 5 * MINUTE_IN_SECONDS);
			error_log('at_av: no se pudo agregar la columna avance a ' . $t . ': ' . $wpdb->last_error);
			return;
		}
	}
	update_option('at_plan_schema', '2');
}
add_action('admin_init', 'at_av_migrar_esquema', 11);

/** Avance del plan con todas sus claves (forma vacía si no hay, o si la columna todavía no existe). */
function at_av_leer(object $fila): array {
	return at_av_normalizar($fila->avance ?? null);
}

/** Guarda el avance completo (normalizado). false sin la migración, si el plan no existe o si el JSON pasa de 1,5 MB. */
function at_av_guardar(int $plan_id, array $avance): bool {
	if (!at_av_columna_lista() || !at_pt_plan($plan_id)) {
		return false;
	}
	$json = wp_json_encode(at_av_normalizar($avance), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if (!is_string($json) || strlen($json) > 1572864) {
		return false;
	}
	global $wpdb;
	return $wpdb->update(at_pt_tabla(), ['avance' => $json], ['id' => $plan_id]) !== false;
}

/** Cambia solo el registro de un render ('render' o 'render_previa'), sobre el avance recién leído. */
function at_av_guardar_render(int $plan_id, string $clave, array $campos): bool {
	if (!in_array($clave, ['render', 'render_previa'], true)) {
		return false;
	}
	$f = at_pt_plan($plan_id);
	if (!$f) {
		return false;
	}
	$a = at_av_leer($f);
	$a[$clave] = array_merge($a[$clave], array_intersect_key($campos, at_av_registro_vacio()));
	return at_av_guardar($plan_id, $a);
}
```

En `wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php`, dos cambios en `at_pt_migrar_esquema()`:

1. La guarda del inicio, para que con la opción en `'2'` no vuelva a correr dbDelta ni baje la opción a `'1'`:

```php
	if (in_array(get_option('at_plan_schema'), ['1', '2'], true) || get_transient('at_pt_migrar_intento')) {
		return;
	}
```

(reemplaza `if (get_option('at_plan_schema') === '1' || get_transient('at_pt_migrar_intento')) {`). Actualiza el docblock: «Idempotente (opción at_plan_schema '1' o '2')».

2. En el `CREATE TABLE`, agregar la columna después de `pdf_url VARCHAR(500) NULL,` (instalación nueva):

```
  avance LONGTEXT NULL,
```

En `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php`, agregar después de `require_once __DIR__ . '/puras.php';` y de `require_once __DIR__ . '/datos.php';` respectivamente:

```php
require_once __DIR__ . '/avance-puras.php';
```
```php
require_once __DIR__ . '/avance-datos.php';
```

y en el docblock del archivo: «Estado del proyecto (Etapa 2): avance-*.php».

- [ ] **Step 4: Correr la prueba y ver que pasa**

Run (mismo comando del Step 2). Expected: «TODO OK». Después, la línea base: `datos-wp-test`, `datos-guardar-wp-test`, `rest-render-wp-test` y `panel-acciones-wp-test` siguen en «TODO OK».

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-datos.php wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php tests/plan/avance-datos-wp-test.php
git commit -m "feat(estado): columna avance del plan con migración idempotente

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Evidencias — subir, revisar por contenido, limpiar y alojar

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-evidencias.php`
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php`
- Test: `tests/plan/avance-evidencias-wp-test.php`

**Interfaces:**
- Consumes: del módulo de entregables (cargado siempre desde `inc/admin-proposals.php`) `at_en_abrir_editor_gd(string $ruta)` (editor GD de WordPress: al guardar, la imagen se reescribe sin EXIF ni GPS) y `at_en_archivos_subidos(array $campo): array` (normaliza `$_FILES['x']` múltiple a una lista de `['name','type','tmp_name','error','size']`).
- Produces:
  - Constantes `AT_AV_IMAGEN_MAX = 15728640`, `AT_AV_VIDEO_MAX = 209715200`, `AT_AV_LADO = 2000`.
  - `at_av_dir(int $plan_id): array` → `['dir' => ruta con / final, 'url' => URL con / final]` (crea la carpeta, su `.htaccess` e `index.php`).
  - `at_av_tipo_contenido(string $ruta): string` → `'jpg'|'png'|'webp'|'mp4'|'webm'|''`.
  - `at_av_guardar_archivo(array $a, int $plan_id, string $clase /* 'imagen'|'video' */): array|WP_Error` → `['url','archivo']`.
  - `at_av_asignar_archivos(array $avance, string $cierre_id, array $subidos, int $plan_id): array` → `['avance','errores','avisos','archivos']` (`archivos` = viejos reemplazados, para borrar).
  - `at_av_subir_una(array $avance, string $evidencia_id, string $campo /* 'archivo'|'poster' */, array $subido, int $plan_id): array` → `['avance','error','archivos']`.
  - `at_av_borrar_archivos(int $plan_id, array $nombres): void`.
  - En pruebas, `AT_AV_PRUEBAS` (definida solo en `tests/plan/`) acepta archivos que no vienen de una subida HTTP; en PROD no existe.

- [ ] **Step 1: Escribir la prueba que falla**

Crear `tests/plan/avance-evidencias-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/avance-evidencias-wp-test.php
// Estado del proyecto, Etapa 2 — Task 3: evidencias (Review Focus 3). Tipo por contenido, EXIF/GPS fuera, carpeta sin
// ejecución de código, nombres aleatorios, asignación por nombre de archivo y borrado seguro.
define('AT_AV_PRUEBAS', true);
require __DIR__ . '/wp-bootstrap.php';
exigir('at_av_dir', 'at_av_tipo_contenido', 'at_av_guardar_archivo', 'at_av_asignar_archivos', 'at_av_subir_una', 'at_av_borrar_archivos');
$plan_id = 990001; // carpeta de prueba: se borra al final
$tmp = sys_get_temp_dir() . '/at-av-prueba-' . getmypid() . '/';
wp_mkdir_p($tmp);
register_shutdown_function(function () use ($tmp, $plan_id) {
	foreach (glob($tmp . '*') ?: [] as $f) { @unlink($f); }
	@rmdir($tmp);
	$d = at_av_dir($plan_id)['dir'];
	foreach (glob($d . '*') ?: [] as $f) { @unlink($f); }
	@rmdir($d);
});
/** Archivo temporal con ese contenido; devuelve la entrada como la deja at_en_archivos_subidos(). */
function subido(string $tmp, string $nombre, string $contenido): array {
	$ruta = $tmp . md5($nombre . microtime(true)) . '-' . $nombre;
	file_put_contents($ruta, $contenido);
	return ['name' => $nombre, 'type' => 'application/octet-stream', 'tmp_name' => $ruta, 'error' => UPLOAD_ERR_OK, 'size' => strlen($contenido)];
}
function png(int $w = 40, int $h = 30): string {
	$im = imagecreatetruecolor($w, $h);
	imagefill($im, 0, 0, imagecolorallocate($im, 30, 120, 200));
	ob_start(); imagepng($im); imagedestroy($im);
	return (string) ob_get_clean();
}
/** JPEG con un segmento EXIF falso que trae un texto de GPS reconocible. */
function jpeg_con_exif(): string {
	$im = imagecreatetruecolor(60, 40);
	ob_start(); imagejpeg($im, null, 90); imagedestroy($im);
	$j = (string) ob_get_clean();
	$exif = "Exif\0\0" . 'GPSLatitude-33.4489-GPSLongitude-70.6693';
	return substr($j, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($j, 2);
}
$mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom" . str_repeat("\0", 64);

// Carpeta: nombres aleatorios, nunca código, noindex y no-transform (D7).
$d = at_av_dir($plan_id);
$base = dirname(rtrim($d['dir'], '/')) . '/';
$ht = (string) @file_get_contents($base . '.htaccess');
ok(is_dir($d['dir']) && str_ends_with($d['url'], '/at-estado/' . $plan_id . '/'), 'carpeta del plan en uploads/at-estado/<id>/');
ok(str_contains($ht, 'Require all denied') && str_contains($ht, '.(php|phtml|phar') && str_contains($ht, 'noindex') && str_contains($ht, 'no-transform') && str_contains($ht, 'Options -Indexes'), '.htaccess: no ejecuta código, no lista, noindex y no-transform');
ok(is_file($base . 'index.php'), 'index.php vacío en la carpeta base');

// Tipo por contenido.
ok(at_av_tipo_contenido(subido($tmp, 'a.png', png())['tmp_name']) === 'png' && at_av_tipo_contenido(subido($tmp, 'a.mp4', $mp4)['tmp_name']) === 'mp4', 'PNG y MP4 se reconocen por su contenido');
ok(at_av_tipo_contenido(subido($tmp, 'w.webm', "\x1A\x45\xDF\xA3" . str_repeat("\0", 40))['tmp_name']) === 'webm', 'WebM por su firma');
ok(at_av_tipo_contenido(subido($tmp, 'foto.jpg', '<?php echo 1; ?>')['tmp_name']) === '', 'código PHP con nombre .jpg: no es nada');

// Imágenes: se reescriben (sin EXIF ni GPS) con nombre aleatorio.
$r = at_av_guardar_archivo(subido($tmp, 'Foto Celular.JPG', jpeg_con_exif()), $plan_id, 'imagen');
$guardado = is_array($r) ? (string) file_get_contents($d['dir'] . $r['archivo']) : '';
ok(is_array($r) && preg_match('/^[a-z0-9]{20}\.jpg$/', $r['archivo']) && $r['url'] === $d['url'] . $r['archivo'], 'foto guardada con nombre aleatorio .jpg');
ok($guardado !== '' && !str_contains($guardado, 'GPSLatitude') && !str_contains($guardado, 'Exif'), 'la foto guardada ya no trae EXIF ni GPS');
$r = at_av_guardar_archivo(subido($tmp, 'captura.png', png()), $plan_id, 'imagen');
ok(is_array($r) && str_ends_with($r['archivo'], '.png'), 'un PNG queda PNG');
foreach ([['foto.jpg', '<?php echo 1; ?>'], ['dibujo.png', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>'], ['texto.webp', 'hola']] as [$n, $c]) {
	$e = at_av_guardar_archivo(subido($tmp, $n, $c), $plan_id, 'imagen');
	ok(is_wp_error($e) && str_contains($e->get_error_message(), 'no es una imagen'), "rechaza «{$n}» disfrazado");
}
$grande = subido($tmp, 'grande.png', png() . str_repeat("\0", 15728641));
$e = at_av_guardar_archivo($grande, $plan_id, 'imagen');
ok(is_wp_error($e) && str_contains($e->get_error_message(), '15 MB'), 'imagen de más de 15 MB: rechazada');
// Videos: MP4 o WebM tal cual.
$r = at_av_guardar_archivo(subido($tmp, 'reel.mp4', $mp4), $plan_id, 'video');
ok(is_array($r) && preg_match('/^[a-z0-9]{20}\.mp4$/', $r['archivo']) && file_get_contents($d['dir'] . $r['archivo']) === $mp4, 'video MP4 guardado sin cambios');
$e = at_av_guardar_archivo(subido($tmp, 'reel.mp4', 'esto no es un video'), $plan_id, 'video');
ok(is_wp_error($e) && str_contains($e->get_error_message(), 'no es un video'), 'un «.mp4» que es texto: rechazado');
$e = at_av_guardar_archivo(['name' => 'x.png', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0], $plan_id, 'imagen');
ok(is_wp_error($e) && str_contains($e->get_error_message(), 'no llegó completo'), 'subida cortada: motivo legible');

// Asignar por nombre de archivo (sin importar mayúsculas); el póster es aparte.
$av = at_av_vacio();
$av['cierres'][] = ['id' => 'cabc123', 'titulo' => 'Sitio', 'estado' => 'borrador', 'antes' => ['evidencias' => []], 'despues' => ['evidencias' => ['cabc123-portada', 'cabc123-reel']]];
$av['evidencias']['cabc123-portada'] = ['tipo' => 'captura', 'titulo' => 'Portada', 'url' => '', 'archivo' => 'portada.png', 'poster' => '', 'poster_archivo' => ''];
$av['evidencias']['cabc123-reel'] = ['tipo' => 'video', 'titulo' => 'Reel', 'url' => '', 'archivo' => 'reel.mp4', 'poster' => '', 'poster_archivo' => 'poster.jpg'];
$res = at_av_asignar_archivos($av, 'cabc123', [subido($tmp, 'Portada.PNG', png()), subido($tmp, 'reel.mp4', $mp4), subido($tmp, 'poster.jpg', jpeg_con_exif()), subido($tmp, 'sobra.png', png())], $plan_id);
$ev = $res['avance']['evidencias'];
ok($res['errores'] === [] && $ev['cabc123-portada']['url'] !== '' && $ev['cabc123-reel']['url'] !== '' && $ev['cabc123-reel']['poster'] !== '', 'captura, video y póster quedan con su URL');
ok(count($res['avisos']) === 1 && str_contains($res['avisos'][0], 'sobra.png') && at_av_faltan_archivos($res['avance'], 'cabc123') === [], 'un archivo que no calza se avisa; ya no falta nada');
$viejo = basename($ev['cabc123-portada']['url']);
$una = at_av_subir_una($res['avance'], 'cabc123-portada', 'archivo', subido($tmp, 'otra.png', png(50, 50)), $plan_id);
ok($una['error'] === '' && $una['avance']['evidencias']['cabc123-portada']['url'] !== $ev['cabc123-portada']['url'] && $una['archivos'] === [$viejo], 'subir uno reemplaza el archivo y devuelve el viejo para borrarlo');
$mala = at_av_subir_una($res['avance'], 'cabc123-portada', 'poster', subido($tmp, 'p.png', png()), $plan_id);
ok($mala['error'] === 'Solo un video lleva póster.', 'una captura no lleva póster');

// Borrado: solo nombres propios del plan, nunca rutas.
file_put_contents($d['dir'] . 'zzzzzzzzzzzzzzzzzzzz.png', 'x');
file_put_contents($tmp . 'fuera.png', 'x');
at_av_borrar_archivos($plan_id, ['zzzzzzzzzzzzzzzzzzzz.png', '../../fuera.png', '.htaccess', $viejo]);
ok(!is_file($d['dir'] . 'zzzzzzzzzzzzzzzzzzzz.png') && !is_file($d['dir'] . $viejo) && is_file($tmp . 'fuera.png') && is_file($base . '.htaccess'), 'borra solo archivos propios de la carpeta del plan');
fin();
```

- [ ] **Step 2: Correr la prueba y ver que falla**

Run: `AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/avance-evidencias-wp-test.php`
Expected: «Faltan funciones: at_av_dir…».

- [ ] **Step 3: Escribir la implementación**

Crear `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-evidencias.php`:

```php
<?php
/**
 * Estado del proyecto (Etapa 2): archivos de las evidencias (capturas, videos y pósters) — decisión D7. Se alojan en
 * wp-content/uploads/at-estado/<id del plan>/ con nombres aleatorios; la carpeta no ejecuta código, no se lista y lleva
 * noindex y no-transform (el CDN de Hostinger achica y recodifica imágenes si no se le dice). El tipo se mira por el
 * contenido, nunca por el nombre. Las imágenes se reescriben con GD (sin EXIF ni GPS, lado ≤ 2.000 px); los videos
 * (MP4 o WebM) se guardan tal cual. El póster de un video lo sube Luis (R1: en Hostinger no hay ffmpeg).
 */
if (!defined('ABSPATH')) {
	exit;
}

const AT_AV_IMAGEN_MAX = 15728640;  // 15 MB
const AT_AV_VIDEO_MAX = 209715200;  // 200 MB
const AT_AV_LADO = 2000;

/** Carpeta y URL de las evidencias del plan (con / final). Crea la carpeta base con su .htaccess e index.php. */
function at_av_dir(int $plan_id): array {
	$up = wp_upload_dir(null, false);
	$base = trailingslashit($up['basedir']) . 'at-estado/';
	$url = trailingslashit($up['baseurl']) . 'at-estado/';
	if (!is_dir($base)) {
		wp_mkdir_p($base);
	}
	if (!is_file($base . '.htaccess')) {
		$reglas = "# Evidencias de «Estado del proyecto»: solo imágenes y videos; nunca código.\n"
			. "Options -Indexes\n"
			. "<FilesMatch \"\\.(php|phtml|phar|php[0-9]|pl|py|cgi|sh|html?|svg|js)$\">\n"
			. "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
			. "</FilesMatch>\n"
			. "<IfModule mod_headers.c>\nHeader set X-Robots-Tag \"noindex, nofollow\"\nHeader set Cache-Control \"public, max-age=604800, no-transform\"\nHeader set X-Content-Type-Options \"nosniff\"\n</IfModule>\n";
		if (@file_put_contents($base . '.htaccess', $reglas) === false) {
			error_log('at_av_dir: no se pudo escribir el .htaccess de ' . $base);
		}
	}
	if (!is_file($base . 'index.php')) {
		file_put_contents($base . 'index.php', "<?php\n// Silencio.\n");
	}
	$dir = $base . (int) $plan_id . '/';
	if (!is_dir($dir)) {
		wp_mkdir_p($dir);
	}
	return ['dir' => $dir, 'url' => $url . (int) $plan_id . '/'];
}

/** Tipo real por el contenido: 'jpg', 'png', 'webp', 'mp4', 'webm' o '' (cualquier otra cosa). */
function at_av_tipo_contenido(string $ruta): string {
	if ($ruta === '' || !is_file($ruta)) {
		return '';
	}
	$info = @getimagesize($ruta);
	if ($info) {
		return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][(string) ($info['mime'] ?? '')] ?? '';
	}
	$h = (string) @file_get_contents($ruta, false, null, 0, 16);
	if (strlen($h) >= 12 && substr($h, 4, 4) === 'ftyp') {
		return 'mp4';
	}
	return strncmp($h, "\x1A\x45\xDF\xA3", 4) === 0 ? 'webm' : '';
}

/**
 * Guarda un archivo subido como evidencia. $clase 'imagen' (captura o póster): JPEG, PNG o WebP de hasta 15 MB, se
 * reescribe con GD y queda JPEG o PNG. $clase 'video': MP4 o WebM de hasta 200 MB, tal cual. Devuelve ['url','archivo']
 * o WP_Error con un motivo legible para el panel.
 */
function at_av_guardar_archivo(array $a, int $plan_id, string $clase) {
	$tmp = (string) ($a['tmp_name'] ?? '');
	$nombre = sanitize_file_name((string) ($a['name'] ?? 'archivo'));
	// Solo una subida HTTP real. AT_AV_PRUEBAS existe únicamente en tests/plan (en CLI no hay subidas HTTP).
	$es_subida = $tmp !== '' && (is_uploaded_file($tmp) || (defined('AT_AV_PRUEBAS') && AT_AV_PRUEBAS === true && is_file($tmp)));
	if (!$es_subida || (int) ($a['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		return new WP_Error('at_av_subida', "«{$nombre}» no llegó completo; vuelve a subirlo.");
	}
	$tipo = at_av_tipo_contenido($tmp);
	$peso = (int) @filesize($tmp);
	$d = at_av_dir($plan_id);
	$aleatorio = strtolower(wp_generate_password(20, false, false));
	if ($clase === 'video') {
		if (!in_array($tipo, ['mp4', 'webm'], true)) {
			return new WP_Error('at_av_tipo', "«{$nombre}» no es un video MP4 o WebM.");
		}
		if ($peso > AT_AV_VIDEO_MAX) {
			return new WP_Error('at_av_peso', "«{$nombre}» pesa más de 200 MB.");
		}
		$archivo = $aleatorio . '.' . $tipo;
		$ok = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $d['dir'] . $archivo) : @copy($tmp, $d['dir'] . $archivo);
		if (!$ok || !is_file($d['dir'] . $archivo)) {
			return new WP_Error('at_av_guardar', "No se pudo guardar «{$nombre}».");
		}
		return ['url' => $d['url'] . $archivo, 'archivo' => $archivo];
	}
	if (!in_array($tipo, ['jpg', 'png', 'webp'], true)) {
		return new WP_Error('at_av_tipo', "«{$nombre}» no es una imagen JPEG, PNG o WebP.");
	}
	if ($peso > AT_AV_IMAGEN_MAX) {
		return new WP_Error('at_av_peso', "«{$nombre}» pesa más de 15 MB.");
	}
	$ext = $tipo === 'png' ? 'png' : 'jpg';
	$archivo = $aleatorio . '.' . $ext;
	$ed = at_en_abrir_editor_gd($tmp);
	if (is_wp_error($ed)) {
		return new WP_Error('at_av_imagen', "No se pudo abrir «{$nombre}» como imagen.");
	}
	if (method_exists($ed, 'maybe_exif_rotate')) {
		$ed->maybe_exif_rotate(); // fotos de celular en vertical: se giran antes de perder el EXIF
	}
	$tam = $ed->get_size();
	$ok = true;
	if (max((int) $tam['width'], (int) $tam['height']) > AT_AV_LADO) {
		$ok = !is_wp_error($ed->resize(AT_AV_LADO, AT_AV_LADO, false));
	}
	// Guardar siempre reescribe la imagen: el EXIF (con el GPS) no pasa.
	$ok = $ok && !is_wp_error($ed->save($d['dir'] . $archivo, $ext === 'png' ? 'image/png' : 'image/jpeg'));
	unset($ed);
	if (!$ok || !is_file($d['dir'] . $archivo)) {
		@unlink($d['dir'] . $archivo);
		return new WP_Error('at_av_imagen', "No se pudo procesar «{$nombre}».");
	}
	return ['url' => $d['url'] . $archivo, 'archivo' => $archivo];
}

/** Nombre de archivo de una URL de evidencia ('' si no es de las nuestras). */
function at_av_nombre_de_url(string $u): string {
	$n = basename((string) parse_url($u, PHP_URL_PATH));
	return preg_match('/^[a-z0-9]{20}\.(jpg|png|mp4|webm)$/', $n) ? $n : '';
}

/**
 * Pone en las evidencias de captura y video de ese cierre los archivos subidos cuyo nombre original calza (sin
 * importar mayúsculas) con su «archivo» o su «poster». Un archivo que no calza se avisa y no se guarda.
 */
function at_av_asignar_archivos(array $avance, string $cierre_id, array $subidos, int $plan_id): array {
	$res = ['avance' => $avance, 'errores' => [], 'avisos' => [], 'archivos' => []];
	$ids = [];
	foreach ($avance['cierres'] as $c) {
		if (($c['id'] ?? '') === $cierre_id) {
			$ids = array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? []));
		}
	}
	$por_nombre = [];
	foreach ($subidos as $s) {
		$por_nombre[strtolower((string) ($s['name'] ?? ''))] = $s;
	}
	$usados = [];
	foreach (array_unique($ids) as $eid) {
		$e = $avance['evidencias'][$eid] ?? null;
		if (!is_array($e) || !in_array($e['tipo'] ?? '', ['captura', 'video'], true)) {
			continue;
		}
		foreach (['archivo' => 'url', 'poster_archivo' => 'poster'] as $campo => $destino) {
			$n = strtolower((string) ($e[$campo] ?? ''));
			if ($n === '' || !isset($por_nombre[$n])) {
				continue;
			}
			$usados[$n] = true;
			$r = at_av_guardar_archivo($por_nombre[$n], $plan_id, ($campo === 'archivo' && $e['tipo'] === 'video') ? 'video' : 'imagen');
			if (is_wp_error($r)) {
				$res['errores'][] = $r->get_error_message();
				continue;
			}
			$viejo = at_av_nombre_de_url((string) ($e[$destino] ?? ''));
			if ($viejo !== '') {
				$res['archivos'][] = $viejo;
			}
			$res['avance']['evidencias'][$eid][$destino] = $r['url'];
		}
	}
	foreach ($por_nombre as $n => $s) {
		if (!isset($usados[$n])) {
			$res['avisos'][] = '«' . sanitize_file_name((string) $s['name']) . '» no corresponde a ninguna evidencia de este cierre: no se guardó.';
		}
	}
	return $res;
}

/** Sube el archivo (o el póster) de una evidencia. ['avance', 'error' ('' si salió bien), 'archivos' (viejos)]. */
function at_av_subir_una(array $avance, string $evidencia_id, string $campo, array $subido, int $plan_id): array {
	$e = $avance['evidencias'][$evidencia_id] ?? null;
	$no = fn(string $m) => ['avance' => $avance, 'error' => $m, 'archivos' => []];
	if (!is_array($e) || !in_array($e['tipo'] ?? '', ['captura', 'video'], true)) {
		return $no('Esa evidencia no lleva archivo.');
	}
	if ($campo === 'poster' && $e['tipo'] !== 'video') {
		return $no('Solo un video lleva póster.');
	}
	$destino = $campo === 'poster' ? 'poster' : 'url';
	$r = at_av_guardar_archivo($subido, $plan_id, ($destino === 'url' && $e['tipo'] === 'video') ? 'video' : 'imagen');
	if (is_wp_error($r)) {
		return $no($r->get_error_message());
	}
	$viejo = at_av_nombre_de_url((string) ($e[$destino] ?? ''));
	$avance['evidencias'][$evidencia_id][$destino] = $r['url'];
	if ($destino === 'poster' && ($e['poster_archivo'] ?? '') === '') {
		// Para que «Editar» (que exporta el cierre) no pierda el póster al volver a importar.
		$n = sanitize_file_name((string) ($subido['name'] ?? ''));
		$avance['evidencias'][$evidencia_id]['poster_archivo'] = preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}\.(jpe?g|png|webp)$/i', $n) ? $n : '';
	}
	return ['avance' => $avance, 'error' => '', 'archivos' => $viejo !== '' ? [$viejo] : []];
}

/** Borra archivos de la carpeta del plan: solo nombres propios (20 letras o números y extensión conocida), nunca rutas. */
function at_av_borrar_archivos(int $plan_id, array $nombres): void {
	$dir = at_av_dir($plan_id)['dir'];
	foreach ($nombres as $n) {
		$n = (string) $n;
		if (preg_match('/^[a-z0-9]{20}\.(jpg|png|mp4|webm)$/', $n) && is_file($dir . $n)) {
			@unlink($dir . $n);
		}
	}
}
```

En `cargar.php`, después de `require_once __DIR__ . '/avance-datos.php';`:

```php
require_once __DIR__ . '/avance-evidencias.php';
```

- [ ] **Step 4: Correr la prueba y ver que pasa**

Run (mismo comando del Step 2). Expected: «TODO OK». Si la línea del EXIF falla porque el editor no es GD, revisa que `at_en_abrir_editor_gd()` exista (módulo de entregables cargado); no cambies la prueba.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-evidencias.php wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php tests/plan/avance-evidencias-wp-test.php
git commit -m "feat(estado): evidencias revisadas por contenido, sin EXIF y en carpeta sin ejecución

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---
### Task 4: Renderer — `document_type: 'estado'` y la presentación «Estado del proyecto»

**Files:**
- Create: `renderer/src/template-estado.js`
- Create: `renderer/test/fixtures/estado-ejemplo.js`
- Create: `renderer/test/template-estado.test.js`
- Create: `renderer/test/template-estado-layout.test.js`
- Create: `renderer/test/server-estado.test.js`
- Modify: `renderer/src/schema.js` (agregar `validateEstadoPayload` y exportarla)
- Modify: `renderer/src/server.js:4` (import) y `:41-47`, `:70-73`, `:147` (despacho por tipo; `image_briefs` ignorado en `estado`)
- Modify: `renderer/src/template-plan.js:999-1021` (solo agregar exportaciones)

**Interfaces:**
- Consumes: de `template.js` `STYLE`, `SCRIPT`, `CONTACT_ICONS`, `LOGO_URL`, `backgroundStyle(url, index, variant)`, `logoMark()`, `renderDeckControls()`; de `template-plan.js` (se exportan en esta tarea) `lunesDe`, `semanaIndice`, `diasEntre`, `fechaLarga`, `fechaCorta`, `rangoCorto`, `urlSegura`, `numero`, `texto`, `fondoPanel`, `porcentaje`, `FASES`, `ESTILO_PORTADA`, `ESTILO_GANTT`, `ESTILO_CIERRE`.
- Produces:
  - `validateEstadoPayload(body): {valid, errors}` (en `schema.js`).
  - `renderEstadoHtml(data, images = {}): string` (en `template-estado.js`).
  - Contrato del cuerpo (lo arma WordPress en la Task 5, `at_av_armar_render_estado`): `{document_type:'estado', unique_id, draft, company_name, client_name, proyecto, actualizado:'Y-m-d', plan_url, agenda:{whatsapp_url, web_url}, cronograma:{inicio, fin, semanas}, bloques:[{nombre, fase, plan_desde, plan_hasta, entregable, estado:'listo'|'en_curso'|'por_venir', inicio_real, fin_real}], cierres:[{id, titulo, bloques:[nombres], fecha_real, borrador:bool, entregable_url, antes:{texto, puntos, evidencias:[ev]}, durante:{texto, actividades:[{bloque, nombre, plan_desde, plan_hasta, real, nota}]}, despues:{…como antes}}], cambios_plan:[{fecha, titulo, detalle, bloques}], necesitamos:[texto], alcance:{fase1:[texto], mensual:[texto]}, image_briefs:[], images:{}}` con `ev = {tipo:'captura'|'enlace'|'cifra'|'video', titulo, url, poster, valor, unidad, fuente, fecha}`.
  - Fotos (las pone n8n en `images`, Task 6): se usan las del plan por lámina — portada `cover`, «Dónde vamos» `metodo`, cronograma `gantt`, cierres `fase_1..3` (rotando), cambios `portal`, alcance `reuniones`, lo que sigue `necesitamos`, cierre `cierre`.

- [ ] **Step 1: Escribir el ejemplo (fixture) y las pruebas que fallan**

Crear `renderer/test/fixtures/estado-ejemplo.js`:

```js
// «Estado del proyecto» de ejemplo para las pruebas del renderer. Datos inventados: el repositorio es público.
const COD = 'AbCdEf123456';

function bloque(nombre, fase, plan_desde, plan_hasta, estado, inicio_real = '', fin_real = '') {
  return { nombre, fase, plan_desde, plan_hasta, entregable: '', estado, inicio_real, fin_real };
}

function ev(tipo, titulo, extra = {}) {
  return Object.assign({ tipo, titulo, url: '', poster: '', valor: '', unidad: '', fuente: '', fecha: '' }, extra);
}

function estadoCorto(cambios = {}) {
  return Object.assign(
    {
      document_type: 'estado',
      unique_id: `${COD}-estado`,
      draft: false,
      company_name: '[PRUEBA] Panadería',
      client_name: 'Cliente Prueba',
      proyecto: 'Sitio de la panadería',
      actualizado: '2026-10-14',
      plan_url: `https://automatizatech.cl/ver-plan.php?id=${COD}`,
      agenda: { whatsapp_url: 'https://wa.me/56900000000?text=prueba', web_url: `https://automatizatech.cl/ver-plan.php?id=${COD}&agendar=1` },
      cronograma: { inicio: '2026-10-05', fin: '2026-11-20', semanas: 7 },
      bloques: [
        bloque('Diseño', 'diseno_desarrollo', '2026-10-05', '2026-10-09', 'listo', '2026-10-03', '2026-10-08'),
        bloque('Desarrollo', 'diseno_desarrollo', '2026-10-12', '2026-10-21', 'listo', '2026-10-08', '2026-10-10'),
        bloque('Puesta en marcha', 'implementacion', '2026-10-22', '2026-10-30', 'en_curso', '2026-10-13'),
        bloque('Acompañamiento', 'soporte', '2026-11-02', '2026-11-20', 'por_venir'),
      ],
      cierres: [
        {
          id: 'cabc123',
          titulo: 'Sitio web',
          bloques: ['Diseño', 'Desarrollo'],
          fecha_real: '2026-10-10',
          borrador: false,
          entregable_url: 'https://automatizatech.cl/ver-entregable.php?id=XyZ123456789',
          antes: {
            texto: 'El sitio usaba una plantilla genérica y tenía páginas de demostración.',
            puntos: ['Plantilla genérica', 'Páginas de demostración indexadas'],
            evidencias: [ev('cifra', 'Lighthouse celular', { valor: '56', unidad: 'puntos', fuente: 'PageSpeed Insights', fecha: '2026-10-06' })],
          },
          durante: {
            texto: 'Rediseñamos y publicamos el sitio.',
            actividades: [{ bloque: 'Diseño', nombre: 'Maqueta de la portada', plan_desde: '2026-10-05', plan_hasta: '2026-10-06', real: '2026-10-03', nota: '' }],
          },
          despues: {
            texto: 'Sitio nuevo publicado.',
            puntos: ['Publicado el 10 de octubre'],
            evidencias: [
              ev('cifra', 'Lighthouse celular', { valor: '98', unidad: 'puntos', fuente: 'medición local', fecha: '2026-10-13' }),
              ev('captura', 'Portada nueva', { url: 'https://automatizatech.cl/wp-content/uploads/at-estado/7/aaaaaaaaaaaaaaaaaaaa.png' }),
              ev('video', 'Recorrido del sitio', { url: 'https://automatizatech.cl/wp-content/uploads/at-estado/7/bbbbbbbbbbbbbbbbbbbb.mp4' }),
              ev('enlace', 'El sitio publicado', { url: 'https://example.com/' }),
            ],
          },
        },
      ],
      cambios_plan: [{ fecha: '2026-10-13', titulo: 'La publicidad pasa al servicio mensual', detalle: 'Lo acordamos por correo.', bloques: ['Puesta en marcha'] }],
      necesitamos: ['Fecha de inicio del servicio mensual'],
      alcance: { fase1: ['Sitio web publicado', 'Ficha de Google'], mensual: ['Publicidad y visibilidad'] },
      image_briefs: [],
      images: {},
    },
    cambios
  );
}

// El peor caso que acepta WordPress (topes de at_av_validar_import): 14 bloques, textos de 2.000 caracteres, 8 puntos
// de 200, 30 actividades, un cierre con 13 evidencias de títulos y fuentes largos, 30 cambios, 30 ítems y 20+20 de alcance.
function estadoLargo() {
  const largo = (n, base = 'texto largo de prueba ') => base.repeat(Math.ceil(n / base.length)).slice(0, n);
  const fases = ['diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo',
    'implementacion', 'implementacion', 'implementacion', 'implementacion', 'implementacion', 'soporte', 'soporte', 'soporte', 'soporte'];
  const bloques = fases.map((f, i) => {
    const d = new Date(Date.UTC(2026, 9, 5 + i * 7)).toISOString().slice(0, 10);
    const h = new Date(Date.UTC(2026, 9, 9 + i * 7)).toISOString().slice(0, 10);
    const estado = i < 5 ? 'listo' : i === 5 ? 'en_curso' : 'por_venir';
    return bloque(largo(80, `Bloque ${i + 1} con un nombre largo `), f, d, h, estado, i <= 5 ? d : '', i < 5 ? h : '');
  });
  const evs = Array.from({ length: 13 }, (_, i) =>
    i % 4 === 0 ? ev('cifra', largo(120), { valor: '1.234.567', unidad: largo(60), fuente: largo(200), fecha: '2026-10-13' })
      : i % 4 === 1 ? ev('captura', largo(120), { url: `https://automatizatech.cl/wp-content/uploads/at-estado/7/${'c'.repeat(19)}${i % 10}.png`, fuente: largo(200), fecha: '2026-10-13' })
        : i % 4 === 2 ? ev('video', largo(120), { url: `https://automatizatech.cl/wp-content/uploads/at-estado/7/${'v'.repeat(19)}${i % 10}.mp4` })
          : ev('enlace', largo(120), { url: `https://example.com/${'ruta-larga/'.repeat(30)}` })
  );
  const parte = (evidencias) => ({ texto: largo(2000), puntos: Array.from({ length: 8 }, () => largo(200)), evidencias });
  const cierre = (i, evidencias) => ({
    id: `c00000${i}`, titulo: largo(120, `Cierre ${i} con un título muy largo `), bloques: [bloques[i].nombre], fecha_real: `2026-10-1${i}`, borrador: false,
    entregable_url: 'https://automatizatech.cl/ver-entregable.php?id=XyZ123456789',
    antes: parte(evidencias.slice(0, 5)),
    durante: { texto: largo(2000), actividades: Array.from({ length: 30 }, (_, k) => ({ bloque: bloques[i].nombre, nombre: largo(120), plan_desde: '2026-10-05', plan_hasta: '2026-10-09', real: '2026-10-08', nota: largo(300) })) },
    despues: parte(evidencias.slice(5)),
  });
  return estadoCorto({
    proyecto: largo(120, 'Proyecto con un nombre muy largo '),
    company_name: largo(120, 'Empresa con un nombre largo '),
    cronograma: { inicio: '2026-10-05', fin: '2027-01-29', semanas: 17 },
    bloques,
    cierres: [cierre(1, evs), cierre(2, []), cierre(3, evs.slice(0, 2))],
    cambios_plan: Array.from({ length: 30 }, (_, i) => ({ fecha: '2026-10-13', titulo: largo(120, `Cambio ${i + 1} `), detalle: largo(600), bloques: [] })),
    necesitamos: Array.from({ length: 30 }, (_, i) => largo(200, `Necesitamos ${i + 1} `)),
    alcance: { fase1: Array.from({ length: 20 }, () => largo(200)), mensual: Array.from({ length: 20 }, () => largo(200)) },
  });
}

module.exports = { estadoCorto, estadoLargo, COD };
```

Crear `renderer/test/template-estado.test.js`:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const { renderEstadoHtml } = require('../src/template-estado');
const { validateEstadoPayload } = require('../src/schema');
const { estadoCorto, estadoLargo } = require('./fixtures/estado-ejemplo');

const laminas = (html) => (html.match(/<section class="slide/g) || []).length;

test('el estado de ejemplo valida y dibuja sus láminas en orden', () => {
  assert.deepEqual(validateEstadoPayload(estadoCorto()), { valid: true, errors: [] });
  const html = renderEstadoHtml(estadoCorto());
  // portada, dónde vamos, cronograma, 1 cierre, cambios, qué incluye, lo que sigue, cierre = 8
  assert.equal(laminas(html), 8);
  const orden = ['Estado del proyecto — Sitio de la panadería', 'Así va tu proyecto', 'Planificado y real', 'Sitio web',
    'Cambios en el plan', 'Qué incluye', 'Lo que sigue', '¿Revisamos juntos el avance?'];
  let desde = 0;
  for (const t of orden) {
    const i = html.indexOf(t, desde);
    assert.ok(i > desde - 1, `falta o está fuera de orden: ${t}`);
    desde = i;
  }
  assert.ok(html.includes('Actualizado el 14 de octubre de 2026'));
});

test('Dónde vamos: estado de cada bloque, «Estás aquí» en el que está en curso y el plan inicial en la ventana completa', () => {
  const html = renderEstadoHtml(estadoCorto());
  assert.ok(html.includes('<b>2 de 4</b>'));
  assert.match(html, /class="estado-bloque is-en_curso is-aqui"[^>]*>.*Estás aquí.*Puesta en marcha/s);
  assert.ok(html.includes('Planificado 5 – 9 oct · listo el 8 oct')); // rangoCorto junta el mes cuando es el mismo
  assert.ok(html.includes(`<a class="estado-plan" href="https://automatizatech.cl/ver-plan.php?id=AbCdEf123456" target="_top" rel="noopener">`));
  assert.ok(html.includes('Ver el plan de actividades inicial'));
});

test('cierre: antes, durante y después, fechas contra lo planificado, evidencias y la entrega', () => {
  const html = renderEstadoHtml(estadoCorto());
  assert.ok(html.includes('Lo teníamos planificado para el 21 de octubre de 2026 y quedó listo el 10 de octubre de 2026 (11 días antes de lo planificado).'));
  assert.ok(html.includes('<h3>Antes</h3>') && html.includes('<h3>Durante</h3>') && html.includes('<h3>Después</h3>'));
  assert.ok(html.includes('plan 6 oct · real 3 oct'));
  assert.match(html, /class="ev ev-cifra">.*<b>98<\/b><span>puntos<\/span>.*Fuente: medición local · 13 de octubre de 2026/s);
  assert.ok(html.includes('<video controls preload="none" src="https://automatizatech.cl/wp-content/uploads/at-estado/7/bbbbbbbbbbbbbbbbbbbb.mp4"'));
  assert.ok(html.includes('class="ev-video-pdf"'), 'en el PDF el video se cambia por un enlace (R1)');
  assert.ok(html.includes('href="https://automatizatech.cl/ver-entregable.php?id=XyZ123456789" target="_top"'));
  assert.ok(!html.includes('easypanel'), 'ningún enlace al servidor interno');
});

test('lo que no es https no se vuelve enlace ni imagen, y el texto se escapa', () => {
  const e = estadoCorto({ plan_url: 'javascript:alert(1)' });
  e.cierres[0].titulo = '<script>alert(1)</script>';
  e.cierres[0].despues.evidencias = [
    { tipo: 'captura', titulo: 'http', url: 'http://example.com/a.png' },
    { tipo: 'enlace', titulo: 'comillas', url: 'https://example.com/"onmouseover="x' },
  ];
  const html = renderEstadoHtml(e);
  assert.ok(!html.includes('<script>alert(1)</script>') && html.includes('&lt;script&gt;alert(1)&lt;/script&gt;'));
  assert.ok(!html.includes('javascript:') && !html.includes('class="estado-plan"'));
  assert.ok(!html.includes('http://example.com/a.png') && !html.includes('onmouseover'));
});

test('vista previa con borradores: marca BORRADOR y avisa que el cliente no la ve', () => {
  const e = estadoCorto({ draft: true });
  e.cierres[0].borrador = true;
  const html = renderEstadoHtml(e);
  assert.ok(html.includes('· BORRADOR') && html.includes('Vista previa con borradores · el cliente no la ve'));
  assert.ok(!renderEstadoHtml(estadoCorto()).includes('BORRADOR'));
});

test('sin cambios de plan no hay lámina de cambios; sin necesitamos dice que vamos al día', () => {
  const html = renderEstadoHtml(estadoCorto({ cambios_plan: [], necesitamos: [] }));
  assert.equal(laminas(html), 7);
  assert.ok(!html.includes('Cambios en el plan') && html.includes('Por ahora nada: vamos al día.'));
});

test('más de 4 evidencias: siguen en láminas «Evidencias» de hasta 8', () => {
  const html = renderEstadoHtml(estadoLargo());
  const c1 = (html.match(/Evidencias \(\d\/\d\)/g) || []);
  assert.deepEqual(c1, ['Evidencias (1/2)', 'Evidencias (2/2)']); // 13 = 4 en la lámina del cierre + 8 + 1
});

test('validateEstadoPayload explica lo que falta', () => {
  const r = validateEstadoPayload({ document_type: 'estado', unique_id: '../x', company_name: '', proyecto: 'P', actualizado: '2026-13-01', bloques: {}, cierres: [{}] });
  assert.equal(r.valid, false);
  for (const t of ['unique_id debe tener', 'company_name', 'actualizado debe ser una fecha', 'bloques debe ser un arreglo', 'el cierre 1 no tiene título']) {
    assert.ok(r.errors.some((e) => e.includes(t)), `falta el motivo: ${t} (${r.errors.join(' | ')})`);
  }
});
```

Crear `renderer/test/template-estado-layout.test.js`:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require('playwright');
const { renderEstadoHtml } = require('../src/template-estado');
const { renderToFiles } = require('../src/render');
const { estadoCorto, estadoLargo } = require('./fixtures/estado-ejemplo');

// Medido en Chromium de verdad a 1920×1080 y en modo impresión (lo que ve el PDF), igual que el plan
// (template-plan-layout.test.js): ningún elemento de una lámina termina a menos de 20 px del borde de abajo ni se sale
// por los lados, con el ejemplo corto y con el peor caso que acepta WordPress.
async function medir(datos) {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-estado-layout-'));
  const archivo = path.join(dir, 'index.html');
  await fs.writeFile(archivo, renderEstadoHtml(datos, {}), 'utf8');
  const browser = await chromium.launch({ args: ['--disable-dev-shm-usage', '--no-sandbox'] });
  try {
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
    await page.goto(pathToFileURL(archivo).href, { waitUntil: 'load' });
    await page.emulateMedia({ media: 'print' });
    return await page.evaluate(() => {
      const fuera = [];
      const slides = document.querySelectorAll('.slide');
      slides.forEach((slide, n) => {
        const s = slide.getBoundingClientRect();
        slide.querySelectorAll('*').forEach((el) => {
          if (el.closest('.slide-bg') || el.closest('.at-watermark')) return;
          // Lo que una caja recorta a propósito (overflow hidden + line-clamp) no se ve: se mide la caja, no su contenido.
          const recorte = el.parentElement && el.parentElement.closest('.hito-col, .ev, .cambio, .alcance > div, .sigue > div, .estado-bloque');
          if (recorte) return;
          const r = el.getBoundingClientRect();
          if ((!r.width && !r.height) || r.height >= s.height - 1) return;
          if (r.bottom > s.bottom - 20 || r.top < s.top - 1 || r.left < s.left - 1 || r.right > s.right + 1) {
            fuera.push(`lámina ${n + 1}: ${el.tagName.toLowerCase()}.${String(el.className)} termina a ${Math.round(r.bottom - s.top)} px`);
          }
        });
      });
      // En cada lámina de cierre, las tres columnas terminan antes de las tarjetas de evidencia (y el título antes de las columnas).
      const pisan = [...document.querySelectorAll('.estado-hito')].filter((s) => {
        const cab = s.querySelector('.panel-head').getBoundingClientRect();
        const cols = s.querySelector('.hito-cols').getBoundingClientRect();
        const evs = s.querySelector('.hito-evidencias');
        return cab.bottom > cols.top - 4 || (evs && cols.bottom > evs.getBoundingClientRect().top - 8);
      }).length;
      return { laminas: slides.length, fuera, pisan };
    });
  } finally {
    await browser.close();
    await fs.rm(dir, { recursive: true, force: true });
  }
}

test('el estado de ejemplo cabe en sus láminas de 1920×1080', async () => {
  const r = await medir(estadoCorto());
  assert.equal(r.laminas, 8);
  assert.deepEqual(r.fuera, []);
});

test('el peor caso (14 bloques, textos y listas en sus topes, 13 evidencias) no se sale de ninguna lámina', async () => {
  const r = await medir(estadoLargo());
  assert.deepEqual(r.fuera, []);
  assert.equal(r.pisan, 0, 'en las láminas de cierre el título, las columnas y las evidencias no se pisan');
});

test('el PDF del estado tiene una página por lámina', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-estado-pdf-'));
  try {
    const { pdfPath } = await renderToFiles(renderEstadoHtml(estadoCorto(), {}), dir);
    const pdf = (await fs.readFile(pdfPath)).toString('latin1');
    assert.equal((pdf.match(/\/Type\s*\/Page(?!s)/g) || []).length, 8);
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});
```

Crear `renderer/test/server-estado.test.js`:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { createApp } = require('../src/server');
const { estadoCorto } = require('./fixtures/estado-ejemplo');

async function conServidor(fn) {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-estado-'));
  const app = createApp({ publicDir, baseUrl: 'http://localhost:3000', higgsfieldCredentials: {} });
  const server = app.listen(0);
  await new Promise((r) => server.once('listening', r));
  const { port } = server.address();
  try {
    await fn(`http://127.0.0.1:${port}`, publicDir);
  } finally {
    server.close();
    await fs.rm(publicDir, { recursive: true, force: true });
  }
}

const renderizar = (base, cuerpo) =>
  fetch(`${base}/render`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(cuerpo) });

test('POST /render con document_type estado dibuja «Estado del proyecto» en /p/<código>-estado/', async () => {
  await conServidor(async (base, publicDir) => {
    const r = await renderizar(base, estadoCorto());
    assert.equal(r.status, 200);
    const cuerpo = await r.json();
    assert.equal(cuerpo.view_url, 'http://localhost:3000/p/AbCdEf123456-estado/index.html');
    assert.equal(cuerpo.pdf_url, 'http://localhost:3000/p/AbCdEf123456-estado/presentation.pdf');
    const html = await fs.readFile(path.join(publicDir, 'AbCdEf123456-estado', 'index.html'), 'utf8');
    assert.ok(html.includes('Estado del proyecto — Sitio de la panadería'));
    assert.ok(!html.includes('Plan de trabajo —') && !html.includes('Propuesta de transformación digital'));
  });
});

test('con document_type estado nunca se piden fotos, aunque lleguen descripciones (D9)', async () => {
  await conServidor(async (base) => {
    const r = await renderizar(base, estadoCorto({ image_briefs: [{ slide: 'cover', prompt: 'una panadería de día' }] }));
    assert.equal(r.status, 200);
    const cuerpo = await r.json();
    assert.equal(cuerpo.images.requested, 0);
    assert.deepEqual(cuerpo.images.missing, []);
  });
});

test('un estado inválido responde 400 con motivos legibles, sin escribir nada', async () => {
  await conServidor(async (base, publicDir) => {
    const r = await renderizar(base, estadoCorto({ cierres: 'no' }));
    assert.equal(r.status, 400);
    const cuerpo = await r.json();
    assert.ok(cuerpo.details.includes('cierres debe ser un arreglo'));
    assert.deepEqual(await fs.readdir(publicDir), []);
  });
});
```

- [ ] **Step 2: Correr las pruebas y ver que fallan**

Run: `cd renderer && node --test test/template-estado.test.js test/server-estado.test.js`
Expected: FAIL con «Cannot find module '../src/template-estado'».

- [ ] **Step 3: Exportar las piezas del plan que usa el estado**

En `renderer/src/template-plan.js`, dentro de `module.exports = { … }` (al final de la lista, después de `renderCierreSlide,`), agregar solo estas exportaciones (el HTML del plan no cambia):

```js
  // Piezas que comparte «Estado del proyecto» (template-estado.js). Solo se agregan exportaciones.
  numero,
  texto,
  fondoPanel,
  porcentaje,
  FASES,
  ESTILO_PORTADA,
  ESTILO_GANTT,
  ESTILO_CIERRE,
```

- [ ] **Step 4: Validación del cuerpo de estado**

En `renderer/src/schema.js`, antes de `module.exports`, agregar:

```js
// «Estado del proyecto» (document_type 'estado'). WordPress ya validó el avance (at_av_validar_import); aquí solo la
// forma que el renderer necesita para dibujar, con motivos legibles en details (n8n los copia a la nota).
const ESTADO_REQUIRED_STRING_FIELDS = ['unique_id', 'company_name', 'proyecto'];

function validateEstadoPayload(body) {
  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    return { valid: false, errors: ['el cuerpo debe ser un objeto JSON'] };
  }
  const errors = [];
  for (const field of ESTADO_REQUIRED_STRING_FIELDS) {
    if (typeof body[field] !== 'string' || body[field].trim() === '') {
      errors.push(`falta o está vacío el campo obligatorio: ${field}`);
    }
  }
  if (typeof body.unique_id === 'string' && body.unique_id.trim() !== '' && !/^[A-Za-z0-9_-]{6,64}$/.test(body.unique_id)) {
    errors.push('unique_id debe tener de 6 a 64 caracteres, solo letras, números, guion o guion bajo');
  }
  if (!esFechaYmd(body.actualizado)) errors.push('actualizado debe ser una fecha AAAA-MM-DD');
  if (!Array.isArray(body.bloques)) errors.push('bloques debe ser un arreglo');
  if (!Array.isArray(body.cierres)) {
    errors.push('cierres debe ser un arreglo');
  } else {
    body.cierres.forEach((c, i) => {
      if (!c || typeof c !== 'object' || typeof c.titulo !== 'string' || c.titulo.trim() === '') {
        errors.push(`el cierre ${i + 1} no tiene título`);
      }
    });
  }
  const c = body.cronograma;
  if (c !== undefined && (!c || typeof c !== 'object' || Array.isArray(c))) {
    errors.push('cronograma debe ser un objeto si viene');
  }
  return { valid: errors.length === 0, errors };
}
```

y cambiar la exportación a:

```js
module.exports = { validatePayload, validatePlanPayload, validateEstadoPayload, REQUIRED_STRING_FIELDS, REQUIRED_ARRAY_FIELDS, PLAN_REQUIRED_STRING_FIELDS, ESTADO_REQUIRED_STRING_FIELDS };
```

- [ ] **Step 5: La plantilla «Estado del proyecto»**

Crear `renderer/src/template-estado.js`:

```js
const { escapeHtml } = require('./escape');
const { STYLE, SCRIPT, CONTACT_ICONS, LOGO_URL, backgroundStyle, logoMark, renderDeckControls } = require('./template');
const {
  lunesDe,
  semanaIndice,
  diasEntre,
  fechaLarga,
  fechaCorta,
  rangoCorto,
  urlSegura,
  numero,
  texto,
  fondoPanel,
  porcentaje,
  FASES,
  ESTILO_PORTADA,
  ESTILO_GANTT,
  ESTILO_CIERRE,
} = require('./template-plan');

// «Estado del proyecto» (document_type 'estado'): la presentación viva del avance de un plan ya enviado (spec §7).
// Láminas: portada, dónde vamos, cronograma planificado y real, una por cierre publicado (más «Evidencias» si no caben),
// cambios de plan (si hay), qué incluye, lo que sigue y lo que necesitamos, y cierre. Sin fotos nuevas (D9): usa las del
// plan por lámina. Todos los enlaces pasan por urlSegura (solo https); WordPress ya los manda por automatizatech.cl (D8).

const ESTADOS = { listo: 'Listo', en_curso: 'En curso', por_venir: 'Por venir' };
const EVIDENCIAS_EN_CIERRE = 4;
const EVIDENCIAS_POR_LAMINA = 8;
const PUNTOS_VISIBLES = 3;
const ACTIVIDADES_VISIBLES = 6;
const BLOQUES_POR_COLUMNA = 7;
const CAMBIOS_VISIBLES = 8;
const PROXIMOS_VISIBLES = 6;
const NECESITAMOS_VISIBLES = 8;
const DIA_MS = 86400000;

const objetos = (l) => (Array.isArray(l) ? l.filter((x) => x && typeof x === 'object') : []);
const cadenas = (l) => (Array.isArray(l) ? l.filter((x) => typeof x === 'string' && x.trim()) : []);
const esFecha = (f) => typeof f === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(f);
const estadoDe = (b) => (Object.prototype.hasOwnProperty.call(ESTADOS, b && b.estado) ? b.estado : 'por_venir');
const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;

function cabeza(index, eyebrow, titulo) {
  return `<div class="panel-head plan-anima"><p class="eyebrow">${numero(index)} · ${eyebrow}</p><h2>${titulo}</h2></div>`;
}

function renderPortadaEstado(d, foto) {
  const proyecto = texto(d.proyecto);
  const cliente = texto(d.client_name).trim();
  const actualizado = fechaLarga(d.actualizado);
  const detalle = [cliente ? `Preparado para ${escapeHtml(cliente)}` : '', actualizado ? `Actualizado el ${escapeHtml(actualizado)}` : '']
    .filter(Boolean)
    .join(' · ');
  return `
    <section class="slide slide-cover plan-cover estado-portada">
      <div class="slide-bg" style="${backgroundStyle(foto, 0, 'cover')}"></div>
      ${logoMark()}
      <div class="slide-body">
        <p class="eyebrow">${escapeHtml(texto(d.company_name))}</p>
        <h1${proyecto.length > 44 ? ' class="is-largo"' : ''}>Estado del proyecto — ${escapeHtml(proyecto)}</h1>
        ${detalle ? `<p class="lede">${detalle}</p>` : ''}
        <div class="accent-bar"></div>
      </div>
    </section>`;
}

function fechasBloque(b) {
  const plan = esFecha(b.plan_desde) && esFecha(b.plan_hasta) ? `Planificado ${rangoCorto(b.plan_desde, b.plan_hasta)}` : '';
  const e = estadoDe(b);
  const real =
    e === 'listo' && esFecha(b.fin_real) ? `listo el ${fechaCorta(b.fin_real)}` : e === 'en_curso' && esFecha(b.inicio_real) ? `empezó el ${fechaCorta(b.inicio_real)}` : '';
  return [plan, real].filter(Boolean).join(' · ');
}

function renderDondeVamos(d, index, foto) {
  const bloques = objetos(d.bloques);
  let aqui = bloques.findIndex((b) => estadoDe(b) === 'en_curso');
  if (aqui < 0) aqui = bloques.findIndex((b) => estadoDe(b) !== 'listo');
  const grupos = [];
  bloques.forEach((b, i) => {
    let g = grupos.find((x) => x.fase === b.fase);
    if (!g) {
      g = { fase: b.fase, items: [] };
      grupos.push(g);
    }
    g.items.push({ b, i });
  });
  // Una fase con muchos bloques se reparte en columnas de 7 para no salirse de la lámina.
  const columnas = [];
  for (const g of grupos) {
    for (let k = 0; k < g.items.length; k += BLOQUES_POR_COLUMNA) {
      columnas.push({ titulo: (FASES[g.fase] || 'Otras actividades') + (k ? ' (sigue)' : ''), items: g.items.slice(k, k + BLOQUES_POR_COLUMNA) });
    }
  }
  const html = columnas
    .map(
      (col) =>
        `<div class="estado-fase"><h3>${escapeHtml(col.titulo)}</h3><ol>${col.items
          .map(({ b, i }) => {
            const e = estadoDe(b);
            return `<li class="estado-bloque is-${e}${i === aqui ? ' is-aqui' : ''}"><span class="estado-chip">${e === 'listo' ? '✓ ' : ''}${ESTADOS[e]}</span>${
              i === aqui ? '<span class="estado-aqui">Estás aquí</span>' : ''
            }<b title="${escapeHtml(texto(b.nombre))}">${escapeHtml(texto(b.nombre))}</b><small>${escapeHtml(fechasBloque(b))}</small></li>`;
          })
          .join('')}</ol></div>`
    )
    .join('');
  const listos = bloques.filter((b) => estadoDe(b) === 'listo').length;
  const plan = urlSegura(d.plan_url);
  return `
    <section class="slide plan-panel estado-donde">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      ${cabeza(index, 'Dónde vamos', 'Así va tu proyecto')}
      <div class="panel-resumen"><span><small>Partes listas</small><b>${listos} de ${bloques.length}</b></span></div>
      <div class="estado-fases">${html || '<p class="estado-vacio">Pronto verás aquí cada parte del plan.</p>'}</div>
      ${
        plan
          ? `<a class="estado-plan" href="${escapeHtml(plan)}" target="_top" rel="noopener"><b>Ver el plan de actividades inicial</b><small>El plan que firmamos, con sus fechas planificadas</small></a>`
          : ''
      }
    </section>`;
}

function renderCronogramaReal(d, index, foto) {
  const c = d.cronograma && typeof d.cronograma === 'object' ? d.cronograma : {};
  const hoy = esFecha(d.actualizado) ? d.actualizado : '';
  const bloques = objetos(d.bloques).filter((b) => esFecha(b.plan_desde) && esFecha(b.plan_hasta));
  const reales = bloques.map((b) => {
    const e = estadoDe(b);
    const desde = esFecha(b.inicio_real) ? b.inicio_real : e === 'listo' ? b.plan_desde : '';
    const hasta = esFecha(b.fin_real) ? b.fin_real : e === 'en_curso' ? hoy : '';
    return desde && hasta && hasta >= desde ? { desde, hasta } : null;
  });
  const fechas = [c.inicio, c.fin, hoy, ...bloques.flatMap((b) => [b.plan_desde, b.plan_hasta]), ...reales.filter(Boolean).flatMap((r) => [r.desde, r.hasta])]
    .filter(esFecha)
    .sort();
  const lunes0 = fechas.length ? lunesDe(fechas[0]) : '';
  const ultima = lunes0 ? semanaIndice(fechas[fechas.length - 1], lunes0) : NaN;
  const semanas = Number.isNaN(ultima) || ultima < 0 ? 1 : ultima + 1;
  const total = semanas * 7;
  const largo = semanas > 18;
  const pos = (desde, hasta) => {
    const ini = Math.max(0, Math.min(total, diasEntre(lunes0, desde)));
    const fin = Math.max(ini, Math.min(total, diasEntre(lunes0, hasta) + 1));
    return `left:${porcentaje(ini / total)};width:${porcentaje(Math.max(fin - ini, 0.35) / total)}`;
  };
  const cabecera = Array.from({ length: semanas }, (_, i) => {
    const f = lunes0 ? new Date(new Date(`${lunes0}T00:00:00Z`).getTime() + i * 7 * DIA_MS) : null;
    const etiqueta = !f ? '' : largo ? `${f.getUTCDate()}/${f.getUTCMonth() + 1}` : fechaCorta(f.toISOString().slice(0, 10));
    return `<span><b>S${i + 1}</b><small>${escapeHtml(etiqueta)}</small></span>`;
  }).join('');
  const filas = bloques
    .map((b, i) => {
      const r = reales[i];
      const e = estadoDe(b);
      return `<div class="crono-fila"><div class="crono-etiqueta"><i class="crono-punto is-${e}"></i><b title="${escapeHtml(texto(b.nombre))}">${escapeHtml(
        texto(b.nombre)
      )}</b></div><div class="crono-pista"><span class="crono-plan" style="${pos(b.plan_desde, b.plan_hasta)}" title="Planificado ${escapeHtml(
        rangoCorto(b.plan_desde, b.plan_hasta)
      )}"></span>${r ? `<span class="crono-real is-${e}" style="${pos(r.desde, r.hasta)}" title="Real ${escapeHtml(rangoCorto(r.desde, r.hasta))}"></span>` : ''}</div></div>`;
    })
    .join('');
  const linea = lunes0 && hoy ? `<span class="crono-hoy" style="left:${porcentaje((diasEntre(lunes0, hoy) + 0.5) / total)}"><em>Hoy</em></span>` : '';
  const fila = Math.max(26, Math.min(56, Math.floor(620 / Math.max(1, bloques.length))));
  return `
    <section class="slide plan-panel estado-crono">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      ${cabeza(index, 'Cronograma', 'Planificado y real')}
      <div class="crono${largo ? ' is-largo' : ''}" style="--semanas:${semanas};--fila:${fila}px">
        <div class="crono-cabecera"><span class="crono-esquina">Semana</span><div class="crono-semanas">${cabecera}</div></div>
        <div class="crono-cuerpo"><div class="crono-guias" aria-hidden="true">${linea}</div>${filas || '<p class="estado-vacio">Las fechas aparecen aquí cuando el plan tenga actividades.</p>'}</div>
        <div class="crono-pie"><span><i class="crono-muestra is-plan"></i>Planificado</span><span><i class="crono-muestra is-real"></i>Real</span><span><i class="crono-muestra is-curso"></i>En curso</span>${
          hoy ? `<span class="crono-nota">Fechas reales al ${escapeHtml(fechaLarga(hoy))}.</span>` : ''
        }</div>
      </div>
    </section>`;
}

/** Fin planificado de los bloques que cubre el cierre (el último plan_hasta). */
function planificadoDe(cierre, bloques) {
  const nombres = cadenas(cierre.bloques);
  return (
    objetos(bloques)
      .filter((b) => nombres.includes(b.nombre) && esFecha(b.plan_hasta))
      .map((b) => b.plan_hasta)
      .sort()
      .pop() || ''
  );
}

function lineaFechas(cierre, bloques) {
  const plan = planificadoDe(cierre, bloques);
  const real = esFecha(cierre.fecha_real) ? cierre.fecha_real : '';
  if (plan && real) {
    const dif = diasEntre(plan, real);
    const comp =
      dif < 0 ? `${plural(-dif, 'día', 'días')} antes de lo planificado` : dif > 0 ? `${plural(dif, 'día', 'días')} después de lo planificado` : 'el día planificado';
    return `Lo teníamos planificado para el ${fechaLarga(plan)} y quedó listo el ${fechaLarga(real)} (${comp}).`;
  }
  return real ? `Quedó listo el ${fechaLarga(real)}.` : '';
}

function renderPuntos(parte) {
  const puntos = cadenas(parte && parte.puntos);
  const vis = puntos.slice(0, PUNTOS_VISIBLES);
  if (!vis.length) return '';
  const mas = puntos.length > vis.length ? `<li class="cierre-mas">y ${plural(puntos.length - vis.length, 'punto más', 'puntos más')}</li>` : '';
  return `<ul class="cierre-puntos">${vis.map((p) => `<li>${escapeHtml(p)}</li>`).join('')}${mas}</ul>`;
}

function renderActividades(durante) {
  const acts = objetos(durante && durante.actividades);
  const vis = acts.slice(0, ACTIVIDADES_VISIBLES);
  if (!vis.length) return '';
  const fila = (a) => {
    const fechas = [esFecha(a.plan_hasta) ? `plan ${fechaCorta(a.plan_hasta)}` : '', esFecha(a.real) ? `real ${fechaCorta(a.real)}` : ''].filter(Boolean).join(' · ');
    return `<li><b title="${escapeHtml(texto(a.nombre))}">${escapeHtml(texto(a.nombre))}</b><small>${escapeHtml(fechas)}</small></li>`;
  };
  const mas = acts.length > vis.length ? `<li class="cierre-mas">y ${plural(acts.length - vis.length, 'actividad más', 'actividades más')}</li>` : '';
  return `<ul class="cierre-acts">${vis.map(fila).join('')}${mas}</ul>`;
}

/** Una evidencia como tarjeta; '' si no se puede mostrar (enlace o archivo que no es https). */
function renderEvidencia(e, cuando) {
  const t = escapeHtml(texto(e.titulo));
  const marca = cuando ? `<span class="ev-cuando">${cuando}</span>` : '';
  const fuente = [texto(e.fuente).trim(), esFecha(e.fecha) ? fechaLarga(e.fecha) : ''].filter(Boolean).join(' · ');
  const pie = fuente ? `<small class="ev-fuente">Fuente: ${escapeHtml(fuente)}</small>` : '';
  if (e.tipo === 'cifra') {
    if (!texto(e.valor).trim()) return '';
    return `<div class="ev ev-cifra">${marca}<b>${escapeHtml(texto(e.valor))}</b><span>${escapeHtml(texto(e.unidad))}</span><p>${t}</p>${pie}</div>`;
  }
  const url = urlSegura(e.url);
  if (!url) return '';
  if (e.tipo === 'captura') {
    return `<figure class="ev ev-captura">${marca}<a href="${escapeHtml(url)}" target="_blank" rel="noopener"><img src="${escapeHtml(url)}" alt="${t}" loading="lazy" /></a><figcaption>${t}</figcaption>${pie}</figure>`;
  }
  if (e.tipo === 'video') {
    const poster = urlSegura(e.poster);
    const p = poster ? ` poster="${escapeHtml(poster)}"` : '';
    // En el PDF un video no corre (R1): se cambia por el póster (si hay) y el enlace.
    return `<figure class="ev ev-video">${marca}<video controls preload="none" src="${escapeHtml(url)}"${p}></video><a class="ev-video-pdf" href="${escapeHtml(url)}">${
      poster ? `<img src="${escapeHtml(poster)}" alt="" />` : '<i aria-hidden="true">▶</i>'
    }<span>Ver el video</span></a><figcaption>${t}</figcaption>${pie}</figure>`;
  }
  if (e.tipo === 'enlace') {
    return `<a class="ev ev-enlace" href="${escapeHtml(url)}" target="_blank" rel="noopener">${marca}<b>${t}</b><small>${escapeHtml(url.replace(/^https:\/\//, ''))}</small>${pie}</a>`;
  }
  return '';
}

function evidenciasDe(cierre) {
  const de = (parte, cuando) => objetos(parte && parte.evidencias).map((e) => renderEvidencia(e, cuando)).filter(Boolean);
  return [...de(cierre.antes, 'Antes'), ...de(cierre.despues, 'Después')];
}

function renderCierreHito(d, cierre, index, foto, tarjetas) {
  const parrafo = (t) => (texto(t).trim() ? `<p class="hito-texto">${escapeHtml(texto(t))}</p>` : '');
  const col = (titulo, cuerpo) => `<div class="hito-col"><h3>${titulo}</h3>${cuerpo || '<p class="hito-texto">—</p>'}</div>`;
  const fechas = lineaFechas(cierre, d.bloques);
  const entrega = urlSegura(cierre.entregable_url);
  const antes = cierre.antes || {};
  const durante = cierre.durante || {};
  const despues = cierre.despues || {};
  return `
    <section class="slide plan-panel estado-hito${cierre.borrador ? ' is-borrador' : ''}">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      <div class="panel-head plan-anima"><p class="eyebrow">${numero(index)} · Terminamos${cierre.borrador ? ' · BORRADOR' : ''}</p><h2>${escapeHtml(
        texto(cierre.titulo)
      )}</h2>${fechas ? `<p class="hito-fechas">${escapeHtml(fechas)}</p>` : ''}</div>
      <div class="hito-cols">
        ${col('Antes', parrafo(antes.texto) + renderPuntos(antes))}
        ${col('Durante', parrafo(durante.texto) + renderActividades(durante))}
        ${col('Después', parrafo(despues.texto) + renderPuntos(despues))}
      </div>
      ${tarjetas.length ? `<div class="hito-evidencias">${tarjetas.join('')}</div>` : ''}
      ${entrega ? `<a class="hito-entrega" href="${escapeHtml(entrega)}" target="_top" rel="noopener">Ver la entrega y dejar comentarios</a>` : ''}
    </section>`;
}

function renderEvidenciasExtra(cierre, index, foto, tarjetas, parte, partes) {
  return `
    <section class="slide plan-panel estado-evidencias">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      ${cabeza(index, `Evidencias (${parte}/${partes})`, escapeHtml(texto(cierre.titulo)))}
      <div class="ev-grid">${tarjetas.join('')}</div>
    </section>`;
}

function renderCambios(d, index, foto) {
  const cambios = objetos(d.cambios_plan);
  const vis = cambios.slice(-CAMBIOS_VISIBLES);
  const items = vis
    .map(
      (c) =>
        `<li class="cambio"><small>${escapeHtml(esFecha(c.fecha) ? fechaLarga(c.fecha) : '')}</small><b title="${escapeHtml(texto(c.titulo))}">${escapeHtml(
          texto(c.titulo)
        )}</b>${texto(c.detalle).trim() ? `<p>${escapeHtml(texto(c.detalle))}</p>` : ''}</li>`
    )
    .join('');
  const antes = cambios.length - vis.length;
  return `
    <section class="slide plan-panel estado-cambios">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      ${cabeza(index, 'Cambios en el plan', 'Lo que acordamos cambiar')}
      <ul class="estado-lista">${items}</ul>
      ${antes > 0 ? `<p class="estado-nota">Y ${plural(antes, 'cambio anterior', 'cambios anteriores')}.</p>` : ''}
    </section>`;
}

function renderAlcance(d, index, foto) {
  const a = d.alcance && typeof d.alcance === 'object' ? d.alcance : {};
  const lista = (l, vacio) => (l.length ? `<ul>${l.map((x) => `<li>${escapeHtml(x)}</li>`).join('')}</ul>` : `<p class="estado-vacio">${vacio}</p>`);
  return `
    <section class="slide plan-panel estado-alcance">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      ${cabeza(index, 'Qué incluye', 'Lo que incluye tu proyecto')}
      <div class="alcance">
        <div><h3>Fase 1 · pago único</h3>${lista(cadenas(a.fase1), 'Lo detalla tu plan de trabajo.')}</div>
        <div><h3>Servicio mensual</h3>${lista(cadenas(a.mensual), 'Por ahora no hay servicio mensual.')}</div>
      </div>
    </section>`;
}

function renderSigue(d, index, foto) {
  const proximos = objetos(d.bloques).filter((b) => estadoDe(b) !== 'listo');
  const necesitamos = cadenas(d.necesitamos);
  const vp = proximos.slice(0, PROXIMOS_VISIBLES);
  const vn = necesitamos.slice(0, NECESITAMOS_VISIBLES);
  const sigue = vp.length
    ? `<ul>${vp
        .map(
          (b) =>
            `<li class="is-${estadoDe(b)}"><b title="${escapeHtml(texto(b.nombre))}">${escapeHtml(texto(b.nombre))}</b><small>${escapeHtml(
              estadoDe(b) === 'en_curso' ? 'En curso' : esFecha(b.plan_desde) && esFecha(b.plan_hasta) ? `Planificado ${rangoCorto(b.plan_desde, b.plan_hasta)}` : 'Por venir'
            )}</small></li>`
        )
        .join('')}${proximos.length > vp.length ? `<li class="cierre-mas">y ${plural(proximos.length - vp.length, 'parte más', 'partes más')}</li>` : ''}</ul>`
    : '<p class="estado-vacio">Terminamos todas las partes del plan.</p>';
  const nec = vn.length
    ? `<ul>${vn.map((x) => `<li>${escapeHtml(x)}</li>`).join('')}${necesitamos.length > vn.length ? `<li class="cierre-mas">y ${necesitamos.length - vn.length} más</li>` : ''}</ul>`
    : '<p class="estado-vacio">Por ahora nada: vamos al día.</p>';
  return `
    <section class="slide plan-panel estado-sigue">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      ${cabeza(index, 'Lo que sigue', 'Lo que viene y lo que necesitamos de ti')}
      <div class="sigue">
        <div><h3>Lo que sigue</h3>${sigue}</div>
        <div><h3>Lo que necesitamos de ti</h3>${nec}</div>
      </div>
    </section>`;
}

function renderCierreEstado(d, index, foto) {
  const agenda = d.agenda && typeof d.agenda === 'object' ? d.agenda : {};
  const whatsapp = urlSegura(agenda.whatsapp_url) || 'https://wa.me/56927002984';
  const web = urlSegura(agenda.web_url);
  const enlace = (href, icono, titulo, sub) =>
    `<a class="cierre-enlace" href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer"><span class="contact-icon" aria-hidden="true">${
      CONTACT_ICONS[icono] || ''
    }</span><span><b>${titulo}</b><small>${sub}</small></span></a>`;
  const enlaces = [
    enlace(whatsapp, 'whatsapp', 'Por WhatsApp con Tech', 'Escríbenos y agendamos contigo'),
    web ? enlace(web, 'web', 'En el sitio web', 'Elige el día y la hora que te acomoden') : '',
  ].join('');
  const fondo = foto
    ? ` style="background-image: linear-gradient(180deg, rgba(10,20,32,.62), rgba(10,20,32,.9)), url('${escapeHtml(foto)}'); background-size: cover; background-position: center;"`
    : '';
  return `
    <section class="slide slide-closing plan-cierre">
      <div class="closing-left"${fondo}>
        <a href="https://automatizatech.cl" target="_blank" rel="noopener noreferrer"><img class="closing-logo" src="${LOGO_URL}" alt="AutomatizaTech" /></a>
      </div>
      <div class="closing-right">
        <p class="eyebrow">${numero(index)} · Próximo paso</p>
        <h2>¿Revisamos juntos el avance?</h2>
        <p class="cierre-texto">Agenda una llamada de seguimiento y conversamos lo que viene.</p>
        <div class="cierre-enlaces">${enlaces}</div>
        <a class="pdf-button" href="presentation.pdf" download>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 11l5 5 5-5M4 19h16" /></svg>
          Descargar el estado en PDF
        </a>
      </div>
    </section>`;
}

const ESTILO_ESTADO = `
  .estado-portada h1.is-largo { font-size: 50px; }
  .estado-vacio { color: #c9d4e0; font-size: 22px; }
  .estado-fases { position: absolute; left: 72px; right: 72px; top: 196px; z-index: 2; display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); gap: 26px; }
  .estado-fase h3 { color: #00d9c0; font-size: 17px; letter-spacing: .14em; text-transform: uppercase; margin-bottom: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .estado-fase ol { list-style: none; display: flex; flex-direction: column; gap: 12px; }
  .estado-bloque { position: relative; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 2px 12px; padding: 10px 16px; overflow: hidden;
    background: rgba(6,13,21,.72); border: 1px solid rgba(255,255,255,.1); border-radius: 14px; }
  .estado-bloque b { grid-column: 1; grid-row: 1; color: #fff; font-size: 20px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .estado-bloque small { grid-column: 1 / -1; color: #9fb3c8; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .estado-chip { grid-column: 2; grid-row: 1; align-self: center; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 800;
    letter-spacing: .08em; text-transform: uppercase; background: rgba(255,255,255,.08); color: #c9d4e0; white-space: nowrap; }
  .estado-bloque.is-listo .estado-chip { background: rgba(0,217,192,.16); color: #00d9c0; }
  .estado-bloque.is-en_curso { border-color: rgba(0,217,192,.75); }
  .estado-bloque.is-en_curso .estado-chip { background: #00d9c0; color: #06222a; }
  .estado-bloque.is-aqui { padding-top: 26px; }
  .estado-aqui { position: absolute; top: 6px; left: 16px; color: #00d9c0; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
  .estado-plan { position: absolute; left: 72px; bottom: 60px; z-index: 2; display: flex; flex-direction: column; gap: 4px; padding: 16px 26px; border-radius: 16px;
    background: rgba(0,217,192,.12); border: 1px solid rgba(0,217,192,.55); color: #fff; text-decoration: none; }
  .estado-plan b { font-size: 22px; }
  .estado-plan small { color: #9fb3c8; font-size: 15px; }

  .estado-crono .crono { position: absolute; top: 196px; left: 72px; right: 72px; bottom: 50px; z-index: 2; display: flex; flex-direction: column; --col: 380px;
    background: rgba(6,13,21,.72); border: 1px solid rgba(255,255,255,.1); border-radius: 18px; padding: 18px 26px 14px; }
  .crono-cabecera { display: flex; align-items: flex-end; height: 50px; flex: none; border-bottom: 1px solid rgba(255,255,255,.14); }
  .crono-esquina { width: var(--col); flex: none; color: #9fb3c8; font-size: 15px; letter-spacing: .12em; text-transform: uppercase; padding-bottom: 10px; }
  .crono-semanas { flex: 1; display: grid; grid-template-columns: repeat(var(--semanas), 1fr); min-width: 0; }
  .crono-semanas span { display: flex; flex-direction: column; align-items: center; gap: 2px; padding-bottom: 8px; min-width: 0; overflow: hidden; }
  .crono-semanas b { color: #fff; font-size: 16px; }
  .crono-semanas small { color: #9fb3c8; font-size: 13px; white-space: nowrap; }
  .crono.is-largo .crono-semanas b { font-size: 12px; }
  .crono.is-largo .crono-semanas small { font-size: 10px; }
  .crono-cuerpo { position: relative; flex: 1; min-height: 0; }
  .crono-guias { position: absolute; top: 0; bottom: 0; left: var(--col); right: 0; pointer-events: none; z-index: 1; }
  .crono-hoy { position: absolute; top: 0; bottom: 0; width: 0; border-left: 2px dashed #f59e0b; }
  .crono-hoy em { position: absolute; top: 2px; left: 6px; color: #f59e0b; font-size: 12px; font-style: normal; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
  .crono-fila { display: flex; align-items: center; height: var(--fila); border-bottom: 1px solid rgba(255,255,255,.06); }
  .crono-etiqueta { width: var(--col); flex: none; display: flex; align-items: center; gap: 10px; min-width: 0; padding-right: 16px; }
  .crono-etiqueta b { color: #fff; font-size: 17px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .crono-punto { width: 12px; height: 12px; border-radius: 50%; flex: none; background: rgba(255,255,255,.3); }
  .crono-punto.is-listo { background: #00d9c0; }
  .crono-punto.is-en_curso { background: #f59e0b; }
  .crono-pista { position: relative; flex: 1; height: 100%; }
  .crono-plan, .crono-real { position: absolute; border-radius: 6px; }
  .crono-plan { top: 18%; height: 28%; background: rgba(255,255,255,.2); }
  .crono-real { top: 54%; height: 28%; background: #00d9c0; }
  .crono-real.is-en_curso { background: #f59e0b; }
  .crono-pie { display: flex; gap: 26px; align-items: center; flex: none; padding-top: 10px; color: #c9d4e0; font-size: 15px; }
  .crono-muestra { display: inline-block; width: 26px; height: 10px; border-radius: 4px; margin-right: 8px; vertical-align: middle; }
  .crono-muestra.is-plan { background: rgba(255,255,255,.2); }
  .crono-muestra.is-real { background: #00d9c0; }
  .crono-muestra.is-curso { background: #f59e0b; }
  .crono-nota { margin-left: auto; color: #9fb3c8; }

  .estado-hito .panel-head, .estado-evidencias .panel-head, .estado-cambios .panel-head, .estado-alcance .panel-head, .estado-sigue .panel-head { right: 120px; }
  .estado-hito .panel-head h2, .estado-evidencias .panel-head h2 { font-size: 40px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .hito-fechas { color: #c9d4e0; font-size: 18px; margin-top: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .hito-cols { position: absolute; left: 72px; right: 72px; top: 268px; height: 392px; z-index: 2; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; }
  .hito-col { display: flex; flex-direction: column; gap: 10px; padding: 16px 22px; overflow: hidden;
    background: rgba(6,13,21,.72); border: 1px solid rgba(255,255,255,.1); border-radius: 16px; }
  .hito-col h3 { color: #00d9c0; font-size: 16px; letter-spacing: .14em; text-transform: uppercase; flex: none; }
  .hito-texto { color: #dbe4ee; font-size: 17px; line-height: 1.45; flex: none; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
  .cierre-puntos, .cierre-acts { list-style: none; display: flex; flex-direction: column; gap: 6px; min-height: 0; }
  .cierre-puntos li { position: relative; padding-left: 18px; color: #fff; font-size: 16px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .cierre-puntos li::before { content: ''; position: absolute; left: 0; top: 8px; width: 8px; height: 8px; border-radius: 50%; background: #00d9c0; }
  .cierre-acts li { display: flex; justify-content: space-between; gap: 12px; font-size: 15px; }
  .cierre-acts b { color: #fff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .cierre-acts small { color: #9fb3c8; white-space: nowrap; }
  li.cierre-mas { color: #9fb3c8; font-size: 14px; padding-left: 0; }
  li.cierre-mas::before { display: none; }
  .hito-evidencias { position: absolute; left: 72px; right: 72px; top: 680px; height: 316px; z-index: 2; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 20px; }
  .hito-entrega { position: absolute; right: 72px; bottom: 26px; z-index: 2; color: #00d9c0; font-size: 17px; font-weight: 700; }
  .ev-grid { position: absolute; left: 72px; right: 72px; top: 230px; bottom: 50px; z-index: 2; display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr)); grid-template-rows: repeat(2, minmax(0, 1fr)); gap: 20px; }
  .ev { display: flex; flex-direction: column; gap: 8px; min-width: 0; height: 100%; padding: 14px; overflow: hidden; color: #fff; text-decoration: none;
    background: rgba(6,13,21,.8); border: 1px solid rgba(255,255,255,.12); border-radius: 14px; }
  .ev-cuando { color: #9fb3c8; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; flex: none; }
  .ev-cifra b { color: #00d9c0; font-size: 60px; line-height: 1; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .ev-cifra span { color: #c9d4e0; font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .ev p, .ev figcaption, .ev-enlace b { font-size: 16px; font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; flex: none; }
  .ev-fuente { margin-top: auto; color: #9fb3c8; font-size: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .ev-captura img, .ev-video video, .ev-video-pdf img { display: block; width: 100%; height: 150px; object-fit: cover; border-radius: 10px; background: #0a1420; flex: none; }
  .ev-video-pdf { display: none; position: relative; color: #fff; text-decoration: none; flex: none; }
  .ev-video-pdf i { display: flex; align-items: center; justify-content: center; width: 100%; height: 150px; border-radius: 10px; background: #0a1420; font-size: 48px; font-style: normal; color: #00d9c0; }
  .ev-video-pdf span { position: absolute; left: 10px; bottom: 10px; padding: 4px 10px; border-radius: 999px; background: #00d9c0; color: #06222a; font-size: 13px; font-weight: 800; }
  .ev-enlace small { color: #00d9c0; font-size: 13px; word-break: break-all; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  @media print { .ev-video video { display: none; } .ev-video-pdf { display: block; } }

  .estado-lista { position: absolute; left: 72px; right: 72px; top: 220px; z-index: 2; list-style: none; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 28px; }
  .cambio { display: flex; flex-direction: column; gap: 4px; height: 168px; padding: 14px 20px; overflow: hidden;
    background: rgba(6,13,21,.72); border: 1px solid rgba(255,255,255,.1); border-radius: 14px; }
  .cambio small { color: #00d9c0; font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
  .cambio b { color: #fff; font-size: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .cambio p { color: #c9d4e0; font-size: 15px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .estado-nota { position: absolute; left: 72px; bottom: 34px; z-index: 2; color: #9fb3c8; font-size: 16px; }

  .alcance, .sigue { position: absolute; left: 72px; right: 72px; top: 220px; bottom: 56px; z-index: 2; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 28px; }
  .alcance > div, .sigue > div { padding: 22px 26px; overflow: hidden; background: rgba(6,13,21,.72); border: 1px solid rgba(255,255,255,.1); border-radius: 16px; }
  .alcance h3, .sigue h3 { color: #00d9c0; font-size: 17px; letter-spacing: .14em; text-transform: uppercase; margin-bottom: 16px; }
  .alcance ul { list-style: none; columns: 2; column-gap: 24px; }
  .alcance li { break-inside: avoid; position: relative; padding-left: 18px; margin-bottom: 10px; color: #fff; font-size: 16px; line-height: 1.35;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .alcance li::before { content: ''; position: absolute; left: 0; top: 8px; width: 8px; height: 8px; border-radius: 50%; background: #00d9c0; }
  .sigue ul { list-style: none; display: flex; flex-direction: column; gap: 12px; }
  .sigue li { display: flex; flex-direction: column; gap: 2px; color: #fff; font-size: 18px; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .sigue li b { font-size: 19px; }
  .sigue li small { display: block; color: #9fb3c8; font-size: 15px; }
  .sigue li.is-en_curso small { color: #f59e0b; }
`;

function renderEstadoHtml(data, images = {}) {
  const d = data && typeof data === 'object' ? data : {};
  const fotos = images && typeof images === 'object' ? images : {};
  const slides = [renderPortadaEstado(d, fotos.cover)];
  const usadas = [fotos.cover];
  let n = 1;
  const agregar = (html, foto) => {
    slides.push(html);
    usadas.push(foto);
  };
  n += 1;
  agregar(renderDondeVamos(d, n, fotos.metodo), fotos.metodo);
  n += 1;
  agregar(renderCronogramaReal(d, n, fotos.gantt), fotos.gantt);
  objetos(d.cierres).forEach((cierre, i) => {
    const foto = fotos[`fase_${(i % 3) + 1}`];
    const tarjetas = evidenciasDe(cierre);
    n += 1;
    agregar(renderCierreHito(d, cierre, n, foto, tarjetas.slice(0, EVIDENCIAS_EN_CIERRE)), foto);
    const resto = tarjetas.slice(EVIDENCIAS_EN_CIERRE);
    const partes = Math.ceil(resto.length / EVIDENCIAS_POR_LAMINA);
    for (let p = 0; p < partes; p++) {
      n += 1;
      agregar(renderEvidenciasExtra(cierre, n, foto, resto.slice(p * EVIDENCIAS_POR_LAMINA, (p + 1) * EVIDENCIAS_POR_LAMINA), p + 1, partes), foto);
    }
  });
  if (objetos(d.cambios_plan).length) {
    n += 1;
    agregar(renderCambios(d, n, fotos.portal), fotos.portal);
  }
  n += 1;
  agregar(renderAlcance(d, n, fotos.reuniones), fotos.reuniones);
  n += 1;
  agregar(renderSigue(d, n, fotos.necesitamos), fotos.necesitamos);
  n += 1;
  agregar(renderCierreEstado(d, n, fotos.cierre), fotos.cierre);

  const preload = [...new Set(usadas.filter(Boolean))].map((url) => `<link rel="preload" as="image" href="${escapeHtml(url)}" />`).join('\n');
  return `<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<title>Estado del proyecto · ${escapeHtml(texto(d.proyecto))}</title>
${preload}
<style>${STYLE}${ESTILO_PORTADA}${ESTILO_GANTT}${ESTILO_CIERRE}${ESTILO_ESTADO}</style>
</head>
<body${d.draft ? ' class="is-draft"' : ''}>
${d.draft ? '<div class="at-draft-badge">Vista previa con borradores · el cliente no la ve</div>' : ''}
<div class="deck">
${slides.join('\n')}
</div>
${renderDeckControls()}
<script>${SCRIPT}</script>
</body>
</html>`;
}

module.exports = { renderEstadoHtml, lineaFechas, fechasBloque, renderEvidencia };
```

- [ ] **Step 6: El servidor despacha `estado`**

En `renderer/src/server.js`:

1. Línea 4: `const { validatePayload, validatePlanPayload, validateEstadoPayload } = require('./schema');` y, junto al `require` de `renderPlanHtml`, agregar `const { renderEstadoHtml } = require('./template-estado');`.
2. Reemplazar las líneas 42-44 por:

```js
    // Un plan de trabajo llega con document_type 'plan' y «Estado del proyecto» con 'estado'; sin ese campo es una
    // propuesta, como siempre.
    const tipo = req.body && typeof req.body === 'object' ? req.body.document_type : '';
    const esPlan = tipo === 'plan';
    const esEstado = tipo === 'estado';
    const { valid, errors } = esEstado ? validateEstadoPayload(req.body) : esPlan ? validatePlanPayload(req.body) : validatePayload(req.body);
```

3. Reemplazar `const imageBriefs = Array.isArray(data.image_briefs) ? data.image_briefs : [];` por:

```js
    // «Estado del proyecto» nunca pide fotos (D9): solo usa las que llegan en data.images (las del plan).
    const imageBriefs = !esEstado && Array.isArray(data.image_briefs) ? data.image_briefs : [];
```

4. Reemplazar `html = esPlan ? renderPlanHtml(data, images) : renderProposalHtml(data, images);` por:

```js
      html = esEstado ? renderEstadoHtml(data, images) : esPlan ? renderPlanHtml(data, images) : renderProposalHtml(data, images);
```

- [ ] **Step 7: Correr las pruebas y ver que pasan**

Run: `cd renderer && node --test test/template-estado.test.js test/server-estado.test.js test/template-estado-layout.test.js`
Expected: todas pasan. Si la prueba de diseño lista elementos fuera de la lámina, ajusta el CSS de `ESTILO_ESTADO` (alturas, `-webkit-line-clamp`) hasta que la lista quede vacía; no relajes la prueba.

Run después la suite completa: `cd renderer && npm test`. Expected: todo pasa, incluidas `template-propuesta-igual.test.js` y las del plan (las exportaciones nuevas no cambian su HTML).

- [ ] **Step 8: Commit**

```bash
git add renderer/src/template-estado.js renderer/src/schema.js renderer/src/server.js renderer/src/template-plan.js renderer/test/fixtures/estado-ejemplo.js renderer/test/template-estado.test.js renderer/test/template-estado-layout.test.js renderer/test/server-estado.test.js
git commit -m "feat(renderer): presentación «Estado del proyecto» (document_type estado)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---
### Task 5: WordPress — cuerpo de «Estado del proyecto», rutas REST con `modo=estado` y pedido del render

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php` (agregar al final `at_av_evidencias_render` y `at_av_armar_render_estado`)
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-render.php`
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php` (agregar `at_pt_uid_propuesta`)
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php` (`at_pt_rest_render` y `at_pt_rest_vista`, docblock del archivo)
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php`
- Test: `tests/plan/avance-render-test.php` (sin WordPress)
- Test: `tests/plan/rest-estado-wp-test.php`

**Interfaces:**
- Consumes: Task 1 (`at_av_*` puras), Task 2 (`at_av_leer`, `at_av_guardar`, `at_av_guardar_render`, `at_av_columna_lista`); del plan `at_pt_armar_render(array $plan, array $datos, bool $final): array`, `at_pt_url_ver_plan(string $base, string $codigo, bool $agendar=false): string`, `at_pt_datos_render(object $fila): array`, `at_pt_payload(object): array`, `at_pt_db_partes(object): array`, `at_pt_host_renderer(): string`, `at_pt_llamar_n8n(string $url, array $cuerpo, int $timeout=15): string`, `AT_N8N_PLAN_RENDER`; de entregables `at_en_por_id(int): ?object` (columna `codigo`) y `at_en_url_pagina(string $base, string $codigo): string`.
- Produces:
  - Puras: `at_av_evidencias_render(array $avance, array $ids): array`; `at_av_armar_render_estado(array $plan, array $avance, array $datos, bool $previa, string $hoy): array` (contrato de la Task 4).
  - `at_pt_uid_propuesta(object $f): string`.
  - `at_av_token_previa(string $codigo): string` (10 hex); `at_av_uid_render(string $codigo, bool $previa): string`; `at_av_url_render_valida(string $u, string $codigo, bool $previa): string`; `at_av_urls_entregables(array $avance): array`.
  - `at_av_rest_render_estado(object $f, bool $previa)` → `WP_REST_Response` `{ok, render, plan_codigo, propuesta_uid, crm_cliente_id, estado}` o `WP_Error` 409.
  - `at_av_rest_vista_estado(object $f, array $p): WP_REST_Response`.
  - `at_av_pedir_render(int $plan_id, bool $previa = false): string` ('' o motivo); `at_av_refrescar(int $plan_id): string`; `at_av_vista_publica(object $fila): string`.
  - Webhook a n8n (lo consume la Task 6): `{id, codigo, modo: 'estado'}` y, solo para la vista previa, `previa: true`.

- [ ] **Step 1: Escribir las pruebas que fallan**

Crear `tests/plan/avance-render-test.php`:

```php
<?php
// Correr: php tests/plan/avance-render-test.php   (sin WordPress)
// Estado del proyecto, Etapa 2 — Task 5: cuerpo para el renderer (document_type estado). Datos inventados.
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php';
$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

$act = fn(string $n, string $d, string $h) => ['nombre' => $n, 'responsable' => 'at', 'dias_habiles' => 2, 'desde' => $d, 'hasta' => $h];
$plan = [
	'proyecto' => '[PRUEBA] Sitio de la panadería',
	'fases' => [
		['clave' => 'diseno_desarrollo', 'bloques' => [
			['nombre' => 'Diseño', 'entregable' => 'Maqueta aprobada', 'actividades' => [$act('Maqueta', '2026-10-05', '2026-10-09')]],
			['nombre' => 'Desarrollo', 'entregable' => 'Sitio en pruebas', 'actividades' => [$act('Construcción', '2026-10-12', '2026-10-21')]],
		]],
		['clave' => 'implementacion', 'bloques' => [['nombre' => 'Puesta en marcha', 'entregable' => 'Sitio publicado', 'actividades' => [$act('Publicación', '2026-10-22', '2026-10-23')]]]],
	],
	'cronograma' => ['inicio' => '2026-10-05', 'fin' => '2026-10-23', 'semanas' => 3, 'barras' => [], 'hitos' => []],
	'soporte' => ['garantia_meses' => 3, 'mensuales' => ['Mantención mensual del sitio']],
];
$datos = ['codigo' => 'AbCdEf123456', 'company_name' => '[PRUEBA] Panadería', 'client_name' => 'Cliente Prueba', 'portal_url' => '', 'whatsapp' => '56900000000',
	'fecha_firma' => '2026-10-01', 'garantia_meses' => 3, 'sitio' => 'https://automatizatech.cl', 'previa_token' => 'abc123def4',
	'entregables' => [3 => 'https://automatizatech.cl/ver-entregable.php?id=XyZ123456789']];
$av = at_av_vacio();
$av['cierres'] = [
	['id' => 'c111111', 'titulo' => 'Sitio web', 'bloques' => ['Diseño', 'Desarrollo'], 'fecha_real' => '2026-10-10', 'estado' => 'publicado', 'publicado_at' => '2026-10-13 10:00:00', 'entregable_id' => 3,
		'antes' => ['texto' => 'Antes.', 'puntos' => ['a'], 'evidencias' => ['c111111-lh']],
		'durante' => ['texto' => 'Durante.', 'actividades' => [['bloque' => 'Diseño', 'nombre' => 'Maqueta', 'plan_desde' => '2026-10-05', 'plan_hasta' => '2026-10-09', 'real' => '2026-10-08', 'nota' => '']]],
		'despues' => ['texto' => 'Después.', 'puntos' => [], 'evidencias' => ['c111111-portada', 'c111111-pendiente', 'c111111-interno', 'c111111-sitio']]],
	['id' => 'c222222', 'titulo' => 'Borrador secreto', 'bloques' => ['Puesta en marcha'], 'fecha_real' => '2026-10-05', 'estado' => 'borrador', 'publicado_at' => '', 'entregable_id' => 0,
		'antes' => ['texto' => 'x', 'puntos' => [], 'evidencias' => []], 'durante' => ['texto' => '', 'actividades' => []], 'despues' => ['texto' => '', 'puntos' => [], 'evidencias' => []]],
];
$av['evidencias'] = [
	'c111111-lh' => ['tipo' => 'cifra', 'titulo' => 'Lighthouse', 'url' => '', 'poster' => '', 'valor' => '98', 'unidad' => 'puntos', 'fuente' => 'PageSpeed', 'fecha' => '2026-10-13'],
	'c111111-portada' => ['tipo' => 'captura', 'titulo' => 'Portada', 'url' => 'https://automatizatech.cl/wp-content/uploads/at-estado/7/aaaaaaaaaaaaaaaaaaaa.png', 'poster' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => ''],
	'c111111-pendiente' => ['tipo' => 'video', 'titulo' => 'Sin subir', 'url' => '', 'poster' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => ''],
	'c111111-interno' => ['tipo' => 'enlace', 'titulo' => 'Interno', 'url' => 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/x/', 'poster' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => ''],
	'c111111-sitio' => ['tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'https://example.com/', 'poster' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => ''],
];
$av['necesitamos'] = [['texto' => 'Logo', 'estado' => 'abierto', 'fecha' => '2026-10-01'], ['texto' => 'Dominio', 'estado' => 'resuelto', 'fecha' => '2026-10-01']];

$r = at_av_armar_render_estado($plan, $av, $datos, false, '2026-10-14');
$json = (string) json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
ok($r['document_type'] === 'estado' && $r['unique_id'] === 'AbCdEf123456-estado' && $r['draft'] === false && $r['actualizado'] === '2026-10-14', 'tipo estado, código del plan + «-estado», sin borradores');
ok(array_column($r['cierres'], 'titulo') === ['Sitio web'] && !str_contains($json, 'Borrador secreto'), 'solo cierres publicados: el borrador no llega (Review Focus 2)');
$c = $r['cierres'][0];
ok($c['borrador'] === false && $c['entregable_url'] === 'https://automatizatech.cl/ver-entregable.php?id=XyZ123456789', 'cierre con el enlace de su entrega');
ok(array_column($c['despues']['evidencias'], 'titulo') === ['Portada', 'Sitio'] && $c['antes']['evidencias'][0]['valor'] === '98', 'evidencias sin archivo o con enlace interno no salen (D8); la cifra sí');
ok(!str_contains($json, 'easypanel'), 'ningún enlace al servidor interno en el cuerpo');
ok(array_column($r['bloques'], 'estado') === ['listo', 'listo', 'por_venir'] && $r['bloques'][0]['fin_real'] === '2026-10-10', 'bloques con su estado (el borrador no marca nada)');
ok($r['necesitamos'] === ['Logo'], 'necesitamos: solo lo abierto, como texto');
ok($r['alcance'] === ['fase1' => ['Maqueta aprobada', 'Sitio en pruebas', 'Sitio publicado'], 'mensual' => ['Mantención mensual del sitio']], 'alcance vacío: entregables del plan y servicios mensuales del plan');
$av2 = $av;
$av2['alcance'] = ['fase1' => ['Sitio web'], 'mensual' => ['Publicidad']];
ok(at_av_armar_render_estado($plan, $av2, $datos, false, '2026-10-14')['alcance'] === ['fase1' => ['Sitio web'], 'mensual' => ['Publicidad']], 'alcance cargado: manda el del avance');
ok($r['plan_url'] === 'https://automatizatech.cl/ver-plan.php?id=AbCdEf123456' && str_contains($r['agenda']['web_url'], 'agendar=1') && $r['company_name'] === '[PRUEBA] Panadería', 'enlaces al plan y a la agenda por automatizatech.cl');
ok($r['image_briefs'] === [] && $r['images'] instanceof stdClass && str_contains($json, '"images":{}'), 'sin fotos nuevas (D9); images sale como objeto JSON');
ok($r['cronograma'] === ['inicio' => '2026-10-05', 'fin' => '2026-10-23', 'semanas' => 3], 'cronograma: inicio, fin y semanas del plan');
$p = at_av_armar_render_estado($plan, $av, $datos, true, '2026-10-14');
ok($p['unique_id'] === 'AbCdEf123456-previa-abc123def4' && $p['draft'] === true && array_column($p['cierres'], 'titulo') === ['Borrador secreto', 'Sitio web'] && $p['cierres'][0]['borrador'] === true, 'vista previa: también borradores, marcados, en orden de fecha y en su propia ruta (R3)');
fin();
```

Crear `tests/plan/rest-estado-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/rest-estado-wp-test.php
// Estado del proyecto, Etapa 2 — Task 5: GET /plan/{id}/render&modo=estado, POST /plan/{id}/vista con modo estado,
// at_av_pedir_render, at_av_refrescar y at_av_vista_publica (Review Focus 2 y 5).
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_av_rest_render_estado', 'at_av_rest_vista_estado', 'at_av_pedir_render', 'at_av_refrescar', 'at_av_vista_publica', 'at_av_url_render_valida', 'at_av_token_previa', 'at_pt_uid_propuesta');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba.\n");
	exit(2);
}
at_av_migrar_esquema();
if (!at_av_columna_lista()) {
	fwrite(STDERR, "La columna avance no está lista en el sitio de prueba.\n");
	exit(2);
}
function pedir(string $metodo, string $ruta, $cuerpo = null, array $query = []): WP_REST_Response {
	$r = new WP_REST_Request($metodo, '/automatiza-tech/v1' . $ruta);
	$r->set_header('x-at-secret', AT_REST_SECRET);
	if ($query) {
		$r->set_query_params($query);
	}
	if ($cuerpo !== null) {
		$r->set_header('content-type', 'application/json');
		$r->set_body(wp_json_encode($cuerpo));
	}
	return rest_do_request($r);
}
/** Importa y aplica un cierre (solo cifras y enlaces: sin archivos que subir). */
function con_cierre(array $avance, array $plan, string $codigo, string $titulo, string $bloque): array {
	$d = ['formato' => 'at-cierre-v1', 'plan' => $codigo, 'cierre' => ['titulo' => $titulo, 'bloques' => [$bloque], 'fecha_real' => '2026-10-10',
		'antes' => ['texto' => 'Antes de ' . $titulo, 'evidencias' => ['c']], 'despues' => ['texto' => 'Después.', 'evidencias' => ['e']]],
		'evidencias' => [['ref' => 'c', 'tipo' => 'cifra', 'titulo' => 'Visitas', 'valor' => '10', 'fuente' => 'GA4', 'fecha' => '2026-10-09'],
			['ref' => 'e', 'tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'https://example.com/']]];
	$v = at_av_validar_import($d, $codigo, $plan, $avance);
	if (!$v['ok']) {
		fwrite(STDERR, 'El cierre de prueba no valida: ' . implode(' | ', $v['errores']) . "\n");
		exit(2);
	}
	return at_av_aplicar_import($avance, $v, 'c' . substr(md5($titulo), 0, 6), '2026-10-13 10:00:00');
}

$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-rest-estado@example.com');
$plan = ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-rest-estado@example.com'));
$id = (int) $plan->id;
ptc_sembrar($id);
$f = at_pt_plan($id);
$cod = (string) $f->codigo;
$payload = at_pt_payload($f);

ok(pedir('GET', "/plan/{$id}/render", null, ['modo' => 'estado'])->get_status() === 409, '1) plan que no está enviado: 409');
ptc_estado($id, 'enviado');
at_pt_guardar($id, ['view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $cod . '/index.html']);
$vista_plan = (string) at_pt_plan($id)->view_url;
$r = pedir('GET', "/plan/{$id}/render", null, ['modo' => 'estado']);
$d = $r->get_data();
ok($r->get_status() === 200 && array_keys($d) === ['ok', 'render', 'plan_codigo', 'propuesta_uid', 'crm_cliente_id', 'estado'], '2) enviado: 200 con render, código del plan, propuesta, ficha y estado');
ok(($d['render']['document_type'] ?? '') === 'estado' && ($d['render']['unique_id'] ?? '') === $cod . '-estado' && ($d['render']['cierres'] ?? null) === [] && $d['plan_codigo'] === $cod, '2) sin cierres: cuerpo de estado vacío de cierres');
ok(pedir('GET', "/plan/{$id}/render", null, ['modo' => 'draft'])->get_data()['render']['document_type'] === 'plan', '2) el render del plan sigue igual');
ok(pedir('GET', "/plan/{$id}/render", null, ['modo' => 'otro'])->get_status() === 400, '2) modo desconocido: 400');

$a = con_cierre(at_av_vacio(), $payload, $cod, 'Borrador secreto', 'Desarrollo')['avance'];
$a = con_cierre($a, $payload, $cod, 'Diseño terminado', 'Diseño')['avance'];
$id_pub = $a['cierres'][1]['id'];
$a = at_av_cambiar_estado_cierre($a, $id_pub, 'publicado', '2026-10-13 11:00:00');
ok(is_array($a) && at_av_guardar($id, $a), '3) avance con un borrador y un publicado');
$d = pedir('GET', "/plan/{$id}/render", null, ['modo' => 'estado'])->get_data();
ok(array_column($d['render']['cierres'], 'titulo') === ['Diseño terminado'] && !str_contains((string) wp_json_encode($d), 'Borrador secreto'), '3) el cuerpo público no trae el borrador (Review Focus 2)');
$d = pedir('GET', "/plan/{$id}/render", null, ['modo' => 'estado', 'previa' => '1'])->get_data();
ok(count($d['render']['cierres']) === 2 && $d['render']['unique_id'] === $cod . '-previa-' . at_av_token_previa($cod) && $d['render']['draft'] === true, '3) la vista previa trae los dos, en su ruta con token (R3)');
ok(preg_match('/^[0-9a-f]{10}$/', at_av_token_previa($cod)) && at_av_token_previa($cod) === at_av_token_previa($cod), '3) token de 10, fijo por plan');

$vista = 'https://' . at_pt_host_renderer() . '/p/' . $cod . '-estado/index.html';
$pdf = 'https://' . at_pt_host_renderer() . '/p/' . $cod . '-estado/presentation.pdf';
$r = pedir('POST', "/plan/{$id}/vista", ['modo' => 'estado', 'ok' => true, 'view_url' => $vista, 'pdf_url' => $pdf, 'nota' => 'Estado del proyecto actualizado']);
$l = at_av_leer(at_pt_plan($id));
ok($r->get_status() === 200 && $l['render']['estado'] === 'listo' && $l['render']['view_url'] === $vista && $l['render']['pdf_url'] === $pdf && $l['render']['nota'] === 'Estado del proyecto actualizado', '4) /vista estado: guarda la presentación lista');
ok((string) at_pt_plan($id)->estado === 'enviado' && (string) at_pt_plan($id)->view_url === $vista_plan, '4) el plan sigue enviado y con su propia vista');
ok(at_av_vista_publica(at_pt_plan($id)) === $vista, '4) ver-estado mostrará esa presentación');
pedir('POST', "/plan/{$id}/vista", ['modo' => 'estado', 'ok' => false, 'nota' => 'el renderer no respondió (HTTP 502)']);
$l = at_av_leer(at_pt_plan($id));
ok($l['render']['estado'] === 'error' && $l['render']['view_url'] === $vista && str_contains($l['render']['nota'], '502') && at_av_vista_publica(at_pt_plan($id)) === $vista, '5) un render fallido conserva la última presentación lista (Review Focus 5)');
foreach (['https://evil.example/p/' . $cod . '-estado/index.html', 'https://' . at_pt_host_renderer() . '/p/' . $cod . '-previa-' . at_av_token_previa($cod) . '/index.html', 'https://' . at_pt_host_renderer() . '/p/OTROplan0001-estado/index.html'] as $mala) {
	pedir('POST', "/plan/{$id}/vista", ['modo' => 'estado', 'ok' => true, 'view_url' => $mala]);
	ok(at_av_leer(at_pt_plan($id))['render']['view_url'] === $vista && at_av_leer(at_pt_plan($id))['render']['estado'] === 'error', '6) un enlace ajeno, de la vista previa o de otro plan no se guarda como público');
}
$prev = 'https://' . at_pt_host_renderer() . '/p/' . $cod . '-previa-' . at_av_token_previa($cod) . '/index.html';
pedir('POST', "/plan/{$id}/vista", ['modo' => 'estado', 'previa' => true, 'ok' => true, 'view_url' => $prev]);
$l = at_av_leer(at_pt_plan($id));
ok($l['render_previa']['estado'] === 'listo' && $l['render_previa']['view_url'] === $prev && $l['render']['view_url'] === $vista, '7) la vista previa va a su propio registro');

$solo_borrador = at_av_cambiar_estado_cierre($l, $id_pub, 'borrador', '2026-10-13 12:00:00');
at_av_guardar($id, $solo_borrador);
ok(at_av_vista_publica(at_pt_plan($id)) === '', '8) sin cierres publicados ver-estado no muestra la presentación aunque haya una lista (Review Focus 2)');
ok(at_av_refrescar($id) === '' && at_av_leer(at_pt_plan($id))['render']['estado'] === 'error', '8) sin publicados no se pide render (R4)');
at_av_guardar($id, at_av_cambiar_estado_cierre(at_av_leer(at_pt_plan($id)), $id_pub, 'publicado', '2026-10-13 12:05:00'));
// En esta prueba toda llamada HTTP sale cortada (fixtures-panel.php): es n8n caído.
$motivo = at_av_pedir_render($id);
$l = at_av_leer(at_pt_plan($id));
ok($motivo !== '' && $l['render']['estado'] === 'error' && str_starts_with($l['render']['nota'], 'No se pudo pedir la presentación') && $l['render']['view_url'] === $vista, '9) n8n caído: queda en error con el motivo y conserva la última presentación (Review Focus 5)');
ok(at_av_refrescar($id) !== '', '9) con publicados, refrescar sí pide el render');
fin();
```

- [ ] **Step 2: Correr las pruebas y ver que fallan**

Run:
```bash
/c/wamp64/bin/php/php8.4.15/php.exe tests/plan/avance-render-test.php
AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/rest-estado-wp-test.php
```
Expected: la primera, «Call to undefined function at_av_armar_render_estado()»; la segunda, «Faltan funciones: …».

- [ ] **Step 3: El cuerpo de estado (funciones puras)**

Al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php`, agregar:

```php
/* ---------- Cuerpo para el renderer (Task 5) ---------- */

/** Evidencias de un cierre para el renderer. No salen las de captura o video sin archivo, ni un enlace que no pase
 *  at_av_url_publica (D8). */
function at_av_evidencias_render(array $avance, array $ids): array {
	$out = [];
	foreach ($ids as $id) {
		$e = $avance['evidencias'][$id] ?? null;
		if (!is_array($e) || !in_array($e['tipo'] ?? '', AT_AV_TIPOS, true)) {
			continue;
		}
		$url = $e['tipo'] === 'cifra' ? '' : at_av_url_publica($e['url'] ?? '');
		if ($e['tipo'] !== 'cifra' && $url === '') {
			continue;
		}
		$out[] = ['tipo' => (string) $e['tipo'], 'titulo' => (string) ($e['titulo'] ?? ''), 'url' => $url, 'poster' => at_av_url_publica($e['poster'] ?? ''),
			'valor' => (string) ($e['valor'] ?? ''), 'unidad' => (string) ($e['unidad'] ?? ''), 'fuente' => (string) ($e['fuente'] ?? ''), 'fecha' => (string) ($e['fecha'] ?? '')];
	}
	return $out;
}

/**
 * Cuerpo de «Estado del proyecto» para el renderer (document_type 'estado', spec §7). Solo cierres publicados; con
 * $previa también los borradores (marcados) y la ruta <código>-previa-<token> (R3). $datos: lo de at_pt_datos_render()
 * más 'previa_token' y 'entregables' (id => URL pública). Sin fotos nuevas (D9): image_briefs vacío e images {} (n8n
 * pone las del plan). Todos los enlaces son de WordPress (D8).
 */
function at_av_armar_render_estado(array $plan, array $avance, array $datos, bool $previa, string $hoy): array {
	$base = at_pt_armar_render($plan, $datos, true);
	$codigo = (string) ($datos['codigo'] ?? '');
	$cierres = [];
	foreach ($avance['cierres'] as $c) {
		$publicado = ($c['estado'] ?? '') === 'publicado';
		if (!$publicado && !$previa) {
			continue;
		}
		$ent = (int) ($c['entregable_id'] ?? 0);
		$parte = fn(array $p) => ['texto' => (string) ($p['texto'] ?? ''), 'puntos' => array_values((array) ($p['puntos'] ?? [])),
			'evidencias' => at_av_evidencias_render($avance, (array) ($p['evidencias'] ?? []))];
		$cierres[] = [
			'id' => (string) $c['id'], 'titulo' => (string) $c['titulo'], 'bloques' => array_values((array) ($c['bloques'] ?? [])),
			'fecha_real' => (string) ($c['fecha_real'] ?? ''), 'borrador' => !$publicado,
			'entregable_url' => $ent > 0 ? at_av_url_publica($datos['entregables'][$ent] ?? '') : '',
			'antes' => $parte((array) ($c['antes'] ?? [])),
			'durante' => ['texto' => (string) ($c['durante']['texto'] ?? ''), 'actividades' => array_values((array) ($c['durante']['actividades'] ?? []))],
			'despues' => $parte((array) ($c['despues'] ?? [])),
		];
	}
	usort($cierres, fn($a, $b) => strcmp($a['fecha_real'], $b['fecha_real']));
	$bloques_plan = at_av_bloques_plan($plan);
	$fase1 = $avance['alcance']['fase1'] ?: array_values(array_unique(array_filter(array_column($bloques_plan, 'entregable'), 'strlen')));
	$mensual = $avance['alcance']['mensual'] ?: array_values(array_filter(array_map('strval', (array) ($plan['soporte']['mensuales'] ?? [])), 'strlen'));
	$crono = is_array($plan['cronograma'] ?? null) ? $plan['cronograma'] : [];
	return [
		'document_type' => 'estado',
		'unique_id'     => $previa ? $codigo . '-previa-' . (string) ($datos['previa_token'] ?? '') : $codigo . '-estado',
		'draft'         => $previa,
		'company_name'  => $base['company_name'],
		'client_name'   => $base['client_name'],
		'proyecto'      => $base['proyecto'],
		'actualizado'   => $hoy,
		'plan_url'      => at_pt_url_ver_plan((string) ($datos['sitio'] ?? ''), $codigo),
		'agenda'        => $base['agenda'],
		'cronograma'    => ['inicio' => (string) ($crono['inicio'] ?? ''), 'fin' => (string) ($crono['fin'] ?? ''), 'semanas' => (int) ($crono['semanas'] ?? 0)],
		'bloques'       => at_av_estado_bloques($plan, $avance),
		'cierres'       => $cierres,
		'cambios_plan'  => array_values($avance['cambios_plan']),
		'necesitamos'   => array_values(array_map(fn($x) => (string) $x['texto'], array_filter($avance['necesitamos'], fn($x) => ($x['estado'] ?? '') !== 'resuelto'))),
		'alcance'       => ['fase1' => array_slice($fase1, 0, AT_AV_MAX_ALCANCE), 'mensual' => array_slice($mensual, 0, AT_AV_MAX_ALCANCE)],
		'image_briefs'  => [],
		'images'        => new stdClass(),
	];
}
```

- [ ] **Step 4: `at_pt_uid_propuesta` en datos.php**

En `wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php`, después de `at_pt_datos_render()`, agregar:

```php
/** Código (unique_link_id) de la propuesta del plan, para reutilizar sus fotos. '' sin propuesta o si el renderer no lo
 *  aceptaría como carpeta (/p/<uid>/). Lo usan el render del plan y el de «Estado del proyecto». */
function at_pt_uid_propuesta(object $f): string {
	if ((int) ($f->propuesta_id ?? 0) <= 0) {
		return '';
	}
	global $wpdb;
	$uid = (string) $wpdb->get_var($wpdb->prepare("SELECT unique_link_id FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", (int) $f->propuesta_id));
	return preg_match('/^[A-Za-z0-9_-]{6,64}$/', $uid) ? $uid : '';
}
```

- [ ] **Step 5: avance-render.php**

Crear `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-render.php`:

```php
<?php
/**
 * Estado del proyecto (Etapa 2): render de la presentación (spec §7). GET /plan/{id}/render&modo=estado arma el cuerpo
 * para n8n; POST /plan/{id}/vista con modo estado guarda lo que n8n cuenta; at_av_pedir_render() avisa a n8n («3 Render»,
 * modo estado). Nunca cambia el estado del plan ni su view_url/pdf_url. El cliente solo ve publicados.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** Token de la vista previa con borradores (R3): 10 hex, fijo por plan, no se adivina sin la sal del sitio. */
function at_av_token_previa(string $codigo): string {
	return substr(hash_hmac('sha256', 'at-estado-previa|' . $codigo, wp_salt('auth')), 0, 10);
}

/** Carpeta del renderer: <código>-estado (pública) o <código>-previa-<token> (vista previa). */
function at_av_uid_render(string $codigo, bool $previa): string {
	return $previa ? $codigo . '-previa-' . at_av_token_previa($codigo) : $codigo . '-estado';
}

/** Enlace del renderer para ese plan y ese registro: solo https://<renderer>/p/<uid>/index.html o presentation.pdf. */
function at_av_url_render_valida(string $u, string $codigo, bool $previa): string {
	$u = trim($u);
	$patron = '#^https://' . preg_quote(at_pt_host_renderer(), '#') . '/p/' . preg_quote(at_av_uid_render($codigo, $previa), '#') . '/(index\.html|presentation\.pdf)$#';
	return preg_match($patron, $u) ? $u : '';
}

/** URL públicas (ver-entregable.php) de los entregables que nombran los cierres: [id => url]. */
function at_av_urls_entregables(array $avance): array {
	$out = [];
	foreach ($avance['cierres'] as $c) {
		$id = (int) ($c['entregable_id'] ?? 0);
		if ($id <= 0 || isset($out[$id]) || !function_exists('at_en_por_id') || !function_exists('at_en_url_pagina')) {
			continue;
		}
		$e = at_en_por_id($id);
		$u = $e ? at_en_url_pagina(home_url(), (string) $e->codigo) : '';
		if ($u !== '') {
			$out[$id] = $u;
		}
	}
	return $out;
}

/** GET /plan/{id}/render&modo=estado[&previa=1]: cuerpo para el renderer. Solo planes enviados con la migración hecha. */
function at_av_rest_render_estado(object $f, bool $previa) {
	if ((string) $f->estado !== 'enviado') {
		return new WP_Error('at_av_no_enviado', 'El estado del proyecto existe solo para planes enviados.', ['status' => 409]);
	}
	if (!at_av_columna_lista()) {
		return new WP_Error('at_av_sin_columna', 'Falta la migración del avance (columna avance).', ['status' => 409]);
	}
	$avance = at_av_leer($f);
	$datos = at_pt_datos_render($f) + ['previa_token' => at_av_token_previa((string) $f->codigo), 'entregables' => at_av_urls_entregables($avance)];
	return new WP_REST_Response([
		'ok'             => true,
		'render'         => at_av_armar_render_estado(at_pt_payload($f), $avance, $datos, $previa, current_time('Y-m-d')),
		'plan_codigo'    => (string) $f->codigo,
		'propuesta_uid'  => at_pt_uid_propuesta($f),
		'crm_cliente_id' => (int) at_pt_db_partes($f)['crm_id'],
		'estado'         => (string) $f->estado,
	], 200);
}

/**
 * POST /plan/{id}/vista con modo estado: guarda el resultado en avance.render (o render_previa). Con un enlace válido de
 * ESE plan y ESE registro, queda «listo»; si no, «error» con la nota, y los enlaces anteriores se conservan (el cliente
 * sigue viendo la última versión lista: Review Focus 5). Nunca toca el estado del plan ni su view_url/pdf_url.
 */
function at_av_rest_vista_estado(object $f, array $p): WP_REST_Response {
	$previa = !empty($p['previa']) && $p['previa'] !== 'false';
	$ok = !empty($p['ok']) && $p['ok'] !== 'false';
	$codigo = (string) $f->codigo;
	$view = at_av_url_render_valida((string) ($p['view_url'] ?? ''), $codigo, $previa);
	$pdf = at_av_url_render_valida((string) ($p['pdf_url'] ?? ''), $codigo, $previa);
	$nota = mb_substr(sanitize_textarea_field((string) ($p['nota'] ?? '')), 0, 500);
	$ahora = current_time('mysql');
	if ($ok && $view !== '' && str_ends_with($view, '/index.html')) {
		$reg = ['estado' => 'listo', 'view_url' => $view, 'pdf_url' => str_ends_with($pdf, '/presentation.pdf') ? $pdf : '', 'nota' => $nota, 'at' => $ahora];
	} else {
		$reg = ['estado' => 'error', 'nota' => $nota !== '' ? $nota : 'El renderer no devolvió una presentación válida.', 'at' => $ahora];
	}
	at_av_guardar_render((int) $f->id, $previa ? 'render_previa' : 'render', $reg);
	return new WP_REST_Response(['ok' => true, 'estado' => (string) $f->estado], 200);
}

/** Presentación que ve el cliente en ver-estado.php: la última lista, si hay al menos un cierre publicado; '' si no. */
function at_av_vista_publica(object $fila): string {
	if (!at_av_columna_lista()) {
		return '';
	}
	$a = at_av_leer($fila);
	if (!at_av_publicados($a)) {
		return '';
	}
	return at_av_url_render_valida((string) $a['render']['view_url'], (string) $fila->codigo, false);
}

/** Pide a n8n el render de «Estado del proyecto» (o su vista previa con borradores). Deja el registro en «pendiente»;
 *  si n8n no recibe el aviso, en «error» con el motivo (los enlaces anteriores se conservan). Devuelve '' o el motivo. */
function at_av_pedir_render(int $plan_id, bool $previa = false): string {
	$f = at_pt_plan($plan_id);
	if (!$f) {
		return 'El plan no existe.';
	}
	$clave = $previa ? 'render_previa' : 'render';
	at_av_guardar_render($plan_id, $clave, ['estado' => 'pendiente', 'nota' => '', 'at' => current_time('mysql')]);
	$cuerpo = ['id' => (int) $f->id, 'codigo' => (string) $f->codigo, 'modo' => 'estado'];
	if ($previa) {
		$cuerpo['previa'] = true;
	}
	$motivo = at_pt_llamar_n8n(AT_N8N_PLAN_RENDER, $cuerpo);
	if ($motivo !== '') {
		at_av_guardar_render($plan_id, $clave, ['estado' => 'error', 'nota' => 'No se pudo pedir la presentación: ' . $motivo, 'at' => current_time('mysql')]);
	}
	return $motivo;
}

/** Rehace la presentación pública solo si hay al menos un cierre publicado (R4). Devuelve '' o el motivo. */
function at_av_refrescar(int $plan_id): string {
	$f = at_pt_plan($plan_id);
	if (!$f || !at_av_publicados(at_av_leer($f))) {
		return '';
	}
	return at_av_pedir_render($plan_id, false);
}
```

- [ ] **Step 6: Rutas REST**

En `wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php`:

1. Docblock del archivo: cambiar las líneas de `render` y `vista` a:
```
 *   GET  /plan/{id}/render&modo=    draft|final: cuerpo para el renderer + propuesta_uid, crm_cliente_id y estado;
 *                                   estado[&previa=1]: «Estado del proyecto» (avance-render.php), solo planes enviados
 *   POST /plan/{id}/vista           {modo, ok, view_url, pdf_url, faltan, nota}; con modo estado guarda avance.render
```
2. En `at_pt_rest_render()`, reemplazar el control de modo por:
```php
	$modo = (string) ($r->get_param('modo') ?? 'draft');
	if (!in_array($modo, ['draft', 'final', 'estado'], true)) {
		return new WP_Error('at_pt_modo', 'modo debe ser draft, final o estado.', ['status' => 400]);
	}
	if ($modo === 'estado') {
		return at_av_rest_render_estado($f, (string) $r->get_param('previa') === '1');
	}
```
3. En la misma función, reemplazar todo el bloque que calcula `$uid` (desde `$uid = '';` hasta el `if (!preg_match(...)) { $uid = ''; }` inclusive, con su comentario) por:
```php
	$uid = at_pt_uid_propuesta($f);
```
4. En `at_pt_rest_vista()`, reemplazar el control de modo por:
```php
	$modo = (string) ($p['modo'] ?? '');
	if (!in_array($modo, ['draft', 'final', 'estado'], true)) {
		return new WP_Error('at_pt_modo', 'modo debe ser draft, final o estado.', ['status' => 400]);
	}
	if ($modo === 'estado') {
		return at_av_rest_vista_estado($f, $p);
	}
```

En `cargar.php`, después de `require_once __DIR__ . '/avance-evidencias.php';`:
```php
require_once __DIR__ . '/avance-render.php';
```

- [ ] **Step 7: Correr las pruebas y ver que pasan**

Run (los dos comandos del Step 2) y la línea base `rest-render-wp-test`, `disparador-wp-test`, `ver-plan-wp-test`, `ver-estado-wp-test`. Expected: todo «TODO OK».

- [ ] **Step 8: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-render.php wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php tests/plan/avance-render-test.php tests/plan/rest-estado-wp-test.php
git commit -m "feat(estado): cuerpo de «Estado del proyecto» y rutas REST con modo estado

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: n8n — rama `modo: 'estado'` en «Plan de trabajo · 3 Render»

**Files:**
- Modify: `N8N/plan-trabajo/build_plan_3_render.py` (docstring, dos `CODE_*` nuevos, nueve nodos y sus conexiones)
- Modify: `N8N/plan-trabajo/probar_plan.py` (sección nueva `estado`)
- Regenerate: `N8N/plan-trabajo/plan-3-render.json` (lo escribe el build; importar el build desde las pruebas también lo reescribe)

**Interfaces:**
- Consumes: Task 5 — WordPress manda al webhook `{id, codigo, modo: 'estado'}` (y `previa: true` solo para la vista previa); `GET /plan/{id}/render&modo=estado[&previa=1]` responde `{ok, render, plan_codigo, propuesta_uid, crm_cliente_id, estado}`; `POST /plan/{id}/vista` acepta `{modo: 'estado', previa, ok, view_url, pdf_url, nota}`. De `plan_js.py`: `fotosPropiasDelPlan(manifest, base, uid)`, `reutilizarFotosPlan(render, manifest, base, uid)`, `leerRespuestaRender(x)`, `nombresLaminas(l)`, `sinUrlInterna(t)`, `esObjetoPlano(x)` (todo dentro de `LIB`), y `mantenerPedido(v)` de `JS_AVISO`.
- Produces: nodos `¿Estado del proyecto?`, `Leer estado`, `Fotos del plan (estado)`, `Fotos de la propuesta (estado)`, `Preparar estado`, `¿Saltar estado?`, `Render estado`, `Resultado estado`, `Guardar estado`. Nunca mandan correo ni piden fotos (R5, D9).

- [ ] **Step 1: Escribir la prueba que falla**

En `N8N/plan-trabajo/probar_plan.py`, antes de `if __name__ == '__main__':`, agregar la sección:

```python
@seccion('estado')
def prueba_estado():
    """Task 6 de la Etapa 2 del Estado del proyecto: rama modo «estado» (spec §7, R5). Datos inventados."""
    import build_plan_3_render  # noqa: F401  (vuelve a escribir plan-3-render.json)
    wf = revisar_workflow('plan-3-render.json', 'Plan de trabajo · 3 Render', 'plan-v1-render')
    nombres = {n['name']: n for n in wf['nodes']}
    re_ = nombres.get('Render estado', {})
    ok(re_.get('credentials', {}).get('httpHeaderAuth', {}).get('id') == 'fj2orzbsjlnHaiLd' and re_.get('parameters', {}).get('url') == BASE_R + '/render'
       and re_.get('onError') == 'continueRegularOutput', 'estado: «Render estado» va con X-AT-Render-Key y nunca corta el flujo')
    for n in ('Fotos del plan (estado)', 'Fotos de la propuesta (estado)'):
        ok('credentials' not in nombres.get(n, {'credentials': 1}), f'estado: «{n}» lee el manifest público sin credenciales')

    COD = 'AbCdEf123456'
    CUERPO = {'document_type': 'estado', 'unique_id': COD + '-estado', 'draft': False, 'company_name': '[PRUEBA] Panadería',
              'proyecto': '[PRUEBA] Sitio', 'actualizado': '2026-10-14', 'bloques': [], 'cierres': [{'titulo': 'Sitio web'}],
              'image_briefs': [], 'images': {}}
    LEIDO = [{'statusCode': 200, 'body': {'ok': True, 'render': CUERPO, 'plan_codigo': COD, 'propuesta_uid': 'PRUEBAprop01', 'crm_cliente_id': 5, 'estado': 'enviado'}}]
    MAN_PLAN = {'statusCode': 200, 'body': {'metodo': {'file': 'metodo.jpg', 'hash': 'd' * 64}, 'gantt': {'file': 'gantt.png', 'hash': 'd' * 64}}}
    VISTA_E = 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/' + COD + '-estado/index.html'
    OK_RENDER = {'statusCode': 200, 'body': {'view_url': VISTA_E, 'pdf_url': VISTA_E.replace('index.html', 'presentation.pdf'),
                                             'images': {'requested': 0, 'stored_local': 4, 'kept_remote': [], 'missing': [], 'reused': 0}}}

    def sim(webhook, **http):
        http.setdefault('Leer estado', LEIDO)
        http.setdefault('Fotos del plan (estado)', [MAN_PLAN])
        http.setdefault('Fotos de la propuesta (estado)', [MANIFEST_PROP])
        http.setdefault('Render estado', [OK_RENDER])
        http.setdefault('Guardar estado', [{'statusCode': 200, 'body': {'ok': True, 'estado': 'enviado'}}])
        return simular('plan-3-render.json', webhook=webhook, http=http, openai={})

    # E1. Camino feliz: lee el cuerpo, junta las fotos del plan y de la propuesta, un render sin fotos nuevas y /vista estado.
    t = sim({'id': 9, 'codigo': COD, 'modo': 'estado'})
    sin_error(t, 'E1')
    ok(llamadas(t, 'Leer estado')[0]['url'] == WP + '/plan/9/render&modo=estado', 'E1: GET /plan/9/render&modo=estado', llamadas(t, 'Leer estado'))
    ok(llamadas(t, 'Fotos del plan (estado)')[0]['url'] == BASE_R + '/p/' + COD + '/img/manifest.json', 'E1: lee las fotos guardadas del plan')
    rc = llamadas(t, 'Render estado')
    imgs = rc[0]['body']['images'] if rc else {}
    ok(len(rc) == 1 and rc[0]['body']['document_type'] == 'estado' and rc[0]['body']['image_briefs'] == []
       and imgs.get('metodo') == BASE_R + '/p/' + COD + '/img/metodo.jpg' and imgs.get('cover') == BASE_R + '/p/PRUEBAprop01/img/cover.jpg'
       and imgs.get('cierre') == BASE_R + '/p/PRUEBAprop01/img/next_steps.png', 'E1: un render de estado con las fotos del plan y portada/cierre de la propuesta, sin pedir ninguna', imgs)
    gv = llamadas(t, 'Guardar estado')
    ok(gv and gv[0]['url'] == WP + '/plan/9/vista' and gv[0]['body']['modo'] == 'estado' and gv[0]['body']['ok'] is True and gv[0]['body']['previa'] is False
       and gv[0]['body']['view_url'] == VISTA_E and gv[0]['body']['nota'].startswith('Estado del proyecto actualizado'), 'E1: POST /vista {modo: estado, ok: true, …}', gv)
    ok(not t['correos'] and not llamadas(t, 'Leer render') and not llamadas(t, 'Render') and not t['openai'], 'E1: no toca el camino del plan, no manda correo ni consulta a OpenAI')

    # E2. Vista previa: la URL lleva &previa=1 y /vista va con previa true.
    t = sim({'id': 9, 'codigo': COD, 'modo': 'estado', 'previa': True})
    ok(llamadas(t, 'Leer estado')[0]['url'].endswith('&modo=estado&previa=1') and llamadas(t, 'Guardar estado')[0]['body']['previa'] is True, 'E2: vista previa con &previa=1 y previa true')

    # E3. WordPress no entrega el cuerpo (409 de un plan no enviado): sin render; /vista estado con ok false y el motivo.
    t = sim({'id': 9, 'codigo': COD, 'modo': 'estado'}, **{'Leer estado': [{'statusCode': 409, 'body': {'message': 'El estado del proyecto existe solo para planes enviados.'}}]})
    sin_error(t, 'E3')
    gv = llamadas(t, 'Guardar estado')
    ok(not llamadas(t, 'Render estado') and gv and gv[0]['body']['ok'] is False and 'HTTP 409' in gv[0]['body']['nota'] and 'solo para planes enviados' in gv[0]['body']['nota'],
       'E3: sin cuerpo no hay render; la nota dice por qué', gv)

    # E4. El renderer falla (502 o red cortada): /vista con ok false, la nota sin URL interna; nunca reintenta ni manda correo.
    for caso, resp in (('502', {'statusCode': 502, 'body': {'error': 'render failed', 'details': 'Chromium cayó en https://n8n-propuesta-renderer.kchiba.easypanel.host/x'}}),
                       ('red', {'error': {'message': 'socket hang up'}})):
        t = sim({'id': 9, 'codigo': COD, 'modo': 'estado'}, **{'Render estado': [resp]})
        gv = llamadas(t, 'Guardar estado')
        ok(len(llamadas(t, 'Render estado')) == 1 and gv and gv[0]['body']['ok'] is False and 'easypanel' not in gv[0]['body']['nota']
           and not t['correos'], f'E4 {caso}: un solo intento, ok false, nota limpia y sin correo', gv)

    # E5. Sin propuesta y sin fotos guardadas: el render sale igual, sin fotos.
    leido = [{'statusCode': 200, 'body': dict(LEIDO[0]['body'], propuesta_uid='')}]
    t = sim({'id': 9, 'codigo': COD, 'modo': 'estado'}, **{'Leer estado': leido, 'Fotos del plan (estado)': [{'statusCode': 404, 'body': 'Not Found'}],
                                                          'Fotos de la propuesta (estado)': [{'statusCode': 404, 'body': 'Not Found'}]})
    rc = llamadas(t, 'Render estado')
    ok(rc and rc[0]['body']['images'] == {} and llamadas(t, 'Guardar estado')[0]['body']['ok'] is True, 'E5: sin propuesta ni manifest el estado se dibuja sin fotos')

    # E6. Los modos del plan no pasan por la rama de estado.
    for modo in ('draft', 'final'):
        t = simular('plan-3-render.json', webhook={'id': 9, 'modo': modo, 'aviso': False},
                    http={'Leer render': [{'statusCode': 500, 'body': {}}], 'Guardar vista': VISTA_BORRADOR}, openai={})
        ok(not llamadas(t, 'Leer estado') and llamadas(t, 'Leer render'), f'E6 {modo}: va por el camino del plan')
```

Run: `python N8N/plan-trabajo/probar_plan.py estado`
Expected: FALLA (los nodos de estado no existen: «Respuesta sin simular» o «Conexión a un nodo que no existe»).

- [ ] **Step 2: Agregar la rama al flujo**

En `N8N/plan-trabajo/build_plan_3_render.py`:

1. Docstring: agregar al final de la lista de modos:
```
- estado (Estado del proyecto, Etapa 2): WordPress pide {id, codigo, modo: 'estado'[, previa: true]} al publicar o
  cambiar el avance. Lee GET /render&modo=estado, junta las fotos ya guardadas del plan (manifest de /p/<código>/) y
  portada/cierre de la propuesta, renderiza UNA vez con image_briefs vacío (nunca paga fotos) y termina en POST /vista
  {modo: 'estado'}. Sin reintentos ni correo: el panel muestra el resultado (R5).
```

2. Después de `CODE_RESULTADO`, agregar:

```python
CODE_ESTADO_PREPARAR = r"""// «Estado del proyecto» (Etapa 2): cuerpo para el renderer con las fotos ya guardadas del plan y portada/cierre
// de la propuesta. Nunca pide ni paga fotos (D9). Nunca lanza.
const hook = $('Webhook').first().json.body || {};
const id = parseInt(hook.id, 10) || 0;
const previa = mantenerPedido(hook.previa);
const le = $('Leer estado').first().json || {};
const b = esObjetoPlano(le.body) ? le.body : {};
const leido = le.statusCode === 200 && b.ok === true && esObjetoPlano(b.render) && b.render.document_type === 'estado';
if (!leido) {
  const motivo = 'No se pudo leer el avance en WordPress (' + (le.statusCode ? 'HTTP ' + le.statusCode
    : 'sin respuesta' + (le.error && le.error.message ? ': ' + le.error.message : '')) + ')' + (b.message ? ' — ' + b.message : '');
  return [{ json: { id, previa, saltar_render: true, motivo: sinUrlInterna(motivo), render: {} } }];
}
const codigo = /^[A-Za-z0-9]{6,32}$/.test(String(b.plan_codigo || '')) ? String(b.plan_codigo) : '';
const uid = /^[A-Za-z0-9_-]{6,64}$/.test(String(b.propuesta_uid || '')) ? String(b.propuesta_uid) : '';
const manifest = (nodo) => {
  if (!$(nodo).isExecuted) return null;
  const r = $(nodo).first().json || {};
  return r.statusCode === 200 ? r.body : null;
};
const propias = fotosPropiasDelPlan(manifest('Fotos del plan (estado)'), RENDERER_BASE, codigo);
const deLaPropuesta = reutilizarFotosPlan({ images: {} }, manifest('Fotos de la propuesta (estado)'), RENDERER_BASE, uid);
const images = Object.assign({}, deLaPropuesta.render.images, propias.images);
const render = Object.assign({}, b.render, { image_briefs: [], images });
return [{ json: { id, previa, saltar_render: false, motivo: '', render } }];"""

CODE_ESTADO_RESULTADO = r"""// Lo que se le cuenta a WordPress (POST /vista, modo estado). Un solo intento: el panel ofrece «Reintentar». Nunca lanza.
const p = $('Preparar estado').first().json || {};
const exec = String($execution.id);
const base = { id: p.id || 0, previa: p.previa === true, ok: false, view_url: '', pdf_url: '', nota: '' };
if (p.saltar_render === true) {
  return [{ json: Object.assign(base, { nota: (p.motivo || 'No se pudo leer el avance') + ' (ejecución ' + exec + ')' }) }];
}
const lr = leerRespuestaRender($('Render estado').first().json);
if (!lr.view_url) {
  const motivo = lr.detalles.length ? lr.detalles.join('; ') : String(lr.cuerpo.error || '').slice(0, 200);
  const det = lr.status === 0 ? lr.fallo : 'HTTP ' + lr.status + (motivo ? ': ' + motivo : '');
  return [{ json: Object.assign(base, { nota: sinUrlInterna('el renderer no devolvió la presentación del estado (' + det + ')') + ' (ejecución ' + exec + ')' }) }];
}
const remotas = Array.isArray(lr.images.kept_remote) ? lr.images.kept_remote : [];
const nota = 'Estado del proyecto actualizado' + (remotas.length ? ' · fotos que quedaron enlazadas: ' + nombresLaminas(remotas) : '');
return [{ json: Object.assign(base, { ok: true, view_url: lr.view_url, pdf_url: lr.pdf_url, nota: sinUrlInterna(nota) }) }];"""
```

3. En la lista `nodes`, agregar al final (antes del `]`):

```python
    # «Estado del proyecto» (Etapa 2, R5): rama propia que sale del Webhook; no toca el camino del plan ni manda correo.
    iff('r26', '¿Estado del proyecto?', [110, 360], "={{ $json.body.modo === 'estado' }}"),
    http('r27', 'Leer estado', [330, 360], 'GET',
         f"={WP}/plan/{{{{ $json.body.id }}}}/render&modo=estado{{{{ [true, 1, '1', 'true'].includes($json.body.previa) ? '&previa=1' : '' }}}}"),
    # Manifest público del propio plan (sus fotos ya pagadas) y de la propuesta (portada y cierre). Un 404 es «sin fotos».
    http('r28', 'Fotos del plan (estado)', [550, 360], 'GET',
         "={{ '" + RENDERER_BASE + "/p/' + encodeURIComponent(String((($json.body || {}).plan_codigo) || 'sin-plan')) + '/img/manifest.json' }}",
         cred=False),
    http('r29', 'Fotos de la propuesta (estado)', [770, 360], 'GET',
         "={{ '" + RENDERER_BASE + "/p/' + encodeURIComponent(String((($('Leer estado').first().json || {}).body || {}).propuesta_uid || 'sin-propuesta')) + '/img/manifest.json' }}",
         cred=False),
    node('r30', 'Preparar estado', 'n8n-nodes-base.code', 2, [990, 360], {'jsCode': LIB + JS_AVISO + CODE_ESTADO_PREPARAR}),
    iff('r31', '¿Saltar estado?', [1210, 360], '={{ $json.saltar_render === true }}'),
    node('r32', 'Render estado', 'n8n-nodes-base.httpRequest', 4.2, [1430, 480],
         {'method': 'POST', 'url': RENDERER, 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
          'sendBody': True, 'specifyBody': 'json', 'jsonBody': '={{ JSON.stringify($json.render) }}',
          'options': dict(FULL, timeout=290000)},
         onError='continueRegularOutput', credentials=CRED_RENDER),
    node('r33', 'Resultado estado', 'n8n-nodes-base.code', 2, [1650, 360], {'jsCode': LIB + JS_AVISO + CODE_ESTADO_RESULTADO}),
    http('r34', 'Guardar estado', [1870, 360], 'POST', f"={WP}/plan/{{{{ $json.id }}}}/vista",
         "={{ JSON.stringify({ modo: 'estado', previa: $json.previa === true, ok: $json.ok, view_url: $json.view_url, pdf_url: $json.pdf_url, nota: $json.nota }) }}"),
```

4. Conexiones: reemplazar `link('Webhook', 'Leer render')` por:

```python
link('Webhook', '¿Estado del proyecto?')
link('¿Estado del proyecto?', 'Leer estado', 0)
link('¿Estado del proyecto?', 'Leer render', 1)
```

y agregar al final de las conexiones:

```python
link('Leer estado', 'Fotos del plan (estado)')
link('Fotos del plan (estado)', 'Fotos de la propuesta (estado)')
link('Fotos de la propuesta (estado)', 'Preparar estado')
link('Preparar estado', '¿Saltar estado?')
link('¿Saltar estado?', 'Resultado estado', 0)
link('¿Saltar estado?', 'Render estado', 1)
link('Render estado', 'Resultado estado')
link('Resultado estado', 'Guardar estado')
```

- [ ] **Step 3: Correr las pruebas y ver que pasan**

Run:
```bash
python N8N/plan-trabajo/build_plan_3_render.py
python N8N/plan-trabajo/probar_plan.py
python N8N/plan-trabajo/probar_revision_plan.py
```
Expected: «escrito … 34 nodos» y «TODO OK» en las dos suites (la sección `estado` incluida). Si una prueba vieja compara la lista exacta de pasos y ahora ve `¿Estado del proyecto?` después de `Webhook`, actualiza esa comparación agregando ese paso (es el único cambio esperado en el camino del plan) y anótalo en el reporte.

- [ ] **Step 4: Commit**

```bash
git add N8N/plan-trabajo/build_plan_3_render.py N8N/plan-trabajo/probar_plan.py N8N/plan-trabajo/plan-3-render.json
git commit -m "feat(n8n): rama «Estado del proyecto» en Plan de trabajo · 3 Render

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---
### Task 7: Panel — sección «📈 Avance» y sus acciones

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php`
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php` (`at_pt_mensajes_panel()` y el final de `at_pt_render_plan()`)
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php`
- Modify: `wp-content/themes/automatiza-tech/assets/css/plan-trabajo.css` (al final)
- Modify: `tests/plan/accion-wp-test-run.php` (archivos subidos en las pruebas)
- Test: `tests/plan/avance-panel-wp-test.php`

**Interfaces:**
- Consumes: Tasks 1-5. Del panel del plan: `at_pt_accion_plan(): array` → `[fila, crm]` (nonce `at_pt_plan_<id>` + `manage_options`), `at_pt_volver(int $crm, int $plan_id, string $msg): void` (redirige y termina), `at_pt_guardar_detalles(int $plan_id, array $lineas)`, `at_pt_panel_fecha(string $ymd, bool $con_anio=false)`, `at_pt_url_ver_estado(string $base, string $codigo)`; de entregables `at_en_archivos_subidos(array $campo): array`.
- Produces: `at_av_mensajes(): array`; `at_av_render_seccion(object $fila, int $crm_id): void`; acciones `admin_post_at_av_importar`, `…_subir`, `…_publicar`, `…_borrador`, `…_quitar`, `…_bloques`, `…_listas`, `…_reintentar`, `…_previa` (funciones `at_av_accion_<nombre>`). Formularios: `cierre_json` (archivo) o `cierre_texto`, `archivos[]`; `evidencia_id`, `campo` (`archivo`|`poster`), `archivo`; `cierre_id`; `bloques[i][nombre|estado|inicio_real|fin_real]`; `cambios_plan`, `necesitamos`, `alcance_fase1`, `alcance_mensual`.

- [ ] **Step 1: Las pruebas pueden mandar archivos**

En `tests/plan/accion-wp-test-run.php`:

1. Antes de `define('WP_USE_THEMES', false);`, agregar:
```php
// Archivos «subidos» de la prueba: vienen en la clave __files del POST (formato de $_FILES). Solo en pruebas:
// AT_AV_PRUEBAS deja que el módulo del avance acepte archivos que no llegan por una subida HTTP.
$archivos = [];
if (isset($post['__files']) && is_array($post['__files'])) {
	$archivos = $post['__files'];
	unset($post['__files']);
}
if (!defined('AT_AV_PRUEBAS')) {
	define('AT_AV_PRUEBAS', true);
}
```
2. Justo antes de `do_action('admin_post_' . $accion);`, agregar `$_FILES = $archivos;`.

- [ ] **Step 2: Escribir la prueba que falla**

Crear `tests/plan/avance-panel-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/avance-panel-wp-test.php
// Estado del proyecto, Etapa 2 — Task 7: sección «Avance» de la pestaña del plan y sus acciones admin-post. Cada acción
// corre en un proceso aparte (accion-wp-test-run.php), que anota y contesta las llamadas a n8n (ok o caído).
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
define('AT_AV_PRUEBAS', true);
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_av_render_seccion', 'at_av_mensajes', 'at_av_accion_importar', 'at_av_accion_publicar');
at_av_migrar_esquema();
if (!at_av_columna_lista()) {
	fwrite(STDERR, "La columna avance no está lista en el sitio de prueba.\n");
	exit(2);
}
$admin = pt_admin_id();
wp_set_current_user($admin);
$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-avance-panel@example.com');
$plan = ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-avance-panel@example.com'));
$id = (int) $plan->id;
ptc_sembrar($id);
$crm = (int) at_pt_plan($id)->crm_cliente_id;
$cod = (string) at_pt_plan($id)->codigo;
$payload_antes = (string) at_pt_plan($id)->payload;
$tmp = sys_get_temp_dir() . '/at-av-panel-' . getmypid() . '/';
wp_mkdir_p($tmp);
register_shutdown_function(function () use ($tmp, $id) {
	foreach (glob($tmp . '*') ?: [] as $f) { @unlink($f); }
	@rmdir($tmp);
	$d = at_av_dir($id)['dir'];
	foreach (glob($d . '*') ?: [] as $f) { @unlink($f); }
	@rmdir($d);
});
$correr = fn(string $accion, array $post, string $n8n = 'ok') => pt_correr_accion($accion, $admin, 'at_pt_plan_' . $id, $post + ['plan_id' => (string) $id, 'crm_id' => (string) $crm], $n8n);
$msg = fn(array $r): string => (string) (pt_query($r['redirect'])['pt_msg'] ?? '');
$leer = fn(): array => at_av_leer(at_pt_plan($id));
$archivo = [
	'formato' => 'at-cierre-v1', 'plan' => $cod,
	'cierre' => ['titulo' => 'Diseño terminado', 'bloques' => ['Diseño'], 'fecha_real' => '2026-10-10',
		'antes' => ['texto' => 'Sin maqueta.', 'evidencias' => ['visitas']], 'despues' => ['texto' => 'Maqueta aprobada.', 'evidencias' => ['portada', 'sitio']]],
	'evidencias' => [
		['ref' => 'visitas', 'tipo' => 'cifra', 'titulo' => 'Visitas', 'valor' => '10', 'fuente' => 'GA4', 'fecha' => '2026-10-09'],
		['ref' => 'portada', 'tipo' => 'captura', 'titulo' => 'Portada', 'archivo' => 'portada.png'],
		['ref' => 'sitio', 'tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'https://example.com/'],
	],
];
$im = imagecreatetruecolor(40, 30);
imagepng($im, $tmp . 'portada.png');
imagedestroy($im);

$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($archivo)]);
ok($msg($r) === 'av_no_enviado' && $leer()['cierres'] === [], '1) plan que no está enviado: no se carga avance');
ptc_estado($id, 'enviado');

$malo = $archivo;
$malo['evidencias'][0]['fuente'] = '';
$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($malo)]);
ok($msg($r) === 'av_import_error' && $leer()['cierres'] === [], '2) cifra sin fuente: no se guarda nada (Review Focus 4)');
$r = $correr('at_av_importar', ['cierre_texto' => '{roto']);
ok($msg($r) === 'av_import_error' && $leer()['cierres'] === [], '2) JSON roto: no se guarda nada');

$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($archivo)]);
$av = $leer();
$cid = (string) ($av['cierres'][0]['id'] ?? '');
ok($msg($r) === 'av_importado_faltan' && count($av['cierres']) === 1 && $av['cierres'][0]['estado'] === 'borrador' && $r['n8n'] === [], '3) importado en borrador, falta la portada y no se pide render (R4)');
$r = $correr('at_av_publicar', ['cierre_id' => $cid]);
ok($msg($r) === 'av_no_publicable' && $leer()['cierres'][0]['estado'] === 'borrador', '4) con archivos pendientes no se publica');

$files = ['archivos' => ['name' => ['portada.png'], 'type' => ['image/png'], 'tmp_name' => [$tmp . 'portada.png'], 'error' => [0], 'size' => [filesize($tmp . 'portada.png')]]];
$r = $correr('at_av_importar', ['cierre_texto' => wp_json_encode($archivo), '__files' => $files]);
$av = $leer();
ok($msg($r) === 'av_importado' && count($av['cierres']) === 1 && $av['cierres'][0]['id'] === $cid && at_av_faltan_archivos($av, $cid) === [], '5) reimportar con el archivo: mismo cierre, ya no falta nada');

$r = $correr('at_av_publicar', ['cierre_id' => $cid]);
$n = $r['n8n'][0] ?? ['url' => '', 'cuerpo' => []];
ok($msg($r) === 'av_publicado' && $leer()['cierres'][0]['estado'] === 'publicado' && str_contains($n['url'], 'plan-v1-render') && ($n['cuerpo']['modo'] ?? '') === 'estado' && !isset($n['cuerpo']['previa']), '6) publicar pide «Estado del proyecto» a n8n (modo estado)');
ok((string) at_pt_plan($id)->payload === $payload_antes && (string) at_pt_plan($id)->estado === 'enviado', '6) el plan firmado no cambia (D3)');

$r = $correr('at_av_borrador', ['cierre_id' => $cid]);
ok($msg($r) === 'av_borrador' && $leer()['cierres'][0]['estado'] === 'borrador' && $leer()['render'] === at_av_registro_vacio() && $r['n8n'] === [], '7) volver a borrador el único publicado: la presentación deja de mostrarse y no se pide render');
$r = $correr('at_av_publicar', ['cierre_id' => $cid], 'caido');
ok($msg($r) === 'av_publicado_sin_render' && $leer()['cierres'][0]['estado'] === 'publicado' && $leer()['render']['estado'] === 'error', '7) n8n caído al publicar: queda publicado y el render en error (Review Focus 5)');
$r = $correr('at_av_reintentar', []);
ok($msg($r) === 'av_reintento' && (($r['n8n'][0]['cuerpo']['modo'] ?? '') === 'estado'), '8) «Reintentar» vuelve a pedir el render');
$r = $correr('at_av_previa', []);
ok($msg($r) === 'av_previa' && (($r['n8n'][0]['cuerpo']['previa'] ?? null) === true), '9) la vista previa va con previa: true (R3)');

$r = $correr('at_av_bloques', ['bloques' => [['nombre' => 'Desarrollo', 'estado' => 'en_curso', 'inicio_real' => '2026-10-12', 'fin_real' => '']]]);
ok($msg($r) === 'av_bloques' && ($leer()['bloques']['Desarrollo']['estado'] ?? '') === 'en_curso' && count($r['n8n']) === 1, '10) bloques guardados y presentación pedida');
$r = $correr('at_av_bloques', ['bloques' => [['nombre' => 'Inventado', 'estado' => 'listo']]]);
ok($msg($r) === 'av_listas_error' && ($leer()['bloques']['Desarrollo']['estado'] ?? '') === 'en_curso', '10) un bloque que no existe: error y no se pierde lo guardado');

$r = $correr('at_av_listas', ['cambios_plan' => '2026-10-13 | Publicidad al mensual | Acordado por correo', 'necesitamos' => "Logo en alta\n✓ Acceso al dominio", 'alcance_fase1' => 'Sitio web', 'alcance_mensual' => 'Publicidad']);
$av = $leer();
ok($msg($r) === 'av_listas' && count($av['cambios_plan']) === 1 && $av['necesitamos'][1]['estado'] === 'resuelto' && $av['alcance'] === ['fase1' => ['Sitio web'], 'mensual' => ['Publicidad']], '11) cambios, necesitamos y alcance guardados');
$r = $correr('at_av_listas', ['cambios_plan' => '13-10 | mal']);
ok($msg($r) === 'av_listas_error' && count($leer()['cambios_plan']) === 1, '11) una línea mal escrita: error y nada cambia');

$r = $correr('at_av_quitar', ['cierre_id' => $cid]);
ok($msg($r) === 'av_sin_cierre' && count($leer()['cierres']) === 1, '12) un cierre publicado no se quita');
$correr('at_av_borrador', ['cierre_id' => $cid]);
$foto = at_av_nombre_de_url((string) ($leer()['evidencias'][$cid . '-portada']['url'] ?? ''));
$r = $correr('at_av_quitar', ['cierre_id' => $cid]);
ok($msg($r) === 'av_quitado' && $leer()['cierres'] === [] && $foto !== '' && !is_file(at_av_dir($id)['dir'] . $foto), '12) quitar un borrador saca el cierre y borra su archivo');

$r = pt_correr_accion('at_av_importar', 0, 'at_pt_plan_' . $id, ['cierre_texto' => wp_json_encode($archivo), 'plan_id' => (string) $id, 'crm_id' => (string) $crm]);
ok($r['redirect'] === '' && $leer()['cierres'] === [], '13) sin sesión ni nonce válido: no hace nada');

ob_start();
at_pt_render_plan(at_pt_plan($id), $crm);
$h = (string) ob_get_clean();
ok(str_contains($h, 'id="at-av"') && str_contains($h, 'value="at_av_importar"') && str_contains($h, 'value="at_av_bloques"') && str_contains($h, 'value="at_av_listas"') && str_contains($h, 'Importar cierre') && str_contains($h, esc_url(at_pt_url_ver_estado(home_url(), $cod))), '14) la pestaña de un plan enviado muestra la sección «Avance»');
ptc_estado($id, 'listo');
ob_start();
at_pt_render_plan(at_pt_plan($id), $crm);
ok(!str_contains((string) ob_get_clean(), 'id="at-av"'), '14) un plan que no está enviado no la muestra');
fin();
```

Run: `AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/avance-panel-wp-test.php`
Expected: «Faltan funciones: at_av_render_seccion…».

- [ ] **Step 3: La sección y sus acciones**

Crear `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php`:

```php
<?php
/**
 * Estado del proyecto (Etapa 2): sección «📈 Avance» de la pestaña «🗓️ Plan de trabajo» (solo planes enviados) y sus
 * acciones admin-post (spec §5). Cada acción: manage_options + nonce del plan (at_pt_accion_plan) y vuelta a la ficha
 * con pt_msg; los detalles (errores de la importación, avisos) van con at_pt_guardar_detalles. Lo que cambia algo
 * visible para el cliente rehace «Estado del proyecto» si hay al menos un cierre publicado (R4). Publicar no envía nada (D5).
 */
if (!defined('ABSPATH')) {
	exit;
}

/** Avisos de la sección: se suman a at_pt_mensajes_panel(). */
function at_av_mensajes(): array {
	return [
		'av_importado'            => ['ok', 'Cierre guardado como borrador. Revísalo y publícalo cuando esté listo; el cliente todavía no lo ve.'],
		'av_importado_faltan'     => ['aviso', 'Cierre guardado como borrador, pero faltan archivos por subir (detalle abajo). No se puede publicar hasta subirlos.'],
		'av_import_error'         => ['error', 'No se guardó el cierre: el archivo tiene errores (detalle abajo). No cambió nada.'],
		'av_subido'               => ['ok', 'Archivo subido.'],
		'av_subida_error'         => ['error', 'No se subió el archivo (detalle abajo).'],
		'av_publicado'            => ['ok', 'Cierre publicado. Estamos rehaciendo «Estado del proyecto»: queda listo en unos minutos. Al cliente no se le envió nada.'],
		'av_publicado_sin_render' => ['aviso', 'Cierre publicado, pero n8n no recibió el pedido de la presentación. Usa «Reintentar» en un rato.'],
		'av_no_publicable'        => ['error', 'No se publicó (detalle abajo).'],
		'av_borrador'             => ['ok', 'El cierre volvió a borrador: el cliente ya no lo ve.'],
		'av_quitado'              => ['ok', 'Cierre quitado, con sus archivos.'],
		'av_bloques'              => ['ok', 'Bloques guardados.'],
		'av_listas'               => ['ok', 'Cambios de plan, «necesitamos de ti» y alcance guardados.'],
		'av_listas_error'         => ['error', 'No se guardó (detalle abajo). No cambió nada.'],
		'av_reintento'            => ['ok', 'Pedimos de nuevo «Estado del proyecto». Queda listo en unos minutos.'],
		'av_reintento_fallo'      => ['error', 'n8n no recibió el pedido. Inténtalo de nuevo en un rato.'],
		'av_previa'               => ['ok', 'Pedimos la vista previa con borradores. Recarga en un minuto para ver el enlace.'],
		'av_sin_publicados'       => ['aviso', 'Todavía no hay cierres publicados: no hay presentación que rehacer.'],
		'av_sin_cierres'          => ['aviso', 'Importa un cierre antes de pedir la vista previa.'],
		'av_no_enviado'           => ['error', 'El avance se carga solo en planes ya enviados al cliente.'],
		'av_sin_columna'          => ['error', 'Falta preparar la base para el avance: vuelve a entrar al escritorio de WordPress e inténtalo de nuevo.'],
		'av_error_guardar'        => ['error', 'No se pudo guardar el avance en la base. Inténtalo de nuevo.'],
		'av_sin_cierre'           => ['error', 'Esa acción no se pudo hacer con ese cierre (detalle abajo, si hay).'],
	];
}

add_action('admin_post_at_av_importar', 'at_av_accion_importar');
add_action('admin_post_at_av_subir', 'at_av_accion_subir');
add_action('admin_post_at_av_publicar', 'at_av_accion_publicar');
add_action('admin_post_at_av_borrador', 'at_av_accion_borrador');
add_action('admin_post_at_av_quitar', 'at_av_accion_quitar');
add_action('admin_post_at_av_bloques', 'at_av_accion_bloques');
add_action('admin_post_at_av_listas', 'at_av_accion_listas');
add_action('admin_post_at_av_reintentar', 'at_av_accion_reintentar');
add_action('admin_post_at_av_previa', 'at_av_accion_previa');

/** Permiso, nonce, plan enviado y migración hecha; si no, vuelve a la ficha. Devuelve [fila, crm, avance]. */
function at_av_accion_inicio(): array {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	if ((string) $fila->estado !== 'enviado') {
		at_pt_volver($crm, $id, 'av_no_enviado');
	}
	if (!at_av_columna_lista()) {
		at_pt_volver($crm, $id, 'av_sin_columna');
	}
	return [$fila, $crm, at_av_leer($fila)];
}

function at_av_guardar_o_volver(int $id, int $crm, array $avance): void {
	if (!at_av_guardar($id, $avance)) {
		at_pt_volver($crm, $id, 'av_error_guardar');
	}
}

function at_av_cierre_post(): string {
	$c = sanitize_key(wp_unslash($_POST['cierre_id'] ?? ''));
	return preg_match('/^c[a-z0-9]{6}$/', $c) ? $c : '';
}

/** «Importar cierre» (y «Guardar cambios» de un borrador, R2): archivo JSON o texto, más los archivos de sus evidencias. */
function at_av_accion_importar(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$texto = '';
	$f = $_FILES['cierre_json'] ?? null;
	if (is_array($f) && (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int) ($f['size'] ?? 0) <= 1048576) {
		$tmp = (string) ($f['tmp_name'] ?? '');
		if ($tmp !== '' && (is_uploaded_file($tmp) || (defined('AT_AV_PRUEBAS') && AT_AV_PRUEBAS === true && is_file($tmp)))) {
			$texto = (string) file_get_contents($tmp);
		}
	}
	if ($texto === '') {
		$texto = (string) wp_unslash($_POST['cierre_texto'] ?? '');
	}
	$datos = json_decode($texto, true);
	if (!is_array($datos)) {
		at_pt_guardar_detalles($id, ['El archivo no es un JSON válido (' . json_last_error_msg() . ').']);
		at_pt_volver($crm, $id, 'av_import_error');
	}
	$v = at_av_validar_import($datos, (string) $fila->codigo, at_pt_payload($fila), $avance);
	if (!$v['ok']) {
		at_pt_guardar_detalles($id, $v['errores']);
		at_pt_volver($crm, $id, 'av_import_error');
	}
	$ids = array_column($avance['cierres'], 'id');
	do {
		$nuevo_id = 'c' . strtolower(wp_generate_password(6, false, false));
	} while (in_array($nuevo_id, $ids, true));
	$r = at_av_aplicar_import($avance, $v, $nuevo_id, current_time('mysql'));
	$nuevo = $r['avance'];
	$avisos = $v['avisos'];
	$borrar = $r['archivos'];
	$subidos = at_en_archivos_subidos((array) ($_FILES['archivos'] ?? []));
	if ($subidos) {
		$a = at_av_asignar_archivos($nuevo, $r['cierre_id'], $subidos, $id);
		$nuevo = $a['avance'];
		$avisos = array_merge($avisos, $a['errores'], $a['avisos']);
		$borrar = array_merge($borrar, $a['archivos']);
	}
	at_av_guardar_o_volver($id, $crm, $nuevo);
	at_av_borrar_archivos($id, $borrar);
	$faltan = at_av_faltan_archivos($nuevo, $r['cierre_id']);
	if ($faltan) {
		$avisos[] = 'Faltan por subir: ' . implode(', ', $faltan) . '.';
	}
	at_pt_guardar_detalles($id, $avisos);
	// Bloques, cambios, necesitamos y alcance pueden haber cambiado y esos sí se ven (R4: solo si hay algo publicado).
	at_av_refrescar($id);
	at_pt_volver($crm, $id, $faltan ? 'av_importado_faltan' : 'av_importado');
}

/** Sube el archivo o el póster de una evidencia («subir después, uno por uno»). */
function at_av_accion_subir(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$eid = (string) wp_unslash($_POST['evidencia_id'] ?? '');
	$campo = ($_POST['campo'] ?? '') === 'poster' ? 'poster' : 'archivo';
	$f = $_FILES['archivo'] ?? null;
	if (!preg_match('/^c[a-z0-9]{6}-[a-z0-9-]{1,40}$/', $eid) || !isset($avance['evidencias'][$eid]) || !is_array($f)) {
		at_pt_volver($crm, $id, 'av_sin_cierre');
	}
	$r = at_av_subir_una($avance, $eid, $campo, $f, $id);
	if ($r['error'] !== '') {
		at_pt_guardar_detalles($id, [$r['error']]);
		at_pt_volver($crm, $id, 'av_subida_error');
	}
	at_av_guardar_o_volver($id, $crm, $r['avance']);
	at_av_borrar_archivos($id, $r['archivos']);
	at_av_refrescar($id);
	at_pt_volver($crm, $id, 'av_subido');
}

function at_av_accion_publicar(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$r = at_av_cambiar_estado_cierre($avance, at_av_cierre_post(), 'publicado', current_time('mysql'));
	if (is_string($r)) {
		at_pt_guardar_detalles($id, [$r]);
		at_pt_volver($crm, $id, $r === 'Ese cierre no existe.' ? 'av_sin_cierre' : 'av_no_publicable');
	}
	at_av_guardar_o_volver($id, $crm, $r);
	$motivo = at_av_refrescar($id);
	at_pt_volver($crm, $id, $motivo === '' ? 'av_publicado' : 'av_publicado_sin_render');
}

/** Vuelve a borrador. La presentación vieja todavía muestra ese cierre: se deja de mostrar ya (Review Focus 2) y, si
 *  quedan otros publicados, se rehace sin él. */
function at_av_accion_borrador(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$r = at_av_cambiar_estado_cierre($avance, at_av_cierre_post(), 'borrador', current_time('mysql'));
	if (is_string($r)) {
		at_pt_guardar_detalles($id, [$r]);
		at_pt_volver($crm, $id, 'av_sin_cierre');
	}
	$r['render'] = at_av_publicados($r) ? array_merge(at_av_registro_vacio(), ['estado' => 'pendiente', 'at' => current_time('mysql')]) : at_av_registro_vacio();
	at_av_guardar_o_volver($id, $crm, $r);
	at_av_refrescar($id);
	at_pt_volver($crm, $id, 'av_borrador');
}

function at_av_accion_quitar(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$r = at_av_quitar_cierre($avance, at_av_cierre_post());
	if (is_string($r)) {
		at_pt_guardar_detalles($id, [$r]);
		at_pt_volver($crm, $id, 'av_sin_cierre');
	}
	at_av_guardar_o_volver($id, $crm, $r['avance']);
	at_av_borrar_archivos($id, $r['archivos']);
	at_pt_volver($crm, $id, 'av_quitado');
}

function at_av_accion_bloques(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$errores = [];
	$bloques = at_av_bloques_de_post(wp_unslash($_POST['bloques'] ?? []), at_pt_payload($fila), $errores);
	if ($errores) {
		at_pt_guardar_detalles($id, $errores);
		at_pt_volver($crm, $id, 'av_listas_error');
	}
	$avance['bloques'] = $bloques;
	at_av_guardar_o_volver($id, $crm, $avance);
	at_av_refrescar($id);
	at_pt_volver($crm, $id, 'av_bloques');
}

function at_av_accion_listas(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$errores = [];
	$cambios = at_av_cambios_de_texto((string) wp_unslash($_POST['cambios_plan'] ?? ''), at_av_bloques_plan(at_pt_payload($fila)), $errores);
	$necesitamos = at_av_necesitamos_de_texto((string) wp_unslash($_POST['necesitamos'] ?? ''), $avance['necesitamos'], current_time('Y-m-d'), $errores);
	$fase1 = at_av_lista_de_texto((string) wp_unslash($_POST['alcance_fase1'] ?? ''), 'fase1', $errores);
	$mensual = at_av_lista_de_texto((string) wp_unslash($_POST['alcance_mensual'] ?? ''), 'mensual', $errores);
	if ($errores) {
		at_pt_guardar_detalles($id, $errores);
		at_pt_volver($crm, $id, 'av_listas_error');
	}
	$avance['cambios_plan'] = $cambios;
	$avance['necesitamos'] = $necesitamos;
	$avance['alcance'] = ['fase1' => $fase1, 'mensual' => $mensual];
	at_av_guardar_o_volver($id, $crm, $avance);
	at_av_refrescar($id);
	at_pt_volver($crm, $id, 'av_listas');
}

function at_av_accion_reintentar(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	if (!at_av_publicados($avance)) {
		at_pt_volver($crm, $id, 'av_sin_publicados');
	}
	at_pt_volver($crm, $id, at_av_pedir_render($id, false) === '' ? 'av_reintento' : 'av_reintento_fallo');
}

function at_av_accion_previa(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	if (!$avance['cierres']) {
		at_pt_volver($crm, $id, 'av_sin_cierres');
	}
	at_pt_volver($crm, $id, at_av_pedir_render($id, true) === '' ? 'av_previa' : 'av_reintento_fallo');
}

/** Campos ocultos de cada formulario de la sección (acción, plan, ficha, nonce y extras). */
function at_av_ocultos(string $accion, int $plan_id, int $crm_id, array $extra = []): string {
	$h = '<input type="hidden" name="action" value="' . esc_attr($accion) . '">'
		. '<input type="hidden" name="plan_id" value="' . $plan_id . '">'
		. '<input type="hidden" name="crm_id" value="' . $crm_id . '">'
		. '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('at_pt_plan_' . $plan_id)) . '">';
	foreach ($extra as $k => $v) {
		$h .= '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) $v) . '">';
	}
	return $h;
}

/** Botón dentro de su propio formulario (con confirmación opcional). */
function at_av_boton(string $accion, int $plan_id, int $crm_id, string $texto, array $extra = [], string $confirmar = '', bool $deshabilitado = false): string {
	$onsubmit = $confirmar !== '' ? ' onsubmit="return confirm(' . esc_attr(wp_json_encode($confirmar, JSON_UNESCAPED_UNICODE)) . ');"' : '';
	return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="at-av-form-boton"' . $onsubmit . '>'
		. at_av_ocultos($accion, $plan_id, $crm_id, $extra)
		. '<button type="submit" class="button"' . ($deshabilitado ? ' disabled' : '') . '>' . esc_html($texto) . '</button></form>';
}

/** Sección «📈 Avance» (solo con el plan enviado; la llama at_pt_render_plan). */
function at_av_render_seccion(object $fila, int $crm_id): void {
	$id = (int) $fila->id;
	$codigo = (string) $fila->codigo;
	$accion = esc_url(admin_url('admin-post.php'));
	echo '<section class="at-av" id="at-av"><h3>📈 Avance del proyecto · «Estado del proyecto»</h3>';
	if (!at_av_columna_lista()) {
		echo '<div class="notice notice-warning inline"><p>Falta preparar la base para el avance: vuelve a entrar al escritorio de WordPress (la migración corre sola) y recarga esta pestaña.</p></div></section>';
		return;
	}
	$av = at_av_leer($fila);
	$plan = at_pt_payload($fila);
	$publicados = at_av_publicados($av);
	$publica = at_pt_url_ver_estado(home_url(), $codigo);

	// Presentación para el cliente y vista previa.
	$etiquetas = ['' => 'Todavía no se genera', 'pendiente' => 'Generándose', 'listo' => 'Lista', 'error' => 'Con error'];
	$reg = $av['render'];
	echo '<div class="at-av-render"><p><strong>Presentación para el cliente:</strong> ' . esc_html($etiquetas[$reg['estado']] ?? $reg['estado'])
		. ($reg['at'] !== '' ? ' · ' . esc_html($reg['at']) : '') . '</p>';
	if ($reg['nota'] !== '') {
		echo '<p class="description">' . esc_html($reg['nota']) . '</p>';
	}
	echo '<p>El cliente la ve en <a href="' . esc_url($publica) . '" target="_blank" rel="noopener">' . esc_html($publica) . '</a>'
		. ($publicados ? '' : ' (mientras no publiques un cierre, ve «Pronto verás aquí el avance de tu proyecto»)') . '.</p><p>';
	if ($reg['view_url'] !== '') {
		echo '<a class="button" href="' . esc_url($reg['view_url']) . '" target="_blank" rel="noopener">👁️ Ver la presentación publicada</a> ';
		if ($reg['pdf_url'] !== '') {
			echo '<a class="button" href="' . esc_url($reg['pdf_url']) . '" target="_blank" rel="noopener">📄 PDF</a> ';
		}
	}
	if ($publicados) {
		echo at_av_boton('at_av_reintentar', $id, $crm_id, '🔄 Rehacer la presentación');
	}
	if ($av['cierres']) {
		echo at_av_boton('at_av_previa', $id, $crm_id, '👁️ Pedir vista previa con borradores');
	}
	echo '</p>';
	$pr = $av['render_previa'];
	if ($pr['estado'] === 'listo' && $pr['view_url'] !== '') {
		echo '<p><a href="' . esc_url($pr['view_url']) . '" target="_blank" rel="noopener">Abrir la vista previa con borradores</a> <span class="description">(solo para ti; el cliente no la ve)</span></p>';
	} elseif (in_array($pr['estado'], ['pendiente', 'error'], true)) {
		echo '<p class="description">Vista previa: ' . esc_html($pr['estado'] === 'pendiente' ? 'generándose…' : 'con error. ' . $pr['nota']) . '</p>';
	}
	echo '</div>';

	// Cierres.
	echo '<h4>Cierres de hito</h4>';
	if (!$av['cierres']) {
		echo '<p class="description">Todavía no hay cierres. Impórtalos abajo con «Importar cierre».</p>';
	} else {
		echo '<table class="widefat striped at-av-cierres"><thead><tr><th>Cierre</th><th>Bloques</th><th>Listo el</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
		foreach ($av['cierres'] as $c) {
			$cid = (string) $c['id'];
			$pub = ($c['estado'] ?? '') === 'publicado';
			$faltan = at_av_faltan_archivos($av, $cid);
			echo '<tr><td><strong>' . esc_html((string) $c['titulo']) . '</strong></td><td>' . esc_html(implode(', ', (array) $c['bloques'])) . '</td><td>'
				. esc_html(at_pt_panel_fecha((string) $c['fecha_real'], true)) . '</td><td>'
				. ($pub ? '<span class="at-av-chip is-pub">Publicado</span>' : '<span class="at-av-chip">Borrador</span>')
				. ($faltan ? '<br><small>Faltan archivos: ' . esc_html(implode(', ', $faltan)) . '</small>' : '') . '</td><td>';
			if ($pub) {
				echo at_av_boton('at_av_borrador', $id, $crm_id, '↩️ Volver a borrador', ['cierre_id' => $cid], 'El cliente dejará de ver este cierre. ¿Volver a borrador?');
			} else {
				echo at_av_boton('at_av_publicar', $id, $crm_id, '✅ Publicar', ['cierre_id' => $cid], 'El cliente verá este cierre en «Estado del proyecto». No se le envía nada. ¿Publicar?', (bool) $faltan);
				echo at_av_boton('at_av_quitar', $id, $crm_id, '🗑️ Quitar', ['cierre_id' => $cid], '¿Quitar este cierre y sus archivos?');
			}
			echo '</td></tr><tr class="at-av-detalle"><td colspan="5"><details><summary>Archivos y edición</summary>';
			foreach (array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? [])) as $eid) {
				$e = $av['evidencias'][$eid] ?? null;
				if (!is_array($e) || !in_array($e['tipo'] ?? '', ['captura', 'video'], true)) {
					continue;
				}
				$campos = $e['tipo'] === 'video' ? ['archivo' => 'Video', 'poster' => 'Póster (opcional)'] : ['archivo' => 'Imagen'];
				foreach ($campos as $campo => $nombre) {
					$url = (string) ($campo === 'poster' ? ($e['poster'] ?? '') : ($e['url'] ?? ''));
					echo '<form method="post" action="' . $accion . '" enctype="multipart/form-data" class="at-av-subir">'
						. at_av_ocultos('at_av_subir', $id, $crm_id, ['evidencia_id' => $eid, 'campo' => $campo])
						. '<span>' . esc_html((string) $e['titulo']) . ' · ' . esc_html($nombre) . ': '
						. ($url !== '' ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">subido</a>' : '<strong>falta</strong>')
						. ($campo === 'archivo' && ($e['archivo'] ?? '') !== '' ? ' <code>' . esc_html((string) $e['archivo']) . '</code>' : '') . '</span> '
						. '<input type="file" name="archivo" required accept="' . ($campo === 'archivo' && $e['tipo'] === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp') . '"> '
						. '<button type="submit" class="button">Subir</button></form>';
				}
			}
			if ($pub) {
				echo '<p class="description">Para editar un cierre publicado, primero vuelve a borrador.</p>';
			} else {
				$json = (string) wp_json_encode(at_av_exportar_cierre($av, $cid, $codigo), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				echo '<form method="post" action="' . $accion . '" class="at-av-editar">' . at_av_ocultos('at_av_importar', $id, $crm_id)
					. '<label>Editar el cierre (formato at-cierre-v1)<textarea name="cierre_texto" rows="14" class="large-text code">' . esc_textarea($json) . '</textarea></label>'
					. '<p class="description">Se valida igual que «Importar cierre». Las evidencias que mantienen su nombre de archivo conservan el que ya subiste.</p>'
					. '<p><button type="submit" class="button">💾 Guardar cambios del cierre</button></p></form>';
			}
			echo '</details></td></tr>';
		}
		echo '</tbody></table>';
	}

	// Importar cierre.
	echo '<h4>Importar cierre</h4><form method="post" action="' . $accion . '" enctype="multipart/form-data" class="at-av-importar">'
		. at_av_ocultos('at_av_importar', $id, $crm_id)
		. '<p><label>Archivo del cierre (.json, formato at-cierre-v1) <input type="file" name="cierre_json" accept=".json,application/json"></label></p>'
		. '<p><label>…o pega su contenido<textarea name="cierre_texto" rows="5" class="large-text code"></textarea></label></p>'
		. '<p><label>Archivos de las evidencias (capturas, videos y pósters, con el nombre que dice el archivo) <input type="file" name="archivos[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"></label></p>'
		. '<p class="description">Se guarda como borrador: el cliente no ve nada hasta que lo publiques. Formato y ejemplo: Docs/METODO_AT/ESTADO-DEL-PROYECTO.md.</p>'
		. '<p><button type="submit" class="button button-primary">📥 Guardar como borrador</button></p></form>';

	// Bloques.
	$opciones = ['por_venir' => 'Por venir', 'en_curso' => 'En curso', 'listo' => 'Listo'];
	echo '<h4>Bloques del plan</h4><form method="post" action="' . $accion . '" class="at-av-bloques">' . at_av_ocultos('at_av_bloques', $id, $crm_id)
		. '<table class="widefat striped"><thead><tr><th>Bloque</th><th>Planificado</th><th>Estado</th><th>Inicio real</th><th>Fin real</th></tr></thead><tbody>';
	foreach (at_av_estado_bloques($plan, $av) as $i => $b) {
		$g = is_array($av['bloques'][$b['nombre']] ?? null) ? $av['bloques'][$b['nombre']] : ['estado' => 'por_venir', 'inicio_real' => '', 'fin_real' => ''];
		$sel = '';
		foreach ($opciones as $k => $t) {
			$sel .= '<option value="' . $k . '"' . selected((string) ($g['estado'] ?? 'por_venir'), $k, false) . '>' . $t . '</option>';
		}
		$cubierto = $b['estado'] === 'listo' && ($g['estado'] ?? '') !== 'listo' ? '<br><small>Listo por un cierre publicado</small>' : '';
		echo '<tr><td>' . esc_html($b['nombre']) . '<input type="hidden" name="bloques[' . $i . '][nombre]" value="' . esc_attr($b['nombre']) . '"></td>'
			. '<td>' . esc_html(at_pt_panel_fecha($b['plan_desde']) . ' – ' . at_pt_panel_fecha($b['plan_hasta'])) . '</td>'
			. '<td><select name="bloques[' . $i . '][estado]">' . $sel . '</select>' . $cubierto . '</td>'
			. '<td><input type="date" name="bloques[' . $i . '][inicio_real]" value="' . esc_attr((string) ($g['inicio_real'] ?? '')) . '"></td>'
			. '<td><input type="date" name="bloques[' . $i . '][fin_real]" value="' . esc_attr((string) ($g['fin_real'] ?? '')) . '"></td></tr>';
	}
	echo '</tbody></table><p><button type="submit" class="button">💾 Guardar bloques</button></p></form>';

	// Cambios de plan, necesitamos y alcance.
	$bp = at_av_bloques_plan($plan);
	$fase1_def = implode("\n", array_values(array_unique(array_filter(array_column($bp, 'entregable'), 'strlen'))));
	$mensual_def = implode("\n", array_map('strval', (array) ($plan['soporte']['mensuales'] ?? [])));
	echo '<h4>Cambios de plan, «necesitamos de ti» y alcance</h4><form method="post" action="' . $accion . '" class="at-av-listas">' . at_av_ocultos('at_av_listas', $id, $crm_id)
		. '<label class="at-pt-campo">Cambios de plan acordados (uno por línea: «2026-10-13 | título | detalle»)<textarea name="cambios_plan" rows="3" class="large-text">' . esc_textarea(at_av_texto_de_cambios($av['cambios_plan'])) . '</textarea></label>'
		. '<label class="at-pt-campo">Lo que necesitamos del cliente (uno por línea; «✓ » al inicio = resuelto)<textarea name="necesitamos" rows="3" class="large-text">' . esc_textarea(at_av_texto_de_necesitamos($av['necesitamos'])) . '</textarea></label>'
		. '<label class="at-pt-campo">Qué incluye la Fase 1 (uno por línea; vacío = los entregables del plan)<textarea name="alcance_fase1" rows="3" class="large-text" placeholder="' . esc_attr($fase1_def) . '">' . esc_textarea(implode("\n", $av['alcance']['fase1'])) . '</textarea></label>'
		. '<label class="at-pt-campo">Qué incluye el servicio mensual (uno por línea; vacío = los mensuales del plan)<textarea name="alcance_mensual" rows="2" class="large-text" placeholder="' . esc_attr($mensual_def) . '">' . esc_textarea(implode("\n", $av['alcance']['mensual'])) . '</textarea></label>'
		. '<p><button type="submit" class="button">💾 Guardar</button></p></form>';
	echo '</section>';
}
```

- [ ] **Step 4: Engancharla al panel del plan**

En `wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php`:

1. En `at_pt_mensajes_panel()`, cambiar `return [` por `return array_merge([` y el cierre `];` de esa función por:
```php
	], function_exists('at_av_mensajes') ? at_av_mensajes() : []);
```
2. Al final de `at_pt_render_plan()`, reemplazar:
```
		<?php endif; ?>
	</div>
	<?php
}

// ---------------------------------------------------------------------------------------------------------------
// Acciones admin-post de la pestaña. Todas terminan en at_pt_volver() (redirect + exit).
```
por:
```
		<?php endif; ?>
		<?php if ($estado === 'enviado' && function_exists('at_av_render_seccion')) { at_av_render_seccion($fila, $crm_id); } ?>
	</div>
	<?php
}

// ---------------------------------------------------------------------------------------------------------------
// Acciones admin-post de la pestaña. Todas terminan en at_pt_volver() (redirect + exit).
```

En `cargar.php`, después de `require_once __DIR__ . '/panel.php';`:
```php
require_once __DIR__ . '/avance-panel.php';
```

Al final de `wp-content/themes/automatiza-tech/assets/css/plan-trabajo.css`:
```css
/* Estado del proyecto (Etapa 2): sección «Avance» de un plan enviado. */
.at-av { margin-top: 28px; padding-top: 18px; border-top: 2px solid #00d9c0; }
.at-av h4 { margin: 22px 0 8px; }
.at-av-chip { display: inline-block; padding: 2px 10px; border-radius: 999px; background: #f0f0f1; font-weight: 600; }
.at-av-chip.is-pub { background: #d1fae5; color: #065f46; }
.at-av-form-boton { display: inline-block; margin: 2px 6px 2px 0; }
.at-av-subir { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 6px 0; }
.at-av textarea.code { font-family: Consolas, monospace; font-size: 12px; }
.at-av-detalle details { margin: 4px 0 8px; }
```

- [ ] **Step 5: Correr las pruebas y ver que pasan**

Run (comando del Step 2). Expected: «TODO OK». Después la línea base: `panel-acciones-wp-test`, `panel-envio-wp-test`, `panel-js-wp-test` («TODO OK») y `panel-render-wp-test` con su única falla previa conocida.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php wp-content/themes/automatiza-tech/assets/css/plan-trabajo.css tests/plan/accion-wp-test-run.php tests/plan/avance-panel-wp-test.php
git commit -m "feat(estado): sección «Avance» del plan con importar, publicar y evidencias

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: `ver-estado.php` completo

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/vista.php` (extraer el diálogo de agenda; `at_pt_html_ver_estado` completo)
- Modify: `ver-estado.php` (raíz del sitio: pasa `agendar=1`)
- Test: `tests/plan/ver-estado-wp-test.php` (ampliar) y `tests/plan/ver-plan-wp-test.php` (sin cambios: debe seguir pasando)

**Interfaces:**
- Consumes: Task 5 `at_av_vista_publica(object $fila): string`; del plan `at_pt_seguimiento_pendiente(string $codigo): ?object`, `at_pt_url_whatsapp_agenda(string $tel, string $codigo)`, `at_pt_token_agenda_hoy(string $codigo)`, `at_pt_url_ver_plan()`.
- Produces: `at_pt_html_agenda(string $codigo, bool $abrir): string` (diálogo + scripts, idéntico al de `ver-plan.php`); `at_pt_html_ver_estado(?object $fila, bool $abrir_agenda = false): string`.

- [ ] **Step 1: Escribir la prueba que falla**

En `tests/plan/ver-estado-wp-test.php`, reemplazar la línea `fin();` del final por:

```php
// Etapa 2: con un cierre publicado y la presentación lista, la página la muestra con el plan inicial y la agenda.
at_av_migrar_esquema();
at_pt_guardar((int) $plan->id, ['estado' => 'enviado']);
$plan_payload = at_pt_payload(at_pt_plan((int) $plan->id));
$d = ['formato' => 'at-cierre-v1', 'plan' => $codigo, 'cierre' => ['titulo' => 'Diseño terminado', 'bloques' => ['Diseño'], 'fecha_real' => '2026-10-10',
	'antes' => ['texto' => 'Antes.'], 'despues' => ['texto' => 'Después.', 'evidencias' => ['sitio']]],
	'evidencias' => [['ref' => 'sitio', 'tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'https://example.com/']]];
$v = at_av_validar_import($d, $codigo, $plan_payload, at_av_vacio());
$ap = at_av_aplicar_import(at_av_vacio(), $v, 'cabc123', '2026-10-13 10:00:00')['avance'];
$vista_estado = 'https://' . at_pt_host_renderer() . '/p/' . $codigo . '-estado/index.html';
$ap['render'] = ['estado' => 'listo', 'view_url' => $vista_estado, 'pdf_url' => '', 'nota' => '', 'at' => '2026-10-13 10:05:00'];
at_av_guardar((int) $plan->id, $ap);
ok(at_pt_html_ver_estado(at_pt_fila_ver_estado($codigo)) !== '' && strpos(at_pt_html_ver_estado(at_pt_fila_ver_estado($codigo)), 'Pronto verás aquí') !== false, 'Etapa 2: con la presentación lista pero el cierre en borrador, sigue «Pronto…» (Review Focus 2)');
at_av_guardar((int) $plan->id, at_av_cambiar_estado_cierre($ap, 'cabc123', 'publicado', '2026-10-13 10:00:00'));
$h = at_pt_html_ver_estado(at_pt_fila_ver_estado($codigo));
ok(strpos($h, '<iframe src="' . esc_url($vista_estado) . '"') !== false && strpos($h, 'Pronto verás aquí') === false, 'Etapa 2: con un cierre publicado y la presentación lista, la muestra');
ok(strpos($h, 'href="' . esc_url(at_pt_url_ver_plan(home_url(), $codigo)) . '"') !== false && strpos($h, 'Ver el plan de actividades inicial') !== false, 'Etapa 2: barra con el plan inicial');
ok(strpos($h, 'data-at-pt-abrir') !== false && strpos($h, 'id="at-pt-agenda"') !== false && strpos($h, 'plan-seguimiento') !== false, 'Etapa 2: «Agendar seguimiento» con el mismo diálogo de ver-plan.php');
ok(stripos($h, 'noindex') !== false && strpos($h, 'Borrador') === false, 'Etapa 2: noindex y nada de borradores');
$fallido = at_av_leer(at_pt_plan((int) $plan->id));
$fallido['render']['estado'] = 'error';
$fallido['render']['nota'] = 'el renderer no respondió';
at_av_guardar((int) $plan->id, $fallido);
ok(strpos(at_pt_html_ver_estado(at_pt_fila_ver_estado($codigo)), '<iframe src="' . esc_url($vista_estado) . '"') !== false, 'Etapa 2: un render fallido después no rompe la página: sigue la última lista (Review Focus 5)');
$fallido['render']['view_url'] = 'https://evil.example/p/' . $codigo . '-estado/index.html';
at_av_guardar((int) $plan->id, $fallido);
ok(strpos(at_pt_html_ver_estado(at_pt_fila_ver_estado($codigo)), 'evil.example') === false, 'Etapa 2: nunca muestra un enlace que no sea del renderer de ese plan');
$fallido['render']['view_url'] = $vista_estado;
at_av_guardar((int) $plan->id, $fallido);
ok(strpos(at_pt_html_ver_estado(at_pt_fila_ver_estado($codigo), true), '"abrir":true') !== false, 'Etapa 2: con agendar=1 el diálogo se abre solo');
fin();
```

Run:
```bash
AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/ver-estado-wp-test.php
```
Expected: FALLA en «con un cierre publicado y la presentación lista, la muestra».

- [ ] **Step 2: Extraer el diálogo de agenda**

En `wp-content/themes/automatiza-tech/inc/plan-trabajo/vista.php`, agregar después de `at_pt_fila_ver_plan()`:

```php
/** Diálogo de la agenda web (selector de horarios de la portada, at-agenda.js) y sus scripts. Lo usan ver-plan.php y
 *  ver-estado.php; la ruta plan-seguimiento solo agenda con el plan enviado. $abrir = vino con agendar=1. */
function at_pt_html_agenda(string $codigo, bool $abrir): string {
	$tema = get_template_directory_uri();
	$dir = get_template_directory();
	$ver = function (string $rel) use ($dir): string {
		$f = $dir . $rel;
		return file_exists($f) ? (string) filemtime($f) : '1';
	};
	$logo = defined('AT_CC_LOGO') ? AT_CC_LOGO : home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png');
	return '<dialog id="at-pt-agenda" class="at-pt-dlg" aria-labelledby="at-pt-agenda-titulo">'
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
		. '<script>window.AT_AGENDA = ' . wp_json_encode(['configUrl' => esc_url_raw(rest_url('automatiza-tech/v1/appointments-config'))], JSON_HEX_TAG) . ';'
		. 'window.AT_PT_VER = ' . wp_json_encode(['url' => esc_url_raw(rest_url('automatiza-tech/v1/plan-seguimiento')), 'codigo' => $codigo, 'token' => at_pt_token_agenda_hoy($codigo), 'abrir' => $abrir], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';</script>'
		. '<script src="' . esc_url($tema . '/assets/home-premium/at-agenda.js?v=' . $ver('/assets/home-premium/at-agenda.js')) . '"></script>'
		. '<script src="' . esc_url($tema . '/assets/js/plan-ver.js?v=' . $ver('/assets/js/plan-ver.js')) . '"></script>';
}
```

En `at_pt_html_ver_plan()`, reemplazar todo el bloque `if ($enviado && !$ya) { $html .= '<dialog …' … '</script>'; }` por:

```php
	if ($enviado && !$ya) {
		$html .= at_pt_html_agenda($codigo, $abrir_agenda);
	}
```

y borrar la variable `$logo` de esa función (ya no se usa ahí). El HTML de `ver-plan.php` debe quedar idéntico: `tests/plan/ver-plan-wp-test.php` sigue en «TODO OK».

- [ ] **Step 3: `ver-estado.php` con la presentación**

Reemplazar `at_pt_fila_ver_estado`'s docblock por «El plan de ese código si tiene página de «Estado del proyecto» (solo planes enviados). null si no.» y reemplazar `at_pt_html_ver_estado()` completa por:

```php
/** Documento HTML de ver-estado.php. Con al menos un cierre publicado y una presentación lista (at_av_vista_publica),
 *  la muestra con una barra: «Ver el plan de actividades inicial» y «Agendar seguimiento» (el diálogo de ver-plan.php).
 *  Si no, «Pronto verás aquí el avance de tu proyecto» con el enlace al plan. $abrir_agenda = vino con agendar=1. */
function at_pt_html_ver_estado(?object $fila, bool $abrir_agenda = false): string {
	$tema = get_template_directory_uri();
	$css = get_template_directory() . '/assets/css/plan-ver.css';
	$cabeza = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
		. '<meta name="robots" content="noindex, nofollow"><title>Estado del proyecto — AutomatizaTech</title>'
		. '<link rel="stylesheet" href="' . esc_url($tema . '/assets/css/plan-ver.css?v=' . (file_exists($css) ? (string) filemtime($css) : '1')) . '"></head>';
	if (!$fila) {
		return $cabeza . '<body class="at-pt-ver at-pt-ver--vacio"><main class="at-pt-vacio"><h1>Esta página no está disponible</h1>'
			. '<p>Revisa el enlace que te enviamos o escríbenos a contacto@automatizatech.cl.</p></main></body></html>';
	}
	$codigo = (string) $fila->codigo;
	$plan = at_pt_url_ver_plan(home_url(), $codigo);
	$vista = function_exists('at_av_vista_publica') ? at_av_vista_publica($fila) : '';
	if ($vista === '') {
		return $cabeza . '<body class="at-pt-ver at-pt-ver--vacio"><main class="at-pt-vacio"><h1>Pronto verás aquí el avance de tu proyecto</h1>'
			. '<p>Cada vez que terminemos una parte del plan, te mostraremos aquí qué hicimos y cómo quedó.</p>'
			. '<p><a class="at-pt-btn at-pt-btn--si" href="' . esc_url($plan) . '">Ver el plan de actividades inicial</a></p>'
			. '</main></body></html>';
	}
	$ya = at_pt_seguimiento_pendiente($codigo);
	$wa = at_pt_url_whatsapp_agenda(function_exists('at_cc_whatsapp_at') ? at_cc_whatsapp_at() : '56927002984', $codigo);
	$html = $cabeza . '<body class="at-pt-ver">'
		. '<iframe src="' . esc_url($vista) . '" title="Estado del proyecto" allow="fullscreen"></iframe>'
		. '<nav class="at-pt-barra" aria-label="Tu proyecto"><p>¿Revisamos juntos el avance?</p>'
		. '<a class="at-pt-btn at-pt-btn--sec" href="' . esc_url($plan) . '">📋 Ver el plan de actividades inicial</a>';
	if ($ya) {
		$html .= '<p class="at-pt-ya">Ya tienes tu llamada de seguimiento el ' . esc_html(at_pt_fecha_larga((string) $ya->meeting_date) . ' a las ' . substr((string) $ya->meeting_time, 0, 5)) . '.</p>';
	} else {
		$html .= '<button type="button" class="at-pt-btn at-pt-btn--si" data-at-pt-abrir>📅 Agendar seguimiento</button>';
	}
	if ($wa !== '') {
		$html .= '<a class="at-pt-btn at-pt-btn--sec" href="' . esc_url($wa) . '" target="_blank" rel="noopener">💬 Escribir a Tech por WhatsApp</a>';
	}
	$html .= '</nav>';
	if (!$ya) {
		$html .= at_pt_html_agenda($codigo, $abrir_agenda);
	}
	return $html . '</body></html>';
}
```

En `ver-estado.php` (raíz), cambiar la última línea por:

```php
echo at_pt_html_ver_estado($fila, isset($_GET['agendar']) && $_GET['agendar'] === '1');
```

- [ ] **Step 4: Correr las pruebas y ver que pasan**

Run:
```bash
for t in ver-estado ver-plan agenda-web agenda; do echo "== $t"; AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/$t-wp-test.php | tail -1; done
```
Expected: «TODO OK» en las cuatro.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/vista.php ver-estado.php tests/plan/ver-estado-wp-test.php
git commit -m "feat(estado): ver-estado.php muestra la presentación con el plan inicial y la agenda

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9: Guía, formato `at-cierre-v1` con ejemplo, punteros y grafo

**Files:**
- Create: `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md`
- Create: `Docs/METODO_AT/ejemplos/cierre-at-cierre-v1-ejemplo.json`
- Modify: `Docs/METODO_AT/PLAN-DE-TRABAJO.md` (sección «Estado del proyecto»: puntero a la Etapa 2)
- Modify: `CLAUDE.md` y `AGENTS.md` (puntero «Plan de trabajo…»: agregar «Etapa 2 LOCAL en `claude/estado-proyecto-e2`»)
- Modify: `graphify-out/` (con `graphify update .`)

**Interfaces:** ninguna de código. El ejemplo JSON debe validar con `at_av_validar_import` contra el plan de prueba de `tests/plan/fixtures-panel.php` (bloques «Diseño», «Desarrollo», «Puesta en marcha»), con el código inventado `AbCdEf123456`.

- [ ] **Step 1: Escribir la guía**

Crear `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md` con estas secciones, en español claro y con datos inventados (el repo es público; nada de códigos ni nombres reales de clientes):

1. **Qué es** (3 frases): las tres presentaciones (propuesta, plan congelado, «Estado del proyecto» viva); qué ve el cliente y cuándo (solo cierres publicados; publicar no envía nada).
2. **Cómo se usa (Luis)**: CRM › ficha › «🗓️ Plan de trabajo» › sección «📈 Avance» (solo con el plan enviado). Paso a paso: importar el cierre (JSON + archivos) → subir lo que falte → «Pedir vista previa con borradores» → «Publicar» → la presentación se rehace en unos minutos (panel muestra «Lista» o «Con error» + «Rehacer») → `ver-estado.php?id=<código>`. Volver a borrador, quitar, editar (R2), bloques, cambios, necesitamos y alcance.
3. **Formato `at-cierre-v1`**: tabla de campos (de la spec §3/§4) con tipos, topes y reglas: `formato`, `plan`, `cierre.{titulo, bloques, fecha_real, entregable_id, antes, durante, despues}`, `evidencias[]` por tipo (`cifra` exige `valor`, `fuente` y `fecha`; `enlace` https y nunca `easypanel`; `captura` y `video` con `archivo`; `video` con `poster` opcional), `bloques`, `cambios_plan`, `necesitamos`, `alcance`. Qué pasa al reimportar el mismo título (borrador: se reemplaza conservando archivos con la misma ref y nombre; publicado: se rechaza).
4. **Ejemplo completo**: enlace a `ejemplos/cierre-at-cierre-v1-ejemplo.json` y su contenido.
5. **Dónde viven las cosas**: columna `avance` (`at_plan_schema` = 2), `uploads/at-estado/<id>/`, `avance-*.php`, `template-estado.js`, rama `estado` de «3 Render», `ver-estado.php`.
6. **Para PROD (en este orden, cada paso con el OK de Luis, respaldo y verificación desde afuera)**:

| Orden | Local | Destino | Clase |
|---|---|---|---|
| 1 | zip de `renderer/` de esta rama | Easypanel, servicio del renderer (lo sube Luis) | OBLIGATORIO |
| 2 | `N8N/plan-trabajo/plan-3-render.json` | n8n: `python N8N/propuestas-v3/deploy.py <ruta absoluta>` | OBLIGATORIO |
| 3 | `inc/plan-trabajo/avance-puras.php`, `avance-datos.php`, `avance-evidencias.php`, `avance-render.php`, `avance-panel.php` (nuevos) | mismas rutas en `wp-content/themes/automatiza-tech/` | OBLIGATORIO, **antes** que `cargar.php` |
| 4 | `inc/plan-trabajo/datos.php`, `rest.php`, `panel.php`, `vista.php`, `assets/css/plan-trabajo.css` | mismas rutas | OBLIGATORIO (respaldar y cotejar md5 contra `main`) |
| 5 | `inc/plan-trabajo/cargar.php` | misma ruta | OBLIGATORIO, **al final** de WordPress |
| 6 | `ver-estado.php` | raíz de `public_html` | OBLIGATORIO |
| 7 | migración: `wp eval 'at_av_migrar_esquema(); echo get_option("at_plan_schema");'` → `2` | PROD | OBLIGATORIO |
| — | `tests/`, `Docs/`, `.superpowers/` | — | NO subir |

Rollback: zip anterior del renderer; reimportar el `plan-3-render.json` de `main`; restaurar los archivos de WordPress del respaldo y borrar los `avance-*.php`; la columna `avance` puede quedar (nadie la lee sin el código).

7. **Qué no se ha probado** (lo llena quien implementa con lo que de verdad no corrió: por ejemplo, el flujo n8n real, un video de 13 MB subido por el navegador, el CDN con `no-transform`).

Crear `Docs/METODO_AT/ejemplos/cierre-at-cierre-v1-ejemplo.json` (UTF-8, sin BOM):

```json
{
  "formato": "at-cierre-v1",
  "plan": "AbCdEf123456",
  "cierre": {
    "titulo": "Diseño del sitio",
    "bloques": ["Diseño"],
    "fecha_real": "2026-10-09",
    "entregable_id": 0,
    "antes": {
      "texto": "El sitio usaba una plantilla genérica, sin las fotos del equipo actual.",
      "puntos": ["Plantilla genérica", "Fotos de personal que ya no trabaja ahí"],
      "evidencias": ["velocidad-antes"]
    },
    "durante": {
      "texto": "Diseñamos la portada y las páginas internas, y las revisamos contigo.",
      "actividades": [
        { "bloque": "Diseño", "nombre": "Maqueta de la portada", "real": "2026-10-06", "nota": "" }
      ]
    },
    "despues": {
      "texto": "Quedó aprobada la maqueta del sitio nuevo.",
      "puntos": ["Maqueta aprobada el 9 de octubre"],
      "evidencias": ["velocidad-despues", "portada", "recorrido", "maqueta"]
    }
  },
  "evidencias": [
    { "ref": "velocidad-antes", "tipo": "cifra", "titulo": "Velocidad en celular", "valor": "56", "unidad": "puntos de 100", "fuente": "PageSpeed Insights", "fecha": "2026-10-06" },
    { "ref": "velocidad-despues", "tipo": "cifra", "titulo": "Velocidad en celular", "valor": "92", "unidad": "puntos de 100", "fuente": "medición local", "fecha": "2026-10-09" },
    { "ref": "portada", "tipo": "captura", "titulo": "Portada nueva", "archivo": "portada-nueva.png" },
    { "ref": "recorrido", "tipo": "video", "titulo": "Recorrido de la maqueta", "archivo": "recorrido.mp4", "poster": "recorrido-poster.jpg" },
    { "ref": "maqueta", "tipo": "enlace", "titulo": "La maqueta en línea", "url": "https://example.com/maqueta" }
  ],
  "bloques": { "Desarrollo": { "estado": "en_curso", "inicio_real": "2026-10-12", "fin_real": "" } },
  "cambios_plan": [
    { "fecha": "2026-10-08", "titulo": "La publicidad pasa al servicio mensual", "detalle": "Lo acordamos por correo.", "bloques": [] }
  ],
  "necesitamos": [ { "texto": "Logo en alta resolución", "estado": "abierto", "fecha": "2026-10-08" } ],
  "alcance": { "fase1": ["Sitio web publicado"], "mensual": ["Publicidad y visibilidad"] }
}
```

- [ ] **Step 2: Verificar que el ejemplo valida**

```bash
cat > $SCR/valida-ejemplo.php <<'EOF'
<?php
require 'wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';
require 'wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php';
$plan = ['fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [
  ['nombre' => 'Diseño', 'actividades' => [['nombre' => 'Maqueta de la portada', 'desde' => '2026-10-05', 'hasta' => '2026-10-06']]],
  ['nombre' => 'Desarrollo', 'actividades' => [['nombre' => 'Construcción del sitio', 'desde' => '2026-10-12', 'hasta' => '2026-10-21']]]]]]];
$v = at_av_validar_import(json_decode(file_get_contents('Docs/METODO_AT/ejemplos/cierre-at-cierre-v1-ejemplo.json'), true), 'AbCdEf123456', $plan, at_av_vacio());
echo $v['ok'] ? "VALIDA\n" : 'NO VALIDA: ' . implode(' | ', $v['errores']) . "\n";
EOF
/c/wamp64/bin/php/php8.4.15/php.exe $SCR/valida-ejemplo.php
```
Expected: `VALIDA`.

- [ ] **Step 3: Punteros**

- `Docs/METODO_AT/PLAN-DE-TRABAJO.md`: al final de la sección «Estado del proyecto — Etapa 1 (EN PROD desde 2026-10-14)», en «Lo que falta», reemplazar la frase de la Etapa 2 por: «**Etapa 2 (LOCAL, rama `claude/estado-proyecto-e2`, sin desplegar):** avance, importar cierre, evidencias, presentación «Estado del proyecto» y `ver-estado.php` completo. Guía: `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md`.»
- `CLAUDE.md` y `AGENTS.md`: en el puntero «Plan de trabajo con carta Gantt…», después de «…está EN PROD desde el 2026-10-14 (rama `claude/estado-proyecto`)», agregar: «; la Etapa 2 (avance, importar cierre y presentación «Estado del proyecto») está LOCAL en `claude/estado-proyecto-e2`, guía `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md`».

- [ ] **Step 4: Grafo**

Run: `graphify update .` (sin costo de API). Expected: termina sin errores.

- [ ] **Step 5: Commit**

```bash
git add Docs/METODO_AT/ESTADO-DEL-PROYECTO.md Docs/METODO_AT/ejemplos/cierre-at-cierre-v1-ejemplo.json Docs/METODO_AT/PLAN-DE-TRABAJO.md CLAUDE.md AGENTS.md graphify-out
git commit -m "docs(estado): guía del Estado del proyecto, formato at-cierre-v1 y despliegue de la Etapa 2

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 10: Prueba de punta a punta en local (la hace quien coordina, no un subagente)

**Files:** ninguno del repo (scripts y capturas en `$SCR/estado-e2/`).

- [ ] **Step 1: Suites completas**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/estado-e2
(cd renderer && npm test 2>&1 | tail -4)
python N8N/plan-trabajo/probar_plan.py 2>&1 | tail -1
python N8N/plan-trabajo/probar_revision_plan.py 2>&1 | tail -1
for t in avance-puras avance-render render; do /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/$t-test.php | tail -1; done
for t in avance-datos avance-evidencias rest-estado avance-panel ver-estado ver-plan rest-render panel-acciones panel-envio disparador agenda-web; do echo "== $t"; AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/$t-wp-test.php | tail -1; done
```
Expected: todo en verde salvo las fallas previas conocidas de `main`.

- [ ] **Step 2: Cuerpo real → renderer local → capturas**

1. Levantar el renderer local en segundo plano: `cd renderer && PORT=3999 BASE_URL=http://localhost:3999 node src/index.js`.
2. Con un script PHP en `$SCR/estado-e2/` (carga `wp-load.php` del sitio de prueba): crear con `fixtures-panel.php` un plan enviado (definir `PTC_CONSERVAR` para que no se borre), importar `Docs/METODO_AT/ejemplos/cierre-at-cierre-v1-ejemplo.json` con el código del plan reemplazado, marcar las evidencias de archivo con URL `https://automatizatech.cl/wp-content/uploads/at-estado/…` inventadas, publicar, y escribir `at_av_armar_render_estado(…)` en `$SCR/estado-e2/cuerpo.json`.
3. `curl -s -X POST localhost:3999/render -H 'Content-Type: application/json' --data @$SCR/estado-e2/cuerpo.json` → `view_url`.
4. Con Playwright (el de `renderer/node_modules`), abrir la vista a 1920×1080 y guardar una captura por lámina en `$SCR/estado-e2/capturas/` (en modo presentación, avanzando con la flecha derecha). Revisar a ojo cada lámina: textos en español, nada cortado, «Estás aquí», cronograma planificado/real, columnas del cierre, evidencias, alcance y cierre.
5. Borrar el plan de prueba (`ptc_limpiar()`), detener el renderer local y registrar los temporales para el Aseo.

- [ ] **Step 3: Revisión final de la rama**

Despachar la revisión de toda la rama (`superpowers:requesting-code-review`, modelo más capaz) con la spec y este plan. Corregir lo que salga, volver a correr el Step 1 y dejar el ledger cerrado.
