# Notas y versiones de los entregables — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el cliente vea cada versión de un entregable en una página propia y deje notas (texto + 0 a 3 imágenes), que Luis
las responda desde la ficha del CRM, y que todo quede en una línea de tiempo por entregable.

**Architecture:** Módulo nuevo `inc/entregables/` con el mismo patrón que `inc/plan-trabajo/` (funciones puras con pruebas,
datos con tablas propias, envío, página pública, panel admin con `admin_post_*`). Se carga desde `inc/admin-proposals.php`.
`crm-ai-completo.php` solo recibe tres llamadas (pestaña, resumen del portal, marca en la lista). `functions.php` no se toca.

**Tech Stack:** PHP 8.3+/WordPress (tema `automatiza-tech`), MySQL/MariaDB (`dbDelta`), `wp_mail` con el SMTP de Hostinger,
`WP_Image_Editor` (GD/Imagick) para reescribir imágenes. Pruebas: scripts PHP con `ok()`/`fin()` como `tests/plan/`.

**Spec:** `Docs/superpowers/specs/2026-10-05-entregables-notas-design.md`

## Global Constraints

- Prefijo de funciones: `at_en_`. Tablas: `{prefix}at_entregables`, `{prefix}at_entregable_versiones`, `{prefix}at_entregable_notas`. Opción de esquema: `at_en_db_version` = `'1'`.
- Código público: `/^[A-Za-z0-9]{12}$/`, generado con `wp_generate_password(12, false)`; se compara exacto con `hash_equals` (la columna es `_ci`).
- Página pública: `ver-entregable.php?id=<código>` en la raíz del sitio; `noindex, nofollow`; `nocache_headers()`; `DONOTCACHEPAGE`.
- Token del formulario: `substr(hash_hmac('sha256', 'at-en-nota|' . $codigo . '|' . $dia, wp_salt('nonce')), 0, 24)`, válido el día de emisión y el siguiente (`$dia = intdiv(time(), 86400)`).
- Límite: **10 notas por hora** por entregable y por IP (transient con la IP hasheada).
- Nota: texto de **1 a 3.000** caracteres (`mb_strlen`), nombre de **1 a 80**. Se guarda tal cual (sin `sanitize_*`, solo sin caracteres de control) y siempre se escapa al mostrar.
- Imágenes: **opcionales, 0 a 3 por nota**, **5 MB** cada una, solo **JPG y PNG** (contenido real), lado ≤ 12.000 px antes de procesar; se reescriben con `wp_get_image_editor` y lado máximo **2.000 px**; nombre `<24 minúsculas/dígitos>.jpg|png`; carpeta `wp-content/uploads/at-entregables/<id>/` con `.htaccess` `Require all denied` + `index.php` vacío.
- Mensaje de Luis en la versión: máximo **6.000** caracteres. Respuesta de AT: mismas reglas que una nota (1–3.000).
- Correos: desde `SMTP_USER` (o `contacto@automatizatech.cl`), `Content-Type: text/html; charset=UTF-8`, `From: Automatiza Tech <…>`. Al cliente: `Reply-To: at_cc_correo_avisos()` + `at_cc_cabecera_copia($para)` + `Bcc: lgonzalez@automatizatech.cl` (sin duplicar). Ningún enlace a `easypanel`.
- Diseño de correo: el del correo a Orly del 05-oct (encabezado `linear-gradient(135deg,#1e3a8a,#06d6a0)`, logo `AT_CC_LOGO` o `home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png')`, botones `border-radius:25px`).
- Admin: toda acción `admin_post_at_en_*` exige `manage_options` + nonce `at_en_<id entregable>` (activar: `at_en_activar_<detalle_id>`), y vuelve con `wp_safe_redirect(at_en_url_ficha(...))` + `exit`.
- Repo público: sin secretos, sin describir fallos explotables en commits.
- Textos visibles en español de Chile. Commits con `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Código ajeno o adivinado:** `?id=` con un código que existe pero con otras mayúsculas, o de 11/13 caracteres → misma página «no disponible» (404), sin filtrar si existe. Pinned en Task 6 (`vista-wp-test.php`).
2. **Imagen que no es imagen:** un `.jpg` que en realidad es PHP o HTML, o un PNG de 12.001 px de lado → la nota no se guarda y no queda ningún archivo. Pinned en Task 4 (`imagenes-wp-test.php`).
3. **Doble clic en «Nueva versión» o en «Enviar»:** dos versiones con el mismo número o dos correos iguales → índice único `(entregable_id, numero)` + transacción; enviar dos veces la misma versión por correo devuelve `ya_enviada`. Pinned en Tasks 3 y 5.
4. **Nota escrita con emoji, tildes o `<script>`:** se cuenta bien el largo (3.000 con emoji pasa, 3.001 no), y en la página, el panel y los correos se ve como texto. Pinned en Tasks 1, 2 y 6.
5. **Entregable cerrado o sin versiones:** el formulario no aparece y un POST directo se rechaza con `cerrado`; sin versiones la página dice «no disponible». Pinned en Task 6.

---

## Mapa de archivos

| Archivo | Responsabilidad |
|---|---|
| `wp-content/themes/automatiza-tech/inc/entregables/cargar.php` | Carga el módulo (orden de `require_once`). |
| `…/entregables/puras.php` | Funciones sin WordPress: códigos, enlaces, token, validación de nota e imagen, mensaje → HTML, textos de WhatsApp, mensajes. |
| `…/entregables/plantillas.php` | HTML y asunto de los tres correos (puro). |
| `…/entregables/datos.php` | Migración, lecturas y escrituras de las tres tablas, historial del CRM. |
| `…/entregables/imagenes.php` | Validar, reescribir, guardar, servir y borrar imágenes. |
| `…/entregables/envio.php` | Envío de la versión, prueba a Luis, aviso de nota, aviso de respuesta, enlaces de WhatsApp. |
| `…/entregables/vista.php` | HTML de la página pública y la acción `at_en_nota` (cliente). |
| `…/entregables/panel.php` | Pestaña «📦 Entregables», resumen del portal, marca de la lista y acciones admin. |
| `ver-entregable.php` (raíz) | Entrada pública: página o imagen. |
| `wp-content/themes/automatiza-tech/inc/admin-proposals.php:92` | Una línea: `require_once __DIR__ . '/entregables/cargar.php';` |
| `wp-content/mu-plugins/crm-ai-completo.php` | Botón y contenido de la pestaña; resumen en `$render_public_item`; marca en `column_estado`. |
| `tests/entregables/*` | Pruebas puras y con WordPress, arnés propio. |
| `Docs/METODO_AT/ENTREGABLES-NOTAS.md` | Guía de uso, despliegue y reversa. |

---

### Task 0: Arnés de pruebas apuntando a esta rama

**Files:**
- Create: `tests/entregables/wp-bootstrap.php`
- Create: `tests/entregables/correr.sh`

**Interfaces:**
- Produces: `ok()`, `fin()`, `exigir(string ...$f)`, `en_admin_id(): int`; variable de entorno `AT_WP_LOAD`; `correr.sh [filtro]`.

- [ ] **Step 1: Apuntar el sitio local `wp-local-plan` a este worktree**

El sitio de prueba vive en el scratchpad (`$SCR/wp-local-plan`, donde `SCR=C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad`). Hoy sus uniones apuntan a `.worktrees/agenda-limite`. Reemplazarlas (PowerShell, una por una; `rmdir` en una unión borra solo la unión):

```powershell
$SCR = "C:\Users\luis_\AppData\Local\Temp\claude\C--wamp64-www-automatiza-tech\be929449-8523-4c30-a49a-56a73bb785ab\scratchpad\wp-local-plan"
$WT  = "C:\wamp64\www\automatiza-tech\.worktrees\entregables-notas"
cmd /c rmdir "$SCR\wp-content\themes\automatiza-tech"
cmd /c mklink /J "$SCR\wp-content\themes\automatiza-tech" "$WT\wp-content\themes\automatiza-tech"
cmd /c rmdir "$SCR\wp-content\mu-plugins"
cmd /c mklink /J "$SCR\wp-content\mu-plugins" "$WT\wp-content\mu-plugins"
cmd /c rmdir "$SCR\contracts"
cmd /c mklink /J "$SCR\contracts" "$WT\contracts"
cmd /c rmdir "$SCR\Docs"
cmd /c mklink /J "$SCR\Docs" "$WT\Docs"
cmd /c "dir /AL $SCR $SCR\wp-content $SCR\wp-content\themes" | Select-String JUNCTION
```

Expected: las cuatro uniones muestran `.worktrees\entregables-notas`.

- [ ] **Step 2: Crear `tests/entregables/wp-bootstrap.php`**

```php
<?php
// Carga el WordPress local de prueba (sitio wp-local-plan del scratchpad, uniones a este worktree).
// Uso: AT_WP_LOAD=<ruta a wp-load.php> php tests/entregables/<prueba>-wp-test.php
$at_en_wp_load = getenv('AT_WP_LOAD');
if (!$at_en_wp_load || !is_file($at_en_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8093';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
define('WP_USE_THEMES', false);
require $at_en_wp_load;
// Las pruebas nunca salen a la red ni mandan correos reales.
add_filter('pre_http_request', function ($pre, $args, $url) {
	return new WP_Error('prueba', 'HTTP bloqueado en la prueba: ' . $url);
}, 1, 3);
$GLOBALS['en_correos'] = [];
add_filter('pre_wp_mail', function ($nulo, $atts) {
	if ($nulo !== null) { // otra prueba ya decidió (p. ej. simular una falla con prioridad 0)
		return $nulo;
	}
	$GLOBALS['en_correos'][] = $atts;
	return true;
}, 1, 2);
$GLOBALS['fallas'] = 0;
function ok($cond, $msg) { if ($cond) { echo "ok   $msg\n"; } else { $GLOBALS['fallas']++; echo "FALLA $msg\n"; } }
function fin() { echo $GLOBALS['fallas'] ? "\n{$GLOBALS['fallas']} FALLAS\n" : "\nTODO OK\n"; exit($GLOBALS['fallas'] ? 1 : 0); }
/** Corta con una falla legible si falta una función (paso rojo). */
function exigir(string ...$funciones): void {
	foreach ($funciones as $f) {
		if (!function_exists($f)) {
			echo "FALLA falta la función {$f}()\n\n1 FALLAS\n";
			exit(1);
		}
	}
}
/** Primer administrador del sitio local. */
function en_admin_id(): int {
	$a = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
	$id = (int) ($a[0] ?? 0);
	if ($id <= 0) {
		fwrite(STDERR, "No hay administrador en el WordPress local de prueba.\n");
		exit(2);
	}
	return $id;
}
```

- [ ] **Step 3: Crear `tests/entregables/correr.sh`**

```bash
#!/bin/bash
# Corre las pruebas del módulo entregables. Uso: bash tests/entregables/correr.sh [filtro]
cd "$(dirname "$0")/../.."
PHP=${PHP:-/c/wamp64/bin/php/php8.4.15/php.exe}
SCR=C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad
export AT_WP_LOAD=${AT_WP_LOAD:-$SCR/wp-local-plan/wp-load.php}
res() { local n="$1"; shift; local o; o=$("$@" 2>&1); printf '%-34s %4s ok | %4s FALLA | %s\n' "$n" "$(echo "$o" | grep -c '^ok')" "$(echo "$o" | grep -c '^FALLA')" "$(echo "$o" | tail -1)"; }
for f in tests/entregables/*-test.php; do
	case "$f" in *"$1"*) res "$(basename "$f")" "$PHP" "$f";; esac
done
```

- [ ] **Step 4: Línea base: las suites existentes siguen en verde con las uniones nuevas**

Run: `bash "$SCR/correr_todo.sh"` no sirve (apunta a otro worktree). Correr directo:
`cd .worktrees/entregables-notas && for f in tests/plan/*-wp-test.php tests/followup/*-wp-test.php; do AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe $f | tail -1; done`
Expected: cada línea `TODO OK`. Si alguna falla, anotar cuál antes de seguir (es la línea base, no culpa de esta rama).

- [ ] **Step 5: Commit**

```bash
git add tests/entregables/wp-bootstrap.php tests/entregables/correr.sh
git commit -m "test(entregables): arnés de pruebas del módulo"
```

---

### Task 1: Funciones puras (`puras.php`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/entregables/puras.php`
- Test: `tests/entregables/puras-test.php`

**Interfaces:**
- Consumes: `at_pt_telefono_wa(string): string` de `inc/plan-trabajo/puras.php` (ya existe).
- Produces:
  - `at_en_codigo_valido(string $c): bool`
  - `at_en_url_pagina(string $base, string $codigo): string`
  - `at_en_url_imagen(string $base, string $codigo, string $nombre): string`
  - `at_en_token(string $codigo, int $dia, string $sal): string`
  - `at_en_token_valido(string $token, string $codigo, int $hoy, string $sal): bool`
  - `at_en_limpiar_texto(string $t): string`
  - `at_en_validar_nota(string $nombre, string $texto): array` → `['ok'=>bool,'error'=>string,'nombre'=>string,'texto'=>string]`
  - `at_en_url_version_valida(string $u): string`
  - `at_en_validar_mensaje(string $m): array` → `['ok'=>bool,'error'=>string,'mensaje'=>string]`
  - `at_en_nombre_imagen_valido(string $n): bool`
  - `at_en_revisar_imagen(array $i): string` (claves `indice,error,size,tipo,ancho,alto`; `''` = sirve)
  - `at_en_mensaje_html(string $m): string`
  - `at_en_extracto(string $t, int $max = 400): string`
  - `at_en_texto_whatsapp_version(string $nombre, int $n, string $titulo, string $url): string`
  - `at_en_texto_whatsapp_respuesta(string $nombre, int $n, string $titulo, string $url): string`
  - `at_en_url_wa(string $telefono, string $texto): string`
  - `at_en_mensajes(): array` (clave → texto)
  - constantes `AT_EN_MAX_TEXTO=3000`, `AT_EN_MAX_NOMBRE=80`, `AT_EN_MAX_MENSAJE=6000`, `AT_EN_MAX_IMAGENES=3`, `AT_EN_MAX_BYTES=5242880`, `AT_EN_MAX_LADO_ENTRADA=12000`, `AT_EN_LADO_FINAL=2000`, `AT_EN_NOTAS_POR_HORA=10`

- [ ] **Step 1: Escribir la prueba que falla** — `tests/entregables/puras-test.php`

```php
<?php
// Correr: php tests/entregables/puras-test.php   (sin WordPress)
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/entregables/puras.php';
$fallas = 0;
function ok($c, $m) { global $fallas; if ($c) { echo "ok   $m\n"; } else { $fallas++; echo "FALLA $m\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

// Código y enlaces
ok(at_en_codigo_valido('Ab3dE5fG7hJ9') && !at_en_codigo_valido('Ab3dE5fG7hJ') && !at_en_codigo_valido('Ab3dE5fG7hJ90') && !at_en_codigo_valido('Ab3dE5fG7h/9') && !at_en_codigo_valido(''), 'código: 12 letras o números exactos');
ok(at_en_url_pagina('https://automatizatech.cl/', 'Ab3dE5fG7hJ9') === 'https://automatizatech.cl/ver-entregable.php?id=Ab3dE5fG7hJ9', 'enlace a la página sin barra doble');
ok(at_en_url_pagina('', 'Ab3dE5fG7hJ9') === '' && at_en_url_pagina('https://x.cl', 'malo') === '', 'sin sitio o código raro: sin enlace');
ok(at_en_url_imagen('https://x.cl', 'Ab3dE5fG7hJ9', 'abcdefghijklmnopqrstuvwx.jpg') === 'https://x.cl/ver-entregable.php?id=Ab3dE5fG7hJ9&img=abcdefghijklmnopqrstuvwx.jpg', 'enlace a una imagen');
ok(at_en_url_imagen('https://x.cl', 'Ab3dE5fG7hJ9', '../wp-config.php') === '', 'imagen con ruta: sin enlace');

// Token del formulario
$sal = 'sal-de-prueba';
$t = at_en_token('Ab3dE5fG7hJ9', 20000, $sal);
ok((bool) preg_match('/^[a-f0-9]{24}$/', $t), 'token de 24 hexadecimales');
ok(at_en_token_valido($t, 'Ab3dE5fG7hJ9', 20000, $sal) && at_en_token_valido($t, 'Ab3dE5fG7hJ9', 20001, $sal), 'vale el día de emisión y el siguiente');
ok(!at_en_token_valido($t, 'Ab3dE5fG7hJ9', 20002, $sal), 'al tercer día vence');
ok(!at_en_token_valido($t, 'Zb3dE5fG7hJ9', 20000, $sal) && !at_en_token_valido('x' . substr($t, 1), 'Ab3dE5fG7hJ9', 20000, $sal) && !at_en_token_valido('', 'Ab3dE5fG7hJ9', 20000, $sal), 'otro código, token alterado o vacío: no vale');

// Nota
$n = at_en_validar_nota('  Orly  ', "Hola\r\nme gusta la v3");
ok($n['ok'] && $n['nombre'] === 'Orly' && $n['texto'] === "Hola\nme gusta la v3", 'nota válida: recorta el nombre y normaliza saltos');
ok(!at_en_validar_nota('Orly', "   ")['ok'] && at_en_validar_nota('Orly', '  ')['error'] === 'nota_vacia', 'nota vacía: error nota_vacia');
ok(at_en_validar_nota('', 'texto')['error'] === 'nombre_vacio', 'sin nombre: error nombre_vacio');
ok(at_en_validar_nota(str_repeat('a', 81), 'texto')['error'] === 'nombre_largo', 'nombre de 81: error nombre_largo');
$emoji = str_repeat('é', 2999) . '🙂';
ok(at_en_validar_nota('Orly', $emoji)['ok'], '3.000 caracteres con tildes y emoji: pasa');
ok(at_en_validar_nota('Orly', $emoji . 'x')['error'] === 'nota_larga', '3.001 caracteres: error nota_larga');
ok(at_en_validar_nota('Orly', "a\x00b\x07c\td")['texto'] === "abc\td", 'quita caracteres de control salvo tab y salto');
ok(at_en_validar_nota('Orly', '<script>alert(1)</script>')['texto'] === '<script>alert(1)</script>', 'se guarda tal cual (se escapa al mostrar)');

// Versión
ok(at_en_url_version_valida(' https://funerariasamordedios.cl/propuestas/ ') === 'https://funerariasamordedios.cl/propuestas/', 'URL de versión http(s) válida');
ok(at_en_url_version_valida('https://algo.easypanel.host/p/x') === '' && at_en_url_version_valida('javascript:alert(1)') === '' && at_en_url_version_valida('ftp://x') === '' && at_en_url_version_valida('') === '', 'easypanel, javascript, ftp o vacío: inválida');
ok(at_en_validar_mensaje(str_repeat('a', 6000))['ok'] && at_en_validar_mensaje(str_repeat('a', 6001))['error'] === 'mensaje_largo', 'mensaje hasta 6.000');
ok(at_en_validar_mensaje('')['ok'], 'mensaje vacío permitido (la plantilla igual dice qué es)');

// Imágenes (reglas puras)
ok(at_en_nombre_imagen_valido('abcdefghijklmnopqrstuvwx.jpg') && at_en_nombre_imagen_valido('0123456789abcdefghijklmn.png'), 'nombre interno válido');
ok(!at_en_nombre_imagen_valido('../abcdefghijklmnopqrstuvw.jpg') && !at_en_nombre_imagen_valido('abcdefghijklmnopqrstuvwx.php') && !at_en_nombre_imagen_valido('ABCDEFGHIJKLMNOPQRSTUVWX.jpg'), 'ruta, otra extensión o mayúsculas: inválido');
$base = ['indice' => 1, 'error' => 0, 'size' => 1000, 'tipo' => 'image/jpeg', 'ancho' => 800, 'alto' => 600];
ok(at_en_revisar_imagen($base) === '', 'JPG chico: sirve');
ok(at_en_revisar_imagen(['tipo' => 'image/png'] + $base) === '', 'PNG: sirve');
ok(at_en_revisar_imagen(['tipo' => 'image/gif'] + $base) === 'La imagen 1 no es JPG ni PNG.', 'GIF: no');
ok(at_en_revisar_imagen(['tipo' => ''] + $base) === 'La imagen 1 no es JPG ni PNG.', 'sin tipo real (no es imagen): no');
ok(at_en_revisar_imagen(['size' => 5242881, 'indice' => 2] + $base) === 'La imagen 2 pesa más de 5 MB.', 'más de 5 MB: no');
ok(at_en_revisar_imagen(['ancho' => 12001] + $base) === 'La imagen 1 es demasiado grande (más de 12.000 px de lado).', 'lado de 12.001 px: no');
ok(at_en_revisar_imagen(['error' => 1] + $base) === 'La imagen 1 no se pudo subir. Inténtalo de nuevo.', 'error de subida: no');

// Mensaje de Luis → HTML
$h = at_en_mensaje_html("Hola Orly:\n\nCambié dos cosas:\n- la portada\n- los colores\n\nMira https://x.cl/p?a=1&b=2 y dime.\n<script>alert(1)</script>");
ok(strpos($h, '<p>Hola Orly:</p>') !== false, 'párrafo por bloque');
ok(strpos($h, '<ul style="padding-left:20px;margin:0 0 14px;"><li>la portada</li><li>los colores</li></ul>') !== false, 'líneas con «- » forman lista');
ok(strpos($h, '<a href="https://x.cl/p?a=1&amp;b=2" style="color:#1e3a8a;">https://x.cl/p?a=1&amp;b=2</a>') !== false, 'URL a enlace escapado');
ok(strpos($h, '<script>') === false && strpos($h, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, '<script> queda como texto');
ok(at_en_mensaje_html('') === '', 'mensaje vacío: nada');

// Extracto
ok(at_en_extracto(str_repeat('a', 500), 400) === str_repeat('a', 400) . '…' && at_en_extracto('corto') === 'corto', 'extracto de 400 con «…»');

// WhatsApp
$w = at_en_texto_whatsapp_version('Orly', 2, 'Propuestas de diseño', 'https://x.cl/ver-entregable.php?id=Ab3dE5fG7hJ9');
ok($w === 'Hola Orly, te escribe Luis de AutomatizaTech. Te envié la versión 2 de Propuestas de diseño. Puedes verla y dejarme tus notas aquí: https://x.cl/ver-entregable.php?id=Ab3dE5fG7hJ9', 'WhatsApp de versión');
ok(strpos(at_en_texto_whatsapp_version('', 1, 'X', 'u'), 'Hola, te escribe Luis') === 0, 'sin nombre: «Hola,»');
ok(at_en_texto_whatsapp_respuesta('Orly', 2, 'Propuestas de diseño', 'https://x.cl/v') === 'Hola Orly, te respondí tu nota sobre la versión 2 de Propuestas de diseño: https://x.cl/v', 'WhatsApp de respuesta');
ok(at_en_url_wa('+56 9 1111 1111', 'Hola Orly') === 'https://wa.me/56911111111?text=Hola%20Orly', 'wa.me con el teléfono del cliente');
ok(at_en_url_wa('', 'Hola') === '', 'sin teléfono: sin enlace');

// Mensajes
$m = at_en_mensajes();
foreach (['no_disponible', 'cerrado', 'nota_ok', 'nota_vacia', 'nota_larga', 'nombre_vacio', 'nombre_largo', 'sesion_vencida', 'muchos_intentos', 'imagen', 'muchas_imagenes', 'no_guardo'] as $k) {
	ok(isset($m[$k]) && $m[$k] !== '', "mensaje «{$k}»");
}
fin();
```

- [ ] **Step 2: Correr y ver que falla**

Run: `/c/wamp64/bin/php/php8.4.15/php.exe tests/entregables/puras-test.php`
Expected: fatal «Failed opening required … entregables/puras.php».

- [ ] **Step 3: Implementar `puras.php`**

```php
<?php
/**
 * Entregables con notas y versiones: funciones sin WordPress (se prueban con php tests/entregables/puras-test.php).
 * Spec: Docs/superpowers/specs/2026-10-05-entregables-notas-design.md
 */
