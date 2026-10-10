# Tablero AT v9 — personas, permisos y tickets de agentes · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el tablero AT muestre en qué está cada persona y cada agente, con acceso por usuario de WordPress (rol «Colaborador AT») y los tickets de `Docs/ORCHESTRATION/` sincronizados como tarjetas de solo lectura.

**Architecture:** Un `mu-plugin` (`at-tablero`) concentra la lógica en archivos chicos y probables: permisos puros, migración, datos y rutas REST `at-tablero/v1`. La página `tablero/index.php` exige la sesión de WordPress e inyecta un nonce en la interfaz existente (`index.html`), que pasa a hablar con la REST mediante un cliente nuevo (`api.js`) y una lógica pura de vistas (`logica.js`). Un script Python lee los tickets con git y publica el lote con una contraseña de aplicación.

**Tech Stack:** PHP 8.0+ sobre WordPress (REST API, roles y capacidades, `$wpdb`), JavaScript del navegador sin build (Tailwind CDN existente), Node `node --test` para la lógica pura, Python 3.13 + PyYAML (`unittest`) para el sync, PHP CLI de WAMP `C:/wamp64/bin/php/php8.4.15/php.exe`.

**Spec:** `Docs/superpowers/specs/2026-10-10-tablero-personas-design.md`

## Global Constraints

- Ticket `AT-TAB-004`; rama `claude/tablero-personas`; worktree `C:/wamp64/www/automatiza-tech/.worktrees/tablero-personas`.
- **No tocar `wp-content/themes/automatiza-tech/functions.php`** (PROD lo adelanta). El código nuevo va en `wp-content/mu-plugins/`.
- Rol nuevo: `colaborador_at`, nombre visible «Colaborador AT». Capacidades: `at_board_ver`, `at_board_editar_propias`, `at_board_admin`, `at_board_sync`, `at_board_ver_proyecto` (esta última definida y sin uso: externos apagados).
- Responsables: `wp:<user_id>` o `agente:claude`, `agente:codex`, `agente:opencode`; vacío = sin asignar.
- `at_id` de tarjetas de ticket: `T-<ticket_id>`; de decisiones: `T-<ticket_id>-D<n>`. El prefijo `T-` está reservado para `origen = ticket`.
- Estados del tablero (sin cambios): `backlog`, `todo`, `progress`, `review`, `wait`, `blocked`, `done`.
- Textos visibles en español de Chile. Sin secretos en código, pruebas, logs ni commits; la contraseña de aplicación se pasa por variable de entorno.
- El repo es **público**: sin nombres ni identificadores de clientes en código, pruebas o documentos.
- Nada se despliega a PROD sin el ok explícito de Luis (Task 9).
- Temporales de desarrollo en el scratch gestionado de Aseo (`C:\Users\luis_\AppData\Local\AutomatizaTech\Aseo\work\cb2cafab9754288d9f149ffa`), registrados con `register`.

## Review Focus

1. **Nonce vencido** (pestaña abierta más de 24 h): la REST responde 403 `rest_cookie_invalid_nonce`, y la interfaz debe pedir «recargar», no mostrar «sin permiso». → prueba en Task 6 (`mensajeDeError`).
2. **Colaborador que reasigna su propia tarjeta a otro** enviando `asignado` distinto por API → 403 aunque la tarjeta sea suya. → prueba en Task 3.
3. **Sync parcial** (GitHub no responde al listar PR): no debe archivar tarjetas de tickets que viven en ramas. → prueba en Task 7 (`parcial=True`) y en Task 3 (`at_tab_sync` con `parcial`).
4. **Tarjeta manual con `at_id` que empieza con `T-`** → 409, para que el sync nunca pise trabajo humano. → prueba en Task 3.
5. **Mismo ticket en `main` y en una rama de PR con contenidos distintos** → gana el commit más reciente, y un ticket que ya no está en `main` pero sigue en un PR abierto no se archiva. → prueba en Task 7.

---

## File Structure

| Archivo | Responsabilidad |
|---|---|
| `wp-content/mu-plugins/at-tablero.php` | Cargador: requiere los cuatro archivos del módulo y registra las rutas REST |
| `wp-content/mu-plugins/at-tablero/permisos.php` | Funciones **puras**: tipo de actor y matriz de permisos (sin WordPress dentro de `at_tab_puede`) |
| `wp-content/mu-plugins/at-tablero/migracion.php` | Columnas v9, rol, capacidades y mapeo de `asignado_a` (idempotente) |
| `wp-content/mu-plugins/at-tablero/datos.php` | Personas, listado, guardado con permisos y sync por lote |
| `wp-content/mu-plugins/at-tablero/rest.php` | Rutas `at-tablero/v1`: tablero, tarjeta, sync y adjuntos |
| `tools/tablero-migrar-v9.php` | CLI de migración (solo `php` por consola) |
| `tablero/index.php` | Puerta: login de WordPress, capacidad, inyección de configuración |
| `tablero/.htaccess` | `DirectoryIndex index.php` y bloqueo de acceso directo a `index.html` |
| `tablero/api.js` | Cliente REST con `X-WP-Nonce` y traducción de errores |
| `tablero/logica.js` | Lógica pura de interfaz: quién puede editar, agrupación por persona, filtro ⚖️ |
| `tablero/index.html` | Interfaz existente, editada para usar `api.js`/`logica.js` y la vista «Por persona» |
| `api-tablero.php` | Pasa a aviso 410 |
| `tools/tablero_sync.py` | Lee los tickets con git y publica el lote |
| `tests/tablero/*` | Pruebas: permisos (PHP puro), migración, datos y REST (WP), lógica (node), sync (unittest) |
| `Docs/ORCHESTRATION/*`, `tablero/CONTEXTO_TABLERO.md` | Regla de sync, campo `decisiones_luis`, ticket AT-TAB-004, contexto v9 |

---

### Task 0: Entorno de prueba

**Files:**
- Create: `tests/tablero/wp-bootstrap.php`
- Create: `tests/tablero/LEEME.md`

**Interfaces:**
- Produces: `ok(bool $cond, string $msg)`, `fin()`, `exigir(string ...$funciones)` y la carga del módulo `at-tablero` en el WordPress de prueba (`AT_WP_LOAD`).

- [ ] **Step 1: Verificar el sitio de prueba existente**

El sitio `wp-local-plan` del plan de trabajo vive en
`C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/wp-local-plan/`.

Run:
```bash
PHP=/c/wamp64/bin/php/php8.4.15/php.exe
WPL=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/wp-local-plan/wp-load.php
"$PHP" -r "define('WP_USE_THEMES', false); require '$WPL'; echo get_bloginfo('version'), PHP_EOL;"
```
Expected: imprime la versión de WordPress. Si falla (el sitio fue borrado o no levanta MySQL), reconstruirlo según
`Docs/superpowers/plans/2026-09-29-plan-de-trabajo-etapa-1/plan.md` «Task 0, Step 3» y registrar la ruta nueva.
No seguir sin un `AT_WP_LOAD` que funcione.

- [ ] **Step 2: Crear el arnés `tests/tablero/wp-bootstrap.php`**

```php
<?php
// Carga el WordPress local de prueba y el módulo at-tablero desde ESTE worktree (no desde el sitio).
// Uso: AT_WP_LOAD=<ruta a wp-load.php> php tests/tablero/<prueba>-wp-test.php
$at_tab_wp_load = getenv('AT_WP_LOAD');
if (!$at_tab_wp_load || !is_file($at_tab_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8093';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
define('WP_USE_THEMES', false);
require $at_tab_wp_load;
require_once dirname(__DIR__, 2) . '/wp-content/mu-plugins/at-tablero.php';
// rest_api_init ya pasó o no pasará en CLI: registrar las rutas a mano para rest_do_request().
if (function_exists('at_tab_registrar_rutas') && !did_action('at_tab_rutas_registradas')) {
	at_tab_registrar_rutas();
}
$GLOBALS['fallas'] = 0;
function ok($cond, $msg) { if ($cond) { echo "ok   $msg\n"; } else { $GLOBALS['fallas']++; echo "FALLA $msg\n"; } }
function fin() { echo $GLOBALS['fallas'] ? "\n{$GLOBALS['fallas']} FALLAS\n" : "\nTODO OK\n"; exit($GLOBALS['fallas'] ? 1 : 0); }
function exigir(string ...$funciones): void {
	foreach ($funciones as $f) {
		if (!function_exists($f)) { echo "FALLA falta la función {$f}()\n\n1 FALLAS\n"; exit(1); }
	}
}
```

- [ ] **Step 3: Crear `tests/tablero/LEEME.md`**

```markdown
# Pruebas del tablero v9 (AT-TAB-004)

- Permisos (PHP puro): `php tests/tablero/permisos-test.php`
- Con WordPress de prueba: `AT_WP_LOAD=<ruta>/wp-load.php php tests/tablero/<nombre>-wp-test.php`
  (migracion, datos, rest). Crean y borran sus propios usuarios `at-tab-prueba-*`.
- Lógica de interfaz: `node --test tests/tablero/logica.test.mjs`
- Sync: `python -m unittest tests/tablero/test_tablero_sync.py -v`
```

- [ ] **Step 4: Commit**

```bash
git add tests/tablero/wp-bootstrap.php tests/tablero/LEEME.md
git commit -m "test(tablero): arnés de pruebas v9 (AT-TAB-004)"
```

---

### Task 1: Permisos puros

**Files:**
- Create: `wp-content/mu-plugins/at-tablero/permisos.php`
- Test: `tests/tablero/permisos-test.php`

**Interfaces:**
- Produces:
  - `at_tab_actor(string $tipo, string $key = ''): array` → `['tipo' => 'admin'|'colaborador'|'servicio'|'externo'|'ninguno', 'key' => 'wp:<id>'|'']`
  - `at_tab_puede(array $actor, string $accion, array $tarjeta = []): bool`. `$accion` ∈ `ver`, `crear`, `editar`, `reasignar`, `sync`. `$tarjeta` usa las claves `tipo` (`cli`|`int`), `origen` (`manual`|`ticket`) y `asignado`.
  - `AT_TAB_AGENTES` = `['agente:claude' => 'Claude', 'agente:codex' => 'Codex', 'agente:opencode' => 'OpenCode']`

- [ ] **Step 1: Write the failing test**

```php
<?php
// php tests/tablero/permisos-test.php  — matriz de la spec §3, sin WordPress.
require dirname(__DIR__, 2) . '/wp-content/mu-plugins/at-tablero/permisos.php';
$f = 0;
function ok($c, $m) { global $f; echo ($c ? 'ok   ' : 'FALLA ') . $m . "\n"; if (!$c) $f++; }

$admin = at_tab_actor('admin', 'wp:1');
$ana   = at_tab_actor('colaborador', 'wp:7');
$sin   = at_tab_actor('ninguno');
$ext   = at_tab_actor('externo', 'wp:9');
$srv   = at_tab_actor('servicio', 'wp:3');
$propia  = ['tipo' => 'int', 'origen' => 'manual', 'asignado' => 'wp:7'];
$ajena   = ['tipo' => 'int', 'origen' => 'manual', 'asignado' => 'wp:1'];
$ticket  = ['tipo' => 'int', 'origen' => 'ticket', 'asignado' => 'agente:claude'];
$cliente = ['tipo' => 'cli', 'origen' => 'manual', 'asignado' => 'wp:7'];

ok(at_tab_puede($admin, 'ver'), 'admin ve');
ok(at_tab_puede($ana, 'ver'), 'colaborador ve');
ok(!at_tab_puede($sin, 'ver'), 'sin rol no ve');
ok(!at_tab_puede($ext, 'ver'), 'externo apagado no ve');
ok(!at_tab_puede($srv, 'ver'), 'servicio no usa la interfaz');

ok(at_tab_puede($admin, 'crear', $ajena), 'admin crea para otro');
ok(at_tab_puede($ana, 'crear', $propia), 'colaborador crea interna para sí');
ok(!at_tab_puede($ana, 'crear', $ajena), 'colaborador no crea para otro');
ok(!at_tab_puede($ana, 'crear', $cliente), 'colaborador no crea tarjetas de cliente');
ok(!at_tab_puede($admin, 'crear', $ticket), 'nadie crea tarjetas de ticket a mano');

ok(at_tab_puede($admin, 'editar', $ajena), 'admin edita tarjeta de persona');
ok(at_tab_puede($ana, 'editar', $propia), 'colaborador edita la suya');
ok(!at_tab_puede($ana, 'editar', $ajena), 'colaborador no edita la ajena');
ok(!at_tab_puede($admin, 'editar', $ticket), 'admin no edita tarjeta de ticket');
ok(!at_tab_puede($srv, 'editar', $propia), 'servicio no edita tarjetas de persona');

ok(at_tab_puede($admin, 'reasignar', $ajena), 'admin reasigna');
ok(!at_tab_puede($ana, 'reasignar', $propia), 'colaborador no reasigna ni la suya');
ok(!at_tab_puede($admin, 'reasignar', $ticket), 'nadie reasigna un ticket en el tablero');

ok(at_tab_puede($srv, 'sync'), 'servicio sincroniza');
ok(!at_tab_puede($admin, 'sync'), 'admin no sincroniza por API');
ok(!at_tab_puede($ana, 'accion-desconocida', $propia), 'acción desconocida = no');
ok(at_tab_actor('raro', 'wp:1')['tipo'] === 'ninguno', 'tipo desconocido cae en ninguno');

echo $f ? "\n$f FALLAS\n" : "\nTODO OK\n"; exit($f ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `/c/wamp64/bin/php/php8.4.15/php.exe tests/tablero/permisos-test.php`
Expected: error fatal por archivo inexistente (`Failed opening required ... permisos.php`).

- [ ] **Step 3: Write minimal implementation**

```php
<?php
/**
 * Tablero AT v9 — permisos puros (spec §3). Sin llamadas a WordPress: se prueban con php a secas.
 */
