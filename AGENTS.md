# Agent Instructions

<!-- AI-MEMORY-WORKFLOW:START -->
## Multi-Agent Collaboration Workflow

Antes de trabajar, crea una rama nueva. Usa y manten actualizados `CLAUDE.md`, `AGENTS.md`, `OPENCODE.md`, `docs/AGENTS.md` y `.github/copilot-instructions.md` para que Claude, Codex, OpenCode y GitHub Copilot compartan contexto y ultimos cambios. Existe un job de sincronizacion de documentacion que actualiza/sincroniza archivos de agentes, `CLAUDE.md`, `OPENCODE.md`, `AGENTS.md` y `.github/copilot-instructions.md`; respeta ese flujo y evita duplicar informacion que el job ya mantiene. Revisa tambien `docs/`, `docs/Master`, `doc/`, `documentation/`, `wiki/` o carpetas equivalentes, porque algunos proyectos guardan su documentacion principal en `docs/Master`. Crea archivos de memoria si faltan y son utiles. Actualiza memoria y documentacion cuando cambien arquitectura, comandos, dependencias, flujos, estructura, reglas o decisiones importantes. No guardes secretos. Al terminar, resume cambios, pruebas y estado Git; no hagas merge sin permiso. Espera confirmacion del usuario para cerrar y sugiere crear PR o merge hacia `main`/`master`/`develop`/`dev` segun corresponda.

### Contexto compartido

- Boveda maestra: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`
- Protocolo global: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\Multi-Agent-Workflow.md`
- Job de documentacion: `ClaudeCodexMemorySync` corre cada 60 minutos en modo seguro (`-NoOverwriteExisting`) sobre `C:\wamp64\www\automatiza-tech` y `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`. Incluye `CLAUDE.md`, `AGENTS.md`, `docs/AGENTS.md`, `OPENCODE.md` y `.github/copilot-instructions.md`; crea faltantes y registra diferencias, pero no sobrescribe memorias divergentes. Cada agente sigue siendo responsable de actualizar la memoria compartida al cerrar tareas importantes.
- OpenCode: usa `AGENTS.md` como reglas principales del proyecto. `OPENCODE.md` contiene contexto especifico del flujo multiagente y queda cargado desde `opencode.json`.
- No guardar secretos, tokens, passwords, credenciales ni datos sensibles.
<!-- AI-MEMORY-WORKFLOW:END -->

<!-- AT-PROFESSIONAL-STANDARD:START -->
## Estándar profesional transversal

Todos los agentes deben aplicar `Docs/ENGINEERING_STANDARDS/PROFESSIONAL_ENGINEERING_STANDARD.md` y el gate proporcional de `Docs/ENGINEERING_STANDARDS/quality-gates.yaml`, además de las reglas específicas del proyecto. El estándar cubre ownership, flujo, arquitectura, código, testing, CI/CD, Git/PR, seguridad, observabilidad, entrega al cliente y cierre con evidencia.
<!-- AT-PROFESSIONAL-STANDARD:END -->

<!-- AT-FTP-HANDOFF:START -->
## Entrega obligatoria para FTP

Al finalizar cualquier tarea que cambie archivos desplegables, incluir siempre una lista exacta para Luis con: ruta local, destino relativo en PROD, clasificación `OBLIGATORIO` u `OPCIONAL`, orden de subida cuando importe y archivos temporales o sensibles que no deben subirse. Distinguir claramente cambios locales de cambios ya desplegados; nunca afirmar que algo está en PROD sin evidencia.
<!-- AT-FTP-HANDOFF:END -->

<!-- AT-ORCHESTRATION:START -->
## Conexiones y credenciales

Antes de decir "no tengo acceso", "no puedo conectarme a n8n" o "no tengo credenciales", **verifica**. La fuente canónica es `Docs/ORCHESTRATION/CONEXIONES-Y-CREDENCIALES.md`: trae el estado real de cada conexión (n8n, Higgsfield, Apify, Drive, budgetpixel) por agente y el comando exacto de chequeo. Asumir que algo no está disponible ya causó trabajo duplicado y respuestas equivocadas a Luis.

