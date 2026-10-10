# Tablero AT v9: personas, permisos y tickets de agentes

Fecha: 2026-10-10 · Ticket: AT-TAB-004 · Autor: Claude Code (claude-opus-5-5) · Aprobado por Luis (Presidente)
por partes en el chat del 2026-10-10 (partes 1 a 5). Revisión B: Codex, acotada.

## 1. Objetivo

Que el tablero ágil (`automatizatech.cl/tablero/`) muestre **en qué está cada uno**: Luis, los colaboradores
humanos y los agentes (Claude, Codex, OpenCode). Que refleje solos los tickets de `Docs/ORCHESTRATION/`, que
muestre las decisiones que esperan a Luis y que permita sumar colaboradores con acceso propio.

Éxito:
- Luis ve en una vista quién tiene qué (en curso, en revisión y bloqueado) y qué espera su decisión.
- Un colaborador entra con su usuario de WordPress, ve todo y solo puede cambiar lo suyo.
- Los tickets de git aparecen en el tablero sin edición manual, sin crear una segunda verdad.

## 2. Estado de partida (verificado en `origin/main` 188ba45)

- `tablero/index.html` (1.514 líneas, Kanban de clientes con 6 pasos y de tareas internas, más métricas) y
  `api-tablero.php` (API 8.3.0: GET con todo y POST upsert por `at_id`). Se instalan con `setup-at-board.php`.
- Tablas: `{prefix}omnichannel_at_board` (clientes, **sin responsable**), `{prefix}omnichannel_at_internas`
  (con `asignado_a` en texto) y `{prefix}omnichannel_at_attachments`.
- Autenticación: un **token compartido** (`AT_BOARD_TOKEN`) en la API y Basic Auth en la carpeta `tablero/`.
  Responsables fijos en el código: `Luis`, `OpenCode Go`, `Claude`, `Codex` (`limpiarAsig`).
- En PROD (10-oct, solo códigos HTTP): `/tablero/` 401, `/api-tablero.php` 401 sin token,
  `/setup-at-board.php` 403. `tablero/CONTEXTO_TABLERO.md` está desactualizado («no deploy»).
- Los tickets viven en `Docs/ORCHESTRATION/AT-*.yaml` y no hay sincronización con el tablero.
  `Docs/ORCHESTRATION/README.md` §12 exige que el tablero sea un **espejo** de las fuentes canónicas y que
  muestre origen, versión y última sincronización.

## 3. Personas y permisos (parte 1, aprobada)

| Actor | Cómo existe | Ver | Crear | Editar o mover |
|---|---|---|---|---|
| Luis | Administrador de WordPress (`manage_options`) | Todo | Cualquier tarjeta de persona; asigna a cualquiera | Toda tarjeta de persona |
| Colaborador AT | Rol nuevo `colaborador_at` | Todo | Tarjetas internas asignadas a sí mismo | Solo tarjetas con `asignado` = él; no reasigna |
| Externo por proyecto | Capacidad `at_board_ver_proyecto` + meta de proyectos | Solo sus proyectos | — | — |
| Agentes | No son usuarios; responsables `agente:claude`, `agente:codex`, `agente:opencode` | — | — | — |
| Servicio `agentes-at` | Usuario de WordPress con contraseña de aplicación y capacidad `at_board_sync` | — | Solo tarjetas `origen = ticket` (vía sync) | Solo tarjetas `origen = ticket` |

- **Externo por proyecto:** definido en el código y apagado. Sin rol asignable, sin pantalla y sin usuarios
  hasta que Luis lo pida. Las pruebas lo cubren como «sin acceso».
- **Tarjetas `origen = ticket`:** de solo lectura en el tablero para todos, Luis incluido. Se cambian en git.
- **Capacidades:** `at_board_ver`, `at_board_editar_propias`, `at_board_admin` y `at_board_sync`. El rol
  `colaborador_at` tiene las dos primeras; el administrador, todas menos `at_board_sync`.
- **Entrada:** `tablero/index.php` carga WordPress. Sin sesión, redirige a `wp-login.php?redirect_to=…`; con
  sesión pero sin `at_board_ver`, responde 403 con un mensaje simple. Si todo está bien, entrega la interfaz.
