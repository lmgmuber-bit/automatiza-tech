# Retomar prospectos, fase 2: correo semanal con aprobación de cada recordatorio

Fecha: 2026-10-18 · Autor: Eje (Claude, plataforma AT) · Decisiones de Luis (Presidente) marcadas como tales; las que
son de Eje dicen «decisión de Eje» y Luis puede cambiarlas. Repositorio público: aquí no van nombres ni datos de
prospectos; el piloto vive fuera del repo, en `C:/Users/luis_/Documents/Codex/Shared-Tools/Retomar-Prospectos-Piloto.md`.

Parte de la fase 1, ya en producción: `Docs/superpowers/specs/2026-10-18-retomar-prospectos-design.md` (módulo
`inc/retomar/`, tablas `wp_at_retomar` y `wp_at_retomar_bajas`, presentación `retomar` del renderer, flujos n8n
«Retomar · Render» activo y «Retomar · Reloj y WhatsApp» inactivo, plantilla Meta `retomar_propuesta` en revisión,
«Compartir a mano» e interruptor `at_rt_wa_automatico` apagado). Guía: `Docs/METODO_AT/RETOMAR-PROSPECTOS.md`.

## 1. Para qué

En la fase 1, Luis tenía que llenar a mano cada prospecto y, al activarlo, los recordatorios salían solos. Luis ahora
quiere lo contrario en las dos puntas: que **el sistema haga el trabajo de detectar y preparar**, y que **ningún
recordatorio salga sin que él lo vea y lo apruebe**. Cada semana le llega un correo con los prospectos que toca
recordar; por cada uno ve el recordatorio y la presentación tal como los recibiría el prospecto, y decide.

Éxito: Luis resuelve la semana en minutos desde el correo, los prospectos estancados vuelven a conversar o agendan,
y nunca sale nada que él no aprobó. Fracaso aunque funcione técnicamente: que salga un mensaje sin aprobación (por
ejemplo, porque un antivirus del correo abrió el enlace de «Aprobar»), que se le escriba a un cliente, a quien
rechazó o a quien pidió no recibir más, que el prospecto vea montos viejos o equivocados, que el correo semanal se
llene de propuestas antiguas, o que salga un enlace roto.

## 2. Decisiones de Luis (18-oct, noche)

1. **Semanal y automático.** El sistema detecta a los prospectos estancados y prepara su recordatorio solo. A Luis le
   llega **un correo por semana** con todos los que toca recordar.
2. **Cada envío lo aprueba Luis.** Esto **reemplaza** la decisión 1 de la fase 1 («Luis no aprueba cada uno»). Por
   cada prospecto, el correo trae tres botones:
   - **✅ Aprobar y enviar:** sale el correo y el WhatsApp al prospecto con el recordatorio y la presentación;
   - **✏️ Pedir cambios:** como en las propuestas: Luis escribe qué ajustar, la IA rehace el recordatorio y la
     presentación, y vuelve a Luis para aprobar;
   - **⏭️ Esta semana no:** no se le envía nada.
3. **El día y la hora del correo semanal se configuran** (día de la semana y hora).
4. **Quién entra solo:** los prospectos con propuesta **en nuestro sistema** que se envió y sigue sin respuesta. Los
   que tienen la propuesta fuera del sistema (en su propio dominio o en una landing) **los agrega Luis a mano** y desde
   ahí se siguen igual.
5. **Si el prospecto pulsa «No me interesa»**, no recibe nunca más un recordatorio (ya existe en la fase 1).
6. **Maqueta aprobada** (18-oct): correo semanal con una tarjeta por prospecto (nombre, negocio, días desde la
   propuesta, «Recordatorio n de 4», canales, los tres textos, miniatura de la presentación, «Ver la presentación
   completa», «Ver el correo tal como le llega» y los tres botones); y lo que recibe el prospecto: correo con «Ver
   resumen y demo», «Agendar 20 minutos» y «Prefiero que no me escriban más», y WhatsApp con «Ver y agendar» y «No me
   interesa».
