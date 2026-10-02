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

## Responder con base y fundamentos (regla general, Luis 2026-09-23)

No asumir. Antes de dar una cifra, un precio, un plazo, un estado o una capacidad, verificarla con una fuente (código, base de datos, prueba, documentación o búsqueda web) y decir de dónde sale, con enlace cuando exista. "Yo creía", "creo que" o "debería" no son base. Lo que no se pueda verificar se presenta como **supuesto explícito**, con el motivo y cómo se comprobaría; nunca como hecho. Los precios que se propongan a un cliente llevan referencias de mercado con fuentes y el cálculo aplicado al caso.





