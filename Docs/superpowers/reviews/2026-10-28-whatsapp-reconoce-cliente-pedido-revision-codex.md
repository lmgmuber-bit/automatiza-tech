# Pedido de revisión B a Codex: Tech WhatsApp reconoce a quien escribe (28-oct-2026)

**Quién pide:** Eje (Claude Code, Opus 5.5), autor de las Tareas 1 a 8. **Quién revisa:** Codex, homólogo en la otra plataforma (método `Shared-Tools/at-agent-company/METODO.md`, checklist B). **Pedido de Luis:** «hazlo y le decimos a Codex que revise».

## Qué revisar

- **Rama:** `claude/wa-reconoce-cliente`. Worktree en la misma máquina: `C:/wamp64/www/automatiza-tech/.worktrees/wa-reconoce`.
- **Revisión exacta:** los commits `90e98f2..c7bbfa4` (Tareas 1 a 8), sobre `ef2277d` (el plan). La rama trae `main` mezclado en `0231861`.
- **Diff:**

  ```bash
  git diff ef2277d..c7bbfa4
  ```

  Son 19 archivos, casi todo nuevo.
- **Diseño (aprobado por Luis):** `Docs/superpowers/specs/2026-10-28-whatsapp-reconoce-cliente-design.md`.
- **Plan:** `Docs/superpowers/plans/2026-10-28-whatsapp-reconoce-cliente.md`. Están hechas las Tareas 1 a 8. La 9 (despliegue) y la 10 (retirar `verify-client`) esperan el OK de Luis. La 11 (pedir el correo por el chat) quedó diferida: hoy hay 0 casos.
- **Clase:** C2. Cambia el bot de WhatsApp que atiende a clientes reales y la tabla de reuniones de PROD. **Nada está desplegado.**

## Reglas para el revisor

- **Solo lectura.** No editar archivos de la rama, no hacer commits, no correr `aplicar.py --subir` ni `sincronizar.py --subir`, no tocar n8n ni PROD.
- **Repo público.** En los hallazgos no van datos de clientes ni detalles de vulnerabilidades explotables. Si aparece algo de ese tipo, va solo a la bóveda privada, y aquí queda una línea que apunte a ella.
- **Entrega:** los hallazgos se escriben en `C:/Users/luis_/Documents/Codex/Shared-Tools/revisiones/2026-10-28-wa-reconoce-B-codex.md`, fuera del repo, y Luis le avisa a Eje. Cada control de B lleva PASS, FAIL, NOT_RUN o NOT_APPLICABLE con evidencia. Cada hallazgo lleva prioridad (bloqueante, importante o menor), archivo:línea, evidencia y acción.

## Checklist A del autor (Eje)

1. **Objetivo y aceptación.**
   - El número lleva a nuevo, prospecto o cliente con la regla de Luis (§2 del diseño).
   - El asistente saluda por nombre y nombra el proyecto o la propuesta.
   - Con varias opciones pregunta con botones o lista.
   - La reunión queda con `contrato_id` o `propuesta_id` solo si son del mismo cliente.
   - El vínculo se ve en la lista de reuniones y en la ficha del CRM.
   - Fuera de esta etapa: Instagram, el chat web, el reconocimiento por correo y la Tarea 11.
2. **Alcance.**
   - Nuevo: `inc/wa-contexto/` (puras, datos, rest, followup, cargar) y `N8N/wa-reconoce/` (nodos/contexto.js con su prueba, aplicar.py y README).
   - Cambian:
     - `inc/admin-followup-meetings.php`: carga del módulo, una llamada en la API y la línea en la lista;
     - `mu-plugins/crm-ai-completo.php`: la línea en la ficha;
     - `N8N/cerebro-at/personalidad-whatsapp.md`: sección «QUIÉN TE ESCRIBE».
   - Pruebas: `tests/wa-contexto/`.
