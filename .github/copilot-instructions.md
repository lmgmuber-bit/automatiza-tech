# GitHub Copilot Instructions

<!-- AI-MEMORY-WORKFLOW:START -->
## GitHub Copilot Instructions

Antes de sugerir cambios, usa y manten actualizados `CLAUDE.md`, `AGENTS.md`, `DEEPSEEK.md`, `docs/AGENTS.md` y `.github/copilot-instructions.md` para que Claude, Codex, DeepSeek y GitHub Copilot compartan contexto y ultimos cambios. Existe un job de sincronizacion de documentacion que actualiza/sincroniza archivos de agentes, `CLAUDE.md`, `DEEPSEEK.md` y `.github/copilot-instructions.md`; respeta ese flujo. Revisa tambien `docs/`, `docs/Master`, `doc/`, `documentation/`, `wiki/` o carpetas equivalentes. Algunos proyectos guardan documentacion principal en `docs/Master`. Si cambian arquitectura, comandos, dependencias, flujos, estructura, reglas o decisiones importantes, actualiza la memoria/documentacion correspondiente. No incluyas secretos, tokens, passwords, credenciales ni datos sensibles.

Boveda maestra: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`
<!-- AI-MEMORY-WORKFLOW:END -->

<!-- AT-PIPELINE:START -->
## Automatizatech — Pipeline de Propuestas (Skills disponibles)

Este repositorio tiene skills de propuestas en `.github/skills/`. Usarlas cuando el usuario trabaje en ventas o propuestas a clientes.

### at-gamma-proposal → `.github/skills/at-gamma-proposal/SKILL.md`
Triggers: "generar propuesta gamma", "nueva propuesta cliente"
Genera: Gamma prompt (8 slides) + chatbot system prompt + prompt de diseño visual

### at-proposal-refiner → `.github/skills/at-proposal-refiner/SKILL.md`
Triggers: "refinar propuesta", "mejorar prompt gamma"
Flujo: historial + Gamma → API → evalúa → refina → prompt de diseño

### API del pipeline
```
GET/POST https://automatizatech.cl/?rest_route=/automatiza-tech/v1/proposal/{ID}/prompts
X-AT-Secret: <secret>  (wp-config.php del servidor)
```

Referencia: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\automatizatech-pipeline.md`
<!-- AT-PIPELINE:END -->

## Automatizatech — Plan de trabajo con carta Gantt (Etapas 1 y 2 EN PROD)

Guía única: `Docs/METODO_AT/PLAN-DE-TRABAJO.md`. Módulo `wp-content/themes/automatiza-tech/inc/plan-trabajo/` (cargado por `cargar.php`; `functions.php` no se toca). Al firmar un contrato de servicios se crea el plan; Luis lo edita y aprueba en la ficha del CRM y lo envía al cliente (correo con PDF o su WhatsApp, siempre con su clic). El cliente lo ve en `ver-plan.php?id=<código>` y agenda la llamada de seguimiento por `POST /wp-json/automatiza-tech/v1/plan-seguimiento` (reunión de seguimiento, nunca un lead). Pruebas en `tests/plan/`. Los correos al cliente nunca enlazan a `*.easypanel.host`.


## Automatizatech — Entregables con notas y sugerencias con IA (EN PROD)

Guía única: `Docs/METODO_AT/ENTREGABLES-NOTAS.md`. Módulo `wp-content/themes/automatiza-tech/inc/entregables/` (prefijo `at_en_`, cargado desde `inc/admin-proposals.php`; `functions.php` no se toca) y página pública `ver-entregable.php?id=<código>`. Las versiones van dentro del mismo entregable. El cliente ve solo las versiones ya enviadas. Las imágenes se reescriben con GD, sin EXIF, y se guardan en una carpeta privada. La IA (`ia.php`, OpenAI) solo sugiere texto y nunca envía. Pruebas: `bash tests/entregables/correr.sh`.

## graphify

For any question about this repo's architecture, structure, components, or how to add/modify/find
code, your first action should be `graphify query "<question>"` when `graphify-out/graph.json`
exists. Use `graphify path "<A>" "<B>"` for relationship questions and `graphify explain "<concept>"`
for focused-concept questions. These return a scoped subgraph, usually much smaller than the full
report or raw grep output.

Triggers: "how do I…", "where is…", "what does … do", "add/modify a <component>",
"explain the architecture", or anything that depends on how files or classes relate.

If `graphify-out/wiki/index.md` exists, use it for broad navigation. Read `graphify-out/GRAPH_REPORT.md`
only for broad architecture review or when query/path/explain do not surface enough context. Only read
source files when (a) modifying/debugging specific code, (b) the graph lacks the needed detail, or
(c) the graph is missing or stale.

Type `/graphify` in Copilot Chat to build or update the graph.
