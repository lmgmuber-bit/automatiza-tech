# Plan de trabajo — Etapa 1 · ESQUELETO Y CONTRATO DE INTERFACES (fuente única para quien redacta tareas)

Spec: `C:\wamp64\www\automatiza-tech\.worktrees\plan-trabajo\Docs\superpowers\specs\2026-09-27-plan-de-trabajo-design.md`
(leerlo entero; decisiones 1 a 12). Hechos verificados del código: `hechos.md` (misma carpeta).
Idioma: todo texto visible, comentarios y mensajes en **español de Chile**. El repo es **PÚBLICO**: ningún nombre,
correo, teléfono ni dato real de clientes en código, pruebas, fixtures ni commits (usar «Cliente Prueba»,
«[PRUEBA] …», `prueba-plan-…@example.com`).

## Ramas y dónde vive cada cosa

- **WordPress + n8n:** rama `claude/plan-de-trabajo`, worktree `C:\wamp64\www\automatiza-tech\.worktrees\plan-trabajo`.
  Sale de `claude/cierre-cliente` (= código del cierre en PROD, commit `7e7c3bf` + docs). La Task 0 le mergea
  `origin/claude/propuestas-json-robusto` (PR #51: `N8N/propuestas-v3/json_guard.py`, `fotos_guard.py` y builders
  que corren en PROD). Probado: merge sin conflictos.
- **Renderer:** rama `claude/plan-renderer` desde `origin/main` (tiene el renderer vigente en Easypanel: enlaces,
  tabla densa y aviso de lámina `at-deck-lamina`). Worktree `C:\wamp64\www\automatiza-tech\.worktrees\plan-renderer`.
  El renderer de las otras ramas es más viejo: **nunca** tocar `renderer/` en `claude/plan-de-trabajo`.

## Herramientas locales (Windows, Git Bash)

- PHP: `PHP=/c/wamp64/bin/php/php8.4.15/php.exe` (no hay `php` en el PATH de bash).
- Pruebas puras: `"$PHP" tests/plan/puras-test.php` → termina en `TODO OK` (exit 0) o `N FALLAS` (exit 1).
- Pruebas con WordPress: `AT_WP_LOAD=<scratchpad>/wp-local-plan/wp-load.php "$PHP" tests/plan/<x>-wp-test.php`.
  El sitio `wp-local-plan` lo arma la Task 0 igual que `wp-local-cierre` (uniones a este worktree; correos y HTTP
  salientes bloqueados por `router.php`/filtros). `<scratchpad>` =
  `C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad`.
- Renderer: `cd renderer && npm install --no-package-lock && node --test test/*.test.js` (no crear package-lock).
- n8n builders: `python N8N/plan-trabajo/build_plan_*.py` escribe el JSON; `python N8N/plan-trabajo/probar_plan.py`
  prueba el JS embebido con `node` (patrón de `N8N/propuestas-v3/probar_json.py`: `node -e` con `const DATOS = …`).

## Reglas globales (valen para todas las tareas)

1. `functions.php` NO se toca. El módulo se carga desde `inc/admin-proposals.php`, una línea justo después de
   `require_once __DIR__ . '/cierre-cliente/cargar.php';` (línea 91): `require_once __DIR__ . '/plan-trabajo/cargar.php';`
2. Módulo independiente (decisión 10): todo el código nuevo en `wp-content/themes/automatiza-tech/inc/plan-trabajo/`;
   solo se tocan fuera de él: `inc/admin-proposals.php` (1 línea), `contracts/contract-service.php` (hook de firma),
   `wp-content/mu-plugins/crm-ai-completo.php` (botón + panel de la pestaña), `inc/admin-followup-meetings.php`
   (precarga por GET). Nada en `inc/propuestas-admin/` ni en `inc/cierre-cliente/`.
3. Prefijo de funciones PHP `at_pt_`; opciones `at_pt_*`; acciones admin-post `at_pt_*`; tabla
   `{$wpdb->prefix}automatiza_planes_trabajo`.
4. `puras.php` no usa WordPress (se prueba sin WP; sin guardia ABSPATH, como `cierre-cliente/puras.php`).
5. Fechas: cadenas `Y-m-d`; cálculos con `DateTimeImmutable` en UTC; nada depende de la zona del servidor.
6. Nada le llega al cliente en la Etapa 1: cero correos o WhatsApp al cliente. Los correos van solo a Luis (n8n).
7. Webhooks WP → n8n con cabecera `X-AT-Secret: AT_REST_SECRET` (igual que `at_v3_llamar_n8n`); rutas REST n8n → WP
   con `permission_callback => 'automatiza_proposals_rest_auth'` (misma clave). No se crea ningún secreto nuevo
   (cambio respecto del spec, que proponía `X-AT-Plan-Key`: se reutiliza la credencial n8n «AT REST Secret (header)»
   id `1NI0sJKc0kC430pb`).
8. Toda escritura del panel: `current_user_can('manage_options')` + nonce por plan; redirección de vuelta a
   `admin.php?page=automatiza-crm-ficha&id=<crm_id>&pt=<plan_id>#tab-plan` con `pt_msg=<clave>`.
