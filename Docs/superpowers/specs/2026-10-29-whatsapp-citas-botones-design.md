# Tech WhatsApp: cancelar y reprogramar con botones, y enlace al portal del cliente

Diseño del 29-oct-2026, a pedido de Luis. Va en la rama `claude/wa-citas-botones`, que parte de `claude/wa-reconoce-cliente` y reusa su módulo `inc/wa-contexto/` y su script `N8N/wa-reconoce/aplicar.py`. Lo mantiene Eje.

## Por qué

- **La prueba real de Luis falló al cancelar** (29-oct, ejecuciones 472498 y 472507 del flujo de WhatsApp `bBcNlFgBzQ0766Mq`):
  - El bot le pidió nombre, correo, fecha y hora en cuatro líneas.
  - Luis escribió los cuatro datos en una sola línea, y «Parse Cancel Data» respondió `INVALID_FORMAT`.
  - El agente, además, le mandó un mensaje confuso.
  - Luis lo resume así: mientras más tiene que escribir la persona, más probable es que no se entienda con el asistente.
- **Lo que pide Luis:** si la persona tiene una cita, el bot se la muestra («mira, esta es tu cita») y la persona escribe lo menos posible.
  - Con una cita, confirma.
  - Con varias, elige con botones o con una lista.
  - Reprogramar funciona igual.
- **Los clientes que ya firmaron preguntan por su proyecto.** El portal del cliente del CRM ya tiene la línea de tiempo y el asistente MAXTECH, que responde dudas. Luis pide que Tech les mande el enlace a esa vista, en vez de responder él.

## Metas

1. **Cancelar sin escribir datos:** el bot muestra las citas del número y la persona elige y confirma con botones.
2. **Reprogramar igual:** elige la cita, confirma y le llega el calendario. La cita vieja se cancela recién cuando la nueva queda agendada.
3. **Enlace al portal:** si escribe un cliente real y pregunta por su proyecto, Tech le manda el enlace firmado a su ficha.
4. **Tipo de cambio:** si la consulta del tipo de cambio se cae o tarda, el bot responde igual y da los precios solo en USD.

## No metas

- La memoria de MAXTECH: que lea contrato, plan, avance, entregables y reuniones. Va en un diseño aparte, inmediatamente después de este.
- Reconocer clientes o prospectos en Instagram y en la web.
- Cambiar las plantillas de los recordatorios de Meta o los ids de los botones que ya traen (`btn_cancelar_lead_`, `btn_followup_cancel_`, etc.). Lo que hay detrás de los botones de reprogramar sí cambia: ver «Reprogramar».
- Cerrar las rutas REST públicas de citas y seguimientos. Es un pendiente aparte, que espera la decisión de Luis y está anotado en la bóveda privada.

## Antimetas (sería un fracaso aunque funcione)

- Cancelar o mover una cita que no es del número que escribe.
- Dejar a alguien sin cita porque abandonó el reprogramado a medio camino.
- Pedir por chat nombre, correo, fecha u hora para cancelar o reprogramar.
- Que el bot se quede sin responder porque una consulta (citas, tipo de cambio) falló o tardó.
- Mandar el enlace del portal de una ficha a un número que no es el de esa ficha.

## Comportamiento

### Qué citas se muestran

- **Solo citas futuras y activas** del número que escribe, ordenadas de la más próxima a la más lejana, con un máximo de 10 (el tope de una lista de WhatsApp).
- **Diagnósticos:** filas de `automatiza_leads`
  - cuyo `phone` termina en los mismos 9 dígitos que el número (`at_wa_sql_ultimos9`, como hoy en `datos.php`), o cuyo `email` es uno de los correos de las fichas del número (en `/leads` el teléfono es opcional);
  - que no están canceladas ni completadas;
  - cuya `scheduled_date` + `scheduled_time` es posterior a ahora.
- **Seguimientos:** filas de `automatiza_followup_meetings`
  - cuyo `phone` coincide, o cuyo `client_email` es uno de los correos de las fichas del número (`at_wa_fichas_por_telefono`);
  - con estado distinto de cancelado o completado;
  - cuya `meeting_date` + `meeting_time` es posterior a ahora.
