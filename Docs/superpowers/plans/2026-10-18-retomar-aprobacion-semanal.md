# Retomar prospectos, fase 2 (correo semanal con aprobación) — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** que el sistema detecte y prepare solo a los prospectos estancados y que cada semana le llegue a Luis un correo para aprobar, pedir cambios u omitir cada recordatorio; nada sale sin su aprobación.

**Architecture:** se extiende el módulo `inc/retomar/` de la fase 1 (WordPress del tema) con cuatro piezas nuevas —detección, redacción con IA vía n8n, aprobación por enlace firmado y resumen semanal— y se cambia el envío para que solo salga lo aprobado. El renderer gana fotos de Higgsfield en el camino `retomar` y devuelve sus URL para reutilizarlas. El reloj n8n pasa a cada hora y WordPress decide qué toca.

**Tech Stack:** PHP 8 / WordPress (tema `automatiza-tech`), MySQL, Node 20 + Express + Playwright (renderer), n8n (flujos generados con Python), OpenAI `gpt-4o` en n8n, Higgsfield Soul 2 (renderer).

**Spec:** `Docs/superpowers/specs/2026-10-18-retomar-aprobacion-semanal-design.md` (fase 1: `Docs/superpowers/specs/2026-10-18-retomar-prospectos-design.md`).

## Global Constraints

- Repositorio **público**: ningún nombre, correo, teléfono ni código de prospecto real en código, pruebas, commits o docs. Datos de prueba: `[PRUEBA]` y correos `rt-...@example.com`, como en `tests/retomar/`.
- No tocar `functions.php`. Todo entra por `inc/retomar/cargar.php` (ya cargado desde `inc/admin-proposals.php`). Ningún `foreach`/variable suelta a nivel de archivo (lección de la fase 1: pisaba la global `$accion`).
- **D8:** ningún correo (al prospecto ni a Luis) lleva enlaces ni imágenes con `easypanel` o el host del renderer (`at_pt_host_renderer()`). Los enlaces del correo semanal apuntan a `automatizatech.cl`.
- **Sin montos:** textos e informe nunca llevan `$`, `CLP`, `USD`, `UF` ni cifras con separador de miles.
- Textos `quedamos`, `proponemos`, `partir`: 1 a 600 caracteres y máximo 6 líneas. `recomendacion`: hasta 300 caracteres y 3 líneas.
- Envío al prospecto: lunes a viernes, 10:00 a 13:00 `America/Santiago`; máximo 4 recordatorios; al menos 7 días entre envíos (`at_rt_intervalo`, `at_rt_max`, `at_rt_en_ventana` de la fase 1).
- Enlaces de aprobación: HMAC con `wp_salt('auth')`, **un solo uso**, vencen a los **604800 s** (7 días), el GET nunca ejecuta: solo el POST de la página de confirmación.
- Correo semanal por defecto: día **1** (lunes, ISO-8601 `N`), hora **`09:00`** `America/Santiago`, destino `at_cc_correo_avisos()`.
- Detección: `status` en `sent` o `evaluando`, enviada hace **14 días o más**, sin ficha CRM `tipo='cliente'` con ese correo, sin baja, sin seguimiento previo con ese correo. `flujo !== 'v3'` → seguimiento en estado `espera_propuesta`.
- Fotos: solo por el renderer (Higgsfield Soul 2); láminas `portada`, `cierre`, `demo`; encargos sin texto, letreros ni etiquetas.
- Textos visibles en español de Chile, voz del equipo en plural («te dejamos», «podemos»).
- Pruebas WordPress solo contra el sitio local (`AT_WP_LOAD`), nunca PROD. Antes de correrlas, confirmar que las junctions del sitio local apuntan a este worktree:
  `powershell -c "(Get-Item '$SCR/wp-local-plan/wp-content/themes/automatiza-tech').Target"` → `...\.worktrees\retomar-eje\wp-content\themes\automatiza-tech` (ídem `wp-content/mu-plugins`).
  `SCR=C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad`.