Las credenciales viven **fuera del repo**, en `C:\Users\luis_\OneDrive\Documentos\APIS KEy\APIS KEY.txt`. Léelas solo si las necesitas y **nunca copies un valor al repositorio, a un `.env`, a un config, a documentación ni a un log**. Si hay que armar un archivo de configuración, entrégale la plantilla a Luis y que él pegue los valores; un agente no escribe secretos.

## Orquestacion multiagente AT

- Fuente canonica: `Docs/ORCHESTRATION/README.md`.
- Claude es el orquestador principal por defecto y Codex el suplente; solo uno puede estar activo mediante un lease valido.
- Antes de ejecutar, cada agente registra clase, riesgo, incertidumbre, reversibilidad y perfil/modelo segun `Docs/ORCHESTRATION/model-routing.yaml`.
- Codex debe seguir `Docs/ORCHESTRATION/CODEX_ORCHESTRATOR.md` cuando tome el relevo.
- Claude debe seguir `Docs/ORCHESTRATION/CLAUDE_ORCHESTRATOR.md` como orquestador principal.
- Estado actual del relevo: `Docs/ORCHESTRATION/current-handoff.yaml`.
- Continuidad cruzada: `Docs/ORCHESTRATION/TASK_TAKEOVER.md`; cualquier agente puede retomar una tarea bloqueada tras reasignar ownership.
- Los chats externos no se comunican por si solos: usar tickets/ledger compartido o a Luis como puente.
- El tablero consume las fuentes canonicas; no crea una segunda verdad permanente.
<!-- AT-ORCHESTRATION:END -->

<!-- AT-METHOD:START -->
## Método AT y casos de uso

Para planificar, cotizar, ejecutar, entregar o retomar trabajo con clientes, leer `Docs/METODO_AT/README.md`. Las fuentes canónicas son `Docs/METODO_AT/APLICACION_DEL_METODO_AT.md` y `Docs/METODO_AT/SERVICIOS_Y_CASOS_DE_USO.md`. Para marketing, nichos y contenido de Instagram/Reels, usar `Docs/MARKETING/2026-07-22-AT-ESTUDIO-NICHOS-Y-ESTRATEGIA-REELS-INSTAGRAM.md`. Distinguir siempre capacidades actuales comprobadas de trabajo en curso y roadmap.
<!-- AT-METHOD:END -->

<!-- AT-VIDEO-RULES:START -->
## AutomatizaTech — Reglas obligatorias para videos/Reels

Estas reglas aplican a cualquier video, Reel Diario, Reel programado, comercial, Story, post audiovisual o prompt generado desde Codex/Claude/n8n para AutomatizaTech.

1. Logo AT obligatorio y discreto: usar solo el logo real de referencia de AutomatizaTech. Debe ir en la esquina superior izquierda como watermark profesional, muy pequeno, sutil y translucido: maximo 4% del ancho del frame, opacidad aproximada 35-45%, reconocible pero nunca dominante. Nunca ubicarlo al centro, nunca agrandarlo, nunca inventar un logo generico ni variar el diseno. Si la herramienta generativa no respeta el tamano o fidelidad, agregar/corregir el overlay real en post con ffmpeg.
2. Texto visible en espanol de Chile: todo texto en pantalla, interfaces, chats, emails, notificaciones, botones, calendarios, dashboards y celulares debe pedirse en espanol (Chile), nunca en ingles. Si Veo/Flow deforma texto menor, no regenerar solo por eso si la pieza es publicable; pero el prompt siempre debe pedir espanol.
3. Antes de generar o publicar, revisar prompts de video para confirmar que incluyan estas reglas. Si un prompt contradice estas reglas, corregirlo primero.
4. Cierre estándar AT para Reels: desde 2026-07-14, agregar una sola vez al final del Reel principal el clip completo `N8N/reel-diario-media-worker/assets/outro-at-10s.mp4` (~10s, 720x1280, con audio y CTA `Agenda tu diagnóstico gratis en automatizatech.cl`). Estructura final: `parte_1 + parte_2 + outro-at-10s.mp4`. No insertar el cierre entre partes ni dentro del bonus/Story. No usar el recorte de 3.2s porque puede cortar el logo; el banner turquesa anterior queda prohibido salvo instruccion expresa de Luis.
5. Log de publicaciones Reel Diario: n8n Plan B debe actualizar el CSV existente `IG-AT/Reel-Diario/Reel-Diario-Log-Publicaciones.csv` (`file_id=1NpJf8VVTiqcN5LdGFfLdi0T9qQ9iGCRw`) con update real del mismo archivo. No crear CSVs duplicados. Registrar intentos reales de publicación, fallos y rechazos QA; no registrar skips como `already_published`, `fresh_lock` o `folder_not_found`.