- **API:** ruta REST de WordPress `at-tablero/v1` (se llama como `?rest_route=/at-tablero/v1/...`), cargada por un
  `mu-plugin` (`wp-content/mu-plugins/at-tablero.php`) para no tocar `functions.php`. WordPress resuelve la sesión
  con la cookie más el nonce `wp_rest` (cabecera `X-WP-Nonce`), y el servicio entra con la contraseña de aplicación
  (Basic), sin código de autenticación propio. Se retiran `AT_BOARD_TOKEN`, el CORS a localhost y el Basic Auth de
  la carpeta; `api-tablero.php` queda como aviso 410. *(Ajuste técnico del 2026-10-10, al escribir el plan:
  reemplaza la idea de autenticar dentro de `api-tablero.php`.)*
- **Autorización en el servidor, siempre.** La interfaz solo oculta acciones por comodidad.
- **Alta y baja de colaboradores:** WordPress → Usuarios → Añadir nuevo, rol «Colaborador AT». Quitar el rol
  revoca el acceso. Sin pantalla propia en esta versión.
- **Fuera de alcance:** la sincronización local↔PROD de la v8. El tablero se usa en PROD; la copia local sirve
  para desarrollo con su propia base.

## 4. Datos (parte 2)

Columnas nuevas en **ambas** tablas (migración idempotente, sin borrar columnas):

| Columna | Tipo | Uso |
|---|---|---|
| `asignado` | VARCHAR(60) | `wp:<user_id>` o `agente:<claude/codex/opencode>`; vacío = sin asignar |
| `origen` | VARCHAR(10) | `manual` o `ticket` |
| `ticket_id` | VARCHAR(40) NULL | p. ej. `AT-INT-PROP-001` |
| `ticket_ref` | VARCHAR(255) NULL | enlace al PR o a la ruta del YAML |
| `decide_luis` | TINYINT(1) | tarjeta ⚖️ |
| `archivada` | TINYINT(1) | tickets que desaparecen del repo; nunca se borran |
| `creado_por` y `actualizado_por` | BIGINT NULL | user_id de WordPress (NULL = servicio) |
| `sync_at` | DATETIME NULL | última sincronización de la tarjeta |

- **Migración de `asignado_a`:** `Luis` → `wp:<id del administrador>` (lo pasa el script de migración como
  parámetro, sin adivinarlo); `Claude` → `agente:claude`; `Codex` → `agente:codex`; `OpenCode Go` →
  `agente:opencode`. La columna vieja queda, pero la API deja de usarla.
- Las tarjetas de ticket se guardan en la tabla de **internas** con `at_id` = `T-<ticket_id>`, y las de
  decisión con `T-<ticket_id>-D<n>`.

## 5. Sincronización de tickets (parte 3)

`tools/tablero_sync.py` (Python 3 + PyYAML, ya instalados), corrido desde el checkout local:

1. **Fuentes:** `origin/main` y las ramas de PR abiertos (`gh pr list --json headRefName`). Lee con
   `git ls-tree` y `git show`, sin checkout. Por cada `ticket_id` usa la versión del commit más reciente.
2. **Mapeo de estado:** `READY`, `READY_FOR_EXECUTION` y `ASSIGNED` → `todo`; `IN_PROGRESS` → `progress`;
   `PEER_REVIEW_PENDING` y `CHANGES_REQUESTED*` → `review`; `AWAITING_HUMAN_GATE` y `WAITING_HUMAN_GATE` → `wait`
   (más tarjeta ⚖️); `BLOCKED` → `blocked`; `DONE` → `done`; cualquier otro → `backlog`, con el estado original
   en la nota.
3. **Campos:** título; `ownership.current_owner` → `asignado`; prioridad; revisor y `portfolio` en la nota;
   `ticket_ref` al PR si lo hay.
4. **Decisiones:** campo opcional nuevo en el ticket:
   `decisiones_luis: [{id: D1, pregunta: "...", estado: pendiente|resuelta}]`. Cada decisión pendiente se
   publica como tarjeta ⚖️ asignada a Luis, y cuando queda resuelta su tarjeta pasa a `done`.