3. **Resultado.** Sigue el plan, con las decisiones A a L que están más abajo.
4. **Vigencia.** No hay dependencias nuevas. Los límites de WhatsApp (3 botones de 20 caracteres; listas de 10 filas con títulos de 24) se tomaron del diseño §10 y no se volvieron a verificar contra la documentación de Meta en esta tarea: queda NOT_RUN.
5. **Pruebas** (corridas el 28-oct):
   - `php tests/wa-contexto/puras-test.php` → 38 ok.
   - Con `AT_WP_LOAD=C:/wamp64/www/automatiza-tech/wp-load.php` y PHP `/c/wamp64/bin/php/php8.3.36/php.exe`:
     - `datos-wp-test.php` → 14 ok;
     - `rest-wp-test.php` → 7 ok;
     - `followup-vinculo-wp-test.php` → 10 ok;
     - `vinculo-texto-wp-test.php` → 7 ok.
   - `node --test N8N/wa-reconoce/nodos/contexto.test.js` → 19 de 19.
   - `node N8N/cerebro-at/validacion.test.js` → 49 OK, sin cambios.
   - `python -I -X utf8 N8N/wa-reconoce/aplicar.py` (vista previa contra los flujos vivos): agenda con 3 nodos, WhatsApp con 16, comprobaciones OK e idempotente. Usa la clave de n8n de `~/.claude.json` de Claude; si Codex no la tiene, es NOT_RUN para B.
   - `python -I -X utf8 N8N/cerebro-at/sincronizar.py` (vista previa): solo cambia la región de personalidad de WhatsApp, con 8 líneas agregadas.
   - Hay pruebas ajenas que fallan contra el espejo local, por la versión vieja de `admin-followup-meetings.php` del espejo (decisiones D y F): `tests/followup/verify-client-wp-test.php` (3 fallas de 401) y `limite-demos-wp-test.php`.
6. **Entrega.** Nada en PROD. El orden de despliegue y la vuelta atrás están en el plan (Tarea 9) y en `N8N/wa-reconoce/README.md`.

## Decisiones del autor (el revisor debe pesarlas)

- **A.** La migración y el vínculo viven en el módulo. `admin-followup-meetings.php` solo recibe el pegamento, porque el WordPress local ejecuta la copia de PROD de ese archivo y lo cambiado ahí no se puede probar.
- **B.** Las dos salidas visibles se verifican con una prueba estática y a la vista después del despliegue.
- **C.** Los scripts de superpowers buscan «Task N» y el plan usa «Tarea N».
- **D y F.** Las pruebas ajenas que fallan contra el espejo, explicadas en la checklist A.
- **E.** Ayudantes de prueba en `tests/wa-contexto/fixtures.php`.
- **G.** `node --test` se corre con la ruta del archivo (Node 24).
- **H.** Si `wa-contexto` falla o no aporta nada, el aviso de propuesta pendiente sale con el texto de hoy, idéntico.
- **I.** `waMensajeOpciones` se exporta.
- **J.** El texto viejo de «Send Not Client Message» se reemplaza desde `{{ $json.message }}`, que debe aparecer una sola vez.
- **K.** `neverError` en las consultas, y una respuesta sin `situacion` da un seguimiento general.
- **L.** «Button Action» tiene una salida de respaldo: la conexión nueva se inserta en la posición de la regla nueva.

El detalle está en `.superpowers/sdd/2026-10-28-whatsapp-reconoce-cliente/progress.md` del worktree (no versionado).

## Dónde mirar con más cuidado (foco de revisión del plan, más lo que Eje pide revisar)

1. **Caída de `wa-contexto`.** Nunca se le ofrece el diagnóstico a un cliente solo porque la consulta falló.
2. **Botón viejo.** Un id que ya no es opción vuelve a preguntar.
3. **Números extranjeros** (+58, +1). Calzan por los últimos 9 dígitos.
4. **Títulos repetidos al recortar.** Se distinguen.
5. **Número en dos fichas.** No hay nombre, cada opción lleva su agenda y el vínculo solo se acepta para esas fichas.
6. **Seguridad y privacidad.**
   - La ruta exige la clave.
   - Fuera de la agenda no salen correos, el número, enlaces ni montos.
   - La línea de contexto para el agente no lleva correo.
   - `at_wa_vinculo_valido` no se puede engañar mandando el id de otro cliente.
7. **n8n.**
   - La reutilización de la clave Redis `followup_resched_client_<tel>` (la del reagendamiento) no rompe el reagendamiento.
   - «Prepare Followup Data» sigue sirviendo a «Generate Days».
   - El camino del diagnóstico de `PejBgr2LZQSAbbBy` (que usa Instagram) queda idéntico.
   - Las envolturas de «Sumar Contexto» y «Elegir Seguimiento» usan `$('…').isExecuted` correctamente en los dos caminos (texto y botón).
8. **Rendimiento.** `at_wa_fichas_por_telefono` hace búsquedas con `RIGHT(REPLACE(...))` sobre cuatro tablas en cada mensaje. Hoy son tablas chicas; ¿vale para el volumen esperado?
