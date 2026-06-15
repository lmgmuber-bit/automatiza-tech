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