9. Salida HTML siempre escapada (`esc_html`, `esc_attr`, `esc_url`); SQL siempre con `$wpdb->prepare`.
10. Commits pequeños, uno por paso verde, mensaje en español, terminando en
    `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Modelo de datos

### Tabla `wp_automatiza_planes_trabajo` (dbDelta, opción `at_plan_schema` = `'1'`)

```sql
CREATE TABLE {$t} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id BIGINT UNSIGNED NOT NULL,
  crm_cliente_id BIGINT UNSIGNED NULL,
  tech_id BIGINT UNSIGNED NULL,
  propuesta_id BIGINT UNSIGNED NULL,
  codigo CHAR(12) NOT NULL,
  estado VARCHAR(20) NOT NULL DEFAULT 'generando',
  fecha_inicio DATE NULL,
  payload LONGTEXT NULL,
  comentarios TEXT NULL,
  nota TEXT NULL,
  view_url VARCHAR(500) NULL,
  pdf_url VARCHAR(500) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  enviado_at DATETIME NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_contrato (contrato_id),
  UNIQUE KEY uniq_codigo (codigo),
  KEY idx_crm (crm_cliente_id)
) {$charset};
```
`codigo`: 12 caracteres `[A-Za-z0-9]` (`wp_generate_password(12, false)`), único. `tech_id` = `contracts.client_id`;
`crm_cliente_id` = `automatiza_tech_clients.crm_cliente_id` de esa ficha (puede ser NULL); `propuesta_id` =
`contracts.proposal_id` (puede ser NULL: un contrato sin propuesta también tiene plan, decisión 10).

### Estados y transiciones (función pura `at_pt_transiciones(): array`)

```
generando -> borrador | error
borrador  -> borrador | cambios | aprobando | error
cambios   -> borrador | error
aprobando -> listo | error
listo     -> borrador | cambios | aprobando | enviado
error     -> generando | borrador | cambios | aprobando
enviado   -> (ninguna en la Etapa 1)
```
«Destrabar» = pasar `generando|cambios|aprobando` a `error` con nota «Destrabado por Luis». Desde `error`, el
panel ofrece: «Reintentar borrador» (→ generando, si no hay payload) o «Volver al borrador» (→ borrador, si hay).

### JSON del plan (`payload`), forma normalizada que produce `at_pt_validar_plan()`

```json
{
  "version": 1,
  "proyecto": "Nombre del proyecto",
  "fecha_firma": "2026-09-28",
  "fecha_inicio": "2026-10-05",
  "fases": [
    {
      "clave": "diseno_desarrollo",
      "titulo": "Diseño y desarrollo",
      "descripcion": "texto editable por Luis",
      "bloques": [
        {
          "nombre": "Arranque",
          "entregable": "",
          "entrega": false,
          "actividades": [
            {"nombre": "Reunión de inicio", "detalle": "", "responsable": "ambos", "dias_habiles": 1,
             "servicio": "", "etapa": "arranque", "origen": "tabla", "en_paralelo": false,
             "desde": "2026-10-05", "hasta": "2026-10-05"}
          ]
        }
      ]
    }
  ],
  "hitos": [{"nombre": "Diseño aprobado", "despues_de": "Diseño", "fecha": "2026-10-23"}],
  "necesitamos_de_ti": ["Logo y colores"],
  "reuniones": [{"nombre": "Reunión de inicio", "detalle": ""}],
  "soporte": {"garantia_meses": 3, "mensuales": []},
  "image_briefs": [{"slide": "metodo", "prompt": "…"}],
  "cronograma": {"inicio": "…", "fin": "…", "semanas": 6,
                 "barras": [{"fase": "diseno_desarrollo", "etiqueta": "Diseño", "tipo": "trabajo", "responsable": "at",
                             "desde": "…", "hasta": "…"}],
                 "hitos": [{"nombre": "…", "fecha": "…"}]}
}
```
Enumeraciones (constantes en `puras.php`):
- `fases[].clave` ∈ `diseno_desarrollo` («Diseño y desarrollo»), `implementacion` («Implementación»), `soporte`
  («Soporte y mejora continua»); títulos fijos por clave; orden fijo en ese orden.
- `responsable` ∈ `at`, `cliente`, `ambos`. `origen` ∈ `tabla`, `ia`, `luis`.
- `etapa` ∈ `arranque`, `diseno`, `desarrollo`, `pruebas`, `implementacion`, `soporte`, `''`.
- `dias_habiles`: entero 1 a 60. `cronograma.barras[].tipo` ∈ `trabajo`, `revision`.
- Slides de foto del plan (`image_briefs[].slide`): `cover`, `metodo`, `gantt`, `fase_1`, `fase_2`, `fase_3`,
  `necesitamos`, `reuniones`, `portal`, `cierre`.

### Tabla de tiempos de referencia (opción `at_pt_duraciones`, JSON)

`at_pt_duraciones_defecto()` devuelve (días hábiles; clave de servicio => valores):
```php
[
  'sitio_una_pagina'   => ['nombre' => 'Sitio de una página',            'diseno' => 3, 'desarrollo' => 5,  'pruebas' => 2, 'implementacion' => 1],
  'sitio_web_tienda'   => ['nombre' => 'Sitio web o tienda',             'diseno' => 5, 'desarrollo' => 10, 'pruebas' => 3, 'implementacion' => 2],
  'asistente_basico'   => ['nombre' => 'Asistente básico',               'diseno' => 2, 'desarrollo' => 4,  'pruebas' => 2, 'implementacion' => 1],
  'asistente_avanzado' => ['nombre' => 'Asistente avanzado',             'diseno' => 3, 'desarrollo' => 8,  'pruebas' => 3, 'implementacion' => 2],
  'plataforma'         => ['nombre' => 'Plataforma o sistema a medida',  'diseno' => 8, 'desarrollo' => 20, 'pruebas' => 5, 'implementacion' => 3],
  'automatizacion_n8n' => ['nombre' => 'Automatización (flujo n8n)',     'diseno' => 2, 'desarrollo' => 5,  'pruebas' => 2, 'implementacion' => 1],
  'google_ads'         => ['nombre' => 'Google Ads (puesta en marcha)',  'diseno' => 2, 'desarrollo' => 3,  'pruebas' => 1, 'implementacion' => 1],
]
```
Arranque fijo (`at_pt_arranque()`): bloque «Arranque» con «Reunión de inicio» (ambos, 1 día) y «Entrega de logo,
textos y accesos» (cliente, 3 días), ambos `etapa: 'arranque'`, `origen: 'tabla'`. Revisión del cliente fija:
5 días hábiles (`AT_PT_DIAS_REVISION = 5`, cláusula 6.1), una vez después de cada bloque con `entrega: true`.

## Reglas de cálculo (puras)

- **Día hábil:** lunes a viernes y no feriado. Feriados = lista `Y-m-d` leída del ajuste existente
  `get_option('automatiza_chat_schedule')['holidays']` (texto, una fecha por línea; ver hechos §6).
- `at_pt_sumar_habiles($desde, $n, $feriados)`: si `$desde` no es hábil, parte en el siguiente hábil; cuenta `$desde`
  como día 1; devuelve el día hábil n-ésimo (inclusive).
- `at_pt_siguiente_habil($fecha, $feriados)`: primer día hábil estrictamente después de `$fecha`.
- `at_pt_inicio_por_defecto($fecha_firma, $feriados)`: el primer lunes estrictamente posterior a la firma; si ese lunes
  no es hábil, el siguiente hábil.
- **Secuencia** (`at_pt_calcular_fechas`): fases en su orden, bloques en su orden, actividades en su orden. La primera
  actividad de un bloque parte en el cursor. Cada actividad siguiente parte el hábil siguiente al **máximo `hasta`
  ya calculado en ese bloque**, salvo `en_paralelo: true`, que parte el mismo día que la actividad anterior (la
  primera de un bloque nunca es paralela). Fin de bloque = máximo `hasta`. Si `entrega: true`: barra `revision`
  (responsable `cliente`, etiqueta «Tu revisión», 5 hábiles) desde el hábil siguiente al fin del bloque; el cursor sigue
  después de la revisión. Si no, el cursor sigue el hábil siguiente al fin del bloque.
- **Barras:** una por bloque (`tipo: 'trabajo'`, responsable = el común si todas sus actividades comparten uno; si
  no, `ambos`) más una por revisión. **Hitos:** `fecha` = fin de la revisión del bloque `despues_de` si ese bloque
  tiene entrega; si no, su fin; los hitos cuyo `despues_de` no existe se descartan con aviso. Se agrega siempre el
  hito «Entrega estimada» = fin del último bloque de `implementacion` (o, si no hay esa fase, fin del cronograma).
  `semanas` = semanas de calendario (lunes a domingo) que toca el rango `inicio`–`fin`.
- **Tabla → días** (`at_pt_aplicar_tabla`): para cada par (`servicio`, `etapa`) con servicio en la tabla y etapa en
  `diseno|desarrollo|pruebas|implementacion`, reparte el total de la tabla entre las actividades de ese par
  proporcional a los días que propuso la IA (pesos; mínimo 1 por actividad; resto por mayor fracción; si el total es
  menor que la cantidad de actividades, 1 a cada una) y las marca `origen: 'tabla'`. Si el grupo tiene alguna actividad
  `origen: 'luis'`, el grupo no se toca. Actividades sin par en la tabla y que no sean `luis` quedan `origen: 'ia'`.
- **Origen al guardar desde el panel** (`at_pt_marcar_ediciones($anterior, $nuevo)`): una actividad nueva o cuyos
  `dias_habiles` cambiaron respecto de la guardada queda `origen: 'luis'`; las demás conservan su origen.

## Cuerpo que WordPress manda al renderer (lo arma `at_pt_armar_render(array $plan, array $datos, bool $final): array`)

```json
{
  "document_type": "plan",
  "unique_id": "<codigo del plan, 12 caracteres>",
  "draft": true,
  "company_name": "…", "client_name": "…", "proyecto": "…",
  "fecha_firma_larga": "28 de septiembre de 2026",
  "fecha_inicio": "2026-10-05", "fecha_fin": "2026-11-20", "semanas": 7,
  "metodo": {"hechas": ["diagnostico", "priorizacion"], "actual": "propuesta",
             "proximas": ["diseno_desarrollo", "implementacion", "soporte"]},
  "fases": [ /* las del plan, con descripcion, bloques, actividades con desde/hasta */ ],
  "cronograma": { /* el del plan */ },
  "necesitamos_de_ti": ["…"], "reuniones": [{"nombre": "…", "detalle": "…"}],
  "soporte": {"garantia_meses": 3, "mensuales": []},
  "portal_url": "https://automatizatech.cl/?crm_view=timeline&cid=…&token=…",
  "agenda": {"whatsapp_url": "https://wa.me/56927002984?text=…", "web_url": ""},
  "image_briefs": [],
  "images": {}
}
```
`draft: true` → `image_briefs: []` (vista previa sin fotos). `draft: false` → `image_briefs` = los del plan.
`agenda.web_url` queda `''` en la Etapa 1 (la agenda web es de la Etapa 2; el renderer muestra ese enlace solo si viene
con valor). `whatsapp_url` = `https://wa.me/<at_cc_whatsapp_at()>?text=` + mensaje urlencodeado «Hola Tech, quiero
agendar la llamada de seguimiento de mi plan de trabajo (código XXXX)». `portal_url` = `at_crm_url_portal($crm_id)`
(puede ser `''`). `images` lo llena n8n con las fotos reutilizadas de la propuesta (ver n8n).

