# Tech WhatsApp reconoce a quien escribe: nuevo, prospecto o cliente — diseño

**Fecha:** 28-oct-2026 · **Pedido:** Luis · **Diseño:** Eje · **Rama:** `claude/wa-reconoce-cliente`

## 1. Para qué

Hoy el asistente de WhatsApp (flujo n8n «WhatsApp Tech - Principal (PROD)», `bBcNlFgBzQ0766Mq`) no sabe quién le escribe. Decide si una reunión es un diagnóstico o un seguimiento por lo que la persona dice, así que un cliente que escribe «quiero agendar» puede terminar con un diagnóstico (y, con CAPI, contado como lead nuevo), y un seguimiento nunca queda ligado a la propuesta o al proyecto del que se trata.

Luis lo pidió así (28-oct): el asistente debe saber, por el número, si es **un cliente nuevo**, **un prospecto** («ya tuvimos una primera reunión y una segunda reunión de seguimiento de esa propuesta») o **un cliente real** («ya firmó»). Si un prospecto tiene varias propuestas en curso, debe preguntar a cuál quiere agendar el seguimiento; si un cliente tiene varios proyectos, a cuál proyecto. Y debe **saludarlo por su nombre y mencionar su propuesta o su proyecto**.

**Éxito:** un cliente con un proyecto escribe «quiero agendar» y el asistente le responde por su nombre, le ofrece el seguimiento de ese proyecto y la reunión queda guardada ligada a ese contrato y su plan de trabajo; un desconocido sigue recibiendo el diagnóstico gratis como hoy.

## 2. Las tres situaciones (regla de Luis)

| Situación | Regla | Qué se agenda |
|---|---|---|
| **Cliente real** | el número pertenece a una ficha con **al menos un contrato de trabajo firmado** (`wp_automatiza_contracts.status = 'signed'`, de tipo `servicios`, `soporte` o `sla`) | seguimiento de **un proyecto** = un contrato firmado (y su plan de trabajo, si tiene) |
| **Prospecto** | **ya tuvimos una primera reunión** con él (regla de Luis): tiene una propuesta (cualquier estado salvo `borrador`/`error`) o un diagnóstico ya realizado, y **sin contrato de trabajo firmado** | seguimiento de **una propuesta abierta**; si no tiene ninguna abierta, seguimiento general |
| **Nuevo** | el número no aparece, o aparece sin ninguna reunión hecha (solo un diagnóstico futuro, cancelado o al que no llegó) | diagnóstico gratis, igual que hoy; si tiene un diagnóstico futuro, el asistente se lo recuerda y ofrece reprogramarlo |

- **Propuesta abierta**: enviada y sin cierre: `wp_automatiza_propuestas.status` en `sent` o `evaluando`, los mismos que ya usa `at/v1/propuesta-contexto` (`cierre-cliente/whatsapp.php`). `pending` es anterior al envío y no cuenta; tampoco `aceptada` (ya pasó a contrato), `rechazada`, `archivada`, `borrador`, `lista`, `ajustando`, `generando` ni `error`.
- **Proyecto** *(Eje, por la regla de Luis «cliente real = firmó un contrato»)*: cada contrato firmado de tipo `servicios`, `soporte` o `sla` que no esté `cancelled`. Al firmarse uno de `servicios` se crea su plan de trabajo (`wp_automatiza_planes_trabajo`, con `codigo` de 12 caracteres); el nombre que se le muestra a la persona es el del plan si existe y, si no, el título/servicio del contrato.
- *(Eje)* Un `nda` o un `handover` firmados, solos, no hacen cliente real ni son proyecto. Un contrato que después quedó `expired` sigue contando para «cliente real» (ya firmó con nosotros), pero no aparece como proyecto para agendar.
- *(Eje)* «Diagnóstico ya realizado» = una fila de `wp_automatiza_leads` cuya fecha ya pasó y que no está cancelada ni marcada como inasistencia; el constructor confirma en el código qué valores de `status` usa la tabla para eso.
- Si el número está en un cliente real y también tiene propuestas abiertas, manda **cliente real**, y la lista de opciones suma sus proyectos y sus propuestas abiertas.

## 3. Cómo conversa

Antes de que el agente responda, el flujo consulta quién es (sección 4) y le pasa una línea de contexto interno, igual que ya hace con las propuestas pendientes (nodo «Sumar Contexto»): el agente la usa y nunca la repite.