- Comandos de prueba (desde la raíz del worktree): `AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php" php tests/retomar/<archivo>.php`; `php tests/retomar/puras-test.php`; `cd renderer && node --test test/*.test.js` (con glob: la carpeta sola se toma como un único archivo).
- Este plan **no despliega**: WordPress por SSH, ZIP del renderer y flujos n8n van después, con OK de Luis.
- Commits en español, terminados con `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Los antivirus del correo abren todos los enlaces del resumen** (GET y HEAD): ninguno puede aprobar, omitir ni pedir cambios. Prueba en Task 7: GET y HEAD de los tres enlaces no cambian `aprobacion` ni `proximo_at`.
2. **Doble clic o aprobar desde dos dispositivos:** un solo envío. Prueba en Task 7 (segundo POST → `usado`) y Task 8 (dos `at_rt_tick` seguidos con el mismo aprobado → un solo correo).
3. **El prospecto se da de baja o agenda entre la aprobación y el envío:** no sale nada y queda detenido. Prueba en Task 8.
4. **Falla el correo semanal o el reloj corre varias veces en la misma hora:** nunca dos resúmenes la misma semana; si `wp_mail` falla, se reintenta en la pasada siguiente. Prueba en Task 9.
5. **Bordes de semana y de año en hora de Chile:** domingo 23:30 vs lunes 00:30, y la semana ISO de fin de año (`2027-01-01` → `2026-W53`). Prueba en Task 2.

## Mapa de archivos

| Archivo | Responsabilidad |
|---|---|
| `inc/retomar/esquema.php` | Esquema v2: columnas `notas`, `redaccion`, `aprobacion`, `fotos`. |
| `inc/retomar/datos.php` | Campos nuevos en `at_rt_campos`/`at_rt_fila`, estado `espera_propuesta`, `at_rt_config()`. |
| `inc/retomar/puras.php` | Reglas sin WordPress: montos, candidata, semana, firma, programación, HTML del resumen, canales. |
| `inc/retomar/detectar.php` (nuevo) | Detección de propuestas estancadas y alta automática del seguimiento. |
| `inc/retomar/redaccion.php` (nuevo) | Pedido a n8n, contexto para la IA y callback con textos y encargos de foto. |
| `inc/retomar/render.php` | Payload con encargos/fotos reutilizadas; callback guarda `fotos`; aviso tras «Pedir cambios». |
| `inc/retomar/aprobacion.php` (nuevo) | Emisión, validación y resolución de enlaces; página de confirmación. |
| `inc/retomar/resumen.php` (nuevo) | Correo semanal y aviso «recordatorio corregido». |
| `inc/retomar/envio.php` | Solo envía lo aprobado; consume la aprobación; marca `sent` la propuesta v3 en `lista`; tick por hora. |
| `inc/retomar/panel.php` | Sin «Activar»; textos opcionales y notas; configuración; «Enviarme el resumen ahora»; botón de la ficha CRM. |
| `inc/retomar/cargar.php` | Carga los cuatro archivos nuevos. |
| `retomar-aprobar.php` (raíz, nuevo) | Entrada pública de los enlaces de aprobación (igual a `retomar.php`). |
| `wp-content/mu-plugins/crm-ai-completo.php` | Una línea: botón «🔁 Retomar» en la ficha de prospecto. |
| `renderer/src/schema.js`, `server.js`, `template-retomar.js` | Encargos e imágenes en `retomar`, `images.urls` en la respuesta, `recomendacion`. |
| `N8N/retomar/build_retomar_redactar.py` (nuevo) + JSON | Flujo «Retomar · Redactar». |
| `N8N/retomar/build_retomar_reloj.py`, `build_retomar_render.py` + JSON | Reloj cada hora; render con `fotos` y 300 s. |
| `Docs/METODO_AT/RETOMAR-PROSPECTOS.md` | Guía de la fase 2 y lista de subida. |

Datos (JSON en columnas `longtext`):
- `redaccion`: `{estado: pendiente|lista|error|revision, generacion: hex16, intentos: int, origen: ia|manual, nota: string, image_briefs: [{slide, prompt}], pedido: string}`
- `aprobacion`: `{estado: por_aprobar|aprobado|cambios|omitido|enviado, n: int, nonce: hex16, huella: string, emitido_at, vence: int, usada: bool, programado?: Y-m-d}`
- `fotos`: `{portada|cierre|demo: {url, hash}}` (`hash` = `hash('sha256', prompt)`)
- `textos`: los tres de la fase 1 + `recomendacion`.

Opciones nuevas: `at_rt_resumen_dia` (`'1'`), `at_rt_resumen_hora` (`'09:00'`), `at_rt_resumen_destino` (`''` = `at_cc_correo_avisos()`), `at_rt_resumen_semana` (`''`), `at_rt_resumen_desde` (`''`). Constante nueva (no secreta): `AT_N8N_RETOMAR_REDACTAR`.

---

### Task 1: Esquema v2 y datos

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/retomar/esquema.php`, `inc/retomar/datos.php`
- Test: `tests/retomar/esquema-wp-test.php`, `tests/retomar/datos-wp-test.php`

**Interfaces:**
- Produces: `at_rt_esquema_listo(): bool` (exige `at_rt_schema === '2'` y las 4 columnas); `at_rt_campos()`/`at_rt_fila()` aceptan y decodifican `notas` (texto), `redaccion`, `aprobacion`, `fotos` (arrays); estado `espera_propuesta` válido; `at_rt_config(): array{dia:int, hora:string, destino:string}`.

- [ ] **Step 1: Pruebas que fallan.** En `esquema-wp-test.php`: tras `at_rt_migrar_esquema()`, `get_option('at_rt_schema') === '2'`; `SHOW COLUMNS FROM {prefix}at_retomar` incluye `notas`, `redaccion`, `aprobacion`, `fotos`; una fila creada antes de migrar conserva `nombre` y `envios`; migrar dos veces no cambia nada. En `datos-wp-test.php`: `at_rt_crear([... 'redaccion' => ['estado'=>'lista'], 'aprobacion' => ['estado'=>'por_aprobar','n'=>1], 'fotos' => ['portada'=>['url'=>'https://x/a.jpg','hash'=>'h']], 'notas' => 'nota'])` y `at_rt_obtener()` devuelve los mismos arrays; `at_rt_campos(['estado'=>'espera_propuesta'])` no es `null`; `at_rt_campos(['estado'=>'inventado'])` es `null`; `at_rt_config()` sin opciones devuelve `['dia'=>1,'hora'=>'09:00','destino'=>at_cc_correo_avisos()]`; con `at_rt_resumen_dia='9'` devuelve `dia` 1 (fuera de 1..7 vuelve al defecto) y con `at_rt_resumen_hora='25:00'` devuelve `'09:00'`.
- [ ] **Step 2: Correr y ver fallar:** `AT_WP_LOAD=... php tests/retomar/esquema-wp-test.php` y `datos-wp-test.php` → FALLA en las columnas nuevas.
- [ ] **Step 3: Implementar.** `dbDelta` agrega `notas text NOT NULL`, `redaccion longtext NOT NULL`, `aprobacion longtext NOT NULL`, `fotos longtext NOT NULL`; la opción pasa a `'2'` solo si las columnas existen. `at_rt_crear` rellena `'[]'` en los JSON nuevos y `''` en `notas`. `at_rt_campos` agrega los cuatro a `$permitidos` (los tres JSON por el mismo camino que `render`), y `espera_propuesta` a la lista de estados. `at_rt_config()` en `datos.php` valida día `1..7` y hora `/^([01]\d|2[0-3]):[0-5]\d$/`; destino vacío o inválido → `at_cc_correo_avisos()`.
- [ ] **Step 4: Correr las dos pruebas y todas las de `tests/retomar/`** → `TODO OK` en cada una.
- [ ] **Step 5: Commit** `feat(retomar): esquema v2 con redacción, aprobación y fotos`.