## Funciones PHP (contrato; firmas exactas)

`inc/plan-trabajo/puras.php` (sin WordPress):
- `at_pt_fases_validas(): array` — `['diseno_desarrollo' => 'Diseño y desarrollo', 'implementacion' => 'Implementación', 'soporte' => 'Soporte y mejora continua']`
- `at_pt_responsables(): array` — `['at' => 'AutomatizaTech', 'cliente' => 'Tú', 'ambos' => 'Ambos']`
- `at_pt_etapas(): array`, `at_pt_origenes(): array`, `at_pt_slides_foto(): array`
- `at_pt_transiciones(): array`, `at_pt_transicion_valida(string $de, string $a): bool`
- `at_pt_duraciones_defecto(): array`, `at_pt_normalizar_duraciones(array $tabla): array` (descarta filas inválidas;
  claves `[a-z0-9_]{2,40}`; números enteros 0..60; nombre 1..60 caracteres)
- `at_pt_arranque(): array` (el bloque Arranque)
- `at_pt_feriados_de_texto(string $raw): array`
- `at_pt_es_habil(string $f, array $feriados): bool`, `at_pt_siguiente_habil(string $f, array $feriados): string`,
  `at_pt_sumar_habiles(string $desde, int $n, array $feriados): string`,
  `at_pt_inicio_por_defecto(string $fecha_firma, array $feriados): string`