if (!defined('AT_TAB_AGENTES')) {
	define('AT_TAB_AGENTES', ['agente:claude' => 'Claude', 'agente:codex' => 'Codex', 'agente:opencode' => 'OpenCode']);
}

function at_tab_actor(string $tipo, string $key = ''): array {
	$validos = ['admin', 'colaborador', 'servicio', 'externo', 'ninguno'];
	return ['tipo' => in_array($tipo, $validos, true) ? $tipo : 'ninguno', 'key' => $key];
}

function at_tab_puede(array $actor, string $accion, array $tarjeta = []): bool {
	$tipo = $actor['tipo'] ?? 'ninguno';
	$key = (string) ($actor['key'] ?? '');
	$origen = $tarjeta['origen'] ?? 'manual';
	$asignado = (string) ($tarjeta['asignado'] ?? '');
	$clase = $tarjeta['tipo'] ?? 'int';
	switch ($accion) {
		case 'ver':
			return $tipo === 'admin' || $tipo === 'colaborador';
		case 'sync':
			return $tipo === 'servicio';
		case 'crear':
			if ($origen !== 'manual') return false;
			if ($tipo === 'admin') return true;
			return $tipo === 'colaborador' && $clase === 'int' && $key !== '' && $asignado === $key;
		case 'editar':
			if ($origen !== 'manual') return false;
			if ($tipo === 'admin') return true;
			return $tipo === 'colaborador' && $key !== '' && $asignado === $key;
		case 'reasignar':
			return $origen === 'manual' && $tipo === 'admin';
	}
	return false;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `/c/wamp64/bin/php/php8.4.15/php.exe tests/tablero/permisos-test.php`
Expected: `TODO OK` (22 líneas `ok`).

- [ ] **Step 5: Commit**

```bash
git add wp-content/mu-plugins/at-tablero/permisos.php tests/tablero/permisos-test.php
git commit -m "feat(tablero): matriz de permisos pura v9 (AT-TAB-004)"
```

---

### Task 2: Migración v9 (columnas, rol, capacidades y responsables)

**Files:**
- Create: `wp-content/mu-plugins/at-tablero/migracion.php`
- Create: `tools/tablero-migrar-v9.php`
- Test: `tests/tablero/migracion-wp-test.php`

**Interfaces:**
- Consumes: nada de tareas previas.
- Produces:
  - `at_tab_tablas(): array` → `['cli' => '{prefix}omnichannel_at_board', 'int' => '{prefix}omnichannel_at_internas', 'adj' => '{prefix}omnichannel_at_attachments']`
  - `at_tab_migrar_v9(int $admin_id, string $servicio_login = ''): array` → `['columnas' => int, 'mapeadas' => int, 'rol' => bool, 'servicio' => bool]`, o `WP_Error` si `$admin_id` no tiene `manage_options` o faltan las tablas v8.
  - Opción `at_board_schema_version` = `'9.0.0'`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// AT_WP_LOAD=... php tests/tablero/migracion-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
exigir('at_tab_tablas', 'at_tab_migrar_v9');
global $wpdb;
$t = at_tab_tablas();
// Tablas v8 mínimas recreadas en el sitio de prueba (mismas columnas que setup-at-board.php).
$wpdb->query("DROP TABLE IF EXISTS {$t['cli']}, {$t['int']}");
$wpdb->query("CREATE TABLE {$t['cli']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, at_id VARCHAR(40) NOT NULL, nombre VARCHAR(191) NOT NULL, contacto VARCHAR(191) NOT NULL DEFAULT '', rubro VARCHAR(191) NOT NULL DEFAULT '', servicios TEXT DEFAULT NULL, paso TINYINT UNSIGNED NOT NULL DEFAULT 1, prioridad VARCHAR(3) NOT NULL DEFAULT 'P2', estado VARCHAR(12) NOT NULL DEFAULT 'progress', estado_label VARCHAR(80) NOT NULL DEFAULT '', ultima DATE NOT NULL DEFAULT '1970-01-01', notas TEXT DEFAULT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY at_id (at_id))");
$wpdb->query("CREATE TABLE {$t['int']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, at_id VARCHAR(40) NOT NULL, titulo VARCHAR(255) NOT NULL, asignado_a VARCHAR(60) NOT NULL DEFAULT 'Luis', tipo VARCHAR(20) NOT NULL DEFAULT 'ops', estado VARCHAR(12) NOT NULL DEFAULT 'backlog', prioridad VARCHAR(3) NOT NULL DEFAULT 'P2', ultima DATE NOT NULL DEFAULT '1970-01-01', notas TEXT DEFAULT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY at_id (at_id))");
foreach (['Luis', 'Claude', 'Codex', 'OpenCode Go', 'Otro'] as $i => $a) {
	$wpdb->insert($t['int'], ['at_id' => "PRUEBA-$i", 'titulo' => "Tarea $i", 'asignado_a' => $a]);
}
$admin_id = wp_insert_user(['user_login' => 'at-tab-prueba-admin', 'user_pass' => wp_generate_password(20), 'role' => 'administrator']);
$sin_id = wp_insert_user(['user_login' => 'at-tab-prueba-sin', 'user_pass' => wp_generate_password(20), 'role' => 'subscriber']);
$srv_id = wp_insert_user(['user_login' => 'at-tab-prueba-srv', 'user_pass' => wp_generate_password(20), 'role' => 'subscriber']);

ok(is_wp_error(at_tab_migrar_v9($sin_id)), '0) rechaza un admin_id sin manage_options');
$r1 = at_tab_migrar_v9($admin_id, 'at-tab-prueba-srv');
ok(!is_wp_error($r1) && $r1['columnas'] === 18, '1) agrega 9 columnas en cada tabla (18)');
ok($r1['mapeadas'] === 4, '1) mapea Luis, Claude, Codex y OpenCode Go (4)');
$asig = $wpdb->get_results("SELECT at_id, asignado FROM {$t['int']} ORDER BY at_id", OBJECT_K);
ok($asig['PRUEBA-0']->asignado === "wp:$admin_id", '1) Luis → wp:<admin>');
ok($asig['PRUEBA-1']->asignado === 'agente:claude' && $asig['PRUEBA-2']->asignado === 'agente:codex' && $asig['PRUEBA-3']->asignado === 'agente:opencode', '1) agentes mapeados');
ok($asig['PRUEBA-4']->asignado === '', '1) valor desconocido queda sin asignar');
$rol = get_role('colaborador_at');
ok($rol && $rol->has_cap('at_board_ver') && $rol->has_cap('at_board_editar_propias') && !$rol->has_cap('at_board_admin'), '1) rol colaborador_at con sus dos capacidades');
ok(get_role('administrator')->has_cap('at_board_admin'), '1) el administrador recibe at_board_admin');
ok($r1['servicio'] === true && user_can($srv_id, 'at_board_sync'), '1) el usuario de servicio recibe at_board_sync');
$r2 = at_tab_migrar_v9($admin_id, 'at-tab-prueba-srv');
ok($r2['columnas'] === 0 && $r2['mapeadas'] === 0, '2) segunda corrida no cambia nada (idempotente)');
ok(get_option('at_board_schema_version') === '9.0.0', '2) versión de esquema 9.0.0');

foreach ([$admin_id, $sin_id, $srv_id] as $u) { wp_delete_user($u); }
$wpdb->query("DROP TABLE IF EXISTS {$t['cli']}, {$t['int']}");
fin();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `AT_WP_LOAD=$WPL /c/wamp64/bin/php/php8.4.15/php.exe tests/tablero/migracion-wp-test.php`
Expected: fatal al requerir `wp-content/mu-plugins/at-tablero.php` (todavía no existe). Crear en este paso el cargador mínimo:

```php
<?php
/**
 * Plugin Name: AT Tablero v9
 * Description: Tablero AT con permisos por usuario de WordPress y tickets de agentes (AT-TAB-004).
 */
if (!defined('ABSPATH')) { exit; }
foreach (['permisos', 'migracion', 'datos', 'rest'] as $at_tab_parte) {
	$at_tab_archivo = __DIR__ . '/at-tablero/' . $at_tab_parte . '.php';
	if (is_file($at_tab_archivo)) { require_once $at_tab_archivo; }
}
if (function_exists('at_tab_registrar_rutas')) {
	add_action('rest_api_init', 'at_tab_registrar_rutas');
}
```
Volver a correr. Expected: `FALLA falta la función at_tab_tablas()`.

- [ ] **Step 3: Write minimal implementation (`migracion.php`)**

```php
<?php
/** Tablero AT v9 — migración idempotente (spec §4). */
if (!defined('ABSPATH')) { exit; }

function at_tab_tablas(): array {
	global $wpdb;
	return [
		'cli' => $wpdb->prefix . 'omnichannel_at_board',
		'int' => $wpdb->prefix . 'omnichannel_at_internas',
		'adj' => $wpdb->prefix . 'omnichannel_at_attachments',
	];
}

function at_tab_columnas_v9(): array {
	return [
		'asignado' => "VARCHAR(60) NOT NULL DEFAULT ''",
		'origen' => "VARCHAR(10) NOT NULL DEFAULT 'manual'",
		'ticket_id' => 'VARCHAR(40) NULL DEFAULT NULL',
		'ticket_ref' => 'VARCHAR(255) NULL DEFAULT NULL',
		'decide_luis' => 'TINYINT(1) NOT NULL DEFAULT 0',
		'archivada' => 'TINYINT(1) NOT NULL DEFAULT 0',
		'creado_por' => 'BIGINT UNSIGNED NULL DEFAULT NULL',
		'actualizado_por' => 'BIGINT UNSIGNED NULL DEFAULT NULL',
		'sync_at' => 'DATETIME NULL DEFAULT NULL',
	];
}

function at_tab_migrar_v9(int $admin_id, string $servicio_login = '') {
	global $wpdb;
	if (!$admin_id || !user_can($admin_id, 'manage_options')) {
		return new WP_Error('at_tab_admin_invalido', 'admin_id debe ser un usuario con manage_options.');
	}
	$t = at_tab_tablas();
	foreach (['cli', 'int'] as $k) {
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t[$k])) !== $t[$k]) {
			return new WP_Error('at_tab_sin_tablas', "Falta la tabla {$t[$k]}: correr antes el setup v8.");
		}
	}
	$agregadas = 0;
	foreach (['cli', 'int'] as $k) {
		$existentes = $wpdb->get_col("SHOW COLUMNS FROM {$t[$k]}");
		foreach (at_tab_columnas_v9() as $col => $def) {
			if (!in_array($col, $existentes, true)) {
				$wpdb->query("ALTER TABLE {$t[$k]} ADD COLUMN {$col} {$def}");
				$agregadas++;
			}
		}
	}
	$mapa = ['Luis' => "wp:{$admin_id}", 'Claude' => 'agente:claude', 'Codex' => 'agente:codex', 'OpenCode Go' => 'agente:opencode'];
	$mapeadas = 0;
	foreach ($mapa as $viejo => $nuevo) {
		$mapeadas += (int) $wpdb->query($wpdb->prepare(
			"UPDATE {$t['int']} SET asignado = %s WHERE asignado_a = %s AND asignado = ''", $nuevo, $viejo
		));
	}
	$rol_creado = false;
	if (!get_role('colaborador_at')) {
		add_role('colaborador_at', 'Colaborador AT', ['read' => true, 'at_board_ver' => true, 'at_board_editar_propias' => true]);
		$rol_creado = true;
	}
	$admin_rol = get_role('administrator');
	foreach (['at_board_ver', 'at_board_editar_propias', 'at_board_admin'] as $cap) {
		if ($admin_rol && !$admin_rol->has_cap($cap)) { $admin_rol->add_cap($cap); }
	}
	$servicio = false;
	if ($servicio_login !== '') {
		$u = get_user_by('login', $servicio_login);
		if ($u) { $u->add_cap('at_board_sync'); $servicio = true; }
	}
	update_option('at_board_schema_version', '9.0.0', false);
	return ['columnas' => $agregadas, 'mapeadas' => $mapeadas, 'rol' => $rol_creado, 'servicio' => $servicio];
}
```

- [ ] **Step 4: Crear el CLI `tools/tablero-migrar-v9.php`**

```php
<?php
// Uso (solo consola): php tools/tablero-migrar-v9.php --wp-load=<ruta wp-load.php> --admin-id=<id de Luis> [--servicio=agentes-at]
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$o = getopt('', ['wp-load:', 'admin-id:', 'servicio::']);
if (empty($o['wp-load']) || empty($o['admin-id'])) { fwrite(STDERR, "Faltan --wp-load y --admin-id\n"); exit(2); }
define('WP_USE_THEMES', false);
require $o['wp-load'];
if (!function_exists('at_tab_migrar_v9')) { fwrite(STDERR, "El mu-plugin at-tablero no está cargado en ese sitio.\n"); exit(2); }
$r = at_tab_migrar_v9((int) $o['admin-id'], (string) ($o['servicio'] ?? ''));
if (is_wp_error($r)) { fwrite(STDERR, $r->get_error_message() . "\n"); exit(1); }
echo json_encode($r), "\n";
```

- [ ] **Step 5: Run test to verify it passes**

Run: `AT_WP_LOAD=$WPL /c/wamp64/bin/php/php8.4.15/php.exe tests/tablero/migracion-wp-test.php`
Expected: `TODO OK`.

- [ ] **Step 6: Commit**

```bash
git add wp-content/mu-plugins/at-tablero.php wp-content/mu-plugins/at-tablero/migracion.php tools/tablero-migrar-v9.php tests/tablero/migracion-wp-test.php
git commit -m "feat(tablero): migración v9 idempotente, rol Colaborador AT y responsables nuevos (AT-TAB-004)"
```

---

### Task 3: Datos — personas, listado, guardado y sync por lote

**Files:**
- Create: `wp-content/mu-plugins/at-tablero/datos.php`
- Test: `tests/tablero/datos-wp-test.php`

**Interfaces:**
- Consumes: `at_tab_actor`, `at_tab_puede` (Task 1); `at_tab_tablas`, `at_tab_migrar_v9` (Task 2).
- Produces:
  - `at_tab_actor_de_usuario(?WP_User $u): array`, que traduce las capacidades a `at_tab_actor`. Orden: `at_board_admin` → admin; `at_board_sync` → servicio; `at_board_ver` → colaborador; `at_board_ver_proyecto` → externo; si no, ninguno.
  - `at_tab_personas(): array` → lista de `['key','nombre','tipo' => 'persona'|'agente','iniciales']`, con los usuarios que tienen `at_board_ver` y luego los agentes.
  - `at_tab_listar(array $actor): array` → `['version' => 'v9', 'clientes' => [...], 'internas' => [...], 'personas' => [...], 'ultima_sync' => ?string]`. Excluye las archivadas.
  - `at_tab_guardar(array $actor, string $tipo, array $input, int $user_id)` → `['at_id','tipo','accion' => 'insert'|'update']` o `WP_Error` con `status` 400/403/409.
  - `at_tab_sync(array $actor, array $tarjetas, bool $parcial)` → `['creadas','actualizadas','archivadas']` o `WP_Error` 403/400. Cada tarjeta es `['at_id','titulo','estado','prioridad','asignado','ticket_id','ticket_ref','decide_luis','notas']`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// AT_WP_LOAD=... php tests/tablero/datos-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
exigir('at_tab_listar', 'at_tab_guardar', 'at_tab_sync', 'at_tab_personas', 'at_tab_actor_de_usuario');
global $wpdb;
$t = at_tab_tablas();
require __DIR__ . '/fixture-tablas-v8.php'; // define at_tab_prueba_tablas_v8(): recrea las dos tablas v8
at_tab_prueba_tablas_v8();
$admin_id = wp_insert_user(['user_login' => 'at-tab-prueba-admin', 'user_pass' => wp_generate_password(20), 'role' => 'administrator']);
$srv_id = wp_insert_user(['user_login' => 'at-tab-prueba-srv', 'user_pass' => wp_generate_password(20), 'role' => 'subscriber']);
at_tab_migrar_v9($admin_id, 'at-tab-prueba-srv');
$ana_id = wp_insert_user(['user_login' => 'at-tab-prueba-ana', 'user_pass' => wp_generate_password(20), 'role' => 'colaborador_at', 'display_name' => 'Ana Prueba']);
$admin = at_tab_actor_de_usuario(get_user_by('id', $admin_id));
$ana = at_tab_actor_de_usuario(get_user_by('id', $ana_id));
$srv = at_tab_actor_de_usuario(get_user_by('id', $srv_id));
ok($admin['tipo'] === 'admin' && $ana['tipo'] === 'colaborador' && $srv['tipo'] === 'servicio', '0) actores desde capacidades');

$p = at_tab_personas();
$keys = array_column($p, 'key');
ok(in_array("wp:$ana_id", $keys, true) && in_array('agente:claude', $keys, true) && !in_array("wp:$srv_id", $keys, true), '1) personas: colaboradores + agentes, sin el servicio');

$r = at_tab_guardar($ana, 'int', ['at_id' => 'P-1', 'titulo' => 'Mía', 'asignado' => "wp:$ana_id", 'estado' => 'todo'], $ana_id);
ok(!is_wp_error($r) && $r['accion'] === 'insert', '2) colaboradora crea su tarjeta');
ok($wpdb->get_var("SELECT creado_por FROM {$t['int']} WHERE at_id='P-1'") == $ana_id, '2) queda creado_por');
$r = at_tab_guardar($ana, 'int', ['at_id' => 'P-1', 'titulo' => 'Mía', 'asignado' => "wp:$admin_id", 'estado' => 'progress'], $ana_id);
ok(is_wp_error($r) && $r->get_error_data()['status'] === 403, '3) colaboradora NO reasigna su propia tarjeta a otro (Review Focus 2)');
$r = at_tab_guardar($admin, 'int', ['at_id' => 'P-2', 'titulo' => 'De Luis', 'asignado' => "wp:$admin_id"], $admin_id);
$r = at_tab_guardar($ana, 'int', ['at_id' => 'P-2', 'titulo' => 'Cambio', 'asignado' => "wp:$admin_id"], $ana_id);
ok(is_wp_error($r) && $r->get_error_data()['status'] === 403, '4) colaboradora no edita la ajena');
$r = at_tab_guardar($admin, 'int', ['at_id' => 'T-FALSO-1', 'titulo' => 'Pisa ticket', 'asignado' => "wp:$admin_id"], $admin_id);
ok(is_wp_error($r) && $r->get_error_data()['status'] === 409, '5) prefijo T- reservado para tickets (Review Focus 4)');

$lote = [['at_id' => 'T-AT-X-001', 'titulo' => 'Ticket X', 'estado' => 'review', 'prioridad' => 'P2', 'asignado' => 'agente:claude', 'ticket_id' => 'AT-X-001', 'ticket_ref' => '', 'decide_luis' => 0, 'notas' => ''],
         ['at_id' => 'T-AT-X-001-D1', 'titulo' => '⚖️ ¿Mergear?', 'estado' => 'todo', 'prioridad' => 'P1', 'asignado' => "wp:$admin_id", 'ticket_id' => 'AT-X-001', 'ticket_ref' => '', 'decide_luis' => 1, 'notas' => '']];
ok(is_wp_error(at_tab_sync($admin, $lote, false)), '6) solo el servicio sincroniza');
$s1 = at_tab_sync($srv, $lote, false);
ok($s1['creadas'] === 2 && $s1['actualizadas'] === 0, '6) sync crea 2');
$s2 = at_tab_sync($srv, $lote, false);
ok($s2['creadas'] === 0 && $s2['actualizadas'] === 0 && $s2['archivadas'] === 0, '6) sync idempotente');
$r = at_tab_guardar($admin, 'int', ['at_id' => 'T-AT-X-001', 'titulo' => 'Edito ticket', 'asignado' => 'agente:claude'], $admin_id);
ok(is_wp_error($r) && $r->get_error_data()['status'] === 409, '7) nadie edita una tarjeta de ticket desde el tablero');
$s3 = at_tab_sync($srv, [$lote[0]], true);
ok($s3['archivadas'] === 0, '8) sync parcial no archiva (Review Focus 3)');
$s4 = at_tab_sync($srv, [$lote[0]], false);
ok($s4['archivadas'] === 1, '8) sync completo archiva la decisión que ya no viene');
$l = at_tab_listar($ana);
$ids = array_column($l['internas'], 'at_id');
ok(in_array('T-AT-X-001', $ids, true) && !in_array('T-AT-X-001-D1', $ids, true) && $l['ultima_sync'] !== null, '9) listar oculta archivadas y trae ultima_sync');

foreach ([$admin_id, $srv_id, $ana_id] as $u) { wp_delete_user($u); }
$wpdb->query("DROP TABLE IF EXISTS {$t['cli']}, {$t['int']}");
fin();
```

Crear también `tests/tablero/fixture-tablas-v8.php`, y cambiar `migracion-wp-test.php` para que llame a
`at_tab_prueba_tablas_v8()` en vez de repetir el SQL:

```php
<?php
// Recrea las tablas v8 del tablero (mismas columnas que setup-at-board.php) en el sitio de prueba.
function at_tab_prueba_tablas_v8(): void {
	global $wpdb;
	$t = at_tab_tablas();
	$wpdb->query("DROP TABLE IF EXISTS {$t['cli']}, {$t['int']}, {$t['adj']}");
	$wpdb->query("CREATE TABLE {$t['cli']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, at_id VARCHAR(40) NOT NULL, nombre VARCHAR(191) NOT NULL, contacto VARCHAR(191) NOT NULL DEFAULT '', rubro VARCHAR(191) NOT NULL DEFAULT '', servicios TEXT DEFAULT NULL, paso TINYINT UNSIGNED NOT NULL DEFAULT 1, prioridad VARCHAR(3) NOT NULL DEFAULT 'P2', estado VARCHAR(12) NOT NULL DEFAULT 'progress', estado_label VARCHAR(80) NOT NULL DEFAULT '', ultima DATE NOT NULL DEFAULT '1970-01-01', notas TEXT DEFAULT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY at_id (at_id))");
	$wpdb->query("CREATE TABLE {$t['int']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, at_id VARCHAR(40) NOT NULL, titulo VARCHAR(255) NOT NULL, asignado_a VARCHAR(60) NOT NULL DEFAULT 'Luis', tipo VARCHAR(20) NOT NULL DEFAULT 'ops', estado VARCHAR(12) NOT NULL DEFAULT 'backlog', prioridad VARCHAR(3) NOT NULL DEFAULT 'P2', ultima DATE NOT NULL DEFAULT '1970-01-01', notas TEXT DEFAULT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY at_id (at_id))");
	$wpdb->query("CREATE TABLE {$t['adj']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, at_id VARCHAR(40) NOT NULL, tipo VARCHAR(3) NOT NULL DEFAULT 'int', filename VARCHAR(191) NOT NULL, mime_type VARCHAR(50) NOT NULL, file_size INT UNSIGNED NOT NULL, contenido LONGBLOB NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_task (tipo, at_id))");
}
```

Las pruebas que terminan con `DROP TABLE` también deben borrar `{$t['adj']}`.

- [ ] **Step 2: Run test to verify it fails**

Run: `AT_WP_LOAD=$WPL /c/wamp64/bin/php/php8.4.15/php.exe tests/tablero/datos-wp-test.php`
Expected: `FALLA falta la función at_tab_listar()`.

- [ ] **Step 3: Write minimal implementation (`datos.php`)**

```php
<?php
/** Tablero AT v9 — datos (spec §4, §5 y §7). */
if (!defined('ABSPATH')) { exit; }

function at_tab_actor_de_usuario(?WP_User $u): array {
	if (!$u || !$u->exists()) return at_tab_actor('ninguno');
	$key = 'wp:' . $u->ID;
	if (user_can($u, 'at_board_admin')) return at_tab_actor('admin', $key);
	if (user_can($u, 'at_board_sync')) return at_tab_actor('servicio', $key);
	if (user_can($u, 'at_board_ver')) return at_tab_actor('colaborador', $key);
	if (user_can($u, 'at_board_ver_proyecto')) return at_tab_actor('externo', $key);
	return at_tab_actor('ninguno', $key);
}

function at_tab_iniciales(string $nombre): string {
	$partes = preg_split('/\s+/', trim($nombre)) ?: [];
	$ini = '';
	foreach (array_slice($partes, 0, 2) as $p) { $ini .= mb_strtoupper(mb_substr($p, 0, 1)); }
	return $ini !== '' ? $ini : '??';
}

function at_tab_personas(): array {
	$out = [];
	foreach (get_users(['capability' => 'at_board_ver', 'orderby' => 'display_name']) as $u) {
		$out[] = ['key' => 'wp:' . $u->ID, 'nombre' => $u->display_name, 'tipo' => 'persona', 'iniciales' => at_tab_iniciales($u->display_name)];
	}
	foreach (AT_TAB_AGENTES as $k => $n) {
		$out[] = ['key' => $k, 'nombre' => '🤖 ' . $n, 'tipo' => 'agente', 'iniciales' => mb_strtoupper(mb_substr($n, 0, 2))];
	}
	return $out;
}

function at_tab_limpiar(string $campo, $v) {
	$v = is_scalar($v) ? trim((string) $v) : '';
	switch ($campo) {
		case 'estado': return in_array($v, ['done','progress','wait','blocked','backlog','todo','review'], true) ? $v : 'backlog';
		case 'prioridad': $v = strtoupper($v); return in_array($v, ['P0','P1','P2','P3'], true) ? $v : 'P2';
		case 'asignado': return preg_match('/^(wp:\d+|agente:(claude|codex|opencode))$/', $v) ? $v : '';
		case 'tipo_tarea': return in_array($v, ['dev','design','ops','research','docs'], true) ? $v : 'ops';
		case 'paso': $i = (int) $v; return ($i >= 1 && $i <= 6) ? $i : 1;
		case 'ultima': return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : gmdate('Y-m-d');
	}
	return mb_substr($v, 0, $campo === 'notas' ? 65500 : 255);
}

function at_tab_error(string $codigo, string $msg, int $status): WP_Error {
	return new WP_Error($codigo, $msg, ['status' => $status]);
}

function at_tab_listar(array $actor): array {
	global $wpdb;
	$t = at_tab_tablas();
	$cols = 'at_id, asignado, origen, ticket_id, ticket_ref, decide_luis, prioridad, estado, DATE_FORMAT(ultima,\'%Y-%m-%d\') AS ultima, notas';
	$clientes = $wpdb->get_results("SELECT {$cols}, nombre, contacto, rubro, servicios, paso, estado_label AS estadoLabel FROM {$t['cli']} WHERE archivada = 0 ORDER BY paso, prioridad, at_id", ARRAY_A) ?: [];
	foreach ($clientes as &$c) {
		$c['servicios'] = json_decode((string) $c['servicios'], true) ?: [];
		$c['paso'] = (int) $c['paso'];
		$c['decide_luis'] = (int) $c['decide_luis'];
	}
	unset($c);
	$internas = $wpdb->get_results("SELECT {$cols}, titulo, tipo FROM {$t['int']} WHERE archivada = 0 ORDER BY FIELD(estado,'progress','todo','review','wait','blocked','backlog','done'), prioridad, at_id", ARRAY_A) ?: [];
	foreach ($internas as &$i) { $i['decide_luis'] = (int) $i['decide_luis']; }
	unset($i);
	$ultima = $wpdb->get_var("SELECT MAX(sync_at) FROM {$t['int']} WHERE origen = 'ticket'");
	return ['version' => 'v9', 'clientes' => $clientes, 'internas' => $internas, 'personas' => at_tab_personas(), 'ultima_sync' => $ultima ?: null];
}

function at_tab_guardar(array $actor, string $tipo, array $input, int $user_id) {
	global $wpdb;
	if (!in_array($tipo, ['cli', 'int'], true)) return at_tab_error('at_tab_tipo', 'tipo inválido', 400);
	$t = at_tab_tablas();
	$tabla = $t[$tipo];
	$at_id = mb_substr(trim((string) ($input['at_id'] ?? '')), 0, 40);
	if ($at_id === '') return at_tab_error('at_tab_at_id', 'at_id requerido', 400);
	$actual = $wpdb->get_row($wpdb->prepare("SELECT at_id, origen, asignado FROM {$tabla} WHERE at_id = %s", $at_id), ARRAY_A);
	if (strpos($at_id, 'T-') === 0 || ($actual && $actual['origen'] === 'ticket')) {
		return at_tab_error('at_tab_ticket', 'Las tarjetas de ticket se cambian en git, no en el tablero.', 409);
	}
	$asignado = at_tab_limpiar('asignado', $input['asignado'] ?? '');
	$tarjeta_nueva = ['tipo' => $tipo, 'origen' => 'manual', 'asignado' => $asignado];
	if ($actual) {
		if (!at_tab_puede($actor, 'editar', ['tipo' => $tipo, 'origen' => 'manual', 'asignado' => $actual['asignado']])) {
			return at_tab_error('at_tab_ajena', 'Solo puedes cambiar tus propias tarjetas.', 403);
		}
		if ($asignado !== $actual['asignado'] && !at_tab_puede($actor, 'reasignar', $tarjeta_nueva)) {
			return at_tab_error('at_tab_reasignar', 'Solo Luis reasigna tarjetas.', 403);
		}
	} elseif (!at_tab_puede($actor, 'crear', $tarjeta_nueva)) {
		return at_tab_error('at_tab_crear', 'No puedes crear esta tarjeta.', 403);
	}
	$dato = [
		'at_id' => $at_id, 'asignado' => $asignado, 'origen' => 'manual',
		'estado' => at_tab_limpiar('estado', $input['estado'] ?? 'backlog'),
		'prioridad' => at_tab_limpiar('prioridad', $input['prioridad'] ?? 'P2'),
		'ultima' => at_tab_limpiar('ultima', $input['ultima'] ?? ''),
		'notas' => at_tab_limpiar('notas', $input['notas'] ?? ''),
		'actualizado_por' => $user_id,
	];
	if ($tipo === 'int') {
		$dato['titulo'] = at_tab_limpiar('titulo', $input['titulo'] ?? '');
		$dato['tipo'] = at_tab_limpiar('tipo_tarea', $input['tipo_tarea'] ?? 'ops');
		if ($dato['titulo'] === '') return at_tab_error('at_tab_titulo', 'titulo requerido', 400);
	} else {
		foreach (['nombre', 'contacto', 'rubro'] as $c) { $dato[$c] = at_tab_limpiar($c, $input[$c] ?? ''); }
		$dato['paso'] = at_tab_limpiar('paso', $input['paso'] ?? 1);
		$dato['estado_label'] = mb_substr(trim((string) ($input['estadoLabel'] ?? '')), 0, 80);
		$dato['servicios'] = wp_json_encode(array_slice(array_values(array_filter(array_map('strval', (array) ($input['servicios'] ?? [])))), 0, 20));
		if ($dato['nombre'] === '') return at_tab_error('at_tab_nombre', 'nombre requerido', 400);
	}
	if ($actual) {
		$wpdb->update($tabla, $dato, ['at_id' => $at_id]);
	} else {
		$dato['creado_por'] = $user_id;
		$wpdb->insert($tabla, $dato);
	}
	if ($wpdb->last_error) return at_tab_error('at_tab_db', 'db_error', 500);
	return ['at_id' => $at_id, 'tipo' => $tipo, 'accion' => $actual ? 'update' : 'insert'];
}

function at_tab_sync(array $actor, array $tarjetas, bool $parcial) {
	global $wpdb;
	if (!at_tab_puede($actor, 'sync')) return at_tab_error('at_tab_sync', 'Solo el servicio sincroniza.', 403);
	$t = at_tab_tablas();
	$ahora = current_time('mysql', true);
	$res = ['creadas' => 0, 'actualizadas' => 0, 'archivadas' => 0];
	$vigentes = [];
	foreach ($tarjetas as $c) {
		$at_id = (string) ($c['at_id'] ?? '');
		if (strpos($at_id, 'T-') !== 0) return at_tab_error('at_tab_lote', "at_id de ticket inválido: {$at_id}", 400);
		$vigentes[] = $at_id;
		$dato = [
			'titulo' => at_tab_limpiar('titulo', $c['titulo'] ?? ''), 'estado' => at_tab_limpiar('estado', $c['estado'] ?? 'backlog'),
			'prioridad' => at_tab_limpiar('prioridad', $c['prioridad'] ?? 'P2'), 'asignado' => at_tab_limpiar('asignado', $c['asignado'] ?? ''),
			'ticket_id' => mb_substr((string) ($c['ticket_id'] ?? ''), 0, 40), 'ticket_ref' => mb_substr((string) ($c['ticket_ref'] ?? ''), 0, 255),
			'decide_luis' => empty($c['decide_luis']) ? 0 : 1, 'notas' => at_tab_limpiar('notas', $c['notas'] ?? ''),
			'origen' => 'ticket', 'archivada' => 0, 'tipo' => 'ops',
		];
		$actual = $wpdb->get_row($wpdb->prepare("SELECT titulo, estado, prioridad, asignado, ticket_id, ticket_ref, decide_luis, notas, origen, archivada, tipo FROM {$t['int']} WHERE at_id = %s", $at_id), ARRAY_A);
		if (!$actual) {
			$wpdb->insert($t['int'], $dato + ['at_id' => $at_id, 'sync_at' => $ahora, 'ultima' => gmdate('Y-m-d')]);
			$res['creadas']++;
		} elseif (array_map('strval', array_intersect_key($actual, $dato)) != array_map('strval', $dato)) {
			$wpdb->update($t['int'], $dato + ['sync_at' => $ahora, 'ultima' => gmdate('Y-m-d')], ['at_id' => $at_id]);
			$res['actualizadas']++;
		} else {
			$wpdb->update($t['int'], ['sync_at' => $ahora], ['at_id' => $at_id]);
		}
	}
	if (!$parcial) {
		$ph = $vigentes ? implode(',', array_fill(0, count($vigentes), '%s')) : "''";
		$sql = "UPDATE {$t['int']} SET archivada = 1 WHERE origen = 'ticket' AND archivada = 0" . ($vigentes ? " AND at_id NOT IN ($ph)" : '');
		$res['archivadas'] = (int) $wpdb->query($vigentes ? $wpdb->prepare($sql, $vigentes) : $sql);
	}
	if ($wpdb->last_error) return at_tab_error('at_tab_db', 'db_error', 500);
	return $res;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `AT_WP_LOAD=$WPL /c/wamp64/bin/php/php8.4.15/php.exe tests/tablero/datos-wp-test.php` y repetir `migracion-wp-test.php`.
Expected: `TODO OK` en las dos.

- [ ] **Step 5: Commit**

```bash
git add wp-content/mu-plugins/at-tablero/datos.php tests/tablero/datos-wp-test.php tests/tablero/fixture-tablas-v8.php tests/tablero/migracion-wp-test.php
git commit -m "feat(tablero): datos v9 con permisos en el servidor y sync por lote (AT-TAB-004)"
```

---

### Task 4: Rutas REST `at-tablero/v1` (con adjuntos)

**Files:**
- Create: `wp-content/mu-plugins/at-tablero/rest.php`
- Test: `tests/tablero/rest-wp-test.php`

**Interfaces:**
- Consumes: Tasks 1–3.
- Produces: `at_tab_registrar_rutas(): void` (dispara `do_action('at_tab_rutas_registradas')`) y estas rutas:
  - `GET /at-tablero/v1/tablero` → `at_tab_listar` (requiere `ver`)
  - `POST /at-tablero/v1/tarjeta` con body `{tipo, at_id, ...}` → `at_tab_guardar`
  - `POST /at-tablero/v1/sync` con body `{tarjetas: [...], parcial: bool}` → `at_tab_sync`
  - `GET /at-tablero/v1/adjunto/(?P<id>\d+)` (binario), `POST /at-tablero/v1/adjunto` (multipart `imagen`, `at_id`) y `DELETE /at-tablero/v1/adjunto/(?P<id>\d+)`, con los mismos límites de la v8: 2 MiB, 5 por tarea y jpeg/png/webp. Escribir exige `editar` sobre la tarea dueña.

- [ ] **Step 1: Write the failing test**

```php
<?php
// AT_WP_LOAD=... php tests/tablero/rest-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixture-tablas-v8.php';
exigir('at_tab_registrar_rutas');
at_tab_prueba_tablas_v8();
$admin_id = wp_insert_user(['user_login' => 'at-tab-prueba-admin', 'user_pass' => wp_generate_password(20), 'role' => 'administrator']);
$srv_id = wp_insert_user(['user_login' => 'at-tab-prueba-srv', 'user_pass' => wp_generate_password(20), 'role' => 'subscriber']);
at_tab_migrar_v9($admin_id, 'at-tab-prueba-srv');
$ana_id = wp_insert_user(['user_login' => 'at-tab-prueba-ana', 'user_pass' => wp_generate_password(20), 'role' => 'colaborador_at']);
$sin_id = wp_insert_user(['user_login' => 'at-tab-prueba-sin', 'user_pass' => wp_generate_password(20), 'role' => 'subscriber']);

function pedir(string $m, string $ruta, array $body = []) {
	$r = new WP_REST_Request($m, $ruta);
	if ($body) { $r->set_header('content-type', 'application/json'); $r->set_body(wp_json_encode($body)); }
	return rest_do_request($r);
}
wp_set_current_user(0);
ok(pedir('GET', '/at-tablero/v1/tablero')->get_status() === 401, '1) sin sesión → 401');
wp_set_current_user($sin_id);
ok(pedir('GET', '/at-tablero/v1/tablero')->get_status() === 403, '2) con sesión sin rol → 403');
wp_set_current_user($ana_id);
$g = pedir('GET', '/at-tablero/v1/tablero');
ok($g->get_status() === 200 && ($g->get_data()['version'] ?? '') === 'v9', '3) colaboradora lee el tablero');
ok(pedir('POST', '/at-tablero/v1/tarjeta', ['tipo' => 'int', 'at_id' => 'R-1', 'titulo' => 'x', 'asignado' => "wp:$admin_id"])->get_status() === 403, '4) colaboradora no crea para otro');
ok(pedir('POST', '/at-tablero/v1/tarjeta', ['tipo' => 'int', 'at_id' => 'R-2', 'titulo' => 'mía', 'asignado' => "wp:$ana_id"])->get_status() === 200, '5) colaboradora crea la suya');
ok(pedir('POST', '/at-tablero/v1/sync', ['tarjetas' => [], 'parcial' => true])->get_status() === 403, '6) colaboradora no sincroniza');
wp_set_current_user($srv_id);
$s = pedir('POST', '/at-tablero/v1/sync', ['tarjetas' => [['at_id' => 'T-AT-R-1', 'titulo' => 't', 'estado' => 'todo', 'asignado' => 'agente:codex', 'ticket_id' => 'AT-R-1']], 'parcial' => false]);
ok($s->get_status() === 200 && $s->get_data()['creadas'] === 1, '7) el servicio sincroniza');
ok(pedir('GET', '/at-tablero/v1/tablero')->get_status() === 403, '8) el servicio no lee la interfaz');

foreach ([$admin_id, $srv_id, $ana_id, $sin_id] as $u) { wp_delete_user($u); }
global $wpdb; $t = at_tab_tablas(); $wpdb->query("DROP TABLE IF EXISTS {$t['cli']}, {$t['int']}");
fin();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `AT_WP_LOAD=$WPL /c/wamp64/bin/php/php8.4.15/php.exe tests/tablero/rest-wp-test.php`
Expected: `FALLA falta la función at_tab_registrar_rutas()`.

- [ ] **Step 3: Write minimal implementation (`rest.php`)**

```php
<?php
/** Tablero AT v9 — rutas REST (spec §3 y §7). */
if (!defined('ABSPATH')) { exit; }

function at_tab_actor_actual(): array { return at_tab_actor_de_usuario(wp_get_current_user()); }

function at_tab_permiso(string $accion): callable {
	return function () use ($accion) {
		if (!is_user_logged_in()) return new WP_Error('rest_not_logged_in', 'Inicia sesión.', ['status' => 401]);
		return at_tab_puede(at_tab_actor_actual(), $accion) ? true : new WP_Error('at_tab_sin_acceso', 'Sin acceso al tablero.', ['status' => 403]);
	};
}

function at_tab_registrar_rutas(): void {
	$ns = 'at-tablero/v1';
	register_rest_route($ns, '/tablero', ['methods' => 'GET', 'permission_callback' => at_tab_permiso('ver'),
		'callback' => fn() => rest_ensure_response(at_tab_listar(at_tab_actor_actual()))]);
	register_rest_route($ns, '/tarjeta', ['methods' => 'POST', 'permission_callback' => at_tab_permiso('ver'),
		'callback' => function (WP_REST_Request $r) {
			$p = $r->get_json_params() ?: [];
			$res = at_tab_guardar(at_tab_actor_actual(), (string) ($p['tipo'] ?? ''), $p, get_current_user_id());
			return is_wp_error($res) ? $res : rest_ensure_response($res);
		}]);
	register_rest_route($ns, '/sync', ['methods' => 'POST', 'permission_callback' => at_tab_permiso('sync'),
		'callback' => function (WP_REST_Request $r) {
			$p = $r->get_json_params() ?: [];
			$res = at_tab_sync(at_tab_actor_actual(), (array) ($p['tarjetas'] ?? []), !empty($p['parcial']));
			return is_wp_error($res) ? $res : rest_ensure_response($res);
		}]);
	register_rest_route($ns, '/adjunto/(?P<id>\d+)', [
		['methods' => 'GET', 'permission_callback' => at_tab_permiso('ver'), 'callback' => 'at_tab_rest_adjunto_get'],
		['methods' => 'DELETE', 'permission_callback' => at_tab_permiso('ver'), 'callback' => 'at_tab_rest_adjunto_borrar'],
	]);
	register_rest_route($ns, '/adjunto', ['methods' => 'POST', 'permission_callback' => at_tab_permiso('ver'), 'callback' => 'at_tab_rest_adjunto_subir']);
	do_action('at_tab_rutas_registradas');
}

function at_tab_puede_editar_tarea(string $at_id): bool {
	global $wpdb; $t = at_tab_tablas();
	$f = $wpdb->get_row($wpdb->prepare("SELECT origen, asignado FROM {$t['int']} WHERE at_id = %s", $at_id), ARRAY_A);
	return $f && at_tab_puede(at_tab_actor_actual(), 'editar', ['tipo' => 'int', 'origen' => $f['origen'], 'asignado' => $f['asignado']]);
}

function at_tab_rest_adjunto_get(WP_REST_Request $r) {
	global $wpdb; $t = at_tab_tablas();
	$a = $wpdb->get_row($wpdb->prepare("SELECT id, mime_type, file_size, contenido FROM {$t['adj']} WHERE id = %d", (int) $r['id']));
	$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
	if (!$a || !isset($ext[$a->mime_type])) return new WP_Error('at_tab_adjunto', 'No existe.', ['status' => 404]);
	header('Content-Type: ' . $a->mime_type);
	header('Content-Length: ' . (int) $a->file_size);
	header('Cache-Control: private, no-store, max-age=0');
	echo $a->contenido;
	exit;
}

function at_tab_rest_adjunto_borrar(WP_REST_Request $r) {
	global $wpdb; $t = at_tab_tablas();
	$at_id = $wpdb->get_var($wpdb->prepare("SELECT at_id FROM {$t['adj']} WHERE id = %d", (int) $r['id']));
	if (!$at_id) return new WP_Error('at_tab_adjunto', 'No existe.', ['status' => 404]);
	if (!at_tab_puede_editar_tarea($at_id)) return new WP_Error('at_tab_ajena', 'Solo en tus propias tarjetas.', ['status' => 403]);
	$wpdb->delete($t['adj'], ['id' => (int) $r['id']], ['%d']);
	return rest_ensure_response(['attachment_id' => (int) $r['id'], 'accion' => 'delete']);
}

function at_tab_rest_adjunto_subir(WP_REST_Request $r) {
	global $wpdb; $t = at_tab_tablas();
	$at_id = mb_substr(trim((string) $r->get_param('at_id')), 0, 40);
	if ($at_id === '' || !at_tab_puede_editar_tarea($at_id)) return new WP_Error('at_tab_ajena', 'Solo en tus propias tarjetas.', ['status' => 403]);
	if ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['adj']} WHERE tipo='int' AND at_id=%s", $at_id)) >= 5) return new WP_Error('at_tab_limite', 'attachment_limit_reached', ['status' => 409]);
	$f = $r->get_file_params()['imagen'] ?? null;
	if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return new WP_Error('at_tab_subida', 'upload_failed', ['status' => 400]);
	if ($f['size'] < 1 || $f['size'] > 2097152) return new WP_Error('at_tab_tamano', 'attachment_size_invalid', ['status' => 413]);
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
	$perm = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];
	$nombre = sanitize_file_name($f['name']);
	$ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
	if (!isset($perm[$mime]) || !in_array($ext, $perm[$mime], true)) return new WP_Error('at_tab_mime', 'attachment_mime_invalid', ['status' => 415]);
	$wpdb->insert($t['adj'], ['at_id' => $at_id, 'tipo' => 'int', 'filename' => mb_substr($nombre, 0, 191), 'mime_type' => $mime, 'file_size' => (int) $f['size'], 'contenido' => file_get_contents($f['tmp_name'])], ['%s','%s','%s','%s','%d','%s']);
	if ($wpdb->last_error) return new WP_Error('at_tab_db', 'db_error', ['status' => 500]);
	return rest_ensure_response($wpdb->get_row($wpdb->prepare("SELECT id, at_id, tipo, filename, mime_type, file_size, created_at FROM {$t['adj']} WHERE id = %d", $wpdb->insert_id), ARRAY_A));
}
```

Además, `at_tab_listar` (Task 3) debe devolver los adjuntos de cada interna, igual que la v8. Reemplazar en
`datos.php` el bucle `foreach ($internas as &$i) { $i['decide_luis'] = (int) $i['decide_luis']; }` por:

```php
	$meta = $wpdb->get_results("SELECT id, at_id, tipo, filename, mime_type, file_size, created_at FROM {$t['adj']} WHERE tipo = 'int' ORDER BY created_at, id", ARRAY_A) ?: [];
	$por_tarea = [];
	foreach ($meta as $m) { $m['id'] = (int) $m['id']; $m['file_size'] = (int) $m['file_size']; $por_tarea[$m['at_id']][] = $m; }
	foreach ($internas as &$i) {
		$i['decide_luis'] = (int) $i['decide_luis'];
		$i['adjuntos'] = $por_tarea[$i['at_id']] ?? [];
	}
