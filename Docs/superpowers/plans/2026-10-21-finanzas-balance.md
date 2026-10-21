# Finanzas AT: pagos reales, medidor de créditos y Balance — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Registrar cada pago real de AutomatizaTech (con su equivalente en pesos), llevar el saldo de las cuentas prepagadas y dejar a los agentes registrar gastos por una API segura, más el agente Balance en Claude y Codex.

**Architecture:** Módulo nuevo `inc/finanzas/` (funciones puras + datos + tipo de cambio + API REST + panel) cargado desde `inc/admin-at-finanzas.php`; dos tablas propias (`at_fin_cuentas`, `at_fin_movimientos`); el catálogo existente (`omnichannel_at_expenses`) no cambia. Script CLI y encargo de Balance fuera del repo, en `Shared-Tools`.

**Tech Stack:** PHP 8.3 + WordPress (wpdb, REST API, dbDelta), mindicador.cl para el dólar observado, Python 3 (script de los agentes). Pruebas: scripts PHP con `ok()`/`fin()` como `tests/retomar/`.

**Spec:** `Docs/superpowers/specs/2026-10-21-finanzas-balance-design.md`

## Global Constraints

- `functions.php` NO se toca; todo se carga con `require_once` desde `wp-content/themes/automatiza-tech/inc/admin-at-finanzas.php`.
- El catálogo (`OmniATFinanceController`, tabla `{prefix}omnichannel_at_expenses`) y la pestaña actual no cambian de comportamiento.
- Tablas `{prefix}at_fin_cuentas` y `{prefix}at_fin_movimientos` con las columnas del spec; versión en la opción `at_fin_schema` = `1`.
- Tipos de movimiento: `pago` · `recarga` · `consumo` · `ajuste`. Monedas: `CLP` · `USD`. `tc_estado`: `ok` · `pendiente` · `manual`. `origen`: `api` · `panel`.
- Gasto del mes = suma de `monto_clp` de `pago` + `recarga`; `consumo` y `ajuste` NO suman.
- Saldo de cuenta = recargas + ajustes − consumos (en la moneda de la cuenta).
- Tipo de cambio: `https://mindicador.cl/api/dolar/DD-MM-AAAA`, último valor ≤ fecha hasta 7 días atrás; CLP = 1.
- REST bajo `automatiza-tech/v1`, autenticado con la cabecera `X-AT-Secret` comparada con `AT_REST_SECRET` (`hash_equals`).
- Idempotencia por `referencia`: mismos datos → 200 con el mismo id; datos distintos → 409.
- Ningún secreto en código, logs, commits ni salida; el repo es público: sin montos reales ni nombres de clientes en commits o docs.
- Textos de interfaz en español de Chile.

## Review Focus

1. Un pago con fecha de sábado, domingo o feriado (sin dólar ese día) → usa el último valor hábil anterior, no queda pendiente ni falla.
2. El mismo pago registrado dos veces sin referencia (dos agentes o un reintento) → queda uno solo, porque la referencia se deriva de los datos.
3. Un monto escrito «1,26» (coma decimal chilena) o con espacios → se interpreta como 1.26; «abc» o 0 → 422 con mensaje claro.
4. Una cuenta con más consumo que recargas (consumo registrado antes que la recarga) → saldo negativo mostrado en rojo, sin error; uso de la última recarga `null` si no hay recarga.
5. Un mes sin movimientos o meses anteriores vacíos en la comparación → ceros, sin división por cero ni avisos de PHP.

---