- `at_pt_validar_plan(array $plan): array` → `['ok' => bool, 'errores' => string[], 'avisos' => string[], 'plan' => array]`
  (normaliza: títulos fijos por clave, orden de fases, agrega el bloque Arranque al inicio de la primera fase si falta,
  recorta textos, fuerza `en_paralelo` false en la primera actividad de cada bloque, descarta image_briefs de slides
  no válidos, un brief por slide)
- `at_pt_aplicar_tabla(array $plan, array $tabla): array`
- `at_pt_marcar_ediciones(array $anterior, array $nuevo): array`
- `at_pt_calcular_fechas(array $plan, string $inicio, array $feriados): array` (llena desde/hasta, `cronograma`,
  `hitos[].fecha`, `fecha_inicio`)
- `at_pt_fecha_larga(string $ymd): string` («28 de septiembre de 2026»)
- `at_pt_armar_render(array $plan, array $datos, bool $final): array` — `$datos` = `['codigo','company_name',
  'client_name','portal_url','whatsapp','fecha_firma']`
- `at_pt_costo_fotos(array $image_briefs, bool $hay_propuesta): array` → `['fotos' => int, 'usd_lista' => float,
  'usd_max' => float]` (cuenta los briefs válidos; si hay propuesta, `cover` y `cierre` no cuentan porque se
  reutilizan; US$0,0032 por foto de lista y el máximo con reintentos = x2, como `at_propuesta_costo_fotos`)

`inc/plan-trabajo/datos.php` (WordPress):
- `at_pt_tabla(): string`, `at_pt_migrar_esquema(): void` (hook `admin_init` y llamada perezosa desde las funciones de
  lectura)
- `at_pt_plan(int $id): ?object`, `at_pt_plan_por_codigo(string $codigo): ?object`,
  `at_pt_plan_de_contrato(int $contrato_id): ?object`, `at_pt_planes_de_crm(int $crm_id): array`
- `at_pt_payload(object $fila): array` (json_decode seguro; `[]` si vacío)
- `at_pt_crear_plan(int $contrato_id): int|WP_Error` (idempotente: si ya existe devuelve su id; exige contrato
  `type === 'servicios'` y `status === 'signed'`; estado inicial `generando`)
- `at_pt_guardar(int $id, array $campos): bool` (columnas permitidas: estado, fecha_inicio, payload (array → JSON),
  comentarios, nota, view_url, pdf_url, enviado_at)