### Task 2: Reglas puras

**Files:**
- Modify: `inc/retomar/puras.php`
- Test: `tests/retomar/puras-test.php`

**Interfaces:**
- Produces (todas sin WordPress salvo `esc_*`, que el archivo ya evita: usar `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`):
  - `at_rt_tiene_montos(string $t): bool`
  - `at_rt_es_candidata(array $p, array $c, string $hoy, int $dias_min = 14): string` — `$p = ['status'=>string, 'enviada'=>'Y-m-d']`, `$c = ['crm_tipo'=>string, 'baja'=>bool, 'existe'=>bool]`; devuelve `''` o el motivo `estado|reciente|cliente|baja|existe` (en ese orden de revisión).
  - `at_rt_necesita_propuesta(?string $flujo): bool` → `$flujo !== 'v3'`.
  - `at_rt_semana(string $ahora): string` → formato `o-\WW` (p. ej. `2026-W43`).
  - `at_rt_toca_resumen(string $ahora, int $dia, string $hora, string $ultima_semana): bool` — verdadero si la semana de `$ahora` no es `$ultima_semana` y (`N > $dia` o (`N == $dia` y `H:i >= $hora`)): así un lunes perdido se recupera el martes.
  - `at_rt_programar(string $ahora): string` → hoy si es hábil y `H:i < '13:00'`; si no, el siguiente día hábil.
  - `at_rt_firma(int $id, string $accion, string $nonce, int $vence, string $salt): string` → 32 hex de `hash_hmac('sha256', "rta|$id|$accion|$nonce|$vence", $salt)`; `at_rt_firma_valida(string $f, int $id, string $accion, string $nonce, int $vence, string $salt): bool` con `hash_equals`. `$accion` ∈ `aprobar|cambios|omitir|ver`.
  - `at_rt_tarjeta_html(array $t): string` y `at_rt_resumen_html(array $tarjetas, array $salieron, string $url_panel): string`. `$t = ['tipo'=>normal|preparacion|propuesta|revision, 'nombre','negocio','dias'=>int,'n'=>int,'max'=>int,'canales'=>string[],'textos'=>array,'url_presentacion','enlaces'=>['aprobar','cambios','omitir','ver']]`. Miniatura: bloque HTML con degradado azul y el nombre del negocio (sin `<img>`: D8).
  - `at_rt_canales(...)`: misma firma; WhatsApp en **todo** envío si está en `$canales`, autorizado y con teléfono (se quita la condición de 1.º y último).

- [ ] **Step 1: Pruebas que fallan** (agregar a `puras-test.php`):
  - montos: `$680.000`, `680.000`, `1.500`, `USD 500`, `2 UF`, `CLP` → `true`; `en 2 semanas`, `fase 1 en 30 días`, `año 2026`, `10:00 a 13:00`, `UFO` → `false`.
  - candidata (hoy `2026-10-18`): `sent` + `2026-10-04` → `''`; `sent` + `2026-10-05` → `reciente`; `evaluando` + `2026-09-01` → `''`; `aceptada`, `archivada`, `pending`, `rechazada`, `lista` → `estado`; `crm_tipo=cliente` → `cliente`; `baja=true` → `baja`; `existe=true` → `existe`.
  - `at_rt_necesita_propuesta('v3') === false`; `null` y `''` → `true`.
  - semana: `2026-10-19 09:00:00` → `2026-W43`; `2027-01-01 10:00:00` → `2026-W53`.
  - toca (día 1, hora 09:00): lunes `2026-10-19 08:59` → falso; `09:00` → verdadero; martes `2026-10-20 10:00` con última `''` → verdadero; mismo martes con última `2026-W43` → falso; domingo `2026-10-18 23:30` con última `2026-W42` → falso (ya se envió esa semana) y con última `2026-W41` → verdadero; lunes `2026-10-19 00:30` con última `2026-W42` → falso (antes de la hora).
  - programar: viernes `2026-10-23 12:59` → `2026-10-23`; viernes `13:00` → `2026-10-26`; sábado `2026-10-24 09:00` → `2026-10-26`.
  - firma: válida con los mismos datos; falsa si cambia `accion`, `nonce`, `vence` o `id`.
  - tarjeta normal contiene `✅ Aprobar y enviar`, `✏️ Pedir cambios`, `⏭️ Esta semana no` y los enlaces dados; `tipo=propuesta` contiene `Necesita propuesta nueva` y no `Aprobar`; `preparacion` → `En preparación`; `revision` → `Necesita revisión`; un `negocio` `<script>x</script>` sale escapado; el resumen nunca contiene `<img`.
  - canales: `at_rt_canales(2, 4, 'correo,whatsapp', true, '+56900000000') === ['correo','whatsapp']`; sin autorización → `['correo']`.
