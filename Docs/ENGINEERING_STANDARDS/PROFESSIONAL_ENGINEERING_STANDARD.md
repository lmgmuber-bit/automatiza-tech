# Estándar profesional de ingeniería y entrega

Versión: 1.0  
Fecha: 2026-07-10  
Autoridad: Luis Miguel  
Ámbito: AutomatizaTech, clientes y proyectos futuros

## Propósito

AutomatizaTech es una empresa pequeña con disciplina de empresa tecnológica madura. Este estándar adopta mecanismos comunes de organizaciones de alto desempeño —ownership claro, decisiones escritas, automatización de calidad, seguridad, revisión y foco en el cliente— sin copiar burocracia innecesaria.

Todos los agentes y colaboradores aplican esta metodología aunque el proyecto no tenga reglas específicas. Las instrucciones particulares de cada repositorio complementan este estándar con stack, comandos, arquitectura y restricciones propias.

## Orden de precedencia

1. Seguridad, legalidad y autorización explícita de Luis.
2. Instrucción actual y concreta del usuario.
3. `AGENTS.md` y reglas específicas del proyecto.
4. Arquitectura, ADR, contratos y documentación vigente del proyecto.
5. Este estándar profesional transversal.

Una regla específica puede cambiar una tecnología o comando; no elimina calidad, seguridad, evidencia ni trazabilidad salvo decisión explícita y registrada.

## Principios no negociables

1. **Resultado del cliente:** comprender qué problema se resuelve y cómo se medirá el éxito.
2. **Ownership visible:** cada tarea tiene un owner activo, un revisor cuando corresponda y una siguiente acción.
3. **Una fuente de verdad:** tickets, documentación, vault, Graphify y Git deben converger; el chat no es memoria permanente.
4. **Trabajo escrito:** decisiones, supuestos, riesgos y criterios de aceptación quedan registrados.
5. **Calidad incorporada:** seguridad, pruebas y observabilidad se diseñan desde el inicio.
6. **Simplicidad deliberada:** usar la solución más simple que cumpla requisitos y permita evolucionar.
7. **Evidencia sobre opinión:** ningún “listo” sin diff, prueba, captura, log sanitizado o criterio verificable.
8. **Reversibilidad:** cambios riesgosos incluyen migración, compatibilidad y rollback.
9. **No heroísmo:** evitar dependencias de una sola persona, sesión o agente; dejar handoff reproducible.
10. **Mejora continua:** incidentes y errores generan aprendizaje, automatización o actualización documental.

## Flujo profesional obligatorio

### 1. Intake y entendimiento

- Confirmar objetivo, usuario/cliente afectado y resultado esperado.
- Distinguir síntoma, causa probable y alcance autorizado.
- Consultar reglas, memoria, Graphify, estado Git y documentación relevante.
- Declarar supuestos; no inventar endpoints, datos, credenciales ni estado externo.

### 2. Definition of Ready

Una tarea está lista para ejecutar cuando tiene:

- ID y título claro.
- Objetivo de negocio/técnico.
- Owner y, si aplica, revisor.
- Prioridad, complejidad, riesgo, incertidumbre y reversibilidad.
- Archivos o componentes permitidos, prohibidos y bloqueados.
- Dependencias y `base_commit`.
- Criterios de aceptación y pruebas esperadas.
- Permisos explícitos para acciones externas o irreversibles.

Si falta información no crítica, avanzar con un supuesto documentado. Si cambia materialmente el resultado o la autorización, escalar a Luis.

### 3. Plan y routing

- Dividir trabajos grandes en unidades cerrables y verificables.
- Seleccionar perfil/modelo mediante `Docs/ORCHESTRATION/model-routing.yaml`.
- Separar implementación y revisión para riesgo alto/crítico.
- Definir plan de rollback antes de una acción difícil de revertir.
- Registrar decisiones arquitectónicas relevantes en un ADR o nota equivalente.

### 4. Preparación de Git y ownership