if (!defined('AT_EN_MAX_TEXTO')) {
	define('AT_EN_MAX_TEXTO', 3000);
	define('AT_EN_MAX_NOMBRE', 80);
	define('AT_EN_MAX_MENSAJE', 6000);
	define('AT_EN_MAX_IMAGENES', 3);
	define('AT_EN_MAX_BYTES', 5242880);
	define('AT_EN_MAX_LADO_ENTRADA', 12000);
	define('AT_EN_LADO_FINAL', 2000);
	define('AT_EN_NOTAS_POR_HORA', 10);
}

function at_en_codigo_valido(string $c): bool {
	return (bool) preg_match('/^[A-Za-z0-9]{12}$/', $c);
}

/** Enlace público del entregable; '' sin sitio o con código inválido. */
function at_en_url_pagina(string $base, string $codigo): string {
	$base = rtrim(trim($base), '/');
	return ($base === '' || !at_en_codigo_valido($codigo)) ? '' : $base . '/ver-entregable.php?id=' . $codigo;
}

function at_en_nombre_imagen_valido(string $n): bool {
	return (bool) preg_match('/^[a-z0-9]{24}\.(jpg|png)$/', $n);
}

function at_en_url_imagen(string $base, string $codigo, string $nombre): string {
	$p = at_en_url_pagina($base, $codigo);
	return ($p === '' || !at_en_nombre_imagen_valido($nombre)) ? '' : $p . '&img=' . $nombre;
}

/** Token del formulario de notas para el día $dia (días desde 1970). */
function at_en_token(string $codigo, int $dia, string $sal): string {
	return substr(hash_hmac('sha256', 'at-en-nota|' . $codigo . '|' . $dia, $sal), 0, 24);
}

/** Vale el día en que se emitió y el siguiente. */
function at_en_token_valido(string $token, string $codigo, int $hoy, string $sal): bool {
	if (!preg_match('/^[a-f0-9]{24}$/', $token)) {
		return false;
	}
	return hash_equals(at_en_token($codigo, $hoy, $sal), $token) || hash_equals(at_en_token($codigo, $hoy - 1, $sal), $token);
}

/** Saltos \r\n → \n y sin caracteres de control (salvo tab y salto de línea). */
function at_en_limpiar_texto(string $t): string {
	$t = str_replace(["\r\n", "\r"], "\n", $t);
	return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $t);
}

/** Valida una nota (del cliente o de AT). El texto se guarda tal cual: se escapa al mostrar. */
function at_en_validar_nota(string $nombre, string $texto): array {
	$nombre = trim(at_en_limpiar_texto(str_replace("\n", ' ', $nombre)));
	$texto = trim(at_en_limpiar_texto($texto));
	$r = ['ok' => false, 'error' => '', 'nombre' => $nombre, 'texto' => $texto];
	if ($nombre === '') {
		$r['error'] = 'nombre_vacio';
	} elseif (mb_strlen($nombre, 'UTF-8') > AT_EN_MAX_NOMBRE) {
		$r['error'] = 'nombre_largo';
	} elseif ($texto === '') {
		$r['error'] = 'nota_vacia';
	} elseif (mb_strlen($texto, 'UTF-8') > AT_EN_MAX_TEXTO) {
		$r['error'] = 'nota_larga';
	} else {
		$r['ok'] = true;
	}
	return $r;
}

/** Enlace de una versión: http(s), sin espacios y nunca a easypanel (Hostinger rechaza esos correos). '' si no sirve. */
function at_en_url_version_valida(string $u): string {
	$u = trim($u);
	return (preg_match('#^https?://[^\s<>"]+$#i', $u) && stripos($u, 'easypanel') === false) ? $u : '';
}

function at_en_validar_mensaje(string $m): array {
	$m = trim(at_en_limpiar_texto($m));
	if (mb_strlen($m, 'UTF-8') > AT_EN_MAX_MENSAJE) {
		return ['ok' => false, 'error' => 'mensaje_largo', 'mensaje' => $m];
	}
	return ['ok' => true, 'error' => '', 'mensaje' => $m];
}

/**
 * Revisa una imagen subida con datos ya medidos: indice (1..3), error (UPLOAD_ERR_*), size (bytes), tipo (MIME real del
 * contenido, '' si no es imagen), ancho y alto. '' si sirve; si no, el mensaje para el cliente.
 */
function at_en_revisar_imagen(array $i): string {
	$n = (int) ($i['indice'] ?? 1);
	if ((int) ($i['error'] ?? 0) !== 0) {
		return "La imagen {$n} no se pudo subir. Inténtalo de nuevo.";
	}
	if (!in_array((string) ($i['tipo'] ?? ''), ['image/jpeg', 'image/png'], true)) {
		return "La imagen {$n} no es JPG ni PNG.";
	}
	if ((int) ($i['size'] ?? 0) > AT_EN_MAX_BYTES) {
		return "La imagen {$n} pesa más de 5 MB.";
	}
	if ((int) ($i['ancho'] ?? 0) > AT_EN_MAX_LADO_ENTRADA || (int) ($i['alto'] ?? 0) > AT_EN_MAX_LADO_ENTRADA || (int) ($i['ancho'] ?? 0) < 1 || (int) ($i['alto'] ?? 0) < 1) {
		return "La imagen {$n} es demasiado grande (más de 12.000 px de lado).";
	}
	return '';
}

/** Escapa y convierte las URL en enlaces. */
function at_en_enlazar(string $t): string {
	$e = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
	return (string) preg_replace_callback('#https?://[^\s<>"\']+#i', function ($m) {
		$u = $m[0];
		return '<a href="' . $u . '" style="color:#1e3a8a;">' . $u . '</a>';
	}, $e);
}

/** Mensaje de Luis → HTML seguro: párrafos por línea en blanco, «- » forma lista, URL a enlace. Sin HTML libre. */
function at_en_mensaje_html(string $m): string {
	$m = trim(at_en_limpiar_texto($m));
	if ($m === '') {
		return '';
	}
	$html = '';
	foreach (preg_split("/\n{2,}/", $m) as $bloque) {
		$lineas = array_values(array_filter(array_map('trim', explode("\n", $bloque)), 'strlen'));
		if (!$lineas) {
			continue;
		}
		$texto = [];
		$items = [];
		foreach ($lineas as $l) {
			if (strpos($l, '- ') === 0) {
				$items[] = '<li>' . at_en_enlazar(substr($l, 2)) . '</li>';
			} else {
				if ($items) {
					$texto[] = '<ul style="padding-left:20px;margin:0 0 14px;">' . implode('', $items) . '</ul>';
					$items = [];
				}
				$texto[] = '<p>' . at_en_enlazar($l) . '</p>';
			}
		}
		if ($items) {
			$texto[] = '<ul style="padding-left:20px;margin:0 0 14px;">' . implode('', $items) . '</ul>';
		}
		$html .= implode('', $texto);
	}
	return $html;
}

function at_en_extracto(string $t, int $max = 400): string {
	$t = trim($t);
	return mb_strlen($t, 'UTF-8') > $max ? mb_substr($t, 0, $max, 'UTF-8') . '…' : $t;
}

function at_en_texto_whatsapp_version(string $nombre, int $n, string $titulo, string $url): string {
	$saludo = trim($nombre) !== '' ? 'Hola ' . trim($nombre) : 'Hola';
	return $saludo . ', te escribe Luis de AutomatizaTech. Te envié la versión ' . $n . ' de ' . trim($titulo) . '. Puedes verla y dejarme tus notas aquí: ' . $url;
}

function at_en_texto_whatsapp_respuesta(string $nombre, int $n, string $titulo, string $url): string {
	$saludo = trim($nombre) !== '' ? 'Hola ' . trim($nombre) : 'Hola';
	return $saludo . ', te respondí tu nota sobre la versión ' . $n . ' de ' . trim($titulo) . ': ' . $url;
}

/** wa.me al teléfono del cliente con el texto; '' sin teléfono válido. */
function at_en_url_wa(string $telefono, string $texto): string {
	$n = at_pt_telefono_wa($telefono);
	return $n === '' ? '' : 'https://wa.me/' . $n . '?text=' . rawurlencode($texto);
}

/** Lo que ven el cliente (página) y Luis (panel) por cada clave. */
function at_en_mensajes(): array {
	return [
		'no_disponible'   => 'Este enlace no está disponible.',
		'cerrado'         => 'Este entregable ya está cerrado. Si necesitas algo, escríbenos por WhatsApp.',
		'nota_ok'         => 'Recibimos tu nota. Te responderemos antes de la reunión.',
		'nota_vacia'      => 'Escribe tu nota antes de enviarla.',
		'nota_larga'      => 'La nota es muy larga: el máximo es 3.000 caracteres. Puedes dividirla en dos.',
		'nombre_vacio'    => 'Escribe tu nombre.',
		'nombre_largo'    => 'El nombre es muy largo (máximo 80 caracteres).',
		'sesion_vencida'  => 'La página quedó abierta mucho rato. Recárgala y vuelve a enviar tu nota.',
		'muchos_intentos' => 'Enviaste muchas notas seguidas. Espera un rato o escríbenos por WhatsApp.',
		'imagen'          => 'Una de las imágenes no sirve.',
		'muchas_imagenes' => 'Puedes adjuntar hasta 3 imágenes por nota.',
		'no_guardo'       => 'No pudimos guardar tu nota. Inténtalo de nuevo o escríbenos por WhatsApp.',
	];
}
```

- [ ] **Step 4: Correr y ver que pasa**

Run: `/c/wamp64/bin/php/php8.4.15/php.exe tests/entregables/puras-test.php`
Expected: `TODO OK`.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/entregables/puras.php tests/entregables/puras-test.php
git commit -m "feat(entregables): funciones puras (código, token, validaciones, WhatsApp)"
```

---

### Task 2: Plantillas de correo (`plantillas.php`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/entregables/plantillas.php`
- Test: `tests/entregables/plantillas-test.php`

**Interfaces:**
- Consumes: `at_en_mensaje_html()`, `at_en_extracto()`, `at_en_url_version_valida()` (Task 1).
- Produces (todas devuelven `['asunto' => string, 'html' => string]`):
  - `at_en_correo_version(array $v)` — claves `nombre, titulo, numero, url_version, url_pagina, mensaje, logo, prueba(bool)`
  - `at_en_correo_nota_luis(array $v)` — claves `cliente, empresa, titulo, numero, texto, imagenes(int), url_ficha, logo`
  - `at_en_correo_respuesta(array $v)` — claves `nombre, titulo, numero, texto, url_pagina, logo`
  - `at_en_correo_marco(string $titulo, string $subtitulo, string $cuerpo, string $logo): string` (envoltorio común)

- [ ] **Step 1: Escribir la prueba que falla** — `tests/entregables/plantillas-test.php`

```php
<?php
// Correr: php tests/entregables/plantillas-test.php   (sin WordPress)
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/entregables/puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/entregables/plantillas.php';
$fallas = 0;
function ok($c, $m) { global $fallas; if ($c) { echo "ok   $m\n"; } else { $fallas++; echo "FALLA $m\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }
$logo = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
$pag = 'https://automatizatech.cl/ver-entregable.php?id=Ab3dE5fG7hJ9';

$c = at_en_correo_version(['nombre' => 'Orly <b>', 'titulo' => 'Propuestas de diseño', 'numero' => 2, 'url_version' => 'https://funerariasamordedios.cl/propuestas/', 'url_pagina' => $pag, 'mensaje' => "Cambié:\n- la portada", 'logo' => $logo, 'prueba' => false]);
ok($c['asunto'] === 'Propuestas de diseño: versión 2 para revisar', 'asunto de la versión');
ok(strpos($c['html'], 'linear-gradient(135deg,#1e3a8a,#06d6a0)') !== false && strpos($c['html'], $logo) !== false, 'diseño AT con logo');
ok(strpos($c['html'], 'Hola <strong>Orly &lt;b&gt;</strong>') !== false, 'nombre escapado');
ok(strpos($c['html'], 'Te enviamos la versión 2 de <strong>Propuestas de diseño</strong>') !== false, 'frase de la versión');
ok(strpos($c['html'], 'href="https://funerariasamordedios.cl/propuestas/"') !== false && strpos($c['html'], '>Ver la versión 2</a>') !== false, 'botón Ver la versión N');
ok(strpos($c['html'], 'href="' . $pag . '"') !== false && strpos($c['html'], '>Agregar notas u observaciones</a>') !== false, 'botón de notas a la página');
ok(strpos($c['html'], '<li>la portada</li>') !== false, 'mensaje de Luis con lista');
ok(strpos($c['html'], 'Luis Miguel') !== false && strpos($c['html'], 'easypanel') === false, 'firma y sin easypanel');
$p = at_en_correo_version(['nombre' => 'Orly', 'titulo' => 'X', 'numero' => 1, 'url_version' => 'https://x.cl', 'url_pagina' => $pag, 'mensaje' => '', 'logo' => $logo, 'prueba' => true]);
ok(strpos($p['asunto'], '[PRUEBA] ') === 0, 'prueba: asunto con [PRUEBA]');
$e = at_en_correo_version(['nombre' => 'O', 'titulo' => 'X', 'numero' => 1, 'url_version' => 'https://n8n.easypanel.host/p', 'url_pagina' => $pag, 'mensaje' => '', 'logo' => $logo, 'prueba' => false]);
ok(strpos($e['html'], 'easypanel') === false && strpos($e['html'], 'Ver la versión 1') === false, 'URL easypanel: sin botón de versión');

$l = at_en_correo_nota_luis(['cliente' => 'Orly', 'empresa' => 'Funerarias Amor de Dios', 'titulo' => 'Propuestas de diseño', 'numero' => 2, 'texto' => "Me gusta <script>x</script>\nla v3", 'imagenes' => 2, 'url_ficha' => 'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-ficha&id=4#tab-entregables', 'logo' => $logo]);
ok($l['asunto'] === '📝 Orly dejó una nota en Propuestas de diseño', 'asunto del aviso a Luis');
ok(strpos($l['html'], '&lt;script&gt;') !== false && strpos($l['html'], '<script>') === false && strpos($l['html'], "Me gusta &lt;script&gt;x&lt;/script&gt;<br />\nla v3") !== false, 'texto escapado con saltos');
ok(strpos($l['html'], 'sobre la versión 2') !== false && strpos($l['html'], '2 imágenes adjuntas') !== false && strpos($l['html'], '>Responder en la ficha</a>') !== false, 'versión, imágenes y botón a la ficha');

$r = at_en_correo_respuesta(['nombre' => 'Orly', 'titulo' => 'Propuestas de diseño', 'numero' => 2, 'texto' => str_repeat('a', 450), 'url_pagina' => $pag, 'logo' => $logo]);
ok($r['asunto'] === 'Respuesta a tu nota sobre Propuestas de diseño', 'asunto de la respuesta');
ok(strpos($r['html'], 'Luis respondió tu nota sobre la versión 2') !== false && strpos($r['html'], str_repeat('a', 400) . '…') !== false && strpos($r['html'], str_repeat('a', 401)) === false, 'extracto de 400');
ok(strpos($r['html'], '>Ver la conversación</a>') !== false && strpos($r['html'], 'href="' . $pag . '"') !== false, 'botón a la conversación');
fin();
```

- [ ] **Step 2: Correr y ver que falla**

Run: `/c/wamp64/bin/php/php8.4.15/php.exe tests/entregables/plantillas-test.php`
Expected: fatal «Failed opening required … plantillas.php».

- [ ] **Step 3: Implementar `plantillas.php`**