- `at_pt_cambiar_estado(int $id, string $a, string $nota = ''): bool` (valida con `at_pt_transicion_valida`)
- `at_pt_feriados(): array` (lee la opción de feriados)
- `at_pt_duraciones(): array` (opción `at_pt_duraciones` normalizada, o la de defecto si está vacía)
- `at_pt_contratos_sin_plan(int $crm_id): array` (contratos `servicios` firmados de las fichas de ese cliente que aún no
  tienen plan)
- `at_pt_datos_render(object $fila): array` (arma `$datos` para `at_pt_armar_render`)
- `at_pt_contexto(object $fila): array` (lo que recibe la IA; ver REST)

`inc/plan-trabajo/disparador.php`:
- constantes con valor por defecto sobrescribible en wp-config:
  `AT_N8N_PLAN_BORRADOR` = `https://n8n-n8n.kchiba.easypanel.host/webhook/plan-v1-borrador`,
  `AT_N8N_PLAN_CAMBIOS` = `…/webhook/plan-v1-cambios`, `AT_N8N_PLAN_RENDER` = `…/webhook/plan-v1-render`
- `at_pt_llamar_n8n(string $url, array $cuerpo): string` ('' si 2xx, si no el motivo; timeout 15; X-AT-Secret)
- `at_pt_al_firmar(object $contrato): void` (listener de `at_contrato_firmado`; nunca lanza; si falla, nota `error`)
- `at_pt_iniciar_borrador(int $plan_id): string` (llama a n8n borrador; si falla → estado error con el motivo)
- `at_pt_pedir_render(int $plan_id, string $modo, bool $aviso): string` (`$modo` ∈ `draft`,`final`)
- En `contracts/contract-service.php`, entre `ContractMailer::send_signed_copy_internal(...)` y `return $fresh;`:
  ```php
  try {
      do_action('at_contrato_firmado', $fresh);
  } catch (\Throwable $e) {
      error_log('at_contrato_firmado: ' . $e->getMessage());
  }
  ```

`inc/plan-trabajo/rest.php` (namespace `automatiza-tech/v1`, `permission_callback => 'automatiza_proposals_rest_auth'`):
- `GET  /plan/(?P<id>\d+)/contexto` → `{ok, plan_id, codigo, estado, proyecto, empresa, cliente, fecha_firma,
  contrato: {servicios_contratados, alcance, entregables, fases_siguientes, plazo}, propuesta: null |
  {company_name, solution_text, how_it_works, extracto_reunion (primeros 3000 caracteres de transcript_text)},
  tabla (at_pt_duraciones()), slides_foto, plan_actual (payload o null), comentarios}`
- `POST /plan/(?P<id>\d+)/borrador` body `{plan, origen: 'borrador'|'cambios'}` → valida, aplica tabla (solo si
  origen = borrador; en cambios respeta los días que no son de tabla), marca ediciones contra el anterior en cambios,
  calcula fechas (fecha_inicio guardada o por defecto), guarda, estado → `borrador`, pide render draft con aviso;
  responde `{ok, errores, avisos}`; si no valida → estado `error` con los errores en la nota, HTTP 422.
- `GET  /plan/(?P<id>\d+)/render?modo=draft|final` → `{ok, render: <cuerpo>, propuesta_uid: '' | '<unique_link_id>'}`
- `POST /plan/(?P<id>\d+)/vista` body `{modo, ok, view_url, pdf_url, faltan: [slides], nota}` → guarda URLs; en
  `final` con `ok` y sin `faltan`: aprobando → `listo`; en `final` sin ok: → `error` con nota; en `draft` no cambia
  el estado (si no ok, solo nota). Responde `{ok}`.
- `POST /plan/(?P<id>\d+)/error` body `{nota}` → estado `error` (si la transición es válida) y nota.

`inc/plan-trabajo/ajustes.php`: submenú `add_submenu_page('automatiza-crm', 'Ajustes del plan de trabajo',
'Ajustes del plan', 'manage_options', 'at-pt-ajustes', 'at_pt_render_ajustes')`; tabla editable (agregar, quitar,
editar filas) que guarda `at_pt_duraciones` por admin-post `at_pt_guardar_duraciones` (nonce), normalizada.

`inc/plan-trabajo/panel.php`:
- `at_pt_render_pestana(array $cliente): void` — contenido de la pestaña «🗓️ Plan de trabajo» (ver spec §6)
- admin-post: `at_pt_crear` (contrato_id), `at_pt_guardar` (plan_id, fecha_inicio, plan_json, textos), `at_pt_cambios`
  (plan_id, comentarios), `at_pt_aprobar` (plan_id), `at_pt_destrabar` (plan_id), `at_pt_reintentar` (plan_id)
- `at_pt_url_ficha(int $crm_id, int $plan_id = 0, string $msg = ''): string`
- `at_pt_datos_agenda(int $plan_id): array` → `['client_name','client_email','company_name','phone',
  'meeting_subject','notes']` para precargar el formulario de seguimiento
- assets `assets/css/plan-trabajo.css` y `assets/js/plan-trabajo.js` (se encolan solo en `page=automatiza-crm-ficha`)