### Task 1: Funciones puras

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/finanzas/puras.php`
- Test: `tests/finanzas/puras-test.php` (mismo encabezado que `tests/retomar/puras-test.php`: `ok()`, contador, `exit`)

**Interfaces:**
- Produces:
  - `at_fin_tipos(): array` → `['pago','recarga','consumo','ajuste']`
  - `at_fin_monto(mixed $v): ?float` — acepta número o texto con coma o punto decimal y espacios; `null` si no es número.
  - `at_fin_validar(array $d, string $hoy): array` → `['ok'=>bool, 'errores'=>string[], 'datos'=>array]`. Normaliza `tipo, fecha (Y-m-d), cuenta (slug|''), proveedor, categoria, descripcion, proyecto, monto (float), moneda, tipo_cambio (?float), gasto_id (?int), referencia, registrado_por, notas`. Reglas del spec: tipo válido; monto ≠ 0 y > 0 salvo `ajuste`; moneda CLP/USD; fecha válida y ≤ `$hoy`; cuenta obligatoria en recarga/consumo/ajuste; recorta proveedor 120, categoria 60, descripcion 255, proyecto 80, referencia 120, registrado_por 60.
  - `at_fin_referencia(array $datos): string` → `'auto-' . sha1(...)` de tipo|fecha|cuenta|proveedor|monto (4 decimales)|moneda|descripcion.
  - `at_fin_tc_de_serie(string $fecha, array $serie): ?float` — `$serie` = `['Y-m-d' => float]`; último valor con fecha ≤ `$fecha` y ≥ `$fecha − 7 días`.
  - `at_fin_resumen_mes(array $movs): array` → `['total_clp'=>int, 'por_categoria'=>[cat=>int], 'por_proveedor'=>[prov=>int], 'pendientes'=>int]` (solo pago+recarga suman; pendientes = movimientos con `tc_estado`='pendiente').
  - `at_fin_saldo(array $movs): array` → `['recargado'=>float,'consumido'=>float,'ajustes'=>float,'saldo'=>float,'ultima_recarga'=>?array{fecha,monto},'usado_desde_recarga'=>float,'pct_usado'=>?float]`.
  - `at_fin_mismos_datos(array $a, array $b): bool` — compara tipo, fecha, cuenta, proveedor, monto, moneda, descripcion.

- [ ] **Step 1: Write the failing tests** — casos mínimos:
  - `at_fin_monto('1,26') === 1.26`, `at_fin_monto(' 50 ') === 50.0`, `at_fin_monto('abc') === null`, `at_fin_monto(0)` → `0.0` (lo rechaza `at_fin_validar`).
  - `at_fin_validar(['tipo'=>'pago','fecha'=>'2026-10-20','proveedor'=>'X','monto'=>'10','moneda'=>'USD'], '2026-10-21')['ok'] === true`; fecha `2026-10-22` → error; tipo `regalo` → error; `consumo` sin cuenta → error; `ajuste` con monto `-5` → ok; `pago` con `-5` → error; moneda `EUR` → error.
  - `at_fin_referencia` igual para los mismos datos y distinta si cambia el monto.
  - `at_fin_tc_de_serie('2026-10-18', ['2026-10-16'=>980.5,'2026-10-17'=>981.0]) === 981.0` (sábado); `'2026-10-30'` con la misma serie → `null` (más de 7 días).
  - `at_fin_resumen_mes`: pago 1000 + recarga 2000 + consumo 500 + ajuste 300 → `total_clp === 3000`; por_categoria suma igual; `pendientes` cuenta un `tc_estado=pendiente`; lista vacía → total 0 sin avisos.
  - `at_fin_saldo`: recarga 50 + consumo 12 + ajuste -3 → saldo 35, pct_usado 24.0 (12/50·100); solo consumos → saldo negativo y `ultima_recarga === null`, `pct_usado === null`.
- [ ] **Step 2:** `php tests/finanzas/puras-test.php` → FALLAS (funciones no existen).
- [ ] **Step 3:** Implementar las funciones de Interfaces en `puras.php` (sin WordPress; sin `function_exists` guards innecesarios).
- [ ] **Step 4:** `php tests/finanzas/puras-test.php` → `N pruebas, 0 fallas`.
- [ ] **Step 5: Commit** `feat(finanzas): funciones puras de movimientos, saldo y tipo de cambio`.

### Task 2: Esquema, datos y tipo de cambio

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/finanzas/datos.php`, `inc/finanzas/cambio.php`, `inc/finanzas/cargar.php`
- Modify: `wp-content/themes/automatiza-tech/inc/admin-at-finanzas.php` (una línea al inicio: `require_once __DIR__ . '/finanzas/cargar.php';`)
- Test: `tests/finanzas/datos-wp-test.php` (usa `require __DIR__ . '/../retomar/wp-bootstrap.php';`, prefijo de datos `[PRUEBA]`, limpieza en `register_shutdown_function`)