```php
<?php
/**
 * Correos del módulo de entregables con el diseño AT (el del correo a Orly del 05-oct). Puras: devuelven asunto y HTML.
 */

function at_en_h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function at_en_boton(string $href, string $texto, string $fondo, string $color = '#ffffff'): string {
	return '<a href="' . at_en_h($href) . '" style="display:inline-block;background:' . $fondo . ';color:' . $color . ';padding:13px 30px;border-radius:25px;text-decoration:none;font-weight:bold;margin:6px;">' . at_en_h($texto) . '</a>';
}

/** Envoltorio común: encabezado degradado con logo, cuerpo y pie. */
function at_en_correo_marco(string $titulo, string $subtitulo, string $cuerpo, string $logo): string {
	return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
		. '<body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,Helvetica,sans-serif;color:#222;">'
		. '<div style="max-width:620px;margin:32px auto;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">'
		. '<div style="background:linear-gradient(135deg,#1e3a8a,#06d6a0);background-color:#1e3a8a;color:#ffffff;text-align:center;padding:30px 20px 22px;">'
		. ($logo !== '' ? '<img src="' . at_en_h($logo) . '" alt="AutomatizaTech" style="max-width:140px;margin-bottom:10px;">' : '')
		. '<h1 style="margin:0;font-size:22px;">' . at_en_h($titulo) . '</h1>'
		. ($subtitulo !== '' ? '<p style="margin:8px 0 0;font-size:15px;">' . at_en_h($subtitulo) . '</p>' : '')
		. '</div><div style="padding:28px 26px;font-size:15px;line-height:1.6;">' . $cuerpo . '</div>'
		. '<div style="background:#f8f9fa;color:#6c757d;text-align:center;font-size:13px;padding:16px 10px;">© ' . date('Y') . ' AutomatizaTech · <a href="https://automatizatech.cl/" style="color:#1e3a8a;">automatizatech.cl</a></div>'
		. '</div></body></html>';
}

function at_en_firma(): string {
	return '<p style="margin-top:24px;">Saludos cordiales,</p><p style="margin-bottom:0;"><b>Luis Miguel</b><br>AutomatizaTech<br><a href="https://automatizatech.cl" style="color:#1e3a8a;">https://automatizatech.cl</a></p>';
}

function at_en_correo_version(array $v): array {
	$titulo = trim((string) ($v['titulo'] ?? ''));
	$n = (int) ($v['numero'] ?? 1);
	$nombre = trim((string) ($v['nombre'] ?? ''));
	$url_v = at_en_url_version_valida((string) ($v['url_version'] ?? ''));
	$url_p = at_en_url_version_valida((string) ($v['url_pagina'] ?? ''));
	$cuerpo = '<p>Hola <strong>' . at_en_h($nombre !== '' ? $nombre : 'cliente') . '</strong>:</p>'
		. '<p>Te enviamos la versión ' . $n . ' de <strong>' . at_en_h($titulo) . '</strong>.</p>'
		. at_en_mensaje_html((string) ($v['mensaje'] ?? ''))
		. ($url_v !== '' ? '<p style="text-align:center;margin:22px 0 6px;">' . at_en_boton($url_v, 'Ver la versión ' . $n, '#1e3a8a') . '</p>' : '')
		. ($url_p !== '' ? '<p>¿Tienes comentarios? Déjalos aquí antes de la reunión; puedes adjuntar hasta 3 imágenes:</p><p style="text-align:center;margin:12px 0 22px;">' . at_en_boton($url_p, 'Agregar notas u observaciones', '#06d6a0', '#06261c') . '</p>' : '')
		. at_en_firma();
	$asunto = $titulo . ': versión ' . $n . ' para revisar';
	return ['asunto' => (!empty($v['prueba']) ? '[PRUEBA] ' : '') . $asunto, 'html' => at_en_correo_marco($titulo, 'Versión ' . $n, $cuerpo, (string) ($v['logo'] ?? ''))];
}

function at_en_correo_nota_luis(array $v): array {
	$cliente = trim((string) ($v['cliente'] ?? '')) ?: 'El cliente';
	$titulo = trim((string) ($v['titulo'] ?? ''));
	$img = (int) ($v['imagenes'] ?? 0);
	$cuerpo = '<p><strong>' . at_en_h($cliente) . '</strong>' . (trim((string) ($v['empresa'] ?? '')) !== '' ? ' (' . at_en_h((string) $v['empresa']) . ')' : '')
		. ' dejó una nota sobre la versión ' . (int) ($v['numero'] ?? 0) . ' de <strong>' . at_en_h($titulo) . '</strong>:</p>'
		. '<div style="background:#f8f9fa;border-left:4px solid #1e3a8a;padding:14px 18px;border-radius:8px;margin:16px 0;">' . nl2br(at_en_h((string) ($v['texto'] ?? ''))) . '</div>'
		. ($img > 0 ? '<p>' . $img . ($img === 1 ? ' imagen adjunta' : ' imágenes adjuntas') . ' (se ven en la ficha).</p>' : '')
		. '<p style="text-align:center;margin:22px 0;">' . at_en_boton((string) ($v['url_ficha'] ?? ''), 'Responder en la ficha', '#1e3a8a') . '</p>';
	return ['asunto' => '📝 ' . $cliente . ' dejó una nota en ' . $titulo, 'html' => at_en_correo_marco('Nota nueva', $titulo, $cuerpo, (string) ($v['logo'] ?? ''))];
}

function at_en_correo_respuesta(array $v): array {
	$titulo = trim((string) ($v['titulo'] ?? ''));
	$nombre = trim((string) ($v['nombre'] ?? ''));
	$cuerpo = '<p>Hola <strong>' . at_en_h($nombre !== '' ? $nombre : 'cliente') . '</strong>:</p>'
		. '<p>Luis respondió tu nota sobre la versión ' . (int) ($v['numero'] ?? 0) . ' de <strong>' . at_en_h($titulo) . '</strong>:</p>'
		. '<div style="background:#f0fdf4;border-left:4px solid #06d6a0;padding:14px 18px;border-radius:8px;margin:16px 0;">' . nl2br(at_en_h(at_en_extracto((string) ($v['texto'] ?? ''), 400))) . '</div>'
		. '<p style="text-align:center;margin:22px 0;">' . at_en_boton((string) ($v['url_pagina'] ?? ''), 'Ver la conversación', '#1e3a8a') . '</p>'
		. at_en_firma();
	return ['asunto' => 'Respuesta a tu nota sobre ' . $titulo, 'html' => at_en_correo_marco('Respuesta a tu nota', $titulo, $cuerpo, (string) ($v['logo'] ?? ''))];
}
```

- [ ] **Step 4: Correr y ver que pasa**

Run: `/c/wamp64/bin/php/php8.4.15/php.exe tests/entregables/plantillas-test.php`
Expected: `TODO OK`.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/entregables/plantillas.php tests/entregables/plantillas-test.php
git commit -m "feat(entregables): plantillas de los tres correos con el diseño AT"
```

---

### Task 3: Datos (`datos.php`), carga del módulo y fixtures

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/entregables/datos.php`
- Create: `wp-content/themes/automatiza-tech/inc/entregables/cargar.php`
- Modify: `wp-content/themes/automatiza-tech/inc/admin-proposals.php:92` (agregar una línea después)
- Create: `tests/entregables/fixtures.php`
- Test: `tests/entregables/datos-wp-test.php`

**Interfaces:**
- Consumes: `at_cc_historial_crm(int,string,string,string)` (`inc/cierre-cliente/bienvenida.php:10`).
- Produces:
  - `at_en_tablas(): array` → `['e' => …, 'v' => …, 'n' => …]`
  - `at_en_migrar_esquema(): void`
  - `at_en_detalle(int $detalle_id): ?object` (solo `detail_type = 'entregable'`)
  - `at_en_crm_de_detalle(object $detalle): int`
  - `at_en_por_id(int $id): ?object`, `at_en_por_codigo(string $c): ?object`, `at_en_por_detalle(int $detalle_id): ?object`
  - `at_en_activar(int $detalle_id): int|WP_Error` (devuelve id del entregable; si ya estaba, el mismo id)
  - `at_en_crear_version(int $ent_id, string $url, string $mensaje): int|WP_Error` (devuelve el número)
  - `at_en_version(int $ent_id, int $numero): ?object`, `at_en_versiones(int $ent_id): array` (orden ascendente)
  - `at_en_marcar_version_enviada(int $ent_id, int $numero, string $canal): bool` (`'correo'|'whatsapp'`)
  - `at_en_agregar_nota(int $ent_id, string $autor, string $nombre, string $texto, array $imagenes, string $ip_hash): int|WP_Error`
  - `at_en_nota(int $id): ?object`, `at_en_notas(int $ent_id): array` (orden ascendente)
  - `at_en_marcar_respondida(int $nota_id): void`, `at_en_marcar_aviso(int $nota_id, bool $ok): void`
  - `at_en_cambiar_estado(int $ent_id, string $estado): bool`
  - `at_en_sin_responder(int $ent_id): int`, `at_en_sin_responder_crm(int $crm_id): int`
  - `at_en_de_crm(int $crm_id): array` (filas `entregable` de las fichas del cliente + columnas `en_id, en_codigo, en_estado, en_version` del entregable si está activado)
  - `at_en_historial(int $crm_id, string $tipo, string $titulo, string $desc): void`
  - Fixtures de prueba: `en_fx_cliente(string $marca): array` → `['crm' => int, 'tech' => int, 'detalle' => int]`, `en_fx_limpiar(string $marca): void`

- [ ] **Step 1: Escribir los fixtures** — `tests/entregables/fixtures.php`

```php
<?php
// Cliente de prueba (CRM + ficha operativa + entregable) y limpieza por marca. Se carga después de wp-bootstrap.php.
function en_fx_cliente(string $marca): array {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => 'Cliente ' . $marca, 'email' => $marca . '@example.com', 'empresa' => 'Empresa ' . $marca, 'telefono' => '+56911111111', 'tipo' => 'cliente', 'estado' => 'prueba']);
	$crm = (int) $wpdb->insert_id;
	$wpdb->insert($wpdb->prefix . 'automatiza_tech_clients', ['name' => 'Cliente ' . $marca, 'email' => $marca . '@example.com', 'company' => 'Empresa ' . $marca, 'phone' => '+56911111111', 'crm_cliente_id' => $crm]);
	$tech = (int) $wpdb->insert_id;
	$wpdb->insert($wpdb->prefix . 'automatiza_clients_details', ['client_id' => $tech, 'detail_type' => 'entregable', 'title' => 'Propuestas ' . $marca, 'status' => 'completed', 'attachment_url' => 'https://example.com/v1', 'created_by' => 1]);
	return ['crm' => $crm, 'tech' => $tech, 'detalle' => (int) $wpdb->insert_id];
}

function en_fx_limpiar(string $marca): void {
	global $wpdb;
	$crm_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}crm_clientes WHERE email = %s", $marca . '@example.com'));
	foreach ($crm_ids as $crm) {
		$techs = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id = %d", $crm));
		foreach ($techs as $tech) {
			$dets = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}automatiza_clients_details WHERE client_id = %d", $tech));
			foreach ($dets as $d) {
				$e = function_exists('at_en_por_detalle') ? at_en_por_detalle((int) $d) : null;
				if ($e) {
					if (function_exists('at_en_borrar_imagenes')) {
						at_en_borrar_imagenes((int) $e->id);
					}
					$t = at_en_tablas();
					$wpdb->delete($t['n'], ['entregable_id' => $e->id]);
					$wpdb->delete($t['v'], ['entregable_id' => $e->id]);
					$wpdb->delete($t['e'], ['id' => $e->id]);
				}
				$wpdb->delete($wpdb->prefix . 'automatiza_clients_details', ['id' => $d]);
			}
			$wpdb->delete($wpdb->prefix . 'automatiza_tech_clients', ['id' => $tech]);
		}
		$wpdb->delete($wpdb->prefix . 'crm_historial', ['cliente_id' => $crm]);
		$wpdb->delete($wpdb->prefix . 'crm_clientes', ['id' => $crm]);
	}
}
```

Antes de seguir, comprobar que esas columnas existen en el sitio local (si `automatiza_tech_clients` usa otros nombres, ajustar el fixture, no el módulo):
Run: `AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php php -r 'define("WP_USE_THEMES",false);$_SERVER["HTTP_HOST"]="localhost";require getenv("AT_WP_LOAD");global $wpdb;foreach(["crm_clientes","automatiza_tech_clients","automatiza_clients_details","crm_historial"] as $t){echo $t.": ".implode(",",$wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}$t"))."\n";}'`
Expected: `crm_clientes` con `nombre,email,empresa,telefono,tipo,estado`; `automatiza_tech_clients` con `name,email,company,phone,crm_cliente_id`; `crm_historial` con `cliente_id,tipo_evento,titulo,descripcion,metadata,usuario_id,created_at`.

- [ ] **Step 2: Escribir la prueba que falla** — `tests/entregables/datos-wp-test.php`

```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/datos-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
exigir('at_en_migrar_esquema', 'at_en_activar', 'at_en_crear_version', 'at_en_agregar_nota', 'at_en_de_crm');
global $wpdb;
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
at_en_migrar_esquema();
$t = at_en_tablas();
foreach ($t as $tabla) {
	ok($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tabla)) === $tabla, "tabla {$tabla} creada");
}
$fx = en_fx_cliente($marca);

// Activar
ok(at_en_detalle($fx['detalle']) !== null && at_en_crm_de_detalle(at_en_detalle($fx['detalle'])) === $fx['crm'], 'detalle y su cliente del CRM');
$id = at_en_activar($fx['detalle']);
ok(is_int($id) && $id > 0, 'activar crea el entregable');
$e = at_en_por_id($id);
ok($e && at_en_codigo_valido((string) $e->codigo) && $e->estado === 'abierto' && (int) $e->version_vigente === 0 && (int) $e->crm_id === $fx['crm'], 'código, abierto, sin versiones y con su cliente');
ok(at_en_activar($fx['detalle']) === $id, 'activar dos veces devuelve el mismo');
ok(at_en_por_codigo((string) $e->codigo)->id == $id && at_en_por_codigo(strtolower((string) $e->codigo) === (string) $e->codigo ? 'X' . substr((string) $e->codigo, 1) : strtolower((string) $e->codigo)) === null, 'por código exacto (mayúsculas distintas no)');
$wpdb->insert($wpdb->prefix . 'automatiza_clients_details', ['client_id' => $fx['tech'], 'detail_type' => 'nota', 'title' => 'No es entregable ' . $marca]);
ok(is_wp_error(at_en_activar((int) $wpdb->insert_id)), 'un detalle que no es entregable no se activa');
ok(is_wp_error(at_en_activar(999999999)), 'un detalle inexistente no se activa');

// Versiones
ok(at_en_crear_version($id, 'https://example.com/v1', 'Primera') === 1, 'v1');
ok(at_en_crear_version($id, 'https://example.com/v2', '') === 2, 'v2');
ok(is_wp_error(at_en_crear_version($id, 'https://x.easypanel.host/p', '')), 'URL easypanel rechazada');
ok(is_wp_error(at_en_crear_version($id, 'https://example.com/v3', str_repeat('a', 6001))), 'mensaje de 6.001 rechazado');
ok((int) at_en_por_id($id)->version_vigente === 2 && count(at_en_versiones($id)) === 2, 'vigente = 2 y dos versiones');
$wpdb->suppress_errors(true);
$dup = $wpdb->insert($t['v'], ['entregable_id' => $id, 'numero' => 2, 'url' => 'https://example.com/dup', 'creado_at' => current_time('mysql')]);
$wpdb->suppress_errors(false);
ok($dup === false, 'índice único: no puede haber dos v2');
ok(at_en_marcar_version_enviada($id, 2, 'correo') && at_en_version($id, 2)->enviado_correo_at !== null && at_en_version($id, 2)->enviado_whatsapp_at === null, 'marca enviada por correo');
ok(!at_en_marcar_version_enviada($id, 9, 'correo') && !at_en_marcar_version_enviada($id, 2, 'fax'), 'versión inexistente o canal raro: no');

// Notas
$n1 = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Me gusta la v2', ['abcdefghijklmnopqrstuvwx.jpg'], str_repeat('a', 64));
ok(is_int($n1) && at_en_nota($n1)->version_numero == 2 && at_en_nota($n1)->autor === 'cliente' && (int) at_en_nota($n1)->respondida === 0, 'nota del cliente sobre la versión vigente');
ok(json_decode((string) at_en_nota($n1)->imagenes, true) === ['abcdefghijklmnopqrstuvwx.jpg'], 'imágenes como JSON');
ok(is_wp_error(at_en_agregar_nota($id, 'otro', 'X', 'y', [], '')), 'autor desconocido rechazado');
ok(is_wp_error(at_en_agregar_nota($id, 'cliente', 'X', '', [], '')), 'nota vacía rechazada');
ok(is_wp_error(at_en_agregar_nota($id, 'cliente', 'X', 'y', ['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg'], '')), 'más de 3 imágenes rechazado');
ok(at_en_sin_responder($id) === 1 && at_en_sin_responder_crm($fx['crm']) === 1, 'una sin responder');
$r1 = at_en_agregar_nota($id, 'at', 'Luis', 'Ya lo corrijo', [], '');
at_en_marcar_respondida($n1);
ok(is_int($r1) && at_en_sin_responder($id) === 0 && count(at_en_notas($id)) === 2, 'respuesta de AT y nota marcada respondida');
at_en_marcar_aviso($n1, false);
ok((int) at_en_nota($n1)->aviso_ok === 0, 'nota marcada sin aviso');

// Estado
ok(at_en_cambiar_estado($id, 'cerrado') && at_en_por_id($id)->estado === 'cerrado' && at_en_por_id($id)->cerrado_at !== null, 'cerrar');
ok(at_en_cambiar_estado($id, 'abierto') && at_en_por_id($id)->estado === 'abierto', 'reabrir');
ok(!at_en_cambiar_estado($id, 'borrado'), 'estado desconocido: no');

// Lista del cliente
$lista = at_en_de_crm($fx['crm']);
$fila = array_values(array_filter($lista, function ($f) use ($fx) { return (int) $f->id === $fx['detalle']; }))[0] ?? null;
ok($fila && (int) $fila->en_id === $id && (int) $fila->en_version === 2, 'la lista trae el entregable activado');
ok(count(array_filter($lista, function ($f) { return $f->detail_type !== 'entregable'; })) === 0, 'la lista solo trae entregables');

// Historial
at_en_historial($fx['crm'], 'entregable_nota', 'Nota de prueba', 'detalle');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d AND tipo_evento = 'entregable_nota'", $fx['crm'])) === 1, 'historial del CRM escrito');
fin();
```

- [ ] **Step 3: Correr y ver que falla**

Run: `AT_WP_LOAD=$SCR/wp-local-plan/wp-load.php /c/wamp64/bin/php/php8.4.15/php.exe tests/entregables/datos-wp-test.php`
Expected: `FALLA falta la función at_en_migrar_esquema()`.

- [ ] **Step 4: Implementar `datos.php`**