5. **Archivado:** un ticket que ya no está en ninguna fuente → su tarjeta pasa a `archivada = 1`.
6. **Envío:** `POST ?rest_route=/at-tablero/v1/sync` con el lote completo; la API hace upsert por `at_id` y responde
   cuántas creó, actualizó y archivó. Credencial: usuario `agentes-at` + contraseña de aplicación, leída por
   etiqueta del archivo de claves y pasada por variable de entorno. Nunca se imprime.
7. **`--dry-run`:** muestra el diff sin enviar nada.
8. **Cuándo corre:** el agente lo ejecuta al crear o cambiar un ticket (regla nueva en
   `Docs/ORCHESTRATION/README.md`). La tarea programada en el PC de Luis es opcional y la decide Luis.

## 6. Interfaz (parte 4)

- **Vista nueva «Por persona»:** una columna por responsable (Luis primero, luego colaboradores, luego agentes)
  con tarjetas en curso, en revisión y bloqueadas, más un contador de hechas en 7 días.
- **Filtro «⚖️ Decide Luis».**
- **Insignia 🤖** en las tarjetas de ticket: sin arrastre ni edición, con enlace al PR.
- La lista de responsables viene de la API: `GET` devuelve `personas[]` (usuarios con rol + agentes).
- **Cabecera:** usuario conectado, rol y «última sincronización de tickets» (§12 del README).
- El enlace desde el panel de WordPress queda para una fase posterior.
- **Mejora acotada del código:** el cliente de la API se extrae a `tablero/api.js`, para no seguir creciendo el
  `index.html` de 1.514 líneas. No hay otras refactorizaciones.

## 7. Errores y casos límite

- 401 sin sesión (API) y redirección al login (página). 403 con sesión sin capacidad. 403 al editar una tarjeta
  ajena o una de ticket. 409 si el `at_id` de ticket choca con una tarjeta manual.
- El nonce vencido se renueva recargando: la interfaz detecta 403 `rest_cookie_invalid_nonce` y pide recargar.
- El sync es idempotente: correrlo dos veces no cambia nada la segunda vez.
- Si GitHub no responde al listar PR, el sync usa solo `origin/main` y lo avisa; no archiva tickets de ramas
  que no pudo leer.

## 8. Pruebas

- `tests/tablero/permisos-wp-test.php`, con el arnés de `tests/plan` (`AT_WP_LOAD=<ruta> php …`). La matriz:
  {administrador, colaborador, sin rol, externo apagado, servicio} × {leer, crear, editar propia, editar ajena,
  reasignar, editar ticket, sync}, con el resultado esperado de la tabla §3.
- `tests/tablero/test_tablero_sync.py`: mapeo de estados, versión más reciente por ticket, decisiones,
  archivado, idempotencia y `--dry-run`, con YAML de ejemplo.
- Migración: corrida dos veces sobre una copia local de las tablas, con conteo y mapeo de `asignado_a`.
- Interfaz: navegador local en escritorio y móvil (vista por persona, filtro y bloqueo de arrastre en 🤖).

## 9. Despliegue y reversión

Solo con el ok explícito de Luis, en este orden:
1. Respaldo de las tres tablas y de los archivos actuales.
2. `mu-plugin` `at-tablero` (todavía nadie lo usa).
3. Migración (`tools/tablero-migrar-v9.php` por SSH).
3b. `api-tablero.php` pasa a aviso 410.
4. `tablero/index.php` y `tablero/api.js`.
5. `tablero/index.html`.
6. Retiro del Basic Auth y de `AT_BOARD_TOKEN`, al final.

Verificación desde afuera en cada paso (códigos HTTP y una sesión real de Luis). Rollback: restaurar los
archivos y las tablas del respaldo. `setup-at-board.php` sigue bloqueado (403).

**Luis hace una sola cosa:** crear el usuario `agentes-at` (sin rol visible, con `at_board_sync`) y su
contraseña de aplicación, y guardarla con una etiqueta en el archivo de claves. Ningún agente ve ni escribe el valor.

## 10. Fuera de alcance

Enlace desde el panel de WordPress (fase posterior); activar externos por proyecto; pantalla propia de gestión
de usuarios; sincronización local↔PROD; editar tickets desde el tablero; notificaciones.