Precarga del seguimiento: `inc/admin-followup-meetings.php` acepta `&pt_plan=<id>` sin `edit_id`: si existe
`at_pt_datos_agenda`, rellena los campos del formulario nuevo (no pasa a modo edición ni crea `meeting_id`). El botón
del panel abre `admin.php?page=automatiza-followup&pt_plan=<id>`. El envío sigue el flujo normal (Calendar, correo y
WhatsApp con las casillas de siempre; lo decide Luis en ese formulario).

## n8n (carpeta `N8N/plan-trabajo/`, rama `claude/plan-de-trabajo`)

- `build_plan_1_borrador.py` → `plan-1-borrador.json`, workflow «Plan de trabajo · 1 Borrador»: Webhook POST
  `plan-v1-borrador` (headerAuth, credencial «AT REST Secret (header)» `1NI0sJKc0kC430pb`, responseMode onReceived) →
  GET `{WP}/plan/{id}/contexto` → OpenAI gpt-4o (`PROMPT_PLAN`, temp 0.3) → Code «Leer plan» (`JS_LEER_JSON` +
  `JS_LIMPIAR_FOTOS` + validación mínima de forma) → POST `{WP}/plan/{id}/borrador` `{plan, origen:'borrador'}`; si
  algo falla → POST `{WP}/plan/{id}/error {nota}` y correo a Luis. `settings.errorWorkflow = 'm7TOfKznVSBGz4Nd'`.
- `build_plan_2_cambios.py` → `plan-2-cambios.json`, «Plan de trabajo · 2 Cambios»: igual, webhook `plan-v1-cambios`,
  prompt `PROMPT_CAMBIOS` (aplica `comentarios` sobre `plan_actual`; no cambia `dias_habiles` de actividades
  `origen: 'luis'`; devuelve el plan completo), POST borrador `{plan, origen:'cambios'}`.
- `build_plan_3_render.py` → `plan-3-render.json`, «Plan de trabajo · 3 Render»: webhook `plan-v1-render` body
  `{id, modo, aviso}` → GET `{WP}/plan/{id}/render?modo=` → si `final` y hay `propuesta_uid`: GET
  `{RENDERER_BASE}/p/{uid}/img/manifest.json` (nunca falla) → Code «Reutilizar fotos» (`reutilizarFotosPlan(render,
  manifest, base, uid)`: `cover` ← foto `cover` de la propuesta, `cierre` ← foto `next_steps`; las pone en
  `render.images` como URL absoluta y quita esos slides de `render.image_briefs`) → POST renderer (credencial
  «X-AT-Render-Key» `fj2orzbsjlnHaiLd`, timeout 290000) → si `final` y faltan fotos o no hay view_url, reintenta hasta
  3 renders en total → POST `{WP}/plan/{id}/vista {modo, ok, view_url, pdf_url, faltan, nota}` → correo a Luis si
  `aviso` o `final` o error.
- `correos_plan.py`: correos de marca a Luis reutilizando `N8N/propuestas-v3/email_tpl.py` (import con `sys.path`),
  enlazando SOLO a `automatizatech.cl` (nunca a `*.easypanel.host`, regla SMTP): panel =
  `https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-ficha&id=<crm>&pt=<plan>#tab-plan`; vista previa =
  el mismo panel (la vista se abre desde ahí).
- `probar_plan.py`: prueba con `node` el JS embebido (leer plan, reutilizar fotos, reintentos) sin llamar a OpenAI ni n8n.
- Despliegue con `python N8N/propuestas-v3/deploy.py <ruta absoluta al json>` (crea si no existe; activa). No se
  despliega en la Etapa de código: lo autoriza Luis (Task final).

## Renderer (rama `claude/plan-renderer`)

- `src/template.js`: solo AGREGAR exportaciones (`STYLE`, `SCRIPT`, `renderDeckControls`, `backgroundStyle`,
  `logoMark`, `LOGO_URL`, `CONTACTS` y lo que el template del plan necesite); el HTML de propuestas debe quedar
  byte a byte igual (prueba: hash del HTML de un payload fijo antes y después).
- `src/schema.js`: `validatePlanPayload(body)` (`unique_id`, `company_name`, `proyecto`, `fases` no vacío,
  `cronograma` con `inicio`, `fin`, `barras` arreglo).
- `src/server.js`: `POST /render` elige por `req.body.document_type === 'plan'` → `validatePlanPayload` +
  `renderPlanHtml`; lo demás (clave, imágenes, manifiesto, respuesta) igual. Sin `document_type` = propuesta, como hoy.
- `src/template-plan.js`: `renderPlanHtml(data, images = {})` y helpers exportados `semanaIndice(fecha, lunesInicio)`,
  `lunesDe(fecha)`, `renderGantt(cronograma)`. Láminas y slide de foto: 1 `cover` portada «Plan de trabajo —
  {proyecto}»; 2 `metodo` Método AT (6 fases: Diagnóstico ✓, Priorización ✓, **Estás aquí: Propuesta por fases —
  cerrada con tu firma del {fecha_firma_larga}**, Diseño y desarrollo, Implementación, Soporte y mejora continua);
  3 `gantt` carta Gantt por semanas (grilla CSS sin librerías; barras por bloque; revisión con otro estilo; rombos de
  hitos; leyenda AT/Tú/Ambos/Tu revisión; «fechas estimadas, desde que recibimos el anticipo y tus insumos»);
  4 una por fase `fase_1..fase_3` (actividades con responsable, días y fechas; entregable; «qué aprobamos juntos»);
  5 `necesitamos` qué necesitamos de ti + cláusula 4.2; 6 `reuniones` reuniones y soporte; 7 `portal` «Sigue tu
  proyecto» (contratos, avance y fechas, historial; todo queda documentado; enlace a `portal_url` si viene);
  8 `cierre` «Agenda tu llamada de seguimiento» con enlaces tocables `agenda.whatsapp_url` y `agenda.web_url` (solo si
  no está vacío). Mismo estilo, logo, modo presentación, `is-draft` y aviso `at-deck-lamina` que la propuesta.
