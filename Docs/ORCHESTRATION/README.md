# Orquestacion multiagente de AutomatizaTech

Version: 1.1  
Fecha: 2026-07-10  
Autoridad final: Luis Miguel

Esta carpeta es la fuente canonica para coordinar a Claude, Codex, OpenCode y futuros agentes. Las memorias de cada herramienta solo deben apuntar aqui; no deben copiar el protocolo completo.

## Principios

1. Claude es el orquestador principal por defecto y Codex es el suplente.
2. Solo puede existir un orquestador activo. El turno se controla con un lease explicito.
3. Un agente externo no recibe mensajes automaticamente desde otro chat. La comunicacion ocurre mediante el ledger compartido, tickets o Luis.
4. Antes de ejecutar, cada agente clasifica complejidad, riesgo, incertidumbre y reversibilidad, y registra el perfil de modelo elegido.
5. El riesgo prevalece sobre la complejidad. Una tarea corta de produccion puede requerir el perfil mas fuerte.
6. No se permite downgrade silencioso para trabajo critico.
7. AT es la prioridad estrategica del portafolio, pero P0-P3 expresa severidad operacional, no importancia de marca.
8. Push, deploy, secretos, pagos, borrados, comunicaciones externas y cambios irreversibles requieren la autorizacion correspondiente.
9. El owner de una tarea es exclusivo pero transferible: por defecto se espera su retorno; por urgencia, falta de capacidad o falta de solucion, Luis puede asignarla a otro agente.
10. Claude, Codex y OpenCode pueden terminar el trabajo de cualquiera de los otros tras registrar el takeover; nunca trabajan simultaneamente sobre el mismo alcance.

## Precisiones de Luis (2026-07-10, v1.1)

11. **Claude como ejecutor.** Ademas de orquestar, Claude participa como ejecutor cuando: (a) la tarea es super compleja (C2/C3) y conviene el razonamiento mas fuerte; (b) ningun otro agente logro resolverla; o (c) ningun agente tiene tokens/capacidad y la tarea es urgente y no puede esperar. En cualquiera de esos casos Claude registra el takeover como cualquier owner y aplica sus propios gates (revision independiente y gate humano en C3).
12. **El tablero como canal comun.** Todos los agentes se comunican y sincronizan estado a traves del tablero. El tablero es un **espejo operativo de la memoria del vault** y de las fuentes canonicas: las consume y las refleja, nunca las reemplaza ni crea una segunda verdad permanente. Debe mostrar origen, version y ultima sincronizacion.
13. **Vault y Graphify obligatorios.** Todo agente debe tener en cuenta la memoria del vault (`C:\Users\luis_\Documents\Codex\AI-Memory-Vault`) y el knowledge graph de Graphify como parte de su contexto antes de actuar, y mantenerlos actualizados cuando cambie arquitectura, decisiones o estado relevante.

## Archivos canonicos

- `CODEX_ORCHESTRATOR.md`: instrucciones para Codex cuando opera como suplente o toma el lease activo.
- `CLAUDE_ORCHESTRATOR.md`: contexto y reglas de Claude como orquestador principal.
- `CLAUDE_ORCHESTRATOR_PROMPT_FINAL.md`: prompt maestro autónomo y actualizado para iniciar o recuperar a Claude con todo el contexto AT.
- `model-routing.yaml`: politica de clasificacion y seleccion interna del perfil de modelo.
- `ticket.example.yaml`: contrato minimo de un ticket delegable.
- `orchestrator-lease.example.yaml`: contrato del turno exclusivo y del handoff.
- `TASK_TAKEOVER.md`: continuidad cruzada cuando un agente pierde tokens, rate limit, contexto o disponibilidad.
- `current-handoff.yaml`: estado vivo del lease, owners, bloqueos y siguiente accion.
- `HANDOFF_CLAUDE_2026-07-10.md`: resumen 3P listo para entregar a Claude.
- `HANDOFF_TABLERO_V8_CODEX_2026-07-10.md`: takeover, correcciones, pruebas y estado listo para deploy del tablero v8.

La operación comercial y de entrega a clientes se documenta en `../METODO_AT/README.md`. Orquestación define **quién y con qué controles**; Método AT define **qué proceso sigue cada servicio**.

## Fuentes de verdad del sistema

| Dominio | Fuente canonica | Papel del tablero |
|---|---|---|
| Clientes y Metodo AT | BD WordPress/OmniCliente | Vista agregada y acciones autorizadas |
| Trabajo interno | GitHub Issues/PRs o ledger equivalente | Vista Kanban |
| Turno Claude/Codex | Registro de lease compartido | Indicador y control de relevo |
| Decisiones | Eventos/resumenes ligados al ticket | Historial auditable |

El tablero no debe guardar una segunda verdad permanente en `localStorage`. Puede usar cache local, pero debe mostrar origen, version y ultima sincronizacion.

## Registro de eventos

Registrar decisiones y transiciones; no copiar conversaciones completas. Un evento minimo contiene:

```yaml
event_id: AT-EVT-0001
ticket_id: AT-INT-0001
timestamp: 2026-07-10T12:00:00-04:00
actor: codex
action: moved_to_review
summary: Implementacion terminada y pruebas adjuntas.
evidence_ref: PR-123
```

## Modo transitorio

Mientras el tablero o GitHub no persistan el lease, la instruccion explicita mas reciente de Luis define el orquestador activo. El agente debe declarar el turno al iniciar y entregar un handoff al terminar. Nunca debe asumir que otro chat recibio el mensaje.