7. **WhatsApp:** un texto con el enlace para ver la presentación. Los prospectos del piloto ya conversan con Luis por
   WhatsApp, así que van con la casilla «WhatsApp autorizado» marcada.
8. **Fotos de la presentación nueva generadas con la API de Higgsfield** (escenas del rubro de cada prospecto). Ya no
   se reutilizan solo las fotos de la propuesta.
9. **Enlace para volver a ver la propuesta** que ya se le envió al prospecto.
10. **El recordatorio dice que nos podemos ajustar** (necesidades, presupuesto, partir por etapas): «lo que podemos
    ajustar» y las recomendaciones van en los textos.
11. **Propuesta antigua hecha fuera de la plantilla AT** (por ejemplo, en Gamma): no se reenvía. Se rehace como
    propuesta nueva con la plantilla propia de AT, con lo que se habló en las reuniones y lo que traía la presentación
    antigua; **sin montos, con plazos** (cuánto nos demoramos en hacer lo que piden), diciendo que lo económico se puede
    ajustar y mencionando la demo ya publicada, que puede quedar funcionando en un dominio propio que compre el
    cliente. Es un trabajo de contenido **aparte** (por el flujo de propuestas v3 que ya existe), no parte de este
    software. El recordatorio de ese prospecto enlaza a la propuesta nueva.
12. **Piloto con dos prospectos** (lista privada). El primero parte sin esperar la propuesta nueva del segundo.

## 3. Decisiones de Eje (Luis puede cambiarlas)

### 3.1 Detección automática
- **Candidata:** una propuesta de `wp_automatiza_propuestas` con estado `sent` o `evaluando`, enviada hace **14 días o
  más**, cuyo correo no pertenece a una ficha del CRM con `tipo = 'cliente'`, no está en la lista de bajas de Retomar y
  no tiene ya un seguimiento de Retomar (en cualquier estado). Las `aceptada`, `rechazada`, `archivada`, `pending` y
  los borradores no entran solas. Medido en producción el 18-oct: con esta regla entran 2 prospectos (los dos del
  piloto, uno de ellos tras desarchivar su propuesta por pedido de Luis); las propuestas de clientes quedan fuera.
- **Fecha de envío:** la del envío al cliente si queda registrada (historial de la propuesta o del CRM); si no, la de
  creación de la propuesta. Quien construye elige la fuente más fiel que exista y lo deja dicho en la guía.
- **Agregar a mano:** el formulario actual del panel Retomar (que ya crea o vincula la ficha del CRM y acepta la URL
  de una propuesta externa y hasta dos demos), más un botón **«🔁 Retomar»** en la ficha de prospecto del CRM que abre
  ese formulario con los datos de la ficha. Un seguimiento agregado a mano entra al siguiente correo semanal sin
  esperar los 14 días.
- Un seguimiento por persona (correo): la detección nunca crea un segundo.
- **Propuesta antigua (no hecha con la plantilla AT).** Se reconoce por `flujo` distinto de `'v3'` (medido en
  producción el 18-oct: solo las propuestas hechas con la plantilla AT tienen `flujo = 'v3'`; las de Gamma tienen
  `NULL`). Su seguimiento se crea igual, pero queda **«Necesita propuesta nueva»**: aparece en el correo semanal sin
  botón de aprobar, con un aviso y el enlace al panel, y nunca enlaza la propuesta antigua (traería montos viejos).
  Cuando Luis vincula en la ficha de Retomar la propuesta nueva (v3), el seguimiento cambia su `propuesta_id` a esa,
  las paradas por aceptada o rechazada pasan a mirar la nueva, la antigua se archiva con la nota «Reemplazada por la
  propuesta N» y el recordatorio se prepara normalmente.
- **La propuesta nueva llega con el recordatorio**, no por el envío normal de propuestas (decisión de Luis §2.11):
  queda aprobada (`lista`) y, cuando sale el recordatorio aprobado que la enlaza, WordPress la pasa a `sent` sin
  mandar su propio correo, con nota en su historial. Motivo medido: desde `lista` el cliente no puede responder la
  propuesta en `ver-presentacion.php`; desde `sent`, sí.