```php
<?php
/**
 * Entregables: tres tablas propias (no se toca wp_automatiza_clients_details) e historial en el CRM.
 */
if (!defined('ABSPATH')) {
	exit;
}

function at_en_tablas(): array {
	global $wpdb;
	return ['e' => $wpdb->prefix . 'at_entregables', 'v' => $wpdb->prefix . 'at_entregable_versiones', 'n' => $wpdb->prefix . 'at_entregable_notas'];
}

/** Crea las tablas con dbDelta. Idempotente (opción at_en_db_version = '1'); si falla, reintenta en 5 minutos. */
function at_en_migrar_esquema(): void {
	if (get_option('at_en_db_version') === '1' || get_transient('at_en_migrar_intento')) {
		return;
	}
	global $wpdb;
	$t = at_en_tablas();
	$c = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta("CREATE TABLE {$t['e']} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  detalle_id BIGINT UNSIGNED NOT NULL,
  crm_id BIGINT UNSIGNED NOT NULL,
  codigo CHAR(12) NOT NULL,
  estado VARCHAR(10) NOT NULL DEFAULT 'abierto',
  version_vigente INT UNSIGNED NOT NULL DEFAULT 0,
  creado_at DATETIME NOT NULL,
  actualizado_at DATETIME NULL,
  cerrado_at DATETIME NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_detalle (detalle_id),
  UNIQUE KEY uniq_codigo (codigo),
  KEY idx_crm (crm_id)
) {$c};");
	dbDelta("CREATE TABLE {$t['v']} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entregable_id BIGINT UNSIGNED NOT NULL,
  numero INT UNSIGNED NOT NULL,
  url VARCHAR(500) NOT NULL,
  mensaje TEXT NULL,
  enviado_correo_at DATETIME NULL,
  enviado_whatsapp_at DATETIME NULL,
  creado_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_numero (entregable_id,numero)
) {$c};");
	dbDelta("CREATE TABLE {$t['n']} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entregable_id BIGINT UNSIGNED NOT NULL,
  version_numero INT UNSIGNED NOT NULL DEFAULT 0,
  autor VARCHAR(10) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  texto TEXT NOT NULL,
  imagenes TEXT NULL,
  respondida TINYINT(1) NOT NULL DEFAULT 0,
  aviso_ok TINYINT(1) NOT NULL DEFAULT 1,
  ip_hash CHAR(64) NULL,
  creado_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_entregable (entregable_id),
  KEY idx_pendientes (entregable_id,autor,respondida)
) {$c};");
	foreach ($t as $tabla) {
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tabla))) !== $tabla) {
			set_transient('at_en_migrar_intento', 1, 5 * MINUTE_IN_SECONDS);
			error_log('at_en: no se pudo crear ' . $tabla . ': ' . $wpdb->last_error);
			return;
		}
	}
	update_option('at_en_db_version', '1');
}
add_action('admin_init', 'at_en_migrar_esquema');

/** Fila de wp_automatiza_clients_details solo si es un entregable; null si no. */
function at_en_detalle(int $detalle_id): ?object {
	if ($detalle_id <= 0) {
		return null;
	}
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_clients_details WHERE id = %d AND detail_type = 'entregable'", $detalle_id));
	return $f ?: null;
}

/** Cliente del CRM de un detalle, por su ficha operativa; 0 si no está enlazada. */
function at_en_crm_de_detalle(object $detalle): int {
	global $wpdb;
	return (int) $wpdb->get_var($wpdb->prepare("SELECT crm_cliente_id FROM {$wpdb->prefix}automatiza_tech_clients WHERE id = %d", (int) $detalle->client_id));
}

function at_en_por_id(int $id): ?object {
	if ($id <= 0) {
		return null;
	}
	at_en_migrar_esquema();
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['e'] . ' WHERE id = %d', $id));
	return $f ?: null;
}

/** Por código exacto (la columna compara sin mayúsculas: se exige igualdad exacta). */
function at_en_por_codigo(string $codigo): ?object {
	if (!at_en_codigo_valido($codigo)) {
		return null;
	}
	at_en_migrar_esquema();
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['e'] . ' WHERE codigo = %s', $codigo));
	return ($f && hash_equals((string) $f->codigo, $codigo)) ? $f : null;
}

function at_en_por_detalle(int $detalle_id): ?object {
	if ($detalle_id <= 0) {
		return null;
	}
	at_en_migrar_esquema();
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['e'] . ' WHERE detalle_id = %d', $detalle_id));
	return $f ?: null;
}

/** Activa las notas de un entregable; si ya estaba, devuelve el mismo id. */
function at_en_activar(int $detalle_id) {
	$d = at_en_detalle($detalle_id);
	if (!$d) {
		return new WP_Error('no_entregable', 'Ese detalle no es un entregable.');
	}
	$ya = at_en_por_detalle($detalle_id);
	if ($ya) {
		return (int) $ya->id;
	}
	$crm = at_en_crm_de_detalle($d);
	if ($crm <= 0) {
		return new WP_Error('sin_crm', 'La ficha operativa no está enlazada a un cliente del CRM.');
	}
	global $wpdb;
	$t = at_en_tablas()['e'];
	for ($i = 0; $i < 5; $i++) {
		$codigo = wp_generate_password(12, false);
		$ok = $wpdb->insert($t, ['detalle_id' => $detalle_id, 'crm_id' => $crm, 'codigo' => $codigo, 'estado' => 'abierto', 'version_vigente' => 0, 'creado_at' => current_time('mysql')]);
		if ($ok) {
			return (int) $wpdb->insert_id;
		}
		$ya = at_en_por_detalle($detalle_id); // otro clic lo activó entretanto
		if ($ya) {
			return (int) $ya->id;
		}
	}
	return new WP_Error('no_guardo', 'No se pudo activar el entregable.');
}

/** Crea la versión siguiente dentro de una transacción (dos clics no crean dos v2). Devuelve su número. */
function at_en_crear_version(int $ent_id, string $url, string $mensaje) {
	$url = at_en_url_version_valida($url);
	if ($url === '') {
		return new WP_Error('url_invalida', 'El enlace de la versión debe empezar con http(s):// y no puede ser de easypanel.');
	}
	$m = at_en_validar_mensaje($mensaje);
	if (!$m['ok']) {
		return new WP_Error($m['error'], 'El mensaje es muy largo (máximo 6.000 caracteres).');
	}
	global $wpdb;
	$t = at_en_tablas();
	$wpdb->query('START TRANSACTION');
	$vig = $wpdb->get_var($wpdb->prepare("SELECT version_vigente FROM {$t['e']} WHERE id = %d FOR UPDATE", $ent_id));
	if ($vig === null) {
		$wpdb->query('ROLLBACK');
		return new WP_Error('sin_entregable', 'El entregable no existe.');
	}
	$n = (int) $vig + 1;
	$ok = $wpdb->insert($t['v'], ['entregable_id' => $ent_id, 'numero' => $n, 'url' => $url, 'mensaje' => $m['mensaje'], 'creado_at' => current_time('mysql')]);
	$ok2 = $ok && $wpdb->update($t['e'], ['version_vigente' => $n, 'actualizado_at' => current_time('mysql')], ['id' => $ent_id]) !== false;
	if (!$ok2) {
		$wpdb->query('ROLLBACK');
		return new WP_Error('no_guardo', 'No se pudo guardar la versión.');
	}
	$wpdb->query('COMMIT');
	return $n;
}

function at_en_version(int $ent_id, int $numero): ?object {
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['v'] . ' WHERE entregable_id = %d AND numero = %d', $ent_id, $numero));
	return $f ?: null;
}

function at_en_versiones(int $ent_id): array {
	global $wpdb;
	return (array) $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['v'] . ' WHERE entregable_id = %d ORDER BY numero ASC', $ent_id));
}

function at_en_marcar_version_enviada(int $ent_id, int $numero, string $canal): bool {
	$col = ['correo' => 'enviado_correo_at', 'whatsapp' => 'enviado_whatsapp_at'][$canal] ?? '';
	if ($col === '' || !at_en_version($ent_id, $numero)) {
		return false;
	}
	global $wpdb;
	return $wpdb->update(at_en_tablas()['v'], [$col => current_time('mysql')], ['entregable_id' => $ent_id, 'numero' => $numero]) !== false;
}

/** Agrega una nota sobre la versión vigente. $imagenes: nombres internos ya guardados (0 a 3). */
function at_en_agregar_nota(int $ent_id, string $autor, string $nombre, string $texto, array $imagenes, string $ip_hash) {
	if (!in_array($autor, ['cliente', 'at'], true)) {
		return new WP_Error('autor', 'Autor desconocido.');
	}
	$v = at_en_validar_nota($nombre, $texto);
	if (!$v['ok']) {
		return new WP_Error($v['error'], at_en_mensajes()[$v['error']] ?? 'Nota inválida.');
	}
	if (count($imagenes) > AT_EN_MAX_IMAGENES) {
		return new WP_Error('muchas_imagenes', at_en_mensajes()['muchas_imagenes']);
	}
	$e = at_en_por_id($ent_id);
	if (!$e) {
		return new WP_Error('sin_entregable', 'El entregable no existe.');
	}
	global $wpdb;
	$ok = $wpdb->insert(at_en_tablas()['n'], [
		'entregable_id' => $ent_id, 'version_numero' => (int) $e->version_vigente, 'autor' => $autor,
		'nombre' => $v['nombre'], 'texto' => $v['texto'], 'imagenes' => wp_json_encode(array_values($imagenes)),
		'respondida' => 0, 'aviso_ok' => 1, 'ip_hash' => $ip_hash !== '' ? $ip_hash : null, 'creado_at' => current_time('mysql'),
	]);
	return $ok ? (int) $wpdb->insert_id : new WP_Error('no_guardo', at_en_mensajes()['no_guardo']);
}

function at_en_nota(int $id): ?object {
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['n'] . ' WHERE id = %d', $id));
	return $f ?: null;
}

function at_en_notas(int $ent_id): array {
	global $wpdb;
	return (array) $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['n'] . ' WHERE entregable_id = %d ORDER BY id ASC', $ent_id));
}

function at_en_marcar_respondida(int $nota_id): void {
	global $wpdb;
	$wpdb->update(at_en_tablas()['n'], ['respondida' => 1], ['id' => $nota_id, 'autor' => 'cliente']);
}

function at_en_marcar_aviso(int $nota_id, bool $ok): void {
	global $wpdb;
	$wpdb->update(at_en_tablas()['n'], ['aviso_ok' => $ok ? 1 : 0], ['id' => $nota_id]);
}

function at_en_cambiar_estado(int $ent_id, string $estado): bool {
	if (!in_array($estado, ['abierto', 'cerrado'], true)) {
		return false;
	}
	global $wpdb;
	$campos = ['estado' => $estado, 'actualizado_at' => current_time('mysql'), 'cerrado_at' => $estado === 'cerrado' ? current_time('mysql') : null];
	return (bool) $wpdb->update(at_en_tablas()['e'], $campos, ['id' => $ent_id]);
}

function at_en_sin_responder(int $ent_id): int {
	global $wpdb;
	return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . at_en_tablas()['n'] . " WHERE entregable_id = %d AND autor = 'cliente' AND respondida = 0", $ent_id));
}

function at_en_sin_responder_crm(int $crm_id): int {
	at_en_migrar_esquema();
	global $wpdb;
	$t = at_en_tablas();
	return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['n']} n JOIN {$t['e']} e ON e.id = n.entregable_id WHERE e.crm_id = %d AND n.autor = 'cliente' AND n.respondida = 0", $crm_id));
}

/** Entregables de las fichas operativas del cliente, con los datos del módulo si están activados. */
function at_en_de_crm(int $crm_id): array {
	if ($crm_id <= 0) {
		return [];
	}
	at_en_migrar_esquema();
	global $wpdb;
	$t = at_en_tablas();
	return (array) $wpdb->get_results($wpdb->prepare(
		"SELECT d.*, e.id AS en_id, e.codigo AS en_codigo, e.estado AS en_estado, e.version_vigente AS en_version
		 FROM {$wpdb->prefix}automatiza_clients_details d
		 JOIN {$wpdb->prefix}automatiza_tech_clients c ON c.id = d.client_id
		 LEFT JOIN {$t['e']} e ON e.detalle_id = d.id
		 WHERE c.crm_cliente_id = %d AND d.detail_type = 'entregable' ORDER BY d.id DESC",
		$crm_id
	));
}

function at_en_historial(int $crm_id, string $tipo, string $titulo, string $desc): void {
	if ($crm_id > 0 && function_exists('at_cc_historial_crm')) {
		at_cc_historial_crm($crm_id, $tipo, $titulo, $desc);
	}
}
```

- [ ] **Step 5: Crear `cargar.php` y la línea de carga**

`wp-content/themes/automatiza-tech/inc/entregables/cargar.php`:

```php
<?php
/**
 * Notas y versiones de los entregables. Spec: Docs/superpowers/specs/2026-10-05-entregables-notas-design.md
 * Se carga desde inc/admin-proposals.php (como plan-trabajo); functions.php no se toca.
 */
if (!defined('ABSPATH')) {
	exit;
}
require_once __DIR__ . '/puras.php';
require_once __DIR__ . '/plantillas.php';
require_once __DIR__ . '/datos.php';
```

En `inc/admin-proposals.php`, justo después de la línea 92 (`require_once __DIR__ . '/plan-trabajo/cargar.php';`):

```php
require_once __DIR__ . '/entregables/cargar.php';
```

- [ ] **Step 6: Correr y ver que pasa**

Run: `bash tests/entregables/correr.sh`
Expected: `puras-test.php`, `plantillas-test.php` y `datos-wp-test.php` en `TODO OK`.

- [ ] **Step 7: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/entregables/datos.php wp-content/themes/automatiza-tech/inc/entregables/cargar.php wp-content/themes/automatiza-tech/inc/admin-proposals.php tests/entregables/fixtures.php tests/entregables/datos-wp-test.php
git commit -m "feat(entregables): tablas, versiones, notas e historial del CRM"
```

---

### Task 4: Imágenes (`imagenes.php`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/entregables/imagenes.php`
- Modify: `wp-content/themes/automatiza-tech/inc/entregables/cargar.php` (agregar `require_once __DIR__ . '/imagenes.php';` después de `datos.php`)
- Test: `tests/entregables/imagenes-wp-test.php`

**Interfaces:**
- Consumes: `at_en_revisar_imagen()`, `at_en_nombre_imagen_valido()` (Task 1); `at_en_por_id()`, `at_en_notas()` (Task 3).
- Produces:
  - `at_en_dir_base(): string` (crea `uploads/at-entregables/` con `.htaccess` y `index.php`)
  - `at_en_dir_imagenes(int $ent_id): string`
  - `at_en_archivos_subidos(array $campo): array` (normaliza `$_FILES['imagenes']`; omite `UPLOAD_ERR_NO_FILE`)
  - `at_en_procesar_imagenes(array $archivos, int $ent_id): array|WP_Error` (devuelve nombres internos; si una falla, borra las ya guardadas)
  - `at_en_ruta_imagen(object $ent, string $nombre): string` (`''` si no pertenece a una nota del entregable)
  - `at_en_servir_imagen(object $ent, string $nombre): void` (cabeceras + contenido + `exit`)
  - `at_en_borrar_imagenes(int $ent_id): void`
  - Filtro `at_en_es_subida` (`bool $es, string $tmp`) — por defecto `is_uploaded_file($tmp)`; las pruebas lo ponen en `true`.

- [ ] **Step 1: Escribir la prueba que falla** — `tests/entregables/imagenes-wp-test.php`

```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/imagenes-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
exigir('at_en_procesar_imagenes', 'at_en_archivos_subidos', 'at_en_ruta_imagen', 'at_en_borrar_imagenes');
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
add_filter('at_en_es_subida', '__return_true'); // en CLI no hay subida HTTP real
$fx = en_fx_cliente($marca);
$id = at_en_activar($fx['detalle']);
at_en_crear_version($id, 'https://example.com/v1', '');
$tmp = sys_get_temp_dir() . '/en-' . $marca;
@mkdir($tmp);

/** JPG real de $w×$h con un segmento APP1 Exif que trae el texto GPS-PRUEBA-<marca>. */
function en_jpg_con_exif(string $ruta, int $w, int $h, string $marca): void {
	$im = imagecreatetruecolor($w, $h);
	imagefill($im, 0, 0, imagecolorallocate($im, 30, 58, 138));
	ob_start(); imagejpeg($im, null, 90); $jpg = ob_get_clean(); imagedestroy($im);
	$exif = "Exif\0\0" . 'GPS-PRUEBA-' . $marca;
	$app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
	file_put_contents($ruta, substr($jpg, 0, 2) . $app1 . substr($jpg, 2));
}
function en_png(string $ruta, int $w, int $h): void {
	$im = imagecreatetruecolor($w, $h); imagepng($im, $ruta); imagedestroy($im);
}
function en_archivo(string $ruta, string $nombre): array {
	return ['name' => $nombre, 'type' => 'image/jpeg', 'tmp_name' => $ruta, 'error' => 0, 'size' => filesize($ruta)];
}

// Normalización de $_FILES
$campo = ['name' => ['a.jpg', ''], 'type' => ['image/jpeg', ''], 'tmp_name' => ['/x/a', ''], 'error' => [0, UPLOAD_ERR_NO_FILE], 'size' => [10, 0]];
ok(count(at_en_archivos_subidos($campo)) === 1 && at_en_archivos_subidos($campo)[0]['name'] === 'a.jpg', 'normaliza y omite los vacíos');
ok(at_en_archivos_subidos([]) === [], 'sin imágenes: lista vacía (son opcionales)');

// JPG con EXIF grande → reescrito, sin EXIF y achicado
en_jpg_con_exif("$tmp/foto.jpg", 2600, 1300, $marca);
$r = at_en_procesar_imagenes([en_archivo("$tmp/foto.jpg", 'foto.jpg')], $id);
ok(is_array($r) && count($r) === 1 && at_en_nombre_imagen_valido($r[0]) && substr($r[0], -4) === '.jpg', 'JPG guardado con nombre interno');
$guardada = at_en_dir_imagenes($id) . $r[0];
ok(is_file($guardada) && strpos((string) file_get_contents($guardada), 'GPS-PRUEBA-' . $marca) === false, 'se borró el EXIF (GPS)');
$tam = getimagesize($guardada);
ok($tam && max($tam[0], $tam[1]) <= 2000, 'lado máximo 2.000 px');
ok(is_file(at_en_dir_base() . '.htaccess') && strpos((string) file_get_contents(at_en_dir_base() . '.htaccess'), 'Require all denied') !== false && is_file(at_en_dir_base() . 'index.php'), 'carpeta bloqueada con .htaccess e index.php');

// PNG
en_png("$tmp/p.png", 300, 200);
$rp = at_en_procesar_imagenes([['type' => 'image/png'] + en_archivo("$tmp/p.png", 'p.png')], $id);
ok(is_array($rp) && substr($rp[0], -4) === '.png', 'PNG guardado como .png');

// PHP disfrazado de JPG → rechazado y sin archivos nuevos
file_put_contents("$tmp/malo.jpg", "<?php echo 'x'; ?>");
$antes = count(glob(at_en_dir_imagenes($id) . '*'));
$m = at_en_procesar_imagenes([en_archivo("$tmp/foto.jpg", 'foto.jpg'), en_archivo("$tmp/malo.jpg", 'malo.jpg')], $id);
ok(is_wp_error($m) && $m->get_error_message() === 'La imagen 2 no es JPG ni PNG.', 'PHP como .jpg: «La imagen 2 no es JPG ni PNG.»');
ok(count(glob(at_en_dir_imagenes($id) . '*')) === $antes, 'si una falla, no queda ninguna guardada');

// Más de 3
$cuatro = array_fill(0, 4, en_archivo("$tmp/foto.jpg", 'f.jpg'));
ok(is_wp_error(at_en_procesar_imagenes($cuatro, $id)) && at_en_procesar_imagenes($cuatro, $id)->get_error_code() === 'muchas_imagenes', 'más de 3: rechazado');

// Más de 5 MB (se informa el size; no hace falta un archivo real de 5 MB)
ok(is_wp_error(at_en_procesar_imagenes([['size' => 5242881] + en_archivo("$tmp/foto.jpg", 'f.jpg')], $id)), 'más de 5 MB: rechazado');

// Lado de 12.001 px
en_png("$tmp/ancha.png", 12001, 1);
ok(is_wp_error(at_en_procesar_imagenes([['type' => 'image/png'] + en_archivo("$tmp/ancha.png", 'a.png')], $id)), '12.001 px de lado: rechazado');

// Subida que no es HTTP real
remove_filter('at_en_es_subida', '__return_true');
ok(is_wp_error(at_en_procesar_imagenes([en_archivo("$tmp/foto.jpg", 'foto.jpg')], $id)), 'sin is_uploaded_file: rechazado');
add_filter('at_en_es_subida', '__return_true');

// Pertenencia
$e = at_en_por_id($id);
ok(at_en_ruta_imagen($e, $r[0]) === '', 'imagen guardada pero sin nota: no se sirve');
at_en_agregar_nota($id, 'cliente', 'Cliente', 'Con foto', [$r[0]], '');
ok(at_en_ruta_imagen($e, $r[0]) === $guardada, 'imagen de una nota del entregable: se sirve');
ok(at_en_ruta_imagen($e, '../' . $r[0]) === '' && at_en_ruta_imagen($e, 'abcdefghijklmnopqrstuvwx.jpg') === '', 'ruta rara o ajena: no');

// Borrado
at_en_borrar_imagenes($id);
ok(!is_dir(at_en_dir_imagenes($id)) || count(glob(at_en_dir_imagenes($id) . '*')) === 0, 'borrar imágenes del entregable');
array_map('unlink', glob("$tmp/*")); @rmdir($tmp);
fin();
```

- [ ] **Step 2: Correr y ver que falla**

Run: `bash tests/entregables/correr.sh imagenes`
Expected: `FALLA falta la función at_en_procesar_imagenes()`.

- [ ] **Step 3: Implementar `imagenes.php`**

