# Correo al cliente precargado, PDF adjunto y borrado visible — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** que el correo al cliente del módulo Propuestas venga redactado (según el rubro y la reunión), editable y guardable; que lleve el PDF de la presentación adjunto cuando se pueda; que borrar se vea sin pasar el mouse; y que la burbuja ARIA no tape «Guardar».

**Architecture:** los cuatro textos viven dentro del contenido de la propuesta (`gamma_prompt_text`, JSON v3) en la clave nueva `correo_cliente`. GPT-4o los escribe en el flujo «1 Borrador» y «2 Cambios» los conserva (y ajusta si el comentario lo pide). WordPress los muestra ya escritos; si faltan, los arma con funciones puras desde el contenido de la propuesta. Al enviar, el PDF se adjunta en este orden: el subido a mano, el guardado en WordPress, o el que WordPress baja del renderer (hasta 15 MB).

**Tech Stack:** WordPress 7.1.2 en PROD (6.9.4 local), PHP 8.3, JS sin dependencias, n8n (workflows generados con Python en `N8N/propuestas-v3/`).

**Decisiones de Luis (2026-09-24):** textos por IA en el borrador + respaldo de WordPress; adjuntar PDF hasta 15 MB (si no, solo botones y se avisa); plan B: el PDF que Luis suba a mano en la ficha es el que se adjunta; «Borrar» siempre visible y botón rojo «🗑️ Borrar marcadas».

## Global Constraints

- Producción. Nada de lo que hoy funciona puede cambiar de comportamiento salvo lo que dice este plan (flujo v3: Pedir cambios, Aprobar, Destrabar; envío solo desde `lista`; página `&clasico=1` intacta).
- No se tocan: `functions.php`, `inc/propuestas-admin/clasico.php`, `inc/admin-proposals.php`, `inc/rest-proposals.php`, `inc/proposals-flow.php`, nombres de campos (`email_subject`, `email_intro`, `email_highlight`, `email_closing`, `send_email`, `pdf_file`), nonces, ni la regla de la casilla `send_email` (marcada por defecto en propuestas viejas).
- Clave nueva del JSON: `correo_cliente` = objeto con exactamente `asunto`, `introduccion`, `que_incluye`, `cierre` (strings). El renderer ignora claves desconocidas (`renderer/src/schema.js` solo exige las obligatorias).
- Host del renderer permitido para bajar PDF: solo `https://n8n-propuesta-renderer.kchiba.easypanel.host`. Nunca bajar de otro host.
- Tope del PDF adjunto: `AT_PA_PDF_MAX_BYTES` = 15 MB (15 * 1024 * 1024), definido con `if (!defined(...))` para poder bajarlo en pruebas locales.
- En los correos nunca enlazar `*.easypanel.host` (el SMTP de Hostinger lo rechaza como spam): los botones siguen siendo `automatizatech.cl/ver-presentacion.php` y `ver-demo.php`. Adjuntar un archivo bajado de ahí sí está bien.
- Textos visibles en español de Chile. Archivos de `inc/propuestas-admin/`, CSS y JS están en LF; mantenerlos así.
- PHP de pruebas: `C:/wamp64/bin/php/php8.3.28/php.exe`. Pruebas: `tests/propuestas/admin-lista-test.php` y `tests/propuestas/flow-test.php` deben imprimir `TODO OK`.
- Nunca mandar correos reales en pruebas locales: el WordPress local de prueba (`http://localhost:8089`) captura `wp_mail` (lo prepara el controlador en la Tarea 5). No correr los workflows de n8n de PROD.
- Commits por ruta, con `Co-Authored-By` del modelo. Nunca `git stash`, `clean`, `push` ni despliegues.

---

