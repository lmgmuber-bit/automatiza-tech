# Conexiones y credenciales — regla general para todos los agentes

> **Aplica a Claude, Codex, OpenCode y Copilot.** Fuente canónica. Si un doc viejo dice
> otra cosa sobre conexiones, prevalece este archivo.
> Última verificación real: **2026-08-26**.

---

## 1. Regla de oro: verificar antes de asumir

**Nunca digas "no tengo acceso a X", "no puedo conectarme a n8n" o "no tengo credenciales"
sin haber corrido primero el chequeo de la sección 4.**

Este proyecto tiene conexiones vivas a n8n, Higgsfield, Apify, Google Drive y más. Asumir
que no están disponibles ha causado trabajo duplicado y respuestas equivocadas a Luis. El
costo de verificar es un comando; el costo de asumir mal es una tarea entera mal hecha.

Si después de verificar la conexión realmente falla, **repórtalo con la salida del chequeo**
— no como una suposición, sino como un hecho con evidencia.

---

## 2. Dónde están las credenciales

```
C:\Users\luis_\OneDrive\Documentos\APIS KEy\APIS KEY.txt
```

Ahí están, entre otras: Anthropic, OpenAI, Gemini/Google AI Studio, Google Places
(Lead Scout), Google para CumpleClick, Apify, Supabase (Reúne VZLA) y la URL de Easypanel.

**Reglas sobre ese archivo — no negociables:**

1. **Léelo solo si lo necesitas.** No lo abras "por si acaso": cada lectura mete secretos
   de producción en el contexto.
2. **Nunca copies un valor al repositorio.** Ni a `.env`, ni a un `config.php`, ni a un
   YAML, ni a documentación, ni a un mensaje de commit. Ni siquiera "temporalmente".
3. **Nunca lo imprimas en pantalla, logs, capturas ni reportes.** Cita el nombre del
   servicio, nunca el valor.
4. **Si necesitas que un archivo de configuración quede armado, entrégale la plantilla a
   Luis y que él pegue los valores.** Un agente no escribe secretos.
5. Si un secreto queda expuesto por accidente (salió en una salida de terminal, en un
   commit, en una captura), **avísale a Luis de inmediato y recomiéndale rotarlo**.

> Nota de riesgo conocida: ese archivo vive en OneDrive, o sea sincronizado a la nube en
> texto plano, y contiene claves de producción. Está en el radar de Luis; no es un
> descubrimiento nuevo que haya que reportar cada vez.

Credenciales que **no** están en ese archivo: FTP de Hostinger, la contraseña de la BD de
CumpleClick y la API key de n8n (esta última ya vive configurada, ver abajo).

---

## 3. Estado real de las conexiones

| Servicio | Para qué | Claude | Codex | OpenCode |
|---|---|---|---|---|
| **n8n** | Reels, bots WhatsApp, recordatorios, IG | ✅ `n8n-mcp` | ✅ `n8n-mcp` | ❌ sin configurar |
| **Higgsfield** | Video/imagen IA, Marketing Studio | ✅ conector | ✅ `mcp_servers.higgsfield` | ❌ |
| **Apify** | Scraping de referencias para el brief de Reels | ✅ `Apify` (2026-08-03) | ❌ | ❌ |
| **Google Drive** | Carpetas Reel Diario, logs de publicación | ✅ conector | — | ❌ |
| **budgetpixel** | Generación de imagen/video/música | ✅ | ✅ | ❌ |
| **context7 / metricool** | Docs de librerías / métricas sociales | ❌ | ✅ | ❌ |
| **Playwright / Chrome DevTools** | QA de frontend, capturas | ✅ plugin | ✅ `browser-use` | ❌ |

**Pendientes de autorizar en Claude:** Notion y Stripe (OAuth), GitHub (header mal formado)
y `magic` de 21st.dev (API key reseteada). Se arreglan con `/mcp` en sesión interactiva.

### 3.1 Credenciales dentro de n8n (actualizado 2026-08-26)

Los secretos que n8n usa para hablar con `automatizatech.cl` viven en **credenciales de n8n**
(cifradas), no escritos a mano en los nodos. Si encuentras un `X-API-Key` con valor literal en
la cabecera de un nodo, es un bug: migrarlo a credencial.

| Credencial n8n | Constante en PROD | La usan |
|---|---|---|
| `AT ARGOS WP Key` | opción `automatiza_n8n_api_key` (WordPress) | `Buscar Historial BD`, `Guardar en BD` (ARGOS) · `Buscar Pendientes`, `Reportar Intento` (Mecánico) |
| `AT Reel Diario WP Secret v2` | `REEL_DIARIO_SECRET` | 11 nodos de `AT_Reel_Diario_Checkpoints_PlanB`, incluido `Token Preflight` |
| `AT Contactos n8n Token` | `AT_N8N_CONTACTS_TOKEN` | nodo `HTTP Request` de `Automatiza Tech Envio de correos a contactos` |