- [ ] **Step 2:** `php tests/retomar/puras-test.php` → FALLA (funciones inexistentes).
- [ ] **Step 3: Implementar** en `puras.php`. Montos: `/\$|\b(CLP|USD|UF)\b|\d{1,3}(?:[.,]\d{3})+/iu`. Asunto, botones y textos del resumen en español de Chile según la maqueta (privada: `Shared-Tools/Retomar-Maqueta-Correo-Semanal-2026-10-18.html`); asunto del resumen lo arma Task 9.
- [ ] **Step 4:** `php tests/retomar/puras-test.php` → todo `ok`. Ajustar la prueba vieja de canales (n=2 ya incluye WhatsApp).
- [ ] **Step 5: Commit** `feat(retomar): reglas de detección, firma, semana y resumen`.

### Task 3: Renderer — fotos de Higgsfield en `retomar`

**Files:**
- Modify: `renderer/src/schema.js`, `renderer/src/server.js`, `renderer/src/template-retomar.js`, `renderer/test/fixtures/retomar-ejemplo.js` (si hace falta)
- Test: `renderer/test/template-retomar.test.js`, nuevo `renderer/test/server-retomar.test.js`

**Interfaces:**
- Consumes: payload de la fase 1 + `image_briefs: [{slide: 'portada'|'cierre'|'demo', prompt}]` (máx. 3, prompt ≤ 1200), `images: {slide: url}`, `recomendacion` (≤ 300, 3 líneas).
- Produces: respuesta de `/render` para `retomar` con `images.urls: {slide: "<baseUrl>/p/<unique_id>/img/<archivo>"}` (solo las guardadas en local) además de los campos actuales; `renderRetomarHtml(datos, images = {})`.

- [ ] **Step 1: Pruebas que fallan.** `template-retomar.test.js`: con `images = {portada: 'img/p.jpg'}` la portada usa `img/p.jpg` y no `fotos.portada`; `images.demo` reemplaza `demo_imagen`; `recomendacion` aparece en la lámina «Para partir» y escapada. `server-retomar.test.js` (stub de `globalThis.fetch` como en `server-images.test.js`): con dos encargos se piden dos fotos a Higgsfield y la respuesta trae `images.urls.portada` que termina en `/img/portada.png`; un encargo cuya foto llega en `images` no se pide; **sin** `image_briefs` no hay ninguna llamada a Higgsfield (comportamiento de la fase 1); `image_briefs` con `slide: 'otra'` → 400; `recomendacion` de 301 caracteres → 400.
- [ ] **Step 2:** `cd renderer && node --test test/*.test.js` → FALLAN las nuevas.
- [ ] **Step 3: Implementar.** `validateRetomarPayload` valida los tres campos. En `server.js`, el camino `esRetomar` usa el mismo bloque de manifiesto/generación/persistencia que las propuestas cuando hay encargos o `images`; si no, conserva el atajo actual. Quitar el comentario «nunca genera imágenes». `renderRetomarHtml(d, images)`: `images[slide]` gana sobre `fotos.*`/`demo_imagen`.
- [ ] **Step 4:** `node --test test/*.test.js` → todas pasan (las 189 de antes + las nuevas).
- [ ] **Step 5: Commit** `feat(renderer): retomar genera fotos con Higgsfield y devuelve sus URL`.

### Task 4: Render en WordPress — encargos, fotos reutilizadas y propuesta v3

**Files:**
- Modify: `inc/retomar/render.php`
- Test: `tests/retomar/render-wp-test.php`

**Interfaces:**
- Consumes: Task 1 (`redaccion.image_briefs`, `fotos`), Task 3 (contrato del renderer).
- Produces: `at_rt_armar_render(array $seg, string $generacion = ''): array` agrega `recomendacion`, `image_briefs` (solo encargos cuyo `hash` difiere de `fotos[slide].hash` o sin foto) e `images` (`slide => url` de los que coinciden); `propuesta_url` solo si la propuesta vinculada tiene `flujo === 'v3'` o si hay `propuesta_url` externa. `at_rt_rest_vista` acepta `fotos: {slide: url}` y guarda `fotos[slide] = {url, hash}` (hash del encargo vigente); si `aprobacion.estado === 'cambios'` y queda `listo`, llama `at_rt_aviso_listo($id)` (Task 9; envolver en `function_exists`).

- [ ] **Step 1: Pruebas que fallan:** encargo nuevo → va en `image_briefs` y no en `images`; encargo igual al guardado → va en `images` con la URL guardada y no en `image_briefs`; propuesta vinculada con `flujo` `NULL` → `propuesta_url` vacío; con `v3` → `ver-presentacion.php?id=...`; el chequeo D8 del payload ignora `images` (URL del renderer) pero sigue rechazando `easypanel` en `demo_urls`; el callback con `fotos.portada` del token correcto se guarda con su hash; con URL de otro token o de otro host → 422 y nada guardado.
- [ ] **Step 2:** `AT_WP_LOAD=... php tests/retomar/render-wp-test.php` → FALLA.
- [ ] **Step 3: Implementar.** Validar cada URL de `fotos` con el mismo patrón de `at_rt_url_render_valida` pero bajo `/img/`. La huella del render (`huella`) incluye textos y encargos, no las URL de fotos.
- [ ] **Step 4:** prueba → `TODO OK`; correr además `tests/retomar/publica-wp-test.php` y `panel-wp-test.php`.
- [ ] **Step 5: Commit** `feat(retomar): render con encargos de foto y propuesta solo si es v3`.

### Task 5: Redacción con IA (WordPress)

**Files:**
- Create: `inc/retomar/redaccion.php`
- Modify: `inc/retomar/cargar.php` (require después de `render.php`)
- Test: `tests/retomar/redaccion-wp-test.php` (nuevo)