**Interfaces:**
- Consumes: Task 1.
- Produces:
  - `at_fin_migrar(): void` — `dbDelta` de las dos tablas del spec (referencia UNIQUE, slug UNIQUE, KEY fecha, KEY cuenta_id), opción `at_fin_schema`=`1`; idempotente.
  - `at_fin_cuenta_guardar(array $d): int|WP_Error` — por `slug` (crea o actualiza nombre, proveedor, moneda, activa, notas).
  - `at_fin_cuenta(string $slug): ?array`, `at_fin_cuentas(): array` (cada una con `at_fin_saldo()` de sus movimientos bajo la clave `saldo`).
  - `at_fin_registrar(array $entrada, string $origen): array|WP_Error` → `['id'=>int,'estado'=>'creado'|'existente']`; valida con `at_fin_validar`, deriva `referencia` si falta, resuelve `cuenta` → `cuenta_id` (422 si no existe), calcula tipo de cambio (`manual` si viene, si no `at_fin_tipo_cambio`), `monto_clp = round(monto × tc)`; referencia repetida: mismos datos → existente, distintos → `WP_Error('duplicado', ..., ['status'=>409])`; errores de validación → `['status'=>422]`.
  - `at_fin_movimientos_mes(string $mes 'Y-m'): array`, `at_fin_movimientos_cuenta(int $cuenta_id, int $limite = 50): array`.
  - `at_fin_completar_pendientes(int $max = 20): int` — reintenta el tipo de cambio de los `pendiente`.
  - `at_fin_tipo_cambio(string $fecha): ?float` (en `cambio.php`) — CLP no aplica; cache en la opción `at_fin_tc_cache` (`['Y-m-d'=>float]`, máx. 120 entradas); consulta `https://mindicador.cl/api/dolar/DD-MM-AAAA` y, si viene vacía, los días anteriores hasta 7; usa `at_fin_tc_de_serie`; `wp_remote_get` timeout 10 s; en error devuelve `null`.
  - `cargar.php` hace `require_once` de puras, datos, cambio, api, panel y llama `at_fin_migrar()` en `admin_init` y en `rest_api_init` (barato: compara la opción).

- [ ] **Step 1: Failing tests** — tablas creadas (`SHOW TABLES`), segunda llamada a `at_fin_migrar` no falla; `at_fin_cuenta_guardar(['slug'=>'prueba-hf','nombre'=>'[PRUEBA] HF','proveedor'=>'Higgsfield','moneda'=>'USD'])` devuelve id y repetirla devuelve el mismo; `at_fin_registrar` de un pago USD con `pre_http_request` simulado (mindicador devuelve 950.0) guarda `monto_clp` = round(monto×950) y `tc_estado='ok'`; repetir el mismo registro sin referencia → `estado='existente'` y un solo registro; misma referencia con otro monto → WP_Error status 409; consumo con cuenta inexistente → 422; con la API simulada en error → `tc_estado='pendiente'`, `monto_clp` NULL, y `at_fin_completar_pendientes()` con la API ya respondiendo lo completa; `tipo_cambio` manual → `tc_estado='manual'` sin llamar HTTP; pago CLP → `tipo_cambio=1`, sin HTTP.
- [ ] **Step 2:** `AT_WP_LOAD=<wp-load local> php tests/finanzas/datos-wp-test.php` → FALLAS.
- [ ] **Step 3:** Implementar `datos.php`, `cambio.php`, `cargar.php` y la línea en `admin-at-finanzas.php`.
- [ ] **Step 4:** La prueba → `TODO OK`; `php -l` de los archivos.
- [ ] **Step 5: Commit** `feat(finanzas): tablas, registro idempotente y tipo de cambio del día`.

