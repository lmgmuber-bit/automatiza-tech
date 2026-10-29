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
  - si la ficha no tiene correo, avisa a la persona y a Luis.
- **La elección se guarda** en Redis `followup_resched_client_<teléfono>`, la misma clave que ya leen «Prepare Seguimiento Data Direct» y «Custom». Así el nombre, el correo, la nota «Plan de trabajo <código>» y el `contrato_id` o la `propuesta_id` llegan a la agenda.
- **Un botón `seg_c_<id>` o `seg_p_<id>`** entra por «Button Action» → «Contexto Elegido» → «Elegir Seguimiento».
- **«Send Not Client Message»** ofrece el diagnóstico gratis, ya no la «demo».

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
- los cambios sean idempotentes.

```bash
python -I -X utf8 N8N/wa-reconoce/aplicar.py --subir
```

Esta orden sube de verdad, y solo se corre con el OK de Luis:
1. respalda los dos flujos en `~/respaldos/n8n/wa-reconoce/<fecha>/`;
2. sube la agenda y después WhatsApp;
3. republica solo si estaban activos;
4. comprueba en la `activeVersion` que cada nodo tocado quedó publicado.

Antes de `--subir`, avisar a la sesión de Instagram, que también usa la agenda.

Pruebas:

```bash
node --test N8N/wa-reconoce/nodos/contexto.test.js
```

Las de WordPress están en `tests/wa-contexto/`. Las `*-wp-test.php` necesitan WAMP y `AT_WP_LOAD`.

## Vuelta atrás

- **n8n:** subir los `*.antes.json` del respaldo, con el mismo PUT de settings filtrados, y republicar.
- **WordPress:** restaurar `inc/admin-followup-meetings.php` y `mu-plugins/crm-ai-completo.php` del respaldo, y borrar `inc/wa-contexto/`. Las columnas `contrato_id` y `propuesta_id` pueden quedar: son nulas y nadie las exige.