- **Etiqueta de cada cita:**
  - «Diagnóstico gratis · mar 03-nov · 17:00»;
  - «Seguimiento: <propuesta o proyecto> · mar 03-nov · 17:00», con el nombre que da `at_wa_vinculo_texto` cuando hay vínculo, o «Seguimiento · …» cuando no lo hay.
- **Topes de WhatsApp:** un botón de respuesta admite 20 caracteres; un título de lista, 24, y su descripción, 72. Los títulos nunca se repiten, igual que en `waMensajeOpciones`.

### Cancelar

Se activa con la acción `cancel_appointment` del agente (`<<ACTION:CANCEL_APPOINTMENT>>`). Hoy esa acción va a «Ask Cancel Data» y pide las cuatro líneas; ese camino se reemplaza.

- **0 citas:** «No encontramos citas agendadas con este número.»
  - Si la persona es nueva, se ofrece el botón «Agendar diagnóstico» (`btn_agendar_demo`).
  - Si es prospecto o cliente, se ofrece el de seguimiento que ya existe (`seg_general`).
  - Siempre va además un botón «Tengo una cita». Si lo toca, se le dice que Luis le va a escribir y a Luis le llega un correo con el número (pudo haber agendado con otro número o correo). Nunca se le piden los datos.
- **1 cita:** «Tienes esta cita: <etiqueta>. ¿La cancelamos?», con los botones «Sí, cancelar» y «No, mantenerla». El de mantenerla reusa `btn_mantener_cita`.
- **2 o 3 citas:** «Tienes estas citas. ¿Cuál quieres cancelar?», con un botón por cita. Al elegir, se pide la misma confirmación que con una sola.
- **4 citas o más:** la misma pregunta, como lista.
- **Al confirmar, se revisa de nuevo en WordPress** que la cita siga activa, sea futura y sea de ese número.
  - Si ya no lo es: «Esa cita ya no está disponible», y se vuelve a mostrar lo vigente (mismo criterio que el botón viejo de `waElegirSeguimiento`).
- **Efectos de cancelar (decisión nuestra, tras la revisión del constructor):**
  - Se cancela con una **ruta nueva de WordPress protegida con `X-AT-Secret`** que cancela el diagnóstico o el seguimiento y deja todos los efectos:
    - estado cancelado (soft delete, nunca `hard_delete`);
    - evento de Google Calendar quitado (`automatiza_tech_delete_google_calendar_event` en el diagnóstico; en el seguimiento, lo que ya hace `/followup-meetings/{id}/cancel`);
    - correo a la persona y aviso a Luis.
  - La ruta responde si todo salió bien. El bot dice «cancelada» solo si la ruta lo confirma. Si falló, dice «No pude cancelarla en este momento, ya le avisamos a Luis», y a Luis le llega el aviso.
  - **Por qué una ruta nueva:**
    - la cadena de texto de hoy llama a `POST /appointments/{id}/cancel`, que no está en el código del repositorio, y tiene `onError=continueRegularOutput`, así que puede decir «cancelada» sin haber cancelado (verificarlo en PROD antes de reemplazarla);
    - «Delete Lead Direct» solo cambia el estado: no quita el evento ni avisa.
  - **Orden de despliegue:** WordPress va antes que n8n.
  - **Ningún camino** de cancelar ni de reprogramar puede usar `hard_delete=true`.

### Reprogramar

Se activa con las acciones `reschedule_appointment` y `reschedule_followup`. Hoy van a «Ask Reschedule Data» y a «Ask Reschedule Followup Data», que piden datos escritos; esos caminos se reemplazan.

- **Elegir y confirmar** funciona igual que en cancelar: «¿La cambiamos de día?», con «Sí, cambiar» y «No, mantenerla».
- **Nombre y correo de la cita nueva:** se copian de la cita vieja (la fila del diagnóstico o del seguimiento), no se piden. El límite de reuniones se cuenta con ese correo.
- **Al confirmar, la cita vieja no se cancela.**
  - Se guarda en Redis cuál cita se está moviendo (tipo e id), con el vínculo `contrato_id`/`propuesta_id` si es un seguimiento.
  - Se abre el calendario del tipo que corresponde: diagnóstico o seguimiento. El seguimiento conserva el vínculo, como hoy hace «Save Reschedule Client».