```

Y agregar al `rest-wp-test.php`, antes de la limpieza, la línea
`ok(isset(pedir('GET', '/at-tablero/v1/tablero')->get_data()['internas'][0]['adjuntos']), '9) listar trae adjuntos');`,
ejecutada con `wp_set_current_user($ana_id)`.

- [ ] **Step 4: Run test to verify it passes**

Run: los tres tests de WordPress (migracion, datos y rest) y `permisos-test.php`. Expected: `TODO OK` en todos.

- [ ] **Step 5: Commit**

```bash
git add wp-content/mu-plugins/at-tablero/rest.php wp-content/mu-plugins/at-tablero/datos.php tests/tablero/rest-wp-test.php
git commit -m "feat(tablero): rutas REST at-tablero/v1 con sesión de WordPress y adjuntos (AT-TAB-004)"
```

---

### Task 5: Puerta de entrada `tablero/index.php`

**Files:**
- Create: `tablero/index.php`
- Create: `tablero/.htaccess`
- Test: `tests/tablero/puerta-http.sh`

**Interfaces:**
- Consumes: capacidades de la Task 2 y `at_tab_actor_de_usuario` (Task 3).
- Produces: `window.AT_TABLERO = {rest: string, nonce: string, usuario: {key, nombre, tipo}}`, inyectado antes del primer `<script>` de `index.html`.

- [ ] **Step 1: Write the failing test (`tests/tablero/puerta-http.sh`)**

```bash
#!/usr/bin/env bash
# Uso: BASE=http://localhost:8093 bash tests/tablero/puerta-http.sh   (sitio de prueba sirviendo este worktree en /tablero/)
set -u; f=0
c=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/tablero/")
l=$(curl -s -o /dev/null -w "%{redirect_url}" "$BASE/tablero/")
[ "$c" = "302" ] && [[ "$l" == *wp-login.php* ]] && echo "ok   sin sesión redirige al login" || { echo "FALLA sin sesión: $c $l"; f=1; }
d=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/tablero/index.html")
[ "$d" = "403" ] && echo "ok   index.html directo bloqueado" || { echo "FALLA index.html directo: $d"; f=1; }
exit $f
```

- [ ] **Step 2: Run test to verify it fails**

Servir el sitio de prueba con este worktree enlazado. En PowerShell:
`New-Item -ItemType Junction -Path "<sitio>\tablero" -Target "C:\wamp64\www\automatiza-tech\.worktrees\tablero-personas\tablero"`
y lo mismo para `<sitio>\wp-content\mu-plugins\at-tablero*`. Arrancar con
`php -S localhost:8093 -t <sitio> <sitio>/router.php`.
Run: `BASE=http://localhost:8093 bash tests/tablero/puerta-http.sh`
Expected: FALLA en las dos líneas (hoy se sirve el HTML directo).