**Interfaces:**
- Consumes: `at_pt_llamar_n8n(string $url, array $cuerpo, int $timeout = 15): string`, `at_rt_pedir_render(int $id): string`, `at_rt_tiene_montos()`, `at_rt_internas()`, `at_rt_auth()`.
- Produces:
  - `at_rt_pedir_redaccion(int $id, string $pedido = ''): string` — `''`, `'sin_flujo'` (sin `AT_N8N_RETOMAR_REDACTAR`) o el error de transporte (deja `redaccion.estado = 'error'`). Cuerpo a n8n: `{id, generacion}`.
  - `at_rt_contexto_redaccion(int $id): ?array` — `{id, generacion, nombre, negocio, demo_urls, notas, pedido, textos_actuales, propuesta:{status, flujo, transcripcion (≤ 12000 caracteres), resumen}, historial:[≤ 10 títulos del CRM], reglas:{max_caracteres:600, max_lineas:6, max_recomendacion:300, sin_montos:true}, slides_foto:['portada','cierre'] + 'demo' si no hay imagen_url}`. Ruta `GET automatiza-tech/v1/retomar/(?P<id>\d+)/contexto`, `permission_callback` `at_rt_auth`.
  - `at_rt_guardar_redaccion(int $id, array $p): array|WP_Error` — ruta `POST .../retomar/(?P<id>\d+)/redaccion`, `at_rt_auth`. Cuerpo `{generacion, ok, textos:{quedamos, proponemos, partir, recomendacion}, image_briefs, nota}`.
- [ ] **Step 1: Pruebas que fallan:** sin la constante → `sin_flujo`; con la constante definida en la prueba y HTTP bloqueado → error y `redaccion.estado === 'error'`; contexto sin cabecera `X-AT-Secret` (vía `rest_do_request`) → 401; `generacion` vieja → 409; texto de 601 caracteres o 7 líneas → 422; `slide` desconocido → 422; montos la 1.ª vez → respuesta `{reintento:true}` y `redaccion.intentos === 2` con pedido que dice «sin montos»; montos la 2.ª vez → `redaccion.estado === 'revision'` y textos sin guardar; respuesta válida → textos guardados con `origen === 'ia'`, `redaccion.estado === 'lista'`, `image_briefs` guardados y `render.estado` en `pendiente` o `error` (render pedido); `ok:false` con nota que trae una URL → nota guardada con `[enlace omitido]`.
- [ ] **Step 2:** prueba → FALLA (archivo inexistente).
- [ ] **Step 3: Implementar.** Patrón de `inc/plan-trabajo/disparador.php` (llamada) y de `at_rt_rest_vista` (generación con CAS bajo `FOR UPDATE`). La IA nunca recibe ni devuelve montos: el contexto no incluye tablas de precios.
- [ ] **Step 4:** prueba → `TODO OK`; todas las de `tests/retomar/` siguen pasando.
- [ ] **Step 5: Commit** `feat(retomar): redacción de textos y encargos de foto con IA`.

### Task 6: Detección automática

**Files:**
- Create: `inc/retomar/detectar.php`; Modify: `inc/retomar/cargar.php`
- Test: `tests/retomar/detectar-wp-test.php` (nuevo)

**Interfaces:**
- Consumes: `at_rt_es_candidata`, `at_rt_necesita_propuesta`, `at_rt_crear`, `at_rt_en_bajas`, `at_rt_pedir_redaccion`, `at_rt_historia`.
- Produces: `at_rt_detectar(string $ahora): array{creados:int[], espera:int[]}`; `at_rt_fecha_envio_propuesta(array $p): string` (`Y-m-d`; usa el registro de envío si existe en el historial de la propuesta; si no, `created_at`).
- [ ] **Step 1: Pruebas que fallan** (propuestas de prueba insertadas y borradas en `register_shutdown_function`; filtro `query` que limita la detección a esos ids, como el de `envio-wp-test.php`): `sent` v3 de hace 20 días → un seguimiento `activo` con `propuesta_id`, `activado_at = $ahora`, `proximo_at = hoy`, `wa_autorizado = 0` y redacción pedida; `sent` con `flujo` NULL → `espera_propuesta` y **sin** redacción pedida; `sent` de hace 5 días, `aceptada`, `archivada`, correo de una ficha `cliente` y correo en bajas → nada; correr dos veces → ningún duplicado; si existe ficha CRM `prospecto` con ese correo → `crm_cliente_id` vinculado.
- [ ] **Step 2:** prueba → FALLA.
- [ ] **Step 3: Implementar.** Retorno anticipado si `!at_rt_esquema_listo()`. Datos del seguimiento: `nombre = client_name`, `negocio = company_name`, `email`, `telefono` de la propuesta o de la ficha, `canales = 'correo,whatsapp'`, `render = ['estado'=>'pendiente','nota'=>'Esperando redacción']`. Historial CRM `retomar_detectado` «🔁 Entró a Retomar».
- [ ] **Step 4:** prueba → `TODO OK`.
- [ ] **Step 5: Commit** `feat(retomar): detección automática de propuestas estancadas`.

### Task 7: Aprobación por enlace firmado

**Files:**
- Create: `inc/retomar/aprobacion.php`, `retomar-aprobar.php` (raíz; copia de `retomar.php` que llama `at_rt_pagina_aprobacion`); Modify: `inc/retomar/cargar.php`
- Test: `tests/retomar/aprobacion-wp-test.php` (nuevo)