### Task 1: Funciones puras del correo y del PDF (consultas.php)

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/propuestas-admin/consultas.php` (agregar al final)
- Test: `tests/propuestas/admin-lista-test.php` (agregar casos)

**Interfaces (Produces):**
- `AT_PA_PDF_MAX_BYTES` (int, bytes) y `AT_PA_RENDERER_HOST` (string `n8n-propuesta-renderer.kchiba.easypanel.host`), ambos con `if (!defined())`.
- `at_pa_correo_textos(?array $payload, string $empresa): array` → `['asunto'=>…, 'introduccion'=>…, 'que_incluye'=>…, 'cierre'=>…]`, ninguno vacío.
- `at_pa_payload_con_correo(array $payload, array $textos): array` → el payload con `correo_cliente` = los cuatro textos (trim; claves faltantes = '').
- `at_pa_url_pdf_renderer(string $presentacion, string $pdf_path): string` → URL del PDF en el renderer o ''.

- [ ] **Step 1: Escribir las pruebas (fallan)** en `tests/propuestas/admin-lista-test.php`, con el mismo estilo de aserciones del archivo:
  - `at_pa_correo_textos(null, 'Ferretería Sur')`: asunto `Propuesta de Automatización Inteligente - Ferretería Sur`; introducción `Es un placer presentarle nuestra propuesta de automatización inteligente diseñada específicamente para Ferretería Sur.`; qué incluye `Hemos analizado sus requerimientos y preparado una solución personalizada que optimizará sus procesos de negocio mediante inteligencia artificial.`; cierre `Quedamos atentos a sus comentarios y consultas.` (son los textos por defecto de siempre).
  - Con payload sin `correo_cliente` y con `solution_title`='Catálogo con asistente', `benefits`=[['title'=>'Atención 24/7'],['title'=>'Menos llamadas perdidas'],['title'=>'Pedidos ordenados']], `next_steps`=['Revisar la propuesta el jueves.']: asunto `Propuesta para Ferretería Sur: Catálogo con asistente`; qué incluye `Catálogo con asistente: Atención 24/7, Menos llamadas perdidas y Pedidos ordenados.`; cierre `Como próximo paso: Revisar la propuesta el jueves. Quedamos atentos a sus comentarios y consultas.`; introducción `Es un placer presentarle la propuesta que preparamos para Ferretería Sur a partir de nuestra conversación.`
  - Con `correo_cliente` completo: devuelve esos cuatro textos tal cual (con trim).
  - Con `correo_cliente` parcial (`asunto` vacío, `cierre` sin clave): esos dos salen del respaldo; los otros de la IA.
  - `$empresa` vacío → usa `su empresa`.
  - `at_pa_payload_con_correo(['a'=>1], ['asunto'=>' X ','introduccion'=>'Y'])` → `['a'=>1,'correo_cliente'=>['asunto'=>'X','introduccion'=>'Y','que_incluye'=>'','cierre'=>'']]`.
  - `at_pa_url_pdf_renderer('https://n8n-propuesta-renderer.kchiba.easypanel.host/p/uBn21AF16EcM/index.html', '')` → `https://n8n-propuesta-renderer.kchiba.easypanel.host/p/uBn21AF16EcM/presentation.pdf`; lo mismo sin `index.html` (termina en `/`).
  - `pdf_path` en el host del renderer que termina en `.pdf` (por ejemplo `https://n8n-propuesta-renderer.kchiba.easypanel.host/p/EY2U5YW7zRO6/presentation.pdf`) → se devuelve ese `pdf_path`, aunque la presentación sea otra.
  - Devuelven '': `http://` (sin s), otro host (`https://gamma.app/…`, `https://evil.example/p/x/index.html`), host parecido (`https://n8n-propuesta-renderer.kchiba.easypanel.host.evil.com/p/abc123/index.html`), `..` en la ruta, id con caracteres raros (`/p/ab$cd/index.html`), ambos vacíos.
- [ ] **Step 2: Correr y ver que fallan.** `C:/wamp64/bin/php/php8.3.28/php.exe tests/propuestas/admin-lista-test.php`
- [ ] **Step 3: Implementar** al final de `consultas.php`:

