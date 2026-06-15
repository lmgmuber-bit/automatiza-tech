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

