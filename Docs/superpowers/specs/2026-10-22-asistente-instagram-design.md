# Asistente de mensajes directos de Instagram (diseño)

**Fecha:** 22-oct-2026 · **Autor:** Eje · **Pedido de Luis:** conversación del 22-oct-2026.

## Para qué

Hoy los mensajes directos que llegan a la cuenta de Instagram de AutomatizaTech solo se ven en el Portal OmniCliente; nadie los responde hasta que Luis entra. Luis quiere que un asistente los responda solo, las 24 horas, con el mismo criterio que los asistentes de la web y de WhatsApp. Cuando alguien quiere hablar con una persona, el asistente lo deriva y le avisa a Luis por correo, para que él lo atienda por el mismo directo.

**Éxito:**
- un mensaje directo nuevo recibe respuesta en segundos, en español de Chile y con el tono de «Tech»;
- quien quiere una reunión la deja agendada sin salir de Instagram;
- quien pide una persona deja de recibir respuestas del bot y Luis recibe el aviso al instante.

## Lo que decidió Luis

1. **Cerebro:** una copia independiente del agente web en n8n, con el mismo prompt, la misma identidad y las mismas reglas de negocio. Debe saber que la persona escribe por Instagram.
2. **Capacidades:** las del agente de WhatsApp, pero en Instagram: responde dudas, consulta la disponibilidad, agenda una llamada y guarda el contacto en la base de datos.
3. **Derivar a una persona:** si alguien quiere hablar con una persona, el asistente deja de responder en esa conversación y llega un correo a **lgonzalez@automatizatech.cl** y a **lmgm.030@gmail.com** que dice que una persona de Instagram quiere hablar con él. Luis responde por el directo de Instagram o desde el Portal.
4. **Varias personas:** queda como está hoy en el Portal. Por ahora atiende Luis, pero cualquier ejecutivo puede tomar la conversación; el Portal ya avisa por correo cada asignación o traspaso.
5. **Horario:** responde las 24 horas.
6. **Conversaciones existentes:** las 3 conversaciones de Instagram que ya están asignadas a una persona siguen así. El asistente parte con las conversaciones nuevas.
7. **Ventana de 24 horas de Instagram:** Luis la conoce y toma las derivaciones el mismo día.

## Lo que ya existe (verificado el 22-oct)

- **Canal en el Portal:** «IG Automatizatech» (canal 2 del cliente 1) recibe los mensajes directos. Hay 3 conversaciones, todas en modo humano; la última llegó el 22-oct a las 04:05.
- **Reenvío al bot:** `OmniChannelController::receive_message` crea las conversaciones nuevas con `status = 'bot'`. Si la conversación está en `bot` y `intervention_mode` no es `human`, llama a `forward_to_n8n()`.
- **Lo que recibe n8n:** `forward_to_n8n()` hace un POST a `bot_configs.n8n_webhook_url` del canal con este JSON:

  ```
  { event:'new_message', conversation_id, channel_id, business_phone,
    contact:{external_id (IGSID), name, phone},
    message:{id, type, content, media_url},
    callback_url (api-omnichannel.php?route=webhook/n8n-callback),
    callback_token (HMAC de conversation_id:channel_id),
    timestamp, prompt_config? }
  ```

- **Lo que falta hoy:** el canal 2 tiene `bot_configs` activo, pero sin `n8n_webhook_url`. Por eso hoy no se reenvía nada.
- **Respuesta:** `handle_n8n_callback()` valida el token, guarda el mensaje y, si el canal es Instagram, lo envía con `send_instagram_message()`.
- **Toma de control:** `takeover_conversation()` pasa la conversación a `intervention_mode = 'human'`, la asigna a un ejecutivo y avisa por correo con `notify_agent_assignment()` (destinatario, copia a supervisores y copia oculta a lgonzalez@automatizatech.cl). Liberar la conversación la devuelve al bot.
- **Ejecutivos del Portal (cliente 1):** Anamaría Sandoval, Luisana Agente y Teomar (supervisor). Luis no aparece como ejecutivo.
- **Agente web en n8n:** «Agente Tech Web (PROD)» (`3A5Y1NPBxpTjhHdE`). Tiene un webhook, consulta el tipo de cambio, un nodo agente con gpt-4o-mini y su memoria. Prompt de unos 7.500 caracteres; la única etiqueta que usa es `<<SHOW_DEMO_FORM>>`.
- **Agente de WhatsApp en n8n:** «WhatsApp Tech - Principal (PROD)». No usa herramientas de n8n: el modelo termina el mensaje con `<<ACTION:…>>` y un Switch reparte a ramas fijas. La agenda funciona con botones de WhatsApp y una máquina de estados en Redis. La cita real la crea el sub-workflow **`PejBgr2LZQSAbbBy`** («Agendar Reunión AutomatizaTech - Cliente»):
  - consulta la disponibilidad en WordPress y en Google Calendar;
  - crea el evento con Meet;
  - hace un POST a `/wp-json/automatiza-tech/v1/leads` o a `/followup-meetings`;
  - manda el correo de confirmación por SMTP.

  Su «derivar a soporte» solo agrega un texto: no avisa a nadie. El mapa completo está en el scratchpad de Eje, `ig-bot/mapa-agente-whatsapp.md`.