- [ ] **Step 3: Write minimal implementation**

`tablero/index.php`:
```php
<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__) . '/wp-load.php';
nocache_headers();
if (!is_user_logged_in()) { auth_redirect(); exit; }
$at_tab_actor = function_exists('at_tab_actor_de_usuario') ? at_tab_actor_de_usuario(wp_get_current_user()) : ['tipo' => 'ninguno'];
if (!in_array($at_tab_actor['tipo'], ['admin', 'colaborador'], true)) {
	status_header(403);
	echo '<!doctype html><meta charset="utf-8"><title>Sin acceso</title><p style="font-family:sans-serif">Tu usuario no tiene acceso al tablero. Pídele a Luis el rol «Colaborador AT».</p>';
	exit;
}
$u = wp_get_current_user();
$cfg = ['rest' => esc_url_raw(rest_url('at-tablero/v1/')), 'nonce' => wp_create_nonce('wp_rest'),
	'usuario' => ['key' => $at_tab_actor['key'], 'nombre' => $u->display_name, 'tipo' => $at_tab_actor['tipo']]];
$html = file_get_contents(__DIR__ . '/index.html');
$inyeccion = '<script>window.AT_TABLERO = ' . wp_json_encode($cfg) . ';</script>';
echo preg_replace('/<script/i', $inyeccion . '<script', $html, 1);
```