### 3.2 Preparación del recordatorio
- **Cuándo:** en cuanto un prospecto entra (detección o alta a mano), el sistema prepara su recordatorio para que esté
  listo antes del correo semanal. Lo que no alcance a quedar listo aparece en el correo como «En preparación» sin
  botones y queda para el correo siguiente (o para un aviso «listo para aprobar», ver 3.4).
- **Textos con IA:** «Lo que conversamos», «Lo que proponemos» y «Para partir», más una línea de **recomendación de
  ajuste** (cómo partir por etapas o adaptarse al presupuesto). Insumos: la transcripción y el contenido de la
  propuesta (`transcript_text` y lo que haya en el registro de la propuesta), las notas del historial del CRM y lo que
  Luis escriba en el formulario o en «Pedir cambios». Mismo patrón y credencial que los flujos del plan de trabajo
  (`N8N/plan-trabajo/plan-1-borrador.json` y `plan-2-cambios.json`: nodo OpenAI `gpt-4o`, credencial «OpenAi account»),
  con callback a WordPress. Límites de la fase 1: 600 caracteres y 6 líneas por texto.
- **Textos de un alta a mano:** los tres campos del formulario pasan a ser **opcionales** y funcionan como notas para la
  IA. Además, el panel permite **editar el texto final directamente**, sin pasar por la IA (por ejemplo, cuando queda
  «Necesita revisión»); lo editado a mano no se vuelve a redactar salvo que Luis pida cambios. Todo texto, venga de la
  IA o de Luis, igual necesita la aprobación del correo semanal.
- **Nunca montos** en los textos ni en la presentación: ni precios de la propuesta ni números inventados. La IA lo
  tiene prohibido y WordPress lo valida (rechaza textos con `$`, «CLP», «USD», «UF» o cifras con separador de miles) y
  se lo devuelve a la IA una vez; si insiste, el recordatorio queda «Necesita revisión» para que Luis lo corrija.
- **Fotos con Higgsfield:** la IA escribe también los encargos de imagen (portada, cierre y, si no hay imagen de la
  demo, una para la lámina de la demo): escenas del rubro con personas, **sin texto, letreros ni etiquetas** (lección
  del 28-sep: Soul 2 imprime etiquetas en botellas y letreros en foco). El renderer genera esas fotos con su integración
  actual (`renderer/src/higgsfield.js`, Soul 2, US$0,0032 por imagen según su propia nota de precios del 20-sep; unas 3
  por presentación ≈ US$0,01) y reutiliza las ya pagadas con el mismo encargo (`photo-manifest`). Si Higgsfield falla o
  no responde a tiempo, la lámina usa las fotos de la propuesta y, si no hay, el degradado de la marca: nunca se
  bloquea el recordatorio por una foto. Hoy el camino `retomar` del renderer tiene prohibido llamar a Higgsfield
  (fase 1): esta fase lo habilita.
- **Una presentación por prospecto,** con su dirección fija. Se rehace solo con «Pedir cambios»; los recordatorios 2 a
  4 reutilizan la vigente.
- **No pagar dos veces la misma foto:** el manifiesto del renderer es por carpeta y cada versión de la presentación
  tiene su propia carpeta (`unique_id` con la generación, fase 1). Por eso WordPress guarda las URL de las fotos ya
  generadas por encargo y, si un «Pedir cambios» no cambia el encargo de una foto, se la pasa al renderer en `images`
  (que el renderer ya respeta sin generar). Solo se paga de nuevo la foto cuyo encargo cambió.
- **Enlaces de la presentación y del correo:** «Ver la propuesta» (la propuesta del sistema, o la URL externa que puso
  Luis; si se hizo una propuesta nueva con la plantilla AT, esa), las demos, «Agendar» (agenda propia de Retomar, fase
  1) y la baja. Nunca enlaces internos ni con `easypanel` (regla D8).

