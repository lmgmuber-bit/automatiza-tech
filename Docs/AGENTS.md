# Agent Instructions

<!-- AI-MEMORY-WORKFLOW:START -->
## Multi-Agent Collaboration Workflow

Antes de trabajar, crea una rama nueva. Usa y manten actualizados `CLAUDE.md`, `AGENTS.md`, `DEEPSEEK.md`, `docs/AGENTS.md` y `.github/copilot-instructions.md` para que Claude, Codex, DeepSeek y GitHub Copilot compartan contexto y ultimos cambios. Existe un job de sincronizacion de documentacion que actualiza/sincroniza archivos de agentes, `CLAUDE.md`, `DEEPSEEK.md` y `.github/copilot-instructions.md`; respeta ese flujo y evita duplicar informacion que el job ya mantiene. Revisa tambien `docs/`, `docs/Master`, `doc/`, `documentation/`, `wiki/` o carpetas equivalentes, porque algunos proyectos guardan su documentacion principal en `docs/Master`. Crea archivos de memoria si faltan y son utiles. Actualiza memoria y documentacion cuando cambien arquitectura, comandos, dependencias, flujos, estructura, reglas o decisiones importantes. No guardes secretos. Al terminar, resume cambios, pruebas y estado Git; no hagas merge sin permiso. Espera confirmacion del usuario para cerrar y sugiere crear PR o merge hacia `main`/`master`/`develop`/`dev` segun corresponda.

### Contexto compartido

- Boveda maestra: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`
- Protocolo global: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\Multi-Agent-Workflow.md`
- Job de documentacion: hay un job que sincroniza/actualiza documentacion y memoria de agentes, incluyendo DeepSeek local. Revisar su salida antes de reestructurar archivos.
- DeepSeek local: el modelo necesita una app puente con acceso a archivos/RAG/MCP; no lee la boveda ni el proyecto por si solo.
- No guardar secretos, tokens, passwords, credenciales ni datos sensibles.
<!-- AI-MEMORY-WORKFLOW:END -->

## GT Baseball Academy (cliente Jeffer García) — estado vivo

- EN PROD en `https://gtbaseball.com/`:
  - inscripción con QR, pago y Turnstile;
  - portada con planes y fondos de estadio;
  - buscador privado `/buscador/` con clave, fotos y listado en PDF.
- Código en `demos/gt-baseball/`, en la rama `claude/gt-baseball-planes`. Flujos n8n en `N8N/gt-baseball/` (ver su
  README).
- 🔴 El repo es público: los datos del cliente viven en `gt-config.json`, fuera del repo. Se despliega solo con
  `deploy_dominio.py` y con el ok de Luis.
- **Relevo completo:** bóveda privada `10-Projects/GT-Baseball/README.md`. Diseño e historial en
  `Docs/superpowers/specs/2026-09-27-gt-baseball-prototipo-design.md`.