- Pruebas: `test/template-plan.test.js`, `test/schema-plan.test.js`, `test/server-plan.test.js` y la de «propuesta
  igual byte a byte».

## Lista de tareas (quién redacta qué)

- Task 0 — Preparación de ramas y sitio de prueba `wp-local-plan` (la redacta el orquestador).
- Task 1 — Puras: días hábiles, feriados, inicio por defecto, fecha larga. (Grupo A)
- Task 2 — Puras: fases/enumeraciones, tabla de tiempos, validación del plan, aplicar tabla, marcar ediciones. (A)
- Task 3 — Puras: calcular fechas y cronograma (secuencia, paralelos, revisiones, hitos, semanas). (A)
- Task 4 — Puras: armar cuerpo de render, costo de fotos, transiciones. (A)
- Task 5 — Datos: tabla, migración, lecturas, crear idempotente, guardar, cambiar estado, feriados, duraciones,
  contratos sin plan, datos_render, contexto. (Grupo B)
- Task 6 — Disparador: hook en `contract-service.php`, listener, llamadas a n8n, carga del módulo (`cargar.php` +
  línea en `admin-proposals.php`). (B)
- Task 7 — REST para n8n (5 rutas). (B)
- Task 8 — Ajustes del plan (tabla de tiempos editable). (Grupo C)
- Task 9 — Pestaña «🗓️ Plan de trabajo» en la ficha del CRM: render, acciones admin-post, assets JS/CSS, edición en
  `crm-ai-completo.php`. (C)
- Task 10 — Agendar seguimiento: `at_pt_datos_agenda` + precarga en `admin-followup-meetings.php`. (C)
- Task 11 — Renderer: exportaciones, esquema y servidor para `document_type: 'plan'`. (Grupo D)
- Task 12 — Renderer: `template-plan.js` con las 8 láminas y la carta Gantt. (D)
- Task 13 — n8n: builders 1 Borrador y 2 Cambios, `correos_plan.py`, prompts, `probar_plan.py`. (Grupo E)
- Task 14 — n8n: builder 3 Render (reutilizar fotos, reintentos, vista, correos) + pruebas. (E)
- Task 15 — Prueba de punta a punta en local (WP local + renderer local + simulador de n8n que hace las mismas
  llamadas HTTP) y verificación visual (celular 390 px y escritorio). (orquestador)
- Task 16 — Despliegue con autorización de Luis (renderer zip → WP PROD por SSH con respaldo → flujos n8n) y prueba
  real con un contrato de prueba; documentación y memoria. (orquestador)

## DECISIONES DE COHERENCIA DEL ORQUESTADOR (29-sep 16:10) — mandan sobre lo anterior de este archivo

D1. `at_pt_marcar_ediciones(array $anterior, array $nuevo, string $marca = 'luis'): array`. Panel → `'luis'`;
    `POST /borrador` con `origen: 'cambios'` → `'ia'`. Así lo que cambia la IA sigue mostrándose «IA · revisar».
D2. Funciones puras adicionales (contrato de grupo-A.md, Task 2 › Interfaces): `at_pt_validar_entrada(mixed $plan, bool
    $borrador = false): array`, `at_pt_plan_de_json(...)`, `at_pt_respetar_dias_luis(array $anterior, array $nuevo): array`,
    `at_pt_luis_perdidas(array $anterior, array $nuevo): array`, `at_pt_clave_de(mixed $v, array $opciones): string`,
    `at_pt_repartir(...)`, `at_pt_semanas(...)` y los topes `AT_PT_MAX_*` (incluido `AT_PT_MAX_DIAS_PLAN` = 130). La
    firma exacta es la de grupo-A.md. **Orden de uso único** (obligatorio en REST y panel):
    - Borrador: `validar_entrada(…, true)` → `respetar_dias_luis($anterior ?: [])` → `aplicar_tabla` → `validar_plan`
      otra vez → `calcular_fechas`.
    - Cambios: `validar_entrada` → `respetar_dias_luis` → avisos + `luis_perdidas` → `marcar_ediciones(…, 'ia')` →
      `validar_plan` → `calcular_fechas`.
    - Panel: `validar_plan` → `marcar_ediciones(…, 'luis')` → `validar_plan` → `calcular_fechas`.
    Si la segunda validación falla (p. ej. tope de 130 días), el plan queda en `error` con nota legible.