- **Nuevo:** sin cambios. Diagnóstico gratis → calendario (`<<ACTION:SHOW_CALENDAR>>`) → nombre y apellido y correo validados por «Check Email Booking».
- **Prospecto, una propuesta abierta:** «Hola María 👋 ¿Quieres agendar una reunión para seguir afinando la propuesta para Spa Aurora?». No pide nombre ni correo si la ficha los tiene.
- **Prospecto, varias:** «Hola María 👋 ¿Sobre cuál propuesta quieres conversar: Sitio web Spa Aurora o Agenda y recordatorios?».
- **Cliente real, un proyecto:** «Hola Julio 👋 ¿Agendamos el seguimiento del proyecto Configurador Vitolio?».
- **Cliente real, varios:** pregunta cuál, con los nombres de cada uno.
- **Mensaje desde el botón de WhatsApp de la lámina del plan o del estado:** ese texto ya trae «(código XXXXXXXXXXXX)»; si el código es de un plan de ese mismo número, se toma ese proyecto sin preguntar.
- La elección se hace con **botones** de WhatsApp (respuesta rápida) cuando hay de 2 a 3 opciones y con una lista cuando hay más; elegir lleva al calendario de seguimiento que ya existe (`<<ACTION:FOLLOWUP_MEETING>>`).
- Si la persona dice explícitamente que quiere un diagnóstico nuevo (por ejemplo, para otro negocio), se le agenda el diagnóstico como hoy.

## 4. Consulta de quién es

- **Ruta nueva** en WordPress, solo para n8n: `GET /wp-json/automatiza-tech/v1/wa-contexto?phone=…`, con `permission_callback` = `at_cc_rest_auth` (la misma autenticación de las rutas que n8n ya usa en el cierre de cliente). Sin la clave, 401.
- **Coincidencia por teléfono:** los últimos 9 dígitos, sin espacios ni signos, como hoy `automatiza_tech_verify_client_by_phone` (01-oct-2026). Se busca en `wp_crm_clientes.telefono`, `wp_automatiza_tech_clients.phone` (donde el cierre de cliente también lo guarda), `wp_automatiza_propuestas.phone` y `wp_automatiza_leads.phone`. Todo se lleva a la ficha única del CRM: por `crm_cliente_id` desde la ficha operativa (los contratos traen `client_id` = ficha operativa y `proposal_id`) y por correo desde las propuestas y los leads (`at_cc_crm_de_email`, que ya existe). «Dos fichas distintas» se cuenta después de ese cruce.
- **Respuesta:** `situacion` (`nuevo` | `prospecto` | `cliente`), `nombre` (solo el primer nombre, para el saludo), `opciones`: lista de `{tipo: 'proyecto'|'propuesta', id, nombre, plan_codigo?}`, y `agenda`: `{nombre_completo, correo, empresa}` de la ficha, **solo para que n8n agende** (la reunión de seguimiento exige correo válido y Google Calendar invita a ese correo). Ni montos ni enlaces.
- **El correo no entra a la conversación** *(Eje)*: el flujo pasa `agenda` directo al sub-workflow de agenda y nunca a la línea de contexto del agente. Si la ficha no tiene correo, el asistente se lo pide a la persona y se valida con `validacion.js` del Cerebro AT, como en el diagnóstico. El tope de 2 reuniones activas por correo (`automatiza_tech_limite_reuniones_activas`) sigue aplicando con ese correo.
- **Llamadores de `verify-client` que se migran:** el nodo «Verify Client» del flujo de WhatsApp y el nodo «Get Client Data» del sub-workflow de agenda (`PejBgr2LZQSAbbBy`). Antes de retirar la ruta vieja se revisan todos los flujos de n8n buscando la URL.
- **Convive con `at/v1/propuesta-contexto`** *(Eje)*: esa ruta sigue igual, con sus efectos (nota en la propuesta y aviso a Luis con freno de 30 minutos) y su regla de no dar por aceptada una propuesta por texto. «Sumar Contexto» arma **una sola** línea interna con las dos fuentes: la situación, el nombre y las opciones de `wa-contexto`, más el aviso de propuesta pendiente si lo hay. No se escriben dos líneas que puedan contradecirse.
- **Número compartido** *(Eje)*: si el número calza con dos o más fichas del CRM distintas, `situacion` = la más alta, pero `nombre` va vacío y las opciones se limitan a las de esas fichas; el asistente no saluda por nombre y pregunta a cuál se refiere.
- La ruta reemplaza a `verify-client` en el flujo de WhatsApp: el paso de seguimiento («Verify Client» → «Is Client?») usa la respuesta nueva. `verify-client` se retira cuando ningún flujo la llame (comprobarlo en todos los flujos de n8n antes de retirarla).

