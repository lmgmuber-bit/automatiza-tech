# AT-ORCH-001 — Gobierno multi-agente

> Estado: **ESQUEMA + LEDGER INICIALIZADOS**. Reemplaza al `agent-log.md` planeado en AT-INT-005.
> Ubicación: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\orchestration\`

## Objetivo

Gobernar la coordinación entre Claude (orquestador), Codex (suplente) y OpenCode Go (ejecutor) para que:
- No haya dos orquestadores activos al mismo tiempo.
- Cada ticket tenga dueño, revisor, riesgo, complejidad, modelo, branch y evidencia requerida.
- Las decisiones y eventos queden en un ledger append-only (reemplaza al `agent-log.md`).

## Archivos

```
orchestration/
├── state.json     ← estado actual: active_orchestrator + lease + handoff_id
├── ledger.jsonl   ← append-only, una línea por evento/decisión (JSON por línea)
└── tickets/       ← (futuro) un JSON por ticket con todos los campos
```

## state.json — schema

```json
{
  "schema_version": "1.0",
  "active_orchestrator": {
    "role": "Claude" | "Codex",
    "agent_id": "<string>",
    "acquired_at": "<ISO-8601>",
    "lease_expires_at": "<ISO-8601>",
    "lease_ttl_seconds": 3600,
    "handoff_id": "<string>",
    "reason": "<string>"
  },
  "previous_orchestrators": [
    { "role": "...", "agent_id": "...", "handoff_id": "...",
      "acquired_at": "...", "released_at": "...", "release_reason": "..." }
  ],
  "candidates": ["Claude", "Codex"],
  "executors": ["OpenCode Go"],
  "humans": ["Luis"],
  "rules": {
    "only_one_active_orchestrator": true,
    "lease_auto_release_on_expiry": true,
    "acquire_requires_handoff_id": true,
    "release_appends_to_ledger": true,
    "human_can_force_release": true,
    "executors_cannot_orchestrate": true
  }
}
```

## ledger.jsonl — formato

Una línea por evento, JSON estricto, append-only. Tipos:

- `acquire`: un agente toma el rol de orquestador.
- `release`: el orquestador suelta el rol (tokens agotados, fin de tarea, force-release por Luis).
- `delegate`: el orquestador delega tickets a un ejecutor.
- `decision`: el orquestador registra una decisión (veredicto, aprobación, bloqueo, escalación).
- `report`: un ejecutor devuelve un reporte al orquestador.
- `handoff`: transferencia entre orquestadores (release + acquire del siguiente).
- `alert`: un ejecutor sube una alerta/opinión al orquestador.
- `human_input`: Luis da una instrucción directa (tarea directa a OpenCode Go, veto, aprobación).

Cada línea tiene: `ts`, `type`, `role`, `agent_id`, `handoff_id`, y campos específicos del tipo.

## Schema de ticket (campos requeridos por AT-ORCH-001)

Cada ticket (`tickets/AT-TAB-XXX.json` futuros) lleva:

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | string | `AT-TAB-001`, `AT-ORCH-001`, etc. |
| `handoff_id` | string | ID del handoff al que pertenece (ej: `AT-TABLERO-ARCH-001`) |
| `title` | string | título corto |
| `requester` | string | quién pidió el ticket (rol: Claude/Codex/Luis) |
| `owner` | string | quién ejecuta (rol: OpenCode Go/Luis) |
| `reviewer` | string | quién revisa al cerrar (rol: Claude/Codex/Luis) |
| `risk` | enum | `low` / `medium` / `high` / `critical` |
| `complexity` | enum | `trivial` / `small` / `medium` / `large` |
| `model_profile` | string | modelo sugerido: `glm-5.2` / `deepseek-flash` / `sonnet` / `opus` |
| `branch` | string | branch git donde se trabaja (ej: `codex/review-orchestrator-v1`) |
| `base_commit` | string | commit base desde el que se parte |
| `locked_files` | string[] | archivos que NO se tocan en este ticket |
| `depends_on` | string[] | IDs de tickets que deben cerrarse antes |
| `evidence_required` | string[] | evidencia para cerrar: `plan`, `diff`, `local_tests`, `security_review` |
| `state` | enum | `todo` / `in_progress` / `in_review` / `done` / `blocked` |
| `lease_expires_at` | ISO-8601 | deadline del trabajo |
| `accepted_evidence` | object | links/resúmenes de la evidencia entregada |

## Protocolo de acquire / release

```
acquire(role, agent_id, handoff_id, lease_ttl):
  1. Leer state.json
  2. Si active_orchestrator existe Y lease_expires_at > now:
     - NO adquirir. Devolver conflicto.
     - Si el agente activo es el mismo: renovar lease (extender expires_at).
  3. Si active_orchestrator existe Y lease_expires_at <= now:
     - Auto-release: append ledger {type:release, reason:"lease_expired"}
     - Mover active a previous_orchestrators
  4. Escribir active_orchestrator = {role, agent_id, acquired_at:now, lease_expires_at:now+ttl, ...}
  5. Append ledger {type:acquire, ...}
  6. Escribir state.json

release(reason):
  1. Verificar que el agente que llama == active_orchestrator.agent_id (o Luis force-release)
  2. Append ledger {type:release, reason}
  3. Mover active a previous_orchestrators
  4. Escribir state.json sin active_orchestrator
```

**Impedir dos orquestadores activos**: el paso 2 de acquire bloquea si hay uno activo con lease válido. Solo Luis puede `force_release` (veto humano).

## Estado actual (inicializado 2026-07-10)

- `active_orchestrator`: Codex (`codex-review-orchestrator-v1`), lease 1 hora.
- `handoff_id`: `AT-TABLERO-ARCH-001`.
- 5 eventos en el ledger: acquire Claude → delegate → release Claude → acquire Codex → decision (veredicto v7.1).
- OpenCode Go sigue ejecutando los 4 tickets delegados; NO es orquestador.

## Próximos pasos

- [ ] Que OpenCode Go appendee su reporte de cierre de los 4 tickets al ledger (tipo `report`).
- [ ] Que Codex revise el reporte, registre `decision` (approve/block), y haga `release` del lease.
- [ ] Si Luis aprueba el deploy: Codex registra `decision: deploy_approved`, Luis ejecuta FTP, OpenCode Go appendea `report: deploy_done`.
- [ ] v9: exponer este ledger en el tablero como tercer tab " Ledger" (solo lectura, fetch al vault vía endpoint).

## Notas

- El ledger es append-only: nunca borrar líneas. Si un evento fue error, agregar uno nuevo `type:amendment` que lo corrige.
- `agent-log.md` planeado en AT-INT-005 queda **obsoleto**: el ledger.jsonl lo reemplaza con estructura.
- Los `state.json` y `ledger.jsonl` NO contienen secretos (solo metadata de gobernanza). Pueden versionarse en el vault si el vault es privado.
