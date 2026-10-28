# Tech WhatsApp reconoce a quien escribe: nuevo, prospecto o cliente — diseño

**Fecha:** 28-oct-2026 · **Pedido:** Luis · **Diseño:** Eje · **Rama:** `claude/wa-reconoce-cliente`

## 1. Para qué

Hoy el asistente de WhatsApp (flujo n8n «WhatsApp Tech - Principal (PROD)», `bBcNlFgBzQ0766Mq`) no sabe quién le escribe. Decide si una reunión es un diagnóstico o un seguimiento por lo que la persona dice, así que un cliente que escribe «quiero agendar» puede terminar con un diagnóstico (y, con CAPI, contado como lead nuevo), y un seguimiento nunca queda ligado a la propuesta o al proyecto del que se trata.

Luis lo pidió así (28-oct): el asistente debe saber, por el número, si es **un cliente nuevo**, **un prospecto** («ya tuvimos una primera reunión y una segunda reunión de seguimiento de esa propuesta») o **un cliente real** («ya firmó»). Si un prospecto tiene varias propuestas en curso, debe preguntar a cuál quiere agendar el seguimiento; si un cliente tiene varios proyectos, a cuál proyecto. Y debe **saludarlo por su nombre y mencionar su propuesta o su proyecto**.

**Éxito:** un cliente con un proyecto escribe «quiero agendar» y el asistente le responde por su nombre, le ofrece el seguimiento de ese proyecto y la reunión queda guardada ligada a ese contrato y su plan de trabajo; un desconocido sigue recibiendo el diagnóstico gratis como hoy.

## 2. Las tres situaciones (regla de Luis)

| Situación | Regla | Qué se agenda |
|---|---|---|
| **Cliente real** | el número pertenece a una ficha del CRM con **al menos un contrato firmado** (`wp_automatiza_contracts.status = 'signed'`) | seguimiento de **un proyecto** = un contrato firmado (y su plan de trabajo, si tiene) |
| **Prospecto** | el número está en la base (ficha del CRM, propuesta o diagnóstico agendado) pero **sin contrato firmado** | seguimiento de **una propuesta abierta**; si no tiene ninguna abierta, seguimiento general |
| **Nuevo** | el número no aparece en ninguna parte | diagnóstico gratis, igual que hoy |

- **Propuesta abierta** *(Eje)*: enviada y sin cierre: `wp_automatiza_propuestas.status` en `sent`, `pending` o `evaluando`. Las `aceptada` ya pasaron a contrato; `rechazada`, `archivada`, `borrador`, `lista`, `ajustando`, `generando` y `error` no cuentan.
- **Proyecto** *(Eje, por la regla de Luis «cliente real = firmó un contrato»)*: cada contrato firmado. Al firmarse, el contrato crea su plan de trabajo (`wp_automatiza_planes_trabajo`, con `codigo` de 12 caracteres); el nombre que se le muestra a la persona es el del plan si existe y, si no, el título/servicio del contrato.
- Si el número está en un cliente real y también tiene propuestas abiertas, manda **cliente real**, y la lista de opciones suma sus proyectos y sus propuestas abiertas.

## 3. Cómo conversa

Antes de que el agente responda, el flujo consulta quién es (sección 4) y le pasa una línea de contexto interno, igual que ya hace con las propuestas pendientes (nodo «Sumar Contexto»): el agente la usa y nunca la repite.

- **Nuevo:** sin cambios. Diagnóstico gratis → calendario (`<<ACTION:SHOW_CALENDAR>>`) → nombre y apellido y correo validados por «Check Email Booking».
- **Prospecto, una propuesta abierta:** «Hola María 👋 ¿Quieres agendar una reunión para seguir afinando la propuesta para Spa Aurora?». No pide nombre ni correo: ya los tenemos.
- **Prospecto, varias:** «Hola María 👋 ¿Sobre cuál propuesta quieres conversar: Sitio web Spa Aurora o Agenda y recordatorios?».
- **Cliente real, un proyecto:** «Hola Julio 👋 ¿Agendamos el seguimiento del proyecto Configurador Vitolio?».
- **Cliente real, varios:** pregunta cuál, con los nombres de cada uno.
- **Mensaje desde el botón de WhatsApp de la lámina del plan o del estado:** ese texto ya trae «(código XXXXXXXXXXXX)»; si el código es de un plan de ese mismo número, se toma ese proyecto sin preguntar.
- La elección se hace con **botones** de WhatsApp (respuesta rápida) cuando hay de 2 a 3 opciones y con una lista cuando hay más; elegir lleva al calendario de seguimiento que ya existe (`<<ACTION:FOLLOWUP_MEETING>>`).
- Si la persona dice explícitamente que quiere un diagnóstico nuevo (por ejemplo, para otro negocio), se le agenda el diagnóstico como hoy.

## 4. Consulta de quién es

- **Ruta nueva** en WordPress, solo para n8n: `GET /wp-json/automatiza-tech/v1/wa-contexto?phone=…`, con `permission_callback` = `at_cc_rest_auth` (la misma autenticación de las rutas que n8n ya usa en el cierre de cliente). Sin la clave, 401.
- **Coincidencia por teléfono:** los últimos 9 dígitos, sin espacios ni signos, como hoy `automatiza_tech_verify_client_by_phone` (01-oct-2026). Se busca en `wp_crm_clientes.telefono`, en `wp_automatiza_propuestas.phone` y en `wp_automatiza_leads.phone`, y se llega a la ficha única del CRM (`crm_cliente_id`) cuando existe.
- **Respuesta:** `situacion` (`nuevo` | `prospecto` | `cliente`), `nombre` (solo el primer nombre, para el saludo), `opciones`: lista de `{tipo: 'proyecto'|'propuesta', id, nombre, plan_codigo?}`. Nada más: ni correo, ni montos, ni enlaces.
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

Solo con el OK de Luis. Orden: migración y ruta en WordPress (por SSH, respaldo, `php -l`, md5 contra PROD) → sub-workflow de agenda → flujo de WhatsApp (respaldo, PUT con settings filtrados y republicar, como `N8N/cerebro-at/sincronizar.py`). Vuelta atrás: los flujos se restauran desde el respaldo; las columnas nuevas pueden quedarse (nulas, nadie las exige).

## 10. Lo que queda al constructor

- Qué nombre mostrar exactamente para una propuesta (título, servicio o empresa) y para un contrato sin plan, siempre corto y reconocible para la persona.
- El texto de los botones y de la lista (WhatsApp limita títulos a 20 caracteres en botones y 24 en listas).
- Cómo se cruzan `wp_automatiza_contracts.client_id` (ficha de `automatiza_tech_clients`) con la ficha única del CRM (`at_cc_techs_de_crm`, `crm_cliente_id`).
- Dónde exactamente se ve el vínculo en la ficha del CRM.