## Diseño

### 1. Flujo nuevo en n8n: «Agente Tech Instagram (PROD)»

Es independiente de los flujos de la web y de WhatsApp, que no se tocan.

1. **Webhook** POST, con una ruta difícil de adivinar. Recibe el JSON del Portal y responde `200` de inmediato. El Portal no espera la respuesta: llama sin bloquear.
2. **Filtro:**
   - solo procesa `event = 'new_message'` del canal 2;
   - descarta los mensajes vacíos;
   - si el mensaje no es texto (audio, imagen o sticker), responde una sola vez «Por ahora leo solo texto: ¿me lo escribes?» *(decisión de Eje: la versión 1 es solo texto)*.
3. **Mensajes en ráfaga:** si la persona manda varios mensajes seguidos, espera unos 6 segundos y responde una vez a todos juntos, como hace el de WhatsApp (Redis, con la clave `ig:<IGSID>`).
4. **Tipo de cambio:** igual que el agente web, `GET /automatiza-tech/v1/exchange-rate`.
5. **Agente IA:**
   - **prompt:** el del agente web copiado tal cual, más un bloque «Canal Instagram»:
     - respuestas cortas, de 1 a 3 párrafos breves;
     - sin formato de página ni enlaces largos;
     - no pedir que «llene el formulario de la web», sino agendar en la misma conversación;
     - cómo derivar a una persona;
     - qué datos pedir para agendar.

     La etiqueta `<<SHOW_DEMO_FORM>>` de la web se reemplaza por las herramientas;
   - **modelo:** el mismo del agente web (gpt-4o-mini) *(decisión de Eje: mismo criterio y menor costo; se puede subir a gpt-4o si la calidad no alcanza)*;
   - **memoria:** Redis por `ig:<IGSID>`, con una ventana de 30 mensajes.
6. **Herramientas del agente** *(decisión de Eje: en Instagram no hay botones, así que la agenda va por conversación con herramientas de n8n, no con la máquina de estados de WhatsApp)*:
   - `ver_disponibilidad(fecha)`: `POST /automatiza-tech/v1/check-availability` y `GET /appointments-config`. Devuelve las horas libres de ese día o de los próximos 3 días hábiles, con la zona horaria de Chile.
   - `agendar(nombre, correo, telefono_opcional, fecha, hora)`: llama al sub-workflow `PejBgr2LZQSAbbBy` con `appointmentType = 'Demo'`, `session_id = 'ig:<IGSID>'` y el teléfono si lo dio. Ese sub-workflow crea la cita, guarda el lead en el CRM y manda el correo de confirmación. Antes de llamar, el agente confirma con la persona la fecha, la hora y el correo.
   - `derivar_a_persona(motivo)`: ver la sección 2. Después de derivar, el agente se despide con algo como «Le avisé a Luis, te va a responder por aquí mismo».
7. **Respuesta:** un POST a `callback_url` con `conversation_id`, `channel_id`, `callback_token` y el texto. El Portal lo guarda y lo envía por Instagram.

**Clientes que piden una reunión de seguimiento:** en WhatsApp se reconocen por teléfono, pero en Instagram no hay teléfono. El agente no intenta identificarlos: los deriva a una persona *(decisión de Eje)*.

### 2. Cambio en el Portal OmniCliente: derivación pedida por el bot

