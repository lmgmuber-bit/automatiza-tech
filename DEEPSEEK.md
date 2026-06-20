# DeepSeek Local Project Memory

<!-- AI-MEMORY-WORKFLOW:START -->
## DeepSeek Local Project Memory

DeepSeek local no lee archivos por si solo. Debe usarse desde una app puente con acceso al proyecto o a la boveda, por ejemplo Ollama/Open WebUI, AnythingLLM, Continue, Cline, Roo Code o un cliente MCP/RAG compatible.

Antes de trabajar, carga o lee este contexto: `AGENTS.md`, `DEEPSEEK.md`, `CLAUDE.md`, `docs/AGENTS.md`, `.github/copilot-instructions.md`, `docs/`, `docs/Master` si existe, y la boveda `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`. Existe un job de sincronizacion de documentacion que actualiza/sincroniza archivos de agentes, `CLAUDE.md`, `DEEPSEEK.md` y `.github/copilot-instructions.md`; respeta ese flujo.

### Contexto compartido

- Boveda maestra: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`
- Protocolo global: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\Multi-Agent-Workflow.md`
- DeepSeek debe recibir contexto por RAG, MCP, workspace de IDE/agente o prompt inicial.
- No guardar secretos, tokens, passwords, credenciales ni datos sensibles.
<!-- AI-MEMORY-WORKFLOW:END -->

<!-- AT-PIPELINE:START -->
## Automatizatech — Pipeline de Propuestas

Este proyecto tiene dos flujos de trabajo para generar y refinar propuestas a clientes.
DeepSeek debe recibir estas instrucciones por RAG, workspace de IDE o prompt inicial.

### Flujo 1 — Generar propuesta nueva (at-gamma-proposal)
**Triggers:** "generar propuesta gamma", "nueva propuesta cliente"
Inputs: datos de reunión (cliente, empresa, rubro, diagnóstico, precios, identidad).
Outputs:
1. Gamma prompt 8 slides → pegar en https://automatizatech.cl/wp-admin/admin.php?page=automatiza-proposals
2. Chatbot system prompt → webhook: https://n8n-n8n.kchiba.easypanel.host/webhook/demo-dinamico/chat
3. Prompt de diseño visual (requiere logo + navegar redes sociales del cliente)

### Flujo 2 — Refinar propuesta existente (at-proposal-refiner)
**Triggers:** "refinar propuesta", "mejorar prompt gamma"
Inputs: historial de llamada (.md) + presentación Gamma + edit_id de la propuesta.
Pasos:
1. GET API con edit_id → obtener gamma_prompt_text + system_prompt_text actuales
2. Cruzar con historial real → detectar gaps
3. Si hay gaps → mostrar diff → pedir aprobación → POST con campos refinados
4. Pedir logo + navegar Instagram/Facebook/sitio → generar prompt de diseño visual

### API
```
GET  https://automatizatech.cl/?rest_route=/automatiza-tech/v1/proposal/{ID}/prompts
POST https://automatizatech.cl/?rest_route=/automatiza-tech/v1/proposal/{ID}/prompts
Header: X-AT-Secret: <secret>  ← en wp-config.php del servidor. No exponer.
POST body: { "gamma_prompt_text": "...", "system_prompt_text": "..." }
```

### Referencia completa
`C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\automatizatech-pipeline.md`
<!-- AT-PIPELINE:END -->