`tablero/.htaccess`:
```apacheconf
DirectoryIndex index.php
<Files "index.html">
  Require all denied
</Files>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `BASE=http://localhost:8093 bash tests/tablero/puerta-http.sh`
Expected: dos `ok`. Si el servidor embebido de PHP no aplica `.htaccess`, el router del sitio de prueba debe devolver
403 para `/tablero/index.html`: agregar esa regla a `<sitio>/router.php` (fuera del repo) y anotarlo en
`tests/tablero/LEEME.md`.

- [ ] **Step 5: Commit**

```bash
git add tablero/index.php tablero/.htaccess tests/tablero/puerta-http.sh tests/tablero/LEEME.md
git commit -m "feat(tablero): puerta con login de WordPress y nonce inyectado (AT-TAB-004)"
```

---

### Task 6: Interfaz — cliente REST, lógica pura y vista «Por persona»

**Files:**
- Create: `tablero/api.js`, `tablero/logica.js`
- Modify: `tablero/index.html` (zonas: líneas 420–460 del cliente v8, 492–497 `ASIG_AVATARS`, 567–660 carga y POST, 767–800 `cardInt`, 865–950 drag, 1100–1185 modal y guardado de internas, 1219–1235 `cambiarTab`, 1262–1365 usuarios y token)
- Test: `tests/tablero/logica.test.mjs`