```php
<?php
/**
 * Imágenes de las notas (0 a 3 por nota): validar el contenido real, reescribir (quita EXIF/GPS, lado ≤ 2.000 px),
 * guardar fuera del alcance público y servir solo por ver-entregable.php con el código del entregable.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** uploads/at-entregables/ con .htaccess que bloquea todo e index.php vacío. */
function at_en_dir_base(): string {
	$up = wp_upload_dir(null, false);
	$dir = trailingslashit($up['basedir']) . 'at-entregables/';
	if (!is_dir($dir)) {
		wp_mkdir_p($dir);
	}
	if (!is_file($dir . '.htaccess')) {
		file_put_contents($dir . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
	}
	if (!is_file($dir . 'index.php')) {
		file_put_contents($dir . 'index.php', "<?php\n// Silencio.\n");
	}
	return $dir;
}

function at_en_dir_imagenes(int $ent_id): string {
	return at_en_dir_base() . (int) $ent_id . '/';
}

/** $_FILES['imagenes'] (formato de arreglos paralelos) → lista de archivos; omite los campos vacíos. */
function at_en_archivos_subidos(array $campo): array {
	if (!isset($campo['name']) || !is_array($campo['name'])) {
		return [];
	}
	$lista = [];
	foreach ($campo['name'] as $i => $nombre) {
		$error = (int) ($campo['error'][$i] ?? UPLOAD_ERR_NO_FILE);
		if ($error === UPLOAD_ERR_NO_FILE) {
			continue;
		}
		$lista[] = ['name' => (string) $nombre, 'type' => (string) ($campo['type'][$i] ?? ''), 'tmp_name' => (string) ($campo['tmp_name'][$i] ?? ''), 'error' => $error, 'size' => (int) ($campo['size'][$i] ?? 0)];
	}
	return $lista;
}

/** MIME real del contenido ('' si no es imagen) y medidas. */
function at_en_medir(string $ruta): array {
	$info = @getimagesize($ruta);
	if (!$info) {
		return ['tipo' => '', 'ancho' => 0, 'alto' => 0];
	}
	return ['tipo' => (string) ($info['mime'] ?? ''), 'ancho' => (int) $info[0], 'alto' => (int) $info[1]];
}

/** Valida y guarda 0 a 3 imágenes; devuelve sus nombres internos o WP_Error (sin dejar archivos a medias). */
function at_en_procesar_imagenes(array $archivos, int $ent_id) {
	if (count($archivos) > AT_EN_MAX_IMAGENES) {
		return new WP_Error('muchas_imagenes', at_en_mensajes()['muchas_imagenes']);
	}
	// Primero se revisan todas; recién después se guarda (una mala no deja otras guardadas).
	foreach (array_values($archivos) as $i => $a) {
		$tmp = (string) ($a['tmp_name'] ?? '');
		$medida = ($tmp !== '' && is_file($tmp)) ? at_en_medir($tmp) : ['tipo' => '', 'ancho' => 0, 'alto' => 0];
		$es_subida = $tmp !== '' && (bool) apply_filters('at_en_es_subida', is_uploaded_file($tmp), $tmp);
		$error = $es_subida ? (int) ($a['error'] ?? 0) : UPLOAD_ERR_CANT_WRITE;
		$m = at_en_revisar_imagen(['indice' => $i + 1, 'error' => $error, 'size' => (int) ($a['size'] ?? 0)] + $medida);
		if ($m !== '') {
			return new WP_Error('imagen', $m);
		}
		$ft = wp_check_filetype_and_ext($tmp, (string) ($a['name'] ?? ''), ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png']);
		if ($ft['type'] && !in_array($ft['type'], ['image/jpeg', 'image/png'], true)) {
			return new WP_Error('imagen', 'La imagen ' . ($i + 1) . ' no es JPG ni PNG.');
		}
	}
	$dir = at_en_dir_imagenes($ent_id);
	wp_mkdir_p($dir);
	$guardadas = [];
	foreach (array_values($archivos) as $i => $a) {
		$tmp = (string) $a['tmp_name'];
		$ext = at_en_medir($tmp)['tipo'] === 'image/png' ? 'png' : 'jpg';
		$nombre = strtolower(wp_generate_password(24, false, false)) . '.' . $ext;
		$ed = wp_get_image_editor($tmp);
		$ok = !is_wp_error($ed);
		if ($ok) {
			$tam = $ed->get_size();
			if (max((int) $tam['width'], (int) $tam['height']) > AT_EN_LADO_FINAL) {
				$ok = !is_wp_error($ed->resize(AT_EN_LADO_FINAL, AT_EN_LADO_FINAL, false));
			}
			// Guardar siempre reescribe la imagen: el EXIF (con el GPS) no pasa.
			$ok = $ok && !is_wp_error($ed->save($dir . $nombre, $ext === 'png' ? 'image/png' : 'image/jpeg'));
		}
		if (!$ok || !is_file($dir . $nombre)) {
			foreach ($guardadas as $g) {
				@unlink($dir . $g);
			}
			return new WP_Error('imagen', 'La imagen ' . ($i + 1) . ' no se pudo procesar. Prueba con otra.');
		}
		$guardadas[] = $nombre;
	}
	return $guardadas;
}

/** Ruta en disco de una imagen solo si figura en una nota de ese entregable; '' si no. */
function at_en_ruta_imagen(object $ent, string $nombre): string {
	if (!at_en_nombre_imagen_valido($nombre)) {
		return '';
	}
	foreach (at_en_notas((int) $ent->id) as $n) {
		$imgs = json_decode((string) $n->imagenes, true);
		if (is_array($imgs) && in_array($nombre, $imgs, true)) {
			$ruta = at_en_dir_imagenes((int) $ent->id) . $nombre;
			return is_file($ruta) ? $ruta : '';
		}
	}
	return '';
}

/** Entrega la imagen (o 404) y termina. */
function at_en_servir_imagen(object $ent, string $nombre): void {
	$ruta = at_en_ruta_imagen($ent, $nombre);
	if ($ruta === '') {
		status_header(404);
		exit;
	}
	header('Content-Type: ' . (substr($nombre, -4) === '.png' ? 'image/png' : 'image/jpeg'));
	header('Content-Length: ' . filesize($ruta));
	header('Cache-Control: private, no-store, no-transform');
	header('X-Content-Type-Options: nosniff');
	header('X-Robots-Tag: noindex, nofollow');
	readfile($ruta);
	exit;
}

function at_en_borrar_imagenes(int $ent_id): void {
	$dir = at_en_dir_imagenes($ent_id);
	foreach ((array) glob($dir . '*') as $f) {
		if (is_file($f)) {
			@unlink($f);
		}
	}
	@rmdir($dir);
}
```

Agregar en `cargar.php`, después de `datos.php`: `require_once __DIR__ . '/imagenes.php';`

- [ ] **Step 4: Correr y ver que pasa**

Run: `bash tests/entregables/correr.sh`
Expected: las cuatro pruebas en `TODO OK`. GD reescribe la imagen y no copia el EXIF. Si el sitio usa Imagick y «se borró el EXIF» falla, extender `WP_Image_Editor_Imagick` no vale la pena: forzar GD solo en este módulo con `add_filter('wp_image_editors', function () { return ['WP_Image_Editor_GD']; })` alrededor de `wp_get_image_editor()` (agregar y quitar el filtro dentro de `at_en_procesar_imagenes`) y volver a correr. En PROD (Hostinger) comprobar qué editor hay antes de subir: `wp eval 'echo _wp_image_editor_choose();'`.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/entregables/imagenes.php wp-content/themes/automatiza-tech/inc/entregables/cargar.php tests/entregables/imagenes-wp-test.php
git commit -m "feat(entregables): imágenes de las notas (validar, reescribir sin EXIF, servir con código)"
```

---

### Task 5: Envío (`envio.php`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/entregables/envio.php`
- Modify: `…/entregables/cargar.php` (agregar `require_once __DIR__ . '/envio.php';` después de `imagenes.php`)
- Test: `tests/entregables/envio-wp-test.php`

**Interfaces:**
- Consumes: plantillas (Task 2), datos (Task 3), `at_cc_correo_avisos()`, `at_cc_cabecera_copia()`.
- Produces:
  - `at_en_cliente(object $ent): array` → `['nombre','email','telefono','empresa','titulo']`
  - `at_en_url_ficha(int $crm_id, int $ent_id = 0, string $msg = ''): string` (admin, `#tab-entregables`)
  - `at_en_cabeceras_cliente(string $para): array`
  - `at_en_enviar_version(int $ent_id, int $numero, bool $prueba): array` → `['ok'=>bool,'motivo'=>string]` (motivos: `sin_entregable`, `sin_version`, `sin_correo`, `ya_enviada`, `correo_fallo`)
  - `at_en_avisar_nota(int $nota_id): bool`
  - `at_en_avisar_respuesta(int $nota_id): bool`
  - `at_en_url_wa_version(object $ent, int $numero): string`, `at_en_texto_wa_version(object $ent, int $numero): string`
  - `at_en_url_wa_respuesta(object $ent, int $numero): string`, `at_en_texto_wa_respuesta(object $ent, int $numero): string`
  - `at_en_logo(): string`

- [ ] **Step 1: Escribir la prueba que falla** — `tests/entregables/envio-wp-test.php`

```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/envio-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
exigir('at_en_enviar_version', 'at_en_avisar_nota', 'at_en_avisar_respuesta', 'at_en_url_wa_version');
global $wpdb;
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
$fx = en_fx_cliente($marca);
$id = at_en_activar($fx['detalle']);
at_en_crear_version($id, 'https://example.com/v1', "Revisa:\n- la portada");
$e = at_en_por_id($id);

// Prueba a Luis: no marca ni registra
$GLOBALS['en_correos'] = [];
$r = at_en_enviar_version($id, 1, true);
$c = $GLOBALS['en_correos'][0] ?? [];
ok($r['ok'] && count($GLOBALS['en_correos']) === 1 && $c['to'] === at_cc_correo_avisos() && strpos($c['subject'], '[PRUEBA] ') === 0, 'prueba: a Luis con [PRUEBA]');
ok(at_en_version($id, 1)->enviado_correo_at === null, 'la prueba no marca la versión');

// Envío real
$GLOBALS['en_correos'] = [];
$r = at_en_enviar_version($id, 1, false);
$c = $GLOBALS['en_correos'][0] ?? [];
$cab = implode("\n", (array) ($c['headers'] ?? []));
ok($r['ok'] && $c['to'] === $marca . '@example.com' && $c['subject'] === 'Propuestas ' . $marca . ': versión 1 para revisar', 'al cliente con el asunto de la versión');
ok(strpos($cab, 'Reply-To: ' . at_cc_correo_avisos()) !== false && strpos($cab, 'Bcc: lgonzalez@automatizatech.cl') !== false && strpos($cab, 'From: Automatiza Tech <') !== false, 'cabeceras: From, Reply-To y copia a Luis');
ok(strpos($c['message'], at_en_url_pagina(home_url(), (string) $e->codigo)) !== false && strpos($c['message'], 'https://example.com/v1') !== false, 'correo con la página y la versión');
ok(at_en_version($id, 1)->enviado_correo_at !== null, 'versión marcada enviada');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d AND tipo_evento = 'entregable_version'", $fx['crm'])) === 1, 'historial: versión enviada');
ok(at_en_enviar_version($id, 1, false)['motivo'] === 'ya_enviada', 'segundo envío de la misma versión: ya_enviada');
ok(at_en_enviar_version($id, 7, false)['motivo'] === 'sin_version', 'versión inexistente: sin_version');

// Falla de wp_mail
add_filter('pre_wp_mail', '__return_false', 0);
at_en_crear_version($id, 'https://example.com/v2', '');
$r2 = at_en_enviar_version($id, 2, false);
remove_filter('pre_wp_mail', '__return_false', 0);
ok(!$r2['ok'] && $r2['motivo'] === 'correo_fallo' && at_en_version($id, 2)->enviado_correo_at === null, 'si wp_mail falla: no se marca');

// Sin correo del cliente
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => ''], ['id' => $fx['crm']]);
ok(at_en_enviar_version($id, 2, false)['motivo'] === 'sin_correo', 'cliente sin correo: sin_correo');
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => $marca . '@example.com'], ['id' => $fx['crm']]);

// Aviso de nota a Luis
$n = at_en_agregar_nota($id, 'cliente', 'Cliente', "Me gusta <b>\nla portada", ['abcdefghijklmnopqrstuvwx.jpg'], '');
$GLOBALS['en_correos'] = [];
ok(at_en_avisar_nota($n) && $GLOBALS['en_correos'][0]['to'] === at_cc_correo_avisos() && strpos($GLOBALS['en_correos'][0]['message'], 'Me gusta &lt;b&gt;') !== false, 'aviso de nota a Luis, escapado');
ok(strpos($GLOBALS['en_correos'][0]['message'], 'page=automatiza-crm-ficha') !== false && strpos($GLOBALS['en_correos'][0]['message'], '#tab-entregables') !== false, 'botón a la ficha');

// Aviso de respuesta al cliente
$resp = at_en_agregar_nota($id, 'at', 'Luis', 'Ya la cambié', [], '');
$GLOBALS['en_correos'] = [];
ok(at_en_avisar_respuesta($resp) && $GLOBALS['en_correos'][0]['to'] === $marca . '@example.com' && $GLOBALS['en_correos'][0]['subject'] === 'Respuesta a tu nota sobre Propuestas ' . $marca, 'aviso de respuesta al cliente');
ok(!at_en_avisar_respuesta($n), 'una nota del cliente no se avisa como respuesta');

// WhatsApp
$url = at_en_url_wa_version($e, 1);
ok(strpos($url, 'https://wa.me/56911111111?text=') === 0 && strpos(rawurldecode($url), 'versión 1 de Propuestas ' . $marca) !== false, 'wa.me de la versión al teléfono de la ficha');
ok(strpos(at_en_texto_wa_respuesta($e, 1), 'te respondí tu nota sobre la versión 1') !== false, 'texto de WhatsApp de la respuesta');
fin();
```

- [ ] **Step 2: Correr y ver que falla**

Run: `bash tests/entregables/correr.sh envio`
Expected: `FALLA falta la función at_en_enviar_version()`.

- [ ] **Step 3: Implementar `envio.php`**

```php
<?php
/**
 * Envío por correo (versión, prueba, aviso de nota, aviso de respuesta) y textos/enlaces de WhatsApp que manda Luis.
 */
if (!defined('ABSPATH')) {
	exit;
}

function at_en_logo(): string {
	return defined('AT_CC_LOGO') ? AT_CC_LOGO : home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png');
}

/** Pestaña «Entregables» de la ficha del cliente en el CRM. */
function at_en_url_ficha(int $crm_id, int $ent_id = 0, string $msg = ''): string {
	$args = ['page' => 'automatiza-crm-ficha', 'id' => $crm_id];
	if ($ent_id > 0) {
		$args['en'] = $ent_id;
	}
	if ($msg !== '') {
		$args['en_msg'] = $msg;
	}
	return add_query_arg($args, admin_url('admin.php')) . '#tab-entregables';
}

/** Datos del cliente del CRM y título del entregable. */
function at_en_cliente(object $ent): array {
	global $wpdb;
	$c = $wpdb->get_row($wpdb->prepare("SELECT nombre, email, telefono, empresa FROM {$wpdb->prefix}crm_clientes WHERE id = %d", (int) $ent->crm_id));
	$d = $wpdb->get_row($wpdb->prepare("SELECT title FROM {$wpdb->prefix}automatiza_clients_details WHERE id = %d", (int) $ent->detalle_id));
	$nombre = trim((string) ($c->nombre ?? ''));
	return [
		'nombre'   => $nombre !== '' ? trim(explode(' ', $nombre)[0]) : '',
		'email'    => is_email((string) ($c->email ?? '')) ? (string) $c->email : '',
		'telefono' => (string) ($c->telefono ?? ''),
		'empresa'  => (string) ($c->empresa ?? ''),
		'titulo'   => trim((string) ($d->title ?? '')) !== '' ? trim((string) $d->title) : 'Entregable',
	];
}

function at_en_from(): string {
	return 'From: Automatiza Tech <' . (defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl') . '>';
}

/** Cabeceras de un correo al cliente: Reply-To de avisos y copias ocultas (sin duplicar ni copiar al destinatario). */
function at_en_cabeceras_cliente(string $para): array {
	$h = ['Content-Type: text/html; charset=UTF-8', at_en_from()];
	if (function_exists('at_cc_correo_avisos')) {
		$h[] = 'Reply-To: ' . at_cc_correo_avisos();
	}
	$bcc = [];
	foreach (function_exists('at_cc_cabecera_copia') ? at_cc_cabecera_copia($para) : [] as $linea) {
		$bcc[] = strtolower(trim(substr($linea, 4)));
	}
	$bcc[] = 'lgonzalez@automatizatech.cl';
	foreach (array_unique($bcc) as $b) {
		if ($b !== '' && $b !== strtolower(trim($para))) {
			$h[] = 'Bcc: ' . $b;
		}
	}
	return $h;
}

/** Envía la versión N al cliente (o a Luis si $prueba). Una versión ya enviada por correo no se reenvía. */
function at_en_enviar_version(int $ent_id, int $numero, bool $prueba): array {
	$ent = at_en_por_id($ent_id);
	if (!$ent) {
		return ['ok' => false, 'motivo' => 'sin_entregable'];
	}
	$v = at_en_version($ent_id, $numero);
	if (!$v) {
		return ['ok' => false, 'motivo' => 'sin_version'];
	}
	if (!$prueba && $v->enviado_correo_at !== null) {
		return ['ok' => false, 'motivo' => 'ya_enviada'];
	}
	$cli = at_en_cliente($ent);
	$para = $prueba ? (function_exists('at_cc_correo_avisos') ? at_cc_correo_avisos() : (string) get_option('admin_email')) : $cli['email'];
	if ($para === '') {
		return ['ok' => false, 'motivo' => 'sin_correo'];
	}
	$c = at_en_correo_version([
		'nombre' => $cli['nombre'], 'titulo' => $cli['titulo'], 'numero' => $numero, 'url_version' => (string) $v->url,
		'url_pagina' => at_en_url_pagina(home_url(), (string) $ent->codigo), 'mensaje' => (string) $v->mensaje,
		'logo' => at_en_logo(), 'prueba' => $prueba,
	]);
	$cab = $prueba ? ['Content-Type: text/html; charset=UTF-8', at_en_from()] : at_en_cabeceras_cliente($para);
	if (!wp_mail($para, $c['asunto'], $c['html'], $cab)) {
		return ['ok' => false, 'motivo' => 'correo_fallo'];
	}
	if (!$prueba) {
		at_en_marcar_version_enviada($ent_id, $numero, 'correo');
		at_en_historial((int) $ent->crm_id, 'entregable_version', $cli['titulo'] . ': versión ' . $numero . ' enviada por correo', 'Enlace: ' . $v->url . ' · Entregable ' . $ent_id . '.');
	}
	return ['ok' => true, 'motivo' => ''];
}

/** Aviso a Luis de una nota nueva del cliente. Si falla, la nota queda marcada «sin aviso». */
function at_en_avisar_nota(int $nota_id): bool {
	$n = at_en_nota($nota_id);
	$ent = $n ? at_en_por_id((int) $n->entregable_id) : null;
	if (!$n || !$ent || $n->autor !== 'cliente') {
		return false;
	}
	$cli = at_en_cliente($ent);
	$imgs = json_decode((string) $n->imagenes, true);
	$c = at_en_correo_nota_luis([
		'cliente' => (string) $n->nombre, 'empresa' => $cli['empresa'], 'titulo' => $cli['titulo'], 'numero' => (int) $n->version_numero,
		'texto' => (string) $n->texto, 'imagenes' => is_array($imgs) ? count($imgs) : 0,
		'url_ficha' => at_en_url_ficha((int) $ent->crm_id, (int) $ent->id), 'logo' => at_en_logo(),
	]);
	$para = function_exists('at_cc_correo_avisos') ? at_cc_correo_avisos() : (string) get_option('admin_email');
	$ok = wp_mail($para, $c['asunto'], $c['html'], ['Content-Type: text/html; charset=UTF-8', at_en_from()]);
	at_en_marcar_aviso($nota_id, (bool) $ok);
	return (bool) $ok;
}

/** Aviso al cliente de una respuesta de AT. */
function at_en_avisar_respuesta(int $nota_id): bool {
	$n = at_en_nota($nota_id);
	$ent = $n ? at_en_por_id((int) $n->entregable_id) : null;
	if (!$n || !$ent || $n->autor !== 'at') {
		return false;
	}
	$cli = at_en_cliente($ent);
	if ($cli['email'] === '') {
		return false;
	}
	$c = at_en_correo_respuesta([
		'nombre' => $cli['nombre'], 'titulo' => $cli['titulo'], 'numero' => (int) $n->version_numero, 'texto' => (string) $n->texto,
		'url_pagina' => at_en_url_pagina(home_url(), (string) $ent->codigo), 'logo' => at_en_logo(),
	]);
	return (bool) wp_mail($cli['email'], $c['asunto'], $c['html'], at_en_cabeceras_cliente($cli['email']));
}

function at_en_texto_wa_version(object $ent, int $numero): string {
	$cli = at_en_cliente($ent);
	return at_en_texto_whatsapp_version($cli['nombre'], $numero, $cli['titulo'], at_en_url_pagina(home_url(), (string) $ent->codigo));
}

function at_en_url_wa_version(object $ent, int $numero): string {
	return at_en_url_wa(at_en_cliente($ent)['telefono'], at_en_texto_wa_version($ent, $numero));
}

function at_en_texto_wa_respuesta(object $ent, int $numero): string {
	$cli = at_en_cliente($ent);
	return at_en_texto_whatsapp_respuesta($cli['nombre'], $numero, $cli['titulo'], at_en_url_pagina(home_url(), (string) $ent->codigo));
}

function at_en_url_wa_respuesta(object $ent, int $numero): string {
	return at_en_url_wa(at_en_cliente($ent)['telefono'], at_en_texto_wa_respuesta($ent, $numero));
}
```

Agregar en `cargar.php`: `require_once __DIR__ . '/envio.php';`

- [ ] **Step 4: Correr y ver que pasa**