### Task 3: API REST

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/finanzas/api.php`
- Test: `tests/finanzas/api-wp-test.php`

**Interfaces:**
- Consumes: Task 2.
- Produces rutas (namespace `automatiza-tech/v1`, `permission_callback` = `at_fin_auth`):
  - `at_fin_auth(WP_REST_Request $r): bool` — misma regla que `at_rt_auth` (no depende de Retomar).
  - `POST /finanzas/movimiento` → 201 `{id, estado:'creado'}` · 200 `{id, estado:'existente'}` · 409 · 422 (con `errores`).
  - `GET /finanzas/resumen?mes=AAAA-MM` → `{mes, total_clp, por_categoria, por_proveedor, pendientes, comparacion:[{mes,total_clp}×6 anteriores]}`; `mes` inválido → 422; por defecto, el mes actual de Chile.
  - `GET /finanzas/cuentas` → lista con saldo.
  - `POST /finanzas/cuenta` → crea/actualiza por slug.

- [ ] **Step 1: Failing tests** (con `rest_do_request`): sin cabecera → 401; con cabecera mala → 401; POST válido → 201 y luego el mismo → 200 `existente`; misma referencia y otro monto → 409; monto `abc` → 422 con `errores` no vacío; resumen del mes de prueba suma solo pago+recarga y trae 6 meses en `comparacion` (en cero si no hay); cuentas devuelve la cuenta de prueba con su saldo.
- [ ] **Step 2:** correr → FALLAS.
- [ ] **Step 3:** Implementar `api.php` (definir `AT_REST_SECRET` de prueba en el test si no existe).
- [ ] **Step 4:** → `TODO OK`.
- [ ] **Step 5: Commit** `feat(finanzas): API segura para registrar y consultar gastos`.

### Task 4: Panel en wp-admin

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/finanzas/panel.php`
- Modify: `wp-content/themes/automatiza-tech/inc/admin-at-finanzas.php` — barra de pestañas bajo el `<h1>` (`?tab=suscripciones|pagos|cuentas`, por defecto `suscripciones`); si `tab` es `pagos` o `cuentas`, dibuja la de `panel.php` y retorna antes del contenido actual. Además, en la acción `renew_paid` existente, después de renovar con éxito registra un `pago` con `gasto_id` del catálogo (proveedor, categoría, monto, moneda, fecha de hoy, `registrado_por`='panel', referencia `renov-<id>-<Y-m>`).
- Test: `tests/finanzas/panel-wp-test.php`

**Interfaces:**
- Consumes: Tasks 1–2.
- Produces: `at_fin_panel_pagos(string $mes): void`, `at_fin_panel_cuentas(): void`, `at_fin_panel_accion(array $post): array{ok:bool,mensaje:string}` (acciones `registrar` y `cuadrar`, con nonce `at_fin_panel`). «Cuadrar»: recibe `cuenta` y `saldo_real`; registra un `ajuste` = saldo_real − saldo estimado con nota «saldo real del panel: X» (no registra nada si la diferencia es 0).

- [ ] **Step 1: Failing tests:** `at_fin_panel_accion(['accion'=>'cuadrar','cuenta'=>'prueba-hf','saldo_real'=>'30'])` con saldo estimado 35 → registra ajuste −5 y el saldo queda 30; diferencia 0 → ok sin movimiento; `registrar` desde el panel guarda `origen='panel'`; la salida de `at_fin_panel_pagos('2026-10')` contiene el total formateado en pesos y «sin datos» cuando el mes está vacío; la de `at_fin_panel_cuentas()` contiene el nombre de la cuenta y su saldo, y la clase de saldo negativo cuando corresponde. Regresión: con `tab` ausente, la página actual sigue mostrando «Gasto mensual».
- [ ] **Step 2:** correr → FALLAS.
- [ ] **Step 3:** Implementar (escapar todo con `esc_html`/`esc_attr`; formulario con `wp_nonce_field`; montos en pesos con separador de miles chileno).
- [ ] **Step 4:** → `TODO OK`; revisar la página en el sitio local (las tres pestañas, móvil y escritorio) con el navegador.
- [ ] **Step 5: Commit** `feat(finanzas): pestañas de pagos por mes y cuentas con medidor`.

