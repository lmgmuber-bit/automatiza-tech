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

## Ronda 2: correcciones de la revisión B de Codex (28-oct)

Informe de Codex: `Shared-Tools/revisiones/2026-10-28-wa-reconoce-B-codex.md`, con dictamen CHANGES_REQUIRED.

**Revisión exacta a revisar:** los commits `26d9e5a..90ccbd2`, sobre `3fc924e`. Se ve con:

```bash
git diff 3fc924e..90ccbd2
```

Las correcciones se hicieron en una sola pasada, cada una con una prueba que primero falló:

| Hallazgo | Corrección | Prueba (antes → después) |
|---|---|---|
| **B-01** | «Contexto Cliente», «Contexto Elegido» y «Verify Client» van con `onError=continueRegularOutput` (además de `neverError`). Un timeout o error de red llega como un ítem con `error`, sin línea de contexto. Termina en un seguimiento general, nunca en una oferta de diagnóstico. | La vista previa de `aplicar.py` falla con 3 ✗ y después queda OK. Además simula la consulta caída sobre «Sumar Contexto» y «Elegir Seguimiento», y `contexto.test.js` prueba la forma `{error}` y `no_disponible`. |
| **B-02** | Toda lectura pasa por `at_wa_q`/`at_wa_ext`: si MySQL deja `last_error`, el número queda `situacion: no_disponible` (nunca `nuevo` ni datos a medias). La ruta responde 503 sin SQL ni el error. El vínculo de la reunión se guarda en 0, y el texto de la lista queda vacío. | `tests/wa-contexto/fallos-wp-test.php` pasa de 8 fallas a 11 ok. Hace fallar cada una de las seis tablas con un `$wpdb` intermediario. |
| **B-03** | Un botón `seg_*` que ya no es opción nunca agenda otra cosa. Con 1 o más opciones vigentes avisa «Esa opción ya no está disponible.» y vuelve a preguntar (con 1 opción pide confirmarla); con 0, solo avisa. | `contexto.test.js`: 3 pruebas nuevas, primero rojas y después verdes. |
| **B-04** | «Save Reschedule Client» guarda `contrato_id` y `propuesta_id`, y GET `/followup-meetings/{id}` los devuelve. | La vista previa evalúa la cadena «Save Reschedule Client» → «Prepare Seguimiento Direct/Custom» con datos sintéticos (contrato 17, propuesta 5): primero falló, después pasa. `followup-vinculo-wp-test.php` pasa de 10/11 a 11/11. |
| **B-05** | `verificar_publicacion` exige: `activate` 200, relectura 200, mismo estado activo y `activeVersionId == versionId`. Además, en la `activeVersion`, los parámetros, el `onError` y las conexiones de cada nodo tocado tienen que ser los subidos (`contiene()` admite claves que agregue n8n, no valores distintos). Si algo no calza, se detiene y nombra el respaldo para volver atrás. | `N8N/wa-reconoce/aplicar_test.py` pasa de 8 errores a 8/8, con casos de `activate` rechazado, relectura fallida, `activeVersion` vieja con los mismos nombres, parámetros y conexiones viejos y flujo inactivo. |
| **B-06** | Cada título final queda reservado: un sufijo nunca choca con otro título. | `contexto.test.js`: el caso de Codex, en botones y en lista. |

**Decisión M:** el plan (Tarea 6) decía tratar un botón viejo como si no hubiera botón, y su foco 2 pedía volver a preguntar. Manda el foco.

**Suite después de la pasada:**
- `tests/wa-contexto`: 6/6 TODO OK;
- `contexto.test.js`: 24/24;
- `aplicar_test.py`: 8/8;
- vista previa: agenda 3 nodos y WhatsApp 17 (se sumó «Save Reschedule Client»), comprobaciones OK e idempotente.

Nada subido a n8n ni a PROD.

**Sigue NOT_RUN** (Eje no puede cerrarlo en esta máquina):
- El POST real y el GET de la reunión con el pegamento de `admin-followup-meetings.php` cargado. El WordPress local ejecuta la copia de PROD de ese archivo. Se verifica en el despliegue (Tarea 9, prueba real con Luis).
- `verify-client-wp-test.php` y `limite-demos-wp-test.php` contra los archivos de la rama.
- La vista en el navegador.
- La documentación vigente de Meta: se intentó y respondió 429.
- El rendimiento con volumen real.

## Ronda 3: correcciones de R2-01 y R2-05 (29-oct)

Informe de Codex: `Shared-Tools/revisiones/2026-10-28-wa-reconoce-B-codex-r2.md`. Quedaron cerrados B-02, B-03, B-04 y B-06; R2-01 y R2-05 seguían abiertos.

**Revisión exacta a revisar:** el commit `e5a8294`, sobre `5f37d9a`. Se ve con:

```bash
git diff 5f37d9a..e5a8294
```

No cambió ningún PHP.