## 5. La reunión queda ligada

- `wp_automatiza_followup_meetings` suma dos columnas, con migración idempotente (`SHOW COLUMNS` + `ALTER TABLE`, como `at_pt_migrar_esquema`): `contrato_id BIGINT UNSIGNED NULL` y `propuesta_id BIGINT UNSIGNED NULL`, con índice cada una.
- `POST /followup-meetings` acepta `contrato_id` y `propuesta_id`, y los guarda solo si pertenecen a ese mismo cliente (mismo número o misma ficha); si no, los ignora y deja la reunión sin vínculo (nunca falla la agenda por esto).
- Para un proyecto con plan, la nota sigue llevando «Plan de trabajo <código>» como hoy, para que la página del plan siga viendo la llamada (`at_pt_seguimiento_pendiente`).
- El sub-workflow de agenda (`PejBgr2LZQSAbbBy`) pasa los dos campos hasta «Save Appointment to WP».
- En la ficha del CRM y en la lista de reuniones de seguimiento, la reunión muestra a qué propuesta o proyecto pertenece.

## 6. Privacidad

- El asistente solo nombra la propuesta o el proyecto del propio número; nunca datos de otra ficha. La línea de contexto lleva solo primer nombre y nombres de propuestas o proyectos.
- La consulta exige la clave de n8n y no devuelve correos, teléfonos, montos ni enlaces.
- El repositorio es público: ningún dato real de clientes en pruebas ni documentación.

## 7. Fuera de esta etapa

Instagram (allí no llega el número al inicio) y el chat de la web (agenda por sus formularios); reconocer clientes por correo; cambiar los recordatorios y plantillas existentes.

## 8. Pruebas

- **Puras/WordPress local:** la regla de las tres situaciones (con y sin contrato firmado, propuesta abierta/cerrada, número en dos fichas, número desconocido, formatos de teléfono `+56 9…`, `569…`, `9…`); la ruta exige la clave (401 sin ella) y no devuelve correo ni teléfono; `POST /followup-meetings` guarda `contrato_id`/`propuesta_id` solo si son del mismo cliente; la migración corre dos veces sin error.
- **n8n:** el nodo que arma el contexto y los botones de elección, con datos simulados (sin enviar WhatsApp).
- **Real, con Luis:** su número cargado en una ficha de prueba como prospecto y luego como cliente con dos proyectos; agenda y cancela.

## 9. Despliegue

Solo con el OK de Luis. **De dónde se parte:** de los flujos vivos de n8n (exportados por la API en el momento, nunca de `N8N/PROD/WhatsApp_Tech_Principal.json`, que está desactualizado) y de la rama `claude/cerebro-at`, que trae `N8N/cerebro-at/` (el cerebro, la validación y `sincronizar.py`, ya en PROD); esa rama se mergea a `main` antes, o esta rama parte de ella. Los cambios a los prompts de WhatsApp se hacen en `N8N/cerebro-at/` y se suben con `sincronizar.py`; los nodos nuevos, con un script del mismo estilo (respaldo, PUT con settings filtrados, republicar, releer). Orden: migración y ruta en WordPress (por SSH, respaldo, `php -l`, md5 contra PROD) → sub-workflow de agenda → flujo de WhatsApp (respaldo, PUT con settings filtrados y republicar, como `N8N/cerebro-at/sincronizar.py`). Vuelta atrás: los flujos se restauran desde el respaldo; las columnas nuevas pueden quedarse (nulas, nadie las exige).

## 10. Lo que queda al constructor

- Qué nombre mostrar exactamente para una propuesta (título, servicio o empresa) y para un contrato sin plan, siempre corto y reconocible para la persona.
- El texto de los botones y de la lista (WhatsApp limita títulos a 20 caracteres en botones y 24 en listas).
- Cómo se cruzan `wp_automatiza_contracts.client_id` (ficha de `automatiza_tech_clients`) con la ficha única del CRM (`at_cc_techs_de_crm`, `crm_cliente_id`).
- Dónde exactamente se ve el vínculo en la ficha del CRM.
- Antes de construir, contar en PROD (solo lectura, por SSH) cuántos clientes con contrato firmado y cuántos prospectos con propuesta abierta **no tienen teléfono** en ninguna de las cuatro tablas: esos los clasificaría como «nuevo». Si son varios, Luis los completa en el CRM antes de encender esto.