Bloque recomendado para prompts:

```text
MANDATORY: The provided reference image is the ONLY logo for AutomatizaTech. You MUST superimpose the EXACT "AT" logo (navy blue circle, white "AT" letters, teal arrow) from the reference image in the top-left corner of the frame. The logo must be clear, sharp, very small, and translucent like a professional TV watermark. Never invent a different logo design or a generic icon.

MANDATORY: All on-screen text, UI elements, app interfaces, phone screens, emails, notifications, and any visible written content in every video or image must be in Spanish (Chile), never in English. This applies to chat bubbles, dashboards, buttons, calendars, and any readable text shown on any screen.
```
<!-- AT-VIDEO-RULES:END -->

<!-- AT-PIPELINE:START -->
## Automatizatech — Pipeline de Propuestas

Este proyecto tiene dos skills de ventas. Invocarlas cuando el usuario trabaje con propuestas a clientes.

### Skill 1 — at-gamma-proposal
**Cuándo:** datos de reunión con prospecto nuevo.
**Triggers:** "generar propuesta gamma", "nueva propuesta cliente", "propuesta gamma"
**Genera:** Gamma prompt (8 slides) + chatbot system prompt + prompt de diseño visual
**Instrucciones:** `.github/skills/at-gamma-proposal/SKILL.md` (Copilot) o `C:\Users\luis_\.codex\skills\at-gamma-proposal\SKILL.md` (Codex)

### Skill 2 — at-proposal-refiner
**Cuándo:** propuesta ya guardada en el sistema (tiene edit_id).
**Triggers:** "refinar propuesta", "mejorar prompt gamma", "actualizar prompts"
**Flujo:** historial llamada + Gamma → GET API → evalúa → refina → POST → Output 3 diseño
**Instrucciones:** `.github/skills/at-proposal-refiner/SKILL.md` (Copilot) o `C:\Users\luis_\.codex\skills\at-proposal-refiner\SKILL.md` (Codex)

### API del pipeline
```
GET/POST https://automatizatech.cl/?rest_route=/automatiza-tech/v1/proposal/{ID}/prompts
Header: X-AT-Secret: <secret>  (wp-config.php del servidor — no exponer)
```

### Referencia completa
`C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\automatizatech-pipeline.md`
<!-- AT-PIPELINE:END -->

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, invoke the `skill` tool with `skill: "graphify"` before doing anything else.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).

## Incidente de seguridad 2026-08-26

- **Incidente de seguridad 2026-08-26 (LEER antes de tocar ARGOS, secretos o el Reel Diario):** `Docs/2026-08-26-INCIDENTE-SEGURIDAD-N8N-Y-REEL-DIARIO.md`. Cerró tres agujeros (bypass de `WP_DEBUG` que dejaba público el listado de errores de ARGOS, `n8n_test_token` que permitía disparar envío masivo de correos, API keys en texto plano en nodos de n8n) y rotó cinco secretos. Contiene además la regla obligatoria de redacción al buscar en el repo, cómo generar el token de Instagram para esta app (`graph.instagram.com`, no el flujo de Page Token), y el workaround del bloqueo de `settings` al escribir workflows por API.
