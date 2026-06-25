# Claude Project Memory

<!-- AI-MEMORY-WORKFLOW:START -->
## Claude Project Memory

Antes de trabajar, crea una rama nueva. Usa y manten actualizados `CLAUDE.md`, `AGENTS.md`, `DEEPSEEK.md`, `docs/AGENTS.md` y `.github/copilot-instructions.md` para que Claude, Codex, DeepSeek y GitHub Copilot compartan contexto y ultimos cambios. Existe un job de sincronizacion de documentacion que actualiza/sincroniza archivos de agentes, `CLAUDE.md`, `DEEPSEEK.md` y `.github/copilot-instructions.md`; respeta ese flujo y evita duplicar informacion que el job ya mantiene. Revisa tambien `docs/`, `docs/Master`, `doc/`, `documentation/`, `wiki/` o carpetas equivalentes, porque algunos proyectos guardan su documentacion principal en `docs/Master`. Crea archivos de memoria si faltan y son utiles. Actualiza memoria y documentacion cuando cambien arquitectura, comandos, dependencias, flujos, estructura, reglas o decisiones importantes. No guardes secretos. Al terminar, resume cambios, pruebas y estado Git; no hagas merge sin permiso. Espera confirmacion del usuario para cerrar y sugiere crear PR o merge hacia `main`/`master`/`develop`/`dev` segun corresponda.

### Contexto compartido

- Boveda maestra: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`
- Protocolo global: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\Multi-Agent-Workflow.md`
- Job de documentacion: hay un job que sincroniza/actualiza documentacion y memoria de agentes, incluyendo DeepSeek local. Revisar su salida antes de reestructurar archivos.
- Claude debe revisar este archivo antes de modificar el proyecto.
<!-- AI-MEMORY-WORKFLOW:END -->

## Punteros de proyecto (estado vivo)

- **Portal OmniCliente — rediseño visual EN PROD (2026-06-01):** ver `Docs/MASTER/06_PORTAL_OMNICLIENTE_FRONTEND.md` y vault `10-Projects/Portal-OmniCliente-Redseno.md`. Rama `feat/inbox-premium-ui` (PR sin mergear). 🔴 Deploy: subir helpers `at-*.php` + `lib/at-auth-middleware.php` ANTES que el resto, o fatal `at-path-safe.php`.
- **Instagram CM AT (automatizar IG con Higgsfield):** plan en vault `10-Projects/Instagram-CM-AT.md` + adaptación `2026-05-29-Adaptacion-Master-Fusion-a-Plan-IG-CM.md` + config canal `Config-Canal-Instagram-Omnichannel.md`. Estado: pendiente config del usuario (upgrade Higgsfield + conectar canal IG en Meta).

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