- Verificar rama, worktree, cambios ajenos y locks.
- Crear o usar una rama con nombre descriptivo según reglas del proyecto.
- No descartar, mover ni incluir cambios ajenos sin autorización.
- No usar `git add .` ni commits generales en un worktree sucio sin revisar cada archivo.
- Aplicar `TASK_TAKEOVER.md` si cambia el owner.

### 5. Implementación

- Mantener cambios pequeños, cohesivos y alineados al alcance.
- Seguir convenciones, patrones y estructura existentes antes de introducir abstracciones nuevas.
- Nombres claros, funciones con una responsabilidad y errores explícitos.
- Validar entradas y escapar salidas según contexto.
- Evitar código muerto, duplicación accidental, dependencias innecesarias y comentarios decorativos.
- Preservar compatibilidad o documentar la ruptura y migración.

### 6. Verificación

- Ejecutar el gate definido en `quality-gates.yaml`.
- Probar primero el camino modificado y luego regresiones proporcionales al impacto.
- Validar casos felices, errores, bordes y permisos.
- No ocultar pruebas fallidas ni declarar éxito parcial como total.
- Si una prueba no puede ejecutarse, explicar por qué, el riesgo y cómo debe validarse.

### 7. Revisión

- Revisar diff completo, no solo archivos recordados.
- Verificar seguridad, datos, concurrencia, compatibilidad, performance y mantenibilidad según riesgo.
- El autor no es el único aprobador de un cambio C3.
- Las observaciones incluyen prioridad, evidencia, archivo y acción requerida.
- Resolver o aceptar explícitamente riesgos antes del cierre.

### 8. Entrega

- Preparar PR o reporte con qué cambió, por qué, pruebas, riesgos, rollback y evidencia.
- Commits pequeños y coherentes; asunto imperativo y trazable al ticket.
- No mezclar refactors no relacionados con una corrección urgente.
- No hacer push, merge, deploy ni comunicación al cliente sin autorización cuando el proyecto la requiera.

### 9. Cierre y memoria

- Confirmar criterios de aceptación uno por uno.
- Registrar resultado, evidencias, riesgos residuales y siguiente acción.
- Actualizar documentación, vault y Graphify cuando cambie contexto compartido.
- Cerrar o reasignar el ticket; ninguna tarea queda “en progreso” sin owner o próximo paso.
- Capturar aprendizaje si hubo incidente, retrabajo o takeover.

## Definition of Done

Una tarea solo está terminada cuando:

- El resultado solicitado existe y fue verificado.
- Los criterios de aceptación están cumplidos o las excepciones fueron aprobadas.
- Pruebas aplicables pasan.
- Seguridad y privacidad fueron revisadas proporcionalmente.
- El diff no contiene cambios accidentales ni secretos.
- Migración, configuración, rollback y deploy están documentados cuando aplican.
- Documentación, memoria y Graphify están actualizados cuando corresponde.
- El reporte permite que otro profesional continúe sin depender del chat original.

## Arquitectura y diseño

- Favorecer módulos con límites claros y dependencias explícitas.
- Usar contratos de API y datos versionables.
- Evitar acoplar UI, negocio, persistencia e integraciones sin necesidad.
- Preferir cambios evolutivos sobre reescrituras masivas sin evidencia.
- Registrar decisiones costosas o difíciles de revertir.
- Diseñar degradación segura para servicios externos: timeout, retry limitado, idempotencia y manejo de fallos cuando apliquen.
- No optimizar prematuramente; medir antes y después de cambios de performance.

## Testing y CI/CD

- C0: verificación focalizada del resultado y diff.
- C1: pruebas del comportamiento modificado y lint/build aplicable.
- C2: unitarias/integración, regresión relevante y revisión independiente recomendada.
- C3: plan de validación, pruebas negativas, seguridad, rollback, revisor independiente y gate humano.
- CI debe ser reproducible y no depender de secretos locales no documentados.
- Un bypass de QA solo puede ser temporal, limitado, autorizado y nunca quedar habilitado silenciosamente en producción.
- Un pipeline rojo no se ignora; se corrige o se acepta el riesgo por escrito.

## Git, commits y PR