Run: `bash tests/entregables/correr.sh`
Expected: cinco pruebas en `TODO OK`.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/entregables/envio.php wp-content/themes/automatiza-tech/inc/entregables/cargar.php tests/entregables/envio-wp-test.php
git commit -m "feat(entregables): correos de versión, aviso de nota y respuesta; textos de WhatsApp"
```

---

### Task 6: Página del cliente (`vista.php`, `ver-entregable.php`) y acción `at_en_nota`

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/entregables/vista.php`
- Create: `ver-entregable.php` (raíz del repo)
- Modify: `…/entregables/cargar.php` (agregar `require_once __DIR__ . '/vista.php';`)
- Create: `tests/entregables/accion-run.php` (corre una acción admin-post en un proceso aparte, con usuario 0 o admin, `$_POST` y `$_FILES`)
- Create: `tests/entregables/accion-helpers.php`
- Test: `tests/entregables/vista-wp-test.php`

**Interfaces:**
- Consumes: Tasks 1–5.
- Produces:
  - `at_en_ip_hash(string $ip): string` (sha256 con `wp_salt('nonce')`)
  - `at_en_token_hoy(string $codigo): string`
  - `at_en_url_pagina_msg(object $ent, string $msg): string` (página + `&en_msg=<clave>#notas`)
  - `at_en_html_pagina(?object $ent, string $msg = ''): string`
  - `at_en_accion_nota(): void` (registrada en `admin_post_nopriv_at_en_nota` y `admin_post_at_en_nota`)
  - En pruebas: `en_correr(string $accion, int $usuario, string $nonce_accion, array $post, array $files = []): array` → `['redirect'=>string,'salida'=>string]`

- [ ] **Step 1: Crear el corredor de acciones** — `tests/entregables/accion-run.php`

```php
<?php
// Uso interno: corre admin_post_<accion> en un proceso aparte (las acciones terminan con redirect + exit).
// Argumentos: <accion> <user_id> <accion_del_nonce|-> <json_post> <json_files>
$wp = getenv('AT_WP_LOAD');
[$_, $accion, $usuario, $nonce_accion, $f_post, $f_files] = $argv + [null, '', '0', '-', '', ''];
$_SERVER['HTTP_HOST'] = 'localhost:8093';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-post.php';
$_SERVER['REMOTE_ADDR'] = getenv('EN_IP') ?: '127.0.0.1';
define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require $wp;
wp_set_current_user((int) $usuario);
add_filter('pre_http_request', function () { return new WP_Error('prueba', 'sin red'); }, 1);
add_filter('pre_wp_mail', function ($n, $a) { fwrite(STDOUT, 'MAIL ' . $a['to'] . ' | ' . $a['subject'] . "\n"); return getenv('EN_MAIL_FALLA') ? false : true; }, 1, 2);
add_filter('at_en_es_subida', '__return_true');
add_filter('wp_redirect', function ($u) { fwrite(STDOUT, 'REDIRECT ' . $u . "\n"); return $u; }, PHP_INT_MAX);
$post = json_decode((string) file_get_contents($f_post), true) ?: [];
if ($nonce_accion !== '-') {
	$post['_wpnonce'] = wp_create_nonce($nonce_accion);
}
$_POST = wp_slash($post + ['action' => $accion]);
$_REQUEST = $_POST;
$_FILES = json_decode((string) file_get_contents($f_files), true) ?: [];
$_SERVER['REQUEST_METHOD'] = 'POST';
do_action(((int) $usuario > 0 ? 'admin_post_' : 'admin_post_nopriv_') . $accion);
fwrite(STDOUT, "SIN_EXIT\n");
```

`tests/entregables/accion-helpers.php`:

```php
<?php
function en_correr(string $accion, int $usuario, string $nonce_accion, array $post, array $files = [], array $env = []): array {
	$fp = tempnam(sys_get_temp_dir(), 'en-p-');
	$ff = tempnam(sys_get_temp_dir(), 'en-f-');
	file_put_contents($fp, wp_json_encode($post, JSON_UNESCAPED_UNICODE));
	file_put_contents($ff, wp_json_encode($files));
	$entorno = array_merge(getenv(), $env);
	$proc = proc_open([PHP_BINARY, __DIR__ . '/accion-run.php', $accion, (string) $usuario, $nonce_accion, $fp, $ff], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $entorno);
	$salida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
	@unlink($fp); @unlink($ff);
	wp_cache_flush();
	$r = ['redirect' => '', 'mails' => [], 'salida' => $salida];
	foreach (preg_split('/\r?\n/', $salida) as $l) {
		if (strpos($l, 'REDIRECT ') === 0) { $r['redirect'] = substr($l, 9); }
		if (strpos($l, 'MAIL ') === 0) { $r['mails'][] = substr($l, 5); }
	}
	return $r;
}
function en_query(string $url): array {
	$q = [];
	parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $q);
	$q['#'] = (string) wp_parse_url($url, PHP_URL_FRAGMENT);
	return $q;
}
```

- [ ] **Step 2: Escribir la prueba que falla** — `tests/entregables/vista-wp-test.php`

```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/vista-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/accion-helpers.php';
exigir('at_en_html_pagina', 'at_en_accion_nota', 'at_en_token_hoy');
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
$fx = en_fx_cliente($marca);
$id = at_en_activar($fx['detalle']);
$e = at_en_por_id($id);
$cod = (string) $e->codigo;

// Sin versiones / inexistente / código con otras mayúsculas: igual «no disponible»
$h0 = at_en_html_pagina($e);
ok(strpos($h0, 'Este enlace no está disponible.') !== false && strpos($h0, '<form') === false, 'sin versiones: no disponible');
ok(at_en_por_codigo(strtoupper($cod) === $cod ? strtolower($cod) : strtoupper($cod)) === null && strpos(at_en_html_pagina(null), 'Este enlace no está disponible.') !== false, 'otro código: no disponible');
ok(strpos(at_en_html_pagina(null), 'noindex') !== false, 'noindex en la página');

// Con versión
at_en_crear_version($id, 'https://example.com/v1', 'Primera');
at_en_crear_version($id, 'https://example.com/v2', 'Segunda');
$e = at_en_por_id($id);
$h = at_en_html_pagina($e);
ok(strpos($h, 'Propuestas ' . $marca) !== false && strpos($h, 'Empresa ' . $marca) !== false, 'título y empresa');
ok(strpos($h, 'href="https://example.com/v2"') !== false && strpos($h, 'Abrir la versión 2') !== false, 'botón a la versión vigente');
ok(strpos($h, 'href="https://example.com/v1"') !== false && strpos($h, 'Versión 1') !== false, 'versiones anteriores');
ok(strpos($h, 'name="token" value="' . at_en_token_hoy($cod) . '"') !== false && strpos($h, 'enctype="multipart/form-data"') !== false && strpos($h, 'accept="image/jpeg,image/png"') !== false, 'formulario con token y 0 a 3 imágenes');
ok(strpos($h, 'value="Cliente"') !== false, 'nombre precargado con el de la ficha');

// Nota del cliente por la acción (sin sesión)
$base = ['codigo' => $cod, 'token' => at_en_token_hoy($cod), 'nombre' => 'Cliente', 'texto' => "Me gusta <script>alert(1)</script>\ny la v2"];
$r = en_correr('at_en_nota', 0, '-', $base);
$q = en_query($r['redirect']);
ok(($q['en_msg'] ?? '') === 'nota_ok' && ($q['id'] ?? '') === $cod && $q['#'] === 'notas', 'nota guardada: vuelve con nota_ok#notas');
ok(count(at_en_notas($id)) === 1 && at_en_notas($id)[0]->version_numero == 2, 'nota guardada sobre la v2');
ok(count($r['mails']) === 1 && strpos($r['mails'][0], 'dejó una nota') !== false, 'aviso a Luis enviado');
$h2 = at_en_html_pagina(at_en_por_id($id));
ok(strpos($h2, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false && strpos($h2, '<script>alert(1)') === false, 'la nota se ve escapada');
ok(strpos($h2, 'sobre la versión 2') !== false, 'la nota dice sobre qué versión');

// Sin imágenes está bien; con imagen real también
$tmp = sys_get_temp_dir() . '/en-v-' . $marca . '.png';
$im = imagecreatetruecolor(50, 40); imagepng($im, $tmp); imagedestroy($im);
$files = ['imagenes' => ['name' => ['a.png'], 'type' => ['image/png'], 'tmp_name' => [$tmp], 'error' => [0], 'size' => [filesize($tmp)]]];
$r = en_correr('at_en_nota', 0, '-', ['texto' => 'Con foto'] + $base, $files);
$ult = array_values(at_en_notas($id))[1] ?? null;
ok(en_query($r['redirect'])['en_msg'] === 'nota_ok' && $ult && count(json_decode((string) $ult->imagenes, true)) === 1, 'nota con 1 imagen');
$img = json_decode((string) $ult->imagenes, true)[0];
ok(strpos(at_en_html_pagina(at_en_por_id($id)), 'ver-entregable.php?id=' . $cod . '&amp;img=' . $img) !== false, 'miniatura servida por la página');

// Errores
ok(en_query(en_correr('at_en_nota', 0, '-', ['token' => 'f00'] + $base)['redirect'])['en_msg'] === 'sesion_vencida', 'token malo: sesion_vencida');
ok(en_query(en_correr('at_en_nota', 0, '-', ['texto' => ''] + $base)['redirect'])['en_msg'] === 'nota_vacia', 'nota vacía');
file_put_contents($tmp . '.jpg', '<?php echo 1;');
$malo = ['imagenes' => ['name' => ['x.jpg'], 'type' => ['image/jpeg'], 'tmp_name' => [$tmp . '.jpg'], 'error' => [0], 'size' => [12]]];
$rm = en_correr('at_en_nota', 0, '-', $base, $malo);
ok(en_query($rm['redirect'])['en_msg'] === 'imagen' && count(at_en_notas($id)) === 2, 'imagen falsa: no se guarda la nota');
ok(strpos(at_en_html_pagina(at_en_por_id($id), 'imagen'), 'Una de las imágenes no sirve.') !== false, 'mensaje de imagen en la página');
$qx = en_query(en_correr('at_en_nota', 0, '-', ['codigo' => 'NoExiste1234'] + $base)['redirect']);
ok(($qx['en_msg'] ?? '') === 'no_disponible', 'código inexistente');

// Límite de 10 por hora (por IP)
$ip = ['EN_IP' => '10.9.8.' . rand(1, 250)];
$msgs = [];
for ($i = 0; $i < 11; $i++) {
	$msgs[] = en_query(en_correr('at_en_nota', 0, '-', ['texto' => "n$i"] + $base, [], $ip)['redirect'])['en_msg'] ?? '';
}
ok(count(array_filter($msgs, function ($m) { return $m === 'nota_ok'; })) === 10 && end($msgs) === 'muchos_intentos', 'la 11.ª nota en una hora: muchos_intentos');

// Cerrado
at_en_cambiar_estado($id, 'cerrado');
$hc = at_en_html_pagina(at_en_por_id($id));
ok(strpos($hc, 'Este entregable ya está cerrado.') !== false && strpos($hc, '<form') === false && strpos($hc, 'Me gusta') !== false, 'cerrado: muestra historial sin formulario');
ok(en_query(en_correr('at_en_nota', 0, '-', $base)['redirect'])['en_msg'] === 'cerrado', 'POST a un cerrado: cerrado');
@unlink($tmp); @unlink($tmp . '.jpg');
fin();
```

- [ ] **Step 3: Correr y ver que falla**

Run: `bash tests/entregables/correr.sh vista`
Expected: `FALLA falta la función at_en_html_pagina()`.

- [ ] **Step 4: Implementar `vista.php`**

```php
<?php
/**
 * Página pública del entregable (ver-entregable.php) y la acción del formulario de notas (cliente, sin sesión).
 */
if (!defined('ABSPATH')) {
	exit;
}

function at_en_ip_hash(string $ip): string {
	return hash('sha256', $ip . '|' . wp_salt('nonce'));
}

function at_en_token_hoy(string $codigo): string {
	return at_en_token($codigo, intdiv(time(), 86400), wp_salt('nonce'));
}

function at_en_url_pagina_msg(object $ent, string $msg): string {
	return at_en_url_pagina(home_url(), (string) $ent->codigo) . '&en_msg=' . rawurlencode($msg) . '#notas';
}

function at_en_fecha(string $mysql): string {
	$t = strtotime($mysql);
	return $t ? date_i18n('j \d\e F \d\e Y, H:i', $t) : '';
}

/** HTML completo de la página. $ent null o sin versiones = «no disponible». $msg = clave de at_en_mensajes(). */
function at_en_html_pagina(?object $ent, string $msg = ''): string {
	$m = at_en_mensajes();
	$h = function ($s) { return esc_html((string) $s); };
	$cab = '<!DOCTYPE html><html lang="es-CL"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Entregable — AutomatizaTech</title><style>'
		. 'body{margin:0;background:#f0f2f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937}.caja{max-width:760px;margin:0 auto;padding:16px}'
		. '.cab{background:linear-gradient(135deg,#1e3a8a,#06d6a0);color:#fff;border-radius:12px;padding:22px;text-align:center}.cab img{max-width:120px}'
		. '.tarjeta{background:#fff;border-radius:12px;padding:18px;margin-top:14px;box-shadow:0 1px 4px rgba(0,0,0,.06)}'
		. '.btn{display:inline-block;background:#1e3a8a;color:#fff;text-decoration:none;font-weight:bold;padding:12px 24px;border-radius:25px;border:0;font-size:16px;cursor:pointer}'
		. '.nota{border-radius:12px;padding:12px 14px;margin:10px 0;max-width:88%}.cli{background:#eef2ff;margin-right:auto}.at{background:#ecfdf5;margin-left:auto}'
		. '.meta{font-size:12px;color:#6b7280;margin-bottom:4px}.imgs img{max-width:120px;max-height:120px;border-radius:8px;margin:6px 6px 0 0;border:1px solid #e5e7eb}'
		. 'label{display:block;font-weight:bold;margin:12px 0 4px}input[type=text],textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:16px}textarea{min-height:120px}'
		. '.aviso{padding:12px;border-radius:8px;margin-top:12px}.ok{background:#ecfdf5;color:#065f46}.err{background:#fef2f2;color:#991b1b}'
		. '</style></head><body><div class="caja"><div class="cab"><img src="' . esc_url(at_en_logo()) . '" alt="AutomatizaTech">';
	$pie = '<p style="text-align:center;color:#6b7280;font-size:13px;margin:22px 0">© ' . date('Y') . ' AutomatizaTech · <a href="https://automatizatech.cl/">automatizatech.cl</a></p></div></body></html>';
	if (!$ent || (int) $ent->version_vigente < 1) {
		return $cab . '<h1 style="font-size:20px">' . $h($m['no_disponible']) . '</h1></div>' . $pie;
	}
	$cli = at_en_cliente($ent);
	$versiones = at_en_versiones((int) $ent->id);
	$vig = at_en_version((int) $ent->id, (int) $ent->version_vigente);
	$html = $cab . '<h1 style="margin:10px 0 4px;font-size:22px">' . $h($cli['titulo']) . '</h1><div>' . $h($cli['empresa']) . '</div></div>';
	$html .= '<div class="tarjeta"><p style="margin-top:0">Versión vigente: <strong>' . (int) $ent->version_vigente . '</strong> · ' . $h(at_en_fecha((string) $vig->creado_at)) . '</p>'
		. '<p><a class="btn" href="' . esc_url((string) $vig->url) . '" target="_blank" rel="noopener">Abrir la versión ' . (int) $ent->version_vigente . '</a></p>';
	$anteriores = array_filter($versiones, function ($v) use ($ent) { return (int) $v->numero !== (int) $ent->version_vigente; });
	if ($anteriores) {
		$html .= '<p style="margin-bottom:4px"><strong>Versiones anteriores</strong></p><ul>';
		foreach (array_reverse($anteriores) as $v) {
			$html .= '<li><a href="' . esc_url((string) $v->url) . '" target="_blank" rel="noopener">Versión ' . (int) $v->numero . '</a> · ' . $h(at_en_fecha((string) $v->creado_at)) . '</li>';
		}
		$html .= '</ul>';
	}
	$html .= '</div><div class="tarjeta" id="notas"><h2 style="margin-top:0;font-size:18px">Notas y observaciones</h2>';
	$notas = at_en_notas((int) $ent->id);
	if (!$notas) {
		$html .= '<p style="color:#6b7280">Todavía no hay notas.</p>';
	}
	foreach ($notas as $n) {
		$imgs = json_decode((string) $n->imagenes, true);
		$html .= '<div class="nota ' . ($n->autor === 'at' ? 'at' : 'cli') . '"><div class="meta">' . $h($n->autor === 'at' ? 'AutomatizaTech · ' . $n->nombre : $n->nombre) . ' · sobre la versión ' . (int) $n->version_numero . ' · ' . $h(at_en_fecha((string) $n->creado_at)) . '</div>'
			. nl2br(esc_html((string) $n->texto));
		if (is_array($imgs) && $imgs) {
			$html .= '<div class="imgs">';
			foreach ($imgs as $img) {
				$u = at_en_url_imagen(home_url(), (string) $ent->codigo, (string) $img);
				if ($u !== '') {
					$html .= '<a href="' . esc_url($u) . '" target="_blank" rel="noopener"><img src="' . esc_url($u) . '" alt="Imagen adjunta" loading="lazy"></a>';
				}
			}
			$html .= '</div>';
		}
		$html .= '</div>';
	}
	if ($msg !== '' && isset($m[$msg])) {
		$html .= '<div class="aviso ' . ($msg === 'nota_ok' ? 'ok' : 'err') . '">' . $h($m[$msg]) . '</div>';
	}
	if ($ent->estado !== 'abierto') {
		$html .= '<div class="aviso err">' . $h($m['cerrado']) . '</div></div>';
		return $html . $pie;
	}
	$html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">'
		. '<input type="hidden" name="action" value="at_en_nota"><input type="hidden" name="codigo" value="' . esc_attr((string) $ent->codigo) . '">'
		. '<input type="hidden" name="token" value="' . esc_attr(at_en_token_hoy((string) $ent->codigo)) . '">'
		. '<label for="en-nombre">Tu nombre</label><input type="text" id="en-nombre" name="nombre" maxlength="80" required value="' . esc_attr($cli['nombre']) . '">'
		. '<label for="en-texto">Tu nota u observación</label><textarea id="en-texto" name="texto" maxlength="3000" required></textarea>'
		. '<label for="en-img">Imágenes (opcional, hasta 3; JPG o PNG de hasta 5 MB)</label><input type="file" id="en-img" name="imagenes[]" accept="image/jpeg,image/png" multiple>'
		. '<p><button class="btn" type="submit">Enviar nota</button></p></form></div>';
	return $html . $pie;
}

/** Acción del formulario (cliente sin sesión o Luis con sesión). Siempre vuelve a la página con en_msg. */
function at_en_accion_nota(): void {
	$codigo = preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_POST['codigo'] ?? ''));
	$ent = at_en_por_codigo((string) $codigo);
	if (!$ent || (int) $ent->version_vigente < 1) {
		wp_safe_redirect(home_url('/ver-entregable.php?id=' . rawurlencode((string) $codigo) . '&en_msg=no_disponible'));
		exit;
	}
	$volver = function (string $msg) use ($ent) {
		wp_safe_redirect(at_en_url_pagina_msg($ent, $msg));
		exit;
	};
	if ($ent->estado !== 'abierto') {
		$volver('cerrado');
	}
	if (!at_en_token_valido((string) wp_unslash($_POST['token'] ?? ''), (string) $ent->codigo, intdiv(time(), 86400), wp_salt('nonce'))) {
		$volver('sesion_vencida');
	}
	$ip = at_en_ip_hash((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
	$clave = 'at_en_lim_' . (int) $ent->id . '_' . substr($ip, 0, 20);
	$intentos = (int) get_transient($clave);
	if ($intentos >= AT_EN_NOTAS_POR_HORA) {
		$volver('muchos_intentos');
	}
	$v = at_en_validar_nota((string) wp_unslash($_POST['nombre'] ?? ''), (string) wp_unslash($_POST['texto'] ?? ''));
	if (!$v['ok']) {
		$volver($v['error']);
	}
	$archivos = at_en_archivos_subidos(isset($_FILES['imagenes']) && is_array($_FILES['imagenes']) ? $_FILES['imagenes'] : []);
	$imagenes = $archivos ? at_en_procesar_imagenes($archivos, (int) $ent->id) : [];
	if (is_wp_error($imagenes)) {
		$volver($imagenes->get_error_code() === 'muchas_imagenes' ? 'muchas_imagenes' : 'imagen');
	}
	$id = at_en_agregar_nota((int) $ent->id, 'cliente', $v['nombre'], $v['texto'], $imagenes, $ip);
	if (is_wp_error($id)) {
		$volver('no_guardo');
	}
	set_transient($clave, $intentos + 1, HOUR_IN_SECONDS);
	$cli = at_en_cliente($ent);
	at_en_historial((int) $ent->crm_id, 'entregable_nota', $cli['titulo'] . ': nota del cliente sobre la versión ' . (int) $ent->version_vigente, at_en_extracto($v['texto'], 300) . ($imagenes ? ' (' . count($imagenes) . ' imagen/es)' : ''));
	at_en_avisar_nota((int) $id);
	$volver('nota_ok');
}
add_action('admin_post_nopriv_at_en_nota', 'at_en_accion_nota');
add_action('admin_post_at_en_nota', 'at_en_accion_nota');
```

