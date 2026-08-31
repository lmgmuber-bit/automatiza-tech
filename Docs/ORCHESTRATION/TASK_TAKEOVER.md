# Protocolo de continuidad y takeover de tareas

## Regla principal

Ninguna tarea pertenece permanentemente a un agente. OpenCode, Claude o Codex pueden retomar y terminar el trabajo de otro cuando este quede sin tokens, alcance un rate limit, pierda contexto, quede indisponible o cuando Luis solicite el relevo.

La exclusividad aplica al owner activo de la tarea, no a la identidad del agente: solo un agente modifica el alcance a la vez, pero el ownership se puede transferir todas las veces necesarias.

## Politica por defecto

Si el agente asignado solo esta esperando que vuelvan sus tokens o se reinicie su rate limit, y la tarea no es urgente, se mantiene el owner y se espera que retome. El takeover no es automatico por una pausa temporal.

Luis puede ordenar el relevo inmediato si la tarea se vuelve urgente, si no quiere esperar o si considera que el owner no esta logrando resolverla. El agente reemplazante debe aprovechar el trabajo e intentos anteriores, no descartarlos.

## Triggers de takeover

- `TOKEN_EXHAUSTION`
- `RATE_LIMIT`
- `CONTEXT_EXHAUSTION`
- `AGENT_UNAVAILABLE`
- `BLOCKED_CAPACITY`
- `LUIS_REASSIGNMENT`
- `ORCHESTRATOR_REBALANCE`
- `URGENT_OWNER_OVERRIDE`
- `UNRESOLVED_BY_OWNER`
- `FAILED_VALIDATION`

## Fuentes para reconstruir el contexto

El nuevo owner debe consultar, en este orden:

1. Ticket o ledger compartido.
2. `Docs/ORCHESTRATION/current-handoff.yaml` y handoff especifico del ticket.
3. Nota vigente en `AI-Memory-Vault`.
4. Graphify (`query`, `path` o `explain`).
5. `git status`, rama, `base_commit` y diff existente.
6. Documentacion del proyecto y archivos del alcance.
7. Pruebas y evidencias generadas por el owner anterior.

Si el agente anterior se quedo sin tokens antes de escribir un handoff, el reemplazo reconstruye el checkpoint desde estas fuentes y marca las suposiciones como `RECONSTRUCTED_HANDOFF`.

## Flujo de reasignacion

1. Marcar la tarea `BLOCKED_CAPACITY` o registrar el trigger equivalente.
2. Capturar el ultimo estado conocido: archivos tocados, diff, pruebas, decisiones, riesgos y siguiente accion.
3. Luis o el orquestador activo selecciona el nuevo owner.
4. Actualizar el ticket: `previous_owner`, `current_owner`, `takeover_reason`, `handoff_ref`, locks, rama y `base_commit`.
5. El nuevo owner acepta el takeover antes de editar.
6. Recalcular el routing del trabajo restante; no heredar a ciegas el modelo anterior.
7. Verificar que el owner anterior no siga activo sobre los mismos archivos.
8. Continuar desde el checkpoint, ejecutar pruebas y entregar evidencia final.

Cuando el trigger sea `UNRESOLVED_BY_OWNER` o `FAILED_VALIDATION`, el handoff debe incluir enfoques intentados, errores obtenidos, pruebas fallidas y motivos por los que la solucion no fue aceptada. El nuevo owner debe cambiar de enfoque con evidencia, no repetir a ciegas.

OpenCode puede retomar posteriormente una tarea continuada por Claude o Codex, pero debe volver a reclamarla. La misma regla funciona en cualquier direccion.

## Autoridad

- Luis puede ordenar takeover inmediato por urgencia, espera excesiva o falta de solucion satisfactoria.
- El orquestador activo puede reasignar por capacidad, riesgo o prioridad.
- Un agente no puede auto-reclamar una tarea que muestra otro owner activo sin registrar el relevo.
- El lease Claude/Codex controla quien orquesta; el ownership del ticket controla quien ejecuta. Son controles separados.

## Campos obligatorios

```yaml
ownership:
  current_owner: codex
  previous_owners: [opencode]
  takeover_allowed: true
  takeover_count: 1
  takeover_reason: RATE_LIMIT
  urgency: normal | urgent
  resolution_status: pending | unresolved | failed_validation
  reassignment_authorized_by: Luis Miguel
  handoff_ref: AT-HANDOFF-TASK-0001
  handoff_quality: COMPLETE | PARTIAL | RECONSTRUCTED_HANDOFF
```

## Regla para el tablero

El tablero debe mostrar owner actual, owner anterior, causa del takeover, cantidad de relevos y ultima sincronizacion. Nunca debe inferir que un agente sigue trabajando solo porque aparece en un seed o en `localStorage`.