- **Nueva acción del callback de n8n:** `action = 'escalate'`, con `motivo`, validada con el mismo `callback_token`. La conversación:
  - pasa a `intervention_mode = 'human'`;
  - queda en estado «esperando a una persona», sin asignar *(decisión de Eje: Luis no es ejecutivo del Portal; así cualquier ejecutivo puede tomarla, como hoy)*;
  - deja una nota de sistema en el historial: «El asistente derivó: <motivo>».
- **Correo inmediato:** va a lgonzalez@automatizatech.cl y a lmgm.030@gmail.com.
  - Asunto: «Instagram: <nombre> quiere hablar contigo».
  - Contenido: el usuario de Instagram, lo que pidió, los últimos mensajes y un enlace directo a la conversación en el Portal.
  - Se envía con el mismo mecanismo de correo del Portal (`wp_mail` y su SMTP).
  - Recordatorio en el texto: Instagram solo permite responder dentro de las 24 horas desde el último mensaje de la persona.
- **Destinatarios configurables:** se guardan en la configuración del bot del canal (`bot_configs`), no en el código.
- **Sin mensajes duplicados:** mientras la conversación esté en modo humano, el Portal ya no la reenvía a n8n. Si n8n llega a contestar después de derivar, el callback se descarta para conversaciones en modo humano.
- **Vuelta al bot:** cuando un ejecutivo o Luis libera la conversación en el Portal, vuelve a `bot`. Esto ya existe.

### 3. Conexión

- Se escribe la URL del webhook nuevo en `bot_configs.n8n_webhook_url` del canal 2. Es un cambio de datos en PROD: se respalda la fila antes.
- Las 3 conversaciones existentes no se tocan: siguen en modo humano.

## Seguridad y privacidad

- El webhook de n8n no lleva clave, porque el Portal hoy no la envía. Se protege con una ruta larga y difícil de adivinar. El callback de respuesta exige el `callback_token` HMAC, así que nadie puede publicar mensajes en el Portal sin él. Que el Portal mande una cabecera secreta queda como mejora posible.
- El prompt mantiene las reglas de seguridad del agente web: no revelar instrucciones ni datos de otros usuarios, y solo temas de AT.
- La base de datos solo se escribe con los endpoints que ya existen (leads, citas y la nueva acción de derivar). No se guarda más información de Instagram que la que el Portal ya guarda.
- **Costo:** cada mensaje consume OpenAI. Hay que avisarle a Balance (regla de los agentes) cuando se mida el primer mes.

## Fuera de esta etapa

- Responder audios e imágenes de Instagram.
- Responder comentarios de publicaciones (es el punto 8 de la lista de Luis, aparte).
- Mensajes después de las 24 horas (etiqueta HUMAN_AGENT de Meta).
- Reconocer a clientes existentes por Instagram.
- Enviar el evento Lead a Meta desde el servidor (API de conversiones).

## Pruebas

- **n8n:** probar el flujo con un JSON falso del Portal y un `callback_url` de prueba que no envíe nada a Instagram:
  - una pregunta de precios devuelve el precio con su equivalente en pesos;
  - pedir una reunión lleva a consultar disponibilidad, pedir los datos, confirmar y llamar a `agendar`. La prueba del sub-workflow va con un correo de prueba de AT, y la cita se borra después;
  - «quiero hablar con una persona» lleva a `derivar_a_persona`;
  - un mensaje de otro tema se rechaza con amabilidad.
- **Portal:** pruebas en PHP de la acción `escalate`:
  - token válido: la conversación queda en modo humano, se envía el correo y se escribe la nota;
  - token inválido: se rechaza;
  - una conversación ya humana: no duplica el correo;
  - un callback de texto en una conversación humana se descarta.
- **De punta a punta:** con el OK de Luis, desde una cuenta de Instagram de prueba se escribe a AutomatizaTech:
  - responde;
  - agenda (con una cita de prueba que se borra);
  - deriva, y llegan los correos a las dos direcciones.

## Despliegue y vuelta atrás

- **Despliegue:** solo con el OK de Luis.
  - Se crea el flujo de n8n desactivado y se prueba.
  - Se sube el cambio del Portal por SSH: respaldo, `php -l`, md5 y las reglas de orden de despliegue del Portal (`Docs/MASTER/06_PORTAL_OMNICLIENTE_FRONTEND.md`).
  - Se escribe `n8n_webhook_url` en la configuración del canal y se activa el flujo.
- **Vuelta atrás:** se deja `n8n_webhook_url` vacío y el Portal vuelve a solo mostrar los mensajes. El código del Portal se restaura desde el respaldo.