- [ ] **Step 5: Crear `ver-entregable.php` en la raíz**

```php
<?php
// ver-entregable.php?id=<código>[&img=<nombre>][&en_msg=<clave>] — entregable del cliente con sus versiones y notas.
// La lógica vive en wp-content/themes/automatiza-tech/inc/entregables/vista.php.
require_once __DIR__ . '/wp-load.php';

$codigo = isset($_GET['id']) && is_string($_GET['id']) ? preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_GET['id'])) : '';
if (!defined('DONOTCACHEPAGE')) {
	define('DONOTCACHEPAGE', true);
}
nocache_headers();
header('X-Robots-Tag: noindex, nofollow');
if (!function_exists('at_en_html_pagina')) {
	status_header(503);
	exit('Entregable no disponible por ahora.');
}
$ent = at_en_por_codigo((string) $codigo);
if ($ent && isset($_GET['img']) && is_string($_GET['img'])) {
	at_en_servir_imagen($ent, (string) wp_unslash($_GET['img']));
}
if (!$ent || (int) $ent->version_vigente < 1) {
	status_header(404);
	$ent = null;
}
$msg = isset($_GET['en_msg']) && is_string($_GET['en_msg']) ? sanitize_key(wp_unslash($_GET['en_msg'])) : '';
echo at_en_html_pagina($ent, $msg);
```

Enlazar el archivo al sitio local (es un archivo, no una carpeta): `cmd /c mklink /H "$SCR\wp-local-plan\ver-entregable.php" "$WT\ver-entregable.php"`. Agregar `require_once __DIR__ . '/vista.php';` en `cargar.php`.

- [ ] **Step 6: Correr y ver que pasa**

Run: `bash tests/entregables/correr.sh`
Expected: seis pruebas en `TODO OK`.

- [ ] **Step 7: Revisión visual en el navegador**

Agregar a `.claude/launch.json` del worktree una configuración `wp-local-entregables` que sirva `$SCR/wp-local-plan` en el puerto 8093 con PHP (`php -S localhost:8093 -t <ruta>`), abrirla con `preview_start`, crear un entregable de prueba con `datos-prueba` (script `tests/entregables/sembrar.php` que llama a `en_fx_cliente('vista')`, `at_en_activar`, `at_en_crear_version` e imprime el código), y abrir `/ver-entregable.php?id=<código>` a 375 px y en escritorio. Revisar: botón legible, formulario sin desbordar, burbujas alineadas. Captura de pantalla para el PR. Borrar con `en_fx_limpiar('vista')`.

- [ ] **Step 8: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/entregables/vista.php wp-content/themes/automatiza-tech/inc/entregables/cargar.php ver-entregable.php tests/entregables/accion-run.php tests/entregables/accion-helpers.php tests/entregables/vista-wp-test.php
git commit -m "feat(entregables): página del cliente con versiones y notas"
```

---

### Task 7: Panel en la ficha del CRM (`panel.php`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/entregables/panel.php`
- Modify: `…/entregables/cargar.php` (agregar `require_once __DIR__ . '/panel.php';`)
- Test: `tests/entregables/panel-wp-test.php`

**Interfaces:**
- Consumes: Tasks 1–6.
- Produces:
  - `at_en_render_pestana(array $cliente): void` (`$cliente['id']` = id del CRM)
  - `at_en_html_resumen_portal(array $item): string` (para el portal; `''` si no aplica)
  - `at_en_marca_lista(int $crm_id): string` (`''` sin pendientes)
  - Acciones `admin_post_`: `at_en_activar` (POST `detalle_id`, `crm_id`), `at_en_version_crear` (`entregable_id`, `url`, `mensaje`), `at_en_version_prueba` / `at_en_version_enviar` / `at_en_version_whatsapp` (`entregable_id`, `numero`), `at_en_responder` (`entregable_id`, `nota_id`, `texto`, `avisar`, `imagenes[]`), `at_en_estado` (`entregable_id`, `estado`)
  - `at_en_mensajes_panel(): array`

- [ ] **Step 1: Escribir la prueba que falla** — `tests/entregables/panel-wp-test.php`

```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/panel-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/accion-helpers.php';
exigir('at_en_render_pestana', 'at_en_html_resumen_portal', 'at_en_marca_lista');
global $wpdb;
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
$admin = en_admin_id();
$fx = en_fx_cliente($marca);
$sin_permiso = wp_insert_user(['user_login' => 'sub' . $marca, 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
register_shutdown_function(function () use ($sin_permiso) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($sin_permiso); });

$pestana = function () use ($fx, $admin) { wp_set_current_user($admin); ob_start(); at_en_render_pestana(['id' => $fx['crm']]); return ob_get_clean(); };
ok(strpos($pestana(), 'Activar notas del cliente') !== false && strpos($pestana(), 'Propuestas ' . $marca) !== false, 'lista con botón Activar');

// Activar: sin permiso / nonce malo / bien
$r = en_correr('at_en_activar', (int) $sin_permiso, 'at_en_activar_' . $fx['detalle'], ['detalle_id' => $fx['detalle'], 'crm_id' => $fx['crm']]);
ok(at_en_por_detalle($fx['detalle']) === null, 'sin manage_options no activa');
$r = en_correr('at_en_activar', $admin, 'otro_nonce', ['detalle_id' => $fx['detalle'], 'crm_id' => $fx['crm']]);
ok(at_en_por_detalle($fx['detalle']) === null, 'nonce malo no activa');
$r = en_correr('at_en_activar', $admin, 'at_en_activar_' . $fx['detalle'], ['detalle_id' => $fx['detalle'], 'crm_id' => $fx['crm']]);
$e = at_en_por_detalle($fx['detalle']);
$q = en_query($r['redirect']);
ok($e && ($q['en_msg'] ?? '') === 'activado' && $q['#'] === 'tab-entregables' && (int) $q['en'] === (int) $e->id, 'activar y volver a la pestaña');
$id = (int) $e->id;

// Nueva versión
$r = en_correr('at_en_version_crear', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'url' => 'https://example.com/v1', 'mensaje' => "Hola\n- portada"]);
ok(en_query($r['redirect'])['en_msg'] === 'version_creada' && (int) at_en_por_id($id)->version_vigente === 1, 'versión 1 creada');
$r = en_correr('at_en_version_crear', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'url' => 'https://x.easypanel.host/', 'mensaje' => '']);
ok(en_query($r['redirect'])['en_msg'] === 'url_invalida', 'URL easypanel: url_invalida');
$html = $pestana();
ok(strpos($html, 'srcdoc=') !== false && strpos($html, 'Enviarme una prueba') !== false && strpos($html, 'Enviar al cliente por correo') !== false && strpos($html, 'Enviar por mi WhatsApp') !== false, 'vista previa y botones de envío');
ok(strpos($html, esc_html(at_en_texto_wa_version(at_en_por_id($id), 1))) !== false, 'texto de WhatsApp para copiar');

// Prueba / envío / WhatsApp
$r = en_correr('at_en_version_prueba', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 1]);
ok(en_query($r['redirect'])['en_msg'] === 'prueba_enviada' && strpos($r['mails'][0] ?? '', '[PRUEBA]') !== false, 'prueba a Luis');
$r = en_correr('at_en_version_enviar', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 1]);
ok(en_query($r['redirect'])['en_msg'] === 'version_enviada' && at_en_version($id, 1)->enviado_correo_at !== null, 'enviada al cliente');
$r = en_correr('at_en_version_enviar', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 1]);
ok(en_query($r['redirect'])['en_msg'] === 'ya_enviada', 'doble clic: ya_enviada');
$r = en_correr('at_en_version_whatsapp', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 1]);
ok(strpos($r['redirect'], 'https://wa.me/56911111111?text=') === 0 && at_en_version($id, 1)->enviado_whatsapp_at !== null, 'WhatsApp abre wa.me y marca');

// Responder
$n = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Cambia el color', [], '');
ok(strpos($pestana(), '🔴 1 nota sin responder') !== false && at_en_marca_lista($fx['crm']) !== '', 'pendiente visible en la pestaña y en la lista');
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n, 'texto' => 'Listo, cambiado', 'avisar' => '1']);
ok(en_query($r['redirect'])['en_msg'] === 'respuesta_ok' && at_en_sin_responder($id) === 0, 'respuesta guardada y nota respondida');
ok(count(array_filter($r['mails'], function ($m) use ($marca) { return strpos($m, $marca . '@example.com') === 0; })) === 1, 'aviso al cliente con «Avisar»');
$n2 = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Otra', [], '');
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n2, 'texto' => 'Ok']);
ok(en_query($r['redirect'])['en_msg'] === 'respuesta_ok' && $r['mails'] === [], 'sin «Avisar»: no sale correo');
$otro = en_fx_cliente($marca . 'b');
$id2 = at_en_activar($otro['detalle']);
at_en_crear_version($id2, 'https://example.com/o', '');
$n3 = at_en_agregar_nota($id2, 'cliente', 'Otro', 'Nota ajena', [], '');
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n3, 'texto' => 'x']);
ok(en_query($r['redirect'])['en_msg'] === 'nota_ajena' && at_en_sin_responder($id2) === 1, 'nota de otro entregable: rechazada');
en_fx_limpiar($marca . 'b');

// Cerrar / reabrir
$r = en_correr('at_en_estado', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'estado' => 'cerrado']);
ok(en_query($r['redirect'])['en_msg'] === 'cerrado_ok' && at_en_por_id($id)->estado === 'cerrado', 'cerrar');
$r = en_correr('at_en_estado', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'estado' => 'abierto']);
ok(at_en_por_id($id)->estado === 'abierto', 'reabrir');

// Línea de tiempo en la pestaña
$html = $pestana();
ok(strpos($html, 'v1 enviada') !== false && strpos($html, 'Cambia el color') !== false && strpos($html, 'Listo, cambiado') !== false, 'línea de tiempo con versiones, notas y respuestas');
ok(strpos($html, at_en_url_pagina(home_url(), (string) at_en_por_id($id)->codigo)) !== false, 'enlace para copiar');

// Resumen del portal
$item = ['id' => $fx['detalle'], 'source' => 'client', 'detail_type' => 'entregable'];
$res = at_en_html_resumen_portal($item);
ok(strpos($res, 'Versión 1') !== false && strpos($res, '4 notas') !== false && strpos($res, 'Ver el detalle y las notas →') !== false, 'resumen con versión, notas y botón');
ok(strpos($res, 'Cambia el color') === false, 'el portal no muestra el texto de las notas');
ok(at_en_html_resumen_portal(['id' => $fx['detalle'], 'source' => 'prospect', 'detail_type' => 'entregable']) === '' && at_en_html_resumen_portal(['id' => 999999999, 'source' => 'client', 'detail_type' => 'entregable']) === '', 'otro origen o sin activar: nada');
fin();
```

- [ ] **Step 2: Correr y ver que falla**

Run: `bash tests/entregables/correr.sh panel`
Expected: `FALLA falta la función at_en_render_pestana()`.

- [ ] **Step 3: Implementar `panel.php`**