**Interfaces:**
- Consumes: la REST de la Task 4 y `window.AT_TABLERO` de la Task 5.
- Produces (en `logica.js`, exportado como ESM y también como `window.ATLogica`):
  - `puedeEditar(tarjeta, usuario) → boolean`: replica `at_tab_puede('editar')` solo para la interfaz.
  - `agruparPorPersona(internas, clientes, personas, primeroKey = '') → [{persona, enCurso:[], enRevision:[], bloqueadas:[], hechas7:number}]`: `primeroKey` (la key de Luis) va primero, luego las personas y después los agentes.
  - `filtrarDecideLuis(internas) → []`
  - `mensajeDeError(status, codigo) → string`
- Produces (en `api.js`, como `window.ATApi`): `cargar()`, `guardar(tipo, dato)`, `subirAdjunto(atId, archivo)`, `borrarAdjunto(id)` y `urlAdjunto(id)`.

- [ ] **Step 1: Write the failing test**

```js
// node --test tests/tablero/logica.test.mjs
import test from 'node:test';
import assert from 'node:assert/strict';
import { puedeEditar, agruparPorPersona, filtrarDecideLuis, mensajeDeError } from '../../tablero/logica.js';

const ana = { key: 'wp:7', tipo: 'colaborador' };
const luis = { key: 'wp:1', tipo: 'admin' };
test('puedeEditar replica la matriz', () => {
  assert.equal(puedeEditar({ origen: 'manual', asignado: 'wp:7' }, ana), true);
  assert.equal(puedeEditar({ origen: 'manual', asignado: 'wp:1' }, ana), false);
  assert.equal(puedeEditar({ origen: 'ticket', asignado: 'agente:claude' }, luis), false);
  assert.equal(puedeEditar({ origen: 'manual', asignado: 'wp:7' }, luis), true);
});
test('agruparPorPersona: Luis primero, estados y hechas de 7 días', () => {
  const hoy = new Date().toISOString().slice(0, 10);
  const personas = [{ key: 'agente:claude', nombre: '🤖 Claude', tipo: 'agente' }, { key: 'wp:1', nombre: 'Luis', tipo: 'persona' }];
  const internas = [
    { at_id: 'a', asignado: 'wp:1', estado: 'progress' }, { at_id: 'b', asignado: 'agente:claude', estado: 'review' },
    { at_id: 'c', asignado: 'agente:claude', estado: 'blocked' }, { at_id: 'd', asignado: 'wp:1', estado: 'done', ultima: hoy },
  ];
  const g = agruparPorPersona(internas, [], personas, 'wp:1');
  assert.equal(g[0].persona.key, 'wp:1');
  assert.deepEqual(g[0].enCurso.map(t => t.at_id), ['a']);
  assert.equal(g[0].hechas7, 1);
  assert.deepEqual(g[1].enRevision.map(t => t.at_id), ['b']);
  assert.deepEqual(g[1].bloqueadas.map(t => t.at_id), ['c']);
});
test('filtrarDecideLuis', () => {
  assert.deepEqual(filtrarDecideLuis([{ at_id: 'x', decide_luis: 1, estado: 'todo' }, { at_id: 'y', decide_luis: 0 }, { at_id: 'z', decide_luis: 1, estado: 'done' }]).map(t => t.at_id), ['x']);
});
test('mensajeDeError distingue nonce vencido (Review Focus 1)', () => {
  assert.match(mensajeDeError(403, 'rest_cookie_invalid_nonce'), /recarga/i);
  assert.match(mensajeDeError(403, 'at_tab_ajena'), /tus propias/i);
  assert.match(mensajeDeError(409, 'at_tab_ticket'), /git/i);
  assert.match(mensajeDeError(401, 'rest_not_logged_in'), /sesión/i);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `node --test tests/tablero/logica.test.mjs`
Expected: FAIL, `Cannot find module .../tablero/logica.js`.

- [ ] **Step 3: Write `tablero/logica.js`**

```js
// Lógica pura del tablero v9 (sin DOM). Se carga como módulo en index.html y en las pruebas de node.
export function puedeEditar(t, u) {
  if (!t || !u || t.origen === 'ticket') return false;
  if (u.tipo === 'admin') return true;
  return u.tipo === 'colaborador' && !!u.key && t.asignado === u.key;
}
export function agruparPorPersona(internas, clientes, personas, primeroKey = '') {
  const hace7 = new Date(Date.now() - 7 * 864e5).toISOString().slice(0, 10);
  const orden = [...personas].sort((a, b) => (a.key === primeroKey ? -1 : b.key === primeroKey ? 1 : (a.tipo === b.tipo ? 0 : a.tipo === 'persona' ? -1 : 1)));
  const todas = [...internas, ...clientes];
  return orden.map(persona => {
    const suyas = todas.filter(t => t.asignado === persona.key);
    return {
      persona,
      enCurso: suyas.filter(t => t.estado === 'progress' || t.estado === 'todo'),
      enRevision: suyas.filter(t => t.estado === 'review'),
      bloqueadas: suyas.filter(t => t.estado === 'blocked' || t.estado === 'wait'),
      hechas7: suyas.filter(t => t.estado === 'done' && (t.ultima || '') >= hace7).length,
    };
  });
}
export function filtrarDecideLuis(internas) {
  return internas.filter(t => Number(t.decide_luis) === 1 && t.estado !== 'done');
}
export function mensajeDeError(status, codigo) {
  if (codigo === 'rest_cookie_invalid_nonce') return 'La sesión del tablero venció: recarga la página.';
  if (status === 401) return 'Inicia sesión en WordPress para usar el tablero.';
  if (codigo === 'at_tab_ticket') return 'Esta tarjeta viene de un ticket: se cambia en git, no aquí.';
  if (codigo === 'at_tab_ajena' || codigo === 'at_tab_reasignar') return 'Solo puedes cambiar tus propias tarjetas.';
  if (status === 403) return 'No tienes permiso para esto.';
  return 'No se pudo guardar. Intenta de nuevo.';
}
if (typeof window !== 'undefined') window.ATLogica = { puedeEditar, agruparPorPersona, filtrarDecideLuis, mensajeDeError };
```

- [ ] **Step 4: Run test to verify it passes**

Run: `node --test tests/tablero/logica.test.mjs`
Expected: 4 pruebas PASS.

- [ ] **Step 5: Write `tablero/api.js`**

```js
// Cliente REST del tablero v9: sesión de WordPress + nonce inyectado por index.php.
(function () {
  const cfg = window.AT_TABLERO || {};
  async function pedir(metodo, ruta, cuerpo, esForm) {
    const h = { 'X-WP-Nonce': cfg.nonce || '' };
    if (cuerpo && !esForm) h['Content-Type'] = 'application/json';
    const r = await fetch(cfg.rest + ruta, { method: metodo, headers: h, credentials: 'same-origin', cache: 'no-store',
      body: cuerpo ? (esForm ? cuerpo : JSON.stringify(cuerpo)) : undefined });
    let j = null; try { j = await r.json(); } catch (e) {}
    if (!r.ok) return { ok: false, status: r.status, codigo: j && j.code, mensaje: window.ATLogica.mensajeDeError(r.status, j && j.code) };
    return { ok: true, data: j };
  }
  window.ATApi = {
    cargar: () => pedir('GET', 'tablero'),
    guardar: (tipo, dato) => pedir('POST', 'tarjeta', Object.assign({ tipo }, dato)),
    subirAdjunto: (atId, archivo) => { const f = new FormData(); f.append('at_id', atId); f.append('imagen', archivo); return pedir('POST', 'adjunto', f, true); },
    borrarAdjunto: (id) => pedir('DELETE', 'adjunto/' + encodeURIComponent(id)),
    urlAdjunto: (id) => cfg.rest + 'adjunto/' + encodeURIComponent(id) + '?_wpnonce=' + encodeURIComponent(cfg.nonce || ''),
    usuario: cfg.usuario || null,
  };
})();
```

- [ ] **Step 6: Editar `tablero/index.html`**

1. **Carga de scripts:** antes del `<script>` principal agregar
   `<script type="module" src="logica.js"></script>` y `<script src="api.js" defer></script>`. Mover el
   arranque (`boot`) al evento `load` para que `window.ATLogica` y `window.ATApi` ya existan.
2. **Quitar el cliente v8:** borrar `STORAGE_KEY_TOKEN`, `API_URL`, `API_TOKEN`, `API_STATE`, `cargarToken`,
   `guardarToken`, `apiHeaders`, `escrituraRemotaDisponible`, `actualizarTokenEstado`, `abrirModalToken` y
   `probarToken`, junto con el botón 🔑 y su modal. Quitar también `abrirModalUsuarios`, `renderUsuarios`,
   `agregarUsuario`, `quitarUsuario`, `descargarHtpasswd` y su botón: las cuentas ahora son de WordPress.
3. **Responsables dinámicos:** reemplazar la constante `ASIG_AVATARS` por `let PERSONAS = [];` y una función:
   ```js
   function avatarDe(key) { const p = PERSONAS.find(x => x.key === key); return p ? { cls: p.tipo === 'agente' ? 'avatar-Claude' : 'avatar-Luis', initials: p.iniciales, nombre: p.nombre } : { cls: 'avatar-Luis', initials: '—', nombre: 'Sin asignar' }; }
   ```
   Cambiar cada uso de `ASIG_AVATARS[t.asignadoA]` por `avatarDe(t.asignado)`. El `<select>` de responsable del modal
   se arma con `PERSONAS`, y queda deshabilitado si `ATApi.usuario.tipo !== 'admin'`.
4. **Carga:** `syncDesdeAPI()` pasa a:
   ```js
   async function syncDesdeAPI() {
     const r = await ATApi.cargar();
     if (!r.ok) { toast(r.mensaje); return false; }
     CLIENTES = r.data.clientes.map(c => ({ ...c, id: c.at_id })); INTERNAS = r.data.internas.map(t => ({ ...t, id: t.at_id }));
     PERSONAS = r.data.personas; ULTIMA_SYNC = r.data.ultima_sync; renderCabeceraUsuario(); renderActual(); return true;
   }
   ```
   Ya no se persiste nada en `localStorage`, salvo el tema claro/oscuro.
5. **Guardado:** en `guardarInt` y `guardarCli`, reemplazar `postEditAPI(tipo, dato)` por
   `ATApi.guardar(tipo, dato)`. Si `!r.ok`, mostrar `toast(r.mensaje)` y recargar con `syncDesdeAPI()`. El
   campo enviado es `asignado`, no `asignadoA`.
6. **Tarjetas:** en `cardInt` y `cardCli`, si `t.origen === 'ticket'`, agregar la insignia
   `<span class="text-[10px] px-1.5 rounded bg-slate-200 text-slate-700">🤖 ${esc(t.ticket_id)}</span>`; si
   `t.decide_luis == 1`, agregar `⚖️ Decide Luis`. Atributo `draggable` = `ATLogica.puedeEditar(t, ATApi.usuario)`.
   En `bindDropTargets`, ignorar el soltado de una tarjeta que no se puede editar.
7. **Modal de interna:** si `!ATLogica.puedeEditar(t, ATApi.usuario)`, los campos van en solo lectura, el botón
   Guardar se oculta y, si es ticket, aparece el enlace `t.ticket_ref` («Ver ticket/PR»).
8. **Nueva pestaña «Por persona»:** junto a las pestañas existentes, un botón `data-tab="personas"`. En
   `cambiarTab` agregar el caso `personas` → `renderPersonas()`:
   ```js
   function renderPersonas() {
     const grupos = ATLogica.agruparPorPersona(INTERNAS, CLIENTES, PERSONAS, (PERSONAS.find(p => p.tipo === 'persona' && ATApi.usuario && ATApi.usuario.tipo === 'admin' && p.key === ATApi.usuario.key) || {}).key || '');
     document.getElementById('vistaPersonas').innerHTML = grupos.map(g => `
       <section class="min-w-[260px] bg-white dark:bg-slate-800 rounded-lg p-3">
         <h3 class="font-semibold mb-2">${esc(g.persona.nombre)} <span class="text-xs text-at-gray">· ${g.hechas7} hechas en 7 días</span></h3>
         ${[['En curso', g.enCurso], ['En revisión', g.enRevision], ['Bloqueado / esperando', g.bloqueadas]].map(([tit, lista]) => `
           <p class="text-[10px] uppercase tracking-wider text-at-gray mt-2">${tit} (${lista.length})</p>
           ${lista.map(t => `<button class="block w-full text-left text-sm py-1 hover:underline" onclick="abrirModalInt('${safeId(t.at_id)}')">${esc(t.titulo || t.nombre)}${Number(t.decide_luis) === 1 ? ' ⚖️' : ''}${t.origen === 'ticket' ? ' 🤖' : ''}</button>`).join('')}`).join('')}
       </section>`).join('');
   }
   ```
   con un contenedor `<div id="vistaPersonas" class="flex gap-4 overflow-x-auto p-4 hidden"></div>`.
9. **Filtro ⚖️:** un botón «⚖️ Decide Luis» en la barra de filtros. Activa `filtroDecide = true`, y
   `renderInternas` usa `ATLogica.filtrarDecideLuis(INTERNAS)` cuando está activo.
10. **Cabecera:** `renderCabeceraUsuario()` muestra `ATApi.usuario.nombre`, el rol («Administrador» o
    «Colaborador AT») y «Tickets sincronizados: <fecha local de ULTIMA_SYNC o "nunca">» (README §12).
11. **Adjuntos:** `cargarAdjuntoBlob(id)` usa `fetch(ATApi.urlAdjunto(id), {credentials:'same-origin'})`, y
    `subirAdjuntoAPI` y el borrado usan `ATApi.subirAdjunto` y `ATApi.borrarAdjunto`.

- [ ] **Step 7: Verificar en el navegador**

Con el sitio de prueba arrancado (Task 5), entrar con tres usuarios de prueba: admin, colaborador y uno sin rol.
Revisar en escritorio y en móvil (375×812):
- el sin rol ve «Sin acceso»;
- el colaborador ve todo, pero solo arrastra y edita las suyas, y el `<select>` de responsable está deshabilitado;
- la vista «Por persona» agrupa bien;
- las tarjetas 🤖 no se arrastran y muestran el enlace;
- «⚖️ Decide Luis» filtra;
- no hay errores en la consola.

Registrar las capturas como evidencia en el scratch de Aseo.

- [ ] **Step 8: Commit**

```bash
git add tablero/api.js tablero/logica.js tablero/index.html tests/tablero/logica.test.mjs
git commit -m "feat(tablero): interfaz v9 con sesión de WordPress, vista Por persona y tarjetas de ticket (AT-TAB-004)"
```

---

### Task 7: Sync de tickets `tools/tablero_sync.py`

**Files:**
- Create: `tools/tablero_sync.py`
- Test: `tests/tablero/test_tablero_sync.py`, `tests/tablero/fixtures/AT-X-001.yaml`, `tests/tablero/fixtures/AT-X-002.yaml`

**Interfaces:**
- Consumes: `POST ?rest_route=/at-tablero/v1/sync` (Task 4).
- Produces:
  - `mapear_estado(status: str) -> tuple[str, bool]` → (estado del tablero, ¿es portón humano?)
  - `ticket_a_tarjetas(ticket: dict, ref: str, admin_key: str) -> list[dict]`
  - `elegir_mas_reciente(versiones: list[tuple[str, dict]]) -> dict` (ISO-8601, ticket)
  - `leer_tickets(repo: str, refs: list[str]) -> dict[str, tuple[str, dict, str]]` → por `ticket_id`: (fecha del commit, ticket, ref)
  - `construir_lote(tickets: dict, admin_key: str) -> list[dict]`
  - CLI: `python tools/tablero_sync.py --repo <ruta> --admin-key wp:<id> [--dry-run] [--url <base>]`, con las
    variables `AT_TABLERO_SYNC_USER` y `AT_TABLERO_SYNC_PASS` (contraseña de aplicación) para enviar.

- [ ] **Step 1: Write the failing test**

```python
# python -m unittest tests/tablero/test_tablero_sync.py -v
import sys, unittest, pathlib
sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[2] / 'tools'))
import tablero_sync as ts