### 3.3 El correo semanal a Luis
- Se envía el **día y la hora configurados** (por defecto, lunes 09:00, hora de Chile), una vez por semana, al correo
  de Luis (opción configurable; por defecto el mismo destino de «Enviar prueba al equipo»). Si no hay nadie que
  recordar, no se envía (o se envía un aviso corto «Esta semana no hay prospectos para recordar»: decisión de quien
  construye, preferir no enviar).
- Contenido: el de la maqueta aprobada (§2.6). La maqueta tiene nombres reales y no va al repo: está en
  `C:/Users/luis_/Documents/Codex/Shared-Tools/Retomar-Maqueta-Correo-Semanal-2026-10-18.html`. La **miniatura** de la
  presentación es HTML del correo (la foto de portada con el nombre del negocio encima), no una captura: el renderer no
  produce imágenes de láminas y no se le agrega eso. Además, una vez, los que terminaron su ciclo esa semana (4 recordatorios
  sin respuesta, agendó, aceptó, se dio de baja) en una lista corta «Salieron de Retomar».
- Un prospecto sin decisión de Luis vuelve a aparecer en el correo siguiente; sus enlaces viejos vencen.

### 3.4 Botones seguros
- Los botones del correo **no ejecutan nada con un solo clic**: abren una página que muestra exactamente qué se va a
  hacer («¿Enviar el recordatorio 1 de 4 a …?») y un botón que confirma por POST. Motivo: Gmail, Outlook y los
  antivirus abren solos los enlaces de los correos (mismo problema que la baja, fase 1 §7.1).
- Cada enlace va **firmado** (HMAC), sirve **una sola vez**, **vence en 7 días** y está atado a la versión de la
  presentación (huella): si la presentación cambió después del correo, el enlace viejo dice «Este recordatorio cambió:
  revisa la versión nueva» y no envía. Sin login, porque Luis aprueba desde el celular; los enlaces solo van a su
  correo.
- **Aprobar:** el envío queda programado para la **siguiente ventana hábil** (lunes a viernes, 10:00 a 13:00, Chile);
  si se aprueba dentro de la ventana, sale en la siguiente pasada del reloj. La página de confirmación dice cuándo sale
  y, si el WhatsApp automático está apagado, ofrece **«💬 Compartir a mano»** (el mismo de la fase 1).
- **Pedir cambios:** la página trae un cuadro «¿Qué cambiamos?»; la IA rehace textos y encargos de imagen, se pide un
  render nuevo y, cuando queda listo, a Luis le llega un **aviso corto** con la tarjeta de ese prospecto y los mismos
  tres botones, sin esperar a la semana siguiente.
- **Esta semana no:** queda anotado en el historial; nada se envía; el prospecto vuelve en el correo siguiente.
- Sacar a un prospecto para siempre: con «Pausar» o «Dar de baja» del panel (fase 1).

### 3.5 Envío
- Con la aprobación, sale el **correo** (el de la fase 1, con su asunto distinto por envío, `List-Unsubscribe` y
  botones) y, si la casilla «WhatsApp autorizado» está marcada **y** el interruptor `at_rt_wa_automatico` está
  encendido (plantilla aprobada por Meta), el **WhatsApp** con la plantilla `retomar_propuesta`. Luis pidió correo y
  WhatsApp en cada envío aprobado: se deja de lado la regla de la fase 1 de WhatsApp solo en el 1.º y el 4.º. Mientras
  el interruptor esté apagado, el WhatsApp es «Compartir a mano».
- El botón de WhatsApp abre la página del prospecto (`retomar.php`), que reúne presentación, «Ver la propuesta»,
  «Agendar» y «No me interesa»: así no se cambia la plantilla que Meta está revisando.
- Se mantienen de la fase 1: tope de **4 recordatorios**, al menos **7 días** entre uno y otro, días hábiles, condiciones
  de parada (baja, agendó, pasó a cliente, propuesta aceptada o rechazada, tope, pausa, respondió), errores de WhatsApp
  sin reintento automático, rastro en el historial del CRM.