**Interfaces:**
- Consumes: `at_rt_firma`, `at_rt_firma_valida`, `at_rt_programar`, `at_rt_pedir_redaccion`, `at_rt_correo_html`, `at_rt_panel_texto_manual`, `at_rt_url_wa_manual`, `at_rt_wa_automatico`.
- Produces:
  - `at_rt_emitir_aprobacion(int $id, string $ahora): ?array` — solo si `redaccion.estado === 'lista'` y `render.estado === 'listo'`; guarda `aprobacion = {estado:'por_aprobar', n: enviados+1, nonce: bin2hex(random_bytes(8)), huella: render.huella, emitido_at, vence: ts+604800, usada:false}` y devuelve `['aprobar','cambios','omitir','ver' => url]` con `home_url('/retomar-aprobar.php?id=..&a=..&k=<nonce>&v=<vence>&f=<firma>')`. Emitir de nuevo rota el nonce (los enlaces anteriores dejan de valer).
  - `at_rt_aprobacion_motivo(array $s, string $accion, array $q, int $ts): string` — `''` o `firma|vencido|usado|cambio|no_disponible` (`cambio`: nonce distinto del vigente o huella distinta de la del render actual).
  - `at_rt_resolver_aprobacion(int $id, string $accion, array $q, string $ahora, string $pedido = ''): array{ok:bool, motivo:string, mensaje:string, programado?:string}` — `aprobar`: `estado='aprobado'`, `usada=true`, `programado = proximo_at = at_rt_programar($ahora)`; `cambios`: exige pedido de 1 a 1000 caracteres, `estado='cambios'`, `usada=true`, agrega el pedido a `notas` y llama `at_rt_pedir_redaccion($id, $pedido)`; `omitir`: `estado='omitido'`, `usada=true`, historial `retomar_omitido` «🔁 Esta semana no», sin tocar `proximo_at`. Todo bajo `FOR UPDATE`.
  - `at_rt_pagina_aprobacion(array $get, string $metodo, array $post): array{http, html, cabeceras}` — mismas cabeceras `noindex`/`no-store` que `at_rt_pagina`. GET/HEAD: confirmación (y, con `a=ver`, el correo del prospecto tal como le llega); POST con nonce de WordPress `at_rt_aprobar_<id>`: ejecuta. Tras aprobar muestra la fecha programada y, si el WhatsApp automático está apagado y hay casilla y teléfono, el enlace «💬 Compartir a mano».
- [ ] **Step 1: Pruebas que fallan:** GET y HEAD de `aprobar`, `cambios` y `omitir` no cambian `aprobacion` ni `proximo_at` (Review Focus 1); POST `aprobar` válido el viernes `2026-10-23 14:00:00` → `programado = 2026-10-26`; segundo POST → `usado`, sin cambios (Review Focus 2); firma alterada → 403 `firma`; `ts > vence` → `vencido`; render rehecho (huella nueva) → `cambio`; emitir de nuevo → el enlace viejo da `cambio`; `cambios` sin pedido → error y nada cambia; con pedido → `aprobacion.estado = 'cambios'`, `notas` contiene el pedido y `redaccion.estado` en `pendiente|error`; `omitir` → `omitido`; `a=ver` muestra el nombre del prospecto y el botón «Ver resumen y demo»; el HTML escapa un `negocio` con `<script>`.
- [ ] **Step 2:** prueba → FALLA.
- [ ] **Step 3: Implementar.** Firma con `wp_salt('auth')`. Ninguna acción en GET/HEAD.
- [ ] **Step 4:** prueba → `TODO OK`.
- [ ] **Step 5: Commit** `feat(retomar): enlaces firmados para aprobar, pedir cambios u omitir`.

### Task 8: Envío solo de lo aprobado

**Files:**
- Modify: `inc/retomar/envio.php`
- Test: `tests/retomar/envio-wp-test.php`

**Interfaces:**
- Consumes: `aprobacion` (Task 7), `at_rt_canales` (Task 2).
- Produces: `at_rt_entregar(int $id, bool $prueba, string $ahora): array` rechaza con motivo `sin_aprobacion` lo que no tiene `aprobacion.estado === 'aprobado'` y `programado <= hoy` (salvo `$prueba`); `at_rt_finalizar_entrega` consume la aprobación en la misma transacción (`aprobacion = {estado:'enviado', n, at}`); `at_rt_marcar_propuesta_enviada(array $s, string $ahora): void` pasa a `sent` la propuesta vinculada si está en `lista` y es `v3` (`UPDATE ... WHERE id=%d AND status='lista'`), con nota «Enviada con el recordatorio de Retomar» vía `at_cc_anotar_simple` si existe.
- [ ] **Step 1: Pruebas que fallan.** Cambiar `rt_env_fixture` para que por defecto traiga `aprobacion = {estado:'aprobado', programado:'2026-10-19', usada:true}` y comprobar que las pruebas viejas siguen pasando. Nuevas: `activo` sin aprobación → `sin_aprobacion`, nada enviado; aprobado con `programado` mañana → nada hoy; aprobado → un correo y `aprobacion.estado === 'enviado'`; dos `rt_env_tick` seguidos → un solo correo (Review Focus 2); aprobado y luego baja (o reunión creada después de `activado_at`) → nada enviado y estado `baja`/`agendo` (Review Focus 3); 2.º envío con WhatsApp autorizado e interruptor encendido → `whatsapp` con un elemento; propuesta vinculada `lista` + `v3` → queda `sent` tras el envío; propuesta `sent` → sin cambios; «Enviar prueba al equipo» (`at_rt_enviar($id, true)`) funciona sin aprobación.
- [ ] **Step 2:** prueba → FALLA.
- [ ] **Step 3: Implementar** la verificación tras el claim (liberar `enviando` si falta la aprobación).
- [ ] **Step 4:** prueba → `TODO OK`; todas las de `tests/retomar/`.
- [ ] **Step 5: Commit** `feat(retomar): solo se envía lo que Luis aprobó`.

### Task 9: Correo semanal, aviso de corrección y reloj por hora

**Files:**
- Create: `inc/retomar/resumen.php`; Modify: `inc/retomar/envio.php` (`at_rt_tick`), `inc/retomar/cargar.php`
- Test: `tests/retomar/resumen-wp-test.php` (nuevo), `tests/retomar/envio-wp-test.php`