```php
if (!defined('AT_PA_PDF_MAX_BYTES')) {
	// En base64 el adjunto crece ~33 %: 15 MB quedan en ~20 MB, bajo los 25 MB por adjunto que aceptan Hostinger y Gmail.
	define('AT_PA_PDF_MAX_BYTES', 15 * 1024 * 1024);
}
if (!defined('AT_PA_RENDERER_HOST')) {
	define('AT_PA_RENDERER_HOST', 'n8n-propuesta-renderer.kchiba.easypanel.host');
}

/** «a, b y c» */
function at_pa_enumerar(array $items): string {
	if (count($items) < 2) {
		return (string) ($items[0] ?? '');
	}
	$ultimo = array_pop($items);
	return implode(', ', $items) . ' y ' . $ultimo;
}

/** Los cuatro textos del correo al cliente, nunca vacíos: los que escribió la IA (correo_cliente) o, si faltan, armados con el contenido. */
function at_pa_correo_textos(?array $payload, string $empresa): array {
	$empresa = trim($empresa) !== '' ? trim($empresa) : 'su empresa';
	$texto = function ($v): string { return is_string($v) ? trim($v) : ''; };
	$respaldo = [
		'asunto'       => "Propuesta de Automatización Inteligente - $empresa",
		'introduccion' => "Es un placer presentarle nuestra propuesta de automatización inteligente diseñada específicamente para $empresa.",
		'que_incluye'  => 'Hemos analizado sus requerimientos y preparado una solución personalizada que optimizará sus procesos de negocio mediante inteligencia artificial.',
		'cierre'       => 'Quedamos atentos a sus comentarios y consultas.',
	];
	if (is_array($payload)) {
		$solucion = $texto($payload['solution_title'] ?? '');
		$beneficios = [];
		foreach ((array) ($payload['benefits'] ?? []) as $b) {
			$t = is_array($b) ? $texto($b['title'] ?? '') : '';
			if ($t !== '') {
				$beneficios[] = $t;
			}
		}
		$pasos = [];
		foreach ((array) ($payload['next_steps'] ?? []) as $s) {
			if ($texto($s) !== '') {
				$pasos[] = rtrim($texto($s), '.');
			}
		}
		$respaldo['introduccion'] = "Es un placer presentarle la propuesta que preparamos para $empresa a partir de nuestra conversación.";
		if ($solucion !== '') {
			$respaldo['asunto'] = "Propuesta para $empresa: $solucion";
		}
		if ($beneficios) {
			$respaldo['que_incluye'] = ($solucion !== '' ? "$solucion: " : '') . at_pa_enumerar($beneficios) . '.';
		}
		if ($pasos) {
			$respaldo['cierre'] = 'Como próximo paso: ' . $pasos[0] . '. Quedamos atentos a sus comentarios y consultas.';
		}
	}
	$ia = is_array($payload) && isset($payload['correo_cliente']) && is_array($payload['correo_cliente']) ? $payload['correo_cliente'] : [];
	$r = [];
	foreach ($respaldo as $k => $v) {
		$r[$k] = $texto($ia[$k] ?? '') !== '' ? $texto($ia[$k]) : $v;
	}
	return $r;
}

/** El payload con los cuatro textos del correo en correo_cliente. */
function at_pa_payload_con_correo(array $payload, array $textos): array {
	$c = [];
	foreach (['asunto', 'introduccion', 'que_incluye', 'cierre'] as $k) {
		$c[$k] = is_string($textos[$k] ?? null) ? trim($textos[$k]) : '';
	}
	$payload['correo_cliente'] = $c;
	return $payload;
}

/** URL del PDF de la presentación en el renderer, o '' si la propuesta no vive ahí. Solo https y solo el host del renderer. */
function at_pa_url_pdf_renderer(string $presentacion, string $pdf_path): string {
	$host = preg_quote(AT_PA_RENDERER_HOST, '#');
	$pdf_path = trim($pdf_path);
	if ($pdf_path !== '' && strpos($pdf_path, '..') === false
		&& preg_match('#^https://' . $host . '/[A-Za-z0-9/_.-]+\.pdf$#', $pdf_path)) {
		return $pdf_path;
	}
	if (preg_match('#^https://' . $host . '/p/([A-Za-z0-9]{6,32})/(index\.html)?$#', trim($presentacion), $m)) {
		return 'https://' . AT_PA_RENDERER_HOST . '/p/' . $m[1] . '/presentation.pdf';
	}
	return '';
}
```

- [ ] **Step 4: Correr las dos pruebas** (`admin-lista-test.php` y `flow-test.php`) → `TODO OK`; `php -l` de `consultas.php`.
- [ ] **Step 5: Commit** `feat(propuestas-admin): textos del correo al cliente y URL del PDF del renderer (funciones puras)`.

---

