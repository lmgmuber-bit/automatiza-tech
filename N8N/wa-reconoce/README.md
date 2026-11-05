# Tech WhatsApp reconoce a quien escribe

Diseño: `Docs/superpowers/specs/2026-10-28-whatsapp-reconoce-cliente-design.md`. Plan: `Docs/superpowers/plans/2026-10-28-whatsapp-reconoce-cliente.md`.

El asistente de WhatsApp sabe, por el número, si le escribe una **persona nueva**, un **prospecto** (ya tuvo una reunión con nosotros) o un **cliente** (firmó un contrato de trabajo).
- **Al responder,** saluda por el nombre y nombra la propuesta o el proyecto.
- **Al agendar un seguimiento,** si hay varios, pregunta cuál con botones.
- **La reunión queda guardada** ligada a ese contrato o a esa propuesta.

## Piezas

| Pieza | Dónde |
|---|---|
| **Regla y datos:** situación, opciones y vínculo de la reunión | `wp-content/themes/automatiza-tech/inc/wa-contexto/` |
| **Ruta solo para n8n** (clave `X-AT-Secret`) | `GET /wp-json/automatiza-tech/v1/wa-contexto?phone=…&texto=…` |
| **Lógica de los nodos nuevos** (línea de contexto y elección), con pruebas | `N8N/wa-reconoce/nodos/contexto.js` |
| **Lo que el agente hace con la línea** | `N8N/cerebro-at/personalidad-whatsapp.md` (sección «QUIÉN TE ESCRIBE»), que se sube con `sincronizar.py` |
| **Script que aplica los cambios en n8n** | `N8N/wa-reconoce/aplicar.py` |

## Qué cambia en n8n

**WhatsApp principal** (`bBcNlFgBzQ0766Mq`):
- **«Contexto Cliente»** consulta la ruta antes del agente. «Sumar Contexto» arma **una sola** línea interna con quién escribe y la propuesta pendiente.
- **«Verify Client»** consulta la ruta nueva, y «Is Client?» sigue al seguimiento salvo que la persona sea nueva.
- **«Elegir Seguimiento»** y «Modo Seguimiento» resuelven el camino:
  - con una opción, va directo;
  - con varias, pregunta con botones (2 o 3) o con una lista (4 o más, hasta 10);
  - sin un correo para agendar (la ficha no lo tiene, el número no está en ninguna ficha o no pudimos revisar los datos), no abre el calendario: avisa a la persona y le manda a Luis un correo que dice el motivo.
- **La elección se guarda** en Redis `followup_resched_client_<teléfono>`, la misma clave que ya leen «Prepare Seguimiento Data Direct» y «Custom». Así el nombre, el correo, la nota «Plan de trabajo <código>» y el `contrato_id` o la `propuesta_id` llegan a la agenda.
- **Un botón `seg_c_<id>` o `seg_p_<id>`** entra por «Button Action» → «Contexto Elegido» → «Elegir Seguimiento».
- **«Send Not Client Message»** ofrece el diagnóstico gratis, ya no la «demo».
- **Un botón viejo** (la opción ya no existe) avisa y vuelve a preguntar; nunca agenda otra opción.
- **Si la consulta de quién escribe se cae,** el flujo sigue, pero nunca da por hecho que la persona es nueva (revisión B de Codex).
  - **El flujo no se corta.** Los tres nodos de consulta van con `onError=continueRegularOutput` y `neverError`: un timeout llega como `error`, y un error de la base como `situacion: no_disponible` (la ruta responde 503).
  - **El agente lo sabe.** «Sumar Contexto» deja `reconocimiento: no_disponible`, y la línea interna le dice al agente que no asuma que es nuevo y que pregunte si es la primera reunión o un seguimiento.
  - **Freno antes del calendario del diagnóstico.** Si en ese mismo mensaje el agente igual responde con el calendario, «¿Reconocimiento caído?» lo frena y «Send Confirmar Tipo Reunión» pregunta con dos botones: «Primera reunión» (`btn_agendar_demo`, que vuelve al agente por «Button to Text» sin pasar otra vez por el freno) o «Es un seguimiento» (`seg_general`).
  - **En el seguimiento, nunca se abre un calendario sin correo.** Si la consulta sigue caída, o se recupera y dice que el número no está en ninguna ficha, se avisa a la persona y a Luis.
- **Al reagendar,** «Save Reschedule Client» guarda también `contrato_id` y `propuesta_id`. GET `/followup-meetings/{id}` ahora los devuelve, y la reunión nueva queda con el mismo vínculo.

**Agenda** (`PejBgr2LZQSAbbBy`):
- **«Get Client Data»** consulta la ruta nueva.
- **«Merge Client Data»** toma el correo, el nombre y la empresa de su agenda.
- **«Prepare Payload»** manda `contrato_id` y `propuesta_id` en el seguimiento. El diagnóstico no cambia; Instagram lo usa.

## Cómo se corre

```bash
python -I -X utf8 N8N/wa-reconoce/aplicar.py
```

Ese comando es la vista previa: hace el GET de los dos flujos, aplica los cambios en memoria y corre las comprobaciones, sin subir nada. Revisa que:
- toda conexión vaya a un nodo que existe;
- todo `$('…')` lea un nodo que existe;
- «Button Action» tenga la regla nueva;
- «Is Client?» salga a «Elegir Seguimiento»;
- «Prepare Followup Data» ya no lea «Route Action»;
- el bloque de los nodos sea idéntico al de `contexto.js` y pase `node --check`;
- el diagnóstico de la agenda no cambie;
- los cambios sean idempotentes;
- los nodos de consulta sigan ante un error, simulando una consulta caída sobre «Sumar Contexto» y «Elegir Seguimiento»;
- un botón viejo vuelva a preguntar;
- el reagendamiento conserve el vínculo, evaluando las expresiones de «Save Reschedule Client» y «Prepare Seguimiento …» con datos sintéticos.

```bash
python -I -X utf8 N8N/wa-reconoce/aplicar.py --subir
```

Esta orden sube de verdad, y solo se corre con el OK de Luis:
1. respalda los dos flujos en `~/respaldos/n8n/wa-reconoce/<fecha>/`;
2. sube la agenda y después WhatsApp;
3. republica solo si estaban activos;
4. exige que `activate` y la relectura respondan 200, que `activeVersionId` sea la versión subida y que los parámetros, el `onError` y las conexiones de cada nodo tocado en la `activeVersion` sean los subidos. Si algo no calza, se detiene y dice qué respaldo subir para volver atrás (`verificar_publicacion`, probada sin n8n con `aplicar_test.py`).

Antes de `--subir`, avisar a la sesión de Instagram, que también usa la agenda.

Pruebas:

```bash
node --test N8N/wa-reconoce/nodos/contexto.test.js
```

```bash
python -I -B -X utf8 N8N/wa-reconoce/aplicar_test.py
```

Las de WordPress están en `tests/wa-contexto/`. Las `*-wp-test.php` necesitan WAMP y `AT_WP_LOAD`.

## Vuelta atrás

- **n8n:** subir los `*.antes.json` del respaldo, con el mismo PUT de settings filtrados, y republicar.
- **WordPress:** restaurar `inc/admin-followup-meetings.php` y `mu-plugins/crm-ai-completo.php` del respaldo, y borrar `inc/wa-contexto/`. Las columnas `contrato_id` y `propuesta_id` pueden quedar: son nulas y nadie las exige.