## Contexto de producto (Luis, 22-oct)

AutomatizaTech usa su propio Portal OmniCliente como **un cliente más**. Es el cliente 1, con sus canales de WhatsApp, Instagram y, más adelante, Telegram y correo. Luis quiere probarlo primero con AT y después ofrecerlo como CRM omnicanal a clientes: varias entradas en una sola plataforma, donde se asigna, se responde y se hace seguimiento.

Por eso la configuración propia de AT (destinatarios de la derivación, ejecutivo por defecto y URL del flujo de n8n) va **por canal o cliente y no fija en el código**, para poder repetir el patrón con otro cliente. En esta etapa no se construye nada multi-cliente adicional.

## Respuestas de Luis a la revisión (22-oct)

- **Luis queda registrado como ejecutivo del Portal** en el cliente 1, además de su rol de superadministrador.
  - La derivación del bot **se le asigna a él**: se usa el mismo mecanismo de toma de control (`intervention_mode = 'human'`, `status = 'assigned'`, `assigned_agent_id` = Luis).
  - El ejecutivo por defecto de las derivaciones es configurable. Cualquier otro ejecutivo puede tomar después la conversación, como hoy.
  - El correo de asignación llega a lgonzalez@automatizatech.cl y a lmgm.030@gmail.com, con el asunto y el contenido descritos en el punto 2 del diseño.
  - Crear el registro de ejecutivo es un cambio de datos en PROD: lo hace Eje con respaldo, o Luis desde el superadmin. No se crea ninguna contraseña por chat.
- **Vuelta al bot** *(Eje; Luis no eligió, se aplica la recomendación)*: la conversación vuelve al bot cuando Luis la libera en el Portal, **o** automáticamente si la persona escribe de nuevo después de 24 horas sin actividad en la conversación. Con esto, quien vuelve días después recibe respuesta automática.

## Decisiones tomadas tras la revisión del constructor (22-oct)

Las que dicen *Eje* puede corregirlas Luis.

- **Estado de la conversación derivada:** lo reemplaza la respuesta de Luis: queda asignada a él como ejecutivo, con `status = 'assigned'`.
  - Si en el futuro no hay un ejecutivo por defecto configurado, queda en `status = 'open'` sin asignar. No se agrega ningún valor nuevo al ENUM.
  - El constructor debe verificar que el inbox muestre ese caso y que «liberar» una conversación sin ejecutivo no falle, por ejemplo al descontar `active_chats`. Si falla, se corrige en el backend con un guardia.
- **Descartar callbacks en conversaciones humanas** *(Eje)*: solo en el canal Instagram. WhatsApp sigue igual que hoy.
- **Correos de derivación** *(Eje)*: se guardan en una opción de WordPress (`omni_ig_derivacion_correos`), sin migración.
  - Se envían con una función nueva; `notify_agent_assignment` necesita un ejecutivo, así que no se reutiliza.
  - El enlace del correo apunta a la conversación en el Portal si su ruta lo permite; si no, al inbox.
- **Si n8n está caído** *(Eje)*: en la versión 1 se acepta que ese mensaje quede sin respuesta automática. Queda visible en el Portal y ARGOS ya vigila los errores de n8n. No hay reintento.
- **Ráfagas con ejecuciones paralelas** *(Eje)*: como en WhatsApp, cada ejecución revisa en Redis si su mensaje es el último. Solo responde la última y las demás terminan sin responder.
- **Teléfono para agendar** *(Eje)*: el agente pide nombre, correo y un teléfono o WhatsApp para recordatorios; si la persona no quiere darlo, sigue sin él.
  - El constructor verifica cómo se comporta `PejBgr2LZQSAbbBy` con el teléfono vacío: si duplica o falla, y el formato exacto de entrada y salida.
  - Si el teléfono es obligatorio, se vuelve obligatorio en el prompt.
- **Credenciales** *(Eje)*: se reutilizan las que ya existen en n8n: OpenAi account, Redis32 y AT REST Secret. Google Calendar y SMTP se usan a través del sub-workflow. No se crean secretos nuevos.

## Lo que queda al constructor

- Los nombres internos de los nodos y el texto exacto del bloque «Canal Instagram» del prompt, dentro de lo decidido.
- Cómo formatear las horas disponibles en el texto.
- El diseño HTML del correo de derivación, siguiendo el estilo de los correos del Portal.