**Interfaces:**
- Consumes: `at_rt_config`, `at_rt_toca_resumen`, `at_rt_semana`, `at_rt_emitir_aprobacion`, `at_rt_tarjeta_html`, `at_rt_resumen_html`, `at_rt_detectar`.
- Produces:
  - `at_rt_resumen(string $ahora, bool $forzar = false): array{enviado:bool, tarjetas:int, motivo:string}` — incluye seguimientos `activo` con `proximo_at <= hoy` y `aprobacion.estado` distinto de `aprobado`, más los `espera_propuesta`; normal si redacción `lista` y render `listo` (emite aprobación), `revision`, `propuesta` o `preparacion` según corresponda; «Salieron de Retomar»: estados `agotado|agendo|cliente|baja|rechazado` con `actualizado_at > at_rt_resumen_desde`. Asunto `🔁 Retomar · N prospectos para revisar esta semana` (`1 prospecto` en singular). Sin tarjetas ni salidas → no envía y marca la semana (`motivo='vacio'`). Si `wp_mail` falla → no marca la semana (`motivo='correo_error'`). Al enviar: `at_rt_resumen_semana = at_rt_semana($ahora)`, `at_rt_resumen_desde = $ahora`.
  - `at_rt_aviso_listo(int $id): bool` — asunto `🔁 Retomar · Recordatorio corregido: <nombre>`, una tarjeta con enlaces nuevos.
  - `at_rt_tick(string $ahora): array` — en toda pasada: `at_rt_detectar`, luego `at_rt_resumen`; el envío de aprobados solo en día hábil y ventana. Devuelve además `detectados` y `resumen`. Retorno anticipado si `!at_rt_esquema_listo()`.
- [ ] **Step 1: Pruebas que fallan:** lunes `09:00` con un listo, un `espera_propuesta` y uno sin render → un correo al destino con `✅ Aprobar y enviar`, `retomar-aprobar.php`, `Necesita propuesta nueva`, `En preparación`, sin `easypanel` ni el host del renderer; segunda llamada la misma semana → sin correo (Review Focus 4); `wp_mail` falla → `correo_error` y la llamada siguiente sí envía (Review Focus 4); `forzar` → envía aunque ya se envió; sin nada → sin correo y semana marcada; tras el resumen, el enlace del resumen anterior da `cambio`; `at_rt_aviso_listo` manda una tarjeta; `at_rt_tick` de lunes `09:05` manda el resumen y ningún correo a prospectos; `at_rt_tick` de lunes `10:30` manda los aprobados.
- [ ] **Step 2:** prueba → FALLA.
- [ ] **Step 3: Implementar.** La lista se arma con `at_rt_lista()` filtrada en PHP; el destino sale de `at_rt_config()`. En cada tarjeta, «Ver la presentación completa» es `home_url('/retomar.php?t=<token>')` (la página pública en automatizatech.cl, nunca la URL del renderer: D8) y «Ver el correo tal como le llega» es el enlace `ver` de la aprobación; `dias` se cuenta desde `at_rt_fecha_envio_propuesta()` o, sin propuesta, desde `creado_at`.
- [ ] **Step 4:** pruebas → `TODO OK`.
- [ ] **Step 5: Commit** `feat(retomar): correo semanal de aprobación y reloj por hora`.

### Task 10: Panel y botón en la ficha CRM

**Files:**
- Modify: `inc/retomar/panel.php`, `wp-content/mu-plugins/crm-ai-completo.php` (una línea), `assets/css/retomar.css` (si hace falta)
- Test: `tests/retomar/panel-wp-test.php`

**Interfaces:**
- Consumes: `at_rt_pedir_redaccion`, `at_rt_pedir_render`, `at_rt_resumen`, `at_rt_config`, `at_cc_archivar_propuesta(object $p, int $usuario_id): array`, `at_cc_anotar_simple`.
- Produces: acciones del panel `guardar|previa|prueba|pausar|respondio|baja|revertir_baja|whatsapp_manual|config|resumen` (sin `activar`: `at_rt_panel_ejecutar('activar', ...)` → `WP_Error` `accion`); `at_rt_boton_ficha(array $cliente): void` (enlace «🔁 Retomar» a `admin.php?page=at-retomar&crm=<id>` solo si `tipo === 'prospecto'`).
- [ ] **Step 1: Pruebas que fallan:** `activar` → error; `guardar` sin textos → seguimiento `activo`, `activado_at` y `proximo_at` de hoy, redacción pedida; con los tres textos → `redaccion = {estado:'lista', origen:'manual'}` y render pedido, sin redacción; con dos de tres → error «Completa los tres textos o deja los tres vacíos»; `notas` se guarda; `config` con día `3`, hora `08:30` y destino válido → opciones guardadas; hora `25:00` → error y nada cambia; `resumen` (forzado) → un correo capturado; seguimiento `espera_propuesta` al que se vincula una propuesta `v3` del mismo correo → la anterior queda `archivada` con nota «Reemplazada por la propuesta N», el seguimiento pasa a `activo` y se pide redacción; `at_rt_boton_ficha(['id'=>5,'tipo'=>'prospecto'])` imprime `page=at-retomar&amp;crm=5`; con `tipo=cliente` no imprime nada; `?crm=<id>` precarga nombre, correo y teléfono de la ficha en el formulario.
- [ ] **Step 2:** prueba → FALLA.
- [ ] **Step 3: Implementar.** Textos del formulario: «Texto final (opcional: si lo dejas vacío, lo redacta la IA)» y «Notas para la IA». Columna «Revisión» en la lista (estado de redacción y de aprobación). Aviso del WhatsApp: «sale en cada recordatorio que apruebes a quien tenga la casilla». En `crm-ai-completo.php`, junto a las acciones de la ficha: `<?php if (is_array($cliente) && function_exists('at_rt_boton_ficha') && current_user_can('manage_options')) { at_rt_boton_ficha($cliente); } ?>`.
- [ ] **Step 4:** prueba → `TODO OK`; `tests/retomar/globales-wp-test.php` sigue pasando.
- [ ] **Step 5: Commit** `feat(retomar): panel sin «Activar», configuración y botón en la ficha`.