- Una rama por objetivo coherente.
- Commits atómicos, revisables y con intención clara.
- Formato recomendado: `tipo(scope): resultado`, por ejemplo `fix(tablero): escape editable attributes`.
- El PR referencia ticket, clase/riesgo, impacto cliente, pruebas, rollback, documentación y riesgos.
- Revisar archivos staged antes del commit.
- No reescribir historia compartida, forzar push o mezclar ramas sin autorización.
- Merge solo con gates cumplidos y decisión del responsable autorizado.

## Seguridad y privacidad

- Mínimo privilegio y deny-by-default.
- Secretos fuera del código, prompts, logs, screenshots, tickets y vault.
- PII y datos comerciales solo donde sean necesarios, con acceso controlado.
- Autenticación no sustituye autorización; validar ownership/capabilities.
- Sanitizar entrada, escapar salida y usar queries parametrizadas.
- Dependencias externas se fijan, revisan y actualizan conscientemente.
- Logs sanitizados, útiles y con retención proporcional.
- Acciones destructivas exigen confirmación, alcance verificado y rollback/respaldo.

## Confiabilidad y observabilidad

- Errores accionables, sin fallos silenciosos.
- Health checks, logs, métricas o trazas según criticidad.
- Identificadores de correlación para flujos distribuidos cuando apliquen.
- Retries limitados e idempotentes; nunca bucles infinitos de reintento.
- Documentar dependencia externa, timeout y comportamiento degradado.
- Tras un incidente: restaurar servicio, preservar evidencia, identificar causa y prevenir recurrencia.

## Profesionalismo hacia clientes

- Comunicar alcance, supuestos, fechas, dependencias y bloqueos sin ocultar incertidumbre.
- No prometer lo que no fue verificado.
- Demostrar avances con entregables y evidencia, no solo actividad.
- Separar solicitud original de cambios de alcance posteriores.
- Proteger datos, marca y continuidad operacional del cliente.
- Entregar instrucciones de uso, soporte, rollback y próximos pasos cuando corresponda.
- Lenguaje claro, respetuoso y orientado a decisiones.

## Conducta obligatoria de agentes

- Leer contexto antes de actuar y verificar hechos inestables.
- Explicar supuestos y límites reales de herramientas/modelos.
- No afirmar comunicación con otro agente sin canal técnico real.
- Preservar trabajo ajeno y coordinar ownership/takeover.
- Seleccionar el modelo por tarea y registrar la decisión.
- No ampliar materialmente el alcance sin autorización.
- Informar bloqueos temprano junto con alternativas.
- Continuar hasta cerrar el objetivo cuando existe una acción segura y autorizada.

## Prohibiciones

- Secretos o datos sensibles en repositorio, chat, logs o documentación.
- Deploy o cambios productivos sin gate y autorización.
- “Funciona en mi máquina” sin evidencia reproducible.
- Commits masivos con trabajo de distintos tickets/agentes.
- Ocultar errores, pruebas fallidas, deuda o riesgos.
- Duplicar fuentes de verdad.
- Cambiar arquitectura silenciosamente.
- Marcar completo por falta de tokens o tiempo.
- Burocracia sin valor: el rigor aumenta con el riesgo.

## Métricas de salud

Medir tendencias, no castigar individuos:

- Tiempo desde READY hasta DONE.
- Porcentaje de tareas reabiertas.
- Defectos escapados a producción.
- Deploys fallidos y tiempo de recuperación.
- Cobertura de criterios de aceptación.
- Retrabajo por contexto incompleto.
- Incidentes por secretos, permisos o datos.
- Satisfacción y aceptación del cliente.

## Reporte mínimo de cierre

```text
Resultado: <qué quedó resuelto>
Ticket / clase / riesgo: <id, C0-C3, nivel>
Cambios: <archivos o componentes>
Pruebas y evidencia: <comandos, resultados, enlaces>
Seguridad/rollback: <estado o n/a fundamentado>
Documentación/memoria/Graphify: <actualizado o n/a>
Riesgos residuales: <ninguno o lista>
Estado Git y siguiente acción: <rama, commit/PR, owner>
```