- **Cuando la cita nueva queda creada con éxito,** se cancela la vieja con los mismos efectos de cancelar y el aviso de reprogramación de hoy, y se borra la marca de Redis.
- **Si la persona abandona o la cita nueva falla,** la vieja queda como estaba.
- **Límite de reuniones activas** (`automatiza_tech_limite_reuniones_activas`, que usan `/leads`, la creación de seguimientos, la agenda del plan, `/check-limit` y el sub-workflow `PejBgr2LZQSAbbBy`): mientras se reprograma, la cita vieja sigue activa y contaría.
  - **Decisión nuestra:** un parámetro nuevo, `excluir` (tipo e id de la cita que se mueve), que **solo vale con `X-AT-Secret`**. Las rutas públicas lo ignoran, para que no sirva para saltarse el límite.
  - Si se agrega a `PejBgr2LZQSAbbBy` (lo usa Instagram), es un campo opcional que Instagram no manda, y se avisa a la sesión de Instagram antes de subir.
  - El correo de Luis está eximido del límite, así que la prueba real no lo ejercita: se prueba con una prueba automática y un correo sintético.
- **Cambio respecto de hoy:** hoy «Delete Lead For Reschedule» y «Cancel Followup For Reschedule» cancelan primero. Con este diseño ya no.
- **Los botones de reprogramar de los recordatorios** (`btn_reprogramar_lead_`, `btn_followup_reschedule_`) pasan también a cancelar después de crear. No se pide otra confirmación, porque tocar el botón ya lo es.

### Si la consulta de citas se cae

- Patrón de la revisión B de Codex: un error de la base responde 503 con `situacion`/`estado` `no_disponible`. El nodo de n8n va con `onError=continueRegularOutput` y `neverError`.
- La persona recibe: «En este momento no pude revisar tus citas. Ya le avisamos a Luis para que te escriba y lo coordinen.»
- Luis recibe un correo con el número y el motivo.
- Nunca se le piden los datos por chat.

### Ruta de WordPress

- **Rutas nuevas** en el módulo `inc/wa-contexto/`, protegidas con `at_cc_rest_auth` (cabecera `X-AT-Secret`), igual que `wa-contexto`:
  - una que devuelve las citas del número;
  - una que revisa si una cita sigue vigente y es de ese número.
- **El constructor decide** si son una o dos rutas, y si la cancelación la hace una ruta nueva protegida o las existentes.
- **Lógica pura sin base** (filtrar, ordenar, etiquetar), en `puras.php` o en un archivo hermano, con pruebas igual que hoy.

### Enlace al portal (clientes)

- **Qué devuelve `wa-contexto`:** suma `portales`, una lista de `{nombre, url}`.
  - Solo cuando `situacion = cliente`.
  - Una entrada por ficha del CRM (`crm_clientes`) cuyo propio `telefono` termina en los mismos 9 dígitos que el número (decisión de Luis: «debe coincidir el número de WhatsApp»).
  - La `url` sale de `url_portal($cliente_id)` de `crm-ai-completo.php`, que ya firma el enlace.
  - Si falta la clave de las fichas, la lista va vacía.
  - El enlace es **por ficha**, no por proyecto: el portal de una ficha muestra todos sus proyectos. Una ficha con varios proyectos lleva un solo enlace; «todos los enlaces» aplica cuando el número está en varias fichas.
  - El `nombre` es la empresa de la ficha, o su nombre si no tiene empresa («Tu portal de <empresa>»).
- **La línea interna** de «Sumar Contexto» (`waLineaContexto`) agrega:
  - con un portal: «Si pregunta por el avance o cualquier detalle de su proyecto, mándale el enlace a su portal, donde ve la línea de tiempo y puede preguntarle a MAXTECH: <url>»;
  - con varios: los enlaces con el nombre de cada uno. Decisión de Luis: si tiene más de un proyecto, mandarle todos los enlaces, que es más fácil.
- **`personalidad-whatsapp.md`** suma una regla corta en el mismo sentido.
- **Dónde no sale:** nunca por Instagram ni por la web, y nunca a un número que no sea el de la ficha.

### Tipo de cambio