class Mapeo(unittest.TestCase):
    def test_estados(self):
        self.assertEqual(ts.mapear_estado('READY_FOR_EXECUTION'), ('todo', False))
        self.assertEqual(ts.mapear_estado('IN_PROGRESS'), ('progress', False))
        self.assertEqual(ts.mapear_estado('CHANGES_REQUESTED_FIXED_PENDING_REREVIEW'), ('review', False))
        self.assertEqual(ts.mapear_estado('AWAITING_HUMAN_GATE'), ('wait', True))
        self.assertEqual(ts.mapear_estado('DONE'), ('done', False))
        self.assertEqual(ts.mapear_estado('ALGO_NUEVO'), ('backlog', False))

class Tarjetas(unittest.TestCase):
    def test_ticket_y_decisiones(self):
        t = {'ticket_id': 'AT-X-001', 'title': 'Hacer X', 'status': 'PEER_REVIEW_PENDING', 'priority': 'P1',
             'ownership': {'current_owner': 'claude'}, 'reviewer': 'codex', 'portfolio': 'internal:automatizatech',
             'decisiones_luis': [{'id': 'D1', 'pregunta': '¿Mergear?', 'estado': 'pendiente'},
                                 {'id': 'D2', 'pregunta': '¿Otra?', 'estado': 'resuelta'}]}
        c = ts.ticket_a_tarjetas(t, 'origin/claude/x', 'wp:1')
        self.assertEqual(c[0]['at_id'], 'T-AT-X-001')
        self.assertEqual(c[0]['asignado'], 'agente:claude')
        self.assertEqual(c[0]['estado'], 'review')
        self.assertEqual([x['at_id'] for x in c[1:]], ['T-AT-X-001-D1', 'T-AT-X-001-D2'])
        self.assertEqual((c[1]['decide_luis'], c[1]['asignado'], c[1]['estado']), (1, 'wp:1', 'todo'))
        self.assertEqual(c[2]['estado'], 'done')

    def test_porton_humano_crea_decision(self):
        t = {'ticket_id': 'AT-X-002', 'title': 'Y', 'status': 'WAITING_HUMAN_GATE', 'ownership': {'current_owner': 'codex'}}
        c = ts.ticket_a_tarjetas(t, 'origin/main', 'wp:1')
        self.assertEqual([x['at_id'] for x in c], ['T-AT-X-002', 'T-AT-X-002-D0'])

class Versiones(unittest.TestCase):
    def test_gana_la_mas_reciente(self):
        v = [('2026-10-09T10:00:00-03:00', {'status': 'IN_PROGRESS'}), ('2026-10-10T09:00:00-03:00', {'status': 'DONE'})]
        self.assertEqual(ts.elegir_mas_reciente(v)['status'], 'DONE')