Constantes sin consumidor en n8n: `AT_REST_SECRET` (endpoint de propuestas, lo usan los
agentes vía skill) y `AT_BOARD_TOKEN` (frontend del tablero, se pega en el navegador).
`OMNI_ADMIN_SECRET` va por variable de entorno del servidor, no por `wp-config-secrets.php`.

**Al rotar un secreto**, actualizar el valor en los dos lados a la vez: `wp-config-secrets.php`
y la credencial de n8n. Verificación barata para el Reel Diario: llamar
`reel-diario/token-check` con la credencial — devuelve `{"ok":true,...}` si ambos coinciden.

### 3.2 Escribir workflows por API: el bloqueo de `settings`

Muchos workflows rebotan al escribirlos con
`Invalid request: request/body/settings must NOT have additional properties`. La UI de n8n
guarda `timeSavedMode` y `availableInMCP` en `settings`, y el schema público los rechaza al
reescribir. **El MCP fusiona los settings guardados e ignora los que uno mande**, así que no
hay solución por MCP: hay que hacer `PUT /api/v1/workflows/<id>` filtrando esas dos claves.
`callerPolicy` sí es válida. Detalle completo en
`Docs/2026-08-26-INCIDENTE-SEGURIDAD-N8N-Y-REEL-DIARIO.md` §9.

---

**OpenCode no tiene ningún MCP configurado.** Si un handoff le pide a OpenCode tocar n8n,
Higgsfield o Drive, ese handoff está mal planteado: o se le configura el MCP primero, o la
tarea va a otro agente.

---

## 4. Cómo verificar y conectar, por agente

### Claude Code

Los MCP viven en `~/.claude.json` (scope de usuario, sirve en todos los proyectos).

```bash
claude mcp list
```

```bash
claude mcp get n8n-mcp
```

Las herramientas MCP llegan **diferidas**: aparecen por nombre pero sin esquema, y hay que
cargarlas antes de invocarlas. Si intentas llamarlas directo, falla con
`InputValidationError`. Cárgalas primero:

```
ToolSearch "select:mcp__n8n-mcp__n8n_health_check,mcp__n8n-mcp__n8n_list_workflows"
```

Agregar un servidor nuevo:

```bash
claude mcp add --scope user --transport http <Nombre> https://servidor/mcp
```

Prefiere **OAuth** (agregar sin token y autorizar con `/mcp`) antes que meter el token en
la URL: un token en la URL sale completo en cada `claude mcp list` y en cualquier captura.

> **Los MCP se cargan al iniciar la sesión.** Si acabas de conectar un servidor, la sesión
> actual **no lo ve**. Hay que abrir una sesión nueva. Esto vale también para las tareas
> programadas: cada corrida arranca sesión propia, así que sí toman lo recién conectado.

### Codex CLI

Los MCP viven en `~/.codex/config.toml`, bloques `[mcp_servers.<nombre>]`.

El ejecutable **no está en el PATH por sí solo**: viene dentro de la app de escritorio, en
una ruta con hash que cambia en cada actualización. Se usa el shim estable:

```
C:\Users\luis_\bin\codex.cmd
```

`Get-Command codex` fallando **no significa que Codex no esté instalado**. Verificar así:

```bash
codex --version
```

```bash
codex login status
```

### OpenCode

Config de usuario en `~/.config/opencode/opencode.jsonc`; config de proyecto en
`.opencode/opencode.json`. **Hoy ninguna de las dos declara servidores MCP.**

---

## 5. Hooks del repositorio: apuntar siempre a un shim

`.codex/hooks.json` llamaba a graphify por ruta absoluta de Python. Al cambiar de PC esa
ruta dejó de existir (python.org vs Microsoft Store instalan en carpetas distintas) y el
hook quedó muerto — y como corre en **cada** llamada Bash, Codex no podía trabajar en el
proyecto.

**Regla:** todo hook versionado en el repo apunta a un shim de `C:\Users\luis_\bin\`, nunca
a una ruta de `AppData` o de Python. Si el intérprete se mueve, se corrige el shim y no un
archivo versionado.

Shims disponibles: `codex.cmd`, `graphify.cmd`.

---

## 6. Chequeo rápido antes de decir "no puedo"

1. ¿El servicio aparece en la tabla de la sección 3? Si sí, **existe**.
2. Corre el chequeo de tu agente (sección 4).
3. Si el estado es `Needs authentication`, no está roto: **falta que Luis autorice** con
   `/mcp` en sesión interactiva. Pídeselo, no lo declares imposible.
4. Si el estado es `Failed to connect`, reporta el error textual.
5. Solo entonces, y con la salida a la vista, di que no está disponible.

---

Relacionado: `README.md` · `CLAUDE_ORCHESTRATOR.md` · `CODEX_ORCHESTRATOR.md` ·
`current-handoff.yaml`