### Task 5: Script de los agentes y encargo de Balance (fuera del repo)

**Files:**
- Create: `C:/Users/luis_/Documents/Codex/Shared-Tools/finanzas/registrar.py`
- Create: `C:/Users/luis_/Documents/Codex/Shared-Tools/finanzas/README.md`
- Create: `C:/Users/luis_/Documents/Codex/Shared-Tools/Encargo-Balance-Finanzas-AT.md`

**Interfaces:**
- Consumes: Task 3 (rutas y respuestas).
- Produces: CLI `python registrar.py {pago|recarga|consumo|ajuste} --monto M --moneda USD|CLP --proveedor P [--cuenta SLUG] [--categoria C] [--descripcion D] [--proyecto X] [--fecha AAAA-MM-DD] [--referencia R] [--tc N] [--por AGENTE]`, `python registrar.py resumen [--mes AAAA-MM]`, `python registrar.py cuentas`, `python registrar.py cuenta --slug S --nombre N --proveedor P --moneda USD`. Base `https://automatizatech.cl/?rest_route=/automatiza-tech/v1`. Lee `AT_REST_SECRET` de la variable de entorno; si no está, del archivo de claves por su **etiqueta exacta** (cargada en memoria, sin imprimir); `--seco` arma y muestra el pedido sin la clave y sin enviarlo.

- [ ] **Step 1:** `python registrar.py pago --monto 1 --moneda USD --proveedor Prueba --seco` muestra el JSON del pedido sin cabecera de clave.
- [ ] **Step 2:** Implementar con `urllib` (sin dependencias); salida en español; códigos 200/201/409/422 explicados.
- [ ] **Step 3:** README con ejemplos y la regla de referencias; encargo de Balance: rol, contexto (catálogo actual, cuentas, proveedores conocidos: Higgsfield, Google Cloud/Workspace, Anthropic/Claude, OpenAI/Codex, Hostinger, Meta, ElevenLabs), rutina semanal de preguntas, cómo cuadrar, límites (no paga ni recarga, no muestra claves, montos reales nunca al repo), homólogo Codex/Claude.
- [ ] **Step 4:** Sin commit en el repo (archivos fuera); anotar rutas en el ledger.

### Task 6: Regla para todos los agentes, guía y cierre

**Files:**
- Modify: `CLAUDE.md`, `AGENTS.md` — viñeta nueva en «Orquestacion multiagente AT»: avisar a Balance cada gasto o recarga (qué, cuánto, cuenta, proyecto, referencia) o registrarlo con `Shared-Tools/finanzas/registrar.py`.
- Create: `Docs/METODO_AT/FINANZAS-BALANCE.md` — guía: qué registra cada tipo, cómo se calcula el gasto del mes y el saldo, cómo cuadrar, API y script, despliegue (lista de archivos), rollback.
- Test: correr todas las suites de `tests/finanzas/` y las de `tests/retomar/` (regresión).

- [ ] **Step 1:** Escribir la regla y la guía (sin montos reales).
- [ ] **Step 2:** `php tests/finanzas/puras-test.php` y cada `*-wp-test.php` de `tests/finanzas` y `tests/retomar` → todo OK.
- [ ] **Step 3: Commit** `docs(finanzas): regla de Balance para los agentes y guía del módulo`; push y PR (sin merge ni despliegue sin el OK de Luis).

## Lista de subida a PROD (para el OK de Luis)

`inc/finanzas/{puras,datos,cambio,api,panel,cargar}.php` primero y `inc/admin-at-finanzas.php` al final (es el que los carga). Respaldo del archivo modificado, `php -l`, md5, verificación de las tres pestañas con sesión y de la API (401 sin clave). Después: crear las cuentas iniciales y cargar los pagos anteriores con Balance.