- Justo antes de enviar se vuelven a revisar las condiciones de parada: una aprobación de hace días no le escribe a
  quien se dio de baja o agendó entretanto.

### 3.6 El reloj
- El flujo n8n «Retomar · Reloj y WhatsApp» pasa de «10:15 de lunes a viernes» a **cada hora**. En cada pasada,
  WordPress decide: detectar candidatos nuevos y pedir su preparación; enviar el correo semanal si es el día y la hora
  configurados y no se envió esta semana; y, dentro de la ventana hábil, enviar los aprobados (correo desde WordPress,
  WhatsApp devuelto a n8n como en la fase 1).
- La redacción con IA va en un flujo n8n nuevo, «Retomar · Redactar», con el patrón de los flujos del plan de trabajo
  (WordPress lo llama; n8n responde por callback con el secreto existente). Crear y activar flujos es parte del
  despliegue, con OK de Luis.

### 3.7 El panel
- Desaparece el botón **«Activar»**: ya nada sale sin la aprobación semanal. El panel queda para: ver la lista y el
  estado de cada uno (incluidos los nuevos `por_aprobar`, `aprobado` y `necesita_revision`, o los nombres que elija
  quien construye), agregar a mano, editar, pausar, dar de baja, marcar «Respondió», ver la presentación, «Compartir a
  mano», y **configurar el día y la hora del correo semanal y su destinatario**.
- «Vista previa» y «Enviar prueba al equipo» se mantienen.
- **Datos existentes:** medido el 18-oct, `wp_at_retomar` y `wp_at_retomar_bajas` están vacías en producción y el reloj
  está inactivo: no hay seguimientos `borrador` ni `activo` que migrar. La migración solo agrega lo nuevo.

## 4. Lo que no hace

- No envía nada sin la aprobación de Luis de ese envío.
- No pone montos ni precios en los textos ni en la presentación.
- No crea propuestas nuevas: la propuesta nueva de un prospecto con propuesta antigua es un trabajo de contenido
  aparte (§2.11).
- No detecta respuestas de correo ni de WhatsApp (sigue «Respondió» a mano).
- No le escribe a clientes, a propuestas rechazadas ni a quien está en la lista de bajas.
- No incluye por sí sola propuestas archivadas: para que entren, Luis las desarchiva o las agrega a mano.

## 5. Pruebas y salida a producción

- **WordPress (sitio local de pruebas):** detección (estados, 14 días, clientes, bajas, sin duplicar), validación de
  montos, enlaces firmados (firma, uso único, vencimiento, huella vieja), que el GET no ejecute nada, programación a la
  siguiente ventana, revisión de paradas justo antes de enviar, «Esta semana no», arrastre al correo siguiente, día y
  hora configurables, y que sin aprobación no salga nada aunque el reloj corra. Las suites de la fase 1 siguen pasando.
- **Renderer:** el camino `retomar` con encargos de imagen (con Higgsfield simulado en las pruebas), reutilización por
  manifiesto y respaldo cuando una foto falla; prueba en Chromium de que nada se salga de las láminas.
- **n8n:** los JSON de «Retomar · Redactar» y del reloj por hora, con sus scripts, como en la fase 1.
- **Piloto:** primero con el primer prospecto del piloto: correo semanal de prueba a Luis, Luis revisa, aprueba o pide
  cambios. El segundo entra cuando su propuesta nueva esté aprobada.
- **Despliegue** con el procedimiento de siempre (respaldo, subida, verificación desde afuera) y la autorización de
  Luis: WordPress por SSH; renderer con el ZIP que sube Luis a Easypanel; flujos n8n con OK.

## 6. Queda para quien lo construya

Nombres de funciones, archivos, estados y opciones; el esquema de la migración (si hace falta columna o tabla para las
aprobaciones); el diseño visual exacto del correo semanal y de las páginas de confirmación dentro del estilo AT y de la
maqueta aprobada; el prompt de la IA; y los detalles de los flujos n8n, siguiendo los patrones de `inc/retomar/`,
`inc/plan-trabajo/` y `N8N/plan-trabajo/`.
