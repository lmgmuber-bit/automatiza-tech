# Contexto local de OpenCode

Reglas principales: [AGENTS.md](AGENTS.md).

<!-- AT-RETOMAR-001-LOCAL:START -->
## AT-RETOMAR-001 — referencia local

Rama `claude/retomar-prospectos-final` (PR #94, sin merge), worktree `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje`. Guía: [Retomar prospectos](Docs/METODO_AT/RETOMAR-PROSPECTOS.md). Fase 1 EN PROD desde el 2026-10-18. Fase 2 (detección automática, redacción con IA, fotos de Higgsfield y correo semanal con aprobación de Luis) **EN PROD desde el 2026-10-19 con el reloj activo**; estado del primer ciclo y pendientes en la sección «Fase 2» de la guía. Nada sale a un prospecto sin la aprobación de Luis (en dos pasos: correo y página de confirmación).
<!-- AT-RETOMAR-001-LOCAL:END -->

## Gastos y créditos (Balance)

- Gastos y créditos (Luis, 2026-10-21): cada vez que un agente gaste créditos o dinero de AT (una generación en Higgsfield, una recarga, una suscripción, Google Cloud, etc.), le avisa a **Balance**, el agente de finanzas (`at-balance-claude` / `at-balance-codex`). El aviso lleva qué fue, cuánto, en qué cuenta, para qué proyecto y la referencia (id de la generación, factura o pedido). Si Balance no está disponible, el agente lo registra él mismo con `C:/Users/luis_/Documents/Codex/Shared-Tools/finanzas/registrar.py`. Nunca se escriben montos reales en el repositorio, que es público. Guía: `Docs/METODO_AT/FINANZAS-BALANCE.md`.