### Task 11: Flujos n8n

**Files:**
- Create: `N8N/retomar/build_retomar_redactar.py`, `N8N/retomar/retomar-redactar.json`
- Modify: `N8N/retomar/build_retomar_reloj.py`, `build_retomar_render.py`, sus JSON, `N8N/retomar/README.md`

**Interfaces:**
- Consumes: rutas `contexto` y `redaccion` (Task 5), respuesta del renderer con `images.urls` (Task 3), callback `vista` con `fotos` (Task 4).
- Produces: «Retomar · Redactar» (webhook `retomar-redactar`, auth «AT REST Secret (header)», `responseMode: onReceived`) → «Leer contexto» (GET `https://automatizatech.cl/?rest_route=/automatiza-tech/v1/retomar/{{id}}/contexto`) → «Preparar pedido» (code) → «Redactar» (`n8n-nodes-base.openAi`, modelo `gpt-4o`, credencial `{id:'g52IEXpRfN5r7jKw', name:'OpenAi account'}`, respuesta JSON) → «Leer respuesta» (code) → «Guardar redacción» (POST `.../retomar/{{id}}/redaccion`); cualquier falla → POST con `ok:false` y `nota`. Reloj: cron `0 5 * * * *` (minuto 5 de cada hora, todos los días), `timezone: America/Santiago`. Render: timeout `300000` y callback con `fotos: body.images && body.images.urls || {}`.
- [ ] **Step 1:** escribir `build_retomar_redactar.py` con el patrón de `N8N/plan-trabajo/build_plan_1_borrador.py`. El prompt pide: español de Chile y voz del equipo en plural; los tres textos (≤ 600 caracteres, ≤ 6 líneas) y `recomendacion` (≤ 300, ≤ 3 líneas); **ningún monto ni precio**; ajustarse al presupuesto y partir por etapas; encargos de foto para cada `slides_foto`: escena fotográfica 16:9 del rubro del prospecto, con personas, **sin texto, letreros, etiquetas ni logos**; salida JSON `{textos, image_briefs}`.
- [ ] **Step 2:** `python N8N/retomar/build_retomar_redactar.py`, `build_retomar_reloj.py`, `build_retomar_render.py` → JSON regenerados; `python -c "import json,sys;[json.load(open(f,encoding='utf-8')) for f in sys.argv[1:]]" N8N/retomar/*.json` sin error; `grep -c '0 5 \* \* \* \*' N8N/retomar/retomar-reloj.json` → 1; `grep -c '300000' N8N/retomar/retomar-render.json` ≥ 1; `grep -ci 'gpt-4o' N8N/retomar/retomar-redactar.json` ≥ 1; ningún JSON contiene valores de credenciales (solo `id` y `name`).
- [ ] **Step 3:** actualizar `N8N/retomar/README.md` (flujos, constante `AT_N8N_RETOMAR_REDACTAR`, orden de activación).
- [ ] **Step 4: Commit** `feat(retomar): flujo de redacción y reloj por hora en n8n`.

### Task 12: Guía, lista de subida y regresión completa

**Files:**
- Modify: `Docs/METODO_AT/RETOMAR-PROSPECTOS.md`, puntero AT-RETOMAR-001 de `CLAUDE.md`/`AGENTS.md` del worktree

- [ ] **Step 1: Guía.** Sección «Fase 2»: cómo funciona la semana, estados nuevos, configuración, qué ve Luis en el correo, qué pasa con cada botón, fotos y costo (US$0,0032 por foto según `renderer/src/higgsfield.js`), propuestas antiguas (`espera_propuesta`), y la **lista de subida**: `OBLIGATORIO` los archivos de `inc/retomar/` tocados y nuevos (`cargar.php` al final), `retomar-aprobar.php` en la raíz, `wp-content/mu-plugins/crm-ai-completo.php`, `assets/css/retomar.css` si cambió; tras subir, `wp eval 'at_rt_migrar_esquema(); echo get_option("at_rt_schema");'` → `2`; constante `AT_N8N_RETOMAR_REDACTAR` en wp-config con `--anchor` (como en la fase 1); ZIP del renderer con `git archive`; crear/actualizar los tres flujos n8n; activar el reloj solo con OK de Luis. No subir `tests/`, `Docs/`, `N8N/`.
- [ ] **Step 2: Regresión completa:** todas las de `tests/retomar/` → `TODO OK`; `php tests/retomar/puras-test.php` → todo `ok`; `cd renderer && node --test test/*.test.js` → todas pasan; suites de `tests/plan/` (33/36: las mismas 3 que fallan en la base `35bc894`) y `tests/entregables/` (11/11). Si algo falla también en la base, demostrarlo apuntando las junctions a la base antes de declararlo histórico.
- [ ] **Step 3:** `graphify update .` y checkpoint de Aseo `before-push` (`{"platform":"claude","session":"<sesión>","root":"<worktree>","milestone":"before-push"}` a `aseo.mjs checkpoint`).
- [ ] **Step 4: Commit y push** `docs(retomar): guía de la fase 2 y lista de subida` a `claude/retomar-prospectos-final`. Sin merge ni despliegue.