| Hallazgo | Corrección | Prueba (antes → después) |
|---|---|---|
| **R2-01** | Ver el detalle debajo de la tabla. | `contexto.test.js` pasa de 4 fallas a 25/25. La vista previa pasa de no correr (faltaban los nodos) a OK, con el camino desde «Sumar Contexto», pasando por «Parse Response Actions» (código real) y «Route Action», hasta el freno: consulta caída → «Send Confirmar Tipo Reunión»; persona nueva, cliente o después de tocar «Primera reunión» → calendario. Se rompió a propósito el freno, la conexión del calendario, el aviso y la marca de «Sumar Contexto»: cada mutación se detecta (1, 4, 1 y 3 fallas). |
| **R2-05** | `nodos_tocados()` suma a los nodos nuevos y a los de parámetros cambiados los que cambian su `onError` o sus conexiones de salida. Con eso «Contexto Propuesta» y «Route Action» entran en la verificación de lo publicado y en la decisión de subir: WhatsApp pasa de 17 a 21 nodos. | `aplicar_test.py` pasa de 5 errores a 13/13. Cubre un cambio solo de conexión, uno solo de `onError` y el caso de Codex: una conexión vieja con los mismos parámetros ya no se acepta. |

**Detalle de R2-01.** La consulta caída ya no se confunde con una persona nueva:
- **`waReconocimiento()`** trata un timeout, un 503, una respuesta vacía o rara como `no_disponible`.
- **«Sumar Contexto»** guarda `reconocimiento`. La línea interna le dice al agente que no asuma que es nuevo y que pregunte si es la primera reunión o un seguimiento. Lo mismo dice la personalidad de WhatsApp.
- **El freno «¿Reconocimiento caído?»** se pone entre «Route Action» (calendario del diagnóstico) y «Get Appointments Config».
  - Si en ese mensaje la consulta se cayó, «Send Confirmar Tipo Reunión» pregunta con dos botones: «Primera reunión» (`btn_agendar_demo`) o «Es un seguimiento» (`seg_general`).
  - «Primera reunión» vuelve al agente por «Button to Text», que no pasa por «Sumar Contexto». Por eso el freno no se repite y no hay ciclo.
- **En el seguimiento,** sin datos no se promete una agenda: `sin_correo` con `motivo: no_disponible` avisa a la persona y a Luis.

**Decisión N:** reemplaza a la H, que con la ruta caída dejaba la línea igual que hoy. Costo si está mal: una pregunta de más cuando WordPress no responde.

**Suite:**
- `contexto.test.js`: 25/25;
- `aplicar_test.py`: 13/13;
- `validacion.test.js`: 49;
- vista previa: agenda 3 nodos y WhatsApp 21, comprobaciones OK;
- `sincronizar.py` (vista previa): solo la personalidad de WhatsApp.

Las `*-wp-test.php` no se repitieron porque el MySQL de WAMP local está detenido (iniciarlo pide permisos de administrador). Ningún PHP cambió desde `90ccbd2`, donde dieron 6/6.

Nada subido a n8n ni a PROD.

## Ronda 4: correcciones de R3-01 y R3-02 (29-oct)

Informe de Codex: `Shared-Tools/revisiones/2026-10-28-wa-reconoce-B-codex-r3.md`. R2-01 y R2-05 quedaron verificados.

**Revisión exacta a revisar:** el commit `cb9c7f5`, sobre `1f062c0`. Se ve con:

```bash
git diff 1f062c0..cb9c7f5
```

No cambió ningún PHP.

- **R3-01.** «Elegir Seguimiento» ya no devuelve `directo` sin un correo, en ninguna situación.
  - Un número que no está en ninguna ficha y toca «Es un seguimiento» sale por la coordinación con Luis (`sin_correo` con `motivo: sin_ficha`). A la persona se le dice «No encontré una propuesta ni un proyecto con este número para agendar el seguimiento. Ya le avisamos a Luis…».
  - El correo a Luis explica los tres motivos: `no_disponible`, `sin_ficha` y `sin_correo`.
  - Una persona nueva que tiene correo y dice ser seguimiento sí lo agenda.
  - Pruebas:
    - `contexto.test.js`: 2 pruebas nuevas, primero rojas y después verdes; queda en 27/27.
    - La vista previa simula el recorrido completo: consulta caída → «Es un seguimiento» → la consulta se recupera con `nuevo` sin correo → «Modo Seguimiento» → «Send Seguimiento Sin Correo».
    - La vista previa también evalúa el correo a Luis con los tres motivos: `sin_ficha` estaba rojo y ahora pasa.
    - Para evaluar textos con varias expresiones se agregó `evaluar_plantilla()`.
- **R3-02.** El README describe el comportamiento actual: si la consulta se cae, no se da por hecho que la persona es nueva, el calendario del diagnóstico se frena y el seguimiento sin correo nunca se abre. Se quitó la frase del seguimiento general automático.

**Suite:**
- `contexto.test.js`: 27/27;
- `aplicar_test.py`: 13/13;
- `validacion.test.js`: 49;
- vista previa: agenda 3 nodos y WhatsApp 21, comprobaciones OK.

Nada subido a n8n ni a PROD. Las `*-wp-test.php` siguen sin repetirse porque el MySQL de WAMP está detenido; ningún PHP cambió desde `90ccbd2`.