```php
<?php
/**
 * Pestaña «📦 Entregables» de la ficha del cliente en el CRM (crm-ai-completo.php la dibuja con at_en_render_pestana()),
 * resumen en el portal del cliente, marca en la lista del CRM y acciones admin-post (manage_options + nonce at_en_<id>).
 */
if (!defined('ABSPATH')) {
	exit;
}

function at_en_mensajes_panel(): array {
	return [
		'activado'        => ['ok', 'Notas activadas. Ahora crea la versión 1.'],
		'version_creada'  => ['ok', 'Versión creada. Revisa la vista previa y envíala.'],
		'prueba_enviada'  => ['ok', 'Te enviamos la prueba a tu correo.'],
		'version_enviada' => ['ok', 'Versión enviada al cliente por correo.'],
		'ya_enviada'      => ['err', 'Esa versión ya se había enviado por correo.'],
		'respuesta_ok'    => ['ok', 'Respuesta guardada.'],
		'cerrado_ok'      => ['ok', 'Entregable cerrado.'],
		'abierto_ok'      => ['ok', 'Entregable reabierto.'],
		'url_invalida'    => ['err', 'El enlace debe empezar con http(s):// y no puede ser de easypanel.'],
		'mensaje_largo'   => ['err', 'El mensaje es muy largo (máximo 6.000 caracteres).'],
		'sin_correo'      => ['err', 'El cliente no tiene un correo válido en su ficha.'],
		'sin_telefono'    => ['err', 'El cliente no tiene un teléfono válido en su ficha.'],
		'correo_fallo'    => ['err', 'No se pudo enviar el correo. Revisa el SMTP e inténtalo de nuevo.'],
		'nota_ajena'      => ['err', 'Esa nota no es de este entregable.'],
		'error'           => ['err', 'No se pudo completar la acción.'],
	] + array_map(function ($t) { return ['err', $t]; }, at_en_mensajes());
}

function at_en_volver(int $crm, int $ent, string $msg): void {
	wp_safe_redirect(at_en_url_ficha($crm, $ent, $msg));
	exit;
}

/** Permiso, nonce y entregable de una acción. Devuelve el entregable. */
function at_en_accion_entregable(): object {
	$id = absint($_POST['entregable_id'] ?? 0);
	check_admin_referer('at_en_' . $id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$e = at_en_por_id($id);
	if (!$e) {
		at_en_volver(absint($_POST['crm_id'] ?? 0), 0, 'error');
	}
	return $e;
}

function at_en_form(string $accion, int $ent_id, string $campos, string $boton, string $extra = ''): string {
	return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin:4px 6px 4px 0"' . $extra . '>'
		. '<input type="hidden" name="action" value="' . esc_attr($accion) . '"><input type="hidden" name="entregable_id" value="' . $ent_id . '">'
		. wp_nonce_field('at_en_' . $ent_id, '_wpnonce', true, false) . $campos
		. '<button type="submit" class="button">' . esc_html($boton) . '</button></form>';
}

function at_en_render_pestana(array $cliente): void {
	$crm = (int) ($cliente['id'] ?? 0);
	$msg = sanitize_key(wp_unslash($_GET['en_msg'] ?? ''));
	$abierto = absint($_GET['en'] ?? 0);
	$mp = at_en_mensajes_panel();
	echo '<h3>📦 Entregables</h3>';
	if ($msg !== '' && isset($mp[$msg])) {
		echo '<div class="notice notice-' . ($mp[$msg][0] === 'ok' ? 'success' : 'error') . ' inline"><p>' . esc_html($mp[$msg][1]) . '</p></div>';
	}
	$lista = at_en_de_crm($crm);
	if (!$lista) {
		echo '<p>Este cliente no tiene entregables. Créalos en «Contratos y operación» con el tipo «Entregable».</p>';
		return;
	}
	foreach ($lista as $d) {
		$en_id = (int) ($d->en_id ?? 0);
		echo '<div style="border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin:10px 0;background:#fff">';
		echo '<strong>' . esc_html((string) $d->title) . '</strong>';
		if ($en_id <= 0) {
			echo ' <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">'
				. '<input type="hidden" name="action" value="at_en_activar"><input type="hidden" name="detalle_id" value="' . (int) $d->id . '"><input type="hidden" name="crm_id" value="' . $crm . '">'
				. wp_nonce_field('at_en_activar_' . (int) $d->id, '_wpnonce', true, false) . '<button class="button">Activar notas del cliente</button></form></div>';
			continue;
		}
		$e = at_en_por_id($en_id);
		$pend = at_en_sin_responder($en_id);
		echo ' · ' . esc_html($e->estado === 'abierto' ? 'Abierto' : 'Cerrado') . ' · versión ' . (int) $e->version_vigente
			. ($pend > 0 ? ' · <span style="color:#b91c1c;font-weight:bold">🔴 ' . $pend . ($pend === 1 ? ' nota sin responder' : ' notas sin responder') . '</span>' : '');
		$url = at_en_url_pagina(home_url(), (string) $e->codigo);
		echo '<p>Enlace del cliente: <input type="text" readonly value="' . esc_attr($url) . '" style="width:60%" onclick="this.select()"></p>';
		echo '<details' . ($abierto === $en_id ? ' open' : '') . '><summary>Línea de tiempo y acciones</summary>';
		at_en_render_detalle($e, $crm);
		echo '</details></div>';
	}
}

/** Línea de tiempo y acciones de un entregable. */
function at_en_render_detalle(object $e, int $crm): void {
	$id = (int) $e->id;
	$eventos = [];
	foreach (at_en_versiones($id) as $v) {
		$canales = array_filter([$v->enviado_correo_at ? 'correo' : '', $v->enviado_whatsapp_at ? 'WhatsApp' : '']);
		$eventos[] = [(string) $v->creado_at, 0, '<div style="background:#eef2ff;padding:8px;border-radius:6px;margin:6px 0"><strong>v' . (int) $v->numero . ' ' . ($canales ? 'enviada (' . esc_html(implode(' + ', $canales)) . ')' : 'creada, sin enviar') . '</strong> · ' . esc_html(at_en_fecha((string) $v->creado_at))
			. ' · <a href="' . esc_url((string) $v->url) . '" target="_blank" rel="noopener">abrir</a>'
			. ((string) $v->mensaje !== '' ? '<details><summary>Mensaje</summary>' . at_en_mensaje_html((string) $v->mensaje) . '</details>' : '') . '</div>'];
	}
	foreach (at_en_notas($id) as $n) {
		$imgs = json_decode((string) $n->imagenes, true);
		$mini = '';
		foreach (is_array($imgs) ? $imgs : [] as $img) {
			$u = at_en_url_imagen(home_url(), (string) $e->codigo, (string) $img);
			$mini .= $u !== '' ? '<a href="' . esc_url($u) . '" target="_blank" rel="noopener"><img src="' . esc_url($u) . '" alt="" style="max-width:90px;max-height:90px;margin:4px;border-radius:4px"></a>' : '';
		}
		$es_cli = $n->autor === 'cliente';
		$html = '<div style="background:' . ($es_cli ? '#fff7ed' : '#ecfdf5') . ';padding:8px;border-radius:6px;margin:6px 0 6px ' . ($es_cli ? '0' : '40px') . '">'
			. '<small>' . esc_html(($es_cli ? '' : 'AT · ') . $n->nombre) . ' · sobre la versión ' . (int) $n->version_numero . ' · ' . esc_html(at_en_fecha((string) $n->creado_at))
			. ($es_cli && !(int) $n->aviso_ok ? ' · <span style="color:#b45309">sin aviso por correo</span>' : '') . '</small><br>'
			. nl2br(esc_html((string) $n->texto)) . ($mini !== '' ? '<div>' . $mini . '</div>' : '');
		if ($es_cli && !(int) $n->respondida) {
			$html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" style="margin-top:6px">'
				. '<input type="hidden" name="action" value="at_en_responder"><input type="hidden" name="entregable_id" value="' . $id . '"><input type="hidden" name="nota_id" value="' . (int) $n->id . '">'
				. wp_nonce_field('at_en_' . $id, '_wpnonce', true, false)
				. '<textarea name="texto" rows="3" style="width:100%" maxlength="3000" required placeholder="Tu respuesta"></textarea>'
				. '<input type="file" name="imagenes[]" accept="image/jpeg,image/png" multiple> '
				. '<label><input type="checkbox" name="avisar" value="1" checked> Avisar al cliente por correo</label> '
				. '<button class="button button-primary">Responder</button></form>';
		}
		$eventos[] = [(string) $n->creado_at, 1, $html . '</div>'];
	}
	usort($eventos, function ($a, $b) { return strcmp($a[0], $b[0]) ?: $a[1] <=> $b[1]; });
	foreach ($eventos as $ev) {
		echo $ev[2];
	}
	// Última respuesta de AT: texto de WhatsApp para avisar.
	$ult_at = null;
	foreach (at_en_notas($id) as $n) {
		if ($n->autor === 'at') {
			$ult_at = $n;
		}
	}
	if ($ult_at) {
		$t = at_en_texto_wa_respuesta($e, (int) $ult_at->version_numero);
		$wa = at_en_url_wa_respuesta($e, (int) $ult_at->version_numero);
		echo '<p><strong>Avisar tu última respuesta por WhatsApp:</strong><br><textarea readonly rows="2" style="width:100%" onclick="this.select()">' . esc_textarea($t) . '</textarea>'
			. ($wa !== '' ? '<a class="button" href="' . esc_url($wa) . '" target="_blank" rel="noopener">Abrir mi WhatsApp</a>' : '') . '</p>';
	}
	// Versión vigente sin enviar por correo: vista previa y envío.
	$vig = (int) $e->version_vigente > 0 ? at_en_version($id, (int) $e->version_vigente) : null;
	if ($vig) {
		$cli = at_en_cliente($e);
		$c = at_en_correo_version(['nombre' => $cli['nombre'], 'titulo' => $cli['titulo'], 'numero' => (int) $vig->numero, 'url_version' => (string) $vig->url, 'url_pagina' => at_en_url_pagina(home_url(), (string) $e->codigo), 'mensaje' => (string) $vig->mensaje, 'logo' => at_en_logo(), 'prueba' => false]);
		echo '<h4>Versión ' . (int) $vig->numero . ': vista previa del correo</h4><p>Asunto: <strong>' . esc_html($c['asunto']) . '</strong></p>'
			. '<iframe srcdoc="' . esc_attr($c['html']) . '" style="width:100%;height:520px;border:1px solid #e2e8f0;border-radius:6px;background:#fff" sandbox></iframe>';
		$num = '<input type="hidden" name="numero" value="' . (int) $vig->numero . '">';
		echo '<p>' . at_en_form('at_en_version_prueba', $id, $num, 'Enviarme una prueba')
			. ($vig->enviado_correo_at === null ? at_en_form('at_en_version_enviar', $id, $num, 'Enviar al cliente por correo', ' onsubmit="return confirm(\'¿Enviar la versión al cliente?\')"') : '<em>Correo enviado.</em> ')
			. at_en_form('at_en_version_whatsapp', $id, $num, 'Enviar por mi WhatsApp', ' target="_blank"') . '</p>';
		echo '<p>Texto del WhatsApp para copiar:<br><textarea readonly rows="3" style="width:100%" onclick="this.select()">' . esc_textarea(at_en_texto_wa_version($e, (int) $vig->numero)) . '</textarea></p>';
	}
	// Nueva versión.
	echo '<h4>Nueva versión (' . ((int) $e->version_vigente + 1) . ')</h4><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
		. '<input type="hidden" name="action" value="at_en_version_crear"><input type="hidden" name="entregable_id" value="' . $id . '">' . wp_nonce_field('at_en_' . $id, '_wpnonce', true, false)
		. '<p><label>Enlace de la versión<br><input type="url" name="url" required style="width:100%" placeholder="https://"></label></p>'
		. '<p><label>Tu mensaje (párrafos con línea en blanco; listas con «- »)<br><textarea name="mensaje" rows="6" maxlength="6000" style="width:100%"></textarea></label></p>'
		. '<button class="button button-primary">Crear versión y ver vista previa</button></form>';
	echo '<p>' . ($e->estado === 'abierto'
		? at_en_form('at_en_estado', $id, '<input type="hidden" name="estado" value="cerrado">', 'Cerrar el entregable')
		: at_en_form('at_en_estado', $id, '<input type="hidden" name="estado" value="abierto">', 'Reabrir el entregable')) . '</p>';
}

/** Resumen para la línea de tiempo del portal del cliente (sin texto de notas ni imágenes). */
function at_en_html_resumen_portal(array $item): string {
	if (($item['source'] ?? '') !== 'client' || ($item['detail_type'] ?? '') !== 'entregable') {
		return '';
	}
	$e = at_en_por_detalle((int) ($item['id'] ?? 0));
	if (!$e || (int) $e->version_vigente < 1) {
		return '';
	}
	$n = count(at_en_notas((int) $e->id));
	return '<div style="margin-top:10px;padding:10px;background:#eef2ff;border-radius:6px">'
		. 'Versión ' . (int) $e->version_vigente . ' · ' . $n . ($n === 1 ? ' nota' : ' notas')
		. ' · <a href="' . esc_url(at_en_url_pagina(home_url(), (string) $e->codigo)) . '" target="_blank" rel="noopener" style="font-weight:bold">Ver el detalle y las notas →</a></div>';
}

/** Marca para la lista de clientes del CRM; '' sin pendientes. */
function at_en_marca_lista(int $crm_id): string {
	$n = at_en_sin_responder_crm($crm_id);
	return $n > 0 ? ' <span title="Notas de entregables sin responder" style="color:#b91c1c;font-weight:bold">🔴 ' . $n . '</span>' : '';
}

function at_en_accion_activar(): void {
	$det = absint($_POST['detalle_id'] ?? 0);
	$crm = absint($_POST['crm_id'] ?? 0);
	check_admin_referer('at_en_activar_' . $det);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$r = at_en_activar($det);
	at_en_volver($crm, is_wp_error($r) ? 0 : (int) $r, is_wp_error($r) ? 'error' : 'activado');
}

function at_en_accion_version_crear(): void {
	$e = at_en_accion_entregable();
	$r = at_en_crear_version((int) $e->id, (string) wp_unslash($_POST['url'] ?? ''), (string) wp_unslash($_POST['mensaje'] ?? ''));
	if (is_wp_error($r)) {
		at_en_volver((int) $e->crm_id, (int) $e->id, $r->get_error_code() === 'url_invalida' ? 'url_invalida' : ($r->get_error_code() === 'mensaje_largo' ? 'mensaje_largo' : 'error'));
	}
	at_en_volver((int) $e->crm_id, (int) $e->id, 'version_creada');
}

function at_en_accion_version_prueba(): void {
	$e = at_en_accion_entregable();
	$r = at_en_enviar_version((int) $e->id, absint($_POST['numero'] ?? 0), true);
	at_en_volver((int) $e->crm_id, (int) $e->id, $r['ok'] ? 'prueba_enviada' : $r['motivo']);
}

function at_en_accion_version_enviar(): void {
	$e = at_en_accion_entregable();
	$r = at_en_enviar_version((int) $e->id, absint($_POST['numero'] ?? 0), false);
	at_en_volver((int) $e->crm_id, (int) $e->id, $r['ok'] ? 'version_enviada' : $r['motivo']);
}

function at_en_accion_version_whatsapp(): void {
	$e = at_en_accion_entregable();
	$n = absint($_POST['numero'] ?? 0);
	$url = at_en_version((int) $e->id, $n) ? at_en_url_wa_version($e, $n) : '';
	if ($url === '') {
		at_en_volver((int) $e->crm_id, (int) $e->id, 'sin_telefono');
	}
	at_en_marcar_version_enviada((int) $e->id, $n, 'whatsapp');
	at_en_historial((int) $e->crm_id, 'entregable_version', at_en_cliente($e)['titulo'] . ': versión ' . $n . ' enviada por WhatsApp', 'Se abrió el WhatsApp de Luis con el enlace del entregable ' . (int) $e->id . '.');
	wp_redirect($url); // wa.me no es del sitio: wp_safe_redirect lo cambiaría por el escritorio
	exit;
}

function at_en_accion_responder(): void {
	$e = at_en_accion_entregable();
	$nota = at_en_nota(absint($_POST['nota_id'] ?? 0));
	if (!$nota || (int) $nota->entregable_id !== (int) $e->id || $nota->autor !== 'cliente') {
		at_en_volver((int) $e->crm_id, (int) $e->id, 'nota_ajena');
	}
	$archivos = at_en_archivos_subidos(isset($_FILES['imagenes']) && is_array($_FILES['imagenes']) ? $_FILES['imagenes'] : []);
	$imagenes = $archivos ? at_en_procesar_imagenes($archivos, (int) $e->id) : [];
	if (is_wp_error($imagenes)) {
		at_en_volver((int) $e->crm_id, (int) $e->id, 'imagen');
	}
	$u = wp_get_current_user();
	$id = at_en_agregar_nota((int) $e->id, 'at', $u && $u->display_name ? (string) $u->display_name : 'Luis', (string) wp_unslash($_POST['texto'] ?? ''), $imagenes, '');
	if (is_wp_error($id)) {
		at_en_volver((int) $e->crm_id, (int) $e->id, $id->get_error_code());
	}
	at_en_marcar_respondida((int) $nota->id);
	at_en_historial((int) $e->crm_id, 'entregable_respuesta', at_en_cliente($e)['titulo'] . ': respuesta de AT', at_en_extracto((string) wp_unslash($_POST['texto'] ?? ''), 300));
	if (!empty($_POST['avisar'])) {
		at_en_avisar_respuesta((int) $id);
	}
	at_en_volver((int) $e->crm_id, (int) $e->id, 'respuesta_ok');
}

function at_en_accion_estado(): void {
	$e = at_en_accion_entregable();
	$estado = sanitize_key(wp_unslash($_POST['estado'] ?? ''));
	$ok = at_en_cambiar_estado((int) $e->id, $estado);
	at_en_volver((int) $e->crm_id, (int) $e->id, !$ok ? 'error' : ($estado === 'cerrado' ? 'cerrado_ok' : 'abierto_ok'));
}

add_action('admin_post_at_en_activar', 'at_en_accion_activar');
add_action('admin_post_at_en_version_crear', 'at_en_accion_version_crear');
add_action('admin_post_at_en_version_prueba', 'at_en_accion_version_prueba');
add_action('admin_post_at_en_version_enviar', 'at_en_accion_version_enviar');
add_action('admin_post_at_en_version_whatsapp', 'at_en_accion_version_whatsapp');
add_action('admin_post_at_en_responder', 'at_en_accion_responder');
add_action('admin_post_at_en_estado', 'at_en_accion_estado');
```

Agregar en `cargar.php`: `require_once __DIR__ . '/panel.php';`

- [ ] **Step 4: Correr y ver que pasa**

Run: `bash tests/entregables/correr.sh`
Expected: siete pruebas en `TODO OK`.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/entregables/panel.php wp-content/themes/automatiza-tech/inc/entregables/cargar.php tests/entregables/panel-wp-test.php
git commit -m "feat(entregables): pestaña Entregables en la ficha del CRM con versiones, envío y respuestas"
```

---

### Task 8: Integración con `crm-ai-completo.php`

**Files:**
- Modify: `wp-content/mu-plugins/crm-ai-completo.php` (tres puntos)
- Test: `tests/entregables/crm-wp-test.php`

**Interfaces:**
- Consumes: `at_en_render_pestana()`, `at_en_html_resumen_portal()`, `at_en_marca_lista()` (Task 7).

- [ ] **Step 1: Escribir la prueba que falla** — `tests/entregables/crm-wp-test.php`

```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/crm-wp-test.php
// Comprueba que el mu-plugin llama al módulo en los tres puntos (pestaña, portal, lista) y que la marca de la lista sale.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
$src = (string) file_get_contents(__DIR__ . '/../../wp-content/mu-plugins/crm-ai-completo.php');
ok(substr_count($src, "function_exists('at_en_render_pestana')") === 2 && strpos($src, 'data-target="tab-entregables"') !== false && strpos($src, 'id="tab-entregables"') !== false && strpos($src, 'at_en_render_pestana($cliente);') !== false, 'botón y contenido de la pestaña Entregables');
ok(strpos($src, "function_exists('at_en_html_resumen_portal') ? at_en_html_resumen_portal(\$h) : ''") !== false, 'resumen en el portal');
ok(strpos($src, "function_exists('at_en_marca_lista') ? at_en_marca_lista((int) (is_array(\$item) ? \$item['id'] : \$item->id)) : ''") !== false, 'marca en la lista');
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
$fx = en_fx_cliente($marca);
$id = at_en_activar($fx['detalle']);
at_en_crear_version($id, 'https://example.com/v1', '');
at_en_agregar_nota($id, 'cliente', 'C', 'pendiente', [], '');
$tabla = new AutomatizaTech_Clientes_List_Table();
ok(strpos($tabla->column_estado(['id' => $fx['crm'], 'estado' => 'prueba']), '🔴 1') !== false, 'la lista muestra 🔴 1 en el estado');
fin();
```

Si `AutomatizaTech_Clientes_List_Table` no se puede construir en CLI (depende de `WP_List_Table`), agregar antes `require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';`.

- [ ] **Step 2: Correr y ver que falla**

Run: `bash tests/entregables/correr.sh crm`
Expected: `FALLA botón y contenido de la pestaña Entregables` (y las otras dos).

- [ ] **Step 3: Cotejar el mu-plugin con PROD antes de editarlo**

Con `scratchpad/prod_ssh.py`: `md5sum` de `public_html/wp-content/mu-plugins/crm-ai-completo.php` en PROD contra `git show main:wp-content/mu-plugins/crm-ai-completo.php | md5sum`. Si difieren, **parar**: bajar la copia de PROD, comparar y decidir con Luis sobre qué base editar.

- [ ] **Step 4: Editar los tres puntos**

1) Botón de pestaña: después del bloque del plan (líneas 1985-1987):

```php
                        <?php if (is_array($cliente) && function_exists('at_en_render_pestana') && current_user_can('manage_options')): ?>
                        <button class="ficha-tab" data-target="tab-entregables">📦 Entregables</button>
                        <?php endif; ?>
```

2) Contenido de la pestaña: después de `</div><!-- /tab-plan -->` y su `<?php endif; ?>` (línea ~2251):

```php
                    <?php if (is_array($cliente) && function_exists('at_en_render_pestana') && current_user_can('manage_options')): ?>
                    <!-- Tab: Entregables (inc/entregables/panel.php) -->
                    <div class="ficha-tab-content" id="tab-entregables">
                    <div class="ficha-card">
                        <?php at_en_render_pestana($cliente); ?>
                    </div>
                    </div><!-- /tab-entregables -->
                    <?php endif; ?>
```

3) Portal: en `$render_public_item` (línea ~5017), al final de la construcción de `$attachment_html`, justo antes del `?>` que abre el HTML del ítem:

```php
                        $attachment_html .= function_exists('at_en_html_resumen_portal') ? at_en_html_resumen_portal($h) : '';
```

4) Lista: `column_estado` (línea 123):

```php
        public function column_estado($item) {
            $val = is_array($item) ? $item['estado'] : $item->estado;
            $marca = function_exists('at_en_marca_lista') ? at_en_marca_lista((int) (is_array($item) ? $item['id'] : $item->id)) : '';
            return sprintf('<span class="crm-badge">%s</span>', ucfirst($val)) . $marca;
        }
```

Verificar que el JS de pestañas de la ficha abre `#tab-entregables` por el hash igual que `#tab-plan` (`plan-trabajo.js`). Si el JS solo conoce `#tab-plan`, agregar en `panel.php` un `admin_footer` mínimo, solo en `page=automatiza-crm-ficha`, que haga clic en `[data-target="tab-entregables"]` cuando `location.hash === '#tab-entregables'`.

- [ ] **Step 5: Correr y ver que pasa**

Run: `bash tests/entregables/correr.sh` y `php -l wp-content/mu-plugins/crm-ai-completo.php`
Expected: ocho pruebas en `TODO OK`; `No syntax errors detected`.

- [ ] **Step 6: Revisión visual de la pestaña**

Con el servidor local (Task 6, Step 7) y sesión de administrador, abrir `wp-admin/admin.php?page=automatiza-crm-ficha&id=<crm de prueba>#tab-entregables`, activar, crear v1, ver la vista previa, enviar una prueba (capturada por `pre_wp_mail` local o MailHog), responder una nota con imagen. Captura para el PR.

- [ ] **Step 7: Commit**

```bash
git add wp-content/mu-plugins/crm-ai-completo.php tests/entregables/crm-wp-test.php
git commit -m "feat(entregables): pestaña, resumen del portal y marca en la lista del CRM"
```

---

### Task 9: Suite completa, guía y PR

**Files:**
- Create: `Docs/METODO_AT/ENTREGABLES-NOTAS.md`
- Modify: `CLAUDE.md` (un puntero de una línea en «Punteros de proyecto»)

- [ ] **Step 1: Correr todas las suites**

Run: `bash tests/entregables/correr.sh` y las de `tests/plan`, `tests/cierre`, `tests/followup` (como en Task 0, Step 4).
Expected: todas `TODO OK` (las 27 de antes + 8 nuevas).

- [ ] **Step 2: Escribir la guía `Docs/METODO_AT/ENTREGABLES-NOTAS.md`**

Contenido (en español, breve): para qué sirve; cómo se usa paso a paso (activar → versión → vista previa → prueba → enviar correo/WhatsApp → responder → cerrar); qué ve el cliente; límites (3.000 caracteres, 0 a 3 imágenes JPG/PNG de 5 MB, 10 notas por hora); archivos y tablas; **despliegue** (orden: migración → `inc/entregables/` → línea de `admin-proposals.php` → `ver-entregable.php` → `crm-ai-completo.php`; respaldo `~/respaldos/entregables-antes-<fecha>.tar.gz`); **reversa** (restaurar el respaldo; las tablas nuevas pueden quedar, no las usa nadie más); qué **no** se probó todavía.

- [ ] **Step 3: Puntero en `CLAUDE.md`**

Agregar en «Punteros de proyecto»: `- **Entregables con notas y versiones (rama claude/entregables-notas, sin desplegar):** guía `Docs/METODO_AT/ENTREGABLES-NOTAS.md`; spec y plan en `Docs/superpowers/`.`

- [ ] **Step 4: Commit y PR**

```bash
git add Docs/METODO_AT/ENTREGABLES-NOTAS.md CLAUDE.md
git commit -m "docs(entregables): guía de uso, despliegue y reversa"
git -c credential.helper='!gh auth git-credential' push -u origin claude/entregables-notas
```

Crear el PR hacia `main` por la API REST (como los PR #73 y #74), con resumen, pruebas (salida de las suites), capturas y la lista FTP (`OBLIGATORIO` / orden). Comentar en el PR que GitHub Actions no corre (facturación) y que las pruebas se corrieron en local. **No mergear** sin el OK de Luis.

---

### Task 10: Subida a PROD (solo con autorización explícita de Luis)

- [ ] **Step 1:** Pedir a Luis el OK para subir. Sin OK, parar aquí.
- [ ] **Step 2:** Reconocer en modo lectura: md5 de `crm-ai-completo.php` y `inc/admin-proposals.php` en PROD contra `main`; que no exista `ver-entregable.php` ni `inc/entregables/`.
- [ ] **Step 3:** Respaldo `tar` de `crm-ai-completo.php`, `inc/admin-proposals.php` → `~/respaldos/entregables-antes-<AAAAMMDD-HHMM>.tar.gz`.
- [ ] **Step 4:** Subir con un script basado en `scratchpad/agenda-limite/subir.py` (blobs de `git show <commit>:<ruta>`, LF): primero `inc/entregables/*` y `ver-entregable.php`; luego `inc/admin-proposals.php`; al final `crm-ai-completo.php`. `php -l` y md5 de cada uno.
- [ ] **Step 5:** Migración: `wp eval 'at_en_migrar_esquema(); echo get_option("at_en_db_version");'` → `1`; `SHOW TABLES LIKE '%at_entregable%'` → 3.
- [ ] **Step 6:** `wp litespeed-purge all`.
- [ ] **Step 7:** Verificar desde afuera por HTTP: `/ver-entregable.php?id=NoExiste1234` → 404 con «no disponible»; `/ver-entregable.php` → 404. La carpeta de imágenes se verifica **por SSH** (`.htaccess` presente), sin pedirla por el CDN.
- [ ] **Step 8:** Reportar a Luis con evidencia y actualizar la guía y la bóveda.

### Task 11: Prueba real con Luis (después de Task 10)

- [ ] Crear cliente y entregable de prueba con el correo y teléfono de Luis (estado CRM `prueba`).
- [ ] Luis: activar, v1 por correo y WhatsApp a sí mismo, nota con 1–2 fotos desde el celular, revisar aviso, ficha, historial y portal, responder con aviso, v2, segunda nota, cerrar.
- [ ] Revisar que las fotos se vean en la página y en la ficha (y que el CDN no las cambie: comparar por SSH el archivo guardado).
- [ ] Borrar todo lo de prueba **con respaldo** (filas de las tres tablas, historial, detalle, ficha, CRM e imágenes) y dejar evidencia en la bóveda.