### Task 2: Ficha y envío — textos precargados que se guardan, y PDF adjunto

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php` (pestaña Envío)
- Modify: `wp-content/themes/automatiza-tech/inc/propuestas-admin/acciones.php` (`at_pa_guardar`, `at_pa_accion_v3`)

**Interfaces (Consumes):** las tres funciones y dos constantes de la Tarea 1.

Requisitos:
1. **Ficha, pestaña Envío.** Calcular `$payload = json_decode((string) $p->gamma_prompt_text, true)` (null si no es array) y `$correo = at_pa_correo_textos(is_array($payload) ? $payload : null, (string) $p->company_name)`. Los cuatro campos muestran ese texto como **valor real** (input `value="<?php echo esc_attr(...) ?>"`, textareas con `esc_textarea`), no como placeholder. Los `name`/`id` no cambian. Cambiar la frase de ayuda de la sección a: «Texto sugerido según la reunión con el cliente. Puedes editarlo; se guarda al apretar Guardar.» Quitar los placeholders que repetían el texto por defecto (se puede dejar el del asunto). Debajo de la casilla de envío, agregar una línea de ayuda: «Se adjunta el PDF que subas en «Cliente y enlaces»; si no subiste uno, se adjunta el de la presentación (hasta 15 MB). Si pesa más, el correo lleva solo los botones.»
2. **Lectura de los cuatro campos** en `at_pa_guardar`: usar `wp_unslash` antes de sanear (asunto con `sanitize_text_field`; los otros tres con `sanitize_textarea_field`), para que un apóstrofo o una comilla no lleguen al cliente con barra invertida. Guardarlos en `$textos = ['asunto'=>…, 'introduccion'=>…, 'que_incluye'=>…, 'cierre'=>…]`.
3. **Guardar los textos.** En `at_pa_guardar`, después de armar `$update_data` y antes del `$wpdb->update`: tomar como base el contenido que va a quedar guardado (si `$update_data['gamma_prompt_text']` existe, ese; si no, el de la fila en la base). Si `json_decode(base, true)` da array, `$update_data['gamma_prompt_text'] = wp_json_encode(at_pa_payload_con_correo($base_decodificada, $textos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)`. Si no es JSON (propuestas viejas de Gamma), no se guarda nada nuevo. Leer la fila (flujo, status, gamma_prompt_text) una sola vez.
4. **Botones v3.** En `at_pa_accion_v3`, ramas `cambios` y `aprobar`: si llegaron los cuatro campos del correo en el POST, aplicar `at_pa_payload_con_correo()` al `$payload` justo después de `at_propuesta_aplicar_precios(...)` (mismo saneo con `wp_unslash` del punto 2), para que lo que Luis editó viaje a n8n y quede guardado también cuando falta un precio. «Destrabar» no cambia.
5. **Envío: textos nunca vacíos.** Donde hoy se usan los textos por defecto (asunto, intro, destacado, cierre), usar el texto enviado y, si viene vacío, el de `at_pa_correo_textos(<payload de la fila recién leída o null>, $company_name)`. El resto del HTML del correo no cambia (sigue `nl2br(esc_html(...))`).
6. **Envío: PDF.** Mantener el orden actual (1. PDF subido ahora; 2. `pdf_path` local de WordPress) y agregar el 3.º: si no hay adjunto, `$url = at_pa_url_pdf_renderer((string) $proposal->gamma_iframe_url, (string) $proposal->pdf_path)`; si no es '', bajarlo con `wp_remote_get($url, ['timeout' => 60, 'stream' => true, 'filename' => $tmp, 'limit_response_size' => AT_PA_PDF_MAX_BYTES + 1])` a `$tmp = trailingslashit(get_temp_dir()) . 'at-propuesta-' . $id . '/Propuesta-' . sanitize_file_name($company_name ?: 'AutomatizaTech') . '.pdf'` (crear la carpeta con `wp_mkdir_p`). Se adjunta solo si: no hubo `WP_Error`, código 200, `filesize` ≤ `AT_PA_PDF_MAX_BYTES` y los primeros 4 bytes son `%PDF`. Si no, se borra el archivo y se guarda el motivo en español: «el PDF pesa X,X MB (tope 15 MB)» / «no se pudo descargar el PDF de la presentación». Después de `wp_mail` borrar el archivo y la carpeta temporales, se haya enviado o no.
7. **Aviso tras enviar:** hoy dice «(con PDF adjunto)» / «(sin PDF adjunto)». Si no se adjuntó y hay motivo, «(sin PDF adjunto: <motivo>; el cliente lo baja desde la presentación)». El texto del cuerpo del correo sobre el PDF sigue la lógica actual (según `$attachments`).
8. Nada más cambia: campos, nonces, estados, `send_email`, bloqueo de envío v3 fuera de `lista`, Bcc, remitente.

- [ ] Implementar, `php -l` de los dos archivos, correr las dos pruebas, y **no** mandar correos (sin SMTP real).
- [ ] Commit `feat(propuestas-admin): correo al cliente precargado y guardado; PDF de la presentación adjunto hasta 15 MB`.

---

### Task 3: Borrar a la vista y espacio para la burbuja ARIA

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/propuestas-admin/lista.php` (`extra_tablenav`, `at_pa_render_lista`)
- Modify: `wp-content/themes/automatiza-tech/inc/propuestas-admin/acciones.php` (`at_pa_procesar_acciones`)
- Modify: `wp-content/themes/automatiza-tech/assets/css/propuestas-admin.css`
- Modify: `wp-content/themes/automatiza-tech/assets/js/propuestas-admin.js`

Requisitos:
1. **«Borrar» siempre visible:** CSS `.at-pa .wp-list-table .row-actions { position: static; }` (WordPress 7.1.2 y 6.9.4 las esconden con `position: relative; left: -9999em` hasta `tr:hover`; con `static`, `left` deja de aplicar). Comprobar que también se ven «Abrir ficha» y «Ver presentación».
2. **Botón «🗑️ Borrar marcadas»** solo arriba (`$which === 'top'`), en un `div.alignleft.actions` propio **antes** del de fechas: `<button type="submit" name="bulk_action" value="borrar_marcadas" class="button at-borrar-marcadas">🗑️ Borrar marcadas</button>`. CSS: texto y borde rojo `#b32d2e` (como `.at-borrar`).
3. **Servidor:** en `at_pa_procesar_acciones`, dentro de `if (isset($_GET['bulk_action']))`, además de `action`/`action2` = `borrar`, aceptar `sanitize_key(wp_unslash($_GET['bulk_action'])) === 'borrar_marcadas'` — siempre exigiendo `!empty($_GET['proposal_ids'])` **antes** de llamar a `at_pa_borrar_varias` (que hace `check_admin_referer('bulk-propuestas')`). Sin filas marcadas no se llama a nada.
4. **Limpieza de URL del servidor:** agregar `'bulk_action'` a la lista de `remove_query_arg` de `at_pa_render_lista`, para que la paginación y el orden nunca arrastren `bulk_action=borrar_marcadas`.
5. **JS:** listener de `click` en `.at-borrar-marcadas` (corre antes del submit y existe en todos los navegadores): sin casillas marcadas → `alert('Marca al menos una propuesta para borrar.')` y `preventDefault()`; con marcadas → `confirm('¿Borrar N propuesta(s)? No se puede deshacer.')`, y `preventDefault()` si cancela. El listener de submit existente no debe preguntar dos veces por este botón (su `e.submitter.id` no es `doaction`/`doaction2`, así que retorna; el respaldo sin `submitter` solo pregunta si un selector está en «borrar»: aceptable). Agregar `'bulk_action'` ya está en `fijas` del limpiador de URL; `huboBorrado` debe considerar también `params.get('bulk_action') === 'borrar_marcadas'`.
6. **Burbuja ARIA:** un mu-plugin de PROD (`aria-widget-flotante.php`) dibuja en todo el admin un botón fijo de 65×65 px a 25 px del borde inferior derecho. En `.at-pa-guardar` agregar `padding-right: 110px` (en todas las anchuras) para que «Guardar» y el aviso de envío nunca queden debajo.

- [ ] Implementar, `php -l`, `node --check`, correr las dos pruebas.
- [ ] Commit `feat(propuestas-admin): Borrar siempre visible, botón Borrar marcadas y espacio para la burbuja ARIA`.

---

### Task 4: n8n — la IA redacta el correo al cliente

**Files:**
- Modify: `N8N/propuestas-v3/build_1_borrador.py` (`PROMPT_REDACTAR`, `CODE_ARMAR`)
- Modify: `N8N/propuestas-v3/build_2_cambios.py` (`PROMPT_CAMBIOS`)
- Regenerate: `N8N/propuestas-v3/1-borrador.json`, `N8N/propuestas-v3/2-cambios.json` (con `python build_1_borrador.py` y `python build_2_cambios.py` desde esa carpeta)

Requisitos:
1. En la forma JSON de `PROMPT_REDACTAR`, después de `next_steps`, agregar:
   `"correo_cliente": {"asunto": "máx. 12 palabras, con el nombre del negocio", "introduccion": "2 o 3 frases que retoman lo conversado en la reunión", "que_incluye": "2 o 3 frases con lo que trae la propuesta (servicios o fases), sin montos", "cierre": "1 o 2 frases con el siguiente paso acordado"},`
2. En «Reglas» agregar una regla: `- correo_cliente: es el correo con el que Luis enviará la propuesta al cliente. Mismo tratamiento (tú/usted) que la propuesta. Sin saludo ni firma (la plantilla ya pone «Estimado/a <nombre>,» y «Atentamente, El equipo de Automatiza Tech»). Sin montos. No menciones enlaces, botones ni adjuntos (la plantilla los agrega). Cálido y concreto, con algo propio de la reunión.`
3. En `CODE_ARMAR`, antes del `return`, normalizar: `const cc = (d.correo_cliente && typeof d.correo_cliente === 'object') ? d.correo_cliente : {}; d.correo_cliente = { asunto: String(cc.asunto || '').trim(), introduccion: String(cc.introduccion || '').trim(), que_incluye: String(cc.que_incluye || '').trim(), cierre: String(cc.cierre || '').trim() };` (si la IA no lo trae, quedan vacíos y WordPress usa su respaldo).
4. En `PROMPT_CAMBIOS`, agregar la frase: `Si un comentario pide cambiar el correo al cliente (asunto, introducción, qué incluye o cierre), cámbialo en correo_cliente con el mismo tratamiento; si no, deja correo_cliente idéntico.` (`CODE_PAYLOAD` ya conserva lo que el modelo omita con `Object.assign`.)
5. No cambiar nada más de los workflows (nodos, credenciales, URLs). Regenerar los dos JSON y comprobar con Python que cargan (`json.load`) y que el texto nuevo está en el nodo del prompt.

- [ ] Implementar, regenerar, verificar, commit `feat(propuestas-v3): el borrador redacta el correo al cliente y Cambios lo conserva`.

---

### Task 5 (controlador): prueba local de punta a punta

- `router.php` del WordPress local: filtro `pre_wp_mail` que anota destinatario, asunto, largo del cuerpo y adjuntos (nombre y bytes) en `wp-local/mail-falso.log` y devuelve `true` (nunca sale un correo). Para probar el tope, `define('AT_PA_PDF_MAX_BYTES', …)` más chico en una corrida.
- Casos: ficha v3 con y sin `correo_cliente` (textos llenos); editar y Guardar sin enviar (persisten en `correo_cliente`); Pedir cambios con textos editados (viajan en el payload); envío de una vieja y de una `lista` con presentación en el renderer (adjunta el PDF real bajado del renderer: solo lectura de una URL pública); tope bajo → sin adjunto con motivo; PDF subido a mano → gana ese; «Borrar» visible, «Borrar marcadas» con 0 y con 2 marcadas (confirmar/cancelar), paginación sin `bulk_action`; barra de Guardar con 110 px libres.

### Task 6 (controlador, con autorización de Luis): despliegue

- WordPress: respaldar y subir `consultas.php` primero, luego `acciones.php`, `ficha.php`, `lista.php`, CSS y JS; verificar desde afuera y con wp-cli (solo lectura), igual que el 2026-09-24 15:48.
- n8n: respaldar los workflows 1 y 2 y publicarlos con `python N8N/propuestas-v3/deploy.py`.
- Prueba real opcional con `prueba: true` en el Borrador (gasto de GPT-4o de centavos; sin fotos) solo con el ok de Luis y el costo a la vista; borrar la fila de prueba después.