D3. Pruebas puras: cuatro archivos `tests/plan/{fechas,validacion,cronograma,render}-test.php` (no existe `puras-test.php`).
D4. «Entrega estimada» = fin del último bloque de `implementacion` (día en que AT entrega, sin la revisión).
D5. `POST /plan/{id}/error` solo pasa a `error` desde `generando` o `cambios`; `POST /borrador` tardío (plan que ya no
    espera borrador, o en `error` con contenido) → 409.
D6. `at_pt_llamar_n8n(string $url, array $cuerpo, int $timeout = 15)`, `at_pt_iniciar_borrador(int $plan_id, int $timeout
    = 15)`; el listener de la firma usa 5 s. Cuerpo a los flujos 1 y 2: `{id, codigo}`; al 3: `{id, codigo, modo, aviso}`.
D7. `GET /contexto` agrega `crm_cliente_id`, `rubro` (de `crm_clientes.rubro`) y `contrato.garantia_meses` (placeholder
    `garantia_meses_servicio` del contrato; 3 si no está). El modelo recibe `rubro` (el Grupo E lo suma a lo que manda).
D8. Garantía: `at_pt_datos_render()` pasa `garantia_meses` del contrato y `at_pt_armar_render()` lo usa siempre que sea un
    entero >= 0, incluido el 0 (el contrato manda); en el panel el campo es de solo lectura («Viene del contrato».
D9. Nombre que se muestra (`company_name` / `empresa`): primero `company_name` de la propuesta si existe (nombre
    comercial); si no, `nombre_proyecto` o `razon_social_cliente` del contrato; si no, el nombre del cliente; nunca vacío.
D10. `GET /plan/{id}/render` responde `{ok, render, propuesta_uid, crm_cliente_id, estado}`. La ruta se llama con
    `&modo=` (la base ya trae `?rest_route=`). El flujo 3 **no renderiza un `draft`** si `estado` ∈ `aprobando`, `listo`,
    `enviado` (responde al webhook y termina sin tocar `/p/<codigo>/`), y `POST /vista` con `modo: 'draft'` **no guarda**
    `view_url`/`pdf_url` si el plan está en `aprobando`, `listo` o `enviado`. `POST /vista` responde `{ok, estado}`.
D11. Flujo 3: un 400 del renderer con `details` no se reintenta y `details` va a la nota; solo se reintentan 5xx, errores de
    red y fotos faltantes (máximo 3 renders).
D12. Prompts (Task 13): incluyen los topes que valida WordPress (13 bloques, 10 actividades por bloque, 58 actividades en
    total, 1 a 60 días por actividad, 130 días hábiles con 5 de revisión por bloque con entrega, 10 hitos, fases y
    responsables por su clave) y piden **no** mandar el bloque «Arranque» (lo pone WordPress).
D13. `N8N/plan-trabajo/plan_js.py` es el módulo compartido de JS de los tres builders (lo define el Grupo E).
D14. Sitio de prueba: la Task 0 deja `wp-admin` como copia, `auth_redirect()` reemplazado en el router con la cookie de
    prueba y las constantes `AT_N8N_PLAN_*` en el `wp-config.php` del sitio (Step 4b). Las Tasks 6 y 9 no repiten esos
    pasos: los referencian («hecho en la Task 0»).
D15. La ficha del CRM llama `at_pt_render_pestana($cliente)` solo si `is_array($cliente)`.
D16. Herramientas: `tests/plan/panel-js-wp-test.php` necesita Chrome o Edge (variable `CHROME` o rutas de Windows; sale con
    exit 2 si no hay). Los scripts con barras invertidas se crean con la herramienta Write, no con heredoc.
D17. Despliegue n8n: primero «3 Render», luego 1 y 2.

D18. Fotos en la Etapa 1: Soul 2 del renderer, sin revisión de texto con GPT-4o (la de las propuestas desde el 27-sep).
    Es una decisión que Luis confirma antes del primer «Aprobar» (Task 16, Step 6); sumarla al flujo 3 es tarea aparte.
D19. Diferencias con el spec aceptadas: «Ajustes del plan» cuelga del menú CRM (el plan vive en la ficha del cliente,
    decisión 6); las notas del plan viven en su propia columna `nota` y no en el Seguimiento de la propuesta (el módulo es
    independiente, decisión 10); «Destrabar» actúa sobre generando/cambios/aprobando y desde error se ofrece
    «Reintentar»; se reutiliza `X-AT-Secret` en vez de `X-AT-Plan-Key` (regla 7).
D20. Riesgos conocidos, aceptados en la Etapa 1: (a) tres criterios para «hay propuesta» (panel por propuesta_id,
    flujo 1 por el objeto del contexto, flujo 3 por unique_link_id): si la propuesta se borró, el costo mostrado puede
    no calzar; (b) una vista previa ya en curso al aprobar puede terminar después de la final y pisar /p/<codigo>/
    (/vista la descarta; basta «Aprobar» de nuevo); (c) notas que se reemplazan (aviso de vista previa sobre avisos del
    borrador; prefijo de /error de n8n sobre el de WordPress), sin perder los errores.
D21. La prueba de legibilidad del renderer usa 27 barras; el máximo teórico es 28 si el panel marca el Arranque con
    entrega. Aceptado (una barra más no cambia la escala).
