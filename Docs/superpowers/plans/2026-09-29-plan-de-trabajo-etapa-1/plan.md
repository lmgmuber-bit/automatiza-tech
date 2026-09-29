# Plan de trabajo con carta Gantt — Etapa 1: Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cuando un cliente firma el contrato de servicios, AutomatizaTech genera un borrador de plan de trabajo con
carta Gantt (Método AT, fases, tiempos por tabla, feriados), que Luis edita, recalcula, pide cambios y aprueba desde la
ficha del cliente en el CRM, con la misma plantilla de la propuesta. En la Etapa 1 nada le llega al cliente.

**Architecture:** Módulo WordPress independiente `inc/plan-trabajo/` (datos, cálculos puros de días hábiles y Gantt,
REST para n8n, pestaña en la ficha del CRM y ajustes con la tabla de tiempos) que se dispara con un hook nuevo al
firmar el contrato. Tres flujos n8n (borrador con GPT-4o, cambios con GPT-4o y render) y un tipo de documento nuevo
`document_type: "plan"` en el propuesta-renderer (Node + Playwright) con la carta Gantt en CSS.

**Tech Stack:** PHP 8 + WordPress (tema `automatiza-tech`, mu-plugin del CRM), n8n (builders Python que generan JSON de
workflows con nodos Code en JavaScript), Node 22 + Express + Playwright (renderer), pruebas PHP propias sin framework,
`node:test` y pruebas Python con `node -e`.

**Spec:** `Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md` (decisiones 1 a 12; ajustado el 29-sep).

**Contrato de interfaces:** `contrato-interfaces.md` (esta carpeta; las decisiones D1-D21 del final mandan). Hechos del código:
`hechos-del-codigo.md`. Notas de las revisiones: `notas-de-revision.md`.

## Global Constraints

- Idioma: todo texto visible, comentario y mensaje en español de Chile.
- El repositorio es PÚBLICO: ningún nombre, correo, teléfono ni dato real de clientes en código, pruebas, fixtures ni
  commits (usar «Cliente Prueba», «[PRUEBA] …», `prueba-plan-…@example.com`).
- `functions.php` no se toca. Solo se tocan fuera de `inc/plan-trabajo/`: `inc/admin-proposals.php` (1 línea),
  `contracts/contract-service.php` (hook), `wp-content/mu-plugins/crm-ai-completo.php` (pestaña) e
  `inc/admin-followup-meetings.php` (precarga por GET), más los dos assets nuevos `assets/css/plan-trabajo.css` y
  `assets/js/plan-trabajo.js`. Nada en `inc/propuestas-admin/` ni `inc/cierre-cliente/`.
- Ramas: WordPress y n8n en `claude/plan-de-trabajo` (sale de `claude/cierre-cliente` + merge del PR #51); renderer en
  `claude/plan-renderer` (sale de `origin/main`). Nunca tocar `renderer/` en la rama del plan.
- Estados del plan: `generando`, `borrador`, `cambios`, `aprobando`, `listo`, `enviado`, `error`, con las transiciones
  de `at_pt_transiciones()`.
- Días: hábiles de lunes a viernes, sin los feriados de `automatiza_chat_schedule['holidays']`; revisión del cliente
  = 5 días hábiles (cláusula 6.1); arranque fijo = reunión de inicio 1 día + entrega de insumos 3 días.
- Seguridad: admin-post con `manage_options` + nonce por plan; REST n8n → WP con `automatiza_proposals_rest_auth`
  (`X-AT-Secret`); WP → n8n con `X-AT-Secret: AT_REST_SECRET`; salida escapada; SQL con `$wpdb->prepare`.
- Etapa 1 = solo Luis ve: cero correos o WhatsApp al cliente; los correos de n8n van solo a Luis y enlazan solo a
  `automatizatech.cl` (nunca a `*.easypanel.host`).
- Fotos: portada y cierre reutilizan las de la propuesta si existe; las láminas nuevas piden fotos nuevas solo al
  «Aprobar», con cantidad y costo (US$0,0032 c/u de lista, máximo x2) a la vista.
- Todo lo que despliega a PROD, Easypanel o n8n lo autoriza Luis paso a paso (Task 16).
- Commits en español terminados en `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. Fecha de inicio o de firma en fin de semana o feriado: el plan parte el hábil siguiente y la secuencia salta
   feriados (Tasks 1 y 3).
2. La IA devuelve JSON inválido, fases desconocidas, días 0 o 200, textos enormes o ninguna actividad: el plan queda en
   `error` con un motivo legible y nunca se guarda un plan roto (Tasks 2, 7 y 13).
3. Luis editó días a mano (origen `luis`) y después pide cambios o se reaplica la tabla: sus días no se pisan
   (Tasks 2, 7, 9 y 13).
4. Contrato firmado sin propuesta y cliente sin correo: el plan igual se genera; portada y cierre piden fotos nuevas; la
   lámina «Sigue tu proyecto» sale sin enlace (Tasks 4, 5, 12 y 14).
5. Firma repetida, contrato que no es de servicios o n8n caído al firmar: un solo plan, la firma nunca falla, el plan
   queda en `error` con «Reintentar» (Tasks 5 y 6).
6. Carta Gantt larga (plataforma a medida + soporte, 10 a 14 semanas, 15 o más barras): cabe y se lee en 1920×1080 y
   en el PDF (Task 12).

## Orden de ejecución

Task 0 → Tasks 1-4 (puras) → Tasks 5-7 (datos, disparador, REST) → Tasks 8-10 (ajustes, panel, agenda) →
Tasks 11-12 (renderer, en su propia rama; pueden ir en paralelo con 5-10) → Tasks 13-14 (n8n) → Task 15 (punta a
punta local) → Task 16 (despliegue con Luis).

---


### Task 0: Ramas y sitio de prueba local

**Files:**
- Modify (merge): rama `claude/plan-de-trabajo` recibe `origin/claude/propuestas-json-robusto` (PR #51)
- Create: worktree `C:\wamp64\www\automatiza-tech\.worktrees\plan-renderer` (rama `claude/plan-renderer` desde `origin/main`)
- Create (fuera del repo): `<SCR>/wp-local-plan/` (sitio WordPress de prueba) y entradas `wp-local-plan` y
  `renderer-plan-local` en `C:\wamp64\www\automatiza-tech\.claude\launch.json` (no se commitean)

`<SCR>` = `C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad`.

**Interfaces:**
- Consumes: nada.
- Produces: `N8N/propuestas-v3/json_guard.py` y `fotos_guard.py` en la rama del plan; el sitio local con
  `AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php` para todas las pruebas `tests/plan/*-wp-test.php`; el worktree del
  renderer con sus 95 pruebas en verde.

- [ ] **Step 1: Traer el PR #51 a la rama del plan**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git fetch origin
git status --short   # debe salir vacío
git merge --no-ff origin/claude/propuestas-json-robusto -m "merge: trae el PR #51 (JSON robusto y fotos sin texto de n8n) para reutilizar json_guard y fotos_guard

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
ls N8N/propuestas-v3/json_guard.py N8N/propuestas-v3/fotos_guard.py
```
Expected: merge sin conflictos (probado el 29-sep) y los dos archivos listados.

- [ ] **Step 2: Worktree del renderer desde main y línea base de sus pruebas**

```bash
cd /c/wamp64/www/automatiza-tech
git worktree add -b claude/plan-renderer .worktrees/plan-renderer origin/main
cd .worktrees/plan-renderer/renderer
npm install --no-package-lock
node --test test/*.test.js 2>&1 | grep -E "^ℹ (tests|pass|fail)"
git -C .. status --short   # vacío: node_modules está ignorado y no se creó package-lock
```
Expected: `ℹ tests 95`, `ℹ pass 95`, `ℹ fail 0`.

- [ ] **Step 3: Armar el sitio de prueba `wp-local-plan` (PowerShell)**

Copia el sitio del cierre y apunta sus uniones al worktree del plan. La base local `automatiza_tech_local` es
compartida; las pruebas crean y borran sus propios datos `[PRUEBA]`.

```powershell
$scr = "C:\Users\luis_\AppData\Local\Temp\claude\C--wamp64-www-automatiza-tech\be929449-8523-4c30-a49a-56a73bb785ab\scratchpad"
$src = "$scr\wp-local-cierre"; $dst = "$scr\wp-local-plan"; $w = "C:\wamp64\www\automatiza-tech\.worktrees\plan-trabajo"
New-Item -ItemType Directory -Force $dst, "$dst\wp-content", "$dst\wp-content\themes", "$dst\wp-content\uploads" | Out-Null
Get-ChildItem $w -Filter *.php -File | Copy-Item -Destination $dst -Force
Copy-Item "C:\wamp64\www\automatiza-tech\wp-config.php" "$dst\wp-config.php" -Force
# Núcleo de WordPress y secretos locales (wp-load.php, wp-settings.php, index.php…): están en .gitignore y el
# worktree no los trae. Se copian de la copia principal solo los que falten.
Get-ChildItem "C:\wamp64\www\automatiza-tech" -Filter *.php -File | Where-Object { -not (Test-Path (Join-Path $dst $_.Name)) } | Copy-Item -Destination $dst
Test-Path "$dst\wp-load.php"   # debe decir True
Copy-Item "$src\router.php" "$dst\router.php" -Force
$wa = Get-Item "$dst\wp-admin" -ErrorAction SilentlyContinue
if ($wa -and $wa.LinkType -eq "Junction") { $wa.Delete(); $wa = $null }   # una unión de una corrida vieja carga otro WordPress
if (-not $wa) { Copy-Item "C:\wamp64\www\automatiza-tech\wp-admin" "$dst\wp-admin" -Recurse }
foreach ($par in @(@("$dst\wp-includes","C:\wamp64\www\automatiza-tech\wp-includes"),
                   @("$dst\contracts","$w\contracts"), @("$dst\Docs","$w\Docs"),
                   @("$dst\wp-content\mu-plugins","$w\wp-content\mu-plugins"), @("$dst\wp-content\plugins","C:\wamp64\www\automatiza-tech\wp-content\plugins"),
                   @("$dst\wp-content\languages","C:\wamp64\www\automatiza-tech\wp-content\languages"),
                   @("$dst\wp-content\themes\automatiza-tech","$w\wp-content\themes\automatiza-tech"))) {
  if (-not (Test-Path $par[0])) { New-Item -ItemType Junction -Path $par[0] -Target $par[1] | Out-Null }
}
Get-ChildItem $dst | Where-Object LinkType | Select-Object Name, Target
```
Expected: siete uniones listadas (las de `contracts`, `Docs`, `mu-plugins` y el tema apuntando a `.worktrees\plan-trabajo`)
y `wp-admin` como **carpeta real**: si fuera unión, PHP la resuelve y `wp-admin/admin.php` carga el `wp-load.php` de
la carpeta de destino, o sea otro WordPress (lo midió la redacción de la Task 9: la ficha salía sin la pestaña).

- [ ] **Step 4: Ajustar el router del sitio nuevo (puerto 8093 y webhooks locales)**

El router copiado fija `http://localhost:8089`. Este sitio usa 8093 y manda los webhooks del plan al simulador local
de n8n (Task 15) en `localhost:5203`; el router ya deja pasar `localhost` y bloquea todo lo demás.

Crear `<SCR>/plan-trabajo/router-plan.py` con la herramienta Write (no con heredoc: se come las barras invertidas, D16):
```python
import sys
p = sys.argv[1]; t = open(p, encoding='utf-8').read()
assert t.count("'http://localhost:8089'") == 2, 'el router cambió: revisar a mano'
t = t.replace("'http://localhost:8089'", "'http://localhost:8093'")
ancla = "if (!defined('WP_HOME')) {"
assert t.count(ancla) == 1
extra = ("foreach (['AT_N8N_PLAN_BORRADOR' => 'plan-v1-borrador', 'AT_N8N_PLAN_CAMBIOS' => 'plan-v1-cambios', "
         "'AT_N8N_PLAN_RENDER' => 'plan-v1-render'] as $c => $p) {\n"
         "    if (!defined($c)) { define($c, 'http://localhost:5203/' . $p); }\n}\n")
t = t.replace(ancla, extra + ancla, 1)
# ?at_admin=1 no alcanza para /wp-admin/: auth_redirect() solo mira la cookie de sesión de WordPress. Con la cookie de
# prueba se reemplaza esa función «pluggable» (solo en este router, fuera del repo).
ancla2 = "if (!empty($_COOKIE['at_admin'])) {"
assert t.count(ancla2) == 1, 'el router cambió: revisar a mano'
t = t.replace(ancla2, "if (!empty($_COOKIE['at_admin']) && !function_exists('auth_redirect')) {\n    function auth_redirect() {}\n}\n" + ancla2, 1)
open(p, 'w', encoding='utf-8').write(t); print('router ok')
```
```bash
python "$SCR/plan-trabajo/router-plan.py" "$SCR/wp-local-plan/router.php"
```
Expected: `router ok`.

- [ ] **Step 4b: Los webhooks del plan también van al simulador por línea de comandos**

El router solo cubre el servidor web. Para que ninguna corrida por consola (pruebas del cierre que firman contratos,
scripts del e2e) le avise al n8n de PROD, el `wp-config.php` del sitio de prueba (copia fuera del repo) define las tres
constantes antes de cargar WordPress. Crear `<SCR>/plan-trabajo/n8n-local.py` con la herramienta Write (no con heredoc,
que se come las barras invertidas):
```python
"""Task 0, Step 4b: en el sitio de prueba wp-local-plan los flujos del plan van al simulador local (Task 15) también por
línea de comandos. Uso: python n8n-local.py <SCR>/wp-local-plan/wp-config.php"""
import sys

p = sys.argv[1]
t = open(p, encoding='utf-8', newline='').read()
eol = '\r\n' if '\r\n' in t else '\n'
assert 'AT_N8N_PLAN_BORRADOR' not in t, 'wp-config ya define los flujos del plan'
ancla = "require_once ABSPATH . 'wp-settings.php';"
assert t.count(ancla) == 1, 'wp-config cambió: revisar a mano'
extra = eol.join([
    "// Plan de trabajo (sitio de prueba): los flujos van al simulador local de la Task 15, nunca al n8n de PROD.",
    "foreach (['AT_N8N_PLAN_BORRADOR' => 'plan-v1-borrador', 'AT_N8N_PLAN_CAMBIOS' => 'plan-v1-cambios', 'AT_N8N_PLAN_RENDER' => 'plan-v1-render'] as $at_pt_c => $at_pt_p) {",
    "    if (!defined($at_pt_c)) {",
    "        define($at_pt_c, 'http://localhost:5203/' . $at_pt_p);",
    "    }",
    "}",
    "",
])
t = t.replace(ancla, extra + ancla, 1)
open(p, 'w', encoding='utf-8', newline='').write(t)
print('wp-config ok:', 'CRLF' if eol == '\r\n' else 'LF')
```
```bash
PHP=/c/wamp64/bin/php/php8.4.15/php.exe
python "$SCR/plan-trabajo/n8n-local.py" "$SCR/wp-local-plan/wp-config.php" && "$PHP" -l "$SCR/wp-local-plan/wp-config.php"
```
Expected: `wp-config ok: CRLF` y `No syntax errors detected`. Las pruebas del plan fijan además sus propias URLs muertas
en `tests/plan/wp-bootstrap.php` (Task 5) y ganan porque definen antes; esto es la red de seguridad.

- [ ] **Step 5: Comprobar que el sitio carga el código de la rama**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
PHP=/c/wamp64/bin/php/php8.4.15/php.exe
AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php" "$PHP" tests/cierre/pagina-wp-test.php 2>&1 | tail -1
```
Expected: `TODO OK` (la suite del cierre corre contra el sitio nuevo: el sitio está bien armado).

- [ ] **Step 6: Entradas de vista previa (no se commitean)**

Agregar a `C:\wamp64\www\automatiza-tech\.claude\launch.json` (con Python, cuidando el JSON):
```json
{"name": "wp-local-plan", "runtimeExecutable": "C:/wamp64/bin/php/php8.4.15/php.exe",
 "runtimeArgs": ["-S", "localhost:8093", "-t", "<SCR>/wp-local-plan", "<SCR>/wp-local-plan/router.php"], "port": 8093},
{"name": "renderer-plan-local", "runtimeExecutable": "cmd",
 "runtimeArgs": ["/c", "<SCR>/plan-trabajo/e2e/renderer-local.cmd"], "port": 5202}
```
`launch.json` no admite variables de entorno (ninguna entrada existente usa `env`): el `.cmd` (Task 15, Step 1) fija
`PORT=5202`, `PUBLIC_DIR`, `BASE_URL=http://localhost:5202` y `RENDER_KEY` vacío (en local `/render` queda abierto
solo en ese servidor) y lanza `node …/plan-renderer/renderer/src/index.js`.


### Task 1: Puras — días hábiles, feriados, inicio por defecto y fecha larga

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php` (carpeta nueva del módulo; sin WordPress, sin guardia `ABSPATH`, como `inc/cierre-cliente/puras.php`)
- Test: `tests/plan/fechas-test.php`

**Interfaces:**
- Consumes: nada.
- Produces (sin WordPress; fechas `'Y-m-d'` calculadas con `DateTimeImmutable` en UTC):
  - `at_pt_ymd(string $f): string` — `'Y-m-d'` válida al inicio de la cadena (acepta `'Y-m-d H:i:s'` de `signed_at`); `''` si no es una fecha real.
  - `at_pt_dia(string $ymd): DateTimeImmutable` — medianoche UTC de una fecha ya validada.
  - `at_pt_feriados_de_texto(string $raw): array` — lista `Y-m-d` ordenada y sin repetir desde `get_option('automatiza_chat_schedule')['holidays']` (una fecha por línea; mismo corte que `wp-content/mu-plugins/api-appointments-config.php:30-42`). La usa `at_pt_feriados()` (Task 5).
  - `at_pt_es_habil(string $f, array $feriados): bool`
  - `at_pt_siguiente_habil(string $f, array $feriados): string` — primer hábil estrictamente después; `''` si `$f` no es fecha.
  - `at_pt_sumar_habiles(string $desde, int $n, array $feriados): string` — hábil n-ésimo contando `$desde` como día 1 (si no es hábil, parte el hábil siguiente); `$n < 1` vale 1.
  - `at_pt_inicio_por_defecto(string $fecha_firma, array $feriados): string` — primer lunes estrictamente posterior a la firma o, si es feriado, el hábil siguiente.
  - `at_pt_fecha_larga(string $ymd): string` — «28 de septiembre de 2026».

**Review Focus cubierto aquí:** 1 (firma o inicio en fin de semana o feriado → el hábil siguiente; feriado del lunes 12-oct-2026 dentro de un conteo).

Raíz de trabajo: el worktree `C:\wamp64\www\automatiza-tech\.worktrees\plan-trabajo` (rama `claude/plan-de-trabajo`). PHP local: `/c/wamp64/bin/php/php8.4.15/php.exe` (en bash no hay `php` en el PATH; PROD corre PHP 8.3, y estas pruebas pasan igual con `/c/wamp64/bin/php/php8.3.28/php.exe`). Las pruebas usan el patrón `ok()` / `TODO OK` de `tests/cierre/puras-test.php`, con `fin()` al final para poder agregar pruebas en las tareas siguientes.

- [ ] **Step 1: Escribir la prueba que falla** — crear `tests/plan/fechas-test.php` (Write, archivo nuevo, UTF-8 sin BOM) con fechas reales de 2026-2027 (firma martes 29-sep-2026 → inicio lunes 5-oct-2026; feriados de prueba 12-oct, 31-oct, 1-nov, 8-dic, 25-dic y 1-ene):

````php
<?php
// Correr: php tests/plan/fechas-test.php   (sin WordPress)
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

// Feriados de prueba: lunes 12-oct, sábado 31-oct y domingo 1-nov de 2026, martes 8-dic, viernes 25-dic y viernes 1-ene-2027.
$fer = ['2026-10-12', '2026-10-31', '2026-11-01', '2026-12-08', '2026-12-25', '2027-01-01'];

// Fecha 'Y-m-d' al inicio de la cadena
ok(at_pt_ymd('2026-09-29') === '2026-09-29' && at_pt_ymd(' 2026-09-29 14:35:00 ') === '2026-09-29', 'ymd: fecha sola y fecha con hora de MySQL');
ok(at_pt_ymd('2026-02-30') === '' && at_pt_ymd('basura') === '' && at_pt_ymd('29-09-2026') === '' && at_pt_ymd('2026-09-290') === '' && at_pt_ymd('') === '', 'ymd: fechas imposibles o mal escritas quedan vacías');

// Feriados desde el texto del ajuste de la agenda
ok(at_pt_feriados_de_texto("2026-12-08\r\n 2026-10-12 \n\nbasura\n2026-02-30\r2026-10-12\n12-10-2026") === ['2026-10-12', '2026-12-08'], 'feriados: una fecha por línea (\\r\\n, \\n o \\r), sin inválidas, sin repetir y en orden');
ok(at_pt_feriados_de_texto('') === [], 'feriados: texto vacío, lista vacía');

// Día hábil
ok(at_pt_es_habil('2026-09-29', $fer), 'martes 29-sep-2026 es hábil');
ok(!at_pt_es_habil('2026-10-03', $fer) && !at_pt_es_habil('2026-10-04', $fer), 'sábado 3 y domingo 4-oct-2026 no son hábiles');
ok(!at_pt_es_habil('2026-10-12', $fer) && at_pt_es_habil('2026-10-12', []), 'lunes 12-oct-2026: no es hábil si es feriado; sí lo es sin feriados');
ok(!at_pt_es_habil('basura', []) && !at_pt_es_habil('2026-02-30', []), 'una fecha inválida no es hábil');

// Siguiente hábil (estrictamente después)
ok(at_pt_siguiente_habil('2026-10-02', $fer) === '2026-10-05', 'viernes 2-oct -> lunes 5-oct');
ok(at_pt_siguiente_habil('2026-10-09', $fer) === '2026-10-13', 'viernes 9-oct -> martes 13-oct (salta el feriado del lunes 12)');
ok(at_pt_siguiente_habil('2026-12-24', $fer) === '2026-12-28', 'jueves 24-dic -> lunes 28-dic (salta el feriado del viernes 25)');
ok(at_pt_siguiente_habil('2026-12-31', $fer) === '2027-01-04', 'jueves 31-dic-2026 -> lunes 4-ene-2027 (cambio de año con feriado)');
ok(at_pt_siguiente_habil('2026-10-05', $fer) === '2026-10-06', 'desde un día hábil también avanza');
ok(at_pt_siguiente_habil('basura', $fer) === '', 'siguiente hábil de una fecha inválida: vacío');

// Sumar días hábiles ($desde cuenta como día 1)
ok(at_pt_sumar_habiles('2026-10-05', 1, $fer) === '2026-10-05', '1 día hábil desde el lunes 5-oct termina ese mismo día');
ok(at_pt_sumar_habiles('2026-10-05', 5, $fer) === '2026-10-09', '5 días hábiles desde el lunes 5-oct terminan el viernes 9-oct');
ok(at_pt_sumar_habiles('2026-10-05', 10, $fer) === '2026-10-19' && at_pt_sumar_habiles('2026-10-05', 10, []) === '2026-10-16', '10 días hábiles desde el 5-oct: lunes 19-oct con el feriado del 12; viernes 16-oct sin él');
ok(at_pt_sumar_habiles('2026-10-03', 1, $fer) === '2026-10-05' && at_pt_sumar_habiles('2026-10-03', 3, $fer) === '2026-10-07', 'desde un sábado cuenta desde el lunes siguiente');
ok(at_pt_sumar_habiles('2026-10-12', 1, $fer) === '2026-10-13', 'desde un feriado cuenta desde el hábil siguiente');
ok(at_pt_sumar_habiles('2026-10-05', 0, $fer) === '2026-10-05' && at_pt_sumar_habiles('2026-10-05', -3, $fer) === '2026-10-05', 'n menor que 1 vale 1');
ok(at_pt_sumar_habiles('2026-12-21', 5, $fer) === '2026-12-28', '5 días desde el lunes 21-dic: 21, 22, 23, 24 y 28 (salta el 25 y el fin de semana)');
ok(at_pt_sumar_habiles('basura', 3, $fer) === '', 'sumar desde una fecha inválida: vacío');

// Inicio por defecto: primer lunes estrictamente después de la firma (o el hábil siguiente si ese lunes es feriado)
ok(at_pt_inicio_por_defecto('2026-09-29', $fer) === '2026-10-05', 'firma martes 29-sep-2026 -> inicio lunes 5-oct-2026');
ok(at_pt_inicio_por_defecto('2026-09-29 18:40:12', $fer) === '2026-10-05', 'firma con hora (signed_at) -> el mismo lunes');
ok(at_pt_inicio_por_defecto('2026-10-05', []) === '2026-10-12', 'firma un lunes -> el lunes siguiente, no el mismo día');
ok(at_pt_inicio_por_defecto('2026-10-05', $fer) === '2026-10-13', 'firma lunes 5-oct con feriado el lunes 12 -> martes 13-oct');
ok(at_pt_inicio_por_defecto('2026-10-03', $fer) === '2026-10-05' && at_pt_inicio_por_defecto('2026-10-04', $fer) === '2026-10-05', 'firma sábado 3 o domingo 4-oct -> lunes 5-oct');
ok(at_pt_inicio_por_defecto('2026-10-11', $fer) === '2026-10-13', 'firma domingo 11-oct: el lunes 12 es feriado -> martes 13-oct');
ok(at_pt_inicio_por_defecto('2026-12-30', $fer) === '2027-01-04', 'firma miércoles 30-dic-2026 -> lunes 4-ene-2027');
ok(at_pt_inicio_por_defecto('', $fer) === '' && at_pt_inicio_por_defecto('2026-13-01', $fer) === '', 'firma inválida: vacío');

// Fecha larga
ok(at_pt_fecha_larga('2026-09-28') === '28 de septiembre de 2026', 'fecha larga: 28 de septiembre de 2026');
ok(at_pt_fecha_larga('2026-10-05 10:00:00') === '5 de octubre de 2026' && at_pt_fecha_larga('2027-01-04') === '4 de enero de 2027', 'fecha larga sin cero a la izquierda y con hora');
ok(at_pt_fecha_larga('basura') === '' && at_pt_fecha_larga('2026-02-30') === '', 'fecha larga inválida: vacío');

fin();
````

- [ ] **Step 2: Correr y ver que falla.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/fechas-test.php; echo "exit=$?"
```

  Expected: `Warning: require_once(…/tests/plan/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php): Failed to open stream: No such file or directory` y `Fatal error: Uncaught Error: Failed opening required '…/wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php'`; ninguna línea `ok`; `exit=255`. Motivo: `puras.php` todavía no existe.

- [ ] **Step 3: Implementar** — crear `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php` (Write, archivo nuevo) con:

````php
<?php
/**
 * Plan de trabajo: reglas sin WordPress. Fechas en días hábiles (Task 1); enumeraciones, tabla de tiempos,
 * validación, tabla -> días y marcas de origen (Task 2); cronograma (Task 3); estados, cuerpo del render y costo de
 * fotos (Task 4). Pruebas: php tests/plan/fechas-test.php, validacion-test.php, cronograma-test.php, render-test.php
 * Spec: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md
 * Las fechas son cadenas 'Y-m-d' y se calculan con DateTimeImmutable en UTC: nada depende de la zona del servidor.
 */

/* ---------- Fechas en días hábiles (Task 1) ---------- */

/** 'Y-m-d' válida al inicio de la cadena (acepta 'Y-m-d H:i:s' de MySQL, como signed_at); '' si no es una fecha real. */
function at_pt_ymd(string $f): string {
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:$|[ T])/', trim($f), $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
		return '';
	}
	return $m[1] . '-' . $m[2] . '-' . $m[3];
}

/** Medianoche UTC de una fecha 'Y-m-d' ya validada con at_pt_ymd(). */
function at_pt_dia(string $ymd): DateTimeImmutable {
	return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
}

/** Feriados desde el texto del ajuste de la agenda (get_option('automatiza_chat_schedule')['holidays'], una fecha
 *  'AAAA-MM-DD' por línea; mismo corte que wp-content/mu-plugins/api-appointments-config.php:30-42). Sin fechas
 *  imposibles, sin repetir y en orden. */
function at_pt_feriados_de_texto(string $raw): array {
	$fechas = [];
	foreach (preg_split('/\r\n|\r|\n/', $raw) as $linea) {
		$f = trim($linea);
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && at_pt_ymd($f) !== '') {
			$fechas[$f] = true;
		}
	}
	$fechas = array_keys($fechas);
	sort($fechas);
	return $fechas;
}

/** Día hábil: de lunes a viernes y no feriado. Una fecha inválida no es hábil. */
function at_pt_es_habil(string $f, array $feriados): bool {
	$ymd = at_pt_ymd($f);
	if ($ymd === '') {
		return false;
	}
	return (int) at_pt_dia($ymd)->format('N') <= 5 && !in_array($ymd, $feriados, true);
}

/** Primer día hábil estrictamente después de $f; '' si $f no es una fecha. Termina siempre: la lista de feriados es finita. */
function at_pt_siguiente_habil(string $f, array $feriados): string {
	$ymd = at_pt_ymd($f);
	if ($ymd === '') {
		return '';
	}
	$d = at_pt_dia($ymd);
	do {
		$d = $d->modify('+1 day');
	} while (!at_pt_es_habil($d->format('Y-m-d'), $feriados));
	return $d->format('Y-m-d');
}

/** Día hábil n-ésimo contando $desde como el día 1; si $desde no es hábil, cuenta desde el hábil siguiente.
 *  $n menor que 1 vale 1. '' si $desde no es una fecha. */
function at_pt_sumar_habiles(string $desde, int $n, array $feriados): string {
	$ymd = at_pt_ymd($desde);
	if ($ymd === '') {
		return '';
	}
	$dia = at_pt_es_habil($ymd, $feriados) ? $ymd : at_pt_siguiente_habil($ymd, $feriados);
	for ($i = 1; $i < max(1, $n); $i++) {
		$dia = at_pt_siguiente_habil($dia, $feriados);
	}
	return $dia;
}

/** Inicio por defecto del plan: el primer lunes estrictamente posterior a la firma; si ese lunes es feriado, el hábil
 *  siguiente. Acepta la firma con hora ('Y-m-d H:i:s'). '' si la firma no es una fecha. */
function at_pt_inicio_por_defecto(string $fecha_firma, array $feriados): string {
	$ymd = at_pt_ymd($fecha_firma);
	if ($ymd === '') {
		return '';
	}
	$lunes = at_pt_dia($ymd)->modify('next monday')->format('Y-m-d');
	return at_pt_es_habil($lunes, $feriados) ? $lunes : at_pt_siguiente_habil($lunes, $feriados);
}

/** '2026-09-28' => '28 de septiembre de 2026' (acepta la fecha con hora); '' si no es una fecha. */
function at_pt_fecha_larga(string $ymd): string {
	$f = at_pt_ymd($ymd);
	if ($f === '') {
		return '';
	}
	$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
	[$a, $m, $d] = explode('-', $f);
	return (int) $d . ' de ' . $meses[(int) $m - 1] . ' de ' . $a;
}
````

- [ ] **Step 4: Correr y ver que pasa.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/fechas-test.php; echo "exit=$?" && /c/wamp64/bin/php/php8.4.15/php.exe -l wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php
```

  Expected: 33 líneas `ok   …`, ninguna `FALLA`, la última línea `TODO OK`, `exit=0` y `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Si una aserción falla se corrige el código, no la prueba (la prueba solo se cambia si contradice el esqueleto o la spec, y se explica en el reporte).

- [ ] **Step 5: Commit.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && git add wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php tests/plan/fechas-test.php && git commit -m "feat(plan): días hábiles, feriados, inicio por defecto y fecha larga (puras)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Puras — enumeraciones, tabla de tiempos, validación del plan, tabla → días y marcas de origen

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php` (solo se agrega al final, en cinco ciclos; nunca se edita en medio de una función)
- Test: `tests/plan/validacion-test.php`

**Interfaces:**
- Consumes (Task 1): `at_pt_ymd(string $f): string`.
- Produces (sin WordPress):
  - `at_pt_fases_validas(): array` → `['diseno_desarrollo' => 'Diseño y desarrollo', 'implementacion' => 'Implementación', 'soporte' => 'Soporte y mejora continua']` (orden fijo).
  - `at_pt_responsables(): array` → `['at' => 'AutomatizaTech', 'cliente' => 'Tú', 'ambos' => 'Ambos']`.
  - `at_pt_etapas(): array` → `['arranque' => 'Arranque', 'diseno' => 'Diseño', 'desarrollo' => 'Desarrollo', 'pruebas' => 'Pruebas', 'implementacion' => 'Implementación', 'soporte' => 'Soporte', '' => 'Otra']`.
  - `at_pt_origenes(): array` → `['tabla' => 'Tabla de tiempos', 'ia' => 'IA · revisar', 'luis' => 'Editado por Luis']` (etiquetas para el panel de la Task 9).
  - `at_pt_slides_foto(): array` → `['cover', 'metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal', 'cierre']`.
  - `at_pt_etapas_tabla(): array` → `['diseno', 'desarrollo', 'pruebas', 'implementacion']` (columnas de la tabla; la usa la Task 8).
  - `at_pt_entero(mixed $v): ?int` — entero exacto desde número o texto de dígitos; `null` si no lo es.
  - `at_pt_duraciones_defecto(): array` (la tabla del esqueleto) y `at_pt_normalizar_duraciones(array $tabla): array` (acepta `clave => fila` o una lista de filas con `'clave'`, como la mandaría el formulario de la Task 8; gana la primera clave repetida).
  - `at_pt_arranque(): array` — el bloque «Arranque» fijo (actividades con las diez claves del esqueleto, en orden).
  - Constantes: `AT_PT_MAX_BLOQUES = 14` (con el Arranque), `AT_PT_MAX_ACTIVIDADES_BLOQUE = 10`, `AT_PT_MAX_ACTIVIDADES = 60` (con las 2 del Arranque), `AT_PT_MAX_DIAS_PLAN = 130` (días hábiles en secuencia, revisiones incluidas), `AT_PT_DIAS_REVISION = 5` (cláusula 6.1).
  - Ayudas: `at_pt_mostrar(mixed $v): string`, `at_pt_clave_nombre(string $s): string` (minúsculas y espacios simples, para comparar nombres), `at_pt_booleano(mixed $v): bool`, `at_pt_texto(mixed $v, int $max, string $que, array &$errores, array &$avisos, bool $multilinea = false): string`, `at_pt_necesitamos_defecto(): array`, `at_pt_reuniones_defecto(): array`, `at_pt_dias_bloque(array $bloque): int`, `at_pt_clave_de(mixed $v, array $opciones): string` (clave de una enumeración desde la clave o su etiqueta: «Tú» → `cliente`, «Implementación» → `implementacion`; `''` si no calza), `at_pt_validar_actividad(mixed $a, string $donde, int $n, array &$errores, array &$avisos): ?array`, `at_pt_validar_bloque(mixed $b, string $fase, int $n, array &$errores, array &$avisos): ?array`.
  - `at_pt_plan_de_json(string $texto): ?array` — el plan desde texto de la IA (JSON puro, con cerco ```` ```json ```` o con texto alrededor); `null` si no hay un objeto JSON (también si es una lista, aunque envuelva un objeto).
  - `at_pt_validar_plan(array $plan): array` → `['ok' => bool, 'errores' => string[], 'avisos' => string[], 'plan' => array]`. **Con errores, `'plan'` es `[]`**: nunca se guarda un plan roto. Normaliza como dice el esqueleto; además: fase y responsable se aceptan por clave o por etiqueta; textos hasta 4 veces su tope se acortan con aviso y más largos son error; bloques sin actividades se quitan con aviso; hitos sobre bloques que no existen se descartan con aviso (el aviso vive aquí porque `at_pt_calcular_fechas` no devuelve avisos); `soporte.mensuales` es una lista de textos y `garantia_meses` un entero de 0 a 24 (3 por defecto, como `garantia_meses_servicio` en `inc/cierre-cliente/puras.php:428`); como mucho 25 errores y 25 avisos (el resto se resume en «… y N errores más.» / «… y N avisos más.», para no llenar la columna `nota`, TEXT). Es idempotente: validar un plan ya validado o con la tabla aplicada devuelve el mismo plan (a uno con fechas se las quita; las vuelve a poner `at_pt_calcular_fechas`).
  - `at_pt_validar_entrada(mixed $plan, bool $borrador = false): array` — **nueva respecto del esqueleto**: lo que llega en `plan` a `POST /plan/{id}/borrador` (objeto o texto de la IA). Mismo resultado que `at_pt_validar_plan`; si no hay un objeto JSON (texto roto, `null`, un número) devuelve `['ok' => false, 'errores' => ['La IA no devolvió un plan en JSON válido (un objeto con fases).'], 'avisos' => [], 'plan' => []]` (Review Focus 2). Con `$borrador = true` quita los bloques «Arranque» que traiga la IA, en cualquier fase, para que entre el fijo con la entrega de insumos de la cláusula 4.2, y avisa «La IA mandó su propio bloque «Arranque»: se usó el fijo (…)».
  - `at_pt_repartir(int $total, array $pesos): array` — resto mayor con mínimo 1, en enteros y proporcional: la actividad que sube al mínimo de 1 no compite por los días que sobran, así que a la que la IA le estimó más nunca le tocan menos.
  - `at_pt_aplicar_tabla(array $plan, array $tabla): array` — como el esqueleto; las actividades de etapa `arranque` conservan su origen.
  - `at_pt_recorrer_actividades(array $plan): array` → lista de `[fase, bloque, actividad, clave]`; la clave es el nombre normalizado y su número de aparición (`'revisión interna#2'`), así que mover una actividad de bloque no la hace nueva. Límite conocido: si la IA inserta otra actividad con el mismo nombre antes de una de Luis, la marca de Luis pasa a la primera.
  - `at_pt_marcar_ediciones(array $anterior, array $nuevo, string $marca = 'luis'): array` — **tercer parámetro nuevo respecto del esqueleto** (opcional, compatible): una actividad nueva o con otros días queda con `$marca`; las demás, el origen de la versión guardada. `'luis'` al guardar desde el panel; `'ia'` en «Pedir cambios», porque lo cambió o agregó la IA (se muestra «IA · revisar» y la IA lo puede volver a cambiar). Otro valor vale `'luis'`.
  - `at_pt_respetar_dias_luis(array $anterior, array $nuevo): array` — **nueva respecto del esqueleto**: devuelve sus días y su marca a las actividades que eran `'luis'` en `$anterior` (aunque la IA las mueva de bloque) y baja a `'ia'` las marcas `'luis'` que invente la IA (así la tabla no se salta ese grupo en un borrador).
  - `at_pt_luis_perdidas(array $anterior, array $nuevo): array` — **nueva respecto del esqueleto**: textos «La IA renombró o quitó «X», que tenía N días hábiles puestos por ti: revísala en el panel.» para las actividades `'luis'` de `$anterior` que ya no están en `$nuevo`.
- **Orden de uso único** (Tasks 7 y 9; ya está en el esqueleto como decisión D2, junto con las firmas de arriba y la marca de D1). `$anterior` = el payload guardado (o `[]` si no hay).
  - **Borrador** (`origen: 'borrador'`): `$v = at_pt_validar_entrada($body['plan'] ?? null, true)` → si `!$v['ok']`, estado `error` con `$v['errores']` → `$plan = at_pt_respetar_dias_luis($anterior, $v['plan'])` → `$plan = at_pt_aplicar_tabla($plan, at_pt_duraciones())` → `$v2 = at_pt_validar_plan($plan)` → si `!$v2['ok']`, estado `error` con `$v2['errores']` → `at_pt_calcular_fechas($v2['plan'], $inicio, $feriados)`. Avisos del plan: `$v['avisos']`.
  - **Pedir cambios** (`origen: 'cambios'`): `$v = at_pt_validar_entrada($body['plan'] ?? null)` → error si `!$v['ok']` → `$plan = at_pt_respetar_dias_luis($anterior, $v['plan'])` → avisos = `array_merge($v['avisos'], at_pt_luis_perdidas($anterior, $plan))` → `$plan = at_pt_marcar_ediciones($anterior, $plan, 'ia')` → `$v2 = at_pt_validar_plan($plan)` → error si `!$v2['ok']` → `at_pt_calcular_fechas($v2['plan'], …)`. No se reaplica la tabla.
  - **Guardar desde el panel** (Task 9): `$v = at_pt_validar_plan($formulario)` → error visible si `!$v['ok']` → `$plan = at_pt_marcar_ediciones($anterior, $v['plan'])` (marca `'luis'`) → `$v2 = at_pt_validar_plan($plan)` → `at_pt_calcular_fechas($v2['plan'], …)`. Aquí Luis sí puede cambiar sus propios días.
  - La segunda validación existe porque la tabla o los días de Luis devueltos pueden pasar el tope de 130 días hábiles (prueba del Ciclo D: 57 días de la IA pasan a 138 con la tabla). Sus avisos se descartan: repiten los de la primera.
- **Para la Task 13 (prompts de n8n):** `PROMPT_PLAN` y `PROMPT_CAMBIOS` deben decir los topes que valida WordPress, o un proyecto grande pero legítimo queda en «error» y «Reintentar» genera lo mismo: como mucho 13 bloques (`AT_PT_MAX_BLOQUES` − 1: el Arranque lo pone WordPress y la IA no lo manda), 10 actividades por bloque, 58 actividades en total (60 con las 2 del Arranque), 130 días hábiles contando 5 de revisión por cada bloque con entrega (unas 26 semanas), de 1 a 60 días hábiles por actividad, hasta 10 hitos, y fases y responsables por su clave (`diseno_desarrollo`, `implementacion`, `soporte`; `at`, `cliente`, `ambos`); si no cabe, que junte bloques o actividades.

**Review Focus cubierto aquí:** 2 (JSON inválido o ausente, fases desconocidas, días 0 o 200, textos enormes, ninguna actividad → error legible y `plan` vacío; una etiqueta inequívoca en vez de la clave no es error), 3 (días de Luis protegidos al reaplicar la tabla y al pedir cambios, en dos rondas; lo que estima la IA sigue siendo «IA · revisar»; aviso si la IA renombra o quita una actividad de Luis), 4 (proyecto sin nombre → aviso, no error) y 6 (topes que mantienen la carta Gantt legible, validados otra vez después de la tabla).

**Ciclo A — enumeraciones, tabla de tiempos y bloque Arranque**

- [ ] **Step 1: Escribir la prueba que falla** — crear `tests/plan/validacion-test.php` (Write, archivo nuevo, UTF-8 sin BOM) con enumeraciones, tabla de tiempos y Arranque:

````php
<?php
// Correr: php tests/plan/validacion-test.php   (sin WordPress)
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

// Enumeraciones
ok(at_pt_fases_validas() === ['diseno_desarrollo' => 'Diseño y desarrollo', 'implementacion' => 'Implementación', 'soporte' => 'Soporte y mejora continua'], 'fases en su orden fijo y con su título fijo');
ok(at_pt_responsables() === ['at' => 'AutomatizaTech', 'cliente' => 'Tú', 'ambos' => 'Ambos'], 'responsables');
ok(array_keys(at_pt_etapas()) === ['arranque', 'diseno', 'desarrollo', 'pruebas', 'implementacion', 'soporte', ''], 'etapas, incluida la vacía');
ok(array_keys(at_pt_origenes()) === ['tabla', 'ia', 'luis'], 'orígenes de la duración');
ok(at_pt_slides_foto() === ['cover', 'metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal', 'cierre'], 'láminas que llevan foto');
ok(at_pt_etapas_tabla() === ['diseno', 'desarrollo', 'pruebas', 'implementacion'], 'columnas de la tabla de tiempos');

// Entero exacto
ok(at_pt_entero(5) === 5 && at_pt_entero('5') === 5 && at_pt_entero(' 12 ') === 12 && at_pt_entero(3.0) === 3 && at_pt_entero('-2') === -2, 'entero desde número o texto de dígitos');
ok(at_pt_entero(3.5) === null && at_pt_entero('3,5') === null && at_pt_entero('') === null && at_pt_entero(null) === null && at_pt_entero(true) === null && at_pt_entero([]) === null && at_pt_entero('diez') === null, 'lo que no es un entero exacto es null');

// Tabla de tiempos
$def = at_pt_duraciones_defecto();
ok(array_keys($def) === ['sitio_una_pagina', 'sitio_web_tienda', 'asistente_basico', 'asistente_avanzado', 'plataforma', 'automatizacion_n8n', 'google_ads'], 'tabla por defecto: siete servicios');
ok($def['plataforma'] === ['nombre' => 'Plataforma o sistema a medida', 'diseno' => 8, 'desarrollo' => 20, 'pruebas' => 5, 'implementacion' => 3], 'plataforma: 8 + 20 + 5 + 3 días hábiles');
ok(at_pt_normalizar_duraciones($def) === $def, 'la tabla por defecto ya viene normalizada');
$sucia = [
	'sitio_una_pagina' => ['nombre' => ' Sitio de una página ', 'diseno' => '3', 'desarrollo' => 5, 'pruebas' => 2, 'implementacion' => 1, 'extra' => 'x'],
	'Con Mayúsculas'   => ['nombre' => 'X', 'diseno' => 1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'a'                => ['nombre' => 'Clave de una letra', 'diseno' => 1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'sin_nombre'       => ['nombre' => '', 'diseno' => 1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'nombre_largo'     => ['nombre' => str_repeat('n', 61), 'diseno' => 1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'dias_61'          => ['nombre' => 'Muchos días', 'diseno' => 61, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'dias_negativos'   => ['nombre' => 'Negativo', 'diseno' => -1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'dias_decimales'   => ['nombre' => 'Decimal', 'diseno' => 2.5, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'falta_pruebas'    => ['nombre' => 'Incompleta', 'diseno' => 1, 'desarrollo' => 1, 'implementacion' => 1],
	'no_es_fila'       => 'texto',
	'google_ads'       => ['nombre' => 'Google Ads', 'diseno' => 0, 'desarrollo' => 3, 'pruebas' => 0, 'implementacion' => 60],
];
ok(at_pt_normalizar_duraciones($sucia) === [
	'sitio_una_pagina' => ['nombre' => 'Sitio de una página', 'diseno' => 3, 'desarrollo' => 5, 'pruebas' => 2, 'implementacion' => 1],
	'google_ads'       => ['nombre' => 'Google Ads', 'diseno' => 0, 'desarrollo' => 3, 'pruebas' => 0, 'implementacion' => 60],
], 'normalizar: descarta claves, nombres y días inválidos; acepta 0 y 60; quita los campos de más');
$lista = [
	['clave' => 'nuevo_servicio', 'nombre' => 'Nuevo servicio', 'diseno' => 1, 'desarrollo' => 2, 'pruebas' => 1, 'implementacion' => 1],
	['clave' => 'nuevo_servicio', 'nombre' => 'Repetido', 'diseno' => 9, 'desarrollo' => 9, 'pruebas' => 9, 'implementacion' => 9],
	['nombre' => 'Sin clave', 'diseno' => 1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
];
ok(at_pt_normalizar_duraciones($lista) === ['nuevo_servicio' => ['nombre' => 'Nuevo servicio', 'diseno' => 1, 'desarrollo' => 2, 'pruebas' => 1, 'implementacion' => 1]], 'normalizar una lista del formulario: gana la primera clave repetida y sin clave no entra');
ok(at_pt_normalizar_duraciones([]) === [], 'tabla vacía');

// Arranque fijo
$arr = at_pt_arranque();
ok($arr['nombre'] === 'Arranque' && $arr['entrega'] === false && $arr['entregable'] === '' && count($arr['actividades']) === 2, 'arranque: un bloque sin entrega con dos actividades');
ok($arr['actividades'][0]['nombre'] === 'Reunión de inicio' && $arr['actividades'][0]['responsable'] === 'ambos' && $arr['actividades'][0]['dias_habiles'] === 1, 'arranque: reunión de inicio, ambos, 1 día hábil');
ok($arr['actividades'][1]['nombre'] === 'Entrega de logo, textos y accesos' && $arr['actividades'][1]['responsable'] === 'cliente' && $arr['actividades'][1]['dias_habiles'] === 3, 'arranque: insumos del cliente, 3 días hábiles');
ok($arr['actividades'][0]['etapa'] === 'arranque' && $arr['actividades'][1]['origen'] === 'tabla' && $arr['actividades'][1]['en_paralelo'] === false, 'arranque: etapa arranque, origen tabla, sin paralelo');
ok(array_keys($arr['actividades'][0]) === ['nombre', 'detalle', 'responsable', 'dias_habiles', 'servicio', 'etapa', 'origen', 'en_paralelo', 'desde', 'hasta'], 'actividad con las diez claves del contrato, en orden');

fin();
````

- [ ] **Step 2: Correr y ver que falla.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/validacion-test.php; echo "exit=$?"
```

  Expected: `Fatal error: Uncaught Error: Call to undefined function at_pt_fases_validas()`; `exit=255`. Es el motivo correcto del fallo: la función todavía no existe.

- [ ] **Step 3: Implementar** — agregar las enumeraciones, la tabla de tiempos y el bloque Arranque al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Edit con `old_string` = el final actual del archivo (estas líneas, que aparecen una sola vez):

````text
	return (int) $d . ' de ' . $meses[(int) $m - 1] . ' de ' . $a;
}
````

  y `new_string` = esas mismas líneas, una línea en blanco y el bloque:

````php
/* ---------- Enumeraciones y tabla de tiempos (Task 2) ---------- */

/** Fases del plan en su orden fijo y con su título fijo (Método AT: lo que viene después de la firma). */
function at_pt_fases_validas(): array {
	return ['diseno_desarrollo' => 'Diseño y desarrollo', 'implementacion' => 'Implementación', 'soporte' => 'Soporte y mejora continua'];
}

/** Quién hace cada actividad. */
function at_pt_responsables(): array {
	return ['at' => 'AutomatizaTech', 'cliente' => 'Tú', 'ambos' => 'Ambos'];
}

/** Etapa de una actividad; '' es «otra» (no calza con la tabla de tiempos). */
function at_pt_etapas(): array {
	return ['arranque' => 'Arranque', 'diseno' => 'Diseño', 'desarrollo' => 'Desarrollo', 'pruebas' => 'Pruebas', 'implementacion' => 'Implementación', 'soporte' => 'Soporte', '' => 'Otra'];
}

/** De dónde sale la duración de una actividad: la tabla de tiempos, la IA (el panel la marca «revisar») o Luis a mano. */
function at_pt_origenes(): array {
	return ['tabla' => 'Tabla de tiempos', 'ia' => 'IA · revisar', 'luis' => 'Editado por Luis'];
}

/** Láminas del plan que pueden llevar foto (image_briefs[].slide), en el orden del documento. */
function at_pt_slides_foto(): array {
	return ['cover', 'metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal', 'cierre'];
}

/** Etapas que son columnas de la tabla de tiempos. */
function at_pt_etapas_tabla(): array {
	return ['diseno', 'desarrollo', 'pruebas', 'implementacion'];
}

/** Entero exacto desde un número JSON o un texto de dígitos (5, '5', ' 12 ', 3.0); null si no lo es (3.5, '3,5', true, []). */
function at_pt_entero(mixed $v): ?int {
	if (is_int($v)) {
		return $v;
	}
	if (is_float($v) && is_finite($v) && floor($v) === $v && abs($v) < 1e9) {
		return (int) $v;
	}
	if (is_string($v) && preg_match('/^\s*-?\d{1,9}\s*$/', $v)) {
		return (int) trim($v);
	}
	return null;
}

/** Tabla de tiempos de referencia propuesta (spec §3, días hábiles por etapa). Luis la corrige en «Ajustes del plan». */
function at_pt_duraciones_defecto(): array {
	return [
		'sitio_una_pagina'   => ['nombre' => 'Sitio de una página',           'diseno' => 3, 'desarrollo' => 5,  'pruebas' => 2, 'implementacion' => 1],
		'sitio_web_tienda'   => ['nombre' => 'Sitio web o tienda',            'diseno' => 5, 'desarrollo' => 10, 'pruebas' => 3, 'implementacion' => 2],
		'asistente_basico'   => ['nombre' => 'Asistente básico',              'diseno' => 2, 'desarrollo' => 4,  'pruebas' => 2, 'implementacion' => 1],
		'asistente_avanzado' => ['nombre' => 'Asistente avanzado',            'diseno' => 3, 'desarrollo' => 8,  'pruebas' => 3, 'implementacion' => 2],
		'plataforma'         => ['nombre' => 'Plataforma o sistema a medida', 'diseno' => 8, 'desarrollo' => 20, 'pruebas' => 5, 'implementacion' => 3],
		'automatizacion_n8n' => ['nombre' => 'Automatización (flujo n8n)',    'diseno' => 2, 'desarrollo' => 5,  'pruebas' => 2, 'implementacion' => 1],
		'google_ads'         => ['nombre' => 'Google Ads (puesta en marcha)', 'diseno' => 2, 'desarrollo' => 3,  'pruebas' => 1, 'implementacion' => 1],
	];
}

/** Solo las filas válidas de una tabla de tiempos: clave [a-z0-9_]{2,40}, nombre de 1 a 60 caracteres y los cuatro
 *  números enteros de 0 a 60. Acepta 'clave' => fila o una lista de filas con 'clave' (el formulario de ajustes). Si
 *  una clave se repite, gana la primera. Quita los campos de más. */
function at_pt_normalizar_duraciones(array $tabla): array {
	$out = [];
	foreach ($tabla as $k => $fila) {
		if (!is_array($fila)) {
			continue;
		}
		$clave = trim(is_int($k) ? (is_string($fila['clave'] ?? null) ? $fila['clave'] : '') : (string) $k);
		if (!preg_match('/^[a-z0-9_]{2,40}$/', $clave) || isset($out[$clave])) {
			continue;
		}
		$nombre = is_string($fila['nombre'] ?? null) ? trim($fila['nombre']) : '';
		$largo = mb_strlen($nombre, 'UTF-8');
		if ($largo < 1 || $largo > 60) {
			continue;
		}
		$limpia = ['nombre' => $nombre];
		foreach (at_pt_etapas_tabla() as $etapa) {
			$n = at_pt_entero($fila[$etapa] ?? null);
			if ($n === null || $n < 0 || $n > 60) {
				continue 2;
			}
			$limpia[$etapa] = $n;
		}
		$out[$clave] = $limpia;
	}
	return $out;
}

/** Bloque fijo «Arranque» (spec §3): reunión de inicio y entrega de insumos del cliente (cláusula 4.2 del contrato:
 *  el plazo corre desde que recibimos el anticipo y los insumos). Siempre es el primer bloque del plan. */
function at_pt_arranque(): array {
	$actividad = static function (string $nombre, string $detalle, string $responsable, int $dias): array {
		return ['nombre' => $nombre, 'detalle' => $detalle, 'responsable' => $responsable, 'dias_habiles' => $dias,
			'servicio' => '', 'etapa' => 'arranque', 'origen' => 'tabla', 'en_paralelo' => false, 'desde' => '', 'hasta' => ''];
	};
	return [
		'nombre'      => 'Arranque',
		'entregable'  => '',
		'entrega'     => false,
		'actividades' => [
			$actividad('Reunión de inicio', 'Nos conocemos, revisamos este plan y acordamos cómo nos comunicamos.', 'ambos', 1),
			$actividad('Entrega de logo, textos y accesos', 'El plazo corre desde que recibimos el anticipo y estos insumos.', 'cliente', 3),
		],
	];
}
````

- [ ] **Step 4: Correr y ver que pasa.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/validacion-test.php; echo "exit=$?" && /c/wamp64/bin/php/php8.4.15/php.exe -l wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php
```

  Expected: 19 líneas `ok   …`, ninguna `FALLA`, la última línea `TODO OK`, `exit=0` y `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Si una aserción falla se corrige el código, no la prueba (la prueba solo se cambia si contradice el esqueleto o la spec, y se explica en el reporte).

- [ ] **Step 5: Commit.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && git add wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php tests/plan/validacion-test.php && git commit -m "feat(plan): enumeraciones, tabla de tiempos y bloque Arranque (puras)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

**Ciclo B — topes, ayudas de texto, JSON de la IA y días por bloque**

- [ ] **Step 6: Escribir las pruebas que fallan** — en `tests/plan/validacion-test.php`, reemplazar la última línea `fin();` (Edit: `old_string` = `fin();`, que aparece una sola vez) por este bloque, que termina en `fin();` (JSON de la IA, ayudas de texto, listas de siempre, topes y días hábiles de un bloque):

````php
// Review Focus 2: del texto de la IA solo sale un plan si es un objeto JSON; lo demás es null y no llega a validarse.
ok(at_pt_plan_de_json('esto no es JSON') === null && at_pt_plan_de_json('{"fases": [') === null && at_pt_plan_de_json('[1, 2]') === null && at_pt_plan_de_json('[{"proyecto": "X"}]') === null && at_pt_plan_de_json('{}') === null && at_pt_plan_de_json('') === null, 'JSON inválido, una lista (aunque envuelva un objeto) u objeto vacío: null');
ok(at_pt_plan_de_json("```json\n{\"proyecto\": \"X\", \"fases\": []}\n```") === ['proyecto' => 'X', 'fases' => []], 'JSON con cerco ```json');
ok(at_pt_plan_de_json("Aquí va el plan:\n{\"proyecto\": \"X\"}\nSaludos") === ['proyecto' => 'X'] && at_pt_plan_de_json("\xEF\xBB\xBF{\"proyecto\": \"Y\"}") === ['proyecto' => 'Y'], 'JSON con texto alrededor o con BOM');

// Ayudas para comparar nombres y leer sí o no
ok(at_pt_clave_nombre("  Diseño \t Web ") === 'diseño web' && at_pt_clave_nombre('ARRANQUE') === 'arranque', 'nombres para comparar: minúsculas y espacios simples');
ok(at_pt_booleano(true) && at_pt_booleano(1) && at_pt_booleano('Sí') && at_pt_booleano(' on ') && at_pt_booleano('true'), 'sí: true, 1, «Sí», «on» y «true»');
ok(!at_pt_booleano(false) && !at_pt_booleano(0) && !at_pt_booleano(2) && !at_pt_booleano('no') && !at_pt_booleano(null) && !at_pt_booleano([1]), 'no: false, 0, 2, «no», null o una lista');

// Valores mostrados en un mensaje de error
ok(at_pt_mostrar(null) === 'vacío' && at_pt_mostrar('') === 'vacío' && at_pt_mostrar(false) === 'false' && at_pt_mostrar([1]) === 'una lista' && at_pt_mostrar(2.5) === '2.5' && at_pt_mostrar("dos\n  líneas") === 'dos líneas', 'valores de la IA mostrados cortos y en una línea');
ok(at_pt_mostrar(str_repeat('x', 50)) === str_repeat('x', 39) . '…' && at_pt_mostrar("\xFF") === 'texto ilegible', 'lo largo se corta a 40 caracteres y lo que no es UTF-8 no se muestra');

// Texto limpio con tope: se acorta con aviso hasta 4 veces el tope; más largo es error
$e = [];
$w = [];
ok(at_pt_texto("  Hola\t mundo \x07 ", 20, 'Campo', $e, $w) === 'Hola mundo' && $e === [] && $w === [], 'una línea: sin caracteres de control ni espacios de más');
ok(at_pt_texto("Uno\r\n\r\n\r\nDos ", 20, 'Campo', $e, $w, true) === "Uno\n\nDos" && at_pt_texto(42, 20, 'Campo', $e, $w) === '42' && at_pt_texto(null, 20, 'Campo', $e, $w) === '' && $e === [], 'multilínea: conserva el párrafo; un número pasa a texto; null queda vacío');
ok(at_pt_texto(str_repeat('a', 25), 20, 'Campo', $e, $w) === str_repeat('a', 19) . '…' && $w === ['Campo: se acortó a 20 caracteres.'], 'hasta 4 veces el tope: se acorta con «…» y aviso');
ok(at_pt_texto(str_repeat('a', 81), 20, 'Campo', $e, $w) === '' && $e === ['Campo: el texto es demasiado largo (81 caracteres; máximo 20).'], 'más de 4 veces el tope: error legible');
$e = [];
ok(at_pt_texto(['x'], 20, 'Campo', $e, $w) === '' && at_pt_texto("\xFF", 20, 'Campo', $e, $w) === '' && $e === ['Campo: no es texto.', 'Campo: el texto no es UTF-8 válido.'], 'una lista o bytes que no son UTF-8: error');

// Listas de siempre y topes de la carta Gantt
ok(count(at_pt_necesitamos_defecto()) === 3 && array_column(at_pt_reuniones_defecto(), 'nombre') === ['Reunión de inicio', 'Llamada de seguimiento del plan', 'Entrega y capacitación'], 'listas de siempre para lo que la IA deje vacío');
ok(AT_PT_MAX_BLOQUES === 14 && AT_PT_MAX_ACTIVIDADES_BLOQUE === 10 && AT_PT_MAX_ACTIVIDADES === 60 && AT_PT_MAX_DIAS_PLAN === 130 && AT_PT_DIAS_REVISION === 5, 'topes de la carta Gantt y 5 días hábiles de revisión (cláusula 6.1)');

// Días hábiles de un bloque en secuencia (sin su revisión)
$paralelas = at_pt_dias_bloque(['actividades' => [
	['dias_habiles' => 5], ['dias_habiles' => 2, 'en_paralelo' => true], ['dias_habiles' => 1], ['dias_habiles' => 3, 'en_paralelo' => true],
]]);
ok($paralelas === 8, 'días de un bloque con paralelas: 5 (con 2 en paralelo) + 3 (con 1 en paralelo) = 8');
ok(at_pt_dias_bloque(['actividades' => [['dias_habiles' => 1, 'en_paralelo' => true], ['dias_habiles' => 2]]]) === 3 && at_pt_dias_bloque(['actividades' => []]) === 0, 'la primera nunca es paralela; bloque vacío: 0');

fin();
````

- [ ] **Step 7: Correr y ver que falla.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/validacion-test.php; echo "exit=$?"
```

  Expected: primero 19 líneas `ok   …` de las pruebas que ya pasaban y después `Fatal error: Uncaught Error: Call to undefined function at_pt_plan_de_json()`; `exit=255`. Es el motivo correcto del fallo: la función todavía no existe.

- [ ] **Step 8: Implementar** — agregar los topes, las ayudas de texto, la lectura del JSON de la IA y los días por bloque al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Edit con `old_string` = el final actual del archivo (estas líneas, que aparecen una sola vez):

````text
			$actividad('Entrega de logo, textos y accesos', 'El plazo corre desde que recibimos el anticipo y estos insumos.', 'cliente', 3),
		],
	];
}
````

  y `new_string` = esas mismas líneas, una línea en blanco y el bloque:

````php
/* ---------- Topes, textos, JSON de la IA y días por bloque (Task 2) ---------- */

// Topes del plan (Review Focus 6). Con 14 bloques, el Arranque incluido, la carta Gantt tiene como máximo 28 barras
// (cada bloque más su «Tu revisión»); con 130 días hábiles, unas 26 semanas más los feriados (peor caso medido en
// tests/plan/cronograma-test.php: 27 barras y 130 días hábiles en 27 semanas). Es lo que el renderer debe dejar
// legible en 1920×1080 y en el PDF. Más que eso es un plan roto o un proyecto que hay que dividir. La tabla de tiempos
// puede subir los días: por eso el plan se valida otra vez después de aplicarla.
const AT_PT_MAX_BLOQUES = 14;
const AT_PT_MAX_ACTIVIDADES_BLOQUE = 10;
const AT_PT_MAX_ACTIVIDADES = 60;
const AT_PT_MAX_DIAS_PLAN = 130;
// Cláusula 6.1 del contrato de servicios (Docs/CONTRATO_SERVICIO_DESARROLLO.md:74): el cliente tiene 5 días hábiles
// para aprobar cada avance. Se suma una vez después de cada bloque con entrega.
const AT_PT_DIAS_REVISION = 5;

/** Un valor que mandó la IA, corto y legible para un mensaje de error. */
function at_pt_mostrar(mixed $v): string {
	if ($v === null || $v === '') {
		return 'vacío';
	}
	if (is_bool($v)) {
		return $v ? 'true' : 'false';
	}
	if (!is_scalar($v)) {
		return 'una lista';
	}
	$s = (string) $v;
	if (!mb_check_encoding($s, 'UTF-8')) {
		return 'texto ilegible';
	}
	$s = trim((string) preg_replace('/\s+/u', ' ', $s));
	return mb_strlen($s, 'UTF-8') > 40 ? mb_substr($s, 0, 39, 'UTF-8') . '…' : $s;
}

/** Nombre para comparar: sin espacios de más y en minúsculas («  Diseño » y «diseño» son el mismo bloque). */
function at_pt_clave_nombre(string $s): string {
	$t = preg_replace('/\s+/u', ' ', $s);
	return mb_strtolower(trim(is_string($t) ? $t : $s), 'UTF-8');
}

/** Sí o no de la IA o de un formulario: true, 1, '1', 'true', 'si', 'sí' u 'on' son sí; lo demás, no. */
function at_pt_booleano(mixed $v): bool {
	if (is_bool($v)) {
		return $v;
	}
	if (is_int($v)) {
		return $v === 1;
	}
	if (is_string($v)) {
		return in_array(mb_strtolower(trim($v), 'UTF-8'), ['1', 'true', 'si', 'sí', 'on'], true);
	}
	return false;
}

/** Texto limpio de un campo del plan: sin caracteres de control (el salto de línea se queda solo si $multilinea), sin
 *  espacios de más y con tope. Hasta 4 veces el tope se acorta con aviso; más que eso es un texto roto de la IA y es
 *  error, igual que un valor que no es texto ni número. $que dice dónde está el campo, para el mensaje. */
function at_pt_texto(mixed $v, int $max, string $que, array &$errores, array &$avisos, bool $multilinea = false): string {
	if ($v === null) {
		return '';
	}
	if (is_int($v) || is_float($v)) {
		$v = (string) $v;
	}
	if (!is_string($v)) {
		$errores[] = "{$que}: no es texto.";
		return '';
	}
	if (!mb_check_encoding($v, 'UTF-8')) {
		$errores[] = "{$que}: el texto no es UTF-8 válido.";
		return '';
	}
	$t = str_replace(["\r\n", "\r"], "\n", $v);
	if ($multilinea) {
		$t = (string) preg_replace('/[^\P{Cc}\n]/u', ' ', $t);
		$t = (string) preg_replace('/[ ]+/u', ' ', $t);
		$t = (string) preg_replace("/ ?\n ?/u", "\n", $t);
		$t = trim((string) preg_replace("/\n{3,}/u", "\n\n", $t));
	} else {
		$t = trim((string) preg_replace('/[\p{Cc}\s]+/u', ' ', $t));
	}
	$largo = mb_strlen($t, 'UTF-8');
	if ($largo > 4 * $max) {
		$errores[] = "{$que}: el texto es demasiado largo (" . number_format($largo, 0, ',', '.') . " caracteres; máximo {$max}).";
		return '';
	}
	if ($largo > $max) {
		$avisos[] = "{$que}: se acortó a {$max} caracteres.";
		$t = rtrim(mb_substr($t, 0, $max - 1, 'UTF-8')) . '…';
	}
	return $t;
}

/** El plan desde el texto de la IA: JSON puro, con cerco ```json o con texto alrededor (del primer «{» al último «}»).
 *  null si no hay un objeto JSON (texto suelto, JSON roto, una lista —aunque envuelva un objeto— o un objeto vacío). */
function at_pt_plan_de_json(string $texto): ?array {
	$t = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $texto));
	$t = trim((string) preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $t));
	$intentos = [$t];
	$a = strpos($t, '{');
	$b = strrpos($t, '}');
	if ($a !== false && $b !== false && $b > $a && !str_starts_with($t, '[')) {
		$intentos[] = substr($t, $a, $b - $a + 1);
	}
	foreach ($intentos as $intento) {
		$d = json_decode($intento, true);
		if (is_array($d) && $d !== [] && !array_is_list($d)) {
			return $d;
		}
	}
	return null;
}

/** «Qué necesitamos de ti» cuando la IA no trae nada (cláusula 4.2: logo, textos y accesos). */
function at_pt_necesitamos_defecto(): array {
	return ['Logo y colores de tu marca', 'Textos e información de tu negocio', 'Accesos que el proyecto necesite (dominio, hosting o cuentas)'];
}

/** Reuniones cuando la IA no trae ninguna (spec §2). */
function at_pt_reuniones_defecto(): array {
	return [
		['nombre' => 'Reunión de inicio', 'detalle' => ''],
		['nombre' => 'Llamada de seguimiento del plan', 'detalle' => ''],
		['nombre' => 'Entrega y capacitación', 'detalle' => ''],
	];
}

/** Días hábiles que ocupa un bloque en secuencia (sin su revisión): la misma regla de at_pt_calcular_fechas(),
 *  contada en días hábiles (los feriados alargan el calendario, no los días hábiles). */
function at_pt_dias_bloque(array $bloque): int {
	$fin = -1;
	$inicio_anterior = 0;
	$primera = true;
	foreach (($bloque['actividades'] ?? []) as $a) {
		$inicio = $primera ? 0 : (!empty($a['en_paralelo']) ? $inicio_anterior : $fin + 1);
		$fin = max($fin, $inicio + max(1, (int) ($a['dias_habiles'] ?? 1)) - 1);
		$inicio_anterior = $inicio;
		$primera = false;
	}
	return $fin + 1;
}
````

- [ ] **Step 9: Correr y ver que pasa.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/validacion-test.php; echo "exit=$?" && /c/wamp64/bin/php/php8.4.15/php.exe -l wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php
```

  Expected: 36 líneas `ok   …` en total (19 de antes + 17 nuevas), ninguna `FALLA`, la última línea `TODO OK`, `exit=0` y `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Además, sin regresiones: `cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/fechas-test.php | tail -1` imprime `TODO OK` por cada archivo. Si una aserción falla se corrige el código, no la prueba (la prueba solo se cambia si contradice el esqueleto o la spec, y se explica en el reporte).

- [ ] **Step 10: Commit.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && git add wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php tests/plan/validacion-test.php && git commit -m "feat(plan): topes, textos, JSON de la IA y días por bloque (puras)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

**Ciclo C — validación del plan (Review Focus 2 y 6)**

- [ ] **Step 11: Escribir las pruebas que fallan** — en `tests/plan/validacion-test.php`, reemplazar la última línea `fin();` (Edit: `old_string` = `fin();`, que aparece una sola vez) por este bloque, que termina en `fin();` (un plan desordenado de la IA que queda normalizado, etiquetas en vez de claves, JSON inválido o ausente, el «Arranque» de la IA, fases desconocidas, días 0 y 200, textos enormes, ninguna actividad, los topes de la carta Gantt y una respuesta desbordada):

````php
// Validación del plan: un plan como lo devuelve la IA, con desorden y detalles que hay que normalizar.
$ia = [
	'proyecto'    => '  [PRUEBA] Sitio web de Cliente Prueba ',
	'fecha_firma' => '2026-09-29 18:40:12',
	'fases'       => [
		['clave' => 'soporte', 'titulo' => 'Otro título', 'bloques' => [
			['nombre' => 'Garantía', 'actividades' => [
				['nombre' => 'Ajustes menores', 'responsable' => 'at', 'dias_habiles' => 10, 'etapa' => 'soporte'],
			]],
		]],
		['clave' => 'diseno_desarrollo', 'descripcion' => "Diseñamos y construimos tu sitio.\r\n\r\n\r\nTú apruebas cada avance.\t", 'bloques' => [
			['nombre' => 'Diseño', 'entregable' => 'Maqueta aprobada', 'entrega' => true, 'actividades' => [
				['nombre' => 'Propuesta de diseño', 'responsable' => 'AT', 'dias_habiles' => '3', 'servicio' => 'sitio_web_tienda', 'etapa' => 'diseno', 'en_paralelo' => true],
				['nombre' => 'Ajustes de diseño', 'responsable' => 'at', 'dias_habiles' => 2, 'servicio' => 'Sitio_Web_Tienda', 'etapa' => 'DISENO', 'origen' => 'inventado', 'en_paralelo' => 'sí'],
			]],
			['nombre' => 'Bloque vacío', 'actividades' => []],
		]],
		['clave' => 'implementacion', 'bloques' => [
			['nombre' => 'Puesta en marcha', 'actividades' => [
				['nombre' => 'Publicación del sitio', 'responsable' => 'at', 'dias_habiles' => 1, 'servicio' => 'sitio_web_tienda', 'etapa' => 'implementacion', 'origen' => 'tabla'],
			]],
		]],
	],
	'hitos' => [
		['nombre' => 'Diseño aprobado', 'despues_de' => ' diseño '],
		['nombre' => 'Hito fantasma', 'despues_de' => 'No existe'],
		['nombre' => 'Entrega estimada', 'despues_de' => 'Puesta en marcha'],
	],
	'necesitamos_de_ti' => [],
	'reuniones'         => ['Reunión de inicio', ['nombre' => 'Entrega y capacitación', 'detalle' => 'Una hora por videollamada']],
	'soporte'           => ['garantia_meses' => '3', 'mensuales' => ['Google Ads: gestión mensual']],
	'image_briefs'      => [
		['slide' => 'metodo', 'prompt' => "team of a small bakery  kneading dough together,\n warm light"],
		['slide' => 'metodo', 'prompt' => 'otra foto para la misma lámina'],
		['slide' => 'inventada', 'prompt' => 'x'],
		['slide' => 'gantt', 'prompt' => ''],
		['slide' => 'fase_1', 'prompt' => str_repeat('a', 1201)],
	],
	'cronograma' => ['basura' => true],
];
$v = at_pt_validar_plan($ia);
$p = $v['plan'];
ok($v['ok'] === true && $v['errores'] === [], 'plan de la IA con desorden: válido y sin errores');
ok(array_column($p['fases'], 'clave') === ['diseno_desarrollo', 'implementacion', 'soporte'], 'fases en su orden fijo aunque vengan desordenadas');
ok(array_column($p['fases'], 'titulo') === ['Diseño y desarrollo', 'Implementación', 'Soporte y mejora continua'], 'títulos fijos por clave (se ignora el de la IA)');
ok(array_column($p['fases'][0]['bloques'], 'nombre') === ['Arranque', 'Diseño'], 'el Arranque va al inicio de la primera fase y el bloque vacío se quita');
ok($p['fases'][0]['bloques'][0] === at_pt_arranque(), 'el bloque Arranque es el fijo');
ok($p['proyecto'] === '[PRUEBA] Sitio web de Cliente Prueba' && $p['version'] === 1, 'proyecto recortado y versión 1');
ok($p['fecha_firma'] === '2026-09-29' && $p['fecha_inicio'] === '', 'fecha de firma sin hora; fecha de inicio vacía hasta calcular');
ok($p['fases'][0]['descripcion'] === "Diseñamos y construimos tu sitio.\n\nTú apruebas cada avance.", 'descripción: conserva el párrafo, sin \\r, sin tabulador y sin líneas vacías de más');
$d1 = $p['fases'][0]['bloques'][1]['actividades'][0];
$d2 = $p['fases'][0]['bloques'][1]['actividades'][1];
ok($d1['responsable'] === 'at' && $d1['dias_habiles'] === 3 && $d1['origen'] === 'ia', 'responsable «AT» -> at; días «3» -> 3; sin origen -> ia');
ok($d1['en_paralelo'] === false && $d2['en_paralelo'] === true, 'la primera actividad de un bloque nunca es paralela; «sí» es paralela');
ok($d2['servicio'] === 'sitio_web_tienda' && $d2['etapa'] === 'diseno' && $d2['origen'] === 'ia', 'servicio y etapa en minúsculas; origen inventado -> ia');
ok($p['fases'][1]['bloques'][0]['actividades'][0]['origen'] === 'tabla', 'un origen válido se conserva');
ok($p['fases'][0]['bloques'][1]['entrega'] === true && $p['fases'][1]['bloques'][0]['entrega'] === false && $p['fases'][0]['bloques'][1]['entregable'] === 'Maqueta aprobada', 'entrega y entregable');
ok($d1['desde'] === '' && $d1['hasta'] === '' && $p['cronograma'] === [], 'desde, hasta y cronograma vacíos hasta calcular (se ignora el cronograma que mande la IA)');
ok($p['hitos'] === [['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño', 'fecha' => '']], 'hitos: el nombre exacto del bloque; fuera el que apunta a un bloque que no existe y la «Entrega estimada» (la pone el cronograma)');
ok(in_array('El hito «Hito fantasma» se descartó: no existe el bloque «No existe».', $v['avisos'], true), 'aviso legible del hito descartado');
ok($p['necesitamos_de_ti'] === at_pt_necesitamos_defecto() && in_array('«Qué necesitamos de ti» venía vacío: se usó la lista de siempre (logo, textos y accesos).', $v['avisos'], true), 'qué necesitamos de ti vacío: lista de siempre, con aviso');
ok($p['reuniones'] === [['nombre' => 'Reunión de inicio', 'detalle' => ''], ['nombre' => 'Entrega y capacitación', 'detalle' => 'Una hora por videollamada']], 'reuniones como texto o como objeto');
ok($p['soporte'] === ['garantia_meses' => 3, 'mensuales' => ['Google Ads: gestión mensual']], 'soporte: garantía entera y mensuales como texto');
ok($p['image_briefs'] === [['slide' => 'metodo', 'prompt' => 'team of a small bakery kneading dough together, warm light']], 'fotos: una por lámina válida, sin vacías ni de más de 1.200 caracteres');
ok(count(array_filter($v['avisos'], fn($a) => strpos($a, 'Se descartó') === 0)) === 4, 'cuatro avisos de fotos descartadas (repetida, lámina inventada, sin descripción y demasiado larga)');
ok(array_keys($p) === ['version', 'proyecto', 'fecha_firma', 'fecha_inicio', 'fases', 'hitos', 'necesitamos_de_ti', 'reuniones', 'soporte', 'image_briefs', 'cronograma'], 'claves del plan normalizado, en orden');
ok(at_pt_validar_plan($p)['plan'] === $p, 'validar dos veces da lo mismo (el panel vuelve a guardar sin duplicar el Arranque)');
$con_arranque = $ia;
$con_arranque['fases'][1]['bloques'] = array_merge([['nombre' => ' arranque ', 'actividades' => [['nombre' => 'Kickoff', 'responsable' => 'ambos', 'dias_habiles' => 2]]]], $ia['fases'][1]['bloques']);
ok(array_column(at_pt_validar_plan($con_arranque)['plan']['fases'][0]['bloques'], 'nombre') === ['arranque', 'Diseño'], 'si la primera fase ya trae un bloque «Arranque», no se agrega otro');
$por_clave = at_pt_validar_plan(['proyecto' => 'X', 'fases' => ['implementacion' => ['bloques' => [['nombre' => 'Puesta en marcha', 'actividades' => [['nombre' => 'Publicación', 'responsable' => 'at', 'dias_habiles' => 1]]]]]]]);
ok($por_clave['ok'] && $por_clave['plan']['fases'][0]['clave'] === 'implementacion' && $por_clave['plan']['fases'][0]['bloques'][0]['nombre'] === 'Arranque', 'fases como objeto con la clave por nombre; el Arranque va a la primera fase que exista');
$repetida = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [
	['clave' => 'soporte', 'bloques' => [['nombre' => 'Garantía', 'actividades' => [['nombre' => 'Ajustes', 'responsable' => 'at', 'dias_habiles' => 5]]]]],
	['clave' => 'soporte', 'bloques' => [['nombre' => 'Mejora continua', 'actividades' => [['nombre' => 'Reunión mensual', 'responsable' => 'ambos', 'dias_habiles' => 1]]]]],
]]);
ok($repetida['ok'] && array_column($repetida['plan']['fases'][0]['bloques'], 'nombre') === ['Arranque', 'Garantía', 'Mejora continua'] && in_array('La fase «Soporte y mejora continua» venía repetida: se juntaron sus bloques.', $repetida['avisos'], true), 'una fase repetida se junta con aviso');
$etiquetas = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'Implementación', 'bloques' => [['nombre' => 'Puesta en marcha', 'actividades' => [['nombre' => 'Publicación', 'responsable' => 'Tú', 'dias_habiles' => 1], ['nombre' => 'Capacitación', 'responsable' => 'AutomatizaTech', 'dias_habiles' => 1]]]]]]]);
ok($etiquetas['ok'] && $etiquetas['plan']['fases'][0]['clave'] === 'implementacion' && array_column($etiquetas['plan']['fases'][0]['bloques'][1]['actividades'], 'responsable') === ['cliente', 'at'], 'la IA escribe la etiqueta en vez de la clave («Implementación», «Tú», «AutomatizaTech»): se entiende sin error');
ok(at_pt_clave_de(' SOPORTE Y MEJORA CONTINUA ', at_pt_fases_validas()) === 'soporte' && at_pt_clave_de('ambos', at_pt_responsables()) === 'ambos' && at_pt_clave_de('el equipo', at_pt_responsables()) === '' && at_pt_clave_de(3, at_pt_responsables()) === '', 'clave desde la clave o desde la etiqueta; lo demás no calza');

// Review Focus 2: lo que la IA devuelve mal deja el plan en error con un motivo legible y nunca un plan a medias.
ok(at_pt_validar_entrada('esto no es JSON') === ['ok' => false, 'errores' => ['La IA no devolvió un plan en JSON válido (un objeto con fases).'], 'avisos' => [], 'plan' => []] && at_pt_validar_entrada(null)['plan'] === [] && at_pt_validar_entrada(42)['ok'] === false, 'JSON inválido, ausente o de otro tipo: error legible y sin plan');
ok(at_pt_validar_entrada("```json\n" . json_encode($ia) . "\n```")['plan'] === $v['plan'] && at_pt_validar_entrada($ia) === $v, 'el plan en texto (con cerco) o como objeto: lo mismo que validar el objeto');
$arranque_ia = at_pt_validar_entrada($con_arranque, true);
ok(array_column($arranque_ia['plan']['fases'][0]['bloques'], 'nombre') === ['Arranque', 'Diseño'] && $arranque_ia['plan']['fases'][0]['bloques'][0] === at_pt_arranque() && $arranque_ia['avisos'][0] === 'La IA mandó su propio bloque «Arranque»: se usó el fijo (reunión de inicio y entrega de logo, textos y accesos).', 'borrador nuevo: el «Arranque» de la IA se cambia por el fijo, que trae la entrega de insumos de la cláusula 4.2');
$arranque_tarde = $ia;
$arranque_tarde['fases'][2]['bloques'][] = ['nombre' => 'Arranque', 'actividades' => [['nombre' => 'Kickoff', 'responsable' => 'ambos', 'dias_habiles' => 1]]];
$bloques_arranque = 0;
foreach (at_pt_validar_entrada($arranque_tarde, true)['plan']['fases'] as $f) {
	foreach ($f['bloques'] as $b) {
		$bloques_arranque += at_pt_clave_nombre($b['nombre']) === 'arranque' ? 1 : 0;
	}
}
ok($bloques_arranque === 1, 'un «Arranque» de la IA en otra fase tampoco queda: un solo Arranque, al inicio');
$mal = at_pt_validar_plan([]);
ok($mal['ok'] === false && $mal['errores'] === ['El plan no trae fases.'] && $mal['plan'] === [], 'plan vacío: error «El plan no trae fases.» y sin plan');
$mal = at_pt_validar_plan([['clave' => 'diseno_desarrollo']]);
ok($mal['ok'] === false && strpos($mal['errores'][0], 'llegó una lista') !== false && $mal['plan'] === [], 'una lista en vez de un objeto: error legible');
$mal = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'marketing', 'bloques' => []], ['clave' => 'implementacion', 'bloques' => [['nombre' => 'Puesta en marcha', 'actividades' => [['nombre' => 'Publicación', 'responsable' => 'at', 'dias_habiles' => 1]]]]]]]);
ok($mal['ok'] === false && $mal['errores'] === ['Fase desconocida: «marketing» (las válidas son diseno_desarrollo, implementacion y soporte).'] && $mal['plan'] === [], 'fase desconocida: error aunque las demás estén bien');
$dias = function ($d) {
	return at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'Diseño', 'actividades' => [['nombre' => 'Maqueta', 'responsable' => 'at', 'dias_habiles' => $d]]]]]]]);
};
ok($dias(0)['errores'] === ['Diseño y desarrollo › Diseño › «Maqueta»: dice 0 días hábiles (deben ser de 1 a 60).'] && $dias(0)['plan'] === [], 'días 0: error legible y sin plan');
ok($dias(200)['errores'] === ['Diseño y desarrollo › Diseño › «Maqueta»: dice 200 días hábiles (deben ser de 1 a 60).'], 'días 200: error legible');
ok($dias(2.5)['errores'] === ['Diseño y desarrollo › Diseño › «Maqueta»: los días hábiles no son un número entero («2.5»; deben ser de 1 a 60).'] && $dias('tres')['ok'] === false && $dias(null)['ok'] === false, 'días con decimales, en palabras o ausentes: error');
ok($dias(1)['ok'] && $dias(60)['ok'] && $dias('60')['ok'], 'días 1 y 60 son válidos');
$resp = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'Diseño', 'actividades' => [['nombre' => 'Maqueta', 'responsable' => 'el equipo', 'dias_habiles' => 2]]]]]]]);
ok($resp['errores'] === ['Diseño y desarrollo › Diseño › «Maqueta»: responsable desconocido «el equipo» (debe ser at, cliente o ambos).'], 'responsable desconocido: error legible');
$texto = function ($nombre) {
	return at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'Diseño', 'actividades' => [['nombre' => $nombre, 'responsable' => 'at', 'dias_habiles' => 2]]]]]]]);
};
$enorme = $texto(str_repeat('texto roto ', 500));
ok($enorme['ok'] === false && $enorme['errores'] === ['Diseño y desarrollo › Diseño › actividad 1: el texto es demasiado largo (5.499 caracteres; máximo 120).'] && $enorme['plan'] === [], 'texto enorme (más de 4 veces el tope): error legible y sin plan');
$largo = $texto(str_repeat('a', 130));
$nombre_largo = $largo['plan']['fases'][0]['bloques'][1]['actividades'][0]['nombre'];
ok($largo['ok'] && mb_strlen($nombre_largo) === 120 && substr($nombre_largo, -3) === '…' && in_array('Diseño y desarrollo › Diseño › actividad 1: se acortó a 120 caracteres.', $largo['avisos'], true), 'texto algo largo: se acorta a 120 con «…» y aviso');
ok($texto(['una', 'lista'])['errores'] === ['Diseño y desarrollo › Diseño › actividad 1: no es texto.'] && $texto('')['errores'] === ['Diseño y desarrollo › Diseño: la actividad 1 no tiene nombre.'], 'nombre que no es texto o vacío: error');
ok($texto("con\x00control y\ttab")['plan']['fases'][0]['bloques'][1]['actividades'][0]['nombre'] === 'con control y tab', 'caracteres de control fuera');
$vacio = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'Diseño', 'actividades' => []]]]]]);
ok($vacio['ok'] === false && $vacio['errores'] === ['El plan no trae actividades (aparte del arranque).'] && $vacio['plan'] === [], 'ninguna actividad: error legible');
$solo_arranque = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [at_pt_arranque()]]]]);
ok($solo_arranque['ok'] === false && $solo_arranque['errores'] === ['El plan no trae actividades (aparte del arranque).'], 'solo el arranque tampoco es un plan');
$sin_proyecto = at_pt_validar_plan(['fases' => [['clave' => 'soporte', 'bloques' => [['nombre' => 'Garantía', 'actividades' => [['nombre' => 'Ajustes', 'responsable' => 'at', 'dias_habiles' => 1]]]]]]]);
ok($sin_proyecto['ok'] && $sin_proyecto['plan']['proyecto'] === '' && $sin_proyecto['avisos'][0] === 'El plan no trae el nombre del proyecto.', 'sin nombre de proyecto: aviso, no error (el render usa el nombre de la empresa)');

// Topes (Review Focus 6): más no cabe legible en la carta Gantt.
$bloque = fn(string $n, int $acts = 1, int $dias = 1, bool $entrega = false) => ['nombre' => $n, 'entrega' => $entrega, 'actividades' => array_map(fn($i) => ['nombre' => "{$n} {$i}", 'responsable' => 'at', 'dias_habiles' => $dias], range(1, $acts))];
$muchos = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => array_map(fn($i) => $bloque("B{$i}"), range(1, 14))]]]);
ok($muchos['ok'] === false && $muchos['errores'] === ['El plan trae 15 bloques contando el arranque (máximo 14): la carta Gantt no cabe legible. Junta bloques o divide el proyecto.'], '15 bloques con el arranque: error');
ok(at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => array_map(fn($i) => $bloque("B{$i}"), range(1, 13))]]])['ok'], '14 bloques con el arranque: válido');
$once = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [$bloque('Grande', 11)]]]]);
ok($once['ok'] === false && $once['errores'] === ['Diseño y desarrollo › Grande: trae 11 actividades (máximo 10 por bloque).'], '11 actividades en un bloque: error');
$sesenta = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => array_map(fn($i) => $bloque("B{$i}", 9), range(1, 7))]]]);
ok($sesenta['ok'] === false && $sesenta['errores'] === ['El plan trae 65 actividades (máximo 60).'], '65 actividades con el arranque: error');
$lento = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [$bloque('Largo', 3, 40, true)]]]]);
ok($lento['ok'] === true, '4 de arranque + 3 × 40 + 5 de revisión = 129 días hábiles: válido');
$muy_lento = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [$bloque('Largo', 3, 40, true), $bloque('Extra', 1, 2)]]]]);
ok($muy_lento['errores'] === ['El plan suma 131 días hábiles con las revisiones (máximo 130, unas 26 semanas): acórtalo o divide el proyecto.'], '131 días hábiles: error');

// Una respuesta desbordada de la IA no llena la nota del plan: hasta 25 mensajes de cada tipo.
$desborde = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'soporte', 'bloques' => [['nombre' => 'Garantía', 'actividades' => [['nombre' => 'Ajustes', 'responsable' => 'at', 'dias_habiles' => 1]]]]]], 'image_briefs' => array_fill(0, 1500, ['slide' => 'inventada', 'prompt' => 'x'])]);
ok($desborde['ok'] && count($desborde['avisos']) === 26 && end($desborde['avisos']) === '… y 1.477 avisos más.', '1.502 avisos: quedan los 25 primeros y «… y 1.477 avisos más.»');
$ceros = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => array_map(fn($i) => $bloque("C{$i}", 10, 0), range(1, 3))]]]);
ok(!$ceros['ok'] && count($ceros['errores']) === 26 && end($ceros['errores']) === '… y 5 errores más.' && $ceros['plan'] === [], '30 errores: quedan los 25 primeros y «… y 5 errores más.»');

fin();
````

- [ ] **Step 12: Correr y ver que falla.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/validacion-test.php; echo "exit=$?"
```

  Expected: primero 36 líneas `ok   …` de las pruebas que ya pasaban y después `Fatal error: Uncaught Error: Call to undefined function at_pt_validar_plan()`; `exit=255`. Es el motivo correcto del fallo: la función todavía no existe.

- [ ] **Step 13: Implementar** — agregar la validación del plan al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Edit con `old_string` = el final actual del archivo (estas líneas, que aparecen una sola vez):

````text
		$inicio_anterior = $inicio;
		$primera = false;
	}
	return $fin + 1;
}
````

  y `new_string` = esas mismas líneas, una línea en blanco y el bloque:

````php
/* ---------- Validación del plan (Task 2) ---------- */

/** Clave de una enumeración desde lo que mandó la IA: la clave misma («AT», « cliente ») o su etiqueta («Tú»,
 *  «Implementación», «Soporte y mejora continua»), sin distinguir mayúsculas ni espacios de más. '' si no calza.
 *  Así un valor inequívoco no deja el plan en «error». */
function at_pt_clave_de(mixed $v, array $opciones): string {
	if (!is_string($v)) {
		return '';
	}
	$buscado = at_pt_clave_nombre($v);
	foreach ($opciones as $clave => $etiqueta) {
		if ($buscado === at_pt_clave_nombre((string) $clave) || $buscado === at_pt_clave_nombre((string) $etiqueta)) {
			return (string) $clave;
		}
	}
	return '';
}

/** Una actividad normalizada (las diez claves, en orden), o null si no se puede usar (el motivo va a $errores). */
function at_pt_validar_actividad(mixed $a, string $donde, int $n, array &$errores, array &$avisos): ?array {
	if (!is_array($a)) {
		$errores[] = "{$donde}: la actividad {$n} no tiene la forma esperada.";
		return null;
	}
	$antes = count($errores);
	$nombre = at_pt_texto($a['nombre'] ?? null, 120, "{$donde} › actividad {$n}", $errores, $avisos);
	if ($nombre === '') {
		if (count($errores) === $antes) {
			$errores[] = "{$donde}: la actividad {$n} no tiene nombre.";
		}
		return null;
	}
	$aqui = "{$donde} › «{$nombre}»";
	$responsable = at_pt_clave_de($a['responsable'] ?? null, at_pt_responsables());
	if ($responsable === '') {
		$errores[] = "{$aqui}: responsable desconocido «" . at_pt_mostrar($a['responsable'] ?? null) . '» (debe ser at, cliente o ambos).';
		$responsable = 'ambos';
	}
	$dias = at_pt_entero($a['dias_habiles'] ?? null);
	if ($dias === null || $dias < 1 || $dias > 60) {
		$errores[] = $dias === null
			? "{$aqui}: los días hábiles no son un número entero («" . at_pt_mostrar($a['dias_habiles'] ?? null) . '»; deben ser de 1 a 60).'
			: "{$aqui}: dice {$dias} días hábiles (deben ser de 1 a 60).";
		$dias = 1;
	}
	$servicio = is_string($a['servicio'] ?? null) ? strtolower(trim($a['servicio'])) : '';
	$etapa = is_string($a['etapa'] ?? null) ? strtolower(trim($a['etapa'])) : '';
	$origen = is_string($a['origen'] ?? null) ? strtolower(trim($a['origen'])) : '';
	return [
		'nombre'       => $nombre,
		'detalle'      => at_pt_texto($a['detalle'] ?? null, 300, "{$aqui}: detalle", $errores, $avisos),
		'responsable'  => $responsable,
		'dias_habiles' => $dias,
		'servicio'     => preg_match('/^[a-z0-9_]{2,40}$/', $servicio) ? $servicio : '',
		'etapa'        => isset(at_pt_etapas()[$etapa]) ? $etapa : '',
		'origen'       => isset(at_pt_origenes()[$origen]) ? $origen : 'ia',
		'en_paralelo'  => at_pt_booleano($a['en_paralelo'] ?? false),
		'desde'        => '',
		'hasta'        => '',
	];
}

/** Un bloque normalizado, o null si no sirve: sin forma o sin nombre (error) o sin actividades (se quita con aviso). */
function at_pt_validar_bloque(mixed $b, string $fase, int $n, array &$errores, array &$avisos): ?array {
	if (!is_array($b)) {
		$errores[] = "{$fase}: el bloque {$n} no tiene la forma esperada.";
		return null;
	}
	$antes = count($errores);
	$nombre = at_pt_texto($b['nombre'] ?? null, 80, "{$fase} › bloque {$n}", $errores, $avisos);
	if ($nombre === '') {
		if (count($errores) === $antes) {
			$errores[] = "{$fase}: el bloque {$n} no tiene nombre.";
		}
		return null;
	}
	$donde = "{$fase} › {$nombre}";
	$bloque = [
		'nombre'      => $nombre,
		'entregable'  => at_pt_texto($b['entregable'] ?? null, 200, "{$donde}: entregable", $errores, $avisos),
		'entrega'     => at_pt_booleano($b['entrega'] ?? false),
		'actividades' => [],
	];
	$actividades = $b['actividades'] ?? [];
	if (!is_array($actividades)) {
		$errores[] = "{$donde}: las actividades no tienen la forma esperada.";
		return null;
	}
	if (count($actividades) > AT_PT_MAX_ACTIVIDADES_BLOQUE) {
		$errores[] = "{$donde}: trae " . count($actividades) . ' actividades (máximo ' . AT_PT_MAX_ACTIVIDADES_BLOQUE . ' por bloque).';
	}
	$k = 0;
	foreach ($actividades as $a) {
		$act = at_pt_validar_actividad($a, $donde, ++$k, $errores, $avisos);
		if ($act === null) {
			continue;
		}
		if ($bloque['actividades'] === []) {
			$act['en_paralelo'] = false; // la primera de un bloque nunca es paralela
		}
		$bloque['actividades'][] = $act;
	}
	if ($bloque['actividades'] === []) {
		if (count($errores) === $antes) {
			$avisos[] = "{$donde}: el bloque no trae actividades y se quitó.";
		}
		return null;
	}
	return $bloque;
}

/** Valida y normaliza el plan que manda la IA o el panel (forma en el esqueleto). Devuelve
 *  ['ok' => bool, 'errores' => string[], 'avisos' => string[], 'plan' => array]. Con errores, 'plan' es [] para que
 *  nunca se guarde un plan roto. Normaliza: títulos fijos por clave, fases en su orden (las repetidas se juntan),
 *  Arranque al inicio de la primera fase si falta, textos recortados, días enteros de 1 a 60, primera actividad de
 *  cada bloque no paralela, hitos solo sobre bloques que existen, un brief de foto por lámina válida; desde, hasta,
 *  fechas de hitos y cronograma quedan vacíos (los llena at_pt_calcular_fechas). Fase y responsable se aceptan por
 *  clave o por etiqueta. Hasta 25 errores y 25 avisos. Es idempotente: validar un plan ya validado da lo mismo, así
 *  que se vuelve a validar después de aplicar la tabla o devolver los días de Luis (pueden pasar el tope de días). */
function at_pt_validar_plan(array $plan): array {
	$errores = [];
	$avisos = [];
	if ($plan !== [] && array_is_list($plan)) {
		return ['ok' => false, 'errores' => ['El plan no tiene la forma esperada: llegó una lista y no un objeto con fases.'], 'avisos' => [], 'plan' => []];
	}
	$out = [
		'version'           => 1,
		'proyecto'          => at_pt_texto($plan['proyecto'] ?? null, 120, 'Nombre del proyecto', $errores, $avisos),
		'fecha_firma'       => is_string($plan['fecha_firma'] ?? null) ? at_pt_ymd($plan['fecha_firma']) : '',
		'fecha_inicio'      => is_string($plan['fecha_inicio'] ?? null) ? at_pt_ymd($plan['fecha_inicio']) : '',
		'fases'             => [],
		'hitos'             => [],
		'necesitamos_de_ti' => [],
		'reuniones'         => [],
		'soporte'           => ['garantia_meses' => 3, 'mensuales' => []],
		'image_briefs'      => [],
		'cronograma'        => [],
	];
	if ($out['proyecto'] === '') {
		$avisos[] = 'El plan no trae el nombre del proyecto.';
	}

	// Fases: conocidas, en su orden fijo y con su título fijo.
	$titulos = at_pt_fases_validas();
	$fases_in = $plan['fases'] ?? null;
	if (!is_array($fases_in) || $fases_in === []) {
		$errores[] = 'El plan no trae fases.';
		$fases_in = [];
	}
	$por_clave = [];
	$n = 0;
	foreach ($fases_in as $k => $f) {
		$n++;
		if (!is_array($f)) {
			$errores[] = "La fase {$n} no tiene la forma esperada.";
			continue;
		}
		$clave_in = $f['clave'] ?? (is_string($k) ? $k : null);
		$clave = at_pt_clave_de($clave_in, $titulos);
		if ($clave === '') {
			$errores[] = 'Fase desconocida: «' . at_pt_mostrar($clave_in) . '» (las válidas son diseno_desarrollo, implementacion y soporte).';
			continue;
		}
		$bloques = $f['bloques'] ?? [];
		if (!is_array($bloques)) {
			$errores[] = "{$titulos[$clave]}: los bloques no tienen la forma esperada.";
			$bloques = [];
		}
		if (isset($por_clave[$clave])) {
			$avisos[] = "La fase «{$titulos[$clave]}» venía repetida: se juntaron sus bloques.";
			$por_clave[$clave]['bloques'] = array_merge($por_clave[$clave]['bloques'], array_values($bloques));
			continue;
		}
		$por_clave[$clave] = ['descripcion' => $f['descripcion'] ?? null, 'bloques' => array_values($bloques)];
	}
	foreach ($titulos as $clave => $titulo) {
		if (!isset($por_clave[$clave])) {
			continue;
		}
		$fase = [
			'clave'       => $clave,
			'titulo'      => $titulo,
			'descripcion' => at_pt_texto($por_clave[$clave]['descripcion'], 600, "{$titulo}: descripción", $errores, $avisos, true),
			'bloques'     => [],
		];
		foreach ($por_clave[$clave]['bloques'] as $j => $b) {
			$bloque = at_pt_validar_bloque($b, $titulo, $j + 1, $errores, $avisos);
			if ($bloque !== null) {
				$fase['bloques'][] = $bloque;
			}
		}
		if ($fase['bloques'] === []) {
			$avisos[] = "La fase «{$titulo}» quedó sin bloques y se quitó.";
			continue;
		}
		$out['fases'][] = $fase;
	}

	// Arranque fijo al inicio de la primera fase, si no está.
	if ($out['fases'] !== []) {
		$tiene_arranque = false;
		foreach ($out['fases'][0]['bloques'] as $b) {
			$tiene_arranque = $tiene_arranque || at_pt_clave_nombre($b['nombre']) === 'arranque';
		}
		if (!$tiene_arranque) {
			array_unshift($out['fases'][0]['bloques'], at_pt_arranque());
		}
	}

	// Tamaño: actividades propias, bloques, actividades y días hábiles en secuencia.
	$n_bloques = 0;
	$n_actividades = 0;
	$n_propias = 0;
	$dias_plan = 0;
	foreach ($out['fases'] as $f) {
		foreach ($f['bloques'] as $b) {
			$n_bloques++;
			$n_actividades += count($b['actividades']);
			if (at_pt_clave_nombre($b['nombre']) !== 'arranque') {
				$n_propias += count($b['actividades']);
			}
			$dias_plan += at_pt_dias_bloque($b) + ($b['entrega'] ? AT_PT_DIAS_REVISION : 0);
		}
	}
	if ($n_propias === 0 && $errores === []) {
		$errores[] = 'El plan no trae actividades (aparte del arranque).';
	}
	if ($n_bloques > AT_PT_MAX_BLOQUES) {
		$errores[] = "El plan trae {$n_bloques} bloques contando el arranque (máximo " . AT_PT_MAX_BLOQUES . '): la carta Gantt no cabe legible. Junta bloques o divide el proyecto.';
	}
	if ($n_actividades > AT_PT_MAX_ACTIVIDADES) {
		$errores[] = "El plan trae {$n_actividades} actividades (máximo " . AT_PT_MAX_ACTIVIDADES . ').';
	}
	if ($dias_plan > AT_PT_MAX_DIAS_PLAN) {
		$errores[] = "El plan suma {$dias_plan} días hábiles con las revisiones (máximo " . AT_PT_MAX_DIAS_PLAN . ', unas 26 semanas): acórtalo o divide el proyecto.';
	}

	// Hitos: solo sobre un bloque que existe (el nombre exacto del bloque queda en despues_de).
	$bloques_por_nombre = [];
	foreach ($out['fases'] as $f) {
		foreach ($f['bloques'] as $b) {
			$bloques_por_nombre[at_pt_clave_nombre($b['nombre'])] ??= $b['nombre'];
		}
	}
	$hitos = is_array($plan['hitos'] ?? null) ? array_values($plan['hitos']) : [];
	foreach ($hitos as $i => $h) {
		$nombre = at_pt_texto(is_array($h) ? ($h['nombre'] ?? null) : null, 80, 'Hito ' . ($i + 1), $errores, $avisos);
		if ($nombre === '' || at_pt_clave_nombre($nombre) === 'entrega estimada') {
			continue; // sin nombre no se muestra; «Entrega estimada» la agrega siempre el cronograma
		}
		$despues = is_string($h['despues_de'] ?? null) ? at_pt_clave_nombre($h['despues_de']) : '';
		if (!isset($bloques_por_nombre[$despues])) {
			$avisos[] = "El hito «{$nombre}» se descartó: no existe el bloque «" . at_pt_mostrar($h['despues_de'] ?? null) . '».';
			continue;
		}
		if (count($out['hitos']) >= 10) {
			$avisos[] = "El hito «{$nombre}» se descartó: máximo 10 hitos.";
			continue;
		}
		$out['hitos'][] = ['nombre' => $nombre, 'despues_de' => $bloques_por_nombre[$despues], 'fecha' => ''];
	}

	// Qué necesitamos de ti (máximo 12).
	$necesitamos = is_array($plan['necesitamos_de_ti'] ?? null) ? array_values($plan['necesitamos_de_ti']) : [];
	foreach ($necesitamos as $i => $x) {
		$t = at_pt_texto(is_array($x) ? ($x['nombre'] ?? $x['texto'] ?? null) : $x, 160, 'Qué necesitamos de ti, punto ' . ($i + 1), $errores, $avisos);
		if ($t !== '' && count($out['necesitamos_de_ti']) < 12) {
			$out['necesitamos_de_ti'][] = $t;
		}
	}
	if ($out['necesitamos_de_ti'] === []) {
		$out['necesitamos_de_ti'] = at_pt_necesitamos_defecto();
		$avisos[] = '«Qué necesitamos de ti» venía vacío: se usó la lista de siempre (logo, textos y accesos).';
	}

	// Reuniones (máximo 8; cada una puede venir como texto o como {nombre, detalle}).
	$reuniones = is_array($plan['reuniones'] ?? null) ? array_values($plan['reuniones']) : [];
	foreach ($reuniones as $i => $r) {
		$q = 'Reunión ' . ($i + 1);
		$nombre = at_pt_texto(is_array($r) ? ($r['nombre'] ?? null) : $r, 80, $q, $errores, $avisos);
		$detalle = is_array($r) ? at_pt_texto($r['detalle'] ?? null, 200, "{$q}: detalle", $errores, $avisos) : '';
		if ($nombre !== '' && count($out['reuniones']) < 8) {
			$out['reuniones'][] = ['nombre' => $nombre, 'detalle' => $detalle];
		}
	}
	if ($out['reuniones'] === []) {
		$out['reuniones'] = at_pt_reuniones_defecto();
		$avisos[] = 'Las reuniones venían vacías: se usó la lista de siempre (inicio, seguimiento del plan, entrega y capacitación).';
	}

	// Soporte: garantía de 0 a 24 meses (3 por defecto, como garantia_meses_servicio en
	// inc/cierre-cliente/puras.php:428) y hasta 6 servicios mensuales como texto.
	$soporte = is_array($plan['soporte'] ?? null) ? $plan['soporte'] : [];
	$garantia = at_pt_entero($soporte['garantia_meses'] ?? 3);
	if ($garantia === null || $garantia < 0 || $garantia > 24) {
		$avisos[] = 'La garantía no era un número de 0 a 24 meses: quedó en 3.';
		$garantia = 3;
	}
	$out['soporte']['garantia_meses'] = $garantia;
	$mensuales = is_array($soporte['mensuales'] ?? null) ? array_values($soporte['mensuales']) : [];
	foreach ($mensuales as $i => $m) {
		$t = at_pt_texto(is_array($m) ? ($m['nombre'] ?? null) : $m, 160, 'Servicio mensual ' . ($i + 1), $errores, $avisos);
		if ($t !== '' && count($out['soporte']['mensuales']) < 6) {
			$out['soporte']['mensuales'][] = $t;
		}
	}

	// Fotos: una por lámina válida, con descripción de 1 a 1.200 caracteres (no se recorta: se perdería el cierre de
	// prohibiciones que agrega fotos_guard en n8n).
	$slides = at_pt_slides_foto();
	$vistos = [];
	$briefs = is_array($plan['image_briefs'] ?? null) ? array_values($plan['image_briefs']) : [];
	foreach ($briefs as $b) {
		$slide = is_array($b) && is_string($b['slide'] ?? null) ? trim($b['slide']) : '';
		$prompt = is_array($b) && is_string($b['prompt'] ?? null) && mb_check_encoding($b['prompt'], 'UTF-8')
			? trim((string) preg_replace('/\s+/u', ' ', $b['prompt'])) : '';
		if (!in_array($slide, $slides, true)) {
			$avisos[] = 'Se descartó la foto de la lámina «' . at_pt_mostrar(is_array($b) ? ($b['slide'] ?? null) : $b) . '»: esa lámina no existe en el plan.';
			continue;
		}
		if (isset($vistos[$slide])) {
			$avisos[] = "Se descartó una segunda foto para la lámina «{$slide}».";
			continue;
		}
		if ($prompt === '' || mb_strlen($prompt, 'UTF-8') > 1200) {
			$avisos[] = "Se descartó la foto de la lámina «{$slide}»: " . ($prompt === '' ? 'no trae descripción.' : 'la descripción pasa de 1.200 caracteres.');
			continue;
		}
		$vistos[$slide] = true;
		$out['image_briefs'][] = ['slide' => $slide, 'prompt' => $prompt];
	}

	// Una respuesta desbordada de la IA no llena la nota del plan (TEXT, 64 KB): hasta 25 mensajes de cada tipo.
	if (count($errores) > 25) {
		$resto = number_format(count($errores) - 25, 0, ',', '.');
		$errores = array_slice($errores, 0, 25);
		$errores[] = "… y {$resto} errores más.";
	}
	if (count($avisos) > 25) {
		$resto = number_format(count($avisos) - 25, 0, ',', '.');
		$avisos = array_slice($avisos, 0, 25);
		$avisos[] = "… y {$resto} avisos más.";
	}

	$ok = $errores === [];
	return ['ok' => $ok, 'errores' => $errores, 'avisos' => $avisos, 'plan' => $ok ? $out : []];
}

/** Lo que llega en «plan» a POST /plan/{id}/borrador: un objeto o el texto de la IA. Mismo resultado que
 *  at_pt_validar_plan(); si no hay un objeto JSON, error legible y plan vacío (Review Focus 2). Con $borrador true
 *  (un borrador nuevo de la IA) se quitan los bloques «Arranque» que traiga la IA, en cualquier fase, para que entre el
 *  fijo con la entrega de insumos de la cláusula 4.2; en «cambios» y en el panel se conserva el Arranque guardado, que
 *  Luis puede editar. */
function at_pt_validar_entrada(mixed $plan, bool $borrador = false): array {
	if (is_string($plan)) {
		$plan = at_pt_plan_de_json($plan);
	}
	if (!is_array($plan)) {
		return ['ok' => false, 'errores' => ['La IA no devolvió un plan en JSON válido (un objeto con fases).'], 'avisos' => [], 'plan' => []];
	}
	$quitados = 0;
	if ($borrador && is_array($plan['fases'] ?? null)) {
		foreach ($plan['fases'] as $k => $f) {
			if (!is_array($f) || !is_array($f['bloques'] ?? null)) {
				continue;
			}
			$quedan = array_values(array_filter($f['bloques'], static fn($b): bool => !(is_array($b) && is_string($b['nombre'] ?? null) && at_pt_clave_nombre($b['nombre']) === 'arranque')));
			$quitados += count($f['bloques']) - count($quedan);
			$plan['fases'][$k]['bloques'] = $quedan;
		}
	}
	$v = at_pt_validar_plan($plan);
	if ($quitados > 0) {
		array_unshift($v['avisos'], 'La IA mandó su propio bloque «Arranque»: se usó el fijo (reunión de inicio y entrega de logo, textos y accesos).');
	}
	return $v;
}
````

- [ ] **Step 14: Correr y ver que pasa.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/validacion-test.php; echo "exit=$?" && /c/wamp64/bin/php/php8.4.15/php.exe -l wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php
```

  Expected: 91 líneas `ok   …` en total (36 de antes + 55 nuevas), ninguna `FALLA`, la última línea `TODO OK`, `exit=0` y `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Si una aserción falla se corrige el código, no la prueba (la prueba solo se cambia si contradice el esqueleto o la spec, y se explica en el reporte).

- [ ] **Step 15: Commit.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && git add wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php tests/plan/validacion-test.php && git commit -m "feat(plan): validación del plan con errores legibles y topes de la carta Gantt" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

**Ciclo D — tabla de tiempos → días (reparto proporcional con resto)**

- [ ] **Step 16: Escribir las pruebas que fallan** — en `tests/plan/validacion-test.php`, reemplazar la última línea `fin();` (Edit: `old_string` = `fin();`, que aparece una sola vez) por este bloque, que termina en `fin();` (reparto 5 entre 2-2-2 → 2-2-1, 5 entre 3-2-1 → 2-2-1, proporcionalidad, 10 entre 3-1 → 8-2, 10 entre 4-4-1 → 5-4-1, mínimo 1, grupos que cruzan bloques, grupo con días de Luis intacto y la tabla que pasa el tope de días):

````php
// Reparto proporcional de la tabla de tiempos, con resto
ok(at_pt_repartir(5, [2, 2, 2]) === [2, 2, 1], '5 días entre tres actividades de 2: cuotas 1,67; los 2 días que sobran van a las primeras -> 2, 2 y 1');
ok(at_pt_repartir(10, [3, 1]) === [8, 2], '10 días con pesos 3 y 1: cuotas 7,5 y 2,5; el empate de fracciones lo gana la primera -> 8 y 2');
ok(at_pt_repartir(10, [4, 4, 1]) === [5, 4, 1], '10 días con pesos 4, 4 y 1: cuotas 4,44, 4,44 y 1,11 -> 5, 4 y 1');
ok(at_pt_repartir(20, [5, 10, 5]) === [5, 10, 5], 'si los pesos ya suman el total, quedan iguales');
ok(at_pt_repartir(8, [1, 1, 1]) === [3, 3, 2], '8 días entre tres iguales: 3, 3 y 2');
ok(at_pt_repartir(4, [1, 1, 100]) === [1, 1, 2], 'mínimo 1: a las chicas les toca 1 y a la grande el resto');
ok(at_pt_repartir(2, [5, 5, 5]) === [1, 1, 1] && at_pt_repartir(0, [3]) === [1], 'total menor que la cantidad (o cero): 1 a cada una');
ok(at_pt_repartir(7, []) === [], 'sin actividades, nada');
ok(at_pt_repartir(5, [3, 2, 1]) === [2, 2, 1], 'diseño = 5 con la IA en 3, 2 y 1: la que subió al mínimo de 1 no se lleva el día que sobra -> 2, 2 y 1');
$suma_ok = true;
foreach ([[13, [1, 2, 3, 4]], [60, [60, 1, 1]], [9, [7, 7, 7, 7, 7, 7, 7, 7]], [31, [2, 9, 4]], [59, [1, 1, 1, 60]]] as [$t, $w]) {
	$r = at_pt_repartir($t, $w);
	$suma_ok = $suma_ok && array_sum($r) === $t && min($r) >= 1;
}
ok($suma_ok, 'el reparto siempre suma el total de la tabla y ninguna actividad queda en 0');
$monotono = true;
foreach ([[5, [3, 2, 1]], [5, [28, 17, 11]], [71, [2, 16, 1, 42, 39]], [18, [58, 56, 52, 13, 27, 33, 34, 23, 59, 36]]] as [$t, $w]) {
	$r = at_pt_repartir($t, $w);
	foreach ($w as $i => $wi) {
		foreach ($w as $j => $wj) {
			$monotono = $monotono && !($wi > $wj && $r[$i] < $r[$j]);
		}
	}
}
ok($monotono, 'proporcional: a la que la IA le estimó más días nunca le tocan menos que a otra');

// Tabla -> días en un plan: por par (servicio, etapa) en todo el plan
$tab = at_pt_duraciones_defecto();
$base = at_pt_validar_plan([
	'proyecto' => '[PRUEBA] Tienda de Cliente Prueba',
	'fases' => [
		['clave' => 'diseno_desarrollo', 'bloques' => [
			['nombre' => 'Diseño', 'entrega' => true, 'actividades' => [
				['nombre' => 'Propuesta de diseño', 'responsable' => 'at', 'dias_habiles' => 2, 'servicio' => 'sitio_web_tienda', 'etapa' => 'diseno'],
				['nombre' => 'Ajustes de diseño', 'responsable' => 'at', 'dias_habiles' => 2, 'servicio' => 'sitio_web_tienda', 'etapa' => 'diseno'],
			]],
			['nombre' => 'Desarrollo', 'actividades' => [
				['nombre' => 'Maquetación', 'responsable' => 'at', 'dias_habiles' => 4, 'servicio' => 'sitio_web_tienda', 'etapa' => 'desarrollo'],
				['nombre' => 'Carrito de compras', 'responsable' => 'at', 'dias_habiles' => 4, 'servicio' => 'sitio_web_tienda', 'etapa' => 'desarrollo'],
				['nombre' => 'Integración con WhatsApp', 'responsable' => 'at', 'dias_habiles' => 3, 'servicio' => 'asistente_basico', 'etapa' => 'desarrollo', 'en_paralelo' => true],
				['nombre' => 'Carga de productos', 'responsable' => 'cliente', 'dias_habiles' => 3, 'servicio' => 'sitio_web_tienda'],
			]],
			['nombre' => 'Pagos', 'actividades' => [
				['nombre' => 'Pasarela de pago', 'responsable' => 'at', 'dias_habiles' => 1, 'servicio' => 'sitio_web_tienda', 'etapa' => 'desarrollo'],
				['nombre' => 'Diseño de la app', 'responsable' => 'at', 'dias_habiles' => 6, 'servicio' => 'app_movil', 'etapa' => 'diseno', 'origen' => 'tabla'],
			]],
			['nombre' => 'Pruebas', 'entrega' => true, 'actividades' => [
				['nombre' => 'Pruebas en celular', 'responsable' => 'at', 'dias_habiles' => 7, 'servicio' => 'sitio_web_tienda', 'etapa' => 'pruebas', 'origen' => 'luis'],
				['nombre' => 'Pruebas de pago', 'responsable' => 'ambos', 'dias_habiles' => 2, 'servicio' => 'sitio_web_tienda', 'etapa' => 'pruebas'],
			]],
		]],
		['clave' => 'implementacion', 'bloques' => [
			['nombre' => 'Puesta en marcha', 'actividades' => [
				['nombre' => 'Publicación', 'responsable' => 'at', 'dias_habiles' => 1, 'servicio' => 'sitio_web_tienda', 'etapa' => 'implementacion'],
				['nombre' => 'Capacitación', 'responsable' => 'ambos', 'dias_habiles' => 1, 'etapa' => 'implementacion'],
			]],
		]],
		['clave' => 'soporte', 'bloques' => [
			['nombre' => 'Garantía', 'actividades' => [
				['nombre' => 'Ajustes menores', 'responsable' => 'at', 'dias_habiles' => 10, 'servicio' => 'sitio_web_tienda', 'etapa' => 'soporte'],
			]],
		]],
	],
])['plan'];
function act(array $plan, string $nombre): ?array {
	foreach ($plan['fases'] as $f) {
		foreach ($f['bloques'] as $b) {
			foreach ($b['actividades'] as $a) {
				if ($a['nombre'] === $nombre) {
					return $a;
				}
			}
		}
	}
	return null;
}
$t = at_pt_aplicar_tabla($base, $tab);
$dias_origen = fn(string $n) => [act($t, $n)['dias_habiles'], act($t, $n)['origen']];
ok($dias_origen('Propuesta de diseño') === [3, 'tabla'] && $dias_origen('Ajustes de diseño') === [2, 'tabla'], 'sitio web o tienda, diseño = 5: la IA dijo 2 y 2 -> 3 y 2, origen tabla');
ok($dias_origen('Maquetación') === [5, 'tabla'] && $dias_origen('Carrito de compras') === [4, 'tabla'] && $dias_origen('Pasarela de pago') === [1, 'tabla'], 'desarrollo = 10 repartido entre bloques distintos: 4, 4 y 1 -> 5, 4 y 1');
ok($dias_origen('Integración con WhatsApp') === [4, 'tabla'], 'asistente básico, desarrollo = 4 aunque la IA dijo 3');
ok($dias_origen('Publicación') === [2, 'tabla'], 'sitio web o tienda, implementación = 2');
ok($dias_origen('Carga de productos') === [3, 'ia'] && $dias_origen('Capacitación') === [1, 'ia'] && $dias_origen('Ajustes menores') === [10, 'ia'], 'sin etapa, sin servicio o etapa de soporte: días de la IA, origen ia');
ok($dias_origen('Diseño de la app') === [6, 'ia'], 'servicio que no está en la tabla: origen ia aunque la IA diga tabla');
ok($dias_origen('Pruebas en celular') === [7, 'luis'] && $dias_origen('Pruebas de pago') === [2, 'ia'], 'Review Focus 3: el par con días de Luis no se toca (7 de Luis y 2 de la IA quedan)');
ok($dias_origen('Reunión de inicio') === [1, 'tabla'] && $dias_origen('Entrega de logo, textos y accesos') === [3, 'tabla'], 'el Arranque fijo no cambia');
ok(at_pt_aplicar_tabla($t, $tab) === $t, 'aplicar la tabla dos veces da lo mismo');
$tab_luis = $tab;
$tab_luis['sitio_web_tienda']['diseno'] = 8;
$t2 = at_pt_aplicar_tabla($base, $tab_luis);
ok(act($t2, 'Propuesta de diseño')['dias_habiles'] === 4 && act($t2, 'Ajustes de diseño')['dias_habiles'] === 4, 'con la tabla corregida por Luis (diseño = 8): 4 y 4');
unset($tab_luis['asistente_basico']);
ok(act(at_pt_aplicar_tabla($base, $tab_luis), 'Integración con WhatsApp') === array_merge(act($base, 'Integración con WhatsApp'), ['origen' => 'ia']), 'si Luis quita un servicio de la tabla, sus actividades quedan con los días de la IA y origen ia');
ok(act(at_pt_aplicar_tabla($base, []), 'Maquetación')['origen'] === 'ia' && act(at_pt_aplicar_tabla($base, []), 'Maquetación')['dias_habiles'] === 4, 'tabla vacía: todo queda de la IA');

// Review Focus 6: la tabla puede pasar el tope de días, así que el plan se valida otra vez después de aplicarla.
$por_servicio = fn(string $bloque, string $etapa, bool $entrega) => ['nombre' => $bloque, 'entrega' => $entrega, 'actividades' => array_map(fn($s) => ['nombre' => "{$bloque} · {$s}", 'responsable' => 'at', 'dias_habiles' => 1, 'servicio' => $s, 'etapa' => $etapa], array_keys($tab))];
$combinado = at_pt_validar_plan(['proyecto' => '[PRUEBA] Proyecto combinado', 'fases' => [
	['clave' => 'diseno_desarrollo', 'bloques' => [$por_servicio('Diseño', 'diseno', true), $por_servicio('Desarrollo', 'desarrollo', true), $por_servicio('Pruebas', 'pruebas', true)]],
	['clave' => 'implementacion', 'bloques' => [$por_servicio('Puesta en marcha', 'implementacion', false)]],
	['clave' => 'soporte', 'bloques' => [['nombre' => 'Garantía', 'actividades' => [['nombre' => 'Ajustes', 'responsable' => 'at', 'dias_habiles' => 10]]]]],
]]);
$tras_tabla = at_pt_validar_plan(at_pt_aplicar_tabla($combinado['plan'], $tab));
ok($combinado['ok'] && !$tras_tabla['ok'] && $tras_tabla['errores'] === ['El plan suma 138 días hábiles con las revisiones (máximo 130, unas 26 semanas): acórtalo o divide el proyecto.'] && $tras_tabla['plan'] === [], 'los siete servicios juntos: 57 días hábiles de la IA pasan a 138 con la tabla; validar otra vez lo deja en error');
ok(at_pt_validar_plan($t)['ok'] && at_pt_validar_plan($t)['plan'] === $t, 'validar después de aplicar la tabla conserva días y orígenes (es idempotente)');

fin();
````

- [ ] **Step 17: Correr y ver que falla.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/validacion-test.php; echo "exit=$?"
```

  Expected: primero 91 líneas `ok   …` de las pruebas que ya pasaban y después `Fatal error: Uncaught Error: Call to undefined function at_pt_repartir()`; `exit=255`. Es el motivo correcto del fallo: la función todavía no existe.

- [ ] **Step 18: Implementar** — agregar el reparto y la aplicación de la tabla al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Edit con `old_string` = el final actual del archivo (estas líneas, que aparecen una sola vez):

````text
		array_unshift($v['avisos'], 'La IA mandó su propio bloque «Arranque»: se usó el fijo (reunión de inicio y entrega de logo, textos y accesos).');
	}
	return $v;
}
````

  y `new_string` = esas mismas líneas, una línea en blanco y el bloque:

````php
/* ---------- Tabla de tiempos -> días (Task 2) ---------- */

/** Reparte $total días entre actividades en proporción a $pesos (los días que propuso la IA): método del resto mayor
 *  con mínimo 1 por actividad. Se da a cada una la parte entera de su cuota (al menos 1); lo que falta va de a un día
 *  a las de mayor fracción (empate: la que va primero), sin contar las que subieron al mínimo de 1, que ya recibieron
 *  más que su cuota; si el mínimo de 1 hizo pasarse, se quita a la más pasada de su cuota. Así a la que la IA le
 *  estimó más nunca le tocan menos días que a otra. Si $total es menor que la cantidad de actividades, 1 a cada una.
 *  Cuentas en enteros: sin errores de coma. */
function at_pt_repartir(int $total, array $pesos): array {
	$pesos = array_map(static fn($p): int => max(1, (int) $p), array_values($pesos));
	$n = count($pesos);
	if ($n === 0) {
		return [];
	}
	if ($total <= $n) {
		return array_fill(0, $n, 1);
	}
	$suma = array_sum($pesos);
	$dias = [];
	$resto = [];
	foreach ($pesos as $i => $p) {
		$entero = intdiv($total * $p, $suma);
		$dias[$i] = max(1, $entero);
		// La que subió al mínimo de 1 ya recibió más que su cuota: no compite por los días que faltan.
		$resto[$i] = $entero >= 1 ? ($total * $p) % $suma : -1;
	}
	$falta = $total - array_sum($dias);
	if ($falta > 0) {
		$orden = array_keys($pesos);
		usort($orden, static fn(int $a, int $b): int => [$resto[$b], $a] <=> [$resto[$a], $b]);
		for ($k = 0; $k < $falta; $k++) {
			$dias[$orden[$k % $n]]++;
		}
	}
	while (array_sum($dias) > $total) {
		$quitar = null;
		$exceso_max = null;
		foreach ($dias as $i => $d) {
			$exceso = $d * $suma - $total * $pesos[$i]; // (días - cuota) × suma, en enteros
			if ($d > 1 && ($exceso_max === null || $exceso >= $exceso_max)) {
				$quitar = $i;
				$exceso_max = $exceso;
			}
		}
		$dias[$quitar]--;
	}
	return $dias;
}

/** Pone los días de la tabla de tiempos donde calza. Para cada par (servicio, etapa) con el servicio en la tabla y la
 *  etapa en diseño, desarrollo, pruebas o implementación, reparte el total de la tabla entre las actividades de ese
 *  par en todo el plan (at_pt_repartir, pesos = los días de la IA) y las marca 'tabla'. Si el par tiene alguna
 *  actividad 'luis', no se toca (Review Focus 3). Las que no calzan y no son 'luis' quedan 'ia'. Las de la etapa
 *  'arranque' (el bloque fijo) conservan su origen. */
function at_pt_aplicar_tabla(array $plan, array $tabla): array {
	$etapas_tabla = at_pt_etapas_tabla();
	$grupos = [];
	foreach (($plan['fases'] ?? []) as $fi => $fase) {
		foreach (($fase['bloques'] ?? []) as $bi => $bloque) {
			foreach (($bloque['actividades'] ?? []) as $ai => $a) {
				$servicio = (string) ($a['servicio'] ?? '');
				$etapa = (string) ($a['etapa'] ?? '');
				if ($etapa === 'arranque') {
					continue;
				}
				if ($servicio !== '' && in_array($etapa, $etapas_tabla, true) && is_array($tabla[$servicio] ?? null) && isset($tabla[$servicio][$etapa])) {
					$grupos[$servicio][$etapa][] = [$fi, $bi, $ai];
				} elseif (($a['origen'] ?? '') !== 'luis') {
					$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] = 'ia';
				}
			}
		}
	}
	foreach ($grupos as $servicio => $por_etapa) {
		foreach ($por_etapa as $etapa => $lugares) {
			$pesos = [];
			foreach ($lugares as [$fi, $bi, $ai]) {
				$a = $plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai];
				if (($a['origen'] ?? '') === 'luis') {
					continue 2; // el grupo tiene días de Luis: no se toca
				}
				$pesos[] = (int) ($a['dias_habiles'] ?? 1);
			}
			$dias = at_pt_repartir((int) $tabla[$servicio][$etapa], $pesos);
			foreach ($lugares as $i => [$fi, $bi, $ai]) {
				$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['dias_habiles'] = $dias[$i];
				$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] = 'tabla';
			}
		}
	}
	return $plan;
}
````

- [ ] **Step 19: Correr y ver que pasa.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/validacion-test.php; echo "exit=$?" && /c/wamp64/bin/php/php8.4.15/php.exe -l wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php
```

  Expected: 116 líneas `ok   …` en total (91 de antes + 25 nuevas), ninguna `FALLA`, la última línea `TODO OK`, `exit=0` y `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Si una aserción falla se corrige el código, no la prueba (la prueba solo se cambia si contradice el esqueleto o la spec, y se explica en el reporte).

- [ ] **Step 20: Commit.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && git add wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php tests/plan/validacion-test.php && git commit -m "feat(plan): tabla de tiempos a días con reparto proporcional" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

**Ciclo E — marcas de origen entre versiones (Review Focus 3)**

- [ ] **Step 21: Escribir las pruebas que fallan** — en `tests/plan/validacion-test.php`, reemplazar la última línea `fin();` (Edit: `old_string` = `fin();`, que aparece una sola vez) por este bloque, que termina en `fin();` (Luis edita en el panel, se reaplica la tabla y después pide cambios dos veces: sus días no se pisan, lo que estima la IA queda «IA · revisar», aviso si la IA renombra una actividad de Luis y un borrador con una marca «luis» inventada recibe la tabla):

````php
// Review Focus 3: Luis edita días a mano y después se reaplica la tabla o pide cambios a la IA: sus días no se pisan.
function cambiar(array $plan, string $nombre, array $campos): array {
	foreach ($plan['fases'] as $fi => $f) {
		foreach ($f['bloques'] as $bi => $b) {
			foreach ($b['actividades'] as $ai => $a) {
				if ($a['nombre'] === $nombre) {
					$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai] = array_merge($a, $campos);
				}
			}
		}
	}
	return $plan;
}
$guardado = $t; // plan con la tabla aplicada (bloque anterior); bloques de la fase 1: Arranque, Diseño, Desarrollo, Pagos, Pruebas
ok(at_pt_marcar_ediciones($guardado, $guardado) === $guardado, 'guardar sin cambios no cambia ningún origen');

// 1) En el panel: Maquetación de 5 a 7 días, una actividad nueva y un origen manipulado en el formulario.
$form = cambiar($guardado, 'Maquetación', ['dias_habiles' => 7]);
$form = cambiar($form, 'Propuesta de diseño', ['origen' => 'ia']);
$form['fases'][0]['bloques'][2]['actividades'][] = ['nombre' => 'Sesión de fotos', 'responsable' => 'cliente', 'dias_habiles' => 2, 'origen' => 'tabla'];
$editado = at_pt_marcar_ediciones($guardado, at_pt_validar_plan($form)['plan']);
ok([act($editado, 'Maquetación')['dias_habiles'], act($editado, 'Maquetación')['origen']] === [7, 'luis'], 'días cambiados en el panel: origen luis');
ok(act($editado, 'Sesión de fotos')['origen'] === 'luis', 'actividad nueva en el panel: origen luis');
ok([act($editado, 'Propuesta de diseño')['dias_habiles'], act($editado, 'Propuesta de diseño')['origen']] === [3, 'tabla'], 'días iguales: conserva el origen guardado aunque el formulario diga otro');
ok(act($editado, 'Carrito de compras')['origen'] === 'tabla' && act($editado, 'Reunión de inicio')['origen'] === 'tabla', 'lo que no cambió conserva su origen');

// 2) Se reaplica la tabla: el par (sitio_web_tienda, desarrollo) tiene días de Luis y no se toca.
$retabla = at_pt_aplicar_tabla($editado, $tab);
ok(act($retabla, 'Maquetación')['dias_habiles'] === 7 && act($retabla, 'Carrito de compras')['dias_habiles'] === 4 && act($retabla, 'Pasarela de pago')['dias_habiles'] === 1, 'reaplicar la tabla no pisa los 7 días de Luis ni reparte su grupo');
ok(act($retabla, 'Sesión de fotos')['origen'] === 'luis' && act($retabla, 'Propuesta de diseño')['dias_habiles'] === 3, 'la actividad de Luis sin par sigue siendo de Luis; los demás grupos, igual');

// 3) «Pedir cambios»: la IA cambia los días de Luis, mueve su actividad de bloque, cambia otra e inventa una marca 'luis'.
//    Orden de la Task 7: validar -> respetar días de Luis -> marcar con 'ia' -> validar -> calcular.
$ia2 = cambiar($editado, 'Maquetación', ['dias_habiles' => 3, 'origen' => 'ia']);
$ia2 = cambiar($ia2, 'Carrito de compras', ['dias_habiles' => 6]);
$ia2['fases'][0]['bloques'][2]['actividades'] = array_values(array_filter($ia2['fases'][0]['bloques'][2]['actividades'], fn($a) => $a['nombre'] !== 'Sesión de fotos'));
$ia2['fases'][0]['bloques'][1]['actividades'][] = ['nombre' => 'Sesión de fotos', 'responsable' => 'cliente', 'dias_habiles' => 4, 'origen' => 'ia'];
$ia2['fases'][0]['bloques'][4]['actividades'][] = ['nombre' => 'Revisión de textos', 'responsable' => 'ambos', 'dias_habiles' => 2, 'origen' => 'luis'];
$respetado = at_pt_respetar_dias_luis($editado, at_pt_validar_plan($ia2)['plan']);
ok([act($respetado, 'Maquetación')['dias_habiles'], act($respetado, 'Maquetación')['origen']] === [7, 'luis'], 'la IA cambió los días de Luis: vuelven a 7 y a luis');
ok([act($respetado, 'Sesión de fotos')['dias_habiles'], act($respetado, 'Sesión de fotos')['origen']] === [2, 'luis'], 'la actividad de Luis movida de bloque conserva sus 2 días');
ok(act($respetado, 'Revisión de textos')['origen'] === 'ia', 'la IA no puede crear una marca luis');
ok(act($respetado, 'Carrito de compras')['dias_habiles'] === 6 && act($respetado, 'Pruebas en celular')['dias_habiles'] === 7, 'los demás cambios de la IA se aplican; los días de Luis de antes siguen');
$tras_cambios = at_pt_marcar_ediciones($editado, $respetado, 'ia');
ok(act($tras_cambios, 'Maquetación')['origen'] === 'luis' && act($tras_cambios, 'Maquetación')['dias_habiles'] === 7 && act($tras_cambios, 'Sesión de fotos')['origen'] === 'luis', 'después de marcar, los días de Luis siguen siendo de Luis');
ok(act($tras_cambios, 'Carrito de compras')['origen'] === 'ia' && act($tras_cambios, 'Revisión de textos')['origen'] === 'ia', 'lo que la IA cambió o agregó al pedir cambios queda ia («revisar»), nunca luis');
ok(act($tras_cambios, 'Propuesta de diseño')['origen'] === 'tabla', 'lo que la IA no tocó conserva su origen');
$ronda2 = at_pt_marcar_ediciones($tras_cambios, at_pt_respetar_dias_luis($tras_cambios, at_pt_validar_plan(cambiar($tras_cambios, 'Carrito de compras', ['dias_habiles' => 3]))['plan']), 'ia');
ok(act($ronda2, 'Carrito de compras')['dias_habiles'] === 3 && act($ronda2, 'Carrito de compras')['origen'] === 'ia' && act($ronda2, 'Maquetación')['dias_habiles'] === 7, 'segunda ronda: la IA puede volver a cambiar lo que estimó ella; los 7 días de Luis siguen');
ok(at_pt_marcar_ediciones($editado, $respetado, 'otra') === at_pt_marcar_ediciones($editado, $respetado), 'una marca desconocida vale luis (la del panel)');
$renombrada = cambiar($editado, 'Maquetación', ['nombre' => 'Maquetación responsive', 'dias_habiles' => 3]);
ok(at_pt_luis_perdidas($editado, at_pt_validar_plan($renombrada)['plan']) === ['La IA renombró o quitó «Maquetación», que tenía 7 días hábiles puestos por ti: revísala en el panel.'] && at_pt_luis_perdidas($editado, $respetado) === [], 'la IA renombra o quita una actividad de Luis: aviso con sus días; si siguen todas, sin avisos');

// 4) Nombres repetidos: se reconocen por orden de aparición.
$dup = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'Diseño', 'actividades' => [
	['nombre' => 'Revisión interna', 'responsable' => 'at', 'dias_habiles' => 1],
	['nombre' => 'Revisión interna', 'responsable' => 'at', 'dias_habiles' => 4, 'origen' => 'luis'],
]]]]]])['plan'];
$dup_ia = $dup;
$dup_ia['fases'][0]['bloques'][1]['actividades'][0]['dias_habiles'] = 9;
$dup_ia['fases'][0]['bloques'][1]['actividades'][1]['dias_habiles'] = 9;
$dup_r = at_pt_respetar_dias_luis($dup, $dup_ia)['fases'][0]['bloques'][1]['actividades'];
ok([$dup_r[0]['dias_habiles'], $dup_r[0]['origen'], $dup_r[1]['dias_habiles'], $dup_r[1]['origen']] === [9, 'ia', 4, 'luis'], 'dos «Revisión interna»: solo la segunda (la de Luis) recupera sus días');
ok(array_column(at_pt_recorrer_actividades($dup), 3) === ['reunión de inicio#1', 'entrega de logo, textos y accesos#1', 'revisión interna#1', 'revisión interna#2'], 'claves de actividad: nombre normalizado y número de aparición');

// 5) Borrador nuevo: sin versión anterior, la IA no puede traer marcas luis.
ok(act(at_pt_respetar_dias_luis([], $base), 'Pruebas en celular')['origen'] === 'ia' && act(at_pt_respetar_dias_luis([], $base), 'Pruebas en celular')['dias_habiles'] === 7, 'borrador nuevo: la marca luis de la IA baja a ia y conserva los días');
$borrador = at_pt_aplicar_tabla(at_pt_respetar_dias_luis([], $base), $tab);
ok([act($borrador, 'Pruebas en celular')['dias_habiles'], act($borrador, 'Pruebas en celular')['origen'], act($borrador, 'Pruebas de pago')['dias_habiles'], act($borrador, 'Pruebas de pago')['origen']] === [2, 'tabla', 1, 'tabla'], 'borrador: una marca luis inventada por la IA no bloquea la tabla; el par (sitio web, pruebas) recibe sus 3 días: 2 y 1');

fin();
````

- [ ] **Step 22: Correr y ver que falla.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/validacion-test.php; echo "exit=$?"
```

  Expected: primero 116 líneas `ok   …` de las pruebas que ya pasaban y después `Fatal error: Uncaught Error: Call to undefined function at_pt_marcar_ediciones()`; `exit=255`. Es el motivo correcto del fallo: la función todavía no existe.

- [ ] **Step 23: Implementar** — agregar las marcas de origen al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Edit con `old_string` = el final actual del archivo (estas líneas, que aparecen una sola vez):

````text
				$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] = 'tabla';
			}
		}
	}
	return $plan;
}
````

  y `new_string` = esas mismas líneas, una línea en blanco y el bloque:

````php
/* ---------- Marcas de origen entre versiones del plan (Task 2) ---------- */

/** Lugar y clave de cada actividad del plan: [fase, bloque, actividad, clave]. La clave es el nombre normalizado y
 *  cuántas veces apareció antes ese nombre («revisión interna#1», «revisión interna#2»). No depende del bloque ni de la
 *  fase: mover una actividad de bloque no la hace nueva; renombrarla, sí (at_pt_luis_perdidas avisa si era de Luis).
 *  Límite conocido: si la IA inserta otra actividad con el mismo nombre ANTES de una de Luis, la marca y los días de
 *  Luis pasan a la primera de las dos (no hay otra forma de reconocerlas: la IA no conserva identificadores). */
function at_pt_recorrer_actividades(array $plan): array {
	$lugares = [];
	$vistas = [];
	foreach (($plan['fases'] ?? []) as $fi => $fase) {
		foreach (($fase['bloques'] ?? []) as $bi => $bloque) {
			foreach (($bloque['actividades'] ?? []) as $ai => $a) {
				$nombre = at_pt_clave_nombre((string) ($a['nombre'] ?? ''));
				$vistas[$nombre] = ($vistas[$nombre] ?? 0) + 1;
				$lugares[] = [$fi, $bi, $ai, $nombre . '#' . $vistas[$nombre]];
			}
		}
	}
	return $lugares;
}

/** Marca de origen de lo que cambió entre la versión guardada y la nueva: una actividad nueva o cuyos dias_habiles
 *  cambiaron queda con $marca; las demás conservan el origen guardado (no el que venga en el formulario o de la IA).
 *  $marca 'luis' (por defecto) al guardar desde el panel: lo cambió Luis a mano. $marca 'ia' en «Pedir cambios»: lo
 *  cambió o lo agregó la IA, así que el panel lo muestra «IA · revisar» y la IA lo puede volver a cambiar en la
 *  ronda siguiente. Cualquier otro valor de $marca vale 'luis'. */
function at_pt_marcar_ediciones(array $anterior, array $nuevo, string $marca = 'luis'): array {
	$marca = $marca === 'ia' ? 'ia' : 'luis';
	$guardadas = [];
	foreach (at_pt_recorrer_actividades($anterior) as [$fi, $bi, $ai, $clave]) {
		$guardadas[$clave] = $anterior['fases'][$fi]['bloques'][$bi]['actividades'][$ai];
	}
	foreach (at_pt_recorrer_actividades($nuevo) as [$fi, $bi, $ai, $clave]) {
		$a = $nuevo['fases'][$fi]['bloques'][$bi]['actividades'][$ai];
		$antes = $guardadas[$clave] ?? null;
		$cambio = $antes === null || (int) ($antes['dias_habiles'] ?? 0) !== (int) ($a['dias_habiles'] ?? 0);
		$nuevo['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] = $cambio ? $marca : (string) ($antes['origen'] ?? ($a['origen'] ?? 'ia'));
	}
	return $nuevo;
}

/** «Pedir cambios» con IA (Review Focus 3): las actividades que en $anterior eran 'luis' recuperan sus días y su marca
 *  aunque la IA los haya cambiado, y la IA no puede crear marcas 'luis' (las que no lo eran vuelven a 'ia'). Con
 *  $anterior vacío (borrador nuevo) solo baja a 'ia' las marcas 'luis' que invente la IA, para que la tabla de tiempos
 *  no se salte ese grupo. La ruta REST la llama después de validar y antes de at_pt_aplicar_tabla() (borrador) o de
 *  at_pt_marcar_ediciones(..., 'ia') (cambios). */
function at_pt_respetar_dias_luis(array $anterior, array $nuevo): array {
	$de_luis = [];
	foreach (at_pt_recorrer_actividades($anterior) as [$fi, $bi, $ai, $clave]) {
		$a = $anterior['fases'][$fi]['bloques'][$bi]['actividades'][$ai];
		if (($a['origen'] ?? '') === 'luis') {
			$de_luis[$clave] = (int) ($a['dias_habiles'] ?? 1);
		}
	}
	foreach (at_pt_recorrer_actividades($nuevo) as [$fi, $bi, $ai, $clave]) {
		$origen = (string) ($nuevo['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] ?? '');
		if (isset($de_luis[$clave])) {
			$nuevo['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['dias_habiles'] = $de_luis[$clave];
			$nuevo['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] = 'luis';
		} elseif ($origen === 'luis') {
			$nuevo['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['origen'] = 'ia';
		}
	}
	return $nuevo;
}

/** Actividades que eran 'luis' en $anterior y ya no están en $nuevo (la IA las renombró o las quitó al pedir cambios):
 *  sus días no se pueden devolver solos. La ruta de «cambios» (Task 7) suma estos textos a los avisos del plan. */
function at_pt_luis_perdidas(array $anterior, array $nuevo): array {
	$en_nuevo = [];
	foreach (at_pt_recorrer_actividades($nuevo) as [, , , $clave]) {
		$en_nuevo[$clave] = true;
	}
	$avisos = [];
	foreach (at_pt_recorrer_actividades($anterior) as [$fi, $bi, $ai, $clave]) {
		$a = $anterior['fases'][$fi]['bloques'][$bi]['actividades'][$ai];
		if (($a['origen'] ?? '') === 'luis' && !isset($en_nuevo[$clave])) {
			$n = (int) ($a['dias_habiles'] ?? 1);
			$avisos[] = 'La IA renombró o quitó «' . $a['nombre'] . '», que tenía ' . ($n === 1 ? '1 día hábil' : "{$n} días hábiles") . ' puestos por ti: revísala en el panel.';
		}
	}
	return $avisos;
}
````

- [ ] **Step 24: Correr y ver que pasa.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/validacion-test.php; echo "exit=$?" && /c/wamp64/bin/php/php8.4.15/php.exe -l wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php
```

  Expected: 137 líneas `ok   …` en total (116 de antes + 21 nuevas), ninguna `FALLA`, la última línea `TODO OK`, `exit=0` y `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Además, sin regresiones: `cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/fechas-test.php | tail -1` imprime `TODO OK` por cada archivo. Si una aserción falla se corrige el código, no la prueba (la prueba solo se cambia si contradice el esqueleto o la spec, y se explica en el reporte).

- [ ] **Step 25: Commit.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && git add wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php tests/plan/validacion-test.php && git commit -m "feat(plan): marcas de origen y días de Luis protegidos al pedir cambios" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Puras — fechas del plan y cronograma de la carta Gantt

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php` (se agrega al final)
- Test: `tests/plan/cronograma-test.php`

**Interfaces:**
- Consumes: Task 1 — `at_pt_ymd(string $f): string`, `at_pt_dia(string $ymd): DateTimeImmutable`, `at_pt_es_habil(string $f, array $feriados): bool`, `at_pt_siguiente_habil(string $f, array $feriados): string`, `at_pt_sumar_habiles(string $desde, int $n, array $feriados): string`, `at_pt_inicio_por_defecto(string $fecha_firma, array $feriados): string`; Task 2 — `at_pt_clave_nombre(string $s): string`, `AT_PT_DIAS_REVISION` (= 5) y, en la prueba, `at_pt_validar_plan(array $plan): array`, `at_pt_aplicar_tabla(array $plan, array $tabla): array`, `at_pt_duraciones_defecto(): array`.
- Produces:
  - `at_pt_semanas(string $desde, string $hasta): int` — semanas de calendario (lunes a domingo) que toca el rango.
  - `at_pt_calcular_fechas(array $plan, string $inicio, array $feriados): array` — llena `desde`/`hasta` de cada actividad, `hitos[].fecha`, `fecha_inicio` (el **inicio efectivo**: si `$inicio` cae en fin de semana o feriado, el hábil siguiente) y `cronograma = {inicio, fin, semanas, barras[{fase, etiqueta, tipo, responsable, desde, hasta}], hitos[{nombre, fecha}]}` con las reglas del esqueleto. Barras en orden de secuencia (cada bloque seguido de su «Tu revisión»); hitos del cronograma ordenados por fecha, con «Entrega estimada» siempre. Si el último bloque de Implementación tiene entrega, la «Entrega estimada» es el fin del bloque (cuando AT entrega) y «Tu revisión» va después (esqueleto.md:183; confirmado por el orquestador en la decisión D4: es el día en que AT entrega, sin la revisión). Con `$inicio` inválido o sin `fases` devuelve el plan sin tocar.
  - **Peor caso para el renderer (fixture de la prueba de legibilidad de la Task 12, en esta prueba):** 14 bloques (el Arranque y 13 «Etapa N» con entrega, de 5 días los nueve primeros y de 4 los otros cuatro) = 27 barras y 130 días hábiles en 27 semanas, del 5-oct-2026 al 8-abr-2027 con los feriados de prueba; un día más ya es error. El máximo teórico es 28 barras si el panel deja marcar con entrega el Arranque.

**Review Focus cubierto aquí:** 1 (inicio en sábado o feriado; feriado del 12-oct-2026 dentro del bloque Diseño; sábado 31-oct y domingo 1-nov feriados entre actividades) y 6 (plataforma a medida + soporte: 15 barras en 14 semanas, del 5-oct-2026 al 4-ene-2027, todas en días hábiles y sin traslaparse; tope de 14 bloques: 27 barras en 23 semanas; peor caso: 27 barras y 130 días hábiles en 27 semanas).

- [ ] **Step 1: Escribir la prueba que falla** — crear `tests/plan/cronograma-test.php` (Write, archivo nuevo, UTF-8 sin BOM) con el cronograma completo de un sitio web (firma 29-sep-2026, inicio 5-oct-2026) calculado a mano, la carta larga de una plataforma y el peor caso de los topes:

````php
<?php
// Correr: php tests/plan/cronograma-test.php   (sin WordPress)
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

// Feriados de prueba: lunes 12-oct, sábado 31-oct y domingo 1-nov de 2026, martes 8-dic, viernes 25-dic y viernes 1-ene-2027.
$fer = ['2026-10-12', '2026-10-31', '2026-11-01', '2026-12-08', '2026-12-25', '2027-01-01'];
$a = fn(string $n, string $r, int $d, bool $paralela = false) => ['nombre' => $n, 'responsable' => $r, 'dias_habiles' => $d, 'en_paralelo' => $paralela];
function fechas(array $plan, string $nombre): array {
	foreach ($plan['fases'] as $f) {
		foreach ($f['bloques'] as $b) {
			foreach ($b['actividades'] as $x) {
				if ($x['nombre'] === $nombre) {
					return [$x['desde'], $x['hasta']];
				}
			}
		}
	}
	return [];
}

// Semanas de calendario (lunes a domingo)
ok(at_pt_semanas('2026-10-05', '2026-10-09') === 1 && at_pt_semanas('2026-10-05', '2026-10-05') === 1, 'lunes a viernes de la misma semana: 1');
ok(at_pt_semanas('2026-10-05', '2026-10-12') === 2 && at_pt_semanas('2026-10-09', '2026-10-12') === 2, 'de un viernes al lunes siguiente: 2 semanas');
ok(at_pt_semanas('2026-10-04', '2026-10-05') === 2, 'domingo 4 y lunes 5-oct son semanas distintas');
ok(at_pt_semanas('2026-12-28', '2027-01-04') === 2 && at_pt_semanas('2026-10-05', '2026-11-27') === 8, 'cambio de año; 5-oct a 27-nov: 8 semanas');
ok(at_pt_semanas('2026-10-09', '2026-10-05') === 0 && at_pt_semanas('basura', '2026-10-05') === 0, 'rango al revés o fecha inválida: 0');

// Sitio web: firma martes 29-sep-2026 -> inicio lunes 5-oct-2026, con el feriado del 12-oct dentro del bloque Diseño.
$plan = at_pt_validar_plan([
	'proyecto' => '[PRUEBA] Sitio web de Cliente Prueba',
	'fases'    => [
		['clave' => 'diseno_desarrollo', 'bloques' => [
			['nombre' => 'Diseño', 'entrega' => true, 'actividades' => [$a('Propuesta de diseño', 'at', 3), $a('Ajustes de diseño', 'at', 2)]],
			['nombre' => 'Desarrollo', 'actividades' => [$a('Maquetación', 'at', 5), $a('Formulario de contacto', 'at', 2, true), $a('Carga de textos', 'cliente', 1)]],
			['nombre' => 'Pruebas y revisión', 'entrega' => true, 'actividades' => [$a('Pruebas en celular y escritorio', 'at', 2)]],
		]],
		['clave' => 'implementacion', 'bloques' => [
			['nombre' => 'Puesta en marcha', 'actividades' => [$a('Publicación del sitio', 'at', 1), $a('Capacitación', 'ambos', 1)]],
		]],
		['clave' => 'soporte', 'bloques' => [
			['nombre' => 'Garantía', 'actividades' => [$a('Ajustes menores', 'at', 10)]],
		]],
	],
	'hitos' => [
		['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño'],
		['nombre' => 'Sitio aprobado', 'despues_de' => 'Pruebas y revisión'],
		['nombre' => 'Insumos recibidos', 'despues_de' => 'Arranque'],
	],
])['plan'];
$inicio = at_pt_inicio_por_defecto('2026-09-29', $fer);
$c = at_pt_calcular_fechas($plan, $inicio, $fer);
ok($inicio === '2026-10-05' && $c['fecha_inicio'] === '2026-10-05' && $c['cronograma']['inicio'] === '2026-10-05', 'firma martes 29-sep -> el plan parte el lunes 5-oct');
ok(fechas($c, 'Reunión de inicio') === ['2026-10-05', '2026-10-05'] && fechas($c, 'Entrega de logo, textos y accesos') === ['2026-10-06', '2026-10-08'], 'Arranque: reunión el 5-oct; insumos del 6 al 8-oct');
ok(fechas($c, 'Propuesta de diseño') === ['2026-10-09', '2026-10-14'], '3 días desde el viernes 9-oct saltan el feriado del lunes 12: 9, 13 y 14-oct');
ok(fechas($c, 'Ajustes de diseño') === ['2026-10-15', '2026-10-16'], 'la siguiente actividad parte el hábil siguiente: 15 y 16-oct');
ok(fechas($c, 'Maquetación') === ['2026-10-26', '2026-10-30'], 'después de la revisión de Diseño (19 al 23-oct) sigue Desarrollo el lunes 26-oct');
ok(fechas($c, 'Formulario de contacto') === ['2026-10-26', '2026-10-27'], 'en paralelo: parte el mismo día que la anterior (26-oct)');
ok(fechas($c, 'Carga de textos') === ['2026-11-02', '2026-11-02'], 'la siguiente parte después del mayor «hasta» del bloque (30-oct), saltando el sábado 31 y el domingo 1-nov feriados');
ok(fechas($c, 'Pruebas en celular y escritorio') === ['2026-11-03', '2026-11-04'] && fechas($c, 'Publicación del sitio') === ['2026-11-12', '2026-11-12'], 'Pruebas 3 y 4-nov; revisión del 5 al 11-nov; Implementación parte el 12-nov');
ok(fechas($c, 'Capacitación') === ['2026-11-13', '2026-11-13'] && fechas($c, 'Ajustes menores') === ['2026-11-16', '2026-11-27'], 'Capacitación el 13-nov; Soporte del 16 al 27-nov (10 días hábiles)');
$barras = array_map(fn($b) => [$b['etiqueta'], $b['tipo'], $b['responsable'], $b['desde'], $b['hasta']], $c['cronograma']['barras']);
ok($barras === [
	['Arranque', 'trabajo', 'ambos', '2026-10-05', '2026-10-08'],
	['Diseño', 'trabajo', 'at', '2026-10-09', '2026-10-16'],
	['Tu revisión', 'revision', 'cliente', '2026-10-19', '2026-10-23'],
	['Desarrollo', 'trabajo', 'ambos', '2026-10-26', '2026-11-02'],
	['Pruebas y revisión', 'trabajo', 'at', '2026-11-03', '2026-11-04'],
	['Tu revisión', 'revision', 'cliente', '2026-11-05', '2026-11-11'],
	['Puesta en marcha', 'trabajo', 'ambos', '2026-11-12', '2026-11-13'],
	['Garantía', 'trabajo', 'at', '2026-11-16', '2026-11-27'],
], 'barras: una por bloque (responsable común o ambos) y «Tu revisión» de 5 días hábiles tras cada bloque con entrega');
ok(array_column($c['cronograma']['barras'], 'fase') === ['diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo', 'implementacion', 'soporte'], 'cada barra dice su fase');
ok(array_keys($c['cronograma']['barras'][0]) === ['fase', 'etiqueta', 'tipo', 'responsable', 'desde', 'hasta'], 'claves de una barra, en orden');
ok($c['cronograma']['hitos'] === [
	['nombre' => 'Insumos recibidos', 'fecha' => '2026-10-08'],
	['nombre' => 'Diseño aprobado', 'fecha' => '2026-10-23'],
	['nombre' => 'Sitio aprobado', 'fecha' => '2026-11-11'],
	['nombre' => 'Entrega estimada', 'fecha' => '2026-11-13'],
], 'hitos en orden de fecha: fin de la revisión si el bloque tiene entrega, su fin si no; «Entrega estimada» = fin del último bloque de Implementación');
ok($c['hitos'] === [
	['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño', 'fecha' => '2026-10-23'],
	['nombre' => 'Sitio aprobado', 'despues_de' => 'Pruebas y revisión', 'fecha' => '2026-11-11'],
	['nombre' => 'Insumos recibidos', 'despues_de' => 'Arranque', 'fecha' => '2026-10-08'],
], 'los hitos del plan reciben su fecha');
ok($c['cronograma']['fin'] === '2026-11-27' && $c['cronograma']['semanas'] === 8, 'cronograma del 5-oct al 27-nov: 8 semanas de calendario');
ok(array_keys($c['cronograma']) === ['inicio', 'fin', 'semanas', 'barras', 'hitos'], 'claves del cronograma, en orden');
ok(at_pt_calcular_fechas($c, $inicio, $fer) === $c, 'recalcular da lo mismo');

// Review Focus 1: inicio en fin de semana o feriado -> el plan parte el hábil siguiente.
$sabado = at_pt_calcular_fechas($plan, '2026-10-10', $fer);
ok($sabado['fecha_inicio'] === '2026-10-13' && fechas($sabado, 'Reunión de inicio') === ['2026-10-13', '2026-10-13'], 'inicio sábado 10-oct (y lunes 12 feriado) -> parte el martes 13-oct');
ok(at_pt_calcular_fechas($plan, '2026-10-12', $fer)['cronograma']['inicio'] === '2026-10-13' && at_pt_calcular_fechas($plan, '2026-10-12', [])['cronograma']['inicio'] === '2026-10-12', 'inicio en feriado -> el hábil siguiente; sin ese feriado, ese mismo día');
$firma_sabado = at_pt_calcular_fechas($plan, at_pt_inicio_por_defecto('2026-10-03', $fer), $fer);
ok($firma_sabado['fecha_inicio'] === '2026-10-05', 'firma un sábado -> inicio el lunes siguiente');
ok(at_pt_calcular_fechas($plan, 'basura', $fer) === $plan && at_pt_calcular_fechas(['proyecto' => 'X'], '2026-10-05', $fer) === ['proyecto' => 'X'], 'inicio inválido o plan sin fases: el plan vuelve sin tocar');

// Paralela más larga que la anterior: el bloque termina con la más larga; la primera nunca es paralela.
$mini = ['fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'Bloque', 'entrega' => false, 'actividades' => [
	$a('A', 'at', 2, true), $a('B', 'cliente', 5, true), $a('C', 'at', 1),
]]]]]];
$m = at_pt_calcular_fechas($mini, '2026-10-05', []);
ok(fechas($m, 'A') === ['2026-10-05', '2026-10-06'] && fechas($m, 'B') === ['2026-10-05', '2026-10-09'] && fechas($m, 'C') === ['2026-10-12', '2026-10-12'], 'B en paralelo con A y más larga: C parte después de B');
ok($m['cronograma']['barras'] === [['fase' => 'diseno_desarrollo', 'etiqueta' => 'Bloque', 'tipo' => 'trabajo', 'responsable' => 'ambos', 'desde' => '2026-10-05', 'hasta' => '2026-10-12']], 'una barra, responsable ambos (AT y cliente)');
ok($m['cronograma']['hitos'] === [['nombre' => 'Entrega estimada', 'fecha' => '2026-10-12']], 'sin fase de Implementación, «Entrega estimada» = fin del cronograma');

// Review Focus 6: carta Gantt larga (plataforma a medida + soporte): 15 barras en 14 semanas, fechas en orden.
$d = fn(string $n, string $r, int $dias, string $s = '', string $e = '', bool $par = false) => ['nombre' => $n, 'responsable' => $r, 'dias_habiles' => $dias, 'servicio' => $s, 'etapa' => $e, 'en_paralelo' => $par];
$larga = at_pt_validar_plan(['proyecto' => '[PRUEBA] Plataforma de Cliente Prueba', 'fases' => [
	['clave' => 'diseno_desarrollo', 'bloques' => [
		['nombre' => 'Diseño', 'entrega' => true, 'actividades' => [$d('Mapa de pantallas', 'at', 3, 'plataforma', 'diseno'), $d('Diseño de pantallas', 'at', 5, 'plataforma', 'diseno')]],
		['nombre' => 'Desarrollo · etapa 1', 'entrega' => true, 'actividades' => [$d('Base de datos y usuarios', 'at', 5, 'plataforma', 'desarrollo'), $d('Panel de administración', 'at', 5, 'plataforma', 'desarrollo')]],
		['nombre' => 'Desarrollo · etapa 2', 'entrega' => true, 'actividades' => [$d('Reportes', 'at', 5, 'plataforma', 'desarrollo'), $d('Integración con pagos', 'at', 5, 'plataforma', 'desarrollo', true)]],
		['nombre' => 'Migración de datos', 'actividades' => [$d('Planilla con tus datos', 'cliente', 2), $d('Carga de datos', 'at', 2, '', '', true)]],
		['nombre' => 'Pruebas', 'entrega' => true, 'actividades' => [$d('Pruebas con usuarios reales', 'ambos', 5, 'plataforma', 'pruebas')]],
	]],
	['clave' => 'implementacion', 'bloques' => [
		['nombre' => 'Puesta en marcha', 'actividades' => [$d('Publicación', 'at', 2, 'plataforma', 'implementacion')]],
		['nombre' => 'Capacitación', 'actividades' => [$d('Capacitación del equipo', 'ambos', 1, 'plataforma', 'implementacion')]],
		['nombre' => 'Marcha blanca', 'actividades' => [$d('Acompañamiento', 'at', 2)]],
	]],
	['clave' => 'soporte', 'bloques' => [
		['nombre' => 'Garantía', 'actividades' => [$d('Corrección de errores', 'at', 2)]],
		['nombre' => 'Mejora continua', 'actividades' => [$d('Reunión mensual de mejoras', 'ambos', 1)]],
	]],
], 'hitos' => [['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño'], ['nombre' => 'Plataforma aprobada', 'despues_de' => 'Pruebas']]]);
$g = at_pt_calcular_fechas(at_pt_aplicar_tabla($larga['plan'], at_pt_duraciones_defecto()), '2026-10-05', $fer)['cronograma'];
ok($larga['ok'] && count($g['barras']) === 15 && $g['semanas'] === 14, 'plataforma + soporte: 10 bloques y 5 revisiones = 15 barras en 14 semanas (5-oct-2026 al 4-ene-2027)');
ok($g['inicio'] === '2026-10-05' && $g['fin'] === '2027-01-04', 'del 5-oct-2026 al 4-ene-2027 (salta los feriados del 12-oct, 8-dic, 25-dic y 1-ene)');
$en_orden = true;
$previo = '';
foreach ($g['barras'] as $b) {
	$en_orden = $en_orden && at_pt_es_habil($b['desde'], $fer) && at_pt_es_habil($b['hasta'], $fer) && $b['desde'] <= $b['hasta'] && $b['desde'] > $previo;
	$previo = $b['hasta'];
}
ok($en_orden, 'cada barra empieza y termina en día hábil, después de la anterior y sin traslaparse');
ok($g['barras'][1] === ['fase' => 'diseno_desarrollo', 'etiqueta' => 'Diseño', 'tipo' => 'trabajo', 'responsable' => 'at', 'desde' => '2026-10-09', 'hasta' => '2026-10-21'], 'Diseño con la tabla (8 días hábiles) salta el feriado del 12-oct: 9 al 21-oct');
ok($g['hitos'] === [['nombre' => 'Diseño aprobado', 'fecha' => '2026-10-28'], ['nombre' => 'Plataforma aprobada', 'fecha' => '2026-12-21'], ['nombre' => 'Entrega estimada', 'fecha' => '2026-12-29']], 'hitos de la carta larga; la entrega estimada es el fin de la Marcha blanca');
$tope = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => array_map(fn($i) => ['nombre' => "Etapa {$i}", 'entrega' => true, 'actividades' => [$a("Trabajo {$i}", 'at', 3)]], range(1, 13))]]]);
$gt = at_pt_calcular_fechas($tope['plan'], '2026-10-05', $fer)['cronograma'];
ok($tope['ok'] && count($gt['barras']) === 27 && $gt['semanas'] === 23 && $gt['fin'] === '2027-03-09', 'el tope de 14 bloques da 27 barras (el Arranque no tiene revisión) en 23 semanas, hasta el 9-mar-2027');
// Peor caso que el renderer debe dejar legible (fixture de la Task 12): 14 bloques, 27 barras, 130 días hábiles.
$peor = at_pt_validar_plan(['proyecto' => '[PRUEBA] Peor caso', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => array_map(fn($i) => ['nombre' => "Etapa {$i}", 'entrega' => true, 'actividades' => [$a("Trabajo {$i}", 'at', $i <= 9 ? 5 : 4)]], range(1, 13))]]]);
$gp = at_pt_calcular_fechas($peor['plan'], '2026-10-05', $fer)['cronograma'];
$habiles = 0;
for ($dia = $gp['inicio']; $dia <= $gp['fin']; $dia = at_pt_siguiente_habil($dia, $fer)) {
	$habiles++;
}
ok($peor['ok'] && count($gp['barras']) === 27 && $habiles === 130 && $gp['semanas'] === 27 && $gp['fin'] === '2027-04-08', 'peor caso: 27 barras y 130 días hábiles en 27 semanas (5-oct-2026 al 8-abr-2027)');
$peor_mas_uno = $peor['plan'];
$peor_mas_uno['fases'][0]['bloques'][1]['actividades'][0]['dias_habiles'] = 6;
ok(at_pt_validar_plan($peor_mas_uno)['errores'] === ['El plan suma 131 días hábiles con las revisiones (máximo 130, unas 26 semanas): acórtalo o divide el proyecto.'], 'un día hábil más que el peor caso ya no se acepta');

// «Entrega estimada» cuando el último bloque de Implementación tiene entrega: el fin del bloque (cuando entregamos),
// antes de la revisión del cliente; «Tu revisión» sigue apareciendo como barra.
$impl = at_pt_calcular_fechas(at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'implementacion', 'bloques' => [['nombre' => 'Puesta en marcha', 'entrega' => true, 'actividades' => [$a('Publicación', 'at', 2)]]]]]])['plan'], '2026-10-05', []);
ok($impl['cronograma']['hitos'] === [['nombre' => 'Entrega estimada', 'fecha' => '2026-10-12']] && end($impl['cronograma']['barras'])['desde'] === '2026-10-13' && $impl['cronograma']['fin'] === '2026-10-19', 'Implementación con entrega: «Entrega estimada» el 12-oct (fin del bloque); tu revisión del 13 al 19-oct');

fin();
````

- [ ] **Step 2: Correr y ver que falla.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/cronograma-test.php; echo "exit=$?"
```

  Expected: `Fatal error: Uncaught Error: Call to undefined function at_pt_semanas()`; `exit=255`. Es el motivo correcto del fallo: la función todavía no existe.

- [ ] **Step 3: Implementar** — agregar el cálculo de fechas y el cronograma al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Edit con `old_string` = el final actual del archivo (estas líneas, que aparecen una sola vez):

````text
			$avisos[] = 'La IA renombró o quitó «' . $a['nombre'] . '», que tenía ' . ($n === 1 ? '1 día hábil' : "{$n} días hábiles") . ' puestos por ti: revísala en el panel.';
		}
	}
	return $avisos;
}
````

  y `new_string` = esas mismas líneas, una línea en blanco y el bloque:

````php
/* ---------- Fechas y cronograma de la carta Gantt (Task 3) ---------- */

/** Semanas de calendario (de lunes a domingo) que toca el rango; 0 si una fecha es inválida o el rango está al revés. */
function at_pt_semanas(string $desde, string $hasta): int {
	$a = at_pt_ymd($desde);
	$b = at_pt_ymd($hasta);
	if ($a === '' || $b === '' || $b < $a) {
		return 0;
	}
	$lunes = static function (string $f): DateTimeImmutable {
		$d = at_pt_dia($f);
		return $d->modify('-' . ((int) $d->format('N') - 1) . ' days');
	};
	return intdiv((int) $lunes($a)->diff($lunes($b))->days, 7) + 1;
}

/** Llena desde y hasta de cada actividad, la fecha de cada hito, fecha_inicio y el cronograma de la carta Gantt.
 *  Secuencia: fases, bloques y actividades en su orden. La primera actividad de un bloque parte en el cursor; cada
 *  siguiente parte el hábil siguiente al mayor «hasta» del bloque, salvo en_paralelo, que parte el mismo día que la
 *  anterior. Un bloque con entrega suma «Tu revisión» (5 días hábiles, del cliente) desde el hábil siguiente a su fin,
 *  y el cursor sigue después de la revisión. Barras: una por bloque (responsable común o 'ambos') y una por revisión.
 *  Hitos: fin de la revisión del bloque despues_de (o su fin si no tiene entrega); se agrega siempre «Entrega
 *  estimada» = fin del último bloque de implementación (o fin del cronograma si no hay esa fase). Un $inicio que no es
 *  hábil parte el hábil siguiente (Review Focus 1). Con $inicio inválido devuelve el plan sin tocar. */
function at_pt_calcular_fechas(array $plan, string $inicio, array $feriados): array {
	$cursor = at_pt_sumar_habiles($inicio, 1, $feriados);
	if ($cursor === '' || !is_array($plan['fases'] ?? null)) {
		return $plan;
	}
	$plan['fecha_inicio'] = $cursor;
	$barras = [];
	$fecha_hito = [];
	$fin_implementacion = '';
	$fin = $cursor;
	foreach ($plan['fases'] as $fi => $fase) {
		$clave_fase = (string) ($fase['clave'] ?? '');
		foreach (($fase['bloques'] ?? []) as $bi => $bloque) {
			$actividades = $bloque['actividades'] ?? [];
			if (!is_array($actividades) || $actividades === []) {
				continue;
			}
			$inicio_bloque = $cursor;
			$fin_bloque = '';
			$desde_anterior = $cursor;
			$responsables = [];
			$primera = true;
			foreach ($actividades as $ai => $a) {
				if ($primera) {
					$desde = $cursor;
				} elseif (!empty($a['en_paralelo'])) {
					$desde = $desde_anterior;
				} else {
					$desde = at_pt_siguiente_habil($fin_bloque, $feriados);
				}
				$hasta = at_pt_sumar_habiles($desde, max(1, (int) ($a['dias_habiles'] ?? 1)), $feriados);
				$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['desde'] = $desde;
				$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai]['hasta'] = $hasta;
				$fin_bloque = max($fin_bloque, $hasta);
				$desde_anterior = $desde;
				$responsables[(string) ($a['responsable'] ?? 'ambos')] = true;
				$primera = false;
			}
			$barras[] = [
				'fase'        => $clave_fase,
				'etiqueta'    => (string) ($bloque['nombre'] ?? ''),
				'tipo'        => 'trabajo',
				'responsable' => count($responsables) === 1 ? (string) array_key_first($responsables) : 'ambos',
				'desde'       => $inicio_bloque,
				'hasta'       => $fin_bloque,
			];
			$termina = $fin_bloque;
			if (!empty($bloque['entrega'])) {
				$revision_desde = at_pt_siguiente_habil($fin_bloque, $feriados);
				$termina = at_pt_sumar_habiles($revision_desde, AT_PT_DIAS_REVISION, $feriados);
				$barras[] = ['fase' => $clave_fase, 'etiqueta' => 'Tu revisión', 'tipo' => 'revision', 'responsable' => 'cliente', 'desde' => $revision_desde, 'hasta' => $termina];
			}
			$fecha_hito[at_pt_clave_nombre((string) ($bloque['nombre'] ?? ''))] ??= $termina;
			if ($clave_fase === 'implementacion') {
				$fin_implementacion = $fin_bloque;
			}
			$fin = max($fin, $termina);
			$cursor = at_pt_siguiente_habil($termina, $feriados);
		}
	}
	$hitos_plan = [];
	$hitos = [];
	foreach (($plan['hitos'] ?? []) as $h) {
		$nombre = is_array($h) ? (string) ($h['nombre'] ?? '') : '';
		$clave = at_pt_clave_nombre(is_array($h) ? (string) ($h['despues_de'] ?? '') : '');
		if ($nombre === '' || !isset($fecha_hito[$clave]) || at_pt_clave_nombre($nombre) === 'entrega estimada') {
			continue; // la validación ya avisó; aquí se descarta sin ruido
		}
		$h['fecha'] = $fecha_hito[$clave];
		$hitos_plan[] = $h;
		$hitos[] = ['nombre' => $nombre, 'fecha' => $fecha_hito[$clave]];
	}
	$hitos[] = ['nombre' => 'Entrega estimada', 'fecha' => $fin_implementacion !== '' ? $fin_implementacion : $fin];
	usort($hitos, static fn(array $x, array $y): int => strcmp($x['fecha'], $y['fecha'])); // estable: a igual fecha, en su orden
	$plan['hitos'] = $hitos_plan;
	$plan['cronograma'] = [
		'inicio'  => $plan['fecha_inicio'],
		'fin'     => $fin,
		'semanas' => at_pt_semanas($plan['fecha_inicio'], $fin),
		'barras'  => $barras,
		'hitos'   => $hitos,
	];
	return $plan;
}
````

- [ ] **Step 4: Correr y ver que pasa.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/cronograma-test.php; echo "exit=$?" && /c/wamp64/bin/php/php8.4.15/php.exe -l wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php
```

  Expected: 38 líneas `ok   …`, ninguna `FALLA`, la última línea `TODO OK`, `exit=0` y `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Además, sin regresiones: `cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/fechas-test.php | tail -1 && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/validacion-test.php | tail -1` imprime `TODO OK` por cada archivo. Si una aserción falla se corrige el código, no la prueba (la prueba solo se cambia si contradice el esqueleto o la spec, y se explica en el reporte).

- [ ] **Step 5: Commit.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && git add wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php tests/plan/cronograma-test.php && git commit -m "feat(plan): fechas en días hábiles y cronograma de la carta Gantt" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Puras — estados del plan, costo de fotos y cuerpo del render

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php` (se agrega al final, en dos ciclos)
- Test: `tests/plan/render-test.php`

**Interfaces:**
- Consumes: Task 1 — `at_pt_ymd(string $f): string`, `at_pt_fecha_larga(string $ymd): string` y, en la prueba, `at_pt_inicio_por_defecto(string $fecha_firma, array $feriados): string`; Task 2 — `at_pt_slides_foto(): array`, `at_pt_entero(mixed $v): ?int` y, en la prueba, `at_pt_validar_plan(array $plan): array`, `at_pt_aplicar_tabla(array $plan, array $tabla): array`, `at_pt_duraciones_defecto(): array`; Task 3 (en la prueba) — `at_pt_calcular_fechas(array $plan, string $inicio, array $feriados): array`.
- Produces:
  - `at_pt_transiciones(): array` y `at_pt_transicion_valida(string $de, string $a): bool` — el mapa del esqueleto; un estado desconocido no pasa a ninguno.
  - `AT_PT_USD_POR_FOTO = 0.0032` — copia de la tarifa de `at_propuesta_costo_fotos()` (`inc/proposals-flow.php:108` y `:124`); no se hace `require` de `proposals-flow.php`.
  - `at_pt_costo_fotos(array $image_briefs, bool $hay_propuesta): array` → `['fotos' => int, 'usd_lista' => float, 'usd_max' => float]` (un brief válido por lámina; con propuesta, `cover` y `cierre` no cuentan; `usd_max` = x2).
  - `at_pt_texto_whatsapp_agenda(string $codigo): string` → «Hola Tech, quiero agendar la llamada de seguimiento de mi plan de trabajo (código XXXX)».
  - `at_pt_armar_render(array $plan, array $datos, bool $final): array` — el cuerpo del esqueleto, con las claves en su orden (las mismas que lee `renderPlanHtml` y valida `validatePlanPayload` en la Task 11). `$datos` = `['codigo', 'company_name', 'client_name', 'portal_url', 'whatsapp', 'fecha_firma', 'garantia_meses']` (lo arma `at_pt_datos_render()` de la Task 5; `whatsapp` = `at_cc_whatsapp_at()`):
    - `company_name` (decisión D9): el nombre que se muestra, que la Task 5 ya arma en este orden: `company_name` de la propuesta (nombre comercial) → `nombre_proyecto` o `razon_social_cliente` del contrato → nombre del cliente. Aquí se usa tal cual; el respaldo solo actúa si llega vacío: nombre del cliente → el proyecto, porque `validatePlanPayload` del renderer lo exige no vacío (esqueleto.md:349-350; hoy `validatePayload` rechaza `company_name` vacío en `origin/main:renderer/src/schema.js:1-9`).
    - `garantia_meses` (decisión D8): los meses de garantía del contrato firmado (placeholder `garantia_meses_servicio`). Si es un entero mayor o igual que 0 (número o texto de dígitos), reemplaza `soporte.garantia_meses` del plan, **incluido el 0**: si el contrato no promete garantía, el documento tampoco. El contrato manda sobre lo que escribió la IA y sobre el panel (en la Task 9 el campo «Garantía» es de solo lectura, «Viene del contrato»). Solo si la clave falta o no es un entero válido (negativo, texto no numérico, vacío, decimal) queda el del plan (3 por defecto). `soporte.mensuales` no se toca.
    - `images` es `stdClass` vacío para que el JSON sea `{}` y no `[]` (la Task 7 debe devolverlo tal cual, sin pasarlo por `json_decode(…, true)`). `portal_url` solo si es http(s) sin espacios; si no, `''`. Proyecto vacío → nombre de la empresa → «Tu proyecto».

**Review Focus cubierto aquí:** 4 (contrato sin propuesta y cliente sin correo: el cuerpo se arma igual, `portal_url` vacío y portada y cierre cuentan como fotos nuevas; persona natural sin empresa: `company_name` no queda vacío) y 5 (transiciones: desde `error` se reintenta el borrador o se vuelve al borrador; destrabar pasa `generando`, `cambios` y `aprobando` a `error`).

**Ciclo A — estados y costo de fotos**

- [ ] **Step 1: Escribir la prueba que falla** — crear `tests/plan/render-test.php` (Write, archivo nuevo, UTF-8 sin BOM) con las transiciones y el costo de fotos (8 fotos = US$0,0256 con propuesta; 10 = US$0,032 sin propuesta):

````php
<?php
// Correr: php tests/plan/render-test.php   (sin WordPress)
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

// Estados y transiciones
ok(array_keys(at_pt_transiciones()) === ['generando', 'borrador', 'cambios', 'aprobando', 'listo', 'error', 'enviado'], 'los siete estados del plan');
ok(at_pt_transicion_valida('generando', 'borrador') && at_pt_transicion_valida('borrador', 'borrador') && at_pt_transicion_valida('borrador', 'aprobando') && at_pt_transicion_valida('aprobando', 'listo'), 'camino normal: generando -> borrador -> (guardar y recalcular) borrador -> aprobando -> listo');
ok(at_pt_transicion_valida('borrador', 'cambios') && at_pt_transicion_valida('cambios', 'borrador') && at_pt_transicion_valida('listo', 'cambios') && at_pt_transicion_valida('listo', 'borrador'), 'pedir cambios desde borrador o listo, y volver al borrador');
ok(at_pt_transicion_valida('generando', 'error') && at_pt_transicion_valida('cambios', 'error') && at_pt_transicion_valida('aprobando', 'error'), 'destrabar: generando, cambios y aprobando pasan a error');
ok(at_pt_transicion_valida('error', 'generando') && at_pt_transicion_valida('error', 'borrador'), 'Review Focus 5: desde error, «Reintentar borrador» (-> generando) o «Volver al borrador»');
ok(!at_pt_transicion_valida('generando', 'listo') && !at_pt_transicion_valida('borrador', 'listo') && !at_pt_transicion_valida('generando', 'generando'), 'no se salta la aprobación ni se genera dos veces a la vez');
ok(!at_pt_transicion_valida('borrador', 'enviado') && at_pt_transicion_valida('listo', 'enviado'), 'solo un plan listo se envía');
ok(at_pt_transiciones()['enviado'] === [] && !at_pt_transicion_valida('enviado', 'borrador') && !at_pt_transicion_valida('enviado', 'error'), 'enviado no cambia en la Etapa 1');
ok(!at_pt_transicion_valida('raro', 'borrador') && !at_pt_transicion_valida('', 'error') && !at_pt_transicion_valida('borrador', 'raro'), 'estados desconocidos: no');

// Costo de fotos nuevas (US$0,0032 por foto, x2 con reintentos)
$todas = array_map(fn($s) => ['slide' => $s, 'prompt' => "escena del rubro para {$s}"], at_pt_slides_foto());
ok(at_pt_costo_fotos($todas, true) === ['fotos' => 8, 'usd_lista' => 0.0256, 'usd_max' => 0.0512], 'con propuesta: 10 láminas, portada y cierre se reutilizan -> 8 fotos, US$0,0256 (máximo 0,0512)');
ok(at_pt_costo_fotos($todas, false) === ['fotos' => 10, 'usd_lista' => 0.032, 'usd_max' => 0.064], 'Review Focus 4: sin propuesta, portada y cierre también son nuevas -> 10 fotos, US$0,032');
$sucias = [
	['slide' => 'metodo', 'prompt' => 'a'], ['slide' => 'metodo', 'prompt' => 'b'], ['slide' => 'inventada', 'prompt' => 'c'],
	['slide' => 'gantt', 'prompt' => '  '], ['slide' => 'cover', 'prompt' => 'd'], 'no es brief', ['prompt' => 'sin lámina'],
];
ok(at_pt_costo_fotos($sucias, true) === ['fotos' => 1, 'usd_lista' => 0.0032, 'usd_max' => 0.0064], 'cuenta una por lámina válida con descripción (sin repetidas, inventadas ni vacías)');
ok(at_pt_costo_fotos($sucias, false)['fotos'] === 2 && at_pt_costo_fotos([], false) === ['fotos' => 0, 'usd_lista' => 0.0, 'usd_max' => 0.0], 'sin propuesta la portada cuenta; sin briefs, costo 0');
ok(AT_PT_USD_POR_FOTO === 0.0032, 'tarifa por foto igual a la de at_propuesta_costo_fotos (inc/proposals-flow.php:124)');

fin();
````

- [ ] **Step 2: Correr y ver que falla.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/render-test.php; echo "exit=$?"
```

  Expected: `Fatal error: Uncaught Error: Call to undefined function at_pt_transiciones()`; `exit=255`. Es el motivo correcto del fallo: la función todavía no existe.

- [ ] **Step 3: Implementar** — agregar los estados y el costo de fotos al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Edit con `old_string` = el final actual del archivo (estas líneas, que aparecen una sola vez):

````text
		'hitos'   => $hitos,
	];
	return $plan;
}
````

  y `new_string` = esas mismas líneas, una línea en blanco y el bloque:

````php
/* ---------- Estados del plan y costo de fotos (Task 4) ---------- */

/** Estados del plan y a cuáles puede pasar cada uno. «Destrabar» es generando, cambios o aprobando -> error; desde
 *  error se reintenta el borrador (-> generando) o se vuelve al borrador (-> borrador). 'enviado' es de la Etapa 2. */
function at_pt_transiciones(): array {
	return [
		'generando' => ['borrador', 'error'],
		'borrador'  => ['borrador', 'cambios', 'aprobando', 'error'],
		'cambios'   => ['borrador', 'error'],
		'aprobando' => ['listo', 'error'],
		'listo'     => ['borrador', 'cambios', 'aprobando', 'enviado'],
		'error'     => ['generando', 'borrador', 'cambios', 'aprobando'],
		'enviado'   => [],
	];
}

/** ¿Puede el plan pasar de $de a $a? Un estado desconocido no pasa a ninguno. */
function at_pt_transicion_valida(string $de, string $a): bool {
	return in_array($a, at_pt_transiciones()[$de] ?? [], true);
}

// Tarifa de lista por foto: la misma de at_propuesta_costo_fotos() (inc/proposals-flow.php:108 y :124, Soul 2,
// US$0,0032 c/u al 2026-09-20). Se copia aquí para que las pruebas puras no dependan de proposals-flow.php. Si cambia
// el modelo o la tarifa (spec §5: qwen-image o recraft se decide al implementar), cambiar los dos lados.
const AT_PT_USD_POR_FOTO = 0.0032;

/** Fotos nuevas del plan y su costo, para mostrar antes de «Aprobar» (decisión 7). Cuenta un brief válido por lámina
 *  (lámina de at_pt_slides_foto() y descripción no vacía). Si hay propuesta, 'cover' y 'cierre' no cuentan: se
 *  reutilizan sus fotos. 'usd_max' = cada foto dos veces (reintentos), como at_propuesta_costo_fotos(); el plan no
 *  tiene revisión de texto con GPT-4o en la Etapa 1. */
function at_pt_costo_fotos(array $image_briefs, bool $hay_propuesta): array {
	$slides = at_pt_slides_foto();
	$reutilizadas = $hay_propuesta ? ['cover', 'cierre'] : [];
	$nuevas = [];
	foreach ($image_briefs as $b) {
		$slide = is_array($b) && is_string($b['slide'] ?? null) ? trim($b['slide']) : '';
		$prompt = is_array($b) && is_string($b['prompt'] ?? null) ? trim($b['prompt']) : '';
		if ($prompt !== '' && in_array($slide, $slides, true) && !in_array($slide, $reutilizadas, true)) {
			$nuevas[$slide] = true;
		}
	}
	$n = count($nuevas);
	return ['fotos' => $n, 'usd_lista' => round($n * AT_PT_USD_POR_FOTO, 4), 'usd_max' => round($n * AT_PT_USD_POR_FOTO * 2, 4)];
}
````

- [ ] **Step 4: Correr y ver que pasa.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/render-test.php; echo "exit=$?" && /c/wamp64/bin/php/php8.4.15/php.exe -l wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php
```

  Expected: 14 líneas `ok   …`, ninguna `FALLA`, la última línea `TODO OK`, `exit=0` y `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Si una aserción falla se corrige el código, no la prueba (la prueba solo se cambia si contradice el esqueleto o la spec, y se explica en el reporte).

- [ ] **Step 5: Commit.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && git add wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php tests/plan/render-test.php && git commit -m "feat(plan): estados del plan y costo de fotos nuevas" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

**Ciclo B — cuerpo del render**

- [ ] **Step 6: Escribir las pruebas que fallan** — en `tests/plan/render-test.php`, reemplazar la última línea `fin();` (Edit: `old_string` = `fin();`, que aparece una sola vez) por este bloque, que termina en `fin();` (el cuerpo completo de un plan real calculado (vista previa y final), el enlace de WhatsApp exacto, el caso sin propuesta ni portal, la persona natural sin empresa y la garantía del contrato de la decisión D8):

````php
// Cuerpo del render: plan completo (validar -> tabla -> fechas), firma martes 29-sep-2026.
$fer = ['2026-10-12', '2026-10-31', '2026-11-01', '2026-12-08', '2026-12-25', '2027-01-01'];
$v = at_pt_validar_plan([
	'proyecto'     => '[PRUEBA] Sitio web de Cliente Prueba',
	'fecha_firma'  => '2026-09-29',
	'fases'        => [
		['clave' => 'diseno_desarrollo', 'descripcion' => 'Diseñamos y construimos tu sitio.', 'bloques' => [
			['nombre' => 'Diseño', 'entrega' => true, 'entregable' => 'Maqueta aprobada', 'actividades' => [
				['nombre' => 'Propuesta de diseño', 'responsable' => 'at', 'dias_habiles' => 3, 'servicio' => 'sitio_una_pagina', 'etapa' => 'diseno'],
			]],
			['nombre' => 'Desarrollo', 'actividades' => [
				['nombre' => 'Maquetación', 'responsable' => 'at', 'dias_habiles' => 5, 'servicio' => 'sitio_una_pagina', 'etapa' => 'desarrollo'],
			]],
		]],
		['clave' => 'implementacion', 'bloques' => [
			['nombre' => 'Puesta en marcha', 'actividades' => [['nombre' => 'Publicación del sitio', 'responsable' => 'at', 'dias_habiles' => 1, 'servicio' => 'sitio_una_pagina', 'etapa' => 'implementacion']]],
		]],
	],
	'hitos'        => [['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño']],
	'reuniones'    => [['nombre' => 'Reunión de inicio', 'detalle' => 'Por videollamada']],
	'image_briefs' => array_map(fn($s) => ['slide' => $s, 'prompt' => "equipo de una pyme trabajando, lámina {$s}"], at_pt_slides_foto()),
]);
$plan = at_pt_calcular_fechas(at_pt_aplicar_tabla($v['plan'], at_pt_duraciones_defecto()), at_pt_inicio_por_defecto('2026-09-29', $fer), $fer);
$datos = [
	'codigo'       => 'Ab3dE5fG7hJ9',
	'company_name' => '[PRUEBA] Empresa de prueba',
	'client_name'  => 'Cliente Prueba',
	'portal_url'   => 'https://automatizatech.cl/?crm_view=timeline&cid=7&token=abc123',
	'whatsapp'     => '56927002984',
	'fecha_firma'  => '2026-09-29 18:40:12',
];
$r = at_pt_armar_render($plan, $datos, false);
ok($v['ok'] && array_keys($r) === ['document_type', 'unique_id', 'draft', 'company_name', 'client_name', 'proyecto', 'fecha_firma_larga', 'fecha_inicio', 'fecha_fin', 'semanas', 'metodo', 'fases', 'cronograma', 'necesitamos_de_ti', 'reuniones', 'soporte', 'portal_url', 'agenda', 'image_briefs', 'images'], 'las claves del cuerpo del render, en el orden del esqueleto');
ok($r['document_type'] === 'plan' && $r['unique_id'] === 'Ab3dE5fG7hJ9' && preg_match('/^[A-Za-z0-9_-]{6,64}$/', $r['unique_id']) === 1, 'tipo plan y unique_id = código (calza con la regla del renderer)');
ok($r['draft'] === true && $r['image_briefs'] === [], 'vista previa: draft true y sin fotos');
ok(strpos(json_encode($r), '"images":{}') !== false && strpos(json_encode($r), '"web_url":""') !== false, 'images sale como objeto JSON vacío {} y web_url vacío');
ok($r['company_name'] === '[PRUEBA] Empresa de prueba' && $r['client_name'] === 'Cliente Prueba' && $r['proyecto'] === '[PRUEBA] Sitio web de Cliente Prueba', 'empresa, cliente y proyecto');
ok($r['fecha_firma_larga'] === '29 de septiembre de 2026', 'fecha de firma larga (la firma trae hora)');
ok($r['fecha_inicio'] === '2026-10-05' && $r['fecha_fin'] === $plan['cronograma']['fin'] && $r['semanas'] === $plan['cronograma']['semanas'] && $r['semanas'] > 0, 'inicio lunes 5-oct, fin y semanas del cronograma');
ok($r['metodo'] === ['hechas' => ['diagnostico', 'priorizacion'], 'actual' => 'propuesta', 'proximas' => ['diseno_desarrollo', 'implementacion', 'soporte']], 'Método AT: diagnóstico y priorización hechas, estás en la propuesta');
ok($r['fases'] === $plan['fases'] && $r['cronograma'] === $plan['cronograma'] && $r['necesitamos_de_ti'] === $plan['necesitamos_de_ti'] && $r['reuniones'] === $plan['reuniones'] && $r['soporte'] === $plan['soporte'], 'fases, cronograma, insumos, reuniones y soporte pasan intactos');
ok($r['portal_url'] === 'https://automatizatech.cl/?crm_view=timeline&cid=7&token=abc123', 'enlace al portal del cliente');
ok($r['agenda']['whatsapp_url'] === 'https://wa.me/56927002984?text=Hola%20Tech%2C%20quiero%20agendar%20la%20llamada%20de%20seguimiento%20de%20mi%20plan%20de%20trabajo%20%28c%C3%B3digo%20Ab3dE5fG7hJ9%29', 'enlace de WhatsApp con Tech con el mensaje y el código del plan');
ok($r['agenda']['web_url'] === '', 'agenda web vacía en la Etapa 1');
$f = at_pt_armar_render($plan, $datos, true);
ok($f['draft'] === false && $f['image_briefs'] === $plan['image_briefs'] && count($f['image_briefs']) === 10, 'versión final: draft false y los 10 briefs del plan');
ok(is_array(json_decode(json_encode($f), true)) && json_decode(json_encode($f))->images == new stdClass(), 'el cuerpo se codifica y decodifica como JSON');

// Review Focus 4: contrato sin propuesta y cliente sin correo (sin portal).
$sin = at_pt_armar_render($plan, array_merge($datos, ['portal_url' => '']), true);
ok($sin['portal_url'] === '' && $sin['fases'] !== [] && $sin['cronograma']['barras'] !== [], 'sin portal: portal_url vacío y el documento se arma igual');
ok(at_pt_costo_fotos($sin['image_briefs'], false)['fotos'] === 10 && in_array('cover', array_column($sin['image_briefs'], 'slide'), true) && in_array('cierre', array_column($sin['image_briefs'], 'slide'), true), 'sin propuesta: portada y cierre van con foto nueva (10 fotos)');
ok(at_pt_armar_render($plan, array_merge($datos, ['portal_url' => 'javascript:alert(1)']), false)['portal_url'] === '' && at_pt_armar_render($plan, array_merge($datos, ['portal_url' => 'https://x.cl/a b']), false)['portal_url'] === '', 'un portal que no es http(s) o trae espacios se descarta');
ok(at_pt_armar_render($plan, array_merge($datos, ['whatsapp' => '+56 9 2700 2984']), false)['agenda']['whatsapp_url'] === $r['agenda']['whatsapp_url'] && at_pt_armar_render($plan, array_merge($datos, ['whatsapp' => '9 2700 2984']), false)['agenda']['whatsapp_url'] === $r['agenda']['whatsapp_url'], 'el número de WhatsApp se normaliza (con +56, espacios o 9 dígitos)');
ok(at_pt_armar_render($plan, array_merge($datos, ['whatsapp' => '']), false)['agenda']['whatsapp_url'] === '', 'sin número de WhatsApp no hay enlace');
$sin_nombre = $plan;
$sin_nombre['proyecto'] = '';
ok(at_pt_armar_render($sin_nombre, $datos, false)['proyecto'] === '[PRUEBA] Empresa de prueba' && at_pt_armar_render($sin_nombre, array_merge($datos, ['company_name' => '']), false)['proyecto'] === 'Tu proyecto', 'sin nombre de proyecto: el de la empresa, o «Tu proyecto»');
ok(at_pt_armar_render($plan, array_merge($datos, ['company_name' => '']), false)['company_name'] === 'Cliente Prueba' && at_pt_armar_render($sin_nombre, array_merge($datos, ['company_name' => '', 'client_name' => '']), false)['company_name'] === 'Tu proyecto', 'persona natural sin empresa: company_name = el cliente o el proyecto (el renderer lo exige no vacío)');
// Decisión D8: la garantía del contrato firmado manda sobre la que trae el plan.
$con_garantia = at_pt_armar_render($plan, array_merge($datos, ['garantia_meses' => 6]), true);
ok($plan['soporte']['garantia_meses'] === 3 && $con_garantia['soporte']['garantia_meses'] === 6 && $con_garantia['soporte']['mensuales'] === $plan['soporte']['mensuales'], 'garantía del contrato (6 meses) en vez de la del plan (3); los mensuales no cambian');
ok(at_pt_armar_render($plan, array_merge($datos, ['garantia_meses' => '12']), false)['soporte']['garantia_meses'] === 12, 'la garantía del contrato también llega como texto de dígitos (placeholder del contrato)');
$cero = at_pt_armar_render($plan, array_merge($datos, ['garantia_meses' => 0]), true)['soporte'];
ok($plan['soporte']['garantia_meses'] === 3 && $cero === ['garantia_meses' => 0, 'mensuales' => $plan['soporte']['mensuales']] && at_pt_armar_render($plan, array_merge($datos, ['garantia_meses' => '0']), false)['soporte']['garantia_meses'] === 0, 'contrato sin garantía (0, número o texto): el documento dice 0 aunque el plan diga 3');
$sin_garantia = array_map(fn($g) => at_pt_armar_render($plan, array_merge($datos, ['garantia_meses' => $g]), false)['soporte'], [-2, 'tres', '', null, 2.5]);
ok($sin_garantia === array_fill(0, 5, $plan['soporte']), 'garantía del contrato negativa, no entera o vacía: queda la del plan');
ok(!array_key_exists('garantia_meses', $datos) && $r['soporte'] === $plan['soporte'], 'sin la clave garantia_meses en los datos: queda la del plan');
ok(at_pt_armar_render([], ['garantia_meses' => 6], false)['soporte'] === ['garantia_meses' => 6, 'mensuales' => []], 'plan sin soporte: la garantía del contrato igual llega al documento');
ok(at_pt_armar_render($plan, array_merge($datos, ['fecha_firma' => '']), false)['fecha_firma_larga'] === '29 de septiembre de 2026', 'sin fecha de firma en los datos: la del plan');
ok(at_pt_armar_render([], ['codigo' => 'Ab3dE5fG7hJ9'], false)['fases'] === [] && at_pt_armar_render([], [], false)['semanas'] === 0, 'un plan vacío no rompe el armado (el renderer lo rechaza por su esquema)');

fin();
````

- [ ] **Step 7: Correr y ver que falla.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/render-test.php; echo "exit=$?"
```

  Expected: primero 14 líneas `ok   …` de las pruebas que ya pasaban y después `Fatal error: Uncaught Error: Call to undefined function at_pt_armar_render()`; `exit=255`. Es el motivo correcto del fallo: la función todavía no existe.

- [ ] **Step 8: Implementar** — agregar el armado del cuerpo del render al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Edit con `old_string` = el final actual del archivo (estas líneas, que aparecen una sola vez):

````text
	return ['fotos' => $n, 'usd_lista' => round($n * AT_PT_USD_POR_FOTO, 4), 'usd_max' => round($n * AT_PT_USD_POR_FOTO * 2, 4)];
}
````

  y `new_string` = esas mismas líneas, una línea en blanco y el bloque:

````php
/* ---------- Cuerpo que WordPress manda al renderer (Task 4) ---------- */

/** Mensaje del enlace «Por WhatsApp con Tech» del cierre del documento (spec §7). */
function at_pt_texto_whatsapp_agenda(string $codigo): string {
	return 'Hola Tech, quiero agendar la llamada de seguimiento de mi plan de trabajo (código ' . $codigo . ')';
}

/** Cuerpo de POST /render del renderer con document_type 'plan' (forma en el esqueleto). $datos = ['codigo',
 *  'company_name', 'client_name', 'portal_url', 'whatsapp', 'fecha_firma', 'garantia_meses'] (at_pt_datos_render(),
 *  Task 5). company_name ya viene en el orden de la decisión D9 (company_name de la propuesta -> nombre_proyecto o
 *  razon_social_cliente del contrato -> nombre del cliente); si aun así llega vacío, se usa el nombre del cliente o
 *  el del proyecto, porque el renderer lo exige no vacío. garantia_meses es la del contrato firmado (decisión D8): si
 *  es un entero >= 0 (número o texto de dígitos) reemplaza soporte.garantia_meses del plan, incluido el 0 (el
 *  contrato no promete garantía, el documento tampoco); si falta o no es un entero >= 0, queda la del plan. El
 *  contrato manda. $final false = vista previa
 *  (draft true, sin fotos: image_briefs vacío); $final true = image_briefs del plan. 'images' sale como objeto JSON
 *  vacío ({}) para que n8n le agregue las fotos reutilizadas por lámina (un [] de PHP llegaría como arreglo y
 *  JSON.stringify perdería esas claves). portal_url solo si es http(s); sin correo no hay portal y la lámina «Sigue tu
 *  proyecto» va sin enlace (Review Focus 4). agenda.web_url queda '' en la Etapa 1. */
function at_pt_armar_render(array $plan, array $datos, bool $final): array {
	$codigo = trim((string) ($datos['codigo'] ?? ''));
	$empresa = trim((string) ($datos['company_name'] ?? ''));
	$cliente = trim((string) ($datos['client_name'] ?? ''));
	$cronograma = is_array($plan['cronograma'] ?? null) ? $plan['cronograma'] : [];
	$soporte = is_array($plan['soporte'] ?? null) ? $plan['soporte'] : ['garantia_meses' => 3, 'mensuales' => []];
	$garantia = at_pt_entero($datos['garantia_meses'] ?? null);
	if ($garantia !== null && $garantia >= 0) {
		$soporte['garantia_meses'] = $garantia; // decisión D8: el contrato manda, también con 0 (sin garantía)
	}
	$proyecto = trim((string) ($plan['proyecto'] ?? ''));
	if ($proyecto === '') {
		$proyecto = $empresa !== '' ? $empresa : 'Tu proyecto';
	}
	if ($empresa === '') {
		// Persona natural sin empresa: el renderer exige company_name no vacío (validatePlanPayload).
		$empresa = $cliente !== '' ? $cliente : $proyecto;
	}
	$fecha_firma = at_pt_ymd((string) ($datos['fecha_firma'] ?? ''));
	if ($fecha_firma === '') {
		$fecha_firma = at_pt_ymd((string) ($plan['fecha_firma'] ?? ''));
	}
	$telefono = (string) preg_replace('/\D/', '', (string) ($datos['whatsapp'] ?? ''));
	if (strlen($telefono) === 9 && $telefono[0] === '9') {
		$telefono = '56' . $telefono; // mismo criterio que at_cc_telefono_normalizado()
	}
	$portal = trim((string) ($datos['portal_url'] ?? ''));
	if (!preg_match('#^https?://\S+$#i', $portal)) {
		$portal = '';
	}
	return [
		'document_type'     => 'plan',
		'unique_id'         => $codigo,
		'draft'             => !$final,
		'company_name'      => $empresa,
		'client_name'       => $cliente,
		'proyecto'          => $proyecto,
		'fecha_firma_larga' => at_pt_fecha_larga($fecha_firma),
		'fecha_inicio'      => (string) ($cronograma['inicio'] ?? ($plan['fecha_inicio'] ?? '')),
		'fecha_fin'         => (string) ($cronograma['fin'] ?? ''),
		'semanas'           => (int) ($cronograma['semanas'] ?? 0),
		'metodo'            => ['hechas' => ['diagnostico', 'priorizacion'], 'actual' => 'propuesta', 'proximas' => ['diseno_desarrollo', 'implementacion', 'soporte']],
		'fases'             => array_values($plan['fases'] ?? []),
		'cronograma'        => $cronograma,
		'necesitamos_de_ti' => array_values($plan['necesitamos_de_ti'] ?? []),
		'reuniones'         => array_values($plan['reuniones'] ?? []),
		'soporte'           => $soporte,
		'portal_url'        => $portal,
		'agenda'            => [
			'whatsapp_url' => $telefono === '' ? '' : 'https://wa.me/' . $telefono . '?text=' . rawurlencode(at_pt_texto_whatsapp_agenda($codigo)),
			'web_url'      => '',
		],
		'image_briefs'      => $final ? array_values($plan['image_briefs'] ?? []) : [],
		'images'            => new stdClass(),
	];
}
````

- [ ] **Step 9: Correr y ver que pasa.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/render-test.php; echo "exit=$?" && /c/wamp64/bin/php/php8.4.15/php.exe -l wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php
```

  Expected: 43 líneas `ok   …` en total (14 de antes + 29 nuevas), ninguna `FALLA`, la última línea `TODO OK`, `exit=0` y `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php`. Además, sin regresiones: `cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/fechas-test.php | tail -1 && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/validacion-test.php | tail -1 && /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/cronograma-test.php | tail -1` imprime `TODO OK` por cada archivo. Si una aserción falla se corrige el código, no la prueba (la prueba solo se cambia si contradice el esqueleto o la spec, y se explica en el reporte).

- [ ] **Step 10: Commit.**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && git add wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php tests/plan/render-test.php && git commit -m "feat(plan): cuerpo del render del plan de trabajo" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 11: Verificación del grupo.** Las cuatro pruebas puras juntas, `tests/plan/{fechas,validacion,cronograma,render}-test.php` (decisión D3: no existe un `tests/plan/puras-test.php`):

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo && for f in fechas validacion cronograma render; do /c/wamp64/bin/php/php8.4.15/php.exe tests/plan/$f-test.php | tail -1; done
```

  Expected: cuatro líneas `TODO OK` (33 + 137 + 38 + 43 = 251 aserciones). `puras.php` no toca WordPress: se prueba sin `wp-load.php`.

---


### Task 5: Datos del plan (tabla, migración, lecturas, crear, guardar, estado, feriados, tabla de tiempos, contratos sin plan, datos del render y contexto)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` (aquí carga `puras.php` y `datos.php`; la Task 6 le suma `disparador.php`, la Task 7 `rest.php` y las Tasks 8 y 9 `ajustes.php` y `panel.php`)
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php`
- Modify: `wp-content/themes/automatiza-tech/inc/admin-proposals.php` — una línea nueva justo después de la 91 (`require_once __DIR__ . '/cierre-cliente/cargar.php';`)
- Test: `tests/plan/wp-bootstrap.php` y `tests/plan/datos-prueba.php` (arnés y datos de prueba de todas las pruebas WordPress del plan), `tests/plan/datos-wp-test.php`, `tests/plan/datos-guardar-wp-test.php`, `tests/plan/datos-ajustes-wp-test.php`, `tests/plan/datos-contexto-wp-test.php`
- Fuera del repo: `<SCR>/plan-trabajo/carga-plan.py` (edita `admin-proposals.php` respetando sus fines de línea CRLF)

La carga del módulo (`cargar.php` + la línea en `admin-proposals.php`) va en esta tarea y no en la 6: las pruebas WordPress
necesitan el módulo cargado (`wp-bootstrap.php` sale con código 2 si no existe `at_pt_crear_plan`). La Task 6 solo le suma
`disparador.php` a `cargar.php`.

**Interfaces:**
- Consumes (Tasks 1-4, `inc/plan-trabajo/puras.php`): `at_pt_transiciones(): array`,
  `at_pt_transicion_valida(string $de, string $a): bool`, `at_pt_feriados_de_texto(string $raw): array`,
  `at_pt_duraciones_defecto(): array`, `at_pt_normalizar_duraciones(array $tabla): array`, `at_pt_slides_foto(): array`.
  Del código existente: `at_crm_url_portal(int $cliente_id): string` (`wp-content/mu-plugins/crm-ai-completo.php:8562`,
  `''` si el cliente del CRM no tiene correo), `at_cc_whatsapp_at(): string` (`inc/cierre-cliente/ajustes.php:76`),
  `at_cc_json_de_payload(string $s): ?array` (`inc/cierre-cliente/puras.php:229`); tablas `automatiza_contracts`
  (`contracts/setup-contracts-db.php:18-65`), `automatiza_tech_clients.crm_cliente_id`, `crm_clientes`, `automatiza_propuestas`.
- Produces (`inc/plan-trabajo/datos.php`):
  - `at_pt_tabla(): string`; `at_pt_migrar_esquema(): void` (hook `admin_init` y llamada perezosa en cada lectura; opción
    `at_plan_schema` = `'1'`; si falla, transitorio `at_pt_migrar_intento` de 5 minutos, como el cierre)
  - `at_pt_plan(int $id): ?object`, `at_pt_plan_por_codigo(string $codigo): ?object` (código exacto, distingue mayúsculas),
    `at_pt_plan_de_contrato(int $contrato_id): ?object`, `at_pt_planes_de_crm(int $crm_id): array` (más nuevo primero;
    manda el enlace actual de la ficha operativa, así un plan creado con la ficha sin enlazar aparece al enlazarla)
  - `at_pt_payload(object $fila): array`
  - `at_pt_crear_plan(int $contrato_id): int|WP_Error` — idempotente; errores `at_pt_sin_contrato`, `at_pt_no_servicios`,
    `at_pt_sin_firma`, `at_pt_insert`; nunca genera un código igual al `unique_link_id` de una propuesta (el renderer
    publica los dos en `/p/<código>/`)
  - `at_pt_guardar(int $id, array $campos): bool` — false sin tocar nada si el plan no existe, si no viene ninguna
    columna permitida o si un valor no sirve (estado desconocido, fecha inexistente, `payload` que no es arreglo);
    `payload` `null` o `[]` lo vacía; nota ≤ 1000 y comentarios ≤ 4000 caracteres; `view_url`/`pdf_url` solo http(s)
  - `at_pt_cambiar_estado(int $id, string $a, string $nota = ''): bool` — la nota describe el estado nuevo: sin nota
    queda vacía; compara y cambia (si otro proceso movió el estado, no se pisa)
  - `at_pt_feriados(): array`, `at_pt_duraciones(): array`, `at_pt_contratos_sin_plan(int $crm_id): array` (filas completas
    de `automatiza_contracts`, más nuevo primero)
  - `at_pt_datos_render(object $fila): array` → `['codigo','company_name','client_name','portal_url','whatsapp','fecha_firma',
    'garantia_meses']` (`whatsapp` solo dígitos; `portal_url` `''` sin correo; `company_name` según D9: el `company_name`
    de la propuesta si existe —nombre comercial—, si no `nombre_proyecto` o `razon_social_cliente` del contrato, si no el
    nombre del cliente; nunca vacío; `garantia_meses` según D8: el marcador `garantia_meses_servicio` del contrato, entero
    de 0 a 24, 3 si no está o no se entiende. `at_pt_armar_render()` (Grupo A) debe preferir este valor sobre el del plan)
  - `at_pt_contexto(object $fila): array` → exactamente el JSON de `GET /plan/{id}/contexto` (Task 7): las claves del
    esqueleto más `crm_cliente_id` (el botón de los correos a Luis lo necesita) y `rubro` (de `crm_clientes.rubro`, `''` si
    no hay; para las fotos por rubro) al final, y `contrato.garantia_meses` (D7, mismo valor que en `at_pt_datos_render`)
  - Internas del módulo: `at_pt_db_contrato(int $id): ?object`, `at_pt_db_estados(): array`,
    `at_pt_db_fecha_valida(string $f): bool`, `at_pt_db_url($u): string`, `at_pt_db_garantia(array $ph): int`,
    `at_pt_db_partes(object $fila): array` (→ `['contrato','ph','tech','propuesta','crm_id','empresa','cliente','proyecto',
    'fecha_firma','garantia_meses']`; la usan `rest.php`)
  - Arnés (lo usan las Tasks 6, 7, 9 y 10): `tests/plan/wp-bootstrap.php` (`ok()`, `fin()`, `exigir(string ...$funciones)`;
    fija `AT_N8N_PLAN_*` a `http://127.0.0.1:9/…` antes de cargar WordPress) y `tests/plan/datos-prueba.php` (`pt_marca()`,
    `pt_cliente()`, `pt_ficha()`, `pt_propuesta()`, `pt_contrato()`, `pt_contrato_fila()`, `pt_archivo()`,
    `pt_plan_inexistente()`, `pt_contrato_inexistente()`, `pt_llamadas()`; anota y responde las llamadas a los flujos del
    plan, bloquea el resto de HTTP, captura los correos y borra todo lo `[PRUEBA]` al terminar, incluso si la prueba se
    cae). Las ayudas REST y de planes (`pt_pedir()`, `pt_plan_ia()`, `pt_actividades()`, `pt_actividad()`,
    `pt_editar_actividad()`) las agrega la Task 7 (Steps 1 y 7), junto con las pruebas que las usan.

Review Focus que cubre esta tarea: 4 (contrato sin propuesta y cliente sin correo: el plan se crea, `portal_url` vacío,
contexto sin propuesta) y 5 (un contrato, un plan; soporte o sin firma no tienen plan).

Variables de todos los bloques de comandos de las Tasks 5 a 7 (el shell no las guarda entre llamadas: cada bloque las repite):
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
```

- [ ] **Step 1: Arnés de las pruebas WordPress del plan**

Create `tests/plan/wp-bootstrap.php`:
```php
<?php
// Carga el WordPress local de prueba del plan de trabajo (Task 0: sitio wp-local-plan).
// Uso: AT_WP_LOAD=<ruta a wp-load.php> php tests/plan/<prueba>-wp-test.php
$at_pt_wp_load = getenv('AT_WP_LOAD');
if (!$at_pt_wp_load || !is_file($at_pt_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba (<scratchpad>/wp-local-plan/wp-load.php, Task 0).\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8093';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
// Defensa: en las pruebas los flujos del plan nunca apuntan al n8n de PROD (router.php solo cubre el
// servidor web, no la línea de comandos): un puerto local cerrado. Además datos-prueba.php intercepta todo.
foreach (['AT_N8N_PLAN_BORRADOR' => 'plan-v1-borrador', 'AT_N8N_PLAN_CAMBIOS' => 'plan-v1-cambios', 'AT_N8N_PLAN_RENDER' => 'plan-v1-render'] as $at_pt_c => $at_pt_p) {
	if (!defined($at_pt_c)) {
		define($at_pt_c, 'http://127.0.0.1:9/' . $at_pt_p);
	}
}
define('WP_USE_THEMES', false);
require $at_pt_wp_load;
if (!function_exists('at_pt_crear_plan')) {
	fwrite(STDERR, "El módulo plan-trabajo no está cargado en ese sitio (falta inc/plan-trabajo/cargar.php o su línea en inc/admin-proposals.php).\n");
	exit(2);
}
$GLOBALS['fallas'] = 0;
function ok($cond, $msg) { if ($cond) { echo "ok   $msg\n"; } else { $GLOBALS['fallas']++; echo "FALLA $msg\n"; } }
function fin() { echo $GLOBALS['fallas'] ? "\n{$GLOBALS['fallas']} FALLAS\n" : "\nTODO OK\n"; exit($GLOBALS['fallas'] ? 1 : 0); }
/** Corta la prueba con una falla legible si falta una función (paso rojo del ciclo TDD). */
function exigir(string ...$funciones): void {
	foreach ($funciones as $f) {
		if (!function_exists($f)) {
			echo "FALLA falta la función {$f}()\n\n1 FALLAS\n";
			exit(1);
		}
	}
}
```

Create `tests/plan/datos-prueba.php`:
```php
<?php
// Datos y ayudas de las pruebas WordPress del plan (base LOCAL compartida). Todo lo que se crea va
// marcado [PRUEBA] y se borra al terminar (también si la prueba se cae: register_shutdown_function).
// El repositorio es público: nada de nombres, correos ni teléfonos reales («Cliente Prueba», @example.com).
// Ninguna llamada HTTP ni correo sale del equipo: las llamadas a los flujos del plan se anotan en
// $GLOBALS['pt_http'] y responden $GLOBALS['pt_http_respuesta'] (código HTTP o WP_Error); lo demás se bloquea.

$GLOBALS['pt_creados'] = ['crm' => [], 'tech' => [], 'propuesta' => [], 'contrato' => [], 'archivo' => []];
$GLOBALS['pt_http'] = [];
$GLOBALS['pt_http_respuesta'] = 200;
$GLOBALS['pt_correos'] = [];

add_filter('pre_wp_mail', function ($nulo, $atts) {
	$GLOBALS['pt_correos'][] = $atts;
	return true;
}, 10, 2);

add_filter('pre_http_request', function ($pre, $args, $url) {
	$flujos = [];
	foreach (['AT_N8N_PLAN_BORRADOR', 'AT_N8N_PLAN_CAMBIOS', 'AT_N8N_PLAN_RENDER'] as $c) {
		if (defined($c)) {
			$flujos[] = constant($c);
		}
	}
	if (!in_array((string) $url, $flujos, true)) {
		return new WP_Error('bloqueado_en_prueba', 'Llamada externa bloqueada en la prueba del plan');
	}
	$GLOBALS['pt_http'][] = ['url' => (string) $url, 'args' => $args, 'cuerpo' => json_decode((string) ($args['body'] ?? ''), true)];
	$r = $GLOBALS['pt_http_respuesta'];
	if ($r instanceof WP_Error) {
		return $r;
	}
	return ['headers' => [], 'body' => '{"ok":true}', 'response' => ['code' => (int) $r, 'message' => ''], 'cookies' => [], 'filename' => null];
}, 10, 3);

/** Llamadas anotadas a un flujo: 'borrador', 'cambios' o 'render'. */
function pt_llamadas(string $cual): array {
	$url = ['borrador' => AT_N8N_PLAN_BORRADOR, 'cambios' => AT_N8N_PLAN_CAMBIOS, 'render' => AT_N8N_PLAN_RENDER][$cual];
	return array_values(array_filter($GLOBALS['pt_http'], function ($l) use ($url) { return $l['url'] === $url; }));
}

function pt_marca(): string {
	return 'prueba-plan-' . strtolower(wp_generate_password(6, false, false));
}

/** Cliente del CRM y su ficha operativa. Sin correo: el CRM queda sin correo (sin portal). $enlazar=false: ficha sin crm_cliente_id. */
function pt_cliente(string $marca, bool $con_correo = true, bool $enlazar = true): array {
	global $wpdb;
	$correo = $con_correo ? $marca . '@example.com' : '';
	$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => '[PRUEBA] Cliente ' . $marca, 'email' => $correo, 'empresa' => '[PRUEBA] Empresa ' . $marca, 'tipo' => 'cliente', 'estado' => 'contratado', 'origen' => 'prueba_plan']);
	$crm = (int) $wpdb->insert_id;
	if (!$crm) {
		fwrite(STDERR, "No se pudo crear el cliente de prueba en el CRM: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['pt_creados']['crm'][] = $crm;
	$tech = pt_ficha($enlazar ? $crm : 0, $marca, $con_correo ? $correo : 'sin-correo-' . $marca . '@example.com');
	return ['crm' => $crm, 'tech' => $tech];
}

/** Ficha operativa (automatiza_tech_clients) enlazada a $crm (0 = sin enlazar). */
function pt_ficha(int $crm, string $marca, string $correo = ''): int {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_tech_clients', [
		'name'           => 'Cliente Prueba',
		'email'          => $correo !== '' ? $correo : 'ficha-' . $marca . '@example.com',
		'company'        => '[PRUEBA] Empresa ' . $marca,
		'crm_cliente_id' => $crm > 0 ? $crm : null,
	]);
	$tech = (int) $wpdb->insert_id;
	if (!$tech) {
		fwrite(STDERR, "No se pudo crear la ficha operativa de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['pt_creados']['tech'][] = $tech;
	return $tech;
}

/** Propuesta v3 aceptada. Su unique_link_id es substr(md5($marca), 0, 12). */
function pt_propuesta(string $marca, array $o = []): int {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email'       => $marca . '@example.com',
		'unique_link_id'     => substr(md5($marca), 0, 12),
		'client_name'        => 'Cliente Prueba',
		'company_name'       => '[PRUEBA] Empresa ' . $marca,
		'phone'              => '',
		'status'             => 'aceptada',
		'flujo'              => 'v3',
		'gamma_prompt_text'  => wp_json_encode(['solution_text' => 'Sitio de una página con formulario de contacto.', 'how_it_works' => [['step_title' => 'Diseño', 'step_text' => 'Maqueta de la portada'], 'Publicación en tu dominio']], JSON_UNESCAPED_UNICODE),
		'transcript_text'    => $o['transcript'] ?? 'Reunión de prueba: quieren un sitio de una página.',
		'system_prompt_text' => '',
		'created_at'         => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	if (!$id) {
		fwrite(STDERR, "No se pudo crear la propuesta de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['pt_creados']['propuesta'][] = $id;
	return $id;
}

/**
 * Contrato directo en la tabla (sin PDF ni correos). Por defecto: servicios, firmado el viernes
 * 2026-10-09 a las 15:00. $o: type, status, signed_at, ph (marcadores, reemplaza los de defecto), ph_mas (se suman a
 * los de defecto).
 */
function pt_contrato(int $tech, ?int $propuesta, array $o = []): int {
	global $wpdb;
	$tipo = $o['type'] ?? 'servicios';
	$estado = $o['status'] ?? 'signed';
	$ph = ($o['ph_mas'] ?? []) + ($o['ph'] ?? [
		'nombre_proyecto'              => '[PRUEBA] Sitio de una página',
		'razon_social_cliente'         => '[PRUEBA] Razón Social SpA',
		'representante_cliente_nombre' => 'Cliente Prueba',
		'email_cliente'                => 'prueba-plan-contrato@example.com',
		'servicios_contratados'        => '- **Sitio de una página**: $1',
		'alcance'                      => 'Sitio de una página con formulario.',
		'entregables'                  => "- Sitio publicado\n- Formulario de contacto",
		'plazo'                        => 'Se define con EL CLIENTE en la reunión de inicio y queda por escrito.',
		'fases_siguientes'             => 'La propuesta no tiene fases siguientes.',
	]);
	$wpdb->insert($wpdb->prefix . 'automatiza_contracts', [
		'client_id'       => $tech,
		'proposal_id'     => $propuesta,
		'contract_number' => strtoupper('PRUEBA-PLAN-' . substr(md5(uniqid('', true)), 0, 10)),
		'type'            => $tipo,
		'template_id'     => $tipo === 'servicios' ? 'servicios_v1' : 'soporte_v2',
		'placeholders'    => wp_json_encode($ph, JSON_UNESCAPED_UNICODE),
		'status'          => $estado,
		'sign_token'      => bin2hex(random_bytes(32)),
		'at_review_token' => bin2hex(random_bytes(32)),
		'signed_at'       => $estado === 'signed' ? ($o['signed_at'] ?? '2026-10-09 15:00:00') : null,
		'expires_at'      => date('Y-m-d H:i:s', strtotime('+10 days')),
		'created_at'      => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	if (!$id) {
		fwrite(STDERR, "No se pudo crear el contrato de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['pt_creados']['contrato'][] = $id;
	return $id;
}

function pt_contrato_fila(int $id): ?object {
	global $wpdb;
	return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_contracts WHERE id = %d", $id)) ?: null;
}

/** Archivo que la prueba generó (PDF firmado, imagen de firma): se borra al terminar. */
function pt_archivo(string $ruta): void {
	$GLOBALS['pt_creados']['archivo'][] = $ruta;
}

/** Un id de plan que no existe. */
function pt_plan_inexistente(): int {
	global $wpdb;
	return (int) $wpdb->get_var('SELECT COALESCE(MAX(id), 0) + 1000 FROM ' . at_pt_tabla());
}

/** Un id de contrato que no existe. */
function pt_contrato_inexistente(): int {
	global $wpdb;
	return (int) $wpdb->get_var("SELECT COALESCE(MAX(id), 0) + 1000 FROM {$wpdb->prefix}automatiza_contracts");
}

function pt_limpiar(): void {
	global $wpdb;
	$c = $GLOBALS['pt_creados'];
	$en = function (array $ids): string { return implode(',', array_map('intval', $ids ?: [0])); };
	if (function_exists('at_pt_tabla') && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like(at_pt_tabla()))) === at_pt_tabla()) {
		$wpdb->query('DELETE FROM ' . at_pt_tabla() . ' WHERE contrato_id IN (' . $en($c['contrato']) . ')');
		// Restos de corridas anteriores que se cayeron: planes cuyo contrato ya no existe (solo en esta base local).
		$wpdb->query('DELETE p FROM ' . at_pt_tabla() . " p LEFT JOIN {$wpdb->prefix}automatiza_contracts k ON k.id = p.contrato_id WHERE k.id IS NULL");
	}
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_contracts WHERE id IN (" . $en($c['contrato']) . ')');
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN (" . $en($c['propuesta']) . ')');
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE id IN (" . $en($c['tech']) . ')');
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id IN (" . $en($c['crm']) . ')');
	foreach ($c['archivo'] as $f) {
		if (is_file($f)) {
			@unlink($f);
		}
	}
	$GLOBALS['pt_creados'] = ['crm' => [], 'tech' => [], 'propuesta' => [], 'contrato' => [], 'archivo' => []];
}
register_shutdown_function('pt_limpiar');
```

- [ ] **Step 2: Escribir la prueba que falla (tabla, crear y lecturas)**

Create `tests/plan/datos-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/datos-wp-test.php
// Task 5: tabla del plan, migración, crear idempotente y lecturas.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/datos-prueba.php';
global $wpdb;
$ids = function (array $filas): array { return array_map(function ($p) { return (int) $p->id; }, $filas); };

// 1) Tabla y migración.
delete_option('at_plan_schema');
delete_transient('at_pt_migrar_intento');
at_pt_migrar_esquema();
$esperadas = ['id', 'contrato_id', 'crm_cliente_id', 'tech_id', 'propuesta_id', 'codigo', 'estado', 'fecha_inicio', 'payload', 'comentarios', 'nota', 'view_url', 'pdf_url', 'created_at', 'updated_at', 'enviado_at'];
ok(get_option('at_plan_schema') === '1', '1) la migración deja at_plan_schema = 1');
ok($wpdb->get_col('SHOW COLUMNS FROM ' . at_pt_tabla()) === $esperadas, '1) la tabla tiene las 16 columnas del contrato de interfaces, en orden');
$indices = [];
foreach ((array) $wpdb->get_results('SHOW INDEX FROM ' . at_pt_tabla()) as $i) {
	$indices[$i->Key_name] = (int) $i->Non_unique === 0 ? 'unico' : 'normal';
}
ok(($indices['PRIMARY'] ?? '') === 'unico' && ($indices['uniq_contrato'] ?? '') === 'unico' && ($indices['uniq_codigo'] ?? '') === 'unico' && ($indices['idx_crm'] ?? '') === 'normal', '1) índices: primario, uniq_contrato, uniq_codigo e idx_crm');
$col_estado = $wpdb->get_row('SHOW COLUMNS FROM ' . at_pt_tabla() . " LIKE 'estado'");
ok($col_estado && $col_estado->Default === 'generando', '1) un plan nace en «generando»');
delete_option('at_plan_schema');
at_pt_migrar_esquema();
ok(get_option('at_plan_schema') === '1' && $wpdb->get_col('SHOW COLUMNS FROM ' . at_pt_tabla()) === $esperadas, '1) correr la migración otra vez no cambia la tabla');
ok(has_action('admin_init', 'at_pt_migrar_esquema') !== false, '1) la migración cuelga de admin_init');

// 2) Crear el plan de un contrato de servicios firmado.
$m = pt_marca();
$cli = pt_cliente($m);
$prop = pt_propuesta($m);
$cid = pt_contrato($cli['tech'], $prop);
$pid = at_pt_crear_plan($cid);
$f = is_int($pid) ? at_pt_plan($pid) : null;
ok(is_int($pid) && $pid > 0, '2) crea el plan de un contrato de servicios firmado');
ok($f && $f->estado === 'generando' && (int) $f->contrato_id === $cid && (int) $f->tech_id === $cli['tech'] && (int) $f->crm_cliente_id === $cli['crm'] && (int) $f->propuesta_id === $prop, '2) enlaza contrato, ficha operativa, cliente del CRM y propuesta; nace en «generando»');
ok($f && preg_match('/^[A-Za-z0-9]{12}$/', (string) $f->codigo) === 1, '2) código de 12 letras o números');
ok($f && $f->payload === null && $f->fecha_inicio === null && $f->view_url === null && $f->nota === null, '2) todavía sin contenido, fecha, vista ni nota');

// 3) Idempotente: un contrato, un plan.
ok(at_pt_crear_plan($cid) === $pid, '3) crear otra vez devuelve el mismo plan');
ok((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . at_pt_tabla() . ' WHERE contrato_id = %d', $cid)) === 1, '3) sigue habiendo un solo plan para ese contrato');

// 4) Contratos que no llevan plan.
$sop = pt_contrato($cli['tech'], null, ['type' => 'soporte']);
$e = at_pt_crear_plan($sop);
ok(is_wp_error($e) && $e->get_error_code() === 'at_pt_no_servicios' && !at_pt_plan_de_contrato($sop), '4) un contrato de soporte no tiene plan');
$sin_firma = pt_contrato($cli['tech'], null, ['status' => 'sent']);
$e = at_pt_crear_plan($sin_firma);
ok(is_wp_error($e) && $e->get_error_code() === 'at_pt_sin_firma' && !at_pt_plan_de_contrato($sin_firma), '4) un contrato sin la firma del cliente no tiene plan');
$e = at_pt_crear_plan(pt_contrato_inexistente());
ok(is_wp_error($e) && $e->get_error_code() === 'at_pt_sin_contrato', '4) un contrato que no existe: error con motivo');

// 5) Contrato sin propuesta y cliente sin correo (Review Focus 4).
$m2 = pt_marca();
$cli2 = pt_cliente($m2, false);
$cid2 = pt_contrato($cli2['tech'], null);
$pid2 = at_pt_crear_plan($cid2);
$f2 = is_int($pid2) ? at_pt_plan($pid2) : null;
ok($f2 && $f2->propuesta_id === null && (int) $f2->crm_cliente_id === $cli2['crm'], '5) contrato sin propuesta y cliente sin correo: el plan igual se crea');

// 6) Ficha operativa que todavía no está enlazada al CRM.
$m3 = pt_marca();
$cli3 = pt_cliente($m3, true, false);
$cid3 = pt_contrato($cli3['tech'], null);
$pid3 = at_pt_crear_plan($cid3);
$f3 = is_int($pid3) ? at_pt_plan($pid3) : null;
ok($f3 && $f3->crm_cliente_id === null && !in_array($pid3, $ids(at_pt_planes_de_crm($cli3['crm'])), true), '6) ficha sin enlazar: el plan queda sin cliente del CRM y no aparece en su ficha');
$wpdb->update($wpdb->prefix . 'automatiza_tech_clients', ['crm_cliente_id' => $cli3['crm']], ['id' => $cli3['tech']]);
ok(in_array($pid3, $ids(at_pt_planes_de_crm($cli3['crm'])), true), '6) al enlazar la ficha, el plan aparece en el cliente del CRM');

// 7) Lecturas.
$codigo = $f ? (string) $f->codigo : '';
ok($codigo !== '' && (int) at_pt_plan_por_codigo($codigo)->id === $pid, '7) por código exacto');
$cambiado = ctype_digit($codigo) ? null : (strtolower($codigo) !== $codigo ? strtolower($codigo) : strtoupper($codigo));
ok($cambiado === null || at_pt_plan_por_codigo($cambiado) === null, '7) el código distingue mayúsculas');
ok(at_pt_plan_por_codigo('corto') === null && at_pt_plan_por_codigo("abc' OR 1=1 #") === null, '7) un código mal formado no busca nada');
ok((int) at_pt_plan_de_contrato($cid)->id === $pid && at_pt_plan_de_contrato($sop) === null, '7) plan de un contrato');
$cid_b = pt_contrato($cli['tech'], null);
$pid_b = at_pt_crear_plan($cid_b);
ok($ids(at_pt_planes_de_crm($cli['crm'])) === [$pid_b, $pid], '7) planes del cliente del CRM, del más nuevo al más viejo');
ok(at_pt_planes_de_crm(0) === [] && at_pt_plan(0) === null && at_pt_plan(pt_plan_inexistente()) === null, '7) ids vacíos o inexistentes: nada');

// 8) Contenido del plan.
ok(at_pt_payload($f) === [], '8) sin contenido: arreglo vacío');
ok(at_pt_payload((object) ['payload' => '{roto']) === [] && at_pt_payload((object) ['payload' => '"texto"']) === [], '8) JSON roto o que no es objeto: arreglo vacío');
ok(at_pt_payload((object) ['payload' => '{"version":1,"fases":[]}']) === ['version' => 1, 'fases' => []], '8) JSON válido: el arreglo');

// 9) El código nunca repite el de una propuesta (el renderer publica los dos en /p/<código>/).
$usado = substr(md5($m), 0, 12);
$vueltas = 0;
$repetir = function ($clave) use (&$vueltas, $usado) { $vueltas++; return $vueltas === 1 ? $usado : $clave; };
add_filter('random_password', $repetir);
$cid9 = pt_contrato($cli['tech'], null);
$pid9 = at_pt_crear_plan($cid9);
remove_filter('random_password', $repetir);
ok(is_int($pid9) && $vueltas >= 2 && at_pt_plan($pid9)->codigo !== $usado, '9) si el código sale igual al de una propuesta, se genera otro');

fin();
```

- [ ] **Step 3: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/datos-wp-test.php; echo "exit=$?"
```
Expected (el módulo no existe ni se carga todavía):
```
El módulo plan-trabajo no está cargado en ese sitio (falta inc/plan-trabajo/cargar.php o su línea en inc/admin-proposals.php).
exit=2
```

- [ ] **Step 4: Crear `cargar.php` (carga `puras.php` y `datos.php`)**

Create `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php`:
```php
<?php
/**
 * Plan de trabajo del proyecto (cronograma con carta Gantt). Etapa 1: solo Luis lo ve.
 * Spec: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md
 * Módulo independiente de la propuesta. Se carga siempre (panel, admin-post, REST y la página de firma
 * de contratos, que hace wp-load) desde inc/admin-proposals.php; functions.php no se toca.
 */
if (!defined('ABSPATH')) {
	exit;
}
require_once __DIR__ . '/puras.php';
require_once __DIR__ . '/datos.php';
```

- [ ] **Step 5: Crear `datos.php` con la tabla, la migración, las lecturas y crear**

Create `wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php`:
```php
<?php
/**
 * Plan de trabajo: tabla propia (wp_automatiza_planes_trabajo), lecturas y escrituras.
 * Un contrato de servicios firmado tiene a lo más un plan (índice único uniq_contrato).
 * Lo contratado sale del contrato; la propuesta, si existe, solo aporta extracto de la reunión y fotos.
 */
if (!defined('ABSPATH')) {
	exit;
}

function at_pt_tabla(): string {
	global $wpdb;
	return $wpdb->prefix . 'automatiza_planes_trabajo';
}

/**
 * Crea la tabla con dbDelta. Idempotente (opción at_plan_schema = '1'). Si falla (tabla bloqueada,
 * sin privilegio en el hosting), espera 5 minutos antes de reintentar, igual que at_cc_migrar_esquema().
 */
function at_pt_migrar_esquema(): void {
	if (get_option('at_plan_schema') === '1' || get_transient('at_pt_migrar_intento')) {
		return;
	}
	global $wpdb;
	$t = at_pt_tabla();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta("CREATE TABLE {$t} (
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
) {$charset};");
	if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($t))) !== $t) {
		set_transient('at_pt_migrar_intento', 1, 5 * MINUTE_IN_SECONDS);
		error_log('at_pt: no se pudo crear ' . $t . ': ' . $wpdb->last_error);
		return;
	}
	update_option('at_plan_schema', '1');
}
add_action('admin_init', 'at_pt_migrar_esquema');

/** Un plan por id; null si no existe. */
function at_pt_plan(int $id): ?object {
	if ($id <= 0) {
		return null;
	}
	at_pt_migrar_esquema();
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_pt_tabla() . ' WHERE id = %d', $id));
	return $f ?: null;
}

/** Un plan por su código (12 letras o números, distingue mayúsculas); null si no existe. */
function at_pt_plan_por_codigo(string $codigo): ?object {
	if (!preg_match('/^[A-Za-z0-9]{12}$/', $codigo)) {
		return null;
	}
	at_pt_migrar_esquema();
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_pt_tabla() . ' WHERE codigo = %s', $codigo));
	// La columna compara sin distinguir mayúsculas (collation *_ci): se exige el código exacto.
	return ($f && hash_equals((string) $f->codigo, $codigo)) ? $f : null;
}

/** El plan de un contrato (hay a lo más uno); null si no tiene. */
function at_pt_plan_de_contrato(int $contrato_id): ?object {
	if ($contrato_id <= 0) {
		return null;
	}
	at_pt_migrar_esquema();
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_pt_tabla() . ' WHERE contrato_id = %d LIMIT 1', $contrato_id));
	return $f ?: null;
}

/**
 * Planes de un cliente del CRM, del más nuevo al más viejo. Manda el enlace actual de la ficha
 * operativa (si la ficha se enlazó después de crear el plan, el plan igual aparece); si la ficha
 * no está enlazada, el cliente que quedó anotado en el plan.
 */
function at_pt_planes_de_crm(int $crm_id): array {
	if ($crm_id <= 0) {
		return [];
	}
	at_pt_migrar_esquema();
	global $wpdb;
	return (array) $wpdb->get_results($wpdb->prepare(
		'SELECT p.* FROM ' . at_pt_tabla() . " p LEFT JOIN {$wpdb->prefix}automatiza_tech_clients t ON t.id = p.tech_id
		 WHERE COALESCE(t.crm_cliente_id, p.crm_cliente_id) = %d ORDER BY p.id DESC",
		$crm_id
	));
}

/** El contenido del plan como arreglo; [] si está vacío o no es un objeto JSON. */
function at_pt_payload(object $fila): array {
	$d = json_decode((string) ($fila->payload ?? ''), true);
	return is_array($d) ? $d : [];
}

/** Fila del contrato (tabla de contracts/contract-service.php) sin cargar ContractService; null si no existe. */
function at_pt_db_contrato(int $id): ?object {
	if ($id <= 0) {
		return null;
	}
	global $wpdb;
	$c = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_contracts WHERE id = %d", $id));
	return $c ?: null;
}

/**
 * Crea el plan de un contrato de servicios firmado, en «generando». Idempotente: si el contrato ya
 * tiene plan, devuelve su id. Devuelve el id o WP_Error con un motivo legible.
 */
function at_pt_crear_plan(int $contrato_id): int|WP_Error {
	at_pt_migrar_esquema();
	$existente = at_pt_plan_de_contrato($contrato_id);
	if ($existente) {
		return (int) $existente->id;
	}
	$c = at_pt_db_contrato($contrato_id);
	if (!$c) {
		return new WP_Error('at_pt_sin_contrato', 'El contrato no existe.');
	}
	if ((string) $c->type !== 'servicios') {
		return new WP_Error('at_pt_no_servicios', 'El plan de trabajo es solo para contratos de servicios.');
	}
	if ((string) $c->status !== 'signed') {
		return new WP_Error('at_pt_sin_firma', 'El contrato todavía no está firmado por el cliente.');
	}
	global $wpdb;
	$tech_id = (int) $c->client_id;
	$crm_id = 0;
	if ($tech_id > 0) {
		$crm_id = (int) $wpdb->get_var($wpdb->prepare("SELECT crm_cliente_id FROM {$wpdb->prefix}automatiza_tech_clients WHERE id = %d", $tech_id));
	}
	$propuesta_id = (int) ($c->proposal_id ?? 0);
	for ($intento = 0; $intento < 5; $intento++) {
		$codigo = wp_generate_password(12, false);
		// El renderer publica en /p/<código>/, la misma carpeta que usan las propuestas: nunca repetir uno.
		$usado = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}automatiza_propuestas WHERE unique_link_id = %s", $codigo));
		if ($usado > 0 || !preg_match('/^[A-Za-z0-9]{12}$/', $codigo)) {
			continue;
		}
		$ok = $wpdb->insert(at_pt_tabla(), [
			'contrato_id'    => $contrato_id,
			'crm_cliente_id' => $crm_id > 0 ? $crm_id : null,
			'tech_id'        => $tech_id > 0 ? $tech_id : null,
			'propuesta_id'   => $propuesta_id > 0 ? $propuesta_id : null,
			'codigo'         => $codigo,
			'estado'         => 'generando',
		]);
		if ($ok) {
			return (int) $wpdb->insert_id;
		}
		// Otro proceso pudo crear el plan de este contrato al mismo tiempo (índice único uniq_contrato).
		$existente = at_pt_plan_de_contrato($contrato_id);
		if ($existente) {
			return (int) $existente->id;
		}
	}
	return new WP_Error('at_pt_insert', 'No se pudo crear el plan: ' . $wpdb->last_error);
}
```

- [ ] **Step 6: Cargar el módulo desde `inc/admin-proposals.php`**

`admin-proposals.php` está en CRLF: un patrón en LF no calza. Crear `<SCR>/plan-trabajo/carga-plan.py` con la herramienta
Write (no con un heredoc) con este contenido:
```python
"""Task 5: carga el módulo del plan desde inc/admin-proposals.php, justo después del cierre (respeta CRLF)."""
import sys

p = sys.argv[1] if len(sys.argv) > 1 else 'wp-content/themes/automatiza-tech/inc/admin-proposals.php'
t = open(p, encoding='utf-8', newline='').read()
eol = '\r\n' if '\r\n' in t else '\n'
ancla = "require_once __DIR__ . '/cierre-cliente/cargar.php';" + eol
assert t.count(ancla) == 1, 'la línea del cierre no está una sola vez: revisar a mano'
assert 'plan-trabajo/cargar.php' not in t, 'la línea del plan ya está'
t = t.replace(ancla, ancla + "require_once __DIR__ . '/plan-trabajo/cargar.php';" + eol, 1)
open(p, 'w', encoding='utf-8', newline='').write(t)
print('admin-proposals.php ok,', 'CRLF' if eol == '\r\n' else 'LF')
```
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad
python "$SCR/plan-trabajo/carga-plan.py" && "$PHP" -l wp-content/themes/automatiza-tech/inc/admin-proposals.php && git diff --stat
```
Expected: `admin-proposals.php ok, CRLF`, `No syntax errors detected in wp-content/themes/automatiza-tech/inc/admin-proposals.php` y
`1 file changed, 1 insertion(+)` (la línea 92 nueva: `require_once __DIR__ . '/plan-trabajo/cargar.php';`). Una segunda
corrida del script se niega (`AssertionError: la línea del plan ya está`).

- [ ] **Step 7: Correr la prueba y verla pasar**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
for f in wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php; do "$PHP" -l "$f"; done
"$PHP" tests/plan/datos-wp-test.php; echo "exit=$?"
```
Expected: dos `No syntax errors detected`, 28 líneas `ok   …` (de `1) la migración deja at_plan_schema = 1` a
`9) si el código sale igual al de una propuesta, se genera otro`), ninguna `FALLA`, `TODO OK` y `exit=0`.

- [ ] **Step 8: Comprobar que cargar el módulo no rompe lo que ya había**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
for t in fechas validacion cronograma render; do printf '%-12s ' "$t"; "$PHP" "tests/plan/$t-test.php" | tail -1; done
"$PHP" tests/cierre/pagina-wp-test.php | tail -1
```
Expected: `TODO OK` cinco veces (las cuatro pruebas puras de las Tasks 1-4 y la página del cierre).

- [ ] **Step 9: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add tests/plan/wp-bootstrap.php tests/plan/datos-prueba.php tests/plan/datos-wp-test.php \
  wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php \
  wp-content/themes/automatiza-tech/inc/admin-proposals.php
git commit -m "$(cat <<'EOF'
feat(plan): tabla del plan de trabajo, migración, crear idempotente y lecturas; el módulo se carga desde admin-proposals

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 10: Escribir la prueba que falla (guardar y cambiar estado)**

Create `tests/plan/datos-guardar-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/datos-guardar-wp-test.php
// Task 5: guardar columnas del plan y cambiar su estado.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_guardar', 'at_pt_cambiar_estado');
require __DIR__ . '/datos-prueba.php';

$m = pt_marca();
$cli = pt_cliente($m);
$cid = pt_contrato($cli['tech'], null);
$pid = at_pt_crear_plan($cid);
if (!is_int($pid)) {
	echo "FALLA no se pudo crear el plan de prueba\n\n1 FALLAS\n";
	exit(1);
}
$no_existe = pt_plan_inexistente();

// 1) Guardar contenido y textos.
$plan = ['version' => 1, 'proyecto' => '[PRUEBA] Proyecto «ñandú»', 'fases' => [['clave' => 'soporte', 'titulo' => 'Soporte y mejora continua', 'bloques' => []]]];
ok(at_pt_guardar($pid, ['payload' => $plan, 'fecha_inicio' => '2026-10-05', 'comentarios' => 'Más corto el diseño', 'nota' => 'Nota']), '1) guarda contenido, fecha, comentarios y nota');
$f = at_pt_plan($pid);
ok(at_pt_payload($f) === $plan && strpos((string) $f->payload, '«ñandú»') !== false, '1) el contenido vuelve igual y queda como JSON legible (sin \\u)');
ok($f->fecha_inicio === '2026-10-05' && $f->comentarios === 'Más corto el diseño' && $f->nota === 'Nota', '1) fecha, comentarios y nota guardados');

// 2) Columnas que no se editan.
$codigo = (string) $f->codigo;
ok(at_pt_guardar($pid, ['codigo' => 'XXXXXXXXXXXX', 'contrato_id' => 1, 'nota' => 'otra']) && at_pt_plan($pid)->codigo === $codigo && (int) at_pt_plan($pid)->contrato_id === $cid, '2) código y contrato no se editan: se ignoran');
ok(!at_pt_guardar($pid, ['codigo' => 'XXXXXXXXXXXX']), '2) sin ninguna columna permitida no guarda nada');

// 3) Valores que no sirven: no se guarda nada (nunca un plan roto).
ok(!at_pt_guardar($pid, ['estado' => 'inventado', 'nota' => 'no']) && at_pt_plan($pid)->estado === 'generando' && at_pt_plan($pid)->nota === 'otra', '3) un estado desconocido no se guarda (ni la nota que venía con él)');
ok(!at_pt_guardar($pid, ['fecha_inicio' => '2026-02-30']) && at_pt_plan($pid)->fecha_inicio === '2026-10-05', '3) una fecha que no existe no se guarda');
ok(!at_pt_guardar($pid, ['payload' => 'no es un arreglo']) && at_pt_payload(at_pt_plan($pid)) === $plan, '3) un contenido que no es arreglo no se guarda');
ok(!at_pt_guardar($no_existe, ['nota' => 'x']), '3) un plan que no existe no se guarda');

// 4) Límites y formatos.
ok(at_pt_guardar($pid, ['nota' => str_repeat('á', 1500)]) && mb_strlen((string) at_pt_plan($pid)->nota) === 1000, '4) la nota se recorta a 1000 caracteres');
ok(at_pt_guardar($pid, ['view_url' => 'javascript:alert(1)', 'pdf_url' => 'https://render.ejemplo.test/p/abc123def456/presentation.pdf']) && at_pt_plan($pid)->view_url === '' && at_pt_plan($pid)->pdf_url === 'https://render.ejemplo.test/p/abc123def456/presentation.pdf', '4) solo se guardan enlaces http(s)');
ok(at_pt_guardar($pid, ['enviado_at' => '2026-10-05 10:00:00']) && at_pt_plan($pid)->enviado_at === '2026-10-05 10:00:00' && !at_pt_guardar($pid, ['enviado_at' => 'ayer']), '4) enviado_at: solo fecha y hora válidas');
ok(at_pt_guardar($pid, ['payload' => null, 'fecha_inicio' => '']) && at_pt_plan($pid)->payload === null && at_pt_plan($pid)->fecha_inicio === null, '4) contenido y fecha se pueden vaciar');

// 5) Cambiar el estado según at_pt_transiciones().
ok(at_pt_cambiar_estado($pid, 'borrador', 'Avisos: ninguno') && at_pt_plan($pid)->estado === 'borrador' && at_pt_plan($pid)->nota === 'Avisos: ninguno', '5) generando → borrador, con su nota');
ok(!at_pt_cambiar_estado($pid, 'enviado') && at_pt_plan($pid)->estado === 'borrador', '5) borrador → enviado no vale: no cambia');
ok(!at_pt_cambiar_estado($pid, 'inventado') && at_pt_plan($pid)->estado === 'borrador', '5) estado desconocido: no cambia');
ok(at_pt_cambiar_estado($pid, 'error', 'Destrabado por Luis') && at_pt_plan($pid)->estado === 'error' && at_pt_plan($pid)->nota === 'Destrabado por Luis', '5) borrador → error con la nota');
ok(at_pt_cambiar_estado($pid, 'borrador') && at_pt_plan($pid)->nota === '', '5) error → borrador sin nota: la nota se limpia');
ok(!at_pt_cambiar_estado($no_existe, 'borrador'), '5) un plan que no existe: false');

fin();
```

- [ ] **Step 11: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/datos-guardar-wp-test.php; echo "exit=$?"
```
Expected:
```
FALLA falta la función at_pt_guardar()

1 FALLAS
exit=1
```

- [ ] **Step 12: Implementar guardar y cambiar estado**

Agregar al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php`:
```php
/** Todos los estados que conoce el plan (los de at_pt_transiciones(), de origen y de destino). */
function at_pt_db_estados(): array {
	$e = [];
	foreach (at_pt_transiciones() as $de => $destinos) {
		$e[(string) $de] = true;
		foreach ((array) $destinos as $a) {
			$e[(string) $a] = true;
		}
	}
	return array_keys($e);
}

/** 'AAAA-MM-DD' que existe en el calendario. */
function at_pt_db_fecha_valida(string $f): bool {
	return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/** Enlace http(s) de hasta 500 caracteres; '' si no sirve (nunca se recorta un enlace). */
function at_pt_db_url($u): string {
	$u = trim((string) $u);
	if ($u === '' || strlen($u) > 500 || !preg_match('#^https?://#i', $u)) {
		return '';
	}
	return (string) esc_url_raw($u, ['http', 'https']);
}

/**
 * Guarda columnas del plan. Permitidas: estado, fecha_inicio, payload (arreglo → JSON), comentarios,
 * nota, view_url, pdf_url, enviado_at; las demás (id, contrato_id, codigo, …) se ignoran. Devuelve false
 * sin tocar nada si el plan no existe, si no viene ninguna permitida o si un valor no sirve (estado
 * desconocido, fecha inexistente, payload que no es arreglo): nunca se guarda un plan roto.
 */
function at_pt_guardar(int $id, array $campos): bool {
	$datos = [];
	foreach ($campos as $k => $v) {
		switch ($k) {
			case 'estado':
				if (!in_array((string) $v, at_pt_db_estados(), true)) {
					return false;
				}
				$datos['estado'] = (string) $v;
				break;
			case 'fecha_inicio':
				if ($v === null || $v === '') {
					$datos['fecha_inicio'] = null;
				} elseif (at_pt_db_fecha_valida((string) $v)) {
					$datos['fecha_inicio'] = (string) $v;
				} else {
					return false;
				}
				break;
			case 'payload':
				if ($v === null || $v === []) {
					$datos['payload'] = null;
					break;
				}
				if (!is_array($v)) {
					return false;
				}
				$json = wp_json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				if (!is_string($json)) {
					return false;
				}
				$datos['payload'] = $json;
				break;
			case 'comentarios':
				$datos['comentarios'] = mb_substr((string) $v, 0, 4000);
				break;
			case 'nota':
				$datos['nota'] = mb_substr((string) $v, 0, 1000);
				break;
			case 'view_url':
			case 'pdf_url':
				$datos[$k] = at_pt_db_url($v);
				break;
			case 'enviado_at':
				if ($v === null || $v === '') {
					$datos['enviado_at'] = null;
				} elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $v)) {
					$datos['enviado_at'] = (string) $v;
				} else {
					return false;
				}
				break;
		}
	}
	if (!$datos || !at_pt_plan($id)) {
		return false;
	}
	global $wpdb;
	return $wpdb->update(at_pt_tabla(), $datos, ['id' => $id]) !== false;
}

/**
 * Cambia el estado si la transición es válida (at_pt_transicion_valida). La nota describe el estado
 * nuevo: cambiar sin nota la deja vacía. Compara y cambia: si otro proceso movió el estado entre la
 * lectura y la escritura, no se pisa y devuelve false.
 */
function at_pt_cambiar_estado(int $id, string $a, string $nota = ''): bool {
	$f = at_pt_plan($id);
	if (!$f || !at_pt_transicion_valida((string) $f->estado, $a)) {
		return false;
	}
	global $wpdb;
	$r = $wpdb->update(at_pt_tabla(), ['estado' => $a, 'nota' => mb_substr($nota, 0, 1000)], ['id' => $id, 'estado' => (string) $f->estado]);
	if ($r === false) {
		return false;
	}
	$ahora = at_pt_plan($id);
	return $ahora !== null && $ahora->estado === $a;
}
```

- [ ] **Step 13: Correr y ver pasar**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" -l wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php && "$PHP" tests/plan/datos-guardar-wp-test.php; echo "exit=$?"
```
Expected: `No syntax errors detected`, 19 líneas `ok   …`, `TODO OK`, `exit=0`.

- [ ] **Step 14: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add tests/plan/datos-guardar-wp-test.php wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php
git commit -m "$(cat <<'EOF'
feat(plan): guardar columnas del plan sin aceptar valores rotos y cambiar su estado solo con transiciones válidas

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 15: Escribir la prueba que falla (feriados, tabla de tiempos, contratos sin plan)**

Create `tests/plan/datos-ajustes-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/datos-ajustes-wp-test.php
// Task 5: feriados, tabla de tiempos y contratos firmados que aún no tienen plan.
// Las opciones se simulan con pre_option_*: la base local no se toca.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_feriados', 'at_pt_duraciones', 'at_pt_contratos_sin_plan');
require __DIR__ . '/datos-prueba.php';

// 1) Feriados de «Ajustes del chat».
$horario = null;
add_filter('pre_option_automatiza_chat_schedule', function () use (&$horario) { return $horario; });
$horario = ['monday' => ['start' => '09:00', 'end' => '18:00'], 'holidays' => "2026-10-12\r\n2026-12-25\n\nno-es-fecha\n"];
$feriados = at_pt_feriados();
ok(in_array('2026-10-12', $feriados, true) && in_array('2026-12-25', $feriados, true) && !in_array('no-es-fecha', $feriados, true), '1) lee los feriados del ajuste existente (una fecha por línea, con \\r\\n o \\n)');
$horario = ['monday' => ['start' => '09:00', 'end' => '18:00']];
ok(at_pt_feriados() === [], '1) sin feriados cargados: lista vacía');
$horario = 'texto';
ok(at_pt_feriados() === [], '1) ajuste con otra forma: lista vacía');

// 2) Tabla de tiempos de referencia.
$tabla = '';
add_filter('pre_option_at_pt_duraciones', function () use (&$tabla) { return $tabla; });
ok(at_pt_duraciones() === at_pt_duraciones_defecto(), '2) sin tabla guardada: la de referencia');
$tabla = '{roto';
ok(at_pt_duraciones() === at_pt_duraciones_defecto(), '2) tabla ilegible: la de referencia');
$tabla = wp_json_encode([
	'sitio_una_pagina' => ['nombre' => 'Sitio de una página', 'diseno' => 4, 'desarrollo' => 6, 'pruebas' => 2, 'implementacion' => 1],
	'MAL CLAVE'        => ['nombre' => 'Fila inválida', 'diseno' => 1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
]);
$t = at_pt_duraciones();
ok(($t['sitio_una_pagina']['diseno'] ?? null) === 4 && !isset($t['MAL CLAVE']), '2) la tabla de Luis, normalizada (sin las filas inválidas)');
$tabla = ['google_ads' => ['nombre' => 'Google Ads', 'diseno' => 2, 'desarrollo' => 3, 'pruebas' => 1, 'implementacion' => 1]];
ok(isset(at_pt_duraciones()['google_ads']), '2) también acepta la tabla guardada como arreglo');

// 3) Contratos de servicios firmados sin plan, de todas las fichas del cliente.
$m = pt_marca();
$cli = pt_cliente($m);
$ficha2 = pt_ficha($cli['crm'], $m . '-2');
$c1 = pt_contrato($cli['tech'], null);
$c2 = pt_contrato($ficha2, null);
pt_contrato($cli['tech'], null, ['type' => 'soporte']);
pt_contrato($cli['tech'], null, ['status' => 'sent']);
$c5 = pt_contrato($cli['tech'], null);
at_pt_crear_plan($c5);
$ids = array_map(function ($c) { return (int) $c->id; }, at_pt_contratos_sin_plan($cli['crm']));
ok($ids === [$c2, $c1], '3) servicios firmados sin plan de las dos fichas, del más nuevo al más viejo (sin soporte, sin firmar ni con plan)');
ok(at_pt_contratos_sin_plan(0) === [], '3) sin cliente: lista vacía');
$otro = pt_cliente(pt_marca());
ok(at_pt_contratos_sin_plan($otro['crm']) === [], '3) otro cliente no ve estos contratos');

fin();
```

- [ ] **Step 16: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/datos-ajustes-wp-test.php; echo "exit=$?"
```
Expected:
```
FALLA falta la función at_pt_feriados()

1 FALLAS
exit=1
```

- [ ] **Step 17: Implementar feriados, tabla de tiempos y contratos sin plan**

Agregar al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php`:
```php
/** Feriados de «Ajustes del chat» (automatiza_chat_schedule['holidays'], una fecha por línea). */
function at_pt_feriados(): array {
	$o = get_option('automatiza_chat_schedule');
	return at_pt_feriados_de_texto(is_array($o) ? (string) ($o['holidays'] ?? '') : '');
}

/** Tabla de tiempos de referencia (opción at_pt_duraciones, JSON o arreglo), normalizada; la de defecto si está vacía o ilegible. */
function at_pt_duraciones(): array {
	$raw = get_option('at_pt_duraciones', '');
	$t = is_array($raw) ? $raw : json_decode((string) $raw, true);
	$t = is_array($t) ? at_pt_normalizar_duraciones($t) : [];
	return $t ?: at_pt_duraciones_defecto();
}

/** Contratos de servicios firmados de todas las fichas operativas del cliente que aún no tienen plan, del más nuevo al más viejo. */
function at_pt_contratos_sin_plan(int $crm_id): array {
	if ($crm_id <= 0) {
		return [];
	}
	at_pt_migrar_esquema();
	global $wpdb;
	$k = $wpdb->prefix . 'automatiza_contracts';
	if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($k))) !== $k) {
		return [];
	}
	return (array) $wpdb->get_results($wpdb->prepare(
		"SELECT c.* FROM {$k} c
		 JOIN {$wpdb->prefix}automatiza_tech_clients t ON t.id = c.client_id
		 LEFT JOIN " . at_pt_tabla() . " p ON p.contrato_id = c.id
		 WHERE t.crm_cliente_id = %d AND c.type = 'servicios' AND c.status = 'signed' AND p.id IS NULL
		 ORDER BY c.id DESC",
		$crm_id
	));
}
```

- [ ] **Step 18: Correr y ver pasar**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" -l wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php && "$PHP" tests/plan/datos-ajustes-wp-test.php; echo "exit=$?"
```
Expected: `No syntax errors detected`, 10 líneas `ok   …`, `TODO OK`, `exit=0`.

- [ ] **Step 19: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add tests/plan/datos-ajustes-wp-test.php wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php
git commit -m "$(cat <<'EOF'
feat(plan): feriados del ajuste del chat, tabla de tiempos normalizada y contratos firmados sin plan

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 20: Escribir la prueba que falla (datos del render y contexto de la IA)**

Create `tests/plan/datos-contexto-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/datos-contexto-wp-test.php
// Task 5: datos para el renderer y contexto para la IA (con y sin propuesta, con y sin correo).
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_datos_render', 'at_pt_contexto');
require __DIR__ . '/datos-prueba.php';
global $wpdb;
add_filter('pre_option_at_pt_duraciones', function () { return ''; });
add_filter('pre_option_at_cc_whatsapp_at', function () { return '+56 9 1234 5678'; });

$m = pt_marca();
$cli = pt_cliente($m);
$wpdb->update($wpdb->prefix . 'crm_clientes', ['rubro' => '[PRUEBA] Panadería'], ['id' => $cli['crm']]);
$prop = pt_propuesta($m, ['transcript' => str_repeat('Hablamos del sitio. ', 400)]);
$cid = pt_contrato($cli['tech'], $prop);
$pid = at_pt_crear_plan($cid);
$f = at_pt_plan((int) $pid);

// 1) Datos para el renderer, con propuesta y cliente con correo.
$d = at_pt_datos_render($f);
ok(array_keys($d) === ['codigo', 'company_name', 'client_name', 'portal_url', 'whatsapp', 'fecha_firma', 'garantia_meses'], '1) las siete claves que espera at_pt_armar_render() (con la garantía del contrato)');
ok($d['codigo'] === $f->codigo && $d['fecha_firma'] === '2026-10-09', '1) código del plan y fecha de firma del contrato');
ok($d['company_name'] === '[PRUEBA] Empresa ' . $m && $d['client_name'] === 'Cliente Prueba', '1) con propuesta: su nombre comercial (company_name) y el cliente que firma en el contrato');
ok(strpos($d['portal_url'], 'crm_view=timeline&cid=' . $cli['crm'] . '&token=') !== false, '1) enlace al portal del cliente (at_crm_url_portal)');
ok($d['whatsapp'] === '56912345678', '1) WhatsApp de AutomatizaTech (Ajustes del cierre), solo dígitos');
ok($d['garantia_meses'] === 3, '1) contrato sin el marcador garantia_meses_servicio: 3 meses');

// 2) Sin propuesta y sin correo (Review Focus 4).
$m2 = pt_marca();
$cli2 = pt_cliente($m2, false);
$cid2 = pt_contrato($cli2['tech'], null);
$f2 = at_pt_plan((int) at_pt_crear_plan($cid2));
$d2 = at_pt_datos_render($f2);
ok($d2['portal_url'] === '', '2) cliente sin correo: sin enlace al portal (la lámina «Sigue tu proyecto» va sin enlace)');
ok($d2['company_name'] === '[PRUEBA] Sitio de una página' && $d2['client_name'] === 'Cliente Prueba', '2) sin propuesta: el nombre del proyecto del contrato (nombre_proyecto)');

// 2b) Sin propuesta ni nombre de proyecto: la razón social; sin nada, el nombre del cliente. Garantía del contrato.
$ph_base = ['representante_cliente_nombre' => 'Cliente Prueba', 'alcance' => 'Sitio de una página con formulario.'];
$f2b = at_pt_plan((int) at_pt_crear_plan(pt_contrato($cli2['tech'], null, ['ph' => $ph_base + ['razon_social_cliente' => '[PRUEBA] Razón Social SpA', 'garantia_meses_servicio' => '6']])));
$d2b = at_pt_datos_render($f2b);
ok($d2b['company_name'] === '[PRUEBA] Razón Social SpA' && $d2b['garantia_meses'] === 6, '2b) sin propuesta ni nombre de proyecto: la razón social; garantía de 6 meses del contrato');
$f2c = at_pt_plan((int) at_pt_crear_plan(pt_contrato($cli2['tech'], null, ['ph' => $ph_base + ['garantia_meses_servicio' => 'a convenir']])));
$d2c = at_pt_datos_render($f2c);
ok($d2c['company_name'] === 'Cliente Prueba' && $d2c['garantia_meses'] === 3, '2b) sin propuesta, proyecto ni razón social: el nombre del cliente (nunca vacío); una garantía ilegible vale 3');
ok(at_pt_contexto($f2b)['empresa'] === '[PRUEBA] Razón Social SpA' && at_pt_contexto($f2b)['contrato']['garantia_meses'] === 6, '2b) el contexto de la IA usa la misma empresa y la misma garantía');
$f2d = at_pt_plan((int) at_pt_crear_plan(pt_contrato($cli2['tech'], null, ['ph' => $ph_base + ['garantia_meses_servicio' => '36 meses']])));
ok(at_pt_datos_render($f2d)['garantia_meses'] === 3, '2b) una garantía fuera de 0 a 24 meses (la que valida el plan) vale 3');

// 3) Contexto para la IA.
$x = at_pt_contexto($f);
ok(array_keys($x) === ['ok', 'plan_id', 'codigo', 'estado', 'proyecto', 'empresa', 'cliente', 'fecha_firma', 'contrato', 'propuesta', 'tabla', 'slides_foto', 'plan_actual', 'comentarios', 'crm_cliente_id', 'rubro'], '3) las claves del contrato de interfaces, en orden (más crm_cliente_id para los correos y rubro para las fotos)');
ok($x['ok'] === true && $x['plan_id'] === $pid && $x['estado'] === 'generando' && $x['proyecto'] === '[PRUEBA] Sitio de una página' && $x['empresa'] === '[PRUEBA] Empresa ' . $m && $x['fecha_firma'] === '2026-10-09' && $x['crm_cliente_id'] === $cli['crm'], '3) plan, estado, proyecto, empresa (nombre comercial de la propuesta), fecha de firma y cliente del CRM');
ok($x['rubro'] === '[PRUEBA] Panadería', '3) el rubro del cliente del CRM (las fotos nuevas se describen por rubro)');
ok(array_keys($x['contrato']) === ['servicios_contratados', 'alcance', 'entregables', 'fases_siguientes', 'plazo', 'garantia_meses'] && $x['contrato']['alcance'] === 'Sitio de una página con formulario.' && strpos($x['contrato']['servicios_contratados'], 'Sitio de una página') !== false && $x['contrato']['garantia_meses'] === 3, '3) lo contratado sale del contrato, con los meses de garantía (3 si el contrato no los dice)');
ok(is_array($x['propuesta']) && $x['propuesta']['company_name'] === '[PRUEBA] Empresa ' . $m && $x['propuesta']['solution_text'] === 'Sitio de una página con formulario de contacto.' && $x['propuesta']['how_it_works'] === ['Diseño: Maqueta de la portada', 'Publicación en tu dominio'], '3) de la propuesta: empresa, solución y pasos');
ok(mb_strlen($x['propuesta']['extracto_reunion']) === 3000 && strpos($x['propuesta']['extracto_reunion'], 'Hablamos del sitio.') === 0, '3) extracto de la reunión: los primeros 3000 caracteres');
ok($x['tabla'] === at_pt_duraciones_defecto() && $x['slides_foto'] === at_pt_slides_foto(), '3) tabla de tiempos y láminas con foto');
ok($x['plan_actual'] === null && $x['comentarios'] === '', '3) sin plan todavía: plan_actual null y sin comentarios');
at_pt_guardar($pid, ['payload' => ['version' => 1, 'fases' => []], 'comentarios' => 'Acorta el diseño']);
$x = at_pt_contexto(at_pt_plan($pid));
ok($x['plan_actual'] === ['version' => 1, 'fases' => []] && $x['comentarios'] === 'Acorta el diseño', '3) con plan: plan_actual y el último pedido de cambios');

// 4) Contexto sin propuesta (Review Focus 4).
$x2 = at_pt_contexto($f2);
ok(array_key_exists('propuesta', $x2) && $x2['propuesta'] === null && $x2['cliente'] === 'Cliente Prueba' && $x2['contrato']['alcance'] !== '' && $x2['rubro'] === '', '4) contrato sin propuesta y cliente sin rubro: propuesta null, rubro vacío y lo contratado igual llega');

fin();
```

- [ ] **Step 21: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/datos-contexto-wp-test.php; echo "exit=$?"
```
Expected:
```
FALLA falta la función at_pt_datos_render()

1 FALLAS
exit=1
```

- [ ] **Step 22: Implementar datos del render y contexto**

Agregar al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php`:
```php
/**
 * Meses de garantía del contrato (marcador garantia_meses_servicio, texto que Luis puede editar al revisar):
 * el primer número, si está entre 0 y 24; si no está o no se entiende, 3 (el valor por defecto del cierre).
 */
function at_pt_db_garantia(array $ph): int {
	if (preg_match('/\d+/', (string) ($ph['garantia_meses_servicio'] ?? ''), $m)) {
		$n = (int) $m[0];
		if ($n >= 0 && $n <= 24) {
			return $n;
		}
	}
	return 3;
}

/**
 * Todo lo que rodea a un plan: contrato y sus marcadores, ficha operativa, propuesta (o null), cliente
 * del CRM, garantía y los nombres ya resueltos. Empresa (lo que se muestra como company_name): el nombre
 * comercial de la propuesta si existe; si no, nombre_proyecto o razon_social_cliente del contrato; si no, el
 * nombre del cliente; nunca vacío. Cliente: quien firma por el cliente en el contrato, si no la propuesta o la
 * ficha.
 */
function at_pt_db_partes(object $fila): array {
	global $wpdb;
	$c = at_pt_db_contrato((int) $fila->contrato_id);
	$ph = $c ? json_decode((string) $c->placeholders, true) : null;
	$ph = is_array($ph) ? $ph : [];
	$tech = null;
	if ((int) $fila->tech_id > 0) {
		$tech = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_tech_clients WHERE id = %d", (int) $fila->tech_id)) ?: null;
	}
	$prop = null;
	if ((int) $fila->propuesta_id > 0) {
		$prop = $wpdb->get_row($wpdb->prepare(
			"SELECT id, unique_link_id, client_name, company_name, gamma_prompt_text, transcript_text FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d",
			(int) $fila->propuesta_id
		)) ?: null;
	}
	$primero = function (...$valores): string {
		foreach ($valores as $v) {
			$v = trim((string) $v);
			if ($v !== '') {
				return $v;
			}
		}
		return '';
	};
	$cliente = $primero($ph['representante_cliente_nombre'] ?? '', $ph['aceptante_nombre'] ?? '', $prop->client_name ?? '', $tech->name ?? '', $ph['razon_social_cliente'] ?? '');
	$cliente = $cliente !== '' ? $cliente : 'Cliente';
	// D9: el nombre comercial de la propuesta; si no, el del contrato (proyecto o razón social); si no, el cliente.
	$empresa = $primero($prop->company_name ?? '', $ph['nombre_proyecto'] ?? '', $ph['razon_social_cliente'] ?? '', $cliente);
	$firma = $c ? substr((string) $c->signed_at, 0, 10) : '';
	$crm_id = (int) ($tech->crm_cliente_id ?? 0);
	return [
		'contrato'       => $c,
		'ph'             => $ph,
		'tech'           => $tech,
		'propuesta'      => $prop,
		'crm_id'         => $crm_id > 0 ? $crm_id : (int) $fila->crm_cliente_id,
		'empresa'        => $empresa,
		'cliente'        => $cliente,
		'proyecto'       => $primero($ph['nombre_proyecto'] ?? '', $empresa),
		'fecha_firma'    => at_pt_db_fecha_valida($firma) ? $firma : current_time('Y-m-d'),
		'garantia_meses' => at_pt_db_garantia($ph),
	];
}

/**
 * Datos del cliente para at_pt_armar_render(): código, empresa, cliente, portal ('' sin correo), WhatsApp de AT,
 * fecha de firma y meses de garantía del contrato (el contrato manda sobre lo que diga el plan: D8).
 */
function at_pt_datos_render(object $fila): array {
	$x = at_pt_db_partes($fila);
	$portal = ($x['crm_id'] > 0 && function_exists('at_crm_url_portal')) ? (string) at_crm_url_portal($x['crm_id']) : '';
	$whatsapp = (string) preg_replace('/\D+/', '', function_exists('at_cc_whatsapp_at') ? at_cc_whatsapp_at() : '');
	return [
		'codigo'         => (string) $fila->codigo,
		'company_name'   => $x['empresa'],
		'client_name'    => $x['cliente'],
		'portal_url'     => $portal,
		'whatsapp'       => $whatsapp !== '' ? $whatsapp : '56927002984',
		'fecha_firma'    => $x['fecha_firma'],
		'garantia_meses' => $x['garantia_meses'],
	];
}

/**
 * Lo que recibe la IA (GET /plan/{id}/contexto). Lo contratado sale del contrato (ya revisado por
 * Luis); la propuesta, si existe, aporta solución, pasos y los primeros 3000 caracteres de la reunión;
 * el rubro sale de la ficha del CRM (las fotos nuevas se describen por rubro, también sin propuesta).
 */
function at_pt_contexto(object $fila): array {
	global $wpdb;
	$x = at_pt_db_partes($fila);
	$rubro = '';
	if ($x['crm_id'] > 0) {
		$rubro = trim((string) $wpdb->get_var($wpdb->prepare("SELECT rubro FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $x['crm_id'])));
	}
	$ph = $x['ph'];
	$campo = function (string $k) use ($ph): string {
		return mb_substr(trim((string) ($ph[$k] ?? '')), 0, 4000);
	};
	$propuesta = null;
	if ($x['propuesta']) {
		$p = $x['propuesta'];
		$d = function_exists('at_cc_json_de_payload') ? at_cc_json_de_payload((string) $p->gamma_prompt_text) : json_decode((string) $p->gamma_prompt_text, true);
		$d = is_array($d) ? $d : [];
		$pasos = [];
		foreach ((array) ($d['how_it_works'] ?? []) as $s) {
			if (is_array($s)) {
				$t = trim((string) ($s['step_title'] ?? ''));
				$tx = trim((string) ($s['step_text'] ?? ''));
				$linea = ($t !== '' && $tx !== '') ? $t . ': ' . $tx : $t . $tx;
			} else {
				$linea = is_scalar($s) ? trim((string) $s) : '';
			}
			if ($linea !== '') {
				$pasos[] = mb_substr($linea, 0, 500);
			}
			if (count($pasos) >= 12) {
				break;
			}
		}
		$propuesta = [
			'company_name'     => (string) $p->company_name,
			'solution_text'    => mb_substr(trim((string) ($d['solution_text'] ?? '')), 0, 4000),
			'how_it_works'     => $pasos,
			'extracto_reunion' => mb_substr((string) $p->transcript_text, 0, 3000),
		];
	}
	$plan = at_pt_payload($fila);
	return [
		'ok'          => true,
		'plan_id'     => (int) $fila->id,
		'codigo'      => (string) $fila->codigo,
		'estado'      => (string) $fila->estado,
		'proyecto'    => $x['proyecto'],
		'empresa'     => $x['empresa'],
		'cliente'     => $x['cliente'],
		'fecha_firma' => $x['fecha_firma'],
		'contrato'    => [
			'servicios_contratados' => $campo('servicios_contratados'),
			'alcance'               => $campo('alcance'),
			'entregables'           => $campo('entregables'),
			'fases_siguientes'      => $campo('fases_siguientes'),
			'plazo'                 => $campo('plazo'),
			// D7: meses de garantía del contrato (garantia_meses_servicio; 3 si no está). La IA los copia en soporte.
			'garantia_meses'        => $x['garantia_meses'],
		],
		'propuesta'   => $propuesta,
		'tabla'       => at_pt_duraciones(),
		'slides_foto' => at_pt_slides_foto(),
		'plan_actual' => $plan ?: null,
		'comentarios' => (string) ($fila->comentarios ?? ''),
		// Para el botón de los correos a Luis (pestaña del plan en la ficha del CRM); 0 si la ficha no está enlazada.
		'crm_cliente_id' => (int) $x['crm_id'],
		// Rubro de la ficha del CRM (crm_clientes.rubro); '' si no hay ficha enlazada o no lo tiene.
		'rubro'          => mb_substr($rubro, 0, 100),
	];
}
```

- [ ] **Step 23: Correr y ver pasar (y las cuatro pruebas de datos juntas)**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" -l wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php && "$PHP" tests/plan/datos-contexto-wp-test.php; echo "exit=$?"
for t in datos datos-guardar datos-ajustes datos-contexto; do printf '%-16s ' "$t"; "$PHP" "tests/plan/$t-wp-test.php" 2>&1 | tail -1; done
```
Expected: `No syntax errors detected`, 22 líneas `ok   …`, `TODO OK`, `exit=0`; y las cuatro líneas del ciclo terminan en `TODO OK`.

- [ ] **Step 24: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add tests/plan/datos-contexto-wp-test.php wp-content/themes/automatiza-tech/inc/plan-trabajo/datos.php
git commit -m "$(cat <<'EOF'
feat(plan): datos del cliente para el renderer y contexto para la IA desde el contrato (la propuesta es opcional; el rubro sale del CRM)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

### Task 6: Disparador al firmar y avisos a n8n (gancho en `contract-service.php`, `disparador.php`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/disparador.php`
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` (suma `require_once __DIR__ . '/disparador.php';`)
- Modify: `contracts/contract-service.php` — dentro de `sign_as_client()`, entre la línea 664
  (`ContractMailer::send_signed_copy_internal($fresh, $signed_path);`) y la 665 (`return $fresh;`)
- Test: `tests/plan/disparador-wp-test.php`, `tests/plan/firma-wp-test.php`, `tests/plan/firma-contrato-wp-test.php`
- Fuera del repo: `<SCR>/plan-trabajo/gancho-firma.py` (edita `contract-service.php` respetando CRLF) y
  `<SCR>/plan-trabajo/sin-red.php` (corre las pruebas del cierre sin ninguna llamada HTTP). El `wp-config.php` del sitio
  de prueba ya apunta los flujos del plan al simulador local desde la Task 0, Step 4b (`n8n-local.py`): esta tarea no lo
  repite.

**Interfaces:**
- Consumes (Task 5): `at_pt_plan(int $id): ?object`, `at_pt_plan_de_contrato(int $contrato_id): ?object`,
  `at_pt_crear_plan(int $contrato_id): int|WP_Error`, `at_pt_guardar(int $id, array $campos): bool`,
  `at_pt_cambiar_estado(int $id, string $a, string $nota = ''): bool`; del arnés, `pt_marca()`, `pt_cliente()`,
  `pt_propuesta()`, `pt_contrato()`, `pt_contrato_fila()`, `pt_archivo()`, `pt_plan_inexistente()` y `pt_llamadas()`
  (`tests/plan/datos-prueba.php`). (Tasks 1-4) `at_pt_transicion_valida(string $de, string $a): bool`.
  `ContractService::sign_as_client($token, $data)` (`contracts/contract-service.php:588-666`) y
  `ContractService::get_by_id($id)` (`:671`).
- Produces (`inc/plan-trabajo/disparador.php`):
  - Constantes con valor por defecto, sobrescribibles en `wp-config.php`: `AT_N8N_PLAN_BORRADOR`
    (`https://n8n-n8n.kchiba.easypanel.host/webhook/plan-v1-borrador`), `AT_N8N_PLAN_CAMBIOS` (`…/webhook/plan-v1-cambios`),
    `AT_N8N_PLAN_RENDER` (`…/webhook/plan-v1-render`)
  - `at_pt_llamar_n8n(string $url, array $cuerpo, int $timeout = 15): string` — POST JSON, `timeout` segundos (15 por
    defecto; mínimo 1), cabeceras `Content-Type: application/json` y `X-AT-Secret: AT_REST_SECRET`; `''` si 2xx; si no
    `n8n respondió HTTP <código>` o `n8n no respondió: <mensaje>` (o `AT_REST_SECRET no está configurado.`). El tercer
    parámetro es nuevo respecto del esqueleto (opcional, compatible)
  - `at_pt_iniciar_borrador(int $plan_id, int $timeout = 15): string` — cuerpo del webhook `{"id": <plan>, "codigo": "<12>"}`;
    deja el plan en `generando` (desde `error` es «Reintentar borrador»: pasa a `generando` y limpia la nota); si n8n no
    recibe el aviso y el plan sigue en `generando`, lo deja en `error` con la nota `No se pudo pedir el borrador: <motivo>`.
    El oyente de la firma la llama con 5 s: el webhook contesta al recibir (responseMode onReceived) y el cliente que
    firma no espera más que eso
  - `at_pt_pedir_render(int $plan_id, string $modo, bool $aviso): string` — cuerpo `{"id", "codigo", "modo": "draft"|"final",
    "aviso": bool}` (D6); si n8n no recibe el aviso: final con el plan en `aprobando` → `error`; si no, solo la nota
  - `at_pt_al_firmar(?object $contrato): void` — escucha `at_contrato_firmado` (prioridad 10); nunca lanza
  - Acción `do_action('at_contrato_firmado', $fresh)` en `sign_as_client()` (fila del contrato recién firmado, después de
    las dos copias por correo, dentro de `try/catch (\Throwable)`)
  - Para el panel (Task 9), con estas piezas: «Reintentar borrador» = `at_pt_iniciar_borrador($id)`; «Pedir cambios» =
    `at_pt_guardar($id, ['comentarios' => …])` + `at_pt_cambiar_estado($id, 'cambios')` +
    `at_pt_llamar_n8n(AT_N8N_PLAN_CAMBIOS, ['id' => $id, 'codigo' => $codigo])` (si devuelve motivo:
    `at_pt_cambiar_estado($id, 'error', 'No se pudo pedir los cambios: ' . $motivo)`); «Aprobar» =
    `at_pt_cambiar_estado($id, 'aprobando')` + `at_pt_pedir_render($id, 'final', true)`; «Guardar y recalcular» =
    `at_pt_guardar()` + `at_pt_pedir_render($id, 'draft', false)`

Review Focus que cubre esta tarea: 5 (firma repetida → un solo plan y un solo aviso; contrato que no es de servicios → nada;
n8n caído al firmar → la firma no falla y el plan queda en `error` con el motivo, listo para «Reintentar borrador») y 4
(contrato sin propuesta y cliente sin correo: igual tiene plan).

Ojo desde esta tarea: toda firma de un contrato de servicios avisa a la URL de `AT_N8N_PLAN_BORRADOR`. El `router.php` del
sitio `wp-local-plan` solo la redirige en el servidor web; por línea de comandos manda el valor de `wp-config.php` o, si no
lo define, el de defecto (n8n de PROD). Por eso la Task 0, Step 4b ya dejó `AT_N8N_PLAN_*` en el `wp-config.php` del sitio
de prueba apuntando al simulador local (`localhost:5203`, como el router): ninguna corrida por línea de comandos —las del
cierre, las de otros agentes o los scripts del e2e de la Task 15— le avisa al n8n de PROD. Además, las pruebas del plan
los fijan a `http://127.0.0.1:9/…` (`wp-bootstrap.php`, gana porque define antes) y las del cierre se corren con
`sin-red.php`, que no deja salir ninguna llamada HTTP (Step 15).

- [ ] **Step 0: (hecho en la Task 0, Step 4b)** Los flujos del plan del sitio de prueba ya van al simulador local por línea de
  comandos (`<SCR>/plan-trabajo/n8n-local.py` sobre `<SCR>/wp-local-plan/wp-config.php`). Si se duda, comprobarlo sin
  editar nada: `grep -c AT_N8N_PLAN_BORRADOR "$SCR/wp-local-plan/wp-config.php"` → `1`. Si da `0`, volver a la Task 0,
  Step 4b antes de la primera firma de esta tarea.

- [ ] **Step 1: Escribir la prueba que falla (avisos a n8n)**

Create `tests/plan/disparador-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/disparador-wp-test.php
// Task 6: avisos WordPress → n8n (sin red: pre_http_request los anota y responde lo que diga la prueba).
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_llamar_n8n', 'at_pt_iniciar_borrador', 'at_pt_pedir_render');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: se define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
require __DIR__ . '/datos-prueba.php';
$caido = new WP_Error('http_request_failed', 'cURL error 7: Failed to connect');

// 1) URL de los tres flujos (sobrescribibles en wp-config.php).
ok(preg_match('#/plan-v1-borrador$#', AT_N8N_PLAN_BORRADOR) && preg_match('#/plan-v1-cambios$#', AT_N8N_PLAN_CAMBIOS) && preg_match('#/plan-v1-render$#', AT_N8N_PLAN_RENDER), '1) constantes de los webhooks plan-v1-borrador, plan-v1-cambios y plan-v1-render');

// 2) at_pt_llamar_n8n().
$GLOBALS['pt_http_respuesta'] = 200;
$motivo = at_pt_llamar_n8n(AT_N8N_PLAN_RENDER, ['id' => 7, 'modo' => 'draft', 'aviso' => true]);
$ll = end($GLOBALS['pt_http']);
ok($motivo === '', '2) n8n responde 2xx: sin motivo');
ok($ll && $ll['url'] === AT_N8N_PLAN_RENDER && ($ll['args']['method'] ?? '') === 'POST' && (int) ($ll['args']['timeout'] ?? 0) === 15, '2) POST al flujo con 15 s de espera');
ok($ll && ($ll['args']['headers']['X-AT-Secret'] ?? '') === AT_REST_SECRET && ($ll['args']['headers']['Content-Type'] ?? '') === 'application/json', '2) con la clave X-AT-Secret y cuerpo JSON');
ok($ll && $ll['cuerpo'] === ['id' => 7, 'modo' => 'draft', 'aviso' => true], '2) el cuerpo llega tal cual');
$GLOBALS['pt_http_respuesta'] = 500;
ok(at_pt_llamar_n8n(AT_N8N_PLAN_RENDER, ['id' => 7]) === 'n8n respondió HTTP 500', '2) HTTP 500: el motivo lo dice');
$GLOBALS['pt_http_respuesta'] = $caido;
ok(at_pt_llamar_n8n(AT_N8N_PLAN_RENDER, ['id' => 7]) === 'n8n no respondió: cURL error 7: Failed to connect', '2) n8n caído: el motivo lo dice');

// 3) at_pt_iniciar_borrador().
$m = pt_marca();
$cli = pt_cliente($m);
$pid = at_pt_crear_plan(pt_contrato($cli['tech'], null));
$GLOBALS['pt_http'] = [];
$GLOBALS['pt_http_respuesta'] = 200;
ok(at_pt_iniciar_borrador($pid) === '' && at_pt_plan($pid)->estado === 'generando', '3) pide el borrador y el plan sigue en «generando»');
$b = pt_llamadas('borrador');
ok(count($b) === 1 && $b[0]['cuerpo'] === ['id' => $pid, 'codigo' => (string) at_pt_plan($pid)->codigo], '3) una llamada al flujo 1 con el id y el código del plan');
$GLOBALS['pt_http_respuesta'] = $caido;
$motivo = at_pt_iniciar_borrador($pid);
$f = at_pt_plan($pid);
ok($motivo !== '' && $f->estado === 'error' && strpos((string) $f->nota, 'n8n no respondió') !== false, '3) n8n caído: el plan queda en «error» con el motivo (Review Focus 5)');
$GLOBALS['pt_http_respuesta'] = 200;
ok(at_pt_iniciar_borrador($pid) === '' && at_pt_plan($pid)->estado === 'generando' && at_pt_plan($pid)->nota === '', '3) «Reintentar borrador»: desde «error» vuelve a «generando» y limpia la nota');
at_pt_guardar($pid, ['estado' => 'borrador', 'payload' => ['version' => 1]]);
$antes = count($GLOBALS['pt_http']);
ok(at_pt_iniciar_borrador($pid) !== '' && at_pt_plan($pid)->estado === 'borrador' && count($GLOBALS['pt_http']) === $antes, '3) con un borrador hecho no se pide otro (borrador → generando no vale)');
ok(at_pt_iniciar_borrador(pt_plan_inexistente()) === 'El plan no existe.', '3) plan que no existe: motivo');

// 4) at_pt_pedir_render().
$GLOBALS['pt_http'] = [];
ok(at_pt_pedir_render($pid, 'draft', true) === '' && (pt_llamadas('render')[0]['cuerpo'] ?? null) === ['id' => $pid, 'codigo' => (string) at_pt_plan($pid)->codigo, 'modo' => 'draft', 'aviso' => true], '4) pide la vista previa con aviso a Luis (id, código, modo y aviso)');
$GLOBALS['pt_http_respuesta'] = 500;
ok(at_pt_pedir_render($pid, 'draft', false) === 'n8n respondió HTTP 500' && at_pt_plan($pid)->estado === 'borrador' && strpos((string) at_pt_plan($pid)->nota, 'vista previa') !== false, '4) vista previa sin n8n: el estado no cambia y queda la nota');
at_pt_guardar($pid, ['estado' => 'aprobando']);
ok(at_pt_pedir_render($pid, 'final', true) !== '' && at_pt_plan($pid)->estado === 'error' && strpos((string) at_pt_plan($pid)->nota, 'versión final') !== false, '4) versión final sin n8n: «aprobando» pasa a «error» con el motivo');
$GLOBALS['pt_http_respuesta'] = 200;
$antes = count($GLOBALS['pt_http']);
ok(at_pt_pedir_render($pid, 'otro', false) === 'Modo de render inválido.' && count($GLOBALS['pt_http']) === $antes, '4) modo desconocido: no llama a n8n');

fin();
```

- [ ] **Step 2: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/disparador-wp-test.php; echo "exit=$?"
```
Expected:
```
FALLA falta la función at_pt_llamar_n8n()

1 FALLAS
exit=1
```

- [ ] **Step 3: Crear `disparador.php` (constantes, llamar a n8n, pedir borrador y render)**

Create `wp-content/themes/automatiza-tech/inc/plan-trabajo/disparador.php`:
```php
<?php
/**
 * Plan de trabajo: disparador al firmar el contrato y avisos a los flujos de n8n.
 * WordPress → n8n con la cabecera X-AT-Secret (AT_REST_SECRET), igual que at_v3_llamar_n8n(): no hay
 * secreto nuevo. Las URL se pueden cambiar en wp-config.php (el sitio de prueba local las apunta a su
 * simulador).
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!defined('AT_N8N_PLAN_BORRADOR')) {
	define('AT_N8N_PLAN_BORRADOR', 'https://n8n-n8n.kchiba.easypanel.host/webhook/plan-v1-borrador');
}
if (!defined('AT_N8N_PLAN_CAMBIOS')) {
	define('AT_N8N_PLAN_CAMBIOS', 'https://n8n-n8n.kchiba.easypanel.host/webhook/plan-v1-cambios');
}
if (!defined('AT_N8N_PLAN_RENDER')) {
	define('AT_N8N_PLAN_RENDER', 'https://n8n-n8n.kchiba.easypanel.host/webhook/plan-v1-render');
}

/**
 * Avisa a un flujo de n8n con un cuerpo JSON; '' si respondió 2xx, si no el motivo en palabras. $timeout en
 * segundos (15 por defecto): los webhooks del plan contestan al recibir, así que la espera es solo la de la red.
 */
function at_pt_llamar_n8n(string $url, array $cuerpo, int $timeout = 15): string {
	if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
		return 'AT_REST_SECRET no está configurado.';
	}
	$r = wp_remote_post($url, [
		'timeout' => max(1, $timeout),
		'headers' => ['Content-Type' => 'application/json', 'X-AT-Secret' => AT_REST_SECRET],
		'body'    => wp_json_encode($cuerpo),
	]);
	if (is_wp_error($r)) {
		return 'n8n no respondió: ' . $r->get_error_message();
	}
	$code = (int) wp_remote_retrieve_response_code($r);
	return ($code >= 200 && $code < 300) ? '' : "n8n respondió HTTP {$code}";
}

/**
 * Pide el borrador al flujo «Plan de trabajo · 1 Borrador» (cuerpo {id, codigo}). Deja el plan en
 * «generando»; desde «error» sirve de «Reintentar borrador». Si n8n no recibe el aviso y el plan sigue
 * en «generando», queda en «error» con el motivo. $timeout: segundos de espera del aviso (el oyente de la
 * firma usa 5 para no retener al cliente). Devuelve '' o el motivo.
 */
function at_pt_iniciar_borrador(int $plan_id, int $timeout = 15): string {
	$f = at_pt_plan($plan_id);
	if (!$f) {
		return 'El plan no existe.';
	}
	if ((string) $f->estado !== 'generando' && !at_pt_cambiar_estado($plan_id, 'generando', '')) {
		return 'El plan está en «' . $f->estado . '»: no se puede pedir un borrador nuevo.';
	}
	$motivo = at_pt_llamar_n8n(AT_N8N_PLAN_BORRADOR, ['id' => (int) $f->id, 'codigo' => (string) $f->codigo], $timeout);
	if ($motivo !== '') {
		$ahora = at_pt_plan($plan_id);
		// Si n8n alcanzó a contestar con el borrador (el plan ya no está en «generando»), no se pisa.
		if ($ahora && (string) $ahora->estado === 'generando') {
			at_pt_cambiar_estado($plan_id, 'error', 'No se pudo pedir el borrador: ' . $motivo);
		}
	}
	return $motivo;
}

/**
 * Pide la vista previa ($modo 'draft') o la versión final ('final') al flujo «Plan de trabajo · 3 Render»
 * (cuerpo {id, codigo, modo, aviso}; con $aviso, n8n le escribe a Luis cuando está lista). Si n8n no recibe el
 * aviso: en la final con el plan «aprobando», el plan pasa a «error»; si no, solo queda la nota y el
 * estado no cambia. Devuelve '' o el motivo.
 */
function at_pt_pedir_render(int $plan_id, string $modo, bool $aviso): string {
	if (!in_array($modo, ['draft', 'final'], true)) {
		return 'Modo de render inválido.';
	}
	$f = at_pt_plan($plan_id);
	if (!$f) {
		return 'El plan no existe.';
	}
	$motivo = at_pt_llamar_n8n(AT_N8N_PLAN_RENDER, ['id' => (int) $f->id, 'codigo' => (string) $f->codigo, 'modo' => $modo, 'aviso' => $aviso]);
	if ($motivo === '') {
		return '';
	}
	$que = $modo === 'final' ? 'la versión final' : 'la vista previa';
	$ahora = at_pt_plan($plan_id);
	if ($modo === 'final' && $ahora && (string) $ahora->estado === 'aprobando') {
		at_pt_cambiar_estado($plan_id, 'error', 'No se pudo pedir ' . $que . ': ' . $motivo);
	} else {
		at_pt_guardar($plan_id, ['nota' => 'No se pudo pedir ' . $que . ': ' . $motivo]);
	}
	return $motivo;
}
```

- [ ] **Step 4: Cargarlo desde `cargar.php`**

Se agrega una línea al final (sin reescribir el archivo: las Tasks 8 y 9 también le agregan líneas, y repetir el paso no
duplica nada ni borra lo que otras tareas pusieron):
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
F=wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php
L="require_once __DIR__ . '/disparador.php';"
[ "$(tail -c1 "$F" | wc -l)" -eq 1 ] || echo >> "$F"
grep -qxF "$L" "$F" || printf '%s\n' "$L" >> "$F"
"$PHP" -l "$F" && tail -3 "$F"
```
Expected: `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` y las tres últimas
líneas:
```php
require_once __DIR__ . '/puras.php';
require_once __DIR__ . '/datos.php';
require_once __DIR__ . '/disparador.php';
```

- [ ] **Step 5: Correr y ver pasar**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
for f in cargar disparador; do "$PHP" -l "wp-content/themes/automatiza-tech/inc/plan-trabajo/$f.php"; done
"$PHP" tests/plan/disparador-wp-test.php; echo "exit=$?"
```
Expected: dos `No syntax errors detected`, 17 líneas `ok   …`, `TODO OK`, `exit=0`. Si sale con código 2 y
`AT_REST_SECRET no está definido…`, el `wp-config.php` del sitio local no trae la clave de prueba (Task 0): no seguir.

- [ ] **Step 6: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add tests/plan/disparador-wp-test.php wp-content/themes/automatiza-tech/inc/plan-trabajo/disparador.php wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php
git commit -m "$(cat <<'EOF'
feat(plan): avisos a los flujos n8n del plan con X-AT-Secret; si n8n no recibe el borrador, el plan queda en error con el motivo

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 7: Escribir la prueba que falla (el módulo escucha la firma)**

Create `tests/plan/firma-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/firma-wp-test.php
// Task 6: el módulo escucha at_contrato_firmado (lo dispara ContractService::sign_as_client()).
// Aquí el gancho se dispara con do_action(); la firma real está en firma-contrato-wp-test.php.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_al_firmar');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: se define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
require __DIR__ . '/datos-prueba.php';
global $wpdb;

ok(has_action('at_contrato_firmado', 'at_pt_al_firmar') === 10, '0) el módulo escucha at_contrato_firmado');

// 1) Contrato de servicios firmado: plan en «generando» y aviso al flujo 1.
$m = pt_marca();
$cli = pt_cliente($m);
$cid = pt_contrato($cli['tech'], pt_propuesta($m));
$GLOBALS['pt_http'] = [];
$GLOBALS['pt_http_respuesta'] = 200;
do_action('at_contrato_firmado', pt_contrato_fila($cid));
$f = at_pt_plan_de_contrato($cid);
ok($f && $f->estado === 'generando', '1) contrato de servicios firmado: crea el plan en «generando»');
ok(count(pt_llamadas('borrador')) === 1 && (pt_llamadas('borrador')[0]['cuerpo']['id'] ?? 0) === ($f ? (int) $f->id : -1), '1) y le pide el borrador a n8n una vez');
ok((int) (pt_llamadas('borrador')[0]['args']['timeout'] ?? 0) === 5, '1) al firmar, el aviso a n8n no retiene al cliente más de 5 s');

// 2) Firma repetida (Review Focus 5).
do_action('at_contrato_firmado', pt_contrato_fila($cid));
ok((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . at_pt_tabla() . ' WHERE contrato_id = %d', $cid)) === 1 && count(pt_llamadas('borrador')) === 1, '2) firma repetida: un solo plan y ningún aviso nuevo');

// 3) Contrato que no es de servicios (Review Focus 5).
$sop = pt_contrato($cli['tech'], null, ['type' => 'soporte']);
do_action('at_contrato_firmado', pt_contrato_fila($sop));
ok(!at_pt_plan_de_contrato($sop) && count(pt_llamadas('borrador')) === 1, '3) contrato de soporte: ni plan ni aviso');

// 4) Manda la base: un objeto que dice «firmado» de un contrato que en la base no lo está.
$sin = pt_contrato($cli['tech'], null, ['status' => 'sent']);
$viejo = pt_contrato_fila($sin);
$viejo->status = 'signed';
do_action('at_contrato_firmado', $viejo);
ok(!at_pt_plan_de_contrato($sin) && count(pt_llamadas('borrador')) === 1, '4) si en la base el contrato no está firmado, no hay plan');

// 5) Nunca lanza.
$lanzo = false;
try {
	at_pt_al_firmar(null);
	at_pt_al_firmar(new WP_Error('x', 'no es un contrato'));
	at_pt_al_firmar((object) ['id' => 'abc', 'type' => 'servicios']);
} catch (\Throwable $e) {
	$lanzo = true;
}
ok(!$lanzo, '5) con datos raros (null, un error, un id que no es número) no lanza');

// 6) n8n caído al firmar, contrato sin propuesta y cliente sin correo (Review Focus 4 y 5).
$m2 = pt_marca();
$cli2 = pt_cliente($m2, false);
$cid2 = pt_contrato($cli2['tech'], null);
$GLOBALS['pt_http_respuesta'] = new WP_Error('http_request_failed', 'cURL error 28: Operation timed out');
do_action('at_contrato_firmado', pt_contrato_fila($cid2));
$f2 = at_pt_plan_de_contrato($cid2);
ok($f2 && $f2->estado === 'error' && strpos((string) $f2->nota, 'n8n no respondió') !== false, '6) n8n caído: el plan queda en «error» con el motivo, listo para «Reintentar borrador»');
ok($f2 && $f2->propuesta_id === null, '6) contrato sin propuesta y cliente sin correo: igual tiene plan');

fin();
```

- [ ] **Step 8: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/firma-wp-test.php; echo "exit=$?"
```
Expected:
```
FALLA falta la función at_pt_al_firmar()

1 FALLAS
exit=1
```

- [ ] **Step 9: Implementar el oyente de la firma**

Agregar al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/disparador.php`:
```php
/**
 * Escucha at_contrato_firmado, que ContractService::sign_as_client() dispara después de mandar las
 * copias. Contrato de servicios sin plan → crea el plan y pide el borrador. Firma repetida → nada.
 * Nunca lanza: un fallo del plan no afecta la firma (queda en el log y, si n8n no recibe el aviso,
 * el plan en «error», desde donde el panel ofrece «Reintentar borrador»). El aviso espera a lo más 5 s:
 * va dentro de la petición en que el cliente firma y el webhook contesta apenas lo recibe.
 */
function at_pt_al_firmar(?object $contrato): void {
	try {
		if (!$contrato || (string) ($contrato->type ?? '') !== 'servicios') {
			return;
		}
		$cid = (int) ($contrato->id ?? 0);
		if ($cid <= 0 || at_pt_plan_de_contrato($cid)) {
			return;
		}
		$id = at_pt_crear_plan($cid);
		if (is_wp_error($id)) {
			error_log('at_pt_al_firmar (contrato ' . $cid . '): ' . $id->get_error_message());
			return;
		}
		at_pt_iniciar_borrador($id, 5);
	} catch (\Throwable $e) {
		error_log('at_pt_al_firmar: ' . $e->getMessage());
	}
}
add_action('at_contrato_firmado', 'at_pt_al_firmar');
```

- [ ] **Step 10: Correr y ver pasar**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" -l wp-content/themes/automatiza-tech/inc/plan-trabajo/disparador.php && "$PHP" tests/plan/firma-wp-test.php; echo "exit=$?"
```
Expected: `No syntax errors detected`, 10 líneas `ok   …`, `TODO OK`, `exit=0`.

- [ ] **Step 11: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add tests/plan/firma-wp-test.php wp-content/themes/automatiza-tech/inc/plan-trabajo/disparador.php
git commit -m "$(cat <<'EOF'
feat(plan): el módulo escucha at_contrato_firmado: un contrato de servicios firmado, un plan y un aviso a n8n

Firma repetida, contratos de soporte o sin firma en la base no crean nada; el oyente nunca lanza.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 12: Escribir la prueba que falla (firma real con `sign_as_client`)**

Create `tests/plan/firma-contrato-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/firma-contrato-wp-test.php
// Task 6: la firma real del cliente (ContractService::sign_as_client) dispara el plan y nunca falla por él.
require __DIR__ . '/wp-bootstrap.php';
require_once ABSPATH . 'contracts/contract-service.php';
exigir('at_pt_al_firmar');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: se define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
require __DIR__ . '/datos-prueba.php';

// PNG de 1x1 (el formato de la firma dibujada).
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

/** Firma el contrato como el cliente y anota el PDF firmado y la imagen de la firma para borrarlos al final. */
function pt_firmar(int $cid, string $png) {
	$c = ContractService::get_by_id($cid);
	$r = ContractService::sign_as_client((string) $c->sign_token, ['signer_name' => 'Cliente Prueba', 'signer_rut' => '11.111.111-1', 'signer_email' => 'prueba-plan-firma@example.com', 'method' => 'canvas', 'signature_dataurl' => $png]);
	$x = ContractService::get_by_id($cid);
	if ($x) {
		pt_archivo(ContractService::storage_dir() . '/' . $x->contract_number . '-FIRMADO.pdf');
		$up = wp_upload_dir();
		$firma = str_replace($up['baseurl'], $up['basedir'], (string) $x->signature_image_url);
		if (preg_match('#/signatures/sig-client-[0-9a-f]+\.(png|jpeg)$#', $firma)) {
			pt_archivo($firma);
		}
	}
	return $r;
}

// 0) Respaldo textual: el gancho está en contract-service.php, en try/catch y después de las copias por correo.
// Lo que importa (orden y fila ya firmada) lo prueba el espía de la prueba 2 por comportamiento.
$fuente = (string) file_get_contents(ABSPATH . 'contracts/contract-service.php');
$patron = '/send_signed_copy_internal\(\$fresh, \$signed_path\);\s*(\/\/[^\n]*\n\s*)?try \{\s*do_action\(\'at_contrato_firmado\', \$fresh\);\s*\} catch \(\\\\Throwable \$e\) \{\s*error_log\(\'at_contrato_firmado: \' \. \$e->getMessage\(\)\);\s*\}\s*return \$fresh;/';
ok(preg_match($patron, $fuente) === 1, '0) contract-service.php dispara at_contrato_firmado en try/catch, después de las copias');

// 1) Firma real de un contrato de servicios. Un espía anota cómo llega el gancho: estado del contrato y
// cuántos correos habían salido (sign_as_client manda dos: la copia al cliente y la interna).
$m = pt_marca();
$cli = pt_cliente($m);
$cid = pt_contrato($cli['tech'], pt_propuesta($m), ['status' => 'sent']);
$GLOBALS['pt_http'] = [];
$GLOBALS['pt_http_respuesta'] = 200;
$GLOBALS['pt_correos'] = [];
$visto = null;
$espia = function ($c) use (&$visto) { $visto = ['correos' => count($GLOBALS['pt_correos']), 'status' => (string) ($c->status ?? '')]; };
add_action('at_contrato_firmado', $espia, 1);
$r = pt_firmar($cid, $png);
remove_action('at_contrato_firmado', $espia, 1);
ok(!is_wp_error($r) && $r->status === 'signed', '1) el cliente firma como siempre' . (is_wp_error($r) ? ' (' . $r->get_error_code() . ')' : ''));
ok($visto !== null && $visto['status'] === 'signed' && $visto['correos'] === 2, '2) el gancho corre con el contrato ya firmado y después de las dos copias por correo');
$f = at_pt_plan_de_contrato($cid);
ok($f && $f->estado === 'generando', '3) la firma crea el plan en «generando»');
$b = pt_llamadas('borrador');
ok(count($b) === 1 && ($b[0]['cuerpo']['id'] ?? 0) === ($f ? (int) $f->id : -1), '4) y avisa al flujo 1 de n8n con el id del plan');

// 2) n8n caído al firmar (Review Focus 5).
$cid2 = pt_contrato($cli['tech'], null, ['status' => 'sent']);
$GLOBALS['pt_http_respuesta'] = new WP_Error('http_request_failed', 'cURL error 7: Failed to connect');
$r2 = pt_firmar($cid2, $png);
ok(!is_wp_error($r2) && $r2->status === 'signed', '5) n8n caído: la firma no falla');
$f2 = at_pt_plan_de_contrato($cid2);
ok($f2 && $f2->estado === 'error' && strpos((string) $f2->nota, 'n8n') !== false, '6) n8n caído: el plan queda en «error» con el motivo');

// 3) Algo del plan lanza una excepción: la firma igual termina.
$GLOBALS['pt_http_respuesta'] = 200;
$lanzador = function () { throw new RuntimeException('falla simulada del plan'); };
add_action('at_contrato_firmado', $lanzador, 1);
$r3 = pt_firmar(pt_contrato($cli['tech'], null, ['status' => 'sent']), $png);
remove_action('at_contrato_firmado', $lanzador, 1);
ok(!is_wp_error($r3) && $r3->status === 'signed', '7) si el plan lanza, la firma igual termina');

// 4) Contrato de soporte: se firma y no tiene plan.
$cid4 = pt_contrato($cli['tech'], null, ['type' => 'soporte', 'status' => 'sent']);
$r4 = pt_firmar($cid4, $png);
ok(!is_wp_error($r4) && $r4->status === 'signed' && !at_pt_plan_de_contrato($cid4), '8) un contrato de soporte se firma y no tiene plan');

fin();
```

- [ ] **Step 13: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/firma-contrato-wp-test.php; echo "exit=$?"
```
Expected (el oyente existe pero `sign_as_client()` todavía no dispara el gancho: el espía nunca corre):
```
FALLA 0) contract-service.php dispara at_contrato_firmado en try/catch, después de las copias
ok   1) el cliente firma como siempre
FALLA 2) el gancho corre con el contrato ya firmado y después de las dos copias por correo
FALLA 3) la firma crea el plan en «generando»
FALLA 4) y avisa al flujo 1 de n8n con el id del plan
ok   5) n8n caído: la firma no falla
FALLA 6) n8n caído: el plan queda en «error» con el motivo
ok   7) si el plan lanza, la firma igual termina
ok   8) un contrato de soporte se firma y no tiene plan

5 FALLAS
exit=1
```

- [ ] **Step 14: El gancho en `contracts/contract-service.php`**

`contract-service.php` está en CRLF. Crear `<SCR>/plan-trabajo/gancho-firma.py` con la herramienta Write (no con un heredoc:
la barra invertida de `\Throwable` se arma con `chr(92)` para que nada la coma):
```python
"""Task 6, Step: gancho at_contrato_firmado en contracts/contract-service.php (respeta CRLF)."""
import sys

BS = chr(92)  # barra invertida: no se escribe literal para que ningún heredoc ni shell la coma
p = sys.argv[1] if len(sys.argv) > 1 else 'contracts/contract-service.php'
t = open(p, encoding='utf-8', newline='').read()
eol = '\r\n' if '\r\n' in t else '\n'
ancla = ('        ContractMailer::send_signed_copy_internal($fresh, $signed_path);' + eol
         + '        return $fresh;' + eol)
assert t.count(ancla) == 1, 'el final de sign_as_client() cambió: revisar a mano'
assert 'at_contrato_firmado' not in t, 'el gancho ya está'
nuevo = ('        ContractMailer::send_signed_copy_internal($fresh, $signed_path);' + eol
         + '        // Plan de trabajo (inc/plan-trabajo/): un fallo del plan nunca afecta la firma.' + eol
         + '        try {' + eol
         + "            do_action('at_contrato_firmado', $fresh);" + eol
         + '        } catch (' + BS + 'Throwable $e) {' + eol
         + "            error_log('at_contrato_firmado: ' . $e->getMessage());" + eol
         + '        }' + eol
         + '        return $fresh;' + eol)
assert nuevo.count(BS) == 1 and (BS + 'Throwable') in nuevo
t = t.replace(ancla, nuevo, 1)
open(p, 'w', encoding='utf-8', newline='').write(t)
print('contract-service.php ok,', 'CRLF' if eol == '\r\n' else 'LF')
```
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad
python "$SCR/plan-trabajo/gancho-firma.py" && "$PHP" -l contracts/contract-service.php && git diff --stat contracts/contract-service.php
```
Expected: `contract-service.php ok, CRLF`, `No syntax errors detected in contracts/contract-service.php` y
`1 file changed, 6 insertions(+)`. El final de `sign_as_client()` queda así:
```php
        ContractMailer::send_signed_copy_internal($fresh, $signed_path);
        // Plan de trabajo (inc/plan-trabajo/): un fallo del plan nunca afecta la firma.
        try {
            do_action('at_contrato_firmado', $fresh);
        } catch (\Throwable $e) {
            error_log('at_contrato_firmado: ' . $e->getMessage());
        }
        return $fresh;
```

- [ ] **Step 15: Correr y ver pasar; el cierre sigue igual (sin red)**

Crear `<SCR>/plan-trabajo/sin-red.php` (fuera del repo; con Write):
```php
<?php
// Corre una prueba WordPress por línea de comandos sin ninguna llamada HTTP: los flujos del plan apuntan a un
// puerto local cerrado y toda llamada (también a localhost) se corta antes de abrir una conexión, así el
// resultado no depende de que algo escuche en ese puerto ni de cuánto tarde Windows en rechazarla.
// Uso: AT_WP_LOAD=<wp-load.php> php sin-red.php tests/cierre/contrato-wp-test.php
foreach (['AT_N8N_PLAN_BORRADOR' => 'plan-v1-borrador', 'AT_N8N_PLAN_CAMBIOS' => 'plan-v1-cambios', 'AT_N8N_PLAN_RENDER' => 'plan-v1-render'] as $c => $p) {
	define($c, 'http://127.0.0.1:9/' . $p);
}
$GLOBALS['wp_filter']['pre_http_request'][1][] = ['function' => function ($pre, $args, $url) {
	$host = (string) parse_url((string) $url, PHP_URL_HOST);
	return new WP_Error('bloqueado_en_prueba', 'Llamada HTTP bloqueada por sin-red.php: ' . $host);
}, 'accepted_args' => 3];
$prueba = $argv[1] ?? '';
if (!is_file($prueba)) {
	fwrite(STDERR, "Uso: php sin-red.php <ruta de la prueba>\n");
	exit(2);
}
$argv = array_slice($argv, 1);
require $prueba;
```
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/firma-contrato-wp-test.php; echo "exit=$?"
printf '%-26s ' contrato; "$PHP" "$SCR/plan-trabajo/sin-red.php" tests/cierre/contrato-wp-test.php 2>/dev/null | tail -1
for t in datos datos-guardar datos-ajustes datos-contexto disparador firma; do printf '%-16s ' "$t"; "$PHP" "tests/plan/$t-wp-test.php" 2>&1 | tail -1; done
```
Expected: 9 líneas `ok   …`, `TODO OK`, `exit=0`; la prueba del cierre que firma como el cliente
(`tests/cierre/contrato-wp-test.php`, la única que llama a `sign_as_client()`) termina en `TODO OK` (sus firmas de contratos de
servicios ahora crean planes que quedan en `error` porque `sin-red.php` corta el aviso: son locales, esa prueba borra sus
contratos al terminar y `pt_limpiar()` borra los planes que quedan sin contrato en la próxima corrida del plan); las seis
del plan, `TODO OK`.

- [ ] **Step 16: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add tests/plan/firma-contrato-wp-test.php contracts/contract-service.php
git commit -m "$(cat <<'EOF'
feat(contratos): sign_as_client() dispara at_contrato_firmado en try/catch después de las copias; la firma nunca falla por el plan

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

### Task 7: Rutas REST para n8n (`rest.php`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php`
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` (suma `require_once __DIR__ . '/rest.php';`)
- Modify: `tests/plan/datos-prueba.php` (se le agregan al final `pt_pedir()` en el Step 1 y `pt_plan_ia()`, `pt_actividades()`,
  `pt_actividad()` y `pt_editar_actividad()` en el Step 7, junto con las pruebas que las usan)
- Test: `tests/plan/rest-contexto-wp-test.php`, `tests/plan/rest-borrador-wp-test.php`, `tests/plan/rest-render-wp-test.php`;
  regresión del cierre `tests/cierre/rest-wp-test.php` y `tests/cierre/contrato-wp-test.php` (mismo namespace
  `automatiza-tech/v1`; se corren con `<SCR>/plan-trabajo/sin-red.php`, Task 6)

**Interfaces:**
- Consumes:
  - Task 5 (`inc/plan-trabajo/datos.php`): `at_pt_plan(int $id): ?object`, `at_pt_payload(object $fila): array`,
    `at_pt_guardar(int $id, array $campos): bool`, `at_pt_cambiar_estado(int $id, string $a, string $nota = ''): bool`,
    `at_pt_contexto(object $fila): array`, `at_pt_datos_render(object $fila): array`, `at_pt_db_partes(object $fila): array`
    (su `crm_id`), `at_pt_duraciones(): array`, `at_pt_feriados(): array`, `at_pt_db_fecha_valida(string $f): bool`,
    `at_pt_db_url($u): string`, `at_pt_crear_plan(int $contrato_id): int|WP_Error`; del arnés, `pt_marca()`, `pt_cliente()`,
    `pt_propuesta()`, `pt_contrato()`, `pt_plan_inexistente()` y `pt_llamadas()`.
  - Task 6 (`inc/plan-trabajo/disparador.php`): `at_pt_pedir_render(int $plan_id, string $modo, bool $aviso): string`;
    `<SCR>/plan-trabajo/sin-red.php` para la regresión del cierre.
  - Tasks 1-4 (`inc/plan-trabajo/puras.php`, Grupo A): `at_pt_validar_entrada(mixed $plan, bool $borrador = false): array`
    (→ `['ok','errores','avisos','plan']`; acepta el objeto o el texto de la IA —con o sin cerco ```` ```json ````—, así que la
    ruta no decodifica nada por su cuenta; con `$borrador` quita el «Arranque» que mande la IA para que entre el fijo),
    `at_pt_validar_plan(array $plan): array` (→ `['ok','errores','avisos','plan']`; con errores, `plan` es `[]`; tope
    `AT_PT_MAX_DIAS_PLAN = 130` días hábiles con las revisiones), `at_pt_aplicar_tabla(array $plan, array $tabla): array`,
    `at_pt_respetar_dias_luis(array $anterior, array $nuevo): array`, `at_pt_luis_perdidas(array $anterior, array $nuevo): array`,
    `at_pt_marcar_ediciones(array $anterior, array $nuevo, string $marca = 'luis'): array`,
    `at_pt_calcular_fechas(array $plan, string $inicio, array $feriados): array`,
    `at_pt_inicio_por_defecto(string $fecha_firma, array $feriados): string`, `at_pt_es_habil(string $f, array $feriados): bool`,
    `at_pt_siguiente_habil(string $f, array $feriados): string`, `at_pt_armar_render(array $plan, array $datos, bool $final): array`,
    `at_pt_transicion_valida(string $de, string $a): bool`, `at_pt_duraciones_defecto(): array`.
  - Del código existente: `automatiza_proposals_rest_auth(WP_REST_Request $r)` (`inc/rest-proposals.php:27-54`: sin
    cabecera 401, clave mala 403, sin `AT_REST_SECRET` 500).
  - **Orden de uso único** de la ruta del borrador (Grupo A, Task 2; esqueleto D2): borrador =
    `at_pt_validar_entrada($p['plan'] ?? null, true)` → `at_pt_respetar_dias_luis($anterior, …)` → `at_pt_aplicar_tabla` →
    `at_pt_validar_plan` otra vez → `at_pt_calcular_fechas`; cambios = `at_pt_validar_entrada($p['plan'] ?? null, false)` →
    `at_pt_respetar_dias_luis` → avisos + `at_pt_luis_perdidas` → `at_pt_marcar_ediciones(…, 'ia')` → `at_pt_validar_plan`
    otra vez → `at_pt_calcular_fechas`. Si la segunda validación falla: `error` con nota legible y HTTP 422.
- Produces (namespace `automatiza-tech/v1`, todas con `permission_callback => 'automatiza_proposals_rest_auth'`; lo que
  consumen los flujos n8n de las Tasks 13 y 14 y el simulador de la Task 15):
  - `GET /plan/{id}/contexto` → 200 `at_pt_contexto()`: `{ok, plan_id, codigo, estado, proyecto, empresa, cliente,
    fecha_firma, contrato: {servicios_contratados, alcance, entregables, fases_siguientes, plazo, garantia_meses (entero;
    3 si el contrato no lo dice)}, propuesta: null |
    {company_name, solution_text, how_it_works: [texto…], extracto_reunion (≤ 3000 caracteres)}, tabla, slides_foto,
    plan_actual (payload o null), comentarios, crm_cliente_id (0 si la ficha no está enlazada), rubro ('' si no hay)}`;
    404 si el plan no existe.
  - `POST /plan/{id}/borrador` cuerpo `{plan: objeto (o el JSON en texto, con o sin cerco), origen: "borrador"|"cambios"}` →
    200 `{ok: true, errores: [], avisos: [...]}` (plan en `borrador`, vista previa pedida con aviso; los avisos también
    quedan en la nota como `Avisos del borrador: …`); 422 `{ok: false, errores, avisos}` si no valida (plan en `error` con
    la nota `La IA devolvió un plan que no sirve: …`, o `El plan no cabe con la tabla de tiempos y los días que fijó Luis: …`
    si lo que falla es la segunda validación, por ejemplo el tope de 130 días hábiles; nada guardado); 409 si llegó tarde
    (`borrador` solo con el plan en `generando`, o en `error` todavía sin contenido: un borrador tardío nunca pisa lo que
    Luis editó; `cambios` solo en `cambios` o `error`); 400 origen desconocido o cuerpo que no es JSON (lo rechaza
    WordPress); 404; 500 si no se pudo guardar.
  - `GET /plan/{id}/render&modo=draft|final` → 200 `{ok: true, render: at_pt_armar_render(...), propuesta_uid: '' |
    '<unique_link_id>', crm_cliente_id, estado}` (D10: `estado` es el del plan en ese momento; el flujo 3 no renderiza un
    `draft` si es `aprobando`, `listo` o `enviado`); 409 sin contenido; 400 modo desconocido; 404. Con la base
    `https://automatizatech.cl/?rest_route=/automatiza-tech/v1`, el modo va como `&modo=` (con `?modo=` queda dentro de
    `rest_route` y la ruta da 404).
  - `POST /plan/{id}/vista` cuerpo `{modo, ok, view_url, pdf_url, faltan: [slides], nota}` → 200 `{ok: true, estado}`;
    400 modo desconocido; 404. Un `draft` que llega con el plan en `aprobando`, `listo` o `enviado` es de una vista previa
    vieja: no guarda `view_url`/`pdf_url` ni nota (D10), así nunca pisa la versión final.
  - `POST /plan/{id}/error` cuerpo `{nota}` → 200 `{ok: true, estado}`; 404. Solo pasa a `error` un plan que espera a los
    flujos 1 y 2 (`generando` o `cambios`); en otro estado el aviso es de una corrida vieja o repetida (por ejemplo el 409
    de un borrador tardío) y solo deja la nota. Más estricto que «si la transición es válida» del esqueleto (ver
    discrepancias); el flujo 3 Render nunca llama a esta ruta (termina siempre en `/vista`).
  - Internas: `at_pt_rest_rutas(): void` (en `rest_api_init`), `at_pt_rest_fila(WP_REST_Request $r)`,
    `at_pt_rest_cuerpo(WP_REST_Request $r): array`,
    `at_pt_rest_rechazar(object $f, array $errores, array $avisos, string $prefijo = 'La IA devolvió un plan que no sirve: '): WP_REST_Response`,
    y los manejadores `at_pt_rest_contexto`, `at_pt_rest_borrador`, `at_pt_rest_render`, `at_pt_rest_vista`, `at_pt_rest_error`.

Review Focus que cubre esta tarea: 1 (firma el viernes con el lunes feriado → parte el martes; inicio un sábado → parte el
lunes; la secuencia salta el feriado del martes 20; ninguna actividad empieza ni termina en fin de semana o feriado), 2 (JSON
en texto roto, fase desconocida, días 0 o 200, sin fases, bloques sin actividades → 422 y `error` con motivo legible, nada
guardado; texto enorme → 422; texto largo → se acorta con aviso en la nota y en la respuesta; error avisado por n8n), 3 (días
`luis` intactos al pedir cambios y ante un borrador tardío; lo que cambia la IA queda «IA · revisar»; aviso si la IA quita una
actividad de Luis), 4 (sin propuesta: `propuesta_uid` y `portal_url` vacíos; n8n no tiene de dónde reutilizar portada y
cierre), 5 (render final fallido → `error`; un error de una corrida vieja no tumba un borrador bueno ni un plan
«aprobando») y 6 (un plan que la tabla lleva sobre 130 días hábiles → 422 y nada guardado; el «Arranque» de la IA se
reemplaza por el fijo).

- [ ] **Step 1: Escribir la prueba que falla (rutas, clave y contexto) y su ayuda REST**

Agregar al final de `tests/plan/datos-prueba.php` (la ayuda que usan las tres pruebas REST de esta tarea):
```php

/** Petición REST a automatiza-tech/v1. $clave: true = AT_REST_SECRET, false = sin cabecera, texto = esa clave. */
function pt_pedir(string $metodo, string $ruta, $cuerpo = null, $clave = true, array $query = []): WP_REST_Response {
	$r = new WP_REST_Request($metodo, '/automatiza-tech/v1' . $ruta);
	if ($clave === true) {
		$r->set_header('x-at-secret', AT_REST_SECRET);
	} elseif (is_string($clave)) {
		$r->set_header('x-at-secret', $clave);
	}
	if ($query) {
		$r->set_query_params($query);
	}
	if ($cuerpo !== null) {
		$r->set_header('content-type', 'application/json');
		$r->set_body(is_string($cuerpo) ? $cuerpo : wp_json_encode($cuerpo));
	}
	return rest_do_request($r);
}
```

Create `tests/plan/rest-contexto-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/rest-contexto-wp-test.php
// Task 7: las cinco rutas REST del plan, su clave y GET /plan/{id}/contexto.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_rest_rutas', 'at_pt_rest_contexto');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: se define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
require __DIR__ . '/datos-prueba.php';
add_filter('pre_option_at_pt_duraciones', function () { return ''; });

// 1) Rutas registradas con la clave de siempre.
$rutas = rest_get_server()->get_routes();
$bien = 0;
foreach (['contexto' => 'GET', 'borrador' => 'POST', 'render' => 'GET', 'vista' => 'POST', 'error' => 'POST'] as $ruta => $metodo) {
	$h = $rutas['/automatiza-tech/v1/plan/(?P<id>\d+)/' . $ruta][0] ?? null;
	if ($h && ($h['permission_callback'] ?? '') === 'automatiza_proposals_rest_auth' && !empty($h['methods'][$metodo])) {
		$bien++;
	}
}
ok($bien === 5, '1) las 5 rutas del plan existen, con su método y la clave X-AT-Secret (automatiza_proposals_rest_auth)');

// 2) Sin clave o con clave mala no entra nadie.
$m = pt_marca();
$cli = pt_cliente($m);
$pid = at_pt_crear_plan(pt_contrato($cli['tech'], pt_propuesta($m, ['transcript' => str_repeat('Hablamos del sitio. ', 400)])));
ok(pt_pedir('GET', "/plan/{$pid}/contexto", null, false)->get_status() === 401, '2) sin clave: 401');
ok(pt_pedir('GET', "/plan/{$pid}/contexto", null, 'clave-mala')->get_status() === 403, '2) clave mala: 403');
ok(pt_pedir('POST', "/plan/{$pid}/error", ['nota' => 'x'], false)->get_status() === 401 && at_pt_plan($pid)->estado === 'generando', '2) sin clave nadie cambia el estado');

// 3) Contexto con propuesta.
$r = pt_pedir('GET', "/plan/{$pid}/contexto");
$d = $r->get_data();
ok($r->get_status() === 200 && $d === at_pt_contexto(at_pt_plan($pid)), '3) contexto: 200 y exactamente at_pt_contexto()');
ok(($d['codigo'] ?? '') === at_pt_plan($pid)->codigo && is_array($d['propuesta'] ?? null) && mb_strlen($d['propuesta']['extracto_reunion']) === 3000, '3) con el código del plan y el extracto de la reunión acotado a 3000 caracteres');
ok(pt_pedir('GET', '/plan/' . pt_plan_inexistente() . '/contexto')->get_status() === 404, '3) plan que no existe: 404');

// 4) Contrato sin propuesta (Review Focus 4).
$m2 = pt_marca();
$cli2 = pt_cliente($m2, false);
$pid2 = at_pt_crear_plan(pt_contrato($cli2['tech'], null));
$d2 = pt_pedir('GET', "/plan/{$pid2}/contexto")->get_data();
ok(is_array($d2) && array_key_exists('propuesta', $d2) && $d2['propuesta'] === null && $d2['contrato']['alcance'] !== '', '4) sin propuesta: propuesta null y lo contratado igual llega');

fin();
```

- [ ] **Step 2: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/rest-contexto-wp-test.php; echo "exit=$?"
```
Expected:
```
FALLA falta la función at_pt_rest_rutas()

1 FALLAS
exit=1
```

- [ ] **Step 3: Crear `rest.php` (registro de las cinco rutas y contexto)**

Create `wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php`:
```php
<?php
/**
 * Plan de trabajo: rutas REST que usan los flujos de n8n (namespace automatiza-tech/v1).
 * Misma clave que las propuestas: cabecera X-AT-Secret = AT_REST_SECRET (automatiza_proposals_rest_auth,
 * inc/rest-proposals.php; sin clave 401, clave mala 403).
 *   GET  /plan/{id}/contexto        lo que recibe la IA
 *   POST /plan/{id}/borrador        {plan, origen: borrador|cambios} → valida, tabla, fechas, guarda, vista previa
 *   GET  /plan/{id}/render&modo=    cuerpo para el renderer + propuesta_uid (fotos a reutilizar), crm_cliente_id y estado
 *                                   (la base ya trae ?rest_route=: el modo va con &modo=)
 *   POST /plan/{id}/vista           {modo, ok, view_url, pdf_url, faltan, nota} → enlaces y estado
 *   POST /plan/{id}/error           {nota} → estado «error»
 */
if (!defined('ABSPATH')) {
	exit;
}

add_action('rest_api_init', 'at_pt_rest_rutas');

function at_pt_rest_rutas(): void {
	$ns = 'automatiza-tech/v1';
	$id = ['id' => ['required' => true, 'validate_callback' => function ($v) { return is_numeric($v); }]];
	$rutas = [
		'contexto' => ['GET', 'at_pt_rest_contexto'],
		'borrador' => ['POST', 'at_pt_rest_borrador'],
		'render'   => ['GET', 'at_pt_rest_render'],
		'vista'    => ['POST', 'at_pt_rest_vista'],
		'error'    => ['POST', 'at_pt_rest_error'],
	];
	foreach ($rutas as $ruta => [$metodo, $callback]) {
		register_rest_route($ns, '/plan/(?P<id>\d+)/' . $ruta, [
			'methods'             => $metodo,
			'callback'            => $callback,
			'permission_callback' => 'automatiza_proposals_rest_auth',
			'args'                => $id,
		]);
	}
}

/** El plan de la URL o un 404. */
function at_pt_rest_fila(WP_REST_Request $r) {
	$f = at_pt_plan((int) $r['id']);
	return $f ?: new WP_Error('at_pt_no_existe', 'No existe el plan.', ['status' => 404]);
}

/** Cuerpo JSON como arreglo ([] si no viene). */
function at_pt_rest_cuerpo(WP_REST_Request $r): array {
	$p = $r->get_json_params();
	return is_array($p) ? $p : [];
}

function at_pt_rest_contexto(WP_REST_Request $r) {
	$f = at_pt_rest_fila($r);
	if (is_wp_error($f)) {
		return $f;
	}
	return new WP_REST_Response(at_pt_contexto($f), 200);
}
```

- [ ] **Step 4: Cargarlo desde `cargar.php`**

Se agrega una línea al final, igual que en la Task 6 (las Tasks 8 y 9 le suman después `ajustes.php` y `panel.php`; repetir
el paso no duplica nada ni borra lo de otras tareas):
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
F=wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php
L="require_once __DIR__ . '/rest.php';"
[ "$(tail -c1 "$F" | wc -l)" -eq 1 ] || echo >> "$F"
grep -qxF "$L" "$F" || printf '%s\n' "$L" >> "$F"
"$PHP" -l "$F" && tail -4 "$F"
```
Expected: `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` y las cuatro últimas
líneas:
```php
require_once __DIR__ . '/puras.php';
require_once __DIR__ . '/datos.php';
require_once __DIR__ . '/disparador.php';
require_once __DIR__ . '/rest.php';
```

- [ ] **Step 5: Correr y ver pasar**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
for f in cargar rest; do "$PHP" -l "wp-content/themes/automatiza-tech/inc/plan-trabajo/$f.php"; done
"$PHP" tests/plan/rest-contexto-wp-test.php; echo "exit=$?"
```
Expected: dos `No syntax errors detected`, 8 líneas `ok   …`, `TODO OK`, `exit=0`.

- [ ] **Step 6: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add tests/plan/datos-prueba.php tests/plan/rest-contexto-wp-test.php wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php
git commit -m "$(cat <<'EOF'
feat(plan): rutas REST del plan con la clave X-AT-Secret y GET /plan/{id}/contexto para la IA

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 7: Escribir la prueba que falla (borrador de la IA) y sus ayudas de planes**

Agregar al final de `tests/plan/datos-prueba.php` (el plan que devolvería la IA y cómo leer y editar sus actividades;
las usan esta prueba y la de render):
```php
/** Plan como lo devuelve la IA (sin fechas ni origen): sitio de una página + capacitación + soporte, con fotos para las 10 láminas. */
function pt_plan_ia(): array {
	$foto = function (string $slide, string $prompt): array { return ['slide' => $slide, 'prompt' => $prompt]; };
	return [
		'proyecto' => '[PRUEBA] Sitio de una página',
		'fases'    => [
			['clave' => 'diseno_desarrollo', 'descripcion' => 'Diseñamos y construimos tu sitio.', 'bloques' => [
				['nombre' => 'Diseño', 'entrega' => true, 'entregable' => 'Maqueta aprobada', 'actividades' => [
					['nombre' => 'Maqueta de la portada', 'detalle' => 'Estructura y estilo', 'responsable' => 'at', 'dias_habiles' => 2, 'servicio' => 'sitio_una_pagina', 'etapa' => 'diseno'],
				]],
				['nombre' => 'Desarrollo', 'entrega' => false, 'entregable' => 'Sitio en pruebas', 'actividades' => [
					['nombre' => 'Construcción del sitio', 'detalle' => '', 'responsable' => 'at', 'dias_habiles' => 4, 'servicio' => 'sitio_una_pagina', 'etapa' => 'desarrollo'],
				]],
			]],
			['clave' => 'implementacion', 'descripcion' => 'Publicamos y te capacitamos.', 'bloques' => [
				['nombre' => 'Puesta en marcha', 'entrega' => false, 'entregable' => 'Sitio publicado', 'actividades' => [
					['nombre' => 'Publicación en tu dominio', 'detalle' => '', 'responsable' => 'at', 'dias_habiles' => 2, 'servicio' => 'sitio_una_pagina', 'etapa' => 'implementacion'],
					['nombre' => 'Capacitación', 'detalle' => '', 'responsable' => 'ambos', 'dias_habiles' => 1, 'servicio' => '', 'etapa' => ''],
				]],
			]],
			['clave' => 'soporte', 'descripcion' => 'Te acompañamos después de la entrega.', 'bloques' => [
				['nombre' => 'Acompañamiento', 'entrega' => false, 'entregable' => '', 'actividades' => [
					['nombre' => 'Ajustes de la primera semana', 'detalle' => '', 'responsable' => 'at', 'dias_habiles' => 5, 'servicio' => '', 'etapa' => 'soporte'],
				]],
			]],
		],
		'hitos'             => [['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño']],
		'necesitamos_de_ti' => ['Logo y colores', 'Textos de la empresa'],
		'reuniones'         => [['nombre' => 'Reunión de inicio', 'detalle' => 'Revisamos el plan juntos']],
		'soporte'           => ['garantia_meses' => 3, 'mensuales' => []],
		'image_briefs'      => [
			$foto('cover', 'close up of hands on a notebook, blurred office background'),
			$foto('metodo', 'small team planning on a table with sticky notes, hands only'),
			$foto('gantt', 'wall calendar next to a wooden desk, soft light'),
			$foto('fase_1', 'designer sketching on paper, hands only'),
			$foto('fase_2', 'person smiling at a laptop, screen facing away'),
			$foto('fase_3', 'handshake at a small shop counter'),
			$foto('necesitamos', 'folder with colorful papers on a desk'),
			$foto('reuniones', 'two people on a video call, screen facing away'),
			$foto('portal', 'person checking a phone, screen facing away'),
			$foto('cierre', 'small business team celebrating in their shop'),
		],
	];
}

/** Todas las actividades del plan, en orden. */
function pt_actividades(array $plan): array {
	$r = [];
	foreach ((array) ($plan['fases'] ?? []) as $f) {
		foreach ((array) ($f['bloques'] ?? []) as $b) {
			foreach ((array) ($b['actividades'] ?? []) as $a) {
				$r[] = $a;
			}
		}
	}
	return $r;
}

/** La primera actividad con ese nombre; null si no hay. */
function pt_actividad(array $plan, string $nombre): ?array {
	foreach (pt_actividades($plan) as $a) {
		if (($a['nombre'] ?? '') === $nombre) {
			return $a;
		}
	}
	return null;
}

/** El plan con los campos de la actividad $nombre cambiados. */
function pt_editar_actividad(array $plan, string $nombre, array $cambios): array {
	foreach ((array) ($plan['fases'] ?? []) as $i => $f) {
		foreach ((array) ($f['bloques'] ?? []) as $j => $b) {
			foreach ((array) ($b['actividades'] ?? []) as $k => $a) {
				if (($a['nombre'] ?? '') === $nombre) {
					$plan['fases'][$i]['bloques'][$j]['actividades'][$k] = array_merge($a, $cambios);
				}
			}
		}
	}
	return $plan;
}
```

Create `tests/plan/rest-borrador-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/rest-borrador-wp-test.php
// Task 7: POST /plan/{id}/borrador — valida, tabla, fechas en días hábiles, días de Luis, planes rotos y tope.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_rest_borrador');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: se define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
require __DIR__ . '/datos-prueba.php';
// Tabla de referencia y dos feriados fijos: el lunes 12 y el martes 20 de octubre de 2026.
add_filter('pre_option_at_pt_duraciones', function () { return ''; });
add_filter('pre_option_automatiza_chat_schedule', function () { return ['holidays' => "2026-10-12\n2026-10-20\n"]; });
$GLOBALS['pt_http_respuesta'] = 200;

// 1) Borrador válido: contrato firmado el viernes 9 de octubre de 2026.
$m = pt_marca();
$cli = pt_cliente($m);
$pid = at_pt_crear_plan(pt_contrato($cli['tech'], pt_propuesta($m)));
$GLOBALS['pt_http'] = [];
$r = pt_pedir('POST', "/plan/{$pid}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'borrador']);
$f = at_pt_plan($pid);
$p = at_pt_payload($f);
ok($r->get_status() === 200 && ($r->get_data()['ok'] ?? null) === true && ($r->get_data()['errores'] ?? null) === [], '1) borrador válido: 200 ok');
ok($f->estado === 'borrador' && !empty($p['fases']) && ($p['fecha_firma'] ?? '') === '2026-10-09', '1) queda en «borrador» con su contenido y la fecha de firma');
ok($f->fecha_inicio === '2026-10-13' && ($p['cronograma']['inicio'] ?? '') === '2026-10-13' && ($p['fecha_inicio'] ?? '') === '2026-10-13', '1) firmado el viernes 9 con el lunes 12 feriado: parte el martes 13 (Review Focus 1)');
$malas = [];
foreach (pt_actividades($p) as $a) {
	foreach (['desde', 'hasta'] as $k) {
		$dia = (string) ($a[$k] ?? '');
		$fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) ? new DateTimeImmutable($dia, new DateTimeZone('UTC')) : null;
		if (!$fecha || (int) $fecha->format('N') >= 6 || in_array($dia, ['2026-10-12', '2026-10-20'], true)) {
			$malas[] = ($a['nombre'] ?? '?') . " {$k}={$dia}";
		}
	}
}
ok($malas === [], '1) ninguna actividad empieza ni termina en fin de semana ni en un feriado' . ($malas ? ': ' . implode(', ', $malas) : ''));
ok((pt_actividad($p, 'Maqueta de la portada')['desde'] ?? '') === '2026-10-19' && (pt_actividad($p, 'Maqueta de la portada')['hasta'] ?? '') === '2026-10-22', '1) la secuencia salta el feriado del martes 20: la maqueta (3 días hábiles) va del lunes 19 al jueves 22');
ok((pt_actividad($p, 'Maqueta de la portada')['dias_habiles'] ?? 0) === 3 && (pt_actividad($p, 'Maqueta de la portada')['origen'] ?? '') === 'tabla', '1) días de la tabla donde calza (diseño de un sitio de una página: 3)');
ok((pt_actividad($p, 'Capacitación')['origen'] ?? '') === 'ia', '1) sin par en la tabla: origen «ia»');
$render = pt_llamadas('render');
ok(count($render) === 1 && $render[0]['cuerpo'] === ['id' => $pid, 'codigo' => (string) $f->codigo, 'modo' => 'draft', 'aviso' => true], '1) pide la vista previa con aviso a Luis');

// 2) Luis eligió un sábado como fecha de inicio (Review Focus 1).
$m2 = pt_marca();
$cli2 = pt_cliente($m2);
$pid2 = at_pt_crear_plan(pt_contrato($cli2['tech'], null));
at_pt_guardar($pid2, ['fecha_inicio' => '2026-10-17']);
pt_pedir('POST', "/plan/{$pid2}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'borrador']);
$f2 = at_pt_plan($pid2);
ok($f2->estado === 'borrador' && $f2->fecha_inicio === '2026-10-19' && (at_pt_payload($f2)['cronograma']['inicio'] ?? '') === '2026-10-19', '2) inicio un sábado: parte el lunes 19');

// 3) Borradores que no se aplican.
at_pt_guardar($pid2, ['estado' => 'listo']);
$antes = at_pt_plan($pid2)->payload;
$r = pt_pedir('POST', "/plan/{$pid2}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'borrador']);
ok($r->get_status() === 409 && at_pt_plan($pid2)->payload === $antes && at_pt_plan($pid2)->estado === 'listo', '3) plan «listo»: un borrador tardío no se aplica (409)');
ok(pt_pedir('POST', "/plan/{$pid2}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'otro'])->get_status() === 400, '3) origen desconocido: 400');
ok(pt_pedir('POST', '/plan/' . pt_plan_inexistente() . '/borrador', ['plan' => pt_plan_ia(), 'origen' => 'borrador'])->get_status() === 404, '3) plan que no existe: 404');

// 4) Planes rotos (Review Focus 2): «error» con motivo legible y nada guardado.
$m3 = pt_marca();
$cli3 = pt_cliente($m3);
$pid3 = at_pt_crear_plan(pt_contrato($cli3['tech'], null));
$r = pt_pedir('POST', "/plan/{$pid3}/borrador", 'esto no es json');
ok($r->get_status() === 400 && at_pt_plan($pid3)->estado === 'generando' && at_pt_plan($pid3)->payload === null, '4) cuerpo que no es JSON: WordPress lo rechaza (400) sin tocar el plan');
$con = function (callable $cambio): array { $p = pt_plan_ia(); $cambio($p); return $p; };
$malos = [
	'plan en texto roto'      => '{"fases": [',
	'fase desconocida'        => $con(function (&$p) { $p['fases'][] = ['clave' => 'marketing', 'descripcion' => '', 'bloques' => [['nombre' => 'Campaña', 'entrega' => false, 'entregable' => '', 'actividades' => [['nombre' => 'Anuncios', 'detalle' => '', 'responsable' => 'at', 'dias_habiles' => 3, 'servicio' => '', 'etapa' => '']]]]]; }),
	'días 0'                  => $con(function (&$p) { $p['fases'][0]['bloques'][0]['actividades'][0]['dias_habiles'] = 0; }),
	'días 200'                => $con(function (&$p) { $p['fases'][0]['bloques'][1]['actividades'][0]['dias_habiles'] = 200; }),
	'sin fases'               => $con(function (&$p) { $p['fases'] = []; }),
	'bloques sin actividades' => $con(function (&$p) { foreach ($p['fases'] as $i => $f) { foreach ($f['bloques'] as $j => $b) { $p['fases'][$i]['bloques'][$j]['actividades'] = []; } } }),
];
foreach ($malos as $nombre => $plan) {
	at_pt_guardar($pid3, ['estado' => 'generando', 'nota' => '']);
	if (is_array($plan)) {
		$v = at_pt_validar_plan($plan);
		ok(empty($v['ok']), "4) {$nombre}: at_pt_validar_plan() lo rechaza");
	}
	$r = pt_pedir('POST', "/plan/{$pid3}/borrador", ['plan' => $plan, 'origen' => 'borrador']);
	$f3 = at_pt_plan($pid3);
	ok($r->get_status() === 422 && ($r->get_data()['ok'] ?? null) === false && !empty($r->get_data()['errores']), "4) {$nombre}: 422 con los errores");
	ok($f3->estado === 'error' && strpos((string) $f3->nota, 'La IA devolvió un plan que no sirve: ') === 0 && mb_strlen((string) $f3->nota) > 40, "4) {$nombre}: el plan queda en «error» con un motivo legible");
	ok($f3->payload === null, "4) {$nombre}: no se guarda nada");
}

// 5) Textos (Review Focus 2): uno enorme se rechaza; uno largo se acorta con aviso (en la nota y en la respuesta).
at_pt_guardar($pid3, ['estado' => 'generando', 'nota' => '']);
$enorme = pt_plan_ia();
$enorme['fases'][0]['descripcion'] = str_repeat('Texto muy largo. ', 6000);
$r = pt_pedir('POST', "/plan/{$pid3}/borrador", ['plan' => $enorme, 'origen' => 'borrador']);
$f3 = at_pt_plan($pid3);
ok($r->get_status() === 422 && $f3->estado === 'error' && $f3->payload === null && strpos((string) $f3->nota, 'demasiado largo') !== false, '5) texto enorme: 422, «error» con el motivo y nada guardado');
at_pt_guardar($pid3, ['estado' => 'generando', 'nota' => '']);
$largo = pt_plan_ia();
$largo['fases'][0]['descripcion'] = str_repeat('x', 1500);
$r = pt_pedir('POST', "/plan/{$pid3}/borrador", ['plan' => $largo, 'origen' => 'borrador']);
$f3 = at_pt_plan($pid3);
ok($r->get_status() === 200 && $f3->estado === 'borrador' && mb_strlen(at_pt_payload($f3)['fases'][0]['descripcion'] ?? '') === 600 && strpos((string) $f3->nota, 'Avisos del borrador: ') === 0 && in_array('Diseño y desarrollo: descripción: se acortó a 600 caracteres.', $r->get_data()['avisos'] ?? [], true), '5) texto largo: se acorta a 600 y el aviso queda en la nota y en la respuesta');

// 6) «Pedir cambios» no pisa los días de Luis (Review Focus 3).
$anterior = pt_editar_actividad(at_pt_payload(at_pt_plan($pid)), 'Construcción del sitio', ['dias_habiles' => 12, 'origen' => 'luis']);
at_pt_guardar($pid, ['payload' => $anterior, 'estado' => 'cambios', 'comentarios' => 'Alarga el diseño un día']);
$ia = pt_editar_actividad($anterior, 'Construcción del sitio', ['dias_habiles' => 2]);
$ia = pt_editar_actividad($ia, 'Maqueta de la portada', ['dias_habiles' => 4]);
$ia['fases'][0]['descripcion'] = 'Diseñamos y construimos tu sitio (revisado).';
$GLOBALS['pt_http'] = [];
$r = pt_pedir('POST', "/plan/{$pid}/borrador", ['plan' => $ia, 'origen' => 'cambios']);
$p6 = at_pt_payload(at_pt_plan($pid));
ok($r->get_status() === 200 && at_pt_plan($pid)->estado === 'borrador', '6) cambios aplicados: vuelve a «borrador»');
ok((pt_actividad($p6, 'Construcción del sitio')['dias_habiles'] ?? 0) === 12 && (pt_actividad($p6, 'Construcción del sitio')['origen'] ?? '') === 'luis', '6) los días que Luis editó no se pisan aunque la IA los cambie');
ok((pt_actividad($p6, 'Maqueta de la portada')['dias_habiles'] ?? 0) === 4 && (pt_actividad($p6, 'Maqueta de la portada')['origen'] ?? '') === 'ia', '6) en «cambios» no se reaplica la tabla: queda el día que pidió la IA, marcado «ia» (IA · revisar, no «luis»)');
ok(($p6['fases'][0]['descripcion'] ?? '') === 'Diseñamos y construimos tu sitio (revisado).' && count(pt_llamadas('render')) === 1, '6) el resto del cambio se guarda y se pide una vista previa nueva');

// 7) Un borrador tardío sobre un plan que ya tiene contenido no se aplica: nada de Luis se pisa (Review Focus 3 y 5).
at_pt_guardar($pid, ['estado' => 'error']);
$antes7 = at_pt_plan($pid)->payload;
$r = pt_pedir('POST', "/plan/{$pid}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'borrador']);
$p7 = at_pt_payload(at_pt_plan($pid));
ok($r->get_status() === 409 && at_pt_plan($pid)->payload === $antes7 && (pt_actividad($p7, 'Construcción del sitio')['dias_habiles'] ?? 0) === 12 && (pt_actividad($p7, 'Construcción del sitio')['origen'] ?? '') === 'luis', '7) borrador tardío sobre un plan con contenido: 409 y nada de Luis se pisa');

// 8) Plataforma grande: con los días de la IA cabe, con la tabla pasa el tope de 130 días hábiles (Review Focus 6).
$m8 = pt_marca();
$cli8 = pt_cliente($m8);
$pid8 = at_pt_crear_plan(pt_contrato($cli8['tech'], null));
$b8 = function (string $n, string $e): array {
	$a = [];
	foreach (array_keys(at_pt_duraciones_defecto()) as $s) {
		$a[] = ['nombre' => "{$n} {$s}", 'detalle' => '', 'responsable' => 'at', 'dias_habiles' => 1, 'servicio' => $s, 'etapa' => $e];
	}
	return ['nombre' => $n, 'entrega' => true, 'entregable' => '', 'actividades' => $a];
};
$grande = ['proyecto' => '[PRUEBA] Proyecto grande', 'fases' => [
	['clave' => 'diseno_desarrollo', 'descripcion' => '', 'bloques' => [$b8('Diseño', 'diseno'), $b8('Desarrollo', 'desarrollo'), $b8('Pruebas', 'pruebas')]],
	['clave' => 'implementacion', 'descripcion' => '', 'bloques' => [$b8('Puesta en marcha', 'implementacion')]],
]];
ok(at_pt_validar_plan($grande)['ok'] === true, '8) con los días de la IA el plan cabe');
$r = pt_pedir('POST', "/plan/{$pid8}/borrador", ['plan' => $grande, 'origen' => 'borrador']);
$f8 = at_pt_plan($pid8);
ok($r->get_status() === 422 && $f8->estado === 'error' && $f8->payload === null && strpos((string) $f8->nota, 'El plan no cabe con la tabla de tiempos y los días que fijó Luis: ') === 0 && strpos((string) $f8->nota, '130') !== false, '8) la tabla lo lleva a 133 días hábiles: 422, «error» con el tope y nada guardado');

// 9) La IA manda su propio «Arranque»: se usa el fijo, con la entrega de insumos de la cláusula 4.2.
$m9 = pt_marca();
$cli9 = pt_cliente($m9);
$pid9 = at_pt_crear_plan(pt_contrato($cli9['tech'], null));
$con_arranque = pt_plan_ia();
array_unshift($con_arranque['fases'][0]['bloques'], ['nombre' => 'Arranque', 'entrega' => false, 'entregable' => '', 'actividades' => [
	['nombre' => 'Kickoff', 'detalle' => '', 'responsable' => 'ambos', 'dias_habiles' => 1, 'servicio' => '', 'etapa' => 'arranque'],
]]);
$r = pt_pedir('POST', "/plan/{$pid9}/borrador", ['plan' => $con_arranque, 'origen' => 'borrador']);
$f9 = at_pt_plan($pid9);
$p9 = at_pt_payload($f9);
$nombres9 = array_column($p9['fases'][0]['bloques'] ?? [], 'nombre');
ok($r->get_status() === 200 && array_column($p9['fases'][0]['bloques'][0]['actividades'] ?? [], 'nombre') === ['Reunión de inicio', 'Entrega de logo, textos y accesos'] && count(array_keys($nombres9, 'Arranque', true)) === 1 && strpos((string) $f9->nota, 'Avisos del borrador: La IA mandó su propio bloque «Arranque»') === 0, '9) «Arranque» de la IA: queda uno solo, el fijo (reunión de inicio y entrega de logo, textos y accesos), con el aviso');

// 10) «Pedir cambios» en que la IA renombra una actividad de Luis: sus días no vuelven solos, pero queda el aviso (Review Focus 3).
at_pt_guardar($pid, ['estado' => 'cambios']);
$ia10 = pt_editar_actividad(at_pt_payload(at_pt_plan($pid)), 'Construcción del sitio', ['nombre' => 'Construcción y pruebas del sitio', 'dias_habiles' => 6, 'origen' => 'ia']);
$r = pt_pedir('POST', "/plan/{$pid}/borrador", ['plan' => $ia10, 'origen' => 'cambios']);
$aviso10 = 'La IA renombró o quitó «Construcción del sitio», que tenía 12 días hábiles puestos por ti: revísala en el panel.';
ok($r->get_status() === 200 && in_array($aviso10, $r->get_data()['avisos'] ?? [], true) && strpos((string) at_pt_plan($pid)->nota, $aviso10) !== false && (pt_actividad(at_pt_payload(at_pt_plan($pid)), 'Construcción y pruebas del sitio')['origen'] ?? '') === 'ia', '10) la IA renombró una actividad de Luis: aviso en la respuesta y en la nota; la nueva queda «ia»');

// 11) El plan llega como el texto de la IA, con cerco ```json: la ruta se lo pasa tal cual a at_pt_validar_entrada().
$m11 = pt_marca();
$cli11 = pt_cliente($m11);
$pid11 = at_pt_crear_plan(pt_contrato($cli11['tech'], null));
$r = pt_pedir('POST', "/plan/{$pid11}/borrador", ['plan' => "```json\n" . wp_json_encode(pt_plan_ia()) . "\n```", 'origen' => 'borrador']);
ok($r->get_status() === 200 && at_pt_plan($pid11)->estado === 'borrador' && (pt_actividad(at_pt_payload(at_pt_plan($pid11)), 'Maqueta de la portada')['origen'] ?? '') === 'tabla', '11) plan en texto con cerco ```json: se acepta y se le aplica la tabla');

// 12) La IA no manda el nombre del proyecto: se usa el del contrato, sin aviso que ya no aplica.
$m12 = pt_marca();
$cli12 = pt_cliente($m12);
$pid12 = at_pt_crear_plan(pt_contrato($cli12['tech'], null));
$sin_nombre = pt_plan_ia();
unset($sin_nombre['proyecto']);
$r = pt_pedir('POST', "/plan/{$pid12}/borrador", ['plan' => $sin_nombre, 'origen' => 'borrador']);
ok($r->get_status() === 200 && (at_pt_payload(at_pt_plan($pid12))['proyecto'] ?? '') === '[PRUEBA] Sitio de una página' && !in_array('El plan no trae el nombre del proyecto.', $r->get_data()['avisos'] ?? [], true), '12) sin nombre de proyecto: el del contrato y sin el aviso de la validación');

fin();
```

- [ ] **Step 8: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/rest-borrador-wp-test.php; echo "exit=$?"
```
Expected:
```
FALLA falta la función at_pt_rest_borrador()

1 FALLAS
exit=1
```

- [ ] **Step 9: Implementar el borrador (orden de uso único: validar, días de Luis, tabla o marcas, validar otra vez, fechas, guardar, vista previa)**

Agregar al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php`:
```php
/**
 * Plan que no sirve: nada se guarda; el plan pasa a «error» (o solo anota, si no puede) con un motivo legible.
 * $prefijo dice de dónde viene el problema: lo que mandó la IA (por defecto) o lo que queda después de aplicar la
 * tabla de tiempos y los días de Luis (la segunda validación). HTTP 422.
 */
function at_pt_rest_rechazar(object $f, array $errores, array $avisos, string $prefijo = 'La IA devolvió un plan que no sirve: '): WP_REST_Response {
	$limpios = [];
	foreach ($errores as $e) {
		$e = mb_substr(sanitize_text_field(is_scalar($e) ? (string) $e : ''), 0, 300);
		if ($e !== '') {
			$limpios[] = $e;
		}
	}
	if (!$limpios) {
		$limpios = ['El plan no pasó la validación.'];
	}
	$nota = $prefijo . implode('; ', array_slice($limpios, 0, 5));
	if (at_pt_transicion_valida((string) $f->estado, 'error')) {
		at_pt_cambiar_estado((int) $f->id, 'error', $nota);
	} else {
		at_pt_guardar((int) $f->id, ['nota' => $nota]);
	}
	return new WP_REST_Response(['ok' => false, 'errores' => $limpios, 'avisos' => array_values(array_map('strval', $avisos))], 422);
}

/**
 * POST /plan/{id}/borrador — llega el plan de la IA; sigue el «Orden de uso único» del Grupo A (Task 2).
 * 'borrador' se acepta con el plan en «generando», o en «error» si todavía no tiene contenido (un borrador tardío
 * nunca pisa lo que Luis editó); 'cambios', en «cambios» o «error». Si no, llegó tarde (409) y no se aplica.
 *   borrador: at_pt_validar_entrada(…, true) (el «Arranque» de la IA se cambia por el fijo) → días de Luis →
 *             tabla de tiempos → at_pt_validar_plan otra vez (la tabla puede pasar el tope de 130 días hábiles).
 *   cambios:  at_pt_validar_entrada(…) → días de Luis → avisos de las actividades de Luis que la IA quitó →
 *             lo que cambió la IA queda «ia» (IA · revisar) → at_pt_validar_plan otra vez. No reaplica la tabla.
 * Después calcula fechas en días hábiles desde la fecha de inicio guardada (o la de defecto) y, si esa fecha no es
 * hábil, desde el hábil siguiente; guarda, pasa a «borrador» y pide la vista previa con aviso a Luis. Los avisos
 * van en la respuesta y en la nota.
 */
function at_pt_rest_borrador(WP_REST_Request $r) {
	$f = at_pt_rest_fila($r);
	if (is_wp_error($f)) {
		return $f;
	}
	$p = at_pt_rest_cuerpo($r);
	$origen = (string) ($p['origen'] ?? 'borrador');
	if (!in_array($origen, ['borrador', 'cambios'], true)) {
		return new WP_REST_Response(['ok' => false, 'errores' => ['origen debe ser «borrador» o «cambios».'], 'avisos' => []], 400);
	}
	$anterior = at_pt_payload($f);
	$admitidos = $origen === 'borrador' ? ['generando', 'error'] : ['cambios', 'error'];
	$tardio = !in_array((string) $f->estado, $admitidos, true)
		|| ($origen === 'borrador' && (string) $f->estado === 'error' && !empty($anterior['fases']));
	if ($tardio) {
		return new WP_REST_Response(['ok' => false, 'errores' => ['El plan está en «' . $f->estado . '»: este ' . $origen . ' llegó tarde y no se aplica.'], 'avisos' => []], 409);
	}
	$ctx = at_pt_contexto($f);
	// El objeto o el texto de la IA (con o sin cerco ```json) va directo a la validación del Grupo A: aquí no se decodifica.
	$v = at_pt_validar_entrada($p['plan'] ?? null, $origen === 'borrador');
	$avisos = array_values(array_map('strval', (array) ($v['avisos'] ?? [])));
	if (empty($v['ok'])) {
		return at_pt_rest_rechazar($f, (array) ($v['errores'] ?? []), $avisos);
	}
	$plan = $v['plan'];
	if (trim((string) ($plan['proyecto'] ?? '')) === '') {
		// Sin nombre de proyecto: el del contrato. El aviso de la validación ya no aplica.
		$plan['proyecto'] = $ctx['proyecto'];
		$avisos = array_values(array_diff($avisos, ['El plan no trae el nombre del proyecto.']));
	}
	$plan = at_pt_respetar_dias_luis($anterior, $plan);
	if ($origen === 'borrador') {
		$plan = at_pt_aplicar_tabla($plan, at_pt_duraciones());
	} else {
		$avisos = array_merge($avisos, at_pt_luis_perdidas($anterior, $plan));
		$plan = at_pt_marcar_ediciones($anterior, $plan, 'ia');
	}
	// Segunda validación: la tabla o los días de Luis pueden pasar los topes (sus avisos repiten los de la primera).
	$v2 = at_pt_validar_plan($plan);
	if (empty($v2['ok'])) {
		return at_pt_rest_rechazar($f, (array) ($v2['errores'] ?? []), $avisos, 'El plan no cabe con la tabla de tiempos y los días que fijó Luis: ');
	}
	$feriados = at_pt_feriados();
	$inicio = (string) ($f->fecha_inicio ?? '');
	if (!at_pt_db_fecha_valida($inicio)) {
		$inicio = at_pt_inicio_por_defecto($ctx['fecha_firma'], $feriados);
	}
	if (!at_pt_es_habil($inicio, $feriados)) {
		$inicio = at_pt_siguiente_habil($inicio, $feriados);
	}
	$plan = at_pt_calcular_fechas($v2['plan'], $inicio, $feriados);
	$plan['fecha_inicio'] = $inicio;
	$plan['fecha_firma'] = $ctx['fecha_firma'];
	$guardado = at_pt_guardar((int) $f->id, [
		'payload'      => $plan,
		'fecha_inicio' => $inicio,
		'estado'       => 'borrador',
		'nota'         => $avisos ? 'Avisos del borrador: ' . implode('; ', $avisos) : '',
	]);
	if (!$guardado) {
		return new WP_REST_Response(['ok' => false, 'errores' => ['No se pudo guardar el plan.'], 'avisos' => $avisos], 500);
	}
	$motivo = at_pt_pedir_render((int) $f->id, 'draft', true);
	if ($motivo !== '') {
		$avisos[] = 'No se pudo pedir la vista previa: ' . $motivo;
	}
	return new WP_REST_Response(['ok' => true, 'errores' => [], 'avisos' => $avisos], 200);
}
```

- [ ] **Step 10: Correr y ver pasar**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" -l wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php && "$PHP" tests/plan/rest-borrador-wp-test.php; echo "exit=$?"
```
Expected: `No syntax errors detected`, 49 líneas `ok   …` (8 del borrador válido, 1 del sábado, 3 de los que no se aplican,
1 del cuerpo que no es JSON, 23 de los seis planes rotos, 2 de textos, 4 de cambios, 1 del borrador tardío, 2 del tope de
130 días, 1 del «Arranque» de la IA, 1 de la actividad de Luis renombrada, 1 del plan en texto con cerco y 1 del plan sin
nombre de proyecto), `TODO OK`, `exit=0`. Si falla una línea
`4) …: at_pt_validar_plan() lo rechaza` o `8) con los días de la IA el plan cabe`, el defecto está en `at_pt_validar_plan()`
(Task 2), no aquí: arreglarlo allá con su prueba pura.

- [ ] **Step 11: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add tests/plan/datos-prueba.php tests/plan/rest-borrador-wp-test.php wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php
git commit -m "$(cat <<'EOF'
feat(plan): POST /plan/{id}/borrador valida el plan de la IA, respeta los días de Luis, aplica la tabla y calcula fechas hábiles

Sigue el orden de uso único del Grupo A: el Arranque de la IA se cambia por el fijo, se valida otra vez después de la
tabla (tope de 130 días hábiles) y en «cambios» lo que cambia la IA queda «ia». Un plan que no valida deja el plan en
error con el motivo y no guarda nada; un borrador tardío no se aplica ni pisa lo que Luis editó.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 12: Escribir la prueba que falla (render, vista y error)**

Create `tests/plan/rest-render-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/rest-render-wp-test.php
// Task 7: GET /plan/{id}/render, POST /plan/{id}/vista y POST /plan/{id}/error.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_rest_render', 'at_pt_rest_vista', 'at_pt_rest_error');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: se define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
require __DIR__ . '/datos-prueba.php';
add_filter('pre_option_at_pt_duraciones', function () { return ''; });
add_filter('pre_option_automatiza_chat_schedule', function () { return ['holidays' => "2026-10-12\n"]; });
$GLOBALS['pt_http_respuesta'] = 200;

// 1) Render: cuerpo para el renderer (con propuesta y un contrato con 6 meses de garantía).
$m = pt_marca();
$cli = pt_cliente($m);
$pid = at_pt_crear_plan(pt_contrato($cli['tech'], pt_propuesta($m), ['ph_mas' => ['garantia_meses_servicio' => '6']]));
ok(pt_pedir('GET', "/plan/{$pid}/render", null, true, ['modo' => 'draft'])->get_status() === 409, '1) sin contenido todavía: 409');
pt_pedir('POST', "/plan/{$pid}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'borrador']);
$f = at_pt_plan($pid);
$r = pt_pedir('GET', "/plan/{$pid}/render", null, true, ['modo' => 'draft']);
$d = $r->get_data();
ok($r->get_status() === 200 && ($d['ok'] ?? null) === true, '1) render de la vista previa: 200');
ok(($d['render']['document_type'] ?? '') === 'plan' && ($d['render']['unique_id'] ?? '') === $f->codigo && ($d['render']['draft'] ?? null) === true && ($d['render']['image_briefs'] ?? null) === [], '1) tipo plan, código del plan, borrador y sin fotos');
// Se compara el JSON: el cuerpo puede traer objetos vacíos (images = {}) y dos objetos distintos nunca son ===.
ok(wp_json_encode($d['render'] ?? null) === wp_json_encode(at_pt_armar_render(at_pt_payload($f), at_pt_datos_render($f), false)), '1) es exactamente at_pt_armar_render() (mismo JSON)');
ok(($d['propuesta_uid'] ?? '') === substr(md5($m), 0, 12) && ($d['crm_cliente_id'] ?? null) === $cli['crm'], '1) con propuesta: su código (para reutilizar portada y cierre) y el cliente del CRM (botón del correo)');
ok(array_keys($d) === ['ok', 'render', 'propuesta_uid', 'crm_cliente_id', 'estado'] && ($d['estado'] ?? '') === 'borrador', '1) responde también el estado del plan (el flujo 3 no renderiza una vista previa de un plan «aprobando», «listo» o «enviado»)');
ok(($d['render']['company_name'] ?? '') === '[PRUEBA] Empresa ' . $m && ($d['render']['soporte']['garantia_meses'] ?? null) === 6, '1) el renderer recibe el nombre comercial de la propuesta y la garantía del contrato (6), no la del plan (3)');
$final = pt_pedir('GET', "/plan/{$pid}/render", null, true, ['modo' => 'final'])->get_data();
$slides = array_column($final['render']['image_briefs'] ?? [], 'slide');
ok(($final['render']['draft'] ?? null) === false && in_array('metodo', $slides, true) && in_array('gantt', $slides, true), '1) versión final: con las descripciones de las fotos');
ok(pt_pedir('GET', "/plan/{$pid}/render", null, true, ['modo' => 'otro'])->get_status() === 400 && pt_pedir('GET', '/plan/' . pt_plan_inexistente() . '/render', null, true, ['modo' => 'draft'])->get_status() === 404, '1) modo desconocido: 400; plan que no existe: 404');

// 2) Sin propuesta y cliente sin correo (Review Focus 4).
$m2 = pt_marca();
$cli2 = pt_cliente($m2, false);
$pid2 = at_pt_crear_plan(pt_contrato($cli2['tech'], null));
pt_pedir('POST', "/plan/{$pid2}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'borrador']);
$d2 = pt_pedir('GET', "/plan/{$pid2}/render", null, true, ['modo' => 'final'])->get_data();
$slides2 = array_column($d2['render']['image_briefs'] ?? [], 'slide');
ok(at_pt_plan($pid2)->estado === 'borrador', '2) sin propuesta y sin correo: el borrador igual se hace');
ok(($d2['propuesta_uid'] ?? null) === '' && ($d2['render']['portal_url'] ?? null) === '' && ($d2['crm_cliente_id'] ?? null) === $cli2['crm'], '2) sin código de propuesta ni enlace al portal («Sigue tu proyecto» va sin enlace)');
// Lo que WordPress decide aquí es propuesta_uid: con él n8n reutiliza portada y cierre (Task 14); sin él, no tiene de dónde.
ok(($final['propuesta_uid'] ?? '') !== '' && ($d2['propuesta_uid'] ?? null) === '' && in_array('cover', $slides2, true) && in_array('cierre', $slides2, true), '2) sin propuesta: la final pide todas las fotos (n8n no tiene de dónde reutilizar portada y cierre)');

// 3) Vista previa.
$base = 'https://render.ejemplo.test/p/' . $f->codigo;
$r = pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'draft', 'ok' => true, 'view_url' => $base . '/index.html', 'pdf_url' => $base . '/presentation.pdf', 'faltan' => [], 'nota' => '']);
$f = at_pt_plan($pid);
ok($r->get_status() === 200 && $f->view_url === $base . '/index.html' && $f->pdf_url === $base . '/presentation.pdf' && $f->estado === 'borrador', '3) vista previa lista: guarda los enlaces y el estado no cambia');
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'draft', 'ok' => false, 'view_url' => '', 'pdf_url' => '', 'faltan' => [], 'nota' => 'renderer HTTP 500']);
$f = at_pt_plan($pid);
ok($f->estado === 'borrador' && $f->view_url === $base . '/index.html' && strpos((string) $f->nota, 'renderer HTTP 500') !== false, '3) vista previa fallida: el estado no cambia, quedan los enlaces anteriores y la nota');
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'draft', 'ok' => true, 'view_url' => 'javascript:alert(1)', 'pdf_url' => '', 'faltan' => [], 'nota' => '']);
ok(at_pt_plan($pid)->view_url === $base . '/index.html', '3) un enlace que no es http(s) no se guarda');
ok(pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'otro', 'ok' => true])->get_status() === 400, '3) modo desconocido: 400');

// 4) Versión final.
at_pt_guardar($pid, ['estado' => 'aprobando']);
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'ok' => true, 'view_url' => $base . '/index.html', 'pdf_url' => $base . '/presentation.pdf', 'faltan' => ['gantt', 'fase_2'], 'nota' => '']);
$f = at_pt_plan($pid);
ok($f->estado === 'error' && strpos((string) $f->nota, 'gantt') !== false && strpos((string) $f->nota, 'fase_2') !== false, '4) versión final con fotos faltantes: «error» diciendo cuáles');
at_pt_guardar($pid, ['estado' => 'aprobando']);
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'ok' => false, 'view_url' => '', 'pdf_url' => '', 'faltan' => [], 'nota' => 'renderer HTTP 502']);
ok(at_pt_plan($pid)->estado === 'error' && strpos((string) at_pt_plan($pid)->nota, 'renderer HTTP 502') !== false, '4) versión final fallida: «error» con el motivo');
at_pt_guardar($pid, ['estado' => 'aprobando']);
$r = pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'ok' => true, 'view_url' => $base . '/index.html', 'pdf_url' => $base . '/presentation.pdf', 'faltan' => [], 'nota' => '']);
ok(at_pt_plan($pid)->estado === 'listo' && ($r->get_data()['estado'] ?? '') === 'listo' && at_pt_plan($pid)->nota === '', '4) versión final completa: «aprobando» pasa a «listo»');
// Una vista previa vieja que termina después de la versión final no la pisa (D10).
$vieja = 'https://render.ejemplo.test/p/' . $f->codigo . '/vieja.html';
$r = pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'draft', 'ok' => true, 'view_url' => $vieja, 'pdf_url' => $vieja, 'faltan' => [], 'nota' => '']);
ok(($r->get_data()['estado'] ?? '') === 'listo' && at_pt_plan($pid)->view_url === $base . '/index.html' && at_pt_plan($pid)->pdf_url === $base . '/presentation.pdf' && at_pt_plan($pid)->nota === '', '4) plan «listo»: una vista previa que llega tarde no guarda sus enlaces');
at_pt_guardar($pid, ['estado' => 'aprobando']);
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'draft', 'ok' => false, 'view_url' => '', 'pdf_url' => '', 'faltan' => [], 'nota' => 'renderer HTTP 500']);
ok(at_pt_plan($pid)->estado === 'aprobando' && at_pt_plan($pid)->view_url === $base . '/index.html' && at_pt_plan($pid)->nota === '', '4) plan «aprobando»: el resultado de una vista previa vieja no se anota');
at_pt_guardar($pid, ['estado' => 'listo']);

// 5) n8n avisa un error.
at_pt_guardar($pid2, ['estado' => 'generando', 'nota' => '']);
$r = pt_pedir('POST', "/plan/{$pid2}/error", ['nota' => 'La IA no devolvió JSON <script>alert(1)</script>']);
$f2 = at_pt_plan($pid2);
ok($r->get_status() === 200 && $f2->estado === 'error' && strpos((string) $f2->nota, 'La IA no devolvió JSON') === 0 && strpos((string) $f2->nota, '<script') === false, '5) error desde n8n: «error» con la nota, sin HTML (Review Focus 2)');
pt_pedir('POST', "/plan/{$pid}/error", ['nota' => 'aviso tardío']);
ok(at_pt_plan($pid)->estado === 'listo' && at_pt_plan($pid)->nota === 'aviso tardío', '5) con el plan «listo», un error tardío solo deja la nota');
// Los flujos 1 y 2 llaman a /error ante cualquier respuesta que no sea 200 con ok, también ante el 409 de un borrador
// tardío: ese aviso no puede tumbar un borrador bueno ni un plan que espera su versión final.
at_pt_guardar($pid, ['estado' => 'borrador', 'nota' => '']);
pt_pedir('POST', "/plan/{$pid}/error", ['nota' => 'WordPress no guardó el plan (HTTP 409)']);
ok(at_pt_plan($pid)->estado === 'borrador' && strpos((string) at_pt_plan($pid)->nota, 'HTTP 409') !== false, '5) un borrador duplicado que llegó tarde (409) no tumba un borrador bueno: solo deja la nota');
at_pt_guardar($pid, ['estado' => 'aprobando', 'nota' => '']);
pt_pedir('POST', "/plan/{$pid}/error", ['nota' => 'WordPress no guardó el plan (HTTP 409)']);
$aprobando = at_pt_plan($pid)->estado;
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'ok' => true, 'view_url' => $base . '/index.html', 'pdf_url' => $base . '/presentation.pdf', 'faltan' => [], 'nota' => '']);
ok($aprobando === 'aprobando' && at_pt_plan($pid)->estado === 'listo', '5) ni un plan «aprobando»: sigue esperando y su versión final lo deja «listo»');
at_pt_guardar($pid2, ['estado' => 'cambios']);
pt_pedir('POST', "/plan/{$pid2}/error", []);
ok(at_pt_plan($pid2)->estado === 'error' && at_pt_plan($pid2)->nota === 'n8n avisó un error sin detalle.', '5) sin nota: un texto por defecto');
ok(pt_pedir('POST', '/plan/' . pt_plan_inexistente() . '/error', ['nota' => 'x'])->get_status() === 404, '5) plan que no existe: 404');

fin();
```

- [ ] **Step 13: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/rest-render-wp-test.php; echo "exit=$?"
```
Expected:
```
FALLA falta la función at_pt_rest_render()

1 FALLAS
exit=1
```

- [ ] **Step 14: Implementar render, vista y error**

Agregar al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php`:
```php
/**
 * GET /plan/{id}/render&modo=draft|final — el cuerpo que n8n le manda al renderer, el código de la propuesta
 * (si hay) para reutilizar su portada y su cierre ('' sin propuesta), el cliente del CRM (botón de los correos)
 * y el estado del plan: el flujo 3 no renderiza una vista previa de un plan «aprobando», «listo» o «enviado» (D10).
 */
function at_pt_rest_render(WP_REST_Request $r) {
	$f = at_pt_rest_fila($r);
	if (is_wp_error($f)) {
		return $f;
	}
	$modo = (string) ($r->get_param('modo') ?? 'draft');
	if (!in_array($modo, ['draft', 'final'], true)) {
		return new WP_Error('at_pt_modo', 'modo debe ser draft o final.', ['status' => 400]);
	}
	$plan = at_pt_payload($f);
	if (empty($plan['fases'])) {
		return new WP_Error('at_pt_sin_contenido', 'El plan todavía no tiene contenido.', ['status' => 409]);
	}
	$uid = '';
	if ((int) $f->propuesta_id > 0) {
		global $wpdb;
		$uid = (string) $wpdb->get_var($wpdb->prepare("SELECT unique_link_id FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", (int) $f->propuesta_id));
	}
	// El renderer arma la ruta /p/<uid>/ con esto: solo códigos que él mismo acepta.
	if (!preg_match('/^[A-Za-z0-9_-]{6,64}$/', $uid)) {
		$uid = '';
	}
	return new WP_REST_Response([
		'ok'             => true,
		'render'         => at_pt_armar_render($plan, at_pt_datos_render($f), $modo === 'final'),
		'propuesta_uid'  => $uid,
		// Para el botón de los correos a Luis; 0 si la ficha no está enlazada al CRM.
		'crm_cliente_id' => (int) at_pt_db_partes($f)['crm_id'],
		'estado'         => (string) $f->estado,
	], 200);
}

/**
 * POST /plan/{id}/vista — n8n cuenta cómo salió el render. Con ok y enlace, guarda view_url y pdf_url.
 * Final completa (ok, enlace y sin fotos faltantes) con el plan «aprobando» → «listo». Final incompleta
 * o fallida → «error» con el motivo (si la transición vale; si no, solo la nota). Vista previa fallida:
 * solo la nota, el estado no cambia. Una vista previa que llega con el plan «aprobando», «listo» o «enviado» es
 * vieja (terminó después de pedir la final): no guarda enlaces ni nota, para no pisar la versión final (D10).
 */
function at_pt_rest_vista(WP_REST_Request $r) {
	$f = at_pt_rest_fila($r);
	if (is_wp_error($f)) {
		return $f;
	}
	$p = at_pt_rest_cuerpo($r);
	$modo = (string) ($p['modo'] ?? '');
	if (!in_array($modo, ['draft', 'final'], true)) {
		return new WP_Error('at_pt_modo', 'modo debe ser draft o final.', ['status' => 400]);
	}
	$ok = !empty($p['ok']) && $p['ok'] !== 'false';
	$view = at_pt_db_url($p['view_url'] ?? '');
	$pdf = at_pt_db_url($p['pdf_url'] ?? '');
	$faltan = [];
	foreach ((array) ($p['faltan'] ?? []) as $s) {
		$s = sanitize_key(is_scalar($s) ? (string) $s : '');
		if ($s !== '') {
			$faltan[] = $s;
		}
	}
	$nota = mb_substr(sanitize_textarea_field((string) ($p['nota'] ?? '')), 0, 500);
	$id = (int) $f->id;
	if ($modo === 'draft' && in_array((string) $f->estado, ['aprobando', 'listo', 'enviado'], true)) {
		return new WP_REST_Response(['ok' => true, 'estado' => (string) $f->estado], 200);
	}
	if ($ok && $view !== '') {
		at_pt_guardar($id, ['view_url' => $view, 'pdf_url' => $pdf]);
	}
	if ($modo === 'final') {
		$completa = $ok && $view !== '' && !$faltan;
		if ($completa && (string) $f->estado === 'aprobando') {
			at_pt_cambiar_estado($id, 'listo', '');
		} elseif (!$completa) {
			$motivo = 'La versión final no quedó completa'
				. ($faltan ? ': faltan las fotos de ' . implode(', ', $faltan) : '')
				. ($nota !== '' ? ' (' . $nota . ')' : '') . '.';
			if (at_pt_transicion_valida((string) $f->estado, 'error')) {
				at_pt_cambiar_estado($id, 'error', $motivo);
			} else {
				at_pt_guardar($id, ['nota' => $motivo]);
			}
		}
	} elseif (!$ok) {
		at_pt_guardar($id, ['nota' => 'La vista previa no se pudo generar' . ($nota !== '' ? ': ' . $nota : '.')]);
	}
	$ahora = at_pt_plan($id);
	return new WP_REST_Response(['ok' => true, 'estado' => $ahora ? (string) $ahora->estado : (string) $f->estado], 200);
}

/**
 * POST /plan/{id}/error — n8n avisa que algo falló. Lo llaman los flujos 1 Borrador y 2 Cambios (el 3 Render termina
 * siempre en /vista). Solo pasa a «error» un plan que espera a esos flujos («generando» o «cambios»); en otro estado
 * el aviso es de una corrida vieja o repetida (por ejemplo, el 409 de un borrador tardío) y solo queda la nota: un
 * borrador bueno, un plan «aprobando» o uno «listo» no se tumban.
 */
function at_pt_rest_error(WP_REST_Request $r) {
	$f = at_pt_rest_fila($r);
	if (is_wp_error($f)) {
		return $f;
	}
	$p = at_pt_rest_cuerpo($r);
	$nota = mb_substr(sanitize_textarea_field((string) ($p['nota'] ?? '')), 0, 1000);
	if ($nota === '') {
		$nota = 'n8n avisó un error sin detalle.';
	}
	if (in_array((string) $f->estado, ['generando', 'cambios'], true)) {
		at_pt_cambiar_estado((int) $f->id, 'error', $nota);
	} else {
		at_pt_guardar((int) $f->id, ['nota' => $nota]);
	}
	$ahora = at_pt_plan((int) $f->id);
	return new WP_REST_Response(['ok' => true, 'estado' => $ahora ? (string) $ahora->estado : (string) $f->estado], 200);
}
```

- [ ] **Step 15: Correr y ver pasar; toda la suite del plan hasta aquí**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" -l wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php && "$PHP" tests/plan/rest-render-wp-test.php; echo "exit=$?"
for t in fechas validacion cronograma render; do printf '%-42s ' "tests/plan/$t-test.php"; "$PHP" "tests/plan/$t-test.php" | tail -1; done
for t in tests/plan/*-wp-test.php; do printf '%-42s ' "$t"; "$PHP" "$t" 2>&1 | tail -1; done
for t in rest contrato; do printf '%-42s ' "tests/cierre/$t-wp-test.php"; "$PHP" "$SCR/plan-trabajo/sin-red.php" "tests/cierre/$t-wp-test.php" 2>/dev/null | tail -1; done
git status --short
```
Expected: `No syntax errors detected`, 27 líneas `ok   …`, `TODO OK`, `exit=0` (si falla solo «1) el renderer recibe … la
garantía del contrato (6)», el defecto está en `at_pt_armar_render()`, Task 4, decisión D8); las cuatro pruebas puras → `TODO OK`; las diez
pruebas WordPress del plan (`datos`, `datos-ajustes`, `datos-contexto`, `datos-guardar`, `disparador`, `firma`,
`firma-contrato`, `rest-borrador`, `rest-contexto`, `rest-render`) → `TODO OK`; las dos del cierre que comparten el
namespace `automatiza-tech/v1` o firman como el cliente (`rest` y `contrato`, con `sin-red.php`) → `TODO OK`;
`git status --short` muestra solo `tests/plan/rest-render-wp-test.php` y `rest.php` sin commitear.

- [ ] **Step 16: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add tests/plan/rest-render-wp-test.php wp-content/themes/automatiza-tech/inc/plan-trabajo/rest.php
git commit -m "$(cat <<'EOF'
feat(plan): rutas de render, vista y error para n8n (reutiliza el código de la propuesta y marca listo o error)

POST /plan/{id}/error solo tumba un plan que espera a los flujos 1 y 2 (generando o cambios): el aviso de una corrida
vieja, como el 409 de un borrador tardío, solo deja la nota.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```


### Task 8: Ajustes del plan (tabla de tiempos editable)

Página «CRM Clientes › Ajustes del plan» donde Luis corrige la tabla de tiempos de referencia (decisión 3 y pendiente 1
de Luis en el spec). Guarda la opción `at_pt_duraciones` en JSON, normalizada con `at_pt_normalizar_duraciones()`.
Funciona sin JavaScript: se agrega un tipo de servicio llenando una de las dos filas vacías y se quita marcando
«Quitar». Esta tarea también deja el proceso hijo y las ayudas con que las Tasks 9 y 10 prueban acciones admin-post
(que siempre terminan en `wp_safe_redirect()` + `exit`), siguiendo el patrón de `tests/cierre/panel-respuesta-wp-test.php`.

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/ajustes.php`
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` (lo crea la Task 5 y las Tasks 6 y 7 le suman
  una línea cada una): agregar al final `require_once __DIR__ . '/ajustes.php';`
- Test: `tests/plan/accion-wp-test-run.php` (proceso hijo), `tests/plan/accion-wp-helpers.php` (ayudas),
  `tests/plan/ajustes-wp-test.php`

**Interfaces:**
- Consumes: `at_pt_duraciones_defecto(): array`, `at_pt_normalizar_duraciones(array $tabla): array`,
  `at_pt_etapas(): array` (clave => etiqueta) y `at_pt_etapas_tabla(): array` (`['diseno', 'desarrollo', 'pruebas',
  'implementacion']`, las columnas de la tabla) (Task 2); `at_pt_duraciones(): array` (Task 5, lee la opción JSON o
  devuelve la de defecto); `tests/plan/wp-bootstrap.php` (Task 5: exige `AT_WP_LOAD`, fija `AT_N8N_PLAN_*` a
  `http://127.0.0.1:9/…`, carga WordPress, corta con exit 2 si falta `at_pt_crear_plan`, define `ok($cond, $msg)`,
  `fin()` y `exigir(string ...$funciones)`); el menú `automatiza-crm` que registra
  `wp-content/mu-plugins/crm-ai-completo.php:313` en `admin_menu` (prioridad 10).
- Produces:
  - `at_pt_menu_ajustes(): void` (en `admin_menu`, prioridad 20) → `add_submenu_page('automatiza-crm', 'Ajustes del plan
    de trabajo', 'Ajustes del plan', 'manage_options', 'at-pt-ajustes', 'at_pt_render_ajustes')`
  - `at_pt_columnas_duracion(): array` → `['diseno' => 'Diseño', 'desarrollo' => 'Desarrollo', 'pruebas' => 'Pruebas',
    'implementacion' => 'Implementación']` (se arma con `at_pt_etapas_tabla()` y las etiquetas de `at_pt_etapas()`)
  - `at_pt_clave_de_nombre(string $nombre): string`
  - `at_pt_duraciones_de_filas(array $filas): array` → `['tabla' => array, 'descartadas' => int]`
  - `at_pt_accion_guardar_duraciones(): void` (admin-post `at_pt_guardar_duraciones`, nonce `at_pt_guardar_duraciones`,
    campos `filas[i][clave|nombre|diseno|desarrollo|pruebas|implementacion|quitar]` o `restaurar=1`; vuelve a
    `admin.php?page=at-pt-ajustes&pt_msg=guardada|vacia|restaurada[&pt_descartadas=N]`)
  - `at_pt_render_ajustes(): void`
  - Pruebas: `pt_admin_id(): int`, `pt_correr_accion(string $accion, int $usuario, string $accion_nonce, array $post,
    string $n8n = 'ok'): array` → `['redirect' => string, 'n8n' => [['url' => string, 'cuerpo' => ?array]], 'salida' =>
    string]`, `pt_query(string $url): array` (parámetros de la URL más `'#'` con el fragmento).

Comandos (Git Bash): el shell no guarda variables ni la carpeta entre llamadas, así que **cada bloque `bash` de las
Tasks 8, 9 y 10 empieza con esta misma línea** (igual que en las Tasks 5 a 7), y después usa `"$PHP"`, `$SCR` y
`AT_WP_LOAD` ya exportada:
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
```

- [ ] **Step 1: Proceso hijo para correr acciones admin-post**

Crear `tests/plan/accion-wp-test-run.php`. Contesta las llamadas HTTP (a n8n) con 200, o con error de conexión en modo
`caido`, y las anota; anota también la redirección. Escribe con `fwrite(STDOUT)` para que `header()` no avise nada.
Igual que `wp-bootstrap.php` (Task 5), fija los flujos del plan a un puerto local cerrado antes de cargar WordPress: si
el filtro de abajo fallara, la llamada tampoco llegaría al n8n de PROD.
```php
<?php
// Uso interno de las pruebas del plan (accion-wp-helpers.php): ejecuta una acción admin-post en un proceso PHP
// aparte, porque esas acciones siempre terminan con wp_safe_redirect() + exit.
// Imprime una línea «REDIRECT <url>» por la redirección y una «N8N <url> <cuerpo>» por cada llamada HTTP saliente
// (que nunca sale: se contesta aquí con 200, o con un error de conexión si el modo es «caido»). Se escribe con
// fwrite(STDOUT) para no marcar la salida como enviada (header() de wp_redirect no avisa nada).
// Argumentos: <accion> <user_id> <accion_del_nonce> <archivo_json_con_el_post> <n8n: ok|caido>
$at_pt_wp_load = getenv('AT_WP_LOAD');
if (!$at_pt_wp_load || !is_file($at_pt_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$accion = (string) ($argv[1] ?? '');
$usuario = (int) ($argv[2] ?? 0);
$accion_nonce = (string) ($argv[3] ?? '');
$post = json_decode((string) @file_get_contents((string) ($argv[4] ?? '')), true);
$modo_n8n = (string) ($argv[5] ?? 'ok');
if ($accion === '' || $accion_nonce === '' || !is_array($post)) {
	fwrite(STDERR, "Uso: accion-wp-test-run.php <accion> <user_id> <accion_del_nonce> <archivo_json> <ok|caido>\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8093';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/wp-admin/admin-post.php';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
// Defensa en profundidad (como wp-bootstrap.php de la Task 5): los flujos del plan nunca apuntan al n8n de PROD.
foreach (['AT_N8N_PLAN_BORRADOR' => 'plan-v1-borrador', 'AT_N8N_PLAN_CAMBIOS' => 'plan-v1-cambios', 'AT_N8N_PLAN_RENDER' => 'plan-v1-render'] as $at_pt_c => $at_pt_p) {
	if (!defined($at_pt_c)) {
		define($at_pt_c, 'http://127.0.0.1:9/' . $at_pt_p);
	}
}
define('WP_USE_THEMES', false);
define('WP_ADMIN', true); // admin-post.php real corre en contexto de admin.
require $at_pt_wp_load;
if (!has_action('admin_post_' . $accion)) {
	fwrite(STDERR, "La acción admin_post_{$accion} no está registrada: revisa inc/plan-trabajo/cargar.php.\n");
	exit(2);
}
wp_set_current_user($usuario);
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', function ($pre, $args, $url) use ($modo_n8n) {
	if (strpos($url, 'wp-cron.php') !== false) { // el cron que WordPress lanza al terminar: ni sale ni se anota
		return ['headers' => [], 'body' => '', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
	}
	$cuerpo = $args['body'] ?? '';
	fwrite(STDOUT, 'N8N ' . $url . ' ' . (is_string($cuerpo) ? $cuerpo : (string) wp_json_encode($cuerpo)) . "\n");
	if ($modo_n8n === 'caido') {
		return new WP_Error('http_request_failed', 'cURL error 7: Failed to connect (prueba)');
	}
	return ['headers' => [], 'body' => '{"ok":true}', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}, PHP_INT_MAX, 3);
add_filter('wp_redirect', function ($ubicacion) {
	fwrite(STDOUT, 'REDIRECT ' . $ubicacion . "\n");
	return $ubicacion;
}, PHP_INT_MAX);
$nonce = wp_create_nonce($accion_nonce);
// WordPress entrega $_POST con barras agregadas (wp_magic_quotes) y las acciones hacen wp_unslash(): se imita.
$_POST = wp_slash($post + ['action' => $accion, '_wpnonce' => $nonce]);
$_REQUEST = $_POST;
$_SERVER['REQUEST_METHOD'] = 'POST';
do_action('admin_post_' . $accion);
fwrite(STDOUT, "SIN_EXIT\n");
```

- [ ] **Step 2: Ayudas de las pruebas con acciones**

Crear `tests/plan/accion-wp-helpers.php`:
```php
<?php
// Ayudas de las pruebas del plan que ejecutan acciones admin-post (Tasks 8, 9 y 10). Se carga después de
// wp-bootstrap.php (Task 5).

/** Primer administrador del WordPress local de prueba; corta la prueba si no hay. */
function pt_admin_id(): int {
	$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
	$id = (int) ($admins[0] ?? 0);
	if ($id <= 0) {
		fwrite(STDERR, "No hay un usuario administrador en el WordPress local de prueba.\n");
		exit(2);
	}
	return $id;
}

/**
 * Corre una acción admin-post en un proceso aparte (accion-wp-test-run.php) y devuelve
 * ['redirect' => url o '', 'n8n' => [['url' => …, 'cuerpo' => array|null], …], 'salida' => stdout+stderr].
 * Después vacía la caché de objetos de este proceso para leer lo que el hijo escribió en la base.
 */
function pt_correr_accion(string $accion, int $usuario, string $accion_nonce, array $post, string $n8n = 'ok'): array {
	$archivo = tempnam(sys_get_temp_dir(), 'pt-post-');
	file_put_contents($archivo, wp_json_encode($post, JSON_UNESCAPED_UNICODE));
	$proc = proc_open([PHP_BINARY, __DIR__ . '/accion-wp-test-run.php', $accion, (string) $usuario, $accion_nonce, $archivo, $n8n], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	if (!is_resource($proc)) {
		fwrite(STDERR, "No se pudo lanzar el proceso hijo de la prueba.\n");
		exit(2);
	}
	$salida = (string) stream_get_contents($pipes[1]);
	$errores = (string) stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
	@unlink($archivo);
	wp_cache_flush();
	$r = ['redirect' => '', 'n8n' => [], 'salida' => $salida . $errores];
	foreach (preg_split('/\r\n|\n/', $salida) as $linea) {
		if (strpos($linea, 'REDIRECT ') === 0) {
			$r['redirect'] = substr($linea, 9);
		} elseif (strpos($linea, 'N8N ') === 0) {
			$partes = explode(' ', substr($linea, 4), 2);
			$r['n8n'][] = ['url' => $partes[0], 'cuerpo' => json_decode($partes[1] ?? '', true)];
		}
	}
	return $r;
}

/** Parámetros de la URL de una redirección (page, id, pt, pt_msg, …) más '#' con el fragmento. */
function pt_query(string $url): array {
	$q = [];
	parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $q);
	$q['#'] = (string) wp_parse_url($url, PHP_URL_FRAGMENT);
	return $q;
}
```

- [ ] **Step 3: Escribir la prueba que falla**

Crear `tests/plan/ajustes-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/ajustes-wp-test.php
// Task 8: CRM › Ajustes del plan (tabla de tiempos de referencia editable, opción at_pt_duraciones en JSON).
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';

if (!function_exists('at_pt_render_ajustes') || !function_exists('at_pt_duraciones_de_filas')) {
	fwrite(STDERR, "ajustes.php no está cargado: revisa inc/plan-trabajo/cargar.php.\n");
	exit(2);
}
// La base local es compartida: se guarda la opción tal como estaba y se deja igual al terminar, también si la
// prueba se cae a mitad de camino (register_shutdown_function corre igual tras un error fatal o un exit).
$opcion_antes = get_option('at_pt_duraciones', null);
register_shutdown_function(function () use ($opcion_antes) {
	if ($opcion_antes === null) {
		delete_option('at_pt_duraciones');
	} else {
		update_option('at_pt_duraciones', $opcion_antes, false);
	}
});
$admin = pt_admin_id();
wp_set_current_user($admin);

// ---------- Filas del formulario → tabla ----------
$fila = function (string $clave, string $nombre, array $dias, bool $quitar = false): array {
	$f = ['clave' => $clave, 'nombre' => $nombre, 'diseno' => $dias[0], 'desarrollo' => $dias[1], 'pruebas' => $dias[2], 'implementacion' => $dias[3]];
	return $quitar ? $f + ['quitar' => '1'] : $f;
};
$r = at_pt_duraciones_de_filas([
	$fila('sitio_una_pagina', 'Sitio de una página', ['4', '6', '2', '1']),
	$fila('', 'Automatización móvil', ['2', '', '1', '1']),
	$fila('', '', ['', '', '', '']),
	$fila('muchos_dias', 'Demasiados días', ['61', '1', '1', '1']),
	$fila('letras', 'Con letras', ['dos', '1', '1', '1']),
	$fila('sitio_una_pagina', 'Clave repetida', ['1', '1', '1', '1']),
	$fila('X-Mala', 'Clave con mayúsculas', ['1', '1', '1', '1']),
	$fila('quitada', 'Marcada para quitar', ['1', '1', '1', '1'], true),
	$fila('sin_nombre', '', ['1', '1', '1', '1']),
]);
ok(array_keys($r['tabla']) === ['sitio_una_pagina', 'automatizacion_movil'], 'quedan solo las dos filas válidas, en su orden: ' . implode(', ', array_keys($r['tabla'])));
$s = $r['tabla']['sitio_una_pagina'] ?? [];
ok(($s['nombre'] ?? '') === 'Sitio de una página' && ($s['diseno'] ?? null) === 4 && ($s['desarrollo'] ?? null) === 6 && ($s['pruebas'] ?? null) === 2 && ($s['implementacion'] ?? null) === 1, 'la fila editada queda con sus días como enteros');
$a = $r['tabla']['automatizacion_movil'] ?? [];
ok(($a['desarrollo'] ?? null) === 0, 'un día en blanco vale 0');
ok($r['descartadas'] === 5, 'se cuentan 5 filas descartadas (61 días, letras, clave repetida, clave inválida, sin nombre): ' . $r['descartadas']);
ok(at_pt_clave_de_nombre('Automatización móvil') === 'automatizacion_movil' && at_pt_clave_de_nombre('¡Sitio  web / tienda!') === 'sitio_web_tienda', 'la clave se arma del nombre sin tildes ni signos');

// ---------- Guardar por admin-post ----------
$r = pt_correr_accion('at_pt_guardar_duraciones', $admin, 'at_pt_guardar_duraciones', ['filas' => [
	$fila('sitio_una_pagina', 'Sitio de una página', ['4', '6', '2', '1']),
	$fila('plataforma', 'Plataforma o sistema a medida', ['8', '22', '5', '3']),
	$fila('mala', 'Fila mala', ['99', '1', '1', '1']),
]]);
$q = pt_query($r['redirect']);
ok(($q['page'] ?? '') === 'at-pt-ajustes' && ($q['pt_msg'] ?? '') === 'guardada' && ($q['pt_descartadas'] ?? '') === '1', 'Guardar: vuelve a los ajustes con «guardada» y 1 fila descartada: ' . $r['redirect']);
$crudo = get_option('at_pt_duraciones');
ok(is_string($crudo) && is_array(json_decode($crudo, true)), 'la opción at_pt_duraciones queda guardada como JSON');
$t = at_pt_duraciones();
ok(array_keys($t) === ['sitio_una_pagina', 'plataforma'] && (int) $t['plataforma']['desarrollo'] === 22, 'at_pt_duraciones() devuelve la tabla guardada');

// Sin filas válidas: no se guarda nada.
$r = pt_correr_accion('at_pt_guardar_duraciones', $admin, 'at_pt_guardar_duraciones', ['filas' => [$fila('mala', 'Fila mala', ['99', '1', '1', '1'])]]);
ok((pt_query($r['redirect'])['pt_msg'] ?? '') === 'vacia', 'solo filas inválidas: avisa «vacia»');
ok(get_option('at_pt_duraciones') === $crudo, 'solo filas inválidas: la tabla guardada no cambia');

// Sin permiso (visitante sin sesión, id 0) y con un nonce de otra acción: no cambia nada.
$r = pt_correr_accion('at_pt_guardar_duraciones', 0, 'at_pt_guardar_duraciones', ['restaurar' => '1']);
ok($r['redirect'] === '' && strpos($r['salida'], 'Sin permiso') !== false, 'sin manage_options: se corta con «Sin permiso»');
$r = pt_correr_accion('at_pt_guardar_duraciones', $admin, 'otra_accion', ['restaurar' => '1']);
ok($r['redirect'] === '', 'con un nonce de otra acción: no redirige ni guarda');
ok(get_option('at_pt_duraciones') === $crudo, 'ni el visitante ni el nonce ajeno cambiaron la tabla');

// Restaurar la propuesta.
$r = pt_correr_accion('at_pt_guardar_duraciones', $admin, 'at_pt_guardar_duraciones', ['restaurar' => '1']);
ok((pt_query($r['redirect'])['pt_msg'] ?? '') === 'restaurada', 'Restaurar: avisa «restaurada»');
ok(get_option('at_pt_duraciones', 'NO') === 'NO' && at_pt_duraciones() === at_pt_duraciones_defecto(), 'Restaurar: se borra la opción y vuelve la tabla propuesta');

// ---------- La página ----------
$_GET = ['page' => 'at-pt-ajustes', 'pt_msg' => 'guardada', 'pt_descartadas' => '2'];
ob_start();
at_pt_render_ajustes();
$h = (string) ob_get_clean();
ok(strpos($h, 'name="action" value="at_pt_guardar_duraciones"') !== false && strpos($h, 'name="_wpnonce"') !== false, 'el formulario va a admin-post con su nonce');
ok(strpos($h, 'name="filas[0][nombre]" value="Sitio de una página"') !== false && strpos($h, 'name="filas[0][diseno]" value="3"') !== false, 'muestra la tabla vigente (Sitio de una página, diseño 3)');
$n = count(at_pt_duraciones_defecto());
ok(strpos($h, 'name="filas[' . ($n + 1) . '][nombre]" value=""') !== false && strpos($h, 'name="filas[' . ($n + 2) . ']') === false, 'agrega dos filas vacías para sumar tipos de servicio');
ok(strpos($h, 'Tabla guardada.') !== false && strpos($h, '2 fila(s) no se guardaron') !== false, 'muestra el aviso y cuántas filas se descartaron');
ok(strpos($h, 'Restaurar la tabla propuesta') !== false && strpos($h, 'return confirm(') !== false, 'Restaurar pide confirmación');
ok(has_action('admin_menu', 'at_pt_menu_ajustes') === 20, 'el submenú se registra en admin_menu con prioridad 20 (después del menú del CRM)');

$_GET = [];
fin(); // la opción at_pt_duraciones vuelve a como estaba en la función de cierre de arriba
```

- [ ] **Step 4: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/ajustes-wp-test.php; echo "exit=$?"
```
Expected (falla porque `ajustes.php` todavía no existe):
```
ajustes.php no está cargado: revisa inc/plan-trabajo/cargar.php.
exit=2
```

- [ ] **Step 5: Implementar `ajustes.php`**

Crear `wp-content/themes/automatiza-tech/inc/plan-trabajo/ajustes.php`:
```php
<?php
/**
 * CRM › Ajustes del plan: tabla de tiempos de referencia (días hábiles por tipo de servicio y etapa) con la que
 * el borrador reparte los días de cada etapa (at_pt_aplicar_tabla). La edita Luis y se guarda normalizada en la
 * opción at_pt_duraciones (JSON). Sin JavaScript: se agrega un tipo llenando una fila vacía y se quita marcando
 * «Quitar». Spec: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md §3.
 */
if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', 'at_pt_menu_ajustes', 20);
add_action('admin_post_at_pt_guardar_duraciones', 'at_pt_accion_guardar_duraciones');

/** Submenú bajo «CRM Clientes» (el menú padre lo registra crm-ai-completo.php con prioridad 10). */
function at_pt_menu_ajustes(): void {
	add_submenu_page('automatiza-crm', 'Ajustes del plan de trabajo', 'Ajustes del plan', 'manage_options', 'at-pt-ajustes', 'at_pt_render_ajustes');
}

/** Columnas de días de la tabla, en el orden en que se muestran (las etapas de la tabla, con su etiqueta; Task 2). */
function at_pt_columnas_duracion(): array {
	$etiquetas = at_pt_etapas();
	$columnas = [];
	foreach (at_pt_etapas_tabla() as $etapa) {
		$columnas[$etapa] = (string) ($etiquetas[$etapa] ?? $etapa);
	}
	return $columnas;
}

/** Clave de servicio desde su nombre: «Automatización móvil» → automatizacion_movil (máximo 40 caracteres). */
function at_pt_clave_de_nombre(string $nombre): string {
	$c = str_replace('-', '_', sanitize_title($nombre));
	$c = (string) preg_replace('/[^a-z0-9_]/', '', $c);
	return substr(trim($c, '_'), 0, 40);
}

/**
 * Filas del formulario → tabla (clave => nombre y días). Ignora las filas vacías y las marcadas «Quitar»;
 * descarta y cuenta las que traen algo inválido (clave fuera de [a-z0-9_]{2,40}, nombre vacío o de más de 60
 * caracteres, días que no son un entero de 0 a 60) o una clave repetida. Un día en blanco vale 0. El resultado
 * pasa además por at_pt_normalizar_duraciones() (Task 2), que es la regla final.
 *
 * @return array{tabla: array, descartadas: int}
 */
function at_pt_duraciones_de_filas(array $filas): array {
	$tabla = [];
	$descartadas = 0;
	foreach ($filas as $f) {
		if (!is_array($f) || !empty($f['quitar'])) {
			continue;
		}
		$nombre = trim(sanitize_text_field((string) ($f['nombre'] ?? '')));
		$clave = trim((string) ($f['clave'] ?? ''));
		$dias = [];
		$vacia = $nombre === '' && $clave === '';
		foreach (at_pt_columnas_duracion() as $k => $_) {
			$v = trim((string) ($f[$k] ?? ''));
			if ($v !== '') {
				$vacia = false;
			}
			$dias[$k] = $v === '' ? '0' : $v;
		}
		if ($vacia) {
			continue;
		}
		if ($clave === '') {
			$clave = at_pt_clave_de_nombre($nombre);
		}
		$valida = (bool) preg_match('/^[a-z0-9_]{2,40}$/', $clave) && $nombre !== '' && mb_strlen($nombre) <= 60 && !isset($tabla[$clave]);
		foreach ($dias as $v) {
			if (!preg_match('/^\d{1,2}$/', $v) || (int) $v > 60) {
				$valida = false;
			}
		}
		if (!$valida) {
			$descartadas++;
			continue;
		}
		$tabla[$clave] = ['nombre' => $nombre] + array_map('intval', $dias);
	}
	$normal = at_pt_normalizar_duraciones($tabla);
	return ['tabla' => $normal, 'descartadas' => $descartadas + count($tabla) - count($normal)];
}

/** Guarda la tabla (o restaura la propuesta) y vuelve a los ajustes con un aviso. Siempre termina la petición. */
function at_pt_accion_guardar_duraciones(): void {
	check_admin_referer('at_pt_guardar_duraciones');
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$url = admin_url('admin.php?page=at-pt-ajustes');
	if (!empty($_POST['restaurar'])) {
		delete_option('at_pt_duraciones');
		wp_safe_redirect(add_query_arg('pt_msg', 'restaurada', $url));
		exit;
	}
	$filas = isset($_POST['filas']) && is_array($_POST['filas']) ? wp_unslash($_POST['filas']) : [];
	$r = at_pt_duraciones_de_filas($filas);
	if (!$r['tabla']) {
		wp_safe_redirect(add_query_arg('pt_msg', 'vacia', $url));
		exit;
	}
	update_option('at_pt_duraciones', wp_json_encode($r['tabla'], JSON_UNESCAPED_UNICODE), false);
	wp_safe_redirect(add_query_arg(['pt_msg' => 'guardada', 'pt_descartadas' => (int) $r['descartadas']], $url));
	exit;
}

/** Página «Ajustes del plan». */
function at_pt_render_ajustes(): void {
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.');
	}
	$avisos = [
		'guardada'   => ['notice-success', 'Tabla guardada. Se usa en los borradores nuevos (al crear un plan o con «Reintentar borrador»); «Pedir cambios» y «Guardar y recalcular» no la vuelven a aplicar, y los días que editaste a mano nunca se tocan.'],
		'vacia'      => ['notice-error', 'La tabla quedó sin filas válidas: no se guardó nada.'],
		'restaurada' => ['notice-success', 'Se restauró la tabla propuesta.'],
	];
	$msg = sanitize_key(wp_unslash($_GET['pt_msg'] ?? ''));
	$descartadas = absint($_GET['pt_descartadas'] ?? 0);
	$filas = [];
	foreach (at_pt_duraciones() as $clave => $t) {
		$filas[] = ['clave' => (string) $clave] + $t;
	}
	$vacia = ['clave' => '', 'nombre' => ''] + array_fill_keys(array_keys(at_pt_columnas_duracion()), '');
	$filas[] = $vacia;
	$filas[] = $vacia;
	$nonce = wp_create_nonce('at_pt_guardar_duraciones');
	$accion = admin_url('admin-post.php');
	?>
	<div class="wrap at-pt-ajustes">
		<h1>Ajustes del plan de trabajo</h1>
		<?php if (isset($avisos[$msg])): ?>
			<div class="notice <?php echo esc_attr($avisos[$msg][0]); ?> is-dismissible"><p><?php echo esc_html($avisos[$msg][1]); ?></p>
			<?php if ($descartadas > 0): ?><p><?php echo esc_html(sprintf('%d fila(s) no se guardaron por tener datos inválidos o una clave repetida.', $descartadas)); ?></p><?php endif; ?>
			</div>
		<?php endif; ?>
		<p>Días hábiles de referencia por tipo de servicio. Con esta tabla el borrador del plan reparte los días de cada etapa entre sus actividades; lo que no calza con la tabla queda como estimación de la IA para que la revises.</p>
		<p>Se suman aparte el arranque (reunión de inicio, 1 día, y entrega de logo, textos y accesos, 3 días; cláusula 4.2 del contrato) y la revisión del cliente (5 días hábiles después de cada entrega; cláusula 6.1).</p>
		<form method="post" action="<?php echo esc_url($accion); ?>">
			<input type="hidden" name="action" value="at_pt_guardar_duraciones">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
			<div style="overflow-x:auto">
			<table class="widefat striped at-pt-duraciones">
				<thead><tr><th scope="col">Tipo de servicio</th><th scope="col">Clave</th>
				<?php foreach (at_pt_columnas_duracion() as $titulo): ?><th scope="col"><?php echo esc_html($titulo); ?></th><?php endforeach; ?>
				<th scope="col">Suma</th><th scope="col">Quitar</th></tr></thead>
				<tbody>
				<?php foreach ($filas as $i => $f): $suma = 0; ?>
					<tr>
						<td><input type="text" class="regular-text" name="filas[<?php echo (int) $i; ?>][nombre]" value="<?php echo esc_attr((string) $f['nombre']); ?>" maxlength="60" aria-label="Tipo de servicio"></td>
						<td><input type="text" class="regular-text" name="filas[<?php echo (int) $i; ?>][clave]" value="<?php echo esc_attr((string) $f['clave']); ?>" maxlength="40" pattern="[a-z0-9_]{2,40}" aria-label="Clave" placeholder="se arma del nombre"></td>
						<?php foreach (at_pt_columnas_duracion() as $k => $titulo): $suma += (int) $f[$k]; ?>
						<td><input type="number" class="small-text" min="0" max="60" step="1" name="filas[<?php echo (int) $i; ?>][<?php echo esc_attr($k); ?>]" value="<?php echo esc_attr((string) $f[$k]); ?>" aria-label="<?php echo esc_attr($titulo); ?>"></td>
						<?php endforeach; ?>
						<td><?php echo $f['nombre'] !== '' ? (int) $suma : ''; ?></td>
						<td><input type="checkbox" name="filas[<?php echo (int) $i; ?>][quitar]" value="1" aria-label="Quitar esta fila"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<p class="description">Para agregar un tipo de servicio, llena una fila vacía (la clave se arma sola desde el nombre). La IA elige el servicio de cada actividad por su clave: si cambias una clave, los planes nuevos dejan de reconocer la anterior.</p>
			<p class="submit"><button type="submit" class="button button-primary">Guardar tabla</button></p>
		</form>
		<form method="post" action="<?php echo esc_url($accion); ?>" onsubmit="return confirm(<?php echo esc_attr(wp_json_encode('¿Volver a la tabla propuesta? Se pierden tus cambios en esta tabla.', JSON_UNESCAPED_UNICODE)); ?>);">
			<input type="hidden" name="action" value="at_pt_guardar_duraciones">
			<input type="hidden" name="restaurar" value="1">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
			<p><button type="submit" class="button">Restaurar la tabla propuesta</button></p>
		</form>
	</div>
	<?php
}
```

- [ ] **Step 6: Cargarlo desde `cargar.php`**

Se agrega una línea al final, igual que las Tasks 6 y 7 (repetir el paso no duplica nada):
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
F=wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php
L="require_once __DIR__ . '/ajustes.php';"
[ "$(tail -c1 "$F" | wc -l)" -eq 1 ] || echo >> "$F"
grep -qxF "$L" "$F" || printf '%s\n' "$L" >> "$F"
"$PHP" -l "$F" && tail -2 "$F"
```
Expected: `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` y las dos últimas
líneas `require_once __DIR__ . '/rest.php';` y `require_once __DIR__ . '/ajustes.php';`.

- [ ] **Step 7: Correr la prueba y ver que pasa**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/ajustes-wp-test.php; echo "exit=$?"
```
Expected: 21 líneas `ok …`, ninguna `FALLA`, y al final `TODO OK` y `exit=0`. La opción `at_pt_duraciones` queda como
estaba antes de la prueba (la restaura la función de cierre de la prueba, también si se cae).

- [ ] **Step 8: Sintaxis**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
for f in wp-content/themes/automatiza-tech/inc/plan-trabajo/ajustes.php tests/plan/accion-wp-test-run.php tests/plan/accion-wp-helpers.php tests/plan/ajustes-wp-test.php; do "$PHP" -l "$f"; done
```
Expected: cuatro líneas `No syntax errors detected in …`.

- [ ] **Step 9: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/ajustes.php wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php tests/plan/accion-wp-test-run.php tests/plan/accion-wp-helpers.php tests/plan/ajustes-wp-test.php
git commit -m "$(cat <<'EOF'
feat(plan): ajustes del plan con la tabla de tiempos editable

Submenú CRM › Ajustes del plan: tabla de días hábiles por servicio y etapa,
guardada normalizada en la opción at_pt_duraciones (JSON), con restaurar la
propuesta. Proceso hijo y ayudas para probar acciones admin-post.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

### Task 9: Pestaña «🗓️ Plan de trabajo» en la ficha del cliente del CRM

La pestaña va en la ficha del cliente oficial (decisión 6), después de «📜 Contratos y operación», solo para quien
tiene `manage_options`. Muestra estado, fechas y documento; la tabla editable (bloque, actividad, responsable, días,
«en paralelo», origen de cada duración y fechas); los textos de las láminas; y los botones «Crear plan de trabajo»,
«Guardar y recalcular», «Pedir cambios», «Aprobar (N fotos ≈ US$X)», «Destrabar», «Reintentar borrador» / «Volver al
borrador» y «Agendar llamada de seguimiento». Todo lo que sale en el documento se edita aquí sin gastar IA (decisión 11):
nombre, detalle, responsable y días de cada actividad; bloques con su entregable; descripción de cada fase; qué
necesitamos, reuniones, hitos y servicios mensuales. La garantía se muestra de solo lectura con la leyenda «Viene del
contrato» (D8: el contrato manda; el panel no la edita ni la guarda). La tabla la serializa `plan-trabajo.js` en el campo
oculto `plan_json`; el servidor sigue el orden de uso único de la Task 2 para «Guardar desde el panel» (D1/D2):
`at_pt_validar_plan()` → `at_pt_marcar_ediciones($anterior, $nuevo, 'luis')` (firma de 3 parámetros, marca explícita)
→ `at_pt_validar_plan()` otra vez → `at_pt_calcular_fechas()`.

Dos hechos del código que esta tarea resuelve (medidos en el sitio local):
- El JS de pestañas del CRM (`crm-ai-completo.php:3248-3262`) no mira la URL: el `#tab-plan` de la redirección no
  abre la pestaña. Lo hace `plan-trabajo.js`.
- La barra de pestañas tiene `overflow: hidden` sin salto de línea en escritorio (`crm-ai-completo.php:7873-7881`): con
  una quinta pestaña, a 1366 px (columna de 565 px, 4 pestañas = 544 px, la nueva mide 147) y aun a 1920 (690 px) la
  nueva quedaría cortada; y en celular la columna de la grilla toma el ancho de la barra y estira la página (a 390 px:
  543 px hoy, 691 px con 5 pestañas).
  `plan-trabajo.css` hace que salte de línea en escritorio y que la columna del plan quepa en celular (417 px; los
  27 px que sobran son el botón «📅 Agendar Seguimiento» del historial del CRM, que ya se sale hoy).

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php`
- Create: `wp-content/themes/automatiza-tech/assets/css/plan-trabajo.css`
- Create: `wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js`
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` (agregar `panel.php`)
- Modify: `wp-content/mu-plugins/crm-ai-completo.php:1984-1985` (botón de la pestaña) y `:2240-2241` (panel)
- Test: `tests/plan/fixtures-panel.php`, `tests/plan/panel-render-wp-test.php`, `tests/plan/panel-js-wp-test.php`
  (el JS en Chrome o Edge sin ventana), `tests/plan/panel-acciones-wp-test.php`, `tests/plan/ficha-crm-wp-test.php`

**Interfaces:**
- Consumes:
  - Task 2: `at_pt_fases_validas(): array`, `at_pt_responsables(): array`, `at_pt_origenes(): array` (`['tabla' =>
    'Tabla de tiempos', 'ia' => 'IA · revisar', 'luis' => 'Editado por Luis']`, etiquetas del panel),
    `at_pt_duraciones_defecto(): array`, `at_pt_validar_plan(array $plan): array` (`['ok', 'errores', 'avisos', 'plan']`;
    con errores `plan` es `[]`), `at_pt_aplicar_tabla(array $plan, array $tabla): array`,
    `at_pt_marcar_ediciones(array $anterior, array $nuevo, string $marca = 'luis'): array` (el panel pasa `'luis'`
    explícito; la REST de la Task 7 pasa `'ia'` en «cambios», D1)
  - Task 1: `at_pt_es_habil(string $f, array $feriados): bool`, `at_pt_siguiente_habil(string $f, array $feriados): string`,
    `at_pt_inicio_por_defecto(string $fecha_firma, array $feriados): string`
  - Task 3: `at_pt_calcular_fechas(array $plan, string $inicio, array $feriados): array`
  - Task 4: `at_pt_costo_fotos(array $image_briefs, bool $hay_propuesta): array` (`['fotos', 'usd_lista', 'usd_max']`),
    `at_pt_transicion_valida(string $de, string $a): bool`
  - Task 5: `at_pt_tabla(): string`, `at_pt_plan(int $id): ?object`, `at_pt_plan_de_contrato(int $contrato_id): ?object`,
    `at_pt_planes_de_crm(int $crm_id): array`, `at_pt_payload(object $fila): array`,
    `at_pt_db_partes(object $fila): array` (su `garantia_meses` es la garantía del contrato que muestra el panel, D8),
    `at_pt_crear_plan(int $contrato_id): int|WP_Error`, `at_pt_guardar(int $id, array $campos): bool`,
    `at_pt_cambiar_estado(int $id, string $a, string $nota = ''): bool`, `at_pt_feriados(): array`,
    `at_pt_contratos_sin_plan(int $crm_id): array` (filas de `automatiza_contracts`: `id`, `contract_number`, `signed_at`)
  - Task 6: `AT_N8N_PLAN_CAMBIOS`, `at_pt_llamar_n8n(string $url, array $cuerpo, int $timeout = 15): string` (`''` o
    el motivo, p. ej. `n8n no respondió: …`), `at_pt_iniciar_borrador(int $plan_id, int $timeout = 15): string` (cuerpo
    `{"id", "codigo"}`; desde `error` pasa sola a `generando`, y si n8n no recibe el aviso deja el plan en `error` con
    `No se pudo pedir el borrador: <motivo>`), `at_pt_pedir_render(int $plan_id, string $modo, bool $aviso): string`
    (cuerpo `{"id", "modo", "aviso"}`; si falla: en `final` con el plan en `aprobando` lo pasa a `error`; si no, solo
    anota `No se pudo pedir la vista previa: <motivo>` en la nota). «Pedir cambios» manda `{"id", "codigo"}` a
    `AT_N8N_PLAN_CAMBIOS` y, si falla, `error` con `No se pudo pedir los cambios: <motivo>` (contrato de la Task 6).
  - mu-plugin: `at_crm_url_portal(int $cliente_id): string` (`crm-ai-completo.php:8562`)
  - Task 8: `pt_admin_id()`, `pt_correr_accion()`, `pt_query()`
- Produces:
  - `at_pt_url_ficha(int $crm_id, int $plan_id = 0, string $msg = ''): string` →
    `admin.php?page=automatiza-crm-ficha&id=<crm>[&pt=<plan>][&pt_msg=<clave>]#tab-plan`
  - `at_pt_render_pestana(array $cliente): void` (`$cliente` = fila ARRAY_A de `wp_crm_clientes`; la ficha solo la llama
    si `is_array($cliente)`: con un id que no existe, `get_row()` devuelve `null`)
  - `at_pt_encolar_assets(): void` (en `admin_enqueue_scripts`; encola `at-plan-trabajo` CSS y JS solo con
    `page=automatiza-crm-ficha`)
  - `at_pt_crm_de_plan(object $fila): int` — cliente del CRM de un plan: el enlace actual de su ficha operativa
    (`automatiza_tech_clients.crm_cliente_id`, como `at_pt_planes_de_crm()`) o, si no hay, el que se guardó al crearlo
    (la usan la vuelta de las acciones y `at_pt_datos_agenda()` de la Task 10)
  - Ayudas: `at_pt_estados_etiqueta(): array`, `at_pt_mensajes_panel(): array` (claves de `pt_msg`: `creado`,
    `ya_existe`, `no_se_pudo`, `guardado`, `guardado_sin_vista`, `error_guardar`, `invalido`, `json_invalido`,
    `no_editable`, `cambios_pedidos`, `sin_comentarios`, `aprobando`, `destrabado`, `reintentando`, `vuelto_borrador`,
    `n8n_fallo`, `transicion`, `sin_plan`), `at_pt_guardar_detalles(int $plan_id, array $lineas): void`,
    `at_pt_tomar_detalles(int $plan_id): array`, `at_pt_aviso_panel(int $plan_id): string`,
    `at_pt_panel_fecha(string $ymd, bool $con_anio = false): string`, `at_pt_fase_de(array $plan, string $clave): array`,
    `at_pt_hitos_editables(array $plan): array`, `at_pt_lineas_pares(array $items, string $a, string $b): string`,
    `at_pt_html_actividad(array $a): void`, `at_pt_html_bloque(array $b): void`,
    `at_pt_render_crear(object $c, int $crm_id): void`, `at_pt_render_selector(array $planes, int $actual, int $crm_id): void`,
    `at_pt_render_plan(object $fila, int $crm_id): void`
  - Acciones admin-post (todas terminan en `at_pt_volver()` = redirect + exit): `at_pt_crear` (campos `contrato_id`,
    `crm_id`; nonce `at_pt_crear_<contrato_id>`), `at_pt_guardar` (`plan_id`, `crm_id`, `fecha_inicio`, `plan_json`,
    `proyecto`, `necesitamos_de_ti`, `reuniones`, `hitos`, `mensuales`; ya no recibe `garantia_meses`: si un POST la
    trae, se ignora, D8), `at_pt_cambios` (`plan_id`,
    `crm_id`, `comentarios`), `at_pt_aprobar`, `at_pt_destrabar`, `at_pt_reintentar` (`plan_id`, `crm_id`); nonce de plan
    `at_pt_plan_<plan_id>`. Funciones: `at_pt_volver(int $crm_id, int $plan_id, string $msg): void`,
    `at_pt_accion_plan(): array` (`[fila, crm_id]`), `at_pt_error_si_sigue(int $plan_id, string $estado_intermedio,
    string $nota): void`, `at_pt_contrato_es_del_cliente(int $contrato_id, int $crm_id): bool`,
    `at_pt_fecha_inicio_elegida(string $pedida, object $fila, array $plan, array $feriados): string`,
    `at_pt_plan_desde_panel(array $anterior, array $editado, array $textos): array`, `at_pt_accion_crear(): void`,
    `at_pt_accion_guardar(): void`, `at_pt_accion_cambios(): void`, `at_pt_accion_aprobar(): void`,
    `at_pt_accion_destrabar(): void`, `at_pt_accion_reintentar(): void`
  - JS: `window.atPlanTrabajo = { serializar(form) → {fases: [{clave, descripcion, bloques: [{nombre, entregable, entrega,
    actividades: [{nombre, detalle, responsable, dias_habiles, en_paralelo, servicio, etapa, origen}]}]}]},
    abrirPestanaPlan() }` (`dias_habiles` es número; `detalle` sale de su campo editable)
  - Pruebas: `tests/plan/fixtures-panel.php` con `ptc_marca()`, `ptc_cliente(string $marca, string $correo): array`
    (`['crm', 'tech']`), `ptc_propuesta(string $marca): int`, `ptc_contrato(int $tech, string $marca, ?int $propuesta,
    string $tipo = 'servicios', string $estado = 'signed', string $correo = ''): int`, `ptc_plan(int $contrato): object`,
    `ptc_plan_base(): array`, `ptc_sembrar(int $plan_id, string $inicio = '2026-10-05'): array`,
    `ptc_estado(int $plan_id, string $estado, string $nota = ''): void`, `ptc_actividad(array $plan, string $nombre): array`,
    `ptc_plan_json(array $payload): string` (lo que manda `plan-trabajo.js` en `plan_json`; la prueba del JS exige que
    sea idéntico), `ptc_cliente_fila(int $crm): array`, `ptc_limpiar(): void` (se registra sola al cerrar el proceso,
    salvo que se defina `PTC_CONSERVAR`; la Task 10 los usa)

- [ ] **Step 1: Datos de prueba de la pestaña**

Crear `tests/plan/fixtures-panel.php` (todo `[PRUEBA]` y `prueba-plan-…@example.com`; corta cualquier llamada HTTP y
correo del proceso de la prueba, y borra lo que creó al cerrar el proceso aunque la prueba se caiga, como
`datos-prueba.php` de la Task 5). Son ayudas propias y no las `pt_*` de la Task 5 porque la pestaña y la agenda necesitan
teléfono en el CRM y en el contrato, planes sembrados con la tabla aplicada y reuniones de seguimiento que borrar:
```php
<?php
// Datos de prueba de la pestaña del plan (Task 9) y de la agenda (Task 10). Todo va marcado [PRUEBA], con correos
// prueba-plan-…@example.com, y ptc_limpiar() lo borra: se registra al cerrar el proceso (también si la prueba se cae
// con un error fatal o un exit), salvo que el script defina PTC_CONSERVAR antes de cargar este archivo (los datos
// para mirar la pestaña en el navegador). Se carga después de wp-bootstrap.php.
$GLOBALS['ptc_creado'] = ['crm' => [], 'tech' => [], 'contratos' => [], 'propuestas' => [], 'planes' => [], 'reuniones' => []];

// Desde el proceso de la prueba no sale ninguna llamada HTTP (n8n, correo por API, cron) ni ningún correo: se cortan
// aquí. Los procesos hijos (accion-wp-test-run.php) tienen su propio filtro, que contesta y anota las llamadas a n8n.
add_filter('pre_http_request', function ($pre, $args, $url) {
	return new WP_Error('prueba_sin_red', 'Llamada HTTP cortada en la prueba: ' . $url);
}, PHP_INT_MAX, 3);
add_filter('pre_wp_mail', '__return_true', PHP_INT_MAX);

function ptc_marca(): string {
	return strtolower(wp_generate_password(6, false, false));
}

/** Cliente del CRM con su ficha operativa enlazada. $correo '' = cliente sin correo (y sin portal). */
function ptc_cliente(string $marca, string $correo): array {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'crm_clientes', [
		'nombre' => '[PRUEBA] Cliente Plan ' . $marca, 'email' => $correo, 'empresa' => '[PRUEBA] Empresa ' . $marca,
		'telefono' => '+56 9 1111 1111', 'tipo' => 'cliente', 'estado' => 'contratado',
	]);
	$crm = (int) $wpdb->insert_id;
	$wpdb->insert($wpdb->prefix . 'automatiza_tech_clients', [
		'name' => 'Cliente Prueba', 'email' => $correo, 'company' => '[PRUEBA] Empresa ' . $marca, 'crm_cliente_id' => $crm,
	]);
	$tech = (int) $wpdb->insert_id;
	if ($crm <= 0 || $tech <= 0) {
		fwrite(STDERR, "No se pudo crear el cliente de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['ptc_creado']['crm'][] = $crm;
	$GLOBALS['ptc_creado']['tech'][] = $tech;
	return ['crm' => $crm, 'tech' => $tech];
}

/** Propuesta mínima (solo para que el contrato tenga proposal_id). */
function ptc_propuesta(string $marca): int {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => "prueba-plan-{$marca}@example.com", 'unique_link_id' => substr(md5($marca . microtime(true)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Empresa ' . $marca, 'status' => 'aceptada', 'flujo' => 'v3',
		'gamma_prompt_text' => '{}', 'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	if ($id <= 0) {
		fwrite(STDERR, "No se pudo crear la propuesta de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['ptc_creado']['propuestas'][] = $id;
	return $id;
}

/** Contrato (por defecto de servicios y firmado) de una ficha operativa, con los marcadores que lee el plan. */
function ptc_contrato(int $tech, string $marca, ?int $propuesta, string $tipo = 'servicios', string $estado = 'signed', string $correo = ''): int {
	global $wpdb;
	$ph = [
		'servicios_contratados' => '- **Sitio web**: $1', 'alcance' => 'Sitio web.', 'entregables' => '- Sitio publicado',
		'plazo' => 'Se define en la reunión de inicio.', 'fases_siguientes' => 'La propuesta no tiene fases siguientes.',
		'nombre_proyecto' => '[PRUEBA] Sitio ' . $marca, 'razon_social_cliente' => '[PRUEBA] Razón social ' . $marca,
		'representante_cliente_nombre' => 'Cliente Prueba', 'email_cliente' => $correo, 'telefono_cliente' => '+56 9 2222 2222',
	];
	$wpdb->insert($wpdb->prefix . 'automatiza_contracts', [
		'client_id' => $tech, 'proposal_id' => $propuesta, 'contract_number' => 'PRUEBA-PT-' . $marca . '-' . count($GLOBALS['ptc_creado']['contratos']),
		'type' => $tipo, 'template_id' => $tipo === 'servicios' ? 'servicios_v1' : 'soporte_v2',
		'placeholders' => wp_json_encode($ph, JSON_UNESCAPED_UNICODE), 'status' => $estado,
		'signed_at' => $estado === 'signed' ? current_time('mysql') : null, 'sign_token' => bin2hex(random_bytes(32)),
	]);
	$id = (int) $wpdb->insert_id;
	if ($id <= 0) {
		fwrite(STDERR, "No se pudo crear el contrato de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['ptc_creado']['contratos'][] = $id;
	return $id;
}

/** Plan del contrato recién creado (estado generando, sin payload), sin llamar a n8n. */
function ptc_plan(int $contrato): object {
	$id = at_pt_crear_plan($contrato);
	if (is_wp_error($id) || (int) $id <= 0) {
		fwrite(STDERR, 'No se pudo crear el plan de prueba: ' . (is_wp_error($id) ? $id->get_error_message() : 'id 0') . "\n");
		exit(2);
	}
	$GLOBALS['ptc_creado']['planes'][] = (int) $id;
	return at_pt_plan((int) $id);
}

/** Lo que devolvería la IA: tres fases, días propuestos, 10 briefs de foto (uno por lámina). */
function ptc_plan_base(): array {
	$briefs = [];
	foreach (['cover', 'metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal', 'cierre'] as $s) {
		$briefs[] = ['slide' => $s, 'prompt' => 'small business owner at a counter, natural light, no text'];
	}
	$act = function (string $nombre, string $resp, int $dias, string $servicio, string $etapa, bool $paralelo = false): array {
		return ['nombre' => $nombre, 'detalle' => '', 'responsable' => $resp, 'dias_habiles' => $dias, 'servicio' => $servicio, 'etapa' => $etapa, 'en_paralelo' => $paralelo];
	};
	return [
		'version' => 1, 'proyecto' => '[PRUEBA] Sitio del panel', 'fecha_firma' => '2026-09-28',
		'fases' => [
			['clave' => 'diseno_desarrollo', 'descripcion' => 'Diseñamos y construimos el sitio.', 'bloques' => [
				['nombre' => 'Diseño', 'entrega' => true, 'entregable' => 'Maqueta aprobada', 'actividades' => [
					$act('Maqueta de la portada', 'at', 2, 'sitio_web_tienda', 'diseno'),
					$act('Maqueta de páginas internas', 'at', 2, 'sitio_web_tienda', 'diseno'),
				]],
				['nombre' => 'Desarrollo', 'entrega' => false, 'entregable' => 'Sitio en pruebas', 'actividades' => [
					$act('Construcción del sitio', 'at', 8, 'sitio_web_tienda', 'desarrollo'),
				]],
			]],
			['clave' => 'implementacion', 'descripcion' => 'Publicamos y te capacitamos.', 'bloques' => [
				['nombre' => 'Puesta en marcha', 'entrega' => false, 'entregable' => 'Sitio publicado', 'actividades' => [
					$act('Publicación en tu dominio', 'at', 2, 'sitio_web_tienda', 'implementacion'),
					$act('Capacitación', 'ambos', 1, '', ''),
				]],
			]],
			['clave' => 'soporte', 'descripcion' => 'Acompañamiento después de la entrega.', 'bloques' => [
				['nombre' => 'Acompañamiento', 'entrega' => false, 'entregable' => '', 'actividades' => [
					$act('Ajustes de la primera semana', 'at', 5, '', 'soporte'),
				]],
			]],
		],
		'hitos' => [['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño']],
		'necesitamos_de_ti' => ['Logo y colores', 'Acceso al dominio'],
		'reuniones' => [['nombre' => 'Reunión de inicio', 'detalle' => 'Revisamos el plan juntos']],
		'soporte' => ['garantia_meses' => 3, 'mensuales' => []],
		'image_briefs' => $briefs,
	];
}

/** Deja el plan en borrador con el payload de ptc_plan_base() validado, con la tabla aplicada y con fechas. */
function ptc_sembrar(int $plan_id, string $inicio = '2026-10-05'): array {
	$v = at_pt_validar_plan(ptc_plan_base());
	if (empty($v['ok'])) {
		fwrite(STDERR, 'El plan base de prueba no valida: ' . implode(' | ', (array) $v['errores']) . "\n");
		exit(2);
	}
	$plan = at_pt_calcular_fechas(at_pt_aplicar_tabla($v['plan'], at_pt_duraciones_defecto()), $inicio, []);
	at_pt_guardar($plan_id, ['payload' => $plan, 'fecha_inicio' => $inicio, 'estado' => 'borrador', 'nota' => '']);
	return $plan;
}

/** Fuerza un estado (y su nota) sin pasar por las transiciones: solo para preparar casos. */
function ptc_estado(int $plan_id, string $estado, string $nota = ''): void {
	at_pt_guardar($plan_id, ['estado' => $estado, 'nota' => $nota]);
}

/** Primera actividad con ese nombre en el payload ([] si no está). */
function ptc_actividad(array $plan, string $nombre): array {
	foreach ((array) ($plan['fases'] ?? []) as $f) {
		foreach ((array) ($f['bloques'] ?? []) as $b) {
			foreach ((array) ($b['actividades'] ?? []) as $a) {
				if (($a['nombre'] ?? '') === $nombre) {
					return $a;
				}
			}
		}
	}
	return [];
}

/**
 * Lo que manda plan-trabajo.js en plan_json (fases → bloques → actividades con los campos editables, en ese orden de
 * claves y con los días como número). panel-js-wp-test.php exige que serializar() dé exactamente esto.
 */
function ptc_plan_json(array $payload): string {
	$fases = [];
	foreach ((array) ($payload['fases'] ?? []) as $f) {
		$bloques = [];
		foreach ((array) ($f['bloques'] ?? []) as $b) {
			$acts = [];
			foreach ((array) ($b['actividades'] ?? []) as $a) {
				$acts[] = [
					'nombre' => (string) $a['nombre'], 'detalle' => (string) ($a['detalle'] ?? ''), 'responsable' => (string) $a['responsable'],
					'dias_habiles' => (int) $a['dias_habiles'], 'en_paralelo' => !empty($a['en_paralelo']), 'servicio' => (string) ($a['servicio'] ?? ''),
					'etapa' => (string) ($a['etapa'] ?? ''), 'origen' => (string) ($a['origen'] ?? ''),
				];
			}
			$bloques[] = ['nombre' => (string) $b['nombre'], 'entregable' => (string) ($b['entregable'] ?? ''), 'entrega' => !empty($b['entrega']), 'actividades' => $acts];
		}
		$fases[] = ['clave' => (string) $f['clave'], 'descripcion' => (string) ($f['descripcion'] ?? ''), 'bloques' => $bloques];
	}
	return (string) wp_json_encode(['fases' => $fases], JSON_UNESCAPED_UNICODE);
}

/** Fila del CRM como la recibe at_pt_render_pestana() (ARRAY_A, igual que la ficha). */
function ptc_cliente_fila(int $crm): array {
	global $wpdb;
	return (array) $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm), ARRAY_A);
}

/** Borra todo lo creado por estas ayudas (y los planes que se hayan creado para sus contratos). */
function ptc_limpiar(): void {
	global $wpdb;
	$c = $GLOBALS['ptc_creado'];
	foreach ($c['planes'] as $id) {
		$wpdb->delete(at_pt_tabla(), ['id' => (int) $id]);
	}
	foreach ($c['contratos'] as $id) {
		$wpdb->delete(at_pt_tabla(), ['contrato_id' => (int) $id]);
		$wpdb->delete($wpdb->prefix . 'automatiza_contracts', ['id' => (int) $id]);
	}
	foreach ($c['propuestas'] as $id) {
		$wpdb->delete($wpdb->prefix . 'automatiza_propuestas', ['id' => (int) $id]);
	}
	foreach ($c['reuniones'] as $id) {
		$wpdb->delete($wpdb->prefix . 'automatiza_followup_meetings', ['id' => (int) $id]);
	}
	foreach ($c['tech'] as $id) {
		$wpdb->delete($wpdb->prefix . 'automatiza_tech_clients', ['id' => (int) $id]);
	}
	foreach ($c['crm'] as $id) {
		$wpdb->delete($wpdb->prefix . 'crm_historial', ['cliente_id' => (int) $id]);
		$wpdb->delete($wpdb->prefix . 'crm_clientes', ['id' => (int) $id]);
	}
	// Ya borrado: la llamada del cierre del proceso (o una segunda llamada) no repite nada.
	$GLOBALS['ptc_creado'] = ['crm' => [], 'tech' => [], 'contratos' => [], 'propuestas' => [], 'planes' => [], 'reuniones' => []];
}

if (!defined('PTC_CONSERVAR')) {
	register_shutdown_function('ptc_limpiar');
}
```

- [ ] **Step 2: Escribir la prueba que falla (lo que dibuja la pestaña)**

Crear `tests/plan/panel-render-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/panel-render-wp-test.php
// Task 9: lo que dibuja la pestaña «🗓️ Plan de trabajo» (at_pt_render_pestana) y sus assets.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';

if (!function_exists('at_pt_render_pestana') || !function_exists('at_pt_url_ficha')) {
	fwrite(STDERR, "panel.php no está cargado: revisa inc/plan-trabajo/cargar.php.\n");
	exit(2);
}
global $wpdb;
wp_set_current_user(pt_admin_id());
$m = ptc_marca();

function pestana(int $crm, array $get = []): string {
	$_GET = $get;
	ob_start();
	at_pt_render_pestana(ptc_cliente_fila($crm));
	$h = (string) ob_get_clean();
	$_GET = [];
	return $h;
}

// ---------- URL de vuelta ----------
$u = at_pt_url_ficha(7, 12, 'guardado');
$q = pt_query($u);
ok(($q['page'] ?? '') === 'automatiza-crm-ficha' && ($q['id'] ?? '') === '7' && ($q['pt'] ?? '') === '12' && ($q['pt_msg'] ?? '') === 'guardado' && $q['#'] === 'tab-plan', 'at_pt_url_ficha arma la ficha con pt, pt_msg y #tab-plan: ' . $u);
$q = pt_query(at_pt_url_ficha(7));
ok(!isset($q['pt']) && !isset($q['pt_msg']) && $q['#'] === 'tab-plan', 'sin plan ni mensaje, la URL no los lleva');

// ---------- Cliente sin contratos ----------
$c0 = ptc_cliente($m . 'a', "prueba-plan-{$m}a@example.com");
$h = pestana($c0['crm']);
ok(strpos($h, 'no tiene contratos de servicios firmados') !== false && strpos($h, 'at_pt_crear') === false, 'sin contratos: lo dice y no ofrece crear');

// ---------- Contrato firmado sin plan (p. ej. firmado antes de este cambio) ----------
$c1 = ptc_cliente($m . 'b', "prueba-plan-{$m}b@example.com");
$k1 = ptc_contrato($c1['tech'], $m . 'b', null);
$ks = ptc_contrato($c1['tech'], $m . 'b', null, 'soporte');
$h = pestana($c1['crm']);
ok(strpos($h, 'name="action" value="at_pt_crear"') !== false && strpos($h, 'name="contrato_id" value="' . $k1 . '"') !== false && strpos($h, 'Crear plan de trabajo') !== false, 'contrato de servicios sin plan: botón «Crear plan de trabajo» con su contrato');
ok(strpos($h, 'name="contrato_id" value="' . $ks . '"') === false, 'un contrato de soporte no ofrece plan');

// ---------- Plan en borrador, con propuesta y con correo ----------
$c2 = ptc_cliente($m . 'c', "prueba-plan-{$m}c@example.com");
$p2 = ptc_propuesta($m . 'c');
$k2 = ptc_contrato($c2['tech'], $m . 'c', $p2);
$f2 = ptc_plan($k2);
ptc_sembrar((int) $f2->id);
$h = pestana($c2['crm'], ['pt' => (string) $f2->id]);
ok(strpos($h, 'at-pt-estado--borrador') !== false && strpos($h, '>Borrador<') !== false, 'estado «Borrador» con su color');
ok(strpos($h, 'value="Construcción del sitio"') !== false && strpos($h, 'value="Reunión de inicio"') !== false, 'la tabla trae las actividades, incluido el arranque');
ok(strpos($h, 'at-pt-origen--tabla') !== false && strpos($h, 'at-pt-origen--ia') !== false && strpos($h, '>Tabla de tiempos<') !== false && strpos($h, '>IA · revisar<') !== false, 'se ve el origen de cada duración con las etiquetas de at_pt_origenes() («Tabla de tiempos», «IA · revisar»)');
ok(strpos($h, 'class="at-pt-a-detalle"') !== false && strpos($h, 'data-detalle=') === false, 'el detalle de cada actividad (sale en la lámina de la fase) se edita en su propio campo');
ok(strpos($h, 'name="mensuales"') !== false, 'los servicios mensuales (lámina de reuniones y soporte) se pueden editar');
$pl9 = at_pt_payload(at_pt_plan((int) $f2->id));
$pl9['soporte']['garantia_meses'] = 9;
at_pt_guardar((int) $f2->id, ['payload' => $pl9]);
$h9 = pestana($c2['crm'], ['pt' => (string) $f2->id]);
ok(strpos($h9, 'name="garantia_meses"') === false && strpos($h9, '<input type="number" class="at-pt-garantia" value="3" readonly ') !== false && strpos($h9, 'Viene del contrato') !== false, 'la garantía es de solo lectura, sin name (no se envía), con la leyenda «Viene del contrato» y el valor del contrato (3, sin marcador) aunque el plan diga 9 (D8)');
ok(strpos($h, 'class="at-pt-plan-json"') !== false && strpos($h, 'value="at_pt_guardar"') !== false && strpos($h, 'Guardar y recalcular') !== false, '«Guardar y recalcular» con el campo oculto plan_json');
ok(strpos($h, 'value="at_pt_cambios"') !== false && strpos($h, 'name="comentarios"') !== false, '«Pedir cambios» con sus comentarios');
$costo = at_pt_costo_fotos(at_pt_payload(at_pt_plan((int) $f2->id))['image_briefs'] ?? [], true);
ok((int) $costo['fotos'] === 8, 'con propuesta cuentan 8 fotos nuevas (portada y cierre se reutilizan): ' . $costo['fotos']);
$etiqueta = '(8 fotos ≈ US$' . number_format((float) $costo['usd_lista'], 4, ',', '.') . ')';
ok(strpos($h, 'Aprobar y generar versión final ' . $etiqueta) !== false, 'el botón Aprobar muestra ' . $etiqueta);
ok(preg_match('/<form[^>]*class="at-pt-form-aprobar at-pt-requiere-guardado"[^>]*onsubmit="return confirm\(&quot;Se generarán 8 fotos nuevas/u', $h) === 1, 'Aprobar pide confirm() con la cantidad de fotos y el costo');
ok(strpos($h, 'page=automatiza-followup') !== false && strpos($h, 'pt_plan=' . $f2->id) !== false && strpos($h, 'Agendar llamada de seguimiento') !== false, '«Agendar llamada de seguimiento» abre el formulario de seguimiento con pt_plan');
ok(strpos($h, 'Destrabar') === false && strpos($h, 'no tiene correo') === false && strpos($h, 'no tiene propuesta') === false, 'en borrador, con correo y con propuesta: sin Destrabar ni avisos de falta');
ok(strpos($h, '<fieldset>') !== false, 'en borrador la tabla se puede editar');

// ---------- Sin propuesta y sin correo (Review Focus 4) ----------
$c3 = ptc_cliente($m . 'd', '');
$k3 = ptc_contrato($c3['tech'], $m . 'd', null);
$f3 = ptc_plan($k3);
ptc_sembrar((int) $f3->id);
$h = pestana($c3['crm']);
ok(strpos($h, '(10 fotos ≈ US$0,0320)') !== false, 'sin propuesta: portada y cierre también son fotos nuevas (10 fotos ≈ US$0,0320)');
ok(strpos($h, 'saldrá sin enlace al portal') !== false, 'sin correo: avisa que «Sigue tu proyecto» sale sin enlace, y la pestaña se dibuja igual');
ok(strpos($h, 'no tiene propuesta') !== false, 'sin propuesta: lo avisa');

// ---------- Estados ocupados y de error ----------
ptc_estado((int) $f3->id, 'aprobando');
$h = pestana($c3['crm']);
ok(strpos($h, 'value="at_pt_destrabar"') !== false && strpos($h, 'value="at_pt_aprobar"') === false && strpos($h, '<fieldset disabled>') !== false, 'aprobando: ofrece Destrabar, no Aprobar, y la tabla queda de solo lectura');
ptc_estado((int) $f3->id, 'error', 'n8n no respondió (prueba)');
$h = pestana($c3['crm']);
ok(strpos($h, 'Motivo: n8n no respondió (prueba)') !== false && strpos($h, 'Volver al borrador') !== false && strpos($h, 'value="at_pt_reintentar"') !== false, 'error con plan: muestra el motivo y «Volver al borrador»');
$k4 = ptc_contrato($c3['tech'], $m . 'e', null);
$f4 = ptc_plan($k4);
ptc_estado((int) $f4->id, 'error', 'n8n caído al firmar (prueba)');
$h = pestana($c3['crm'], ['pt' => (string) $f4->id]);
ok(strpos($h, 'Reintentar borrador') !== false && strpos($h, 'Motivo: n8n caído al firmar (prueba)') !== false, 'error sin plan: «Reintentar borrador» con el motivo (Review Focus 5)');

// ---------- Varios planes: selector ----------
ok(strpos($h, 'class="at-pt-selector"') !== false && strpos($h, 'pt=' . $f4->id . '#tab-plan" aria-current="page"') !== false && strpos($h, 'pt=' . $f3->id . '#tab-plan"') !== false && strpos($h, 'pt=' . $f3->id . '#tab-plan" aria-current') === false, 'dos planes: el selector lleva a los dos y marca el que se está viendo');

// ---------- Escapado de lo que viene de la IA ----------
$pl = at_pt_payload(at_pt_plan((int) $f2->id));
$pl['fases'][0]['bloques'][0]['actividades'][0]['nombre'] = '<script>alert(1)</script> [PRUEBA] "comillas" & más';
at_pt_guardar((int) $f2->id, ['payload' => $pl]);
$h = pestana($c2['crm']);
ok(strpos($h, '<script>alert(1)') === false && strpos($h, '&lt;script&gt;alert(1)&lt;/script&gt; [PRUEBA] &quot;comillas&quot; &amp; más') !== false, 'los textos del plan salen escapados');

// ---------- Aviso de la última acción (pt_msg) ----------
$h = pestana($c2['crm'], ['pt' => (string) $f2->id, 'pt_msg' => 'guardado']);
ok(strpos($h, 'Guardado y recalculado.') !== false, 'pt_msg=guardado muestra su aviso');
at_pt_guardar_detalles((int) $f2->id, ['Días fuera de rango en «Construcción del sitio»']);
$h = pestana($c2['crm'], ['pt' => (string) $f2->id, 'pt_msg' => 'invalido']);
ok(strpos($h, 'No se guardó: el plan tiene errores.') !== false && strpos($h, '• Días fuera de rango en «Construcción del sitio»') !== false, 'pt_msg=invalido muestra los errores guardados una sola vez');
$h = pestana($c2['crm'], ['pt' => (string) $f2->id, 'pt_msg' => 'invalido']);
ok(strpos($h, '• Días fuera de rango') === false, 'los errores no se repiten al recargar');
$h = pestana($c2['crm'], ['pt_msg' => '<script>']);
ok(strpos($h, 'at-pt-aviso') === false, 'un pt_msg desconocido no dibuja ningún aviso');

// ---------- Assets: solo en la ficha del CRM ----------
$_GET = ['page' => 'automatiza-proposals'];
at_pt_encolar_assets();
ok(!wp_style_is('at-plan-trabajo', 'enqueued') && !wp_script_is('at-plan-trabajo', 'enqueued'), 'en otra página no se encola nada');
$_GET = ['page' => 'automatiza-crm-ficha', 'id' => (string) $c2['crm']];
at_pt_encolar_assets();
ok(wp_style_is('at-plan-trabajo', 'enqueued') && wp_script_is('at-plan-trabajo', 'enqueued'), 'en la ficha del cliente se encolan plan-trabajo.css y plan-trabajo.js');
$_GET = [];

ptc_limpiar();
fin();
```

- [ ] **Step 3: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/panel-render-wp-test.php; echo "exit=$?"
```
Expected (falla porque `panel.php` no existe):
```
panel.php no está cargado: revisa inc/plan-trabajo/cargar.php.
exit=2
```

- [ ] **Step 4: Implementar `panel.php` (lo que se dibuja)**

Crear `wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php`:
```php
<?php
/**
 * Pestaña «🗓️ Plan de trabajo» de la ficha del cliente en el CRM (crm-ai-completo.php la dibuja con
 * at_pt_render_pestana()) y sus acciones admin-post. Mismo estilo y botones que el panel de propuestas.
 * Toda escritura: manage_options + nonce por plan ('at_pt_plan_<id>'; «Crear»: 'at_pt_crear_<contrato>') y vuelta
 * a la ficha con pt_msg (at_pt_url_ficha). Spec: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md §6.
 */
if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_enqueue_scripts', 'at_pt_encolar_assets');

/** Pestaña del plan en la ficha del cliente (el JS de plan-trabajo.js la abre por el #tab-plan). */
function at_pt_url_ficha(int $crm_id, int $plan_id = 0, string $msg = ''): string {
	$args = ['page' => 'automatiza-crm-ficha', 'id' => $crm_id];
	if ($plan_id > 0) {
		$args['pt'] = $plan_id;
	}
	if ($msg !== '') {
		$args['pt_msg'] = $msg;
	}
	return add_query_arg($args, admin_url('admin.php')) . '#tab-plan';
}

/** CSS y JS de la pestaña, solo en la ficha del cliente del CRM. */
function at_pt_encolar_assets(): void {
	if (sanitize_key(wp_unslash($_GET['page'] ?? '')) !== 'automatiza-crm-ficha') {
		return;
	}
	$dir = get_template_directory() . '/assets';
	$url = get_template_directory_uri() . '/assets';
	if (file_exists($dir . '/css/plan-trabajo.css')) {
		wp_enqueue_style('at-plan-trabajo', $url . '/css/plan-trabajo.css', [], (string) filemtime($dir . '/css/plan-trabajo.css'));
	}
	if (file_exists($dir . '/js/plan-trabajo.js')) {
		wp_enqueue_script('at-plan-trabajo', $url . '/js/plan-trabajo.js', [], (string) filemtime($dir . '/js/plan-trabajo.js'), true);
	}
}

function at_pt_estados_etiqueta(): array {
	return [
		'generando' => 'Generando borrador', 'borrador' => 'Borrador', 'cambios' => 'Aplicando cambios',
		'aprobando' => 'Generando versión final', 'listo' => 'Listo', 'enviado' => 'Enviado', 'error' => 'Error',
	];
}

/** Avisos que vuelven en pt_msg: clave => [tipo, texto]. */
function at_pt_mensajes_panel(): array {
	return [
		'creado'             => ['ok', 'Plan creado. La IA está armando el borrador; te llegará un correo cuando esté la vista previa.'],
		'ya_existe'          => ['aviso', 'Ese contrato ya tiene plan de trabajo: no se creó otro.'],
		'no_se_pudo'         => ['error', 'No se pudo crear el plan: el contrato debe ser de servicios, estar firmado y ser de este cliente.'],
		'guardado'           => ['ok', 'Guardado y recalculado. La vista previa nueva llega en unos segundos.'],
		'guardado_sin_vista' => ['aviso', 'Guardado y recalculado, pero no se pudo pedir la vista previa a n8n. Vuelve a guardar en un rato.'],
		'error_guardar'      => ['error', 'No se pudo guardar el plan en la base. Inténtalo de nuevo.'],
		'invalido'           => ['error', 'No se guardó: el plan tiene errores.'],
		'json_invalido'      => ['error', 'No se guardó: la tabla no llegó completa. Recarga la página e inténtalo de nuevo.'],
		'no_editable'        => ['error', 'Este plan no se puede editar mientras la IA trabaja en él. Si lleva mucho rato, usa «Destrabar».'],
		'cambios_pedidos'    => ['ok', 'Cambios pedidos. Te llegará un correo con la nueva vista previa.'],
		'sin_comentarios'    => ['error', 'Escribe qué quieres cambiar antes de pedir cambios.'],
		'aprobando'          => ['ok', 'Aprobado. Se están generando las fotos y la versión final; te llegará un correo cuando esté lista.'],
		'destrabado'         => ['ok', 'Plan destrabado: quedó en «error» para poder reintentar.'],
		'reintentando'       => ['ok', 'Se le pidió de nuevo el borrador a la IA.'],
		'vuelto_borrador'    => ['ok', 'El plan volvió a borrador: puedes editarlo, pedir cambios o aprobarlo.'],
		'n8n_fallo'          => ['error', 'No se pudo avisar a n8n: el plan quedó en «error» con el motivo. Puedes reintentar desde aquí.'],
		'transicion'         => ['error', 'Esa acción no corresponde al estado actual del plan.'],
		'sin_plan'           => ['error', 'Ese plan de trabajo no existe.'],
	];
}

/** Detalle de la última acción (errores de validación o avisos), una sola vez, por usuario. */
function at_pt_guardar_detalles(int $plan_id, array $lineas): void {
	$lineas = array_values(array_filter(array_map('strval', $lineas), 'strlen'));
	if ($lineas) {
		set_transient('at_pt_detalles_' . get_current_user_id(), ['plan_id' => $plan_id, 'lineas' => array_slice($lineas, 0, 20)], 120);
	}
}

function at_pt_tomar_detalles(int $plan_id): array {
	$clave = 'at_pt_detalles_' . get_current_user_id();
	$d = get_transient($clave);
	if (!is_array($d) || (int) ($d['plan_id'] ?? 0) !== $plan_id) {
		return [];
	}
	delete_transient($clave);
	return array_map('strval', (array) ($d['lineas'] ?? []));
}

function at_pt_aviso_panel(int $plan_id): string {
	$m = at_pt_mensajes_panel()[sanitize_key(wp_unslash($_GET['pt_msg'] ?? ''))] ?? null;
	if (!$m) {
		return '';
	}
	$clase = ['ok' => 'notice-success', 'aviso' => 'notice-warning'][$m[0]] ?? 'notice-error';
	$html = '<div class="notice ' . $clase . ' inline at-pt-aviso"><p>' . esc_html($m[1]) . '</p>';
	foreach (at_pt_tomar_detalles($plan_id) as $l) {
		$html .= '<p>• ' . esc_html($l) . '</p>';
	}
	return $html . '</div>';
}

/** «5 oct» (o «5 oct 2026» con año) desde Y-m-d; '' si no es una fecha. */
function at_pt_panel_fecha(string $ymd, bool $con_anio = false): string {
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
		return '';
	}
	$meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	return (int) $m[3] . ' ' . $meses[(int) $m[2] - 1] . ($con_anio ? ' ' . $m[1] : '');
}

/** Fase del payload con esa clave ([] si el plan no la trae). */
function at_pt_fase_de(array $plan, string $clave): array {
	foreach ((array) ($plan['fases'] ?? []) as $f) {
		if (is_array($f) && ($f['clave'] ?? '') === $clave) {
			return $f;
		}
	}
	return [];
}

/** Hitos que edita Luis: todos menos «Entrega estimada», que se calcula sola. */
function at_pt_hitos_editables(array $plan): array {
	return array_values(array_filter((array) ($plan['hitos'] ?? []), function ($h) {
		return is_array($h) && ($h['nombre'] ?? '') !== 'Entrega estimada';
	}));
}

/** Líneas «a | b» (o solo «a» si b está vacío) para los textareas de reuniones e hitos. */
function at_pt_lineas_pares(array $items, string $a, string $b): string {
	$lineas = [];
	foreach ($items as $it) {
		if (!is_array($it) || trim((string) ($it[$a] ?? '')) === '') {
			continue;
		}
		$segundo = trim((string) ($it[$b] ?? ''));
		$lineas[] = trim((string) $it[$a]) . ($segundo !== '' ? ' | ' . $segundo : '');
	}
	return implode("\n", $lineas);
}

/** Una fila editable de actividad (también sirve de plantilla para «+ Actividad»). */
function at_pt_html_actividad(array $a): void {
	$etiquetas = at_pt_origenes(); // Task 2: 'Tabla de tiempos', 'IA · revisar', 'Editado por Luis'
	$ayudas = [
		'tabla' => 'Días de la tabla de tiempos',
		'ia'    => 'Días estimados por la IA: revísalos',
		'luis'  => 'Días que editaste tú: la IA y la tabla no los pisan',
	];
	$origen = isset($etiquetas[$a['origen'] ?? '']) ? (string) $a['origen'] : 'ia';
	$responsables = array_merge(at_pt_responsables(), ['cliente' => 'Cliente']);
	$desde = (string) ($a['desde'] ?? '');
	$hasta = (string) ($a['hasta'] ?? '');
	?>
	<div class="at-pt-act" data-servicio="<?php echo esc_attr((string) ($a['servicio'] ?? '')); ?>" data-etapa="<?php echo esc_attr((string) ($a['etapa'] ?? '')); ?>" data-origen="<?php echo esc_attr($origen); ?>">
		<div class="at-pt-act-l1">
			<input type="text" class="at-pt-a-nombre" value="<?php echo esc_attr((string) ($a['nombre'] ?? '')); ?>" maxlength="120" aria-label="Actividad" placeholder="Nombre de la actividad">
			<button type="button" class="at-pt-quitar at-pt-quitar-act" aria-label="Quitar esta actividad">✕</button>
		</div>
		<div class="at-pt-act-l2">
			<label>Responsable <select class="at-pt-a-responsable">
				<?php foreach ($responsables as $k => $t): ?><option value="<?php echo esc_attr($k); ?>"<?php selected((string) ($a['responsable'] ?? 'at'), $k); ?>><?php echo esc_html($t); ?></option><?php endforeach; ?>
			</select></label>
			<label>Días hábiles <input type="number" class="at-pt-a-dias" min="1" max="60" step="1" value="<?php echo esc_attr((string) (int) ($a['dias_habiles'] ?? 1)); ?>"></label>
			<label class="at-pt-check"><input type="checkbox" class="at-pt-a-paralelo"<?php checked(!empty($a['en_paralelo'])); ?>> En paralelo</label>
			<span class="at-pt-origen at-pt-origen--<?php echo esc_attr($origen); ?>" title="<?php echo esc_attr($ayudas[$origen] ?? ''); ?>"><?php echo esc_html((string) $etiquetas[$origen]); ?></span>
			<?php if ($desde !== ''): ?><span class="at-pt-fechas"><?php echo esc_html(at_pt_panel_fecha($desde) . ($hasta !== '' && $hasta !== $desde ? ' → ' . at_pt_panel_fecha($hasta) : '')); ?></span><?php endif; ?>
		</div>
		<div class="at-pt-act-l3"><input type="text" class="at-pt-a-detalle" value="<?php echo esc_attr((string) ($a['detalle'] ?? '')); ?>" maxlength="300" aria-label="Detalle de la actividad (sale en la lámina de la fase)" placeholder="Detalle (opcional, sale en la lámina)"></div>
	</div>
	<?php
}

/** Un bloque editable con sus actividades (también sirve de plantilla para «+ Bloque»). */
function at_pt_html_bloque(array $b): void {
	$acts = array_values(array_filter((array) ($b['actividades'] ?? []), 'is_array'));
	$desde = '';
	$hasta = '';
	foreach ($acts as $a) {
		$d = (string) ($a['desde'] ?? '');
		$h = (string) ($a['hasta'] ?? '');
		if ($d !== '' && ($desde === '' || $d < $desde)) {
			$desde = $d;
		}
		if ($h !== '' && $h > $hasta) {
			$hasta = $h;
		}
	}
	?>
	<div class="at-pt-bloque">
		<div class="at-pt-bloque-cab">
			<label class="at-pt-campo">Bloque <input type="text" class="at-pt-b-nombre" value="<?php echo esc_attr((string) ($b['nombre'] ?? '')); ?>" maxlength="80"></label>
			<label class="at-pt-campo">Entregable <input type="text" class="at-pt-b-entregable" value="<?php echo esc_attr((string) ($b['entregable'] ?? '')); ?>" maxlength="160"></label>
		</div>
		<div class="at-pt-bloque-pie">
			<label class="at-pt-check"><input type="checkbox" class="at-pt-b-entrega"<?php checked(!empty($b['entrega'])); ?>> Revisión del cliente después (5 días hábiles)</label>
			<?php if ($desde !== ''): ?><span class="at-pt-fechas"><?php echo esc_html(at_pt_panel_fecha($desde) . ' → ' . at_pt_panel_fecha($hasta)); ?></span><?php endif; ?>
			<button type="button" class="at-pt-quitar at-pt-quitar-bloque">Quitar bloque</button>
		</div>
		<div class="at-pt-acts">
			<?php foreach ($acts as $a) { at_pt_html_actividad($a); } ?>
		</div>
		<button type="button" class="button button-small at-pt-agregar-act">+ Actividad</button>
	</div>
	<?php
}

/** Contenido de la pestaña «🗓️ Plan de trabajo» de la ficha del cliente ($cliente = fila ARRAY_A de wp_crm_clientes). */
function at_pt_render_pestana(array $cliente): void {
	$crm_id = (int) ($cliente['id'] ?? 0);
	echo '<div class="at-pt"><h3>🗓️ Plan de trabajo</h3>';
	if ($crm_id <= 0 || !current_user_can('manage_options')) {
		echo '<p>Solo un administrador puede ver el plan de trabajo.</p></div>';
		return;
	}
	$planes = array_values(at_pt_planes_de_crm($crm_id));
	$sin_plan = array_values(at_pt_contratos_sin_plan($crm_id));
	$pedido = absint($_GET['pt'] ?? 0);
	$fila = null;
	foreach ($planes as $p) {
		if ((int) $p->id === $pedido) {
			$fila = $p;
		}
	}
	if (!$fila && $planes) {
		$fila = $planes[0];
	}
	echo at_pt_aviso_panel($fila ? (int) $fila->id : 0);
	if (!$planes && !$sin_plan) {
		echo '<p>Este cliente no tiene contratos de servicios firmados. El plan de trabajo se crea solo cuando firma su contrato de servicios.</p></div>';
		return;
	}
	foreach ($sin_plan as $c) {
		at_pt_render_crear($c, $crm_id);
	}
	if ($fila) {
		if (count($planes) > 1) {
			at_pt_render_selector($planes, (int) $fila->id, $crm_id);
		}
		at_pt_render_plan($fila, $crm_id);
	}
	echo '</div>';
}

/** Botón «Crear plan de trabajo» para un contrato de servicios firmado que aún no tiene plan. */
function at_pt_render_crear(object $c, int $crm_id): void {
	$cid = (int) $c->id;
	$numero = (string) ($c->contract_number ?? '');
	$firmado = substr((string) ($c->signed_at ?? ''), 0, 10);
	?>
	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="at-pt-crear">
		<input type="hidden" name="action" value="at_pt_crear">
		<input type="hidden" name="contrato_id" value="<?php echo $cid; ?>">
		<input type="hidden" name="crm_id" value="<?php echo $crm_id; ?>">
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('at_pt_crear_' . $cid)); ?>">
		<p>El contrato de servicios <strong><?php echo esc_html($numero !== '' ? $numero : '#' . $cid); ?></strong><?php echo $firmado !== '' ? esc_html(' (firmado el ' . at_pt_panel_fecha($firmado, true) . ')') : ''; ?> todavía no tiene plan de trabajo.</p>
		<p class="at-pt-botones"><button type="submit" class="button button-primary">🗓️ Crear plan de trabajo</button></p>
		<p class="description">La IA arma el borrador con lo contratado y la tabla de tiempos; te llega un correo con la vista previa. Al cliente no le llega nada.</p>
	</form>
	<?php
}

/** Selector cuando el cliente tiene más de un plan (un contrato de servicios firmado por plan). */
function at_pt_render_selector(array $planes, int $actual, int $crm_id): void {
	$etiquetas = at_pt_estados_etiqueta();
	echo '<nav class="at-pt-selector" aria-label="Planes de este cliente">';
	foreach ($planes as $p) {
		$pl = at_pt_payload($p);
		$nombre = trim((string) ($pl['proyecto'] ?? '')) !== '' ? trim((string) $pl['proyecto']) : 'Plan ' . $p->codigo;
		$es = (int) $p->id === $actual;
		printf(
			'<a class="button%s" href="%s"%s>%s</a>',
			$es ? ' button-primary' : '',
			esc_url(at_pt_url_ficha($crm_id, (int) $p->id)),
			$es ? ' aria-current="page"' : '',
			esc_html($nombre . ' · ' . ($etiquetas[$p->estado] ?? $p->estado))
		);
	}
	echo '</nav>';
}

/** Estado, fechas, documento, tabla editable y botones de un plan. */
function at_pt_render_plan(object $fila, int $crm_id): void {
	$id = (int) $fila->id;
	$pl = at_pt_payload($fila);
	$estado = (string) $fila->estado;
	$etiquetas = at_pt_estados_etiqueta();
	$tiene_plan = !empty($pl['fases']) && is_array($pl['fases']);
	$editable = $tiene_plan && in_array($estado, ['borrador', 'listo', 'error'], true);
	$ocupado = in_array($estado, ['generando', 'cambios', 'aprobando'], true);
	$nonce = wp_create_nonce('at_pt_plan_' . $id);
	$accion = admin_url('admin-post.php');
	$ocultos = function (string $a) use ($id, $crm_id, $nonce): string {
		return '<input type="hidden" name="action" value="' . esc_attr($a) . '">'
			. '<input type="hidden" name="plan_id" value="' . $id . '">'
			. '<input type="hidden" name="crm_id" value="' . $crm_id . '">'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">';
	};
	$crono = is_array($pl['cronograma'] ?? null) ? $pl['cronograma'] : [];
	$entrega = '';
	foreach ((array) ($pl['hitos'] ?? []) as $h) {
		if (is_array($h) && ($h['nombre'] ?? '') === 'Entrega estimada') {
			$entrega = (string) ($h['fecha'] ?? '');
		}
	}
	if ($entrega === '') {
		$entrega = (string) ($crono['fin'] ?? '');
	}
	$semanas = (int) ($crono['semanas'] ?? 0);
	$proyecto = trim((string) ($pl['proyecto'] ?? ''));
	$nota = trim((string) ($fila->nota ?? ''));
	$sin_portal = function_exists('at_crm_url_portal') && at_crm_url_portal($crm_id) === '';
	// Decisión D8: la garantía del documento es la del contrato firmado (at_pt_db_partes(), Task 5), no la del plan.
	$garantia = (int) at_pt_db_partes($fila)['garantia_meses'];
	?>
	<div class="at-pt-plan" data-plan="<?php echo $id; ?>">
		<p class="at-pt-sub"><strong><?php echo esc_html($proyecto !== '' ? $proyecto : 'Plan sin nombre todavía'); ?></strong> · código <?php echo esc_html((string) $fila->codigo); ?> · contrato #<?php echo (int) $fila->contrato_id; ?></p>
		<div class="at-pt-resumen">
			<div class="at-pt-caja"><h4>Estado</h4>
				<span class="at-pt-estado at-pt-estado--<?php echo esc_attr($estado); ?>"><?php echo esc_html($etiquetas[$estado] ?? $estado); ?></span>
				<?php if ($nota !== '' && $estado !== 'error'): ?><p><?php echo esc_html($nota); ?></p><?php endif; ?>
				<p class="at-pt-sub">Actualizado: <?php echo esc_html((string) ($fila->updated_at ?? '')); ?></p>
			</div>
			<div class="at-pt-caja"><h4>Fechas estimadas</h4>
				<?php if (!empty($crono['inicio'])): ?>
					<p>Inicio: <?php echo esc_html(at_pt_panel_fecha((string) $crono['inicio'], true)); ?></p>
					<p>Entrega estimada: <?php echo esc_html(at_pt_panel_fecha($entrega, true)); ?></p>
					<p><?php echo esc_html($semanas === 1 ? '1 semana' : $semanas . ' semanas'); ?></p>
				<?php else: ?><p>Todavía sin fechas.</p><?php endif; ?>
			</div>
			<div class="at-pt-caja"><h4>Documento</h4>
				<?php if (!empty($fila->view_url)): ?>
					<p class="at-pt-botones"><a class="button" href="<?php echo esc_url((string) $fila->view_url); ?>" target="_blank" rel="noopener">👁️ <?php echo esc_html($estado === 'listo' ? 'Ver versión final' : 'Ver vista previa'); ?></a>
					<?php if (!empty($fila->pdf_url)): ?><a class="button" href="<?php echo esc_url((string) $fila->pdf_url); ?>" target="_blank" rel="noopener">📄 PDF</a><?php endif; ?></p>
				<?php else: ?><p>Aún no hay vista previa.</p><?php endif; ?>
			</div>
		</div>
		<?php if ($sin_portal): ?><div class="notice notice-warning inline"><p>Este cliente no tiene correo en su ficha: la lámina «Sigue tu proyecto» saldrá sin enlace al portal.</p></div><?php endif; ?>
		<?php if (empty($fila->propuesta_id)): ?><div class="notice notice-info inline"><p>Este contrato no tiene propuesta: la portada y el cierre llevarán fotos nuevas.</p></div><?php endif; ?>

		<?php if ($ocupado): ?>
			<div class="notice notice-info inline"><p>La IA está trabajando en este plan (<?php echo esc_html(mb_strtolower($etiquetas[$estado])); ?>). Te llegará un correo cuando termine. Si lleva más de 10 minutos, destrábalo.</p>
			<form method="post" action="<?php echo esc_url($accion); ?>" onsubmit="return confirm(<?php echo esc_attr(wp_json_encode('¿Destrabar este plan? Queda en «error» para poder reintentar. No se llama a n8n.', JSON_UNESCAPED_UNICODE)); ?>);">
				<?php echo $ocultos('at_pt_destrabar'); ?>
				<p class="at-pt-botones"><button type="submit" class="button">🔓 Destrabar (pasar a error)</button></p>
			</form></div>
		<?php endif; ?>

		<?php if ($estado === 'error'): ?>
			<div class="notice notice-error inline"><p><strong>El plan quedó en error.</strong> <?php echo esc_html($nota !== '' ? 'Motivo: ' . $nota : 'Sin motivo anotado.'); ?></p>
			<form method="post" action="<?php echo esc_url($accion); ?>">
				<?php echo $ocultos('at_pt_reintentar'); ?>
				<p class="at-pt-botones"><button type="submit" class="button button-primary"><?php echo esc_html($tiene_plan ? '↩️ Volver al borrador' : '🔁 Reintentar borrador'); ?></button></p>
			</form></div>
		<?php endif; ?>

		<?php if ($estado === 'listo'): ?>
			<div class="notice notice-success inline"><p>Versión final lista. Si cambias algo y guardas, el plan vuelve a borrador y hay que aprobarlo de nuevo. El envío al cliente llega en la etapa 2.</p></div>
		<?php endif; ?>

		<?php if ($tiene_plan): ?>
		<form method="post" action="<?php echo esc_url($accion); ?>" class="at-pt-form-plan">
			<?php echo $ocultos('at_pt_guardar'); ?>
			<input type="hidden" name="plan_json" class="at-pt-plan-json" value="">
			<fieldset<?php echo $editable ? '' : ' disabled'; ?>>
				<legend class="screen-reader-text">Plan editable</legend>
				<label class="at-pt-campo">Nombre del proyecto
					<input type="text" name="proyecto" value="<?php echo esc_attr($proyecto); ?>" maxlength="120"></label>
				<label class="at-pt-campo">Fecha de inicio
					<input type="date" name="fecha_inicio" value="<?php echo esc_attr((string) ($fila->fecha_inicio ?? '')); ?>"></label>
				<p class="description">Si cae en fin de semana o feriado, el plan parte el día hábil siguiente. Las fechas son estimadas: corren desde que llegan el anticipo y los insumos del cliente.</p>
				<?php foreach (at_pt_fases_validas() as $clave => $titulo): $fase = at_pt_fase_de($pl, $clave); ?>
				<section class="at-pt-fase" data-clave="<?php echo esc_attr($clave); ?>">
					<h4><?php echo esc_html($titulo); ?></h4>
					<label class="at-pt-campo">Descripción de la fase (va en su lámina)
						<textarea class="at-pt-f-descripcion" rows="2"><?php echo esc_textarea((string) ($fase['descripcion'] ?? '')); ?></textarea></label>
					<?php foreach ((array) ($fase['bloques'] ?? []) as $b) { at_pt_html_bloque(is_array($b) ? $b : []); } ?>
					<button type="button" class="button button-small at-pt-agregar-bloque">+ Bloque</button>
				</section>
				<?php endforeach; ?>
				<div class="at-pt-textos">
					<label class="at-pt-campo">Qué necesitamos del cliente (uno por línea)
						<textarea name="necesitamos_de_ti" rows="3"><?php echo esc_textarea(implode("\n", array_map('strval', (array) ($pl['necesitamos_de_ti'] ?? [])))); ?></textarea></label>
					<label class="at-pt-campo">Reuniones (una por línea: «Nombre | detalle»)
						<textarea name="reuniones" rows="3"><?php echo esc_textarea(at_pt_lineas_pares((array) ($pl['reuniones'] ?? []), 'nombre', 'detalle')); ?></textarea></label>
					<label class="at-pt-campo">Hitos (uno por línea: «Nombre | bloque después del cual se cumple»)
						<textarea name="hitos" rows="2"><?php echo esc_textarea(at_pt_lineas_pares(at_pt_hitos_editables($pl), 'nombre', 'despues_de')); ?></textarea></label>
					<p class="description">La «Entrega estimada» se calcula sola.</p>
					<label class="at-pt-campo">Meses de garantía
						<input type="number" class="at-pt-garantia" value="<?php echo esc_attr((string) $garantia); ?>" readonly aria-describedby="at-pt-garantia-nota"></label>
					<p class="description" id="at-pt-garantia-nota">Viene del contrato: se cambia en el contrato, no aquí.</p>
					<label class="at-pt-campo">Servicios mensuales (uno por línea; van en la lámina de reuniones y soporte)
						<textarea name="mensuales" rows="2"><?php echo esc_textarea(implode("\n", array_map('strval', (array) ($pl['soporte']['mensuales'] ?? [])))); ?></textarea></label>
				</div>
				<p class="at-pt-botones"><button type="submit" class="button button-primary">💾 Guardar y recalcular</button></p>
				<p class="description">Recalcula las fechas en días hábiles y pide una vista previa nueva, sin fotos. No usa IA.</p>
			</fieldset>
		</form>
		<template id="at-pt-tpl-actividad"><?php at_pt_html_actividad(['origen' => 'luis', 'responsable' => 'at', 'dias_habiles' => 1]); ?></template>
		<template id="at-pt-tpl-bloque"><?php at_pt_html_bloque(['nombre' => 'Nuevo bloque', 'actividades' => [['origen' => 'luis', 'responsable' => 'at', 'dias_habiles' => 1]]]); ?></template>

		<div class="at-pt-acciones">
			<?php if ($editable):
				$costo = at_pt_costo_fotos((array) ($pl['image_briefs'] ?? []), !empty($fila->propuesta_id));
				$n = (int) $costo['fotos'];
				$lista = number_format((float) $costo['usd_lista'], 4, ',', '.');
				$maximo = number_format((float) $costo['usd_max'], 4, ',', '.');
				$etiqueta_costo = '(' . $n . ($n === 1 ? ' foto' : ' fotos') . ' ≈ US$' . $lista . ')';
				$confirmar = $n > 0
					? sprintf('Se generarán %d fotos nuevas (≈ US$%s de lista; hasta US$%s si hay que rehacerlas) y la versión final del plan. ¿Aprobar?', $n, $lista, $maximo)
					: 'No hay fotos nuevas que generar. Se generará la versión final del plan. ¿Aprobar?';
				$ultimo = trim((string) ($fila->comentarios ?? ''));
			?>
			<form method="post" action="<?php echo esc_url($accion); ?>" class="at-pt-form-cambios at-pt-requiere-guardado">
				<?php echo $ocultos('at_pt_cambios'); ?>
				<label class="at-pt-campo">Qué quieres que cambie la IA
					<textarea name="comentarios" rows="3" placeholder="Ej.: separa la capacitación en dos sesiones y agrega la carga de productos"></textarea></label>
				<?php if ($ultimo !== ''): ?><p class="description">Último pedido: <?php echo esc_html($ultimo); ?></p><?php endif; ?>
				<p class="description">La IA aplica tus comentarios y respeta los días que editaste tú. No genera fotos.</p>
				<p class="at-pt-botones"><button type="submit" class="button">✏️ Pedir cambios</button></p>
			</form>
			<form method="post" action="<?php echo esc_url($accion); ?>" class="at-pt-form-aprobar at-pt-requiere-guardado" onsubmit="return confirm(<?php echo esc_attr(wp_json_encode($confirmar, JSON_UNESCAPED_UNICODE)); ?>);">
				<?php echo $ocultos('at_pt_aprobar'); ?>
				<p class="at-pt-botones"><button type="submit" class="button button-primary">✅ Aprobar y generar versión final <?php echo esc_html($etiqueta_costo); ?></button></p>
			</form>
			<?php endif; ?>
			<p class="at-pt-botones"><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=automatiza-followup&pt_plan=' . $id)); ?>">📅 Agendar llamada de seguimiento</a></p>
			<p class="description">Abre «Reuniones de seguimiento» con los datos del cliente; el evento, el correo y el WhatsApp salen solo si dejas sus casillas marcadas.</p>
		</div>
		<?php endif; ?>
	</div>
	<?php
}
```

- [ ] **Step 5: Cargar `panel.php` desde `cargar.php`**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
F=wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php
L="require_once __DIR__ . '/panel.php';"
[ "$(tail -c1 "$F" | wc -l)" -eq 1 ] || echo >> "$F"
grep -qxF "$L" "$F" || printf '%s
' "$L" >> "$F"
"$PHP" -l "$F" && tail -2 "$F"
```
Expected: `No syntax errors detected in …cargar.php` y las dos últimas líneas `require_once __DIR__ . '/ajustes.php';` y
`require_once __DIR__ . '/panel.php';`.

- [ ] **Step 6: Correr la prueba: la pestaña se dibuja, los assets todavía no existen**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/panel-render-wp-test.php | grep -v "^ok "; echo "exit=${PIPESTATUS[0]}"
```
Expected (falla solo lo que depende de `plan-trabajo.css` y `plan-trabajo.js`, que `at_pt_encolar_assets()` encola solo
si existen):
```
FALLA en la ficha del cliente se encolan plan-trabajo.css y plan-trabajo.js

1 FALLAS
exit=1
```

- [ ] **Step 7: Estilos de la pestaña**

Crear `wp-content/themes/automatiza-tech/assets/css/plan-trabajo.css`:
```css
/* Plan de trabajo (inc/plan-trabajo/panel.php): pestaña «🗓️ Plan de trabajo» de la ficha del cliente en el CRM.
   Se encola solo en admin.php?page=automatiza-crm-ficha. Mismos colores que el módulo de propuestas. */
.at-pt { --at-navy: #0d1b2a; --at-teal: #00d9c0; --at-borde: #e2e8f0; --at-suave: #f8fafc; --at-gris: #64748b; }

/* La barra de pestañas de la ficha esconde lo que no cabe (overflow: hidden, sin salto de línea): con la quinta
   pestaña, en escritorio pasa a la línea siguiente en vez de cortarse. Bajo 768 px el CRM ya la hace desplazable. */
@media screen and (min-width: 768px) {
  .ficha-col > .ficha-tabs { flex-wrap: wrap; }
}
/* En celular esa barra es desplazable (overflow-x: auto), pero la columna de la grilla toma el ancho mínimo de su
   contenido y estira la página entera (medido a 390 px: 543 px con 4 pestañas, 691 px con 5). Con min-width: 0 la
   columna del plan cabe en la pantalla y la barra se desplaza. */
.ficha-grid > .ficha-col:has(> #tab-plan) { min-width: 0; }

.at-pt h3 { margin: 0 0 8px; }
.at-pt-sub { margin: 0 0 12px; color: var(--at-gris); overflow-wrap: anywhere; }
.at-pt-caja .at-pt-sub { margin: 6px 0 0; font-size: 12px; }
.at-pt .notice.inline { margin: 8px 0 12px; }
.at-pt .notice.inline form { margin: 0 0 8px; }

.at-pt-estado { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; line-height: 1.6; background: #e2e8f0; color: #334155; white-space: nowrap; }
.at-pt-estado--generando, .at-pt-estado--cambios, .at-pt-estado--aprobando { background: #dbeafe; color: #1e40af; }
.at-pt-estado--borrador { background: #fef3c7; color: #92400e; }
.at-pt-estado--listo { background: #ccfbf1; color: #115e59; }
.at-pt-estado--enviado { background: #dcfce7; color: #166534; }
.at-pt-estado--error { background: #fee2e2; color: #991b1b; }

.at-pt-selector { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 12px; }
.at-pt-selector .button { white-space: normal; height: auto; line-height: 1.4; padding-top: 4px; padding-bottom: 4px; }

.at-pt-resumen { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin: 0 0 12px; }
.at-pt-caja { border: 1px solid var(--at-borde); border-radius: 6px; padding: 10px 12px; background: var(--at-suave); min-width: 0; overflow-wrap: anywhere; }
.at-pt-caja h4 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--at-gris); }
.at-pt-caja p { margin: 0 0 4px; }

.at-pt fieldset { border: 0; margin: 0; padding: 0; min-width: 0; }
.at-pt fieldset[disabled] { opacity: .65; }
.at-pt-campo { display: block; margin: 0 0 10px; font-weight: 600; font-size: 12px; color: #334155; }
.at-pt-campo input[type="text"], .at-pt-campo input[type="date"], .at-pt-campo input[type="number"], .at-pt-campo textarea {
  display: block; width: 100%; max-width: 100%; box-sizing: border-box; margin-top: 3px; font-weight: 400; font-size: 14px;
}
.at-pt-campo input[type="date"] { max-width: 220px; }
.at-pt-campo input[type="number"] { max-width: 120px; }

.at-pt-fase { border-top: 3px solid var(--at-teal); padding: 12px 0 4px; margin: 16px 0 0; }
.at-pt-fase > h4 { margin: 0 0 8px; font-size: 15px; color: var(--at-navy); }
.at-pt-bloque { border: 1px solid var(--at-borde); border-radius: 6px; padding: 10px; margin: 0 0 10px; background: #fff; }
.at-pt-bloque-cab { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 0 10px; }
.at-pt-bloque-pie { display: flex; flex-wrap: wrap; gap: 4px 14px; align-items: center; margin: 0 0 4px; }
.at-pt-bloque-pie .at-pt-quitar-bloque { margin-left: auto; }

.at-pt-act { border-top: 1px dashed var(--at-borde); padding: 8px 0; }
.at-pt-act-l1 { display: flex; gap: 6px; align-items: center; }
.at-pt-act-l1 .at-pt-a-nombre { flex: 1 1 auto; min-width: 0; font-size: 14px; }
.at-pt-act-l2 { display: flex; flex-wrap: wrap; gap: 6px 14px; align-items: center; margin-top: 6px; font-size: 12px; color: #334155; }
.at-pt-act-l2 label { display: inline-flex; align-items: center; gap: 4px; }
.at-pt-act-l2 select { max-width: 150px; }
.at-pt-act-l3 { margin-top: 6px; }
.at-pt-act-l3 input { width: 100%; max-width: 100%; box-sizing: border-box; font-size: 12px; }
.at-pt-a-dias { width: 4.5em; }
.at-pt-check { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; }
.at-pt-origen { display: inline-block; padding: 0 8px; border-radius: 999px; font-size: 11px; font-weight: 600; line-height: 1.8; white-space: nowrap; }
.at-pt-origen--tabla { background: #f1f5f9; color: #475569; }
.at-pt-origen--ia { background: #fef3c7; color: #92400e; }
.at-pt-origen--luis { background: #dbeafe; color: #1e40af; }
.at-pt-fechas { color: var(--at-gris); white-space: nowrap; font-size: 12px; }
.at-pt-quitar { background: none; border: 0; color: #b32d2e; cursor: pointer; padding: 4px 6px; font-size: 12px; line-height: 1; min-width: 32px; min-height: 32px; }
.at-pt-quitar:hover, .at-pt-quitar:focus-visible { text-decoration: underline; }
.at-pt-quitar:disabled { color: #a7aaad; cursor: default; }

.at-pt-textos { margin-top: 16px; }
.at-pt-botones { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 4px; }
.at-pt-botones .button { white-space: normal; height: auto; min-height: 32px; line-height: 1.4; padding-top: 5px; padding-bottom: 5px; }
.at-pt-acciones { border-top: 1px solid var(--at-borde); margin-top: 16px; padding-top: 12px; }
.at-pt-acciones form { margin: 0 0 12px; }

@media screen and (max-width: 767px) {
  .at-pt-bloque-cab { grid-template-columns: minmax(0, 1fr); }
  .at-pt-botones .button { width: 100%; text-align: center; }
  .at-pt-campo input[type="date"] { max-width: 100%; }
}
```

- [ ] **Step 8: Escribir la prueba que falla (el JavaScript en un navegador)**

La lógica del JS (armar `plan_json`, bloquear «Pedir cambios» y «Aprobar» con cambios sin guardar, las plantillas de
«+ Actividad» y «+ Bloque», abrir la pestaña por la dirección) se prueba en Chrome sin ventana, con la pestaña que dibuja
el servidor. La prueba exige que `serializar()` dé exactamente `ptc_plan_json()`, que es lo que la prueba de las
acciones (Step 14) manda como `plan_json`: si el JS cambia claves, tipos u origen, falla aquí.

**Requisito de herramientas (D16):** esta prueba necesita **Chrome o Edge** instalado. Lo toma de la variable de entorno
`CHROME` o, si no está, de las rutas de siempre en Windows (`C:/Program Files[ (x86)]/Google/Chrome/Application/chrome.exe`
y `…/Microsoft/Edge/Application/msedge.exe`). Si no encuentra ninguno sale con **exit 2** y el mensaje «No se encontró
Chrome ni Edge: define CHROME con la ruta del navegador.»: eso no es un paso rojo, es la máquina sin navegador (en la de
Luis están los dos). Las demás pruebas del plan no lo necesitan.

Crear `tests/plan/panel-js-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/panel-js-wp-test.php
// Task 9: plan-trabajo.js en un navegador de verdad. Dibuja la pestaña con at_pt_render_pestana() y datos [PRUEBA],
// la deja en un HTML temporal con plan-trabajo.css, plan-trabajo.js y un chequeo al final, y la abre con Chrome (o
// Edge) sin ventana (--headless=new --dump-dom). El chequeo anota «ok …» o «FALLA …» en <pre id="at-pt-resultado">.
// El navegador sale de la variable de entorno CHROME o de las rutas de siempre en Windows.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_render_pestana', 'ptc_plan_json');

$navegador = (string) getenv('CHROME');
if ($navegador === '') {
	foreach (['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe'] as $p) {
		if (is_file($p)) {
			$navegador = $p;
			break;
		}
	}
}
if ($navegador === '' || !is_file($navegador)) {
	fwrite(STDERR, "No se encontró Chrome ni Edge: define CHROME con la ruta del navegador.\n");
	exit(2);
}
$js = get_template_directory() . '/assets/js/plan-trabajo.js';
$css = get_template_directory() . '/assets/css/plan-trabajo.css';
ok(is_file($js) && is_file($css), 'existen assets/js/plan-trabajo.js y assets/css/plan-trabajo.css');
if (!is_file($js) || !is_file($css)) {
	fin();
}

/** Borra una carpeta temporal entera (el perfil del navegador incluido); lo que no se pueda borrar se deja. */
function ptj_borrar(string $ruta): void {
	if (is_dir($ruta) && !is_link($ruta)) {
		foreach ((array) scandir($ruta) as $e) {
			if ($e !== '.' && $e !== '..') {
				ptj_borrar($ruta . '/' . $e);
			}
		}
		@rmdir($ruta);
	} elseif (file_exists($ruta) || is_link($ruta)) {
		@unlink($ruta);
	}
}

// La pestaña de un plan en borrador, con propuesta, tal como la dibuja el servidor.
wp_set_current_user(pt_admin_id());
$m = ptc_marca();
$c = ptc_cliente($m, "prueba-plan-{$m}@example.com");
$k = ptc_contrato($c['tech'], $m, ptc_propuesta($m));
$f = ptc_plan($k);
$plan = ptc_sembrar((int) $f->id);
$_GET = ['page' => 'automatiza-crm-ficha', 'id' => (string) $c['crm'], 'pt' => (string) $f->id];
ob_start();
at_pt_render_pestana(ptc_cliente_fila($c['crm']));
$pestana = (string) ob_get_clean();
$_GET = [];
ptc_limpiar(); // el HTML ya quedó dibujado: los datos no hacen falta

// Chequeo que corre en el navegador después de plan-trabajo.js. alert() y confirm() se reemplazan para anotar lo que
// mostrarían (confirm() contesta «Cancelar»); los envíos son eventos submit sintéticos, que nunca navegan.
$chequeo = <<<'JS'
(function () {
  var r = [];
  function ok(c, m) { r.push((c ? 'ok   ' : 'FALLA ') + m); }
  var avisos = [];
  var confirmaciones = [];
  window.alert = function (m) { avisos.push(String(m)); };
  window.confirm = function (m) { confirmaciones.push(String(m)); return false; };
  function enviar(f) {
    var e = new Event('submit', { bubbles: true, cancelable: true });
    f.dispatchEvent(e);
    return e;
  }
  try {
    var api = window.atPlanTrabajo;
    ok(!!(api && typeof api.serializar === 'function' && typeof api.abrirPestanaPlan === 'function'), 'plan-trabajo.js publica window.atPlanTrabajo con serializar() y abrirPestanaPlan()');
    ok(document.getElementById('tab-plan').classList.contains('active') && document.querySelector('.ficha-tab[data-target="tab-plan"]').classList.contains('active') && !document.getElementById('tab-resumen').classList.contains('active'), 'con #tab-plan en la dirección se abre la pestaña del plan');
    var form = document.querySelector('.at-pt-form-plan');
    ok(JSON.stringify(api.serializar(form)) === JSON.stringify(window.AT_PT_ESPERADO), 'serializar() arma exactamente el plan_json que espera el servidor (ptc_plan_json: claves, orden, tipos, detalle y origen)');
    var aprobar = document.querySelector('.at-pt-form-aprobar');
    var e = enviar(aprobar);
    ok(confirmaciones.length === 1 && confirmaciones[0].indexOf('fotos nuevas') !== -1 && e.defaultPrevented && avisos.length === 0, 'sin cambios, «Aprobar» pide confirmar las fotos y su costo; al cancelar no se envía');
    var dias = form.querySelector('.at-pt-a-dias');
    dias.value = '9';
    dias.dispatchEvent(new Event('input', { bubbles: true }));
    confirmaciones = [];
    e = enviar(aprobar);
    ok(e.defaultPrevented && confirmaciones.length === 0 && avisos.length === 1 && avisos[0].indexOf('cambios sin guardar') !== -1, 'con cambios sin guardar, «Aprobar» se bloquea con un aviso antes de confirmar');
    avisos = [];
    e = enviar(document.querySelector('.at-pt-form-cambios'));
    ok(e.defaultPrevented && avisos.length === 1, 'con cambios sin guardar, «Pedir cambios» también se bloquea');
    ok(api.serializar(form).fases[0].bloques[0].actividades[0].dias_habiles === 9, 'los días editados viajan como número');
    var detalle = form.querySelector('.at-pt-a-detalle');
    detalle.value = '  [PRUEBA] Detalle editado  ';
    ok(api.serializar(form).fases[0].bloques[0].actividades[0].detalle === '[PRUEBA] Detalle editado', 'el detalle editado viaja en plan_json, sin espacios de sobra');
    var bloque = form.querySelector('.at-pt-fase[data-clave="diseno_desarrollo"] .at-pt-bloque');
    var antes = bloque.querySelectorAll('.at-pt-act').length;
    bloque.querySelector('.at-pt-agregar-act').click();
    var filas = bloque.querySelectorAll('.at-pt-act');
    var nueva = filas[filas.length - 1];
    nueva.querySelector('.at-pt-a-nombre').value = '[PRUEBA] Actividad nueva';
    var acts = api.serializar(form).fases[0].bloques[0].actividades;
    var ult = acts[acts.length - 1];
    ok(filas.length === antes + 1 && ult.nombre === '[PRUEBA] Actividad nueva' && ult.origen === 'luis' && ult.dias_habiles === 1 && ult.responsable === 'at' && ult.en_paralelo === false, '«+ Actividad» agrega una fila de Luis: 1 día, AutomatizaTech, no en paralelo');
    nueva.querySelector('.at-pt-quitar-act').click();
    ok(bloque.querySelectorAll('.at-pt-act').length === antes, '✕ quita la actividad');
    var fase = form.querySelector('.at-pt-fase[data-clave="implementacion"]');
    var n = fase.querySelectorAll('.at-pt-bloque').length;
    fase.querySelector('.at-pt-agregar-bloque').click();
    var nuevo = fase.querySelectorAll('.at-pt-bloque')[n];
    nuevo.querySelector('.at-pt-a-nombre').value = '[PRUEBA] Actividad del bloque nuevo';
    var bl = api.serializar(form).fases[1].bloques;
    var b = bl[bl.length - 1];
    ok(fase.querySelectorAll('.at-pt-bloque').length === n + 1 && b.nombre === 'Nuevo bloque' && b.actividades.length === 1 && b.actividades[0].origen === 'luis', '«+ Bloque» agrega al final de la fase un bloque con una actividad de Luis');
    e = enviar(form);
    var pj = JSON.parse(form.querySelector('.at-pt-plan-json').value || 'null');
    ok(!e.defaultPrevented && pj !== null && pj.fases.length === 3 && !form.hasAttribute('data-sucio'), '«Guardar y recalcular» llena plan_json con las tres fases y da la tabla por guardada');
    avisos = [];
    confirmaciones = [];
    enviar(aprobar);
    ok(avisos.length === 0 && confirmaciones.length === 1, 'después de guardar, «Aprobar» vuelve a pedir solo la confirmación');
  } catch (err) {
    ok(false, 'el chequeo se cayó: ' + err.message);
  }
  document.getElementById('at-pt-resultado').textContent = r.join('\n');
}());
JS;

$html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Prueba de plan-trabajo.js</title>'
	. '<style>.ficha-tab-content{display:none}.ficha-tab-content.active{display:block}</style>'
	. '<style>' . file_get_contents($css) . '</style></head><body>'
	. '<div class="ficha-grid"><div class="ficha-col"><div class="ficha-tabs">'
	. '<button class="ficha-tab active" data-target="tab-resumen">Resumen</button><button class="ficha-tab" data-target="tab-plan">🗓️ Plan de trabajo</button></div>'
	. '<div class="ficha-tab-content active" id="tab-resumen">Resumen</div>'
	. '<div class="ficha-tab-content" id="tab-plan"><div class="ficha-card">' . $pestana . '</div></div></div></div>'
	. '<pre id="at-pt-resultado"></pre>'
	. '<script>window.AT_PT_ESPERADO = ' . ptc_plan_json($plan) . ';</script>'
	. '<script>' . file_get_contents($js) . '</script>'
	. '<script>' . $chequeo . '</script></body></html>';

$dir = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/at-pt-js-' . $m;
wp_mkdir_p($dir);
file_put_contents($dir . '/pestana.html', $html);
$url = 'file:///' . ltrim($dir, '/') . '/pestana.html#tab-plan';
$proc = proc_open(
	[$navegador, '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--user-data-dir=' . $dir . '/perfil', '--dump-dom', $url],
	[1 => ['pipe', 'w'], 2 => ['file', $dir . '/navegador.log', 'w']],
	$pipes
);
if (!is_resource($proc)) {
	fwrite(STDERR, "No se pudo abrir el navegador: {$navegador}\n");
	exit(2);
}
$dom = (string) stream_get_contents($pipes[1]);
fclose($pipes[1]);
proc_close($proc);

$lineas = [];
if (preg_match('#<pre id="at-pt-resultado">(.*?)</pre>#s', $dom, $mm)) {
	$lineas = array_values(array_filter(explode("\n", html_entity_decode($mm[1], ENT_QUOTES, 'UTF-8')), 'strlen'));
}
ok($lineas !== [], 'el navegador corrió el chequeo y devolvió su resultado');
if ($lineas === []) {
	fwrite(STDERR, 'Salida del navegador (primeros 2000 caracteres): ' . substr($dom, 0, 2000) . "\n");
}
foreach ($lineas as $l) {
	ok(strncmp($l, 'ok   ', 5) === 0, (string) preg_replace('/^(ok|FALLA)\s+/u', '', $l));
}
ptj_borrar($dir);
fin();
```

- [ ] **Step 9: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/panel-js-wp-test.php; echo "exit=$?"
```
Expected (falla porque `plan-trabajo.js` todavía no existe):
```
FALLA existen assets/js/plan-trabajo.js y assets/css/plan-trabajo.css

1 FALLAS
exit=1
```
Si en vez de eso sale `exit=2` con «No se encontró Chrome ni Edge…», falta el navegador (requisito del Step 8): definir
`CHROME` con su ruta y repetir.

- [ ] **Step 10: JavaScript de la pestaña**

Crear `wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js`:
```js
/* Plan de trabajo (inc/plan-trabajo/panel.php): pestaña «🗓️ Plan de trabajo» de la ficha del cliente en el CRM.
   - Abre la pestaña si la URL trae #tab-plan, pt o pt_msg (el JS de pestañas del CRM no mira la URL) y quita
     pt_msg de la barra para que recargar no repita el aviso.
   - Agrega y quita actividades y bloques de la tabla editable.
   - Al guardar, arma el JSON del plan en el campo oculto plan_json (el servidor lo valida entero).
   - Con cambios sin guardar en la tabla, no deja «Pedir cambios» ni «Aprobar» (se perderían).
   - Tras enviar un formulario de la pestaña, desactiva sus botones (evita el doble clic). */
(function () {
  'use strict';

  function abrirPestanaPlan() {
    var boton = document.querySelector('.ficha-tab[data-target="tab-plan"]');
    var panel = document.getElementById('tab-plan');
    var col = boton ? boton.closest('.ficha-col') : null;
    if (!boton || !panel || !col) { return; }
    Array.prototype.forEach.call(col.querySelectorAll('.ficha-tab'), function (t) { t.classList.remove('active'); });
    Array.prototype.forEach.call(col.querySelectorAll('.ficha-tab-content'), function (c) { c.classList.remove('active'); });
    boton.classList.add('active');
    panel.classList.add('active');
  }

  function valor(raiz, selector) {
    var el = raiz.querySelector(selector);
    return el ? String(el.value || '').trim() : '';
  }

  function marcado(raiz, selector) {
    var el = raiz.querySelector(selector);
    return !!(el && el.checked);
  }

  /* Fases → bloques → actividades, en el orden de la pantalla. Se omiten las actividades sin nombre y los
     bloques y fases que quedan vacíos. Los días van como número (0 si no es un número: el servidor lo rechaza). */
  function serializar(form) {
    var fases = [];
    Array.prototype.forEach.call(form.querySelectorAll('.at-pt-fase'), function (f) {
      var bloques = [];
      Array.prototype.forEach.call(f.querySelectorAll('.at-pt-bloque'), function (b) {
        var actividades = [];
        Array.prototype.forEach.call(b.querySelectorAll('.at-pt-act'), function (a) {
          var nombre = valor(a, '.at-pt-a-nombre');
          if (nombre === '') { return; }
          var dias = parseInt(valor(a, '.at-pt-a-dias'), 10);
          actividades.push({
            nombre: nombre,
            detalle: valor(a, '.at-pt-a-detalle'),
            responsable: valor(a, '.at-pt-a-responsable') || 'at',
            dias_habiles: isNaN(dias) ? 0 : dias,
            en_paralelo: marcado(a, '.at-pt-a-paralelo'),
            servicio: a.getAttribute('data-servicio') || '',
            etapa: a.getAttribute('data-etapa') || '',
            origen: a.getAttribute('data-origen') || 'luis'
          });
        });
        if (!actividades.length) { return; }
        bloques.push({
          nombre: valor(b, '.at-pt-b-nombre'),
          entregable: valor(b, '.at-pt-b-entregable'),
          entrega: marcado(b, '.at-pt-b-entrega'),
          actividades: actividades
        });
      });
      if (!bloques.length) { return; }
      fases.push({ clave: f.getAttribute('data-clave') || '', descripcion: valor(f, '.at-pt-f-descripcion'), bloques: bloques });
    });
    return { fases: fases };
  }

  function clonar(id) {
    var t = document.getElementById(id);
    return t && t.content && t.content.firstElementChild ? t.content.firstElementChild.cloneNode(true) : null;
  }

  function iniciarFormulario(form) {
    function marcarSucio() { form.setAttribute('data-sucio', '1'); }
    form.addEventListener('input', marcarSucio);
    form.addEventListener('change', marcarSucio);
    form.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('button') : null;
      if (!b || !form.contains(b) || b.type !== 'button') { return; }
      if (b.classList.contains('at-pt-agregar-act')) {
        var lista = b.closest('.at-pt-bloque') ? b.closest('.at-pt-bloque').querySelector('.at-pt-acts') : null;
        var nueva = clonar('at-pt-tpl-actividad');
        if (lista && nueva) { lista.appendChild(nueva); marcarSucio(); nueva.querySelector('.at-pt-a-nombre').focus(); }
      } else if (b.classList.contains('at-pt-agregar-bloque')) {
        var bloque = clonar('at-pt-tpl-bloque');
        if (bloque) { b.parentNode.insertBefore(bloque, b); marcarSucio(); bloque.querySelector('.at-pt-b-nombre').focus(); }
      } else if (b.classList.contains('at-pt-quitar-act')) {
        var act = b.closest('.at-pt-act');
        if (act) { act.parentNode.removeChild(act); marcarSucio(); }
      } else if (b.classList.contains('at-pt-quitar-bloque')) {
        var bl = b.closest('.at-pt-bloque');
        if (bl && window.confirm('¿Quitar este bloque con todas sus actividades?')) { bl.parentNode.removeChild(bl); marcarSucio(); }
      }
    });
    form.addEventListener('submit', function () {
      var campo = form.querySelector('.at-pt-plan-json');
      if (campo) { campo.value = JSON.stringify(serializar(form)); }
      form.removeAttribute('data-sucio');
    });
  }

  /* Captura en document: corre antes que el onsubmit (confirm) del formulario y puede detenerlo. */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.classList || !f.classList.contains('at-pt-requiere-guardado')) { return; }
    if (document.querySelector('.at-pt-form-plan[data-sucio="1"]')) {
      e.preventDefault();
      e.stopPropagation();
      window.alert('Tienes cambios sin guardar en la tabla. Primero usa «Guardar y recalcular».');
    }
  }, true);

  /* Burbuja en document: si el envío siguió (nadie lo canceló), se desactivan los botones de ese formulario. */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented || !f.closest || !f.closest('.at-pt')) { return; }
    setTimeout(function () {
      Array.prototype.forEach.call(f.querySelectorAll('button[type="submit"]'), function (b) { b.disabled = true; });
    }, 0);
  });

  var params = new URLSearchParams(window.location.search);
  if (window.location.hash === '#tab-plan' || params.has('pt') || params.has('pt_msg')) {
    abrirPestanaPlan();
  }
  if (params.has('pt_msg') && window.history && window.history.replaceState) {
    params.delete('pt_msg');
    window.history.replaceState(null, '', window.location.pathname + '?' + params.toString() + window.location.hash);
  }
  Array.prototype.forEach.call(document.querySelectorAll('.at-pt-form-plan'), iniciarFormulario);

  window.atPlanTrabajo = { serializar: serializar, abrirPestanaPlan: abrirPestanaPlan };
}());
```

- [ ] **Step 11: Correr las dos pruebas y ver que pasan**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/panel-js-wp-test.php; echo "exit=$?"
"$PHP" tests/plan/panel-render-wp-test.php | tail -1
```
Expected: 15 líneas `ok …` (entre ellas `ok   serializar() arma exactamente el plan_json que espera el servidor (…)`,
`ok   con cambios sin guardar, «Aprobar» se bloquea con un aviso antes de confirmar` y `ok   «+ Actividad» agrega una
fila de Luis: 1 día, AutomatizaTech, no en paralelo`), `TODO OK`, `exit=0` (tarda unos segundos: abre el navegador una
vez); y la prueba de la pestaña, `TODO OK` (33 `ok`: entre ellas `ok   el botón Aprobar muestra (8 fotos ≈ US$0,0256)`,
`ok   sin propuesta: portada y cierre también son fotos nuevas (10 fotos ≈ US$0,0320)`, `ok   sin correo: avisa que
«Sigue tu proyecto» sale sin enlace, y la pestaña se dibuja igual` y `ok   error sin plan: «Reintentar borrador» con el
motivo (Review Focus 5)`).

- [ ] **Step 12: Sintaxis**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
for f in wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php tests/plan/fixtures-panel.php tests/plan/panel-render-wp-test.php tests/plan/panel-js-wp-test.php; do "$PHP" -l "$f"; done
node --check wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js && echo "node --check OK"
```
Expected: cuatro `No syntax errors detected in …` y `node --check OK`.

- [ ] **Step 13: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php wp-content/themes/automatiza-tech/assets/css/plan-trabajo.css wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js tests/plan/fixtures-panel.php tests/plan/panel-render-wp-test.php tests/plan/panel-js-wp-test.php
git commit -m "$(cat <<'EOF'
feat(plan): pestaña del plan de trabajo (vista, tabla editable y botones)

Estado, fechas y documento; tabla editable con origen de cada duración y
detalle de cada actividad; textos de las láminas con servicios mensuales;
Aprobar muestra fotos y costo con confirm(); assets solo en la ficha del CRM;
el JS se prueba en Chrome sin ventana contra el plan_json del servidor.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 14: Escribir la prueba que falla (acciones admin-post)**

Crear `tests/plan/panel-acciones-wp-test.php` (cubre los casos 1, 2, 3, 4 y 5 del Review Focus del lado del panel; el
`plan_json` que manda es `ptc_plan_json()`, el mismo que la prueba del JS exige a `serializar()`):
```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/panel-acciones-wp-test.php
// Task 9: acciones admin-post de la pestaña «🗓️ Plan de trabajo» (crear, guardar y recalcular, pedir cambios,
// aprobar, destrabar, reintentar). Cada una termina en redirect + exit: corre en un proceso aparte
// (accion-wp-test-run.php), que contesta las llamadas a n8n con 200 o, en modo «caido», con error de conexión.
// Cubre los casos 1 (inicio en fin de semana/feriado), 2 (plan roto no se guarda), 3 (días de Luis no se pisan),
// 4 (todas las acciones corren con un cliente sin correo y un contrato sin propuesta) y 5 (doble creación, contrato
// que no es de servicios, n8n caído) del Review Focus.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';

if (!has_action('admin_post_at_pt_guardar') || !function_exists('at_pt_plan_desde_panel')) {
	fwrite(STDERR, "Las acciones de panel.php no están cargadas: revisa inc/plan-trabajo/cargar.php.\n");
	exit(2);
}
global $wpdb;
$admin = pt_admin_id();
wp_set_current_user($admin);
$m = ptc_marca();
// La base local es compartida: los feriados de «Ajustes del chat» vuelven a como estaban al cerrar el proceso,
// también si la prueba se cae a mitad de camino.
$horario_antes = get_option('automatiza_chat_schedule', null);
register_shutdown_function(function () use ($horario_antes) {
	if ($horario_antes === null) {
		delete_option('automatiza_chat_schedule');
	} else {
		update_option('automatiza_chat_schedule', $horario_antes);
	}
});

/** Copia del payload con un campo de una actividad cambiado. */
function con_campo(array $payload, string $nombre, string $campo, $valor): array {
	foreach ($payload['fases'] as $i => $f) {
		foreach ($f['bloques'] as $j => $b) {
			foreach ($b['actividades'] as $k => $a) {
				if ($a['nombre'] === $nombre) {
					$payload['fases'][$i]['bloques'][$j]['actividades'][$k][$campo] = $valor;
				}
			}
		}
	}
	return $payload;
}

/** Copia del payload con los días de una actividad cambiados. */
function con_dias(array $payload, string $nombre, int $dias): array {
	return con_campo($payload, $nombre, 'dias_habiles', $dias);
}

/** Lo que manda el formulario «Guardar y recalcular» (plan_json como lo arma plan-trabajo.js: ptc_plan_json). */
function post_guardar(object $fila, array $payload, string $inicio, array $extra = []): array {
	return $extra + [
		'plan_id' => (string) $fila->id, 'crm_id' => (string) $fila->crm_cliente_id, 'fecha_inicio' => $inicio,
		'plan_json' => ptc_plan_json($payload), 'proyecto' => (string) ($payload['proyecto'] ?? ''),
		'necesitamos_de_ti' => implode("\n", $payload['necesitamos_de_ti'] ?? []), 'reuniones' => 'Reunión de inicio | Revisamos el plan juntos',
		'hitos' => 'Diseño aprobado | Diseño', 'mensuales' => implode("\n", $payload['soporte']['mensuales'] ?? []),
	];
}

function accion_plan(string $accion, object $fila, array $post = [], string $n8n = 'ok', int $usuario = -1, string $nonce = ''): array {
	return pt_correr_accion($accion, $usuario >= 0 ? $usuario : $GLOBALS['admin'], $nonce !== '' ? $nonce : 'at_pt_plan_' . $fila->id,
		$post + ['plan_id' => (string) $fila->id, 'crm_id' => (string) $fila->crm_cliente_id], $n8n);
}

function msg(array $r): string {
	return (string) (pt_query($r['redirect'])['pt_msg'] ?? '');
}

function releer(object $fila): object {
	return at_pt_plan((int) $fila->id);
}

// ============ Crear (botón para contratos firmados sin plan) ============
// Cliente sin correo (sin portal) y contrato sin propuesta (Review Focus 4): el plan igual se crea y se trabaja.
$c = ptc_cliente($m, '');
$k = ptc_contrato($c['tech'], $m, null);
$r = pt_correr_accion('at_pt_crear', $admin, 'at_pt_crear_' . $k, ['contrato_id' => (string) $k, 'crm_id' => (string) $c['crm']]);
$fila = at_pt_plan_de_contrato($k);
ok($fila !== null, 'Crear: el contrato (sin propuesta, de un cliente sin correo) quedó con plan');
if (!$fila) {
	fwrite(STDERR, trim($r['salida']) . "\n");
	fin();
}
$GLOBALS['ptc_creado']['planes'][] = (int) $fila->id;
$q = pt_query($r['redirect']);
ok(($q['page'] ?? '') === 'automatiza-crm-ficha' && ($q['id'] ?? '') === (string) $c['crm'] && ($q['pt'] ?? '') === (string) $fila->id && ($q['pt_msg'] ?? '') === 'creado' && $q['#'] === 'tab-plan', 'Crear: vuelve a la pestaña del plan con «creado»: ' . $r['redirect']);
ok(count($r['n8n']) === 1 && strpos($r['n8n'][0]['url'], 'plan-v1-borrador') !== false && (int) ($r['n8n'][0]['cuerpo']['id'] ?? 0) === (int) $fila->id && ($r['n8n'][0]['cuerpo']['codigo'] ?? '') === (string) $fila->codigo, 'Crear: le pide el borrador a n8n con el id y el código del plan');
ok($fila->estado === 'generando', 'Crear: el plan queda «generando»');
$r = pt_correr_accion('at_pt_crear', $admin, 'at_pt_crear_' . $k, ['contrato_id' => (string) $k, 'crm_id' => (string) $c['crm']]);
$n = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . at_pt_tabla() . ' WHERE contrato_id = %d', $k));
ok($n === 1 && msg($r) === 'ya_existe' && !$r['n8n'], 'Crear dos veces (doble clic): un solo plan y no vuelve a llamar a n8n');

// n8n caído al crear (Review Focus 5): el plan igual existe y queda en error con un motivo legible.
$k2 = ptc_contrato($c['tech'], $m . 'x', null);
$r = pt_correr_accion('at_pt_crear', $admin, 'at_pt_crear_' . $k2, ['contrato_id' => (string) $k2, 'crm_id' => (string) $c['crm']], 'caido');
$f2 = at_pt_plan_de_contrato($k2);
ok($f2 && $f2->estado === 'error' && trim((string) $f2->nota) !== '' && msg($r) === 'n8n_fallo', 'n8n caído al crear: plan en «error» con motivo (' . ($f2->nota ?? '') . ')');

// Contrato que no es de servicios, o de otro cliente: no se crea nada.
$ks = ptc_contrato($c['tech'], $m . 's', null, 'soporte');
$r = pt_correr_accion('at_pt_crear', $admin, 'at_pt_crear_' . $ks, ['contrato_id' => (string) $ks, 'crm_id' => (string) $c['crm']]);
ok(at_pt_plan_de_contrato($ks) === null && msg($r) === 'no_se_pudo' && !$r['n8n'], 'contrato de soporte: no crea plan ni llama a n8n');
$otro = ptc_cliente($m . 'o', "prueba-plan-{$m}o@example.com");
$ko = ptc_contrato($otro['tech'], $m . 'o', null);
$r = pt_correr_accion('at_pt_crear', $admin, 'at_pt_crear_' . $ko, ['contrato_id' => (string) $ko, 'crm_id' => (string) $c['crm']]);
ok(at_pt_plan_de_contrato($ko) === null && msg($r) === 'no_se_pudo', 'contrato de otro cliente: no crea plan');

// ============ Reintentar desde error sin plan (Review Focus 5) ============
$r = accion_plan('at_pt_reintentar', $f2, [], 'caido');
ok(releer($f2)->estado === 'error' && msg($r) === 'n8n_fallo', 'Reintentar con n8n todavía caído: sigue en «error»');
$r = accion_plan('at_pt_reintentar', $f2);
ok(releer($f2)->estado === 'generando' && msg($r) === 'reintentando' && count($r['n8n']) === 1 && strpos($r['n8n'][0]['url'], 'plan-v1-borrador') !== false, 'Reintentar con n8n arriba: vuelve a «generando» y pide el borrador');

// ============ Guardar y recalcular ============
ptc_sembrar((int) $fila->id);
$fila = releer($fila);
$pl = at_pt_payload($fila);
ok(ptc_actividad($pl, 'Construcción del sitio')['origen'] === 'tabla' && ptc_actividad($pl, 'Capacitación')['origen'] === 'ia', 'punto de partida: Construcción viene de la tabla y Capacitación de la IA');
// Review Focus 1: inicio un sábado (10-oct-2026) y el lunes 12 feriado → el plan parte el martes 13. El feriado se
// agrega a los que ya había (se devuelven al cerrar el proceso).
$horario = is_array($horario_antes) ? $horario_antes : [];
$horario['holidays'] = trim((string) ($horario['holidays'] ?? '')) . "\n2026-10-12";
update_option('automatiza_chat_schedule', $horario);
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, con_dias($pl, 'Construcción del sitio', 12), '2026-10-10'));
$fila = releer($fila);
$g = at_pt_payload($fila);
$a = ptc_actividad($g, 'Construcción del sitio');
ok(msg($r) === 'guardado' && $fila->estado === 'borrador', 'Guardar: vuelve con «guardado» y el plan sigue en borrador: ' . $r['redirect']);
ok((int) ($a['dias_habiles'] ?? 0) === 12 && ($a['origen'] ?? '') === 'luis', 'Guardar: la actividad editada queda con 12 días y origen «luis»');
ok(ptc_actividad($g, 'Maqueta de la portada')['origen'] === 'tabla' && ptc_actividad($g, 'Capacitación')['origen'] === 'ia', 'Guardar: las no editadas conservan su origen');
ok((string) $fila->fecha_inicio === '2026-10-13' && ($g['fases'][0]['bloques'][0]['actividades'][0]['desde'] ?? '') === '2026-10-13', 'inicio en sábado con lunes feriado: el plan parte el martes 13-oct');
ok(count($r['n8n']) === 1 && strpos($r['n8n'][0]['url'], 'plan-v1-render') !== false && ($r['n8n'][0]['cuerpo']['modo'] ?? '') === 'draft' && ($r['n8n'][0]['cuerpo']['aviso'] ?? null) === false, 'Guardar: pide la vista previa (render draft, sin aviso)');
// Review Focus 3 (lado del panel): volver a guardar sin cambios no reaplica la tabla ni pierde la marca de Luis.
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, $g, '2026-10-13'));
$a = ptc_actividad(at_pt_payload(releer($fila)), 'Construcción del sitio');
ok(msg($r) === 'guardado' && (int) ($a['dias_habiles'] ?? 0) === 12 && ($a['origen'] ?? '') === 'luis', 'volver a guardar: la actividad de Luis sigue 12/luis');

// Review Focus 2 (lado del panel): un plan roto nunca se guarda.
$antes = (string) releer($fila)->payload;
foreach ([0, 200] as $dias_malos) {
	$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, con_dias($g, 'Capacitación', $dias_malos), '2026-10-13'));
	ok(msg($r) === 'invalido' && (string) releer($fila)->payload === $antes && !$r['n8n'], "días {$dias_malos}: no se guarda, no llama a n8n y avisa «invalido»");
}
ok(at_pt_tomar_detalles((int) $fila->id) !== [], 'los errores de validación quedan para mostrarlos en la pestaña');
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, $g, '2026-10-13', ['plan_json' => 'esto no es JSON']));
ok(msg($r) === 'json_invalido' && (string) releer($fila)->payload === $antes, 'plan_json roto: no se guarda nada');
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, $g, '2026-10-13', ['plan_json' => wp_json_encode(['fases' => []])]));
ok(msg($r) === 'invalido' && (string) releer($fila)->payload === $antes, 'sin ninguna actividad: no se guarda');
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, $g, '2026-10-13', ['plan_json' => str_replace('"clave":"implementacion"', '"clave":"otra_fase"', ptc_plan_json($g))]));
ok(msg($r) === 'invalido' && (string) releer($fila)->payload === $antes && !$r['n8n'], 'una fase desconocida: no se guarda');

// Textos de las láminas y detalle de una actividad (todo editable sin IA, decisión 11).
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, con_campo($g, 'Maqueta de la portada', 'detalle', '[PRUEBA] Detalle editado'), '2026-10-13', [
	'proyecto' => '[PRUEBA] Proyecto editado', 'necesitamos_de_ti' => "Logo\n\nTextos de la empresa\n", 'reuniones' => "Inicio | Revisamos todo\nEntrega", 'garantia_meses' => '6',
	'mensuales' => "Mantención del sitio\n\nCampañas en Google Ads\n",
]));
$g2 = at_pt_payload(releer($fila));
ok(msg($r) === 'guardado' && ($g2['proyecto'] ?? '') === '[PRUEBA] Proyecto editado' && ($g2['necesitamos_de_ti'] ?? []) === ['Logo', 'Textos de la empresa'], 'Guardar: nombre del proyecto y «qué necesitamos» (sin líneas vacías)');
ok(($g2['reuniones'][0]['nombre'] ?? '') === 'Inicio' && ($g2['reuniones'][0]['detalle'] ?? '') === 'Revisamos todo' && ($g2['reuniones'][1]['nombre'] ?? '') === 'Entrega', 'Guardar: reuniones «Nombre | detalle»');
ok(($g2['soporte']['mensuales'] ?? null) === ['Mantención del sitio', 'Campañas en Google Ads'] && isset($g2['image_briefs']) && count($g2['image_briefs']) === 10, 'Guardar: servicios mensuales (sin líneas vacías), y los briefs de foto intactos');
ok(isset($g['soporte']['garantia_meses']) && (int) $g['soporte']['garantia_meses'] !== 6 && ($g2['soporte']['garantia_meses'] ?? null) === $g['soporte']['garantia_meses'], 'Guardar: un POST con garantia_meses=6 no cambia la garantía (viene del contrato, D8)');
$maqueta = ptc_actividad($g2, 'Maqueta de la portada');
ok(($maqueta['detalle'] ?? '') === '[PRUEBA] Detalle editado' && ($maqueta['origen'] ?? '') === 'tabla', 'Guardar: el detalle editado queda guardado y no cambia el origen de sus días');

// n8n caído al pedir la vista previa: lo editado se guarda igual, el plan no pasa a error y queda el motivo en la nota.
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, con_dias($g2, 'Capacitación', 2), '2026-10-13'), 'caido');
$fila = releer($fila);
ok(msg($r) === 'guardado_sin_vista' && $fila->estado === 'borrador' && (int) ptc_actividad(at_pt_payload($fila), 'Capacitación')['dias_habiles'] === 2 && strpos((string) $fila->nota, 'No se pudo pedir la vista previa') !== false, 'n8n caído al guardar: se guarda, sigue en borrador, avisa «guardado_sin_vista» y anota el motivo');
$g3 = at_pt_payload($fila);
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, $g3, '2026-10-13'));
ok(msg($r) === 'guardado' && trim((string) releer($fila)->nota) === '', 'guardar con n8n arriba borra la nota de la vista previa que falló');
// Guardar desde «listo»: vuelve a borrador (la versión final hay que aprobarla de nuevo).
ptc_estado((int) $fila->id, 'listo');
$r = accion_plan('at_pt_guardar', releer($fila), post_guardar(releer($fila), $g3, '2026-10-13'));
$fila = releer($fila);
ok(msg($r) === 'guardado' && $fila->estado === 'borrador', 'Guardar desde «listo»: vuelve a borrador');

// ============ Pedir cambios ============
$r = accion_plan('at_pt_cambios', $fila, ['comentarios' => '   ']);
ok(msg($r) === 'sin_comentarios' && releer($fila)->estado === 'borrador' && !$r['n8n'], 'Pedir cambios sin comentarios: no hace nada');
$r = accion_plan('at_pt_cambios', $fila, ['comentarios' => 'Separa la capacitación en dos sesiones.']);
$fila = releer($fila);
ok(msg($r) === 'cambios_pedidos' && $fila->estado === 'cambios' && $fila->comentarios === 'Separa la capacitación en dos sesiones.', 'Pedir cambios: guarda los comentarios y pasa a «cambios»');
ok(count($r['n8n']) === 1 && strpos($r['n8n'][0]['url'], 'plan-v1-cambios') !== false && (int) ($r['n8n'][0]['cuerpo']['id'] ?? 0) === (int) $fila->id && ($r['n8n'][0]['cuerpo']['codigo'] ?? '') === (string) $fila->codigo, 'Pedir cambios: llama al flujo 2 Cambios con el id y el código');
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, $g3, '2026-10-13'));
ok(msg($r) === 'no_editable', 'mientras la IA aplica cambios, Guardar no se puede');
$r = accion_plan('at_pt_aprobar', $fila);
ok(msg($r) === 'transicion' && releer($fila)->estado === 'cambios', 'mientras la IA aplica cambios, Aprobar no se puede');

// ============ Destrabar y volver al borrador ============
$r = accion_plan('at_pt_destrabar', $fila);
$fila = releer($fila);
ok(msg($r) === 'destrabado' && $fila->estado === 'error' && $fila->nota === 'Destrabado por Luis', 'Destrabar: «cambios» pasa a «error» con «Destrabado por Luis»');
$r = accion_plan('at_pt_reintentar', $fila);
ok(msg($r) === 'vuelto_borrador' && releer($fila)->estado === 'borrador' && !$r['n8n'], 'Reintentar con plan: vuelve a borrador sin llamar a n8n');
$r = accion_plan('at_pt_destrabar', $fila);
ok(msg($r) === 'transicion' && releer($fila)->estado === 'borrador', 'Destrabar un borrador: no corresponde');

// n8n caído al pedir cambios.
$r = accion_plan('at_pt_cambios', $fila, ['comentarios' => 'Otro cambio.'], 'caido');
$fila = releer($fila);
ok(msg($r) === 'n8n_fallo' && $fila->estado === 'error' && strpos((string) $fila->nota, 'No se pudo pedir los cambios') !== false, 'Pedir cambios con n8n caído: «error» con el motivo (' . $fila->nota . ')');
accion_plan('at_pt_reintentar', $fila);

// ============ Aprobar ============
$r = accion_plan('at_pt_aprobar', $fila);
$fila = releer($fila);
ok(msg($r) === 'aprobando' && $fila->estado === 'aprobando', 'Aprobar: pasa a «aprobando»');
ok(count($r['n8n']) === 1 && strpos($r['n8n'][0]['url'], 'plan-v1-render') !== false && ($r['n8n'][0]['cuerpo']['modo'] ?? '') === 'final' && ($r['n8n'][0]['cuerpo']['aviso'] ?? null) === true, 'Aprobar: pide el render final con aviso');
$r = accion_plan('at_pt_destrabar', $fila);
ok(releer($fila)->estado === 'error', 'Destrabar desde «aprobando»: queda en «error»');
accion_plan('at_pt_reintentar', $fila);
$r = accion_plan('at_pt_aprobar', $fila, [], 'caido');
$fila = releer($fila);
ok(msg($r) === 'n8n_fallo' && $fila->estado === 'error' && trim((string) $fila->nota) !== '', 'Aprobar con n8n caído: «error» con el motivo');
$r = accion_plan('at_pt_aprobar', releer($f2));
ok(msg($r) === 'transicion' && releer($f2)->estado === 'generando', 'Aprobar un plan que todavía se genera: no corresponde');

// ============ Seguridad y vuelta a la ficha ============
accion_plan('at_pt_reintentar', $fila);
$r = accion_plan('at_pt_aprobar', $fila, [], 'ok', 0);
ok($r['redirect'] === '' && strpos($r['salida'], 'Sin permiso') !== false && releer($fila)->estado === 'borrador', 'sin manage_options: se corta y no cambia nada');
$r = accion_plan('at_pt_aprobar', $fila, [], 'ok', $admin, 'at_pt_plan_' . $f2->id);
ok($r['redirect'] === '' && releer($fila)->estado === 'borrador' && !$r['n8n'], 'con el nonce de otro plan: no cambia nada');
$r = pt_correr_accion('at_pt_aprobar', $admin, 'at_pt_plan_999999999', ['plan_id' => '999999999', 'crm_id' => (string) $c['crm']]);
ok(msg($r) === 'sin_plan', 'un plan que no existe: vuelve con «sin_plan»');
// El plan quedó con un crm_cliente_id viejo: la vuelta usa el cliente enlazado hoy a su ficha operativa.
$wpdb->update(at_pt_tabla(), ['crm_cliente_id' => (int) $c['crm'] + 1000000], ['id' => (int) $fila->id]);
$r = accion_plan('at_pt_destrabar', releer($fila));
ok((pt_query($r['redirect'])['id'] ?? '') === (string) $c['crm'], 'plan con el cliente del CRM guardado viejo: vuelve a la ficha del cliente enlazado hoy');
$wpdb->update(at_pt_tabla(), ['crm_cliente_id' => (int) $c['crm']], ['id' => (int) $fila->id]);

ptc_limpiar();
fin();
```

- [ ] **Step 15: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/panel-acciones-wp-test.php; echo "exit=$?"
```
Expected (falla porque las acciones no están registradas):
```
Las acciones de panel.php no están cargadas: revisa inc/plan-trabajo/cargar.php.
exit=2
```

- [ ] **Step 16: Implementar las acciones**

Agregar al final de `wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php` (el archivo no cierra con `?>`;
el bloque va tal cual, sin otro `<?php`). El bloque tiene barras invertidas (tres expresiones regulares), así que **no**
se pega con heredoc (D16: el heredoc de la herramienta Bash puede comérselas): se crea con la herramienta Write en
`$SCR/plan-trabajo/panel-acciones.php.txt` y se anexa con `cat`, que copia los bytes tal cual, detrás de una línea en
blanco:
```php
// ---------------------------------------------------------------------------------------------------------------
// Acciones admin-post de la pestaña. Todas terminan en at_pt_volver() (redirect + exit).
// ---------------------------------------------------------------------------------------------------------------

add_action('admin_post_at_pt_crear', 'at_pt_accion_crear');
add_action('admin_post_at_pt_guardar', 'at_pt_accion_guardar');
add_action('admin_post_at_pt_cambios', 'at_pt_accion_cambios');
add_action('admin_post_at_pt_aprobar', 'at_pt_accion_aprobar');
add_action('admin_post_at_pt_destrabar', 'at_pt_accion_destrabar');
add_action('admin_post_at_pt_reintentar', 'at_pt_accion_reintentar');

/** Vuelve a la pestaña del plan con un aviso; siempre termina la petición. */
function at_pt_volver(int $crm_id, int $plan_id, string $msg): void {
	wp_safe_redirect(at_pt_url_ficha($crm_id, $plan_id, $msg));
	exit;
}

/**
 * Cliente del CRM de un plan: el enlace actual de su ficha operativa (el mismo que usa at_pt_planes_de_crm()) o, si la
 * ficha no está enlazada, el que se guardó al crear el plan. 0 si no hay ninguno.
 */
function at_pt_crm_de_plan(object $fila): int {
	global $wpdb;
	$actual = (int) ($fila->tech_id ?? 0) > 0
		? (int) $wpdb->get_var($wpdb->prepare("SELECT crm_cliente_id FROM {$wpdb->prefix}automatiza_tech_clients WHERE id = %d", (int) $fila->tech_id))
		: 0;
	return $actual > 0 ? $actual : (int) ($fila->crm_cliente_id ?? 0);
}

/** Nonce del plan, permiso y plan de una acción del panel. Devuelve [fila, crm_id para volver]. */
function at_pt_accion_plan(): array {
	$id = absint($_POST['plan_id'] ?? 0);
	check_admin_referer('at_pt_plan_' . $id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$crm_post = absint($_POST['crm_id'] ?? 0);
	$fila = at_pt_plan($id);
	if (!$fila) {
		at_pt_volver($crm_post, 0, 'sin_plan');
	}
	$crm = at_pt_crm_de_plan($fila);
	return [$fila, $crm > 0 ? $crm : $crm_post];
}

/** Si n8n no recibió el aviso y el plan sigue en el estado intermedio, lo deja en «error» con esa nota. */
function at_pt_error_si_sigue(int $plan_id, string $estado_intermedio, string $nota): void {
	$f = at_pt_plan($plan_id);
	if ($f && (string) $f->estado === $estado_intermedio) {
		at_pt_cambiar_estado($plan_id, 'error', $nota);
	}
}

/** El contrato es de una ficha operativa enlazada a ese cliente del CRM. */
function at_pt_contrato_es_del_cliente(int $contrato_id, int $crm_id): bool {
	if ($contrato_id <= 0 || $crm_id <= 0) {
		return false;
	}
	global $wpdb;
	$crm = $wpdb->get_var($wpdb->prepare(
		"SELECT t.crm_cliente_id FROM {$wpdb->prefix}automatiza_contracts c
		 JOIN {$wpdb->prefix}automatiza_tech_clients t ON t.id = c.client_id WHERE c.id = %d",
		$contrato_id
	));
	return (int) $crm === $crm_id;
}

/**
 * Fecha de inicio para recalcular: la que escribió Luis, o la guardada, o la de defecto (primer lunes hábil
 * después de la firma). Si cae en fin de semana o feriado, se corre al hábil siguiente.
 */
function at_pt_fecha_inicio_elegida(string $pedida, object $fila, array $plan, array $feriados): string {
	$es_fecha = function (string $f): bool {
		return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
	};
	$inicio = trim($pedida);
	if (!$es_fecha($inicio)) {
		$inicio = trim((string) ($fila->fecha_inicio ?? ''));
	}
	if (!$es_fecha($inicio)) {
		$firma = (string) ($plan['fecha_firma'] ?? '');
		return at_pt_inicio_por_defecto($es_fecha($firma) ? $firma : current_time('Y-m-d'), $feriados);
	}
	return at_pt_es_habil($inicio, $feriados) ? $inicio : at_pt_siguiente_habil($inicio, $feriados);
}

/**
 * Plan guardado + lo que llegó del panel: las fases que armó plan-trabajo.js (plan_json, con el detalle de cada
 * actividad) y los textos de las láminas (proyecto, qué necesitamos, reuniones, hitos y servicios mensuales).
 * Conserva lo que el panel no edita (image_briefs, fecha_firma, soporte.garantia_meses…): la garantía viene del
 * contrato (D8) y, aunque $textos traiga 'garantia_meses', no se toca. Sin validar: lo valida
 * at_pt_validar_plan() después.
 */
function at_pt_plan_desde_panel(array $anterior, array $editado, array $textos): array {
	$plan = $anterior;
	$fases = [];
	foreach ((array) ($editado['fases'] ?? []) as $f) {
		if (!is_array($f)) {
			continue;
		}
		$bloques = [];
		foreach ((array) ($f['bloques'] ?? []) as $b) {
			if (!is_array($b)) {
				continue;
			}
			$acts = [];
			foreach ((array) ($b['actividades'] ?? []) as $a) {
				if (!is_array($a)) {
					continue;
				}
				$acts[] = [
					'nombre'       => sanitize_text_field((string) ($a['nombre'] ?? '')),
					'detalle'      => sanitize_textarea_field((string) ($a['detalle'] ?? '')),
					'responsable'  => sanitize_key((string) ($a['responsable'] ?? '')),
					'dias_habiles' => is_numeric($a['dias_habiles'] ?? null) ? (int) $a['dias_habiles'] : 0,
					'servicio'     => sanitize_key((string) ($a['servicio'] ?? '')),
					'etapa'        => sanitize_key((string) ($a['etapa'] ?? '')),
					'origen'       => sanitize_key((string) ($a['origen'] ?? '')),
					'en_paralelo'  => !empty($a['en_paralelo']),
				];
			}
			$bloques[] = [
				'nombre'      => sanitize_text_field((string) ($b['nombre'] ?? '')),
				'entregable'  => sanitize_text_field((string) ($b['entregable'] ?? '')),
				'entrega'     => !empty($b['entrega']),
				'actividades' => $acts,
			];
		}
		$fases[] = [
			'clave'       => sanitize_key((string) ($f['clave'] ?? '')),
			'descripcion' => sanitize_textarea_field((string) ($f['descripcion'] ?? '')),
			'bloques'     => $bloques,
		];
	}
	$plan['fases'] = $fases;
	$lineas = function (string $texto): array {
		return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $texto)), 'strlen'));
	};
	if (trim((string) ($textos['proyecto'] ?? '')) !== '') {
		$plan['proyecto'] = trim((string) $textos['proyecto']);
	}
	$plan['necesitamos_de_ti'] = $lineas((string) ($textos['necesitamos_de_ti'] ?? ''));
	$plan['reuniones'] = [];
	foreach ($lineas((string) ($textos['reuniones'] ?? '')) as $l) {
		$p = array_map('trim', explode('|', $l, 2));
		$plan['reuniones'][] = ['nombre' => $p[0], 'detalle' => $p[1] ?? ''];
	}
	$plan['hitos'] = [];
	foreach ($lineas((string) ($textos['hitos'] ?? '')) as $l) {
		$p = array_map('trim', explode('|', $l, 2));
		if ($p[0] !== 'Entrega estimada') {
			$plan['hitos'][] = ['nombre' => $p[0], 'despues_de' => $p[1] ?? ''];
		}
	}
	$soporte = is_array($plan['soporte'] ?? null) ? $plan['soporte'] : ['garantia_meses' => 3, 'mensuales' => []];
	if (array_key_exists('mensuales', $textos)) {
		$soporte['mensuales'] = $lineas((string) $textos['mensuales']);
	}
	$plan['soporte'] = $soporte;
	return $plan;
}

/** «Crear plan de trabajo» (contrato firmado sin plan): crea el plan y pide el borrador a n8n. */
function at_pt_accion_crear(): void {
	$contrato_id = absint($_POST['contrato_id'] ?? 0);
	check_admin_referer('at_pt_crear_' . $contrato_id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$crm_id = absint($_POST['crm_id'] ?? 0);
	$ya = at_pt_plan_de_contrato($contrato_id);
	if ($ya) {
		at_pt_volver($crm_id, (int) $ya->id, 'ya_existe');
	}
	if (!at_pt_contrato_es_del_cliente($contrato_id, $crm_id)) {
		at_pt_volver($crm_id, 0, 'no_se_pudo');
	}
	$id = at_pt_crear_plan($contrato_id);
	if (is_wp_error($id) || (int) $id <= 0) {
		at_pt_volver($crm_id, 0, 'no_se_pudo');
	}
	$id = (int) $id;
	// Si n8n no recibe el aviso, at_pt_iniciar_borrador() (Task 6) deja el plan en «error» con el motivo.
	$motivo = at_pt_iniciar_borrador($id);
	at_pt_volver($crm_id, $id, $motivo === '' ? 'creado' : 'n8n_fallo');
}

/**
 * «Guardar y recalcular» (sin IA), en el orden de uso único de la Task 2: validar lo que llegó → marcar lo que
 * editó Luis → validar otra vez → fechas. Guarda, deja el plan en borrador y pide la vista previa.
 */
function at_pt_accion_guardar(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	$anterior = at_pt_payload($fila);
	if (empty($anterior['fases']) || !in_array((string) $fila->estado, ['borrador', 'listo', 'error'], true)) {
		at_pt_volver($crm, $id, 'no_editable');
	}
	$editado = json_decode((string) wp_unslash($_POST['plan_json'] ?? ''), true);
	if (!is_array($editado) || !isset($editado['fases']) || !is_array($editado['fases'])) {
		at_pt_volver($crm, $id, 'json_invalido');
	}
	$textos = [
		'proyecto'          => sanitize_text_field(wp_unslash($_POST['proyecto'] ?? '')),
		'necesitamos_de_ti' => sanitize_textarea_field(wp_unslash($_POST['necesitamos_de_ti'] ?? '')),
		'reuniones'         => sanitize_textarea_field(wp_unslash($_POST['reuniones'] ?? '')),
		'hitos'             => sanitize_textarea_field(wp_unslash($_POST['hitos'] ?? '')),
		'mensuales'         => sanitize_textarea_field(wp_unslash($_POST['mensuales'] ?? '')),
	];
	$v = at_pt_validar_plan(at_pt_plan_desde_panel($anterior, $editado, $textos));
	if (empty($v['ok'])) {
		at_pt_guardar_detalles($id, (array) ($v['errores'] ?? []));
		at_pt_volver($crm, $id, 'invalido');
	}
	$v2 = at_pt_validar_plan(at_pt_marcar_ediciones($anterior, (array) $v['plan'], 'luis'));
	if (empty($v2['ok'])) {
		at_pt_guardar_detalles($id, (array) ($v2['errores'] ?? []));
		at_pt_volver($crm, $id, 'invalido');
	}
	$feriados = at_pt_feriados();
	$plan = (array) $v2['plan'];
	$inicio = at_pt_fecha_inicio_elegida((string) wp_unslash($_POST['fecha_inicio'] ?? ''), $fila, $plan, $feriados);
	$plan = at_pt_calcular_fechas($plan, $inicio, $feriados);
	// Desde «listo» o «error» vuelve a borrador (la versión final hay que aprobarla de nuevo).
	if ((string) $fila->estado !== 'borrador' && !at_pt_cambiar_estado($id, 'borrador', '')) {
		at_pt_volver($crm, $id, 'transicion');
	}
	// La nota vieja (p. ej. «No se pudo pedir la vista previa…») se borra: describe un intento anterior.
	if (!at_pt_guardar($id, ['payload' => $plan, 'fecha_inicio' => $inicio, 'nota' => ''])) {
		at_pt_volver($crm, $id, 'error_guardar');
	}
	at_pt_guardar_detalles($id, (array) ($v['avisos'] ?? []));
	$motivo = at_pt_pedir_render($id, 'draft', false);
	at_pt_volver($crm, $id, $motivo === '' ? 'guardado' : 'guardado_sin_vista');
}

/** «Pedir cambios»: guarda los comentarios y llama al flujo «Plan de trabajo · 2 Cambios» con {id, codigo}. */
function at_pt_accion_cambios(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	$comentarios = trim(sanitize_textarea_field(wp_unslash($_POST['comentarios'] ?? '')));
	if ($comentarios === '') {
		at_pt_volver($crm, $id, 'sin_comentarios');
	}
	if (empty(at_pt_payload($fila)['fases']) || !at_pt_transicion_valida((string) $fila->estado, 'cambios')) {
		at_pt_volver($crm, $id, 'transicion');
	}
	if (!at_pt_guardar($id, ['comentarios' => mb_substr($comentarios, 0, 4000)])) {
		at_pt_volver($crm, $id, 'error_guardar');
	}
	if (!at_pt_cambiar_estado($id, 'cambios', '')) {
		at_pt_volver($crm, $id, 'transicion');
	}
	$motivo = at_pt_llamar_n8n(AT_N8N_PLAN_CAMBIOS, ['id' => $id, 'codigo' => (string) $fila->codigo]);
	if ($motivo !== '') {
		at_pt_error_si_sigue($id, 'cambios', 'No se pudo pedir los cambios: ' . $motivo);
	}
	at_pt_volver($crm, $id, $motivo === '' ? 'cambios_pedidos' : 'n8n_fallo');
}

/** «Aprobar»: versión final con fotos (el costo se confirmó en el navegador). */
function at_pt_accion_aprobar(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	if (empty(at_pt_payload($fila)['fases']) || !at_pt_transicion_valida((string) $fila->estado, 'aprobando')) {
		at_pt_volver($crm, $id, 'transicion');
	}
	if (!at_pt_cambiar_estado($id, 'aprobando', '')) {
		at_pt_volver($crm, $id, 'transicion');
	}
	// Si n8n no recibe el aviso, at_pt_pedir_render() (Task 6) pasa el plan de «aprobando» a «error» con el motivo.
	$motivo = at_pt_pedir_render($id, 'final', true);
	at_pt_volver($crm, $id, $motivo === '' ? 'aprobando' : 'n8n_fallo');
}

/** «Destrabar»: un plan que quedó esperando a n8n pasa a «error» para poder reintentar. */
function at_pt_accion_destrabar(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	if (!in_array((string) $fila->estado, ['generando', 'cambios', 'aprobando'], true)) {
		at_pt_volver($crm, $id, 'transicion');
	}
	at_pt_cambiar_estado($id, 'error', 'Destrabado por Luis');
	at_pt_volver($crm, $id, 'destrabado');
}

/**
 * Desde «error»: con payload vuelve a borrador sin llamar a nadie; sin payload pide otra vez el borrador
 * (at_pt_iniciar_borrador() lo pasa a «generando» y, si n8n no recibe el aviso, lo deja en «error» con el motivo).
 */
function at_pt_accion_reintentar(): void {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	if ((string) $fila->estado !== 'error') {
		at_pt_volver($crm, $id, 'transicion');
	}
	if (!empty(at_pt_payload($fila)['fases'])) {
		if (!at_pt_cambiar_estado($id, 'borrador', '')) {
			at_pt_volver($crm, $id, 'transicion');
		}
		at_pt_volver($crm, $id, 'vuelto_borrador');
	}
	$motivo = at_pt_iniciar_borrador($id);
	at_pt_volver($crm, $id, $motivo === '' ? 'reintentando' : 'n8n_fallo');
}
```
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
echo >> wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php && cat "$SCR/plan-trabajo/panel-acciones.php.txt" >> wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php
grep -cF '\r\n|\r|\n' wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php
"$PHP" -l wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php
```
Expected: `1` (las barras invertidas llegaron) y `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php`.

- [ ] **Step 17: Correr la prueba y ver que pasa**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/panel-acciones-wp-test.php; echo "exit=$?"
for t in panel-render panel-js; do printf '%-14s ' $t; "$PHP" tests/plan/$t-wp-test.php | tail -1; done
```
Expected: 49 líneas `ok …` (entre ellas `ok   inicio en sábado con lunes feriado: el plan parte el martes 13-oct`,
`ok   volver a guardar: la actividad de Luis sigue 12/luis`, `ok   días 200: no se guarda, no llama a n8n y avisa
«invalido»`, `ok   una fase desconocida: no se guarda`, `ok   Guardar: servicios mensuales (sin líneas
vacías), y los briefs de foto intactos`, `ok   Guardar: un POST con garantia_meses=6 no cambia la garantía (viene del
contrato, D8)`, `ok   guardar con n8n arriba borra la nota de la vista previa que falló` y
`ok   n8n caído al crear: plan en «error» con motivo (No se pudo pedir el borrador: n8n no respondió: …)`), `TODO OK`,
`exit=0`; y `panel-render` y `panel-js` siguen en `TODO OK`. Cada acción corre en un proceso aparte: la prueba tarda
del orden de un minuto.

- [ ] **Step 18: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php tests/plan/panel-acciones-wp-test.php
git commit -m "$(cat <<'EOF'
feat(plan): acciones de la pestaña del plan (crear, guardar, cambios, aprobar, destrabar, reintentar)

Guardar valida, marca los días de Luis y vuelve a validar (orden de la
Task 2), corre el inicio al hábil siguiente, limpia la nota vieja y pide la
vista previa; n8n caído deja el plan en error con motivo y Reintentar; un
plan roto nunca se guarda; la vuelta usa el cliente enlazado hoy.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 19: Escribir la prueba que falla (la ficha real del CRM)**

Crear `tests/plan/ficha-crm-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/ficha-crm-wp-test.php
// Task 9: la ficha real del CRM (wp-content/mu-plugins/crm-ai-completo.php) trae la pestaña «🗓️ Plan de trabajo»
// después de «📜 Contratos y operación», con el panel del plan adentro, solo para administradores.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';

if (!isset($GLOBALS['at_crm_ai']) || !method_exists($GLOBALS['at_crm_ai'], 'render_ficha_cliente')) {
	fwrite(STDERR, "El mu-plugin crm-ai-completo.php no está cargado en ese sitio.\n");
	exit(2);
}
$m = ptc_marca();
$c = ptc_cliente($m, "prueba-plan-{$m}@example.com");
$k = ptc_contrato($c['tech'], $m, null);
$f = ptc_plan($k);
ptc_sembrar((int) $f->id);

function ficha(int $crm): string {
	$_GET = ['page' => 'automatiza-crm-ficha', 'id' => (string) $crm];
	ob_start();
	$GLOBALS['at_crm_ai']->render_ficha_cliente();
	$h = (string) ob_get_clean();
	$_GET = [];
	return $h;
}

wp_set_current_user(pt_admin_id());
$h = ficha($c['crm']);
$boton_op = strpos($h, 'data-target="tab-operacion"');
$boton_plan = strpos($h, '<button class="ficha-tab" data-target="tab-plan">🗓️ Plan de trabajo</button>');
ok($boton_plan !== false, 'la ficha tiene el botón «🗓️ Plan de trabajo»');
ok($boton_op !== false && $boton_plan !== false && $boton_op < $boton_plan, 'va después de «📜 Contratos y operación»');
$fin_op = strpos($h, '</div><!-- /tab-operacion -->');
$panel = strpos($h, '<div class="ficha-tab-content" id="tab-plan">');
ok($fin_op !== false && $panel !== false && $fin_op < $panel, 'el panel id="tab-plan" va después del de «Contratos y operación»');
ok($panel !== false && strpos($h, 'value="Construcción del sitio"', $panel) !== false && strpos($h, '</div><!-- /tab-plan -->', $panel) !== false, 'el panel trae el plan del cliente y se cierra');
ok(substr_count($h, 'id="tab-plan"') === 1, 'hay un solo panel del plan');

// Ficha de un id que no existe (p. ej. un enlace viejo): $cliente es null y la ficha se dibuja como antes, sin la
// pestaña del plan y sin error fatal (at_pt_render_pestana() pide un arreglo).
global $wpdb;
$no_existe = (int) $wpdb->get_var("SELECT COALESCE(MAX(id), 0) + 1000 FROM {$wpdb->prefix}crm_clientes");
$h = ficha($no_existe);
ok(strpos($h, 'data-target="tab-operacion"') !== false && strpos($h, 'id="tab-plan"') === false, 'ficha de un id que no existe: se dibuja como antes, sin la pestaña y sin error fatal');

// Sin manage_options (aquí, sin sesión: id 0) la pestaña no aparece aunque el resto de la ficha se dibuje.
wp_set_current_user(0);
$h = ficha($c['crm']);
ok(strpos($h, 'data-target="tab-operacion"') !== false && strpos($h, 'data-target="tab-plan"') === false && strpos($h, 'id="tab-plan"') === false, 'sin manage_options no se ve la pestaña del plan');

wp_set_current_user(pt_admin_id());
ptc_limpiar();
fin();
```

- [ ] **Step 20: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/ficha-crm-wp-test.php; echo "exit=$?"
```
Expected (falla porque la ficha todavía no tiene la pestaña; la ficha de un id que no existe ya se dibuja hoy):
```
FALLA la ficha tiene el botón «🗓️ Plan de trabajo»
FALLA va después de «📜 Contratos y operación»
FALLA el panel id="tab-plan" va después del de «Contratos y operación»
FALLA el panel trae el plan del cliente y se cierra
FALLA hay un solo panel del plan
ok   ficha de un id que no existe: se dibuja como antes, sin la pestaña y sin error fatal
ok   sin manage_options no se ve la pestaña del plan

5 FALLAS
exit=1
```

- [ ] **Step 21: Botón de la pestaña en `crm-ai-completo.php` (líneas 1984-1985)**

`crm-ai-completo.php` está en CRLF en el árbol de trabajo (`core.autocrlf=true`: 8564 de 8564 líneas). Con la
herramienta Edit, que respeta los fines de línea (si se hace con un script, convertir el patrón a CRLF antes de buscar).
La condición lleva `is_array($cliente)` en el botón y en el panel: con un id que no existe, `$cliente` es `null`
(`crm-ai-completo.php:1494` no lo revisa) y `at_pt_render_pestana(array $cliente)` lanzaría un `TypeError` que hoy no
existe; así nunca aparece el botón sin su panel.
old_string:
```php
                        <button class="ficha-tab" data-target="tab-operacion">📜 Contratos y operación</button>
                        <?php endif; ?>
```
new_string:
```php
                        <button class="ficha-tab" data-target="tab-operacion">📜 Contratos y operación</button>
                        <?php if (is_array($cliente) && function_exists('at_pt_render_pestana') && current_user_can('manage_options')): ?>
                        <button class="ficha-tab" data-target="tab-plan">🗓️ Plan de trabajo</button>
                        <?php endif; ?>
                        <?php endif; ?>
```

- [ ] **Step 22: Panel de la pestaña en `crm-ai-completo.php` (líneas 2240-2241)**

old_string:
```php
                    </div><!-- /tab-operacion -->
                    <?php endif; ?>
```
new_string:
```php
                    </div><!-- /tab-operacion -->
                    <?php if (is_array($cliente) && function_exists('at_pt_render_pestana') && current_user_can('manage_options')): ?>
                    <!-- Tab: Plan de trabajo (inc/plan-trabajo/panel.php) -->
                    <div class="ficha-tab-content" id="tab-plan">
                    <div class="ficha-card">
                        <?php at_pt_render_pestana($cliente); ?>
                    </div>
                    </div><!-- /tab-plan -->
                    <?php endif; ?>
                    <?php endif; ?>
```

- [ ] **Step 23: Correr la prueba, las del cierre que dibujan la ficha, y ver que pasan**

Las del cierre se corren con `sin-red.php` (Task 6), que no deja salir ninguna llamada HTTP:
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" -l wp-content/mu-plugins/crm-ai-completo.php
"$PHP" tests/plan/ficha-crm-wp-test.php; echo "exit=$?"
for t in timeline-ficha-enlazada revision-final; do printf 'cierre %-24s ' $t; "$PHP" "$SCR/plan-trabajo/sin-red.php" tests/cierre/$t-wp-test.php 2>/dev/null | tail -1; done
```
Expected: `No syntax errors detected in wp-content/mu-plugins/crm-ai-completo.php`; 7 líneas `ok …`, `TODO OK`, `exit=0`;
y `TODO OK` en las dos pruebas del cierre (la primera dibuja la ficha del CRM completa).

- [ ] **Step 24: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add wp-content/mu-plugins/crm-ai-completo.php tests/plan/ficha-crm-wp-test.php
git commit -m "$(cat <<'EOF'
feat(plan): la ficha del cliente del CRM muestra la pestaña del plan de trabajo

Botón y panel después de «Contratos y operación», solo con manage_options y
solo si el cliente existe (con un id que no existe la ficha se dibuja como antes).

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 25: Verificación en el navegador (sitio local, 390 px y escritorio)**

Requisitos del sitio `wp-local-plan` (D14): **los deja la Task 0** y aquí no se repiten. (a) `wp-admin` es una
**copia** y no una unión (Task 0, Step 3: con la unión, PHP carga el `wp-load.php` de la carpeta de destino, otro
WordPress, y la ficha sale sin la pestaña); (b) el router reemplaza `auth_redirect()` cuando viene la cookie de prueba
`at_admin` (Task 0, Step 4: sin eso `/wp-admin/` responde 302 a `wp-login.php?…&reauth=1`). Solo se comprueba, sin
cambiar nada:
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
test -L "$SCR/wp-local-plan/wp-admin" && echo "wp-admin es union: volver a la Task 0" || echo "wp-admin copia"
grep -c "function auth_redirect() {}" "$SCR/wp-local-plan/router.php"
```
Expected: `wp-admin copia` y `1`. Si no, volver a la Task 0 (Steps 3 y 4) antes de seguir.

(c) Datos de prueba: crear `$SCR/plan-trabajo/verificar-panel.php` (cliente sin correo ni propuesta y un plan largo de
plataforma a medida, el caso 6 del Review Focus: 22 actividades con las 2 del arranque, 16 barras y 14 semanas, del
5-oct-2026 al 6-ene-2027). Define `PTC_CONSERVAR` para que los datos no se borren solos al terminar (los borra
`limpiar`):
```php
<?php
// Datos [PRUEBA] para mirar la pestaña del plan en el navegador (Tasks 9 y 10). Solo scratchpad, nunca al repo.
// Uso: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php verificar-panel.php crear|limpiar
$_SERVER['HTTP_HOST'] = 'localhost:8093';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
define('WP_USE_THEMES', false);
define('PTC_CONSERVAR', true); // fixtures-panel.php no registra ptc_limpiar() al cerrar: los datos quedan para mirarlos
require getenv('AT_WP_LOAD');
require 'C:/wamp64/www/automatiza-tech/.worktrees/plan-trabajo/tests/plan/fixtures-panel.php';
$estado = __DIR__ . '/verificar-panel.json';
if (($argv[1] ?? '') === 'limpiar') {
	$GLOBALS['ptc_creado'] = json_decode((string) file_get_contents($estado), true);
	ptc_limpiar();
	@unlink($estado);
	echo "limpio\n";
	exit;
}
$m = ptc_marca();
$c = ptc_cliente($m, ''); // sin correo: sin portal (Review Focus 4)
$k = ptc_contrato($c['tech'], $m, null); // sin propuesta
$f = ptc_plan($k);
ptc_sembrar((int) $f->id);
file_put_contents($estado, json_encode($GLOBALS['ptc_creado'])); // antes de lo que puede fallar: «limpiar» lo encuentra
// Plan largo (plataforma a medida, Review Focus 6): 7 módulos más, con revisión en los pares y construcción en paralelo.
$pl = at_pt_payload(at_pt_plan((int) $f->id));
for ($i = 1; $i <= 7; $i++) {
	$pl['fases'][0]['bloques'][] = ['nombre' => "Módulo {$i} de la plataforma", 'entrega' => $i % 2 === 0, 'entregable' => "Módulo {$i} funcionando", 'actividades' => [
		['nombre' => "Diseño del módulo {$i}", 'detalle' => '', 'responsable' => 'at', 'dias_habiles' => 2, 'servicio' => 'plataforma', 'etapa' => 'diseno', 'origen' => 'ia', 'en_paralelo' => false],
		['nombre' => "Construcción del módulo {$i} con integración a los sistemas del cliente", 'detalle' => '', 'responsable' => 'ambos', 'dias_habiles' => 3, 'servicio' => 'plataforma', 'etapa' => 'desarrollo', 'origen' => 'tabla', 'en_paralelo' => true],
	]];
}
// Orden único de D2 (panel): validar -> marcar ediciones con 'luis' -> validar otra vez -> fechas.
$v = at_pt_validar_plan($pl);
if (!empty($v['ok'])) {
	$v = at_pt_validar_plan(at_pt_marcar_ediciones($pl, $v['plan'], 'luis'));
}
if (empty($v['ok'])) {
	fwrite(STDERR, "El plan largo no valida:\n" . implode("\n", (array) $v['errores']) . "\n");
	exit(1);
}
$plan = at_pt_calcular_fechas($v['plan'], '2026-10-05', at_pt_feriados());
at_pt_guardar((int) $f->id, ['payload' => $plan, 'view_url' => 'http://localhost:5202/p/' . $f->codigo . '/index.html']);
echo json_encode(['crm' => $c['crm'], 'plan' => (int) $f->id, 'barras' => count($plan['cronograma']['barras'] ?? []), 'semanas' => (int) ($plan['cronograma']['semanas'] ?? 0)]), "\n";
```
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
cd "$SCR/plan-trabajo" && "$PHP" verificar-panel.php crear
```
Expected: `{"crm":<CRM>,"plan":<PLAN>,"barras":16,"semanas":14}`.

Levantar `wp-local-plan` con `preview_start` (entrada de la Task 0) y abrir
`http://localhost:8093/wp-admin/admin.php?page=automatiza-crm-ficha&id=<CRM>&pt=<PLAN>&pt_msg=guardado&at_admin=1#tab-plan`.

Escritorio (`resize_window` 1366×900 y después 1920×1080), mirar exactamente:
- Las cinco pestañas se ven enteras: a 1366 y a 1920 (columna de 565 y 690 px) «🗓️ Plan de trabajo» baja a una
  segunda fila en vez de cortarse. La pestaña del
  plan queda abierta sola, el aviso «Guardado y recalculado…» sale una vez y `pt_msg` desaparece de la barra de
  direcciones.
- Cajas «Estado» (Borrador), «Fechas estimadas» (inicio 5 oct 2026, entrega estimada, 14 semanas) y «Documento» (Ver
  vista previa); avisos «no tiene correo… sin enlace al portal» y «no tiene propuesta… fotos nuevas»; al final de los
  textos, «Meses de garantía» de solo lectura con la leyenda «Viene del contrato» y «Servicios mensuales».
- En cada fase: bloque «Arranque» primero; cada actividad con nombre, ✕, responsable, días, «En paralelo», origen
  («Tabla de tiempos», «IA · revisar» o «Editado por Luis»), fechas y su detalle editable debajo; cada bloque con entregable, «Revisión del cliente después», sus fechas y
  «Quitar bloque»; botones «+ Actividad» y «+ Bloque».
- En la consola: `atPlanTrabajo.serializar(document.querySelector('.at-pt-form-plan'))` devuelve 3 fases sin `desde` ni
  `hasta`. Cambiar los días de una actividad, apretar «Pedir cambios»: sale el aviso «Tienes cambios sin guardar en la
  tabla…» y no se envía nada.
- Poner de fecha de inicio un sábado y «Guardar y recalcular»: vuelve a la pestaña con «Guardado y recalculado…» (o
  «…no se pudo pedir la vista previa a n8n…» si el simulador de n8n no está arriba), la fecha pasa al lunes hábil y la
  actividad editada dice «Editado por Luis».
- «Aprobar»: el botón dice «(10 fotos ≈ US$0,0320)» y el `confirm` «Se generarán 10 fotos nuevas (≈ US$0,0320 de lista;
  hasta US$0,0640 si hay que rehacerlas) y la versión final del plan. ¿Aprobar?». Cancelar: no se envía.

Celular (`resize_window` 390×844, recargar):
```js
(() => { const p = document.getElementById('tab-plan').getBoundingClientRect();
  return { ancho: document.documentElement.scrollWidth, plan: Math.round(p.right),
           seSalen: [...document.querySelectorAll('#tab-plan *')].filter(e => e.getBoundingClientRect().right > p.right + 1).length }; })()
```
Expected: `plan` ≤ 390 y `seSalen: 0`; `ancho` cerca de 417 (en el sitio de prueba del 29-sep: 417; los 27 px que
sobran son el botón «📅 Agendar Seguimiento» del historial del CRM, que ya se sale hoy; sin la regla `:has` de
`plan-trabajo.css` eran 691, y con las 4 pestañas de hoy, 543). La barra de pestañas se
desplaza con el dedo; bloques y actividades en una columna; botones a todo el ancho; nada del plan cortado.

Guardar capturas (escritorio: cabecera y tabla; celular: cabecera, un bloque y los botones) en
`$SCR/plan-trabajo/evidencia/panel-*.png`. Al terminar: `resize_window` preset `desktop` y
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
cd "$SCR/plan-trabajo" && "$PHP" verificar-panel.php limpiar
```
Expected: `limpio`. (Sin commit: nada de esto va al repo.)

### Task 10: Agendar llamada de seguimiento desde el plan

«Agendar llamada de seguimiento» (decisión 9) abre el formulario que ya existe en «Reuniones de seguimiento»
(`admin.php?page=automatiza-followup&pt_plan=<id>`) con los datos del cliente precargados y el tipo fijo en el asunto
(«Seguimiento del plan de trabajo — <proyecto>»; la tabla `wp_automatiza_followup_meetings` no tiene columna de tipo).
Solo precarga: no pasa a modo edición, no crea `meeting_id` ni ninguna fila, y tras un POST no vuelve a precargar (el
formulario se envía a su misma URL, que conserva `pt_plan`; así no se agenda dos veces). El envío sigue el flujo de
siempre (Calendar con Meet, correo y WhatsApp según las casillas que deje Luis).

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php` (agregar al final `at_pt_datos_agenda()`)
- Modify: `wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php:609-614` (precarga), `:642` (aviso) y los
  valores de `:743`, `:751`, `:773`, `:781`, `:835`, `:843`
- Test: `tests/plan/agenda-wp-test.php`

**Interfaces:**
- Consumes: `at_pt_plan(int $id): ?object`, `at_pt_payload(object $fila): array`, `at_pt_tabla(): string` (Task 5);
  `at_pt_crm_de_plan(object $fila): int` y las fixtures `ptc_*` (Task 9);
  `pt_admin_id()` (Task 8); `automatiza_tech_followup_page()` (`admin-followup-meetings.php:355`); columnas
  `wp_crm_clientes.nombre|email|empresa|telefono` y los marcadores del contrato `representante_cliente_nombre`,
  `razon_social_cliente`, `email_cliente`, `telefono_cliente`, `nombre_proyecto`.
- Produces: `at_pt_datos_agenda(int $plan_id): array` → `['client_name', 'client_email', 'company_name', 'phone',
  'meeting_subject', 'notes']` (en ese orden; `[]` si el plan no existe; `client_email` `''` si no hay uno válido;
  largos recortados a 100/150/30/255 como las columnas de la tabla); en `admin-followup-meetings.php`, las variables
  `$pt_plan_pedido`, `$pt_precargar` y `$pt_precarga`.

- [ ] **Step 1: Escribir la prueba que falla**

Crear `tests/plan/agenda-wp-test.php`:
```php
<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/agenda-wp-test.php
// Task 10: «Agendar llamada de seguimiento» (decisión 9). at_pt_datos_agenda() y la precarga por GET
// (?pt_plan=<id>) del formulario de inc/admin-followup-meetings.php. Abrir el formulario nunca crea una reunión.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';

if (!function_exists('at_pt_datos_agenda') || !function_exists('automatiza_tech_followup_page')) {
	fwrite(STDERR, "Falta at_pt_datos_agenda (panel.php) o el módulo de reuniones de seguimiento.\n");
	exit(2);
}
global $wpdb;
wp_set_current_user(pt_admin_id());
$m = ptc_marca();
$tabla_reuniones = $wpdb->prefix . 'automatiza_followup_meetings';

/** HTML de «Reuniones de seguimiento» con ese GET (y ese POST, si viene). */
function seguimiento(array $get, array $post = []): string {
	$_GET = $get + ['page' => 'automatiza-followup'];
	$_POST = $post;
	$_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
	ob_start();
	automatiza_tech_followup_page();
	$h = (string) ob_get_clean();
	$_GET = [];
	$_POST = [];
	$_SERVER['REQUEST_METHOD'] = 'GET';
	return $h;
}

/** Valor del atributo value del input con ese id ('' si no tiene; null si no está). */
function valor_input(string $h, string $id): ?string {
	if (!preg_match('/<input[^>]*id="' . preg_quote($id, '/') . '"[^>]*value="([^"]*)"/s', $h, $mm)) {
		return null;
	}
	return html_entity_decode($mm[1], ENT_QUOTES, 'UTF-8');
}

function valor_notas(string $h): ?string {
	if (!preg_match('/<textarea name="notes" id="notes"[^>]*>(.*?)<\/textarea>/s', $h, $mm)) {
		return null;
	}
	return html_entity_decode($mm[1], ENT_QUOTES, 'UTF-8');
}

// ---------- at_pt_datos_agenda ----------
$correo = "prueba-plan-{$m}@example.com";
$c = ptc_cliente($m, $correo);
$k = ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', $correo);
$f = ptc_plan($k);
ptc_sembrar((int) $f->id);
$f = at_pt_plan((int) $f->id);
$d = at_pt_datos_agenda((int) $f->id);
ok(array_keys($d) === ['client_name', 'client_email', 'company_name', 'phone', 'meeting_subject', 'notes'], 'devuelve las seis claves del formulario, en orden');
ok($d['client_name'] === '[PRUEBA] Cliente Plan ' . $m && $d['client_email'] === $correo && $d['company_name'] === '[PRUEBA] Empresa ' . $m && $d['phone'] === '+56 9 1111 1111', 'nombre, correo, empresa y teléfono salen de la ficha del CRM');
ok($d['meeting_subject'] === 'Seguimiento del plan de trabajo — [PRUEBA] Sitio del panel', 'asunto fijo «Seguimiento del plan de trabajo — <proyecto>»: ' . $d['meeting_subject']);
ok(strpos($d['notes'], (string) $f->codigo) !== false && strpos($d['notes'], 'PRUEBA-PT-' . $m) !== false, 'las notas internas llevan el código del plan y el número del contrato');
ok(at_pt_datos_agenda(0) === [] && at_pt_datos_agenda(999999999) === [], 'un plan que no existe devuelve []');
// Plan creado cuando la ficha operativa todavía no estaba enlazada al CRM (crm_cliente_id vacío en el plan): los
// datos salen igual del cliente del CRM enlazado hoy (at_pt_crm_de_plan, Task 9), no solo de los marcadores.
$wpdb->update(at_pt_tabla(), ['crm_cliente_id' => null], ['id' => (int) $f->id]);
ok((at_pt_datos_agenda((int) $f->id)['client_name'] ?? '') === $d['client_name'], 'plan guardado sin cliente del CRM: los datos salen del cliente enlazado hoy a su ficha');
$wpdb->update(at_pt_tabla(), ['crm_cliente_id' => $c['crm']], ['id' => (int) $f->id]);

// Sin correo en el CRM ni en el contrato, sin teléfono en el CRM y todavía sin payload (Review Focus 4).
$c2 = ptc_cliente($m . 'n', '');
$wpdb->update($wpdb->prefix . 'crm_clientes', ['telefono' => ''], ['id' => $c2['crm']]);
$k2 = ptc_contrato($c2['tech'], $m . 'n', null);
$f2 = ptc_plan($k2);
$d2 = at_pt_datos_agenda((int) $f2->id);
ok($d2['client_email'] === '', 'sin correo: el campo queda vacío para que Luis lo escriba');
ok($d2['phone'] === '+56 9 2222 2222', 'sin teléfono en el CRM: se usa el del contrato');
ok($d2['meeting_subject'] === 'Seguimiento del plan de trabajo — [PRUEBA] Sitio ' . $m . 'n', 'sin payload: el asunto usa el nombre del proyecto del contrato');

// ---------- La página de seguimiento con ?pt_plan ----------
$antes = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_reuniones}");
$h = seguimiento(['pt_plan' => (string) $f->id]);
ok(valor_input($h, 'client_name') === $d['client_name'] && valor_input($h, 'client_email') === $d['client_email'], 'precarga nombre y correo');
ok(valor_input($h, 'company_name') === $d['company_name'] && valor_input($h, 'phone') === $d['phone'], 'precarga empresa y teléfono');
ok(valor_input($h, 'meeting_subject') === $d['meeting_subject'], 'precarga el asunto fijo del seguimiento del plan');
ok(valor_notas($h) === $d['notes'], 'precarga las notas internas con el código del plan');
ok(valor_input($h, 'meeting_date') === '' && strpos($h, 'Datos precargados desde el plan de trabajo') !== false, 'la fecha la elige Luis y la página avisa de dónde vienen los datos');
ok(strpos($h, 'Programar Nueva Reunión') !== false && strpos($h, 'Editar Reunión #') === false, 'es una reunión nueva: no pasa a modo edición');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_reuniones}") === $antes, 'abrir el formulario no crea ninguna reunión');

$h = seguimiento(['pt_plan' => (string) $f2->id]);
ok(valor_input($h, 'client_email') === '' && strpos($h, 'no tiene correo') !== false, 'cliente sin correo: el campo queda vacío y la página lo avisa');
$h = seguimiento(['pt_plan' => '999999999']);
ok(strpos($h, 'No se encontró ese plan de trabajo') !== false && valor_input($h, 'client_name') === '', 'plan inexistente: formulario vacío con aviso');
$h = seguimiento([]);
ok(valor_input($h, 'meeting_subject') === 'Reunión de Seguimiento - AutomatizaTech' && strpos($h, 'Datos precargados') === false, 'sin pt_plan: el formulario de siempre');

// edit_id manda sobre pt_plan.
$wpdb->insert($tabla_reuniones, ['client_name' => '[PRUEBA] Reunión existente ' . $m, 'client_email' => $correo, 'meeting_date' => '2026-12-01', 'meeting_time' => '10:00:00', 'status' => 'scheduled']);
$reunion = (int) $wpdb->insert_id;
$GLOBALS['ptc_creado']['reuniones'][] = $reunion;
$h = seguimiento(['edit_id' => (string) $reunion, 'pt_plan' => (string) $f->id]);
ok(valor_input($h, 'client_name') === '[PRUEBA] Reunión existente ' . $m && strpos($h, 'Datos precargados') === false, 'con edit_id se edita esa reunión y no se precarga el plan');

// Tras un POST (formulario devuelto con error) no se vuelve a precargar: evita agendar dos veces.
$antes = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_reuniones}");
$h = seguimiento(['pt_plan' => (string) $f->id], [
	'followup_nonce' => wp_create_nonce('save_followup_meeting'), 'client_name' => '', 'client_email' => '', 'invitees_emails' => '',
	'company_name' => '', 'phone' => '', 'meeting_date' => '', 'meeting_time' => '', 'meet_link' => '', 'meeting_subject' => '', 'notes' => '',
]);
ok(strpos($h, 'completa todos los campos obligatorios') !== false, 'el POST sí se procesó (con su error de campos obligatorios)');
ok(valor_input($h, 'client_name') === '' && strpos($h, 'Datos precargados') === false, 'tras un POST el formulario no se precarga');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_reuniones}") === $antes, 'el POST con error no creó reuniones');

ptc_limpiar();
fin();
```

- [ ] **Step 2: Correrla y ver que falla**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/agenda-wp-test.php; echo "exit=$?"
```
Expected (falla porque `at_pt_datos_agenda()` no existe):
```
Falta at_pt_datos_agenda (panel.php) o el módulo de reuniones de seguimiento.
exit=2
```

- [ ] **Step 3: Implementar `at_pt_datos_agenda()`**

Agregar al final de `panel.php`:
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
cat >> wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php <<'PANEL_AGENDA'

// ---------------------------------------------------------------------------------------------------------------
// «Agendar llamada de seguimiento» (decisión 9): datos para precargar inc/admin-followup-meetings.php (?pt_plan=).
// ---------------------------------------------------------------------------------------------------------------

/**
 * Datos del cliente de un plan para el formulario de «Reuniones de seguimiento»: la ficha del CRM manda y, si le
 * falta algo, lo completan los marcadores del contrato. El tipo es fijo («Seguimiento del plan de trabajo», en el
 * asunto). [] si el plan no existe. Largos recortados a las columnas de wp_automatiza_followup_meetings.
 */
function at_pt_datos_agenda(int $plan_id): array {
	$fila = $plan_id > 0 ? at_pt_plan($plan_id) : null;
	if (!$fila) {
		return [];
	}
	global $wpdb;
	$crm = null;
	$crm_id = at_pt_crm_de_plan($fila); // el enlace actual de la ficha operativa, o el guardado al crear el plan
	if ($crm_id > 0) {
		$crm = $wpdb->get_row($wpdb->prepare("SELECT nombre, email, empresa, telefono FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id));
	}
	$contrato = $wpdb->get_row($wpdb->prepare("SELECT contract_number, placeholders FROM {$wpdb->prefix}automatiza_contracts WHERE id = %d", (int) $fila->contrato_id));
	$ph = $contrato ? json_decode((string) $contrato->placeholders, true) : [];
	$ph = is_array($ph) ? $ph : [];
	$primero = function (...$valores): string {
		foreach ($valores as $v) {
			$v = trim(sanitize_text_field((string) $v));
			if ($v !== '') {
				return $v;
			}
		}
		return '';
	};
	$correo = $primero($crm->email ?? '', $ph['email_cliente'] ?? '');
	$proyecto = $primero(at_pt_payload($fila)['proyecto'] ?? '', $ph['nombre_proyecto'] ?? '');
	$numero = $contrato ? trim((string) $contrato->contract_number) : '';
	return [
		'client_name'     => mb_substr($primero($crm->nombre ?? '', $ph['representante_cliente_nombre'] ?? '', $ph['razon_social_cliente'] ?? ''), 0, 100),
		'client_email'    => is_email($correo) ? $correo : '',
		'company_name'    => mb_substr($primero($crm->empresa ?? '', $ph['razon_social_cliente'] ?? ''), 0, 150),
		'phone'           => mb_substr($primero($crm->telefono ?? '', $ph['telefono_cliente'] ?? ''), 0, 30),
		'meeting_subject' => mb_substr('Seguimiento del plan de trabajo' . ($proyecto !== '' ? ' — ' . $proyecto : ''), 0, 255),
		'notes'           => 'Plan de trabajo ' . $fila->codigo . ($numero !== '' ? ' (contrato ' . $numero . ')' : '') . '. Llamada para revisar el plan con el cliente y aclarar sus dudas.',
	];
}
PANEL_AGENDA
"$PHP" -l wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php
```
Expected: `No syntax errors detected in wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php`.

- [ ] **Step 4: Correr la prueba: los datos pasan, la página todavía no precarga**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" tests/plan/agenda-wp-test.php | grep -v "^ok "; echo "exit=${PIPESTATUS[0]}"
```
Expected (fallan solo los casos de la página, porque `admin-followup-meetings.php` todavía no lee `pt_plan`):
```
FALLA precarga nombre y correo
FALLA precarga empresa y teléfono
FALLA precarga el asunto fijo del seguimiento del plan
FALLA precarga las notas internas con el código del plan
FALLA la fecha la elige Luis y la página avisa de dónde vienen los datos
FALLA cliente sin correo: el campo queda vacío y la página lo avisa
FALLA plan inexistente: formulario vacío con aviso

7 FALLAS
exit=1
```

- [ ] **Step 5: Leer `pt_plan` en `admin-followup-meetings.php` (después de la línea 614)**

El archivo está en CRLF en el árbol de trabajo (3765 de 3765 líneas): usar la herramienta Edit.
old_string:
```php
        $edit_meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
    }
```
new_string:
```php
        $edit_meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
    }

    // --- PRECARGA DESDE EL PLAN DE TRABAJO (?pt_plan=<id>, inc/plan-trabajo/panel.php) ---
    // Solo al abrir el formulario (GET) y sin edit_id: rellena una reunión NUEVA con los datos del cliente del plan.
    // No crea nada ni pasa a modo edición; tras un POST no se repite (evita agendar dos veces).
    $pt_plan_pedido = isset($_GET['pt_plan']) ? absint($_GET['pt_plan']) : 0;
    $pt_precargar = !$edit_meeting && $pt_plan_pedido > 0 && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST';
    $pt_precarga = ($pt_precargar && function_exists('at_pt_datos_agenda')) ? at_pt_datos_agenda($pt_plan_pedido) : array();
```

- [ ] **Step 6: Aviso de la precarga (línea 642)**

old_string:
```php
        <?php echo $message; ?>
```
new_string:
```php
        <?php echo $message; ?>
        <?php if ($pt_precarga): ?>
            <div class="notice notice-info inline"><p>📋 Datos precargados desde el plan de trabajo. Elige fecha y hora; el evento en Google Calendar, el correo y el WhatsApp salen solo con sus casillas marcadas.</p>
            <?php if ($pt_precarga['client_email'] === ''): ?><p><strong>Este cliente no tiene correo en su ficha:</strong> escríbelo antes de programar la reunión.</p><?php endif; ?></div>
        <?php elseif ($pt_precargar): ?>
            <div class="notice notice-warning inline"><p>No se encontró ese plan de trabajo: el formulario queda vacío.</p></div>
        <?php endif; ?>
```

- [ ] **Step 7: Valores del formulario (líneas 743, 751, 773, 781, 835 y 843)**

Seis ediciones; cada old_string aparece una sola vez en el archivo.
1. old_string `value="<?php echo esc_attr($edit_meeting->client_name ?? ''); ?>"`
   → new_string `value="<?php echo esc_attr($edit_meeting->client_name ?? ($pt_precarga['client_name'] ?? '')); ?>"`
2. old_string `value="<?php echo esc_attr($edit_meeting->client_email ?? ''); ?>"`
   → new_string `value="<?php echo esc_attr($edit_meeting->client_email ?? ($pt_precarga['client_email'] ?? '')); ?>"`
3. old_string `value="<?php echo esc_attr($edit_meeting->company_name ?? ''); ?>"`
   → new_string `value="<?php echo esc_attr($edit_meeting->company_name ?? ($pt_precarga['company_name'] ?? '')); ?>"`
4. old_string `value="<?php echo esc_attr($edit_meeting->phone ?? ''); ?>"`
   → new_string `value="<?php echo esc_attr($edit_meeting->phone ?? ($pt_precarga['phone'] ?? '')); ?>"`
5. old_string `value="<?php echo esc_attr($edit_meeting->meeting_subject ?? 'Reunión de Seguimiento - AutomatizaTech'); ?>"`
   → new_string `value="<?php echo esc_attr($edit_meeting->meeting_subject ?? ($pt_precarga['meeting_subject'] ?? 'Reunión de Seguimiento - AutomatizaTech')); ?>"`
6. old_string `<?php echo esc_textarea($edit_meeting->notes ?? ''); ?></textarea>`
   → new_string `<?php echo esc_textarea($edit_meeting->notes ?? ($pt_precarga['notes'] ?? '')); ?></textarea>`

- [ ] **Step 8: Correr la prueba y ver que pasa**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
"$PHP" -l wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php
"$PHP" tests/plan/agenda-wp-test.php; echo "exit=$?"
```
Expected: `No syntax errors detected in …admin-followup-meetings.php`; 23 líneas `ok …` (entre ellas `ok   abrir el
formulario no crea ninguna reunión`, `ok   con edit_id se edita esa reunión y no se precarga el plan` y `ok   tras un
POST el formulario no se precarga`), `TODO OK`, `exit=0`.

- [ ] **Step 9: Suite del plan completa y regresión del cierre**

Todas las pruebas del plan (las puras de las Tasks 1 a 4 no usan `AT_WP_LOAD`; las de WordPress, sí) y las dos del
cierre que dibujan la ficha del CRM, estas con `sin-red.php` (Task 6):
```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo; PHP=/c/wamp64/bin/php/php8.4.15/php.exe; SCR=/c/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"
for t in tests/plan/*-test.php; do printf '%-44s ' "$t"; "$PHP" "$t" 2>&1 | tail -1; done
for t in ajustes panel-render panel-js panel-acciones ficha-crm agenda; do printf '%-16s ' $t; "$PHP" tests/plan/$t-wp-test.php | grep -c '^ok '; done
for t in timeline-ficha-enlazada revision-final; do printf 'cierre %-24s ' $t; "$PHP" "$SCR/plan-trabajo/sin-red.php" tests/cierre/$t-wp-test.php 2>/dev/null | tail -1; done
```
Expected: `TODO OK` al final de cada línea del primer y del tercer ciclo; en el segundo, las pruebas de las Tasks 8 a 10
cuentan 21, 33, 15, 49, 7 y 23 `ok` (148 en total, 0 `FALLA`).

- [ ] **Step 10: Verificación en el navegador de la precarga**

Con los requisitos y los datos del Step 25 de la Task 9 (`verificar-panel.php crear`), en la pestaña del plan apretar
«📅 Agendar llamada de seguimiento». Debe abrir `admin.php?page=automatiza-followup&pt_plan=<PLAN>` con: aviso azul
«📋 Datos precargados desde el plan de trabajo…» y la línea «Este cliente no tiene correo en su ficha…»; Nombre
`[PRUEBA] Cliente Plan …`, Email vacío, Empresa `[PRUEBA] Empresa …`, Teléfono `+56 9 1111 1111`, Asunto
`Seguimiento del plan de trabajo — [PRUEBA] Sitio del panel`, Notas internas con el código del plan y el contrato,
Fecha y Hora vacías, título «➕ Programar Nueva Reunión» y las tres casillas marcadas como siempre. **No enviar el
formulario** (crearía el evento y mandaría el correo y el WhatsApp). A 390 px esta página ya mide 730 px de ancho por su
propio diseño (formulario de 400 px mínimo y lista de 500 px): es anterior a este cambio y queda fuera de alcance.
Al terminar: `verificar-panel.php limpiar` → `limpio`.

- [ ] **Step 11: Commit**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
git add wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php tests/plan/agenda-wp-test.php
git commit -m "$(cat <<'EOF'
feat(plan): agendar la llamada de seguimiento del plan con los datos precargados

at_pt_datos_agenda() arma nombre, correo, empresa, teléfono, asunto fijo y
notas desde la ficha del CRM y el contrato; Reuniones de seguimiento los
precarga con ?pt_plan (solo GET, sin edit_id, sin crear nada).

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
EOF
)"
```


### Task 11: Renderer — exportaciones de `template.js`, `validatePlanPayload` y rama `document_type: 'plan'` en `/render`

Rama `claude/plan-renderer` (sale de `origin/main`, que tiene el renderer vigente en Easypanel: commit `86021c1`,
«Merge pull request #57 … deck-avisa-lamina»). Worktree `C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer`.
**Nunca** tocar `renderer/` en `claude/plan-de-trabajo`. Todas las rutas de esta tarea son relativas a la raíz del
worktree. Datos de prueba inventados («Cliente Prueba», códigos `PlanPrueba01`/`PlanPrueba02`): el repositorio es público.

**Files:**
- Modify: `renderer/src/template.js` — solo se AGREGA un bloque al final del archivo (después del `module.exports = { … };` de las líneas 690-698). Nada más cambia.
- Modify: `renderer/src/schema.js:39` — se inserta `validatePlanPayload` antes de la línea 39 y se reemplaza esa línea (`module.exports`).
- Modify: `renderer/src/server.js:4`, `:6`, `:41`, `:144` — cuatro reemplazos de una línea.
- Create: `renderer/src/template-plan.js` — primera versión: documento con la portada y los marcadores de sección que completa la Task 12.
- Create: `renderer/test/fixtures/plan-ejemplo.js` — cuerpos de ejemplo `planCorto()` (6 semanas, 7 barras) y `planLargo()` (14 semanas, 16 barras).
- Test: `renderer/test/template-propuesta-igual.test.js`, `renderer/test/schema-plan.test.js`, `renderer/test/server-plan.test.js` (5 pruebas, dos de ellas de fotos con y sin propuesta).

**Interfaces:**
- Consumes: el cuerpo que arma `at_pt_armar_render(array $plan, array $datos, bool $final): array` (Task 4) y manda n8n
  «Plan de trabajo · 3 Render» (Task 14): `{document_type: 'plan', unique_id, draft, company_name, client_name, proyecto,
  fecha_firma_larga, fecha_inicio, fecha_fin, semanas, metodo, fases, cronograma, necesitamos_de_ti, reuniones, soporte,
  portal_url, agenda: {whatsapp_url, web_url}, image_briefs, images}`. `soporte.garantia_meses` es la del contrato firmado
  (decisión D8) y `company_name` el nombre que decide la decisión D9; el renderer los muestra tal como llegan, sin
  valores propios. Lo pide n8n con el cuerpo que devuelve `GET /plan/{id}/render` (`{ok, render, propuesta_uid,
  crm_cliente_id, estado}`, decisión D10): solo `render` va al renderer. De `origin/main`: `escapeHtml`, `linkify`
  (`renderer/src/escape.js`), `renderToFiles(html, outputDir)` (`renderer/src/render.js`), `createApp({publicDir, baseUrl, higgsfieldCredentials, renderKey})`.
- Produces:
  - `renderer/src/template.js` exporta además `STYLE: string`, `SCRIPT: string`, `LOGO_URL: string`,
    `CONTACTS: {label, href, icon}[]`, `CONTACT_ICONS: {mail, web, whatsapp, instagram}` (SVG en línea),
    `backgroundStyle(imageUrl: string, index: number, variant = 'content'): string`, `logoMark(): string`,
    `renderDeckControls(): string`. El HTML de `renderProposalHtml` no cambia (huellas sha256 fijas en la prueba).
  - `renderer/src/schema.js`: `validatePlanPayload(body): {valid: boolean, errors: string[]}` (mensajes en español;
    incluye la forma de `unique_id`, `/^[A-Za-z0-9_-]{6,64}$/`, para que también ese rechazo traiga motivo) y
    `PLAN_REQUIRED_STRING_FIELDS = ['unique_id', 'company_name', 'proyecto']`.
  - `POST /render`: si `req.body.document_type === 'plan'` valida con `validatePlanPayload` y dibuja con `renderPlanHtml`;
    lo demás es igual que para una propuesta. Respuestas que lee n8n (Task 14):
    - 200 `{view_url: '<BASE_URL>/p/<unique_id>/index.html', pdf_url: '…/presentation.pdf', images: {requested: number,
      stored_local: number, kept_remote: string[], missing: string[], reused: number}}`. `requested` cuenta solo los
      `image_briefs`: portada y cierre que llegan en `images` (fotos de la propuesta) no suman, así que calza con
      `at_pt_costo_fotos`. `missing` y `kept_remote` son claves de lámina (`metodo`, `fase_2`…).
    - 400 `{error: 'invalid payload', details: string[]}`: rechazo del esquema, **siempre con `details`** en un plan.
      Reintentarlo da lo mismo: n8n no lo reintenta y copia `details` a la nota del plan en «error» (decisión D11).
    - 401 `{error: 'unauthorized'}`: falta o no calza `X-AT-Render-Key` (credencial de n8n). Tampoco se arregla
      reintentando.
    - 502 `{error: 'render failed', details: string}` (aquí `details` es un **texto**, el mensaje del error de fotos o
      de Chromium): falla pasajera, se reintenta dentro del tope de 3 renders.
    Sin `document_type`, todo sigue como hoy (propuesta).
  - `renderer/src/template-plan.js`: `renderPlanHtml(data, images = {}): string` (en esta tarea, la portada) y los
    marcadores de sección `// === Fechas ===`, `// === Ayudas ===`, `// === Carta Gantt ===`, `// === Láminas ===`,
    `// === Documento ===` que usa la Task 12. Constante `ESTILO_PORTADA` y función `renderPlanCover(data, imageUrl)`.
  - `renderer/test/fixtures/plan-ejemplo.js`: `planCorto(): object`, `planLargo(): object` (copias nuevas en cada llamada).

- [ ] **Step 1: Preparar el worktree y comprobar la línea base (95 pruebas)**

Si la Task 0 ya creó el worktree, el `worktree add` se salta solo. Después, correr la batería vigente:

```bash
cd C:/wamp64/www/automatiza-tech && git fetch origin && (test -d .worktrees/plan-renderer || git worktree add .worktrees/plan-renderer -b claude/plan-renderer origin/main)
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && npm install --no-package-lock
```

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/*.test.js
```

Salida esperada (el final; las pruebas del servidor abren Chromium, tarda ~40 s):

```text
ℹ tests 95
ℹ pass 95
ℹ fail 0
```

No debe aparecer `package-lock.json` en `git status` (se instala con `--no-package-lock`).

- [ ] **Step 2: Escribir la prueba de que la propuesta no cambia y de las exportaciones nuevas**

Crear `renderer/test/template-propuesta-igual.test.js`. Las dos huellas se sacaron del `template.js` de `origin/main`
(`86021c1`) sin tocar. Antes de seguir, comprobar que `template.js` no cambió desde ese commit:

```bash
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer diff --quiet 86021c1 HEAD -- renderer/src/template.js && echo IGUAL || echo CAMBIO
```

Si dice `CAMBIO`, correr el Step 3 antes de tocar `template.js`: las dos pruebas de huella fallan y `assert.equal`
muestra la huella del archivo actual en la línea `actual:`. Copiar cada una en su prueba y volver a correr el Step 3
(deben quedar dos en verde y una en rojo) antes del Step 4.

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const template = require('../src/template');

// Guardia del plan de trabajo: template.js solo AGREGA exportaciones para template-plan.js, así que el
// HTML de una propuesta tiene que salir byte a byte igual que antes. Las huellas se sacaron del
// template.js de origin/main (commit 86021c1) antes de tocarlo. Se quitan los \r por si el archivo se
// sacó con CRLF (core.autocrlf en Windows); los template literals de JS ya los normalizan a \n.
const BASE = {
  company_name: 'Cliente Prueba SpA',
  client_name: 'Cliente Prueba',
  challenge_title: 'Desafío de prueba',
  challenge_text: 'Texto con <b>marcas</b> y https://automatizatech.cl/demo (enlace).',
  solution_title: 'Solución de prueba',
  solution_text: 'Texto de solución',
  benefits: [{ title: 'Ahorro', text: 'Menos trabajo manual' }],
  how_it_works: [{ step_title: 'Inicio', step_text: 'Reunión inicial' }],
  pricing_rows: [
    { service: 'Sitio web', price_usd: 500, price_clp: 460000 },
    { service: 'Total', price_usd: 500, emphasis: true },
  ],
  pricing_note: 'Precio de prueba',
  next_steps: ['Aprobación', 'Inicio'],
};

function huella(html) {
  return crypto.createHash('sha256').update(html.replace(/\r\n/g, '\n'), 'utf8').digest('hex');
}

test('la propuesta final sale byte a byte igual que antes del plan de trabajo', () => {
  assert.equal(huella(template.renderProposalHtml(BASE, {})), 'd6eeb3845919cb90867fe38f195a03d2336dab6db29953d04ca689faac51a4e5');
});

test('el borrador con lámina extra y fotos también sale igual', () => {
  const data = { ...BASE, draft: true, extra_slides: [{ eyebrow: 'Alcance', title: 'Qué incluye', text: 'Detalle' }] };
  const images = { cover: 'https://example.com/portada.jpg', next_steps: 'img/next_steps.jpg' };
  assert.equal(huella(template.renderProposalHtml(data, images)), '38a644cf40fc0f07f570e22664f6d2a71450fddd3a506c50e16acb35476bb168');
});

test('template.js comparte con el plan su estilo, script, logo y controles', () => {
  assert.equal(typeof template.STYLE, 'string');
  assert.ok(template.STYLE.includes('.at-watermark'));
  assert.equal(typeof template.SCRIPT, 'string');
  assert.ok(template.SCRIPT.includes("type: 'at-deck-lamina'"));
  assert.ok(template.LOGO_URL.includes('logo-automatiza-tech'));
  assert.equal(template.CONTACTS.length, 4);
  assert.ok(template.CONTACT_ICONS.whatsapp.startsWith('<svg'));
  assert.ok(template.CONTACT_ICONS.web.startsWith('<svg'));
  assert.ok(template.backgroundStyle('', 0).startsWith('background: linear-gradient(135deg, #0d1b2a'));
  assert.ok(template.logoMark().includes('class="at-watermark"'));
  assert.ok(template.renderDeckControls().includes('id="at-prev"'));
});
```

- [ ] **Step 3: Correr la prueba y verla fallar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/template-propuesta-igual.test.js
```

Salida esperada: las dos huellas pasan (todavía no se tocó nada) y falla la de exportaciones porque `template.STYLE`
es `undefined` (`'undefined' !== 'string'`):

```text
✖ template.js comparte con el plan su estilo, script, logo y controles
ℹ tests 3
ℹ pass 2
ℹ fail 1
```

- [ ] **Step 4: Agregar las exportaciones al final de `template.js`**

Agregar este bloque al final de `renderer/src/template.js`, después de la última línea (`};` del `module.exports`).
No se modifica ninguna otra línea del archivo.

```js
// Piezas que comparte el plan de trabajo (template-plan.js): mismo estilo, script de navegación, logo,
// íconos de contacto y controles. Solo se agregan exportaciones; el HTML de la propuesta no cambia
// (lo cuida test/template-propuesta-igual.test.js).
Object.assign(module.exports, {
  STYLE,
  SCRIPT,
  LOGO_URL,
  CONTACTS,
  CONTACT_ICONS,
  backgroundStyle,
  logoMark,
  renderDeckControls,
});
```

- [ ] **Step 5: Correr las pruebas del template y verlas pasar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/template-propuesta-igual.test.js test/template.test.js test/template-deck.test.js
```

```text
ℹ tests 49
ℹ pass 49
ℹ fail 0
```

- [ ] **Step 6: Commit**

```bash
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer add renderer/src/template.js renderer/test/template-propuesta-igual.test.js
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer commit -m "feat(renderer): template.js comparte estilo, script, logo y controles con el plan de trabajo" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 7: Crear los cuerpos de ejemplo del plan**

Crear `renderer/test/fixtures/plan-ejemplo.js` (no lo toma `node --test test/*.test.js` porque no termina en `.test.js`).
Las fechas salen de las reglas del esqueleto: arranque el lunes 5-oct-2026 (firma el lunes 28-sep), revisión de 5 días
hábiles después de cada bloque con entrega, y los feriados 12-oct, 8-dic, 25-dic y 1-ene saltados (por ejemplo,
«Pruebas internas» de 2 días va del 7 al 9 de diciembre porque el 8 es feriado).

```js
// Cuerpos de ejemplo de POST /render con document_type 'plan' (la forma que arma at_pt_armar_render en
// WordPress). Datos inventados: el repositorio es público. Las fechas salen de las reglas del plan (días
// hábiles, revisión de 5 días hábiles después de cada entrega, feriados 12-oct, 8-dic, 25-dic y 1-ene).
// Cada función devuelve una copia nueva para que una prueba no le cambie los datos a la siguiente.

function act(nombre, responsable, dias_habiles, desde, hasta, extra = {}) {
  return {
    nombre,
    detalle: '',
    responsable,
    dias_habiles,
    servicio: '',
    etapa: '',
    origen: 'tabla',
    en_paralelo: false,
    desde,
    hasta,
    ...extra,
  };
}

function barra(fase, etiqueta, tipo, responsable, desde, hasta) {
  return { fase, etiqueta, tipo, responsable, desde, hasta };
}

function base(unique_id, proyecto) {
  return {
    document_type: 'plan',
    unique_id,
    draft: false,
    company_name: 'Cliente Prueba SpA',
    client_name: 'Cliente Prueba',
    proyecto,
    fecha_firma_larga: '28 de septiembre de 2026',
    metodo: {
      hechas: ['diagnostico', 'priorizacion'],
      actual: 'propuesta',
      proximas: ['diseno_desarrollo', 'implementacion', 'soporte'],
    },
    necesitamos_de_ti: ['Logo y colores de tu marca', 'Textos e imágenes de tu negocio', 'Accesos al dominio y al hosting'],
    reuniones: [
      { nombre: 'Reunión de inicio', detalle: 'Revisamos juntos este plan y los insumos.' },
      { nombre: 'Llamada de seguimiento del plan', detalle: 'Resolvemos tus dudas del cronograma.' },
      { nombre: 'Entrega y capacitación', detalle: '' },
    ],
    soporte: { garantia_meses: 3, mensuales: [] },
    portal_url: 'https://automatizatech.cl/?crm_view=timeline&cid=999&token=prueba-token',
    agenda: {
      whatsapp_url: `https://wa.me/56927002984?text=Hola%20Tech%2C%20quiero%20agendar%20la%20llamada%20de%20seguimiento%20de%20mi%20plan%20de%20trabajo%20(c%C3%B3digo%20${unique_id})`,
      web_url: '',
    },
    image_briefs: [],
    images: {},
  };
}

// Sitio de una página: 3 fases, 7 barras, 6 semanas.
function planCorto() {
  return {
    ...base('PlanPrueba01', 'Sitio de una página'),
    fecha_inicio: '2026-10-05',
    fecha_fin: '2026-11-10',
    semanas: 6,
    fases: [
      {
        clave: 'diseno_desarrollo',
        titulo: 'Diseño y desarrollo',
        descripcion: 'Diseñamos y construimos tu sitio de una página.',
        bloques: [
          {
            nombre: 'Arranque',
            entregable: '',
            entrega: false,
            actividades: [
              act('Reunión de inicio', 'ambos', 1, '2026-10-05', '2026-10-05', { etapa: 'arranque' }),
              act('Entrega de logo, textos y accesos', 'cliente', 3, '2026-10-06', '2026-10-08', { etapa: 'arranque' }),
            ],
          },
          {
            nombre: 'Diseño',
            entregable: 'Diseño de la página',
            entrega: true,
            actividades: [
              act('Diseño de la página', 'at', 3, '2026-10-09', '2026-10-14', { etapa: 'diseno', servicio: 'sitio_una_pagina' }),
            ],
          },
          {
            nombre: 'Desarrollo',
            entregable: 'Sitio en ambiente de prueba',
            entrega: true,
            actividades: [
              act('Construcción del sitio', 'at', 5, '2026-10-22', '2026-10-28', { etapa: 'desarrollo', servicio: 'sitio_una_pagina' }),
              act('Pruebas en celular y computador', 'at', 2, '2026-10-29', '2026-10-30', { etapa: 'pruebas', servicio: 'sitio_una_pagina' }),
            ],
          },
        ],
      },
      {
        clave: 'implementacion',
        titulo: 'Implementación',
        descripcion: 'Lo publicamos en tu dominio.',
        bloques: [
          {
            nombre: 'Publicación',
            entregable: '',
            entrega: false,
            actividades: [
              act('Publicación en tu dominio', 'at', 1, '2026-11-09', '2026-11-09', { etapa: 'implementacion', servicio: 'sitio_una_pagina' }),
            ],
          },
        ],
      },
      {
        clave: 'soporte',
        titulo: 'Soporte y mejora continua',
        descripcion: 'Te acompañamos después de publicar.',
        bloques: [
          {
            nombre: 'Garantía',
            entregable: '',
            entrega: false,
            actividades: [act('Revisión a las dos semanas', 'ambos', 1, '2026-11-10', '2026-11-10', { etapa: 'soporte' })],
          },
        ],
      },
    ],
    cronograma: {
      inicio: '2026-10-05',
      fin: '2026-11-10',
      semanas: 6,
      barras: [
        barra('diseno_desarrollo', 'Arranque', 'trabajo', 'ambos', '2026-10-05', '2026-10-08'),
        barra('diseno_desarrollo', 'Diseño', 'trabajo', 'at', '2026-10-09', '2026-10-14'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-10-15', '2026-10-21'),
        barra('diseno_desarrollo', 'Desarrollo', 'trabajo', 'at', '2026-10-22', '2026-10-30'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-11-02', '2026-11-06'),
        barra('implementacion', 'Publicación', 'trabajo', 'at', '2026-11-09', '2026-11-09'),
        barra('soporte', 'Garantía', 'trabajo', 'ambos', '2026-11-10', '2026-11-10'),
      ],
      hitos: [
        { nombre: 'Diseño aprobado', fecha: '2026-10-21' },
        { nombre: 'Entrega estimada', fecha: '2026-11-09' },
      ],
    },
  };
}

// Plataforma a medida con soporte: 14 semanas, 16 barras (6 de revisión), con feriados en medio.
function planLargo() {
  const plataforma = { servicio: 'plataforma' };
  return {
    ...base('PlanPrueba02', 'Plataforma de reservas y pagos'),
    fecha_inicio: '2026-10-05',
    fecha_fin: '2027-01-08',
    semanas: 14,
    fases: [
      {
        clave: 'diseno_desarrollo',
        titulo: 'Diseño y desarrollo',
        descripcion:
          'Diseñamos las pantallas contigo y construimos la plataforma por partes, para que veas avances reales cada pocas semanas.',
        bloques: [
          {
            nombre: 'Arranque',
            entregable: '',
            entrega: false,
            actividades: [
              act('Reunión de inicio', 'ambos', 1, '2026-10-05', '2026-10-05', { etapa: 'arranque' }),
              act('Entrega de logo, textos y accesos', 'cliente', 3, '2026-10-06', '2026-10-08', { etapa: 'arranque' }),
            ],
          },
          {
            nombre: 'Diseño',
            entregable: 'Diseño de todas las pantallas',
            entrega: true,
            actividades: [
              act('Mapa de pantallas y flujos', 'at', 3, '2026-10-09', '2026-10-14', { etapa: 'diseno', ...plataforma }),
              act('Diseño visual de las pantallas', 'at', 4, '2026-10-15', '2026-10-20', { etapa: 'diseno', ...plataforma }),
            ],
          },
          {
            nombre: 'Núcleo de la plataforma',
            entregable: 'Usuarios y panel funcionando en ambiente de prueba',
            entrega: true,
            actividades: [
              act('Base de datos y usuarios', 'at', 4, '2026-10-28', '2026-11-02', { etapa: 'desarrollo', ...plataforma }),
              act('Panel de administración', 'at', 3, '2026-11-03', '2026-11-05', { etapa: 'desarrollo', ...plataforma }),
            ],
          },
          {
            nombre: 'Agenda y pagos',
            entregable: 'Reservas y cobro en línea en ambiente de prueba',
            entrega: true,
            actividades: [
              act('Módulo de agenda', 'at', 4, '2026-11-13', '2026-11-18', { etapa: 'desarrollo', ...plataforma }),
              act('Módulo de pagos', 'at', 4, '2026-11-13', '2026-11-18', { etapa: 'desarrollo', en_paralelo: true, ...plataforma }),
            ],
          },
          {
            nombre: 'Integraciones',
            entregable: 'WhatsApp y correos automáticos conectados',
            entrega: true,
            actividades: [
              act('Conexión con WhatsApp', 'at', 2, '2026-11-26', '2026-11-27', { etapa: 'desarrollo', origen: 'ia', ...plataforma }),
              act('Correos automáticos', 'at', 2, '2026-11-26', '2026-11-27', {
                etapa: 'desarrollo',
                origen: 'ia',
                en_paralelo: true,
                ...plataforma,
              }),
            ],
          },
          {
            nombre: 'Pruebas y revisión',
            entregable: 'Plataforma completa lista para publicar',
            entrega: true,
            actividades: [
              act('Pruebas internas', 'at', 2, '2026-12-07', '2026-12-09', { etapa: 'pruebas', ...plataforma }),
              act('Pruebas con tu equipo', 'ambos', 2, '2026-12-10', '2026-12-11', { etapa: 'pruebas', ...plataforma }),
            ],
          },
        ],
      },
      {
        clave: 'implementacion',
        titulo: 'Implementación',
        descripcion: 'La dejamos funcionando en tu dominio, con tus datos reales y tu equipo capacitado.',
        bloques: [
          {
            nombre: 'Puesta en marcha',
            entregable: '',
            entrega: false,
            actividades: [
              act('Carga de datos iniciales', 'cliente', 1, '2026-12-21', '2026-12-21', { etapa: 'implementacion', ...plataforma }),
              act('Publicación en tu dominio', 'at', 1, '2026-12-22', '2026-12-22', { etapa: 'implementacion', ...plataforma }),
            ],
          },
          {
            nombre: 'Capacitación',
            entregable: 'Equipo capacitado y manual de uso',
            entrega: true,
            actividades: [
              act('Capacitación del equipo', 'ambos', 1, '2026-12-23', '2026-12-23', { etapa: 'implementacion', ...plataforma }),
              act('Manual de uso', 'at', 1, '2026-12-23', '2026-12-23', { etapa: 'implementacion', en_paralelo: true, ...plataforma }),
            ],
          },
        ],
      },
      {
        clave: 'soporte',
        titulo: 'Soporte y mejora continua',
        descripcion: 'No desaparecemos: acompañamos las primeras semanas, medimos y ajustamos contigo.',
        bloques: [
          {
            nombre: 'Acompañamiento',
            entregable: '',
            entrega: false,
            actividades: [act('Acompañamiento posterior al lanzamiento', 'at', 3, '2027-01-04', '2027-01-06', { etapa: 'soporte' })],
          },
          {
            nombre: 'Medición y mejoras',
            entregable: '',
            entrega: false,
            actividades: [act('Informe de uso y mejoras propuestas', 'at', 2, '2027-01-07', '2027-01-08', { etapa: 'soporte' })],
          },
        ],
      },
    ],
    cronograma: {
      inicio: '2026-10-05',
      fin: '2027-01-08',
      semanas: 14,
      barras: [
        barra('diseno_desarrollo', 'Arranque', 'trabajo', 'ambos', '2026-10-05', '2026-10-08'),
        barra('diseno_desarrollo', 'Diseño', 'trabajo', 'at', '2026-10-09', '2026-10-20'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-10-21', '2026-10-27'),
        barra('diseno_desarrollo', 'Núcleo de la plataforma', 'trabajo', 'at', '2026-10-28', '2026-11-05'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-11-06', '2026-11-12'),
        barra('diseno_desarrollo', 'Agenda y pagos', 'trabajo', 'at', '2026-11-13', '2026-11-18'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-11-19', '2026-11-25'),
        barra('diseno_desarrollo', 'Integraciones', 'trabajo', 'at', '2026-11-26', '2026-11-27'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-11-30', '2026-12-04'),
        barra('diseno_desarrollo', 'Pruebas y revisión', 'trabajo', 'ambos', '2026-12-07', '2026-12-11'),
        barra('diseno_desarrollo', 'Tu revisión', 'revision', 'cliente', '2026-12-14', '2026-12-18'),
        barra('implementacion', 'Puesta en marcha', 'trabajo', 'ambos', '2026-12-21', '2026-12-22'),
        barra('implementacion', 'Capacitación', 'trabajo', 'ambos', '2026-12-23', '2026-12-23'),
        barra('implementacion', 'Tu revisión', 'revision', 'cliente', '2026-12-24', '2026-12-31'),
        barra('soporte', 'Acompañamiento', 'trabajo', 'at', '2027-01-04', '2027-01-06'),
        barra('soporte', 'Medición y mejoras', 'trabajo', 'at', '2027-01-07', '2027-01-08'),
      ],
      hitos: [
        { nombre: 'Diseño aprobado', fecha: '2026-10-27' },
        { nombre: 'Plataforma aprobada', fecha: '2026-12-18' },
        { nombre: 'Entrega estimada', fecha: '2026-12-23' },
      ],
    },
  };
}

module.exports = { planCorto, planLargo };
```

- [ ] **Step 8: Escribir la prueba de `validatePlanPayload`**

Crear `renderer/test/schema-plan.test.js`:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const { validatePlanPayload, validatePayload } = require('../src/schema');
const { planCorto } = require('./fixtures/plan-ejemplo');

test('acepta el cuerpo de un plan completo', () => {
  assert.deepEqual(validatePlanPayload(planCorto()), { valid: true, errors: [] });
});

test('rechaza un cuerpo que no es un objeto', () => {
  for (const cuerpo of [null, [], 'plan']) {
    assert.deepEqual(validatePlanPayload(cuerpo), { valid: false, errors: ['el cuerpo debe ser un objeto JSON'] });
  }
});

test('exige unique_id (con la forma que acepta /render), company_name y proyecto con texto', () => {
  const r = validatePlanPayload({ ...planCorto(), unique_id: '', company_name: '   ', proyecto: 7 });
  assert.equal(r.valid, false);
  assert.ok(r.errors.includes('falta o está vacío el campo obligatorio: unique_id'));
  assert.ok(r.errors.includes('falta o está vacío el campo obligatorio: company_name'));
  assert.ok(r.errors.includes('falta o está vacío el campo obligatorio: proyecto'));
  assert.ok(!r.errors.some((e) => e.startsWith('unique_id debe')), 'vacío: un solo motivo');
  // El código del plan (12 letras y números, wp_generate_password) siempre pasa; un unique_id raro se rechaza
  // con motivo legible, igual que los demás errores del esquema.
  assert.deepEqual(validatePlanPayload({ ...planCorto(), unique_id: 'Ab3dE5fG7hJ9' }), { valid: true, errors: [] });
  for (const malo of ['../fuera', 'corto', 'con espacio 1']) {
    assert.deepEqual(validatePlanPayload({ ...planCorto(), unique_id: malo }).errors, [
      'unique_id debe tener de 6 a 64 caracteres, solo letras, números, guion o guion bajo',
    ]);
  }
});

test('exige al menos una fase', () => {
  assert.ok(validatePlanPayload({ ...planCorto(), fases: [] }).errors.includes('falta o está vacío el arreglo obligatorio: fases'));
  const sin = planCorto();
  delete sin.fases;
  assert.ok(validatePlanPayload(sin).errors.includes('falta o está vacío el arreglo obligatorio: fases'));
});

test('exige el cronograma con inicio y fin como fechas reales y las barras como arreglo', () => {
  const sin = planCorto();
  delete sin.cronograma;
  assert.deepEqual(validatePlanPayload(sin).errors, ['falta el objeto obligatorio: cronograma']);

  const malo = planCorto();
  malo.cronograma = { inicio: '05-10-2026', fin: '2026-02-30', barras: 'ninguna' };
  const errores = validatePlanPayload(malo).errors;
  assert.ok(errores.includes('cronograma.inicio debe ser una fecha AAAA-MM-DD'));
  assert.ok(errores.includes('cronograma.fin debe ser una fecha AAAA-MM-DD'));
  assert.ok(errores.includes('cronograma.barras debe ser un arreglo'));

  const alReves = planCorto();
  alReves.cronograma.fin = '2026-09-01';
  assert.ok(validatePlanPayload(alReves).errors.includes('cronograma.fin no puede ser anterior a cronograma.inicio'));
});

test('rechaza image_briefs que no sea un arreglo', () => {
  assert.ok(validatePlanPayload({ ...planCorto(), image_briefs: 'fotos' }).errors.includes('image_briefs debe ser un arreglo si viene'));
});

test('la validación de propuestas no cambia: un plan no pasa como propuesta', () => {
  assert.equal(validatePayload(planCorto()).valid, false);
});
```

- [ ] **Step 9: Correr la prueba y verla fallar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/schema-plan.test.js
```

Salida esperada: falla con `TypeError: validatePlanPayload is not a function` en seis pruebas; la séptima (la de
propuestas) ya pasa:

```text
validatePlanPayload is not a function
ℹ tests 7
ℹ pass 1
ℹ fail 6
```

- [ ] **Step 10: Implementar `validatePlanPayload` en `schema.js`**

Insertar este bloque en `renderer/src/schema.js` justo antes de la línea 39
(`module.exports = { validatePayload, REQUIRED_STRING_FIELDS, REQUIRED_ARRAY_FIELDS };`), dejando una línea en blanco
entre el bloque y esa línea:

```js
// Plan de trabajo (document_type 'plan'). Solo revisa la forma que el renderer necesita para dibujar;
// el contenido (fases válidas, días de 1 a 60, responsables) ya lo validó WordPress con at_pt_validar_plan.
const PLAN_REQUIRED_STRING_FIELDS = ['unique_id', 'company_name', 'proyecto'];

function esFechaYmd(valor) {
  if (typeof valor !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(valor)) return false;
  const d = new Date(`${valor}T00:00:00Z`);
  return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === valor;
}

function validatePlanPayload(body) {
  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    return { valid: false, errors: ['el cuerpo debe ser un objeto JSON'] };
  }
  const errors = [];

  for (const field of PLAN_REQUIRED_STRING_FIELDS) {
    if (typeof body[field] !== 'string' || body[field].trim() === '') {
      errors.push(`falta o está vacío el campo obligatorio: ${field}`);
    }
  }

  // La misma regla que server.js aplica después (unique_id pasa a ser una carpeta de publicDir), pero aquí
  // con motivo legible en details: todo 400 del plan trae details y n8n lo copia a la nota sin reintentar.
  if (typeof body.unique_id === 'string' && body.unique_id.trim() !== '' && !/^[A-Za-z0-9_-]{6,64}$/.test(body.unique_id)) {
    errors.push('unique_id debe tener de 6 a 64 caracteres, solo letras, números, guion o guion bajo');
  }

  if (!Array.isArray(body.fases) || body.fases.length === 0) {
    errors.push('falta o está vacío el arreglo obligatorio: fases');
  }

  const c = body.cronograma;
  if (!c || typeof c !== 'object' || Array.isArray(c)) {
    errors.push('falta el objeto obligatorio: cronograma');
  } else {
    if (!esFechaYmd(c.inicio)) errors.push('cronograma.inicio debe ser una fecha AAAA-MM-DD');
    if (!esFechaYmd(c.fin)) errors.push('cronograma.fin debe ser una fecha AAAA-MM-DD');
    if (esFechaYmd(c.inicio) && esFechaYmd(c.fin) && c.fin < c.inicio) {
      errors.push('cronograma.fin no puede ser anterior a cronograma.inicio');
    }
    if (!Array.isArray(c.barras)) errors.push('cronograma.barras debe ser un arreglo');
  }

  if (body.image_briefs !== undefined && !Array.isArray(body.image_briefs)) {
    errors.push('image_briefs debe ser un arreglo si viene');
  }

  return { valid: errors.length === 0, errors };
}
```

Y reemplazar esa línea 39:

```js
module.exports = { validatePayload, REQUIRED_STRING_FIELDS, REQUIRED_ARRAY_FIELDS };
```

por:

```js
module.exports = { validatePayload, validatePlanPayload, REQUIRED_STRING_FIELDS, REQUIRED_ARRAY_FIELDS, PLAN_REQUIRED_STRING_FIELDS };
```

- [ ] **Step 11: Correr la prueba y verla pasar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/schema-plan.test.js test/schema.test.js
```

```text
ℹ tests 11
ℹ pass 11
ℹ fail 0
```

- [ ] **Step 12: Commit**

```bash
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer add renderer/src/schema.js renderer/test/schema-plan.test.js renderer/test/fixtures/plan-ejemplo.js
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer commit -m "feat(renderer): validatePlanPayload valida la forma del plan de trabajo con motivos en español" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 13: Escribir la prueba del servidor con `document_type: 'plan'`**

Crear `renderer/test/server-plan.test.js` (usa Playwright de verdad, como `server.test.js`). Las dos pruebas de fotos
cubren el caso 4 del lado del renderer: con propuesta, n8n manda portada y cierre en `images` y solo se pide a
Higgsfield la foto que falta; sin propuesta, `images` llega vacío y portada y cierre se piden como fotos nuevas. El
Higgsfield falso responde 500, así que esas láminas quedan en `missing` sin esperar su cola:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { createApp } = require('../src/server');
const { planCorto } = require('./fixtures/plan-ejemplo');

async function conServidor(fn) {
  const publicDir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-plan-'));
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

function renderizar(base, cuerpo) {
  return fetch(`${base}/render`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(cuerpo),
  });
}

test('POST /render con document_type plan dibuja el plan de trabajo, no una propuesta', async () => {
  await conServidor(async (base, publicDir) => {
    const r = await renderizar(base, planCorto());
    assert.equal(r.status, 200);
    const cuerpo = await r.json();
    assert.equal(cuerpo.view_url, 'http://localhost:3000/p/PlanPrueba01/index.html');
    assert.equal(cuerpo.pdf_url, 'http://localhost:3000/p/PlanPrueba01/presentation.pdf');
    const html = await fs.readFile(path.join(publicDir, 'PlanPrueba01', 'index.html'), 'utf8');
    assert.ok(html.includes('Plan de trabajo — Sitio de una página'));
    assert.ok(!html.includes('Propuesta de transformación digital'));
    const pdf = await fs.stat(path.join(publicDir, 'PlanPrueba01', 'presentation.pdf'));
    assert.ok(pdf.size > 0);
  });
});

// Todo 400 de un plan trae `details` con motivos en español: n8n (Task 14) no lo reintenta y copia esos
// motivos a la nota del plan en «error» (decisión D11).
test('un plan inválido responde 400 con motivos legibles en details, sin escribir nada', async () => {
  await conServidor(async (base, publicDir) => {
    const plan = planCorto();
    delete plan.cronograma;
    const r = await renderizar(base, plan);
    assert.equal(r.status, 400);
    const cuerpo = await r.json();
    assert.equal(cuerpo.error, 'invalid payload');
    assert.deepEqual(cuerpo.details, ['falta el objeto obligatorio: cronograma']);

    const raro = await renderizar(base, { ...planCorto(), unique_id: '../fuera' });
    assert.equal(raro.status, 400);
    assert.deepEqual(await raro.json(), {
      error: 'invalid payload',
      details: ['unique_id debe tener de 6 a 64 caracteres, solo letras, números, guion o guion bajo'],
    });
    assert.deepEqual(await fs.readdir(publicDir), []);
  });
});

test('sin document_type el cuerpo se valida como propuesta, igual que hoy', async () => {
  await conServidor(async (base) => {
    const plan = planCorto();
    delete plan.document_type;
    const r = await renderizar(base, plan);
    assert.equal(r.status, 400);
    const cuerpo = await r.json();
    assert.ok(cuerpo.details.includes('missing or empty required field: challenge_title'));
  });
});

// Higgsfield falso: anota el prompt de cada foto que se le pide y responde 500 (en la prueba no hay
// credenciales), así esa lámina queda en «missing» sin esperar la cola. Las fotos de cdn.example.com (las
// que n8n reutiliza de la propuesta) se «descargan» al tiro. Lo demás (el propio servidor) pasa derecho.
async function conHiggsfieldFalso(fn) {
  const originalFetch = global.fetch;
  const prompts = [];
  global.fetch = async (url, opts) => {
    const u = String(url);
    if (u.includes('higgsfield')) {
      prompts.push(JSON.parse(opts.body).prompt);
      return { ok: false, status: 500, text: async () => 'sin credenciales en la prueba' };
    }
    if (u.startsWith('https://cdn.example.com/')) {
      return { ok: true, status: 200, headers: { get: () => 'image/png' }, arrayBuffer: async () => Buffer.from('PNG') };
    }
    return originalFetch(url, opts);
  };
  try {
    await fn(prompts);
  } finally {
    global.fetch = originalFetch;
  }
}

const BRIEFS = [
  { slide: 'cover', prompt: 'foto-portada' },
  { slide: 'metodo', prompt: 'foto-metodo' },
  { slide: 'cierre', prompt: 'foto-cierre' },
];

test('con propuesta: portada y cierre llegan en images y no se piden a Higgsfield; la lámina nueva sí', async () => {
  await conHiggsfieldFalso(async (prompts) => {
    await conServidor(async (base, publicDir) => {
      const plan = {
        ...planCorto(),
        image_briefs: BRIEFS,
        images: { cover: 'https://cdn.example.com/portada.png', cierre: 'https://cdn.example.com/proximos.png' },
      };
      const r = await renderizar(base, plan);
      assert.equal(r.status, 200);
      const cuerpo = await r.json();
      assert.deepEqual([...new Set(prompts)], ['foto-metodo']);
      assert.equal(cuerpo.images.requested, 3);
      assert.equal(cuerpo.images.stored_local, 2);
      assert.deepEqual(cuerpo.images.missing, ['metodo']);
      const html = await fs.readFile(path.join(publicDir, 'PlanPrueba01', 'index.html'), 'utf8');
      assert.ok(html.includes("url('img/cover.png')"));
    });
  });
});

test('contrato sin propuesta: portada y cierre se piden como fotos nuevas', async () => {
  await conHiggsfieldFalso(async (prompts) => {
    await conServidor(async (base) => {
      const r = await renderizar(base, { ...planCorto(), image_briefs: BRIEFS, images: {} });
      assert.equal(r.status, 200);
      const cuerpo = await r.json();
      assert.deepEqual([...new Set(prompts)].sort(), ['foto-cierre', 'foto-metodo', 'foto-portada']);
      assert.deepEqual([...cuerpo.images.missing].sort(), ['cierre', 'cover', 'metodo']);
    });
  });
});
```

- [ ] **Step 14: Correr la prueba y verla fallar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/server-plan.test.js
```

Salida esperada: el servidor todavía valida el plan como propuesta y responde 400 (`400 !== 200`) en las cuatro
pruebas que mandan un plan; la prueba «sin document_type…» ya pasa:

```text
✖ POST /render con document_type plan dibuja el plan de trabajo, no una propuesta
✖ contrato sin propuesta: portada y cierre se piden como fotos nuevas
ℹ tests 5
ℹ pass 1
ℹ fail 4
```

- [ ] **Step 15: Crear `template-plan.js` con la portada y los marcadores de sección**

Crear `renderer/src/template-plan.js`. Los marcadores `// === … ===` son parte del contrato con la Task 12: cada sección
nueva se inserta justo antes del marcador que la sigue.

```js
const { escapeHtml } = require('./escape');
const { STYLE, SCRIPT, backgroundStyle, logoMark, renderDeckControls } = require('./template');

// Plan de trabajo: el documento que acompaña al contrato de servicios firmado. Usa el mismo estilo, logo
// y modo presentación que la propuesta (piezas que exporta template.js, sin cambiarlas) y suma sus
// láminas propias. Todo texto que viene del plan pasa por escapeHtml.
// El archivo va por secciones; cada sección nueva se agrega justo antes del marcador que la sigue.

// === Fechas ===

// === Ayudas ===

// === Carta Gantt ===

// === Láminas ===

function renderPlanCover(data, imageUrl) {
  const proyecto = String(data.proyecto || '');
  const cliente = String(data.client_name || '').trim();
  const semanas = Math.round(Number(data.semanas) || 0);
  const detalle = [
    cliente ? `Preparado para ${escapeHtml(cliente)}` : '',
    semanas > 0 ? `${semanas} ${semanas === 1 ? 'semana estimada' : 'semanas estimadas'}` : '',
  ]
    .filter(Boolean)
    .join(' · ');
  return `
    <section class="slide slide-cover plan-cover">
      <div class="slide-bg" style="${backgroundStyle(imageUrl, 0, 'cover')}"></div>
      ${logoMark()}
      <div class="slide-body">
        <p class="eyebrow">${escapeHtml(String(data.company_name || ''))}</p>
        <h1${proyecto.length > 48 ? ' class="is-largo"' : ''}>Plan de trabajo — ${escapeHtml(proyecto)}</h1>
        ${detalle ? `<p class="lede">${detalle}</p>` : ''}
        <div class="accent-bar"></div>
      </div>
    </section>`;
}

const ESTILO_PORTADA = `
  .plan-cover h1 { font-size: 66px; line-height: 1.12; max-width: 86%; overflow-wrap: anywhere; }
  .plan-cover h1.is-largo { font-size: 52px; }
`;

// === Documento ===

function renderPlanHtml(data, images = {}) {
  const d = data && typeof data === 'object' ? data : {};
  const fotos = images && typeof images === 'object' ? images : {};
  const preload = fotos.cover ? `<link rel="preload" as="image" href="${escapeHtml(fotos.cover)}" />` : '';
  return `<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Plan de trabajo · ${escapeHtml(String(d.proyecto || ''))}</title>
${preload}
<style>${STYLE}${ESTILO_PORTADA}</style>
</head>
<body${d.draft ? ' class="is-draft"' : ''}>
${d.draft ? '<div class="at-draft-badge">Borrador · vista previa sin fotos</div>' : ''}
<div class="deck">
${renderPlanCover(d, fotos.cover)}
</div>
${renderDeckControls()}
<script>${SCRIPT}</script>
</body>
</html>`;
}

module.exports = { renderPlanHtml };
```

- [ ] **Step 16: Elegir el template según `document_type` en `server.js`**

Cuatro reemplazos de una línea en `renderer/src/server.js`.

Línea 4, reemplazar:

```js
const { validatePayload } = require('./schema');
```

por:

```js
const { validatePayload, validatePlanPayload } = require('./schema');
```

Línea 6, reemplazar:

```js
const { renderProposalHtml } = require('./template');
```

por:

```js
const { renderProposalHtml } = require('./template');
const { renderPlanHtml } = require('./template-plan');
```

Línea 41, reemplazar:

```js
    const { valid, errors } = validatePayload(req.body);
```

por:

```js
    // Un plan de trabajo llega con document_type 'plan'; sin ese campo es una propuesta, como siempre.
    const esPlan = Boolean(req.body) && req.body.document_type === 'plan';
    const { valid, errors } = esPlan ? validatePlanPayload(req.body) : validatePayload(req.body);
```

Línea 144 (antes de los reemplazos anteriores; queda en la 147), reemplazar:

```js
      html = renderProposalHtml(data, images);
```

por:

```js
      html = esPlan ? renderPlanHtml(data, images) : renderProposalHtml(data, images);
```

Todo lo demás de `/render` (clave `X-AT-Render-Key`, `unique_id` con `/^[A-Za-z0-9_-]{6,64}$/`, fotos, manifiesto,
respuesta) queda igual para los dos tipos de documento.

- [ ] **Step 17: Correr la prueba del servidor y verla pasar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/server-plan.test.js
```

```text
ℹ tests 5
ℹ pass 5
ℹ fail 0
```

- [ ] **Step 18: Correr toda la batería (95 de antes + 15 nuevas)**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/*.test.js
```

```text
ℹ tests 110
ℹ pass 110
ℹ fail 0
```

- [ ] **Step 19: Commit**

```bash
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer add renderer/src/server.js renderer/src/template-plan.js renderer/test/server-plan.test.js
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer commit -m "feat(renderer): /render dibuja el plan de trabajo cuando llega document_type plan" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

Entregable probado: `POST /render` con `document_type: 'plan'` genera `/p/<codigo>/index.html` y `presentation.pdf`
con la portada del plan, rechaza con 400 y motivo legible en `details` un plan sin cronograma, con fechas imposibles
o con un `unique_id` que no sirve de carpeta, pide a
Higgsfield solo las fotos que no llegaron en `images` (con propuesta, la del método; sin propuesta, también portada y
cierre), y las propuestas salen byte a byte iguales.

---

### Task 12: Renderer — `template-plan.js` con las 8 láminas y la carta Gantt

Misma rama y worktree que la Task 11. El documento tiene 8 tipos de lámina, en este orden y con esta clave de foto
(`image_briefs[].slide` / `images`): 1 portada `cover`; 2 Método AT `metodo`; 3 carta Gantt `gantt`; 4 una lámina por
fase `fase_1`..`fase_3` (más láminas de «continuación» si la fase no cabe); 5 qué necesitamos de ti `necesitamos`;
6 reuniones y soporte `reuniones`; 7 sigue tu proyecto `portal`; 8 cierre para agendar `cierre`. Un plan de 3 fases
cortas tiene 10 láminas; el plan largo del fixture, 11. Mismo estilo, logo, modo presentación, `is-draft` y aviso
`at-deck-lamina` que la propuesta (las piezas de `template.js` de la Task 11). Sin librerías: la Gantt es HTML y CSS.

Decisiones de diseño que fijan las pruebas:
- **Gantt por días de calendario sobre una grilla semanal** (lunes a domingo, los fines de semana en una franja más
  oscura): una barra de lunes a viernes cubre 5/7 de su semana. Una fila por bloque de trabajo; la revisión del cliente
  (5 días hábiles, franjas ámbar) va en la **misma fila** que el bloque que revisa, así 16 barras son 10 filas.
- **Siempre cabe:** el alto de fila se reparte en 660 px (`medidasGantt`): 48 px con 10 filas; con muchas filas se
  achica (clase `is-denso` bajo 34 px) y nunca pasa del alto. Con más de 18 semanas la cabecera usa `5/10`.
- **Colores:** AutomatizaTech turquesa `#00d9c0`, Tú celeste `#38bdf8`, Ambos lila `#a78bfa`, Tu revisión ámbar a rayas;
  hitos como rombos (la «Entrega estimada», turquesa y con línea guía de color); etiquetas de hitos en dos carriles.
- **Fases largas** se reparten en láminas por alto medido de fila (`paginarBloques`) y con a lo más 5 entregas por
  lámina, para que la tarjeta «Qué aprobamos juntos» quepa; nombres y detalles de una sola línea con puntos
  suspensivos; un bloque gigante se corta en trozos «(sigue)». La revisión de cada entrega se busca por
  `nombre#ocurrencia`, así dos bloques con el mismo nombre en una fase no se pisan las fechas.
- **Cláusulas del contrato:** se citan la 4.2 (plazo desde el anticipo y los insumos) y la 6.1 (5 días hábiles para
  aprobar), que tienen el mismo número en todas las versiones del contrato; la garantía se cita como «cláusula de
  Garantía de tu contrato», sin número, porque pasó de 12.1 a 13.1 cuando se agregó la cláusula de IA (commit
  `6c2dc62`) y el contrato 12 se firmó antes. Su texto repite la exclusión del contrato (cambios de terceros o del
  cliente).
- Fechas en español y en UTC («5 de octubre de 2026», «9 – 14 oct», «28 oct – 5 nov»); nada depende de la zona horaria.
- Enlaces solo `https` (`urlSegura`): un `portal_url` vacío o raro deja la lámina «Sigue tu proyecto» sin botón; el
  enlace web de agenda aparece solo si `agenda.web_url` trae valor (Etapa 1: nunca). En la prueba de punta a punta
  local (Task 15) `at_crm_url_portal()` arma el enlace con `home_url`, que en local es `http://`: la lámina sale sin
  botón y con «Pídenos el enlace a tu portal cuando quieras.». Es lo esperado, no una falla.

**Files:**
- Modify: `renderer/src/template-plan.js` — insertar código antes de los marcadores `// === Ayudas ===`,
  `// === Carta Gantt ===`, `// === Láminas ===` y `// === Documento ===`; reemplazar las líneas 1-2 (`require`) y la
  línea final `module.exports = { … };`; al final, reemplazar desde `// === Documento ===` hasta el fin del archivo.
- Test: `renderer/test/template-plan.test.js` (se crea en el Step 1 y crece en cada ciclo) y
  `renderer/test/template-plan-layout.test.js` (Step 21: mide las láminas en Chromium a 1920×1080 y cuenta las páginas del PDF).
- Fuera del repositorio: `C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/plan-renderer-capturas/capturas-plan.js` (revisión visual; no se commitea).

**Interfaces:**
- Consumes (Task 11): de `renderer/src/template.js` `STYLE`, `SCRIPT`, `CONTACT_ICONS`, `LOGO_URL`,
  `backgroundStyle(imageUrl, index, variant)`, `logoMark()`, `renderDeckControls()`; de `renderer/src/escape.js`
  `escapeHtml(value)`, `linkify(escaped)`; de `renderer/src/template-plan.js` `renderPlanCover(data, imageUrl)`,
  `ESTILO_PORTADA` y los marcadores; de `renderer/test/fixtures/plan-ejemplo.js` `planCorto()`, `planLargo()`.
  Del cuerpo de WordPress (Task 4): `cronograma.barras[] = {fase, etiqueta, tipo: 'trabajo'|'revision', responsable, desde, hasta}`
  con `etiqueta` = nombre del bloque en las barras de trabajo; `cronograma.hitos[] = {nombre, fecha}` con el hito fijo
  `'Entrega estimada'`; `fases[].bloques[].actividades[] = {nombre, detalle, responsable, dias_habiles, en_paralelo, desde, hasta}`.
- Produces (lo usan la Task 11 vía `server.js`, las Tasks 13/14 por las claves de foto y la Task 15):
  - `renderPlanHtml(data, images = {}): string` — documento completo.
  - `lunesDe(fecha: string): string` ('' si no es fecha), `semanaIndice(fecha: string, lunesInicio: string): number`
    (NaN si no es fecha), `diasEntre(desde, hasta): number`, `fechaLarga(ymd): string`, `fechaCorta(ymd): string`,
    `rangoCorto(desde, hasta): string`, `diasTexto(n): string`, `urlSegura(url): string`.
  - `renderGantt(cronograma): string`, `filasGantt(cronograma): {fase, titulo, filas: {fase, trabajo, revision, orden}[]}[]`,
    `medidasGantt(nFilas, nFases, conHitos): {fila: number, denso: boolean}`, `renderGanttSlide(data, index, imageUrl): string`.
  - `renderMetodoSlide(data, index, imageUrl)`, `paginarBloques(bloques, descripcion = ''): object[][]`,
    `renderFaseSlide({fase, numeroFase, totalFases, bloques, continuacion, cronograma}, index, imageUrl)`,
    `renderNecesitamosSlide(data, index, imageUrl)`, `renderReunionesSlide(data, index, imageUrl)`,
    `renderPortalSlide(data, index, imageUrl)`, `renderCierreSlide(data, index, imageUrl)` — todas devuelven `string`.
  - Claves de foto: `cover`, `metodo`, `gantt`, `fase_1`, `fase_2`, `fase_3`, `necesitamos`, `reuniones`, `portal`, `cierre`.

#### Ciclo A — fechas y ayudas

- [ ] **Step 1: Escribir las pruebas de fechas y ayudas**

Crear `renderer/test/template-plan.test.js`:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const tp = require('../src/template-plan');
const { planCorto, planLargo } = require('./fixtures/plan-ejemplo');

function contar(texto, aguja) {
  return texto.split(aguja).length - 1;
}

// --- Fechas y ayudas ---------------------------------------------------------

test('lunesDe devuelve el lunes de la semana, también en domingo y al cambiar de año', () => {
  assert.equal(tp.lunesDe('2026-10-05'), '2026-10-05');
  assert.equal(tp.lunesDe('2026-10-07'), '2026-10-05');
  assert.equal(tp.lunesDe('2026-10-11'), '2026-10-05');
  assert.equal(tp.lunesDe('2027-01-01'), '2026-12-28');
  assert.equal(tp.lunesDe('2026-02-30'), '');
  assert.equal(tp.lunesDe(''), '');
});

test('semanaIndice cuenta semanas de lunes a domingo desde el lunes de inicio', () => {
  assert.equal(tp.semanaIndice('2026-10-05', '2026-10-05'), 0);
  assert.equal(tp.semanaIndice('2026-10-11', '2026-10-05'), 0);
  assert.equal(tp.semanaIndice('2026-10-12', '2026-10-05'), 1);
  assert.equal(tp.semanaIndice('2027-01-08', '2026-10-05'), 13);
  assert.ok(Number.isNaN(tp.semanaIndice('no-es-fecha', '2026-10-05')));
});

test('las fechas se escriben en español y no dependen de la zona horaria', () => {
  assert.equal(tp.fechaLarga('2026-09-28'), '28 de septiembre de 2026');
  assert.equal(tp.fechaLarga('2027-01-01'), '1 de enero de 2027');
  assert.equal(tp.fechaCorta('2026-10-05'), '5 oct');
  assert.equal(tp.rangoCorto('2026-10-09', '2026-10-14'), '9 – 14 oct');
  assert.equal(tp.rangoCorto('2026-10-28', '2026-11-05'), '28 oct – 5 nov');
  assert.equal(tp.rangoCorto('2026-12-23', '2026-12-23'), '23 dic');
  assert.equal(tp.diasEntre('2026-10-05', '2026-10-12'), 7);
  assert.equal(tp.fechaLarga('2026-13-01'), '');
});

test('días hábiles en singular y plural', () => {
  assert.equal(tp.diasTexto(1), '1 día hábil');
  assert.equal(tp.diasTexto(3), '3 días hábiles');
  assert.equal(tp.diasTexto('x'), '0 días hábiles');
});

test('urlSegura solo deja pasar enlaces https sin comillas ni espacios', () => {
  assert.equal(tp.urlSegura(' https://wa.me/56927002984?text=Hola '), 'https://wa.me/56927002984?text=Hola');
  assert.equal(tp.urlSegura('javascript:alert(1)'), '');
  assert.equal(tp.urlSegura('http://automatizatech.cl'), '');
  assert.equal(tp.urlSegura('https://x.cl/"><script>'), '');
  assert.equal(tp.urlSegura(''), '');
  assert.equal(tp.urlSegura(null), '');
});
```

- [ ] **Step 2: Correr y ver fallar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/template-plan.test.js
```

Salida esperada: `TypeError: tp.lunesDe is not a function` (y lo mismo con las otras ayudas):

```text
is not a function
ℹ tests 5
ℹ pass 0
ℹ fail 5
```

- [ ] **Step 3: Implementar las fechas y las ayudas**

Insertar en `renderer/src/template-plan.js`, justo antes de la línea `// === Ayudas ===` (con una línea en blanco antes
del marcador):

```js
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const DIA_MS = 86400000;

// Las fechas llegan como 'YYYY-MM-DD' y se leen en UTC: así el resultado no depende de la zona horaria
// del servidor (Easypanel corre en UTC, un equipo en Chile no). Una fecha imposible (2026-02-30) es null.
function leerFecha(fecha) {
  if (typeof fecha !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(fecha)) return null;
  const d = new Date(`${fecha}T00:00:00Z`);
  if (Number.isNaN(d.getTime()) || d.toISOString().slice(0, 10) !== fecha) return null;
  return d;
}

function lunesDe(fecha) {
  const d = leerFecha(fecha);
  if (!d) return '';
  const desdeLunes = (d.getUTCDay() + 6) % 7; // 0 = lunes … 6 = domingo
  return new Date(d.getTime() - desdeLunes * DIA_MS).toISOString().slice(0, 10);
}

// Días de calendario de `desde` a `hasta` (negativo si `hasta` es anterior); NaN si alguna no es fecha.
function diasEntre(desde, hasta) {
  const a = leerFecha(desde);
  const b = leerFecha(hasta);
  if (!a || !b) return NaN;
  return Math.round((b.getTime() - a.getTime()) / DIA_MS);
}

// Semana (0 = la del lunes de inicio) en que cae `fecha`; las semanas van de lunes a domingo.
function semanaIndice(fecha, lunesInicio) {
  const n = diasEntre(lunesInicio, fecha);
  return Number.isNaN(n) ? NaN : Math.floor(n / 7);
}

function fechaLarga(fecha) {
  const d = leerFecha(fecha);
  return d ? `${d.getUTCDate()} de ${MESES[d.getUTCMonth()]} de ${d.getUTCFullYear()}` : '';
}

function fechaCorta(fecha) {
  const d = leerFecha(fecha);
  return d ? `${d.getUTCDate()} ${MESES_CORTOS[d.getUTCMonth()]}` : '';
}

function rangoCorto(desde, hasta) {
  const a = leerFecha(desde);
  const b = leerFecha(hasta);
  if (!a) return '';
  if (!b || desde === hasta) return fechaCorta(desde);
  if (a.getUTCFullYear() === b.getUTCFullYear() && a.getUTCMonth() === b.getUTCMonth()) {
    return `${a.getUTCDate()} – ${fechaCorta(hasta)}`;
  }
  return `${fechaCorta(desde)} – ${fechaCorta(hasta)}`;
}
```

Insertar justo antes de la línea `// === Carta Gantt ===`:

```js
const RESPONSABLES = { at: 'AutomatizaTech', cliente: 'Tú', ambos: 'Ambos' };
const FASES = { diseno_desarrollo: 'Diseño y desarrollo', implementacion: 'Implementación', soporte: 'Soporte y mejora continua' };

// Un responsable desconocido se muestra como AutomatizaTech: WordPress ya los valida, esto es la red.
function responsableClave(r) {
  return Object.prototype.hasOwnProperty.call(RESPONSABLES, r) ? r : 'at';
}

function diasTexto(n) {
  const k = Math.max(0, Math.round(Number(n) || 0));
  return k === 1 ? '1 día hábil' : `${k} días hábiles`;
}

function numero(n) {
  return String(n).padStart(2, '0');
}

// Solo enlaces https sin comillas ni espacios: un portal_url o un enlace de agenda raro (javascript:,
// http:, vacío) no se vuelve un enlace; la lámina sale igual, sin él.
function urlSegura(url) {
  const u = typeof url === 'string' ? url.trim() : '';
  return /^https:\/\/[^\s"'<>]+$/.test(u) ? u : '';
}

function texto(v) {
  return typeof v === 'string' ? v : v === null || v === undefined ? '' : String(v);
}

// Fondo de las láminas de contenido: la foto queda a la derecha y el texto sobre la parte oscura. Sin
// foto (vista previa del borrador), el mismo degradado de marca que la propuesta.
function fondoContenido(imageUrl, index) {
  if (!imageUrl) return backgroundStyle('', index);
  return `background-image: linear-gradient(100deg, rgba(13,27,42,.97) 0%, rgba(13,27,42,.95) 55%, rgba(13,27,42,.7) 74%, rgba(13,27,42,.32) 100%), url('${escapeHtml(
    imageUrl
  )}'); background-size: cover; background-position: center;`;
}

// Fondo de las láminas anchas (Método y carta Gantt): la foto apenas se asoma, porque el contenido ocupa
// todo el ancho y tiene que leerse sin pelear con ella.
function fondoPanel(imageUrl, index) {
  if (!imageUrl) return backgroundStyle('', index);
  return `background-image: linear-gradient(180deg, rgba(10,20,32,.88) 0%, rgba(10,20,32,.94) 100%), url('${escapeHtml(
    imageUrl
  )}'); background-size: cover; background-position: center;`;
}
```

Reemplazar la última línea del archivo:

```js
module.exports = { renderPlanHtml };
```

por:

```js
module.exports = { renderPlanHtml, lunesDe, semanaIndice, diasEntre, fechaLarga, fechaCorta, rangoCorto, diasTexto, urlSegura };
```

- [ ] **Step 4: Correr y ver pasar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/template-plan.test.js
```

```text
ℹ tests 5
ℹ pass 5
ℹ fail 0
```

- [ ] **Step 5: Commit**

```bash
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer add renderer/src/template-plan.js renderer/test/template-plan.test.js
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer commit -m "feat(renderer): fechas en español y ayudas del plan de trabajo" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

#### Ciclo B — carta Gantt

- [ ] **Step 6: Escribir las pruebas de la carta Gantt (incluida la larga: 14 semanas, 16 barras)**

Agregar al final de `renderer/test/template-plan.test.js` (una línea en blanco antes):

```js
// --- Carta Gantt -------------------------------------------------------------

test('la revisión del cliente va en la fila del bloque que revisa: 16 barras son 10 filas', () => {
  const grupos = tp.filasGantt(planLargo().cronograma);
  assert.deepEqual(
    grupos.map((g) => [g.titulo, g.filas.length]),
    [
      ['Diseño y desarrollo', 6],
      ['Implementación', 2],
      ['Soporte y mejora continua', 2],
    ]
  );
  const diseno = grupos[0].filas[1];
  assert.equal(diseno.trabajo.etiqueta, 'Diseño');
  assert.equal(diseno.revision.desde, '2026-10-21');
  assert.equal(grupos[0].filas[0].revision, null, 'el arranque no tiene revisión');
});

test('una revisión sin bloque previo queda en su propia fila y las barras rotas se ignoran', () => {
  const grupos = tp.filasGantt({
    barras: [
      { fase: 'soporte', etiqueta: 'Tu revisión', tipo: 'revision', responsable: 'cliente', desde: '2026-10-05', hasta: '2026-10-09' },
      { fase: 'soporte', etiqueta: 'Rota', tipo: 'trabajo', responsable: 'at', desde: '2026-10-09', hasta: '2026-10-05' },
      { fase: 'soporte', etiqueta: 'Sin fecha', tipo: 'trabajo', responsable: 'at', desde: '', hasta: '2026-10-05' },
      null,
    ],
  });
  assert.equal(grupos.length, 1);
  assert.equal(grupos[0].filas.length, 1);
  assert.equal(grupos[0].filas[0].trabajo, null);
  assert.equal(grupos[0].filas[0].revision.desde, '2026-10-05');
});

test('la carta larga (14 semanas, 16 barras) usa filas altas y todo cabe en su alto', () => {
  const { fila, denso } = tp.medidasGantt(10, 3, true);
  assert.equal(fila, 48);
  assert.equal(denso, false);
  // Con cualquier cantidad de filas, filas + títulos de fase + fila de hitos caben en los 660 px.
  for (const n of [1, 10, 16, 25, 40, 80]) {
    const m = tp.medidasGantt(n, 3, true);
    assert.ok(n * m.fila + 3 * 34 + 72 <= 660, `${n} filas se salen`);
  }
  assert.equal(tp.medidasGantt(25, 3, true).denso, true);
});

test('renderGantt dibuja 14 semanas, 16 barras, los hitos y la leyenda', () => {
  const html = tp.renderGantt(planLargo().cronograma);
  assert.equal(contar(html, '<span class="gantt-semana">'), 14);
  assert.ok(html.includes('<b>S1</b><small>5 oct</small>'));
  assert.ok(html.includes('<b>S14</b><small>4 ene</small>'));
  assert.equal(contar(html, 'class="gantt-barra '), 16);
  assert.equal(contar(html, 'class="gantt-barra is-revision"'), 6);
  assert.equal(contar(html, '<div class="gantt-fila">'), 10);
  assert.equal(contar(html, '<div class="gantt-fase">'), 3);
  assert.ok(html.includes('style="--semanas:14;--fila:48px"'));
  assert.equal(contar(html, 'class="gantt-hito'), 3);
  assert.ok(html.includes('Entrega estimada · 23 dic'));
  // «Plataforma aprobada» (18 dic, fin del día 75 de 98) y «Entrega estimada» (23 dic, día 80) están pegadas:
  // van en carriles distintos, y la entrega se etiqueta a la izquierda porque a la derecha no cabe.
  assert.ok(html.includes('<span class="gantt-hito is-arriba" style="left:76.531%"><i></i><em>Plataforma aprobada · 18 dic</em>'));
  assert.ok(html.includes('<span class="gantt-hito is-entrega is-abajo is-izq" style="left:81.633%">'));
  for (const leyenda of ['AutomatizaTech', 'Tú', 'Ambos', 'Tu revisión (5 días hábiles)', 'Hito']) {
    assert.ok(html.includes(`</i>${leyenda}</span>`), `falta la leyenda ${leyenda}`);
  }
  assert.ok(html.includes('Fechas estimadas, desde que recibimos el anticipo y tus insumos.'));
});

test('tres hitos pegados no se pisan: el tercero se corre a la derecha de los otros dos', () => {
  const c = planCorto().cronograma;
  c.hitos = [
    { nombre: 'Diseño aprobado', fecha: '2026-10-21' },
    { nombre: 'Textos aprobados', fecha: '2026-10-22' },
    { nombre: 'Fotos aprobadas', fecha: '2026-10-23' },
  ];
  const html = tp.renderGantt(c);
  const hitos = html.match(/<span class="gantt-hito[^>]*>/g);
  assert.equal(hitos[0], '<span class="gantt-hito is-arriba" style="left:40.476%">');
  assert.equal(hitos[1], '<span class="gantt-hito is-abajo" style="left:42.857%">');
  assert.ok(/^<span class="gantt-hito is-arriba" style="left:45.238%;--corrido:\d+px">$/.test(hitos[2]), hitos[2]);
});

test('las barras se ubican por día de calendario dentro de la grilla semanal', () => {
  const html = tp.renderGantt(planCorto().cronograma);
  // 6 semanas = 42 días. Arranque: lunes 5 a jueves 8 de octubre = días 0 a 3.
  assert.ok(html.includes('class="gantt-barra is-ambos" style="left:0%;width:9.524%"'));
  // Primera revisión: jueves 15 a miércoles 21 = días 10 a 16.
  assert.ok(html.includes('class="gantt-barra is-revision" style="left:23.81%;width:16.667%"'));
});

test('la carta se achica (is-denso) cuando hay muchas filas y marca las semanas largas', () => {
  const barras = Array.from({ length: 30 }, (_, i) => ({
    fase: 'diseno_desarrollo',
    etiqueta: `Bloque ${i + 1}`,
    tipo: 'trabajo',
    responsable: 'at',
    desde: tp.lunesDe('2026-10-05'),
    hasta: '2026-10-09',
  }));
  const html = tp.renderGantt({ inicio: '2026-10-05', fin: '2027-04-30', barras, hitos: [] });
  assert.ok(html.includes('class="gantt is-denso is-largo"'));
  assert.equal(contar(html, '<span class="gantt-semana">'), 30);
  assert.ok(html.includes('<b>S30</b><small>26/4</small>'));
});

test('sin barras la carta muestra un aviso y no se rompe', () => {
  const html = tp.renderGantt({ inicio: '2026-10-05', fin: '2026-10-09', barras: [], hitos: [] });
  assert.ok(html.includes('Las fechas aparecen aquí cuando el plan tenga actividades.'));
  assert.doesNotThrow(() => tp.renderGantt(undefined));
});

test('los textos de la carta se escapan', () => {
  const c = planCorto().cronograma;
  c.barras[1].etiqueta = 'Diseño <script>alert(1)</script>';
  c.hitos[0].nombre = 'Hito <b>x</b>';
  const html = tp.renderGantt(c);
  assert.ok(!html.includes('<script>alert(1)'));
  assert.ok(html.includes('Diseño &lt;script&gt;alert(1)&lt;/script&gt;'));
  assert.ok(html.includes('Hito &lt;b&gt;x&lt;/b&gt;'));
});

test('la lámina de la carta dice inicio, entrega estimada y duración', () => {
  const html = tp.renderGanttSlide(planLargo(), 3, '');
  assert.ok(html.includes('03 · Carta Gantt'));
  assert.ok(html.includes('<b>5 de octubre de 2026</b>'));
  assert.ok(html.includes('<b>23 de diciembre de 2026</b>'));
  assert.ok(html.includes('<b>14 semanas</b>'));
});
```

- [ ] **Step 7: Correr y ver fallar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/template-plan.test.js
```

Salida esperada: las 10 nuevas fallan con `tp.filasGantt is not a function` (y `medidasGantt`, `renderGantt`,
`renderGanttSlide`); las 5 del ciclo A siguen pasando:

```text
is not a function
ℹ tests 15
ℹ pass 5
ℹ fail 10
```

- [ ] **Step 8: Implementar la carta Gantt**

Insertar en `renderer/src/template-plan.js`, justo antes de la línea `// === Láminas ===`:

```js
// Alto (px) que la carta tiene para filas dentro de la lámina de 1920×1080, después del título, la
// cabecera de semanas y la leyenda. Se reparte entre las filas: con pocas barras cada fila es alta y
// cómoda; con muchas se achica hasta caber. Nunca se sale de la lámina, ni en pantalla ni en el PDF.
const GANTT_ALTO = 660;
const GANTT_ALTO_FASE = 34;
const GANTT_ALTO_HITOS = 72;
// Ancho aproximado (px) de la pista de barras y de una letra de la etiqueta de un hito (16 px, negrita).
const GANTT_PISTA = 1300;
const GANTT_LETRA = 9.5;

function medidasGantt(nFilas, nFases, conHitos) {
  const libre = GANTT_ALTO - nFases * GANTT_ALTO_FASE - (conHitos ? GANTT_ALTO_HITOS : 0);
  const fila = Math.max(1, Math.min(52, Math.floor(libre / Math.max(1, nFilas))));
  return { fila, denso: fila < 34 };
}

// Una fila por bloque de trabajo. La revisión del cliente va en la MISMA fila que el bloque que revisa
// (el de su fase que terminó justo antes de que ella empiece), así 16 barras son 10 filas y no 16. Una
// revisión sin bloque previo queda en su propia fila. Las barras con fechas inválidas se ignoran.
function filasGantt(cronograma) {
  const barras = (cronograma && Array.isArray(cronograma.barras) ? cronograma.barras : []).filter(
    (b) => b && typeof b === 'object' && leerFecha(b.desde) && leerFecha(b.hasta) && b.hasta >= b.desde
  );
  const trabajos = [];
  const sueltas = [];
  barras.forEach((b, orden) => {
    if (b.tipo !== 'revision') trabajos.push({ fase: String(b.fase || ''), trabajo: b, revision: null, orden });
  });
  barras.forEach((b, orden) => {
    if (b.tipo !== 'revision') return;
    let elegido = null;
    for (const f of trabajos) {
      if (f.fase !== String(b.fase || '') || f.revision || f.trabajo.hasta >= b.desde) continue;
      if (!elegido || f.trabajo.hasta > elegido.trabajo.hasta) elegido = f;
    }
    if (elegido) elegido.revision = b;
    else sueltas.push({ fase: String(b.fase || ''), trabajo: null, revision: b, orden });
  });
  const filas = trabajos.concat(sueltas).sort((a, b) => a.orden - b.orden);
  const grupos = [];
  for (const f of filas) {
    let g = grupos.find((x) => x.fase === f.fase);
    if (!g) {
      g = { fase: f.fase, titulo: FASES[f.fase] || f.fase || 'Otras actividades', filas: [] };
      grupos.push(g);
    }
    g.filas.push(f);
  }
  return grupos;
}

function porcentaje(x) {
  return `${+(Math.max(0, Math.min(1, x)) * 100).toFixed(3)}%`;
}

// Posición de una barra por días de calendario: un bloque de lunes a viernes cubre 5/7 de su semana y
// el fin de semana queda a la vista como el hueco entre barras.
function posicion(desde, hasta, lunes0, totalDias) {
  const ini = Math.max(0, Math.min(totalDias, diasEntre(lunes0, desde)));
  const fin = Math.max(ini, Math.min(totalDias, diasEntre(lunes0, hasta) + 1));
  return `left:${porcentaje(ini / totalDias)};width:${porcentaje(Math.max(fin - ini, 0.35) / totalDias)}`;
}

// Reparte las etiquetas de los hitos en dos carriles (arriba y abajo del rombo) para que no se pisen: cada
// etiqueta va al primer carril libre en su tramo; si no cabe a la derecha del rombo, va a su izquierda. Si
// no queda carril libre (tres hitos pegados), se corre a la derecha hasta donde termina la anterior.
function carrilesHitos(hitos, xs) {
  const fin = [-Infinity, -Infinity];
  return hitos.map((h, i) => {
    const ancho = ((texto(h.nombre).length + 9) * GANTT_LETRA) / GANTT_PISTA;
    const izq = xs[i] + ancho + 0.012 > 1;
    const desde = izq ? xs[i] - ancho : xs[i];
    const carril = fin[0] <= desde ? 0 : fin[1] <= desde ? 1 : fin[0] <= fin[1] ? 0 : 1;
    const corrido = izq ? 0 : Math.max(0, Math.min(fin[carril] + 0.008 - desde, 1 - ancho - desde));
    fin[carril] = Math.max(fin[carril], izq ? xs[i] : desde + corrido + ancho);
    return { carril, izq, corrido: Math.round(corrido * GANTT_PISTA) };
  });
}

function renderGantt(cronograma) {
  const c = cronograma && typeof cronograma === 'object' ? cronograma : {};
  const lunes0 = lunesDe(c.inicio);
  const ultima = semanaIndice(c.fin, lunes0);
  const semanas = Number.isNaN(ultima) || ultima < 0 ? 1 : ultima + 1;
  const totalDias = semanas * 7;
  const grupos = lunes0 ? filasGantt(c) : [];
  const hitos = lunes0
    ? (Array.isArray(c.hitos) ? c.hitos : [])
        .filter((h) => h && h.nombre && leerFecha(h.fecha))
        .sort((a, b) => (a.fecha < b.fecha ? -1 : a.fecha > b.fecha ? 1 : 0))
    : [];
  const nFilas = grupos.reduce((n, g) => n + g.filas.length, 0);
  const { fila, denso } = medidasGantt(nFilas, grupos.length, hitos.length > 0);
  const largo = semanas > 18;

  const cabecera = Array.from({ length: semanas }, (_, i) => {
    const d = lunes0 ? new Date(leerFecha(lunes0).getTime() + i * 7 * DIA_MS) : null;
    const fecha = !d ? '' : largo ? `${d.getUTCDate()}/${d.getUTCMonth() + 1}` : fechaCorta(d.toISOString().slice(0, 10));
    return `<span class="gantt-semana"><b>S${i + 1}</b><small>${escapeHtml(fecha)}</small></span>`;
  }).join('');

  const hitoX = (h) => (diasEntre(lunes0, h.fecha) + 1) / totalDias;
  const guias = hitos
    .map((h) => `<span class="gantt-guia${h.nombre === 'Entrega estimada' ? ' is-entrega' : ''}" style="left:${porcentaje(hitoX(h))}"></span>`)
    .join('');

  const cuerpo = grupos
    .map((g) => {
      const filasHtml = g.filas
        .map((f) => {
          const principal = f.trabajo || f.revision;
          const nombre = f.trabajo ? texto(f.trabajo.etiqueta) || 'Actividad' : 'Tu revisión';
          const rango = rangoCorto(principal.desde, (f.revision || principal).hasta);
          const barrasHtml = [];
          if (f.trabajo) {
            const r = responsableClave(f.trabajo.responsable);
            const titulo = `${nombre} · ${RESPONSABLES[r]} · ${rangoCorto(f.trabajo.desde, f.trabajo.hasta)}`;
            barrasHtml.push(
              `<span class="gantt-barra is-${r}" style="${posicion(f.trabajo.desde, f.trabajo.hasta, lunes0, totalDias)}" title="${escapeHtml(titulo)}"></span>`
            );
          }
          if (f.revision) {
            const titulo = `Tu revisión · ${rangoCorto(f.revision.desde, f.revision.hasta)}`;
            barrasHtml.push(
              `<span class="gantt-barra is-revision" style="${posicion(f.revision.desde, f.revision.hasta, lunes0, totalDias)}" title="${escapeHtml(titulo)}"></span>`
            );
          }
          const punto = f.trabajo ? responsableClave(f.trabajo.responsable) : 'revision';
          return `<div class="gantt-fila"><div class="gantt-etiqueta"><i class="gantt-punto is-${punto}"></i><b title="${escapeHtml(nombre)}">${escapeHtml(
            nombre
          )}</b><small>${escapeHtml(rango)}</small></div><div class="gantt-pista">${barrasHtml.join('')}</div></div>`;
        })
        .join('');
      return `<div class="gantt-fase">${escapeHtml(g.titulo)}</div>${filasHtml}`;
    })
    .join('');

  const xs = hitos.map(hitoX);
  const carriles = carrilesHitos(hitos, xs);
  const hitosHtml = hitos.length
    ? `<div class="gantt-fila gantt-fila-hitos"><div class="gantt-etiqueta"><b>Hitos</b></div><div class="gantt-pista">${hitos
        .map((h, i) => {
          const x = xs[i];
          const { carril, izq, corrido } = carriles[i];
          const clases = ['gantt-hito', h.nombre === 'Entrega estimada' ? 'is-entrega' : '', carril ? 'is-abajo' : 'is-arriba', izq ? 'is-izq' : '']
            .filter(Boolean)
            .join(' ');
          const estilo = `left:${porcentaje(x)}${corrido ? `;--corrido:${corrido}px` : ''}`;
          return `<span class="${clases}" style="${estilo}"><i></i><em>${escapeHtml(texto(h.nombre))} · ${escapeHtml(fechaCorta(h.fecha))}</em></span>`;
        })
        .join('')}</div></div>`
    : '';

  const vacio = nFilas ? '' : '<p class="gantt-vacio">Las fechas aparecen aquí cuando el plan tenga actividades.</p>';

  return `<div class="gantt${denso ? ' is-denso' : ''}${largo ? ' is-largo' : ''}" style="--semanas:${semanas};--fila:${fila}px">
      <div class="gantt-cabecera"><span class="gantt-esquina">Semana</span><div class="gantt-semanas">${cabecera}</div></div>
      <div class="gantt-cuerpo">
        <div class="gantt-rejilla" aria-hidden="true">${guias}</div>
        ${cuerpo}${hitosHtml}${vacio}
      </div>
      <div class="gantt-pie">
        <span class="gantt-leyenda"><i class="gantt-punto is-at"></i>AutomatizaTech</span>
        <span class="gantt-leyenda"><i class="gantt-punto is-cliente"></i>Tú</span>
        <span class="gantt-leyenda"><i class="gantt-punto is-ambos"></i>Ambos</span>
        <span class="gantt-leyenda"><i class="gantt-punto is-revision"></i>Tu revisión (5 días hábiles)</span>
        <span class="gantt-leyenda"><i class="gantt-rombo"></i>Hito</span>
        <span class="gantt-nota">Fechas estimadas, desde que recibimos el anticipo y tus insumos.</span>
      </div>
    </div>`;
}

function renderGanttSlide(data, index, imageUrl) {
  const c = data && data.cronograma && typeof data.cronograma === 'object' ? data.cronograma : {};
  const entrega = (Array.isArray(c.hitos) ? c.hitos : []).find((h) => h && h.nombre === 'Entrega estimada' && leerFecha(h.fecha));
  const lunes0 = lunesDe(c.inicio);
  const ultima = semanaIndice(c.fin, lunes0);
  const semanas = Number.isNaN(ultima) || ultima < 0 ? 0 : ultima + 1;
  const resumen = [
    leerFecha(c.inicio) ? `<span><small>Inicio estimado</small><b>${escapeHtml(fechaLarga(c.inicio))}</b></span>` : '',
    entrega ? `<span><small>Entrega estimada</small><b>${escapeHtml(fechaLarga(entrega.fecha))}</b></span>` : '',
    semanas ? `<span><small>Duración</small><b>${semanas} ${semanas === 1 ? 'semana' : 'semanas'}</b></span>` : '',
  ].join('');
  return `
    <section class="slide plan-panel plan-gantt">
      <div class="slide-bg" style="${fondoPanel(imageUrl, index)}"></div>
      ${logoMark()}
      <div class="panel-head plan-anima">
        <p class="eyebrow">${numero(index)} · Carta Gantt</p>
        <h2>Tu proyecto, semana a semana</h2>
      </div>
      <div class="panel-resumen">${resumen}</div>
      ${renderGantt(c)}
    </section>`;
}

const ESTILO_GANTT = `
  .panel-head { position: absolute; left: 300px; top: 52px; right: 800px; z-index: 2; }
  .panel-head h2 { color: #fff; font-size: 44px; font-weight: 800; margin-top: 8px; }
  .panel-resumen { position: absolute; top: 60px; right: 72px; z-index: 2; display: flex; gap: 34px; }
  .panel-resumen span { display: flex; flex-direction: column; gap: 4px; text-align: right; }
  .panel-resumen small { color: #9fb3c8; font-size: 15px; letter-spacing: .1em; text-transform: uppercase; }
  .panel-resumen b { color: #fff; font-size: 24px; font-weight: 700; }
  .plan-gantt .gantt { position: absolute; top: 196px; left: 72px; right: 72px; max-height: 844px; z-index: 2;
    display: flex; flex-direction: column; background: rgba(6,13,21,.72); border: 1px solid rgba(255,255,255,.1);
    border-radius: 18px; padding: 20px 26px 16px; }
  .gantt { --col: 420px; }
  .gantt-cabecera { display: flex; align-items: flex-end; height: 56px; flex: none; border-bottom: 1px solid rgba(255,255,255,.14); }
  .gantt-esquina { width: var(--col); flex: none; color: #9fb3c8; font-size: 15px; letter-spacing: .12em; text-transform: uppercase; padding-bottom: 10px; }
  .gantt-semanas { flex: 1; display: grid; grid-template-columns: repeat(var(--semanas), 1fr); min-width: 0; }
  .gantt-semana { display: flex; flex-direction: column; align-items: center; gap: 2px; padding-bottom: 8px; min-width: 0; overflow: hidden; }
  .gantt-semana b { color: #fff; font-size: 17px; font-weight: 700; }
  .gantt-semana small { color: #9fb3c8; font-size: 14px; white-space: nowrap; }
  .gantt.is-largo .gantt-semana b { font-size: 13px; }
  .gantt.is-largo .gantt-semana small { font-size: 11px; }
  .gantt-cuerpo { position: relative; flex: none; }
  .gantt-rejilla { position: absolute; top: 0; bottom: 0; left: var(--col); right: 0; pointer-events: none;
    background-image: linear-gradient(to right, rgba(255,255,255,.1) 0 1px, transparent 1px 71.4286%, rgba(255,255,255,.035) 71.4286% 100%);
    background-size: calc(100% / var(--semanas)) 100%; }
  .gantt-guia { position: absolute; top: 0; bottom: 0; width: 0; border-left: 2px dashed rgba(255,255,255,.28); }
  .gantt-guia.is-entrega { border-left-color: rgba(0,217,192,.75); }
  .gantt-fase { position: relative; height: 34px; display: flex; align-items: flex-end; padding-bottom: 5px; color: #00d9c0;
    font-size: 15px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
  .gantt-fila { position: relative; display: flex; height: var(--fila); }
  .gantt-etiqueta { width: var(--col); flex: none; display: flex; align-items: center; gap: 12px; padding-right: 16px; min-width: 0; overflow: hidden; }
  .gantt-etiqueta b { color: #fff; font-size: clamp(11px, calc(var(--fila) * .42), 21px); font-weight: 700; white-space: nowrap; overflow: hidden;
    text-overflow: ellipsis; min-width: 0; }
  .gantt-etiqueta small { margin-left: auto; flex: none; color: #9fb3c8; font-size: clamp(10px, calc(var(--fila) * .33), 16px); white-space: nowrap; }
  .gantt.is-denso .gantt-etiqueta small { display: none; }
  .gantt-pista { position: relative; flex: 1; min-width: 0; }
  .gantt-barra { position: absolute; top: 50%; height: max(4px, calc(var(--fila) * .56)); transform: translateY(-50%); border-radius: 7px; min-width: 4px; }
  .gantt-barra.is-at, .gantt-punto.is-at { background: #00d9c0; }
  .gantt-barra.is-cliente, .gantt-punto.is-cliente { background: #38bdf8; }
  .gantt-barra.is-ambos, .gantt-punto.is-ambos { background: #a78bfa; }
  .gantt-barra.is-revision, .gantt-punto.is-revision {
    background: repeating-linear-gradient(135deg, rgba(245,158,11,.95) 0 7px, rgba(245,158,11,.4) 7px 14px); box-shadow: inset 0 0 0 2px #f59e0b; }
  .gantt-punto { flex: none; width: 14px; height: 14px; border-radius: 4px; display: inline-block; }
  .gantt-fila-hitos { height: 72px; border-top: 1px solid rgba(255,255,255,.14); }
  .gantt-fila-hitos .gantt-etiqueta b { font-size: 19px; }
  .gantt-hito { position: absolute; top: 50%; width: 0; height: 0; }
  .gantt-hito i { position: absolute; left: -9px; top: -9px; width: 18px; height: 18px; transform: rotate(45deg); background: #0a1420; border: 3px solid #fff; }
  .gantt-hito.is-entrega i { background: #00d9c0; border-color: #00d9c0; box-shadow: 0 0 0 5px rgba(0,217,192,.22); }
  .gantt-hito em { position: absolute; left: calc(14px + var(--corrido, 0px)); font-style: normal; font-size: 16px; font-weight: 700; color: #fff; white-space: nowrap;
    background: #0a1520; padding: 1px 6px; border-radius: 5px; }
  .gantt-hito.is-arriba em { bottom: 14px; }
  .gantt-hito.is-abajo em { top: 14px; }
  .gantt-hito.is-izq em { left: auto; right: 14px; }
  .gantt-hito.is-entrega em { color: #00d9c0; }
  .gantt-rombo { flex: none; width: 13px; height: 13px; transform: rotate(45deg); border: 2px solid #fff; display: inline-block; }
  .gantt-vacio { color: #c9d4e0; font-size: 22px; padding: 40px 0; }
  .gantt-pie { flex: none; display: flex; align-items: center; flex-wrap: wrap; gap: 10px 26px; margin-top: 14px; padding-top: 12px;
    border-top: 1px solid rgba(255,255,255,.14); }
  .gantt-leyenda { display: inline-flex; align-items: center; gap: 9px; color: #c9d4e0; font-size: 17px; }
  .gantt-nota { margin-left: auto; color: #9fb3c8; font-size: 16px; font-style: italic; }
`;
```

Reemplazar la última línea del archivo:

```js
module.exports = { renderPlanHtml, lunesDe, semanaIndice, diasEntre, fechaLarga, fechaCorta, rangoCorto, diasTexto, urlSegura };
```

por:

```js
module.exports = { renderPlanHtml, lunesDe, semanaIndice, diasEntre, fechaLarga, fechaCorta, rangoCorto, diasTexto, urlSegura, filasGantt, medidasGantt, renderGantt, renderGanttSlide };
```

- [ ] **Step 9: Correr y ver pasar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/template-plan.test.js
```

```text
ℹ tests 15
ℹ pass 15
ℹ fail 0
```

- [ ] **Step 10: Commit**

```bash
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer add renderer/src/template-plan.js renderer/test/template-plan.test.js
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer commit -m "feat(renderer): carta Gantt por semanas que cabe siempre en la lámina" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

#### Ciclo C — Método AT y láminas de fase

- [ ] **Step 11: Escribir las pruebas del Método AT y de las fases**

Agregar al final de `renderer/test/template-plan.test.js`:

```js
// --- Método AT y fases -------------------------------------------------------

test('el Método AT marca Diagnóstico y Priorización hechos y «Estás aquí» en Propuesta por fases', () => {
  const html = tp.renderMetodoSlide(planCorto(), 2, '');
  assert.ok(html.includes('02 · El Método AT'));
  assert.equal(contar(html, 'class="metodo-paso '), 6);
  assert.equal(contar(html, 'class="metodo-paso is-hecha"'), 2);
  assert.equal(contar(html, 'Estás aquí'), 1);
  const actual = html.match(/<li class="metodo-paso is-actual">[\s\S]*?<\/li>/)[0];
  assert.ok(actual.includes('<h3>Propuesta por fases</h3>'));
  assert.ok(actual.includes('Cerrada con tu firma del 28 de septiembre de 2026'));
  assert.equal(contar(html, 'class="metodo-paso is-proxima"'), 3);
  assert.ok(html.includes('Lo que viene: Diseño y desarrollo → Implementación → Soporte y mejora continua.'));
});

test('sin fecha de firma ni datos del método, la lámina igual sale bien', () => {
  const html = tp.renderMetodoSlide({}, 2, '');
  assert.equal(contar(html, 'Estás aquí'), 1);
  assert.ok(html.includes('Cerrada con tu firma del contrato'));
});

test('paginarBloques deja una fase larga en dos láminas y una corta en una', () => {
  const [fase1] = planLargo().fases;
  const paginas = tp.paginarBloques(fase1.bloques, fase1.descripcion);
  assert.deepEqual(
    paginas.map((p) => p.map((b) => b.nombre)),
    [['Arranque', 'Diseño', 'Núcleo de la plataforma', 'Agenda y pagos'], ['Integraciones', 'Pruebas y revisión']]
  );
  assert.equal(tp.paginarBloques(planCorto().fases[0].bloques, planCorto().fases[0].descripcion).length, 1);
  assert.deepEqual(tp.paginarBloques(undefined), [[]]);
});

test('paginarBloques deja a lo más 5 entregas por lámina, para que «Qué aprobamos juntos» quepa', () => {
  // El peor caso de la Task 3: Arranque + 13 bloques con entrega de una actividad cada uno. Por alto de la
  // tabla caben 7 en la segunda lámina, pero la tarjeta de entregas se saldría por abajo.
  const bloques = [
    { nombre: 'Arranque', entrega: false, actividades: [{ nombre: 'Reunión de inicio' }, { nombre: 'Entrega de logo, textos y accesos' }] },
  ];
  for (let i = 1; i <= 13; i++) {
    bloques.push({ nombre: `Etapa ${i}`, entregable: `Módulo ${i}`, entrega: true, actividades: [{ nombre: `Trabajo ${i}` }] });
  }
  const paginas = tp.paginarBloques(bloques, 'Descripción corta de la fase.');
  assert.deepEqual(
    paginas.map((p) => p.filter((b) => b.entrega).length),
    [5, 5, 3]
  );
  assert.equal(paginas.flat().length, 14);
});

test('dos bloques con el mismo nombre en una fase muestran cada uno su propia revisión', () => {
  const plan = planCorto();
  // El bloque «Desarrollo» pasa a llamarse igual que el anterior («Diseño»), en la fase y en su barra.
  plan.fases[0].bloques[2].nombre = 'Diseño';
  plan.cronograma.barras[3].etiqueta = 'Diseño';
  const fase = plan.fases[0];
  const [pagina] = tp.paginarBloques(fase.bloques, fase.descripcion);
  for (const bloques of [fase.bloques, pagina]) {
    const html = tp.renderFaseSlide(
      { fase, numeroFase: 1, totalFases: 3, bloques, continuacion: false, cronograma: plan.cronograma },
      4,
      ''
    );
    assert.ok(html.includes('<li><b>Diseño de la página</b><span>Tu revisión: 15 – 21 oct.</span></li>'));
    assert.ok(html.includes('<li><b>Sitio en ambiente de prueba</b><span>Tu revisión: 2 – 6 nov.</span></li>'));
  }
});

test('la lámina de una fase lista actividades con responsable, días y fechas, y qué aprobamos juntos', () => {
  const plan = planCorto();
  const html = tp.renderFaseSlide(
    { fase: plan.fases[0], numeroFase: 1, totalFases: 3, bloques: plan.fases[0].bloques, continuacion: false, cronograma: plan.cronograma },
    4,
    ''
  );
  assert.ok(html.includes('04 · Fase 1 de 3'));
  assert.ok(html.includes('<h2>Diseño y desarrollo</h2>'));
  assert.ok(html.includes('Diseñamos y construimos tu sitio de una página.'));
  assert.ok(html.includes('<td class="plan-act">Reunión de inicio</td><td><span class="quien is-ambos">Ambos</span></td><td>1 día hábil</td><td>5 oct</td>'));
  assert.ok(html.includes('<span class="quien is-cliente">Tú</span></td><td>3 días hábiles</td><td>6 – 8 oct</td>'));
  assert.ok(html.includes('<span class="quien is-at">AutomatizaTech</span>'));
  assert.ok(html.includes('<b>Diseño</b><span>Entrega: Diseño de la página</span>'));
  assert.ok(html.includes('Qué aprobamos juntos'));
  assert.ok(html.includes('<li><b>Diseño de la página</b><span>Tu revisión: 15 – 21 oct.</span></li>'));
  assert.ok(html.includes('<li><b>Sitio en ambiente de prueba</b><span>Tu revisión: 2 – 6 nov.</span></li>'));
  assert.ok(html.includes('cláusula 6.1 del contrato'));
});

test('una fase sin entregas lo dice, y la continuación no repite la descripción', () => {
  const plan = planLargo();
  const fase = plan.fases[2];
  const html = tp.renderFaseSlide(
    { fase, numeroFase: 3, totalFases: 3, bloques: fase.bloques, continuacion: true, cronograma: plan.cronograma },
    10,
    ''
  );
  assert.ok(html.includes('Soporte y mejora continua <span class="plan-cont">(continuación)</span>'));
  assert.ok(!html.includes('No desaparecemos: acompañamos'));
  assert.ok(html.includes('En esta parte no hay entregas que aprobar'));
});

test('las actividades en paralelo se marcan y la tabla se compacta con muchas filas', () => {
  const plan = planLargo();
  const [pagina1] = tp.paginarBloques(plan.fases[0].bloques, plan.fases[0].descripcion);
  const html = tp.renderFaseSlide(
    { fase: plan.fases[0], numeroFase: 1, totalFases: 3, bloques: pagina1, continuacion: false, cronograma: plan.cronograma },
    4,
    ''
  );
  assert.ok(html.includes('Módulo de pagos<em class="plan-paralelo">en paralelo</em>'));
  assert.ok(html.includes('<table class="plan-tabla is-muy-denso">'));
});

test('los textos de las fases se escapan', () => {
  const plan = planCorto();
  plan.fases[0].descripcion = 'Descripción <img src=x onerror=alert(1)>';
  plan.fases[0].bloques[0].actividades[0].nombre = 'Reunión <script>x</script>';
  const html = tp.renderFaseSlide(
    { fase: plan.fases[0], numeroFase: 1, totalFases: 3, bloques: plan.fases[0].bloques, continuacion: false, cronograma: plan.cronograma },
    4,
    ''
  );
  assert.ok(!html.includes('<img src=x'));
  assert.ok(!html.includes('<script>x'));
  assert.ok(html.includes('Reunión &lt;script&gt;x&lt;/script&gt;'));
});
```

- [ ] **Step 12: Correr y ver fallar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/template-plan.test.js
```

```text
is not a function
ℹ tests 24
ℹ pass 15
ℹ fail 9
```

- [ ] **Step 13: Implementar el Método AT y las láminas de fase**

Reemplazar la línea 1 de `renderer/src/template-plan.js`:

```js
const { escapeHtml } = require('./escape');
```

por:

```js
const { escapeHtml, linkify } = require('./escape');
```

Insertar justo antes de la línea `// === Documento ===`:

```js
// Mismas seis fases y los mismos textos que la sección «Método» de automatizatech.cl
// (assets/home-premium/body.html): el plan no puede contar el método distinto que la web.
const METODO = [
  { clave: 'diagnostico', titulo: 'Diagnóstico', texto: 'Revisamos tu negocio y detectamos dónde estás perdiendo tiempo y ventas.' },
  { clave: 'priorizacion', titulo: 'Priorización', texto: 'Definimos qué conviene implementar primero para tener impacto rápido.' },
  { clave: 'propuesta', titulo: 'Propuesta por fases', texto: 'Un plan claro, por etapas, sin sorpresas ni inversiones a ciegas.' },
  { clave: 'diseno_desarrollo', titulo: 'Diseño y desarrollo', texto: 'Construimos con diseño premium y tecnología sólida, pensada para crecer.' },
  { clave: 'implementacion', titulo: 'Implementación', texto: 'Lo dejamos funcionando y conectado a tu operación real, sin fricción.' },
  { clave: 'soporte', titulo: 'Soporte y mejora continua', texto: 'No desaparecemos. Medimos, ajustamos y seguimos optimizando contigo.' },
];

function renderMetodoSlide(data, index, imageUrl) {
  const m = data && data.metodo && typeof data.metodo === 'object' ? data.metodo : {};
  const hechas = new Set(Array.isArray(m.hechas) ? m.hechas : ['diagnostico', 'priorizacion']);
  const actual = typeof m.actual === 'string' && m.actual ? m.actual : 'propuesta';
  const firma = texto(data && data.fecha_firma_larga).trim();
  const pasos = METODO.map((p, i) => {
    const estado = p.clave === actual ? 'actual' : hechas.has(p.clave) ? 'hecha' : 'proxima';
    const marca = estado === 'hecha' ? '✓' : numero(i + 1);
    const extra =
      estado === 'actual'
        ? `<span class="metodo-aqui">Estás aquí</span><p class="metodo-firma">${
            firma ? `Cerrada con tu firma del ${escapeHtml(firma)}` : 'Cerrada con tu firma del contrato'
          }</p>`
        : `<p class="metodo-estado">${estado === 'hecha' ? 'Hecho' : 'Lo que viene'}</p>`;
    return `<li class="metodo-paso is-${estado}"><span class="metodo-marca">${marca}</span><h3>${escapeHtml(p.titulo)}</h3><p class="metodo-texto">${escapeHtml(
      p.texto
    )}</p>${extra}</li>`;
  }).join('');
  const proximas = METODO.filter((p) => p.clave !== actual && !hechas.has(p.clave)).map((p) => escapeHtml(p.titulo));
  return `
    <section class="slide plan-panel plan-metodo">
      <div class="slide-bg" style="${fondoPanel(imageUrl, index)}"></div>
      ${logoMark()}
      <div class="panel-head plan-anima">
        <p class="eyebrow">${numero(index)} · El Método AT</p>
        <h2>Dónde estamos en tu proyecto</h2>
      </div>
      <ol class="metodo">${pasos}</ol>
      ${proximas.length ? `<p class="metodo-pie">Lo que viene: ${proximas.join(' → ')}. Este plan detalla esas fases.</p>` : ''}
    </section>`;
}

// Alto (px) de cada fila de la tabla de una fase según la densidad, medido en Chrome a 1920×1080 con
// nombres y detalles en una sola línea (normal 59,5/51/+23; denso 49,5/41/+23; muy denso 39,5/32) y
// redondeado hacia arriba. La tabla tiene ALTO_TABLA px bajo el título, menos lo que ocupe la descripción.
// Con eso se reparte la fase en láminas y se elige la letra más grande que cabe: nada se sale por abajo,
// ni en pantalla ni en el PDF.
const FILAS_FASE = {
  normal: { clase: '', bloque: 62, act: 53, detalle: 25 },
  denso: { clase: ' is-denso', bloque: 52, act: 43, detalle: 24 },
  muyDenso: { clase: ' is-muy-denso', bloque: 40, act: 33, detalle: 0 },
};
const ALTO_TABLA = 560;
// La tarjeta «Qué aprobamos juntos» (470 px de ancho) cabe con hasta 5 entregas de dos líneas; con 7 termina
// a 1118 px (medido en Chrome a 1920×1080 con el peor caso de la Task 3). Por eso una lámina lleva a lo más
// MAX_ENTREGAS_LAMINA bloques con entrega, aunque la tabla tenga espacio para más.
const MAX_ENTREGAS_LAMINA = 5;

// Lo que queda para la tabla después de la descripción: hasta 3 líneas de unas 80 letras (38 px cada una).
function altoDisponible(descripcion) {
  const d = texto(descripcion).trim();
  return d ? ALTO_TABLA - 18 - Math.min(3, Math.ceil(d.length / 80)) * 38 : ALTO_TABLA;
}

function altoBloques(bloques, m) {
  return bloques.reduce(
    (alto, b) =>
      alto +
      m.bloque +
      (Array.isArray(b.actividades) ? b.actividades : []).reduce((s, a) => s + m.act + (m.detalle && texto(a.detalle).trim() ? m.detalle : 0), 0),
    0
  );
}

function densidadFase(bloques, disponible) {
  for (const m of [FILAS_FASE.normal, FILAS_FASE.denso]) {
    if (altoBloques(bloques, m) <= disponible) return m.clase;
  }
  return FILAS_FASE.muyDenso.clase;
}

// Reparte los bloques de una fase en láminas llenando cada una hasta su alto con la letra más chica (la
// primera lleva la descripción) y con a lo más MAX_ENTREGAS_LAMINA entregas, para que una fase larga siga
// en una lámina de «continuación» en vez de salirse por abajo. Un bloque que no cabe solo en una lámina se
// corta en trozos; el trozo que sigue lleva `sigue: true` y la entrega queda en el último trozo. Cada bloque
// lleva `ocurrencia` (cuántos bloques anteriores de la fase se llaman igual) para encontrar su revisión
// aunque dos bloques tengan el mismo nombre; los trozos de un bloque comparten la suya.
function paginarBloques(bloques, descripcion = '') {
  const m = FILAS_FASE.muyDenso;
  const porTrozo = Math.max(1, Math.floor((altoDisponible('x'.repeat(240)) - m.bloque) / m.act));
  const lista = [];
  const vistos = new Map();
  for (const b of (Array.isArray(bloques) ? bloques : []).filter((x) => x && typeof x === 'object')) {
    const acts = (Array.isArray(b.actividades) ? b.actividades : []).filter((a) => a && typeof a === 'object');
    const ocurrencia = vistos.get(texto(b.nombre)) || 0;
    vistos.set(texto(b.nombre), ocurrencia + 1);
    if (acts.length <= porTrozo) {
      lista.push({ ...b, actividades: acts, ocurrencia });
      continue;
    }
    for (let i = 0; i < acts.length; i += porTrozo) {
      lista.push({
        ...b,
        actividades: acts.slice(i, i + porTrozo),
        entrega: Boolean(b.entrega) && i + porTrozo >= acts.length,
        sigue: i > 0,
        ocurrencia,
      });
    }
  }
  const paginas = [];
  let actual = [];
  for (const b of lista) {
    const disponible = paginas.length === 0 ? altoDisponible(descripcion) : ALTO_TABLA;
    const entregas = actual.filter((x) => x.entrega).length + (b.entrega ? 1 : 0);
    if (actual.length && (altoBloques(actual.concat([b]), m) > disponible || entregas > MAX_ENTREGAS_LAMINA)) {
      paginas.push(actual);
      actual = [];
    }
    actual.push(b);
  }
  if (actual.length) paginas.push(actual);
  return paginas.length ? paginas : [[]];
}

// La revisión del cliente de cada bloque con entrega, emparejada igual que en la carta Gantt. La clave es
// «nombre#ocurrencia» (0 para el primer bloque con ese nombre en la fase, 1 para el segundo…): dos bloques
// «Desarrollo» en la misma fase no se pisan la revisión.
function revisionesPorBloque(cronograma, faseClave) {
  const mapa = new Map();
  const vistos = new Map();
  for (const g of filasGantt(cronograma)) {
    if (g.fase !== faseClave) continue;
    for (const f of g.filas) {
      if (!f.trabajo) continue;
      const nombre = texto(f.trabajo.etiqueta);
      const k = vistos.get(nombre) || 0;
      vistos.set(nombre, k + 1);
      if (f.revision) mapa.set(`${nombre}#${k}`, f.revision);
    }
  }
  return mapa;
}

// Clave de cada bloque para revisionesPorBloque: la `ocurrencia` que puso paginarBloques o, si la lámina se
// dibuja con los bloques de la fase sin paginar, la que se cuenta aquí en orden.
function clavesBloques(lista) {
  const vistos = new Map();
  return lista.map((b) => {
    const nombre = texto(b.nombre);
    if (Number.isInteger(b.ocurrencia)) return `${nombre}#${b.ocurrencia}`;
    const k = vistos.get(nombre) || 0;
    vistos.set(nombre, k + 1);
    return `${nombre}#${k}`;
  });
}

function renderFaseSlide({ fase, numeroFase, totalFases, bloques, continuacion, cronograma }, index, imageUrl) {
  const f = fase && typeof fase === 'object' ? fase : {};
  const titulo = texto(f.titulo).trim() || FASES[f.clave] || 'Fase';
  const revisiones = revisionesPorBloque(cronograma, f.clave);
  const lista = (Array.isArray(bloques) ? bloques : []).filter((b) => b && typeof b === 'object');
  const conDescripcion = !continuacion && Boolean(texto(f.descripcion).trim());
  const densidad = densidadFase(lista, conDescripcion ? altoDisponible(f.descripcion) : ALTO_TABLA);

  const filas = lista
    .map((b) => {
      const acts = (Array.isArray(b.actividades) ? b.actividades : []).filter((a) => a && typeof a === 'object');
      const entregable = b.entrega && texto(b.entregable).trim() ? `<span>Entrega: ${escapeHtml(b.entregable)}</span>` : '';
      const nombreBloque = `${escapeHtml(texto(b.nombre))}${b.sigue ? ' (sigue)' : ''}`;
      const cabeza = `<tr class="plan-bloque"><td colspan="4"><b>${nombreBloque}</b>${entregable}</td></tr>`;
      const cuerpo = acts
        .map((a) => {
          const r = responsableClave(a.responsable);
          const paralelo = a.en_paralelo ? '<em class="plan-paralelo">en paralelo</em>' : '';
          const detalle = texto(a.detalle).trim() ? `<small>${escapeHtml(a.detalle)}</small>` : '';
          return `<tr><td class="plan-act">${escapeHtml(texto(a.nombre))}${paralelo}${detalle}</td><td><span class="quien is-${r}">${
            RESPONSABLES[r]
          }</span></td><td>${diasTexto(a.dias_habiles)}</td><td>${escapeHtml(rangoCorto(a.desde, a.hasta))}</td></tr>`;
        })
        .join('');
      return cabeza + cuerpo;
    })
    .join('');

  const claves = clavesBloques(lista);
  const entregas = lista
    .map((b, i) => {
      if (!b.entrega) return '';
      const rev = revisiones.get(claves[i]);
      const cuando = rev ? `Tu revisión: ${escapeHtml(rangoCorto(rev.desde, rev.hasta))}.` : 'Lo revisas apenas te lo entregamos.';
      return `<li><b>${escapeHtml(texto(b.entregable).trim() || texto(b.nombre))}</b><span>${cuando}</span></li>`;
    })
    .join('');
  const aprobamos = entregas
    ? `<ul class="aprobamos-lista">${entregas}</ul><p class="aprobamos-nota">Tienes 5 días hábiles para aprobar cada entrega o enviarnos tus observaciones (cláusula 6.1 del contrato).</p>`
    : '<p class="aprobamos-nota">En esta parte no hay entregas que aprobar: te contamos el avance en cada reunión de seguimiento.</p>';

  return `
    <section class="slide plan-contenido plan-fase">
      <div class="slide-bg" style="${fondoContenido(imageUrl, index)}"></div>
      ${logoMark()}
      <div class="plan-texto plan-anima">
        <p class="eyebrow">${numero(index)} · Fase ${numeroFase} de ${totalFases}</p>
        <div class="accent-bar"></div>
        <h2>${escapeHtml(titulo)}${continuacion ? ' <span class="plan-cont">(continuación)</span>' : ''}</h2>
        ${conDescripcion ? `<p class="plan-desc">${linkify(escapeHtml(f.descripcion))}</p>` : ''}
        <table class="plan-tabla${densidad}"><thead><tr><th>Actividad</th><th>Quién</th><th>Duración</th><th>Fechas</th></tr></thead><tbody>${filas}</tbody></table>
      </div>
      <aside class="plan-tarjeta"><h3>Qué aprobamos juntos</h3>${aprobamos}</aside>
    </section>`;
}

const ESTILO_FASES = `
  .metodo { position: absolute; left: 72px; right: 72px; top: 320px; z-index: 2; list-style: none; display: grid;
    grid-template-columns: repeat(6, 1fr); gap: 22px; }
  .metodo::before { content: ''; position: absolute; left: 4%; right: 4%; top: 36px; height: 2px; background: rgba(255,255,255,.16); }
  .metodo-paso { position: relative; padding: 0 6px; }
  .metodo-marca { position: relative; display: flex; align-items: center; justify-content: center; width: 72px; height: 72px;
    border-radius: 50%; font-size: 24px; font-weight: 800; color: #9fb3c8; background: #0a1420; border: 2px solid rgba(255,255,255,.22); }
  .metodo-paso.is-hecha .metodo-marca { color: #06222a; background: #00d9c0; border-color: #00d9c0; font-size: 32px; }
  .metodo-paso.is-actual .metodo-marca { color: #fff; border-color: #00d9c0; box-shadow: 0 0 0 8px rgba(0,217,192,.18); }
  .metodo-paso.is-actual::after { content: ''; position: absolute; inset: -24px -14px -30px; z-index: -1; border-radius: 20px;
    background: rgba(0,217,192,.07); border: 1px solid rgba(0,217,192,.32); }
  .metodo-paso h3 { color: #fff; font-size: 27px; font-weight: 800; margin-top: 26px; line-height: 1.2; }
  .metodo-texto { color: #b8c6d6; font-size: 19px; line-height: 1.5; margin-top: 12px; }
  .metodo-paso.is-hecha h3, .metodo-paso.is-hecha .metodo-texto { opacity: .72; }
  .metodo-paso.is-proxima .metodo-texto { color: #9fb3c8; }
  .metodo-estado { color: #9fb3c8; font-size: 16px; letter-spacing: .1em; text-transform: uppercase; margin-top: 18px; font-weight: 700; }
  .metodo-paso.is-hecha .metodo-estado { color: #00d9c0; }
  .metodo-aqui { display: inline-block; margin-top: 18px; padding: 7px 16px; border-radius: 999px; background: #00d9c0; color: #06222a;
    font-size: 16px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
  .metodo-firma { color: #fff; font-size: 18px; line-height: 1.45; margin-top: 12px; }
  .metodo-pie { position: absolute; left: 72px; right: 72px; bottom: 120px; z-index: 2; color: #c9d4e0; font-size: 24px;
    border-top: 1px solid rgba(255,255,255,.14); padding-top: 26px; }
  .plan-contenido .plan-texto { position: absolute; left: 72px; top: 240px; width: 1180px; z-index: 2; }
  .plan-texto h2 { color: #fff; font-size: 46px; font-weight: 800; margin-bottom: 18px; text-shadow: 0 2px 18px rgba(6,13,21,.7); }
  .plan-cont { color: #9fb3c8; font-size: 30px; font-weight: 600; }
  .plan-desc { color: #dbe4ee; font-size: 25px; line-height: 1.5; margin-bottom: 18px; max-width: 1080px;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .plan-desc a { color: #00d9c0; }
  .plan-tabla { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .plan-tabla th { color: #9fb3c8; font-size: 15px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; text-align: left;
    padding: 0 12px 10px 0; border-bottom: 1px solid rgba(255,255,255,.2); }
  .plan-tabla th:nth-child(1) { width: 52%; }
  .plan-tabla th:nth-child(2) { width: 17%; }
  .plan-tabla th:nth-child(3) { width: 16%; }
  .plan-tabla th:nth-child(4) { width: 15%; }
  .plan-tabla td { color: #dbe4ee; font-size: 21px; padding: 10px 12px 10px 0; border-bottom: 1px solid rgba(255,255,255,.1); vertical-align: top;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .plan-tabla tr.plan-bloque td { color: #fff; padding-top: 18px; border-bottom: 0; }
  .plan-tabla tr.plan-bloque b { font-size: 23px; }
  .plan-tabla tr.plan-bloque span { margin-left: 16px; color: #00d9c0; font-size: 18px; }
  .plan-act small { display: block; color: #9fb3c8; font-size: 17px; margin-top: 3px; overflow: hidden; text-overflow: ellipsis; }
  .plan-paralelo { margin-left: 10px; font-style: normal; font-size: 14px; color: #06222a; background: #9fb3c8; border-radius: 999px;
    padding: 2px 10px; vertical-align: 2px; }
  .quien { display: inline-block; font-size: 17px; font-weight: 700; padding: 3px 12px; border-radius: 999px; color: #06222a; }
  .quien.is-at { background: #00d9c0; }
  .quien.is-cliente { background: #38bdf8; }
  .quien.is-ambos { background: #a78bfa; }
  .plan-tabla.is-denso td { font-size: 19px; padding: 6px 12px 6px 0; }
  .plan-tabla.is-denso tr.plan-bloque td { padding-top: 12px; }
  .plan-tabla.is-muy-denso td { font-size: 17px; padding: 4px 12px 4px 0; }
  .plan-tabla.is-muy-denso tr.plan-bloque td { padding-top: 9px; }
  .plan-tabla.is-muy-denso tr.plan-bloque b { font-size: 19px; }
  .plan-tabla.is-muy-denso .plan-act small { display: none; }
  .plan-tabla.is-muy-denso .quien { font-size: 15px; padding: 1px 10px; }
  .plan-tarjeta { position: absolute; right: 72px; top: 240px; width: 470px; z-index: 2; background: rgba(6,13,21,.8);
    border: 1px solid rgba(0,217,192,.35); border-radius: 18px; padding: 30px 32px; }
  .plan-tarjeta h3 { color: #00d9c0; font-size: 20px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; margin-bottom: 18px; }
  .aprobamos-lista { list-style: none; display: flex; flex-direction: column; gap: 16px; }
  .aprobamos-lista li { display: flex; flex-direction: column; gap: 4px; padding-left: 18px; border-left: 3px solid #f59e0b; }
  .aprobamos-lista b { color: #fff; font-size: 21px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .aprobamos-lista span { color: #c9d4e0; font-size: 18px; }
  .aprobamos-nota { color: #9fb3c8; font-size: 17px; line-height: 1.5; margin-top: 20px; }
  @media screen {
    body.mode-deck .slide.is-active .plan-anima > *,
    body.mode-deck .slide.is-active .plan-tarjeta,
    body.mode-deck .slide.is-active .metodo-paso,
    body.mode-deck .slide.is-active .gantt { animation: at-rise .65s cubic-bezier(.22,.9,.3,1) both; }
    body.mode-deck .slide.is-active .plan-anima > *:nth-child(2) { animation-delay: .1s; }
    body.mode-deck .slide.is-active .plan-anima > *:nth-child(3) { animation-delay: .18s; }
    body.mode-deck .slide.is-active .plan-anima > *:nth-child(n+4) { animation-delay: .26s; }
    body.mode-deck .slide.is-active .plan-tarjeta, body.mode-deck .slide.is-active .gantt { animation-delay: .3s; }
    body.mode-deck .slide.is-active .metodo-paso:nth-child(2) { animation-delay: .08s; }
    body.mode-deck .slide.is-active .metodo-paso:nth-child(3) { animation-delay: .16s; }
    body.mode-deck .slide.is-active .metodo-paso:nth-child(4) { animation-delay: .24s; }
    body.mode-deck .slide.is-active .metodo-paso:nth-child(5) { animation-delay: .32s; }
    body.mode-deck .slide.is-active .metodo-paso:nth-child(6) { animation-delay: .4s; }
    @media (prefers-reduced-motion: reduce) {
      body.mode-deck .slide.is-active .plan-anima > *, body.mode-deck .slide.is-active .plan-tarjeta,
      body.mode-deck .slide.is-active .metodo-paso, body.mode-deck .slide.is-active .gantt { animation: none !important; }
    }
  }
`;
```

Reemplazar la última línea del archivo:

```js
module.exports = { renderPlanHtml, lunesDe, semanaIndice, diasEntre, fechaLarga, fechaCorta, rangoCorto, diasTexto, urlSegura, filasGantt, medidasGantt, renderGantt, renderGanttSlide };
```

por:

```js
module.exports = { renderPlanHtml, lunesDe, semanaIndice, diasEntre, fechaLarga, fechaCorta, rangoCorto, diasTexto, urlSegura, filasGantt, medidasGantt, renderGantt, renderGanttSlide, renderMetodoSlide, paginarBloques, renderFaseSlide };
```

- [ ] **Step 14: Correr y ver pasar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/template-plan.test.js
```

```text
ℹ tests 24
ℹ pass 24
ℹ fail 0
```

- [ ] **Step 15: Commit**

```bash
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer add renderer/src/template-plan.js renderer/test/template-plan.test.js
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer commit -m "feat(renderer): láminas del Método AT y de cada fase con qué aprobamos juntos" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

#### Ciclo D — qué necesitamos, reuniones y soporte, portal y cierre

- [ ] **Step 16: Escribir las pruebas de las cuatro láminas finales**

Agregar al final de `renderer/test/template-plan.test.js`:

```js
// --- Qué necesitamos, reuniones, portal y cierre -----------------------------

test('«Qué necesitamos de ti» lista los insumos y la regla de la cláusula 4.2', () => {
  const html = tp.renderNecesitamosSlide(planCorto(), 8, '');
  assert.ok(html.includes('<h2>Qué necesitamos de ti</h2>'));
  assert.ok(html.includes('<li>Logo y colores de tu marca</li>'));
  assert.ok(html.includes('El plazo corre desde que recibimos el anticipo y estos insumos.'));
  assert.ok(html.includes('cláusula 4.2 del contrato'));
  assert.ok(tp.renderNecesitamosSlide({}, 8, '').includes('Te avisamos en la reunión de inicio si falta algo para partir.'));
});

test('«Reuniones y soporte» lista las reuniones, la garantía y los servicios mensuales', () => {
  const plan = planCorto();
  plan.soporte = { garantia_meses: 3, mensuales: ['Mantención del sitio', { nombre: 'Campañas', detalle: 'Google Ads' }] };
  const html = tp.renderReunionesSlide(plan, 9, '');
  assert.ok(html.includes('<li><b>Reunión de inicio</b><span>Revisamos juntos este plan y los insumos.</span></li>'));
  assert.ok(html.includes('<li><b>Entrega y capacitación</b></li>'));
  assert.ok(html.includes('<b>Garantía de 3 meses:</b>'));
  // Misma exclusión que la cláusula de Garantía del contrato y sin número: la numeración cambió cuando se
  // agregó la cláusula de IA (12.1 pasó a 13.1) y los contratos firmados antes citan la otra.
  assert.ok(
    html.includes('los 3 meses siguientes a la entrega final, salvo los que vengan de cambios hechos por terceros o por ti (cláusula de Garantía de tu contrato)')
  );
  assert.ok(!html.includes('13.1'));
  assert.ok(html.includes('<li>Mantención del sitio</li>'));
  assert.ok(html.includes('<li>Campañas: Google Ads</li>'));
  // La garantía es la del contrato (at_pt_armar_render la toma de ahí, decisión D8) y el renderer la muestra
  // tal como llega, sin un valor propio: 6 meses dice 6, 1 dice «mes» y 0 no promete garantía.
  const garantia = (meses) => tp.renderReunionesSlide({ ...planCorto(), soporte: { garantia_meses: meses, mensuales: [] } }, 9, '');
  assert.ok(garantia(6).includes('<b>Garantía de 6 meses:</b>') && garantia(6).includes('en los 6 meses siguientes a la entrega final'));
  assert.ok(!garantia(6).includes('3 meses'));
  assert.ok(garantia(1).includes('<b>Garantía de 1 mes:</b>') && garantia(1).includes('en el mes siguiente a la entrega final'));
  assert.ok(!garantia(0).includes('Garantía de') && garantia(0).includes('Te acompañamos después de la entrega.'));
  const sinNada = tp.renderReunionesSlide({}, 9, '');
  assert.ok(sinNada.includes('Coordinamos cada reunión contigo con anticipación.'));
  assert.ok(sinNada.includes('Te acompañamos después de la entrega.'));
});

test('«Sigue tu proyecto» enlaza al portal del cliente', () => {
  const html = tp.renderPortalSlide(planCorto(), 10, '');
  assert.ok(html.includes('<h2>Sigue tu proyecto</h2>'));
  for (const parte of ['Tus contratos', 'Tu proyecto', 'El historial', 'Todo queda documentado.']) {
    assert.ok(html.includes(parte), `falta ${parte}`);
  }
  assert.ok(
    html.includes(
      '<a class="plan-boton" href="https://automatizatech.cl/?crm_view=timeline&amp;cid=999&amp;token=prueba-token" target="_blank" rel="noopener noreferrer">Entrar a mi portal</a>'
    )
  );
});

test('cliente sin correo (sin portal): la lámina sale igual, sin enlace', () => {
  for (const portal_url of ['', undefined, 'javascript:alert(1)']) {
    const html = tp.renderPortalSlide({ ...planCorto(), portal_url }, 10, '');
    assert.ok(html.includes('<h2>Sigue tu proyecto</h2>'));
    assert.ok(!html.includes('class="plan-boton"'));
    assert.ok(!html.includes('javascript:'));
    assert.ok(html.includes('Pídenos el enlace a tu portal cuando quieras.'));
  }
});

test('el cierre trae los enlaces para agendar: WhatsApp siempre, la web solo si viene', () => {
  const plan = planCorto();
  const html = tp.renderCierreSlide(plan, 11, '');
  assert.ok(html.includes('<h2>Agenda tu llamada de seguimiento</h2>'));
  assert.ok(html.includes(`<a class="cierre-enlace" href="${plan.agenda.whatsapp_url}" target="_blank" rel="noopener noreferrer">`));
  assert.ok(html.includes('<b>Por WhatsApp con Tech</b>'));
  assert.ok(!html.includes('En el sitio web'), 'sin web_url no hay enlace web');
  assert.ok(html.includes('Código de tu plan: <b>PlanPrueba01</b>'));
  assert.ok(html.includes('class="pdf-button" href="presentation.pdf" download'));
  assert.ok(html.includes('Descargar el plan en PDF'));

  const conWeb = tp.renderCierreSlide({ ...plan, agenda: { ...plan.agenda, web_url: 'https://automatizatech.cl/ver-plan.php?id=PlanPrueba01&agendar=1' } }, 11, '');
  assert.ok(conWeb.includes('href="https://automatizatech.cl/ver-plan.php?id=PlanPrueba01&amp;agendar=1"'));
  assert.ok(conWeb.includes('<b>En el sitio web</b>'));
});

test('sin enlace de WhatsApp válido, el cierre usa el número de AutomatizaTech', () => {
  const html = tp.renderCierreSlide({ unique_id: 'PlanPrueba01', agenda: { whatsapp_url: 'http://malo', web_url: 'javascript:x' } }, 11, '');
  assert.ok(html.includes('href="https://wa.me/56927002984"'));
  assert.ok(!html.includes('javascript:'));
});

test('el cierre usa la foto de próximos pasos si viene, y el logo encima', () => {
  const html = tp.renderCierreSlide(planCorto(), 11, 'img/cierre.png');
  assert.ok(html.includes("url('img/cierre.png')"));
  assert.ok(html.includes('class="closing-logo"'));
});
```

- [ ] **Step 17: Correr y ver fallar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/template-plan.test.js
```

```text
is not a function
ℹ tests 31
ℹ pass 24
ℹ fail 7
```

- [ ] **Step 18: Implementar las cuatro láminas finales**

Reemplazar la línea 2 de `renderer/src/template-plan.js`:

```js
const { STYLE, SCRIPT, backgroundStyle, logoMark, renderDeckControls } = require('./template');
```

por:

```js
const { STYLE, SCRIPT, CONTACT_ICONS, LOGO_URL, backgroundStyle, logoMark, renderDeckControls } = require('./template');
```

Insertar justo antes de la línea `// === Documento ===`:

```js
// Listas largas: más de 6 insumos van en dos columnas y más de 14 en tres, con letra más chica; más de 5
// reuniones o servicios mensuales achican la letra y más de 8 esconden el detalle. En cuanto una lista se
// compacta, cada punto muestra a lo más dos líneas (ESTILO_CIERRE). Así una lista en los topes que acepta
// at_pt_validar_plan (12 insumos de 160 letras, 8 reuniones con detalle de 200, 6 servicios mensuales de 160)
// cabe en la lámina en vez de salirse por abajo.
function claseLista(n, dosColumnas) {
  if (dosColumnas) return n > 14 ? ' is-muy-denso is-tres-columnas' : n > 6 ? ' is-denso is-dos-columnas' : '';
  return n > 8 ? ' is-muy-denso' : n > 5 ? ' is-denso' : '';
}

function renderNecesitamosSlide(data, index, imageUrl) {
  const items = (Array.isArray(data && data.necesitamos_de_ti) ? data.necesitamos_de_ti : []).map(texto).filter((s) => s.trim());
  const lista = items.length
    ? `<ul class="plan-lista${claseLista(items.length, true)}">${items.map((s) => `<li>${escapeHtml(s)}</li>`).join('')}</ul>`
    : '<p class="plan-desc">Te avisamos en la reunión de inicio si falta algo para partir.</p>';
  return `
    <section class="slide plan-contenido plan-necesitamos">
      <div class="slide-bg" style="${fondoContenido(imageUrl, index)}"></div>
      ${logoMark()}
      <div class="plan-texto plan-angosto plan-anima">
        <p class="eyebrow">${numero(index)} · Tu parte</p>
        <div class="accent-bar"></div>
        <h2>Qué necesitamos de ti</h2>
        ${lista}
        <p class="plan-clausula">El plazo corre desde que recibimos el anticipo y estos insumos. Si la entrega se atrasa, las fechas se corren en los mismos días (cláusula 4.2 del contrato).</p>
      </div>
    </section>`;
}

function renderReunionesSlide(data, index, imageUrl) {
  const reuniones = (Array.isArray(data && data.reuniones) ? data.reuniones : [])
    .map((r) => (r && typeof r === 'object' ? r : { nombre: texto(r) }))
    .filter((r) => texto(r.nombre).trim());
  const s = data && data.soporte && typeof data.soporte === 'object' ? data.soporte : {};
  const meses = Math.round(Number(s.garantia_meses) || 0);
  // `mensuales` puede traer textos u objetos {nombre, detalle}: se muestran los dos.
  const mensuales = (Array.isArray(s.mensuales) ? s.mensuales : [])
    .map((m) => (m && typeof m === 'object' ? [texto(m.nombre), texto(m.detalle)].filter((x) => x.trim()).join(': ') : texto(m)))
    .filter((x) => x.trim());
  const listaReuniones = reuniones.length
    ? `<ul class="plan-lista${claseLista(reuniones.length, false)}">${reuniones
        .map((r) => `<li><b>${escapeHtml(r.nombre)}</b>${texto(r.detalle).trim() ? `<span>${escapeHtml(r.detalle)}</span>` : ''}</li>`)
        .join('')}</ul>`
    : '<p class="plan-desc">Coordinamos cada reunión contigo con anticipación.</p>';
  const garantia =
    meses > 0
      ? `<p class="plan-desc"><b>Garantía de ${meses} ${meses === 1 ? 'mes' : 'meses'}:</b> corregimos sin costo los errores de lo entregado que aparezcan en ${
          meses === 1 ? 'el mes siguiente' : `los ${meses} meses siguientes`
        } a la entrega final, salvo los que vengan de cambios hechos por terceros o por ti (cláusula de Garantía de tu contrato).</p>`
      : '';
  const listaMensuales = mensuales.length
    ? `<p class="plan-sub">Servicios mensuales</p><ul class="plan-lista${claseLista(mensuales.length, false)}">${mensuales
        .map((x) => `<li>${escapeHtml(x)}</li>`)
        .join('')}</ul>`
    : '';
  const soporte = garantia || listaMensuales ? `${garantia}${listaMensuales}` : '<p class="plan-desc">Te acompañamos después de la entrega.</p>';
  return `
    <section class="slide plan-contenido plan-reuniones">
      <div class="slide-bg" style="${fondoContenido(imageUrl, index)}"></div>
      ${logoMark()}
      <div class="plan-texto plan-anima">
        <p class="eyebrow">${numero(index)} · Acompañamiento</p>
        <div class="accent-bar"></div>
        <h2>Reuniones y soporte</h2>
        <div class="plan-columnas">
          <div><p class="plan-sub">Reuniones</p>${listaReuniones}</div>
          <div><p class="plan-sub">Soporte</p>${soporte}</div>
        </div>
      </div>
    </section>`;
}

function renderPortalSlide(data, index, imageUrl) {
  const url = urlSegura(data && data.portal_url);
  const accion = url
    ? `<a class="plan-boton" href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer">Entrar a mi portal</a>`
    : '<p class="plan-desc">Pídenos el enlace a tu portal cuando quieras.</p>';
  return `
    <section class="slide plan-contenido plan-portal">
      <div class="slide-bg" style="${fondoContenido(imageUrl, index)}"></div>
      ${logoMark()}
      <div class="plan-texto plan-angosto plan-anima">
        <p class="eyebrow">${numero(index)} · Tu portal</p>
        <div class="accent-bar"></div>
        <h2>Sigue tu proyecto</h2>
        <p class="plan-desc">En tu portal de cliente ves, cuando quieras:</p>
        <ul class="plan-lista">
          <li><b>Tus contratos</b><span>Firmados y a la mano.</span></li>
          <li><b>Tu proyecto</b><span>Su avance, sus fechas y su estado.</span></li>
          <li><b>El historial</b><span>Reuniones, seguimientos, notas y pagos.</span></li>
        </ul>
        <p class="plan-clausula">Todo queda documentado.</p>
        ${accion}
      </div>
    </section>`;
}

function renderCierreSlide(data, index, imageUrl) {
  const agenda = data && data.agenda && typeof data.agenda === 'object' ? data.agenda : {};
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
  const foto = imageUrl
    ? ` style="background-image: linear-gradient(180deg, rgba(10,20,32,.62), rgba(10,20,32,.9)), url('${escapeHtml(
        imageUrl
      )}'); background-size: cover; background-position: center;"`
    : '';
  const codigo = texto(data && data.unique_id).trim();
  return `
    <section class="slide slide-closing plan-cierre">
      <div class="closing-left"${foto}>
        <a href="https://automatizatech.cl" target="_blank" rel="noopener noreferrer"><img class="closing-logo" src="${LOGO_URL}" alt="AutomatizaTech" /></a>
      </div>
      <div class="closing-right">
        <p class="eyebrow">${numero(index)} · Próximo paso</p>
        <h2>Agenda tu llamada de seguimiento</h2>
        <p class="cierre-texto">Revisemos juntos este plan y resolvamos tus dudas antes de partir.</p>
        <div class="cierre-enlaces">${enlaces}</div>
        ${codigo ? `<p class="cierre-codigo">Código de tu plan: <b>${escapeHtml(codigo)}</b></p>` : ''}
        <a class="pdf-button" href="presentation.pdf" download>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 11l5 5 5-5M4 19h16" /></svg>
          Descargar el plan en PDF
        </a>
      </div>
    </section>`;
}

const ESTILO_CIERRE = `
  .plan-contenido .plan-texto.plan-angosto { width: 1000px; top: 240px; }
  .plan-sub { color: #00d9c0; font-size: 18px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; margin-bottom: 14px; }
  .plan-lista { list-style: none; display: flex; flex-direction: column; gap: 18px; margin-bottom: 26px; }
  .plan-lista li { position: relative; padding-left: 44px; color: #dbe4ee; font-size: 26px; line-height: 1.35; overflow-wrap: anywhere;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .plan-lista li::before { content: '✓'; position: absolute; left: 0; top: 2px; width: 30px; height: 30px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 17px; font-weight: 800; color: #06222a; background: #00d9c0; }
  .plan-lista li b { color: #fff; }
  .plan-lista li span { display: block; color: #b8c6d6; font-size: 21px; margin-top: 2px; }
  .plan-lista.is-denso { gap: 10px; }
  .plan-lista.is-denso li { font-size: 21px; }
  .plan-lista.is-denso li span { font-size: 18px; }
  .plan-lista.is-denso li, .plan-lista.is-dos-columnas li, .plan-lista.is-tres-columnas li { -webkit-line-clamp: 2; }
  .plan-columnas .plan-lista:last-child { margin-bottom: 0; }
  .plan-lista.is-muy-denso { gap: 6px; }
  .plan-lista.is-muy-denso li { font-size: 18px; padding-left: 34px; -webkit-line-clamp: 2; }
  .plan-lista.is-muy-denso li::before { width: 24px; height: 24px; font-size: 14px; top: 0; }
  .plan-lista.is-muy-denso li span { display: none; }
  .plan-lista.is-dos-columnas, .plan-lista.is-tres-columnas { display: block; column-gap: 44px; }
  .plan-lista.is-dos-columnas { columns: 2; }
  .plan-lista.is-tres-columnas { columns: 3; }
  .plan-lista.is-dos-columnas li, .plan-lista.is-tres-columnas li { break-inside: avoid; margin-bottom: 12px; }
  .plan-clausula { color: #fff; font-size: 22px; line-height: 1.5; padding: 18px 24px; border-left: 4px solid #00d9c0;
    background: rgba(0,217,192,.1); border-radius: 0 12px 12px 0; margin-bottom: 26px; max-width: 900px; }
  .plan-columnas { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; }
  .plan-columnas .plan-desc { -webkit-line-clamp: 6; font-size: 23px; }
  .plan-boton { display: inline-flex; align-items: center; font-size: 22px; font-weight: 700; color: #06222a; background: #00d9c0;
    text-decoration: none; border-radius: 999px; padding: 18px 34px; }
  .plan-cierre .closing-left { flex-direction: column; }
  .plan-cierre .closing-right .eyebrow { margin-bottom: 14px; }
  .cierre-texto { color: #c9d4e0; font-size: 26px; line-height: 1.5; margin-bottom: 34px; max-width: 820px; }
  .cierre-enlaces { display: flex; flex-direction: column; gap: 18px; align-items: flex-start; }
  .cierre-enlace { display: inline-flex; align-items: center; gap: 20px; text-decoration: none; padding: 18px 30px 18px 20px;
    border-radius: 18px; background: rgba(0,217,192,.1); border: 1px solid rgba(0,217,192,.4); min-width: 560px; }
  .cierre-enlace .contact-icon { width: 58px; height: 58px; }
  .cierre-enlace .contact-icon svg { width: 28px; height: 28px; }
  .cierre-enlace b { display: block; color: #fff; font-size: 27px; }
  .cierre-enlace small { display: block; color: #9fb3c8; font-size: 19px; margin-top: 4px; }
  .cierre-enlace:hover { background: rgba(0,217,192,.2); }
  .cierre-codigo { color: #9fb3c8; font-size: 19px; margin-top: 28px; }
  .cierre-codigo b { color: #fff; letter-spacing: .06em; }
`;
```

Reemplazar la última línea del archivo:

```js
module.exports = { renderPlanHtml, lunesDe, semanaIndice, diasEntre, fechaLarga, fechaCorta, rangoCorto, diasTexto, urlSegura, filasGantt, medidasGantt, renderGantt, renderGanttSlide, renderMetodoSlide, paginarBloques, renderFaseSlide };
```

por:

```js
module.exports = { renderPlanHtml, lunesDe, semanaIndice, diasEntre, fechaLarga, fechaCorta, rangoCorto, diasTexto, urlSegura, filasGantt, medidasGantt, renderGantt, renderGanttSlide, renderMetodoSlide, paginarBloques, renderFaseSlide, renderNecesitamosSlide, renderReunionesSlide, renderPortalSlide, renderCierreSlide };
```

- [ ] **Step 19: Correr y ver pasar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/template-plan.test.js
```

```text
ℹ tests 31
ℹ pass 31
ℹ fail 0
```

- [ ] **Step 20: Commit**

```bash
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer add renderer/src/template-plan.js renderer/test/template-plan.test.js
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer commit -m "feat(renderer): láminas de insumos, reuniones, portal y cierre con enlaces para agendar" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

#### Ciclo E — documento completo

- [ ] **Step 21: Escribir las pruebas del documento completo y la prueba de diseño en Chromium**

Agregar al final de `renderer/test/template-plan.test.js`:

```js
// --- Documento completo ------------------------------------------------------

function eyebrows(html) {
  return [...html.matchAll(/<p class="eyebrow">(\d\d) · ([^<]+)<\/p>/g)].map((m) => `${m[1]} ${m[2]}`);
}

test('el plan corto trae las 8 láminas en orden: portada, método, Gantt, 3 fases, necesitamos, reuniones, portal, cierre', () => {
  const html = tp.renderPlanHtml(planCorto(), {});
  assert.equal(contar(html, '<section class="slide'), 10);
  assert.ok(html.includes('<h1>Plan de trabajo — Sitio de una página</h1>'));
  assert.ok(html.includes('<title>Plan de trabajo · Sitio de una página</title>'));
  assert.deepEqual(eyebrows(html), [
    '02 El Método AT',
    '03 Carta Gantt',
    '04 Fase 1 de 3',
    '05 Fase 2 de 3',
    '06 Fase 3 de 3',
    '07 Tu parte',
    '08 Acompañamiento',
    '09 Tu portal',
    '10 Próximo paso',
  ]);
});

test('el plan largo parte la primera fase en dos láminas y la carta Gantt sigue en una sola', () => {
  const html = tp.renderPlanHtml(planLargo(), {});
  assert.equal(contar(html, '<section class="slide'), 11);
  assert.equal(contar(html, 'class="slide plan-panel plan-gantt"'), 1);
  assert.equal(contar(html, '(continuación)'), 1);
  assert.equal(contar(html, 'class="gantt-barra '), 16);
});

test('usa el mismo estilo, logo, controles y aviso de lámina que la propuesta', () => {
  const html = tp.renderPlanHtml(planCorto(), {});
  assert.ok(html.includes('.at-watermark img { width: 190px'));
  assert.ok(html.includes('logo-automatiza-tech'));
  assert.ok(html.includes('id="at-prev"'));
  assert.ok(html.includes('setMode(true)'));
  assert.ok(html.includes("type: 'at-deck-lamina'"));
  assert.ok(html.includes('.gantt-barra.is-revision'));
});

test('el borrador lleva el aviso y oculta el botón del PDF; la versión final no', () => {
  const borrador = tp.renderPlanHtml({ ...planCorto(), draft: true }, {});
  assert.ok(borrador.includes('<body class="is-draft">'));
  assert.ok(borrador.includes('Borrador · vista previa sin fotos'));
  // El botón está en el cierre y lo esconde la regla de STYLE (template.js) para body.is-draft.
  assert.ok(borrador.includes('<a class="pdf-button" href="presentation.pdf" download>'));
  assert.ok(borrador.includes('body.is-draft .pdf-button { display: none !important; }'));
  const final = tp.renderPlanHtml(planCorto(), {});
  assert.ok(!final.includes('class="at-draft-badge"'));
});

test('cada lámina toma su foto y las fotos se precargan una sola vez', () => {
  const images = {
    cover: 'img/cover.png',
    metodo: 'img/metodo.png',
    gantt: 'img/gantt.png',
    fase_1: 'img/fase_1.png',
    fase_2: 'img/fase_2.png',
    fase_3: 'img/fase_3.png',
    necesitamos: 'img/necesitamos.png',
    reuniones: 'img/reuniones.png',
    portal: 'img/portal.png',
    cierre: 'img/cierre.png',
  };
  const html = tp.renderPlanHtml(planLargo(), images);
  for (const url of Object.values(images)) {
    assert.ok(html.includes(`url('${url}')`), `falta la foto ${url}`);
  }
  // fase_1 sale en dos láminas y se precarga una sola vez.
  assert.equal(contar(html, 'url(\'img/fase_1.png\')'), 2);
  const head = html.slice(0, html.indexOf('</head>'));
  assert.equal(contar(head, '<link rel="preload" as="image"'), 10);
});

test('sin fotos, cada lámina cae al degradado de marca y no hay precargas', () => {
  const html = tp.renderPlanHtml(planCorto(), {});
  assert.ok(html.includes('background: linear-gradient(135deg, #0d1b2a'));
  assert.ok(!html.includes('rel="preload"'));
});

test('contrato sin propuesta y cliente sin portal: el plan se dibuja igual', () => {
  const plan = { ...planCorto(), portal_url: '', images: {}, metodo: undefined, reuniones: [], necesitamos_de_ti: [], soporte: undefined };
  const html = tp.renderPlanHtml(plan, {});
  assert.equal(contar(html, '<section class="slide'), 10);
  assert.ok(html.includes('<h2>Sigue tu proyecto</h2>'));
  assert.ok(!html.includes('class="plan-boton"'));
  assert.equal(contar(html, 'Estás aquí'), 1);
});

test('un plan con solo lo mínimo que exige el esquema no rompe el documento', () => {
  const minimo = {
    unique_id: 'PlanPrueba03',
    company_name: 'Cliente Prueba SpA',
    proyecto: 'Proyecto de prueba',
    fases: [{ clave: 'diseno_desarrollo' }],
    cronograma: { inicio: '2026-10-05', fin: '2026-10-09', barras: [] },
  };
  const html = tp.renderPlanHtml(minimo, undefined);
  assert.equal(contar(html, '<section class="slide'), 8);
  assert.ok(html.includes('<h2>Diseño y desarrollo</h2>'));
  assert.ok(html.includes('Las fechas aparecen aquí cuando el plan tenga actividades.'));
});

test('el nombre del proyecto y el de la empresa se escapan en la portada y el título', () => {
  const html = tp.renderPlanHtml({ ...planCorto(), proyecto: 'Sitio <b>nuevo</b> & más', company_name: 'Empresa <i>X</i>' }, {});
  assert.ok(html.includes('<title>Plan de trabajo · Sitio &lt;b&gt;nuevo&lt;/b&gt; &amp; más</title>'));
  assert.ok(html.includes('<h1>Plan de trabajo — Sitio &lt;b&gt;nuevo&lt;/b&gt; &amp; más</h1>'));
  assert.ok(html.includes('<p class="eyebrow">Empresa &lt;i&gt;X&lt;/i&gt;</p>'));
});
```

Crear además `renderer/test/template-plan-layout.test.js`. Es la prueba del caso 6 del Review Focus: mide en el
Chromium de Playwright (el de `render.js`, con sus mismos `args`), a 1920×1080 y en modo impresión, el plan largo,
un plan extremo (14 bloques en 27 semanas), el peor caso de la Task 3 (13 entregas de dos líneas) y las listas en
los topes de `at_pt_validar_plan`; y cuenta las páginas del PDF que genera `renderToFiles`. Tarda unos 15 s:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require('playwright');
const { renderPlanHtml } = require('../src/template-plan');
const { renderToFiles } = require('../src/render');
const { planLargo } = require('./fixtures/plan-ejemplo');

// Caso 6 del plan de trabajo, medido en Chromium de verdad (el mismo de render.js, con sus mismos args) a
// 1920×1080 y en modo impresión, que es lo que ve el PDF: ningún elemento de una lámina puede terminar a
// menos de 20 px del borde de abajo ni salirse por los lados, la tabla de una fase no pisa la tarjeta y la
// carta Gantt termina antes de 1050 px con letra legible. Los planes usan los topes que acepta
// at_pt_validar_plan (Task 2): 14 bloques, 12 insumos de 160 letras, 8 reuniones (nombre 80 + detalle 200)
// y 6 servicios mensuales de 160. Datos inventados: el repositorio es público.

const FERIADOS = new Set(['2026-10-12', '2026-12-08', '2026-12-25', '2027-01-01']);
const DIA_MS = 86400000;

function mover(fecha, dias) {
  return new Date(new Date(`${fecha}T00:00:00Z`).getTime() + dias * DIA_MS).toISOString().slice(0, 10);
}

function esHabil(fecha) {
  const d = new Date(`${fecha}T00:00:00Z`).getUTCDay();
  return d !== 0 && d !== 6 && !FERIADOS.has(fecha);
}

function siguiente(fecha) {
  let x = mover(fecha, 1);
  while (!esHabil(x)) x = mover(x, 1);
  return x;
}

function sumar(desde, n) {
  let x = desde;
  while (!esHabil(x)) x = mover(x, 1);
  for (let i = 1; i < n; i++) x = siguiente(x);
  return x;
}

function largo(t, n) {
  return `${t} `.repeat(Math.ceil(n / (t.length + 1))).slice(0, n).trim();
}

// 14 bloques de trabajo (el tope de la Task 3) con su revisión en 27 semanas, textos de 80 a 420 letras,
// un bloque de 30 actividades, 18 insumos y 9 reuniones: nada de esto debería llegar, pero no puede romper.
function planExtremo() {
  const p = planLargo();
  p.unique_id = 'PlanPrueba99';
  p.proyecto = largo('Plataforma de reservas, pagos, inventario y reportes', 118);
  p.fases[0].descripcion = largo('Diseñamos las pantallas contigo y construimos la plataforma por partes.', 420);
  p.fases[0].bloques.splice(1, 0, {
    nombre: largo('Bloque gigante de desarrollo con muchas actividades', 80),
    entregable: largo('Entregable con un nombre muy largo', 90),
    entrega: true,
    actividades: Array.from({ length: 30 }, (_, i) => ({
      nombre: `${i + 1}. ${largo('Actividad con un nombre larguísimo', 100)}`,
      detalle: i % 2 ? largo('Detalle muy largo de la actividad', 150) : '',
      responsable: ['at', 'cliente', 'ambos'][i % 3],
      dias_habiles: 60,
      en_paralelo: i % 4 === 3,
      desde: '2026-10-09',
      hasta: '2026-10-14',
    })),
  });
  p.necesitamos_de_ti = Array.from({ length: 18 }, (_, i) => `${i + 1}. ${largo('Insumo con descripción larga', 80)}`);
  p.reuniones = Array.from({ length: 9 }, (_, i) => ({ nombre: `Reunión ${i + 1}`, detalle: largo('Detalle largo', 120) }));
  p.soporte = { garantia_meses: 12, mensuales: Array.from({ length: 6 }, (_, i) => `Servicio mensual ${i + 1}`) };
  const barras = [];
  for (let i = 0; i < 14; i++) {
    const lunes = mover('2026-10-05', i * 14);
    const fase = i < 10 ? 'diseno_desarrollo' : i < 12 ? 'implementacion' : 'soporte';
    barras.push({ fase, etiqueta: `Bloque ${i + 1} ${largo('con nombre largo', 60)}`, tipo: 'trabajo', responsable: 'at', desde: lunes, hasta: mover(lunes, 4) });
    barras.push({ fase, etiqueta: 'Tu revisión', tipo: 'revision', responsable: 'cliente', desde: mover(lunes, 7), hasta: mover(lunes, 11) });
  }
  p.cronograma = {
    inicio: '2026-10-05',
    fin: barras[barras.length - 1].hasta,
    semanas: 28,
    barras,
    hitos: [
      { nombre: 'Diseño aprobado', fecha: '2026-10-16' },
      { nombre: 'Núcleo aprobado', fecha: '2026-10-30' },
      { nombre: 'Módulos aprobados', fecha: '2026-11-13' },
      { nombre: 'Plataforma aprobada', fecha: '2027-03-19' },
      { nombre: 'Entrega estimada', fecha: '2027-03-26' },
    ],
  };
  return p;
}

// El peor caso de la Task 3 (Arranque + 13 bloques con entrega, una actividad cada uno) con entregables de
// dos líneas, como los del plan largo del fixture. Sin tope de entregas por lámina, la tarjeta «Qué
// aprobamos juntos» de la segunda lámina de la fase termina a 1118 px.
function planPeorCaso() {
  const p = planLargo();
  p.unique_id = 'PlanPrueba04';
  const inicio = '2026-10-05';
  const finArranque = sumar(siguiente(inicio), 3);
  const bloques = [
    {
      nombre: 'Arranque',
      entregable: '',
      entrega: false,
      actividades: [
        { nombre: 'Reunión de inicio', detalle: '', responsable: 'ambos', dias_habiles: 1, en_paralelo: false, desde: inicio, hasta: inicio },
        { nombre: 'Entrega de logo, textos y accesos', detalle: '', responsable: 'cliente', dias_habiles: 3, en_paralelo: false, desde: siguiente(inicio), hasta: finArranque },
      ],
    },
  ];
  const barras = [{ fase: 'diseno_desarrollo', etiqueta: 'Arranque', tipo: 'trabajo', responsable: 'ambos', desde: inicio, hasta: finArranque }];
  let cursor = siguiente(finArranque);
  for (let i = 1; i <= 13; i++) {
    const dias = i <= 9 ? 5 : 4;
    const hasta = sumar(cursor, dias);
    bloques.push({
      nombre: `Etapa ${i}`,
      entregable: `Módulo ${i} funcionando en ambiente de prueba con sus reportes`,
      entrega: true,
      actividades: [{ nombre: `Trabajo ${i}`, detalle: '', responsable: 'at', dias_habiles: dias, en_paralelo: false, desde: cursor, hasta }],
    });
    barras.push({ fase: 'diseno_desarrollo', etiqueta: `Etapa ${i}`, tipo: 'trabajo', responsable: 'at', desde: cursor, hasta });
    const revisionDesde = siguiente(hasta);
    const revisionHasta = sumar(revisionDesde, 5);
    barras.push({ fase: 'diseno_desarrollo', etiqueta: 'Tu revisión', tipo: 'revision', responsable: 'cliente', desde: revisionDesde, hasta: revisionHasta });
    cursor = siguiente(revisionHasta);
  }
  p.fases = [{ ...p.fases[0], bloques }];
  p.cronograma = {
    inicio,
    fin: barras[barras.length - 1].hasta,
    semanas: 27,
    barras,
    hitos: [{ nombre: 'Entrega estimada', fecha: barras[barras.length - 2].hasta }],
  };
  return p;
}

// Las tres listas en los topes de at_pt_validar_plan, con textos largos de verdad.
function planListasAlTope() {
  const p = planLargo();
  p.unique_id = 'PlanPrueba05';
  p.necesitamos_de_ti = Array.from(
    { length: 12 },
    (_, i) => `${i + 1}. ${largo('Acceso al panel del proveedor de correo con usuario administrador y clave vigente', 155)}`
  );
  p.reuniones = Array.from({ length: 8 }, (_, i) => ({
    nombre: `Reunión ${i + 1} ${largo('de seguimiento semanal del avance', 68)}`,
    detalle: largo('Revisamos contigo lo avanzado, resolvemos dudas y acordamos los próximos pasos', 200),
  }));
  p.soporte = {
    garantia_meses: 12,
    mensuales: Array.from({ length: 6 }, (_, i) => `${i + 1}. ${largo('Mantención mensual del sitio con respaldos y actualizaciones de seguridad', 155)}`),
  };
  return p;
}

async function medir(plan) {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-plan-layout-'));
  const archivo = path.join(dir, 'index.html');
  await fs.writeFile(archivo, renderPlanHtml(plan, {}), 'utf8');
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
          const r = el.getBoundingClientRect();
          // Sin tamaño, o a lo alto de toda la lámina (las dos columnas del cierre): no es contenido que se corte.
          if ((!r.width && !r.height) || r.height >= s.height - 1) return;
          if (r.bottom > s.bottom - 20 || r.top < s.top - 1 || r.left < s.left - 1 || r.right > s.right + 1) {
            fuera.push(`lámina ${n + 1}: ${el.tagName.toLowerCase()}.${String(el.className)} termina a ${Math.round(r.bottom - s.top)} px`);
          }
        });
        const tabla = slide.querySelector('.plan-texto');
        const tarjeta = slide.querySelector('.plan-tarjeta');
        if (tabla && tarjeta && tabla.getBoundingClientRect().right > tarjeta.getBoundingClientRect().left) {
          fuera.push(`lámina ${n + 1}: la tabla se monta sobre la tarjeta`);
        }
      });
      const g = document.querySelector('.gantt');
      const gantt = g
        ? {
            fin: Math.round(g.getBoundingClientRect().bottom - g.closest('.slide').getBoundingClientRect().top),
            letra: parseFloat(getComputedStyle(g.querySelector('.gantt-etiqueta b')).fontSize),
          }
        : null;
      return { laminas: slides.length, fuera, gantt };
    });
  } finally {
    await browser.close();
    await fs.rm(dir, { recursive: true, force: true });
  }
}

test('la carta Gantt larga (14 semanas, 16 barras) y todas las láminas del plan largo caben en 1920×1080', async () => {
  const m = await medir(planLargo());
  assert.equal(m.laminas, 11);
  assert.deepEqual(m.fuera, []);
  assert.ok(m.gantt, 'falta la carta Gantt');
  assert.ok(m.gantt.fin <= 1050, `la carta termina a ${m.gantt.fin} px`);
  assert.ok(m.gantt.letra >= 16, `letra de la carta ${m.gantt.letra} px`);
});

test('con 14 bloques en 27 semanas y textos enormes nada se sale de su lámina y la carta sigue legible', async () => {
  const m = await medir(planExtremo());
  assert.ok(m.laminas > 11, `${m.laminas} láminas`);
  assert.deepEqual(m.fuera, []);
  assert.ok(m.gantt, 'falta la carta Gantt');
  assert.ok(m.gantt.fin <= 1050, `la carta termina a ${m.gantt.fin} px`);
  assert.ok(m.gantt.letra >= 14, `letra de la carta ${m.gantt.letra} px`);
});

test('el peor caso de la Task 3 (13 entregas de dos líneas) reparte «Qué aprobamos juntos» sin salirse', async () => {
  const m = await medir(planPeorCaso());
  // Portada, método, Gantt, la fase en tres láminas (5 + 5 + 3 entregas), necesitamos, reuniones, portal, cierre.
  assert.equal(m.laminas, 10);
  assert.deepEqual(m.fuera, []);
});

test('las listas en los topes de at_pt_validar_plan caben en sus láminas', async () => {
  const m = await medir(planListasAlTope());
  assert.equal(m.laminas, 11);
  assert.deepEqual(m.fuera, []);
});

test('el PDF del plan largo tiene una página por lámina', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'renderer-plan-pdf-'));
  try {
    const { pdfPath } = await renderToFiles(renderPlanHtml(planLargo(), {}), dir);
    const pdf = (await fs.readFile(pdfPath)).toString('latin1');
    assert.equal((pdf.match(/\/Type\s*\/Page(?!s)/g) || []).length, 11);
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});
```

- [ ] **Step 22: Correr y ver fallar**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/template-plan.test.js test/template-plan-layout.test.js
```

Salida esperada: `renderPlanHtml` todavía dibuja solo la portada. En `template-plan.test.js` fallan siete (por ejemplo
`1 !== 10` láminas, o el borrador sin botón del PDF) y dos ya pasan (el degradado sin fotos de la portada y el escape
vienen de la Task 11). En `template-plan-layout.test.js` fallan las cinco: no hay carta Gantt (`falta la carta
Gantt`), la cuenta de láminas da 1 y el PDF tiene 1 página (`1 !== 11`):

```text
✖ el plan corto trae las 8 láminas en orden: portada, método, Gantt, 3 fases, necesitamos, reuniones, portal, cierre
✖ la carta Gantt larga (14 semanas, 16 barras) y todas las láminas del plan largo caben en 1920×1080
✖ el PDF del plan largo tiene una página por lámina
ℹ tests 45
ℹ pass 33
ℹ fail 12
```

- [ ] **Step 23: Armar el documento completo**

Reemplazar en `renderer/src/template-plan.js` todo desde la línea `// === Documento ===` hasta el final del archivo
(la versión de la Task 11 de `renderPlanHtml` y la línea `module.exports`) por:

```js
// === Documento ===

// Orden de las láminas (y la foto de cada una, image_briefs[].slide): portada (cover), Método AT (metodo),
// carta Gantt (gantt), una por fase o más si la fase es larga (fase_1..fase_3), qué necesitamos de ti
// (necesitamos), reuniones y soporte (reuniones), sigue tu proyecto (portal) y cierre para agendar (cierre).
function renderPlanHtml(data, images = {}) {
  const d = data && typeof data === 'object' ? data : {};
  const fotos = images && typeof images === 'object' ? images : {};
  const fases = (Array.isArray(d.fases) ? d.fases : []).filter((f) => f && typeof f === 'object').slice(0, 3);
  const slides = [renderPlanCover(d, fotos.cover)];
  const usadas = [fotos.cover];
  let n = 1;
  const agregar = (html, foto) => {
    slides.push(html);
    usadas.push(foto);
  };
  n += 1;
  agregar(renderMetodoSlide(d, n, fotos.metodo), fotos.metodo);
  n += 1;
  agregar(renderGanttSlide(d, n, fotos.gantt), fotos.gantt);
  fases.forEach((fase, i) => {
    const foto = fotos[`fase_${i + 1}`];
    paginarBloques(fase.bloques, fase.descripcion).forEach((bloques, p) => {
      n += 1;
      const datosFase = { fase, numeroFase: i + 1, totalFases: fases.length, bloques, continuacion: p > 0, cronograma: d.cronograma };
      agregar(renderFaseSlide(datosFase, n, foto), foto);
    });
  });
  n += 1;
  agregar(renderNecesitamosSlide(d, n, fotos.necesitamos), fotos.necesitamos);
  n += 1;
  agregar(renderReunionesSlide(d, n, fotos.reuniones), fotos.reuniones);
  n += 1;
  agregar(renderPortalSlide(d, n, fotos.portal), fotos.portal);
  n += 1;
  agregar(renderCierreSlide(d, n, fotos.cierre), fotos.cierre);

  // Igual que en la propuesta: en modo presentación solo se dibuja la lámina activa y el navegador no
  // baja la foto de una lámina oculta; precargarlas desde <head> evita ver el degradado un instante.
  const preload = [...new Set(usadas.filter(Boolean))]
    .map((url) => `<link rel="preload" as="image" href="${escapeHtml(url)}" />`)
    .join('\n');

  return `<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Plan de trabajo · ${escapeHtml(texto(d.proyecto))}</title>
${preload}
<style>${STYLE}${ESTILO_PORTADA}${ESTILO_GANTT}${ESTILO_FASES}${ESTILO_CIERRE}</style>
</head>
<body${d.draft ? ' class="is-draft"' : ''}>
${d.draft ? '<div class="at-draft-badge">Borrador · vista previa sin fotos</div>' : ''}
<div class="deck">
${slides.join('\n')}
</div>
${renderDeckControls()}
<script>${SCRIPT}</script>
</body>
</html>`;
}

module.exports = {
  renderPlanHtml,
  lunesDe,
  semanaIndice,
  diasEntre,
  fechaLarga,
  fechaCorta,
  rangoCorto,
  diasTexto,
  urlSegura,
  filasGantt,
  medidasGantt,
  renderGantt,
  renderGanttSlide,
  renderMetodoSlide,
  paginarBloques,
  renderFaseSlide,
  renderNecesitamosSlide,
  renderReunionesSlide,
  renderPortalSlide,
  renderCierreSlide,
};
```

- [ ] **Step 24: Correr las pruebas del template, la de diseño y toda la batería**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/template-plan.test.js test/template-plan-layout.test.js
```

```text
ℹ tests 45
ℹ pass 45
ℹ fail 0
```

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node --test test/*.test.js
```

```text
ℹ tests 155
ℹ pass 155
ℹ fail 0
```

- [ ] **Step 25: Commit**

```bash
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer add renderer/src/template-plan.js renderer/test/template-plan.test.js renderer/test/template-plan-layout.test.js
git -C C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer commit -m "feat(renderer): documento completo del plan de trabajo con sus 8 láminas, medido en Chromium" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

#### Revisión visual complementaria (1920×1080 y PDF)

Lo que sostiene el caso 6 es `template-plan-layout.test.js` (Step 21), que corre con la batería. Este script queda
como revisión a ojo: saca una captura por lámina para mirar colores, rombos y etiquetas, que ninguna prueba ve.

- [ ] **Step 26: Escribir el script de capturas (fuera del repositorio)**

Crear `C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/plan-renderer-capturas/capturas-plan.js`.
Dibuja el plan largo del fixture y uno extremo (textos de 100 letras, un bloque de 30 actividades, 34 barras en 30
semanas, 5 hitos pegados, 18 insumos, 9 reuniones), saca una captura por lámina con Chrome instalado y revisa en modo
impresión que ningún elemento se salga de su lámina ni la tabla se monte sobre la tarjeta:

```js
// Revisión visual del plan de trabajo (no va al repositorio). Se corre desde renderer/:
//   node <ruta>/capturas-plan.js <carpeta-de-salida>
// Dibuja dos planes: el largo del fixture (14 semanas, 16 barras) y uno extremo (textos enormes, un bloque
// de 30 actividades, 34 barras en 30 semanas, listas largas). De cada uno saca una captura 1920×1080 por
// lámina en modo presentación y revisa en modo impresión (el del PDF) que nada se salga de su lámina.
// Del plan largo genera además el PDF con el mismo renderToFiles del servidor y cuenta sus páginas.
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');

const raiz = process.cwd();
const { chromium } = require(require.resolve('playwright', { paths: [raiz] }));
const { renderPlanHtml } = require(path.join(raiz, 'src', 'template-plan'));
const { renderToFiles } = require(path.join(raiz, 'src', 'render'));
const { planLargo } = require(path.join(raiz, 'test', 'fixtures', 'plan-ejemplo'));

const CHROME = process.env.CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const DIA_MS = 86400000;

function largo(t, n) {
  return `${t} `.repeat(Math.ceil(n / (t.length + 1))).slice(0, n).trim();
}

function mover(fecha, dias) {
  return new Date(new Date(`${fecha}T00:00:00Z`).getTime() + dias * DIA_MS).toISOString().slice(0, 10);
}

// Nada de esto debería llegar desde WordPress, pero el documento no puede romperse si llega.
function planExtremo() {
  const p = planLargo();
  p.unique_id = 'PlanPrueba99';
  p.proyecto = largo('Plataforma de reservas, pagos, inventario y reportes', 118);
  p.fases[0].descripcion = largo('Diseñamos las pantallas contigo y construimos la plataforma por partes.', 420);
  p.fases[0].bloques.splice(1, 0, {
    nombre: largo('Bloque gigante de desarrollo con muchas actividades', 80),
    entregable: largo('Entregable con un nombre muy largo', 90),
    entrega: true,
    actividades: Array.from({ length: 30 }, (_, i) => ({
      nombre: `${i + 1}. ${largo('Actividad con un nombre larguísimo que la IA no debería escribir', 100)}`,
      detalle: i % 2 ? largo('Detalle también muy largo de la actividad', 150) : '',
      responsable: ['at', 'cliente', 'ambos'][i % 3],
      dias_habiles: 60,
      servicio: 'plataforma',
      etapa: 'desarrollo',
      origen: 'ia',
      en_paralelo: i % 4 === 3,
      desde: '2026-10-09',
      hasta: '2026-10-14',
    })),
  });
  p.necesitamos_de_ti = Array.from({ length: 18 }, (_, i) => `${i + 1}. ${largo('Insumo con descripción larga para la lista', 80)}`);
  p.reuniones = Array.from({ length: 9 }, (_, i) => ({ nombre: `Reunión ${i + 1} ${largo('de seguimiento', 40)}`, detalle: largo('Detalle largo', 120) }));
  p.soporte = { garantia_meses: 12, mensuales: Array.from({ length: 7 }, (_, i) => `Servicio mensual ${i + 1}`) };
  const barras = [];
  for (const k of [0, 1]) {
    for (const b of p.cronograma.barras) {
      const etiqueta = k ? `${b.etiqueta} ${largo('segunda vuelta con nombre largo', 40)}` : b.etiqueta;
      barras.push({ ...b, etiqueta, desde: mover(b.desde, k * 105), hasta: mover(b.hasta, k * 105) });
    }
  }
  barras.push({ fase: 'soporte', etiqueta: 'Extra 1', tipo: 'trabajo', responsable: 'at', desde: '2027-04-26', hasta: '2027-04-28' });
  barras.push({ fase: 'soporte', etiqueta: 'Extra 2', tipo: 'trabajo', responsable: 'cliente', desde: '2027-04-29', hasta: '2027-04-30' });
  p.cronograma = {
    inicio: '2026-10-05',
    fin: '2027-04-30',
    semanas: 30,
    barras,
    hitos: [
      { nombre: 'Diseño aprobado', fecha: '2026-10-27' },
      { nombre: 'Núcleo aprobado', fecha: '2026-11-12' },
      { nombre: 'Módulos aprobados', fecha: '2026-11-25' },
      { nombre: 'Plataforma aprobada', fecha: '2027-04-02' },
      { nombre: 'Entrega estimada', fecha: '2027-04-07' },
    ],
  };
  return p;
}

async function revisar(browser, carpeta, plan) {
  fs.mkdirSync(carpeta, { recursive: true });
  const html = renderPlanHtml(plan, {});
  const archivo = path.join(carpeta, 'index.html');
  fs.writeFileSync(archivo, html, 'utf8');
  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 }, reducedMotion: 'reduce' });
  const page = await context.newPage();
  await page.goto(pathToFileURL(archivo).href, { waitUntil: 'load' });
  const total = await page.locator('.slide').count();
  for (let i = 0; i < total; i++) {
    if (i > 0) await page.keyboard.press('ArrowRight');
    await page.waitForTimeout(150);
    await page.screenshot({ path: path.join(carpeta, `lamina-${String(i + 1).padStart(2, '0')}.png`) });
  }

  // Modo impresión = lo que ve el PDF: todas las láminas visibles a escala 1.
  await page.emulateMedia({ media: 'print' });
  const problemas = await page.evaluate(() => {
    const fuera = [];
    document.querySelectorAll('.slide').forEach((slide, n) => {
      const s = slide.getBoundingClientRect();
      slide.querySelectorAll('*').forEach((el) => {
        if (el.closest('.slide-bg') || el.closest('.at-watermark')) return;
        const r = el.getBoundingClientRect();
        if (!r.width && !r.height) return;
        if (r.left < s.left - 1 || r.top < s.top - 1 || r.right > s.right + 1 || r.bottom > s.bottom + 1) {
          fuera.push(`lámina ${n + 1}: ${el.tagName.toLowerCase()}.${el.className} sale de la lámina`);
        }
      });
      const gantt = slide.querySelector('.gantt');
      if (gantt) {
        const g = gantt.getBoundingClientRect();
        if (g.bottom > s.bottom - 30) fuera.push(`lámina ${n + 1}: la carta Gantt termina a ${Math.round(g.bottom - s.top)} px`);
      }
      for (const sel of ['.plan-texto', '.plan-tarjeta', '.closing-right']) {
        const el = slide.querySelector(sel);
        if (!el) continue;
        const fin = Math.max(0, ...[...el.querySelectorAll('*')].map((x) => x.getBoundingClientRect().bottom));
        if (fin > s.bottom - 20) fuera.push(`lámina ${n + 1}: ${sel} termina a ${Math.round(fin - s.top)} px`);
      }
      const texto = slide.querySelector('.plan-texto');
      const tarjeta = slide.querySelector('.plan-tarjeta');
      if (texto && tarjeta && texto.getBoundingClientRect().right > tarjeta.getBoundingClientRect().left) {
        fuera.push(`lámina ${n + 1}: la tabla se monta sobre la tarjeta`);
      }
    });
    return fuera;
  });
  const medidas = await page.evaluate(() => {
    const g = document.querySelector('.gantt');
    const s = g.closest('.slide').getBoundingClientRect();
    const r = g.getBoundingClientRect();
    const fila = document.querySelector('.gantt-fila').getBoundingClientRect().height;
    const letra = getComputedStyle(document.querySelector('.gantt-etiqueta b')).fontSize;
    const semana = document.querySelector('.gantt-semana').getBoundingClientRect().width;
    const tablas = [...document.querySelectorAll('.plan-tabla')].map((t) => {
      const sl = t.closest('.slide').getBoundingClientRect();
      return `${t.className.replace('plan-tabla', '').trim() || 'normal'} ${Math.round(t.getBoundingClientRect().bottom - sl.top)}`;
    });
    return { arriba: Math.round(r.top - s.top), abajo: Math.round(r.bottom - s.top), fila, letra, semana: Math.round(semana), tablas };
  });
  console.log(`${path.basename(carpeta)}: ${total} láminas`);
  console.log(`  carta Gantt: de ${medidas.arriba} a ${medidas.abajo} px de 1080; fila ${medidas.fila} px; letra ${medidas.letra}; semana ${medidas.semana} px`);
  console.log(`  tablas de fase (densidad y dónde terminan): ${medidas.tablas.join(' | ')}`);
  console.log(problemas.length ? problemas.map((x) => `  ${x}`).join('\n') : '  sin elementos fuera de su lámina');
  await context.close();
  return html;
}

async function main() {
  const salida = path.resolve(process.argv[2] || 'capturas');
  const browser = await chromium.launch(fs.existsSync(CHROME) ? { executablePath: CHROME } : {});
  let htmlLargo = '';
  try {
    htmlLargo = await revisar(browser, path.join(salida, 'plan-largo'), planLargo());
    await revisar(browser, path.join(salida, 'plan-extremo'), planExtremo());
  } finally {
    await browser.close();
  }
  const pdfDir = path.join(salida, 'plan-largo', 'pdf');
  await renderToFiles(htmlLargo, pdfDir);
  const pdf = fs.readFileSync(path.join(pdfDir, 'presentation.pdf')).toString('latin1');
  console.log(`páginas del PDF del plan largo: ${(pdf.match(/\/Type\s*\/Page(?!s)/g) || []).length}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
```

- [ ] **Step 27: Correr la revisión y mirar las capturas**

```bash
cd C:/wamp64/www/automatiza-tech/.worktrees/plan-renderer/renderer && node C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/plan-renderer-capturas/capturas-plan.js C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/plan-renderer-capturas/salida
```

Salida esperada, completa (los píxeles pueden variar uno o dos con otra fuente; lo que no puede faltar son las dos
líneas «sin elementos fuera de su lámina» y las 11 páginas del PDF):

```text
plan-largo: 11 láminas
  carta Gantt: de 196 a 993 px de 1080; fila 48 px; letra 20.16px; semana 93 px
  tablas de fase (densidad y dónde terminan): is-muy-denso 928 | normal 745 | normal 801 | normal 699
  sin elementos fuera de su lámina
plan-extremo: 14 láminas
  carta Gantt: de 196 a 997 px de 1080; fila 22 px; letra 11px; semana 43 px
  tablas de fase (densidad y dónde terminan): normal 715 | is-muy-denso 814 | is-muy-denso 814 | is-muy-denso 924 | normal 906 | normal 801 | normal 699
  sin elementos fuera de su lámina
páginas del PDF del plan largo: 11
```

Abrir `salida/plan-largo/lamina-03.png` (la carta Gantt) y confirmar a ojo: cabecera S1 «5 oct» a S14 «4 ene»; tres
grupos (Diseño y desarrollo, Implementación, Soporte y mejora continua) con 10 filas de 48 px y los nombres completos
con su rango a la derecha; seis franjas ámbar a rayas pegadas a su bloque; rombos «Diseño aprobado · 27 oct»,
«Plataforma aprobada · 18 dic» y «Entrega estimada · 23 dic» (turquesa) sin taparse; leyenda y la nota «Fechas
estimadas, desde que recibimos el anticipo y tus insumos.» dentro de la tarjeta. En `salida/plan-extremo/lamina-03.png`
las 22 filas (de 22 px, con sus 34 barras) y las 30 semanas siguen dentro de la tarjeta. Mirar también la lámina 02 («Estás aquí» en Propuesta por
fases) y la 11 (enlace de WhatsApp y código del plan). No se commitea nada en este paso.

Entregable probado: `renderPlanHtml` dibuja el plan completo (10 láminas el corto, 11 el largo) y la batería lo
sostiene en Chromium: en el plan largo, en uno de 14 bloques en 27 semanas con textos enormes, en el peor caso de la
Task 3 (13 entregas) y con las listas en sus topes, ningún elemento se sale de su lámina, la carta Gantt termina antes
de 1050 px con letra de 16 px o más (14 en el extremo) y el PDF del plan largo tiene 11 páginas.


### Task 13: n8n — flujos «1 Borrador» y «2 Cambios», correos a Luis y pruebas del plan

Dos flujos n8n que piden el plan a GPT-4o y lo guardan en WordPress, más sus correos y la prueba local. Copian los
patrones de `N8N/propuestas-v3` que corren en PROD (rama `origin/claude/propuestas-json-robusto`, traída por la Task 0):
helpers `node`/`http`/`iff`/`link`, credenciales por id, `onError: continueRegularOutput` + `neverError` en todo HTTP,
`settings.errorWorkflow`, correos con `email_tpl.py`, y pruebas que corren el `jsCode` REAL con `node`.

Reglas de diseño que las pruebas fijan:
- **Nunca deja el plan trabado.** Ningún nodo Code lanza: toda falla (contexto ilegible, OpenAI caído, JSON inválido,
  plan sin actividades, 422 o 5xx de WordPress) termina en `POST /plan/{id}/error {nota}` con un motivo legible y
  en un correo a Luis. Si ni eso responde, el correo lo dice y sugiere «Destrabar».
- **Nunca tumba un plan que ya siguió.** Si al leer el contexto el plan no está en el estado que espera la ruta
  (`generando`, o `error` todavía sin contenido, para el borrador; `cambios` o `error` para los cambios: las mismas
  reglas del 409 de `POST /plan/{id}/borrador`, Task 7 y decisión D5), el flujo termina sin llamar a OpenAI ni tocar el
  plan; y si WordPress igual responde 409 al guardar, tampoco marca error ni escribe. Pasa cuando Luis usa «Destrabar» y «Reintentar» mientras la
  ejecución vieja sigue esperando a OpenAI: sin esto, la vieja pasaría a «error» el borrador bueno (o uno «aprobando»,
  que después nunca llegaría a «listo»).
- **WordPress decide qué es válido** (`at_pt_validar_plan`, Task 2): n8n solo revisa la forma mínima (objeto del plan,
  con fases, al menos una actividad) y manda el resto tal cual; un 422 queda como nota con los `errores` de WordPress.
  Para que un proyecto grande pero legítimo no termine en 422 (y «Reintentar» repita lo mismo), `PROMPT_PLAN` y
  `PROMPT_CAMBIOS` le dicen al modelo los topes exactos que valida WordPress (grupo-A.md, Task 2, «Para la Task 13»;
  decisión D12): 13 bloques, 10 actividades por bloque, 58 actividades en total, de 1 a 60 días hábiles por actividad,
  130 días hábiles con 5 de revisión por cada bloque con entrega, 10 hitos, y fases y responsables por su clave.
- **Borrador:** se descarta el bloque «Arranque» del modelo (lo pone WordPress con `at_pt_arranque`), toda actividad
  sale con `origen: 'ia'` (el modelo no puede declarar «tabla» ni «luis»), las claves de primer nivel que no son del
  plan se descartan (WordPress arma el plan solo con las que conoce) y las fotos se filtran: solo láminas nuevas del
  plan, una por lámina, reglas de `fotos_guard.py`; portada y cierre solo si el contrato no tiene propuesta.
- **Cambios:** una actividad se reconoce por su nombre normalizado y su orden de aparición, la misma clave que
  `at_pt_recorrer_actividades` de WordPress (Task 2): moverla de bloque no la hace nueva. Los días de las actividades
  `origen: 'luis'` se restituyen aunque el modelo los cambie o las mueva (además de pedirlo en el prompt); lo nuevo o
  con días cambiados va `'ia'` (revisar), como lo marca WordPress con `at_pt_marcar_ediciones(…, 'ia')`. Lo que el
  modelo omite se conserva del plan guardado, y las fases se unen por su `clave` y las fotos por su `slide`: una
  respuesta con solo la fase o la foto que cambió no borra las demás. El bloque «Arranque» no se le manda al modelo
  (lo pone WordPress, D12) y n8n lo restituye tal como estaba guardado, con lo que Luis haya editado en él: si el modelo
  manda uno propio, se descarta. Una nota suelta, una nota junto a las fases o `{}` no pasan como cambio. Sin comentarios no se llama a OpenAI: el plan guardado vuelve tal cual (WordPress recalcula y
  renderiza). Límite conocido: si el modelo RENOMBRA una actividad de Luis, se la trata como nueva; el prompt se lo
  prohíbe y WordPress avisa con `at_pt_luis_perdidas` (Task 2).
- **Vista previa:** si WordPress guardó el plan pero no pudo pedir la vista previa (aviso «No se pudo pedir la vista
  previa: …» de la Task 7), el flujo le escribe a Luis: si no, él esperaría el correo «borrador listo» de «3 Render»,
  que no va a llegar. El plan está bien (en «borrador»): no se marca error.
- **Privacidad y costo:** el modelo recibe lo contratado (con `contrato.garantia_meses`, D7), el rubro de la ficha del
  CRM (para describir las fotos, D7), la propuesta (si hay), la tabla y las láminas; no recibe el
  nombre de la persona ni fechas. Los flujos 1 y 2 no llaman al renderer: la vista previa la pide WordPress al guardar
  (`at_pt_pedir_render(..., 'draft', true)`) y el correo «Borrador listo» lo manda «3 Render» (Task 14).
- **Enlaces:** los correos enlazan solo a `automatizatech.cl` (nunca a `*.easypanel.host`, que el SMTP de Hostinger
  rechaza). El botón abre `admin.php?page=automatiza-crm-ficha&id=<crm>&pt=<plan>#tab-plan` con el `crm_cliente_id` que
  entrega `/contexto` (Tasks 5 y 7); si llega 0 (ficha sin enlazar al CRM), abre la lista de clientes
  (`page=automatiza-crm-clientes`, registrada en `wp-content/mu-plugins/crm-ai-completo.php:325`).

Casos del Review Focus que esta tarea prueba (parte n8n): **2** (JSON inválido, cortado o con texto alrededor, sin
actividades, fases desconocidas, días 0 y 200 y un texto de 5000 caracteres → nunca se guarda un plan roto: n8n no lo
«arregla», WordPress responde 422 y el plan queda en «error» con esos errores como nota); **3** (días `origen: 'luis'`
restituidos en «2 Cambios» aunque el modelo los cambie, mueva la actividad de bloque, devuelva solo la fase que cambió
o intente marcar «luis» otra actividad; dos actividades con el mismo nombre); **4** (contrato sin propuesta: el modelo
describe también portada y cierre, y el correo sin ficha conocida no se rompe); **5** (lado n8n: contexto 404, OpenAI
caído o sin detalle, WordPress 500 o sin respuesta → «error» con «Reintentar borrador» y correo; un pedido que llegó
tarde o un 409 no tumban el plan que ya siguió; guardado sin vista previa → correo). Las fechas en fin de semana o
feriado (1) las calcula WordPress (Tasks 1 y 3): el prompt le prohíbe al modelo escribir fechas.

**Files:**
- Create: `N8N/plan-trabajo/.gitignore`
- Create: `N8N/plan-trabajo/plan_js.py` (JavaScript compartido de los tres flujos y el nodo «Motivo del error»; decisión
  D13 del esqueleto. Sin él, `build_plan_2_cambios.py` tendría que importar
  `build_plan_1_borrador.py`, que al importarse reescribe su JSON; `probar_revision_fotos.py` de propuestas evita lo
  mismo leyendo el builder con `ast`.)
- Create: `N8N/plan-trabajo/correos_plan.py`
- Create: `N8N/plan-trabajo/build_plan_1_borrador.py` → genera y se commitea `N8N/plan-trabajo/plan-1-borrador.json`
- Create: `N8N/plan-trabajo/build_plan_2_cambios.py` → genera y se commitea `N8N/plan-trabajo/plan-2-cambios.json`
- Test: `N8N/plan-trabajo/probar_plan.py` (se crea con el arnés y la sección `puras`; las secciones `correos`,
  `borrador` y `cambios` se insertan, cada una en su paso rojo, justo antes de la línea marcador. Así cada commit deja
  la suite completa en `TODO OK`.)
- Se usan sin modificar (llegan con la Task 0): `N8N/propuestas-v3/json_guard.py`, `N8N/propuestas-v3/fotos_guard.py`,
  `N8N/propuestas-v3/email_tpl.py`

**Interfaces:**
- Consumes:
  - `N8N/propuestas-v3/json_guard.py` → `JS_LEER_JSON`: `leerJsonModelo(raw)`, `esObjetoPlano(v)`.
  - `N8N/propuestas-v3/fotos_guard.py` → `JS_LIMPIAR_FOTOS`: `limpiarFotos(briefs) → {limpias, reemplazadas, neutralizadas}`.
  - `N8N/propuestas-v3/email_tpl.py` → `JS_ESC`, `boton(url, texto, primario=True)`, `caja(html, tono)`, `etiqueta(texto, estado)`,
    `marco(titulo, etiqueta_html, cuerpo)`, `nota(html)`, `parrafo(html)`.
  - Tasks 6 y 9 (decisión D6): WordPress llama a los webhooks `plan-v1-borrador` (`at_pt_iniciar_borrador`) y
    `plan-v1-cambios` (panel, «Pedir cambios») con cuerpo `{"id": <plan_id>, "codigo": "<codigo del plan>"}` y la
    cabecera `X-AT-Secret`. Los flujos usan solo `id` (el `codigo` viaja para los registros de n8n; si falta, nada cambia).
  - Task 7 (con `at_pt_contexto` de la Task 5): `GET /plan/{id}/contexto` → `{ok, plan_id, codigo, estado, proyecto,
    empresa, cliente, fecha_firma, contrato: {servicios_contratados, alcance, entregables, fases_siguientes, plazo,
    garantia_meses}, propuesta: null|{company_name, solution_text, how_it_works: [texto…], extracto_reunion}, tabla,
    slides_foto, plan_actual, comentarios, crm_cliente_id, rubro}` (`crm_cliente_id` entero, 0 si la ficha no está
    enlazada al CRM, obligatorio para el botón de los correos; `rubro` de `crm_clientes.rubro`, `''` si no hay, y
    `contrato.garantia_meses` del placeholder `garantia_meses_servicio`, 3 si no está: los dos van al modelo, D7);
    `POST /plan/{id}/borrador {plan, origen: 'borrador'|'cambios'}` → 200
    `{ok: true, errores: [], avisos}` (con el aviso `'No se pudo pedir la vista previa: <motivo>'` si no pudo llamar a
    «3 Render»), 422 `{ok: false, errores, avisos}`, 409 si llegó tarde (D5: `borrador` solo en `generando`, o en
    `error` todavía sin contenido; `cambios` solo en `cambios` o `error`), 500
    `{ok: false, errores: ['No se pudo guardar el plan.']}`; `POST /plan/{id}/error {nota}` → `{ok, estado}` (guarda
    hasta 1000 caracteres de nota; D5: solo pasa a «error» un plan en `generando` o `cambios`, en otro estado deja la nota).
  - Task 2 (misma regla en los dos lados): `at_pt_recorrer_actividades` reconoce una actividad por nombre normalizado
    y orden de aparición; en «cambios» WordPress aplica `at_pt_respetar_dias_luis` y `at_pt_marcar_ediciones(…, 'ia')`.
- Produces:
  - `plan_js.JS_PLAN` (JavaScript): `CLAVES_PLAN`, `SLIDES_NUEVAS`, `SLIDES_DE_PROPUESTA = {cover: 'cover', cierre: 'next_steps'}`,
    `normPlan(s)`, `slidesConFoto(hayPropuesta, nFases) → string[]`, `recorrerActividades(plan, fn(actividad, bloque, fase))`,
    `esArranque(bloque) → bool`, `quitarArranque(plan) → plan` (cambia el que recibe),
    `restituirArranque(plan, anterior) → plan` (cambia el que recibe),
    `fotosDelPlan(briefs, nFases, hayPropuesta) → [{slide, prompt}]`, `protegerLuis(plan, anterior) → number`,
    `unirPor(antes, nuevos, campo) → array`,
    `leerPlanModelo(ia, {modo: 'borrador'|'cambios', hayPropuesta, anterior}) → {ok, reason, plan, protegidas}`.
  - `plan_js.code_motivo(modo: str) -> str` (código del nodo «Motivo del error»; salida `{id, crm, proyecto, reason, exec}`).
  - `correos_plan.JS_PANEL` (JavaScript `urlPanel(crm, plan) → string`), `correos_plan.AVISO_SIN_VISTA`,
    `correos_plan.marco_plan(titulo, etiqueta_html, cuerpo)`,
    `correos_plan.js_correo_plan(preparacion, asunto, titulo, etiqueta_html, cuerpo, extra='')`,
    `correos_plan.correo_error_plan(flujo)`, `correos_plan.correo_sin_vista(flujo)`.
  - `build_plan_1_borrador.PROMPT_PLAN`, `build_plan_2_cambios.PROMPT_CAMBIOS`.
  - Workflows «Plan de trabajo · 1 Borrador» (webhook `plan-v1-borrador`, 17 nodos) y «Plan de trabajo · 2 Cambios»
    (webhook `plan-v1-cambios`, 19 nodos); ambos con `settings.errorWorkflow = 'm7TOfKznVSBGz4Nd'`.
  - `probar_plan.py`: `seccion(nombre)`, `ok(cond, msg, detalle='')`, `node_js(programa, datos)`, `lib()`, `cargar(archivo)`,
    `simular(archivo, webhook=…, http={nodo: [respuestas]}, openai={nodo: [{content}|{error}|{}]})`, `llamadas(t, nodo)`,
    `sin_error(t, caso)`, `revisar_workflow(archivo, nombre, path)`, datos `act`, `brief`, `contexto`, `PLAN_IA`,
    `PLAN_GUARDADO`, `actividades`, `actividad`, `NUEVAS`, `TODAS`, `CIERRE`, `WP`, y la línea marcador
    `# ==== Las tareas siguientes agregan sus secciones justo antes de esta línea ====`.

- [ ] **Step 1: Crear la carpeta, su `.gitignore` y la prueba con el arnés y la sección `puras` (todavía falla)**

`N8N/plan-trabajo/.gitignore`:
```gitignore
__pycache__/
*.pyc
```

`N8N/plan-trabajo/probar_plan.py`:
```python
"""Prueba local de los flujos n8n del plan de trabajo, sin llamar a OpenAI, WordPress, al renderer ni a n8n.

Uso: python N8N/plan-trabajo/probar_plan.py [sección ...]   (sin argumentos corre todas; sale con 1 si algo falla)
Secciones: ver SECCIONES al final (puras, correos, borrador, cambios y las que agregan las tareas siguientes).

Qué se prueba:
- El JavaScript compartido (plan_js.py) corriendo con node: lectura de la respuesta del modelo, fotos del plan,
  días que Luis editó a mano.
- Los correos (correos_plan.py) con datos de ejemplo: nunca enlazan a *.easypanel.host.
- Los flujos completos, recorriendo el grafo de plan-*.json con una pequeña máquina de estados en node (como
  N8N/propuestas-v3/probar_revision_fotos.py): los nodos Code corren con su jsCode REAL (el que se publica); los
  HTTP, OpenAI, el webhook y el correo son simulados; las expresiones ={{ … }} de URL, cuerpos e IF se evalúan igual
  que en n8n, así que lo que se revisa es lo que de verdad se enviaría.
Datos de prueba inventados: «Cliente Prueba», «[PRUEBA] …». El repositorio es público.
"""
import json, os, subprocess, sys

sys.stdout.reconfigure(encoding='utf-8')
AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'propuestas-v3'))
sys.path.insert(0, AQUI)
fallas = []
SECCIONES = {}

CIERRE = 'no signs, no labels, no text, no lettering, no logos, no watermarks, no subtitles, no captions'
NUEVAS = ['metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal']
TODAS = ['cover', 'metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal', 'cierre']
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
CRED_WP_ID = '1NI0sJKc0kC430pb'
# Topes que valida WordPress (grupo-A.md, Task 2, «Para la Task 13»; decisión D12): los dos prompts los dicen tal cual.
TOPES_PROMPT = ['13 bloques', '10 actividades por bloque', '58 actividades en total', 'de 1 a 60 días hábiles por actividad',
                '130 días hábiles', '5 días hábiles de revisión por cada bloque con entrega', '10 hitos',
                '"diseno_desarrollo", "implementacion" y "soporte"', '"at", "cliente" o "ambos"',
                'NO incluyas el bloque «Arranque»']


def seccion(nombre):
    def deco(fn):
        SECCIONES[nombre] = fn
        return fn
    return deco


def ok(cond, msg, detalle=''):
    print(('ok    ' if cond else 'FALLA ') + msg + ('' if cond or not detalle else f' ({str(detalle)[:300]})'))
    if not cond:
        fallas.append(msg)


def node_js(programa, datos=None):
    """Corre JavaScript con node (por la entrada estándar: no cabe en la línea de comandos de Windows)."""
    prog = 'const DATOS = ' + json.dumps(datos, ensure_ascii=False) + ';\n' + programa
    r = subprocess.run(['node', '-'], input=prog, capture_output=True, text=True, encoding='utf-8')
    if r.returncode != 0:
        return None, (r.stderr.strip().splitlines() or ['node sin mensaje'])[-1]
    try:
        return json.loads(r.stdout), None
    except ValueError:
        return None, 'salida no es JSON: ' + r.stdout[:200]


def lib():
    from fotos_guard import JS_LIMPIAR_FOTOS
    from json_guard import JS_LEER_JSON
    from plan_js import JS_PLAN
    return JS_LEER_JSON + '\n' + JS_LIMPIAR_FOTOS + '\n' + JS_PLAN + '\n'


def cargar(archivo):
    with open(os.path.join(AQUI, archivo), encoding='utf-8') as fh:
        return json.load(fh)


# ---------------------------------------------------------------- datos de prueba (inventados)

def act(nombre, responsable='at', dias=3, servicio='', etapa='', paralelo=False, origen=None, **extra):
    a = {'nombre': nombre, 'detalle': '', 'responsable': responsable, 'dias_habiles': dias, 'servicio': servicio,
         'etapa': etapa, 'en_paralelo': paralelo}
    if origen:
        a['origen'] = origen
    a.update(extra)
    return a


TABLA = {'sitio_web_tienda': {'nombre': 'Sitio web o tienda', 'diseno': 5, 'desarrollo': 10, 'pruebas': 3, 'implementacion': 2}}
CONTRATO = {'servicios_contratados': '- **Sitio web de prueba**: $1.000.000', 'alcance': 'Sitio de prueba con catálogo.',
            'entregables': 'Sitio publicado', 'fases_siguientes': 'Fase 2: tienda en línea',
            'plazo': 'Se define con EL CLIENTE en la reunión de inicio y queda por escrito.', 'garantia_meses': 6}
PROPUESTA = {'company_name': '[PRUEBA] Panadería', 'solution_text': 'Sitio con catálogo de panes.',
             'how_it_works': ['Catálogo: tus panes en línea'],
             'extracto_reunion': 'Cliente Prueba vende pan amasado en su barrio.'}


def contexto(**cambios):
    base = {'ok': True, 'plan_id': 9, 'codigo': 'PRUEBAplan01', 'estado': 'generando',
            'proyecto': '[PRUEBA] Sitio de la panadería', 'empresa': '[PRUEBA] Panadería', 'cliente': 'Cliente Prueba',
            'fecha_firma': '2026-09-26', 'contrato': CONTRATO, 'propuesta': PROPUESTA, 'tabla': TABLA,
            'slides_foto': TODAS, 'plan_actual': None, 'comentarios': '', 'crm_cliente_id': 5,
            'rubro': '[PRUEBA] Panadería'}
    base.update(cambios)
    return base


PLAN_IA = {
    'version': 1, 'proyecto': '[PRUEBA] Sitio de la panadería',
    'fases': [
        {'clave': 'diseno_desarrollo', 'descripcion': 'Diseñamos y construimos tu sitio.', 'bloques': [
            {'nombre': 'Arranque', 'entregable': '', 'entrega': False, 'actividades': [act('Reunión de inicio', 'ambos', 1)]},
            {'nombre': 'Diseño', 'entregable': 'Diseño del sitio', 'entrega': True, 'actividades': [
                act('Diseño de la portada', 'at', 3, 'sitio_web_tienda', 'diseno', origen='tabla'),
                act('Diseño del catálogo', 'at', 2, 'sitio_web_tienda', 'diseno', True)]},
            {'nombre': 'Desarrollo', 'entregable': 'Sitio para probar', 'entrega': True, 'actividades': [
                act('Construcción del sitio', 'at', 8, 'sitio_web_tienda', 'desarrollo')]}]},
        {'clave': 'implementacion', 'descripcion': 'Publicamos tu sitio.', 'bloques': [
            {'nombre': 'Puesta en marcha', 'entregable': 'Sitio publicado', 'entrega': True, 'actividades': [
                act('Publicación del sitio', 'at', 2, 'sitio_web_tienda', 'implementacion')]}]},
        {'clave': 'soporte', 'descripcion': 'Te acompañamos.', 'bloques': [
            {'nombre': 'Garantía', 'entregable': '', 'entrega': False, 'actividades': [
                act('Acompañamiento y ajustes', 'at', 5, '', 'soporte')]}]},
    ],
    'hitos': [{'nombre': 'Diseño aprobado', 'despues_de': 'Diseño'}],
    'necesitamos_de_ti': ['Logo', 'Textos', 'Fotos de tus productos'],
    'reuniones': [{'nombre': 'Reunión de inicio', 'detalle': ''}, {'nombre': 'Llamada de seguimiento del plan', 'detalle': ''},
                  {'nombre': 'Entrega y capacitación', 'detalle': ''}],
    'soporte': {'garantia_meses': 3, 'mensuales': []},
    'image_briefs': [
        {'slide': 'cover', 'prompt': 'close-up of hands kneading bread dough, background completely blurred'},
        {'slide': 'metodo', 'prompt': 'bakers preparing the counter before opening, warm morning light'},
        {'slide': 'gantt', 'prompt': 'hands shaping loaves one by one on a floured table'},
        {'slide': 'gantt', 'prompt': 'segunda foto de la misma lámina, se descarta'},
        {'slide': 'fase_1', 'prompt': 'flour, eggs and wooden tools laid out on a table'},
        {'slide': 'fase_2', 'prompt': 'baker handing a warm loaf to the first customer of the day'},
        {'slide': 'fase_3', 'prompt': 'baker calmly serving a customer in the afternoon'},
        {'slide': 'necesitamos', 'prompt': 'hands gathering bread baskets on a counter'},
        {'slide': 'reuniones', 'prompt': 'two bakers talking in a meeting at the office'},
        {'slide': 'portal', 'prompt': 'owner resting with a coffee next to a laptop'},
        {'slide': 'cierre', 'prompt': 'bakery team celebrating the end of the day'},
        {'slide': 'challenge', 'prompt': 'lámina de la propuesta, no del plan'},
    ],
}


def brief(slide, tema):
    return {'slide': slide, 'prompt': f'{tema}, {CIERRE}'}


# Plan guardado en WordPress (forma normalizada): Arranque de la tabla, días de Luis, fechas y cronograma.
PLAN_GUARDADO = {
    'version': 1, 'proyecto': '[PRUEBA] Sitio de la panadería', 'fecha_firma': '2026-09-26', 'fecha_inicio': '2026-09-28',
    'fases': [
        {'clave': 'diseno_desarrollo', 'titulo': 'Diseño y desarrollo', 'descripcion': 'Diseñamos y construimos tu sitio.', 'bloques': [
            {'nombre': 'Arranque', 'entregable': '', 'entrega': False, 'actividades': [
                act('Reunión de inicio', 'ambos', 1, '', 'arranque', origen='tabla', desde='2026-09-28', hasta='2026-09-28'),
                act('Entrega de logo, textos y accesos', 'cliente', 3, '', 'arranque', origen='tabla', desde='2026-09-29', hasta='2026-10-01')]},
            {'nombre': 'Diseño', 'entregable': 'Diseño del sitio', 'entrega': True, 'actividades': [
                act('Diseño de la portada', 'at', 7, 'sitio_web_tienda', 'diseno', origen='luis', desde='2026-10-02', hasta='2026-10-12'),
                act('Diseño del catálogo', 'at', 2, 'sitio_web_tienda', 'diseno', True, origen='tabla', desde='2026-10-02', hasta='2026-10-05')]},
            {'nombre': 'Desarrollo', 'entregable': 'Sitio para probar', 'entrega': True, 'actividades': [
                act('Construcción del sitio', 'at', 10, 'sitio_web_tienda', 'desarrollo', origen='tabla', desde='2026-10-20', hasta='2026-11-02')]}]},
        {'clave': 'implementacion', 'titulo': 'Implementación', 'descripcion': 'Publicamos tu sitio.', 'bloques': [
            {'nombre': 'Puesta en marcha', 'entregable': 'Sitio publicado', 'entrega': True, 'actividades': [
                act('Publicación del sitio', 'at', 2, 'sitio_web_tienda', 'implementacion', origen='tabla', desde='2026-11-10', hasta='2026-11-11')]}]},
        {'clave': 'soporte', 'titulo': 'Soporte y mejora continua', 'descripcion': 'Te acompañamos.', 'bloques': [
            {'nombre': 'Garantía', 'entregable': '', 'entrega': False, 'actividades': [
                act('Acompañamiento y ajustes', 'at', 5, '', 'soporte', origen='ia', desde='2026-11-19', hasta='2026-11-25')]}]},
    ],
    'hitos': [{'nombre': 'Diseño aprobado', 'despues_de': 'Diseño', 'fecha': '2026-10-19'}],
    'necesitamos_de_ti': ['Logo', 'Textos'],
    'reuniones': [{'nombre': 'Reunión de inicio', 'detalle': ''}],
    'soporte': {'garantia_meses': 3, 'mensuales': []},
    'image_briefs': [brief('metodo', 'bakers preparing the counter before opening, warm morning light'),
                     brief('gantt', 'hands shaping loaves one by one on a floured table')],
    'cronograma': {'inicio': '2026-09-28', 'fin': '2026-11-25', 'semanas': 9, 'barras': [], 'hitos': []},
}


def actividades(plan):
    return [(f['clave'], b['nombre'], a) for f in plan['fases'] for b in f['bloques'] for a in b['actividades']]


def actividad(plan, nombre):
    return next((a for _, _, a in actividades(plan) if a['nombre'] == nombre), None)


# ---------------------------------------------------------------- simulador de flujos n8n

SIM = r"""
const { wf, esc: E } = DATOS;
const nodos = Object.fromEntries(wf.nodes.map((n) => [n.name, n]));
const runData = {};
const T = { pasos: [], http: {}, openai: {}, correos: [], error: null };
const cuenta = {};
const $execution = { id: 'SIM-1' };
const $ = (n) => ({
  get isExecuted() { return !!(runData[n] && runData[n].length); },
  first: () => {
    if (!runData[n] || !runData[n].length) throw new Error('Nodo sin ejecutar: ' + n);
    return runData[n][runData[n].length - 1][0];
  },
});
function evaluar(expr, $json, $input) {
  if (typeof expr !== 'string' || !expr.startsWith('=')) return expr;
  const s = expr.slice(1);
  const ev = (code) => new Function('$', '$json', '$input', '$execution', 'return (' + code + ');')($, $json, $input, $execution);
  const uno = s.match(/^\{\{([\s\S]*)\}\}$/);
  if (uno && !uno[1].includes('{{')) return ev(uno[1]);
  return s.replace(/\{\{([\s\S]*?)\}\}/g, (_, code) => String(ev(code)));
}
function siguiente(lista, nombre) {
  const l = (lista || {})[nombre];
  if (!Array.isArray(l) || !l.length) throw new Error('Respuesta sin simular para: ' + nombre);
  cuenta[nombre] = (cuenta[nombre] || 0) + 1;
  return JSON.parse(JSON.stringify(l[Math.min(cuenta[nombre] - 1, l.length - 1)]));
}
function correr(nombre, items) {
  const n = nodos[nombre];
  if (!n) throw new Error('Conexión a un nodo que no existe: ' + nombre);
  const $json = items[0] ? items[0].json : {};
  const $input = { first: () => items[0], all: () => items, item: items[0] };
  const $runIndex = (runData[nombre] || []).length;
  const p = n.parameters;
  let salidas;
  if (n.type === 'n8n-nodes-base.webhook') {
    salidas = [[{ json: { headers: {}, body: E.webhook } }]];
  } else if (n.type === 'n8n-nodes-base.code') {
    const fn = new Function('$', '$json', '$input', '$runIndex', '$execution', p.jsCode);
    salidas = [fn($, $json, $input, $runIndex, $execution)];
  } else if (n.type === 'n8n-nodes-base.httpRequest') {
    const url = evaluar(p.url, $json, $input);
    const cuerpo = p.sendBody ? JSON.parse(evaluar(p.jsonBody, $json, $input)) : undefined;
    (T.http[nombre] = T.http[nombre] || []).push({ url, body: cuerpo, method: p.method });
    salidas = [[{ json: siguiente(E.http, nombre) }]];
  } else if (n.type === 'n8n-nodes-base.openAi') {
    const msgs = p.prompt.messages;
    (T.openai[nombre] = T.openai[nombre] || []).push({ model: p.model, temperature: p.options.temperature,
      system: msgs[0].content, user: evaluar(msgs[1].content, $json, $input) });
    const r = siguiente(E.openai, nombre);
    // {content} = respuesta; {error} = falla con el detalle en json; {} = falla sin detalle (el nodo declarativo
    // de OpenAI con continueRegularOutput puede dejar el error fuera de json y entregar {}).
    salidas = [[{ json: r.error ? { error: r.error } : ('content' in r ? { message: { role: 'assistant', content: r.content } } : {}) }]];
  } else if (n.type === 'n8n-nodes-base.if') {
    const v = evaluar(p.conditions.conditions[0].leftValue, $json, $input);
    salidas = (v === true || v === 'true') ? [items, []] : [[], items];
  } else if (n.type === 'n8n-nodes-base.emailSend') {
    T.correos.push({ para: p.toEmail, asunto: evaluar(p.subject, $json, $input), html: evaluar(p.html, $json, $input) });
    salidas = [items];
  } else {
    throw new Error('tipo sin simular: ' + n.type);
  }
  runData[nombre] = runData[nombre] || [];
  runData[nombre].push(salidas.flat());
  T.salidas = T.salidas || {};
  T.salidas[nombre] = salidas.flat().map((i) => i.json);
  return salidas;
}
try {
  const cola = [['Webhook', [{ json: {} }]]];
  let pasos = 0;
  while (cola.length) {
    if (++pasos > 300) throw new Error('el flujo no termina (más de 300 pasos)');
    const [nombre, items] = cola.shift();
    T.pasos.push(nombre);
    const salidas = correr(nombre, items);
    const con = (wf.connections[nombre] || { main: [] }).main;
    salidas.forEach((its, i) => {
      if (!its || !its.length) return;
      for (const d of con[i] || []) cola.push([d.node, its]);
    });
  }
} catch (e) {
  T.error = e.message;
}
console.log(JSON.stringify(T));
"""


def simular(archivo, **esc):
    t, err = node_js(SIM, {'wf': cargar(archivo), 'esc': esc})
    return t if t is not None else {'error': err, 'pasos': [], 'http': {}, 'openai': {}, 'correos': [], 'salidas': {}}


def llamadas(t, nodo):
    return t.get('http', {}).get(nodo, [])


def sin_error(t, caso):
    ok(not t.get('error'), f'{caso}: el recorrido termina sin lanzar', t.get('error'))


def revisar_workflow(archivo, nombre, path):
    wf = cargar(archivo)
    nombres = [n['name'] for n in wf['nodes']]
    ok(wf['name'] == nombre, f'{archivo}: se llama «{nombre}»', wf['name'])
    ok(len(nombres) == len(set(nombres)), f'{archivo}: nombres de nodo únicos')
    ok(wf['settings'].get('errorWorkflow') == 'm7TOfKznVSBGz4Nd', f'{archivo}: usa el flujo común de avisos de error')
    destinos = [d['node'] for c in wf['connections'].values() for rama in c['main'] for d in rama]
    ok(all(d in nombres for d in destinos) and all(o in nombres for o in wf['connections']),
       f'{archivo}: toda conexión une nodos que existen')
    hook = next(n for n in wf['nodes'] if n['type'] == 'n8n-nodes-base.webhook')
    hp = hook['parameters']
    ok(hp['path'] == path and hp['authentication'] == 'headerAuth' and hp['responseMode'] == 'onReceived'
       and hook['credentials']['httpHeaderAuth']['id'] == CRED_WP_ID and hook.get('webhookId') == path,
       f'{archivo}: webhook {path} con la credencial «AT REST Secret (header)»')
    for n in wf['nodes']:
        if n['type'] == 'n8n-nodes-base.httpRequest' and n['parameters']['url'].startswith('=' + WP):
            ok(n['credentials']['httpHeaderAuth']['id'] == CRED_WP_ID and n.get('onError') == 'continueRegularOutput'
               and n['parameters']['options']['response']['response'] == {'fullResponse': True, 'neverError': True},
               f'{archivo}: «{n["name"]}» va a WordPress con X-AT-Secret y nunca corta el flujo')
        if n['type'] == 'n8n-nodes-base.emailSend':
            ok(n['parameters']['toEmail'] == 'lmgm.0303@gmail.com', f'{archivo}: «{n["name"]}» le escribe solo a Luis')
        if n['type'] == 'n8n-nodes-base.openAi':
            ok(n['credentials']['openAiApi']['id'] == 'g52IEXpRfN5r7jKw' and n.get('onError') == 'continueRegularOutput',
               f'{archivo}: «{n["name"]}» usa la credencial de OpenAI y una falla no corta el flujo')
    return wf


# ---------------------------------------------------------------- secciones de la Task 13

@seccion('puras')
def prueba_puras():
    base = lib()
    prog = base + r"""
const R = {};
R.slides_con = slidesConFoto(true, 3);
R.slides_sin = slidesConFoto(false, 3);
R.slides_dos = slidesConFoto(true, 2);
const b = DATOS.briefs;
R.fotos_con = fotosDelPlan(b, 3, true);
R.fotos_sin = fotosDelPlan(b, 3, false);
R.fotos_dos = fotosDelPlan(b, 2, true);
R.fotos_dos_veces = fotosDelPlan(R.fotos_sin, 3, false);
R.fotos_basura = fotosDelPlan([null, 'x', { slide: 'metodo' }, { slide: 'gantt', prompt: 7 }, { slide: 'portal', prompt: '   ' }], 3, true);
const leer = (content, op) => leerPlanModelo({ message: { content } }, op);
const B = { modo: 'borrador', hayPropuesta: true };
R.ok = leer(JSON.stringify(DATOS.plan), B);
R.llave = leer(JSON.stringify(DATOS.plan) + '}', B);
R.envuelto = leer(JSON.stringify({ plan: DATOS.plan }), B);
R.texto = leer('Aquí va el plan:\n' + JSON.stringify(DATOS.plan), B);
R.arreglo = leer('[' + JSON.stringify(DATOS.plan) + ']', B);
R.nulo = leer('null', B);
R.ajenas = leer(JSON.stringify(Object.assign({ nota: 'x' }, DATOS.plan)), B);
R.sin_fases = leer(JSON.stringify({ proyecto: 'x', fases: [] }), B);
R.fase_rara = leer(JSON.stringify({ fases: [{ clave: 'diseno_desarrollo', bloques: 'no' }] }), B);
R.solo_arranque = leer(JSON.stringify({ fases: [{ clave: 'diseno_desarrollo', bloques: [DATOS.plan.fases[0].bloques[0]] },
  { clave: 'implementacion', bloques: [] }] }), B);
R.vacia = leerPlanModelo({ message: { content: '' } }, B);
R.caida = leerPlanModelo({ error: { message: 'Rate limit' } }, B);
R.cortada = leer(JSON.stringify(DATOS.plan).slice(0, 400), B);
const C = { modo: 'cambios', hayPropuesta: true, anterior: DATOS.guardado };
const cambiado = JSON.parse(JSON.stringify(DATOS.guardado));
cambiado.fases[0].bloques[1].actividades[0].dias_habiles = 2;
cambiado.fases[0].bloques[1].actividades[0].origen = 'ia';
cambiado.fases[0].bloques[2].actividades[0].origen = 'luis';
cambiado.fases[0].bloques[2].actividades[0].dias_habiles = 12;
cambiado.fases[1].bloques[0].actividades.push({ nombre: 'Capacitación del equipo', responsable: 'ambos', dias_habiles: 1, origen: 'luis' });
delete cambiado.image_briefs;
R.cambios = leer(JSON.stringify(cambiado), C);
R.cambios_vacio = leer('{}', C);
R.cambios_nota = leer('{"nota": "no hay cambios"}', C);
R.cambios_clave_previa = leer(JSON.stringify({ extra_wp: 1, fases: DATOS.guardado.fases }), Object.assign({}, C,
  { anterior: Object.assign({ extra_wp: 0 }, DATOS.guardado) }));
R.cambios_ajena = leer(JSON.stringify({ nota: 'x', fases: DATOS.guardado.fases }), C);
// Luis pidió «pasa la portada a Desarrollo»: el modelo la movió de bloque y le cambió los días.
const movido = JSON.parse(JSON.stringify(DATOS.guardado));
const mov = movido.fases[0].bloques[1].actividades.shift();
mov.dias_habiles = 2;
mov.origen = 'ia';
movido.fases[0].bloques[2].actividades.push(mov);
R.cambios_movida = leer(JSON.stringify(movido), C);
// Respuesta parcial: solo la fase que cambió y solo la foto pedida.
const impl = JSON.parse(JSON.stringify(DATOS.guardado.fases[1]));
impl.bloques[0].actividades.push({ nombre: 'Capacitación del equipo', responsable: 'ambos', dias_habiles: 1 });
R.cambios_parcial = leer(JSON.stringify({ fases: [impl],
  image_briefs: [{ slide: 'metodo', prompt: 'baker opening the shutters of the bakery at dawn' }] }), C);
// Dos actividades con el mismo nombre: se reconocen por orden de aparición, como at_pt_recorrer_actividades (Task 2).
const repetida = JSON.parse(JSON.stringify(DATOS.guardado));
repetida.fases[0].bloques[1].actividades.push({ nombre: 'Revisión interna', responsable: 'at', dias_habiles: 4, origen: 'luis' });
repetida.fases[0].bloques[2].actividades.push({ nombre: 'Revisión interna', responsable: 'at', dias_habiles: 2, origen: 'ia' });
const repetidaIa = JSON.parse(JSON.stringify(repetida));
repetidaIa.fases[0].bloques[1].actividades[2].dias_habiles = 1;
repetidaIa.fases[0].bloques[2].actividades[1].dias_habiles = 3;
R.cambios_repetida = leer(JSON.stringify(repetidaIa), Object.assign({}, C, { anterior: repetida }));
// «Arranque» (D12): al modelo no se le manda; si no lo devuelve, vuelve el guardado; si manda uno propio, se descarta.
const sinArr = quitarArranque(JSON.parse(JSON.stringify(DATOS.guardado)));
R.quitado = sinArr.fases.map((f) => f.bloques.map((b) => b.nombre));
R.guardado_intacto = DATOS.guardado.fases[0].bloques[0].nombre;
R.cambios_sin_arranque = leer(JSON.stringify({ fases: sinArr.fases }), C);
const conSuyo = JSON.parse(JSON.stringify(DATOS.guardado));
conSuyo.fases[0].bloques[0].actividades[1].dias_habiles = 9;
conSuyo.fases[0].bloques[0].actividades.push({ nombre: 'Visita al local', responsable: 'ambos', dias_habiles: 2 });
conSuyo.fases[1].bloques.unshift({ nombre: 'Arranque', entrega: false, actividades: [{ nombre: 'Otra reunión', responsable: 'ambos', dias_habiles: 1 }] });
R.cambios_arranque_ia = leer(JSON.stringify(conSuyo), C);
const luisEnArranque = JSON.parse(JSON.stringify(DATOS.guardado));
Object.assign(luisEnArranque.fases[0].bloques[0].actividades[1], { dias_habiles: 5, origen: 'luis' });
R.cambios_arranque_luis = leer(JSON.stringify({ fases: sinArr.fases }), Object.assign({}, C, { anterior: luisEnArranque }));
console.log(JSON.stringify(R));
"""
    r, err = node_js(prog, {'briefs': PLAN_IA['image_briefs'], 'plan': PLAN_IA, 'guardado': PLAN_GUARDADO})
    ok(r is not None, 'puras: el JavaScript de plan_js corre con node', err)
    if r is None:
        return
    ok(r['slides_con'] == NUEVAS, 'slidesConFoto: con propuesta, solo las 8 láminas nuevas', r['slides_con'])
    ok(r['slides_sin'] == TODAS, 'slidesConFoto: sin propuesta, también portada (primera) y cierre (última)', r['slides_sin'])
    ok('fase_3' not in r['slides_dos'] and 'fase_2' in r['slides_dos'], 'slidesConFoto: con 2 fases no pide fase_3', r['slides_dos'])
    s_con = [b['slide'] for b in r['fotos_con']]
    ok(s_con == NUEVAS, 'fotosDelPlan: con propuesta descarta portada, cierre, láminas ajenas y la foto repetida', s_con)
    ok([b['slide'] for b in r['fotos_sin']] == ['cover'] + NUEVAS + ['cierre'],
       'fotosDelPlan: sin propuesta conserva portada y cierre', [b['slide'] for b in r['fotos_sin']])
    ok('fase_3' not in [b['slide'] for b in r['fotos_dos']], 'fotosDelPlan: con 2 fases descarta fase_3')
    ok(all(b['prompt'].endswith(CIERRE) and b['prompt'].count('no subtitles') == 1 for b in r['fotos_sin']),
       'fotosDelPlan: toda descripción termina con el cierre de prohibiciones, una sola vez')
    gantt = next(b for b in r['fotos_con'] if b['slide'] == 'gantt')
    ok(gantt['prompt'].startswith('hands shaping loaves'), 'fotosDelPlan: de una lámina repetida queda la primera', gantt['prompt'])
    reu = next(b for b in r['fotos_con'] if b['slide'] == 'reuniones')
    ok('meeting' not in reu['prompt'] and 'office' not in reu['prompt'],
       'fotosDelPlan: «reunión en la oficina» se reemplaza por una escena neutra (fotos_guard)', reu['prompt'])
    portal = next(b for b in r['fotos_con'] if b['slide'] == 'portal')
    ok('facing away' in portal['prompt'], 'fotosDelPlan: el computador del portal queda apagado y de espaldas', portal['prompt'])
    cover = next(b for b in r['fotos_sin'] if b['slide'] == 'cover')
    ok('close-up' in cover['prompt'] or 'blurred' in cover['prompt'], 'fotosDelPlan: la portada va en primer plano', cover['prompt'])
    ok(r['fotos_dos_veces'] == r['fotos_sin'], 'fotosDelPlan: filtrar dos veces no cambia nada (el renderer reutiliza por hash)')
    ok(r['fotos_basura'] == [], 'fotosDelPlan: descarta entradas sin lámina o sin descripción', r['fotos_basura'])

    plan = (r['ok'] or {}).get('plan') or {}
    ok(r['ok']['ok'] is True and r['ok']['reason'] == '', 'leerPlanModelo: JSON limpio → ok', r['ok'].get('reason'))
    nombres_b = [b['nombre'] for b in plan.get('fases', [{}])[0].get('bloques', [])]
    ok(nombres_b == ['Diseño', 'Desarrollo'], 'leerPlanModelo (borrador): descarta el «Arranque» del modelo', nombres_b)
    ok(all(a.get('origen') == 'ia' for _, _, a in actividades(plan)) and plan.get('fases'),
       'leerPlanModelo (borrador): toda actividad queda con origen «ia» (el modelo no puede declarar «tabla» ni «luis»)')
    ok([b['slide'] for b in plan.get('image_briefs', [])] == NUEVAS, 'leerPlanModelo (borrador): fotos filtradas', plan.get('image_briefs'))
    ok(r['llave']['ok'] is True, 'leerPlanModelo: tolera una llave «}» de más al final (json_guard)')
    ok(r['envuelto']['ok'] is True and r['envuelto']['plan']['proyecto'] == PLAN_IA['proyecto'], 'leerPlanModelo: desenvuelve {"plan": …}')
    aj = r['ajenas']
    ok(aj['ok'] is True and 'nota' not in (aj['plan'] or {}) and (aj['plan'] or {}).get('fases'),
       'leerPlanModelo (borrador): una clave ajena de primer nivel se descarta sin botar el plan (WordPress arma el plan solo con claves conocidas)', aj.get('reason'))
    for caso, inicio in (('texto', 'La respuesta del modelo no es un JSON válido'), ('arreglo', 'La respuesta del modelo no es un objeto JSON'),
                         ('nulo', 'La respuesta del modelo no es un objeto JSON'),
                         ('sin_fases', 'El plan del modelo no trae fases'), ('fase_rara', 'El plan del modelo trae una fase sin su lista de bloques'),
                         ('solo_arranque', 'El modelo no propuso ninguna actividad'), ('vacia', 'El modelo no respondió'),
                         ('caida', 'El modelo no respondió: Rate limit'), ('cortada', 'La respuesta del modelo no es un JSON válido')):
        ok(r[caso]['ok'] is False and r[caso]['plan'] is None and r[caso]['reason'].startswith(inicio),
           f'leerPlanModelo: {caso} → error legible «{inicio}…»', r[caso])

    c = r['cambios']
    pc = c.get('plan') or {}
    ok(c['ok'] is True, 'leerPlanModelo (cambios): respuesta completa → ok', c.get('reason'))
    portada = actividad(pc, 'Diseño de la portada') if pc else None
    ok(portada and portada['dias_habiles'] == 7 and portada['origen'] == 'luis',
       'protegerLuis: los días que Luis puso a mano (7) no se pisan aunque el modelo los cambie a 2', portada)
    ok(c['protegidas'] == 1, 'protegerLuis: cuenta la actividad de Luis restituida', c['protegidas'])
    constr = actividad(pc, 'Construcción del sitio') if pc else None
    ok(constr and constr['origen'] == 'ia' and constr['dias_habiles'] == 12,
       'protegerLuis: el modelo no puede declarar «luis»; la actividad de la tabla a la que le cambió los días queda «ia» (revisar)', constr)
    catalogo = actividad(pc, 'Diseño del catálogo') if pc else None
    ok(catalogo and catalogo['origen'] == 'tabla' and catalogo['dias_habiles'] == 2,
       'protegerLuis: una actividad de la tabla con los mismos días conserva «tabla»', catalogo)
    nueva = actividad(pc, 'Capacitación del equipo') if pc else None
    ok(nueva and nueva['origen'] == 'ia', 'protegerLuis: una actividad que agregó el modelo queda «ia» aunque diga «luis»', nueva)
    arr = [a['origen'] for f, b, a in actividades(pc) if b == 'Arranque'] if pc else []
    ok(arr == ['tabla', 'tabla'], 'leerPlanModelo (cambios): el bloque «Arranque» se conserva con origen «tabla»', arr)
    ok(pc.get('image_briefs') == PLAN_GUARDADO['image_briefs'], 'leerPlanModelo (cambios): lo que el modelo omite se conserva del plan guardado')
    ok(r['cambios_vacio']['ok'] is False and 'no trae ninguna parte del plan' in r['cambios_vacio']['reason'],
       'leerPlanModelo (cambios): {} → error, no un «cambio» vacío')
    ok(r['cambios_nota']['ok'] is False and 'claves que no son del plan: nota' in r['cambios_nota']['reason'],
       'leerPlanModelo (cambios): una nota suelta no pasa como cambio aplicado')
    ok(r['cambios_clave_previa']['ok'] is True, 'leerPlanModelo (cambios): acepta una clave que ya traía el plan guardado')
    ok(r['cambios_ajena']['ok'] is False and 'claves que no son del plan: nota' in r['cambios_ajena']['reason'],
       'leerPlanModelo (cambios): una nota junto a las fases tampoco pasa (puede ser un «antes/después»)')
    pm = r['cambios_movida'].get('plan') or {'fases': []}
    mv = actividad(pm, 'Diseño de la portada')
    en_desarrollo = [a['nombre'] for f, b, a in actividades(pm) if b == 'Desarrollo']
    ok(mv and mv['dias_habiles'] == 7 and mv['origen'] == 'luis' and 'Diseño de la portada' in en_desarrollo,
       'protegerLuis: si el modelo mueve de bloque una actividad de Luis, sus días (7) se conservan', mv)
    pp = r['cambios_parcial'].get('plan') or {'fases': []}
    ok(r['cambios_parcial']['ok'] is True and [f['clave'] for f in pp['fases']] == ['diseno_desarrollo', 'implementacion', 'soporte'],
       'leerPlanModelo (cambios): si el modelo devuelve solo la fase que cambió, las otras fases se conservan', [f.get('clave') for f in pp['fases']])
    pp_portada = actividad(pp, 'Diseño de la portada')
    ok(pp_portada and pp_portada['dias_habiles'] == 7 and pp_portada['origen'] == 'luis'
       and (actividad(pp, 'Capacitación del equipo') or {}).get('origen') == 'ia',
       'leerPlanModelo (cambios): respuesta parcial → los días de Luis siguen y la actividad nueva entra «ia»', pp_portada)
    fotos_pp = pp.get('image_briefs') or []
    ok([b['slide'] for b in fotos_pp] == ['metodo', 'gantt'] and fotos_pp[0]['prompt'] == 'baker opening the shutters of the bakery at dawn, ' + CIERRE
       and fotos_pp[1] == PLAN_GUARDADO['image_briefs'][1],
       'leerPlanModelo (cambios): si el modelo devuelve solo la foto pedida, las demás quedan idénticas (mismo hash)', fotos_pp)
    pr = r['cambios_repetida'].get('plan') or {'fases': []}
    rep = [(b, a['dias_habiles'], a['origen']) for f, b, a in actividades(pr) if a['nombre'] == 'Revisión interna']
    ok(rep == [('Diseño', 4, 'luis'), ('Desarrollo', 3, 'ia')],
       'protegerLuis: dos actividades con el mismo nombre se reconocen por orden de aparición (como at_pt_recorrer_actividades)', rep)
    ok(r['quitado'] == [['Diseño', 'Desarrollo'], ['Puesta en marcha'], ['Garantía']] and r['guardado_intacto'] == 'Arranque',
       'quitarArranque: saca solo el bloque «Arranque» de la copia que recibe', r['quitado'])
    guardado_arr = PLAN_GUARDADO['fases'][0]['bloques'][0]
    ps = r['cambios_sin_arranque'].get('plan') or {'fases': [{'bloques': [{}]}]}
    ok(r['cambios_sin_arranque']['ok'] is True and ps['fases'][0]['bloques'][0] == guardado_arr
       and [b['nombre'] for f in ps['fases'] for b in f['bloques']].count('Arranque') == 1,
       'restituirArranque: si el modelo no manda el «Arranque», vuelve el guardado, igual y en su lugar', ps['fases'][0]['bloques'][0])
    pa_ = r['cambios_arranque_ia'].get('plan') or {'fases': [{'bloques': [{}]}, {'bloques': []}]}
    ok(pa_['fases'][0]['bloques'][0] == guardado_arr and [b['nombre'] for b in pa_['fases'][1]['bloques']] == ['Puesta en marcha']
       and actividad(pa_, 'Visita al local') is None and actividad(pa_, 'Otra reunión') is None,
       'restituirArranque: el «Arranque» que manda el modelo (con días o actividades cambiados, o en otra fase) se descarta', pa_['fases'][0]['bloques'][0])
    pl = r['cambios_arranque_luis'].get('plan') or {'fases': []}
    ent = actividad(pl, 'Entrega de logo, textos y accesos') or {}
    ok(ent.get('dias_habiles') == 5 and ent.get('origen') == 'luis',
       'restituirArranque: lo que Luis editó en el «Arranque» (5 días, «luis») se conserva', ent)


# ==== Las tareas siguientes agregan sus secciones justo antes de esta línea ====

if __name__ == '__main__':
    pedidas = sys.argv[1:] or list(SECCIONES)
    desconocidas = [s for s in pedidas if s not in SECCIONES]
    if desconocidas:
        print('secciones desconocidas:', ', '.join(desconocidas), '— hay:', ', '.join(SECCIONES))
        sys.exit(2)
    for s in pedidas:
        print(f'== {s}')
        try:
            SECCIONES[s]()
        except Exception as e:  # una sección que lanza cuenta como falla y no oculta las demás
            ok(False, f'{s}: la sección lanzó {type(e).__name__}: {e}')
    print('TODO OK' if not fallas else f'{len(fallas)} FALLA(S)')
    sys.exit(1 if fallas else 0)
```

- [ ] **Step 2: Correr `puras` y verla fallar**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
python N8N/plan-trabajo/probar_plan.py puras; echo "exit=$?"
```
Expected (falla porque `plan_js.py` no existe):
```
== puras
FALLA puras: la sección lanzó ModuleNotFoundError: No module named 'plan_js'
1 FALLA(S)
exit=1
```

- [ ] **Step 3: Escribir `N8N/plan-trabajo/plan_js.py`**
```python
"""JavaScript que comparten los flujos n8n del plan de trabajo (1 Borrador, 2 Cambios y 3 Render).

Va embebido en los nodos Code. Necesita, antes en el mismo nodo, JS_LEER_JSON (json_guard.py: leerJsonModelo y
esObjetoPlano) y JS_LIMPIAR_FOTOS (fotos_guard.py: limpiarFotos), que viven en N8N/propuestas-v3.

Reparto de responsabilidades: WordPress (at_pt_validar_plan) es quien decide si un plan es válido (claves de fase,
responsables, días de 1 a 60, largo de los textos) y rechaza con HTTP 422. Aquí solo se revisa la FORMA mínima
para no mandarle basura: que la respuesta sea un objeto JSON del plan, con fases y al menos una actividad; en
«cambios» se une con el plan guardado (lo que el modelo omite se conserva); el bloque «Arranque» del modelo se descarta
(en «cambios» vuelve el guardado; en «borrador» lo pone WordPress); se fija el origen de cada actividad, se
protegen los días que Luis editó a mano y se filtran las descripciones de fotos (solo láminas nuevas del plan, una
por lámina, con las reglas de fotos_guard). Nunca lanza: devuelve {ok, reason, plan, protegidas} para que el flujo
pase el plan a «error» con un motivo legible.
"""

JS_PLAN = r"""
const CLAVES_PLAN = ['version', 'proyecto', 'fecha_firma', 'fecha_inicio', 'fases', 'hitos', 'necesitamos_de_ti',
  'reuniones', 'soporte', 'image_briefs', 'cronograma'];
// Láminas con foto NUEVA (decisión 7). Portada y cierre solo si el contrato no tiene propuesta: si la tiene,
// se reutilizan sus fotos (portada ← cover, cierre ← next_steps) y no se pagan.
const SLIDES_NUEVAS = ['metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal'];
const SLIDES_DE_PROPUESTA = { cover: 'cover', cierre: 'next_steps' };
const normPlan = (s) => String(s == null ? '' : s).trim().toLowerCase().replace(/\s+/g, ' ');

function slidesConFoto(hayPropuesta, nFases) {
  const n = Math.max(0, Math.min(3, Number(nFases) || 0));
  const l = SLIDES_NUEVAS.filter((s) => !/^fase_\d$/.test(s) || Number(s.slice(5)) <= n);
  return hayPropuesta ? l : ['cover', ...l, 'cierre'];
}

function recorrerActividades(plan, fn) {
  const fases = plan && Array.isArray(plan.fases) ? plan.fases : [];
  for (const f of fases) {
    if (!esObjetoPlano(f) || !Array.isArray(f.bloques)) continue;
    for (const b of f.bloques) {
      if (!esObjetoPlano(b) || !Array.isArray(b.actividades)) continue;
      for (const a of b.actividades) if (esObjetoPlano(a)) fn(a, b, f);
    }
  }
}

// El bloque «Arranque» (reunión de inicio y entrega de logo, textos y accesos) lo pone WordPress (at_pt_arranque, con
// los días de la tabla) y Luis lo puede editar en el panel: el modelo nunca lo manda ni lo cambia (decisión D12).
const esArranque = (b) => esObjetoPlano(b) && normPlan(b.nombre) === 'arranque';

// Saca los bloques «Arranque» de todas las fases. Cambia el plan que recibe y lo devuelve.
function quitarArranque(plan) {
  for (const f of (plan && Array.isArray(plan.fases) ? plan.fases : [])) {
    if (esObjetoPlano(f) && Array.isArray(f.bloques)) f.bloques = f.bloques.filter((b) => !esArranque(b));
  }
  return plan;
}

// Cambios: descarta el «Arranque» que haya mandado el modelo y pone, al comienzo de la misma fase donde estaba, el que
// está guardado (con lo que Luis haya editado en él). Si la fase no vino en la respuesta, unirPor ya dejó la guardada.
// Cambia el plan que recibe y lo devuelve.
function restituirArranque(plan, anterior) {
  const guardados = new Map();
  for (const f of (anterior && Array.isArray(anterior.fases) ? anterior.fases : [])) {
    if (!esObjetoPlano(f) || !Array.isArray(f.bloques)) continue;
    const a = f.bloques.filter(esArranque);
    if (a.length && !guardados.has(f.clave)) guardados.set(f.clave, a);
  }
  quitarArranque(plan);
  for (const f of (plan && Array.isArray(plan.fases) ? plan.fases : [])) {
    if (!esObjetoPlano(f) || !Array.isArray(f.bloques) || !guardados.has(f.clave)) continue;
    f.bloques = JSON.parse(JSON.stringify(guardados.get(f.clave))).concat(f.bloques);
    guardados.delete(f.clave);
  }
  return plan;
}

// Solo láminas del plan, una foto por lámina y el filtro de fotos_guard (idempotente: volver a filtrar una
// descripción ya filtrada no la cambia, así el renderer reutiliza la foto por su hash y no la vuelve a cobrar).
function fotosDelPlan(briefs, nFases, hayPropuesta) {
  const validas = new Set(slidesConFoto(hayPropuesta, nFases));
  const vistas = new Set();
  const elegidas = (Array.isArray(briefs) ? briefs : []).filter((b) => esObjetoPlano(b) && typeof b.slide === 'string'
    && typeof b.prompt === 'string' && b.prompt.trim() !== '' && validas.has(b.slide) && !vistas.has(b.slide)
    && vistas.add(b.slide));
  return limpiarFotos(elegidas.map((b) => ({ slide: b.slide, prompt: b.prompt }))).limpias;
}

// Cambios: una actividad se reconoce por su nombre (sin mayúsculas ni espacios de más) y por cuántas veces apareció
// antes ese nombre en el plan: la misma clave que at_pt_recorrer_actividades de WordPress (Task 2). Moverla de bloque
// no la hace nueva; renombrarla, sí (por eso el prompt le prohíbe al modelo renombrar las de Luis). Si Luis le puso
// los días a mano (origen «luis»), vuelven a los guardados aunque el modelo los haya cambiado. Una actividad que ya
// existía conserva su origen si el modelo no le cambió los días; si se los cambió, o si es nueva, queda «ia»
// (revisar), como la marca WordPress (at_pt_marcar_ediciones(…, 'ia')): el modelo nunca declara «luis» ni «tabla».
// Devuelve cuántas actividades de Luis traían días distintos.
function protegerLuis(plan, anterior) {
  const contador = () => {
    const vistas = new Map();
    return (a) => {
      const n = normPlan(a.nombre);
      vistas.set(n, (vistas.get(n) || 0) + 1);
      return n + '#' + vistas.get(n);
    };
  };
  const previas = new Map();
  const claveAnterior = contador();
  recorrerActividades(anterior, (a) => { previas.set(claveAnterior(a), a); });
  const claveNueva = contador();
  let protegidas = 0;
  recorrerActividades(plan, (a) => {
    const p = previas.get(claveNueva(a));
    if (!p) { a.origen = 'ia'; return; }
    const mismosDias = Number(a.dias_habiles) === Number(p.dias_habiles);
    if (p.origen === 'luis') {
      if (!mismosDias) protegidas++;
      a.dias_habiles = p.dias_habiles;
      a.origen = 'luis';
    } else {
      a.origen = p.origen === 'tabla' && mismosDias ? 'tabla' : 'ia';
    }
  });
  return protegidas;
}

// Une dos listas de objetos por un campo (fases por «clave», fotos por «slide»): cada elemento guardado se reemplaza,
// en su lugar, por el que trae el modelo con el mismo campo; los que el modelo agrega van al final. Si alguna de las
// dos no es una lista o el modelo la manda vacía, queda la del modelo tal cual (la validación decide).
function unirPor(antes, nuevos, campo) {
  if (nuevos === undefined) return antes;
  if (!Array.isArray(antes) || !Array.isArray(nuevos) || !nuevos.length) return nuevos;
  const dados = new Map(nuevos.filter(esObjetoPlano).map((x) => [x[campo], x]));
  const previos = antes.filter(esObjetoPlano);
  return previos.map((x) => (dados.has(x[campo]) ? dados.get(x[campo]) : x))
    .concat(nuevos.filter((x) => !esObjetoPlano(x) || !previos.some((p) => p[campo] === x[campo])));
}

// ia = salida del nodo OpenAI ({message: {content}} o {error} con onError=continue).
// op = {modo: 'borrador'|'cambios', hayPropuesta: bool, anterior: plan guardado (solo en cambios)}.
function leerPlanModelo(ia, op) {
  const mal = (reason) => ({ ok: false, reason, plan: null, protegidas: 0 });
  const contenido = ia && ia.message ? ia.message.content : null;
  if (contenido == null || String(contenido).trim() === '') {
    const err = ia && ia.error ? (ia.error.message || String(ia.error)) : '';
    return mal('El modelo no respondió' + (err ? ': ' + err : ''));
  }
  let r;
  try {
    r = leerJsonModelo(contenido);
  } catch (e) {
    return mal('La respuesta del modelo no es un JSON válido (' + (e && e.message ? e.message : String(e)) + ')');
  }
  if (!esObjetoPlano(r)) return mal('La respuesta del modelo no es un objeto JSON');
  // El modelo a veces imita la forma de la entrada y envuelve el plan: {"plan": …} o {"plan_actual": …}.
  for (const k of ['plan', 'plan_actual']) {
    if (esObjetoPlano(r[k]) && Object.keys(r).every((x) => x === k || x === 'comentarios')) { r = r[k]; break; }
  }
  // Claves que no son del plan. En cambios, una nota o un «antes/después» no pasan como cambio aplicado: toda clave
  // debe ser del plan (o una que ya traía el plan guardado). En borrador se descartan: WordPress arma el plan solo con
  // las claves que conoce, y una nota suelta o la plantilla del prompt igual fallan por no traer fases o actividades.
  const cambios = op.modo === 'cambios';
  const previo = cambios && esObjetoPlano(op.anterior) ? op.anterior : {};
  const ajenas = Object.keys(r).filter((k) => !CLAVES_PLAN.includes(k) && !Object.prototype.hasOwnProperty.call(previo, k));
  if (ajenas.length && cambios) return mal('La respuesta del modelo trae claves que no son del plan: ' + ajenas.slice(0, 5).join(', '));
  for (const k of ajenas) delete r[k];
  let plan = r;
  if (cambios) {
    if (!Object.keys(r).length) return mal('La respuesta del modelo no trae ninguna parte del plan');
    // Lo que el modelo omita se conserva del plan guardado: nunca se manda a WordPress un plan a medias. Las fases se
    // unen por su clave y las fotos por su lámina: si el modelo devuelve solo la fase o la foto que cambió, las otras
    // quedan como estaban (con los días de Luis, y cada foto con su mismo texto, que el renderer reconoce por su hash).
    plan = Object.assign({}, previo, r);
    plan.fases = unirPor(previo.fases, r.fases, 'clave');
    plan.image_briefs = unirPor(previo.image_briefs, r.image_briefs, 'slide');
  }
  plan = JSON.parse(JSON.stringify(plan));
  if (!Array.isArray(plan.fases) || !plan.fases.length) return mal('El plan del modelo no trae fases');
  if (plan.fases.some((f) => !esObjetoPlano(f) || !Array.isArray(f.bloques))) {
    return mal('El plan del modelo trae una fase sin su lista de bloques');
  }
  // El «Arranque» del modelo se descarta: en borrador lo pone WordPress; en cambios vuelve el guardado.
  if (cambios) restituirArranque(plan, previo);
  else quitarArranque(plan);
  let n = 0;
  recorrerActividades(plan, () => { n++; });
  if (!n) return mal('El modelo no propuso ninguna actividad');
  let protegidas = 0;
  if (cambios) protegidas = protegerLuis(plan, previo);
  else recorrerActividades(plan, (a) => { a.origen = 'ia'; });
  plan.image_briefs = fotosDelPlan(plan.image_briefs, plan.fases.length, op.hayPropuesta === true);
  return { ok: true, reason: '', plan, protegidas };
}
"""


def code_motivo(modo):
    """Código del nodo «Motivo del error» de «1 Borrador» (modo 'borrador') y «2 Cambios» (modo 'cambios').

    Arma un motivo legible venga de donde venga el fallo (contexto, modelo o guardado en WordPress). Nunca lanza.
    """
    if modo not in ('borrador', 'cambios'):
        raise ValueError(modo)
    return r"""const MODO = '__MODO__';
const hook = $('Webhook').first().json.body || {};
const id = parseInt(hook.id, 10) || 0;
const lc = $('Leer contexto').first().json || {};
const ctx = lc.statusCode === 200 && esObjetoPlano(lc.body) ? lc.body : {};
const cuerpo = (x) => (esObjetoPlano(x.body) ? x.body : {});
const estadoHttp = (x) => (x.statusCode ? 'HTTP ' + x.statusCode
  : 'sin respuesta' + (x.error && x.error.message ? ': ' + x.error.message : ''));
let reason = '';
if ($('Guardar borrador').isExecuted) {
  const g = $('Guardar borrador').first().json || {};
  const b = cuerpo(g);
  const errores = Array.isArray(b.errores) ? b.errores.map(String).filter(Boolean) : [];
  if (g.statusCode === 422 && errores.length) reason = 'WordPress rechazó el plan: ' + errores.join(' · ');
  else if (g.statusCode === 200) reason = 'WordPress no confirmó el guardado del plan' + (errores.length ? ': ' + errores.join(' · ') : '');
  else reason = 'WordPress no guardó el plan (' + estadoHttp(g) + ')'
    + (errores.length ? ': ' + errores.join(' · ') : (b.message ? ' — ' + b.message : ''));
} else if ($('Leer plan').isExecuted) {
  reason = String($('Leer plan').first().json.reason || '');
} else if (MODO === 'cambios' && lc.statusCode === 200 && ctx.ok === true && !esObjetoPlano(ctx.plan_actual)) {
  reason = 'El plan no tiene un borrador guardado al que aplicarle cambios';
} else {
  const b = cuerpo(lc);
  reason = 'No se pudo leer el contexto del plan en WordPress (' + estadoHttp(lc) + ')' + (b.message ? ' — ' + b.message : '');
}
if (!reason) reason = MODO === 'cambios' ? 'Error desconocido al aplicar los cambios' : 'Error desconocido al generar el borrador';
// WordPress guarda como mucho 1000 caracteres de nota (/error) y el flujo le suma « (ejecución N)».
if (reason.length > 900) reason = reason.slice(0, 900) + '…';
return [{ json: { id, crm: parseInt(ctx.crm_cliente_id, 10) || 0, proyecto: String(ctx.proyecto || ctx.empresa || ('plan ' + id)),
  reason, exec: String($execution.id) } }];""".replace('__MODO__', modo)
```

- [ ] **Step 4: Correr la suite completa y verla pasar**

```bash
SAL=$(python N8N/plan-trabajo/probar_plan.py 2>&1); echo "exit=$?"
echo "$SAL" | grep -c '^ok '; echo "$SAL" | grep -c '^FALLA'; echo "$SAL" | tail -1
```
Expected: `exit=0`, `51`, `0`, `TODO OK` (51 líneas `ok`, entre ellas «protegerLuis: los días que Luis puso a mano (7) no se pisan
aunque el modelo los cambie a 2», «protegerLuis: si el modelo mueve de bloque una actividad de Luis, sus días (7) se
conservan», «leerPlanModelo (cambios): si el modelo devuelve solo la fase que cambió, las otras fases se conservan»,
«restituirArranque: el «Arranque» que manda el modelo (…) se descarta» y «fotosDelPlan: filtrar dos veces no cambia nada»).

- [ ] **Step 5: Commit**

```bash
git add N8N/plan-trabajo/.gitignore N8N/plan-trabajo/probar_plan.py N8N/plan-trabajo/plan_js.py
git commit -m "feat(plan-n8n): lectura de la respuesta del modelo, fotos del plan y días de Luis protegidos" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 6: Insertar la sección `correos` en la prueba (todavía falla)**

En `N8N/plan-trabajo/probar_plan.py`, con Edit, reemplazar la línea marcador por este bloque, que termina con la MISMA
línea marcador:
```python
@seccion('correos')
def prueba_correos():
    from correos_plan import JS_PANEL, correo_error_plan, correo_sin_vista
    r, err = node_js(JS_PANEL + "console.log(JSON.stringify([urlPanel(5, 9), urlPanel(0, 9), urlPanel('abc', 9), urlPanel(5, 0), urlPanel(null, null)]));")
    ok(r == ['https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-ficha&id=5&pt=9#tab-plan',
             'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-clientes',
             'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-clientes',
             'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-ficha&id=5#tab-plan',
             'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-clientes'],
       'urlPanel: ficha del cliente en la pestaña del plan; sin ficha conocida, la lista de clientes', r or err)
    try:
        correo_error_plan('otro')
        ok(False, 'correo_error_plan: un flujo desconocido se rechaza')
    except ValueError:
        ok(True, 'correo_error_plan: un flujo desconocido se rechaza')

    def correr(codigo, datos):
        js = ("const $ = (n) => ({ isExecuted: n in DATOS, first: () => ({ json: DATOS[n] }) });\n"
              "const $execution = { id: '777' };\n"
              "const salida = (function () {\n" + codigo + "\n})();\nconsole.log(JSON.stringify(salida[0].json));")
        return node_js(js, datos)

    motivo = {'id': 9, 'crm': 5, 'proyecto': '[PRUEBA] Sitio <b>de</b> la panadería', 'reason': 'La respuesta no es JSON\nsegunda línea', 'exec': '777'}
    for flujo, frase, siguiente in (('borrador', 'no se pudo generar el borrador del plan', 'Reintentar borrador'),
                                    ('cambios', 'no se pudieron aplicar los cambios al plan', 'Volver al borrador')):
        r, err = correr(correo_error_plan(flujo), {'Motivo del error': motivo, 'Marcar error': {'statusCode': 200}})
        ok(r is not None, f'correo {flujo}: el código corre', err)
        if r is None:
            continue
        h = r['html']
        ok(r['asunto'] == '⚠️ [PRUEBA] Sitio <b>de</b> la panadería · ' + frase, f'correo {flujo}: asunto', r['asunto'])
        ok('Plan de trabajo · aviso interno' in h and 'Propuestas · aviso interno' not in h, f'correo {flujo}: rótulo del plan')
        ok('easypanel' not in h, f'correo {flujo}: no enlaza a *.easypanel.host')
        ok('automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in h, f'correo {flujo}: botón a la pestaña del plan en la ficha')
        ok('&lt;b&gt;de&lt;/b&gt;' in h and '<b>de</b>' not in h, f'correo {flujo}: escapa el nombre del proyecto')
        ok(siguiente in h and 'segunda línea' in h and 'La respuesta no es JSON<br>' in h, f'correo {flujo}: motivo y siguiente paso')
        ok('777' in h and 'Nada de esto le llegó al cliente' in h, f'correo {flujo}: ejecución y aviso de que el cliente no recibió nada')
        ok('el envío lo haces tú' not in h and 'Aviso automático del plan de trabajo de AutomatizaTech. Nada de esto le llega al cliente.' in h,
           f'correo {flujo}: pie del plan (en la Etapa 1 nada se le envía al cliente)')
        r2, _ = correr(correo_error_plan(flujo), {'Motivo del error': dict(motivo, crm=0), 'Marcar error': {'error': {'message': 'ECONNRESET'}}})
        h2 = (r2 or {}).get('html', '')
        trabado = 'generando' if flujo == 'borrador' else 'cambios'
        ok('Tampoco se pudo marcar el plan como error' in h2 and 'sin respuesta' in h2 and f'«{trabado}»' in h2 and 'Destrabar' in h2,
           f'correo {flujo}: si tampoco se pudo marcar el error, lo dice y sugiere «Destrabar»')
        ok('automatiza-crm-clientes' in h2, f'correo {flujo}: sin ficha conocida, enlaza a la lista de clientes')

    try:
        correo_sin_vista('otro')
        ok(False, 'correo_sin_vista: un flujo desconocido se rechaza')
    except ValueError:
        ok(True, 'correo_sin_vista: un flujo desconocido se rechaza')
    guardado = {'Webhook': {'body': {'id': 9}},
                'Leer contexto': {'statusCode': 200, 'body': {'proyecto': '[PRUEBA] Sitio <b>de</b> la panadería', 'crm_cliente_id': 5}},
                'Guardar borrador': {'statusCode': 200, 'body': {'ok': True, 'errores': [], 'avisos': [
                    'Se descartó la foto de la lámina «gantt»: no trae descripción.', 'No se pudo pedir la vista previa: HTTP 404']}}}
    for flujo, frase in (('borrador', 'borrador del plan guardado sin vista previa'), ('cambios', 'cambios del plan guardados sin vista previa')):
        r, err = correr(correo_sin_vista(flujo), guardado)
        ok(r is not None, f'correo sin vista {flujo}: el código corre', err)
        if r is None:
            continue
        h = r['html']
        ok(r['asunto'] == '⚠️ [PRUEBA] Sitio <b>de</b> la panadería · ' + frase, f'correo sin vista {flujo}: asunto', r['asunto'])
        ok('No se pudo pedir la vista previa: HTTP 404' in h and 'gantt' not in h and 'Guardar y recalcular' in h and 'borrador listo' in h,
           f'correo sin vista {flujo}: dice por qué no llega el aviso «borrador listo» y cómo pedir la vista previa')
        ok('automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in h and 'easypanel' not in h and '&lt;b&gt;de&lt;/b&gt;' in h and '777' in h,
           f'correo sin vista {flujo}: botón a la pestaña del plan, proyecto escapado y ejecución')


# ==== Las tareas siguientes agregan sus secciones justo antes de esta línea ====
```

- [ ] **Step 7: Correr `correos` y verla fallar**

```bash
python N8N/plan-trabajo/probar_plan.py correos; echo "exit=$?"
```
Expected:
```
== correos
FALLA correos: la sección lanzó ModuleNotFoundError: No module named 'correos_plan'
1 FALLA(S)
exit=1
```

- [ ] **Step 8: Escribir `N8N/plan-trabajo/correos_plan.py`**
```python
"""Correos de marca a Luis de los flujos n8n del plan de trabajo (1 Borrador, 2 Cambios y 3 Render).

Reutiliza la plantilla de las propuestas (N8N/propuestas-v3/email_tpl.py) y cambia solo el rótulo del encabezado
y el pie. Cada función devuelve el código de un nodo Code que arma {asunto, html} (el del render, además,
{enviar}); el nodo de correo usa ={{ $json.asunto }} y ={{ $json.html }}.
Solo enlaza al panel en automatizatech.cl: nunca a *.easypanel.host, que el SMTP de Hostinger rechaza como spam
(554 5.7.1, 2026-09-24). La vista previa del plan se abre desde el panel.
Nada de esto le llega al cliente: en la Etapa 1 los correos van solo a Luis.
"""
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'propuestas-v3'))
from email_tpl import JS_ESC, boton, caja, etiqueta, marco, nota, parrafo  # noqa: E402

ROTULOS = [
    ('Propuestas · aviso interno', 'Plan de trabajo · aviso interno'),
    # En la Etapa 1 no hay envío al cliente: el pie de las propuestas («el envío lo haces tú desde el panel») no aplica.
    ('Aviso automático del flujo de propuestas de AutomatizaTech. Nada de esto se envía al cliente: el envío lo haces tú '
     'desde el panel.', 'Aviso automático del plan de trabajo de AutomatizaTech. Nada de esto le llega al cliente.'),
]

# Comienzo del aviso que agrega POST /plan/{id}/borrador (Task 7) cuando guardó el plan pero no pudo pedirle la vista
# previa a «3 Render»: 'No se pudo pedir la vista previa: <motivo>'.
AVISO_SIN_VISTA = 'No se pudo pedir la vista previa'

# Enlace a la pestaña del plan en la ficha del cliente (at_pt_url_ficha en WordPress). Si el flujo no conoce el
# id de la ficha del CRM, abre la lista de clientes: nunca un enlace roto.
JS_PANEL = r"""const PANEL_BASE = 'https://automatizatech.cl/wp-admin/admin.php?page=';
function urlPanel(crm, plan) {
  const c = parseInt(crm, 10);
  const p = parseInt(plan, 10);
  if (!(c > 0)) return PANEL_BASE + 'automatiza-crm-clientes';
  return PANEL_BASE + 'automatiza-crm-ficha&id=' + c + (p > 0 ? '&pt=' + p : '') + '#tab-plan';
}
"""


def marco_plan(titulo, etiqueta_html, cuerpo):
    html = marco(titulo, etiqueta_html, cuerpo)
    for viejo, nuevo in ROTULOS:
        if html.count(viejo) != 1:
            raise SystemExit(f'email_tpl.marco cambió: no encuentro «{viejo}» (revisar correos_plan.ROTULOS)')
        html = html.replace(viejo, nuevo)
    return html


def js_correo_plan(preparacion, asunto, titulo, etiqueta_html, cuerpo, extra=''):
    """Como email_tpl.js_correo, con el rótulo del plan, urlPanel() y campos extra en la salida."""
    html = marco_plan(titulo, etiqueta_html, cuerpo)
    return (JS_ESC + JS_PANEL + preparacion + '\nconst html = `' + html + '`;\n'
            + 'return [{ json: { asunto: ' + asunto + ', html' + (', ' + extra if extra else '') + ' } }];')


def correo_error_plan(flujo):
    """Correo de «1 Borrador» o «2 Cambios» cuando algo falló: el plan quedó (o debió quedar) en «error»."""
    if flujo == 'borrador':
        que, trabado = 'no se pudo generar el borrador del plan', 'generando'
        titulo = 'No se pudo generar el borrador del plan: ${esc(m.proyecto)}'
        siguiente = ('El plan quedó en <strong>error</strong>. En la pestaña «Plan de trabajo» de la ficha del cliente, '
                     '«Reintentar borrador» lo vuelve a generar.')
    elif flujo == 'cambios':
        que, trabado = 'no se pudieron aplicar los cambios al plan', 'cambios'
        titulo = 'No se pudieron aplicar los cambios al plan: ${esc(m.proyecto)}'
        siguiente = ('El plan quedó en <strong>error</strong>. «Volver al borrador» recupera el último borrador guardado; '
                     'también puedes volver a pedir cambios.')
    else:
        raise ValueError(flujo)
    prep = """const m = $('Motivo del error').first().json;
const me = $('Marcar error').first().json;
const marcado = me.statusCode === 200;
const panel = urlPanel(m.crm, m.id);"""
    no_marcado = caja('<strong>Tampoco se pudo marcar el plan como error</strong> '
                      '(${esc(me.statusCode ? "HTTP " + me.statusCode : "sin respuesta")}): puede haber quedado en «'
                      + trabado + '». Revísalo en el panel y usa «Destrabar».', 'error')
    cuerpo = (caja('${br(m.reason)}', 'error')
              + "${marcado ? `" + parrafo(siguiente) + "` : `" + no_marcado + "`}"
              + '<div style="margin:6px 0 4px;">' + boton('${esc(panel)}', '✏️ Abrir el plan en el panel') + '</div>'
              + nota('Ejecución de n8n: ${esc(m.exec)}. Nada de esto le llegó al cliente.'))
    return js_correo_plan(prep, "'⚠️ ' + m.proyecto + ' · " + que + "'", titulo, etiqueta('Error', 'error'), cuerpo)


def correo_sin_vista(flujo):
    """Correo de «1 Borrador» o «2 Cambios» cuando WordPress guardó el plan pero no pudo pedir la vista previa.

    Sin este aviso Luis esperaría el correo «borrador listo» de «3 Render», que no va a llegar. El plan está bien
    (en «borrador»): no se marca error.
    """
    if flujo == 'borrador':
        que, lo_guardado = 'borrador del plan guardado sin vista previa', 'El borrador del plan quedó guardado'
    elif flujo == 'cambios':
        que, lo_guardado = 'cambios del plan guardados sin vista previa', 'El plan con tus cambios quedó guardado'
    else:
        raise ValueError(flujo)
    prep = """const ctx = $('Leer contexto').first().json.body || {};
const hook = $('Webhook').first().json.body || {};
const id = parseInt(hook.id, 10) || 0;
const g = $('Guardar borrador').first().json.body || {};
const avisos = (Array.isArray(g.avisos) ? g.avisos : []).map(String).filter((a) => a.startsWith(""" + repr(AVISO_SIN_VISTA) + """));
const proyecto = String(ctx.proyecto || ctx.empresa || ('plan ' + id));
const panel = urlPanel(ctx.crm_cliente_id, id);
const exec = String($execution.id);"""
    cuerpo = (caja('${avisos.map(esc).join("<br>")}', 'aviso')
              + parrafo(lo_guardado + ' en WordPress, pero la vista previa no se pidió, así que no te va a llegar el correo '
                        '«borrador listo». En la pestaña «Plan de trabajo» de la ficha del cliente, «Guardar y recalcular» '
                        'la vuelve a pedir.')
              + '<div style="margin:6px 0 4px;">' + boton('${esc(panel)}', '✏️ Abrir el plan en el panel') + '</div>'
              + nota('Ejecución de n8n: ${esc(exec)}. Nada de esto le llegó al cliente.'))
    return js_correo_plan(prep, "'⚠️ ' + proyecto + ' · " + que + "'", 'Vista previa sin pedir: ${esc(proyecto)}',
                          etiqueta('Borrador', 'borrador'), cuerpo)
```

- [ ] **Step 9: Correr la suite completa y verla pasar**

```bash
SAL=$(python N8N/plan-trabajo/probar_plan.py 2>&1); echo "exit=$?"
echo "$SAL" | grep -c '^ok '; echo "$SAL" | grep -c '^FALLA'; echo "$SAL" | tail -1
```
Expected: `exit=0`, `84`, `0`, `TODO OK` (33 de la sección `correos`: ningún correo enlaza a `*.easypanel.host`, el pie ya no
dice «el envío lo haces tú desde el panel» y el aviso de vista previa sin pedir explica por qué no llega el «borrador
listo»).

- [ ] **Step 10: Commit**

```bash
git add N8N/plan-trabajo/correos_plan.py N8N/plan-trabajo/probar_plan.py
git commit -m "feat(plan-n8n): correos de marca a Luis cuando falla el borrador o los cambios del plan" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 11: Insertar la sección `borrador` en la prueba (todavía falla)**

En `N8N/plan-trabajo/probar_plan.py`, con Edit, reemplazar la línea marcador por:
```python
@seccion('borrador')
def prueba_borrador():
    from build_plan_1_borrador import PROMPT_PLAN  # noqa: F401  (vuelve a escribir plan-1-borrador.json)
    revisar_workflow('plan-1-borrador.json', 'Plan de trabajo · 1 Borrador', 'plan-v1-borrador')
    ok('easypanel' not in json.dumps(cargar('plan-1-borrador.json')), '1 Borrador: no toca el renderer ni enlaza a *.easypanel.host')
    faltan = [x for x in TOPES_PROMPT if x not in PROMPT_PLAN]
    ok(not faltan, 'PROMPT_PLAN: dice los topes que valida WordPress (13 bloques, 10 por bloque, 58 actividades, 1 a 60 días, '
       '130 días hábiles, 10 hitos, claves) y pide no mandar el «Arranque»', faltan)
    GUARDADO_OK = {'statusCode': 200, 'body': {'ok': True, 'errores': [], 'avisos': []}}
    MARCADO = {'statusCode': 200, 'body': {'ok': True}}
    hook = {'id': 9, 'codigo': 'PRUEBAplan01'}

    # 1. Contrato con propuesta: el plan se guarda en WordPress sin Arranque, con origen «ia» y solo fotos nuevas.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [GUARDADO_OK]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA, ensure_ascii=False)}]})
    sin_error(t, 'B1')
    g = llamadas(t, 'Guardar borrador')
    ok(len(g) == 1 and g[0]['url'] == WP + '/plan/9/borrador' and g[0]['body']['origen'] == 'borrador',
       'B1: un solo POST /plan/9/borrador con origen «borrador»', g)
    plan = g[0]['body']['plan'] if g else {}
    ok([b['nombre'] for b in plan.get('fases', [{}])[0].get('bloques', [])] == ['Diseño', 'Desarrollo'], 'B1: sin el Arranque del modelo')
    ok([b['slide'] for b in plan.get('image_briefs', [])] == NUEVAS, 'B1: con propuesta, solo las 8 fotos nuevas', plan.get('image_briefs'))
    ok(llamadas(t, 'Leer contexto')[0]['url'] == WP + '/plan/9/contexto', 'B1: lee el contexto del plan 9')
    ok(not llamadas(t, 'Marcar error') and not t['correos'], 'B1: sin error ni correo (el aviso lo manda «3 Render»)')
    o = t['openai'].get('Redactar plan', [{}])[0]
    ok(o.get('model') == 'gpt-4o' and o.get('temperature') == 0.3 and o.get('system') == PROMPT_PLAN,
       'B1: gpt-4o, temperatura 0,3 y PROMPT_PLAN', {k: o.get(k) for k in ('model', 'temperature')})
    pedido = json.loads(o.get('user') or '{}')
    ok(set(pedido) == {'proyecto', 'empresa', 'rubro', 'contrato', 'propuesta', 'tabla', 'slides_foto'} and pedido.get('rubro') == '[PRUEBA] Panadería',
       'B1: el modelo recibe proyecto, empresa, rubro (de la ficha del CRM), contrato, propuesta, tabla y láminas (ni el nombre de la persona ni fechas)', sorted(pedido))
    ok(pedido.get('slides_foto') == NUEVAS and pedido.get('contrato') == CONTRATO and pedido.get('tabla') == TABLA,
       'B1: lo contratado sale del contrato y las láminas pedidas son las nuevas')
    ok((pedido.get('contrato') or {}).get('garantia_meses') == 6,
       'B1: el modelo recibe los meses de garantía del contrato (contrato.garantia_meses, D7)', pedido.get('contrato'))

    # 2. Contrato sin propuesta: portada y cierre piden foto nueva.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto(propuesta=None, rubro='')}],
                'Guardar borrador': [GUARDADO_OK]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA, ensure_ascii=False)}]})
    sin_error(t, 'B2')
    g = llamadas(t, 'Guardar borrador')
    slides = [b['slide'] for b in (g[0]['body']['plan'].get('image_briefs', []) if g else [])]
    ok(slides == TODAS, 'B2: sin propuesta, el plan pide también portada y cierre', slides)
    pedido = json.loads(t['openai'].get('Redactar plan', [{}])[0].get('user') or '{}')
    ok(pedido.get('propuesta') is None and pedido.get('rubro') == '' and pedido.get('slides_foto') == TODAS, 'B2: el modelo sabe que no hay propuesta y qué láminas describir')

    # 3. Respuesta que no es JSON → «error» con motivo legible y correo; nunca se guarda un plan roto.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Marcar error': [MARCADO]}, openai={'Redactar plan': [{'content': 'Aquí va el plan:\n' + json.dumps(PLAN_IA)}]})
    sin_error(t, 'B3')
    me = llamadas(t, 'Marcar error')
    ok(not llamadas(t, 'Guardar borrador'), 'B3: no guarda nada en WordPress')
    ok(len(me) == 1 and me[0]['url'] == WP + '/plan/9/error' and me[0]['body']['nota'].startswith('La respuesta del modelo no es un JSON válido')
       and me[0]['body']['nota'].endswith('(ejecución SIM-1)'), 'B3: POST /plan/9/error con el motivo y la ejecución', me)
    ok(len(t['correos']) == 1 and t['correos'][0]['asunto'] == '⚠️ [PRUEBA] Sitio de la panadería · no se pudo generar el borrador del plan'
       and 'automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in t['correos'][0]['html'] and 'easypanel' not in t['correos'][0]['html'],
       'B3: correo a Luis con el enlace a la pestaña del plan', [c['asunto'] for c in t['correos']])

    # 4. Ninguna actividad (solo el Arranque del modelo) → error.
    vacio = {'fases': [{'clave': 'diseno_desarrollo', 'bloques': [PLAN_IA['fases'][0]['bloques'][0]]}, {'clave': 'implementacion', 'bloques': []}]}
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}], 'Marcar error': [MARCADO]},
                openai={'Redactar plan': [{'content': json.dumps(vacio)}]})
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('El modelo no propuso ninguna actividad') and not llamadas(t, 'Guardar borrador'),
       'B4: sin actividades → «error», sin guardar', me)

    # 5. Fases desconocidas, días 0 y 200 y un texto enorme: n8n no los corrige (lo decide WordPress, que responde 422).
    raro = json.loads(json.dumps(PLAN_IA))
    raro['fases'].append({'clave': 'marketing', 'descripcion': '', 'bloques': [{'nombre': 'Campaña', 'actividades': [act('Anuncios', 'at', 200)]}]})
    raro['fases'][0]['bloques'][1]['actividades'][0]['dias_habiles'] = 0
    raro['fases'][0]['bloques'][1]['actividades'][0]['detalle'] = 'x' * 5000
    rechazo = {'statusCode': 422, 'body': {'ok': False, 'errores': ['Fase desconocida: marketing', 'Días hábiles fuera de rango (1 a 60) en «Anuncios»: 200'], 'avisos': []}}
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [rechazo], 'Marcar error': [MARCADO]}, openai={'Redactar plan': [{'content': json.dumps(raro)}]})
    sin_error(t, 'B5')
    g = llamadas(t, 'Guardar borrador')
    enviado = g[0]['body']['plan'] if g else {}
    ok(enviado and [f['clave'] for f in enviado['fases']][-1] == 'marketing'
       and actividad(enviado, 'Diseño de la portada')['dias_habiles'] == 0 and len(actividad(enviado, 'Diseño de la portada')['detalle']) == 5000,
       'B5: n8n manda el plan tal cual y WordPress (at_pt_validar_plan) decide')
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('WordPress rechazó el plan: Fase desconocida: marketing · Días hábiles fuera de rango'),
       'B5: el 422 de WordPress queda como motivo legible en la nota', me)
    ok(len(t['correos']) == 1 and 'Fase desconocida: marketing' in t['correos'][0]['html'], 'B5: el correo trae los errores de WordPress')

    # 6. WordPress no encuentra el plan: ni se llama a OpenAI.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 404, 'body': {'code': 'at_pt_no_existe', 'message': 'Plan no encontrado'}}],
                'Marcar error': [{'statusCode': 404, 'body': {}}]}, openai={})
    sin_error(t, 'B6')
    me = llamadas(t, 'Marcar error')
    ok(not t['openai'] and me and me[0]['body']['nota'].startswith('No se pudo leer el contexto del plan en WordPress (HTTP 404) — Plan no encontrado'),
       'B6: contexto ilegible → motivo con el HTTP y sin gastar en OpenAI', me)
    ok(t['correos'] and 'Tampoco se pudo marcar el plan como error' in t['correos'][0]['html'] and 'plan 9' in t['correos'][0]['asunto'],
       'B6: el correo avisa que tampoco se pudo marcar el error', [c['asunto'] for c in t['correos']])

    # 7. OpenAI caído; 8. WordPress caído al marcar el error.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}], 'Marcar error': [{'error': {'message': 'ECONNRESET'}}]},
                openai={'Redactar plan': [{'error': {'message': 'Rate limit reached'}}]})
    sin_error(t, 'B7')
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('El modelo no respondió: Rate limit reached'), 'B7: OpenAI caído → motivo legible', me)
    ok(t['correos'] and 'sin respuesta' in t['correos'][0]['html'] and '«generando»' in t['correos'][0]['html'],
       'B8: si WordPress tampoco responde, el correo dice que el plan puede seguir en «generando»')
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}], 'Marcar error': [MARCADO]},
                openai={'Redactar plan': [{}]})
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('El modelo no respondió (ejecución SIM-1)') and len(t['correos']) == 1,
       'B7b: OpenAI falla sin detalle ({}, el error queda fuera de json) → «error» legible y correo', me)

    # 9. Llave de más al final y 10. respuesta envuelta: se aceptan.
    for caso, contenido in (('B9 llave de más', json.dumps(PLAN_IA) + '}'), ('B10 envuelta', json.dumps({'plan': PLAN_IA}))):
        t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}], 'Guardar borrador': [GUARDADO_OK]},
                    openai={'Redactar plan': [{'content': contenido}]})
        ok(len(llamadas(t, 'Guardar borrador')) == 1 and not t['correos'], f'{caso}: se guarda el plan')

    # 11. WordPress guarda pero no confirma (5xx): error.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [{'statusCode': 500, 'body': {'message': 'Error crítico'}}], 'Marcar error': [MARCADO]},
                openai={'Redactar plan': [{'content': json.dumps(PLAN_IA)}]})
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('WordPress no guardó el plan (HTTP 500) — Error crítico'), 'B11: un 500 al guardar → error legible', me)
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [{'statusCode': 500, 'body': {'ok': False, 'errores': ['No se pudo guardar el plan.'], 'avisos': []}}],
                'Marcar error': [MARCADO]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA)}]})
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('WordPress no guardó el plan (HTTP 500): No se pudo guardar el plan.'),
       'B11b: el 500 propio de /borrador trae su motivo en «errores» y llega a la nota', me)

    # 12. Llegó tarde (el plan ya siguió: otra ejecución lo guardó, Luis lo destrabó y volvió al borrador, etc.):
    #     no se gasta en OpenAI, no se toca el plan y no se escribe. Con el plan en «error» sí se trabaja (WordPress lo acepta).
    for estado in ('borrador', 'aprobando', 'listo'):
        t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto(estado=estado)}]}, openai={})
        sin_error(t, f'B12 {estado}')
        ok(not t['openai'] and not llamadas(t, 'Guardar borrador') and not llamadas(t, 'Marcar error') and not t['correos'],
           f'B12: plan en «{estado}» → el borrador no se pide, no se guarda, no se marca error ni se escribe')
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto(estado='error')}],
                'Guardar borrador': [GUARDADO_OK]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA)}]})
    ok(len(llamadas(t, 'Guardar borrador')) == 1 and not t['correos'], 'B12: plan en «error» (Destrabar antes de que llegue n8n) → se genera y se guarda')
    # D5: en «error» CON contenido, WordPress responde 409 a un borrador (nunca pisa lo que Luis editó): ni se pide.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto(estado='error', plan_actual=PLAN_GUARDADO)}]},
                openai={})
    sin_error(t, 'B12 error con contenido')
    ok(not t['openai'] and not llamadas(t, 'Guardar borrador') and not llamadas(t, 'Marcar error') and not t['correos'],
       'B12: plan en «error» que ya tiene contenido → el borrador no se pide (WordPress respondería 409)')

    # 13. WordPress responde 409 al guardar («llegó tarde y no se aplica»): no se pasa a «error» un plan que ya siguió.
    tarde = {'statusCode': 409, 'body': {'ok': False, 'errores': ['El plan está en «aprobando»: este borrador llegó tarde y no se aplica.'], 'avisos': []}}
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [tarde]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA)}]})
    sin_error(t, 'B13')
    ok(len(llamadas(t, 'Guardar borrador')) == 1 and not llamadas(t, 'Marcar error') and not t['correos'],
       'B13: un borrador tardío (409) no pasa a «error» un plan que Luis ya está usando ni le escribe')

    # 14. WordPress guardó pero no pudo pedir la vista previa: correo a Luis (si no, esperaría el «borrador listo»).
    sin_vista = {'statusCode': 200, 'body': {'ok': True, 'errores': [], 'avisos': ['No se pudo pedir la vista previa: HTTP 404']}}
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [sin_vista]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA)}]})
    sin_error(t, 'B14')
    ok(not llamadas(t, 'Marcar error') and len(t['correos']) == 1
       and t['correos'][0]['asunto'] == '⚠️ [PRUEBA] Sitio de la panadería · borrador del plan guardado sin vista previa'
       and 'Guardar y recalcular' in t['correos'][0]['html'] and 'automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in t['correos'][0]['html'],
       'B14: guardado sin vista previa → correo a Luis con «Guardar y recalcular», sin marcar error', [c['asunto'] for c in t['correos']])
    otro_aviso = {'statusCode': 200, 'body': {'ok': True, 'errores': [], 'avisos': ['La IA mandó su propio bloque «Arranque»: se usó el fijo.']}}
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [otro_aviso]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA)}]})
    ok(not t['correos'], 'B14: otros avisos de WordPress no mandan correo (los muestra el panel)')


# ==== Las tareas siguientes agregan sus secciones justo antes de esta línea ====
```

- [ ] **Step 12: Correr `borrador` y verla fallar**

```bash
python N8N/plan-trabajo/probar_plan.py borrador; echo "exit=$?"
```
Expected:
```
== borrador
FALLA borrador: la sección lanzó ModuleNotFoundError: No module named 'build_plan_1_borrador'
1 FALLA(S)
exit=1
```

- [ ] **Step 13: Escribir `N8N/plan-trabajo/build_plan_1_borrador.py` (con `PROMPT_PLAN`)**
```python
"""Construye el workflow n8n «Plan de trabajo · 1 Borrador» y lo guarda en plan-1-borrador.json.

Diseño: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md; Task 13 del plan de implementación (Etapa 1).
Lo llama WordPress (at_pt_iniciar_borrador, al firmar el contrato o con «Crear plan» / «Reintentar borrador») con
{id, codigo} y la cabecera X-AT-Secret. Lee el contexto del plan (lo contratado sale del CONTRATO; la propuesta, si existe,
solo aporta rubro y extracto), pide el plan a GPT-4o y lo guarda en WordPress, que lo valida, aplica la tabla de
tiempos, calcula las fechas y pide la vista previa (flujo «3 Render», que es el que le escribe a Luis).
Nunca deja el plan trabado en «generando»: si algo falla, lo pasa a «error» con el motivo y le escribe a Luis.
Si llegó tarde (el plan ya no está en «generando» ni en «error» sin contenido, o WordPress responde 409), no hace nada: no gasta en
GPT-4o ni pasa a «error» un plan que ya siguió. Si WordPress guardó pero no pudo pedir la vista previa, le escribe a
Luis (si no, esperaría el correo «borrador listo», que no llega).
Solo referencia credenciales por id; no contiene secretos.
"""
import json, os, sys

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'propuestas-v3'))
from fotos_guard import JS_LIMPIAR_FOTOS  # noqa: E402
from json_guard import JS_LEER_JSON  # noqa: E402
from correos_plan import AVISO_SIN_VISTA, correo_error_plan, correo_sin_vista  # noqa: E402
from plan_js import JS_PLAN, code_motivo  # noqa: E402

CRED_OPENAI = {'openAiApi': {'id': 'g52IEXpRfN5r7jKw', 'name': 'OpenAi account'}}
CRED_SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
LUIS = 'lmgm.0303@gmail.com'
FULL = {'response': {'response': {'fullResponse': True, 'neverError': True}}}
LIB = JS_LEER_JSON + '\n' + JS_LIMPIAR_FOTOS + '\n' + JS_PLAN + '\n'

PROMPT_PLAN = """Eres jefe de proyectos de AutomatizaTech (Chile). Recibes en JSON los datos de un contrato de servicios que el cliente ya firmó (contrato: servicios contratados, alcance, entregables, fases siguientes, plazo y meses de garantía), el rubro del cliente según su ficha (rubro; puede venir vacío), la tabla de tiempos de referencia de AutomatizaTech (tabla), las láminas que llevan foto nueva (slides_foto) y, si existe, un extracto de la propuesta y de la reunión con el cliente (propuesta; puede venir null). Arma el PLAN DE TRABAJO del proyecto: las actividades concretas para entregar lo contratado, en orden, con su responsable y sus días hábiles. Responde SOLO un objeto JSON (sin texto antes ni después, sin bloques de código) con esta forma exacta:
{
 "version": 1,
 "proyecto": "nombre corto del proyecto, máx. 8 palabras; usa el de los datos si viene",
 "fases": [
  {"clave": "diseno_desarrollo", "descripcion": "2 o 3 frases para el cliente: qué hacemos en esta fase",
   "bloques": [
    {"nombre": "máx. 4 palabras", "entregable": "una línea: qué recibe o revisa el cliente al cerrar el bloque; vacío si nada", "entrega": true,
     "actividades": [
      {"nombre": "máx. 8 palabras", "detalle": "una línea", "responsable": "at", "dias_habiles": 3, "servicio": "clave de la tabla o vacío", "etapa": "diseno", "en_paralelo": false}
     ]}
   ]},
  {"clave": "implementacion", "descripcion": "…", "bloques": ["…"]},
  {"clave": "soporte", "descripcion": "…", "bloques": ["…"]}
 ],
 "hitos": [{"nombre": "máx. 5 palabras", "despues_de": "nombre exacto de un bloque del plan"}],
 "necesitamos_de_ti": ["insumo concreto que el cliente debe entregar, una línea"],
 "reuniones": [{"nombre": "máx. 6 palabras", "detalle": "una línea: para qué es"}],
 "soporte": {"garantia_meses": 3, "mensuales": ["nombre de un servicio mensual contratado"]},
 "image_briefs": [{"slide": "una lámina de slides_foto", "prompt": "descripción en inglés, de 40 a 60 palabras"}]
}
Reglas:
- Español de Chile, trato de "tú", frases simples y concretas: el documento lo lee el cliente.
- LO CONTRATADO MANDA: arma actividades solo para lo que dicen contrato.servicios_contratados, contrato.alcance y contrato.entregables. contrato.fases_siguientes son etapas futuras que NO están contratadas: no las planifiques. La propuesta solo sirve para entender el rubro y el detalle de lo acordado; si contradice al contrato, manda el contrato.
- No inventes datos del cliente ni del proyecto: ni nombres de personas, teléfonos, correos, direcciones, montos, cifras, fechas ni herramientas que no estén en los datos. Nunca escribas precios ni montos (contrato.servicios_contratados trae precios: ignóralos). No escribas fechas: las calcula el sistema en días hábiles.
- fases: exactamente las tres, en este orden y con estas claves: "diseno_desarrollo", "implementacion" y "soporte". No escribas "titulo": el sistema pone los títulos.
- NO incluyas el bloque «Arranque» (reunión de inicio y entrega de logo, textos y accesos): lo agrega el sistema al comienzo del plan.
- TOPES que revisa el sistema (si pasas uno, el plan entero se rechaza): como máximo 13 bloques, 10 actividades por bloque y 58 actividades en total; de 1 a 60 días hábiles por actividad; el plan completo, en secuencia y contando 5 días hábiles de revisión por cada bloque con entrega, no pasa de 130 días hábiles (unas 26 semanas); como máximo 10 hitos; fases solo con las claves "diseno_desarrollo", "implementacion" y "soporte", y responsable solo "at", "cliente" o "ambos". Si el proyecto no cabe, junta bloques o actividades.
- bloques: en diseno_desarrollo de 2 a 4 (por ejemplo «Diseño», «Desarrollo», «Pruebas y revisión»); en implementacion 1 o 2; en soporte 1. En total, no más de 7: cada bloque es una barra de la carta Gantt. Cada bloque con 1 a 5 actividades.
- entrega: true solo en los bloques que terminan con algo que el cliente revisa y aprueba (el diseño, una versión para probar, la puesta en marcha). Después de cada entrega el sistema agrega 5 días hábiles de revisión del cliente (cláusula 6.1 del contrato): no los agregues tú.
- responsable: "at" (AutomatizaTech), "cliente" o "ambos".
- servicio y etapa: si la actividad es parte de un servicio de la tabla, escribe en "servicio" su clave (una clave de tabla) y en "etapa" la etapa: "diseno", "desarrollo", "pruebas" o "implementacion". El sistema reparte los días de la tabla entre las actividades de un mismo servicio y etapa según los días que propongas. En la fase soporte usa "etapa": "soporte". Si la actividad no calza con la tabla, "servicio": "" y "etapa": "".
- dias_habiles: entero, días hábiles de trabajo de esa actividad (ver TOPES). Si calza con la tabla, coherente con ella; si no, una estimación prudente.
- en_paralelo: true solo si la actividad se puede hacer al mismo tiempo que la anterior del mismo bloque; la primera actividad de cada bloque siempre false.
- hitos: 2 a 4; despues_de es el nombre exacto de un bloque que existe en el plan. El sistema agrega solo el hito «Entrega estimada».
- necesitamos_de_ti: 3 a 6 insumos concretos que este proyecto necesita del cliente (por ejemplo logo, textos, fotos de sus productos, accesos al dominio).
- reuniones: 3 o 4. Siempre «Reunión de inicio», «Llamada de seguimiento del plan» y «Entrega y capacitación»; una cuarta solo si el proyecto la necesita.
- soporte: garantia_meses = contrato.garantia_meses si viene; si no, 3. mensuales: los servicios mensuales que aparezcan en lo contratado (por ejemplo mantención o gestión de campañas); si no hay, [].
- image_briefs: exactamente una por cada lámina de slides_foto, y ninguna otra. Fotografía realista, cálida y respetuosa del RUBRO de este cliente (el de rubro; si viene vacío, dedúcelo de la propuesta o del alcance y del nombre del negocio): su gente trabajando o atendiendo, sus clientes, sus productos y sus lugares. Se permiten personas en acción.
  · Cada foto muestra el MUNDO DEL CLIENTE, nunca el trabajo de AutomatizaTech: nada de celulares, computadores, pantallas, oficinas, mesas de trabajo con papeles, calendarios, planos, documentos ni gráficos, aunque la lámina hable de plazos, reuniones o de un portal. No uses las palabras "meeting" ni "office".
  · Qué mostrar: cover = primer plano de las manos o de un detalle del oficio del rubro; metodo = la gente del negocio preparando su lugar antes de abrir; gantt = manos del rubro haciendo el trabajo paso a paso, en orden; fase_1 = los materiales, herramientas o productos del rubro dispuestos para empezar; fase_2 = el negocio atendiendo a su primer cliente del día; fase_3 = la gente del negocio atendiendo con calma, tiempo después; necesitamos = manos reuniendo los productos o herramientas propios del negocio sobre un mesón; reuniones = dos personas del rubro conversando cara a cara, de pie, en el lugar de trabajo; portal = el dueño o la dueña tranquilo, con un café, en su negocio al final del día; cierre = una escena esperanzadora del rubro (una entrega que llega, un equipo celebrando).
  · cover: SIEMPRE un primer plano con el fondo completamente desenfocado; nunca fachadas, calles, estadios, galerías ni muros de fondo (ahí el modelo de imagen inventa carteles).
  · Si un producto del rubro lleva etiqueta o pantalla (botellas, latas, cajas, celulares, libros), muéstralo sin etiqueta, de espaldas o apagado, y escríbelo así en el prompt (por ejemplo "unlabeled bottle", "phone screen facing away").
  · Nunca letreros, carteles, menús, pizarras, hojas, planos, dibujos, instrucciones ni texto de ningún tipo. En una tienda o sala de ventas, primer plano con el fondo desenfocado.
  · Cada prompt tiene de 40 a 60 palabras (máximo 400 caracteres) y termina con: "no signs, no labels, no text, no lettering, no logos, no watermarks"."""

CODE_PEDIDO = r"""// Lo que ve el modelo: lo contratado (del contrato), el rubro de la ficha del CRM (para las fotos), la propuesta si
// existe, la tabla y las láminas con foto nueva.
// No se le manda el nombre de la persona ni fechas: el plan no los necesita y las fechas las calcula WordPress.
const c = ($input.first().json || {}).body || {};
const hayPropuesta = esObjetoPlano(c.propuesta);
const permitidas = Array.isArray(c.slides_foto) && c.slides_foto.length ? c.slides_foto : null;
const datos = {
  proyecto: String(c.proyecto || ''),
  empresa: String(c.empresa || ''),
  rubro: String(c.rubro || '').slice(0, 100),
  contrato: esObjetoPlano(c.contrato) ? c.contrato : {},
  propuesta: hayPropuesta ? c.propuesta : null,
  tabla: esObjetoPlano(c.tabla) ? c.tabla : {},
  slides_foto: slidesConFoto(hayPropuesta, 3).filter((s) => !permitidas || permitidas.includes(s)),
};
return [{ json: { mensaje: JSON.stringify(datos) } }];"""

CODE_LEER = r"""// Nunca lanza: si la respuesta no sirve, ok=false con el motivo y el flujo pasa el plan a «error».
const ctx = $('Leer contexto').first().json.body || {};
const hook = $('Webhook').first().json.body || {};
const r = leerPlanModelo($('Redactar plan').first().json, { modo: 'borrador', hayPropuesta: esObjetoPlano(ctx.propuesta) });
return [{ json: Object.assign({ id: parseInt(hook.id, 10) || 0 }, r) }];"""


def node(id_, name, type_, version, pos, params, **extra):
    n = {'id': id_, 'name': name, 'type': type_, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


def http_wp(id_, name, pos, method, url, body_expr=None, timeout=30000):
    params = {'method': method, 'url': url, 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
              'options': dict(FULL, timeout=timeout)}
    if body_expr:
        params.update({'sendBody': True, 'specifyBody': 'json', 'jsonBody': body_expr})
    # neverError no cubre timeouts ni conexiones cortadas: con continueRegularOutput pasa {error} sin statusCode
    # y los «¿…?» lo mandan a la rama de error (el plan nunca queda trabado en «generando»).
    return node(id_, name, 'n8n-nodes-base.httpRequest', 4.2, pos, params, credentials=CRED_WP,
                onError='continueRegularOutput')


def iff(id_, name, pos, left):
    return node(id_, name, 'n8n-nodes-base.if', 2.2, pos, {
        'conditions': {'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'loose', 'version': 2},
                       'conditions': [{'id': id_ + '-c', 'leftValue': left, 'rightValue': True,
                                       'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
                       'combinator': 'and'},
        'options': {}})


nodes = [
    node('p1', 'Webhook', 'n8n-nodes-base.webhook', 2, [0, 0],
         {'httpMethod': 'POST', 'path': 'plan-v1-borrador', 'authentication': 'headerAuth',
          'responseMode': 'onReceived', 'options': {}},
         webhookId='plan-v1-borrador', credentials=CRED_WP),
    http_wp('p2', 'Leer contexto', [220, 0], 'GET', f"={WP}/plan/{{{{ $json.body.id }}}}/contexto"),
    iff('p3', '¿Contexto leído?', [440, 0], '={{ $json.statusCode === 200 && !!$json.body && $json.body.ok === true }}'),
    # Llegó tarde si el plan ya no espera un borrador (POST /borrador solo lo acepta en «generando», o en «error» todavía
    # sin contenido; Task 7 y D5): otra ejecución ya lo guardó, o Luis lo destrabó y siguió. Falso termina aquí: sin
    # gastar en GPT-4o ni tocar el plan.
    iff('p14', '¿Espera respuesta?', [660, -120],
        "={{ $json.body.estado === 'generando' || ($json.body.estado === 'error' && !($json.body.plan_actual"
        " && Array.isArray($json.body.plan_actual.fases) && $json.body.plan_actual.fases.length > 0)) }}"),
    node('p4', 'Preparar pedido', 'n8n-nodes-base.code', 2, [880, -120], {'jsCode': LIB + CODE_PEDIDO}),
    node('p5', 'Redactar plan', 'n8n-nodes-base.openAi', 1, [1100, -120],
         {'resource': 'chat', 'model': 'gpt-4o',
          'prompt': {'messages': [{'role': 'system', 'content': PROMPT_PLAN}, {'content': '={{ $json.mensaje }}'}]},
          'options': {'temperature': 0.3}, 'requestOptions': {}},
         credentials=CRED_OPENAI, onError='continueRegularOutput'),
    node('p6', 'Leer plan', 'n8n-nodes-base.code', 2, [1320, -120], {'jsCode': LIB + CODE_LEER}),
    iff('p7', '¿Plan legible?', [1540, -120], '={{ $json.ok === true }}'),
    http_wp('p8', 'Guardar borrador', [1760, -240], 'POST', f"={WP}/plan/{{{{ $json.id }}}}/borrador",
            "={{ JSON.stringify({ plan: $json.plan, origen: 'borrador' }) }}", timeout=60000),
    iff('p9', '¿Guardado?', [1980, -240], '={{ $json.statusCode === 200 && !!$json.body && $json.body.ok === true }}'),
    iff('p16', '¿Vista previa pedida?', [2200, -360],
        "={{ ![].concat(($json.body && $json.body.avisos) || []).some((a) => String(a).startsWith("
        + json.dumps(AVISO_SIN_VISTA) + ")) }}"),
    node('p17', 'Armar correo sin vista', 'n8n-nodes-base.code', 2, [2420, -360], {'jsCode': correo_sin_vista('borrador')}),
    # 409 = WordPress dice que este borrador llegó tarde (el plan ya siguió): no se marca error ni se escribe.
    iff('p15', '¿Llegó tarde?', [2200, -120], '={{ $json.statusCode === 409 }}'),
    node('p10', 'Motivo del error', 'n8n-nodes-base.code', 2, [2420, 120], {'jsCode': LIB + code_motivo('borrador')}),
    http_wp('p11', 'Marcar error', [2640, 120], 'POST', f"={WP}/plan/{{{{ $json.id }}}}/error",
            "={{ JSON.stringify({ nota: $json.reason + ' (ejecución ' + $json.exec + ')' }) }}"),
    node('p12', 'Armar correo error', 'n8n-nodes-base.code', 2, [2860, 120], {'jsCode': correo_error_plan('borrador')}),
    node('p13', 'Correo a Luis', 'n8n-nodes-base.emailSend', 1, [3080, -120],
         {'fromEmail': 'contacto@automatizatech.cl', 'toEmail': LUIS,
          'subject': '={{ $json.asunto }}', 'html': '={{ $json.html }}', 'options': {}},
         credentials=CRED_SMTP),
]

connections = {}


def link(a, b, output=0):
    connections.setdefault(a, {'main': []})
    while len(connections[a]['main']) <= output:
        connections[a]['main'].append([])
    connections[a]['main'][output].append({'node': b, 'type': 'main', 'index': 0})


link('Webhook', 'Leer contexto')
link('Leer contexto', '¿Contexto leído?')
link('¿Contexto leído?', '¿Espera respuesta?', 0)
link('¿Contexto leído?', 'Motivo del error', 1)
link('¿Espera respuesta?', 'Preparar pedido', 0)
link('Preparar pedido', 'Redactar plan')
link('Redactar plan', 'Leer plan')
link('Leer plan', '¿Plan legible?')
link('¿Plan legible?', 'Guardar borrador', 0)
link('¿Plan legible?', 'Motivo del error', 1)
link('Guardar borrador', '¿Guardado?')
link('¿Guardado?', '¿Vista previa pedida?', 0)
link('¿Guardado?', '¿Llegó tarde?', 1)
# Vista previa pedida: termina aquí; «3 Render» le escribe a Luis cuando la vista previa esté lista.
link('¿Vista previa pedida?', 'Armar correo sin vista', 1)
link('Armar correo sin vista', 'Correo a Luis')
link('¿Llegó tarde?', 'Motivo del error', 1)
link('Motivo del error', 'Marcar error')
link('Marcar error', 'Armar correo error')
link('Armar correo error', 'Correo a Luis')

wf = {'name': 'Plan de trabajo · 1 Borrador', 'nodes': nodes, 'connections': connections,
      # Si el flujo se cae sin llegar a su propio aviso, «Propuestas v3 · 0 Avisar error» le escribe a Luis.
      'settings': {'executionOrder': 'v1', 'errorWorkflow': 'm7TOfKznVSBGz4Nd'}}
out = os.path.join(AQUI, 'plan-1-borrador.json')
with open(out, 'w', encoding='utf-8') as fh:
    json.dump(wf, fh, ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
```

- [ ] **Step 14: Construir el flujo y correr la suite completa**

```bash
python N8N/plan-trabajo/build_plan_1_borrador.py
```
```bash
SAL=$(python N8N/plan-trabajo/probar_plan.py 2>&1); echo "exit=$?"
echo "$SAL" | grep -c '^ok '; echo "$SAL" | grep -c '^FALLA'; echo "$SAL" | tail -1
```
Expected: `escrito …\N8N\plan-trabajo\plan-1-borrador.json 17 nodos` y `exit=0`, `143`, `0`, `TODO OK` (59 de la sección `borrador`:
`PROMPT_PLAN` trae los topes de WordPress y pide no mandar el «Arranque»; B1 con propuesta guarda sin Arranque, origen
«ia» y 8 fotos nuevas, y el modelo recibe rubro y `contrato.garantia_meses`; B2 sin propuesta pide portada y cierre; B3
JSON inválido, B4 sin actividades, B5 422 de WordPress con fases desconocidas, días 0 y 200 y un texto de 5000
caracteres, B6 contexto 404, B7 y B7b OpenAI caído con y sin detalle y B11/B11b un 500 al guardar terminan en
`POST /plan/9/error` con motivo legible y correo; B12 un plan que ya no está en «generando» ni en «error» sin contenido
no gasta en OpenAI ni se toca; B13 un 409 no lo pasa a «error»; B14 guardado sin vista previa → correo a Luis).

- [ ] **Step 15: Commit**

```bash
git add N8N/plan-trabajo/build_plan_1_borrador.py N8N/plan-trabajo/plan-1-borrador.json N8N/plan-trabajo/probar_plan.py
git commit -m "feat(plan-n8n): flujo «Plan de trabajo · 1 Borrador» (contexto del contrato, GPT-4o y guardado en WordPress)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 16: Insertar la sección `cambios` en la prueba (todavía falla)**

En `N8N/plan-trabajo/probar_plan.py`, con Edit, reemplazar la línea marcador por:
```python
@seccion('cambios')
def prueba_cambios():
    from build_plan_2_cambios import PROMPT_CAMBIOS  # noqa: F401  (vuelve a escribir plan-2-cambios.json)
    revisar_workflow('plan-2-cambios.json', 'Plan de trabajo · 2 Cambios', 'plan-v1-cambios')
    ok('easypanel' not in json.dumps(cargar('plan-2-cambios.json')), '2 Cambios: no toca el renderer ni enlaza a *.easypanel.host')
    faltan = [x for x in TOPES_PROMPT if x not in PROMPT_CAMBIOS]
    ok(not faltan, 'PROMPT_CAMBIOS: dice los mismos topes que valida WordPress y pide no mandar el «Arranque»', faltan)
    GUARDADO_OK = {'statusCode': 200, 'body': {'ok': True, 'errores': [], 'avisos': []}}
    MARCADO = {'statusCode': 200, 'body': {'ok': True}}
    hook = {'id': 9, 'codigo': 'PRUEBAplan01'}
    ctx = contexto(estado='cambios', plan_actual=PLAN_GUARDADO, comentarios='Agrega la capacitación y cambia la foto del método.')

    # 1. Luis editó los días de «Diseño de la portada» (7, origen «luis»); el modelo los cambia a 2: vuelven a 7.
    resp = json.loads(json.dumps(PLAN_GUARDADO))
    del resp['cronograma']
    for _, _, a in actividades(resp):
        a.pop('desde', None)
        a.pop('hasta', None)
    actividad(resp, 'Diseño de la portada').update(dias_habiles=2, origen='ia')
    actividad(resp, 'Construcción del sitio').update(origen='luis', dias_habiles=12)
    resp['fases'][1]['bloques'][0]['actividades'].append(act('Capacitación del equipo', 'ambos', 1))
    resp['image_briefs'][0] = {'slide': 'metodo', 'prompt': 'baker opening the shutters of the bakery at dawn'}
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [GUARDADO_OK]},
                openai={'Aplicar cambios': [{'content': json.dumps(resp, ensure_ascii=False)}]})
    sin_error(t, 'C1')
    g = llamadas(t, 'Guardar borrador')
    ok(len(g) == 1 and g[0]['url'] == WP + '/plan/9/borrador' and g[0]['body']['origen'] == 'cambios', 'C1: POST /plan/9/borrador con origen «cambios»', g)
    plan = g[0]['body']['plan'] if g else PLAN_GUARDADO
    portada = actividad(plan, 'Diseño de la portada') or {}
    ok(portada.get('dias_habiles') == 7 and portada.get('origen') == 'luis',
       'C1: los días que Luis editó a mano no se pisan (7, «luis») aunque el modelo los cambie a 2', portada)
    # Lo que n8n MANDA; WordPress vuelve a marcar el origen al guardar (at_pt_respetar_dias_luis y
    # at_pt_marcar_ediciones(…, 'ia'), Tasks 2 y 7) con la misma regla.
    ok((actividad(plan, 'Construcción del sitio') or {}).get('origen') == 'ia',
       'C1: el modelo no puede marcar «luis»: la actividad de la tabla a la que le cambió los días va «ia» (revisar)')
    ok((actividad(plan, 'Diseño del catálogo') or {}).get('origen') == 'tabla', 'C1: una actividad de la tabla que no cambió va «tabla»')
    ok((actividad(plan, 'Capacitación del equipo') or {}).get('origen') == 'ia', 'C1: la actividad nueva va «ia»')
    ok(plan['image_briefs'][0]['prompt'] == 'baker opening the shutters of the bakery at dawn, ' + CIERRE
       and plan['image_briefs'][1] == PLAN_GUARDADO['image_briefs'][1], 'C1: la foto pedida cambia y la otra queda idéntica (mismo hash)')
    o = t['openai'].get('Aplicar cambios', [{}])[0]
    pedido = json.loads(o.get('user') or '{}')
    ok(o.get('temperature') == 0.2 and o.get('system') == PROMPT_CAMBIOS and pedido.get('comentarios') == ctx['comentarios'],
       'C1: gpt-4o a 0,2 con PROMPT_CAMBIOS y los comentarios de Luis')
    pa = pedido.get('plan_actual') or {}
    ok('cronograma' not in pa and all('desde' not in a and 'hasta' not in a for _, _, a in actividades(pa)) and pa.get('fases'),
       'C1: el modelo recibe el plan sin fechas (las recalcula WordPress)')
    ok('Arranque' not in [b['nombre'] for f in pa.get('fases', []) for b in f['bloques']]
       and (pedido.get('contrato') or {}).get('garantia_meses') == 6,
       'C1: el modelo no recibe el bloque «Arranque» (D12) y sí los meses de garantía del contrato (D7)')
    ok(plan['fases'][0]['bloques'][0] == PLAN_GUARDADO['fases'][0]['bloques'][0],
       'C1: a WordPress el «Arranque» vuelve tal como estaba guardado', plan['fases'][0]['bloques'][0])
    ok(pedido.get('slides_foto') == NUEVAS and pedido.get('rubro') == '[PRUEBA] Panadería',
       'C1: con propuesta, el modelo solo puede describir láminas nuevas, del rubro del cliente')

    # 2. Sin comentarios: no se llama al modelo y el plan guardado vuelve tal cual.
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': dict(ctx, comentarios='  ')}],
                'Guardar borrador': [GUARDADO_OK]}, openai={})
    sin_error(t, 'C2')
    g = llamadas(t, 'Guardar borrador')
    ok(not t['openai'] and g and g[0]['body']['plan'] == PLAN_GUARDADO, 'C2: sin comentarios, sin OpenAI, y el plan vuelve idéntico')

    # 3. Respuesta parcial (solo fases): lo demás se conserva.
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [GUARDADO_OK]},
                openai={'Aplicar cambios': [{'content': json.dumps({'fases': resp['fases']}, ensure_ascii=False)}]})
    g = llamadas(t, 'Guardar borrador')
    p3 = g[0]['body']['plan'] if g else {}
    ok(p3.get('image_briefs') == PLAN_GUARDADO['image_briefs'] and p3.get('hitos') == PLAN_GUARDADO['hitos'],
       'C3: lo que el modelo omite se conserva del plan guardado')

    # 4. Una nota suelta, 5. sin plan guardado, 6. respuesta cortada, 7. envuelta, 8. vacía.
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Marcar error': [MARCADO]},
                openai={'Aplicar cambios': [{'content': '{"nota": "ya está listo"}'}]})
    me = llamadas(t, 'Marcar error')
    ok(me and 'claves que no son del plan: nota' in me[0]['body']['nota'] and not llamadas(t, 'Guardar borrador'), 'C4: una nota no pasa como cambio', me)
    ok(t['correos'] and t['correos'][0]['asunto'].endswith('no se pudieron aplicar los cambios al plan') and 'Volver al borrador' in t['correos'][0]['html'],
       'C4: correo de cambios con el siguiente paso')
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': dict(ctx, plan_actual=None)}], 'Marcar error': [MARCADO]}, openai={})
    me = llamadas(t, 'Marcar error')
    ok(not t['openai'] and me and me[0]['body']['nota'].startswith('El plan no tiene un borrador guardado al que aplicarle cambios'),
       'C5: sin plan guardado → error, sin gastar en OpenAI', me)
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Marcar error': [MARCADO]},
                openai={'Aplicar cambios': [{'content': json.dumps(resp)[:500]}]})
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('La respuesta del modelo no es un JSON válido'), 'C6: respuesta cortada → error', me)
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [GUARDADO_OK]},
                openai={'Aplicar cambios': [{'content': json.dumps({'comentarios': 'x', 'plan_actual': resp})}]})
    ok(len(llamadas(t, 'Guardar borrador')) == 1, 'C7: {"plan_actual": …, "comentarios": …} se desenvuelve y se guarda')
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Marcar error': [MARCADO]},
                openai={'Aplicar cambios': [{'content': '{}'}]})
    me = llamadas(t, 'Marcar error')
    ok(me and 'no trae ninguna parte del plan' in me[0]['body']['nota'], 'C8: {} → error', me)

    # 9. Respuesta parcial: solo la fase que cambió y solo la foto pedida → a WordPress va el plan entero.
    impl = json.loads(json.dumps(resp['fases'][1]))
    parcial = {'fases': [impl], 'image_briefs': [{'slide': 'metodo', 'prompt': 'baker opening the shutters of the bakery at dawn'}]}
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [GUARDADO_OK]},
                openai={'Aplicar cambios': [{'content': json.dumps(parcial, ensure_ascii=False)}]})
    g = llamadas(t, 'Guardar borrador')
    p9 = g[0]['body']['plan'] if g else {'fases': []}
    ok([f['clave'] for f in p9['fases']] == ['diseno_desarrollo', 'implementacion', 'soporte']
       and (actividad(p9, 'Diseño de la portada') or {}).get('dias_habiles') == 7 and actividad(p9, 'Capacitación del equipo'),
       'C9: si el modelo devuelve solo la fase que cambió, las otras fases (con los días de Luis) no se pierden', [f.get('clave') for f in p9['fases']])
    ok([b['slide'] for b in p9.get('image_briefs', [])] == ['metodo', 'gantt'] and p9['image_briefs'][1] == PLAN_GUARDADO['image_briefs'][1],
       'C9: si el modelo devuelve solo la foto pedida, las demás quedan idénticas')

    # 10. Llegó tarde: el plan ya no está en «cambios» (Luis lo destrabó y volvió al borrador, u otra ejecución lo guardó).
    for estado in ('borrador', 'aprobando'):
        t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': dict(ctx, estado=estado)}]}, openai={})
        sin_error(t, f'C10 {estado}')
        ok(not t['openai'] and not llamadas(t, 'Guardar borrador') and not llamadas(t, 'Marcar error') and not t['correos'],
           f'C10: plan en «{estado}» → no se piden cambios, no se guarda, no se marca error ni se escribe')
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': dict(ctx, estado='error')}],
                'Guardar borrador': [GUARDADO_OK]}, openai={'Aplicar cambios': [{'content': json.dumps(resp, ensure_ascii=False)}]})
    ok(len(llamadas(t, 'Guardar borrador')) == 1, 'C10: plan en «error» → los cambios se aplican (WordPress los acepta)')
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': dict(ctx, estado='generando', plan_actual=None)}]}, openai={})
    ok(not llamadas(t, 'Marcar error') and not t['correos'], 'C10: un plan que se está generando no se pasa a «error» por un pedido de cambios viejo')

    # 11. WordPress responde 409 al guardar: no se pasa a «error» el plan que ya siguió.
    tarde = {'statusCode': 409, 'body': {'ok': False, 'errores': ['El plan está en «borrador»: este cambios llegó tarde y no se aplica.'], 'avisos': []}}
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [tarde]},
                openai={'Aplicar cambios': [{'content': json.dumps(resp, ensure_ascii=False)}]})
    sin_error(t, 'C11')
    ok(len(llamadas(t, 'Guardar borrador')) == 1 and not llamadas(t, 'Marcar error') and not t['correos'], 'C11: 409 → sin «error» ni correo')

    # 12. Guardado sin vista previa y 13. un 422 de WordPress en cambios.
    sin_vista = {'statusCode': 200, 'body': {'ok': True, 'errores': [], 'avisos': ['No se pudo pedir la vista previa: sin respuesta']}}
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [sin_vista]},
                openai={'Aplicar cambios': [{'content': json.dumps(resp, ensure_ascii=False)}]})
    ok(not llamadas(t, 'Marcar error') and len(t['correos']) == 1 and t['correos'][0]['asunto'].endswith('cambios del plan guardados sin vista previa'),
       'C12: cambios guardados sin vista previa → correo a Luis', [c['asunto'] for c in t['correos']])
    rechazo = {'statusCode': 422, 'body': {'ok': False, 'errores': ['Días hábiles fuera de rango (1 a 60) en «Capacitación del equipo»: 0'], 'avisos': []}}
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [rechazo],
                'Marcar error': [MARCADO]}, openai={'Aplicar cambios': [{'content': json.dumps(resp, ensure_ascii=False)}]})
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('WordPress rechazó el plan: Días hábiles fuera de rango') and t['correos']
       and 'Volver al borrador' in t['correos'][0]['html'], 'C13: un 422 en cambios → «error» con el motivo de WordPress y correo', me)


# ==== Las tareas siguientes agregan sus secciones justo antes de esta línea ====
```

- [ ] **Step 17: Correr `cambios` y verla fallar**

```bash
python N8N/plan-trabajo/probar_plan.py cambios; echo "exit=$?"
```
Expected:
```
== cambios
FALLA cambios: la sección lanzó ModuleNotFoundError: No module named 'build_plan_2_cambios'
1 FALLA(S)
exit=1
```

- [ ] **Step 18: Escribir `N8N/plan-trabajo/build_plan_2_cambios.py` (con `PROMPT_CAMBIOS`)**
```python
"""Construye el workflow n8n «Plan de trabajo · 2 Cambios» y lo guarda en plan-2-cambios.json.

Diseño: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md; Task 13 del plan de implementación (Etapa 1).
Lo llama el panel del plan («Pedir cambios», at_pt_cambios) con {id, codigo} y X-AT-Secret, después de guardar los
comentarios y pasar el plan a «cambios». GPT-4o aplica los comentarios sobre el plan guardado sin tocar los días
que Luis editó a mano (origen «luis»); este flujo además los restituye si el modelo los cambió. Sin comentarios no
se llama al modelo: se vuelve a guardar el plan actual (WordPress recalcula y pide la vista previa).
Nunca deja el plan trabado en «cambios»: si algo falla, lo pasa a «error» con el motivo y le escribe a Luis.
Si llegó tarde (el plan ya no está en «cambios» ni en «error», o WordPress responde 409), no hace nada. Si WordPress
guardó pero no pudo pedir la vista previa, le escribe a Luis.
Solo referencia credenciales por id; no contiene secretos.
"""
import json, os, sys

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'propuestas-v3'))
from fotos_guard import JS_LIMPIAR_FOTOS  # noqa: E402
from json_guard import JS_LEER_JSON  # noqa: E402
from correos_plan import AVISO_SIN_VISTA, correo_error_plan, correo_sin_vista  # noqa: E402
from plan_js import JS_PLAN, code_motivo  # noqa: E402

CRED_OPENAI = {'openAiApi': {'id': 'g52IEXpRfN5r7jKw', 'name': 'OpenAi account'}}
CRED_SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
LUIS = 'lmgm.0303@gmail.com'
FULL = {'response': {'response': {'fullResponse': True, 'neverError': True}}}
LIB = JS_LEER_JSON + '\n' + JS_LIMPIAR_FOTOS + '\n' + JS_PLAN + '\n'

PROMPT_CAMBIOS = """Recibes en JSON el plan de trabajo actual de un proyecto (plan_actual), los comentarios del consultor (comentarios), los datos del contrato firmado (contrato), la tabla de tiempos de referencia (tabla), el rubro del cliente según su ficha (rubro; puede venir vacío) y las láminas que llevan foto nueva (slides_foto). Devuelve SOLO el objeto JSON del plan completo, directamente (no lo envuelvas en otra clave como "plan" o "plan_actual"; sin texto antes ni después, sin bloques de código), con la misma forma y las mismas claves que plan_actual, aplicando SOLO los cambios que piden los comentarios; todo lo demás queda idéntico.
Reglas:
- Español de Chile, trato de "tú", frases simples.
- Las actividades con "origen": "luis" las editó el consultor a mano: nunca cambies sus "dias_habiles" ni su nombre (su detalle, solo si un comentario lo pide).
- Conserva el "origen" de cada actividad que ya existe. En una actividad nueva escribe "origen": "ia".
- No cambies nombres de bloques ni de actividades salvo que un comentario lo pida: el sistema reconoce las actividades por su nombre.
- fases: las mismas tres claves, en el mismo orden: "diseno_desarrollo", "implementacion" y "soporte".
- plan_actual viene sin el bloque «Arranque» (reunión de inicio y entrega de logo, textos y accesos). NO incluyas el bloque «Arranque» en tu respuesta, aunque un comentario hable de él: el sistema lo pone tal como está guardado (el consultor lo edita en el panel).
- No escribas fechas ("desde", "hasta", "fecha_inicio", "cronograma"): el sistema las recalcula en días hábiles.
- TOPES que revisa el sistema (si pasas uno, el plan entero se rechaza): como máximo 13 bloques, 10 actividades por bloque y 58 actividades en total; de 1 a 60 días hábiles por actividad; el plan completo, en secuencia y contando 5 días hábiles de revisión por cada bloque con entrega, no pasa de 130 días hábiles (unas 26 semanas); como máximo 10 hitos; fases solo con las claves "diseno_desarrollo", "implementacion" y "soporte", y responsable solo "at", "cliente" o "ambos". Si un comentario pide algo que no cabe, junta bloques o actividades que no sean del consultor.
- dias_habiles: entero (ver TOPES). en_paralelo: la primera actividad de cada bloque siempre false. servicio y etapa: como vienen; en una actividad nueva que calce con la tabla, su clave de tabla y su etapa ("diseno", "desarrollo", "pruebas" o "implementacion"); si no, "".
- soporte.garantia_meses: contrato.garantia_meses si viene; si no, deja el que trae plan_actual.
- entrega: true solo en bloques que terminan con algo que el cliente revisa y aprueba (el sistema agrega sus 5 días hábiles de revisión).
- Lo contratado manda (contrato): no agregues trabajo fuera de lo contratado salvo que un comentario lo pida expresamente. No inventes datos del cliente (nombres, teléfonos, direcciones, cifras) ni escribas montos.
- Si un comentario pide cambiar una foto, reescribe solo ese image_brief y deja los demás idénticos: fotografía realista del rubro del cliente (rubro; si viene vacío, dedúcelo del plan y del contrato; se permiten personas en acción); la de cover siempre en primer plano con el fondo desenfocado; productos con etiqueta o pantalla, sin etiqueta, de espaldas o apagados; nunca pantallas, computadores, oficinas, letreros, carteles, documentos, planos, hojas ni texto; de 40 a 60 palabras (máximo 400 caracteres); termina con "no signs, no labels, no text, no lettering, no logos, no watermarks". Nunca crees image_briefs para láminas que no estén en slides_foto."""

CODE_PEDIDO = r"""// Lo que ve el modelo: el plan guardado (sin fechas: las recalcula WordPress; sin el bloque «Arranque»: lo pone
// WordPress y «Leer plan» restituye el guardado), los comentarios de Luis, lo contratado (con los meses de garantía),
// el rubro, la tabla y las láminas con foto nueva.
const c = ($input.first().json || {}).body || {};
const hayPropuesta = esObjetoPlano(c.propuesta);
const actual = JSON.parse(JSON.stringify(c.plan_actual || {}));
delete actual.cronograma;
delete actual.fecha_inicio;
quitarArranque(actual);
recorrerActividades(actual, (a) => { delete a.desde; delete a.hasta; });
const permitidas = Array.isArray(c.slides_foto) && c.slides_foto.length ? c.slides_foto : null;
const n = Array.isArray(actual.fases) ? actual.fases.length : 3;
const datos = {
  comentarios: String(c.comentarios || '').trim(),
  rubro: String(c.rubro || '').slice(0, 100),
  plan_actual: actual,
  contrato: esObjetoPlano(c.contrato) ? c.contrato : {},
  tabla: esObjetoPlano(c.tabla) ? c.tabla : {},
  slides_foto: slidesConFoto(hayPropuesta, n).filter((s) => !permitidas || permitidas.includes(s)),
};
return [{ json: { mensaje: JSON.stringify(datos) } }];"""

CODE_LEER = r"""// Nunca lanza: si la respuesta no sirve, ok=false con el motivo y el flujo pasa el plan a «error».
// Sin comentarios no se llamó al modelo: el plan guardado pasa por el mismo camino (queda igual).
const ctx = $('Leer contexto').first().json.body || {};
const hook = $('Webhook').first().json.body || {};
const anterior = esObjetoPlano(ctx.plan_actual) ? ctx.plan_actual : {};
const conIa = $('Aplicar cambios').isExecuted;
const ia = conIa ? $('Aplicar cambios').first().json : { message: { content: JSON.stringify(anterior) } };
const r = leerPlanModelo(ia, { modo: 'cambios', hayPropuesta: esObjetoPlano(ctx.propuesta), anterior });
return [{ json: Object.assign({ id: parseInt(hook.id, 10) || 0, con_ia: conIa }, r) }];"""


def node(id_, name, type_, version, pos, params, **extra):
    n = {'id': id_, 'name': name, 'type': type_, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


def http_wp(id_, name, pos, method, url, body_expr=None, timeout=30000):
    params = {'method': method, 'url': url, 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
              'options': dict(FULL, timeout=timeout)}
    if body_expr:
        params.update({'sendBody': True, 'specifyBody': 'json', 'jsonBody': body_expr})
    return node(id_, name, 'n8n-nodes-base.httpRequest', 4.2, pos, params, credentials=CRED_WP,
                onError='continueRegularOutput')


def iff(id_, name, pos, left):
    return node(id_, name, 'n8n-nodes-base.if', 2.2, pos, {
        'conditions': {'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'loose', 'version': 2},
                       'conditions': [{'id': id_ + '-c', 'leftValue': left, 'rightValue': True,
                                       'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
                       'combinator': 'and'},
        'options': {}})


nodes = [
    node('q1', 'Webhook', 'n8n-nodes-base.webhook', 2, [0, 0],
         {'httpMethod': 'POST', 'path': 'plan-v1-cambios', 'authentication': 'headerAuth',
          'responseMode': 'onReceived', 'options': {}},
         webhookId='plan-v1-cambios', credentials=CRED_WP),
    http_wp('q2', 'Leer contexto', [220, 0], 'GET', f"={WP}/plan/{{{{ $json.body.id }}}}/contexto"),
    iff('q3', '¿Contexto leído?', [440, 0], '={{ $json.statusCode === 200 && !!$json.body && $json.body.ok === true }}'),
    # Llegó tarde si el plan ya no espera cambios (POST /borrador con origen «cambios» solo se acepta en «cambios» o
    # «error»; Task 7). Va antes de mirar el plan guardado: un pedido viejo no pasa a «error» un plan que se está
    # generando. Falso termina aquí: sin gastar en GPT-4o ni tocar el plan.
    iff('q15', '¿Espera respuesta?', [660, 0], "={{ ['cambios', 'error'].includes(String($json.body.estado)) }}"),
    iff('q16', '¿Hay plan guardado?', [880, 0],
        "={{ !!$json.body.plan_actual && typeof $json.body.plan_actual === 'object' && !Array.isArray($json.body.plan_actual) }}"),
    iff('q4', '¿Hay comentarios?', [1100, 0], "={{ String(($json.body && $json.body.comentarios) || '').trim().length > 0 }}"),
    node('q5', 'Preparar pedido', 'n8n-nodes-base.code', 2, [1320, -120], {'jsCode': LIB + CODE_PEDIDO}),
    node('q6', 'Aplicar cambios', 'n8n-nodes-base.openAi', 1, [1540, -120],
         {'resource': 'chat', 'model': 'gpt-4o',
          'prompt': {'messages': [{'role': 'system', 'content': PROMPT_CAMBIOS}, {'content': '={{ $json.mensaje }}'}]},
          'options': {'temperature': 0.2}, 'requestOptions': {}},
         credentials=CRED_OPENAI, onError='continueRegularOutput'),
    node('q7', 'Leer plan', 'n8n-nodes-base.code', 2, [1760, 0], {'jsCode': LIB + CODE_LEER}),
    iff('q8', '¿Plan legible?', [1980, 0], '={{ $json.ok === true }}'),
    http_wp('q9', 'Guardar borrador', [2200, -120], 'POST', f"={WP}/plan/{{{{ $json.id }}}}/borrador",
            "={{ JSON.stringify({ plan: $json.plan, origen: 'cambios' }) }}", timeout=60000),
    iff('q10', '¿Guardado?', [2420, -120], '={{ $json.statusCode === 200 && !!$json.body && $json.body.ok === true }}'),
    iff('q17', '¿Vista previa pedida?', [2640, -240],
        "={{ ![].concat(($json.body && $json.body.avisos) || []).some((a) => String(a).startsWith("
        + json.dumps(AVISO_SIN_VISTA) + ")) }}"),
    node('q18', 'Armar correo sin vista', 'n8n-nodes-base.code', 2, [2860, -240], {'jsCode': correo_sin_vista('cambios')}),
    # 409 = WordPress dice que estos cambios llegaron tarde (el plan ya siguió): no se marca error ni se escribe.
    iff('q19', '¿Llegó tarde?', [2640, 0], '={{ $json.statusCode === 409 }}'),
    node('q11', 'Motivo del error', 'n8n-nodes-base.code', 2, [2860, 160], {'jsCode': LIB + code_motivo('cambios')}),
    http_wp('q12', 'Marcar error', [3080, 160], 'POST', f"={WP}/plan/{{{{ $json.id }}}}/error",
            "={{ JSON.stringify({ nota: $json.reason + ' (ejecución ' + $json.exec + ')' }) }}"),
    node('q13', 'Armar correo error', 'n8n-nodes-base.code', 2, [3300, 160], {'jsCode': correo_error_plan('cambios')}),
    node('q14', 'Correo a Luis', 'n8n-nodes-base.emailSend', 1, [3520, -40],
         {'fromEmail': 'contacto@automatizatech.cl', 'toEmail': LUIS,
          'subject': '={{ $json.asunto }}', 'html': '={{ $json.html }}', 'options': {}},
         credentials=CRED_SMTP),
]

connections = {}


def link(a, b, output=0):
    connections.setdefault(a, {'main': []})
    while len(connections[a]['main']) <= output:
        connections[a]['main'].append([])
    connections[a]['main'][output].append({'node': b, 'type': 'main', 'index': 0})


link('Webhook', 'Leer contexto')
link('Leer contexto', '¿Contexto leído?')
link('¿Contexto leído?', '¿Espera respuesta?', 0)
link('¿Contexto leído?', 'Motivo del error', 1)
link('¿Espera respuesta?', '¿Hay plan guardado?', 0)
link('¿Hay plan guardado?', '¿Hay comentarios?', 0)
link('¿Hay plan guardado?', 'Motivo del error', 1)
link('¿Hay comentarios?', 'Preparar pedido', 0)
link('¿Hay comentarios?', 'Leer plan', 1)
link('Preparar pedido', 'Aplicar cambios')
link('Aplicar cambios', 'Leer plan')
link('Leer plan', '¿Plan legible?')
link('¿Plan legible?', 'Guardar borrador', 0)
link('¿Plan legible?', 'Motivo del error', 1)
link('Guardar borrador', '¿Guardado?')
link('¿Guardado?', '¿Vista previa pedida?', 0)
link('¿Guardado?', '¿Llegó tarde?', 1)
# Vista previa pedida: termina aquí; «3 Render» le escribe a Luis cuando la vista previa esté lista.
link('¿Vista previa pedida?', 'Armar correo sin vista', 1)
link('Armar correo sin vista', 'Correo a Luis')
link('¿Llegó tarde?', 'Motivo del error', 1)
link('Motivo del error', 'Marcar error')
link('Marcar error', 'Armar correo error')
link('Armar correo error', 'Correo a Luis')

wf = {'name': 'Plan de trabajo · 2 Cambios', 'nodes': nodes, 'connections': connections,
      # Si el flujo se cae sin llegar a su propio aviso, «Propuestas v3 · 0 Avisar error» le escribe a Luis.
      'settings': {'executionOrder': 'v1', 'errorWorkflow': 'm7TOfKznVSBGz4Nd'}}
out = os.path.join(AQUI, 'plan-2-cambios.json')
with open(out, 'w', encoding='utf-8') as fh:
    json.dump(wf, fh, ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
```

- [ ] **Step 19: Construir el flujo y correr la suite completa**

```bash
python N8N/plan-trabajo/build_plan_2_cambios.py
```
```bash
SAL=$(python N8N/plan-trabajo/probar_plan.py 2>&1); echo "exit=$?"
echo "$SAL" | grep -c '^ok '; echo "$SAL" | grep -c '^FALLA'; echo "$SAL" | tail -1
git status --short N8N/
```
Expected: `escrito …plan-2-cambios.json 19 nodos` y `exit=0`, `188`, `0`, `TODO OK` (45 de la sección `cambios`: `PROMPT_CAMBIOS`
trae los mismos topes; C1 los días de Luis vuelven a 7, el modelo no puede marcar «luis», no recibe el «Arranque» y a
WordPress vuelve el guardado; C2 sin comentarios no se llama a OpenAI y el plan vuelve idéntico;
C9 una respuesta con solo la fase y la foto que cambiaron no borra las demás; C10 y C11 un pedido tardío o un 409 no
tumban el plan; C12 guardado sin vista previa → correo; C13 un 422 en cambios → «error» con el motivo). `git status`
muestra ` M N8N/plan-trabajo/probar_plan.py`, `?? N8N/plan-trabajo/build_plan_2_cambios.py` y
`?? N8N/plan-trabajo/plan-2-cambios.json`, sin `__pycache__`. La suite tarda cerca de un minuto: cada caso arranca `node`.

- [ ] **Step 20: Commit y comprobar que los JSON commiteados son los que generan los builders**

```bash
git add N8N/plan-trabajo/build_plan_2_cambios.py N8N/plan-trabajo/plan-2-cambios.json N8N/plan-trabajo/probar_plan.py
git commit -m "feat(plan-n8n): flujo «Plan de trabajo · 2 Cambios» que respeta los días editados por Luis" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
python N8N/plan-trabajo/build_plan_1_borrador.py > /dev/null && python N8N/plan-trabajo/build_plan_2_cambios.py > /dev/null && git diff --exit-code -- N8N/plan-trabajo/ && echo "JSON commiteados = builders"
```
Expected: la última línea es `JSON commiteados = builders` (lo que se despliega en la Task 16 es lo que se probó).

### Task 14: n8n — flujo «3 Render» (fotos de la propuesta reutilizadas, reintentos, vista y correos)

Un flujo que arma la presentación del plan con el renderer y le cuenta el resultado a WordPress:
- **draft** (tras el borrador, los cambios o «Guardar y recalcular»): vista previa SIN fotos, venga lo que venga en
  `image_briefs`; un solo render; `POST /vista {modo: 'draft'}` (WordPress no cambia el estado). Correo solo si
  WordPress pidió `aviso` o si algo falló. **Si `GET /render` dice que el plan ya está `aprobando`, `listo` o `enviado`,
  no se dibuja** (decisión D10): una vista previa atrasada pisaría en `/p/<codigo>/` la versión final. El flujo termina
  ahí, sin llamar al renderer, sin `POST /vista` y sin correo (el webhook ya respondió al recibir).
- **final** (al «Aprobar», plan en «aprobando»): si hay `propuesta_uid`, lee `img/manifest.json` de la propuesta en el
  renderer y `reutilizarFotosPlan()` pone portada ← `cover` y cierre ← `next_steps` en `render.images` (URL absoluta; el
  renderer la copia junto al plan y no genera esa foto). Con propuesta, portada y cierre **nunca** se piden como foto
  nueva, aunque falte el manifest: así lo que se paga calza con el costo que el panel muestra (`at_pt_costo_fotos`
  no las cuenta); la lámina va sin foto y la nota lo avisa. Último filtro antes de gastar con `fotosDelPlan` (idempotente:
  no cambia el hash de una foto ya guardada). Reintenta hasta **3 renders en total** con el mismo cuerpo (el renderer
  reutiliza por hash lo ya pagado) solo lo que puede salir distinto (decisión D11): error de red o tiempo vencido, un
  5xx, o un 200 al que le faltan fotos o la presentación. Un 4xx no se reintenta (el 400 `{error: 'invalid payload',
  details: [...]}` del esquema, Task 11, da lo mismo cada vez) y sus `details` van a la nota. Para distinguirlos, el nodo
  «Render» pide la respuesta completa (`fullResponse` + `neverError`, como las llamadas a WordPress) y
  `leerRespuestaRender()` la lee (acepta también el cuerpo suelto). Termina SIEMPRE en `POST /vista`: en final,
  `ok: true` solo con presentación y sin fotos faltantes; si no, `ok: false` y WordPress lo pasa a «error» (nunca queda
  en «aprobando»). Siempre escribe a Luis en final, y solo dice «listo» si WordPress guardó el resultado y respondió
  `estado: 'listo'` (si Luis destrabó el plan mientras se generaba, WordPress guarda los enlaces pero el plan sigue en
  «error», y el correo lo dice).
- La base de WordPress ya trae `?rest_route=`, así que el modo va con `&modo=` (con `?modo=` PHP lo deja dentro de
  `rest_route` y la ruta no calza; probado con `parse_str` de PHP 8.4.15).
- Nunca enlaza `view_url`, `pdf_url` ni el renderer en el correo: el botón abre la pestaña del plan en el panel, con el
  `crm_cliente_id` que entrega `/render`.
- Despliegue (Task 16, decisión D17): «3 Render» va **primero**, antes que «1 Borrador» y «2 Cambios», porque WordPress
  lo llama apenas guarda un borrador.

Casos del Review Focus que esta tarea prueba (parte n8n): **4** (sin propuesta, portada y cierre piden foto nueva y no se
lee ningún manifest; `portal_url` vacío llega intacto al renderer, que dibuja «Sigue tu proyecto» sin enlace, Task 12);
**5** (renderer caído o WordPress que no entrega el cuerpo o no guarda la vista → el plan nunca queda en «aprobando»:
`POST /vista` final sin ok y correo con «Destrabar»). La Gantt larga (6) la dibuja el renderer (Task 12); aquí solo
viaja. El prompt de la Task 13 pide como máximo 7 bloques, pero eso es una instrucción al modelo y n8n no la hace
cumplir: WordPress acepta hasta `AT_PT_MAX_BLOQUES = 14` bloques con el Arranque (hasta 27 barras contando una revisión
por entrega) y Luis puede agregar más en el panel, así que la prueba de legibilidad en 1920×1080 y en el PDF (Task 12)
debe usar ese máximo.

**Files:**
- Modify: `N8N/plan-trabajo/plan_js.py` (agregar al final `RENDERER_BASE` y `JS_RENDER`)
- Modify: `N8N/plan-trabajo/correos_plan.py` (agregar al final `correo_render()`)
- Create: `N8N/plan-trabajo/build_plan_3_render.py` → genera y se commitea `N8N/plan-trabajo/plan-3-render.json`
- Test: `N8N/plan-trabajo/probar_plan.py` (insertar las secciones `render_puras`, `correos_render` y `render`, cada una
  en su paso rojo, justo antes de la línea marcador `# ==== Las tareas siguientes agregan sus secciones justo antes de esta línea ====`)

**Interfaces:**
- Consumes:
  - Task 13: `JS_PLAN` (`esObjetoPlano`, `SLIDES_DE_PROPUESTA`, `fotosDelPlan(briefs, nFases, hayPropuesta)`), `JS_LEER_JSON`,
    `JS_LIMPIAR_FOTOS`; `correos_plan.js_correo_plan(...)`, `JS_PANEL`/`urlPanel(crm, plan)`, `etiqueta`, `caja`, `parrafo`,
    `boton`, `nota`; de `probar_plan.py`: `seccion`, `ok`, `node_js`, `lib`, `simular`, `llamadas`, `sin_error`,
    `revisar_workflow`, `brief`, `PLAN_GUARDADO`, `NUEVAS`, `TODAS`, `CIERRE`, `WP`.
  - Task 6 (decisión D6): WordPress llama al webhook `plan-v1-render` con `{id, codigo, modo: 'draft'|'final', aviso: bool}`
    y `X-AT-Secret` (`at_pt_pedir_render($plan_id, $modo, $aviso)`). El flujo usa `id`, `modo` y `aviso`; un modo
    desconocido se trata como `draft` (nunca se pagan fotos por error).
  - Task 7 (decisión D10): `GET /plan/{id}/render&modo=draft|final` (la base ya trae `?rest_route=`) → `{ok, render:
    <cuerpo de at_pt_armar_render>, propuesta_uid: '' | '<unique_link_id>', crm_cliente_id, estado}` (`crm_cliente_id`
    entero, 0 si la ficha no está enlazada; `estado` del plan, que decide si una vista previa todavía se dibuja);
    `POST /plan/{id}/vista {modo, ok, view_url, pdf_url, faltan: [slides], nota}` → `{ok, estado}` (en final completa con
    el plan «aprobando» lo pasa a «listo»; en `draft` no guarda los enlaces si el plan ya está `aprobando`, `listo` o
    `enviado`; guarda hasta 500 caracteres de nota).
  - Task 11 (renderer): `POST /render` con `document_type: 'plan'` → 200 `{view_url, pdf_url, images: {requested,
    stored_local, kept_remote, missing, reused}}`, 400 `{error: 'invalid payload', details: string[]}` (esquema: no se
    reintenta) o 502 `{error: 'render failed', details: string}` (se reintenta); `images: {slide: url}` gana sobre
    generar la foto; `GET /p/<uid>/img/manifest.json` → `{slide: {file, hash}}` (`origin/main:renderer/src/server.js`,
    `photo-manifest.js`; grupo-D.md, Task 11).
- Produces:
  - `plan_js.RENDERER_BASE = 'https://n8n-propuesta-renderer.kchiba.easypanel.host'` (solo para n8n; nunca en un correo).
  - `plan_js.JS_RENDER` (JavaScript): `NOMBRES_LAMINAS`, `nombresLaminas(l) → string`,
    `reutilizarFotosPlan(render, manifest, base, uid) → {render (copia), reutilizadas: string[], sin_foto: string[]}`,
    `ESTADOS_SIN_VISTA_PREVIA = ['aprobando', 'listo', 'enviado']`,
    `leerRespuestaRender(x) → {status, cuerpo, view_url, pdf_url, images, missing, detalles, fallo, reintentable}`.
  - `correos_plan.correo_render() -> str` (nodo que devuelve `{asunto, html, enviar}`).
  - `build_plan_3_render.MAX_RENDERS = 3`; workflow «Plan de trabajo · 3 Render» (webhook `plan-v1-render`, 15 nodos,
    `settings.errorWorkflow = 'm7TOfKznVSBGz4Nd'`). La Task 15 simula estas mismas llamadas (con `&modo=`) y la Task 16
    lo despliega.

- [ ] **Step 1: Insertar los datos del render y la sección `render_puras` (todavía falla)**

En `N8N/plan-trabajo/probar_plan.py`, con Edit, reemplazar la línea marcador por este bloque, que termina con la MISMA
línea marcador (así la próxima sección y la próxima tarea pueden volver a insertar):
```python
# ---------------------------------------------------------------- secciones de la Task 14 («3 Render»)

BASE_R = 'https://n8n-propuesta-renderer.kchiba.easypanel.host'
UID_PROP = 'PRUEBAprop01'
VISTA = BASE_R + '/p/PRUEBAplan01/index.html'
PDF = BASE_R + '/p/PRUEBAplan01/presentation.pdf'
BRIEFS_NUEVAS = [brief(s, t) for s, t in (
    ('metodo', 'bakers preparing the counter before opening, warm morning light'),
    ('gantt', 'hands shaping loaves one by one on a floured table'),
    ('fase_1', 'flour, eggs and wooden tools laid out on a table'),
    ('fase_2', 'baker handing a warm loaf to the first customer of the day'),
    ('fase_3', 'baker calmly serving a customer in the afternoon'),
    ('necesitamos', 'hands gathering bread baskets on a counter'),
    ('reuniones', 'two bakers talking face to face in the bakery'),
    ('portal', 'owner resting with a cup of coffee in the bakery at dusk'))]
BRIEF_COVER = {'slide': 'cover', 'prompt': 'close-up of hands kneading bread dough, shallow depth of field, background completely blurred, ' + CIERRE}
BRIEF_CIERRE = brief('cierre', 'bakery team celebrating the end of the day')


def cuerpo_render(final=True, **cambios):
    """Cuerpo que arma WordPress (at_pt_armar_render) para el renderer; datos inventados."""
    b = {'document_type': 'plan', 'unique_id': 'PRUEBAplan01', 'draft': not final, 'company_name': '[PRUEBA] Panadería',
         'client_name': 'Cliente Prueba', 'proyecto': '[PRUEBA] Sitio de la panadería', 'fecha_firma_larga': '26 de septiembre de 2026',
         'fecha_inicio': '2026-09-28', 'fecha_fin': '2026-11-25', 'semanas': 9,
         'metodo': {'hechas': ['diagnostico', 'priorizacion'], 'actual': 'propuesta', 'proximas': ['diseno_desarrollo', 'implementacion', 'soporte']},
         'fases': PLAN_GUARDADO['fases'], 'cronograma': PLAN_GUARDADO['cronograma'], 'necesitamos_de_ti': ['Logo', 'Textos'],
         'reuniones': [{'nombre': 'Reunión de inicio', 'detalle': ''}], 'soporte': {'garantia_meses': 3, 'mensuales': []},
         'portal_url': '', 'agenda': {'whatsapp_url': 'https://wa.me/56900000000?text=prueba', 'web_url': ''},
         'image_briefs': ([BRIEF_COVER] + BRIEFS_NUEVAS + [BRIEF_CIERRE]) if final else [], 'images': {}}
    b.update(cambios)
    return b


def leido(render, uid=UID_PROP, estado=None, **extra):
    """Respuesta de GET /plan/{id}/render (Task 7, D10): cuerpo, código de la propuesta, ficha del CRM (5) y estado del
    plan («borrador» para una vista previa y «aprobando» para la versión final, si no se dice otro)."""
    if estado is None:
        estado = 'borrador' if render.get('draft') else 'aprobando'
    return [{'statusCode': 200, 'body': dict({'ok': True, 'render': render, 'propuesta_uid': uid, 'crm_cliente_id': 5,
                                              'estado': estado}, **extra)}]


def renderer_ok(pedidas=8, missing=(), sin_vista=False, error=None):
    """Salida del nodo «Render» (fullResponse + neverError): {statusCode, body}; {error} si se cortó la red."""
    if error:
        return {'error': {'message': error}}
    r = {'images': {'requested': pedidas, 'stored_local': pedidas - len(missing), 'kept_remote': [], 'missing': list(missing), 'reused': 0}}
    if not sin_vista:
        r.update(view_url=VISTA, pdf_url=PDF)
    return {'statusCode': 200, 'body': r, 'headers': {}}


def renderer_http(status, body):
    return {'statusCode': status, 'body': body, 'headers': {}}


RECHAZO_ESQUEMA = renderer_http(400, {'error': 'invalid payload', 'details': ['falta el objeto obligatorio: cronograma',
                                                                            'fases debe traer al menos una fase']})


MANIFEST_PROP = {'statusCode': 200, 'body': {'cover': {'file': 'cover.jpg', 'hash': 'a' * 64},
                                             'next_steps': {'file': 'next_steps.png', 'hash': 'b' * 64},
                                             'solution': {'file': 'solution.jpg', 'hash': 'c' * 64}}}
# Respuesta de POST /plan/{id}/vista (Task 7): {ok, estado}. La versión final completa deja el plan «listo».
VISTA_LISTO = [{'statusCode': 200, 'body': {'ok': True, 'estado': 'listo'}}]
VISTA_BORRADOR = [{'statusCode': 200, 'body': {'ok': True, 'estado': 'borrador'}}]


@seccion('render_puras')
def prueba_render_puras():
    from plan_js import JS_RENDER, RENDERER_BASE
    ok(RENDERER_BASE == BASE_R, 'RENDERER_BASE es el renderer de Easypanel (solo para n8n, nunca en un correo)')
    prog = lib() + JS_RENDER + r"""
const R = {};
const render = DATOS.render;
const copia = JSON.stringify(render);
R.completo = reutilizarFotosPlan(render, DATOS.manifest, DATOS.base, 'PRUEBAprop01');
R.no_muta = JSON.stringify(render) === copia;
R.sin_uid = reutilizarFotosPlan(render, DATOS.manifest, DATOS.base, '');
R.uid_malo = reutilizarFotosPlan(render, DATOS.manifest, DATOS.base, '../x');
R.texto = reutilizarFotosPlan(render, JSON.stringify({ cover: { file: 'cover.webp', hash: 'x' } }), DATOS.base, 'PRUEBAprop01');
R.nulo = reutilizarFotosPlan(render, null, DATOS.base, 'PRUEBAprop01');
R.ilegible = reutilizarFotosPlan(render, '<html>404</html>', DATOS.base, 'PRUEBAprop01');
R.arreglo = reutilizarFotosPlan(render, [1, 2], DATOS.base, 'PRUEBAprop01');
R.malicioso = reutilizarFotosPlan(render, { cover: { file: '../../secreto.jpg' }, next_steps: { file: '.oculto' } }, DATOS.base, 'PRUEBAprop01');
R.base_mala = reutilizarFotosPlan(render, DATOS.manifest, 'javascript:alert(1)', 'PRUEBAprop01');
R.dada = reutilizarFotosPlan(Object.assign({}, render, { images: { cover: 'https://example.com/foto.jpg' } }), {}, DATOS.base, 'PRUEBAprop01');
R.render_nulo = reutilizarFotosPlan(null, DATOS.manifest, DATOS.base, 'PRUEBAprop01');
R.nombres = nombresLaminas(['gantt', 'necesitamos', 'otra']);
const L = (x) => leerRespuestaRender(x);
R.resp = {
  ok: L(DATOS.r_ok), falta: L(DATOS.r_falta), sin_vista: L(DATOS.r_sin_vista), esquema: L(DATOS.r_400),
  caido: L(DATOS.r_502), red: L({ error: { message: 'socket hang up' } }), suelto: L(DATOS.r_ok.body),
  texto: L({ statusCode: 400, body: JSON.stringify(DATOS.r_400.body) }), html: L({ statusCode: 503, body: '<html>Bad gateway</html>' }),
  clave: L({ statusCode: 401, body: { error: 'unauthorized' } }), nada: L(null),
};
R.sin_vista_previa = ESTADOS_SIN_VISTA_PREVIA;
console.log(JSON.stringify(R));
"""
    r, err = node_js(prog, {'render': cuerpo_render(True), 'manifest': MANIFEST_PROP['body'], 'base': BASE_R,
                            'r_ok': renderer_ok(8), 'r_falta': renderer_ok(8, ['gantt']), 'r_sin_vista': renderer_ok(8, sin_vista=True),
                            'r_400': RECHAZO_ESQUEMA, 'r_502': renderer_http(502, {'error': 'render failed', 'details': 'Playwright timeout'})})
    ok(r is not None, 'render_puras: el JavaScript corre con node', err)
    if r is None:
        return
    c = r['completo']
    ok(c['render']['images'] == {'cover': BASE_R + '/p/PRUEBAprop01/img/cover.jpg', 'cierre': BASE_R + '/p/PRUEBAprop01/img/next_steps.png'},
       'reutilizarFotosPlan: portada ← cover y cierre ← next_steps de la propuesta, con URL absoluta', c['render']['images'])
    ok([b['slide'] for b in c['render']['image_briefs']] == NUEVAS, 'reutilizarFotosPlan: portada y cierre salen de las fotos a pagar')
    ok(c['reutilizadas'] == ['cover', 'cierre'] and c['sin_foto'] == [], 'reutilizarFotosPlan: informa las reutilizadas')
    ok(r['no_muta'], 'reutilizarFotosPlan: no cambia el objeto que recibe')
    ok(r['sin_uid']['render']['image_briefs'] == cuerpo_render(True)['image_briefs'] and r['sin_uid']['render']['images'] == {}
       and r['sin_uid']['reutilizadas'] == [] and r['sin_uid']['sin_foto'] == [],
       'reutilizarFotosPlan: sin propuesta no toca nada (portada y cierre piden foto nueva)')
    ok(r['uid_malo']['reutilizadas'] == [] and r['uid_malo']['render']['images'] == {}, 'reutilizarFotosPlan: un uid raro se trata como sin propuesta')
    ok(r['texto']['reutilizadas'] == ['cover'] and r['texto']['sin_foto'] == ['cierre']
       and r['texto']['render']['images']['cover'].endswith('/img/cover.webp'), 'reutilizarFotosPlan: lee el manifest aunque llegue como texto')
    for caso in ('nulo', 'ilegible', 'arreglo', 'base_mala'):
        x = r[caso]
        ok(x['reutilizadas'] == [] and x['sin_foto'] == ['cover', 'cierre'] and x['render']['images'] == {}
           and [b['slide'] for b in x['render']['image_briefs']] == NUEVAS,
           f'reutilizarFotosPlan: manifest o base {caso} → sin foto y sin pagar portada ni cierre', x['sin_foto'])
    m = r['malicioso']
    ok(m['reutilizadas'] == [] and m['render']['images'] == {} and m['sin_foto'] == ['cover', 'cierre'],
       'reutilizarFotosPlan: nombres de archivo con «..», barras u ocultos no se usan', m)
    ok(r['dada']['render']['images'] == {'cover': 'https://example.com/foto.jpg'} and r['dada']['sin_foto'] == ['cierre'],
       'reutilizarFotosPlan: una imagen que ya trae el cuerpo se respeta y no se avisa como faltante')
    ok(r['render_nulo']['render']['images']['cover'].endswith('/cover.jpg'), 'reutilizarFotosPlan: un cuerpo nulo no lanza')
    ok(r['nombres'] == 'carta Gantt, qué necesitamos de ti, otra', 'nombresLaminas: nombres legibles para las notas', r['nombres'])
    ok(r['sin_vista_previa'] == ['aprobando', 'listo', 'enviado'], 'ESTADOS_SIN_VISTA_PREVIA: aprobando, listo y enviado (D10)')
    x = r['resp']
    ok(x['ok']['status'] == 200 and x['ok']['view_url'] == VISTA and x['ok']['pdf_url'] == PDF and x['ok']['missing'] == []
       and x['ok']['reintentable'] is False, 'leerRespuestaRender: 200 completo → enlaces y nada que reintentar', x['ok'])
    ok(x['falta']['missing'] == ['gantt'] and x['falta']['reintentable'] is True, 'leerRespuestaRender: 200 con fotos faltantes → se reintenta')
    ok(x['sin_vista']['view_url'] == '' and x['sin_vista']['reintentable'] is True, 'leerRespuestaRender: 200 sin presentación → se reintenta')
    ok(x['esquema']['status'] == 400 and x['esquema']['reintentable'] is False
       and x['esquema']['detalles'] == ['falta el objeto obligatorio: cronograma', 'fases debe traer al menos una fase'],
       'leerRespuestaRender: 400 del esquema con details → no se reintenta y trae los motivos (D11)', x['esquema'])
    ok(x['caido']['status'] == 502 and x['caido']['reintentable'] is True and x['caido']['detalles'] == ['Playwright timeout'],
       'leerRespuestaRender: 502 con details en texto → se reintenta y trae el motivo', x['caido'])
    ok(x['red']['status'] == 0 and x['red']['fallo'] == 'socket hang up' and x['red']['reintentable'] is True,
       'leerRespuestaRender: error de red o tiempo vencido → se reintenta', x['red'])
    ok(x['suelto']['status'] == 200 and x['suelto']['view_url'] == VISTA, 'leerRespuestaRender: acepta el cuerpo suelto (sin fullResponse)')
    ok(x['texto']['detalles'] == x['esquema']['detalles'] and x['texto']['reintentable'] is False,
       'leerRespuestaRender: un cuerpo que llega como texto JSON se lee igual')
    ok(x['html']['status'] == 503 and x['html']['reintentable'] is True and x['html']['detalles'] == [] and x['html']['cuerpo'].get('error', '').startswith('<html>'),
       'leerRespuestaRender: un 503 con HTML → se reintenta, sin romperse', x['html'])
    ok(x['clave']['reintentable'] is False and x['nada']['status'] == 0 and x['nada']['reintentable'] is True,
       'leerRespuestaRender: un 401 no se reintenta; una salida vacía cuenta como falla de red')


# ==== Las tareas siguientes agregan sus secciones justo antes de esta línea ====
```

- [ ] **Step 2: Correr `render_puras` y verla fallar**

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
python N8N/plan-trabajo/probar_plan.py render_puras; echo "exit=$?"
```
Expected:
```
== render_puras
FALLA render_puras: la sección lanzó ImportError: cannot import name 'JS_RENDER' from 'plan_js' (…\N8N\plan-trabajo\plan_js.py)
1 FALLA(S)
exit=1
```

- [ ] **Step 3: Agregar al final de `N8N/plan-trabajo/plan_js.py`**
```python



# Task 14 — «3 Render». Nunca enlazar esta URL en un correo (el SMTP de Hostinger rechaza *.easypanel.host).
RENDERER_BASE = 'https://n8n-propuesta-renderer.kchiba.easypanel.host'

JS_RENDER = r"""
const NOMBRES_LAMINAS = { cover: 'portada', metodo: 'Método AT', gantt: 'carta Gantt', fase_1: 'fase 1', fase_2: 'fase 2',
  fase_3: 'fase 3', necesitamos: 'qué necesitamos de ti', reuniones: 'reuniones y soporte', portal: 'sigue tu proyecto',
  cierre: 'cierre' };
const nombresLaminas = (l) => (Array.isArray(l) ? l : []).map((s) => NOMBRES_LAMINAS[s] || String(s)).join(', ');

// Decisión 7: portada y cierre del plan reutilizan, sin costo, las fotos de la propuesta (cover y next_steps).
// manifest = img/manifest.json de la propuesta en el renderer ({lámina: {file, hash}}); base = URL del renderer;
// uid = unique_link_id de la propuesta. Pone la URL absoluta en render.images (el renderer la copia junto al plan y no
// genera esa foto) y, si hay propuesta, quita portada y cierre de render.image_briefs: nunca se pagan, porque el panel
// no las cuenta en el costo (at_pt_costo_fotos). Si la propuesta no tiene esa foto, la lámina va sin foto y se avisa.
// Nunca lanza y no cambia `render`: devuelve {render (copia), reutilizadas, sin_foto}.
function reutilizarFotosPlan(render, manifest, base, uid) {
  const r = JSON.parse(JSON.stringify(esObjetoPlano(render) ? render : {}));
  const images = esObjetoPlano(r.images) ? r.images : {};
  const reutilizadas = [];
  const sin_foto = [];
  if (!/^[A-Za-z0-9_-]{6,64}$/.test(String(uid || ''))) {
    r.images = images;
    return { render: r, reutilizadas, sin_foto };
  }
  let m = manifest;
  if (typeof m === 'string') { try { m = JSON.parse(m); } catch (e) { m = null; } }
  if (!esObjetoPlano(m)) m = {};
  const baseOk = /^https?:\/\/[A-Za-z0-9.-]+(:\d+)?$/.test(String(base || ''));
  for (const [lamina, dePropuesta] of Object.entries(SLIDES_DE_PROPUESTA)) {
    const e = Object.prototype.hasOwnProperty.call(m, dePropuesta) ? m[dePropuesta] : null;
    // Nombre de archivo como los que guarda el renderer (<lámina>.<ext>): sin «..», sin barras ni archivos ocultos.
    const file = esObjetoPlano(e) && typeof e.file === 'string' && /^[A-Za-z0-9_-]+\.[A-Za-z0-9]{2,5}$/.test(e.file) ? e.file : '';
    if (file && baseOk) {
      images[lamina] = base + '/p/' + uid + '/img/' + file;
      reutilizadas.push(lamina);
    } else if (!images[lamina]) {
      sin_foto.push(lamina);
    }
  }
  r.image_briefs = (Array.isArray(r.image_briefs) ? r.image_briefs : [])
    .filter((b) => !(esObjetoPlano(b) && Object.prototype.hasOwnProperty.call(SLIDES_DE_PROPUESTA, b.slide)));
  r.images = images;
  return { render: r, reutilizadas, sin_foto };
}

// Decisión D10: con el plan en uno de estos estados, una vista previa no se dibuja (pisaría la versión final en
// /p/<codigo>/). WordPress tampoco guarda sus enlaces (POST /vista, Task 7).
const ESTADOS_SIN_VISTA_PREVIA = ['aprobando', 'listo', 'enviado'];

// Salida del nodo «Render» (fullResponse + neverError): {statusCode, body, headers}; si se cortó la red o venció el
// tiempo, {error} sin statusCode (onError: continueRegularOutput). También acepta el cuerpo suelto (sin fullResponse).
// Decisión D11: se reintenta solo lo que puede salir distinto (la red, un 5xx, o un 200 sin presentación o con fotos
// faltantes). Un 4xx da lo mismo cada vez: el 400 del esquema trae sus motivos en `details` (Task 11), que van a la nota.
// Nunca lanza.
function leerRespuestaRender(x) {
  const j = esObjetoPlano(x) ? x : {};
  let status = 0;
  let cuerpo = {};
  let fallo = '';
  if (j.statusCode !== undefined && j.statusCode !== null && j.statusCode !== '') {
    status = Number(j.statusCode) || 0;
    let b = j.body;
    if (typeof b === 'string') {
      try { b = JSON.parse(b); } catch (e) { b = { error: b.slice(0, 200) }; }
    }
    cuerpo = esObjetoPlano(b) ? b : {};
  } else if (j.error) {
    const e = j.error;
    fallo = typeof e === 'string' ? e : String(e.message || e.description || 'sin detalle');
  } else if (Object.keys(j).length) {
    status = 200;
    cuerpo = j;
  } else {
    fallo = 'sin respuesta';
  }
  const d = cuerpo.details;
  const detalles = (Array.isArray(d) ? d : (d == null || d === '' ? [] : [d])).map((t) => String(t).slice(0, 200)).filter(Boolean).slice(0, 5);
  const images = status === 200 && esObjetoPlano(cuerpo.images) ? cuerpo.images : {};
  const missing = Array.isArray(images.missing) ? images.missing.map(String) : [];
  const url = (u) => (status === 200 && typeof u === 'string' ? u : '');
  const view_url = url(cuerpo.view_url);
  const pdf_url = url(cuerpo.pdf_url);
  const reintentable = status === 0 || status >= 500 || (status === 200 && (!view_url || missing.length > 0));
  return { status, cuerpo, view_url, pdf_url, images, missing, detalles, fallo, reintentable };
}
"""
```

- [ ] **Step 4: Correr la suite completa y verla pasar**

```bash
SAL=$(python N8N/plan-trabajo/probar_plan.py 2>&1); echo "exit=$?"
echo "$SAL" | grep -c '^ok '; echo "$SAL" | grep -c '^FALLA'; echo "$SAL" | tail -1
```
Expected: `exit=0`, `216`, `0`, `TODO OK` (28 de `render_puras`, entre ellas «reutilizarFotosPlan: nombres de archivo con «..»,
barras u ocultos no se usan», «manifest o base nulo → sin foto y sin pagar portada ni cierre» y «leerRespuestaRender: 400
del esquema con details → no se reintenta y trae los motivos (D11)»).

- [ ] **Step 5: Commit**

```bash
git add N8N/plan-trabajo/plan_js.py N8N/plan-trabajo/probar_plan.py
git commit -m "feat(plan-n8n): portada y cierre del plan reutilizan las fotos de la propuesta sin costo" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 6: Insertar la sección `correos_render` (todavía falla)**

En `N8N/plan-trabajo/probar_plan.py`, con Edit, reemplazar la línea marcador por:
```python
@seccion('correos_render')
def prueba_correos_render():
    from correos_plan import correo_render
    codigo = correo_render()

    def correr(v, g):
        js = ("const $ = (n) => ({ isExecuted: n in DATOS, first: () => ({ json: DATOS[n] }) });\n"
              "const salida = (function () {\n" + codigo + "\n})();\nconsole.log(JSON.stringify(salida[0].json));")
        return node_js(js, {'Resultado del render': v, 'Guardar vista': g})

    base = {'id': 9, 'crm': 5, 'exec': '888', 'proyecto': '[PRUEBA] Sitio <i>panadería</i>', 'view_url': VISTA, 'pdf_url': PDF,
            'faltan': [], 'problemas': [], 'avisos': [], 'resumen': []}
    g_listo = {'statusCode': 200, 'body': {'ok': True, 'estado': 'listo'}}
    g_error = {'statusCode': 200, 'body': {'ok': True, 'estado': 'error'}}
    g_borr = {'statusCode': 200, 'body': {'ok': True, 'estado': 'borrador'}}
    p = '[PRUEBA] Sitio <i>panadería</i>'
    sin_guardar = '⚠️ ' + p + ' · el resultado del plan no quedó guardado en WordPress'
    # (nombre, resultado, respuesta de /vista, enviar, asunto, frases que debe traer, frases que no debe traer)
    casos = [
        ('final ok', dict(base, modo='final', aviso=False, ok=True,
                          resumen=['Presentación y PDF generados', '8 de 8 fotos nuevas guardadas junto al plan',
                                   'Con las fotos de la propuesta, sin costo: portada, cierre'],
                          avisos=['la propuesta no tiene foto guardada para cierre: esas láminas van sin foto']), g_listo,
         True, '✅ ' + p + ' · plan de trabajo listo',
         ['Con las fotos de la propuesta, sin costo', '⚠️ la propuesta no tiene foto', '>Listo<', 'quedó <strong>listo</strong>'], ['«aprobando»']),
        ('final con problemas', dict(base, modo='final', aviso=False, ok=False,
                                     problemas=['fotos que no se generaron tras 3 intentos: carta Gantt']), g_error,
         True, '⚠️ ' + p + ' · la versión final del plan tiene problemas', ['carta Gantt', 'no se vuelven a pagar', 'Con problemas'], ['>Listo<']),
        ('final ok con el plan en error', dict(base, modo='final', aviso=False, ok=True, resumen=['Presentación y PDF generados']), g_error,
         True, '⚠️ ' + p + ' · la versión final salió, pero el plan quedó en «error»',
         ['dejó el plan en «error»', 'no se vuelven a pagar', 'Con problemas'], ['quedó <strong>listo</strong>', '>Listo<']),
        ('borrador con aviso', dict(base, modo='draft', aviso=True, ok=True, resumen=['Vista previa generada (sin fotos)']), g_borr,
         True, p + ' · borrador del plan listo para revisar', ['solo al «Aprobar»', 'Borrador'], []),
        ('borrador sin aviso', dict(base, modo='draft', aviso=False, ok=True), g_borr, False, None, [], []),
        ('borrador que falló', dict(base, modo='draft', aviso=False, ok=False,
                                    problemas=['el renderer no devolvió la presentación (timeout)']), g_borr,
         True, '⚠️ ' + p + ' · la vista previa del plan no se pudo generar', ['Guardar y recalcular', 'timeout'], []),
        ('final sin guardar en WordPress', dict(base, modo='final', aviso=False, ok=True), {'statusCode': 500, 'body': {}},
         True, sin_guardar, ['No se pudo guardar el resultado en WordPress', 'HTTP 500', '«aprobando»', 'Destrabar', 'Con problemas',
                             'La presentación salió bien'], ['quedó <strong>listo</strong>', '>Listo<']),
        ('borrador sin guardar en WordPress', dict(base, modo='draft', aviso=False, ok=True), {'error': {'message': 'ECONNRESET'}},
         True, sin_guardar, ['No se pudo guardar el resultado en WordPress', 'sin respuesta', 'Con problemas'], ['>Borrador<']),
    ]
    for nombre, v, g, enviar, asunto, frases, ausentes in casos:
        r, err = correr(v, g)
        ok(r is not None, f'correo render {nombre}: el código corre', err)
        if r is None:
            continue
        ok(r['enviar'] is enviar, f'correo render {nombre}: enviar = {enviar}', r['enviar'])
        if asunto:
            ok(r['asunto'] == asunto, f'correo render {nombre}: asunto', r['asunto'])
        h = r['html']
        ok('easypanel' not in h, f'correo render {nombre}: no enlaza a *.easypanel.host (ni la vista ni el PDF)')
        ok('automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in h and 'Plan de trabajo · aviso interno' in h,
           f'correo render {nombre}: botón a la pestaña del plan y rótulo del plan')
        ok('&lt;i&gt;panadería&lt;/i&gt;' in h and '<i>panadería</i>' not in h, f'correo render {nombre}: escapa el proyecto')
        faltan = [f for f in frases if f not in h]
        ok(not faltan, f'correo render {nombre}: trae lo que debe decir', faltan)
        sobran = [f for f in ausentes if f in h]
        ok(not sobran, f'correo render {nombre}: no dice lo que no corresponde', sobran)
    r, _ = correr(dict(base, modo='final', aviso=False, ok=True, proyecto='x'), g_listo)
    ok(r and '«aprobando»' not in r['html'], 'correo render: si WordPress guardó, no habla de un plan trabado')


# ==== Las tareas siguientes agregan sus secciones justo antes de esta línea ====
```

- [ ] **Step 7: Correr `correos_render` y verla fallar**

```bash
python N8N/plan-trabajo/probar_plan.py correos_render; echo "exit=$?"
```
Expected:
```
== correos_render
FALLA correos_render: la sección lanzó ImportError: cannot import name 'correo_render' from 'correos_plan' (…\N8N\plan-trabajo\correos_plan.py)
1 FALLA(S)
exit=1
```

- [ ] **Step 8: Agregar al final de `N8N/plan-trabajo/correos_plan.py`**
```python



def correo_render():
    """Correo de «3 Render»: vista previa lista (solo si WordPress pidió aviso), versión final lista o con problemas.

    Además de {asunto, html} devuelve {enviar}: sin aviso, una vista previa que salió bien y quedó guardada no manda
    correo (Luis está mirando el panel, por ejemplo tras «Guardar y recalcular»). Solo dice «listo» si WordPress guardó
    el resultado y dejó el plan en «listo» (POST /vista responde {ok, estado}).
    """
    prep = """const v = $('Resultado del render').first().json;
const g = $('Guardar vista').first().json;
const guardado = g.statusCode === 200;
const ok = v.ok === true;
const final = v.modo === 'final';
// Una versión final que salió bien deja el plan «listo» solo si seguía «aprobando»: si Luis lo destrabó mientras
// tanto, WordPress guarda los enlaces pero el plan sigue en «error». Sin «estado» en la respuesta, se asume «listo».
const estadoWp = g.body && typeof g.body.estado === 'string' ? g.body.estado : '';
const quedoListo = !final || estadoWp === '' || estadoWp === 'listo';
const bien = ok && guardado && quedoListo;
const enviar = v.aviso === true || final || !ok || !guardado;
const panel = urlPanel(v.crm, v.id);
const que = !guardado ? 'el resultado del plan no quedó guardado en WordPress'
  : final ? (!ok ? 'la versión final del plan tiene problemas'
    : (quedoListo ? 'plan de trabajo listo' : 'la versión final salió, pero el plan quedó en «' + estadoWp + '»'))
  : (ok ? 'borrador del plan listo para revisar' : 'la vista previa del plan no se pudo generar');
const titular = que.charAt(0).toUpperCase() + que.slice(1).replace(' para revisar', '');
const li = (t) => `<li style="margin:0 0 6px;">${t}</li>`;
const lista = (l, pre) => (Array.isArray(l) ? l : []).map((t) => li(pre + esc(t))).join('');"""
    ul = '<ul style="margin:0;padding-left:18px;">'
    no_guardado = caja('<strong>No se pudo guardar el resultado en WordPress</strong> '
                       '(${esc(g.statusCode ? "HTTP " + g.statusCode : "sin respuesta")}).'
                       '${final ? " El plan puede haber quedado en «aprobando»: revísalo en el panel y usa «Destrabar»." : ""}',
                       'error')
    no_quedo_listo = caja('La versión final salió bien, pero WordPress dejó el plan en «${esc(estadoWp)}» (por ejemplo, si '
                          'lo destrabaste mientras se generaba): no quedó «listo». Revísalo en el panel: puedes volver al '
                          'borrador o aprobarlo de nuevo, y las fotos que ya salieron no se vuelven a pagar.', 'aviso')
    final_ok = (caja(ul + '${lista(v.resumen, "")}${lista(v.avisos, "⚠️ ")}</ul>', 'ok')
                + "${!guardado ? `" + parrafo('La presentación salió bien, pero WordPress no registró el resultado.')
                + "` : (quedoListo ? `" + parrafo('El plan quedó <strong>listo</strong>. Revísalo en el panel: nada se le '
                                                  'envió al cliente.')
                + "` : `" + no_quedo_listo + "`)}")
    final_mal = (caja(ul + '${lista(v.problemas, "")}${lista(v.avisos, "⚠️ ")}</ul>', 'error')
                 + parrafo('El plan quedó en <strong>error</strong>. Desde el panel puedes volver al borrador o aprobarlo de '
                           'nuevo: las fotos que ya salieron no se vuelven a pagar.'))
    borrador_ok = (parrafo('${guardado ? "La vista previa del plan (sin fotos) quedó lista." : "La vista previa del plan '
                           '(sin fotos) se generó, pero el panel no la tiene: «Guardar y recalcular» la vuelve a pedir."} '
                           'En la pestaña «Plan de trabajo» de la ficha del cliente ves las actividades, los días y las '
                           'fechas: ahí los editas, pides cambios o lo apruebas.')
                   + "${(v.avisos || []).length ? `" + caja(ul + '${lista(v.avisos, "⚠️ ")}</ul>', 'aviso') + "` : ''}"
                   + parrafo('Las fotos nuevas se generan solo al «Aprobar»; su cantidad y su costo aparecen en el panel antes.'))
    borrador_mal = (caja(ul + '${lista(v.problemas, "")}</ul>', 'error')
                    + parrafo('El plan sigue en borrador. «Guardar y recalcular» en el panel vuelve a generar la vista previa.'))
    cuerpo = ("${guardado ? '' : `" + no_guardado + "`}"
              + "${ok ? (final ? `" + final_ok + "` : `" + borrador_ok + "`) : (final ? `" + final_mal + "` : `"
              + borrador_mal + "`)}"
              + '<div style="margin:6px 0 4px;">' + boton('${esc(panel)}', '✏️ Abrir el plan en el panel') + '</div>'
              + nota('Ejecución de n8n: ${esc(v.exec)}. La presentación y el PDF se abren desde el panel. '
                     'Nada de esto le llegó al cliente.'))
    etiquetas = ("${bien ? (final ? `" + etiqueta('Listo', 'lista') + "` : `" + etiqueta('Borrador', 'borrador') + "`) : `"
                 + etiqueta('Con problemas', 'error') + "`}")
    asunto = "(bien ? (final ? '✅ ' : '') : '⚠️ ') + v.proyecto + ' · ' + que"
    titulo = '${esc(titular)}: ${esc(v.proyecto)}'
    return js_correo_plan(prep, asunto, titulo, etiquetas, cuerpo, extra='enviar')
```

- [ ] **Step 9: Correr la suite completa y verla pasar**

```bash
SAL=$(python N8N/plan-trabajo/probar_plan.py 2>&1); echo "exit=$?"
echo "$SAL" | grep -c '^ok '; echo "$SAL" | grep -c '^FALLA'; echo "$SAL" | tail -1
```
Expected: `exit=0`, `280`, `0`, `TODO OK` (64 de `correos_render`: en los ocho casos el HTML no contiene «easypanel» aunque
`view_url` y `pdf_url` sean del renderer; «borrador sin aviso» da `enviar = False`; «final ok con el plan en error» y
los dos «sin guardar en WordPress» no dicen «listo»).

- [ ] **Step 10: Commit**

```bash
git add N8N/plan-trabajo/correos_plan.py N8N/plan-trabajo/probar_plan.py
git commit -m "feat(plan-n8n): correo a Luis del render del plan (vista previa, versión final y problemas)" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 11: Insertar la sección `render` (todavía falla)**

En `N8N/plan-trabajo/probar_plan.py`, con Edit, reemplazar la línea marcador por:
```python
@seccion('render')
def prueba_render():
    from build_plan_3_render import MAX_RENDERS  # vuelve a escribir plan-3-render.json
    wf = revisar_workflow('plan-3-render.json', 'Plan de trabajo · 3 Render', 'plan-v1-render')
    rn = next(n for n in wf['nodes'] if n['name'] == 'Render')
    ok(rn['credentials']['httpHeaderAuth']['id'] == 'fj2orzbsjlnHaiLd' and rn['parameters']['url'] == BASE_R + '/render'
       and rn['parameters']['options']['timeout'] == 290000 and rn.get('onError') == 'continueRegularOutput'
       and rn['parameters']['options']['response']['response'] == {'fullResponse': True, 'neverError': True},
       'plan-3-render.json: el render va con la clave X-AT-Render-Key, 290 s, respuesta completa (código HTTP) y sin cortar el flujo')
    fp = next(n for n in wf['nodes'] if n['name'] == 'Fotos de la propuesta')
    ok('credentials' not in fp and fp.get('onError') == 'continueRegularOutput',
       'plan-3-render.json: el manifest público se lee sin credenciales y nunca corta el flujo')
    ok(MAX_RENDERS == 3, 'plan-3-render.json: hasta 3 renders en total')
    correo = next(n for n in wf['nodes'] if n['name'] == 'Armar correo')['parameters']['jsCode']
    ok('easypanel' not in correo, 'plan-3-render.json: el código del correo no nombra *.easypanel.host')

    def sim(webhook, **http):
        http.setdefault('Guardar vista', VISTA_LISTO if webhook.get('modo') == 'final' else VISTA_BORRADOR)
        return simular('plan-3-render.json', webhook=webhook, http=http, openai={})

    # R1. Vista previa con aviso: sin fotos aunque WordPress mande alguna, un solo render, correo «borrador listo».
    t = sim({'id': 9, 'modo': 'draft', 'aviso': True}, **{'Leer render': leido(cuerpo_render(False, image_briefs=[BRIEFS_NUEVAS[0]])),
                                                         'Render': [renderer_ok(0)]})
    sin_error(t, 'R1')
    ok(llamadas(t, 'Leer render')[0]['url'] == WP + '/plan/9/render&modo=draft',
       'R1: GET /plan/9/render con &modo=draft (la base ya trae ?rest_route=)', llamadas(t, 'Leer render'))
    rc = llamadas(t, 'Render')
    ok(len(rc) == 1 and rc[0]['body']['draft'] is True and rc[0]['body']['image_briefs'] == [] and rc[0]['body']['unique_id'] == 'PRUEBAplan01',
       'R1: un render, draft, sin fotos y con el código del plan', rc)
    ok(not llamadas(t, 'Fotos de la propuesta'), 'R1: la vista previa no lee las fotos de la propuesta')
    gv = llamadas(t, 'Guardar vista')
    ok(gv and gv[0]['url'] == WP + '/plan/9/vista' and {k: gv[0]['body'][k] for k in ('modo', 'ok', 'view_url', 'pdf_url', 'faltan')}
       == {'modo': 'draft', 'ok': True, 'view_url': VISTA, 'pdf_url': PDF, 'faltan': []} and gv[0]['body']['nota'].startswith('Vista previa lista'),
       'R1: POST /plan/9/vista {modo: draft, ok: true, …}', gv)
    ok(len(t['correos']) == 1 and t['correos'][0]['asunto'] == '[PRUEBA] Sitio de la panadería · borrador del plan listo para revisar'
       and 'easypanel' not in t['correos'][0]['html'], 'R1: correo «borrador listo» sin enlaces a *.easypanel.host',
       [c['asunto'] for c in t['correos']])
    ok(t['correos'] and 'automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in t['correos'][0]['html'],
       'R1: el botón abre la pestaña del plan en la ficha del CRM (crm_cliente_id de /render)')

    # R2. Vista previa sin aviso («Guardar y recalcular»): sin correo. Con aviso 'true' como texto: con correo.
    t = sim({'id': 9, 'modo': 'draft', 'aviso': False}, **{'Leer render': leido(cuerpo_render(False)), 'Render': [renderer_ok(0)]})
    ok(not t['correos'] and llamadas(t, 'Guardar vista'), 'R2: vista previa sin aviso → se guarda y no se manda correo')
    t = sim({'id': 9, 'modo': 'draft', 'aviso': 'true'}, **{'Leer render': leido(cuerpo_render(False)), 'Render': [renderer_ok(0)]})
    ok(len(t['correos']) == 1, 'R2: aviso "true" como texto también avisa')

    # R3. La vista previa falla: no se reintenta, queda la nota y se avisa.
    t = sim({'id': 9, 'modo': 'draft', 'aviso': False}, **{'Leer render': leido(cuerpo_render(False)),
                                                          'Render': [renderer_ok(error='connect ECONNREFUSED')]})
    sin_error(t, 'R3')
    gv = llamadas(t, 'Guardar vista')
    ok(len(llamadas(t, 'Render')) == 1 and gv and gv[0]['body']['ok'] is False
       and gv[0]['body']['nota'].startswith('el renderer no devolvió la presentación (connect ECONNREFUSED)'), 'R3: un solo intento y nota con el motivo', gv)
    ok(t['correos'] and t['correos'][0]['asunto'].endswith('la vista previa del plan no se pudo generar'), 'R3: correo con el problema')

    # R4. Versión final con propuesta: portada y cierre reutilizan sus fotos; se pagan solo las 8 nuevas.
    render_final = cuerpo_render(True, image_briefs=[BRIEF_COVER] + BRIEFS_NUEVAS + [BRIEFS_NUEVAS[1], brief('challenge', 'x'), BRIEF_CIERRE])
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [renderer_ok(8)]})
    sin_error(t, 'R4')
    ok(llamadas(t, 'Leer render')[0]['url'].endswith('/plan/9/render&modo=final'), 'R4: GET con &modo=final')
    fpc = llamadas(t, 'Fotos de la propuesta')
    ok(fpc and fpc[0]['url'] == BASE_R + '/p/PRUEBAprop01/img/manifest.json', 'R4: lee el manifest de fotos de la propuesta', fpc)
    rc = llamadas(t, 'Render')
    body = rc[0]['body'] if rc else {}
    ok(len(rc) == 1 and body.get('draft') is False and body.get('images') == {'cover': BASE_R + '/p/PRUEBAprop01/img/cover.jpg',
                                                                                'cierre': BASE_R + '/p/PRUEBAprop01/img/next_steps.png'},
       'R4: un render final con portada y cierre desde la propuesta', body.get('images'))
    ok(body.get('image_briefs') == BRIEFS_NUEVAS,
       'R4: se piden solo las 8 fotos nuevas, sin repetidas ni láminas ajenas, con la misma descripción (mismo hash)')
    ok(all(body.get(k) == render_final[k] for k in ('document_type', 'unique_id', 'fases', 'cronograma', 'agenda', 'portal_url', 'client_name', 'metodo')),
       'R4: el resto del cuerpo de WordPress llega intacto al renderer (también portal_url vacío: la lámina «Sigue tu proyecto» sale sin enlace)')
    gv = llamadas(t, 'Guardar vista')
    ok(gv and gv[0]['body']['modo'] == 'final' and gv[0]['body']['ok'] is True and gv[0]['body']['faltan'] == []
       and gv[0]['body']['nota'].startswith('Versión final verificada: Presentación y PDF generados · 8 de 8 fotos nuevas guardadas junto al plan'
                                            ' · Con las fotos de la propuesta, sin costo: portada, cierre · 1 render'),
       'R4: POST /vista final ok (WordPress pasa el plan a «listo»)', gv)
    ok(t['correos'] and t['correos'][0]['asunto'] == '✅ [PRUEBA] Sitio de la panadería · plan de trabajo listo'
       and 'automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in t['correos'][0]['html'], 'R4: correo «plan de trabajo listo» con el botón a la ficha')

    # R5. Faltan fotos en los tres intentos: 3 renders con el mismo cuerpo y el plan pasa a «error».
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [renderer_ok(8, ['gantt', 'fase_2']), renderer_ok(8, ['gantt'])]})
    sin_error(t, 'R5')
    rc = llamadas(t, 'Render')
    ok(len(rc) == 3 and rc[0]['body'] == rc[1]['body'] == rc[2]['body'],
       'R5: tres renders con el mismo cuerpo (el renderer reutiliza lo ya pagado)', len(rc))
    gv = llamadas(t, 'Guardar vista')
    ok(gv and gv[0]['body']['ok'] is False and gv[0]['body']['faltan'] == ['gantt']
       and gv[0]['body']['nota'].startswith('fotos que no se generaron tras 3 intentos: carta Gantt') and '(ejecución SIM-1)' in gv[0]['body']['nota'],
       'R5: POST /vista final sin ok, con lo que falta (WordPress lo pasa a «error»)', gv)
    ok(t['correos'] and t['correos'][0]['asunto'].endswith('la versión final del plan tiene problemas'), 'R5: correo con problemas')

    # R6. La segunda pasada completa las fotos: queda listo con 2 renders.
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [renderer_ok(8, ['gantt']), renderer_ok(8)]})
    gv = llamadas(t, 'Guardar vista')
    ok(len(llamadas(t, 'Render')) == 2 and gv and gv[0]['body']['ok'] is True and '2 renders' in gv[0]['body']['nota'],
       'R6: se reintenta solo hasta que salen todas', gv)

    # R7. La propuesta no tiene manifest (404): portada y cierre sin foto, sin pagarlas, y el plan igual queda listo.
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final),
                                                          'Fotos de la propuesta': [{'statusCode': 404, 'body': 'Not Found'}],
                                                          'Render': [renderer_ok(8)]})
    rc = llamadas(t, 'Render')
    gv = llamadas(t, 'Guardar vista')
    ok(rc and rc[0]['body']['images'] == {} and [b['slide'] for b in rc[0]['body']['image_briefs']] == NUEVAS,
       'R7: sin manifest no se reutiliza ni se paga portada o cierre')
    ok(gv and gv[0]['body']['ok'] is True and 'la propuesta no tiene foto guardada para portada, cierre' in gv[0]['body']['nota'],
       'R7: queda listo con el aviso en la nota', gv)

    # R8. Contrato sin propuesta: portada y cierre piden foto nueva; no se lee ningún manifest.
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(cuerpo_render(True), uid=''), 'Render': [renderer_ok(10)]})
    rc = llamadas(t, 'Render')
    ok(not llamadas(t, 'Fotos de la propuesta') and rc and [b['slide'] for b in rc[0]['body']['image_briefs']] == TODAS
       and rc[0]['body']['images'] == {}, 'R8: sin propuesta se piden las 10 fotos (portada y cierre incluidas)')
    gv = llamadas(t, 'Guardar vista')
    ok(gv and gv[0]['body']['ok'] is True and '10 de 10 fotos nuevas' in gv[0]['body']['nota'], 'R8: listo con 10 fotos nuevas', gv)

    # R9. WordPress no entrega el cuerpo: no se renderiza y la versión final queda en «error» (nunca en «aprobando»).
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': [{'statusCode': 404, 'body': {'code': 'x', 'message': 'Plan no encontrado'}}]})
    sin_error(t, 'R9')
    gv = llamadas(t, 'Guardar vista')
    ok(not llamadas(t, 'Render') and gv and gv[0]['url'] == WP + '/plan/9/vista' and gv[0]['body']['modo'] == 'final'
       and gv[0]['body']['ok'] is False and gv[0]['body']['nota'].startswith('No se pudo leer el plan en WordPress (HTTP 404) — Plan no encontrado'),
       'R9: POST /vista final sin ok', gv)
    ok(t['correos'] and 'plan 9' in t['correos'][0]['asunto'], 'R9: correo aunque no se sepa el nombre del proyecto')

    # R10. Todo salió bien pero WordPress no guardó el resultado: el correo lo dice y sugiere «Destrabar».
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [renderer_ok(8)], 'Guardar vista': [{'statusCode': 500, 'body': {}}]})
    h = t['correos'][0]['html'] if t['correos'] else ''
    ok(t['correos'] and t['correos'][0]['asunto'].startswith('⚠️') and 'No se pudo guardar el resultado en WordPress' in h and 'Destrabar' in h,
       'R10: si WordPress no guarda, el correo avisa que el plan puede seguir en «aprobando»')

    # R11. El renderer nunca responde: 3 intentos y «error».
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [renderer_ok(error='timeout of 290000ms exceeded')]})
    gv = llamadas(t, 'Guardar vista')
    ok(len(llamadas(t, 'Render')) == 3 and gv and gv[0]['body']['ok'] is False
       and gv[0]['body']['nota'].startswith('el renderer no devolvió la presentación (timeout of 290000ms exceeded) tras 3 intentos'),
       'R11: 3 intentos y error', gv)

    # R12. Modo desconocido: se trata como vista previa (nunca se pagan fotos por error).
    t = sim({'id': 9, 'modo': 'otro'}, **{'Leer render': leido(cuerpo_render(True), estado='borrador'), 'Render': [renderer_ok(0)]})
    rc = llamadas(t, 'Render')
    ok(llamadas(t, 'Leer render')[0]['url'].endswith('&modo=draft') and rc and rc[0]['body']['draft'] is True
       and rc[0]['body']['image_briefs'] == [], 'R12: modo desconocido → vista previa sin fotos')

    # R13 (D10). Vista previa atrasada: el plan ya está «aprobando», «listo» o «enviado» → no se dibuja ni se escribe.
    for estado in ('aprobando', 'listo', 'enviado'):
        t = sim({'id': 9, 'codigo': 'PRUEBAplan01', 'modo': 'draft', 'aviso': True},
                **{'Leer render': leido(cuerpo_render(False), estado=estado), 'Render': [renderer_ok(0)]})
        sin_error(t, f'R13 {estado}')
        ok(t['pasos'][-1] == '¿Toca renderizar?' and not llamadas(t, 'Render') and not llamadas(t, 'Guardar vista') and not t['correos'],
           f'R13: vista previa con el plan en «{estado}» → no toca el renderer (/p/<codigo>/ queda con la versión final), ni /vista, ni correo',
           t['pasos'])
    t = sim({'id': 9, 'modo': 'draft', 'aviso': False}, **{'Leer render': leido(cuerpo_render(False), estado='error'), 'Render': [renderer_ok(0)]})
    ok(len(llamadas(t, 'Render')) == 1 and llamadas(t, 'Guardar vista'), 'R13: con el plan en «error» la vista previa sí se dibuja (Volver al borrador)')
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final, estado='aprobando'),
                                                          'Fotos de la propuesta': [MANIFEST_PROP], 'Render': [renderer_ok(8)]})
    ok(len(llamadas(t, 'Render')) == 1, 'R13: la versión final con el plan «aprobando» se dibuja')

    # R14 (D11). El renderer rechaza el cuerpo (400 del esquema): no se reintenta y los motivos van a la nota.
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [RECHAZO_ESQUEMA, renderer_ok(8)]})
    sin_error(t, 'R14')
    gv = llamadas(t, 'Guardar vista')
    ok(len(llamadas(t, 'Render')) == 1 and gv and gv[0]['body']['ok'] is False
       and gv[0]['body']['nota'].startswith('el renderer rechazó el plan (HTTP 400: falta el objeto obligatorio: cronograma; '
                                            'fases debe traer al menos una fase)'),
       'R14: 400 con details → un solo render y los motivos en la nota (WordPress pasa el plan a «error»)', gv)
    ok(t['correos'] and 'falta el objeto obligatorio: cronograma' in t['correos'][0]['html'], 'R14: el correo trae los motivos del renderer')
    t = sim({'id': 9, 'modo': 'draft', 'aviso': False}, **{'Leer render': leido(cuerpo_render(False)), 'Render': [RECHAZO_ESQUEMA]})
    gv = llamadas(t, 'Guardar vista')
    ok(gv and gv[0]['body']['ok'] is False and 'falta el objeto obligatorio: cronograma' in gv[0]['body']['nota'] and t['correos'],
       'R14: en la vista previa el 400 también llega a la nota y al correo', gv)

    # R15 (D11). Un 5xx sí se reintenta: el segundo render sale bien; si los tres fallan, la nota trae el HTTP y el motivo.
    caido = renderer_http(502, {'error': 'render failed', 'details': 'Playwright timeout'})
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [caido, renderer_ok(8)]})
    gv = llamadas(t, 'Guardar vista')
    ok(len(llamadas(t, 'Render')) == 2 and gv and gv[0]['body']['ok'] is True and '2 renders' in gv[0]['body']['nota'],
       'R15: un 502 se reintenta y la segunda pasada deja el plan listo', gv)
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [caido]})
    gv = llamadas(t, 'Guardar vista')
    ok(len(llamadas(t, 'Render')) == 3 and gv and gv[0]['body']['ok'] is False
       and gv[0]['body']['nota'].startswith('el renderer no devolvió la presentación (HTTP 502: Playwright timeout) tras 3 intentos'),
       'R15: tres 502 → 3 intentos y la nota con el código y el motivo', gv)


# ==== Las tareas siguientes agregan sus secciones justo antes de esta línea ====
```

- [ ] **Step 12: Correr `render` y verla fallar**

```bash
python N8N/plan-trabajo/probar_plan.py render; echo "exit=$?"
```
Expected:
```
== render
FALLA render: la sección lanzó ModuleNotFoundError: No module named 'build_plan_3_render'
1 FALLA(S)
exit=1
```

- [ ] **Step 13: Escribir `N8N/plan-trabajo/build_plan_3_render.py`**
```python
"""Construye el workflow n8n «Plan de trabajo · 3 Render» y lo guarda en plan-3-render.json.

Diseño: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md; Task 14 del plan de implementación (Etapa 1).
Lo llama WordPress (at_pt_pedir_render) con {id, codigo, modo: 'draft'|'final', aviso} y X-AT-Secret:
- draft: vista previa SIN fotos (tras el borrador, los cambios o «Guardar y recalcular»). No cambia el estado del
  plan; si falla, solo deja la nota. Correo a Luis solo si WordPress pidió aviso o si algo falló. Si GET /render dice
  que el plan ya está «aprobando», «listo» o «enviado», no la dibuja (pisaría la versión final): termina sin más.
- final: al «Aprobar» (plan en «aprobando»). Portada y cierre reutilizan las fotos de la propuesta (sin costo); las
  láminas nuevas piden fotos nuevas (gasto: US$0,0032 c/u de lista, el mismo cálculo que muestra el panel antes de
  aprobar). Si faltan fotos, no hubo presentación, se cortó la red o el renderer dio un 5xx, vuelve a llamarlo (hasta
  3 renders en total; el renderer reutiliza las fotos ya guardadas con el mismo prompt y no las vuelve a cobrar). Un
  4xx (el 400 del esquema) no se reintenta y sus motivos van a la nota. Termina SIEMPRE con POST /vista:
  WordPress pasa el plan a «listo» o a «error»; nunca queda trabado en «aprobando». Siempre le escribe a Luis.
Solo referencia credenciales por id; no contiene secretos.
"""
import json, os, sys

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'propuestas-v3'))
from fotos_guard import JS_LIMPIAR_FOTOS  # noqa: E402
from json_guard import JS_LEER_JSON  # noqa: E402
from correos_plan import correo_render  # noqa: E402
from plan_js import JS_PLAN, JS_RENDER, RENDERER_BASE  # noqa: E402

CRED_SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
CRED_RENDER = {'httpHeaderAuth': {'id': 'fj2orzbsjlnHaiLd', 'name': 'X-AT-Render-Key'}}  # clave de /render del renderer
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
RENDERER = RENDERER_BASE + '/render'
LUIS = 'lmgm.0303@gmail.com'
FULL = {'response': {'response': {'fullResponse': True, 'neverError': True}}}
MAX_RENDERS = 3
LIB = (JS_LEER_JSON + '\n' + JS_LIMPIAR_FOTOS + '\n' + JS_PLAN + '\n' + JS_RENDER + '\n'
       + 'const RENDERER_BASE = ' + json.dumps(RENDERER_BASE) + ';\n')

JS_AVISO = r"""const avisoPedido = (v) => v === true || v === 1 || v === '1' || v === 'true';
"""

CODE_PREPARAR = r"""// Arma el cuerpo que va al renderer. Nunca lanza.
const hook = $('Webhook').first().json.body || {};
const lr = ($('Leer render').first().json || {}).body || {};
const id = parseInt(hook.id, 10) || 0;
const modo = hook.modo === 'final' ? 'final' : 'draft';
const uid = /^[A-Za-z0-9_-]{6,64}$/.test(String(lr.propuesta_uid || '')) ? String(lr.propuesta_uid) : '';
let render = esObjetoPlano(lr.render) ? lr.render : {};
let reutilizadas = [];
let sin_foto = [];
if (modo === 'final') {
  let manifest = null;
  if ($('Fotos de la propuesta').isExecuted) {
    const fp = $('Fotos de la propuesta').first().json || {};
    if (fp.statusCode === 200) manifest = fp.body;
  }
  const x = reutilizarFotosPlan(render, manifest, RENDERER_BASE, uid);
  render = x.render;
  reutilizadas = x.reutilizadas;
  sin_foto = x.sin_foto;
  // Último filtro antes de gastar (idempotente, no cambia el hash de una foto ya guardada): solo láminas del plan,
  // una por lámina, reglas de fotos_guard; con propuesta, nunca portada ni cierre.
  render.image_briefs = fotosDelPlan(render.image_briefs, Array.isArray(render.fases) ? render.fases.length : 0, uid !== '');
  render.draft = false;
} else {
  // La vista previa nunca paga fotos, venga lo que venga de WordPress.
  render = Object.assign({}, render, { draft: true, image_briefs: [] });
}
return [{ json: { id, modo, aviso: avisoPedido(hook.aviso), render, reutilizadas, sin_foto,
  crm: parseInt(lr.crm_cliente_id, 10) || 0,
  proyecto: String(render.proyecto || render.company_name || ('plan ' + id)) } }];"""

CODE_REINTENTAR = r"""// Tras cada render: en la versión final se vuelve a llamar al renderer solo si puede salir distinto (D11): la red o
// el tiempo, un 5xx, o un 200 sin presentación o con fotos faltantes. Un 4xx (el 400 del esquema con details) da lo
// mismo cada vez: no se reintenta. El renderer reutiliza las fotos ya guardadas con el mismo prompt
// (img/manifest.json del plan) y solo pide las que faltan: un reintento no vuelve a pagar las que ya salieron.
// Tope: MAX_RENDERS llamadas en total. Nunca lanza.
const MAX_RENDERS = __MAX__;
const base = $('Preparar render').first().json;
const r = $input.first().json || {};
const intento = $runIndex + 1;
const lr = leerRespuestaRender(r);
return [{ json: Object.assign({}, base, { resultado: r, intento,
  reintentar: base.modo === 'final' && lr.reintentable && intento < MAX_RENDERS }) }];""".replace(
    '__MAX__', str(MAX_RENDERS))

CODE_RESULTADO = r"""// Resume lo que pasó para WordPress (POST /vista) y para el correo. Nunca lanza.
const hook = $('Webhook').first().json.body || {};
const id = parseInt(hook.id, 10) || 0;
const modo = hook.modo === 'final' ? 'final' : 'draft';
const exec = String($execution.id);
const vacio = { id, modo, aviso: avisoPedido(hook.aviso), exec, ok: false, view_url: '', pdf_url: '', faltan: [],
  problemas: [], avisos: [], resumen: [], nota: '', crm: 0, proyecto: 'plan ' + id, renders: 0 };
if (!$('Preparar render').isExecuted) {
  const lr = $('Leer render').first().json || {};
  const b = esObjetoPlano(lr.body) ? lr.body : {};
  const motivo = 'No se pudo leer el plan en WordPress (' + (lr.statusCode ? 'HTTP ' + lr.statusCode
    : 'sin respuesta' + (lr.error && lr.error.message ? ': ' + lr.error.message : '')) + ')' + (b.message ? ' — ' + b.message : '');
  return [{ json: Object.assign(vacio, { problemas: [motivo], nota: motivo + ' (ejecución ' + exec + ')' }) }];
}
const v = $input.first().json || {};
const lr = leerRespuestaRender(v.resultado);
const img = lr.images;
const intento = Number(v.intento) || 1;
const veces = intento + ' ' + (intento === 1 ? 'intento' : 'intentos');
const missing = lr.missing;
const remotas = Array.isArray(img.kept_remote) ? img.kept_remote.map(String) : [];
const pedidas = Number(img.requested) || 0;
const problemas = [];
const avisos = [];
if (!lr.view_url) {
  // D11: los motivos del renderer (details del 400 del esquema o del 502) van a la nota, que WordPress guarda.
  const motivo = lr.detalles.length ? lr.detalles.join('; ') : String(lr.cuerpo.error || '').slice(0, 200);
  const rechazo = lr.status >= 400 && lr.status < 500;
  const det = lr.status === 0 ? lr.fallo : (lr.status !== 200 ? 'HTTP ' + lr.status + (motivo ? ': ' + motivo : '') : '');
  problemas.push((rechazo ? 'el renderer rechazó el plan' : 'el renderer no devolvió la presentación') + (det ? ' (' + det + ')' : '')
    + (modo === 'final' && !rechazo ? ' tras ' + veces : ''));
}
if (modo === 'final' && missing.length) problemas.push('fotos que no se generaron tras ' + veces + ': ' + nombresLaminas(missing));
if (remotas.length) avisos.push('fotos que no se pudieron guardar junto al plan (quedaron enlazadas): ' + nombresLaminas(remotas));
if (Array.isArray(v.sin_foto) && v.sin_foto.length) {
  avisos.push('la propuesta no tiene foto guardada para ' + nombresLaminas(v.sin_foto) + ': esas láminas van sin foto');
}
const ok = problemas.length === 0;
const resumen = [];
if (ok && modo === 'final') {
  resumen.push('Presentación y PDF generados');
  resumen.push((pedidas - missing.length) + ' de ' + pedidas + ' fotos nuevas guardadas junto al plan');
  if (Array.isArray(v.reutilizadas) && v.reutilizadas.length) resumen.push('Con las fotos de la propuesta, sin costo: ' + nombresLaminas(v.reutilizadas));
  resumen.push(intento + ' ' + (intento === 1 ? 'render' : 'renders'));
} else if (ok) {
  resumen.push('Vista previa generada (sin fotos)');
}
const extra = avisos.length ? ' · ' + avisos.join(' · ') : '';
const nota = ok ? (modo === 'final' ? 'Versión final verificada: ' : 'Vista previa lista: ') + resumen.join(' · ') + extra
  : problemas.join(' · ') + extra + ' (ejecución ' + exec + ')';
return [{ json: Object.assign(vacio, { ok, view_url: lr.view_url, pdf_url: lr.pdf_url,
  faltan: modo === 'final' ? missing : [], problemas, avisos, resumen, nota, crm: v.crm || 0, proyecto: v.proyecto || vacio.proyecto,
  renders: intento }) }];"""


def node(id_, name, type_, version, pos, params, **extra):
    n = {'id': id_, 'name': name, 'type': type_, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


def http(id_, name, pos, method, url, body_expr=None, cred=True, timeout=30000):
    params = {'method': method, 'url': url, 'options': dict(FULL, timeout=timeout)}
    if cred:
        params.update({'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth'})
    if body_expr:
        params.update({'sendBody': True, 'specifyBody': 'json', 'jsonBody': body_expr})
    extra = {'credentials': CRED_WP} if cred else {}
    # neverError no cubre timeouts ni conexiones cortadas: con continueRegularOutput pasa {error} sin statusCode y
    # el flujo sigue hasta POST /vista (el plan nunca queda trabado en «aprobando»).
    return node(id_, name, 'n8n-nodes-base.httpRequest', 4.2, pos, params, onError='continueRegularOutput', **extra)


def iff(id_, name, pos, left):
    return node(id_, name, 'n8n-nodes-base.if', 2.2, pos, {
        'conditions': {'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'loose', 'version': 2},
                       'conditions': [{'id': id_ + '-c', 'leftValue': left, 'rightValue': True,
                                       'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
                       'combinator': 'and'},
        'options': {}})


nodes = [
    node('r1', 'Webhook', 'n8n-nodes-base.webhook', 2, [0, 0],
         {'httpMethod': 'POST', 'path': 'plan-v1-render', 'authentication': 'headerAuth',
          'responseMode': 'onReceived', 'options': {}},
         webhookId='plan-v1-render', credentials=CRED_WP),
    # La base de WP ya trae «?rest_route=», así que el modo va con «&».
    http('r2', 'Leer render', [220, 0], 'GET',
         f"={WP}/plan/{{{{ $json.body.id }}}}/render&modo={{{{ $json.body.modo === 'final' ? 'final' : 'draft' }}}}"),
    iff('r3', '¿Render leído?', [440, 0],
        "={{ $json.statusCode === 200 && !!$json.body && $json.body.ok === true && !!$json.body.render"
        " && typeof $json.body.render === 'object' }}"),
    # D10: una vista previa con el plan ya «aprobando», «listo» o «enviado» pisaría en /p/<codigo>/ la versión final.
    # Falso termina aquí: sin renderer, sin /vista y sin correo (el webhook ya respondió al recibir).
    iff('r15', '¿Toca renderizar?', [660, 0],
        "={{ $('Webhook').first().json.body.modo === 'final' || !" + json.dumps(['aprobando', 'listo', 'enviado'])
        + ".includes(String($json.body.estado)) }}"),
    iff('r4', '¿Reutilizar fotos?', [880, -120],
        "={{ $('Webhook').first().json.body.modo === 'final' && String($json.body.propuesta_uid || '').length >= 6 }}"),
    # Nunca falla: sin manifest (404) o con error, «Preparar render» deja portada y cierre sin foto y lo avisa.
    http('r5', 'Fotos de la propuesta', [1100, -240], 'GET',
         "={{ '" + RENDERER_BASE + "/p/' + encodeURIComponent($json.body.propuesta_uid) + '/img/manifest.json' }}", cred=False),
    node('r6', 'Preparar render', 'n8n-nodes-base.code', 2, [1320, -120], {'jsCode': LIB + JS_AVISO + CODE_PREPARAR}),
    # Respuesta completa (código HTTP y cuerpo) para distinguir un 400 del esquema, que no se reintenta, de un 5xx (D11).
    node('r7', 'Render', 'n8n-nodes-base.httpRequest', 4.2, [1540, -120],
         {'method': 'POST', 'url': RENDERER, 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
          'sendBody': True, 'specifyBody': 'json', 'jsonBody': '={{ JSON.stringify($json.render) }}',
          'options': dict(FULL, timeout=290000)},
         onError='continueRegularOutput', credentials=CRED_RENDER),
    node('r8', '¿Reintentar render?', 'n8n-nodes-base.code', 2, [1760, -240], {'jsCode': LIB + CODE_REINTENTAR}),
    iff('r9', '¿Faltan fotos?', [1980, -240], '={{ $json.reintentar === true }}'),
    node('r10', 'Resultado del render', 'n8n-nodes-base.code', 2, [2200, 0], {'jsCode': LIB + JS_AVISO + CODE_RESULTADO}),
    http('r11', 'Guardar vista', [2420, 0], 'POST', f"={WP}/plan/{{{{ $json.id }}}}/vista",
         "={{ JSON.stringify({ modo: $json.modo, ok: $json.ok, view_url: $json.view_url, pdf_url: $json.pdf_url,"
         " faltan: $json.faltan, nota: $json.nota }) }}"),
    node('r12', 'Armar correo', 'n8n-nodes-base.code', 2, [2640, 0], {'jsCode': correo_render()}),
    iff('r13', '¿Avisar a Luis?', [2860, 0], '={{ $json.enviar === true }}'),
    node('r14', 'Correo a Luis', 'n8n-nodes-base.emailSend', 1, [3080, -120],
         {'fromEmail': 'contacto@automatizatech.cl', 'toEmail': LUIS,
          'subject': '={{ $json.asunto }}', 'html': '={{ $json.html }}', 'options': {}},
         credentials=CRED_SMTP),
]

connections = {}


def link(a, b, output=0):
    connections.setdefault(a, {'main': []})
    while len(connections[a]['main']) <= output:
        connections[a]['main'].append([])
    connections[a]['main'][output].append({'node': b, 'type': 'main', 'index': 0})


link('Webhook', 'Leer render')
link('Leer render', '¿Render leído?')
link('¿Render leído?', '¿Toca renderizar?', 0)
link('¿Render leído?', 'Resultado del render', 1)
# ¿Toca renderizar? falso (vista previa atrasada, D10): termina aquí.
link('¿Toca renderizar?', '¿Reutilizar fotos?', 0)
link('¿Reutilizar fotos?', 'Fotos de la propuesta', 0)
link('¿Reutilizar fotos?', 'Preparar render', 1)
link('Fotos de la propuesta', 'Preparar render')
link('Preparar render', 'Render')
link('Render', '¿Reintentar render?')
link('¿Reintentar render?', '¿Faltan fotos?')
link('¿Faltan fotos?', 'Render', 0)
link('¿Faltan fotos?', 'Resultado del render', 1)
link('Resultado del render', 'Guardar vista')
link('Guardar vista', 'Armar correo')
link('Armar correo', '¿Avisar a Luis?')
link('¿Avisar a Luis?', 'Correo a Luis', 0)

wf = {'name': 'Plan de trabajo · 3 Render', 'nodes': nodes, 'connections': connections,
      # Si el flujo se cae sin llegar a su propio aviso, «Propuestas v3 · 0 Avisar error» le escribe a Luis.
      'settings': {'executionOrder': 'v1', 'errorWorkflow': 'm7TOfKznVSBGz4Nd'}}
out = os.path.join(AQUI, 'plan-3-render.json')
with open(out, 'w', encoding='utf-8') as fh:
    json.dump(wf, fh, ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
```

- [ ] **Step 14: Construir el flujo y correr toda la prueba**

```bash
python N8N/plan-trabajo/build_plan_3_render.py
```
```bash
SAL=$(python N8N/plan-trabajo/probar_plan.py 2>&1); echo "exit=$?"
echo "$SAL" | grep -c '^ok '; echo "$SAL" | grep -c '^FALLA'; echo "$SAL" | tail -1
```
```bash
python N8N/plan-trabajo/probar_plan.py nada; echo "exit=$?"
```
Expected: `escrito …plan-3-render.json 15 nodos`, `exit=0`, `341`, `0`, `TODO OK` (61 de la sección `render`: R1 vista previa sin fotos,
`&modo=draft` y botón a la ficha; R4 portada y cierre desde la propuesta y solo 8 fotos pagadas; R5 tres renders con el
mismo cuerpo y `POST /vista` final con `ok: false` y `faltan: ['gantt']`; R7 manifest 404 → listo con aviso; R8 sin
propuesta → 10 fotos; R9 WordPress no entrega el cuerpo → `/vista` final sin ok; R10 `/vista` falla → el correo sugiere
«Destrabar»; R11 renderer caído → 3 intentos; R12 modo desconocido → vista previa; R13 vista previa con el plan
«aprobando», «listo» o «enviado» → no toca el renderer ni escribe; R14 un 400 del esquema → un solo render y sus
`details` en la nota; R15 un 502 sí se reintenta). La última orden responde
`secciones desconocidas: nada — hay: puras, correos, borrador, cambios, render_puras, correos_render, render` y
`exit=2`. La suite completa tarda cerca de un minuto y medio (82 s medidos el 29-sep): cada caso arranca `node`.

- [ ] **Step 15: Commit y comprobar que los JSON commiteados son los que generan los builders**

```bash
git add N8N/plan-trabajo/build_plan_3_render.py N8N/plan-trabajo/plan-3-render.json N8N/plan-trabajo/probar_plan.py
git commit -m "feat(plan-n8n): flujo «Plan de trabajo · 3 Render» con reintentos, vista en WordPress y aviso a Luis" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
python N8N/plan-trabajo/build_plan_1_borrador.py > /dev/null && python N8N/plan-trabajo/build_plan_2_cambios.py > /dev/null && python N8N/plan-trabajo/build_plan_3_render.py > /dev/null && git diff --exit-code -- N8N/plan-trabajo/ && echo "JSON commiteados = builders"
```
Expected: la última línea es `JSON commiteados = builders`. `git log --oneline` muestra los 7 commits de las Tasks 13
y 14, y después de cada uno `python N8N/plan-trabajo/probar_plan.py` termina en `TODO OK`.

Nota para la Task 16 (no se ejecuta aquí: desplegar lo autoriza Luis). `deploy.py` une su propia carpeta con el
argumento (`os.path.join(os.path.dirname(__file__), sys.argv[1])`), así que sirven la ruta relativa a su carpeta
(`python N8N/propuestas-v3/deploy.py ../plan-trabajo/plan-3-render.json`) y la absoluta: desde Git Bash,
`"$(pwd)/N8N/plan-trabajo/plan-3-render.json"` llega a Python como `C:/…` (conversión de rutas de MSYS, probado el
29-sep). Orden obligatorio (decisión D17): primero `plan-3-render.json` y después `plan-1-borrador.json` y
`plan-2-cambios.json`: WordPress llama a «3 Render» apenas guarda un borrador (si no existe, el borrador queda guardado
sin vista previa y «1 Borrador» le escribe a Luis).


### Task 15: Prueba de punta a punta en local (WordPress + renderer + simulador de n8n)

Objetivo: recorrer el ciclo completo sin tocar PROD ni gastar créditos: firma → borrador → vista previa → editar y
recalcular → pedir cambios → aprobar → versión final; más los casos 4 y 5 del Review Focus. El simulador hace las
mismas llamadas HTTP que los tres flujos n8n, con un plan fijo en vez de GPT-4o y una foto local en vez de Higgsfield.
Nada de esto va al repo: vive en `<SCR>/plan-trabajo/e2e/`.

**Files (fuera del repo):**
- Create: `<SCR>/plan-trabajo/e2e/simulador_n8n.py`, `plan-fijo.json`, `datos.php`, `estado.php`, `limpiar.php`,
  `renderer-local.cmd`
- Modify: `C:\wamp64\www\automatiza-tech\.claude\launch.json` (entradas locales; no se commitea)

**Interfaces:**
- Consumes: todo lo de las Tasks 1-14 (rutas REST de la Task 7, acciones del panel de la Task 9, `renderPlanHtml` de la
  Task 12, cuerpos de los flujos de las Tasks 13-14), el sitio `wp-local-plan` y el worktree del renderer (Task 0).
- Produces: evidencia (capturas y salidas) para el informe a Luis. Ningún cambio de código salvo arreglos que salgan
  de aquí, cada uno con su prueba en la tarea dueña.

- [ ] **Step 1: Servidor local del renderer**

`<SCR>/plan-trabajo/e2e/renderer-local.cmd`:
```bat
@echo off
set PORT=5202
set PUBLIC_DIR=C:\Users\luis_\AppData\Local\Temp\claude\C--wamp64-www-automatiza-tech\be929449-8523-4c30-a49a-56a73bb785ab\scratchpad\plan-trabajo\render-public
set BASE_URL=http://localhost:5202
set RENDER_KEY=
node C:\wamp64\www\automatiza-tech\.worktrees\plan-renderer\renderer\src\index.js
```
Y una foto de relleno para la versión final (se sirve desde el propio renderer):
```bash
mkdir -p "$SCR/plan-trabajo/render-public/fixture"
python -c "import sys; from PIL import Image; Image.new('RGB',(1280,720),(30,60,90)).save(sys.argv[1], quality=85)" "$SCR/plan-trabajo/render-public/fixture/foto.jpg"
```
La entrada `renderer-plan-local` de `launch.json` ya la agregó la Task 0, Step 6 (apunta a este `.cmd`).

- [ ] **Step 2: Plan fijo que reemplaza a GPT-4o**

`<SCR>/plan-trabajo/e2e/plan-fijo.json` (forma de salida de la IA según el esqueleto; sin fechas, sin origen):
```json
{
  "proyecto": "[PRUEBA] Sitio y asistente",
  "fases": [
    {"clave": "diseno_desarrollo", "descripcion": "Diseñamos y construimos el sitio y el asistente.",
     "bloques": [
       {"nombre": "Diseño", "entrega": true, "entregable": "Maqueta del sitio aprobada",
        "actividades": [
          {"nombre": "Maqueta de la portada", "detalle": "Estructura y estilo", "responsable": "at", "dias_habiles": 2, "servicio": "sitio_web_tienda", "etapa": "diseno"},
          {"nombre": "Maqueta de las páginas internas", "detalle": "", "responsable": "at", "dias_habiles": 2, "servicio": "sitio_web_tienda", "etapa": "diseno"}]},
       {"nombre": "Desarrollo", "entrega": false, "entregable": "Sitio funcionando en pruebas",
        "actividades": [
          {"nombre": "Construcción del sitio", "detalle": "", "responsable": "at", "dias_habiles": 8, "servicio": "sitio_web_tienda", "etapa": "desarrollo"},
          {"nombre": "Asistente con preguntas frecuentes", "detalle": "", "responsable": "at", "dias_habiles": 3, "servicio": "asistente_basico", "etapa": "desarrollo", "en_paralelo": true}]},
       {"nombre": "Pruebas y revisión", "entrega": true, "entregable": "Versión lista para publicar",
        "actividades": [
          {"nombre": "Pruebas en celular y computador", "detalle": "", "responsable": "at", "dias_habiles": 3, "servicio": "sitio_web_tienda", "etapa": "pruebas"}]}]},
    {"clave": "implementacion", "descripcion": "Publicamos y te capacitamos.",
     "bloques": [
       {"nombre": "Puesta en marcha", "entrega": false, "entregable": "Sitio publicado",
        "actividades": [
          {"nombre": "Publicación en tu dominio", "detalle": "", "responsable": "at", "dias_habiles": 2, "servicio": "sitio_web_tienda", "etapa": "implementacion"},
          {"nombre": "Capacitación", "detalle": "", "responsable": "ambos", "dias_habiles": 1, "servicio": "", "etapa": ""}]}]},
    {"clave": "soporte", "descripcion": "Acompañamiento después de la entrega.",
     "bloques": [
       {"nombre": "Acompañamiento", "entrega": false, "entregable": "",
        "actividades": [
          {"nombre": "Ajustes de la primera semana", "detalle": "", "responsable": "at", "dias_habiles": 5, "servicio": "", "etapa": "soporte"}]}]}
  ],
  "hitos": [{"nombre": "Diseño aprobado", "despues_de": "Diseño"}, {"nombre": "Sitio publicado", "despues_de": "Puesta en marcha"}],
  "necesitamos_de_ti": ["Logo y colores", "Textos de la empresa", "Acceso al dominio"],
  "reuniones": [{"nombre": "Reunión de inicio", "detalle": "Revisamos el plan juntos"}, {"nombre": "Entrega y capacitación", "detalle": ""}],
  "soporte": {"garantia_meses": 3, "mensuales": []},
  "image_briefs": [
    {"slide": "metodo", "prompt": "team planning on a table, hands and notes, no text"},
    {"slide": "gantt", "prompt": "calendar on a wooden desk, soft light, no text"},
    {"slide": "fase_1", "prompt": "designer sketching, hands, no text"},
    {"slide": "fase_2", "prompt": "person smiling at a laptop screen facing away, no text"},
    {"slide": "fase_3", "prompt": "handshake at a small shop counter, no text"},
    {"slide": "necesitamos", "prompt": "folder with colorful papers, no text"},
    {"slide": "reuniones", "prompt": "two people in a video call, screen facing away, no text"},
    {"slide": "portal", "prompt": "person checking a phone, screen facing away, no text"},
    {"slide": "cover", "prompt": "close up of hands on a notebook, blurred background, no text"},
    {"slide": "cierre", "prompt": "team celebrating, no text"}
  ]
}
```

- [ ] **Step 3: Simulador de n8n**

`<SCR>/plan-trabajo/e2e/simulador_n8n.py` (responde 200 al instante y trabaja en un hilo, como `responseMode:
onReceived`; lee la clave local de REST sin imprimirla):
```python
"""Simulador local de los flujos «Plan de trabajo · 1 Borrador / 2 Cambios / 3 Render» (solo pruebas locales).
Uso: python simulador_n8n.py   (escucha en localhost:5203)"""
import json, os, subprocess, sys, threading, time, urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

sys.stdout.reconfigure(encoding='utf-8')
AQUI = os.path.dirname(os.path.abspath(__file__))
SCR = os.path.dirname(os.path.dirname(AQUI))
SITIO = os.path.join(SCR, 'wp-local-plan')
PHP = r'C:\wamp64\bin\php\php8.4.15\php.exe'
WP = 'http://localhost:8093/?rest_route=/automatiza-tech/v1'
RENDER = 'http://localhost:5202/render'
FOTO = 'http://localhost:5202/p/fixture/foto.jpg'
PLAN_FIJO = json.load(open(os.path.join(AQUI, 'plan-fijo.json'), encoding='utf-8'))
LOG = os.path.join(AQUI, 'simulador.log')


def secreto():
    cod = ("$_SERVER['HTTP_HOST']='localhost:8093';$_SERVER['REQUEST_URI']='/';define('WP_USE_THEMES',false);"
           "require getenv('SITIO').'/wp-load.php';echo AT_REST_SECRET;")
    r = subprocess.run([PHP, '-r', cod], capture_output=True, text=True, env=dict(os.environ, SITIO=SITIO))
    return r.stdout.strip()


CLAVE = secreto()


def log(msg):
    with open(LOG, 'a', encoding='utf-8') as f:
        f.write(time.strftime('%H:%M:%S ') + msg + '\n')


def http(metodo, url, cuerpo=None, clave=True):
    datos = json.dumps(cuerpo).encode('utf-8') if cuerpo is not None else None
    cab = {'Content-Type': 'application/json'}
    if clave:
        cab['X-AT-Secret'] = CLAVE
    req = urllib.request.Request(url, data=datos, method=metodo, headers=cab)
    try:
        with urllib.request.urlopen(req, timeout=300) as r:
            return r.status, json.loads(r.read() or b'{}')
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read() or b'{}')


def borrador(pid, origen):
    s, ctx = http('GET', f'{WP}/plan/{pid}/contexto')
    log(f'contexto {pid}: HTTP {s}')
    if origen == 'cambios':
        plan = ctx.get('plan_actual') or {}
        if plan.get('fases'):
            plan['fases'][0]['descripcion'] = plan['fases'][0].get('descripcion', '') + ' (revisado)'
    else:
        plan = json.loads(json.dumps(PLAN_FIJO))
    s, r = http('POST', f'{WP}/plan/{pid}/borrador', {'plan': plan, 'origen': origen})
    log(f'borrador {pid} ({origen}): HTTP {s} {json.dumps(r, ensure_ascii=False)[:300]}')


def render(pid, modo):
    s, r = http('GET', f'{WP}/plan/{pid}/render&modo={modo}')
    log(f'render-cuerpo {pid} {modo}: HTTP {s}')
    cuerpo = r.get('render') or {}
    if modo == 'draft' and r.get('estado') in ('aprobando', 'listo', 'enviado'):
        log(f'render {pid} draft omitido: el plan está en {r.get("estado")} (D10)')
        return
    if modo == 'final':
        cuerpo['images'] = {b['slide']: FOTO for b in cuerpo.get('image_briefs', [])}
    s, v = http('POST', RENDER, cuerpo, clave=False)
    faltan = (v.get('images') or {}).get('missing', []) if isinstance(v, dict) else []
    ok = s == 200 and bool(v.get('view_url')) and not faltan
    s2, _ = http('POST', f'{WP}/plan/{pid}/vista', {'modo': modo, 'ok': ok, 'view_url': v.get('view_url', ''),
                                                   'pdf_url': v.get('pdf_url', ''), 'faltan': faltan,
                                                   'nota': '' if ok else f'renderer HTTP {s}'})
    log(f'render {pid} {modo}: renderer HTTP {s}, ok={ok}, vista HTTP {s2}')


class Manejador(BaseHTTPRequestHandler):
    def do_POST(self):
        largo = int(self.headers.get('Content-Length') or 0)
        cuerpo = json.loads(self.rfile.read(largo) or b'{}')
        ruta = self.path.strip('/')
        ok_clave = self.headers.get('X-AT-Secret') == CLAVE
        self.send_response(200 if ok_clave else 403)
        self.end_headers()
        self.wfile.write(b'{"ok":true}')
        if not ok_clave:
            log(f'{ruta}: clave incorrecta'); return
        pid = int(cuerpo.get('id') or 0)
        log(f'webhook {ruta} {json.dumps(cuerpo)}')
        if ruta == 'plan-v1-borrador':
            threading.Thread(target=borrador, args=(pid, 'borrador')).start()
        elif ruta == 'plan-v1-cambios':
            threading.Thread(target=borrador, args=(pid, 'cambios')).start()
        elif ruta == 'plan-v1-render':
            threading.Thread(target=render, args=(pid, cuerpo.get('modo', 'draft'))).start()

    def log_message(self, *a):
        pass


if __name__ == '__main__':
    assert CLAVE, 'no se pudo leer AT_REST_SECRET del sitio local'
    print('simulador n8n en http://localhost:5203')
    ThreadingHTTPServer(('localhost', 5203), Manejador).serve_forever()
```
Entrada en `launch.json`: `{"name": "simulador-n8n-plan", "runtimeExecutable": "python", "runtimeArgs":
["<SCR>/plan-trabajo/e2e/simulador_n8n.py"], "port": 5203}`.

- [ ] **Step 4: Datos de prueba y firma simulada**

`<SCR>/plan-trabajo/e2e/datos.php` (base LOCAL; crea cliente del CRM, ficha operativa, propuesta opcional y contrato de
servicios firmado; dispara el hook como lo haría `sign_as_client`):
```php
<?php
// Uso: AT_WP_LOAD=<sitio>/wp-load.php php datos.php [con_propuesta|sin_propuesta] [con_correo|sin_correo]
$_SERVER['HTTP_HOST'] = 'localhost:8093'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
define('WP_USE_THEMES', false);
require getenv('AT_WP_LOAD');
global $wpdb;
$con_propuesta = ($argv[1] ?? 'con_propuesta') === 'con_propuesta';
$con_correo = ($argv[2] ?? 'con_correo') === 'con_correo';
$marca = 'e2e-' . substr(md5(microtime(true)), 0, 6);
$correo = $con_correo ? "prueba-plan-{$marca}@example.com" : '';
$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => '[PRUEBA] Cliente Plan ' . $marca, 'email' => $correo, 'tipo' => 'cliente']);
$crm = (int) $wpdb->insert_id;
$wpdb->insert($wpdb->prefix . 'automatiza_tech_clients', ['name' => 'Cliente Prueba', 'email' => $correo ?: "sin-correo-{$marca}@example.com", 'company' => '[PRUEBA] Empresa ' . $marca, 'crm_cliente_id' => $crm]);
$tech = (int) $wpdb->insert_id;
$prop = null;
if ($con_propuesta) {
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', ['client_email' => $correo ?: "prop-{$marca}@example.com", 'unique_link_id' => substr(md5($marca), 0, 12), 'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Empresa ' . $marca, 'status' => 'aceptada', 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode(['solution_text' => 'Sitio con asistente', 'how_it_works' => [['step_title' => 'Diseño', 'step_text' => 'maqueta']]]), 'transcript_text' => 'Reunión de prueba: quieren un sitio con asistente.', 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
	$prop = (int) $wpdb->insert_id;
}
$ph = ['servicios_contratados' => "- **Sitio web**: \$1\n- **Asistente básico**: \$1", 'alcance' => 'Sitio web y asistente básico.', 'entregables' => "- Sitio publicado\n- Asistente", 'plazo' => 'Se define en la reunión de inicio.', 'fases_siguientes' => 'La propuesta no tiene fases siguientes.', 'nombre_proyecto' => '[PRUEBA] Sitio y asistente', 'razon_social_cliente' => 'Cliente Prueba', 'email_cliente' => $correo];
$wpdb->insert($wpdb->prefix . 'automatiza_contracts', ['client_id' => $tech, 'proposal_id' => $prop, 'contract_number' => 'PRUEBA-' . $marca, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => wp_json_encode($ph, JSON_UNESCAPED_UNICODE), 'status' => 'signed', 'signed_at' => current_time('mysql'), 'sign_token' => bin2hex(random_bytes(32))]);
$cid = (int) $wpdb->insert_id;
$contrato = ContractService::get_by_id($cid);
do_action('at_contrato_firmado', $contrato);
do_action('at_contrato_firmado', $contrato); // firma repetida: debe seguir habiendo un solo plan
$plan = at_pt_plan_de_contrato($cid);
echo wp_json_encode(['marca' => $marca, 'crm' => $crm, 'tech' => $tech, 'propuesta' => $prop, 'contrato' => $cid, 'plan' => $plan ? (int) $plan->id : 0, 'estado' => $plan->estado ?? '', 'planes_del_contrato' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . at_pt_tabla() . ' WHERE contrato_id = %d', $cid))]), "\n";
```
Nota: si alguna columna de `crm_clientes` o `automatiza_tech_clients` no calza con el esquema local, ajustar
leyendo `SHOW COLUMNS` antes de insertar (no suponer).

`<SCR>/plan-trabajo/e2e/estado.php`:
```php
<?php
$_SERVER['HTTP_HOST'] = 'localhost:8093'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
define('WP_USE_THEMES', false);
require getenv('AT_WP_LOAD');
$p = at_pt_plan((int) ($argv[1] ?? 0));
if (!$p) { echo "no existe\n"; exit(1); }
$pl = at_pt_payload($p);
$origenes = [];
foreach ($pl['fases'] ?? [] as $f) foreach ($f['bloques'] ?? [] as $b) foreach ($b['actividades'] ?? [] as $a) $origenes[] = $a['nombre'] . '=' . $a['dias_habiles'] . '/' . $a['origen'];
echo wp_json_encode(['estado' => $p->estado, 'nota' => $p->nota, 'view_url' => $p->view_url, 'inicio' => $pl['cronograma']['inicio'] ?? '', 'fin' => $pl['cronograma']['fin'] ?? '', 'semanas' => $pl['cronograma']['semanas'] ?? 0, 'actividades' => $origenes, 'desc_fase_1' => $pl['fases'][0]['descripcion'] ?? ''], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
```

`<SCR>/plan-trabajo/e2e/limpiar.php` (borra todo lo `[PRUEBA]` de esta prueba):
```php
<?php
$_SERVER['HTTP_HOST'] = 'localhost:8093'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
define('WP_USE_THEMES', false);
require getenv('AT_WP_LOAD');
global $wpdb;
$contratos = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}automatiza_contracts WHERE contract_number LIKE 'PRUEBA-e2e-%'");
foreach ($contratos as $c) { $wpdb->delete(at_pt_tabla(), ['contrato_id' => (int) $c]); $wpdb->delete($wpdb->prefix . 'automatiza_contracts', ['id' => (int) $c]); }
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE company_name LIKE '[PRUEBA] Empresa e2e-%'");
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE company LIKE '[PRUEBA] Empresa e2e-%'");
$wpdb->query("DELETE FROM {$wpdb->prefix}crm_clientes WHERE nombre LIKE '[PRUEBA] Cliente Plan e2e-%'");
echo count($contratos), " contratos de prueba borrados\n";
```

- [ ] **Step 5: Ciclo feliz con propuesta y correo**

Levantar `wp-local-plan`, `renderer-plan-local` y `simulador-n8n-plan` con `preview_start` (nunca con Bash). Luego:
```bash
cd "$SCR/plan-trabajo/e2e"; export AT_WP_LOAD="$SCR/wp-local-plan/wp-load.php"; PHP=/c/wamp64/bin/php/php8.4.15/php.exe
"$PHP" datos.php con_propuesta con_correo
```
Expected: JSON con `plan > 0`, `estado: "generando"` (o `"borrador"` si el simulador ya alcanzó a responder) y
`planes_del_contrato: 1` (la firma repetida no duplica).
Esperar ~10 s y:
```bash
"$PHP" estado.php <plan>
tail -20 simulador.log
```
Expected: `estado: "borrador"`, `view_url` de `localhost:5202`, `inicio` = lunes hábil siguiente a hoy, actividades con
`origen` `tabla` (las de la tabla, con los días repartidos: diseño del sitio 5 días entre dos actividades → 3 y 2) y
`ia` (Capacitación, Ajustes de la primera semana); el log muestra contexto 200, borrador 200, render draft y vista 200.
`wp-local-plan/http-bloqueado.log` no debe tener líneas nuevas (nada salió a internet).

- [ ] **Step 6: Panel — ver, editar, recalcular, pedir cambios y aprobar (navegador)**

1. Abrir `http://localhost:8093/wp-admin/?at_admin=1` y luego
   `http://localhost:8093/wp-admin/admin.php?page=automatiza-crm-ficha&id=<crm>&pt=<plan>#tab-plan`.
2. Comprobar: pestaña «🗓️ Plan de trabajo» visible; estado «Borrador»; enlace a la vista previa; tabla con las
   actividades, su origen y sus fechas; «✅ Aprobar y generar versión final (8 fotos ≈ US$0,0256)» (con propuesta: cover y cierre no cuentan).
3. Cambiar «Construcción del sitio» de sus días a 12 y la fecha de inicio a un sábado; «Guardar y recalcular».
   `estado.php` debe mostrar esa actividad `12/luis`, el inicio movido al lunes hábil siguiente y una vista nueva.
4. «Pedir cambios» con un comentario. Tras ~10 s: estado `borrador`, `desc_fase_1` termina en «(revisado)» y la
   actividad sigue `12/luis` (Review Focus 3).
5. «Aprobar» → aceptar el `confirm` → tras ~15 s estado `listo` y `view_url` final.
6. Abrir la vista final en 1920×1080 y en 390 px de ancho: capturas de las 10 láminas; revisar portada, Método AT
   con «Estás aquí», carta Gantt (barras, franjas «Tu revisión», rombos de hitos, leyenda), láminas de fase,
   «Qué necesitamos de ti», reuniones, «Sigue tu proyecto» sin botón (en local el portal es
   `http://` y el renderer solo enlaza `https`, Task 12; el enlace se verifica en PROD en la Task 16, Step 6) y cierre con el enlace de WhatsApp
   (y sin enlace web: Etapa 1). Nada cortado; el PDF (`presentation.pdf`) abre y sus enlaces se pueden tocar.
7. «Agendar llamada de seguimiento» abre `admin.php?page=automatiza-followup&pt_plan=<plan>` con nombre, correo,
   empresa y asunto «Seguimiento del plan de trabajo — …» precargados. No enviar el formulario.

- [ ] **Step 7: Sin propuesta y sin correo (Review Focus 4)**

```bash
"$PHP" datos.php sin_propuesta sin_correo
sleep 10; "$PHP" estado.php <plan2>
```
Expected: estado `borrador`. En la pestaña: «✅ Aprobar y generar versión final (10 fotos ≈ US$0,032)» (portada y cierre también se generan). La vista
muestra «Sigue tu proyecto» sin enlace y sin error.

- [ ] **Step 8: n8n caído al firmar (Review Focus 5)**

Detener `simulador-n8n-plan` (`preview_stop`), correr `"$PHP" datos.php con_propuesta con_correo`: el JSON sale igual
(la firma no falla) y `estado.php` muestra `error` con un motivo legible («n8n …»). Levantar el simulador y usar
«Reintentar borrador» en el panel: pasa a `borrador`.

- [ ] **Step 9: Limpiar**

```bash
"$PHP" limpiar.php
rm -rf "$SCR/plan-trabajo/render-public"/[A-Za-z0-9]*
```
Detener los tres servidores. Guardar capturas y salidas en `<SCR>/plan-trabajo/e2e/evidencia/` para el informe.

### Task 16: Despliegue con autorización de Luis, prueba real y documentación

Cada paso con efecto en PROD, Easypanel o n8n se hace **solo con el ok explícito de Luis para ese paso**. Nada le llega
a un cliente en la Etapa 1.

**Files:**
- Create: `Docs/METODO_AT/PLAN-DE-TRABAJO.md` (guía para agentes, sin datos de clientes)
- Modify: `Docs/METODO_AT/README.md` (índice), `CLAUDE.md` y `AGENTS.md` (puntero), memoria
  `project_at_plan_de_trabajo.md`, bóveda `10-Projects/` (nota del despliegue con respaldos)

**Interfaces:**
- Consumes: las ramas `claude/plan-de-trabajo` y `claude/plan-renderer` con todo en verde y la Task 15 aprobada.
- Produces: la Etapa 1 en PROD, verificada.

- [ ] **Step 1: Subir las ramas y abrir los PR** (ok de Luis)

`claude/plan-renderer` → `main`; `claude/plan-de-trabajo` → `claude/cierre-cliente` (apilado, como el #54 y el #56).
La rama del plan trae el merge del PR #51, que todavía no está en `claude/cierre-cliente` ni en `main`: el diff del PR
mostraría sus archivos (unos 23, incluidos `CumpleBooth/…`). Lo más limpio es que Luis mergee antes el #51; si no, se
dice en la descripción del PR.
Antes de cada merge futuro, seguir `30-Agent-Protocols/Merge-con-GitHub-Actions-bloqueado.md` de la bóveda (comentario en
el PR antes de mergear mientras Actions siga bloqueado).

- [ ] **Step 2: Renderer** (Luis sube el zip)

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-renderer
git archive --format=zip -o /c/Users/luis_/Downloads/propuesta-renderer-PLAN-$(git rev-parse --short HEAD).zip HEAD:renderer
```
Luis lo sube a Easypanel. Verificar `GET /health` → 200 y que una propuesta ya publicada sigue abriendo igual.

- [ ] **Step 3: Flujos n8n** (ok de Luis; van antes que WordPress: si alguien firma entre medio, el webhook ya existe)

```bash
cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo
python N8N/plan-trabajo/build_plan_1_borrador.py && python N8N/plan-trabajo/build_plan_2_cambios.py && python N8N/plan-trabajo/build_plan_3_render.py
python N8N/plan-trabajo/probar_plan.py
python N8N/propuestas-v3/deploy.py "$(pwd)/N8N/plan-trabajo/plan-3-render.json"   # primero: WordPress lo llama apenas guarda un borrador
python N8N/propuestas-v3/deploy.py "$(pwd)/N8N/plan-trabajo/plan-1-borrador.json"
python N8N/propuestas-v3/deploy.py "$(pwd)/N8N/plan-trabajo/plan-2-cambios.json"
```
Expected: tres líneas «creado: Plan de trabajo · … activo=True». Son flujos nuevos: no pisan nada (no hay respaldo que
hacer); anotar sus ids en la memoria.

- [ ] **Step 4: WordPress en PROD por SSH** (ok de Luis)

1. Reconocer (solo lectura): md5 en PROD de `wp-content/themes/automatiza-tech/inc/admin-proposals.php`,
   `contracts/contract-service.php`, `wp-content/mu-plugins/crm-ai-completo.php` e
   `wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php`, comparados con la base de la rama
   (`git show claude/cierre-cliente:<ruta> | md5sum`). Si alguno difiere, parar y revisar la diferencia con Luis.
2. Respaldar esos cuatro archivos en `~/respaldos/plan-trabajo-antes-<fecha>.tar.gz`.
3. Subir a temporales, `php -l` en el servidor, y reemplazar: carpeta nueva `inc/plan-trabajo/` completa,
   `assets/css/plan-trabajo.css`, `assets/js/plan-trabajo.js` y los cuatro archivos modificados. Orden: primero la
   carpeta nueva y los assets, después `admin-proposals.php` (que la carga), después `contract-service.php`, el
   mu-plugin y `admin-followup-meetings.php`.
4. Migración: sonda de solo escritura mínima que llama `at_pt_migrar_esquema()` y muestra `SHOW CREATE TABLE`.
5. Verificar desde afuera: `GET /?rest_route=/automatiza-tech/v1/plan/1/contexto` sin clave → 401; el sitio y
   `wp-admin` responden; la ficha del CRM muestra la pestaña (Luis, con su sesión).
6. Rollback: `cd ~ && tar xzf respaldos/plan-trabajo-antes-<fecha>.tar.gz` y borrar `inc/plan-trabajo/` y los dos assets;
   la tabla nueva puede quedar (no la lee nadie más).

- [ ] **Step 5: Feriados** (solo lectura)

Sonda que cuenta las fechas de `automatiza_chat_schedule['holidays']` por año. Si 2026 y 2027 no están completos,
avisar a Luis para que los cargue en «Ajustes del chat» (no inventar la lista).

- [ ] **Step 6: Prueba real** (ok de Luis; gasta ~US$0,02 de GPT-4o por borrador y fotos solo si aprueba)

Antes del primer «Aprobar», recordarle a Luis la decisión D18: en la Etapa 1 las fotos nuevas salen con Soul 2 y **sin**
la revisión de texto con GPT-4o que tienen las propuestas; Soul 2 a veces imprime letras en prendas y envases. Si
prefiere, se suma esa revisión al flujo 3 antes de aprobar (tarea aparte).

Luis elige el contrato firmado para probar y usa «Crear plan de trabajo» en la ficha de ese cliente. Verificar el
borrador, la vista previa, editar y recalcular, «Pedir cambios» y, si Luis lo decide, «Aprobar» con el costo a la
vista. Nada se envía al cliente (el envío es la Etapa 2).

- [ ] **Step 7: Documentación y memoria**

`Docs/METODO_AT/PLAN-DE-TRABAJO.md`: qué es, estados, rutas, flujos n8n y sus ids, tabla de tiempos, cómo destrabar,
despliegue, respaldos y rollback (sin datos de clientes). Puntero en `Docs/METODO_AT/README.md`, `CLAUDE.md` y
`AGENTS.md`. Memoria y bóveda con fechas, respaldos, ids y pendientes (Etapa 2: envío al cliente, `ver-plan.php` y agenda
web; Etapa 3: plantilla Meta y Tech en el bot). Lista FTP para Luis según `CLAUDE.md` («Entrega obligatoria para FTP»).