class Lote(unittest.TestCase):
    def test_idempotente_y_ordenado(self):
        tickets = {'AT-X-001': ('2026-10-10T09:00:00-03:00', {'ticket_id': 'AT-X-001', 'title': 'A', 'status': 'DONE', 'ownership': {'current_owner': 'claude'}}, 'origin/main')}
        self.assertEqual(ts.construir_lote(tickets, 'wp:1'), ts.construir_lote(tickets, 'wp:1'))

if __name__ == '__main__':
    unittest.main()
```

- [ ] **Step 2: Run test to verify it fails**

Run: `python -m unittest tests/tablero/test_tablero_sync.py -v`
Expected: `ModuleNotFoundError: No module named 'tablero_sync'`.

- [ ] **Step 3: Write minimal implementation**

```python
"""Sincroniza los tickets de Docs/ORCHESTRATION/ con el tablero AT v9 (AT-TAB-004, spec §5).

Lee origin/main y las ramas de PR abiertos con git (sin checkout), arma el lote y lo publica en
?rest_route=/at-tablero/v1/sync con la contraseña de aplicación del usuario de servicio
(variables AT_TABLERO_SYNC_USER y AT_TABLERO_SYNC_PASS). --dry-run solo muestra el lote.
"""
import argparse, base64, json, os, subprocess, sys, urllib.request
import yaml

ESTADOS = {'READY': 'todo', 'READY_FOR_EXECUTION': 'todo', 'ASSIGNED': 'todo', 'IN_PROGRESS': 'progress',
           'PEER_REVIEW_PENDING': 'review', 'PEER_REVIEW_PASSED': 'review', 'BLOCKED': 'blocked', 'DONE': 'done'}
PORTONES = ('AWAITING_HUMAN_GATE', 'WAITING_HUMAN_GATE')
AGENTES = {'claude': 'agente:claude', 'codex': 'agente:codex', 'opencode': 'agente:opencode'}

def mapear_estado(status):
    s = str(status or '').upper()
    if s in PORTONES:
        return 'wait', True
    if s.startswith('CHANGES_REQUESTED'):
        return 'review', False
    return ESTADOS.get(s, 'backlog'), False

def ticket_a_tarjetas(t, ref, admin_key):
    tid = str(t.get('ticket_id', '')).strip()
    estado, porton = mapear_estado(t.get('status'))
    owner = str((t.get('ownership') or {}).get('current_owner') or t.get('owner') or '').lower()
    notas = f"Estado del ticket: {t.get('status')} · Revisor: {t.get('reviewer', '—')} · Cartera: {t.get('portfolio', '—')} · Fuente: {ref}"
    tarjetas = [{'at_id': f'T-{tid}', 'titulo': f"{tid} · {t.get('title', '')}"[:255], 'estado': estado,
                 'prioridad': t.get('priority', 'P2'), 'asignado': AGENTES.get(owner, ''), 'ticket_id': tid,
                 'ticket_ref': ref, 'decide_luis': 0, 'notas': notas}]
    decisiones = list(t.get('decisiones_luis') or [])
    if porton and not any(str(d.get('estado')) == 'pendiente' for d in decisiones):
        decisiones.insert(0, {'id': 'D0', 'pregunta': f"Portón humano: {t.get('status_note') or 'revisar el ticket'}", 'estado': 'pendiente'})
    for d in decisiones:
        tarjetas.append({'at_id': f"T-{tid}-{d.get('id')}", 'titulo': f"⚖️ {d.get('pregunta', '')}"[:255],
                         'estado': 'done' if str(d.get('estado')) == 'resuelta' else 'todo', 'prioridad': 'P1',
                         'asignado': admin_key, 'ticket_id': tid, 'ticket_ref': ref, 'decide_luis': 1, 'notas': f'Decisión de {tid}'})
    return tarjetas

def elegir_mas_reciente(versiones):
    return max(versiones, key=lambda v: v[0])[1]

def _git(repo, *args):
    return subprocess.run(['git', '-C', repo, *args], capture_output=True, text=True, encoding='utf-8', check=True).stdout

def refs_de_pr(repo):
    """Devuelve (refs, completo). completo=False si gh no respondió: entonces el sync es parcial."""
    try:
        salida = subprocess.run(['gh', 'pr', 'list', '--state', 'open', '--json', 'headRefName'], cwd=repo,
                                capture_output=True, text=True, check=True, timeout=60).stdout
        return [f"origin/{p['headRefName']}" for p in json.loads(salida)], True
    except Exception:
        return [], False

def leer_tickets(repo, refs):
    versiones = {}
    for ref in refs:
        try:
            nombres = _git(repo, 'ls-tree', '--name-only', ref, 'Docs/ORCHESTRATION/').split()
        except subprocess.CalledProcessError:
            continue
        for ruta in nombres:
            if not (ruta.split('/')[-1].startswith('AT-') and ruta.endswith('.yaml')):
                continue
            fecha = _git(repo, 'log', '-1', '--format=%cI', ref, '--', ruta).strip()
            t = yaml.safe_load(_git(repo, 'show', f'{ref}:{ruta}')) or {}
            if t.get('ticket_id'):
                versiones.setdefault(t['ticket_id'], []).append((fecha, t, ref))
    return {tid: max(vs, key=lambda v: v[0]) for tid, vs in versiones.items()}

def construir_lote(tickets, admin_key):
    lote = []
    for tid in sorted(tickets):
        _fecha, t, ref = tickets[tid]
        lote.extend(ticket_a_tarjetas(t, ref, admin_key))
    return lote

def enviar(url, lote, parcial):
    usuario, clave = os.environ.get('AT_TABLERO_SYNC_USER'), os.environ.get('AT_TABLERO_SYNC_PASS')
    if not usuario or not clave:
        sys.exit('Faltan AT_TABLERO_SYNC_USER/AT_TABLERO_SYNC_PASS (contraseña de aplicación por variable de entorno).')
    cuerpo = json.dumps({'tarjetas': lote, 'parcial': parcial}).encode('utf-8')
    auth = base64.b64encode(f'{usuario}:{clave}'.encode()).decode()
    req = urllib.request.Request(url.rstrip('/') + '/?rest_route=/at-tablero/v1/sync', data=cuerpo, method='POST',
                                 headers={'Content-Type': 'application/json', 'Authorization': f'Basic {auth}'})
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.loads(r.read().decode('utf-8'))

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--repo', default='.')
    ap.add_argument('--admin-key', required=True, help='wp:<id de Luis>')
    ap.add_argument('--url', default='https://automatizatech.cl')
    ap.add_argument('--dry-run', action='store_true')
    a = ap.parse_args()
    prs, completo = refs_de_pr(a.repo)
    lote = construir_lote(leer_tickets(a.repo, ['origin/main', *prs]), a.admin_key)
    parcial = not completo
    if a.dry_run:
        print(json.dumps({'parcial': parcial, 'tarjetas': lote}, ensure_ascii=False, indent=1))
        return
    if parcial:
        print('Aviso: GitHub no respondió; sync parcial (no se archiva nada).', file=sys.stderr)
    print(json.dumps(enviar(a.url, lote, parcial)))

if __name__ == '__main__':
    main()
```

- [ ] **Step 4: Run test to verify it passes**

Run: `python -m unittest tests/tablero/test_tablero_sync.py -v`
Expected: 5 pruebas OK.

- [ ] **Step 5: Prueba en seco contra el repo real**

Run: `python tools/tablero_sync.py --repo /c/wamp64/www/automatiza-tech --admin-key wp:1 --dry-run > <scratch>/lote.json`
Expected: JSON con `T-AT-INT-PROP-001` (desde la rama del PR #80, porque es más reciente) y los tickets de CumpleClick
desde `origin/main`. Revisar que ningún título traiga datos de clientes: el lote viaja a PROD, no al repo.
Registrar `lote.json` en Aseo.

- [ ] **Step 6: Commit**

```bash
git add tools/tablero_sync.py tests/tablero/test_tablero_sync.py
git commit -m "feat(tablero): sync de tickets de agentes con decisiones de Luis (AT-TAB-004)"
```

---

### Task 8: Documentación, método y ticket

**Files:**
- Modify: `Docs/ORCHESTRATION/README.md`: en el punto 12, agregar «Al crear o cambiar un ticket, el agente corre `python tools/tablero_sync.py --admin-key wp:<id de Luis>` (ver `tablero/CONTEXTO_TABLERO.md`)».
- Modify: `Docs/ORCHESTRATION/ticket.example.yaml`: agregar el bloque opcional `decisiones_luis: [{id: D1, pregunta: "...", estado: pendiente}]`, con un comentario.
- Create: `Docs/ORCHESTRATION/AT-TAB-004.yaml` (formato de AT-INT-PROP-001: dueño claude, revisor codex, criterios = las pruebas de las Tasks 1–7).
- Modify: `tablero/CONTEXTO_TABLERO.md`: reescribir como v9 (qué es, quién entra, cómo se agrega un colaborador, sync y despliegue), sin datos de clientes.
- Modify: `api-tablero.php` → aviso 410:
  ```php
  <?php
  http_response_code(410);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'API v8 retirada: usar ?rest_route=/at-tablero/v1/ con sesión de WordPress']);
  ```
- Modify: `CLAUDE.md` y `AGENTS.md`: un puntero de una línea al tablero v9 en «Orquestacion multiagente AT».

- [ ] **Step 1:** Hacer los cambios de arriba.
- [ ] **Step 2:** Correr todas las pruebas: `permisos-test.php`, los tres `*-wp-test.php`, `logica.test.mjs` y `test_tablero_sync.py`. Expected: todo OK.
- [ ] **Step 3: Commit**

```bash
git add Docs/ORCHESTRATION api-tablero.php tablero/CONTEXTO_TABLERO.md CLAUDE.md AGENTS.md
git commit -m "docs(tablero): v9 en el método, ticket AT-TAB-004 y API v8 retirada (AT-TAB-004)"
```

---

### Task 9: Revisión B, PR y despliegue (gated por Luis)

- [ ] **Step 1:** Abrir el PR hacia `main` con el resumen y las pruebas. Pedir a Codex la revisión B **acotada** al diff del PR, por el puente de solo lectura, y registrarla en `AT-TAB-004.yaml` y en `Shared-Tools/Actas/`.
- [ ] **Step 2:** **Pedir el ok de Luis para PROD.** Pedirle además que cree el usuario `agentes-at` (rol Suscriptor), genere su contraseña de aplicación en su perfil, la guarde con una etiqueta en el archivo de claves y diga su `user_id`.
- [ ] **Step 3 (con el ok):** por SSH (`Docs/ORCHESTRATION/CONEXIONES-Y-CREDENCIALES.md` §3.3):
  1. Respaldar `mysqldump` de las tres tablas `*omnichannel_at_*` y `tar` de `tablero/`, `api-tablero.php` y `wp-content/mu-plugins/` en `~/respaldos/tablero-v9-antes-<fecha>`.
  2. Subir `wp-content/mu-plugins/at-tablero.php` y `at-tablero/`.
  3. `php tools/tablero-migrar-v9.php --wp-load=<public_html>/wp-load.php --admin-id=<Luis> --servicio=agentes-at` (subir el CLI fuera de `public_html` o borrarlo después).
  4. Subir `tablero/index.php`, `api.js`, `logica.js`, `index.html` y `.htaccess` (este último reemplaza el Basic Auth: respaldar antes el actual).
  5. Subir `api-tablero.php` (410).
- [ ] **Step 4:** Verificación desde afuera:
  - `/tablero/` sin sesión → 302 al login;
  - `/tablero/index.html` → 403;
  - `?rest_route=/at-tablero/v1/tablero` sin sesión → 401;
  - `api-tablero.php` → 410;
  - Luis entra y ve el tablero;
  - primer sync real con la contraseña de aplicación pasada por variable de entorno.
- [ ] **Step 5:** Rollback, si algo falla: restaurar los archivos y las tablas del respaldo y quitar el `mu-plugin`.
- [ ] **Step 6:** Cerrar Aseo de las tareas (`audit`, preguntar a Luis por los temporales, `decide`, `finish`). Actualizar la memoria (Engram, bóveda y `MEMORY.md`) y entregar la lista FTP a Luis.