- **Los cuatro nodos que lo consultan** (web: «Get Exchange Rate»; WhatsApp: «Get Exchange Rate», «Get Exchange Rate Audio» y «Get Exchange Rate Image») van con `onError=continueRegularOutput`, un timeout de 8 s y 2 reintentos. Al 29-oct, en la versión publicada, no tienen nada de eso.
- **El prompt de cada agente** (fuera de las marcas del Cerebro AT, porque esa parte es propia de cada flujo) dice: si no hay tasa, da los precios solo en USD; nunca «$0 CLP».
- **WordPress cachea la tasa** en `automatiza_tech_get_exchange_rate` (`inc/api-endpoints.php`), por ejemplo con un transient de 6 h. Si la consulta al banco falla, devuelve la última tasa conocida.
- **Al subirlo, se avisa a la sesión de Instagram,** que ya protegió su nodo y quiere usar la caché.

## Restricciones

- **n8n:**
  - Eje es el único que aplica cambios en web y WhatsApp. Si se toca la agenda `PejBgr2LZQSAbbBy`, se avisa a la sesión de Instagram antes de subir.
  - Script idempotente con vista previa y `--subir`, como `aplicar.py`: respalda, sube y verifica la `activeVersion`, no el borrador.
- **PROD:** solo con el OK explícito de Luis en el chat, y con respaldo antes.
- **Repositorio público:** ni datos de clientes ni el número o el correo de Luis en commits, pruebas o documentación. Los detalles de seguridad van a la bóveda.
- **Código:**
  - `functions.php` no se toca.
  - Nada se borra para siempre: cancelar es cambiar el estado.
- **Pruebas:**
  - Desarrollo guiado por pruebas en la lógica pura (JS de los nodos con `node --test`, PHP puro).
  - Pruebas con WordPress (`*-wp-test.php`, necesitan WAMP; al 29-oct MySQL de WAMP está apagado).
  - Simulación de nodos y de expresiones, como `aplicar_test.py`.
- **Revisión B:** la hace Codex, el homólogo en la otra plataforma, con listas A y B, como en `claude/wa-reconoce-cliente`.
- **Prueba real con el número de Luis:**
  - cancelar la reunión 69 (seguimiento del 3-nov a las 17:00, ligada a la propuesta de prueba 61);
  - reprogramar una cita de prueba;
  - pedir el enlace siendo cliente (Fase B de la prueba de reconocimiento).

## Lo que decidió Luis

- El bot muestra las citas que existen y la persona escribe lo menos posible.
- Con una cita, confirma; con varias, elige con botones o con una lista.
- Reprogramar funciona igual.
- El enlace al portal sale solo si coincide el número de WhatsApp. Con más de un proyecto, se mandan los enlaces de todos.

## Decisiones nuestras (Luis puede cambiarlas)

- Siempre se pide confirmar antes de cancelar, también después de elegir entre varias citas.
- Al reprogramar, la cita vieja se cancela cuando la nueva queda creada. Luis vio la propuesta y dijo «sí, me parece».
- Solo citas futuras, con un máximo de 10.
- Para el enlace, el teléfono que tiene que coincidir es el de la propia ficha del CRM (`crm_clientes.telefono`), no el de una propuesta o una cita.
- Revisión del constructor (29-oct), resuelta por nosotros:
  - ruta nueva protegida para cancelar con todos los efectos;
  - `excluir` solo con clave;
  - diagnósticos también por los correos de la ficha;
  - botón «Tengo una cita» → aviso a Luis;
  - la cita nueva copia nombre y correo de la vieja;
  - los botones de reprogramar de los recordatorios también cancelan después de crear;
  - un enlace por ficha.
- El enlace al portal va en este mismo trabajo, porque toca la misma ruta y el mismo flujo. La memoria de MAXTECH va aparte.

## Lo que queda al constructor

- Los ids de los botones nuevos. Usar un prefijo propio, sin chocar con los que ya existen en «Button Action», y respetar la regla `fallbackOutput: extra`.
- Los textos finales, con el tono de Tech WhatsApp y respetando los topes de caracteres.
- El tiempo de vida de la marca de Redis del reprogramado y de la elección.
- Qué nodos se crean y cuáles se reusan.
- Cómo se conecta la cancelación de la cita vieja después de crear la nueva, en cada tipo.
- Si las rutas de WordPress son una o dos.
